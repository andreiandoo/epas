<?php
/**
 * COMPONENT: event-grid
 *
 * Input:
 *   $events   array of canonical events (from kit_events()) — REQUIRED
 *   $variant  card variant to use ('grid' default)
 *   $empty    empty-state message (optional)
 *   $columns  optional min column width override in px (default from CSS)
 *
 * Renders a responsive grid of event-card components, or an empty-state.
 */

/** @var array $events */
$events  = $events  ?? [];
$variant = $variant ?? 'grid';
$empty   = $empty   ?? 'Nu există evenimente disponibile momentan.';
$style   = isset($columns) ? ' style="grid-template-columns:repeat(auto-fill,minmax(' . (int)$columns . 'px,1fr))"' : '';

if (!$events) {
    component('empty-state', ['message' => $empty]);
    return;
}
?>
<div class="kit-grid"<?= $style ?>>
  <?php foreach ($events as $event): ?>
    <?php if (is_array($event)) component('event-card', ['event' => $event, 'variant' => $variant]); ?>
  <?php endforeach; ?>
</div>
