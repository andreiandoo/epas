<?php
/**
 * viaqui.com — Homepage (v2 design).
 * Path: /
 *
 * Shared shell (head, header, footer, menu data): includes/v2. Homepage sections: includes/v2/home.
 */
// nginx (Ploi) sends every URL that is not a file here; the router applies the .htaccess rules and returns only for "/".
require __DIR__ . '/includes/router.php';

require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/api.php';

// 5-minute full-page cache: on a hit the render is skipped entirely (no API calls).
$pageCacheTTL = 300;
require_once __DIR__ . '/includes/page-cache.php';

require_once __DIR__ . '/includes/nav-helpers.php';
require_once __DIR__ . '/includes/v2/helpers.php';
require_once __DIR__ . '/includes/v2/nav.php';
require_once __DIR__ . '/includes/v2/home/data.php';

// An API outage would otherwise be cached as a half-empty page for five minutes.
if (empty($V2['categories'])) {
    $skipPageCache = true;
}

$pageTitleRaw = 'Viaqui · Tickets for attractions, museums, tours and experiences';
$pageDescription = 'Tickets for attractions, museums, castles, parks and experiences. Pick a day, pay securely and walk in with the QR code on your phone.';
$canonicalUrl = SITE_URL . '/';
$structuredData = [[
    '@context' => 'https://schema.org', '@type' => 'FAQPage',
    'mainEntity' => array_map(function ($f) {
        return ['@type' => 'Question', 'name' => $f[0], 'acceptedAnswer' => ['@type' => 'Answer', 'text' => $f[1]]];
    }, V2_FAQ),
]];

$v2Styles = ['home.css'];
$v2Scripts = ['home.js'];
$v2HeaderOverlay = true;
// No hero preload: the hero photo is picked at random in the page (includes/v2/home/sections.php).
$v2ClientData = [
    'libs' => [v2_asset('vendor/gsap-3.15.0.min.js'), v2_asset('vendor/ScrollTrigger-3.15.0.min.js'), v2_asset('vendor/lenis-1.3.26.min.js')],
    'cities' => $V2['suggest']['cities'],
    'attractions' => $V2['suggest']['attractions'],
    'categories' => $V2['suggest']['categories'],
];

include __DIR__ . '/includes/v2/head.php';
include __DIR__ . '/includes/v2/header.php';
include __DIR__ . '/includes/v2/home/sections.php';
include __DIR__ . '/includes/v2/footer.php';
