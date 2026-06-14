<?php
/**
 * Single attraction landing — /atractie/{slug}  (F4, GYG-style v4 design).
 *
 * Pure render: expects $_GET['slug']. Pulls the attraction detail (name,
 * description, gallery, geo, type, city, county + linked activities + sibling
 * attractions in the same city/county) from the core API in a SINGLE call and
 * renders a GetYourGuide-style POI page that cross-links to the activities and
 * nearby attractions. Non-breaking: 404s cleanly when the slug isn't a real
 * attraction. Uses only the bilete.online prebuilt CSS token vocabulary
 * (paper / ink / vermilion / ochre / forest / mint / rose / shadow-ticket …).
 */

$pageCacheTTL = 300;
require_once __DIR__ . '/includes/page-cache.php';
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/api.php';
require_once __DIR__ . '/includes/nav-helpers.php';

$slug = $slug ?? ($_GET['slug'] ?? '');
if (!preg_match('/^[a-z][a-z0-9-]+$/', $slug)) {
    http_response_code(404);
    require __DIR__ . '/404.php';
    exit;
}

$resp = api_cached("attraction_{$slug}", fn () => api_get('/attractions/' . $slug), 300);
$attraction = $resp['data']['attraction'] ?? null;
if (!$attraction) {
    http_response_code(404);
    require __DIR__ . '/404.php';
    exit;
}

$bo_img = function ($u) {
    $u = (string) $u;
    if ($u === '') return '';
    return str_starts_with($u, 'http') ? $u : rtrim(STORAGE_URL, '/') . '/' . ltrim($u, '/');
};

$atName       = $attraction['name'] ?? 'Atracție';
$atSubtitle   = $attraction['subtitle'] ?? '';
$atDesc       = $attraction['description'] ?? '';
$atType       = $attraction['type']['name'] ?? '';
$atTypeIcon   = $attraction['type']['icon'] ?? '';
$atCity       = $attraction['city'] ?? null;
$atCitySlug   = $atCity['slug'] ?? '';
$atCityName   = $atCity['name'] ?? '';
$atCounty     = $attraction['county'] ?? '';
$atCover      = $bo_img($attraction['cover_image_url'] ?? '');
$atGallery    = array_values(array_filter(array_map($bo_img, (array) ($attraction['gallery'] ?? []))));
$atAddress    = $attraction['address'] ?? '';
$atLat        = $attraction['latitude'] ?? null;
$atLng        = $attraction['longitude'] ?? null;
$atActivities = is_array($attraction['activities'] ?? null) ? $attraction['activities'] : [];
$cityAttractions   = is_array($attraction['city_attractions'] ?? null) ? $attraction['city_attractions'] : [];
$countyAttractions = is_array($attraction['county_attractions'] ?? null) ? $attraction['county_attractions'] : [];

// Lightbox images: cover first (if present), then the gallery.
$lightbox = array_values(array_filter(array_merge($atCover ? [$atCover] : [], $atGallery)));

$durationLabel = function (int $m): string {
    if ($m <= 0) return '';
    if ($m < 60) return $m . ' min';
    $h = intdiv($m, 60); $rest = $m % 60;
    return $rest ? "{$h}h {$rest}m" : "{$h}h";
};
$pricedFromCents = function ($c): string {
    if (!$c) return '';
    return number_format($c / 100, 0, ',', '.') . ' lei';
};
$cardUrl = function ($a) {
    $cs = $a['city']['slug'] ?? '';
    return $cs ? '/' . $cs . '/' . ($a['slug'] ?? '') : '/activitate/' . ($a['slug'] ?? '');
};

// Header context — treat as the city it sits in (so the header adapts).
$headerContext = $atCityName !== ''
    ? ['type' => 'city', 'label' => $atCityName, 'slug' => $atCitySlug]
    : ['type' => 'homepage'];

