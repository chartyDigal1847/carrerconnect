<?php

namespace App\Events;

use App\Models\Announcement;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;

class AnnouncementPublished implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets;

    public function __construct(public Announcement $announcement) {}

    public function broadcastOn(): array
    {
        $channels = [new Channel('announcements')];

        if ($this->announcement->department_id) {
            $channels[] = new Channel('announcements.department.'.$this->announcement->department_id);
        }

        return $channels;
    }

    public function broadcastAs(): string
    {
        return 'announcement.published';
    }

    public function broadcastWith(): array
    {
        $this->announcement->loadMissing('author:id,name');

        return [
            'id' => $this->announcement->id,
            'title' => $this->announcement->title,
            'priority' => $this->announcement->priority,
            'author_name' => $this->announcement->author?->name,
            'published_at' => $this->announcement->published_at?->toIso8601String(),
        ];
    }
}
