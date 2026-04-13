<?php
/**
 * Whitelabel head — nav + cookie consent.
 * Available vars: $pageTitle, $showBackLink (bool), $backUrl, $backLabel
 */
$bp = BASE_PATH;
$ac = ACCENT_COLOR ?: '#D4A843';
?>
<!DOCTYPE html>
<html lang="ro">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= htmlspecialchars($pageTitle ?? ORG_NAME) ?></title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:ital,wght@0,300;0,400;0,600;1,300;1,400&family=Outfit:wght@300;400;500;600&display=swap" rel="stylesheet">
<link rel="stylesheet" href="<?= $bp ?>/assets/css/style.css">
<style>
  :root {
    --accent:      <?= $ac ?>;
    --accent-dim:  <?= $ac ?>1a;
    --accent-glow: <?= $ac ?>59;
    --bg:          #080808;
    --bg2:         #0f0f0f;
    --bg3:         #161616;
    --border:      rgba(255,255,255,0.07);
    --text:        #f0ede6;
    --text-muted:  rgba(240,237,230,0.45);
    --text-dim:    rgba(240,237,230,0.22);
    --radius:      4px;
    --font-display:'Cormorant Garamond', Georgia, serif;
    --font-body:   'Outfit', system-ui, sans-serif;
  }
</style>
</head>
<body>

<!-- NAV -->
<nav>
  <?php if (!empty($showBackLink)): ?>
  <a href="<?= htmlspecialchars($backUrl ?? $bp . '/') ?>" class="nav-back">
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M19 12H5"/><path d="m12 19-7-7 7-7"/></svg>
    <?= htmlspecialchars($backLabel ?? 'Toate evenimentele') ?>
  </a>
  <?php else: ?>
  <a href="<?= $bp ?>/" class="nav-logo">
    <?php if (LOGO_URL): ?>
    <img src="<?= htmlspecialchars(LOGO_URL) ?>" alt="<?= htmlspecialchars(ORG_NAME) ?>">
    <?php else: ?>
    <span><?= htmlspecialchars(ORG_NAME) ?></span>
    <?php endif; ?>
  </a>
  <?php endif; ?>

  <a href="<?= $bp ?>/" class="nav-logo" <?= !empty($showBackLink) ? '' : 'style="display:none"' ?>>
    <?php if (LOGO_URL): ?>
    <img src="<?= htmlspecialchars(LOGO_URL) ?>" alt="" style="height:28px;">
    <?php else: ?>
    <span><?= htmlspecialchars(ORG_NAME) ?></span>
    <?php endif; ?>
  </a>

  <div class="nav-actions">
    <a href="<?= $bp ?>/checkout" class="btn-cart">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="9" cy="21" r="1"/><circle cx="20" cy="21" r="1"/><path d="M1 1h4l2.68 13.39a2 2 0 0 0 2 1.61h9.72a2 2 0 0 0 2-1.61L23 6H6"/></svg>
      Coșul meu
      <span class="cart-count" id="wl-cart-count" style="display:none;">0</span>
    </a>
  </div>
</nav>

<!-- Cookie consent -->
<div id="wl-cookie" class="cookie-banner">
  <div style="display:flex;align-items:center;justify-content:space-between;gap:16px;flex-wrap:wrap;max-width:1080px;margin:0 auto;">
    <span style="font-size:13px;color:var(--text-muted);flex:1;">
      Acest site folosește cookie-uri. <a href="<?= $bp ?>/privacy" style="color:var(--accent);">Confidențialitate</a>
    </span>
    <button onclick="document.getElementById('wl-cookie').style.display='none';document.cookie='wl_ck=1;path=/;max-age=31536000'" class="btn-cart" style="padding:7px 18px;font-size:12px;">Accept</button>
  </div>
</div>
<script>if(!document.cookie.includes('wl_ck=1'))document.getElementById('wl-cookie').style.display='block';</script>
