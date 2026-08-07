<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Shorts — vertical short-form video feed (mobile app only)
    |--------------------------------------------------------------------------
    |
    | See docs/plans/shorts.md. Ranking weights live here (rather than in code)
    | so the feed stays tunable per environment and remains explainable.
    |
    */

    'feed' => [
        // Page size for the cursor-paginated feed.
        'page_size' => (int) env('SHORTS_FEED_PAGE_SIZE', 10),
        'max_page_size' => (int) env('SHORTS_FEED_MAX_PAGE_SIZE', 30),

        // Candidate pool the ranker scores before trimming to a page.
        'candidate_pool' => (int) env('SHORTS_FEED_CANDIDATE_POOL', 200),

        // Never place two consecutive shorts from the same owner.
        'diversity_enabled' => (bool) env('SHORTS_FEED_DIVERSITY', true),
    ],

    /*
    | Weights for the "For You" scored query. Kept flat and readable — the score
    | is logged in dev so a placement can always be explained.
    */
    'ranker' => [
        'weights' => [
            'affinity' => (float) env('SHORTS_W_AFFINITY', 3.0),
            'popularity' => (float) env('SHORTS_W_POPULARITY', 1.5),
            'watch' => (float) env('SHORTS_W_WATCH', 2.0),
            'geo' => (float) env('SHORTS_W_GEO', 1.0),
            'freshness' => (float) env('SHORTS_W_FRESH', 1.0),
            'featured' => (float) env('SHORTS_W_FEATURED', 0.75),
            'seen_penalty' => (float) env('SHORTS_W_SEEN', 4.0),
        ],

        // Half-life (hours) used by the freshness decay.
        'freshness_half_life_hours' => (int) env('SHORTS_FRESH_HALF_LIFE', 72),

        // Popularity window (hours) for the velocity signal.
        'popularity_window_hours' => (int) env('SHORTS_POPULARITY_WINDOW', 48),

        // Log the per-short score breakdown outside production.
        'explain' => (bool) env('SHORTS_RANKER_EXPLAIN', false),
    ],

    /*
    | Telemetry ingestion guardrails (see D6).
    */
    'telemetry' => [
        // Max events accepted in one batched POST.
        'max_batch' => (int) env('SHORTS_TELEMETRY_MAX_BATCH', 100),

        // A view only counts past these thresholds (anti-fraud, see D6).
        'view_min_ms' => (int) env('SHORTS_VIEW_MIN_MS', 2000),
        'view_min_ratio' => (float) env('SHORTS_VIEW_MIN_RATIO', 0.25),

        // Keep 1/N impressions; every other event type is kept in full.
        'impression_sampling' => (int) env('SHORTS_IMPRESSION_SAMPLING', 1),

        // Raw short_events retention before pruning into the rollups.
        'retention_days' => (int) env('SHORTS_EVENTS_RETENTION_DAYS', 90),
    ],

    /*
    | Deep links used by share + landing (see D1).
    */
    'deep_link' => [
        'scheme' => env('SHORTS_DEEPLINK_SCHEME', 'tixello'),
        'share_base_url' => env('SHORTS_SHARE_BASE_URL', env('APP_URL', 'http://localhost')),
        // Store fallbacks on the share landing page when the app is not installed.
        'ios_store_url' => env('SHORTS_IOS_STORE_URL'),
        'android_store_url' => env('SHORTS_ANDROID_STORE_URL'),
    ],

    /*
    | Points and streaks for shorts activity (see D11).
    |
    | The daily cap is the anti-abuse lever: without it, a script can farm the
    | watch reward indefinitely.
    */
    'gamification' => [
        'enabled' => (bool) env('SHORTS_GAMIFICATION', true),
        'watch_points' => (int) env('SHORTS_POINTS_WATCH', 5),
        'share_points' => (int) env('SHORTS_POINTS_SHARE', 10),
        'ugc_points' => (int) env('SHORTS_POINTS_UGC', 50),
        'streak_bonus_cap' => (int) env('SHORTS_STREAK_BONUS_CAP', 10),
        'daily_cap' => (int) env('SHORTS_POINTS_DAILY_CAP', 100),
    ],

    /*
    | Player UX (see D9) — surfaced to the client through the feed payload so a
    | tuning change does not need an app release.
    */
    'player' => [
        // How many shorts ahead the client should prefetch posters for.
        'prefetch_count' => (int) env('SHORTS_PREFETCH_COUNT', 2),
        // Platform-wide quality drop, used by the Bunny cost guardrails (D8).
        'data_saver_global' => (bool) env('SHORTS_DATA_SAVER_GLOBAL', false),
    ],
];
