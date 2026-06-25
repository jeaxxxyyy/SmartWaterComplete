<?php

namespace App\Services;

use App\Models\Billing;
use App\Models\Notification;
use App\Models\WaterReading;
use Carbon\Carbon;

class WaterConsumptionService
{
    public function resolveSettingsForUser(?int $userId): Billing
    {
        $settings = Billing::query()
            ->where('is_active', true)
            ->where(function ($query) use ($userId): void {
                $query->whereNull('user_id');

                if ($userId !== null) {
                    $query->orWhere('user_id', $userId);
                }
            })
            ->orderByRaw('CASE WHEN user_id IS NULL THEN 1 ELSE 0 END')
            ->latest('id')
            ->first();

        if ($settings instanceof Billing) {
            return $settings;
        }

        return new Billing([
            'water_rate' => 0,
            'subsidy_deduction' => 0,
            'threshold_liters' => 0,
            'leak_flow_rate_threshold' => 0,
            'quiet_hours_start' => 22,
            'quiet_hours_end' => 4,
            'is_active' => true,
        ]);
    }

    public function calculateEstimatedBill(float $cubicMeters, Billing $settings): float
    {
        $estimatedBill = ($cubicMeters * (float) $settings->water_rate) - (float) $settings->subsidy_deduction;

        return max($estimatedBill, 0);
    }

    public function isNightTime(?Carbon $recordedAt, Billing $settings): bool
    {
        $recordedAt ??= now();

        $hour = (int) $recordedAt->format('G');
        $start = (int) $settings->quiet_hours_start;
        $end = (int) $settings->quiet_hours_end;

        if ($start === $end) {
            return false;
        }

        if ($start < $end) {
            return $hour >= $start && $hour < $end;
        }

        return $hour >= $start || $hour < $end;
    }

    public function detectPossibleLeak(float $flowRate, Billing $settings, ?Carbon $recordedAt = null): bool
    {
        if ((float) $settings->leak_flow_rate_threshold <= 0) {
            return false;
        }

        return $flowRate >= (float) $settings->leak_flow_rate_threshold
            && $this->isNightTime($recordedAt, $settings);
    }

    public function storeReading(array $data): array
    {
        $settings = $this->resolveSettingsForUser($data['user_id']);
        $recordedAt = array_key_exists('recorded_at', $data) && $data['recorded_at'] !== null
            ? Carbon::parse($data['recorded_at'])
            : now();

        $estimatedBill = $this->calculateEstimatedBill($data['cubic_meter'], $settings);
        $isThresholdExceeded = (float) $settings->threshold_liters > 0
            && $data['liters_used'] >= (float) $settings->threshold_liters;
        $isPossibleLeak = $this->detectPossibleLeak($data['flow_rate'], $settings, $recordedAt);

        $reading = WaterReading::create([
            'user_id' => $data['user_id'],
            'flow_rate' => $data['flow_rate'],
            'liters_used' => $data['liters_used'],
            'cubic_meter' => $data['cubic_meter'],
            'estimated_bill' => $estimatedBill,
            'rate_per_cubic_meter' => (float) $settings->water_rate,
            'subsidy_deduction' => (float) $settings->subsidy_deduction,
            'threshold_liters' => $settings->threshold_liters,
            'is_threshold_exceeded' => $isThresholdExceeded,
            'is_possible_leak' => $isPossibleLeak,
            'recorded_at' => $recordedAt,
        ]);

        $notifications = collect();

        if ($isThresholdExceeded) {
            $notifications->push(Notification::create([
                'user_id' => $data['user_id'],
                'water_reading_id' => $reading->id,
                'type' => 'threshold_exceeded',
                'title' => 'Water usage threshold reached',
                'message' => 'Your water consumption has reached the configured threshold.',
                'metadata' => [
                    'liters_used' => $data['liters_used'],
                    'threshold_liters' => $settings->threshold_liters,
                ],
                'sent_at' => now(),
            ]));
        }

        if ($isPossibleLeak) {
            $notifications->push(Notification::create([
                'user_id' => $data['user_id'],
                'water_reading_id' => $reading->id,
                'type' => 'possible_leak',
                'title' => 'Possible water leak detected',
                'message' => 'Unusual water flow was detected during quiet hours.',
                'metadata' => [
                    'flow_rate' => $data['flow_rate'],
                    'leak_flow_rate_threshold' => $settings->leak_flow_rate_threshold,
                    'recorded_at' => $recordedAt->toIso8601String(),
                ],
                'sent_at' => now(),
            ]));
        }

        return [
            'reading' => $reading,
            'settings' => $settings,
            'notifications' => $notifications,
        ];
    }
}