$breadcrumbs = [['name' => 'Acasă', 'url' => SITE_URL . '/']];
if ($atCityName && $atCitySlug) {
    $breadcrumbs[] = ['name' => $atCityName, 'url' => SITE_URL . '/' . $atCitySlug];
}
$breadcrumbs[] = ['name' => $atName, 'url' => SITE_URL . '/atractie/' . $slug];

// Kicker line (type · city)
$kicker = trim(mb_strtoupper($atType) . ($atCityName ? ' · ' . mb_strtoupper($atCityName) : ''), ' ·') ?: 'ATRACȚIE';

$pageTitleRaw = $atName . ($atCityName ? ' — ' . $atCityName : '') . ' | bilete.online';
$pageDescription = $atDesc !== '' ? mb_substr(trim(strip_tags($atDesc)), 0, 160) : ('Activități, tururi și bilete pentru ' . $atName . ($atCityName ? ' din ' . $atCityName : '') . '.');
$canonicalUrl = SITE_URL . '/atractie/' . $slug;
$ogImage = $atCover ?: (SITE_URL . '/assets/images/og-default.jpg');
$currentPage = 'attraction';
$cssBundle = 'listing';

$structuredData = [[
    '@context' => 'https://schema.org',
    '@type' => 'TouristAttraction',
    'name' => $atName,
    'description' => $pageDescription,
    'url' => $canonicalUrl,
    'image' => $atCover ?: null,
    'address' => $atAddress ? ['@type' => 'PostalAddress', 'streetAddress' => $atAddress, 'addressLocality' => $atCityName, 'addressRegion' => $atCounty] : null,
    'geo' => ($atLat && $atLng) ? ['@type' => 'GeoCoordinates', 'latitude' => $atLat, 'longitude' => $atLng] : null,
]];

include __DIR__ . '/includes/head.php';
include __DIR__ . '/includes/header.php';

// Small renderer for an attraction "rail" card (city/county columns).
$renderAttractionRow = function (array $p) use ($bo_img) {
    $img = $bo_img($p['cover_image_url'] ?? '');
    $name = $p['name'] ?? '';
    $meta = trim(($p['type']['name'] ?? '') . (!empty($p['city']['name']) ? ' · ' . $p['city']['name'] : ''), ' ·');
    $href = '/atractie/' . ($p['slug'] ?? '');
    ?>
    <a href="<?= htmlspecialchars($href, ENT_QUOTES) ?>" class="group flex items-center gap-4 rounded-2xl border-2 border-ink bg-paper p-3 transition hover:-translate-y-0.5 hover:shadow-ticket">
        <span class="h-20 w-24 shrink-0 overflow-hidden rounded-xl border border-ink/15 bg-ink">
            <?php if ($img): ?>
                <img src="<?= htmlspecialchars($img, ENT_QUOTES) ?>" alt="<?= htmlspecialchars($name, ENT_QUOTES) ?>" class="h-full w-full object-cover transition duration-500 group-hover:scale-105" loading="lazy">
            <?php else: ?>
                <span class="grid h-full place-items-center bg-gradient-to-br from-vermilion via-ochre to-forest text-[10px] font-bold text-paper">FOTO</span>
            <?php endif; ?>
        </span>
        <span class="min-w-0 flex-1">
            <span class="block truncate font-display text-2xl font-bold leading-none group-hover:text-vermilion"><?= htmlspecialchars($name) ?></span>
            <?php if ($meta): ?><span class="mt-1 block truncate text-sm text-ink-soft"><?= htmlspecialchars($meta) ?></span><?php endif; ?>
        </span>
        <span class="text-ink-soft transition group-hover:translate-x-1 group-hover:text-vermilion">→</span>
    </a>
    <?php
};
?>

<div x-data='{ lbOpen:false, lbIndex:0, imgs: <?= htmlspecialchars(json_encode($lightbox, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE), ENT_QUOTES) ?>,
    open(i){ this.lbIndex=i; this.lbOpen=true; document.body.style.overflow="hidden"; },
    close(){ this.lbOpen=false; document.body.style.overflow=""; },
    next(){ this.lbIndex=(this.lbIndex+1)%this.imgs.length; },
    prev(){ this.lbIndex=(this.lbIndex-1+this.imgs.length)%this.imgs.length; } }'
    @keydown.window.escape="close()">

