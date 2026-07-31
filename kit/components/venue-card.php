<?php
/**
 * COMPONENT: venue-card
 * Input: $venue (canonical venue)
 */
$venue = $venue ?? [];
if (!$venue) return;
$img = $venue['image_url'] ?: 'https://placehold.co/600x400?text=' . rawurlencode($venue['name']);
?>
<a href="<?= e($venue['url']) ?>" class="kit-card kit-place-card">
  <div class="kit-place-card__media"><img src="<?= e($img) ?>" alt="<?= e($venue['name']) ?>" loading="lazy"></div>
  <div class="kit-place-card__body">
    <h3 class="kit-display" style="font-size:1.05rem"><?= e($venue['name']) ?></h3>
    <p class="kit-event-card__meta"><?= e(trim(($venue['city'] ?? '') . ($venue['country'] ? ', ' . $venue['country'] : ''), ', ')) ?></p>
    <?php if ($venue['events_count'] !== null): ?>
      <span class="kit-chip"><?= (int)$venue['events_count'] ?> evenimente</span>
    <?php endif; ?>
  </div>
</a>
