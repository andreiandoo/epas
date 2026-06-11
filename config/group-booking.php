<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Group Booking Configuration
    |--------------------------------------------------------------------------
    */

    'min_group_size' => env('GROUP_BOOKING_MIN_SIZE', 2),
    'max_group_size' => env('GROUP_BOOKING_MAX_SIZE', 500),

    'discount_tiers' => [
        ['min' => 10, 'max' => 24, 'discount' => 5],
        ['min' => 25, 'max' => 49, 'discount' => 10],
        ['min' => 50, 'max' => 99, 'discount' => 15],
        ['min' => 100, 'max' => null, 'discount' => 20],
    ],

    'payment' => [
        'split_payment_expiry_days' => env('GROUP_SPLIT_EXPIRY_DAYS', 7),
        'reminder_days_before_event' => env('GROUP_REMINDER_DAYS', [7, 3, 1]),
        'allow_partial_confirmation' => env('GROUP_ALLOW_PARTIAL', false),
    ],

    'invoice' => [
        'payment_terms_days' => env('GROUP_INVOICE_TERMS', 30),
        'require_po_number' => env('GROUP_REQUIRE_PO', false),
    ],

    'notifications' => [
        'send_payment_reminders' => env('GROUP_SEND_REMINDERS', true),
        'notify_organizer_on_payment' => env('GROUP_NOTIFY_ORGANIZER', true),
    ],

    'import' => [
        'max_csv_rows' => env('GROUP_MAX_CSV_ROWS', 500),
        'required_columns' => ['name', 'email'],
    ],
];
