<?php
/**
 * viaqui.com: the older catalogue of venues (first design, Alpine + the legacy head).
 *
 * Not routed any more: /venues is served by hub-locatii.php. Kept in step with the language layer in case it is
 * linked again. It lists the venues the marketplace API returns; with none, it says so (the page used to fall back
 * to six invented Romanian venues with invented ratings, whose links were 404s).
 */

$pageCacheTTL = 600;
require_once __DIR__ . '/includes/page-cache.php';
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/api.php';
require_once __DIR__ . '/includes/nav-helpers.php';
require_once __DIR__ . '/includes/v2/helpers.php';   // v2_t(), v2_te(), v2_own_price_label()

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
    'escape_room' => v2_t('Escape room'),
    'escape-room' => v2_t('Escape room'),
    'museum'      => v2_t('Museum'),
    'muzeu'       => v2_t('Museum'),
    'park'        => v2_t('Park'),
    'parc'        => v2_t('Park'),
    'adventure_park' => v2_t('Adventure park'),
    'workshop'    => v2_t('Workshop'),
    'tour'        => v2_t('Guided tour'),
    'aquarium'    => v2_t('Aquarium'),
    'zoo'         => v2_t('Zoo'),
    'cave'        => v2_t('Cave'),
    'leisure_venue' => v2_t('Leisure centre'),
];

$locations = [];
foreach ($rawVenues as $v) {
    $name = navFlatName($v['name'] ?? '');
    $slug = $v['slug'] ?? '';
    if (! $name || ! $slug) continue;

    $type = $v['type'] ?? $v['venue_type'] ?? '';
    $typeLabel = $typeLabels[$type] ?? ucfirst(str_replace('_', ' ', (string) $type)) ?: v2_t('Venue');
    $cityName = navFlatName($v['city']['name'] ?? $v['city_name'] ?? '');
    $citySlug = $v['city']['slug'] ?? $v['city_slug'] ?? '';

    $locations[] = [
        'name'        => $name,
        'slug'        => $slug,
        'type'        => $type ?: 'all',
        'typeLabel'   => $typeLabel,
        'city'        => $cityName ?: v2_t('Europe'),
        'citySlug'    => $citySlug ?: '',
        'image'       => $v['cover_image_url'] ?? $v['image'] ?? null,
        'rating'      => isset($v['rating']) && $v['rating'] ? (number_format((float) $v['rating'], 1) . ' ★') : '—',
        'description' => navFlatName($v['description'] ?? $v['short_description'] ?? '') ?: v2_t('A venue listed on Viaqui, with activities you can book online.'),
        'tags'        => array_slice(is_array($v['tags'] ?? null) ? $v['tags'] : (is_array($v['amenities'] ?? null) ? $v['amenities'] : []), 0, 4),
        'activities'  => array_map(fn ($a) => [
            'name'  => navFlatName($a['title'] ?? $a['name'] ?? ''),
            'price' => isset($a['cheapest_price_cents']) && $a['cheapest_price_cents'] > 0
                ? v2_t('from {price}', ['price' => v2_own_price_label((int) $a['cheapest_price_cents'], $a['currency'] ?? $v['currency'] ?? null)])
                : (isset($a['price']) ? $a['price'] : ''),
            'url'   => '/activity/' . ($a['slug'] ?? ''),
        ], array_slice($v['activities'] ?? [], 0, 3)),
    ];
}


// Type filters with counts
$typeCounts = [];
foreach ($locations as $l) {
    $typeCounts[$l['type']] = ($typeCounts[$l['type']] ?? 0) + 1;
}
$typeFilters = [['key' => 'all', 'label' => v2_t('All venues'), 'count' => count($locations)]];
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
$pageTitleRaw    = v2_t('Venues and operators') . ' | ' . SITE_NAME;
$pageDescription = v2_t('Discover the venues on Viaqui: escape rooms, museums, parks, workshops, caves, nature reserves and leisure centres. QR tickets, by email straight away.');
$canonicalUrl    = SITE_URL . '/venues';
$currentPage     = 'locatii';
$cssBundle       = 'listing';

$breadcrumbs = [
    ['name' => v2_t('Home'), 'url' => SITE_URL . '/'],
    ['name' => v2_t('Venues'), 'url' => $canonicalUrl],
];

$structuredData = [[
    '@context' => 'https://schema.org',
    '@type' => 'CollectionPage',
    'name' => $pageTitleRaw,
    'description' => $pageDescription,
    'url' => $canonicalUrl,
    'inLanguage' => v2_locale(),
]];

include __DIR__ . '/includes/head.php';
include __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/v2/product-icons.php';
echo am_product_icon_sprite(['pin', 'key', 'museum', 'evergreen', 'balloon', 'palette', 'walk']);
?>

