<?php
/**
 * bilete.online — /locatie/{slug}  (single location detail)
 *
 * Renders one venue: identity, gallery, activities list, program/address,
 * SEO entity links. Pulls real data via `GET /venues/{slug}` and shows a
 * minimal "not found" if the slug doesn't match.
 *
 * Layout follows designs/single-location.html: hero + sticky nav +
 * activities list with venue sidebar + about + program + FAQ.
 */

require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/api.php';
require_once __DIR__ . '/includes/nav-helpers.php';

$slug = $_GET['slug'] ?? '';
if (! preg_match('/^[a-z][a-z0-9-]+$/', $slug)) {
    http_response_code(404);
    require __DIR__ . '/404.php';
    exit;
}

$venueResp = api_cached("venue_detail_{$slug}", fn () => api_get('/venues/' . urlencode($slug)), 300);
$venue = $venueResp['data']['venue'] ?? $venueResp['data'] ?? null;

if (! $venue || empty($venue['name'])) {
    http_response_code(404);
    require __DIR__ . '/404.php';
    exit;
}

$name = navFlatName($venue['name'] ?? '');
$cityName = navFlatName($venue['city']['name'] ?? $venue['city_name'] ?? '');
$citySlug = $venue['city']['slug'] ?? $venue['city_slug'] ?? '';
$address  = $venue['address'] ?? '';
$rating   = $venue['rating'] ?? null;
$reviewsCount = (int) ($venue['reviews_count'] ?? 0);
$venueType = $venue['type'] ?? $venue['venue_type'] ?? '';
$description = navFlatName($venue['description'] ?? '');
$shortDescription = navFlatName($venue['short_description'] ?? '');
$coverImage = $venue['cover_image_url'] ?? $venue['image'] ?? null;
$gallery = is_array($venue['gallery'] ?? null) ? array_filter($venue['gallery']) : [];
$activities = $venue['activities'] ?? [];

// Map venue types to RO labels
$typeLabels = [
    'escape_room' => 'Escape room', 'museum' => 'Muzeu', 'park' => 'Parc',
    'adventure_park' => 'Parc aventură', 'workshop' => 'Atelier', 'tour' => 'Tur ghidat',
    'aquarium' => 'Acvariu', 'zoo' => 'Grădină zoologică', 'cave' => 'Peșteră',
    'leisure_venue' => 'Centru de agrement',
];
$typeLabel = $typeLabels[$venueType] ?? ucfirst(str_replace('_', ' ', (string) $venueType)) ?: 'Locație';

// Activities normalized
$normalizedActivities = [];
$cheapestPrice = null;
foreach ($activities as $a) {
    $aTitle = navFlatName($a['title'] ?? $a['name'] ?? '');
    $aSlug  = $a['slug'] ?? '';
    if (! $aTitle || ! $aSlug) continue;
    $cents = (int) ($a['cheapest_price_cents'] ?? 0);
    $priceLei = $cents > 0 ? (int) round($cents / 100) : null;
    if ($priceLei !== null && ($cheapestPrice === null || $priceLei < $cheapestPrice)) {
        $cheapestPrice = $priceLei;
    }
    $normalizedActivities[] = [
        'title'       => $aTitle,
        'slug'        => $aSlug,
        'url'         => '/activitate/' . $aSlug,
        'image'       => $a['cover_image_url'] ?? null,
        'description' => navFlatName($a['short_description'] ?? $a['description'] ?? '') ?: '',
        'duration'    => isset($a['duration_minutes']) && $a['duration_minutes'] ? ((int) $a['duration_minutes']) . ' min' : '',
        'price'       => $priceLei,
        'category'    => navFlatName($a['category']['name'] ?? '') ?: '',
        'tags'        => array_slice(is_array($a['tags'] ?? null) ? $a['tags'] : [], 0, 4),
    ];
}

