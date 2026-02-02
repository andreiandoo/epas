<?php

/**
 * SECURITY FIX: CORS Configuration
 *
 * CRITICAL: Previously had allowed_origins='*' with supports_credentials=true
 * This combination is INSECURE and allows CSRF attacks from any domain.
 *
 * Now uses environment variables for allowed origins.
 * Set CORS_ALLOWED_ORIGINS in .env as comma-separated list:
 * CORS_ALLOWED_ORIGINS=https://yourdomain.com,https://app.yourdomain.com
 */

$allowedOrigins = env('CORS_ALLOWED_ORIGINS', '');
$originsArray = $allowedOrigins ? array_map('trim', explode(',', $allowedOrigins)) : [];

// In development, allow localhost
if (app()->environment('local', 'development', 'testing')) {
    $originsArray = array_merge($originsArray, [
        'http://localhost:3000',
        'http://localhost:5173',
        'http://127.0.0.1:3000',
        'http://127.0.0.1:5173',
    ]);
}

return [
    'paths' => ['api/*', 'sanctum/csrf-cookie'],

    'allowed_methods' => ['GET', 'POST', 'PUT', 'PATCH', 'DELETE', 'OPTIONS'],

    // SECURITY: Use specific origins instead of wildcard
    'allowed_origins' => $originsArray,

    // Allow patterns for tenant subdomains
    'allowed_origins_patterns' => [
        // Example: Allow all subdomains of your main domain
        // '#^https://.*\.yourdomain\.com$#',
    ],

    'allowed_headers' => [
        'Accept',
        'Authorization',
        'Content-Type',
        'X-Requested-With',
        'X-API-Key',
        'X-Tenant-Domain',
        'X-Request-ID',
    ],

    'exposed_headers' => [
        'X-RateLimit-Limit',
        'X-RateLimit-Remaining',
        'X-RateLimit-Reset',
        'X-Request-ID',
    ],

    'max_age' => 86400,

    // SECURITY: Only enable credentials if specific origins are set
    'supports_credentials' => !empty($originsArray),
];
