<?php
/**
 * viaqui.com — single activity page (v2 design).
 *
 * Wired to REAL data from /activities/{slug}. The booking → cart pipeline is
 * unchanged (date → slots → variants → BileteOnlineCart.addActivityItem → /cart
 * or /checkout); the booking widget lives in assets/v2/js/activity.js and the
 * page loads config.js + cart.js from the previous stack for the cart.
 * Reviews, operator, recommendations and "suitable for" render from real
 * data and degrade gracefully when empty.
 *
 * URL: /{city-slug}/{activity-slug} (city is cosmetic; lookup is by slug).
 * /activity/{slug} (and the older /activitate/{slug}) 301-redirect here.
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

// Activities module: each experience has one page, /experience/{slug} (access tickets and packages send the
// visitor on to their location). Only when the module sells the product online; otherwise this page stays.
$amProduct = api_cached("am_product_{$slug}", fn () => api_get('/activities-module/products/' . $slug), 60);
if (!empty($amProduct['success']) && ($amProduct['data']['slug'] ?? '') === $slug && !empty($amProduct['data']['variants'])) {
    header('Location: /experience/' . rawurlencode($slug), true, 301);
    exit;
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
// If reached via /activity/{slug} (no city param) or via the
// wrong city prefix, 301-redirect to the canonical city-prefixed URL. When
// the activity has no city we stay on /activity/{slug} (no redirect).
// ------------------------------------------------------------
$citySlug = $activity['city']['slug'] ?? '';
$reqCity  = trim((string) ($_GET['city'] ?? ''));
if ($citySlug !== '' && $reqCity !== $citySlug) {
    header('Location: /' . rawurlencode($citySlug) . '/' . rawurlencode($activity['slug']), true, 301);
    exit;
}

require_once __DIR__ . '/includes/nav-helpers.php';
require_once __DIR__ . '/includes/v2/helpers.php';
require_once __DIR__ . '/includes/v2/nav.php';

// ============================================================
// HELPERS
// ============================================================
$cityName = $activity['city']['name'] ?? ($activity['venue']['city'] ?? null);
$categoryName = $activity['category']['name'] ?? null;
$categorySlug = $activity['category']['slug'] ?? '';
$organizer = $activity['organizer'] ?? null;
$heroImage = $activity['hero_image_url'] ?? $activity['cover_image_url'] ?? null;
$reviews = $activity['reviews'] ?? ['average' => 0, 'count' => 0, 'distribution' => [], 'detailed_averages' => [], 'recommend_pct' => 0, 'items' => []];

// The currency the activity is sold (and charged) in: its own when the API sends it, else its variants', else the
// site's. Every price of the activity on this page is written in it, like the booking widget (activity.js).
$actCurrency = strtoupper((string) ($activity['currency'] ?? (array_values(array_filter(array_column((array) ($activity['variants'] ?? []), 'currency')))[0] ?? ''))) ?: SITE_CURRENCY;
$pricedFromCents = function (?int $cents, $currency = null) use ($actCurrency): string {
    if (! $cents || $cents <= 0) return '—';
    return v2_money_in($cents / 100, is_string($currency) && $currency !== '' ? strtoupper($currency) : $actCurrency);
};
$durationLabel = function (?int $min): string {
    if (! $min) return '';
    if ($min < 60) return $min . ' min';
    $h = intdiv($min, 60); $m = $min % 60;
    return $m ? "{$h}h {$m}m" : "{$h}h";
};
$ratingLabel = fn ($v): string => number_format((float) $v, 1);
$langLabels = ['ro' => v2_t('Romanian'), 'en' => v2_t('English'), 'de' => v2_t('German'), 'fr' => v2_t('French'), 'it' => v2_t('Italian'), 'es' => v2_t('Spanish'), 'hu' => v2_t('Hungarian')];
$difficultyLabels = ['easy' => v2_t('Easy'), 'medium' => v2_t('Medium'), 'hard' => v2_t('Hard'), 'expert' => v2_t('Expert')];

$langsOffered = array_values(array_map(fn ($l) => $langLabels[$l] ?? ucfirst($l), (array) ($activity['languages_offered'] ?? [])));
$flags = $activity['flags'] ?? [];

// "Suitable for": real traveler types with flag-derived fallback.
$suitableFor = [];
foreach ((array) ($activity['traveler_types'] ?? []) as $tt) {
    if (! empty($tt['name'])) $suitableFor[] = $tt['name'];
}
if (empty($suitableFor)) {
    if (! empty($flags['is_kid_friendly'])) $suitableFor[] = v2_t('families');
    if (! empty($flags['is_accessible'])) $suitableFor[] = v2_t('accessible');
    if (! empty($flags['is_outdoor'])) $suitableFor[] = v2_t('outdoor');
    if (! empty($flags['is_indoor'])) $suitableFor[] = v2_t('indoor');
    $suitableFor[] = v2_t('couples');
    $suitableFor[] = v2_t('groups');
}
$suitableFor = array_values(array_unique($suitableFor));

// Interests — thematic tags. Associated attractions.
$interestTags = [];
foreach ((array) ($activity['interests'] ?? []) as $it) {
    if (! empty($it['name'])) $interestTags[] = trim((($it['icon'] ?? '') ? $it['icon'] . ' ' : '') . $it['name']);
}
$activityAttractions = is_array($activity['attractions'] ?? null) ? $activity['attractions'] : [];

// Hero badges.
$heroBadges = [];
if (! empty($flags['is_featured'])) $heroBadges[] = [v2_t('Recommended'), 'is-feat'];
if (! empty($activity['cancellation_policy'])) $heroBadges[] = [v2_t('Free cancellation'), 'is-ok'];
if ($categoryName) $heroBadges[] = [$categoryName, ''];
$heroBadges[] = [v2_t('Digital tickets'), ''];

// ============================================================
// PAGE METADATA
// ============================================================
$pageTitleRaw    = ($activity['seo']['title'] ?? null) ?: ($activity['title'] . ' | ' . SITE_NAME);
$pageDescription = $activity['seo']['description'] ?? $activity['short_description'] ?? v2_t('{title} on {site}', ['title' => $activity['title'], 'site' => SITE_NAME]);
$canonicalUrl    = $citySlug ? (SITE_URL . '/' . $citySlug . '/' . $activity['slug']) : (SITE_URL . '/activity/' . $activity['slug']);
if ($heroImage) $ogImage = v2_media_url($heroImage);

$breadcrumbs = [['name' => v2_t('Home'), 'url' => SITE_URL . '/']];
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
        'priceCurrency' => $actCurrency,
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
$structuredData[] = [
    '@context' => 'https://schema.org',
    '@type' => 'BreadcrumbList',
    'itemListElement' => array_map(fn ($bc, $i) => [
        '@type' => 'ListItem',
        'position' => $i + 1,
        'name' => $bc['name'],
        'item' => $bc['url'],
    ], $breadcrumbs, array_keys($breadcrumbs)),
];

// ============================================================
// BOOKING BOOTSTRAP — real booking pipeline (unchanged contract)
// ============================================================
$bookingWindow = $activity['booking_window'] ?? ['lead_time_hours' => 2, 'max_advance_days' => 60, 'min_participants' => 1, 'max_participants' => 10];
$tz = new DateTimeZone('Europe/Bucharest');
$todayLocal = new DateTimeImmutable('today', $tz);
$gallery = array_values(array_filter(array_map(fn ($url) => $url ? ['src' => v2_media_url($url), 'alt' => $activity['title']] : null, $activity['gallery'] ?? [])));
if (! $gallery && $heroImage) {
    $gallery[] = ['src' => v2_media_url($heroImage), 'alt' => $activity['title']];
}
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
        'currency'       => $v['currency'] ?? null, // activity.js prices each line in it
        'capacity_share' => (int) $v['capacity_share'],
        'min_per_order'  => (int) $v['min_per_order'],
        'max_per_order'  => (int) $v['max_per_order'],
        'min_age'        => $v['min_age'],
        'max_age'        => $v['max_age'],
    ], $activity['variants'] ?? []),
    'window'  => $bookingWindow,
    'currency' => $actCurrency, // what activity.js falls back to when a variant names none
    // Calendar bounds in the time zone below (the calendar builds local dates, never UTC ones).
    'today'   => $todayLocal->format('Y-m-d'),
    'max_date' => $todayLocal->modify('+' . (int) ($bookingWindow['max_advance_days'] ?? 60) . ' days')->format('Y-m-d'),
    'gallery' => $gallery,
    // Loyalty points: activity.js estimates them with the programme's real rules (/checkout/features via cart.js)
    // and shows nothing when the marketplace runs no points programme (this used to promise a fixed 5%).
];

// Recommendation rails (only when ≥1 card each).
$rails = [];
if (! empty($activity['related'])) {
    $rails[] = ['title' => v2_t('You might also like'), 'cards' => $activity['related']];
}
$recs = $activity['recommendations'] ?? [];
if (! empty($recs['same_organizer'])) {
    $rails[] = ['title' => ($organizer['name'] ?? null) ? v2_t('More experiences from {operator}', ['operator' => $organizer['name']]) : v2_t('More experiences from this operator'), 'cards' => $recs['same_organizer']];
}
if (! empty($recs['same_city_same_cat'])) {
    $rails[] = ['title' => ($cityName && $categoryName) ? v2_t('{category} in {city}', ['category' => $categoryName, 'city' => $cityName]) : v2_t('Similar activities'), 'cards' => $recs['same_city_same_cat']];
}
if (! empty($recs['same_city'])) {
    $rails[] = ['title' => $cityName ? v2_t('More experiences in {city}', ['city' => $cityName]) : v2_t('More experiences'), 'cards' => $recs['same_city']];
}
// Proximity rail: real Haversine distance from the API (distance_km on each card).
if (! empty($activity['nearby'])) {
    $rails[] = ['title' => v2_t('Activities nearby'), 'cards' => $activity['nearby'], 'show_distance' => true];
}

// Extra discovery rails built from a city activities pool. Same pool,
// different framing/order (Top / Experiences). Empty rails are skipped.
$cityLabel = $cityName ?: v2_t('your city');
$cityPool = [];
if ($citySlug) {
    $cpResp = api_cached("city_pool_{$citySlug}", fn () => api_get('/activities', ['city' => $citySlug, 'per_page' => 16]), 300);
    $cpRaw = $cpResp['data']['items'] ?? $cpResp['data']['data'] ?? (is_array($cpResp['data'] ?? null) ? $cpResp['data'] : []);
    foreach ((is_array($cpRaw) ? $cpRaw : []) as $ca) {
        if ((int) ($ca['id'] ?? 0) !== (int) $activity['id']) $cityPool[] = $ca;
    }
}
if (count($cityPool) >= 1 && empty($recs['same_city'])) {
    $rails[] = ['title' => v2_t('More experiences in {city}', ['city' => $cityLabel]), 'cards' => array_slice($cityPool, 0, 8)];
}
if (count($cityPool) >= 3) {
    $top = $cityPool;
    usort($top, fn ($a, $b) => ((($b['flags']['is_featured'] ?? false) ? 1 : 0) <=> (($a['flags']['is_featured'] ?? false) ? 1 : 0)));
    $rails[] = ['title' => v2_t('Top activities in {city}', ['city' => $cityLabel]), 'cards' => array_slice($top, 0, 8)];
}
if (count($cityPool) >= 3) {
    $rails[] = ['title' => v2_t('Experiences to discover in {city}', ['city' => $cityLabel]), 'cards' => array_slice(array_reverse($cityPool), 0, 8)];
}

// Card URL helper (city-prefixed when the card carries a city).
$cardUrl = function (array $c): string {
    $cs = $c['city']['slug'] ?? '';
    return $cs ? ('/' . $cs . '/' . ($c['slug'] ?? '')) : ('/activity/' . ($c['slug'] ?? ''));
};

// Facts (max 4).
$facts = [];
if (! empty($activity['duration_minutes'])) $facts[] = ['clock', $durationLabel((int) $activity['duration_minutes']), v2_t('duration')];
if ($langsOffered) $facts[] = ['headset', implode(' / ', array_slice($langsOffered, 0, 3)), v2_t('languages available')];
if (! empty($activity['cancellation_policy'])) $facts[] = ['check-circle', v2_t('Free cancellation'), v2_t('see the conditions')];
if (! empty($activity['meeting_point'])) $facts[] = ['map-pin', v2_t('Meeting point'), v2_t('details below')];
if (! empty($activity['difficulty_level'])) $facts[] = ['star', $difficultyLabels[$activity['difficulty_level']] ?? ucfirst($activity['difficulty_level']), v2_t('difficulty')];
$facts = array_slice($facts, 0, 4);

$fromPrice = $pricedFromCents($activity['cheapest_price_cents'] ?? $lowPriceCents);
$lei = fn (int $cents, $currency = null): string => v2_money_in($cents / 100, is_string($currency) && $currency !== '' ? strtoupper($currency) : $actCurrency); // a price of this activity (the name is from the Romanian site)
$hasReviews = ($reviews['count'] ?? 0) > 0;

$v2Styles = ['activity.css'];
$v2Scripts = ['activity.js'];
$v2LegacyScripts = ['assets/js/config.js', 'assets/js/cart.js'];
$v2BodyClass = 'has-abar';
$v2HeadExtra = '<script>window.BILETEONLINE = ' . json_encode([
    'siteName' => SITE_NAME,
    'siteUrl' => SITE_URL,
    'apiUrl' => '/api/proxy.php',
    'storageUrl' => STORAGE_URL,
    'env' => API_ENV,
    'locale' => SITE_LOCALE,
    'currency' => SITE_CURRENCY,
    'supportEmail' => defined('SUPPORT_EMAIL') ? SUPPORT_EMAIL : '',
], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG) . ';</script>';
$v2ClientData = ['booking' => $bookingBootstrap];

$v2HeadExtra .= v2_track_organizer($organizer['id'] ?? null);
include __DIR__ . '/includes/v2/head.php';
include __DIR__ . '/includes/v2/header.php';
?>
<main id="main" class="page-main" tabindex="-1">

  <!-- ============================== HEAD ============================== -->
  <section class="ah" aria-labelledby="ah-h">
    <div class="wrap">
      <nav class="crumbs" aria-label="<?= v2_te('Breadcrumb') ?>">
        <?php foreach ($breadcrumbs as $i => $b): ?>
          <?php if ($i > 0): ?><span aria-hidden="true">/</span><?php endif; ?>
          <?php if ($i < count($breadcrumbs) - 1): ?><a href="<?= v2_e(substr($b['url'], strlen(SITE_URL)) ?: '/') ?>"><?= v2_e($b['name']) ?></a><?php else: ?><span aria-current="page"><?= v2_e($b['name']) ?></span><?php endif; ?>
        <?php endforeach; ?>
      </nav>
      <ul class="ah-badges">
        <?php foreach ($heroBadges as [$t, $cls]): ?><li class="<?= $cls ?>"><?= v2_e($t) ?></li><?php endforeach; ?>
      </ul>
      <h1 class="ah-h" id="ah-h"><?= v2_e($activity['title']) ?></h1>
      <div class="ah-meta">
        <?php if ($hasReviews): ?>
          <a class="ah-rating" href="#review-uri"><?= v2_ic('star') ?><b><?= $ratingLabel($reviews['average']) ?></b><span>(<?= v2_e(v2_num((int) $reviews['count'], 'review', 'reviews')) ?>)</span></a>
        <?php endif; ?>
        <?php if ($organizer): ?>
          <a href="/operator/<?= v2_e($organizer['slug'] ?? '') ?>"><?= v2_e($organizer['name'] ?? v2_t('Operator')) ?></a>
        <?php endif; ?>
        <span><?= v2_te('ID: {id}', ['id' => 'BO-' . (int) $activity['id']]) ?></span>
      </div>
      <?php if (! empty($activity['short_description'])): ?>
        <p class="ah-lead"><?= v2_e($activity['short_description']) ?></p>
      <?php endif; ?>

      <!-- Gallery (graceful when empty) -->
      <?php if ($gallery): ?>
      <div class="agal<?= count($gallery) > 1 ? ' has-thumbs' : '' ?>">
        <button class="agal-main" type="button" data-gallery="0" aria-haspopup="dialog" aria-controls="lb">
          <img src="<?= v2_e($gallery[0]['src']) ?>" alt="<?= v2_e($gallery[0]['alt']) ?>" fetchpriority="high" decoding="async">
          <span class="agal-cta"><?= v2_ic('magnifying-glass') ?><?= v2_te('See the gallery') ?></span>
        </button>
        <?php if (count($gallery) > 1): ?>
        <div class="agal-thumbs">
          <?php foreach (array_slice($gallery, 1, 4) as $gi => $g): ?>
          <button type="button" data-gallery="<?= $gi + 1 ?>" aria-haspopup="dialog" aria-controls="lb"><img src="<?= v2_e($g['src']) ?>" alt="" loading="lazy" decoding="async"><span class="sr"><?= v2_te('Photo {n}', ['n' => $gi + 2]) ?></span></button>
          <?php endforeach; ?>
        </div>
        <?php endif; ?>
      </div>
      <?php else: ?>
      <div class="agal-empty">
        <?= v2_fallback($activity['title']) ?>
        <div class="agal-empty-text">
          <p><?= v2_e(mb_substr($activity['title'], 0, 22)) ?></p>
          <span><?= v2_te('Viaqui · activity') ?></span>
        </div>
      </div>
      <?php endif; ?>
    </div>
  </section>

  <!-- ============================== DETAILS + BOOKING ============================== -->
  <div class="wrap alay">
    <div class="alay-main">
      <?php if ($facts): ?>
      <ul class="afacts">
        <?php foreach ($facts as [$icon, $value, $label]): ?>
        <li><span class="afact-ic"><?= v2_ic($icon) ?></span><b><?= v2_e($value) ?></b><small><?= v2_e($label) ?></small></li>
        <?php endforeach; ?>
      </ul>
      <?php endif; ?>

      <nav class="anav" aria-label="<?= v2_te('Sections of this activity') ?>">
        <a href="#descriere"><?= v2_te('Description') ?></a>
        <?php if ($activity['included_items'] || $activity['not_included']): ?><a href="#include"><?= v2_te('What is included') ?></a><?php endif; ?>
        <a href="#review-uri"><?= v2_te('Reviews') ?></a>
        <?php if (! empty($activity['faqs'])): ?><a href="#faq"><?= v2_te('FAQ') ?></a><?php endif; ?>
        <a class="anav-book" href="#rezervare"><?= v2_te('Book · from {price}', ['price' => $fromPrice]) ?></a>
      </nav>

      <!-- Description -->
      <section class="acard adesc" id="descriere" aria-labelledby="descriere-h">
        <h2 id="descriere-h"><?= v2_e($activity['seo']['body_title'] ?? v2_t('About {title}', ['title' => $activity['title']])) ?></h2>
        <div class="adesc-grid">
          <div class="adesc-body"><?= $activity['description'] ?? '' ?></div>
          <aside class="adesc-side">
            <h3><?= v2_te('Suitable for') ?></h3>
            <ul class="atags"><?php foreach ($suitableFor as $s): ?><li><?= v2_e($s) ?></li><?php endforeach; ?></ul>
            <?php if (! empty($interestTags)): ?>
              <h3><?= v2_te('Interests') ?></h3>
              <ul class="atags is-green"><?php foreach ($interestTags as $it): ?><li><?= v2_e($it) ?></li><?php endforeach; ?></ul>
            <?php endif; ?>
            <?php if (! empty($activity['requirements'])): ?>
              <h3><?= v2_te('Requirements') ?></h3>
              <ul class="areq"><?php foreach ($activity['requirements'] as $r): ?><li><?= v2_e($r) ?></li><?php endforeach; ?></ul>
            <?php endif; ?>
          </aside>
        </div>
      </section>

      <!-- Operator -->
      <?php if ($organizer): ?>
      <section class="acard aop" aria-labelledby="aop-h">
        <span class="aop-ini" aria-hidden="true"><?= v2_e(mb_strtoupper(mb_substr($organizer['name'] ?? 'O', 0, 2))) ?></span>
        <div class="aop-text">
          <p class="kicker"><?= v2_te('Activity operator') ?></p>
          <h2 id="aop-h"><?= v2_e($organizer['name'] ?? v2_t('Operator')) ?></h2>
          <?php if ($hasReviews): ?>
          <ul class="atags"><li class="is-green"><?= v2_te('{n} rating', ['n' => $ratingLabel($reviews['average'])]) ?></li><li><?= v2_e(v2_num((int) $reviews['count'], 'review', 'reviews')) ?></li></ul>
          <?php endif; ?>
        </div>
        <a class="btn btn-ghost" href="/operator/<?= v2_e($organizer['slug'] ?? '') ?>"><?= v2_te('View operator') ?></a>
      </section>
      <?php endif; ?>

      <!-- Included / Not included -->
      <?php if (! empty($activity['included_items']) || ! empty($activity['not_included'])): ?>
      <section class="ainc" id="include" aria-label="<?= v2_te('What the price includes') ?>">
        <?php if (! empty($activity['included_items'])): ?>
        <div class="acard ainc-yes">
          <p class="kicker"><?= v2_te('Included') ?></p>
          <h2><?= v2_te('Included in the price') ?></h2>
          <ul><?php foreach ($activity['included_items'] as $it): ?><li><?= v2_ic('check') ?><span><?= v2_e($it) ?></span></li><?php endforeach; ?></ul>
        </div>
        <?php endif; ?>
        <?php if (! empty($activity['not_included'])): ?>
        <div class="acard ainc-no">
          <p class="kicker"><?= v2_te('Not included') ?></p>
          <h2><?= v2_te('Not included in the price') ?></h2>
          <ul><?php foreach ($activity['not_included'] as $it): ?><li><?= v2_ic('x') ?><span><?= v2_e($it) ?></span></li><?php endforeach; ?></ul>
        </div>
        <?php endif; ?>
      </section>
      <?php endif; ?>

      <!-- Map + meeting point -->
      <?php if (! empty($activity['venue']['lat']) && ! empty($activity['venue']['lng'])): ?>
      <?php $vLat = $activity['venue']['lat']; $vLng = $activity['venue']['lng']; $vAddr = trim(($activity['venue']['address'] ?? '') . ($cityName ? ', ' . $cityName : ''), ', '); ?>
      <section class="acard amap" aria-labelledby="amap-h">
        <div class="amap-text">
          <p class="kicker"><?= v2_te('Venue & meeting point') ?></p>
          <h2 id="amap-h"><?= v2_e($activity['venue']['name'] ?? v2_t('Meeting point')) ?></h2>
          <?php if ($vAddr): ?>
          <p class="amap-addr"><?= v2_ic('map-pin') ?><span><b><?= v2_e($vAddr) ?></b><?php if ($cityName): ?><small><?= v2_e($cityName) ?></small><?php endif; ?></span></p>
          <?php endif; ?>
          <?php if (! empty($activity['meeting_point'])): ?>
          <div class="amap-how"><b><?= v2_te('How to find us') ?></b><p><?= v2_e($activity['meeting_point']) ?></p></div>
          <?php endif; ?>
          <a class="btn btn-primary" href="https://www.google.com/maps/dir/?api=1&destination=<?= urlencode($vLat . ',' . $vLng) ?>" target="_blank" rel="noopener"><?= v2_ic('map-pin') ?><?= v2_te('Open in Google Maps') ?></a>
        </div>
        <div class="amap-frame">
          <iframe title="<?= v2_te('Map of {name}', ['name' => $activity['venue']['name'] ?? $activity['title']]) ?>" loading="lazy" referrerpolicy="no-referrer-when-downgrade" src="https://www.google.com/maps?q=<?= urlencode($vLat . ',' . $vLng) ?>&z=15&output=embed"></iframe>
        </div>
      </section>
      <?php endif; ?>

      <!-- FAQ -->
      <?php if (! empty($activity['faqs'])): ?>
      <section class="acard afaq" id="faq" aria-labelledby="faq-h">
        <p class="kicker"><?= v2_te('FAQ') ?></p>
        <h2 id="faq-h"><?= v2_te('Frequently asked questions') ?></h2>
        <?php foreach ($activity['faqs'] as $i => $faq): ?>
        <details class="qa"<?= $i === 0 ? ' open' : '' ?>><summary><?= v2_e($faq['q'] ?? '') ?><span class="pm"><?= v2_ic('plus') ?></span></summary><p><?= v2_e($faq['a'] ?? '') ?></p></details>
        <?php endforeach; ?>
      </section>
      <?php endif; ?>
    </div>

    <!-- BOOKING ASIDE (real flow) -->
    <aside class="alay-side">
      <section class="bk" id="rezervare" aria-labelledby="bk-h">
        <div class="bk-top">
          <p class="bk-kicker" id="bk-h"><?= v2_te('Quick booking') ?></p>
          <p class="bk-from"><?= v2_te('from') ?></p>
          <p class="bk-price"><?= v2_e($fromPrice) ?></p>
          <p class="bk-part" id="bk-part" aria-live="polite" hidden></p>
        </div>
        <div class="bk-body">
          <!-- Date -->
          <div class="bk-block">
            <div class="bk-row">
              <label class="bk-label" for="bk-date"><?= v2_te('Choose a date') ?></label>
              <button class="bk-link" type="button" id="bk-cal-toggle" aria-expanded="false" aria-controls="bk-cal"><?= v2_te('Choose from the calendar') ?></button>
            </div>
            <ul class="bk-days" id="bk-days" aria-label="<?= v2_te('Next available days') ?>" hidden></ul>
            <input class="bk-input" type="date" id="bk-date" min="<?= $bookingBootstrap['today'] ?>" max="<?= $bookingBootstrap['max_date'] ?>" value="<?= $bookingBootstrap['today'] ?>">
            <div class="bk-cal" id="bk-cal" hidden>
              <div class="bk-cal-head">
                <button class="icon-btn" type="button" id="bk-cal-prev" aria-label="<?= v2_te('Previous month') ?>"><?= v2_ic('arrow-left') ?></button>
                <p id="bk-cal-title" aria-live="polite"></p>
                <button class="icon-btn" type="button" id="bk-cal-next" aria-label="<?= v2_te('Next month') ?>"><?= v2_ic('arrow-right') ?></button>
              </div>
              <div class="bk-cal-dow" aria-hidden="true"><span><?= v2_te('Mo') ?></span><span><?= v2_te('Tu') ?></span><span><?= v2_te('We') ?></span><span><?= v2_te('Th') ?></span><span><?= v2_te('Fr') ?></span><span><?= v2_te('Sa') ?></span><span><?= v2_te('Su') ?></span></div>
              <div class="bk-cal-grid" id="bk-cal-grid"></div>
            </div>
          </div>

          <!-- Slots -->
          <div class="bk-block">
            <p class="bk-label"><?= v2_te('Time') ?></p>
            <p class="bk-note" id="bk-slots-loading" hidden><?= v2_te('Loading the available times…') ?></p>
            <p class="bk-note" id="bk-slots-none" hidden><?= v2_te('There are no times available on this date. Choose another day.') ?></p>
            <div class="bk-slots" id="bk-slots" role="group" aria-label="<?= v2_te('Available times') ?>"></div>
          </div>

          <!-- Variants -->
          <div class="bk-block bk-variants" id="bk-variants" hidden>
            <?php foreach ($bookingBootstrap['variants'] as $v): ?>
            <div class="bk-var">
              <div class="bk-var-text">
                <b><?= v2_e($v['name']) ?></b>
                <span><?= v2_e($lei($v['price_cents'], $v['currency'])) ?><?php if ($v['capacity_share'] > 1): ?> · <?= v2_e(v2_num((int) $v['capacity_share'], 'place', 'places')) ?><?php endif; ?></span>
              </div>
              <div class="bk-step">
                <button type="button" data-dec="<?= (int) $v['id'] ?>" aria-label="<?= v2_te('Remove one: {name}', ['name' => $v['name']]) ?>">−</button>
                <output data-qty="<?= (int) $v['id'] ?>" aria-live="polite">0</output>
                <button type="button" data-inc="<?= (int) $v['id'] ?>" aria-label="<?= v2_te('Add one: {name}', ['name' => $v['name']]) ?>">+</button>
              </div>
            </div>
            <?php endforeach; ?>
          </div>

          <!-- Breakdown -->
          <div class="bk-sum" id="bk-sum" hidden>
            <div class="bk-line"><span><?= v2_te('Tickets') ?></span><strong id="bk-sub"></strong></div>
            <div class="bk-line" id="bk-fee-row" hidden><span><?= v2_t('Estimated platform fee (<span id="bk-fee-rate"></span>%)') ?></span><strong id="bk-fee"></strong></div>
            <div class="bk-line bk-total"><span><?= v2_te('Estimated total') ?></span><strong id="bk-total"></strong></div>
            <div class="bk-line bk-pts" hidden><span><?= v2_te('Estimated bonus points') ?></span><strong id="bk-points"></strong></div>
            <p class="bk-small"><?= v2_te('Final fees and points are worked out at checkout.') ?></p>
          </div>

          <button class="btn btn-primary bk-go" type="button" id="bk-cart" disabled><?= v2_te('Add to basket') ?></button>
          <button class="btn bk-go bk-go-dark" type="button" id="bk-checkout" disabled><?= v2_te('Book now: straight to checkout') ?></button>

          <!-- Points reward card -->
          <div class="bk-reward" id="bk-reward" hidden>
            <div><b><?= v2_te('You earn points') ?></b><p><?= v2_t('You get <strong id="bk-reward-n">0</strong> points after the activity. You can use them on your next booking.') ?></p></div>
            <span id="bk-reward-big">+0</span>
          </div>

          <ul class="bk-pay" aria-label="<?= v2_te('Payment methods') ?>"><li>Apple Pay</li><li>Google Pay</li><li><?= v2_te('Card') ?></li><li>Revolut</li></ul>
          <p class="bk-small bk-center"><?= v2_te('You are not charged until you confirm at checkout.') ?></p>
        </div>
      </section>

      <!-- Benefits -->
      <section class="acard aben" aria-labelledby="aben-h">
        <p class="kicker" id="aben-h"><?= v2_te('Why book here') ?></p>
        <ul>
          <?php if (! empty($activity['cancellation_policy'])): ?><li><?= v2_ic('check-circle') ?><span><b><?= v2_te('Free cancellation') ?></b><?= v2_e($activity['cancellation_policy']) ?></span></li><?php endif; ?>
          <li><?= v2_ic('qr-code') ?><span><b><?= v2_te('Digital tickets') ?></b><?= v2_te('you get your QR code by email after confirmation') ?></span></li>
          <?php if (! empty($activity['meeting_point'])): ?><li><?= v2_ic('map-pin') ?><span><b><?= v2_te('Meeting point') ?></b><?= v2_e($activity['meeting_point']) ?></span></li><?php endif; ?>
        </ul>
      </section>

      <!-- Help -->
      <section class="acard ahelp" aria-labelledby="ahelp-h">
        <p class="kicker"><?= v2_te('Need help?') ?></p>
        <h2 id="ahelp-h"><?= v2_te('Not sure it is right for you?') ?></h2>
        <p><?= v2_te('Write to us and we will tell you whether the activity suits your group.') ?></p>
        <a class="btn btn-ghost" href="/contact?activity=<?= v2_e($activity['slug']) ?>"><?= v2_ic('headset') ?><?= v2_te('Ask support') ?></a>
      </section>
    </aside>
  </div>

  <!-- Mobile sticky price bar -->
  <div class="abar">
    <div><small><?= v2_te('from') ?></small><b><?= v2_e($fromPrice) ?></b></div>
    <a class="btn btn-primary" href="#rezervare"><?= v2_te('Choose tickets') ?></a>
  </div>

  <!-- ============================== CUSTOMER REVIEWS ============================== -->
  <section class="sec arev" id="review-uri" aria-labelledby="arev-h">
    <div class="wrap arev-grid">
      <aside class="arev-side">
        <h2 id="arev-h"><?= v2_te('What customers say') ?></h2>
        <?php if ($hasReviews): ?>
        <div class="arev-score">
          <p class="arev-avg"><?= v2_ic('star') ?><?= $ratingLabel($reviews['average']) ?></p>
          <p class="arev-count"><?= v2_e(v2_num((int) $reviews['count'], 'verified review', 'verified reviews')) ?></p>
          <?php if (! empty($reviews['detailed_averages'])): ?>
          <ul class="arev-bars">
            <?php foreach ($reviews['detailed_averages'] as $aspect => $val): ?>
            <li><span><?= v2_e(navMbUcfirst((string) $aspect)) ?></span><i style="--w:<?= max(0, min(100, ((float) $val / 5) * 100)) ?>%"></i><b><?= $ratingLabel($val) ?></b></li>
            <?php endforeach; ?>
          </ul>
          <?php endif; ?>
        </div>
        <?php else: ?>
        <div class="arev-none">
          <p><?= v2_te('No reviews yet') ?></p>
          <span><?= v2_te('Be the first to leave a review after you take part in this activity.') ?></span>
        </div>
        <?php endif; ?>
      </aside>
      <div>
        <?php if (! empty($reviews['items'])): ?>
        <ul class="arev-list">
          <?php foreach ($reviews['items'] as $rv): ?>
          <li class="arev-item">
            <div class="arev-who">
              <span class="arev-ini" aria-hidden="true"><?= v2_e($rv['initial'] ?? '?') ?></span>
              <span><b><?= v2_e($rv['name'] ?? '') ?></b><small><?= v2_e($rv['meta'] ?? '') ?></small></span>
              <span class="arev-stars"><?= (int) ($rv['rating'] ?? 5) ?><?= v2_ic('star') ?><span class="sr"> <?= v2_te('out of 5') ?></span></span>
            </div>
            <p><?= v2_e($rv['text'] ?? '') ?></p>
            <span class="arev-ok"><?= v2_ic('check') ?><?= v2_te('Verified booking') ?></span>
          </li>
          <?php endforeach; ?>
        </ul>
        <?php endif; ?>
        <div class="arev-cta">
          <div><h3><?= v2_te('Reviews from customers who bought tickets.') ?></h3><p><?= v2_te('After taking part, customers can rate the activity, the organisation and the value for money.') ?></p></div>
          <a class="btn btn-primary" href="/account/reviews"><?= v2_te('Write a review') ?></a>
        </div>
      </div>
    </div>
  </section>

  <!-- ============================== ATTRACTIONS ============================== -->
  <?php if (! empty($activityAttractions)): ?>
  <section class="sec attr" aria-labelledby="aatt-h">
    <div class="wrap">
      <div class="sec-head">
        <div><p class="kicker"><?= v2_te('Attractions') ?></p><h2 id="aatt-h"><?= v2_te('Attractions you will discover') ?></h2></div>
        <div class="rail-btns" data-for="aatt-rail">
          <button class="rail-btn" type="button" data-dir="-1" aria-label="<?= v2_te('Previous attractions') ?>"><?= v2_ic('arrow-left') ?></button>
          <button class="rail-btn" type="button" data-dir="1" aria-label="<?= v2_te('Next attractions') ?>"><?= v2_ic('arrow-right') ?></button>
        </div>
      </div>
      <ul class="rail" id="aatt-rail">
        <?php foreach ($activityAttractions as $at): ?>
        <li class="at"><a href="/attraction/<?= v2_e($at['slug'] ?? '') ?>">
          <span class="at-media"><?php $atImg = v2_media_url($at['cover_image_url'] ?? null); ?><?= $atImg ? v2_photo([$atImg, 0, 0, '']) : v2_fallback($at['name'] ?? '') ?><?php if (! empty($at['type'])): ?><span class="at-badge"><?= v2_e(is_array($at['type']) ? ($at['type']['name'] ?? '') : $at['type']) ?></span><?php endif; ?></span>
          <span class="at-name"><?= v2_e($at['name'] ?? '') ?><?= v2_ic('arrow-right') ?></span>
          <span class="at-meta"><span><?= v2_te('View attraction') ?></span></span>
        </a></li>
        <?php endforeach; ?>
      </ul>
    </div>
  </section>
  <?php endif; ?>

  <!-- ============================== RECOMMENDATIONS ============================== -->
  <?php foreach ($rails as $idx => $rail): if (empty($rail['cards'])) continue; ?>
  <section class="sec arail<?= $idx === 0 ? ' is-deep' : ($idx % 2 ? ' is-alt' : '') ?>" aria-labelledby="arail-h-<?= $idx ?>">
    <div class="wrap">
      <div class="sec-head">
        <h2 id="arail-h-<?= $idx ?>"><?= v2_e($rail['title']) ?></h2>
        <div class="sec-tools">
          <?php if ($citySlug): ?><a class="sec-link" href="/<?= v2_e($citySlug) ?>"><?= v2_te('See all') ?><?= v2_ic('arrow-right') ?></a><?php endif; ?>
          <div class="rail-btns" data-for="arail-<?= $idx ?>">
            <button class="rail-btn" type="button" data-dir="-1" aria-label="<?= v2_te('Previous') ?>"><?= v2_ic('arrow-left') ?></button>
            <button class="rail-btn" type="button" data-dir="1" aria-label="<?= v2_te('Next') ?>"><?= v2_ic('arrow-right') ?></button>
          </div>
        </div>
      </div>
      <ul class="rail arail-list" id="arail-<?= $idx ?>">
        <?php foreach (array_slice($rail['cards'], 0, 10) as $ci => $c): $cImg = v2_media_url($c['cover_image_url'] ?? null); ?>
        <li class="xp">
          <a href="<?= v2_e($cardUrl($c)) ?>">
            <span class="xp-media">
              <?= $cImg ? v2_photo([$cImg, 0, 0, '']) : v2_fallback($c['title'] ?? '', $ci) ?>
              <?php if (! empty($c['category']['name'])): ?><span class="xp-badges"><span><?= v2_e($c['category']['name']) ?></span></span><?php endif; ?>
              <?php if (! empty($rail['show_distance']) && isset($c['distance_km'])): ?><span class="xp-dist"><?= v2_e(number_format((float) $c['distance_km'], ($c['distance_km'] < 10 ? 1 : 0), '.', ',')) ?> km</span><?php endif; ?>
            </span>
            <span class="xp-body">
              <span class="xp-title"><?= v2_e($c['title'] ?? '') ?></span>
              <span class="xp-meta"><?php if (! empty($c['city']['name'])): ?><span><?= v2_ic('map-pin') ?><?= v2_e($c['city']['name']) ?></span><?php endif; ?><?php if (! empty($c['duration_minutes'])): ?><span><?= v2_ic('clock') ?><?= v2_e($durationLabel((int) $c['duration_minutes'])) ?></span><?php endif; ?></span>
              <span class="xp-foot"><span class="xp-go"><?= v2_ic('arrow-right') ?></span><?php if (! empty($c['cheapest_price_cents'])): ?><span class="xp-price"><?= v2_t('from<b>{price}</b>', ['price' => v2_e(v2_own_price_label($c['cheapest_price_cents'], $c['currency'] ?? null, $c['cheapest_price_eur_cents'] ?? null))]) ?></span><?php endif; ?></span>
            </span>
          </a>
        </li>
        <?php endforeach; ?>
      </ul>
    </div>
  </section>
  <?php endforeach; ?>

  <?php if ($gallery): ?>
  <!-- gallery lightbox -->
  <div class="lb" id="lb" role="dialog" aria-modal="true" aria-labelledby="lb-title" hidden>
    <div class="lb-top">
      <p class="lb-title" id="lb-title"><?= v2_te('Gallery') ?></p>
      <span class="lb-count" id="lb-count">1 / <?= count($gallery) ?></span>
      <button class="icon-btn" type="button" data-lb="close"><?= v2_ic('x') ?><span class="sr"><?= v2_te('Close the gallery') ?></span></button>
    </div>
    <figure class="lb-fig"><img id="lb-img" src="" alt=""></figure>
    <div class="lb-nav"<?= count($gallery) < 2 ? ' hidden' : '' ?>>
      <button class="rail-btn" type="button" data-lb="prev" aria-label="<?= v2_te('Previous photo') ?>"><?= v2_ic('arrow-left') ?></button>
      <button class="rail-btn" type="button" data-lb="next" aria-label="<?= v2_te('Next photo') ?>"><?= v2_ic('arrow-right') ?></button>
    </div>
  </div>
  <?php endif; ?>
</main>
<?php include __DIR__ . '/includes/v2/footer.php'; ?>
