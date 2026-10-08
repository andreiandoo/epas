<?php
/**
 * Experience sold through the activities module: /experience/{slug} (v2 design).
 *
 * Pure render: expects $_GET['slug']. Reads the product from `GET /activities-module/products/{slug}` and 404s
 * cleanly when the slug doesn't match. Access tickets and packages have no page of their own: their slug sends
 * the visitor to the location's tickets.
 *
 * Top to bottom: hero (location · city, title, subtitle, duration, price, photo), booking for one date (the
 * experience, plus the location's access tickets when it needs one; booking.js), details (description, what is
 * included, meeting point, conditions), the location card with the map, the rest of what is sold there, lightbox.
 */

$pageCacheTTL = 120;
require_once __DIR__ . '/includes/page-cache.php';
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/api.php';
require_once __DIR__ . '/includes/nav-helpers.php';

$slug = $_GET['slug'] ?? '';
if (!is_string($slug) || !preg_match('/^[a-z0-9][a-z0-9-]{0,190}$/', $slug)) {
    http_response_code(404);
    require __DIR__ . '/404.php';
    exit;
}

$resp = api_cached("am_product_{$slug}", fn () => api_get('/activities-module/products/' . $slug), 60);
$product = (!empty($resp['success']) && is_array($resp['data'] ?? null) && ($resp['data']['slug'] ?? '') === $slug) ? $resp['data'] : null;
if (!$product || empty($product['variants'])) {
    http_response_code(404);
    require __DIR__ . '/404.php';
    exit;
}

$xpLocation = is_array($product['location'] ?? null) ? $product['location'] : null;
if (($product['type'] ?? '') !== 'experience') {
    // Access tickets and packages are bought on the location page.
    header('Location: ' . ($xpLocation && !empty($xpLocation['slug']) ? '/venue/' . rawurlencode($xpLocation['slug']) . '#bilete' : '/'), true, 301);
    exit;
}

require_once __DIR__ . '/includes/v2/helpers.php';
require_once __DIR__ . '/includes/v2/nav.php';
require_once __DIR__ . '/includes/v2/am-labels.php';

$xpTitle     = navFlatName($product['title'] ?? '') ?: v2_t('Experience');
$xpSubtitle  = trim((string) ($product['subtitle'] ?? ''));
$xpShort     = trim((string) ($product['short_description'] ?? ''));
$xpDescHtml  = am_rich($product['description'] ?? '');
$xpLocName   = $xpLocation ? navFlatName($xpLocation['name'] ?? '') : '';
$xpLocSlug   = $xpLocation ? (string) ($xpLocation['slug'] ?? '') : '';
$xpCity      = $xpLocation && is_array($xpLocation['city'] ?? null) ? $xpLocation['city'] : [];
$xpCityName  = navFlatName($xpCity['name'] ?? '');
$xpCitySlug  = (string) ($xpCity['slug'] ?? '');
$xpLat       = $xpLocation['latitude'] ?? null;
$xpLng       = $xpLocation['longitude'] ?? null;
$xpImage     = v2_media_url($product['image'] ?? null) ?? '';
$xpGallery   = array_values(array_filter(array_map('v2_media_url', (array) ($product['gallery'] ?? []))));
$xpLocCover  = $xpLocation ? (v2_media_url($xpLocation['cover_image'] ?? null) ?? '') : '';
$xpVariants  = array_values((array) $product['variants']);
$xpMinPrice  = min(array_map(fn ($v) => (int) ($v['price_cents'] ?? 0), $xpVariants));
// The currency the experience is sold (and charged) in: the API sends it with every variant's price, sometimes on
// the product too. Every price on this page is written in it.
$xpCurrency  = strtoupper((string) (array_values(array_filter(array_column($xpVariants, 'currency')))[0] ?? $product['currency'] ?? '')) ?: SITE_CURRENCY;
$xpDurations = array_values(array_unique(array_filter(array_map(fn ($v) => (int) ($v['duration_minutes'] ?? 0), $xpVariants)))) ?: array_filter([(int) ($product['duration_minutes'] ?? 0)]);
$xpLangs     = ['ro' => v2_t('Romanian'), 'en' => v2_t('English'), 'hu' => v2_t('Hungarian'), 'de' => v2_t('German'), 'fr' => v2_t('French'), 'es' => v2_t('Spanish'), 'it' => v2_t('Italian')];
$xpLanguages = array_values(array_filter(array_map(fn ($l) => $xpLangs[$l] ?? null, (array) ($product['languages'] ?? []))));
$xpIncluded  = array_values(array_filter(array_map('strval', (array) ($product['included_items'] ?? []))));
$xpExcluded  = array_values(array_filter(array_map('strval', (array) ($product['not_included'] ?? []))));
$xpNeeds     = array_values(array_filter(array_map('strval', (array) ($product['requirements'] ?? []))));
$xpMeeting   = trim((string) ($product['meeting_point'] ?? ''));
$xpCancel    = trim((string) ($product['cancellation_policy'] ?? ''));
$xpTerms     = trim((string) ($product['usage_terms'] ?? ''));
$xpAgeMin    = $product['age_min'] ?? null;
$xpAgeMax    = $product['age_max'] ?? null;
$xpRequires  = (string) ($product['access_requirement'] ?? 'none');
$xpSiblings  = array_values(array_filter((array) ($product['at_location'] ?? []), fn ($s) => is_array($s) && !empty($s['slug'])));

