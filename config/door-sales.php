<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Door Sales Configuration
    |--------------------------------------------------------------------------
    */

    // Platform fee (percentage of subtotal)
    'platform_fee_percentage' => env('DOOR_SALES_PLATFORM_FEE', 2.5),
    'min_fee' => env('DOOR_SALES_MIN_FEE', 0.10),

    // Payment processing fees (Stripe Tap to Pay)
    'processing_fee_percentage' => env('DOOR_SALES_PROCESSING_FEE_PCT', 1.4),
    'processing_fee_fixed' => env('DOOR_SALES_PROCESSING_FEE_FIXED', 0.25),

    // Volume discount tiers
    'volume_tiers' => [
        ['min' => 0, 'max' => 10000, 'fee' => 2.5],
        ['min' => 10000, 'max' => 50000, 'fee' => 2.0],
        ['min' => 50000, 'max' => null, 'fee' => 1.5],
    ],

    // Transaction limits
    'max_tickets_per_sale' => env('DOOR_SALES_MAX_TICKETS', 10),
    'max_amount_per_sale' => env('DOOR_SALES_MAX_AMOUNT', 5000),

    // Payment methods
    'payment_methods' => [
        'card_tap' => true,
        'apple_pay' => true,
        'google_pay' => true,
    ],

    // Stripe Terminal settings
    'stripe' => [
        'terminal_location' => env('STRIPE_TERMINAL_LOCATION'),
        'connection_token_url' => '/api/stripe/terminal/connection-token',
    ],

    // Email settings
    'send_tickets_automatically' => env('DOOR_SALES_AUTO_SEND_TICKETS', true),

    // Settlement
    'settlement_schedule' => env('DOOR_SALES_SETTLEMENT_SCHEDULE', 'daily'),
];
