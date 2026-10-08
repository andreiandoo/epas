<?php
/**
 * /routes/{slug}: one editorial route (v2, Viaqui): a map in route mode, the stops in order, and the text.
 * Rendered by includes/v2/route-page.php; the data comes from assets/v2/data/map/routes.json.
 */
$pageCacheTTL = 1800;
require_once __DIR__ . '/includes/page-cache.php';
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/api.php';
require_once __DIR__ . '/includes/nav-helpers.php';
require_once __DIR__ . '/includes/v2/helpers.php';
require_once __DIR__ . '/includes/v2/product-icons.php';
require_once __DIR__ . '/includes/v2/places.php';
require_once __DIR__ . '/includes/v2/partners.php';

$rtAll = v2_routes();
$slug = preg_match('/^[a-z][a-z0-9-]{1,80}$/', (string) ($_GET['slug'] ?? '')) ? (string) $_GET['slug'] : '';
$route = $slug !== '' ? ($rtAll[$slug] ?? null) : null;
if (!$route) {
    http_response_code(404);
    require __DIR__ . '/404.php';
    exit;
}

$stops = $route['stops'];
$rtRoad = !empty($route['road']);
$rtKm = (int) $route['km'];
$rtMin = (int) ($route['min'] ?? 0);
$rtDrive = v2_hm($rtMin);
$rtCountryNames = array_column($route['countries'], 1);
$rtCodes = array_column($route['countries'], 0);
$rtCard = v2_route_card($slug, $route);

// The other routes: those of the same country first, then the rest.
$others = [];
foreach ($rtAll as $oSlug => $o) {
    if ($oSlug !== $slug) {
        $others[] = [(int) !array_intersect($rtCodes, array_column($o['countries'], 0)), v2_route_card($oSlug, $o)];
    }
}
usort($others, fn ($a, $b) => $a[0] <=> $b[0]);
$others = array_column(array_slice($others, 0, 3), 1);

$rtRegions = $route['regions'] ?? [];
// "Italy", "Italy and France", "Italy, France and Spain": the countries as one phrase of the visitor's language.
$rtCountriesText = count($rtCountryNames) > 1
    ? v2_t('{first} and {last}', ['first' => implode(', ', array_slice($rtCountryNames, 0, -1)), 'last' => (string) end($rtCountryNames)])
    : (string) ($rtCountryNames[0] ?? '');

// What a driver needs before setting off: a car where the route starts (the first stop's city when a partner has a
// page for it, otherwise the country), an airport transfer there and mobile data for the maps. No luggage storage:
// the bags travel in the boot.
$rtStart = $stops[0] ?? null;
$rtTrip = array_values(array_filter(
    v2_trip_links((string) ($rtStart[3] ?? ''), (string) ($rtCodes[0] ?? ''), (string) ($rtStart[2] ?? ''), (string) ($rtCountryNames[0] ?? '')),
    fn ($l) => $l['kind'] !== 'luggage'
));
usort($rtTrip, fn ($a, $b) => ($b['kind'] === 'car') <=> ($a['kind'] === 'car'));   // the car first, the rest in their order

