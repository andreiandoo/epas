<?php
/**
 * City × Intent SEO landing page.
 * Handles BOTH:
 *   /{city}/{intent}     — city-scoped (e.g. /brasov/activitati-indoor)
 *   /{intent}            — global (e.g. /activitati-azi)
 *
 * One PHP file → 100 cities × 25 intents × pagination = thousands of SEO pages.
 * Data and SEO meta come from the marketplace API; this file is pure render +
 * cross-link composition.
 */

$pageCacheTTL = 300;
require_once __DIR__ . '/includes/page-cache.php';
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/api.php';

$citySlug = $_GET['city'] ?? null;
$intentSlug = $_GET['intent'] ?? null;
$pageNum = max(1, (int) ($_GET['page'] ?? 1));

if (!$intentSlug || !preg_match('/^[a-z0-9-]+$/', $intentSlug)) {
    http_response_code(404);
    require_once __DIR__ . '/404.php';
    exit;
}

if ($citySlug && !preg_match('/^[a-z0-9-]+$/', $citySlug)) {
    http_response_code(404);
    require_once __DIR__ . '/404.php';
    exit;
}

// Fetch + cache
$cacheKey = 'intent_' . $intentSlug . '_' . ($citySlug ?? 'global') . '_p' . $pageNum;
$apiData = api_cached($cacheKey, function () use ($intentSlug, $citySlug, $pageNum) {
    $endpoint = $citySlug
        ? '/intents/' . urlencode($intentSlug) . '/cities/' . urlencode($citySlug) . '/events'
        : '/intents/' . urlencode($intentSlug) . '/events';
    return api_get($endpoint, ['page' => $pageNum, 'per_page' => 24]);
}, 300);

// Hard 404 only if both intent + city were rejected by the API
// (the API returns success=false with error key)
if (!is_array($apiData) || empty($apiData['success']) || !isset($apiData['data'])) {
    http_response_code(404);
    require_once __DIR__ . '/404.php';
    exit;
}

$data = $apiData['data'];
$intent = $data['intent'] ?? null;
$city = $data['city'] ?? null;
$meta = $data['meta'] ?? [];
$events = $data['events'] ?? [];
$pagination = $data['pagination'] ?? ['current_page' => 1, 'last_page' => 1, 'total' => 0];
$crossLinks = $data['cross_links'] ?? ['other_intents_for_city' => [], 'same_intent_for_cities' => []];

// Accent color → Tailwind class mapping (safelisted in tailwind.config.cjs)
$accent = $meta['accent_color'] ?? 'vermilion';
$accentMap = [
    'vermilion' => ['text' => 'text-vermilion', 'bg' => 'bg-vermilion', 'bg-light' => 'bg-vermilion/10', 'hover' => 'hover:bg-vermilion-d'],
    'forest'    => ['text' => 'text-forest',    'bg' => 'bg-forest',    'bg-light' => 'bg-forest/10',    'hover' => 'hover:bg-forest-l'],
    'ochre'     => ['text' => 'text-ochre',     'bg' => 'bg-ochre',     'bg-light' => 'bg-ochre/15',     'hover' => 'hover:bg-vermilion-d'],
    'sky'       => ['text' => 'text-sky',       'bg' => 'bg-sky',       'bg-light' => 'bg-sky/10',       'hover' => 'hover:bg-ink-2'],
];
$accentClasses = $accentMap[$accent] ?? $accentMap['vermilion'];

// SEO setup for head.php
$pageTitleRaw = $meta['title'] ?? ('Activități · ' . SITE_NAME);
$pageDescription = $meta['description'] ?? SITE_TAGLINE;
$canonicalUrl = SITE_URL . ($meta['canonical_path'] ?? '/');
$ogImage = !empty($meta['cover_image_url']) ? $meta['cover_image_url'] : SITE_URL . '/assets/images/og-default.jpg';
$noindex = !empty($meta['noindex']);
$currentPage = 'intent';
$cssBundle = 'listing';

