<?php
/**
 * bilete.online — single activity page (v4, GetYourGuide-style).
 *
 * GYG-style redesign of the activity detail page, wired to REAL data from
 * /activities/{slug} and preserving the existing booking → cart pipeline
 * (date → slots → variants → BileteOnlineCart.addActivityItem → /cos). New
 * GYG sections (reviews, operator, recommendations, "potrivit pentru") render
 * from real data and degrade gracefully when empty.
 *
 * URL: /{city-slug}/{activity-slug} (city is cosmetic; lookup is by slug).
 * Legacy /activitate/{slug} 301-redirects here via .htaccess.
 */

$pageCacheTTL = 0; // booking widget + slots are dynamic; don't full-page cache
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/api.php';

$slug = trim((string) ($_GET['slug'] ?? ''));
if ($slug === '') {
    http_response_code(404);
    include __DIR__ . '/404.php';
    return;
}

$activityResp = api_cached("activity_detail_{$slug}", fn () => api_get('/activities/' . $slug), 60);
if (! ($activityResp['success'] ?? false) || empty($activityResp['data']['activity'])) {
    http_response_code(404);
    include __DIR__ . '/404.php';
    return;
}

$activity = $activityResp['data']['activity'];

// ------------------------------------------------------------
// Canonical URL enforcement: the public URL is /{city}/{slug}.
// If reached via the legacy /activitate/{slug} (no city param) or via the
// wrong city prefix, 301-redirect to the canonical city-prefixed URL. When
// the activity has no city we stay on /activitate/{slug} (no redirect).
// ------------------------------------------------------------
$citySlug = $activity['city']['slug'] ?? '';
$reqCity  = trim((string) ($_GET['city'] ?? ''));
if ($citySlug !== '' && $reqCity !== $citySlug) {
    header('Location: /' . rawurlencode($citySlug) . '/' . rawurlencode($activity['slug']), true, 301);
    exit;
}

// ============================================================
// HELPERS
// ============================================================
$cityName = $activity['city']['name'] ?? ($activity['venue']['city'] ?? null);
$categoryName = $activity['category']['name'] ?? null;
$categorySlug = $activity['category']['slug'] ?? '';
$organizer = $activity['organizer'] ?? null;
$heroImage = $activity['hero_image_url'] ?? $activity['cover_image_url'] ?? null;
$reviews = $activity['reviews'] ?? ['average' => 0, 'count' => 0, 'distribution' => [], 'detailed_averages' => [], 'recommend_pct' => 0, 'items' => []];

$pricedFromCents = function (?int $cents): string {
    if (! $cents || $cents <= 0) return '—';
    return number_format($cents / 100, 0, ',', '.') . ' lei';
};
$durationLabel = function (?int $min): string {
    if (! $min) return '';
    if ($min < 60) return $min . ' min';
    $h = intdiv($min, 60); $m = $min % 60;
    return $m ? "{$h}h {$m}m" : "{$h}h";
};
$langLabels = ['ro' => 'Română', 'en' => 'Engleză', 'de' => 'Germană', 'fr' => 'Franceză', 'it' => 'Italiană', 'es' => 'Spaniolă', 'hu' => 'Maghiară'];
$difficultyLabels = ['easy' => 'Ușor', 'medium' => 'Mediu', 'hard' => 'Greu', 'expert' => 'Expert'];

$langsOffered = array_values(array_map(fn ($l) => $langLabels[$l] ?? ucfirst($l), (array) ($activity['languages_offered'] ?? [])));
$flags = $activity['flags'] ?? [];

// "Potrivit pentru" — real traveler types (F3) with flag-derived fallback.
$suitableFor = [];
foreach ((array) ($activity['traveler_types'] ?? []) as $tt) {
    if (! empty($tt['name'])) $suitableFor[] = $tt['name'];
}
if (empty($suitableFor)) {
    if (! empty($flags['is_kid_friendly'])) $suitableFor[] = 'familii';
    if (! empty($flags['is_accessible'])) $suitableFor[] = 'accesibil';
    if (! empty($flags['is_outdoor'])) $suitableFor[] = 'outdoor';
    if (! empty($flags['is_indoor'])) $suitableFor[] = 'indoor';
    $suitableFor[] = 'cupluri';
    $suitableFor[] = 'grupuri';
}
$suitableFor = array_values(array_unique($suitableFor));

// Interests (F3) — thematic tags. Associated attractions (F4).
$interestTags = [];
foreach ((array) ($activity['interests'] ?? []) as $it) {
    if (! empty($it['name'])) $interestTags[] = trim((($it['icon'] ?? '') ? $it['icon'] . ' ' : '') . $it['name']);
}
$activityAttractions = is_array($activity['attractions'] ?? null) ? $activity['attractions'] : [];

// Hero badges.
$heroBadges = [];
if (! empty($flags['is_featured'])) $heroBadges[] = ['t' => 'Recomandat', 'c' => 'bg-vermilion text-paper'];
if (! empty($activity['cancellation_policy'])) $heroBadges[] = ['t' => 'Anulare gratuită', 'c' => 'bg-mint text-forest'];
if ($categoryName) $heroBadges[] = ['t' => $categoryName, 'c' => 'bg-paper-2 text-ink-soft'];
$heroBadges[] = ['t' => 'Bilete digitale', 'c' => 'bg-paper-2 text-ink-soft'];

// ============================================================
// PAGE METADATA
// ============================================================
$pageTitleRaw    = ($activity['seo']['title'] ?? null) ?: ($activity['title'] . ' — ' . SITE_NAME);
$pageDescription = $activity['seo']['description'] ?? $activity['short_description'] ?? ($activity['title'] . ' pe ' . SITE_NAME);
$canonicalUrl    = $citySlug ? (SITE_URL . '/' . $citySlug . '/' . $activity['slug']) : (SITE_URL . '/activitate/' . $activity['slug']);
$currentPage     = 'activitate';
$cssBundle       = 'single';

// Context-aware header — "Explorează {oraș}" + nearby framing on the activity page.
$headerContext = ($cityName && $citySlug)
    ? ['type' => 'activity', 'label' => $cityName, 'slug' => $citySlug]
    : ['type' => 'homepage'];

$breadcrumbs = [['name' => 'Acasă', 'url' => SITE_URL . '/']];
if ($cityName && $citySlug) $breadcrumbs[] = ['name' => $cityName, 'url' => SITE_URL . '/' . $citySlug];
if ($categoryName && $categorySlug) $breadcrumbs[] = ['name' => $categoryName, 'url' => SITE_URL . '/' . $categorySlug];
$breadcrumbs[] = ['name' => $activity['title'], 'url' => $canonicalUrl];

// JSON-LD
$variantPrices  = array_filter(array_column($activity['variants'] ?? [], 'price_cents'));
$lowPriceCents  = $variantPrices ? min($variantPrices) : ($activity['cheapest_price_cents'] ?? null);
$highPriceCents = $variantPrices ? max($variantPrices) : $lowPriceCents;
$structuredData = [[
    '@context'    => 'https://schema.org',
    '@type'       => ['Product', 'TouristAttraction'],
    'name'        => $activity['title'],
    'description' => $activity['short_description'] ?? '',
    'image'       => $heroImage,
    'category'    => $categoryName,
    'url'         => $canonicalUrl,
    'offers'      => $lowPriceCents ? [
        '@type'         => 'AggregateOffer',
        'priceCurrency' => 'RON',
        'lowPrice'      => number_format($lowPriceCents / 100, 2, '.', ''),
        'highPrice'     => number_format($highPriceCents / 100, 2, '.', ''),
        'offerCount'    => count($activity['variants'] ?? []),
        'availability'  => 'https://schema.org/InStock',
    ] : null,
    'aggregateRating' => (($reviews['count'] ?? 0) > 0) ? [
        '@type'       => 'AggregateRating',
        'ratingValue' => $reviews['average'],
        'reviewCount' => $reviews['count'],
    ] : null,
]];
if (! empty($activity['faqs']) && is_array($activity['faqs'])) {
    $structuredData[] = [
        '@context'   => 'https://schema.org',
        '@type'      => 'FAQPage',
        'mainEntity' => array_map(fn ($faq) => [
            '@type'          => 'Question',
            'name'           => $faq['q'] ?? '',
            'acceptedAnswer' => ['@type' => 'Answer', 'text' => $faq['a'] ?? ''],
        ], $activity['faqs']),
    ];
}

