<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Master Switch
    |--------------------------------------------------------------------------
    | Disable support ticketing entirely (API + UI hidden) when false.
    */
    'enabled' => env('SUPPORT_ENABLED', true),

    /*
    |--------------------------------------------------------------------------
    | Beta Allow-List
    |--------------------------------------------------------------------------
    | While the system is in testing, only the IDs listed here may open or
    | view tickets. Empty array = nobody has access. Set to ['*'] to open
    | the gate to everyone of that opener type.
    |
    | Set via .env:
    |   SUPPORT_ALLOWED_ORGANIZER_IDS=136,200
    |   SUPPORT_ALLOWED_CUSTOMER_IDS=*
    */
    'allowed_opener_ids' => [
        'organizer' => array_values(array_filter(array_map(
            'trim',
            explode(',', env('SUPPORT_ALLOWED_ORGANIZER_IDS', '136'))
        ))),
        'customer' => array_values(array_filter(array_map(
            'trim',
            explode(',', env('SUPPORT_ALLOWED_CUSTOMER_IDS', ''))
        ))),
    ],

    /*
    |--------------------------------------------------------------------------
    | Attachment Rules
    |--------------------------------------------------------------------------
    */
    'attachments' => [
        'max_size_kb' => 3 * 1024,                 // 3 MB per file
        'allowed_mimes' => ['jpg', 'jpeg', 'png', 'pdf'],
        'max_per_message' => 5,
        'storage_disk' => 'public',
        'storage_path' => 'support-tickets',
    ],

    /*
    |--------------------------------------------------------------------------
    | Lifecycle
    |--------------------------------------------------------------------------
    */
    'auto_close' => [
        'enabled' => true,
        // Tickets in 'resolved' state get closed automatically after this
        // many days without further activity (cron picks them up).
        'after_days' => 7,
    ],

    /*
    |--------------------------------------------------------------------------
    | Polymorphic Morph Map
    |--------------------------------------------------------------------------
    | Aliases used in opener_type / author_type DB columns. Keep stable —
    | renaming requires a data migration.
    */
    'morph_map' => [
        'organizer' => \App\Models\MarketplaceOrganizer::class,
        'customer' => \App\Models\MarketplaceCustomer::class,
        'staff' => \App\Models\User::class,
    ],
];
