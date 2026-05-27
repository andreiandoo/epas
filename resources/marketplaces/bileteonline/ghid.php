<?php
/**
 * bilete.online — /ghiduri/{slug}  (single guide, v2 design)
 *
 * Renders one editorial guide authored in /marketplace/blog-articles.
 * Content is RichEditor HTML. Linked activities render as recommendation
 * cards; FAQs come from the guide's own `faqs` (set in admin) and fall
 * back to a generic set if none are defined.
 *
 * NOTE: the "Cuprins" (table of contents) section from the v2 template is
 * intentionally NOT implemented (per product decision) — the article body
 * is free-form RichEditor HTML without stable anchor IDs, so a 2-column
 * layout (content + sidebar) is used instead of the original 3-column.
 */

require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/api.php';
require_once __DIR__ . '/includes/nav-helpers.php';

$slug = $_GET['slug'] ?? '';
if (! preg_match('/^[a-z0-9][a-z0-9-]*$/', $slug)) {
    http_response_code(404);
    require __DIR__ . '/404.php';
    exit;
}

$resp    = api_cached("guide_detail_{$slug}", fn () => api_get('/blog-articles/' . urlencode($slug)), 180);
$article = $resp['data'] ?? null;

if (! $article || empty($article['title'])) {
    http_response_code(404);
    require __DIR__ . '/404.php';
    exit;
}

$title       = $article['title'];
$excerpt     = $article['excerpt'] ?? '';
$contentHtml = $article['content'] ?? '';
$image       = $article['image_url'] ?? null;
$catName     = $article['category']['name'] ?? '';
$catSlug     = $article['category']['slug'] ?? '';
$readTime    = isset($article['read_time']) ? ((int) $article['read_time']) . ' min' : '5 min';
$publishedAt = $article['published_at'] ?? $article['created_at'] ?? null;
$activities  = is_array($article['activities'] ?? null) ? $article['activities'] : [];
$linkedEvent = $article['event'] ?? null;

// Per-guide FAQs, with a generic fallback when none defined in admin.
$faqs = is_array($article['faqs'] ?? null) ? $article['faqs'] : [];
if (empty($faqs)) {
    $faqs = [
        ['q' => 'Cum cumpăr bilete pentru activitățile din ghid?', 'a' => 'Deschizi activitatea recomandată, alegi data și ora disponibile, completezi datele și primești biletul cu cod QR pe email.'],
        ['q' => 'Trebuie să printez biletul?', 'a' => 'Nu. Poți arăta codul QR de pe telefon. Dacă o locație cere altceva, găsești informația pe pagina activității.'],
        ['q' => 'Pot anula sau reprograma?', 'a' => 'Politica de anulare este stabilită de fiecare locație și apare pe pagina activității înainte de plată.'],
    ];
}

// Date label
$dateIso = $publishedAt ? date('c', strtotime($publishedAt)) : null;
$dateLabel = '';
if ($publishedAt && ($ts = strtotime($publishedAt))) {
    $months = [1=>'ianuarie',2=>'februarie',3=>'martie',4=>'aprilie',5=>'mai',6=>'iunie',7=>'iulie',8=>'august',9=>'septembrie',10=>'octombrie',11=>'noiembrie',12=>'decembrie'];
    $dateLabel = (int) date('j', $ts) . ' ' . $months[(int) date('n', $ts)] . ' ' . date('Y', $ts);
}

// Related guides — same category, exclude current. Best-effort.
$relatedResp = api_cached("guides_related_{$catSlug}", function () use ($catSlug) {
    $params = ['per_page' => 4, 'status' => 'published'];
    if ($catSlug) $params['category'] = $catSlug;
    return api_get('/blog-articles', $params);
}, 300);
$relatedRaw = $relatedResp['data'] ?? [];
$related = [];
foreach ((is_array($relatedRaw) ? $relatedRaw : []) as $r) {
    if (($r['slug'] ?? '') === $slug) continue;
    $related[] = [
        'title'   => $r['title'] ?? '',
        'slug'    => $r['slug'] ?? '',
        'excerpt' => $r['excerpt'] ?? '',
        'image'   => $r['image_url'] ?? null,
        'kicker'  => $r['category']['name'] ?? 'Ghid',
    ];
    if (count($related) >= 3) break;
}

