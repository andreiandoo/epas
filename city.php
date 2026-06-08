<?php
/**
 * Single city landing — /{city-slug}.
 *
 * Pure render: expects $_GET['slug'] (or `$slug` already set by the
 * slug.php dispatcher with `$cityData` pre-fetched).
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

// Use pre-fetched data if dispatched via slug.php; else fetch now.
$cityData = $cityData ?? navGetCityBySlug($slug);

if (!$cityData) {
    http_response_code(404);
    require __DIR__ . '/404.php';
    exit;
}

// ============================================================
// City data extraction
// ============================================================
// Pull the full API response (city + affiliates) so we can render the
// GetYourGuide widget without a second network call. Both helpers share
// the same upstream cache key, so this stays cheap.
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
$lat = $cityData['latitude'] ?? null;
$lng = $cityData['longitude'] ?? null;

// Pagination + filter parsing
$pageNum = max(1, (int) ($_GET['page'] ?? 1));
$categoryFilter = isset($_GET['category']) && preg_match('/^[a-z][a-z0-9-]+$/', $_GET['category']) ? $_GET['category'] : null;
$searchQuery = isset($_GET['q']) ? mb_substr(trim($_GET['q']), 0, 80) : '';

// Price filter — whitelisted (same set as category page)
$priceMaxAllowed = [50, 100, 200, 500];
$maxPrice = (isset($_GET['max_price']) && in_array((int) $_GET['max_price'], $priceMaxAllowed, true))
    ? (int) $_GET['max_price']
    : null;

// Sort — whitelisted to API-supported values
$sortAllowed = ['recommended', 'price_asc', 'price_desc', 'name_asc', 'date_asc'];
$sort = (isset($_GET['sort']) && in_array($_GET['sort'], $sortAllowed, true)) ? $_GET['sort'] : 'recommended';

// Fetch parent categories for quick-link grid + filter dropdown (max 12)
$topCategories = navGetCategories(12);

// Fetch events for this city
$apiCacheKey = "city_events_{$slug}_" . ($categoryFilter ?? 'all') . '_' . md5($searchQuery) . "_mp{$maxPrice}_s{$sort}_p{$pageNum}";
$eventsResp = api_cached($apiCacheKey, function () use ($slug, $categoryFilter, $searchQuery, $maxPrice, $sort, $pageNum) {
    $params = ['city' => $slug, 'page' => $pageNum, 'per_page' => 18, 'time_scope' => 'upcoming'];
    if ($categoryFilter) $params['category'] = $categoryFilter;
    if ($searchQuery !== '') $params['search'] = $searchQuery;
    if ($maxPrice !== null) $params['max_price'] = $maxPrice;
    if ($sort !== 'recommended') $params['sort'] = $sort;
    return api_get('/events', $params);
}, 300);

$events = $eventsResp['data'] ?? [];
$evPagination = $eventsResp['meta'] ?? ['current_page' => 1, 'last_page' => 1, 'total' => count($events)];
if (!is_array($events)) $events = [];

// Activities in this city (+ optional category filter). bilete.online is
// activity-centric, so the city listing must include ACTIVITIES, not just
// ticketed events. /activities filters by exact city slug + category slug.
$actCacheKey = "city_activities_{$slug}_" . ($categoryFilter ?? 'all') . '_' . md5($searchQuery) . "_mp{$maxPrice}_s{$sort}_p{$pageNum}";
$actResp = api_cached($actCacheKey, function () use ($slug, $categoryFilter, $searchQuery, $maxPrice, $sort, $pageNum) {
    $params = ['city' => $slug, 'page' => $pageNum, 'per_page' => 24];
    if ($categoryFilter) $params['category'] = $categoryFilter;
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
        'cat'         => $a['category']['name'] ?? '',
        'cover'       => $a['cover_image_url'] ?? '',
        'price_cents' => $a['cheapest_price_cents'] ?? null,
        'url'         => ($a['city']['slug'] ?? $citySlug ?? '') ? '/' . ($a['city']['slug'] ?? $citySlug) . '/' . ($a['slug'] ?? '') : '/activitate/' . ($a['slug'] ?? ''),
        'cta'         => 'Vezi activitatea',
    ];
}
foreach ($events as $ev) {
    $cards[] = [
        'title'       => navEventTitle($ev),
        'cat'         => navEventCategoryLabel($ev),
        'cover'       => $ev['cover_image_url'] ?? $ev['image_url'] ?? '',
        'price_cents' => $ev['cheapest_price_cents'] ?? null,
        'url'         => '/bilete/' . ($ev['slug'] ?? ''),
        'cta'         => 'Vezi bilete',
    ];
}

$pagination = [
    'current_page' => $pageNum,
    'last_page'    => max((int) ($evPagination['last_page'] ?? 1), (int) ($actPagination['last_page'] ?? 1)),
    'total'        => (int) ($evPagination['total'] ?? count($events)) + (int) ($actPagination['total'] ?? count($activities)),
];

// Active filter chips
$activeChips = [];
if ($searchQuery !== '') {
    $activeChips[] = ['label' => '„' . $searchQuery . '"', 'remove_qs' => navResetQueryString($_GET, ['q'])];
}
if ($categoryFilter) {
    $catLabel = '';
    foreach ($topCategories as $c) { if ($c['slug'] === $categoryFilter) { $catLabel = $c['label']; break; } }
    if (!$catLabel) $catLabel = ucwords(str_replace('-', ' ', $categoryFilter));
    $activeChips[] = ['label' => $catLabel, 'remove_qs' => navResetQueryString($_GET, ['category'])];
}
if ($maxPrice !== null) {
    $activeChips[] = ['label' => 'Sub ' . $maxPrice . ' lei', 'remove_qs' => navResetQueryString($_GET, ['max_price'])];
}
if ($sort !== 'recommended') {
    $sortLabels = ['price_asc' => 'Preț crescător', 'price_desc' => 'Preț descrescător', 'name_asc' => 'Alfabetic', 'date_asc' => 'Data evenimentului'];
    $activeChips[] = ['label' => 'Sortat: ' . ($sortLabels[$sort] ?? $sort), 'remove_qs' => navResetQueryString($_GET, ['sort'])];
}

// ============================================================
// SEO setup
// ============================================================
$pageTitleRaw = 'Activități în ' . $cityName . ' — bilete online | bilete.online';
$pageDescription = 'Descoperă activități în ' . $cityName . ': escape rooms, muzee, parcuri, experiențe pentru copii, tururi și aventură. Rezervi online cu bilet QR.';
$canonicalUrl = SITE_URL . '/' . $slug;
$ogImage = $cityCover
    ? (str_starts_with($cityCover, 'http') ? $cityCover : STORAGE_URL . '/' . ltrim($cityCover, '/'))
    : (SITE_URL . '/assets/images/og-default.jpg');
$currentPage = 'city';
$cssBundle = 'listing';

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
// GYG-style discovery data (real images + DB-derived lists)
// ============================================================
// Context for the v6 header (Explore button + search placeholder + hero).
$headerContext = ['type' => 'city', 'label' => $cityName, 'slug' => $slug];

$bo_img = function ($u) {
    $u = (string) $u;
    if ($u === '') return '';
    return str_starts_with($u, 'http') ? $u : rtrim(STORAGE_URL, '/') . '/' . ltrim($u, '/');
};

// Gallery — real city cover + activity covers (graceful when empty).
$gallery = [];
$coverResolved = $cityCover ? $bo_img($cityCover) : ($cityImage ? $bo_img($cityImage) : '');
if ($coverResolved) $gallery[] = ['src' => $coverResolved, 'alt' => $cityName];
foreach ($activities as $a) {
    $img = $bo_img($a['cover_image_url'] ?? '');
    if ($img === '') continue;
    $gallery[] = ['src' => $img, 'alt' => (is_array($a['title'] ?? null) ? navFlatName($a['title']) : ($a['title'] ?? $cityName))];
    if (count($gallery) >= 5) break;
}

// Nearby = other featured cities (geo "nearby" lands with F2; until then this
// is a relevant cross-link set rather than strict proximity).
$nearbyCities = [];
foreach (navGetCities(12) as $c) {
    if (($c['slug'] ?? '') === $slug) continue;
    $nearbyCities[] = $c;
    if (count($nearbyCities) >= 5) break;
}

// Real editorial guides (Inspiration). Falls back to empty → section hidden.
$cityGuides = [];
try {
    $gr = api_cached("city_guides_{$slug}", fn () => api_get('/blog-articles', ['per_page' => 3, 'status' => 'published']), 300);
    $rg = $gr['data']['articles'] ?? $gr['data']['items'] ?? $gr['data'] ?? [];
    foreach ((array) $rg as $g) {
        $gt = navFlatName($g['title'] ?? '');
        $gs = $g['slug'] ?? '';
        if ($gt === '' || $gs === '') continue;
        $cityGuides[] = [
            'title'   => $gt,
            'href'    => '/ghiduri/' . ltrim($gs, '/'),
            'kicker'  => mb_strtoupper(navFlatName($g['category']['name'] ?? '') ?: 'Ghid'),
            'excerpt' => navFlatName($g['excerpt'] ?? ''),
            'image'   => $bo_img($g['image_url'] ?? ''),
        ];
        if (count($cityGuides) >= 3) break;
    }
} catch (\Throwable $e) {}

// F4 — Attractions in this city (points of interest). Hidden when none.
$cityAttractions = [];
try {
    $atResp = api_cached("city_attractions_{$slug}", fn () => api_get('/attractions', ['city' => $slug, 'per_page' => 8]), 300);
    $atRaw = $atResp['data']['items'] ?? $atResp['data']['data'] ?? (is_array($atResp['data'] ?? null) ? $atResp['data'] : []);
    foreach ((is_array($atRaw) ? $atRaw : []) as $at) {
        $an = $at['name'] ?? '';
        $as = $at['slug'] ?? '';
        if ($an === '' || $as === '') continue;
        $cityAttractions[] = [
            'name'  => $an,
            'slug'  => $as,
            'image' => $bo_img($at['cover_image_url'] ?? ''),
            'type'  => $at['type']['name'] ?? '',
            'count' => $at['activities_count'] ?? null,
        ];
        if (count($cityAttractions) >= 8) break;
    }
} catch (\Throwable $e) {}

// Traveler types — city-scoped search links (city.php handles ?q), so these
// always resolve to real filtered results without needing dedicated routes.
$cityLow = mb_strtolower($cityName);
$travelerTypes = [
    ['icon' => '👨‍👩‍👧', 'title' => 'Pentru familii',  'desc' => 'Activități sigure, interactive și ușor de planificat cu copiii.',       'href' => '/' . $slug . '?q=' . rawurlencode('copii')],
    ['icon' => '💛',      'title' => 'Pentru cupluri',  'desc' => 'Tururi, experiențe cadou, date night și activități de seară.',           'href' => '/' . $slug . '?q=' . rawurlencode('cuplu')],
    ['icon' => '☔',      'title' => 'Când plouă',      'desc' => 'Muzee, ateliere, escape rooms și experiențe indoor.',                    'href' => '/' . $slug . '?q=' . rawurlencode('indoor')],
    ['icon' => '🎒',      'title' => 'Pentru turiști',  'desc' => 'Atracții principale, tururi ghidate și experiențe de primă vizită.',     'href' => '/' . $slug . '?q=' . rawurlencode('tur')],
];
?>
<?php
include __DIR__ . '/includes/head.php';
include __DIR__ . '/includes/header.php';
?>

<!-- ============================== HERO (GYG things-to-do) ============================== -->
<section class="relative overflow-hidden border-b border-ink/10 bg-paper" x-data="cityHero(<?= htmlspecialchars(json_encode($gallery, JSON_UNESCAPED_UNICODE), ENT_QUOTES) ?>)">
    <div class="absolute inset-0 opacity-[.08]" style="background-image:radial-gradient(#1B1714 1.2px,transparent 1.3px);background-size:18px 18px"></div>
    <div class="relative mx-auto max-w-[1500px] px-4 py-8 sm:px-6 lg:py-14">
        <nav aria-label="Breadcrumb" class="flex flex-wrap items-center gap-2 text-sm font-bold text-ink-soft">
            <?php foreach ($breadcrumbs as $i => $bc): ?>
                <?php if ($i > 0): ?><span aria-hidden="true">/</span><?php endif; ?>
                <?php if ($i < count($breadcrumbs) - 1): ?>
                    <a href="<?= htmlspecialchars($bc['url'], ENT_QUOTES) ?>" class="hover:text-vermilion"><?= htmlspecialchars($bc['name']) ?></a>
                <?php else: ?>
                    <span aria-current="page" class="text-ink"><?= htmlspecialchars($bc['name']) ?></span>
                <?php endif; ?>
            <?php endforeach; ?>
        </nav>

        <div class="mt-7 grid gap-8 lg:grid-cols-[minmax(0,1fr)_560px] lg:items-end">
            <div>
                <p class="font-mono text-xs tracking-[.18em] text-vermilion"><?= htmlspecialchars(strtoupper($cityName)) ?> · LUCRURI DE FĂCUT</p>
                <h1 class="mt-3 font-display text-6xl font-bold leading-[.84] sm:text-7xl lg:text-8xl">Lucruri de făcut în <?= htmlspecialchars($cityName) ?></h1>
                <p class="mt-6 max-w-4xl text-xl leading-relaxed text-ink-soft">
                    <?php if ($cityDescription): ?>
                        <?= htmlspecialchars($cityDescription) ?>
                    <?php else: ?>
                        <?= htmlspecialchars($cityName) ?> combină atracții, muzee, tururi, experiențe de familie, escape rooms și activități outdoor. Alege activități pentru weekend, bilete pentru atracții, tururi culturale sau experiențe cadou — rezervi online cu bilet QR.
                    <?php endif; ?>
                </p>
                <form action="/<?= htmlspecialchars($slug, ENT_QUOTES) ?>" method="get" class="mt-7 max-w-3xl rounded-full border-2 border-ink bg-paper p-2 shadow-deep" role="search" aria-label="Caută activități în <?= htmlspecialchars($cityName) ?>">
                    <div class="flex items-center gap-2">
                        <svg viewBox="0 0 24 24" class="ml-3 h-5 w-5 shrink-0 text-ink-soft" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="7"/><path d="m20 20-3.5-3.5"/></svg>
                        <label class="sr-only" for="city-search">Caută activități în <?= htmlspecialchars($cityName) ?></label>
                        <input id="city-search" name="q" class="w-full bg-transparent px-2 py-3 font-bold outline-none placeholder:text-ink-soft/70" placeholder="Caută activități în <?= htmlspecialchars($cityName, ENT_QUOTES) ?>: copii, muzee, escape rooms..." />
                        <button type="submit" class="shrink-0 rounded-full bg-vermilion px-6 py-3 font-bold text-paper transition hover:bg-vermilion-d">Caută</button>
                    </div>
                </form>
                <?php if (!empty($topCategories)): ?>
                <div class="mt-5 flex flex-wrap items-center gap-2 text-sm">
                    <span class="font-bold text-ink-soft">Populare:</span>
                    <?php foreach (array_slice($topCategories, 0, 4) as $cat): ?>
                        <a href="/<?= htmlspecialchars($slug, ENT_QUOTES) ?>?category=<?= htmlspecialchars($cat['slug'], ENT_QUOTES) ?>" class="rounded-full bg-paper-2 border border-ink/10 px-3 py-1.5 font-bold transition hover:bg-ink hover:text-paper">
                            <?php if (!empty($cat['icon_emoji'])): ?><?= htmlspecialchars($cat['icon_emoji']) ?> <?php endif; ?><?= htmlspecialchars($cat['label']) ?>
                        </a>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
            </div>

            <!-- Gallery (real images) -->
            <?php if (count($gallery) >= 1): ?>
                <div class="grid h-[460px] grid-cols-5 grid-rows-4 gap-3">
                    <button type="button" @click="openG(0)" class="group relative col-span-3 row-span-4 overflow-hidden rounded-[2rem] border-2 border-ink bg-ink">
                        <img src="<?= htmlspecialchars($gallery[0]['src'], ENT_QUOTES) ?>" alt="<?= htmlspecialchars($gallery[0]['alt'], ENT_QUOTES) ?>" class="h-full w-full object-cover transition duration-500 group-hover:scale-105" loading="lazy">
                        <div class="absolute inset-0 bg-gradient-to-t from-ink/70 via-transparent to-transparent"></div>
                        <span class="absolute bottom-4 left-4 rounded-full bg-paper px-4 py-2 text-sm font-bold text-ink">Vezi imagini</span>
                    </button>
                    <?php for ($gi = 1; $gi <= 4; $gi++): $g = $gallery[$gi] ?? null; ?>
                        <?php if ($g): ?>
                            <button type="button" @click="openG(<?= $gi ?>)" class="group relative col-span-2 row-span-1 overflow-hidden rounded-[1.5rem] border-2 border-ink bg-ink">
                                <img src="<?= htmlspecialchars($g['src'], ENT_QUOTES) ?>" alt="<?= htmlspecialchars($g['alt'], ENT_QUOTES) ?>" class="h-full w-full object-cover transition duration-500 group-hover:scale-105" loading="lazy">
                            </button>
                        <?php else: ?>
                            <div class="col-span-2 row-span-1 grid place-items-center rounded-[1.5rem] border-2 border-ink bg-gradient-to-br <?= ['from-vermilion to-vermilion-d','from-forest to-ink','from-sky to-ink','from-ochre to-vermilion-d'][$gi - 1] ?? 'from-vermilion to-vermilion-d' ?> text-paper">
                                <span class="font-display text-xl font-bold opacity-80"><?= htmlspecialchars($cityName) ?></span>
                            </div>
                        <?php endif; ?>
                    <?php endfor; ?>
                </div>
            <?php else: ?>
                <div class="grid h-[460px] grid-cols-2 grid-rows-2 gap-3">
                    <?php foreach ([['from-forest to-ink','CITY','Ghid'],['from-vermilion to-vermilion-d','INDOOR','Escape'],['from-sky to-ink','CULTURĂ','Muzee'],['from-ochre to-vermilion-d','OUTDOOR','Aventură']] as $tile): ?>
                        <div class="flex items-end rounded-[1.5rem] border-2 border-ink bg-gradient-to-br <?= $tile[0] ?> p-5 text-paper">
                            <div><p class="font-mono text-[10px] tracking-wider opacity-80"><?= $tile[1] ?></p><p class="font-display text-3xl font-bold leading-none"><?= $tile[2] ?></p></div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Gallery lightbox -->
    <div x-show="open" x-cloak class="fixed inset-0 z-[90] bg-ink/90 p-4 backdrop-blur-sm" @keydown.escape.window="open=false" @click.self="open=false">
        <div class="mx-auto flex h-full max-w-6xl flex-col">
            <div class="mb-4 flex items-center justify-between gap-4 text-paper">
                <p class="font-display text-3xl font-bold" x-text="g[i] ? g[i].alt : ''"></p>
                <button @click="open=false" class="grid h-11 w-11 place-items-center rounded-full bg-paper text-2xl font-bold text-ink">×</button>
            </div>
            <div class="grid min-h-0 flex-1 place-items-center">
                <img :src="g[i] ? g[i].src : ''" :alt="g[i] ? g[i].alt : ''" class="max-h-full max-w-full rounded-[2rem] object-contain">
            </div>
            <div class="mt-4 flex items-center justify-center gap-2">
                <button @click="prev()" class="rounded-full bg-paper px-5 py-3 font-bold text-ink">←</button>
                <span class="text-paper" x-text="(i + 1) + ' / ' + g.length"></span>
                <button @click="next()" class="rounded-full bg-paper px-5 py-3 font-bold text-ink">→</button>
            </div>
        </div>
    </div>
</section>

<script>
document.addEventListener('alpine:init', () => {
    Alpine.data('cityHero', (gallery) => ({
        g: Array.isArray(gallery) ? gallery : [],
        open: false,
        i: 0,
        openG(idx) { if (!this.g.length) return; this.i = Math.max(0, Math.min(idx, this.g.length - 1)); this.open = true; },
        next() { if (this.g.length) this.i = (this.i + 1) % this.g.length; },
        prev() { if (this.g.length) this.i = (this.i - 1 + this.g.length) % this.g.length; },
    }));
});
</script>

<!-- ============================== QUICK FACTS ============================== -->
<section class="bg-paper">
    <div class="mx-auto grid max-w-[1500px] gap-4 px-4 py-6 sm:px-6 md:grid-cols-4">
        <article class="rounded-[1.5rem] border border-ink/10 bg-paper-2 p-5"><p class="font-mono text-xs tracking-[.16em] text-ink-soft">SEZON BUN</p><p class="mt-2 font-display text-3xl font-bold leading-none">Tot anul</p><p class="mt-2 text-sm text-ink-soft">activități indoor și outdoor în fiecare sezon</p></article>
        <article class="rounded-[1.5rem] border border-ink/10 bg-paper-2 p-5"><p class="font-mono text-xs tracking-[.16em] text-ink-soft">CITY BREAK</p><p class="mt-2 font-display text-3xl font-bold leading-none">2 – 4 zile</p><p class="mt-2 text-sm text-ink-soft">destul pentru atracții, tururi și experiențe</p></article>
        <article class="rounded-[1.5rem] border border-ink/10 bg-paper-2 p-5"><p class="font-mono text-xs tracking-[.16em] text-ink-soft">PENTRU FAMILII</p><p class="mt-2 font-display text-3xl font-bold leading-none">multe opțiuni</p><p class="mt-2 text-sm text-ink-soft">muzee interactive, ateliere, parcuri, teatru</p></article>
        <article class="rounded-[1.5rem] border border-ink/10 bg-paper-2 p-5"><p class="font-mono text-xs tracking-[.16em] text-ink-soft">BILETE</p><p class="mt-2 font-display text-3xl font-bold leading-none">QR digital</p><p class="mt-2 text-sm text-ink-soft">rezervare rapidă și puncte bonus</p></article>
    </div>
</section>

<!-- ============================== STICKY SECTION NAV ============================== -->
<section class="sticky top-[72px] z-40 border-y border-ink/10 bg-paper/95 backdrop-blur-xl">
    <div class="mx-auto flex max-w-[1500px] gap-2 overflow-x-auto px-4 py-3 text-sm font-bold sm:px-6">
        <a href="#activitati" class="shrink-0 rounded-full bg-ink px-4 py-2.5 text-paper">Top activități</a>
        <?php if (!empty($cityAttractions)): ?><a href="#attractions" class="shrink-0 rounded-full bg-paper-2 px-4 py-2.5 hover:bg-ink hover:text-paper">Atracții</a><?php endif; ?>
        <a href="#categorii" class="shrink-0 rounded-full bg-paper-2 px-4 py-2.5 hover:bg-ink hover:text-paper">Categorii</a>
        <a href="#by-traveler" class="shrink-0 rounded-full bg-paper-2 px-4 py-2.5 hover:bg-ink hover:text-paper">Pentru cine</a>
        <?php if (!empty($nearbyCities)): ?><a href="#nearby" class="shrink-0 rounded-full bg-paper-2 px-4 py-2.5 hover:bg-ink hover:text-paper">Aproape de <?= htmlspecialchars($cityName) ?></a><?php endif; ?>
        <?php if (!empty($cityGuides)): ?><a href="#guides" class="shrink-0 rounded-full bg-paper-2 px-4 py-2.5 hover:bg-ink hover:text-paper">Ghiduri</a><?php endif; ?>
        <a href="#ghid-local" class="shrink-0 rounded-full bg-paper-2 px-4 py-2.5 hover:bg-ink hover:text-paper">FAQ</a>
    </div>
</section>

<!-- ============================== ATRACTII (F4) ============================== -->
<?php if (!empty($cityAttractions)): ?>
<section id="attractions" class="bg-paper">
    <div class="mx-auto max-w-[1500px] px-4 py-12 sm:px-6 lg:py-16">
        <div class="flex flex-col gap-4 md:flex-row md:items-end md:justify-between">
            <div>
                <p class="font-mono text-xs tracking-[.18em] text-vermilion">ATRACȚII DE NERATAT</p>
                <h2 class="mt-2 font-display text-6xl font-bold leading-none">Atracții pe care merită să le pui pe listă</h2>
            </div>
        </div>
        <div class="mt-8 grid gap-5 md:grid-cols-2 xl:grid-cols-4">
            <?php $atBg = ['from-vermilion to-vermilion-d','from-forest to-ink','from-sky to-ink','from-ochre to-vermilion-d']; foreach ($cityAttractions as $ai => $at): ?>
                <a href="/atractie/<?= htmlspecialchars($at['slug'], ENT_QUOTES) ?>" class="group overflow-hidden rounded-[2rem] border-2 border-ink bg-paper shadow-deep transition hover:-translate-y-0.5">
                    <div class="relative h-56 overflow-hidden">
                        <?php if (!empty($at['image'])): ?>
                            <img src="<?= htmlspecialchars($at['image'], ENT_QUOTES) ?>" alt="<?= htmlspecialchars($at['name'], ENT_QUOTES) ?>" class="h-full w-full object-cover transition duration-500 group-hover:scale-105" loading="lazy">
                        <?php else: ?>
                            <div class="grid h-full place-items-center bg-gradient-to-br <?= $atBg[$ai % count($atBg)] ?> text-paper"><span class="px-4 text-center font-display text-2xl font-bold"><?= htmlspecialchars($at['name']) ?></span></div>
                        <?php endif; ?>
                        <?php if (!empty($at['type'])): ?><span class="absolute left-4 top-4 rounded-full bg-paper px-3 py-1 text-xs font-bold text-ink"><?= htmlspecialchars($at['type']) ?></span><?php endif; ?>
                        <?php if (!empty($at['count'])): ?><span class="absolute right-4 top-4 rounded-full bg-ink/90 px-3 py-1 text-xs font-bold text-paper"><?= (int) $at['count'] ?> activități</span><?php endif; ?>
                    </div>
                    <div class="p-5">
                        <p class="font-display text-3xl font-bold leading-none group-hover:text-vermilion"><?= htmlspecialchars($at['name']) ?></p>
                    </div>
                </a>
            <?php endforeach; ?>
        </div>
    </div>
</section>
<?php endif; ?>

<!-- ============================== CATEGORY QUICK LINKS ============================== -->
<?php if (!empty($topCategories)): ?>
<section id="categorii" class="max-w-7xl mx-auto px-4 sm:px-6 py-12 lg:py-16">
    <div class="flex items-end justify-between gap-6 mb-8">
        <div>
            <p class="font-mono text-xs tracking-[.2em] text-vermilion mb-2">CATEGORII LOCALE</p>
            <h2 class="font-display text-[clamp(2rem,4vw,3.8rem)] font-700 leading-[0.95]">Alege ce fel de zi vrei în <?= htmlspecialchars($cityName) ?></h2>
        </div>
        <a href="#activitati" class="hidden sm:inline-flex items-center gap-2 font-700 text-vermilion underline-wobble">
            Vezi toate activitățile
            <svg viewBox="0 0 24 24" class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2.3" aria-hidden="true"><path d="M5 12h14m-6-6 6 6-6 6"/></svg>
        </a>
    </div>

    <div class="grid sm:grid-cols-2 lg:grid-cols-4 gap-4">
        <?php
        $catAccentBg = [
            'vermilion' => 'bg-gradient-to-br from-vermilion to-vermilion-d text-vermilion',
            'forest'    => 'bg-gradient-to-br from-forest-l to-forest text-forest',
            'sky'       => 'bg-gradient-to-br from-sky to-ink text-sky',
            'ochre'     => 'bg-gradient-to-br from-ochre to-vermilion-d text-ochre',
        ];
        foreach ($topCategories as $cat):
            $bgClass = $catAccentBg[$cat['accent']] ?? $catAccentBg['vermilion'];
        ?>
            <a href="/<?= htmlspecialchars($slug, ENT_QUOTES) ?>?category=<?= htmlspecialchars($cat['slug'], ENT_QUOTES) ?>" class="ticket ticket-lift group border-2 border-ink rounded-2xl overflow-hidden bg-paper" style="--perf:100%">
                <div class="duotone h-32 p-5 flex items-end <?= $bgClass ?>">
                    <div class="grid-tex"></div>
                    <span class="relative text-paper/90 font-mono text-[10px] tracking-wider"><?= htmlspecialchars($cat['count']) ?></span>
                    <span class="absolute top-5 right-5 text-3xl text-paper/40 group-hover:scale-110 transition-transform" aria-hidden="true"><?= htmlspecialchars($cat['icon_emoji'] ?? '🎫') ?></span>
                </div>
                <div class="p-5">
                    <h3 class="font-display text-2xl font-700 leading-none"><?= htmlspecialchars($cat['label']) ?></h3>
                </div>
            </a>
        <?php endforeach; ?>
    </div>
</section>
<?php endif; ?>

<!-- ============================== EVENTS LISTING ============================== -->
<section id="activitati" x-data="cityFilters()" class="bg-paper-2/70 border-y border-ink/10">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 py-16 lg:py-20">

        <div class="flex flex-col sm:flex-row sm:items-end justify-between gap-4 mb-8">
            <div>
                <p class="font-mono text-xs tracking-[.2em] text-vermilion mb-2">LISTĂ LOCALĂ</p>
                <h2 class="font-display text-[clamp(2rem,4vw,3.5rem)] font-700 leading-[0.95]">
                    Cele mai bune lucruri de făcut în <?= htmlspecialchars($cityName) ?>
                </h2>
                <p class="mt-3 text-ink-soft max-w-2xl">
                    <?= (int) ($pagination['total'] ?? 0) ?> <?= ($pagination['total'] ?? 0) === 1 ? 'activitate disponibilă' : 'activități disponibile' ?>
                </p>
            </div>
        </div>

        <!-- FILTER TOOLBAR -->
        <div class="sticky top-24 z-40 -mx-4 sm:-mx-6 px-4 sm:px-6 py-4 bg-paper/95 backdrop-blur-md border-y border-ink/10 mb-6">
            <!-- DESKTOP -->
            <form method="get" action="/<?= htmlspecialchars($slug, ENT_QUOTES) ?>" class="hidden lg:flex items-center gap-3" @click.outside="open=null">
                <?php if ($categoryFilter): ?><input type="hidden" name="category" value="<?= htmlspecialchars($categoryFilter, ENT_QUOTES) ?>"><?php endif; ?>
                <?php if ($maxPrice !== null): ?><input type="hidden" name="max_price" value="<?= $maxPrice ?>"><?php endif; ?>
                <?php if ($sort !== 'recommended'): ?><input type="hidden" name="sort" value="<?= htmlspecialchars($sort, ENT_QUOTES) ?>"><?php endif; ?>

                <div class="relative flex-1 max-w-xs">
                    <svg viewBox="0 0 24 24" class="w-4 h-4 absolute left-3.5 top-1/2 -translate-y-1/2 text-ink-soft" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><circle cx="11" cy="11" r="7"/><path d="m20 20-3.5-3.5"/></svg>
                    <input name="q" type="search" value="<?= htmlspecialchars($searchQuery, ENT_QUOTES) ?>" placeholder="Caută în <?= htmlspecialchars($cityName, ENT_QUOTES) ?>…" class="w-full bg-paper border-2 border-ink/15 focus:border-ink rounded-full pl-10 pr-4 py-2.5 text-[15px] focus:outline-none transition-colors" />
                </div>

                <!-- Categorie -->
                <?php
                $catFilterLabel = 'Categorie';
                if ($categoryFilter) {
                    foreach ($topCategories as $c) { if ($c['slug'] === $categoryFilter) { $catFilterLabel = $c['label']; break; } }
                    if ($catFilterLabel === 'Categorie') $catFilterLabel = ucwords(str_replace('-', ' ', $categoryFilter));
                }
                ?>
                <div class="relative">
                    <button type="button" @click="open = open==='cat' ? null : 'cat'" class="<?= $categoryFilter ? 'border-ink bg-ink text-paper' : 'border-ink/15 bg-paper hover:border-ink' ?> flex items-center gap-2 px-4 py-2.5 rounded-full border-2 text-[15px] font-500 transition">
                        <span><?= htmlspecialchars($catFilterLabel) ?></span>
                        <svg :class="open==='cat' && 'rotate-180'" class="w-3.5 h-3.5 transition-transform" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" aria-hidden="true"><path d="m6 9 6 6 6-6"/></svg>
                    </button>
                    <div x-show="open==='cat'" x-cloak x-transition class="absolute left-0 top-[calc(100%+10px)] z-50 w-64 bg-paper border-2 border-ink rounded-2xl shadow-2xl p-2 max-h-80 overflow-y-auto">
                        <a href="<?= htmlspecialchars(navBuildUrl($slug, $_GET, [], ['category']), ENT_QUOTES) ?>" class="block w-full text-left px-3 py-2 rounded-lg text-[15px] hover:bg-paper-2 transition <?= !$categoryFilter ? 'bg-ink text-paper' : '' ?>">Toate categoriile</a>
                        <?php foreach ($topCategories as $c): ?>
                            <a href="<?= htmlspecialchars(navBuildUrl($slug, $_GET, ['category' => $c['slug']]), ENT_QUOTES) ?>" class="block w-full text-left px-3 py-2 rounded-lg text-[15px] hover:bg-paper-2 transition <?= $categoryFilter === $c['slug'] ? 'bg-ink text-paper' : '' ?>">
                                <?php if (!empty($c['icon_emoji'])): ?><span class="mr-1"><?= htmlspecialchars($c['icon_emoji']) ?></span><?php endif; ?>
                                <?= htmlspecialchars($c['label']) ?>
                            </a>
                        <?php endforeach; ?>
                    </div>
                </div>

                <!-- Preț -->
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
                    <?php $sortLabels = ['recommended' => 'Recomandate', 'price_asc' => 'Preț ↑', 'price_desc' => 'Preț ↓', 'name_asc' => 'Alfabetic', 'date_asc' => 'Dată']; ?>
                    <button type="button" @click="open = open==='sort' ? null : 'sort'" class="<?= $sort !== 'recommended' ? 'border-ink bg-ink text-paper' : 'border-ink/15 bg-paper hover:border-ink' ?> flex items-center gap-2 px-4 py-2.5 rounded-full border-2 text-[15px] font-500 transition">
                        <span><?= htmlspecialchars($sortLabels[$sort] ?? 'Sortare') ?></span>
                        <svg :class="open==='sort' && 'rotate-180'" class="w-3.5 h-3.5 transition-transform" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" aria-hidden="true"><path d="m6 9 6 6 6-6"/></svg>
                    </button>
                    <div x-show="open==='sort'" x-cloak x-transition class="absolute right-0 top-[calc(100%+10px)] z-50 w-52 bg-paper border-2 border-ink rounded-2xl shadow-2xl p-2">
                        <?php foreach ($sortLabels as $value => $label): ?>
                            <a href="<?= htmlspecialchars($value === 'recommended' ? navBuildUrl($slug, $_GET, [], ['sort']) : navBuildUrl($slug, $_GET, ['sort' => $value]), ENT_QUOTES) ?>" class="block w-full text-left px-3 py-2 rounded-lg text-[15px] hover:bg-paper-2 transition <?= $sort === $value ? 'bg-ink text-paper' : '' ?>">
                                <?= htmlspecialchars($label) ?>
                            </a>
                        <?php endforeach; ?>
                    </div>
                </div>

                <button type="submit" class="px-5 py-2.5 rounded-full bg-vermilion text-paper font-600 hover:bg-vermilion-d transition-colors">Caută</button>
            </form>

            <!-- MOBILE -->
            <form method="get" action="/<?= htmlspecialchars($slug, ENT_QUOTES) ?>" class="lg:hidden flex items-center gap-2">
                <?php if ($categoryFilter): ?><input type="hidden" name="category" value="<?= htmlspecialchars($categoryFilter, ENT_QUOTES) ?>"><?php endif; ?>
                <?php if ($maxPrice !== null): ?><input type="hidden" name="max_price" value="<?= $maxPrice ?>"><?php endif; ?>
                <?php if ($sort !== 'recommended'): ?><input type="hidden" name="sort" value="<?= htmlspecialchars($sort, ENT_QUOTES) ?>"><?php endif; ?>
                <div class="relative flex-1">
                    <svg viewBox="0 0 24 24" class="w-4 h-4 absolute left-3.5 top-1/2 -translate-y-1/2 text-ink-soft" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><circle cx="11" cy="11" r="7"/><path d="m20 20-3.5-3.5"/></svg>
                    <input name="q" type="search" value="<?= htmlspecialchars($searchQuery, ENT_QUOTES) ?>" placeholder="Caută…" class="w-full bg-paper border-2 border-ink/15 focus:border-ink rounded-full pl-10 pr-4 py-2.5 text-[15px] focus:outline-none" />
                </div>
                <button type="button" @click="sheet=true" class="relative shrink-0 flex items-center gap-2 px-4 py-2.5 rounded-full border-2 border-ink font-600 text-sm">
                    <svg viewBox="0 0 24 24" class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M3 5h18M6 12h12M10 19h4"/></svg>
                    Filtre
                    <?php if (!empty($activeChips)): ?><span class="grid place-items-center w-5 h-5 rounded-full bg-vermilion text-paper text-[11px] font-700"><?= count($activeChips) ?></span><?php endif; ?>
                </button>
            </form>

            <!-- active chips -->
            <?php if (!empty($activeChips)): ?>
                <div class="flex flex-wrap items-center gap-2 mt-3">
                    <?php foreach ($activeChips as $ch): ?>
                        <a href="/<?= htmlspecialchars($slug, ENT_QUOTES) ?><?= htmlspecialchars($ch['remove_qs'], ENT_QUOTES) ?>" class="group flex items-center gap-1.5 pl-3 pr-2 py-1 rounded-full bg-paper-2 border border-ink/15 text-sm hover:border-ink transition">
                            <span><?= htmlspecialchars($ch['label']) ?></span>
                            <svg class="w-3.5 h-3.5 text-ink-soft group-hover:text-vermilion" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" aria-hidden="true"><path d="M6 6l12 12M18 6 6 18"/></svg>
                        </a>
                    <?php endforeach; ?>
                    <a href="/<?= htmlspecialchars($slug, ENT_QUOTES) ?>" class="text-sm font-600 text-vermilion underline-wobble ml-1">Șterge tot</a>
                </div>
            <?php endif; ?>
        </div>

        <!-- MOBILE FILTER SHEET -->
        <div x-show="sheet" x-cloak class="lg:hidden fixed inset-0 z-[70]" @keydown.escape.window="sheet=false">
            <div x-show="sheet" x-transition.opacity class="absolute inset-0 bg-ink/50" @click="sheet=false"></div>
            <div x-show="sheet"
                 x-transition:enter="transition ease-out duration-300" x-transition:enter-start="translate-y-full" x-transition:enter-end="translate-y-0"
                 x-transition:leave="transition ease-in duration-200" x-transition:leave-start="translate-y-0" x-transition:leave-end="translate-y-full"
                 class="absolute bottom-0 inset-x-0 bg-paper rounded-t-3xl border-t-2 border-ink max-h-[88vh] overflow-y-auto">
                <div class="sticky top-0 bg-paper flex items-center justify-between px-5 py-4 border-b border-ink/10">
                    <h2 class="font-display text-xl font-700">Filtre</h2>
                    <button @click="sheet=false" class="grid place-items-center w-9 h-9 rounded-full border-2 border-ink/15" aria-label="Închide">
                        <svg viewBox="0 0 24 24" class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2.4" aria-hidden="true"><path d="M6 6l12 12M18 6 6 18"/></svg>
                    </button>
                </div>
                <div class="p-5 space-y-6">
                    <div>
                        <p class="font-mono text-[10px] tracking-wider text-ink-soft mb-3">CATEGORIE</p>
                        <div class="flex flex-wrap gap-2">
                            <a href="<?= htmlspecialchars(navBuildUrl($slug, $_GET, [], ['category']), ENT_QUOTES) ?>" class="px-3.5 py-2 rounded-full border-2 text-sm font-500 transition <?= !$categoryFilter ? 'bg-ink text-paper border-ink' : 'border-ink/15' ?>">Toate</a>
                            <?php foreach ($topCategories as $c): ?>
                                <a href="<?= htmlspecialchars(navBuildUrl($slug, $_GET, ['category' => $c['slug']]), ENT_QUOTES) ?>" class="px-3.5 py-2 rounded-full border-2 text-sm font-500 transition <?= $categoryFilter === $c['slug'] ? 'bg-ink text-paper border-ink' : 'border-ink/15' ?>">
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
                            <?php foreach (['recommended' => 'Recomandate', 'price_asc' => 'Preț ↑', 'price_desc' => 'Preț ↓', 'name_asc' => 'Alfabetic', 'date_asc' => 'Dată'] as $value => $label):
                                $url = $value === 'recommended' ? navBuildUrl($slug, $_GET, [], ['sort']) : navBuildUrl($slug, $_GET, ['sort' => $value]);
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

        <?php if (empty($cards)): ?>
            <div class="ticket bg-paper border-2 border-ink rounded-3xl p-10 sm:p-16 text-center" style="--perf:100%">
                <p class="font-display text-3xl font-700">
                    <?php if ($categoryFilter): ?>
                        Nicio activitate din această categorie în <?= htmlspecialchars($cityName) ?>.
                    <?php else: ?>
                        Încă nu sunt activități listate aici.
                    <?php endif; ?>
                </p>
                <p class="text-ink-soft mt-2">Reveniți în curând — operatorii își vor adăuga curând experiențele.</p>
                <a href="/categorii" class="inline-block mt-5 px-5 py-3 rounded-full bg-ink text-paper font-700 hover:bg-vermilion transition">Vezi alte categorii</a>
            </div>
        <?php else: ?>
            <div class="grid sm:grid-cols-2 lg:grid-cols-3 gap-5">
                <?php foreach ($cards as $ev):
                    $evTitle = $ev['title'];
                    $evCat = $ev['cat'];
                    $evUrl = $ev['url'];
                    $evCover = $ev['cover'];
                    $evPriceCents = $ev['price_cents'] ?? null;
                    $evPrice = navFormatPriceCents($evPriceCents);
                ?>
                    <article class="ticket ticket-lift group bg-paper border-2 border-ink rounded-2xl overflow-hidden flex flex-col" style="--perf:100%">
                        <a href="<?= htmlspecialchars($evUrl, ENT_QUOTES) ?>" class="block">
                            <?php if ($evCover): ?>
                                <img src="<?= htmlspecialchars($evCover, ENT_QUOTES) ?>" alt="<?= htmlspecialchars($evTitle, ENT_QUOTES) ?>" class="h-44 w-full object-cover" loading="lazy" width="600" height="320">
                            <?php else: ?>
                                <div class="duotone h-36 p-4 bg-gradient-to-br from-vermilion to-vermilion-d text-vermilion">
                                    <div class="grid-tex"></div>
                                    <?php if ($evCat): ?>
                                        <span class="relative font-mono text-[10px] text-paper/90 bg-ink/25 px-2 py-1 rounded"><?= htmlspecialchars(strtoupper($evCat)) ?></span>
                                    <?php endif; ?>
                                </div>
                            <?php endif; ?>
                        </a>

                        <div class="p-5 flex-1 flex flex-col">
                            <?php if ($evCat): ?>
                                <p class="font-mono text-[10px] text-ink-soft tracking-wider"><?= htmlspecialchars(strtoupper($evCat)) ?></p>
                            <?php endif; ?>
                            <a href="<?= htmlspecialchars($evUrl, ENT_QUOTES) ?>" class="group">
                                <h3 class="font-display text-2xl font-700 leading-tight mt-1.5 group-hover:text-vermilion transition-colors"><?= htmlspecialchars($evTitle) ?></h3>
                            </a>

                            <div class="mt-auto pt-5 flex items-end justify-between gap-3">
                                <div>
                                    <?php if ($evPriceCents !== null && $evPriceCents > 0): ?>
                                        <p class="text-xs text-ink-soft">de la</p>
                                    <?php endif; ?>
                                    <p class="font-display text-2xl font-700"><?= htmlspecialchars($evPrice) ?></p>
                                </div>
                                <a href="<?= htmlspecialchars($evUrl, ENT_QUOTES) ?>" class="px-4 py-2.5 rounded-full bg-ink text-paper text-sm font-700 hover:bg-vermilion transition-colors"><?= htmlspecialchars($ev['cta']) ?></a>
                            </div>
                        </div>
                    </article>
                <?php endforeach; ?>
            </div>

            <!-- pagination -->
            <?php if (($pagination['last_page'] ?? 1) > 1): ?>
                <nav class="mt-10 flex justify-center gap-2 flex-wrap" aria-label="Pagini">
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
                        <a href="<?= htmlspecialchars($baseLink) ?>page=<?= $current - 1 ?>" class="px-4 py-2 rounded-full border-2 border-ink/20 hover:border-ink font-600 text-sm">‹ Anterior</a>
                    <?php endif; ?>
                    <?php for ($p = $start; $p <= $end; $p++): ?>
                        <a href="<?= htmlspecialchars($baseLink) ?>page=<?= $p ?>" class="px-4 py-2 rounded-full border-2 font-600 text-sm <?= $p === $current ? 'bg-ink text-paper border-ink' : 'border-ink/20 hover:border-ink' ?>"><?= $p ?></a>
                    <?php endfor; ?>
                    <?php if ($current < $last): ?>
                        <a href="<?= htmlspecialchars($baseLink) ?>page=<?= $current + 1 ?>" class="px-4 py-2 rounded-full border-2 border-ink/20 hover:border-ink font-600 text-sm">Următor ›</a>
                    <?php endif; ?>
                </nav>
            <?php endif; ?>
        <?php endif; ?>
    </div>
</section>

<script>
document.addEventListener('alpine:init', () => {
    Alpine.data('cityFilters', () => ({ open: null, sheet: false }));
});
</script>

<!-- ============================== BY TRAVELER ============================== -->
<section id="by-traveler" class="bg-ink text-paper">
    <div class="mx-auto max-w-[1500px] px-4 py-12 sm:px-6 lg:py-16">
        <p class="font-mono text-xs tracking-[.18em] text-ochre">PENTRU CINE</p>
        <h2 class="mt-2 font-display text-6xl font-bold leading-none">Alege după stilul tău de ieșire în <?= htmlspecialchars($cityName) ?></h2>
        <div class="mt-8 grid gap-5 md:grid-cols-2 xl:grid-cols-4">
            <?php foreach ($travelerTypes as $tt): ?>
                <a href="<?= htmlspecialchars($tt['href'], ENT_QUOTES) ?>" class="group rounded-[2rem] border border-paper/10 bg-paper/10 p-6 transition hover:-translate-y-0.5 hover:bg-paper hover:text-ink">
                    <p class="text-4xl"><?= $tt['icon'] ?></p>
                    <p class="mt-5 font-display text-4xl font-bold leading-none group-hover:text-vermilion"><?= htmlspecialchars($tt['title']) ?></p>
                    <p class="mt-3 text-paper/60 group-hover:text-ink-soft"><?= htmlspecialchars($tt['desc']) ?></p>
                </a>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<?php if (!empty($nearbyCities)): ?>
<!-- ============================== NEARBY ============================== -->
<section id="nearby" class="bg-paper">
    <div class="mx-auto max-w-[1500px] px-4 py-12 sm:px-6 lg:py-16">
        <p class="font-mono text-xs tracking-[.18em] text-vermilion">ÎN APROPIERE</p>
        <h2 class="mt-2 font-display text-6xl font-bold leading-none">Alte orașe de explorat lângă <?= htmlspecialchars($cityName) ?></h2>
        <p class="mt-3 max-w-3xl text-ink-soft">Pentru excursii de o zi, experiențe de weekend sau activități care merg bine împreună cu o vizită în <?= htmlspecialchars($cityName) ?>.</p>
        <div class="mt-8 grid gap-4 md:grid-cols-2 xl:grid-cols-5">
            <?php $nbBg = ['from-vermilion to-vermilion-d','from-forest to-ink','from-sky to-ink','from-ochre to-vermilion-d','from-ink to-forest']; foreach ($nearbyCities as $ni => $nc): ?>
                <a href="<?= htmlspecialchars($nc['href'], ENT_QUOTES) ?>" class="group overflow-hidden rounded-[2rem] border-2 border-ink bg-paper shadow-deep transition hover:-translate-y-0.5">
                    <div class="grid h-40 place-items-end bg-gradient-to-br <?= $nbBg[$ni % count($nbBg)] ?> p-4">
                        <span class="font-mono text-[10px] tracking-wider text-paper/80">ORAȘ</span>
                    </div>
                    <div class="p-4">
                        <p class="font-display text-3xl font-bold leading-none group-hover:text-vermilion"><?= htmlspecialchars($nc['label']) ?></p>
                        <p class="mt-1 text-sm text-ink-soft">Activități și experiențe locale</p>
                    </div>
                </a>
            <?php endforeach; ?>
        </div>
    </div>
</section>
<?php endif; ?>

<?php if (!empty($cityGuides)): ?>
<!-- ============================== GUIDES ============================== -->
<section id="guides" class="bg-paper-2">
    <div class="mx-auto max-w-[1500px] px-4 py-12 sm:px-6 lg:py-16">
        <div class="flex flex-col gap-4 md:flex-row md:items-end md:justify-between">
            <div>
                <p class="font-mono text-xs tracking-[.18em] text-ink-soft">ARTICOLE</p>
                <h2 class="mt-2 font-display text-6xl font-bold leading-none">Ghiduri și idei pentru <?= htmlspecialchars($cityName) ?></h2>
            </div>
            <a href="/ghiduri" class="rounded-full bg-ink px-5 py-3 font-bold text-paper transition hover:bg-vermilion">Toate ghidurile</a>
        </div>
        <div class="mt-8 grid gap-5 lg:grid-cols-3">
            <?php foreach ($cityGuides as $guide): ?>
                <a href="<?= htmlspecialchars($guide['href'], ENT_QUOTES) ?>" class="group overflow-hidden rounded-[2rem] border-2 border-ink bg-paper shadow-deep transition hover:-translate-y-0.5">
                    <?php if (!empty($guide['image'])): ?>
                        <img src="<?= htmlspecialchars($guide['image'], ENT_QUOTES) ?>" alt="<?= htmlspecialchars($guide['title'], ENT_QUOTES) ?>" class="h-56 w-full object-cover transition duration-500 group-hover:scale-105" loading="lazy">
                    <?php else: ?>
                        <div class="grid h-56 place-items-center bg-gradient-to-br from-forest to-ink text-paper"><span class="font-display text-2xl font-bold opacity-80">Ghid</span></div>
                    <?php endif; ?>
                    <div class="p-5">
                        <p class="font-mono text-xs tracking-[.16em] text-ink-soft"><?= htmlspecialchars($guide['kicker']) ?></p>
                        <p class="mt-2 font-display text-4xl font-bold leading-none group-hover:text-vermilion"><?= htmlspecialchars($guide['title']) ?></p>
                        <?php if (!empty($guide['excerpt'])): ?><p class="mt-3 line-clamp-3 text-ink-soft"><?= htmlspecialchars($guide['excerpt']) ?></p><?php endif; ?>
                        <p class="mt-4 text-sm font-bold text-vermilion">Citește ghidul →</p>
                    </div>
                </a>
            <?php endforeach; ?>
        </div>
    </div>
</section>
<?php endif; ?>

<?php if ($gygWidgetEnabled): ?>
<!-- ============================== GETYOURGUIDE WIDGET ============================== -->
<!--
     Affiliate widget — surfaces tours + activities for this city from
     GetYourGuide. Only renders when BOTH the per-city GYG location id
     (marketplace_cities.getyourguide_city_id) AND the per-marketplace
     partner id (marketplace_clients.settings.affiliate.getyourguide_partner_id)
     are configured. Without both, the section is skipped entirely so
     unconfigured cities don't show an empty box.

     The GYG SDK script is loaded with async+defer so it never blocks
     the LCP — the widget hydrates after the page is interactive.
-->
<section id="getyourguide" class="max-w-7xl mx-auto px-4 sm:px-6 py-16 lg:py-20 border-t border-ink/10">
    <div class="mb-8 max-w-3xl">
        <p class="font-mono text-xs tracking-[.2em] text-vermilion mb-3">EXTRA · PRIN PARTENERII NOȘTRI</p>
        <h2 class="font-display text-[clamp(1.8rem,3vw,2.6rem)] font-700 leading-[1.05] mb-3">
            Mai multe activități și tururi în <?= htmlspecialchars($cityName) ?>
        </h2>
        <p class="text-ink-soft leading-relaxed">
            Selecție de tururi ghidate, experiențe și activități internaționale, disponibile prin GetYourGuide.
        </p>
    </div>
    <div
        data-gyg-href="https://widget.getyourguide.com/default/city.frame"
        data-gyg-location-id="<?= htmlspecialchars($gygCityId, ENT_QUOTES) ?>"
        data-gyg-locale-code="ro-RO"
        data-gyg-widget="city"
        data-gyg-partner-id="<?= htmlspecialchars($gygPartnerId, ENT_QUOTES) ?>"
        aria-label="Activități GetYourGuide pentru <?= htmlspecialchars($cityName, ENT_QUOTES) ?>"
    ></div>
</section>
<script async defer src="https://widget.getyourguide.com/dist/pa.umd.production.min.js"></script>
<?php endif; ?>

<!-- ============================== EDITORIAL + CROSS-LINKS ============================== -->
<section id="ghid-local" class="max-w-7xl mx-auto px-4 sm:px-6 py-20 lg:py-28">
    <div class="grid lg:grid-cols-[1fr_.75fr] gap-14 items-start">

        <article class="max-w-3xl">
            <p class="font-mono text-xs tracking-[.2em] text-vermilion mb-3">GHID LOCAL</p>
            <h2 class="font-display text-[clamp(2.1rem,4vw,4rem)] font-700 leading-[0.92] mb-7">
                <?php if ($citySeoTitle): ?>
                    <?= htmlspecialchars($citySeoTitle) ?>
                <?php else: ?>
                    Ce să faci în <?= htmlspecialchars($cityName) ?>: idei pentru weekend, familie sau o zi liberă.
                <?php endif; ?>
            </h2>

            <?php if ($citySeoHtml): ?>
                <div class="prose-custom text-[17px] leading-relaxed text-ink-soft">
                    <?= strip_tags($citySeoHtml, '<p><h2><h3><h4><strong><em><b><i><u><a><ul><ol><li><blockquote><br><span>') ?>
                </div>
            <?php else: ?>
                <div class="space-y-5 text-[17px] leading-relaxed text-ink-soft">
                    <?php if ($cityDescription): ?>
                        <p><?= nl2br(htmlspecialchars($cityDescription)) ?></p>
                    <?php else: ?>
                        <p>
                            <?= htmlspecialchars($cityName) ?> oferă o gamă largă de activități pentru weekend sau o zi liberă: poți combina o plimbare în centru cu un escape room, o vizită la muzee, o activitate pentru copii sau o ieșire de aventură în apropiere.
                        </p>
                    <?php endif; ?>
                    <p>
                        Această pagină adună activitățile disponibile în oraș și în zona apropiată, cu informații utile despre preț, durată, public potrivit și disponibilitate. După plată, biletul ajunge pe email cu cod QR și poate fi scanat direct la intrare.
                    </p>
                </div>
            <?php endif; ?>

            <!-- Local search crosslinks -->
            <?php if (!empty($topCategories)): ?>
            <div class="mt-10">
                <h3 class="font-display text-3xl font-700">Căutări locale utile</h3>
                <div class="mt-4 flex flex-wrap gap-2">
                    <?php foreach ($topCategories as $cat): ?>
                        <a href="/<?= htmlspecialchars($slug, ENT_QUOTES) ?>?category=<?= htmlspecialchars($cat['slug'], ENT_QUOTES) ?>" class="px-3.5 py-1.5 rounded-full bg-paper-2 border border-ink/10 text-sm hover:bg-ink hover:text-paper transition">
                            <?= htmlspecialchars(mb_strtolower($cat['label'])) ?> <?= htmlspecialchars($cityName) ?>
                        </a>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>

            <!-- Intent crosslinks (SEO gold — connects city pages to intent system) -->
            <div class="mt-8">
                <h3 class="font-display text-3xl font-700">După ce ai chef</h3>
                <div class="mt-4 flex flex-wrap gap-2">
                    <a href="/<?= htmlspecialchars($slug, ENT_QUOTES) ?>/activitati-azi" class="px-3.5 py-1.5 rounded-full bg-paper-2 border border-ink/10 text-sm hover:bg-ink hover:text-paper transition">⏰ azi în <?= htmlspecialchars($cityName) ?></a>
                    <a href="/<?= htmlspecialchars($slug, ENT_QUOTES) ?>/activitati-weekend" class="px-3.5 py-1.5 rounded-full bg-paper-2 border border-ink/10 text-sm hover:bg-ink hover:text-paper transition">🎉 weekend</a>
                    <a href="/<?= htmlspecialchars($slug, ENT_QUOTES) ?>/activitati-gratuite" class="px-3.5 py-1.5 rounded-full bg-paper-2 border border-ink/10 text-sm hover:bg-ink hover:text-paper transition">🎁 gratuite</a>
                    <a href="/<?= htmlspecialchars($slug, ENT_QUOTES) ?>/activitati-copii" class="px-3.5 py-1.5 rounded-full bg-paper-2 border border-ink/10 text-sm hover:bg-ink hover:text-paper transition">🧒 copii</a>
                    <a href="/<?= htmlspecialchars($slug, ENT_QUOTES) ?>/activitati-indoor" class="px-3.5 py-1.5 rounded-full bg-paper-2 border border-ink/10 text-sm hover:bg-ink hover:text-paper transition">🏛️ indoor</a>
                    <a href="/<?= htmlspecialchars($slug, ENT_QUOTES) ?>/activitati-romantice" class="px-3.5 py-1.5 rounded-full bg-paper-2 border border-ink/10 text-sm hover:bg-ink hover:text-paper transition">💞 romantice</a>
                </div>
            </div>

            <!-- FAQ from admin (if any) -->
            <?php if (!empty($cityFaqs)): ?>
            <div class="mt-10" x-data="{ active: 0 }">
                <h3 class="font-display text-3xl font-700 mb-5">Întrebări frecvente despre <?= htmlspecialchars($cityName) ?></h3>
                <div class="space-y-3">
                    <?php foreach ($cityFaqs as $i => $f): ?>
                        <div class="border-2 border-ink rounded-xl overflow-hidden bg-paper">
                            <button type="button" @click="active = active === <?= $i ?> ? null : <?= $i ?>" :aria-expanded="active === <?= $i ?>" class="w-full flex items-center justify-between gap-4 text-left px-5 py-4">
                                <span class="font-600 text-[16px]"><?= htmlspecialchars($f['q']) ?></span>
                                <span class="shrink-0 grid place-items-center w-7 h-7 rounded-full border-2 border-ink transition-transform duration-300" :class="active === <?= $i ?> && 'rotate-45 bg-vermilion border-vermilion text-paper'">
                                    <svg viewBox="0 0 24 24" class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2.4" aria-hidden="true"><path d="M12 5v14M5 12h14"/></svg>
                                </span>
                            </button>
                            <div x-show="active === <?= $i ?>" x-collapse x-cloak>
                                <p class="px-5 pb-5 text-ink-soft leading-relaxed"><?= htmlspecialchars($f['a']) ?></p>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>
        </article>

        <!-- Sidebar: city snapshot + nearby cities -->
        <aside class="space-y-5">
            <div class="ticket bg-ink text-paper rounded-2xl p-6 overflow-hidden" style="--perf:100%">
                <p class="font-mono text-[10px] tracking-[.2em] text-ochre"><?= htmlspecialchars(strtoupper($cityName)) ?> PE SCURT</p>
                <h3 class="font-display text-3xl font-700 mt-2"><?= htmlspecialchars($cityName) ?> pe scurt</h3>
                <dl class="mt-6 space-y-4">
                    <div class="flex justify-between gap-6 border-b border-paper/10 pb-3">
                        <dt class="text-paper/60">Activități listate</dt>
                        <dd class="font-700"><?= $eventCount ?: '—' ?></dd>
                    </div>
                    <?php if ($countyName): ?>
                    <div class="flex justify-between gap-6 border-b border-paper/10 pb-3">
                        <dt class="text-paper/60">Județ</dt>
                        <dd class="font-700"><?= htmlspecialchars($countyName) ?></dd>
                    </div>
                    <?php endif; ?>
                    <?php if ($regionName): ?>
                    <div class="flex justify-between gap-6 border-b border-paper/10 pb-3">
                        <dt class="text-paper/60">Regiune</dt>
                        <dd class="font-700"><?= htmlspecialchars($regionName) ?></dd>
                    </div>
                    <?php endif; ?>
                    <div class="flex justify-between gap-6">
                        <dt class="text-paper/60">Bilete QR</dt>
                        <dd class="font-700 text-ochre">Da</dd>
                    </div>
                </dl>
            </div>

            <!-- Other featured cities -->
            <?php
            $otherCities = array_filter(navGetCities(20), fn ($c) => $c['slug'] !== $slug);
            if (!empty($otherCities)):
            ?>
            <div class="bg-paper-2 border-2 border-ink rounded-2xl p-6">
                <p class="font-mono text-[10px] tracking-[.2em] text-vermilion">ALTE ORAȘE</p>
                <h3 class="font-display text-2xl font-700 mt-2">Poți căuta și aici</h3>
                <div class="mt-5 grid grid-cols-2 gap-2">
                    <?php foreach (array_slice($otherCities, 0, 8) as $c): ?>
                        <a href="<?= htmlspecialchars($c['href'], ENT_QUOTES) ?>" class="px-3 py-2 rounded-xl bg-paper border border-ink/10 hover:bg-ink hover:text-paper transition">
                            <?= htmlspecialchars($c['label']) ?>
                        </a>
                    <?php endforeach; ?>
                </div>
                <a href="/orase" class="inline-block mt-4 text-sm font-700 text-vermilion underline-wobble">Toate orașele →</a>
            </div>
            <?php endif; ?>

            <!-- Owner CTA -->
            <div class="bg-vermilion text-paper border-2 border-ink rounded-2xl p-6">
                <p class="font-mono text-[10px] tracking-[.2em] text-paper/75">PENTRU OPERATORI LOCALI</p>
                <h3 class="font-display text-3xl font-700 mt-2">Ai o activitate în <?= htmlspecialchars($cityName) ?>?</h3>
                <p class="mt-3 text-paper/80">Pagină dedicată, bilete QR, disponibilitate online și comision 2%.</p>
                <a href="/pentru-locatii" class="inline-flex mt-5 px-5 py-3 rounded-full bg-paper text-ink font-700 hover:bg-ink hover:text-paper transition">Listează activitatea</a>
            </div>
        </aside>
    </div>
</section>

<?php include __DIR__ . '/includes/footer.php'; ?>
