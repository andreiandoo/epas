<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Waitlist & Resale Configuration
    |--------------------------------------------------------------------------
    */

    'waitlist' => [
        'max_quantity_per_entry' => env('WAITLIST_MAX_QUANTITY', 10),
        'offer_expiry_hours' => env('WAITLIST_OFFER_EXPIRY_HOURS', 24),
        'auto_process' => env('WAITLIST_AUTO_PROCESS', true),
        'priority_levels' => ['normal', 'vip'],
    ],

    'resale' => [
        'enabled' => env('RESALE_ENABLED', true),
        'max_markup_percentage' => env('RESALE_MAX_MARKUP', 20),
        'platform_fee_percentage' => env('RESALE_PLATFORM_FEE', 10),
        'payout_delay_days' => env('RESALE_PAYOUT_DELAY', 3),
        'require_approval' => env('RESALE_REQUIRE_APPROVAL', false),
    ],

    'notifications' => [
        'waitlist_position' => env('NOTIFY_WAITLIST_POSITION', true),
        'resale_sold' => env('NOTIFY_RESALE_SOLD', true),
        'resale_purchased' => env('NOTIFY_RESALE_PURCHASED', true),
    ],
];
