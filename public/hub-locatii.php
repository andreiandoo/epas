<?php
/**
 * Locations with online tickets: /venues and /{city}/venues (v2 design, includes/v2/am-hub.php).
 *
 * Reads GET /activities-module/locations (?city=, ?pagina=). The city chips come from every published location.
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
$resp = api_cached('am_locations_' . md5(json_encode($params)), fn () => api_get('/activities-module/locations', $params), 300);
$rows = (!empty($resp['success']) && is_array($resp['data']['items'] ?? null)) ? $resp['data']['items'] : [];
$pag = $resp['data']['pagination'] ?? ['current_page' => 1, 'last_page' => 1, 'total' => count($rows)];

// Every published location (one page of 50 is plenty for a long while) for the city chips and the stats.
$allResp = api_cached('am_locations_all', fn () => api_get('/activities-module/locations', ['per_page' => 50]), 600);
$all = (!empty($allResp['success']) && is_array($allResp['data']['items'] ?? null)) ? $allResp['data']['items'] : [];
$cities = [];
foreach ($all as $l) {
    $cs = (string) ($l['city']['slug'] ?? '');
    if ($cs !== '') {
        $cities[$cs] = [navFlatName($l['city']['name'] ?? $cs), ($cities[$cs][1] ?? 0) + 1];
    }
}
uasort($cities, fn ($a, $b) => $b[1] <=> $a[1] ?: strcmp($a[0], $b[0]));

$items = [];
foreach ($rows as $l) {
    if (empty($l['slug'])) {
        continue;
    }
    $counts = is_array($l['counts'] ?? null) ? $l['counts'] : [];
    $offer = array_filter([
        !empty($counts['access']) ? v2_num((int) $counts['access'], 'ticket', 'tickets') : '',
        !empty($counts['experience']) ? v2_exp((int) $counts['experience']) : '',
        !empty($counts['package']) ? v2_num((int) $counts['package'], 'package', 'packages') : '',
    ]);
    $items[] = [
        'href' => '/venue/' . $l['slug'],
        'image' => v2_media_url($l['cover_image'] ?? null),
        'kicker' => navFlatName($l['category']['name'] ?? '') ?: v2_t('Venue'),
        'title' => navFlatName($l['name'] ?? ''),
        'meta' => array_values(array_filter([
            !empty($l['city']['name']) ? ['map-pin', navFlatName($l['city']['name'])] : null,
            $offer ? ['ticket', implode(' · ', $offer)] : null,
        ])),
        'price' => !empty($l['min_price_cents']) ? (int) round($l['min_price_cents'] / 100) : null,
        'priceLabel' => !empty($l['min_price_cents']) ? am_lei((int) $l['min_price_cents'], $l['currency'] ?? null) : '',
        'badges' => array_values(array_filter([!empty($l['is_promoted']) ? v2_t('Promoted') : null, !empty($l['has_lodging']) ? v2_t('Accommodation') : null])),
    ];
}

// A page past the last one is not a list.
if ($page > 1 && !$items) {
    http_response_code(404);
    require __DIR__ . '/404.php';
    exit;
}

$base = $citySlug !== '' ? '/' . $citySlug . '/venues' : '/venues';
$breadcrumbs = [['name' => v2_t('Home'), 'url' => SITE_URL . '/'], ['name' => v2_t('Venues'), 'url' => SITE_URL . '/venues']];
if ($citySlug !== '') {
    $breadcrumbs[] = ['name' => $cityName, 'url' => SITE_URL . $base];
}

$cityChips = [[v2_t('All'), '/venues', $citySlug === '']];
foreach ($cities as $cs => [$cn]) {
    $cityChips[] = [$cn, '/' . $cs . '/venues', $cs === $citySlug];
}
if ($citySlug !== '' && !isset($cities[$citySlug])) {
    $cityChips[] = [$cityName, $base, true];
}

$total = (int) ($pag['total'] ?? count($items));
$hub = [
    'tight' => true,
    'kicker' => v2_t('Entry tickets online'),
    'title' => v2_t('Venues'),
    'titleHtml' => $cityName !== ''
        ? v2_t('Venues <em>in {city}</em>', ['city' => v2_e($cityName)])
        : v2_t('Venues <em>with online tickets</em>'),
    'lead' => v2_t('Nature reserves, parks, museums and other places where you buy your entry ticket and the experiences there online, in a single order.'),
    'stats' => array_values(array_filter([
        $total ? v2_num($total, 'venue', 'venues') : '',
        $citySlug === '' && count($cities) > 1 ? v2_num(count($cities), 'city', 'cities') : '',
    ])),
    'image' => $items[0]['image'] ?? null,
    'breadcrumbs' => $breadcrumbs,
    'filters' => count($cityChips) > 2 || $citySlug !== '' ? [[v2_t('City'), $cityChips]] : [],
    'items' => $items,
    'heading' => $cityName !== '' ? v2_t('Venues in {city}', ['city' => $cityName]) : v2_t('Venues'),
    'page' => (int) ($pag['current_page'] ?? $page),
    'last' => (int) ($pag['last_page'] ?? 1),
    'pageUrl' => fn (int $p) => $base . ($p > 1 ? '?pagina=' . $p : ''),
    'empty' => $citySlug !== ''
        ? [v2_t('We have no venues in {city} yet.', ['city' => $cityName]), v2_t('See the venues in other cities, or the experiences here.'), [v2_t('All venues'), '/venues']]
        : [v2_t('The first venues are coming soon.'), v2_t('This is where you will find reserves, parks, museums and other places you enter with a ticket bought online.'), [v2_t('Do you run a venue? Sell tickets here'), '/partners']],
];

$pageTitleRaw = $cityName !== '' ? v2_t('Venues in {city}: entry tickets online', ['city' => $cityName]) : v2_t('Venues: entry tickets online');
if ($page > 1) {
    $pageTitleRaw = v2_t('{title} (page {n})', ['title' => $pageTitleRaw, 'n' => $page]);
}
$pageTitleRaw .= ' | Viaqui';
$pageDescription = $cityName !== ''
    ? v2_t('Venues in {city} where you buy your entry ticket, parking and the experiences there online: nature reserves, parks, museums.', ['city' => $cityName])
    : v2_t('Venues across Europe where you buy your entry ticket, parking and the experiences there online: nature reserves, parks, museums.');
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
