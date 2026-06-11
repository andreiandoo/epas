<?php
/**
 * bilete.online — /locatii  (v2 design)
 *
 * Full catalog of locations / operators that sell activities on the
 * platform. Pulls real venues from the marketplace API and falls back
 * to a curated static list if the API is empty (so /locatii never
 * shows an empty page).
 */

$pageCacheTTL = 600;
require_once __DIR__ . '/includes/page-cache.php';
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/api.php';
require_once __DIR__ . '/includes/nav-helpers.php';

// =========================================================================
// DATA — fetch venues
// =========================================================================
$venuesResp = api_cached('venues_all', function () {
    $resp = api_get('/venues', ['per_page' => 100]);
    if (! empty($resp['data']['venues']) || ! empty($resp['data']['items'])) {
        return $resp;
    }
    return ['data' => []];
}, 600);

$rawVenues = $venuesResp['data']['venues']
    ?? $venuesResp['data']['items']
    ?? (is_array($venuesResp['data'] ?? null) ? $venuesResp['data'] : []);
if (! is_array($rawVenues)) $rawVenues = [];

$typeLabels = [
    'escape_room' => 'Escape room',
    'escape-room' => 'Escape room',
    'museum'      => 'Muzeu',
    'muzeu'       => 'Muzeu',
    'park'        => 'Parc',
    'parc'        => 'Parc',
    'adventure_park' => 'Parc aventură',
    'workshop'    => 'Atelier',
    'tour'        => 'Tur ghidat',
    'aquarium'    => 'Acvariu',
    'zoo'         => 'Grădină zoologică',
    'cave'        => 'Peșteră',
    'leisure_venue' => 'Centru de agrement',
];

$locations = [];
foreach ($rawVenues as $v) {
    $name = navFlatName($v['name'] ?? '');
    $slug = $v['slug'] ?? '';
    if (! $name || ! $slug) continue;

    $type = $v['type'] ?? $v['venue_type'] ?? '';
    $typeLabel = $typeLabels[$type] ?? ucfirst(str_replace('_', ' ', (string) $type)) ?: 'Locație';
    $cityName = navFlatName($v['city']['name'] ?? $v['city_name'] ?? '');
    $citySlug = $v['city']['slug'] ?? $v['city_slug'] ?? '';

    $locations[] = [
        'name'        => $name,
        'slug'        => $slug,
        'type'        => $type ?: 'all',
        'typeLabel'   => $typeLabel,
        'city'        => $cityName ?: 'România',
        'citySlug'    => $citySlug ?: '',
        'image'       => $v['cover_image_url'] ?? $v['image'] ?? null,
        'rating'      => isset($v['rating']) && $v['rating'] ? (number_format((float) $v['rating'], 1) . ' ★') : '—',
        'description' => navFlatName($v['description'] ?? $v['short_description'] ?? '') ?: 'Locație listată pe bilete.online cu activități disponibile online.',
        'tags'        => array_slice(is_array($v['tags'] ?? null) ? $v['tags'] : (is_array($v['amenities'] ?? null) ? $v['amenities'] : []), 0, 4),
        'activities'  => array_map(fn ($a) => [
            'name'  => navFlatName($a['title'] ?? $a['name'] ?? ''),
            'price' => isset($a['cheapest_price_cents']) && $a['cheapest_price_cents'] > 0
                ? 'de la ' . (int) round($a['cheapest_price_cents'] / 100) . ' lei'
                : (isset($a['price']) ? $a['price'] : ''),
            'url'   => '/activitate/' . ($a['slug'] ?? ''),
        ], array_slice($v['activities'] ?? [], 0, 3)),
    ];
}

