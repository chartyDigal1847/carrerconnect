<?php

use App\Models\MessageThread;
use Illuminate\Support\Facades\Broadcast;

/*
|--------------------------------------------------------------------------
| CareerConnect broadcast channels (Laravel Reverb / WebSockets)
|--------------------------------------------------------------------------
| Public channels: announcements, boards.{id}
| Private channels: notifications.{userId}, messages.thread.{threadId}
*/

Broadcast::channel('notifications.{userId}', function ($user, int $userId) {
    return (int) $user->id === $userId;
});

Broadcast::channel('messages.thread.{threadId}', function ($user, int $threadId) {
    $thread = MessageThread::find($threadId);

    if (! $thread) {
        return false;
    }

    if ($user->role === 'admin') {
        return true;
    }

    return $thread->creator_id === $user->id
        || in_array($user->id, $thread->participants ?? [], true);
});
