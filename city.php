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
$cityName = navFlatName($cityData['name'] ?? '');
$cityDescription = navFlatName($cityData['description'] ?? '');
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

// Pagination + filter
$pageNum = max(1, (int) ($_GET['page'] ?? 1));
$categoryFilter = isset($_GET['category']) && preg_match('/^[a-z][a-z0-9-]+$/', $_GET['category']) ? $_GET['category'] : null;

// Fetch events for this city
$eventsResp = api_cached("city_events_{$slug}_" . ($categoryFilter ?? 'all') . "_p{$pageNum}", function () use ($slug, $categoryFilter, $pageNum) {
    $params = ['city' => $slug, 'page' => $pageNum, 'per_page' => 18, 'time_scope' => 'upcoming'];
    if ($categoryFilter) $params['category'] = $categoryFilter;
    return api_get('/events', $params);
}, 300);

$events = $eventsResp['data'] ?? [];
$pagination = $eventsResp['meta'] ?? ['current_page' => 1, 'last_page' => 1, 'total' => count($events)];
if (!is_array($events)) $events = [];

// Fetch parent categories for quick-link grid (max 8)
$topCategories = navGetCategories(8);

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
<section id="activitati" class="bg-paper-2/70 border-y border-ink/10">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 py-16 lg:py-20">

        <div class="flex flex-col sm:flex-row sm:items-end justify-between gap-4 mb-8">
            <div>
                <p class="font-mono text-xs tracking-[.2em] text-vermilion mb-2">LISTĂ LOCALĂ</p>
                <h2 class="font-display text-[clamp(2rem,4vw,3.5rem)] font-700 leading-[0.95]">
                    Cele mai bune lucruri de făcut în <?= htmlspecialchars($cityName) ?>
                </h2>
                <p class="mt-3 text-ink-soft max-w-2xl">
                    <?= (int) ($pagination['total'] ?? 0) ?> <?= ($pagination['total'] ?? 0) === 1 ? 'activitate disponibilă' : 'activități disponibile' ?>
                    <?php if ($categoryFilter): ?>
                        · filtrate după categorie
                        <a href="/<?= htmlspecialchars($slug, ENT_QUOTES) ?>" class="text-vermilion underline-wobble">șterge filtrul</a>
                    <?php endif; ?>
                </p>
            </div>
        </div>

        <?php if (empty($events)): ?>
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
                <?php foreach ($events as $ev):
                    $evTitle = navEventTitle($ev);
                    $evCat = navEventCategoryLabel($ev);
                    $evSlug = $ev['slug'] ?? '';
                    $evCover = $ev['cover_image_url'] ?? $ev['image_url'] ?? '';
                    $evPrice = navFormatPriceCents($ev['cheapest_price_cents'] ?? null);
                ?>
                    <article class="ticket ticket-lift group bg-paper border-2 border-ink rounded-2xl overflow-hidden flex flex-col" style="--perf:100%">
                        <a href="/bilete/<?= htmlspecialchars($evSlug, ENT_QUOTES) ?>" class="block">
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
                            <a href="/bilete/<?= htmlspecialchars($evSlug, ENT_QUOTES) ?>" class="group">
                                <h3 class="font-display text-2xl font-700 leading-tight mt-1.5 group-hover:text-vermilion transition-colors"><?= htmlspecialchars($evTitle) ?></h3>
                            </a>

                            <div class="mt-auto pt-5 flex items-end justify-between gap-3">
                                <div>
                                    <?php if (($ev['cheapest_price_cents'] ?? null) !== null && $ev['cheapest_price_cents'] > 0): ?>
                                        <p class="text-xs text-ink-soft">de la</p>
                                    <?php endif; ?>
                                    <p class="font-display text-2xl font-700"><?= htmlspecialchars($evPrice) ?></p>
                                </div>
                                <a href="/bilete/<?= htmlspecialchars($evSlug, ENT_QUOTES) ?>" class="px-4 py-2.5 rounded-full bg-ink text-paper text-sm font-700 hover:bg-vermilion transition-colors">Vezi bilete</a>
                            </div>
                        </div>
                    </article>
                <?php endforeach; ?>
            </div>

            <!-- pagination -->
            <?php if (($pagination['last_page'] ?? 1) > 1): ?>
                <nav class="mt-10 flex justify-center gap-2" aria-label="Pagini">
                    <?php
                    $base = '/' . $slug . ($categoryFilter ? '?category=' . urlencode($categoryFilter) . '&' : '?');
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
    </div>
</section>

<!-- ============================== EDITORIAL + CROSS-LINKS ============================== -->
<section id="ghid-local" class="max-w-7xl mx-auto px-4 sm:px-6 py-20 lg:py-28">
    <div class="grid lg:grid-cols-[1fr_.75fr] gap-14 items-start">

        <article class="max-w-3xl">
            <p class="font-mono text-xs tracking-[.2em] text-vermilion mb-3">GHID LOCAL</p>
            <h2 class="font-display text-[clamp(2.1rem,4vw,4rem)] font-700 leading-[0.92] mb-7">
                Ce să faci în <?= htmlspecialchars($cityName) ?>: idei pentru weekend, familie sau o zi liberă.
            </h2>

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