// SEO
$pageTitleRaw    = $title . ' — ghid | ' . SITE_NAME;
$pageDescription = $excerpt ?: ('Ghid de activități pe ' . SITE_NAME . ': ' . $title);
$canonicalUrl    = SITE_URL . '/ghiduri/' . $slug;
$currentPage     = 'ghiduri';
$cssBundle       = 'listing';
$ogImage         = $image;

$breadcrumbs = [
    ['name' => 'Acasă',   'url' => SITE_URL . '/'],
    ['name' => 'Ghiduri', 'url' => SITE_URL . '/ghiduri'],
    ['name' => $title,    'url' => $canonicalUrl],
];

$structuredData = [
    [
        '@context' => 'https://schema.org',
        '@type' => 'Article',
        'headline' => $title,
        'description' => $pageDescription,
        'url' => $canonicalUrl,
        'mainEntityOfPage' => $canonicalUrl,
        'image' => $image,
        'datePublished' => $dateIso,
        'inLanguage' => 'ro-RO',
        'author' => ['@type' => 'Organization', 'name' => SITE_NAME],
        'publisher' => ['@type' => 'Organization', 'name' => SITE_NAME, 'url' => SITE_URL . '/'],
    ],
];
if (! empty($faqs)) {
    $structuredData[] = [
        '@context' => 'https://schema.org',
        '@type' => 'FAQPage',
        'mainEntity' => array_map(fn ($f) => [
            '@type' => 'Question',
            'name' => $f['q'],
            'acceptedAnswer' => ['@type' => 'Answer', 'text' => $f['a']],
        ], $faqs),
    ];
}

include __DIR__ . '/includes/head.php';
include __DIR__ . '/includes/header.php';
?>

<article x-data="singleGuidePage(<?= htmlspecialchars(json_encode([
    'activities' => $activities,
    'related'    => $related,
    'faqs'       => $faqs,
]), ENT_QUOTES) ?>)">

<!-- HERO -->
<section class="relative overflow-hidden border-b border-ink/10">
    <div class="absolute inset-0 bg-[radial-gradient(circle_at_84%_14%,rgba(232,69,39,.18),transparent_32%),radial-gradient(circle_at_14%_76%,rgba(30,74,61,.16),transparent_34%)]"></div>
    <div class="relative max-w-7xl mx-auto px-4 sm:px-6 pt-10 lg:pt-14 pb-10">
        <nav aria-label="Breadcrumb" class="text-sm text-ink-soft">
            <ol class="flex flex-wrap items-center gap-2">
                <?php foreach ($breadcrumbs as $i => $b): ?>
                    <?php if ($i > 0): ?><li aria-hidden="true">/</li><?php endif; ?>
                    <li>
                        <?php if ($i < count($breadcrumbs) - 1): ?>
                            <a href="<?= htmlspecialchars($b['url'], ENT_QUOTES) ?>" class="hover:text-vermilion"><?= htmlspecialchars($b['name']) ?></a>
                        <?php else: ?>
                            <span class="text-ink font-bold"><?= htmlspecialchars($b['name']) ?></span>
                        <?php endif; ?>
                    </li>
                <?php endforeach; ?>
            </ol>
        </nav>

        <div class="mt-7 max-w-4xl">
            <div class="flex flex-wrap items-center gap-3 text-sm">
                <?php if ($catName): ?>
                    <span class="stamp inline-flex px-3 py-1 rounded-full bg-vermilion text-paper border-vermilion font-mono text-[11px] tracking-[.12em]"><?= htmlspecialchars(strtoupper($catName)) ?></span>
                <?php endif; ?>
                <span class="text-ink-soft font-mono"><?= htmlspecialchars($readTime) ?> citire</span>
                <?php if ($dateLabel): ?><span class="text-ink-soft">· <?= htmlspecialchars($dateLabel) ?></span><?php endif; ?>
            </div>
            <h1 class="mt-5 font-display text-[clamp(2.4rem,6vw,5.4rem)] leading-[.88] font-bold"><?= htmlspecialchars($title) ?></h1>
            <?php if ($excerpt): ?>
                <p class="mt-5 text-xl lg:text-2xl text-ink-soft leading-relaxed"><?= htmlspecialchars($excerpt) ?></p>
            <?php endif; ?>
        </div>

        <?php if ($image): ?>
            <div class="mt-8 relative rounded-[2rem] overflow-hidden border-2 border-ink shadow-deep">
                <img src="<?= htmlspecialchars($image, ENT_QUOTES) ?>" alt="<?= htmlspecialchars($title, ENT_QUOTES) ?>" class="w-full h-[300px] sm:h-[480px] object-cover" loading="eager">
            </div>
        <?php endif; ?>
    </div>