// Static fallback if no venues yet
if (empty($locations)) {
    $locations = [
        ['name' => 'Mystery Rooms Brașov', 'slug' => 'mystery-rooms-brasov', 'type' => 'escape_room', 'typeLabel' => 'Escape room', 'city' => 'Brașov', 'citySlug' => 'brasov', 'image' => null, 'rating' => '4.9 ★', 'description' => 'Operator de escape rooms cu camere tematice pentru grupuri mici și mari.', 'tags' => ['mister', 'grupuri', 'indoor'], 'activities' => []],
        ['name' => 'Muzeul Național de Artă', 'slug' => 'muzeul-national-de-arta', 'type' => 'museum', 'typeLabel' => 'Muzeu', 'city' => 'București', 'citySlug' => 'bucuresti', 'image' => null, 'rating' => '4.7 ★', 'description' => 'Galerii de artă românească și expoziții temporare.', 'tags' => ['artă', 'cultură', 'indoor'], 'activities' => []],
        ['name' => 'Parc Aventura Brașov', 'slug' => 'parc-aventura-brasov', 'type' => 'adventure_park', 'typeLabel' => 'Parc aventură', 'city' => 'Brașov', 'citySlug' => 'brasov', 'image' => null, 'rating' => '4.8 ★', 'description' => 'Trasee în copaci, tiroliene, escaladă pentru toate vârstele.', 'tags' => ['outdoor', 'aventură', 'familie'], 'activities' => []],
        ['name' => 'Atelier Ceramică Cluj', 'slug' => 'atelier-ceramica-cluj', 'type' => 'workshop', 'typeLabel' => 'Atelier', 'city' => 'Cluj-Napoca', 'citySlug' => 'cluj-napoca', 'image' => null, 'rating' => '5.0 ★', 'description' => 'Ateliere de ceramică pentru începători și avansați.', 'tags' => ['ateliere', 'creativ', 'indoor'], 'activities' => []],
        ['name' => 'Peștera Valea Cetății', 'slug' => 'pestera-valea-cetatii', 'type' => 'cave', 'typeLabel' => 'Peșteră', 'city' => 'Brașov', 'citySlug' => 'brasov', 'image' => null, 'rating' => '4.6 ★', 'description' => 'Tur ghidat în una dintre cele mai vechi peșteri din zonă.', 'tags' => ['natură', 'tur', 'outdoor'], 'activities' => []],
        ['name' => 'Acvariul Constanța', 'slug' => 'acvariul-constanta', 'type' => 'aquarium', 'typeLabel' => 'Acvariu', 'city' => 'Constanța', 'citySlug' => 'constanta', 'image' => null, 'rating' => '4.5 ★', 'description' => 'Specii marine din toată lumea și expoziții interactive.', 'tags' => ['copii', 'familie', 'indoor'], 'activities' => []],
    ];
}

// Type filters with counts
$typeCounts = [];
foreach ($locations as $l) {
    $typeCounts[$l['type']] = ($typeCounts[$l['type']] ?? 0) + 1;
}
$typeFilters = [['key' => 'all', 'label' => 'Toate locațiile', 'count' => count($locations)]];
$seen = ['all' => true];
foreach ($locations as $l) {
    if (isset($seen[$l['type']])) continue;
    $seen[$l['type']] = true;
    $typeFilters[] = ['key' => $l['type'], 'label' => $l['typeLabel'], 'count' => $typeCounts[$l['type']]];
}

// Unique cities
$citiesList = array_values(array_unique(array_filter(array_map(fn ($l) => $l['city'], $locations))));
sort($citiesList);

// Quick chips
$quickChips = array_slice($typeFilters, 0, 6);

// SEO
$pageTitleRaw    = 'Locații și operatori — ' . SITE_NAME;
$pageDescription = 'Descoperă locațiile partenere bilete.online: escape rooms, muzee, parcuri, ateliere, peșteri, rezervații și centre de agrement. Bilete cu QR, instant pe email.';
$canonicalUrl    = SITE_URL . '/locatii';
$currentPage     = 'locatii';
$cssBundle       = 'listing';

$breadcrumbs = [
    ['name' => 'Acasă', 'url' => SITE_URL . '/'],
    ['name' => 'Locații', 'url' => $canonicalUrl],
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

<main x-data="locationsPage(<?= htmlspecialchars(json_encode([
    'locations'   => $locations,
    'typeFilters' => $typeFilters,
    'cities'      => $citiesList,
    'quickChips'  => $quickChips,
]), ENT_QUOTES) ?>)">

