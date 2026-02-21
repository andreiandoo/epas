<?php
/**
 * TICS.ro - Venue Page
 * Shows venue details and upcoming events.
 * URL: /bilete-{venue-slug}  →  venue.php?venue={slug}
 */

require_once __DIR__ . '/includes/config.php';

$venueSlug = $_GET['venue'] ?? '';

if (!$venueSlug) {
    http_response_code(404);
    header('Location: /');
    exit;
}

// ── Fetch venue from API ──────────────────────────────────────────────────────
$venueResponse = callApi('venues/' . $venueSlug);

if (empty($venueResponse['success']) || empty($venueResponse['data'])) {
    http_response_code(404);
    header('Location: /');
    exit;
}

$venue = $venueResponse['data'];

$venueName      = $venue['name']        ?? '';
$venueCity      = $venue['city']        ?? '';
$venueDesc      = $venue['description'] ?? '';
$venueAddress   = $venue['address']     ?? '';
$venueCapacity  = (int) ($venue['capacity'] ?? 0);
$venueImage     = $venue['image']       ?? '';
$venueCover     = $venue['cover_image'] ?? $venueImage;
$venueEvCount   = (int) ($venue['events_count'] ?? 0);
$venueAmenities = $venue['amenities']   ?? [];
$venueUpcoming  = $venue['upcoming_events'] ?? [];
$venueCategories = $venue['categories'] ?? [];
$venueContact   = $venue['contact']     ?? [];
$venueSocial    = $venue['social']      ?? [];
$venueLat       = $venue['latitude']    ?? null;
$venueLng       = $venue['longitude']   ?? null;

// City base slug for linking back to city page
$cityBaseSlug = $venueCity ? slugify($venueCity) : '';

// ── Page configuration ────────────────────────────────────────────────────────
$pageTitle       = $venueName ? $venueName . ' – Bilete și Evenimente' : 'Locație';
$pageDescription = $venueDesc
    ?: ('Descoperă evenimentele la ' . $venueName . ($venueCity ? ' din ' . $venueCity : '') . '. Bilete disponibile pe TICS.ro.');
$canonicalUrl = SITE_URL . '/bilete-' . $venueSlug;

if ($venueImage) {
    $pageImage = $venueImage;
}

$breadcrumbs = array_filter([
    ['name' => 'Acasă',   'url' => '/'],
    $venueCity ? ['name' => $venueCity, 'url' => '/evenimente-' . $cityBaseSlug] : null,
    ['name' => $venueName],
]);

$headExtra = '<style>.hero-gradient{background:linear-gradient(135deg,#1e3a5f 0%,#2d5a87 50%,#3d7ab5 100%)}.stat-card{backdrop-filter:blur(10px);background:rgba(255,255,255,.1)}</style>';

require_once __DIR__ . '/includes/head.php';
setLoginState($isLoggedIn, $loggedInUser);
require_once __DIR__ . '/includes/header.php';
?>

