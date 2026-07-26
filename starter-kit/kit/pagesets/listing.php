<?php
/**
 * PAGESET: listing  — filters + event-grid + pagination. Kind-aware.
 * Used for repertoire/events/activities/season. Title from the active nav.
 */
$PAGE  = $PAGE ?? [];
$nav   = $PAGE['nav'] ?? 'events';
$title = $PAGE['title'] ?? (kit_nav_label($nav) ?: kit_term('events_cap', 'Evenimente'));
$page  = max(1, (int)($_GET['page'] ?? 1));
$cat   = $_GET['cat'] ?? '';

$resp   = kit_api_get(kit_events_endpoint(), array_filter(['per_page' => 12, 'page' => $page, 'category' => $cat]));
$events = kit_map_events($resp);
$meta   = kit_meta($resp);

// Category filter options (from the taxonomy). Silent-empty if none.
$catOptions = array_map(fn($c) => [$c['slug'], $c['name']], kit_categories());

layout('public', ['title' => $title . ' — ' . kit_cfg('site_name'), 'nav' => $nav],
function () use ($events, $meta, $page, $cat, $title, $nav, $catOptions) { ?>
  <section class="kit-section"><div class="kit-container">
    <h1 class="kit-display" style="font-size:clamp(1.8rem,4vw,2.6rem);margin-bottom:1.25rem"><?= e($title) ?></h1>
    <?php if ($catOptions) component('filters', [
        'action' => kit_nav_url($nav),
        'fields' => [['name' => 'cat', 'label' => kit_term('events_cap', 'Categorie'), 'value' => $cat, 'options' => $catOptions]],
    ]); ?>
    <?php component('event-grid', ['events' => $events, 'empty' => 'Nu există ' . kit_term('events', 'evenimente') . ' momentan.']); ?>
    <?php component('pagination', [
        'current' => $meta['current_page'] ?? $page, 'last' => $meta['last_page'] ?? 1,
        'base' => kit_nav_url($nav), 'query' => array_filter(['cat' => $cat]),
    ]); ?>
  </div></section>
<?php });
