<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Authentication Defaults
    |--------------------------------------------------------------------------
    */

    'defaults' => [
        'guard'     => env('AUTH_GUARD', 'faculty'),
        'passwords' => env('AUTH_PASSWORD_BROKER', 'faculty'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Authentication Guards
    |--------------------------------------------------------------------------
    | This module uses DEORIS Portal SSO only.
    | Faculty identity is provisioned from portal claims on every auth.
    */

    'guards' => [
        'faculty' => [
            'driver'   => 'session',
            'provider' => 'faculty',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | User Providers
    |--------------------------------------------------------------------------
    | Faculty provider uses FacultyUser model cached from DEORIS Portal.
    */

    'providers' => [
        'faculty' => [
            'driver' => 'eloquent',
            'model'  => App\Models\FacultyUser::class,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Resetting Passwords
    |--------------------------------------------------------------------------
    | Not used — authentication is via DEORIS Portal SSO only.
    */

    'passwords' => [
        'faculty' => [
            'provider' => 'faculty',
            'table'    => env('AUTH_PASSWORD_RESET_TOKEN_TABLE', 'password_reset_tokens'),
            'expire'   => 60,
            'throttle' => 60,
        ],
    ],

    'password_timeout' => env('AUTH_PASSWORD_TIMEOUT', 10800),

];
