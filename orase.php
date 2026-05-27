<?php
/**
 * bilete.online — /orase  (v2 design)
 *
 * Full catalog of cities where activities are available. Pulls real
 * cities from the API (`/cities` — paginated, all flag) and decorates
 * them with the v2 ticket aesthetic + region filter sidebar + city ×
 * intent hubs + A-Z index.
 *
 * When the API has no cities yet, the page falls back to a curated
 * static list so /orase is never empty (SEO + UX).
 */

$pageCacheTTL = 600;
require_once __DIR__ . '/includes/page-cache.php';
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/api.php';
require_once __DIR__ . '/includes/nav-helpers.php';

// =========================================================================
// DATA — fetch all cities + region grouping
// =========================================================================
$citiesResp = api_cached('cities_all', function () {
    // Try the public marketplace endpoint first; fall back to featured-only.
    $resp = api_get('/cities', ['per_page' => 100, 'all' => 1]);
    if (! empty($resp['data']['cities']) || ! empty($resp['data']['items'])) {
        return $resp;
    }
    return api_get('/locations/cities/featured');
}, 600);

$rawCities = $citiesResp['data']['cities']
    ?? $citiesResp['data']['items']
    ?? $citiesResp['data']
    ?? [];

if (! is_array($rawCities)) $rawCities = [];

// Region label heuristic — when the API doesn't include a region field,
// group by county and treat the county as the "region". Cosmetic only —
// the SEO value is still there.
$regionLabelMap = [
    'muntenia'      => 'Muntenia',
    'transilvania'  => 'Transilvania',
    'moldova'       => 'Moldova',
    'banat'         => 'Banat & Crișana',
    'crisana'       => 'Banat & Crișana',
    'banat-crisana' => 'Banat & Crișana',
    'dobrogea'      => 'Dobrogea',
    'oltenia'       => 'Oltenia',
    'maramures'     => 'Maramureș',
    'bucovina'      => 'Bucovina',
];

$cities = [];
foreach ($rawCities as $c) {
    $name = navFlatName($c['name'] ?? '');
    $slug = $c['slug'] ?? '';
    if (! $name || ! $slug) continue;

    $regionRaw = strtolower((string) ($c['region'] ?? $c['region_slug'] ?? $c['county'] ?? ''));
    $regionKey = preg_replace('/[^a-z]+/', '', strtr($regionRaw, ['ă'=>'a','â'=>'a','î'=>'i','ș'=>'s','ț'=>'t']));
    $regionLabel = $regionLabelMap[$regionRaw] ?? $regionLabelMap[$regionKey] ?? ($c['region'] ?? $c['county'] ?? 'România');

    $cities[] = [
        'name'        => $name,
        'slug'        => $slug,
        'url'         => '/' . $slug,
        'region'      => $regionLabel,
        'region_key'  => $regionKey ?: 'romania',
        'count'       => (int) ($c['events_count'] ?? $c['event_count'] ?? $c['activities_count'] ?? 0),
        'image'       => $c['cover_image_url'] ?? $c['image'] ?? null,
        'description' => navFlatName($c['description'] ?? '') ?: 'Activități, experiențe și locuri de vizitat în ' . $name . '.',
        'tags'        => array_slice(is_array($c['tags'] ?? null) ? $c['tags'] : [], 0, 4),
    ];
}

// Sort: featured + most events first
usort($cities, fn ($a, $b) => $b['count'] <=> $a['count']);

