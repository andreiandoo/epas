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
require_once __DIR__ . '/includes/v2/nav.php';

// ============================================================
// Category data extraction — name/description come pre-translated
// (strings) from CategoriesController::show()
// ============================================================
$catName = navFlatName($category['name'] ?? '');
$catDescription = navFlatName($category['description'] ?? '');
$catImage = $category['image'] ?? null;
$eventCount = (int) ($category['event_count'] ?? 0);

$metaTitle = navFlatName($category['meta_title'] ?? '') ?: ($catName . ' — bilete & rezervări online | bilete.online');
$metaDescription = navFlatName($category['meta_description'] ?? '') ?: ($catDescription ?: ('Activități din categoria ' . $catName . ' pe bilete.online. Rezervi online, intri cu QR.'));

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
$priceMaxAllowed = [50, 100, 200, 500];
$maxPrice = (isset($_GET['max_price']) && in_array((int) $_GET['max_price'], $priceMaxAllowed, true))
    ? (int) $_GET['max_price']
    : null;

// Sort — whitelisted server-side values matching what MarketplaceEventsController supports
$sortLabels = ['price_asc' => 'Preț crescător', 'price_desc' => 'Preț descrescător', 'name_asc' => 'Alfabetic', 'date_asc' => 'Data evenimentului'];
$sort = (isset($_GET['sort']) && is_string($_GET['sort']) && isset($sortLabels[$_GET['sort']])) ? $_GET['sort'] : 'recommended';

// Events and activities are independent upstream calls, fetched concurrently (curl_multi).
// /activities filters by the exact category slug (parent OR subcategory), so pass the
// resolved category's real slug so subcategory pages match too.
$activityCatSlug = $category['slug'] ?? $slug;
$evParams = ['category' => $slug, 'page' => $pageNum, 'per_page' => 24, 'time_scope' => 'upcoming'];
$actParams = ['category' => $activityCatSlug, 'page' => $pageNum, 'per_page' => 24];
if ($cityFilter) { $evParams['city'] = $cityFilter; $actParams['city'] = $cityFilter; }
if ($searchQuery !== '') { $evParams['search'] = $searchQuery; $actParams['search'] = $searchQuery; }
if ($maxPrice !== null) { $evParams['max_price'] = $maxPrice; $actParams['max_price_ron'] = $maxPrice; }
// Don't send `recommended` — let the API use its default (date_asc for upcoming)
if ($sort !== 'recommended') $evParams['sort'] = $sort;
if ($sort === 'price_asc') $actParams['sort'] = 'cheapest';

$cacheSuffix = ($cityFilter ?? 'all') . '_' . md5($searchQuery) . "_mp{$maxPrice}_s{$sort}_p{$pageNum}";
$listings = api_cached_many([
    'events'     => ['key' => "v2_category_events_{$slug}_{$cacheSuffix}", 'endpoint' => '/events', 'params' => $evParams, 'ttl' => 300],
    'activities' => ['key' => "v2_category_activities_{$activityCatSlug}_{$cacheSuffix}", 'endpoint' => '/activities', 'params' => $actParams, 'ttl' => 300],
]);

$events = $listings['events']['data'] ?? [];
if (!is_array($events)) $events = [];
$evPagination = $listings['events']['meta'] ?? ['current_page' => 1, 'last_page' => 1, 'total' => count($events)];

$activities = $listings['activities']['data']['items'] ?? [];
if (!is_array($activities)) $activities = [];
$actPagination = $listings['activities']['data']['pagination'] ?? ['last_page' => 1, 'total' => count($activities)];

// Combined pagination: both sources paginate independently; surface the deeper
// page count and the summed total (used for the hero activities stat).
$pagination = [
    'current_page' => $pageNum,
    'last_page'    => max((int) ($evPagination['last_page'] ?? 1), (int) ($actPagination['last_page'] ?? 1)),
    'total'        => (int) ($evPagination['total'] ?? count($events)) + (int) ($actPagination['total'] ?? count($activities)),
];