<!-- Hero -->
<section class="hero-gradient text-white relative overflow-hidden">
    <?php if ($venueCover): ?>
    <div class="absolute inset-0">
        <img src="<?= e($venueCover) ?>" alt="<?= e($venueName) ?>" class="w-full h-full object-cover opacity-25">
    </div>
    <?php endif; ?>
    <div class="absolute inset-0 bg-gradient-to-r from-gray-900/85 via-gray-900/60 to-transparent"></div>

    <div class="max-w-6xl mx-auto px-4 lg:px-8 py-12 lg:py-16 relative">
        <!-- Breadcrumb -->
        <div class="flex items-center gap-2 text-sm text-white/60 mb-6 flex-wrap">
            <a href="/" class="hover:text-white transition-colors">Acasă</a>
            <?php if ($venueCity && $cityBaseSlug): ?>
            <svg class="w-4 h-4 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
            <a href="/evenimente-<?= e($cityBaseSlug) ?>" class="hover:text-white transition-colors"><?= e($venueCity) ?></a>
            <?php endif; ?>
            <svg class="w-4 h-4 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
            <span class="text-white"><?= e($venueName) ?></span>
        </div>

        <div class="max-w-2xl">
            <!-- Categories -->
            <?php if (!empty($venueCategories)): ?>
            <div class="flex items-center gap-2 mb-3 flex-wrap">
                <?php foreach ($venueCategories as $cat): ?>
                <span class="px-3 py-1 bg-white/15 border border-white/25 rounded-full text-xs font-medium"><?= e($cat['name']) ?></span>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>

            <h1 class="text-3xl lg:text-4xl font-bold mb-2"><?= e($venueName) ?></h1>

            <?php if ($venueCity || $venueAddress): ?>
            <p class="text-white/80 flex items-center gap-2 mb-4">
                <svg class="w-4 h-4 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                <?= e(implode(', ', array_filter([$venueAddress, $venueCity]))) ?>
            </p>
            <?php endif; ?>

            <div class="flex flex-wrap gap-3">
                <?php if ($venueEvCount > 0): ?>
                <div class="flex items-center gap-2 px-4 py-2 bg-white/10 backdrop-blur rounded-full border border-white/20">
                    <span class="w-2 h-2 bg-green-400 rounded-full"></span>
                    <span class="text-sm font-medium"><?= $venueEvCount ?> evenimente active</span>
                </div>
                <?php endif; ?>
                <?php if ($venueCapacity > 0): ?>
                <div class="flex items-center gap-2 px-4 py-2 bg-white/10 backdrop-blur rounded-full border border-white/20">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                    <span class="text-sm font-medium"><?= number_format($venueCapacity) ?> locuri</span>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</section>

