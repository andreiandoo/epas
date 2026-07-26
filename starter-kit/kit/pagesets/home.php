<?php
/**
 * PAGESET: home  — hero (featured) + featured grid. Kind-aware via kit_term.
 * Expects: kit booted; optional $PAGE.
 */
$events = kit_events(['per_page' => 8]);
$hero   = $events[0] ?? null;
layout('public', ['title' => kit_cfg('site_name') . (kit_cfg('site_city') ? ' — ' . kit_cfg('site_city') : ''), 'nav' => ''],
function () use ($events, $hero) {
    if ($hero) component('hero', [
        'eyebrow'  => kit_term('events_cap', 'Evenimente'),
        'title'    => $hero['title'],
        'subtitle' => $hero['short_description'] ?: ('Bine ai venit la ' . kit_cfg('site_name') . '.'),
        'image'    => $hero['hero_image_url'],
        'actions'  => [
            ['label' => kit_cfg('cta_label', 'Bilete'), 'url' => $hero['url'], 'style' => 'primary'],
            ['label' => kit_term('events_cap', 'Vezi tot'), 'url' => kit_cfg('cta_url', '/'), 'style' => 'outline'],
        ],
    ]);
    ?>
    <section class="kit-section"><div class="kit-container">
      <h2 class="kit-display" style="font-size:1.6rem;margin-bottom:1.25rem"><?= e(kit_term('events_cap', 'Evenimente')) ?></h2>
      <?php component('event-grid', ['events' => $events]); ?>
    </div></section>
<?php });