<!-- HERO -->
<section class="relative overflow-hidden border-b-2 border-ink">
    <div class="absolute inset-0 bg-[radial-gradient(circle_at_82%_14%,rgba(232,69,39,.24),transparent_30%),radial-gradient(circle_at_16%_72%,rgba(30,74,61,.22),transparent_34%),radial-gradient(circle_at_50%_44%,rgba(218,154,51,.18),transparent_30%)]"></div>
    <div class="relative max-w-7xl mx-auto px-4 sm:px-6 pt-14 sm:pt-20 pb-16 sm:pb-24">
        <nav class="flex items-center gap-2 text-sm text-ink-soft" aria-label="Breadcrumb">
            <a href="/" class="hover:text-vermilion">Acasă</a><span>/</span><span class="text-ink">Locații</span>
        </nav>
        <div class="mt-8 grid lg:grid-cols-[1fr_.92fr] gap-12 items-center">
            <div>
                <p class="stamp inline-flex px-3 py-1 text-xs font-mono tracking-[.18em] text-vermilion bg-paper/70">LOCAȚII · OPERATORI · ACTIVITĂȚI</p>
                <h1 class="mt-6 font-display text-6xl sm:text-8xl font-bold leading-[.82]">Locuri unde mergi să faci ceva.</h1>
                <p class="mt-6 max-w-2xl text-xl sm:text-2xl text-ink-soft leading-relaxed">
                    Descoperă locații și operatori de activități: escape rooms, muzee, parcuri, ateliere, peșteri, rezervații, centre de agrement și spații care vând experiențe online.
                </p>
                <div class="mt-8 max-w-2xl">
                    <label class="sr-only" for="location-search">Caută locație</label>
                    <div class="relative">
                        <input id="location-search" type="text" class="field text-lg pr-14" x-model="search" placeholder="Caută: escape room, muzeu, Brașov, copii...">
                        <span class="absolute right-4 top-1/2 -translate-y-1/2 text-ink-soft">
                            <svg viewBox="0 0 24 24" class="w-6 h-6" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="7"/><path d="m20 20-3.5-3.5"/></svg>
                        </span>
                    </div>
                </div>
                <div class="mt-6 flex flex-wrap gap-2">
                    <template x-for="chip in quickChips" :key="chip.key">
                        <button @click="activeType=chip.key" class="rounded-full bg-paper/70 border border-ink/10 px-4 py-2 font-bold hover:bg-ink hover:text-paper transition" x-text="chip.label"></button>
                    </template>
                </div>
            </div>
            <div class="relative min-h-[420px] hidden lg:block">
                <div class="absolute inset-x-8 top-10 bottom-8 rounded-[2.4rem] bg-ink rotate-[-2deg] shadow-deep"></div>
                <div class="absolute top-0 left-0 right-0 mx-auto max-w-[540px] ticket bg-paper border-2 border-ink rounded-[2rem] overflow-hidden shadow-deep rotate-[2deg]" style="--perf:100%">
                    <div class="p-6 sm:p-8">
                        <p class="font-mono text-xs tracking-[.18em] text-ink-soft">LOCATION GRAPH</p>
                        <h2 class="mt-3 font-display text-3xl font-bold leading-none">O locație poate avea mai multe activități.</h2>
                        <div class="mt-7 rounded-3xl bg-paper-2 border border-ink/10 p-5">
                            <div class="flex items-center gap-3">
                                <span class="grid place-items-center w-12 h-12 rounded-2xl bg-vermilion text-paper">📍</span>
                                <div>
                                    <p class="font-display text-2xl font-bold"><?= htmlspecialchars($locations[0]['name'] ?? 'Mystery Rooms') ?></p>
                                    <p class="text-sm text-ink-soft"><?= htmlspecialchars($locations[0]['typeLabel'] ?? 'Operator') ?> · <?= htmlspecialchars($locations[0]['city'] ?? '') ?></p>
                                </div>
                            </div>
                            <div class="mt-5 grid gap-3">
                                <div class="rounded-2xl bg-paper border border-ink/10 p-4 flex justify-between gap-3"><span class="font-bold">Camera 13</span><span class="text-forest font-bold">bilete</span></div>
                                <div class="rounded-2xl bg-paper border border-ink/10 p-4 flex justify-between gap-3"><span class="font-bold">Laboratorul 7</span><span class="text-forest font-bold">bilete</span></div>
                                <div class="rounded-2xl bg-paper border border-ink/10 p-4 flex justify-between gap-3"><span class="font-bold">Misiunea Alpha</span><span class="text-ochre font-bold">soon</span></div>
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
                <p class="font-mono text-xs tracking-[.18em] text-ink-soft">FILTRARE LOCAȚII</p>
                <div class="mt-4 space-y-2">
                    <template x-for="filter in typeFilters" :key="filter.key">
                        <button @click="activeType=filter.key" :class="activeType===filter.key ? 'bg-ink text-paper' : 'bg-paper-2 text-ink hover:bg-ink/5'" class="w-full rounded-2xl px-4 py-3 text-left font-bold transition flex items-center justify-between gap-3">
                            <span x-text="filter.label"></span>
                            <span class="text-xs opacity-60" x-text="filter.count"></span>
                        </button>
                    </template>
                </div>
                <label class="block mt-5">
                    <span class="block mb-1.5 text-sm font-bold">Oraș</span>
                    <select class="field" x-model="activeCity">
                        <option value="all">Toate orașele</option>
                        <template x-for="city in cities" :key="city">
                            <option :value="city" x-text="city"></option>
                        </template>
                    </select>
                </label>
                <div class="mt-5 rounded-2xl bg-mint border border-forest/20 p-4">
                    <p class="font-bold text-forest">Pentru locații</p>
                    <p class="mt-1 text-sm text-ink-soft">Ai o locație cu activități? Poți avea pagină dedicată, activități listate, bilete QR și dashboard.</p>
                    <a href="/pentru-locatii" class="mt-3 inline-flex font-bold text-forest underline-wobble">Vezi detalii</a>
                </div>
            </div>
        </aside>

        <section>
            <div class="flex flex-col sm:flex-row sm:items-end sm:justify-between gap-4">
                <div>
                    <p class="font-mono text-xs tracking-[.18em] text-ink-soft">LOCAȚII</p>
                    <h2 class="mt-2 font-display text-5xl font-bold leading-none" x-text="currentTypeTitle()"></h2>
                </div>
                <p class="text-ink-soft" x-text="filteredLocations().length + ' din <?= count($locations) ?> locații'"></p>
            </div>

            <div class="mt-6 grid md:grid-cols-2 xl:grid-cols-3 gap-5">
                <template x-for="location in filteredLocations()" :key="location.slug">
                    <article class="group rounded-[2rem] border-2 border-ink bg-paper overflow-hidden shadow-ticket hover:-translate-y-1 transition">
                        <a :href="'/locatie/' + location.slug" class="block">
                            <div class="relative h-52 overflow-hidden bg-ink">
                                <img x-show="location.image" :src="location.image" :alt="'Imagine pentru ' + location.name" class="w-full h-full object-cover opacity-85 group-hover:scale-105 transition duration-500" loading="lazy" onerror="this.style.display='none'">
                                <div class="absolute inset-0 bg-gradient-to-t from-ink/85 via-ink/10 to-transparent"></div>
                                <div class="absolute inset-0 grid place-items-center" x-show="!location.image">
                                    <span class="text-6xl opacity-30">📍</span>
                                </div>
                                <span class="absolute left-4 top-4 rounded-full bg-paper text-ink px-3 py-1 text-xs font-bold" x-text="location.typeLabel"></span>
                                <span class="absolute right-4 top-4 rounded-full bg-mint text-forest px-3 py-1 text-xs font-bold" x-text="location.rating"></span>
                                <div class="absolute left-4 bottom-4 right-4">
                                    <p class="text-paper/65 font-mono text-[10px] tracking-[.18em]" x-text="location.city"></p>
                                    <h3 class="font-display text-3xl font-bold text-paper leading-none" x-text="location.name"></h3>
                                </div>
                            </div>
                        </a>
                        <div class="p-5">
                            <p class="text-ink-soft leading-relaxed line-clamp-3" x-text="location.description"></p>
                            <div class="mt-4 flex flex-wrap gap-2" x-show="location.tags.length > 0">
                                <template x-for="tag in location.tags" :key="tag">
                                    <span class="rounded-full bg-paper-2 border border-ink/10 px-3 py-1 text-xs font-bold" x-text="tag"></span>
                                </template>
                            </div>
                            <div class="mt-5 rounded-2xl bg-paper-2 border border-ink/10 p-4" x-show="location.activities.length > 0">
                                <div class="flex items-center justify-between gap-3">
                                    <span class="font-bold">Activități disponibile</span>
                                    <span class="text-sm text-ink-soft" x-text="location.activities.length"></span>
                                </div>
                                <div class="mt-3 space-y-2">
                                    <template x-for="activity in location.activities.slice(0,2)" :key="activity.name">
                                        <a :href="activity.url" class="flex items-center justify-between gap-3 text-sm hover:text-vermilion">
                                            <span x-text="activity.name"></span>
                                            <span class="font-bold" x-text="activity.price"></span>
                                        </a>
                                    </template>
                                </div>
                            </div>
                            <div class="mt-5 flex items-center justify-between gap-3">
                                <a :href="'/locatie/' + location.slug" class="font-bold text-vermilion underline-wobble">Vezi locația</a>
                                <a x-show="location.citySlug" :href="'/' + location.citySlug" class="text-sm font-bold text-ink-soft hover:text-ink" x-text="location.city"></a>
                            </div>
                        </div>
                    </article>
                </template>
            </div>
        </section>
    </div>
