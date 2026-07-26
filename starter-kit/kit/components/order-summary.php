<?php
/**
 * COMPONENT: order-summary  (cart/checkout/confirmation totals)
 * Input:
 *   $lines    array of cart-line inputs (req)
 *   $totals   [subtotal, fees, discount, total, currency]  (total req)
 *   $cta      optional ['label','url'] (e.g. "Finalizează comanda")
 *   $title    optional heading
 */
$lines  = $lines  ?? [];
$totals = $totals ?? [];
$cur    = $totals['currency'] ?? Kit::get('currency', 'RON');
?>
<div class="kit-ordersum">
  <?php if (!empty($title)): ?><h3 class="kit-display" style="font-size:1.2rem;margin-bottom:.5rem"><?= e($title) ?></h3><?php endif; ?>
  <?php foreach ($lines as $line) component('cart-line', ['line' => $line]); ?>
  <div class="kit-ordersum__totals">
    <?php foreach (['subtotal' => 'Subtotal', 'fees' => 'Taxe', 'discount' => 'Reducere'] as $k => $lab): ?>
      <?php if (isset($totals[$k])): ?>
        <div class="kit-ordersum__row"><span class="kit-muted"><?= $lab ?></span><span><?= ($k === 'discount' ? '−' : '') . e(kit_price((float)$totals[$k], $cur)) ?></span></div>
      <?php endif; ?>
    <?php endforeach; ?>
    <div class="kit-ordersum__row kit-ordersum__row--total"><span>Total</span><strong><?= e(kit_price((float)($totals['total'] ?? 0), $cur)) ?></strong></div>
  </div>
  <?php if (!empty($cta['url'])): ?>
    <a href="<?= e($cta['url']) ?>" class="kit-btn kit-btn--primary" style="width:100%;margin-top:.75rem"><?= e($cta['label'] ?? 'Continuă') ?></a>
  <?php endif; ?>
</div>
