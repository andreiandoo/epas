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
<link rel="manifest" href="/site.webmanifest">

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

<!-- ===================== FONTS (non-render-blocking: preload + print swap) ===================== -->
<?php $fontsUrl = 'https://fonts.googleapis.com/css2?family=Fraunces:ital,opsz,wght@0,9..144,400;0,9..144,500;0,9..144,600;0,9..144,700;0,9..144,900;1,9..144,500&family=Hanken+Grotesk:wght@400;500;600;700&family=Spline+Sans+Mono:wght@400;500;600&display=swap'; ?>
<link rel="preload" as="style" href="<?= $fontsUrl ?>">
<link href="<?= $fontsUrl ?>" rel="stylesheet" media="print" onload="this.media='all'">
<noscript><link href="<?= $fontsUrl ?>" rel="stylesheet"></noscript>

<!-- ===================== CRITICAL CSS (inline above-the-fold) ===================== -->
<style>
:root{--color-paper:#F5EFE6;--color-ink:#1B1714;--color-ink-soft:#5A4F46;--color-accent:#C2410C;--color-line:#E8DFCF}*,::after,::before{box-sizing:border-box;border:0 solid #E8DFCF;margin:0;padding:0}html{line-height:1.5;-webkit-text-size-adjust:100%;font-family:'Hanken Grotesk','Inter',system-ui,sans-serif;scroll-behavior:smooth;background:#F5EFE6;color:#1B1714}body{margin:0;line-height:inherit;background:#F5EFE6;color:#1B1714;-webkit-font-smoothing:antialiased;min-height:100vh}img,video{max-width:100%;height:auto;display:block}a{color:inherit;text-decoration:inherit}button,[role=button]{cursor:pointer;background:transparent}h1,h2,h3,h4,h5,h6{font-family:'Fraunces',Georgia,serif;font-size:inherit;font-weight:inherit}ul,ol,menu{list-style:none;margin:0;padding:0}.hidden{display:none}[x-cloak]{display:none!important}.flex{display:flex}.block{display:block}.inline-block{display:inline-block}.relative{position:relative}.absolute{position:absolute}.fixed{position:fixed}.sticky{position:sticky}.items-center{align-items:center}.justify-between{justify-content:space-between}.justify-center{justify-content:center}.gap-2{gap:.5rem}.gap-4{gap:1rem}.gap-6{gap:1.5rem}.w-full{width:100%}.h-full{height:100%}.min-h-screen{min-height:100vh}.overflow-hidden{overflow:hidden}.bg-paper{background-color:#F5EFE6}.bg-ink{background-color:#1B1714}.text-paper{color:#F5EFE6}.text-ink{color:#1B1714}.text-accent{color:#C2410C}.font-bold{font-weight:700}.font-semibold{font-weight:600}.font-medium{font-weight:500}.text-sm{font-size:.875rem;line-height:1.25rem}.text-xs{font-size:.75rem;line-height:1rem}.text-lg{font-size:1.125rem;line-height:1.75rem}.text-xl{font-size:1.25rem;line-height:1.75rem}.text-2xl{font-size:1.5rem;line-height:2rem}.rounded-xl{border-radius:.75rem}.rounded-lg{border-radius:.5rem}.rounded-full{border-radius:9999px}.p-4{padding:1rem}.px-4{padding-left:1rem;padding-right:1rem}.px-6{padding-left:1.5rem;padding-right:1.5rem}.py-2{padding-top:.5rem;padding-bottom:.5rem}.py-3{padding-top:.75rem;padding-bottom:.75rem}.py-4{padding-top:1rem;padding-bottom:1rem}.mb-2{margin-bottom:.5rem}.mb-4{margin-bottom:1rem}.mb-6{margin-bottom:1.5rem}.mt-7{margin-top:1.75rem}.mx-auto{margin-left:auto;margin-right:auto}.max-w-2xl{max-width:42rem}.max-w-7xl{max-width:80rem}.border{border-width:1px}.border-line{border-color:#E8DFCF}.shadow-sm{box-shadow:0 1px 2px 0 rgba(27,23,20,.06)}.transition-all{transition-property:all;transition-timing-function:cubic-bezier(.4,0,.2,1);transition-duration:.15s}.opacity-0{opacity:0}.inset-0{inset:0}.top-0{top:0}.object-cover{object-fit:cover}.container{width:100%;margin-left:auto;margin-right:auto;padding-left:1rem;padding-right:1rem}@media(min-width:768px){.container{max-width:768px}.md\:flex{display:flex}}@media(min-width:1024px){.container{max-width:1024px}.lg\:flex{display:flex}.lg\:hidden{display:none}}@media(min-width:1280px){.container{max-width:1280px}}.leading-relaxed{line-height:1.625}
</style>

<!-- ===================== STYLES (preload + render-blocking to avoid FOUC) ===================== -->
<?php if ($cssLink): ?>
<link rel="preload" as="style" href="<?= htmlspecialchars($cssLink, ENT_QUOTES) ?><?= file_exists(BILETEONLINE_ROOT . $cssLink) ? '?v=' . filemtime(BILETEONLINE_ROOT . $cssLink) : '' ?>">
<link rel="stylesheet" href="<?= htmlspecialchars($cssLink, ENT_QUOTES) ?><?= file_exists(BILETEONLINE_ROOT . $cssLink) ? '?v=' . filemtime(BILETEONLINE_ROOT . $cssLink) : '' ?>">
<?php else: ?>
<link rel="stylesheet" href="<?= asset('assets/css/custom.css') ?>">
<link rel="stylesheet" href="<?= asset('assets/css/tailwind.min.css') ?>">
<?php endif; ?>

<!-- ===================== GOOGLE CONSENT MODE v2 — MUST be before tracking ===================== -->
<script>
window.dataLayer=window.dataLayer||[];function gtag(){dataLayer.push(arguments);}
(function(){
    var c=null;
    try{c=JSON.parse(localStorage.getItem('bileteonline_cookie_consent'))}catch(e){}
    var m=c&&c.marketing,a=c&&c.analytics,f=c&&c.functional;
    gtag('consent','default',{
        'ad_storage':m?'granted':'denied',
        'ad_user_data':m?'granted':'denied',
        'ad_personalization':m?'granted':'denied',
        'analytics_storage':a?'granted':'denied',
        'functionality_storage':f?'granted':'denied',
        'personalization_storage':f?'granted':'denied',
        'security_storage':'granted',
        'wait_for_update':c?0:500
    });
    gtag('set','ads_data_redaction',true);
    gtag('set','url_passthrough',true);
})();
</script>

<!-- ===================== TRACKING SCRIPTS (head) — deferred until user interaction or 7s idle ===================== -->
<?php
if (!isset($trackingHeadScripts)) {
    require_once __DIR__ . '/tracking.php';
}
if (!empty($trackingHeadScripts)): ?>
<script>
(function(){
    var h=<?= json_encode($trackingHeadScripts) ?>;
    var done=false;
    function go(){
        if(done)return;done=true;
        var d=document.createElement('div');d.innerHTML=h;
        d.querySelectorAll('script').forEach(function(s){
            var n=document.createElement('script');
            if(s.src){n.src=s.src;n.async=true}else{n.textContent=s.textContent}
            document.head.appendChild(n);
        });
    }
    ['scroll','click','touchstart','mousemove','keydown'].forEach(function(e){
        window.addEventListener(e,go,{once:true,passive:true});
    });
    if('requestIdleCallback' in window){requestIdleCallback(function(){setTimeout(go,7000)});}
    else{setTimeout(go,10000);}
})();
</script>
<?php endif; ?>

<!-- Clean GA / UTM params from URL after GA has read them (15s after load) -->
<script>
setTimeout(function(){
    if(!window.location.search)return;
    var p=new URLSearchParams(window.location.search),del=[];
    p.forEach(function(v,k){if(/^(_gl|_ga|_up|_gac|utm_)/.test(k))del.push(k)});
    if(del.length){
        del.forEach(function(k){p.delete(k)});
        var s=p.toString();
        history.replaceState(null,'',location.pathname+(s?'?'+s:'')+location.hash);
    }
},15000);
</script>

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

<!-- GetYourGuide Analytics -->
<script async defer src="https://widget.getyourguide.com/dist/pa.umd.production.min.js" data-gyg-partner-id="HF2XYCH"></script>
</head>
