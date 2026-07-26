<?php
/**
 * KIND: organizator  (promoter / organizer running many events across venues)
 * Multi-venue, tours, gift cards; storefront = events + organizer profile.
 */
return [
    'profile' => 'tenant',
    'label'   => 'Organizator',

    'terms' => [
        'event' => 'eveniment', 'events' => 'evenimente', 'events_cap' => 'Evenimente',
        'venue' => 'locație', 'artist' => 'artist', 'artists' => 'artiști', 'artists_cap' => 'Artiști',
        'buy' => 'Cumpără bilete', 'buy_short' => 'Bilete',
    ],
    'features' => [
        'multi_venue' => true, 'tours' => true, 'gift_cards' => true, 'reviews' => true,
    ],

    'event_url_pattern'    => '/eveniment/{slug}',
    'venue_url_pattern'    => '/locatie/{slug}',
    'category_url_pattern' => '/evenimente?cat={slug}',

    'menu' => [
        ['key' => 'events', 'label' => 'Evenimente', 'url' => '/evenimente'],
        ['key' => 'venues', 'label' => 'Locații',    'url' => '/locatii'],
        ['key' => 'tours',  'label' => 'Turnee',     'url' => '/turnee'],
        ['key' => 'about',  'label' => 'Despre',     'url' => '/despre'],
    ],
    'cta_label' => 'Vezi evenimentele',
    'cta_url'   => '/evenimente',
    'cart_url'  => '/cos',

    'pages' => ['index', 'events', 'venues', 'tours', 'about', 'show', 'cart', 'checkout', '404'],
];
