<?php
/**
 * bilete.online — Homepage (v2 design).
 * Path: /
 *
 * Header, sections and footer live in includes/home-v2/. The other pages still use
 * includes/head.php + header.php + footer.php until each of them is redesigned.
 */
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/api.php';

// 5-minute full-page cache: on a hit the render is skipped entirely (no API calls).
$pageCacheTTL = 300;
require_once __DIR__ . '/includes/page-cache.php';

require_once __DIR__ . '/includes/nav-helpers.php';
require_once __DIR__ . '/includes/home-v2/helpers.php';
require_once __DIR__ . '/includes/home-v2/data.php';

// An API outage would otherwise be cached as a half-empty page for five minutes.
if (empty($HV2['categories'])) {
    $skipPageCache = true;
}

$pageTitleRaw = 'bilete.online · Bilete la atracții, muzee, parcuri și experiențe din România';
$pageDescription = 'Bilete la atracții, muzee, castele, parcuri și experiențe din toată România. Alegi ziua, plătești în siguranță și intri cu QR de pe telefon.';
$canonicalUrl = SITE_URL . '/';

include __DIR__ . '/includes/home-v2/head.php';
include __DIR__ . '/includes/home-v2/header.php';
include __DIR__ . '/includes/home-v2/sections.php';
include __DIR__ . '/includes/home-v2/footer.php';
