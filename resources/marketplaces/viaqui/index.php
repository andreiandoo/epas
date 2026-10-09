<?php
/**
 * Viaqui — Homepage (v2 design).
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

// While the page runs on the starter catalogue (empty database, or the API did not answer), it is not cached,
// so real data shows up the moment it exists.
if (!empty($V2['seed'])) {
    $skipPageCache = true;
}

$pageTitleRaw = v2_t('Viaqui · Tickets for attractions, museums, tours and experiences');
$pageDescription = v2_t('Tickets for attractions, museums, castles, parks and experiences. Pick a day, pay securely and walk in with the QR code on your phone.');
$canonicalUrl = SITE_URL . '/';
$ogImage = SITE_URL . '/assets/v2/img/hero-bran-1440.webp';
$structuredData = [[
    '@context' => 'https://schema.org', '@type' => 'FAQPage',
    'mainEntity' => array_map(function ($f) {
        return ['@type' => 'Question', 'name' => $f[0], 'acceptedAnswer' => ['@type' => 'Answer', 'text' => $f[1]]];
    }, v2_home_faq()),
]];

$v2Styles = ['home.css'];
$v2InlineCss = true;      // the first paint of the landing page does not wait for a stylesheet request
$v2Scripts = ['home.js'];
$v2HeaderOverlay = true;
$v2HeadExtra = '<link rel="preload" as="image" href="' . v2_asset('img/hero-bran-900.webp') . '" imagesrcset="' . v2_home_hero_srcset('bran') . '" imagesizes="(min-width:1024px) 40vw, 90vw" fetchpriority="high">';
$v2ClientData = [
    'libs' => [
        'gsap' => v2_asset('vendor/gsap-3.15.0.min.js'), 'scrollTrigger' => v2_asset('vendor/ScrollTrigger-3.15.0.min.js'),
        'lenis' => v2_asset('vendor/lenis-1.3.26.min.js'), 'motion' => v2_asset('vendor/motion-11.11.17.min.js'),
        'three' => v2_asset('vendor/three-0.160.0.min.js'),
    ],
    'cities' => $V2['suggest']['cities'],
    'categories' => $V2['suggest']['categories'],
];

include __DIR__ . '/includes/v2/head.php';
include __DIR__ . '/includes/v2/header.php';
include __DIR__ . '/includes/v2/home/sections.php';
include __DIR__ . '/includes/v2/footer.php';
