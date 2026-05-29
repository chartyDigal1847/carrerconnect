<?php

namespace App\Observers;

use App\Models\BoardPost;
use App\Events\PostCreated;

class BoardPostObserver
{
    public function created(BoardPost $post): void
    {
        if ($post->status === 'approved') {
            PostCreated::dispatch($post);
        }
    }

    public function updated(BoardPost $post): void
    {
        if ($post->wasChanged('status') && $post->status === 'approved') {
            PostCreated::dispatch($post);
        }
    }
}
