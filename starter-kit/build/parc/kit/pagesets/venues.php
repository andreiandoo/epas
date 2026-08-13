<?php
/** PAGESET: venues — grid of venue cards. */
$PAGE = $PAGE ?? [];
$nav  = $PAGE['nav'] ?? 'venues';
$title = kit_nav_label($nav) ?: 'Locații';
$venues = kit_venues();
layout('public', ['title' => $title . ' — ' . kit_cfg('site_name'), 'nav' => $nav],
function () use ($venues, $title) { ?>
  <section class="kit-section"><div class="kit-container">
    <h1 class="kit-display" style="font-size:clamp(1.8rem,4vw,2.6rem);margin-bottom:1.5rem"><?= e($title) ?></h1>
    <?php if ($venues): ?>
      <div class="kit-grid" style="grid-template-columns:repeat(auto-fill,minmax(240px,1fr))">
        <?php foreach ($venues as $v) component('venue-card', ['venue' => $v]); ?>
      </div>
    <?php else: component('empty-state', ['message' => 'Momentan nu avem locații de afișat.']); endif; ?>
  </div></section>
<?php });