// Breadcrumbs
$breadcrumbs = [['name' => 'Acasă', 'url' => SITE_URL . '/']];
if ($city) {
    $breadcrumbs[] = ['name' => $city['name'], 'url' => SITE_URL . '/' . $city['slug']];
}
$breadcrumbs[] = ['name' => $intent['name'] ?? 'Activități', 'url' => $canonicalUrl];

// Structured data: CollectionPage + ItemList of top 10 events
$itemListElements = [];
foreach (array_slice($events, 0, 10) as $i => $ev) {
    $evTitle = is_array($ev['title'] ?? null)
        ? ($ev['title']['ro'] ?? $ev['title']['en'] ?? reset($ev['title']))
        : ($ev['title'] ?? 'Activitate');
    $evSlug = $ev['slug'] ?? '';
    $itemListElements[] = [
        '@type' => 'ListItem',
        'position' => $i + 1,
        'name' => $evTitle,
        'url' => SITE_URL . '/bilete/' . $evSlug,
    ];
}
$structuredData = [];
if (!empty($itemListElements)) {
    $structuredData[] = [
        '@context' => 'https://schema.org',
        '@type' => 'CollectionPage',
        'name' => $pageTitleRaw,
        'description' => $pageDescription,
        'url' => $canonicalUrl,
        'inLanguage' => 'ro-RO',
        'isPartOf' => ['@type' => 'WebSite', 'name' => SITE_NAME, 'url' => SITE_URL],
        'mainEntity' => [
            '@type' => 'ItemList',
            'numberOfItems' => $pagination['total'],
            'itemListElement' => $itemListElements,
        ],
    ];
}

include __DIR__ . '/includes/head.php';
include __DIR__ . '/includes/header.php';

// Helper: format ticket price from cents (or display "Gratuit" / "—")
function _intentFormatPrice(?int $cents): string {
    if ($cents === null) return '—';
    if ($cents === 0) return 'Gratuit';
    return number_format($cents / 100, 0, ',', '.') . ' lei';
}

// Helper: extract translated title
function _intentTitle(array $ev, string $locale = 'ro'): string {
    if (is_array($ev['title'] ?? null)) {
        return $ev['title'][$locale] ?? $ev['title']['ro'] ?? $ev['title']['en'] ?? reset($ev['title']);
    }
    return $ev['title'] ?? 'Activitate';
}

// Helper: extract category label
function _intentCategoryLabel(array $ev, string $locale = 'ro'): string {
    $cat = $ev['marketplace_event_category'] ?? null;
    if (!$cat || !is_array($cat)) return '';
    if (is_array($cat['name'] ?? null)) {
        return $cat['name'][$locale] ?? $cat['name']['ro'] ?? '';
    }
    return $cat['name'] ?? '';
}

// Helper: extract city label
function _intentCityLabel(array $ev, string $locale = 'ro'): string {
    $c = $ev['marketplace_city'] ?? null;
    if ($c && is_array($c['name'] ?? null)) {
        return $c['name'][$locale] ?? $c['name']['ro'] ?? '';
    }
    return $ev['venue']['city'] ?? '';
}
?>

<!-- ============================== BREADCRUMB ============================== -->
<nav class="max-w-7xl mx-auto px-4 sm:px-6 pt-6 text-xs font-mono tracking-wider text-ink-soft" aria-label="Breadcrumb">
    <ol class="flex flex-wrap items-center gap-2">
        <?php foreach ($breadcrumbs as $i => $bc): ?>
            <li class="flex items-center gap-2">
                <?php if ($i > 0): ?>
                    <span class="text-ink-soft/40">›</span>
                <?php endif; ?>
                <?php if ($i < count($breadcrumbs) - 1): ?>
                    <a href="<?= htmlspecialchars($bc['url'], ENT_QUOTES) ?>" class="hover:text-vermilion transition"><?= htmlspecialchars($bc['name']) ?></a>
                <?php else: ?>
                    <span class="text-ink"><?= htmlspecialchars($bc['name']) ?></span>
                <?php endif; ?>
            </li>
        <?php endforeach; ?>
    </ol>
</nav>

