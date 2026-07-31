<?php
/**
 * COMPONENT: ticket-card  (a purchased ticket, account area)
 * Input: $ticket = [event, venue, date, time, seat_label, code, is_subscription, status]
 * The QR button calls kitQR.show(code, event) from kit.js.
 */
$t = $ticket ?? [];
if (!$t) return;
$b = kit_date_badge($t['date'] ?? '', Kit::get('locale', 'ro'));
$sub = !empty($t['is_subscription']);
?>
<div class="kit-ticket">
  <div class="kit-ticket__stub">
    <b><?= e((string)$b['day']) ?></b><span><?= e($b['month']) ?></span>
  </div>
  <div class="kit-ticket__body">
    <span class="kit-badge <?= $sub ? 'kit-badge--promoted' : 'kit-badge--soldout' ?>" style="background:<?= $sub ? '' : 'var(--kit-success)' ?>"><?= $sub ? 'Abonament' : 'Valid' ?></span>
    <h3 class="kit-display" style="font-size:1.05rem;margin:.35rem 0 .2rem"><?= e($t['event'] ?? '') ?></h3>
    <p class="kit-event-card__meta"><?= e(implode(' • ', array_filter([$t['time'] ?? '', $t['venue'] ?? '', $t['seat_label'] ?? '']))) ?></p>
  </div>
  <?php if (!empty($t['code'])): ?>
  <button type="button" class="kit-btn kit-btn--outline" onclick="window.kitQR&&kitQR.show(<?= e(json_encode($t['code'])) ?>,<?= e(json_encode($t['event'] ?? '')) ?>)">QR</button>
  <?php endif; ?>
</div>