// Static fallback so the page is never empty
if (empty($cities)) {
    $cities = [
        ['name' => 'București',   'slug' => 'bucuresti',   'url' => '/bucuresti',   'region' => 'Muntenia',      'region_key' => 'muntenia',     'count' => 0, 'image' => null, 'description' => 'Cel mai mare hub pentru muzee, escape rooms, indoor și evenimente speciale.', 'tags' => []],
        ['name' => 'Brașov',      'slug' => 'brasov',      'url' => '/brasov',      'region' => 'Transilvania',  'region_key' => 'transilvania', 'count' => 0, 'image' => null, 'description' => 'Activități urbane, natură și aventură.', 'tags' => []],
        ['name' => 'Cluj-Napoca', 'slug' => 'cluj-napoca', 'url' => '/cluj-napoca', 'region' => 'Transilvania',  'region_key' => 'transilvania', 'count' => 0, 'image' => null, 'description' => 'Activități pentru studenți, familii și grupuri.', 'tags' => []],
        ['name' => 'Iași',        'slug' => 'iasi',        'url' => '/iasi',        'region' => 'Moldova',       'region_key' => 'moldova',      'count' => 0, 'image' => null, 'description' => 'Cultural, educativ și experiențe locale în nord-est.', 'tags' => []],
        ['name' => 'Timișoara',   'slug' => 'timisoara',   'url' => '/timisoara',   'region' => 'Banat & Crișana','region_key' => 'banatcrisana','count' => 0, 'image' => null, 'description' => 'Experiențe urbane, culturale și de weekend.', 'tags' => []],
        ['name' => 'Constanța',   'slug' => 'constanta',   'url' => '/constanta',   'region' => 'Dobrogea',      'region_key' => 'dobrogea',     'count' => 0, 'image' => null, 'description' => 'Activități de litoral și sezoniere.', 'tags' => []],
        ['name' => 'Sibiu',       'slug' => 'sibiu',       'url' => '/sibiu',       'region' => 'Transilvania',  'region_key' => 'transilvania', 'count' => 0, 'image' => null, 'description' => 'Cetatea + experiențe culturale și outdoor.', 'tags' => []],
        ['name' => 'Oradea',      'slug' => 'oradea',      'url' => '/oradea',      'region' => 'Banat & Crișana','region_key' => 'banatcrisana','count' => 0, 'image' => null, 'description' => 'Activități culturale și familie în vestul țării.', 'tags' => []],
    ];
}

// Quick chips — top 6 by count
$quickCities = array_slice($cities, 0, 6);

// Regions with counts
$regionCounts = [];
foreach ($cities as $c) {
    $key = $c['region_key'];
    $regionCounts[$key] = ($regionCounts[$key] ?? 0) + 1;
}
$regionFilters = [['key' => 'all', 'label' => 'Toate regiunile', 'count' => count($cities)]];
foreach ($cities as $c) {
    $key = $c['region_key'];
    if (in_array($key, array_column($regionFilters, 'key'), true)) continue;
    $regionFilters[] = ['key' => $key, 'label' => $c['region'], 'count' => $regionCounts[$key]];
}

// A-Z grouping
$alphabet = [];
foreach ($cities as $c) {
    $letter = mb_strtoupper(mb_substr($c['name'], 0, 1));
    // Romanian diacritics → ASCII for grouping
    $letter = strtr($letter, ['Ă'=>'A','Â'=>'A','Î'=>'I','Ș'=>'S','Ț'=>'T']);
    $alphabet[$letter][] = ['name' => $c['name'], 'slug' => $c['slug']];
}
ksort($alphabet);

// City intent hubs (with top city as anchor)
$topCitySlug = $cities[0]['slug'] ?? 'bucuresti';
$topCityName = $cities[0]['name'] ?? 'București';
$cityIntentHubs = [
    ['kicker' => 'COPII',    'title' => "Activități copii în {$topCityName}",  'description' => 'Muzee interactive, parcuri, ateliere și experiențe pentru familie.', 'url' => "/{$topCitySlug}/activitati-copii"],
    ['kicker' => 'WEEKEND',  'title' => "Weekend în {$topCityName}",            'description' => 'Idei rapide pentru weekend — copii, grupuri, cupluri.',              'url' => "/{$topCitySlug}/activitati-weekend"],
    ['kicker' => 'INDOOR',   'title' => "Indoor în {$topCityName}",             'description' => 'Activități pentru zile reci sau ploioase.',                          'url' => "/{$topCitySlug}/activitati-indoor"],
    ['kicker' => 'BUGET',    'title' => "Sub 50 lei în {$topCityName}",         'description' => 'Activități accesibile, fără să golești portofelul.',                  'url' => "/{$topCitySlug}/activitati-sub-50-lei"],
    ['kicker' => 'ESCAPE',   'title' => "Escape rooms în {$topCityName}",       'description' => 'Mister, puzzle-uri și provocări contra cronometru.',                 'url' => "/{$topCitySlug}/escape-rooms"],
    ['kicker' => 'MUZEE',    'title' => "Muzee în {$topCityName}",              'description' => 'Artă, istorie, știință și expoziții temporare.',                      'url' => "/{$topCitySlug}/muzee"],
];

