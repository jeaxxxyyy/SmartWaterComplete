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

    /** Cash is collected face-to-face by authorized municipal staff. */
    public function store(Request $request, BillingStatement $billingStatement)
    {
        $data = $request->validate(['amount' => ['required', 'numeric', 'min:0.01'], 'payment_method' => ['required', Rule::in(['cash'])], 'notes' => ['nullable', 'string', 'max:1000'], 'received_at' => ['nullable', 'date', 'before_or_equal:now']]);
        $payment = DB::transaction(function () use ($billingStatement, $request, $data) {
            $statement = BillingStatement::query()->lockForUpdate()->findOrFail($billingStatement->id);
            if (in_array($statement->status, ['cancelled', 'void'], true)) {
                throw ValidationException::withMessages(['payment' => 'Payments cannot be made against a cancelled statement.']);
            }
            if ((float) $data['amount'] > (float) $statement->remaining_balance) {
                throw ValidationException::withMessages(['amount' => 'The payment cannot exceed the remaining balance.']);
            }
            $payment = $statement->payments()->create(['recorded_by' => $request->user()->id, 'verified_by' => $request->user()->id, 'verified_at' => now(), 'receipt_number' => 'OR-'.now()->format('Ymd').'-'.Str::upper(Str::random(8)), 'amount' => round((float) $data['amount'], 2), 'payment_method' => 'cash', 'status' => 'confirmed', 'notes' => $data['notes'] ?? null, 'received_at' => isset($data['received_at']) ? Carbon::parse($data['received_at']) : now()]);
            $paid = round((float) $statement->payments()->where('status', 'confirmed')->sum('amount'), 2);
            $remaining = max(round((float) $statement->opening_balance + (float) $statement->current_charge - $paid, 2), 0);
            $statement->update(['amount_paid' => $paid, 'remaining_balance' => $remaining, 'status' => $remaining <= 0 ? 'paid' : 'partial', 'paid_at' => $remaining <= 0 ? now() : null]);

            return $payment;
        });
        $this->notifyAndAudit($request, $payment, 'billing_payment_recorded', 'Cash payment recorded and receipt generated.');

        return response()->json(['message' => 'Cash payment recorded and receipt generated.', 'data' => $this->payload($payment)], 201);
    }

    public function void(Request $request, BillingPayment $billingPayment)
    {
        $note = $request->validate(['note' => ['required', 'string', 'max:1000']])['note'];
        $payment = DB::transaction(function () use ($billingPayment, $request, $note) {
            $payment = BillingPayment::query()->lockForUpdate()->findOrFail($billingPayment->id);
            if ($payment->status !== 'confirmed') {
                throw ValidationException::withMessages(['payment' => 'Only confirmed payments can be voided.']);
            }
            $statement = BillingStatement::query()->lockForUpdate()->findOrFail($payment->billing_statement_id);
            $payment->update(['status' => 'voided', 'voided_by' => $request->user()->id, 'voided_at' => now(), 'status_note' => $note]);
            $paid = round((float) $statement->payments()->where('status', 'confirmed')->sum('amount'), 2);
            $remaining = max(round((float) $statement->opening_balance + (float) $statement->current_charge - $paid, 2), 0);
            $statement->update(['amount_paid' => $paid, 'remaining_balance' => $remaining, 'status' => $remaining <= 0 ? 'paid' : ($paid > 0 ? 'partial' : ($statement->due_date?->isBefore(today()) ? 'overdue' : 'unpaid')), 'paid_at' => $remaining <= 0 ? now() : null]);

            return $payment->fresh();
        });
        $this->notifyAndAudit($request, $payment, 'billing_payment_voided', 'Cash payment voided and balance recalculated.');

        return response()->json(['message' => 'Cash payment voided and balance recalculated.', 'data' => $this->payload($payment)]);
    }

    private function notifyAndAudit(Request $request, BillingPayment $payment, string $action, string $message): void
    {
        $payment->loadMissing('statement.user.notificationPreference');
        $statement = $payment->statement;
        if ($statement->user?->notificationPreference?->billing_updates ?? true) {
            Notification::create(['user_id' => $statement->user_id, 'type' => 'billing', 'title' => $message, 'message' => $message.' Receipt: '.$payment->receipt_number.'.', 'metadata' => ['billing_statement_id' => $statement->id, 'billing_payment_id' => $payment->id], 'sent_at' => now(), 'delivery_status' => 'delivered']);
        }
        $this->audit->recordAudit($request->user(), $action, $message, ['billing_statement_id' => $statement->id, 'billing_payment_id' => $payment->id, 'amount' => $payment->amount, 'status' => $payment->status], $payment);
    }

    private function payload(BillingPayment $payment): BillingPayment
    {
        return $payment->load(['recorder:id,name', 'verifier:id,name', 'voider:id,name']);
    }
}
