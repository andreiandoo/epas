<?php
/**
 * COMPONENT: stat-tile
 * Input: $num (string|int), $label (string), $hint (optional)
 */
$num   = $num   ?? '';
$label = $label ?? '';
$hint  = $hint  ?? '';
?>
<div class="kit-stat">
  <div class="kit-stat__num"><?= e((string)$num) ?></div>
  <div class="kit-stat__label"><?= e($label) ?></div>
  <?php if ($hint): ?><div class="kit-muted" style="font-size:.75rem;margin-top:.25rem"><?= e($hint) ?></div><?php endif; ?>
</div>
