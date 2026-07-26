<?php
/**
 * PAGE: /  (homepage) — kind-aware starter you extend.
 * Uses kit_term() so the SAME page reads "Spectacole" for a theatre or
 * "Activități" for a leisure venue, driven purely by the kind.
 */
require __DIR__ . '/../includes/bootstrap.php';

$events = kit_events(['per_page' => 8]);
$hero   = $events[0] ?? null;

layout('public', ['title' => kit_cfg('site_name'), 'nav' => ''], function () use ($events, $hero) {
    component('hero', [
        'eyebrow'  => 'Bine ai venit',
        'title'    => kit_cfg('site_name'),
        'subtitle' => 'Descoperă ' . kit_term('events', 'evenimente') . ' și cumpără bilete online.',
        'image'    => $hero['hero_image_url'] ?? '',
        'actions'  => [['label' => kit_cfg('cta_label'), 'url' => kit_cfg('cta_url'), 'style' => 'primary']],
    ]);
    ?>
    <section class="kit-section">
      <div class="kit-container">
        <h2 class="kit-display" style="font-size:1.6rem;margin-bottom:1.25rem"><?= e(kit_term('events_cap', 'Evenimente')) ?></h2>
        <?php component('event-grid', ['events' => $events]); ?>
      </div>
    </section>
<?php });
