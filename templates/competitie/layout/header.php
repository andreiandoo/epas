<?php
/**
 * Antetul template-ului competiție: logo, meniul din core, cont, coș.
 * Pe paginile comune (coș, plată, cont) antetul e opac de la început.
 */
$navItems = (array) site('nav', []);
$logoUrl  = site('branding.logo');
$solid    = defined('TS_SHARED_PAGE') || !empty($headSolid);
$current  = '/' . trim((string) parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH), '/');
?>
<body class="<?= e($bodyClass ?? '') ?>">
<?php if (SITE_PREVIEW): ?>
<div style="position:fixed;left:16px;bottom:16px;z-index:300;background:#fbbf24;color:#000;border-radius:999px;padding:8px 16px;font:700 12px/1 system-ui">Previzualizare ciornă · <a href="/?preview=0" style="color:#000;text-decoration:underline">ieși</a></div>
<?php endif; ?>
<div class="progress belt-bg" aria-hidden="true"></div>
<header class="site-head<?= $solid ? ' is-solid' : '' ?>" x-data="siteHead" :class="open && 'is-open'"<?= $solid ? ' data-solid' : '' ?>>
    <div class="wrap">
        <div class="site-head__row">
            <a href="/" class="brand" aria-label="<?= e(SITE_NAME) ?> — acasă">
                <?php if ($logoUrl): ?>
                    <img src="<?= e($logoUrl) ?>" alt="" width="46" height="46" style="object-fit:contain">
                <?php else: ?>
                    <span style="width:46px;height:46px;border-radius:50%;display:grid;place-items:center;background:var(--blue);font-family:var(--display);font-weight:800;font-size:18px;color:#fff"><?= e(SITE_LOGO_TEXT) ?></span>
                <?php endif; ?>
                <span>
                    <span class="brand__name"><?= e(SITE_NAME) ?></span>
                    <span class="brand__sub"><?= e(SITE_CITY !== '' ? SITE_CITY : 'Bilete online') ?></span>
                </span>
            </a>
            <nav class="nav" aria-label="Principal">
                <?php foreach ($navItems as $item): $href = ts_href($item['url'] ?? ''); ?>
                <a href="<?= e($href) ?>" class="<?= $href !== '/' && str_starts_with($current, rtrim($href, '/')) ? 'is-on' : '' ?>"<?= preg_match('#^https?://#', $href) ? ' target="_blank" rel="noopener"' : '' ?>><?= e($item['label'] ?? '') ?></a>
                <?php endforeach; ?>
            </nav>
            <div class="head-tools">
                <a x-show="!user" href="/autentificare" class="head-user head-user--plain">Contul meu</a>
                <a x-show="user" x-cloak href="/cont/bilete" class="head-user" title="Biletele mele">
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
            <?php foreach ($navItems as $item): ?><a href="<?= e(ts_href($item['url'] ?? '')) ?>"><?= e($item['label'] ?? '') ?></a><?php endforeach; ?>
            <a href="/cos">Coșul meu</a>
            <a x-show="!user" href="/autentificare">Contul meu</a>
            <a x-show="user" href="/cont/bilete">Biletele mele</a>
            <a x-show="user" href="/cont/comenzi">Comenzile mele</a>
            <a x-show="user" href="/cont/profil">Profilul meu</a>
            <button type="button" x-show="user" @click="logout()">Ieși din cont</button>
        </div>
    </div>
</header>