<!-- ============================== HERO ============================== -->
<section class="max-w-7xl mx-auto px-4 sm:px-6 py-12 sm:py-16">
    <p class="stamp inline-flex px-3 py-1 text-xs font-mono tracking-[.18em] <?= $accentClasses['text'] ?> -rotate-3"><?= htmlspecialchars($city ? 'LOCAL' : 'CATALOG') ?></p>

    <h1 class="mt-5 font-display text-[clamp(2.6rem,7vw,5.4rem)] font-700 leading-[.92] tracking-tight">
        <?php if (!empty($meta['icon'])): ?>
            <span class="inline-block align-middle mr-2 text-5xl sm:text-6xl"><?= htmlspecialchars($meta['icon']) ?></span>
        <?php endif; ?>
        <?= htmlspecialchars($meta['h1'] ?? $meta['title'] ?? '') ?>
    </h1>

    <?php if (!empty($meta['intro_copy'])): ?>
        <p class="mt-6 text-lg sm:text-xl text-ink-soft max-w-3xl leading-relaxed"><?= htmlspecialchars($meta['intro_copy']) ?></p>
    <?php endif; ?>

    <div class="mt-8 flex flex-wrap items-center gap-4">
        <a href="#activitati" class="px-6 py-3.5 rounded-full <?= $accentClasses['bg'] ?> text-paper font-600 <?= $accentClasses['hover'] ?> transition">
            Vezi <?= (int) $pagination['total'] ?> <?= $pagination['total'] === 1 ? 'activitate' : 'activități' ?>
        </a>
        <?php if ($city): ?>
            <a href="/<?= htmlspecialchars($city['slug'], ENT_QUOTES) ?>" class="text-ink-soft hover:text-ink underline-wobble font-600">Toate activitățile din <?= htmlspecialchars($city['name']) ?> →</a>
        <?php else: ?>
            <a href="/orase" class="text-ink-soft hover:text-ink underline-wobble font-600">Caută după oraș →</a>
        <?php endif; ?>
    </div>
</section>

