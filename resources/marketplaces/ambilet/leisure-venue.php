<?php
/**
 * Leisure Venue custom event page.
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
$maxAdvanceDays = $venueConfig['max_advance_days'] ?? 90;

// Gallery from event
$gallery = $ev['gallery'] ?? [];

$bodyClass = 'bg-surface';
$cssBundle = 'leisure-venue';
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

<!-- Hero Section -->
<section class="relative mt-17 mobile:mt-18 overflow-hidden" style="min-height:340px;">
    <?php if ($heroImage): ?>
    <div class="absolute inset-0">
        <img src="<?= htmlspecialchars($heroImage) ?>" alt="<?= htmlspecialchars($ev['name'] ?? '') ?>"
             class="object-cover w-full h-full" style="min-height:340px;">
        <div class="absolute inset-0" style="background:linear-gradient(to bottom, rgba(0,0,0,0.25) 0%, rgba(0,0,0,0.65) 100%);"></div>
    </div>
    <?php endif; ?>
    <div class="relative z-10 px-4 py-12 mx-auto max-w-7xl md:py-16">
        <div class="flex items-center gap-6">
            <?php if ($posterImage && $posterImage !== $heroImage): ?>
            <img src="<?= htmlspecialchars($posterImage) ?>" alt=""
                 class="hidden md:block w-28 h-28 rounded-2xl object-cover shadow-lg border-2 border-white/20">
            <?php endif; ?>
            <div>
                <h1 class="text-3xl font-bold text-white md:text-4xl lg:text-5xl drop-shadow-lg">
                    <?= htmlspecialchars($ev['name'] ?? 'Locație') ?>
                </h1>
                <?php if (!empty($ev['short_description'])): ?>
                <p class="mt-2 text-lg text-white/90 max-w-2xl"><?= htmlspecialchars($ev['short_description']) ?></p>
                <?php endif; ?>

                <!-- Amenities badges -->
                <?php if (!empty($amenities)): ?>
                <div class="flex flex-wrap gap-2 mt-4">
                    <?php foreach ($amenities as $amenity): ?>
                    <span class="px-3 py-1 text-sm font-medium text-white rounded-full bg-white/20 backdrop-blur-sm">
                        <?= htmlspecialchars($amenity) ?>
                    </span>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>

                <!-- Operating hours quick display -->
                <?php if ($contactPhone || $directionsUrl): ?>
                <div class="flex items-center gap-4 mt-4 text-sm text-white/80">
                    <?php if ($contactPhone): ?>
                    <a href="tel:<?= htmlspecialchars($contactPhone) ?>" class="flex items-center gap-1 hover:text-white">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z"/></svg>
                        <?= htmlspecialchars($contactPhone) ?>
                    </a>
                    <?php endif; ?>
                    <?php if ($directionsUrl): ?>
                    <a href="<?= htmlspecialchars($directionsUrl) ?>" target="_blank" rel="noopener" class="flex items-center gap-1 hover:text-white">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                        Indicații
                    </a>
                    <?php endif; ?>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</section>

<!-- Main Content: Calendar + Ticket Selector -->
<main class="px-4 py-8 mx-auto max-w-7xl">
    <div class="flex flex-col gap-8 lg:flex-row">

        <!-- Left Column: Calendar -->
        <div class="lg:w-1/2 xl:w-5/12">
            <div class="sticky top-24">
                <div class="p-6 bg-white shadow-sm rounded-2xl border border-gray-100">
                    <h2 class="text-lg font-bold text-secondary mb-4">Alege data vizitei</h2>

                    <!-- Calendar navigation -->
                    <div class="flex items-center justify-between mb-4">
                        <button id="cal-prev" class="p-2 rounded-lg hover:bg-gray-100 transition" aria-label="Luna anterioară">
                            <svg class="w-5 h-5 text-gray-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
                        </button>
                        <span id="cal-month-label" class="text-base font-semibold text-secondary"></span>
                        <button id="cal-next" class="p-2 rounded-lg hover:bg-gray-100 transition" aria-label="Luna următoare">
                            <svg class="w-5 h-5 text-gray-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
                        </button>
                    </div>

                    <!-- Calendar grid -->
                    <div id="cal-grid" class="select-none">
                        <!-- Day headers -->
                        <div class="grid grid-cols-7 mb-2">
                            <div class="text-xs font-medium text-center text-gray-400 py-1">Lu</div>
                            <div class="text-xs font-medium text-center text-gray-400 py-1">Ma</div>
                            <div class="text-xs font-medium text-center text-gray-400 py-1">Mi</div>
                            <div class="text-xs font-medium text-center text-gray-400 py-1">Jo</div>
                            <div class="text-xs font-medium text-center text-gray-400 py-1">Vi</div>
                            <div class="text-xs font-medium text-center text-gray-400 py-1">Sâ</div>
                            <div class="text-xs font-medium text-center text-gray-400 py-1">Du</div>
                        </div>
                        <!-- Calendar days will be rendered by JS -->
                        <div id="cal-days" class="grid grid-cols-7 gap-1"></div>
                    </div>

                    <!-- Legend -->
                    <div class="flex items-center gap-4 mt-4 text-xs text-gray-500">
                        <span class="flex items-center gap-1"><span class="w-3 h-3 rounded-full bg-green-500"></span> Disponibil</span>
                        <span class="flex items-center gap-1"><span class="w-3 h-3 rounded-full bg-amber-500"></span> Locuri limitate</span>
                        <span class="flex items-center gap-1"><span class="w-3 h-3 rounded-full bg-red-500"></span> Sold out</span>
                        <span class="flex items-center gap-1"><span class="w-3 h-3 rounded-full bg-gray-300"></span> Închis</span>
                    </div>
                </div>

                <!-- Operating hours card -->
                <?php if (!empty($operatingSchedule)): ?>
                <div class="p-4 mt-4 bg-white shadow-sm rounded-2xl border border-gray-100">
                    <h3 class="text-sm font-semibold text-secondary mb-2">Program</h3>
                    <div class="grid grid-cols-2 gap-x-4 gap-y-1 text-sm">
                        <?php
                        $dayNames = ['mon' => 'Luni', 'tue' => 'Marți', 'wed' => 'Miercuri', 'thu' => 'Joi', 'fri' => 'Vineri', 'sat' => 'Sâmbătă', 'sun' => 'Duminică'];
                        foreach ($dayNames as $key => $label):
                            $hours = $operatingSchedule[$key] ?? null;
                        ?>
                        <div class="flex justify-between py-0.5">
                            <span class="text-gray-500"><?= $label ?></span>
                            <?php if ($hours): ?>
                            <span class="font-medium text-secondary"><?= htmlspecialchars($hours['open'] ?? '') ?> - <?= htmlspecialchars($hours['close'] ?? '') ?></span>
                            <?php else: ?>
                            <span class="text-gray-400">Închis</span>
                            <?php endif; ?>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Right Column: Ticket Selector -->
        <div class="lg:w-1/2 xl:w-7/12">
            <!-- Selected date display -->
            <div id="selected-date-bar" class="hidden p-4 mb-4 bg-primary/5 border border-primary/20 rounded-xl">
                <div class="flex items-center justify-between">
                    <div>
                        <span class="text-sm text-gray-500">Data selectată:</span>
                        <span id="selected-date-label" class="ml-2 font-semibold text-secondary"></span>
                    </div>
                    <button id="change-date-btn" class="text-sm font-medium text-primary hover:underline">Schimbă</button>
                </div>
            </div>

            <!-- Placeholder: Select date first -->
            <div id="no-date-placeholder" class="p-8 text-center bg-white rounded-2xl border border-gray-100 shadow-sm">
                <svg class="w-16 h-16 mx-auto text-gray-300 mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                <p class="text-gray-500">Selectează o dată din calendar pentru a vedea biletele disponibile</p>
            </div>

            <!-- Loading state -->
            <div id="tickets-loading" class="hidden space-y-4">
                <div class="h-24 skeleton rounded-2xl"></div>
                <div class="h-24 skeleton rounded-2xl"></div>
                <div class="h-24 skeleton rounded-2xl"></div>
            </div>

            <!-- Ticket groups container (rendered by JS) -->
            <div id="ticket-groups" class="hidden space-y-6"></div>

            <!-- Cart summary (sticky on desktop) -->
            <div id="cart-summary" class="hidden mt-6 p-5 bg-white rounded-2xl border border-gray-100 shadow-sm sticky top-24">
                <h3 class="text-base font-bold text-secondary mb-3">Sumar comandă</h3>
                <div id="cart-items-summary" class="space-y-2 text-sm"></div>
                <div class="flex justify-between pt-3 mt-3 border-t border-gray-100">
                    <span class="font-semibold text-secondary">Total</span>
                    <span id="cart-total" class="text-lg font-bold text-primary">0 RON</span>
                </div>
                <button id="add-to-cart-btn" class="w-full mt-4 py-3 px-6 bg-primary hover:bg-primary-dark text-white font-semibold rounded-xl transition disabled:opacity-50 disabled:cursor-not-allowed" disabled>
                    Adaugă în coș
                </button>
            </div>
        </div>
    </div>

    <!-- Description Section -->
    <?php if (!empty($ev['description'])): ?>
    <section class="mt-12 p-6 bg-white rounded-2xl shadow-sm border border-gray-100">
        <h2 class="text-xl font-bold text-secondary mb-4">Despre</h2>
        <div class="prose prose-sm max-w-none text-gray-600 ep-event-description">
            <?= $ev['description'] ?>
        </div>
    </section>
    <?php endif; ?>

    <!-- Rules / Regulament -->
    <?php if (!empty($rulesHtml)): ?>
    <section class="mt-6 p-6 bg-white rounded-2xl shadow-sm border border-gray-100">
        <h2 class="text-xl font-bold text-secondary mb-4">Regulament</h2>
        <div class="prose prose-sm max-w-none text-gray-600">
            <?= $rulesHtml ?>
        </div>
    </section>
    <?php endif; ?>

    <!-- Gallery -->
    <?php if (!empty($gallery)): ?>
    <section class="mt-6 p-6 bg-white rounded-2xl shadow-sm border border-gray-100">
        <h2 class="text-xl font-bold text-secondary mb-4">Galerie</h2>
        <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-3">
            <?php foreach ($gallery as $img):
                $imgUrl = str_starts_with($img, 'http') ? $img : STORAGE_URL . '/' . $img;
            ?>
            <a href="<?= htmlspecialchars($imgUrl) ?>" target="_blank" class="block rounded-xl overflow-hidden aspect-video hover:opacity-90 transition">
                <img src="<?= htmlspecialchars($imgUrl) ?>" alt="" class="object-cover w-full h-full" loading="lazy">
            </a>
            <?php endforeach; ?>
        </div>
    </section>
    <?php endif; ?>

    <!-- Venue / Directions -->
    <?php if (!empty($ev['venue_name']) || $directionsUrl): ?>
    <section class="mt-6 p-6 bg-white rounded-2xl shadow-sm border border-gray-100">
        <h2 class="text-xl font-bold text-secondary mb-4">Locație</h2>
        <div class="flex flex-col gap-2">
            <?php if (!empty($ev['venue_name'])): ?>
            <p class="font-semibold text-secondary"><?= htmlspecialchars($ev['venue_name']) ?></p>
            <?php endif; ?>
            <?php if (!empty($ev['venue_address'])): ?>
            <p class="text-sm text-gray-500"><?= htmlspecialchars($ev['venue_address']) ?><?php if (!empty($ev['venue_city'])): ?>, <?= htmlspecialchars($ev['venue_city']) ?><?php endif; ?></p>
            <?php endif; ?>
            <?php if ($directionsUrl): ?>
            <a href="<?= htmlspecialchars($directionsUrl) ?>" target="_blank" rel="noopener"
               class="inline-flex items-center gap-2 mt-2 px-4 py-2 text-sm font-medium text-primary border border-primary/30 rounded-lg hover:bg-primary/5 transition w-fit">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                Deschide în Google Maps
            </a>
            <?php endif; ?>
        </div>
    </section>
    <?php endif; ?>
</main>

<!-- Mobile sticky bottom bar -->
<div id="mobile-bottom-bar" class="fixed bottom-0 left-0 right-0 z-40 bg-white border-t border-gray-200 shadow-lg p-4 lg:hidden hidden">
    <div class="flex items-center justify-between gap-4">
        <div>
            <div id="mobile-total-label" class="text-sm text-gray-500">Total</div>
            <div id="mobile-total-amount" class="text-xl font-bold text-primary">0 RON</div>
        </div>
        <button id="mobile-add-to-cart-btn" class="px-6 py-3 bg-primary hover:bg-primary-dark text-white font-semibold rounded-xl transition disabled:opacity-50" disabled>
            Adaugă în coș
        </button>
    </div>
</div>

<?php
require_once __DIR__ . '/includes/footer.php';
require_once __DIR__ . '/includes/scripts.php';
?>
<script src="<?= ASSETS_URL ?>/js/pages/leisure-venue.js?v=<?= filemtime(__DIR__ . '/assets/js/pages/leisure-venue.js') ?>"></script>
