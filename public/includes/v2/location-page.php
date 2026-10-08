<?php
/**
 * Location sold through the activities module: /venue/{slug} (v2 design).
 *
 * Included by locatie.php when the module knows the slug (GET /activities-module/locations/{slug}); expects
 * $location (the API's data) and $slug. Venues from the older catalogue keep their own page in locatie.php.
 *
 * Top to bottom: hero (category · city, name, subtitle, address and prices, photo), tickets for one date
 * (access tickets, experiences and packages together; booking.js), about + gallery + map, program and
 * facilities, lodging (information and the operator's own booking links, nothing sold here), rules, nearby
 * attractions, FAQ, gallery lightbox. Hero, map and lightbox come from attraction.css + attraction.js.
 */

require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/nav.php';
require_once __DIR__ . '/am-labels.php';

$lcName     = navFlatName($location['name'] ?? '') ?: 'Venue';
$lcSubtitle = trim((string) ($location['subtitle'] ?? ''));
$lcShort    = trim((string) ($location['short_description'] ?? ''));
$lcDescHtml = am_rich($location['description'] ?? '');
$lcRules    = trim((string) ($location['rules'] ?? ''));
$lcCity     = is_array($location['city'] ?? null) ? $location['city'] : [];
$lcCityName = navFlatName($lcCity['name'] ?? '');
$lcCitySlug = (string) ($lcCity['slug'] ?? '');
$lcCatName  = is_array($location['category'] ?? null) ? navFlatName($location['category']['name'] ?? '') : '';
$lcAddress  = trim((string) ($location['address'] ?? ''));
$lcLat      = $location['latitude'] ?? null;
$lcLng      = $location['longitude'] ?? null;
$lcCover    = v2_media_url($location['cover_image'] ?? null) ?? '';
$lcGallery  = array_values(array_filter(array_map('v2_media_url', (array) ($location['gallery'] ?? []))));
$lcContact  = is_array($location['contact'] ?? null) ? $location['contact'] : [];
$lcSeasons  = array_values(array_filter((array) ($location['seasons'] ?? []), 'is_array'));
$lcClosed   = array_slice(array_values((array) ($location['closed_dates'] ?? [])), 0, 8);
$lcFacilities = am_facility_labels($location['facilities'] ?? []); // the operator's own entries (custom:…) come through too
$lcLodging  = is_array($location['lodging'] ?? null) ? $location['lodging'] : null;
$lcFaqs     = array_values(array_filter((array) ($location['faqs'] ?? []), fn ($f) => is_array($f) && trim((string) ($f['q'] ?? '')) !== '' && trim((string) ($f['a'] ?? '')) !== ''));
$lcNearby   = array_values(array_filter((array) ($location['nearby_attractions'] ?? []), fn ($a) => is_array($a) && !empty($a['slug'])));
$lcProducts = array_values(array_filter((array) ($location['products'] ?? []), fn ($p) => is_array($p) && !empty($p['variants'])));
$lcCounts   = is_array($location['counts'] ?? null) ? $location['counts'] : [];
$lcMinPrice = (int) ($location['min_price_cents'] ?? 0);
$lcOrganizer = is_array($location['organizer'] ?? null) ? navFlatName($location['organizer']['name'] ?? '') : '';
// The currency this location sells (and charges) in: the location's own when the API sends it, else the one its
// products' variants are priced in (the API sends `currency` with every variant's price). Every price here uses it.
$lcVariantCurrencies = [];
foreach ($lcProducts as $lcP) {
    $lcVariantCurrencies = array_merge($lcVariantCurrencies, array_filter(array_column((array) ($lcP['variants'] ?? []), 'currency')), array_filter([$lcP['currency'] ?? null]));
}
$lcCurrency = $location['currency'] ?? $lcVariantCurrencies[0] ?? '';
$lcCurrency = is_string($lcCurrency) && $lcCurrency !== '' ? strtoupper($lcCurrency) : SITE_CURRENCY;
// ISO code of the country, when the API names it (structured data only).
$lcCountry = $lcCity['country'] ?? $location['country'] ?? '';
$lcCountry = is_string($lcCountry) && preg_match('/^[A-Za-z]{2}$/', $lcCountry) ? strtoupper($lcCountry) : null;

$lightbox = array_values(array_unique(array_filter(array_merge($lcCover ? [$lcCover] : [], $lcGallery))));
$heroImage = $lightbox[0] ?? '';
$mapsUrl = ($lcLat && $lcLng)
    ? 'https://www.google.com/maps/search/?api=1&query=' . urlencode($lcLat . ',' . $lcLng)
    : (preg_match('#^https?://#i', (string) ($location['google_maps_url'] ?? '')) ? (string) $location['google_maps_url'] : '');

