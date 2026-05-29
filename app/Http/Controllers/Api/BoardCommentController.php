<?php

namespace App\Http\Controllers\Api;

use App\Models\ActivityLog;
use App\Models\BoardComment;
use App\Models\BoardPost;
use App\Models\CommunicationBoard;
use App\Services\Security\RoleCapabilityService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BoardCommentController
{
    public function __construct(private RoleCapabilityService $capabilities) {}

    public function index(CommunicationBoard $board, BoardPost $post): JsonResponse
    {
        $this->authorizeBoardPost($board, $post);

        $comments = BoardComment::where('post_id', $post->id)
            ->where('status', 'approved')
            ->with(['author:id,name', 'replies.author:id,name'])
            ->whereNull('parent_id')
            ->orderBy('created_at')
            ->paginate(30);

        return response()->json($comments);
    }

    public function store(CommunicationBoard $board, BoardPost $post, Request $request): JsonResponse
    {
        $this->authorizeBoardPost($board, $post);

        if (! $this->capabilities->can(auth()->user(), 'boards.comment')) {
            return response()->json(['error' => 'Insufficient permissions'], 403);
        }

        $validated = $request->validate([
            'content' => 'required|string|max:3000',
            'parent_id' => 'nullable|exists:board_comments,id',
        ]);

        $comment = BoardComment::create([
            'post_id' => $post->id,
            'author_id' => auth()->id(),
            'parent_id' => $validated['parent_id'] ?? null,
            'content' => $validated['content'],
            'status' => $board->is_moderated ? 'pending' : 'approved',
        ]);

        ActivityLog::create([
            'user_id' => auth()->id(),
            'action' => 'commented',
            'entity_type' => 'comment',
            'entity_id' => $comment->id,
            'ip_address' => $request->ip(),
        ]);

        return response()->json($comment->load('author:id,name'), 201);
    }

    public function update(Request $request, BoardComment $comment): JsonResponse
    {
        if ($comment->author_id !== auth()->id() && auth()->user()->role !== 'admin') {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $validated = $request->validate(['content' => 'required|string|max:3000']);
        $comment->update($validated);

        return response()->json($comment);
    }

    public function destroy(BoardComment $comment): JsonResponse
    {
        if ($comment->author_id !== auth()->id() && ! $this->capabilities->can(auth()->user(), 'moderation.*')) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $comment->delete();

        return response()->json(null, 204);
    }

    private function authorizeBoardPost(CommunicationBoard $board, BoardPost $post): void
    {
        if ($post->board_id !== $board->id || ! $board->canAccess(auth()->user())) {
            abort(403, 'Unauthorized');
        }
    }
}
