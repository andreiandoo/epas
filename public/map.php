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
        header('Location: ' . $mpHref . '?city=' . rawurlencode($mpCity), true, 302);
        exit;
    }
}

$mpName = $mpCountry ? (string) $summary['name'] : 'Europe';
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
    'label' => 'Types of places', 'sub' => v2_num(count($exTypeRows), 'type', 'types'), 'unit' => 'places',
    'note' => 'Choose as many types as you like. With none chosen, the map shows them all.',
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
            'label' => 'Regions', 'sub' => v2_num(count($exRegionRows), 'region', 'regions'), 'unit' => 'attractions',
            'pills' => count($exRegionRows) > 16, 'search' => count($exRegionRows) > 16 ? 'Search a region' : '',
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
        'label' => 'Countries', 'sub' => v2_num(count($exCountryRows), 'country', 'countries'),
        'note' => 'Each country has its own map, with every attraction we list there.',
        'search' => 'Search a country', 'rows' => $exCountryRows,
    ];
}
$explorer[] = [
    'id' => 'cities', 'icon' => 'buildings', 'kind' => 'city',
    'label' => 'Cities', 'sub' => v2_num((int) ($summary['citiesTotal'] ?? count($exCityRows)), 'city', 'cities'),
    'search' => 'Search a city', 'rows' => $exCityRows,
    'more' => $mpCountry ? ['All cities in ' . $mpName, '/' . $mpCountrySlug] : ['All destinations', '/cities'],
];

// ------------------------------------------------------------------ editorial
$mpChurches = (int) ($mpTypeCount['churches'] ?? 0);
$mpTopCity = $summary['cities'][0] ?? null;
$mpTopTypes = implode(', ', array_map(fn ($t) => mb_strtolower($t[1]), array_slice($summary['types'] ?? [], 0, 5)));

if ($mpCountry) {
    $mpTopRegion = $summary['regions'][0] ?? null;
    $prose = [
        '<p>This map holds all ' . v2_e(v2_thousands($total)) . ' attractions we list in ' . v2_e($mpName) . ': ' . v2_e($mpTopTypes) . ' and more. Every pin opens the page of the place, with a description, the address and what else is nearby.</p>',
        '<p>' . ($mpTopRegion ? 'The region with the most is ' . v2_e($mpTopRegion[0]) . ' (' . v2_e(v2_thousands((int) $mpTopRegion[2])) . ')' : 'They are spread across the whole country')
            . ($mpTopCity ? ', and the city with the most is ' . v2_e($mpTopCity[1]) . ', with ' . v2_e(v2_thousands((int) $mpTopCity[4])) . '.' : '.')
            . ' The buttons above the map filter it in place by type, by region or by city, and whatever you choose ends up in the address of the page, so a copied link opens exactly what you were looking at.</p>',
        '<p>The map opens on the <strong>Popular</strong> selection, which leaves out churches, lakes and bridges; there are many of them and they would cover everything else. Turn them on at any time from <strong>Types of places</strong>. For a plain list, see <a href="/attractions?country=' . v2_e(strtolower($mpCountry['code'])) . '">the attractions of ' . v2_e($mpName) . '</a>; for the whole continent, <a href="/map">the map of Europe</a>.</p>',
    ];
    $faq = [
        ['How many attractions does the map of ' . $mpName . ' show?', v2_thousands($total) . ' places, each with checked coordinates and its own page on Viaqui.'],
        ['Can I see only the castles, or only the museums?', 'Yes. Open “Types of places” above the map and choose the ones you want; you can choose several at once, and the number next to each shows how many places it holds.'],
        ['Why are the churches not shown from the start?', 'There are a great many of them and they would hide the rest of the map. The Popular selection leaves them out; open “Types of places” and choose “Churches” to bring them back.'],
    ];
} else {
    $prose = [
        '<p>The map of Europe shows the ' . v2_e(v2_thousands($total)) . ' best-known attractions out of the ' . v2_e(v2_thousands((int) ($summary['catalogue'] ?? $total))) . ' we list in ' . count($summary['countries'] ?? []) . ' countries: ' . v2_e($mpTopTypes) . ' and more. Every pin opens the page of the place.</p>',
        '<p>Showing every attraction of the continent at once would make the map slow and unreadable, so each country has its own map with everything in it. Open <strong>Countries</strong> above the map and choose one, or start from a city.</p>',
        '<p>Search works on names and cities, and <strong>Near me</strong> centres the map on where you are and sorts the list by distance. For plain lists, see <a href="/attractions">the attractions page</a> or <a href="/cities">all destinations</a>.</p>',
    ];
    $faq = [
        ['Where do the places on the map come from?', 'From the Viaqui catalogue of attractions, built on open data from Wikidata and Wikimedia Commons: ' . v2_thousands((int) ($summary['catalogue'] ?? $total)) . ' places across Europe, each with its own page.'],
        ['Why does the map of Europe not show everything?', 'It shows the ' . v2_thousands($total) . ' best-known places, so that it stays fast. The map of each country shows every attraction we list there.'],
        ['Can I see only the castles, or only the museums?', 'Yes. Open “Types of places” above the map and choose the ones you want; you can choose several at once.'],
    ];
}
$faq[] = ['Can I book a ticket straight from the map?', 'Where a place has tickets or experiences on sale, its page has the booking button. The other attractions are places to visit, with no ticket sold here.'];
$faq[] = ['Does it work on a phone?', 'Yes. On a phone the map fills the screen and the list of results slides up from the bottom; you can drag it to see more of the map or more of the list.'];

