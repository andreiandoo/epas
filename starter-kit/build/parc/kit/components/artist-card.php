<?php
/**
 * COMPONENT: artist-card
 * Input: $artist (canonical artist), $variant 'grid'|'compact'
 */
$artist = $artist ?? [];
if (!$artist) return;
$name = $artist['name'] ?? '';
$img = ($artist['image'] ?? '') ?: 'https://placehold.co/400x400?text=' . rawurlencode($name);
?>
<a href="<?= e($artist['url'] ?? '#') ?>" class="kit-card kit-artist-card">
  <div class="kit-artist-card__media"><img src="<?= e($img) ?>" alt="<?= e($name) ?>" loading="lazy"></div>
  <div class="kit-artist-card__body">
    <?php if (!empty($artist['role'])): ?><span class="kit-event-card__cat"><?= e($artist['role']) ?></span><?php endif; ?>
    <h3 class="kit-display" style="font-size:1.05rem"><?= e($name) ?></h3>
  </div>
</a>
