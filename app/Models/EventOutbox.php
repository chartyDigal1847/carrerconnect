<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class EventOutbox extends Model
{
    protected $fillable = [
        'event_id', 'event_name', 'source_service', 'payload',
        'schema_version', 'correlation_id', 'is_published',
        'retry_count', 'error_message', 'published_at'
    ];

    protected $casts = [
        'payload' => 'array',
        'is_published' => 'boolean',
        'published_at' => 'datetime',
    ];

    public static function createEvent(
        string $eventName,
        array $payload,
        ?string $correlationId = null
    ): self {
        return static::create([
            'event_id' => Str::uuid()->toString(),
            'event_name' => $eventName,
            'source_service' => config('careerconnect.service_name', 'careerconnect-service'),
            'payload' => $payload,
            'schema_version' => '1.0',
            'correlation_id' => $correlationId ?? Str::uuid()->toString(),
        ]);
    }

    public function markAsPublished(): void
    {
        $this->update(['is_published' => true, 'published_at' => now()]);
    }

    public function incrementRetry(): void
    {
        $this->increment('retry_count');
    }

    public function setError(string $message): void
    {
        $this->update(['error_message' => $message]);
    }
}
