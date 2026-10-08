<?php
/**
 * Single city landing — /{city-slug} (v2 design).
 *
 * Pure render: expects $_GET['slug'] (or `$slug` already set by the
 * slug.php dispatcher with `$cityData` pre-fetched).
 *
 * Top to bottom: hero (search, popular categories, photo gallery, rotating category cards), in-page nav,
 * GetYourGuide widget (right after the nav when the city has no listings of its own, otherwise after them),
 * activities and events with filters, interests and traveller styles, attractions, guides, nearby cities,
 * local guide (city text, local searches, intents, FAQ, city facts, other cities, owner invitation), newsletter.
 */

$pageCacheTTL = 300;
require_once __DIR__ . '/includes/page-cache.php';
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/api.php';
require_once __DIR__ . '/includes/nav-helpers.php';

$slug = $slug ?? ($_GET['slug'] ?? '');

if (!is_string($slug) || !preg_match('/^[a-z][a-z0-9-]+$/', $slug)) {
    http_response_code(404);
    require __DIR__ . '/404.php';
    exit;
}

// Use pre-fetched data if dispatched via slug.php; else fetch now.
$cityData = $cityData ?? navGetCityBySlug($slug);

if (!$cityData) {
    http_response_code(404);
    require __DIR__ . '/404.php';
    exit;
}

require_once __DIR__ . '/includes/v2/helpers.php';
require_once __DIR__ . '/includes/v2/nav.php';
require_once __DIR__ . '/includes/v2/promoted.php';
require_once __DIR__ . '/includes/v2/partners.php';

// ============================================================
// City data extraction
// ============================================================
// The full API response (city + affiliates) feeds the GetYourGuide widget without a second
// network call: both helpers share the same upstream cache key.
$cityResponse  = navGetCityResponseBySlug($slug);
$gygCityId     = trim((string) ($cityData['getyourguide_city_id'] ?? ''));
$gygPartnerId  = trim((string) ($cityResponse['affiliates']['getyourguide_partner_id'] ?? ''));
$gygWidgetEnabled = $gygCityId !== '' && $gygPartnerId !== '';

$cityName = navFlatName($cityData['name'] ?? '');
$cityDescription = navFlatName($cityData['description'] ?? '');
$citySeoTitle = navFlatName($cityData['seo_body_title'] ?? '');
$citySeoHtml = navFlatName($cityData['seo_body'] ?? '');
$cityFaqs = $cityData['faqs'] ?? [];
if (!is_array($cityFaqs)) $cityFaqs = [];
$cityFaqs = array_values(array_filter($cityFaqs, fn ($f) => !empty($f['q']) && !empty($f['a'])));
$cityCover = $cityData['cover_image'] ?? $cityData['cover_image_url'] ?? null;
$cityImage = $cityData['image'] ?? $cityData['image_url'] ?? null;
$countyName = is_array($cityData['county'] ?? null)
    ? navFlatName($cityData['county']['name'] ?? '')
    : (is_string($cityData['county'] ?? null) ? $cityData['county'] : '');
$regionName = is_array($cityData['region'] ?? null)
    ? navFlatName($cityData['region']['name'] ?? '')
    : (is_string($cityData['region'] ?? null) ? $cityData['region'] : '');
$eventCount = (int) ($cityData['events_count'] ?? 0);

// Country: the API sends its ISO code; the name and the page come from the shell's list of countries.
$countryCode = strtoupper((string) ($cityData['country'] ?? ''));
$countryName = '';
$countrySlug = '';
foreach (($V2NAV['countriesFull'] ?? []) as $v2Country) {
    if ($countryCode !== '' && ($v2Country['code'] ?? '') === $countryCode) {
        $countryName = (string) $v2Country['name'];
        $countrySlug = (string) $v2Country['slug'];
        break;
    }
}
if ($countryName === '' && isset($V2NAV['cities'][$slug])) {   // one of the large cities the shell already knows
    $countryName = (string) $V2NAV['cities'][$slug]['region'];
    foreach (($V2NAV['countriesFull'] ?? []) as $v2Country) {
        if ($v2Country['name'] === $countryName) {
            $countrySlug = (string) $v2Country['slug'];
            $countryCode = (string) ($v2Country['code'] ?? '');
        }
    }
}

// Pagination + filter parsing
$pageNum = max(1, (int) ($_GET['page'] ?? 1));
$categoryFilter = isset($_GET['category']) && is_string($_GET['category']) && preg_match('/^[a-z][a-z0-9-]+$/', $_GET['category']) ? $_GET['category'] : null;
$searchQuery = isset($_GET['q']) && is_string($_GET['q']) ? mb_substr(trim($_GET['q']), 0, 80) : '';

// Price filter, in euro (the site currency). With an empty list the control is not printed.
$priceMaxAllowed = [10, 25, 50, 100];
$maxPrice = (isset($_GET['max_price']) && in_array((int) $_GET['max_price'], $priceMaxAllowed, true))
    ? (int) $_GET['max_price']
    : null;

// Sort — whitelisted to API-supported values
$sortOptions = ['recommended' => v2_t('Recommended'), 'price_asc' => v2_t('Price: low to high'), 'price_desc' => v2_t('Price: high to low'), 'name_asc' => v2_t('A to Z')];
$sort = (isset($_GET['sort']) && is_string($_GET['sort']) && isset($sortOptions[$_GET['sort']])) ? $_GET['sort'] : 'recommended';

// Parent categories for the filters, popular chips, rotating hero cards and the interest grid (max 12).
$topCategories = array_slice($V2NAV['categories'], 0, 12);
$catNameOf = function (string $catSlug) use ($V2NAV): string {
    return $V2NAV['categoryBySlug'][$catSlug]['name'] ?? ucwords(str_replace('-', ' ', $catSlug));
};

// Activities, attractions, locations and the paid "Populare în …" block are INDEPENDENT upstream calls, so they run
// CONCURRENTLY (curl_multi via api_cached_many): one round-trip of wall-time on a cold cache. viaqui.com has no
// events.

$actParams = ['city' => $slug, 'page' => $pageNum, 'per_page' => 24];
if ($categoryFilter) $actParams['category'] = $categoryFilter;
if ($searchQuery !== '') $actParams['search'] = $searchQuery;
// operators sell in their own currency: the API filters and sorts on the euro value of the price
if ($maxPrice !== null) $actParams['max_price_eur'] = $maxPrice;
if ($sort === 'price_asc') $actParams['sort'] = 'cheapest_eur';

$cacheSuffix = ($categoryFilter ?? 'all') . '_' . md5($searchQuery) . "_mp{$maxPrice}_s{$sort}_p{$pageNum}";
$listings = api_cached_many([
    'activities' => [
        'key'      => "city_activities_{$slug}_{$cacheSuffix}",
        'endpoint' => '/activities',
        'params'   => $actParams,
        'ttl'      => 300,
    ],
    'attractions' => [
        'key'      => "v2_city_attractions_{$slug}",
        'endpoint' => '/attractions',
        'params'   => ['city' => $slug, 'per_page' => 8],
        'ttl'      => 300,
    ],
    // Locations with online tickets (activities module); empty where the module is off
    'locations' => [
        'key'      => "v2_city_locations_{$slug}",
        'endpoint' => '/activities-module/locations',
        'params'   => ['city' => $slug, 'per_page' => 8],
        'ttl'      => 300,
    ],
    'promoted' => v2_promoted_job('city', ['city' => $slug]),
]);
$promoted = v2_promoted_items($listings['promoted'] ?? null);

$actResp = $listings['activities'] ?? ['data' => []];
$activities = $actResp['data']['items'] ?? [];
if (!is_array($activities)) $activities = [];
$actPagination = $actResp['data']['pagination'] ?? ['last_page' => 1, 'total' => count($activities)];

// Activity cards, in the API order (paid promotions for this city first).
$cards = [];
foreach ($activities as $a) {
    if (!is_array($a) || !($n = v2_activity($a))) {
        continue;
    }
    $cards[] = [
        'title'       => $n['title'],
        'cat'         => $n['catName'],
        'image'       => $n['image'],
        'dur'         => $n['dur'],
        'price_cents' => isset($a['cheapest_price_cents']) ? (int) $a['cheapest_price_cents'] : null,
        'currency'    => (string) ($a['currency'] ?? ''),
        'eur_cents'   => $a['cheapest_price_eur_cents'] ?? null,
        'url'         => $n['href'],
        'cta'         => v2_t('See the experience'),
        'promoted'    => !empty($a['flags']['is_promoted']),
    ];
}

$pagination = [
    'current_page' => $pageNum,
    'last_page'    => max(1, (int) ($actPagination['last_page'] ?? 1)),
    'total'        => (int) ($actPagination['total'] ?? count($activities)),
];

