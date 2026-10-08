<?php
/**
 * <head> comun + header-ul site-ului.
 * Setează înainte de include:
 *   $pageTitle       — <title>
 *   $pageDescription — meta description (opțional)
 *   $pageImage       — imagine Open Graph (opțional)
 *   $activeNav       — 'home' | 'events' (opțional)
 *   $bodyClass       — clase pe <body> (opțional)
 */
$pageTitle       = $pageTitle       ?? (SITE_TAGLINE . ' — ' . SITE_NAME);
$pageDescription = $pageDescription ?? 'Bilete online la competițiile organizate de Federația Română de Karate WUKF: cupe și campionate naționale de kata, kumite și kobudo.';
$pageImage       = $pageImage       ?? ('https://' . TENANT_HOST . '/assets/logo-wukf.png');
$activeNav       = $activeNav       ?? '';
$bodyClass       = $bodyClass       ?? '';
?>
<!DOCTYPE html>
<html lang="ro">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($pageTitle) ?></title>
    <meta name="description" content="<?= e($pageDescription) ?>">
    <meta property="og:type" content="website">
    <meta property="og:site_name" content="<?= e(SITE_NAME) ?>">
    <meta property="og:title" content="<?= e($pageTitle) ?>">
    <meta property="og:description" content="<?= e($pageDescription) ?>">
    <meta property="og:image" content="<?= e($pageImage) ?>">
    <meta name="theme-color" content="#0E0F11">
    <link rel="icon" type="image/svg+xml" href="/favicon.svg">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Big+Shoulders+Display:wght@700;800;900&family=Instrument+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="/assets/site.css?v=<?= ASSET_V ?>">
    <script src="/assets/site.js?v=<?= ASSET_V ?>"></script>
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.14.1/dist/cdn.min.js"></script>
    <?= $pageExtraHead ?? '' ?>
</head>
<body class="<?= e($bodyClass) ?>">
<header class="site-head" x-data="siteHead">
    <div class="belt"></div>
    <div class="wrap">
        <div class="site-head__row">
            <a href="/" class="brand" aria-label="<?= e(SITE_NAME) ?> — acasă">
                <img src="/assets/logo-wukf.png" alt="" width="44" height="44">
                <span>
                    <span class="brand__name"><?= e(SITE_SHORT) ?></span>
                    <span class="brand__sub">Federația Română · Bilete</span>
                </span>
            </a>
            <nav class="nav" aria-label="Principal">
                <a href="/" class="<?= $activeNav === 'home' ? 'is-on' : '' ?>">Acasă</a>
                <a href="/competitii" class="<?= $activeNav === 'events' ? 'is-on' : '' ?>">Competiții</a>
                <a href="<?= e(SITE_FEDERATION) ?>" target="_blank" rel="noopener">wukf.ro</a>
            </nav>
            <div class="head-tools">
                <a x-show="!user" href="/autentificare" class="head-user">Contul meu</a>
                <a x-show="user" x-cloak href="/biletele-mele" class="head-user" title="Biletele mele">
                    <span class="head-user__dot" x-text="initials"></span><span x-text="firstName"></span>
                </a>
                <a href="/cos" class="icon-btn" aria-label="Coșul meu">
                    <svg fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-2.3 2.3c-.6.6-.2 1.7.7 1.7H17m0 0a2 2 0 100 4 2 2 0 000-4zm-8 2a2 2 0 11-4 0 2 2 0 014 0z"/></svg>
                    <span class="icon-btn__count" x-show="count > 0" x-cloak x-text="count"></span>
                </a>
                <button type="button" class="icon-btn menu-btn" @click="open = !open" :aria-expanded="open" aria-label="Meniu">
                    <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" d="M4 7h16M4 12h16M4 17h16"/></svg>
                </button>
            </div>
        </div>
        <div class="drawer" x-show="open" x-cloak>
            <a href="/">Acasă</a>
            <a href="/competitii">Competiții</a>
            <a href="/cos">Coșul meu</a>
            <a x-show="!user" href="/autentificare">Contul meu</a>
            <a x-show="user" href="/biletele-mele">Biletele mele</a>
            <button type="button" x-show="user" @click="logout()">Ieși din cont</button>
        </div>
    </div>
</header>