// SEO
$pageTitleRaw = $name . ' — ' . ($cityName ?: 'România') . ' · ' . SITE_NAME;
$pageDescription = $shortDescription ?: ($description ? mb_substr(strip_tags($description), 0, 160) : "Bilete pentru activități la {$name}" . ($cityName ? " în {$cityName}" : '') . '. Rezervi online, primești QR pe email.');
$canonicalUrl = SITE_URL . '/locatie/' . $slug;
$currentPage  = 'locatie';
$cssBundle    = 'listing';
$ogImage      = $coverImage ?: ($gallery[0] ?? null);

$breadcrumbs = [['name' => 'Acasă', 'url' => SITE_URL . '/']];
if ($cityName && $citySlug) {
    $breadcrumbs[] = ['name' => $cityName, 'url' => SITE_URL . '/' . $citySlug];
}
$breadcrumbs[] = ['name' => 'Locații', 'url' => SITE_URL . '/locatii'];
$breadcrumbs[] = ['name' => $name, 'url' => $canonicalUrl];

$structuredData = [[
    '@context' => 'https://schema.org',
    '@type' => 'LocalBusiness',
    'name' => $name,
    'url' => $canonicalUrl,
    'image' => $coverImage,
    'address' => $address ? [
        '@type' => 'PostalAddress',
        'streetAddress' => $address,
        'addressLocality' => $cityName,
        'addressCountry' => 'RO',
    ] : null,
    'aggregateRating' => ($rating && $reviewsCount > 0) ? [
        '@type' => 'AggregateRating',
        'ratingValue' => (float) $rating,
        'reviewCount' => $reviewsCount,
    ] : null,
    'description' => $pageDescription,
]];

include __DIR__ . '/includes/head.php';
include __DIR__ . '/includes/header.php';
?>

<main x-data="venuePage(<?= htmlspecialchars(json_encode([
    'activities' => $normalizedActivities,
    'gallery'    => array_merge($coverImage ? [$coverImage] : [], array_slice($gallery, 0, 5)),
]), ENT_QUOTES) ?>)">

