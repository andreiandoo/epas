<?php
/** PAGESET: music (artist) — releases grid + streaming links (placeholder data). */
$PAGE = $PAGE ?? [];
$nav  = $PAGE['nav'] ?? 'music';
$releases = [
    ['title' => 'Album Nou', 'year' => '2026', 'img' => 'https://images.unsplash.com/photo-1493225457124-a3eb161ffa5f?w=500&q=80'],
    ['title' => 'Single de vară', 'year' => '2025', 'img' => 'https://images.unsplash.com/photo-1458560871784-56d23406c091?w=500&q=80'],
    ['title' => 'EP Live', 'year' => '2024', 'img' => 'https://images.unsplash.com/photo-1511671782779-c97d3d27a1d4?w=500&q=80'],
];
layout('public', ['title' => 'Muzică — ' . kit_cfg('site_name'), 'nav' => $nav],
function () use ($releases) { ?>
  <section class="kit-section"><div class="kit-container">
    <h1 class="kit-display" style="font-size:clamp(1.8rem,4vw,2.6rem);margin-bottom:1.5rem">Muzică</h1>
    <div class="kit-grid" style="grid-template-columns:repeat(auto-fill,minmax(200px,1fr))">
      <?php foreach ($releases as $r): ?>
        <div class="kit-card">
          <div style="aspect-ratio:1/1;overflow:hidden;background:var(--kit-surface-2)"><img src="<?= e($r['img']) ?>" alt="<?= e($r['title']) ?>" style="width:100%;height:100%;object-fit:cover"></div>
          <div style="padding:.9rem 1rem"><h3 class="kit-display" style="font-size:1.05rem"><?= e($r['title']) ?></h3><p class="kit-muted" style="font-size:.85rem"><?= e($r['year']) ?></p></div>
        </div>
      <?php endforeach; ?>
    </div>
    <div style="display:flex;gap:.75rem;flex-wrap:wrap;margin-top:2rem">
      <a class="kit-btn kit-btn--outline" href="#">Spotify</a>
      <a class="kit-btn kit-btn--outline" href="#">Apple Music</a>
      <a class="kit-btn kit-btn--outline" href="#">YouTube</a>
    </div>
  </div></section>
<?php });
