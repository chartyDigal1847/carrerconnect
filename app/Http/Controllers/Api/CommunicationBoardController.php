<?php

namespace App\Http\Controllers\Api;

use App\Models\CommunicationBoard;
use App\Models\BoardPost;
use App\Models\ActivityLog;
use App\Services\Domain\EventOutboxService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class CommunicationBoardController
{
    public function __construct(private EventOutboxService $events) {}
    public function index(Request $request): JsonResponse
    {
        $user = auth()->user();
        $departmentId = $user->departmentId();

        $boardsQuery = CommunicationBoard::query()->where('is_active', true);

        if ($user->role !== 'admin') {
            $boardsQuery->where(function ($q) use ($user, $departmentId) {
                $q->where('visibility', 'all')
                    ->orWhere('creator_id', $user->id)
                    ->orWhere(function ($q2) use ($departmentId) {
                        if ($departmentId) {
                            $q2->where('visibility', 'department')
                                ->where('department_id', $departmentId);
                        }
                    })
                    ->orWhere(function ($q2) use ($user) {
                        $q2->where('visibility', 'role')
                            ->whereJsonContains('allowed_roles', $user->role);
                    });
            });
        }

        $boards = $boardsQuery
            ->with('creator')
            ->paginate(20);

        return response()->json($boards);
    }

    public function show(CommunicationBoard $board): JsonResponse
    {
        $user = auth()->user();

        if (!$board->canAccess($user)) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $board->load(['creator', 'posts' => function ($q) {
            $q->where('status', 'approved')
                ->orderBy('is_pinned', 'desc')
                ->orderBy('created_at', 'desc')
                ->paginate(10);
        }]);

        return response()->json($board);
    }

    public function store(Request $request): JsonResponse
    {
        // Route middleware already enforces role:admin,instructor,admission_officer
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'string',
            'visibility' => 'in:all,department,role',
            'allowed_roles' => 'array',
            'is_moderated' => 'boolean',
        ]);

        $board = CommunicationBoard::create([
            ...$validated,
            'creator_id' => auth()->id(),
        ]);

        ActivityLog::create([
            'user_id' => auth()->id(),
            'action' => 'created',
            'entity_type' => 'board',
            'entity_id' => $board->id,
            'ip_address' => request()->ip(),
        ]);

        $this->events->record('CommunicationBoardCreated', [
            'board_id' => $board->id,
            'name' => $board->name,
            'creator_id' => $board->creator_id,
        ]);

        return response()->json($board, 201);
    }

    public function update(Request $request, CommunicationBoard $board): JsonResponse
    {
        if ($board->creator_id !== auth()->id() && auth()->user()->role !== 'admin') {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $validated = $request->validate([
            'name' => 'string|max:255',
            'description' => 'string',
            'is_active' => 'boolean',
        ]);

        $board->update($validated);

        return response()->json($board);
    }

    public function destroy(CommunicationBoard $board): JsonResponse
    {
        if ($board->creator_id !== auth()->id() && auth()->user()->role !== 'admin') {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $board->delete();

        return response()->json(null, 204);
    }
}
