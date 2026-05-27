<?php
/**
 * bilete.online — /ghiduri  (guides index, v2 design)
 *
 * Editorial guides listing. Content is authored in the Tixello admin
 * (/marketplace/blog-articles) and served via the marketplace blog API
 * (/blog-articles). Cards link to /ghiduri/{slug}.
 *
 * Topic filter uses the blog categories. Search is diacritics-insensitive
 * (client-side Alpine).
 */

$pageCacheTTL = 300;
require_once __DIR__ . '/includes/page-cache.php';
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/api.php';
require_once __DIR__ . '/includes/nav-helpers.php';

// =========================================================================
// DATA — guides + categories
// =========================================================================
$articlesResp = api_cached('guides_index', fn () => api_get('/blog-articles', ['per_page' => 48, 'status' => 'published']), 300);
$rawArticles  = $articlesResp['data'] ?? [];
if (! is_array($rawArticles)) $rawArticles = [];

$catsResp = api_cached('guides_categories', fn () => api_get('/blog-categories'), 600);
$rawCats  = $catsResp['data'] ?? [];
if (! is_array($rawCats)) $rawCats = [];

// Normalize guides for Alpine
$guides = [];
foreach ($rawArticles as $a) {
    $title = $a['title'] ?? '';
    $slug  = $a['slug'] ?? '';
    if (! $title || ! $slug) continue;
    $publishedAt = $a['published_at'] ?? $a['created_at'] ?? null;
    $dateLabel = '';
    if ($publishedAt) {
        $ts = strtotime($publishedAt);
        if ($ts) {
            $months = [1=>'Ian',2=>'Feb',3=>'Mar',4=>'Apr',5=>'Mai',6=>'Iun',7=>'Iul',8=>'Aug',9=>'Sep',10=>'Oct',11=>'Noi',12=>'Dec'];
            $dateLabel = $months[(int) date('n', $ts)] . ' ' . date('Y', $ts);
        }
    }
    $guides[] = [
        'title'      => $title,
        'slug'       => $slug,
        'excerpt'    => $a['excerpt'] ?? '',
        'image'      => $a['image_url'] ?? null,
        'topic'      => $a['category']['slug'] ?? 'all',
        'topicLabel' => $a['category']['name'] ?? 'Ghid',
        'readTime'   => isset($a['read_time']) ? ((int) $a['read_time']) . ' min' : '5 min',
        'date'       => $dateLabel,
        'featured'   => (bool) ($a['is_featured'] ?? false),
    ];
}

// Topic filters from categories (with counts derived from guides)
$topicCounts = [];
foreach ($guides as $g) {
    $topicCounts[$g['topic']] = ($topicCounts[$g['topic']] ?? 0) + 1;
}
$topics = [['key' => 'all', 'label' => 'Toate ghidurile', 'count' => count($guides)]];
foreach ($rawCats as $c) {
    $slug = $c['slug'] ?? '';
    $name = $c['name'] ?? '';
    if (! $slug || ! $name) continue;
    if (! isset($topicCounts[$slug])) continue; // only categories that have guides
    $topics[] = ['key' => $slug, 'label' => $name, 'count' => $topicCounts[$slug]];
}
$quickChips = array_slice(array_filter($topics, fn ($t) => $t['key'] !== 'all'), 0, 5);
$quickChips = array_values($quickChips);

// Featured guide for hero (first featured, else first)
$featuredGuide = null;
foreach ($guides as $g) { if ($g['featured']) { $featuredGuide = $g; break; } }
if (! $featuredGuide && ! empty($guides)) $featuredGuide = $guides[0];

// Editorial SEO hubs (static — link to programmatic pages)
$editorialHubs = [
    ['kicker' => 'LOCAL',  'title' => 'Ghiduri pe orașe',     'description' => 'Ce să faci în București, Brașov, Cluj, Iași sau Timișoara.', 'url' => '/orase'],
    ['kicker' => 'INTENT', 'title' => 'Ghiduri de weekend',   'description' => 'Idei pentru weekend: copii, grupuri, cupluri.',             'url' => '/activitati-weekend'],
    ['kicker' => 'VREME',  'title' => 'Zile ploioase',        'description' => 'Activități indoor când vremea nu ține cu tine.',            'url' => '/activitati-zile-ploioase'],
    ['kicker' => 'CADOU',  'title' => 'Cadouri experiență',   'description' => 'Idei de cadou: carduri cadou și activități memorabile.',    'url' => '/card-cadou'],
];