<!-- ===================== HERO ===================== -->
<section class="relative overflow-hidden border-b-2 border-ink bg-paper">
    <div class="mx-auto max-w-[1500px] px-4 py-8 sm:px-6 lg:py-12">
        <nav aria-label="Breadcrumb" class="flex flex-wrap items-center gap-2 text-sm font-bold text-ink-soft">
            <?php foreach ($breadcrumbs as $i => $bc): ?>
                <?php if ($i > 0): ?><span aria-hidden="true">/</span><?php endif; ?>
                <?php if ($i < count($breadcrumbs) - 1): ?>
                    <a href="<?= htmlspecialchars($bc['url'], ENT_QUOTES) ?>" class="hover:text-vermilion"><?= htmlspecialchars($bc['name']) ?></a>
                <?php else: ?>
                    <span aria-current="page" class="text-ink"><?= htmlspecialchars($bc['name']) ?></span>
                <?php endif; ?>
            <?php endforeach; ?>
        </nav>

        <div class="mt-7 grid gap-8 lg:grid-cols-[minmax(0,1fr)_600px] lg:items-end">
            <div>
                <p class="font-mono text-xs tracking-[.18em] text-vermilion"><?= htmlspecialchars($kicker) ?></p>
                <h1 class="mt-3 font-display text-6xl font-bold leading-[.86] sm:text-7xl"><?= htmlspecialchars($atName) ?></h1>
                <?php if ($atSubtitle): ?><p class="mt-4 font-display text-2xl text-ink-soft"><?= htmlspecialchars($atSubtitle) ?></p><?php endif; ?>

                <div class="mt-6 flex flex-wrap items-center gap-3 text-sm font-bold">
                    <?php if ($atType): ?>
                        <span class="inline-flex items-center gap-2 rounded-full bg-paper-2 px-4 py-2 text-ink-soft">
                            <?php if ($atTypeIcon): ?><span><?= htmlspecialchars($atTypeIcon) ?></span><?php endif; ?>
                            <?= htmlspecialchars($atType) ?>
                        </span>
                    <?php endif; ?>
                    <?php if ($atAddress): ?>
                        <span class="inline-flex items-center gap-2 rounded-full bg-paper-2 px-4 py-2 text-ink-soft">
                            <svg viewBox="0 0 24 24" class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 21s-7-5.6-7-11a7 7 0 0 1 14 0c0 5.4-7 11-7 11Z"/><circle cx="12" cy="10" r="2.5"/></svg>
                            <?= htmlspecialchars($atAddress) ?>
                        </span>
                    <?php endif; ?>
                </div>

                <div class="mt-7 flex flex-wrap gap-3">
                    <?php if (!empty($atActivities)): ?>
                        <a href="#activitati" class="rounded-full bg-vermilion px-6 py-4 font-bold text-paper transition hover:bg-vermilion-d">Vezi ce poți face aici</a>
                    <?php elseif ($atCitySlug): ?>
                        <a href="/<?= htmlspecialchars($atCitySlug) ?>" class="rounded-full bg-vermilion px-6 py-4 font-bold text-paper transition hover:bg-vermilion-d">Activități în <?= htmlspecialchars($atCityName) ?></a>
                    <?php endif; ?>
                    <?php if ($atLat && $atLng): ?>
                        <a href="https://www.google.com/maps/search/?api=1&query=<?= urlencode($atLat . ',' . $atLng) ?>" target="_blank" rel="noopener" class="rounded-full border-2 border-ink bg-paper px-6 py-4 font-bold transition hover:bg-ink hover:text-paper">Deschide în Maps</a>
                    <?php endif; ?>
                </div>
            </div>

            <?php if ($lightbox): ?>
                <button type="button" @click="open(0)" class="group relative h-[420px] overflow-hidden rounded-[2rem] border-2 border-ink bg-ink shadow-deep">
                    <img src="<?= htmlspecialchars($lightbox[0], ENT_QUOTES) ?>" alt="<?= htmlspecialchars($atName, ENT_QUOTES) ?>" class="h-full w-full object-cover transition duration-500 group-hover:scale-105" loading="eager">
                    <span class="pointer-events-none absolute inset-0 bg-gradient-to-t from-ink/55 via-transparent to-transparent"></span>
                    <?php if (count($lightbox) > 1): ?>
                        <span class="absolute bottom-5 left-5 rounded-full bg-paper px-5 py-3 text-sm font-bold text-ink shadow-ticket">Vezi galeria (<?= count($lightbox) ?>)</span>
                    <?php endif; ?>
                </button>
            <?php else: ?>
                <div class="h-[420px] overflow-hidden rounded-[2rem] border-2 border-ink bg-ink">
                    <div class="grid h-full place-items-center bg-gradient-to-br from-vermilion via-ochre to-forest text-paper"><span class="px-6 text-center font-display text-4xl font-bold"><?= htmlspecialchars($atName) ?></span></div>
                </div>
            <?php endif; ?>
        </div>
    </div>
