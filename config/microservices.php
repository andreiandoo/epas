<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Microservices Settings
    |--------------------------------------------------------------------------
    |
    | General configuration for the microservices infrastructure
    |
    */

    'enabled' => env('MICROSERVICES_ENABLED', true),

    /*
    |--------------------------------------------------------------------------
    | Queue Configuration
    |--------------------------------------------------------------------------
    */

    'queue' => [
        'default' => env('MICROSERVICES_QUEUE', 'microservices'),
        'retry_after' => env('MICROSERVICES_RETRY_AFTER', 90),
        'max_attempts' => env('MICROSERVICES_MAX_ATTEMPTS', 3),
    ],

    /*
    |--------------------------------------------------------------------------
    | WhatsApp Configuration
    |--------------------------------------------------------------------------
    */

    'whatsapp' => [
        'enabled' => env('WHATSAPP_ENABLED', true),
        'default_adapter' => env('WHATSAPP_DEFAULT_ADAPTER', 'mock'),
        'rate_limit' => env('WHATSAPP_RATE_LIMIT', 60), // messages per minute
        
        'twilio' => [
            'enabled' => env('WHATSAPP_TWILIO_ENABLED', true),
            'account_sid' => env('WHATSAPP_TWILIO_ACCOUNT_SID'),
            'auth_token' => env('WHATSAPP_TWILIO_AUTH_TOKEN'),
            'from_number' => env('WHATSAPP_TWILIO_FROM_NUMBER'),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | eFactura (ANAF) Configuration
    |--------------------------------------------------------------------------
    */

    'efactura' => [
        'enabled' => env('EFACTURA_ENABLED', true),
        'default_adapter' => env('EFACTURA_DEFAULT_ADAPTER', 'mock'),
        'environment' => env('EFACTURA_ENVIRONMENT', 'production'), // 'production' or 'test'
        'polling_interval' => env('EFACTURA_POLLING_INTERVAL', 10), // minutes
        'max_retries' => env('EFACTURA_MAX_RETRIES', 3),
        
        'anaf' => [
            'enabled' => env('EFACTURA_ANAF_ENABLED', true),
            'api_token' => env('EFACTURA_ANAF_API_TOKEN'),
            'certificate_path' => env('EFACTURA_ANAF_CERTIFICATE_PATH'),
            'private_key_path' => env('EFACTURA_ANAF_PRIVATE_KEY_PATH'),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Accounting Configuration
    |--------------------------------------------------------------------------
    */

    'accounting' => [
        'enabled' => env('ACCOUNTING_ENABLED', true),
        'default_adapter' => env('ACCOUNTING_DEFAULT_ADAPTER', 'mock'),
        'auto_issue' => env('ACCOUNTING_AUTO_ISSUE', false),
        
        'smartbill' => [
            'enabled' => env('ACCOUNTING_SMARTBILL_ENABLED', true),
            'username' => env('ACCOUNTING_SMARTBILL_USERNAME'),
            'token' => env('ACCOUNTING_SMARTBILL_TOKEN'),
            'company_vat' => env('ACCOUNTING_SMARTBILL_COMPANY_VAT'),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Webhooks Configuration
    |--------------------------------------------------------------------------
    */

    'webhooks' => [
        'enabled' => env('WEBHOOKS_ENABLED', true),
        'timeout' => env('WEBHOOK_TIMEOUT', 30),
        'retry_limit' => env('WEBHOOK_RETRY_LIMIT', 3),
        'verify_ssl' => env('WEBHOOK_VERIFY_SSL', true),
        'rate_limit' => env('WEBHOOK_RATE_LIMIT', 100), // per minute
    ],

    /*
    |--------------------------------------------------------------------------
    | Notifications Configuration
    |--------------------------------------------------------------------------
    */

    'notifications' => [
        'enabled' => env('NOTIFICATIONS_ENABLED', true),
        'default_channels' => ['database', 'email'],
        'email_enabled' => env('NOTIFICATIONS_EMAIL_ENABLED', true),
        'whatsapp_enabled' => env('NOTIFICATIONS_WHATSAPP_ENABLED', false),
    ],

    /*
    |--------------------------------------------------------------------------
    | Feature Flags Configuration
    |--------------------------------------------------------------------------
    */

    'feature_flags' => [
        'enabled' => env('FEATURE_FLAGS_ENABLED', true),
        'cache_ttl' => env('FEATURE_FLAGS_CACHE_TTL', 300), // seconds
    ],

    /*
    |--------------------------------------------------------------------------
    | Metrics & Analytics
    |--------------------------------------------------------------------------
    */

    'metrics' => [
        'enabled' => env('MICROSERVICES_METRICS_ENABLED', true),
        'track_usage' => env('MICROSERVICES_TRACK_USAGE', true),
        'track_costs' => env('MICROSERVICES_TRACK_COSTS', true),
        'retention_days' => env('MICROSERVICES_METRICS_RETENTION_DAYS', 90),
    ],

    /*
    |--------------------------------------------------------------------------
    | Health Check Configuration
    |--------------------------------------------------------------------------
    */

    'health' => [
        'enabled' => env('HEALTH_CHECK_ENABLED', true),
        'cache_ttl' => env('HEALTH_CHECK_CACHE_TTL', 60), // seconds
        'timeout' => env('HEALTH_CHECK_TIMEOUT', 5), // seconds
    ],

    /*
    |--------------------------------------------------------------------------
    | Alerts Configuration
    |--------------------------------------------------------------------------
    */

    'alerts' => [
        'enabled' => env('ALERTS_ENABLED', true),

        // Email alerts
        'email' => [
            'enabled' => env('ALERTS_EMAIL_ENABLED', true),
        ],

        // Slack alerts
        'slack' => [
            'enabled' => env('ALERTS_SLACK_ENABLED', false),
            'webhook_url' => env('ALERTS_SLACK_WEBHOOK_URL'),
        ],

        // Alert recipients by type
        'recipients' => [
            'default' => env('ALERTS_DEFAULT_RECIPIENTS', 'admin@example.com'),
            'health' => env('ALERTS_HEALTH_RECIPIENTS'),
            'microservice_expiring' => env('ALERTS_MICROSERVICE_EXPIRING_RECIPIENTS'),
            'microservice_suspended' => env('ALERTS_MICROSERVICE_SUSPENDED_RECIPIENTS'),
            'webhook_failure' => env('ALERTS_WEBHOOK_FAILURE_RECIPIENTS'),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | API Configuration
    |--------------------------------------------------------------------------
    */

    'api' => [
        'enabled' => env('MICROSERVICES_API_ENABLED', true),
        'track_detailed_usage' => env('MICROSERVICES_API_TRACK_USAGE', false),
        'usage_retention_days' => env('MICROSERVICES_API_USAGE_RETENTION_DAYS', 90),
        'default_rate_limit' => env('MICROSERVICES_API_DEFAULT_RATE_LIMIT', 1000), // per hour
    ],

    /*
    |--------------------------------------------------------------------------
    | Cache Configuration
    |--------------------------------------------------------------------------
    */

    'cache' => [
        'enabled' => env('MICROSERVICES_CACHE_ENABLED', true),
        'catalog_ttl' => env('MICROSERVICES_CACHE_CATALOG_TTL', 3600), // 1 hour
        'subscription_ttl' => env('MICROSERVICES_CACHE_SUBSCRIPTION_TTL', 300), // 5 minutes
        'config_ttl' => env('MICROSERVICES_CACHE_CONFIG_TTL', 600), // 10 minutes
        'webhook_ttl' => env('MICROSERVICES_CACHE_WEBHOOK_TTL', 300), // 5 minutes
        'warm_on_boot' => env('MICROSERVICES_CACHE_WARM_ON_BOOT', false),
    ],

    /*
    |--------------------------------------------------------------------------
    | Audit Configuration
    |--------------------------------------------------------------------------
    */

    'audit' => [
        'enabled' => env('MICROSERVICES_AUDIT_ENABLED', true),
        'retention_days' => env('MICROSERVICES_AUDIT_RETENTION_DAYS', 365), // 1 year
    ],

];
