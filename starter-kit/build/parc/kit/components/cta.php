<?php
/**
 * COMPONENT: cta  (call-to-action band)
 * Input: $title, $text, $action=['label','url']
 */
$title  = $title  ?? '';
$text   = $text   ?? '';
$action = $action ?? [];
?>
<section class="kit-cta">
  <div class="kit-container" style="text-align:center">
    <h2 class="kit-display" style="font-size:1.8rem;margin-bottom:.5rem"><?= e($title) ?></h2>
    <?php if ($text): ?><p class="kit-muted" style="max-width:36rem;margin:0 auto 1.25rem"><?= e($text) ?></p><?php endif; ?>
    <?php if (!empty($action['url'])): ?><a href="<?= e($action['url']) ?>" class="kit-btn kit-btn--primary"><?= e($action['label'] ?? 'Află mai mult') ?></a><?php endif; ?>
  </div>
</section>
