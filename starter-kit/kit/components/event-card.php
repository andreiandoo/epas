<?php
/**
 * COMPONENT: event-card
 *
 * Input:
 *   $event    canonical event (viewmodel.php) — REQUIRED
 *   $variant  'grid' (default) | 'horizontal' | 'poster' | 'compact'
 *
 * Renders one event as a link card. Pure: no data fetching. Styling comes
 * entirely from .kit-event-card* tokens in tokens.css, so a theme restyles it
 * without touching this file.
 */

/** @var array $event */
/** @var string $variant */
$variant = $variant ?? 'grid';
if (empty($event) || !is_array($event)) return;

$badge = kit_date_badge($event['date'] ?? '', Kit::get('locale', 'ro'));
$price = $event['price_from'] !== null
    ? 'de la ' . kit_price((float)$event['price_from'], $event['currency'])
    : '';
$img = $event['poster_url'] ?: $event['hero_image_url'] ?: 'https://placehold.co/600x900?text=' . rawurlencode($event['title']);

$statusBadges = '';
if ($event['is_cancelled'])      $statusBadges .= '<span class="kit-badge kit-badge--cancelled">Anulat</span>';
elseif ($event['is_postponed'])  $statusBadges .= '<span class="kit-badge kit-badge--postponed">Reprogramat</span>';
elseif ($event['is_sold_out'])   $statusBadges .= '<span class="kit-badge kit-badge--soldout">Sold Out</span>';
if ($event['is_promoted'])       $statusBadges .= '<span class="kit-badge kit-badge--promoted">Recomandat</span>';

$cls = 'kit-event-card kit-event-card--' . e($variant);
?>
<a href="<?= e($event['url']) ?>" class="<?= $cls ?>">
  <div class="kit-event-card__media">
    <img src="<?= e($img) ?>" alt="<?= e($event['title']) ?>" loading="lazy">
    <?php if ($statusBadges): ?><div class="kit-event-card__badges"><?= $statusBadges ?></div><?php endif; ?>
    <?php if ($badge['day'] && $variant !== 'horizontal'): ?>
      <div class="kit-event-card__date"><b><?= e((string)$badge['day']) ?></b><span><?= e($badge['month']) ?></span></div>
    <?php endif; ?>
  </div>
  <div class="kit-event-card__body">
    <?php if ($event['category']): ?><span class="kit-event-card__cat"><?= e($event['category']) ?></span><?php endif; ?>
    <h3 class="kit-event-card__title"><?= e($event['title']) ?></h3>
    <p class="kit-event-card__meta">
      <?php
        $meta = array_filter([
            $variant === 'horizontal' && $badge['day'] ? ($badge['day'] . ' ' . $badge['month']) : '',
            $event['time'] ?: '',
            $event['venue_name'] ?: '',
            $event['city'] ?: '',
        ]);
        echo e(implode(' • ', $meta));
      ?>
    </p>
    <?php if ($price): ?><p class="kit-event-card__price"><?= e($price) ?></p><?php endif; ?>
  </div>
</a>