$lightbox = array_values(array_unique(array_filter(array_merge($xpImage ? [$xpImage] : [], $xpGallery, $xpLocCover ? [$xpLocCover] : []))));
$heroImage = $lightbox[0] ?? '';
$mapsUrl = ($xpLat && $xpLng) ? 'https://www.google.com/maps/search/?api=1&query=' . urlencode($xpLat . ',' . $xpLng) : '';

// Access tickets next to the experience when it needs one (same date, same cart).
$xpAccess = [];
if ($xpRequires !== 'none' && $xpLocSlug !== '') {
    $locResp = api_cached("am_location_{$xpLocSlug}", fn () => api_get('/activities-module/locations/' . $xpLocSlug), 60);
    if (!empty($locResp['success']) && is_array($locResp['data']['products'] ?? null)) {
        $xpAccess = array_values(array_filter($locResp['data']['products'], fn ($p) => is_array($p)
            && ($p['type'] ?? '') === 'access' && empty($p['requires_vehicle_info']) && !empty($p['variants'])));
    }
}

$durationText = $xpDurations ? implode(' / ', array_map(fn ($m) => $m >= 60 && $m % 60 === 0 ? ($m / 60) . ' h' : $m . ' min', $xpDurations)) : '';

/* ------------------------------------------------------------------ what this page actually has
 * An experience is usually two sentences, a duration and a price. The old layout gave that a
 * full-height poster hero and a section heading over one line of text; the visitor scrolled past
 * a lot of air to reach the only thing that matters, which is the date picker. Measure the text,
 * set the lead accordingly, and collect the scattered facts into one list the page can lay out. */
// Measure what the block will actually print: half the catalogue has no description at all and
// falls back to the one-line short description, which is exactly the case that needs a lead.
$xpDescChars = mb_strlen(trim(preg_replace('/\s+/u', ' ', strip_tags($xpDescHtml !== '' ? $xpDescHtml : ($xpShort !== '' ? $xpShort : $xpTitle)))));
$xpLeadBig   = $xpDescChars > 0 && $xpDescChars < 320;   // short enough to be set as a lead, not as body copy

$xpWhere = trim($xpCityName . (!empty($xpLocation['city']['county']) ? ', ' . $xpLocation['city']['county'] : ''), ', ');

// The facts that used to be a bulleted list in a side card, as tiles that fill the width.
$xpFacts = [];
if ($durationText !== '') {
    $xpFacts[] = ['clock', v2_t('Duration'), $durationText];
}
if (($product['booking_mode'] ?? '') === 'slot') {
    $xpFacts[] = ['calendar-blank', v2_t('Booking'), v2_t('By time slot, with limited places')];
} else {
    $xpFacts[] = ['calendar-blank', v2_t('Booking'), v2_t('By day: you choose the date')];
}
if ($xpLanguages) {
    $xpFacts[] = ['globe-simple', v2_t('Languages'), implode(', ', $xpLanguages)];
}
if ($xpAgeMin || $xpAgeMax) {
    $xpFacts[] = ['users-three', v2_t('Age'), $xpAgeMin && $xpAgeMax
        ? v2_t('{min}–{max} years', ['min' => $xpAgeMin, 'max' => $xpAgeMax])
        : ($xpAgeMin ? v2_t('from {age} years', ['age' => $xpAgeMin]) : v2_t('up to {age} years', ['age' => $xpAgeMax]))];
}
if ($xpRequires !== 'none') {
    $xpFacts[] = ['ticket', v2_t('Entry'), $xpRequires === 'adult' ? v2_t('Also needs an adult entry ticket, on the same day') : v2_t('Also needs an entry ticket, on the same day')];
}
if ($xpCancel !== '') {
    $xpFacts[] = ['check-circle', v2_t('Cancellation'), $xpCancel];
}
if ($xpWhere !== '') {
    $xpFacts[] = ['map-pin', v2_t('City'), $xpWhere];
}

