<?php
/**
 * Single category landing — /{category-slug}
 *
 * Doubles as a single-segment URL resolver: if the slug doesn't match an
 * active event category, falls through to city.php (which resolves to a
 * marketplace city or 404). This lets the .htaccess have a single generic
 * route for /{slug} without hardcoding a category whitelist.
 */

$pageCacheTTL = 300;
require_once __DIR__ . '/includes/page-cache.php';
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/api.php';
require_once __DIR__ . '/includes/nav-helpers.php';

$slug = $_GET['slug'] ?? '';

if (!preg_match('/^[a-z][a-z0-9-]+$/', $slug)) {
    http_response_code(404);
    require __DIR__ . '/404.php';
    exit;
}

$category = navGetCategoryBySlug($slug);

if (!$category) {
    // Not a category. Try city.php if it exists (it will resolve to city
    // or 404). If city.php hasn't been built yet, fall back to a generic
    // 404 here so the resolver chain stays clean.
    if (file_exists(__DIR__ . '/city.php')) {
        require __DIR__ . '/city.php';
        return;
    }
    http_response_code(404);
    require __DIR__ . '/404.php';
    exit;
}

// ============================================================
// Category data extraction
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

// Accent → Tailwind classes (safelisted in tailwind.config.cjs)
$accentMap = [
    'vermilion' => ['bg' => 'bg-vermilion', 'bg-light' => 'bg-vermilion/10', 'text' => 'text-vermilion', 'bg-dark' => 'bg-vermilion-d', 'gradient' => 'from-vermilion to-vermilion-d'],
    'forest'    => ['bg' => 'bg-forest',    'bg-light' => 'bg-forest/10',    'text' => 'text-forest',    'bg-dark' => 'bg-forest-l',    'gradient' => 'from-forest-l to-forest'],
    'ochre'     => ['bg' => 'bg-ochre',     'bg-light' => 'bg-ochre/15',     'text' => 'text-ochre',     'bg-dark' => 'bg-vermilion-d', 'gradient' => 'from-ochre to-vermilion-d'],
    'sky'       => ['bg' => 'bg-sky',       'bg-light' => 'bg-sky/10',       'text' => 'text-sky',       'bg-dark' => 'bg-ink-2',       'gradient' => 'from-sky to-ink'],
];
$ac = $accentMap[$accent] ?? $accentMap['vermilion'];

// ============================================================
// Events fetch for this category (paginated)
// ============================================================
$pageNum = max(1, (int) ($_GET['page'] ?? 1));
$cityFilter = isset($_GET['city']) && preg_match('/^[a-z][a-z0-9-]+$/', $_GET['city']) ? $_GET['city'] : null;

$eventsResp = api_cached("category_events_{$slug}_" . ($cityFilter ?? 'all') . "_p{$pageNum}", function () use ($slug, $cityFilter, $pageNum) {
    $params = ['category' => $slug, 'page' => $pageNum, 'per_page' => 24, 'time_scope' => 'upcoming'];
    if ($cityFilter) $params['city'] = $cityFilter;
    return api_get('/events', $params);
}, 300);

$events = $eventsResp['data'] ?? [];
$pagination = $eventsResp['meta'] ?? ['current_page' => 1, 'last_page' => 1, 'total' => count($events)];
if (!is_array($events)) $events = [];

// Featured cities for sidebar filter
$featuredCities = navGetCities(20);

// ============================================================
// SEO setup
// ============================================================
$pageTitleRaw = $metaTitle;
$pageDescription = $metaDescription;
$canonicalUrl = SITE_URL . '/' . $slug;
$ogImage = $catImage ? (STORAGE_URL . '/' . ltrim($catImage, '/')) : (SITE_URL . '/assets/images/og-default.jpg');
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

// JSON-LD CollectionPage + ItemList
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

include __DIR__ . '/includes/head.php';
include __DIR__ . '/includes/header.php';

// Helpers
function _catFormatPrice(?int $cents): string {
    if ($cents === null) return '—';
    if ($cents === 0) return 'Gratuit';
    return number_format($cents / 100, 0, ',', '.') . ' lei';
}
function _catEvTitle(array $ev): string {
    if (is_array($ev['title'] ?? null)) {
        return $ev['title']['ro'] ?? $ev['title']['en'] ?? reset($ev['title']);
    }
    return $ev['title'] ?? 'Activitate';
}
function _catEvCity(array $ev): string {
    $c = $ev['marketplace_city'] ?? null;
    if ($c && is_array($c['name'] ?? null)) return $c['name']['ro'] ?? '';
    return $ev['venue']['city'] ?? '';
}
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
                    <?= htmlspecialchars($catName) ?>
                </h1>
                <?php if ($catDescription): ?>
                    <p class="mt-6 text-lg text-ink-soft max-w-xl leading-relaxed"><?= htmlspecialchars($catDescription) ?></p>
                <?php endif; ?>

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
                </div>
            </div>

            <!-- decorative ticket -->
            <div class="hidden lg:block relative h-72" aria-hidden="true">
                <div class="ticket ticket-lift absolute right-4 top-2 w-[80%] rotate-6 bg-ink text-paper rounded-2xl overflow-hidden border-2 border-ink shadow-2xl" style="--perf:72%; --punch:#1B1714">
                    <div class="duotone h-32 bg-gradient-to-br <?= $ac['gradient'] ?> <?= $ac['text'] ?>">
                        <div class="grid-tex"></div>
                        <span class="stamp absolute top-4 left-4 text-paper/80 px-3 py-1 text-[10px] font-mono -rotate-6"><?= htmlspecialchars(strtoupper($catName)) ?></span>
                        <span class="absolute right-5 bottom-2 text-5xl opacity-30"><?= htmlspecialchars($catIcon) ?></span>
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

