<?php
/**
 * bilete.online — homepage v2, preview.
 * Path: /homepage-nou.php (noindex). The live homepage (index.php) is untouched; switching
 * over later means rendering these same partials from index.php.
 */
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/api.php';

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
$noindex = true;

include __DIR__ . '/includes/home-v2/head.php';
include __DIR__ . '/includes/home-v2/header.php';
include __DIR__ . '/includes/home-v2/sections.php';
include __DIR__ . '/includes/home-v2/footer.php';
