<?php
/**
 * Attractions (points of interest): /atractii and /{oras}/atractii (v2 design, includes/v2/am-hub.php).
 *
 * Reads GET /attractions (?city=, ?tip= → type, ?pagina=). Cards open /atractie/{slug}.
 */

$pageCacheTTL = 600;
require_once __DIR__ . '/includes/page-cache.php';
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/api.php';
require_once __DIR__ . '/includes/nav-helpers.php';
require_once __DIR__ . '/includes/v2/helpers.php';
require_once __DIR__ . '/includes/v2/am-labels.php';

$hubCity = am_hub_city();
if ($hubCity === null) {
    http_response_code(404);
    require __DIR__ . '/404.php';
    exit;
}
[$citySlug, $cityName] = $hubCity;
$page = am_hub_page();
$type = (string) ($_GET['tip'] ?? '');
if (!isset(AM_ATTRACTION_TYPES[$type])) {
    $type = '';
}

$params = ['per_page' => 24, 'page' => $page] + ($citySlug !== '' ? ['city' => $citySlug] : []) + ($type !== '' ? ['type' => $type] : []);
$resp = api_cached('am_attractions_' . md5(json_encode($params)), fn () => api_get('/attractions', $params), 600);
$rows = (!empty($resp['success']) && is_array($resp['data']['items'] ?? null)) ? $resp['data']['items'] : [];
$pag = $resp['data']['pagination'] ?? ['current_page' => 1, 'last_page' => 1, 'total' => count($rows)];

$items = [];
foreach ($rows as $row) {
    $a = v2_attraction($row);
    if (!$a) {
        continue;
    }
    $n = (int) ($row['activities_count'] ?? 0);
    $items[] = [
        'href' => $a['href'],
        'image' => $a['image'],
        'kicker' => $a['type'] ?: 'Atracție',
        'title' => $a['name'],
        'meta' => array_values(array_filter([$a['city'] !== '' ? ['map-pin', $a['city']] : null])),
        'price' => null,
        'badges' => $n > 0 ? [v2_num($n, 'activitate', 'activități')] : [],
    ];
}

// A page past the last one is not a list.
if ($page > 1 && !$items) {
    http_response_code(404);
    require __DIR__ . '/404.php';
    exit;
}

$base = $citySlug !== '' ? '/' . $citySlug . '/atractii' : '/atractii';
$url = fn (string $b, string $t, int $p = 1) => $b . (($q = http_build_query(array_filter(['tip' => $t, 'pagina' => $p > 1 ? $p : null]))) !== '' ? '?' . $q : '');
$breadcrumbs = [['name' => 'Acasă', 'url' => SITE_URL . '/']];
if ($citySlug !== '') {
    $breadcrumbs[] = ['name' => $cityName, 'url' => SITE_URL . '/' . $citySlug];
}
$breadcrumbs[] = ['name' => 'Atracții', 'url' => SITE_URL . $base];

$typeChips = [['Toate', $url($base, ''), $type === '']];
foreach (AM_ATTRACTION_TYPES as $ts => $tn) {
    $typeChips[] = [$tn, $url($base, $ts), $ts === $type];
}
$cityChips = [['Toată țara', $url('/atractii', $type), $citySlug === '']];
$seen = [];
foreach (navGetCities(12) as $c) {
    $cityChips[] = [$c['label'], $url('/' . $c['slug'] . '/atractii', $type), $c['slug'] === $citySlug];
    $seen[$c['slug']] = true;
}
if ($citySlug !== '' && empty($seen[$citySlug])) {
    $cityChips[] = [$cityName, $url($base, $type), true];
}

$total = (int) ($pag['total'] ?? count($items));
$typeName = $type !== '' ? AM_ATTRACTION_TYPES[$type] : '';
$hub = [
    'kicker' => $typeName !== '' ? $typeName : 'Locuri de văzut',
    'title' => 'Atracții',
    'titleEm' => $cityName !== '' ? 'în ' . $cityName : 'din România',
    'lead' => 'Castele, muzee, mănăstiri, parcuri și priveliști. Pe fiecare pagină vezi unde se află și ce poți face în jur.',
    'stats' => array_values(array_filter([$total ? v2_num($total, 'atracție', 'atracții') : ''])),
    'image' => $items[0]['image'] ?? null,
    'breadcrumbs' => $breadcrumbs,
    'filters' => [['Tip', $typeChips], ['Oraș', $cityChips]],
    'items' => $items,
    'heading' => 'Atracții' . ($typeName !== '' ? ': ' . $typeName : '') . ($cityName !== '' ? ' în ' . $cityName : ''),
    'page' => (int) ($pag['current_page'] ?? $page),
    'last' => (int) ($pag['last_page'] ?? 1),
    'pageUrl' => fn (int $p) => $url($base, $type, $p),
    'empty' => ['Nu am găsit atracții' . ($typeName !== '' ? ' de tipul „' . $typeName . '”' : '') . ($cityName !== '' ? ' în ' . $cityName : '') . '.', 'Încearcă alt tip sau alt oraș.', ['Toate atracțiile', '/atractii']],
];

$pageTitleRaw = ($typeName !== '' ? $typeName . ': atracții' : 'Atracții') . ($cityName !== '' ? ' în ' . $cityName : ' din România') . ($page > 1 ? ' (pagina ' . $page . ')' : '') . ' | bilete.online';
$pageDescription = 'Atracții' . ($cityName !== '' ? ' în ' . $cityName : ' din România') . ($typeName !== '' ? ' (' . mb_strtolower($typeName) . ')' : '') . ': castele, muzee, mănăstiri, parcuri și priveliști, cu hartă și lucruri de făcut în apropiere.';
$canonicalUrl = SITE_URL . $url($base, $type, $page);
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
