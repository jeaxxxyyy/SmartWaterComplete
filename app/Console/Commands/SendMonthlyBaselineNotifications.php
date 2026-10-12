<?php

namespace App\Console\Commands;

use App\Models\Notification;
use App\Models\User;
use App\Models\WaterReading;
use Carbon\Carbon;
use Illuminate\Console\Command;

class SendMonthlyBaselineNotifications extends Command
{
    protected $signature = 'water:send-monthly-baseline-notifications
                            {--from=2026-08 : First reporting month (YYYY-MM)}
                            {--to=2026-10 : Last reporting month (YYYY-MM)}
                            {--baseline=2026-07 : The 100% baseline month (YYYY-MM)}';

    protected $description = 'Send one July-baseline consumption notification for each household and reporting month.';

    public function handle(): int
    {
        try {
            $from = Carbon::createFromFormat('!Y-m', (string) $this->option('from'), config('app.timezone'))->startOfMonth();
            $to = Carbon::createFromFormat('!Y-m', (string) $this->option('to'), config('app.timezone'))->startOfMonth();
            $baselineStart = Carbon::createFromFormat('!Y-m', (string) $this->option('baseline'), config('app.timezone'))->startOfMonth();
        } catch (\Throwable) {
            $this->error('Use YYYY-MM for --from, --to, and --baseline.');

            return self::FAILURE;
        }

        if ($from->greaterThan($to)) {
            $this->error('The first reporting month must not be after the last reporting month.');

            return self::FAILURE;
        }

        $sent = 0;
        $skipped = 0;
        $months = collect();
        for ($month = $from->copy(); $month->lessThanOrEqualTo($to); $month->addMonth()) {
            $months->push($month->copy());
        }

        User::query()
            ->where('role', 'user')
            ->with('notificationPreference')
            ->orderBy('id')
            ->each(function (User $household) use ($baselineStart, $months, &$sent, &$skipped): void {
                if ($household->notificationPreference?->consumption_alerts === false) {
                    $skipped += $months->count();

                    return;
                }

                $baselineLiters = (float) WaterReading::query()
                    ->where('user_id', $household->id)
                    ->whereBetween('recorded_at', [$baselineStart, $baselineStart->copy()->endOfMonth()])
                    ->sum('liters_used');

                if ($baselineLiters <= 0) {
                    $skipped += $months->count();

                    return;
                }

                $months->each(function (Carbon $month) use ($household, $baselineStart, $baselineLiters, &$sent, &$skipped): void {
                    $monthKey = $month->format('Y-m');
                    if (Notification::query()
                        ->where('user_id', $household->id)
                        ->where('type', 'consumption_change')
                        ->where('metadata->comparison_month', $monthKey)
                        ->exists()) {
                        $skipped++;

                        return;
                    }

                    $readings = WaterReading::query()
                        ->where('user_id', $household->id)
                        ->whereBetween('recorded_at', [$month, $month->copy()->endOfMonth()]);
                    $liters = (float) (clone $readings)->sum('liters_used');
                    $latestReading = $readings->latest('recorded_at')->latest('id')->first();
                    if (! $latestReading) {
                        $skipped++;

                        return;
                    }

                    $percentage = ($liters / $baselineLiters) * 100;
                    $change = $percentage - 100;
                    $direction = $change > 0 ? 'increase' : ($change < 0 ? 'decrease' : 'unchanged');
                    $percentageLabel = number_format($percentage, 2);
                    $changeLabel = number_format(abs($change), 2);
                    $comparisonMessage = match ($direction) {
                        'increase' => 'Your water use for '.$month->format('F Y').' was '.$changeLabel.'% higher than your July baseline.',
                        'decrease' => 'Your water use for '.$month->format('F Y').' was '.$changeLabel.'% lower than your July baseline.',
                        default => 'Your water use for '.$month->format('F Y').' matches your July baseline.',
                    };

                    Notification::create([
                        'user_id' => $household->id,
                        'water_reading_id' => $latestReading->id,
                        'type' => 'consumption_change',
                        'title' => $month->format('F Y').' water consumption update',
                        'message' => $comparisonMessage,
                        'metadata' => [
                            'comparison_month' => $monthKey,
                            'baseline_month' => $baselineStart->format('Y-m'),
                            'baseline_liters' => round($baselineLiters, 4),
                            'current_liters' => round($liters, 4),
                            'usage_percentage' => round($percentage, 2),
                            'percentage_change' => round($change, 2),
                            'absolute_percentage_change' => round(abs($change), 2),
                            'change_direction' => $direction,
                            'notification_basis' => 'july_baseline_backfill',
                        ],
                        'sent_at' => now(),
                    ]);
                    $sent++;
                });
            });

        $this->info("Sent {$sent} monthly baseline notification(s); skipped {$skipped}.");

        return self::SUCCESS;
    }
}