// ------------------------------------------------------------------ page
$breadcrumbs = [['Home', '/'], ['Attractions', '/attractions'], ['Map', '/map']];
if ($mpCountry) {
    $breadcrumbs[] = [$mpName, $mpPath];
}

$mapPage = [
    'h1' => 'Attractions map',
    'h1em' => 'of ' . $mpName,
    'lead' => $mpCountry
        ? 'Every attraction we list in ' . $mpName . ', on one map. Choose the types you care about, search a place or see what is near you.'
        : 'The best-known places of the continent on one map. Choose a country to see everything in it, search a place or see what is near you.',
    'stats' => array_values(array_filter([
        [v2_thousands($total), ' attractions'],
        [(string) count($summary['types'] ?? []), ' types'],
        $mpCountry ? [v2_thousands((int) ($summary['citiesTotal'] ?? 0)), ' cities and towns'] : [(string) count($summary['countries'] ?? []), ' countries'],
    ])),
    'breadcrumbs' => $breadcrumbs,
    'summary' => $summary,
    'total' => $total,
    'explorer' => $explorer,
    'picks' => $summary['picks'] ?? [],
    'ideasHeading' => 'Not sure where to start?',
    'ideasLead' => 'The map shows everything at once, which is a lot. These are the places we would open first.',
    'ideasCta' => ['Start a plan', '/plan'],
    'picksHeading' => $mpCountry ? 'Places worth opening in ' . $mpName : 'Places worth opening',
    'listHref' => $mpCountry ? '/attractions?country=' . strtolower($mpCountry['code']) : '/attractions',
    'routeCards' => [],
    'config' => [
        'dataUrl' => '/assets/v2/data/map/' . $mpFile . '.json?v=' . rawurlencode((string) $summary['v']),
        'cartoKey' => defined('CARTO_API_KEY') ? CARTO_API_KEY : '',
        'urlState' => true,
        'preset' => 'popular',
        // the explorer above the map owns types and presets; the bar keeps the flag toggles
        'chips' => 'flags',
        'types' => [],
        'bounds' => $summary['bounds'] ?? null,
        'title' => 'Attractions in ' . $mpName,
        'base' => '/attraction/',
    ],
    'prose' => $prose,
    'faq' => $faq,
];

$pageTitle = 'Attractions map of ' . $mpName;
$pageDescription = 'Interactive map with ' . v2_thousands($total) . ($mpCountry ? ' attractions in ' . $mpName : ' of the best-known attractions in Europe') . ': ' . $mpTopTypes . '. Filter by type, search a place or see what is near you.';
$canonicalUrl = SITE_URL . $mpPath;
$ogImage = $mapPage['picks'][0][6] ?? (SITE_URL . '/assets/images/og-default.jpg');

$structuredData = [[
    '@context' => 'https://schema.org', '@type' => 'CollectionPage',
    'name' => $pageTitle, 'description' => $pageDescription, 'url' => $canonicalUrl, 'inLanguage' => 'en',
], [
    '@context' => 'https://schema.org', '@type' => 'ItemList', 'name' => 'Attractions on the map', 'numberOfItems' => $total,
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
