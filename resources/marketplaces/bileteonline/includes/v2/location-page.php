<?php
/**
 * Location sold through the activities module: /locatie/{slug} (v2 design).
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

$lcName     = navFlatName($location['name'] ?? '') ?: 'Locație';
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

$lightbox = array_values(array_unique(array_filter(array_merge($lcCover ? [$lcCover] : [], $lcGallery))));
$heroImage = $lightbox[0] ?? '';
$mapsUrl = ($lcLat && $lcLng)
    ? 'https://www.google.com/maps/search/?api=1&query=' . urlencode($lcLat . ',' . $lcLng)
    : (preg_match('#^https?://#i', (string) ($location['google_maps_url'] ?? '')) ? (string) $location['google_maps_url'] : '');

// "3 tipuri de acces · 2 experiențe · 1 pachet"
$countBits = array_filter([
    !empty($lcCounts['access']) ? v2_num((int) $lcCounts['access'], 'bilet de acces', 'bilete de acces') : '',
    !empty($lcCounts['experience']) ? v2_exp((int) $lcCounts['experience']) : '',
    !empty($lcCounts['package']) ? v2_num((int) $lcCounts['package'], 'pachet', 'pachete') : '',
]);

$breadcrumbs = [['name' => 'Acasă', 'url' => SITE_URL . '/'], ['name' => 'Locații', 'url' => SITE_URL . '/locatii']];
if ($lcCityName !== '' && $lcCitySlug !== '') {
    $breadcrumbs[] = ['name' => $lcCityName, 'url' => SITE_URL . '/' . $lcCitySlug];
}
$breadcrumbs[] = ['name' => $lcName, 'url' => SITE_URL . '/locatie/' . $slug];

$kicker = trim($lcCatName . ($lcCityName !== '' ? ' · ' . $lcCityName : ''), ' ·') ?: 'Locație';
$seo = is_array($location['seo'] ?? null) ? $location['seo'] : [];
$pageTitleRaw = trim((string) ($seo['meta_title'] ?? '')) ?: ($lcName . ($lcProducts ? ': bilete online' : '') . ($lcCityName !== '' ? ', ' . $lcCityName : '') . ' | bilete.online');
$pageDescription = trim((string) ($seo['meta_description'] ?? ''))
    ?: mb_substr($lcShort !== '' ? $lcShort : trim(preg_replace('/\s+/u', ' ', strip_tags($lcDescHtml))), 0, 160)
    ?: ('Bilete și experiențe la ' . $lcName . ($lcCityName !== '' ? ', ' . $lcCityName : '') . '. Rezervi online, intri cu biletul de pe telefon.');
$canonicalUrl = SITE_URL . '/locatie/' . $slug;
$ogImage = $heroImage ?: (SITE_URL . '/assets/images/og-default.jpg');

$lcPostal = $lcAddress !== '' ? ['@type' => 'PostalAddress', 'streetAddress' => $lcAddress, 'addressLocality' => $lcCityName ?: null, 'addressCountry' => 'RO'] : null;
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
    'offers' => $lcMinPrice ? ['@type' => 'AggregateOffer', 'lowPrice' => number_format($lcMinPrice / 100, 2, '.', ''), 'priceCurrency' => 'RON', 'url' => $canonicalUrl . '#bilete'] : null,
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
    'subtitle' => $p['subtitle'] ?? null, 'short_description' => $p['short_description'] ?? null, 'icon' => $p['icon'] ?? null,
    'image' => v2_media_url($p['image'] ?? null), 'booking_mode' => $p['booking_mode'] ?? 'day', 'capacity_mode' => $p['capacity_mode'] ?? null,
    'duration_minutes' => $p['duration_minutes'] ?? 0, 'unit_label' => $p['unit_label'] ?? null, 'usage_terms' => $p['usage_terms'] ?? null,
    'display_category' => $p['display_category'] ?? null, 'access_requirement' => $p['access_requirement'] ?? 'none',
    'requires_vehicle_info' => !empty($p['requires_vehicle_info']), 'included_items' => $p['included_items'] ?? [],
    'age_min' => $p['age_min'] ?? null, 'age_max' => $p['age_max'] ?? null, 'commission' => $p['commission'] ?? null,
    'variants' => $p['variants'] ?? [], 'addons' => $p['addons'] ?? [], 'components' => $p['components'] ?? [],
], $lcProducts);

$lcHasDay = (bool) array_filter($lcProducts, fn ($p) => ($p['booking_mode'] ?? 'day') === 'day');
$lcHasSlot = (bool) array_filter($lcProducts, fn ($p) => ($p['booking_mode'] ?? '') === 'slot');
$lcTz = new DateTimeZone('Europe/Bucharest');
$v2Styles = ['attraction.css', 'location.css'];
$v2Scripts = ['attraction.js', 'booking.js'];
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
    ],
];

$lcArches = '<svg class="deco-arches" viewBox="0 0 400 400" aria-hidden="true" focusable="false"><path d="M40 400V200a160 160 0 0 1 320 0v200"/><path d="M90 400V200a110 110 0 0 1 220 0v200"/><path d="M140 400V200a60 60 0 0 1 120 0v200"/></svg>';
$lcLodgingType = $lcLodging ? (AM_LODGING_TYPES[$lcLodging['type'] ?? ''] ?? 'Cazare') : '';

include __DIR__ . '/head.php';
include __DIR__ . '/header.php';
?>
<main id="main" tabindex="-1">
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
          <?php if ($lcMinPrice): ?><li><?= v2_ic('ticket') ?>de la <?= v2_e(am_lei($lcMinPrice)) ?></li><?php endif; ?>
          <?php if ($countBits): ?><li><?= v2_e(implode(' · ', $countBits)) ?></li><?php endif; ?>
        </ul>
        <?php endif; ?>
        <div class="th-cta">
          <?php if ($lcProducts): ?>
            <a class="btn btn-light" href="#bilete">Alege biletele<?= v2_ic('arrow-right') ?></a>
          <?php elseif ($lcCitySlug !== ''): ?>
            <a class="btn btn-light" href="/<?= v2_e($lcCitySlug) ?>">Ce poți face în <?= v2_e($lcCityName) ?><?= v2_ic('arrow-right') ?></a>
          <?php endif; ?>
          <?php if ($mapsUrl): ?>
            <a class="btn btn-outline-light" href="<?= v2_e($mapsUrl) ?>" target="_blank" rel="noopener"><?= v2_ic('map-pin') ?>Deschide în Maps</a>
          <?php endif; ?>
        </div>
      </div>

      <div class="th-media">
        <?php if ($lightbox): ?>
        <button class="th-arch" type="button" data-gallery="0" aria-haspopup="dialog" aria-controls="lb" aria-label="Deschide galeria: <?= v2_e($lcName) ?>">
          <img src="<?= v2_e($heroImage) ?>" alt="<?= v2_e($lcName) ?>" fetchpriority="high" decoding="async">
          <?php if (count($lightbox) > 1): ?><span class="th-gal"><?= v2_ic('magnifying-glass') ?>Vezi galeria (<?= count($lightbox) ?>)</span><?php endif; ?>
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
          <div><p class="kicker">Rezervă online</p><h2 id="bkx-h">Bilete și experiențe</h2></div>
          <button class="bkx-link" type="button" id="bkx-cal-toggle" aria-expanded="false" aria-controls="bkx-cal">Altă dată</button>
        </div>
        <ul class="bkx-days" id="bkx-days" aria-label="Alege ziua vizitei"></ul>
        <div class="bkx-cal" id="bkx-cal" hidden>
          <div class="bkx-cal-head">
            <button class="rail-btn" type="button" id="bkx-cal-prev" aria-label="Luna anterioară"><?= v2_ic('arrow-left') ?></button>
            <p id="bkx-cal-title" aria-live="polite"></p>
            <button class="rail-btn" type="button" id="bkx-cal-next" aria-label="Luna următoare"><?= v2_ic('arrow-right') ?></button>
          </div>
          <div class="bkx-cal-dow" aria-hidden="true"><span>L</span><span>Ma</span><span>Mi</span><span>J</span><span>V</span><span>S</span><span>D</span></div>
          <div class="bkx-cal-grid" id="bkx-cal-grid"></div>
        </div>
        <p class="bkx-hours" id="bkx-hours" aria-live="polite"></p>
        <div class="bkx-tabs" id="bkx-tabs" role="group" aria-label="Categorii de bilete" hidden></div>
        <div class="bkx-list" id="bkx-list"></div>
      </div>

      <aside class="bkx-side" aria-label="Rezervarea ta">
        <div class="bkx-sum" id="bkx-sum" hidden>
          <h3>Rezervarea ta</h3>
          <ul class="bkx-lines" id="bkx-lines"></ul>
          <div class="bkx-row"><span>Subtotal</span><strong id="bkx-sub">0 lei</strong></div>
          <div class="bkx-row" id="bkx-fee-row" hidden><span id="bkx-fee-label">Comision ticketing</span><strong id="bkx-fee">0 lei</strong></div>
          <div class="bkx-row bkx-total"><span>Total</span><strong id="bkx-total">0 lei</strong></div>
          <p class="bkx-small" id="bkx-card-note" hidden>Comisionul de tranzacționare a plății se calculează în checkout, în funcție de metoda de plată aleasă.</p>
          <p class="bkx-err" id="bkx-err" role="alert" hidden></p>
          <div class="bkx-cta">
            <button class="btn btn-primary" type="button" id="bkx-go" disabled>Continuă spre plată<?= v2_ic('arrow-right') ?></button>
            <button class="btn btn-ghost" type="button" id="bkx-cart" disabled><?= v2_ic('shopping-cart-simple') ?>Adaugă în coș</button>
          </div>
          <p class="bkx-small">Biletele ajung pe email imediat după plată. Le arăți de pe telefon la intrare.</p>
        </div>
        <div class="lcp-card">
          <h3>Bine de știut</h3>
          <ul class="lcp-facts">
            <?php if ($lcHasDay): ?><li><?= v2_ic('calendar-blank') ?><span>Biletele fără oră sunt valabile în ziua aleasă, în programul locației.</span></li><?php endif; ?>
            <?php if ($lcHasSlot): ?><li><?= v2_ic('clock') ?><span>Experiențele cu oră fixă au locuri limitate: alegi ora direct aici.</span></li><?php endif; ?>
            <li><?= v2_ic('lock-simple') ?><span>Plată securizată cu cardul. În același coș poți pune bilete de la mai multe locații.</span></li>
            <?php if ($lcOrganizer !== ''): ?><li><?= v2_ic('buildings') ?><span>Operator: <?= v2_e($lcOrganizer) ?></span></li><?php endif; ?>
          </ul>
        </div>
      </aside>
    </div>
    <div class="bkx-bar" id="bkx-bar" hidden>
      <div><b id="bkx-bar-total">0 lei</b><span id="bkx-bar-count"></span></div>
      <button class="btn btn-primary" type="button" id="bkx-bar-go">Vezi rezervarea</button>
    </div>
  </section>
  <?php endif; ?>

  <!-- ===================== ABOUT + MAP ===================== -->
  <?php if ($lcDescHtml !== '' || $lcShort !== '' || count($lightbox) > 1 || $mapsUrl): ?>
  <section class="sec tabout" id="despre" aria-labelledby="tabout-h">
    <div class="wrap tabout-grid">
      <div>
        <p class="kicker">Despre</p>
        <h2 id="tabout-h">Despre <?= v2_e($lcName) ?></h2>
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
        <iframe title="Hartă <?= v2_e($lcName) ?>" loading="lazy" referrerpolicy="no-referrer-when-downgrade" src="https://www.google.com/maps?q=<?= urlencode($lcLat . ',' . $lcLng) ?>&z=14&output=embed"></iframe>
        <?php endif; ?>
        <a class="tmap-link" href="<?= v2_e($mapsUrl) ?>" target="_blank" rel="noopener"><?= v2_ic('map-pin') ?>Deschide în Google Maps<?= v2_ic('arrow-right') ?></a>
      </div>
      <?php endif; ?>
    </div>
  </section>
  <?php endif; ?>

  <!-- ===================== PROGRAM + FACILITIES + CONTACT ===================== -->
  <?php if ($lcSeasons || $lcFacilities || $lcClosed || array_filter($lcContact)): ?>
  <section class="lcp-sec" id="program" aria-labelledby="lcp-prog-h">
    <div class="wrap">
      <div class="sec-head"><div><p class="kicker">Program</p><h2 id="lcp-prog-h">Când e deschis</h2></div></div>
      <div class="lcp-grid">
        <div class="lcp-card">
          <?php if ($lcSeasons): ?>
            <?php foreach ($lcSeasons as $s): $range = trim(am_month_day($s['start'] ?? null) . ' – ' . am_month_day($s['end'] ?? null), ' –'); ?>
            <div class="lcp-season">
              <b><?= v2_e(trim((string) ($s['name'] ?? '')) ?: 'Program') ?></b>
              <?php if ($range !== ''): ?><small><?= v2_e($range) ?></small><?php endif; ?>
              <dl class="lcp-hours">
                <?php foreach (am_week_rows($s['schedule'] ?? []) as [$days, $hours]): ?>
                <dt><?= v2_e($days) ?></dt><dd><?= v2_e($hours) ?></dd>
                <?php endforeach; ?>
                <?php if (!empty($s['last_entry'])): ?><dt>ultima intrare</dt><dd><?= v2_e(substr((string) $s['last_entry'], 0, 5)) ?></dd><?php endif; ?>
              </dl>
            </div>
            <?php endforeach; ?>
          <?php else: ?>
            <p class="bkx-note">Programul zilei apare la bilete, după ce alegi data.</p>
          <?php endif; ?>
          <?php if ($lcClosed): ?>
            <div class="lcp-season">
              <b>Zile în care e închis</b>
              <small><?= v2_e(implode(', ', array_filter(array_map('am_date', $lcClosed)))) ?></small>
            </div>
          <?php endif; ?>
        </div>

        <div class="lcp-card">
          <?php if ($lcFacilities): ?>
            <h3>Facilități</h3>
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
        <div><p class="kicker"><?= v2_e($lcLodgingType . (!empty($lcLodging['classification']) ? ' · ' . $lcLodging['classification'] : '')) ?></p><h2 id="lcp-lodge-h">Cazare la <?= v2_e($lcName) ?></h2></div>
      </div>
      <div class="lcp-grid">
        <div class="lcp-body">
          <?php if (!empty($lcLodging['description'])): ?><?= am_rich($lcLodging['description']) ?><?php endif; ?>
          <ul class="lcp-facts">
            <?php if (!empty($lcLodging['check_in']) || !empty($lcLodging['check_out'])): ?><li><?= v2_ic('clock') ?><span><?= v2_e(trim((!empty($lcLodging['check_in']) ? 'Check-in de la ' . $lcLodging['check_in'] : '') . (!empty($lcLodging['check_out']) ? (!empty($lcLodging['check_in']) ? ', check-out până la ' : 'Check-out până la ') . $lcLodging['check_out'] : ''))) ?></span></li><?php endif; ?>
            <?php if (!empty($lcLodging['price_from'])): ?><li><?= v2_ic('coins') ?><span>De la <?= v2_e(number_format((float) $lcLodging['price_from'], 0, ',', '.')) ?> lei / noapte</span></li><?php endif; ?>
            <?php if (!empty($lcLodging['phone'])): ?><li><?= v2_ic('phone') ?><a href="tel:<?= v2_e(preg_replace('/[^0-9+]/', '', $lcLodging['phone'])) ?>"><?= v2_e($lcLodging['phone']) ?></a></li><?php endif; ?>
            <?php if (!empty($lcLodging['email'])): ?><li><?= v2_ic('envelope-simple') ?><a href="mailto:<?= v2_e($lcLodging['email']) ?>"><?= v2_e($lcLodging['email']) ?></a></li><?php endif; ?>
          </ul>
          <?php if (!empty($lcLodging['policies'])): ?><div class="lcp-body lcp-policies"><?= am_rich($lcLodging['policies']) ?></div><?php endif; ?>
        </div>
        <div class="lcp-card">
          <?php $lgFac = am_facility_labels($lcLodging['facilities'] ?? [], AM_LODGING_FACILITIES); ?>
          <?php if ($lgFac): ?>
            <h3>Dotări</h3>
            <ul class="lcp-chips"><?php foreach ($lgFac as $f): ?><li><?= v2_e($f) ?></li><?php endforeach; ?></ul>
          <?php endif; ?>
          <?php if ($lgLinks): ?>
            <h3>Rezervă cazarea</h3>
            <div class="lcp-links">
              <?php foreach ($lgLinks as $l): ?>
              <a class="btn btn-ghost" href="<?= v2_e($l['url']) ?>" target="_blank" rel="nofollow noopener"><?= v2_e(trim((string) ($l['label'] ?? '')) ?: (AM_LINK_PLATFORMS[$l['platform'] ?? 'other'] ?? 'Rezervă')) ?><?= v2_ic('arrow-right') ?></a>
              <?php endforeach; ?>
            </div>
            <p class="bkx-small">Cazarea se rezervă direct la gazdă, pe platforma aleasă de ea. bilete.online nu intermediază plata cazării.</p>
          <?php endif; ?>
        </div>
      </div>
      <?php $lgRooms = array_values(array_filter((array) ($lcLodging['rooms'] ?? []), fn ($r) => is_array($r) && trim((string) ($r['name'] ?? '')) !== '')); ?>
      <?php if ($lgRooms): ?>
      <ul class="lcp-rooms" aria-label="Camere">
        <?php foreach ($lgRooms as $r): $rMeta = array_filter([!empty($r['capacity']) ? v2_num((int) $r['capacity'], 'persoană', 'persoane') : '', trim((string) ($r['beds'] ?? '')), !empty($r['count']) ? v2_num((int) $r['count'], 'cameră', 'camere') . ' de acest tip' : '']); ?>
        <li class="lcp-room">
          <b><?= v2_e($r['name']) ?></b>
          <?php if ($rMeta): ?><small><?= v2_e(implode(' · ', $rMeta)) ?></small><?php endif; ?>
          <?php if (!empty($r['description'])): ?><small><?= v2_e($r['description']) ?></small><?php endif; ?>
          <?php if (!empty($r['price_from'])): ?><p class="lcp-price">de la <?= v2_e(number_format((float) $r['price_from'], 0, ',', '.')) ?> lei / noapte</p><?php endif; ?>
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
        <h3 id="lcp-rules-h">Reguli de vizitare</h3>
        <div class="lcp-body"><?= am_rich($lcRules) ?></div>
      </div>
    </div>
  </section>
  <?php endif; ?>

  <!-- ===================== NEARBY ATTRACTIONS ===================== -->
  <?php if ($lcNearby): ?>
  <section class="lcp-sec" id="atractii" aria-labelledby="lcp-near-h">
    <div class="wrap">
      <div class="sec-head"><div><p class="kicker">În apropiere</p><h2 id="lcp-near-h">Atracții în zonă</h2></div></div>
      <ul class="lcp-near">
        <?php foreach ($lcNearby as $ni => $a): $aImg = v2_media_url($a['image'] ?? null); $aName = navFlatName($a['name'] ?? ''); ?>
        <li><a href="/atractie/<?= v2_e($a['slug']) ?>">
          <?php if ($aImg): ?><img src="<?= v2_e($aImg) ?>" alt="" loading="lazy" decoding="async"><?php else: ?><span class="lcp-near-ph" aria-hidden="true"></span><?php endif; ?>
          <span><b><?= v2_e($aName) ?></b><?php if (isset($a['distance_km'])): ?><small><?= v2_e(str_replace('.', ',', (string) round((float) $a['distance_km'], 1))) ?> km</small><?php elseif (!empty($a['subtitle'])): ?><small><?= v2_e($a['subtitle']) ?></small><?php endif; ?></span>
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
      <div class="sec-head"><div><p class="kicker">Întrebări</p><h2 id="lcp-faq-h">Întrebări frecvente</h2></div></div>
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
      <button class="icon-btn" type="button" data-lb="close"><?= v2_ic('x') ?><span class="sr">Închide galeria</span></button>
    </div>
    <figure class="lb-fig"><img id="lb-img" src="" alt=""></figure>
    <div class="lb-nav"<?= count($lightbox) < 2 ? ' hidden' : '' ?>>
      <button class="rail-btn" type="button" data-lb="prev" aria-label="Fotografia anterioară"><?= v2_ic('arrow-left') ?></button>
      <button class="rail-btn" type="button" data-lb="next" aria-label="Fotografia următoare"><?= v2_ic('arrow-right') ?></button>
    </div>
  </div>
  <?php endif; ?>
</main>
<?php include __DIR__ . '/footer.php'; ?>
