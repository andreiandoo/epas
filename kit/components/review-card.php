<?php
/**
 * COMPONENT: review-card
 * Input: $review = [rating (0-5), title, body, author, date, event, status]
 */
$r = $review ?? [];
if (!$r) return;
$rating = (int)($r['rating'] ?? 0);
?>
<div class="kit-review">
  <div class="kit-review__stars" aria-label="<?= $rating ?>/5">
    <?php for ($i = 1; $i <= 5; $i++) echo '<span style="color:' . ($i <= $rating ? 'var(--kit-accent)' : 'var(--kit-border)') . '">★</span>'; ?>
  </div>
  <?php if (!empty($r['title'])): ?><h4 class="kit-display" style="font-size:1rem;margin:.4rem 0 .2rem"><?= e($r['title']) ?></h4><?php endif; ?>
  <?php if (!empty($r['body'])): ?><p style="font-size:.9rem"><?= e($r['body']) ?></p><?php endif; ?>
  <p class="kit-muted" style="font-size:.8rem;margin-top:.5rem">
    <?= e(implode(' • ', array_filter([$r['author'] ?? '', $r['event'] ?? '', $r['date'] ?? '']))) ?>
    <?php if (!empty($r['status']) && $r['status'] !== 'published'): ?><span class="kit-chip"><?= e($r['status']) ?></span><?php endif; ?>
  </p>
</div>