</section>

<!-- ===================== ABOUT + MAP ===================== -->
<section class="bg-paper">
    <div class="mx-auto grid max-w-[1500px] gap-8 px-4 py-12 sm:px-6 lg:grid-cols-[1.2fr_.8fr] lg:py-16">
        <div>
            <?php if ($atDesc): ?>
                <p class="font-mono text-xs tracking-[.18em] text-vermilion">DESPRE</p>
                <h2 class="mt-2 font-display text-5xl font-bold leading-none">Despre <?= htmlspecialchars($atName) ?></h2>
                <div class="article-prose mt-5 max-w-3xl text-lg leading-relaxed text-ink-soft"><?= nl2br(htmlspecialchars($atDesc)) ?></div>
            <?php endif; ?>

            <?php if (count($lightbox) > 1): ?>
                <div class="mt-8 grid grid-cols-2 gap-3 sm:grid-cols-3">
                    <?php foreach (array_slice($lightbox, 1, 6) as $gi => $g): ?>
                        <button type="button" @click="open(<?= $gi + 1 ?>)" class="overflow-hidden rounded-2xl border-2 border-ink bg-ink">
                            <img src="<?= htmlspecialchars($g, ENT_QUOTES) ?>" alt="<?= htmlspecialchars($atName, ENT_QUOTES) ?>" class="h-40 w-full object-cover transition duration-500 hover:scale-105" loading="lazy">
                        </button>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>

        <?php if ($atLat && $atLng): ?>
        <div class="self-start overflow-hidden rounded-[2rem] border-2 border-ink shadow-ticket">
            <iframe title="Hartă <?= htmlspecialchars($atName, ENT_QUOTES) ?>" width="100%" height="380" style="border:0" loading="lazy" referrerpolicy="no-referrer-when-downgrade"
                src="https://www.google.com/maps?q=<?= urlencode($atLat . ',' . $atLng) ?>&z=15&output=embed"></iframe>
            <a href="https://www.google.com/maps/search/?api=1&query=<?= urlencode($atLat . ',' . $atLng) ?>" target="_blank" rel="noopener" class="block bg-ink px-5 py-3 text-center font-bold text-paper transition hover:bg-vermilion">Deschide în Google Maps</a>
        </div>
        <?php endif; ?>
    </div>
</section>

