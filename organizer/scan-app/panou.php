<?php
$scanPage      = 'panou';
$scanPageTitle = 'Panou';
require __DIR__ . '/_layout.php';
?>
<section class="scanapp-section">

  <!-- Hero card: selected event name + countdown -->
  <div class="scanapp-hero" id="scanapp-hero">
    <div class="scanapp-hero__label">Eveniment activ</div>
    <div class="scanapp-hero__name" id="scanapp-hero-name">Selectează un eveniment</div>
    <div class="scanapp-hero__meta" id="scanapp-hero-meta">—</div>
  </div>

  <!-- Admin view: stat cards + quick actions -->
  <div id="scanapp-admin-view" hidden>
    <div class="scanapp-section-title">Statistici live</div>
    <div class="scanapp-stats-grid">
      <button type="button" class="scanapp-stat-card" data-modal="entered">
        <div class="scanapp-stat-card__label">Intrați</div>
        <div class="scanapp-stat-card__value" id="scanapp-stat-entered">0</div>
        <div class="scanapp-stat-card__hint" id="scanapp-stat-entered-hint">din 0</div>
        <div class="scanapp-progress"><div class="scanapp-progress__fill" id="scanapp-entered-bar" style="width: 0%"></div></div>
      </button>
      <button type="button" class="scanapp-stat-card" data-modal="sold">
        <div class="scanapp-stat-card__label">Bilete vândute</div>
        <div class="scanapp-stat-card__value" id="scanapp-stat-sold">0</div>
        <div class="scanapp-stat-card__hint">click pentru defalcare</div>
      </button>
      <button type="button" class="scanapp-stat-card" data-modal="revenue">
        <div class="scanapp-stat-card__label">Venituri</div>
        <div class="scanapp-stat-card__value" id="scanapp-stat-revenue">0 lei</div>
        <div class="scanapp-stat-card__hint">online vs POS</div>
      </button>
      <button type="button" class="scanapp-stat-card" data-modal="remaining">
        <div class="scanapp-stat-card__label">Rămase</div>
        <div class="scanapp-stat-card__value" id="scanapp-stat-remaining">0</div>
        <div class="scanapp-stat-card__hint">click pentru defalcare</div>
      </button>
    </div>

    <div class="scanapp-section-title">Acțiuni rapide</div>
    <div class="scanapp-quick-grid">
      <a class="scanapp-quick-action" href="/organizator/scan/scanare">
        <span class="scanapp-quick-action__icon">
          <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M23 19a2 2 0 01-2 2H3a2 2 0 01-2-2V8a2 2 0 012-2h4l2-3h6l2 3h4a2 2 0 012 2z"/><circle cx="12" cy="13" r="4"/></svg>
        </span>
        Scanează
      </a>
      <a class="scanapp-quick-action" href="/organizator/scan/vanzare">
        <span class="scanapp-quick-action__icon">
          <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="9" cy="21" r="1"/><circle cx="20" cy="21" r="1"/><path d="M1 1h4l2.68 13.39a2 2 0 002 1.61h9.72a2 2 0 002-1.61L23 6H6"/></svg>
        </span>
        Vinde
      </a>
      <a class="scanapp-quick-action" href="/organizator/scan/guest-list">
        <span class="scanapp-quick-action__icon">
          <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17 20h5v-2a3 3 0 00-5.356-1.857"/><circle cx="9" cy="7" r="4"/><path d="M16 3.13a4 4 0 010 7.75M22 21v-2a4 4 0 00-3-3.87"/></svg>
        </span>
        Listă invitați
      </a>
      <a class="scanapp-quick-action" href="/organizator/scan/asignare-personal">
        <span class="scanapp-quick-action__icon">
          <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17 20h5v-2a3 3 0 00-5.356-1.857"/><path d="M9 11a4 4 0 100-8 4 4 0 000 8z"/></svg>
        </span>
        Echipă
      </a>
    </div>

    <button type="button" class="scanapp-btn scanapp-btn--danger scanapp-btn--block" id="scanapp-action-close-shift" style="margin-top: 14px;">Închide tura</button>
  </div>

  <!-- Scanner view: personal turnover + shortcuts -->
  <div id="scanapp-scanner-view" hidden>
    <div class="scanapp-section-title">Activitatea ta în tură</div>
    <div class="scanapp-stats-grid">
      <div class="scanapp-stat-card" style="cursor:default;">
        <div class="scanapp-stat-card__label">Numerar</div>
        <div class="scanapp-stat-card__value" id="scanapp-shift-cash">0 lei</div>
      </div>
      <div class="scanapp-stat-card" style="cursor:default;">
        <div class="scanapp-stat-card__label">Card</div>
        <div class="scanapp-stat-card__value" id="scanapp-shift-card">0 lei</div>
      </div>
      <div class="scanapp-stat-card" style="cursor:default;">
        <div class="scanapp-stat-card__label">Scanări</div>
        <div class="scanapp-stat-card__value" id="scanapp-shift-scans">0</div>
      </div>
      <div class="scanapp-stat-card" style="cursor:default;">
        <div class="scanapp-stat-card__label">Vânzări</div>
        <div class="scanapp-stat-card__value" id="scanapp-shift-sales">0</div>
      </div>
    </div>

    <div class="scanapp-section-title">Durată tură</div>
    <div class="scanapp-card">
      <div style="display:flex; align-items:center; justify-content:space-between;">
        <span style="color: var(--scanapp-text-sec); font-size: 13px;">Tura ta a început la</span>
        <span id="scanapp-shift-start" style="font-weight: 700;">—</span>
      </div>
      <div style="display:flex; align-items:center; justify-content:space-between; margin-top:8px;">
        <span style="color: var(--scanapp-text-sec); font-size: 13px;">Durată</span>
        <span id="scanapp-shift-duration" style="font-weight: 700;">—</span>
      </div>
    </div>

    <div style="margin-top: 14px; display: grid; grid-template-columns: 1fr 1fr; gap: 8px;">
      <a class="scanapp-btn scanapp-btn--primary" href="/organizator/scan/scanare">Începe scanarea</a>
      <a class="scanapp-btn scanapp-btn--primary" href="/organizator/scan/vanzare">Începe vânzarea</a>
    </div>
    <button type="button" class="scanapp-btn scanapp-btn--danger scanapp-btn--block" id="scanapp-scanner-close-shift" style="margin-top: 8px;">Închide tura</button>
  </div>

</section>

<!-- Generic sheet container — title + body filled dynamically by panou.js -->
<div class="scanapp-sheet-backdrop" id="scanapp-sheet" role="dialog" aria-modal="true">
  <div class="scanapp-sheet">
    <div class="scanapp-sheet__handle"></div>
    <h2 class="scanapp-sheet__title" id="scanapp-sheet-title">—</h2>
    <div id="scanapp-sheet-body"></div>
    <hr class="scanapp-divider">
    <button type="button" class="scanapp-btn scanapp-btn--block" id="scanapp-sheet-close">Închide</button>
  </div>
</div>

<?php $scanPageScript = 'panou.js'; require __DIR__ . '/_layout_end.php'; ?>
