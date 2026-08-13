<?php
/**
 * LAYOUT: operator  (venue staff, on the venue's OWN site)
 *
 * Vars: $title, $nav (active key), $requires ('pos'|'checkin'|'rentals'|null), $slot
 *
 * The counterpart of Ambilet's /organizator/leisure-* panel, but living inside
 * the tenant storefront so staff never touch the platform admin. Tablet-first:
 * big targets, high contrast, no marketing chrome.
 *
 * Gated on the client by kitOperatorShell() (kit.js), which redirects to the
 * login when there is no token and re-validates it against the server on every
 * page — a token revoked server-side must not leave a usable till. The nav is
 * filtered by the capability flags the API returns for the operator's role, so
 * a cashier never sees check-in and vice versa.
 */
/** @var array $__cfg */ /** @var string $slot */
$title = $title ?? (t('operator.panel') . ' — ' . $__cfg['site_name']);
$navActive = $nav ?? '';
$requires  = $requires ?? null;
$loginUrl  = $__cfg['operator_login_url'] ?? '/operator/login';

// `can` mirrors OperatorController::operatorPayload(); x-show keeps the markup
// static and lets the runtime decide, so there is no server round-trip per role.
$menu = [
    ['key' => 'dashboard', 'label' => t('operator.dashboard'), 'url' => '/operator',             'icon' => '📊', 'can' => null],
    ['key' => 'pos',       'label' => t('operator.pos'),       'url' => '/operator/pos',         'icon' => '🧾', 'can' => 'pos'],
    ['key' => 'checkin',   'label' => t('operator.checkin'),   'url' => '/operator/checkin',     'icon' => '🎫', 'can' => 'checkin'],
    ['key' => 'rentals',   'label' => t('operator.rentals'),   'url' => '/operator/inchirieri',  'icon' => '🛶', 'can' => 'rentals'],
];
?><!DOCTYPE html>
<html lang="<?= e(kit_locale()) ?>">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
  <meta name="robots" content="noindex,nofollow">
  <title><?= e($title) ?></title>
  <?php if (!empty($__cfg['fonts_href'])): ?><link href="<?= e($__cfg['fonts_href']) ?>" rel="stylesheet"><?php endif; ?>
  <?php if (!empty($__cfg['favicon'])): ?><link rel="icon" href="<?= e($__cfg['favicon']) ?>"><?php endif; ?>
  <?= kit_head_scripts() ?>
  <?= kit_theme_links() ?>
  <?php if (!empty($extra_styles)): ?><style><?= $extra_styles ?></style><?php endif; ?>
</head>
<body class="kit-body kit-op"
      x-data="kitOperatorShell('<?= e($loginUrl) ?>', <?= $requires ? "'" . e($requires) . "'" : 'null' ?>)"
      x-init="init()">

  <div x-show="denied" x-cloak class="kit-op__denied">
    <p class="kit-op__deniedicon">⛔</p>
    <h1 class="kit-display"><?= e(t('operator.denied')) ?></h1>
    <p class="kit-muted"><?= e(t('operator.denied_msg')) ?></p>
    <a href="/operator" class="kit-btn kit-btn--outline"><?= e(t('operator.dashboard')) ?></a>
  </div>

  <div class="kit-op__wrap" x-show="ready && !denied" x-cloak>
    <header class="kit-op__bar">
      <a href="/operator" class="kit-op__brand">
        <span class="kit-logo__mark"><?= e($__cfg['logo_text'] ?: 'K') ?></span>
        <span><?= e($__cfg['site_name']) ?></span>
      </a>
      <div class="kit-op__who">
        <strong x-text="op && op.name"></strong>
        <small class="kit-muted" x-text="op && op.role_label"></small>
      </div>
      <button type="button" class="kit-btn kit-btn--outline kit-op__out" @click="logout()">
        <?= e(t('menu.logout')) ?>
      </button>
    </header>

    <nav class="kit-op__nav">
      <?php foreach ($menu as $it): ?>
        <a href="<?= e($it['url']) ?>"
           class="kit-op__tab<?= ($it['key'] === $navActive) ? ' is-active' : '' ?>"
           <?php if ($it['can']): ?>x-show="op && op.can && op.can.<?= e($it['can']) ?>"<?php endif; ?>>
          <span class="kit-op__tabicon"><?= $it['icon'] ?></span>
          <span><?= e($it['label']) ?></span>
        </a>
      <?php endforeach; ?>
    </nav>

    <main class="kit-op__main"><?= $slot ?></main>
  </div>
</body>
</html>