// SEO
$pageTitleRaw    = 'Toate orașele — bilete.online';
$pageDescription = 'Explorează activități în orașe din România: București, Brașov, Cluj, Iași, Timișoara, Constanța, Sibiu și multe altele. Bilete pentru escape rooms, muzee, parcuri și experiențe locale.';
$canonicalUrl    = SITE_URL . '/orase';
$currentPage     = 'orase';
$cssBundle       = 'listing';

$breadcrumbs = [
    ['name' => 'Acasă', 'url' => SITE_URL . '/'],
    ['name' => 'Orașe', 'url' => $canonicalUrl],
];

$structuredData = [[
    '@context' => 'https://schema.org',
    '@type' => 'CollectionPage',
    'name' => $pageTitleRaw,
    'description' => $pageDescription,
    'url' => $canonicalUrl,
    'inLanguage' => 'ro-RO',
    'mainEntity' => [
        '@type' => 'ItemList',
        'numberOfItems' => count($cities),
        'itemListElement' => array_map(fn ($i, $c) => [
            '@type' => 'ListItem',
            'position' => $i + 1,
            'name' => $c['name'],
            'url' => SITE_URL . '/' . $c['slug'],
        ], array_keys($cities), $cities),
    ],
]];

include __DIR__ . '/includes/head.php';
include __DIR__ . '/includes/header.php';
?>

<main x-data="citiesPage(<?= htmlspecialchars(json_encode([
    'cities'        => $cities,
    'regionFilters' => $regionFilters,
    'quickCities'   => $quickCities,
]), ENT_QUOTES) ?>)">

<!-- HERO -->
<section class="relative overflow-hidden border-b-2 border-ink">
    <div class="absolute inset-0 bg-[radial-gradient(circle_at_82%_14%,rgba(232,69,39,.24),transparent_30%),radial-gradient(circle_at_16%_72%,rgba(30,74,61,.22),transparent_34%),radial-gradient(circle_at_50%_44%,rgba(44,95,138,.15),transparent_30%)]"></div>
    <div class="relative max-w-7xl mx-auto px-4 sm:px-6 pt-14 sm:pt-20 pb-16 sm:pb-24">
        <nav class="flex items-center gap-2 text-sm text-ink-soft" aria-label="Breadcrumb">
            <a href="/" class="hover:text-vermilion">Acasă</a><span>/</span><span class="text-ink">Orașe</span>
        </nav>
        <div class="mt-8 grid lg:grid-cols-[1fr_.9fr] gap-12 items-center">
            <div>
                <p class="stamp inline-flex px-3 py-1 text-xs font-mono tracking-[.18em] text-vermilion bg-paper/70">ORAȘE · ACTIVITĂȚI LOCALE · EXPERIENȚE</p>
                <h1 class="mt-6 font-display text-6xl sm:text-8xl font-bold leading-[.82]">Alege orașul. Găsește ce ai de făcut.</h1>
                <p class="mt-6 max-w-2xl text-xl sm:text-2xl text-ink-soft leading-relaxed">
                    Explorează activități locale: escape rooms, muzee, parcuri, natură, ateliere, tururi și experiențe pentru copii, familie sau grupuri.
                </p>
                <div class="mt-8 max-w-2xl">
                    <label class="sr-only" for="city-search">Caută oraș</label>
                    <div class="relative">
                        <input id="city-search" type="text" class="field text-lg pr-14" x-model="search" placeholder="Caută: București, Brașov, Cluj, Iași...">
                        <span class="absolute right-4 top-1/2 -translate-y-1/2 text-ink-soft">
                            <svg viewBox="0 0 24 24" class="w-6 h-6" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="7"/><path d="m20 20-3.5-3.5"/></svg>
                        </span>
                    </div>
                </div>
                <div class="mt-6 flex flex-wrap gap-2">
                    <?php foreach ($quickCities as $qc): ?>
                        <a href="<?= htmlspecialchars($qc['url'], ENT_QUOTES) ?>" class="rounded-full bg-paper/70 border border-ink/10 px-4 py-2 font-bold hover:bg-ink hover:text-paper transition"><?= htmlspecialchars($qc['name']) ?></a>
                    <?php endforeach; ?>
                </div>
            </div>
            <div class="relative min-h-[420px] hidden lg:block">
                <div class="absolute inset-x-8 top-10 bottom-8 rounded-[2.4rem] bg-ink rotate-[2deg] shadow-deep"></div>
                <div class="absolute top-0 left-0 right-0 mx-auto max-w-[540px] rounded-[2rem] border-2 border-ink bg-paper shadow-deep overflow-hidden rotate-[-2deg]">
                    <div class="p-6 sm:p-8">
                        <p class="font-mono text-xs tracking-[.18em] text-ink-soft">LOCAL DISCOVERY</p>
                        <h2 class="mt-3 font-display text-3xl font-bold leading-none">Orașul devine hub.</h2>
                        <div class="mt-6 relative h-64 rounded-3xl bg-paper-2 border border-ink/10 overflow-hidden">
                            <svg viewBox="0 0 500 300" class="absolute inset-0 w-full h-full" aria-hidden="true">
                                <path d="M80 220 C150 90, 255 260, 330 115 S450 95, 430 220" fill="none" stroke="#1B1714" stroke-width="3"/>
                                <circle cx="80" cy="220" r="10" fill="#E84527"/>
                                <circle cx="190" cy="118" r="10" fill="#1E4A3D"/>
                                <circle cx="330" cy="115" r="10" fill="#DA9A33"/>
                                <circle cx="430" cy="220" r="10" fill="#2C5F8A"/>
                            </svg>
                            <?php foreach (array_slice($cities, 0, 3) as $i => $c):
                                $pos = ['left-6 top-6', 'right-6 top-16', 'left-16 bottom-7'][$i];
                            ?>
                                <a href="<?= htmlspecialchars($c['url'], ENT_QUOTES) ?>" class="absolute <?= $pos ?> rounded-2xl bg-paper border border-ink/10 p-3 hover:border-ink transition">
                                    <p class="font-bold"><?= htmlspecialchars($c['name']) ?></p>
                                    <p class="text-xs text-ink-soft"><?= $c['count'] > 0 ? $c['count'].' activități' : 'descoperă' ?></p>
                                </a>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- POPULAR + FILTER -->
