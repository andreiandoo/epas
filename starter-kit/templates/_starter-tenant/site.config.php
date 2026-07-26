<?php
/**
 * SITE CONFIG — __SITE_NAME__ (TENANT profile, kind: __KIND__)
 *
 * The `kind` supplies menu, terminology, features and URL patterns (see
 * kit/kinds/__KIND__.php). Here you only set IDENTITY + brand hooks. Uncomment
 * any inherited key below to override it for this specific site.
 */
return [
    'kind'      => '__KIND__',   // teatru | filarmonica | agentie | leisure | artist | organizator
    'api_base'  => 'https://core.tixello.com/api',
    'core_url'  => 'https://core.tixello.com',
    'tenant_id' => 0, // TODO: this tenant's ID in Tixello

    'site_name' => '__SITE_NAME__',
    'site_city' => '',           // TODO
    'logo_text' => '',           // TODO (2-3 letters)
    'site_url'  => 'https://__SLUG__.tixello.ro',
    'locale'    => 'ro',
    'currency'  => 'RON',

    // Inherited from the kind — uncomment to override:
    // 'menu' => [ ... ],
    // 'event_url_pattern' => '/…/{slug}',
    // 'cta_label' => '…', 'cta_url' => '/…',

    'fonts_href'  => '', // optional Google Fonts URL for this theme
    'tokens_href' => '/theme/tokens.css',
    'theme_href'  => '/theme/theme.css',
    'kit_js_href' => '/kit/kit.js',

    'cache_ttl' => 120,
    'debug'     => false,
    'fixtures'  => getenv('KIT_FIXTURES') ?: null,
];