<!-- HERO -->
<section class="relative overflow-hidden border-b border-ink/10">
    <div class="absolute inset-0 bg-[radial-gradient(circle_at_20%_10%,rgba(232,69,39,.14),transparent_34%),radial-gradient(circle_at_90%_25%,rgba(30,74,61,.16),transparent_30%)]"></div>
    <div class="relative max-w-7xl mx-auto px-4 sm:px-6 pt-10 lg:pt-14 pb-12 lg:pb-16">
        <nav aria-label="Breadcrumb" class="mb-6 text-sm text-ink-soft">
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

        <div class="grid lg:grid-cols-[1.04fr_.96fr] gap-10 lg:gap-14 items-center">
            <div>
                <div class="flex flex-wrap gap-2 mb-5">
                    <span class="stamp inline-flex items-center gap-2 px-3 py-1 rounded-full bg-vermilion text-paper border-vermilion font-mono text-[11px] tracking-[.12em]"><?= htmlspecialchars(strtoupper($typeLabel)) ?></span>
                    <?php if ($cityName): ?>
                        <span class="stamp inline-flex items-center gap-2 px-3 py-1 rounded-full bg-paper border-ink/30 text-ink-soft font-mono text-[11px] tracking-[.12em]"><?= htmlspecialchars(strtoupper($cityName)) ?></span>
                    <?php endif; ?>
                    <?php if (count($normalizedActivities) > 0): ?>
                        <span class="stamp inline-flex items-center gap-2 px-3 py-1 rounded-full bg-paper border-ink/30 text-ink-soft font-mono text-[11px] tracking-[.12em]"><?= count($normalizedActivities) ?> ACTIVITĂȚI</span>
                    <?php endif; ?>
                </div>

                <h1 class="font-display text-[clamp(2.6rem,7vw,6.2rem)] leading-[.86] font-bold max-w-4xl"><?= htmlspecialchars($name) ?></h1>

                <?php if ($shortDescription || $description): ?>
                    <p class="mt-6 text-xl lg:text-2xl text-ink-soft leading-relaxed max-w-2xl"><?= htmlspecialchars($shortDescription ?: mb_substr(strip_tags($description), 0, 220)) ?></p>
                <?php endif; ?>

                <div class="mt-7 flex flex-wrap items-center gap-3 text-sm">
                    <?php if (count($normalizedActivities) > 0): ?>
                        <a href="#activitati" class="px-6 py-3 rounded-full bg-ink text-paper font-bold hover:bg-ink-2 transition">Vezi activitățile</a>
                    <?php endif; ?>
                    <a href="#program" class="px-6 py-3 rounded-full bg-paper border-2 border-ink font-bold hover:bg-ink hover:text-paper transition">Program & adresă</a>
                    <a href="#faq" class="px-6 py-3 rounded-full bg-paper-2 border border-ink/10 font-bold hover:bg-paper-3 transition">Întrebări</a>
                </div>

                <div class="mt-8 grid grid-cols-2 sm:grid-cols-4 gap-3 max-w-2xl">
                    <?php if ($rating): ?>
                        <div class="bg-paper/80 border border-ink/10 rounded-2xl p-4">
                            <p class="font-display text-2xl font-bold"><?= htmlspecialchars(number_format((float) $rating, 1)) ?></p>
                            <p class="text-xs text-ink-soft">rating mediu</p>
                        </div>
                    <?php endif; ?>
                    <?php if ($reviewsCount > 0): ?>
                        <div class="bg-paper/80 border border-ink/10 rounded-2xl p-4">
                            <p class="font-display text-2xl font-bold"><?= htmlspecialchars((string) $reviewsCount) ?></p>
                            <p class="text-xs text-ink-soft">recenzii</p>
                        </div>
                    <?php endif; ?>
                    <?php if ($cheapestPrice !== null): ?>
                        <div class="bg-paper/80 border border-ink/10 rounded-2xl p-4">
                            <p class="font-display text-2xl font-bold"><?= htmlspecialchars((string) $cheapestPrice) ?> lei</p>
                            <p class="text-xs text-ink-soft">de la / bilet</p>
                        </div>
                    <?php endif; ?>
                    <div class="bg-paper/80 border border-ink/10 rounded-2xl p-4">
                        <p class="font-display text-2xl font-bold">QR</p>
                        <p class="text-xs text-ink-soft">intrare rapidă</p>
                    </div>
                </div>
            </div>

            <div class="relative">
                <div class="ticket bg-paper border-2 border-ink rounded-[2rem] overflow-hidden shadow-deep" style="--perf:100%">
                    <div class="relative aspect-[4/3] bg-ink">
                        <template x-for="(img,i) in gallery" :key="img">
                            <img x-show="active===i" x-transition.opacity :src="img" :alt="<?= htmlspecialchars(json_encode($name), ENT_QUOTES) ?> + ' — imagine ' + (i+1)" class="absolute inset-0 w-full h-full object-cover opacity-90" loading="lazy" onerror="this.style.display='none'">
                        </template>
                        <div x-show="gallery.length === 0" class="absolute inset-0 grid place-items-center">
                            <span class="text-8xl opacity-30">📍</span>
                        </div>
                        <div class="absolute inset-0 bg-gradient-to-t from-ink/70 via-ink/10 to-transparent pointer-events-none"></div>
                        <div class="absolute left-5 right-5 bottom-5 flex items-end justify-between gap-4">
                            <div>
                                <p class="font-mono text-[10px] tracking-[.22em] text-paper/70">GALERIE LOCAȚIE</p>
                                <p class="font-display text-3xl text-paper font-bold"><?= htmlspecialchars($typeLabel) ?></p>
                            </div>
                            <div class="flex gap-2" x-show="gallery.length > 1">
                                <template x-for="(img,i) in gallery" :key="i">
                                    <button @click="active=i" :class="active===i ? 'bg-vermilion w-7' : 'bg-paper/60 w-2.5'" class="h-2.5 rounded-full transition-all"></button>
                                </template>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- STICKY MINI NAV -->
