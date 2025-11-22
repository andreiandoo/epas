<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Analytics Dashboard Configuration
    |--------------------------------------------------------------------------
    */

    'cache' => [
        'enabled' => env('ANALYTICS_CACHE_ENABLED', true),
        'ttl' => env('ANALYTICS_CACHE_TTL', 300), // 5 minutes
        'realtime_ttl' => env('ANALYTICS_REALTIME_TTL', 60), // 1 minute
    ],

    'aggregation' => [
        'enabled' => env('ANALYTICS_AGGREGATION_ENABLED', true),
        'schedule' => env('ANALYTICS_AGGREGATION_SCHEDULE', '0 2 * * *'), // 2 AM daily
        'retention_days' => env('ANALYTICS_RETENTION_DAYS', 365),
    ],

    'widgets' => [
        'max_per_dashboard' => env('ANALYTICS_MAX_WIDGETS', 20),
        'default_refresh' => env('ANALYTICS_DEFAULT_REFRESH', '5m'),
        'types' => ['chart', 'metric', 'table', 'map'],
    ],

    'reports' => [
        'formats' => ['pdf', 'xlsx', 'csv'],
        'max_date_range_days' => env('ANALYTICS_MAX_DATE_RANGE', 365),
        'default_date_range_days' => env('ANALYTICS_DEFAULT_DATE_RANGE', 30),
    ],

    'tracking' => [
        'enabled' => env('ANALYTICS_TRACKING_ENABLED', true),
        'session_timeout_minutes' => env('ANALYTICS_SESSION_TIMEOUT', 30),
        'anonymize_ip' => env('ANALYTICS_ANONYMIZE_IP', false),
    ],

    'export' => [
        'chunk_size' => env('ANALYTICS_EXPORT_CHUNK', 1000),
        'max_rows' => env('ANALYTICS_EXPORT_MAX_ROWS', 50000),
    ],
];
