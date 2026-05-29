<?php

namespace App\Http\Controllers\Api;

use App\Models\Announcement;
use App\Models\BoardPost;
use App\Models\Notification;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class RealtimeController
{
    /**
     * Lightweight polling endpoint for live updates (notifications, announcements).
     */
    public function poll(Request $request): JsonResponse
    {
        $user = auth()->user();
        $since = $request->query('since')
            ? Carbon::parse($request->query('since'))
            : now()->subMinutes(5);

        $notifications = Notification::where('user_id', $user->id)
            ->where('is_archived', false)
            ->where('created_at', '>', $since)
            ->orderByDesc('created_at')
            ->limit(15)
            ->get(['id', 'title', 'message', 'type', 'action_url', 'is_read', 'created_at']);

        $departmentId = $user->departmentId();

        $newAnnouncements = Announcement::where('is_active', true)
            ->where('published_at', '>', $since)
            ->where(function ($q) use ($user, $departmentId) {
                $q->where('visibility', 'all')
                    ->orWhere('author_id', $user->id)
                    ->orWhere(function ($q2) use ($departmentId) {
                        if ($departmentId) {
                            $q2->where('visibility', 'department')
                                ->where('department_id', $departmentId);
                        }
                    });
            })
            ->count();

        $newPosts = BoardPost::where('status', 'approved')
            ->where('created_at', '>', $since)
            ->count();

        $unreadCount = Notification::where('user_id', $user->id)
            ->where('is_read', false)
            ->where('is_archived', false)
            ->count();

        return response()->json([
            'server_time' => now()->toIso8601String(),
            'unread_count' => $unreadCount,
            'notifications' => $notifications,
            'counts' => [
                'new_announcements' => $newAnnouncements,
                'new_posts' => $newPosts,
            ],
        ]);
    }
}
