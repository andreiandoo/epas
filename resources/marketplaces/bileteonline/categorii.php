<?php
/**
 * /categorii — full catalog of leisure activity categories.
 * Renders parent categories as visual cards + their children chips below
 * for SEO crawlability + user discovery.
 */

$pageCacheTTL = 600; // 10 min — categories change rarely
require_once __DIR__ . '/includes/page-cache.php';
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/api.php';
require_once __DIR__ . '/includes/nav-helpers.php';

// Fetch ALL parent categories with their children nested.
// CategoriesController::index() returns the tree shape with children[].
$categoriesResp = api_cached('categories_full_tree', function () {
    return api_get('/event-categories');
}, 600);

$rawCategories = $categoriesResp['data']['categories'] ?? [];
if (!is_array($rawCategories)) $rawCategories = [];

// Filter to parents only + transform for display
$parents = [];
foreach ($rawCategories as $cat) {
    if (!empty($cat['parent_id'])) continue; // children are nested under parents in this response
    $parents[] = $cat;
}

// Sort by sort_order
usort($parents, fn ($a, $b) => ($a['sort_order'] ?? 0) <=> ($b['sort_order'] ?? 0));

// Accent map → Tailwind classes (safelisted)
$accentMap = [
    'vermilion' => ['bg' => 'bg-vermilion', 'text' => 'text-vermilion'],
    'forest'    => ['bg' => 'bg-forest',    'text' => 'text-forest'],
    'ochre'     => ['bg' => 'bg-ochre',     'text' => 'text-ochre'],
    'sky'       => ['bg' => 'bg-sky',       'text' => 'text-sky'],
];

// ============================================================
// SEO
// ============================================================
$pageTitleRaw = 'Toate categoriile de activități — bilete.online';
$pageDescription = 'Explorează toate categoriile de activități disponibile pe bilete.online: escape rooms, muzee, parcuri, natură, familie, indoor și outdoor.';
$canonicalUrl = SITE_URL . '/categorii';
$currentPage = 'categorii';
$cssBundle = 'listing';

$breadcrumbs = [
    ['name' => 'Acasă', 'url' => SITE_URL . '/'],
    ['name' => 'Categorii', 'url' => $canonicalUrl],
];

// JSON-LD ItemList of categories
$itemListElements = [];
foreach ($parents as $i => $cat) {
    $catName = navFlatName($cat['name'] ?? '');
    $catSlug = $cat['slug'] ?? '';
    if (!$catName || !$catSlug) continue;
    $itemListElements[] = [
        '@type' => 'ListItem',
        'position' => $i + 1,
        'name' => $catName,
        'url' => SITE_URL . '/' . $catSlug,
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
        'mainEntity' => [
            '@type' => 'ItemList',
            'numberOfItems' => count($itemListElements),
            'itemListElement' => $itemListElements,
        ],
    ];
}

include __DIR__ . '/includes/head.php';
include __DIR__ . '/includes/header.php';
?>

