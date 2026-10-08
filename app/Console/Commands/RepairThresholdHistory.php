<?php

namespace App\Console\Commands;

use App\Models\Notification;
use App\Models\WaterReading;
use App\Services\BillingStatementService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class RepairThresholdHistory extends Command
{
    protected $signature = 'water:repair-threshold-history
        {--from-limit=1 : Repair readings stored at or below this incorrect limit in liters}
        {--to-limit=15000 : Correct monthly usage limit in liters}
        {--apply : Persist the repair; otherwise only show a dry-run summary}';

    protected $description = 'Repair historical usage-limit flags created by an incorrect monthly threshold';

    public function handle(BillingStatementService $audit): int
    {
        $fromLimit = (float) $this->option('from-limit');
        $toLimit = (float) $this->option('to-limit');

        if ($fromLimit < 0 || $toLimit < 0 || $toLimit <= $fromLimit) {
            $this->error('The corrected limit must be greater than the incorrect limit, and both must be non-negative.');

            return self::INVALID;
        }

        $candidates = WaterReading::query()
            ->where(function ($query) use ($fromLimit): void {
                $query->where('water_limit_liters', '<=', $fromLimit)
                    ->orWhere('threshold_liters', '<=', $fromLimit);
            })
            ->orderBy('user_id')
            ->orderBy('recorded_at')
            ->orderBy('id')
            ->get(['id', 'user_id', 'recorded_at', 'is_threshold_exceeded']);

        if ($candidates->isEmpty()) {
            $this->info('No readings use the incorrect threshold. Nothing needs repair.');

            return self::SUCCESS;
        }

        $candidateIds = $candidates->pluck('id');
        $candidateLookup = $candidateIds->flip();
        $shouldExceedIds = collect();
        $shouldNotExceedIds = collect();

        foreach ($candidates->groupBy(fn (WaterReading $reading): string => $reading->user_id.'|'.$reading->recorded_at->format('Y-m')) as $group) {
            /** @var WaterReading $representative */
            $representative = $group->first();
            $periodStart = $representative->recorded_at->copy()->startOfMonth();
            $periodEnd = $representative->recorded_at->copy()->endOfMonth();
            $runningLiters = 0.0;

            WaterReading::query()
                ->where('user_id', $representative->user_id)
                ->whereBetween('recorded_at', [$periodStart, $periodEnd])
                ->orderBy('recorded_at')
                ->orderBy('id')
                ->get(['id', 'liters_used'])
                ->each(function (WaterReading $reading) use (&$runningLiters, $candidateLookup, $shouldExceedIds, $shouldNotExceedIds, $toLimit): void {
                    $runningLiters += (float) $reading->liters_used;
                    if (! $candidateLookup->has($reading->id)) {
                        return;
                    }

                    ($toLimit > 0 && $runningLiters >= $toLimit ? $shouldExceedIds : $shouldNotExceedIds)->push($reading->id);
                });
        }

        $incorrectTrueFlags = $candidates
            ->filter(fn (WaterReading $reading): bool => $reading->is_threshold_exceeded && $shouldNotExceedIds->contains($reading->id))
            ->count();
        $incorrectFalseFlags = $candidates
            ->filter(fn (WaterReading $reading): bool => ! $reading->is_threshold_exceeded && $shouldExceedIds->contains($reading->id))
            ->count();
        $notificationIds = Notification::query()
            ->where('type', 'threshold_exceeded')
            ->whereIn('water_reading_id', $shouldNotExceedIds)
            ->pluck('id');

        $this->table(
            ['Check', 'Count'],
            [
                ['Readings using incorrect limit', $candidates->count()],
                ['Incorrect exceeded flags to clear', $incorrectTrueFlags],
                ['Missing exceeded flags to set', $incorrectFalseFlags],
                ['Invalid threshold notifications to archive', $notificationIds->count()],
            ],
        );

        if (! $this->option('apply')) {
            $this->warn('Dry run only. Re-run with --apply after confirming that a database backup exists.');

            return self::SUCCESS;
        }

        DB::transaction(function () use ($audit, $candidateIds, $shouldExceedIds, $shouldNotExceedIds, $notificationIds, $fromLimit, $toLimit): void {
            $candidateIds->chunk(500)->each(fn ($ids) => WaterReading::query()->whereIn('id', $ids)->update([
                'threshold_liters' => $toLimit,
                'water_limit_liters' => $toLimit,
            ]));
            $shouldNotExceedIds->chunk(500)->each(fn ($ids) => WaterReading::query()->whereIn('id', $ids)->update([
                'is_threshold_exceeded' => false,
            ]));
            $shouldExceedIds->chunk(500)->each(fn ($ids) => WaterReading::query()->whereIn('id', $ids)->update([
                'is_threshold_exceeded' => true,
            ]));

            if ($notificationIds->isNotEmpty()) {
                Notification::query()->whereIn('id', $notificationIds)->update([
                    'archived_at' => now(),
                    'user_deleted_at' => now(),
                ]);
            }

            $audit->recordAudit(null, 'threshold_history_repaired', 'Repaired readings created under an incorrect monthly usage limit.', [
                'incorrect_limit_liters' => $fromLimit,
                'corrected_limit_liters' => $toLimit,
                'reading_count' => $candidateIds->count(),
                'exceeded_after_repair_count' => $shouldExceedIds->count(),
                'archived_notification_count' => $notificationIds->count(),
            ]);
        });

        $this->info('Historical usage-limit data was repaired successfully.');

        return self::SUCCESS;
    }
}
