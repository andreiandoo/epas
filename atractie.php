<?php
/**
 * Single attraction landing — /atractie/{slug}  (F4).
 *
 * Pure render: expects $_GET['slug']. Pulls the attraction detail (name,
 * description, gallery, geo, type, city + linked activities) from the core API
 * and renders a GYG-style POI page that cross-links to the activities that
 * cover it. Non-breaking: 404s cleanly when the slug isn't a real attraction.
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

$resp = api_cached("attraction_{$slug}", fn () => api_get('/attractions/' . $slug), 120);
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

$atName    = $attraction['name'] ?? 'Atracție';
$atSubtitle = $attraction['subtitle'] ?? '';
$atDesc    = $attraction['description'] ?? '';
$atType    = $attraction['type']['name'] ?? '';
$atCity    = $attraction['city'] ?? null;
$atCitySlug = $atCity['slug'] ?? '';
$atCityName = $atCity['name'] ?? '';
$atCover   = $bo_img($attraction['cover_image_url'] ?? '');
$atGallery = array_values(array_filter(array_map($bo_img, (array) ($attraction['gallery'] ?? []))));
$atAddress = $attraction['address'] ?? '';
$atLat     = $attraction['latitude'] ?? null;
$atLng     = $attraction['longitude'] ?? null;
$atActivities = is_array($attraction['activities'] ?? null) ? $attraction['activities'] : [];

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
    'address' => $atAddress ? ['@type' => 'PostalAddress', 'streetAddress' => $atAddress, 'addressLocality' => $atCityName] : null,
    'geo' => ($atLat && $atLng) ? ['@type' => 'GeoCoordinates', 'latitude' => $atLat, 'longitude' => $atLng] : null,
]];

include __DIR__ . '/includes/head.php';
include __DIR__ . '/includes/header.php';
?>

<!-- HERO -->
<section class="relative overflow-hidden border-b border-ink/10 bg-paper">
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
                <p class="font-mono text-xs tracking-[.18em] text-vermilion"><?= htmlspecialchars(trim(mb_strtoupper($atType) . ($atCityName ? ' · ' . mb_strtoupper($atCityName) : ''), ' ·')) ?: 'ATRACȚIE' ?></p>
                <h1 class="mt-3 font-display text-6xl font-bold leading-[.86] sm:text-7xl"><?= htmlspecialchars($atName) ?></h1>
                <?php if ($atSubtitle): ?><p class="mt-4 font-display text-2xl text-ink-soft"><?= htmlspecialchars($atSubtitle) ?></p><?php endif; ?>
                <?php if ($atAddress): ?>
                    <p class="mt-5 inline-flex items-center gap-2 rounded-full bg-paper-2 px-4 py-2 text-sm font-bold text-ink-soft">
                        <svg viewBox="0 0 24 24" class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 21s-7-5.6-7-11a7 7 0 0 1 14 0c0 5.4-7 11-7 11Z"/><circle cx="12" cy="10" r="2.5"/></svg>
                        <?= htmlspecialchars($atAddress) ?>
                    </p>
                <?php endif; ?>
            </div>
            <div class="h-[420px] overflow-hidden rounded-[2rem] border-2 border-ink bg-ink">
                <?php if ($atCover): ?>
                    <img src="<?= htmlspecialchars($atCover, ENT_QUOTES) ?>" alt="<?= htmlspecialchars($atName, ENT_QUOTES) ?>" class="h-full w-full object-cover" loading="eager">
                <?php else: ?>
                    <div class="grid h-full place-items-center bg-gradient-to-br from-vermilion via-ochre to-forest text-paper"><span class="px-6 text-center font-display text-4xl font-bold"><?= htmlspecialchars($atName) ?></span></div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</section>

<!-- DESCRIPTION + MAP -->
<section class="bg-paper">
    <div class="mx-auto grid max-w-[1500px] gap-8 px-4 py-12 sm:px-6 lg:grid-cols-[1.2fr_.8fr] lg:py-16">
        <div>
            <?php if ($atDesc): ?>
                <p class="font-mono text-xs tracking-[.18em] text-vermilion">DESPRE</p>
                <div class="article-prose mt-3 max-w-3xl text-lg leading-relaxed text-ink-soft"><?= nl2br(htmlspecialchars($atDesc)) ?></div>
            <?php endif; ?>
            <?php if (!empty($atGallery)): ?>
                <div class="mt-8 grid grid-cols-2 gap-3 sm:grid-cols-3">
                    <?php foreach (array_slice($atGallery, 0, 6) as $g): ?>
                        <img src="<?= htmlspecialchars($g, ENT_QUOTES) ?>" alt="<?= htmlspecialchars($atName, ENT_QUOTES) ?>" class="h-40 w-full rounded-2xl border-2 border-ink object-cover" loading="lazy">
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
        <?php if ($atLat && $atLng): ?>
        <div class="overflow-hidden rounded-[2rem] border-2 border-ink">
            <iframe title="Hartă <?= htmlspecialchars($atName, ENT_QUOTES) ?>" width="100%" height="380" style="border:0" loading="lazy" referrerpolicy="no-referrer-when-downgrade"
                src="https://www.google.com/maps?q=<?= urlencode($atLat . ',' . $atLng) ?>&z=15&output=embed"></iframe>
            <a href="https://www.google.com/maps/search/?api=1&query=<?= urlencode($atLat . ',' . $atLng) ?>" target="_blank" rel="noopener" class="block bg-ink px-5 py-3 text-center font-bold text-paper transition hover:bg-vermilion">Deschide în Google Maps</a>
        </div>
        <?php endif; ?>
    </div>
</section>

<!-- LINKED ACTIVITIES -->
<?php if (!empty($atActivities)): ?>
<section class="bg-paper-2 border-y-2 border-ink">
    <div class="mx-auto max-w-[1500px] px-4 py-12 sm:px-6 lg:py-16">
        <div class="flex flex-wrap items-end justify-between gap-4">
            <div>
                <p class="font-mono text-xs tracking-[.18em] text-vermilion">EXPERIENȚE</p>
                <h2 class="mt-2 font-display text-5xl font-bold leading-none">Activități la <?= htmlspecialchars($atName) ?></h2>
            </div>
            <?php if ($atCitySlug): ?><a href="/<?= htmlspecialchars($atCitySlug) ?>" class="rounded-full border-2 border-ink px-5 py-2.5 text-sm font-bold transition hover:bg-ink hover:text-paper">Vezi <?= htmlspecialchars($atCityName) ?></a><?php endif; ?>
        </div>
        <div class="mt-8 grid gap-5 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4">
            <?php foreach ($atActivities as $a): ?>
                <a href="<?= htmlspecialchars($cardUrl($a)) ?>" class="group overflow-hidden rounded-[1.5rem] border-2 border-ink bg-paper shadow-ticket transition hover:-translate-y-1">
                    <div class="relative h-44 overflow-hidden bg-ink">
                        <?php if (!empty($a['cover_image_url'])): ?>
                            <img src="<?= htmlspecialchars($bo_img($a['cover_image_url']), ENT_QUOTES) ?>" alt="<?= htmlspecialchars($a['title'] ?? '', ENT_QUOTES) ?>" class="h-full w-full object-cover transition duration-500 group-hover:scale-105" loading="lazy">
                        <?php else: ?>
                            <div class="grid h-full place-items-center bg-gradient-to-br from-vermilion via-ochre to-forest text-paper"><span class="px-3 text-center font-display text-lg font-bold"><?= htmlspecialchars(mb_substr($a['title'] ?? '', 0, 20)) ?></span></div>
                        <?php endif; ?>
                        <?php if (!empty($a['category']['name'])): ?><span class="absolute left-3 top-3 rounded-full bg-paper px-3 py-1 text-xs font-bold text-ink"><?= htmlspecialchars($a['category']['name']) ?></span><?php endif; ?>
                    </div>
                    <div class="p-4">
                        <p class="font-display text-xl font-bold leading-tight line-clamp-2 group-hover:text-vermilion"><?= htmlspecialchars($a['title'] ?? '') ?></p>
                        <p class="mt-2 text-sm text-ink-soft"><?= htmlspecialchars(trim(($a['city']['name'] ?? '') . (!empty($a['duration_minutes']) ? ' · ' . $durationLabel((int) $a['duration_minutes']) : ''), ' ·')) ?></p>
                        <?php if (!empty($a['cheapest_price_cents'])): ?><p class="mt-3 font-bold"><span class="text-xs font-normal text-ink-soft">de la</span> <?= $pricedFromCents($a['cheapest_price_cents']) ?></p><?php endif; ?>
                    </div>
                </a>
            <?php endforeach; ?>
        </div>
    </div>
</section>
<?php endif; ?>

<?php include __DIR__ . '/includes/footer.php'; ?>
