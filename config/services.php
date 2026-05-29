<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'key' => env('POSTMARK_API_KEY'),
    ],

    'resend' => [
        'key' => env('RESEND_API_KEY'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | CareerConnect Service Configuration
    |--------------------------------------------------------------------------
    | SOA microservice configuration for CareerConnect module
    |--------------------------------------------------------------------------
    */

    'careerconnect' => [
        'service_name' => env('CAREERCONNECT_SERVICE_NAME', 'careerconnect-service'),
        'service_key' => env('CAREERCONNECT_SERVICE_KEY', 'careerconnect-secret-key'),
        'service_url' => env('CAREERCONNECT_URL', 'http://careerconnect.local'),
        'api_version' => 'v1',
    ],

    'event_hub_url' => env('EVENT_HUB_URL', 'http://event-hub.local/api/v1'),
    'event_secret' => env('EVENT_HUB_SECRET', 'event-hub-secret'),

];
