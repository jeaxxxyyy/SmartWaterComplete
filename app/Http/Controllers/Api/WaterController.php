<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\WaterReading;
use App\Models\Device;
use App\Services\WaterConsumptionService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class WaterController extends Controller
{
    public function __construct(
        protected WaterConsumptionService $waterConsumptionService
    ) {}

    public function index(Request $request)
    {
        $validated = $request->validate([
            'user_id' => ['nullable', 'integer', 'exists:users,id'],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date'],
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $query = WaterReading::with(['notifications', 'user'])
            ->latest('recorded_at')
            ->latest('id');

        if ($request->user()?->isAdmin() !== true) {
            $query->where('user_id', $request->user()?->id);
        } elseif (! empty($validated['user_id'])) {
            $query->where('user_id', $validated['user_id']);
        }

        if (! empty($validated['from'])) {
            $query->whereDate('recorded_at', '>=', $validated['from']);
        }

        if (! empty($validated['to'])) {
            $query->whereDate('recorded_at', '<=', $validated['to']);
        }

        return $query
            ->paginate($validated['per_page'] ?? 10)
            ->withQueryString();
    }

    public function history(Request $request)
    {
        $user = $request->user();
        $userId = $user?->isAdmin() === true && $request->filled('user_id')
            ? $request->integer('user_id')
            : ($user?->isAdmin() === true ? null : $user?->id);

        $daily = WaterReading::query()
            ->when($userId !== null, fn ($query) => $query->where('user_id', $userId))
            ->where('recorded_at', '>=', now()->subMonths(12)->startOfDay())
            ->selectRaw('date(recorded_at) as period, sum(liters_used) as total_liters, sum(cubic_meter) as total_cubic_meter, sum(estimated_bill) as estimated_bill, sum(case when is_possible_leak = 1 then 1 else 0 end) as leak_count, sum(case when is_threshold_exceeded = 1 then 1 else 0 end) as limit_count')
            ->groupBy(DB::raw('date(recorded_at)'))
            ->orderByDesc('period')
            ->get();

        $aggregate = function (string $period) use ($daily) {
            return $daily
                ->groupBy(fn ($row) => match ($period) {
                    'week' => Carbon::parse($row->period)->startOfWeek()->format('Y-m-d'),
                    'month' => Carbon::parse($row->period)->startOfMonth()->format('Y-m'),
                    default => $row->period,
                })
                ->map(fn ($rows, $label) => [
                    'period' => $label,
                    'total_liters' => round((float) $rows->sum('total_liters'), 4),
                    'total_cubic_meter' => round((float) $rows->sum('total_cubic_meter'), 6),
                    'estimated_bill' => round((float) $rows->sum('estimated_bill'), 2),
                    'leak_count' => (int) $rows->sum('leak_count'),
                    'limit_count' => (int) $rows->sum('limit_count'),
                ])
                ->values();
        };

        return response()->json(['data' => [
            'daily' => $aggregate('day')->take(31)->values(),
            'weekly' => $aggregate('week')->take(12)->values(),
            'monthly' => $aggregate('month')->take(12)->values(),
        ]]);
    }

    public function store(Request $request)
    {
        $accessToken = $request->user()?->currentAccessToken();

        if ($accessToken === null || ! $accessToken->can('device-upload')) {
            return response()->json([
                'message' => 'Device access is required.',
            ], 403);
        }

        $validated = $request->validate([
            'user_id' => ['nullable', 'integer', 'exists:users,id'],
            'device_name' => ['nullable', 'string', 'max:255'],
            'flow_rate' => ['required', 'numeric', 'min:0'],
            'liters_used' => ['nullable', 'numeric', 'min:0'],
            'total_water_liters' => ['nullable', 'numeric', 'min:0'],
            'cubic_meter' => ['nullable', 'numeric', 'min:0'],
            'water_limit_liters' => ['nullable', 'numeric', 'min:0'],
            'leak_detection_status' => ['nullable', 'string', 'max:50'],
            'monitoring_status' => ['nullable', 'string', 'max:50'],
            'wifi_signal_strength' => ['nullable', 'integer', 'min:0', 'max:100'],
            'device_connection_status' => ['nullable', 'string', 'max:50'],
            'server_connection_status' => ['nullable', 'string', 'max:50'],
            'recorded_at' => ['nullable', 'date'],
        ]);

        $device = $request->user();
        $registeredDevice = Device::query()->where('auth_user_id', $device->id)->first();
        if ($registeredDevice && $registeredDevice->status !== 'active') {
            return response()->json(['message' => 'This municipal meter is not active.'], 403);
        }
        $targetUserId = $registeredDevice?->household_id ?? $device->assigned_user_id ?? $device->id;

        if (isset($validated['user_id']) && (int) $validated['user_id'] !== (int) $targetUserId) {
            return response()->json([
                'message' => 'This device may only submit readings for its assigned household.',
            ], 403);
        }

        $validated['user_id'] = $targetUserId;

        $validated['liters_used'] = (float) ($validated['liters_used'] ?? $validated['total_water_liters'] ?? 0);
        $validated['cubic_meter'] = $validated['cubic_meter'] ?? ($validated['liters_used'] / 1000);
        $validated['water_limit_liters'] = $validated['water_limit_liters'] ?? null;
        $validated['monitoring_status'] = $validated['monitoring_status'] ?? 'online';
        $validated['device_connection_status'] = $validated['device_connection_status'] ?? 'connected';
        $validated['server_connection_status'] = $validated['server_connection_status'] ?? 'connected';
        $validated['leak_detection_status'] = $validated['leak_detection_status'] ?? 'Normal';

        $result = $this->waterConsumptionService->storeReading($validated);

        $registeredDevice?->update([
            'last_seen_at' => now(),
            'last_telemetry_at' => now(),
        ]);

        return response()->json([
            'message' => 'Water data saved successfully',
            'data' => [
                'reading' => $result['reading'],
                'notifications' => $result['notifications'],
            ],
        ], 201);
    }

    public function liveState(Request $request)
    {
        $user = $request->user();
        $userId = $user?->isAdmin() === true && $request->filled('user_id')
            ? $request->integer('user_id')
            : ($user?->isAdmin() === true ? null : $user?->id);

        return response()->json([
            'data' => $this->waterConsumptionService->liveStateForUser($userId),
        ]);
    }
}
