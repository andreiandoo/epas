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
        'tours' => true, 'multi_venue' => true, 'reviews' => true, 'gift_cards' => true, 'blog' => true,
    ],

    'terms_i18n' => ['en' => ['event' => 'event', 'events' => 'events', 'events_cap' => 'Events', 'event_cap' => 'Event', 'venue' => 'venue', 'artist' => 'artist', 'artists' => 'artists', 'artists_cap' => 'Artists', 'buy' => 'Buy tickets', 'buy_short' => 'Tickets']],
    'event_url_pattern'    => '/eveniment/{slug}',
    'artist_url_pattern'   => '/artist/{slug}',
    'venue_url_pattern'    => '/locatie/{slug}',
    'category_url_pattern' => '/evenimente?cat={slug}',

    'menu' => [
        ['key' => 'events',  'label' => 'Evenimente', 'url' => '/evenimente'],
        ['key' => 'artists', 'label' => 'Artiști',    'url' => '/artisti'],
        ['key' => 'tours',   'label' => 'Turnee',     'url' => '/turnee'],
        ['key' => 'venues',  'label' => 'Locații',    'url' => '/locatii'],
        ['key' => 'blog',    'label' => 'Blog',       'url' => '/blog'],
        ['key' => 'about',   'label' => 'Despre',     'url' => '/despre'],
    ],
    'cta_label' => 'Vezi evenimentele',
    'cta_url'   => '/evenimente',
    'cart_url'  => '/cos',

    'pages' => [
        'index'    => ['set' => 'home'],
        'events'   => ['set' => 'listing', 'nav' => 'events'],
        'artists'  => ['set' => 'artists', 'nav' => 'artists'],
        'tours'    => ['set' => 'tours',   'nav' => 'tours'],
        'venues'   => ['set' => 'venues',  'nav' => 'venues'],
        'about'    => ['set' => 'about',   'nav' => 'about'],
        'show'     => ['set' => 'show'],
        'cart'     => ['set' => 'cart'],
        'checkout' => ['set' => 'checkout'],
        '404'      => ['set' => '404'],
    ],
];
