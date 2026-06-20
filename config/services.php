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

    'bot_api' => [
        'key' => env('BOT_API_KEY'),
    ],

    'metaapi' => [
        // Token de MetaApi (lo generas en https://app.metaapi.cloud).
        'token' => env('METAAPI_TOKEN'),
        // Region por defecto donde se despliegan las cuentas (new-york, london, singapore...).
        'region' => env('METAAPI_REGION', 'new-york'),
        // Base de la Provisioning API. Normalmente no hace falta cambiarla.
        'provisioning_url' => env('METAAPI_PROVISIONING_URL', 'https://mt-provisioning-api-v1.agiliumtrade.agiliumtrade.ai'),
        // Tipo de cuenta cloud y fiabilidad por defecto.
        'account_type' => env('METAAPI_ACCOUNT_TYPE', 'cloud-g2'),
        'reliability' => env('METAAPI_RELIABILITY', 'high'),
    ],

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

];
