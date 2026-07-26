<?php
/** PAGE: /  (marketplace homepage) — hero + featured events. */
require __DIR__ . '/../includes/bootstrap.php';

$featured = kit_events(['per_page' => 8]);
$hero     = $featured[0] ?? null;

layout('public', ['title' => kit_cfg('site_name'), 'nav' => ''], function () use ($featured, $hero) {
    component('hero', [
        'eyebrow'  => kit_cfg('site_name'),
        'title'    => 'Bilete la cele mai bune evenimente',
        'subtitle' => 'Concerte, festivaluri, teatru și sport — într-un singur loc.',
        'image'    => $hero['hero_image_url'] ?? '',
        'actions'  => [['label' => 'Vezi evenimentele', 'url' => '/evenimente', 'style' => 'primary']],
    ]);
    ?>
    <section class="kit-section">
      <div class="kit-container">
        <h2 class="kit-display" style="font-size:1.6rem;margin-bottom:1.25rem">Recomandate</h2>
        <?php component('event-grid', ['events' => $featured]); ?>
      </div>
    </section>
<?php });
