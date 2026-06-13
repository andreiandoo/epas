<?php
$scanPage      = 'setari-scan';
$scanPageTitle = 'Porți de acces';
require __DIR__ . '/_layout.php';
?>
<section class="scanapp-section">
  <div class="scanapp-card">
    <div class="scanapp-card__title">Porți de acces</div>
    <p class="scanapp-card__text">Adaugă și gestionează porțile prin care intră participanții.</p>
  </div>

  <div id="scanapp-gates-list">
    <div class="scanapp-card scanapp-card--placeholder">
      <p class="scanapp-card__text">Se încarcă porțile…</p>
    </div>
  </div>

  <div style="margin-top: 14px;">
    <button type="button" class="scanapp-btn scanapp-btn--primary scanapp-btn--block" id="scanapp-gate-add">+ Adaugă poartă nouă</button>
  </div>

  <div style="margin-top: 14px;">
    <a class="scanapp-btn scanapp-btn--block" href="/organizator/scan/setari-scan">‹ Înapoi la setări</a>
  </div>
</section>

<!-- Add / edit gate sheet -->
<div class="scanapp-sheet-backdrop" id="scanapp-gate-sheet" role="dialog" aria-modal="true">
  <div class="scanapp-sheet">
    <div class="scanapp-sheet__handle"></div>
    <h2 class="scanapp-sheet__title" id="scanapp-gate-sheet-title">Poartă nouă</h2>
    <label class="scanapp-card__text" for="scanapp-gate-name" style="display:block; margin-bottom:6px;">Nume poartă</label>
    <input type="text" class="scanapp-input" id="scanapp-gate-name" placeholder="Ex: Intrare A">
    <label class="scanapp-card__text" for="scanapp-gate-capacity" style="display:block; margin: 12px 0 6px;">Capacitate (opțional)</label>
    <input type="number" class="scanapp-input" id="scanapp-gate-capacity" min="0" placeholder="Ex: 500" style="text-transform: none; letter-spacing: 0;">
    <hr class="scanapp-divider">
    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 8px;">
      <button type="button" class="scanapp-btn" id="scanapp-gate-cancel">Anulează</button>
      <button type="button" class="scanapp-btn scanapp-btn--primary" id="scanapp-gate-save">Salvează</button>
    </div>
    <button type="button" class="scanapp-btn scanapp-btn--danger scanapp-btn--block" id="scanapp-gate-delete" hidden style="margin-top: 8px;">Șterge poarta</button>
  </div>
</div>

<?php $scanPageScript = 'porti.js'; require __DIR__ . '/_layout_end.php'; ?>
