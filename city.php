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

// Pagination + filter parsing
$pageNum = max(1, (int) ($_GET['page'] ?? 1));
$categoryFilter = isset($_GET['category']) && is_string($_GET['category']) && preg_match('/^[a-z][a-z0-9-]+$/', $_GET['category']) ? $_GET['category'] : null;
$searchQuery = isset($_GET['q']) && is_string($_GET['q']) ? mb_substr(trim($_GET['q']), 0, 80) : '';

// Price filter — whitelisted (same set as category page)
$priceMaxAllowed = [50, 100, 200, 500];
$maxPrice = (isset($_GET['max_price']) && in_array((int) $_GET['max_price'], $priceMaxAllowed, true))
    ? (int) $_GET['max_price']
    : null;

// Sort — whitelisted to API-supported values
$sortOptions = ['recommended' => 'Recomandate', 'price_asc' => 'Preț crescător', 'price_desc' => 'Preț descrescător', 'name_asc' => 'Alfabetic', 'date_asc' => 'Data evenimentului'];
$sort = (isset($_GET['sort']) && is_string($_GET['sort']) && isset($sortOptions[$_GET['sort']])) ? $_GET['sort'] : 'recommended';

// Parent categories for the filters, popular chips, rotating hero cards and the interest grid (max 12).
$topCategories = array_slice($V2NAV['categories'], 0, 12);
$catNameOf = function (string $catSlug) use ($V2NAV): string {
    return $V2NAV['categoryBySlug'][$catSlug]['name'] ?? ucwords(str_replace('-', ' ', $catSlug));
};

// Events, activities and attractions are INDEPENDENT upstream calls, so they run CONCURRENTLY
// (curl_multi via api_cached_many): one round-trip of wall-time on a cold cache.
$evParams = ['city' => $slug, 'page' => $pageNum, 'per_page' => 18, 'time_scope' => 'upcoming'];
if ($categoryFilter) $evParams['category'] = $categoryFilter;
if ($searchQuery !== '') $evParams['search'] = $searchQuery;
if ($maxPrice !== null) $evParams['max_price'] = $maxPrice;
if ($sort !== 'recommended') $evParams['sort'] = $sort;

$actParams = ['city' => $slug, 'page' => $pageNum, 'per_page' => 24];
if ($categoryFilter) $actParams['category'] = $categoryFilter;
if ($searchQuery !== '') $actParams['search'] = $searchQuery;
if ($maxPrice !== null) $actParams['max_price_ron'] = $maxPrice;
if ($sort === 'price_asc') $actParams['sort'] = 'cheapest';

$cacheSuffix = ($categoryFilter ?? 'all') . '_' . md5($searchQuery) . "_mp{$maxPrice}_s{$sort}_p{$pageNum}";
$listings = api_cached_many([
    'events' => [
        'key'      => "city_events_{$slug}_{$cacheSuffix}",
        'endpoint' => '/events',
        'params'   => $evParams,
        'ttl'      => 300,
    ],
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
]);

$eventsResp = $listings['events'] ?? ['data' => []];
$events = $eventsResp['data'] ?? [];
$evPagination = $eventsResp['meta'] ?? ['current_page' => 1, 'last_page' => 1, 'total' => is_array($events) ? count($events) : 0];
if (!is_array($events)) $events = [];

$actResp = $listings['activities'] ?? ['data' => []];
$activities = $actResp['data']['items'] ?? [];
if (!is_array($activities)) $activities = [];
$actPagination = $actResp['data']['pagination'] ?? ['last_page' => 1, 'total' => count($activities)];

// Unified card list — activities first (primary content), then events.
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
        'url'         => $n['href'],
        'cta'         => 'Vezi activitatea',
    ];
}
foreach ($events as $ev) {
    if (!is_array($ev) || empty($ev['slug'])) {
        continue;
    }
    $cards[] = [
        'title'       => navEventTitle($ev),
        'cat'         => navEventCategoryLabel($ev),
        'image'       => v2_media_url($ev['cover_image_url'] ?? $ev['image_url'] ?? null),
        'dur'         => '',
        'price_cents' => isset($ev['cheapest_price_cents']) ? (int) $ev['cheapest_price_cents'] : null,
        'url'         => '/bilete/' . $ev['slug'],
        'cta'         => 'Vezi bilete',
    ];
}

$pagination = [
    'current_page' => $pageNum,
    'last_page'    => max((int) ($evPagination['last_page'] ?? 1), (int) ($actPagination['last_page'] ?? 1)),
    'total'        => (int) ($evPagination['total'] ?? count($events)) + (int) ($actPagination['total'] ?? count($activities)),
];

