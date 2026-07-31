<?php
/**
 * COMPONENT: breadcrumb
 * Input: $items = [['label'=>..,'url'=>..], ...]  (last item = current, no url)
 */
$items = $items ?? [];
if (!$items) return;
?>
<nav class="kit-breadcrumb" aria-label="breadcrumb">
  <?php foreach ($items as $i => $it): ?>
    <?php if ($i) echo '<span>›</span>'; ?>
    <?php if (!empty($it['url']) && $i < count($items) - 1): ?>
      <a href="<?= e($it['url']) ?>"><?= e($it['label']) ?></a>
    <?php else: ?>
      <span aria-current="page"><?= e($it['label']) ?></span>
    <?php endif; ?>
  <?php endforeach; ?>
</nav>