<main x-data="locationsPage(<?= htmlspecialchars(json_encode([
    'locations'   => $locations,
    'typeFilters' => $typeFilters,
    'cities'      => $citiesList,
    'quickChips'  => $quickChips,
    // texts the script below prints
    'labels'      => [
        'all'   => v2_t('All venues'),
        'count' => v2_t('{shown} of {total} venues'),
        'photo' => v2_t('Photo of {name}'),
    ],
]), ENT_QUOTES) ?>)">

<!-- HERO -->
<section class="relative overflow-hidden border-b-2 border-ink">
    <div class="absolute inset-0 bg-[radial-gradient(circle_at_82%_14%,rgba(232,69,39,.24),transparent_30%),radial-gradient(circle_at_16%_72%,rgba(30,74,61,.22),transparent_34%),radial-gradient(circle_at_50%_44%,rgba(218,154,51,.18),transparent_30%)]"></div>
    <div class="relative max-w-7xl mx-auto px-4 sm:px-6 pt-14 sm:pt-20 pb-16 sm:pb-24">
        <nav class="flex items-center gap-2 text-sm text-ink-soft" aria-label="<?= v2_te('Breadcrumb') ?>">
            <a href="/" class="hover:text-vermilion"><?= v2_te('Home') ?></a><span>/</span><span class="text-ink"><?= v2_te('Venues') ?></span>
        </nav>
        <div class="mt-8 grid lg:grid-cols-[1fr_.92fr] gap-12 items-center">
            <div>
                <p class="stamp inline-flex px-3 py-1 text-xs font-mono tracking-[.18em] text-vermilion bg-paper/70"><?= v2_te('Venues · Operators · Activities') ?></p>
                <h1 class="mt-6 font-display text-6xl sm:text-8xl font-bold leading-[.82]"><?= v2_te('Places you go to do something.') ?></h1>
                <p class="mt-6 max-w-2xl text-xl sm:text-2xl text-ink-soft leading-relaxed">
                    <?= v2_te('Discover venues and activity operators: escape rooms, museums, parks, workshops, caves, nature reserves, leisure centres and places that sell experiences online.') ?>
                </p>
                <div class="mt-8 max-w-2xl">
                    <label class="sr-only" for="location-search"><?= v2_te('Search for a venue') ?></label>
                    <div class="relative">
                        <input id="location-search" type="text" class="field text-lg pr-14" x-model="search" placeholder="<?= v2_te('Search: escape room, museum, Lisbon, kids…') ?>">
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
                        <p class="font-mono text-xs tracking-[.18em] text-ink-soft"><?= v2_te('How a venue is listed') ?></p>
                        <h2 class="mt-3 font-display text-3xl font-bold leading-none"><?= v2_te('One venue can have several activities.') ?></h2>
                        <div class="mt-7 rounded-3xl bg-paper-2 border border-ink/10 p-5">
                            <div class="flex items-center gap-3">
                                <span class="grid place-items-center w-12 h-12 rounded-2xl bg-vermilion text-paper"><?= am_product_icon_svg('pin', 'w-6 h-6') ?></span>
                                <div>
                                    <p class="font-display text-2xl font-bold"><?= htmlspecialchars($locations[0]['name'] ?? 'Mystery Rooms') ?></p>
                                    <p class="text-sm text-ink-soft"><?= htmlspecialchars($locations[0]['typeLabel'] ?? v2_t('Operator')) ?> · <?= htmlspecialchars($locations[0]['city'] ?? '') ?></p>
                                </div>
                            </div>
                            <div class="mt-5 grid gap-3">
                                <div class="rounded-2xl bg-paper border border-ink/10 p-4 flex justify-between gap-3"><span class="font-bold"><?= v2_te('Room 13') ?></span><span class="text-forest font-bold"><?= v2_te('tickets') ?></span></div>
                                <div class="rounded-2xl bg-paper border border-ink/10 p-4 flex justify-between gap-3"><span class="font-bold"><?= v2_te('Lab 7') ?></span><span class="text-forest font-bold"><?= v2_te('tickets') ?></span></div>
                                <div class="rounded-2xl bg-paper border border-ink/10 p-4 flex justify-between gap-3"><span class="font-bold"><?= v2_te('Mission Alpha') ?></span><span class="text-ochre font-bold"><?= v2_te('soon') ?></span></div>
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
                <p class="font-mono text-xs tracking-[.18em] text-ink-soft"><?= v2_te('Filter venues') ?></p>
                <div class="mt-4 space-y-2">
                    <template x-for="filter in typeFilters" :key="filter.key">
                        <button @click="activeType=filter.key" :class="activeType===filter.key ? 'bg-ink text-paper' : 'bg-paper-2 text-ink hover:bg-ink/5'" class="w-full rounded-2xl px-4 py-3 text-left font-bold transition flex items-center justify-between gap-3">
                            <span x-text="filter.label"></span>
                            <span class="text-xs opacity-60" x-text="filter.count"></span>
                        </button>
                    </template>
                </div>
                <label class="block mt-5">
                    <span class="block mb-1.5 text-sm font-bold"><?= v2_te('City') ?></span>
                    <select class="field" x-model="activeCity">
                        <option value="all"><?= v2_te('All cities') ?></option>
                        <template x-for="city in cities" :key="city">
                            <option :value="city" x-text="city"></option>
                        </template>
                    </select>
                </label>
                <div class="mt-5 rounded-2xl bg-mint border border-forest/20 p-4">
                    <p class="font-bold text-forest"><?= v2_te('For venues') ?></p>
                    <p class="mt-1 text-sm text-ink-soft"><?= v2_te('Do you run a venue with activities? You can have your own page, your activities listed, QR tickets and a dashboard.') ?></p>
                    <a href="/partners" class="mt-3 inline-flex font-bold text-forest underline-wobble"><?= v2_te('See details') ?></a>
                </div>
            </div>
        </aside>

        <section>
            <div class="flex flex-col sm:flex-row sm:items-end sm:justify-between gap-4">
                <div>
                    <p class="font-mono text-xs tracking-[.18em] text-ink-soft"><?= v2_te('Venues') ?></p>
                    <h2 class="mt-2 font-display text-5xl font-bold leading-none" x-text="currentTypeTitle()"></h2>
                </div>
                <p class="text-ink-soft" x-text="labels.count.replace('{shown}', filteredLocations().length).replace('{total}', <?= count($locations) ?>)"></p>
            </div>

            <?php if (!$locations): ?><p class="mt-6 text-lg text-ink-soft"><?= v2_te('No venues are listed yet. Come back soon.') ?></p><?php endif; ?>
            <div class="mt-6 grid md:grid-cols-2 xl:grid-cols-3 gap-5">
                <template x-for="location in filteredLocations()" :key="location.slug">
                    <article class="group rounded-[2rem] border-2 border-ink bg-paper overflow-hidden shadow-ticket hover:-translate-y-1 transition">
                        <a :href="'/venue/' + location.slug" class="block">
                            <div class="relative h-52 overflow-hidden bg-ink">
                                <img x-show="location.image" :src="location.image" :alt="labels.photo.replace('{name}', location.name)" class="w-full h-full object-cover opacity-85 group-hover:scale-105 transition duration-500" loading="lazy" onerror="this.style.display='none'">
                                <div class="absolute inset-0 bg-gradient-to-t from-ink/85 via-ink/10 to-transparent"></div>
                                <div class="absolute inset-0 grid place-items-center" x-show="!location.image">
                                    <span class="opacity-30"><?= am_product_icon_svg('pin', 'w-16 h-16') ?></span>
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
                                    <span class="font-bold"><?= v2_te('Activities available') ?></span>
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
                                <a :href="'/venue/' + location.slug" class="font-bold text-vermilion underline-wobble"><?= v2_te('View venue') ?></a>
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
                <p class="stamp inline-flex px-3 py-1 text-xs font-mono tracking-[.18em] text-vermilion"><?= v2_te('The venue page') ?></p>
                <h2 class="mt-5 font-display text-5xl sm:text-6xl font-bold leading-[.9]"><?= v2_te('A good venue is more than an address.') ?></h2>
                <p class="mt-5 text-lg text-ink-soft leading-relaxed"><?= v2_te('A venue page explains what experiences it offers, which activities you can buy, where it is, how to get there and what the rules are.') ?></p>
            </div>
            <div class="grid sm:grid-cols-2 gap-4">
                <article class="rounded-3xl border-2 border-ink/15 bg-paper-2/70 p-6">
                    <p class="font-mono text-xs tracking-[.18em] text-vermilion">01</p>
                    <h3 class="mt-2 font-display text-3xl font-bold"><?= v2_te('Identity') ?></h3>
                    <p class="mt-2 text-ink-soft"><?= v2_te('Name, description, type of venue, gallery, atmosphere.') ?></p>
                </article>
                <article class="rounded-3xl border-2 border-ink/15 bg-mint p-6">
                    <p class="font-mono text-xs tracking-[.18em] text-forest">02</p>
                    <h3 class="mt-2 font-display text-3xl font-bold"><?= v2_te('Activities') ?></h3>
                    <p class="mt-2 text-ink-soft"><?= v2_te('The list of activities, tickets, prices, availability.') ?></p>
                </article>
                <article class="rounded-3xl border-2 border-ink/15 bg-paper-2/70 p-6">
                    <p class="font-mono text-xs tracking-[.18em] text-vermilion">03</p>
                    <h3 class="mt-2 font-display text-3xl font-bold"><?= v2_te('Access') ?></h3>
                    <p class="mt-2 text-ink-soft"><?= v2_te('Address, map, parking, transport, opening hours and rules.') ?></p>
                </article>
                <article class="rounded-3xl border-2 border-ink/15 bg-ink text-paper p-6">
                    <p class="font-mono text-xs tracking-[.18em] text-ochre">04</p>
                    <h3 class="mt-2 font-display text-3xl font-bold"><?= v2_te('Trust') ?></h3>
                    <p class="mt-2 text-paper/60"><?= v2_te('Reviews, FAQ, information for families and groups.') ?></p>
                </article>
            </div>
        </div>
    </div>
