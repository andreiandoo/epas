<?php
/**
 * The attractions map: /map (Europe) and /map/{country} (v2, Viaqui; rendered by includes/v2/map-page.php).
 *
 * /map carries the best-known attractions of the continent, so the page stays light; a country page carries every
 * attraction of that country. Both read only the small summary next to the pin file (assets/v2/data/map, written
 * by plans/viaqui-data/build_map_data.py); the browser fetches the pins.
 *
 * ?type=, ?region= and ?city= are read by the map itself (assets/v2/js/map.js). /map?city={slug} is sent to the
 * map of that city's country, because the Europe map may not hold the city at all.
 */
$pageCacheTTL = 1800;
require_once __DIR__ . '/includes/page-cache.php';
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/api.php';
require_once __DIR__ . '/includes/nav-helpers.php';
require_once __DIR__ . '/includes/v2/helpers.php';
require_once __DIR__ . '/includes/v2/am-labels.php';
require_once __DIR__ . '/includes/v2/places.php';

$mpIndex = v2_map_file('index');
$mpCountrySlug = preg_match('/^[a-z0-9][a-z0-9-]{1,60}$/', (string) ($_GET['country'] ?? '')) ? (string) $_GET['country'] : '';
$mpCountry = $mpCountrySlug !== '' ? ($mpIndex['countries'][$mpCountrySlug] ?? null) : null;
$summary = $mpCountry ? v2_map_file(strtolower($mpCountry['code']) . '.summary') : ($mpCountrySlug === '' ? v2_map_file('europe.summary') : null);
if (!$mpIndex || !$summary) {
    // No dataset, no map page: better a 404 than a page whose whole point never loads.
    http_response_code(404);
    require __DIR__ . '/404.php';
    exit;
}

$mpCity = preg_match('/^[a-z0-9][a-z0-9-]{1,80}$/', (string) ($_GET['city'] ?? '')) ? (string) $_GET['city'] : '';
if (!$mpCountry && $mpCity !== '') {
    $mpCityRow = navGetCityBySlug($mpCity);
    $mpHref = is_array($mpCityRow) ? v2_map_href((string) ($mpCityRow['country'] ?? '')) : '/map';
    if ($mpHref !== '/map') {
        header('Location: ' . v2_url($mpHref) . '?city=' . rawurlencode($mpCity), true, 302);
        exit;
    }
}

$mpName = $mpCountry ? (string) $summary['name'] : v2_t('Europe');
$mpPath = $mpCountry ? '/map/' . $mpCountrySlug : '/map';
$mpFile = $mpCountry ? strtolower($mpCountry['code']) : 'europe';
$total = (int) $summary['total'];
$mpTypeCount = array_column($summary['types'] ?? [], 3, 0);

// ------------------------------------------------------------------ the explorer panels
$exTypeRows = [];
foreach ($summary['types'] ?? [] as [$tSlug, $tName, , $tCount]) {
    $exTypeRows[] = [$tSlug, am_place_icon($tSlug), $tName, $tCount, '/attractions?type=' . rawurlencode($tSlug) . ($mpCountry ? '&country=' . strtolower($mpCountry['code']) : '')];
}
$exCityRows = [];
foreach ($summary['cities'] ?? [] as [$ctSlug, $ctName, , , $ctCount]) {
    $exCityRows[] = [$ctSlug, '', $ctName, $ctCount, '/' . $ctSlug . '/attractions'];
}

