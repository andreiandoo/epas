<?php
$scanPage      = 'setari-scan';
$scanPageTitle = 'Asignare personal';
require __DIR__ . '/_layout.php';
?>
<section class="scanapp-section">
  <div class="scanapp-card">
    <div class="scanapp-card__title">Personal asignat</div>
    <p class="scanapp-card__text">Selectează porțile pentru fiecare membru al echipei.</p>
  </div>

  <div id="scanapp-staff-list">
    <div class="scanapp-card scanapp-card--placeholder">
      <p class="scanapp-card__text">Se încarcă echipa…</p>
    </div>
  </div>

  <div style="margin-top: 14px;">
    <a class="scanapp-btn scanapp-btn--block" href="/organizator/scan/setari-scan">‹ Înapoi la setări</a>
  </div>
</section>

<!-- Edit assignment sheet -->
<div class="scanapp-sheet-backdrop" id="scanapp-staff-sheet" role="dialog" aria-modal="true">
  <div class="scanapp-sheet">
    <div class="scanapp-sheet__handle"></div>
    <h2 class="scanapp-sheet__title" id="scanapp-staff-sheet-title">Asignează poartă</h2>
    <p class="scanapp-card__text" id="scanapp-staff-sheet-member">—</p>
    <div id="scanapp-staff-sheet-gates" style="margin-top: 12px;"></div>
    <hr class="scanapp-divider">
    <button type="button" class="scanapp-btn scanapp-btn--block" id="scanapp-staff-sheet-close">Închide</button>
  </div>
</div>

<?php $scanPageScript = 'asignare-personal.js'; require __DIR__ . '/_layout_end.php'; ?>