</section>

<?php if ($catSlug): ?>
<!-- QUICK CTA STRIP -->
<section class="border-b-2 border-ink bg-ink text-paper">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 py-5 flex flex-col lg:flex-row lg:items-center lg:justify-between gap-4">
        <div>
            <p class="font-mono text-xs tracking-[.18em] text-paper/45">VREI DIRECT ACTIVITĂȚI?</p>
            <p class="font-display text-2xl font-bold">Vezi activități legate de acest ghid.</p>
        </div>
        <div class="flex flex-wrap gap-3">
            <a href="/<?= htmlspecialchars($catSlug, ENT_QUOTES) ?>" class="rounded-full bg-vermilion text-paper px-5 py-3 font-bold hover:bg-vermilion-d transition"><?= htmlspecialchars($catName ?: 'Vezi activități') ?></a>
            <a href="/categorii" class="rounded-full border-2 border-paper/40 px-5 py-3 font-bold hover:bg-paper hover:text-ink transition">Toate categoriile</a>
        </div>
    </div>
</section>
<?php endif; ?>

<!-- BODY (2-column: content + sidebar; NO table of contents) -->
<section class="max-w-7xl mx-auto px-4 sm:px-6 py-14 sm:py-20">
    <div class="grid lg:grid-cols-[minmax(0,1fr)_340px] gap-10 items-start">

        <div class="article-prose min-w-0">
            <?php if ($contentHtml): ?>
                <?= $contentHtml /* Trusted: authored in admin RichEditor */ ?>
            <?php else: ?>
                <p class="text-ink-soft text-lg">Conținutul acestui ghid va fi disponibil în curând.</p>
            <?php endif; ?>

            <!-- Linked activities -->
            <template x-if="activities.length > 0">
                <div class="not-prose my-10">
                    <h2 class="font-display text-4xl font-bold leading-none">Activități recomandate</h2>
                    <div class="mt-6 grid md:grid-cols-2 gap-5">
                        <template x-for="activity in activities" :key="activity.slug">
                            <article class="rounded-[2rem] border-2 border-ink bg-paper overflow-hidden shadow-ticket hover:-translate-y-1 transition">
                                <a :href="activity.url" class="block">
                                    <div class="relative h-52 bg-ink overflow-hidden">
                                        <img x-show="activity.image" :src="activity.image" :alt="activity.title" class="w-full h-full object-cover opacity-85" loading="lazy" onerror="this.style.display='none'">
                                        <div class="absolute inset-0 grid place-items-center" x-show="!activity.image"><span class="text-6xl opacity-30">🎫</span></div>
                                        <div class="absolute inset-0 bg-gradient-to-t from-ink/85 via-transparent to-transparent"></div>
                                        <span x-show="activity.category" class="absolute left-4 top-4 rounded-full bg-paper text-ink px-3 py-1 text-xs font-bold" x-text="activity.category"></span>
                                        <h3 class="absolute left-4 bottom-4 right-4 font-display text-3xl font-bold text-paper leading-none" x-text="activity.title"></h3>
                                    </div>
                                </a>
                                <div class="p-5 flex items-center justify-between gap-3">
                                    <span class="font-display text-2xl font-bold" x-text="activity.price || ''"></span>
                                    <a :href="activity.url" class="rounded-full bg-vermilion text-paper px-4 py-2 font-bold hover:bg-vermilion-d transition">Vezi bilete</a>
                                </div>
                            </article>
                        </template>
                    </div>
                </div>
            </template>

            <!-- FAQ -->
            <h2 id="faq" class="!mt-12">Întrebări frecvente</h2>
            <div class="not-prose mt-6 space-y-3" x-data="{open:0}">
                <template x-for="(faq,index) in faqs" :key="faq.q">
                    <article class="rounded-3xl border-2 border-ink bg-paper overflow-hidden">
                        <button @click="open=open===index?null:index" class="w-full text-left p-5 sm:p-6 flex items-center justify-between gap-4">
                            <span class="font-display text-2xl sm:text-3xl font-bold" x-text="faq.q"></span>
                            <span class="text-3xl font-bold" x-text="open===index?'−':'+'"></span>
                        </button>
                        <div x-show="open===index" x-collapse class="px-5 sm:px-6 pb-6 text-ink-soft leading-relaxed" x-text="faq.a"></div>
                    </article>
                </template>
            </div>
        </div>

        <!-- SIDEBAR -->
        <aside class="lg:sticky lg:top-28 space-y-5">
            <?php if ($linkedEvent): ?>
                <div class="rounded-[2rem] border-2 border-vermilion bg-rose p-5">
                    <p class="font-mono text-xs tracking-[.18em] text-vermilion">EVENIMENT</p>
                    <h3 class="mt-2 font-display text-3xl font-bold leading-none"><?= htmlspecialchars($linkedEvent['title'] ?? '') ?></h3>
                    <a href="/bilete/<?= htmlspecialchars($linkedEvent['slug'] ?? '', ENT_QUOTES) ?>" class="mt-4 inline-flex rounded-full bg-vermilion text-paper px-5 py-3 font-bold hover:bg-vermilion-d transition">Vezi biletele</a>
                </div>
            <?php endif; ?>

            <div class="rounded-[2rem] border-2 border-forest bg-mint p-5">
                <p class="font-mono text-xs tracking-[.18em] text-forest">CARD CADOU</p>
                <h3 class="mt-2 font-display text-3xl font-bold">Nu știi ce să alegi?</h3>
                <p class="mt-2 text-ink-soft">Trimite un card cadou și lasă destinatarul să aleagă experiența.</p>
                <a href="/card-cadou" class="mt-4 inline-flex rounded-full bg-forest text-paper px-5 py-3 font-bold hover:bg-ink transition">Cumpără card</a>
            </div>

            <div class="rounded-[2rem] border-2 border-ink bg-ink text-paper p-5">
                <p class="font-mono text-xs tracking-[.18em] text-paper/45">SHARE</p>
                <h3 class="mt-2 font-display text-3xl font-bold">Trimite ghidul</h3>
                <div class="mt-4 grid grid-cols-2 gap-2">
                    <a :href="'https://www.facebook.com/sharer/sharer.php?u=' + encodeURIComponent(pageUrl)" target="_blank" rel="noopener" class="rounded-full bg-paper/10 px-4 py-2 font-bold text-center hover:bg-paper hover:text-ink transition">Facebook</a>
                    <a :href="'https://wa.me/?text=' + encodeURIComponent(pageUrl)" target="_blank" rel="noopener" class="rounded-full bg-paper/10 px-4 py-2 font-bold text-center hover:bg-paper hover:text-ink transition">WhatsApp</a>
                    <a :href="'mailto:?body=' + encodeURIComponent(pageUrl)" class="rounded-full bg-paper/10 px-4 py-2 font-bold text-center hover:bg-paper hover:text-ink transition">Email</a>
                    <button type="button" @click="copyLink()" class="rounded-full bg-paper/10 px-4 py-2 font-bold hover:bg-paper hover:text-ink transition" x-text="copied ? 'Copiat ✓' : 'Copy link'"></button>
                </div>
            </div>
        </aside>
    </div>
