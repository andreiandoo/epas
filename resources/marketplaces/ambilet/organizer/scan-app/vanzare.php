<?php
$scanPage      = 'vanzare';
$scanPageTitle = 'Vânzare';
require __DIR__ . '/_layout.php';
?>
<section class="scanapp-section">

  <!-- Reports-only -->
  <div class="scanapp-card" id="scanapp-reports-only" hidden>
    <div class="scanapp-card__title">Eveniment trecut</div>
    <p class="scanapp-card__text">Acest eveniment s-a încheiat. Vânzarea on-site nu mai este disponibilă.</p>
    <div class="scanapp-result__actions">
      <a class="scanapp-btn scanapp-btn--primary" href="/organizator/scan/rapoarte">Vezi rapoarte</a>
    </div>
  </div>

  <!-- Ticket grid -->
  <div id="scanapp-sales-main">
    <div class="scanapp-section-title">Tipuri de bilete</div>
    <div class="scanapp-ticket-grid" id="scanapp-ticket-grid">
      <div class="scanapp-card scanapp-card--placeholder">
        <p class="scanapp-card__text">Se încarcă tipurile de bilete…</p>
      </div>
    </div>

    <div class="scanapp-section-title" style="margin-top: 16px;">Vânzări recente</div>
    <div class="scanapp-recent-sales" id="scanapp-recent-sales">
      <div class="scanapp-card scanapp-card--placeholder">
        <p class="scanapp-card__text">Nu există vânzări înregistrate în această tură.</p>
      </div>
    </div>
  </div>

  <p class="scanapp-card__text scanapp-card__text--muted" style="margin-top: 16px;">
    <b>Notă:</b> versiunea web nu suportă plata prin NFC (Stripe Tap) sau conexiune cu POS bancar fizic.
    Folosește versiunea Android pentru aceste funcționalități. Aplicația web acceptă plată cu numerar
    sau confirmare manuală pe terminal POS.
  </p>

</section>

<!-- Sticky cart bar -->
<div class="scanapp-cart-bar" id="scanapp-cart-bar">
  <div class="scanapp-cart-bar__summary">
    <div class="scanapp-cart-bar__count" id="scanapp-cart-count">0 bilete</div>
    <div class="scanapp-cart-bar__total" id="scanapp-cart-total">0 lei</div>
  </div>
  <button type="button" class="scanapp-btn scanapp-btn--primary" id="scanapp-checkout-btn">Plătește</button>
</div>

<!-- Payment sheet -->
<div class="scanapp-sheet-backdrop" id="scanapp-payment-sheet" role="dialog" aria-modal="true">
  <div class="scanapp-sheet">
    <div class="scanapp-sheet__handle"></div>
    <h2 class="scanapp-sheet__title">Alege metoda de plată</h2>
    <div id="scanapp-cart-preview" style="margin-bottom: 14px;"></div>
    <div class="scanapp-payment-options">
      <button type="button" class="scanapp-payment-btn scanapp-payment-btn--primary" data-method="cash">
        <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="2" y="6" width="20" height="12" rx="2"/><circle cx="12" cy="12" r="2"/></svg>
        Numerar
      </button>
      <button type="button" class="scanapp-payment-btn" data-method="card">
        <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="2" y="5" width="20" height="14" rx="2"/><line x1="2" y1="10" x2="22" y2="10"/></svg>
        Card POS
      </button>
    </div>
    <p class="scanapp-card__text scanapp-card__text--muted" style="margin-top: 12px;">
      Pentru plata cu card: confirmă încasarea pe terminalul tău POS bancar, apoi apasă <b>Card POS</b>.
    </p>
    <hr class="scanapp-divider">
    <button type="button" class="scanapp-btn scanapp-btn--block" id="scanapp-payment-cancel">Anulează</button>
  </div>
</div>

<!-- Success / QR sheet -->
<div class="scanapp-sheet-backdrop" id="scanapp-success-sheet" role="dialog" aria-modal="true">
  <div class="scanapp-sheet">
    <div class="scanapp-sheet__handle"></div>
    <h2 class="scanapp-sheet__title" id="scanapp-success-title">Plată reușită</h2>
    <p class="scanapp-card__text" id="scanapp-success-subtitle">Comandă finalizată.</p>
    <div class="scanapp-qr-display" id="scanapp-qr-display" hidden>
      <div id="scanapp-qr-host" style="width: 220px; height: 220px; display: flex; align-items: center; justify-content: center;"></div>
      <p class="scanapp-qr-display__hint">Clientul scanează acest cod cu telefonul pentru a primi biletele pe email.</p>
    </div>
    <div class="scanapp-claim-status" id="scanapp-claim-status" hidden>Aștept claim…</div>
    <hr class="scanapp-divider">
    <button type="button" class="scanapp-btn scanapp-btn--primary scanapp-btn--block" id="scanapp-success-done">Finalizează</button>
  </div>
</div>

<!-- Seating widget modal — same /seating/embed page the Android app's
     WebView loads. We iframe it here and listen for postMessage from the
     iframe (confirm / cancel / ready) so the behavior is 1:1 with mobile. -->
<div class="scanapp-seating-modal" id="scanapp-seating-modal" role="dialog" aria-modal="true">
  <div class="scanapp-seating-modal__inner">
    <div class="scanapp-seating-modal__header">
      <button type="button" class="scanapp-seating-modal__back" id="scanapp-seating-back">‹ Înapoi</button>
      <div class="scanapp-seating-modal__title">Selectează locuri</div>
      <span style="width: 64px;"></span>
    </div>
    <div class="scanapp-seating-modal__body">
      <div class="scanapp-seating-modal__loading" id="scanapp-seating-loading">
        <p style="color:#9CA3AF; font-size:13px;">Se pregătește harta…</p>
      </div>
      <iframe class="scanapp-seating-modal__iframe" id="scanapp-seating-iframe" allow="autoplay; fullscreen" referrerpolicy="strict-origin-when-cross-origin"></iframe>
    </div>
  </div>
</div>

<!-- QR generator: qrcode-generator (kazuhikoarase) — small, no deps, stable CDN. -->
<script src="https://cdn.jsdelivr.net/npm/qrcode-generator@1.4.4/qrcode.min.js" defer></script>
<?php $scanPageScript = 'vanzare.js'; require __DIR__ . '/_layout_end.php'; ?>