// SEO
$pageTitleRaw    = 'Ghiduri de activități — ' . SITE_NAME;
$pageDescription = 'Ghiduri locale și tematice pentru activități: ce să faci în weekend, unde mergi cu copiii, ce alegi când plouă și cum cumperi bilete online fără haos.';
$canonicalUrl    = SITE_URL . '/ghiduri';
$currentPage     = 'ghiduri';
$cssBundle       = 'listing';

$breadcrumbs = [
    ['name' => 'Acasă',   'url' => SITE_URL . '/'],
    ['name' => 'Ghiduri', 'url' => $canonicalUrl],
];

$structuredData = [[
    '@context' => 'https://schema.org',
    '@type' => 'CollectionPage',
    'name' => $pageTitleRaw,
    'description' => $pageDescription,
    'url' => $canonicalUrl,
    'inLanguage' => 'ro-RO',
]];

include __DIR__ . '/includes/head.php';
include __DIR__ . '/includes/header.php';
?>

<main x-data="guidesPage(<?= htmlspecialchars(json_encode([
    'guides'     => $guides,
    'topics'     => $topics,
    'quickChips' => $quickChips,
]), ENT_QUOTES) ?>)">

<!-- HERO -->
<section class="relative overflow-hidden border-b-2 border-ink">
    <div class="absolute inset-0 bg-[radial-gradient(circle_at_84%_14%,rgba(232,69,39,.24),transparent_30%),radial-gradient(circle_at_14%_76%,rgba(30,74,61,.22),transparent_34%),radial-gradient(circle_at_50%_44%,rgba(218,154,51,.18),transparent_30%)]"></div>
    <div class="relative max-w-7xl mx-auto px-4 sm:px-6 pt-14 sm:pt-20 pb-16 sm:pb-24">
        <nav class="flex items-center gap-2 text-sm text-ink-soft" aria-label="Breadcrumb">
            <a href="/" class="hover:text-vermilion">Acasă</a><span>/</span><span class="text-ink">Ghiduri</span>
        </nav>
        <div class="mt-8 grid lg:grid-cols-[1fr_.9fr] gap-12 items-center">
            <div>
                <p class="stamp inline-flex px-3 py-1 text-xs font-mono tracking-[.18em] text-vermilion bg-paper/70">GHIDURI · IDEI DE IEȘIT · SEO EDITORIAL</p>
                <h1 class="mt-6 font-display text-6xl sm:text-8xl font-bold leading-[.82]">Idei bune pentru când vrei să faci ceva.</h1>
                <p class="mt-6 max-w-2xl text-xl sm:text-2xl text-ink-soft leading-relaxed">
                    Ghiduri locale și tematice pentru activități: ce să faci în weekend, unde mergi cu copiii, ce alegi când plouă și ce experiențe merită în orașul tău.
                </p>
                <div class="mt-8 max-w-2xl">
                    <label class="sr-only" for="guide-search">Caută ghiduri</label>
                    <div class="relative">
                        <input id="guide-search" type="text" class="field text-lg pr-14" x-model="search" placeholder="Caută: weekend, copii, Brașov, muzeu, ploaie...">
                        <span class="absolute right-4 top-1/2 -translate-y-1/2 text-ink-soft">
                            <svg viewBox="0 0 24 24" class="w-6 h-6" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="7"/><path d="m20 20-3.5-3.5"/></svg>
                        </span>
                    </div>
                </div>
                <div class="mt-6 flex flex-wrap gap-2">
                    <template x-for="chip in quickChips" :key="chip.key">
                        <button @click="activeTopic=chip.key" class="rounded-full bg-paper/70 border border-ink/10 px-4 py-2 font-bold hover:bg-ink hover:text-paper transition" x-text="chip.label"></button>
                    </template>
                </div>
            </div>
            <?php if ($featuredGuide): ?>
                <div class="relative hidden lg:block">
                    <div class="absolute inset-x-8 top-10 bottom-8 rounded-[2.4rem] bg-ink rotate-[2deg] shadow-deep"></div>
                    <article class="relative ticket bg-paper border-2 border-ink rounded-[2rem] overflow-hidden shadow-deep rotate-[-2deg]" style="--perf:100%">
                        <?php if ($featuredGuide['image']): ?>
                            <img src="<?= htmlspecialchars($featuredGuide['image'], ENT_QUOTES) ?>" alt="<?= htmlspecialchars($featuredGuide['title'], ENT_QUOTES) ?>" class="h-64 w-full object-cover" loading="lazy" onerror="this.style.display='none'">
                        <?php endif; ?>
                        <div class="p-6 sm:p-8">
                            <p class="font-mono text-xs tracking-[.18em] text-vermilion">GHID RECOMANDAT</p>
                            <h2 class="mt-3 font-display text-4xl font-bold leading-none"><?= htmlspecialchars($featuredGuide['title']) ?></h2>
                            <?php if ($featuredGuide['excerpt']): ?>
                                <p class="mt-3 text-ink-soft line-clamp-2"><?= htmlspecialchars($featuredGuide['excerpt']) ?></p>
                            <?php endif; ?>
                            <a href="/ghiduri/<?= htmlspecialchars($featuredGuide['slug'], ENT_QUOTES) ?>" class="mt-5 inline-flex rounded-full bg-vermilion text-paper px-5 py-3 font-bold hover:bg-vermilion-d transition">Citește ghidul</a>
                        </div>
                    </article>
                </div>
            <?php endif; ?>
        </div>
    </div>
