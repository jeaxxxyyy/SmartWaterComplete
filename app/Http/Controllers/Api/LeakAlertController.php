<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\LeakAlert;
use App\Models\Notification;
use App\Models\User;
use App\Models\WaterReading;
use App\Services\BillingStatementService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class LeakAlertController extends Controller
{
    public function __construct(protected BillingStatementService $audit) {}

    public function index(Request $request)
    {
        $validated = $request->validate([
            'status' => ['nullable', Rule::in(LeakAlert::STATUSES)],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $this->syncLegacyAlerts();

        $alerts = LeakAlert::query()
            ->with([
                'user:id,name,email,account_number,address,is_active,account_status',
                'firstReading:id,user_id,device_id,flow_rate,liters_used,recorded_at,leak_detection_status',
                'latestReading:id,user_id,device_id,flow_rate,liters_used,recorded_at,leak_detection_status,monitoring_status,device_connection_status,server_connection_status',
                'latestReading.device:id,name,asset_number,serial_number,status,hardware_id,last_seen_at',
                'notification:id,user_id,water_reading_id,type,title,message,sent_at',
            ])
            ->whereHas('user', fn ($query) => $query->where('role', 'user'))
            ->when(isset($validated['status']), fn ($query) => $query->where('status', $validated['status']))
            ->latest('detected_at')
            ->paginate($validated['per_page'] ?? 100)
            ->withQueryString();

        return response()->json([
            'data' => $alerts->through(fn (LeakAlert $alert) => $this->serializeAlert($alert)),
        ]);
    }

    public function update(Request $request, LeakAlert $leakAlert)
    {
        if (! $leakAlert->user?->isHousehold()) {
            return response()->json(['message' => 'The requested leak alert does not belong to a household account.'], 404);
        }

        $validated = $request->validate([
            'status' => ['required', Rule::in(LeakAlert::STATUSES)],
            'notes' => ['sometimes', 'nullable', 'string', 'max:2000'],
        ]);

        $before = $leakAlert->only(['status', 'notes', 'acknowledged_at', 'resolved_at']);
        $nextStatus = $validated['status'];
        $changes = ['status' => $nextStatus];

        if (array_key_exists('notes', $validated)) {
            $changes['notes'] = $validated['notes'];
        }

        if (in_array($nextStatus, [LeakAlert::STATUS_ACKNOWLEDGED, LeakAlert::STATUS_INVESTIGATING], true)
            && $leakAlert->acknowledged_at === null) {
            $changes['acknowledged_at'] = now();
        }

        if (in_array($nextStatus, [LeakAlert::STATUS_RESOLVED, LeakAlert::STATUS_FALSE_ALARM], true)) {
            $changes['resolved_at'] = $leakAlert->resolved_at ?? now();
        } else {
            $changes['resolved_at'] = null;
        }

        $leakAlert->update($changes);
        $leakAlert->refresh();

        $this->audit->recordAudit($request->user(), 'leak_alert_updated', 'Updated a household leak alert.', [
            'before' => $before,
            'after' => $leakAlert->only(['status', 'notes', 'acknowledged_at', 'resolved_at']),
            'household_id' => $leakAlert->user_id,
        ], $leakAlert);

        return response()->json([
            'message' => 'Leak alert updated successfully.',
            'data' => $this->serializeAlert($leakAlert->load([
                'user:id,name,email,account_number,address,is_active,account_status',
                'firstReading',
                'latestReading.device',
                'notification',
            ])),
        ]);
    }

    public function notify(Request $request, LeakAlert $leakAlert)
    {
        $validated = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'message' => ['required', 'string', 'max:2000'],
        ]);

        $household = $leakAlert->user;
        if (! $household?->isHousehold() || ! $household->isAccountActive()) {
            return response()->json([
                'message' => 'The affected household account is not active and cannot receive a follow-up notification.',
            ], 422);
        }

        $notification = DB::transaction(function () use ($validated, $leakAlert, $request): Notification {
            $notification = Notification::create([
                'user_id' => $leakAlert->user_id,
                'water_reading_id' => $leakAlert->latest_water_reading_id,
                'type' => 'announcement',
                'title' => $validated['title'],
                'message' => $validated['message'],
                'metadata' => [
                    'leak_alert_id' => $leakAlert->id,
                    'sender_id' => $request->user()?->id,
                    'recipient_scope' => 'leak_alert_follow_up',
                ],
                'sent_at' => now(),
            ]);

            $leakAlert->update([
                'notification_id' => $notification->id,
                'notified_at' => $notification->sent_at,
            ]);

            return $notification;
        });

        $this->audit->recordAudit($request->user(), 'leak_alert_notified', 'Sent a leak-alert follow-up notification to a household.', [
            'leak_alert_id' => $leakAlert->id,
            'household_id' => $leakAlert->user_id,
            'notification_id' => $notification->id,
        ], $leakAlert);

        return response()->json([
            'message' => 'Follow-up notification sent to the affected household.',
            'data' => $this->serializeAlert($leakAlert->fresh()->load([
                'user:id,name,email,account_number,address,is_active,account_status',
                'firstReading',
                'latestReading.device',
                'notification',
            ])),
        ], 201);
    }

    private function syncLegacyAlerts(): void
    {
        Notification::query()
            ->with('waterReading')
            ->where('type', 'possible_leak')
            ->whereNotNull('user_id')
            ->get()
            ->groupBy('user_id')
            ->each(function ($notifications, $userId): void {
                if (LeakAlert::query()->where('user_id', $userId)->exists()) {
                    return;
                }

                $ordered = $notifications->sortBy(fn (Notification $notification) => $notification->sent_at ?? $notification->created_at);
                $first = $ordered->first();
                $latest = $ordered->last();
                $firstReading = $first?->waterReading;
                $latestReading = $latest?->waterReading
                    ?? WaterReading::query()->where('user_id', $userId)->latest('recorded_at')->latest('id')->first();

                LeakAlert::create([
                    'user_id' => $userId,
                    'first_water_reading_id' => $firstReading?->id,
                    'latest_water_reading_id' => $latestReading?->id,
                    'notification_id' => $latest?->id,
                    'status' => LeakAlert::STATUS_DETECTED,
                    'detected_at' => $firstReading?->recorded_at ?? $first?->sent_at ?? $first?->created_at,
                    'last_detected_at' => $latestReading?->recorded_at ?? $latest?->sent_at ?? $latest?->created_at,
                    'notified_at' => $latest?->sent_at ?? $latest?->created_at,
                ]);
            });
    }

    private function serializeAlert(LeakAlert $alert): array
    {
        $latestReading = $alert->latestReading;

        return [
            'id' => $alert->id,
            'status' => $alert->status,
            'notes' => $alert->notes,
            'detected_at' => $alert->detected_at,
            'last_detected_at' => $alert->last_detected_at,
            'notified_at' => $alert->notified_at,
            'acknowledged_at' => $alert->acknowledged_at,
            'resolved_at' => $alert->resolved_at,
            'user' => $alert->user,
            'first_reading' => $alert->firstReading,
            'latest_reading' => $latestReading,
            'device' => $latestReading?->device,
            'notification' => $alert->notification,
        ];
    }
}
