<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Device;
use App\Models\WaterReading;
use App\Services\BillingStatementService;
use App\Services\WaterConsumptionService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;
use Throwable;

class WaterController extends Controller
{
    public function __construct(
        protected WaterConsumptionService $waterConsumptionService,
        protected BillingStatementService $audit
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

        $query = WaterReading::with(['notifications', 'user', 'device'])
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
        $validated = $request->validate([
            'period' => ['nullable', Rule::in(['daily', 'weekly', 'monthly'])],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date', 'after_or_equal:from'],
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:50'],
            'search' => ['nullable', 'string', 'max:255'],
        ]);

        $user = $request->user();
        $userId = $user?->isAdmin() === true && $request->filled('user_id')
            ? $request->integer('user_id')
            : ($user?->isAdmin() === true ? null : $user?->id);

        $period = $validated['period'] ?? 'daily';
        $from = isset($validated['from']) ? Carbon::parse($validated['from'])->startOfDay() : now()->subDays(30)->startOfDay();
        $to = isset($validated['to']) ? Carbon::parse($validated['to'])->endOfDay() : now()->endOfDay();

        $baseQuery = WaterReading::query()
            ->when($userId !== null, fn ($query) => $query->where('user_id', $userId))
            ->when(isset($validated['search']) && $validated['search'] !== '', function ($query) use ($validated): void {
                $search = '%'.trim($validated['search']).'%';
                $query->where(function ($subQuery) use ($search) {
                    $subQuery->where('reading_id', 'like', $search)
                        ->orWhere('device_name', 'like', $search)
                        ->orWhere('leak_detection_status', 'like', $search);
                });
            })
            ->whereBetween('recorded_at', [$from, $to]);

        $settings = $this->waterConsumptionService->resolveSettingsForUser($userId);
        $totalLiters = (float) $baseQuery->sum('liters_used');
        $totalCubicMeter = (float) $baseQuery->sum('cubic_meter');
        $totalEstimatedBill = (float) $baseQuery->sum('estimated_bill');
        $readingCount = (int) $baseQuery->count();
        $driver = DB::connection()->getDriverName();
        [$dailyPeriod, $weeklyPeriod, $monthlyPeriod, $hourExpression] = match ($driver) {
            'mysql', 'mariadb' => [
                'date(recorded_at)',
                'date_sub(date(recorded_at), interval weekday(recorded_at) day)',
                "date_format(recorded_at, '%Y-%m-01')",
                'hour(recorded_at)',
            ],
            'pgsql' => [
                'cast(recorded_at as date)',
                "cast(date_trunc('week', recorded_at) as date)",
                "to_char(date_trunc('month', recorded_at), 'YYYY-MM-01')",
                'extract(hour from recorded_at)',
            ],
            default => [
                'date(recorded_at)',
                "date(recorded_at, '-' || ((cast(strftime('%w', recorded_at) as integer) + 6) % 7) || ' days')",
                "date(recorded_at, 'start of month')",
                "cast(strftime('%H', recorded_at) as integer)",
            ],
        };

        $nightCondition = $settings->quiet_hours_start < $settings->quiet_hours_end
            ? "{$hourExpression} >= {$settings->quiet_hours_start} and {$hourExpression} < {$settings->quiet_hours_end}"
            : "{$hourExpression} >= {$settings->quiet_hours_start} or {$hourExpression} < {$settings->quiet_hours_end}";
        $aggregateColumns = "sum(liters_used) as total_liters, sum(cubic_meter) as total_cubic_meter, sum(estimated_bill) as estimated_bill, sum(case when is_possible_leak = 1 then 1 else 0 end) as leak_count, sum(case when is_threshold_exceeded = 1 then 1 else 0 end) as limit_count, sum(case when ({$nightCondition}) then 1 else 0 end) as night_count";
        $mapAggregates = static function ($rows) {
            return $rows->map(function ($row) {
                $condition = $row->leak_count > 0 ? 'leak' : ($row->limit_count > 0 ? 'threshold' : ($row->night_count > 0 ? 'night' : 'normal'));

                return [
                    'period' => $row->period,
                    'total_liters' => (float) $row->total_liters,
                    'total_cubic_meter' => (float) $row->total_cubic_meter,
                    'estimated_bill' => (float) $row->estimated_bill,
                    'leak_count' => (int) $row->leak_count,
                    'limit_count' => (int) $row->limit_count,
                    'night_count' => (int) $row->night_count,
                    'condition' => $condition,
                    'status' => $condition === 'leak' ? 'Leak Detected' : ($condition === 'threshold' ? 'Monthly Threshold Exceeded' : ($condition === 'night' ? 'Night-time Usage' : 'Normal')),
                ];
            })->values();
        };

        $dailyPaginator = (clone $baseQuery)
            ->selectRaw("{$dailyPeriod} as period, {$aggregateColumns}")
            ->groupBy(DB::raw($dailyPeriod))
            ->orderByDesc('period')
            ->paginate($validated['per_page'] ?? 10)
            ->withQueryString();
        $dailyItems = $mapAggregates($dailyPaginator->getCollection())->all();

        $weeklyItems = (clone $baseQuery)
            ->selectRaw("{$weeklyPeriod} as period, {$aggregateColumns}")
            ->groupBy(DB::raw($weeklyPeriod))
            ->orderByDesc('period')
            ->get()
            ->pipe($mapAggregates);

        $monthlyItems = (clone $baseQuery)
            ->selectRaw("{$monthlyPeriod} as period, {$aggregateColumns}")
            ->groupBy(DB::raw($monthlyPeriod))
            ->orderByDesc('period')
            ->get()
            ->pipe($mapAggregates);

        $historyMeta = [
            'current_page' => $dailyPaginator->currentPage(),
            'last_page' => $dailyPaginator->lastPage(),
            'from' => $dailyPaginator->firstItem(),
            'to' => $dailyPaginator->lastItem(),
            'total' => $dailyPaginator->total(),
        ];

        if ($period !== 'daily') {
            $perPage = $validated['per_page'] ?? 10;
            $currentPage = $validated['page'] ?? 1;
            $selectedItems = $period === 'weekly' ? $weeklyItems : $monthlyItems;
            $total = $selectedItems->count();
            $offset = ($currentPage - 1) * $perPage;
            $pagedItems = $selectedItems->slice($offset, $perPage)->values();

            if ($period === 'weekly') {
                $weeklyItems = $pagedItems;
            } else {
                $monthlyItems = $pagedItems;
            }

            $historyMeta = [
                'current_page' => $currentPage,
                'last_page' => max(1, (int) ceil($total / $perPage)),
                'from' => $pagedItems->isEmpty() ? null : $offset + 1,
                'to' => $pagedItems->isEmpty() ? null : min($offset + $perPage, $total),
                'total' => $total,
            ];
        }

        $chartItems = match ($period) {
            'weekly' => $weeklyItems,
            'monthly' => $monthlyItems,
            default => $dailyItems,
        };

        $chart = collect($chartItems)
            ->reverse()
            ->map(function ($item) use ($period) {
                $label = $period === 'weekly'
                    ? Carbon::parse($item['period'])->startOfWeek()->format('M j')
                    : ($period === 'monthly'
                        ? Carbon::parse($item['period'])->format('M Y')
                        : Carbon::parse($item['period'])->format('M j'));

                return [
                    'label' => $label,
                    'value' => (float) ($item['total_cubic_meter'] ?? $item['cubic_meter'] ?? 0),
                ];
            })
            ->values()
            ->all();

        $summary = [
            'from' => $from->toDateString(),
            'to' => $to->toDateString(),
            'reading_count' => $readingCount,
            'today_usage' => (float) (clone $baseQuery)->whereDate('recorded_at', now()->toDateString())->sum('liters_used'),
            'week_usage' => (float) (clone $baseQuery)->where('recorded_at', '>=', now()->startOfWeek())->sum('liters_used'),
            'month_usage' => (float) (clone $baseQuery)->where('recorded_at', '>=', now()->startOfMonth())->sum('liters_used'),
            'average_daily_usage' => $readingCount > 0 ? round($totalLiters / max(1, $from->diffInDays($to) + 1), 4) : 0,
            'estimated_bill' => round($totalEstimatedBill, 2),
            'water_rate' => (float) $settings->water_rate,
            'fixed_monthly_charge' => (float) $settings->fixed_monthly_charge,
            'subsidy_deduction' => (float) $settings->subsidy_deduction,
            'currency' => $settings->currency ?? 'PHP',
        ];

        return response()->json(['data' => [
            'daily' => $dailyItems,
            'weekly' => $weeklyItems,
            'monthly' => $monthlyItems,
            'chart' => $chart,
            'summary' => $summary,
            'meta' => $historyMeta,
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
            'reading_id' => ['required', 'string', 'max:150', 'regex:/\S/'],
            'device_name' => ['nullable', 'string', 'max:255'],
            'flow_rate' => ['required', 'numeric', 'min:0'],
            'liters_used' => ['required', 'numeric', 'min:0'],
            'total_water_liters' => ['nullable', 'numeric', 'min:0'],
            'cubic_meter' => ['nullable', 'numeric', 'min:0'],
            'water_limit_liters' => ['nullable', 'numeric', 'min:0'],
            'leak_detection_status' => ['nullable', 'string', 'max:50'],
            'monitoring_status' => ['nullable', Rule::in(['online', 'paused'])],
            'wifi_signal_strength' => ['nullable', 'integer', 'min:0', 'max:100'],
            'device_connection_status' => ['nullable', Rule::in(['connected', 'disconnected'])],
            'server_connection_status' => ['nullable', Rule::in(['connected', 'disconnected'])],
            'firmware_version' => ['nullable', 'string', 'max:50'],
            'recorded_at' => ['nullable', 'date'],
        ]);

        $deviceUser = $request->user();
        $registeredDevice = Device::query()->where('auth_user_id', $deviceUser->id)->first();
        if (! $registeredDevice || ! $deviceUser->isDevice() || ! $registeredDevice->household?->isHousehold()) {
            Log::warning('Rejected water telemetry from an unregistered device identity.', ['device_user_id' => $deviceUser->id]);

            return response()->json(['message' => 'This device is not registered with Smart Water.'], 403);
        }

        if ($registeredDevice->status !== 'active' || ! $deviceUser->isAccountActive()) {
            $this->recordTelemetryFailure($deviceUser, $registeredDevice, 'inactive_device');

            return response()->json(['message' => 'This municipal meter is not active.'], 403);
        }
        $targetUserId = $registeredDevice->household_id;

        if (isset($validated['user_id']) && (int) $validated['user_id'] !== (int) $targetUserId) {
            $this->recordTelemetryFailure($deviceUser, $registeredDevice, 'household_mismatch', $validated['reading_id']);

            return response()->json([
                'message' => 'This device may only submit readings for its assigned household.',
            ], 403);
        }

        $validated['user_id'] = $targetUserId;
        $validated['monitoring_status'] = $validated['monitoring_status'] ?? 'online';
        $validated['device_connection_status'] = $validated['device_connection_status'] ?? 'connected';
        $validated['device_id'] = $registeredDevice->id;

        try {
            $result = $this->waterConsumptionService->storeReading($validated);
        } catch (Throwable $exception) {
            $this->recordTelemetryFailure($deviceUser, $registeredDevice, 'processing_error', $validated['reading_id']);
            Log::error('Water telemetry processing failed.', [
                'device_id' => $registeredDevice->id,
                'reading_id_hash' => hash('sha256', $validated['reading_id']),
                'exception' => $exception::class,
            ]);
            throw $exception;
        }

        return response()->json([
            'message' => $result['duplicate'] ? 'Reading was already received.' : 'Water reading stored successfully.',
            'reading_id' => $result['reading']->reading_id,
            'duplicate' => $result['duplicate'],
            'is_possible_leak' => (bool) $result['reading']->is_possible_leak,
            'server_total_liters' => $result['server_total_liters'],
            'water_limit_liters' => $result['water_limit_liters'],
            'limit_reached' => $result['limit_reached'],
            'data' => [
                'reading' => $result['reading'],
                'notifications' => $result['notifications'],
            ],
        ], $result['duplicate'] ? 200 : 201);
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

    private function recordTelemetryFailure($deviceUser, Device $device, string $reason, ?string $readingId = null): void
    {
        $this->audit->recordAudit($deviceUser, 'device_sync_failed', 'A device synchronization attempt was rejected.', [
            'device_id' => $device->id,
            'reason' => $reason,
            'reading_id_hash' => $readingId ? hash('sha256', $readingId) : null,
        ], $device, 'failure');
    }
}