</section>

<!-- LOCATION TYPES -->
<section class="border-y-2 border-ink bg-ink text-paper">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 py-16 sm:py-20">
        <div class="max-w-3xl">
            <p class="stamp inline-flex px-3 py-1 text-xs font-mono tracking-[.18em] text-ochre"><?= v2_te('Types of venue') ?></p>
            <h2 class="mt-5 font-display text-5xl sm:text-6xl font-bold leading-[.9]"><?= v2_te('One marketplace, different models.') ?></h2>
            <p class="mt-5 text-lg text-paper/60 leading-relaxed"><?= v2_te('Each type of venue needs a different structure: time slots, day tickets, tours, packages.') ?></p>
        </div>
        <div class="mt-10 grid md:grid-cols-3 gap-5">
            <?php $types = [
                ['key', v2_t('Escape rooms'), v2_t('Time slots, rooms, difficulty, players, check-in.')],
                ['museum', v2_t('Museums'), v2_t('Opening hours, exhibitions, tours, adult and child tickets, access.')],
                ['evergreen', v2_t('Nature'), v2_t('Rules, equipment, tours, guide, level, season.')],
                ['balloon', v2_t('Parks'), v2_t('Day access, attractions, packages, ages, facilities.')],
                ['palette', v2_t('Workshops'), v2_t('Limited places, materials, age, duration.')],
                ['walk', v2_t('Tours'), v2_t('Meeting point, language, duration, guide, route.')],
            ]; foreach ($types as $t): ?>
                <article class="rounded-3xl bg-paper/10 border border-paper/10 p-6">
                    <p aria-hidden="true"><?= am_product_icon_svg($t[0], 'w-9 h-9') ?></p>
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
        <p class="stamp inline-flex px-3 py-1 text-xs font-mono tracking-[.18em] text-vermilion"><?= v2_te('FAQ') ?></p>
        <h2 class="mt-5 font-display text-5xl sm:text-6xl font-bold leading-[.9]"><?= v2_te('How do I choose a venue?') ?></h2>
    </div>
    <div class="mt-10 space-y-3">
        <?php $faqs = [
            [v2_t('Are all venues checked?'), v2_t('Yes. Listed venues go through a validation process before they are published. Our team checks that they are genuine, their contact details and that they meet the platform terms.')],
            [v2_t('What activities will I find at a venue?'), v2_t('It depends on the type of venue: escape rooms have rooms and time slots, museums have opening hours and tours, parks have day access, workshops have sessions with limited places.')],
            [v2_t('How do I buy tickets for a venue?'), v2_t('From the venue page or straight from the activity page. At the end you get your QR code by email and in your account.')],
            [v2_t('Can I add my venue to Viaqui?'), v2_t('Yes. See the page for venues for details about listing, commission, the dashboard and the tools of the platform.')],
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
                <p class="font-mono text-xs tracking-[.2em] text-paper/60"><?= v2_te('Venues') ?></p>
                <h2 class="mt-3 font-display text-5xl sm:text-6xl font-bold leading-[.9]"><?= v2_te('List your venue and sell tickets online.') ?></h2>
                <p class="mt-4 max-w-2xl text-paper/75 text-lg"><?= v2_te('Your own page, activities, QR tickets, a dashboard, a check-in scanner and reports.') ?></p>
            </div>
            <div class="flex flex-col sm:flex-row lg:flex-col gap-3">
                <a href="/partners" class="rounded-full bg-paper text-ink px-6 py-4 font-bold text-center hover:bg-ink hover:text-paper transition"><?= v2_te('For venues') ?></a>
                <a href="/login?ca=venue&amp;mode=register" class="rounded-full border-2 border-paper/60 px-6 py-4 font-bold text-center hover:bg-paper hover:text-ink transition"><?= v2_te('Request an account') ?></a>
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
        labels: data.labels || {},
        norm(s) {
            return (s || '').toString().toLowerCase().normalize('NFD')
                .replace(/[̀-ͯ]/g, '')
                .replace(/[şș]/g, 's').replace(/[ţț]/g, 't')
                .replace(/[ăâ]/g, 'a').replace(/[î]/g, 'i').trim();
        },
        currentTypeTitle() {
            const found = this.typeFilters.find(f => f.key === this.activeType);
            return found ? found.label : (this.labels.all || '');
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
