<?php
/**
 * Scan App Layout — Mobile-first dark theme for /organizator/scan/*
 *
 * NOT to be confused with the main organizer panel layout (head.php + organizer-sidebar.php).
 * This is a separate, additive-only mini-app that replicates the Tixello Android scanner
 * app for iOS users who cannot install the APK.
 *
 * Usage at the top of each scan-app page (e.g. panou.php, scanare.php):
 *   $scanPage      = 'panou';                              // active tab key
 *   $scanPageTitle = 'Panou';                              // header text
 *   require __DIR__ . '/_layout.php';                      // emits <head>+<body open>+<main open>
 *   // ... page content ...
 *   require __DIR__ . '/_layout_end.php';                  // emits </main>+nav+</body>
 *
 * DO NOT include head.php or organizer-sidebar.php from here — this layout is
 * intentionally self-contained so the scanner UI has no dependency on the main
 * panel layout's heavy SEO head, Tailwind organizer.css bundle, or sidebar.
 */

require_once dirname(__DIR__, 2) . '/includes/config.php';

$scanPage      = $scanPage      ?? 'panou';
$scanPageTitle = $scanPageTitle ?? 'Aplicație Scan';

$tabs = [
    'panou'        => ['label' => 'Panou',    'href' => '/organizator/scan/panou'],
    'scanare'      => ['label' => 'Scanare',  'href' => '/organizator/scan/scanare'],
    'vanzare'      => ['label' => 'Vânzare',  'href' => '/organizator/scan/vanzare'],
    'rapoarte'     => ['label' => 'Rapoarte', 'href' => '/organizator/scan/rapoarte'],
    'setari-scan'  => ['label' => 'Setări',   'href' => '/organizator/scan/setari-scan'],
];
?><!DOCTYPE html>
<html lang="ro" class="scanapp-html">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover, maximum-scale=1, user-scalable=no">
    <meta name="theme-color" content="#0A0A0F">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <meta name="apple-mobile-web-app-title" content="Aplicație Scan">
    <meta name="mobile-web-app-capable" content="yes">
    <meta name="format-detection" content="telephone=no">

    <!-- PERF: preconnect to the API origin so the DNS lookup + TCP + TLS
         handshake happens IN PARALLEL with HTML parsing. By the time JS
         fires the first /api/scan-proxy → core.tixello.com request, the
         connection is already warm. Saves ~100-300ms on cold visits
         (mobile data, first session). dns-prefetch is a cheaper fallback
         that older browsers honor.

         Scope: only the scan-app layout. Other marketplace pages remain
         untouched. -->
    <link rel="preconnect" href="https://core.tixello.com" crossorigin>
    <link rel="dns-prefetch" href="https://core.tixello.com">
    <link rel="dns-prefetch" href="https://cdn.jsdelivr.net">

    <!-- PWA: manifest + iOS apple-touch-icon + theme. We declare a single
         apple-touch-icon at 180x180 (iOS picks the closest match and resizes;
         no point making 4 GD calls on first load). The manifest itself
         enumerates the icon sizes for Android Chrome / Edge. -->
    <link rel="manifest" href="/organizator/scan/manifest.webmanifest">
    <link rel="apple-touch-icon" href="/organizator/scan/icon.php?size=180">
    <link rel="icon" type="image/png" sizes="192x192" href="/organizator/scan/icon.php?size=192">

    <title><?= htmlspecialchars($scanPageTitle) ?> — Aplicație Scan</title>

    <link rel="stylesheet" href="/assets/css/scan-app.css?v=<?= filemtime(dirname(__DIR__, 2) . '/assets/css/scan-app.css') ?>">

    <!-- AmbiletAuth + AmbiletAPI from main panel (shared, additive use only) -->
    <script src="/assets/js/auth.js"></script>
    <script src="/assets/js/api.js"></script>

    <script>
      window.SCAN_APP = window.SCAN_APP || {
        page: <?= json_encode($scanPage) ?>,
        apiBase: <?= json_encode(API_BASE_URL) ?>,
        apiEnv: <?= json_encode(API_ENV) ?>,
        coreUrl: <?= json_encode(CORE_URL) ?>,
        storageUrl: <?= json_encode(STORAGE_URL) ?>,
        version: '0.2.0'
      };
    </script>
