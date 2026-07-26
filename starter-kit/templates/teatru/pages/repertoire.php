<?php
/**
 * PAGE: /repertoriu  (event listing)
 *
 * This is the whole page. Compare with the original teatru repertoire.php:
 * no api_get, no field normalization, no card markup — data in, component out.
 */
require __DIR__ . '/../includes/bootstrap.php';

$events = kit_events(['per_page' => 100]);

layout('public', [
    'title' => 'Repertoriu — ' . kit_cfg('site_name'),
    'nav'   => 'repertoire',
], function () use ($events) { ?>
    <section class="kit-section">
      <div class="kit-container">
        <p class="kit-event-card__cat">Stagiunea 2026</p>
        <h1 class="kit-display" style="font-size:clamp(2rem,5vw,3rem);margin:.25rem 0 1.5rem">Repertoriu</h1>
        <?php component('event-grid', ['events' => $events]); ?>
      </div>
    </section>
<?php });
