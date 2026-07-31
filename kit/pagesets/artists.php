<?php
/** PAGESET: artists — grid of artist cards (troupe/orchestra/etc). */
$PAGE = $PAGE ?? [];
$nav  = $PAGE['nav'] ?? 'artists';
$title = kit_nav_label($nav) ?: kit_term('artists_cap', 'Artiști');
$artists = kit_artists();
layout('public', ['title' => $title . ' — ' . kit_cfg('site_name'), 'nav' => $nav],
function () use ($artists, $title) { ?>
  <section class="kit-section"><div class="kit-container">
    <h1 class="kit-display" style="font-size:clamp(1.8rem,4vw,2.6rem);margin-bottom:1.5rem"><?= e($title) ?></h1>
    <?php if ($artists): ?>
      <div class="kit-grid" style="grid-template-columns:repeat(auto-fill,minmax(180px,1fr))">
        <?php foreach ($artists as $a) component('artist-card', ['artist' => $a]); ?>
      </div>
    <?php else: component('empty-state', ['message' => 'Momentan nu avem membri de afișat.']); endif; ?>
  </div></section>
<?php });
