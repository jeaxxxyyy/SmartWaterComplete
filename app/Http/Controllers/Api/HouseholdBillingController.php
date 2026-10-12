<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\BillingStatement;
use App\Models\Device;
use App\Services\WaterConsumptionService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

class HouseholdBillingController extends Controller
{
    public function __construct(protected WaterConsumptionService $consumption) {}

    public function index(Request $request)
    {
        $user = $request->user();
        abort_unless($user?->isHousehold() && $user->isAccountActive(), 403, 'Household account access is required.');

        [$periodStart, $periodEnd] = $this->consumption->currentBillingPeriod();
        $periodIsCurrent = true;
        $meter = null;

        try {
            // The authenticated household is the only source of ownership. Do not
            // accept a household or meter identifier from the browser.
            $meter = Device::query()
                ->where('household_id', $user->id)
                ->where('status', '!=', 'decommissioned')
                ->latest('last_seen_at')
                ->latest('id')
                ->first();

            if (! $meter) {
                return response()->json([
                    'success' => false,
                    'message' => 'No active water meter is assigned to this household.',
                ], 404);
            }

            $statement = BillingStatement::query()
                ->where('user_id', $user->id)
                ->whereDate('billing_month', $periodStart->toDateString())
                ->latest('id')
                ->first();

            // Telemetry stores interval usage in liters_used. The device payload
            // may also contain a cumulative total_water_liters, but that value is
            // never summed for the billing estimate.
            $periodAggregate = $meter->readings()
                ->whereBetween('recorded_at', [$periodStart, $periodEnd])
                ->whereNotNull('recorded_at')
                ->where('liters_used', '>=', 0)
                ->selectRaw('COUNT(*) as reading_count, COALESCE(SUM(liters_used), 0) as total_liters')
                ->first();
            $readingCount = (int) ($periodAggregate?->reading_count ?? 0);
            $totalLiters = round((float) ($periodAggregate?->total_liters ?? 0), 6);

            // At the beginning of a new month, the current period can be empty
            // even though the meter has valid historical telemetry. Keep the
            // household billing card useful by showing the latest metered month
            // until the first reading for the current month arrives.
            if (! $statement && $readingCount === 0) {
                $latestPriorReading = $meter->readings()
                    ->whereNotNull('recorded_at')
                    ->where('recorded_at', '<', $periodStart)
                    ->latest('recorded_at')
                    ->latest('id')
                    ->first();

                if ($latestPriorReading) {
                    $periodStart = $latestPriorReading->recorded_at->copy()->startOfMonth();
                    $periodEnd = $latestPriorReading->recorded_at->copy()->endOfMonth();
                    $periodIsCurrent = false;

                    $statement = BillingStatement::query()
                        ->where('user_id', $user->id)
                        ->whereDate('billing_month', $periodStart->toDateString())
                        ->latest('id')
                        ->first();

                    $periodAggregate = $meter->readings()
                        ->whereBetween('recorded_at', [$periodStart, $periodEnd])
                        ->whereNotNull('recorded_at')
                        ->where('liters_used', '>=', 0)
                        ->selectRaw('COUNT(*) as reading_count, COALESCE(SUM(liters_used), 0) as total_liters')
                        ->first();
                    $readingCount = (int) ($periodAggregate?->reading_count ?? 0);
                    $totalLiters = round((float) ($periodAggregate?->total_liters ?? 0), 6);
                }
            }

            $settingsEffectiveOn = $periodIsCurrent ? now() : $periodEnd;
            $settings = $this->consumption->resolveSettingsForUser($user->id, $settingsEffectiveOn);
            $hasRateConfiguration = $settings->exists === true;

            if (! $statement && ! $hasRateConfiguration) {
                return response()->json([
                    'success' => false,
                    'message' => 'No active water rate is configured for this billing period.',
                    'data' => [
                        'period' => $this->periodPayload($periodStart, $periodEnd, $periodIsCurrent),
                    ],
                ], 422);
            }

            $totalCubicMeter = round($totalLiters / 1000, 8);

            $latestReading = $meter->readings()
                ->whereNotNull('recorded_at')
                ->latest('recorded_at')
                ->latest('id')
                ->first();

            $recordType = $statement !== null ? 'finalized' : ($readingCount > 0 ? 'estimated' : 'unavailable');
            $calculation = $this->calculationPayload(
                $recordType,
                $statement,
                $settings,
                $totalLiters,
                $totalCubicMeter,
            );
            $currency = $calculation['currency'];
            $rateAmount = (float) $calculation['water_rate'];

            $history = BillingStatement::query()
                ->with(['payments' => fn ($query) => $query->latest('received_at')])
                ->where('user_id', $user->id)
                ->latest('billing_month')
                ->latest('id')
                ->limit(12)
                ->get()
                ->map(fn (BillingStatement $item): array => [
                    'id' => $item->id,
                    'statement_number' => $item->statement_number,
                    'billing_month' => optional($item->billing_month)->toDateString(),
                    'due_date' => optional($item->due_date)->toDateString(),
                    'total_liters' => (float) $item->total_liters,
                    'total_cubic_meter' => (float) $item->total_cubic_meter,
                    'water_rate' => (float) $item->water_rate,
                    'current_charge' => (float) $item->current_charge,
                    'amount_paid' => (float) $item->amount_paid,
                    'remaining_balance' => (float) $item->remaining_balance,
                    'currency' => $item->currency ?? 'PHP',
                    'status' => $item->status,
                    'payments' => $item->payments->map(fn ($payment): array => [
                        'id' => $payment->id,
                        'receipt_number' => $payment->receipt_number,
                        'amount' => (float) $payment->amount,
                        'status' => $payment->status,
                        'payment_method' => $payment->payment_method,
                        'reference_number' => $payment->reference_number,
                        'status_note' => $payment->status_note,
                        'received_at' => $this->timestamp($payment->received_at),
                    ])->values(),
                ])
                ->values();

            $data = [
                'period' => $this->periodPayload($periodStart, $periodEnd, $periodIsCurrent),
                'record_type' => $recordType,
                'has_rate_configuration' => $hasRateConfiguration || $statement !== null,
                'rate_effective_date' => $statement
                    ? optional($statement->generated_at)->toDateString()
                    : optional($settings->effective_date)->toDateString(),
                // These calculation keys are retained for existing frontend and
                // admin consumers.
                'calculation' => $calculation,
                'reading_count' => $readingCount,
                'consumption' => [
                    'liters' => $calculation['total_liters'],
                    'cubic_meters' => $calculation['total_cubic_meter'],
                ],
                'rate' => [
                    'amount' => $rateAmount,
                    'unit' => 'm3',
                    'formatted' => $this->formatRate($rateAmount, $currency),
                ],
                'estimate' => [
                    'amount' => $calculation['current_charge'],
                    'formatted' => $calculation['current_charge'] === null
                        ? 'Unavailable'
                        : $this->formatMoney($calculation['current_charge'], $currency),
                    'status' => $recordType,
                ],
                'balance' => [
                    'amount' => $calculation['remaining_balance'],
                    'formatted' => $calculation['remaining_balance'] === null
                        ? 'Not finalized'
                        : $this->formatMoney($calculation['remaining_balance'], $currency),
                    'finalized' => $statement !== null,
                ],
                'meter' => [
                    'id' => $meter->id,
                    'name' => $meter->name,
                    'asset_number' => $meter->asset_number,
                    'serial_number' => $meter->serial_number,
                    'status' => $meter->is_online ? 'online' : $meter->status,
                    'is_online' => $meter->is_online,
                    'last_seen_at' => $this->timestamp($meter->last_seen_at),
                    'last_telemetry_at' => $this->timestamp($meter->last_telemetry_at),
                ],
                'statement_id' => $statement?->id,
                'statement_number' => $statement?->statement_number,
                'due_date' => optional($statement?->due_date)->toDateString(),
                'statement_status' => $statement?->status,
                'latest_reading' => $this->latestReadingPayload($latestReading),
                'history' => $history,
            ];

            return response()->json(['success' => true, 'data' => $data]);
        } catch (Throwable $exception) {
            $reference = (string) Str::uuid();
            Log::error('Household billing estimate failed.', [
                'user_id' => $user->id,
                'meter_id' => $meter?->id,
                'period' => $periodStart->format('Y-m'),
                'exception' => $exception::class,
                'exception_message' => $exception->getMessage(),
                'error_reference' => $reference,
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Billing estimate is temporarily unavailable. Please try again.',
                'error_reference' => $reference,
            ], 503);
        }
    }

