<?php
/** PAGESET: tours — event listing framed as tour dates (schedule-row list). */
$PAGE = $PAGE ?? [];
$nav  = $PAGE['nav'] ?? 'tours';
$title = kit_nav_label($nav) ?: 'Turneu';
$events = kit_events(['per_page' => 100]);
layout('public', ['title' => $title . ' — ' . kit_cfg('site_name'), 'nav' => $nav],
function () use ($events, $title) { ?>
  <section class="kit-section"><div class="kit-container">
    <h1 class="kit-display" style="font-size:clamp(1.8rem,4vw,2.6rem);margin-bottom:1.5rem"><?= e($title) ?></h1>
    <?php if ($events): ?>
      <div style="display:flex;flex-direction:column;gap:.75rem">
        <?php foreach ($events as $e) component('schedule-row', ['event' => $e]); ?>
      </div>
    <?php else: component('empty-state', ['message' => 'Nu sunt date de turneu momentan.']); endif; ?>
  </div></section>
<?php });