<section class="max-w-7xl mx-auto px-4 sm:px-6 py-12">
    <div class="grid lg:grid-cols-[300px_1fr] gap-8 items-start">
        <aside class="lg:sticky lg:top-28">
            <div class="rounded-[2rem] border-2 border-ink bg-paper p-5 shadow-ticket">
                <p class="font-mono text-xs tracking-[.18em] text-ink-soft">REGIUNI</p>
                <div class="mt-4 space-y-2">
                    <template x-for="filter in regionFilters" :key="filter.key">
                        <button @click="activeRegion=filter.key" :class="activeRegion===filter.key ? 'bg-ink text-paper' : 'bg-paper-2 text-ink hover:bg-ink/5'" class="w-full rounded-2xl px-4 py-3 text-left font-bold transition flex items-center justify-between gap-3">
                            <span x-text="filter.label"></span>
                            <span class="text-xs opacity-60" x-text="filter.count"></span>
                        </button>
                    </template>
                </div>
                <div class="mt-5 rounded-2xl bg-mint border border-forest/20 p-4">
                    <p class="font-bold text-forest">Structură locală</p>
                    <p class="mt-1 text-sm text-ink-soft">Fiecare oraș are pagini pentru categorii, public, buget, vreme și timp: azi, weekend, copii, indoor, sub 50 lei.</p>
                </div>
            </div>
        </aside>

        <section>
            <div class="flex flex-col sm:flex-row sm:items-end sm:justify-between gap-4">
                <div>
                    <p class="font-mono text-xs tracking-[.18em] text-ink-soft">ORAȘE</p>
                    <h2 class="mt-2 font-display text-5xl font-bold leading-none" x-text="currentRegionTitle()"></h2>
                </div>
                <p class="text-ink-soft" x-text="filteredCities().length + ' din <?= count($cities) ?> orașe'"></p>
            </div>

            <div class="mt-6 grid md:grid-cols-2 xl:grid-cols-3 gap-5">
                <template x-for="city in filteredCities()" :key="city.slug">
                    <article class="group rounded-[2rem] border-2 border-ink bg-paper overflow-hidden shadow-ticket hover:-translate-y-1 transition">
                        <a :href="city.url" class="block">
                            <div class="relative h-52 overflow-hidden bg-ink">
                                <img x-show="city.image" :src="city.image" :alt="'Imagine pentru ' + city.name" class="w-full h-full object-cover opacity-85 group-hover:scale-105 transition duration-500" loading="lazy" onerror="this.style.display='none'">
                                <div class="absolute inset-0 bg-gradient-to-t from-ink/85 via-ink/10 to-transparent"></div>
                                <div class="absolute inset-0 grid place-items-center" x-show="!city.image">
                                    <span class="font-display text-7xl font-bold text-paper/30" x-text="city.name.charAt(0).toUpperCase()"></span>
                                </div>
                                <div class="absolute left-4 bottom-4 right-4">
                                    <p class="text-paper/65 font-mono text-[10px] tracking-[.18em]" x-text="city.region"></p>
                                    <h3 class="font-display text-4xl font-bold text-paper leading-none" x-text="city.name"></h3>
                                </div>
                                <span x-show="city.count > 0" class="absolute top-4 right-4 rounded-full bg-paper text-ink px-3 py-1 text-xs font-bold" x-text="city.count + ' activități'"></span>
                            </div>
                        </a>
                        <div class="p-5">
                            <p class="text-ink-soft leading-relaxed" x-text="city.description"></p>
                            <div class="mt-5 grid grid-cols-2 gap-2 text-sm font-bold">
                                <a :href="city.url + '/activitati-copii'" class="rounded-xl bg-mint text-forest px-3 py-2 hover:bg-forest hover:text-paper transition text-center">Copii</a>
                                <a :href="city.url + '/activitati-weekend'" class="rounded-xl bg-paper-2 px-3 py-2 hover:bg-ink hover:text-paper transition text-center">Weekend</a>
                                <a :href="city.url + '/activitati-indoor'" class="rounded-xl bg-paper-2 px-3 py-2 hover:bg-ink hover:text-paper transition text-center">Indoor</a>
                                <a :href="city.url" class="rounded-xl bg-vermilion text-paper px-3 py-2 hover:bg-vermilion-d transition text-center">Vezi orașul</a>
                            </div>
                        </div>
                    </article>
                </template>
            </div>
        </section>
    </div>
