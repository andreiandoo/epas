<?php
/**
 * SITE CONFIG — ambilet (MARKETPLACE profile)
 *
 * Same file role as the tenant one, but profile=marketplace: auth is by API key
 * and the data layer targets /marketplace-client/*. Note NOTHING in the page
 * code changes between profiles — only this config + theme.css.
 */
return [
    'profile'   => 'marketplace',
    'api_base'  => 'https://core.tixello.com/api',
    'core_url'  => 'https://core.tixello.com',
    'api_key'   => getenv('AMBILET_API_KEY') ?: 'mpc_REPLACE_ME',

    'site_name' => 'AmBilet',
    'site_city' => '',
    'logo_text' => 'A',
    'site_url'  => 'https://ambilet.ro',
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

    'fonts_href' => 'https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap',
    'tokens_href' => '/theme/tokens.css',
    'theme_href'  => '/theme/theme.css',
    'kit_js_href' => '/kit/kit.js',

    'cache_ttl' => 120,
    'debug'     => false,
    'fixtures'  => getenv('KIT_FIXTURES') ?: null,
];