</head>
<body class="scanapp-body" data-scan-page="<?= htmlspecialchars($scanPage) ?>">

  <!-- Pre-auth gate: redirects to /organizator/login if no token. Runs BEFORE the
       rest of the page renders so unauthenticated users don't see a flash of
       the dark UI. -->
  <script>
    (function () {
      if (typeof AmbiletAuth === 'undefined') return;
      if (!AmbiletAuth.isLoggedIn || !AmbiletAuth.isLoggedIn()) {
        var rt = encodeURIComponent(location.pathname + location.search);
        location.replace('/organizator/login?redirect=' + rt);
      }
    })();
  </script>

  <!-- Top bar: ported from tixello-app/src/components/Header.js but trimmed
       for web (no 48px paddingTop — browser chrome already gives us space).
       Logo left, status pill + refresh button right. The refresh button
       used to be a bell icon which confused users (no notifications exist
       yet), so it's a circular-arrow icon now to clearly signal its action. -->
  <header class="scanapp-topbar">
    <div class="scanapp-topbar__inner">
      <a class="scanapp-topbar__left" href="/organizator/scan/panou" aria-label="Aplicație Scan">
        <img src="/assets/images/ambilet-logo.webp" alt="" class="scanapp-topbar__logo">
      </a>
      <div class="scanapp-topbar__right">
        <div class="scanapp-status-pill scanapp-status-pill--online" id="scanapp-status-pill" role="status">
          <span class="scanapp-pulse-dot"></span>
          <span class="scanapp-status-pill__text" id="scanapp-status-text">Live</span>
        </div>
        <button type="button" class="scanapp-bell" id="scanapp-refresh" aria-label="Reîncarcă datele" title="Reîncarcă datele">
          <svg viewBox="0 0 24 24" width="22" height="22" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <polyline points="23 4 23 10 17 10"/>
            <polyline points="1 20 1 14 7 14"/>
            <path d="M3.51 9a9 9 0 0114.85-3.36L23 10M1 14l4.64 4.36A9 9 0 0020.49 15"/>
          </svg>
        </button>
      </div>
    </div>
  </header>

  <?php
    // EventSelector visibility rules (after user feedback):
    //   panou           → full interactive selector (open picker on tap)
    //   scanare/vanzare → read-only event name strip (no chevron, no click)
    //   rapoarte        → nothing (page has its own past-event selector)
    //   setari-scan     → nothing (no event context)
    $selectorMode = 'hidden';
    if ($scanPage === 'panou')        $selectorMode = 'interactive';
    elseif (in_array($scanPage, ['scanare', 'vanzare'], true)) $selectorMode = 'readonly';
  ?>
  <?php if ($selectorMode === 'interactive'): ?>
  <button type="button" class="scanapp-event-selector scanapp-event-selector--interactive" id="scanapp-event-selector-bar">
    <div class="scanapp-event-selector__content">
      <div class="scanapp-event-selector__title-row">
        <div class="scanapp-event-selector__name" id="scanapp-es-name">Niciun eveniment selectat</div>
        <div class="scanapp-event-selector__badge" id="scanapp-es-badge" hidden>
          <span class="scanapp-pulse-dot scanapp-event-selector__badge-dot" id="scanapp-es-badge-dot"></span>
          <span id="scanapp-es-badge-text">Viitor</span>
        </div>
      </div>
      <div class="scanapp-event-selector__meta" id="scanapp-es-meta">Apasă pentru a alege un eveniment</div>
    </div>
    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="var(--scanapp-text-tertiary)" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M9 18l6-6-6-6"/></svg>
  </button>
  <?php elseif ($selectorMode === 'readonly'): ?>
  <div class="scanapp-event-selector scanapp-event-selector--readonly">
    <div class="scanapp-event-selector__content">
      <div class="scanapp-event-selector__title-row">
        <div class="scanapp-event-selector__name" id="scanapp-es-name">—</div>
        <div class="scanapp-event-selector__badge" id="scanapp-es-badge" hidden>
          <span class="scanapp-pulse-dot scanapp-event-selector__badge-dot" id="scanapp-es-badge-dot"></span>
          <span id="scanapp-es-badge-text">Viitor</span>
        </div>
      </div>
      <div class="scanapp-event-selector__meta" id="scanapp-es-meta">—</div>
    </div>
  </div>
  <?php endif; ?>

  <main class="scanapp-main">
<?php
// All page content rendered between this and _layout_end.php
?>