</section>

<!-- CITY × INTENT HUBS -->
<section class="border-y-2 border-ink bg-paper-2/65">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 py-16 sm:py-20">
        <div class="grid lg:grid-cols-[.85fr_1.15fr] gap-10 items-start">
            <div class="lg:sticky lg:top-28">
                <p class="stamp inline-flex px-3 py-1 text-xs font-mono tracking-[.18em] text-vermilion">CITY + INTENT</p>
                <h2 class="mt-5 font-display text-5xl sm:text-6xl font-bold leading-[.9]">Cele mai bune pagini locale nu sunt doar „orașe".</h2>
                <p class="mt-5 text-lg text-ink-soft leading-relaxed">Un utilizator caută mai specific: copii în Brașov, escape rooms în București, indoor în Cluj, ce să faci azi în Iași.</p>
            </div>
            <div class="grid sm:grid-cols-2 gap-4">
                <?php foreach ($cityIntentHubs as $hub): ?>
                    <a href="<?= htmlspecialchars($hub['url'], ENT_QUOTES) ?>" class="rounded-3xl border-2 border-ink/15 bg-paper p-5 hover:border-ink hover:-translate-y-1 transition">
                        <p class="font-mono text-xs tracking-[.18em] text-vermilion"><?= htmlspecialchars($hub['kicker']) ?></p>
                        <h3 class="mt-2 font-display text-3xl font-bold"><?= htmlspecialchars($hub['title']) ?></h3>
                        <p class="mt-2 text-ink-soft"><?= htmlspecialchars($hub['description']) ?></p>
                    </a>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
</section>