</section>

<!-- GUIDE LIST -->
<section class="border-y-2 border-ink bg-paper-2/65">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 py-16 sm:py-20">
        <div class="grid lg:grid-cols-[300px_1fr] gap-8 items-start">
            <aside class="lg:sticky lg:top-28">
                <div class="rounded-[2rem] border-2 border-ink bg-paper p-5 shadow-ticket">
                    <p class="font-mono text-xs tracking-[.18em] text-ink-soft">TOPICURI</p>
                    <div class="mt-4 space-y-2">
                        <template x-for="topic in topics" :key="topic.key">
                            <button @click="activeTopic=topic.key" :class="activeTopic===topic.key ? 'bg-ink text-paper' : 'bg-paper-2 text-ink hover:bg-ink/5'" class="w-full rounded-2xl px-4 py-3 text-left font-bold transition flex items-center justify-between gap-3">
                                <span x-text="topic.label"></span>
                                <span class="text-xs opacity-60" x-text="topic.count"></span>
                            </button>
                        </template>
                    </div>
                    <div class="mt-5 rounded-2xl bg-mint border border-forest/20 p-4">
                        <p class="font-bold text-forest">Idei de ieșit în oraș</p>
                        <p class="mt-1 text-sm text-ink-soft">Ghidurile leagă orașe, categorii și activități reale cu bilete online.</p>
                    </div>
                </div>
            </aside>

            <section>
                <div class="flex flex-col sm:flex-row sm:items-end sm:justify-between gap-4">
                    <div>
                        <p class="font-mono text-xs tracking-[.18em] text-ink-soft">GHIDURI</p>
                        <h2 class="mt-2 font-display text-5xl font-bold leading-none" x-text="currentTopicTitle()"></h2>
                    </div>
                    <p class="text-ink-soft" x-text="filteredGuides().length + ' din <?= count($guides) ?> ghiduri'"></p>
                </div>

                <?php if (empty($guides)): ?>
                    <div class="mt-8 ticket bg-paper border-2 border-ink rounded-3xl p-10 text-center" style="--perf:100%">
                        <p class="font-display text-2xl font-bold">Încă nu sunt ghiduri publicate.</p>
                        <p class="text-ink-soft mt-2">Revino în curând — pregătim conținut editorial pentru activități.</p>
                        <a href="/categorii" class="mt-5 inline-flex px-6 py-3 rounded-full bg-ink text-paper font-bold">Explorează categorii</a>
                    </div>
                <?php else: ?>
                    <div class="mt-6 grid md:grid-cols-2 xl:grid-cols-3 gap-5">
                        <template x-for="guide in filteredGuides()" :key="guide.slug">
                            <article class="group rounded-[2rem] border-2 border-ink bg-paper overflow-hidden shadow-ticket hover:-translate-y-1 transition">
                                <a :href="'/ghiduri/' + guide.slug" class="block">
                                    <div class="relative h-52 overflow-hidden bg-ink">
                                        <img x-show="guide.image" :src="guide.image" :alt="guide.title" class="w-full h-full object-cover opacity-85 group-hover:scale-105 transition duration-500" loading="lazy" onerror="this.style.display='none'">
                                        <div class="absolute inset-0 grid place-items-center" x-show="!guide.image"><span class="text-6xl opacity-30">📖</span></div>
                                        <div class="absolute inset-0 bg-gradient-to-t from-ink/85 via-ink/10 to-transparent"></div>
                                        <span class="absolute left-4 top-4 rounded-full bg-paper text-ink px-3 py-1 text-xs font-bold" x-text="guide.topicLabel"></span>
                                        <span class="absolute right-4 top-4 rounded-full bg-mint text-forest px-3 py-1 text-xs font-bold" x-text="guide.readTime"></span>
                                        <h3 class="absolute left-4 bottom-4 right-4 font-display text-3xl font-bold text-paper leading-none" x-text="guide.title"></h3>
                                    </div>
                                </a>
                                <div class="p-5">
                                    <p class="text-ink-soft leading-relaxed line-clamp-3" x-text="guide.excerpt"></p>
                                    <div class="mt-5 pt-4 border-t border-ink/10 flex items-center justify-between gap-3">
                                        <a :href="'/ghiduri/' + guide.slug" class="font-bold text-vermilion underline-wobble">Citește ghidul</a>
                                        <span class="text-sm text-ink-soft" x-text="guide.date"></span>
                                    </div>
                                </div>
                            </article>
                        </template>
                    </div>
                <?php endif; ?>
            </section>
        </div>
    </div>