// ============================================================
// ALPINE BOOTSTRAP — real booking pipeline (unchanged contract)
// ============================================================
$bookingBootstrap = [
    'activity_id'      => (int) ($activity['id'] ?? 0),
    'slug'             => $activity['slug'],
    'title'            => $activity['title'] ?? '',
    'cover_image'      => $activity['cover_image_url'] ?? ($activity['hero_image_url'] ?? null),
    'venue_name'       => $activity['venue']['name'] ?? null,
    'venue_city'       => $cityName,
    'organizer_id'     => (int) ($organizer['id'] ?? 0) ?: null,
    'duration_minutes' => (int) ($activity['duration_minutes'] ?? 0) ?: null,
    'commission_rate'  => (float) ($organizer['commission_rate'] ?? 0),
    'commission_mode'  => (string) ($organizer['commission_mode'] ?? 'included'),
    'variants'         => array_map(fn ($v) => [
        'id'             => $v['id'],
        'name'           => $v['name'],
        'description'    => $v['description'] ?? null,
        'price_cents'    => (int) $v['price_cents'],
        'capacity_share' => (int) $v['capacity_share'],
        'min_per_order'  => (int) $v['min_per_order'],
        'max_per_order'  => (int) $v['max_per_order'],
        'min_age'        => $v['min_age'],
        'max_age'        => $v['max_age'],
    ], $activity['variants'] ?? []),
    'window'  => $activity['booking_window'] ?? ['lead_time_hours' => 2, 'max_advance_days' => 60, 'min_participants' => 1, 'max_participants' => 10],
    'gallery' => array_values(array_filter(array_map(fn ($url) => $url ? ['src' => $url, 'alt' => $activity['title']] : null, $activity['gallery'] ?? []))),
    'reviews' => $reviews,
    // Loyalty estimate (display only — exact points computed at checkout from
    // the marketplace gamification config). earn_percentage = % of subtotal
    // value awarded; point_value_cents = value of 1 point in cents.
    'earn_percentage'   => 5,
    'point_value_cents' => 1,
];

// Recommendation rails (only when ≥1 card each).
$rails = [];
if (! empty($activity['related'])) {
    $rails[] = ['kicker' => 'CONEXIUNI', 'title' => 'Te-ar putea interesa', 'cards' => $activity['related']];
}
$recs = $activity['recommendations'] ?? [];
if (! empty($recs['same_organizer'])) {
    $rails[] = ['kicker' => 'ACELAȘI OPERATOR', 'title' => ($organizer['name'] ?? null) ? 'Alte experiențe de la ' . $organizer['name'] : 'Alte experiențe ale operatorului', 'cards' => $recs['same_organizer']];
}
if (! empty($recs['same_city_same_cat'])) {
    $rails[] = ['kicker' => 'SIMILARE', 'title' => ($cityName && $categoryName) ? "{$categoryName} în {$cityName}" : 'Activități similare', 'cards' => $recs['same_city_same_cat']];
}
if (! empty($recs['same_city'])) {
    $rails[] = ['kicker' => 'ÎN ACELAȘI ORAȘ', 'title' => $cityName ? "Alte experiențe în {$cityName}" : 'Alte experiențe', 'cards' => $recs['same_city']];
}

// F2 — proximity rail. Real Haversine distance from the API; cards carry a
// distance_km the rail card can surface. Placed high (right after the curated
// recommendations) since "near me" is a primary GYG discovery pattern.
if (! empty($activity['nearby'])) {
    $rails[] = ['kicker' => 'ÎN APROPIERE', 'title' => 'Activități în apropiere', 'cards' => $activity['nearby'], 'show_distance' => true];
}

// Extra GYG-style discovery rails built from a city activities pool. Same pool,
// different framing/order (Top / Experiențe) — like GetYourGuide destination
// pages. Only added when there's enough to show; empty rails are skipped.
$cityLabel = $cityName ?: 'orașul tău';
$cityPool = [];
if ($citySlug) {
    $cpResp = api_cached("city_pool_{$citySlug}", fn () => api_get('/activities', ['city' => $citySlug, 'per_page' => 16]), 300);
    $cpRaw = $cpResp['data']['items'] ?? $cpResp['data']['data'] ?? (is_array($cpResp['data'] ?? null) ? $cpResp['data'] : []);
    foreach ((is_array($cpRaw) ? $cpRaw : []) as $ca) {
        if ((int) ($ca['id'] ?? 0) !== (int) $activity['id']) $cityPool[] = $ca;
    }
}
if (count($cityPool) >= 1 && empty($recs['same_city'])) {
    $rails[] = ['kicker' => 'ÎN ' . mb_strtoupper($cityLabel), 'title' => 'Alte experiențe în ' . $cityLabel, 'cards' => array_slice($cityPool, 0, 8)];
}
if (count($cityPool) >= 3) {
    $top = $cityPool;
    usort($top, fn ($a, $b) => ((($b['flags']['is_featured'] ?? false) ? 1 : 0) <=> (($a['flags']['is_featured'] ?? false) ? 1 : 0)));
    $rails[] = ['kicker' => 'TOP', 'title' => 'Top activități în ' . $cityLabel, 'cards' => array_slice($top, 0, 8)];
}
if (count($cityPool) >= 3) {
    $rails[] = ['kicker' => 'EXPERIENȚE', 'title' => 'Experiențe de descoperit în ' . $cityLabel, 'cards' => array_slice(array_reverse($cityPool), 0, 8)];
}

// Card URL helper (city-prefixed when the card carries a city).
$cardUrl = function (array $c): string {
    $cs = $c['city']['slug'] ?? '';
    return $cs ? ('/' . $cs . '/' . ($c['slug'] ?? '')) : ('/activitate/' . ($c['slug'] ?? ''));
};

include __DIR__ . '/includes/head.php';
include __DIR__ . '/includes/header.php';
?>

