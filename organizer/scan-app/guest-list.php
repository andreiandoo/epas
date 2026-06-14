<?php
$scanPage      = 'panou';
$scanPageTitle = 'Listă invitați';
require __DIR__ . '/_layout.php';
?>
<section class="scanapp-section">
  <div class="scanapp-card">
    <div class="scanapp-card__title">Listă invitați</div>
    <p class="scanapp-card__text">Caută și gestionează participanții. Mirror al modalului GuestList din aplicația mobilă.</p>
  </div>

  <div class="scanapp-stats-grid" style="grid-template-columns: 1fr 1fr 1fr;">
    <div class="scanapp-stat-card" style="cursor:default; padding: 10px;">
      <div class="scanapp-stat-card__label">Total</div>
      <div class="scanapp-stat-card__value" id="scanapp-guest-total" style="font-size: 18px;">0</div>
    </div>
    <div class="scanapp-stat-card" style="cursor:default; padding: 10px;">
      <div class="scanapp-stat-card__label">Intrați</div>
      <div class="scanapp-stat-card__value" id="scanapp-guest-checked" style="font-size: 18px;">0</div>
    </div>
    <div class="scanapp-stat-card" style="cursor:default; padding: 10px;">
      <div class="scanapp-stat-card__label">Lipsesc</div>
      <div class="scanapp-stat-card__value" id="scanapp-guest-missing" style="font-size: 18px;">0</div>
    </div>
  </div>

  <!-- Filter pills -->
  <div class="scanapp-stat-pills" id="scanapp-guest-filters">
    <button type="button" class="scanapp-stat-pill scanapp-stat-pill--active" data-filter="all">Toți</button>
    <button type="button" class="scanapp-stat-pill" data-filter="checked">Intrați</button>
    <button type="button" class="scanapp-stat-pill" data-filter="missing">Neintrați</button>
  </div>

  <!-- Search -->
  <input type="search" class="scanapp-input" id="scanapp-guest-search" placeholder="Caută după nume, email sau cod bilet…" style="text-transform: none; letter-spacing: 0;">

  <!-- List -->
  <div id="scanapp-guest-list">
    <div class="scanapp-card scanapp-card--placeholder">
      <p class="scanapp-card__text">Se încarcă lista de invitați…</p>
    </div>
  </div>

  <div style="margin-top: 14px;">
    <a class="scanapp-btn scanapp-btn--block" href="/organizator/scan/panou">‹ Înapoi la panou</a>
  </div>
</section>

<?php $scanPageScript = 'guest-list.js'; require __DIR__ . '/_layout_end.php'; ?>
