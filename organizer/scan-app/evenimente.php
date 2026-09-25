<?php
// Venue app only (/venue/scan/evenimente): the venue's events, like the
// Evenimente tab of the Android app. Tapping an event opens its details and
// the Scanare / Vânzare / Participanți actions.
$scanPage       = 'evenimente';
$scanPageTitle  = 'Evenimente';
$scanPageScript = 'evenimente.js';
require __DIR__ . '/_layout.php';
?>
<section class="scanapp-section">
  <div class="scanapp-venue-head">
    <div class="scanapp-venue-head__title">Evenimentele din locația ta</div>
    <div class="scanapp-venue-head__sub" id="scanapp-venue-name">—</div>
  </div>

  <div class="scanapp-venue-seg" role="tablist">
    <button type="button" class="scanapp-venue-seg__btn scanapp-venue-seg__btn--active" data-scope="upcoming" role="tab">Viitoare</button>
    <button type="button" class="scanapp-venue-seg__btn" data-scope="past" role="tab">Trecute</button>
  </div>

  <div id="scanapp-venue-events">
    <div class="scanapp-card scanapp-card--placeholder"><p class="scanapp-card__text">Se încarcă evenimentele…</p></div>
  </div>
</section>

<!-- Event details sheet -->
<div class="scanapp-sheet-backdrop" id="scanapp-venue-event-sheet" aria-hidden="true">
  <div class="scanapp-sheet" role="dialog" aria-modal="true">
    <div class="scanapp-sheet__handle"></div>
    <div id="scanapp-venue-event-detail"></div>
  </div>
</div>
<?php require __DIR__ . '/_layout_end.php'; ?>
