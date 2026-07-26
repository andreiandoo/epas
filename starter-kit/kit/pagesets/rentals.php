<?php
/**
 * PAGESET: rentals  (leisure) — bookable rental items + "how it works".
 * Uses events as rental inventory in the demo; swap kit_events() for a rentals
 * endpoint when the API exposes one (feature 'rentals').
 */
$PAGE = $PAGE ?? [];
$nav  = $PAGE['nav'] ?? 'rentals';
$items = kit_events(['per_page' => 12]);
layout('public', ['title' => 'Închirieri — ' . kit_cfg('site_name'), 'nav' => $nav],
function () use ($items) { ?>
  <section class="kit-section"><div class="kit-container">
    <h1 class="kit-display" style="font-size:clamp(1.8rem,4vw,2.6rem);margin-bottom:.5rem">Închirieri</h1>
    <p class="kit-muted" style="margin-bottom:2rem">Rezervă pe intervale orare. Alege, plătește, te prezinți la recepție.</p>
    <div class="kit-grid" style="grid-template-columns:repeat(auto-fill,minmax(220px,1fr))">
      <?php foreach ($items as $e) component('event-card', ['event' => $e]); ?>
    </div>
    <div class="kit-grid" style="grid-template-columns:repeat(auto-fill,minmax(220px,1fr));margin-top:3rem">
      <?php foreach ([['1','Alege intervalul'],['2','Rezervă online'],['3','Te prezinți la fața locului']] as $s): ?>
        <?php component('stat-tile', ['num' => $s[0], 'label' => $s[1]]); ?>
      <?php endforeach; ?>
    </div>
  </div></section>
<?php });
