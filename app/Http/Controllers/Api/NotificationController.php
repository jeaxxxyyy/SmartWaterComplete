<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Notification;
use App\Models\User;
use App\Services\BillingStatementService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class NotificationController extends Controller
{
    public const ADMIN_SENT_TYPES = ['announcement', 'service_interruption', 'maintenance', 'billing', 'leak_alert', 'device_system'];

    public function __construct(protected BillingStatementService $audit) {}

    public function index(Request $request)
    {
        $validated = $request->validate([
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $query = Notification::query()->with('user:id,name,email')->latest();

        if ($request->user()?->isAdmin() !== true) {
            $query->delivered()
                ->where('user_id', $request->user()?->id)
                ->whereNull('user_deleted_at');
            if ($this->isWithinQuietHours($request->user()?->notificationPreference)) {
                $query->whereIn('type', ['possible_leak', 'leak_alert', 'service_interruption']);
            }
        } elseif ($request->filled('user_id')) {
            $query->delivered()
                ->whereNull('archived_at')
                ->where('user_id', $request->integer('user_id'));
        } else {
            $query->delivered()->whereNull('archived_at');
        }

        return response()->json([
            'data' => $query
                ->paginate($validated['per_page'] ?? 10)
                ->withQueryString(),
        ]);
    }

    public function adminIndex(Request $request)
    {
        $validated = $request->validate([
            'type' => ['nullable', Rule::in(self::ADMIN_SENT_TYPES)],
            'status' => ['nullable', Rule::in(['scheduled', 'delivered', 'failed', 'partial'])],
            'search' => ['nullable', 'string', 'max:255'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
            'page' => ['nullable', 'integer', 'min:1'],
        ]);

        $items = Notification::query()
            ->with('user:id,name,email,account_number')
            ->whereIn('type', self::ADMIN_SENT_TYPES)
            ->whereNull('archived_at')
            ->when(isset($validated['type']), fn ($query) => $query->where('type', $validated['type']))
            ->when(isset($validated['search']), function ($query) use ($validated): void {
                $like = '%'.trim($validated['search']).'%';
                $query->where(function ($builder) use ($like): void {
                    $builder->where('title', 'like', $like)
                        ->orWhere('message', 'like', $like)
                        ->orWhereHas('user', fn ($userQuery) => $userQuery->where('name', 'like', $like)->orWhere('email', 'like', $like)->orWhere('account_number', 'like', $like));
                });
            })
            ->latest('sent_at')
            ->latest('scheduled_at')
            ->latest('id')
            ->get();

        $records = $items->groupBy(fn (Notification $item): string => $item->batch_id ?? 'notification-'.$item->id)
            ->map(fn ($batch): array => $this->serializeBatch($batch))
            ->filter(fn (array $batch): bool => ! isset($validated['status']) || $batch['delivery_status'] === $validated['status'])
            ->sortByDesc(fn (array $batch) => $batch['sent_at'] ?? $batch['scheduled_at'] ?? '')
            ->values();
        $perPage = $validated['per_page'] ?? 10;
        $page = $validated['page'] ?? 1;
        $paginator = new LengthAwarePaginator(
            $records->forPage($page, $perPage)->values(),
            $records->count(),
            $perPage,
            $page,
            ['path' => $request->url(), 'query' => $request->query()],
        );

        return response()->json(['data' => $paginator]);
    }

    public function markAsRead(Request $request, Notification $notification)
    {
        if ($request->user()?->isAdmin() !== true && $notification->user_id !== $request->user()?->id) {
            return response()->json([
                'message' => 'You are not allowed to update this notification.',
            ], 403);
        }

        $notification->update([
            'is_read' => true,
        ]);

        return response()->json([
            'message' => 'Notification marked as read',
            'data' => $notification->fresh(),
        ]);
    }

    public function markAllAsRead(Request $request)
    {
        $updated = Notification::query()
            ->delivered()
            ->where('user_id', $request->user()->id)
            ->whereNull('user_deleted_at')
            ->where('is_read', false)
            ->update(['is_read' => true]);

        return response()->json([
            'message' => $updated === 1 ? '1 notification marked as read.' : "{$updated} notifications marked as read.",
            'data' => ['updated_count' => $updated],
        ]);
    }

    public function destroyReceived(Request $request, Notification $notification)
    {
        if ($notification->user_id !== $request->user()?->id) {
            return response()->json(['message' => 'You are not allowed to delete this notification.'], 403);
        }

        $notification->update(['user_deleted_at' => now()]);

        return response()->json(['message' => 'Notification deleted from your inbox.']);
    }

    public function destroyAllReceived(Request $request)
    {
        $deleted = Notification::query()
            ->where('user_id', $request->user()->id)
            ->whereNull('user_deleted_at')
            ->update(['user_deleted_at' => now()]);

        return response()->json([
            'message' => 'All notifications cleared from your inbox.',
            'data' => ['deleted_count' => $deleted],
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'user_id' => ['nullable', 'integer', 'exists:users,id'],
            'audience' => ['nullable', Rule::in(['all_active_households', 'selected_household'])],
            'type' => ['required', Rule::in(self::ADMIN_SENT_TYPES)],
            'title' => ['required', 'string', 'max:255'],
            'message' => ['required', 'string', 'max:2000'],
            'scheduled_at' => ['nullable', 'date', 'after:now'],
        ]);
        $audience = $validated['audience'] ?? (isset($validated['user_id']) ? 'selected_household' : 'all_active_households');
        if ($audience === 'selected_household' && ! isset($validated['user_id'])) {
            throw ValidationException::withMessages(['user_id' => 'Choose a household for a targeted notification.']);
        }
        if ($audience === 'all_active_households' && isset($validated['user_id'])) {
            throw ValidationException::withMessages(['audience' => 'Choose selected household when a recipient is specified.']);
        }
        $scheduledAt = isset($validated['scheduled_at']) ? Carbon::parse($validated['scheduled_at']) : null;
        $recipients = User::query()
            ->with('notificationPreference')
            ->where('role', 'user')
            ->where('is_active', true)
            ->where(function ($query): void {
                $query->whereNull('account_status')->orWhere('account_status', User::ACCOUNT_STATUS_ACTIVE);
            })
            ->when($audience === 'selected_household', fn ($query) => $query->whereKey($validated['user_id']))
            ->get()
            ->filter(function (User $user) use ($validated): bool {
                $preference = $user->notificationPreference;
                if (! $preference) {
                    return true;
                }

                return match ($validated['type']) {
                    'billing' => $preference->billing_updates,
                    'leak_alert' => $preference->leak_alerts,
                    'announcement', 'maintenance', 'device_system' => $preference->announcements,
                    default => true,
                };
            })
            ->values();
        $batchId = (string) Str::uuid();
        $sentAt = $scheduledAt ? null : now();
        $deliveryStatus = $scheduledAt ? 'scheduled' : 'delivered';
        $recipientCount = $recipients->count();

        if ($recipientCount === 0) {
            $this->audit->recordAudit($request->user(), 'notification_send_empty', 'No eligible household users were found for an administrative notification.', [
                'type' => $validated['type'],
                'target_user_id' => $validated['user_id'] ?? null,
            ], null, 'failure');

            return response()->json([
                'message' => 'No eligible household users were found.',
                'data' => [],
                'recipient_count' => 0,
                'delivered_count' => 0,
            ], 422);
        }

        try {
            $items = DB::transaction(fn () => $recipients->map(fn (User $user) => Notification::create([
                'user_id' => $user->id,
                'type' => $validated['type'],
                'batch_id' => $batchId,
                'title' => $validated['title'],
                'message' => $validated['message'],
                'metadata' => [
                    'sender_id' => $request->user()?->id,
                    'recipient_scope' => $audience,
                ],
                'sent_at' => $sentAt,
                'scheduled_at' => $scheduledAt,
                'delivery_status' => $deliveryStatus,
            ])));
        } catch (\Throwable $exception) {
            Log::error('Smart Water administrative notification delivery failed.', [
                'admin_id' => $request->user()?->id,
                'batch_id' => $batchId,
                'recipient_count' => $recipientCount,
                'exception' => $exception,
            ]);

            throw $exception;
        }

        $this->audit->recordAudit($request->user(), $scheduledAt ? 'notification_scheduled' : 'notification_sent', $scheduledAt ? 'Scheduled an administrative notification.' : 'Sent an administrative notification.', [
            'type' => $validated['type'],
            'recipient_count' => $items->count(),
            'target_user_id' => $validated['user_id'] ?? null,
            'scheduled_at' => $scheduledAt,
        ]);

        $recipientLabel = $items->count() === 1 ? 'household user' : 'household users';

        return response()->json([
            'message' => $scheduledAt
                ? "Notification scheduled for {$scheduledAt->format('M j, Y g:i A')} for {$items->count()} {$recipientLabel}."
                : "Notification sent successfully to {$items->count()} {$recipientLabel}.",
            'data' => $items,
            'recipient_count' => $recipientCount,
            'delivered_count' => $scheduledAt ? 0 : $items->count(),
            'scheduled_count' => $scheduledAt ? $items->count() : 0,
        ], 201);
    }

    public function retry(Request $request, Notification $notification)
    {
        $batch = $this->adminBatch($notification);
        $failed = $batch->where('delivery_status', 'failed');
        if ($failed->isEmpty()) {
            return response()->json(['message' => 'There are no failed recipients to retry.'], 422);
        }

        $sentAt = now();
        Notification::query()->whereIn('id', $failed->pluck('id'))->update([
            'delivery_status' => 'delivered',
            'delivery_error' => null,
            'sent_at' => $sentAt,
            'scheduled_at' => null,
        ]);
        $this->audit->recordAudit($request->user(), 'notification_retried', 'Retried failed administrative notification deliveries.', [
            'notification_id' => $notification->id,
            'batch_id' => $notification->batch_id,
            'retried_count' => $failed->count(),
        ]);

        return response()->json([
            'message' => "Retried {$failed->count()} notification delivery record(s).",
            'data' => $this->serializeBatch($this->adminBatch($notification->fresh())),
        ]);
    }

    public function archive(Request $request, Notification $notification)
    {
        $batch = $this->adminBatch($notification);
        $archived = Notification::query()->whereIn('id', $batch->pluck('id'))->update(['archived_at' => now()]);
        $this->audit->recordAudit($request->user(), 'notification_archived', 'Archived an administrative notification batch.', [
            'notification_id' => $notification->id,
            'batch_id' => $notification->batch_id,
            'archived_count' => $archived,
        ]);

        return response()->json(['message' => 'Notification batch archived successfully.', 'data' => ['archived_count' => $archived]]);
    }

    public function destroy(Request $request, Notification $notification)
    {
        if (! in_array($notification->type, self::ADMIN_SENT_TYPES, true)) {
            return response()->json(['message' => 'Only admin-sent notifications can be deleted here.'], 422);
        }

        $deleted = $notification->batch_id
            ? Notification::query()->where('batch_id', $notification->batch_id)->delete()
            : Notification::query()
                ->where('type', $notification->type)
                ->where('title', $notification->title)
                ->where('message', $notification->message)
                ->whereBetween('sent_at', [optional($notification->sent_at)->copy()->subSeconds(5), optional($notification->sent_at)->copy()->addSeconds(5)])
                ->delete();

        $this->audit->recordAudit($request->user(), 'notification_deleted', 'Deleted an administrative notification batch.', [
            'notification_id' => $notification->id,
            'deleted_count' => $deleted,
        ]);

        return response()->json([
            'message' => 'Sent notification deleted successfully.',
            'data' => ['deleted_count' => $deleted],
        ]);
    }

    public function destroyAllSent(Request $request)
    {
        $deleted = Notification::query()->whereIn('type', self::ADMIN_SENT_TYPES)->delete();

        $this->audit->recordAudit($request->user(), 'notifications_deleted', 'Deleted all administrative notification records.', [
            'deleted_count' => $deleted,
        ]);

        return response()->json([
            'message' => 'All sent notifications deleted successfully.',
            'data' => ['deleted_count' => $deleted],
        ]);
    }

    private function adminBatch(Notification $notification)
    {
        abort_unless(in_array($notification->type, self::ADMIN_SENT_TYPES, true), 422, 'Only admin-sent notifications can be managed here.');

        return Notification::query()
            ->with('user:id,name,email,account_number')
            ->whereIn('type', self::ADMIN_SENT_TYPES)
            ->when($notification->batch_id, fn ($query) => $query->where('batch_id', $notification->batch_id), fn ($query) => $query->whereKey($notification->id))
            ->get();
    }

    private function serializeBatch($batch): array
    {
        $first = $batch->first();
        $statuses = $batch->pluck('delivery_status')->filter()->unique()->values();
        $deliveryStatus = $statuses->contains('failed')
            ? ($statuses->contains('delivered') ? 'partial' : 'failed')
            : ($statuses->contains('scheduled') ? 'scheduled' : 'delivered');
        $metadata = is_array($first?->metadata) ? $first->metadata : [];

        return [
            'id' => $first?->id,
            'batch_id' => $first?->batch_id,
            'type' => $first?->type,
            'title' => $first?->title,
            'message' => $first?->message,
            'audience' => $metadata['recipient_scope'] ?? ($batch->count() === 1 ? 'selected_household' : 'all_active_households'),
            'recipient_count' => $batch->count(),
            'delivered_count' => $batch->where('delivery_status', 'delivered')->count(),
            'failed_count' => $batch->where('delivery_status', 'failed')->count(),
            'read_count' => $batch->where('is_read', true)->count(),
            'unread_count' => $batch->where('is_read', false)->count(),
            'delivery_status' => $deliveryStatus,
            'sent_at' => $batch->max('sent_at'),
            'scheduled_at' => $batch->min('scheduled_at'),
            'created_at' => $batch->min('created_at'),
            'recipients' => $batch->map(fn (Notification $item): array => [
                'id' => $item->user_id,
                'name' => $item->user?->name,
                'email' => $item->user?->email,
                'delivery_status' => $item->delivery_status,
                'is_read' => (bool) $item->is_read,
            ])->values(),
        ];
    }

    private function isWithinQuietHours($preference): bool
    {
        if (! $preference?->quiet_hours_start || ! $preference?->quiet_hours_end) {
            return false;
        }

        $current = now(config('app.timezone'))->format('H:i:s');
        $start = (string) $preference->quiet_hours_start;
        $end = (string) $preference->quiet_hours_end;

        return $start < $end
            ? $current >= $start && $current < $end
            : $current >= $start || $current < $end;
    }
}
