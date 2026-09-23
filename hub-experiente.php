<?php
/**
 * Experiences: /experiente and /{oras}/experiente (v2 design, includes/v2/am-hub.php).
 *
 * Reads GET /activities (?city=, ?search=, ?category=, ?date=, ?max_price_ron=, ?sort=, ?pagina=); with the
 * activities module that list holds only experiences sold online (access tickets and packages are on their
 * location's page). Cards open /experienta/{slug}.
 *
 * The city lives in the path, so the filter's Oraș field submits ?oras= and this file sends the browser on to
 * the canonical /{oras}/experiente. That happens before the page cache, so no redirect is ever cached.
 */

if (isset($_GET['oras'])) {
    $boTo = (string) $_GET['oras'];
    $boRest = $_GET;
    unset($boRest['oras'], $boRest['city'], $boRest['pagina']);
    $boQs = http_build_query(array_filter($boRest, fn ($v) => is_string($v) && $v !== ''));
    header('Location: ' . (preg_match('/^[a-z][a-z0-9-]{1,50}$/', $boTo) ? '/' . $boTo . '/experiente' : '/experiente')
        . ($boQs !== '' ? '?' . $boQs : ''), true, 302);
    exit;
}

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

// ---------------------------------------------------------------- what the visitor asked for
$q = trim((string) ($_GET['q'] ?? ''));
if (mb_strlen($q) > 80) {
    $q = mb_substr($q, 0, 80);
}
$cat = (string) ($_GET['categorie'] ?? '');
if (!preg_match('/^[a-z0-9-]{0,60}$/', $cat)) {
    $cat = '';
}
$date = (string) ($_GET['data'] ?? '');
$today = date('Y-m-d');
if ($date !== '' && (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) || $date < $today)) {
    $date = '';
}
$maxPrice = (int) ($_GET['pret'] ?? 0);
$priceSteps = [50, 100, 150, 250, 500];
if (!in_array($maxPrice, $priceSteps, true)) {
    $maxPrice = 0;
}
$sorts = ['' => 'Recomandate', 'ieftin' => 'Cele mai ieftine', 'curand' => 'Începe curând'];
$sort = (string) ($_GET['sort'] ?? '');
if (!isset($sorts[$sort])) {
    $sort = '';
}
$hasFilter = $q !== '' || $cat !== '' || $date !== '' || $maxPrice > 0 || $citySlug !== '';

$base = $citySlug !== '' ? '/' . $citySlug . '/experiente' : '/experiente';
/** The same list with some of the filters swapped; null clears one. Page numbers never carry over. */
$url = function (array $over = [], ?string $forCity = null) use ($base, $citySlug, $q, $cat, $date, $maxPrice, $sort) {
    $args = array_merge(['q' => $q, 'categorie' => $cat, 'data' => $date, 'pret' => $maxPrice ?: '', 'sort' => $sort], $over);
    $path = $forCity === null ? $base : ($forCity !== '' ? '/' . $forCity . '/experiente' : '/experiente');
    $qs = http_build_query(array_filter($args, fn ($v) => $v !== '' && $v !== null));

    return $path . ($qs !== '' ? '?' . $qs : '');
};

$params = ['per_page' => 24, 'page' => $page]
    + ($citySlug !== '' ? ['city' => $citySlug] : [])
    + ($q !== '' ? ['search' => $q] : [])
    + ($cat !== '' ? ['category' => $cat] : [])
    + ($date !== '' ? ['date' => $date] : [])
    + ($maxPrice > 0 ? ['max_price_ron' => $maxPrice] : [])
    + ($sort === 'ieftin' ? ['sort' => 'cheapest'] : ($sort === 'curand' ? ['sort' => 'soon'] : []));
$resp = api_cached('am_experiences_' . md5(json_encode($params)), fn () => api_get('/activities', $params), 300);
$rows = (!empty($resp['success']) && is_array($resp['data']['items'] ?? null)) ? $resp['data']['items'] : [];
$pag = $resp['data']['pagination'] ?? ['current_page' => 1, 'last_page' => 1, 'total' => count($rows)];

