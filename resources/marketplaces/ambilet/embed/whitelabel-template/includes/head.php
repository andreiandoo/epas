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

<?php if (defined('TRACKING_GTM_ID') && TRACKING_GTM_ID): ?>
<!-- Google Tag Manager -->
<script>(function(w,d,s,l,i){w[l]=w[l]||[];w[l].push({'gtm.start':
new Date().getTime(),event:'gtm.js'});var f=d.getElementsByTagName(s)[0],
j=d.createElement(s),dl=l!='dataLayer'?'&l='+l:'';j.async=true;j.src=
'https://www.googletagmanager.com/gtm.js?id='+i+dl;f.parentNode.insertBefore(j,f);
})(window,document,'script','dataLayer','<?= htmlspecialchars(TRACKING_GTM_ID) ?>');</script>
<!-- End Google Tag Manager -->
<?php endif; ?>

<?php if (defined('TRACKING_GA_ID') && TRACKING_GA_ID): ?>
<!-- Google Analytics (gtag) -->
<script async src="https://www.googletagmanager.com/gtag/js?id=<?= htmlspecialchars(TRACKING_GA_ID) ?>"></script>
<script>
window.dataLayer = window.dataLayer || [];
function gtag(){dataLayer.push(arguments);}
gtag('js', new Date());
gtag('config', '<?= htmlspecialchars(TRACKING_GA_ID) ?>');
</script>
<!-- End Google Analytics -->
<?php endif; ?>

<?php if (defined('TRACKING_FB_PIXEL_ID') && TRACKING_FB_PIXEL_ID): ?>
<!-- Meta (Facebook) Pixel -->
<script>
!function(f,b,e,v,n,t,s)
{if(f.fbq)return;n=f.fbq=function(){n.callMethod?
n.callMethod.apply(n,arguments):n.queue.push(arguments)};
if(!f._fbq)f._fbq=n;n.push=n;n.loaded=!0;n.version='2.0';
n.queue=[];t=b.createElement(e);t.async=!0;
t.src=v;s=b.getElementsByTagName(e)[0];
s.parentNode.insertBefore(t,s)}(window, document,'script',
'https://connect.facebook.net/en_US/fbevents.js');
fbq('init', '<?= htmlspecialchars(TRACKING_FB_PIXEL_ID) ?>');
fbq('track', 'PageView');
</script>
<noscript><img height="1" width="1" style="display:none" src="https://www.facebook.com/tr?id=<?= htmlspecialchars(TRACKING_FB_PIXEL_ID) ?>&ev=PageView&noscript=1"/></noscript>
<!-- End Meta Pixel -->
<?php endif; ?>

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

<!-- Cart timer bar (visible when cart has items) -->
<div id="wl-timer-bar" style="display:none;background:var(--bg3);border-bottom:1px solid var(--border);padding:8px 24px;text-align:center;font-size:13px;color:var(--text-muted);">
  <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="width:14px;height:14px;vertical-align:-2px;margin-right:4px;color:var(--accent);"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
  Biletele sunt rezervate pentru <strong id="wl-timer-countdown" style="color:var(--accent);"></strong>
</div>

<!-- Cookie consent -->
<div id="wl-cookie" class="cookie-banner" style="display:none;">
  <div style="display:flex;align-items:center;justify-content:space-between;gap:20px;flex-wrap:wrap;max-width:1080px;margin:0 auto;">
    <div style="flex:1;min-width:200px;">
      <div style="display:flex;align-items:center;gap:8px;margin-bottom:4px;">
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="color:var(--accent);flex-shrink:0;"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>
        <span style="font-size:13px;font-weight:600;color:var(--text);">Confidențialitate și cookie-uri</span>
      </div>
      <span style="font-size:12px;color:var(--text-muted);line-height:1.5;">
        Folosim cookie-uri pentru a-ți oferi cea mai bună experiență. Prin continuarea navigării, ești de acord cu
        <a href="<?= $bp ?>/privacy" style="color:var(--accent);font-weight:500;">politica noastră</a>.
      </span>
    </div>
    <div style="display:flex;gap:8px;flex-shrink:0;">
      <button onclick="document.getElementById('wl-cookie').style.display='none';document.cookie='wl_ck=1;path=/;max-age=31536000'" style="padding:9px 20px;background:var(--accent);color:#000;border:none;border-radius:var(--radius);font-family:var(--font-body);font-size:12px;font-weight:700;letter-spacing:.04em;text-transform:uppercase;cursor:pointer;transition:opacity .2s;">Accept toate</button>
      <button onclick="document.getElementById('wl-cookie').style.display='none';document.cookie='wl_ck=0;path=/;max-age=31536000'" style="padding:9px 16px;background:none;color:var(--text-muted);border:1px solid var(--border);border-radius:var(--radius);font-family:var(--font-body);font-size:12px;font-weight:500;cursor:pointer;transition:color .2s;">Doar esențiale</button>
    </div>
  </div>
</div>
<script>if(!document.cookie.includes('wl_ck='))document.getElementById('wl-cookie').style.display='block';</script>