<!-- ============================== SUBCATEGORII (if any) ============================== -->
<?php if (!empty($children)): ?>
<section class="max-w-7xl mx-auto px-4 sm:px-6 pt-8">
    <p class="font-mono text-[10px] tracking-[.2em] text-ink-soft mb-3">SUBCATEGORII</p>
    <div class="flex flex-wrap gap-2">
        <?php foreach ($children as $child):
            $childName = navFlatName($child['name'] ?? '');
            $childSlug = $child['slug'] ?? '';
            if (!$childName || !$childSlug) continue;
        ?>
            <a href="/<?= htmlspecialchars($childSlug, ENT_QUOTES) ?>" class="px-4 py-2 rounded-full bg-paper-2 border border-ink/15 text-sm hover:border-ink hover:bg-ink hover:text-paper transition">
                <?= htmlspecialchars($childName) ?>
                <?php if (!empty($child['event_count'])): ?>
                    <span class="ml-1 text-ink-soft"><?= (int) $child['event_count'] ?></span>
                <?php endif; ?>
            </a>
        <?php endforeach; ?>
    </div>
</section>
<?php endif; ?>

<!-- ============================== FILTRE + REZULTATE ============================== -->
<section class="max-w-7xl mx-auto px-4 sm:px-6 py-10">

    <!-- city filter rail (chip strip) -->
    <?php if (!empty($featuredCities)): ?>
    <div class="flex gap-2 overflow-x-auto no-bar pb-3 mb-6 -mx-4 sm:-mx-6 px-4 sm:px-6">
        <a href="/<?= htmlspecialchars($slug, ENT_QUOTES) ?>" class="shrink-0 px-4 py-2 rounded-full border-2 text-sm font-600 transition <?= $cityFilter ? 'bg-transparent border-ink/20 hover:border-ink' : 'bg-ink text-paper border-ink' ?>">
            Toate orașele
        </a>
        <?php foreach ($featuredCities as $c): ?>
            <a href="/<?= htmlspecialchars($slug, ENT_QUOTES) ?>?city=<?= htmlspecialchars($c['slug'], ENT_QUOTES) ?>" class="shrink-0 px-4 py-2 rounded-full border-2 text-sm font-600 transition <?= $cityFilter === $c['slug'] ? 'bg-ink text-paper border-ink' : 'bg-transparent border-ink/20 hover:border-ink' ?>">
                <?= htmlspecialchars($c['label']) ?>
            </a>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <!-- header line -->
    <div class="flex items-baseline justify-between mb-6">
        <p class="font-display text-2xl font-700">
            <?= (int) ($pagination['total'] ?? 0) ?>
            <span class="text-ink-soft font-sans text-lg font-500">
                <?= ($pagination['total'] ?? 0) === 1 ? 'activitate' : 'activități' ?>
            </span>
            <?php if ($cityFilter): ?>
                <span class="text-ink-soft font-sans text-base font-500"> · filtrate după oraș</span>
            <?php endif; ?>
        </p>
        <p class="hidden sm:block font-mono text-xs text-ink-soft">DISPONIBILE TOT ANUL · INTRARE CU QR</p>
    </div>

    <?php if (empty($events)): ?>
        <!-- empty state -->
        <div class="ticket bg-paper border-2 border-ink rounded-3xl p-10 sm:p-16 text-center" style="--perf:100%">
            <div class="inline-grid place-items-center w-16 h-16 rounded-full bg-paper-2 mb-4">
                <svg viewBox="0 0 24 24" class="w-7 h-7 text-ink-soft" fill="none" stroke="currentColor" stroke-width="1.6"><circle cx="11" cy="11" r="7"/><path d="m20 20-3.5-3.5"/></svg>
            </div>
            <p class="font-display text-2xl font-700">Încă nu sunt activități listate aici.</p>
            <p class="text-ink-soft mt-2">Categoria e activă — operatorii își vor adăuga curând experiențele.</p>
            <a href="/categorii" class="inline-block mt-5 px-5 py-2.5 rounded-full bg-ink text-paper font-600">Vezi toate categoriile</a>
        </div>
    <?php else: ?>
        <!-- grid of events -->
        <div class="grid sm:grid-cols-2 lg:grid-cols-3 gap-5">
            <?php foreach ($events as $ev):
                $evTitle = _catEvTitle($ev);
                $evCity = _catEvCity($ev);
                $evSlug = $ev['slug'] ?? '';
                $evCover = $ev['cover_image_url'] ?? $ev['image_url'] ?? '';
                $evPrice = _catFormatPrice($ev['cheapest_price_cents'] ?? null);
            ?>
                <a href="/bilete/<?= htmlspecialchars($evSlug, ENT_QUOTES) ?>" class="ticket ticket-lift group bg-paper border-2 border-ink rounded-2xl overflow-hidden flex flex-col" style="--perf:100%">
                    <?php if ($evCover): ?>
                        <img src="<?= htmlspecialchars($evCover, ENT_QUOTES) ?>" alt="<?= htmlspecialchars($evTitle, ENT_QUOTES) ?>" class="h-40 w-full object-cover" loading="lazy" width="600" height="320">
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
                        <h2 class="font-display text-xl font-700 leading-tight mt-1 group-hover:text-vermilion transition-colors"><?= htmlspecialchars($evTitle) ?></h2>

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
            <nav class="mt-10 flex justify-center gap-2" aria-label="Pagini">
                <?php
                $base = '/' . $slug . ($cityFilter ? '?city=' . urlencode($cityFilter) . '&' : '?');
                $current = (int) ($pagination['current_page'] ?? 1);
                $last = (int) ($pagination['last_page'] ?? 1);
                $start = max(1, $current - 3);
                $end = min($last, $start + 6);
                $start = max(1, $end - 6);
                ?>
                <?php if ($current > 1): ?>
                    <a href="<?= htmlspecialchars($base) ?>page=<?= $current - 1 ?>" class="px-4 py-2 rounded-full border-2 border-ink/20 hover:border-ink font-600 text-sm">‹ Anterior</a>
                <?php endif; ?>
                <?php for ($p = $start; $p <= $end; $p++): ?>
                    <a href="<?= htmlspecialchars($base) ?>page=<?= $p ?>" class="px-4 py-2 rounded-full border-2 font-600 text-sm <?= $p === $current ? 'bg-ink text-paper border-ink' : 'border-ink/20 hover:border-ink' ?>"><?= $p ?></a>
                <?php endfor; ?>
                <?php if ($current < $last): ?>
                    <a href="<?= htmlspecialchars($base) ?>page=<?= $current + 1 ?>" class="px-4 py-2 rounded-full border-2 border-ink/20 hover:border-ink font-600 text-sm">Următor ›</a>
                <?php endif; ?>
            </nav>
        <?php endif; ?>
    <?php endif; ?>
