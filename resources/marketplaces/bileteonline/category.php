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
$pagination = $eventsResp['meta'] ?? ['current_page' => 1, 'last_page' => 1, 'total' => count($events)];
if (!is_array($events)) $events = [];

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

include __DIR__ . '/includes/head.php';
include __DIR__ . '/includes/header.php';
?>

<!-- ============================== HERO ============================== -->
<section class="relative overflow-hidden border-b border-ink/10">
    <div class="absolute inset-0 -z-10 bg-gradient-to-b from-paper via-paper to-paper-2"></div>
    <div class="absolute -z-10 -top-28 -right-32 w-[520px] h-[520px] rounded-full <?= $ac['bg'] ?>/10 blur-3xl" aria-hidden="true"></div>
    <div class="absolute inset-0 -z-10 opacity-[.3] bg-ruled-vertical" aria-hidden="true"></div>

    <div class="max-w-7xl mx-auto px-4 sm:px-6 pt-7 pb-12 lg:pb-16">
        <!-- breadcrumb -->
        <nav aria-label="breadcrumb" class="flex flex-wrap items-center gap-2 text-sm text-ink-soft font-mono mb-8">
            <?php foreach ($breadcrumbs as $i => $bc): ?>
                <?php if ($i > 0): ?><span class="opacity-40">/</span><?php endif; ?>
                <?php if ($i < count($breadcrumbs) - 1): ?>
                    <a href="<?= htmlspecialchars($bc['url'], ENT_QUOTES) ?>" class="hover:text-vermilion transition"><?= htmlspecialchars($bc['name']) ?></a>
                <?php else: ?>
                    <span class="text-ink font-600"><?= htmlspecialchars($bc['name']) ?></span>
                <?php endif; ?>
            <?php endforeach; ?>
        </nav>

        <div class="grid lg:grid-cols-[1.35fr_.65fr] gap-10 items-center">
            <div>
                <div class="inline-flex items-center gap-2 mb-5 px-3 py-1 rounded-full <?= $ac['bg-light'] ?> <?= $ac['text'] ?> text-xs font-mono tracking-wider">
                    <span class="w-1.5 h-1.5 rounded-full <?= $ac['bg'] ?>"></span> CATEGORIE · DISPONIBILE TOT ANUL
                </div>

                <h1 class="font-display text-[clamp(2.6rem,6vw,4.6rem)] font-700 leading-[0.92]">
                    <?php if ($catIcon): ?><span class="inline-block align-middle mr-2"><?= htmlspecialchars($catIcon) ?></span><?php endif; ?>
                    <?= htmlspecialchars($catName) ?><br>
                    <span class="ital <?= $ac['text'] ?>">în <?= htmlspecialchars($heroLocation) ?></span>
                </h1>

                <p class="mt-6 text-lg text-ink-soft max-w-2xl leading-relaxed">
                    <?php if ($catDescription): ?>
                        <?= htmlspecialchars($catDescription) ?>
                    <?php else: ?>
                        Alege orașul și activitatea — rezervi online și intri cu QR.
                    <?php endif; ?>
                </p>

                <!-- mini stats -->
                <div class="mt-8 flex flex-wrap gap-3">
                    <div class="ticket bg-paper border-2 border-ink rounded-xl px-4 py-2.5" style="--perf:100%">
                        <p class="font-display text-2xl font-700 leading-none"><?= (int) ($pagination['total'] ?? $eventCount) ?></p>
                        <p class="font-mono text-[10px] text-ink-soft mt-1">ACTIVITĂȚI</p>
                    </div>
                    <?php if (!empty($children)): ?>
                    <div class="ticket bg-paper border-2 border-ink rounded-xl px-4 py-2.5" style="--perf:100%">
                        <p class="font-display text-2xl font-700 leading-none"><?= count($children) ?></p>
                        <p class="font-mono text-[10px] text-ink-soft mt-1">SUBCATEGORII</p>
                    </div>
                    <?php endif; ?>
                    <?php if (!empty($featuredCities)): ?>
                    <div class="ticket bg-paper border-2 border-ink rounded-xl px-4 py-2.5" style="--perf:100%">
                        <p class="font-display text-2xl font-700 leading-none"><?= count($featuredCities) ?>+</p>
                        <p class="font-mono text-[10px] text-ink-soft mt-1">ORAȘE</p>
                    </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- decorative ticket (uses admin color + emoji) -->
            <div class="hidden lg:block relative h-72" aria-hidden="true">
                <div class="ticket ticket-lift absolute right-4 top-2 w-[80%] rotate-6 bg-ink text-paper rounded-2xl overflow-hidden border-2 border-ink shadow-2xl" style="--perf:72%; --punch:#1B1714">
                    <div class="duotone h-32 relative overflow-hidden bg-gradient-to-br <?= $ac['gradient'] ?> <?= $ac['text'] ?>">
                        <?php if (!empty($catImage)): ?>
                            <img src="<?= htmlspecialchars(str_starts_with($catImage, 'http') ? $catImage : STORAGE_URL . '/' . ltrim($catImage, '/'), ENT_QUOTES) ?>" alt="<?= htmlspecialchars($catName, ENT_QUOTES) ?>" loading="lazy" class="absolute inset-0 w-full h-full object-cover" />
                        <?php endif; ?>
                        <div class="grid-tex"></div>
                        <span class="stamp absolute top-4 left-4 text-paper/80 px-3 py-1 text-[10px] font-mono -rotate-6"><?= htmlspecialchars(strtoupper($catName)) ?></span>
                        <?php if (empty($catImage)): ?><span class="absolute right-5 bottom-2 text-5xl opacity-30"><?= htmlspecialchars($catIcon) ?></span><?php endif; ?>
                    </div>
                    <div class="p-5">
                        <p class="font-mono text-[10px] text-paper/50">ADMIT ONE · QR</p>
                        <h3 class="font-display text-xl font-700 mt-1">bilete.online</h3>
                        <div class="mt-3 flex items-center justify-between">
                            <span class="font-mono text-xs text-paper/60">VALABIL TOT ANUL</span>
                            <span class="px-3 py-1.5 rounded-full <?= $ac['bg'] ?> text-paper text-xs font-600">Rezervă</span>
                        </div>
                    </div>
                    <div class="notch top"></div><div class="notch bot"></div><div class="perf"></div>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- ============================== SUBCATEGORII (prominent grid) ============================== -->
