<?php
$scanPage      = 'rapoarte';
$scanPageTitle = 'Rapoarte';
require __DIR__ . '/_layout.php';
?>
<section class="scanapp-section">

  <!-- Hero -->
  <div class="scanapp-hero">
    <div class="scanapp-hero__label">Eveniment</div>
    <div class="scanapp-hero__name" id="scanapp-report-event-name">Selectează un eveniment</div>
    <div class="scanapp-hero__meta" id="scanapp-report-event-meta">—</div>
  </div>

  <!-- Top stat cards -->
  <div class="scanapp-stats-grid">
    <div class="scanapp-stat-card" style="cursor:default;">
      <div class="scanapp-stat-card__label">Rată check-in</div>
      <div class="scanapp-stat-card__value" id="scanapp-report-rate">0%</div>
      <div class="scanapp-progress"><div class="scanapp-progress__fill" id="scanapp-report-rate-bar" style="width:0%"></div></div>
    </div>
    <div class="scanapp-stat-card" style="cursor:default;">
      <div class="scanapp-stat-card__label">Total vândute</div>
      <div class="scanapp-stat-card__value" id="scanapp-report-sold">0</div>
      <div class="scanapp-stat-card__hint" id="scanapp-report-sold-hint">din 0</div>
    </div>
    <div class="scanapp-stat-card" style="cursor:default;">
      <div class="scanapp-stat-card__label">Intrați</div>
      <div class="scanapp-stat-card__value" id="scanapp-report-entered">0</div>
      <div class="scanapp-stat-card__hint" id="scanapp-report-entered-hint">0 de scanat</div>
    </div>
    <div class="scanapp-stat-card" style="cursor:default;">
      <div class="scanapp-stat-card__label">Venituri</div>
      <div class="scanapp-stat-card__value" id="scanapp-report-revenue">0 lei</div>
      <div class="scanapp-stat-card__hint">brut</div>
    </div>
  </div>

  <!-- Gate / ticket type performance -->
  <div class="scanapp-section-title">Performanță tipuri de bilete</div>
  <div class="scanapp-report-bars" id="scanapp-report-types">
    <div class="scanapp-card scanapp-card--placeholder">
      <p class="scanapp-card__text">Se încarcă defalcarea pe tipuri…</p>
    </div>
  </div>

  <!-- Recent activity (from app context) -->
  <div class="scanapp-section-title">Activitate recentă (tură curentă)</div>
  <div class="scanapp-card" id="scanapp-report-activity">
    <p class="scanapp-card__text scanapp-card__text--muted">Datele se populează automat pe măsură ce scanezi sau vinzi bilete.</p>
  </div>

  <!-- Export -->
  <div style="margin-top: 14px;">
    <button type="button" class="scanapp-btn scanapp-btn--block" id="scanapp-report-export">Exportă participanți (CSV)</button>
  </div>

  <!-- Event picker (other events with reports) -->
  <div style="margin-top: 14px;">
    <button type="button" class="scanapp-btn scanapp-btn--block" id="scanapp-report-pick-event">Schimbă evenimentul</button>
  </div>

</section>

<!-- Event picker sheet -->
<div class="scanapp-sheet-backdrop" id="scanapp-event-sheet" role="dialog" aria-modal="true">
  <div class="scanapp-sheet">
    <div class="scanapp-sheet__handle"></div>
    <h2 class="scanapp-sheet__title">Alege un eveniment</h2>
    <div id="scanapp-event-sheet-body"></div>
    <hr class="scanapp-divider">
    <button type="button" class="scanapp-btn scanapp-btn--block" id="scanapp-event-sheet-close">Închide</button>
  </div>
</div>

<?php $scanPageScript = 'rapoarte.js'; require __DIR__ . '/_layout_end.php'; ?>
