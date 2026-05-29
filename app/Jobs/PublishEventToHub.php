<?php

namespace App\Jobs;

use App\Contracts\Integration\EventPublisher;
use App\Models\EventOutbox;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class PublishEventToHub implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $tries = 5;

    public $timeout = 30;

    public function __construct(public EventOutbox $event)
    {
        $this->onQueue(config('careerconnect.queues.events'));
    }

    public function handle(EventPublisher $publisher): void
    {
        if ($publisher->publish($this->event)) {
            return;
        }

        Log::warning('careerconnect.event.publish_retry', [
            'event_id' => $this->event->event_id,
            'retry_count' => $this->event->retry_count,
        ]);

        $this->release(60);
    }
}