<section class="sticky top-[64px] z-30 bg-paper/90 backdrop-blur-md border-b border-ink/10">
    <div class="max-w-7xl mx-auto px-4 sm:px-6">
        <div class="flex items-center gap-2 overflow-x-auto no-bar py-3 text-sm font-bold">
            <a href="#activitati" class="shrink-0 px-4 py-2 rounded-full bg-ink text-paper">Activități</a>
            <a href="#despre" class="shrink-0 px-4 py-2 rounded-full bg-paper-2 hover:bg-paper-3 transition">Despre</a>
            <a href="#program" class="shrink-0 px-4 py-2 rounded-full bg-paper-2 hover:bg-paper-3 transition">Program & adresă</a>
            <a href="#faq" class="shrink-0 px-4 py-2 rounded-full bg-paper-2 hover:bg-paper-3 transition">FAQ</a>
        </div>
    </div>
</section>

<!-- ACTIVITIES LIST -->
<section id="activitati" class="max-w-7xl mx-auto px-4 sm:px-6 py-14 lg:py-20">
    <div class="flex flex-col lg:flex-row lg:items-end lg:justify-between gap-6 mb-8">
        <div>
            <p class="font-mono text-xs tracking-[.2em] text-vermilion mb-3">ACTIVITĂȚI ÎN ACEASTĂ LOCAȚIE</p>
            <h2 class="font-display text-[clamp(2rem,5vw,4rem)] font-bold leading-[.92]">Alege experiența potrivită</h2>
            <p class="mt-4 text-ink-soft text-lg max-w-2xl">Aceeași locație poate avea bilete diferite: acces general, tururi ghidate, intervale orare, camere tematice, ateliere sau abonamente.</p>
        </div>
        <div class="ticket bg-paper-2 border-2 border-ink rounded-2xl p-4 max-w-md" style="--perf:100%">
            <p class="font-bold">Cumperi mai multe activități?</p>
            <p class="text-sm text-ink-soft mt-1">Rezervi separat fiecare activitate. Toate biletele ajung pe email, cu QR.</p>
        </div>
    </div>

    <div class="grid lg:grid-cols-[1fr_360px] gap-8 items-start">
        <div>
            <?php if (! empty($normalizedActivities)): ?>
                <p class="text-sm text-ink-soft mb-4"><?= count($normalizedActivities) ?> activități disponibile</p>
                <div class="space-y-4">
                    <?php foreach ($normalizedActivities as $a): ?>
                        <article class="ticket bg-paper border-2 border-ink rounded-3xl overflow-hidden shadow-soft hover:-translate-y-1 transition" style="--perf:72%; --punch:#F4EFE3">
                            <div class="grid md:grid-cols-[240px_1fr]">
                                <a href="<?= htmlspecialchars($a['url'], ENT_QUOTES) ?>" class="relative min-h-[220px] bg-ink block overflow-hidden">
                                    <?php if ($a['image']): ?>
                                        <img src="<?= htmlspecialchars($a['image'], ENT_QUOTES) ?>" alt="<?= htmlspecialchars($a['title'], ENT_QUOTES) ?>" class="absolute inset-0 w-full h-full object-cover opacity-90 transition-transform duration-700 hover:scale-105" loading="lazy">
                                    <?php else: ?>
                                        <div class="absolute inset-0 grid place-items-center text-paper/30 text-6xl">🎫</div>
                                    <?php endif; ?>
                                    <?php if ($a['category']): ?>
                                        <div class="absolute top-3 left-3 px-2.5 py-1 rounded-full bg-paper text-ink text-[11px] font-bold"><?= htmlspecialchars($a['category']) ?></div>
                                    <?php endif; ?>
                                    <?php if ($a['duration']): ?>
                                        <div class="absolute bottom-3 left-3 px-2.5 py-1 rounded-full bg-ink/80 text-paper text-[11px] font-mono"><?= htmlspecialchars($a['duration']) ?></div>
                                    <?php endif; ?>
                                </a>
                                <div class="p-5 lg:p-6 grid md:grid-cols-[1fr_150px] gap-5">
                                    <div>
                                        <h3 class="font-display text-2xl lg:text-3xl leading-[1] font-bold">
                                            <a href="<?= htmlspecialchars($a['url'], ENT_QUOTES) ?>" class="hover:text-vermilion transition"><?= htmlspecialchars($a['title']) ?></a>
                                        </h3>
                                        <?php if ($a['description']): ?>
                                            <p class="mt-3 text-ink-soft leading-relaxed line-clamp-3"><?= htmlspecialchars($a['description']) ?></p>
                                        <?php endif; ?>
                                        <?php if (! empty($a['tags'])): ?>
                                            <div class="mt-4 flex flex-wrap gap-2">
                                                <?php foreach ($a['tags'] as $tag): ?>
                                                    <span class="px-2.5 py-1 rounded-full bg-paper-2 border border-ink/10 text-xs font-bold"><?= htmlspecialchars((string) $tag) ?></span>
                                                <?php endforeach; ?>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                    <div class="md:border-l-2 md:border-dashed md:border-ink/20 md:pl-5 flex md:flex-col items-end md:items-stretch justify-between gap-4">
                                        <?php if ($a['price'] !== null): ?>
                                            <div class="text-right md:text-left">
                                                <p class="text-xs text-ink-soft">de la</p>
                                                <p class="font-display text-3xl font-bold"><?= htmlspecialchars((string) $a['price']) ?> lei</p>
                                            </div>
                                        <?php endif; ?>
                                        <a href="<?= htmlspecialchars($a['url'], ENT_QUOTES) ?>" class="inline-flex justify-center px-5 py-3 rounded-full bg-vermilion text-paper font-bold hover:bg-vermilion-d transition">Rezervă</a>
                                    </div>
                                </div>
                            </div>
                        </article>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div class="border-2 border-dashed border-ink/30 rounded-3xl p-10 text-center bg-paper-2">
                    <h3 class="font-display text-3xl font-bold">Nu există încă activități listate pentru această locație.</h3>
                    <p class="mt-2 text-ink-soft">Revino în curând sau caută în alte locații apropiate.</p>
                    <?php if ($citySlug): ?>
                        <a href="/<?= htmlspecialchars($citySlug, ENT_QUOTES) ?>" class="mt-5 inline-flex px-6 py-3 rounded-full bg-ink text-paper font-bold">Vezi activități în <?= htmlspecialchars($cityName) ?></a>
                    <?php else: ?>
                        <a href="/categorii" class="mt-5 inline-flex px-6 py-3 rounded-full bg-ink text-paper font-bold">Explorează categorii</a>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </div>

        <!-- Sticky venue card -->
        <aside class="lg:sticky lg:top-40 space-y-4">
            <div class="ticket bg-ink text-paper rounded-3xl overflow-hidden border-2 border-ink shadow-deep" style="--perf:100%">
                <div class="p-6">
                    <p class="font-mono text-[10px] tracking-[.22em] text-paper/50 mb-3">FIȘA LOCAȚIEI</p>
                    <h3 class="font-display text-3xl font-bold leading-none"><?= htmlspecialchars($name) ?></h3>
                    <div class="mt-5 space-y-3 text-sm">
                        <?php if ($address): ?>
                            <div class="flex items-start gap-3">
                                <svg viewBox="0 0 24 24" class="w-5 h-5 text-ochre shrink-0 mt-0.5" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M12 21s7-4.7 7-11a7 7 0 1 0-14 0c0 6.3 7 11 7 11Z"/><circle cx="12" cy="10" r="2.5"/></svg>
                                <span><?= htmlspecialchars($address) ?><?= $cityName ? ', ' . htmlspecialchars($cityName) : '' ?></span>
                            </div>
                        <?php endif; ?>
                        <?php if (! empty($venue['opening_hours'])): ?>
                            <div class="flex items-start gap-3">
                                <svg viewBox="0 0 24 24" class="w-5 h-5 text-ochre shrink-0 mt-0.5" fill="none" stroke="currentColor" stroke-width="1.8"><circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/></svg>
                                <span><?= htmlspecialchars(is_string($venue['opening_hours']) ? $venue['opening_hours'] : 'Vezi programul') ?></span>
                            </div>
                        <?php endif; ?>
                        <?php if (count($normalizedActivities) > 0): ?>
                            <div class="flex items-start gap-3">
                                <svg viewBox="0 0 24 24" class="w-5 h-5 text-ochre shrink-0 mt-0.5" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M20 7H4m16 5H4m16 5H4"/></svg>
                                <span><?= count($normalizedActivities) ?> activități disponibile</span>
                            </div>
                        <?php endif; ?>
                    </div>
                    <div class="mt-6 grid grid-cols-2 gap-2">
                        <a href="#program" class="px-4 py-3 rounded-2xl bg-paper text-ink font-bold text-center hover:bg-paper-2 transition">Adresă</a>
                        <a href="#activitati" class="px-4 py-3 rounded-2xl bg-vermilion text-paper font-bold text-center hover:bg-vermilion-d transition">Bilete</a>
                    </div>
                </div>
            </div>

            <div class="bg-paper-2 rounded-3xl border border-ink/10 p-5">
                <p class="font-bold">Căutări SEO targetate</p>
                <div class="mt-3 flex flex-wrap gap-2 text-sm">
                    <?php if ($citySlug): ?>
                        <a href="/<?= htmlspecialchars($citySlug, ENT_QUOTES) ?>" class="px-3 py-1.5 rounded-full bg-paper border border-ink/10 hover:bg-ink hover:text-paper transition">activități <?= htmlspecialchars($cityName) ?></a>
                        <a href="/<?= htmlspecialchars($citySlug, ENT_QUOTES) ?>/activitati-copii" class="px-3 py-1.5 rounded-full bg-paper border border-ink/10 hover:bg-ink hover:text-paper transition">copii <?= htmlspecialchars($cityName) ?></a>
                        <a href="/<?= htmlspecialchars($citySlug, ENT_QUOTES) ?>/activitati-weekend" class="px-3 py-1.5 rounded-full bg-paper border border-ink/10 hover:bg-ink hover:text-paper transition">weekend <?= htmlspecialchars($cityName) ?></a>
                    <?php endif; ?>
                </div>
            </div>
        </aside>
    </div>