// Locations with online tickets in this city (access tickets, experiences, packages). Section hidden when none.
$cityLocations = [];
foreach ((array) (($listings['locations']['success'] ?? false) ? ($listings['locations']['data']['items'] ?? []) : []) as $l) {
    if (!is_array($l) || empty($l['slug']) || navFlatName($l['name'] ?? '') === '') {
        continue;
    }
    $cityLocations[] = [
        'href' => '/locatie/' . $l['slug'],
        'name' => navFlatName($l['name']),
        'image' => v2_media_url($l['cover_image'] ?? null),
        'meta' => trim(navFlatName($l['category']['name'] ?? '') . (!empty($l['min_price_cents']) ? ' · de la ' . v2_thousands((int) round($l['min_price_cents'] / 100)) . ' lei' : ''), ' ·'),
        'lodging' => !empty($l['has_lodging']),
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
        <p class="kicker"><?= $gygPromote ? 'Activități și tururi' : 'Extra · prin partenerii noștri' ?></p>
        <h2 id="gyg-h"><?= $gygPromote ? 'Activități și tururi în ' : 'Mai multe activități și tururi în ' ?><?= v2_e($cityName) ?></h2>
        <p>Selecție de tururi ghidate, experiențe și activități disponibile prin GetYourGuide.</p>
      </div>
      <div id="gyg-mount"
        data-gyg-href="https://widget.getyourguide.com/default/activities.frame"
        data-gyg-location-id="<?= v2_e($gygCityId) ?>"
        data-gyg-locale-code="ro-RO"
        data-gyg-widget="activities"
        data-gyg-number-of-items="40"
        data-gyg-partner-id="<?= v2_e($gygPartnerId) ?>"
        aria-label="Activități GetYourGuide pentru <?= v2_e($cityName) ?>">
        <span class="gyg-powered">Powered by <a target="_blank" rel="sponsored noopener" href="<?= v2_e($gygUrl) ?>">GetYourGuide</a></span>
      </div>
    </div>
  </section>
    <?php
};

// ---- Links (only validated parameters are carried over; filter links land on the listing) ----
$baseGet = array_filter([
    'q'         => $searchQuery,
    'category'  => (string) $categoryFilter,
    'max_price' => $maxPrice !== null ? (string) $maxPrice : '',
    'sort'      => $sort === 'recommended' ? '' : $sort,
], fn ($v) => $v !== '');
$cityUrl = function (array $over = []) use ($baseGet, $slug): string {
    $p = array_filter(array_merge($baseGet, $over), fn ($v) => $v !== '' && $v !== null);
    return '/' . $slug . ($p ? '?' . http_build_query($p) : '') . '#activitati';
};
$catLink = fn (string $catSlug): string => '/' . $slug . '?category=' . rawurlencode($catSlug) . '#activitati';

// Active filter chips
$activeChips = [];
if ($searchQuery !== '') {
    $activeChips[] = ['„' . $searchQuery . '”', $cityUrl(['q' => ''])];
}
if ($categoryFilter) {
    $activeChips[] = [$catNameOf($categoryFilter), $cityUrl(['category' => ''])];
}
if ($maxPrice !== null) {
    $activeChips[] = ['Sub ' . $maxPrice . ' lei', $cityUrl(['max_price' => ''])];
}
if ($sort !== 'recommended') {
    $activeChips[] = ['Sortat: ' . $sortOptions[$sort], $cityUrl(['sort' => ''])];
}

$priceHtml = function (?int $cents): string {
    if ($cents === null) {
        return '';
    }
    if ($cents === 0) {
        return '<span class="xp-price"><b>Gratuit</b></span>';
    }
    return '<span class="xp-price">de la<b>' . v2_e(number_format($cents / 100, 0, ',', '.')) . ' lei</b></span>';
};

// ============================================================
// SEO setup
// ============================================================
$pageTitleRaw = 'Activități în ' . $cityName . ' — bilete online | bilete.online';
$pageDescription = 'Descoperă activități în ' . $cityName . ': escape rooms, muzee, parcuri, experiențe pentru copii, tururi și aventură. Rezervi online cu bilet QR.';
$canonicalUrl = SITE_URL . '/' . $slug;
$ogImage = $cityCover
    ? (str_starts_with($cityCover, 'http') ? $cityCover : STORAGE_URL . '/' . ltrim($cityCover, '/'))
    : (SITE_URL . '/assets/images/og-default.jpg');

$breadcrumbs = [
    ['name' => 'Acasă', 'url' => SITE_URL . '/'],
    ['name' => 'Orașe', 'url' => SITE_URL . '/orase'],
    ['name' => $cityName, 'url' => $canonicalUrl],
];

// JSON-LD CollectionPage with City entity
$itemListElements = [];
foreach (array_slice($cards, 0, 10) as $i => $card) {
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
    'name' => 'Activități în ' . $cityName,
    'url' => $canonicalUrl,
    'inLanguage' => 'ro-RO',
    'about' => [
        '@type' => 'City',
        'name' => $cityName,
        'address' => [
            '@type' => 'PostalAddress',
            'addressLocality' => $cityName,
            'addressRegion' => $countyName ?: $regionName,
            'addressCountry' => 'RO',
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
    foreach (['Escape rooms', 'Muzee & expoziții', 'Parcuri de distracții', 'Parcuri de aventură'] as $label) {
        $wheelItems[] = ['label' => $label, 'image' => null, 'srcset' => '', 'href' => '/' . $slug];
    }
}

// Other cities: the same region first (the nearest we can tell without coordinates), then the rest.
$otherCitiesAll = array_values(array_filter($V2NAV['citiesList'], fn ($c) => $c['slug'] !== $slug));
$cityRegion = $V2NAV['cities'][$slug]['region'] ?? $regionName;
$sameRegion = $cityRegion !== '' ? array_values(array_filter($otherCitiesAll, fn ($c) => $c['region'] === $cityRegion)) : [];
$nearbyCities = array_slice(array_merge($sameRegion, array_values(array_filter($otherCitiesAll, fn ($c) => !in_array($c, $sameRegion, true)))), 0, 5);
$otherCities = array_slice($otherCitiesAll, 0, 8);

// Editorial guides (Inspiration). Section hidden when none.
$cityGuides = array_slice($V2NAV['guides'], 0, 3);

// Traveler types — city-scoped search links (city.php handles ?q), so these
// always resolve to real filtered results without needing dedicated routes.
$travelerTypes = [
    ['icon' => 'users-three',   'title' => 'Pentru familii', 'desc' => 'Activități sigure, interactive și ușor de planificat cu copiii.',   'href' => '/' . $slug . '?q=' . rawurlencode('copii') . '#activitati'],
    ['icon' => 'heart',         'title' => 'Pentru cupluri', 'desc' => 'Tururi, experiențe cadou, date night și activități de seară.',       'href' => '/' . $slug . '?q=' . rawurlencode('cuplu') . '#activitati'],
    ['icon' => 'cloud-rain',    'title' => 'Când plouă',     'desc' => 'Muzee, ateliere, escape rooms și experiențe indoor.',                'href' => '/' . $slug . '?q=' . rawurlencode('indoor') . '#activitati'],
    ['icon' => 'castle-turret', 'title' => 'Pentru turiști', 'desc' => 'Atracții principale, tururi ghidate și experiențe de primă vizită.', 'href' => '/' . $slug . '?q=' . rawurlencode('tur') . '#activitati'],
];

// Intent hubs (connect the city page to the programmatic intent pages).
$intentLinks = [
    ['activitati-azi',       'clock',       'azi în ' . $cityName],
    ['activitati-weekend',   'sun',         'weekend'],
    ['activitati-gratuite',  'gift',        'gratuite'],
    ['activitati-copii',     'users-three', 'copii'],
    ['activitati-indoor',    'cloud-rain',  'indoor'],
    ['activitati-romantice', 'heart',       'romantice'],
];

$cityArches = '<svg class="deco-arches" viewBox="0 0 400 400" aria-hidden="true" focusable="false"><path d="M40 400V200a160 160 0 0 1 320 0v200"/><path d="M90 400V200a110 110 0 0 1 220 0v200"/><path d="M140 400V200a60 60 0 0 1 120 0v200"/></svg>';

// An API outage would otherwise be cached as a half-empty page for five minutes.
if (empty($V2NAV['categories'])) {
    $skipPageCache = true;
}

$v2Styles = ['city.css'];
$v2Scripts = ['city.js'];
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
        <nav class="crumbs" aria-label="Breadcrumb">
          <?php foreach ($breadcrumbs as $i => $bc): ?>
            <?php if ($i > 0): ?><span aria-hidden="true">/</span><?php endif; ?>
            <?php if ($i < count($breadcrumbs) - 1): ?><a href="<?= v2_e(substr($bc['url'], strlen(SITE_URL)) ?: '/') ?>"><?= v2_e($bc['name']) ?></a><?php else: ?><span aria-current="page"><?= v2_e($bc['name']) ?></span><?php endif; ?>
          <?php endforeach; ?>
        </nav>
        <h1 class="ch-h" id="ch-h"><span class="ch-pre">Lucruri de făcut în</span> <span class="ch-city"><?= v2_e($cityName) ?></span></h1>
        <p class="ch-lead">
          <?php if ($cityDescription !== ''): ?>
            <?= v2_e($cityDescription) ?>
          <?php else: ?>
            <?= v2_e($cityName) ?> combină atracții, muzee, tururi, experiențe de familie, escape rooms și activități outdoor. Alege activități pentru weekend, bilete pentru atracții, tururi culturale sau experiențe cadou — rezervi online cu bilet QR.
          <?php endif; ?>
        </p>
        <form class="ch-search" action="/<?= v2_e($slug) ?>#activitati" method="get" role="search" aria-label="Caută activități în <?= v2_e($cityName) ?>">
          <?= v2_ic('magnifying-glass') ?>
          <label class="sr" for="city-search">Caută activități în <?= v2_e($cityName) ?></label>
          <input id="city-search" name="q" type="search" placeholder="Caută activități în <?= v2_e($cityName) ?>: copii, muzee, escape rooms..." autocomplete="off">
          <button class="btn btn-primary" type="submit">Caută</button>
        </form>
        <?php if (!empty($topCategories)): ?>
        <div class="ch-pop">
          <span>Populare:</span>
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
            <span>Galerie foto</span><b><?= count($gallery) ?></b>
          </button>
          <?php endif; ?>
        </div>
        <!-- Categorii populare: one card at a time swings in along the arch, then out; cycles continuously. -->
        <div class="orbit" data-orbit role="group" aria-label="Categorii populare în <?= v2_e($cityName) ?>">
          <?php foreach ($wheelItems as $i => $w): ?>
          <a class="oc<?= $i === 0 ? ' is-on' : '' ?>" href="<?= v2_e($w['href']) ?>">
            <span class="oc-media"><?= $w['image'] ? v2_photo([$w['image'], 640, 800, ''], $w['srcset'] ? ' srcset="' . v2_e($w['srcset']) . '" sizes="170px"' : '') : v2_fallback($w['label'], $i) ?></span>
            <span class="oc-name"><?= v2_e($w['label']) ?></span>
            <span class="oc-meta">în <?= v2_e($cityName) ?></span>
          </a>
          <?php endforeach; ?>
        </div>
      </div>
    </div>
  </section>
  <div id="hdr-sentinel" aria-hidden="true"></div>

  <!-- ============================== STICKY SECTION NAV ============================== -->
  <nav class="cnav" aria-label="Secțiunile paginii">
    <div class="wrap cnav-in">
      <a href="#activitati" aria-current="true">Top activități</a>
      <a href="#interese">Explorează după interes</a>
      <?php if (!empty($cityAttractions)): ?><a href="#attractions">Atracții</a><?php endif; ?>
      <?php if (!empty($cityGuides)): ?><a href="#guides">Ghiduri</a><?php endif; ?>
      <?php if (!empty($nearbyCities)): ?><a href="#nearby">Aproape de <?= v2_e($cityName) ?></a><?php endif; ?>
      <a href="#ghid-local">FAQ</a>
    </div>
  </nav>

  <!-- ============================== GETYOURGUIDE (promoted: cities without own listings) ============================== -->
  <?php if ($gygPromote) { $renderGygSection(); } ?>

  <!-- ============================== ACTIVITIES + EVENTS ============================== -->
  <section class="cl" id="activitati" aria-labelledby="cl-h">
    <div class="wrap cl-head">
      <h2 id="cl-h">Top activități în <?= v2_e($cityName) ?></h2>
      <p class="cl-count"><b><?= (int) $pagination['total'] ?></b> <?= (int) $pagination['total'] === 1 ? 'rezultat' : 'rezultate' ?></p>
    </div>

    <div class="ctb">
      <div class="wrap">
        <div class="ctb-in">
          <form class="ctb-search" action="/<?= v2_e($slug) ?>#activitati" method="get" role="search" aria-label="Caută în <?= v2_e($cityName) ?>">
            <?= v2_ic('magnifying-glass') ?>
            <label class="sr" for="cl-q">Caută în <?= v2_e($cityName) ?></label>
            <input id="cl-q" name="q" type="search" value="<?= v2_e($searchQuery) ?>" placeholder="Caută în <?= v2_e($cityName) ?>…" autocomplete="off">
            <?php foreach ($baseGet as $k => $v): if ($k === 'q') continue; ?><input type="hidden" name="<?= v2_e($k) ?>" value="<?= v2_e($v) ?>"><?php endforeach; ?>
            <button class="btn btn-primary ctb-go" type="submit"><span>Caută</span><?= v2_ic('arrow-right') ?></button>
          </form>

          <div class="ctb-dds">
            <details class="dd">
              <summary class="dd-btn<?= $categoryFilter ? ' is-set' : '' ?>"><?= v2_e($categoryFilter ? $catNameOf($categoryFilter) : 'Categorie') ?><?= v2_ic('caret-down') ?></summary>
              <div class="dd-pop">
                <ul>
                  <li><a href="<?= v2_e($cityUrl(['category' => ''])) ?>"<?= !$categoryFilter ? ' aria-current="true"' : '' ?>>Toate categoriile</a></li>
                  <?php foreach ($topCategories as $c): ?><li><a href="<?= v2_e($cityUrl(['category' => $c['slug']])) ?>"<?= $categoryFilter === $c['slug'] ? ' aria-current="true"' : '' ?>><?= v2_e($c['name']) ?></a></li><?php endforeach; ?>
                </ul>
              </div>
            </details>
            <details class="dd">
              <summary class="dd-btn<?= $maxPrice !== null ? ' is-set' : '' ?>"><?= $maxPrice !== null ? 'Sub ' . $maxPrice . ' lei' : 'Preț' ?><?= v2_ic('caret-down') ?></summary>
              <div class="dd-pop">
                <ul>
                  <li><a href="<?= v2_e($cityUrl(['max_price' => ''])) ?>"<?= $maxPrice === null ? ' aria-current="true"' : '' ?>>Orice preț</a></li>
                  <?php foreach ($priceMaxAllowed as $cap): ?><li><a href="<?= v2_e($cityUrl(['max_price' => $cap])) ?>"<?= $maxPrice === $cap ? ' aria-current="true"' : '' ?>>Sub <?= $cap ?> lei</a></li><?php endforeach; ?>
                </ul>
              </div>
            </details>
            <details class="dd">
              <summary class="dd-btn<?= $sort !== 'recommended' ? ' is-set' : '' ?>"><?= v2_e($sort !== 'recommended' ? $sortOptions[$sort] : 'Sortare') ?><?= v2_ic('caret-down') ?></summary>
              <div class="dd-pop is-right">
                <ul>
                  <?php foreach ($sortOptions as $value => $label): ?><li><a href="<?= v2_e($cityUrl(['sort' => $value === 'recommended' ? '' : $value])) ?>"<?= $sort === $value ? ' aria-current="true"' : '' ?>><?= v2_e($label) ?></a></li><?php endforeach; ?>
                </ul>
              </div>
            </details>
          </div>

          <button class="ctb-open" type="button" data-sheet-open aria-haspopup="dialog" aria-controls="cl-sheet" aria-expanded="false"><?= v2_ic('list') ?>Filtre<?php if ($activeChips): ?><span class="ctb-n"><?= count($activeChips) ?></span><?php endif; ?></button>
        </div>

        <?php if ($activeChips): ?>
        <ul class="cl-active" aria-label="Filtre active">
          <?php foreach ($activeChips as [$label, $href]): ?><li><a class="achip" href="<?= v2_e($href) ?>"><?= v2_e($label) ?><?= v2_ic('x') ?><span class="sr"> (elimină)</span></a></li><?php endforeach; ?>
          <li><a class="aclear" href="/<?= v2_e($slug) ?>#activitati">Șterge tot</a></li>
        </ul>
        <?php endif; ?>
      </div>
    </div>

    <!-- phone filter sheet (shown inline when JavaScript is off) -->
    <div class="sheet" id="cl-sheet" role="dialog" aria-modal="true" aria-labelledby="cl-sheet-h">
      <div class="sheet-panel">
        <div class="sheet-top">
          <h2 id="cl-sheet-h">Filtre</h2>
          <button class="icon-btn" type="button" data-sheet-close><?= v2_ic('x') ?><span class="sr">Închide filtrele</span></button>
        </div>
        <div class="sheet-body">
          <section class="fgroup">
            <h3 class="flabel">Categorie</h3>
            <div class="fchips">
              <a class="fchip" href="<?= v2_e($cityUrl(['category' => ''])) ?>"<?= !$categoryFilter ? ' aria-current="true"' : '' ?>>Toate</a>
              <?php foreach ($topCategories as $c): ?><a class="fchip" href="<?= v2_e($cityUrl(['category' => $c['slug']])) ?>"<?= $categoryFilter === $c['slug'] ? ' aria-current="true"' : '' ?>><?= v2_e($c['name']) ?></a><?php endforeach; ?>
            </div>
          </section>
          <section class="fgroup">
            <h3 class="flabel">Preț maxim</h3>
            <div class="fchips">
              <a class="fchip" href="<?= v2_e($cityUrl(['max_price' => ''])) ?>"<?= $maxPrice === null ? ' aria-current="true"' : '' ?>>Orice</a>
              <?php foreach ($priceMaxAllowed as $cap): ?><a class="fchip" href="<?= v2_e($cityUrl(['max_price' => $cap])) ?>"<?= $maxPrice === $cap ? ' aria-current="true"' : '' ?>>Sub <?= $cap ?> lei</a><?php endforeach; ?>
            </div>
          </section>
          <section class="fgroup">
            <h3 class="flabel">Sortare</h3>
            <div class="fchips">
              <?php foreach ($sortOptions as $value => $label): ?><a class="fchip" href="<?= v2_e($cityUrl(['sort' => $value === 'recommended' ? '' : $value])) ?>"<?= $sort === $value ? ' aria-current="true"' : '' ?>><?= v2_e($label) ?></a><?php endforeach; ?>
            </div>
          </section>
        </div>
      </div>
    </div>

    <div class="wrap">
      <?php if (empty($cards)): ?>
      <div class="cl-empty">
        <h3>
          <?php if ($categoryFilter): ?>
            Nicio activitate din această categorie în <?= v2_e($cityName) ?>.
          <?php else: ?>
            Încă nu sunt activități listate aici.
          <?php endif; ?>
        </h3>
        <p>Reveniți în curând — operatorii își vor adăuga curând experiențele.</p>
        <div class="cl-empty-cta">
          <a class="btn btn-light" href="/categorii">Vezi alte categorii</a>
          <?php if ($activeChips): ?><a class="btn btn-ghost" href="/<?= v2_e($slug) ?>#activitati">Șterge filtrele</a><?php endif; ?>
        </div>
        <svg class="cl-empty-line" viewBox="0 590 3240 310" aria-hidden="true" focusable="false"><use href="#drum-g"/></svg>
      </div>
      <?php else: ?>
      <ul class="xp-grid" data-reveal>
        <?php foreach ($cards as $i => $card): ?>
        <li class="xp">
          <a href="<?= v2_e($card['url']) ?>">
            <span class="xp-media"><?= $card['image'] ? v2_photo([$card['image'], 0, 0, '']) : v2_fallback($card['title'], $i) ?></span>
            <span class="xp-body">
              <span class="xp-cat"><?= v2_e($card['cat']) ?></span>
              <span class="xp-title"><?= v2_e($card['title']) ?></span>
              <span class="xp-meta"><?php if ($card['dur']): ?><span><?= v2_ic('clock') ?><?= v2_e($card['dur']) ?></span><?php endif; ?></span>
              <span class="xp-foot"><span class="xp-go"><?= v2_e($card['cta']) ?><?= v2_ic('arrow-right') ?></span><?= $priceHtml($card['price_cents']) ?></span>
            </span>
          </a>
        </li>
        <?php endforeach; ?>
      </ul>

      <?php
      $current = (int) $pagination['current_page'];
      $last = max(1, (int) $pagination['last_page']);
      if ($last > 1):
          $start = max(1, $current - 3);
          $end = min($last, $start + 6);
          $start = max(1, $end - 6);
      ?>
      <nav class="pager" aria-label="Pagini">
        <?php if ($current > 1): ?><a class="pg-step" href="<?= v2_e($cityUrl(['page' => $current - 1 > 1 ? $current - 1 : ''])) ?>" rel="prev"><?= v2_ic('arrow-left') ?>Anterior</a><?php endif; ?>
        <?php for ($p = $start; $p <= $end; $p++): ?>
          <?php if ($p === $current): ?><span aria-current="page"><?= $p ?></span><?php else: ?><a href="<?= v2_e($cityUrl(['page' => $p > 1 ? $p : ''])) ?>"><?= $p ?></a><?php endif; ?>
        <?php endfor; ?>
        <?php if ($current < $last): ?><a class="pg-step" href="<?= v2_e($cityUrl(['page' => $current + 1])) ?>" rel="next">Următor<?= v2_ic('arrow-right') ?></a><?php endif; ?>
      </nav>
      <?php endif; ?>
      <?php endif; ?>
    </div>
  </section>

  <!-- ============================== GETYOURGUIDE ("extra" slot, below our own listings) ============================== -->
  <?php if ($gygWidgetEnabled && !$gygPromote) { $renderGygSection(); } ?>

  <!-- ============================== EXPLOREAZĂ DUPĂ INTERES (categorii + traveler) ============================== -->
  <section class="sec ci" id="interese" aria-labelledby="ci-h">
    <div class="wrap ci-grid">
      <div>
        <div class="sec-head">
          <div>
            <h2 id="ci-h">Explorează după interes</h2>
            <p class="sec-sub">Categorii utile ca să găsești mai repede ce cauți în <?= v2_e($cityName) ?>.</p>
          </div>
        </div>
        <?php if (!empty($topCategories)): ?>
        <ul class="ctiles" data-reveal>
          <?php foreach ($topCategories as $cat): ?>
          <li>
            <a class="ctile" href="<?= v2_e($catLink($cat['slug'])) ?>">
              <span class="ctile-media"><?= $cat['thumb'] ? v2_photo([$cat['thumb'], 0, 0, '']) : v2_fallback($cat['name']) ?></span>
              <span class="ctile-text"><b><?= v2_e($cat['name']) ?></b><?php if ($cat['count'] > 0): ?><small><?= v2_num($cat['count'], 'activitate', 'activități') ?></small><?php endif; ?></span>
              <?= v2_ic('arrow-right') ?>
            </a>
          </li>
          <?php endforeach; ?>
        </ul>
        <?php endif; ?>
      </div>

      <aside class="styles" aria-labelledby="styles-h">
        <?= $cityArches ?>
        <h3 id="styles-h">Alege după stilul tău</h3>
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
  <section class="sec attr" id="locatii" aria-labelledby="loc-h">
    <div class="wrap">
      <div class="sec-head">
        <div>
          <h2 id="loc-h">Locații cu bilete online în <?= v2_e($cityName) ?></h2>
          <p class="sec-sub">Îți iei biletul de intrare și experiențele de acolo dinainte, într-o singură comandă.</p>
        </div>
        <div class="sec-tools">
          <a class="sec-link" href="/<?= v2_e($slug) ?>/locatii">Toate locațiile<?= v2_ic('arrow-right') ?></a>
          <?php if (count($cityLocations) > 2): ?>
          <div class="rail-btns" data-for="loc-rail">
            <button class="rail-btn" type="button" data-dir="-1" aria-label="Locațiile anterioare"><?= v2_ic('arrow-left') ?></button>
            <button class="rail-btn" type="button" data-dir="1" aria-label="Locațiile următoare"><?= v2_ic('arrow-right') ?></button>
          </div>
          <?php endif; ?>
        </div>
      </div>
      <ul class="rail" id="loc-rail">
        <?php foreach ($cityLocations as $li => $lc): ?>
        <li class="at"><a href="<?= v2_e($lc['href']) ?>">
          <span class="at-media"><?= $lc['image'] ? v2_photo([$lc['image'], 0, 0, '']) : v2_fallback($lc['name'], $li) ?><?php if ($lc['lodging']): ?><span class="at-badge">Cazare</span><?php endif; ?></span>
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
          <h2 id="attr-h">Atracții de neratat în <?= v2_e($cityName) ?></h2>
          <p class="sec-sub">Descoperă locurile care definesc orașul și vezi activitățile disponibile în jurul lor.</p>
        </div>
        <div class="sec-tools">
          <a class="sec-link" href="/<?= v2_e($slug) ?>/atractii">Toate atracțiile<?= v2_ic('arrow-right') ?></a>
          <div class="rail-btns" data-for="attr-rail">
            <button class="rail-btn" type="button" data-dir="-1" aria-label="Atracțiile anterioare"><?= v2_ic('arrow-left') ?></button>
            <button class="rail-btn" type="button" data-dir="1" aria-label="Atracțiile următoare"><?= v2_ic('arrow-right') ?></button>
          </div>
        </div>
      </div>
      <ul class="rail" id="attr-rail">
        <?php foreach ($cityAttractions as $at): ?>
        <li class="at"><a href="<?= v2_e($at['href']) ?>">
          <span class="at-media"><?= $at['image'] ? v2_photo([$at['image'], 0, 0, '']) : v2_fallback($at['name']) ?><?php if ($at['count'] > 0): ?><span class="at-badge"><?= v2_num($at['count'], 'activitate', 'activități') ?></span><?php endif; ?></span>
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
        <h2 id="guides-h">Ghiduri și idei pentru <?= v2_e($cityName) ?></h2>
        <a class="sec-link" href="/ghiduri">Toate ghidurile<?= v2_ic('arrow-right') ?></a>
      </div>
      <ul class="cg-grid" data-reveal>
        <?php foreach ($cityGuides as $g): ?>
        <li class="ia"><a href="<?= v2_e($g['href']) ?>">
          <span class="ia-media"><?= $g['cover'] ? v2_photo($g['cover']) : v2_fallback($g['slug']) ?></span>
          <span class="ia-text">
            <span class="ia-cat"><?= v2_e($g['category'] !== '' ? $g['category'] : 'Ghid') ?></span>
            <h3><?= v2_e($g['title']) ?></h3>
            <?php if ($g['excerpt'] !== ''): ?><p><?= v2_e($g['excerpt']) ?></p><?php endif; ?>
            <span class="ia-more">Citește ghidul<?= v2_ic('arrow-right') ?></span>
          </span>
        </a></li>
        <?php endforeach; ?>
      </ul>
    </div>
  </section>
  <?php endif; ?>

  <!-- ============================== ORAȘE APROPIATE ============================== -->
  <?php if (!empty($nearbyCities)): ?>
  <section class="sec cn" id="nearby" aria-labelledby="nearby-h">
    <?php readfile(__DIR__ . '/includes/v2/topo.svg'); ?>
    <div class="wrap">
      <div class="sec-head">
        <div>
          <h2 id="nearby-h">Alte orașe de explorat lângă <?= v2_e($cityName) ?></h2>
          <p class="sec-sub">Pentru excursii de o zi, experiențe de weekend sau activități care merg bine împreună cu o vizită în <?= v2_e($cityName) ?>.</p>
        </div>
      </div>
      <ul class="ncities" data-reveal>
        <?php foreach ($nearbyCities as $i => $nc): ?>
        <li class="nc"><a href="<?= v2_e($nc['href']) ?>">
          <span class="nc-media"><?= $nc['photo'] ? v2_photo([$nc['photo'][0], $nc['photo'][1], $nc['photo'][2], '']) : v2_fallback($nc['name'], $i) ?></span>
          <span class="nc-body"><b><?= v2_e($nc['name']) ?><?= v2_ic('arrow-right') ?></b><small><?= $nc['count'] ? v2_exp($nc['count']) : 'Activități și experiențe locale' ?></small></span>
        </a></li>
        <?php endforeach; ?>
      </ul>
    </div>
  </section>
  <?php endif; ?>

  <!-- ============================== GHID LOCAL + CROSS-LINKS ============================== -->
  <section class="sec lg" id="ghid-local" aria-labelledby="lg-h">
    <div class="wrap lg-grid">
      <article class="lg-main">
        <p class="kicker">Ghid local</p>
        <h2 class="lg-h" id="lg-h">
          <?php if ($citySeoTitle !== ''): ?>
            <?= v2_e($citySeoTitle) ?>
          <?php else: ?>
            Ce să faci în <?= v2_e($cityName) ?>: idei pentru weekend, familie sau o zi liberă.
          <?php endif; ?>
        </h2>

        <div class="lg-body">
          <?php if ($citySeoHtml !== ''): ?>
            <?= strip_tags($citySeoHtml, '<p><h2><h3><h4><strong><em><b><i><u><a><ul><ol><li><blockquote><br><span>') ?>
          <?php else: ?>
            <?php if ($cityDescription !== ''): ?>
              <p><?= nl2br(v2_e($cityDescription)) ?></p>
            <?php else: ?>
              <p><?= v2_e($cityName) ?> oferă o gamă largă de activități pentru weekend sau o zi liberă: poți combina o plimbare în centru cu un escape room, o vizită la muzee, o activitate pentru copii sau o ieșire de aventură în apropiere.</p>
            <?php endif; ?>
            <p>Această pagină adună activitățile disponibile în oraș și în zona apropiată, cu informații utile despre preț, durată, public potrivit și disponibilitate. După plată, biletul ajunge pe email cu cod QR și poate fi scanat direct la intrare.</p>
          <?php endif; ?>
        </div>

        <?php if (!empty($topCategories)): ?>
        <div class="lg-block">
          <h3>Căutări locale utile</h3>
          <div class="chips-links">
            <?php foreach ($topCategories as $cat): ?><a href="<?= v2_e($catLink($cat['slug'])) ?>"><?= v2_e(mb_strtolower($cat['name'])) ?> <?= v2_e($cityName) ?></a><?php endforeach; ?>
          </div>
        </div>
        <?php endif; ?>

        <div class="lg-block">
          <h3>După ce ai chef</h3>
          <div class="chips-links intents">
            <?php foreach ($intentLinks as [$intentSlug, $icon, $label]): ?><a href="/<?= v2_e($slug) ?>/<?= $intentSlug ?>"><?= v2_ic($icon) ?><?= v2_e($label) ?></a><?php endforeach; ?>
          </div>
        </div>

        <?php if (!empty($cityFaqs)): ?>
        <div class="lg-block">
          <h3>Întrebări frecvente despre <?= v2_e($cityName) ?></h3>
          <?php foreach ($cityFaqs as $i => $f): ?>
          <details class="qa"<?= $i === 0 ? ' open' : '' ?>><summary><?= v2_e($f['q']) ?><span class="pm"><?= v2_ic('plus') ?></span></summary><p><?= v2_e($f['a']) ?></p></details>
          <?php endforeach; ?>
        </div>
        <?php endif; ?>
      </article>

      <aside class="lg-aside">
        <?php if (!empty($otherCities)): ?>
        <div class="ocities">
          <p class="kicker">Alte orașe</p>
          <h3>Poți căuta și aici</h3>
          <ul>
            <?php foreach ($otherCities as $c): ?><li><a href="<?= v2_e($c['href']) ?>"><?= v2_e($c['name']) ?></a></li><?php endforeach; ?>
          </ul>
          <a class="sec-link" href="/orase">Toate orașele<?= v2_ic('arrow-right') ?></a>
        </div>
        <?php endif; ?>

        <div class="owner">
          <?= $cityArches ?>
          <p class="kicker">Pentru operatori locali</p>
          <h3>Ai o activitate în <?= v2_e($cityName) ?>?</h3>
          <p>Pagină dedicată, bilete QR, disponibilitate online și comision 2%.</p>
          <a class="btn btn-primary" href="/parteneri">Listează activitatea<?= v2_ic('arrow-right') ?></a>
        </div>
      </aside>
    </div>
  </section>

  <!-- ============================== NEWSLETTER ============================== -->
  <section class="nl" aria-labelledby="nl-h">
    <div class="wrap nl-grid">
      <div class="nl-copy">
        <p class="kicker">Newsletter bilete.online</p>
        <h2 id="nl-h">Primește idei de activități în <?= v2_e($cityName) ?></h2>
        <p class="nl-lead">Activități noi, ghiduri locale și idei de weekend, direct pe email.</p>
        <form class="nl-form" data-newsletter="city-<?= v2_e($slug) ?>" data-msg="nl-msg" data-ok="Te-ai abonat cu succes. Mulțumim!" data-err="A apărut o eroare. Încearcă din nou." data-keep>
          <label class="sr" for="nl-email">Emailul tău</label>
          <input id="nl-email" name="email" type="email" required placeholder="emailul tău" autocomplete="email">
          <button class="btn btn-primary" type="submit">Abonează-mă</button>
        </form>
        <p class="form-msg" id="nl-msg" role="status" hidden></p>
      </div>
      <?php $nlImg = $coverResolved ?: ($gallery[1]['src'] ?? ($gallery[0]['src'] ?? '')); ?>
      <div class="nl-media">
        <?php if ($nlImg): ?>
        <img src="<?= v2_e($nlImg) ?>" alt="<?= v2_e($cityName) ?>" loading="lazy" decoding="async">
        <?php else: ?>
        <?= v2_fallback($cityName) ?><span class="nl-name"><?= v2_e($cityName) ?></span>
        <?php endif; ?>
      </div>
    </div>
  </section>

  <?php if ($gallery): ?>
  <!-- gallery lightbox -->
  <div class="lb" id="lb" role="dialog" aria-modal="true" aria-labelledby="lb-title" hidden>
    <div class="lb-top">
      <p class="lb-title" id="lb-title"><?= v2_e($gallery[0]['alt']) ?></p>
      <span class="lb-count" id="lb-count">1 / <?= count($gallery) ?></span>
      <button class="icon-btn" type="button" data-lb="close"><?= v2_ic('x') ?><span class="sr">Închide galeria</span></button>
    </div>
    <figure class="lb-fig"><img id="lb-img" src="" alt=""></figure>
    <div class="lb-nav"<?= count($gallery) < 2 ? ' hidden' : '' ?>>
      <button class="rail-btn" type="button" data-lb="prev" aria-label="Fotografia anterioară"><?= v2_ic('arrow-left') ?></button>
      <button class="rail-btn" type="button" data-lb="next" aria-label="Fotografia următoare"><?= v2_ic('arrow-right') ?></button>
    </div>
  </div>
  <?php endif; ?>
</main>
<?php include __DIR__ . '/includes/v2/footer.php'; ?>