</section>

<!-- ANATOMY -->
<section class="border-y-2 border-ink bg-paper-2/65">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 py-16 sm:py-20">
        <div class="grid lg:grid-cols-[.85fr_1.15fr] gap-10 items-start">
            <div class="lg:sticky lg:top-28">
                <p class="stamp inline-flex px-3 py-1 text-xs font-mono tracking-[.18em] text-vermilion">PAGINĂ LOCAȚIE</p>
                <h2 class="mt-5 font-display text-5xl sm:text-6xl font-bold leading-[.9]">O locație bună nu este doar o adresă.</h2>
                <p class="mt-5 text-lg text-ink-soft leading-relaxed">Pagina unei locații explică ce experiențe oferă, ce activități poți cumpăra, unde este, cum ajungi și ce reguli există.</p>
            </div>
            <div class="grid sm:grid-cols-2 gap-4">
                <article class="rounded-3xl border-2 border-ink/15 bg-paper-2/70 p-6">
                    <p class="font-mono text-xs tracking-[.18em] text-vermilion">01</p>
                    <h3 class="mt-2 font-display text-3xl font-bold">Identitate</h3>
                    <p class="mt-2 text-ink-soft">Nume, descriere, tip locație, galerie, atmosferă.</p>
                </article>
                <article class="rounded-3xl border-2 border-ink/15 bg-mint p-6">
                    <p class="font-mono text-xs tracking-[.18em] text-forest">02</p>
                    <h3 class="mt-2 font-display text-3xl font-bold">Activități</h3>
                    <p class="mt-2 text-ink-soft">Lista activităților, bilete, prețuri, disponibilitate.</p>
                </article>
                <article class="rounded-3xl border-2 border-ink/15 bg-paper-2/70 p-6">
                    <p class="font-mono text-xs tracking-[.18em] text-vermilion">03</p>
                    <h3 class="mt-2 font-display text-3xl font-bold">Acces</h3>
                    <p class="mt-2 text-ink-soft">Adresă, hartă, parcare, transport, program și reguli.</p>
                </article>
                <article class="rounded-3xl border-2 border-ink/15 bg-ink text-paper p-6">
                    <p class="font-mono text-xs tracking-[.18em] text-ochre">04</p>
                    <h3 class="mt-2 font-display text-3xl font-bold">Trust</h3>
                    <p class="mt-2 text-paper/60">Recenzii, FAQ, informații pentru familii și grupuri.</p>
                </article>
            </div>
        </div>
    </div>
