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
        'url'         => '/activitate/' . ($a['slug'] ?? ''),
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

include __DIR__ . '/includes/head.php';
include __DIR__ . '/includes/header.php';
?>

<!-- ============================== HERO ============================== -->
<section class="relative overflow-hidden">
    <div class="absolute inset-0 pointer-events-none -z-10" aria-hidden="true">
        <div class="absolute -top-24 right-[-8rem] w-[520px] h-[520px] rounded-full bg-vermilion/10 blur-3xl"></div>
        <div class="absolute bottom-[-12rem] left-[-8rem] w-[520px] h-[520px] rounded-full bg-forest/10 blur-3xl"></div>
    </div>

    <div class="relative max-w-7xl mx-auto px-4 sm:px-6 py-16 lg:py-24 grid lg:grid-cols-[1.05fr_.95fr] gap-12 items-center">
        <div>
            <nav aria-label="Breadcrumb" class="mb-8">
                <ol class="flex flex-wrap items-center gap-2 text-sm text-ink-soft">
                    <?php foreach ($breadcrumbs as $i => $bc): ?>
                        <?php if ($i > 0): ?><li aria-hidden="true">/</li><?php endif; ?>
                        <li>
                            <?php if ($i < count($breadcrumbs) - 1): ?>
                                <a href="<?= htmlspecialchars($bc['url'], ENT_QUOTES) ?>" class="hover:text-vermilion underline-wobble"><?= htmlspecialchars($bc['name']) ?></a>
                            <?php else: ?>
                                <span aria-current="page" class="text-ink font-600"><?= htmlspecialchars($bc['name']) ?></span>
                            <?php endif; ?>
                        </li>
                    <?php endforeach; ?>
                </ol>
            </nav>

            <div class="flex flex-wrap gap-2 mb-5">
                <?php if ($eventCount > 0): ?>
                    <span class="stamp inline-flex items-center gap-2 px-3 py-1.5 text-xs font-mono tracking-wider text-vermilion bg-vermilion/5">
                        <span class="w-1.5 h-1.5 rounded-full bg-vermilion"></span> <?= $eventCount ?> ACTIVITĂȚI
                    </span>
                <?php endif; ?>
                <?php if ($countyName): ?>
                    <span class="stamp inline-flex items-center gap-2 px-3 py-1.5 text-xs font-mono tracking-wider text-forest bg-forest/5">
                        <?= htmlspecialchars(strtoupper($cityName)) ?> · JUDEȚUL <?= htmlspecialchars(strtoupper($countyName)) ?>
                    </span>
                <?php endif; ?>
            </div>

            <h1 class="font-display text-[clamp(3rem,8vw,7.7rem)] font-700 leading-[0.88] tracking-tight">
                Activități în <span class="ital text-vermilion"><?= htmlspecialchars($cityName) ?></span>
            </h1>

            <p class="mt-7 text-xl sm:text-2xl text-ink-soft leading-relaxed max-w-2xl">
                <?php if ($cityDescription): ?>
                    <?= htmlspecialchars($cityDescription) ?>
                <?php else: ?>
                    Găsește rapid ce să faci în <?= htmlspecialchars($cityName) ?>: escape rooms, muzee, parcuri de aventură, experiențe pentru copii, tururi și activități indoor sau outdoor. Rezervi online, primești bilet QR și intri fără cozi inutile.
                <?php endif; ?>
            </p>

            <!-- Local search box -->
            <form action="/cauta" method="get" class="mt-8 max-w-2xl" role="search" aria-label="Caută activități în <?= htmlspecialchars($cityName) ?>">
                <div class="ticket bg-paper border-2 border-ink rounded-2xl p-2 sm:p-3" style="--perf:100%">
                    <div class="grid sm:grid-cols-[1fr_auto] gap-2">
                        <label class="sr-only" for="city-search">Caută activități în <?= htmlspecialchars($cityName) ?></label>
                        <div class="flex items-center gap-3 px-3 py-2 bg-paper-2 rounded-xl border border-ink/10">
                            <svg viewBox="0 0 24 24" class="w-5 h-5 text-ink-soft shrink-0" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><circle cx="11" cy="11" r="7"/><path d="m20 20-3.5-3.5"/></svg>
                            <input id="city-search" name="q" placeholder="Caută: escape room, muzeu, copii, aventură..." class="w-full bg-transparent outline-none text-base placeholder:text-ink-soft/70" />
                            <input type="hidden" name="oras" value="<?= htmlspecialchars($slug, ENT_QUOTES) ?>" />
                        </div>
                        <button type="submit" class="px-6 py-3 rounded-xl bg-ink text-paper font-700 hover:bg-vermilion transition-colors">
                            Caută
                        </button>
                    </div>
                </div>
            </form>

            <!-- Popular searches: top categories quick links scoped to this city -->
            <?php if (!empty($topCategories)): ?>
            <div class="mt-6 flex flex-wrap items-center gap-2 text-sm">
                <span class="text-ink-soft mr-1">Populare:</span>
                <?php foreach (array_slice($topCategories, 0, 4) as $cat): ?>
                    <a href="/<?= htmlspecialchars($slug, ENT_QUOTES) ?>?category=<?= htmlspecialchars($cat['slug'], ENT_QUOTES) ?>" class="px-3 py-1.5 rounded-full bg-paper-2 border border-ink/10 hover:bg-ink hover:text-paper transition">
                        <?php if (!empty($cat['icon_emoji'])): ?><?= htmlspecialchars($cat['icon_emoji']) ?> <?php endif; ?>
                        <?= htmlspecialchars(mb_strtolower($cat['label'])) ?> <?= htmlspecialchars($cityName) ?>
                    </a>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </div>

        <!-- visual city ticket -->
        <div class="relative min-h-[420px] hidden lg:block" aria-hidden="true">
            <div class="absolute inset-0 rounded-[2rem] bg-ink rotate-2"></div>
            <div class="relative h-[480px] rounded-[2rem] overflow-hidden border-2 border-ink bg-paper">
                <?php if ($cityCover): ?>
                    <img src="<?= htmlspecialchars((str_starts_with($cityCover, 'http') ? $cityCover : STORAGE_URL . '/' . ltrim($cityCover, '/')), ENT_QUOTES) ?>" alt="" class="absolute inset-0 w-full h-full object-cover opacity-60 mix-blend-luminosity" loading="lazy">
                <?php else: ?>
                    <div class="absolute inset-0 grid grid-cols-2 grid-rows-2 gap-2 p-2">
                        <figure class="duotone rounded-[1.35rem] bg-gradient-to-br from-forest-l to-forest text-forest flex items-end p-5">
                            <div class="grid-tex"></div>
                            <figcaption class="relative text-paper">
                                <p class="font-mono text-[10px] tracking-wider opacity-80">CITY GUIDE</p>
                                <p class="font-display text-3xl font-700 leading-none"><?= htmlspecialchars($cityName) ?></p>
                            </figcaption>
                        </figure>
                        <figure class="duotone rounded-[1.35rem] bg-gradient-to-br from-vermilion to-vermilion-d text-vermilion flex items-end p-5 translate-y-8">
                            <div class="grid-tex"></div>
                            <figcaption class="relative text-paper">
                                <p class="font-mono text-[10px] tracking-wider opacity-80">INDOOR</p>
                                <p class="font-display text-3xl font-700 leading-none">Escape</p>
                            </figcaption>
                        </figure>
                        <figure class="duotone rounded-[1.35rem] bg-gradient-to-br from-sky to-ink text-sky flex items-end p-5 -translate-y-4">
                            <div class="grid-tex"></div>
                            <figcaption class="relative text-paper">
                                <p class="font-mono text-[10px] tracking-wider opacity-80">CULTURĂ</p>
                                <p class="font-display text-3xl font-700 leading-none">Muzee</p>
                            </figcaption>
                        </figure>
                        <figure class="duotone rounded-[1.35rem] bg-gradient-to-br from-ochre to-vermilion-d text-ochre flex items-end p-5">
                            <div class="grid-tex"></div>
                            <figcaption class="relative text-paper">
                                <p class="font-mono text-[10px] tracking-wider opacity-80">OUTDOOR</p>
                                <p class="font-display text-3xl font-700 leading-none">Aventură</p>
                            </figcaption>
                        </figure>
                    </div>
                <?php endif; ?>

                <div class="absolute left-6 bottom-6 right-6 ticket bg-paper text-ink border-2 border-ink rounded-2xl p-5" style="--perf:72%">
                    <div class="perf"></div><span class="notch top"></span><span class="notch bot"></span>
                    <div class="grid grid-cols-[1fr_auto] gap-6 items-center">
                        <div>
                            <p class="font-mono text-[10px] tracking-[.2em] text-ink-soft"><?= htmlspecialchars(strtoupper($cityName)) ?> CITY PASS</p>
                            <p class="font-display text-2xl font-700 leading-tight mt-1">Rezervă experiențe locale în 30 de secunde</p>
                        </div>
                        <div class="text-right">
                            <p class="font-mono text-[10px] text-ink-soft">DE LA</p>
                            <p class="font-display text-3xl font-700 text-vermilion">25 lei</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

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