// Featured cities: hero stat, city links, city filter label.
$featuredCities = array_slice($V2NAV['citiesList'], 0, 30);
$heroLocation = 'România';
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
    $pageTitleRaw = $catName . ' în ' . $heroLocation . ' — bilete & rezervări online | bilete.online';
    $pageDescription = 'Activități ' . mb_strtolower($catName) . ' în ' . $heroLocation . '. Rezervi online cu QR, intri rapid.';
} else {
    $pageTitleRaw = $metaTitle;
    $pageDescription = $metaDescription;
}
// Canonical URL — always the short form (slug.php also 301-redirects long → short).
$shortSlug = bo_short_category_slug($category ?? ['slug' => $slug]);
$canonicalUrl = SITE_URL . '/' . ($shortSlug ?: $slug);
$ogImage = $catImage ? (str_starts_with($catImage, 'http') ? $catImage : STORAGE_URL . '/' . ltrim($catImage, '/')) : (SITE_URL . '/assets/images/og-default.jpg');

$breadcrumbs = [
    ['name' => 'Acasă', 'url' => SITE_URL . '/'],
    ['name' => 'Categorii', 'url' => SITE_URL . '/categorii'],
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
foreach (array_slice($events, 0, 10) as $i => $ev) {
    $evTitle = is_array($ev['title'] ?? null) ? ($ev['title']['ro'] ?? reset($ev['title'])) : ($ev['title'] ?? '');
    $itemListElements[] = [
        '@type' => 'ListItem',
        'position' => $i + 1,
        'name' => $evTitle,
        'url' => SITE_URL . '/bilete/' . ($ev['slug'] ?? ''),
    ];
}
$structuredData = [];
if (!empty($itemListElements)) {
    $structuredData[] = [
        '@context' => 'https://schema.org',
        '@type' => 'CollectionPage',
        'name' => $metaTitle,
        'url' => $canonicalUrl,
        'inLanguage' => 'ro-RO',
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
            'q' => 'Cât costă bilete pentru ' . mb_strtolower($catName) . '?',
            'a' => 'Prețurile încep de la valoarea afișată pe fiecare card și diferă în funcție de operator, dificultate sau durată. Vezi prețul exact pe pagina activității înainte de rezervare.',
        ],
        [
            'q' => 'Cum primesc biletul după rezervare?',
            'a' => 'Imediat după plată primești biletul cu cod QR pe email și în contul tău bilete.online. La intrare prezinți codul QR de pe telefon — fără tipărire obligatorie.',
        ],
        [
            'q' => 'Pot anula sau reprograma rezervarea?',
            'a' => 'Politica de anulare e stabilită de fiecare operator și e afișată clar pe pagina activității, înainte de plată. Verifică condițiile concrete pe locul unde rezervi.',
        ],
        [
            'q' => navMbUcfirst($catName) . ' sunt disponibile tot anul?',
            'a' => 'Majoritatea activităților funcționează pe tot parcursul anului, cu intervale orare zilnice. Programul exact apare pe pagina fiecărei locații înainte să selectezi data.',
        ],
        [
            'q' => 'Pot plăti cu un card cadou bilete.online?',
            'a' => 'Da. Cardurile cadou bilete.online se pot folosi la orice activitate de pe platformă, inclusiv cele din categoria ' . mb_strtolower($catName) . '.',
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
    $badges = !empty($flags['is_featured']) ? ['Recomandat'] : [];
    $catLabel = navFlatName($a['category']['name'] ?? '') ?: $catName;
    $place = navFlatName($a['city']['name'] ?? '') ?: $heroLocation;
    $description = mb_substr(trim(strip_tags((string) navFlatName($a['short_description'] ?? ''))), 0, 160);
    $acts[] = [
        'id'            => (int) ($a['id'] ?? 0) ?: 100000 + $ix,
        'title'         => $title,
        'href'          => $citySlugA ? '/' . $citySlugA . '/' . $aslug : '/activitate/' . $aslug,
        'category'      => $catLabel,
        'categorySlug'  => (string) ($a['category']['slug'] ?? ''),
        'image'         => v2_media_url($a['cover_image_url'] ?? null),
        'place'         => $place,
        'rating'        => $rev && isset($rev['average']) ? round((float) $rev['average'], 1) : 0,
        'reviews'       => $rev && isset($rev['count']) ? (int) $rev['count'] : 0,
        'price'         => isset($a['cheapest_price_cents']) ? (int) round($a['cheapest_price_cents'] / 100) : 0,
        'duration'      => $dur > 0 ? ($dur < 60 ? 'short' : ($dur <= 90 ? 'medium' : 'long')) : '',
        'durationLabel' => $dur > 0 ? ($dur . ' min') : '',
        'languages'     => $langs,
        'features'      => $features,
        'interests'     => array_values(array_filter(array_map(fn ($i) => $i['slug'] ?? '', (array) ($a['interests'] ?? [])))),
        'travelerTypes' => array_values(array_filter(array_map(fn ($t) => $t['slug'] ?? '', (array) ($a['traveler_types'] ?? [])))),
        'badges'        => $badges,
        'text'          => mb_strtolower(implode(' ', [$title, $catLabel, $place, $description, implode(' ', $badges)])),
        '_city'         => $citySlugA,
        '_lat'          => isset($a['venue']['lat']) ? (float) $a['venue']['lat'] : (isset($a['latitude']) ? (float) $a['latitude'] : null),
        '_lng'          => isset($a['venue']['lng']) ? (float) $a['venue']['lng'] : (isset($a['longitude']) ? (float) $a['longitude'] : null),
    ];
}
// Same order the browser applies for "Recomandate", so nothing moves when category.js starts.
usort($acts, fn ($x, $y) => [$y['rating'], $y['reviews']] <=> [$x['rating'], $x['reviews']]);

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
    if ($a['_lat'] === null && $a['_city'] !== '' && count($geoCities) < 16) $geoCities[$a['_city']] = true;
}
$cityGeo = [];
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

$langLabels = ['ro' => 'Română', 'en' => 'Engleză', 'de' => 'Germană', 'fr' => 'Franceză', 'es' => 'Spaniolă', 'it' => 'Italiană', 'hu' => 'Maghiară'];
$langPresent = [];
foreach ($acts as $a) foreach ($a['languages'] as $l) $langPresent[$l] = true;
$langOptions = [];
foreach (array_keys($langPresent) as $l) $langOptions[] = ['value' => $l, 'label' => $langLabels[$l] ?? mb_strtoupper($l)];

$featLabels = ['family' => 'Potrivit pentru familie', 'wheelchair' => 'Accesibil', 'indoor' => 'Indoor', 'outdoor' => 'Outdoor'];
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

$durationOptions = [['value' => 'short', 'label' => 'Sub 60 min'], ['value' => 'medium', 'label' => '60–90 min'], ['value' => 'long', 'label' => '90+ min']];
$ratingOptions = [['value' => 0, 'label' => 'Orice rating'], ['value' => 4, 'label' => '4.0+'], ['value' => 4.5, 'label' => '4.5+'], ['value' => 4.8, 'label' => '4.8+']];

$priceVals = array_filter(array_map(fn ($a) => $a['price'], $acts));
$priceCap = $priceVals ? (int) (ceil(max($priceVals) / 50) * 50) : 250;
if ($priceCap < 100) $priceCap = 100;
$hasRatings = (bool) array_filter($acts, fn ($a) => $a['rating'] > 0);

$optionLabels = function (array $options): array {
    $out = [];
    foreach ($options as $o) $out[(string) $o['value']] = $o['label'];
    return $out;
};

// Quick filters in the bar (key, kicker, popover title) and the tabs of the filters dialog.
$quickFilters = [['search', 'Caută', 'Caută în categorie'], ['price', 'Preț', 'Bugetul tău'], ['duration', 'Durată', 'Durata activității']];
if ($interestOptions) $quickFilters[] = ['interests', 'Interese', 'Ce te atrage'];
if ($travelerOptions) $quickFilters[] = ['traveler', 'Pentru cine', 'Cui i se potrivește'];
if ($langOptions) $quickFilters[] = ['languages', 'Limbă', 'Limba activității'];
if ($featOptions) $quickFilters[] = ['features', 'Caracteristici', 'Caracteristici'];

$filterTabs = [];
if ($catOptions) $filterTabs[] = ['categories', 'Categorii', ''];
if ($interestOptions) $filterTabs[] = ['interests', 'Interese', 'Alege atmosfera sau tema activității.'];
if ($travelerOptions) $filterTabs[] = ['traveler', 'Pentru cine', 'Cui i se potrivește activitatea.'];
$filterTabs[] = ['price', 'Preț', ''];
if ($langOptions) $filterTabs[] = ['languages', 'Limbă', ''];
$filterTabs[] = ['duration', 'Durată', ''];
if ($featOptions) $filterTabs[] = ['features', 'Caracteristici', ''];
if ($hasRatings) $filterTabs[] = ['rating', 'Rating minim', ''];
$filterTabTitles = ['rating' => 'Rating minim'];

// ---- Controls shared by the popovers and the filters dialog (category.js keeps them in sync) ----
$renderChecks = function (string $field, array $options) {
    ?><div class="kchecks"><?php foreach ($options as $o): ?><label class="kcheck"><input type="checkbox" data-f="<?= v2_e($field) ?>" value="<?= v2_e($o['value']) ?>"><span><?= v2_e($o['label']) ?></span></label><?php endforeach; ?></div><?php
};
$renderControl = function (string $key) use ($renderChecks, $priceCap, $catOptions, $interestOptions, $travelerOptions, $langOptions, $durationOptions, $featOptions, $ratingOptions) {
    switch ($key) {
        case 'search':
            ?><input class="ksearch" type="search" data-f="search" placeholder="Caută după nume, locație, temă..." aria-label="Caută în categorie" autocomplete="off"><?php
            break;
        case 'price':
            ?><div class="kprice"><div class="kprice-row"><span>Preț maxim</span><strong data-out="maxPrice"><?= v2_thousands($priceCap) ?> lei</strong></div><input class="krange" type="range" min="0" max="<?= $priceCap ?>" step="10" value="<?= $priceCap ?>" data-f="maxPrice" aria-label="Preț maxim, în lei"><div class="kprice-ends"><span>0 lei</span><span><?= v2_thousands($priceCap) ?> lei</span></div></div><?php
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
$serverChips = [];
if ($searchQuery !== '') $serverChips[] = ['„' . $searchQuery . '”', $catUrl(['q' => ''])];
if ($cityFilter) $serverChips[] = [$heroLocation, $catUrl(['city' => ''])];
if ($maxPrice !== null) $serverChips[] = ['Sub ' . $maxPrice . ' lei', $catUrl(['max_price' => ''])];
if ($sort !== 'recommended') $serverChips[] = ['Sortat: ' . $sortLabels[$sort], $catUrl(['sort' => ''])];

// Hero photo: the platform photo of the category (or of its parent, for subcategories).
$photoCat = $V2NAV['categoryBySlug'][$category['slug'] ?? $slug] ?? (($parent && !empty($parent['slug'])) ? ($V2NAV['categoryBySlug'][$parent['slug']] ?? null) : null);
$heroImage = $photoCat['image'] ?? v2_media_url($catImage);
$heroSrcset = $photoCat['srcset'] ?? '';

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
$v2HeadExtra = $heroImage ? '<link rel="preload" as="image" href="' . v2_e($heroImage) . '"' . ($heroSrcset ? ' imagesrcset="' . v2_e($heroSrcset) . '" imagesizes="(min-width: 1024px) 460px, 78vw"' : '') . ' fetchpriority="high">' : '';
$v2ClientData = [
    'activities' => $acts,
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
  <section class="kh" aria-labelledby="kh-h">
    <?= $catArches ?>
    <svg class="kh-line draw-clip" viewBox="0 590 3240 310" aria-hidden="true" focusable="false"><use href="#drum-g"/></svg>
    <div class="kh-in">
      <div class="kh-copy">
        <nav class="crumbs" aria-label="Breadcrumb">
          <?php foreach ($breadcrumbs as $i => $bc): ?>
            <?php if ($i > 0): ?><span aria-hidden="true">/</span><?php endif; ?>
            <?php if ($i < count($breadcrumbs) - 1): ?><a href="<?= v2_e(substr($bc['url'], strlen(SITE_URL)) ?: '/') ?>"><?= v2_e($bc['name']) ?></a><?php else: ?><span aria-current="page"><?= v2_e($bc['name']) ?></span><?php endif; ?>
          <?php endforeach; ?>
        </nav>
        <p class="kh-kicker"><i aria-hidden="true"></i>Categorie · disponibile tot anul</p>
        <h1 class="kh-h" id="kh-h"><?= v2_e($catName) ?> <em>în <?= v2_e($heroLocation) ?></em></h1>
        <?php if ($catDescription !== ''): ?><p class="kh-lead"><?= v2_e($catDescription) ?></p><?php endif; ?>
        <ul class="kh-stats">
          <li><?= v2_num((int) ($pagination['total'] ?? $eventCount), 'activitate', 'activități') ?></li>
          <?php if (!empty($children)): ?><li><?= v2_num(count($children), 'tip', 'tipuri') ?></li><?php endif; ?>
          <?php if (!empty($featuredCities)): ?><li><?= count($featuredCities) ?>+ orașe</li><?php endif; ?>
        </ul>
      </div>
      <div class="kh-media">
        <div class="kh-arch">
          <?php if ($heroImage): ?>
          <img src="<?= v2_e($heroImage) ?>"<?= $heroSrcset ? ' srcset="' . v2_e($heroSrcset) . '" sizes="(min-width: 1024px) 460px, 78vw"' : '' ?> width="640" height="800" alt="<?= v2_e($catName) ?>" fetchpriority="high" decoding="async">
          <?php else: ?>
          <?= v2_fallback($catName) ?>
          <?php endif; ?>
        </div>
      </div>
    </div>
  </section>
  <div id="hdr-sentinel" aria-hidden="true"></div>

  <!-- ============================== FILTER BAR ============================== -->
  <div class="kbar" id="k-bar">
    <div class="wrap kbar-in">
      <div class="kbar-pills">
        <button class="kpill kpill-map" type="button" data-open-map aria-haspopup="dialog" aria-controls="k-map"><?= v2_ic('map-pin') ?>Hartă</button>
        <button class="kpill" type="button" data-open-filters="" aria-haspopup="dialog" aria-controls="k-filters"><?= v2_ic('list') ?>Filtre<span class="kn" data-fcount hidden>0</span></button>
        <?php if (!empty($children)): ?>
        <button class="kpill" type="button" data-subcat aria-expanded="false" aria-controls="k-sub">Alege tipul de <?= v2_e($catLower) ?><?= v2_ic('caret-down', 'ic kcaret') ?></button>
        <?php endif; ?>
        <?php foreach ($quickFilters as [$key, $label]): ?>
        <button class="kpill" type="button" data-top="<?= $key ?>" aria-expanded="false" aria-controls="kp-<?= $key ?>"><?= v2_e($label) ?></button>
        <?php endforeach; ?>
        <button class="kclear" type="button" data-reset data-reset-bar hidden>Șterge tot</button>
      </div>
      <label class="ksort"><span>Sortare</span>
        <select class="select" id="k-sort">
          <option value="recommended">Recomandate</option>
          <?php if ($hasRatings): ?><option value="rating">Rating</option><?php endif; ?>
          <option value="priceAsc">Preț crescător</option>
          <option value="priceDesc">Preț descrescător</option>
          <option value="duration">Durată scurtă</option>
        </select>
      </label>
      <?php foreach ($quickFilters as [$key, $label, $title]): ?>
      <div class="kpop" id="kp-<?= $key ?>" role="dialog" aria-labelledby="kp-<?= $key ?>-h" hidden>
        <div class="kpop-top">
          <div><p class="kicker"><?= v2_e($label) ?></p><h3 id="kp-<?= $key ?>-h"><?= v2_e($title) ?></h3></div>
          <button class="icon-btn" type="button" data-pop-close><?= v2_ic('x') ?><span class="sr">Închide</span></button>
        </div>
        <?php $renderControl($key); ?>
        <div class="kpop-foot">
          <button class="btn btn-ghost" type="button" data-clear="<?= $key ?>">Curăță</button>
          <button class="btn btn-primary" type="button" data-pop-close>Aplică</button>
        </div>
      </div>
      <?php endforeach; ?>
    </div>
  </div>

  <!-- ============================== SUBCATEGORIES ============================== -->
  <?php if (!empty($children)): ?>
  <section class="ksub" id="k-sub" aria-labelledby="k-sub-h">
    <div class="ksub-clip">
      <div class="ksub-in">
        <div class="wrap ksub-pad">
          <div class="ksub-head">
            <h2 id="k-sub-h">Alege tipul de <?= v2_e($catLower) ?></h2>
            <button class="link-btn" type="button" data-subcat><?= count($children) ?> opțiuni · închide</button>
          </div>
          <ul class="ksub-grid">
            <?php foreach ($children as $child):
                $childName = navFlatName($child['name'] ?? '');
                $childSlug = $child['slug'] ?? '';
                if (!$childName || !$childSlug) continue;
                $child['parent_slug'] = $category['slug'] ?? '';
                $childCount = (int) ($child['event_count'] ?? 0);
            ?>
            <li><a class="ksc" href="/<?= v2_e(bo_short_category_slug($child)) ?>"><span class="ksc-ic" aria-hidden="true"><?= v2_e(mb_substr($childName, 0, 1)) ?></span><span><b><?= v2_e($childName) ?></b><small><?= $childCount > 0 ? v2_num($childCount, 'activitate', 'activități') : 'în curând' ?></small></span></a></li>
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
      <h2 class="sr" id="kres-h"><?= v2_e($catName) ?>: activități</h2>
      <?php if ($serverChips): ?>
      <ul class="kactive" aria-label="Filtre din adresă">
        <?php foreach ($serverChips as [$label, $href]): ?><li><a class="achip" href="<?= v2_e($href) ?>"><?= v2_e($label) ?><?= v2_ic('x') ?><span class="sr"> (elimină)</span></a></li><?php endforeach; ?>
      </ul>
      <?php endif; ?>
      <div class="kchips" id="k-chips" aria-label="Filtre active"></div>

      <?php if ($acts): ?>
      <ul class="xp-grid" id="k-grid" data-reveal>
        <?php foreach ($acts as $i => $a): ?>
        <li class="xp" data-id="<?= $a['id'] ?>">
          <a href="<?= v2_e($a['href']) ?>">
            <span class="xp-media"><?= $a['image'] ? v2_photo([$a['image'], 0, 0, '']) : v2_fallback($a['title'], $i) ?><?php if ($a['badges']): ?><span class="xp-badges"><?php foreach ($a['badges'] as $b): ?><span><?= v2_e($b) ?></span><?php endforeach; ?></span><?php endif; ?></span>
            <span class="xp-body">
              <span class="xp-cat"><?= v2_e($a['category']) ?></span>
              <span class="xp-title"><?= v2_e($a['title']) ?></span>
              <span class="xp-meta"><?php if ($a['rating'] > 0): ?><span class="xp-rating"><?= v2_ic('star') ?><?= str_replace('.', ',', (string) $a['rating']) ?><?php if ($a['reviews'] > 0): ?> (<?= v2_thousands($a['reviews']) ?>)<?php endif; ?></span><?php endif; ?><?php if ($a['durationLabel']): ?><span><?= v2_ic('clock') ?><?= v2_e($a['durationLabel']) ?></span><?php endif; ?><?php if ($a['place']): ?><span><?= v2_ic('map-pin') ?><?= v2_e($a['place']) ?></span><?php endif; ?></span>
              <span class="xp-foot"><span class="xp-go">Vezi<?= v2_ic('arrow-right') ?></span><?php if ($a['price']): ?><span class="xp-price">de la<b><?= v2_thousands($a['price']) ?> lei</b></span><?php else: ?><span class="xp-price is-na"><b>Vezi preț</b></span><?php endif; ?></span>
            </span>
          </a>
          <button class="xp-fav" type="button" data-fav aria-pressed="false"><?= v2_ic('heart', 'ic ic-off') ?><?= v2_ic('heart-fill', 'ic ic-on') ?><span class="sr">Salvează <?= v2_e($a['title']) ?></span></button>
        </li>
        <?php endforeach; ?>
      </ul>
      <?php endif; ?>

      <div class="k-empty" id="k-empty"<?= $acts ? ' hidden' : '' ?>>
        <h3>Nu am găsit activități.</h3>
        <p>Schimbă filtrele sau resetează căutarea.</p>
        <div class="k-empty-cta">
          <button class="btn btn-light" type="button" data-reset>Resetează filtrele</button>
          <?php if ($serverChips): ?><a class="btn btn-ghost" href="/<?= v2_e($slug) ?>">Toate activitățile din categorie</a><?php endif; ?>
        </div>
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
      <nav class="pager" aria-label="Pagini">
        <?php if ($current > 1): ?><a class="pg-step" href="<?= v2_e($catUrl(['page' => $current - 1 > 1 ? $current - 1 : ''])) ?>" rel="prev"><?= v2_ic('arrow-left') ?>Anterior</a><?php endif; ?>
        <?php for ($p = $start; $p <= $end; $p++): ?>
          <?php if ($p === $current): ?><span aria-current="page"><?= $p ?></span><?php else: ?><a href="<?= v2_e($catUrl(['page' => $p > 1 ? $p : ''])) ?>"><?= $p ?></a><?php endif; ?>
        <?php endfor; ?>
        <?php if ($current < $last): ?><a class="pg-step" href="<?= v2_e($catUrl(['page' => $current + 1])) ?>" rel="next">Următor<?= v2_ic('arrow-right') ?></a><?php endif; ?>
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
            Tot ce trebuie să știi despre <em><?= v2_e($catLower) ?></em>
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
          <p>Pe bilete.online găsești o selecție curată de <strong><?= v2_e($catLower) ?></strong> din toată România. Rezervi online data și ora dorită, plătești securizat și intri cu biletul QR direct la locație.</p>
        <?php endif; ?>

        <?php if (!empty($featuredCities)): ?>
        <div class="kx">
          <h3 class="flabel"><?= v2_e($catName) ?> pe orașe</h3>
          <div class="chips-links">
            <?php foreach (array_slice($featuredCities, 0, 8) as $c): ?><a href="/<?= v2_e($slug) ?>?city=<?= v2_e($c['slug']) ?>"><?= v2_e($catName) ?> <?= v2_e($c['name']) ?></a><?php endforeach; ?>
          </div>
        </div>
        <?php endif; ?>

        <?php if ($siblings): ?>
        <div class="kx">
          <h3 class="flabel">Categorii înrudite</h3>
          <div class="chips-links">
            <?php foreach (array_slice($siblings, 0, 8) as $sib): ?><a href="<?= v2_e($sib['href']) ?>"><?= v2_e($sib['name']) ?></a><?php endforeach; ?>
          </div>
        </div>
        <?php endif; ?>
      </div>

      <div class="faq-col">
        <h2 id="kfaq-h">Întrebări frecvente</h2>
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
        <div><p class="kicker">Filtre</p><h2 id="kf-h">Filtrează activitățile</h2></div>
        <button class="icon-btn" type="button" data-dlg-close><?= v2_ic('x') ?><span class="sr">Închide filtrele</span></button>
      </div>
      <div class="kdlg-body">
        <div class="kdlg-tabs" role="tablist" aria-orientation="vertical" aria-label="Filtre" data-tabs>
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
        <button class="btn btn-ghost" type="button" data-reset>Șterge tot</button>
        <button class="btn btn-primary" type="button" data-dlg-close>Arată <span data-count><?= count($acts) ?></span> rezultate</button>
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
              <p class="kicker">Hartă</p>
              <h2 id="km-h"><?= v2_e($catName) ?><?= $cityFilter ? ' în ' . v2_e($heroLocation) : '' ?></h2>
              <p class="kmap-n" id="k-map-n" aria-live="polite"><span data-count><?= count($acts) ?></span> rezultate pe hartă</p>
              <p class="kmap-zone" id="k-map-zone" hidden><span id="k-map-zone-text"></span><button class="link-btn" type="button" data-map-all>Arată toate</button></p>
              <p class="kmap-note" id="k-map-note" hidden>Unele activități apar pe hartă în centrul orașului, acolo unde locul exact lipsește.</p>
            </div>
            <button class="icon-btn" type="button" data-dlg-close><?= v2_ic('x') ?><span class="sr">Închide harta</span></button>
          </div>
          <div class="kmap-quick">
            <button class="kpill" type="button" data-open-filters="" aria-haspopup="dialog" aria-controls="k-filters"><?= v2_ic('list') ?>Filtre</button>
            <button class="kpill" type="button" data-open-filters="price" aria-haspopup="dialog" aria-controls="k-filters">Preț</button>
            <button class="kpill" type="button" data-open-filters="duration" aria-haspopup="dialog" aria-controls="k-filters">Durată</button>
          </div>
        </div>
        <ul class="kmap-list" id="k-map-list"></ul>
      </aside>
      <section class="kmap-view" aria-label="Previzualizare hartă">
        <div class="kmap-canvas" id="k-map-canvas" data-carto-key="<?= v2_e(defined('CARTO_API_KEY') ? CARTO_API_KEY : '') ?>"></div>
        <div id="k-map-pins"></div>
        <div class="kmap-card" id="k-map-card" hidden></div>
      </section>
    </div>
  </div>
</main>
<?php include __DIR__ . '/includes/v2/footer.php'; ?>