$items = [];
foreach ($rows as $row) {
    $a = v2_activity($row);
    if (!$a) {
        continue;
    }
    // Where it happens comes first: "Închiriere barcă cu vâsle" only means something once you know it is at
    // Parcul Bucov, în Ploiești. Without a location on the product we fall back to the city.
    $where = $a['loc'] !== ''
        ? ['la', $a['loc'], $a['locCity'] !== '' ? ', în ' . $a['locCity'] : '']
        : ($a['city'] !== '' ? ['în', $a['city'], ''] : null);
    $aria = $a['title'] . ($a['loc'] !== '' ? ' la ' . $a['loc'] . ($a['locCity'] !== '' ? ', în ' . $a['locCity'] : '') : ($a['city'] !== '' ? ' în ' . $a['city'] : ''));
    $items[] = [
        'href' => '/experienta/' . $a['slug'],
        'image' => $a['image'],
        'kicker' => $a['catName'] ?: 'Experiență',
        'title' => $a['title'],
        'where' => $where,
        'aria' => $aria,
        'meta' => array_values(array_filter([
            $a['rating'] > 0 ? ['star', str_replace('.', ',', (string) $a['rating']) . ($a['reviews'] > 0 ? ' (' . v2_thousands($a['reviews']) . ')' : '')] : null,
            $a['dur'] !== '' ? ['clock', $a['dur']] : null,
            $a['catName'] !== '' ? ['tag', $a['catName']] : null,
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

// ---------------------------------------------------------------- what the filter can offer
// The cities and categories that actually hold experiences, read off one unfiltered page of the catalogue.
// Offering all 400 marketplace cities when nine of them have anything would just be a list of dead ends.
$facetResp = api_cached('am_experiences_facets', fn () => api_get('/activities', ['per_page' => 50]), 600);
$facetRows = (!empty($facetResp['success']) && is_array($facetResp['data']['items'] ?? null)) ? $facetResp['data']['items'] : [];
$facetTotal = (int) ($facetResp['data']['pagination']['total'] ?? count($facetRows));
$facetExact = $facetTotal <= count($facetRows);       // counts are honest only while the sample is the whole catalogue
$facetCities = [];
$facetCats = [];
foreach ($facetRows as $row) {
    $cs = (string) ($row['city']['slug'] ?? '');
    if ($cs !== '') {
        $facetCities[$cs] = [navFlatName($row['city']['name'] ?? $cs), ($facetCities[$cs][1] ?? 0) + 1];
    }
    $ks = (string) ($row['category']['slug'] ?? '');
    if ($ks !== '') {
        $facetCats[$ks] = [navFlatName($row['category']['name'] ?? $ks), ($facetCats[$ks][1] ?? 0) + 1];
    }
}
uasort($facetCities, fn ($a, $b) => $b[1] <=> $a[1] ?: strcoll($a[0], $b[0]));
uasort($facetCats, fn ($a, $b) => $b[1] <=> $a[1] ?: strcoll($a[0], $b[0]));
if ($citySlug !== '' && !isset($facetCities[$citySlug])) {
    $facetCities[$citySlug] = [$cityName, 0];
}

$cityOptions = [['', 'Toată țara']];
foreach ($facetCities as $cs => [$cn, $cc]) {
    $cityOptions[] = [$cs, $cn . ($facetExact && $cc > 0 ? ' (' . $cc . ')' : '')];
}
$catChips = [['Toate', $url(['categorie' => '']), $cat === '']];
foreach ($facetCats as $ks => [$kn]) {
    $catChips[] = [$kn, $url(['categorie' => $ks]), $ks === $cat];
}

$active = [];
if ($citySlug !== '') {
    $active[] = [$cityName, $url([], '')];
}
if ($q !== '') {
    $active[] = ['„' . $q . '”', $url(['q' => ''])];
}
if ($cat !== '') {
    $active[] = [$facetCats[$cat][0] ?? $cat, $url(['categorie' => ''])];
}
if ($date !== '') {
    $active[] = [am_date($date), $url(['data' => ''])];
}
if ($maxPrice > 0) {
    $active[] = ['până în ' . $maxPrice . ' lei', $url(['pret' => ''])];
}

$total = (int) ($pag['total'] ?? count($items));
$countLine = $total > 0
    ? v2_exp($total) . ($hasFilter ? ($citySlug !== '' && count($active) === 1 ? ' în ' . $cityName : ', după filtrele tale') : ' pe bilete.online')
    : 'Niciun rezultat pentru filtrele alese';

$priceOptions = [['', 'Oricât']];
foreach ($priceSteps as $p) {
    $priceOptions[] = [(string) $p, 'până în ' . $p . ' lei'];
}
$sortOptions = [];
foreach ($sorts as $sv => $sl) {
    $sortOptions[] = [$sv, $sl];
}

$breadcrumbs = [['name' => 'Acasă', 'url' => SITE_URL . '/'], ['name' => 'Experiențe', 'url' => SITE_URL . '/experiente']];
if ($citySlug !== '') {
    $breadcrumbs[] = ['name' => $cityName, 'url' => SITE_URL . $base];
}

$hub = [
    'tight' => true,
    'kicker' => 'Alegi ziua și ora, biletul vine pe email',
    'title' => 'Experiențe',
    'titleEm' => $cityName !== '' ? 'în ' . $cityName : 'de trăit în România',
    'lead' => 'Tururi ghidate, ateliere, plimbări cu barca, degustări și alte lucruri de făcut, la locațiile care le organizează.',
    'stats' => array_values(array_filter([$total ? v2_exp($total) : ''])),
    'image' => $items[0]['image'] ?? null,
    'breadcrumbs' => $breadcrumbs,
    'filter' => [
        'action' => $base,
        'search' => ['name' => 'q', 'value' => $q, 'placeholder' => 'Caută o experiență', 'clear' => $url(['q' => ''])],
        'fields' => [
            ['name' => 'oras', 'label' => 'Oraș', 'value' => $citySlug, 'options' => $cityOptions, 'find' => 'Caută orașul'],
            ['name' => 'data', 'label' => 'Disponibil pe', 'value' => $date, 'type' => 'date', 'min' => $today],
            ['name' => 'pret', 'label' => 'Preț maxim', 'value' => $maxPrice ?: '', 'options' => $priceOptions],
            ['name' => 'sort', 'label' => 'Sortare', 'value' => $sort, 'options' => $sortOptions],
        ],
        'chips' => count($catChips) > 2 ? ['label' => 'Categorie', 'items' => $catChips] : null,
        'hidden' => ['categorie' => $cat],
        'active' => $active,
        'reset' => $hasFilter ? '/experiente' : null,
        'count' => $countLine,
    ],
    'items' => $items,
    'heading' => 'Experiențe' . ($cityName !== '' ? ' în ' . $cityName : ''),
    'page' => (int) ($pag['current_page'] ?? $page),
    'last' => (int) ($pag['last_page'] ?? 1),
    'pageUrl' => fn (int $p) => $url(['pagina' => $p > 1 ? $p : '']),
    'empty' => $hasFilter && ($q !== '' || $cat !== '' || $date !== '' || $maxPrice > 0)
        ? ['Nicio experiență pentru filtrele alese.', 'Încearcă fără o parte dintre ele sau caută în toată țara.', ['Toate experiențele', '/experiente']]
        : ($citySlug !== ''
            ? ['Încă nu avem experiențe în ' . $cityName . '.', 'Uită-te la experiențele din alte orașe sau la ce se întâmplă în ' . $cityName . '.', ['Ce faci în ' . $cityName, '/' . $citySlug]]
            : ['Primele experiențe apar în curând.', 'Aici vei găsi tururi, ateliere și alte lucruri de făcut, cu rezervare online.', ['Organizezi experiențe? Vinde-le aici', '/parteneri']]),
];

$pageTitleRaw = 'Experiențe' . ($cityName !== '' ? ' în ' . $cityName : '') . ': rezervă online' . ($page > 1 ? ' (pagina ' . $page . ')' : '') . ' | bilete.online';
$pageDescription = 'Experiențe' . ($cityName !== '' ? ' în ' . $cityName : ' în România') . ': tururi ghidate, ateliere, plimbări și degustări. Alegi ziua și ora, plătești online, primești biletul pe email.';
$canonicalUrl = SITE_URL . $base . ($page > 1 ? '?pagina=' . $page : '');
// A filtered list is a slice of the plain one: it stays out of the index and points at the canonical page.
if ($q !== '' || $cat !== '' || $date !== '' || $maxPrice > 0 || $sort !== '') {
    $noindex = true;
}
$ogImage = $hub['image'] ?: (SITE_URL . '/assets/images/og-default.jpg');
$structuredData = [[
    '@context' => 'https://schema.org',
    '@type' => 'ItemList',
    'name' => $hub['heading'],
    'numberOfItems' => $total,
    'itemListElement' => array_map(fn ($it, $i) => ['@type' => 'ListItem', 'position' => ($page - 1) * 24 + $i + 1, 'url' => SITE_URL . $it['href'], 'name' => $it['aria'] ?: $it['title']], $items, array_keys($items)),
], [
    '@context' => 'https://schema.org',
    '@type' => 'BreadcrumbList',
    'itemListElement' => array_map(fn ($bc, $i) => ['@type' => 'ListItem', 'position' => $i + 1, 'name' => $bc['name'], 'item' => $bc['url']], $breadcrumbs, array_keys($breadcrumbs)),
]];

require __DIR__ . '/includes/v2/am-hub.php';