<main id="top" x-data="activityPage(<?= htmlspecialchars(json_encode($bookingBootstrap, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), ENT_QUOTES) ?>)" class="bg-paper">

  <!-- Breadcrumb -->
  <div class="mx-auto max-w-[1500px] px-4 pt-5 sm:px-6">
    <nav class="flex flex-wrap items-center gap-2 text-sm font-bold text-ink-soft" aria-label="Breadcrumb">
      <?php foreach ($breadcrumbs as $i => $b): ?>
        <?php if ($i > 0): ?><span>/</span><?php endif; ?>
        <?php if ($i < count($breadcrumbs) - 1): ?>
          <a href="<?= htmlspecialchars($b['url']) ?>" class="hover:text-vermilion"><?= htmlspecialchars($b['name']) ?></a>
        <?php else: ?>
          <span class="text-ink truncate max-w-[60vw]"><?= htmlspecialchars($b['name']) ?></span>
        <?php endif; ?>
      <?php endforeach; ?>
    </nav>
  </div>

  <!-- HERO + BOOKING -->
  <section class="mx-auto max-w-[1500px] px-4 py-5 sm:px-6 lg:py-8">
    <div class="grid gap-8 lg:grid-cols-[minmax(0,1fr)_410px] xl:grid-cols-[minmax(0,1fr)_440px]">
      <div class="min-w-0">
        <div class="flex flex-wrap gap-2">
          <?php foreach ($heroBadges as $b): ?>
            <span class="rounded-full px-3 py-1 text-xs font-bold <?= $b['c'] ?>"><?= htmlspecialchars($b['t']) ?></span>
          <?php endforeach; ?>
        </div>

        <h1 class="mt-4 font-display text-5xl font-bold leading-[.9] sm:text-6xl lg:text-7xl"><?= htmlspecialchars($activity['title']) ?></h1>

        <div class="mt-4 flex flex-wrap items-center gap-3 text-sm font-bold">
          <?php if (($reviews['count'] ?? 0) > 0): ?>
            <a href="#review-uri" class="inline-flex items-center gap-1 text-ochre underline-wobble">
              <span>★</span><span class="text-ink"><?= htmlspecialchars(number_format((float) $reviews['average'], 1)) ?></span>
              <span class="text-ink-soft">(<?= (int) $reviews['count'] ?> review-uri)</span>
            </a>
            <span class="text-ink-soft">·</span>
          <?php endif; ?>
          <?php if ($organizer): ?>
            <a href="/operator/<?= htmlspecialchars($organizer['slug'] ?? '') ?>" class="text-ink-soft underline-wobble hover:text-vermilion"><?= htmlspecialchars($organizer['name'] ?? 'Operator') ?></a>
            <span class="text-ink-soft">·</span>
          <?php endif; ?>
          <span class="text-ink-soft">ID: BO-<?= (int) $activity['id'] ?></span>
        </div>

        <?php if (! empty($activity['short_description'])): ?>
          <p class="mt-5 max-w-4xl text-xl leading-relaxed text-ink-soft"><?= htmlspecialchars($activity['short_description']) ?></p>
        <?php endif; ?>

        <!-- Gallery (graceful when empty) -->
        <?php $gal = $bookingBootstrap['gallery']; ?>
        <?php if (count($gal) > 0): ?>
          <div class="mt-7 grid h-[420px] gap-3 overflow-hidden rounded-[2rem] border-2 border-ink bg-ink p-2 shadow-deep md:h-[520px] md:grid-cols-[1.3fr_.7fr]">
            <button @click="openGallery(0)" class="group relative overflow-hidden rounded-[1.5rem] text-left">
              <img :src="gallery[0]?.src" :alt="gallery[0]?.alt" class="h-full w-full object-cover transition duration-500 group-hover:scale-105">
              <div class="absolute inset-0 bg-gradient-to-t from-ink/65 via-transparent to-transparent"></div>
              <span class="absolute bottom-4 left-4 rounded-full bg-paper px-4 py-2 text-sm font-bold text-ink">Vezi galeria</span>
            </button>
            <div class="hidden grid-cols-2 gap-3 md:grid" x-show="gallery.length > 1">
              <template x-for="(image, index) in gallery.slice(1,5)" :key="image.src">
                <button @click="openGallery(index+1)" class="group relative overflow-hidden rounded-[1.5rem]">
                  <img :src="image.src" :alt="image.alt" class="h-full w-full object-cover transition duration-500 group-hover:scale-105">
                </button>
              </template>
            </div>
          </div>
        <?php else: ?>
          <div class="mt-7 grid h-[320px] place-items-center overflow-hidden rounded-[2rem] border-2 border-ink bg-gradient-to-br from-vermilion via-ochre to-forest text-paper shadow-deep md:h-[420px]">
            <div class="text-center">
              <p class="font-display text-5xl font-bold leading-none sm:text-6xl"><?= htmlspecialchars(mb_substr($activity['title'], 0, 22)) ?></p>
              <p class="mt-3 font-mono text-xs tracking-[.2em] text-paper/70">BILETE.ONLINE · ACTIVITATE</p>
            </div>
          </div>
        <?php endif; ?>

        <!-- Facts -->
        <div class="mt-6 grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
          <?php
          $facts = [];
          if (! empty($activity['duration_minutes'])) $facts[] = ['⏱️', $durationLabel((int) $activity['duration_minutes']), 'durată'];
          if ($langsOffered) $facts[] = ['🎧', implode(' / ', array_slice($langsOffered, 0, 3)), 'limbi disponibile'];
          if (! empty($activity['cancellation_policy'])) $facts[] = ['↩️', 'Anulare gratuită', 'vezi condițiile'];
          if (! empty($activity['meeting_point'])) $facts[] = ['📍', 'Punct de întâlnire', 'detalii mai jos'];
          if (! empty($activity['difficulty_level'])) $facts[] = ['🎯', $difficultyLabels[$activity['difficulty_level']] ?? ucfirst($activity['difficulty_level']), 'dificultate'];
          $facts = array_slice($facts, 0, 4);
          foreach ($facts as $f): ?>
            <article class="rounded-[1.5rem] border border-ink/10 bg-paper-2 p-4">
              <p class="text-2xl"><?= $f[0] ?></p>
              <h2 class="mt-2 font-display text-2xl font-bold leading-none"><?= htmlspecialchars($f[1]) ?></h2>
              <p class="mt-1 text-sm text-ink-soft"><?= htmlspecialchars($f[2]) ?></p>
            </article>
          <?php endforeach; ?>
        </div>

        <!-- Mobile sticky price bar -->
        <div class="fixed inset-x-0 bottom-0 z-40 border-t-2 border-ink bg-paper p-3 lg:hidden">
          <div class="flex items-center justify-between gap-3">
            <div><p class="text-xs font-bold text-ink-soft">de la</p><p class="font-display text-3xl font-bold leading-none"><?= $pricedFromCents($activity['cheapest_price_cents'] ?? $lowPriceCents) ?></p></div>
            <a href="#rezervare" class="rounded-full bg-vermilion px-6 py-4 font-bold text-paper">Alege bilete</a>
          </div>
        </div>

        <!-- Section nav -->
        <nav class="sticky top-[64px] z-30 mt-6 hidden overflow-x-auto border-y border-ink/10 bg-paper/95 py-3 backdrop-blur md:block">
          <div class="flex gap-2 text-sm font-bold">
            <a href="#descriere" class="rounded-full px-4 py-2 hover:bg-paper-2">Descriere</a>
            <?php if ($activity['included_items'] || $activity['not_included']): ?><a href="#include" class="rounded-full px-4 py-2 hover:bg-paper-2">Include</a><?php endif; ?>
            <a href="#review-uri" class="rounded-full px-4 py-2 hover:bg-paper-2">Review-uri</a>
            <?php if (! empty($activity['faqs'])): ?><a href="#faq" class="rounded-full px-4 py-2 hover:bg-paper-2">FAQ</a><?php endif; ?>
          </div>
        </nav>
      </div>

      <!-- BOOKING ASIDE (real flow, v4 styling) -->
      <aside id="rezervare" class="lg:sticky lg:top-[88px] lg:self-start">
        <div class="overflow-hidden rounded-[2rem] border-2 border-ink bg-paper shadow-deep">
          <div class="bg-ink p-5 text-paper">
            <div class="flex items-start justify-between gap-4">
              <div>
                <p class="font-mono text-xs tracking-[.18em] text-paper/45">REZERVARE RAPIDĂ</p>
                <p class="mt-2 text-paper/60">de la</p>
                <p class="font-display text-5xl font-bold leading-none"><?= $pricedFromCents($activity['cheapest_price_cents'] ?? $lowPriceCents) ?></p>
                <p class="mt-1 text-sm text-paper/55" x-show="totalSeatsUsed>0" x-text="participantsLabel"></p>
              </div>
            </div>
          </div>

          <div class="space-y-4 p-5">
            <!-- Date -->
            <div>
              <div class="mb-1.5 flex items-center justify-between gap-3">
                <label class="block text-sm font-bold">Selectează data</label>
                <button @click="calendarOpen=!calendarOpen" type="button" class="rounded-full bg-paper-2 px-3 py-1 text-xs font-bold transition hover:bg-ink hover:text-paper" x-text="calendarOpen ? 'Ascunde calendar' : 'Alege din calendar'"></button>
              </div>
              <input type="date" class="field w-full" :min="minDate" :max="maxDate" x-model="selectedDate" @change="loadSlots()">
              <div x-show="calendarOpen" x-collapse class="mt-3 rounded-[1.5rem] border-2 border-ink bg-paper-2 p-4">
                <div class="flex items-center justify-between gap-3">
                  <button @click="prevMonth()" type="button" class="grid h-9 w-9 place-items-center rounded-full bg-paper font-bold transition hover:bg-ink hover:text-paper">←</button>
                  <p class="font-display text-2xl font-bold leading-none" x-text="calendarTitle()"></p>
                  <button @click="nextMonth()" type="button" class="grid h-9 w-9 place-items-center rounded-full bg-paper font-bold transition hover:bg-ink hover:text-paper">→</button>
                </div>
                <div class="mt-4 grid grid-cols-7 gap-1 text-center text-[11px] font-bold text-ink-soft"><span>L</span><span>M</span><span>M</span><span>J</span><span>V</span><span>S</span><span>D</span></div>
                <div class="mt-2 grid grid-cols-7 gap-1">
                  <template x-for="day in calendarDays()" :key="day.key">
                    <button type="button" @click="day.selectable && selectDate(day.value)" :disabled="!day.selectable" :class="['grid h-10 place-items-center rounded-xl text-sm font-bold transition', day.inMonth ? 'text-ink' : 'text-ink-soft/40', day.selectable ? 'bg-paper hover:bg-ink hover:text-paper' : 'bg-paper/40 cursor-not-allowed opacity-40', day.value===selectedDate ? 'bg-ink text-paper' : '']">
                      <span x-text="day.label"></span>
                    </button>
                  </template>
                </div>
              </div>
            </div>

            <!-- Slots -->
            <div>
              <label class="mb-1.5 block text-sm font-bold">Ora</label>
              <div x-show="loadingSlots" class="rounded-2xl bg-paper-2 px-4 py-3 text-sm text-ink-soft">Se încarcă orele disponibile…</div>
              <div x-show="!loadingSlots && slots.length===0" class="rounded-2xl bg-paper-2 px-4 py-3 text-sm text-ink-soft">Nu există ore disponibile pentru această dată. Alege altă zi.</div>
              <div x-show="!loadingSlots && slots.length>0" class="grid grid-cols-3 gap-2">
                <template x-for="slot in slots" :key="slot.start_time">
                  <button type="button" @click="selectedSlot=slot.start_time; Object.keys(quantities).forEach(k=>quantities[k]=0)" :class="selectedSlot===slot.start_time ? 'bg-ink text-paper' : 'bg-paper-2 hover:bg-paper-3'" class="rounded-2xl p-2.5 text-center transition">
                    <span class="block font-display text-lg font-bold leading-none" x-text="fmtTime(slot.start_time)"></span>
                    <span class="block text-[11px] font-bold" :class="(slot.capacity_remaining||0)<=3 ? 'text-vermilion' : 'text-ink-soft'" x-text="(slot.capacity_remaining||0) + ' locuri'"></span>
                  </button>
                </template>
              </div>
            </div>

            <!-- Variants -->
            <div x-show="selectedSlot" class="space-y-3">
              <template x-for="v in variants" :key="v.id">
                <div class="rounded-3xl border border-ink/10 bg-paper-2 p-4">
                  <div class="flex items-center justify-between gap-4">
                    <div class="min-w-0">
                      <p class="font-bold" x-text="v.name"></p>
                      <p class="text-sm text-ink-soft"><span x-text="money(v.price_cents)"></span><span x-show="v.capacity_share>1"> · <span x-text="v.capacity_share"></span> locuri</span></p>
                    </div>
                    <div class="inline-flex overflow-hidden rounded-full border-2 border-ink">
                      <button @click="decrement(v.id)" class="grid h-10 w-10 place-items-center font-bold hover:bg-ink hover:text-paper">−</button>
                      <span class="grid h-10 w-10 place-items-center border-x-2 border-ink font-bold" x-text="quantities[v.id]||0"></span>
                      <button @click="increment(v.id)" class="grid h-10 w-10 place-items-center font-bold hover:bg-ink hover:text-paper">+</button>
                    </div>
                  </div>
                </div>
              </template>
            </div>

            <!-- Breakdown -->
            <div x-show="totalSeatsUsed>0" class="rounded-3xl border border-ink/10 bg-paper-2 p-4">
              <div class="space-y-2 text-sm">
                <div class="flex justify-between"><span>Bilete</span><strong x-text="money(totalCents)"></strong></div>
                <div x-show="commissionRate>0 && commissionMode==='added_on_top'" class="flex justify-between"><span>Comision platformă estimat (<span x-text="commissionRate"></span>%)</span><strong x-text="money(Math.round(totalCents*commissionRate/100))"></strong></div>
                <div class="flex items-end justify-between border-t border-ink/10 pt-3 text-lg"><span class="font-bold">Total estimat</span><strong class="font-display text-4xl leading-none" x-text="money(commissionMode==='added_on_top' ? totalCents + Math.round(totalCents*commissionRate/100) : totalCents)"></strong></div>
                <div class="flex justify-between rounded-2xl bg-mint px-3 py-2 text-forest"><span class="font-bold">Puncte bonus estimate</span><strong x-text="'+' + pointsEstimate() + ' puncte'"></strong></div>
                <p class="text-xs text-ink-soft">Taxele și punctele finale se calculează la checkout.</p>
              </div>
            </div>

            <button @click="submitBooking('cart')" :disabled="!canSubmit" :class="canSubmit ? 'bg-vermilion hover:bg-vermilion-d' : 'bg-ink/20 cursor-not-allowed'" class="w-full rounded-full px-6 py-4 text-center font-bold text-paper transition">Adaugă în coș</button>
            <button @click="submitBooking('checkout')" :disabled="!canSubmit" :class="canSubmit ? 'bg-ink hover:bg-forest text-paper' : 'bg-ink/10 text-ink-soft cursor-not-allowed'" class="w-full rounded-full px-6 py-4 text-center font-bold transition">Rezervă acum — direct la checkout</button>

            <!-- Points reward card -->
            <div x-show="totalSeatsUsed>0" class="rounded-3xl border border-forest/20 bg-mint p-4">
              <div class="flex items-center justify-between gap-3">
                <div>
                  <p class="font-bold text-forest">Câștigi puncte</p>
                  <p class="text-sm text-ink-soft">Primești <strong x-text="pointsEstimate()"></strong> puncte după confirmarea participării.</p>
                </div>
                <span class="font-display text-4xl font-bold text-forest" x-text="'+' + pointsEstimate()"></span>
              </div>
            </div>

            <div class="grid grid-cols-4 gap-2 text-center text-[11px] font-bold text-ink-soft">
              <span class="rounded-2xl bg-paper-2 px-2 py-2">Apple Pay</span><span class="rounded-2xl bg-paper-2 px-2 py-2">Google Pay</span><span class="rounded-2xl bg-paper-2 px-2 py-2">Card</span><span class="rounded-2xl bg-paper-2 px-2 py-2">Revolut</span>
            </div>
            <p class="text-center text-xs text-ink-soft">Nu se percepe plată până la confirmarea checkout-ului.</p>
          </div>
        </div>

        <!-- Benefits -->
        <div class="mt-4 rounded-[2rem] border-2 border-ink bg-paper p-5 shadow-ticket">
          <p class="font-mono text-xs tracking-[.18em] text-ink-soft">BENEFICII REZERVARE</p>
          <div class="mt-4 space-y-3 text-sm">
            <?php if (! empty($activity['cancellation_policy'])): ?><div class="flex gap-3"><span class="text-forest">✓</span><p><strong>Anulare gratuită</strong><br><span class="text-ink-soft"><?= htmlspecialchars($activity['cancellation_policy']) ?></span></p></div><?php endif; ?>
            <div class="flex gap-3"><span class="text-forest">✓</span><p><strong>Bilete digitale</strong><br><span class="text-ink-soft">primești QR pe email după confirmare</span></p></div>
            <?php if (! empty($activity['meeting_point'])): ?><div class="flex gap-3"><span class="text-forest">✓</span><p><strong>Punct de întâlnire</strong><br><span class="text-ink-soft"><?= htmlspecialchars($activity['meeting_point']) ?></span></p></div><?php endif; ?>
          </div>
        </div>
      </aside>
    </div>
  </section>

  <!-- DETAILS -->
  <section class="mx-auto max-w-[1500px] px-4 pb-20 sm:px-6">
    <div class="grid gap-8 lg:grid-cols-[minmax(0,1fr)_410px] xl:grid-cols-[minmax(0,1fr)_440px]">
      <div class="space-y-8">
        <!-- Description -->
        <section id="descriere" class="rounded-[2rem] border-2 border-ink bg-paper p-6 shadow-ticket sm:p-8">
          <h2 class="mt-2 font-display text-4xl font-bold leading-none sm:text-5xl"><?= htmlspecialchars($activity['seo']['body_title'] ?? ('Despre ' . $activity['title'])) ?></h2>
          <div class="mt-6 grid gap-6 lg:grid-cols-[1fr_280px]">
            <div class="prose-bo space-y-4 text-lg leading-relaxed text-ink-soft"><?= $activity['description'] ?? '' ?></div>
            <aside class="rounded-3xl bg-paper-2 p-5">
              <p class="font-bold">Potrivit pentru</p>
              <div class="mt-3 flex flex-wrap gap-2">
                <?php foreach ($suitableFor as $s): ?><span class="rounded-full bg-paper px-3 py-1 text-sm font-bold"><?= htmlspecialchars($s) ?></span><?php endforeach; ?>
              </div>
              <?php if (! empty($interestTags)): ?>
                <p class="mt-5 font-bold">Interese</p>
                <div class="mt-3 flex flex-wrap gap-2">
                  <?php foreach ($interestTags as $it): ?><span class="rounded-full bg-mint px-3 py-1 text-sm font-bold text-forest"><?= htmlspecialchars($it) ?></span><?php endforeach; ?>
                </div>
              <?php endif; ?>
              <?php if (! empty($activity['requirements'])): ?>
                <p class="mt-5 font-bold">Cerințe</p>
                <ul class="mt-2 space-y-1.5 text-sm text-ink-soft">
                  <?php foreach ($activity['requirements'] as $r): ?><li class="flex gap-2"><span class="text-vermilion">•</span><span><?= htmlspecialchars($r) ?></span></li><?php endforeach; ?>
                </ul>
              <?php endif; ?>
            </aside>
          </div>
        </section>

        <!-- Operator -->
        <?php if ($organizer): ?>
        <section class="rounded-[2rem] border-2 border-ink bg-paper p-6 shadow-ticket sm:p-8">
          <div class="grid gap-5 md:grid-cols-[84px_1fr_auto] md:items-center">
            <div class="grid h-20 w-20 place-items-center rounded-3xl bg-ink text-paper font-display text-3xl font-bold"><?= htmlspecialchars(mb_strtoupper(mb_substr($organizer['name'] ?? 'O', 0, 2))) ?></div>
            <div>
              <p class="font-mono text-xs tracking-[.18em] text-ink-soft">OPERATOR ACTIVITATE</p>
              <h2 class="mt-1 font-display text-3xl font-bold leading-none sm:text-4xl"><?= htmlspecialchars($organizer['name'] ?? 'Operator') ?></h2>
              <?php if (($reviews['count'] ?? 0) > 0): ?>
                <div class="mt-3 flex flex-wrap gap-2">
                  <span class="rounded-full bg-mint px-3 py-1 text-xs font-bold text-forest"><?= htmlspecialchars(number_format((float) $reviews['average'], 1)) ?> rating</span>
                  <span class="rounded-full bg-paper-2 px-3 py-1 text-xs font-bold text-ink-soft"><?= (int) $reviews['count'] ?> review-uri</span>
                </div>
              <?php endif; ?>
            </div>
            <a href="/operator/<?= htmlspecialchars($organizer['slug'] ?? '') ?>" class="rounded-full border-2 border-ink px-5 py-3 text-center font-bold transition hover:bg-ink hover:text-paper">Vezi operator</a>
          </div>
        </section>
        <?php endif; ?>

        <!-- Included / Not included -->
        <?php if (! empty($activity['included_items']) || ! empty($activity['not_included'])): ?>
        <section id="include" class="grid gap-5 md:grid-cols-2">
          <?php if (! empty($activity['included_items'])): ?>
          <div class="rounded-[2rem] border-2 border-forest bg-mint p-6 shadow-ticket sm:p-8">
            <p class="font-mono text-xs tracking-[.18em] text-forest">INCLUS</p>
            <h2 class="mt-2 font-display text-3xl font-bold leading-none sm:text-4xl">Inclus în preț</h2>
            <ul class="mt-5 space-y-3"><?php foreach ($activity['included_items'] as $it): ?><li class="flex gap-3"><span class="text-forest">✓</span><span><?= htmlspecialchars($it) ?></span></li><?php endforeach; ?></ul>
          </div>
          <?php endif; ?>
          <?php if (! empty($activity['not_included'])): ?>
          <div class="rounded-[2rem] border-2 border-vermilion bg-rose p-6 shadow-ticket sm:p-8">
            <p class="font-mono text-xs tracking-[.18em] text-vermilion">NEINCLUS</p>
            <h2 class="mt-2 font-display text-3xl font-bold leading-none sm:text-4xl">Nu este inclus</h2>
            <ul class="mt-5 space-y-3"><?php foreach ($activity['not_included'] as $it): ?><li class="flex gap-3"><span class="text-vermilion">×</span><span><?= htmlspecialchars($it) ?></span></li><?php endforeach; ?></ul>
          </div>
          <?php endif; ?>
        </section>
        <?php endif; ?>

        <!-- Map + meeting point -->
        <?php if (! empty($activity['venue']['lat']) && ! empty($activity['venue']['lng'])): ?>
        <?php $vLat = $activity['venue']['lat']; $vLng = $activity['venue']['lng']; $vAddr = trim(($activity['venue']['address'] ?? '') . ($cityName ? ', ' . $cityName : ''), ', '); ?>
        <section class="overflow-hidden rounded-[2rem] border-2 border-ink bg-paper shadow-ticket">
          <div class="grid lg:grid-cols-[400px_1fr]">
            <div class="p-6 sm:p-8 lg:border-r-2 lg:border-ink/10">
              <p class="font-mono text-xs tracking-[.18em] text-ink-soft">LOCAȚIE & PUNCT DE ÎNTÂLNIRE</p>
              <h2 class="mt-2 font-display text-3xl font-bold leading-none sm:text-4xl"><?= htmlspecialchars($activity['venue']['name'] ?? 'Punct de întâlnire') ?></h2>
              <?php if ($vAddr): ?>
                <div class="mt-5 flex items-start gap-3">
                  <span class="grid h-11 w-11 flex-none place-items-center rounded-2xl bg-vermilion text-paper text-lg">📍</span>
                  <div><p class="font-bold leading-snug"><?= htmlspecialchars($vAddr) ?></p><?php if ($cityName): ?><p class="text-sm text-ink-soft"><?= htmlspecialchars($cityName) ?></p><?php endif; ?></div>
                </div>
              <?php endif; ?>
              <?php if (! empty($activity['meeting_point'])): ?>
                <div class="mt-5 rounded-3xl border border-ink/10 bg-paper-2 p-4">
                  <p class="font-bold">Cum ne găsești</p>
                  <p class="mt-1 text-sm leading-relaxed text-ink-soft"><?= htmlspecialchars($activity['meeting_point']) ?></p>
                </div>
              <?php endif; ?>
              <a href="https://www.google.com/maps/dir/?api=1&destination=<?= urlencode($vLat . ',' . $vLng) ?>" target="_blank" rel="noopener" class="mt-5 inline-flex items-center gap-2 rounded-full bg-ink px-5 py-3 font-bold text-paper transition hover:bg-vermilion">
                <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 20l-5.447-2.724A1 1 0 013 16.382V5.618a1 1 0 011.447-.894L9 7m0 13l6-3m-6 3V7m6 10l4.553 2.276A1 1 0 0021 18.382V7.618a1 1 0 00-.553-.894L15 4m0 13V4m0 0L9 7"/></svg>
                Deschide în Google Maps
              </a>
            </div>
            <div class="relative min-h-[340px] bg-paper-2">
              <iframe class="absolute inset-0 h-full w-full" loading="lazy" referrerpolicy="no-referrer-when-downgrade" src="https://www.google.com/maps?q=<?= urlencode($vLat . ',' . $vLng) ?>&z=15&output=embed"></iframe>
            </div>
          </div>
        </section>
        <?php endif; ?>

        <!-- FAQ -->
        <?php if (! empty($activity['faqs'])): ?>
        <section id="faq" class="rounded-[2rem] border-2 border-ink bg-paper p-6 shadow-ticket sm:p-8" x-data="{open:0}">
          <p class="font-mono text-xs tracking-[.18em] text-ink-soft">FAQ</p>
          <h2 class="mt-2 font-display text-4xl font-bold leading-none sm:text-5xl">Întrebări frecvente</h2>
          <div class="mt-6 divide-y divide-ink/10 overflow-hidden rounded-3xl border border-ink/10">
            <?php foreach ($activity['faqs'] as $i => $faq): ?>
              <div class="bg-paper-2">
                <button @click="open=open===<?= $i ?>?null:<?= $i ?>" class="flex w-full items-center justify-between gap-4 p-5 text-left font-bold"><span><?= htmlspecialchars($faq['q'] ?? '') ?></span><span x-text="open===<?= $i ?>?'−':'+'"></span></button>
                <div x-show="open===<?= $i ?>" x-collapse class="px-5 pb-5 text-ink-soft"><?= htmlspecialchars($faq['a'] ?? '') ?></div>
              </div>
            <?php endforeach; ?>
          </div>
        </section>
        <?php endif; ?>
      </div>

      <!-- Help sidebar -->
      <aside class="hidden lg:block">
        <div class="rounded-[2rem] border border-ink/10 bg-paper-2 p-5">
          <p class="font-mono text-xs tracking-[.18em] text-ink-soft">AI NEVOIE DE AJUTOR?</p>
          <h2 class="mt-2 font-display text-3xl font-bold leading-none">Nu ești sigur dacă ți se potrivește?</h2>
          <p class="mt-3 text-ink-soft">Scrie-ne și îți spunem dacă activitatea e potrivită pentru grupul tău.</p>
          <a href="/contact?activity=<?= htmlspecialchars($activity['slug']) ?>" class="mt-5 inline-flex rounded-full bg-ink px-5 py-3 font-bold text-paper transition hover:bg-vermilion">Întreabă suport</a>
        </div>
      </aside>
    </div>
  </section>

  <!-- CUSTOMER REVIEWS (full-width) -->
  <section id="review-uri" class="border-y-2 border-ink bg-paper">
    <div class="mx-auto max-w-[1500px] px-4 py-14 sm:px-6 lg:py-20">
      <div class="grid gap-8 lg:grid-cols-[360px_1fr]">
        <aside class="lg:sticky lg:top-28 lg:self-start">
          <h2 class="mt-2 font-display text-5xl font-bold leading-none sm:text-6xl">Ce spun clienții</h2>
          <?php if (($reviews['count'] ?? 0) > 0): ?>
            <div class="mt-6 rounded-[2rem] border-2 border-ink bg-ink p-6 text-paper shadow-deep">
              <div class="relative inline-block">
                <p class="pl-4 font-display text-7xl font-bold leading-none sm:text-8xl"><?= htmlspecialchars(number_format((float) $reviews['average'], 1)) ?></p>
                <p class="text-2xl text-ochre absolute left-0 top-0">★</p>
              </div>
              <p class="mt-2 text-paper/60"><?= (int) $reviews['count'] ?> review-uri verificate</p>
              <?php if (! empty($reviews['detailed_averages'])): ?>
                <div class="mt-6 space-y-3">
                  <?php foreach ($reviews['detailed_averages'] as $aspect => $val): ?>
                    <div class="flex items-center gap-3"><span class="w-24 text-sm font-bold capitalize"><?= htmlspecialchars($aspect) ?></span><div class="h-2 flex-1 rounded-full bg-paper/15"><div class="h-full rounded-full bg-ochre" style="width: <?= max(0, min(100, ((float) $val / 5) * 100)) ?>%"></div></div><span class="text-sm font-bold"><?= htmlspecialchars(number_format((float) $val, 1)) ?></span></div>
                  <?php endforeach; ?>
                </div>
              <?php endif; ?>
            </div>
          <?php else: ?>
            <div class="mt-6 rounded-[2rem] border-2 border-ink bg-paper-2 p-6">
              <p class="font-display text-3xl font-bold">Încă niciun review</p>
              <p class="mt-2 text-ink-soft">Fii primul care lasă un review după ce participi la această activitate.</p>
            </div>
          <?php endif; ?>
        </aside>
        <div>
          <?php if (! empty($reviews['items'])): ?>
            <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
              <?php foreach ($reviews['items'] as $rv): ?>
                <article class="rounded-[2rem] border-2 border-ink bg-paper-2 p-6 shadow-ticket">
                  <div class="flex items-start justify-between gap-4">
                    <div class="flex items-center gap-3">
                      <span class="grid h-12 w-12 place-items-center rounded-full bg-ink font-bold text-paper"><?= htmlspecialchars($rv['initial'] ?? '?') ?></span>
                      <div><p class="font-bold"><?= htmlspecialchars($rv['name'] ?? '') ?></p><p class="text-sm text-ink-soft"><?= htmlspecialchars($rv['meta'] ?? '') ?></p></div>
                    </div>
                    <span class="text-ochre text-sm font-bold"><?= (int) ($rv['rating'] ?? 5) ?>★</span>
                  </div>
                  <p class="mt-4 leading-relaxed text-ink-soft"><?= htmlspecialchars($rv['text'] ?? '') ?></p>
                  <p class="mt-4 inline-flex rounded-full bg-mint px-3 py-1 text-xs font-bold text-forest">Rezervare verificată</p>
                </article>
              <?php endforeach; ?>
            </div>
          <?php endif; ?>
          <div class="mt-6 rounded-[2rem] border-2 border-ink bg-mint p-6">
            <div class="grid gap-4 md:grid-cols-[1fr_auto] md:items-center">
              <div><h3 class="font-display text-3xl font-bold leading-none sm:text-4xl">Review-uri de la clienți care au cumpărat bilete.</h3><p class="mt-2 text-ink-soft">După participare, clienții pot evalua activitatea, organizarea și raportul calitate-preț.</p></div>
              <a href="/cont/recenzii" class="rounded-full bg-ink px-5 py-3 text-center font-bold text-paper transition hover:bg-vermilion">Scrie review</a>
            </div>
          </div>
        </div>
      </div>
    </div>
  </section>

  <!-- ATRACTII ASOCIATE (F4) -->
  <?php if (! empty($activityAttractions)): ?>
  <section class="bg-paper border-b-2 border-ink">
    <div class="mx-auto max-w-[1500px] px-4 py-12 sm:px-6 lg:py-16">
      <div class="flex flex-wrap items-end justify-between gap-4">
        <div>
          <p class="font-mono text-xs tracking-[.18em] text-vermilion">ATRACȚII</p>
          <h2 class="mt-2 font-display text-4xl font-bold leading-none sm:text-5xl">Atracții pe care le descoperi</h2>
        </div>
      </div>
      <div class="mt-8 grid gap-5 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4">
        <?php foreach ($activityAttractions as $at): ?>
          <a href="/atractie/<?= htmlspecialchars($at['slug'] ?? '', ENT_QUOTES) ?>" class="group overflow-hidden rounded-[1.5rem] border-2 border-ink bg-paper shadow-ticket transition hover:-translate-y-1">
            <div class="relative h-40 overflow-hidden bg-ink">
              <?php if (! empty($at['cover_image_url'])): ?>
                <img src="<?= htmlspecialchars($at['cover_image_url'], ENT_QUOTES) ?>" alt="<?= htmlspecialchars($at['name'] ?? '', ENT_QUOTES) ?>" class="h-full w-full object-cover transition duration-500 group-hover:scale-105" loading="lazy">
              <?php else: ?>
                <div class="grid h-full place-items-center bg-gradient-to-br from-forest via-sky to-ink text-paper"><span class="px-3 text-center font-display text-lg font-bold"><?= htmlspecialchars(mb_substr($at['name'] ?? '', 0, 22)) ?></span></div>
              <?php endif; ?>
              <?php if (! empty($at['type'])): ?><span class="absolute left-3 top-3 rounded-full bg-paper px-3 py-1 text-xs font-bold text-ink"><?= htmlspecialchars($at['type']) ?></span><?php endif; ?>
            </div>
            <div class="p-4">
              <p class="font-display text-xl font-bold leading-tight line-clamp-2 group-hover:text-vermilion"><?= htmlspecialchars($at['name'] ?? '') ?></p>
              <p class="mt-2 text-sm font-bold text-vermilion">Vezi atracția →</p>
            </div>
          </a>
        <?php endforeach; ?>
      </div>
    </div>
  </section>
  <?php endif; ?>

  <!-- RECOMMENDATIONS (GYG-style scrollable rails) -->
  <?php foreach ($rails as $idx => $rail): if (empty($rail['cards'])) continue; ?>
  <?php $dark = ($idx === 0); ?>
  <section class="<?= $dark ? 'bg-ink text-paper' : ($idx % 2 ? 'bg-paper-2/60' : 'bg-paper') ?> border-b-2 border-ink">
    <div class="mx-auto max-w-[1500px] px-4 py-12 sm:px-6 lg:py-16">
      <div class="flex flex-wrap items-end justify-between gap-4">
        <div>
          <h2 class="mt-2 font-display text-4xl font-bold leading-none sm:text-5xl"><?= htmlspecialchars($rail['title']) ?></h2>
        </div>
        <?php if ($citySlug): ?><a href="/<?= htmlspecialchars($citySlug) ?>" class="rounded-full <?= $dark ? 'bg-paper text-ink hover:bg-vermilion hover:text-paper' : 'border-2 border-ink hover:bg-ink hover:text-paper' ?> px-5 py-2.5 text-sm font-bold transition">Vezi tot</a><?php endif; ?>
      </div>
      <div class="no-bar mt-8 flex snap-x gap-5 overflow-x-auto pb-2">
        <?php foreach (array_slice($rail['cards'], 0, 10) as $c): ?>
          <a href="<?= htmlspecialchars($cardUrl($c)) ?>" class="group w-[280px] shrink-0 snap-start sm:w-[300px]">
            <div class="overflow-hidden rounded-[1.5rem] border-2 border-ink bg-paper text-ink shadow-ticket transition group-hover:-translate-y-1">
              <div class="relative h-44 overflow-hidden bg-ink">
                <?php if (! empty($c['cover_image_url'])): ?>
                  <img src="<?= htmlspecialchars($c['cover_image_url']) ?>" alt="<?= htmlspecialchars($c['title'] ?? '') ?>" class="h-full w-full object-cover transition duration-500 group-hover:scale-105" loading="lazy">
                <?php else: ?>
                  <div class="grid h-full place-items-center bg-gradient-to-br from-vermilion via-ochre to-forest text-paper"><span class="px-3 text-center font-display text-xl font-bold"><?= htmlspecialchars(mb_substr($c['title'] ?? '', 0, 20)) ?></span></div>
                <?php endif; ?>
                <?php if (! empty($c['category']['name'])): ?><span class="absolute left-3 top-3 rounded-full bg-paper px-3 py-1 text-xs font-bold text-ink"><?= htmlspecialchars($c['category']['name']) ?></span><?php endif; ?>
                <?php if (! empty($rail['show_distance']) && isset($c['distance_km'])): ?><span class="absolute right-3 top-3 rounded-full bg-ink/90 px-3 py-1 text-xs font-bold text-paper"><?= htmlspecialchars(number_format((float) $c['distance_km'], ($c['distance_km'] < 10 ? 1 : 0), ',', '.')) ?> km</span><?php endif; ?>
              </div>
              <div class="p-4">
                <p class="font-display text-xl font-bold leading-tight line-clamp-2 group-hover:text-vermilion"><?= htmlspecialchars($c['title'] ?? '') ?></p>
                <p class="mt-2 text-sm text-ink-soft"><?= htmlspecialchars(trim(($c['city']['name'] ?? '') . (! empty($c['duration_minutes']) ? ' · ' . $durationLabel((int) $c['duration_minutes']) : ''), ' ·')) ?></p>
                <div class="mt-3 flex items-end justify-between gap-2">
                  <p class="font-bold"><?= ! empty($c['cheapest_price_cents']) ? '<span class="text-ink-soft text-xs font-normal">de la</span> ' . $pricedFromCents($c['cheapest_price_cents']) : '' ?></p>
                  <span class="text-lg">→</span>
                </div>
              </div>
            </div>
          </a>
        <?php endforeach; ?>
      </div>
    </div>
  </section>
  <?php endforeach; ?>

  <!-- Gallery modal -->
  <div x-show="galleryOpen" x-cloak class="fixed inset-0 z-[90] bg-ink/95 p-4 sm:p-8 text-paper" @keydown.escape.window="galleryOpen=false">
    <div class="mx-auto flex h-full max-w-6xl flex-col">
      <div class="mb-4 flex items-center justify-between gap-4"><p class="font-mono text-xs tracking-[.2em] text-paper/50">GALERIE</p><button @click="galleryOpen=false" class="grid h-11 w-11 place-items-center rounded-full bg-paper text-2xl font-bold text-ink">×</button></div>
      <div class="relative grid min-h-0 flex-1 place-items-center">
        <img :src="gallery[galleryIndex]?.src" :alt="gallery[galleryIndex]?.alt" class="max-h-full max-w-full rounded-[2rem] object-contain">
        <button @click="prevImage()" class="absolute left-4 top-1/2 -translate-y-1/2 grid h-12 w-12 place-items-center rounded-full bg-paper font-bold text-ink">←</button>
        <button @click="nextImage()" class="absolute right-4 top-1/2 -translate-y-1/2 grid h-12 w-12 place-items-center rounded-full bg-paper font-bold text-ink">→</button>
      </div>
    </div>
  </div>

