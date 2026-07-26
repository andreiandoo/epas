<?php
/** PAGESET: gallery — image grid (uses event posters in the demo). */
$PAGE = $PAGE ?? [];
$nav  = $PAGE['nav'] ?? 'gallery';
$imgs = array_values(array_filter(array_map(fn($e) => $e['hero_image_url'] ?: $e['poster_url'], kit_events(['per_page' => 12]))));
layout('public', ['title' => 'Galerie — ' . kit_cfg('site_name'), 'nav' => $nav],
function () use ($imgs) { ?>
  <section class="kit-section"><div class="kit-container">
    <h1 class="kit-display" style="font-size:clamp(1.8rem,4vw,2.6rem);margin-bottom:1.5rem">Galerie</h1>
    <div class="kit-grid" style="grid-template-columns:repeat(auto-fill,minmax(220px,1fr))">
      <?php foreach ($imgs as $src): ?>
        <div class="kit-card" style="aspect-ratio:4/3;overflow:hidden"><img src="<?= e($src) ?>" alt="" loading="lazy" style="width:100%;height:100%;object-fit:cover"></div>
      <?php endforeach; ?>
    </div>
  </div></section>
<?php });
