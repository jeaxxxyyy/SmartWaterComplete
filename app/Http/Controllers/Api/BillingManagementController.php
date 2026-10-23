<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\BillingStatement;
use App\Models\User;
use App\Models\WaterReading;
use App\Services\BillingStatementService;
use App\Services\WaterConsumptionService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

class BillingManagementController extends Controller
{
    public function __construct(
        protected WaterConsumptionService $waterConsumptionService,
        protected BillingStatementService $billingStatementService,
    ) {}

    public function index(Request $request)
    {
        $validated = $request->validate([
            'search' => ['nullable', 'string', 'max:255'],
            'bill_type' => ['nullable', 'in:all,estimated,finalized'],
            'status' => ['nullable', 'in:all,estimated,unpaid,partial,paid,overdue,cancelled'],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date', 'after_or_equal:from'],
            'payment_method' => ['nullable', 'in:cash'],
            'payment_reference' => ['nullable', 'string', 'max:100'],
            'payment_date' => ['nullable', 'date'],
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:50'],
        ]);

        [$from, $to] = $this->period($validated);
        $billType = $validated['bill_type'] ?? 'all';
        $status = $validated['status'] ?? 'all';

        $households = User::query()
            ->where('role', 'user')
            ->when(! empty($validated['search']), function ($query) use ($validated): void {
                $search = '%'.$validated['search'].'%';
                $query->where(function ($builder) use ($search): void {
                    $builder->where('name', 'like', $search)
                        ->orWhere('email', 'like', $search)
                        ->orWhere('account_number', 'like', $search)
                        ->orWhere('address', 'like', $search)
                        ->orWhereHas('devices', function ($deviceQuery) use ($search): void {
                            $deviceQuery->where('name', 'like', $search)
                                ->orWhere('asset_number', 'like', $search)
                                ->orWhere('serial_number', 'like', $search);
                        });
                });
            })
            ->get(['id', 'name', 'email', 'account_number', 'address']);
        $householdIds = $households->modelKeys();
        $householdsById = $households->keyBy('id');

        $records = collect();
        if ($billType !== 'estimated' && $status !== 'estimated' && $householdIds !== []) {
            $statements = BillingStatement::query()
                ->with(['user:id,name,email,account_number,address', 'payments'])
                ->whereIn('user_id', $householdIds)
                ->whereBetween('billing_month', [$from->toDateString(), $to->toDateString()])
                ->when(! empty($validated['payment_method']), fn ($query) => $query->whereHas('payments', fn ($payments) => $payments->where('payment_method', $validated['payment_method'])))
                ->when(! empty($validated['payment_reference']), fn ($query) => $query->whereHas('payments', fn ($payments) => $payments->where('reference_number', 'like', '%'.$validated['payment_reference'].'%')))
                ->when(! empty($validated['payment_date']), fn ($query) => $query->whereHas('payments', fn ($payments) => $payments->whereDate('received_at', $validated['payment_date'])))
                ->get();

            $records = $records->concat($statements->map(fn (BillingStatement $statement): array => $this->finalizedRecord($statement)));
        }

        if ($billType !== 'finalized' && $status !== 'unpaid' && $status !== 'partial' && $status !== 'paid' && $householdIds !== []) {
            $records = $records->concat($this->estimatedRecords($householdsById, $householdIds, $from, $to));
        }

        $records = $records
            ->when($status !== 'all' && $status !== 'estimated', fn (Collection $items) => $items->where('status', $status))
            ->sortByDesc(fn (array $record): string => ($record['period']['sort'] ?? '').'|'.($record['household']['name'] ?? ''))
            ->values();

        $summary = [
            'total_records' => $records->count(),
            'finalized_count' => $records->where('record_type', 'finalized')->count(),
            'estimated_count' => $records->where('record_type', 'estimated')->count(),
            'finalized_charges' => (float) $records->where('record_type', 'finalized')->sum('current_charge'),
            'estimated_charges' => (float) $records->where('record_type', 'estimated')->sum('current_charge'),
            'outstanding_balance' => (float) $records->sum('remaining_balance'),
            'households_with_billing' => $records->pluck('household.id')->filter()->unique()->count(),
            'period' => ['from' => $from->toDateString(), 'to' => $to->toDateString()],
        ];

        $page = (int) ($validated['page'] ?? 1);
        $perPage = (int) ($validated['per_page'] ?? 10);
        $paginator = new LengthAwarePaginator(
            $records->forPage($page, $perPage)->values(),
            $records->count(),
            $perPage,
            $page,
            ['path' => $request->url(), 'query' => $request->query()],
        );

        return response()->json(['data' => $paginator, 'meta' => $summary]);
    }