</section>

<!-- ============================== RELATED CATEGORIES + SEO COPY ============================== -->
<section class="bg-paper-2 border-y border-ink/10">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 py-16 lg:py-20 grid lg:grid-cols-[1fr_.8fr] gap-14">
        <div>
            <?php if ($catDescription): ?>
                <h2 class="font-display text-[clamp(1.8rem,4vw,2.8rem)] font-700 leading-[1] mb-6">
                    Tot ce trebuie să știi despre <span class="ital <?= $ac['text'] ?>"><?= htmlspecialchars(mb_strtolower($catName)) ?></span>
                </h2>
                <div class="space-y-4 text-ink-soft leading-relaxed text-[17px] max-w-2xl">
                    <p><?= nl2br(htmlspecialchars($catDescription)) ?></p>
                </div>
            <?php endif; ?>

            <!-- City crosslinks for SEO -->
            <?php if (!empty($featuredCities)): ?>
            <div class="mt-8">
                <p class="font-mono text-[10px] tracking-[.2em] text-ink-soft mb-3"><?= htmlspecialchars(strtoupper($catName)) ?> PE ORAȘE</p>
                <div class="flex flex-wrap gap-2">
                    <?php foreach (array_slice($featuredCities, 0, 8) as $c): ?>
                        <a href="/<?= htmlspecialchars($slug, ENT_QUOTES) ?>?city=<?= htmlspecialchars($c['slug'], ENT_QUOTES) ?>" class="px-3.5 py-1.5 rounded-full bg-paper border border-ink/10 text-sm hover:bg-ink hover:text-paper transition">
                            <?= htmlspecialchars($catName) ?> în <?= htmlspecialchars($c['label']) ?>
                        </a>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>
        </div>

        <!-- Related sibling categories -->
        <div>
            <p class="font-mono text-[10px] tracking-[.2em] text-ink-soft mb-3">CATEGORII ÎNRUDITE</p>
            <h2 class="font-display text-2xl font-700 mb-6">Vezi și</h2>
            <div class="flex flex-wrap gap-2">
                <?php
                $siblings = array_filter($navCategories, fn ($c) => $c['slug'] !== $slug);
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
</section>

<?php include __DIR__ . '/includes/footer.php'; ?>