<!-- HERO -->
<section class="max-w-7xl mx-auto px-4 sm:px-6 py-14 sm:py-20">
    <nav aria-label="breadcrumb" class="flex flex-wrap items-center gap-2 text-sm text-ink-soft font-mono mb-6">
        <a href="/" class="hover:text-vermilion transition">Acasă</a>
        <span class="opacity-40">/</span>
        <span class="text-ink font-600">Categorii</span>
    </nav>

    <p class="stamp inline-flex px-3 py-1 text-xs font-mono tracking-[.18em] text-vermilion">CATEGORII</p>
    <h1 class="mt-5 font-display text-6xl sm:text-8xl font-700 leading-[.85]">Toate categoriile</h1>
    <p class="mt-6 text-xl text-ink-soft max-w-3xl">
        Găsește rapid tipul potrivit de experiență: indoor, outdoor, culturală, familie, aventură sau activități de weekend.
    </p>

    <?php if (empty($parents)): ?>
        <!-- empty state: API failed or no categories seeded -->
        <div class="mt-10 ticket bg-paper border-2 border-ink rounded-3xl p-10 text-center" style="--perf:100%">
            <p class="font-display text-2xl font-700">Categoriile încă nu sunt configurate.</p>
            <p class="text-ink-soft mt-2">Reveniți în curând — sau scrie-ne la <a href="mailto:<?= htmlspecialchars(SUPPORT_EMAIL ?? '', ENT_QUOTES) ?>" class="text-vermilion underline-wobble"><?= htmlspecialchars(SUPPORT_EMAIL ?? '') ?></a>.</p>
        </div>
    <?php else: ?>

    <!-- CARDS GRID -->
    <div class="mt-10 grid md:grid-cols-2 lg:grid-cols-3 gap-5">
        <?php foreach ($parents as $cat):
            $catName = navFlatName($cat['name'] ?? '');
            $catSlug = $cat['slug'] ?? '';
            if (!$catName || !$catSlug) continue;
            $catDescription = navFlatName($cat['description'] ?? '');
            $catEmoji = $cat['icon_emoji'] ?? '🎫';
            $accent = navAccentFromHex($cat['color'] ?? null);
            $ac = $accentMap[$accent] ?? $accentMap['vermilion'];
            $eventCount = (int) ($cat['event_count'] ?? 0);
            $children = $cat['children'] ?? [];
        ?>
            <article class="ticket ticket-lift group bg-paper border-2 border-ink rounded-3xl overflow-hidden flex flex-col" style="--perf:100%">
                <a href="/<?= htmlspecialchars($catSlug, ENT_QUOTES) ?>" class="block">
                    <div class="duotone h-32 p-5 flex items-end <?= $ac['bg'] ?> <?= $ac['text'] ?>">
                        <div class="grid-tex"></div>
                        <div class="relative flex items-end justify-between w-full">
                            <span class="text-5xl leading-none" aria-hidden="true"><?= htmlspecialchars($catEmoji) ?></span>
                            <span class="font-mono text-[10px] text-paper/90 bg-ink/25 px-2 py-1 rounded">
                                <?= $eventCount > 0 ? $eventCount . ' activități' : 'în curând' ?>
                            </span>
                        </div>
                    </div>
                    <div class="p-5">
                        <h2 class="font-display text-2xl font-700 leading-tight group-hover:text-vermilion transition-colors">
                            <?= htmlspecialchars($catName) ?>
                        </h2>
                        <?php if ($catDescription): ?>
                            <p class="mt-2 text-ink-soft text-sm leading-relaxed line-clamp-2"><?= htmlspecialchars($catDescription) ?></p>
                        <?php endif; ?>
                    </div>
                </a>

                <?php if (!empty($children)): ?>
                    <div class="px-5 pb-5 mt-auto">
                        <p class="font-mono text-[10px] tracking-[.18em] text-ink-soft mb-2.5">SUBCATEGORII</p>
                        <div class="flex flex-wrap gap-1.5">
                            <?php
                            // Show max 4 child chips per card; rest go behind "+N more"
                            $childrenList = array_slice($children, 0, 4);
                            $remaining = max(0, count($children) - 4);
                            foreach ($childrenList as $child):
                                $childName = navFlatName($child['name'] ?? '');
                                $childSlug = $child['slug'] ?? '';
                                if (!$childName || !$childSlug) continue;
                            ?>
                                <a href="/<?= htmlspecialchars($childSlug, ENT_QUOTES) ?>" class="px-2.5 py-1 rounded-full bg-paper-2 border border-ink/10 text-xs hover:bg-ink hover:text-paper transition">
                                    <?= htmlspecialchars($childName) ?>
                                </a>
                            <?php endforeach; ?>
                            <?php if ($remaining > 0): ?>
                                <a href="/<?= htmlspecialchars($catSlug, ENT_QUOTES) ?>" class="px-2.5 py-1 rounded-full bg-ink text-paper text-xs font-600">+<?= $remaining ?></a>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endif; ?>
            </article>
        <?php endforeach; ?>
    </div>

    <?php endif; ?>
</section>

<!-- BOTTOM CROSS-LINK -->
<section class="bg-paper-2 border-y border-ink/10">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 py-14 grid lg:grid-cols-2 gap-10">
        <div>
            <p class="font-mono text-xs tracking-[.2em] text-vermilion mb-3">DUPĂ INTENȚIE</p>
            <h2 class="font-display text-2xl sm:text-3xl font-700 leading-tight mb-5">Sau caută după ce vrei să faci</h2>
            <div class="flex flex-wrap gap-2">
                <a href="/activitati-azi" class="px-4 py-2 rounded-full bg-paper border border-ink/15 hover:border-ink hover:bg-ink hover:text-paper transition text-sm font-600">⏰ Azi</a>
                <a href="/activitati-weekend" class="px-4 py-2 rounded-full bg-paper border border-ink/15 hover:border-ink hover:bg-ink hover:text-paper transition text-sm font-600">🎉 Weekend</a>
                <a href="/activitati-gratuite" class="px-4 py-2 rounded-full bg-paper border border-ink/15 hover:border-ink hover:bg-ink hover:text-paper transition text-sm font-600">🎁 Gratuite</a>
                <a href="/activitati-copii" class="px-4 py-2 rounded-full bg-paper border border-ink/15 hover:border-ink hover:bg-ink hover:text-paper transition text-sm font-600">🧒 Copii</a>
                <a href="/activitati-indoor" class="px-4 py-2 rounded-full bg-paper border border-ink/15 hover:border-ink hover:bg-ink hover:text-paper transition text-sm font-600">🏛️ Indoor</a>
                <a href="/activitati-romantice" class="px-4 py-2 rounded-full bg-paper border border-ink/15 hover:border-ink hover:bg-ink hover:text-paper transition text-sm font-600">💞 Romantice</a>
            </div>
        </div>

        <div>
            <p class="font-mono text-xs tracking-[.2em] text-vermilion mb-3">DUPĂ ORAȘ</p>
            <h2 class="font-display text-2xl sm:text-3xl font-700 leading-tight mb-5">Activități în orașele mari</h2>
            <div class="flex flex-wrap gap-2">
                <?php foreach (navGetCities(8) as $c): ?>
                    <a href="/<?= htmlspecialchars($c['slug'], ENT_QUOTES) ?>" class="px-4 py-2 rounded-full bg-paper border border-ink/15 hover:border-ink hover:bg-ink hover:text-paper transition text-sm font-600">
                        <?= htmlspecialchars($c['label']) ?>
                    </a>
                <?php endforeach; ?>
                <a href="/orase" class="px-4 py-2 rounded-full bg-ink text-paper text-sm font-600">Toate orașele →</a>
            </div>
        </div>
    </div>
</section>

<?php include __DIR__ . '/includes/footer.php'; ?>
