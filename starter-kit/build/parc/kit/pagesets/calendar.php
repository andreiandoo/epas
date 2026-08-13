<?php
/** PAGESET: calendar — month grid + day-filtered list. */
$PAGE = $PAGE ?? [];
$nav  = $PAGE['nav'] ?? 'schedule';
$title = kit_nav_label($nav) ?: 'Program';
$events = kit_events(['per_page' => 100]);
layout('public', ['title' => $title . ' — ' . kit_cfg('site_name'), 'nav' => $nav],
function () use ($events, $title) { ?>
  <section class="kit-section"><div class="kit-container">
    <p class="kit-event-card__cat"><?= e(kit_term('events_cap', 'Calendar')) ?></p>
    <h1 class="kit-display" style="font-size:clamp(2rem,5vw,3rem);margin:.25rem 0 1.5rem"><?= e($title) ?></h1>
    <?php component('calendar', ['events' => $events]); ?>
  </div></section>
<?php });
