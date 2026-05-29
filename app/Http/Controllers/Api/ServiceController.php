<?php

namespace App\Http\Controllers\Api;

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class ServiceController
{
    /**
     * SOA service manifest — used by portal/registry for independent deployment discovery.
     */
    public function manifest(): JsonResponse
    {
        return response()->json([
            'service_name' => config('careerconnect.service_name'),
            'display_name' => config('careerconnect.display_name'),
            'service_key' => config('careerconnect.service_key') ? '***configured***' : null,
            'service_url' => config('careerconnect.service_url'),
            'api_version' => config('careerconnect.api_version'),
            'trusted_portal_url' => config('careerconnect.trusted_portal_url'),
            'redis_channels' => config('careerconnect.redis.channels'),
            'queue_names' => config('careerconnect.queues'),
            'service' => config('careerconnect.service_name'),
            'version' => config('careerconnect.version'),
            'api' => [
                'base' => url('/api/v1'),
                'health' => url('/api/health'),
                'openapi' => url('/api/v1/service/openapi'),
            ],
            'architecture' => [
                'style' => 'microservice',
                'database' => 'isolated',
                'authentication' => 'deoris_portal_sso',
                'integration' => ['rest', 'event_hub'],
            ],
            'boundary' => config('careerconnect.boundary'),
            'integrations' => [
                'portal' => config('careerconnect.integrations.portal.base_url'),
                'event_hub' => config('careerconnect.integrations.event_hub.base_url'),
            ],
        ]);
    }

    public function health(): JsonResponse
    {
        $dbOk = false;

        try {
            DB::connection(config('careerconnect.database.connection'))->getPdo();
            $dbOk = true;
        } catch (\Throwable) {
            // leave false
        }

        $status = $dbOk ? 'healthy' : 'degraded';
        $code = $dbOk ? 200 : 503;

        return response()->json([
            'status' => $status,
            'service' => config('careerconnect.service_name'),
            'version' => config('careerconnect.version'),
            'checks' => [
                'database' => $dbOk ? 'up' : 'down',
            ],
            'timestamp' => now()->toIso8601String(),
        ], $code);
    }

    public function openapi(): JsonResponse
    {
        return response()->json([
            'openapi' => '3.0.3',
            'info' => [
                'title' => 'CareerConnect API',
                'version' => config('careerconnect.version'),
                'description' => 'Independent DEORIS microservice — faculty communication and career support.',
            ],
            'servers' => [['url' => url('/api/v1')]],
            'paths' => [
                '/auth/me' => ['get' => ['summary' => 'Current faculty profile (SSO)']],
                '/announcements' => ['get' => ['summary' => 'List announcements'], 'post' => ['summary' => 'Create announcement']],
                '/boards' => ['get' => ['summary' => 'List boards'], 'post' => ['summary' => 'Create board']],
                '/resources' => ['get' => ['summary' => 'List career resources']],
                '/messages/threads' => ['get' => ['summary' => 'List message threads'], 'post' => ['summary' => 'Create thread']],
                '/departments' => ['get' => ['summary' => 'List departments']],
                '/realtime/poll' => ['get' => ['summary' => 'Poll for live updates']],
                '/service/manifest' => ['get' => ['summary' => 'SOA service manifest']],
            ],
            'components' => [
                'securitySchemes' => [
                    'portalBearer' => [
                        'type' => 'http',
                        'scheme' => 'bearer',
                        'description' => 'DEORIS Portal SSO token',
                    ],
                ],
            ],
            'security' => [['portalBearer' => []]],
        ]);
    }
}