<!-- Main Content -->
<div class="max-w-6xl mx-auto px-4 lg:px-8 py-10">
    <div class="flex flex-col lg:flex-row gap-10">

        <!-- Left: Events -->
        <div class="flex-1 min-w-0">

            <!-- Upcoming Events -->
            <?php if (!empty($venueUpcoming)): ?>
            <section class="mb-10">
                <h2 class="text-xl font-semibold text-gray-900 mb-5">
                    Evenimente la <?= e($venueName) ?>
                    <span class="ml-2 text-sm font-normal text-gray-400">(<?= count($venueUpcoming) ?> viitoare)</span>
                </h2>

                <div class="space-y-3">
                    <?php foreach ($venueUpcoming as $ev): ?>
                    <?php
                        $evSlug     = $ev['slug']     ?? '';
                        $evName     = $ev['name']     ?? '';
                        $evImage    = $ev['image']    ?? '';
                        $evDate     = $ev['starts_at'] ?? '';
                        $evPrice    = $ev['price_from'] ?? null;
                        $evCurrency = $ev['currency']   ?? 'RON';
                        $evSoldOut  = $ev['is_sold_out'] ?? false;
                        $evCat      = $ev['category']['name'] ?? '';

                        // Format date
                        $dateStr = '';
                        if ($evDate) {
                            try {
                                $dt = new DateTime($evDate);
                                $months = ['Ian', 'Feb', 'Mar', 'Apr', 'Mai', 'Iun', 'Iul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
                                $dateStr = $dt->format('d') . ' ' . $months[(int)$dt->format('n') - 1] . ' ' . $dt->format('Y, H:i');
                            } catch (Exception $e) {}
                        }
                    ?>
                    <a href="<?= e('/bilete/' . $evSlug) ?>" class="flex items-center gap-4 bg-white border border-gray-200 rounded-2xl p-4 hover:border-gray-300 hover:shadow-sm transition-all group">
                        <!-- Image -->
                        <div class="w-20 h-20 flex-shrink-0 rounded-xl overflow-hidden bg-gray-100">
                            <?php if ($evImage): ?>
                            <img src="<?= e($evImage) ?>" alt="<?= e($evName) ?>" class="w-full h-full object-cover">
                            <?php else: ?>
                            <div class="w-full h-full flex items-center justify-center bg-gradient-to-br from-purple-50 to-indigo-100">
                                <svg class="w-8 h-8 text-purple-300" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                            </div>
                            <?php endif; ?>
                        </div>

                        <!-- Info -->
                        <div class="flex-1 min-w-0">
                            <?php if ($evCat): ?>
                            <p class="text-xs text-purple-600 font-medium mb-0.5"><?= e($evCat) ?></p>
                            <?php endif; ?>
                            <h3 class="font-semibold text-gray-900 truncate group-hover:text-purple-700 transition-colors"><?= e($evName) ?></h3>
                            <?php if ($dateStr): ?>
                            <p class="text-sm text-gray-500 mt-0.5"><?= e($dateStr) ?></p>
                            <?php endif; ?>
                        </div>

                        <!-- Price -->
                        <div class="flex-shrink-0 text-right">
                            <?php if ($evSoldOut): ?>
                            <span class="inline-block px-3 py-1 bg-red-50 text-red-600 text-xs font-semibold rounded-full">Sold out</span>
                            <?php elseif ($evPrice !== null): ?>
                            <p class="text-xs text-gray-400 mb-0.5">de la</p>
                            <p class="font-bold text-gray-900"><?= formatPrice($evPrice, $evCurrency) ?></p>
                            <?php else: ?>
                            <span class="inline-block px-3 py-1 bg-green-50 text-green-700 text-xs font-semibold rounded-full">Gratuit</span>
                            <?php endif; ?>
                            <svg class="w-4 h-4 text-gray-400 mt-1 ml-auto" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
                        </div>
                    </a>
                    <?php endforeach; ?>
                </div>
            </section>
            <?php else: ?>
            <section class="mb-10">
                <h2 class="text-xl font-semibold text-gray-900 mb-5">Evenimente la <?= e($venueName) ?></h2>
                <div class="text-center py-16 bg-white rounded-2xl border border-gray-200">
                    <svg class="w-12 h-12 mx-auto mb-3 text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                    <p class="text-gray-500">Nu există evenimente programate momentan.</p>
                    <?php if ($venueCity && $cityBaseSlug): ?>
                    <a href="/evenimente-<?= e($cityBaseSlug) ?>" class="inline-block mt-4 px-5 py-2 bg-gray-900 text-white text-sm font-medium rounded-full hover:bg-gray-800 transition-colors">
                        Toate evenimentele din <?= e($venueCity) ?>
                    </a>
                    <?php endif; ?>
                </div>
            </section>
            <?php endif; ?>

        </div>

        <!-- Right: Info sidebar -->
        <aside class="lg:w-80 flex-shrink-0 space-y-5">

            <!-- Description -->
            <?php if ($venueDesc): ?>
            <div class="bg-white border border-gray-200 rounded-2xl p-5">
                <h3 class="font-semibold text-gray-900 mb-3">Despre locație</h3>
                <p class="text-sm text-gray-600 leading-relaxed"><?= e($venueDesc) ?></p>
            </div>
            <?php endif; ?>

            <!-- Contact & Address -->
            <div class="bg-white border border-gray-200 rounded-2xl p-5">
                <h3 class="font-semibold text-gray-900 mb-4">Informații</h3>
                <div class="space-y-3 text-sm">
                    <?php if ($venueAddress): ?>
                    <div class="flex items-start gap-3">
                        <svg class="w-4 h-4 mt-0.5 text-gray-400 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                        <span class="text-gray-700"><?= e($venueAddress) ?><?= $venueCity ? ', ' . e($venueCity) : '' ?></span>
                    </div>
                    <?php endif; ?>
                    <?php if (!empty($venueContact['phone'])): ?>
                    <div class="flex items-center gap-3">
                        <svg class="w-4 h-4 text-gray-400 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z"/></svg>
                        <a href="tel:<?= e($venueContact['phone']) ?>" class="text-gray-700 hover:text-gray-900"><?= e($venueContact['phone']) ?></a>
                    </div>
                    <?php endif; ?>
                    <?php if (!empty($venueContact['email'])): ?>
                    <div class="flex items-center gap-3">
                        <svg class="w-4 h-4 text-gray-400 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/></svg>
                        <a href="mailto:<?= e($venueContact['email']) ?>" class="text-gray-700 hover:text-gray-900 truncate"><?= e($venueContact['email']) ?></a>
                    </div>
                    <?php endif; ?>
                    <?php if (!empty($venueContact['website'])): ?>
                    <div class="flex items-center gap-3">
                        <svg class="w-4 h-4 text-gray-400 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"/></svg>
                        <a href="<?= e($venueContact['website']) ?>" target="_blank" rel="noopener" class="text-gray-700 hover:text-gray-900 truncate"><?= e(preg_replace('#^https?://#', '', rtrim($venueContact['website'], '/'))) ?></a>
                    </div>
                    <?php endif; ?>
                    <?php if ($venueCapacity > 0): ?>
                    <div class="flex items-center gap-3">
                        <svg class="w-4 h-4 text-gray-400 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                        <span class="text-gray-700">Capacitate: <?= number_format($venueCapacity) ?> locuri</span>
                    </div>
                    <?php endif; ?>
                </div>

                <!-- Social links -->
                <?php $hasSocial = !empty($venueSocial['facebook']) || !empty($venueSocial['instagram']) || !empty($venueSocial['tiktok']); ?>
                <?php if ($hasSocial): ?>
                <div class="flex items-center gap-3 mt-4 pt-4 border-t border-gray-100">
                    <?php if (!empty($venueSocial['facebook'])): ?>
                    <a href="<?= e($venueSocial['facebook']) ?>" target="_blank" rel="noopener" class="p-2 bg-gray-100 hover:bg-blue-50 hover:text-blue-600 rounded-full transition-colors" title="Facebook">
                        <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 24 24"><path d="M24 12.073c0-6.627-5.373-12-12-12s-12 5.373-12 12c0 5.99 4.388 10.954 10.125 11.854v-8.385H7.078v-3.47h3.047V9.43c0-3.007 1.792-4.669 4.533-4.669 1.312 0 2.686.235 2.686.235v2.953H15.83c-1.491 0-1.956.925-1.956 1.874v2.25h3.328l-.532 3.47h-2.796v8.385C19.612 23.027 24 18.062 24 12.073z"/></svg>
                    </a>
                    <?php endif; ?>
                    <?php if (!empty($venueSocial['instagram'])): ?>
                    <a href="<?= e($venueSocial['instagram']) ?>" target="_blank" rel="noopener" class="p-2 bg-gray-100 hover:bg-pink-50 hover:text-pink-600 rounded-full transition-colors" title="Instagram">
                        <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 24 24"><path d="M12 2.163c3.204 0 3.584.012 4.85.07 3.252.148 4.771 1.691 4.919 4.919.058 1.265.069 1.645.069 4.849 0 3.205-.012 3.584-.069 4.849-.149 3.225-1.664 4.771-4.919 4.919-1.266.058-1.644.07-4.85.07-3.204 0-3.584-.012-4.849-.07-3.26-.149-4.771-1.699-4.919-4.92-.058-1.265-.07-1.644-.07-4.849 0-3.204.013-3.583.07-4.849.149-3.227 1.664-4.771 4.919-4.919 1.266-.057 1.645-.069 4.849-.069zm0-2.163c-3.259 0-3.667.014-4.947.072-4.358.2-6.78 2.618-6.98 6.98-.059 1.281-.073 1.689-.073 4.948 0 3.259.014 3.668.072 4.948.2 4.358 2.618 6.78 6.98 6.98 1.281.058 1.689.072 4.948.072 3.259 0 3.668-.014 4.948-.072 4.354-.2 6.782-2.618 6.979-6.98.059-1.28.073-1.689.073-4.948 0-3.259-.014-3.667-.072-4.947-.196-4.354-2.617-6.78-6.979-6.98-1.281-.059-1.69-.073-4.949-.073zm0 5.838c-3.403 0-6.162 2.759-6.162 6.162s2.759 6.163 6.162 6.163 6.162-2.759 6.162-6.163c0-3.403-2.759-6.162-6.162-6.162zm0 10.162c-2.209 0-4-1.79-4-4 0-2.209 1.791-4 4-4s4 1.791 4 4c0 2.21-1.791 4-4 4zm6.406-11.845c-.796 0-1.441.645-1.441 1.44s.645 1.44 1.441 1.44c.795 0 1.439-.645 1.439-1.44s-.644-1.44-1.439-1.44z"/></svg>
                    </a>
                    <?php endif; ?>
                    <?php if (!empty($venueSocial['tiktok'])): ?>
                    <a href="<?= e($venueSocial['tiktok']) ?>" target="_blank" rel="noopener" class="p-2 bg-gray-100 hover:bg-gray-200 rounded-full transition-colors" title="TikTok">
                        <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 24 24"><path d="M19.59 6.69a4.83 4.83 0 01-3.77-4.25V2h-3.45v13.67a2.89 2.89 0 01-2.88 2.5 2.89 2.89 0 01-2.89-2.89 2.89 2.89 0 012.89-2.89c.28 0 .54.04.79.1V9.01a6.32 6.32 0 00-.79-.05 6.34 6.34 0 00-6.34 6.34 6.34 6.34 0 006.34 6.34 6.34 6.34 0 006.33-6.34V8.69a8.18 8.18 0 004.78 1.52V6.78a4.85 4.85 0 01-1.01-.09z"/></svg>
                    </a>
                    <?php endif; ?>
                </div>
                <?php endif; ?>
            </div>

            <!-- Amenities -->
            <?php if (!empty($venueAmenities)): ?>
            <div class="bg-white border border-gray-200 rounded-2xl p-5">
                <h3 class="font-semibold text-gray-900 mb-3">Facilități</h3>
                <div class="flex flex-wrap gap-2">
                    <?php foreach ($venueAmenities as $amenity): ?>
                    <span class="px-3 py-1.5 bg-gray-50 border border-gray-200 rounded-full text-xs font-medium text-gray-700">
                        ✓ <?= e($amenity) ?>
                    </span>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>

            <!-- Map placeholder -->
            <?php if ($venueLat && $venueLng): ?>
            <div class="bg-white border border-gray-200 rounded-2xl overflow-hidden">
                <a href="https://www.google.com/maps/search/?api=1&query=<?= urlencode($venueAddress . ', ' . $venueCity) ?>" target="_blank" rel="noopener" class="block">
                    <div class="h-40 bg-gradient-to-br from-gray-100 to-gray-200 flex items-center justify-center hover:from-gray-200 hover:to-gray-300 transition-colors">
                        <div class="text-center">
                            <svg class="w-10 h-10 text-gray-400 mx-auto mb-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                            <p class="text-sm text-gray-500">Deschide în Google Maps</p>
                        </div>
                    </div>
                </a>
                <div class="p-4">
                    <p class="text-sm text-gray-700 font-medium"><?= e($venueAddress) ?></p>
                    <?php if ($venueCity): ?><p class="text-xs text-gray-500"><?= e($venueCity) ?></p><?php endif; ?>
                </div>
            </div>
            <?php endif; ?>

            <!-- Back to city -->
            <?php if ($venueCity && $cityBaseSlug): ?>
            <a href="/evenimente-<?= e($cityBaseSlug) ?>" class="flex items-center gap-2 text-sm font-medium text-gray-600 hover:text-gray-900 transition-colors">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
                Toate evenimentele din <?= e($venueCity) ?>
            </a>
            <?php endif; ?>

        </aside>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>

</body>
</html>
