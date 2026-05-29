<?php

namespace App\Listeners;

use App\Events\MessageSent;
use App\Jobs\SendNotification;
use App\Models\FacultyUser;
use Illuminate\Support\Facades\Bus;

class NotifyMessageParticipants
{
    public function handle(MessageSent $event): void
    {
        $message = $event->message->loadMissing('thread', 'sender');
        $thread = $message->thread;
        $participantIds = array_unique(array_merge(
            [$thread->creator_id],
            $thread->participants ?? []
        ));

        $recipients = FacultyUser::whereIn('id', $participantIds)
            ->where('id', '!=', $message->sender_id)
            ->where('is_active', true)
            ->where('role', '!=', 'student')
            ->get();

        $jobs = $recipients->map(fn ($user) => new SendNotification(
            $user,
            'New message: '.$thread->subject,
            $message->sender->name.' sent a secure message',
            'message',
            '/messages/threads/'.$thread->id
        ))->all();

        if ($jobs) {
            Bus::batch($jobs)->dispatch();
        }
    }
}
