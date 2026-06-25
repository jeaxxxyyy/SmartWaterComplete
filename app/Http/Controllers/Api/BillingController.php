<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Billing;
use App\Services\BillingStatementService;
use App\Services\WaterConsumptionService;
use Illuminate\Http\Request;

class BillingController extends Controller
{
    public function __construct(
        protected WaterConsumptionService $waterConsumptionService,
        protected BillingStatementService $billingStatementService
    ) {
    }

    public function index(Request $request)
    {
        $userId = $request->integer('user_id') ?: null;
        $settings = $this->waterConsumptionService->resolveSettingsForUser($userId);

        return response()->json([
            'data' => $settings,
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'user_id' => ['nullable', 'integer', 'exists:users,id'],
            'water_rate' => ['required', 'numeric', 'min:0'],
            'subsidy_deduction' => ['required', 'numeric', 'min:0'],
            'threshold_liters' => ['required', 'numeric', 'min:0'],
            'leak_flow_rate_threshold' => ['required', 'numeric', 'min:0'],
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
}
