<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\BillingStatement;
use App\Models\LeakAlert;
use App\Models\Notification;
use App\Models\ServiceRequest;
use Illuminate\Http\Request;

class AdminActionCenterController extends Controller
{
    public function __invoke(Request $request)
    {
        $requests = ServiceRequest::query()
            ->with('household:id,name,account_number')
            ->whereNotIn('status', ['resolved', 'closed'])
            ->orderByRaw("CASE priority WHEN 'urgent' THEN 1 WHEN 'high' THEN 2 WHEN 'normal' THEN 3 ELSE 4 END")
            ->oldest('created_at')
            ->limit(8)
            ->get()
            ->map(fn (ServiceRequest $item): array => [
                'type' => 'service_request',
                'severity' => in_array($item->priority, ['urgent', 'high'], true) ? 'high' : 'normal',
                'title' => $item->subject,
                'description' => $item->reference_number.' · '.($item->household?->name ?? 'Household'),
                'status' => $item->status,
                'created_at' => $item->created_at,
                'target' => 'service-requests',
            ]);
        $overdue = BillingStatement::query()
            ->with('user:id,name,account_number')
            ->whereIn('status', ['unpaid', 'partial'])
            ->whereNotNull('due_date')
            ->whereDate('due_date', '<', today())
            ->oldest('due_date')
            ->limit(8)
            ->get()
            ->map(fn (BillingStatement $item): array => [
                'type' => 'overdue_bill',
                'severity' => 'high',
                'title' => 'Overdue '.($item->statement_number ?? 'billing statement'),
                'description' => ($item->user?->name ?? 'Household').' · '.$item->currency.' '.number_format((float) $item->remaining_balance, 2),
                'status' => $item->status,
                'created_at' => $item->due_date,
                'target' => 'billing-management',
            ]);
        $leaks = LeakAlert::query()
            ->with('user:id,name,account_number')
            ->whereIn('status', LeakAlert::ACTIVE_STATUSES)
            ->latest('last_detected_at')
            ->limit(8)
            ->get()
            ->map(fn (LeakAlert $item): array => [
                'type' => 'leak',
                'severity' => 'urgent',
                'title' => 'Active leak follow-up',
                'description' => ($item->user?->name ?? 'Household').' · '.str_replace('_', ' ', $item->status),
                'status' => $item->status,
                'created_at' => $item->last_detected_at ?? $item->created_at,
                'target' => 'leak-monitoring',
            ]);
        $failedNotifications = Notification::query()->where('delivery_status', 'failed')->whereNull('archived_at')->count();
        $scheduledNotifications = Notification::query()->where('delivery_status', 'scheduled')->whereNull('archived_at')->count();
        $items = $requests->concat($overdue)->concat($leaks)->sortByDesc(fn (array $item) => $item['severity'] === 'urgent' ? 3 : ($item['severity'] === 'high' ? 2 : 1))->take(15)->values();

        return response()->json(['data' => [
            'summary' => [
                'open_service_requests' => ServiceRequest::query()->whereNotIn('status', ['resolved', 'closed'])->count(),
                'overdue_statements' => BillingStatement::query()->whereIn('status', ['unpaid', 'partial'])->whereNotNull('due_date')->whereDate('due_date', '<', today())->count(),
                'active_leaks' => LeakAlert::query()->whereIn('status', LeakAlert::ACTIVE_STATUSES)->count(),
                'failed_notifications' => $failedNotifications,
                'scheduled_notifications' => $scheduledNotifications,
                'total_attention' => ServiceRequest::query()->whereNotIn('status', ['resolved', 'closed'])->count()
                    + BillingStatement::query()->whereIn('status', ['unpaid', 'partial'])->whereNotNull('due_date')->whereDate('due_date', '<', today())->count()
                    + LeakAlert::query()->whereIn('status', LeakAlert::ACTIVE_STATUSES)->count()
                    + $failedNotifications,
            ],
            'items' => $items,
        ]]);
    }
}
