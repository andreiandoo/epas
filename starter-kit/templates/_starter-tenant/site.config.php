<?php
/**
 * SITE CONFIG — __SITE_NAME__ (TENANT profile)
 * Fill the TODOs, then edit theme.css. Those two files ARE the site.
 */
return [
    'profile'   => 'tenant',
    'api_base'  => 'https://core.tixello.com/api',
    'core_url'  => 'https://core.tixello.com',
    'tenant_id' => 0, // TODO: this tenant's ID in Tixello

    'site_name' => '__SITE_NAME__',
    'site_city' => '',           // TODO
    'logo_text' => '',           // TODO (2-3 letters)
    'site_url'  => 'https://__SLUG__.tixello.ro',
    'locale'    => 'ro',
    'currency'  => 'RON',

    'event_url_pattern'    => '/spectacol/{slug}',
    'artist_url_pattern'   => '/artist/{slug}',
    'category_url_pattern' => '/repertoriu?cat={slug}',

    'menu' => [
        ['key' => 'repertoire',    'label' => 'Repertoriu', 'url' => '/repertoriu'],
        ['key' => 'schedule',      'label' => 'Program',    'url' => '/program'],
        ['key' => 'subscriptions', 'label' => 'Abonamente', 'url' => '/abonamente'],
        ['key' => 'about',         'label' => 'Despre',     'url' => '/despre'],
    ],
    'cta_label' => 'Cumpără bilete',
    'cta_url'   => '/program',
    'cart_url'  => '/cos',

    'fonts_href' => '', // optional Google Fonts URL for this theme
    'tokens_href' => '/theme/tokens.css',
    'theme_href'  => '/theme/theme.css',
    'kit_js_href' => '/kit/kit.js',

    'cache_ttl' => 120,
    'debug'     => false,
    'fixtures'  => getenv('KIT_FIXTURES') ?: null,
];
