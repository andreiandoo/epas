<?php
/**
 * Single category landing — /{category-slug} (v2 design).
 *
 * Pure render: expects $_GET['slug'] (or `$slug` already set by an
 * including script — e.g. slug.php dispatcher). If the slug isn't a real
 * category we 404 here — city resolution lives in slug.php, not here.
 *
 * Top to bottom: hero, sticky filter bar (map, filters, subcategory toggle, quick filters with popovers, sort),
 * subcategory panel, activity cards (filtered and sorted in the browser by category.js), filters dialog,
 * map dialog, editorial text with city and related-category links, FAQ.
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

// Reuse pre-fetched data if slug.php already loaded it; else fetch now.
$category = $category ?? navGetCategoryBySlug($slug);

if (!$category) {
    http_response_code(404);
    require __DIR__ . '/404.php';
    exit;
}

// CategoriesController::show() wraps it under .category
$category = $category['category'] ?? $category;

require_once __DIR__ . '/includes/v2/helpers.php';
require_once __DIR__ . '/includes/v2/partners.php';
require_once __DIR__ . '/includes/v2/nav.php';
require_once __DIR__ . '/includes/v2/promoted.php';

// ============================================================
// Category data extraction — name/description come pre-translated
// (strings) from CategoriesController::show()
// ============================================================
$catName = navFlatName($category['name'] ?? '');
$catDescription = navFlatName($category['description'] ?? '');
$catImage = $category['image'] ?? null;
$eventCount = (int) ($category['event_count'] ?? 0);

$metaTitle = navFlatName($category['meta_title'] ?? '') ?: (v2_t('{category}: tickets and experiences', ['category' => $catName]) . ' | Viaqui');
$metaDescription = navFlatName($category['meta_description'] ?? '') ?: ($catDescription ?: v2_t('{category} on Viaqui: experiences across Europe. Book online and walk in with a QR ticket on your phone.', ['category' => $catName]));

$parent = $category['parent'] ?? null;
$children = $category['children'] ?? [];
if (!is_array($children)) $children = [];

// Admin-managed SEO body (RichEditor HTML) + custom FAQs. Both optional —
// frontend falls back to generic copy / generated FAQ when unset.
$seoBodyTitle = navFlatName($category['seo_body_title'] ?? '');
$seoBodyHtml = navFlatName($category['seo_body'] ?? '');
$adminFaqs = $category['faqs'] ?? [];
if (!is_array($adminFaqs)) $adminFaqs = [];

// ============================================================
// Listings fetch + filter parsing (query-string filters stay supported: the
// city links below use ?city=, and q / max_price / sort / page still work)
// ============================================================
$pageNum = max(1, (int) ($_GET['page'] ?? 1));
$cityFilter = isset($_GET['city']) && is_string($_GET['city']) && preg_match('/^[a-z][a-z0-9-]+$/', $_GET['city']) ? $_GET['city'] : null;
$searchQuery = isset($_GET['q']) && is_string($_GET['q']) ? mb_substr(trim($_GET['q']), 0, 80) : '';

// Price filter — single max_price value (whitelisted to keep cache key small + DOS-safe)
$priceMaxAllowed = [10, 25, 50, 100];   // euro, the same steps as the city page
$maxPrice = (isset($_GET['max_price']) && in_array((int) $_GET['max_price'], $priceMaxAllowed, true))
    ? (int) $_GET['max_price']
    : null;

// Sort — whitelisted server-side values matching what MarketplaceEventsController supports
$sortLabels = ['price_asc' => v2_t('Price: low to high'), 'price_desc' => v2_t('Price: high to low'), 'name_asc' => v2_t('A to Z'), 'date_asc' => v2_t('Event date')];
$sort = (isset($_GET['sort']) && is_string($_GET['sort']) && isset($sortLabels[$_GET['sort']])) ? $_GET['sort'] : 'recommended';

// viaqui.com has no events: the page lists the activities of the category, plus the paid "Promoted in …"
// block (fetched concurrently, curl_multi). /activities filters by the exact category slug (parent OR subcategory),
// so pass the resolved category's real slug so subcategory pages match too.
$activityCatSlug = $category['slug'] ?? $slug;
$actParams = ['category' => $activityCatSlug, 'page' => $pageNum, 'per_page' => 24];
if ($cityFilter) { $actParams['city'] = $cityFilter; }
if ($searchQuery !== '') { $actParams['search'] = $searchQuery; }
// operators sell in their own currency: the API filters and sorts on the euro value of the price
if ($maxPrice !== null) { $actParams['max_price_eur'] = $maxPrice; }
if ($sort === 'price_asc') $actParams['sort'] = 'cheapest_eur';

$cacheSuffix = ($cityFilter ?? 'all') . '_' . md5($searchQuery) . "_mp{$maxPrice}_s{$sort}_p{$pageNum}";
$listings = api_cached_many([
    'activities' => ['key' => "v2_category_activities_{$activityCatSlug}_{$cacheSuffix}", 'endpoint' => '/activities', 'params' => $actParams, 'ttl' => 300],
    'promoted'   => v2_promoted_job('category', ['category' => $activityCatSlug]),
]);
$promoted = v2_promoted_items($listings['promoted'] ?? null);

$activities = $listings['activities']['data']['items'] ?? [];
if (!is_array($activities)) $activities = [];
$actPagination = $listings['activities']['data']['pagination'] ?? ['last_page' => 1, 'total' => count($activities)];

$pagination = [
    'current_page' => $pageNum,
    'last_page'    => max(1, (int) ($actPagination['last_page'] ?? 1)),
    'total'        => (int) ($actPagination['total'] ?? count($activities)),
];

// Featured cities: hero stat, city links, city filter label.
$featuredCities = array_slice($V2NAV['citiesList'], 0, 30);
$heroLocation = v2_t('Europe');
if ($cityFilter) {
    $heroLocation = $V2NAV['cities'][$cityFilter]['name'] ?? ucwords(str_replace('-', ' ', $cityFilter));
}

// ============================================================
// SEO — city-filtered view gets a city-aware title/description so users
// see the right context in tabs + social shares. Canonical stays on the
// clean /{slug} URL so Google consolidates filtered views into the parent
// landing rather than indexing thin variants.
// ============================================================
if ($cityFilter) {
    $pageTitleRaw = v2_t('{category} in {city}: tickets and experiences', ['category' => $catName, 'city' => $heroLocation]) . ' | Viaqui';
    $pageDescription = v2_t('{category} in {city}. Book online and walk in with a QR ticket on your phone.', ['category' => $catName, 'city' => $heroLocation]);
} else {
    $pageTitleRaw = $metaTitle;
    $pageDescription = $metaDescription;
}
// Canonical URL — always the short form (slug.php also 301-redirects long → short).
$shortSlug = bo_short_category_slug($category ?? ['slug' => $slug]);
$canonicalUrl = SITE_URL . '/' . ($shortSlug ?: $slug);
$ogImage = $catImage ? (str_starts_with($catImage, 'http') ? $catImage : STORAGE_URL . '/' . ltrim($catImage, '/')) : (SITE_URL . '/assets/images/og-default.jpg');

$breadcrumbs = [
    ['name' => v2_t('Home'), 'url' => SITE_URL . '/'],
    ['name' => v2_t('Categories'), 'url' => SITE_URL . '/categories'],
];
if ($parent && !empty($parent['slug'])) {
    $breadcrumbs[] = [
        'name' => navFlatName($parent['name'] ?? ''),
        'url' => SITE_URL . '/' . $parent['slug'],
    ];
}
$breadcrumbs[] = ['name' => $catName, 'url' => $canonicalUrl];

// JSON-LD: CollectionPage + ItemList + FAQPage + BreadcrumbList
$itemListElements = [];
foreach (array_slice($activities, 0, 10) as $a) {
    $aTitle = is_array($a) ? navFlatName($a['title'] ?? '') : '';
    if ($aTitle === '' || empty($a['slug'])) continue;
    $itemListElements[] = [
        '@type' => 'ListItem',
        'position' => count($itemListElements) + 1,
        'name' => $aTitle,
        'url' => SITE_URL . '/experience/' . $a['slug'],
    ];
}
$structuredData = [];
if (!empty($itemListElements)) {
    $structuredData[] = [
        '@context' => 'https://schema.org',
        '@type' => 'CollectionPage',
        'name' => $metaTitle,
        'url' => $canonicalUrl,
        'inLanguage' => v2_locale(),
        'about' => $metaDescription,
        'mainEntity' => [
            '@type' => 'ItemList',
            'numberOfItems' => (int) ($pagination['total'] ?? count($itemListElements)),
            'itemListElement' => $itemListElements,
        ],
    ];
}

// FAQ items: prefer admin-set ones, fallback to generic auto-generated.
$faqItems = array_values(array_filter($adminFaqs, fn ($f) => !empty($f['q']) && !empty($f['a'])));
if (empty($faqItems)) {
    $faqItems = [
        [
            'q' => v2_t('How much do tickets for {category} cost?', ['category' => mb_strtolower($catName)]),
            'a' => v2_t('Prices start from the amount shown on each card and vary with the operator, the difficulty and the duration. You see the exact price on the experience page before you book.'),
        ],
        [
            'q' => v2_t('How do I get my ticket after booking?'),
            'a' => v2_t('As soon as you pay, your ticket with a QR code arrives by email and appears in your Viaqui account. Show the QR code on your phone at the entrance; you do not need to print it.'),
        ],
        [
            'q' => v2_t('Can I cancel or change my booking?'),
            'a' => v2_t('Each operator sets its own cancellation policy, and it is shown on the experience page before you pay. Check the terms of the experience you are booking.'),
        ],
        [
            'q' => v2_t('Are {category} available all year?', ['category' => mb_strtolower($catName)]),
            'a' => v2_t('Most experiences run all year, with time slots every day. The exact opening times are on each venue page, before you choose a date.'),
        ],
        [
            'q' => v2_t('Can I pay with a Viaqui gift card?'),
            'a' => v2_t('Yes. Viaqui gift cards can be used for any experience on the platform, including those in {category}.', ['category' => mb_strtolower($catName)]),
        ],
    ];
}

$structuredData[] = [
    '@context' => 'https://schema.org',
    '@type' => 'FAQPage',
    'mainEntity' => array_map(fn ($f) => [
        '@type' => 'Question',
        'name' => $f['q'],
        'acceptedAnswer' => ['@type' => 'Answer', 'text' => $f['a']],
    ], $faqItems),
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

// ============================================================
// Activities for the cards and the in-browser filters + map. Only filter
// dimensions backed by real data are exposed.
// ============================================================
$acts = [];
foreach ($activities as $ix => $a) {
    if (!is_array($a)) continue;
    $title = navFlatName($a['title'] ?? '');
    $aslug = (string) ($a['slug'] ?? '');
    if ($title === '' || $aslug === '') continue;
    $citySlugA = $a['city']['slug'] ?? '';
    $dur = (int) ($a['duration_minutes'] ?? 0);
    $langs = array_values(array_unique(array_filter(array_map(
        fn ($l) => strtolower(substr((string) (is_array($l) ? ($l['code'] ?? $l['name'] ?? '') : $l), 0, 2)),
        (array) ($a['languages_offered'] ?? [])
    ))));
    $flags = is_array($a['flags'] ?? null) ? $a['flags'] : [];
    $features = [];
    if (!empty($flags['is_kid_friendly'])) $features[] = 'family';
    if (!empty($flags['is_accessible']))   $features[] = 'wheelchair';
    if (!empty($flags['is_indoor']))        $features[] = 'indoor';
    if (!empty($flags['is_outdoor']))       $features[] = 'outdoor';
    $rev = is_array($a['reviews'] ?? null) ? $a['reviews'] : null;
    // Paid promotion first (always labelled), then the editorial pick.
    $promotedA = !empty($flags['is_promoted']);
    $badges = array_values(array_filter([$promotedA ? v2_t('Promoted') : null, !empty($flags['is_featured']) ? v2_t('Recommended') : null]));
    $catLabel = navFlatName($a['category']['name'] ?? '') ?: $catName;
    $place = navFlatName($a['city']['name'] ?? '') ?: $heroLocation;
    $description = mb_substr(trim(strip_tags((string) navFlatName($a['short_description'] ?? ''))), 0, 160);
    $acts[] = [
        'id'            => (int) ($a['id'] ?? 0) ?: 100000 + $ix,
        'title'         => $title,
        'href'          => '/experience/' . $aslug,
        'category'      => $catLabel,
        'categorySlug'  => (string) ($a['category']['slug'] ?? ''),
        'image'         => v2_media_url($a['cover_image_url'] ?? null),
        'place'         => $place,
        'rating'        => $rev && isset($rev['average']) ? round((float) $rev['average'], 1) : 0,
        'reviews'       => $rev && isset($rev['count']) ? (int) $rev['count'] : 0,
        // the euro value: what the page's price filter and sort compare (the label is printed, see below)
        'price'         => isset($a['cheapest_price_cents']) ? (int) round(v2_own_price_eur($a['cheapest_price_cents'], $a['currency'] ?? null, $a['cheapest_price_eur_cents'] ?? null)) : 0,
        'priceLabel'    => v2_own_price_label($a['cheapest_price_cents'] ?? 0, $a['currency'] ?? null, $a['cheapest_price_eur_cents'] ?? null),
        'duration'      => $dur > 0 ? ($dur < 60 ? 'short' : ($dur <= 90 ? 'medium' : 'long')) : '',
        'durationLabel' => $dur > 0 ? v2_t('{n} min', ['n' => $dur]) : '',
        'languages'     => $langs,
        'features'      => $features,
        'interests'     => array_values(array_filter(array_map(fn ($i) => $i['slug'] ?? '', (array) ($a['interests'] ?? [])))),
        'travelerTypes' => array_values(array_filter(array_map(fn ($t) => $t['slug'] ?? '', (array) ($a['traveler_types'] ?? [])))),
        'badges'        => $badges,
        'promoted'      => $promotedA,
        'text'          => mb_strtolower(implode(' ', [$title, $catLabel, $place, $description, implode(' ', $badges)])),
        '_city'         => $citySlugA,
        '_lat'          => isset($a['venue']['lat']) ? (float) $a['venue']['lat'] : (isset($a['latitude']) ? (float) $a['latitude'] : null),
        '_lng'          => isset($a['venue']['lng']) ? (float) $a['venue']['lng'] : (isset($a['longitude']) ? (float) $a['longitude'] : null),
    ];
}
// `price` is what the filters and the sort compare (euro); `priceLabel` is what is printed (the operator's currency).
foreach ($acts as $k => $a) {
    $acts[$k]['ext'] = false;
    $acts[$k]['via'] = '';
}

// Partner products of the category (WeGoTrip), after our own, on the first page: across Europe, or in the chosen
// city. The same search, price and sort choices apply. Each price is printed in the currency of its country; the
// filters and the sort work on the euro value.
$partnerCityGeo = [];
$partnerActs = [];
if ($pageNum === 1) {
    $ptItems = $cityFilter
        ? v2_partner_filter(v2_wegotrip_city_all($cityFilter), (string) ($category['slug'] ?? $slug), '', null, 'recommended')
        : v2_wegotrip_category((string) ($category['slug'] ?? $slug));
    $ptSort = ['price_asc' => 'price_asc', 'price_desc' => 'price_desc', 'name_asc' => 'name_asc'][$sort] ?? 'recommended';
    foreach (array_slice(v2_partner_filter($ptItems, null, $searchQuery, $maxPrice, $ptSort), 0, 72) as $ix => $pp) {
        if ($pp['geo'] && $pp['place'] !== '') {
            $partnerCityGeo[$pp['place']] = $pp['geo'];
        }
        $mins = preg_match('/^([\d.]+)/', $pp['duration'], $pm) ? (float) $pm[1] * 60 : 0;   // "2 – 3 hours"
        $partnerActs[] = [
            'id'            => 900000 + $ix,
            'title'         => $pp['title'],
            'href'          => v2_partner_href('wegotrip', $pp['url'], 'category-' . $slug),
            'category'      => $pp['category'] !== '' ? $pp['category'] : $catName,
            'categorySlug'  => '',
            'image'         => $pp['img'] !== '' ? $pp['img'] : null,
            'place'         => $pp['city'] !== '' ? $pp['city'] : $heroLocation,
            'rating'        => $pp['ratings'] >= 5 ? round($pp['rating'], 1) : 0,
            'reviews'       => $pp['ratings'] >= 5 ? $pp['ratings'] : 0,
            'price'         => (int) round($pp['price']),
            'priceLabel'    => $pp['price'] > 0 ? v2_price_local($pp['price'], $pp['cc']) : '',
            'duration'      => $mins > 0 ? ($mins < 60 ? 'short' : ($mins <= 90 ? 'medium' : 'long')) : '',
            'durationLabel' => $pp['duration'],
            'languages'     => [],
            'features'      => [],
            'interests'     => [],
            'travelerTypes' => [],
            'badges'        => [],
            'promoted'      => false,
            'ext'           => true,
            'via'           => 'WeGoTrip',
            'text'          => mb_strtolower(implode(' ', [$pp['title'], $pp['category'], $pp['city']])),
            '_city'         => $pp['place'],
            '_lat'          => null,
            '_lng'          => null,
        ];
    }
}

// Same order the browser applies for "Recommended", so nothing moves when category.js starts.
usort($acts, fn ($x, $y) => [(int) $y['promoted'], $y['rating'], $y['reviews']] <=> [(int) $x['promoted'], $x['rating'], $x['reviews']]);
// What the page can show: our own total (never less than the cards of this page) plus the partner listings on it.
// The hero, the announced count and the JSON-LD all print this number; the core total alone ignores the partners.
$resultsTotal = max((int) ($pagination['total'] ?? 0), count($acts)) + count($partnerActs);
// partner products keep the order they came in (best sellers, or the sort asked for) and follow our own
$acts = array_merge($acts, $partnerActs);
foreach ($structuredData as $sdKey => $sdRow) {
    if (($sdRow['@type'] ?? '') === 'CollectionPage') {
        $structuredData[$sdKey]['mainEntity']['numberOfItems'] = $resultsTotal;
    }
}

// Map pins: normalize real lat/lng into x/y% (bbox); golden-angle scatter when
// coords are missing so the map preview stays evenly populated.
$withCoords = array_filter($acts, fn ($a) => $a['_lat'] !== null && $a['_lng'] !== null);
$minLat = $maxLat = $minLng = $maxLng = null;
if (count($withCoords) >= 2) {
    $lats = array_column($withCoords, '_lat');
    $lngs = array_column($withCoords, '_lng');
    $minLat = min($lats); $maxLat = max($lats); $minLng = min($lngs); $maxLng = max($lngs);
}
foreach ($acts as $k => $a) {
    if ($minLat !== null && $a['_lat'] !== null && $a['_lng'] !== null && ($maxLat - $minLat) > 0 && ($maxLng - $minLng) > 0) {
        $x = 10 + ($a['_lng'] - $minLng) / ($maxLng - $minLng) * 80;
        $y = 10 + ($maxLat - $a['_lat']) / ($maxLat - $minLat) * 80;
    } else {
        $ang = $k * 137.508 * M_PI / 180;
        $x = 50 + cos($ang) * (18 + ($k % 4) * 6);
        $y = 50 + sin($ang) * (14 + ($k % 3) * 7);
    }
    $acts[$k]['map'] = ['x' => round(max(6, min(94, $x)), 1), 'y' => round(max(8, min(90, $y)), 1)];
}

// Real map positions. The activity list carries no coordinates, so an activity without its own venue point sits on
// its city's centre (city coordinates change rarely: cached for a day), spread in a small spiral when several share
// a city so no pin hides another. `approx` tells the map these are city positions.
$geoCities = [];
foreach ($acts as $a) {
    if ($a['_lat'] === null && $a['_city'] !== '' && !isset($partnerCityGeo[$a['_city']]) && count($geoCities) < 16) $geoCities[$a['_city']] = true;
}
$cityGeo = $partnerCityGeo;
if ($geoCities) {
    $geoJobs = [];
    foreach (array_keys($geoCities) as $gs) {
        $geoJobs[$gs] = ['key' => "v2_city_geo_{$gs}", 'endpoint' => '/locations/cities/' . rawurlencode($gs), 'params' => [], 'ttl' => 86400];
    }
    foreach (api_cached_many($geoJobs) as $gs => $res) {
        $gc = $res['data']['city'] ?? null;
        if (is_array($gc) && is_numeric($gc['latitude'] ?? null) && is_numeric($gc['longitude'] ?? null)) {
            $cityGeo[$gs] = [(float) $gc['latitude'], (float) $gc['longitude']];
        }
    }
}
$geoSeen = [];
foreach ($acts as $k => $a) {
    $geo = null;
    if ($a['_lat'] !== null && $a['_lng'] !== null) {
        $geo = ['lat' => round($a['_lat'], 6), 'lng' => round($a['_lng'], 6), 'approx' => false];
    } elseif (isset($cityGeo[$a['_city']])) {
        $n = $geoSeen[$a['_city']] = ($geoSeen[$a['_city']] ?? -1) + 1;
        $ang = $n * 137.508 * M_PI / 180;
        $r = $n === 0 ? 0 : 0.0035 * sqrt($n);
        $geo = ['lat' => round($cityGeo[$a['_city']][0] + sin($ang) * $r, 6), 'lng' => round($cityGeo[$a['_city']][1] + cos($ang) * $r * 1.4, 6), 'approx' => true];
    }
    $acts[$k]['geo'] = $geo;
    unset($acts[$k]['_lat'], $acts[$k]['_lng'], $acts[$k]['_city']);
}

// Filter options derived from real data only.
$catOptions = [];
foreach ($children as $ch) {
    $cs = $ch['slug'] ?? ''; $cn = navFlatName($ch['name'] ?? '');
    if ($cs === '' || $cn === '') continue;
    $catOptions[] = ['value' => $cs, 'label' => $cn];
}
if (empty($catOptions)) {
    $seenCat = [];
    foreach ($acts as $a) {
        $cs = $a['categorySlug'];
        if ($cs === '' || isset($seenCat[$cs])) continue;
        $seenCat[$cs] = true;
        $catOptions[] = ['value' => $cs, 'label' => $a['category']];
    }
}

$langLabels = ['ro' => v2_t('Romanian'), 'en' => v2_t('English'), 'de' => v2_t('German'), 'fr' => v2_t('French'), 'es' => v2_t('Spanish'), 'it' => v2_t('Italian'), 'hu' => v2_t('Hungarian')];
$langPresent = [];
foreach ($acts as $a) foreach ($a['languages'] as $l) $langPresent[$l] = true;
$langOptions = [];
foreach (array_keys($langPresent) as $l) $langOptions[] = ['value' => $l, 'label' => $langLabels[$l] ?? mb_strtoupper($l)];

$featLabels = ['family' => v2_t('Good for families'), 'wheelchair' => v2_t('Wheelchair accessible'), 'indoor' => v2_t('Indoors'), 'outdoor' => v2_t('Outdoors')];
$featPresent = [];
foreach ($acts as $a) foreach ($a['features'] as $f) $featPresent[$f] = true;
$featOptions = [];
foreach ($featLabels as $fk => $fl) if (isset($featPresent[$fk])) $featOptions[] = ['value' => $fk, 'label' => $fl];

// Interests + traveler types, built from slug→name maps over the real activity payloads.
$interestNames = [];
$travelerNames = [];
foreach ($activities as $a) {
    foreach ((array) ($a['interests'] ?? []) as $i) {
        if (!empty($i['slug'])) $interestNames[$i['slug']] = $i['name'] ?? $i['slug'];
    }
    foreach ((array) ($a['traveler_types'] ?? []) as $t) {
        if (!empty($t['slug'])) $travelerNames[$t['slug']] = $t['name'] ?? $t['slug'];
    }
}
$interestOptions = [];
foreach ($interestNames as $slugK => $nameK) $interestOptions[] = ['value' => $slugK, 'label' => $nameK];
$travelerOptions = [];
foreach ($travelerNames as $slugK => $nameK) $travelerOptions[] = ['value' => $slugK, 'label' => $nameK];

$durationOptions = [['value' => 'short', 'label' => v2_t('Under 60 min')], ['value' => 'medium', 'label' => v2_t('60–90 min')], ['value' => 'long', 'label' => v2_t('90+ min')]];
$ratingOptions = [['value' => 0, 'label' => v2_t('Any rating')], ['value' => 4, 'label' => '4.0+'], ['value' => 4.5, 'label' => '4.5+'], ['value' => 4.8, 'label' => '4.8+']];

$priceVals = array_filter(array_map(fn ($a) => $a['price'], $acts));
$priceCap = $priceVals ? (int) (ceil(max($priceVals) / 10) * 10) : 100;   // euro
if ($priceCap < 50) $priceCap = 50;
$hasRatings = (bool) array_filter($acts, fn ($a) => $a['rating'] > 0);

$optionLabels = function (array $options): array {
    $out = [];
    foreach ($options as $o) $out[(string) $o['value']] = $o['label'];
    return $out;
};

// Quick filters in the bar (key, kicker, popover title) and the tabs of the filters dialog.
$quickFilters = [['search', v2_t('Search'), v2_t('Search this category')], ['price', v2_t('Price'), v2_t('Your budget')], ['duration', v2_t('Duration'), v2_t('How long it takes')]];
if ($interestOptions) $quickFilters[] = ['interests', v2_t('Interests'), v2_t('What you are into')];
if ($travelerOptions) $quickFilters[] = ['traveler', v2_t('Who it is for'), v2_t('Who it suits')];
if ($langOptions) $quickFilters[] = ['languages', v2_t('Language'), v2_t('Language of the experience')];
if ($featOptions) $quickFilters[] = ['features', v2_t('Features'), v2_t('Features')];

// Where: "Anywhere in Europe", then the cities grouped by country. One list feeds the select (the fallback and the
// source category.js reads), the search panel of the bar and the "Where" tab of the filters dialog.
$whereGroups = [];
$whereKnown = false;
foreach (($V2NAV['countriesFull'] ?? []) as $kwCountry) {
    $kwCities = [];
    foreach ((array) ($kwCountry['featured'] ?? []) as $kwCity) {
        $kwSlug = ltrim((string) ($kwCity['href'] ?? ''), '/');
        if ($kwSlug === '') { continue; }
        $kwCities[] = ['slug' => $kwSlug, 'name' => (string) ($kwCity['name'] ?? $kwSlug)];
        if ($cityFilter === $kwSlug) { $whereKnown = true; }
    }
    if ($kwCities) { $whereGroups[] = ['name' => (string) $kwCountry['name'], 'cities' => $kwCities]; }
}
// Order: where this category has the most to book comes first. Counted from the listings on the page (ours) and
// from everything the partner sells in the category; then the cities the partner covers at all; then the order the
// places came in. The same for the countries, by the sum of their cities.
$kwCount = [];
foreach ($acts as $kwAct) {
    if (empty($kwAct['ext']) && ($kwAct['_city'] ?? '') !== '') { $kwCount[$kwAct['_city']] = ($kwCount[$kwAct['_city']] ?? 0) + 1; }
}
foreach (v2_wegotrip_category((string) ($category['slug'] ?? $slug), 1000) as $kwItem) {
    if ($kwItem['place'] !== '') { $kwCount[$kwItem['place']] = ($kwCount[$kwItem['place']] ?? 0) + 1; }
}
$kwCovered = v2_partners_on() ? (v2_wegotrip_index()['cities'] ?? []) : [];
foreach ($whereGroups as $kwI => $kwGroup) {
    $kwRank = [];
    foreach ($kwGroup['cities'] as $kwJ => $kwCity) {
        $kwRank[] = [-($kwCount[$kwCity['slug']] ?? 0), isset($kwCovered[$kwCity['slug']]) ? 0 : 1, $kwJ];
    }
    array_multisort($kwRank, $whereGroups[$kwI]['cities']);
    $whereGroups[$kwI]['_rank'] = [-array_sum(array_map(fn ($r) => -$r[0], $kwRank)), -count(array_filter($kwRank, fn ($r) => $r[1] === 0)), $kwI];
}
usort($whereGroups, fn ($x, $y) => $x['_rank'] <=> $y['_rank']);
$whereLabel = $cityFilter ? $heroLocation : v2_t('Anywhere in Europe');
// The search field and the (script-filled) list of places: once in the bar's panel, once in the dialog.
$renderWhereBox = function (string $id) {
    ?><div class="kw-box" data-kw="<?= v2_e($id) ?>">
          <div class="kw-field"><?= v2_ic('magnifying-glass') ?><input class="kw-input" id="<?= v2_e($id) ?>-q" type="text" role="combobox" aria-expanded="true" aria-controls="<?= v2_e($id) ?>-list" aria-autocomplete="list" aria-label="<?= v2_te('Search a country or a city') ?>" placeholder="<?= v2_te('Search a country or a city') ?>" autocomplete="off" autocapitalize="off" autocorrect="off" spellcheck="false" enterkeyhint="go"></div>
          <div class="kw-list" id="<?= v2_e($id) ?>-list" role="listbox" aria-label="<?= v2_te('Places') ?>" tabindex="-1" data-lenis-prevent></div>
          <p class="kw-none" hidden><?= v2_te('No place matches. Try another spelling.') ?></p>
          <p class="sr" aria-live="polite" data-kw-status></p>
        </div><?php
};

$filterTabs = [];
if ($whereGroups) $filterTabs[] = ['where', v2_t('Where'), v2_t('Pick a country or a city. The list reloads for that place when you show the results.')];
if ($catOptions) $filterTabs[] = ['categories', v2_t('Categories'), ''];
if ($interestOptions) $filterTabs[] = ['interests', v2_t('Interests'), v2_t('Choose the mood or theme of the experience.')];
if ($travelerOptions) $filterTabs[] = ['traveler', v2_t('Who it is for'), v2_t('Who the experience suits.')];
$filterTabs[] = ['price', v2_t('Price'), ''];
if ($langOptions) $filterTabs[] = ['languages', v2_t('Language'), ''];
$filterTabs[] = ['duration', v2_t('Duration'), ''];
if ($featOptions) $filterTabs[] = ['features', v2_t('Features'), ''];
if ($hasRatings) $filterTabs[] = ['rating', v2_t('Minimum rating'), ''];
$filterTabTitles = ['rating' => v2_t('Minimum rating')];

// ---- Controls shared by the popovers and the filters dialog (category.js keeps them in sync) ----
$renderChecks = function (string $field, array $options) {
    ?><div class="kchecks"><?php foreach ($options as $o): ?><label class="kcheck"><input type="checkbox" data-f="<?= v2_e($field) ?>" value="<?= v2_e($o['value']) ?>"><span><?= v2_e($o['label']) ?></span></label><?php endforeach; ?></div><?php
};
$renderControl = function (string $key) use ($renderChecks, $renderWhereBox, $priceCap, $catOptions, $interestOptions, $travelerOptions, $langOptions, $durationOptions, $featOptions, $ratingOptions) {
    switch ($key) {
        case 'where':
            $renderWhereBox('kfw');
            break;
        case 'search':
            ?><input class="ksearch" type="search" data-f="search" placeholder="<?= v2_te('Search by name, place or theme') ?>" aria-label="<?= v2_te('Search this category') ?>" autocomplete="off"><?php
            break;
        case 'price':
            ?><div class="kprice"><div class="kprice-row"><span><?= v2_te('Maximum price') ?></span><strong data-out="maxPrice"><?= v2_e(v2_money($priceCap)) ?></strong></div><input class="krange" type="range" min="0" max="<?= $priceCap ?>" step="10" value="<?= $priceCap ?>" data-f="maxPrice" aria-label="<?= v2_te('Maximum price, in euro') ?>"><div class="kprice-ends"><span><?= v2_e(v2_money(0)) ?></span><span><?= v2_e(v2_money($priceCap)) ?></span></div></div><?php
            break;
        case 'rating':
            ?><div class="krating"><?php foreach ($ratingOptions as $o): ?><button type="button" data-f="minRating" data-v="<?= $o['value'] ?>" aria-pressed="<?= $o['value'] === 0 ? 'true' : 'false' ?>"><span class="kstars" aria-hidden="true">★★★★★</span><span><?= v2_e($o['label']) ?></span></button><?php endforeach; ?></div><?php
            break;
        default:
            $map = ['categories' => ['categories', $catOptions], 'interests' => ['interests', $interestOptions], 'traveler' => ['travelerTypes', $travelerOptions], 'languages' => ['languages', $langOptions], 'duration' => ['durations', $durationOptions], 'features' => ['features', $featOptions]];
            if (isset($map[$key])) $renderChecks($map[$key][0], $map[$key][1]);
    }
};

// ---- Links for the query-string filters ----
$baseGet = array_filter([
    'q'         => $searchQuery,
    'city'      => (string) $cityFilter,
    'max_price' => $maxPrice !== null ? (string) $maxPrice : '',
    'sort'      => $sort === 'recommended' ? '' : $sort,
], fn ($v) => $v !== '');
$catUrl = function (array $over = []) use ($baseGet, $slug): string {
    $p = array_filter(array_merge($baseGet, $over), fn ($v) => $v !== '' && $v !== null);
    return '/' . $slug . ($p ? '?' . http_build_query($p) : '');
};
// A category (or a category in a city) with nothing to list yet: no filters to offer, another message, not indexed.
$catEmpty = !$acts && $pageNum === 1 && $searchQuery === '' && $maxPrice === null;
if ($catEmpty) {
    $noindex = true;
}
// where to send the visitor meanwhile: the main categories that do have listings (ours or a partner's)
$catElsewhere = [];
if ($catEmpty) {
    foreach ($V2NAV['categories'] as $navCat) {
        if ($navCat['slug'] !== ($category['slug'] ?? $slug) && ($navCat['count'] > 0 || isset(V2_WEGOTRIP_CATEGORY_IDS[$navCat['slug']])) && count($catElsewhere) < 4) {
            $catElsewhere[] = ['name' => $navCat['name'], 'href' => '/' . $navCat['slug'] . ($cityFilter ? '?city=' . rawurlencode($cityFilter) : '')];
        }
    }
}
$serverChips = [];
if ($searchQuery !== '') $serverChips[] = ['“' . $searchQuery . '”',$catUrl(['q' => ''])];
if ($cityFilter) $serverChips[] = [$heroLocation, $catUrl(['city' => ''])];
if ($maxPrice !== null) $serverChips[] = [v2_t('Under {price}', ['price' => v2_money($maxPrice)]), $catUrl(['max_price' => ''])];
if ($sort !== 'recommended') $serverChips[] = [v2_t('Sorted: {order}', ['order' => $sortLabels[$sort]]), $catUrl(['sort' => ''])];

// Hero photo: the platform photo of the category (or of its parent, for subcategories).
$photoCat = $V2NAV['categoryBySlug'][$category['slug'] ?? $slug] ?? (($parent && !empty($parent['slug'])) ? ($V2NAV['categoryBySlug'][$parent['slug']] ?? null) : null);
$heroImage = $photoCat['image'] ?? v2_media_url($catImage);
$heroSrcset = $photoCat['srcset'] ?? '';
// From 768px up the hero's right side previews the list itself: the first three listings of the first page, as small
// ticket cards, the ones that have a photo. Fewer than two and there is no preview (the hero is then text only).
// The category photo is only the phones' backdrop, so wider windows are handed an empty image in its place.
$heroStack = $pageNum === 1 ? array_values(array_filter(array_slice($acts, 0, 3), fn ($a) => !empty($a['image']))) : [];
if (count($heroStack) < 2) {
    $heroStack = [];
}
$heroStackFront = count($heroStack) === 3 ? 1 : 0;   // the card on top: the middle one of three, the first of two
$heroBlank = 'data:image/gif;base64,R0lGODlhAQABAAAAACH5BAEKAAEALAAAAAABAAEAAAICTAEAOw==';

$siblings = array_values(array_filter($V2NAV['categories'], fn ($c) => $c['slug'] !== $slug));
$catLower = mb_strtolower($catName);
$catArches = '<svg class="deco-arches" viewBox="0 0 400 400" aria-hidden="true" focusable="false"><path d="M40 400V200a160 160 0 0 1 320 0v200"/><path d="M90 400V200a110 110 0 0 1 220 0v200"/><path d="M140 400V200a60 60 0 0 1 120 0v200"/></svg>';

// An API outage would otherwise be cached as a half-empty page for five minutes.
if (empty($V2NAV['categories'])) {
    $skipPageCache = true;
}

$v2Styles = ['category.css'];
$v2Scripts = ['category.js'];
$v2HeaderOverlay = true;
$v2HeadExtra = $heroImage ? '<link rel="preload" as="image" media="(max-width: 767px)" href="' . v2_e($heroImage) . '"' . ($heroSrcset ? ' imagesrcset="' . v2_e($heroSrcset) . '" imagesizes="100vw"' : '') . ' fetchpriority="high">' : '';
$v2ClientData = [
    'activities' => $acts,
    'total'      => $resultsTotal,
    'priceMax'   => $priceCap,
    'labels'     => [
        'categories'    => $optionLabels($catOptions),
        'interests'     => $optionLabels($interestOptions),
        'travelerTypes' => $optionLabels($travelerOptions),
        'languages'     => $optionLabels($langOptions),
        'durations'     => $optionLabels($durationOptions),
        'features'      => $optionLabels($featOptions),
    ],
];

include __DIR__ . '/includes/v2/head.php';
include __DIR__ . '/includes/v2/header.php';
?>
<main id="main" tabindex="-1">
  <!-- ============================== HERO ============================== -->
  <?php /* Phones: the category photo is a soft backdrop under the copy. From 768px: no photo; beside the copy sits a
           preview of the list (the first listings as small ticket cards), when at least two of them have a photo. */ ?>
  <section class="kh<?= $heroImage ? ' kh-has-photo' : '' ?><?= $heroStack ? ' kh-has-pre' : '' ?>" aria-labelledby="kh-h">
    <?= $catArches ?>
    <svg class="kh-line draw-clip" viewBox="0 590 3240 310" aria-hidden="true" focusable="false"><use href="#drum-g"/></svg>
    <?php if ($heroImage): ?>
    <div class="kh-media">
      <div class="kh-photo">
        <picture><source media="(min-width: 768px)" srcset="<?= $heroBlank ?>"><img src="<?= v2_e($heroImage) ?>"<?= $heroSrcset ? ' srcset="' . v2_e($heroSrcset) . '" sizes="100vw"' : '' ?> width="640" height="800" alt="<?= v2_e($catName) ?>" fetchpriority="high" decoding="async"></picture>
      </div>
    </div>
    <?php endif; ?>
    <div class="kh-in">
      <div class="kh-copy">
        <nav class="crumbs" aria-label="<?= v2_te('Breadcrumb') ?>">
          <?php foreach ($breadcrumbs as $i => $bc): ?>
            <?php if ($i > 0): ?><span aria-hidden="true">/</span><?php endif; ?>
            <?php if ($i < count($breadcrumbs) - 1): ?><a href="<?= v2_e(substr($bc['url'], strlen(SITE_URL)) ?: '/') ?>"><?= v2_e($bc['name']) ?></a><?php else: ?><span aria-current="page"><?= v2_e($bc['name']) ?></span><?php endif; ?>
          <?php endforeach; ?>
        </nav>
        <p class="kh-kicker"><i aria-hidden="true"></i><?= v2_te('Category · open all year') ?></p>
        <h1 class="kh-h" id="kh-h"><?= v2_t('{category} <em>in {place}</em>', ['category' => v2_e($catName), 'place' => v2_e($heroLocation)]) ?></h1>
        <?php if ($catDescription !== ''): ?><p class="kh-lead"><?= v2_e($catDescription) ?></p><?php endif; ?>
        <?php /* No "0 experiences": the number is what the page lists (see $resultsTotal); with nothing to list it is left out. */ ?>
        <ul class="kh-stats"<?= $catEmpty ? ' hidden' : '' ?>>
          <?php if ($resultsTotal > 0): ?><li><?= v2_e(v2_num($resultsTotal, 'experience', 'experiences')) ?></li><?php endif; ?>
          <?php if (!empty($children)): ?><li><?= v2_e(v2_num(count($children), 'type', 'types')) ?></li><?php endif; ?>
          <?php if (!empty($featuredCities)): ?><li><?= v2_te('{n}+ cities', ['n' => count($featuredCities)]) ?></li><?php endif; ?>
        </ul>
      </div>
      <?php if ($heroStack): ?>
      <?php /* The same listings open the grid below, so these links repeat its first cards: the group says so, and
               they stay out of the JSON-LD. Phones do not show it (and are handed an empty image for the front card). */ ?>
      <div class="kh-pre" data-n="<?= count($heroStack) ?>" role="group" aria-label="<?= v2_te('Preview of the list below: the first experiences on it') ?>">
        <p class="kh-pre-lab"><?= v2_te('First on the list') ?></p>
        <ul class="kh-pre-list">
          <?php foreach ($heroStack as $hi => $a): $ktSrc = v2_thumb($a['image'], 480, 320); $ktTag = $a['via'] !== '' ? v2_t('on {partner}', ['partner' => $a['via']]) : ($a['promoted'] ? v2_t('Promoted') : ''); ?>
          <li class="kt<?= $hi === $heroStackFront ? ' is-front' : '' ?>">
            <a class="kt-a" href="<?= v2_e($a['href']) ?>"<?= $a['ext'] ? ' target="_blank" rel="sponsored nofollow noopener"' : '' ?>>
              <span class="kt-media"><?php if ($hi === $heroStackFront): ?><picture><source media="(max-width: 767px)" srcset="<?= $heroBlank ?>"><img src="<?= v2_e($ktSrc) ?>" width="480" height="320" alt="" loading="eager" fetchpriority="high" decoding="async"></picture><?php else: ?><img src="<?= v2_e($ktSrc) ?>" width="480" height="320" alt="" loading="lazy" decoding="async"><?php endif; ?><?php if ($ktTag !== ''): ?><span class="kt-tag"><?= v2_e($ktTag) ?></span><?php endif; ?></span>
              <span class="kt-body">
                <span class="kt-title"><?= v2_e($a['title']) ?></span>
                <span class="kt-place"><?php if ($a['place']): ?><?= v2_ic('map-pin') ?><span><?= v2_e($a['place']) ?></span><?php endif; ?></span>
                <?php if ($a['price']): ?><span class="kt-price"><?= v2_te('from') ?> <b><?= v2_e($a['priceLabel']) ?></b></span><?php else: ?><span class="kt-price is-na"><b><?= v2_te('See price') ?></b></span><?php endif; ?>
              </span>
            </a>
          </li>
          <?php endforeach; ?>
        </ul>
      </div>
      <?php endif; ?>
    </div>
  </section>
  <div id="hdr-sentinel" aria-hidden="true"></div>

  <?php v2_promoted_section($promoted, [
      'id' => 'promo-cat',
      'kicker' => v2_t('Promoted'),
      'title' => v2_t('Promoted in {category}', ['category' => $catLower]),
      'intro' => v2_t('Places and experiences in {category} that their operators are promoting at the moment.', ['category' => $catLower]),
  ]); ?>

  <!-- ============================== FILTER BAR ============================== -->
  <div class="kbar" id="k-bar"<?= $catEmpty && !$cityFilter ? ' hidden' : '' ?>>
    <div class="wrap kbar-in">
      <div class="kbar-pills">
        <button class="kpill kpill-map" type="button" data-open-map aria-haspopup="dialog" aria-controls="k-map"><?= v2_ic('map-pin') ?><?= v2_te('Map') ?></button>
        <button class="kpill" type="button" data-open-filters="" aria-haspopup="dialog" aria-controls="k-filters"><?= v2_ic('list') ?><?= v2_te('Filters') ?><span class="kn" data-fcount hidden>0</span></button>
        <?php if (!empty($children)): ?>
        <button class="kpill" type="button" data-subcat aria-expanded="false" aria-controls="k-sub"><?= v2_te('Types of {category}', ['category' => $catLower]) ?><?= v2_ic('caret-down', 'ic kcaret') ?></button>
        <?php endif; ?>
        <?php foreach ($quickFilters as [$key, $label]): ?>
        <button class="kpill" type="button" data-top="<?= $key ?>" aria-expanded="false" aria-controls="kp-<?= $key ?>"><?= v2_e($label) ?></button>
        <?php endforeach; ?>
        <button class="kclear" type="button" data-reset data-reset-bar hidden><?= v2_te('Clear all') ?></button>
      </div>
      <?php /* Where: a country, then one of its cities; choosing reloads the list for that city (?city=). The select is
               the plain control; with scripts on, category.js reads its options into the search panel that the button
               opens (and into the "Where" tab of the filters dialog), so both go to the same addresses. */ ?>
      <label class="ksort kwhere kwhere-fb"><span><?= v2_te('Where') ?></span>
        <select class="select" id="k-where" onchange="if (this.value) window.location.href = this.value;">
          <option value="<?= v2_e($catUrl(['city' => ''])) ?>"<?= $cityFilter ? '' : ' selected' ?>><?= v2_te('Anywhere in Europe') ?></option>
          <?php foreach ($whereGroups as $kwGroup): ?>
          <optgroup label="<?= v2_e($kwGroup['name']) ?>">
            <?php foreach ($kwGroup['cities'] as $kwCity): ?>
            <option value="<?= v2_e($catUrl(['city' => $kwCity['slug']])) ?>"<?= $cityFilter === $kwCity['slug'] ? ' selected' : '' ?>><?= v2_e($kwCity['name']) ?></option>
            <?php endforeach; ?>
          </optgroup>
          <?php endforeach; ?>
          <?php if ($cityFilter && !$whereKnown): ?>
          <option value="<?= v2_e($catUrl(['city' => $cityFilter])) ?>" selected><?= v2_e($heroLocation) ?></option>
          <?php endif; ?>
        </select>
      </label>
      <div class="ksort kwhere kw">
        <span id="k-where-lab"><?= v2_te('Where') ?></span>
        <button class="select kw-btn" type="button" id="k-where-btn" aria-haspopup="dialog" aria-expanded="false" aria-controls="k-where-pop" aria-labelledby="k-where-lab k-where-val"><span id="k-where-val"><?= v2_e($whereLabel) ?></span></button>
      </div>
      <label class="ksort"><span><?= v2_te('Sort') ?></span>
        <select class="select" id="k-sort">
          <option value="recommended"><?= v2_te('Recommended') ?></option>
          <?php if ($hasRatings): ?><option value="rating"><?= v2_te('Rating') ?></option><?php endif; ?>
          <option value="priceAsc"><?= v2_te('Price: low to high') ?></option>
          <option value="priceDesc"><?= v2_te('Price: high to low') ?></option>
          <option value="duration"><?= v2_te('Shortest first') ?></option>
        </select>
      </label>
      <div class="kw-pop" id="k-where-pop" role="dialog" aria-labelledby="k-where-pop-h" hidden>
        <div class="kw-pop-top">
          <h3 id="k-where-pop-h"><?= v2_te('Where do you want to go?') ?></h3>
          <button class="icon-btn" type="button" data-kw-close><?= v2_ic('x') ?><span class="sr"><?= v2_te('Close') ?></span></button>
        </div>
        <?php $renderWhereBox('kbw'); ?>
      </div>
      <?php foreach ($quickFilters as [$key, $label, $title]): ?>
      <div class="kpop" id="kp-<?= $key ?>" role="dialog" aria-labelledby="kp-<?= $key ?>-h" hidden>
        <div class="kpop-top">
          <div><p class="kicker"><?= v2_e($label) ?></p><h3 id="kp-<?= $key ?>-h"><?= v2_e($title) ?></h3></div>
          <button class="icon-btn" type="button" data-pop-close><?= v2_ic('x') ?><span class="sr"><?= v2_te('Close') ?></span></button>
        </div>
        <?php $renderControl($key); ?>
        <div class="kpop-foot">
          <button class="btn btn-ghost" type="button" data-clear="<?= $key ?>"><?= v2_te('Clear') ?></button>
          <button class="btn btn-primary" type="button" data-pop-close><?= v2_te('Apply') ?></button>
        </div>
      </div>
      <?php endforeach; ?>
    </div>
  </div>

  <!-- ============================== SUBCATEGORIES ============================== -->
  <?php if (!empty($children)): ?>
  <section class="ksub" id="k-sub" aria-labelledby="k-sub-h"<?= $catEmpty ? ' hidden' : '' ?>>
    <div class="ksub-clip">
      <div class="ksub-in">
        <div class="wrap ksub-pad">
          <div class="ksub-head">
            <h2 id="k-sub-h"><?= v2_te('Types of {category}', ['category' => $catLower]) ?></h2>
            <button class="link-btn" type="button" data-subcat><?= v2_te('{options} · close', ['options' => v2_num(count($children), 'option', 'options')]) ?></button>
          </div>
          <ul class="ksub-grid">
            <?php foreach ($children as $child):
                $childName = navFlatName($child['name'] ?? '');
                $childSlug = $child['slug'] ?? '';
                if (!$childName || !$childSlug) continue;
                $child['parent_slug'] = $category['slug'] ?? '';
                $childCount = (int) ($child['event_count'] ?? 0);
            ?>
            <li><a class="ksc" href="/<?= v2_e(bo_short_category_slug($child)) ?>"><span class="ksc-ic" aria-hidden="true"><?= v2_e(mb_substr($childName, 0, 1)) ?></span><span><b><?= v2_e($childName) ?></b><small><?= $childCount > 0 ? v2_e(v2_num($childCount, 'experience', 'experiences')) : v2_te('coming soon') ?></small></span></a></li>
            <?php endforeach; ?>
          </ul>
        </div>
      </div>
    </div>
  </section>
  <?php endif; ?>

  <!-- ============================== RESULTS ============================== -->
  <section class="kres" aria-labelledby="kres-h">
    <div class="wrap">
      <h2 class="sr" id="kres-h"><?= v2_te('{category}: experiences', ['category' => $catName]) ?></h2>
      <?php /* The number of results, for screen readers: the same total as the hero; category.js updates it when a filter narrows the list. */ ?>
      <p class="sr" id="k-total" role="status"><?= $resultsTotal > 0 ? v2_e(v2_num($resultsTotal, 'experience', 'experiences')) : '' ?></p>
      <?php if ($whereGroups): ?>
      <noscript>
        <form class="kw-noscript" method="get" action="/<?= v2_e($slug) ?>">
          <?php foreach ($baseGet as $bgKey => $bgValue): if ($bgKey === 'city') { continue; } ?><input type="hidden" name="<?= v2_e($bgKey) ?>" value="<?= v2_e($bgValue) ?>"><?php endforeach; ?>
          <label for="k-where-ns"><?= v2_te('Where') ?></label>
          <select class="select" id="k-where-ns" name="city">
            <option value=""><?= v2_te('Anywhere in Europe') ?></option>
            <?php foreach ($whereGroups as $kwGroup): ?>
            <optgroup label="<?= v2_e($kwGroup['name']) ?>">
              <?php foreach ($kwGroup['cities'] as $kwCity): ?><option value="<?= v2_e($kwCity['slug']) ?>"<?= $cityFilter === $kwCity['slug'] ? ' selected' : '' ?>><?= v2_e($kwCity['name']) ?></option><?php endforeach; ?>
            </optgroup>
            <?php endforeach; ?>
          </select>
          <button class="btn btn-primary" type="submit"><?= v2_te('Show results') ?></button>
        </form>
      </noscript>
      <?php endif; ?>
      <?php if ($serverChips): ?>
      <ul class="kactive" aria-label="<?= v2_te('Filters from the address') ?>">
        <?php foreach ($serverChips as [$label, $href]): ?><li><a class="achip" href="<?= v2_e($href) ?>"><?= v2_e($label) ?><?= v2_ic('x') ?><span class="sr"> <?= v2_te('(remove)') ?></span></a></li><?php endforeach; ?>
      </ul>
      <?php endif; ?>
      <div class="kchips" id="k-chips" aria-label="<?= v2_te('Active filters') ?>"></div>

      <?php if ($partnerActs): ?><p class="cl-fx"><?= v2_ic('info') ?><?= v2_display_currency() !== null ? v2_e(v2_fx_note('')) : v2_te('Prices are shown in the currency of each country. The price filter and the sort use their value in euro.') ?> <?= v2_te('Listings marked “on WeGoTrip” are sold by our partner, which may pay Viaqui a commission.') ?></p><?php endif; ?>
      <?php if ($acts): ?>
      <ul class="xp-grid" id="k-grid" data-reveal>
        <?php foreach ($acts as $i => $a): ?>
        <li class="xp<?= $a['ext'] ? ' xp-partner' : '' ?>" data-id="<?= $a['id'] ?>">
          <a href="<?= v2_e($a['href']) ?>"<?= $a['ext'] ? ' target="_blank" rel="sponsored nofollow noopener"' : '' ?>>
            <span class="xp-media"><?= $a['image'] ? v2_photo([$a['image'], 0, 0, '']) : v2_fallback($a['title'], $i) ?><?php if ($a['via'] !== ''): ?><span class="xp-via"><?= v2_te('on {partner}', ['partner' => $a['via']]) ?></span><?php endif; ?><?php if ($a['badges']): ?><span class="xp-badges"><?php foreach ($a['badges'] as $b): ?><span><?= v2_e($b) ?></span><?php endforeach; ?></span><?php endif; ?></span>
            <span class="xp-body">
              <span class="xp-cat"><?= v2_e($a['category']) ?></span>
              <span class="xp-title"><?= v2_e($a['title']) ?></span>
              <span class="xp-meta"><?php if ($a['rating'] > 0): ?><span class="xp-rating"><?= v2_ic('star') ?><?= (string) $a['rating'] ?><?php if ($a['reviews'] > 0): ?> (<?= v2_thousands($a['reviews']) ?>)<?php endif; ?></span><?php endif; ?><?php if ($a['durationLabel']): ?><span><?= v2_ic('clock') ?><?= v2_e($a['durationLabel']) ?></span><?php endif; ?><?php if ($a['place']): ?><span><?= v2_ic('map-pin') ?><?= v2_e($a['place']) ?></span><?php endif; ?></span>
              <span class="xp-foot"><span class="xp-go"><?= v2_te('See') ?><?= v2_ic('arrow-right') ?></span><?php if ($a['price']): ?><span class="xp-price"><?= v2_te('from') ?><b><?= v2_e($a['priceLabel']) ?></b></span><?php else: ?><span class="xp-price is-na"><b><?= v2_te('See price') ?></b></span><?php endif; ?></span>
            </span>
          </a>
          <button class="xp-fav" type="button" data-fav aria-pressed="false"><?= v2_ic('heart', 'ic ic-off') ?><?= v2_ic('heart-fill', 'ic ic-on') ?><span class="sr"><?= v2_te('Save {title}', ['title' => $a['title']]) ?></span></button>
        </li>
        <?php endforeach; ?>
      </ul>
      <?php endif; ?>

      <div class="k-empty<?= $catEmpty ? ' k-empty-yet' : '' ?>" id="k-empty"<?= $acts ? ' hidden' : '' ?>>
        <?php if ($catEmpty): ?>
        <h3><?= $cityFilter ? v2_te('Nothing listed under {category} in {city} yet.', ['category' => $catName, 'city' => $heroLocation]) : v2_te('Nothing listed under {category} yet.', ['category' => $catName]) ?></h3>
        <p><?= $catElsewhere ? v2_te('We are adding operators across Europe, and this category is still waiting for its first one. Until then, these have plenty to choose from:') : v2_te('We are adding operators across Europe, and this category is still waiting for its first one.') ?></p>
        <div class="k-empty-cta">
          <?php foreach ($catElsewhere as $ce): ?><a class="btn btn-light" href="<?= v2_e($ce['href']) ?>"><?= v2_e($ce['name']) ?></a><?php endforeach; ?>
          <?php if ($cityFilter): ?>
          <a class="btn btn-ghost" href="/<?= v2_e($cityFilter) ?>"><?= v2_te('Everything in {city}', ['city' => $heroLocation]) ?></a>
          <a class="btn btn-ghost" href="/<?= v2_e($slug) ?>"><?= v2_te('{category} across Europe', ['category' => $catName]) ?></a>
          <?php else: ?>
          <a class="btn btn-ghost" href="/attractions"><?= v2_te('Browse attractions') ?></a>
          <?php endif; ?>
        </div>
        <p class="k-empty-op"><?= v2_t('Do you run experiences of this kind? <a href="/partners">List them on Viaqui</a>.') ?></p>
        <?php else: ?>
        <h3><?= v2_te('No experiences found.') ?></h3>
        <p><?= v2_te('Change the filters or clear your search.') ?></p>
        <div class="k-empty-cta">
          <button class="btn btn-light" type="button" data-reset><?= v2_te('Reset the filters') ?></button>
          <?php if ($serverChips): ?><a class="btn btn-ghost" href="/<?= v2_e($slug) ?>"><?= v2_te('All experiences in this category') ?></a><?php endif; ?>
        </div>
        <?php endif; ?>
        <svg class="k-empty-line" viewBox="0 590 3240 310" aria-hidden="true" focusable="false"><use href="#drum-g"/></svg>
      </div>

      <?php
      $current = (int) $pagination['current_page'];
      $last = max(1, (int) $pagination['last_page']);
      if ($last > 1 && $acts):
          $start = max(1, $current - 3);
          $end = min($last, $start + 6);
          $start = max(1, $end - 6);
      ?>
      <nav class="pager" aria-label="<?= v2_te('Pages') ?>">
        <?php if ($current > 1): ?><a class="pg-step" href="<?= v2_e($catUrl(['page' => $current - 1 > 1 ? $current - 1 : ''])) ?>" rel="prev"><?= v2_ic('arrow-left') ?><?= v2_te('Previous') ?></a><?php endif; ?>
        <?php for ($p = $start; $p <= $end; $p++): ?>
          <?php if ($p === $current): ?><span aria-current="page"><?= $p ?></span><?php else: ?><a href="<?= v2_e($catUrl(['page' => $p > 1 ? $p : ''])) ?>"><?= $p ?></a><?php endif; ?>
        <?php endfor; ?>
        <?php if ($current < $last): ?><a class="pg-step" href="<?= v2_e($catUrl(['page' => $current + 1])) ?>" rel="next"><?= v2_te('Next') ?><?= v2_ic('arrow-right') ?></a><?php endif; ?>
      </nav>
      <?php endif; ?>
    </div>
  </section>

  <!-- ============================== EDITORIAL + FAQ ============================== -->
  <section class="sec faq kseo" aria-labelledby="kseo-h">
    <div class="wrap faq-wrap">
      <div class="seo">
        <h2 id="kseo-h">
          <?php if ($seoBodyTitle !== ''): ?>
            <?= v2_e($seoBodyTitle) ?>
          <?php else: ?>
            <?= v2_t('What to know about <em>{category}</em>', ['category' => v2_e($catLower)]) ?>
          <?php endif; ?>
        </h2>

        <?php if ($seoBodyHtml !== ''): ?>
          <?php // Admin RichEditor HTML — emit as-is, only allow the editor's whitelisted tags. ?>
          <div class="kseo-body"><?= strip_tags($seoBodyHtml, '<p><h2><h3><h4><strong><em><b><i><u><a><ul><ol><li><blockquote><br><span>') ?></div>
        <?php elseif ($catDescription !== ''): ?>
          <div class="kseo-body">
            <?php foreach (preg_split('/\n\s*\n/', trim($catDescription)) as $paragraph): ?>
              <p><?= nl2br(v2_e($paragraph)) ?></p>
            <?php endforeach; ?>
          </div>
        <?php else: ?>
          <p><?= v2_t('On Viaqui you will find a careful selection of <strong>{category}</strong> across Europe. Choose a date and time online, pay securely and walk in with the QR ticket on your phone.', ['category' => v2_e($catLower)]) ?></p>
        <?php endif; ?>

        <?php if (!empty($featuredCities)): ?>
        <div class="kx">
          <h3 class="flabel"><?= v2_te('{category} by city', ['category' => $catName]) ?></h3>
          <div class="chips-links">
            <?php foreach (array_slice($featuredCities, 0, 8) as $c): ?><a href="/<?= v2_e($slug) ?>?city=<?= v2_e($c['slug']) ?>"><?= v2_te('{what} in {city}', ['what' => $catName, 'city' => $c['name']]) ?></a><?php endforeach; ?>
          </div>
        </div>
        <?php endif; ?>

        <?php if ($siblings): ?>
        <div class="kx">
          <h3 class="flabel"><?= v2_te('Related categories') ?></h3>
          <div class="chips-links">
            <?php foreach (array_slice($siblings, 0, 8) as $sib): ?><a href="<?= v2_e($sib['href']) ?>"><?= v2_e($sib['name']) ?></a><?php endforeach; ?>
          </div>
        </div>
        <?php endif; ?>
      </div>

      <div class="faq-col">
        <h2 id="kfaq-h"><?= v2_te('Frequently asked questions') ?></h2>
        <?php foreach ($faqItems as $i => $f): ?>
        <details class="qa"<?= $i === 0 ? ' open' : '' ?>><summary><?= v2_e($f['q']) ?><span class="pm"><?= v2_ic('plus') ?></span></summary><p><?= v2_e($f['a']) ?></p></details>
        <?php endforeach; ?>
      </div>
    </div>
  </section>

  <!-- ============================== FILTERS DIALOG ============================== -->
  <div class="kdlg" id="k-filters" role="dialog" aria-modal="true" aria-labelledby="kf-h" hidden>
    <div class="kdlg-panel">
      <div class="kdlg-top">
        <div><p class="kicker"><?= v2_te('Filters') ?></p><h2 id="kf-h"><?= v2_te('Filter the experiences') ?></h2></div>
        <button class="icon-btn" type="button" data-dlg-close><?= v2_ic('x') ?><span class="sr"><?= v2_te('Close filters') ?></span></button>
      </div>
      <div class="kdlg-body">
        <div class="kdlg-tabs" role="tablist" aria-orientation="vertical" aria-label="<?= v2_te('Filters') ?>" data-tabs>
          <?php foreach ($filterTabs as $t => [$key, $label]): ?>
          <button type="button" role="tab" id="kft-<?= $key ?>" aria-controls="kfp-<?= $key ?>" aria-selected="<?= $t === 0 ? 'true' : 'false' ?>" tabindex="<?= $t === 0 ? 0 : -1 ?>"><?= v2_e($label) ?><span class="kdot" data-tabdot="<?= $key ?>" hidden></span></button>
          <?php endforeach; ?>
        </div>
        <div class="kdlg-panels">
          <?php foreach ($filterTabs as $t => [$key, $label, $sub]): ?>
          <section role="tabpanel" id="kfp-<?= $key ?>" aria-labelledby="kft-<?= $key ?>"<?= $t === 0 ? '' : ' hidden' ?>>
            <h3><?= v2_e($label) ?></h3>
            <?php if ($sub !== ''): ?><p><?= v2_e($sub) ?></p><?php endif; ?>
            <div class="kdlg-control"><?php $renderControl($key); ?></div>
          </section>
          <?php endforeach; ?>
        </div>
      </div>
      <div class="kdlg-foot">
        <button class="btn btn-ghost" type="button" data-reset><?= v2_te('Clear all') ?></button>
        <?php /* A place chosen in the "Where" tab is applied by this button: it then names the place and loads its list. */ ?>
        <button class="btn btn-primary" type="button" data-dlg-close data-dlg-apply><span data-apply-count><?= v2_t('Show <span data-count>{n}</span> results', ['n' => count($acts)]) ?></span><span data-apply-place hidden></span></button>
      </div>
    </div>
  </div>

  <!-- ============================== MAP DIALOG ============================== -->
  <div class="kmap" id="k-map" role="dialog" aria-modal="true" aria-labelledby="km-h" hidden>
    <div class="kmap-panel">
      <aside class="kmap-side">
        <div class="kmap-top">
          <div class="kmap-head">
            <div>
              <p class="kicker"><?= v2_te('Map') ?></p>
              <h2 id="km-h"><?= $cityFilter ? v2_te('{what} in {city}', ['what' => $catName, 'city' => $heroLocation]) : v2_e($catName) ?></h2>
              <p class="kmap-n" id="k-map-n" aria-live="polite"><?= v2_t('<span data-count>{n}</span> results on the map', ['n' => count($acts)]) ?></p>
              <p class="kmap-zone" id="k-map-zone" hidden><span id="k-map-zone-text"></span><button class="link-btn" type="button" data-map-all><?= v2_te('Show all') ?></button></p>
              <p class="kmap-note" id="k-map-note" hidden><?= v2_te('Where the exact place is missing, an experience is shown in the centre of its city.') ?></p>
            </div>
            <button class="icon-btn" type="button" data-dlg-close><?= v2_ic('x') ?><span class="sr"><?= v2_te('Close the map') ?></span></button>
          </div>
          <div class="kmap-quick">
            <button class="kpill" type="button" data-open-filters="" aria-haspopup="dialog" aria-controls="k-filters"><?= v2_ic('list') ?><?= v2_te('Filters') ?></button>
            <button class="kpill" type="button" data-open-filters="price" aria-haspopup="dialog" aria-controls="k-filters"><?= v2_te('Price') ?></button>
            <button class="kpill" type="button" data-open-filters="duration" aria-haspopup="dialog" aria-controls="k-filters"><?= v2_te('Duration') ?></button>
          </div>
        </div>
        <ul class="kmap-list" id="k-map-list"></ul>
      </aside>
      <section class="kmap-view" aria-label="<?= v2_te('Map preview') ?>">
        <div class="kmap-canvas" id="k-map-canvas" data-carto-key="<?= v2_e(defined('CARTO_API_KEY') ? CARTO_API_KEY : '') ?>"></div>
        <div id="k-map-pins"></div>
        <div class="kmap-card" id="k-map-card" hidden></div>
      </section>
    </div>
  </div>
</main>
<?php include __DIR__ . '/includes/v2/footer.php'; ?>
