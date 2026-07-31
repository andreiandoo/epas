<?php
/**
 * PAGESET: operator-login — venue staff sign in on the venue's own site.
 *
 * Deliberately NOT the customer login: a different token, a different storage
 * key, a different landing page. Standalone markup (no operator layout) because
 * the layout itself is auth-gated and would bounce straight back here.
 */
$PAGE = $PAGE ?? [];
$home = $PAGE['home'] ?? '/operator';
$cfg  = Kit::config();
?><!DOCTYPE html>
<html lang="<?= e(kit_locale()) ?>">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="robots" content="noindex,nofollow">
  <title><?= e(t('operator.login') . ' — ' . kit_cfg('site_name')) ?></title>
  <?php if (!empty($cfg['fonts_href'])): ?><link href="<?= e($cfg['fonts_href']) ?>" rel="stylesheet"><?php endif; ?>
  <?php if (!empty($cfg['favicon'])): ?><link rel="icon" href="<?= e($cfg['favicon']) ?>"><?php endif; ?>
  <?= kit_head_scripts() ?>
  <?= kit_theme_links() ?>
</head>
<body class="kit-body kit-op">
  <div class="kit-op__login" x-data="kitOperatorLogin('<?= e($home) ?>')">
    <form class="kit-op__card" @submit.prevent="submit()">
      <div class="kit-op__loginhead">
        <span class="kit-logo__mark"><?= e(kit_cfg('logo_text') ?: 'K') ?></span>
        <div>
          <h1 class="kit-display" style="font-size:1.4rem;margin:0"><?= e(kit_cfg('site_name')) ?></h1>
          <p class="kit-muted" style="margin:.15rem 0 0;font-size:.9rem"><?= e(t('operator.panel')) ?></p>
        </div>
      </div>

      <label class="kit-op__label" for="op-email"><?= e(t('checkout.email')) ?></label>
      <input id="op-email" class="kit-op__input" type="email" inputmode="email"
             autocomplete="username" x-model="email" required autofocus>

      <label class="kit-op__label" for="op-pass"><?= e(t('auth.password')) ?></label>
      <input id="op-pass" class="kit-op__input" type="password"
             autocomplete="current-password" x-model="password" required>

      <p class="kit-booking__error" x-show="error" x-text="error" x-cloak></p>

      <button class="kit-btn kit-btn--primary kit-op__submit" :disabled="busy">
        <span x-text="busy ? '…' : '<?= e(t('auth.login_cta')) ?>'"></span>
      </button>

      <p class="kit-muted kit-op__hint"><?= e(t('operator.login_hint')) ?></p>
    </form>
  </div>
</body>
</html>
