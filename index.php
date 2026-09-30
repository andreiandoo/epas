<?php
/**
 * Homepage - Ambilet.ro
 * Based on ticketing-homepage-v3.html template
 */

$pageCacheTTL = 300; // 5 minutes
require_once __DIR__ . '/includes/page-cache.php';

require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/api.php';

// "Azi" strip — promotes /azi with today's count + a few posters. Same
// cache key/query as includes/when-events-page.php, so /azi and the
// homepage share one API call and always show the same number.
$todayEvents = [];
$todayResponse = api_cached('when_events_today', function () {
    return api_get('/marketplace-events?date=today&per_page=200&sort=date_asc');
}, 300);
if (is_array($todayResponse)) {
    $rawToday = $todayResponse['data']['data'] ?? $todayResponse['data']['events'] ?? $todayResponse['data'] ?? [];
    $todayDate = (new DateTime('now', new DateTimeZone('Europe/Bucharest')))->format('Y-m-d');
    foreach (is_array($rawToday) ? $rawToday : [] as $ev) {
        if (!is_array($ev) || !empty($ev['is_cancelled'])) continue;
        // Stale cache after midnight: drop yesterday's single-day events.
        $evDate = substr((string) ($ev['starts_at'] ?? ''), 0, 10);
        $isRange = ($ev['duration_mode'] ?? 'single_day') !== 'single_day';
        if (!$isRange && $evDate !== '' && $evDate < $todayDate) continue;
        $todayEvents[] = $ev;
    }
}
$todayCount = count($todayEvents);
$todayPosters = [];
foreach ($todayEvents as $ev) {
    $img = $ev['image'] ?? $ev['poster_url'] ?? $ev['hero_image_url'] ?? null;
    if ($img) $todayPosters[] = ['img' => $img, 'name' => (string) ($ev['name'] ?? $ev['title'] ?? '')];
    if (count($todayPosters) >= 4) break;
}
$todayNames = array_slice(array_values(array_filter(array_map(fn ($e) => trim((string) ($e['name'] ?? $e['title'] ?? '')), $todayEvents))), 0, 2);

$pageTitle = 'Bilete Evenimente Romania';
$pageDescription = 'Cumpara bilete online pentru concerte, festivaluri, teatru, sport si multe altele. Platforma de ticketing pentru evenimente din Romania.';
$currentPage = 'home';
$transparentHeader = false;
$headExtra = '<link rel="stylesheet" href="' . asset('assets/css/homepage.css') . '">';

$cssBundle = 'home';
require_once __DIR__ . '/includes/head.php';
require_once __DIR__ . '/includes/header.php';
?>

<!-- Hero Carousel - 3D Poster Stack -->
<section class="relative pt-0 pb-4 overflow-hidden bg-gradient-to-b from-gray-900 via-gray-800 to-gray-900 mt-17 mobile:pt-10" id="heroSlider">
    <div class="px-4 mx-auto max-w-7xl">
        <div class="hero-carousel-wrapper">
            <!-- 3D Carousel Container -->
            <div id="heroCarousel" class="hero-carousel">
                <!-- Loading Skeleton -->
                <div class="hero-skeleton-item skeleton" style="transform: rotateY(20deg) translateX(240px); opacity: 0.4;"></div>
                <div class="hero-skeleton-item skeleton" style="transform: rotateY(10deg) translateX(120px); opacity: 0.6;"></div>
                <div class="hero-skeleton-item skeleton" style="z-index: 3;"></div>
                <div class="hero-skeleton-item skeleton" style="transform: rotateY(-10deg) translateX(-120px); opacity: 0.6;"></div>
                <div class="hero-skeleton-item skeleton" style="transform: rotateY(-20deg) translateX(-240px); opacity: 0.4;"></div>
            </div>
            <!-- Dot Indicators -->
            <div id="heroDots" class="hidden hero-dots">
                <?php for ($i = 0; $i < 5; $i++): ?>
                <button class="hero-dot <?= $i === 0 ? 'active' : '' ?>" name="heroDot<?= $i ?>"></button>
                <?php endfor; ?>
            </div>
        </div>
    </div>