<!-- ===================== LINKED ACTIVITIES ===================== -->
<section id="activitati" class="border-y-2 border-ink bg-paper-2">
    <div class="mx-auto max-w-[1500px] px-4 py-12 sm:px-6 lg:py-16">
        <div class="flex flex-wrap items-end justify-between gap-4">
            <div class="max-w-3xl">
                <p class="font-mono text-xs tracking-[.18em] text-vermilion">TURURI ȘI EXPERIENȚE</p>
                <h2 class="mt-2 font-display text-5xl font-bold leading-none">Activități la <?= htmlspecialchars($atName) ?></h2>
            </div>
            <?php if ($atCitySlug): ?><a href="/<?= htmlspecialchars($atCitySlug) ?>" class="rounded-full border-2 border-ink bg-paper px-5 py-2.5 text-sm font-bold transition hover:bg-ink hover:text-paper">Toate activitățile din <?= htmlspecialchars($atCityName) ?></a><?php endif; ?>
        </div>

        <?php if (!empty($atActivities)): ?>
            <div class="mt-8 grid gap-5 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4">
                <?php foreach ($atActivities as $a): ?>
                    <a href="<?= htmlspecialchars($cardUrl($a)) ?>" class="group flex flex-col overflow-hidden rounded-[1.5rem] border-2 border-ink bg-paper shadow-ticket transition hover:-translate-y-1">
                        <span class="relative block h-44 overflow-hidden bg-ink">
                            <?php if (!empty($a['cover_image_url'])): ?>
                                <img src="<?= htmlspecialchars($bo_img($a['cover_image_url']), ENT_QUOTES) ?>" alt="<?= htmlspecialchars($a['title'] ?? '', ENT_QUOTES) ?>" class="h-full w-full object-cover transition duration-500 group-hover:scale-105" loading="lazy">
                            <?php else: ?>
                                <span class="grid h-full place-items-center bg-gradient-to-br from-vermilion via-ochre to-forest text-paper"><span class="px-3 text-center font-display text-lg font-bold"><?= htmlspecialchars(mb_substr($a['title'] ?? '', 0, 20)) ?></span></span>
                            <?php endif; ?>
                            <?php if (!empty($a['category']['name'])): ?><span class="absolute left-3 top-3 rounded-full bg-paper px-3 py-1 text-xs font-bold text-ink shadow-ticket"><?= htmlspecialchars($a['category']['name']) ?></span><?php endif; ?>
                        </span>
                        <span class="flex flex-1 flex-col p-4">
                            <span class="font-display text-xl font-bold leading-tight line-clamp-2 group-hover:text-vermilion"><?= htmlspecialchars($a['title'] ?? '') ?></span>
                            <span class="mt-2 text-sm text-ink-soft"><?= htmlspecialchars(trim(($a['city']['name'] ?? '') . (!empty($a['duration_minutes']) ? ' · ' . $durationLabel((int) $a['duration_minutes']) : ''), ' ·')) ?></span>
                            <?php if (!empty($a['cheapest_price_cents'])): ?>
                                <span class="mt-auto pt-3 font-bold"><span class="text-xs font-normal text-ink-soft">de la</span> <?= $pricedFromCents($a['cheapest_price_cents']) ?></span>
                            <?php endif; ?>
                        </span>
                    </a>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <div class="mt-8 rounded-[1.5rem] border-2 border-dashed border-ink/30 bg-paper p-7">
                <h3 class="font-display text-3xl font-bold leading-none">Momentan nu există activități direct asociate.</h3>
                <p class="mt-3 text-ink-soft">Descoperă experiențe și evenimente disponibile în <?= htmlspecialchars($atCityName ?: 'zonă') ?>.</p>
                <?php if ($atCitySlug): ?><a href="/<?= htmlspecialchars($atCitySlug) ?>" class="mt-5 inline-flex rounded-full bg-ink px-5 py-3 font-bold text-paper transition hover:bg-vermilion">Vezi activitățile din <?= htmlspecialchars($atCityName) ?></a><?php endif; ?>
            </div>
        <?php endif; ?>
    </div>
</section>

