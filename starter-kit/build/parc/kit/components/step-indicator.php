<?php
/**
 * COMPONENT: step-indicator  (numbered progress for wizards/checkout)
 * Input: $steps (string[] labels, req), $current (1-based int)
 */
$steps   = $steps ?? [];
$current = (int)($current ?? 1);
if (!$steps) return;
?>
<ol class="kit-steps">
  <?php foreach ($steps as $i => $label): $n = $i + 1; $state = $n < $current ? 'done' : ($n === $current ? 'active' : ''); ?>
    <li class="kit-steps__item<?= $state ? ' is-' . $state : '' ?>">
      <span class="kit-steps__dot"><?= $n < $current ? '✓' : $n ?></span>
      <span class="kit-steps__label"><?= e($label) ?></span>
    </li>
  <?php endforeach; ?>
</ol>