<?php if (!empty($children)): ?>
<section class="max-w-7xl mx-auto px-4 sm:px-6 pt-10 pb-2">
    <div class="flex items-end justify-between gap-6 mb-6">
        <div>
            <p class="font-mono text-xs tracking-[.2em] <?= $ac['text'] ?> mb-2">SUBCATEGORII</p>
            <h2 class="font-display text-2xl sm:text-3xl font-700 leading-tight">Alege tipul de <?= htmlspecialchars(mb_strtolower($catName)) ?></h2>
        </div>
        <p class="hidden sm:block font-mono text-xs text-ink-soft"><?= count($children) ?> opțiuni</p>
    </div>
    <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 gap-3">
        <?php foreach ($children as $child):
            $childName = navFlatName($child['name'] ?? '');
            $childSlug = $child['slug'] ?? '';
            if (!$childName || !$childSlug) continue;
            $childEmoji = $child['icon_emoji'] ?? '';
            $childCount = (int) ($child['event_count'] ?? 0);
        ?>
            <a href="/<?= htmlspecialchars(bo_short_category_slug($child), ENT_QUOTES) ?>" class="group flex items-center gap-3 p-4 rounded-2xl border-2 border-ink/10 bg-paper hover:border-ink hover:<?= $ac['bg-light'] ?> transition-colors">
                <?php if ($childEmoji): ?>
                    <span class="grid place-items-center w-10 h-10 rounded-lg <?= $ac['bg-light'] ?> <?= $ac['text'] ?> shrink-0 group-hover:<?= $ac['bg'] ?> group-hover:text-paper transition-colors text-xl leading-none" aria-hidden="true"><?= htmlspecialchars($childEmoji) ?></span>
                <?php else: ?>
                    <span class="grid place-items-center w-10 h-10 rounded-lg <?= $ac['bg-light'] ?> <?= $ac['text'] ?> shrink-0 font-display text-lg font-700">
                        <?= htmlspecialchars(mb_substr($childName, 0, 1)) ?>
                    </span>
                <?php endif; ?>
                <div class="min-w-0 flex-1">
                    <p class="font-600 text-sm leading-tight truncate"><?= htmlspecialchars($childName) ?></p>
                    <?php if ($childCount > 0): ?>
                        <p class="text-xs text-ink-soft mt-0.5"><?= $childCount ?> <?= $childCount === 1 ? 'activitate' : 'activități' ?></p>
                    <?php else: ?>
                        <p class="text-xs text-ink-soft/60 mt-0.5">în curând</p>
                    <?php endif; ?>
                </div>
            </a>
        <?php endforeach; ?>
    </div>