<!-- ============================== ACTIVITĂȚI ============================== -->
<section id="activitati" class="max-w-7xl mx-auto px-4 sm:px-6 pb-16">

    <?php if (empty($events)): ?>
        <!-- EMPTY STATE -->
        <div class="ticket bg-paper border-2 border-ink rounded-3xl p-10 sm:p-16 text-center" style="--perf:100%">
            <p class="font-display text-3xl sm:text-4xl font-700">Nimic disponibil acum.</p>
            <p class="mt-3 text-ink-soft max-w-xl mx-auto">Pagina rămâne activă — verifică din nou peste câteva zile sau încearcă o altă intenție.</p>

            <?php if (!empty($crossLinks['other_intents_for_city'])): ?>
                <div class="mt-8 flex flex-wrap justify-center gap-2">
                    <?php foreach (array_slice($crossLinks['other_intents_for_city'], 0, 6) as $li): ?>
                        <a href="<?= htmlspecialchars($li['path'], ENT_QUOTES) ?>" class="px-4 py-2 rounded-full border-2 border-ink/20 hover:border-ink text-sm font-600">
                            <?php if (!empty($li['icon'])): ?><?= htmlspecialchars($li['icon']) ?> <?php endif; ?><?= htmlspecialchars($li['name']) ?>
                        </a>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    <?php else: ?>
        <!-- RESULTS HEADER -->
        <div class="flex flex-col sm:flex-row sm:items-end justify-between gap-3 mb-8">
            <p class="font-mono text-xs tracking-[.2em] text-ink-soft">
                <?= (int) $pagination['total'] ?> rezultate
                <?php if ($pagination['last_page'] > 1): ?>
                    · pagina <?= (int) $pagination['current_page'] ?> din <?= (int) $pagination['last_page'] ?>
                <?php endif; ?>
            </p>
            <a href="/cauta?intent=<?= urlencode($intent['slug']) ?><?= $city ? '&city=' . urlencode($city['slug']) : '' ?>" class="text-sm font-600 underline-wobble self-start sm:self-end">
                Filtre avansate →
            </a>
        </div>

        <!-- GRID -->
        <div class="grid sm:grid-cols-2 lg:grid-cols-3 gap-5">
            <?php foreach ($events as $ev):
                $evTitle = _intentTitle($ev, SITE_LOCALE);
                $evCat = _intentCategoryLabel($ev, SITE_LOCALE);
                $evCity = _intentCityLabel($ev, SITE_LOCALE);
                $evSlug = $ev['slug'] ?? '';
                $evCover = $ev['cover_image_url'] ?? $ev['image_url'] ?? '';
                $evPrice = _intentFormatPrice($ev['cheapest_price_cents'] ?? null);
            ?>
                <article class="ticket ticket-lift group bg-paper border-2 border-ink rounded-2xl overflow-hidden flex flex-col" style="--perf:100%">
                    <?php if ($evCover): ?>
                        <img src="<?= htmlspecialchars($evCover, ENT_QUOTES) ?>" alt="<?= htmlspecialchars($evTitle, ENT_QUOTES) ?>" class="h-40 w-full object-cover" loading="lazy" width="600" height="320">
                    <?php else: ?>
                        <div class="duotone <?= $accentClasses['bg'] ?> h-32 flex items-center justify-center text-paper/50 font-mono text-xs tracking-wider">
                            <div class="grid-tex"></div>
                            <span class="relative">FĂRĂ POZĂ</span>
                        </div>
                    <?php endif; ?>

                    <div class="p-4 flex-1 flex flex-col">
                        <p class="font-mono text-[10px] text-ink-soft tracking-wider">
                            <?php if ($evCat): ?><?= htmlspecialchars(strtoupper($evCat)) ?><?php endif; ?>
                            <?php if ($evCat && $evCity): ?> · <?php endif; ?>
                            <?php if ($evCity): ?><?= htmlspecialchars(strtoupper($evCity)) ?><?php endif; ?>
                        </p>
                        <h3 class="font-display text-xl font-700 leading-tight mt-1.5"><?= htmlspecialchars($evTitle) ?></h3>

                        <div class="mt-auto pt-4 flex items-center justify-between gap-3">
                            <p class="text-ink-soft">
                                <?php if (($ev['cheapest_price_cents'] ?? null) !== null && $ev['cheapest_price_cents'] > 0): ?>
                                    <span class="text-xs">de la </span>
                                <?php endif; ?>
                                <span class="font-display text-lg font-700 text-ink"><?= htmlspecialchars($evPrice) ?></span>
                            </p>
                            <a href="/bilete/<?= htmlspecialchars($evSlug, ENT_QUOTES) ?>" class="px-3 py-1.5 rounded-full bg-ink text-paper text-xs font-600 group-hover:<?= $accentClasses['bg'] ?> transition-colors">
                                Vezi bilete
                            </a>
                        </div>
                    </div>
                </article>
            <?php endforeach; ?>
        </div>

        <!-- PAGINATION -->
        <?php if ($pagination['last_page'] > 1): ?>
            <nav class="mt-10 flex justify-center gap-2" aria-label="Pagini">
                <?php
                $base = $meta['canonical_path'] ?? '/';
                $maxLinks = 7;
                $start = max(1, $pagination['current_page'] - 3);
                $end = min($pagination['last_page'], $start + $maxLinks - 1);
                $start = max(1, $end - $maxLinks + 1);
                ?>
                <?php if ($pagination['current_page'] > 1): ?>
                    <a href="<?= htmlspecialchars($base) ?>?page=<?= $pagination['current_page'] - 1 ?>" class="px-4 py-2 rounded-full border-2 border-ink/20 hover:border-ink font-600 text-sm">‹ Anterior</a>
                <?php endif; ?>
                <?php for ($p = $start; $p <= $end; $p++): ?>
                    <a href="<?= htmlspecialchars($base) ?>?page=<?= $p ?>" class="px-4 py-2 rounded-full border-2 font-600 text-sm <?= $p === $pagination['current_page'] ? 'bg-ink text-paper border-ink' : 'border-ink/20 hover:border-ink' ?>"><?= $p ?></a>
                <?php endfor; ?>
                <?php if ($pagination['current_page'] < $pagination['last_page']): ?>
                    <a href="<?= htmlspecialchars($base) ?>?page=<?= $pagination['current_page'] + 1 ?>" class="px-4 py-2 rounded-full border-2 border-ink/20 hover:border-ink font-600 text-sm">Următor ›</a>
                <?php endif; ?>
            </nav>
        <?php endif; ?>
    <?php endif; ?>
