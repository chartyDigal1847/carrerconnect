<?php

namespace App\Http\Controllers\Api;

use App\Models\Announcement;
use App\Models\BoardPost;
use App\Models\Notification;
use App\Models\CareerResource;
use App\Models\CommunicationBoard;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class DashboardController
{
    public function stats(): JsonResponse
    {
        $user = auth()->user();
        $departmentId = $user->departmentId();

        $stats = [
            'total_announcements' => Announcement::where('is_active', true)
                ->where(function ($q) use ($user, $departmentId) {
                    $q->where('visibility', 'all')
                        ->orWhere('author_id', $user->id)
                        ->orWhere(function ($q2) use ($departmentId) {
                            if ($departmentId) {
                                $q2->where('visibility', 'department')
                                    ->where('department_id', $departmentId);
                            }
                        });
                })->count(),

            'total_boards' => CommunicationBoard::where('is_active', true)
                ->where(function ($q) use ($user, $departmentId) {
                    $q->where('visibility', 'all')
                        ->orWhere('creator_id', $user->id)
                        ->orWhere(function ($q2) use ($departmentId) {
                            if ($departmentId) {
                                $q2->where('visibility', 'department')
                                    ->where('department_id', $departmentId);
                            }
                        });
                })->count(),

            'unread_notifications' => Notification::where('user_id', $user->id)
                ->where('is_read', false)
                ->count(),

            'total_resources' => CareerResource::where('is_approved', true)->count(),

            'recent_announcements' => Announcement::where('is_active', true)
                ->where(function ($q) use ($departmentId) {
                    $q->where('visibility', 'all')
                        ->orWhere(function ($q2) use ($departmentId) {
                            if ($departmentId) {
                                $q2->where('visibility', 'department')
                                    ->where('department_id', $departmentId);
                            }
                        });
                })
                ->orderBy('published_at', 'desc')
                ->limit(5)
                ->with('author')
                ->get(),

            'recent_posts' => BoardPost::where('status', 'approved')
                ->orderBy('created_at', 'desc')
                ->limit(5)
                ->with('author', 'board')
                ->get(),
        ];

        return response()->json($stats);
    }

    public function analytics(): JsonResponse
    {
        if (auth()->user()->role !== 'admin') {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $analytics = [
            'announcement_stats' => [
                'total' => Announcement::count(),
                'published' => Announcement::where('is_active', true)->count(),
                'by_priority' => Announcement::selectRaw('priority, count(*) as count')
                    ->groupBy('priority')
                    ->get(),
            ],

            'engagement_stats' => [
                'total_board_posts' => BoardPost::count(),
                'total_comments' => DB::table('board_comments')->count(),
                'most_viewed_post' => BoardPost::orderBy('views_count', 'desc')
                    ->first(['id', 'title', 'views_count']),
            ],

            'resource_stats' => [
                'total_resources' => CareerResource::count(),
                'approved_resources' => CareerResource::where('is_approved', true)->count(),
                'total_downloads' => CareerResource::sum('downloads_count'),
            ],

            'user_activity' => [
                'total_users' => DB::table('faculty_users')->where('is_active', true)->count(),
                'by_role' => DB::table('faculty_users')
                    ->where('is_active', true)
                    ->selectRaw('role, count(*) as count')
                    ->groupBy('role')
                    ->get(),
            ],
        ];

        return response()->json($analytics);
    }
}
