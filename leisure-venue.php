<?php
/**
 * Leisure Venue custom event page — modern immersive template.
 * Included from event.php when display_template === 'leisure_venue'.
 *
 * Variables available from event.php:
 *   $ev             — event data array
 *   $eventSlug      — event slug
 *   $pageTitle       — already set to event name
 *   $pageDescription — already set
 *   $eventPreload   — full API response
 */

$venueConfig = $ev['venue_config'] ?? [];
$amenities   = $venueConfig['amenities'] ?? [];
$heroImages  = $venueConfig['hero_images'] ?? [];
$heroImage   = !empty($heroImages) ? $heroImages[0] : ($ev['cover_image_url'] ?? $ev['hero_image_url'] ?? $ev['image_url'] ?? '');
if ($heroImage && !str_starts_with($heroImage, 'http')) {
    $heroImage = STORAGE_URL . '/' . $heroImage;
}
$posterImage = $ev['poster_url'] ?? $ev['image_url'] ?? $ev['image'] ?? '';
if ($posterImage && !str_starts_with($posterImage, 'http')) {
    $posterImage = STORAGE_URL . '/' . $posterImage;
}
$contactPhone  = $venueConfig['contact_phone'] ?? '';
$directionsUrl = $venueConfig['directions_url'] ?? '';
$rulesHtml     = $venueConfig['rules_html'] ?? '';
$operatingSchedule = $venueConfig['operating_schedule'] ?? [];
$seasons       = $venueConfig['seasons'] ?? [];
$maxAdvanceDays = $venueConfig['max_advance_days'] ?? 90;
$gallery = $ev['gallery'] ?? [];
$venueName = $ev['venue_name'] ?? '';
$venueCity = $ev['venue_city'] ?? '';
$venueAddress = $ev['venue_address'] ?? '';
$description = $ev['description'] ?? '';
$shortDescription = $ev['short_description'] ?? '';

$bodyClass = 'bg-surface';
$cssBundle = 'single';
require_once __DIR__ . '/includes/head.php';
require_once __DIR__ . '/includes/header.php';
?>