// "3 entry tickets · 2 experiences · 1 package"
$countBits = array_filter([
    !empty($lcCounts['access']) ? v2_num((int) $lcCounts['access'], 'entry ticket', 'entry tickets') : '',
    !empty($lcCounts['experience']) ? v2_exp((int) $lcCounts['experience']) : '',
    !empty($lcCounts['package']) ? v2_num((int) $lcCounts['package'], 'package', 'packages') : '',
]);

$breadcrumbs = [['name' => 'Home', 'url' => SITE_URL . '/'], ['name' => 'Venues', 'url' => SITE_URL . '/venues']];
if ($lcCityName !== '' && $lcCitySlug !== '') {
    $breadcrumbs[] = ['name' => $lcCityName, 'url' => SITE_URL . '/' . $lcCitySlug];
}
$breadcrumbs[] = ['name' => $lcName, 'url' => SITE_URL . '/venue/' . $slug];

$kicker = trim($lcCatName . ($lcCityName !== '' ? ' · ' . $lcCityName : ''), ' ·') ?: 'Venue';
$seo = is_array($location['seo'] ?? null) ? $location['seo'] : [];
$pageTitleRaw = trim((string) ($seo['meta_title'] ?? '')) ?: ($lcName . ($lcProducts ? ': tickets online' : '') . ($lcCityName !== '' ? ', ' . $lcCityName : '') . ' | Viaqui');
$pageDescription = trim((string) ($seo['meta_description'] ?? ''))
    ?: mb_substr($lcShort !== '' ? $lcShort : trim(preg_replace('/\s+/u', ' ', strip_tags($lcDescHtml))), 0, 160)
    ?: ('Tickets and experiences at ' . $lcName . ($lcCityName !== '' ? ', ' . $lcCityName : '') . '. Book online and walk in with the ticket on your phone.');
$canonicalUrl = SITE_URL . '/venue/' . $slug;
$ogImage = $heroImage ?: (SITE_URL . '/assets/images/og-default.jpg');

$lcPostal = $lcAddress !== '' ? array_filter(['@type' => 'PostalAddress', 'streetAddress' => $lcAddress, 'addressLocality' => $lcCityName ?: null, 'addressCountry' => $lcCountry]) : null;
$lcGeo = ($lcLat && $lcLng) ? ['@type' => 'GeoCoordinates', 'latitude' => $lcLat, 'longitude' => $lcLng] : null;
$structuredData = [array_filter([
    '@context' => 'https://schema.org',
    '@type' => 'TouristAttraction',
    'name' => $lcName,
    'description' => $pageDescription,
    'url' => $canonicalUrl,
    'image' => $lightbox ?: null,
    'address' => $lcPostal,
    'geo' => $lcGeo,
    'telephone' => $lcContact['phone'] ?? null,
    'isAccessibleForFree' => false,
    'offers' => $lcMinPrice ? ['@type' => 'AggregateOffer', 'lowPrice' => number_format($lcMinPrice / 100, 2, '.', ''), 'priceCurrency' => $lcCurrency, 'url' => $canonicalUrl . '#bilete'] : null,
])];
if ($lcLodging) {
    $structuredData[] = array_filter([
        '@context' => 'https://schema.org',
        '@type' => 'LodgingBusiness',
        'name' => $lcName,
        'url' => $canonicalUrl . '#cazare',
        'address' => $lcPostal,
        'geo' => $lcGeo,
        'checkinTime' => $lcLodging['check_in'] ?? null,
        'checkoutTime' => $lcLodging['check_out'] ?? null,
        'amenityFeature' => array_values(array_map(fn ($f) => ['@type' => 'LocationFeatureSpecification', 'name' => $f, 'value' => true],
            am_facility_labels($lcLodging['facilities'] ?? [], AM_LODGING_FACILITIES))) ?: null,
    ]);
}
if ($lcFaqs) {
    $structuredData[] = [
        '@context' => 'https://schema.org',
        '@type' => 'FAQPage',
        'mainEntity' => array_map(fn ($f) => ['@type' => 'Question', 'name' => $f['q'], 'acceptedAnswer' => ['@type' => 'Answer', 'text' => $f['a']]], $lcFaqs),
    ];
}
$structuredData[] = [
    '@context' => 'https://schema.org',
    '@type' => 'BreadcrumbList',
    'itemListElement' => array_map(fn ($bc, $i) => ['@type' => 'ListItem', 'position' => $i + 1, 'name' => $bc['name'], 'item' => $bc['url']], $breadcrumbs, array_keys($breadcrumbs)),
];

