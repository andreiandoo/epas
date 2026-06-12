<?php
$scanPage      = 'scanare';
$scanPageTitle = 'Scanare';
require __DIR__ . '/_layout.php';
?>
<section class="scanapp-section">

  <!-- Reports-only banner (shown when selectedEvent.timeCategory === 'past') -->
  <div class="scanapp-card" id="scanapp-reports-only" hidden>
    <div class="scanapp-card__title">Eveniment trecut</div>
    <p class="scanapp-card__text">
      Acest eveniment s-a încheiat. Check-in-ul nu mai este disponibil, dar poți vizualiza rapoartele.
    </p>
    <div class="scanapp-result__actions">
      <a class="scanapp-scanner-controls__btn scanapp-scanner-controls__btn--primary" href="/organizator/scan/rapoarte">Vezi rapoarte</a>
    </div>
  </div>

  <!-- Live stat pills (scans/min, total checked in, check-in rate) -->
  <div class="scanapp-stat-pills" id="scanapp-stat-pills" hidden>
    <div class="scanapp-stat-pill"><span class="scanapp-stat-pill__value" id="scanapp-stat-spm">0</span>scanări/min</div>
    <div class="scanapp-stat-pill"><span class="scanapp-stat-pill__value" id="scanapp-stat-checkedin">0</span>check-in</div>
    <div class="scanapp-stat-pill"><span class="scanapp-stat-pill__value" id="scanapp-stat-rate">0%</span>rată</div>
  </div>

  <!-- Scanner viewport -->
  <div class="scanapp-scanner" id="scanapp-scanner-host">
    <video class="scanapp-scanner__video" id="scanapp-video" playsinline muted></video>
    <div class="scanapp-scanner__overlay">
      <div class="scanapp-scanner__frame">
        <div class="scanapp-scanner__line"></div>
      </div>
    </div>
    <div class="scanapp-scanner__hint">Aliniază codul QR în pătrat</div>

    <!-- Placeholder shown before user taps "Pornește camera" or if permission denied -->
    <div class="scanapp-scanner__placeholder" id="scanapp-scanner-placeholder">
      <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
        <path d="M23 19a2 2 0 01-2 2H3a2 2 0 01-2-2V8a2 2 0 012-2h4l2-3h6l2 3h4a2 2 0 012 2z"/>
        <circle cx="12" cy="13" r="4"/>
      </svg>
      <h3 class="scanapp-scanner__placeholder-title">Apasă pentru a porni camera</h3>
      <p class="scanapp-scanner__placeholder-text" id="scanapp-scanner-placeholder-text">
        Vei putea scana QR-uri și coduri de bare direct cu camera dispozitivului.
      </p>
    </div>
  </div>

  <!-- Scanner controls -->
  <div class="scanapp-scanner-controls">
    <button type="button" class="scanapp-scanner-controls__btn scanapp-scanner-controls__btn--primary" id="scanapp-btn-camera">Pornește camera</button>
    <button type="button" class="scanapp-scanner-controls__btn" id="scanapp-btn-manual">Introducere manuală</button>
  </div>

  <!-- Scan result card -->
  <div class="scanapp-result" id="scanapp-result" role="status" aria-live="polite">
    <div class="scanapp-result__header">
      <div class="scanapp-result__icon" id="scanapp-result-icon"></div>
      <div style="flex:1; min-width:0;">
        <h3 class="scanapp-result__title" id="scanapp-result-title">—</h3>
        <p class="scanapp-result__subtitle" id="scanapp-result-subtitle">—</p>
      </div>
    </div>
    <div class="scanapp-result__details" id="scanapp-result-details"></div>
    <div class="scanapp-result__actions">
      <button type="button" class="scanapp-scanner-controls__btn scanapp-scanner-controls__btn--primary" id="scanapp-result-next">Scanează următorul</button>
    </div>
  </div>

</section>

<!-- Manual entry sheet -->
<div class="scanapp-modal" id="scanapp-manual-modal" role="dialog" aria-modal="true" aria-labelledby="scanapp-manual-title">
  <div class="scanapp-modal__sheet">
    <h2 class="scanapp-modal__title" id="scanapp-manual-title">Introducere manuală</h2>
    <p class="scanapp-modal__text">Tastează codul biletului așa cum apare pe QR sau bilet.</p>
    <input type="text" class="scanapp-input" id="scanapp-manual-input" inputmode="latin" autocapitalize="characters" autocomplete="off" autocorrect="off" placeholder="Ex: X1SG7TLS">
    <div class="scanapp-result__actions">
      <button type="button" class="scanapp-scanner-controls__btn" id="scanapp-manual-cancel">Anulează</button>
      <button type="button" class="scanapp-scanner-controls__btn scanapp-scanner-controls__btn--primary" id="scanapp-manual-submit">Check-in</button>
    </div>
  </div>
</div>

<script src="/assets/js/scan-app/scanner.js?v=<?= filemtime(dirname(__DIR__, 2) . '/assets/js/scan-app/scanner.js') ?>" defer></script>
<script src="/assets/js/scan-app/pages/scanare.js?v=<?= filemtime(dirname(__DIR__, 2) . '/assets/js/scan-app/pages/scanare.js') ?>" defer></script>

<?php require __DIR__ . '/_layout_end.php'; ?>
