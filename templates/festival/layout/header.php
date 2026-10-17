<?php
/**
 * Antetul festivalului: numele (sau logo), meniul din panou, limba, cont, coș și
 * butonul de bilete. Transparent peste hero, opac la scroll și pe paginile comune.
 */
$navItems = (array) site('nav', []);
$logoUrl  = site('branding.logo');
$cta      = (array) site('header_cta', []);
$solid    = defined('TS_SHARED_PAGE') || !empty($headSolid);
$current  = '/' . trim((string) parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH), '/');
?>
<body class="fx-body <?= e($bodyClass ?? '') ?>">
<a href="#main" class="fx-skip"><?= te('Sari la conținut') ?></a>
<?php if (SITE_PREVIEW): ?>
<div style="position:fixed;left:16px;bottom:16px;z-index:300;background:#fbbf24;color:#000;border-radius:999px;padding:8px 16px;font:700 12px/1 system-ui"><?= te('Previzualizare ciornă') ?> · <a href="/?preview=0" style="color:#000;text-decoration:underline"><?= te('ieși') ?></a></div>
<?php endif; ?>
<header class="fx-head<?= $solid ? ' is-solid' : '' ?>" x-data="siteHead" :class="open && 'is-open'" data-fx-head<?= $solid ? ' data-solid' : '' ?>>
    <div class="fx-wrap fx-head__row">
        <a href="/" class="fx-brand" aria-label="<?= e(SITE_NAME) ?>">
            <?php if ($logoUrl): ?><img src="<?= e($logoUrl) ?>" alt="" height="40"><?php else: ?><span><?= e(SITE_NAME) ?></span><?php endif; ?>
        </a>
        <nav class="fx-nav" aria-label="<?= te('Principal') ?>">
            <?php foreach ($navItems as $item): $href = ts_href($item['url'] ?? ''); $on = $href !== '/' && !str_contains($href, '#') && str_starts_with($current, rtrim($href, '/')); ?>
            <a href="<?= e($href) ?>" class="<?= $on ? 'is-on' : '' ?>"<?= $on ? ' aria-current="page"' : '' ?>><?= e(t((string) ($item['label'] ?? ''))) ?></a>
            <?php endforeach; ?>
        </nav>
        <div class="fx-tools">
            <?= ts_lang_switch('fx-lang', '', 'is-on') ?>
            <a x-show="!user" href="/autentificare" class="fx-icon" aria-label="<?= te('Contul meu') ?>">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><circle cx="12" cy="8" r="4"/><path d="M4 21a8 8 0 0116 0"/></svg>
            </a>
            <a x-show="user" x-cloak href="/cont" class="fx-icon fx-icon--me" :title="firstName"><span x-text="initials"></span></a>
            <a href="/cos" class="fx-icon" aria-label="<?= te('Coșul meu') ?>">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path d="M6 7h12l1 13H5L6 7z"/><path d="M9 10V6a3 3 0 016 0v4"/></svg>
                <b class="fx-icon__n" x-show="count > 0" x-cloak x-text="count"></b>
            </a>
            <?php if (!empty($cta['label']) && !empty($cta['url'])): ?>
            <a href="<?= e(ts_href($cta['url'])) ?>" class="fx-btn fx-btn--sm fx-head__cta"><?= e(t((string) $cta['label'])) ?></a>
            <?php endif; ?>
            <button type="button" class="fx-icon fx-menu" @click="open = !open" :aria-expanded="open" aria-label="<?= te('Meniu') ?>">
                <svg x-show="!open" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M4 8h16M4 16h16"/></svg>
                <svg x-show="open" x-cloak viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M6 6l12 12M18 6L6 18"/></svg>
            </button>
        </div>
    </div>
    <div class="fx-drawer" x-show="open" x-cloak x-transition.opacity>
        <div class="fx-wrap">
            <?php foreach ($navItems as $item): ?><a href="<?= e(ts_href($item['url'] ?? '')) ?>" @click="open = false"><?= e(t((string) ($item['label'] ?? ''))) ?></a><?php endforeach; ?>
            <a href="/cos"><?= te('Coșul meu') ?></a>
            <a x-show="!user" href="/autentificare"><?= te('Contul meu') ?></a>
            <a x-show="user" href="/cont/bilete"><?= te('Biletele mele') ?></a>
            <button type="button" x-show="user" @click="logout()"><?= te('Ieși din cont') ?></button>
            <?= ts_lang_switch('fx-lang fx-lang--drawer', '', 'is-on') ?>
        </div>
    </div>
</header>
<div id="main" tabindex="-1"></div>
