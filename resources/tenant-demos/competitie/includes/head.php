<?php
/**
 * <head> comun + header-ul site-ului.
 * Setează înainte de include:
 *   $pageTitle       — <title>
 *   $pageDescription — meta description (opțional)
 *   $pageImage       — imagine Open Graph (opțional)
 *   $activeNav       — 'home' | 'events' (opțional)
 *   $bodyClass       — clase pe <body> (opțional; 'is-light' pentru paginile de comerț)
 *   $headSolid       — true = header opac de la început (pagini fără antet închis la culoare)
 *   $pageExtraHead   — HTML brut la finalul <head> (opțional)
 */
$pageTitle       = $pageTitle       ?? (SITE_TAGLINE . ' — ' . SITE_NAME);
$pageDescription = $pageDescription ?? 'Bilete online la competițiile organizate de Federația Română de Karate WUKF: cupe și campionate naționale de kata, kumite și kobudo.';
$pageImage       = $pageImage       ?? ('https://' . TENANT_HOST . '/assets/logo-wukf.png');
$activeNav       = $activeNav       ?? '';
$bodyClass       = $bodyClass       ?? '';
$headSolid       = $headSolid       ?? false;
?>
<!DOCTYPE html>
<html lang="ro">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <title><?= e($pageTitle) ?></title>
    <meta name="description" content="<?= e($pageDescription) ?>">
    <meta property="og:type" content="website">
    <meta property="og:site_name" content="<?= e(SITE_NAME) ?>">
    <meta property="og:title" content="<?= e($pageTitle) ?>">
    <meta property="og:description" content="<?= e($pageDescription) ?>">
    <meta property="og:image" content="<?= e($pageImage) ?>">
    <meta name="theme-color" content="#01012F">
    <link rel="icon" type="image/svg+xml" href="/favicon.svg">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="preconnect" href="https://cdn.jsdelivr.net" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Barlow+Condensed:wght@600;700;800&family=Barlow:wght@400;500;600;700&family=JetBrains+Mono:wght@500;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="/assets/site.css?v=<?= ASSET_V ?>">
    <script>window.WUKF_CFG = <?= json_encode(['api' => API_BASE, 'host' => TENANT_HOST], JSON_UNESCAPED_SLASHES) ?>;</script>
    <script>
        // Consimțământ: totul refuzat până alege vizitatorul; alegerea salvată se aplică înainte de orice script de măsurare
        window.dataLayer = window.dataLayer || [];
        function gtag() { dataLayer.push(arguments); }
        (function () {
            var c = null;
            try { c = JSON.parse(localStorage.getItem('wukf_consent') || 'null'); } catch (e) {}
            var yes = function (on) { return on ? 'granted' : 'denied'; };
            gtag('consent', 'default', {
                ad_storage: yes(c && c.marketing), ad_user_data: yes(c && c.marketing), ad_personalization: yes(c && c.marketing),
                analytics_storage: yes(c && c.analytics), functionality_storage: 'granted', security_storage: 'granted'
            });
        })();
    </script>
    <script src="/assets/site.js?v=<?= ASSET_V ?>"></script>
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.14.1/dist/cdn.min.js"></script>
    <script defer src="https://cdnjs.cloudflare.com/ajax/libs/gsap/3.12.5/gsap.min.js"></script>
    <script defer src="https://cdnjs.cloudflare.com/ajax/libs/gsap/3.12.5/ScrollTrigger.min.js"></script>
    <script defer src="https://cdn.jsdelivr.net/npm/lenis@1.1.14/dist/lenis.min.js"></script>
    <script defer src="https://cdn.jsdelivr.net/npm/motion@11.11.13/dist/motion.js"></script>
    <script defer src="/assets/fx.js?v=<?= ASSET_V ?>"></script>
    <?= $pageExtraHead ?? '' ?>
</head>
<body class="<?= e($bodyClass) ?>">
<div class="progress belt-bg" aria-hidden="true"></div>
<header class="site-head<?= $headSolid ? ' is-solid' : '' ?>" x-data="siteHead" :class="open && 'is-open'"<?= $headSolid ? ' data-solid' : '' ?>>
    <div class="wrap">
        <div class="site-head__row">
            <a href="/" class="brand" aria-label="<?= e(SITE_NAME) ?> — acasă">
                <img src="/assets/logo-wukf.png" alt="" width="46" height="46">
                <span>
                    <span class="brand__name"><?= e(SITE_SHORT) ?></span>
                    <span class="brand__sub">Federația Română · Bilete</span>
                </span>
            </a>
            <nav class="nav" aria-label="Principal">
                <a href="/competitii" class="<?= $activeNav === 'events' ? 'is-on' : '' ?>">Competiții</a>
                <a href="<?= e(SITE_FEDERATION) ?>" target="_blank" rel="noopener">wukf.ro</a>
            </nav>
            <div class="head-tools">
                <a x-show="!user" href="/autentificare" class="head-user head-user--plain">Contul meu</a>
                <a x-show="user" x-cloak href="/biletele-mele" class="head-user" title="Biletele mele">
                    <span class="head-user__dot" x-text="initials"></span><span x-text="firstName"></span>
                </a>
                <a href="/cos" class="icon-btn" aria-label="Coșul meu">
                    <svg fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M6 7h12l1 13H5L6 7z"/><path stroke-linecap="round" stroke-linejoin="round" d="M9 10V6a3 3 0 016 0v4"/></svg>
                    <span class="icon-btn__count" x-show="count > 0" x-cloak x-text="count"></span>
                </a>
                <button type="button" class="icon-btn menu-btn" @click="open = !open" :aria-expanded="open" aria-label="Meniu">
                    <svg x-show="!open" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" d="M4 8h16M4 16h16"/></svg>
                    <svg x-show="open" x-cloak fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" d="M6 6l12 12M18 6L6 18"/></svg>
                </button>
            </div>
        </div>
    </div>
    <div class="drawer" x-show="open" x-cloak>
        <div class="wrap">
            <a href="/competitii">Competiții</a>
            <a href="/cos">Coșul meu</a>
            <a x-show="!user" href="/autentificare">Contul meu</a>
            <a x-show="user" href="/biletele-mele">Biletele mele</a>
            <a x-show="user" href="/comenzile-mele">Comenzile mele</a>
            <a x-show="user" href="/profil">Profilul meu</a>
            <button type="button" x-show="user" @click="logout()">Ieși din cont</button>
        </div>
    </div>
</header>
