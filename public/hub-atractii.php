<?php
/**
 * Attractions (points of interest): /attractions and /{city}/attractions (v2 design, includes/v2/am-hub.php).
 *
 * Reads GET /attractions (?city=, ?tip= → type, ?search=, ?sort=, ?pagina=). Cards open /attraction/{slug}.
 *
 * The city lives in the path, so the filter's City field submits ?oras= and this file sends the browser on to
 * the canonical /{city}/attractions. That happens before the page cache, so no redirect is ever cached.
 */

if (isset($_GET['oras'])) {
    $boTo = (string) $_GET['oras'];
    $boRest = $_GET;
    unset($boRest['oras'], $boRest['city'], $boRest['pagina']);
    $boQs = http_build_query(array_filter($boRest, fn ($v) => is_string($v) && $v !== ''));
    header('Location: ' . (preg_match('/^[a-z][a-z0-9-]{1,50}$/', $boTo) ? '/' . $boTo . '/attractions' : '/attractions')
        . ($boQs !== '' ? '?' . $boQs : ''), true, 302);
    exit;
}

$pageCacheTTL = 600;
require_once __DIR__ . '/includes/page-cache.php';
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/api.php';
require_once __DIR__ . '/includes/nav-helpers.php';
require_once __DIR__ . '/includes/v2/helpers.php';
require_once __DIR__ . '/includes/v2/am-labels.php';

/* am_hub_city() resolves the slug through /locations/cities/{slug}, which only answers for
   cities marked visible. Plenty of localities hold attractions without being a marketplace city —
   Alba Iulia and Sighișoara as much as a village the import created — and for those the page was
   a 404 even though the attractions exist. Fall back to the slug, and take the display name from
   the attractions themselves; a slug with no attractions is still a 404, further down. */
$hubCity = am_hub_city();
$cityUnlisted = false;
if ($hubCity === null) {
    $raw = (string) ($_GET['city'] ?? '');
    if (!preg_match('/^[a-z][a-z0-9-]{1,50}$/', $raw)) {
        http_response_code(404);
        require __DIR__ . '/404.php';
        exit;
    }
    $hubCity = [$raw, ''];
    $cityUnlisted = true;
}
[$citySlug, $cityName] = $hubCity;
$page = am_hub_page();

// ---------------------------------------------------------------- what the visitor asked for
$type = (string) ($_GET['tip'] ?? '');
if (!isset(AM_ATTRACTION_TYPES[$type])) {
    $type = '';
}
$q = trim((string) ($_GET['q'] ?? ''));
if (mb_strlen($q) > 80) {
    $q = mb_substr($q, 0, 80);
}
$sorts = ['' => v2_t('Recommended'), 'nume' => v2_t('A to Z'), 'activitati' => v2_t('With tickets and activities')];
$typeNames = am_attraction_types_t();   // AM_ATTRACTION_TYPES in the visitor's language
$sort = (string) ($_GET['sort'] ?? '');
if (!isset($sorts[$sort])) {
    $sort = '';
}
$hasFilter = $q !== '' || $type !== '' || $citySlug !== '';

$base = $citySlug !== '' ? '/' . $citySlug . '/attractions' : '/attractions';
/** The same list with some of the filters swapped; null clears one. Page numbers never carry over. */
$url = function (array $over = [], ?string $forCity = null) use ($base, $q, $type, $sort) {
    $args = array_merge(['q' => $q, 'tip' => $type, 'sort' => $sort], $over);
    $path = $forCity === null ? $base : ($forCity !== '' ? '/' . $forCity . '/attractions' : '/attractions');
    $qs = http_build_query(array_filter($args, fn ($v) => $v !== '' && $v !== null));

    return $path . ($qs !== '' ? '?' . $qs : '');
};

$params = ['per_page' => 24, 'page' => $page]
    + ($citySlug !== '' ? ['city' => $citySlug] : [])
    + ($type !== '' ? ['type' => $type] : [])
    + ($q !== '' ? ['search' => $q] : [])
    + ($sort !== '' ? ['sort' => $sort === 'activitati' ? 'activities' : 'name'] : []);
