<?php
/**
 * COMPONENT: subscription-card  (pricing / season plan)
 * Input: $plan = [name, price, currency, subtitle, is_featured, benefits[], cta_label, cta_url, badge]
 */
$plan = $plan ?? [];
if (!$plan) return;
$featured = !empty($plan['is_featured']);
?>
<div class="kit-plan<?= $featured ? ' kit-plan--featured' : '' ?>">
  <?php if ($featured || !empty($plan['badge'])): ?>
    <span class="kit-badge kit-badge--promoted" style="position:absolute;top:1rem;right:1rem"><?= e($plan['badge'] ?? 'Recomandat') ?></span>
  <?php endif; ?>
  <h3 class="kit-display" style="font-size:1.4rem"><?= e($plan['name'] ?? '') ?></h3>
  <?php if (!empty($plan['subtitle'])): ?><p class="kit-muted" style="font-size:.9rem"><?= e($plan['subtitle']) ?></p><?php endif; ?>
  <div class="kit-plan__price"><?= e(kit_price(isset($plan['price']) ? (float)$plan['price'] : null, $plan['currency'] ?? null)) ?></div>
  <?php if (!empty($plan['benefits'])): ?>
    <ul class="kit-plan__benefits">
      <?php foreach ($plan['benefits'] as $b): ?><li><span style="color:var(--kit-success)">✓</span><?= e($b) ?></li><?php endforeach; ?>
    </ul>
  <?php endif; ?>
  <a href="<?= e($plan['cta_url'] ?? '#') ?>" class="kit-btn <?= $featured ? 'kit-btn--primary' : 'kit-btn--outline' ?>" style="width:100%"><?= e($plan['cta_label'] ?? 'Abonează-te') ?></a>
</div>
