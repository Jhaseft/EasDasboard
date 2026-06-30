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

    // Banco Económico — QR Simple. Método de pago para recargar la billetera:
    // el usuario recarga en USD y el QR cobra el equivalente en BOB (usd_to_bob).
    'baneco' => [
        'base'           => env('BANECO_API_BASE'),
        'aes_key'        => env('BANECO_AES_KEY'),
        'username'       => env('BANECO_USERNAME'),
        'password'       => env('BANECO_PASSWORD'),
        'account_credit' => env('BANECO_ACCOUNT_CREDIT'),
        'currency'       => env('BANECO_CURRENCY', 'BOB'),
        'usd_to_bob'     => (float) env('BANECO_USD_TO_BOB', 6.9),
        'qr_ttl_days'    => (int) env('BANECO_QR_TTL_DAYS', 1),
    ],

];
