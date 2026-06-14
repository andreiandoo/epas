<?php
$scanPage      = 'scanare';
$scanPageTitle = 'Scanare';
require __DIR__ . '/_layout.php';
?>
<section class="scanapp-section">

  <!-- Reports-only placeholder (shown when selected event has ended) -->
  <div class="scanapp-card" id="scanapp-reports-only" hidden>
    <div class="scanapp-card__title">Eveniment trecut</div>
    <p class="scanapp-card__text">Acest eveniment s-a încheiat. Check-in-ul nu mai este disponibil, dar poți vizualiza rapoartele.</p>
    <div style="margin-top: 14px;">
      <a class="scanapp-btn scanapp-btn--primary scanapp-btn--block" href="/organizator/scan/rapoarte">Vezi rapoarte</a>
    </div>
  </div>

  <div id="scanapp-checkin-main">

    <!-- Scanner section: 280x280 square frame with corner brackets + scan line -->
    <div class="scanapp-scanner-section">
      <div class="scanapp-scanner-frame" id="scanapp-scanner-frame">
        <video class="scanapp-scanner-frame__video" id="scanapp-video" playsinline muted></video>
        <div class="scanapp-scanner-frame__line" id="scanapp-scan-line" hidden></div>
        <div class="scanapp-scanner-corner scanapp-scanner-corner--tl"></div>
        <div class="scanapp-scanner-corner scanapp-scanner-corner--tr"></div>
        <div class="scanapp-scanner-corner scanapp-scanner-corner--bl"></div>
        <div class="scanapp-scanner-corner scanapp-scanner-corner--br"></div>

        <!-- Placeholder (shown until camera is started) -->
        <button type="button" class="scanapp-scanner-frame__placeholder" id="scanapp-scanner-placeholder" aria-label="Pornește camera">
          <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="var(--scanapp-text-quaternary)" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <path d="M1 9V5a2 2 0 012-2h4M15 3h4a2 2 0 012 2v4M23 15v4a2 2 0 01-2 2h-4M9 21H5a2 2 0 01-2-2v-4"/>
            <line x1="7" y1="12" x2="17" y2="12"/>
          </svg>
          <div class="scanapp-scanner-frame__placeholder-text" id="scanapp-scanner-placeholder-text">Apasă pentru a scana</div>
        </button>
      </div>
    </div>

    <!-- Result card (filled by JS, hidden by default) -->
    <div class="scanapp-result" id="scanapp-result" role="status" aria-live="polite"></div>

    <!-- Action buttons: big purple "Începe Scanarea" + "Cod Manual" link -->
    <div class="scanapp-actions-container">
      <button type="button" class="scanapp-scan-button" id="scanapp-btn-camera">
        <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M1 9V5a2 2 0 012-2h4M15 3h4a2 2 0 012 2v4M23 15v4a2 2 0 01-2 2h-4M9 21H5a2 2 0 01-2-2v-4"/></svg>
        <span id="scanapp-btn-camera-text">Începe Scanarea</span>
      </button>
      <button type="button" class="scanapp-manual-entry-link" id="scanapp-btn-manual">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 20h9M16.5 3.5a2.121 2.121 0 013 3L7 19l-4 1 1-4L16.5 3.5z"/></svg>
        Cod Manual
      </button>
    </div>

    <!-- Live stats pills (scanări/min, așteptare, intrați) -->
    <div class="scanapp-stats-row">
      <div class="scanapp-stat-pill"><span class="scanapp-stat-pill__value" id="scanapp-stat-spm">0</span>scanări/min</div>
      <div class="scanapp-stat-pill"><span class="scanapp-stat-pill__value" id="scanapp-stat-wait">0s</span>așteptare</div>
      <div class="scanapp-stat-pill"><span class="scanapp-stat-pill__value" id="scanapp-stat-checkedin">0</span>intrați</div>
    </div>

    <!-- Recent scans list -->
    <div class="scanapp-recent-scans-section" id="scanapp-recent-scans-section" hidden>
      <div class="scanapp-section-title">Scanări Recente</div>
      <div id="scanapp-recent-scans-list"></div>
    </div>

  </div>
</section>

<!-- Manual entry modal (centered card) -->
<div class="scanapp-modal-overlay" id="scanapp-manual-modal" role="dialog" aria-modal="true">
  <div class="scanapp-modal-card">
    <h2 class="scanapp-modal-card__title">Cod Manual</h2>
    <p class="scanapp-modal-card__desc">Tastează codul biletului așa cum apare pe QR sau bilet.</p>
    <input type="text" class="scanapp-input" id="scanapp-manual-input" inputmode="latin" autocapitalize="characters" autocomplete="off" autocorrect="off" placeholder="Ex: X1SG7TLS">
    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 8px; margin-top: 16px;">
      <button type="button" class="scanapp-btn" id="scanapp-manual-cancel">Anulează</button>
      <button type="button" class="scanapp-btn scanapp-btn--primary" id="scanapp-manual-submit">Check-in</button>
    </div>
  </div>
</div>

<?php $scanPageScript = 'scanare.js'; require __DIR__ . '/_layout_end.php'; ?>
