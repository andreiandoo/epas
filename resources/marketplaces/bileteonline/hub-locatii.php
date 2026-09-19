<?php
/**
 * Locations with online tickets: /locatii and /{oras}/locatii (v2 design, includes/v2/am-hub.php).
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
        !empty($counts['access']) ? v2_num((int) $counts['access'], 'bilet', 'bilete') : '',
        !empty($counts['experience']) ? v2_exp((int) $counts['experience']) : '',
        !empty($counts['package']) ? v2_num((int) $counts['package'], 'pachet', 'pachete') : '',
    ]);
    $items[] = [
        'href' => '/locatie/' . $l['slug'],
        'image' => v2_media_url($l['cover_image'] ?? null),
        'kicker' => navFlatName($l['category']['name'] ?? '') ?: 'Locație',
        'title' => navFlatName($l['name'] ?? ''),
        'meta' => array_values(array_filter([
            !empty($l['city']['name']) ? ['map-pin', navFlatName($l['city']['name'])] : null,
            $offer ? ['ticket', implode(' · ', $offer)] : null,
        ])),
        'price' => !empty($l['min_price_cents']) ? (int) round($l['min_price_cents'] / 100) : null,
        'badges' => !empty($l['has_lodging']) ? ['Cazare'] : [],
    ];
}

// A page past the last one is not a list.
if ($page > 1 && !$items) {
    http_response_code(404);
    require __DIR__ . '/404.php';
    exit;
}

$base = $citySlug !== '' ? '/' . $citySlug . '/locatii' : '/locatii';
$breadcrumbs = [['name' => 'Acasă', 'url' => SITE_URL . '/'], ['name' => 'Locații', 'url' => SITE_URL . '/locatii']];
if ($citySlug !== '') {
    $breadcrumbs[] = ['name' => $cityName, 'url' => SITE_URL . $base];
}

$cityChips = [['Toate', '/locatii', $citySlug === '']];
foreach ($cities as $cs => [$cn]) {
    $cityChips[] = [$cn, '/' . $cs . '/locatii', $cs === $citySlug];
}
if ($citySlug !== '' && !isset($cities[$citySlug])) {
    $cityChips[] = [$cityName, $base, true];
}

$total = (int) ($pag['total'] ?? count($items));
$hub = [
    'kicker' => 'Bilete online la intrare',
    'title' => 'Locații',
    'titleEm' => $cityName !== '' ? 'în ' . $cityName : 'cu bilete online',
    'lead' => 'Rezervații naturale, parcuri, muzee și alte locuri unde îți iei online biletul de intrare și experiențele de acolo, într-o singură comandă.',
    'stats' => array_values(array_filter([
        $total ? v2_num($total, 'locație', 'locații') : '',
        $citySlug === '' && count($cities) > 1 ? v2_num(count($cities), 'oraș', 'orașe') : '',
    ])),
    'image' => $items[0]['image'] ?? null,
    'breadcrumbs' => $breadcrumbs,
    'filters' => count($cityChips) > 2 || $citySlug !== '' ? [['Oraș', $cityChips]] : [],
    'items' => $items,
    'heading' => 'Locații' . ($cityName !== '' ? ' în ' . $cityName : ''),
    'page' => (int) ($pag['current_page'] ?? $page),
    'last' => (int) ($pag['last_page'] ?? 1),
    'pageUrl' => fn (int $p) => $base . ($p > 1 ? '?pagina=' . $p : ''),
    'empty' => $citySlug !== ''
        ? ['Încă nu avem locații în ' . $cityName . '.', 'Vezi locațiile din celelalte orașe sau experiențele de aici.', ['Toate locațiile', '/locatii']]
        : ['Primele locații apar în curând.', 'Aici vei găsi rezervații, parcuri, muzee și alte locuri în care intri cu biletul luat online.', ['Ai o locație? Vinde bilete aici', '/parteneri']],
];

$pageTitleRaw = 'Locații' . ($cityName !== '' ? ' în ' . $cityName : '') . ': bilete online la intrare' . ($page > 1 ? ' (pagina ' . $page . ')' : '') . ' | bilete.online';
$pageDescription = 'Locații' . ($cityName !== '' ? ' din ' . $cityName : ' din România') . ' unde îți iei online biletul de intrare, parcarea și experiențele de acolo: rezervații naturale, parcuri, muzee.';
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
