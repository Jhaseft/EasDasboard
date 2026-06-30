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

    // Binance — recarga con USDT. El usuario deposita USDT a una de nuestras
    // direcciones y el backend lo detecta vía el historial de depósitos (API key
    // de SOLO LECTURA). 1 USDT = 1 USD, sin tipo de cambio.
    'binance' => [
        'key'             => env('BINANCE_API_KEY'),
        'secret'          => env('BINANCE_API_SECRET'),
        'base'            => env('BINANCE_API_BASE_URL', 'https://api.binance.com'),
        'coin'            => env('BINANCE_COIN', 'USDT'),
        'default_network' => env('BINANCE_DEFAULT_NETWORK', 'TRX'),

        // Redes activas: solo las que tienen dirección configurada (BINANCE_WALLET_<RED>).
        'networks' => array_values(array_filter([
            ['network' => 'TRX',      'label' => 'TRC20',     'wallet' => env('BINANCE_WALLET_TRX')],
            ['network' => 'BSC',      'label' => 'BEP20',     'wallet' => env('BINANCE_WALLET_BSC')],
            ['network' => 'MATIC',    'label' => 'Polygon',   'wallet' => env('BINANCE_WALLET_MATIC')],
            ['network' => 'ETH',      'label' => 'ERC20',     'wallet' => env('BINANCE_WALLET_ETH')],
            ['network' => 'ARBITRUM', 'label' => 'Arbitrum',  'wallet' => env('BINANCE_WALLET_ARBITRUM')],
            ['network' => 'OPTIMISM', 'label' => 'Optimism',  'wallet' => env('BINANCE_WALLET_OPTIMISM')],
            ['network' => 'AVAX',     'label' => 'Avalanche', 'wallet' => env('BINANCE_WALLET_AVAX')],
            ['network' => 'BASE',     'label' => 'Base',      'wallet' => env('BINANCE_WALLET_BASE')],
            ['network' => 'SOL',      'label' => 'Solana',    'wallet' => env('BINANCE_WALLET_SOL')],
        ], fn ($n) => ! empty($n['wallet']))),

        'underpay_tolerance'       => (float) env('BINANCE_UNDERPAY_TOLERANCE_PERCENT', 0.5),
        'overpay_tolerance'        => (float) env('BINANCE_OVERPAY_TOLERANCE_PERCENT', 5),
        'intent_ttl_minutes'       => (int) env('BINANCE_INTENT_TTL_MINUTES', 30),
        'history_lookback_minutes' => (int) env('BINANCE_HISTORY_LOOKBACK_MINUTES', 90),
        'manual_lookback_minutes'  => (int) env('BINANCE_MANUAL_CONFIRM_LOOKBACK_MINUTES', 1440),
    ],

];
