<?php

namespace App\Observers;

use App\Models\Announcement;
use App\Events\AnnouncementPublished;
use App\Models\ActivityLog;

class AnnouncementObserver
{
    public function created(Announcement $announcement): void
    {
        if ($announcement->published_at) {
            AnnouncementPublished::dispatch($announcement);
        }
    }

    public function updated(Announcement $announcement): void
    {
        if ($announcement->wasChanged('published_at') && $announcement->published_at) {
            AnnouncementPublished::dispatch($announcement);
        }
    }
}
