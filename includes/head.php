<?php
/**
 * bilete.online — <head> include
 *
 * SEO-optimized document head. Every page can override the variables below
 * before including this file. Sensible defaults are pulled from config.php.
 *
 * Page variables (all optional):
 *   $pageTitle        — page title segment (auto-appends " · bilete.online")
 *   $pageTitleRaw     — full title override (no suffix appended)
 *   $pageDescription  — meta description (auto-truncated to 160 chars)
 *   $pageKeywords     — comma-separated keywords (optional, low SEO weight)
 *   $canonicalUrl     — full canonical URL (auto-derived from REQUEST_URI)
 *   $ogImage          — full URL to OG/Twitter share image (1200x630 recommended)
 *   $ogType           — defaults to 'website'; use 'article', 'product', etc.
 *   $noindex          — true to add <meta robots="noindex">
 *   $hideFromSitemap  — true to also discourage following links
 *   $breadcrumbs      — array of ['name'=>..., 'url'=>...] for BreadcrumbList JSON-LD
 *   $structuredData   — array of additional JSON-LD blocks (each as PHP array)
 *   $cssBundle        — Tailwind bundle name (home|event|listing|...) — falls back to tailwind.min.css
 *   $extraHead        — raw HTML to inject just before </head>
 */

if (!defined('BILETEONLINE_ROOT')) {
    require_once __DIR__ . '/config.php';
}

// ============================================================
// DERIVED VALUES
// ============================================================
$_scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$_host = $_SERVER['HTTP_HOST'] ?? parse_url(SITE_URL, PHP_URL_HOST);
$_requestUri = $_SERVER['REQUEST_URI'] ?? '/';
$_currentUrl = $_scheme . '://' . $_host . $_requestUri;

$pageTitle = $pageTitle ?? null;
$pageTitleRaw = $pageTitleRaw ?? null;
$pageDescription = $pageDescription ?? SITE_TAGLINE;
$pageKeywords = $pageKeywords ?? null;
$canonicalUrl = $canonicalUrl ?? (SITE_URL . strtok($_requestUri, '?'));
$ogImage = $ogImage ?? (SITE_URL . '/assets/images/og-default.jpg');
$ogType = $ogType ?? 'website';
$noindex = $noindex ?? false;
$hideFromSitemap = $hideFromSitemap ?? false;
$breadcrumbs = $breadcrumbs ?? [];
$structuredData = $structuredData ?? [];
$cssBundle = $cssBundle ?? null;
$extraHead = $extraHead ?? '';

// Title resolution
if ($pageTitleRaw) {
    $finalTitle = $pageTitleRaw;
} elseif ($pageTitle) {
    $finalTitle = $pageTitle . ' · ' . SITE_NAME;
} else {
    $finalTitle = SITE_NAME . ' — ' . SITE_TAGLINE;
}

// Description trim (search engines truncate around 155-160 chars)
$finalDescription = mb_substr(trim($pageDescription), 0, 160);

// Robots
$robotsValue = 'index, follow, max-image-preview:large, max-snippet:-1';
if ($noindex) {
    $robotsValue = $hideFromSitemap ? 'noindex, nofollow' : 'noindex, follow';
}

// CSS bundle to load (built by deploy script). Fallback chain:
//   1) per-page bundle (assets/css/bundles/{name}.css)
//   2) combined styles.css (deploy script concatenates custom + tailwind)
//   3) raw tailwind.min.css + custom.css separately
$cssLink = null;
if ($cssBundle && file_exists(BILETEONLINE_ROOT . '/assets/css/bundles/' . $cssBundle . '.css')) {
    $cssLink = '/assets/css/bundles/' . $cssBundle . '.css';
} elseif (file_exists(BILETEONLINE_ROOT . '/assets/css/styles.css')) {
    $cssLink = '/assets/css/styles.css';
}

// JS bridge — surfaces PHP config as window.BILETEONLINE for client code
$jsConfig = [
    'siteName' => SITE_NAME,
    'siteUrl' => SITE_URL,
    'apiUrl' => '/api/proxy.php',
    'storageUrl' => STORAGE_URL,
    'env' => API_ENV,
    'locale' => SITE_LOCALE,
    'currency' => 'RON',
    'supportEmail' => defined('SUPPORT_EMAIL') ? SUPPORT_EMAIL : '',
];
?><!DOCTYPE html>
<html lang="<?= htmlspecialchars(SITE_LOCALE, ENT_QUOTES) ?>">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
<meta http-equiv="X-UA-Compatible" content="IE=edge">

