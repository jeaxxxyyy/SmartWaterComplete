<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\BillingStatement;
use App\Services\BillingStatementService;
use App\Models\Notification;
use App\Models\WaterReading;
use Illuminate\Http\Request;

class ExportController extends Controller
{
    public function __construct(
        protected BillingStatementService $billingStatementService
    ) {
    }

    public function readings(Request $request)
    {
        $query = WaterReading::query()->latest('recorded_at');

        if ($request->filled('user_id')) {
            $query->where('user_id', $request->integer('user_id'));
        }

        $this->billingStatementService->recordAudit(
            $request->user(),
            'export_readings',
            'Exported water readings to CSV.',
            ['user_id' => $request->integer('user_id')],
        );

        return response()->streamDownload(function () use ($query): void {
            $output = fopen('php://output', 'w');
            fputcsv($output, [
                'id',
                'user_id',
                'flow_rate',
                'liters_used',
                'cubic_meter',
                'estimated_bill',
                'recorded_at',
            ]);

            foreach ($query->cursor() as $reading) {
                fputcsv($output, [
                    $reading->id,
                    $reading->user_id,
                    $reading->flow_rate,
                    $reading->liters_used,
                    $reading->cubic_meter,
                    $reading->estimated_bill,
                    optional($reading->recorded_at)->toDateTimeString(),
                ]);
            }

            fclose($output);
        }, 'water-readings.csv', [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    public function billingStatements(Request $request)
    {
        $query = BillingStatement::query()->latest('billing_month');

        if ($request->filled('user_id')) {
            $query->where('user_id', $request->integer('user_id'));
        }

        $this->billingStatementService->recordAudit(
            $request->user(),
            'export_billing_statements',
            'Exported billing statements to CSV.',
            ['user_id' => $request->integer('user_id')],
        );

        return response()->streamDownload(function () use ($query): void {
            $output = fopen('php://output', 'w');
            fputcsv($output, [
                'id',
                'user_id',
                'billing_month',
                'opening_balance',
                'current_charge',
                'amount_paid',
                'remaining_balance',
                'status',
            ]);

            foreach ($query->cursor() as $statement) {
                fputcsv($output, [
                    $statement->id,
                    $statement->user_id,
                    optional($statement->billing_month)->toDateString(),
                    $statement->opening_balance,
                    $statement->current_charge,
                    $statement->amount_paid,
                    $statement->remaining_balance,
                    $statement->status,
                ]);
            }

            fclose($output);
        }, 'billing-statements.csv', [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    public function notifications(Request $request)
    {
        $query = Notification::query()->latest();

        if ($request->filled('user_id')) {
            $query->where('user_id', $request->integer('user_id'));
        }

        $this->billingStatementService->recordAudit(
            $request->user(),
            'export_notifications',
            'Exported notifications to CSV.',
            ['user_id' => $request->integer('user_id')],
        );

        return response()->streamDownload(function () use ($query): void {
            $output = fopen('php://output', 'w');
            fputcsv($output, [
                'id',
                'user_id',
                'type',
                'title',
                'is_read',
                'sent_at',
            ]);

            foreach ($query->cursor() as $notification) {
                fputcsv($output, [
                    $notification->id,
                    $notification->user_id,
                    $notification->type,
                    $notification->title,
                    $notification->is_read ? 'yes' : 'no',
                    optional($notification->sent_at)->toDateTimeString(),
                ]);
            }

            fclose($output);
        }, 'notifications.csv', [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }
}