$explorer = [[
    'id' => 'types', 'icon' => 'squares-four', 'kind' => 'type',
    'label' => v2_t('Types of places'), 'sub' => v2_num(count($exTypeRows), 'type', 'types'), 'unit' => 'places',
    'note' => v2_t('Choose as many types as you like; with none chosen, the map shows them all.'),
    'presets' => true, 'rows' => $exTypeRows,
]];
if ($mpCountry) {
    $exRegionRows = [];
    foreach ($summary['regions'] ?? [] as [$rName, $rSlug, $rCount]) {
        $exRegionRows[] = [$rSlug, 'map', $rName, $rCount, $mpPath . '?region=' . rawurlencode($rSlug)];
    }
    if (count($exRegionRows) > 1) {
        $explorer[] = [
            'id' => 'regions', 'icon' => 'globe-simple', 'kind' => 'region',
            'label' => v2_t('Regions'), 'sub' => v2_num(count($exRegionRows), 'region', 'regions'), 'unit' => 'attractions',
            'pills' => count($exRegionRows) > 16, 'search' => count($exRegionRows) > 16 ? v2_t('Search a region') : '',
            'rows' => $exRegionRows,
        ];
    }
} else {
    $exCountryRows = [];
    foreach ($summary['countries'] ?? [] as [$cCode, $cName, $cSlug, $cCount]) {
        $exCountryRows[] = [$cSlug, $cCode, $cName, $cCount, '/map/' . $cSlug];
    }
    $explorer[] = [
        'id' => 'countries', 'icon' => 'globe-simple', 'kind' => 'go', 'flags' => true,
        'label' => v2_t('Countries'), 'sub' => v2_num(count($exCountryRows), 'country', 'countries'),
        'note' => v2_t('Pick a country and see everything worth the trip there, on one map.'),
        'search' => v2_t('Search a country'), 'rows' => $exCountryRows,
    ];
}
$explorer[] = [
    'id' => 'cities', 'icon' => 'buildings', 'kind' => 'city',
    'label' => v2_t('Cities'), 'sub' => v2_num((int) ($summary['citiesTotal'] ?? count($exCityRows)), 'city', 'cities'),
    'search' => v2_t('Search a city'), 'rows' => $exCityRows,
    'more' => $mpCountry ? [v2_t('All cities in {country}', ['country' => $mpName]), '/' . $mpCountrySlug] : [v2_t('All destinations'), '/cities'],
];

// ------------------------------------------------------------------ editorial
$mpChurches = (int) ($mpTypeCount['churches'] ?? 0);
$mpTopCity = $summary['cities'][0] ?? null;
$mpTopTypes = implode(', ', array_map(fn ($t) => mb_strtolower($t[1]), array_slice($summary['types'] ?? [], 0, 5)));