</section>

<?php if ($todayCount > 0): ?>
<!-- Azi pe Ambilet: promotes /azi (server-rendered, no lazy flash) -->
<section class="bg-gray-900" aria-label="Evenimente azi">
    <div class="px-4 pb-6 mx-auto max-w-7xl">
        <a href="/azi" class="flex items-center gap-4 p-3 transition-all border group rounded-2xl border-white/10 bg-white/5 hover:bg-white/10 hover:border-primary/50 md:p-4 md:gap-5">
            <div class="flex items-center flex-shrink-0">
                <?php foreach ($todayPosters as $i => $p): ?>
                <img src="<?= htmlspecialchars($p['img']) ?>" alt="<?= htmlspecialchars($p['name']) ?>" width="48" height="64" loading="lazy" decoding="async" class="object-cover w-10 aspect-[3/4] border-2 border-gray-900 rounded-lg shadow-lg md:w-12<?= $i >= 2 ? ' hidden sm:block' : '' ?>" style="transform: rotate(<?= ($i - 1) * 4 ?>deg);<?= $i > 0 ? ' margin-left: -12px;' : '' ?>">
                <?php endforeach; ?>
                <?php if (empty($todayPosters)): ?>
                <span class="flex items-center justify-center w-12 h-12 text-white rounded-xl bg-primary">
                    <svg class="w-6 h-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
                </span>
                <?php endif; ?>
            </div>
            <div class="flex-1 min-w-0">
                <div class="flex items-center gap-2 mb-0.5">
                    <span class="relative flex w-2 h-2" aria-hidden="true">
                        <span class="absolute inline-flex w-full h-full rounded-full opacity-75 animate-ping bg-primary"></span>
                        <span class="relative inline-flex w-2 h-2 rounded-full bg-primary"></span>
                    </span>
                    <span class="text-[11px] font-bold tracking-widest uppercase text-primary">Azi</span>
                </div>
                <p class="text-base font-bold text-white md:text-lg">
                    <?= $todayCount ?> <?= $todayCount === 1 ? 'eveniment are loc' : 'evenimente au loc' ?> azi
                </p>
                <?php if ($todayNames): ?>
                <p class="text-xs text-gray-400 truncate md:text-sm"><?= htmlspecialchars(implode(', ', $todayNames)) ?><?= $todayCount > count($todayNames) ? ' și altele' : '' ?></p>
                <?php endif; ?>
            </div>
            <span class="inline-flex items-center flex-shrink-0 gap-1.5 px-4 py-2.5 text-sm font-bold text-white rounded-xl bg-primary">
                <span class="hidden sm:inline">Vezi ce e azi</span>
                <svg class="w-4 h-4 transition-transform group-hover:translate-x-1" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M5 12h14M12 5l7 7-7 7"/></svg>
            </span>
        </a>
    </div>
</section>
<?php endif; ?>

<?php // require_once __DIR__ . '/includes/featured-carousel.php'; ?>

<!-- Promoted & Recommended Events -->
<section class="py-10 bg-primary/20 md:py-14">
    <div class="px-4 mx-auto max-w-7xl">
        <h2 class="mb-6 text-lg font-bold text-center text-primary md:text-2xl">Nu rata aceste evenimente</h2>
        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 md:grid-cols-6 lg:grid-cols-6 md:gap-5" id="promotedEventsGrid">
            <!-- Promoted events will be loaded dynamically -->
            <?php for ($i = 0; $i < 12; $i++): ?>
            <div class="overflow-hidden bg-white border rounded-xl border-border">
                <div class="skeleton aspect-[2/3]"></div>
                <div class="h-8 skeleton"></div>
                <div class="p-3">
                    <div class="skeleton skeleton-title"></div>
                    <div class="w-2/3 mt-2 skeleton skeleton-text"></div>
                </div>
            </div>
            <?php endfor; ?>
        </div>
    </div>
</section>

