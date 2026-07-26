<?php
/**
 * SITE CONFIG — __SITE_NAME__ (MARKETPLACE profile)
 * Fill the TODOs, then edit theme.css.
 */
return [
    'profile'  => 'marketplace',
    'api_base' => 'https://core.tixello.com/api',
    'core_url' => 'https://core.tixello.com',
    'api_key'  => getenv('KIT_API_KEY') ?: 'mpc_TODO', // TODO: marketplace client key

    'site_name' => '__SITE_NAME__',
    'logo_text' => '',           // TODO
    'site_url'  => 'https://__SLUG__.ro',
    'locale'    => 'ro',
    'currency'  => 'RON',

    'event_url_pattern'    => '/bilete/{slug}',
    'artist_url_pattern'   => '/artist/{slug}',
    'venue_url_pattern'    => '/venue/{slug}',
    'category_url_pattern' => '/categorie/{slug}',

    'menu' => [
        ['key' => 'events',     'label' => 'Evenimente', 'url' => '/evenimente'],
        ['key' => 'categories', 'label' => 'Categorii',  'url' => '/categorii'],
        ['key' => 'venues',     'label' => 'Locații',    'url' => '/locatii'],
        ['key' => 'artists',    'label' => 'Artiști',    'url' => '/artisti'],
    ],
    'cta_label' => 'Cumpără bilete',
    'cta_url'   => '/evenimente',
    'cart_url'  => '/cos',

    'fonts_href' => '',
    'tokens_href' => '/theme/tokens.css',
    'theme_href'  => '/theme/theme.css',
    'kit_js_href' => '/kit/kit.js',

    'cache_ttl' => 120,
    'debug'     => false,
    'fixtures'  => getenv('KIT_FIXTURES') ?: null,
];
