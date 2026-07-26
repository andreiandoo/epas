<?php
/**
 * KIND: artist  (single artist / band official site)
 * Tour dates, EPK, booking/contact, merch, fan CRM. No seating/subscriptions.
 */
return [
    'profile' => 'tenant',
    'label'   => 'Artist / Trupă',

    'terms' => [
        'event' => 'concert', 'events' => 'concerte', 'events_cap' => 'Concerte',
        'event_cap' => 'Concert', 'venue' => 'locație', 'artist' => 'membru',
        'artists' => 'formație', 'artists_cap' => 'Formația', 'buy' => 'Bilete', 'buy_short' => 'Bilete',
    ],
    'features' => [
        'epk' => true, 'booking' => true, 'tours' => true, 'merch' => true, 'fan_crm' => true,
    ],

    'event_url_pattern'    => '/concert/{slug}',
    'category_url_pattern' => '/concerte?an={slug}',

    'menu' => [
        ['key' => 'tour',    'label' => 'Turneu',  'url' => '/concerte'],
        ['key' => 'music',   'label' => 'Muzică',  'url' => '/muzica'],
        ['key' => 'gallery', 'label' => 'Galerie', 'url' => '/galerie'],
        ['key' => 'epk',     'label' => 'Press',   'url' => '/epk'],
        ['key' => 'contact', 'label' => 'Contact / Booking', 'url' => '/contact'],
    ],
    'cta_label' => 'Vezi concertele',
    'cta_url'   => '/concerte',
    'cart_url'  => '/cos',

    'pages' => ['index', 'tour', 'music', 'gallery', 'epk', 'contact', 'show', '404'],
];
