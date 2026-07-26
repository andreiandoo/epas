<?php
/**
 * KIND: leisure  (activities / attractions / rentals — parks, escape rooms, boats)
 * Time-slot booking & rentals instead of seating; "activitate" vocabulary.
 */
return [
    'profile' => 'tenant',
    'label'   => 'Leisure / Activități',

    'terms' => [
        'event' => 'activitate', 'events' => 'activități', 'events_cap' => 'Activități',
        'event_cap' => 'Activitate', 'venue' => 'locație', 'artist' => 'gazdă',
        'artists' => 'echipă', 'artists_cap' => 'Echipa', 'buy' => 'Rezervă', 'buy_short' => 'Rezervă',
    ],
    'features' => [
        'booking' => true, 'rentals' => true, 'gift_cards' => true, 'reviews' => true,
    ],

    'event_url_pattern'    => '/activitate/{slug}',
    'category_url_pattern' => '/activitati?cat={slug}',

    'menu' => [
        ['key' => 'activities', 'label' => 'Activități', 'url' => '/activitati'],
        ['key' => 'rentals',    'label' => 'Închirieri', 'url' => '/inchirieri'],
        ['key' => 'giftcards',  'label' => 'Carduri cadou', 'url' => '/carduri-cadou'],
        ['key' => 'about',      'label' => 'Despre',     'url' => '/despre'],
    ],
    'cta_label' => 'Rezervă acum',
    'cta_url'   => '/activitati',
    'cart_url'  => '/cos',

    'pages' => [
        'index'      => ['set' => 'home'],
        'activities' => ['set' => 'listing',   'nav' => 'activities'],
        'rentals'    => ['set' => 'rentals',   'nav' => 'rentals'],
        'giftcards'  => ['set' => 'giftcards', 'nav' => 'giftcards'],
        'about'      => ['set' => 'about',     'nav' => 'about'],
        'show'       => ['set' => 'show'],
        'cart'       => ['set' => 'cart'],
        'checkout'   => ['set' => 'checkout'],
        '404'        => ['set' => '404'],
    ],
];
