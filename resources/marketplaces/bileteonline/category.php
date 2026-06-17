<?php
/**
 * Single category landing — /{category-slug}.
 *
 * Pure render: expects $_GET['slug'] (or `$slug` already set by an
 * including script — e.g. slug.php dispatcher). If the slug isn't a real
 * category we 404 here — city resolution lives in slug.php, not here.
 */

$pageCacheTTL = 300;
require_once __DIR__ . '/includes/page-cache.php';
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/api.php';
require_once __DIR__ . '/includes/nav-helpers.php';

$slug = $slug ?? ($_GET['slug'] ?? '');

if (!preg_match('/^[a-z][a-z0-9-]+$/', $slug)) {
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

// ============================================================
// Category data extraction — name/description come pre-translated
// (strings) from CategoriesController::show()
// ============================================================
$catName = navFlatName($category['name'] ?? '');
$catDescription = navFlatName($category['description'] ?? '');
$catIcon = $category['icon_emoji'] ?? '🎫';
$catColor = $category['color'] ?? '#E84527';
$accent = navAccentFromHex($catColor);
$catImage = $category['image'] ?? null;
$eventCount = (int) ($category['event_count'] ?? 0);

$metaTitle = navFlatName($category['meta_title'] ?? '') ?: ($catName . ' — bilete & rezervări online | bilete.online');
$metaDescription = navFlatName($category['meta_description'] ?? '') ?: ($catDescription ?: ('Activități din categoria ' . $catName . ' pe bilete.online. Rezervi online, intri cu QR.'));

$parent = $category['parent'] ?? null;
$children = $category['children'] ?? [];

// Admin-managed SEO body (RichEditor HTML) + custom FAQs. Both optional —
// frontend falls back to generic copy / generated FAQ when unset.
$seoBodyTitle = navFlatName($category['seo_body_title'] ?? '');
$seoBodyHtml = navFlatName($category['seo_body'] ?? '');
$adminFaqs = $category['faqs'] ?? [];
if (!is_array($adminFaqs)) $adminFaqs = [];

// Accent → Tailwind classes (safelisted)
$accentMap = [
    'vermilion' => ['bg' => 'bg-vermilion', 'bg-light' => 'bg-vermilion/10', 'text' => 'text-vermilion', 'bg-dark' => 'bg-vermilion-d', 'gradient' => 'from-vermilion to-vermilion-d', 'border' => 'border-vermilion'],
    'forest'    => ['bg' => 'bg-forest',    'bg-light' => 'bg-forest/10',    'text' => 'text-forest',    'bg-dark' => 'bg-forest-l',    'gradient' => 'from-forest-l to-forest',    'border' => 'border-forest'],
    'ochre'     => ['bg' => 'bg-ochre',     'bg-light' => 'bg-ochre/15',     'text' => 'text-ochre',     'bg-dark' => 'bg-vermilion-d', 'gradient' => 'from-ochre to-vermilion-d', 'border' => 'border-ochre'],
    'sky'       => ['bg' => 'bg-sky',       'bg-light' => 'bg-sky/10',       'text' => 'text-sky',       'bg-dark' => 'bg-ink-2',       'gradient' => 'from-sky to-ink',           'border' => 'border-sky'],
];
$ac = $accentMap[$accent] ?? $accentMap['vermilion'];

// ============================================================
// Events fetch (paginated) + filter parsing
// ============================================================
$pageNum = max(1, (int) ($_GET['page'] ?? 1));
$cityFilter = isset($_GET['city']) && preg_match('/^[a-z][a-z0-9-]+$/', $_GET['city']) ? $_GET['city'] : null;
$searchQuery = isset($_GET['q']) ? trim($_GET['q']) : '';
$searchQuery = mb_substr($searchQuery, 0, 80); // hard cap

// Price filter — single max_price chip (whitelisted values to keep cache key small + DOS-safe)
$priceMaxAllowed = [50, 100, 200, 500];
$maxPrice = (isset($_GET['max_price']) && in_array((int) $_GET['max_price'], $priceMaxAllowed, true))
    ? (int) $_GET['max_price']
    : null;

// Sort — whitelisted server-side values matching what MarketplaceEventsController supports
$sortAllowed = ['recommended', 'price_asc', 'price_desc', 'name_asc', 'date_asc'];
$sort = (isset($_GET['sort']) && in_array($_GET['sort'], $sortAllowed, true)) ? $_GET['sort'] : 'recommended';

$apiCacheKey = "category_events_{$slug}_" . ($cityFilter ?? 'all') . '_' . md5($searchQuery) . "_mp{$maxPrice}_s{$sort}_p{$pageNum}";
$eventsResp = api_cached($apiCacheKey, function () use ($slug, $cityFilter, $searchQuery, $maxPrice, $sort, $pageNum) {
    $params = ['category' => $slug, 'page' => $pageNum, 'per_page' => 24, 'time_scope' => 'upcoming'];
    if ($cityFilter) $params['city'] = $cityFilter;
    if ($searchQuery !== '') $params['search'] = $searchQuery;
    if ($maxPrice !== null) $params['max_price'] = $maxPrice;
    // Don't send `recommended` — let the API use its default (date_asc for upcoming)
    if ($sort !== 'recommended') $params['sort'] = $sort;
    return api_get('/events', $params);
}, 300);

$events = $eventsResp['data'] ?? [];
$evPagination = $eventsResp['meta'] ?? ['current_page' => 1, 'last_page' => 1, 'total' => count($events)];
if (!is_array($events)) $events = [];

// Activities in this category/subcategory + city. bilete.online is activity-
// centric, so the listing must include ACTIVITIES, not just ticketed events.
// /activities filters by exact category slug (parent OR subcategory) + city —
// pass the resolved category's real slug so subcategory pages match too.
$activityCatSlug = $category['slug'] ?? $slug;
$actCacheKey = "category_activities_{$activityCatSlug}_" . ($cityFilter ?? 'all') . '_' . md5($searchQuery) . "_mp{$maxPrice}_s{$sort}_p{$pageNum}";
$actResp = api_cached($actCacheKey, function () use ($activityCatSlug, $cityFilter, $searchQuery, $maxPrice, $sort, $pageNum) {
    $params = ['category' => $activityCatSlug, 'page' => $pageNum, 'per_page' => 24];
    if ($cityFilter) $params['city'] = $cityFilter;
    if ($searchQuery !== '') $params['search'] = $searchQuery;
    if ($maxPrice !== null) $params['max_price_ron'] = $maxPrice;
    if ($sort === 'price_asc') $params['sort'] = 'cheapest';
    return api_get('/activities', $params);
}, 300);
$activities = $actResp['data']['items'] ?? [];
if (!is_array($activities)) $activities = [];
$actPagination = $actResp['data']['pagination'] ?? ['last_page' => 1, 'total' => count($activities)];

// Unified card list — activities first (primary content), then events.
$cards = [];
foreach ($activities as $a) {
    $cards[] = [
        'title'       => is_array($a['title'] ?? null) ? navFlatName($a['title']) : ($a['title'] ?? ''),
        'city'        => $a['city']['name'] ?? ($a['venue']['city'] ?? ''),
        'cover'       => $a['cover_image_url'] ?? '',
        'price_cents' => $a['cheapest_price_cents'] ?? null,
        'url'         => ($a['city']['slug'] ?? '') ? '/' . $a['city']['slug'] . '/' . ($a['slug'] ?? '') : '/activitate/' . ($a['slug'] ?? ''),
        'cta'         => 'Vezi activitatea',
    ];
}
foreach ($events as $ev) {
    $cards[] = [
        'title'       => navEventTitle($ev),
        'city'        => navEventCity($ev),
        'cover'       => $ev['cover_image_url'] ?? $ev['image_url'] ?? '',
        'price_cents' => $ev['cheapest_price_cents'] ?? null,
        'url'         => '/bilete/' . ($ev['slug'] ?? ''),
        'cta'         => 'Vezi bilete',
    ];
}

// Combined pagination: both sources paginate independently; surface the deeper
// page count and the summed total (used for the hero "ACTIVITĂȚI" stat).
$pagination = [
    'current_page' => $pageNum,
    'last_page'    => max((int) ($evPagination['last_page'] ?? 1), (int) ($actPagination['last_page'] ?? 1)),
    'total'        => (int) ($evPagination['total'] ?? count($events)) + (int) ($actPagination['total'] ?? count($activities)),
];

// Featured cities for filter dropdown
$featuredCities = navGetCities(30);

// Resolve city label for the hero "în {location}" line when a city filter is active.
$heroLocation = 'România';
if ($cityFilter) {
    foreach ($featuredCities as $c) {
        if ($c['slug'] === $cityFilter) { $heroLocation = $c['label']; break; }
    }
    if ($heroLocation === 'România') {
        // Featured cities list didn't include this slug — best-effort prettify.
        $heroLocation = ucwords(str_replace('-', ' ', $cityFilter));
    }
}

// Active filter chips for UI
$activeChips = [];
if ($searchQuery !== '') {
    $activeChips[] = ['label' => '„' . $searchQuery . '"', 'key' => 'q', 'remove_qs' => navResetQueryString($_GET, ['q'])];
}
if ($cityFilter) {
    $cityLabel = '';
    foreach ($featuredCities as $c) { if ($c['slug'] === $cityFilter) { $cityLabel = $c['label']; break; } }
    if (!$cityLabel) $cityLabel = ucwords(str_replace('-', ' ', $cityFilter));
    $activeChips[] = ['label' => $cityLabel, 'key' => 'city', 'remove_qs' => navResetQueryString($_GET, ['city'])];
}
if ($maxPrice !== null) {
    $activeChips[] = ['label' => 'Sub ' . $maxPrice . ' lei', 'key' => 'max_price', 'remove_qs' => navResetQueryString($_GET, ['max_price'])];
}
if ($sort !== 'recommended') {
    $sortLabels = [
        'price_asc' => 'Preț crescător',
        'price_desc' => 'Preț descrescător',
        'name_asc' => 'Alfabetic',
        'date_asc' => 'Data evenimentului',
    ];
    $activeChips[] = ['label' => 'Sortat: ' . ($sortLabels[$sort] ?? $sort), 'key' => 'sort', 'remove_qs' => navResetQueryString($_GET, ['sort'])];
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
// Canonical URL — always the short form. Even when this page renders
// from the legacy long slug (e.g. the customer typed it directly), we
// tell Google + the browser that the short URL is the real one. slug.php
// also 301-redirects long → short, so this stays consistent across paths.
$shortSlug = bo_short_category_slug($category ?? ['slug' => $slug]);
$canonicalUrl = SITE_URL . '/' . ($shortSlug ?: $slug);
$ogImage = $catImage ? (str_starts_with($catImage, 'http') ? $catImage : STORAGE_URL . '/' . ltrim($catImage, '/')) : (SITE_URL . '/assets/images/og-default.jpg');
$currentPage = 'category';
$cssBundle = 'listing';

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

// JSON-LD: CollectionPage + ItemList + FAQPage
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
if (!empty($adminFaqs)) {
    $faqItems = array_values(array_filter($adminFaqs, fn ($f) => !empty($f['q']) && !empty($f['a'])));
}
if (empty($faqItems ?? null)) {
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

// ============================================================
// GYG filters + map view — seed REAL activities for client-side
// filtering. Only filter dimensions backed by real data are exposed
// (search, subcategories, price, languages, duration, features, rating);
// start-time / interests / places land with their data modules (F3).
// ============================================================
$headerContext = $cityFilter
    ? ['type' => 'city', 'label' => $heroLocation, 'slug' => $cityFilter]
    : ['type' => 'category', 'label' => $catName, 'slug' => ($shortSlug ?: $slug)];

$bo_img = function ($u) {
    $u = (string) $u;
    if ($u === '') return '';
    return str_starts_with($u, 'http') ? $u : rtrim(STORAGE_URL, '/') . '/' . ltrim($u, '/');
};

$gygActivities = [];
$ix = 0;
foreach ($activities as $a) {
    $title = is_array($a['title'] ?? null) ? navFlatName($a['title']) : ($a['title'] ?? '');
    $aslug = $a['slug'] ?? '';
    if ($title === '' || $aslug === '') continue;
    $citySlugA = $a['city']['slug'] ?? '';
    $href = $citySlugA ? '/' . $citySlugA . '/' . $aslug : '/activitate/' . $aslug;
    $priceLei = isset($a['cheapest_price_cents']) && $a['cheapest_price_cents'] !== null ? (int) round($a['cheapest_price_cents'] / 100) : 0;
    $dur = (int) ($a['duration_minutes'] ?? 0);
    $langs = array_values(array_unique(array_filter(array_map(
        fn ($l) => strtolower(substr((string) (is_array($l) ? ($l['code'] ?? $l['name'] ?? '') : $l), 0, 2)),
        (array) ($a['languages_offered'] ?? [])
    ))));
    $flags = $a['flags'] ?? [];
    $features = [];
    if (!empty($flags['is_kid_friendly'])) $features[] = 'family';
    if (!empty($flags['is_accessible']))   $features[] = 'wheelchair';
    if (!empty($flags['is_indoor']))        $features[] = 'indoor';
    if (!empty($flags['is_outdoor']))       $features[] = 'outdoor';
    $rev = $a['reviews'] ?? null;
    $badges = [];
    if (!empty($flags['is_featured'])) $badges[] = 'Recomandat';
    $gygActivities[] = [
        'id'              => (int) ($a['id'] ?? (++$ix)),
        'title'           => $title,
        'href'            => $href,
        'category'        => $a['category']['name'] ?? $catName,
        'categorySlug'    => $a['category']['slug'] ?? '',
        'image'           => $bo_img($a['cover_image_url'] ?? ''),
        'place'           => $a['city']['name'] ?? $heroLocation,
        'description'     => mb_substr(trim(strip_tags((string) navFlatName($a['short_description'] ?? ''))), 0, 160),
        'rating'          => $rev && isset($rev['average']) ? round((float) $rev['average'], 1) : 0,
        'reviews'         => $rev && isset($rev['count']) ? (int) $rev['count'] : 0,
        'price'           => $priceLei,
        'duration'        => $dur > 0 ? ($dur < 60 ? 'short' : ($dur <= 90 ? 'medium' : 'long')) : '',
        'durationLabel'   => $dur > 0 ? ($dur . ' min') : '',
        'languages'       => $langs,
        'features'        => $features,
        'interests'       => array_values(array_filter(array_map(fn ($i) => $i['slug'] ?? '', (array) ($a['interests'] ?? [])))),
        'travelerTypes'   => array_values(array_filter(array_map(fn ($t) => $t['slug'] ?? '', (array) ($a['traveler_types'] ?? [])))),
        'badges'          => $badges,
        'freeCancellation' => false,
        'favorite'        => false,
        '_lat'            => isset($a['venue']['lat']) ? (float) $a['venue']['lat'] : (isset($a['latitude']) ? (float) $a['latitude'] : null),
        '_lng'            => isset($a['venue']['lng']) ? (float) $a['venue']['lng'] : (isset($a['longitude']) ? (float) $a['longitude'] : null),
    ];
    $ix++;
}

// Map pins: normalize real lat/lng into x/y% (bbox); golden-angle scatter when
// coords are missing so the "map preview" stays evenly populated.
$withCoords = array_filter($gygActivities, fn ($a) => $a['_lat'] !== null && $a['_lng'] !== null);
$minLat = $maxLat = $minLng = $maxLng = null;
if (count($withCoords) >= 2) {
    $lats = array_column($withCoords, '_lat');
    $lngs = array_column($withCoords, '_lng');
    $minLat = min($lats); $maxLat = max($lats); $minLng = min($lngs); $maxLng = max($lngs);
}
foreach ($gygActivities as $k => $a) {
    if ($minLat !== null && $a['_lat'] !== null && $a['_lng'] !== null && ($maxLat - $minLat) > 0 && ($maxLng - $minLng) > 0) {
        $x = 10 + ($a['_lng'] - $minLng) / ($maxLng - $minLng) * 80;
        $y = 10 + ($maxLat - $a['_lat']) / ($maxLat - $minLat) * 80;
    } else {
        $ang = $k * 137.508 * M_PI / 180;
        $x = 50 + cos($ang) * (18 + ($k % 4) * 6);
        $y = 50 + sin($ang) * (14 + ($k % 3) * 7);
    }
    $gygActivities[$k]['map'] = ['x' => round(max(6, min(94, $x)), 1), 'y' => round(max(8, min(90, $y)), 1)];
    unset($gygActivities[$k]['_lat'], $gygActivities[$k]['_lng']);
}
$gygActivities = array_values($gygActivities);

// Filter options derived from real data only.
$catOptions = [];
foreach ((array) $children as $ch) {
    $cs = $ch['slug'] ?? ''; $cn = navFlatName($ch['name'] ?? '');
    if ($cs === '' || $cn === '') continue;
    $catOptions[] = ['value' => $cs, 'label' => $cn];
}
if (empty($catOptions)) {
    $seenCat = [];
    foreach ($gygActivities as $a) {
        $cs = $a['categorySlug'];
        if ($cs === '' || isset($seenCat[$cs])) continue;
        $seenCat[$cs] = true;
        $catOptions[] = ['value' => $cs, 'label' => $a['category']];
    }
}

$langLabels = ['ro' => 'Română', 'en' => 'Engleză', 'de' => 'Germană', 'fr' => 'Franceză', 'es' => 'Spaniolă', 'it' => 'Italiană', 'hu' => 'Maghiară'];
$langPresent = [];
foreach ($gygActivities as $a) foreach ($a['languages'] as $l) $langPresent[$l] = true;
$langOptions = [];
foreach (array_keys($langPresent) as $l) $langOptions[] = ['value' => $l, 'label' => $langLabels[$l] ?? mb_strtoupper($l)];

$featLabels = ['family' => 'Potrivit pentru familie', 'wheelchair' => 'Accesibil', 'indoor' => 'Indoor', 'outdoor' => 'Outdoor'];
$featPresent = [];
foreach ($gygActivities as $a) foreach ($a['features'] as $f) $featPresent[$f] = true;
$featOptions = [];
foreach ($featLabels as $fk => $fl) if (isset($featPresent[$fk])) $featOptions[] = ['value' => $fk, 'label' => $fl];

// F3 — interests + traveler types options, built from slug→name maps over the
// real activity payloads (only values actually present become filters).
$interestNames = [];
$travelerNames = [];
foreach ($activities as $a) {
    foreach ((array) ($a['interests'] ?? []) as $i) {
        if (! empty($i['slug'])) $interestNames[$i['slug']] = $i['name'] ?? $i['slug'];
    }
    foreach ((array) ($a['traveler_types'] ?? []) as $t) {
        if (! empty($t['slug'])) $travelerNames[$t['slug']] = $t['name'] ?? $t['slug'];
    }
}
$interestOptions = [];
foreach ($interestNames as $slugK => $nameK) $interestOptions[] = ['value' => $slugK, 'label' => $nameK];
$travelerOptions = [];
foreach ($travelerNames as $slugK => $nameK) $travelerOptions[] = ['value' => $slugK, 'label' => $nameK];

$gygPriceVals = array_filter(array_map(fn ($a) => $a['price'], $gygActivities));
$gygPriceMax = $gygPriceVals ? (int) (ceil(max($gygPriceVals) / 50) * 50) : 250;
if ($gygPriceMax < 100) $gygPriceMax = 100;

$gygHasRatings = (bool) array_filter($gygActivities, fn ($a) => $a['rating'] > 0);

$gygSeed = json_encode([
    'activities'      => $gygActivities,
    'categoryOptions' => $catOptions,
    'languageOptions' => $langOptions,
    'featureOptions'  => $featOptions,
    'interestOptions' => $interestOptions,
    'travelerOptions' => $travelerOptions,
    'durationOptions' => [
        ['value' => 'short', 'label' => 'Sub 60 min'],
        ['value' => 'medium', 'label' => '60–90 min'],
        ['value' => 'long', 'label' => '90+ min'],
    ],
    'ratingOptions' => [
        ['value' => 0, 'label' => 'Orice rating'],
        ['value' => 4, 'label' => '4.0+'],
        ['value' => 4.5, 'label' => '4.5+'],
        ['value' => 4.8, 'label' => '4.8+'],
    ],
    'priceMax'     => $gygPriceMax,
    'hasRatings'   => $gygHasRatings,
    'catName'      => $catName,
    'cityLabel'    => $cityFilter ? $heroLocation : '',
], JSON_UNESCAPED_UNICODE);

include __DIR__ . '/includes/head.php';
include __DIR__ . '/includes/header.php';
?>

<!-- ============================== HERO (compact, imagine ca background) ============================== -->
<?php $catImageUrl = $catImage ? $bo_img($catImage) : ''; ?>
<section class="relative overflow-hidden border-b-2 border-ink <?= $catImageUrl ? 'text-paper' : '' ?>">
    <?php if ($catImageUrl): ?>
        <img src="<?= htmlspecialchars($catImageUrl, ENT_QUOTES) ?>" alt="<?= htmlspecialchars($catName, ENT_QUOTES) ?>" class="absolute inset-0 object-cover w-full h-full -z-10" loading="eager">
        <div class="absolute inset-0 -z-10 bg-gradient-to-r from-ink/90 via-ink/70 to-ink/40"></div>
    <?php else: ?>
        <div class="absolute inset-0 -z-10 bg-gradient-to-b from-paper via-paper to-paper-2"></div>
        <div class="absolute -z-10 -top-24 -right-28 w-[420px] h-[420px] rounded-full <?= $ac['bg'] ?>/10 blur-3xl" aria-hidden="true"></div>
    <?php endif; ?>

    <div class="px-4 py-8 mx-auto max-w-7xl sm:px-6 lg:py-10">
        <div class="inline-flex items-center gap-2 mb-4 px-3 py-1 rounded-full text-xs font-mono tracking-wider <?= $catImageUrl ? 'bg-paper/15 text-paper' : $ac['bg-light'] . ' ' . $ac['text'] ?>">
            <span class="w-1.5 h-1.5 rounded-full <?= $catImageUrl ? 'bg-paper' : $ac['bg'] ?>"></span> CATEGORIE · DISPONIBILE TOT ANUL
        </div>

        <h1 class="flex flex-wrap items-center gap-3 font-display text-[clamp(1.9rem,4vw,3.2rem)] font-700 leading-[0.95]">
            <?php if ($catIcon): ?>
                <span class="grid w-12 h-12 text-2xl shrink-0 place-items-center rounded-xl <?= $catImageUrl ? 'bg-paper/15' : $ac['bg-light'] ?>"><?= htmlspecialchars($catIcon) ?></span>
            <?php endif; ?>
            <span><?= htmlspecialchars($catName) ?> <span class="ital <?= $catImageUrl ? 'text-paper/85' : $ac['text'] ?>">în <?= htmlspecialchars($heroLocation) ?></span></span>
        </h1>

        <?php if ($catDescription): ?>
            <p class="max-w-2xl mt-4 leading-relaxed line-clamp-2 <?= $catImageUrl ? 'text-paper/85' : 'text-ink-soft' ?>"><?= htmlspecialchars($catDescription) ?></p>
        <?php endif; ?>

        <div class="flex flex-wrap gap-2 mt-5 text-sm font-bold">
            <span class="rounded-full px-3.5 py-1.5 <?= $catImageUrl ? 'bg-paper/15 text-paper' : 'bg-paper-2 border border-ink/10' ?>"><?= (int) ($pagination['total'] ?? $eventCount) ?> activități</span>
            <?php if (!empty($children)): ?>
                <span class="rounded-full px-3.5 py-1.5 <?= $catImageUrl ? 'bg-paper/15 text-paper' : 'bg-paper-2 border border-ink/10' ?>"><?= count($children) ?> tipuri</span>
            <?php endif; ?>
            <?php if (!empty($featuredCities)): ?>
                <span class="rounded-full px-3.5 py-1.5 <?= $catImageUrl ? 'bg-paper/15 text-paper' : 'bg-paper-2 border border-ink/10' ?>"><?= count($featuredCities) ?>+ orașe</span>
            <?php endif; ?>
        </div>
    </div>
</section>

<!-- ============================== GYG FILTERS + MAP VIEW ============================== -->
<section x-data="categoryGyg(<?= htmlspecialchars($gygSeed, ENT_QUOTES) ?>)" x-init="init()" class="border-t border-ink/10">

    <!-- Sticky top filter bar -->
    <div class="sticky top-[72px] z-40 border-b border-ink/10 bg-paper/95 backdrop-blur-xl">
        <div class="px-4 py-3 mx-auto max-w-7xl sm:px-6">
            <div class="flex items-center gap-2 pb-1 overflow-x-auto">
                <button @click="openMap()" class="shrink-0 rounded-full border-2 border-ink bg-ink px-4 py-2.5 text-sm font-bold text-paper transition hover:bg-vermilion">Map view</button>
                <button @click="filtersModal=true" class="shrink-0 rounded-full border-2 border-ink bg-paper px-4 py-2.5 text-sm font-bold transition hover:bg-ink hover:text-paper">Filtre <span x-show="activeFilterCount()" class="ml-1 rounded-full bg-vermilion px-2 py-0.5 text-xs text-paper" x-text="activeFilterCount()"></span></button>
                <?php if (!empty($children)): ?>
                <button @click="subcatOpen = !subcatOpen" :class="subcatOpen ? 'bg-ink text-paper' : 'bg-paper'" class="shrink-0 inline-flex items-center gap-1.5 rounded-full border-2 border-ink px-4 py-2.5 text-sm font-bold transition hover:bg-ink hover:text-paper">
                    Alege tipul de <?= htmlspecialchars(mb_strtolower($catName)) ?>
                    <svg :class="subcatOpen && 'rotate-180'" class="w-3.5 h-3.5 transition-transform" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" aria-hidden="true"><path d="m6 9 6 6 6-6"/></svg>
                </button>
                <?php endif; ?>
                <template x-for="b in topButtons()" :key="b.key">
                    <button @click="openTopFilter(b.key)" class="shrink-0 rounded-full border-2 border-ink/10 bg-paper-2 px-4 py-2.5 text-sm font-bold transition hover:border-ink"><span x-text="b.label"></span><span x-show="topFilterActive(b.key)" class="ml-1 text-vermilion">•</span></button>
                </template>
                <button @click="resetFilters()" x-show="activeFilterCount()" x-cloak class="shrink-0 rounded-full px-4 py-2.5 text-sm font-bold text-vermilion hover:bg-rose">Șterge tot</button>
            </div>

            <!-- Compact top filter popover -->
            <div x-show="topFilterOpen" x-cloak x-transition @click.outside="topFilterOpen=null" class="absolute left-4 right-4 top-[calc(100%+8px)] z-50 mx-auto max-w-7xl rounded-[2rem] border-2 border-ink bg-paper p-5 shadow-deep sm:left-6 sm:right-6 lg:w-[720px]">
                <div class="flex items-start justify-between gap-4">
                    <div>
                        <p class="font-mono text-xs tracking-[.18em] text-ink-soft" x-text="topFilterMeta().kicker"></p>
                        <h3 class="text-4xl font-bold leading-none font-display" x-text="topFilterMeta().title"></h3>
                    </div>
                    <button @click="topFilterOpen=null" class="grid w-10 h-10 text-xl font-bold rounded-full place-items-center bg-ink text-paper">×</button>
                </div>
                <div class="mt-5">
                    <template x-if="topFilterOpen==='search'">
                        <input x-ref="searchInput" x-model="filters.search" class="w-full px-4 py-3 font-bold border-2 outline-none rounded-2xl border-ink/15 bg-paper focus:border-vermilion" placeholder="Caută după nume, locație, temă..." />
                    </template>
                    <template x-if="topFilterOpen==='price'">
                        <div>
                            <div class="flex items-center justify-between"><span class="font-bold">Preț maxim</span><strong x-text="formatMoney(filters.maxPrice)"></strong></div>
                            <input type="range" min="0" :max="priceCap" step="10" x-model.number="filters.maxPrice" class="w-full mt-4" style="accent-color:#E84527">
                            <div class="flex justify-between mt-2 text-xs font-bold text-ink-soft"><span>0 lei</span><span x-text="formatMoney(priceCap)"></span></div>
                        </div>
                    </template>
                    <template x-if="topFilterOpen==='duration'">
                        <div class="grid gap-2 sm:grid-cols-3">
                            <template x-for="o in durationOptions" :key="o.value">
                                <label class="flex items-center gap-3 px-4 py-3 font-bold cursor-pointer rounded-2xl bg-paper-2 hover:bg-paper"><input type="checkbox" :value="o.value" x-model="filters.durations" class="w-5 h-5" style="accent-color:#E84527"><span x-text="o.label"></span></label>
                            </template>
                        </div>
                    </template>
                    <template x-if="topFilterOpen==='languages'">
                        <div class="grid gap-2 sm:grid-cols-3">
                            <template x-for="o in languageOptions" :key="o.value">
                                <label class="flex items-center gap-3 px-4 py-3 font-bold cursor-pointer rounded-2xl bg-paper-2 hover:bg-paper"><input type="checkbox" :value="o.value" x-model="filters.languages" class="w-5 h-5" style="accent-color:#E84527"><span x-text="o.label"></span></label>
                            </template>
                        </div>
                    </template>
                    <template x-if="topFilterOpen==='features'">
                        <div class="grid gap-2 sm:grid-cols-2">
                            <template x-for="o in featureOptions" :key="o.value">
                                <label class="flex items-center gap-3 px-4 py-3 font-bold cursor-pointer rounded-2xl bg-paper-2 hover:bg-paper"><input type="checkbox" :value="o.value" x-model="filters.features" class="w-5 h-5" style="accent-color:#E84527"><span x-text="o.label"></span></label>
                            </template>
                        </div>
                    </template>
                    <template x-if="topFilterOpen==='interests'">
                        <div class="grid gap-2 sm:grid-cols-2">
                            <template x-for="o in interestOptions" :key="o.value">
                                <label class="flex items-center gap-3 px-4 py-3 font-bold cursor-pointer rounded-2xl bg-paper-2 hover:bg-paper"><input type="checkbox" :value="o.value" x-model="filters.interests" class="w-5 h-5" style="accent-color:#E84527"><span x-text="o.label"></span></label>
                            </template>
                        </div>
                    </template>
                    <template x-if="topFilterOpen==='traveler'">
                        <div class="grid gap-2 sm:grid-cols-2">
                            <template x-for="o in travelerOptions" :key="o.value">
                                <label class="flex items-center gap-3 px-4 py-3 font-bold cursor-pointer rounded-2xl bg-paper-2 hover:bg-paper"><input type="checkbox" :value="o.value" x-model="filters.travelerTypes" class="w-5 h-5" style="accent-color:#E84527"><span x-text="o.label"></span></label>
                            </template>
                        </div>
                    </template>
                </div>
                <div class="flex items-center justify-between gap-3 pt-4 mt-5 border-t border-ink/10">
                    <button @click="clearTopFilter()" class="px-5 py-3 font-bold border-2 rounded-full border-ink hover:bg-ink hover:text-paper">Curăță</button>
                    <button @click="topFilterOpen=null" class="px-5 py-3 font-bold rounded-full bg-vermilion text-paper hover:bg-vermilion-d">Aplică</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Subcategorii (togglate din bara de filtre) -->
    <?php if (!empty($children)): ?>
    <div x-show="subcatOpen" x-collapse x-cloak class="border-b border-ink/10 bg-paper-2">
        <div class="px-4 py-6 mx-auto max-w-7xl sm:px-6">
            <div class="flex items-end justify-between gap-4 mb-4">
                <h2 class="text-2xl leading-tight font-display sm:text-3xl font-700">Alege tipul de <?= htmlspecialchars(mb_strtolower($catName)) ?></h2>
                <button @click="subcatOpen=false" class="hidden text-sm font-bold sm:inline text-ink-soft hover:text-vermilion"><?= count($children) ?> opțiuni · închide</button>
            </div>
            <div class="grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-4">
                <?php foreach ($children as $child):
                    $childName = navFlatName($child['name'] ?? '');
                    $childSlug = $child['slug'] ?? '';
                    if (!$childName || !$childSlug) continue;
                    $childEmoji = $child['icon_emoji'] ?? '';
                    $childCount = (int) ($child['event_count'] ?? 0);
                ?>
                    <a href="/<?= htmlspecialchars(bo_short_category_slug($child), ENT_QUOTES) ?>" class="group flex items-center gap-3 p-4 rounded-2xl border-2 border-ink/10 bg-paper hover:border-ink transition-colors">
                        <?php if ($childEmoji): ?>
                            <span class="grid place-items-center w-10 h-10 rounded-lg <?= $ac['bg-light'] ?> <?= $ac['text'] ?> shrink-0 group-hover:<?= $ac['bg'] ?> group-hover:text-paper transition-colors text-xl leading-none" aria-hidden="true"><?= htmlspecialchars($childEmoji) ?></span>
                        <?php else: ?>
                            <span class="grid place-items-center w-10 h-10 rounded-lg <?= $ac['bg-light'] ?> <?= $ac['text'] ?> shrink-0 font-display text-lg font-700"><?= htmlspecialchars(mb_substr($childName, 0, 1)) ?></span>
                        <?php endif; ?>
                        <div class="flex-1 min-w-0">
                            <p class="text-sm leading-tight truncate font-600"><?= htmlspecialchars($childName) ?></p>
                            <?php if ($childCount > 0): ?>
                                <p class="text-xs text-ink-soft mt-0.5"><?= $childCount ?> <?= $childCount === 1 ? 'activitate' : 'activități' ?></p>
                            <?php else: ?>
                                <p class="text-xs text-ink-soft/60 mt-0.5">în curând</p>
                            <?php endif; ?>
                        </div>
                    </a>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- Results -->
    <div class="px-4 py-8 mx-auto max-w-7xl sm:px-6 lg:py-10">
        <div class="flex flex-col gap-4 mb-5 lg:flex-row lg:items-end lg:justify-between">
            <div>
                <p class="text-5xl font-bold leading-none font-display"><span x-text="filteredActivities().length"></span> rezultate</p>
                <p class="mt-2 text-ink-soft">Activitățile sunt ordonate după relevanță, rating și disponibilitate.</p>
            </div>
            <label class="flex items-center max-w-sm gap-2">
                <span class="text-sm font-bold text-ink-soft">Sortare</span>
                <select class="px-4 py-3 font-bold border-2 outline-none rounded-2xl border-ink/15 bg-paper focus:border-vermilion" x-model="sortBy">
                    <option value="recommended">Recomandate</option>
                    <option value="rating" x-show="hasRatings">Rating</option>
                    <option value="priceAsc">Preț crescător</option>
                    <option value="priceDesc">Preț descrescător</option>
                    <option value="duration">Durată scurtă</option>
                </select>
            </label>
        </div>

        <div class="flex flex-wrap gap-2 mb-6" x-show="activeChips().length" x-cloak>
            <template x-for="chip in activeChips()" :key="chip.key">
                <button @click="removeChip(chip)" class="px-4 py-2 text-sm font-bold rounded-full bg-ink text-paper"><span x-text="chip.label"></span> ×</button>
            </template>
        </div>

        <div class="space-y-5">
            <template x-for="activity in sortedActivities()" :key="activity.id">
                <article class="group overflow-hidden rounded-[2rem] border-2 border-ink bg-paper shadow-deep transition hover:-translate-y-0.5">
                    <div class="grid md:grid-cols-[290px_1fr]">
                        <a :href="activity.href" class="relative block min-h-[230px] overflow-hidden bg-ink">
                            <img x-show="activity.image" :src="activity.image" :alt="activity.title" class="object-cover w-full h-full transition duration-500 group-hover:scale-105" loading="lazy">
                            <div x-show="!activity.image" class="grid h-full place-items-center bg-gradient-to-br from-vermilion to-vermilion-d"><span class="text-5xl opacity-40">🎫</span></div>
                            <div class="absolute flex flex-wrap gap-2 left-4 top-4">
                                <template x-for="badge in activity.badges" :key="badge"><span class="px-3 py-1 text-xs font-bold rounded-full bg-paper text-ink" x-text="badge"></span></template>
                            </div>
                            <button @click.prevent="activity.favorite=!activity.favorite" class="absolute grid w-10 h-10 text-2xl rounded-full right-4 top-4 place-items-center bg-paper text-ink"><span x-text="activity.favorite ? '♥' : '♡'"></span></button>
                        </a>
                        <div class="grid gap-4 p-5 lg:grid-cols-[1fr_220px]">
                            <div>
                                <div class="flex flex-wrap gap-2">
                                    <span x-show="activity.category" class="px-3 py-1 text-xs font-bold rounded-full bg-paper-2 text-ink-soft" x-text="activity.category"></span>
                                </div>
                                <a :href="activity.href" class="block mt-3"><h3 class="text-4xl font-bold leading-none font-display group-hover:text-vermilion" x-text="activity.title"></h3></a>
                                <p x-show="activity.description" class="mt-3 text-ink-soft" x-text="activity.description"></p>
                                <div class="flex flex-wrap items-center gap-3 mt-4 text-sm font-bold">
                                    <template x-if="activity.rating > 0"><span class="text-ochre">★★★★★</span></template>
                                    <span x-show="activity.rating > 0" x-text="activity.rating"></span>
                                    <span x-show="activity.reviews > 0" class="text-ink-soft" x-text="'(' + activity.reviews.toLocaleString('ro-RO') + ')'"></span>
                                    <span x-show="activity.place" class="text-ink-soft">·</span>
                                    <span x-show="activity.place" x-text="activity.place"></span>
                                    <span x-show="activity.durationLabel" class="text-ink-soft">·</span>
                                    <span x-show="activity.durationLabel" x-text="activity.durationLabel"></span>
                                </div>
                                <div class="flex flex-wrap gap-2 mt-4">
                                    <template x-for="feature in activity.features" :key="feature"><span class="px-3 py-1 text-xs font-bold rounded-full bg-paper-2 text-ink-soft" x-text="featureLabel(feature)"></span></template>
                                </div>
                            </div>
                            <aside class="flex flex-col justify-between p-4 rounded-3xl bg-paper-2">
                                <div>
                                    <p class="text-sm font-bold text-ink-soft" x-show="activity.price">de la</p>
                                    <p class="text-4xl font-bold leading-none font-display" x-text="activity.price ? formatMoney(activity.price) : 'Vezi preț'"></p>
                                    <p x-show="activity.price" class="px-3 py-2 mt-3 text-sm font-bold rounded-2xl bg-mint text-forest" x-text="'+' + Math.floor(activity.price/5) + ' puncte bonus'"></p>
                                </div>
                                <a :href="activity.href" class="px-5 py-3 mt-4 font-bold text-center transition rounded-full bg-vermilion text-paper hover:bg-vermilion-d">Vezi bilete</a>
                            </aside>
                        </div>
                    </div>
                </article>
            </template>

            <div x-show="filteredActivities().length === 0" x-cloak class="rounded-[2rem] border-2 border-ink bg-paper p-8 text-center">
                <p class="text-5xl font-bold leading-none font-display">Nu am găsit activități.</p>
                <p class="mt-3 text-ink-soft">Schimbă filtrele sau resetează căutarea.</p>
                <button @click="resetFilters()" class="px-6 py-4 mt-5 font-bold rounded-full bg-vermilion text-paper">Resetează filtrele</button>
            </div>
        </div>
    </div>

    <!-- Filters modal -->
    <div x-show="filtersModal" x-cloak class="fixed inset-0 z-[90] bg-ink/70 p-3 backdrop-blur-sm sm:p-5" role="dialog" aria-modal="true" aria-label="Filtre activități">
        <div @click.outside="filtersModal=false" class="mx-auto flex max-h-[94vh] w-full max-w-5xl flex-col overflow-hidden rounded-[2rem] border-2 border-ink bg-paper shadow-deep">
            <div class="flex items-start justify-between gap-4 p-5 border-b border-ink/10 sm:p-6">
                <div>
                    <p class="font-mono text-xs tracking-[.18em] text-ink-soft">FILTRE</p>
                    <h2 class="text-5xl font-bold leading-none font-display">Filtrează activitățile</h2>
                </div>
                <button @click="filtersModal=false" class="grid text-2xl font-bold rounded-full h-11 w-11 shrink-0 place-items-center bg-ink text-paper">×</button>
            </div>
            <div class="grid min-h-0 flex-1 overflow-auto lg:grid-cols-[260px_1fr]">
                <aside class="p-4 border-b border-ink/10 bg-paper-2 lg:border-b-0 lg:border-r">
                    <template x-for="tab in modalTabs()" :key="tab.key">
                        <button @click="modalTab=tab.key" :class="modalTab===tab.key ? 'bg-ink text-paper' : 'hover:bg-paper'" class="flex items-center justify-between w-full px-4 py-3 mt-1 font-bold text-left transition rounded-2xl"><span x-text="tab.label"></span><span x-show="tabHasValue(tab.key)" class="rounded-full bg-vermilion px-2 py-0.5 text-xs text-paper">•</span></button>
                    </template>
                </aside>
                <section class="p-5 sm:p-6">
                    <div x-show="modalTab==='categories'">
                        <h3 class="text-4xl font-bold leading-none font-display">Categorii</h3>
                        <div class="grid gap-2 mt-5 sm:grid-cols-2">
                            <template x-for="o in categoryOptions" :key="o.value">
                                <label class="flex items-center gap-3 px-4 py-3 font-bold cursor-pointer rounded-2xl bg-paper-2 hover:bg-paper"><input type="checkbox" :value="o.value" x-model="filters.categories" class="w-5 h-5" style="accent-color:#E84527"><span x-text="o.label"></span></label>
                            </template>
                        </div>
                    </div>
                    <div x-show="modalTab==='interests'">
                        <h3 class="text-4xl font-bold leading-none font-display">Interese</h3>
                        <p class="mt-2 text-ink-soft">Alege atmosfera sau tema activității.</p>
                        <div class="grid gap-2 mt-5 sm:grid-cols-2">
                            <template x-for="o in interestOptions" :key="o.value">
                                <label class="flex items-center gap-3 px-4 py-3 font-bold cursor-pointer rounded-2xl bg-paper-2 hover:bg-paper"><input type="checkbox" :value="o.value" x-model="filters.interests" class="w-5 h-5" style="accent-color:#E84527"><span x-text="o.label"></span></label>
                            </template>
                        </div>
                    </div>
                    <div x-show="modalTab==='traveler'">
                        <h3 class="text-4xl font-bold leading-none font-display">Pentru cine</h3>
                        <p class="mt-2 text-ink-soft">Cui i se potrivește activitatea.</p>
                        <div class="grid gap-2 mt-5 sm:grid-cols-2">
                            <template x-for="o in travelerOptions" :key="o.value">
                                <label class="flex items-center gap-3 px-4 py-3 font-bold cursor-pointer rounded-2xl bg-paper-2 hover:bg-paper"><input type="checkbox" :value="o.value" x-model="filters.travelerTypes" class="w-5 h-5" style="accent-color:#E84527"><span x-text="o.label"></span></label>
                            </template>
                        </div>
                    </div>
                    <div x-show="modalTab==='price'">
                        <h3 class="text-4xl font-bold leading-none font-display">Preț</h3>
                        <div class="p-5 mt-6 rounded-3xl bg-paper-2">
                            <div class="flex items-center justify-between"><span class="font-bold">Preț maxim</span><strong x-text="formatMoney(filters.maxPrice)"></strong></div>
                            <input type="range" min="0" :max="priceCap" step="10" x-model.number="filters.maxPrice" class="w-full mt-5" style="accent-color:#E84527">
                            <div class="flex justify-between mt-2 text-xs font-bold text-ink-soft"><span>0 lei</span><span x-text="formatMoney(priceCap)"></span></div>
                        </div>
                    </div>
                    <div x-show="modalTab==='languages'">
                        <h3 class="text-4xl font-bold leading-none font-display">Limbă</h3>
                        <div class="grid gap-2 mt-5 sm:grid-cols-3">
                            <template x-for="o in languageOptions" :key="o.value">
                                <label class="flex items-center gap-3 px-4 py-3 font-bold cursor-pointer rounded-2xl bg-paper-2 hover:bg-paper"><input type="checkbox" :value="o.value" x-model="filters.languages" class="w-5 h-5" style="accent-color:#E84527"><span x-text="o.label"></span></label>
                            </template>
                        </div>
                    </div>
                    <div x-show="modalTab==='duration'">
                        <h3 class="text-4xl font-bold leading-none font-display">Durată</h3>
                        <div class="grid gap-2 mt-5 sm:grid-cols-3">
                            <template x-for="o in durationOptions" :key="o.value">
                                <label class="flex items-center gap-3 px-4 py-3 font-bold cursor-pointer rounded-2xl bg-paper-2 hover:bg-paper"><input type="checkbox" :value="o.value" x-model="filters.durations" class="w-5 h-5" style="accent-color:#E84527"><span x-text="o.label"></span></label>
                            </template>
                        </div>
                    </div>
                    <div x-show="modalTab==='features'">
                        <h3 class="text-4xl font-bold leading-none font-display">Caracteristici</h3>
                        <div class="grid gap-2 mt-5 sm:grid-cols-2">
                            <template x-for="o in featureOptions" :key="o.value">
                                <label class="flex items-center gap-3 px-4 py-3 font-bold cursor-pointer rounded-2xl bg-paper-2 hover:bg-paper"><input type="checkbox" :value="o.value" x-model="filters.features" class="w-5 h-5" style="accent-color:#E84527"><span x-text="o.label"></span></label>
                            </template>
                        </div>
                    </div>
                    <div x-show="modalTab==='rating'">
                        <h3 class="text-4xl font-bold leading-none font-display">Rating minim</h3>
                        <div class="grid gap-2 mt-5 sm:grid-cols-4">
                            <template x-for="o in ratingOptions" :key="o.value">
                                <button @click="filters.minRating=o.value" :class="filters.minRating===o.value ? 'bg-ink text-paper' : 'bg-paper-2'" class="px-4 py-4 font-bold text-left rounded-2xl hover:bg-ink hover:text-paper"><span class="block text-ochre">★★★★★</span><span x-text="o.label"></span></button>
                            </template>
                        </div>
                    </div>
                </section>
            </div>
            <div class="flex items-center justify-between gap-3 p-5 border-t border-ink/10 sm:p-6">
                <button @click="resetFilters()" class="px-5 py-3 font-bold border-2 rounded-full border-ink hover:bg-ink hover:text-paper">Șterge tot</button>
                <button @click="filtersModal=false" class="px-6 py-3 font-bold rounded-full bg-vermilion text-paper hover:bg-vermilion-d">Arată <span x-text="filteredActivities().length"></span> rezultate</button>
            </div>
        </div>
    </div>

    <!-- Map view modal -->
    <div x-show="mapOpen" x-cloak class="fixed inset-0 z-[95] bg-ink/80 p-2 backdrop-blur-sm sm:p-4" role="dialog" aria-modal="true" aria-label="Map view">
        <div @click.outside="mapOpen=false" class="mx-auto flex h-[96vh] max-w-[1700px] overflow-hidden rounded-[2rem] border-2 border-ink bg-paper shadow-deep">
            <aside class="flex flex-col w-full border-r border-ink/10 lg:w-1/3">
                <div class="p-4 border-b border-ink/10">
                    <div class="flex items-start justify-between gap-4">
                        <div>
                            <p class="font-mono text-xs tracking-[.18em] text-ink-soft">MAP VIEW</p>
                            <h2 class="text-3xl font-bold leading-none font-display"><?= htmlspecialchars($catName) ?><?= $cityFilter ? ' în ' . htmlspecialchars($heroLocation) : '' ?></h2>
                            <p class="mt-1 text-sm text-ink-soft"><span x-text="filteredActivities().length"></span> rezultate pe hartă</p>
                        </div>
                        <button @click="mapOpen=false" class="grid w-10 h-10 text-xl font-bold rounded-full place-items-center bg-ink text-paper">×</button>
                    </div>
                    <div class="flex gap-2 pb-1 mt-4 overflow-x-auto">
                        <button @click="filtersModal=true" class="px-4 py-2 text-sm font-bold rounded-full shrink-0 bg-ink text-paper">Filtre</button>
                        <button @click="openTopFilter('price')" class="px-4 py-2 text-sm font-bold rounded-full shrink-0 bg-paper-2">Preț</button>
                        <button @click="openTopFilter('duration')" class="px-4 py-2 text-sm font-bold rounded-full shrink-0 bg-paper-2">Durată</button>
                    </div>
                </div>
                <div class="flex-1 p-3 overflow-auto">
                    <template x-for="activity in sortedActivities()" :key="'l-'+activity.id">
                        <button @mouseenter="hoveredPin=activity.id" @mouseleave="hoveredPin=null" @click="selectedPin=activity.id" :class="selectedPin===activity.id ? 'border-vermilion bg-rose' : 'border-ink/10 bg-paper'" class="w-full p-3 mb-3 text-left transition border-2 rounded-3xl hover:border-vermilion">
                            <div class="grid grid-cols-[92px_1fr] gap-3">
                                <div class="w-full h-24 overflow-hidden rounded-2xl bg-ink">
                                    <img x-show="activity.image" :src="activity.image" :alt="activity.title" class="object-cover w-full h-full">
                                </div>
                                <div class="min-w-0">
                                    <p class="text-2xl font-bold leading-none line-clamp-2 font-display" x-text="activity.title"></p>
                                    <p class="mt-1 text-xs text-ink-soft" x-text="[activity.place, activity.durationLabel].filter(Boolean).join(' · ')"></p>
                                    <p x-show="activity.rating > 0" class="mt-2 text-sm font-bold"><span class="text-ochre">★★★★★</span> <span x-text="activity.rating"></span></p>
                                    <p class="mt-1 font-bold" x-text="activity.price ? ('de la ' + formatMoney(activity.price)) : 'Vezi preț'"></p>
                                </div>
                            </div>
                        </button>
                    </template>
                </div>
            </aside>

            <section class="relative flex-1 hidden lg:block">
                <div class="absolute inset-0" style="background:linear-gradient(90deg,rgba(27,23,20,.06) 1px,transparent 1px),linear-gradient(rgba(27,23,20,.06) 1px,transparent 1px),radial-gradient(circle at 20% 20%,rgba(232,69,39,.14),transparent 22%),radial-gradient(circle at 80% 70%,rgba(30,74,61,.16),transparent 28%),#D9E5D0;background-size:48px 48px,48px 48px,auto,auto,auto"></div>
                <div class="absolute z-10 px-4 py-2 text-sm font-bold rounded-full right-5 top-5 bg-paper shadow-deep"><?= htmlspecialchars($cityFilter ? $heroLocation : 'România') ?> · map preview</div>
                <template x-for="activity in sortedActivities()" :key="'pin-'+activity.id">
                    <button @mouseenter="hoveredPin=activity.id" @mouseleave="hoveredPin=null" @click="selectedPin=activity.id" class="absolute z-20 px-3 py-2 text-sm font-bold transition -translate-x-1/2 -translate-y-1/2 border-2 rounded-full border-paper text-paper shadow-deep hover:scale-110" :class="selectedPin===activity.id || hoveredPin===activity.id ? 'bg-vermilion' : 'bg-ink'" :style="'left:'+activity.map.x+'%; top:'+activity.map.y+'%;'">
                        <span x-text="activity.price ? formatMoney(activity.price) : '•'"></span>
                    </button>
                </template>
                <div x-show="selectedActivity()" x-cloak x-transition class="absolute bottom-5 left-5 z-30 w-[420px] overflow-hidden rounded-[2rem] border-2 border-ink bg-paper shadow-deep">
                    <template x-if="selectedActivity()">
                        <div>
                            <div class="w-full h-40 overflow-hidden bg-ink"><img x-show="selectedActivity().image" :src="selectedActivity().image" :alt="selectedActivity().title" class="object-cover w-full h-full"></div>
                            <div class="p-5">
                                <div class="flex items-start justify-between gap-4">
                                    <div>
                                        <p class="text-3xl font-bold leading-none font-display" x-text="selectedActivity().title"></p>
                                        <p class="mt-2 text-sm text-ink-soft" x-text="[selectedActivity().place, selectedActivity().durationLabel].filter(Boolean).join(' · ')"></p>
                                    </div>
                                    <button @click="selectedPin=null" class="grid rounded-full h-9 w-9 shrink-0 place-items-center bg-ink text-paper">×</button>
                                </div>
                                <div class="flex items-center justify-between mt-3">
                                    <p x-show="selectedActivity().rating > 0" class="font-bold"><span class="text-ochre">★★★★★</span> <span x-text="selectedActivity().rating"></span></p>
                                    <p class="text-3xl font-bold font-display" x-text="selectedActivity().price ? formatMoney(selectedActivity().price) : ''"></p>
                                </div>
                                <a :href="selectedActivity().href" class="block px-5 py-3 mt-4 font-bold text-center rounded-full bg-vermilion text-paper">Vezi bilete</a>
                            </div>
                        </div>
                    </template>
                </div>
            </section>
        </div>
    </div>
</section>

<script>
function categoryGyg(seed) {
    return {
        activities: (seed && seed.activities) || [],
        categoryOptions: (seed && seed.categoryOptions) || [],
        languageOptions: (seed && seed.languageOptions) || [],
        featureOptions: (seed && seed.featureOptions) || [],
        interestOptions: (seed && seed.interestOptions) || [],
        travelerOptions: (seed && seed.travelerOptions) || [],
        durationOptions: (seed && seed.durationOptions) || [],
        ratingOptions: (seed && seed.ratingOptions) || [],
        priceCap: (seed && seed.priceMax) || 250,
        hasRatings: !!(seed && seed.hasRatings),
        topFilterOpen: null, filtersModal: false, mapOpen: false, subcatOpen: false,
        modalTab: 'price', sortBy: 'recommended',
        hoveredPin: null, selectedPin: null,
        filters: { search: '', categories: [], interests: [], travelerTypes: [], maxPrice: (seed && seed.priceMax) || 250, languages: [], durations: [], features: [], minRating: 0 },

        init() {
            this.filters.maxPrice = this.priceCap;
            this.modalTab = this.categoryOptions.length ? 'categories' : 'price';
        },
        openMap() { this.mapOpen = true; this.topFilterOpen = null; },
        topButtons() {
            const b = [{ key: 'search', label: 'Caută' }, { key: 'price', label: 'Preț' }, { key: 'duration', label: 'Durată' }];
            if (this.interestOptions.length) b.push({ key: 'interests', label: 'Interese' });
            if (this.travelerOptions.length) b.push({ key: 'traveler', label: 'Pentru cine' });
            if (this.languageOptions.length) b.push({ key: 'languages', label: 'Limbă' });
            if (this.featureOptions.length) b.push({ key: 'features', label: 'Caracteristici' });
            return b;
        },
        modalTabs() {
            const t = [];
            if (this.categoryOptions.length) t.push({ key: 'categories', label: 'Categorii' });
            if (this.interestOptions.length) t.push({ key: 'interests', label: 'Interese' });
            if (this.travelerOptions.length) t.push({ key: 'traveler', label: 'Pentru cine' });
            t.push({ key: 'price', label: 'Preț' });
            if (this.languageOptions.length) t.push({ key: 'languages', label: 'Limbă' });
            t.push({ key: 'duration', label: 'Durată' });
            if (this.featureOptions.length) t.push({ key: 'features', label: 'Caracteristici' });
            if (this.hasRatings) t.push({ key: 'rating', label: 'Rating' });
            return t;
        },
        openTopFilter(key) {
            this.topFilterOpen = this.topFilterOpen === key ? null : key;
            if (key === 'search') this.$nextTick(() => this.$refs.searchInput && this.$refs.searchInput.focus());
        },
        topFilterMeta() {
            const m = { search: { kicker: 'CAUTĂ', title: 'Caută în categorie' }, price: { kicker: 'PREȚ', title: 'Bugetul tău' }, duration: { kicker: 'DURATĂ', title: 'Durata activității' }, languages: { kicker: 'LIMBĂ', title: 'Limba activității' }, features: { kicker: 'CARACTERISTICI', title: 'Caracteristici' }, interests: { kicker: 'INTERESE', title: 'Ce te atrage' }, traveler: { kicker: 'PENTRU CINE', title: 'Cui i se potrivește' } };
            return m[this.topFilterOpen] || { kicker: 'FILTRU', title: 'Filtru' };
        },
        topFilterActive(key) {
            if (key === 'search') return !!this.filters.search;
            if (key === 'price') return this.filters.maxPrice < this.priceCap;
            if (key === 'duration') return this.filters.durations.length;
            if (key === 'languages') return this.filters.languages.length;
            if (key === 'features') return this.filters.features.length;
            if (key === 'interests') return this.filters.interests.length;
            if (key === 'traveler') return this.filters.travelerTypes.length;
            return false;
        },
        tabHasValue(key) {
            if (key === 'categories') return this.filters.categories.length;
            if (key === 'interests') return this.filters.interests.length;
            if (key === 'traveler') return this.filters.travelerTypes.length;
            if (key === 'price') return this.filters.maxPrice < this.priceCap;
            if (key === 'languages') return this.filters.languages.length;
            if (key === 'duration') return this.filters.durations.length;
            if (key === 'features') return this.filters.features.length;
            if (key === 'rating') return this.filters.minRating > 0;
            return false;
        },
        clearTopFilter() {
            const k = this.topFilterOpen;
            if (k === 'search') this.filters.search = '';
            if (k === 'price') this.filters.maxPrice = this.priceCap;
            if (k === 'duration') this.filters.durations = [];
            if (k === 'languages') this.filters.languages = [];
            if (k === 'features') this.filters.features = [];
            if (k === 'interests') this.filters.interests = [];
            if (k === 'traveler') this.filters.travelerTypes = [];
        },
        resetFilters() {
            this.filters = { search: '', categories: [], interests: [], travelerTypes: [], maxPrice: this.priceCap, languages: [], durations: [], features: [], minRating: 0 };
            this.sortBy = 'recommended';
        },
        activeFilterCount() {
            let c = 0;
            if (this.filters.search) c++;
            c += this.filters.categories.length + this.filters.interests.length + this.filters.travelerTypes.length + this.filters.languages.length + this.filters.durations.length + this.filters.features.length;
            if (this.filters.maxPrice < this.priceCap) c++;
            if (this.filters.minRating > 0) c++;
            return c;
        },
        activeChips() {
            const chips = [];
            if (this.filters.search) chips.push({ key: 'search', label: 'Caută: ' + this.filters.search, type: 'search' });
            this.filters.categories.forEach(v => chips.push({ key: 'cat-' + v, label: this.labelFor(this.categoryOptions, v), type: 'category', value: v }));
            this.filters.interests.forEach(v => chips.push({ key: 'int-' + v, label: this.labelFor(this.interestOptions, v), type: 'interest', value: v }));
            this.filters.travelerTypes.forEach(v => chips.push({ key: 'trav-' + v, label: this.labelFor(this.travelerOptions, v), type: 'traveler', value: v }));
            if (this.filters.maxPrice < this.priceCap) chips.push({ key: 'price', label: 'Max ' + this.formatMoney(this.filters.maxPrice), type: 'price' });
            this.filters.languages.forEach(v => chips.push({ key: 'lang-' + v, label: this.labelFor(this.languageOptions, v), type: 'language', value: v }));
            this.filters.durations.forEach(v => chips.push({ key: 'dur-' + v, label: this.labelFor(this.durationOptions, v), type: 'duration', value: v }));
            this.filters.features.forEach(v => chips.push({ key: 'feat-' + v, label: this.labelFor(this.featureOptions, v), type: 'feature', value: v }));
            if (this.filters.minRating > 0) chips.push({ key: 'rating', label: this.filters.minRating + '+ stele', type: 'rating' });
            return chips;
        },
        removeChip(chip) {
            if (chip.type === 'search') this.filters.search = '';
            if (chip.type === 'category') this.filters.categories = this.filters.categories.filter(v => v !== chip.value);
            if (chip.type === 'interest') this.filters.interests = this.filters.interests.filter(v => v !== chip.value);
            if (chip.type === 'traveler') this.filters.travelerTypes = this.filters.travelerTypes.filter(v => v !== chip.value);
            if (chip.type === 'price') this.filters.maxPrice = this.priceCap;
            if (chip.type === 'language') this.filters.languages = this.filters.languages.filter(v => v !== chip.value);
            if (chip.type === 'duration') this.filters.durations = this.filters.durations.filter(v => v !== chip.value);
            if (chip.type === 'feature') this.filters.features = this.filters.features.filter(v => v !== chip.value);
            if (chip.type === 'rating') this.filters.minRating = 0;
        },
        filteredActivities() {
            const q = this.filters.search.toLowerCase().trim();
            return this.activities.filter(a => {
                if (q && !JSON.stringify(a).toLowerCase().includes(q)) return false;
                if (this.filters.categories.length && !this.filters.categories.includes(a.categorySlug)) return false;
                if (this.filters.interests.length && !this.filters.interests.some(i => (a.interests || []).includes(i))) return false;
                if (this.filters.travelerTypes.length && !this.filters.travelerTypes.some(t => (a.travelerTypes || []).includes(t))) return false;
                if (a.price > 0 && a.price > this.filters.maxPrice) return false;
                if (this.filters.languages.length && !this.filters.languages.some(l => a.languages.includes(l))) return false;
                if (this.filters.durations.length && !this.filters.durations.includes(a.duration)) return false;
                if (this.filters.features.length && !this.filters.features.every(f => a.features.includes(f))) return false;
                if (this.filters.minRating > 0 && a.rating < this.filters.minRating) return false;
                return true;
            });
        },
        sortedActivities() {
            const list = [...this.filteredActivities()];
            if (this.sortBy === 'rating') return list.sort((a, b) => b.rating - a.rating);
            if (this.sortBy === 'priceAsc') return list.sort((a, b) => (a.price || 1e9) - (b.price || 1e9));
            if (this.sortBy === 'priceDesc') return list.sort((a, b) => b.price - a.price);
            if (this.sortBy === 'duration') { const o = { short: 1, medium: 2, long: 3, '': 4 }; return list.sort((a, b) => (o[a.duration] || 4) - (o[b.duration] || 4)); }
            return list.sort((a, b) => (b.rating - a.rating) || (b.reviews - a.reviews));
        },
        selectedActivity() { return this.activities.find(a => a.id === this.selectedPin); },
        labelFor(list, val) { const o = list.find(x => x.value === val); return o ? o.label : val; },
        featureLabel(val) { return this.labelFor(this.featureOptions, val); },
        formatMoney(v) { try { return new Intl.NumberFormat('ro-RO', { style: 'currency', currency: 'RON', maximumFractionDigits: 0 }).format(v || 0); } catch (e) { return (v || 0) + ' lei'; } },
    };
}
</script>

<!-- ============================== FILTRE + REZULTATE (legacy server-side — disabled, kept for rollback) ============================== -->
<?php if (false): ?>
<section x-data="categoryFilters()" class="px-4 pt-10 mx-auto max-w-7xl sm:px-6">

    <!-- sticky filter toolbar -->
    <div class="sticky z-40 px-4 py-4 -mx-4 border-b top-24 sm:-mx-6 sm:px-6 bg-paper backdrop-blur-md border-ink/10">

        <!-- DESKTOP filter pills -->
        <form method="get" action="/<?= htmlspecialchars($slug, ENT_QUOTES) ?>" class="items-center hidden gap-3 lg:flex" @click.outside="open=null">
            <?php // Preserve current filter state across form submission ?>
            <?php if ($cityFilter): ?><input type="hidden" name="city" value="<?= htmlspecialchars($cityFilter, ENT_QUOTES) ?>"><?php endif; ?>
            <?php if ($maxPrice !== null): ?><input type="hidden" name="max_price" value="<?= $maxPrice ?>"><?php endif; ?>
            <?php if ($sort !== 'recommended'): ?><input type="hidden" name="sort" value="<?= htmlspecialchars($sort, ENT_QUOTES) ?>"><?php endif; ?>

            <!-- search -->
            <div class="relative flex-1 max-w-xs">
                <svg viewBox="0 0 24 24" class="w-4 h-4 absolute left-3.5 top-1/2 -translate-y-1/2 text-ink-soft" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><circle cx="11" cy="11" r="7"/><path d="m20 20-3.5-3.5"/></svg>
                <input name="q" type="search" value="<?= htmlspecialchars($searchQuery, ENT_QUOTES) ?>" placeholder="Caută <?= htmlspecialchars(mb_strtolower($catName), ENT_QUOTES) ?>…" class="w-full bg-paper border-2 border-ink/15 focus:border-ink rounded-full pl-10 pr-4 py-2.5 text-[15px] focus:outline-none transition-colors" />
            </div>

            <!-- Oraș dropdown -->
            <?php
            $cityLabel = 'Oraș';
            if ($cityFilter) {
                foreach ($featuredCities as $c) { if ($c['slug'] === $cityFilter) { $cityLabel = $c['label']; break; } }
                if ($cityLabel === 'Oraș') $cityLabel = ucwords(str_replace('-', ' ', $cityFilter));
            }
            ?>
            <div class="relative">
                <button type="button" @click="open = open==='city' ? null : 'city'" class="<?= $cityFilter ? 'border-ink bg-ink text-paper' : 'border-ink/15 bg-paper hover:border-ink' ?> flex items-center gap-2 px-4 py-2.5 rounded-full border-2 text-[15px] font-500 transition">
                    <span><?= htmlspecialchars($cityLabel) ?></span>
                    <svg :class="open==='city' && 'rotate-180'" class="w-3.5 h-3.5 transition-transform" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" aria-hidden="true"><path d="m6 9 6 6 6-6"/></svg>
                </button>
                <div x-show="open==='city'" x-cloak x-transition class="absolute left-0 top-[calc(100%+10px)] z-50 w-60 bg-paper border-2 border-ink rounded-2xl shadow-2xl p-2 max-h-72 overflow-y-auto">
                    <a href="<?= htmlspecialchars(navBuildUrl($slug, $_GET, [], ['city']), ENT_QUOTES) ?>" class="block w-full text-left px-3 py-2 rounded-lg text-[15px] hover:bg-paper-2 transition <?= !$cityFilter ? 'bg-ink text-paper' : '' ?>">Toate orașele</a>
                    <?php foreach ($featuredCities as $c): ?>
                        <a href="<?= htmlspecialchars(navBuildUrl($slug, $_GET, ['city' => $c['slug']]), ENT_QUOTES) ?>" class="block w-full text-left px-3 py-2 rounded-lg text-[15px] hover:bg-paper-2 transition <?= $cityFilter === $c['slug'] ? 'bg-ink text-paper' : '' ?>">
                            <?= htmlspecialchars($c['label']) ?>
                        </a>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- Preț (max) dropdown -->
            <div class="relative">
                <button type="button" @click="open = open==='price' ? null : 'price'" class="<?= $maxPrice !== null ? 'border-ink bg-ink text-paper' : 'border-ink/15 bg-paper hover:border-ink' ?> flex items-center gap-2 px-4 py-2.5 rounded-full border-2 text-[15px] font-500 transition">
                    <span><?= $maxPrice !== null ? 'Sub ' . $maxPrice . ' lei' : 'Preț' ?></span>
                    <svg :class="open==='price' && 'rotate-180'" class="w-3.5 h-3.5 transition-transform" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" aria-hidden="true"><path d="m6 9 6 6 6-6"/></svg>
                </button>
                <div x-show="open==='price'" x-cloak x-transition class="absolute left-0 top-[calc(100%+10px)] z-50 w-56 bg-paper border-2 border-ink rounded-2xl shadow-2xl p-2">
                    <a href="<?= htmlspecialchars(navBuildUrl($slug, $_GET, [], ['max_price']), ENT_QUOTES) ?>" class="block w-full text-left px-3 py-2 rounded-lg text-[15px] hover:bg-paper-2 transition <?= $maxPrice === null ? 'bg-ink text-paper' : '' ?>">Orice preț</a>
                    <?php foreach ($priceMaxAllowed as $cap): ?>
                        <a href="<?= htmlspecialchars(navBuildUrl($slug, $_GET, ['max_price' => $cap]), ENT_QUOTES) ?>" class="block w-full text-left px-3 py-2 rounded-lg text-[15px] hover:bg-paper-2 transition <?= $maxPrice === $cap ? 'bg-ink text-paper' : '' ?>">Sub <?= $cap ?> lei</a>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- Sortare -->
            <div class="relative">
                <button type="button" @click="open = open==='sort' ? null : 'sort'" class="<?= $sort !== 'recommended' ? 'border-ink bg-ink text-paper' : 'border-ink/15 bg-paper hover:border-ink' ?> flex items-center gap-2 px-4 py-2.5 rounded-full border-2 text-[15px] font-500 transition">
                    <span>
                        <?php
                        $sortLabels = ['recommended' => 'Recomandate', 'price_asc' => 'Preț ↑', 'price_desc' => 'Preț ↓', 'name_asc' => 'Alfabetic', 'date_asc' => 'Dată'];
                        echo htmlspecialchars($sortLabels[$sort] ?? 'Sortare');
                        ?>
                    </span>
                    <svg :class="open==='sort' && 'rotate-180'" class="w-3.5 h-3.5 transition-transform" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" aria-hidden="true"><path d="m6 9 6 6 6-6"/></svg>
                </button>
                <div x-show="open==='sort'" x-cloak x-transition class="absolute right-0 top-[calc(100%+10px)] z-50 w-52 bg-paper border-2 border-ink rounded-2xl shadow-2xl p-2">
                    <?php foreach ($sortLabels as $value => $label): ?>
                        <a href="<?= htmlspecialchars(navBuildUrl($slug, $_GET, $value === 'recommended' ? [] : ['sort' => $value], $value === 'recommended' ? ['sort'] : []), ENT_QUOTES) ?>" class="block w-full text-left px-3 py-2 rounded-lg text-[15px] hover:bg-paper-2 transition <?= $sort === $value ? 'bg-ink text-paper' : '' ?>">
                            <?= htmlspecialchars($label) ?>
                        </a>
                    <?php endforeach; ?>
                </div>
            </div>

            <button type="submit" class="px-5 py-2.5 rounded-full <?= $ac['bg'] ?> text-paper font-600 hover:<?= $ac['bg-dark'] ?> transition-colors">Caută</button>

            <!-- Right: counter -->
            <p class="ml-auto font-mono text-xs text-ink-soft">
                <?= (int) ($pagination['total'] ?? 0) ?> rezultate
            </p>
        </form>

        <!-- MOBILE: search + filter button -->
        <form method="get" action="/<?= htmlspecialchars($slug, ENT_QUOTES) ?>" class="flex items-center gap-2 lg:hidden">
            <?php if ($cityFilter): ?><input type="hidden" name="city" value="<?= htmlspecialchars($cityFilter, ENT_QUOTES) ?>"><?php endif; ?>
            <?php if ($maxPrice !== null): ?><input type="hidden" name="max_price" value="<?= $maxPrice ?>"><?php endif; ?>
            <?php if ($sort !== 'recommended'): ?><input type="hidden" name="sort" value="<?= htmlspecialchars($sort, ENT_QUOTES) ?>"><?php endif; ?>
            <div class="relative flex-1">
                <svg viewBox="0 0 24 24" class="w-4 h-4 absolute left-3.5 top-1/2 -translate-y-1/2 text-ink-soft" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><circle cx="11" cy="11" r="7"/><path d="m20 20-3.5-3.5"/></svg>
                <input name="q" type="search" value="<?= htmlspecialchars($searchQuery, ENT_QUOTES) ?>" placeholder="Caută…" class="w-full bg-paper border-2 border-ink/15 focus:border-ink rounded-full pl-10 pr-4 py-2.5 text-[15px] focus:outline-none" />
            </div>
            <button type="button" @click="sheet=true" class="relative shrink-0 flex items-center gap-2 px-4 py-2.5 rounded-full border-2 border-ink font-600 text-sm">
                <svg viewBox="0 0 24 24" class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M3 5h18M6 12h12M10 19h4"/></svg>
                Filtre
                <?php if (!empty($activeChips)): ?>
                    <span class="grid place-items-center w-5 h-5 rounded-full <?= $ac['bg'] ?> text-paper text-[11px] font-700"><?= count($activeChips) ?></span>
                <?php endif; ?>
            </button>
        </form>

        <!-- active filter chips -->
        <?php if (!empty($activeChips)): ?>
            <div class="flex flex-wrap items-center gap-2 mt-3">
                <?php foreach ($activeChips as $ch): ?>
                    <a href="/<?= htmlspecialchars($slug, ENT_QUOTES) ?><?= htmlspecialchars($ch['remove_qs'], ENT_QUOTES) ?>" class="group flex items-center gap-1.5 pl-3 pr-2 py-1 rounded-full bg-paper-2 border border-ink/15 text-sm hover:border-ink transition">
                        <span><?= htmlspecialchars($ch['label']) ?></span>
                        <svg class="w-3.5 h-3.5 text-ink-soft group-hover:text-vermilion" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" aria-hidden="true"><path d="M6 6l12 12M18 6 6 18"/></svg>
                    </a>
                <?php endforeach; ?>
                <a href="/<?= htmlspecialchars($slug, ENT_QUOTES) ?>" class="text-sm font-600 <?= $ac['text'] ?> underline-wobble ml-1">Șterge tot</a>
            </div>
        <?php endif; ?>
    </div>

    <!-- ===== results ===== -->
    <div class="py-8 lg:py-10">
        <div class="flex items-baseline justify-between mb-6">
            <p class="text-2xl font-display font-700">
                <?= (int) ($pagination['total'] ?? 0) ?>
                <span class="font-sans text-lg text-ink-soft font-500">
                    <?= ($pagination['total'] ?? 0) === 1 ? mb_strtolower($catName) : (mb_strtolower($catName) . ' disponibile') ?>
                </span>
            </p>
            <p class="hidden font-mono text-xs sm:block text-ink-soft">DISPONIBILE TOT ANUL · INTRARE CU QR</p>
        </div>

        <?php if (empty($cards)): ?>
            <div class="p-10 text-center border-2 ticket bg-paper border-ink rounded-3xl sm:p-16" style="--perf:100%">
                <div class="inline-grid w-16 h-16 mb-4 rounded-full place-items-center bg-paper-2">
                    <svg viewBox="0 0 24 24" class="w-7 h-7 text-ink-soft" fill="none" stroke="currentColor" stroke-width="1.6" aria-hidden="true"><circle cx="11" cy="11" r="7"/><path d="m20 20-3.5-3.5"/></svg>
                </div>
                <p class="text-2xl font-display font-700">
                    <?= ($searchQuery !== '' || $cityFilter) ? 'Nimic pe filtrele astea.' : 'Încă nu sunt activități listate aici.' ?>
                </p>
                <p class="mt-2 text-ink-soft">
                    <?= ($searchQuery !== '' || $cityFilter) ? 'Lărgește criteriile sau încearcă o altă subcategorie.' : 'Categoria e activă — operatorii își vor adăuga curând experiențele.' ?>
                </p>
                <?php if ($searchQuery !== '' || $cityFilter): ?>
                    <a href="/<?= htmlspecialchars($slug, ENT_QUOTES) ?>" class="inline-block mt-5 px-5 py-2.5 rounded-full bg-ink text-paper font-600">Resetează filtrele</a>
                <?php else: ?>
                    <a href="/categorii" class="inline-block mt-5 px-5 py-2.5 rounded-full bg-ink text-paper font-600">Vezi alte categorii</a>
                <?php endif; ?>
            </div>
        <?php else: ?>
            <div class="grid gap-5 sm:grid-cols-2 lg:grid-cols-3">
                <?php foreach ($cards as $ev):
                    $evTitle = $ev['title'];
                    $evCity = $ev['city'];
                    $evCover = $ev['cover'];
                    $evPriceCents = $ev['price_cents'] ?? null;
                    $evPrice = navFormatPriceCents($evPriceCents);
                ?>
                    <a href="<?= htmlspecialchars($ev['url'], ENT_QUOTES) ?>" class="flex flex-col overflow-hidden border-2 ticket ticket-lift group bg-paper border-ink rounded-2xl" style="--perf:100%">
                        <?php if ($evCover): ?>
                            <img src="<?= htmlspecialchars($evCover, ENT_QUOTES) ?>" alt="<?= htmlspecialchars($evTitle, ENT_QUOTES) ?>" class="object-cover w-full h-44" loading="lazy" width="600" height="320">
                        <?php else: ?>
                            <div class="duotone h-36 p-4 bg-gradient-to-br <?= $ac['gradient'] ?> <?= $ac['text'] ?>">
                                <div class="grid-tex"></div>
                                <span class="relative font-mono text-[10px] text-paper/90 bg-ink/25 px-2 py-1 rounded"><?= htmlspecialchars(strtoupper($catName)) ?></span>
                            </div>
                        <?php endif; ?>

                        <div class="flex flex-col flex-1 p-4">
                            <?php if ($evCity): ?>
                                <p class="font-mono text-[10px] text-ink-soft tracking-wider"><?= htmlspecialchars(strtoupper($evCity)) ?></p>
                            <?php endif; ?>
                            <h3 class="font-display text-xl font-700 leading-tight mt-1.5 group-hover:<?= $ac['text'] ?> transition-colors"><?= htmlspecialchars($evTitle) ?></h3>

                            <div class="flex items-center justify-between pt-4 mt-auto">
                                <p class="text-ink-soft">
                                    <?php if ($evPriceCents !== null && $evPriceCents > 0): ?>
                                        <span class="text-xs">de la </span>
                                    <?php endif; ?>
                                    <span class="text-lg font-display font-700 text-ink"><?= htmlspecialchars($evPrice) ?></span>
                                </p>
                                <span class="px-3 py-1.5 rounded-full bg-ink text-paper text-xs font-600 group-hover:<?= $ac['bg'] ?> transition-colors"><?= htmlspecialchars($ev['cta']) ?></span>
                            </div>
                        </div>
                    </a>
                <?php endforeach; ?>
            </div>

            <!-- pagination -->
            <?php if (($pagination['last_page'] ?? 1) > 1): ?>
                <nav class="flex flex-wrap justify-center gap-2 mt-10" aria-label="Pagini">
                    <?php
                    $baseQs = $_GET;
                    unset($baseQs['slug'], $baseQs['page']);
                    $baseLink = '/' . $slug . ($baseQs ? '?' . http_build_query($baseQs) . '&' : '?');
                    $current = (int) ($pagination['current_page'] ?? 1);
                    $last = (int) ($pagination['last_page'] ?? 1);
                    $start = max(1, $current - 3);
                    $end = min($last, $start + 6);
                    $start = max(1, $end - 6);
                    ?>
                    <?php if ($current > 1): ?>
                        <a href="<?= htmlspecialchars($baseLink) ?>page=<?= $current - 1 ?>" class="px-4 py-2 text-sm border-2 rounded-full border-ink/20 hover:border-ink font-600">‹ Anterior</a>
                    <?php endif; ?>
                    <?php for ($p = $start; $p <= $end; $p++): ?>
                        <a href="<?= htmlspecialchars($baseLink) ?>page=<?= $p ?>" class="px-4 py-2 rounded-full border-2 font-600 text-sm <?= $p === $current ? 'bg-ink text-paper border-ink' : 'border-ink/20 hover:border-ink' ?>"><?= $p ?></a>
                    <?php endfor; ?>
                    <?php if ($current < $last): ?>
                        <a href="<?= htmlspecialchars($baseLink) ?>page=<?= $current + 1 ?>" class="px-4 py-2 text-sm border-2 rounded-full border-ink/20 hover:border-ink font-600">Următor ›</a>
                    <?php endif; ?>
                </nav>
            <?php endif; ?>
        <?php endif; ?>
    </div>

    <!-- MOBILE FILTER SHEET -->
    <div x-show="sheet" x-cloak class="lg:hidden fixed inset-0 z-[70]" @keydown.escape.window="sheet=false">
        <div x-show="sheet" x-transition.opacity class="absolute inset-0 bg-ink/50" @click="sheet=false"></div>
        <div x-show="sheet"
             x-transition:enter="transition ease-out duration-300" x-transition:enter-start="translate-y-full" x-transition:enter-end="translate-y-0"
             x-transition:leave="transition ease-in duration-200" x-transition:leave-start="translate-y-0" x-transition:leave-end="translate-y-full"
             class="absolute bottom-0 inset-x-0 bg-paper rounded-t-3xl border-t-2 border-ink max-h-[88vh] overflow-y-auto">
            <div class="sticky top-0 flex items-center justify-between px-5 py-4 border-b bg-paper border-ink/10">
                <h2 class="text-xl font-display font-700">Filtre</h2>
                <button @click="sheet=false" class="grid border-2 rounded-full place-items-center w-9 h-9 border-ink/15" aria-label="Închide">
                    <svg viewBox="0 0 24 24" class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2.4" aria-hidden="true"><path d="M6 6l12 12M18 6 6 18"/></svg>
                </button>
            </div>
            <div class="p-5 space-y-6">
                <div>
                    <p class="font-mono text-[10px] tracking-wider text-ink-soft mb-3">ORAȘ</p>
                    <div class="flex flex-wrap gap-2">
                        <a href="<?= htmlspecialchars(navBuildUrl($slug, $_GET, [], ['city']), ENT_QUOTES) ?>" class="px-3.5 py-2 rounded-full border-2 text-sm font-500 transition <?= !$cityFilter ? 'bg-ink text-paper border-ink' : 'border-ink/15' ?>">Toate</a>
                        <?php foreach ($featuredCities as $c): ?>
                            <a href="<?= htmlspecialchars(navBuildUrl($slug, $_GET, ['city' => $c['slug']]), ENT_QUOTES) ?>" class="px-3.5 py-2 rounded-full border-2 text-sm font-500 transition <?= $cityFilter === $c['slug'] ? 'bg-ink text-paper border-ink' : 'border-ink/15' ?>">
                                <?= htmlspecialchars($c['label']) ?>
                            </a>
                        <?php endforeach; ?>
                    </div>
                </div>

                <div>
                    <p class="font-mono text-[10px] tracking-wider text-ink-soft mb-3">PREȚ MAXIM</p>
                    <div class="flex flex-wrap gap-2">
                        <a href="<?= htmlspecialchars(navBuildUrl($slug, $_GET, [], ['max_price']), ENT_QUOTES) ?>" class="px-3.5 py-2 rounded-full border-2 text-sm font-500 transition <?= $maxPrice === null ? 'bg-ink text-paper border-ink' : 'border-ink/15' ?>">Orice</a>
                        <?php foreach ($priceMaxAllowed as $cap): ?>
                            <a href="<?= htmlspecialchars(navBuildUrl($slug, $_GET, ['max_price' => $cap]), ENT_QUOTES) ?>" class="px-3.5 py-2 rounded-full border-2 text-sm font-500 transition <?= $maxPrice === $cap ? 'bg-ink text-paper border-ink' : 'border-ink/15' ?>">Sub <?= $cap ?> lei</a>
                        <?php endforeach; ?>
                    </div>
                </div>

                <div>
                    <p class="font-mono text-[10px] tracking-wider text-ink-soft mb-3">SORTARE</p>
                    <div class="flex flex-wrap gap-2">
                        <?php
                        $mobileSortLabels = ['recommended' => 'Recomandate', 'price_asc' => 'Preț ↑', 'price_desc' => 'Preț ↓', 'name_asc' => 'Alfabetic', 'date_asc' => 'Dată'];
                        foreach ($mobileSortLabels as $value => $label):
                            $url = $value === 'recommended'
                                ? navBuildUrl($slug, $_GET, [], ['sort'])
                                : navBuildUrl($slug, $_GET, ['sort' => $value]);
                        ?>
                            <a href="<?= htmlspecialchars($url, ENT_QUOTES) ?>" class="px-3.5 py-2 rounded-full border-2 text-sm font-500 transition <?= $sort === $value ? 'bg-ink text-paper border-ink' : 'border-ink/15' ?>">
                                <?= htmlspecialchars($label) ?>
                            </a>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>
<?php endif; /* legacy server-side filter UI */ ?>

<!-- ============================== EDITORIAL + FAQ ============================== -->
<section class="bg-paper-2 border-y border-ink/10">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 py-16 lg:py-20 grid lg:grid-cols-[1fr_.8fr] gap-14">

        <div>
            <h2 class="font-display text-[clamp(1.8rem,4vw,2.8rem)] font-700 leading-[1] mb-6">
                <?php if ($seoBodyTitle): ?>
                    <?= htmlspecialchars($seoBodyTitle) ?>
                <?php else: ?>
                    Tot ce trebuie să știi despre <span class="ital <?= $ac['text'] ?>"><?= htmlspecialchars(mb_strtolower($catName)) ?></span>
                <?php endif; ?>
            </h2>

            <?php if ($seoBodyHtml): ?>
                <?php // Admin RichEditor HTML — emit as-is, only allow the editor's whitelisted tags. ?>
                <div class="prose-custom text-ink-soft leading-relaxed text-[17px] max-w-2xl">
                    <?= strip_tags($seoBodyHtml, '<p><h2><h3><h4><strong><em><b><i><u><a><ul><ol><li><blockquote><br><span>') ?>
                </div>
            <?php elseif ($catDescription): ?>
                <div class="space-y-4 text-ink-soft leading-relaxed text-[17px] max-w-2xl">
                    <?php foreach (preg_split('/\n\s*\n/', trim($catDescription)) as $paragraph): ?>
                        <p><?= nl2br(htmlspecialchars($paragraph)) ?></p>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <p class="text-ink-soft leading-relaxed text-[17px] max-w-2xl">
                    Pe bilete.online găsești o selecție curată de <strong class="text-ink font-600"><?= htmlspecialchars(mb_strtolower($catName)) ?></strong> din toată România. Rezervi online data și ora dorită, plătești securizat și intri cu biletul QR direct la locație.
                </p>
            <?php endif; ?>

            <!-- City crosslinks for local SEO -->
            <?php if (!empty($featuredCities)): ?>
            <div class="mt-8">
                <p class="font-mono text-[10px] tracking-[.2em] text-ink-soft mb-3"><?= htmlspecialchars(strtoupper($catName)) ?> PE ORAȘE</p>
                <div class="flex flex-wrap gap-2">
                    <?php foreach (array_slice($featuredCities, 0, 8) as $c): ?>
                        <a href="/<?= htmlspecialchars($slug, ENT_QUOTES) ?>?city=<?= htmlspecialchars($c['slug'], ENT_QUOTES) ?>" class="px-3.5 py-1.5 rounded-full bg-paper border border-ink/10 text-sm hover:bg-ink hover:text-paper transition">
                            <?= htmlspecialchars($catName) ?> <?= htmlspecialchars($c['label']) ?>
                        </a>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>

            <!-- Related sibling categories -->
            <div class="mt-10">
                <p class="font-mono text-[10px] tracking-[.2em] text-ink-soft mb-3">CATEGORII ÎNRUDITE</p>
                <div class="flex flex-wrap gap-2">
                    <?php
                    $siblings = array_filter($navCategories ?? navGetCategories(12), fn ($c) => $c['slug'] !== $slug);
                    foreach (array_slice($siblings, 0, 8) as $sib):
                    ?>
                        <a href="<?= htmlspecialchars($sib['href'], ENT_QUOTES) ?>" class="px-4 py-2 text-sm transition border rounded-full bg-paper border-ink/15 hover:border-ink hover:bg-ink hover:text-paper">
                            <?php if (!empty($sib['icon_emoji'])): ?><span class="mr-1"><?= htmlspecialchars($sib['icon_emoji']) ?></span><?php endif; ?>
                            <?= htmlspecialchars($sib['label']) ?>
                        </a>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>

        <!-- FAQ accordion -->
        <div x-data="{ active: 0 }">
            <h2 class="mb-6 text-2xl font-display font-700">Întrebări frecvente</h2>
            <div class="space-y-3">
                <?php foreach ($faqItems as $i => $f): ?>
                    <div class="overflow-hidden border-2 border-ink rounded-xl bg-paper">
                        <button type="button" @click="active = active === <?= $i ?> ? null : <?= $i ?>" :aria-expanded="active === <?= $i ?>" class="flex items-center justify-between w-full gap-4 px-5 py-4 text-left">
                            <span class="font-600 text-[16px]"><?= htmlspecialchars($f['q']) ?></span>
                            <span class="grid transition-transform duration-300 border-2 rounded-full shrink-0 place-items-center w-7 h-7 border-ink" :class="active === <?= $i ?> && 'rotate-45 <?= $ac['bg'] ?> <?= $ac['border'] ?> text-paper'">
                                <svg viewBox="0 0 24 24" class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2.4" aria-hidden="true"><path d="M12 5v14M5 12h14"/></svg>
                            </span>
                        </button>
                        <div x-show="active === <?= $i ?>" x-collapse x-cloak>
                            <p class="px-5 pb-5 leading-relaxed text-ink-soft"><?= htmlspecialchars($f['a']) ?></p>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
</section>

<script>
document.addEventListener('alpine:init', () => {
    Alpine.data('categoryFilters', () => ({
        open: null,
        sheet: false,
    }));
});
</script>

<?php include __DIR__ . '/includes/footer.php'; ?>