</section>

<!-- RELATED GUIDES -->
<template x-if="related.length > 0">
    <section class="border-y-2 border-ink bg-paper-2/65">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 py-16 sm:py-20">
            <div class="flex flex-col lg:flex-row lg:items-end lg:justify-between gap-5">
                <div>
                    <p class="stamp inline-flex px-3 py-1 text-xs font-mono tracking-[.18em] text-vermilion">CITEȘTE ȘI</p>
                    <h2 class="mt-5 font-display text-5xl sm:text-6xl font-bold leading-[.9]">Ghiduri similare</h2>
                </div>
                <a href="/ghiduri" class="rounded-full bg-ink text-paper px-6 py-4 font-bold hover:bg-vermilion transition">Toate ghidurile</a>
            </div>
            <div class="mt-10 grid md:grid-cols-3 gap-5">
                <template x-for="guide in related" :key="guide.slug">
                    <article class="rounded-[2rem] border-2 border-ink bg-paper overflow-hidden shadow-ticket hover:-translate-y-1 transition">
                        <a :href="'/ghiduri/' + guide.slug" class="block">
                            <img x-show="guide.image" :src="guide.image" :alt="guide.title" class="h-48 w-full object-cover" loading="lazy" onerror="this.style.display='none'">
                            <div class="p-5">
                                <p class="font-mono text-xs tracking-[.18em] text-vermilion" x-text="guide.kicker"></p>
                                <h3 class="mt-2 font-display text-3xl font-bold leading-none" x-text="guide.title"></h3>
                                <p class="mt-2 text-ink-soft line-clamp-2" x-text="guide.excerpt"></p>
                            </div>
                        </a>
                    </article>
                </template>
            </div>
        </div>
    </section>
