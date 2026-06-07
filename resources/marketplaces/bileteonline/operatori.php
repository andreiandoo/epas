<?php
/**
 * bilete.online — /operatori
 *
 * Listing of OPERATORS — the companies (organizers) that sell activities on the
 * platform. Replaces the old "Locații" listing (venues) for this activity-first
 * marketplace. Pulls real organizers from /marketplace-events/organizers and
 * falls back to a small static list so the page is never empty. Single operator
 * profiles live at /operator/{slug} (served by public.php).
 */

$pageCacheTTL = 600;
require_once __DIR__ . '/includes/page-cache.php';
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/api.php';
require_once __DIR__ . '/includes/nav-helpers.php';

// =========================================================================
// DATA — fetch operators (organizers)
// =========================================================================
$opsResp = api_cached('operators_all', function () {
    return api_get('/marketplace-events/organizers', ['per_page' => 100, 'sort' => 'name']);
}, 600);

$rawOps = $opsResp['data']['items']
    ?? $opsResp['data']['data']
    ?? (is_array($opsResp['data'] ?? null) ? $opsResp['data'] : []);
if (! is_array($rawOps)) $rawOps = [];

$operators = [];
foreach ($rawOps as $o) {
    $name = navFlatName($o['name'] ?? '');
    $slug = $o['slug'] ?? '';
    if (! $name || ! $slug) continue;

    $cityName = navFlatName(is_array($o['city'] ?? null) ? ($o['city']['name'] ?? '') : ($o['city'] ?? ''));
    $citySlug = is_array($o['city'] ?? null) ? ($o['city']['slug'] ?? '') : '';

    $operators[] = [
        'name'        => $name,
        'slug'        => $slug,
        'city'        => $cityName ?: 'România',
        'citySlug'    => $citySlug ?: '',
        'logo'        => $o['logo'] ?? null,
        'verified'    => ! empty($o['verified']),
        'count'       => (int) ($o['activities_count'] ?? $o['event_count'] ?? 0),
        'description' => navFlatName($o['description'] ?? $o['short_description'] ?? '') ?: 'Operator partener bilete.online cu activități disponibile online.',
    ];
}

// Static fallback if API empty (keeps the page presentable pre-launch).
if (empty($operators)) {
    $operators = [
        ['name' => 'Mystery Rooms București', 'slug' => 'mystery-rooms-bucuresti', 'city' => 'București', 'citySlug' => 'bucuresti', 'logo' => null, 'verified' => true, 'count' => 4, 'description' => 'Operator de escape rooms tematice pentru grupuri mici și mari în centrul Bucureștiului.'],
        ['name' => 'Prestige Tours Romania', 'slug' => 'prestige-tours-romania', 'city' => 'București', 'citySlug' => 'bucuresti', 'logo' => null, 'verified' => true, 'count' => 9, 'description' => 'Tururi ghidate, excursii de o zi și experiențe culturale în București, Sinaia, Bran și Brașov.'],
        ['name' => 'Atelier Ceramică Cluj', 'slug' => 'atelier-ceramica-cluj', 'city' => 'Cluj-Napoca', 'citySlug' => 'cluj-napoca', 'logo' => null, 'verified' => false, 'count' => 3, 'description' => 'Ateliere de ceramică pentru începători și avansați, în grupuri mici.'],
    ];
}

// Unique cities (for the filter).
$citiesList = array_values(array_unique(array_filter(array_map(fn ($o) => $o['city'], $operators))));
sort($citiesList);

// SEO
$pageTitleRaw    = 'Operatori — companii care vând activități · ' . SITE_NAME;
$pageDescription = 'Descoperă operatorii parteneri bilete.online: companiile care organizează și vând activități, experiențe și tururi. Profil dedicat, activități listate, bilete cu QR pe email.';
$canonicalUrl    = SITE_URL . '/operatori';
$currentPage     = 'operatori';
$cssBundle       = 'listing';

$breadcrumbs = [
    ['name' => 'Acasă', 'url' => SITE_URL . '/'],
    ['name' => 'Operatori', 'url' => $canonicalUrl],
];

$structuredData = [[
    '@context'   => 'https://schema.org',
    '@type'      => 'CollectionPage',
    'name'       => $pageTitleRaw,
    'description' => $pageDescription,
    'url'        => $canonicalUrl,
    'inLanguage' => 'ro-RO',
]];

include __DIR__ . '/includes/head.php';
include __DIR__ . '/includes/header.php';
?>

<main x-data="operatorsPage(<?= htmlspecialchars(json_encode([
    'operators' => $operators,
    'cities'    => $citiesList,
]), ENT_QUOTES) ?>)">