</main>

<script>
function activityPage(bootstrap) {
    const today = new Date();
    const todayStr = today.toISOString().substring(0, 10);
    const maxDate = new Date(today);
    maxDate.setDate(maxDate.getDate() + (bootstrap.window.max_advance_days || 60));

    return {
        activityId: bootstrap.activity_id,
        slug: bootstrap.slug,
        title: bootstrap.title,
        coverImage: bootstrap.cover_image,
        venueName: bootstrap.venue_name,
        venueCity: bootstrap.venue_city,
        organizerId: bootstrap.organizer_id,
        durationMinutes: bootstrap.duration_minutes,
        commissionRate: bootstrap.commission_rate || 0,
        commissionMode: bootstrap.commission_mode || 'included',
        earnPercentage: bootstrap.earn_percentage || 5,
        pointValueCents: bootstrap.point_value_cents || 1,
        variants: bootstrap.variants,
        window: bootstrap.window,
        gallery: bootstrap.gallery || [],

        selectedDate: todayStr,
        selectedSlot: null,
        slots: [],
        loadingSlots: false,
        quantities: {},

        calendarOpen: false,
        calendarMonth: today.getMonth(),
        calendarYear: today.getFullYear(),
        availableDates: [],

        galleryOpen: false,
        galleryIndex: 0,

        minDate: todayStr,
        maxDate: maxDate.toISOString().substring(0, 10),

        init() {
            this.loadSlots();
            this.loadAvailableDates();
            this.$watch('galleryOpen', v => document.body.style.overflow = v ? 'hidden' : '');
        },

        async loadSlots() {
            this.selectedSlot = null;
            this.slots = [];
            this.loadingSlots = true;
            try {
                const url = `/api/proxy.php?action=activity.slots&slug=${encodeURIComponent(this.slug)}&date=${encodeURIComponent(this.selectedDate)}`;
                const r = await fetch(url);
                const j = await r.json();
                this.slots = (j?.data?.slots) || [];
            } catch (e) { this.slots = []; }
            finally { this.loadingSlots = false; }
        },

        async loadAvailableDates() {
            try {
                const url = `/api/proxy.php?action=activity.available-dates&slug=${encodeURIComponent(this.slug)}`;
                const r = await fetch(url);
                const j = await r.json();
                this.availableDates = (j?.data?.dates) || (j?.data?.available_dates) || [];
            } catch (e) { this.availableDates = []; }
        },

        selectDate(value) {
            this.selectedDate = value;
            const p = value.split('-').map(Number);
            this.calendarYear = p[0]; this.calendarMonth = p[1] - 1;
            this.loadSlots();
        },
        calendarTitle() {
            return new Intl.DateTimeFormat('ro-RO', { month: 'long', year: 'numeric' }).format(new Date(this.calendarYear, this.calendarMonth, 1));
        },
        calendarDays() {
            const first = new Date(this.calendarYear, this.calendarMonth, 1);
            const offset = (first.getDay() + 6) % 7;
            const start = new Date(this.calendarYear, this.calendarMonth, 1 - offset);
            const hasList = this.availableDates.length > 0;
            return Array.from({ length: 42 }, (_, i) => {
                const d = new Date(start); d.setDate(start.getDate() + i);
                const value = d.toISOString().substring(0, 10);
                const inRange = value >= this.minDate && value <= this.maxDate;
                const selectable = inRange && (hasList ? this.availableDates.includes(value) : true);
                return { key: value, value, label: d.getDate(), inMonth: d.getMonth() === this.calendarMonth, selectable };
            });
        },
        prevMonth() { if (this.calendarMonth === 0) { this.calendarMonth = 11; this.calendarYear--; } else this.calendarMonth--; },
        nextMonth() { if (this.calendarMonth === 11) { this.calendarMonth = 0; this.calendarYear++; } else this.calendarMonth++; },

        increment(variantId) {
            const variant = this.variants.find(v => v.id === variantId);
            if (! variant) return;
            const current = this.quantities[variantId] || 0;
            const max = Math.max(1, variant.max_per_order || 10);
            const remainingSeats = this.currentSlotRemaining - this.totalSeatsUsed + (current * (variant.capacity_share || 1));
            const maxByCapacity = Math.floor(remainingSeats / (variant.capacity_share || 1));
            this.quantities[variantId] = Math.min(current + 1, max, Math.max(0, maxByCapacity));
        },
        decrement(variantId) {
            const current = this.quantities[variantId] || 0;
            this.quantities[variantId] = Math.max(0, current - 1);
        },

        get currentSlot() { return this.slots.find(s => s.start_time === this.selectedSlot); },
        get currentSlotRemaining() { return this.currentSlot ? (this.currentSlot.capacity_remaining || 0) : 0; },
        get totalSeatsUsed() { return this.variants.reduce((acc, v) => acc + ((this.quantities[v.id] || 0) * (v.capacity_share || 1)), 0); },
        get totalCents() { return this.variants.reduce((acc, v) => acc + ((this.quantities[v.id] || 0) * (v.price_cents || 0)), 0); },

        get participantsLabel() {
            const n = this.totalSeatsUsed;
            if (! n) return 'Selectează biletele';
            const min = this.window.min_participants || 1;
            const max = this.window.max_participants || 99;
            if (n < min) return `Minim ${min} participanți`;
            if (n > max) return `Maxim ${max} participanți`;
            return `${n} ${n === 1 ? 'participant' : 'participanți'}`;
        },
        get canSubmit() {
            const min = this.window.min_participants || 1;
            const max = this.window.max_participants || 99;
            return this.selectedSlot && this.totalSeatsUsed >= min && this.totalSeatsUsed <= max && this.totalSeatsUsed <= this.currentSlotRemaining && this.totalCents > 0;
        },

        money(cents) {
            return new Intl.NumberFormat('ro-RO', { style: 'currency', currency: 'RON', maximumFractionDigits: 0 }).format((cents || 0) / 100);
        },
        fmtTime(t) { return (t || '').toString().slice(0, 5); },
        // Estimated loyalty points on the tickets subtotal. earn_percentage = %
        // of value awarded; point_value_cents = value of 1 point in cents.
        pointsEstimate() {
            const pv = this.pointValueCents || 1;
            return Math.max(0, Math.floor((this.totalCents * (this.earnPercentage || 0) / 100) / pv));
        },

        openGallery(index) { if (this.gallery.length === 0) return; this.galleryIndex = index; this.galleryOpen = true; },
        nextImage() { this.galleryIndex = (this.galleryIndex + 1) % this.gallery.length; },
        prevImage() { this.galleryIndex = (this.galleryIndex - 1 + this.gallery.length) % this.gallery.length; },

        submitBooking(dest) {
            if (! this.canSubmit) return;
            if (typeof BileteOnlineCart === 'undefined' || typeof BileteOnlineCart.addActivityItem !== 'function') {
                alert('Coșul nu este încărcat. Reîncarcă pagina și încearcă din nou.');
                return;
            }
            const slot = this.currentSlot;
            if (! slot) return;

            const activityData = {
                id: this.activityId, slug: this.slug, title: this.title, image: this.coverImage,
                venue: this.venueName, city: this.venueCity, organizer_id: this.organizerId,
                duration_minutes: this.durationMinutes,
                commission_rate: this.commissionRate, commission_mode: this.commissionMode,
            };

            let pushed = 0;
            for (const variant of this.variants) {
                const qty = this.quantities[variant.id] || 0;
                if (qty <= 0) continue;
                const result = BileteOnlineCart.addActivityItem(
                    activityData,
                    { id: variant.id, name: variant.name, price_cents: variant.price_cents, capacity_share: variant.capacity_share || 1 },
                    { date: this.selectedDate, start_time: slot.start_time, end_time: slot.end_time },
                    qty
                );
                if (result) pushed++;
            }
            if (pushed === 0) { alert('Nu am putut adăuga în coș. Verifică data și ora alese, apoi încearcă din nou.'); return; }
            window.location.href = (dest === 'checkout') ? '/finalizare' : '/cos';
        },
    };
}
</script>

<?php include __DIR__ . '/includes/footer.php'; ?>