// How much else is sold here, counted from what came back rather than from the location's own
// counters: those are zero on plenty of locations that clearly do sell things.
$xpLocCounts = [];
$xpByType = [];
foreach ($xpSiblings as $s) {
    $xpByType[$s['type'] ?? 'access'] = ($xpByType[$s['type'] ?? 'access'] ?? 0) + 1;
}
$xpByType['experience'] = ($xpByType['experience'] ?? 0) + 1;   // this page counts too
$xpTypeNoun = [   // the noun for a count, in the visitor's language
    'experience' => fn (int $n) => v2_plural($n, 'experience', 'experiences'),
    'access' => fn (int $n) => v2_plural($n, 'entry ticket', 'entry tickets'),
    'package' => fn (int $n) => v2_plural($n, 'package', 'packages'),
];
foreach ($xpTypeNoun as $ck => $noun) {
    $cv = (int) ($xpByType[$ck] ?? 0);
    if ($cv > 0) {
        $xpLocCounts[] = [(string) $cv, $noun($cv)];
    }
}

$breadcrumbs = [['name' => v2_t('Home'), 'url' => SITE_URL . '/'], ['name' => v2_t('Experiences'), 'url' => SITE_URL . '/experiences']];
if ($xpLocName !== '' && $xpLocSlug !== '') {
    $breadcrumbs[] = ['name' => $xpLocName, 'url' => SITE_URL . '/venue/' . $xpLocSlug];
}
$breadcrumbs[] = ['name' => $xpTitle, 'url' => SITE_URL . '/experience/' . $slug];

$kicker = trim($xpLocName . ($xpCityName !== '' ? ' · ' . $xpCityName : ''), ' ·') ?: v2_t('Experience');
$pageTitleRaw = ($xpLocName !== '' ? v2_t('{title} at {place}', ['title' => $xpTitle, 'place' => $xpLocName]) : $xpTitle) . ' | Viaqui';
$pageDescription = mb_substr($xpShort !== '' ? $xpShort : trim(preg_replace('/\s+/u', ' ', strip_tags($xpDescHtml))), 0, 160)
    ?: ($xpLocName !== ''
        ? v2_t('{title} at {place}. Book online, choose your time and get your ticket by email.', ['title' => $xpTitle, 'place' => $xpLocName])
        : v2_t('{title}. Book online, choose your time and get your ticket by email.', ['title' => $xpTitle]));
$canonicalUrl = SITE_URL . '/experience/' . $slug;
$ogImage = $heroImage ?: (SITE_URL . '/assets/images/og-default.jpg');

$structuredData = [array_filter([
    '@context' => 'https://schema.org',
    '@type' => 'Product',
    'name' => $xpTitle,
    'description' => $pageDescription,
    'url' => $canonicalUrl,
    'image' => $lightbox ?: null,
    'category' => v2_t('Experience'),
    'offers' => ['@type' => 'AggregateOffer', 'lowPrice' => number_format($xpMinPrice / 100, 2, '.', ''),
        'highPrice' => number_format(max(array_map(fn ($v) => (int) ($v['price_cents'] ?? 0), $xpVariants)) / 100, 2, '.', ''),
        'offerCount' => count($xpVariants), 'priceCurrency' => $xpCurrency, 'availability' => 'https://schema.org/InStock', 'url' => $canonicalUrl . '#bilete'],
]), [
    '@context' => 'https://schema.org',
    '@type' => 'BreadcrumbList',
    'itemListElement' => array_map(fn ($bc, $i) => ['@type' => 'ListItem', 'position' => $i + 1, 'name' => $bc['name'], 'item' => $bc['url']], $breadcrumbs, array_keys($breadcrumbs)),
]];