$resp = api_cached('am_attractions_' . md5(json_encode($params)), fn () => api_get('/attractions', $params), 600);
$rows = (!empty($resp['success']) && is_array($resp['data']['items'] ?? null)) ? $resp['data']['items'] : [];
$pag = $resp['data']['pagination'] ?? ['current_page' => 1, 'last_page' => 1, 'total' => count($rows)];

$items = [];
foreach ($rows as $row) {
    $a = v2_attraction($row);
    if (!$a) {
        continue;
    }
    if ($cityUnlisted && $cityName === '' && ($row['city']['slug'] ?? '') === $citySlug) {
        $cityName = navFlatName($row['city']['name'] ?? '');
    }
    $n = (int) ($row['activities_count'] ?? 0);
    $items[] = [
        'href' => $a['href'],
        'image' => $a['image'],
        'kicker' => $a['type'] ?: v2_t('Attraction'),
        'title' => $a['name'],
        'aria' => $a['city'] !== '' ? v2_t('{name}, in {city}', ['name' => $a['name'], 'city' => $a['city']]) : $a['name'],
        'meta' => array_values(array_filter([$a['city'] !== '' ? ['map-pin', $a['city']] : null])),
        'price' => null,
        'badges' => $n > 0 ? [v2_num($n, 'activity', 'activities')] : [],
    ];
}

// A slug nothing is filed under is not a city page.
if ($cityUnlisted && ($cityName === '' || !$items)) {
    http_response_code(404);
    require __DIR__ . '/404.php';
    exit;
}

// A page past the last one is not a list.
if ($page > 1 && !$items) {
    http_response_code(404);
    require __DIR__ . '/404.php';
    exit;
}

// ?search= is newer than some deployments of the core. If the list came back unfiltered, keep only what
// matches here, and say so by counting what is left — better a short honest list than an ignored search.
if ($q !== '' && $items) {
    $fold = fn (string $t) => strtr(mb_strtolower($t), ['ă' => 'a', 'â' => 'a', 'î' => 'i', 'ș' => 's', 'ş' => 's', 'ț' => 't', 'ţ' => 't']);
    $needle = $fold($q);
    $kept = array_values(array_filter($items, fn ($it) => mb_strpos($fold($it['title']), $needle) !== false));
    if (count($kept) < count($items)) {
        $items = $kept;
        $pag['total'] = count($items);
        $pag['last_page'] = 1;
    }
}

$breadcrumbs = [['name' => v2_t('Home'), 'url' => SITE_URL . '/']];
if ($citySlug !== '') {
    $breadcrumbs[] = ['name' => $cityName, 'url' => SITE_URL . '/' . $citySlug];
}
$breadcrumbs[] = ['name' => v2_t('Attractions'), 'url' => SITE_URL . $base];

// ---------------------------------------------------------------- what the filter can offer
// The pin dataset's own summary already counts every attraction per type and per city, so the filter can offer
// the places that actually hold something instead of the whole country's list of localities.
$mapData = v2_map_data();
$summary = v2_map_summary();
$typeCounts = [];
foreach ((array) ($summary['types'] ?? []) as $t) {
    if (is_array($t) && isset($t[0])) {
        $typeCounts[(string) $t[0]] = (int) ($t[3] ?? 0);
    }
}
$cityOptions = [['', v2_t('Anywhere')]];
$seenCity = [];
foreach ((array) ($summary['cities'] ?? []) as $c) {
    if (!is_array($c) || empty($c[0])) {
        continue;
    }
    $cityOptions[] = [(string) $c[0], (string) $c[1] . (!empty($c[4]) ? ' (' . (int) $c[4] . ')' : '')];
    $seenCity[(string) $c[0]] = true;
}
if ($citySlug !== '' && empty($seenCity[$citySlug])) {
    $cityOptions[] = [$citySlug, $cityName];
}

