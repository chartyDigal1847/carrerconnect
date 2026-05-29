<?php

namespace App\Http\Controllers\Api;

use App\Models\FacultyUser;
use App\Models\MessageThread;
use App\Models\Message;
use App\Services\Domain\EventOutboxService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class MessageController
{
    public function __construct(private EventOutboxService $events) {}

    /**
     * Return a list of all faculty users the current user can message.
     * Excludes the authenticated user themselves.
     */
    public function facultyDirectory(): JsonResponse
    {
        $currentId = auth()->id();

        $faculty = FacultyUser::where('id', '!=', $currentId)
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name', 'email', 'role', 'department']);

        return response()->json($faculty);
    }

    public function threads(): JsonResponse
    {
        $threads = MessageThread::where('creator_id', auth()->id())
            ->orWhereJsonContains('participants', auth()->id())
            ->with(['creator', 'messages' => function ($q) {
                $q->latest()->limit(1);
            }])
            ->latest('last_message_at')
            ->paginate(20);

        return response()->json($threads);
    }

    public function createThread(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'subject' => 'required|string|max:255',
            'participants' => 'required|array|min:1',
            'participants.*' => 'exists:faculty_users,id',
        ]);

        $thread = MessageThread::create([
            'subject' => $validated['subject'],
            'creator_id' => auth()->id(),
            'participants' => array_merge(
                [$auth = auth()->id()],
                array_filter($validated['participants'], fn($id) => $id !== auth()->id())
            ),
        ]);

        return response()->json($thread, 201);
    }

    public function getThread(MessageThread $thread): JsonResponse
    {
        $user = auth()->user();

        if ($thread->creator_id !== $user->id && !in_array($user->id, $thread->participants ?? [])) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $thread->load(['creator', 'messages' => function ($q) {
            $q->with('sender')->latest()->paginate(50);
        }]);

        return response()->json($thread);
    }

    public function sendMessage(MessageThread $thread, Request $request): JsonResponse
    {
        $user = auth()->user();

        if ($thread->creator_id !== $user->id && !in_array($user->id, $thread->participants ?? [])) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $validated = $request->validate([
            'content' => 'required|string|max:5000',
        ]);

        $message = Message::create([
            'thread_id' => $thread->id,
            'sender_id' => auth()->id(),
            'content' => $validated['content'],
        ]);

        $thread->update([
            'last_message_at' => now(),
            'messages_count' => $thread->messages()->count(),
        ]);

        $this->events->record('FacultyMessagePosted', [
            'thread_id' => $thread->id,
            'message_id' => $message->id,
            'sender_id' => auth()->id(),
            'subject' => $thread->subject,
        ]);

        return response()->json($message->load('sender'), 201);
    }

    public function markMessageAsRead(Message $message): JsonResponse
    {
        $message->markAsReadBy(auth()->id());

        return response()->json($message);
    }
}