</section>

<!-- LOCATION TYPES -->
<section class="border-y-2 border-ink bg-ink text-paper">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 py-16 sm:py-20">
        <div class="max-w-3xl">
            <p class="stamp inline-flex px-3 py-1 text-xs font-mono tracking-[.18em] text-ochre">TIPURI DE LOCAȚII</p>
            <h2 class="mt-5 font-display text-5xl sm:text-6xl font-bold leading-[.9]">Același marketplace, modele diferite.</h2>
            <p class="mt-5 text-lg text-paper/60 leading-relaxed">Fiecare tip de locație are nevoie de altă structură: sloturi, bilete pe zi, tururi, pachete.</p>
        </div>
        <div class="mt-10 grid md:grid-cols-3 gap-5">
            <?php $types = [
                ['🕵️', 'Escape rooms',  'Sloturi orare, camere, dificultate, jucători, check-in.'],
                ['🖼️', 'Muzee',          'Program, expoziții, tururi, bilete adult/copil, acces.'],
                ['🌲', 'Natură',         'Reguli, echipament, tururi, ghid, nivel, sezon.'],
                ['🎡', 'Parcuri',        'Acces pe zi, atracții, pachete, vârste, facilități.'],
                ['🎨', 'Ateliere',       'Locuri limitate, materiale, vârstă, durată.'],
                ['🚶', 'Tururi',         'Punct de întâlnire, limbă, durată, ghid, traseu.'],
            ]; foreach ($types as $t): ?>
                <article class="rounded-3xl bg-paper/10 border border-paper/10 p-6">
                    <p class="text-3xl"><?= $t[0] ?></p>
                    <h3 class="mt-3 font-display text-3xl font-bold"><?= htmlspecialchars($t[1]) ?></h3>
                    <p class="mt-2 text-paper/60"><?= htmlspecialchars($t[2]) ?></p>
                </article>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<!-- FAQ -->
