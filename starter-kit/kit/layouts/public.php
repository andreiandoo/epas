<?php
/**
 * LAYOUT: public
 *
 * Vars (from layout($name, $vars, $body)):
 *   $title        <title> text
 *   $description  meta description (optional)
 *   $nav          active nav key (optional) — highlights the menu item
 *   $extra_styles raw CSS injected into <head> (optional)
 *   $extra_head   raw HTML injected into <head> (optional)
 *   $slot         page body HTML (provided by layout())
 *
 * Header/footer are driven by config: site_name, logo_text, site_city, menu[].
 * Look is 100% from tokens.css + the template's theme.css.
 */

/** @var array $__cfg */
/** @var string $slot */
$title       = $title       ?? $__cfg['site_name'];
$description = $description ?? ($__cfg['site_name'] . ' — bilete și evenimente');
$navActive   = $nav ?? '';
$menu        = $__cfg['menu'] ?? [];
$useTailwind = $__cfg['use_tailwind'] ?? true;
?><!DOCTYPE html>
<html lang="<?= e($__cfg['locale']) ?>">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="description" content="<?= e($description) ?>">
  <title><?= e($title) ?></title>
  <?php if (!empty($__cfg['fonts_href'])): ?><link href="<?= e($__cfg['fonts_href']) ?>" rel="stylesheet"><?php endif; ?>
  <?php if ($useTailwind): ?><script src="https://cdn.tailwindcss.com"></script><?php endif; ?>
  <?php if ($__cfg['use_alpine'] ?? true): ?><script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script><?php endif; ?>
  <?= kit_theme_links() ?>
  <?php if (!empty($extra_styles)): ?><style><?= $extra_styles ?></style><?php endif; ?>
  <?= $extra_head ?? '' ?>
</head>
<body class="kit-body">
  <header class="kit-site-header">
    <div class="kit-container kit-site-header__inner">
      <a href="/" class="kit-logo">
        <?php if (!empty($__cfg['logo_text'])): ?><span class="kit-logo__mark"><?= e($__cfg['logo_text']) ?></span><?php endif; ?>
        <span class="kit-logo__name"><?= e($__cfg['site_name']) ?>
          <?php if (!empty($__cfg['site_city'])): ?><small><?= e($__cfg['site_city']) ?></small><?php endif; ?>
        </span>
      </a>
      <nav class="kit-site-nav">
        <?php foreach ($menu as $item): ?>
          <a href="<?= e($item['url']) ?>" class="kit-site-nav__link<?= ($item['key'] ?? '') === $navActive ? ' is-active' : '' ?>"><?= e($item['label']) ?></a>
        <?php endforeach; ?>
      </nav>
      <div class="kit-site-header__actions">
        <a href="<?= e($__cfg['cart_url'] ?? '/cos') ?>" class="kit-site-nav__link" aria-label="Coș">🛒</a>
        <a href="<?= e($__cfg['cta_url'] ?? ($menu[0]['url'] ?? '/')) ?>" class="kit-btn kit-btn--primary"><?= e($__cfg['cta_label'] ?? 'Bilete') ?></a>
      </div>
    </div>
  </header>

  <main class="kit-main"><?= $slot ?></main>

  <footer class="kit-site-footer">
    <div class="kit-container kit-site-footer__inner">
      <p><?= e($__cfg['site_name']) ?><?php if (!empty($__cfg['site_city'])): ?> · <?= e($__cfg['site_city']) ?><?php endif; ?></p>
      <p class="kit-muted">© <?= date('Y') ?> · Powered by Kit</p>
    </div>
  </footer>

  <script>window.KIT = <?= json_encode([
      'proxy'    => $__cfg['proxy_url'] ?? '/api/proxy.php',
      'cartKey'  => $__cfg['cart_key'] ?? 'kit_cart',
      'authKey'  => $__cfg['auth_key'] ?? 'kit_auth',
      'cartUrl'  => $__cfg['cart_url'] ?? '/cos',
      'currency' => $__cfg['currency'] ?? 'RON',
  ], JSON_UNESCAPED_SLASHES) ?>;</script>
  <script src="<?= e($__cfg['kit_js_href'] ?? '/kit/kit.js') ?>" defer></script>
</body>
</html>
<?php
// Header/footer/layout chrome styles live with the layout so the layout is
// self-contained. Still token-driven — no hard-coded colours.
?>
<style id="kit-layout-css">
.kit-container { max-width: 1200px; margin: 0 auto; padding: 0 1.25rem; }
.kit-main { min-height: 60vh; }
.kit-site-header { position: sticky; top: 0; z-index: 50; background: color-mix(in srgb, var(--kit-bg) 92%, transparent);
  backdrop-filter: blur(8px); border-bottom: 1px solid var(--kit-border); }
.kit-site-header__inner { display: flex; align-items: center; justify-content: space-between; height: 4rem; gap: 1rem; }
.kit-logo { display: flex; align-items: center; gap: .6rem; text-decoration: none; color: var(--kit-text); }
.kit-logo__mark { display: grid; place-items: center; width: 2.5rem; height: 2.5rem; border-radius: 999px;
  border: 2px solid var(--kit-primary); color: var(--kit-primary); font-family: var(--kit-font-display); font-weight: 700; }
.kit-logo__name { font-family: var(--kit-font-display); font-weight: 700; line-height: 1; }
.kit-logo__name small { display: block; font-size: .6rem; letter-spacing: .2em; color: var(--kit-primary); font-weight: 600; }
.kit-site-nav { display: none; gap: 1.5rem; }
@media (min-width: 900px) { .kit-site-nav { display: flex; } }
.kit-site-nav__link { text-decoration: none; color: var(--kit-text-muted); font-size: .95rem; transition: var(--kit-transition); }
.kit-site-nav__link:hover, .kit-site-nav__link.is-active { color: var(--kit-primary); }
.kit-site-header__actions { display: flex; align-items: center; gap: .75rem; }
.kit-site-footer { margin-top: 4rem; border-top: 1px solid var(--kit-border); background: var(--kit-surface); }
.kit-site-footer__inner { display: flex; justify-content: space-between; flex-wrap: wrap; gap: .5rem; padding: 2rem 1.25rem; font-size: .9rem; }
.kit-section { padding: 3rem 0; }
</style>