</template>

</article>

<style>
    .article-prose { font-size: 1.075rem; line-height: 1.8; color: #2E2820; }
    .article-prose > p { margin: 0 0 1.15rem; }
    .article-prose h2 { font-family: Fraunces, Georgia, serif; font-weight: 700; font-size: clamp(1.8rem, 3.5vw, 2.6rem); line-height: 1.05; margin: 2.2rem 0 1rem; letter-spacing: -.018em; }
    .article-prose h3 { font-family: Fraunces, Georgia, serif; font-weight: 700; font-size: 1.6rem; margin: 1.8rem 0 .8rem; }
    .article-prose ul, .article-prose ol { margin: 0 0 1.15rem 1.25rem; }
    .article-prose ul { list-style: disc; } .article-prose ol { list-style: decimal; }
    .article-prose li { margin-bottom: .4rem; }
    .article-prose a { color: #E84527; font-weight: 600; background-image: linear-gradient(currentColor,currentColor); background-size: 100% 2px; background-repeat: no-repeat; background-position: 0 100%; }
    .article-prose a:hover { background-size: 0 2px; }
    .article-prose blockquote { border-left: 4px solid #E84527; padding: .6rem 0 .6rem 1.25rem; margin: 1.6rem 0; font-family: Fraunces, Georgia, serif; font-size: 1.3rem; font-style: italic; color: #1B1714; }
    .article-prose img { border-radius: 1.25rem; border: 2px solid #1B1714; margin: 1.6rem 0; }
    .article-prose .not-prose { font-family: "Hanken Grotesk", system-ui, sans-serif; }
</style>

<script>
function singleGuidePage(data) {
    return {
        activities: data.activities || [],
        related: data.related || [],
        faqs: data.faqs || [],
        copied: false,
        pageUrl: window.location.href,
        copyLink() {
            try {
                navigator.clipboard.writeText(this.pageUrl);
                this.copied = true;
                setTimeout(() => { this.copied = false; }, 2500);
            } catch (e) {}
        },
    };
}
</script>

<?php include __DIR__ . '/includes/footer.php'; ?>
