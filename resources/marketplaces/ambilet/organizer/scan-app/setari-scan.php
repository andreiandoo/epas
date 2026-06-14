<?php
$scanPage      = 'setari-scan';
$scanPageTitle = 'Setări';
require __DIR__ . '/_layout.php';
?>
<section class="scanapp-section">

  <!-- Page header -->
  <div class="scanapp-settings-page-header">
    <h1 class="scanapp-settings-page-header__title">Setări</h1>
  </div>

  <!-- Account section -->
  <div class="scanapp-settings-section-header">Cont</div>
  <div class="scanapp-section-card">
    <div class="scanapp-info-row">
      <span class="scanapp-info-row__label">Nume</span>
      <span class="scanapp-info-row__value" id="scanapp-account-name">—</span>
    </div>
    <div class="scanapp-divider-line"></div>
    <div class="scanapp-info-row">
      <span class="scanapp-info-row__label">Rol</span>
      <span class="scanapp-info-row__value" id="scanapp-account-role">—</span>
    </div>
    <div class="scanapp-divider-line"></div>
    <div class="scanapp-info-row">
      <span class="scanapp-info-row__label">Organizator</span>
      <span class="scanapp-info-row__value" id="scanapp-account-org">—</span>
    </div>
  </div>

  <!-- Scanner section -->
  <div class="scanapp-settings-section-header">Scanner</div>
  <div class="scanapp-section-card">
    <div class="scanapp-setting-row">
      <div class="scanapp-setting-row__label">Vibrație</div>
      <div class="scanapp-toggle" data-setting="vibrationFeedback" role="switch" tabindex="0"><div class="scanapp-toggle__knob"></div></div>
    </div>
    <div class="scanapp-divider-line"></div>
    <div class="scanapp-setting-row">
      <div class="scanapp-setting-row__label">Efecte Sonore</div>
      <div class="scanapp-toggle" data-setting="soundEffects" role="switch" tabindex="0"><div class="scanapp-toggle__knob"></div></div>
    </div>
    <div class="scanapp-divider-line"></div>
    <div class="scanapp-setting-row">
      <div class="scanapp-setting-row__label">Auto-confirmare Valide</div>
      <div class="scanapp-toggle" data-setting="autoConfirmValid" role="switch" tabindex="0"><div class="scanapp-toggle__knob"></div></div>
    </div>
  </div>

  <!-- Hardware -->
  <div class="scanapp-settings-section-header">Hardware</div>
  <div class="scanapp-section-card">
    <div class="scanapp-status-badge-row">
      <span class="scanapp-status-badge-row__label">Card prin NFC</span>
      <span class="scanapp-status-badge">Indisponibil pe web</span>
    </div>
    <div class="scanapp-divider-line"></div>
    <div class="scanapp-status-badge-row">
      <span class="scanapp-status-badge-row__label">POS bancar fizic</span>
      <span class="scanapp-status-badge">Indisponibil pe web</span>
    </div>
    <div class="scanapp-info-box">
      <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
      <div class="scanapp-info-box__text">Pentru plata cu card NFC (Stripe Tap) sau conexiune POS bancar, folosește aplicația Android.</div>
    </div>
  </div>

  <!-- Admin Controls -->
  <div id="scanapp-admin-section" hidden>
    <div class="scanapp-settings-section-header">Comenzi Admin</div>
    <div class="scanapp-section-card">
      <div class="scanapp-admin-badge" style="margin-bottom: 6px;">
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>
        Acces Administrator
      </div>
      <div class="scanapp-divider-line"></div>
      <a class="scanapp-admin-row" href="/organizator/scan/porti">
        <span class="scanapp-admin-row__label">Administrare Porți</span>
        <span class="scanapp-admin-row__right">
          <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M9 18l6-6-6-6"/></svg>
        </span>
      </a>
      <div class="scanapp-divider-line"></div>
      <a class="scanapp-admin-row" href="/organizator/scan/asignare-personal">
        <span class="scanapp-admin-row__label">Asignare Personal</span>
        <span class="scanapp-admin-row__right">
          <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M9 18l6-6-6-6"/></svg>
        </span>
      </a>
    </div>
  </div>

  <!-- Install banners (Android/iOS install prompts) -->
  <div id="scanapp-android-banner" hidden style="margin-top: 16px;">
    <div class="scanapp-info-box">
      <svg width="18" height="18" viewBox="0 0 24 24" fill="currentColor"><path d="M17.523 15.342c-.515 0-.937-.421-.937-.937s.421-.937.937-.937.937.421.937.937-.421.937-.937.937m-11.046 0c-.515 0-.937-.421-.937-.937s.421-.937.937-.937.937.421.937.937-.421.937-.937.937m11.383-6.235l1.873-3.241a.39.39 0 00-.142-.532.39.39 0 00-.532.142l-1.896 3.286a11.66 11.66 0 00-9.527 0L5.74 5.476a.39.39 0 00-.532-.142.39.39 0 00-.142.532l1.873 3.241C3.751 10.768 1.55 13.876 1.18 17.519H22.82c-.371-3.643-2.572-6.751-5.96-8.412"/></svg>
      <div class="scanapp-info-box__text">
        Folosești Android? Aplicația nativă oferă o experiență mai bună.
        <a href="https://ambilet.ro/android" target="_blank" rel="noopener" style="display: inline-block; margin-top: 6px; color: var(--scanapp-purple); font-weight: 700; text-decoration: none;">Descarcă APK →</a>
      </div>
    </div>
  </div>

  <div id="scanapp-ios-banner" hidden style="margin-top: 16px;">
    <div class="scanapp-info-box">
      <svg width="18" height="18" viewBox="0 0 24 24" fill="currentColor"><path d="M17.05 20.28c-.98.95-2.05.8-3.08.35-1.09-.46-2.09-.48-3.24 0-1.44.62-2.2.44-3.06-.35C2.79 15.25 3.51 7.59 9.05 7.31c1.35.07 2.29.74 3.08.8 1.18-.24 2.31-.93 3.57-.84 1.51.12 2.65.72 3.4 1.8-3.12 1.87-2.38 5.98.48 7.13-.57 1.5-1.31 2.99-2.54 4.09zM12.03 7.25c-.15-2.23 1.66-4.07 3.74-4.25.29 2.58-2.34 4.5-3.74 4.25z"/></svg>
      <div class="scanapp-info-box__text">
        Folosești iPhone? Atinge butonul <b>Distribuie</b> în Safari, apoi <b>Adaugă pe ecranul de start</b>.
      </div>
    </div>
  </div>

  <!-- Logout -->
  <button type="button" class="scanapp-logout-btn" id="scanapp-logout-btn">
    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M9 21H5a2 2 0 01-2-2V5a2 2 0 012-2h4M16 17l5-5-5-5M21 12H9"/></svg>
    Deconectare
  </button>

  <p style="margin-top: 14px; text-align: center; color: var(--scanapp-text-quaternary); font-size: 11px;">
    Versiune aplicație web: <span id="scanapp-version">—</span>
  </p>
</section>

<?php $scanPageScript = 'setari-scan.js'; require __DIR__ . '/_layout_end.php'; ?>
