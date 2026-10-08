<?php
/**
 * The trip planner (v2, Viaqui): /plan chooses a country, /plan/{country} is the planner for it. Say where and how
 * long, get a day-by-day itinerary built out of the attraction catalogue, then move it around until it is yours.
 *
 * Each country is its own dataset (assets/v2/data/map/<cc>.json, the one its map uses), so the planner works one
 * country at a time. Everything runs in the browser against that file (assets/v2/js/plan.js): no request per change,
 * no server state, and the plan lives in the URL and in localStorage, so a link is a plan. The map is EPMap in its
 * bare flavour: the page draws the list and the controls, and whatever the list has under its heading is what the
 * map shows.
 *
 * The planner is deterministic on purpose: no model writes these itineraries. The catalogue has no opening hours,
 * so durations are per-type estimates from includes/v2/plan-config.php and the page says so where it matters.
 *
 * /plan?route={slug} (the "Open as a plan" button of a route) is sent to the planner of the route's country.
 */
$pageCacheTTL = 1800;
require_once __DIR__ . '/includes/page-cache.php';
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/api.php';
require_once __DIR__ . '/includes/nav-helpers.php';
require_once __DIR__ . '/includes/v2/helpers.php';
require_once __DIR__ . '/includes/v2/plan-config.php';
require_once __DIR__ . '/includes/v2/product-icons.php';
require_once __DIR__ . '/includes/v2/places.php';
require_once __DIR__ . '/includes/v2/nav.php';

$plIndex = v2_map_file('index');
$plRoutes = v2_routes();
$plCountrySlug = preg_match('/^[a-z0-9][a-z0-9-]{1,60}$/', (string) ($_GET['country'] ?? '')) ? (string) $_GET['country'] : '';
$plCountry = $plCountrySlug !== '' ? ($plIndex['countries'][$plCountrySlug] ?? null) : null;
$summary = $plCountry ? v2_map_file(strtolower($plCountry['code']) . '.summary') : null;
if (!$plIndex || ($plCountrySlug !== '' && !$summary)) {
    http_response_code(404);
    require __DIR__ . '/404.php';
    exit;
}

// A route opened as a plan belongs to the planner of its country.
$plRouteSlug = preg_match('/^[a-z][a-z0-9-]{1,80}$/', (string) ($_GET['route'] ?? '')) ? (string) $_GET['route'] : '';
if (!$plCountry && $plRouteSlug !== '' && isset($plRoutes[$plRouteSlug])) {
    header('Location: ' . v2_url('/plan/' . $plRoutes[$plRouteSlug]['countries'][0][2]) . '?route=' . rawurlencode($plRouteSlug), true, 302);
    exit;
}

$v2PlaceIcons = true; // the route cards' icons (product-icons.php), printed by the header

