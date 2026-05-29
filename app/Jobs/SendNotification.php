<?php

namespace App\Jobs;

use App\Events\NotificationCreated;
use App\Models\FacultyUser;
use App\Models\Notification;
use App\Services\Domain\EventOutboxService;
use Illuminate\Bus\Batchable;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class SendNotification implements ShouldQueue
{
   use Batchable, Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        public FacultyUser $user,
        public string $title,
        public string $message,
        public string $type,
        public ?string $actionUrl = null,
    ) {
        $this->onQueue(config('careerconnect.queues.notifications'));
    }

    public function handle(EventOutboxService $outbox): void
    {
        $notification = Notification::create([
            'user_id' => $this->user->id,
            'title' => $this->title,
            'message' => $this->message,
            'type' => $this->type,
            'action_url' => $this->actionUrl,
        ]);

        NotificationCreated::dispatch($notification);

        $outbox->record('NotificationSent', [
            'notification_id' => $notification->id,
            'user_id' => $this->user->id,
            'type' => $this->type,
            'title' => $this->title,
        ]);
    }
}