<!-- HERO -->
<section class="relative overflow-hidden border-b-2 border-ink">
    <div class="absolute inset-0 bg-[radial-gradient(circle_at_82%_14%,rgba(232,69,39,.24),transparent_30%),radial-gradient(circle_at_16%_72%,rgba(30,74,61,.22),transparent_34%),radial-gradient(circle_at_50%_44%,rgba(218,154,51,.18),transparent_30%)]"></div>
    <div class="relative max-w-7xl mx-auto px-4 sm:px-6 pt-14 sm:pt-20 pb-16 sm:pb-24">
        <nav class="flex items-center gap-2 text-sm text-ink-soft" aria-label="Breadcrumb">
            <a href="/" class="hover:text-vermilion">Acasă</a><span>/</span><span class="text-ink">Operatori</span>
        </nav>
        <div class="mt-8 grid lg:grid-cols-[1fr_.92fr] gap-12 items-center">
            <div>
                <p class="inline-flex px-3 py-1 text-xs font-mono tracking-[.18em] text-vermilion bg-paper/70">OPERATORI · COMPANII · ACTIVITĂȚI</p>
                <h1 class="mt-6 font-display text-6xl sm:text-8xl font-bold leading-[.82]">Cine organizează experiențele.</h1>
                <p class="mt-6 max-w-2xl text-xl sm:text-2xl text-ink-soft leading-relaxed">
                    Operatorii sunt companiile care creează și vând activitățile de pe platformă: escape rooms, tururi ghidate, ateliere, experiențe și multe altele. Fiecare are profil dedicat cu activitățile lui.
                </p>
                <div class="mt-8 max-w-2xl">
                    <label class="sr-only" for="operator-search">Caută operator</label>
                    <div class="relative">
                        <input id="operator-search" type="text" class="field text-lg pr-14" x-model="search" placeholder="Caută: nume operator, oraș...">
                        <span class="absolute right-4 top-1/2 -translate-y-1/2 text-ink-soft">
                            <svg viewBox="0 0 24 24" class="w-6 h-6" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="7"/><path d="m20 20-3.5-3.5"/></svg>
                        </span>
                    </div>
                </div>
                <div class="mt-6 flex flex-wrap gap-2">
                    <button @click="activeCity='all'; onlyVerified=false" class="rounded-full bg-paper/70 border border-ink/10 px-4 py-2 font-bold hover:bg-ink hover:text-paper transition">Toți operatorii</button>
                    <button @click="onlyVerified=true" class="rounded-full bg-paper/70 border border-ink/10 px-4 py-2 font-bold hover:bg-ink hover:text-paper transition">Verificați</button>
                </div>
            </div>
            <div class="relative min-h-[420px] hidden lg:block">
                <div class="absolute inset-x-8 top-10 bottom-8 rounded-[2.4rem] bg-ink rotate-[-2deg] shadow-deep"></div>
                <div class="absolute top-0 left-0 right-0 mx-auto max-w-[540px] bg-paper border-2 border-ink rounded-[2rem] overflow-hidden shadow-deep rotate-[2deg]">
                    <div class="p-6 sm:p-8">
                        <p class="font-mono text-xs tracking-[.18em] text-ink-soft">OPERATOR GRAPH</p>
                        <h2 class="mt-3 font-display text-3xl font-bold leading-none">Un operator poate avea mai multe activități.</h2>
                        <div class="mt-7 rounded-3xl bg-paper-2 border border-ink/10 p-5">
                            <div class="flex items-center gap-3">
                                <span class="grid place-items-center w-12 h-12 rounded-2xl bg-vermilion text-paper font-display text-xl font-bold"><?= htmlspecialchars(mb_substr($operators[0]['name'] ?? 'O', 0, 1)) ?></span>
                                <div>
                                    <p class="font-display text-2xl font-bold"><?= htmlspecialchars($operators[0]['name'] ?? 'Operator') ?></p>
                                    <p class="text-sm text-ink-soft"><?= htmlspecialchars($operators[0]['city'] ?? '') ?></p>
                                </div>
                            </div>
                            <div class="mt-5 grid gap-3">
                                <div class="rounded-2xl bg-paper border border-ink/10 p-4 flex justify-between gap-3"><span class="font-bold">Activitate 1</span><span class="text-forest font-bold">bilete</span></div>
                                <div class="rounded-2xl bg-paper border border-ink/10 p-4 flex justify-between gap-3"><span class="font-bold">Activitate 2</span><span class="text-forest font-bold">bilete</span></div>
                                <div class="rounded-2xl bg-paper border border-ink/10 p-4 flex justify-between gap-3"><span class="font-bold">Activitate 3</span><span class="text-ochre font-bold">soon</span></div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- LIST + FILTER -->
