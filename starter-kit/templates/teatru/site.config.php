<?php
/**
 * SITE CONFIG — teatru (TENANT profile)
 *
 * This is the ONLY file (plus theme.css) that differs from any other tenant
 * site. Everything data/behaviour-related is here; everything visual is in
 * theme.css. To spin up a new tenant, copy templates/_starter-tenant and edit
 * these two files.
 */
return [
    'profile'    => 'tenant',
    'api_base'   => 'https://core.tixello.com/api',
    'core_url'   => 'https://core.tixello.com',
    'tenant_id'  => 17,

    'site_name'  => 'Teatrul Național',
    'site_city'  => 'BUCUREȘTI',
    'logo_text'  => 'TN',
    'site_url'   => 'https://teatru.tixello.ro',
    'locale'     => 'ro',
    'currency'   => 'RON',

    // Canonical on-site URL patterns (match .htaccess rewrites)
    'event_url_pattern'    => '/spectacol/{slug}',
    'artist_url_pattern'   => '/artist/{slug}',
    'category_url_pattern' => '/repertoriu?cat={slug}',

    // Header
    'menu' => [
        ['key' => 'repertoire',    'label' => 'Repertoriu', 'url' => '/repertoriu'],
        ['key' => 'schedule',      'label' => 'Program',    'url' => '/program'],
        ['key' => 'subscriptions', 'label' => 'Abonamente', 'url' => '/abonamente'],
        ['key' => 'troupe',        'label' => 'Trupa',      'url' => '/trupa'],
        ['key' => 'about',         'label' => 'Despre noi', 'url' => '/despre'],
    ],
    'cta_label' => 'Cumpără bilete',
    'cta_url'   => '/program',
    'cart_url'  => '/cos',

    // Fonts for this theme
    'fonts_href' => 'https://fonts.googleapis.com/css2?family=Playfair+Display:wght@400;600;700&family=Lato:wght@300;400;600;700&display=swap',

    // Asset hrefs (served locally by the deployed site; see includes/bootstrap.php)
    'tokens_href' => '/theme/tokens.css',
    'theme_href'  => '/theme/theme.css',
    'kit_js_href' => '/kit/kit.js',

    'cache_ttl'   => 120,
    'debug'       => false,

    // OFFLINE VERIFICATION: point at the repo fixtures so pages render without
    // the live backend. Set to null in production.
    'fixtures'    => getenv('KIT_FIXTURES') ?: null,
];