$routePage = [
    'slug' => $slug,
    'trip' => $rtTrip,
    'title' => $route['title'],
    'lead' => $route['lead'],
    'emoji' => $route['icon'],
    'pace' => $route['pace'],
    'km' => $rtKm,
    'road' => $rtRoad,
    'drive' => $rtDrive,
    'stops' => $stops,
    'others' => $others,
    'breadcrumbs' => [[v2_t('Home'), '/'], [v2_t('Map'), '/map'], [v2_t('Routes'), '/routes'], [$route['title'], '/routes/' . $slug]],
    'config' => [
        'cartoKey' => defined('CARTO_API_KEY') ? CARTO_API_KEY : '',
        'urlState' => false,
        'fixed' => true,
        'title' => $route['title'],
        'base' => '/attraction/',
        'routeKm' => $rtKm,
        'routeMin' => $rtMin,
        'routeRoad' => $rtRoad,
        'routeGeometry' => $route['geometry'] ?? '',
        // Route mode builds its dataset from these, so no pin file is downloaded here.
        'routeStops' => $stops,
    ],
    'prose' => [
        '<p>' . v2_e($route['intro']) . '</p>',
        // One whole sentence for each case (how many regions, road or straight line, with or without a driving time).
        '<p>' . (count($rtRegions) > 1
                ? v2_t('The route has <strong>{stops}</strong> in {countries}, across {regions}.', ['stops' => v2_e(v2_num(count($stops), 'stop', 'stops')), 'countries' => v2_e($rtCountriesText), 'regions' => v2_e(v2_num(count($rtRegions), 'region', 'regions'))])
                : (count($rtRegions) === 1
                    ? v2_t('The route has <strong>{stops}</strong> in {countries}, in {region}.', ['stops' => v2_e(v2_num(count($stops), 'stop', 'stops')), 'countries' => v2_e($rtCountriesText), 'region' => v2_e($rtRegions[0])])
                    : v2_t('The route has <strong>{stops}</strong> in {countries}.', ['stops' => v2_e(v2_num(count($stops), 'stop', 'stops')), 'countries' => v2_e($rtCountriesText)])))
            . ' ' . ($rtRoad
                ? ($rtDrive !== ''
                    ? v2_t('From the first to the last there are <strong>{km} km by road</strong>, which is <strong>{time}</strong> of driving with no stops.', ['km' => v2_e(v2_thousands($rtKm)), 'time' => v2_e($rtDrive)])
                    : v2_t('From the first to the last there are <strong>{km} km by road</strong>.', ['km' => v2_e(v2_thousands($rtKm))]))
                : v2_t('Between the first and the last there are <strong>{km} km in a straight line</strong>.', ['km' => v2_e(v2_thousands($rtKm))]))
            . ' ' . v2_t('The numbers on the map and in the list are the same order; press a stop to see it on the map, or open its page for the description and the address.') . '</p>',
        '<p>' . v2_t('The route is a suggestion, not a schedule: you can drive it the other way round, split it over more days or take only the stops on your way. Every stop is also on <a href="{url}">the attractions map of {country}</a>, together with everything else there is to see around it.', ['url' => v2_e(v2_map_href($rtCodes[0])), 'country' => v2_e($rtCountryNames[0])]) . '</p>',
    ],
    'faq' => [
        [v2_t('How long does the route take?'), $rtDrive !== ''
            ? v2_t('The driving alone is {time}. With the stops, it depends how long you stay at each. We suggest {pace}, a pace that leaves time to go inside, not only to drive past.', ['time' => $rtDrive, 'pace' => $route['pace']])
            : v2_t('With the stops, it depends how long you stay at each. We suggest {pace}, a pace that leaves time to go inside, not only to drive past.', ['pace' => $route['pace']])],
        $rtRoad
            ? [v2_t('Is the distance by road?'), $rtDrive !== ''
                ? v2_t('Yes. The {km} km and the {time} are calculated on real roads, not in a straight line, with OpenStreetMap data. They do not include stops, traffic or detours.', ['km' => v2_thousands($rtKm), 'time' => $rtDrive])
                : v2_t('Yes. The {km} km are calculated on real roads, not in a straight line, with OpenStreetMap data. They do not include stops, traffic or detours.', ['km' => v2_thousands($rtKm)])]
            : [v2_t('Is the distance by road?'), v2_t('No. For this route the road could not be calculated, so the {km} km are measured in a straight line. For the real distance, open the route in Google Maps with the button under the map.', ['km' => v2_thousands($rtKm)])],
        [v2_t('Do I pay at every stop?'), v2_t('No. Some of the stops are free to enter; where a ticket is sold, it shows on the page of the place. Opening hours are set by whoever runs each place, so check them before you leave.')],
        [v2_t('Can I change the order?'), v2_t('Yes. The order here is the one that keeps the drive short, but the route works just as well backwards or in pieces.')],
    ],
];

$rtStopsText = v2_num(count($stops), 'stop', 'stops');
$pageTitle = v2_t('{title}: a route with {stops}', ['title' => $route['title'], 'stops' => $rtStopsText]);
if ($rtRoad && $rtDrive !== '') {
    $rtSummary = v2_t('A route with {stops} and {km} km by road ({time} of driving), with a map and navigation.', ['stops' => $rtStopsText, 'km' => v2_thousands($rtKm), 'time' => $rtDrive]);
} elseif ($rtRoad) {
    $rtSummary = v2_t('A route with {stops} and {km} km by road, with a map and navigation.', ['stops' => $rtStopsText, 'km' => v2_thousands($rtKm)]);
} elseif ($rtDrive !== '') {
    $rtSummary = v2_t('A route with {stops} and {km} km ({time} of driving), with a map and navigation.', ['stops' => $rtStopsText, 'km' => v2_thousands($rtKm), 'time' => $rtDrive]);
} else {
    $rtSummary = v2_t('A route with {stops} and {km} km, with a map and navigation.', ['stops' => $rtStopsText, 'km' => v2_thousands($rtKm)]);
}
$pageDescription = $route['lead'] . ' ' . $rtSummary;
$canonicalUrl = SITE_URL . '/routes/' . $slug;
$ogImage = $rtCard[7] ?: (SITE_URL . '/assets/images/og-default.jpg');

$structuredData = [[
    '@context' => 'https://schema.org', '@type' => 'TouristTrip',
    'name' => $route['title'], 'description' => $route['lead'], 'url' => $canonicalUrl,
    'itinerary' => [
        '@type' => 'ItemList', 'numberOfItems' => count($stops), 'itemListOrder' => 'https://schema.org/ItemListOrderAscending',
        'itemListElement' => array_map(
            fn ($s, $i) => ['@type' => 'ListItem', 'position' => $i + 1, 'url' => SITE_URL . '/attraction/' . $s[0], 'name' => $s[1]],
            $stops,
            array_keys($stops)
        ),
    ],
], [
    '@context' => 'https://schema.org', '@type' => 'FAQPage',
    'mainEntity' => array_map(fn ($f) => ['@type' => 'Question', 'name' => $f[0], 'acceptedAnswer' => ['@type' => 'Answer', 'text' => $f[1]]], $routePage['faq']),
], [
    '@context' => 'https://schema.org', '@type' => 'BreadcrumbList',
    'itemListElement' => array_map(
        fn ($bc, $i) => ['@type' => 'ListItem', 'position' => $i + 1, 'name' => $bc[0], 'item' => SITE_URL . $bc[1]],
        $routePage['breadcrumbs'],
        array_keys($routePage['breadcrumbs'])
    ),
]];

require __DIR__ . '/includes/v2/route-page.php';
