<?php

namespace App\Http\Controllers\Api;

use App\Models\BoardPost;
use App\Models\CommunicationBoard;
use App\Models\ActivityLog;
use App\Services\Security\RoleCapabilityService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class BoardPostController
{
    public function __construct(private RoleCapabilityService $capabilities) {}
    public function index(CommunicationBoard $board, Request $request): JsonResponse
    {
        $user = auth()->user();

        if (!$board->canAccess($user)) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $posts = BoardPost::where('board_id', $board->id)
            ->where('status', 'approved')
            ->with('author')
            ->orderBy('is_pinned', 'desc')
            ->orderBy('created_at', 'desc')
            ->paginate(20);

        return response()->json($posts);
    }

    public function show(CommunicationBoard $board, BoardPost $post): JsonResponse
    {
        $user = auth()->user();

        if (!$board->canAccess($user) || $post->board_id !== $board->id) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $post->incrementViews();
        $post->load(['author', 'comments' => function ($q) {
            $q->where('status', 'approved')
                ->orderBy('created_at', 'asc')
                ->with('author');
        }]);

        return response()->json($post);
    }

    public function store(CommunicationBoard $board, Request $request): JsonResponse
    {
        $user = auth()->user();

        if (!$board->canAccess($user)) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        // Only roles with boards.post capability can create posts
        if (! $this->capabilities->can($user, 'boards.post')) {
            return response()->json(['error' => 'Insufficient permissions to post'], 403);
        }

        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'content' => 'required|string',
        ]);

        $post = BoardPost::create([
            ...$validated,
            'board_id' => $board->id,
            'author_id' => auth()->id(),
            'status' => $board->is_moderated ? 'pending' : 'approved',
        ]);

        // posts_count is maintained by the DB trigger trg_board_post_increment_board_count

        ActivityLog::create([
            'user_id' => auth()->id(),
            'action' => 'posted',
            'entity_type' => 'post',
            'entity_id' => $post->id,
            'ip_address' => request()->ip(),
        ]);

        return response()->json($post, 201);
    }

    public function update(BoardPost $post, Request $request): JsonResponse
    {
        if ($post->author_id !== auth()->id() && auth()->user()->role !== 'admin') {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $validated = $request->validate([
            'title' => 'string|max:255',
            'content' => 'string',
        ]);

        $post->update($validated);

        return response()->json($post);
    }

    public function destroy(BoardPost $post): JsonResponse
    {
        if ($post->author_id !== auth()->id() && auth()->user()->role !== 'admin') {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $post->board()->decrement('posts_count');
        $post->delete();

        return response()->json(null, 204);
    }
}