<!-- A-Z INDEX -->
<section class="border-y-2 border-ink bg-ink text-paper">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 py-16 sm:py-20">
        <div class="max-w-3xl">
            <p class="stamp inline-flex px-3 py-1 text-xs font-mono tracking-[.18em] text-ochre">INDEX ORAȘE</p>
            <h2 class="mt-5 font-display text-5xl sm:text-6xl font-bold leading-[.9]">Index curat pentru crawl și utilizatori.</h2>
            <p class="mt-5 text-lg text-paper/60 leading-relaxed">Pe lângă cardurile vizuale, structură textuală scalabilă pentru linkuri interne.</p>
        </div>
        <div class="mt-10 grid md:grid-cols-3 lg:grid-cols-4 gap-5">
            <?php foreach ($alphabet as $letter => $citiesInLetter): ?>
                <div class="rounded-3xl bg-paper/10 border border-paper/10 p-5">
                    <p class="font-display text-4xl font-bold text-ochre"><?= htmlspecialchars($letter) ?></p>
                    <div class="mt-3 space-y-1">
                        <?php foreach ($citiesInLetter as $c): ?>
                            <a href="/<?= htmlspecialchars($c['slug'], ENT_QUOTES) ?>" class="block py-1.5 text-paper/70 hover:text-ochre"><?= htmlspecialchars($c['name']) ?></a>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<!-- FAQ -->
<section class="max-w-5xl mx-auto px-4 sm:px-6 py-16 sm:py-20" x-data="{open:0}">
    <div class="text-center max-w-3xl mx-auto">
        <p class="stamp inline-flex px-3 py-1 text-xs font-mono tracking-[.18em] text-vermilion">FAQ</p>
        <h2 class="mt-5 font-display text-5xl sm:text-6xl font-bold leading-[.9]">Cum aleg orașul potrivit?</h2>
    </div>
    <div class="mt-10 space-y-3">
        <?php $faqs = [
            ['Cum sunt selectate orașele afișate?', 'Afișăm orașele cu activități listate plus o curatare manuală pentru hub-urile populare. Pe măsură ce apar locații noi într-un oraș, acesta urcă automat în listă.'],
            ['Ce găsesc pe pagina unui oraș?', 'Activități populare, categorii locale (escape rooms, muzee, parcuri), opțiuni pentru copii, weekend, indoor și ghiduri editoriale.'],
            ['Cum funcționează combinațiile oraș + categorie?', 'Pagini ca /brasov/escape-rooms sau /cluj-napoca/activitati-copii sunt construite automat din taxonomia comună a platformei.'],
            ['Pot vedea activități aproape de mine?', 'Da, există o pagină dedicată /activitati-aproape-de-mine bazată pe locația GPS dacă o permiți, altfel folosește orașul tău preferat.'],
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
                <p class="font-mono text-xs tracking-[.2em] text-paper/60">LOCAL DISCOVERY</p>
                <h2 class="mt-3 font-display text-5xl sm:text-6xl font-bold leading-[.9]">Începe cu orașul. Apoi alege experiența.</h2>
                <p class="mt-4 max-w-2xl text-paper/75 text-lg">Fiecare oraș poate fi un hub pentru activități, categorii, locații, ghiduri și pagini SEO locale.</p>
            </div>
            <div class="flex flex-col sm:flex-row lg:flex-col gap-3">
                <a href="/categorii" class="rounded-full bg-paper text-ink px-6 py-4 font-bold text-center hover:bg-ink hover:text-paper transition">Vezi categorii</a>
                <a href="/locatii" class="rounded-full border-2 border-paper/60 px-6 py-4 font-bold text-center hover:bg-paper hover:text-ink transition">Vezi locații</a>
            </div>
        </div>
    </div>
</section>

</main>

<script>
function citiesPage(data) {
    return {
        search: '',
        activeRegion: 'all',
        cities: data.cities || [],
        regionFilters: data.regionFilters || [],
        quickCities: data.quickCities || [],
        currentRegionTitle() {
            const found = this.regionFilters.find(f => f.key === this.activeRegion);
            return found ? found.label : 'Toate orașele';
        },
        filteredCities() {
            const q = (this.search || '').toLowerCase().trim();
            return this.cities.filter(c => {
                const matchesRegion = this.activeRegion === 'all' || c.region_key === this.activeRegion;
                const matchesSearch = !q || (c.name + ' ' + c.region + ' ' + c.description).toLowerCase().includes(q);
                return matchesRegion && matchesSearch;
            });
        },
    };
}
</script>

<?php include __DIR__ . '/includes/footer.php'; ?>
