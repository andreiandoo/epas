<?php
/**
 * SITE CONFIG — Nordvale (TENANT profile, kind: leisure)
 *
 * Backed by the demo tenant created by NordvaleParcSeeder on core.tixello.com.
 * The `kind` supplies menu, terminology, features and URL patterns (see
 * kit/kinds/leisure.php); this file carries identity + brand only.
 *
 * The seeder prints the tenant id it created:
 *   php artisan db:seed --class=NordvaleParcSeeder
 * Put that number in `tenant_id` below. It must be a LITERAL — reading it from
 * the environment would resolve to 0 on the web server, where the variable does
 * not exist, and every API call would silently return nothing. tools/build.php
 * refuses to build until this is set, which is the point.
 */
return [
    'kind'      => 'leisure',
    'api_base'  => 'https://core.tixello.com/api',
    'core_url'  => 'https://core.tixello.com',
    'tenant_id' => 36,

    'site_name' => 'Nordvale',
    'site_city' => 'ZĂRNEȘTI',
    'logo_text' => 'NV',
    'site_url'  => 'https://parc.tixello.ro',
    'locale'    => 'ro',
    'locales'   => ['ro', 'en'],
    'currency'  => 'RON',

    'description' => 'Rezervație naturală de 400 de hectare: pădure, lac glaciar, '
        . 'trasee marcate și experiențe ghidate. Rezervi online, pe zi și pe interval.',
    'organization_type' => 'TouristAttraction',
    'social' => [
        'facebook'  => 'https://facebook.com/nordvale',
        'instagram' => 'https://instagram.com/nordvale',
    ],

    // The leisure kind's menu, re-labelled to the Nordvale voice.
    'menu' => [
        ['key' => 'activities', 'label' => 'Experiențe',    'url' => '/activitati'],
        ['key' => 'rentals',    'label' => 'Închirieri',    'url' => '/inchirieri'],
        ['key' => 'about',      'label' => 'Rezervația',    'url' => '/despre'],
        ['key' => 'giftcards',  'label' => 'Carduri cadou', 'url' => '/carduri-cadou'],
    ],
    'cta_label' => 'Rezervă vizita',
    'cta_url'   => '/activitati',

    'fonts_href'  => 'https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700'
        . '&family=Fraunces:opsz,wght@9..144,500;9..144,600;9..144,700&display=swap',
    'tokens_href' => '/theme/tokens.css',
    'theme_href'  => '/theme/theme.css',
    'kit_js_href' => '/kit/kit.js',

    'cache_ttl' => 120,
    'debug'     => false,
    'fixtures'  => null,   // live API; export KIT_FIXTURES=<dir> for offline preview

    'deploy_branch'  => 'parc',
    'deploy_webhook' => 'https://parc.tixello.ro/_webhook-deploy.php',
];
