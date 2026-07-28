<?php
/**
 * COMPONENT: rental-card  (leisure)
 * Input: $rental (canonical rental, req) — from kit_rentals().
 *
 * One pool of identical units (kayaks, bikes, lockers): image, live
 * availability, cheapest duration, and the duration options as chips.
 * Links to the booking page for that pool.
 */
$rental = $rental ?? [];
if (!$rental) return;
$href  = $rental['options'][0]['url'] ?? '#';
$avail = $rental['available'];
$total = $rental['total'];
$out   = $avail !== null && (int) $avail <= 0;
?>
<article class="kit-rental<?= $out ? ' is-unavailable' : '' ?>">
  <a class="kit-rental__media" href="<?= e($href) ?>">
    <?php if (!empty($rental['image_url'])): ?>
      <img src="<?= e($rental['image_url']) ?>" alt="<?= e($rental['name']) ?>" loading="lazy">
    <?php elseif (!empty($rental['icon'])): ?>
      <span class="kit-rental__icon" aria-hidden="true"><?= e($rental['icon']) ?></span>
    <?php endif; ?>
    <?php if ($out): ?>
      <span class="kit-badge kit-badge--soldout"><?= e(t('booking.all_out')) ?></span>
    <?php elseif ($avail !== null): ?>
      <span class="kit-badge kit-rental__stock"><?= e(t('booking.available_now', ['n' => (int) $avail])) ?></span>
    <?php endif; ?>
  </a>

  <div class="kit-rental__body">
    <h3 class="kit-rental__title"><a href="<?= e($href) ?>"><?= e($rental['name']) ?></a></h3>

    <?php if (!empty($rental['description'])): ?>
      <p class="kit-muted kit-rental__desc"><?= e($rental['description']) ?></p>
    <?php endif; ?>

    <?php if (!empty($rental['options'])): ?>
      <ul class="kit-rental__opts">
        <?php foreach ($rental['options'] as $o): ?>
          <?php foreach (($o['variants'] ?: [['label' => $o['name'], 'price' => $o['price']]]) as $v): ?>
            <li>
              <span><?= e($v['label']) ?></span>
              <strong><?= e(kit_price($v['price'], $rental['currency'])) ?></strong>
            </li>
          <?php endforeach; ?>
        <?php endforeach; ?>
      </ul>
    <?php endif; ?>

    <?php if ($rental['price_from'] !== null): ?>
      <p class="kit-rental__from">
        <?= e(t('common.from')) ?> <strong><?= e(kit_price($rental['price_from'], $rental['currency'])) ?></strong>
      </p>
    <?php endif; ?>

    <a href="<?= e($href) ?>" class="kit-btn kit-btn--outline kit-rental__cta"><?= e(kit_term('buy')) ?></a>
  </div>
</article>