</section>

<!-- ABOUT -->
<?php if ($description): ?>
    <section id="despre" class="bg-paper-2 border-y border-ink/10">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 py-16 lg:py-24 grid lg:grid-cols-[.9fr_1.1fr] gap-12">
            <div>
                <p class="font-mono text-xs tracking-[.2em] text-vermilion mb-3">DESPRE LOCAȚIE</p>
                <h2 class="font-display text-[clamp(2rem,5vw,4rem)] font-bold leading-[.92]"><?= htmlspecialchars($name) ?> — <span class="text-vermilion italic">despre</span></h2>
            </div>
            <div class="text-[17px] leading-relaxed text-ink-soft prose-venue">
                <?= $description /* Trusted HTML from API; sanitize if external input ever lands here */ ?>
            </div>
        </div>
    </section>
<?php endif; ?>

<!-- PROGRAM & ADDRESS -->
<section id="program" class="max-w-7xl mx-auto px-4 sm:px-6 py-16 lg:py-24">
    <div class="grid lg:grid-cols-[1fr_1fr] gap-8 lg:gap-12">
        <div>
            <p class="font-mono text-xs tracking-[.2em] text-vermilion mb-3">PROGRAM, ADRESĂ & ACCES</p>
            <h2 class="font-display text-[clamp(2rem,5vw,3.8rem)] font-bold leading-[.95]">Cum ajungi la <?= htmlspecialchars($name) ?></h2>

            <div class="mt-8 grid sm:grid-cols-2 gap-4">
                <?php if ($address): ?>
                    <div class="rounded-3xl bg-paper-2 border border-ink/10 p-5">
                        <p class="font-bold text-lg">Adresă</p>
                        <p class="mt-2 text-ink-soft"><?= htmlspecialchars($address) ?><?= $cityName ? '<br>' . htmlspecialchars($cityName) : '' ?></p>
                        <a href="https://maps.google.com/?q=<?= urlencode($address . ' ' . $cityName) ?>" target="_blank" rel="noopener" class="inline-flex mt-3 text-vermilion font-bold underline-wobble">Deschide în Maps</a>
                    </div>
                <?php endif; ?>
                <?php if (! empty($venue['opening_hours'])): ?>
                    <div class="rounded-3xl bg-paper-2 border border-ink/10 p-5">
                        <p class="font-bold text-lg">Program</p>
                        <p class="mt-2 text-ink-soft"><?= htmlspecialchars(is_string($venue['opening_hours']) ? $venue['opening_hours'] : 'Vezi pagina locației') ?></p>
                    </div>
                <?php endif; ?>
                <div class="rounded-3xl bg-paper-2 border border-ink/10 p-5">
                    <p class="font-bold text-lg">Recomandare</p>
                    <p class="mt-2 text-ink-soft">Ajungi cu 10–15 minute înainte de intervalul ales, mai ales pentru activitățile cu grup.</p>
                </div>
                <div class="rounded-3xl bg-paper-2 border border-ink/10 p-5">
                    <p class="font-bold text-lg">Bilet QR</p>
                    <p class="mt-2 text-ink-soft">După plată primești biletul cu QR pe email și în cont. Nu trebuie să-l printezi.</p>
                </div>
            </div>
        </div>

        <div class="ticket bg-ink rounded-[2rem] overflow-hidden border-2 border-ink min-h-[420px] relative" style="--perf:100%">
            <div class="absolute inset-0 opacity-70" style="background-image:linear-gradient(rgba(244,239,227,.08) 1px, transparent 1px), linear-gradient(90deg, rgba(244,239,227,.08) 1px, transparent 1px); background-size:38px 38px;"></div>
            <div class="absolute inset-8 rounded-[1.5rem] border-2 border-paper/25"></div>
            <div class="absolute left-1/2 top-1/2 -translate-x-1/2 -translate-y-1/2 text-center text-paper">
                <div class="mx-auto w-20 h-20 rounded-full bg-vermilion grid place-items-center shadow-deep">
                    <svg viewBox="0 0 24 24" class="w-9 h-9" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M12 21s7-4.7 7-11a7 7 0 1 0-14 0c0 6.3 7 11 7 11Z"/><circle cx="12" cy="10" r="2.5"/></svg>
                </div>
                <p class="font-display text-4xl font-bold mt-4"><?= htmlspecialchars($cityName ?: 'România') ?></p>
                <p class="text-paper/65 mt-2 max-w-sm"><?= htmlspecialchars($address ?: 'Vezi pagina locației pentru detalii despre poziție.') ?></p>
            </div>
        </div>
    </div>
