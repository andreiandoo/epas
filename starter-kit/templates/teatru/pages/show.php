<?php
/**
 * PAGE: /spectacol/{slug}  (single event)
 *
 * Demonstrates composing the single-event view from components:
 * breadcrumb + event-hero (with a ticket-selector in its rail) + a related grid.
 * The slug comes from the .htaccess rewrite (?slug=…).
 */
require __DIR__ . '/../includes/bootstrap.php';

$slug  = $_GET['slug'] ?? 'hamlet';
$event = kit_event($slug);

if (!$event) {
    http_response_code(404);
    layout('public', ['title' => 'Spectacol negăsit'], function () {
        component('empty-state', ['message' => 'Spectacolul nu a fost găsit.', 'action' => ['label' => 'Vezi repertoriul', 'url' => '/repertoriu']]);
    });
    return;
}

// Related: other events, excluding this one.
$related = array_values(array_filter(kit_events(['per_page' => 4]), fn($e) => $e['id'] !== $event['id']));

layout('public', [
    'title' => $event['title'] . ' — ' . kit_cfg('site_name'),
    'nav'   => 'repertoire',
], function () use ($event, $related) {
    component('event-hero', [
        'event' => $event,
        // ticket-selector rendered into the hero's rail slot
        'slot'  => component_html('ticket-selector', ['event' => $event]),
    ]);
    ?>
    <section class="kit-section">
      <div class="kit-container">
        <?php component('breadcrumb', ['items' => [
            ['label' => 'Acasă', 'url' => '/'],
            ['label' => 'Repertoriu', 'url' => '/repertoriu'],
            ['label' => $event['title']],
        ]]); ?>
        <?php if ($event['description']): ?>
          <div style="max-width:48rem;margin:1.5rem 0"><?= $event['description'] /* trusted HTML from API */ ?></div>
        <?php endif; ?>

        <?php if ($event['artists']): ?>
          <h2 class="kit-display" style="font-size:1.4rem;margin:2rem 0 1rem">Distribuție</h2>
          <div class="kit-grid" style="grid-template-columns:repeat(auto-fill,minmax(150px,1fr))">
            <?php foreach ($event['artists'] as $a): ?>
              <?php component('artist-card', ['artist' => [
                  'name' => $a['name'], 'image' => $a['image'], 'role' => '',
                  'url' => '/artist/' . rawurlencode($a['slug']),
              ]]); ?>
            <?php endforeach; ?>
          </div>
        <?php endif; ?>

        <?php if ($related): ?>
          <h2 class="kit-display" style="font-size:1.4rem;margin:2.5rem 0 1rem">Vezi și</h2>
          <?php component('event-grid', ['events' => $related]); ?>
        <?php endif; ?>
      </div>
    </section>
<?php });
