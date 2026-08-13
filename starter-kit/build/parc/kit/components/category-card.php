<?php
/**
 * COMPONENT: category-card  (also used for genres / cities — any taxonomy chip)
 * Input: $item (canonical taxonomy: slug,name,icon,image_url,count,url)
 */
$item = $item ?? [];
if (!$item) return;
$hasImg = !empty($item['image_url']);
?>
<a href="<?= e($item['url']) ?>" class="kit-card kit-tax-card<?= $hasImg ? ' kit-tax-card--img' : '' ?>"<?= $hasImg ? ' style="background-image:url(\'' . e($item['image_url']) . '\')"' : '' ?>>
  <div class="kit-tax-card__inner">
    <?php if (!empty($item['icon'])): ?><span class="kit-tax-card__icon"><?= $item['icon'] ?></span><?php endif; ?>
    <span class="kit-tax-card__name kit-display"><?= e($item['name']) ?></span>
    <?php if ($item['count'] !== null): ?><span class="kit-muted" style="font-size:.8rem"><?= (int)$item['count'] ?></span><?php endif; ?>
  </div>
</a>
