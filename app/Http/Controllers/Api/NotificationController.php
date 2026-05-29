<?php

namespace App\Http\Controllers\Api;

use App\Models\Notification;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class NotificationController
{
    public function index(Request $request): JsonResponse
    {
        $unread = $request->boolean('unread');

        $query = Notification::where('user_id', auth()->id())
            ->where('is_archived', false)
            ->orderBy('created_at', 'desc');

        if ($unread) {
            $query->where('is_read', false);
        }

        $notifications = $query->paginate(20);

        return response()->json($notifications);
    }

    public function markAsRead(Notification $notification): JsonResponse
    {
        if ($notification->user_id !== auth()->id()) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $notification->markAsRead();

        return response()->json($notification);
    }

    public function markAllAsRead(): JsonResponse
    {
        Notification::where('user_id', auth()->id())
            ->where('is_read', false)
            ->update(['is_read' => true, 'read_at' => now()]);

        return response()->json(['message' => 'All notifications marked as read']);
    }

    public function archive(Notification $notification): JsonResponse
    {
        if ($notification->user_id !== auth()->id()) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $notification->update(['is_archived' => true]);

        return response()->json($notification);
    }

    public function getUnreadCount(): JsonResponse
    {
        $count = Notification::where('user_id', auth()->id())
            ->where('is_read', false)
            ->where('is_archived', false)
            ->count();

        return response()->json(['unread_count' => $count]);
    }
}
