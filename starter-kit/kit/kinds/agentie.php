<?php
/**
 * KIND: agentie  (booking / ticket agency, resells across venues & artists)
 * Broad event vocabulary, multi-venue, tours; no own subscriptions/seating.
 */
return [
    'profile' => 'tenant',
    'label'   => 'Agenție',

    'terms' => [
        'event' => 'eveniment', 'events' => 'evenimente', 'events_cap' => 'Evenimente',
        'venue' => 'locație', 'artist' => 'artist', 'artists' => 'artiști', 'artists_cap' => 'Artiști',
        'buy' => 'Cumpără bilete', 'buy_short' => 'Bilete',
    ],
    'features' => [
        'tours' => true, 'multi_venue' => true, 'reviews' => true, 'gift_cards' => true,
    ],

    'event_url_pattern'    => '/eveniment/{slug}',
    'artist_url_pattern'   => '/artist/{slug}',
    'venue_url_pattern'    => '/locatie/{slug}',
    'category_url_pattern' => '/evenimente?cat={slug}',

    'menu' => [
        ['key' => 'events',  'label' => 'Evenimente', 'url' => '/evenimente'],
        ['key' => 'artists', 'label' => 'Artiști',    'url' => '/artisti'],
        ['key' => 'tours',   'label' => 'Turnee',     'url' => '/turnee'],
        ['key' => 'venues',  'label' => 'Locații',    'url' => '/locatii'],
        ['key' => 'about',   'label' => 'Despre',     'url' => '/despre'],
    ],
    'cta_label' => 'Vezi evenimentele',
    'cta_url'   => '/evenimente',
    'cart_url'  => '/cos',

    'pages' => ['index', 'events', 'artists', 'tours', 'venues', 'show', 'cart', 'checkout', '404'],
];
