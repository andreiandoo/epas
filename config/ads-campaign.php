<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Ads Campaign Module Configuration
    |--------------------------------------------------------------------------
    |
    | Configuration for the Tixello Ads Campaign Manager.
    | API credentials are stored in the Settings model (encrypted).
    |
    */

    'enabled' => env('ADS_CAMPAIGN_ENABLED', true),

    /*
    |--------------------------------------------------------------------------
    | Platform Settings
    |--------------------------------------------------------------------------
    */

    'platforms' => [
        'facebook' => [
            'enabled' => env('ADS_FACEBOOK_ENABLED', true),
            'api_version' => env('ADS_FACEBOOK_API_VERSION', 'v21.0'),
            // Credentials stored in Settings model:
            // facebook_app_id, facebook_app_secret, facebook_access_token
            // meta.facebook_ad_account_id, meta.facebook_page_id, meta.facebook_pixel_id
        ],

        'instagram' => [
            'enabled' => env('ADS_INSTAGRAM_ENABLED', true),
            // Uses same credentials as Facebook (Meta Business Suite)
        ],

        'google' => [
            'enabled' => env('ADS_GOOGLE_ENABLED', true),
            'api_version' => env('ADS_GOOGLE_API_VERSION', 'v18'),
            // Credentials stored in Settings model:
            // google_ads_client_id, google_ads_client_secret, google_ads_developer_token
            // meta.google_ads_customer_id, meta.google_ads_refresh_token
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Scheduling
    |--------------------------------------------------------------------------
    */

    'scheduling' => [
        // How often to sync metrics from ad platforms
        'metrics_sync_interval' => env('ADS_METRICS_SYNC_MINUTES', 60),

        // How often to run the optimization engine
        'optimization_interval' => env('ADS_OPTIMIZATION_HOURS', 6),

        // When to generate daily reports (24h format)
        'daily_report_time' => env('ADS_DAILY_REPORT_TIME', '08:00'),

        // When to sync audience segments
        'audience_sync_interval' => env('ADS_AUDIENCE_SYNC_HOURS', 24),
    ],

    /*
    |--------------------------------------------------------------------------
    | Optimization Defaults
    |--------------------------------------------------------------------------
    */

    'optimization' => [
        // Default optimization thresholds
        'defaults' => [
            'max_cpc' => (float) env('ADS_DEFAULT_MAX_CPC', 3.00),
            'min_ctr' => (float) env('ADS_DEFAULT_MIN_CTR', 0.5),
            'min_roas' => (float) env('ADS_DEFAULT_MIN_ROAS', 1.5),
            'max_frequency' => (int) env('ADS_DEFAULT_MAX_FREQUENCY', 5),
        ],

        // Minimum data required before optimization kicks in
        'min_impressions_for_optimization' => 1000,
        'min_days_for_ab_test' => 3,
        'min_impressions_per_variant' => 1000,

        // Budget reallocation
        'min_reallocation_change_pct' => 10, // Only reallocate if change > 10%
        'min_platform_budget_pct' => 10,     // No platform gets less than 10% of total
    ],

    /*
    |--------------------------------------------------------------------------
    | Default Budget Weights (for initial allocation)
    |--------------------------------------------------------------------------
    */

    'budget_weights' => [
        'facebook' => 0.35,
        'instagram' => 0.30,
        'google' => 0.25,
        'tiktok' => 0.10,
    ],

    /*
    |--------------------------------------------------------------------------
    | Creative Specifications
    |--------------------------------------------------------------------------
    */

    'creative_specs' => [
        'facebook' => [
            'image_max_size_mb' => 30,
            'video_max_size_mb' => 4096,
            'video_max_duration_seconds' => 240,
            'headline_max_chars' => 40,
            'primary_text_max_chars' => 125,
            'description_max_chars' => 30,
        ],
        'instagram' => [
            'image_max_size_mb' => 30,
            'video_max_size_mb' => 4096,
            'video_max_duration_seconds' => 120,
            'headline_max_chars' => 40,
            'primary_text_max_chars' => 125,
            'reels_max_duration_seconds' => 90,
        ],
        'google' => [
            'image_max_size_mb' => 5,
            'headline_max_chars' => 30,
            'description_max_chars' => 90,
            'headlines_required' => 3,
            'descriptions_required' => 2,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Retargeting Defaults
    |--------------------------------------------------------------------------
    */

    'retargeting' => [
        'website_visitors_days' => 30,     // Retarget visitors from last 30 days
        'cart_abandoners_days' => 14,      // Retarget abandoners from last 14 days
        'past_attendees_days' => 180,      // Retarget past event attendees
        'lookalike_default_pct' => 2,      // 2% lookalike audience
        'max_audience_sync_size' => 10000, // Max users per sync batch
    ],
];
