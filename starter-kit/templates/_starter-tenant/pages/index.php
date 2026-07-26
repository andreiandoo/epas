<?php
/**
 * PAGE: /  (homepage)  — a working starter you extend.
 * hero + featured grid, both from components. ~20 lines.
 */
require __DIR__ . '/../includes/bootstrap.php';

$featured = kit_events(['per_page' => 8]);
$hero     = $featured[0] ?? null;

layout('public', ['title' => kit_cfg('site_name'), 'nav' => ''], function () use ($featured, $hero) {
    component('hero', [
        'eyebrow'  => 'Bine ai venit',
        'title'    => kit_cfg('site_name'),
        'subtitle' => 'Descoperă spectacolele stagiunii și cumpără bilete online.',
        'image'    => $hero['hero_image_url'] ?? '',
        'actions'  => [['label' => 'Vezi programul', 'url' => '/program', 'style' => 'primary']],
    ]);
    ?>
    <section class="kit-section">
      <div class="kit-container">
        <h2 class="kit-display" style="font-size:1.6rem;margin-bottom:1.25rem">Spectacole</h2>
        <?php component('event-grid', ['events' => $featured]); ?>
      </div>
    </section>
<?php });
