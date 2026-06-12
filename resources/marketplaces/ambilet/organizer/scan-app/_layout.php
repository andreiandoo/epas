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

    <!-- PWA: manifest + iOS apple-touch-icon + theme. -->
    <link rel="manifest" href="/organizator/scan/manifest.webmanifest">
    <link rel="apple-touch-icon" sizes="180x180" href="/organizator/scan/icon.php?size=180">
    <link rel="apple-touch-icon" sizes="167x167" href="/organizator/scan/icon.php?size=167">
    <link rel="apple-touch-icon" sizes="152x152" href="/organizator/scan/icon.php?size=152">
    <link rel="apple-touch-icon" sizes="120x120" href="/organizator/scan/icon.php?size=120">
    <link rel="icon" type="image/png" sizes="192x192" href="/organizator/scan/icon.php?size=192">
    <link rel="icon" type="image/png" sizes="32x32"  href="/organizator/scan/icon.php?size=32">

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
        version: '0.1.0'
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

  <header class="scanapp-header">
    <button type="button" class="scanapp-header__menu" id="scanapp-event-picker" aria-label="Schimbă eveniment">
      <svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2">
        <rect x="3" y="4" width="18" height="18" rx="2" ry="2"></rect>
        <path d="M16 2v4M8 2v4M3 10h18"></path>
      </svg>
    </button>
    <div class="scanapp-header__title">
      <div class="scanapp-header__event-name" id="scanapp-event-name">Selectează un eveniment</div>
      <div class="scanapp-header__event-meta" id="scanapp-event-meta">—</div>
    </div>
    <button type="button" class="scanapp-header__notif" id="scanapp-notif" aria-label="Notificări">
      <svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2">
        <path d="M18 8a6 6 0 00-12 0c0 7-3 9-3 9h18s-3-2-3-9"></path>
        <path d="M13.73 21a2 2 0 01-3.46 0"></path>
      </svg>
      <span class="scanapp-header__badge" id="scanapp-notif-badge" hidden>0</span>
    </button>
  </header>

  <main class="scanapp-main">
<?php
// All page content rendered between this and _layout_end.php
?>
