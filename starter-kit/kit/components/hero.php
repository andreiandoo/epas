<?php
/**
 * COMPONENT: hero
 * Input: $title (req), $eyebrow, $subtitle, $image (bg url), $actions=[['label','url','style'=>'primary|outline']]
 */
$title    = $title    ?? '';
$eyebrow  = $eyebrow  ?? '';
$subtitle = $subtitle ?? '';
$image    = $image    ?? '';
$actions  = $actions  ?? [];
?>
<section class="kit-hero">
  <?php if ($image): ?><div class="kit-hero__bg" style="background-image:url('<?= e($image) ?>')"></div><?php endif; ?>
  <div class="kit-container">
    <?php if ($eyebrow): ?><p class="kit-hero__eyebrow"><?= e($eyebrow) ?></p><?php endif; ?>
    <h1 class="kit-hero__title"><?= e($title) ?></h1>
    <?php if ($subtitle): ?><p style="max-width:40rem;margin:0 auto 1.5rem;opacity:.9"><?= e($subtitle) ?></p><?php endif; ?>
    <?php if ($actions): ?>
      <div style="display:flex;gap:.75rem;justify-content:center;flex-wrap:wrap">
        <?php foreach ($actions as $a): ?>
          <a href="<?= e($a['url'] ?? '#') ?>" class="kit-btn kit-btn--<?= e($a['style'] ?? 'primary') ?>"><?= e($a['label'] ?? '') ?></a>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </div>
</section>