$shape = fn ($p) => [
    'id' => $p['id'], 'slug' => $p['slug'] ?? null, 'type' => $p['type'] ?? 'access', 'title' => navFlatName($p['title'] ?? ''),
    'subtitle' => $p['subtitle'] ?? null, 'short_description' => $p['short_description'] ?? null, 'icon' => am_product_icon($p['icon'] ?? null),
    'image' => v2_media_url($p['image'] ?? null), 'booking_mode' => $p['booking_mode'] ?? 'day', 'capacity_mode' => $p['capacity_mode'] ?? null,
    'duration_minutes' => $p['duration_minutes'] ?? 0, 'unit_label' => $p['unit_label'] ?? null, 'usage_terms' => $p['usage_terms'] ?? null,
    'display_category' => $p['display_category'] ?? null, 'access_requirement' => $p['access_requirement'] ?? 'none',
    'requires_vehicle_info' => !empty($p['requires_vehicle_info']), 'included_items' => $p['included_items'] ?? [],
    'age_min' => $p['age_min'] ?? null, 'age_max' => $p['age_max'] ?? null, 'commission' => $p['commission'] ?? null,
    'currency' => $p['currency'] ?? null, // each variant carries its own `currency` too (kept whole below)
    'variants' => $p['variants'] ?? [], 'addons' => $p['addons'] ?? [], 'components' => $p['components'] ?? [],
];

$v2Styles = ['attraction.css', 'location.css', 'experience.css'];
$v2Scripts = ['hdrag.js', 'attraction.js', 'booking.js'];
$v2LegacyScripts = ['assets/js/config.js', 'assets/js/cart.js'];
$v2HeaderOverlay = true;
$v2HeadExtra = $heroImage ? '<link rel="preload" as="image" href="' . v2_e($heroImage) . '" fetchpriority="high">' : '';
$v2ClientData = [
    'gallery' => array_map(fn ($src) => ['src' => $src, 'alt' => $xpTitle], $lightbox),
    'booking' => [
        'mode' => 'product',
        'location' => $xpLocSlug !== '' ? ['slug' => $xpLocSlug, 'name' => $xpLocName, 'city' => $xpCityName ?: null, 'image' => $xpLocCover ?: null] : null,
        'product_slug' => $slug,
        'products' => array_merge([$shape($product)], array_map($shape, $xpAccess)),
        'categories' => [],
        'today' => (new DateTimeImmutable('now', new DateTimeZone('Europe/Bucharest')))->format('Y-m-d'),
        'max_days' => max(1, (int) ($product['max_advance_days'] ?? 0) ?: 90),
        'focus_product_id' => $product['id'],
        'currency' => $xpCurrency, // what booking.js falls back to when a variant or a calendar day names none
    ],
];

$xpArches = '<svg class="deco-arches" viewBox="0 0 400 400" aria-hidden="true" focusable="false"><path d="M40 400V200a160 160 0 0 1 320 0v200"/><path d="M90 400V200a110 110 0 0 1 220 0v200"/><path d="M140 400V200a60 60 0 0 1 120 0v200"/></svg>';

