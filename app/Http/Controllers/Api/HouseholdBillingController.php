<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\BillingStatement;
use App\Models\WaterReading;
use App\Services\WaterConsumptionService;
use Illuminate\Http\Request;

class HouseholdBillingController extends Controller
{
    public function __construct(protected WaterConsumptionService $consumption) {}

    public function index(Request $request)
    {
        $user = $request->user();
        abort_unless($user?->isHousehold() && $user->isAccountActive(), 403, 'Household account access is required.');

        $from = now()->startOfMonth();
        $to = now()->endOfMonth();
        $settings = $this->consumption->resolveSettingsForUser($user->id);
        $hasRateConfiguration = $settings->exists === true;
        $statement = BillingStatement::query()
            ->where('user_id', $user->id)
            ->whereDate('billing_month', $from->toDateString())
            ->latest('id')
            ->first();
        $readings = WaterReading::query()
            ->where('user_id', $user->id)
            ->whereBetween('recorded_at', [$from, $to])
            ->latest('recorded_at')
            ->latest('id');
        $readingCount = (clone $readings)->count();
        $totalLiters = (float) (clone $readings)->sum('liters_used');
        $totalCubicMeter = (float) (clone $readings)->sum('cubic_meter');
        $latestReading = (clone $readings)->first();
        $meter = $user->devices()
            ->where('status', '!=', 'decommissioned')
            ->latest('last_seen_at')
            ->latest('id')
            ->first();

        $recordType = $statement !== null ? 'finalized' : ($readingCount > 0 && $hasRateConfiguration ? 'estimated' : 'unavailable');
        $calculation = $statement !== null
            ? [
                'total_liters' => (float) $statement->total_liters,
                'total_cubic_meter' => (float) $statement->total_cubic_meter,
                'water_rate' => (float) $statement->water_rate,
                'fixed_monthly_charge' => (float) ($statement->fixed_monthly_charge ?? 0),
                'subsidy_deduction' => (float) $statement->subsidy_deduction,
                'usage_charge' => max((float) $statement->current_charge - (float) ($statement->fixed_monthly_charge ?? 0) + (float) $statement->subsidy_deduction, 0),
                'current_charge' => (float) $statement->current_charge,
                'remaining_balance' => (float) $statement->remaining_balance,
                'currency' => $statement->currency ?? 'PHP',
                'effective_date' => optional($statement->generated_at)->toDateString(),
            ]
            : [
                'total_liters' => $totalLiters,
                'total_cubic_meter' => $totalCubicMeter,
                'water_rate' => (float) $settings->water_rate,
                'fixed_monthly_charge' => (float) ($settings->fixed_monthly_charge ?? 0),
                'subsidy_deduction' => (float) $settings->subsidy_deduction,
                'usage_charge' => $totalCubicMeter * (float) $settings->water_rate,
                'current_charge' => max(($totalCubicMeter * (float) $settings->water_rate) + (float) ($settings->fixed_monthly_charge ?? 0) - (float) $settings->subsidy_deduction, 0),
                'remaining_balance' => null,
                'currency' => $settings->currency ?? 'PHP',
                'effective_date' => optional($settings->effective_date)->toDateString(),
            ];

        $history = BillingStatement::query()
            ->where('user_id', $user->id)
            ->latest('billing_month')
            ->latest('id')
            ->limit(12)
            ->get()
            ->map(fn (BillingStatement $item): array => [
                'billing_month' => optional($item->billing_month)->toDateString(),
                'total_liters' => (float) $item->total_liters,
                'total_cubic_meter' => (float) $item->total_cubic_meter,
                'water_rate' => (float) $item->water_rate,
                'current_charge' => (float) $item->current_charge,
                'remaining_balance' => (float) $item->remaining_balance,
                'currency' => $item->currency ?? 'PHP',
                'status' => $item->status,
            ])
            ->values();

        return response()->json([
            'data' => [
                'period' => [
                    'from' => $from->toDateString(),
                    'to' => $to->toDateString(),
                    'label' => $from->format('F Y'),
                ],
                'record_type' => $recordType,
                'has_rate_configuration' => $hasRateConfiguration,
                'rate_effective_date' => optional($settings->effective_date)->toDateString(),
                'calculation' => $calculation,
                'reading_count' => $readingCount,
                'latest_reading' => $latestReading ? [
                    'recorded_at' => $latestReading->recorded_at,
                    'flow_rate' => (float) $latestReading->flow_rate,
                    'liters_used' => (float) $latestReading->liters_used,
                    'cubic_meter' => (float) $latestReading->cubic_meter,
                    'device_connection_status' => $latestReading->device_connection_status,
                ] : null,
                'meter' => $meter ? [
                    'name' => $meter->name,
                    'asset_number' => $meter->asset_number,
                    'serial_number' => $meter->serial_number,
                    'status' => $meter->status,
                    'is_online' => $meter->is_online,
                    'last_seen_at' => $meter->last_seen_at,
                ] : null,
                'statement_id' => $statement?->id,
                'statement_status' => $statement?->status,
                'history' => $history,
            ],
        ]);
    }
}
