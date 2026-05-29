<?php

namespace App\Listeners;

use App\Models\Announcement;
use App\Models\FacultyUser;
use App\Jobs\SendNotification;
use App\Services\Domain\EventOutboxService;
use Illuminate\Support\Facades\Bus;

class BroadcastAnnouncementNotification
{
    public function __construct(private EventOutboxService $outbox) {}

    public function handle($event): void
    {
        if (!($event instanceof \App\Events\AnnouncementPublished)) {
            return;
        }

        $announcement = $event->announcement;

        $this->outbox->record('AnnouncementPublished', [
            'announcement_id' => $announcement->id,
            'title' => $announcement->title,
            'content' => $announcement->content,
            'author_id' => $announcement->author_id,
            'priority' => $announcement->priority,
            'visibility' => $announcement->visibility,
        ]);

        // Send notifications to relevant users
        $users = $this->getTargetUsers($announcement);

        $jobs = $users->map(fn($user) => new SendNotification(
            $user,
            "New Announcement: {$announcement->title}",
            $announcement->content,
            'announcement',
            "/announcements/{$announcement->id}"
        ))->toArray();

        Bus::batch($jobs)->dispatch();
    }

    private function getTargetUsers(Announcement $announcement): \Illuminate\Database\Eloquent\Collection
    {
        $query = FacultyUser::where('is_active', true);

        if ($announcement->visibility === 'department' && $announcement->department_id) {
            $code = $announcement->department?->code;
            if ($code) {
                $query->where('department', $code);
            }
        } elseif ($announcement->visibility === 'role' && $announcement->target_roles) {
            $query->whereIn('role', $announcement->target_roles);
        }

        // Exclude student role
        return $query->where('role', '!=' , 'student')->get();
    }
}
