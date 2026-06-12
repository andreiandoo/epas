<?php
$scanPage      = 'setari-scan';
$scanPageTitle = 'Setări scanner';
require __DIR__ . '/_layout.php';
?>
<section class="scanapp-section">

  <!-- Account info card -->
  <div class="scanapp-card">
    <div class="scanapp-card__title">Cont</div>
    <div class="scanapp-setting-row" style="border-bottom:0;">
      <div>
        <div class="scanapp-setting-row__label" id="scanapp-account-name">—</div>
        <div class="scanapp-setting-row__hint" id="scanapp-account-role">—</div>
      </div>
    </div>
  </div>

  <!-- Scanner settings -->
  <div class="scanapp-card">
    <div class="scanapp-card__title">Scanner</div>
    <div class="scanapp-setting-row">
      <div>
        <div class="scanapp-setting-row__label">Vibrație feedback</div>
        <div class="scanapp-setting-row__hint">Vibrează la fiecare scanare validă / duplicată / invalidă.</div>
      </div>
      <div class="scanapp-toggle" data-setting="vibrationFeedback" role="switch" tabindex="0"><div class="scanapp-toggle__knob"></div></div>
    </div>
    <div class="scanapp-setting-row">
      <div>
        <div class="scanapp-setting-row__label">Efecte sonore</div>
        <div class="scanapp-setting-row__hint">Sunet scurt la rezultate (beep generat în browser).</div>
      </div>
      <div class="scanapp-toggle" data-setting="soundEffects" role="switch" tabindex="0"><div class="scanapp-toggle__knob"></div></div>
    </div>
    <div class="scanapp-setting-row">
      <div>
        <div class="scanapp-setting-row__label">Confirmare automată</div>
        <div class="scanapp-setting-row__hint">Scanarea validă închide rezultatul automat și auto-check-in pe vânzări POS.</div>
      </div>
      <div class="scanapp-toggle" data-setting="autoConfirmValid" role="switch" tabindex="0"><div class="scanapp-toggle__knob"></div></div>
    </div>
  </div>

  <!-- Hardware status (read-only placeholders, web cannot drive POS NFC) -->
  <div class="scanapp-card">
    <div class="scanapp-card__title">Hardware</div>
    <div class="scanapp-setting-row">
      <div>
        <div class="scanapp-setting-row__label">Card reader</div>
        <div class="scanapp-setting-row__hint">Plata prin NFC (Stripe Tap) NU este suportată în versiunea web.</div>
      </div>
      <div style="color: var(--scanapp-text-ter); font-size: 12px; font-weight: 600;">— indisponibil</div>
    </div>
    <div class="scanapp-setting-row">
      <div>
        <div class="scanapp-setting-row__label">POS bancar fizic</div>
        <div class="scanapp-setting-row__hint">Conexiunea directă cu POS bancar NU este suportată în versiunea web.</div>
      </div>
      <div style="color: var(--scanapp-text-ter); font-size: 12px; font-weight: 600;">— indisponibil</div>
    </div>
  </div>

  <!-- Admin controls (only for admin / owner role) -->
  <div class="scanapp-card" id="scanapp-admin-section" hidden>
    <div class="scanapp-card__title">Administrare</div>
    <a class="scanapp-btn scanapp-btn--block" href="/organizator/scan/porti" style="margin-bottom: 8px;">
      Administrare porți de acces
    </a>
    <a class="scanapp-btn scanapp-btn--block" href="/organizator/scan/asignare-personal">
      Asignează personal la porți
    </a>
  </div>

  <!-- Install banners (Android APK redirect / iOS A2HS tutorial) -->
  <div class="scanapp-banner scanapp-banner--android" id="scanapp-android-banner" hidden>
    <div class="scanapp-banner__icon">📱</div>
    <div class="scanapp-banner__body">
      <div class="scanapp-banner__title">Folosești Android?</div>
      <div class="scanapp-banner__text">Aplicația nativă oferă o experiență mai bună pentru scanare îndelungată.</div>
      <a class="scanapp-banner__cta" href="https://ambilet.ro/android" target="_blank" rel="noopener">Descarcă APK Android</a>
    </div>
  </div>

  <div class="scanapp-banner scanapp-banner--ios" id="scanapp-ios-banner" hidden>
    <div class="scanapp-banner__icon">🍎</div>
    <div class="scanapp-banner__body">
      <div class="scanapp-banner__title">Folosești iPhone / iPad?</div>
      <div class="scanapp-banner__text">Atinge butonul <b>Distribuie</b> (pătrat cu săgeată) în Safari, apoi alege <b>Adaugă pe ecranul de start</b>.</div>
    </div>
  </div>

  <!-- Shift / Logout -->
  <div style="margin-top: 14px;">
    <button type="button" class="scanapp-btn scanapp-btn--danger scanapp-btn--block" id="scanapp-logout-btn">Deconectare</button>
  </div>

  <p class="scanapp-card__text scanapp-card__text--muted" style="margin-top: 14px; text-align: center;">
    Versiune aplicație web: <span id="scanapp-version">—</span>
  </p>

</section>

<script src="/assets/js/scan-app/pages/setari-scan.js?v=<?= filemtime(dirname(__DIR__, 2) . '/assets/js/scan-app/pages/setari-scan.js') ?>" defer></script>

<?php require __DIR__ . '/_layout_end.php'; ?>