<section class="max-w-5xl mx-auto px-4 sm:px-6 py-16 sm:py-20" x-data="{open:0}">
    <div class="text-center max-w-3xl mx-auto">
        <p class="stamp inline-flex px-3 py-1 text-xs font-mono tracking-[.18em] text-vermilion">FAQ</p>
        <h2 class="mt-5 font-display text-5xl sm:text-6xl font-bold leading-[.9]">Cum aleg o locație?</h2>
    </div>
    <div class="mt-10 space-y-3">
        <?php $faqs = [
            ['Sunt toate locațiile verificate?', 'Da. Locațiile listate trec printr-un proces de validare înainte de publicare. Echipa noastră verifică legitimitatea, contactele și conformitatea cu condițiile platformei.'],
            ['Ce activități găsesc într-o locație?', 'În funcție de tipul locației: escape rooms au camere și sloturi, muzeele au programe și tururi, parcurile au acces pe zi, atelierele au sesiuni cu locuri limitate.'],
            ['Cum cumpăr bilete pentru o locație?', 'De pe pagina locației sau direct din pagina activității. La final primești QR pe email și în cont.'],
            ['Pot adăuga locația mea pe bilete.online?', 'Da. Vezi pagina Pentru locații pentru detalii despre listare, comisioane, dashboard și instrumentele platformei.'],
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
                <p class="font-mono text-xs tracking-[.2em] text-paper/60">LOCAȚII</p>
                <h2 class="mt-3 font-display text-5xl sm:text-6xl font-bold leading-[.9]">Listează locația ta și vinde bilete online.</h2>
                <p class="mt-4 max-w-2xl text-paper/75 text-lg">Pagina dedicată, activități, bilete QR, dashboard, scanner check-in și rapoarte.</p>
            </div>
            <div class="flex flex-col sm:flex-row lg:flex-col gap-3">
                <a href="/pentru-locatii" class="rounded-full bg-paper text-ink px-6 py-4 font-bold text-center hover:bg-ink hover:text-paper transition">Pentru locații</a>
                <a href="/autentificare?ca=venue&mode=register" class="rounded-full border-2 border-paper/60 px-6 py-4 font-bold text-center hover:bg-paper hover:text-ink transition">Solicită cont</a>
            </div>
        </div>
    </div>
</section>

</main>

<script>
function locationsPage(data) {
    return {
        search: '',
        activeType: 'all',
        activeCity: 'all',
        locations: data.locations || [],
        typeFilters: data.typeFilters || [],
        cities: data.cities || [],
        quickChips: data.quickChips || [],
        norm(s) {
            return (s || '').toString().toLowerCase().normalize('NFD')
                .replace(/[̀-ͯ]/g, '')
                .replace(/[şș]/g, 's').replace(/[ţț]/g, 't')
                .replace(/[ăâ]/g, 'a').replace(/[î]/g, 'i').trim();
        },
        currentTypeTitle() {
            const found = this.typeFilters.find(f => f.key === this.activeType);
            return found ? found.label : 'Toate locațiile';
        },
        filteredLocations() {
            const q = this.norm(this.search);
            return this.locations.filter(l => {
                const matchesType = this.activeType === 'all' || l.type === this.activeType;
                const matchesCity = this.activeCity === 'all' || l.city === this.activeCity;
                const matchesSearch = !q || this.norm(l.name + ' ' + l.city + ' ' + l.description + ' ' + l.typeLabel).includes(q);
                return matchesType && matchesCity && matchesSearch;
            });
        },
    };
}
</script>

<?php include __DIR__ . '/includes/footer.php'; ?>
