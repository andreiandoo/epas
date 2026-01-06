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

    /*
    |--------------------------------------------------------------------------
    | Admin Configuration
    |--------------------------------------------------------------------------
    */

    'admin' => [
        // Admin user IDs (for direct ID-based authentication)
        'user_ids' => array_filter(explode(',', env('MICROSERVICES_ADMIN_USER_IDS', ''))),

        // Allowed email domains for admin access (for development)
        'allowed_domains' => array_filter(explode(',', env('MICROSERVICES_ADMIN_DOMAINS', ''))),
    ],

    /*
    |--------------------------------------------------------------------------
    | Webhook Security Configuration
    |--------------------------------------------------------------------------
    */

    'webhooks_security' => [
        // Signature verification
        'verify_signatures' => env('WEBHOOKS_VERIFY_SIGNATURES', true),

        // Custom webhook signature secret (for non-provider webhooks)
        'signature_secret' => env('WEBHOOKS_SIGNATURE_SECRET'),

        // Twilio webhook verification (uses auth_token from whatsapp config)
        'twilio_verify' => env('WEBHOOKS_TWILIO_VERIFY', true),

        // Stripe webhook secret
        'stripe_webhook_secret' => env('STRIPE_WEBHOOK_SECRET'),

        // GitHub webhook secret
        'github_webhook_secret' => env('GITHUB_WEBHOOK_SECRET'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Promo Codes Configuration
    |--------------------------------------------------------------------------
    */

    'promo_codes' => [
        'enabled' => env('PROMO_CODES_ENABLED', true),

        // Default code length when auto-generating
        'default_code_length' => env('PROMO_CODES_DEFAULT_LENGTH', 8),

        // Maximum discount percentage allowed
        'max_percentage' => env('PROMO_CODES_MAX_PERCENTAGE', 100),

        // Default validity period (days)
        'default_validity_days' => env('PROMO_CODES_DEFAULT_VALIDITY_DAYS', 30),

        // Allow stacking multiple promo codes
        'allow_stacking' => env('PROMO_CODES_ALLOW_STACKING', false),

        // Cleanup old expired codes after X days
        'cleanup_expired_after_days' => env('PROMO_CODES_CLEANUP_DAYS', 365),
    ],

    /*
    |--------------------------------------------------------------------------
    | Payment Processors - Revolut Configuration
    |--------------------------------------------------------------------------
    */

    'revolut' => [
        'enabled' => env('REVOLUT_ENABLED', true),
        'sandbox' => env('REVOLUT_SANDBOX', true),
        'api_version' => env('REVOLUT_API_VERSION', '1.0'),
        'rate_limit' => env('REVOLUT_RATE_LIMIT', 100), // requests per minute
        'webhook_tolerance' => env('REVOLUT_WEBHOOK_TOLERANCE', 300), // seconds
    ],

    /*
    |--------------------------------------------------------------------------
    | Payment Processors - PayPal Configuration
    |--------------------------------------------------------------------------
    */

    'paypal' => [
        'enabled' => env('PAYPAL_ENABLED', true),
        'sandbox' => env('PAYPAL_SANDBOX', true),
        'api_version' => env('PAYPAL_API_VERSION', 'v2'),
        'rate_limit' => env('PAYPAL_RATE_LIMIT', 100), // requests per minute
        'token_cache_ttl' => env('PAYPAL_TOKEN_CACHE_TTL', 3500), // seconds (tokens expire in ~1hr)
        'capture_on_checkout' => env('PAYPAL_CAPTURE_ON_CHECKOUT', true),
        'pay_later_enabled' => env('PAYPAL_PAY_LATER_ENABLED', true),
    ],

    /*
    |--------------------------------------------------------------------------
    | Payment Processors - Klarna Configuration
    |--------------------------------------------------------------------------
    */

    'klarna' => [
        'enabled' => env('KLARNA_ENABLED', true),
        'sandbox' => env('KLARNA_SANDBOX', true),
        'default_region' => env('KLARNA_DEFAULT_REGION', 'eu'), // eu, na, oc
        'rate_limit' => env('KLARNA_RATE_LIMIT', 100), // requests per minute
        'auto_capture' => env('KLARNA_AUTO_CAPTURE', true),
        'payment_methods' => [
            'pay_later' => env('KLARNA_PAY_LATER_ENABLED', true),
            'pay_now' => env('KLARNA_PAY_NOW_ENABLED', true),
            'slice_it' => env('KLARNA_SLICE_IT_ENABLED', true),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | SMS Payment Microservice Configuration
    |--------------------------------------------------------------------------
    */

    'sms_payment' => [
        'enabled' => env('SMS_PAYMENT_ENABLED', true),

        // Twilio configuration for SMS delivery
        'twilio' => [
            'enabled' => env('SMS_TWILIO_ENABLED', true),
            'account_sid' => env('SMS_TWILIO_ACCOUNT_SID'),
            'auth_token' => env('SMS_TWILIO_AUTH_TOKEN'),
            'from_number' => env('SMS_TWILIO_FROM_NUMBER'),
        ],

        // SMS settings
        'rate_limit' => env('SMS_PAYMENT_RATE_LIMIT', 30), // SMS per minute
        'retry_attempts' => env('SMS_PAYMENT_RETRY_ATTEMPTS', 3),
        'retry_delay' => env('SMS_PAYMENT_RETRY_DELAY', 60), // seconds

        // Default fallback processor for actual payment processing
        'default_fallback_processor' => env('SMS_PAYMENT_DEFAULT_PROCESSOR', 'stripe'),

        // Message templates
        'templates' => [
            'payment_request' => env('SMS_TEMPLATE_PAYMENT_REQUEST',
                'Payment request: {amount} for {description}. Pay securely here: {link}'),
            'payment_reminder' => env('SMS_TEMPLATE_PAYMENT_REMINDER',
                'Reminder: Your payment of {amount} (Order: {order_id}) is pending. Complete payment: {link}'),
            'payment_confirmation' => env('SMS_TEMPLATE_PAYMENT_CONFIRMATION',
                'Payment confirmed! {amount} received for Order {order_id}. Thank you!'),
        ],

        // Queue configuration
        'queue' => [
            'connection' => env('SMS_PAYMENT_QUEUE_CONNECTION', 'database'),
            'queue' => env('SMS_PAYMENT_QUEUE', 'sms-payments'),
        ],
    ],

];