</section>
<?php endif; ?>

<!-- ============================== FILTRE + REZULTATE ============================== -->
<section x-data="categoryFilters()" class="max-w-7xl mx-auto px-4 sm:px-6 pt-10">

    <!-- sticky filter toolbar -->
    <div class="sticky top-24 z-40 -mx-4 sm:-mx-6 px-4 sm:px-6 py-4 bg-paper/90 backdrop-blur-md border-b border-ink/10">

        <!-- DESKTOP filter pills -->
        <form method="get" action="/<?= htmlspecialchars($slug, ENT_QUOTES) ?>" class="hidden lg:flex items-center gap-3" @click.outside="open=null">
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
        <form method="get" action="/<?= htmlspecialchars($slug, ENT_QUOTES) ?>" class="lg:hidden flex items-center gap-2">
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
            <p class="font-display text-2xl font-700">
                <?= (int) ($pagination['total'] ?? 0) ?>
                <span class="text-ink-soft font-sans text-lg font-500">
                    <?= ($pagination['total'] ?? 0) === 1 ? mb_strtolower($catName) : (mb_strtolower($catName) . ' disponibile') ?>
                </span>
            </p>
            <p class="hidden sm:block font-mono text-xs text-ink-soft">DISPONIBILE TOT ANUL · INTRARE CU QR</p>
        </div>

        <?php if (empty($events)): ?>
            <div class="ticket bg-paper border-2 border-ink rounded-3xl p-10 sm:p-16 text-center" style="--perf:100%">
                <div class="inline-grid place-items-center w-16 h-16 rounded-full bg-paper-2 mb-4">
                    <svg viewBox="0 0 24 24" class="w-7 h-7 text-ink-soft" fill="none" stroke="currentColor" stroke-width="1.6" aria-hidden="true"><circle cx="11" cy="11" r="7"/><path d="m20 20-3.5-3.5"/></svg>
                </div>
                <p class="font-display text-2xl font-700">
                    <?= ($searchQuery !== '' || $cityFilter) ? 'Nimic pe filtrele astea.' : 'Încă nu sunt activități listate aici.' ?>
                </p>
                <p class="text-ink-soft mt-2">
                    <?= ($searchQuery !== '' || $cityFilter) ? 'Lărgește criteriile sau încearcă o altă subcategorie.' : 'Categoria e activă — operatorii își vor adăuga curând experiențele.' ?>
                </p>
                <?php if ($searchQuery !== '' || $cityFilter): ?>
                    <a href="/<?= htmlspecialchars($slug, ENT_QUOTES) ?>" class="inline-block mt-5 px-5 py-2.5 rounded-full bg-ink text-paper font-600">Resetează filtrele</a>
                <?php else: ?>
                    <a href="/categorii" class="inline-block mt-5 px-5 py-2.5 rounded-full bg-ink text-paper font-600">Vezi alte categorii</a>
                <?php endif; ?>
            </div>
        <?php else: ?>
            <div class="grid sm:grid-cols-2 lg:grid-cols-3 gap-5">
                <?php foreach ($events as $ev):
                    $evTitle = navEventTitle($ev);
                    $evCity = navEventCity($ev);
                    $evSlug = $ev['slug'] ?? '';
                    $evCover = $ev['cover_image_url'] ?? $ev['image_url'] ?? '';
                    $evPrice = navFormatPriceCents($ev['cheapest_price_cents'] ?? null);
                ?>
                    <a href="/bilete/<?= htmlspecialchars($evSlug, ENT_QUOTES) ?>" class="ticket ticket-lift group bg-paper border-2 border-ink rounded-2xl overflow-hidden flex flex-col" style="--perf:100%">
                        <?php if ($evCover): ?>
                            <img src="<?= htmlspecialchars($evCover, ENT_QUOTES) ?>" alt="<?= htmlspecialchars($evTitle, ENT_QUOTES) ?>" class="h-44 w-full object-cover" loading="lazy" width="600" height="320">
                        <?php else: ?>
                            <div class="duotone h-36 p-4 bg-gradient-to-br <?= $ac['gradient'] ?> <?= $ac['text'] ?>">
                                <div class="grid-tex"></div>
                                <span class="relative font-mono text-[10px] text-paper/90 bg-ink/25 px-2 py-1 rounded"><?= htmlspecialchars(strtoupper($catName)) ?></span>
                            </div>
                        <?php endif; ?>

                        <div class="p-4 flex-1 flex flex-col">
                            <?php if ($evCity): ?>
                                <p class="font-mono text-[10px] text-ink-soft tracking-wider"><?= htmlspecialchars(strtoupper($evCity)) ?></p>
                            <?php endif; ?>
                            <h3 class="font-display text-xl font-700 leading-tight mt-1.5 group-hover:<?= $ac['text'] ?> transition-colors"><?= htmlspecialchars($evTitle) ?></h3>

                            <div class="mt-auto pt-4 flex items-center justify-between">
                                <p class="text-ink-soft">
                                    <?php if (($ev['cheapest_price_cents'] ?? null) !== null && $ev['cheapest_price_cents'] > 0): ?>
                                        <span class="text-xs">de la </span>
                                    <?php endif; ?>
                                    <span class="font-display text-lg font-700 text-ink"><?= htmlspecialchars($evPrice) ?></span>
                                </p>
                                <span class="px-3 py-1.5 rounded-full bg-ink text-paper text-xs font-600 group-hover:<?= $ac['bg'] ?> transition-colors">Vezi bilete</span>
                            </div>
                        </div>
                    </a>
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
                        <a href="<?= htmlspecialchars($sib['href'], ENT_QUOTES) ?>" class="px-4 py-2 rounded-full bg-paper border border-ink/15 text-sm hover:border-ink hover:bg-ink hover:text-paper transition">
                            <?php if (!empty($sib['icon_emoji'])): ?><span class="mr-1"><?= htmlspecialchars($sib['icon_emoji']) ?></span><?php endif; ?>
                            <?= htmlspecialchars($sib['label']) ?>
                        </a>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>

        <!-- FAQ accordion -->
        <div x-data="{ active: 0 }">
            <h2 class="font-display text-2xl font-700 mb-6">Întrebări frecvente</h2>
            <div class="space-y-3">
                <?php foreach ($faqItems as $i => $f): ?>
                    <div class="border-2 border-ink rounded-xl overflow-hidden bg-paper">
                        <button type="button" @click="active = active === <?= $i ?> ? null : <?= $i ?>" :aria-expanded="active === <?= $i ?>" class="w-full flex items-center justify-between gap-4 text-left px-5 py-4">
                            <span class="font-600 text-[16px]"><?= htmlspecialchars($f['q']) ?></span>
                            <span class="shrink-0 grid place-items-center w-7 h-7 rounded-full border-2 border-ink transition-transform duration-300" :class="active === <?= $i ?> && 'rotate-45 <?= $ac['bg'] ?> <?= $ac['border'] ?> text-paper'">
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
