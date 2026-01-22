<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Seating Module Enabled
    |--------------------------------------------------------------------------
    |
    | Master toggle for the seating maps module. When disabled, all seating
    | functionality is hidden from the UI and APIs return 404.
    | Per-tenant overrides are read from the tenants.features JSON column.
    |
    */

    'enabled' => env('SEATING_ENABLED', true),

    /*
    |--------------------------------------------------------------------------
    | Seat Hold TTL (Time To Live)
    |--------------------------------------------------------------------------
    |
    | Number of seconds a seat hold remains valid. After this time, if the
    | user hasn't completed checkout, seats are automatically released back
    | to the available pool.
    |
    | Default: 900 seconds (15 minutes) - matches cart checkout timer
    |
    */

    'hold_ttl_seconds' => env('SEATING_HOLD_TTL_SECONDS', 900),

    /*
    |--------------------------------------------------------------------------
    | Maximum Held Seats Per Session
    |--------------------------------------------------------------------------
    |
    | Maximum number of seats a single session can hold simultaneously.
    | This prevents abuse and ensures fair distribution.
    |
    */

    'max_held_seats_per_session' => env('SEATING_MAX_HELD_PER_SESSION', 10),

    /*
    |--------------------------------------------------------------------------
    | Rate Limits
    |--------------------------------------------------------------------------
    |
    | Configure rate limiting for seating operations to prevent abuse and
    | ensure system stability under high load.
    |
    */

    'rate_limits' => [
        'hold_per_minute' => env('SEATING_RATE_LIMIT_HOLD', 30),
        'confirm_per_minute' => env('SEATING_RATE_LIMIT_CONFIRM', 10),
        'release_per_minute' => env('SEATING_RATE_LIMIT_RELEASE', 30),
        'query_per_minute' => env('SEATING_RATE_LIMIT_QUERY', 60),
    ],

    /*
    |--------------------------------------------------------------------------
    | Use Redis for Holds
    |--------------------------------------------------------------------------
    |
    | When true, seat holds use Redis with native TTL expiration for
    | performance and reliability. When false, falls back to database-only
    | implementation with scheduled job cleanup.
    |
    */

    'use_redis_holds' => env('SEATING_USE_REDIS', true),

    /*
    |--------------------------------------------------------------------------
    | Redis Configuration
    |--------------------------------------------------------------------------
    |
    | Redis connection and key prefix for seat hold operations.
    |
    */

    'redis' => [
        'connection' => env('SEATING_REDIS_CONNECTION', 'default'),
        'key_prefix' => env('SEATING_REDIS_PREFIX', 'seating'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Storage Disk
    |--------------------------------------------------------------------------
    |
    | Disk used for seating map background images and exported files.
    | Must be configured in config/filesystems.php.
    |
    */

    'storage_disk' => env('SEATING_STORAGE_DISK', 'public'),

    /*
    |--------------------------------------------------------------------------
    | Background Images
    |--------------------------------------------------------------------------
    |
    | Configuration for venue seating map background images.
    |
    */

    'background_images' => [
        'max_size_mb' => env('SEATING_BG_MAX_SIZE_MB', 5),
        'allowed_mimes' => ['image/jpeg', 'image/png', 'image/svg+xml'],
        'directory' => 'seating/backgrounds',
    ],

    /*
    |--------------------------------------------------------------------------
    | Canvas Constraints
    |--------------------------------------------------------------------------
    |
    | Default and maximum canvas dimensions for seating designer.
    |
    */

    'canvas' => [
        'default_width' => 1920,
        'default_height' => 1080,
        'max_width' => 4096,
        'max_height' => 4096,
        'min_width' => 800,
        'min_height' => 600,
    ],

    /*
    |--------------------------------------------------------------------------
    | Geometry Validation
    |--------------------------------------------------------------------------
    |
    | Constraints for seating geometry elements.
    |
    */

    'validation' => [
        'max_sections_per_layout' => 50,
        'max_rows_per_section' => 100,
        'max_seats_per_row' => 200,
        'max_total_seats_per_layout' => 10000,
        'seat_uid_pattern' => '/^[A-Z0-9_-]{1,32}$/i',
    ],

    /*
    |--------------------------------------------------------------------------
    | Dynamic Pricing Configuration
    |--------------------------------------------------------------------------
    |
    | Settings for the dynamic pricing engine. This module is separate from
    | the seating maps but integrates closely for per-seat pricing.
    |
    */

    'dynamic_pricing' => [
        // Master toggle for dynamic pricing functionality
        'enabled' => env('SEATING_DP_ENABLED', false),

        // Minimum time between price changes for the same seat (prevents thrashing)
        'cooldown_seconds' => env('SEATING_DP_COOLDOWN_SECONDS', 120),

        // Enforce floor and ceiling price limits from rules
        'floor_ceiling_enforced' => env('SEATING_DP_ENFORCE_LIMITS', true),

        // Maximum percentage change allowed in a single pricing update
        'max_change_percent' => env('SEATING_DP_MAX_CHANGE_PERCENT', 50),

        // Recompute frequency for time-based and velocity strategies
        'recompute_interval_minutes' => env('SEATING_DP_RECOMPUTE_INTERVAL', 5),

        // Available pricing strategies
        'strategies' => [
            'time_based' => \App\Services\Seating\Pricing\Strategies\TimeBasedStrategy::class,
            'velocity' => \App\Services\Seating\Pricing\Strategies\VelocitySales::class,
            'threshold' => \App\Services\Seating\Pricing\Strategies\OccupancyThreshold::class,
            'custom' => \App\Services\Seating\Pricing\Strategies\CustomStrategy::class,
        ],

        // Cache duration for computed prices (seconds)
        'cache_ttl' => env('SEATING_DP_CACHE_TTL', 60),
    ],

    /*
    |--------------------------------------------------------------------------
    | Session Management
    |--------------------------------------------------------------------------
    |
    | Configuration for session-based seat holds and tracking.
    |
    */

    'session' => [
        // Cookie name for session UID
        'cookie_name' => env('SEATING_SESSION_COOKIE', 'epas_seating_session'),

        // Cookie lifetime (same as hold TTL + buffer)
        'cookie_lifetime_minutes' => env('SEATING_SESSION_LIFETIME', 20),

        // Header name for session UID (alternative to cookie)
        'header_name' => 'X-Session-Id',

        // Session ID length
        'session_id_length' => 32,
    ],

    /*
    |--------------------------------------------------------------------------
    | Polling & Real-time Updates
    |--------------------------------------------------------------------------
    |
    | Configuration for seat availability polling and real-time updates.
    |
    */

    'polling' => [
        // Minimum interval between polling requests (seconds)
        'min_interval_seconds' => env('SEATING_POLL_MIN_INTERVAL', 2),

        // Enable server-sent events for real-time updates
        'sse_enabled' => env('SEATING_SSE_ENABLED', false),

        // Broadcast driver for seat status changes
        'broadcast_driver' => env('SEATING_BROADCAST_DRIVER', 'log'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Concurrency Control
    |--------------------------------------------------------------------------
    |
    | Settings for optimistic locking and version control.
    |
    */

    'concurrency' => [
        // Maximum retry attempts for version conflicts
        'max_retries' => 3,

        // Delay between retries (milliseconds)
        'retry_delay_ms' => 100,
    ],

    /*
    |--------------------------------------------------------------------------
    | Cleanup & Maintenance
    |--------------------------------------------------------------------------
    |
    | Settings for background maintenance tasks.
    |
    */

    'cleanup' => [
        // How often to run the expired holds cleanup job (when not using Redis)
        'expired_holds_frequency' => '* * * * *', // Every minute

        // Delete hold records older than this (days)
        'purge_holds_after_days' => env('SEATING_PURGE_HOLDS_DAYS', 30),

        // Archive old event seating layouts after this many days
        'archive_events_after_days' => env('SEATING_ARCHIVE_DAYS', 365),
    ],

    /*
    |--------------------------------------------------------------------------
    | CORS Configuration
    |--------------------------------------------------------------------------
    |
    | CORS settings for public seating APIs. Only the requesting tenant's
    | domains should be allowed.
    |
    */

    'cors' => [
        'allowed_origins' => env('SEATING_CORS_ORIGINS', '*'),
        'allowed_methods' => ['GET', 'POST', 'DELETE', 'OPTIONS'],
        'allowed_headers' => ['Content-Type', 'X-Session-Id', 'X-Requested-With'],
        'exposed_headers' => ['X-Expires-At', 'X-Hold-Count'],
        'max_age' => 3600,
    ],

    /*
    |--------------------------------------------------------------------------
    | Logging & Telemetry
    |--------------------------------------------------------------------------
    |
    | Configuration for seating module logging and metrics.
    |
    */

    'logging' => [
        // Log channel for seating operations
        'channel' => env('SEATING_LOG_CHANNEL', 'stack'),

        // Log level for seating operations
        'level' => env('SEATING_LOG_LEVEL', 'info'),

        // Enable detailed query logging (debug only)
        'log_queries' => env('SEATING_LOG_QUERIES', false),
    ],

    /*
    |--------------------------------------------------------------------------
    | Events & Webhooks
    |--------------------------------------------------------------------------
    |
    | Domain events emitted by the seating module for integration with
    | external systems and internal analytics.
    |
    */

    'events' => [
        'enabled' => env('SEATING_EVENTS_ENABLED', true),

        // List of events to emit
        'emit' => [
            'layout.published',
            'layout.cloned',
            'event.snapshot.created',
            'event.snapshot.regenerated',
            'seats.held',
            'seats.released',
            'seats.sold',
            'seats.blocked',
            'dp.rule.created',
            'dp.rule.updated',
            'dp.rule.deleted',
            'dp.override.applied',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Frontend Widget
    |--------------------------------------------------------------------------
    |
    | Configuration for the public-facing seating widget.
    |
    */

    'widget' => [
        // Widget script version (for cache busting)
        'version' => env('SEATING_WIDGET_VERSION', '1.0.0'),

        // CDN URL for widget assets (optional)
        'cdn_url' => env('SEATING_WIDGET_CDN', null),

        // Default theme
        'default_theme' => 'light',

        // Available seat shapes
        'seat_shapes' => ['circle', 'rect', 'stadium'],
    ],

    /*
    |--------------------------------------------------------------------------
    | Export Configuration
    |--------------------------------------------------------------------------
    |
    | Settings for exporting seating data (CSV, JSON).
    |
    */

    'export' => [
        // Directory for temporary export files
        'temp_directory' => 'seating/exports',

        // Export file retention (hours)
        'retention_hours' => 24,

        // Maximum rows per export
        'max_rows' => 50000,
    ],

];
