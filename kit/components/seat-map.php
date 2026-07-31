<?php
/**
 * COMPONENT: seat-map  (reserved-seating selector)
 *
 * SSR renders only the MOUNT + legend; the geometry, availability, holds and
 * pricing are hydrated client-side from the seating proxy (see kit/kit.js →
 * kitSeatMap). This mirrors how teatru/ambilet do seating: session-based holds
 * via /api/public/*, forwarded through the site's api/proxy.php.
 *
 * Input:
 *   $event canonical event — REQUIRED  (needs id)
 *   $checkout_url  where "continuă" goes (default /cos)
 */
$event = $event ?? [];
if (!$event) return;
$checkout = $checkout_url ?? ($__cfg['cart_url'] ?? '/cos');
?>
<div class="kit-seatmap"
     data-component="seat-map"
     data-event-id="<?= (int)$event['id'] ?>"
     data-checkout="<?= e($checkout) ?>"
     data-currency="<?= e($event['currency']) ?>"
     data-title="<?= e($event['title']) ?>"
     data-image="<?= e($event['poster_url']) ?>">
  <div class="kit-seatmap__stage">SCENA</div>
  <div class="kit-seatmap__canvas" data-seatmap-canvas>
    <div class="kit-skeleton" style="height:260px"></div>
  </div>
  <div class="kit-seatmap__legend">
    <span><i style="background:var(--kit-seat-free)"></i> Liber</span>
    <span><i style="background:var(--kit-seat-selected)"></i> Selectat</span>
    <span><i style="background:var(--kit-seat-sold)"></i> Ocupat</span>
  </div>
  <div class="kit-seatmap__summary" data-seatmap-summary hidden>
    <span data-seatmap-count>0 locuri</span>
    <strong data-seatmap-total></strong>
    <a class="kit-btn kit-btn--primary" data-seatmap-continue href="<?= e($checkout) ?>">Continuă</a>
  </div>
</div>
