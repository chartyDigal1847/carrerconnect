<?php

namespace App\Events;

use App\Models\BoardPost;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;

class PostCreated implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets;

    public function __construct(public BoardPost $post) {}

    public function broadcastOn(): array
    {
        return [
            new Channel('boards.'.$this->post->board_id),
        ];
    }

    public function broadcastAs(): string
    {
        return 'post.created';
    }

    public function broadcastWith(): array
    {
        $this->post->loadMissing('author:id,name');

        return [
            'id' => $this->post->id,
            'board_id' => $this->post->board_id,
            'title' => $this->post->title,
            'author_name' => $this->post->author?->name,
        ];
    }
}