</section>

<!-- FAQ -->
<section id="faq" class="max-w-5xl mx-auto px-4 sm:px-6 py-16 lg:py-24" x-data="{open:0}">
    <div class="text-center max-w-3xl mx-auto">
        <p class="stamp inline-flex px-3 py-1 text-xs font-mono tracking-[.18em] text-vermilion">FAQ</p>
        <h2 class="mt-5 font-display text-5xl sm:text-6xl font-bold leading-[.9]">Întrebări frecvente</h2>
    </div>
    <div class="mt-10 space-y-3">
        <?php $faqs = [
            ['Cum cumpăr biletele pentru ' . $name . '?', 'Alegi activitatea de mai sus, selectezi data și ora, completezi datele și primești biletul cu QR pe email.'],
            ['Pot anula sau reprograma biletul?', 'Politica de anulare este stabilită de fiecare locație și e afișată pe pagina activității înainte de plată.'],
            ['Trebuie să-mi creez cont?', 'Nu, poți cumpăra ca invitat. Contul îți ajută însă să-ți regăsești biletele și istoricul.'],
            ['Cum intru cu biletul la locație?', 'Arăți codul QR de pe bilet — în email sau în cont. Personalul scanează și ești înăuntru.'],
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

</main>

<style>
    .prose-venue p { margin-bottom: 1rem; }
    .prose-venue a { color: #E84527; background-image: linear-gradient(currentColor, currentColor); background-size: 100% 2px; background-repeat: no-repeat; background-position: 0 100%; }
    .prose-venue a:hover { background-size: 0 2px; }
    .no-bar::-webkit-scrollbar { display: none; }
    .no-bar { -ms-overflow-style: none; scrollbar-width: none; }
</style>

<script>
function venuePage(data) {
    return {
        active: 0,
        gallery: data.gallery || [],
        activities: data.activities || [],
    };
}
</script>

<?php include __DIR__ . '/includes/footer.php'; ?>
