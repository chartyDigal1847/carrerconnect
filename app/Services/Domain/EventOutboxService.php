<?php

namespace App\Services\Domain;

use App\Jobs\PublishEventToHub;
use App\Models\EventOutbox;

class EventOutboxService
{
    public function record(string $eventName, array $payload, ?string $correlationId = null): EventOutbox
    {
        $event = EventOutbox::createEvent($eventName, $payload, $correlationId);

        PublishEventToHub::dispatch($event)
            ->onQueue(config('careerconnect.queues.events'));

        return $event;
    }
}