<section class="max-w-7xl mx-auto px-4 sm:px-6 py-12">
    <div class="grid lg:grid-cols-[300px_1fr] gap-8 items-start">
        <aside class="lg:sticky lg:top-28">
            <div class="rounded-[2rem] border-2 border-ink bg-paper p-5 shadow-ticket">
                <p class="font-mono text-xs tracking-[.18em] text-ink-soft">FILTRARE OPERATORI</p>
                <label class="block mt-4">
                    <span class="block mb-1.5 text-sm font-bold">Oraș</span>
                    <select class="field" x-model="activeCity">
                        <option value="all">Toate orașele</option>
                        <template x-for="city in cities" :key="city">
                            <option :value="city" x-text="city"></option>
                        </template>
                    </select>
                </label>
                <label class="mt-4 flex items-center gap-3 cursor-pointer">
                    <input type="checkbox" x-model="onlyVerified" class="h-5 w-5 accent-vermilion">
                    <span class="font-bold">Doar operatori verificați</span>
                </label>
                <div class="mt-5 rounded-2xl bg-mint border border-forest/20 p-4">
                    <p class="font-bold text-forest">Ești operator?</p>
                    <p class="mt-1 text-sm text-ink-soft">Ai activități de vândut? Poți avea pagină dedicată, activități listate, bilete QR și dashboard.</p>
                    <a href="/devino-partener" class="mt-3 inline-flex font-bold text-forest underline-wobble">Devino partener</a>
                </div>
            </div>
        </aside>

        <section>
            <div class="flex flex-col sm:flex-row sm:items-end sm:justify-between gap-4">
                <div>
                    <p class="font-mono text-xs tracking-[.18em] text-ink-soft">OPERATORI</p>
                    <h2 class="mt-2 font-display text-5xl font-bold leading-none">Operatori parteneri</h2>
                </div>
                <p class="text-ink-soft" x-text="filteredOperators().length + ' din <?= count($operators) ?> operatori'"></p>
            </div>

            <div class="mt-6 grid md:grid-cols-2 xl:grid-cols-3 gap-5">
                <template x-for="op in filteredOperators()" :key="op.slug">
                    <article class="group rounded-[2rem] border-2 border-ink bg-paper overflow-hidden shadow-ticket hover:-translate-y-1 transition">
                        <a :href="'/operator/' + op.slug" class="block p-5">
                            <div class="flex items-center gap-4">
                                <template x-if="op.logo">
                                    <img :src="op.logo" :alt="op.name" class="h-16 w-16 rounded-2xl object-cover border-2 border-ink/10" loading="lazy" onerror="this.style.display='none'">
                                </template>
                                <template x-if="!op.logo">
                                    <span class="grid h-16 w-16 place-items-center rounded-2xl bg-ink text-paper font-display text-2xl font-bold" x-text="op.name.charAt(0)"></span>
                                </template>
                                <div class="min-w-0">
                                    <div class="flex items-center gap-2">
                                        <h3 class="font-display text-2xl font-bold leading-none group-hover:text-vermilion truncate" x-text="op.name"></h3>
                                        <span x-show="op.verified" class="inline-flex items-center gap-1 rounded-full bg-mint px-2 py-0.5 text-[10px] font-bold text-forest" title="Operator verificat">✓ verificat</span>
                                    </div>
                                    <p class="mt-1 text-sm text-ink-soft" x-text="op.city"></p>
                                </div>
                            </div>
                        </a>
                        <div class="px-5 pb-5">
                            <p class="text-ink-soft leading-relaxed line-clamp-3" x-text="op.description"></p>
                            <div class="mt-5 flex items-center justify-between gap-3">
                                <a :href="'/operator/' + op.slug" class="font-bold text-vermilion underline-wobble">Vezi operatorul</a>
                                <span x-show="op.count > 0" class="rounded-full bg-paper-2 border border-ink/10 px-3 py-1 text-xs font-bold" x-text="op.count + ' activități'"></span>
                            </div>
                        </div>
                    </article>
                </template>
            </div>

            <div x-show="filteredOperators().length === 0" class="mt-10 rounded-[2rem] border-2 border-ink bg-paper-2 p-10 text-center">
                <p class="font-display text-3xl font-bold">Niciun operator găsit</p>
                <p class="mt-2 text-ink-soft">Încearcă alt oraș sau șterge filtrele.</p>
            </div>
        </section>
    </div>
</section>