// Partner products (WeGoTrip: self-guided audio tours, many with the entry ticket) join the same listing, after
// our own: the same category, search, price and sort filters apply to them, and the pages run on through both.
// Page n holds what is left of our own listings for that page, then partner products up to 24.
$perPage = 24;
$ownTotal = $pagination['total'];
$partnerAll = v2_partner_filter(v2_wegotrip_city_all((string) $slug), $categoryFilter, $searchQuery, $maxPrice, $sort);
$partnerOnPage = array_slice($partnerAll, max(0, ($pageNum - 1) * $perPage - $ownTotal), max(0, $perPage - count($cards)));
foreach ($partnerOnPage as $pp) {
    $cards[] = ['title' => $pp['title'], 'url' => '', 'partner' => $pp];
}
$pagination['total'] = $ownTotal + count($partnerAll);
$pagination['last_page'] = max(1, (int) ceil($pagination['total'] / $perPage));

// Locations with online tickets in this city (access tickets, experiences, packages). Section hidden when none.
$cityLocations = [];
foreach ((array) (($listings['locations']['success'] ?? false) ? ($listings['locations']['data']['items'] ?? []) : []) as $l) {
    if (!is_array($l) || empty($l['slug']) || navFlatName($l['name'] ?? '') === '') {
        continue;
    }
    $cityLocations[] = [
        'href' => '/venue/' . $l['slug'],
        'name' => navFlatName($l['name']),
        'image' => v2_media_url($l['cover_image'] ?? null),
        'meta' => trim(navFlatName($l['category']['name'] ?? '') . (!empty($l['min_price_cents']) ? ' · ' . v2_t('from {price}', ['price' => v2_own_price_label($l['min_price_cents'], $l['currency'] ?? null)]) : ''), ' ·'),
        'lodging' => !empty($l['has_lodging']),
        'promoted' => !empty($l['is_promoted']),
    ];
}

// Attractions in this city (points of interest). Section hidden when none.
$atData = $listings['attractions']['data'] ?? [];
$atRaw = $atData['items'] ?? $atData['data'] ?? (is_array($atData) ? $atData : []);
$cityAttractions = [];
foreach ((is_array($atRaw) ? $atRaw : []) as $at) {
    if (!is_array($at) || !($n = v2_attraction($at))) {
        continue;
    }
    $n['count'] = (int) ($at['activities_count'] ?? 0);
    $cityAttractions[] = $n;
    if (count($cityAttractions) >= 8) break;
}

// GetYourGuide affiliate widget (activities grid). Rendered ONCE — right after the section nav when
// this city has no own activities/events (so GYG fills the page) or as the lower "extra" section when
// we do have our own content. city.js loads the SDK only when the widget nears the viewport.
$gygPromote = $gygWidgetEnabled && empty($cards);
$renderGygSection = function () use ($cityName, $slug, $gygCityId, $gygPartnerId, $gygPromote) {
    $gygUrl = 'https://www.getyourguide.com/' . rawurlencode($slug) . '-l' . rawurlencode($gygCityId) . '/';
    ?>
  <section class="sec gyg" id="getyourguide" aria-labelledby="gyg-h">
    <div class="wrap">
      <div class="gyg-head">
        <p class="kicker"><?= $gygPromote ? v2_te('Activities and tours') : v2_te('More, through our partners') ?></p>
        <h2 id="gyg-h"><?= $gygPromote ? v2_te('Activities and tours in {city}', ['city' => $cityName]) : v2_te('More activities and tours in {city}', ['city' => $cityName]) ?></h2>
        <p><?= v2_te('A selection of guided tours, experiences and activities available through GetYourGuide.') ?></p>
      </div>
      <div id="gyg-mount"
        data-gyg-href="https://widget.getyourguide.com/default/activities.frame"
        data-gyg-location-id="<?= v2_e($gygCityId) ?>"
        data-gyg-locale-code="en-US"
        data-gyg-widget="activities"
        data-gyg-number-of-items="40"
        data-gyg-partner-id="<?= v2_e($gygPartnerId) ?>"
        aria-label="<?= v2_te('GetYourGuide activities for {city}', ['city' => $cityName]) ?>">
        <span class="gyg-powered"><?= v2_t('Powered by <a target="_blank" rel="sponsored noopener" href="{url}">GetYourGuide</a>', ['url' => v2_e($gygUrl)]) ?></span>
      </div>
    </div>
  </section>
    <?php
};

// Flights (Aviasales): the cheapest return fares found lately from the large European airports, for cities with an airport.
$flights = v2_flights_to((string) $slug, 6);
// Plan your trip: airport transfer, luggage storage, eSIM, car hire, where a partner serves this city or its country.
$tripLinks = v2_trip_links((string) $slug, $countryCode, $cityName, $countryName);

// ---- Links (only validated parameters are carried over; filter links land on the listing) ----
$baseGet = array_filter([
    'q'         => $searchQuery,
    'category'  => (string) $categoryFilter,
    'max_price' => $maxPrice !== null ? (string) $maxPrice : '',
    'sort'      => $sort === 'recommended' ? '' : $sort,
], fn ($v) => $v !== '');
$cityUrl = function (array $over = []) use ($baseGet, $slug): string {
    $p = array_filter(array_merge($baseGet, $over), fn ($v) => $v !== '' && $v !== null);
    return '/' . $slug . ($p ? '?' . http_build_query($p) : '') . '#things-to-do';
};
$catLink = fn (string $catSlug): string => '/' . $slug . '?category=' . rawurlencode($catSlug) . '#things-to-do';

// Active filter chips
$activeChips = [];
if ($searchQuery !== '') {
    $activeChips[] = ['“' . $searchQuery . '”', $cityUrl(['q' => ''])];
}
if ($categoryFilter) {
    $activeChips[] = [$catNameOf($categoryFilter), $cityUrl(['category' => ''])];
}
if ($maxPrice !== null) {
    $activeChips[] = [v2_t('Under {price}', ['price' => v2_money($maxPrice)]), $cityUrl(['max_price' => ''])];
}
if ($sort !== 'recommended') {
    $activeChips[] = [v2_t('Sorted: {order}', ['order' => $sortOptions[$sort]]), $cityUrl(['sort' => ''])];
}

$priceHtml = function (?int $cents, string $currency = '', $eurCents = null): string {
    if ($cents === null) {
        return '';
    }
    if ($cents === 0) {
        return '<span class="xp-price"><b>' . v2_te('Free') . '</b></span>';
    }
    return '<span class="xp-price">' . v2_te('from') . '<b>' . v2_e(v2_own_price_label($cents, $currency, $eurCents)) . '</b></span>';
};

// ============================================================
// SEO setup
// ============================================================
$pageTitleRaw = ($countryName !== ''
    ? v2_t('Things to do in {city}, {country}: tickets and experiences', ['city' => $cityName, 'country' => $countryName])
    : v2_t('Things to do in {city}: tickets and experiences', ['city' => $cityName])) . ' | Viaqui';
$pageDescription = v2_t('Things to do in {city}: attractions, museums, tours, family days out and experiences. Book online and walk in with a QR ticket on your phone.', ['city' => $cityName]);
$canonicalUrl = SITE_URL . '/' . $slug;
$ogImage = $cityCover
    ? (str_starts_with($cityCover, 'http') ? $cityCover : STORAGE_URL . '/' . ltrim($cityCover, '/'))
    : (SITE_URL . '/assets/images/og-default.jpg');

$breadcrumbs = array_values(array_filter([
    ['name' => v2_t('Home'), 'url' => SITE_URL . '/'],
    ['name' => v2_t('Destinations'), 'url' => SITE_URL . '/cities'],
    $countryName !== '' && $countrySlug !== '' ? ['name' => $countryName, 'url' => SITE_URL . '/' . $countrySlug] : null,
    ['name' => $cityName, 'url' => $canonicalUrl],
]));

// JSON-LD CollectionPage with City entity
$itemListElements = [];
foreach (array_slice(array_values(array_filter($cards, fn ($c) => empty($c['partner']))), 0, 10) as $i => $card) {   // our own pages only
    $itemListElements[] = [
        '@type' => 'ListItem',
        'position' => $i + 1,
        'name' => $card['title'],
        'url' => SITE_URL . $card['url'],
    ];
}
$structuredData = [];
$structuredData[] = [
    '@context' => 'https://schema.org',
    '@type' => 'CollectionPage',
    'name' => v2_t('Things to do in {city}', ['city' => $cityName]),
    'url' => $canonicalUrl,
    'inLanguage' => v2_locale(),
    'about' => [
        '@type' => 'City',
        'name' => $cityName,
        'address' => [
            '@type' => 'PostalAddress',
            'addressLocality' => $cityName,
            'addressRegion' => $countyName ?: $regionName,
            'addressCountry' => $countryCode ?: null,
        ],
    ],
    'mainEntity' => [
        '@type' => 'ItemList',
        'numberOfItems' => (int) ($pagination['total'] ?? count($itemListElements)),
        'itemListElement' => $itemListElements,
    ],
];
$structuredData[] = [
    '@context' => 'https://schema.org',
    '@type' => 'BreadcrumbList',
    'itemListElement' => array_map(fn ($bc, $i) => [
        '@type' => 'ListItem',
        'position' => $i + 1,
        'name' => $bc['name'],
        'item' => $bc['url'],
    ], $breadcrumbs, array_keys($breadcrumbs)),
];

