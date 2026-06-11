<?php
/**
 * AmBilet.ro API Configuration
 *
 * IMPORTANT: This file should be placed OUTSIDE the public webroot
 * or protected via .htaccess to prevent direct access.
 *
 * Recommended structure:
 *   /var/www/ambilet/
 *   ├── config/
 *   │   └── config.php  ← This file (outside webroot)
 *   └── public/         ← Webroot
 *       ├── index.html
 *       ├── api.php     ← Proxy script
 *       └── ...
 */

return [
    // Your Core platform URL
    'core_api_url' => 'https://your-core-domain.com/api/marketplace-client',

    // API credentials (get these from Core admin panel)
    'api_key' => 'YOUR_API_KEY_HERE',
    'api_secret' => 'YOUR_API_SECRET_HERE',

    // Allowed origins for CORS (your AmBilet.ro domains)
    'allowed_origins' => [
        'https://ambilet.ro',
        'https://www.ambilet.ro',
        'http://localhost:3000', // For local development
    ],

    // Rate limiting (requests per minute per IP)
    'rate_limit' => 60,

    // Cache settings (in seconds)
    'cache_ttl' => [
        'events' => 300,      // 5 minutes
        'event_detail' => 60, // 1 minute
        'config' => 3600,     // 1 hour
    ],
];
