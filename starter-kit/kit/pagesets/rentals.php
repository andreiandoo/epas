<?php
/**
 * PAGESET: rentals  (leisure) — the venue's rental pools, live.
 *
 * Backed by kit_rentals() → /tenant-client/leisure/rentals: physical resource
 * types with live availability and their duration options. Falls back to the
 * bookables of category `rental` when the venue tracks no physical units, and
 * finally to an empty-state rather than pretending there is stock.
 */
$PAGE = $PAGE ?? [];
$nav  = $PAGE['nav'] ?? 'rentals';

$rentals = kit_rentals();
$fallbackBookables = $rentals ? [] : kit_bookables('rental');

layout('public', ['title' => t('booking.rentals_title') . ' — ' . kit_cfg('site_name'), 'nav' => $nav],
function () use ($rentals, $fallbackBookables) { ?>
  <section class="kit-section"><div class="kit-container">
    <h1 class="kit-display" style="font-size:clamp(1.8rem,4vw,2.6rem);margin-bottom:.5rem">
      <?= e(t('booking.rentals_title')) ?>
    </h1>
    <p class="kit-muted" style="margin-bottom:2rem"><?= e(t('booking.rentals_intro')) ?></p>

    <?php if ($rentals): ?>
      <div class="kit-grid" style="grid-template-columns:repeat(auto-fill,minmax(240px,1fr))">
        <?php foreach ($rentals as $r) component('rental-card', ['rental' => $r]); ?>
      </div>
    <?php elseif ($fallbackBookables): ?>
      <?php component('booking-widget', ['bookables' => $fallbackBookables, 'title' => t('booking.rentals_title')]); ?>
    <?php else: ?>
      <?php component('empty-state', ['message' => t('cart.empty')]); ?>
    <?php endif; ?>

    <div class="kit-grid" style="grid-template-columns:repeat(auto-fill,minmax(220px,1fr));margin-top:3rem">
      <?php foreach ([
        ['1', t('booking.choose_slot')],
        ['2', t('booking.title')],
        ['3', t('booking.people')],
      ] as $s): ?>
        <?php component('stat-tile', ['num' => $s[0], 'label' => $s[1]]); ?>
      <?php endforeach; ?>
    </div>
  </div></section>
<?php });