$v2HeadExtra .= v2_track_organizer($product['organizer_id'] ?? null);
include __DIR__ . '/includes/v2/head.php';
include __DIR__ . '/includes/v2/header.php';
?>
<main id="main" tabindex="-1">
<?= am_product_icon_sprite(array_map(fn ($x) => $x['icon'] ?? null, array_merge([$product], $xpSiblings))) ?>
  <!-- ===================== HERO ===================== -->
  <section class="th is-compact" aria-labelledby="th-h">
    <?= $xpArches ?>
    <svg class="th-line draw-clip" viewBox="0 590 3240 310" aria-hidden="true" focusable="false"><use href="#drum-g"/></svg>
    <div class="th-in">
      <div class="th-copy">
        <nav class="crumbs" aria-label="<?= v2_te('Breadcrumb') ?>">
          <?php foreach ($breadcrumbs as $i => $bc): ?>
            <?php if ($i > 0): ?><span aria-hidden="true">/</span><?php endif; ?>
            <?php if ($i < count($breadcrumbs) - 1): ?><a href="<?= v2_e(substr($bc['url'], strlen(SITE_URL)) ?: '/') ?>"><?= v2_e($bc['name']) ?></a><?php else: ?><span aria-current="page"><?= v2_e($bc['name']) ?></span><?php endif; ?>
          <?php endforeach; ?>
        </nav>
        <p class="th-kicker"><?= v2_e($kicker) ?></p>
        <h1 class="th-h" id="th-h"><?= v2_e($xpTitle) ?></h1>
        <?php if ($xpSubtitle !== '' || $xpShort !== ''): ?><p class="th-sub"><?= v2_e($xpSubtitle !== '' ? $xpSubtitle : $xpShort) ?></p><?php endif; ?>
        <ul class="th-chips">
          <?php if ($durationText !== ''): ?><li><?= v2_ic('clock') ?><?= v2_e($durationText) ?></li><?php endif; ?>
          <li class="is-price"><?= v2_ic('ticket') ?><?= v2_t('from <b>{price}</b>', ['price' => v2_e(v2_money_in($xpMinPrice / 100, $xpCurrency))]) ?></li>
          <?php if ($xpLanguages): ?><li><?= v2_ic('globe-simple') ?><?= v2_e(implode(', ', $xpLanguages)) ?></li><?php endif; ?>
        </ul>
        <div class="th-cta">
          <a class="btn btn-light" href="#bilete"><?= ($product['booking_mode'] ?? '') === 'slot' ? v2_te('Choose a date and time') : v2_te('Choose a date') ?><?= v2_ic('arrow-right') ?></a>
          <?php if ($xpLocSlug !== ''): ?><a class="btn btn-outline-light" href="/venue/<?= v2_e($xpLocSlug) ?>"><?= v2_ic('map-pin') ?><?= v2_e($xpLocName) ?></a><?php endif; ?>
        </div>
      </div>

      <div class="th-media">
        <?php if ($lightbox): ?>
        <button class="th-arch" type="button" data-gallery="0" aria-haspopup="dialog" aria-controls="lb" aria-label="<?= v2_te('Open the gallery: {name}', ['name' => $xpTitle]) ?>">
          <img src="<?= v2_e(v2_thumb($heroImage, 960, 600)) ?>" alt="<?= v2_e($xpTitle) ?>" fetchpriority="high" decoding="async">
          <?php if (count($lightbox) > 1): ?><span class="th-gal"><?= v2_ic('magnifying-glass') ?><?= v2_te('See the gallery ({n})', ['n' => count($lightbox)]) ?></span><?php endif; ?>
        </button>
        <?php else: ?>
        <div class="th-arch is-empty"><?= v2_fallback($xpTitle) ?><?php if ($xpLocName !== ''): ?><span class="th-arch-name" aria-hidden="true"><?php if ($xpCityName !== ''): ?><small><?= v2_e($xpCityName) ?></small><?php endif; ?><?= v2_e($xpLocName) ?></span><?php endif; ?></div>
        <?php endif; ?>
      </div>
    </div>
  </section>
  <div id="hdr-sentinel" aria-hidden="true"></div>

  <!-- ===================== BOOKING ===================== -->
  <section class="bkx-sec" id="bilete" aria-labelledby="bkx-h">
    <div class="wrap bkx-grid" id="bkx">
      <div>
        <div class="bkx-head">
          <div><p class="kicker"><?= v2_te('Book online') ?></p><h2 id="bkx-h"><?= $xpAccess ? v2_te('The experience and entry tickets') : (($product['booking_mode'] ?? '') === 'slot' ? v2_te('Choose a date and time') : v2_te('Choose a date')) ?></h2></div>
          <button class="bkx-link" type="button" id="bkx-cal-toggle" aria-expanded="false" aria-controls="bkx-cal"><?= v2_te('Another date') ?></button>
        </div>
        <?php if ($xpAccess): ?>
        <p class="bkx-hours"><?= $xpRequires === 'adult' ? v2_te('The experience also needs an adult entry ticket for the same day. You can book them together, below.') : v2_te('The experience also needs an entry ticket for the same day. You can book them together, below.') ?></p>
        <?php endif; ?>
        <ul class="bkx-days" id="bkx-days" aria-label="<?= v2_te('Choose a day') ?>"></ul>
        <div class="bkx-cal" id="bkx-cal" hidden>
          <div class="bkx-cal-head">
            <button class="rail-btn" type="button" id="bkx-cal-prev" aria-label="<?= v2_te('Previous month') ?>"><?= v2_ic('arrow-left') ?></button>
            <p id="bkx-cal-title" aria-live="polite"></p>
            <button class="rail-btn" type="button" id="bkx-cal-next" aria-label="<?= v2_te('Next month') ?>"><?= v2_ic('arrow-right') ?></button>
          </div>
          <div class="bkx-cal-dow" aria-hidden="true"><span><?= v2_te('Mo') ?></span><span><?= v2_te('Tu') ?></span><span><?= v2_te('We') ?></span><span><?= v2_te('Th') ?></span><span><?= v2_te('Fr') ?></span><span><?= v2_te('Sa') ?></span><span><?= v2_te('Su') ?></span></div>
          <div class="bkx-cal-grid" id="bkx-cal-grid"></div>
        </div>
        <p class="bkx-hours" id="bkx-hours" aria-live="polite"></p>
        <div class="bkx-tabs" id="bkx-tabs" role="group" aria-label="<?= v2_te('Categories') ?>" hidden></div>
        <div class="bkx-list" id="bkx-list"></div>
      </div>

      <aside class="bkx-side" aria-label="<?= v2_te('Your booking') ?>">
        <div class="bkx-sum" id="bkx-sum" hidden>
          <h3><?= v2_te('Your booking') ?></h3>
          <ul class="bkx-lines" id="bkx-lines"></ul>
          <div class="bkx-row"><span><?= v2_te('Subtotal') ?></span><strong id="bkx-sub"><?= v2_e(v2_money_in(0, $xpCurrency)) ?></strong></div>
          <div class="bkx-row" id="bkx-fee-row" hidden><span id="bkx-fee-label"><?= v2_te('Booking fee') ?></span><strong id="bkx-fee"><?= v2_e(v2_money_in(0, $xpCurrency)) ?></strong></div>
          <div class="bkx-row bkx-total"><span><?= v2_te('Total') ?></span><strong id="bkx-total"><?= v2_e(v2_money_in(0, $xpCurrency)) ?></strong></div>
          <p class="bkx-err" id="bkx-err" role="alert" hidden></p>
          <div class="bkx-cta">
            <button class="btn btn-primary" type="button" id="bkx-go" disabled><?= v2_te('Continue to payment') ?><?= v2_ic('arrow-right') ?></button>
            <button class="btn btn-ghost" type="button" id="bkx-cart" disabled><?= v2_ic('shopping-cart-simple') ?><?= v2_te('Add to basket') ?></button>
          </div>
          <p class="bkx-small"><span id="bkx-card-note" hidden><?= v2_te('The card processing fee is worked out at checkout and depends on the payment method you choose.') ?> </span><?= v2_te('Your ticket arrives by email straight after payment. Show it on your phone.') ?></p>
        </div>
        <div class="lcp-card">
          <h3><?= v2_te('Good to know') ?></h3>
          <ul class="lcp-facts">
            <?php if (($product['booking_mode'] ?? '') === 'slot'): ?><li><?= v2_ic('clock') ?><span><?= v2_te('Places are limited for each time slot: you book your time right here.') ?></span></li><?php endif; ?>
            <?php if ($xpCancel !== ''): ?><li><?= v2_ic('check-circle') ?><span><?= v2_e($xpCancel) ?></span></li><?php endif; ?>
            <li><?= v2_ic('lock-simple') ?><span><?= v2_te('Secure card payment. One basket can hold tickets from several venues.') ?></span></li>
          </ul>
        </div>
      </aside>
    </div>
    <div class="bkx-bar" id="bkx-bar" hidden>
      <div><b id="bkx-bar-total"><?= v2_e(v2_money_in(0, $xpCurrency)) ?></b><span id="bkx-bar-count"></span></div>
      <button class="btn btn-primary" type="button" id="bkx-bar-go"><?= v2_te('See your booking') ?></button>
    </div>
  </section>

  <!-- ===================== DETAILS ===================== -->
  <section class="xpd" id="detalii" aria-labelledby="xp-about-h">
    <div class="wrap">
      <div class="xpd-head">
        <p class="kicker"><?= v2_te('About') ?></p>
        <h2 id="xp-about-h"><?= v2_te('What to expect') ?></h2>
      </div>
      <div class="xpd-body<?= $xpLeadBig ? ' is-lead' : '' ?>"><?= $xpDescHtml !== '' ? $xpDescHtml : '<p>' . v2_e($xpShort !== '' ? $xpShort : $xpTitle) . '</p>' ?></div>

      <?php if ($xpFacts): ?>
      <ul class="xpd-tiles">
        <?php foreach ($xpFacts as [$fIcon, $fLabel, $fValue]): ?>
        <li><span class="xpd-tile-ic"><?= v2_ic($fIcon) ?></span><span class="xpd-tile-t"><b><?= v2_e($fLabel) ?></b><span><?= v2_e($fValue) ?></span></span></li>
        <?php endforeach; ?>
      </ul>
      <?php endif; ?>

      <?php if ($xpIncluded || $xpExcluded || $xpNeeds): ?>
      <div class="xpd-two">
        <?php if ($xpIncluded): ?>
        <div class="xpd-col is-in">
          <h3><?= v2_ic('check-circle') ?><?= v2_te('Included in the price') ?></h3>
          <ul><?php foreach ($xpIncluded as $x): ?><li><?= v2_ic('check') ?><span><?= v2_e($x) ?></span></li><?php endforeach; ?></ul>
        </div>
        <?php endif; ?>
        <?php if ($xpExcluded): ?>
        <div class="xpd-col is-out">
          <h3><?= v2_ic('x') ?><?= v2_te('Not included') ?></h3>
          <ul><?php foreach ($xpExcluded as $x): ?><li><?= v2_ic('x') ?><span><?= v2_e($x) ?></span></li><?php endforeach; ?></ul>
        </div>
        <?php endif; ?>
        <?php if ($xpNeeds): ?>
        <div class="xpd-col is-need">
          <h3><?= v2_ic('info') ?><?= v2_te('What to bring') ?></h3>
          <ul><?php foreach ($xpNeeds as $x): ?><li><?= v2_ic('check') ?><span><?= v2_e($x) ?></span></li><?php endforeach; ?></ul>
        </div>
        <?php endif; ?>
      </div>
      <?php endif; ?>

      <?php if ($xpTerms !== ''): ?>
      <details class="xpd-terms"><summary><?= v2_ic('file-text') ?><?= v2_te('Terms of use') ?><?= v2_ic('caret-down') ?></summary><div class="lcp-body"><?= am_rich($xpTerms) ?></div></details>
      <?php endif; ?>

      <?php if (count($lightbox) > 1): ?>
      <ul class="tthumbs xpd-thumbs">
        <?php foreach (array_slice($lightbox, 1, 6) as $gi => $g): ?>
        <li><button type="button" data-gallery="<?= $gi + 1 ?>" aria-haspopup="dialog" aria-controls="lb"><img src="<?= v2_e(v2_thumb($g, 480, 360)) ?>" alt="<?= v2_e($xpTitle) ?>" loading="lazy" decoding="async"></button></li>
        <?php endforeach; ?>
      </ul>
      <?php endif; ?>
    </div>
  </section>

  <!-- ===================== LOCATION ===================== -->
  <?php if ($xpLocSlug !== ''): ?>
  <section class="xpl" id="locatie" aria-labelledby="xp-loc-h">
    <div class="wrap">
      <div class="xpl-card">
        <div class="xpl-copy">
          <p class="kicker"><?= v2_te('Where it takes place') ?></p>
          <h2 id="xp-loc-h"><?= v2_e($xpLocName) ?></h2>
          <?php if ($xpWhere !== ''): ?><p class="xpl-where"><?= v2_ic('map-pin') ?><?= v2_e($xpWhere) ?></p><?php endif; ?>
          <?php if (!empty($xpLocation['short_description'])): ?><p class="xpl-desc"><?= v2_e($xpLocation['short_description']) ?></p><?php endif; ?>
          <?php if ($xpMeeting !== ''): ?><p class="xpl-meet"><?= v2_ic('target') ?><span><b><?= v2_te('Meeting point') ?></b><?= v2_e($xpMeeting) ?></span></p><?php endif; ?>
          <?php if ($xpLocCounts): ?>
          <ul class="xpl-counts">
            <?php foreach ($xpLocCounts as [$cN, $cLabel]): ?><li><b><?= v2_e($cN) ?></b><?= v2_e($cLabel) ?></li><?php endforeach; ?>
          </ul>
          <?php endif; ?>
          <div class="xpl-cta">
            <a class="btn btn-primary" href="/venue/<?= v2_e($xpLocSlug) ?>"><?= v2_te('Everything at {place}', ['place' => $xpLocName]) ?><?= v2_ic('arrow-right') ?></a>
            <?php if ($mapsUrl): ?><a class="btn btn-ghost" href="<?= v2_e($mapsUrl) ?>" target="_blank" rel="noopener"><?= v2_ic('map-pin') ?><?= v2_te('Open in Maps') ?></a><?php endif; ?>
          </div>
        </div>
        <div class="xpl-media">
          <?php if ($xpLocCover !== ''): ?>
          <div class="xpl-shot"><img src="<?= v2_e(v2_thumb($xpLocCover, 960, 540)) ?>" alt="<?= v2_e($xpLocName) ?>" loading="lazy" decoding="async"></div>
          <?php endif; ?>
          <?php if ($mapsUrl): ?>
          <div class="xpl-map">
            <iframe title="<?= v2_te('Map of {name}', ['name' => $xpLocName]) ?>" loading="lazy" referrerpolicy="no-referrer-when-downgrade" src="https://www.google.com/maps?q=<?= urlencode($xpLat . ',' . $xpLng) ?>&z=14&output=embed"></iframe>
          </div>
          <?php endif; ?>
        </div>
      </div>

      <?php if ($xpSiblings): ?>
      <div class="xpl-more">
        <div class="xpl-more-h">
          <h3><?= v2_te('Also on sale here') ?></h3>
          <a class="mpd-link xpl-all" href="/venue/<?= v2_e($xpLocSlug) ?>#bilete"><?= v2_te('All tickets for this venue') ?><?= v2_ic('arrow-right') ?></a>
        </div>
        <ul class="xpl-grid">
          <?php foreach ($xpSiblings as $si => $s): ?>
          <?php
            $sImg   = v2_media_url($s['image'] ?? null);
            $isXp   = ($s['type'] ?? '') === 'experience';
            $sType  = am_product_types_t()[$s['type'] ?? ''] ?? v2_t('Ticket');
            $sHref  = $isXp && !empty($s['slug']) ? '/experience/' . $s['slug'] : '/venue/' . $xpLocSlug . '#bilete';
            $sLine  = trim((string) ($s['subtitle'] ?? ''));
            $sMin   = (int) ($s['duration_minutes'] ?? 0);
            if ($sLine === '' && $sMin > 0) {
                $sLine = $sMin >= 60 && $sMin % 60 === 0 ? ($sMin / 60) . ' h' : $sMin . ' min';
            }
          ?>
          <li>
            <a class="xpl-item" href="<?= v2_e($sHref) ?>">
              <span class="xpl-item-media<?= $sImg ? '' : ' is-empty' ?>">
                <?php if ($sImg): ?><img src="<?= v2_e(v2_thumb($sImg, 480, 300)) ?>" alt="" loading="lazy" decoding="async"><?php else: ?><span class="xpl-item-ph" aria-hidden="true"><?= am_product_icon_svg(am_product_icon_or_type($s['icon'] ?? null, $s['type'] ?? null)) ?></span><?php endif; ?>
                <span class="xpl-item-type"><?= v2_e($sType) ?></span>
              </span>
              <span class="xpl-item-b">
                <b><?= v2_e(navFlatName($s['title'] ?? '')) ?></b>
                <?php if ($sLine !== ''): ?><small><?= v2_e($sLine) ?></small><?php endif; ?>
                <span class="xpl-item-foot">
                  <?php if (!empty($s['min_price_cents'])): ?><span class="xpl-item-price"><?= v2_t('from <b>{price}</b>', ['price' => v2_e(v2_money_in((int) $s['min_price_cents'] / 100, strtoupper((string) ($s['currency'] ?? '')) ?: $xpCurrency))]) ?></span><?php endif; ?>
                  <span class="xp-go"><?= v2_ic('arrow-right') ?></span>
                </span>
              </span>
            </a>
          </li>
          <?php endforeach; ?>
        </ul>
      </div>
      <?php endif; ?>
    </div>
  </section>
  <?php endif; ?>

  <?php if ($lightbox): ?>
  <!-- ===================== LIGHTBOX ===================== -->
  <div class="lb" id="lb" role="dialog" aria-modal="true" aria-labelledby="lb-title" hidden>
    <div class="lb-top">
      <p class="lb-title" id="lb-title"><?= v2_e($xpTitle) ?></p>
      <span class="lb-count" id="lb-count">1 / <?= count($lightbox) ?></span>
      <button class="icon-btn" type="button" data-lb="close"><?= v2_ic('x') ?><span class="sr"><?= v2_te('Close the gallery') ?></span></button>
    </div>
    <figure class="lb-fig"><img id="lb-img" src="" alt=""></figure>
    <div class="lb-nav"<?= count($lightbox) < 2 ? ' hidden' : '' ?>>
      <button class="rail-btn" type="button" data-lb="prev" aria-label="<?= v2_te('Previous photo') ?>"><?= v2_ic('arrow-left') ?></button>
      <button class="rail-btn" type="button" data-lb="next" aria-label="<?= v2_te('Next photo') ?>"><?= v2_ic('arrow-right') ?></button>
    </div>
  </div>
  <?php endif; ?>
</main>
<?php include __DIR__ . '/includes/v2/footer.php'; ?>
