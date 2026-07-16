<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Billing;
use App\Models\BillingStatement;
use App\Models\Notification;
use App\Models\User;
use App\Models\WaterReading;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ReportController extends Controller
{
    public function summary(Request $request)
    {
        $user = $request->user();
        $isAdmin = $user?->isAdmin() === true;
        $userId = $isAdmin && $request->filled('user_id')
            ? $request->integer('user_id')
            : ($isAdmin ? null : $user?->id);

        $currentMonthStart = now()->startOfMonth();
        $currentMonthEnd = now()->endOfMonth();

        $readingQuery = WaterReading::query()
            ->when($userId !== null, fn ($query) => $query->where('user_id', $userId))
            ->whereBetween('recorded_at', [$currentMonthStart, $currentMonthEnd]);

        $notificationQuery = Notification::query()
            ->when($userId !== null, fn ($query) => $query->where('user_id', $userId))
            ->when(! $isAdmin, fn ($query) => $query->whereNull('user_deleted_at'));

        $statement = BillingStatement::query()
            ->when($userId !== null, fn ($query) => $query->where('user_id', $userId))
            ->latest('billing_month')
            ->first();

        $activeBilling = Billing::query()
            ->where('is_active', true)
            ->when($userId !== null, fn ($query) => $query->where(function ($builder) use ($userId): void {
                $builder->whereNull('user_id')->orWhere('user_id', $userId);
            }))
            ->latest('id')
            ->first();

        $monthExpression = match (DB::connection()->getDriverName()) {
            'mysql', 'mariadb' => "DATE_FORMAT(recorded_at, '%Y-%m')",
            'pgsql' => "TO_CHAR(recorded_at, 'YYYY-MM')",
            default => "strftime('%Y-%m', recorded_at)",
        };

        $monthlyUsage = WaterReading::query()
            ->when($userId !== null, fn ($query) => $query->where('user_id', $userId))
            ->where('recorded_at', '>=', now()->subMonths(11)->startOfMonth())
            ->selectRaw("{$monthExpression} as month, sum(liters_used) as total_liters, sum(cubic_meter) as total_cubic_meter, sum(estimated_bill) as estimated_bill")
            ->groupByRaw($monthExpression)
            ->orderBy('month')
            ->get()
            ->keyBy('month');

        $monthlySeries = collect(range(11, 0))
            ->map(function (int $monthsAgo) use ($monthlyUsage) {
                $date = now()->subMonths($monthsAgo);
                $monthKey = $date->format('Y-m');
                $row = $monthlyUsage->get($monthKey);

                return [
                    'month' => $monthKey,
                    'label' => $date->format('M Y'),
                    'total_liters' => (float) ($row->total_liters ?? 0),
                    'total_cubic_meter' => (float) ($row->total_cubic_meter ?? 0),
                    'estimated_bill' => (float) ($row->estimated_bill ?? 0),
                ];
            })
            ->values();

        return response()->json([
            'data' => [
                'current_month' => [
                    'reading_count' => $readingQuery->count(),
                    'total_liters' => (float) $readingQuery->sum('liters_used'),
                    'total_cubic_meter' => (float) $readingQuery->sum('cubic_meter'),
                    'estimated_bill' => (float) $readingQuery->sum('estimated_bill'),
                    'latest_reading' => WaterReading::query()
                        ->when($userId !== null, fn ($query) => $query->where('user_id', $userId))
                        ->latest('recorded_at')
                        ->latest('id')
                        ->first(),
                ],
                'notifications' => [
                    'total' => $notificationQuery->count(),
                    'unread' => (clone $notificationQuery)->where('is_read', false)->count(),
                    'latest' => (clone $notificationQuery)->latest()->limit(5)->get(),
                ],
                'billing_statement' => $statement,
                'active_billing' => $activeBilling,
                'monthly_usage' => $monthlySeries,
            ],
        ]);
    }

    public function consumption(Request $request)
    {
        $validated = $request->validate([
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date'],
            'user_id' => ['nullable', 'integer', 'exists:users,id'],
        ]);

        $from = isset($validated['from']) ? Carbon::parse($validated['from'])->startOfDay() : now()->startOfMonth();
        $to = isset($validated['to']) ? Carbon::parse($validated['to'])->endOfDay() : now()->endOfDay();

        $query = WaterReading::query()
            ->whereBetween('recorded_at', [$from, $to])
            ->when(isset($validated['user_id']), fn ($builder) => $builder->where('user_id', $validated['user_id']));

        $summary = [
            'reading_count' => (clone $query)->count(),
            'total_liters' => (float) (clone $query)->sum('liters_used'),
            'total_cubic_meter' => (float) (clone $query)->sum('cubic_meter'),
            'total_estimated_bill' => (float) (clone $query)->sum('estimated_bill'),
            'threshold_exceeded_count' => (clone $query)->where('is_threshold_exceeded', true)->count(),
            'possible_leak_count' => (clone $query)->where('is_possible_leak', true)->count(),
        ];

        $daily = (clone $query)
            ->selectRaw('date(recorded_at) as day, sum(liters_used) as liters_used, sum(cubic_meter) as cubic_meter, sum(estimated_bill) as estimated_bill')
            ->groupBy(DB::raw('date(recorded_at)'))
            ->orderBy('day')
            ->get();

        return response()->json([
            'data' => [
                'summary' => $summary,
                'daily' => $daily,
            ],
        ]);
    }

    public function notifications(Request $request)
    {
        $validated = $request->validate([
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date'],
            'user_id' => ['nullable', 'integer', 'exists:users,id'],
        ]);

        $from = isset($validated['from']) ? Carbon::parse($validated['from'])->startOfDay() : now()->startOfMonth();
        $to = isset($validated['to']) ? Carbon::parse($validated['to'])->endOfDay() : now()->endOfDay();

        $query = Notification::query()
            ->whereBetween('created_at', [$from, $to])
            ->when(isset($validated['user_id']), fn ($builder) => $builder->where('user_id', $validated['user_id']));

        return response()->json([
            'data' => [
                'total' => (clone $query)->count(),
                'unread' => (clone $query)->where('is_read', false)->count(),
                'items' => $query->latest()->get(),
            ],
        ]);
    }

    public function leaks()
    {
        $householdIds = Notification::query()
            ->where('type', 'possible_leak')
            ->distinct()
            ->pluck('user_id');

        $items = User::query()
            ->whereIn('id', $householdIds)
            ->where('role', 'user')
            ->get(['id', 'name', 'email', 'account_number', 'address'])
            ->map(function (User $user): array {
                $latestReading = WaterReading::query()
                    ->where('user_id', $user->id)
                    ->latest('recorded_at')
                    ->latest('id')
                    ->first();
                $latestNotice = Notification::query()
                    ->where('user_id', $user->id)
                    ->where('type', 'possible_leak')
                    ->latest('sent_at')
                    ->first();

                return [
                    'user' => $user,
                    'status' => $latestReading?->is_possible_leak ? 'unresolved' : 'resolved',
                    'flow_rate' => (float) ($latestReading?->flow_rate ?? 0),
                    'last_reading_at' => $latestReading?->recorded_at,
                    'last_notice_at' => $latestNotice?->sent_at,
                    'last_notice_title' => $latestNotice?->title,
                ];
            })
            ->sortByDesc(fn (array $item) => $item['status'] === 'unresolved')
            ->values();

        return response()->json(['data' => $items]);
    }
}
