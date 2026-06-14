<?php
$scanPage      = 'rapoarte';
$scanPageTitle = 'Rapoarte';
require __DIR__ . '/_layout.php';
?>
<section class="scanapp-section">

  <!-- Page header: big title + 'Actualizat acum' subtitle with pulsing dot -->
  <div class="scanapp-reports-page-header">
    <h1 class="scanapp-reports-page-header__title">Rapoarte</h1>
    <div class="scanapp-reports-page-header__sub">
      <span class="scanapp-pulse-dot"></span>
      <span id="scanapp-report-event-name">Selectează un eveniment</span>
    </div>
  </div>

  <!-- Past-event selector (clickable row) -->
  <button type="button" class="scanapp-past-selector" id="scanapp-report-pick-event">
    <div class="scanapp-past-selector__left">
      <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 8v4l3 3"/><path d="M3 12a9 9 0 1018 0 9 9 0 00-18 0z"/></svg>
      Eveniment
    </div>
    <div class="scanapp-past-selector__right">
      <span class="scanapp-past-selector__value" id="scanapp-report-event-meta">Selectează</span>
      <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="var(--scanapp-text-tertiary)" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M6 9l6 6 6-6"/></svg>
    </div>
  </button>

  <!-- Top metrics -->
  <div class="scanapp-metrics-grid">
    <div class="scanapp-metric-card scanapp-metric-card--wide">
      <div class="scanapp-metric-card__label">Rata Check-in</div>
      <div class="scanapp-metric-card__value">
        <span id="scanapp-report-rate">0</span><span class="scanapp-metric-card__suffix">%</span>
      </div>
      <div style="margin-top: 8px; height: 4px; background: var(--scanapp-surface-hover); border-radius: 2px; overflow: hidden;">
        <div id="scanapp-report-rate-bar" style="height: 100%; width: 0%; background: var(--scanapp-purple); border-radius: 2px;"></div>
      </div>
    </div>
    <div class="scanapp-metrics-row">
      <div class="scanapp-metric-card">
        <div class="scanapp-metric-card__label">Total Vândute</div>
        <div class="scanapp-metric-card__value" id="scanapp-report-sold">0</div>
        <div style="font-size: 12px; color: var(--scanapp-text-tertiary); margin-top: 4px;" id="scanapp-report-sold-hint">—</div>
      </div>
      <div class="scanapp-metric-card">
        <div class="scanapp-metric-card__label">Intrați</div>
        <div class="scanapp-metric-card__value" id="scanapp-report-entered">0</div>
        <div style="font-size: 12px; color: var(--scanapp-text-tertiary); margin-top: 4px;" id="scanapp-report-entered-hint">—</div>
      </div>
    </div>
    <div class="scanapp-metric-card">
      <div class="scanapp-metric-card__label">Venituri</div>
      <div class="scanapp-metric-card__value" id="scanapp-report-revenue">0 lei</div>
    </div>
  </div>

  <!-- Performanță Porților (Tipuri bilete) -->
  <div class="scanapp-section-title" style="margin-top: 0; margin-bottom: 12px;">Performanța Porților</div>
  <div class="scanapp-section-card">
    <div class="scanapp-report-bars" id="scanapp-report-types">
      <div style="color: var(--scanapp-text-tertiary); font-size: 13px; text-align: center; padding: 12px;">Se încarcă defalcarea…</div>
    </div>
  </div>

  <!-- Activitate Recentă -->
  <div class="scanapp-section-title">Activitate Recentă</div>
  <div class="scanapp-section-card" id="scanapp-report-activity">
    <div style="color: var(--scanapp-text-tertiary); font-size: 13px;">Nicio activitate înregistrată pe acest dispozitiv.</div>
  </div>

  <!-- Export -->
  <button type="button" class="scanapp-export-btn" id="scanapp-report-export">
    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4M7 10l5 5 5-5M12 15V3"/></svg>
    Exportă Raport
  </button>
</section>

<!-- Event picker sheet (with year/month/search filters) -->
<div class="scanapp-sheet-backdrop" id="scanapp-event-sheet" role="dialog" aria-modal="true">
  <div class="scanapp-sheet" style="max-height: 92vh;">
    <div class="scanapp-sheet__handle"></div>
    <h2 class="scanapp-sheet__title">Alege un eveniment</h2>

    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 8px; margin-bottom: 8px;">
      <select id="scanapp-event-filter-year" class="scanapp-input" style="text-transform: none; letter-spacing: 0; padding: 10px 12px; font-size: 13px;">
        <option value="">Toți anii</option>
      </select>
      <select id="scanapp-event-filter-month" class="scanapp-input" style="text-transform: none; letter-spacing: 0; padding: 10px 12px; font-size: 13px;">
        <option value="">Toate lunile</option>
        <option value="1">Ianuarie</option><option value="2">Februarie</option><option value="3">Martie</option>
        <option value="4">Aprilie</option><option value="5">Mai</option><option value="6">Iunie</option>
        <option value="7">Iulie</option><option value="8">August</option><option value="9">Septembrie</option>
        <option value="10">Octombrie</option><option value="11">Noiembrie</option><option value="12">Decembrie</option>
      </select>
    </div>
    <input type="search" id="scanapp-event-filter-search" class="scanapp-input" style="text-transform: none; letter-spacing: 0; margin-bottom: 12px;" placeholder="Caută după nume eveniment…">
    <div id="scanapp-event-sheet-body" style="max-height: 50vh; overflow-y: auto;"></div>
    <hr class="scanapp-divider">
    <button type="button" class="scanapp-btn scanapp-btn--block" id="scanapp-event-sheet-close">Închide</button>
  </div>
</div>

<?php $scanPageScript = 'rapoarte.js'; require __DIR__ . '/_layout_end.php'; ?>
