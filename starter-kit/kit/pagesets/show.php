<?php
/**
 * PAGESET: show  — single event. Feature-gated: reserved seating gets a
 * seat-map, general admission gets a ticket-selector.
 */
$slug  = $_GET['slug'] ?? '';
$event = $slug ? kit_event($slug) : null;

if (!$event) {
    http_response_code(404);
    layout('public', ['title' => kit_term('event_cap', 'Eveniment') . ' negăsit'], function () {
        component('empty-state', ['message' => kit_term('event_cap', 'Evenimentul') . ' nu a fost găsit.',
            'action' => ['label' => kit_term('events_cap', 'Vezi tot'), 'url' => kit_cfg('cta_url', '/')]]);
    });
    return;
}

$related = array_values(array_filter(kit_events(['per_page' => 4]), fn($e) => $e['id'] !== $event['id']));
$rail = kit_feature('seating')
    ? component_html('seat-map', ['event' => $event])
    : component_html('ticket-selector', ['event' => $event]);

layout('public', [
    'title'       => $event['title'] . ' — ' . kit_cfg('site_name'),
    'nav'         => '',
    'og_type'     => 'event',
    'event'       => $event,                                   // → Event JSON-LD
    'description' => $event['short_description'] ?: ($event['title'] . ' la ' . ($event['venue_name'] ?: kit_cfg('site_name'))),
    'image'       => $event['hero_image_url'] ?: $event['poster_url'],
],
function () use ($event, $related, $rail) {
    component('event-hero', ['event' => $event, 'slot' => $rail]);
    ?>
    <section class="kit-section"><div class="kit-container">
      <?php component('breadcrumb', ['items' => [
          ['label' => t('common.home'), 'url' => '/'],
          ['label' => kit_term('events_cap', 'Evenimente'), 'url' => kit_cfg('cta_url', '/')],
          ['label' => $event['title']],
      ]]); ?>
      <?php if ($event['description']): ?><div style="max-width:48rem;margin:1.5rem 0"><?= $event['description'] ?></div><?php endif; ?>
      <?php if ($event['artists']): ?>
        <h2 class="kit-display" style="font-size:1.4rem;margin:2rem 0 1rem"><?= e(kit_term('artists_cap', 'Distribuție')) ?></h2>
        <div class="kit-grid" style="grid-template-columns:repeat(auto-fill,minmax(150px,1fr))">
          <?php foreach ($event['artists'] as $a) component('artist-card', ['artist' => ['name' => $a['name'], 'image' => $a['image'], 'url' => vm_url(kit_cfg('artist_url_pattern'), ['slug' => $a['slug']])]]); ?>
        </div>
      <?php endif; ?>
      <?php if ($related): ?>
        <h2 class="kit-display" style="font-size:1.4rem;margin:2.5rem 0 1rem"><?= e(t('common.see_also')) ?></h2>
        <?php component('event-grid', ['events' => $related]); ?>
      <?php endif; ?>
    </div></section>
<?php });
