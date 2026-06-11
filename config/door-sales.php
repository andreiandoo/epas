<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Door Sales Configuration
    |--------------------------------------------------------------------------
    */

    // Platform fee (percentage of subtotal - includes Stripe fees)
    'platform_fee_percentage' => env('DOOR_SALES_PLATFORM_FEE', 5.0),
    'min_fee' => env('DOOR_SALES_MIN_FEE', 0.10),

    // Volume discount tiers (can be applied per tenant)
    'volume_tiers' => [
        ['min' => 0, 'max' => 10000, 'fee' => 5.0],
        ['min' => 10000, 'max' => 50000, 'fee' => 4.0],
        ['min' => 50000, 'max' => null, 'fee' => 3.0],
    ],

    // Stripe Connect settings
    'stripe_connect' => [
        'account_type' => env('STRIPE_CONNECT_ACCOUNT_TYPE', 'express'),
        'default_country' => env('STRIPE_CONNECT_DEFAULT_COUNTRY', 'RO'),
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
