<?php

return [
    /*
    |--------------------------------------------------------------------------
    | CRM & Marketing Automation Configuration
    |--------------------------------------------------------------------------
    */

    'segments' => [
        'recalculate_interval_hours' => env('CRM_SEGMENT_RECALCULATE_HOURS', 24),
        'max_conditions' => env('CRM_MAX_SEGMENT_CONDITIONS', 10),
    ],

    'campaigns' => [
        'default_from_name' => env('CRM_DEFAULT_FROM_NAME', config('app.name')),
        'default_from_email' => env('CRM_DEFAULT_FROM_EMAIL', config('mail.from.address')),
        'batch_size' => env('CRM_CAMPAIGN_BATCH_SIZE', 100),
        'rate_limit_per_minute' => env('CRM_RATE_LIMIT', 500),
    ],

    'tracking' => [
        'enabled' => env('CRM_TRACKING_ENABLED', true),
        'track_opens' => env('CRM_TRACK_OPENS', true),
        'track_clicks' => env('CRM_TRACK_CLICKS', true),
    ],

    'automation' => [
        'max_steps' => env('CRM_MAX_WORKFLOW_STEPS', 20),
        'max_active_workflows' => env('CRM_MAX_ACTIVE_WORKFLOWS', 50),
        'process_interval_minutes' => env('CRM_PROCESS_INTERVAL', 5),
    ],

    'unsubscribe' => [
        'enabled' => env('CRM_UNSUBSCRIBE_ENABLED', true),
        'cooldown_days' => env('CRM_UNSUBSCRIBE_COOLDOWN', 30),
    ],
];
