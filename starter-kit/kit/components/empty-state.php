<?php
/**
 * COMPONENT: empty-state
 * Input: $message (string), $icon (optional emoji/HTML), $action (optional ['label','url'])
 */
$message = $message ?? 'Nimic de afișat.';
$icon    = $icon ?? '🔍';
?>
<div class="kit-empty">
  <div style="font-size:2.5rem;margin-bottom:.5rem"><?= $icon ?></div>
  <p><?= e($message) ?></p>
  <?php if (!empty($action['url'])): ?>
    <a href="<?= e($action['url']) ?>" class="kit-btn kit-btn--outline" style="margin-top:1rem"><?= e($action['label'] ?? 'Înapoi') ?></a>
  <?php endif; ?>
</div>