<!-- ===================== CORE SEO ===================== -->
<title><?= htmlspecialchars($finalTitle, ENT_QUOTES) ?></title>
<meta name="description" content="<?= htmlspecialchars($finalDescription, ENT_QUOTES) ?>">
<?php if ($pageKeywords): ?>
<meta name="keywords" content="<?= htmlspecialchars($pageKeywords, ENT_QUOTES) ?>">
<?php endif; ?>
<link rel="canonical" href="<?= htmlspecialchars($canonicalUrl, ENT_QUOTES) ?>">
<meta name="robots" content="<?= htmlspecialchars($robotsValue, ENT_QUOTES) ?>">
<meta name="googlebot" content="<?= htmlspecialchars($robotsValue, ENT_QUOTES) ?>">
<meta name="bingbot" content="<?= htmlspecialchars($robotsValue, ENT_QUOTES) ?>">

<!-- Locale & alternates -->
<meta http-equiv="content-language" content="<?= htmlspecialchars(SITE_LOCALE, ENT_QUOTES) ?>">
<link rel="alternate" hreflang="ro-RO" href="<?= htmlspecialchars($canonicalUrl, ENT_QUOTES) ?>">
<link rel="alternate" hreflang="x-default" href="<?= htmlspecialchars($canonicalUrl, ENT_QUOTES) ?>">

<!-- Theme & app integration -->
<meta name="theme-color" content="#1B1714">
<meta name="msapplication-TileColor" content="#1B1714">
<meta name="application-name" content="<?= htmlspecialchars(SITE_NAME, ENT_QUOTES) ?>">
<meta name="apple-mobile-web-app-title" content="<?= htmlspecialchars(SITE_NAME, ENT_QUOTES) ?>">
<meta name="apple-mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
<meta name="mobile-web-app-capable" content="yes">
<meta name="format-detection" content="telephone=no">
<meta name="referrer" content="strict-origin-when-cross-origin">

<!-- ===================== OPEN GRAPH ===================== -->
<meta property="og:type" content="<?= htmlspecialchars($ogType, ENT_QUOTES) ?>">
<meta property="og:site_name" content="<?= htmlspecialchars(SITE_NAME, ENT_QUOTES) ?>">
<meta property="og:title" content="<?= htmlspecialchars($pageTitle ?? SITE_NAME, ENT_QUOTES) ?>">
<meta property="og:description" content="<?= htmlspecialchars($finalDescription, ENT_QUOTES) ?>">
<meta property="og:url" content="<?= htmlspecialchars($canonicalUrl, ENT_QUOTES) ?>">
<meta property="og:locale" content="ro_RO">
<meta property="og:image" content="<?= htmlspecialchars($ogImage, ENT_QUOTES) ?>">
<meta property="og:image:width" content="1200">
<meta property="og:image:height" content="630">
<meta property="og:image:alt" content="<?= htmlspecialchars($pageTitle ?? SITE_NAME, ENT_QUOTES) ?>">

<!-- ===================== TWITTER CARD ===================== -->
<meta name="twitter:card" content="summary_large_image">
<meta name="twitter:title" content="<?= htmlspecialchars($pageTitle ?? SITE_NAME, ENT_QUOTES) ?>">
<meta name="twitter:description" content="<?= htmlspecialchars($finalDescription, ENT_QUOTES) ?>">
<meta name="twitter:image" content="<?= htmlspecialchars($ogImage, ENT_QUOTES) ?>">

<!-- ===================== FAVICONS & PWA ===================== -->
<link rel="icon" type="image/svg+xml" href="/assets/images/favicon.svg">
<link rel="icon" type="image/png" sizes="32x32" href="/assets/images/favicon-32.png">
<link rel="icon" type="image/png" sizes="16x16" href="/assets/images/favicon-16.png">
<link rel="apple-touch-icon" sizes="180x180" href="/assets/images/apple-touch-icon.png">
<link rel="manifest" href="/site.webmanifest">
<meta name="msapplication-config" content="/browserconfig.xml">

