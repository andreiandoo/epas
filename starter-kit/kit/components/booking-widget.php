<?php
/**
 * COMPONENT: booking-widget  (leisure — the seat-map's counterpart)
 *
 * Input: $bookables (canonical bookable[], req) — usually kit_bookables() or
 *        one rental's `options`.
 *        $title (string, optional) — heading above the picker.
 *
 * A leisure venue sells "a day and a slot", not a seat. This renders the SSR
 * skeleton; kit.js hydrates it through the proxy:
 *   availability?ticket_type&month  → which days are open / limited / sold out
 *   slots?ticket_type&date          → the intervals inside the chosen day
 * Choosing a slot + duration + quantity writes a cart line carrying
 * capacity_id / visit_date / slot_time / duration_minutes, which the checkout
 * forwards so the backend can hold the place under a row lock.
 *
 * Like seat-map, this component NEVER fetches: it only emits the mount plus the
 * data attributes the hydrator needs.
 */
$bookables = $bookables ?? [];
if (!$bookables) return;
$title = $title ?? kit_term('buy', t('booking.title'));
?>
<div class="kit-booking"
     data-component="booking"
     data-bookables="<?= e(json_encode(array_map(fn ($b) => [
         'id'       => $b['ticket_type_id'],
         'name'     => $b['name'],
         'price'    => $b['price'],
         'currency' => $b['currency'],
         'variants' => $b['variants'],
         'event_id' => $b['event_id'],
         'title'    => $b['event_title'] ?: $b['name'],
     ], $bookables), JSON_UNESCAPED_UNICODE)) ?>">

  <h3 class="kit-display kit-booking__title"><?= e($title) ?></h3>

  <?php if (count($bookables) > 1): ?>
    <div class="kit-booking__field">
      <label class="kit-booking__label"><?= e(t('booking.choose_type')) ?></label>
      <select class="kit-booking__select" data-booking-type>
        <?php foreach ($bookables as $i => $b): ?>
          <option value="<?= (int) $b['ticket_type_id'] ?>"><?= e($b['name']) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
  <?php endif; ?>

  <div class="kit-booking__field">
    <label class="kit-booking__label"><?= e(t('booking.choose_date')) ?></label>
    <div class="kit-booking__cal">
      <div class="kit-booking__calhead">
        <button type="button" class="kit-booking__nav" data-booking-prev aria-label="<?= e(t('common.back')) ?>">‹</button>
        <strong data-booking-month></strong>
        <button type="button" class="kit-booking__nav" data-booking-next aria-label="<?= e(t('common.next')) ?>">›</button>
      </div>
      <div class="kit-booking__grid" data-booking-days></div>
      <p class="kit-muted kit-booking__legend">
        <span class="kit-booking__dot" data-status="available"></span> <?= e(t('booking.free')) ?>
        <span class="kit-booking__dot" data-status="limited"></span> <?= e(t('booking.limited')) ?>
        <span class="kit-booking__dot" data-status="sold_out"></span> <?= e(t('booking.sold_out')) ?>
      </p>
    </div>
  </div>

  <div class="kit-booking__field" data-booking-slots-wrap hidden>
    <label class="kit-booking__label"><?= e(t('booking.choose_slot')) ?></label>
    <div class="kit-booking__slots" data-booking-slots></div>
  </div>

  <div class="kit-booking__field" data-booking-variants-wrap hidden>
    <label class="kit-booking__label"><?= e(t('booking.choose_duration')) ?></label>
    <div class="kit-booking__slots" data-booking-variants></div>
  </div>

  <div class="kit-booking__field">
    <label class="kit-booking__label"><?= e(t('booking.people')) ?></label>
    <div class="kit-qty">
      <button type="button" data-booking-minus aria-label="minus">−</button>
      <span data-booking-qty>1</span>
      <button type="button" data-booking-plus aria-label="plus">+</button>
    </div>
  </div>

  <div class="kit-booking__total">
    <span><?= e(t('common.total')) ?></span>
    <strong data-booking-total>—</strong>
  </div>
  <p class="kit-booking__error" data-booking-error hidden></p>
  <button type="button" class="kit-btn kit-btn--primary kit-booking__cta" data-booking-add disabled>
    <?= e(kit_term('buy', t('common.add_to_cart'))) ?>
  </button>
</div>
