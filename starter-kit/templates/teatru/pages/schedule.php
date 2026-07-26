<?php
/**
 * PAGE: /program  (schedule) — demonstrates the calendar component.
 */
require __DIR__ . '/../includes/bootstrap.php';

$events = kit_events(['per_page' => 100]);

layout('public', ['title' => 'Program — ' . kit_cfg('site_name'), 'nav' => 'schedule'], function () use ($events) { ?>
  <section class="kit-section">
    <div class="kit-container">
      <p class="kit-event-card__cat">Calendar spectacole</p>
      <h1 class="kit-display" style="font-size:clamp(2rem,5vw,3rem);margin:.25rem 0 1.5rem">Program</h1>
      <?php component('calendar', ['events' => $events]); ?>
    </div>
  </section>
<?php });
