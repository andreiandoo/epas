<?php
/**
 * LAYOUT: account  (logged-in area — cont/*)
 *
 * Vars: $title, $nav (active key), $extra_styles, $slot
 * Config: account_menu[] = [['key','label','url','icon'], ...], login_url
 *
 * Client-auth-gated: kitAccountShell() (kit.js) redirects to login if there is
 * no token in localStorage. Page bodies are JS-hydrated from the proxy with the
 * Bearer token (see the example cont/* pages). The QR modal is rendered once here.
 */
/** @var array $__cfg */ /** @var string $slot */
$title = $title ?? ('Contul meu — ' . $__cfg['site_name']);
$navActive = $nav ?? '';
// Default account menu is feature-gated: subscription/gift-card entries appear
// only for kinds that enable them.
$menu = $__cfg['account_menu'] ?? array_values(array_filter([
    ['key' => 'dashboard', 'label' => 'Panou',        'url' => '/cont',              'icon' => '🏠'],
    ['key' => 'tickets',   'label' => 'Bilete',       'url' => '/cont/bilete',       'icon' => '🎫'],
    ['key' => 'orders',    'label' => 'Comenzi',      'url' => '/cont/comenzi',      'icon' => '🧾'],
    kit_feature('subscriptions') ? ['key' => 'subscriptions', 'label' => 'Abonamente', 'url' => '/cont/abonamente', 'icon' => '🎟️'] : null,
    kit_feature('gift_cards')    ? ['key' => 'giftcards',     'label' => 'Carduri cadou', 'url' => '/cont/carduri-cadou', 'icon' => '🎁'] : null,
    ['key' => 'settings',  'label' => 'Setări',       'url' => '/cont/setari',       'icon' => '⚙️'],
]));
$loginUrl = $__cfg['login_url'] ?? '/autentificare';
$demo = !empty($__cfg['fixtures']);  // dev preview: skip auth gate when using fixtures
?><!DOCTYPE html>
<html lang="<?= e($__cfg['locale']) ?>">
<head>
  <meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= e($title) ?></title>
  <?php if (!empty($__cfg['fonts_href'])): ?><link href="<?= e($__cfg['fonts_href']) ?>" rel="stylesheet"><?php endif; ?>
  <?php if ($__cfg['use_tailwind'] ?? true): ?><script src="https://cdn.tailwindcss.com"></script><?php endif; ?>
  <?= kit_head_scripts() ?>
  <?= kit_theme_links() ?>
  <?php if (!empty($extra_styles)): ?><style><?= $extra_styles ?></style><?php endif; ?>
</head>
<body class="kit-body" x-data="kitAccountShell('<?= e($loginUrl) ?>', <?= $demo ? 'true' : 'false' ?>)" x-init="init()">
  <div class="kit-account" x-show="ready" x-cloak>
    <aside class="kit-account__side">
      <a href="/" class="kit-logo" style="padding:1.25rem"><span class="kit-logo__mark"><?= e($__cfg['logo_text'] ?: 'K') ?></span><span class="kit-logo__name"><?= e($__cfg['site_name']) ?></span></a>
      <nav class="kit-account__nav">
        <?php foreach ($menu as $it): ?>
          <a href="<?= e($it['url']) ?>" class="kit-account__link<?= ($it['key'] ?? '') === $navActive ? ' is-active' : '' ?>">
            <span><?= $it['icon'] ?? '' ?></span> <?= e($it['label']) ?>
          </a>
        <?php endforeach; ?>
        <button class="kit-account__link" @click="logout()" style="width:100%;text-align:left;background:none;border:none;cursor:pointer">↩ Ieșire</button>
      </nav>
    </aside>
    <main class="kit-account__main"><?= $slot ?></main>
  </div>
  <?php component('qr-modal'); ?>
</body>
</html>
<style id="kit-account-css">
.kit-account { display:grid; grid-template-columns:1fr; min-height:100vh; }
@media (min-width:900px){ .kit-account { grid-template-columns:260px 1fr; } }
.kit-account__side { border-right:1px solid var(--kit-border); background:var(--kit-surface); }
.kit-account__nav { display:flex; flex-direction:column; gap:.15rem; padding:.5rem; }
.kit-account__link { display:flex; align-items:center; gap:.6rem; padding:.65rem .9rem; border-radius:var(--kit-radius-sm);
  text-decoration:none; color:var(--kit-text-muted); font-size:.95rem; }
.kit-account__link:hover { background:var(--kit-surface-2); color:var(--kit-text); }
.kit-account__link.is-active { background:var(--kit-primary); color:var(--kit-on-primary); }
.kit-account__main { padding:2rem 1.5rem; max-width:900px; }
</style>
