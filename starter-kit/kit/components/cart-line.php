<?php
/**
 * COMPONENT: cart-line  (one line in the cart/checkout summary)
 * Input: $line = [title, image, ticket, seats[], qty, price, currency, url]
 * Works for both GA (qty+ticket) and reserved-seating (seats[]) lines.
 */
$line = $line ?? [];
if (!$line) return;
$qty   = $line['qty'] ?? (isset($line['seats']) ? count($line['seats']) : 1);
$sub   = (float)($line['price'] ?? 0) * (isset($line['seats']) ? 1 : $qty);
$seats = $line['seats'] ?? [];
$img   = $line['image'] ?: 'https://placehold.co/120x120';
?>
<div class="kit-cart-line">
  <img src="<?= e($img) ?>" alt="" class="kit-cart-line__img" loading="lazy">
  <div class="kit-cart-line__info">
    <a href="<?= e($line['url'] ?? '#') ?>" class="kit-cart-line__title kit-display"><?= e($line['title'] ?? '') ?></a>
    <?php if (!empty($line['ticket'])): ?><div class="kit-muted" style="font-size:.85rem"><?= e($line['ticket']) ?> × <?= (int)$qty ?></div><?php endif; ?>
    <?php if ($seats): ?>
      <div class="kit-cart-line__seats">
        <?php foreach ($seats as $s): ?><span class="kit-chip"><?= e(is_array($s) ? ($s['label'] ?? '') : $s) ?></span><?php endforeach; ?>
      </div>
    <?php endif; ?>
  </div>
  <div class="kit-cart-line__price"><?= e(kit_price($sub, $line['currency'] ?? null)) ?></div>
</div>
