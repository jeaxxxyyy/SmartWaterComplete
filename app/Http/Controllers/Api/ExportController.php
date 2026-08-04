<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\BillingStatement;
use App\Models\Notification;
use App\Models\User;
use App\Models\WaterReading;
use App\Services\BillingStatementService;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Http\Request;

class ExportController extends Controller
{
    public function __construct(protected BillingStatementService $billingStatementService) {}

    public function readings(Request $request)
    {
        $validated = $request->validate([
            'user_id' => ['nullable', 'integer', 'exists:users,id'],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date', 'after_or_equal:from'],
        ]);

        if (isset($validated['user_id']) && ! User::query()->whereKey($validated['user_id'])->where('role', 'user')->exists()) {
            abort(404);
        }

        $items = WaterReading::query()
            ->join('users', 'users.id', '=', 'water_readings.user_id')
            ->where('users.role', 'user')
            ->when(isset($validated['user_id']), fn ($query) => $query->where('water_readings.user_id', $validated['user_id']))
            ->when(isset($validated['from']), fn ($query) => $query->where('water_readings.recorded_at', '>=', Carbon::parse($validated['from'])->startOfDay()))
            ->when(isset($validated['to']), fn ($query) => $query->where('water_readings.recorded_at', '<=', Carbon::parse($validated['to'])->endOfDay()))
            ->selectRaw('users.account_number, users.name, date(water_readings.recorded_at) as period, sum(water_readings.liters_used) as total_liters, sum(water_readings.cubic_meter) as total_cubic_meter, sum(water_readings.estimated_bill) as estimated_bill, sum(case when water_readings.is_possible_leak = 1 then 1 else 0 end) as leak_count')
            ->groupBy('users.id', 'users.account_number', 'users.name', 'period')
            ->orderByDesc('period')
            ->get();

        return $this->pdf($request, 'Water Consumption Readings', 'water-readings.pdf',
            ['Account', 'Day', 'Total liters', 'Total volume', 'Estimated bill', 'Leak alerts'],
            $items->map(fn ($item) => [
                $item->account_number ?? $item->name ?? '—',
                optional($item->period ? Carbon::parse($item->period) : null)->format('F j, Y') ?? '—',
                number_format((float) $item->total_liters, 3).' L',
                number_format((float) $item->total_cubic_meter, 4).' m³',
                'PHP '.number_format((float) $item->estimated_bill, 2),
                (string) $item->leak_count,
            ])->all(), 'export_readings');
    }

    public function billingStatements(Request $request)
    {
        $items = BillingStatement::query()->with('user:id,name,account_number')
            ->whereHas('user', fn ($query) => $query->where('role', 'user'))
            ->when($request->filled('user_id'), fn ($query) => $query->where('user_id', $request->integer('user_id')))
            ->latest('billing_month')->get();

        return $this->pdf($request, 'Billing Statements', 'billing-statements.pdf',
            ['Account', 'Month', 'Consumption', 'Rate / m³', 'Fixed charge', 'Charge', 'Paid', 'Balance', 'Status'],
            $items->map(fn ($item) => [
                $item->user?->account_number ?? $item->user?->name ?? '—',
                optional($item->billing_month)->format('F Y') ?? '—',
                number_format((float) $item->total_cubic_meter, 4).' m³',
                ($item->currency ?? 'PHP').' '.number_format((float) $item->water_rate, 2),
                ($item->currency ?? 'PHP').' '.number_format((float) ($item->fixed_monthly_charge ?? 0), 2),
                ($item->currency ?? 'PHP').' '.number_format((float) $item->current_charge, 2),
                ($item->currency ?? 'PHP').' '.number_format((float) $item->amount_paid, 2),
                ($item->currency ?? 'PHP').' '.number_format((float) $item->remaining_balance, 2),
                ucfirst($item->status),
            ])->all(), 'export_billing_statements');
    }

    public function notifications(Request $request)
    {
        $items = Notification::query()->with('user:id,name,account_number')
            ->when($request->filled('user_id'), fn ($query) => $query->where('user_id', $request->integer('user_id')))
            ->latest()->get();

        return $this->pdf($request, 'Notification Records', 'notifications.pdf',
            ['Account', 'Type', 'Title', 'Message', 'Status', 'Sent'],
            $items->map(fn ($item) => [
                $item->user?->account_number ?? $item->user?->name ?? '—',
                ucwords(str_replace('_', ' ', $item->type)),
                $item->title,
                $item->message,
                $item->is_read ? 'Read' : 'Unread',
                optional($item->sent_at ?? $item->created_at)->format('M d, Y H:i') ?? '—',
            ])->all(), 'export_notifications');
    }

    private function pdf(Request $request, string $title, string $filename, array $headers, array $rows, string $auditAction)
    {
        $this->billingStatementService->recordAudit(
            $request->user(), $auditAction, "Exported {$title} to PDF.",
            ['user_id' => $request->integer('user_id') ?: null, 'from' => $request->input('from'), 'to' => $request->input('to')],
        );

        return Pdf::loadView('reports.pdf', [
            'title' => $title,
            'headers' => $headers,
            'rows' => $rows,
            'generatedAt' => now(),
        ])->setPaper('a4', 'landscape')->stream($filename);
    }
}
