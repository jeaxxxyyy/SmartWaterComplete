<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Device;
use App\Models\WaterReading;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class SystemHealthController extends Controller
{
    public function __invoke(): JsonResponse
    {
        $latestReading = WaterReading::query()
            ->with(['user:id,name,account_number', 'device:id,name,asset_number,status,last_seen_at'])
            ->latest('recorded_at')
            ->latest('id')
            ->first();
        $activeDevices = Device::query()->where('status', 'active')->get(['id', 'last_seen_at']);
        $offlineSeconds = (int) config('devices.offline_seconds');
        $onlineDevices = $activeDevices->filter(
            fn (Device $device): bool => $device->last_seen_at?->gte(now()->subSeconds($offlineSeconds)) === true,
        )->count();
        $integrity = [
            'readings_without_household' => DB::table('water_readings as readings')
                ->leftJoin('users', 'users.id', '=', 'readings.user_id')
                ->whereNull('users.id')
                ->count(),
            'readings_without_device' => DB::table('water_readings as readings')
                ->leftJoin('devices', 'devices.id', '=', 'readings.device_id')
                ->whereNotNull('readings.device_id')
                ->whereNull('devices.id')
                ->count(),
            'device_household_mismatches' => DB::table('water_readings as readings')
                ->join('devices', 'devices.id', '=', 'readings.device_id')
                ->whereColumn('readings.user_id', '<>', 'devices.household_id')
                ->count(),
        ];
        $integrityIssueCount = array_sum($integrity);
        $failedJobs = DB::table('failed_jobs')->count();
        $telemetryAgeSeconds = $latestReading?->recorded_at?->diffInSeconds(now());
        $foreignKeysEnforced = $this->foreignKeysAreEnforced();
        $hasTelemetryProblem = $activeDevices->isNotEmpty()
            && ($telemetryAgeSeconds === null || $telemetryAgeSeconds > $offlineSeconds);
        $isHealthy = $integrityIssueCount === 0
            && $failedJobs === 0
            && $foreignKeysEnforced
            && ! $hasTelemetryProblem;

        return response()->json([
            'data' => [
                'status' => $isHealthy ? 'healthy' : 'attention_required',
                'checked_at' => now()->toIso8601String(),
                'database' => [
                    'status' => 'connected',
                    'driver' => DB::connection()->getDriverName(),
                    'foreign_keys_enforced' => $foreignKeysEnforced,
                ],
                'telemetry' => [
                    'latest_reading_id' => $latestReading?->id,
                    'latest_reading_at' => $latestReading?->recorded_at?->toIso8601String(),
                    'age_seconds' => $telemetryAgeSeconds,
                    'readings_last_24_hours' => WaterReading::query()->where('recorded_at', '>=', now()->subDay())->count(),
                    'status' => $hasTelemetryProblem ? 'stale' : ($latestReading ? 'current' : 'waiting'),
                    'household' => $latestReading?->user,
                    'device' => $latestReading?->device,
                ],
                'devices' => [
                    'active' => $activeDevices->count(),
                    'online' => $onlineDevices,
                    'offline' => $activeDevices->count() - $onlineDevices,
                    'offline_after_seconds' => $offlineSeconds,
                ],
                'integrity' => [
                    ...$integrity,
                    'issue_count' => $integrityIssueCount,
                ],
                'queues' => [
                    'failed_jobs' => $failedJobs,
                ],
            ],
        ]);
    }

    private function foreignKeysAreEnforced(): bool
    {
        return match (DB::connection()->getDriverName()) {
            'mysql', 'mariadb' => (int) (DB::selectOne('select @@FOREIGN_KEY_CHECKS as enabled')->enabled ?? 0) === 1,
            'sqlite' => (int) (DB::selectOne('PRAGMA foreign_keys')->foreign_keys ?? 0) === 1,
            default => true,
        };
    }
}