<!-- Inject event data for JS -->
<script>
    window.__LEISURE_VENUE__ = <?= json_encode([
        'slug' => $eventSlug,
        'event' => [
            'id' => $ev['id'] ?? null,
            'name' => $ev['name'] ?? '',
            'slug' => $eventSlug,
            'image' => $posterImage,
            'starts_at' => $ev['starts_at'] ?? null,
            'ends_at' => $ev['ends_at'] ?? null,
            'range_start_date' => $ev['range_start_date'] ?? null,
            'range_end_date' => $ev['range_end_date'] ?? null,
        ],
        'venue_config' => $venueConfig,
        'max_advance_days' => $maxAdvanceDays,
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
</script>

<style>
    .lv-hero { min-height: 85vh; }
    @media (max-width: 767px) { .lv-hero { min-height: 70vh; } }
    .lv-hero-overlay { background: linear-gradient(180deg, rgba(0,0,0,0.1) 0%, rgba(0,0,0,0.3) 40%, rgba(0,0,0,0.75) 100%); }
    .lv-glass { background: rgba(255,255,255,0.08); backdrop-filter: blur(12px); -webkit-backdrop-filter: blur(12px); border: 1px solid rgba(255,255,255,0.15); }
    .lv-cta-pulse { animation: lv-pulse 2s infinite; }
    @keyframes lv-pulse { 0%,100% { box-shadow: 0 0 0 0 rgba(var(--color-primary-rgb, 59,130,246), 0.5); } 50% { box-shadow: 0 0 0 12px rgba(var(--color-primary-rgb, 59,130,246), 0); } }
    .lv-amenity { transition: transform 0.2s; }
    .lv-amenity:hover { transform: translateY(-2px); }
    .lv-section-fade { opacity: 0; transform: translateY(24px); transition: opacity 0.6s ease, transform 0.6s ease; }
    .lv-section-fade.visible { opacity: 1; transform: translateY(0); }
    .lv-calendar-day { transition: all 0.15s ease; }
    .lv-calendar-day:not(.disabled):hover { transform: scale(1.12); z-index: 1; }
    .lv-ticket-card { transition: all 0.2s ease; }
    .lv-ticket-card:hover { box-shadow: 0 4px 24px rgba(0,0,0,0.08); }
    .lv-gallery-item { transition: transform 0.3s ease; }
    .lv-gallery-item:hover { transform: scale(1.03); }
    #ticket-booking-section { scroll-margin-top: 80px; }
</style>

<!-- ==================== HERO ==================== -->
<section class="lv-hero relative flex items-end overflow-hidden mt-17 mobile:mt-0">
    <?php if ($heroImage): ?>
    <img src="<?= htmlspecialchars($heroImage) ?>" alt="<?= htmlspecialchars($ev['name'] ?? '') ?>"
         class="absolute inset-0 w-full h-full object-cover object-center" loading="eager">
    <?php endif; ?>
    <div class="absolute inset-0 lv-hero-overlay"></div>

    <div class="relative z-10 w-full px-4 pb-12 pt-32 mx-auto max-w-7xl md:pb-16">
        <!-- Amenities pills -->
        <?php if (!empty($amenities)): ?>
        <div class="flex flex-wrap gap-2 mb-5">
            <?php foreach ($amenities as $amenity): ?>
            <span class="lv-amenity lv-glass px-3 py-1.5 text-xs font-semibold text-white rounded-full uppercase tracking-wider">
                <?= htmlspecialchars($amenity) ?>
            </span>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>

        <!-- Title -->
        <h1 class="text-4xl font-black text-white md:text-5xl lg:text-6xl leading-tight tracking-tight drop-shadow-lg max-w-3xl">
            <?= htmlspecialchars($ev['name'] ?? 'Locație') ?>
        </h1>

        <?php if ($shortDescription): ?>
        <p class="mt-3 text-lg text-white/85 max-w-2xl leading-relaxed"><?= htmlspecialchars($shortDescription) ?></p>
        <?php endif; ?>

        <!-- Quick info row -->
        <div class="flex flex-wrap items-center gap-4 mt-5 text-sm text-white/75">
            <?php if ($venueName || $venueCity): ?>
            <span class="flex items-center gap-1.5">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                <?= htmlspecialchars($venueName ? ($venueName . ($venueCity ? ', ' . $venueCity : '')) : $venueCity) ?>
            </span>
            <?php endif; ?>
            <?php if ($contactPhone): ?>
            <a href="tel:<?= htmlspecialchars($contactPhone) ?>" class="flex items-center gap-1.5 hover:text-white transition">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z"/></svg>
                <?= htmlspecialchars($contactPhone) ?>
            </a>
            <?php endif; ?>
            <?php if ($directionsUrl): ?>
            <a href="<?= htmlspecialchars($directionsUrl) ?>" target="_blank" rel="noopener" class="flex items-center gap-1.5 hover:text-white transition">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 20l-5.447-2.724A1 1 0 013 16.382V5.618a1 1 0 011.447-.894L9 7m0 13l6-3m-6 3V7m6 10l4.553 2.276A1 1 0 0021 18.382V7.618a1 1 0 00-.553-.894L15 4m0 13V4m0 0L9 7"/></svg>
                Indicații
            </a>
            <?php endif; ?>
        </div>

        <!-- CTA Button -->
        <div class="mt-8">
            <a href="#ticket-booking-section" class="lv-cta-pulse inline-flex items-center gap-2 px-8 py-4 text-base font-bold text-white rounded-xl bg-primary hover:bg-primary-dark transition-colors shadow-lg">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 5v2m0 4v2m0 4v2M5 5a2 2 0 00-2 2v3a2 2 0 110 4v3a2 2 0 002 2h14a2 2 0 002-2v-3a2 2 0 110-4V7a2 2 0 00-2-2H5z"/></svg>
                Cumpără bilete
            </a>
        </div>
    </div>
</section>

<!-- ==================== BOOKING SECTION ==================== -->
<section id="ticket-booking-section" class="px-4 py-12 mx-auto max-w-7xl lv-section-fade">
    <div class="text-center mb-10">
        <h2 class="text-2xl font-bold text-secondary md:text-3xl">Alege data și biletele</h2>
        <p class="mt-2 text-gray-500">Selectează o dată din calendar, apoi alege biletele dorite.</p>
    </div>

    <div class="flex flex-col gap-8 lg:flex-row">
        <!-- Left: Calendar -->
        <div class="lg:w-5/12">
            <div class="sticky top-24 space-y-4">
                <div class="p-6 bg-white shadow-sm rounded-2xl border border-gray-100">
                    <!-- Calendar nav -->
                    <div class="flex items-center justify-between mb-4">
                        <button id="cal-prev" class="p-2 rounded-lg hover:bg-gray-100 transition" aria-label="Luna anterioară">
                            <svg class="w-5 h-5 text-gray-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
                        </button>
                        <span id="cal-month-label" class="text-base font-bold text-secondary"></span>
                        <button id="cal-next" class="p-2 rounded-lg hover:bg-gray-100 transition" aria-label="Luna următoare">
                            <svg class="w-5 h-5 text-gray-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
                        </button>
                    </div>

                    <!-- Day headers -->
                    <div class="grid grid-cols-7 mb-2">
                        <div class="text-xs font-semibold text-center text-gray-400 py-1">Lu</div>
                        <div class="text-xs font-semibold text-center text-gray-400 py-1">Ma</div>
                        <div class="text-xs font-semibold text-center text-gray-400 py-1">Mi</div>
                        <div class="text-xs font-semibold text-center text-gray-400 py-1">Jo</div>
                        <div class="text-xs font-semibold text-center text-gray-400 py-1">Vi</div>
                        <div class="text-xs font-semibold text-center text-gray-400 py-1">Sâ</div>
                        <div class="text-xs font-semibold text-center text-gray-400 py-1">Du</div>
                    </div>

                    <!-- Calendar days -->
                    <div id="cal-days" class="grid grid-cols-7 gap-1"></div>

                    <!-- Legend -->
                    <div class="flex flex-wrap items-center gap-x-4 gap-y-1 mt-4 text-[11px] text-gray-400 uppercase tracking-wide font-medium">
                        <span class="flex items-center gap-1.5"><span class="w-2.5 h-2.5 rounded-full bg-emerald-500"></span> Disponibil</span>
                        <span class="flex items-center gap-1.5"><span class="w-2.5 h-2.5 rounded-full bg-amber-500"></span> Limitat</span>
                        <span class="flex items-center gap-1.5"><span class="w-2.5 h-2.5 rounded-full bg-red-500"></span> Sold out</span>
                        <span class="flex items-center gap-1.5"><span class="w-2.5 h-2.5 rounded-full bg-gray-300"></span> Închis</span>
                    </div>
                </div>

                <!-- Operating hours -->
                <?php if (!empty($operatingSchedule) || !empty($seasons)): ?>
                <div class="p-5 bg-white shadow-sm rounded-2xl border border-gray-100">
                    <h3 class="text-sm font-bold text-secondary mb-3 flex items-center gap-2">
                        <svg class="w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                        Program
                    </h3>
                    <?php if (!empty($seasons)): ?>
                        <?php foreach ($seasons as $season):
                            $seasonSchedule = $season['schedule'] ?? [];
                        ?>
                        <div class="mb-3 last:mb-0">
                            <div class="text-xs font-semibold text-primary mb-1.5 uppercase tracking-wide"><?= htmlspecialchars($season['name'] ?? 'Sezon') ?></div>
                            <div class="grid grid-cols-1 gap-y-0.5 text-sm">
                                <?php
                                $dayNames = ['mon' => 'Luni', 'tue' => 'Marți', 'wed' => 'Miercuri', 'thu' => 'Joi', 'fri' => 'Vineri', 'sat' => 'Sâmbătă', 'sun' => 'Duminică'];
                                foreach ($dayNames as $key => $label):
                                    $hours = $seasonSchedule[$key] ?? null;
                                ?>
                                <div class="flex justify-between py-0.5">
                                    <span class="text-gray-500"><?= $label ?></span>
                                    <?php if ($hours && !empty($hours['open'])): ?>
                                    <span class="font-medium text-secondary"><?= htmlspecialchars($hours['open']) ?> – <?= htmlspecialchars($hours['close'] ?? '') ?></span>
                                    <?php else: ?>
                                    <span class="text-gray-400 text-xs">Închis</span>
                                    <?php endif; ?>
                                </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    <?php elseif (!empty($operatingSchedule)): ?>
                        <div class="grid grid-cols-1 gap-y-0.5 text-sm">
                            <?php
                            $dayNames = ['mon' => 'Luni', 'tue' => 'Marți', 'wed' => 'Miercuri', 'thu' => 'Joi', 'fri' => 'Vineri', 'sat' => 'Sâmbătă', 'sun' => 'Duminică'];
                            foreach ($dayNames as $key => $label):
                                $hours = $operatingSchedule[$key] ?? null;
                            ?>
                            <div class="flex justify-between py-0.5">
                                <span class="text-gray-500"><?= $label ?></span>
                                <?php if ($hours): ?>
                                <span class="font-medium text-secondary"><?= htmlspecialchars($hours['open'] ?? '') ?> – <?= htmlspecialchars($hours['close'] ?? '') ?></span>
                                <?php else: ?>
                                <span class="text-gray-400 text-xs">Închis</span>
                                <?php endif; ?>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Right: Ticket selector -->
        <div class="lg:w-7/12">
            <!-- Selected date bar -->
            <div id="selected-date-bar" class="hidden mb-4">
                <div class="flex items-center justify-between p-4 bg-primary/5 border border-primary/20 rounded-xl">
                    <div class="flex items-center gap-3">
                        <div class="w-10 h-10 flex items-center justify-center rounded-lg bg-primary text-white">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                        </div>
                        <div>
                            <div class="text-xs text-gray-500 uppercase tracking-wide font-medium">Data vizitei</div>
                            <div id="selected-date-label" class="font-bold text-secondary"></div>
                        </div>
                    </div>
                    <button id="change-date-btn" class="text-sm font-semibold text-primary hover:underline">Schimbă</button>
                </div>
            </div>

            <!-- Placeholder -->
            <div id="no-date-placeholder" class="flex flex-col items-center justify-center p-12 text-center bg-white rounded-2xl border-2 border-dashed border-gray-200">
                <div class="w-16 h-16 flex items-center justify-center rounded-full bg-gray-100 mb-4">
                    <svg class="w-8 h-8 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                </div>
                <p class="text-gray-500 font-medium">Selectează o dată din calendar</p>
                <p class="text-sm text-gray-400 mt-1">pentru a vedea biletele disponibile</p>
            </div>

            <!-- Loading -->
            <div id="tickets-loading" class="hidden space-y-4">
                <div class="h-20 skeleton rounded-2xl"></div>
                <div class="h-20 skeleton rounded-2xl"></div>
                <div class="h-20 skeleton rounded-2xl"></div>
            </div>

            <!-- Ticket groups (JS-rendered) -->
            <div id="ticket-groups" class="hidden space-y-4"></div>

            <!-- Cart summary -->
            <div id="cart-summary" class="hidden mt-6 p-5 bg-white rounded-2xl border border-gray-100 shadow-lg sticky top-24">
                <h3 class="text-sm font-bold text-secondary mb-3 uppercase tracking-wide">Sumar</h3>
                <div id="cart-items-summary" class="space-y-2 text-sm"></div>
                <div class="flex justify-between items-center pt-3 mt-3 border-t border-gray-100">
                    <span class="text-sm font-semibold text-gray-500">Total</span>
                    <span id="cart-total" class="text-xl font-black text-primary">0 RON</span>
                </div>
                <button id="add-to-cart-btn" class="w-full mt-4 py-3.5 px-6 bg-primary hover:bg-primary-dark text-white font-bold rounded-xl transition-all disabled:opacity-40 disabled:cursor-not-allowed flex items-center justify-center gap-2" disabled>
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-2.293 2.293c-.63.63-.184 1.707.707 1.707H17m0 0a2 2 0 100 4 2 2 0 000-4zm-8 2a2 2 0 100 4 2 2 0 000-4z"/></svg>
                    Adaugă în coș
                </button>
            </div>
        </div>
    </div>
</section>

<!-- ==================== ABOUT ==================== -->
<?php if (!empty($description)): ?>
<section class="px-4 py-12 mx-auto max-w-7xl lv-section-fade">
    <div class="max-w-4xl mx-auto">
        <div class="p-8 bg-white rounded-2xl shadow-sm border border-gray-100 md:p-10">
            <h2 class="text-2xl font-bold text-secondary mb-6">Despre</h2>
            <div class="prose prose-lg max-w-none text-gray-600 ep-event-description">
                <?= $description ?>
            </div>
        </div>
    </div>
</section>
<?php endif; ?>

<!-- ==================== GALLERY ==================== -->
<?php if (!empty($gallery)): ?>
<section class="px-4 py-12 mx-auto max-w-7xl lv-section-fade">
    <h2 class="text-2xl font-bold text-secondary mb-6 text-center">Galerie</h2>
    <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-3">
        <?php foreach ($gallery as $i => $img):
            $imgUrl = str_starts_with($img, 'http') ? $img : STORAGE_URL . '/' . $img;
            $span = ($i === 0) ? 'md:col-span-2 md:row-span-2' : '';
        ?>
        <a href="<?= htmlspecialchars($imgUrl) ?>" target="_blank"
           class="lv-gallery-item block rounded-xl overflow-hidden <?= $span ?> aspect-video hover:opacity-90 transition">
            <img src="<?= htmlspecialchars($imgUrl) ?>" alt="" class="object-cover w-full h-full" loading="lazy">
        </a>
        <?php endforeach; ?>
    </div>
</section>
<?php endif; ?>

<!-- ==================== RULES ==================== -->
<?php if (!empty($rulesHtml)): ?>
<section class="px-4 py-12 mx-auto max-w-7xl lv-section-fade">
    <div class="max-w-4xl mx-auto">
        <div class="p-8 bg-white rounded-2xl shadow-sm border border-gray-100 md:p-10">
            <h2 class="text-2xl font-bold text-secondary mb-6 flex items-center gap-2">
                <svg class="w-6 h-6 text-amber-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4.5c-.77-.833-2.694-.833-3.464 0L3.34 16.5c-.77.833.192 2.5 1.732 2.5z"/></svg>
                Regulament
            </h2>
            <div class="prose prose-sm max-w-none text-gray-600">
                <?= $rulesHtml ?>
            </div>
        </div>
    </div>
</section>
<?php endif; ?>

<!-- ==================== LOCATION ==================== -->
<?php if ($venueName || $directionsUrl): ?>
<section class="px-4 py-12 mx-auto max-w-7xl lv-section-fade">
    <div class="max-w-4xl mx-auto">
        <div class="p-8 bg-white rounded-2xl shadow-sm border border-gray-100 md:p-10">
            <h2 class="text-2xl font-bold text-secondary mb-4 flex items-center gap-2">
                <svg class="w-6 h-6 text-primary" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                Locație
            </h2>
            <?php if ($venueName): ?>
            <p class="text-lg font-semibold text-secondary"><?= htmlspecialchars($venueName) ?></p>
            <?php endif; ?>
            <?php if ($venueAddress): ?>
            <p class="text-gray-500 mt-1"><?= htmlspecialchars($venueAddress) ?><?php if ($venueCity): ?>, <?= htmlspecialchars($venueCity) ?><?php endif; ?></p>
            <?php endif; ?>
            <?php if ($directionsUrl): ?>
            <a href="<?= htmlspecialchars($directionsUrl) ?>" target="_blank" rel="noopener"
               class="inline-flex items-center gap-2 mt-4 px-5 py-2.5 text-sm font-semibold text-white bg-primary rounded-xl hover:bg-primary-dark transition shadow-sm">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 20l-5.447-2.724A1 1 0 013 16.382V5.618a1 1 0 011.447-.894L9 7m0 13l6-3m-6 3V7m6 10l4.553 2.276A1 1 0 0021 18.382V7.618a1 1 0 00-.553-.894L15 4m0 13V4m0 0L9 7"/></svg>
                Deschide în Google Maps
            </a>
            <?php endif; ?>
        </div>
    </div>
</section>
<?php endif; ?>

<!-- ==================== MOBILE STICKY BAR ==================== -->
<div id="mobile-bottom-bar" class="fixed bottom-0 left-0 right-0 z-40 hidden lg:hidden">
    <div class="bg-white/95 backdrop-blur-lg border-t border-gray-200 shadow-2xl px-4 py-3">
        <div class="flex items-center justify-between gap-4 max-w-lg mx-auto">
            <div>
                <div id="mobile-total-label" class="text-xs text-gray-500 font-medium">Total</div>
                <div id="mobile-total-amount" class="text-xl font-black text-primary">0 RON</div>
            </div>
            <button id="mobile-add-to-cart-btn" class="px-6 py-3 bg-primary hover:bg-primary-dark text-white font-bold rounded-xl transition-all disabled:opacity-40 flex items-center gap-2" disabled>
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-2.293 2.293c-.63.63-.184 1.707.707 1.707H17m0 0a2 2 0 100 4 2 2 0 000-4zm-8 2a2 2 0 100 4 2 2 0 000-4z"/></svg>
                Adaugă în coș
            </button>
        </div>
    </div>
</div>

<!-- Scroll reveal observer -->
<script>
document.addEventListener('DOMContentLoaded', function() {
    const observer = new IntersectionObserver(function(entries) {
        entries.forEach(function(entry) {
            if (entry.isIntersecting) {
                entry.target.classList.add('visible');
                observer.unobserve(entry.target);
            }
        });
    }, { threshold: 0.1 });
    document.querySelectorAll('.lv-section-fade').forEach(function(el) { observer.observe(el); });
});
</script>

<?php
require_once __DIR__ . '/includes/footer.php';
require_once __DIR__ . '/includes/scripts.php';
?>
<script src="<?= ASSETS_URL ?>/js/pages/leisure-venue.js?v=<?= filemtime(__DIR__ . '/assets/js/pages/leisure-venue.js') ?>"></script>
