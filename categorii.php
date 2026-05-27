<?php
/**
 * bilete.online — /categorii  (v2 design)
 *
 * Full catalog of leisure activity categories. Parent categories are
 * pulled live from the API + decorated with the bilete.online v2 ticket
 * aesthetic. The hero search + SEO intent hubs are static (programmatic
 * SEO pages — slugs link to /activitati-azi, /activitati-copii, etc.).
 */

$pageCacheTTL = 600; // 10 min — categories change rarely
require_once __DIR__ . '/includes/page-cache.php';
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/api.php';
require_once __DIR__ . '/includes/nav-helpers.php';

// =========================================================================
// DATA — parent categories with their children nested.
// =========================================================================
$categoriesResp = api_cached('categories_full_tree', fn () => api_get('/event-categories'), 600);
$rawCategories  = $categoriesResp['data']['categories'] ?? [];
if (! is_array($rawCategories)) $rawCategories = [];

$parents = [];
foreach ($rawCategories as $cat) {
    if (! empty($cat['parent_id'])) continue;
    $parents[] = $cat;
}
usort($parents, fn ($a, $b) => ($a['sort_order'] ?? 0) <=> ($b['sort_order'] ?? 0));

// Default emoji palette by index (for categories without icon_emoji).
$defaultEmojis = ['🎫', '🎡', '🖼️', '🧗', '🌲', '🎨', '🎭', '🎪', '🏛️', '🌳'];

// Static "intent hubs" — programmatic SEO pages we link to even if the
// pages themselves are still placeholders.
$intentHubs = [
    ['kicker' => 'TIMP',   'title' => 'Activități azi',         'description' => 'Pentru decizii rapide și activități disponibile imediat.',     'url' => '/activitati-azi'],
    ['kicker' => 'TIMP',   'title' => 'Activități weekend',     'description' => 'Idei pentru weekend: familie, grupuri, cupluri.',                'url' => '/activitati-weekend'],
    ['kicker' => 'VREME',  'title' => 'Zile ploioase',          'description' => 'Indoor: muzee, escape rooms, ateliere și expoziții.',           'url' => '/activitati-zile-ploioase'],
    ['kicker' => 'VREME',  'title' => 'Zile caniculare',        'description' => 'Activități răcoroase, indoor sau de seară.',                    'url' => '/activitati-zile-caniculare'],
    ['kicker' => 'BUGET',  'title' => 'Sub 50 lei',             'description' => 'Experiențe accesibile, potrivite pentru ieșiri spontane.',      'url' => '/activitati-sub-50-lei'],
    ['kicker' => 'PUBLIC', 'title' => 'Activități pentru copii','description' => 'Idei pentru copii și familie: muzee, ateliere, parcuri.',       'url' => '/activitati-copii'],
    ['kicker' => 'PUBLIC', 'title' => 'Activități pentru cupluri','description' => 'Experiențe pentru doi: tururi, ateliere, date nights.',       'url' => '/activitati-cupluri'],
    ['kicker' => 'OCAZIE', 'title' => 'Zi de naștere',          'description' => 'Idei pentru grupuri, copii, cupluri și cadouri.',               'url' => '/activitati-zi-de-nastere'],
];

// =========================================================================
// SEO
// =========================================================================
$pageTitleRaw    = 'Toate categoriile de activități — ' . SITE_NAME;
$pageDescription = 'Explorează toate categoriile de activități disponibile pe bilete.online: escape rooms, muzee, parcuri de distracții, parcuri de aventură, natură, peșteri, ateliere, copii, familie, cupluri și grupuri.';
$canonicalUrl    = SITE_URL . '/categorii';
$currentPage     = 'categorii';
$cssBundle       = 'listing';

$breadcrumbs = [
    ['name' => 'Acasă',     'url' => SITE_URL . '/'],
    ['name' => 'Categorii', 'url' => $canonicalUrl],
];

