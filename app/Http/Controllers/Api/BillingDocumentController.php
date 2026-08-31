<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\BillingPayment;
use App\Models\BillingStatement;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;

class BillingDocumentController extends Controller
{
    public function statement(Request $request, BillingStatement $billingStatement)
    {
        $this->authorizeRecord($request, $billingStatement->user_id);
        $billingStatement->load(['user:id,name,email,account_number,address', 'payments.recorder:id,name']);

        return Pdf::loadView('reports.billing-statement', ['statement' => $billingStatement])
            ->download(($billingStatement->statement_number ?? 'billing-statement').'.pdf');
    }

    public function receipt(Request $request, BillingPayment $billingPayment)
    {
        $billingPayment->load(['statement.user:id,name,email,account_number,address', 'recorder:id,name']);
        $this->authorizeRecord($request, $billingPayment->statement->user_id);

        return Pdf::loadView('reports.billing-receipt', ['payment' => $billingPayment])
            ->download($billingPayment->receipt_number.'.pdf');
    }

    private function authorizeRecord(Request $request, int $householdId): void
    {
        abort_unless($request->user()?->isAdmin() || $request->user()?->id === $householdId, 403, 'You are not allowed to download this billing document.');
    }
}