// What booking.js needs of each product (descriptions stay on the server).
$bookingProducts = array_map(fn ($p) => [
    'id' => $p['id'], 'slug' => $p['slug'] ?? null, 'type' => $p['type'] ?? 'access', 'title' => navFlatName($p['title'] ?? ''),
    'subtitle' => $p['subtitle'] ?? null, 'short_description' => $p['short_description'] ?? null, 'icon' => am_product_icon($p['icon'] ?? null),
    'image' => v2_media_url($p['image'] ?? null), 'booking_mode' => $p['booking_mode'] ?? 'day', 'capacity_mode' => $p['capacity_mode'] ?? null,
    'duration_minutes' => $p['duration_minutes'] ?? 0, 'unit_label' => $p['unit_label'] ?? null, 'usage_terms' => $p['usage_terms'] ?? null,
    'display_category' => $p['display_category'] ?? null, 'access_requirement' => $p['access_requirement'] ?? 'none',
    'requires_vehicle_info' => !empty($p['requires_vehicle_info']), 'included_items' => $p['included_items'] ?? [],
    'age_min' => $p['age_min'] ?? null, 'age_max' => $p['age_max'] ?? null, 'commission' => $p['commission'] ?? null,
    'currency' => $p['currency'] ?? null, // each variant carries its own `currency` too (kept whole below)
    'variants' => $p['variants'] ?? [], 'addons' => $p['addons'] ?? [], 'components' => $p['components'] ?? [],
], $lcProducts);

$lcHasDay = (bool) array_filter($lcProducts, fn ($p) => ($p['booking_mode'] ?? 'day') === 'day');
$lcHasSlot = (bool) array_filter($lcProducts, fn ($p) => ($p['booking_mode'] ?? '') === 'slot');
$lcTz = new DateTimeZone('Europe/Bucharest');
$v2Styles = ['attraction.css', 'location.css'];
$v2Scripts = ['hdrag.js', 'attraction.js', 'booking.js'];
$v2LegacyScripts = ['assets/js/config.js', 'assets/js/cart.js'];
$v2HeaderOverlay = true;
$v2HeadExtra = $heroImage ? '<link rel="preload" as="image" href="' . v2_e($heroImage) . '" fetchpriority="high">' : '';
$v2ClientData = [
    'gallery' => array_map(fn ($src) => ['src' => $src, 'alt' => $lcName], $lightbox),
    'booking' => [
        'mode' => 'location',
        'location' => ['slug' => $slug, 'name' => $lcName, 'city' => $lcCityName ?: null, 'image' => $heroImage ?: null],
        'product_slug' => null,
        'products' => $bookingProducts,
        'categories' => array_values((array) ($location['display_categories'] ?? [])),
        'today' => (new DateTimeImmutable('now', $lcTz))->format('Y-m-d'),
        'max_days' => max(1, (int) ($location['max_advance_days'] ?? 0) ?: 90),
        'focus_product_id' => null,
        'currency' => $lcCurrency, // what booking.js falls back to when a variant or a calendar day names none
    ],
];

$lcArches = '<svg class="deco-arches" viewBox="0 0 400 400" aria-hidden="true" focusable="false"><path d="M40 400V200a160 160 0 0 1 320 0v200"/><path d="M90 400V200a110 110 0 0 1 220 0v200"/><path d="M140 400V200a60 60 0 0 1 120 0v200"/></svg>';
$lcLodgingType = $lcLodging ? ([
    'pensiune' => 'Guest house', 'hotel' => 'Hotel', 'cabana' => 'Mountain lodge', 'vila' => 'Villa', 'apartamente' => 'Apartments',
    'camping' => 'Campsite', 'glamping' => 'Glamping', 'altele' => 'Accommodation',
][$lcLodging['type'] ?? ''] ?? 'Accommodation') : '';

