<?php
/**
 * Attractions (points of interest): /atractii and /{oras}/atractii (v2 design, includes/v2/am-hub.php).
 *
 * Reads GET /attractions (?city=, ?tip= → type, ?search=, ?sort=, ?pagina=). Cards open /atractie/{slug}.
 *
 * The city lives in the path, so the filter's Oraș field submits ?oras= and this file sends the browser on to
 * the canonical /{oras}/atractii. That happens before the page cache, so no redirect is ever cached.
 */

if (isset($_GET['oras'])) {
    $boTo = (string) $_GET['oras'];
    $boRest = $_GET;
    unset($boRest['oras'], $boRest['city'], $boRest['pagina']);
    $boQs = http_build_query(array_filter($boRest, fn ($v) => is_string($v) && $v !== ''));
    header('Location: ' . (preg_match('/^[a-z][a-z0-9-]{1,50}$/', $boTo) ? '/' . $boTo . '/atractii' : '/atractii')
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
$sorts = ['' => 'Recomandate', 'nume' => 'Alfabetic', 'activitati' => 'Cu bilete și activități'];
$sort = (string) ($_GET['sort'] ?? '');
if (!isset($sorts[$sort])) {
    $sort = '';
}
$hasFilter = $q !== '' || $type !== '' || $citySlug !== '';

$base = $citySlug !== '' ? '/' . $citySlug . '/atractii' : '/atractii';
/** The same list with some of the filters swapped; null clears one. Page numbers never carry over. */
$url = function (array $over = [], ?string $forCity = null) use ($base, $q, $type, $sort) {
    $args = array_merge(['q' => $q, 'tip' => $type, 'sort' => $sort], $over);
    $path = $forCity === null ? $base : ($forCity !== '' ? '/' . $forCity . '/atractii' : '/atractii');
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
        'kicker' => $a['type'] ?: 'Atracție',
        'title' => $a['name'],
        'aria' => $a['name'] . ($a['city'] !== '' ? ', în ' . $a['city'] : ''),
        'meta' => array_values(array_filter([$a['city'] !== '' ? ['map-pin', $a['city']] : null])),
        'price' => null,
        'badges' => $n > 0 ? [v2_num($n, 'activitate', 'activități')] : [],
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

$breadcrumbs = [['name' => 'Acasă', 'url' => SITE_URL . '/']];
if ($citySlug !== '') {
    $breadcrumbs[] = ['name' => $cityName, 'url' => SITE_URL . '/' . $citySlug];
}
$breadcrumbs[] = ['name' => 'Atracții', 'url' => SITE_URL . $base];

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
$cityOptions = [['', 'Toată țara']];
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

$typeChips = [['Toate', $url(['tip' => '']), $type === '']];
foreach (AM_ATTRACTION_TYPES as $ts => $tn) {
    $typeChips[] = [$tn . (!empty($typeCounts[$ts]) ? ' · ' . v2_thousands($typeCounts[$ts]) : ''), $url(['tip' => $ts]), $ts === $type];
}

$active = [];
if ($citySlug !== '') {
    $active[] = [$cityName, $url([], '')];
}
if ($q !== '') {
    $active[] = ['„' . $q . '”', $url(['q' => ''])];
}
if ($type !== '') {
    $active[] = [AM_ATTRACTION_TYPES[$type], $url(['tip' => ''])];
}

$total = (int) ($pag['total'] ?? count($items));
$typeName = $type !== '' ? AM_ATTRACTION_TYPES[$type] : '';
$countLine = $total > 0
    ? v2_num($total, 'atracție', 'atracții') . ($hasFilter ? ($citySlug !== '' && count($active) === 1 ? ' în ' . $cityName : ', după filtrele tale') : ' în toată țara')
    : 'Niciun rezultat pentru filtrele alese';

$sortOptions = [];
foreach ($sorts as $sv => $sl) {
    $sortOptions[] = [$sv, $sl];
}

// Interactive map over the list. The pin dataset is static (bin/build-map-data.php); when it has
// not been built the helper returns null and no map button is printed at all. The page's own Tip
// and city filters carry into the map, where Tip becomes multi-select.
$hubMap = null;
if ($mapData) {
    $hubMap = [
        'heading' => 'Harta atracțiilor din România',
        'note'    => v2_thousands($mapData['total']) . ' de atracții pe hartă. Alege ce vrei să vezi din filtrul Tip și apasă pe un punct.',
        'cta'     => 'Deschide harta',
        'config'  => [
            'dataUrl'  => $mapData['url'],
            'cartoKey' => defined('CARTO_API_KEY') ? CARTO_API_KEY : '',
            'dialog'   => true,
            'urlState' => true,
            'preset'   => 'popular',
            'types'    => $type !== '' ? [$type] : [],
            'city'     => $citySlug,
            'title'    => 'Atracții' . ($cityName !== '' ? ' în ' . $cityName : ' din România'),
            'base'     => '/atractie/',
        ],
    ];
}
$hub = [
    'tight' => true,
    'kicker' => $typeName !== '' ? $typeName : 'Locuri de văzut',
    'title' => 'Atracții',
    'titleEm' => $cityName !== '' ? 'în ' . $cityName : 'din România',
    'lead' => 'Castele, muzee, mănăstiri, parcuri și priveliști. Pe fiecare pagină vezi unde se află și ce poți face în jur.',
    'stats' => array_values(array_filter([$total ? v2_num($total, 'atracție', 'atracții') : ''])),
    'image' => $items[0]['image'] ?? null,
    'breadcrumbs' => $breadcrumbs,
    'map' => $hubMap,
    'filter' => [
        'action' => $base,
        'search' => ['name' => 'q', 'value' => $q, 'placeholder' => 'Caută o atracție după nume', 'clear' => $url(['q' => ''])],
        'fields' => [
            ['name' => 'oras', 'label' => 'Oraș', 'value' => $citySlug, 'options' => $cityOptions, 'find' => 'Caută orașul'],
            ['name' => 'sort', 'label' => 'Sortare', 'value' => $sort, 'options' => $sortOptions],
        ],
        'chips' => ['label' => 'Tip', 'items' => $typeChips],
        'hidden' => ['tip' => $type],
        'active' => $active,
        'reset' => $hasFilter ? '/atractii' : null,
        'count' => $countLine,
    ],
    'items' => $items,
    'heading' => 'Atracții' . ($typeName !== '' ? ': ' . $typeName : '') . ($cityName !== '' ? ' în ' . $cityName : ''),
    'page' => (int) ($pag['current_page'] ?? $page),
    'last' => (int) ($pag['last_page'] ?? 1),
    'pageUrl' => fn (int $p) => $url(['pagina' => $p > 1 ? $p : '']),
    'empty' => ['Nu am găsit atracții' . ($typeName !== '' ? ' de tipul „' . $typeName . '”' : '') . ($q !== '' ? ' după „' . $q . '”' : '') . ($cityName !== '' ? ' în ' . $cityName : '') . '.', 'Încearcă alt tip, alt oraș sau alt cuvânt.', ['Toate atracțiile', '/atractii']],
];

$pageTitleRaw = ($typeName !== '' ? $typeName . ': atracții' : 'Atracții') . ($cityName !== '' ? ' în ' . $cityName : ' din România') . ($page > 1 ? ' (pagina ' . $page . ')' : '') . ' | bilete.online';
$pageDescription = 'Atracții' . ($cityName !== '' ? ' în ' . $cityName : ' din România') . ($typeName !== '' ? ' (' . mb_strtolower($typeName) . ')' : '') . ': castele, muzee, mănăstiri, parcuri și priveliști, cu hartă și lucruri de făcut în apropiere.';
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