$typeChips = [[v2_t('All'), $url(['tip' => '']), $type === '']];
foreach ($typeNames as $ts => $tn) {
    $typeChips[] = [$tn . (!empty($typeCounts[$ts]) ? ' · ' . v2_thousands($typeCounts[$ts]) : ''), $url(['tip' => $ts]), $ts === $type];
}

$active = [];
if ($citySlug !== '') {
    $active[] = [$cityName, $url([], '')];
}
if ($q !== '') {
    $active[] = ['“' . $q . '”', $url(['q' => ''])];
}
if ($type !== '') {
    $active[] = [$typeNames[$type], $url(['tip' => ''])];
}

$total = (int) ($pag['total'] ?? count($items));
$typeName = $type !== '' ? $typeNames[$type] : '';
$totalText = v2_num($total, 'attraction', 'attractions');
if ($total <= 0) {
    $countLine = v2_t('No results for these filters');
} elseif (!$hasFilter) {
    $countLine = v2_t('{count} across Europe', ['count' => $totalText]);
} elseif ($citySlug !== '' && count($active) === 1) {
    $countLine = v2_t('{count} in {city}', ['count' => $totalText, 'city' => $cityName]);
} else {
    $countLine = v2_t('{count} matching your filters', ['count' => $totalText]);
}

$sortOptions = [];
foreach ($sorts as $sv => $sl) {
    $sortOptions[] = [$sv, $sl];
}

// Interactive map over the list. The pin dataset is static (bin/build-map-data.php); when it has
// not been built the helper returns null and no map button is printed at all. The page's own Type
// and city filters carry into the map, where Type becomes multi-select.
$hubMap = null;
if ($mapData) {
    $hubMap = [
        'heading' => v2_t('The map of attractions'),
        'note'    => v2_t('{n} attractions on the map. Pick what you want to see with the Type filter and tap a point.', ['n' => v2_thousands($mapData['total'])]),
        'cta'     => v2_t('Open the map'),
        'config'  => [
            'dataUrl'  => $mapData['url'],
            'cartoKey' => defined('CARTO_API_KEY') ? CARTO_API_KEY : '',
            'dialog'   => true,
            'urlState' => true,
            'preset'   => 'popular',
            'types'    => $type !== '' ? [$type] : [],
            'city'     => $citySlug,
            'title'    => $cityName !== '' ? v2_t('Attractions in {city}', ['city' => $cityName]) : v2_t('Attractions in Europe'),
            'base'     => '/attraction/',
        ],
    ];
}
$hub = [
    'tight' => true,
    'kicker' => $typeName !== '' ? $typeName : v2_t('Places to see'),
    'title' => v2_t('Attractions'),
    'titleHtml' => $cityName !== ''
        ? v2_t('Attractions <em>in {city}</em>', ['city' => v2_e($cityName)])
        : v2_t('Attractions <em>across Europe</em>'),
    'lead' => v2_t('Castles, museums, monasteries, parks and viewpoints. Each page shows where the place is and what you can do around it.'),
    'stats' => array_values(array_filter([$total ? $totalText : ''])),
    'image' => $items[0]['image'] ?? null,
    'breadcrumbs' => $breadcrumbs,
    'map' => $hubMap,
    'filter' => [
        'action' => $base,
        'search' => ['name' => 'q', 'value' => $q, 'placeholder' => v2_t('Search for an attraction by name'), 'clear' => $url(['q' => ''])],
        'fields' => [
            ['name' => 'oras', 'label' => v2_t('City'), 'value' => $citySlug, 'options' => $cityOptions, 'find' => v2_t('Search for a city')],
            ['name' => 'sort', 'label' => v2_t('Sort by'), 'value' => $sort, 'options' => $sortOptions],
        ],
        'chips' => ['label' => v2_t('Type'), 'items' => $typeChips],
        'hidden' => ['tip' => $type],
        'active' => $active,
        'reset' => $hasFilter ? '/attractions' : null,
        'count' => $countLine,
    ],
    'items' => $items,
    'heading' => $typeName !== ''
        ? ($cityName !== '' ? v2_t('Attractions: {type} in {city}', ['type' => $typeName, 'city' => $cityName]) : v2_t('Attractions: {type}', ['type' => $typeName]))
        : ($cityName !== '' ? v2_t('Attractions in {city}', ['city' => $cityName]) : v2_t('Attractions')),
    'page' => (int) ($pag['current_page'] ?? $page),
    'last' => (int) ($pag['last_page'] ?? 1),
    'pageUrl' => fn (int $p) => $url(['pagina' => $p > 1 ? $p : '']),
    // What was asked for is named as a list (type, words, city), so the sentence stays whole in every language.
    'empty' => [
        ($emptyFor = implode(', ', array_filter([$typeName, $q !== '' ? '“' . $q . '”' : '', $cityName]))) !== ''
            ? v2_t('We found no attractions for: {filters}.', ['filters' => $emptyFor])
            : v2_t('We found no attractions.'),
        v2_t('Try another type, another city or another word.'),
        [v2_t('All attractions'), '/attractions'],
    ],
];