if ($mpCountry) {
    $mpTopRegion = $summary['regions'][0] ?? null;
    // One whole sentence for each case, so that a translation can order the words as its language asks.
    $mpRegionVars = $mpTopRegion ? ['region' => v2_e($mpTopRegion[0]), 'regionCount' => v2_e(v2_thousands((int) $mpTopRegion[2]))] : [];
    $mpCityVars = $mpTopCity ? ['city' => v2_e($mpTopCity[1]), 'cityCount' => v2_e(v2_thousands((int) $mpTopCity[4]))] : [];
    if ($mpTopRegion && $mpTopCity) {
        $mpMost = v2_t('The region with the most is {region} ({regionCount}), and the city with the most is {city}, with {cityCount}.', $mpRegionVars + $mpCityVars);
    } elseif ($mpTopRegion) {
        $mpMost = v2_t('The region with the most is {region} ({regionCount}).', $mpRegionVars);
    } elseif ($mpTopCity) {
        $mpMost = v2_t('They are spread across the whole country, and the city with the most is {city}, with {cityCount}.', $mpCityVars);
    } else {
        $mpMost = v2_t('They are spread across the whole country.');
    }
    $prose = [
        '<p>' . v2_t('This map holds all {count} attractions we list in {country}: {types} and more. Every pin opens the page of the place, with a description, the address and what else is nearby.', ['count' => v2_e(v2_thousands($total)), 'country' => v2_e($mpName), 'types' => v2_e($mpTopTypes)]) . '</p>',
        '<p>' . $mpMost . ' ' . v2_t('The buttons above the map filter it in place by type, by region or by city, and whatever you choose ends up in the address of the page, so a copied link opens exactly what you were looking at.') . '</p>',
        '<p>' . v2_t('The map opens on the <strong>Popular</strong> selection, which leaves out churches, lakes and bridges; there are many of them and they would cover everything else. Turn them on at any time from <strong>Types of places</strong>. For a plain list, see <a href="{listUrl}">the attractions of {country}</a>; for the whole continent, <a href="{mapUrl}">the map of Europe</a>.', ['listUrl' => '/attractions?country=' . v2_e(strtolower($mpCountry['code'])), 'country' => v2_e($mpName), 'mapUrl' => '/map']) . '</p>',
    ];
    $faq = [
        [v2_t('How many attractions does the map of {country} show?', ['country' => $mpName]), v2_t('{count} places, each with checked coordinates and its own page on Viaqui.', ['count' => v2_thousands($total)])],
        [v2_t('Can I see only the castles, or only the museums?'), v2_t('Yes. Open “Types of places” above the map and choose the ones you want; you can choose several at once, and the number next to each shows how many places it holds.')],
        [v2_t('Why are the churches not shown from the start?'), v2_t('There are a great many of them and they would hide the rest of the map. The Popular selection leaves them out; open “Types of places” and choose “Churches” to bring them back.')],
    ];
} else {
    $prose = [
        '<p>' . v2_t('The map of Europe shows the {count} best-known attractions out of the {catalogue} we list in {countries}: {types} and more. Every pin opens the page of the place.', ['count' => v2_e(v2_thousands($total)), 'catalogue' => v2_e(v2_thousands((int) ($summary['catalogue'] ?? $total))), 'countries' => v2_e(v2_num(count($summary['countries'] ?? []), 'country', 'countries')), 'types' => v2_e($mpTopTypes)]) . '</p>',
        '<p>' . v2_t('Showing every attraction of the continent at once would make the map slow and unreadable, so each country has its own map with everything in it. Open <strong>Countries</strong> above the map and choose one, or start from a city.') . '</p>',
        '<p>' . v2_t('Search works on names and cities, and <strong>Near me</strong> centres the map on where you are and sorts the list by distance. For plain lists, see <a href="{attractionsUrl}">the attractions page</a> or <a href="{citiesUrl}">all destinations</a>.', ['attractionsUrl' => '/attractions', 'citiesUrl' => '/cities']) . '</p>',
    ];
    $faq = [
        [v2_t('Where do the places on the map come from?'), v2_t('From the Viaqui catalogue of attractions, built on open data from Wikidata and Wikimedia Commons: {count} places across Europe, each with its own page.', ['count' => v2_thousands((int) ($summary['catalogue'] ?? $total))])],
        [v2_t('Why does the map of Europe not show everything?'), v2_t('It shows the {count} best-known places, so that it stays fast. The map of each country shows every attraction we list there.', ['count' => v2_thousands($total)])],
        [v2_t('Can I see only the castles, or only the museums?'), v2_t('Yes. Open “Types of places” above the map and choose the ones you want; you can choose several at once.')],
    ];
}
$faq[] = [v2_t('Can I book a ticket straight from the map?'), v2_t('Where a place has tickets or experiences on sale, its page has the booking button. The other attractions are places to visit, with no ticket sold here.')];
$faq[] = [v2_t('Does it work on a phone?'), v2_t('Yes. On a phone the map fills the screen and the list of results slides up from the bottom; you can drag it to see more of the map or more of the list.')];

// ------------------------------------------------------------------ routes worth showing here
// A country shows the routes that cross it; the map of Europe shows three, different on each rebuild of the page.
$mpRouteCards = [];
foreach (v2_routes() as $rtSlug => $rt) {
    if (!$mpCountry || in_array($mpCountry['code'], array_column($rt['countries'], 0), true)) {
        $mpRouteCards[] = v2_route_card($rtSlug, $rt);
    }
}
if (!$mpCountry) {
    shuffle($mpRouteCards);
}
$mpRouteCards = array_slice($mpRouteCards, 0, 6);

// ------------------------------------------------------------------ page
$breadcrumbs = [[v2_t('Home'), '/'], [v2_t('Attractions'), '/attractions'], [v2_t('Map'), '/map']];
if ($mpCountry) {
    $breadcrumbs[] = [$mpName, $mpPath];
}