    /** @return array{start: string, end: string, from: string, to: string, label: string, is_current: bool} */
    private function periodPayload($periodStart, $periodEnd, bool $isCurrent = true): array
    {
        return [
            'start' => $periodStart->toDateString(),
            'end' => $periodEnd->toDateString(),
            'from' => $periodStart->toDateString(),
            'to' => $periodEnd->toDateString(),
            'label' => $periodStart->format('F Y'),
            'is_current' => $isCurrent,
        ];
    }

    private function calculationPayload(
        string $recordType,
        ?BillingStatement $statement,
        $settings,
        float $totalLiters,
        float $totalCubicMeter,
    ): array {
        if ($statement) {
            $fixedMonthlyCharge = round((float) ($statement->fixed_monthly_charge ?? 0), 2);
            $subsidyDeduction = round((float) ($statement->subsidy_deduction ?? 0), 2);
            $currentCharge = round((float) $statement->current_charge, 2);

            return [
                'total_liters' => round((float) $statement->total_liters, 6),
                'total_cubic_meter' => round((float) $statement->total_cubic_meter, 8),
                'water_rate' => round((float) $statement->water_rate, 2),
                'fixed_monthly_charge' => $fixedMonthlyCharge,
                'subsidy_deduction' => $subsidyDeduction,
                'usage_charge' => round(max($currentCharge - $fixedMonthlyCharge + $subsidyDeduction, 0), 2),
                'current_charge' => $currentCharge,
                'remaining_balance' => round((float) $statement->remaining_balance, 2),
                'currency' => $statement->currency ?? 'PHP',
                'effective_date' => optional($statement->generated_at)->toDateString(),
            ];
        }

        $waterRate = round((float) $settings->water_rate, 2);
        $fixedMonthlyCharge = round((float) ($settings->fixed_monthly_charge ?? 0), 2);
        $subsidyDeduction = round((float) ($settings->subsidy_deduction ?? 0), 2);
        $usageCharge = round($totalCubicMeter * $waterRate, 2);
        $currentCharge = $recordType === 'estimated'
            ? max(round($usageCharge + $fixedMonthlyCharge - $subsidyDeduction, 2), 0)
            : null;

        return [
            'total_liters' => $recordType === 'estimated' ? $totalLiters : 0,
            'total_cubic_meter' => $recordType === 'estimated' ? $totalCubicMeter : 0,
            'water_rate' => $waterRate,
            'fixed_monthly_charge' => $fixedMonthlyCharge,
            'subsidy_deduction' => $subsidyDeduction,
            'usage_charge' => $recordType === 'estimated' ? $usageCharge : null,
            'current_charge' => $currentCharge,
            'remaining_balance' => null,
            'currency' => $settings->currency ?? 'PHP',
            'effective_date' => optional($settings->effective_date)->toDateString(),
        ];
    }

