<?php
/**
 * PAGE: /  (teatru homepage) — hero (featured) + spotlight + upcoming grid.
 */
require __DIR__ . '/../includes/bootstrap.php';

$events = kit_events(['per_page' => 8]);
$hero   = $events[0] ?? null;

layout('public', ['title' => kit_cfg('site_name') . ' — ' . kit_cfg('site_city'), 'nav' => ''], function () use ($events, $hero) {
    if ($hero) component('hero', [
        'eyebrow'  => 'Stagiunea 2026',
        'title'    => $hero['title'],
        'subtitle' => $hero['short_description'] ?: 'Bine ai venit la ' . kit_cfg('site_name') . '.',
        'image'    => $hero['hero_image_url'],
        'actions'  => [
            ['label' => 'Cumpără bilete', 'url' => $hero['url'], 'style' => 'primary'],
            ['label' => 'Vezi programul', 'url' => '/program', 'style' => 'outline'],
        ],
    ]);
    ?>
    <section class="kit-section">
      <div class="kit-container">
        <div class="kit-divider" style="display:flex;align-items:center;justify-content:space-between;margin-bottom:1.25rem">
          <h2 class="kit-display" style="font-size:1.6rem">Următoarele spectacole</h2>
          <a href="/repertoriu" class="kit-site-nav__link">Tot repertoriul →</a>
        </div>
        <?php component('event-grid', ['events' => $events]); ?>
      </div>
    </section>
<?php });