</section>

<!-- SEO HUBS -->
<section class="max-w-7xl mx-auto px-4 sm:px-6 py-16 sm:py-20">
    <div class="grid lg:grid-cols-[.85fr_1.15fr] gap-10 items-start">
        <div class="lg:sticky lg:top-28">
            <p class="stamp inline-flex px-3 py-1 text-xs font-mono tracking-[.18em] text-vermilion">HUBURI EDITORIALE</p>
            <h2 class="mt-5 font-display text-5xl sm:text-6xl font-bold leading-[.9]">Ghidurile acoperă intenții reale de căutare.</h2>
            <p class="mt-5 text-lg text-ink-soft leading-relaxed">Ghidurile explică, compară, recomandă și apoi trimit către categorii, orașe și activități cu bilete online.</p>
        </div>
        <div class="grid sm:grid-cols-2 gap-4">
            <?php foreach ($editorialHubs as $hub): ?>
                <a href="<?= htmlspecialchars($hub['url'], ENT_QUOTES) ?>" class="rounded-3xl border-2 border-ink/15 bg-paper p-5 hover:border-ink hover:-translate-y-1 transition">
                    <p class="font-mono text-xs tracking-[.18em] text-vermilion"><?= htmlspecialchars($hub['kicker']) ?></p>
                    <h3 class="mt-2 font-display text-3xl font-bold"><?= htmlspecialchars($hub['title']) ?></h3>
                    <p class="mt-2 text-ink-soft"><?= htmlspecialchars($hub['description']) ?></p>
                </a>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<!-- FINAL CTA -->
<section class="max-w-7xl mx-auto px-4 sm:px-6 pb-16 sm:pb-20">
    <div class="relative overflow-hidden rounded-[2rem] border-2 border-ink bg-vermilion text-paper p-8 sm:p-12">
        <div class="absolute inset-0 opacity-15" style="background-image:radial-gradient(#fff 1px,transparent 1.4px);background-size:15px 15px"></div>
        <div class="relative grid lg:grid-cols-[1fr_auto] gap-8 items-center">
            <div>
                <p class="font-mono text-xs tracking-[.2em] text-paper/60">EXPLOREAZĂ</p>
                <h2 class="mt-3 font-display text-5xl sm:text-6xl font-bold leading-[.9]">Ai citit ghidul. Acum alege activitatea.</h2>
                <p class="mt-4 max-w-2xl text-paper/75 text-lg">Vezi categorii, orașe și activități disponibile cu bilete online.</p>
            </div>
            <div class="flex flex-col sm:flex-row lg:flex-col gap-3">
                <a href="/categorii" class="rounded-full bg-paper text-ink px-6 py-4 font-bold text-center hover:bg-ink hover:text-paper transition">Vezi categorii</a>
                <a href="/orase" class="rounded-full border-2 border-paper/60 px-6 py-4 font-bold text-center hover:bg-paper hover:text-ink transition">Alege orașul</a>
            </div>
        </div>
    </div>
</section>

</main>

<script>
function guidesPage(data) {
    return {
        search: '',
        activeTopic: 'all',
        guides: data.guides || [],
        topics: data.topics || [],
        quickChips: data.quickChips || [],
        norm(s) {
            return (s || '').toString().toLowerCase().normalize('NFD')
                .replace(/[̀-ͯ]/g, '')
                .replace(/[şș]/g, 's').replace(/[ţț]/g, 't')
                .replace(/[ăâ]/g, 'a').replace(/[î]/g, 'i').trim();
        },
        currentTopicTitle() {
            const found = this.topics.find(t => t.key === this.activeTopic);
            return found ? found.label : 'Toate ghidurile';
        },
        filteredGuides() {
            const q = this.norm(this.search);
            return this.guides.filter(g => {
                const matchesTopic = this.activeTopic === 'all' || g.topic === this.activeTopic;
                const matchesSearch = !q || this.norm(g.title + ' ' + g.excerpt + ' ' + g.topicLabel).includes(q);
                return matchesTopic && matchesSearch;
            });
        },
    };
}
</script>

<?php include __DIR__ . '/includes/footer.php'; ?>