    private function latestReadingPayload(?object $reading): ?array
    {
        if (! $reading) {
            return null;
        }

        $totalLiters = data_get($reading->device_payload, 'total_water_liters');

        return [
            'id' => $reading->id,
            'reading_id' => $reading->reading_id,
            'flow_rate' => $reading->flow_rate === null ? null : (float) $reading->flow_rate,
            'liters_used' => (float) $reading->liters_used,
            'cubic_meter' => (float) $reading->cubic_meter,
            'total_liters' => is_numeric($totalLiters) ? (float) $totalLiters : null,
            'recorded_at' => $this->timestamp($reading->recorded_at),
            'leak_detection_status' => $reading->leak_detection_status,
            'is_possible_leak' => (bool) $reading->is_possible_leak,
            'monitoring_status' => $reading->monitoring_status,
            'device_connection_status' => $reading->device_connection_status,
            'server_connection_status' => $reading->server_connection_status,
        ];
    }

    private function timestamp($value): ?string
    {
        return $value?->copy()->timezone(config('app.timezone'))->toIso8601String();
    }

    private function formatMoney(?float $amount, string $currency): string
    {
        return sprintf('%s %.2f', $currency, $amount ?? 0);
    }

    private function formatRate(float $amount, string $currency): string
    {
        return sprintf('%s %.2f / m³', $currency, $amount);
    }
}