<!-- ===================== EXPLORE CITY CTA ===================== -->
<?php if ($atCitySlug && $atCover): ?>
<section class="relative min-h-[360px] overflow-hidden border-b-2 border-ink">
    <img src="<?= htmlspecialchars($atCover, ENT_QUOTES) ?>" alt="<?= htmlspecialchars($atCityName, ENT_QUOTES) ?>" class="absolute inset-0 h-full w-full object-cover" loading="lazy">
    <div class="absolute inset-0 bg-ink/65"></div>
    <div class="relative mx-auto flex min-h-[360px] max-w-[1500px] items-center justify-center px-4 py-14 text-center sm:px-6">
        <div>
            <p class="font-mono text-xs tracking-[.18em] text-paper/70">EXPLOREAZĂ</p>
            <h2 class="mt-2 font-display text-5xl font-bold leading-none text-paper sm:text-6xl"><?= htmlspecialchars($atCityName) ?></h2>
            <a href="/<?= htmlspecialchars($atCitySlug) ?>" class="mt-7 inline-flex rounded-full bg-vermilion px-7 py-4 font-bold text-paper transition hover:bg-vermilion-d">Toate activitățile din <?= htmlspecialchars($atCityName) ?></a>
        </div>
    </div>
</section>
<?php endif; ?>

<!-- ===================== NEARBY ATTRACTIONS ===================== -->
<?php if (!empty($cityAttractions) || !empty($countyAttractions)): ?>
<section class="bg-paper">
    <div class="mx-auto max-w-[1500px] px-4 py-12 sm:px-6 lg:py-16">
        <div class="grid gap-12 lg:grid-cols-2">
            <?php if (!empty($cityAttractions)): ?>
            <div>
                <p class="font-mono text-xs tracking-[.18em] text-vermilion">ÎN <?= htmlspecialchars(mb_strtoupper($atCityName ?: 'ORAȘ')) ?></p>
                <h2 class="mt-2 font-display text-4xl font-bold leading-none">Alte atracții din oraș</h2>
                <div class="mt-6 space-y-3">
                    <?php foreach ($cityAttractions as $p) { $renderAttractionRow($p); } ?>
                </div>
            </div>
            <?php endif; ?>

            <?php if (!empty($countyAttractions)): ?>
            <div>
                <p class="font-mono text-xs tracking-[.18em] text-vermilion"><?= $atCounty ? 'JUDEȚUL ' . htmlspecialchars(mb_strtoupper($atCounty)) : 'ÎN APROPIERE' ?></p>
                <h2 class="mt-2 font-display text-4xl font-bold leading-none">Atracții din apropiere</h2>
                <div class="mt-6 space-y-3">
                    <?php foreach ($countyAttractions as $p) { $renderAttractionRow($p); } ?>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </div>
</section>
<?php endif; ?>

<!-- ===================== LIGHTBOX ===================== -->
<?php if ($lightbox): ?>
<div x-show="lbOpen" x-cloak @click.self="close()" class="fixed inset-0 z-[95] bg-ink/90 p-4 backdrop-blur-sm" style="display:none">
    <div class="mx-auto flex h-full max-w-6xl flex-col">
        <div class="mb-4 flex items-center justify-between text-paper">
            <p class="font-display text-2xl font-bold"><?= htmlspecialchars($atName) ?></p>
            <button type="button" @click="close()" class="grid h-11 w-11 place-items-center rounded-full bg-paper text-2xl text-ink">×</button>
        </div>
        <div class="grid min-h-0 flex-1 place-items-center">
            <img :src="imgs[lbIndex]" :alt="'<?= htmlspecialchars($atName, ENT_QUOTES) ?>'" class="max-h-full max-w-full rounded-[1.5rem] border-2 border-paper object-contain">
        </div>
        <div class="mt-4 flex items-center justify-center gap-2" x-show="imgs.length > 1">
            <button type="button" @click="prev()" class="rounded-full bg-paper px-5 py-3 font-bold text-ink">←</button>
            <span class="px-3 py-3 font-bold text-paper" x-text="(lbIndex+1)+' / '+imgs.length"></span>
            <button type="button" @click="next()" class="rounded-full bg-paper px-5 py-3 font-bold text-ink">→</button>
        </div>
    </div>
</div>
<?php endif; ?>

</div><!-- /x-data -->

<?php include __DIR__ . '/includes/footer.php'; ?>
