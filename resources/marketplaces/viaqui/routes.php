<?php
/**
 * /routes: the index of the editorial routes (v2, Viaqui). Each route is an ordered list of real attractions from
 * the catalogue; the facts come from assets/v2/data/map/routes.json (plans/viaqui-data/build_routes.py).
 */
$pageCacheTTL = 1800;
require_once __DIR__ . '/includes/page-cache.php';
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/api.php';
require_once __DIR__ . '/includes/nav-helpers.php';
require_once __DIR__ . '/includes/v2/helpers.php';
require_once __DIR__ . '/includes/v2/product-icons.php';
require_once __DIR__ . '/includes/v2/places.php';
require_once __DIR__ . '/includes/v2/nav.php';

$rxRoutes = v2_routes();
if (!$rxRoutes) {
    http_response_code(404);
    require __DIR__ . '/404.php';
    exit;
}

// Grouped by country, in the order of the file; a country filter is a plain link (?country=it).
$rxCountries = [];
foreach ($rxRoutes as $r) {
    foreach ($r['countries'] as [$cCode, $cName]) {
        $rxCountries[$cCode] = $cName;
    }
}
asort($rxCountries);
$rxCountry = strtoupper((string) ($_GET['country'] ?? ''));
$rxCountry = isset($rxCountries[$rxCountry]) ? $rxCountry : '';

$routeCards = [];
$rxStops = 0;
$rxKm = 0;
foreach ($rxRoutes as $slug => $r) {
    $rxStops += (int) $r['count'];
    $rxKm += (int) $r['km'];
    if ($rxCountry === '' || in_array($rxCountry, array_column($r['countries'], 0), true)) {
        $routeCards[] = v2_route_card($slug, $r);
    }
}

$v2Styles = ['map-page.css', 'routes.css', 'places.css'];
$v2PlaceIcons = true; // the route cards' icons (product-icons.php), printed by the header

$rxWhere = $rxCountry !== '' ? $rxCountries[$rxCountry] : 'Europe';
$pageTitle = 'Routes through ' . $rxWhere . ': ' . v2_num(count($routeCards), 'itinerary', 'itineraries') . ' with a map';
$pageDescription = v2_num(count($routeCards), 'ready-made route', 'ready-made routes') . ' through ' . $rxWhere
    . ', from the castles of the Rhine to the temples of Sicily: real stops in the order the road links them, each with a map, road distances and navigation.';
$canonicalUrl = SITE_URL . '/routes' . ($rxCountry !== '' ? '?country=' . strtolower($rxCountry) : '');
$ogImage = $routeCards[0][7] ?? (SITE_URL . '/assets/images/og-default.jpg');

$breadcrumbs = [['Home', '/'], ['Map', '/map'], ['Routes', '/routes']];
$structuredData = [[
    '@context' => 'https://schema.org', '@type' => 'CollectionPage',
    'name' => 'Routes through ' . $rxWhere, 'description' => $pageDescription, 'url' => $canonicalUrl, 'inLanguage' => 'en',
], [
    '@context' => 'https://schema.org', '@type' => 'ItemList', 'name' => 'Routes', 'numberOfItems' => count($routeCards),
    'itemListElement' => array_map(
        fn ($c, $i) => ['@type' => 'ListItem', 'position' => $i + 1, 'url' => SITE_URL . '/routes/' . $c[0], 'name' => $c[1]],
        $routeCards,
        array_keys($routeCards)
    ),
], [
    '@context' => 'https://schema.org', '@type' => 'BreadcrumbList',
    'itemListElement' => array_map(
        fn ($bc, $i) => ['@type' => 'ListItem', 'position' => $i + 1, 'name' => $bc[0], 'item' => SITE_URL . $bc[1]],
        $breadcrumbs,
        array_keys($breadcrumbs)
    ),
]];