    public function show(BillingStatement $billingStatement)
    {
        $billingStatement->load(['user:id,name,email,account_number,address', 'payments.recorder:id,name']);
        $from = Carbon::parse($billingStatement->billing_month)->startOfMonth();
        $to = $from->copy()->endOfMonth();
        $readings = WaterReading::query()
            ->where('user_id', $billingStatement->user_id)
            ->whereBetween('recorded_at', [$from, $to])
            ->latest('recorded_at')
            ->get();
        $usageCharge = (float) $billingStatement->total_cubic_meter * (float) $billingStatement->water_rate;

        return response()->json([
            'data' => [
                'record_type' => 'finalized',
                'statement' => $billingStatement,
                'household' => $billingStatement->user,
                'readings' => $readings,
                'calculation' => [
                    'usage_charge' => $usageCharge,
                    'fixed_monthly_charge' => (float) ($billingStatement->fixed_monthly_charge ?? 0),
                    'subsidy_deduction' => (float) $billingStatement->subsidy_deduction,
                    'current_charge' => (float) $billingStatement->current_charge,
                    'amount_paid' => (float) $billingStatement->amount_paid,
                    'remaining_balance' => (float) $billingStatement->remaining_balance,
                    'currency' => $billingStatement->currency ?? 'PHP',
                ],
                'payments' => $billingStatement->payments,
            ],
        ]);
    }

    private function finalizedRecord(BillingStatement $statement): array
    {
        $user = $statement->user;

        return [
            'id' => $statement->id,
            'statement_number' => $statement->statement_number,
            'due_date' => optional($statement->due_date)->toDateString(),
            'record_type' => 'finalized',
            'status' => $statement->status,
            'household' => $user,
            'period' => [
                'label' => optional($statement->billing_month)->format('F Y'),
                'sort' => optional($statement->billing_month)->toDateString(),
            ],
            'total_liters' => (float) $statement->total_liters,
            'total_cubic_meter' => (float) $statement->total_cubic_meter,
            'water_rate' => (float) $statement->water_rate,
            'fixed_monthly_charge' => (float) ($statement->fixed_monthly_charge ?? 0),
            'subsidy_deduction' => (float) $statement->subsidy_deduction,
            'current_charge' => (float) $statement->current_charge,
            'amount_paid' => (float) $statement->amount_paid,
            'remaining_balance' => (float) $statement->remaining_balance,
            'currency' => $statement->currency ?? 'PHP',
            'generated_at' => $statement->generated_at,
            'payments' => $statement->payments,
        ];
    }

    private function estimatedRecords(Collection $households, array $householdIds, Carbon $from, Carbon $to): Collection
    {
        $aggregates = WaterReading::query()
            ->whereIn('user_id', $householdIds)
            ->whereBetween('recorded_at', [$from, $to])
            ->selectRaw('user_id, sum(liters_used) as total_liters, sum(cubic_meter) as total_cubic_meter, max(recorded_at) as latest_recorded_at, count(*) as reading_count')
            ->groupBy('user_id')
            ->get();

        return $aggregates->map(function ($aggregate) use ($households, $from, $to): array {
            $user = $households->get($aggregate->user_id);
            $settings = $this->waterConsumptionService->resolveSettingsForUser((int) $aggregate->user_id, $to->isPast() ? $to : now());
            $usageCharge = (float) $aggregate->total_cubic_meter * (float) $settings->water_rate;
            $fixedCharge = (float) ($settings->fixed_monthly_charge ?? 0);
            $subsidy = (float) ($settings->subsidy_deduction ?? 0);
            $charge = max($usageCharge + $fixedCharge - $subsidy, 0);

            return [
                'id' => 'estimated-'.$aggregate->user_id.'-'.$from->format('Ymd').$to->format('Ymd'),
                'record_type' => 'estimated',
                'status' => 'estimated',
                'household' => $user,
                'period' => ['label' => $from->format('M j').' – '.$to->format('M j, Y'), 'sort' => $to->toDateString()],
                'total_liters' => (float) $aggregate->total_liters,
                'total_cubic_meter' => (float) $aggregate->total_cubic_meter,
                'reading_count' => (int) $aggregate->reading_count,
                'water_rate' => (float) $settings->water_rate,
                'fixed_monthly_charge' => $fixedCharge,
                'subsidy_deduction' => $subsidy,
                'current_charge' => $charge,
                'amount_paid' => 0,
                'remaining_balance' => $charge,
                'currency' => $settings->currency ?? 'PHP',
                'generated_at' => $aggregate->latest_recorded_at,
                'period_from' => $from->toDateString(),
                'period_to' => $to->toDateString(),
            ];
        });
    }

    private function period(array $validated): array
    {
        $timezone = config('app.timezone');
        $from = isset($validated['from'])
            ? Carbon::parse($validated['from'], $timezone)->startOfDay()
            : now($timezone)->startOfMonth();
        $to = isset($validated['to'])
            ? Carbon::parse($validated['to'], $timezone)->endOfDay()
            : now($timezone)->endOfMonth();

        return [$from, $to];
    }
}