</section>

<!-- ============================== CROSS-LINKING ============================== -->
<?php if (!empty($crossLinks['other_intents_for_city']) || !empty($crossLinks['same_intent_for_cities'])): ?>
<section class="bg-paper-2 border-y border-ink/10">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 py-14 grid lg:grid-cols-2 gap-10">

        <?php if (!empty($crossLinks['other_intents_for_city'])): ?>
        <div>
            <p class="font-mono text-xs tracking-[.2em] text-vermilion mb-3">EXPLOREAZĂ ALTE INTENȚII</p>
            <h2 class="font-display text-2xl sm:text-3xl font-700 leading-tight mb-5">
                <?php if ($city): ?>
                    Și mai multe activități în <?= htmlspecialchars($city['name']) ?>
                <?php else: ?>
                    Alte tipuri de activități
                <?php endif; ?>
            </h2>
            <div class="flex flex-wrap gap-2">
                <?php foreach ($crossLinks['other_intents_for_city'] as $li): ?>
                    <a href="<?= htmlspecialchars($li['path'], ENT_QUOTES) ?>" class="inline-flex items-center gap-1.5 px-4 py-2 rounded-full bg-paper border border-ink/15 hover:border-ink hover:bg-ink hover:text-paper transition text-sm font-600">
                        <?php if (!empty($li['icon'])): ?><span><?= htmlspecialchars($li['icon']) ?></span><?php endif; ?>
                        <?= htmlspecialchars($li['name']) ?>
                    </a>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>

        <?php if (!empty($crossLinks['same_intent_for_cities'])): ?>
        <div>
            <p class="font-mono text-xs tracking-[.2em] text-vermilion mb-3">ÎN ALTE ORAȘE</p>
            <h2 class="font-display text-2xl sm:text-3xl font-700 leading-tight mb-5">
                <?= htmlspecialchars($intent['name']) ?> în alte orașe
            </h2>
            <div class="flex flex-wrap gap-2">
                <?php foreach ($crossLinks['same_intent_for_cities'] as $li): ?>
                    <a href="<?= htmlspecialchars($li['path'], ENT_QUOTES) ?>" class="px-4 py-2 rounded-full bg-paper border border-ink/15 hover:border-ink hover:bg-ink hover:text-paper transition text-sm font-600">
                        <?= htmlspecialchars($li['name']) ?>
                    </a>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>
    </div>
</section>
<?php endif; ?>

<!-- ============================== SEO COPY ============================== -->
<?php if (!empty($meta['seo_copy'])): ?>
<section class="max-w-4xl mx-auto px-4 sm:px-6 py-16">
    <article class="ticket bg-paper border-2 border-ink rounded-3xl p-6 sm:p-10 prose-custom" style="--perf:100%">
        <h2 class="font-display text-2xl sm:text-3xl font-700 mb-4"><?= htmlspecialchars($meta['h1'] ?? $intent['name']) ?></h2>
        <div class="text-ink-soft text-[17px] leading-relaxed space-y-4">
            <?php
            // Render seo_copy as paragraphs (split on \n\n) with simple link auto-detection — admin writes plain text/markdown-lite
            $paragraphs = preg_split('/\n\s*\n/', trim($meta['seo_copy']));
            foreach ($paragraphs as $p):
                echo '<p>' . nl2br(htmlspecialchars($p)) . '</p>';
            endforeach;
            ?>
        </div>
    </article>
</section>
<?php endif; ?>

<?php include __DIR__ . '/includes/footer.php'; ?>
