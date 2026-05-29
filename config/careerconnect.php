<?php

/**
 * CareerConnect — SOA service identity (DEORIS ecosystem).
 *
 * Database: isolated `careerconnect` schema only.
 * Integration: Portal SSO + Event Hub HTTP only.
 */
return [

    'service_name' => env('CAREERCONNECT_SERVICE_NAME', 'careerconnect-service'),
    'display_name' => env('CAREERCONNECT_DISPLAY_NAME', 'CareerConnect'),
    'version' => env('CAREERCONNECT_SERVICE_VERSION', '1.0.0'),
    'service_key' => env('CAREERCONNECT_SERVICE_KEY'),
    'service_url' => env('CAREERCONNECT_URL', env('APP_URL', 'http://careerconnect.local')),
    'api_version' => env('CAREERCONNECT_API_VERSION', 'v1'),
    'trusted_portal_url' => env('APP_PORTAL_URL', 'https://deoris.test'),
    'event_secret' => env('EVENT_HUB_SECRET'),

    'redis' => [
        'prefix' => env('REDIS_PREFIX', 'careerconnect'),
        'channels' => [
            'events' => 'careerconnect.events',
            'notifications' => 'careerconnect.notifications',
            'broadcast' => 'careerconnect.broadcast',
            'cache' => 'careerconnect.cache',
            'queues' => 'careerconnect.queues',
        ],
    ],

    'queues' => [
        'communications' => 'careerconnect.communications',
        'notifications' => 'careerconnect.notifications',
        'events' => 'careerconnect.events',
        'moderation' => 'careerconnect.moderation',
        'default' => 'careerconnect.default',
    ],

    'database' => [
        'connection' => env('DB_CONNECTION', 'mysql'),
        'name' => env('DB_DATABASE', 'careerconnect'),
    ],

    'integrations' => [
        'portal' => [
            'base_url' => env('APP_PORTAL_URL', 'https://deoris.test'),
            'exchange_path' => env('PORTAL_SSO_EXCHANGE_PATH', '/api/v1/sso/exchange'),
            'verify_ssl' => filter_var(
                env('PORTAL_HTTP_VERIFY_SSL', env('APP_ENV', 'production') !== 'local'),
                FILTER_VALIDATE_BOOL
            ),
            'timeout' => (int) env('PORTAL_HTTP_TIMEOUT', 8),
        ],
        'event_hub' => [
            'base_url' => env('EVENT_HUB_URL', 'http://event-hub.local/api/v1'),
            'events_path' => '/events',
            'secret' => env('EVENT_HUB_SECRET'),
            'service_key' => env('CAREERCONNECT_SERVICE_KEY'),
            'timeout' => (int) env('EVENT_HUB_HTTP_TIMEOUT', 30),
            'max_skew_seconds' => (int) env('EVENT_HUB_MAX_SKEW', 300),
        ],
    ],

    'boundary' => [
        'allow_shared_database' => false,
        'allowed_inbound_auth' => ['portal_sso'],
        'allowed_outbound' => ['portal_http', 'event_hub_http'],
        'forbidden_database_names' => ['deoris', 'deoris_portal', 'portal', 'shared', 'master'],
    ],

    'roles' => [
        'allowed' => ['instructor', 'cashier', 'librarian', 'admission_officer', 'admin'],
        'blocked' => ['student'],
        'capabilities' => [
            'instructor' => [
                'announcements.create', 'announcements.update', 'boards.create', 'boards.post',
                'boards.comment', 'resources.create', 'resources.access', 'messages.send', 'discussions.participate',
            ],
            'cashier' => [
                'boards.access', 'boards.comment', 'announcements.read', 'discussions.participate',
            ],
            'librarian' => [
                'resources.create', 'resources.access', 'boards.access', 'boards.comment',
                'announcements.read', 'discussions.participate',
            ],
            'admission_officer' => [
                'announcements.create', 'announcements.update', 'boards.create', 'boards.post',
                'boards.comment', 'dashboard.coordination', 'announcements.read',
            ],
            'admin' => [
                'announcements.*', 'boards.*', 'resources.*', 'messages.*', 'moderation.*',
                'analytics.*', 'activity.*',
            ],
        ],
    ],

    'rate_limit' => [
        'api_per_minute' => (int) env('CAREERCONNECT_API_RATE_LIMIT', 120),
    ],

];
