<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Notification;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class NotificationController extends Controller
{
    private const ADMIN_SENT_TYPES = ['service_interruption', 'maintenance', 'announcement'];

    public function index(Request $request)
    {
        $validated = $request->validate([
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $query = Notification::query()->with('user:id,name,email')->latest();

        if ($request->user()?->isAdmin() !== true) {
            $query->where('user_id', $request->user()?->id)
                ->whereNull('user_deleted_at');
        } elseif ($request->filled('user_id')) {
            $query->where('user_id', $request->integer('user_id'));
        }

        return response()->json([
            'data' => $query
                ->paginate($validated['per_page'] ?? 10)
                ->withQueryString(),
        ]);
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
            'type' => ['required', 'in:service_interruption,maintenance,announcement'],
            'title' => ['required', 'string', 'max:255'],
            'message' => ['required', 'string', 'max:2000'],
        ]);
        $recipients = isset($validated['user_id'])
            ? User::query()->whereKey($validated['user_id'])->where('role', 'user')->get()
            : User::query()->where('role', 'user')->where('is_active', true)->get();
        $batchId = (string) Str::uuid();
        $sentAt = now();
        $items = $recipients->map(fn (User $user) => Notification::create([
            'user_id' => $user->id,
            'type' => $validated['type'],
            'batch_id' => $batchId,
            'title' => $validated['title'],
            'message' => $validated['message'],
            'sent_at' => $sentAt,
        ]));

        return response()->json(['message' => 'Notification sent.', 'data' => $items], 201);
    }

    public function destroy(Notification $notification)
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

        return response()->json([
            'message' => 'Sent notification deleted successfully.',
            'data' => ['deleted_count' => $deleted],
        ]);
    }

    public function destroyAllSent()
    {
        $deleted = Notification::query()->whereIn('type', self::ADMIN_SENT_TYPES)->delete();

        return response()->json([
            'message' => 'All sent notifications deleted successfully.',
            'data' => ['deleted_count' => $deleted],
        ]);
    }
}
