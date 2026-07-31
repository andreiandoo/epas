<?php
/** PAGESET: blog — article listing. */
$PAGE = $PAGE ?? [];
$nav  = $PAGE['nav'] ?? 'blog';
$posts = kit_posts(['per_page' => 12]);
layout('public', ['title' => t('nav.blog') . ' — ' . kit_cfg('site_name'), 'nav' => $nav],
function () use ($posts) { ?>
  <section class="kit-section"><div class="kit-container">
    <h1 class="kit-display" style="font-size:clamp(1.8rem,4vw,2.6rem);margin-bottom:1.5rem"><?= e(t('nav.blog')) ?></h1>
    <?php if ($posts): ?>
      <div class="kit-grid" style="grid-template-columns:repeat(auto-fill,minmax(280px,1fr))">
        <?php foreach ($posts as $p): ?>
          <a href="<?= e($p['url']) ?>" class="kit-card kit-post-card">
            <div class="kit-post-card__media"><img src="<?= e($p['image'] ?: 'https://placehold.co/600x360') ?>" alt="<?= e($p['title']) ?>" loading="lazy"></div>
            <div class="kit-post-card__body">
              <?php if ($p['date']): ?><span class="kit-muted" style="font-size:.75rem"><?= e($p['date']) ?></span><?php endif; ?>
              <h3 class="kit-display" style="font-size:1.1rem"><?= e($p['title']) ?></h3>
              <?php if ($p['excerpt']): ?><p class="kit-event-card__meta"><?= e($p['excerpt']) ?></p><?php endif; ?>
            </div>
          </a>
        <?php endforeach; ?>
      </div>
    <?php else: component('empty-state', ['icon' => '📰', 'message' => 'Nu există articole momentan.']); endif; ?>
  </div></section>
<?php });
