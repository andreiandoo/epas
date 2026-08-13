<?php
/**
 * COMPONENT: schedule-row  (one row in a program/schedule list)
 * Input: $event (canonical, req)
 * A horizontal listing row with a prominent date block. Use inside a list on
 * schedule/program pages.
 */
$event = $event ?? [];
if (!$event) return;
$b = kit_date_badge($event['date'] ?? '', Kit::get('locale', 'ro'));
$days = ['Dum','Lun','Mar','Mie','Joi','Vin','Sâm'];
$wd = $event['date'] ? $days[(int)date('w', strtotime($event['date']))] : '';
$img = $event['poster_url'] ?: $event['hero_image_url'] ?: 'https://placehold.co/120x120';
?>
<div class="kit-schedule-row">
  <div class="kit-schedule-row__date">
    <b><?= e((string)$b['day']) ?></b>
    <span><?= e(trim($wd . ' ' . $b['month'])) ?></span>
  </div>
  <img src="<?= e($img) ?>" alt="" class="kit-schedule-row__img" loading="lazy">
  <div class="kit-schedule-row__info">
    <?php if ($event['category']): ?><span class="kit-event-card__cat"><?= e($event['category']) ?></span><?php endif; ?>
    <a href="<?= e($event['url']) ?>" class="kit-schedule-row__title kit-display"><?= e($event['title']) ?></a>
    <p class="kit-event-card__meta"><?= e(implode(' • ', array_filter([$event['time'], $event['venue_name']]))) ?></p>
  </div>
  <div class="kit-schedule-row__cta">
    <?php if ($event['price_from'] !== null): ?><span class="kit-muted" style="font-size:.85rem"><?= e(t('common.from')) ?> <?= e(kit_price((float)$event['price_from'], $event['currency'])) ?></span><?php endif; ?>
    <a href="<?= e($event['url']) ?>" class="kit-btn <?= $event['is_sold_out'] ? 'kit-btn--outline' : 'kit-btn--primary' ?>"><?= e($event['is_sold_out'] ? t('common.sold_out') : t('common.tickets')) ?></a>
  </div>
</div>
