<?php

namespace App\Contracts\Integration;

use App\Models\EventOutbox;

/**
 * Publishes domain events to the DEORIS Event Hub (async integration boundary).
 */
interface EventPublisher
{
    public function publish(EventOutbox $event): bool;
}