<!-- Categories -->
<section class="py-8 bg-primary lazy-section" id="categoriesSection" data-lazy-load="categories">
    <div class="px-4 mx-auto max-w-7xl">
        <h2 class="mb-4 text-lg font-bold text-center text-white md:text-2xl">Explorează după categorie</h2>

        <div class="grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-6 md:gap-4" id="categoriesGrid">
            <!-- Categories will be loaded dynamically -->
            <div class="skeleton rounded-2xl" style="height: 120px;"></div>
            <div class="skeleton rounded-2xl" style="height: 120px;"></div>
            <div class="skeleton rounded-2xl" style="height: 120px;"></div>
            <div class="skeleton rounded-2xl" style="height: 120px;"></div>
            <div class="skeleton rounded-2xl" style="height: 120px;"></div>
            <div class="skeleton rounded-2xl" style="height: 120px;"></div>
        </div>

        <!-- City Filter Buttons — filter "ultimele evenimente adăugate" below.
             Lives here (not in #cityEventsSection) so the red band shows up
             together with the categories instead of fading in later. -->
        <div class="flex flex-wrap justify-center gap-2 mt-6" id="cityFilterButtons">
            <button class="px-4 py-2 text-sm font-semibold text-white transition-all rounded-full city-filter-btn active bg-primary" data-city="">
                Toate
            </button>
            <!-- City buttons will be loaded dynamically -->
        </div>
    </div>
</section>

<!-- Events by City (Combined with Latest Events) -->
<section class="lazy-section" id="cityEventsSection" data-lazy-load="cityEvents">
    <div class="py-8 bg-white ">
        <div class="px-4 mx-auto max-w-7xl">
            <span class="block w-full mb-4 text-lg font-bold text-center text-second-text md:text-2xl">ultimele evenimente adăugate</span>
            <!-- Events Grid (filtered by city) -->
            <div class="grid grid-cols-2 gap-4 lg:grid-cols-4 xl:grid-cols-4 md:gap-5" id="cityEventsGrid">
                <!-- Events will be loaded dynamically -->
                <?php for ($i = 0; $i < 10; $i++): ?>
                <div class="overflow-hidden bg-white border rounded-2xl border-border">
                    <div class="skeleton h-44"></div>
                    <div class="p-4">
                        <div class="w-1/3 mb-2 skeleton skeleton-text"></div>
                        <div class="skeleton skeleton-title"></div>
                        <div class="w-2/3 mt-2 skeleton skeleton-text"></div>
                    </div>
                </div>
                <?php endfor; ?>
            </div>

            <div class="mt-10 text-center">
                <a href="/evenimente" class="inline-flex items-center gap-2 px-8 py-4 font-bold transition-all border-2 border-primary text-primary rounded-xl hover:bg-primary hover:text-white">
                    Vezi mai multe evenimente
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                    </svg>
                </a>
            </div>
        </div>
    </div>
</section>

<!-- Organizers CTA -->
<section class="py-12 bg-white md:py-16 lazy-section" id="organizersCta" data-lazy-load="static">
    <div class="px-4 mx-auto max-w-7xl">
        <div class="flex flex-col items-center gap-8 p-6 bg-surface rounded-3xl md:p-10 md:flex-row">
            <div class="flex-1">
                <span class="inline-block px-3 py-1.5 bg-accent/20 text-accent font-bold text-xs rounded-full mb-4 uppercase tracking-wide">Pentru Organizatori</span>
                <h2 class="mb-3 text-2xl font-bold md:text-3xl text-secondary">Vrei sa-ti vinzi biletele prin <?= SITE_NAME ?>?</h2>
                <p class="text-muted">Comisioane transparente, plati rapide si suport dedicat pentru organizatori.</p>
            </div>
            <div class="flex-shrink-0 hidden md:block">
                <div class="flex flex-wrap gap-3">
                    <a href="/organizator/register" class="inline-flex items-center gap-2 px-6 py-3 font-bold text-white btn-primary bg-primary rounded-xl">
                        Inregistreaza-te gratuit
                    </a>
                </div>
            </div>
        </div>
    </div>
</section>

<?php require_once __DIR__ . '/includes/footer.php'; ?>

<?php
$scriptsExtra = '<script defer src="' . asset('assets/js/pages/homepage.js') . '"></script>';
require_once __DIR__ . '/includes/scripts.php';
