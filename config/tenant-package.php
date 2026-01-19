<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Package Generation Settings
    |--------------------------------------------------------------------------
    */

    'build_path' => env('TENANT_PACKAGE_BUILD_PATH', resource_path('tenant-client')),

    'output_path' => env('TENANT_PACKAGE_OUTPUT_PATH', storage_path('app/packages')),

    /*
    |--------------------------------------------------------------------------
    | Obfuscation Settings
    |--------------------------------------------------------------------------
    */

    'obfuscation' => [
        'enabled' => env('TENANT_PACKAGE_OBFUSCATION', true),

        // JavaScript Obfuscator options
        'options' => [
            'compact' => true,
            'controlFlowFlattening' => true,
            'controlFlowFlatteningThreshold' => 0.75,
            'deadCodeInjection' => true,
            'deadCodeInjectionThreshold' => 0.4,
            'debugProtection' => false,
            'disableConsoleOutput' => true,
            'identifierNamesGenerator' => 'hexadecimal',
            'selfDefending' => true,
            'stringArray' => true,
            'stringArrayEncoding' => ['base64'],
            'stringArrayThreshold' => 0.75,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Package Versioning
    |--------------------------------------------------------------------------
    */

    'versioning' => [
        'auto_increment' => true,
        'format' => 'semver', // semver or date
    ],

    /*
    |--------------------------------------------------------------------------
    | Security Settings
    |--------------------------------------------------------------------------
    */

    'security' => [
        // Enable domain locking in generated packages
        'domain_lock' => true,

        // Enable integrity verification
        'integrity_check' => true,

        // API request signing
        'request_signing' => true,

        // Token expiry in minutes
        'api_token_expiry' => 60,
    ],

    /*
    |--------------------------------------------------------------------------
    | Default Modules
    |--------------------------------------------------------------------------
    |
    | Modules that are always included in every package
    |
    */

    'default_modules' => [
        'core',
        'events',
        'auth',
        'cart',
        'checkout',
    ],

    /*
    |--------------------------------------------------------------------------
    | Optional Modules
    |--------------------------------------------------------------------------
    |
    | Modules that can be enabled based on tenant's microservices
    |
    */

    'optional_modules' => [
        'seating' => 'seating',
        'affiliates' => 'affiliates',
        'insurance' => 'insurance',
        'whatsapp' => 'whatsapp',
        'promo-codes' => 'promo_codes',
        'invitations' => 'invitations',
        'tracking' => 'tracking',
    ],

    /*
    |--------------------------------------------------------------------------
    | CDN Settings
    |--------------------------------------------------------------------------
    */

    'cdn' => [
        'enabled' => env('TENANT_PACKAGE_CDN_ENABLED', false),
        'url' => env('TENANT_PACKAGE_CDN_URL'),
    ],
];
