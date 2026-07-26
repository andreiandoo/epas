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
        'multi_venue' => true, 'tours' => true, 'gift_cards' => true, 'reviews' => true, 'blog' => true,
    ],

    'terms_i18n' => ['en' => ['event' => 'event', 'events' => 'events', 'events_cap' => 'Events', 'event_cap' => 'Event', 'venue' => 'venue', 'artist' => 'artist', 'artists' => 'artists', 'artists_cap' => 'Artists', 'buy' => 'Buy tickets', 'buy_short' => 'Tickets']],
    'event_url_pattern'    => '/eveniment/{slug}',
    'venue_url_pattern'    => '/locatie/{slug}',
    'category_url_pattern' => '/evenimente?cat={slug}',

    'menu' => [
        ['key' => 'events', 'label' => 'Evenimente', 'url' => '/evenimente'],
        ['key' => 'venues', 'label' => 'Locații',    'url' => '/locatii'],
        ['key' => 'tours',  'label' => 'Turnee',     'url' => '/turnee'],
        ['key' => 'blog',   'label' => 'Blog',       'url' => '/blog'],
        ['key' => 'about',  'label' => 'Despre',     'url' => '/despre'],
    ],
    'cta_label' => 'Vezi evenimentele',
    'cta_url'   => '/evenimente',
    'cart_url'  => '/cos',

    'pages' => [
        'index'    => ['set' => 'home'],
        'events'   => ['set' => 'listing', 'nav' => 'events'],
        'venues'   => ['set' => 'venues',  'nav' => 'venues'],
        'tours'    => ['set' => 'tours',   'nav' => 'tours'],
        'about'    => ['set' => 'about',   'nav' => 'about'],
        'show'     => ['set' => 'show'],
        'cart'     => ['set' => 'cart'],
        'checkout' => ['set' => 'checkout'],
        '404'      => ['set' => '404'],
    ],
];
