<?php

namespace App\Services\Security;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

class EventSecurityService
{
    public function buildSignedPayload(array $payload): array
    {
        $timestamp = now()->timestamp;
        $nonce = Str::uuid()->toString();
        $body = json_encode($payload, JSON_UNESCAPED_SLASHES);

        $signature = hash_hmac(
            'sha256',
            $timestamp.'|'.$nonce.'|'.$body,
            (string) config('careerconnect.event_secret')
        );

        return [
            'payload' => $payload,
            'timestamp' => $timestamp,
            'nonce' => $nonce,
            'signature' => $signature,
        ];
    }

    public function verifyInbound(string $signature, int $timestamp, string $nonce, string $payloadJson): bool
    {
        $maxSkew = config('careerconnect.integrations.event_hub.max_skew_seconds', 300);

        if (abs(now()->timestamp - $timestamp) > $maxSkew) {
            return false;
        }

        if (Cache::has($this->nonceKey($nonce))) {
            return false;
        }

        $expected = hash_hmac(
            'sha256',
            $timestamp.'|'.$nonce.'|'.$payloadJson,
            (string) config('careerconnect.event_secret')
        );

        if (! hash_equals($expected, $signature)) {
            return false;
        }

        Cache::put($this->nonceKey($nonce), true, $maxSkew);

        return true;
    }

    private function nonceKey(string $nonce): string
    {
        return config('careerconnect.redis.prefix').':event_nonce:'.$nonce;
    }
}