<!-- ===================== RESOURCE HINTS ===================== -->
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link rel="preconnect" href="<?= htmlspecialchars(CORE_URL, ENT_QUOTES) ?>" crossorigin>
<link rel="preconnect" href="<?= htmlspecialchars(STORAGE_URL, ENT_QUOTES) ?>" crossorigin>
<link rel="dns-prefetch" href="//fonts.googleapis.com">
<link rel="dns-prefetch" href="//fonts.gstatic.com">

<?php
// Self-hosted Alpine + collapse (saves the cdn.jsdelivr.net DNS + TLS
// handshake on cold visits). Falls back to the CDN if the local files
// haven't been deployed yet so the site never breaks during rollout.
$alpineLocalCore     = BILETEONLINE_ROOT . '/assets/js/vendor/alpine-3.min.js';
$alpineLocalCollapse = BILETEONLINE_ROOT . '/assets/js/vendor/alpine-collapse-3.min.js';
$alpineCoreUrl     = file_exists($alpineLocalCore)
    ? asset('assets/js/vendor/alpine-3.min.js') . '?v=' . filemtime($alpineLocalCore)
    : 'https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js';
$alpineCollapseUrl = file_exists($alpineLocalCollapse)
    ? asset('assets/js/vendor/alpine-collapse-3.min.js') . '?v=' . filemtime($alpineLocalCollapse)
    : 'https://cdn.jsdelivr.net/npm/@alpinejs/collapse@3.x.x/dist/cdn.min.js';
?>

<!-- Preload Alpine + collapse — local copy when deployed, CDN as fallback. -->
<link rel="preload" as="script" href="<?= htmlspecialchars($alpineCoreUrl, ENT_QUOTES) ?>">
<link rel="preload" as="script" href="<?= htmlspecialchars($alpineCollapseUrl, ENT_QUOTES) ?>">

<!-- ===================== FONTS ===================== -->
<link rel="preload" href="https://fonts.googleapis.com/css2?family=Fraunces:ital,opsz,wght@0,9..144,400;0,9..144,500;0,9..144,600;0,9..144,700;0,9..144,900;1,9..144,500&family=Hanken+Grotesk:wght@400;500;600;700&family=Spline+Sans+Mono:wght@400;500;600&display=swap" as="style">
<link href="https://fonts.googleapis.com/css2?family=Fraunces:ital,opsz,wght@0,9..144,400;0,9..144,500;0,9..144,600;0,9..144,700;0,9..144,900;1,9..144,500&family=Hanken+Grotesk:wght@400;500;600;700&family=Spline+Sans+Mono:wght@400;500;600&display=swap" rel="stylesheet">

<!-- ===================== STYLES ===================== -->
<?php if ($cssLink): ?>
<link rel="stylesheet" href="<?= htmlspecialchars($cssLink, ENT_QUOTES) ?><?= file_exists(BILETEONLINE_ROOT . $cssLink) ? '?v=' . filemtime(BILETEONLINE_ROOT . $cssLink) : '' ?>">
<?php else: ?>
<link rel="stylesheet" href="<?= asset('assets/css/custom.css') ?>">
<link rel="stylesheet" href="<?= asset('assets/css/tailwind.min.css') ?>">
<?php endif; ?>

