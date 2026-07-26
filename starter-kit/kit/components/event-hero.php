<?php
/**
 * COMPONENT: event-hero  (single-event detail header)
 * Input:
 *   $event  canonical event — REQUIRED
 *   $slot   optional pre-rendered HTML for the right rail (e.g. ticket-selector)
 */
$event = $event ?? [];
if (!$event) return;
$img = $event['hero_image_url'] ?: $event['poster_url'] ?: 'https://placehold.co/1600x900';
$poster = $event['poster_url'] ?: $img;
$dateLabel = '';
if ($event['date']) {
    $ts = strtotime($event['date']);
    $days = ['Duminică','Luni','Marți','Miercuri','Joi','Vineri','Sâmbătă'];
    $months = ['','ianuarie','februarie','martie','aprilie','mai','iunie','iulie','august','septembrie','octombrie','noiembrie','decembrie'];
    $dateLabel = $days[(int)date('w',$ts)] . ', ' . (int)date('j',$ts) . ' ' . $months[(int)date('n',$ts)] . ' ' . date('Y',$ts);
}
?>
<section class="kit-event-hero">
  <div class="kit-event-hero__bg" style="background-image:url('<?= e($img) ?>')"></div>
  <div class="kit-container kit-event-hero__grid">
    <div class="kit-event-hero__poster"><img src="<?= e($poster) ?>" alt="<?= e($event['title']) ?>"></div>
    <div class="kit-event-hero__info">
      <?php if ($event['category']): ?><p class="kit-hero__eyebrow"><?= e($event['category']) ?></p><?php endif; ?>
      <h1 class="kit-display" style="font-size:clamp(1.8rem,4vw,3rem);margin:.25rem 0 1rem"><?= e($event['title']) ?></h1>
      <ul class="kit-event-hero__meta">
        <?php if ($dateLabel): ?><li>📅 <?= e($dateLabel) ?><?= $event['time'] ? ' · ' . e($event['time']) : '' ?></li><?php endif; ?>
        <?php if ($event['venue_name']): ?><li>📍 <?= e($event['venue_name']) ?><?= $event['city'] ? ', ' . e($event['city']) : '' ?></li><?php endif; ?>
        <?php if ($event['price_from'] !== null): ?><li>🎫 de la <?= e(kit_price((float)$event['price_from'], $event['currency'])) ?></li><?php endif; ?>
      </ul>
      <?php if (!empty($event['short_description'])): ?><p style="opacity:.9;max-width:38rem"><?= e($event['short_description']) ?></p><?php endif; ?>
      <?php if (!empty($slot)): ?><div class="kit-event-hero__rail"><?= $slot ?></div><?php endif; ?>
    </div>
  </div>
</section>
