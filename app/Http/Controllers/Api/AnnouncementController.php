<?php

namespace App\Http\Controllers\Api;

use App\Models\Announcement;
use App\Models\ActivityLog;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class AnnouncementController
{
    public function index(Request $request): JsonResponse
    {
        $user = auth()->user();
        $departmentId = $user->departmentId();

        $query = Announcement::query()
            ->where(function ($q) use ($user, $departmentId) {
                $q->where('visibility', 'all')
                    ->orWhere(function ($q2) use ($departmentId) {
                        if ($departmentId) {
                            $q2->where('visibility', 'department')
                                ->where('department_id', $departmentId);
                        }
                    })
                    ->orWhere(function ($q2) use ($user) {
                        $q2->where('visibility', 'role')
                            ->whereJsonContains('target_roles', $user->role);
                    });
            })
            ->where('is_active', true)
            ->orderBy('is_pinned', 'desc')
            ->orderBy('published_at', 'desc')
            ->paginate(15);

        return response()->json($query);
    }

    public function show(Announcement $announcement): JsonResponse
    {
        $user = auth()->user();

        if (!$announcement->canAccess($user)) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $announcement->incrementViews();

        return response()->json($announcement->load('author'));
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'content' => 'required|string',
            'priority' => 'in:low,normal,high,urgent',
            'visibility' => 'in:all,department,role',
            'target_roles' => 'array',
            'department_id' => 'nullable|exists:departments,id',
            'expires_at' => 'date',
        ]);

        // Admins can target any department explicitly; others default to their own.
        $departmentId = null;
        if (($validated['visibility'] ?? 'all') === 'department') {
            $departmentId = isset($validated['department_id'])
                ? (int) $validated['department_id']
                : auth()->user()->departmentId();
        }

        $announcement = Announcement::create([
            ...$validated,
            'author_id' => auth()->id(),
            'department_id' => $departmentId,
            'published_at' => now(),
            'is_active' => true,
        ]);

        // Log activity
        ActivityLog::create([
            'user_id' => auth()->id(),
            'action' => 'created',
            'entity_type' => 'announcement',
            'entity_id' => $announcement->id,
            'ip_address' => request()->ip(),
        ]);

        return response()->json($announcement, 201);
    }

    public function update(Request $request, Announcement $announcement): JsonResponse
    {
        if ($announcement->author_id !== auth()->id() && auth()->user()->role !== 'admin') {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $validated = $request->validate([
            'title' => 'string|max:255',
            'content' => 'string',
            'priority' => 'in:low,normal,high,urgent',
            'is_pinned' => 'boolean',
        ]);

        $announcement->update($validated);

        ActivityLog::create([
            'user_id' => auth()->id(),
            'action' => 'updated',
            'entity_type' => 'announcement',
            'entity_id' => $announcement->id,
            'ip_address' => request()->ip(),
        ]);

        return response()->json($announcement);
    }

    public function destroy(Announcement $announcement): JsonResponse
    {
        if ($announcement->author_id !== auth()->id() && auth()->user()->role !== 'admin') {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $announcement->delete();

        ActivityLog::create([
            'user_id' => auth()->id(),
            'action' => 'deleted',
            'entity_type' => 'announcement',
            'entity_id' => $announcement->id,
            'ip_address' => request()->ip(),
        ]);

        return response()->json(null, 204);
    }
}