// ====================================================================== /plan: choose the country
if (!$plCountry) {
    $plCountries = [];
    foreach ($plIndex['countries'] as $cSlug => $c) {
        $plCountries[] = [$cSlug, $c['code'], $c['name'], (int) $c['total']];
    }
    usort($plCountries, fn ($a, $b) => $b[3] <=> $a[3]);
    $routeCards = [];
    foreach ($plRoutes as $rSlug => $r) {
        $routeCards[] = v2_route_card($rSlug, $r);
    }
    shuffle($routeCards);
    $routeCards = array_slice($routeCards, 0, 6);

    $v2Styles = ['map-page.css', 'routes.css', 'plan.css', 'places.css'];
    $v2Scripts = ['trip-list.js'];
    $pageTitle = v2_t('Trip planner: a day-by-day itinerary with a map');
    $pageDescription = v2_t('Choose a country, say where you go and for how many days, and the planner builds your itinerary from the {count} attractions on the map: stops day by day, a map that follows the list, times and navigation.', ['count' => v2_thousands((int) $plIndex['total'])]);
    $canonicalUrl = SITE_URL . '/plan';
    $breadcrumbs = [[v2_t('Home'), '/'], [v2_t('Map'), '/map'], [v2_t('Trip planner'), '/plan']];
    $structuredData = [[
        '@context' => 'https://schema.org', '@type' => 'WebApplication', 'name' => v2_t('Viaqui trip planner'),
        'applicationCategory' => 'TravelApplication', 'operatingSystem' => 'Web', 'url' => $canonicalUrl, 'inLanguage' => v2_locale(),
        'offers' => ['@type' => 'Offer', 'price' => '0', 'priceCurrency' => 'EUR'],
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
        <nav class="crumbs" aria-label="<?= v2_te('Breadcrumb') ?>">
          <?php foreach ($breadcrumbs as $i => [$bcName, $bcUrl]): ?>
            <?php if ($i > 0): ?><span aria-hidden="true">/</span><?php endif; ?>
            <?php if ($i < count($breadcrumbs) - 1): ?><a href="<?= v2_e($bcUrl) ?>"><?= v2_e($bcName) ?></a><?php else: ?><span aria-current="page"><?= v2_e($bcName) ?></span><?php endif; ?>
          <?php endforeach; ?>
        </nav>
        <h1 id="mph-h"><?= v2_t('Trip planner <em>where to?</em>') ?></h1>
        <p class="mph-lead"><?= v2_te('Choose the country first. Then say where you start, where you go and for how many days, and you get an itinerary day by day, in the order the road links the stops. After that you move it around as you like.') ?></p>
      </div>
      <ul class="mph-stats">
        <li><b><?= count($plCountries) ?></b> <?= v2_e(v2_plural(count($plCountries), 'country', 'countries')) ?></li>
        <li><b><?= v2_e(v2_thousands((int) $plIndex['total'])) ?></b> <?= v2_e(v2_plural((int) $plIndex['total'], 'attraction', 'attractions')) ?></li>
        <li><b><?= count($plRoutes) ?></b> <?= v2_e(v2_plural(count($plRoutes), 'ready-made route', 'ready-made routes')) ?></li>
      </ul>
    </div>
  </section>

  <!-- The places kept with "Add to your trip" on attraction pages: drawn in the browser, where the list lives. -->
  <section class="sec tl" id="your-trip-list" data-trip-list aria-labelledby="tl-h">
    <div class="wrap">
      <div class="sec-head">
        <div>
          <h2 id="tl-h"><?= v2_te('Your trip list') ?></h2>
          <p class="sec-sub" data-trip-sum></p>
        </div>
      </div>
      <p class="tl-empty" data-trip-empty><?= v2_t('Nothing saved yet. Open an attraction and press <b>Add to your trip</b>: it shows up here, and the planner turns what you saved in a country into a plan, day by day. The list is kept in this browser, with no account needed.') ?></p>
      <div data-trip-groups></div>
    </div>
  </section>

  <section class="sec" aria-labelledby="plc-h">
    <div class="wrap">
      <div class="sec-head"><h2 id="plc-h"><?= v2_te('Which country?') ?></h2></div>
      <ul class="plc-grid">
        <?php foreach ($plCountries as [$cSlug, $cCode, $cName, $cTotal]): ?>
        <li><a class="plc" href="/plan/<?= v2_e($cSlug) ?>"><?= v2_flag($cCode) ?><b><?= v2_e($cName) ?></b><span><?= v2_e(v2_num($cTotal, 'attraction', 'attractions')) ?></span><?= v2_ic('arrow-right') ?></a></li>
        <?php endforeach; ?>
      </ul>
    </div>
  </section>

  <?php if ($routeCards): ?>
  <section class="sec" aria-labelledby="pl-routes-h">
    <div class="wrap">
      <div class="sec-head">
        <h2 id="pl-routes-h"><?= v2_te('Or take a ready-made route') ?></h2>
        <a class="sec-link" href="/routes"><?= v2_te('All routes') ?><?= v2_ic('arrow-right') ?></a>
      </div>
      <?php require __DIR__ . '/includes/v2/route-cards.php'; ?>
    </div>
  </section>
  <?php endif; ?>

  <section class="sec" aria-labelledby="pl-about-h">
    <div class="wrap mp-text">
      <div class="mp-prose">
        <h2 id="pl-about-h" class="sr"><?= v2_te('About the planner') ?></h2>
        <p><?= v2_te('The planner does not invent places: it takes the real attractions of the catalogue, filters them by what interests you, groups them into days so that each day stays in one area, and puts them in the order that keeps the driving short. No language model writes these itineraries, which is why it will never tell you something about a place that is not in the catalogue.') ?></p>
        <p><?= v2_te('It works one country at a time, because each country has its own map. A trip that crosses a border is two plans, one after the other; the ready-made routes are a good place to start for the best-known drives.') ?></p>
      </div>
      <div class="mp-faq">
        <details open><summary><?= v2_te('Is it free?') ?></summary><p><?= v2_te('Yes. The planner is free to use and needs no account. With an account you can also save your plans and find them again on another device.') ?></p></details>
        <details><summary><?= v2_te('Is the plan saved?') ?></summary><p><?= v2_te('Yes, in your browser and in the address of the page. The link in the address bar holds the whole plan, so you can send it to whoever travels with you.') ?></p></details>
        <details><summary><?= v2_te('Does it work for a motorcycle or a bicycle?') ?></summary><p><?= v2_te('Yes. You choose how you travel and the plan changes: on a motorcycle it looks for views and allows longer rides between stops, on a bicycle it stays close and measures the road on the cycling network.') ?></p></details>
      </div>
    </div>
  </section>
</main>
    <?php
    include __DIR__ . '/includes/v2/footer.php';
    return;
}

// ====================================================================== /plan/{country}: the planner
$plName = (string) $summary['name'];
$plCode = (string) $plCountry['code'];
$plPath = '/plan/' . $plCountrySlug;

// Starting points offered on the first screen: the cities with the most to see.
$startCities = array_slice($summary['cities'] ?? [], 0, 12);
$plTopCities = implode(', ', array_slice(array_column($summary['cities'] ?? [], 1), 0, 2));
$plTopRegion = (string) (($summary['regions'][0] ?? [])[0] ?? '');

// The routes of this country: as cards under the form, and as plans for ?route=<slug> (their stops in order,
// and how many days the author meant them to take: "3 days" in the route's pace).
$routeCards = [];
$routesForPlan = [];
foreach ($plRoutes as $rtSlug => $rt) {
    if (!in_array($plCode, array_column($rt['countries'], 0), true) || empty($rt['stops'])) {
        continue;
    }
    $routeCards[] = v2_route_card($rtSlug, $rt);
    $first = $rt['stops'][0];
    $last = $rt['stops'][count($rt['stops']) - 1];
    $routesForPlan[$rtSlug] = [
        'title' => $rt['title'],
        'days' => preg_match('/(\d+)\s*day/', (string) $rt['pace'], $rtM) ? max(1, min(7, (int) $rtM[1])) : 1,
        'stops' => array_column($rt['stops'], 0),
        'from' => $first[2] !== '' ? $first[2] : $first[1],
        'to' => $last[2] !== '' ? $last[2] : $last[1],
        'a' => [$first[7], $first[8]],
        'b' => [$last[7], $last[8]],
    ];
}
$routeCards = array_slice($routeCards, 0, 3);

$v2HeaderOverlay = true;   // the page opens on a dark band
$v2Styles = ['map.css', 'map-page.css', 'routes.css', 'plan.css', 'places.css'];
$v2Scripts = ['map.js', 'plan.js', 'trip-list.js'];
$v2ClientData = [
    'plan' => [
        'dataUrl' => '/assets/v2/data/map/' . strtolower($plCode) . '.json?v=' . rawurlencode((string) $summary['v']),
        'country' => strtolower($plCode),
        'countryName' => $plName,
        'cartoKey' => defined('CARTO_API_KEY') ? CARTO_API_KEY : '',
        'durations' => PLAN_DURATIONS,
        // the labels of these lists go to the script in the visitor's language (plan_labels() in plan-config.php)
        'paces' => plan_labels(PLAN_PACES, [0, 2]),
        'interests' => plan_labels(PLAN_INTERESTS),
        'company' => plan_labels(PLAN_COMPANY),
        'bookables' => [],
        'travel' => PLAN_TRAVEL,
        'swap' => PLAN_SWAP,
        'budgets' => PLAN_NIGHT_BUDGETS,
        'stops' => plan_labels(PLAN_STOP_PRESETS),
        'minutes' => PLAN_STOP_MINUTES,
        'cities' => array_map(fn ($c) => [$c[0], $c[1], $c[4]], $summary['cities'] ?? []),
        // [slug, name, count]: a zone of the dataset carries the region's slug, and that is what the planner filters on
        'regions' => array_map(fn ($r) => [$r[1], $r[0], $r[2]], $summary['regions'] ?? []),
        'total' => (int) $summary['total'],
        'party' => PLAN_PARTY,
        'stay22' => ['providers' => plan_labels(PLAN_STAY22['providers']), 'note' => plan_label(PLAN_STAY22['note'])] + PLAN_STAY22,
        'modes' => plan_labels(PLAN_MODES),
        'roads' => [],
        'routes' => $routesForPlan,
    ],
];

$pageTitle = v2_t('Trip planner for {country}: a day-by-day itinerary with a map', ['country' => $plName]);
$pageDescription = v2_t('Say where you go in {country}, for how many days and how you travel (car, motorcycle or bicycle), and the planner builds your itinerary from the {count} attractions on the map: stops day by day, a map that follows the list, times and navigation.', ['country' => $plName, 'count' => v2_thousands((int) $summary['total'])]);
$canonicalUrl = SITE_URL . $plPath;

$breadcrumbs = [[v2_t('Home'), '/'], [v2_t('Map'), '/map'], [v2_t('Trip planner'), '/plan'], [$plName, $plPath]];
$structuredData = [[
    '@context' => 'https://schema.org', '@type' => 'WebApplication', 'name' => v2_t('Viaqui trip planner for {country}', ['country' => $plName]),
    'applicationCategory' => 'TravelApplication', 'operatingSystem' => 'Web', 'url' => $canonicalUrl, 'inLanguage' => v2_locale(),
    'offers' => ['@type' => 'Offer', 'price' => '0', 'priceCurrency' => 'EUR'],
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
<?php require __DIR__ . '/includes/v2/plan-icons.php'; ?>
<main id="main" tabindex="-1">
  <!-- ============================== START ============================== -->
  <section class="pl-start plb" id="pl-start" data-mode="car" aria-labelledby="pl-h">
    <div class="plb-hero">
      <div class="wrap">
        <nav class="crumbs" aria-label="<?= v2_te('Breadcrumb') ?>">
          <?php foreach ($breadcrumbs as $i => [$bcName, $bcUrl]): ?>
            <?php if ($i > 0): ?><span aria-hidden="true">/</span><?php endif; ?>
            <?php if ($i < count($breadcrumbs) - 1): ?><a href="<?= v2_e($bcUrl) ?>"><?= v2_e($bcName) ?></a><?php else: ?><span aria-current="page"><?= v2_e($bcName) ?></span><?php endif; ?>
          <?php endforeach; ?>
        </nav>
        <h1 id="pl-h"><?= v2_flag($plCode) ?><?= v2_t('A trip through <em>{country}</em>', ['country' => v2_e($plName)]) ?></h1>
        <p class="pl-lead"><?= v2_t('Say where you start, where you go and how you travel. You get an itinerary from the {count} attractions we list in {country}, day by day, in the order the road links them. Then you move it around as you like. <a href="{url}">Another country</a>', ['count' => v2_e(v2_thousands((int) $summary['total'])), 'country' => v2_e($plName), 'url' => '/plan']) ?></p>
      </div>
    </div>
    <div id="hdr-sentinel" aria-hidden="true"></div>

    <div class="wrap plb-wrap">
      <p class="pl-saved-line" data-trip-country="<?= v2_e($plCountrySlug) ?>" hidden><?= v2_ic('heart') ?><span><?= v2_t('You saved <b data-trip-count></b> in {country}.', ['country' => v2_e($plName)]) ?></span><a class="btn btn-primary" href="<?= v2_e($plPath) ?>?list=1"><?= v2_te('Plan a trip with them') ?></a><a href="/plan#your-trip-list"><?= v2_te('See the list') ?></a></p>
      <form class="pl-form plb-card" id="pl-form" novalidate>
        <div class="plb-g">
          <span class="plb-l" id="pl-mode-l"><?= v2_te('How do you travel?') ?></span>
          <div class="plb-seg" id="pl-mode" role="group" aria-labelledby="pl-mode-l">
            <span class="plb-seg-thumb" aria-hidden="true"></span>
            <?php foreach (PLAN_MODES as $mKey => [$mLabel, $mIcon]): ?>
            <button type="button" data-mode="<?= v2_e($mKey) ?>" aria-pressed="<?= $mKey === 'car' ? 'true' : 'false' ?>"><?= v2_ic($mIcon) ?><?= v2_e(plan_label($mLabel)) ?></button>
            <?php endforeach; ?>
          </div>
        </div>

        <div class="plb-g">
          <div class="plb-rt">
            <div class="plb-rt-row">
              <span class="plb-dot" aria-hidden="true"></span>
              <div class="plb-rt-f pl-auto">
                <label for="pl-from-place"><?= v2_te('Where do you start?') ?></label>
                <input id="pl-from-place" type="text" autocomplete="off" placeholder="<?= v2_te('The city you set off from, in {country}', ['country' => $plName]) ?>" required aria-describedby="pl-from-hint">
                <ul class="pl-sugg" id="pl-sugg-from" role="listbox" hidden></ul>
              </div>
            </div>
            <div class="plb-rt-row is-to">
              <span class="plb-dot" aria-hidden="true"></span>
              <div class="plb-rt-f pl-auto">
                <label for="pl-where"><?= v2_te('Where are you going?') ?></label>
                <input id="pl-where" type="text" autocomplete="off" placeholder="<?= $plTopCities !== '' ? v2_te('A city or a region: {examples}…', ['examples' => $plTopCities . ($plTopRegion !== '' ? ', ' . $plTopRegion : '')]) : v2_te('A city or a region') ?>" aria-describedby="pl-where-hint">
                <ul class="pl-sugg" id="pl-sugg" role="listbox" hidden></ul>
              </div>
            </div>
            <div class="plb-rt-row is-back" id="pl-back-row" hidden>
              <span class="plb-dot" aria-hidden="true"></span>
              <div class="plb-rt-f pl-auto">
                <label for="pl-back"><?= v2_te('Where do you return?') ?></label>
                <input id="pl-back" type="text" autocomplete="off" placeholder="<?= v2_te('The last day ends here') ?>">
                <ul class="pl-sugg" id="pl-sugg-back" role="listbox" hidden></ul>
              </div>
            </div>
          </div>
          <p class="pl-hint"><span id="pl-from-hint"><?= v2_te('The drive is measured from where you start: the city you live in, or the one you land in.') ?></span> <span id="pl-where-hint"><?= v2_te('The destination is a city or a region of {country}.', ['country' => $plName]) ?></span></p>
          <button class="plb-link" type="button" id="pl-back-toggle" aria-expanded="false" aria-controls="pl-back-row"><?= v2_te('I return somewhere else') ?></button>
        </div>

        <div class="plb-tiles">
          <div class="plb-tile">
            <label for="pl-days"><?= v2_te('How many days?') ?></label>
            <div class="pl-stepper">
              <button class="pl-step-btn" type="button" data-days="-1" aria-label="<?= v2_te('One day fewer') ?>">−</button>
              <input id="pl-days" type="number" min="1" max="7" value="2" inputmode="numeric">
              <button class="pl-step-btn" type="button" data-days="1" aria-label="<?= v2_te('One day more') ?>">+</button>
            </div>
          </div>
          <div class="plb-tile">
            <label for="pl-from"><?= v2_t('Starting on <span class="pl-opt">(optional)</span>') ?></label>
            <input id="pl-from" type="date">
          </div>
          <fieldset class="plb-tile plb-tile-wide">
            <legend><?= v2_te('How many of you?') ?></legend>
            <div class="pl-party">
              <div class="pl-party-one">
                <div class="pl-stepper">
                  <button class="pl-step-btn" type="button" data-party="adults" data-step="-1" aria-label="<?= v2_te('One adult fewer') ?>">−</button>
                  <input id="pl-adults" type="number" min="1" max="<?= (int) PLAN_PARTY['adults_max'] ?>" value="<?= (int) PLAN_PARTY['adults'] ?>" inputmode="numeric" aria-label="<?= v2_te('Adults') ?>">
                  <button class="pl-step-btn" type="button" data-party="adults" data-step="1" aria-label="<?= v2_te('One adult more') ?>">+</button>
                </div>
                <label for="pl-adults"><?= v2_te('adults') ?></label>
              </div>
              <div class="pl-party-one">
                <div class="pl-stepper">
                  <button class="pl-step-btn" type="button" data-party="children" data-step="-1" aria-label="<?= v2_te('One child fewer') ?>">−</button>
                  <input id="pl-children" type="number" min="0" max="<?= (int) PLAN_PARTY['children_max'] ?>" value="<?= (int) PLAN_PARTY['children'] ?>" inputmode="numeric" aria-label="<?= v2_te('Children') ?>">
                  <button class="pl-step-btn" type="button" data-party="children" data-step="1" aria-label="<?= v2_te('One child more') ?>">+</button>
                </div>
                <label for="pl-children"><?= v2_te('children') ?></label>
              </div>
            </div>
          </fieldset>
        </div>

        <details class="plb-prefs" id="pl-prefs">
          <summary><span><b><?= v2_te('Preferences') ?></b><small id="pl-prefs-sum"></small></span><?= v2_ic('caret-down') ?></summary>
          <div class="plb-prefs-in">
            <fieldset class="plb-g">
              <legend class="plb-l"><?= v2_te('Who are you travelling with?') ?></legend>
              <div class="pl-chips" id="pl-company">
                <?php foreach (PLAN_COMPANY as $key => [$label, $emoji, $budget, $weights]): ?>
                <button class="pl-chip" type="button" data-company="<?= v2_e($key) ?>" aria-pressed="false"><span aria-hidden="true"><?= am_product_icon_svg($emoji, 'ic-em') ?></span><?= v2_e(plan_label($label)) ?></button>
                <?php endforeach; ?>
              </div>
              <p class="pl-hint"><?= v2_te('It tilts the suggestions towards what suits them. It is a weighting of types of places, not a label on each place.') ?></p>
            </fieldset>
            <fieldset class="plb-g">
              <legend class="plb-l"><?= v2_te('What interests you?') ?></legend>
              <div class="pl-chips" id="pl-interests">
                <?php foreach (PLAN_INTERESTS as $key => [$label, $emoji, $types]): ?>
                <button class="pl-chip" type="button" data-interest="<?= v2_e($key) ?>" aria-pressed="false"><span aria-hidden="true"><?= am_product_icon_svg($emoji, 'ic-em') ?></span><?= v2_e(plan_label($label)) ?></button>
                <?php endforeach; ?>
              </div>
              <p class="pl-hint"><?= v2_te('Leave them all off and you get a bit of everything.') ?></p>
            </fieldset>
            <fieldset class="plb-g">
              <legend class="plb-l"><?= v2_te('At what pace?') ?></legend>
              <div class="pl-chips plb-paces" id="pl-pace">
                <?php foreach (PLAN_PACES as $key => [$label, $minutes, $note]): ?>
                <button class="pl-chip pl-chip-pace" type="button" data-pace="<?= v2_e($key) ?>" aria-pressed="<?= $key === 'normal' ? 'true' : 'false' ?>"><b><?= v2_e(plan_label($label)) ?></b><small><?= v2_e(plan_label($note)) ?></small></button>
                <?php endforeach; ?>
              </div>
            </fieldset>
            <p class="pl-hint"><?= v2_te('The number of people matters when you look for a place to stay, for the nights between the days. On a motorcycle the plan looks for views and allows longer rides between stops; on a bicycle it stays close and measures the road on the cycling network.') ?></p>
          </div>
        </details>

        <div class="pl-actions">
          <button class="btn btn-primary pl-go" type="submit"><?= v2_te('Make my plan') ?><?= v2_ic('arrow-right') ?></button>
          <p class="pl-note"><?= v2_te('Nothing leaves your phone: the plan lives in the address of the page and in your browser.') ?></p>
        </div>
      </form>

      <div class="pl-quick">
        <p class="pl-quick-h"><?= v2_te('Or start from a city:') ?></p>
        <ul class="mp-chips">
          <?php foreach ($startCities as [$cSlug, $cName, , , $cCount]): ?>
          <li><button class="mp-chip" type="button" data-start-city="<?= v2_e($cSlug) ?>"><?= v2_e($cName) ?><b><?= v2_e(v2_thousands((int) $cCount)) ?></b></button></li>
          <?php endforeach; ?>
        </ul>
      </div>
    </div>
  </section>

  <!-- ============================== PLAN (built in the browser) ==============================
       One map, one list. The map holds its place — the top of a phone, the right of a desk — and the
       list scrolls beside it; what the list has under its heading is what the map shows. -->
  <section class="pl-plan plx" id="pl-plan" hidden data-mode="car" aria-labelledby="pl-plan-h">
    <h2 class="sr" id="pl-plan-h"><?= v2_te('Your plan') ?></h2>
    <p class="sr" id="pl-live" role="status" aria-live="polite"></p>

    <div class="plx-map" id="plx-map">
      <div class="plx-frame pl-map-frame">
        <div data-epm-root data-epm-config="<?= v2_e(json_encode([
            'cartoKey' => defined('CARTO_API_KEY') ? CARTO_API_KEY : '',
            'urlState' => false,
            'fixed' => true,
            'bare' => true,
            'title' => v2_t('Your plan'),
            'base' => '/attraction/',
            'view' => !empty($summary['bounds']) ? ['lat' => ($summary['bounds'][0][0] + $summary['bounds'][1][0]) / 2, 'lng' => ($summary['bounds'][0][1] + $summary['bounds'][1][1]) / 2, 'zoom' => 6] : null,
            'routeStops' => [],
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)) ?>"></div>
      </div>

      <div class="plx-top" id="plx-top">
        <div class="plx-bar">
          <button class="plx-ib" type="button" id="plx-back" aria-label="<?= v2_te('Change the details of the plan') ?>"><?= v2_ic('arrow-left') ?></button>
          <div class="plx-title"><b id="plx-t"></b><span id="plx-s"></span></div>
          <span class="plx-mode" id="plx-mode"></span>
          <button class="plx-ib" type="button" id="plx-menu-b" aria-label="<?= v2_te('What you can do with the plan') ?>" aria-expanded="false" aria-controls="plx-menu"><?= v2_ic('pl-dots') ?></button>
        </div>
        <div class="plx-days" id="plx-days" role="group" aria-label="<?= v2_te('The days of the plan') ?>"></div>
      </div>
      <div class="plx-menu" id="plx-menu" hidden></div>

      <div class="plx-prof" id="plx-prof" hidden>
        <div class="plx-prof-h"><span class="plx-prof-a"></span><span class="plx-prof-b"></span></div>
        <svg viewBox="0 0 300 44" preserveAspectRatio="none" aria-hidden="true"><path class="plx-prof-area"/><path class="plx-prof-line"/><line class="plx-prof-x" y1="0" y2="44"/></svg>
      </div>

      <div class="plx-ctl">
        <button type="button" class="plx-zoom" data-plx="in" aria-label="<?= v2_te('Zoom in') ?>"><?= v2_ic('plus') ?></button>
        <button type="button" class="plx-zoom" data-plx="out" aria-label="<?= v2_te('Zoom out') ?>"><?= v2_ic('pl-minus') ?></button>
        <button type="button" class="plx-size" data-plx="size" aria-label="<?= v2_te('A larger or a smaller map') ?>"><?= v2_ic('pl-size') ?></button>
        <button type="button" data-plx="fit" aria-label="<?= v2_te('Fit the route again') ?>"><?= v2_ic('pl-fit') ?></button>
      </div>

      <button class="plx-stay-btn" type="button" id="plx-stay-btn" hidden><?= v2_ic('pl-bed') ?><span><?= v2_te('Show places to stay') ?></span></button>

      <div class="plx-stay" id="plx-stay" hidden>
        <div class="plx-stay-head">
          <button class="plx-stay-x" type="button" id="plx-stay-x"><?= v2_ic('arrow-left') ?><?= v2_te('Back to the route') ?></button>
          <div class="plx-stay-prov" id="plx-stay-prov" role="group" aria-label="<?= v2_te('Where the places to stay come from') ?>">
            <?php $provFirst = true; foreach (PLAN_STAY22['providers'] as $pvKey => [$pvLabel]): ?>
            <button type="button" data-prov="<?= v2_e($pvKey) ?>" aria-pressed="<?= $provFirst ? 'true' : 'false' ?>"><?= v2_e(plan_label($pvLabel)) ?></button>
            <?php $provFirst = false; endforeach; ?>
          </div>
          <a class="plx-stay-out" id="plx-stay-out" href="<?= v2_e(PLAN_STAY22['link']) ?>" target="_blank" rel="noopener nofollow sponsored"><?= v2_te('Open the list on Stay22') ?><?= v2_ic('arrow-right') ?></a>
        </div>
        <div class="plx-stay-body" id="pl-pane-stay"></div>
      </div>
    </div>

    <div class="plx-sheet">
      <div class="plx-grab" id="plx-grab" role="separator" aria-orientation="horizontal" aria-label="<?= v2_te('Drag to change how much of the screen the map takes') ?>" tabindex="0"></div>
      <div class="plx-list" id="plx-list">
        <header class="pl-bar" id="pl-bar"></header>
        <div class="pl-days" id="pl-days-list"></div>
        <footer class="plx-end">
          <h3 class="plx-end-h"><?= v2_te('That is the whole trip') ?></h3>
          <div class="plx-end-acts" id="plx-end-acts"></div>
          <p class="rp-note" id="pl-map-note"></p>
          <p class="plx-end-p"><?= v2_te('What you moved, added or replaced stays where it is when you regenerate the rest. The link in the address bar holds the whole plan.') ?></p>
        </footer>
      </div>
    </div>
  </section>

  <?php if ($routeCards): ?>
  <!-- ============================== ALTERNATIVE: READY-MADE ROUTES ============================== -->
  <section class="sec" aria-labelledby="pl-routes-h">
    <div class="wrap">
      <div class="sec-head">
        <h2 id="pl-routes-h"><?= v2_te('Or take a ready-made route') ?></h2>
        <a class="sec-link" href="/routes?country=<?= v2_e(strtolower($plCode)) ?>"><?= v2_te('All routes in {country}', ['country' => $plName]) ?><?= v2_ic('arrow-right') ?></a>
      </div>
      <?php require __DIR__ . '/includes/v2/route-cards.php'; ?>
    </div>
  </section>
  <?php endif; ?>

  <section class="sec" aria-labelledby="pl-about-h">
    <div class="wrap mp-text">
      <div class="mp-prose">
        <h2 id="pl-about-h" class="sr"><?= v2_te('About the planner') ?></h2>
        <p><?= v2_te('The planner does not invent places: it takes the real attractions of the catalogue, filters them by what interests you, groups them into days so that each day stays in one area, and puts them in the order that keeps the driving short. The best-known places come first. No language model writes these itineraries, which is why it will never tell you something about a place that is not in the catalogue.') ?></p>
        <p><?= v2_t('Visiting times are <strong>estimates by type of place</strong>: a castle 90 minutes, a museum 75, a church 25. The catalogue does not hold the opening hours of each place yet, so check them before you go. Kilometres and driving times are calculated by road; where the road cannot be calculated you see an estimate, marked as such.') ?></p>
        <p><?= v2_t('The map stays on screen and follows the list: the day you have reached, then the stop and its neighbours, then the town you sleep in. The plan is yours: drag the stops where you want, within the day or to another day, remove what you do not like, add something else. If a stop does not suit you, open it and choose <em>Replace</em>: you get a few places near it, chosen by the same interests, and the one you pick takes its position in the day.') ?></p>
      </div>
      <div class="mp-faq">
        <details open><summary><?= v2_te('How do you know how long I stay at each place?') ?></summary><p><?= v2_te('We do not: these are estimates by type of place, shown as such. You can change them for each stop.') ?></p></details>
        <details><summary><?= v2_te('I do not like a stop. Can I put something else in its place?') ?></summary><p><?= v2_te('Yes. Open the stop and choose Replace: a short list of places nearby opens under it, with the photo, the type, the town, how far they are and how long the visit takes, chosen by the same interests and the same company the plan was made for.') ?></p></details>
        <details><summary><?= v2_te('Can I add a break or a meal?') ?></summary><p><?= v2_te('Yes, anywhere in the day: the button “A stop of your own” at the head of the day puts it at the end, and the + between two stops puts it exactly there. You choose how long it takes and whether it stays at the previous stop, has no particular place, or is somewhere else; the drive and the times are recalculated.') ?></p></details>
        <details><summary><?= v2_te('What about places to stay?') ?></summary><p><?= v2_te('Between days we suggest a town to sleep in, chosen to keep both the evening and the next morning short; you can change it or remove it. When the list reaches a night, the map moves to its town, and the button on the map opens the list of places to stay right there, in place of the map. They come from Booking, Expedia, Vrbo and others; if you book one of them, the website earns a commission, at no extra cost to you.') ?></p></details>
        <details><summary><?= v2_te('Does it work for a motorcycle or a bicycle?') ?></summary><p><?= v2_te('Yes. You choose how you travel and the plan changes: on a motorcycle it looks for views and allows longer rides between stops, on a bicycle it stays close and measures the road on the cycling network. For both you see the elevation profile of the day, linked to the map.') ?></p></details>
        <details><summary><?= v2_te('Can I buy tickets from here?') ?></summary><p><?= v2_te('Not directly from the plan for now. Where a place sells tickets through Viaqui, its page has the booking button, and the stop in the plan leads there.') ?></p></details>
        <details><summary><?= v2_te('Is the plan saved?') ?></summary><p><?= v2_te('Yes, in your browser and in the address of the page. If you clear your browser data, the link still works.') ?></p></details>
        <details><summary><?= v2_te('Does it work offline?') ?></summary><p><?= v2_te('No, but you can print the plan or open it in Google Maps day by day, to have it offline there.') ?></p></details>
      </div>
    </div>
  </section>
</main>
<?php include __DIR__ . '/includes/v2/footer.php'; ?>