<!-- WHAT IS AN OPERATOR -->
<section class="border-y-2 border-ink bg-paper-2/65">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 py-16 sm:py-20">
        <div class="grid lg:grid-cols-[.85fr_1.15fr] gap-10 items-start">
            <div class="lg:sticky lg:top-28">
                <p class="inline-flex px-3 py-1 text-xs font-mono tracking-[.18em] text-vermilion">PAGINĂ OPERATOR</p>
                <h2 class="mt-5 font-display text-5xl sm:text-6xl font-bold leading-[.9]">Un operator e mai mult decât un nume.</h2>
                <p class="mt-5 text-lg text-ink-soft leading-relaxed">Pagina unui operator arată cine este, ce activități oferă, unde operează, review-urile clienților și cum poți rezerva.</p>
            </div>
            <div class="grid sm:grid-cols-2 gap-4">
                <article class="rounded-3xl border-2 border-ink/15 bg-paper-2/70 p-6"><p class="font-mono text-xs tracking-[.18em] text-vermilion">01</p><h3 class="mt-2 font-display text-3xl font-bold">Identitate</h3><p class="mt-2 text-ink-soft">Nume, logo, descriere, orașe, încredere.</p></article>
                <article class="rounded-3xl border-2 border-ink/15 bg-mint p-6"><p class="font-mono text-xs tracking-[.18em] text-forest">02</p><h3 class="mt-2 font-display text-3xl font-bold">Activități</h3><p class="mt-2 text-ink-soft">Lista activităților, bilete, prețuri, disponibilitate.</p></article>
                <article class="rounded-3xl border-2 border-ink/15 bg-paper-2/70 p-6"><p class="font-mono text-xs tracking-[.18em] text-vermilion">03</p><h3 class="mt-2 font-display text-3xl font-bold">Review-uri</h3><p class="mt-2 text-ink-soft">Recenzii verificate de la clienți care au participat.</p></article>
                <article class="rounded-3xl border-2 border-ink/15 bg-ink text-paper p-6"><p class="font-mono text-xs tracking-[.18em] text-ochre">04</p><h3 class="mt-2 font-display text-3xl font-bold">Contact</h3><p class="mt-2 text-paper/60">Întrebări, politici, informații pentru grupuri.</p></article>
            </div>
        </div>
    </div>
</section>

<!-- FINAL CTA -->
<section class="max-w-7xl mx-auto px-4 sm:px-6 py-16 sm:py-20">
    <div class="relative overflow-hidden rounded-[2rem] border-2 border-ink bg-vermilion text-paper p-8 sm:p-12">
        <div class="absolute inset-0 opacity-15" style="background-image:radial-gradient(#fff 1px,transparent 1.4px);background-size:15px 15px"></div>
        <div class="relative grid lg:grid-cols-[1fr_auto] gap-8 items-center">
            <div>
                <p class="font-mono text-xs tracking-[.2em] text-paper/60">OPERATORI</p>
                <h2 class="mt-3 font-display text-5xl sm:text-6xl font-bold leading-[.9]">Ești operator? Vinde activități online.</h2>
                <p class="mt-4 max-w-2xl text-paper/75 text-lg">Pagină dedicată, activități, bilete QR, dashboard, scanner check-in și rapoarte.</p>
            </div>
            <div class="flex flex-col sm:flex-row lg:flex-col gap-3">
                <a href="/devino-partener" class="rounded-full bg-paper text-ink px-6 py-4 font-bold text-center hover:bg-ink hover:text-paper transition">Devino partener</a>
                <a href="/organizator/inregistrare" class="rounded-full border-2 border-paper/60 px-6 py-4 font-bold text-center hover:bg-paper hover:text-ink transition">Solicită cont</a>
            </div>
        </div>
    </div>
</section>

</main>

<script>
function operatorsPage(data) {
    return {
        search: '',
        activeCity: 'all',
        onlyVerified: false,
        operators: data.operators || [],
        cities: data.cities || [],
        norm(s) {
            return (s || '').toString().toLowerCase().normalize('NFD')
                .replace(/[̀-ͯ]/g, '')
                .replace(/[şș]/g, 's').replace(/[ţț]/g, 't')
                .replace(/[ăâ]/g, 'a').replace(/[î]/g, 'i').trim();
        },
        filteredOperators() {
            const q = this.norm(this.search);
            return this.operators.filter(o => {
                const matchesCity = this.activeCity === 'all' || o.city === this.activeCity;
                const matchesVerified = !this.onlyVerified || o.verified;
                const matchesSearch = !q || this.norm(o.name + ' ' + o.city + ' ' + o.description).includes(q);
                return matchesCity && matchesVerified && matchesSearch;
            });
        },
    };
}
</script>

<?php include __DIR__ . '/includes/footer.php'; ?>
