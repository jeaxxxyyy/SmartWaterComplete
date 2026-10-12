<?php

namespace App\Services;

use App\Models\Device;
use App\Models\User;
use App\Models\WaterReading;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class HouseholdConsumptionService
{
    private const PERIODS = ['daily', 'weekly', 'monthly'];

    private const INVALID_READING_STATUSES = [
        'duplicate', 'failed', 'invalid', 'malformed', 'rejected',
    ];

    public function forPeriod(User $user, string $period): array
    {
        abort_unless(in_array($period, self::PERIODS, true), 422, 'The selected consumption period is invalid.');

        $timezone = (string) config('app.timezone');
        $now = Carbon::now($timezone);
        [$currentFrom, $currentTo] = $this->periodRange($period, $now);
        [$trendFrom, $trendTo] = $this->trendRange($period, $now);
        $device = $this->assignedDevice($user);
        $base = $this->periodMetadata($period, $currentFrom, $currentTo, $timezone, $device);

        if ($device === null) {
            return $this->unavailableResponse($base, 'no_meter');
        }

        if ($device->status !== 'active') {
            return $this->unavailableResponse($base, 'pending_activation');
        }

        $readings = WaterReading::query()
            ->where('user_id', $user->id)
            ->where('device_id', $device->id)
            ->whereBetween('recorded_at', [$trendFrom, $trendTo])
            ->orderBy('recorded_at')
            ->orderBy('id')
            ->get();
        $baseline = $this->cumulativeBaseline($user->id, $device->id, $trendFrom);
        $events = $this->calculateEvents($readings, $baseline, $timezone);
        $buckets = $this->aggregateTrend($events, $period, $now, $timezone);
        $trendHasData = $buckets->contains(fn (array $bucket): bool => $bucket['has_data']);
        $hasAnyReadings = WaterReading::query()
            ->where('user_id', $user->id)
            ->where('device_id', $device->id)
            ->exists();
        $currentEvents = $events->filter(fn (array $event): bool => $event['recorded_at']->greaterThanOrEqualTo($currentFrom));
        $currentHasData = $currentEvents->isNotEmpty();
        $currentLiters = $currentHasData ? (float) $currentEvents->sum('liters') : null;
        $summary = $this->summary($buckets, $period, $currentFrom, $timezone);

        $availability = ! $trendHasData
            ? ($hasAnyReadings ? 'no_valid_readings' : 'waiting_for_first_reading')
            : ($currentHasData ? 'ready' : 'no_period_data');

        return [
            ...$base,
            'availability' => $availability,
            'has_readings' => $readings->isNotEmpty(),
            'has_data' => $currentHasData,
            'reading_count' => $currentEvents->count(),
            'valid_reading_count' => $events->count(),
            'liters' => $currentHasData ? round($currentLiters, 6) : null,
            'cubic_meters' => $currentHasData ? round($currentLiters / 1000, 8) : null,
            'trend' => $buckets->values()->all(),
            'summary' => $summary,
            'trend_from' => $trendFrom->toIso8601String(),
            'trend_to' => $trendTo->toIso8601String(),
        ];
    }

    private function assignedDevice(User $user): ?Device
    {
        return Device::query()
            ->where('household_id', $user->id)
            ->where('status', '!=', 'decommissioned')
            ->latest('id')
            ->first(['id', 'name', 'asset_number', 'serial_number', 'status']);
    }

    private function unavailableResponse(array $base, string $availability): array
    {
        return [
            ...$base,
            'availability' => $availability,
            'has_readings' => false,
            'has_data' => false,
            'reading_count' => 0,
            'valid_reading_count' => 0,
            'liters' => null,
            'cubic_meters' => null,
            'trend' => [],
            'summary' => $this->emptySummary(),
        ];
    }

    private function periodMetadata(string $period, Carbon $from, Carbon $to, string $timezone, ?Device $device): array
    {
        return [
            'period' => $period,
            'label' => match ($period) {
                'daily' => 'DAILY CONSUMPTION',
                'weekly' => 'WEEKLY CONSUMPTION',
                'monthly' => 'MONTHLY CONSUMPTION',
            },
            'caption' => match ($period) {
                'daily' => 'TODAY',
                'weekly' => 'THIS WEEK',
                'monthly' => 'THIS MONTH',
            },
            'from' => $from->toIso8601String(),
            'to' => $to->toIso8601String(),
            'timezone' => $timezone,
            'week_starts_on' => 'Monday',
            'meter' => $device ? [
                'id' => $device->id,
                'name' => $device->name,
                'asset_number' => $device->asset_number,
                'serial_number' => $device->serial_number,
                'status' => $device->status,
            ] : null,
        ];
    }

    private function periodRange(string $period, Carbon $now): array
    {
        return match ($period) {
            'daily' => [$now->copy()->startOfDay(), $now->copy()],
            'weekly' => [$this->startOfWeek($now), $now->copy()],
            'monthly' => [$now->copy()->startOfMonth(), $now->copy()],
        };
    }

    private function trendRange(string $period, Carbon $now): array
    {
        [$currentFrom] = $this->periodRange($period, $now);

        return match ($period) {
            'daily' => [$currentFrom->copy()->subDays(6), $now->copy()],
            'weekly' => [$currentFrom->copy()->subWeeks(7), $now->copy()],
            'monthly' => [$currentFrom->copy()->subMonths(11)->startOfMonth(), $now->copy()],
        };
    }

    private function startOfWeek(Carbon $date): Carbon
    {
        return $date->copy()->startOfWeek(Carbon::MONDAY)->startOfDay();
    }

    private function cumulativeBaseline(int $userId, int $deviceId, Carbon $from): ?WaterReading
    {
        return WaterReading::query()
            ->where('user_id', $userId)
            ->where('device_id', $deviceId)
            ->where('recorded_at', '<', $from)
            ->orderByDesc('recorded_at')
            ->orderByDesc('id')
            ->get()
            ->first(fn (WaterReading $reading): bool => $this->isValidReading($reading) && $this->cumulativeValue($reading) !== null);
    }

    private function calculateEvents(Collection $readings, ?WaterReading $baseline, string $timezone): Collection
    {
        $lastCumulative = $baseline ? $this->cumulativeValue($baseline) : null;
        $seenReadingIds = [];

        return $readings
            ->filter(function (WaterReading $reading) use (&$seenReadingIds): bool {
                if (! $this->isValidReading($reading)) {
                    return false;
                }

                $readingId = trim((string) $reading->reading_id);
                if ($readingId === '') {
                    return true;
                }
                if (isset($seenReadingIds[$readingId])) {
                    return false;
                }
                $seenReadingIds[$readingId] = true;

                return true;
            })
            ->map(function (WaterReading $reading) use (&$lastCumulative, $timezone): ?array {
                $interval = $this->intervalValue($reading);
                $liters = $interval;

                if ($liters === null) {
                    $cumulative = $this->cumulativeValue($reading);
                    if ($cumulative === null) {
                        return null;
                    }
                    if ($lastCumulative !== null) {
                        $liters = $cumulative >= $lastCumulative
                            ? $cumulative - $lastCumulative
                            : $cumulative;
                    }
                    $lastCumulative = $cumulative;
                }

                if ($liters === null) {
                    return null;
                }

                return [
                    'recorded_at' => $reading->recorded_at->copy()->timezone($timezone),
                    'liters' => max(0.0, (float) $liters),
                ];
            })
            ->filter()
            ->values();
    }

    private function aggregateTrend(Collection $events, string $period, Carbon $now, string $timezone): Collection
    {
        [$trendFrom] = $this->trendRange($period, $now);
        $bucketCount = match ($period) {
            'daily' => 7,
            'weekly' => 8,
            'monthly' => 12,
        };
        $buckets = collect(range(0, $bucketCount - 1))->mapWithKeys(function (int $offset) use ($period, $trendFrom, $timezone): array {
            $start = match ($period) {
                'daily' => $trendFrom->copy()->addDays($offset)->startOfDay(),
                'weekly' => $trendFrom->copy()->addWeeks($offset)->startOfWeek(Carbon::MONDAY)->startOfDay(),
                'monthly' => $trendFrom->copy()->addMonths($offset)->startOfMonth(),
            };
            $key = $this->bucketKey($start, $period);

            return [$key => [
                'period' => $key,
                'period_end' => $this->bucketEnd($start, $period)->toIso8601String(),
                'label' => $this->bucketLabel($start, $period),
                'short_label' => $this->bucketShortLabel($start, $period),
                'has_data' => false,
                'reading_count' => 0,
                'liters' => null,
                'cubic_meters' => null,
            ]];
        });

        foreach ($events as $event) {
            $start = $event['recorded_at']->copy();
            $key = $this->bucketKey($start, $period);
            if (! $buckets->has($key)) {
                continue;
            }
            $bucket = $buckets->get($key);
            $bucket['has_data'] = true;
            $bucket['reading_count']++;
            $bucket['liters'] = (float) ($bucket['liters'] ?? 0) + $event['liters'];
            $bucket['cubic_meters'] = round($bucket['liters'] / 1000, 8);
            $buckets->put($key, $bucket);
        }

        return $buckets->values()->map(fn (array $bucket): array => [
            ...$bucket,
            'liters' => $bucket['has_data'] ? round((float) $bucket['liters'], 6) : null,
            'cubic_meters' => $bucket['has_data'] ? round((float) $bucket['cubic_meters'], 8) : null,
        ]);
    }

    private function summary(Collection $buckets, string $period, Carbon $currentFrom, string $timezone): array
    {
        $currentKey = $this->bucketKey($currentFrom, $period);
        $previousStart = match ($period) {
            'daily' => $currentFrom->copy()->subDay(),
            'weekly' => $currentFrom->copy()->subWeek(),
            'monthly' => $currentFrom->copy()->subMonth()->startOfMonth(),
        };
        $previousKey = $this->bucketKey($previousStart, $period);
        $current = $buckets->firstWhere('period', $currentKey);
        $previous = $buckets->firstWhere('period', $previousKey);
        $dataBuckets = $buckets->filter(fn (array $bucket): bool => $bucket['has_data']);
        $currentValue = $current['cubic_meters'] ?? null;
        $previousValue = $previous['cubic_meters'] ?? null;
        $change = $previousValue !== null && $previousValue > 0 && $currentValue !== null
            ? round((($currentValue - $previousValue) / $previousValue) * 100, 1)
            : null;

        return [
            'current' => $current && $current['has_data'] ? $this->summaryValue($current) : null,
            'previous' => $previous && $previous['has_data'] ? $this->summaryValue($previous) : null,
            'change_percent' => $change,
            'direction' => $change === null || $currentValue === null || $previousValue === null
                ? null
                : ($currentValue > $previousValue ? 'increasing' : ($currentValue < $previousValue ? 'decreasing' : 'unchanged')),
            'average_cubic_meters' => $dataBuckets->isNotEmpty() ? round((float) $dataBuckets->avg('cubic_meters'), 8) : null,
            'period_label' => match ($period) {
                'daily' => 'day',
                'weekly' => 'week',
                'monthly' => 'month',
            },
            'timezone' => $timezone,
        ];
    }

    private function summaryValue(array $bucket): array
    {
        return [
            'label' => $bucket['label'],
            'period' => $bucket['period'],
            'liters' => $bucket['liters'],
            'cubic_meters' => $bucket['cubic_meters'],
        ];
    }

    private function emptySummary(): array
    {
        return [
            'current' => null,
            'previous' => null,
            'change_percent' => null,
            'direction' => null,
            'average_cubic_meters' => null,
            'period_label' => null,
        ];
    }

    private function isValidReading(WaterReading $reading): bool
    {
        if ($reading->recorded_at === null) {
            return false;
        }

        $payload = is_array($reading->device_payload) ? $reading->device_payload : [];
        $status = strtolower(trim((string) ($payload['reading_status'] ?? $payload['status'] ?? '')));
        if (in_array($status, self::INVALID_READING_STATUSES, true)) {
            return false;
        }

        return $this->intervalValue($reading) !== null || $this->cumulativeValue($reading) !== null;
    }

    private function intervalValue(WaterReading $reading): ?float
    {
        $payload = is_array($reading->device_payload) ? $reading->device_payload : [];
        foreach (['interval_liters', 'liters_used', 'consumption_liters'] as $key) {
            if (array_key_exists($key, $payload) && is_numeric($payload[$key]) && (float) $payload[$key] >= 0) {
                return (float) $payload[$key];
            }
        }

        if ($payload === [] && is_numeric($reading->liters_used) && (float) $reading->liters_used >= 0) {
            return (float) $reading->liters_used;
        }

        return null;
    }

    private function cumulativeValue(WaterReading $reading): ?float
    {
        $payload = is_array($reading->device_payload) ? $reading->device_payload : [];
        foreach (['total_liters', 'total_water_liters', 'cumulative_liters'] as $key) {
            if (array_key_exists($key, $payload) && is_numeric($payload[$key]) && (float) $payload[$key] >= 0) {
                return (float) $payload[$key];
            }
        }

        return null;
    }

    private function bucketKey(Carbon $date, string $period): string
    {
        return match ($period) {
            'daily' => $date->copy()->startOfDay()->toDateString(),
            'weekly' => $this->startOfWeek($date)->toDateString(),
            'monthly' => $date->format('Y-m'),
        };
    }

    private function bucketEnd(Carbon $start, string $period): Carbon
    {
        return match ($period) {
            'daily' => $start->copy()->endOfDay(),
            'weekly' => $start->copy()->addDays(6)->endOfDay(),
            'monthly' => $start->copy()->endOfMonth(),
        };
    }

    private function bucketLabel(Carbon $start, string $period): string
    {
        if ($period === 'weekly') {
            $end = $this->bucketEnd($start, $period);
            return $start->format('F j').'–'.$end->format('F j, Y');
        }

        return $period === 'monthly' ? $start->format('F Y') : $start->format('F j, Y');
    }

    private function bucketShortLabel(Carbon $start, string $period): string
    {
        return $period === 'monthly' ? $start->format('M Y') : ($period === 'weekly' ? $start->format('M j') : $start->format('M j'));
    }
}
