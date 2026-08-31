<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\BillingPayment;
use App\Models\BillingStatement;
use App\Models\Notification;
use App\Services\BillingStatementService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class BillingPaymentController extends Controller
{
    public function __construct(protected BillingStatementService $audit) {}

    public function store(Request $request, BillingStatement $billingStatement)
    {
        $validated = $request->validate([
            'amount' => ['required', 'numeric', 'min:0.01'],
            'payment_method' => ['required', Rule::in(['cash', 'bank_deposit', 'check', 'other'])],
            'reference_number' => ['nullable', 'string', 'max:100'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'received_at' => ['nullable', 'date', 'before_or_equal:now'],
        ]);

        $payment = DB::transaction(function () use ($billingStatement, $request, $validated): BillingPayment {
            $statement = BillingStatement::query()->lockForUpdate()->findOrFail($billingStatement->id);
            $amount = round((float) $validated['amount'], 2);
            if ($amount > (float) $statement->remaining_balance) {
                throw ValidationException::withMessages([
                    'amount' => 'The payment cannot exceed the remaining balance.',
                ]);
            }

            $payment = $statement->payments()->create([
                'recorded_by' => $request->user()->id,
                'receipt_number' => 'OR-'.now()->format('Ymd').'-'.Str::upper(Str::random(8)),
                'amount' => $amount,
                'payment_method' => $validated['payment_method'],
                'reference_number' => $validated['reference_number'] ?? null,
                'notes' => $validated['notes'] ?? null,
                'received_at' => isset($validated['received_at']) ? Carbon::parse($validated['received_at']) : now(),
            ]);
            $paid = round((float) $statement->payments()->sum('amount'), 2);
            $remaining = max(round((float) $statement->opening_balance + (float) $statement->current_charge - $paid, 2), 0);
            $statement->update([
                'amount_paid' => $paid,
                'remaining_balance' => $remaining,
                'status' => $remaining <= 0 ? 'paid' : 'partial',
                'paid_at' => $remaining <= 0 ? now() : null,
            ]);

            if ($statement->user()->first()?->notificationPreference?->billing_updates ?? true) {
                Notification::create([
                    'user_id' => $statement->user_id,
                    'type' => 'billing',
                    'title' => 'Payment recorded',
                    'message' => sprintf('Payment of %s %.2f was recorded. Receipt: %s.', $statement->currency ?? 'PHP', $amount, $payment->receipt_number),
                    'metadata' => ['billing_statement_id' => $statement->id, 'billing_payment_id' => $payment->id],
                    'sent_at' => now(),
                    'delivery_status' => 'delivered',
                ]);
            }

            return $payment;
        });

        $this->audit->recordAudit($request->user(), 'billing_payment_recorded', 'Recorded an offline billing payment.', [
            'billing_statement_id' => $billingStatement->id,
            'billing_payment_id' => $payment->id,
            'amount' => $payment->amount,
            'payment_method' => $payment->payment_method,
        ], $payment);

        return response()->json([
            'message' => 'Payment recorded and receipt generated.',
            'data' => $payment->load('recorder:id,name'),
        ], 201);
    }
}