include __DIR__ . '/includes/v2/head.php';
include __DIR__ . '/includes/v2/header.php';
?>
<main id="main" tabindex="-1">
  <section class="mph" aria-labelledby="mph-h">
    <div class="wrap mph-in">
      <div class="mph-copy">
        <nav class="crumbs" aria-label="Breadcrumb">
          <?php foreach ($breadcrumbs as $i => [$bcName, $bcUrl]): ?>
            <?php if ($i > 0): ?><span aria-hidden="true">/</span><?php endif; ?>
            <?php if ($i < count($breadcrumbs) - 1): ?><a href="<?= v2_e($bcUrl) ?>"><?= v2_e($bcName) ?></a><?php else: ?><span aria-current="page"><?= v2_e($bcName) ?></span><?php endif; ?>
          <?php endforeach; ?>
        </nav>
        <h1 id="mph-h">Routes <em>through <?= v2_e($rxWhere) ?></em></h1>
        <p class="mph-lead">Ready-made itineraries built from places that are already on the map: the order to visit them in, the road distance between stops and a button that opens the whole drive in Google Maps.</p>
      </div>
      <ul class="mph-stats">
        <li><b><?= count($rxRoutes) ?></b> routes</li>
        <li><b><?= v2_e(v2_thousands($rxStops)) ?></b> stops</li>
        <li><b><?= v2_e(v2_thousands($rxKm)) ?></b> km</li>
        <li><b><?= count($rxCountries) ?></b> countries</li>
      </ul>
    </div>
  </section>

  <section class="sec" aria-labelledby="rx-h">
    <div class="wrap">
      <h2 class="sr" id="rx-h">All routes</h2>
      <p class="rx-filter" aria-label="Routes by country">
        <a href="/routes"<?= $rxCountry === '' ? ' aria-current="true"' : '' ?>>All countries</a>
        <?php foreach ($rxCountries as $cCode => $cName): ?>
        <a href="/routes?country=<?= v2_e(strtolower($cCode)) ?>"<?= $rxCountry === $cCode ? ' aria-current="true"' : '' ?>><?= v2_flag($cCode) ?><?= v2_e($cName) ?></a>
        <?php endforeach; ?>
      </p>
      <?php require __DIR__ . '/includes/v2/route-cards.php'; ?>
    </div>
  </section>

  <section class="sec" aria-labelledby="rx-about-h">
    <div class="wrap mp-text">
      <div class="mp-prose">
        <h2 id="rx-about-h" class="sr">About the routes</h2>
        <p>A route is a list of stops in the order the road links them. Every stop is a real attraction from the catalogue, with its own page, and the map shows the stops numbered and joined by a line, so you can see at a glance how the trip unfolds.</p>
        <p>Kilometres and times are calculated on real roads, with OpenStreetMap data, not in a straight line. They do not include stops, traffic or detours. The navigation button sends the whole route to Google Maps, with every stop as a waypoint.</p>
        <p>Routes are suggestions, not schedules. You can drive them the other way round, split them over more days or take only the stops that are on your way. To start from one place and see what is around it, open <a href="/map">the attractions map</a>.</p>
      </div>
      <div class="mp-faq">
        <details open><summary>Where do the stops come from?</summary><p>From the Viaqui catalogue of attractions. Every stop has its own page and checked coordinates, and is also on the map of its country.</p></details>
        <details><summary>Do I pay to get in?</summary><p>It depends on the place. Some are free to enter, others charge a ticket set by whoever runs them. Where a ticket is sold through Viaqui, it shows on the page of the place.</p></details>
        <details><summary>Can I follow a route on my phone?</summary><p>Yes. The map fills the screen and the list of stops slides up from the bottom. The navigation button opens the route straight in your maps app.</p></details>
        <details><summary>How long does a route take?</summary><p>Each one says how many days we would give it, at a pace that leaves time to go inside, not only to drive past. The driving time alone is shown next to it.</p></details>
        <details><summary>Will there be more routes?</summary><p>Yes, as more places enter the catalogue. If you have a route to suggest, write to us at <?= v2_e(SUPPORT_EMAIL) ?>.</p></details>
      </div>
    </div>
  </section>
</main>
<?php include __DIR__ . '/includes/v2/footer.php'; ?>
