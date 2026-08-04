<?php

namespace App\Services;

use App\Models\Billing;
use App\Models\Device;
use App\Models\LeakAlert;
use App\Models\Notification;
use App\Models\User;
use App\Models\WaterReading;
use App\Services\BillingStatementService;
use Carbon\Carbon;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;

class WaterConsumptionService
{
    public function __construct(protected BillingStatementService $audit) {}

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
            ->where(function ($query): void {
                $query->whereNull('effective_date')
                    ->orWhereDate('effective_date', '<=', now()->toDateString());
            })
            ->orderByRaw('CASE WHEN user_id IS NULL THEN 1 ELSE 0 END')
            ->orderByRaw('CASE WHEN effective_date IS NULL THEN 0 ELSE 1 END DESC')
            ->orderByDesc('effective_date')
            ->latest('id')
            ->first();

        if ($settings instanceof Billing) {
            return $settings;
        }

        return new Billing([
            'water_rate' => 0,
            'fixed_monthly_charge' => 0,
            'subsidy_deduction' => 0,
            'currency' => 'PHP',
            'effective_date' => now()->toDateString(),
            'threshold_liters' => 0,
            'consumption_alert_enabled' => true,
            'consumption_alert_unit' => 'liters',
            'consumption_alert_behavior' => 'household',
            'leak_flow_rate_threshold' => 0,
            'leak_alert_enabled' => true,
            'leak_continuous_duration_minutes' => 0,
            'leak_alert_behavior' => 'household',
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

    public function detectPossibleLeak(float $flowRate, Billing $settings, ?Carbon $recordedAt = null, ?int $userId = null): bool
    {
        if ((float) $settings->leak_flow_rate_threshold <= 0) {
            return false;
        }

        if ($flowRate < (float) $settings->leak_flow_rate_threshold) {
            return false;
        }

        $duration = (int) ($settings->leak_continuous_duration_minutes ?? 0);
        if ($duration <= 0 || $userId === null) {
            return true;
        }

        $recordedAt ??= now();
        $cutoff = $recordedAt->copy()->subMinutes($duration);

        return WaterReading::query()
            ->where('user_id', $userId)
            ->where('recorded_at', '<=', $cutoff)
            ->where('flow_rate', '>=', (float) $settings->leak_flow_rate_threshold)
            ->exists()
            && ! WaterReading::query()
                ->where('user_id', $userId)
                ->whereBetween('recorded_at', [$cutoff, $recordedAt])
                ->where('flow_rate', '<', (float) $settings->leak_flow_rate_threshold)
                ->exists();
    }

    public function storeReading(array $data): array
    {
        return DB::transaction(function () use ($data): array {
            $device = isset($data['device_id'])
                ? Device::query()->lockForUpdate()->findOrFail($data['device_id'])
                : null;
            $household = User::query()->lockForUpdate()->findOrFail($data['user_id']);
            $settings = $this->resolveSettingsForUser($household->id);
            $recordedAt = array_key_exists('recorded_at', $data) && $data['recorded_at'] !== null
                ? Carbon::parse($data['recorded_at'])
                : now();

            if ($device && ! empty($data['reading_id'])) {
                $duplicate = WaterReading::query()
                    ->where('device_id', $device->id)
                    ->where('reading_id', $data['reading_id'])
                    ->first();

                if ($duplicate) {
                    $device->update([
                        'last_seen_at' => now(),
                        'last_telemetry_at' => now(),
                        ...(! empty($data['firmware_version']) ? ['firmware_version' => $data['firmware_version']] : []),
                    ]);
                    $waterLimitLiters = (float) ($duplicate->water_limit_liters ?? $settings->threshold_liters);

                    return [
                        'reading' => $duplicate,
                        'settings' => $settings,
                        'notifications' => collect(),
                        'duplicate' => true,
                        'server_total_liters' => $this->serverTotalLiters($household->id),
                        'water_limit_liters' => $waterLimitLiters,
                        'limit_reached' => (bool) $duplicate->is_threshold_exceeded,
                    ];
                }
            }

            $litersUsed = (float) ($data['liters_used'] ?? $data['total_water_liters'] ?? 0);
            $cubicMeters = $litersUsed / 1000;
            $waterLimitLiters = (float) $settings->threshold_liters;
            $monitoringStatus = (string) ($data['monitoring_status'] ?? 'online');
            $deviceConnectionStatus = (string) ($data['device_connection_status'] ?? 'connected');
            // Server receipt is the source of truth for server connectivity.
            $serverConnectionStatus = 'connected';
            $previousReading = WaterReading::query()
                ->where('user_id', $household->id)
                ->latest('recorded_at')
                ->latest('id')
                ->first();
            $periodLiters = (float) WaterReading::query()
                ->where('user_id', $household->id)
                ->whereBetween('recorded_at', [$recordedAt->copy()->startOfMonth(), $recordedAt->copy()->endOfMonth()])
                ->sum('liters_used');
            $payload = Arr::only($data, [
                'reading_id',
                'device_name',
                'firmware_version',
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
                'device_id',
            ]);

            $estimatedBill = $this->calculateEstimatedBill($cubicMeters, $settings);
            $isThresholdExceeded = $waterLimitLiters > 0 && ($periodLiters + $litersUsed) >= $waterLimitLiters;
            // The configured municipal flow-rate threshold is authoritative. A device cannot
            // declare a leak merely because its accumulated usage reached the water limit.
            $isPossibleLeak = $this->detectPossibleLeak((float) $data['flow_rate'], $settings, $recordedAt, $household->id);
            $leakDetectionStatus = $isPossibleLeak ? 'Leak Detected' : 'Normal';
            $payload['leak_detection_status'] = $leakDetectionStatus;
            $payload['server_total_liters'] = $periodLiters + $litersUsed;
            $payload['server_water_limit_liters'] = $waterLimitLiters;

            $reading = WaterReading::create([
                'user_id' => $household->id,
                'device_id' => $device?->id,
                'reading_id' => $data['reading_id'] ?? null,
                'device_name' => $device?->name ?? ($data['device_name'] ?? null),
                'device_payload' => $payload,
                'leak_detection_status' => $leakDetectionStatus,
                'flow_rate' => $data['flow_rate'],
                'liters_used' => $litersUsed,
                'cubic_meter' => $cubicMeters,
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

            if ($device) {
                $device->update([
                    'last_seen_at' => now(),
                    'last_telemetry_at' => now(),
                    ...(! empty($data['firmware_version']) ? ['firmware_version' => $data['firmware_version']] : []),
                ]);
            }

            $notifications = collect();
            $leakNotification = null;

            if ($isThresholdExceeded && ! $previousReading?->is_threshold_exceeded
                && ($settings->consumption_alert_enabled ?? true)
                && ($settings->consumption_alert_behavior ?? 'household') !== 'disabled') {
                $thresholdNotification = Notification::create([
                    'user_id' => $household->id,
                    'water_reading_id' => $reading->id,
                    'type' => 'threshold_exceeded',
                    'title' => 'Water usage threshold reached',
                    'message' => 'Your water consumption has reached the configured threshold.',
                    'metadata' => [
                        'liters_used' => $periodLiters + $litersUsed,
                        'threshold_liters' => $waterLimitLiters,
                    ],
                    'sent_at' => now(),
                ]);
                $notifications->push($thresholdNotification);
                if (($settings->consumption_alert_behavior ?? 'household') === 'household_admin') {
                    $this->notifyAdministrators($thresholdNotification, 'Consumption threshold reached for '.$household->name.'.');
                }
            }

            $leakNoticeSentToday = Notification::query()
                ->where('user_id', $household->id)
                ->where('type', 'possible_leak')
                ->whereDate('sent_at', now()->toDateString())
                ->exists();

            if ($isPossibleLeak && ! $leakNoticeSentToday
                && ($settings->leak_alert_enabled ?? true)
                && ($settings->leak_alert_behavior ?? 'household') !== 'disabled') {
                $isReminder = $previousReading?->is_possible_leak === true;
                $leakNotification = Notification::create([
                    'user_id' => $household->id,
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
                ]);
                $notifications->push($leakNotification);
                if (($settings->leak_alert_behavior ?? 'household') === 'household_admin') {
                    $this->notifyAdministrators($leakNotification, 'Possible water leak detected for '.$household->name.'.');
                }
            }

            if ($isPossibleLeak) {
                $alert = $previousReading?->is_possible_leak === true
                    ? LeakAlert::query()
                        ->where('user_id', $household->id)
                        ->whereIn('status', LeakAlert::ACTIVE_STATUSES)
                        ->latest('detected_at')
                        ->first()
                    : null;

                if ($alert instanceof LeakAlert) {
                    $alert->update([
                        'latest_water_reading_id' => $reading->id,
                        'last_detected_at' => $recordedAt,
                        ...($leakNotification instanceof Notification ? [
                            'notification_id' => $leakNotification->id,
                            'notified_at' => $leakNotification->sent_at,
                        ] : []),
                    ]);
                } else {
                    $alert = LeakAlert::create([
                        'user_id' => $household->id,
                        'first_water_reading_id' => $reading->id,
                        'latest_water_reading_id' => $reading->id,
                        'notification_id' => $leakNotification?->id,
                        'status' => LeakAlert::STATUS_DETECTED,
                        'detected_at' => $recordedAt,
                        'last_detected_at' => $recordedAt,
                        'notified_at' => $leakNotification?->sent_at,
                    ]);
                    $this->audit->recordAudit(null, 'leak_alert_created', 'A possible water leak alert was created from meter telemetry.', [
                        'household_id' => $household->id,
                        'water_reading_id' => $reading->id,
                        'flow_rate' => $data['flow_rate'],
                    ], $alert);
                }
            }

            return [
                'reading' => $reading,
                'settings' => $settings,
                'notifications' => $notifications,
                'duplicate' => false,
                'server_total_liters' => $this->serverTotalLiters($household->id),
                'water_limit_liters' => $waterLimitLiters,
                'limit_reached' => $isThresholdExceeded,
            ];
        });
    }

    public function serverTotalLiters(int $userId): float
    {
        return (float) WaterReading::query()->where('user_id', $userId)->sum('liters_used');
    }

    private function notifyAdministrators(Notification $source, string $message): void
    {
        User::query()->where('role', 'admin')->where('is_active', true)->each(function (User $admin) use ($source, $message): void {
            Notification::create([
                'user_id' => $admin->id,
                'water_reading_id' => $source->water_reading_id,
                'type' => $source->type,
                'title' => 'Administrator alert: '.$source->title,
                'message' => $message,
                'metadata' => array_merge($source->metadata ?? [], ['household_notification_id' => $source->id]),
                'sent_at' => $source->sent_at,
            ]);
        });
    }

    public function liveStateForUser(?int $userId): array
    {
        $settings = $this->resolveSettingsForUser($userId);
        $device = $userId === null
            ? null
            : Device::query()->where('household_id', $userId)->latest('id')->first();

        $readingQuery = WaterReading::query()
            ->when($userId !== null, fn ($query) => $query->where('user_id', $userId))
            ->latest('recorded_at')
            ->latest('id');

        $latestReading = $readingQuery->first();
        $lastSeenAt = $device?->last_seen_at ?? $latestReading?->recorded_at;
        $connectionAgeSeconds = $lastSeenAt?->diffInSeconds(now());
        $isLive = $device?->is_online === true
            || ($device === null && $latestReading !== null && ($connectionAgeSeconds === null || $connectionAgeSeconds <= config('devices.offline_seconds')));

        $waterLimitLiters = $latestReading?->water_limit_liters;
        if ($waterLimitLiters === null) {
            $waterLimitLiters = (float) $settings->threshold_liters;
        }

        return [
            'latest_reading' => $latestReading,
            'active_billing' => $settings,
            'device_state' => [
                'device_id' => $device?->id,
                'device_name' => $device?->name ?? $latestReading?->device_name,
                'water_limit_liters' => (float) $waterLimitLiters,
                'monitoring_status' => $latestReading?->monitoring_status ?? ($isLive ? 'online' : 'offline'),
                'wifi_signal_strength' => $latestReading?->wifi_signal_strength,
                'device_connection_status' => $isLive ? 'connected' : 'offline',
                'server_connection_status' => $isLive ? 'connected' : 'offline',
                'leak_detection_status' => $latestReading?->leak_detection_status ?? ($latestReading?->is_possible_leak ? 'Leak detected' : 'Normal'),
                'connection_age_seconds' => $connectionAgeSeconds,
                'last_seen_at' => $lastSeenAt,
                'is_live' => $isLive,
            ],
        ];
    }
}
