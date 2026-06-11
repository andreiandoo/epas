<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Mobile Wallet Configuration
    |--------------------------------------------------------------------------
    */

    'apple' => [
        'team_id' => env('APPLE_WALLET_TEAM_ID'),
        'pass_type_id' => env('APPLE_WALLET_PASS_TYPE_ID'),
        'certificate_path' => env('APPLE_WALLET_CERTIFICATE_PATH'),
        'certificate_password' => env('APPLE_WALLET_CERTIFICATE_PASSWORD'),
        'wwdr_certificate_path' => env('APPLE_WALLET_WWDR_PATH'),
    ],

    'google' => [
        'issuer_id' => env('GOOGLE_WALLET_ISSUER_ID'),
        'service_account_path' => env('GOOGLE_WALLET_SERVICE_ACCOUNT_PATH'),
        'class_suffix' => env('GOOGLE_WALLET_CLASS_SUFFIX', 'event_ticket'),
    ],

    'storage' => [
        'disk' => env('WALLET_STORAGE_DISK', 'local'),
        'path' => env('WALLET_STORAGE_PATH', 'wallet-passes'),
    ],

    'notifications' => [
        'send_on_generation' => env('WALLET_NOTIFY_ON_GENERATION', true),
    ],

    'cache' => [
        'ttl' => env('WALLET_CACHE_TTL', 3600),
    ],
];