$itemListElements = [];
foreach ($parents as $i => $cat) {
    $catName = navFlatName($cat['name'] ?? '');
    $catSlug = $cat['slug'] ?? '';
    if (! $catName || ! $catSlug) continue;
    $itemListElements[] = [
        '@type' => 'ListItem',
        'position' => $i + 1,
        'name' => $catName,
        'url' => SITE_URL . '/' . $catSlug,
    ];
}
$structuredData = [];
if (! empty($itemListElements)) {
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

// JSON for Alpine.js search
$alpineCategories = array_map(function ($c) use ($defaultEmojis) {
    static $i = 0;
    $emoji = $c['icon_emoji'] ?? null;
    if (! $emoji) {
        $emoji = $defaultEmojis[$i % count($defaultEmojis)];
        $i++;
    }
    $children = $c['children'] ?? [];
    return [
        'title'    => navFlatName($c['name'] ?? '') ?: ($c['slug'] ?? ''),
        'slug'     => $c['slug'] ?? '',
        'url'      => '/' . ($c['slug'] ?? ''),
        'emoji'    => $emoji,
        'desc'     => navFlatName($c['description'] ?? '') ?: '',
        'count'    => (int) ($c['event_count'] ?? 0),
        'children' => array_map(fn ($ch) => [
            'title' => navFlatName($ch['name'] ?? '') ?: ($ch['slug'] ?? ''),
            'slug'  => $ch['slug'] ?? '',
        ], array_slice($children, 0, 12)),
    ];
}, $parents);

include __DIR__ . '/includes/head.php';
include __DIR__ . '/includes/header.php';
?>

<main x-data="categoriesPage(<?= htmlspecialchars(json_encode($alpineCategories), ENT_QUOTES) ?>)">

<!-- HERO -->
<section class="relative overflow-hidden border-b-2 border-ink">
    <div class="absolute inset-0 bg-[radial-gradient(circle_at_82%_14%,rgba(232,69,39,.24),transparent_30%),radial-gradient(circle_at_16%_72%,rgba(30,74,61,.22),transparent_34%),radial-gradient(circle_at_48%_42%,rgba(218,154,51,.16),transparent_30%)]"></div>
    <div class="relative max-w-7xl mx-auto px-4 sm:px-6 pt-14 sm:pt-20 pb-16 sm:pb-24">
        <nav class="flex items-center gap-2 text-sm text-ink-soft" aria-label="Breadcrumb">
            <a href="/" class="hover:text-vermilion">Acasă</a><span>/</span><span class="text-ink">Categorii</span>
        </nav>
        <div class="mt-8 grid lg:grid-cols-[1fr_.85fr] gap-12 items-center">
            <div>
                <p class="stamp inline-flex px-3 py-1 text-xs font-mono tracking-[.18em] text-vermilion bg-paper/70">TOATE CATEGORIILE · ACTIVITĂȚI · BILETE ONLINE</p>
                <h1 class="mt-6 font-display text-6xl sm:text-8xl font-bold leading-[.82]">Ce vrei să faci?</h1>
                <p class="mt-6 max-w-2xl text-xl sm:text-2xl text-ink-soft leading-relaxed">
                    Explorează activități după categorie, public, vreme, buget sau ocazie. De la escape rooms și muzee până la parcuri de aventură, peșteri, rezervații, ateliere și experiențe pentru familie.
                </p>
                <div class="mt-8 max-w-2xl">
                    <label class="sr-only" for="category-search">Caută categorii</label>
                    <div class="relative">
                        <input id="category-search" type="text" class="field text-lg pr-14" x-model="search" placeholder="Caută: escape room, muzeu, copii, indoor, weekend...">
                        <span class="absolute right-4 top-1/2 -translate-y-1/2 text-ink-soft">
                            <svg viewBox="0 0 24 24" class="w-6 h-6" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="7"/><path d="m20 20-3.5-3.5"/></svg>
                        </span>
                    </div>
                </div>
                <div class="mt-6 flex flex-wrap gap-2">
                    <?php foreach ($intentHubs as $hub): ?>
                        <a href="<?= htmlspecialchars($hub['url'], ENT_QUOTES) ?>" class="rounded-full bg-paper/70 border border-ink/10 px-4 py-2 font-bold hover:bg-ink hover:text-paper transition"><?= htmlspecialchars($hub['title']) ?></a>
                    <?php endforeach; ?>
                </div>
            </div>
            <div class="relative min-h-[420px] hidden lg:block">
                <div class="absolute inset-x-8 top-10 bottom-8 rounded-[2.4rem] bg-ink rotate-[-2deg] shadow-deep"></div>
                <div class="absolute top-0 left-0 right-0 mx-auto max-w-[540px] ticket bg-paper border-2 border-ink rounded-[2rem] overflow-hidden shadow-deep rotate-[2deg]" style="--perf:100%">
                    <div class="p-6 sm:p-8">
                        <p class="font-mono text-xs tracking-[.18em] text-ink-soft">CATEGORY MAP</p>
                        <h2 class="mt-3 font-display text-3xl font-bold leading-none">Dintr-o idee vagă într-o activitate concretă.</h2>
                        <div class="mt-7 grid grid-cols-2 gap-3">
                            <?php foreach (array_slice($parents, 0, 4) as $i => $cat):
                                $name  = navFlatName($cat['name'] ?? '') ?: ($cat['slug'] ?? '');
                                $slug  = $cat['slug'] ?? '';
                                $emoji = $cat['icon_emoji'] ?? $defaultEmojis[$i % count($defaultEmojis)];
                                $bg    = ['bg-vermilion text-paper', 'bg-mint text-ink', 'bg-ochre text-ink', 'bg-forest text-paper'][$i % 4];
                                $rotate = ($i % 2 === 0) ? '-2deg' : '2deg';
                            ?>
                                <a href="/<?= htmlspecialchars($slug, ENT_QUOTES) ?>" class="rounded-3xl <?= $bg ?> p-5 hover:rotate-0 transition" style="transform: rotate(<?= $rotate ?>)">
                                    <p class="text-3xl"><?= htmlspecialchars($emoji) ?></p>
                                    <p class="mt-3 font-display text-2xl font-bold"><?= htmlspecialchars($name) ?></p>
                                </a>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- CATEGORY GRID -->
<section class="max-w-7xl mx-auto px-4 sm:px-6 py-14">
    <div class="flex flex-col sm:flex-row sm:items-end sm:justify-between gap-4">
        <div>
            <p class="font-mono text-xs tracking-[.18em] text-ink-soft">REZULTATE</p>
            <h2 class="mt-2 font-display text-5xl font-bold leading-none">Categorii principale</h2>
        </div>
        <p class="text-ink-soft" x-text="filteredCategories().length + ' din <?= count($alpineCategories) ?> categorii afișate'"></p>
    </div>

    <?php if (empty($alpineCategories)): ?>
        <div class="mt-10 ticket bg-paper border-2 border-ink rounded-3xl p-10 text-center" style="--perf:100%">
            <p class="font-display text-2xl font-bold">Categoriile încă nu sunt configurate.</p>
            <p class="text-ink-soft mt-2">Reveniți în curând sau scrie-ne la <a href="mailto:<?= htmlspecialchars(SUPPORT_EMAIL ?? '', ENT_QUOTES) ?>" class="text-vermilion underline-wobble"><?= htmlspecialchars(SUPPORT_EMAIL ?? '') ?></a>.</p>
        </div>
    <?php else: ?>
        <div class="mt-8 grid md:grid-cols-2 xl:grid-cols-3 gap-5">
            <template x-for="cat in filteredCategories()" :key="cat.slug">
                <article class="group rounded-[2rem] border-2 border-ink bg-paper overflow-hidden shadow-ticket hover:-translate-y-1 transition">
                    <a :href="cat.url" class="block">
                        <div class="relative h-44 bg-gradient-to-br from-vermilion/15 via-ochre/15 to-forest/15">
                            <div class="absolute inset-0 grid place-items-center">
                                <span class="text-7xl" x-text="cat.emoji"></span>
                            </div>
                            <div class="absolute left-4 bottom-4 right-4 flex items-end justify-between gap-3">
                                <div class="bg-ink/85 backdrop-blur px-3 py-1.5 rounded-full">
                                    <span class="font-mono text-[10px] tracking-[.18em] text-paper/70">CATEGORIE</span>
                                </div>
                                <span class="font-mono text-[10px] tracking-[.18em] text-ink-soft bg-paper/90 px-3 py-1.5 rounded-full" x-text="cat.count > 0 ? cat.count + ' activități' : 'în curând'"></span>
                            </div>
                        </div>
                    </a>
                    <div class="p-5">
                        <a :href="cat.url"><h3 class="font-display text-3xl font-bold leading-none group-hover:text-vermilion transition" x-text="cat.title"></h3></a>
                        <p x-show="cat.desc" class="mt-2 text-ink-soft leading-relaxed line-clamp-3" x-text="cat.desc"></p>
                        <div class="mt-4 flex flex-wrap gap-2" x-show="cat.children.length > 0">
                            <template x-for="ch in cat.children.slice(0, 6)" :key="ch.slug">
                                <a :href="'/' + ch.slug" class="rounded-full bg-paper-2 border border-ink/10 px-3 py-1 text-xs font-bold hover:bg-ink hover:text-paper transition" x-text="ch.title"></a>
                            </template>
                            <a x-show="cat.children.length > 6" :href="cat.url" class="rounded-full bg-ink text-paper px-3 py-1 text-xs font-bold" x-text="'+' + (cat.children.length - 6)"></a>
                        </div>
                        <div class="mt-5 pt-4 border-t border-ink/10 flex items-center justify-between gap-3">
                            <a :href="cat.url" class="font-bold text-vermilion underline-wobble">Vezi categoria</a>
                        </div>
                    </div>
                </article>
            </template>
        </div>
    <?php endif; ?>
</section>

<!-- TAXONOMY EXPLAINER -->
<section class="border-y-2 border-ink bg-paper-2/65">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 py-16 sm:py-20">
        <div class="max-w-4xl">
            <p class="stamp inline-flex px-3 py-1 text-xs font-mono tracking-[.18em] text-vermilion">TAXONOMIE</p>
            <h2 class="mt-5 font-display text-5xl sm:text-6xl font-bold leading-[.9]">Cum sunt organizate activitățile.</h2>
            <p class="mt-5 text-lg text-ink-soft leading-relaxed">O activitate are o categorie reală, dar și un public, un context, un buget și o vreme — toate folosite pentru a o găsi rapid.</p>
        </div>
        <div class="mt-10 grid lg:grid-cols-3 gap-5">
            <article class="rounded-[2rem] border-2 border-ink bg-paper p-6">
                <p class="font-mono text-xs tracking-[.18em] text-vermilion">CATEGORY</p>
                <h3 class="mt-3 font-display text-4xl font-bold leading-none">Ce este activitatea?</h3>
                <ul class="mt-5 space-y-2 text-ink-soft">
                    <li>• Escape room</li>
                    <li>• Muzeu / expoziție</li>
                    <li>• Parc de aventură</li>
                    <li>• Peșteră / natură</li>
                    <li>• Atelier creativ</li>
                </ul>
            </article>
            <article class="rounded-[2rem] border-2 border-ink bg-ink text-paper p-6">
                <p class="font-mono text-xs tracking-[.18em] text-ochre">AUDIENCE</p>
                <h3 class="mt-3 font-display text-4xl font-bold leading-none">Pentru cine este?</h3>
                <ul class="mt-5 space-y-2 text-paper/60">
                    <li>• Copii</li>
                    <li>• Familie</li>
                    <li>• Cupluri</li>
                    <li>• Grupuri</li>
                    <li>• Corporate</li>
                </ul>
            </article>
            <article class="rounded-[2rem] border-2 border-ink bg-paper p-6">
                <p class="font-mono text-xs tracking-[.18em] text-vermilion">CONTEXT</p>
                <h3 class="mt-3 font-display text-4xl font-bold leading-none">Când / de ce o alegi?</h3>
                <ul class="mt-5 space-y-2 text-ink-soft">
                    <li>• Weekend</li>
                    <li>• Azi / mâine</li>
                    <li>• Zi ploioasă</li>
                    <li>• Sub 50 lei</li>
                    <li>• Zi de naștere</li>
                </ul>
            </article>
        </div>
    </div>
</section>

<!-- INTENT HUBS -->
<section class="max-w-7xl mx-auto px-4 sm:px-6 py-16 sm:py-20">
    <div class="grid lg:grid-cols-[.85fr_1.15fr] gap-10 items-start">
        <div class="lg:sticky lg:top-28">
            <p class="stamp inline-flex px-3 py-1 text-xs font-mono tracking-[.18em] text-vermilion">HUBURI SEO</p>
            <h2 class="mt-5 font-display text-5xl sm:text-6xl font-bold leading-[.9]">Caută după intenție, nu doar după tip.</h2>
            <p class="mt-5 text-lg text-ink-soft leading-relaxed">Mulți utilizatori nu știu exact ce categorie vor. Caută „ceva pentru copii", „ce facem azi", „activități când plouă" sau „ceva ieftin".</p>
        </div>
        <div class="grid sm:grid-cols-2 gap-4">
            <?php foreach ($intentHubs as $hub): ?>
                <a href="<?= htmlspecialchars($hub['url'], ENT_QUOTES) ?>" class="rounded-3xl border-2 border-ink/15 bg-paper-2/70 p-5 hover:border-ink hover:bg-paper transition">
                    <p class="font-mono text-xs tracking-[.18em] text-vermilion"><?= htmlspecialchars($hub['kicker']) ?></p>
                    <h3 class="mt-2 font-display text-3xl font-bold"><?= htmlspecialchars($hub['title']) ?></h3>
                    <p class="mt-2 text-ink-soft"><?= htmlspecialchars($hub['description']) ?></p>
                </a>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<!-- CITY × CATEGORY -->
<section class="border-y-2 border-ink bg-ink text-paper">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 py-16 sm:py-20">
        <div class="grid lg:grid-cols-2 gap-10 items-center">
            <div>
                <p class="stamp inline-flex px-3 py-1 text-xs font-mono tracking-[.18em] text-ochre">SEO LOCAL</p>
                <h2 class="mt-5 font-display text-5xl sm:text-6xl font-bold leading-[.9]">Categorii × Orașe.</h2>
                <p class="mt-5 text-lg text-paper/60 leading-relaxed">Combinațiile generează pagini relevante pentru căutări locale: „escape rooms Brașov", „muzee Cluj", „activități copii București".</p>
                <a href="/orase" class="mt-7 inline-flex rounded-full bg-paper text-ink px-6 py-3 font-bold hover:bg-vermilion hover:text-paper transition">Vezi toate orașele</a>
            </div>
            <div class="ticket bg-paper text-ink rounded-[2rem] overflow-hidden shadow-deep" style="--perf:100%">
                <div class="p-6 border-b-2 border-dashed border-ink/15">
                    <p class="font-mono text-xs tracking-[.18em] text-ink-soft">EXEMPLE</p>
                    <h3 class="mt-2 font-display text-4xl font-bold">Linkuri interne</h3>
                </div>
                <div class="p-6 space-y-3">
                    <?php foreach (navGetCities(4) as $c): ?>
                        <a href="/<?= htmlspecialchars($c['slug'], ENT_QUOTES) ?>" class="block rounded-2xl bg-paper-2 border border-ink/10 p-4 hover:border-ink transition">
                            <strong>/<?= htmlspecialchars($c['slug']) ?></strong><br>
                            <span class="text-sm text-ink-soft">activități în <?= htmlspecialchars($c['label']) ?></span>
                        </a>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- FAQ -->
<section class="max-w-5xl mx-auto px-4 sm:px-6 py-16 sm:py-20" x-data="{open:0}">
    <div class="text-center max-w-3xl mx-auto">
        <p class="stamp inline-flex px-3 py-1 text-xs font-mono tracking-[.18em] text-vermilion">FAQ</p>
        <h2 class="mt-5 font-display text-5xl sm:text-6xl font-bold leading-[.9]">Cum alegi categoria potrivită?</h2>
    </div>
    <div class="mt-10 space-y-3">
        <?php $faqs = [
            ['Care este diferența dintre categorie și intenție?', 'Categoria descrie ce este activitatea: escape room, muzeu, parc, atelier. Intenția descrie de ce o cauți: pentru copii, pentru weekend, când plouă, sub 50 lei sau pentru o zi de naștere.'],
            ['O activitate poate apărea în mai multe pagini?', 'Da. O activitate are o categorie principală, dar poate apărea în pagini după public, oraș, vreme, buget sau ocazie.'],
            ['Cum aleg rapid o activitate potrivită?', 'Începe cu orașul, apoi alege contextul: copii, indoor, outdoor, azi, weekend sau buget. Dacă știi exact ce vrei, mergi direct la categoria principală.'],
            ['De ce sunt importante paginile oraș + categorie?', 'Utilizatorii caută local: escape rooms Brașov, muzee București, activități copii Cluj. Aceste combinații ajută la SEO și la descoperire rapidă.'],
            ['Pot cumpăra bilete direct din categorie?', 'Da. Paginile de categorie afișează activitățile disponibile, prețurile și butoanele directe către pagina activității sau coș.'],
        ]; foreach ($faqs as $i => $faq): ?>
            <article class="rounded-3xl border-2 border-ink bg-paper overflow-hidden">
                <button @click="open=open===<?= $i ?>?null:<?= $i ?>" class="w-full text-left p-5 sm:p-6 flex items-center justify-between gap-4">
                    <span class="font-display text-2xl sm:text-3xl font-bold"><?= htmlspecialchars($faq[0]) ?></span>
                    <span class="text-3xl font-bold" x-text="open===<?= $i ?>?'−':'+'"></span>
                </button>
                <div x-show="open===<?= $i ?>" x-collapse class="px-5 sm:px-6 pb-6 text-ink-soft leading-relaxed"><?= htmlspecialchars($faq[1]) ?></div>
            </article>
        <?php endforeach; ?>
    </div>
</section>

<!-- FINAL CTA -->
<section class="max-w-7xl mx-auto px-4 sm:px-6 pb-16 sm:pb-20">
    <div class="relative overflow-hidden rounded-[2rem] border-2 border-ink bg-vermilion text-paper p-8 sm:p-12">
        <div class="absolute inset-0 opacity-15" style="background-image:radial-gradient(#fff 1px,transparent 1.4px);background-size:15px 15px"></div>
        <div class="relative grid lg:grid-cols-[1fr_auto] gap-8 items-center">
            <div>
                <p class="font-mono text-xs tracking-[.2em] text-paper/60">DESCOPERĂ</p>
                <h2 class="mt-3 font-display text-5xl sm:text-6xl font-bold leading-[.9]">Alege categoria. Găsește activitatea.</h2>
                <p class="mt-4 max-w-2xl text-paper/75 text-lg">Începe cu un tip sau cu o intenție: copii, weekend, indoor, outdoor, buget sau oraș.</p>
            </div>
            <div class="flex flex-col sm:flex-row lg:flex-col gap-3">
                <a href="/orase" class="rounded-full bg-paper text-ink px-6 py-4 font-bold text-center hover:bg-ink hover:text-paper transition">Alege orașul</a>
                <a href="/activitati-azi" class="rounded-full border-2 border-paper/60 px-6 py-4 font-bold text-center hover:bg-paper hover:text-ink transition">Activități azi</a>
            </div>
        </div>
    </div>
</section>

</main>

<script>
function categoriesPage(categories) {
    return {
        search: '',
        categories: categories || [],
        norm(s) {
            return (s || '').toString().toLowerCase().normalize('NFD')
                .replace(/[̀-ͯ]/g, '')
                .replace(/[şș]/g, 's').replace(/[ţț]/g, 't')
                .replace(/[ăâ]/g, 'a').replace(/[î]/g, 'i').trim();
        },
        filteredCategories() {
            const q = this.norm(this.search);
            if (! q) return this.categories;
            return this.categories.filter(c => {
                const blob = this.norm(c.title + ' ' + c.desc + ' ' + (c.children || []).map(ch => ch.title).join(' '));
                return blob.includes(q);
            });
        },
    };
}
</script>

<?php include __DIR__ . '/includes/footer.php'; ?>
