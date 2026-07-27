<?php
/**
 * SITE CONFIG — teatru (kind: teatru)
 *
 * With the `kind` layer, this file now carries ONLY identity + brand hooks.
 * Menu, terminology, features and URL patterns come from kit/kinds/teatru.php.
 * (This is exactly what makes "10 teatru templates" cheap: they share the kind
 * and differ only here + theme.css.)
 */
return [
    'kind'      => 'teatru',
    'api_base'  => 'https://core.tixello.com/api',
    'core_url'  => 'https://core.tixello.com',
    'tenant_id' => 17,
    'deploy_branch' => 'teatru', 'deploy_webhook' => 'https://teatru.tixello.ro/_webhook-deploy.php',

    'site_name' => 'Teatrul Național',
    'site_city' => 'BUCUREȘTI',
    'logo_text' => 'TN',
    'site_url'  => 'https://teatru.tixello.ro',
    'locale'    => 'ro',
    'currency'  => 'RON',

    'fonts_href' => 'https://fonts.googleapis.com/css2?family=Playfair+Display:wght@400;600;700&family=Lato:wght@300;400;600;700&display=swap',
    'tokens_href' => '/theme/tokens.css',
    'theme_href'  => '/theme/theme.css',
    'kit_js_href' => '/kit/kit.js',

    'cache_ttl' => 120,
    'debug'     => false,
    'fixtures'  => getenv('KIT_FIXTURES') ?: null,
];
