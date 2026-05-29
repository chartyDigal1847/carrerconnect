<?php

namespace App\Http\Controllers\Api;

use App\Models\Announcement;
use App\Models\CommunicationBoard;
use App\Models\BoardPost;
use App\Models\CareerResource;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class SearchController
{
    public function search(Request $request): JsonResponse
    {
        $query = $request->query('q');
        $type = $request->query('type'); // announcement, board, post, resource, all

        if (!$query || strlen($query) < 2) {
            return response()->json(['error' => 'Query too short'], 400);
        }

        $user = auth()->user();
        $departmentId = $user->departmentId();
        $results = [];

        // Search announcements
        if (!$type || $type === 'announcement' || $type === 'all') {
            $announcements = Announcement::whereFullText(
                ['title', 'content'],
                $query
            )
                ->where('is_active', true)
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
                ->limit(10)
                ->get();

            $results['announcements'] = $announcements;
        }

        // Search boards
        if (!$type || $type === 'board' || $type === 'all') {
            $boards = CommunicationBoard::where('name', 'like', '%' . $query . '%')
                ->where('is_active', true)
                ->where(function ($q) use ($user) {
                    $q->where('visibility', 'all')
                        ->orWhere('creator_id', $user->id)
                        ->orWhere(function ($q2) use ($user) {
                            $q2->where('visibility', 'department')
                                ->where('department_id', $user->departmentId());
                        });
                })
                ->limit(10)
                ->get();

            $results['boards'] = $boards;
        }

        // Search posts
        if (!$type || $type === 'post' || $type === 'all') {
            $posts = BoardPost::whereFullText(['title', 'content'], $query)
                ->where('status', 'approved')
                ->limit(10)
                ->get();

            $results['posts'] = $posts;
        }

        // Search resources
        if (!$type || $type === 'resource' || $type === 'all') {
            $resources = CareerResource::whereFullText(['title', 'description'], $query)
                ->where('is_approved', true)
                ->limit(10)
                ->get();

            $results['resources'] = $resources;
        }

        return response()->json($results);
    }
}
