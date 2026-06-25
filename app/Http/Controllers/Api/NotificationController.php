<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Notification;
use Illuminate\Http\Request;

class NotificationController extends Controller
{
    public function index(Request $request)
    {
        $validated = $request->validate([
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $query = Notification::query()->latest();

        if ($request->user()?->isAdmin() !== true) {
            $query->where('user_id', $request->user()?->id);
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
}