if ($typeName !== '') {
    $pageTitleRaw = $cityName !== '' ? v2_t('{type}: attractions in {city}', ['type' => $typeName, 'city' => $cityName]) : v2_t('{type}: attractions in Europe', ['type' => $typeName]);
    $pageDescription = $cityName !== ''
        ? v2_t('Attractions in {city} ({type}): castles, museums, monasteries, parks and viewpoints, with a map and things to do nearby.', ['city' => $cityName, 'type' => mb_strtolower($typeName)])
        : v2_t('Attractions across Europe ({type}): castles, museums, monasteries, parks and viewpoints, with a map and things to do nearby.', ['type' => mb_strtolower($typeName)]);
} else {
    $pageTitleRaw = $cityName !== '' ? v2_t('Attractions in {city}', ['city' => $cityName]) : v2_t('Attractions in Europe');
    $pageDescription = $cityName !== ''
        ? v2_t('Attractions in {city}: castles, museums, monasteries, parks and viewpoints, with a map and things to do nearby.', ['city' => $cityName])
        : v2_t('Attractions across Europe: castles, museums, monasteries, parks and viewpoints, with a map and things to do nearby.');
}
if ($page > 1) {
    $pageTitleRaw = v2_t('{title} (page {n})', ['title' => $pageTitleRaw, 'n' => $page]);
}
$pageTitleRaw .= ' | Viaqui';
// The canonical list is the plain one for this city and type: a search or a re-sort is only a view of it.
$canonicalQs = http_build_query(array_filter(['tip' => $type, 'pagina' => $page > 1 ? $page : null]));
$canonicalUrl = SITE_URL . $base . ($canonicalQs !== '' ? '?' . $canonicalQs : '');
// A searched or re-sorted list is a slice of the plain one: out of the index, pointing at the canonical page.
if ($q !== '' || $sort !== '') {
    $noindex = true;
}
$ogImage = $hub['image'] ?: (SITE_URL . '/assets/images/og-default.jpg');
$structuredData = [[
    '@context' => 'https://schema.org',
    '@type' => 'ItemList',
    'name' => $hub['heading'],
    'numberOfItems' => $total,
    'itemListElement' => array_map(fn ($it, $i) => ['@type' => 'ListItem', 'position' => ($page - 1) * 24 + $i + 1, 'url' => SITE_URL . $it['href'], 'name' => $it['title']], $items, array_keys($items)),
], [
    '@context' => 'https://schema.org',
    '@type' => 'BreadcrumbList',
    'itemListElement' => array_map(fn ($bc, $i) => ['@type' => 'ListItem', 'position' => $i + 1, 'name' => $bc['name'], 'item' => $bc['url']], $breadcrumbs, array_keys($breadcrumbs)),
]];

require __DIR__ . '/includes/v2/am-hub.php';
