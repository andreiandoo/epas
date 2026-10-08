<?php
/**
 * Experiences: /experiences and /{city}/experiences (v2 design, includes/v2/am-hub.php).
 *
 * Reads GET /activities (?city=, ?search=, ?category=, ?date=, ?max_price_ron=, ?sort=, ?pagina=); with the
 * activities module that list holds only experiences sold online (access tickets and packages are on their
 * location's page). Cards open /experience/{slug}.
 *
 * The city lives in the path, so the filter's City field submits ?oras= and this file sends the browser on to
 * the canonical /{city}/experiences. The query names (oras, categorie, data, pret, pagina) and the sort values are
 * the ones the page has always used: they are addresses, not texts. That happens before the page cache, so no redirect is ever cached.
 */

if (isset($_GET['oras'])) {
    $boTo = (string) $_GET['oras'];
    $boRest = $_GET;
    unset($boRest['oras'], $boRest['city'], $boRest['pagina']);
    $boQs = http_build_query(array_filter($boRest, fn ($v) => is_string($v) && $v !== ''));
    header('Location: ' . (preg_match('/^[a-z][a-z0-9-]{1,50}$/', $boTo) ? '/' . $boTo . '/experiences' : '/experiences')
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
$sorts = ['' => v2_t('Recommended'), 'ieftin' => v2_t('Cheapest first'), 'curand' => v2_t('Starting soon')];
$sort = (string) ($_GET['sort'] ?? '');
if (!isset($sorts[$sort])) {
    $sort = '';
}
$hasFilter = $q !== '' || $cat !== '' || $date !== '' || $maxPrice > 0 || $citySlug !== '';

$base = $citySlug !== '' ? '/' . $citySlug . '/experiences' : '/experiences';
/** The same list with some of the filters swapped; null clears one. Page numbers never carry over. */
$url = function (array $over = [], ?string $forCity = null) use ($base, $citySlug, $q, $cat, $date, $maxPrice, $sort) {
    $args = array_merge(['q' => $q, 'categorie' => $cat, 'data' => $date, 'pret' => $maxPrice ?: '', 'sort' => $sort], $over);
    $path = $forCity === null ? $base : ($forCity !== '' ? '/' . $forCity . '/experiences' : '/experiences');
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
    // Where it happens comes first: "Rowing boat hire" only means something once you know it is at Lake Bled,
    // in Bled. Without a location on the product we fall back to the city. One sentence per case, so a language
    // can order it its own way; the place is in <b>.
    if ($a['loc'] !== '' && $a['locCity'] !== '') {
        $where = v2_t('at <b>{place}</b>, in {city}', ['place' => v2_e($a['loc']), 'city' => v2_e($a['locCity'])]);
        $aria = v2_t('{title} at {place}, in {city}', ['title' => $a['title'], 'place' => $a['loc'], 'city' => $a['locCity']]);
    } elseif ($a['loc'] !== '') {
        $where = v2_t('at <b>{place}</b>', ['place' => v2_e($a['loc'])]);
        $aria = v2_t('{title} at {place}', ['title' => $a['title'], 'place' => $a['loc']]);
    } elseif ($a['city'] !== '') {
        $where = v2_t('in <b>{city}</b>', ['city' => v2_e($a['city'])]);
        $aria = v2_t('{title} in {city}', ['title' => $a['title'], 'city' => $a['city']]);
    } else {
        $where = null;
        $aria = $a['title'];
    }
    $items[] = [
        'href' => '/experience/' . $a['slug'],
        'image' => $a['image'],
        'kicker' => $a['catName'] ?: v2_t('Experience'),
        'title' => $a['title'],
        'where' => $where,
        'aria' => $aria,
        'meta' => array_values(array_filter([
            $a['rating'] > 0 ? ['star', (string) $a['rating'] . ($a['reviews'] > 0 ? ' (' . v2_thousands($a['reviews']) . ')' : '')] : null,
            $a['dur'] !== '' ? ['clock', $a['dur']] : null,
            $a['catName'] !== '' ? ['tag', $a['catName']] : null,
        ])),
        'price' => $a['price'] ?: null,
        'priceLabel' => $a['priceLabel'],   // in the operator's own currency (or the one the visitor chose)
        'badges' => $a['promoted'] ? [v2_t('Promoted')] : [],
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

$cityOptions = [['', v2_t('Anywhere')]];
foreach ($facetCities as $cs => [$cn, $cc]) {
    $cityOptions[] = [$cs, $cn . ($facetExact && $cc > 0 ? ' (' . $cc . ')' : '')];
}
$catChips = [[v2_t('All'), $url(['categorie' => '']), $cat === '']];
foreach ($facetCats as $ks => [$kn]) {
    $catChips[] = [$kn, $url(['categorie' => $ks]), $ks === $cat];
}

$active = [];
if ($citySlug !== '') {
    $active[] = [$cityName, $url([], '')];
}
if ($q !== '') {
    $active[] = ['“' . $q . '”', $url(['q' => ''])];
}
if ($cat !== '') {
    $active[] = [$facetCats[$cat][0] ?? $cat, $url(['categorie' => ''])];
}
if ($date !== '') {
    $active[] = [am_date($date), $url(['data' => ''])];
}
if ($maxPrice > 0) {
    $active[] = [v2_t('up to {price}', ['price' => v2_money($maxPrice)]), $url(['pret' => ''])];
}

$total = (int) ($pag['total'] ?? count($items));
if ($total <= 0) {
    $countLine = v2_t('No results for these filters');
} elseif (!$hasFilter) {
    $countLine = v2_t('{count} on Viaqui', ['count' => v2_exp($total)]);
} elseif ($citySlug !== '' && count($active) === 1) {
    $countLine = v2_t('{count} in {city}', ['count' => v2_exp($total), 'city' => $cityName]);
} else {
    $countLine = v2_t('{count} matching your filters', ['count' => v2_exp($total)]);
}

$priceOptions = [['', v2_t('Any price')]];
foreach ($priceSteps as $p) {
    $priceOptions[] = [(string) $p, v2_t('up to {price}', ['price' => v2_money($p)])];
}
$sortOptions = [];
foreach ($sorts as $sv => $sl) {
    $sortOptions[] = [$sv, $sl];
}

$breadcrumbs = [['name' => v2_t('Home'), 'url' => SITE_URL . '/'], ['name' => v2_t('Experiences'), 'url' => SITE_URL . '/experiences']];
if ($citySlug !== '') {
    $breadcrumbs[] = ['name' => $cityName, 'url' => SITE_URL . $base];
}

$hub = [
    'tight' => true,
    'kicker' => v2_t('Pick the day and time, the ticket arrives by email'),
    'title' => v2_t('Experiences'),
    'titleHtml' => $cityName !== ''
        ? v2_t('Experiences <em>in {city}</em>', ['city' => v2_e($cityName)])
        : v2_t('Experiences <em>across Europe</em>'),
    'lead' => v2_t('Guided tours, workshops, boat trips, tastings and other things to do, at the venues that run them.'),
    'stats' => array_values(array_filter([$total ? v2_exp($total) : ''])),
    'image' => $items[0]['image'] ?? null,
    'breadcrumbs' => $breadcrumbs,
    'filter' => [
        'action' => $base,
        'search' => ['name' => 'q', 'value' => $q, 'placeholder' => v2_t('Search for an experience'), 'clear' => $url(['q' => ''])],
        'fields' => [
            ['name' => 'oras', 'label' => v2_t('City'), 'value' => $citySlug, 'options' => $cityOptions, 'find' => v2_t('Search for a city')],
            ['name' => 'data', 'label' => v2_t('Available on'), 'value' => $date, 'type' => 'date', 'min' => $today],
            ['name' => 'pret', 'label' => v2_t('Maximum price'), 'value' => $maxPrice ?: '', 'options' => $priceOptions],
            ['name' => 'sort', 'label' => v2_t('Sort by'), 'value' => $sort, 'options' => $sortOptions],
        ],
        'chips' => count($catChips) > 2 ? ['label' => v2_t('Category'), 'items' => $catChips] : null,
        'hidden' => ['categorie' => $cat],
        'active' => $active,
        'reset' => $hasFilter ? '/experiences' : null,
        'count' => $countLine,
    ],
    'items' => $items,
    'heading' => $cityName !== '' ? v2_t('Experiences in {city}', ['city' => $cityName]) : v2_t('Experiences'),
    'page' => (int) ($pag['current_page'] ?? $page),
    'last' => (int) ($pag['last_page'] ?? 1),
    'pageUrl' => fn (int $p) => $url(['pagina' => $p > 1 ? $p : '']),
    'empty' => $hasFilter && ($q !== '' || $cat !== '' || $date !== '' || $maxPrice > 0)
        ? [v2_t('No experiences for these filters.'), v2_t('Try without some of them, or search everywhere.'), [v2_t('All experiences'), '/experiences']]
        : ($citySlug !== ''
            ? [v2_t('We have no experiences in {city} yet.', ['city' => $cityName]), v2_t('Look at experiences in other cities, or at what else there is in {city}.', ['city' => $cityName]), [v2_t('Things to do in {city}', ['city' => $cityName]), '/' . $citySlug]]
            : [v2_t('The first experiences are coming soon.'), v2_t('This is where you will find tours, workshops and other things to do, with online booking.'), [v2_t('Do you run experiences? Sell them here'), '/partners']]),
];

$pageTitleRaw = $cityName !== '' ? v2_t('Experiences in {city}: book online', ['city' => $cityName]) : v2_t('Experiences: book online');
if ($page > 1) {
    $pageTitleRaw = v2_t('{title} (page {n})', ['title' => $pageTitleRaw, 'n' => $page]);
}
$pageTitleRaw .= ' | Viaqui';
$pageDescription = $cityName !== ''
    ? v2_t('Experiences in {city}: guided tours, workshops, walks and tastings. Pick the day and time, pay online, get your ticket by email.', ['city' => $cityName])
    : v2_t('Experiences across Europe: guided tours, workshops, walks and tastings. Pick the day and time, pay online, get your ticket by email.');
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
