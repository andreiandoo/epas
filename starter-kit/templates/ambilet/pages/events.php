<?php
/**
 * PAGE: /evenimente  (marketplace event listing)
 *
 * Same components as the tenant repertoire page — event-grid, filters,
 * pagination — but this is the MARKETPLACE profile. The only difference is
 * site.config.php + theme.css. Proof that one component set serves both.
 */
require __DIR__ . '/../includes/bootstrap.php';

$page = max(1, (int)($_GET['page'] ?? 1));
$cat  = $_GET['cat'] ?? '';

$resp   = kit_api_get(kit_events_endpoint(), array_filter(['per_page' => 12, 'page' => $page, 'category' => $cat]));
$events = kit_map_events($resp);
$meta   = kit_meta($resp);

layout('public', [
    'title' => 'Evenimente — ' . kit_cfg('site_name'),
    'nav'   => 'events',
], function () use ($events, $meta, $page, $cat) { ?>
    <section class="kit-section">
      <div class="kit-container">
        <h1 class="kit-display" style="font-size:clamp(1.8rem,4vw,2.6rem);margin-bottom:1.25rem">Evenimente</h1>

        <?php component('filters', [
            'action' => '/evenimente',
            'fields' => [[
                'name' => 'cat', 'label' => 'Categorie', 'value' => $cat,
                'options' => [['festival','Festival'],['concert','Concert'],['balet','Balet'],['teatru','Teatru']],
            ]],
        ]); ?>

        <?php component('event-grid', ['events' => $events]); ?>

        <?php component('pagination', [
            'current' => $meta['current_page'] ?? $page,
            'last'    => $meta['last_page'] ?? 1,
            'base'    => '/evenimente',
            'query'   => array_filter(['cat' => $cat]),
        ]); ?>
      </div>
    </section>
<?php });
