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

// =========================================================================
// [activities ...] SHORTCODE — renders real activity cards inside guide
// content. Attributes:
//   ids="1,5,9"                              hand-picked activities, in order
//   city="bucuresti" category="x" limit="6"  auto-pull from category + city
//   style="small|large|long"                 card layout (default small)
// =========================================================================
if (! function_exists('bo_activity_card_url')) {
    function bo_activity_card_url(array $a): string {
        $cs = $a['city']['slug'] ?? '';
        return $cs ? '/' . $cs . '/' . ($a['slug'] ?? '') : '/activitate/' . ($a['slug'] ?? '');
    }
    function bo_price_from_cents($cents): string {
        $c = (int) $cents;
        return $c > 0 ? 'de la ' . number_format($c / 100, 0, ',', '.') . ' lei' : '';
    }
    function bo_duration_label($min): string {
        $m = (int) $min; if ($m <= 0) return '';
        if ($m < 60) return $m . ' min';
        $h = intdiv($m, 60); $r = $m % 60; return $r ? "{$h}h {$r}m" : "{$h}h";
    }

    function bo_render_activity_shortcodes(string $html): string {
        if (stripos($html, '[activities') === false) return $html;
        // The RichEditor usually wraps the shortcode in <p>…</p>; strip that
        // wrapper first (cards are block-level), then handle any bare ones.
        $html = preg_replace_callback('#<p>\s*(\[activities\b[^\]]*\])\s*</p>#i', fn ($m) => bo_activities_block($m[1]), $html);
        $html = preg_replace_callback('#\[activities\b[^\]]*\]#i', fn ($m) => bo_activities_block($m[0]), $html);
        return $html;
    }

    function bo_activities_block(string $shortcode): string {
        // RichEditor (TipTap) stores attribute quotes as &quot; entities and may
        // use curly/typographic quotes. Normalise to straight quotes + accept
        // both " and ' so the attributes actually parse.
        $sc = html_entity_decode($shortcode, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $sc = str_replace(['“', '”', '„', '‟', '″', '＂', '«', '»'], '"', $sc);
        preg_match_all('/([a-z_]+)\s*=\s*["\']([^"\']*)["\']/i', $sc, $mm, PREG_SET_ORDER);
        $attr = [];
        foreach ($mm as $a) $attr[strtolower($a[1])] = $a[2];

        $style = in_array($attr['style'] ?? 'small', ['small', 'large', 'long'], true) ? ($attr['style'] ?? 'small') : 'small';

        $params = [];
        if (! empty($attr['ids'])) {
            $ids = preg_replace('/[^0-9,]/', '', $attr['ids']);
            if ($ids === '') return '';
            $params['ids'] = $ids;
            $params['per_page'] = max(1, min(24, substr_count($ids, ',') + 1));
        } else {
            if (! empty($attr['city']))     $params['city'] = $attr['city'];
            if (! empty($attr['category'])) $params['category'] = $attr['category'];
            if (empty($params['city']) && empty($params['category'])) return '';
            $params['per_page'] = max(1, min(24, (int) ($attr['limit'] ?? 6)));
            $params['sort'] = in_array($attr['sort'] ?? '', ['recent', 'cheapest', 'soon'], true) ? $attr['sort'] : 'recent';
        }

        $resp = api_cached('guide_acts_' . md5(json_encode($params)), fn () => api_get('/activities', $params), 300);
        $items = $resp['data']['items'] ?? $resp['data']['data'] ?? [];
        if (! is_array($items) || ! $items) return '';

        return bo_activities_cards_html($items, $style);
    }

    function bo_activities_cards_html(array $items, string $style): string {
        $e = fn ($s) => htmlspecialchars((string) $s, ENT_QUOTES);
        $cards = '';
        foreach ($items as $a) {
            $url   = $e(bo_activity_card_url($a));
            $title = $e($a['title'] ?? '');
            $img   = $a['cover_image_url'] ?? '';
            $cat   = $e($a['category']['name'] ?? '');
            $meta  = $e(trim(($a['city']['name'] ?? '') . (bo_duration_label($a['duration_minutes'] ?? 0) ? ' · ' . bo_duration_label($a['duration_minutes'] ?? 0) : ''), ' ·'));
            $price = $e(bo_price_from_cents($a['cheapest_price_cents'] ?? 0));
            $ph    = '<div class="grid h-full w-full place-items-center bg-gradient-to-br from-vermilion via-ochre to-forest text-paper"><span class="px-3 text-center font-display text-xl font-bold">' . $e(mb_substr($a['title'] ?? '', 0, 18)) . '</span></div>';

            if ($style === 'long') {
                $imgBox = '<div class="relative h-32 w-40 flex-none overflow-hidden bg-ink sm:h-36 sm:w-52">' . ($img ? '<img src="' . $e($img) . '" alt="' . $title . '" class="h-full w-full object-cover transition duration-500 group-hover:scale-105" loading="lazy">' : $ph) . '</div>';
                $cards .= '<a href="' . $url . '" class="group flex gap-4 overflow-hidden rounded-[1.5rem] border-2 border-ink bg-paper shadow-ticket transition hover:-translate-y-0.5">' . $imgBox
                    . '<div class="flex min-w-0 flex-1 flex-col justify-center p-4 pr-5">'
                    . ($cat ? '<span class="mb-1 font-mono text-[10px] uppercase tracking-[.14em] text-vermilion">' . $cat . '</span>' : '')
                    . '<p class="font-display text-2xl font-bold leading-tight line-clamp-2 group-hover:text-vermilion">' . $title . '</p>'
                    . '<p class="mt-1 text-sm text-ink-soft">' . $meta . '</p>'
                    . ($price ? '<p class="mt-2 font-bold">' . $price . '</p>' : '')
                    . '</div></a>';
            } elseif ($style === 'large') {
                $imgBox = '<div class="relative aspect-square overflow-hidden bg-ink">' . ($img ? '<img src="' . $e($img) . '" alt="' . $title . '" class="h-full w-full object-cover transition duration-500 group-hover:scale-105" loading="lazy">' : $ph) . ($cat ? '<span class="absolute left-3 top-3 rounded-full bg-paper px-3 py-1 text-xs font-bold text-ink">' . $cat . '</span>' : '') . '</div>';
                $cards .= '<a href="' . $url . '" class="group overflow-hidden rounded-[1.5rem] border-2 border-ink bg-paper shadow-ticket transition hover:-translate-y-1">' . $imgBox
                    . '<div class="p-5"><p class="font-display text-2xl font-bold leading-tight line-clamp-2 group-hover:text-vermilion">' . $title . '</p>'
                    . '<p class="mt-2 text-sm text-ink-soft">' . $meta . '</p>'
                    . ($price ? '<p class="mt-3 font-bold">' . $price . '</p>' : '') . '</div></a>';
            } else { // small (vertical)
                $imgBox = '<div class="relative h-40 overflow-hidden bg-ink">' . ($img ? '<img src="' . $e($img) . '" alt="' . $title . '" class="h-full w-full object-cover transition duration-500 group-hover:scale-105" loading="lazy">' : $ph) . ($cat ? '<span class="absolute left-3 top-3 rounded-full bg-paper px-2.5 py-1 text-[11px] font-bold text-ink">' . $cat . '</span>' : '') . '</div>';
                $cards .= '<a href="' . $url . '" class="group overflow-hidden rounded-[1.25rem] border-2 border-ink bg-paper shadow-ticket transition hover:-translate-y-1">' . $imgBox
                    . '<div class="p-4"><p class="font-display text-xl font-bold leading-tight line-clamp-2 group-hover:text-vermilion">' . $title . '</p>'
                    . '<p class="mt-1.5 text-sm text-ink-soft">' . $meta . '</p>'
                    . ($price ? '<p class="mt-2 font-bold">' . $price . '</p>' : '') . '</div></a>';
            }
        }

        $grid = match ($style) {
            'long'  => 'grid gap-4',
            'large' => 'grid gap-5 sm:grid-cols-2 lg:grid-cols-3',
            default => 'grid gap-4 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4',
        };

        return '<div class="not-prose my-8 ' . $grid . '">' . $cards . '</div>';
    }
}

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
$contentHtml = bo_render_activity_shortcodes($article['content'] ?? '');
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

// F6 — auto "Activități recomandate" rail. Editors can also hand-pick activities
// inline via the [activities] shortcode in the body; this guarantees every guide
// still ends with a few bookable activities so a guide always converts.
$activities = [];
try {
    $railResp = api_cached('guide_rail_' . $slug, fn () => api_get('/activities', ['per_page' => 12, 'sort' => 'recent']), 300);
    $railRaw = $railResp['data']['items'] ?? [];
    foreach ((is_array($railRaw) ? $railRaw : []) as $a) {
        $t = is_array($a['title'] ?? null) ? ($a['title']['ro'] ?? reset($a['title'])) : ($a['title'] ?? '');
        $s = $a['slug'] ?? '';
        if ($t === '' || $s === '') continue;
        $cs = $a['city']['slug'] ?? '';
        $img = (string) ($a['cover_image_url'] ?? '');
        if ($img !== '' && ! str_starts_with($img, 'http')) $img = rtrim(STORAGE_URL, '/') . '/' . ltrim($img, '/');
        $activities[] = [
            'slug'     => $s,
            'url'      => $cs ? '/' . $cs . '/' . $s : '/activitate/' . $s,
            'title'    => $t,
            'image'    => $img,
            'category' => $a['category']['name'] ?? '',
            'price'    => ! empty($a['cheapest_price_cents']) ? ('de la ' . number_format($a['cheapest_price_cents'] / 100, 0, ',', '.') . ' lei') : '',
        ];
        if (count($activities) >= 6) break;
    }
} catch (\Throwable $e) {
    $activities = [];
}
?>
<?php
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
