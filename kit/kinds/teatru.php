<?php
/**
 * KIND: teatru  (theatre)
 *
 * A kind manifest is a preset layered between kit defaults and a site's config:
 * it sets the profile, terminology, enabled features, URL patterns, default
 * menu and the recommended page-set. A concrete teatru site then only supplies
 * identity (tenant_id, name) + a theme.css. 10 teatru templates share this
 * manifest and differ only by theme + copy.
 */
return [
    'profile' => 'tenant',
    'label'   => 'Teatru',

    'terms' => [
        'event' => 'spectacol', 'events' => 'spectacole', 'events_cap' => 'Spectacole',
        'event_cap' => 'Spectacol', 'venue' => 'sală', 'artist' => 'actor',
        'artists' => 'trupă', 'artists_cap' => 'Trupa', 'buy' => 'Cumpără bilete', 'buy_short' => 'Bilete',
    ],
    'features' => [
        'seating' => true, 'subscriptions' => true, 'gamification' => true,
        'reviews' => true, 'gift_cards' => true,
    ],
    // Per-locale noun overrides (kit_term picks the active locale).
    'terms_i18n' => [
        'en' => ['event' => 'show', 'events' => 'shows', 'events_cap' => 'Shows', 'event_cap' => 'Show',
                 'venue' => 'hall', 'artist' => 'actor', 'artists' => 'troupe', 'artists_cap' => 'Troupe',
                 'buy' => 'Buy tickets', 'buy_short' => 'Tickets'],
        'hu' => ['event' => 'előadás', 'events' => 'előadások', 'events_cap' => 'Előadások', 'event_cap' => 'Előadás',
                 'venue' => 'terem', 'artist' => 'színész', 'artists' => 'társulat', 'artists_cap' => 'Társulat',
                 'buy' => 'Jegyvásárlás', 'buy_short' => 'Jegyek'],
    ],

    'event_url_pattern'    => '/spectacol/{slug}',
    'artist_url_pattern'   => '/actor/{slug}',
    'category_url_pattern' => '/repertoriu?gen={slug}',

    'menu' => [
        ['key' => 'repertoire',    'label' => 'Repertoriu', 'url' => '/repertoriu'],
        ['key' => 'schedule',      'label' => 'Program',    'url' => '/program'],
        ['key' => 'subscriptions', 'label' => 'Abonamente', 'url' => '/abonamente'],
        ['key' => 'troupe',        'label' => 'Trupa',      'url' => '/trupa'],
        ['key' => 'about',         'label' => 'Despre',     'url' => '/despre'],
    ],
    'cta_label' => 'Cumpără bilete',
    'cta_url'   => '/program',
    'cart_url'  => '/cos',

    // Page-set the generator scaffolds: page name => [pageset, nav].
    'pages' => [
        'index'         => ['set' => 'home'],
        'repertoire'    => ['set' => 'listing',       'nav' => 'repertoire'],
        'schedule'      => ['set' => 'calendar',      'nav' => 'schedule'],
        'subscriptions' => ['set' => 'subscriptions', 'nav' => 'subscriptions'],
        'troupe'        => ['set' => 'artists',       'nav' => 'troupe'],
        'about'         => ['set' => 'about',         'nav' => 'about'],
        'show'          => ['set' => 'show'],
        'cart'          => ['set' => 'cart'],
        'checkout'      => ['set' => 'checkout'],
        '404'           => ['set' => '404'],
    ],
];
