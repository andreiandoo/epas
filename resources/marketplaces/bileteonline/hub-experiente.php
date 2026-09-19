<?php
/**
 * Experiences: /experiente and /{oras}/experiente (v2 design, includes/v2/am-hub.php).
 *
 * Reads GET /activities (?city=, ?pagina=); with the activities module that list holds only experiences sold
 * online (access tickets and packages are on their location's page). Cards open /experienta/{slug}.
 */

$pageCacheTTL = 300;
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

$params = ['per_page' => 24, 'page' => $page] + ($citySlug !== '' ? ['city' => $citySlug] : []);
$resp = api_cached('am_experiences_' . md5(json_encode($params)), fn () => api_get('/activities', $params), 300);
$rows = (!empty($resp['success']) && is_array($resp['data']['items'] ?? null)) ? $resp['data']['items'] : [];
$pag = $resp['data']['pagination'] ?? ['current_page' => 1, 'last_page' => 1, 'total' => count($rows)];

$items = [];
foreach ($rows as $row) {
    $a = v2_activity($row);
    if (!$a) {
        continue;
    }
    $items[] = [
        'href' => '/experienta/' . $a['slug'],
        'image' => $a['image'],
        'kicker' => $a['catName'] ?: 'Experiență',
        'title' => $a['title'],
        'meta' => array_values(array_filter([
            $a['rating'] > 0 ? ['star', str_replace('.', ',', (string) $a['rating']) . ($a['reviews'] > 0 ? ' (' . v2_thousands($a['reviews']) . ')' : '')] : null,
            $a['dur'] !== '' ? ['clock', $a['dur']] : null,
            $a['city'] !== '' ? ['map-pin', $a['city']] : null,
        ])),
        'price' => $a['price'] ?: null,
        'badges' => [],
    ];
}

// A page past the last one is not a list.
if ($page > 1 && !$items) {
    http_response_code(404);
    require __DIR__ . '/404.php';
    exit;
}

$base = $citySlug !== '' ? '/' . $citySlug . '/experiente' : '/experiente';
$breadcrumbs = [['name' => 'Acasă', 'url' => SITE_URL . '/'], ['name' => 'Experiențe', 'url' => SITE_URL . '/experiente']];
if ($citySlug !== '') {
    $breadcrumbs[] = ['name' => $cityName, 'url' => SITE_URL . $base];
}

$cityChips = [['Toate', '/experiente', $citySlug === '']];
$seen = [];
foreach (navGetCities(12) as $c) {
    $cityChips[] = [$c['label'], '/' . $c['slug'] . '/experiente', $c['slug'] === $citySlug];
    $seen[$c['slug']] = true;
}
if ($citySlug !== '' && empty($seen[$citySlug])) {
    $cityChips[] = [$cityName, $base, true];
}

$total = (int) ($pag['total'] ?? count($items));
$hub = [
    'kicker' => 'Cu ora aleasă, biletul pe telefon',
    'title' => 'Experiențe',
    'titleEm' => $cityName !== '' ? 'în ' . $cityName : 'de trăit în România',
    'lead' => 'Tururi ghidate, ateliere, plimbări cu barca, degustări și alte lucruri de făcut. Alegi ziua și ora, plătești online și primești biletul pe email.',
    'stats' => array_values(array_filter([$total ? v2_exp($total) : ''])),
    'image' => $items[0]['image'] ?? null,
    'breadcrumbs' => $breadcrumbs,
    'filters' => [['Oraș', $cityChips]],
    'items' => $items,
    'heading' => 'Experiențe' . ($cityName !== '' ? ' în ' . $cityName : ''),
    'page' => (int) ($pag['current_page'] ?? $page),
    'last' => (int) ($pag['last_page'] ?? 1),
    'pageUrl' => fn (int $p) => $base . ($p > 1 ? '?pagina=' . $p : ''),
    'empty' => $citySlug !== ''
        ? ['Încă nu avem experiențe în ' . $cityName . '.', 'Uită-te la experiențele din alte orașe sau la ce se întâmplă în ' . $cityName . '.', ['Ce faci în ' . $cityName, '/' . $citySlug]]
        : ['Primele experiențe apar în curând.', 'Aici vei găsi tururi, ateliere și alte lucruri de făcut, cu rezervare online.', ['Organizezi experiențe? Vinde-le aici', '/parteneri']],
];

$pageTitleRaw = 'Experiențe' . ($cityName !== '' ? ' în ' . $cityName : '') . ': rezervă online' . ($page > 1 ? ' (pagina ' . $page . ')' : '') . ' | bilete.online';
$pageDescription = 'Experiențe' . ($cityName !== '' ? ' în ' . $cityName : ' în România') . ': tururi ghidate, ateliere, plimbări și degustări. Alegi ziua și ora, plătești online, primești biletul pe email.';
$canonicalUrl = SITE_URL . $base . ($page > 1 ? '?pagina=' . $page : '');
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