// FAQPage JSON-LD when admin set FAQs for this city
if (!empty($cityFaqs)) {
    $structuredData[] = [
        '@context' => 'https://schema.org',
        '@type' => 'FAQPage',
        'mainEntity' => array_map(fn ($f) => [
            '@type' => 'Question',
            'name' => $f['q'],
            'acceptedAnswer' => ['@type' => 'Answer', 'text' => $f['a']],
        ], $cityFaqs),
    ];
}

// ============================================================
// Discovery data (real images + DB-derived lists)
// ============================================================
// Gallery — city cover (or the provisional city photo), then activity covers, then attraction covers.
$coverResolved = v2_media_url($cityCover) ?? v2_media_url($cityImage) ?? '';
$gallery = [];
if ($coverResolved !== '') {
    $gallery[] = ['src' => $coverResolved, 'alt' => $cityName];
} elseif (!empty($V2NAV['cities'][$slug]['photo'][0])) {
    $cityPhoto = $V2NAV['cities'][$slug]['photo'];
    $gallery[] = ['src' => $cityPhoto[0], 'alt' => ($cityPhoto[3] ?? '') !== '' ? $cityPhoto[3] : $cityName];
}
foreach ($activities as $a) {
    if (count($gallery) >= 5) break;
    $img = is_array($a) ? v2_media_url($a['cover_image_url'] ?? null) : null;
    if ($img) $gallery[] = ['src' => $img, 'alt' => navFlatName($a['title'] ?? '') ?: $cityName];
}
foreach ($cityAttractions as $at) {
    if (count($gallery) >= 5) break;
    if ($at['image']) $gallery[] = ['src' => $at['image'], 'alt' => $at['name']];
}
$heroPhoto = $gallery[0] ?? null;

// Rotating category cards in the hero (6 categories).
$wheelItems = [];
foreach (array_slice($topCategories, 0, 6) as $cat) {
    $wheelItems[] = ['label' => $cat['name'], 'image' => $cat['image'], 'srcset' => $cat['srcset'], 'href' => $catLink($cat['slug'])];
}
if (empty($wheelItems)) {
    foreach ([v2_t('Museums & Exhibitions'), v2_t('Tours & Sightseeing'), v2_t('Nature & Outdoors'), v2_t('Family & Kids')] as $label) {
        $wheelItems[] = ['label' => $label, 'image' => null, 'srcset' => '', 'href' => '/' . $slug];
    }
}

// Other cities: the same country first (the shell knows the largest cities of each country), then the rest.
$otherCitiesAll = array_values(array_filter($V2NAV['citiesList'], fn ($c) => $c['slug'] !== $slug));
$cityRegion = $countryName !== '' ? $countryName : ($V2NAV['cities'][$slug]['region'] ?? '');
$sameRegion = $cityRegion !== '' ? array_values(array_filter($otherCitiesAll, fn ($c) => $c['region'] === $cityRegion)) : [];
$nearbyCities = array_slice(array_merge($sameRegion, array_values(array_filter($otherCitiesAll, fn ($c) => !in_array($c, $sameRegion, true)))), 0, 5);
$otherCities = array_slice($otherCitiesAll, 0, 8);

// Editorial guides (Inspiration). Section hidden when none.
$cityGuides = array_slice($V2NAV['guides'], 0, 3);

// Traveler types — city-scoped search links (city.php handles ?q), so these
// always resolve to real filtered results without needing dedicated routes.
$travelerTypes = [
    ['icon' => 'users-three',   'title' => v2_t('For families'),     'desc' => v2_t('Safe, hands-on and easy to plan with children.'),              'href' => '/' . $slug . '?q=' . rawurlencode('kids') . '#things-to-do'],
    ['icon' => 'heart',         'title' => v2_t('For couples'),      'desc' => v2_t('Tours, gift experiences and things to do in the evening.'),    'href' => '/' . $slug . '?q=' . rawurlencode('couple') . '#things-to-do'],
    ['icon' => 'cloud-rain',    'title' => v2_t('When it rains'),    'desc' => v2_t('Museums, workshops, escape rooms and indoor experiences.'),   'href' => '/' . $slug . '?q=' . rawurlencode('indoor') . '#things-to-do'],
    ['icon' => 'castle-turret', 'title' => v2_t('On a first visit'), 'desc' => v2_t('The main sights, guided tours and what not to miss.'),         'href' => '/' . $slug . '?q=' . rawurlencode('tour') . '#things-to-do'],
];

// Intent hubs (connect the city page to the programmatic intent pages).
// Empty for now: the intent pages (weekend ideas, with kids …) are built from listings, and Viaqui has none yet,
// so every one of these links would lead to "page not found". Fill it again when cities have experiences:
//   ['weekend-ideas', 'sun', 'this weekend'], ['with-kids', 'users-three', 'with kids'],
//   ['rainy-days', 'cloud-rain', 'indoors'], ['for-couples', 'heart', 'for couples']
$intentLinks = [];

$cityArches = '<svg class="deco-arches" viewBox="0 0 400 400" aria-hidden="true" focusable="false"><path d="M40 400V200a160 160 0 0 1 320 0v200"/><path d="M90 400V200a110 110 0 0 1 220 0v200"/><path d="M140 400V200a60 60 0 0 1 120 0v200"/></svg>';

// An API outage would otherwise be cached as a half-empty page for five minutes.
if (empty($V2NAV['categories'])) {
    $skipPageCache = true;
}

$v2Styles = ['city.css'];
$v2Scripts = ['hdrag.js', 'city.js'];
$v2HeaderOverlay = true;
$v2HeadExtra = $heroPhoto ? '<link rel="preload" as="image" href="' . v2_e($heroPhoto['src']) . '" fetchpriority="high">' : '';
$v2ClientData = ['gallery' => $gallery];

