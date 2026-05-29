<?php

namespace App\Listeners;

use App\Events\PostCreated;
use App\Jobs\SendNotification;
use App\Models\CommunicationBoard;
use App\Models\FacultyUser;
use Illuminate\Support\Facades\Bus;

class NotifyBoardActivity
{
    public function handle(PostCreated $event): void
    {
        $post = $event->post->loadMissing('board', 'author');
        $board = $post->board;

        $users = $this->boardAudience($board)
            ->where('id', '!=', $post->author_id);

        $jobs = $users->map(fn ($user) => new SendNotification(
            $user,
            'New board post: '.$post->title,
            $post->author->name.' posted in '.$board->name,
            'board',
            '/boards/'.$board->id
        ))->all();

        if ($jobs) {
            Bus::batch($jobs)->dispatch();
        }
    }

    private function boardAudience(CommunicationBoard $board)
    {
        $query = FacultyUser::where('is_active', true)->where('role', '!=', 'student');

        if ($board->visibility === 'department' && $board->department_id) {
            $code = $board->department?->code;
            if ($code) {
                $query->where('department', $code);
            }
        } elseif ($board->visibility === 'role' && $board->allowed_roles) {
            $query->whereIn('role', $board->allowed_roles);
        }

        return $query->get();
    }
}
