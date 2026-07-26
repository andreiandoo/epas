<?php
/**
 * KIND: filarmonica  (philharmonic / concert hall)
 * Like teatru but the vocabulary is concert/season/orchestra, and there is no
 * "troupe" — soloists & conductors instead.
 */
return [
    'profile' => 'tenant',
    'label'   => 'Filarmonică',

    'terms' => [
        'event' => 'concert', 'events' => 'concerte', 'events_cap' => 'Concerte',
        'event_cap' => 'Concert', 'venue' => 'sală', 'artist' => 'solist',
        'artists' => 'orchestră', 'artists_cap' => 'Orchestra', 'buy' => 'Cumpără bilete', 'buy_short' => 'Bilete',
    ],
    'features' => [
        'seating' => true, 'subscriptions' => true, 'reviews' => true, 'gift_cards' => true,
    ],

    'event_url_pattern'    => '/concert/{slug}',
    'artist_url_pattern'   => '/solist/{slug}',
    'category_url_pattern' => '/program?gen={slug}',

    'menu' => [
        ['key' => 'schedule',      'label' => 'Program',    'url' => '/program'],
        ['key' => 'season',        'label' => 'Stagiune',   'url' => '/stagiune'],
        ['key' => 'subscriptions', 'label' => 'Abonamente', 'url' => '/abonamente'],
        ['key' => 'orchestra',     'label' => 'Orchestra',  'url' => '/orchestra'],
        ['key' => 'about',         'label' => 'Despre',     'url' => '/despre'],
    ],
    'cta_label' => 'Cumpără bilete',
    'cta_url'   => '/program',
    'cart_url'  => '/cos',

    'pages' => [
        'index'         => ['set' => 'home'],
        'schedule'      => ['set' => 'calendar',      'nav' => 'schedule'],
        'season'        => ['set' => 'listing',       'nav' => 'season'],
        'subscriptions' => ['set' => 'subscriptions', 'nav' => 'subscriptions'],
        'orchestra'     => ['set' => 'artists',       'nav' => 'orchestra'],
        'about'         => ['set' => 'about',         'nav' => 'about'],
        'show'          => ['set' => 'show'],
        'cart'          => ['set' => 'cart'],
        'checkout'      => ['set' => 'checkout'],
        '404'           => ['set' => '404'],
    ],
];