<!-- ===================== STRUCTURED DATA ===================== -->
<script type="application/ld+json">
{
    "@context": "https://schema.org",
    "@type": "Organization",
    "name": "<?= addslashes(SITE_NAME) ?>",
    "url": "<?= addslashes(SITE_URL) ?>",
    "logo": "<?= addslashes(SITE_URL) ?>/assets/images/bileteonline-logo.webp",
    "sameAs": [],
    "contactPoint": {
        "@type": "ContactPoint",
        "email": "<?= addslashes(defined('SUPPORT_EMAIL') ? SUPPORT_EMAIL : '') ?>",
        "contactType": "customer support",
        "areaServed": "RO",
        "availableLanguage": ["Romanian"]
    }
}
</script>
<script type="application/ld+json">
{
    "@context": "https://schema.org",
    "@type": "WebSite",
    "name": "<?= addslashes(SITE_NAME) ?>",
    "url": "<?= addslashes(SITE_URL) ?>",
    "inLanguage": "ro-RO",
    "publisher": {"@type": "Organization", "name": "Tixello", "url": "https://tixello.ro/"},
    "potentialAction": {
        "@type": "SearchAction",
        "target": {"@type": "EntryPoint", "urlTemplate": "<?= addslashes(SITE_URL) ?>/cauta?q={search_term_string}"},
        "query-input": "required name=search_term_string"
    }
}
</script>
<?php if (!empty($breadcrumbs)): ?>
<script type="application/ld+json">
{
    "@context": "https://schema.org",
    "@type": "BreadcrumbList",
    "itemListElement": [
<?php
$bcItems = [];
foreach ($breadcrumbs as $i => $bc) {
    $bcItems[] = sprintf(
        '        {"@type": "ListItem", "position": %d, "name": "%s", "item": "%s"}',
        $i + 1,
        addslashes($bc['name']),
        addslashes($bc['url'] ?? '')
    );
}
echo implode(",\n", $bcItems) . "\n";
?>
    ]
}
</script>
<?php endif; ?>
<?php foreach ($structuredData as $jsonLd): ?>
<script type="application/ld+json"><?= json_encode($jsonLd, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) ?></script>
<?php endforeach; ?>

<!-- ===================== CLIENT CONFIG ===================== -->
<script>
window.BILETEONLINE = <?= json_encode($jsConfig, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?>;
</script>

<!-- ===================== CORE GLOBALS (must come BEFORE Alpine) =====================
     BileteOnlineAuth / BileteOnlineAPI / BileteOnlineUtils need to be on
     window by the time Alpine fires `alpine:init`, so x-init / init() on
     every component can read the current auth state without racing.

     We prefer a SINGLE concatenated bundle (config + utils + api + auth)
     — saves 3 HTTP round-trips on cold visits. The bundle is auto-built
     by head.php itself: if it's missing or older than ANY of the 4
     sources, we concatenate fresh. That keeps it always in sync without
     a separate build step. Falls back to the 4 individual scripts if the
     concat ever fails (defensive, should never trigger). -->
<?php
$jsDir          = BILETEONLINE_ROOT . '/assets/js';
$coreSources    = [
    $jsDir . '/config.js',
    $jsDir . '/utils.js',
    $jsDir . '/api.js',
    $jsDir . '/auth.js',
];
$coreBundlePath = $jsDir . '/core-bundle.js';
$useCoreBundle  = false;

if (count(array_filter($coreSources, 'file_exists')) === count($coreSources)) {
    $needsRebuild = ! file_exists($coreBundlePath)
        || max(array_map('filemtime', $coreSources)) > filemtime($coreBundlePath);
    if ($needsRebuild) {
        $concat = '';
        foreach ($coreSources as $src) {
            $concat .= "/* === " . basename($src) . " === */\n" . file_get_contents($src) . "\n\n";
        }
        @file_put_contents($coreBundlePath, $concat, LOCK_EX);
    }
    $useCoreBundle = file_exists($coreBundlePath);
}
?>
<?php if ($useCoreBundle): ?>
<script defer src="<?= asset('assets/js/core-bundle.js') ?>?v=<?= filemtime($coreBundlePath) ?>"></script>
<?php else: ?>
<script defer src="<?= asset('assets/js/config.js') ?>"></script>
<script defer src="<?= asset('assets/js/utils.js') ?>"></script>
<script defer src="<?= asset('assets/js/api.js') ?>"></script>
<script defer src="<?= asset('assets/js/auth.js') ?>"></script>
<?php endif; ?>

<!-- ===================== ALPINE.JS (deferred, in load order) ===================== -->
<script defer src="<?= asset('assets/js/components/alpine-bootstrap.js') ?>"></script>
<script defer src="<?= htmlspecialchars($alpineCollapseUrl, ENT_QUOTES) ?>"></script>
<script defer src="<?= htmlspecialchars($alpineCoreUrl, ENT_QUOTES) ?>"></script>

<?php echo $extraHead; ?>
</head>