$v2HeadExtra .= v2_track_organizer($location['organizer_id'] ?? null);
include __DIR__ . '/head.php';
include __DIR__ . '/header.php';
?>
<main id="main" tabindex="-1">
<?= am_product_icon_sprite(array_column($bookingProducts, 'icon')) ?>
  <!-- ===================== HERO ===================== -->
  <section class="th" aria-labelledby="th-h">
    <?= $lcArches ?>
    <svg class="th-line draw-clip" viewBox="0 590 3240 310" aria-hidden="true" focusable="false"><use href="#drum-g"/></svg>
    <div class="th-in">
      <div class="th-copy">
        <nav class="crumbs" aria-label="Breadcrumb">
          <?php foreach ($breadcrumbs as $i => $bc): ?>
            <?php if ($i > 0): ?><span aria-hidden="true">/</span><?php endif; ?>
            <?php if ($i < count($breadcrumbs) - 1): ?><a href="<?= v2_e(substr($bc['url'], strlen(SITE_URL)) ?: '/') ?>"><?= v2_e($bc['name']) ?></a><?php else: ?><span aria-current="page"><?= v2_e($bc['name']) ?></span><?php endif; ?>
          <?php endforeach; ?>
        </nav>
        <p class="th-kicker"><?= v2_e($kicker) ?></p>
        <h1 class="th-h" id="th-h"><?= v2_e($lcName) ?></h1>
        <?php if ($lcSubtitle !== ''): ?><p class="th-sub"><?= v2_e($lcSubtitle) ?></p><?php endif; ?>
        <?php if ($lcAddress !== '' || $lcMinPrice || $countBits): ?>
        <ul class="th-chips">
          <?php if ($lcAddress !== ''): ?><li><?= v2_ic('map-pin') ?><?= v2_e($lcAddress) ?></li><?php endif; ?>
          <?php if ($lcMinPrice): ?><li><?= v2_ic('ticket') ?>from <?= v2_e(v2_money_in($lcMinPrice / 100, $lcCurrency)) ?></li><?php endif; ?>
          <?php if ($countBits): ?><li><?= v2_e(implode(' · ', $countBits)) ?></li><?php endif; ?>
        </ul>
        <?php endif; ?>
        <div class="th-cta">
          <?php if ($lcProducts): ?>
            <a class="btn btn-light" href="#bilete">Choose your tickets<?= v2_ic('arrow-right') ?></a>
          <?php elseif ($lcCitySlug !== ''): ?>
            <a class="btn btn-light" href="/<?= v2_e($lcCitySlug) ?>">Things to do in <?= v2_e($lcCityName) ?><?= v2_ic('arrow-right') ?></a>
          <?php endif; ?>
          <?php if ($mapsUrl): ?>
            <a class="btn btn-outline-light" href="<?= v2_e($mapsUrl) ?>" target="_blank" rel="noopener"><?= v2_ic('map-pin') ?>Open in Maps</a>
          <?php endif; ?>
        </div>
      </div>

      <div class="th-media">
        <?php if ($lightbox): ?>
        <button class="th-arch" type="button" data-gallery="0" aria-haspopup="dialog" aria-controls="lb" aria-label="Open the gallery: <?= v2_e($lcName) ?>">
          <img src="<?= v2_e($heroImage) ?>" alt="<?= v2_e($lcName) ?>" fetchpriority="high" decoding="async">
          <?php if (count($lightbox) > 1): ?><span class="th-gal"><?= v2_ic('magnifying-glass') ?>See the gallery (<?= count($lightbox) ?>)</span><?php endif; ?>
        </button>
        <?php else: ?>
        <div class="th-arch is-empty"><?= v2_fallback($lcName) ?><?php if ($lcCityName !== ''): ?><span class="th-arch-name" aria-hidden="true"><?php if ($lcCatName !== ''): ?><small><?= v2_e($lcCatName) ?></small><?php endif; ?><?= v2_e($lcCityName) ?></span><?php endif; ?></div>
        <?php endif; ?>
      </div>
    </div>
  </section>
  <div id="hdr-sentinel" aria-hidden="true"></div>

  <!-- ===================== TICKETS ===================== -->
  <?php if ($lcProducts): ?>
  <section class="bkx-sec" id="bilete" aria-labelledby="bkx-h">
    <div class="wrap bkx-grid" id="bkx">
      <div>
        <div class="bkx-head">
          <div><p class="kicker">Book online</p><h2 id="bkx-h">Tickets and experiences</h2></div>
          <button class="bkx-link" type="button" id="bkx-cal-toggle" aria-expanded="false" aria-controls="bkx-cal">Another date</button>
        </div>
        <ul class="bkx-days" id="bkx-days" aria-label="Choose the day of your visit"></ul>
        <div class="bkx-cal" id="bkx-cal" hidden>
          <div class="bkx-cal-head">
            <button class="rail-btn" type="button" id="bkx-cal-prev" aria-label="Previous month"><?= v2_ic('arrow-left') ?></button>
            <p id="bkx-cal-title" aria-live="polite"></p>
            <button class="rail-btn" type="button" id="bkx-cal-next" aria-label="Next month"><?= v2_ic('arrow-right') ?></button>
          </div>
          <div class="bkx-cal-dow" aria-hidden="true"><span>Mo</span><span>Tu</span><span>We</span><span>Th</span><span>Fr</span><span>Sa</span><span>Su</span></div>
          <div class="bkx-cal-grid" id="bkx-cal-grid"></div>
        </div>
        <p class="bkx-hours" id="bkx-hours" aria-live="polite"></p>
        <div class="bkx-tabs" id="bkx-tabs" role="group" aria-label="Ticket categories" hidden></div>
        <div class="bkx-list" id="bkx-list"></div>
      </div>

      <aside class="bkx-side" aria-label="Your booking">
        <div class="bkx-sum" id="bkx-sum" hidden>
          <h3>Your booking</h3>
          <ul class="bkx-lines" id="bkx-lines"></ul>
          <div class="bkx-row"><span>Subtotal</span><strong id="bkx-sub"><?= v2_e(v2_money_in(0, $lcCurrency)) ?></strong></div>
          <div class="bkx-row" id="bkx-fee-row" hidden><span id="bkx-fee-label">Booking fee</span><strong id="bkx-fee"><?= v2_e(v2_money_in(0, $lcCurrency)) ?></strong></div>
          <div class="bkx-row bkx-total"><span>Total</span><strong id="bkx-total"><?= v2_e(v2_money_in(0, $lcCurrency)) ?></strong></div>
          <p class="bkx-err" id="bkx-err" role="alert" hidden></p>
          <div class="bkx-cta">
            <button class="btn btn-primary" type="button" id="bkx-go" disabled>Continue to payment<?= v2_ic('arrow-right') ?></button>
            <button class="btn btn-ghost" type="button" id="bkx-cart" disabled><?= v2_ic('shopping-cart-simple') ?>Add to basket</button>
          </div>
          <p class="bkx-small"><span id="bkx-card-note" hidden>The card processing fee is worked out at checkout and depends on the payment method you choose. </span>Your tickets arrive by email straight after payment. Show them on your phone at the entrance.</p>
        </div>
        <div class="lcp-card">
          <h3>Good to know</h3>
          <ul class="lcp-facts">
            <?php if ($lcHasDay): ?><li><?= v2_ic('calendar-blank') ?><span>Tickets without a time are valid on the day you choose, during the venue's opening hours.</span></li><?php endif; ?>
            <?php if ($lcHasSlot): ?><li><?= v2_ic('clock') ?><span>Experiences at a fixed time have limited places: you choose your time right here.</span></li><?php endif; ?>
            <li><?= v2_ic('lock-simple') ?><span>Secure card payment. One basket can hold tickets from several venues.</span></li>
            <?php if ($lcOrganizer !== ''): ?><li><?= v2_ic('buildings') ?><span>Operator: <?= v2_e($lcOrganizer) ?></span></li><?php endif; ?>
          </ul>
        </div>
      </aside>
    </div>
    <div class="bkx-bar" id="bkx-bar" hidden>
      <div><b id="bkx-bar-total"><?= v2_e(v2_money_in(0, $lcCurrency)) ?></b><span id="bkx-bar-count"></span></div>
      <button class="btn btn-primary" type="button" id="bkx-bar-go">See your booking</button>
    </div>
  </section>
  <?php endif; ?>

  <!-- ===================== ABOUT + MAP ===================== -->
  <?php if ($lcDescHtml !== '' || $lcShort !== '' || count($lightbox) > 1 || $mapsUrl): ?>
  <section class="sec tabout" id="despre" aria-labelledby="tabout-h">
    <div class="wrap tabout-grid">
      <div>
        <p class="kicker">About</p>
        <h2 id="tabout-h">About <?= v2_e($lcName) ?></h2>
        <div class="tabout-body lcp-body"><?= $lcDescHtml !== '' ? $lcDescHtml : '<p>' . v2_e($lcShort) . '</p>' ?></div>

        <?php if (count($lightbox) > 1): ?>
          <ul class="tthumbs">
            <?php foreach (array_slice($lightbox, 1, 6) as $gi => $g): ?>
            <li><button type="button" data-gallery="<?= $gi + 1 ?>" aria-haspopup="dialog" aria-controls="lb"><img src="<?= v2_e($g) ?>" alt="<?= v2_e($lcName) ?>" loading="lazy" decoding="async"></button></li>
            <?php endforeach; ?>
          </ul>
        <?php endif; ?>
      </div>

      <?php if ($mapsUrl): ?>
      <div class="tmap">
        <?php if ($lcLat && $lcLng): ?>
        <iframe title="Map of <?= v2_e($lcName) ?>" loading="lazy" referrerpolicy="no-referrer-when-downgrade" src="https://www.google.com/maps?q=<?= urlencode($lcLat . ',' . $lcLng) ?>&z=14&output=embed"></iframe>
        <?php endif; ?>
        <a class="tmap-link" href="<?= v2_e($mapsUrl) ?>" target="_blank" rel="noopener"><?= v2_ic('map-pin') ?>Open in Google Maps<?= v2_ic('arrow-right') ?></a>
      </div>
      <?php endif; ?>
    </div>
  </section>
  <?php endif; ?>

  <!-- ===================== PROGRAM + FACILITIES + CONTACT ===================== -->
  <?php if ($lcSeasons || $lcFacilities || $lcClosed || array_filter($lcContact)): ?>
  <section class="lcp-sec" id="program" aria-labelledby="lcp-prog-h">
    <div class="wrap">
      <div class="sec-head"><div><p class="kicker">Opening hours</p><h2 id="lcp-prog-h">When it is open</h2></div></div>
      <div class="lcp-grid">
        <div class="lcp-card">
          <?php if ($lcSeasons): ?>
            <?php foreach ($lcSeasons as $s): $range = trim(am_month_day($s['start'] ?? null) . ' – ' . am_month_day($s['end'] ?? null), ' –'); ?>
            <div class="lcp-season">
              <b><?= v2_e(trim((string) ($s['name'] ?? '')) ?: 'Opening hours') ?></b>
              <?php if ($range !== ''): ?><small><?= v2_e($range) ?></small><?php endif; ?>
              <dl class="lcp-hours">
                <?php foreach (am_week_rows($s['schedule'] ?? []) as [$days, $hours]): ?>
                <dt><?= v2_e($days) ?></dt><dd><?= v2_e($hours) ?></dd>
                <?php endforeach; ?>
                <?php if (!empty($s['last_entry'])): ?><dt>last entry</dt><dd><?= v2_e(substr((string) $s['last_entry'], 0, 5)) ?></dd><?php endif; ?>
              </dl>
            </div>
            <?php endforeach; ?>
          <?php else: ?>
            <p class="bkx-note">The day's opening hours appear with the tickets, once you choose a date.</p>
          <?php endif; ?>
          <?php if ($lcClosed): ?>
            <div class="lcp-season">
              <b>Days it is closed</b>
              <small><?= v2_e(implode(', ', array_filter(array_map('am_date', $lcClosed)))) ?></small>
            </div>
          <?php endif; ?>
        </div>

        <div class="lcp-card">
          <?php if ($lcFacilities): ?>
            <h3>Facilities</h3>
            <ul class="lcp-chips"><?php foreach ($lcFacilities as $f): ?><li><?= v2_e($f) ?></li><?php endforeach; ?></ul>
          <?php endif; ?>
          <?php if (array_filter($lcContact) || $lcAddress !== ''): ?>
            <h3>Contact</h3>
            <ul class="lcp-facts">
              <?php if ($lcAddress !== ''): ?><li><?= v2_ic('map-pin') ?><span><?= v2_e($lcAddress) ?></span></li><?php endif; ?>
              <?php if (!empty($lcContact['phone'])): ?><li><?= v2_ic('phone') ?><a href="tel:<?= v2_e(preg_replace('/[^0-9+]/', '', $lcContact['phone'])) ?>"><?= v2_e($lcContact['phone']) ?></a></li><?php endif; ?>
              <?php if (!empty($lcContact['email'])): ?><li><?= v2_ic('envelope-simple') ?><a href="mailto:<?= v2_e($lcContact['email']) ?>"><?= v2_e($lcContact['email']) ?></a></li><?php endif; ?>
              <?php if (!empty($lcContact['website']) && preg_match('#^https?://#i', $lcContact['website'])): ?><li><?= v2_ic('globe-simple') ?><a href="<?= v2_e($lcContact['website']) ?>" target="_blank" rel="nofollow noopener"><?= v2_e(preg_replace('#^https?://(www\.)?#i', '', rtrim($lcContact['website'], '/'))) ?></a></li><?php endif; ?>
            </ul>
          <?php endif; ?>
        </div>
      </div>
    </div>
  </section>
  <?php endif; ?>

  <!-- ===================== LODGING (information + the operator's links) ===================== -->
  <?php if ($lcLodging): $lgLinks = array_values(array_filter((array) ($lcLodging['links'] ?? []), fn ($l) => is_array($l) && preg_match('#^https?://#i', (string) ($l['url'] ?? '')))); ?>
  <section class="lcp-sec" id="cazare" aria-labelledby="lcp-lodge-h">
    <div class="wrap lcp-lodging">
      <div class="sec-head">
        <div><p class="kicker"><?= v2_e($lcLodgingType . (!empty($lcLodging['classification']) ? ' · ' . $lcLodging['classification'] : '')) ?></p><h2 id="lcp-lodge-h">Staying at <?= v2_e($lcName) ?></h2></div>
      </div>
      <div class="lcp-grid">
        <div class="lcp-body">
          <?php if (!empty($lcLodging['description'])): ?><?= am_rich($lcLodging['description']) ?><?php endif; ?>
          <ul class="lcp-facts">
            <?php if (!empty($lcLodging['check_in']) || !empty($lcLodging['check_out'])): ?><li><?= v2_ic('clock') ?><span><?= v2_e(trim((!empty($lcLodging['check_in']) ? 'Check-in from ' . $lcLodging['check_in'] : '') . (!empty($lcLodging['check_out']) ? (!empty($lcLodging['check_in']) ? ', check-out by ' : 'Check-out by ') . $lcLodging['check_out'] : ''))) ?></span></li><?php endif; ?>
            <?php if (!empty($lcLodging['price_from'])): ?><li><?= v2_ic('coins') ?><span>From <?= v2_e(v2_money_in(round((float) $lcLodging['price_from']), strtoupper((string) ($lcLodging['currency'] ?? '')) ?: $lcCurrency)) ?> / night</span></li><?php endif; ?>
            <?php if (!empty($lcLodging['phone'])): ?><li><?= v2_ic('phone') ?><a href="tel:<?= v2_e(preg_replace('/[^0-9+]/', '', $lcLodging['phone'])) ?>"><?= v2_e($lcLodging['phone']) ?></a></li><?php endif; ?>
            <?php if (!empty($lcLodging['email'])): ?><li><?= v2_ic('envelope-simple') ?><a href="mailto:<?= v2_e($lcLodging['email']) ?>"><?= v2_e($lcLodging['email']) ?></a></li><?php endif; ?>
          </ul>
          <?php if (!empty($lcLodging['policies'])): ?><div class="lcp-body lcp-policies"><?= am_rich($lcLodging['policies']) ?></div><?php endif; ?>
        </div>
        <div class="lcp-card">
          <?php $lgFac = am_facility_labels($lcLodging['facilities'] ?? [], AM_LODGING_FACILITIES); ?>
          <?php if ($lgFac): ?>
            <h3>Amenities</h3>
            <ul class="lcp-chips"><?php foreach ($lgFac as $f): ?><li><?= v2_e($f) ?></li><?php endforeach; ?></ul>
          <?php endif; ?>
          <?php if ($lgLinks): ?>
            <h3>Book your stay</h3>
            <div class="lcp-links">
              <?php foreach ($lgLinks as $l): ?>
              <a class="btn btn-ghost" href="<?= v2_e($l['url']) ?>" target="_blank" rel="nofollow noopener"><?= v2_e(trim((string) ($l['label'] ?? '')) ?: (['booking' => 'Booking.com', 'airbnb' => 'Airbnb', 'travelminit' => 'Travelminit', 'website' => 'Official website', 'other' => 'Book your stay'][$l['platform'] ?? 'other'] ?? 'Book')) ?><?= v2_ic('arrow-right') ?></a>
              <?php endforeach; ?>
            </div>
            <p class="bkx-small">You book the stay directly with the host, on the platform the host has chosen. viaqui.com does not handle payment for accommodation.</p>
          <?php endif; ?>
        </div>
      </div>
      <?php $lgRooms = array_values(array_filter((array) ($lcLodging['rooms'] ?? []), fn ($r) => is_array($r) && trim((string) ($r['name'] ?? '')) !== '')); ?>
      <?php if ($lgRooms): ?>
      <ul class="lcp-rooms" aria-label="Rooms">
        <?php foreach ($lgRooms as $r): $rMeta = array_filter([!empty($r['capacity']) ? v2_num((int) $r['capacity'], 'person', 'people') : '', trim((string) ($r['beds'] ?? '')), !empty($r['count']) ? v2_num((int) $r['count'], 'room', 'rooms') . ' of this type' : '']); ?>
        <li class="lcp-room">
          <b><?= v2_e($r['name']) ?></b>
          <?php if ($rMeta): ?><small><?= v2_e(implode(' · ', $rMeta)) ?></small><?php endif; ?>
          <?php if (!empty($r['description'])): ?><small><?= v2_e($r['description']) ?></small><?php endif; ?>
          <?php if (!empty($r['price_from'])): ?><p class="lcp-price">from <?= v2_e(v2_money_in(round((float) $r['price_from']), strtoupper((string) ($r['currency'] ?? $lcLodging['currency'] ?? '')) ?: $lcCurrency)) ?> / night</p><?php endif; ?>
        </li>
        <?php endforeach; ?>
      </ul>
      <?php endif; ?>
    </div>
  </section>
  <?php endif; ?>

  <!-- ===================== RULES ===================== -->
  <?php if ($lcRules !== ''): ?>
  <section class="lcp-sec" id="reguli" aria-labelledby="lcp-rules-h">
    <div class="wrap">
      <div class="lcp-card">
        <h3 id="lcp-rules-h">Visiting rules</h3>
        <div class="lcp-body"><?= am_rich($lcRules) ?></div>
      </div>
    </div>
  </section>
  <?php endif; ?>

  <!-- ===================== NEARBY ATTRACTIONS ===================== -->
  <?php if ($lcNearby): ?>
  <section class="lcp-sec" id="atractii" aria-labelledby="lcp-near-h">
    <div class="wrap">
      <div class="sec-head"><div><p class="kicker">Nearby</p><h2 id="lcp-near-h">Attractions in the area</h2></div></div>
      <ul class="lcp-near">
        <?php foreach ($lcNearby as $ni => $a): $aImg = v2_media_url($a['image'] ?? null); $aName = navFlatName($a['name'] ?? ''); ?>
        <li><a href="/attraction/<?= v2_e($a['slug']) ?>">
          <?php if ($aImg): ?><img src="<?= v2_e($aImg) ?>" alt="" loading="lazy" decoding="async"><?php else: ?><span class="lcp-near-ph" aria-hidden="true"></span><?php endif; ?>
          <span><b><?= v2_e($aName) ?></b><?php if (isset($a['distance_km'])): ?><small><?= v2_e((string) round((float) $a['distance_km'], 1)) ?> km</small><?php elseif (!empty($a['subtitle'])): ?><small><?= v2_e($a['subtitle']) ?></small><?php endif; ?></span>
        </a></li>
        <?php endforeach; ?>
      </ul>
    </div>
  </section>
  <?php endif; ?>

  <!-- ===================== FAQ ===================== -->
  <?php if ($lcFaqs): ?>
  <section class="lcp-sec" id="intrebari" aria-labelledby="lcp-faq-h">
    <div class="wrap">
      <div class="sec-head"><div><p class="kicker">Questions</p><h2 id="lcp-faq-h">Frequently asked questions</h2></div></div>
      <div class="lcp-faq">
        <?php foreach ($lcFaqs as $f): ?>
        <details><summary><?= v2_e($f['q']) ?></summary><p><?= nl2br(v2_e($f['a'])) ?></p></details>
        <?php endforeach; ?>
      </div>
    </div>
  </section>
  <?php endif; ?>

  <?php if ($lightbox): ?>
  <!-- ===================== LIGHTBOX ===================== -->
  <div class="lb" id="lb" role="dialog" aria-modal="true" aria-labelledby="lb-title" hidden>
    <div class="lb-top">
      <p class="lb-title" id="lb-title"><?= v2_e($lcName) ?></p>
      <span class="lb-count" id="lb-count">1 / <?= count($lightbox) ?></span>
      <button class="icon-btn" type="button" data-lb="close"><?= v2_ic('x') ?><span class="sr">Close the gallery</span></button>
    </div>
    <figure class="lb-fig"><img id="lb-img" src="" alt=""></figure>
    <div class="lb-nav"<?= count($lightbox) < 2 ? ' hidden' : '' ?>>
      <button class="rail-btn" type="button" data-lb="prev" aria-label="Previous photo"><?= v2_ic('arrow-left') ?></button>
      <button class="rail-btn" type="button" data-lb="next" aria-label="Next photo"><?= v2_ic('arrow-right') ?></button>
    </div>
  </div>
  <?php endif; ?>
</main>
<?php include __DIR__ . '/footer.php'; ?>
