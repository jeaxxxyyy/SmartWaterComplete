<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\BillingStatement;
use App\Models\User;
use App\Services\BillingStatementService;
use Carbon\Carbon;
use Illuminate\Http\Request;

class BillingStatementController extends Controller
{
    public function __construct(
        protected BillingStatementService $billingStatementService
    ) {
    }

    public function index(Request $request)
    {
        $query = BillingStatement::query()->latest('billing_month');

        if ($request->user()?->isAdmin() !== true) {
            $query->where('user_id', $request->user()?->id);
        } elseif ($request->filled('user_id')) {
            $query->where('user_id', $request->integer('user_id'));
        }

        return response()->json([
            'data' => $query->get(),
        ]);
    }

    public function show(Request $request, BillingStatement $billingStatement)
    {
        if ($request->user()?->isAdmin() !== true && $billingStatement->user_id !== $request->user()?->id) {
            return response()->json([
                'message' => 'You are not allowed to view this billing statement.',
            ], 403);
        }

        return response()->json([
            'data' => $billingStatement,
        ]);
    }

    public function generate(Request $request)
    {
        $validated = $request->validate([
            'user_id' => ['required', 'integer', 'exists:users,id'],
            'month' => ['nullable', 'date_format:Y-m'],
        ]);

        $user = User::query()->where('role', 'user')->findOrFail($validated['user_id']);
        $month = isset($validated['month']) ? Carbon::createFromFormat('Y-m', $validated['month']) : now();
        $statement = $this->billingStatementService->generateForUser($user, $month);

        $this->billingStatementService->recordAudit(
            $request->user(),
            'billing_statement_generated',
            'Generated billing statement for user.',
            ['user_id' => $user->id, 'month' => $month->format('Y-m')],
            $statement,
        );

        return response()->json([
            'message' => 'Billing statement generated successfully.',
            'data' => $statement,
        ], 201);
    }

    public function markAsPaid(Request $request, BillingStatement $billingStatement)
    {
        if ($request->user()?->isAdmin() !== true) {
            return response()->json([
                'message' => 'Admin access is required.',
            ], 403);
        }

        $validated = $request->validate([
            'amount_paid' => ['required', 'numeric', 'min:0'],
        ]);

        $remainingBalance = max((float) $billingStatement->opening_balance + (float) $billingStatement->current_charge - (float) $validated['amount_paid'], 0);

        $billingStatement->update([
            'amount_paid' => $validated['amount_paid'],
            'remaining_balance' => $remainingBalance,
            'status' => $remainingBalance > 0 ? 'partial' : 'paid',
            'paid_at' => $remainingBalance <= 0 ? now() : null,
        ]);

        $this->billingStatementService->recordAudit(
            $request->user(),
            'billing_statement_paid',
            'Marked billing statement as paid.',
            ['billing_statement_id' => $billingStatement->id, 'amount_paid' => $validated['amount_paid']],
            $billingStatement,
        );

        return response()->json([
            'message' => 'Billing statement updated successfully.',
            'data' => $billingStatement->fresh(),
        ]);
    }
}
