<?php

namespace App\Services;

use App\Models\Billing;
use App\Models\Notification;
use App\Models\WaterReading;
use Carbon\Carbon;
use Illuminate\Support\Arr;

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

        return $flowRate >= (float) $settings->leak_flow_rate_threshold;
    }

    public function storeReading(array $data): array
    {
        $settings = $this->resolveSettingsForUser($data['user_id']);
        $recordedAt = array_key_exists('recorded_at', $data) && $data['recorded_at'] !== null
            ? Carbon::parse($data['recorded_at'])
            : now();
        $litersUsed = (float) ($data['liters_used'] ?? $data['total_water_liters'] ?? 0);
        $waterLimitLiters = array_key_exists('water_limit_liters', $data) && $data['water_limit_liters'] !== null
            ? (float) $data['water_limit_liters']
            : (float) $settings->threshold_liters;
        $monitoringStatus = (string) ($data['monitoring_status'] ?? 'online');
        $deviceConnectionStatus = (string) ($data['device_connection_status'] ?? 'connected');
        $serverConnectionStatus = (string) ($data['server_connection_status'] ?? 'connected');
        $previousReading = WaterReading::query()
            ->where('user_id', $data['user_id'])
            ->latest('recorded_at')
            ->latest('id')
            ->first();
        $payload = Arr::only($data, [
            'device_name',
            'flow_rate',
            'liters_used',
            'total_water_liters',
            'cubic_meter',
            'water_limit_liters',
            'leak_detection_status',
            'monitoring_status',
            'wifi_signal_strength',
            'device_connection_status',
            'server_connection_status',
            'recorded_at',
            'user_id',
        ]);

        $estimatedBill = $this->calculateEstimatedBill($data['cubic_meter'], $settings);
        $isThresholdExceeded = $waterLimitLiters > 0 && $litersUsed >= $waterLimitLiters;
        // The configured municipal flow-rate threshold is authoritative. A device cannot
        // declare a leak merely because its accumulated usage reached the water limit.
        $isPossibleLeak = $this->detectPossibleLeak($data['flow_rate'], $settings, $recordedAt);
        $leakDetectionStatus = $isPossibleLeak ? 'Leak Detected' : 'Normal';
        $payload['leak_detection_status'] = $leakDetectionStatus;

        $reading = WaterReading::create([
            'user_id' => $data['user_id'],
            'device_name' => $data['device_name'] ?? null,
            'device_payload' => $payload,
            'leak_detection_status' => $leakDetectionStatus,
            'flow_rate' => $data['flow_rate'],
            'liters_used' => $litersUsed,
            'cubic_meter' => $data['cubic_meter'],
            'estimated_bill' => $estimatedBill,
            'rate_per_cubic_meter' => (float) $settings->water_rate,
            'subsidy_deduction' => (float) $settings->subsidy_deduction,
            'threshold_liters' => $settings->threshold_liters,
            'water_limit_liters' => $waterLimitLiters,
            'monitoring_status' => $monitoringStatus,
            'wifi_signal_strength' => $data['wifi_signal_strength'] ?? null,
            'device_connection_status' => $deviceConnectionStatus,
            'server_connection_status' => $serverConnectionStatus,
            'is_threshold_exceeded' => $isThresholdExceeded,
            'is_possible_leak' => $isPossibleLeak,
            'recorded_at' => $recordedAt,
        ]);

        $notifications = collect();

        if ($isThresholdExceeded && ! $previousReading?->is_threshold_exceeded) {
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

        $leakNoticeSentToday = Notification::query()
            ->where('user_id', $data['user_id'])
            ->where('type', 'possible_leak')
            ->whereDate('sent_at', now()->toDateString())
            ->exists();

        if ($isPossibleLeak && ! $leakNoticeSentToday) {
            $isReminder = $previousReading?->is_possible_leak === true;
            $notifications->push(Notification::create([
                'user_id' => $data['user_id'],
                'water_reading_id' => $reading->id,
                'type' => 'possible_leak',
                'title' => $isReminder ? 'Unresolved water leak reminder' : 'Possible water leak detected',
                'message' => $isReminder
                    ? 'The possible water leak remains unresolved. This is the daily reminder.'
                    : 'Water flow reached the configured municipal leak threshold.',
                'metadata' => [
                    'flow_rate' => $data['flow_rate'],
                    'leak_flow_rate_threshold' => $settings->leak_flow_rate_threshold,
                    'recorded_at' => $recordedAt->toIso8601String(),
                    'daily_reminder' => $isReminder,
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

    public function liveStateForUser(?int $userId): array
    {
        $settings = $this->resolveSettingsForUser($userId);

        $readingQuery = WaterReading::query()
            ->when($userId !== null, fn ($query) => $query->where('user_id', $userId))
            ->latest('recorded_at')
            ->latest('id');

        $latestReading = $readingQuery->first();
        $recordedAt = $latestReading?->recorded_at;
        $connectionAgeSeconds = $recordedAt?->diffInSeconds(now());
        $isLive = $latestReading !== null && ($connectionAgeSeconds === null || $connectionAgeSeconds <= 15);

        $waterLimitLiters = $latestReading?->water_limit_liters;
        if ($waterLimitLiters === null) {
            $waterLimitLiters = (float) $settings->threshold_liters;
        }

        return [
            'latest_reading' => $latestReading,
            'active_billing' => $settings,
            'device_state' => [
                'device_name' => $latestReading?->device_name,
                'water_limit_liters' => (float) $waterLimitLiters,
                'monitoring_status' => $latestReading?->monitoring_status ?? ($isLive ? 'online' : 'offline'),
                'wifi_signal_strength' => $latestReading?->wifi_signal_strength,
                'device_connection_status' => $latestReading?->device_connection_status ?? ($isLive ? 'connected' : 'offline'),
                'server_connection_status' => $latestReading?->server_connection_status ?? ($isLive ? 'connected' : 'offline'),
                'leak_detection_status' => $latestReading?->leak_detection_status ?? ($latestReading?->is_possible_leak ? 'Leak detected' : 'Normal'),
                'connection_age_seconds' => $connectionAgeSeconds,
                'is_live' => $isLive,
            ],
        ];
    }
}