$mapPage = [
    // the heading as one sentence, with its second line marked up: "Attractions map <em>of Italy</em>"
    'h1html' => v2_t('Attractions map <em>of {place}</em>', ['place' => v2_e($mpName)]),
    'lead' => $mpCountry
        ? v2_t('Every attraction we list in {country}, on one map. Choose the types you care about, search a place or see what is near you.', ['country' => $mpName])
        : v2_t('The best-known places of the continent on one map. Choose a country to see everything in it, search a place or see what is near you.'),
    'stats' => array_values(array_filter([
        [v2_thousands($total), ' ' . v2_plural($total, 'attraction', 'attractions')],
        [(string) count($summary['types'] ?? []), ' ' . v2_plural(count($summary['types'] ?? []), 'type', 'types')],
        $mpCountry
            ? [v2_thousands((int) ($summary['citiesTotal'] ?? 0)), ' ' . v2_plural((int) ($summary['citiesTotal'] ?? 0), 'city or town', 'cities and towns')]
            : [(string) count($summary['countries'] ?? []), ' ' . v2_plural(count($summary['countries'] ?? []), 'country', 'countries')],
    ])),
    'breadcrumbs' => $breadcrumbs,
    'summary' => $summary,
    'total' => $total,
    'explorer' => $explorer,
    'picks' => $summary['picks'] ?? [],
    'ideasHeading' => v2_t('Not sure where to start?'),
    'ideasLead' => v2_t('The map shows everything at once, which is a lot. These are the routes already drawn and the places we would open first.'),
    'ideasCta' => [v2_t('Start a plan'), $mpCountry ? '/plan/' . $mpCountrySlug : '/plan'],
    'picksHeading' => $mpCountry ? v2_t('Places worth opening in {country}', ['country' => $mpName]) : v2_t('Places worth opening'),
    'listHref' => $mpCountry ? '/attractions?country=' . strtolower($mpCountry['code']) : '/attractions',
    'routeCards' => $mpRouteCards,
    'routesHeading' => $mpCountry ? v2_t('Routes through {country}', ['country' => $mpName]) : v2_t('Ready-made routes'),
    'config' => [
        'dataUrl' => '/assets/v2/data/map/' . $mpFile . '.json?v=' . rawurlencode((string) $summary['v']),
        'cartoKey' => defined('CARTO_API_KEY') ? CARTO_API_KEY : '',
        'urlState' => true,
        'preset' => 'popular',
        // the explorer above the map owns types and presets; the bar keeps the flag toggles
        'chips' => 'flags',
        'types' => [],
        'bounds' => $summary['bounds'] ?? null,
        'title' => v2_t('Attractions in {place}', ['place' => $mpName]),
        'base' => '/attraction/',
    ],
    'prose' => $prose,
    'faq' => $faq,
];

$pageTitle = v2_t('Attractions map of {place}', ['place' => $mpName]);
$pageDescription = $mpCountry
    ? v2_t('Interactive map with {count} attractions in {country}: {types}. Filter by type, search a place or see what is near you.', ['count' => v2_thousands($total), 'country' => $mpName, 'types' => $mpTopTypes])
    : v2_t('Interactive map with {count} of the best-known attractions in Europe: {types}. Filter by type, search a place or see what is near you.', ['count' => v2_thousands($total), 'types' => $mpTopTypes]);
$canonicalUrl = SITE_URL . $mpPath;
$ogImage = $mapPage['picks'][0][6] ?? (SITE_URL . '/assets/images/og-default.jpg');

$structuredData = [[
    '@context' => 'https://schema.org', '@type' => 'CollectionPage',
    'name' => $pageTitle, 'description' => $pageDescription, 'url' => $canonicalUrl, 'inLanguage' => v2_locale(),
], [
    '@context' => 'https://schema.org', '@type' => 'ItemList', 'name' => v2_t('Attractions on the map'), 'numberOfItems' => $total,
    'itemListElement' => array_map(
        fn ($p, $i) => ['@type' => 'ListItem', 'position' => $i + 1, 'url' => SITE_URL . '/attraction/' . $p[0], 'name' => $p[1]],
        $mapPage['picks'],
        array_keys($mapPage['picks'])
    ),
], [
    '@context' => 'https://schema.org', '@type' => 'FAQPage',
    'mainEntity' => array_map(fn ($f) => ['@type' => 'Question', 'name' => $f[0], 'acceptedAnswer' => ['@type' => 'Answer', 'text' => $f[1]]], $faq),
], [
    '@context' => 'https://schema.org', '@type' => 'BreadcrumbList',
    'itemListElement' => array_map(
        fn ($bc, $i) => ['@type' => 'ListItem', 'position' => $i + 1, 'name' => $bc[0], 'item' => SITE_URL . $bc[1]],
        $breadcrumbs,
        array_keys($breadcrumbs)
    ),
]];

require __DIR__ . '/includes/v2/map-page.php';
