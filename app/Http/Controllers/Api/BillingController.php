<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Billing;
use App\Services\BillingStatementService;
use App\Services\WaterConsumptionService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class BillingController extends Controller
{
    public function __construct(
        protected WaterConsumptionService $waterConsumptionService,
        protected BillingStatementService $billingStatementService
    ) {}

    public function index(Request $request)
    {
        $userId = $request->user()?->isAdmin() === true
            ? ($request->integer('user_id') ?: null)
            : $request->user()?->id;
        $settings = $this->waterConsumptionService->resolveSettingsForUser($userId);
        $scheduled = Billing::query()
            ->where('is_active', true)
            ->whereNotNull('effective_date')
            ->whereDate('effective_date', '>', now()->toDateString())
            ->where(function ($query) use ($userId): void {
                $query->whereNull('user_id');
                if ($userId !== null) {
                    $query->orWhere('user_id', $userId);
                }
            })
            ->orderByRaw('CASE WHEN user_id IS NULL THEN 1 ELSE 0 END')
            ->orderByRaw('DATE(effective_date) ASC')
            ->latest('id')
            ->first();

        return response()->json([
            'data' => $settings,
            'scheduled' => $scheduled,
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'user_id' => ['nullable', 'integer', 'exists:users,id'],
            'water_rate' => ['required', 'numeric', 'min:0'],
            'fixed_monthly_charge' => ['sometimes', 'numeric', 'min:0'],
            'subsidy_deduction' => ['required', 'numeric', 'min:0'],
            'currency' => ['sometimes', 'string', 'size:3', 'regex:/^[A-Z]{3}$/'],
            'effective_date' => ['sometimes', 'nullable', 'date'],
            'threshold_liters' => ['required', 'numeric', 'min:0'],
            'consumption_alert_enabled' => ['sometimes', 'boolean'],
            'consumption_change_threshold_percent' => ['sometimes', 'numeric', 'min:0.01', 'max:10000'],
            'consumption_alert_unit' => ['sometimes', Rule::in(['liters', 'cubic_meters', 'percentage'])],
            'consumption_alert_behavior' => ['sometimes', Rule::in(['household', 'household_admin', 'disabled'])],
            'leak_flow_rate_threshold' => ['required', 'numeric', 'min:0'],
            'leak_alert_enabled' => ['sometimes', 'boolean'],
            'leak_continuous_duration_minutes' => ['sometimes', 'integer', 'min:0', 'max:1440'],
            'leak_alert_behavior' => ['sometimes', Rule::in(['household', 'household_admin', 'disabled'])],
            'quiet_hours_start' => ['required', 'integer', 'min:0', 'max:23'],
            'quiet_hours_end' => ['required', 'integer', 'min:0', 'max:23'],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        $billing = Billing::create($validated);

        $this->billingStatementService->recordAudit(
            $request->user(),
            'billing_settings_created',
            'Created billing settings.',
            $validated,
            $billing,
        );

        return response()->json([
            'message' => 'Billing settings saved successfully',
            'data' => $billing,
        ], 201);
    }

    public function storeRates(Request $request)
    {
        $validated = $request->validate([
            'user_id' => ['nullable', 'integer', 'exists:users,id'],
            'water_rate' => ['required', 'numeric', 'min:0'],
            'fixed_monthly_charge' => ['required', 'numeric', 'min:0'],
            'subsidy_deduction' => ['required', 'numeric', 'min:0'],
            'effective_date' => ['nullable', 'date'],
            'currency' => ['required', 'string', 'size:3', 'regex:/^[A-Z]{3}$/'],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        return $this->saveSection($request, $validated, 'billing_rates', 'Billing rates saved successfully.');
    }

    public function storeConsumptionAlerts(Request $request)
    {
        $validated = $request->validate([
            'user_id' => ['nullable', 'integer', 'exists:users,id'],
            'notification_behavior' => ['required', Rule::in(['household', 'household_admin', 'disabled'])],
            'consumption_alert_enabled' => ['sometimes', 'boolean'],
        ]);

        // Consumption alerts are baseline reports, not an administrator-set usage cap.
        $validated['threshold_liters'] = 0;
        $validated['consumption_change_threshold_percent'] = 0;
        $validated['consumption_alert_unit'] = 'percentage';
        $validated['consumption_alert_behavior'] = $validated['notification_behavior'];
        unset($validated['notification_behavior']);

        return $this->saveSection($request, $validated, 'consumption_alerts', 'Consumption alert settings saved successfully.');
    }

    public function storeLeakSettings(Request $request)
    {
        $validated = $request->validate([
            'user_id' => ['nullable', 'integer', 'exists:users,id'],
            'leak_flow_rate_threshold' => ['required', 'numeric', 'min:0'],
            'leak_continuous_duration_minutes' => ['required', 'integer', 'min:0', 'max:1440'],
            'quiet_hours_start' => ['required', 'integer', 'min:0', 'max:23'],
            'quiet_hours_end' => ['required', 'integer', 'min:0', 'max:23'],
            'leak_alert_behavior' => ['required', Rule::in(['household', 'household_admin', 'disabled'])],
            'leak_alert_enabled' => ['sometimes', 'boolean'],
        ]);

        return $this->saveSection($request, $validated, 'leak_settings', 'Leak monitoring settings saved successfully.');
    }

    private function saveSection(Request $request, array $validated, string $section, string $message)
    {
        $userId = isset($validated['user_id']) ? (int) $validated['user_id'] : null;
        $billing = DB::transaction(function () use ($userId, $validated, $section): Billing {
            $current = $this->waterConsumptionService->resolveSettingsForUser($userId);

            if ($section !== 'billing_rates') {
                // Alerts take effect immediately. Carry the change into each
                // scheduled rate without activating that rate early or modifying
                // historical snapshots.
                $scheduled = Billing::query()
                    ->where('user_id', $userId)
                    ->where('is_active', true)
                    ->whereDate('effective_date', '>', now()->toDateString())
                    ->latest('id')
                    ->lockForUpdate()
                    ->get()
                    ->unique(fn (Billing $settings) => $settings->effective_date->toDateString());

                foreach ($scheduled as $settings) {
                    Billing::create(array_merge($this->settingsSnapshot($settings), $validated, [
                        'user_id' => $userId,
                        'is_active' => true,
                    ]));
                }
            }

            return Billing::create(array_merge($this->settingsSnapshot($current), $validated, [
                'user_id' => $userId,
                'effective_date' => $section === 'billing_rates'
                    ? ($validated['effective_date'] ?? now()->toDateString())
                    : now()->toDateString(),
                'is_active' => true,
            ]));
        });

        $this->billingStatementService->recordAudit(
            $request->user(),
            'billing_settings_updated',
            'Updated '.$section.'.',
            ['section' => $section, 'billing_id' => $billing->id, 'settings' => $validated],
            $billing,
        );

        return response()->json(['message' => $message, 'data' => $billing], 201);
    }

    private function settingsSnapshot(Billing $settings): array
    {
        return [
            'water_rate' => $settings->water_rate ?? 0,
            'fixed_monthly_charge' => $settings->fixed_monthly_charge ?? 0,
            'subsidy_deduction' => $settings->subsidy_deduction ?? 0,
            'currency' => $settings->currency ?? 'PHP',
            'effective_date' => $settings->effective_date,
            'threshold_liters' => $settings->threshold_liters ?? 0,
            'consumption_alert_enabled' => $settings->consumption_alert_enabled ?? true,
            'consumption_change_threshold_percent' => $settings->consumption_change_threshold_percent ?? 100,
            'consumption_alert_unit' => $settings->consumption_alert_unit ?? 'percentage',
            'consumption_alert_behavior' => $settings->consumption_alert_behavior ?? 'household',
            'leak_flow_rate_threshold' => $settings->leak_flow_rate_threshold ?? 0,
            'leak_alert_enabled' => $settings->leak_alert_enabled ?? true,
            'leak_continuous_duration_minutes' => $settings->leak_continuous_duration_minutes ?? 0,
            'leak_alert_behavior' => $settings->leak_alert_behavior ?? 'household',
            'quiet_hours_start' => $settings->quiet_hours_start ?? 22,
            'quiet_hours_end' => $settings->quiet_hours_end ?? 4,
        ];
    }
}