include __DIR__ . '/includes/v2/head.php';
include __DIR__ . '/includes/v2/header.php';
?>
<main id="main" tabindex="-1">
  <!-- ============================== HERO ============================== -->
  <section class="ch" aria-labelledby="ch-h">
    <svg class="ch-line draw-clip" viewBox="0 590 3240 310" aria-hidden="true" focusable="false"><use href="#drum-g"/></svg>
    <div class="ch-in">
      <div class="ch-copy">
        <nav class="crumbs" aria-label="<?= v2_te('Breadcrumb') ?>">
          <?php foreach ($breadcrumbs as $i => $bc): ?>
            <?php if ($i > 0): ?><span aria-hidden="true">/</span><?php endif; ?>
            <?php if ($i < count($breadcrumbs) - 1): ?><a href="<?= v2_e(substr($bc['url'], strlen(SITE_URL)) ?: '/') ?>"><?= v2_e($bc['name']) ?></a><?php else: ?><span aria-current="page"><?= v2_e($bc['name']) ?></span><?php endif; ?>
          <?php endforeach; ?>
        </nav>
        <h1 class="ch-h" id="ch-h"><?= v2_t('<span class="ch-pre">Things to do in</span> <span class="ch-city">{city}</span>', ['city' => v2_e($cityName)]) ?></h1>
        <p class="ch-lead">
          <?php if ($cityDescription !== ''): ?>
            <?= v2_e($cityDescription) ?>
          <?php else: ?>
            <?= $countryName !== ''
                ? v2_te('Attractions, museums, tours, family days out and time outdoors in {city}, {country}. Choose what to see, book online and walk in with the QR ticket on your phone.', ['city' => $cityName, 'country' => $countryName])
                : v2_te('Attractions, museums, tours, family days out and time outdoors in {city}. Choose what to see, book online and walk in with the QR ticket on your phone.', ['city' => $cityName]) ?>
          <?php endif; ?>
        </p>
        <form class="ch-search" action="/<?= v2_e($slug) ?>#things-to-do" method="get" role="search" aria-label="<?= v2_te('Search things to do in {city}', ['city' => $cityName]) ?>">
          <?= v2_ic('magnifying-glass') ?>
          <label class="sr" for="city-search"><?= v2_te('Search things to do in {city}', ['city' => $cityName]) ?></label>
          <input id="city-search" name="q" type="search" placeholder="<?= v2_te('Search in {city}: museums, tours, kids…', ['city' => $cityName]) ?>" autocomplete="off">
          <button class="btn btn-primary" type="submit"><?= v2_te('Search') ?></button>
        </form>
        <?php if (!empty($topCategories)): ?>
        <div class="ch-pop">
          <span><?= v2_te('Popular:') ?></span>
          <?php foreach (array_slice($topCategories, 0, 4) as $cat): ?><a href="<?= v2_e($catLink($cat['slug'])) ?>"><?= v2_e($cat['name']) ?></a><?php endforeach; ?>
        </div>
        <?php endif; ?>
      </div>

      <div class="ch-media">
        <div class="ch-arch">
          <?php if ($heroPhoto): ?>
          <img src="<?= v2_e($heroPhoto['src']) ?>" alt="<?= v2_e($heroPhoto['alt']) ?>" fetchpriority="high" decoding="async">
          <?php else: ?>
          <?= v2_fallback($cityName) ?>
          <?php endif; ?>
          <?php if ($gallery): ?>
          <button class="ch-gal" type="button" data-gallery="0" aria-haspopup="dialog" aria-controls="lb">
            <span class="ch-thumbs" aria-hidden="true"><?php foreach (array_slice($gallery, 0, 3) as $g): ?><img src="<?= v2_e($g['src']) ?>" alt="" loading="lazy" decoding="async"><?php endforeach; ?></span>
            <span><?= v2_te('Photo gallery') ?></span><b><?= count($gallery) ?></b>
          </button>
          <?php endif; ?>
        </div>
        <!-- Categorii populare: one card at a time swings in along the arch, then out; cycles continuously. -->
        <div class="orbit" data-orbit role="group" aria-label="<?= v2_te('Popular categories in {city}', ['city' => $cityName]) ?>">
          <?php foreach ($wheelItems as $i => $w): ?>
          <a class="oc<?= $i === 0 ? ' is-on' : '' ?>" href="<?= v2_e($w['href']) ?>">
            <span class="oc-media"><?= $w['image'] ? v2_photo([$w['image'], 640, 800, ''], $w['srcset'] ? ' srcset="' . v2_e($w['srcset']) . '" sizes="170px"' : '') : v2_fallback($w['label'], $i) ?></span>
            <span class="oc-name"><?= v2_e($w['label']) ?></span>
            <span class="oc-meta"><?= v2_te('in {city}', ['city' => $cityName]) ?></span>
          </a>
          <?php endforeach; ?>
        </div>
        <?php if ($coverResolved !== '' && is_array($cityData['image_credit'] ?? null)): require_once __DIR__ . '/includes/v2/places.php'; ?>
        <p class="ch-credit"><?= v2_photo_credit($cityData['image_credit']) ?></p>
        <?php endif; ?>
      </div>
    </div>
  </section>
  <div id="hdr-sentinel" aria-hidden="true"></div>

  <!-- ============================== STICKY SECTION NAV ============================== -->
  <nav class="cnav" aria-label="<?= v2_te('Sections of this page') ?>">
    <div class="wrap cnav-in">
      <a href="#things-to-do" aria-current="true"><?= v2_te('Things to do') ?></a>
      <a href="#interests"><?= v2_te('Browse by interest') ?></a>
      <?php if (!empty($cityAttractions)): ?><a href="#attractions"><?= v2_te('Attractions') ?></a><?php endif; ?>
      <?php if (!empty($cityGuides)): ?><a href="#guides"><?= v2_te('Guides') ?></a><?php endif; ?>
      <?php if (!empty($nearbyCities)): ?><a href="#nearby"><?= v2_te('More cities') ?></a><?php endif; ?>
      <a href="#local-guide"><?= v2_te('FAQ') ?></a>
    </div>
  </nav>

  <!-- ============================== GETYOURGUIDE (promoted: cities without own listings) ============================== -->
  <?php if ($gygPromote) { $renderGygSection(); } ?>

  <!-- ============================== PROMOVATE (paid city placement) ============================== -->
  <?php v2_promoted_section($promoted, [
      'id' => 'promo-city',
      'kicker' => v2_t('Promoted'),
      'title' => v2_t('Popular in {city}', ['city' => $cityName]),
      'intro' => v2_t('Places and experiences in {city} that their operators are promoting at the moment.', ['city' => $cityName]),
  ]); ?>

  <!-- ============================== ACTIVITIES ============================== -->
  <section class="cl" id="things-to-do" aria-labelledby="cl-h">
    <div class="wrap cl-head">
      <h2 id="cl-h"><?= v2_te('Things to do in {city}', ['city' => $cityName]) ?></h2>
      <p class="cl-count"><b><?= (int) $pagination['total'] ?></b> <?= v2_e(v2_plural((int) $pagination['total'], 'result', 'results')) ?></p>
    </div>

    <div class="ctb">
      <div class="wrap">
        <div class="ctb-in">
          <form class="ctb-search" action="/<?= v2_e($slug) ?>#things-to-do" method="get" role="search" aria-label="<?= v2_te('Search in {city}', ['city' => $cityName]) ?>">
            <?= v2_ic('magnifying-glass') ?>
            <label class="sr" for="cl-q"><?= v2_te('Search in {city}', ['city' => $cityName]) ?></label>
            <input id="cl-q" name="q" type="search" value="<?= v2_e($searchQuery) ?>" placeholder="<?= v2_te('Search in {city}…', ['city' => $cityName]) ?>" autocomplete="off">
            <?php foreach ($baseGet as $k => $v): if ($k === 'q') continue; ?><input type="hidden" name="<?= v2_e($k) ?>" value="<?= v2_e($v) ?>"><?php endforeach; ?>
            <button class="btn btn-primary ctb-go" type="submit"><span><?= v2_te('Search') ?></span><?= v2_ic('arrow-right') ?></button>
          </form>

          <div class="ctb-dds">
            <details class="dd">
              <summary class="dd-btn<?= $categoryFilter ? ' is-set' : '' ?>"><?= v2_e($categoryFilter ? $catNameOf($categoryFilter) : v2_t('Category')) ?><?= v2_ic('caret-down') ?></summary>
              <div class="dd-pop">
                <ul>
                  <li><a href="<?= v2_e($cityUrl(['category' => ''])) ?>"<?= !$categoryFilter ? ' aria-current="true"' : '' ?>><?= v2_te('All categories') ?></a></li>
                  <?php foreach ($topCategories as $c): ?><li><a href="<?= v2_e($cityUrl(['category' => $c['slug']])) ?>"<?= $categoryFilter === $c['slug'] ? ' aria-current="true"' : '' ?>><?= v2_e($c['name']) ?></a></li><?php endforeach; ?>
                </ul>
              </div>
            </details>
            <?php if ($priceMaxAllowed): ?>
            <details class="dd">
              <summary class="dd-btn<?= $maxPrice !== null ? ' is-set' : '' ?>"><?= $maxPrice !== null ? v2_te('Under {price}', ['price' => v2_money($maxPrice)]) : v2_te('Price') ?><?= v2_ic('caret-down') ?></summary>
              <div class="dd-pop">
                <ul>
                  <li><a href="<?= v2_e($cityUrl(['max_price' => ''])) ?>"<?= $maxPrice === null ? ' aria-current="true"' : '' ?>><?= v2_te('Any price') ?></a></li>
                  <?php foreach ($priceMaxAllowed as $cap): ?><li><a href="<?= v2_e($cityUrl(['max_price' => $cap])) ?>"<?= $maxPrice === $cap ? ' aria-current="true"' : '' ?>><?= v2_te('Under {price}', ['price' => v2_money($cap)]) ?></a></li><?php endforeach; ?>
                </ul>
              </div>
            </details>
            <?php endif; ?>
            <details class="dd">
              <summary class="dd-btn<?= $sort !== 'recommended' ? ' is-set' : '' ?>"><?= v2_e($sort !== 'recommended' ? $sortOptions[$sort] : v2_t('Sort')) ?><?= v2_ic('caret-down') ?></summary>
              <div class="dd-pop is-right">
                <ul>
                  <?php foreach ($sortOptions as $value => $label): ?><li><a href="<?= v2_e($cityUrl(['sort' => $value === 'recommended' ? '' : $value])) ?>"<?= $sort === $value ? ' aria-current="true"' : '' ?>><?= v2_e($label) ?></a></li><?php endforeach; ?>
                </ul>
              </div>
            </details>
          </div>

          <button class="ctb-open" type="button" data-sheet-open aria-haspopup="dialog" aria-controls="cl-sheet" aria-expanded="false"><?= v2_ic('list') ?><?= v2_te('Filters') ?><?php if ($activeChips): ?><span class="ctb-n"><?= count($activeChips) ?></span><?php endif; ?></button>
        </div>

        <?php if ($activeChips): ?>
        <ul class="cl-active" aria-label="<?= v2_te('Active filters') ?>">
          <?php foreach ($activeChips as [$label, $href]): ?><li><a class="achip" href="<?= v2_e($href) ?>"><?= v2_e($label) ?><?= v2_ic('x') ?><span class="sr"> <?= v2_te('(remove)') ?></span></a></li><?php endforeach; ?>
          <li><a class="aclear" href="/<?= v2_e($slug) ?>#things-to-do"><?= v2_te('Clear all') ?></a></li>
        </ul>
        <?php endif; ?>
      </div>
    </div>

    <!-- phone filter sheet (shown inline when JavaScript is off) -->
    <div class="sheet" id="cl-sheet" role="dialog" aria-modal="true" aria-labelledby="cl-sheet-h">
      <div class="sheet-panel">
        <div class="sheet-top">
          <h2 id="cl-sheet-h"><?= v2_te('Filters') ?></h2>
          <button class="icon-btn" type="button" data-sheet-close><?= v2_ic('x') ?><span class="sr"><?= v2_te('Close filters') ?></span></button>
        </div>
        <div class="sheet-body">
          <section class="fgroup">
            <h3 class="flabel"><?= v2_te('Category') ?></h3>
            <div class="fchips">
              <a class="fchip" href="<?= v2_e($cityUrl(['category' => ''])) ?>"<?= !$categoryFilter ? ' aria-current="true"' : '' ?>><?= v2_te('All') ?></a>
              <?php foreach ($topCategories as $c): ?><a class="fchip" href="<?= v2_e($cityUrl(['category' => $c['slug']])) ?>"<?= $categoryFilter === $c['slug'] ? ' aria-current="true"' : '' ?>><?= v2_e($c['name']) ?></a><?php endforeach; ?>
            </div>
          </section>
          <?php if ($priceMaxAllowed): ?>
          <section class="fgroup">
            <h3 class="flabel"><?= v2_te('Maximum price') ?></h3>
            <div class="fchips">
              <a class="fchip" href="<?= v2_e($cityUrl(['max_price' => ''])) ?>"<?= $maxPrice === null ? ' aria-current="true"' : '' ?>><?= v2_te('Any') ?></a>
              <?php foreach ($priceMaxAllowed as $cap): ?><a class="fchip" href="<?= v2_e($cityUrl(['max_price' => $cap])) ?>"<?= $maxPrice === $cap ? ' aria-current="true"' : '' ?>><?= v2_te('Under {price}', ['price' => v2_money($cap)]) ?></a><?php endforeach; ?>
            </div>
          </section>
          <?php endif; ?>
          <section class="fgroup">
            <h3 class="flabel"><?= v2_te('Sort') ?></h3>
            <div class="fchips">
              <?php foreach ($sortOptions as $value => $label): ?><a class="fchip" href="<?= v2_e($cityUrl(['sort' => $value === 'recommended' ? '' : $value])) ?>"<?= $sort === $value ? ' aria-current="true"' : '' ?>><?= v2_e($label) ?></a><?php endforeach; ?>
            </div>
          </section>
        </div>
      </div>
    </div>

    <div class="wrap">
      <?php if ($cards && ($clFx = v2_fx_note($countryCode)) !== ''): ?><p class="cl-fx"><?= v2_ic('info') ?><?= v2_e($clFx) ?></p><?php endif; ?>
      <?php if (empty($cards)): ?>
      <div class="cl-empty">
        <h3>
          <?php if ($categoryFilter): ?>
            <?= v2_te('Nothing in this category in {city} yet.', ['city' => $cityName]) ?>
          <?php else: ?>
            <?= v2_te('Nothing is listed here yet.') ?>
          <?php endif; ?>
        </h3>
        <p><?= v2_te('Venues and operators in {city} are being added. Until then, the attractions map and the routes show what there is to see.', ['city' => $cityName]) ?></p>
        <div class="cl-empty-cta">
          <a class="btn btn-light" href="/map?city=<?= v2_e($slug) ?>"><?= v2_te('Open the map') ?></a>
          <?php if ($activeChips): ?><a class="btn btn-ghost" href="/<?= v2_e($slug) ?>#things-to-do"><?= v2_te('Clear the filters') ?></a><?php endif; ?>
        </div>
        <svg class="cl-empty-line" viewBox="0 590 3240 310" aria-hidden="true" focusable="false"><use href="#drum-g"/></svg>
      </div>
      <?php else: ?>
      <?php if ($pageNum > 1): // opened in the middle of the list: the way back to its start, with or without the pager ?>
      <p class="cl-earlier"><a href="<?= v2_e($cityUrl(['page' => ''])) ?>"><?= v2_ic('arrow-left') ?><?= v2_te('See the list from the start') ?></a></p>
      <?php endif; ?>
      <ul class="xp-grid" data-reveal>
        <?php foreach ($cards as $i => $card): ?>
        <?php if (!empty($card['partner'])): ?><?= v2_partner_cards([$card['partner']], 'wegotrip', 'city-' . $slug) ?><?php continue; endif; ?>
        <li class="xp">
          <a href="<?= v2_e($card['url']) ?>">
            <span class="xp-media"><?= $card['image'] ? v2_photo([$card['image'], 0, 0, '']) : v2_fallback($card['title'], $i) ?><?= $card['promoted'] ? v2_promoted_tag() : '' ?></span>
            <span class="xp-body">
              <span class="xp-cat"><?= v2_e($card['cat']) ?></span>
              <span class="xp-title"><?= v2_e($card['title']) ?></span>
              <span class="xp-meta"><?php if ($card['dur']): ?><span><?= v2_ic('clock') ?><?= v2_e($card['dur']) ?></span><?php endif; ?></span>
              <span class="xp-foot"><span class="xp-go"><?= v2_e($card['cta']) ?><?= v2_ic('arrow-right') ?></span><?= $priceHtml($card['price_cents'], $card['currency'], $card['eur_cents']) ?></span>
            </span>
          </a>
        </li>
        <?php endforeach; ?>
      </ul>
      <?php if ($partnerOnPage): ?><?= v2_partner_note('wegotrip') ?><?php endif; ?>

      <?php
      $current = (int) $pagination['current_page'];
      $last = max(1, (int) $pagination['last_page']);
      if ($last > 1):
          $start = max(1, $current - 3);
          $end = min($last, $start + 6);
          $start = max(1, $end - 6);
      ?>
      <nav class="pager" aria-label="<?= v2_te('Pages') ?>">
        <?php if ($current > 1): ?><a class="pg-step" href="<?= v2_e($cityUrl(['page' => $current - 1 > 1 ? $current - 1 : ''])) ?>" rel="prev"><?= v2_ic('arrow-left') ?><?= v2_te('Previous') ?></a><?php endif; ?>
        <?php for ($p = $start; $p <= $end; $p++): ?>
          <?php if ($p === $current): ?><span aria-current="page"><?= $p ?></span><?php else: ?><a href="<?= v2_e($cityUrl(['page' => $p > 1 ? $p : ''])) ?>"><?= $p ?></a><?php endif; ?>
        <?php endfor; ?>
        <?php if ($current < $last): ?><a class="pg-step" href="<?= v2_e($cityUrl(['page' => $current + 1])) ?>" rel="next"><?= v2_te('Next') ?><?= v2_ic('arrow-right') ?></a><?php endif; ?>
      </nav>
      <?php endif; ?>
      <?php endif; ?>
    </div>
  </section>

  <!-- ============================== GETYOURGUIDE ("extra" slot, below our own listings) ============================== -->
  <?php if ($gygWidgetEnabled && !$gygPromote) { $renderGygSection(); } ?>

  <!-- ============================== EXPLOREAZĂ DUPĂ INTERES (categorii + traveler) ============================== -->
  <section class="sec ci" id="interests" aria-labelledby="ci-h">
    <div class="wrap ci-grid">
      <div>
        <div class="sec-head">
          <div>
            <h2 id="ci-h"><?= v2_te('Browse by interest') ?></h2>
            <p class="sec-sub"><?= v2_te('Categories that get you to what you are looking for in {city} faster.', ['city' => $cityName]) ?></p>
          </div>
        </div>
        <?php if (!empty($topCategories)): ?>
        <ul class="ctiles" data-reveal>
          <?php foreach ($topCategories as $cat): ?>
          <li>
            <a class="ctile" href="<?= v2_e($catLink($cat['slug'])) ?>">
              <span class="ctile-media"><?= $cat['thumb'] ? v2_photo([$cat['thumb'], 0, 0, '']) : v2_fallback($cat['name']) ?></span>
              <span class="ctile-text"><b><?= v2_e($cat['name']) ?></b><?php if ($cat['count'] > 0): ?><small><?= v2_e(v2_num($cat['count'], 'experience', 'experiences')) ?></small><?php endif; ?></span>
              <?= v2_ic('arrow-right') ?>
            </a>
          </li>
          <?php endforeach; ?>
        </ul>
        <?php endif; ?>
      </div>

      <aside class="styles" aria-labelledby="styles-h">
        <?= $cityArches ?>
        <h3 id="styles-h"><?= v2_te('Choose by how you travel') ?></h3>
        <ul>
          <?php foreach ($travelerTypes as $tt): ?>
          <li><a class="style" href="<?= v2_e($tt['href']) ?>"><span class="style-ic"><?= v2_ic($tt['icon']) ?></span><span><b><?= v2_e($tt['title']) ?></b><small><?= v2_e($tt['desc']) ?></small></span></a></li>
          <?php endforeach; ?>
        </ul>
      </aside>
    </div>
  </section>

  <!-- ============================== LOCAȚII (bilete online la intrare) ============================== -->
  <?php if ($cityLocations): ?>
  <section class="sec attr" id="venues" aria-labelledby="loc-h">
    <div class="wrap">
      <div class="sec-head">
        <div>
          <h2 id="loc-h"><?= v2_te('Venues with online tickets in {city}', ['city' => $cityName]) ?></h2>
          <p class="sec-sub"><?= v2_te('Book the entry ticket and the experiences on site ahead of time, in one order.') ?></p>
        </div>
        <div class="sec-tools">
          <a class="sec-link" href="/<?= v2_e($slug) ?>/venues"><?= v2_te('All venues') ?><?= v2_ic('arrow-right') ?></a>
          <?php if (count($cityLocations) > 2): ?>
          <div class="rail-btns" data-for="loc-rail">
            <button class="rail-btn" type="button" data-dir="-1" aria-label="<?= v2_te('Previous venues') ?>"><?= v2_ic('arrow-left') ?></button>
            <button class="rail-btn" type="button" data-dir="1" aria-label="<?= v2_te('Next venues') ?>"><?= v2_ic('arrow-right') ?></button>
          </div>
          <?php endif; ?>
        </div>
      </div>
      <ul class="rail" id="loc-rail">
        <?php foreach ($cityLocations as $li => $lc): ?>
        <li class="at"><a href="<?= v2_e($lc['href']) ?>">
          <span class="at-media"><?= $lc['image'] ? v2_photo([$lc['image'], 0, 0, '']) : v2_fallback($lc['name'], $li) ?><?php if ($lc['promoted']): ?><?= v2_promoted_tag() ?><?php endif; ?><?php if ($lc['lodging']): ?><span class="at-badge"><?= v2_te('Stays') ?></span><?php endif; ?></span>
          <span class="at-name"><?= v2_e($lc['name']) ?><?= v2_ic('arrow-right') ?></span>
          <?php if ($lc['meta'] !== ''): ?><span class="at-meta"><span><?= v2_e($lc['meta']) ?></span></span><?php endif; ?>
        </a></li>
        <?php endforeach; ?>
      </ul>
    </div>
  </section>
  <?php endif; ?>

  <!-- ============================== ATRACȚII ============================== -->
  <?php if (!empty($cityAttractions)): ?>
  <section class="sec attr" id="attractions" aria-labelledby="attr-h">
    <svg class="attr-line" viewBox="0 590 3240 310" aria-hidden="true" focusable="false"><use href="#drum-g"/></svg>
    <div class="wrap">
      <div class="sec-head">
        <div>
          <h2 id="attr-h"><?= v2_te('Attractions not to miss in {city}', ['city' => $cityName]) ?></h2>
          <p class="sec-sub"><?= v2_te('The places that define the city, and what you can do around them.') ?></p>
        </div>
        <div class="sec-tools">
          <a class="sec-link" href="/<?= v2_e($slug) ?>/attractions"><?= v2_te('All attractions') ?><?= v2_ic('arrow-right') ?></a>
          <div class="rail-btns" data-for="attr-rail">
            <button class="rail-btn" type="button" data-dir="-1" aria-label="<?= v2_te('Previous attractions') ?>"><?= v2_ic('arrow-left') ?></button>
            <button class="rail-btn" type="button" data-dir="1" aria-label="<?= v2_te('Next attractions') ?>"><?= v2_ic('arrow-right') ?></button>
          </div>
        </div>
      </div>
      <ul class="rail" id="attr-rail">
        <?php foreach ($cityAttractions as $at): ?>
        <li class="at"><a href="<?= v2_e($at['href']) ?>">
          <span class="at-media"><?= $at['image'] ? v2_photo([v2_thumb($at['image'], 640), 0, 0, '']) : v2_fallback($at['name']) ?><?php if ($at['count'] > 0): ?><span class="at-badge"><?= v2_e(v2_num($at['count'], 'experience', 'experiences')) ?></span><?php endif; ?></span>
          <span class="at-name"><?= v2_e($at['name']) ?><?= v2_ic('arrow-right') ?></span>
          <?php if ($at['type'] !== ''): ?><span class="at-meta"><span><?= v2_e($at['type']) ?></span></span><?php endif; ?>
        </a></li>
        <?php endforeach; ?>
      </ul>
    </div>
  </section>
  <?php endif; ?>

  <!-- ============================== GHIDURI ============================== -->
  <?php if (!empty($cityGuides)): ?>
  <section class="sec insp" id="guides" aria-labelledby="guides-h">
    <div class="wrap">
      <div class="sec-head">
        <h2 id="guides-h"><?= v2_te('Guides and ideas') ?></h2>
        <a class="sec-link" href="/guides"><?= v2_te('All guides') ?><?= v2_ic('arrow-right') ?></a>
      </div>
      <ul class="cg-grid" data-reveal>
        <?php foreach ($cityGuides as $g): ?>
        <li class="ia"><a href="<?= v2_e($g['href']) ?>">
          <span class="ia-media"><?= $g['cover'] ? v2_photo($g['cover']) : v2_fallback($g['slug']) ?></span>
          <span class="ia-text">
            <span class="ia-cat"><?= v2_e($g['category'] !== '' ? $g['category'] : v2_t('Guide')) ?></span>
            <h3><?= v2_e($g['title']) ?></h3>
            <?php if ($g['excerpt'] !== ''): ?><p><?= v2_e($g['excerpt']) ?></p><?php endif; ?>
            <span class="ia-more"><?= v2_te('Read the guide') ?><?= v2_ic('arrow-right') ?></span>
          </span>
        </a></li>
        <?php endforeach; ?>
      </ul>
    </div>
  </section>
  <?php endif; ?>

  <!-- ============================== ORAȘE APROPIATE ============================== -->
  <?php if (!empty($nearbyCities)): ?>
  <?php if ($flights): ?>
  <!-- ============================== FLIGHTS (partner: Aviasales) ============================== -->
  <?php
  // A boarding pass: the lowest fare found leads on the deep stub, the fare of each departure city is a row of the
  // board beside it. Every number is a fare the Aviasales data returned (cheapest first); nothing is rounded or added.
  $flBest = $flights[0];
  $flSub = 'city-' . $slug . '-flights';
  $flBestPrice = v2_price_local($flBest['price'], '');
  $flBestDates = v2_flight_dates($flBest['out'], $flBest['back']);
  // Aviasales' own search form with this city filled in as the destination: every fare, from wherever the visitor is
  $flAllUrl = !empty($flBest['to'])
      ? 'https://www.aviasales.com/?destination_iata=' . rawurlencode((string) $flBest['to']) . '&currency=' . strtolower(SITE_CURRENCY) . '&locale=en'
      : $flBest['url'];
  ?>
  <section class="sec flp" id="flights" aria-labelledby="fl-h">
    <div class="wrap">
      <div class="fl-pass">
        <div class="fl-lead">
          <?php if ($heroPhoto): ?><img class="fl-photo" src="<?= v2_e($heroPhoto['src']) ?>" alt="" loading="lazy" decoding="async"><?php endif; ?>
          <p class="kicker"><?= v2_te('Getting there') ?></p>
          <h2 id="fl-h"><?= v2_t('Return flights to {city} <span class="fl-price">from <b>{price}</b></span>', ['city' => v2_e($cityName), 'price' => v2_e($flBestPrice)]) ?></h2>
          <p class="fl-best"><?= $flBest['direct']
              ? v2_te('The lowest fare we found: {origin} to {city}, {dates}, direct.', ['origin' => $flBest['from'], 'city' => $cityName, 'dates' => $flBestDates])
              : v2_te('The lowest fare we found: {origin} to {city}, {dates}, with a stop.', ['origin' => $flBest['from'], 'city' => $cityName, 'dates' => $flBestDates]) ?></p>
          <?php if (!empty($flBest['to'])):
              $flFromUrl = fn (string $code): string => 'https://www.aviasales.com/?origin_iata=' . rawurlencode($code) . '&destination_iata=' . rawurlencode((string) $flBest['to']) . '&currency=' . strtolower(SITE_CURRENCY) . '&locale=en';
          ?>
          <form class="fl-form" id="fl-form" action="/go" method="get" target="_blank" rel="sponsored nofollow noopener" data-to="<?= v2_e((string) $flBest['to']) ?>" data-any="<?= v2_e($flAllUrl) ?>" data-tpl="<?= v2_e($flFromUrl('__FROM__')) ?>">
            <input type="hidden" name="p" value="aviasales">
            <input type="hidden" name="s" value="<?= v2_e(v2_partner_sub($flSub)) ?>">
            <label class="fl-from-l" for="fl-from"><?= v2_te('Flying from') ?></label>
            <div class="fl-from">
              <select class="fl-from-sel" id="fl-from" name="u">
                <option value="<?= v2_e($flAllUrl) ?>"><?= v2_te('Anywhere') ?></option>
                <?php foreach (V2_FLIGHT_ORIGINS as $flCode => $flName): if ($flCode === $flBest['to']) { continue; } ?>
                <option value="<?= v2_e($flFromUrl($flCode)) ?>" data-code="<?= v2_e($flCode) ?>"><?= v2_e($flName) ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <button class="btn btn-light fl-all" type="submit"><?= v2_te('Search flights to {city}', ['city' => $cityName]) ?><?= v2_ic('arrow-right') ?></button>
          </form>
          <?php else: ?>
          <a class="btn btn-light fl-all" href="<?= v2_e(v2_partner_href('aviasales', $flAllUrl, $flSub)) ?>" target="_blank" rel="sponsored nofollow noopener"><?= v2_te('Search all flights to {city}', ['city' => $cityName]) ?><?= v2_ic('arrow-right') ?></a>
          <?php endif; ?>
          <p class="fl-on"><?= v2_te('Opens the search on Aviasales, with your route already filled in.') ?></p>
        </div>
        <div class="fl-board">
          <p class="fl-board-h"><?= v2_te('Lowest return fare found, by departure city') ?></p>
          <ol class="fl-list">
            <?php foreach ($flights as $fi => $f): ?>
            <li><a href="<?= v2_e(v2_partner_href('aviasales', $f['url'], $flSub)) ?>" target="_blank" rel="sponsored nofollow noopener">
              <span class="fl-route"><b><?= v2_e($f['from']) ?></b><?= v2_ic('arrow-right') ?><span><?= v2_e($cityName) ?></span><?php if ($fi === 0 && count($flights) > 1): ?><i class="fl-tag"><?= v2_te('Lowest') ?></i><?php endif; ?></span>
              <span class="fl-when"><?= v2_ic('calendar-blank') ?><span><?= v2_e(v2_flight_dates($f['out'], $f['back'])) ?></span><span class="fl-stop"><?= $f['direct'] ? v2_te('Direct') : v2_te('With a stop') ?></span></span>
              <span class="fl-fare"><small><?= v2_te('from') ?></small><b><?= v2_e(v2_price_local($f['price'], '')) ?></b></span>
              <span class="fl-go"><span class="sr"><?= v2_te('See this fare on Aviasales') ?></span><?= v2_ic('arrow-right') ?></span>
            </a></li>
            <?php endforeach; ?>
          </ol>
          <p class="fl-note"><?= v2_te('Return fares for one adult, found on Aviasales in the last two days for the dates shown. Fares change often; you search and book on Aviasales or the airline\'s site. Viaqui may earn a commission, at no extra cost to you.') ?></p>
        </div>
      </div>
    </div>
  </section>
  <?php endif; ?>

  <?php if ($tripLinks): ?>
  <!-- ============================== PLAN YOUR TRIP (partners: transfer, luggage, eSIM, car) ============================== -->
  <?php
  // What each service solves for someone arriving in this city: [name of the service, the benefit, one line, the action].
  // The two a city visit needs first (the ride in from the airport, somewhere for the bags) are the large cards.
  $tripCountry = v2_partner_the($countryName);
  $tripCopy = [
      'transfer' => [
          v2_t('Airport transfer'),
          v2_t('A driver waiting for you at arrivals'),
          v2_t('The price is fixed when you book, before you fly, so there is no meter to watch on the way to your door in {city}.', ['city' => $cityName]),
          v2_t('Book a transfer'),
      ],
      'luggage' => [
          v2_t('Luggage storage'),
          v2_t('Free hands between check-out and your flight'),
          v2_t('Leave your bags for a few hours near the station or in the centre, and spend the last day in {city} without them.', ['city' => $cityName]),
          v2_t('Find luggage storage'),
      ],
      'esim' => [
          $tripCountry !== '' ? v2_t('eSIM for {country}', ['country' => $tripCountry]) : v2_t('eSIM for your trip'),
          v2_t('Mobile data from the moment you land'),
          v2_t('Install it on your phone before you leave home and switch it on when you land, with no roaming bill to come home to.'),
          v2_t('Get an eSIM'),
      ],
      'car' => [
          v2_t('Car hire'),
          v2_t('A car for the days you leave {city}', ['city' => $cityName]),
          v2_t('See the towns and countryside around {city} on your own timetable, and hand the car back when you are done.', ['city' => $cityName]),
          v2_t('Compare car hire'),
      ],
  ];
  $tripCards = array_values(array_filter($tripLinks, fn ($l) => isset($tripCopy[$l['kind']])));
  $tripLead = array_values(array_intersect(['transfer', 'luggage'], array_column($tripCards, 'kind')));
  if (!$tripLead && $tripCards) {
      $tripLead = [$tripCards[0]['kind']];      // neither is on offer here: the first service leads
  }
  usort($tripCards, fn ($a, $b) => in_array($b['kind'], $tripLead, true) <=> in_array($a['kind'], $tripLead, true));   // large cards first; the order is otherwise kept
  ?>
  <?php if ($tripCards): ?>
  <section class="sec trip" id="plan-your-trip" aria-labelledby="trip-h">
    <div class="wrap">
      <div class="trip-head">
        <p class="kicker"><?= v2_te('Before you go') ?></p>
        <h2 id="trip-h"><?= v2_te('Arrive in {city} with the practical things sorted', ['city' => $cityName]) ?></h2>
        <p class="trip-sub"><?= v2_te('The parts of a city trip that are easier to settle at home than at the airport. Each is booked online, ahead of the trip, on the partner\'s own site.') ?></p>
      </div>
      <ul class="trip-list" data-lead="<?= count($tripLead) ?>" data-rest="<?= count($tripCards) - count($tripLead) ?>">
        <?php foreach ($tripCards as $l): [$tKind, $tTitle, $tText, $tCta] = $tripCopy[$l['kind']]; $tLead = in_array($l['kind'], $tripLead, true); ?>
        <li class="trip-card<?= $tLead ? ' is-lead' : '' ?>"><a href="<?= v2_e(v2_partner_href($l['program'], $l['url'], 'city-' . $slug . '-' . $l['kind'])) ?>" target="_blank" rel="sponsored nofollow noopener">
          <span class="trip-ic"><?= v2_ic($l['icon']) ?></span>
          <span class="trip-body">
            <span class="trip-kind"><?= v2_e($tKind) ?></span>
            <b class="trip-title"><?= v2_e($tTitle) ?></b>
            <span class="trip-text"><?= v2_e($tText) ?></span>
          </span>
          <span class="trip-foot">
            <span class="trip-cta"><?= v2_e($tCta) ?><?= v2_ic('arrow-right') ?></span>
            <span class="trip-by"><?= v2_te('Booked and paid on {partner}', ['partner' => $l['name']]) ?></span>
          </span>
          <?php if ($tLead): ?><?= v2_ic($l['icon'], 'trip-mark') ?><?php endif; ?>
        </a></li>
        <?php endforeach; ?>
      </ul>
      <p class="partner-note"><?= v2_te('These services are sold by our partners: you book and pay on their sites. Viaqui may earn a commission, at no extra cost to you.') ?></p>
    </div>
  </section>
  <?php endif; ?>
  <?php endif; ?>

  <section class="sec cn" id="nearby" aria-labelledby="nearby-h">
    <?php readfile(__DIR__ . '/includes/v2/topo.svg'); ?>
    <div class="wrap">
      <div class="sec-head">
        <div>
          <h2 id="nearby-h"><?= $sameRegion ? v2_te('More cities to explore in {country}', ['country' => $cityRegion]) : v2_te('More cities to explore') ?></h2>
          <p class="sec-sub"><?= v2_te('For a day trip, a weekend away or a second stop that goes well with a visit to {city}.', ['city' => $cityName]) ?></p>
        </div>
      </div>
      <ul class="ncities" data-reveal>
        <?php foreach ($nearbyCities as $i => $nc): ?>
        <li class="nc"><a href="<?= v2_e($nc['href']) ?>">
          <span class="nc-media"><?= $nc['photo'] ? v2_photo([$nc['photo'][0], $nc['photo'][1], $nc['photo'][2], '']) : v2_fallback($nc['name'], $i) ?></span>
          <span class="nc-body"><b><?= v2_e($nc['name']) ?><?= v2_ic('arrow-right') ?></b><small><?= $nc['count'] ? v2_e(v2_exp($nc['count'])) : v2_te('Discover the city') ?></small></span>
        </a></li>
        <?php endforeach; ?>
      </ul>
    </div>
  </section>
  <?php endif; ?>

  <!-- ============================== GHID LOCAL + CROSS-LINKS ============================== -->
  <section class="sec lg" id="local-guide" aria-labelledby="lg-h">
    <div class="wrap lg-grid">
      <article class="lg-main">
        <p class="kicker"><?= v2_te('Local guide') ?></p>
        <h2 class="lg-h" id="lg-h">
          <?php if ($citySeoTitle !== ''): ?>
            <?= v2_e($citySeoTitle) ?>
          <?php else: ?>
            <?= v2_te('What to do in {city}: ideas for a weekend, a family day or a free afternoon.', ['city' => $cityName]) ?>
          <?php endif; ?>
        </h2>

        <div class="lg-body">
          <?php if ($citySeoHtml !== ''): ?>
            <?= strip_tags($citySeoHtml, '<p><h2><h3><h4><strong><em><b><i><u><a><ul><ol><li><blockquote><br><span>') ?>
          <?php else: ?>
            <?php if ($cityDescription !== ''): ?>
              <p><?= nl2br(v2_e($cityDescription)) ?></p>
            <?php else: ?>
              <p><?= v2_te('This page gathers what you can see and book in {city} and around it: the main attractions, museums, tours, things to do with children and time outdoors.', ['city' => $cityName]) ?></p>
            <?php endif; ?>
            <p><?= v2_te('Each listing shows the price, how long it takes, who it suits and when it is available. After payment the ticket arrives by email as a QR code that is scanned at the entrance.') ?></p>
          <?php endif; ?>
        </div>

        <?php if (!empty($topCategories)): ?>
        <div class="lg-block">
          <h3><?= v2_te('Useful local searches') ?></h3>
          <div class="chips-links">
            <?php foreach ($topCategories as $cat): ?><a href="<?= v2_e($catLink($cat['slug'])) ?>"><?= v2_te('{what} in {city}', ['what' => $cat['name'], 'city' => $cityName]) ?></a><?php endforeach; ?>
          </div>
        </div>
        <?php endif; ?>

        <?php if ($intentLinks): ?>
        <div class="lg-block">
          <h3><?= v2_te('By mood') ?></h3>
          <div class="chips-links intents">
            <?php foreach ($intentLinks as [$intentSlug, $icon, $label]): ?><a href="/<?= v2_e($slug) ?>/<?= $intentSlug ?>"><?= v2_ic($icon) ?><?= v2_e($label) ?></a><?php endforeach; ?>
          </div>
        </div>
        <?php endif; ?>

        <?php if (!empty($cityFaqs)): ?>
        <div class="lg-block">
          <h3><?= v2_te('Frequent questions about {city}', ['city' => $cityName]) ?></h3>
          <?php foreach ($cityFaqs as $i => $f): ?>
          <details class="qa"<?= $i === 0 ? ' open' : '' ?>><summary><?= v2_e($f['q']) ?><span class="pm"><?= v2_ic('plus') ?></span></summary><p><?= v2_e($f['a']) ?></p></details>
          <?php endforeach; ?>
        </div>
        <?php endif; ?>
      </article>

      <aside class="lg-aside">
        <?php if (!empty($otherCities)): ?>
        <div class="ocities">
          <p class="kicker"><?= v2_te('Other cities') ?></p>
          <h3><?= v2_te('You can also look here') ?></h3>
          <ul>
            <?php foreach ($otherCities as $c): ?><li><a href="<?= v2_e($c['href']) ?>"><?= v2_e($c['name']) ?></a></li><?php endforeach; ?>
          </ul>
          <a class="sec-link" href="/cities"><?= v2_te('All destinations') ?><?= v2_ic('arrow-right') ?></a>
        </div>
        <?php endif; ?>

        <div class="owner">
          <?= $cityArches ?>
          <p class="kicker"><?= v2_te('For local operators') ?></p>
          <h3><?= v2_te('Do you run something in {city}?', ['city' => $cityName]) ?></h3>
          <p><?= v2_te('Your own page, QR tickets, online availability and a commission only on what you sell.') ?></p>
          <a class="btn btn-primary" href="/partners"><?= v2_te('List your venue') ?><?= v2_ic('arrow-right') ?></a>
        </div>
      </aside>
    </div>
  </section>

  <!-- ============================== NEWSLETTER ============================== -->
  <!-- One band for the newsletter, written for this city. It takes the place of the footer's own newsletter block
       (the footer is told below), and sits flush against the footer so the two read as one. -->
  <section class="cnl" aria-labelledby="nl-h">
    <div class="wrap vf-top">
      <div class="vf-say">
        <h2 class="vf-line" id="nl-h"><?= v2_t('Get ideas for {city}.<br>More places.<br>Same feeling.<br><em>Your way in.</em>', ['city' => v2_e($cityName)]) ?></h2>
      </div>
      <section class="vf-nl" aria-labelledby="nl-card-h">
        <p class="vf-k"><?= v2_te('Newsletter') ?></p>
        <h3 id="nl-card-h"><?= v2_te('Ideas for {city}, before you ask “what shall we do?”', ['city' => $cityName]) ?></h3>
        <p><?= v2_te('New places, routes and guides in and around {city}, ideas for the children and gift experiences. One email when there is something worth the trip.', ['city' => $cityName]) ?></p>
        <form class="vf-nl-form cnl-form" data-newsletter="city-<?= v2_e($slug) ?>" data-msg="nl-msg" data-ok="<?= v2_te('Done. Check your inbox to confirm.') ?>" data-err="<?= v2_te('We could not complete the subscription. Please try again.') ?>" data-keep>
          <label class="vf-field"><span><?= v2_te('Email') ?></span><input id="nl-email" name="email" type="email" required placeholder="<?= v2_te('you@example.com') ?>" autocomplete="email"></label>
          <input type="hidden" name="city" value="<?= v2_e($cityName) ?>">
          <button class="btn vf-nl-go" type="submit"><?= v2_te('Subscribe') ?><?= v2_ic('arrow-right') ?></button>
        </form>
        <p class="form-msg" id="nl-msg" role="status" hidden></p>
        <ul class="vf-nl-points">
          <li><?= v2_ic('check') ?><?= v2_te('No more than one email a week') ?></li>
          <li><?= v2_ic('check') ?><?= v2_te('Ideas near {city}', ['city' => $cityName]) ?></li>
          <li><?= v2_ic('check') ?><?= v2_te('Unsubscribe with one click') ?></li>
        </ul>
        <p class="vf-fine"><?= v2_t('By subscribing you agree to receive editorial and commercial messages from Viaqui. See the <a href="/privacy">privacy policy</a>.') ?></p>
      </section>
    </div>
  </section>

  <?php if ($gallery): ?>
  <!-- gallery lightbox -->
  <div class="lb" id="lb" role="dialog" aria-modal="true" aria-labelledby="lb-title" hidden>
    <div class="lb-top">
      <p class="lb-title" id="lb-title"><?= v2_e($gallery[0]['alt']) ?></p>
      <span class="lb-count" id="lb-count">1 / <?= count($gallery) ?></span>
      <button class="icon-btn" type="button" data-lb="close"><?= v2_ic('x') ?><span class="sr"><?= v2_te('Close the gallery') ?></span></button>
    </div>
    <figure class="lb-fig"><img id="lb-img" src="" alt=""></figure>
    <div class="lb-nav"<?= count($gallery) < 2 ? ' hidden' : '' ?>>
      <button class="rail-btn" type="button" data-lb="prev" aria-label="<?= v2_te('Previous photo') ?>"><?= v2_ic('arrow-left') ?></button>
      <button class="rail-btn" type="button" data-lb="next" aria-label="<?= v2_te('Next photo') ?>"><?= v2_ic('arrow-right') ?></button>
    </div>
  </div>
  <?php endif; ?>
</main>
<?php $v2FooterNewsletter = false; // this page carries its own newsletter band, right above the footer ?>
<?php include __DIR__ . '/includes/v2/footer.php'; ?>
