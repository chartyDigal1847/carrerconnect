<?php

namespace App\Services\Integration;

use App\Contracts\Integration\EventPublisher;
use App\Models\EventOutbox;
use App\Services\Security\EventSecurityService;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class EventHubPublisher implements EventPublisher
{
    public function __construct(private EventSecurityService $security) {}

    public function publish(EventOutbox $event): bool
    {
        $envelope = $this->security->buildSignedPayload([
            'event_id' => $event->event_id,
            'event_name' => $event->event_name,
            'source_service' => $event->source_service,
            'payload' => $event->payload,
            'schema_version' => $event->schema_version,
            'correlation_id' => $event->correlation_id,
            'published_at' => now()->toIso8601String(),
        ]);

        $url = rtrim(config('careerconnect.integrations.event_hub.base_url'), '/')
            .config('careerconnect.integrations.event_hub.events_path');

        $response = Http::timeout(config('careerconnect.integrations.event_hub.timeout', 30))
            ->withHeaders([
                'X-Event-Signature' => $envelope['signature'],
                'X-Event-Timestamp' => (string) $envelope['timestamp'],
                'X-Event-Nonce' => $envelope['nonce'],
                'X-Service-Key' => config('careerconnect.integrations.event_hub.service_key'),
                'X-DEORIS-Source-Service' => config('careerconnect.service_name'),
            ])
            ->acceptJson()
            ->post($url, $envelope);

        if ($response->successful()) {
            $event->markAsPublished();
            Log::info('careerconnect.event.published', [
                'event_id' => $event->event_id,
                'event_name' => $event->event_name,
            ]);

            return true;
        }

        $event->incrementRetry();
        $event->setError($response->body());

        return false;
    }
}
