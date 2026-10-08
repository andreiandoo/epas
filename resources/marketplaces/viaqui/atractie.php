<?php
/**
 * Single attraction landing — /atractie/{slug} (v2 design).
 *
 * Pure render: expects $_GET['slug']. Pulls the attraction detail (name,
 * description, gallery, geo, type, city, county + linked activities + sibling
 * attractions in the same city/county) from the core API in a SINGLE call and
 * renders a POI page that cross-links to the activities and nearby
 * attractions. 404s cleanly when the slug isn't a real attraction.
 *
 * Top to bottom: hero (type · city, title, subtitle, type and address, actions, photo), about + gallery + map,
 * linked activities, "Explorează {oraș}" band, other attractions in the city and in the county, lightbox.
 */

$pageCacheTTL = 300;
require_once __DIR__ . '/includes/page-cache.php';
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/api.php';
require_once __DIR__ . '/includes/nav-helpers.php';

$slug = $slug ?? ($_GET['slug'] ?? '');
if (!is_string($slug) || !preg_match('/^[a-z][a-z0-9-]+$/', $slug)) {
    http_response_code(404);
    require __DIR__ . '/404.php';
    exit;
}

$resp = api_cached("attraction_{$slug}", fn () => api_get('/attractions/' . $slug), 300);
$attraction = $resp['data']['attraction'] ?? null;
if (!$attraction) {
    http_response_code(404);
    require __DIR__ . '/404.php';
    exit;
}

require_once __DIR__ . '/includes/v2/helpers.php';
require_once __DIR__ . '/includes/v2/places.php';
require_once __DIR__ . '/includes/v2/nav.php';
require_once __DIR__ . '/includes/v2/partners.php';

$atName       = $attraction['name'] ?? 'Attraction';
$atSubtitle   = $attraction['subtitle'] ?? '';
$atDesc       = $attraction['description'] ?? '';
$atType       = $attraction['type']['name'] ?? '';
$atTypeIcon   = $attraction['type']['icon'] ?? '';
$atCity       = $attraction['city'] ?? null;
$atCitySlug   = $atCity['slug'] ?? '';
/* Two thirds of the localities that hold attractions are not marketplace cities, so /{slug}
   answers 404 for them — the import creates them invisible precisely so that every attraction has
   a place. `has_page` says which ones do have a city page; when it is absent (an older API) we
   assume none, because /{slug}/atractii always exists for a locality that holds this attraction
   and a link that works beats a link that promises more. The section is never hidden: only its
   target and its wording change. */
$atCityHasPage = $atCitySlug !== '' && ($atCity['has_page'] ?? false);
$atCityPage    = $atCityHasPage ? '/' . $atCitySlug : ($atCitySlug !== '' ? '/' . $atCitySlug . '/attractions' : '');
$atCityThings  = $atCityHasPage ? 'things to do' : 'attractions';
$atCityName   = $atCity['name'] ?? '';
$atCounty     = $attraction['county'] ?? '';
$atCountryName = '';
$atCountrySlug = '';
foreach (($V2NAV['countriesFull'] ?? []) as $v2Country) {
    if (($v2Country['code'] ?? '') !== '' && $v2Country['code'] === strtoupper((string) ($attraction['country'] ?? ''))) {
        $atCountryName = (string) $v2Country['name'];
        $atCountrySlug = (string) $v2Country['slug'];
    }
}
$atCover      = v2_media_url($attraction['cover_image_url'] ?? null) ?? '';
/* CC BY-SA lets us publish the photo only next to the photographer's name, the licence and a way
   back to the original. Null for our own photos, which is most of them. */
$atCredit     = is_array($attraction['cover_credit'] ?? null) ? $attraction['cover_credit'] : null;
$atGallery    = array_values(array_filter(array_map('v2_media_url', (array) ($attraction['gallery'] ?? []))));
$atAddress    = $attraction['address'] ?? '';
$atLat        = $attraction['latitude'] ?? null;
$atLng        = $attraction['longitude'] ?? null;
$atActivities = is_array($attraction['activities'] ?? null) ? array_values($attraction['activities']) : [];
$cityAttractions   = is_array($attraction['city_attractions'] ?? null) ? array_values($attraction['city_attractions']) : [];
// What the import knows beyond the basics (core: attractions.facts, see plans/viaqui-data/build_facts.py).
$atFacts   = is_array($attraction['facts'] ?? null) ? $attraction['facts'] : [];
$atUnesco  = !empty($attraction['is_unesco']);
$atNative  = trim((string) ($atFacts['native_name'] ?? ''));
// Neighbours by distance, each with distance_km; places already shown in the city column are left out.
$atNearby  = is_array($attraction['nearby'] ?? null) ? array_values($attraction['nearby']) : [];
$atShownSlugs = array_column($cityAttractions, 'slug');
$atNearby  = array_values(array_filter($atNearby, fn ($n) => !in_array($n['slug'] ?? '', $atShownSlugs, true)));
// The facts as rows of a list: [icon, label, html]. Only what is known is printed.
$atFactRows = [];
if (!empty($atFacts['year'])) {
    $atYear = (int) $atFacts['year'];
    $atFactRows[] = ['calendar-blank', 'Built', v2_e($atYear < 0 ? abs($atYear) . ' BC' : (string) $atYear)];
}
if (!empty($atFacts['styles'])) {
    $atFactRows[] = ['buildings', count($atFacts['styles']) > 1 ? 'Styles' : 'Style', v2_e(ucfirst(implode(', ', array_map('strval', $atFacts['styles']))))];
}
if (!empty($atFacts['architects'])) {
    $atFactRows[] = ['user-circle', count($atFacts['architects']) > 1 ? 'Architects' : 'Architect', v2_e(implode(', ', array_map('strval', $atFacts['architects'])))];
}
if (!empty($atFacts['visitors'][0])) {
    $atVis = (int) $atFacts['visitors'][0];
    $atVisText = $atVis >= 1000000 ? rtrim(rtrim(number_format($atVis / 1000000, 1), '0'), '.') . ' million' : number_format(round($atVis / 1000) * 1000);
    $atFactRows[] = ['users-three', 'Visitors a year', v2_e($atVisText . (!empty($atFacts['visitors'][1]) ? ' (' . (int) $atFacts['visitors'][1] . ')' : ''))];
}
if (!empty($atFacts['hours'])) {
    $atFactRows[] = ['clock', 'Opening hours', v2_e(v2_opening_hours((string) $atFacts['hours'])) . ' <small>from OpenStreetMap; check before you go</small>'];
}
if (!empty($atFacts['website']) && preg_match('#^https?://#i', (string) $atFacts['website'])) {
    $atHost = preg_replace('/^www\./', '', (string) parse_url((string) $atFacts['website'], PHP_URL_HOST));
    $atFactRows[] = ['globe-simple', 'Official website', '<a href="' . v2_e($atFacts['website']) . '" target="_blank" rel="noopener nofollow">' . v2_e($atHost) . '</a>'];
}
if (!empty($atFacts['types'])) {
    $atAlso = array_values(array_filter(array_map(fn ($t) => V2_ATTRACTION_TYPES[$t] ?? '', (array) $atFacts['types'])));
    if ($atAlso) {
        $atFactRows[] = ['tag', 'Also listed as', v2_e(implode(', ', $atAlso))];
    }
}
$atGalleryCredits = array_values(array_filter((array) ($atFacts['gallery'] ?? []), fn ($g) => is_array($g) && !empty($g['license'])));
$countyAttractions = is_array($attraction['county_attractions'] ?? null) ? array_values($attraction['county_attractions']) : [];

/* Partner offers: self-guided audio tours (many with the entry ticket) that WeGoTrip sells for this attraction.
   Own listings come first; with none, the partner's fill the section instead of the "nothing listed" line. */
$atPartner = v2_wegotrip_products('attraction', (string) $slug, 4, (string) ($attraction['name'] ?? ''));
$atPartnerSub = 'attraction-' . $slug;

// Lightbox images: cover first (if present), then the gallery.
$lightbox = array_values(array_filter(array_merge($atCover ? [$atCover] : [], $atGallery)));

$mapsUrl = ($atLat && $atLng) ? 'https://www.google.com/maps/search/?api=1&query=' . urlencode($atLat . ',' . $atLng) : '';

/* ------------------------------------------------------------------ how much this page has to say
 * Most of the catalogue is a name, a point on the map and a sentence the importer wrote. A page
 * like that used to look exactly like a page with a history, a gallery and tickets: the same
 * full-height hero, the same tall portrait frame, the same "Despre" heading over one line of
 * generated text. Grade it instead, and give the thin ones a hero built for what they are —
 * a locator entry, where the map is the content and the photograph is the thing that is missing. */
$atDescText   = trim((string) $atDesc);
// The importer writes "Monument în Pojorâta, Suceava" into the subtitle: the kicker already says it.
$atBoilerSub  = false;   // Viaqui subtitles are Wikidata's one-line descriptions, never generated boilerplate
// ...and a description that admits it is a placeholder. Do not frame it as an article.
$atBoilerDesc = false;
$atRealDesc   = $atBoilerDesc ? '' : $atDescText;
$atLead       = $atBoilerSub ? '' : $atSubtitle;

$atRichness = (mb_strlen($atRealDesc) >= 600 ? 2 : (mb_strlen($atRealDesc) >= 220 ? 1 : 0))
    + (count($lightbox) >= 3 ? 2 : (count($lightbox) >= 1 ? 1 : 0))
    + ($atActivities || $atPartner['items'] ? 2 : 0);
$atCompact = $atRichness < 4;

// In the compact hero the lead is whatever real prose exists; the "Despre" section then only
// earns its heading when there is more than the hero already showed.
$atHeroLead = $atLead;
// (text that carries a credit, such as a Wikipedia introduction, always goes in the section below, where the credit is)
if ($atCompact && $atHeroLead === '' && $atRealDesc !== '' && mb_strlen($atRealDesc) <= 320 && empty($attraction['description_credit'])) {
    $atHeroLead = $atRealDesc;   // short enough to be the whole story; no section repeats it
}
$atShowAbout = $atRealDesc !== '' && $atHeroLead !== $atRealDesc;
// With no photograph, the map takes the frame: for a monument, where it is IS the content.
$atHeroMap   = $atCompact && !$lightbox && $mapsUrl !== '';
$atShowMapSection = $mapsUrl !== '' && !$atHeroMap;
$atCoords = ($atLat && $atLng) ? number_format((float) $atLat, 4, '.', '') . ', ' . number_format((float) $atLng, 4, '.', '') : '';

$durationLabel = function (int $m): string {
    if ($m <= 0) return '';
    if ($m < 60) return $m . ' min';
    $h = intdiv($m, 60); $rest = $m % 60;
    return $rest ? "{$h}h {$rest}m" : "{$h}h";
};
$pricedFromCents = function ($c): string {
    if (!$c) return '';
    return v2_money($c / 100);
};
$cardUrl = fn ($a) => '/experience/' . ($a['slug'] ?? '');

$breadcrumbs = [['name' => 'Home', 'url' => SITE_URL . '/']];
if ($atCountryName !== '') {
    $breadcrumbs[] = ['name' => $atCountryName, 'url' => SITE_URL . '/' . $atCountrySlug];
}
if ($atCityName) {
    $breadcrumbs[] = ['name' => $atCityName, 'url' => $atCityPage !== '' ? SITE_URL . $atCityPage : null];
}
$breadcrumbs[] = ['name' => $atName, 'url' => SITE_URL . '/attraction/' . $slug];

// Kicker line (type · city)
$kicker = trim($atType . ($atCityName ? ' · ' . $atCityName : ''), ' ·') ?: 'Attraction';

$pageTitleRaw = $atName . ($atCityName ? ', ' . $atCityName : ($atCountryName !== '' ? ', ' . $atCountryName : '')) . ' | Viaqui';
$pageDescription = $atDesc !== '' ? mb_substr(trim(strip_tags($atDesc)), 0, 160) : trim(($atSubtitle !== '' ? ucfirst($atSubtitle) . '. ' : '') . 'Where ' . $atName . ' is, what is around it' . ($atCityName ? ' in ' . $atCityName : '') . ' and what you can book nearby.');
$canonicalUrl = SITE_URL . '/attraction/' . $slug;
$ogImage = $atCover ?: (SITE_URL . '/assets/images/og-default.jpg');

$structuredData = [[
    '@context' => 'https://schema.org',
    '@type' => 'TouristAttraction',
    'name' => $atName,
    'description' => $pageDescription,
    'url' => $canonicalUrl,
    'image' => $atCover ?: null,
    'address' => $atAddress ? ['@type' => 'PostalAddress', 'streetAddress' => $atAddress, 'addressLocality' => $atCityName, 'addressRegion' => $atCounty] : null,
    'geo' => ($atLat && $atLng) ? ['@type' => 'GeoCoordinates', 'latitude' => $atLat, 'longitude' => $atLng] : null,
], [
    '@context' => 'https://schema.org',
    '@type' => 'BreadcrumbList',
    'itemListElement' => (function () use ($breadcrumbs) {
        $steps = array_values(array_filter($breadcrumbs, fn ($bc) => !empty($bc['url'])));
        return array_map(fn ($bc, $i) => [
            '@type' => 'ListItem',
            'position' => $i + 1,
            'name' => $bc['name'],
            'item' => $bc['url'],
        ], $steps, array_keys($steps));
    })(),
]];

$attrArches = '<svg class="deco-arches" viewBox="0 0 400 400" aria-hidden="true" focusable="false"><path d="M40 400V200a160 160 0 0 1 320 0v200"/><path d="M90 400V200a110 110 0 0 1 220 0v200"/><path d="M140 400V200a60 60 0 0 1 120 0v200"/></svg>';

// Row for the "other attractions" columns.
$renderAttractionRow = function (array $p, int $i) {
    $img = v2_media_url($p['cover_image_url'] ?? null);
    $name = $p['name'] ?? '';
    $meta = trim(($p['type']['name'] ?? '') . (!empty($p['city']['name']) ? ' · ' . $p['city']['name'] : ''), ' ·');
    if (isset($p['distance_km'])) {
        $km = (float) $p['distance_km'];
        $meta = trim($meta . ' · ' . ($km < 1 ? (max(1, (int) round($km * 10)) * 100) . ' m' : rtrim(rtrim(number_format($km, 1), '0'), '.') . ' km') . ' away', ' ·');
    }
    ?>
    <li><a class="trow" href="/attraction/<?= v2_e($p['slug'] ?? '') ?>">
      <span class="trow-media"><?= $img ? v2_photo([v2_thumb($img, 240, 240), 0, 0, '']) : v2_fallback($name, $i) ?></span>
      <span class="trow-text"><b><?= v2_e($name) ?></b><?php if ($meta): ?><small><?= v2_e($meta) ?></small><?php endif; ?></span>
      <?= v2_ic('arrow-right') ?>
    </a></li>
    <?php
};

$v2Styles = ['attraction.css'];
$v2Scripts = ['attraction.js', 'trip-list.js'];
$v2HeaderOverlay = true;
$v2HeadExtra = $lightbox ? '<link rel="preload" as="image" href="' . v2_e($lightbox[0]) . '" fetchpriority="high">' : '';
$v2ClientData = ['gallery' => array_map(fn ($src) => ['src' => $src, 'alt' => $atName], $lightbox)];

include __DIR__ . '/includes/v2/head.php';
include __DIR__ . '/includes/v2/header.php';
?>
<main id="main" tabindex="-1">
  <!-- ===================== HERO ===================== -->
  <section class="th<?= $atCompact ? ' is-compact' : '' ?>" aria-labelledby="th-h">
    <?= $attrArches ?>
    <svg class="th-line draw-clip" viewBox="0 590 3240 310" aria-hidden="true" focusable="false"><use href="#drum-g"/></svg>
    <div class="th-in">
      <div class="th-copy">
        <nav class="crumbs" aria-label="Breadcrumb">
          <?php foreach ($breadcrumbs as $i => $bc): ?>
            <?php if ($i > 0): ?><span aria-hidden="true">/</span><?php endif; ?>
            <?php if ($i < count($breadcrumbs) - 1 && !empty($bc['url'])): ?><a href="<?= v2_e(substr($bc['url'], strlen(SITE_URL)) ?: '/') ?>"><?= v2_e($bc['name']) ?></a><?php elseif ($i < count($breadcrumbs) - 1): ?><span><?= v2_e($bc['name']) ?></span><?php else: ?><span aria-current="page"><?= v2_e($bc['name']) ?></span><?php endif; ?>
          <?php endforeach; ?>
        </nav>
        <p class="th-kicker"><?= v2_e($kicker) ?></p>
        <h1 class="th-h" id="th-h"><?= v2_e($atName) ?></h1>
        <?php if ($atNative !== ''): ?><p class="th-native" lang="<?= v2_e(strtolower((string) ($attraction['country'] ?? ''))) ?>"><?= v2_e($atNative) ?></p><?php endif; ?>
        <?php if ($atUnesco): ?><p class="th-unesco"><?= v2_ic('star') ?>UNESCO World Heritage Site</p><?php endif; ?>
        <?php if ($atHeroLead !== ''): ?><p class="th-sub"><?= v2_e($atHeroLead) ?></p><?php endif; ?>

        <?php if ($atCompact): ?>
        <ul class="th-facts">
          <?php if ($atType !== ''): ?><li><?= v2_ic('tag') ?><span><b>Type</b><span><?= v2_e($atType) ?></span></span></li><?php endif; ?>
          <?php if ($atCityName !== '' || $atCounty !== '' || $atCountryName !== ''): ?><li><?= v2_ic('buildings') ?><span><b>Where</b><span><?= v2_e(implode(', ', array_filter([$atCityName, $atCounty, $atCountryName]))) ?></span></span></li><?php endif; ?>
          <?php if ($atAddress !== ''): ?><li><?= v2_ic('map-pin') ?><span><b>Address</b><span><?= v2_e($atAddress) ?></span></span></li><?php endif; ?>
        </ul>
        <?php elseif ($atType || $atAddress): ?>
        <ul class="th-chips">
          <?php if ($atType): ?><li><?= v2_e($atType) ?></li><?php endif; ?>
          <?php if ($atAddress): ?><li><?= v2_ic('map-pin') ?><?= v2_e($atAddress) ?></li><?php endif; ?>
        </ul>
        <?php endif; ?>

        <div class="th-cta">
          <?php if (!empty($atActivities) || $atPartner['items']): ?>
            <a class="btn btn-light" href="#things-to-do">See what you can do here<?= v2_ic('arrow-right') ?></a>
          <?php elseif ($atCityPage): ?>
            <a class="btn btn-light" href="<?= v2_e($atCityPage) ?>" title="<?= $atCityHasPage ? 'Things to do' : 'Attractions' ?> in <?= v2_e($atCityName) ?>"><span class="th-cta-t">Explore <?= v2_e($atCityName) ?></span><?= v2_ic('arrow-right') ?></a>
          <?php endif; ?>
          <?php if ($atCitySlug !== ''): ?>
            <a class="btn btn-outline-light" href="/map?city=<?= v2_e($atCitySlug) ?>"><?= v2_ic('globe-simple') ?>On the map</a>
          <?php endif; ?>
          <?php
          // "Add to your trip": kept in the browser (assets/v2/js/trip-list.js) and shown on /plan, where the planner of the
          // country turns the saved places into days. The photo is passed as the bare Commons file name when it is one.
          $atTripImg = v2_commons_name((string) $atCover) ?? (string) $atCover;
          $atTrip = ['s' => (string) $slug, 'n' => (string) $atName, 'c' => $atCountrySlug, 'cn' => $atCountryName, 'city' => (string) $atCityName, 't' => (string) $atType, 'img' => $atTripImg];
          ?>
          <button class="btn btn-outline-light th-trip" type="button" data-trip-add data-trip="<?= v2_e(json_encode($atTrip, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)) ?>" aria-pressed="false"><?= v2_ic('heart') ?><span data-trip-label>Add to your trip</span></button>
        </div>
        <p class="th-tripnote" data-trip-note hidden><a href="/plan#your-trip-list">See your trip list</a></p>
      </div>

      <div class="th-media">
        <?php if ($lightbox): ?>
        <button class="th-arch" type="button" data-gallery="0" aria-haspopup="dialog" aria-controls="lb" aria-label="Open the gallery: <?= v2_e($atName) ?>">
          <img src="<?= v2_e($atCompact ? v2_thumb($lightbox[0], 960, 600) : $lightbox[0]) ?>" alt="<?= v2_e($atName) ?>" fetchpriority="high" decoding="async">
          <?php if (count($lightbox) > 1): ?><span class="th-gal"><?= v2_ic('magnifying-glass') ?>See the gallery (<?= count($lightbox) ?>)</span><?php endif; ?>
        </button>
        <?php if ($atCredit): ?>
        <p class="th-credit">Photo: <?= v2_e(($atCredit['author'] ?? '') !== '' ? $atCredit['author'] : 'unknown author') ?><?php if (!empty($atCredit['license'])): ?> · <?php if (!empty($atCredit['license_url'])): ?><a href="<?= v2_e($atCredit['license_url']) ?>" target="_blank" rel="noopener nofollow license"><?= v2_e($atCredit['license']) ?></a><?php else: ?><?= v2_e($atCredit['license']) ?><?php endif; ?><?php endif; ?><?php $atCreditUrl = $atCredit['source_url'] ?? (preg_match('#^https?://#', (string) ($atCredit['source'] ?? '')) ? $atCredit['source'] : ''); if ($atCreditUrl !== ''): ?> · <a href="<?= v2_e($atCreditUrl) ?>" target="_blank" rel="noopener nofollow"><?= v2_e(!empty($atCredit['source_url']) && !empty($atCredit['source']) ? $atCredit['source'] : 'source') ?></a><?php endif; ?></p>
        <?php endif; ?>
        <?php elseif ($atHeroMap): ?>
        <div class="th-map">
          <iframe title="Map: <?= v2_e($atName) ?>" loading="lazy" referrerpolicy="no-referrer-when-downgrade" src="https://www.google.com/maps?q=<?= urlencode($atLat . ',' . $atLng) ?>&z=14&output=embed"></iframe>
          <a class="th-map-link" href="<?= v2_e($mapsUrl) ?>" target="_blank" rel="noopener"><?= v2_ic('map-pin') ?>Open in Google Maps<?= v2_ic('arrow-right') ?></a>
        </div>
        <?php else: ?>
        <div class="th-arch is-empty"><?= v2_fallback($atName) ?><?php if ($atCityName !== '' || $atType !== ''): ?><span class="th-arch-name" aria-hidden="true"><?php if ($atCityName !== '' && $atType !== ''): ?><small><?= v2_e($atType) ?></small><?php endif; ?><?= v2_e($atCityName !== '' ? $atCityName : $atType) ?></span><?php endif; ?></div>
        <?php endif; ?>
      </div>
    </div>
  </section>
  <div id="hdr-sentinel" aria-hidden="true"></div>

  <!-- ===================== ABOUT + MAP ===================== -->
  <?php if ($atShowAbout || count($lightbox) > 1 || $atShowMapSection): ?>
  <section class="sec tabout<?= $atShowAbout ? '' : ' is-slim' ?>" aria-labelledby="<?= $atShowAbout ? 'tabout-h' : 'th-h' ?>">
    <div class="wrap tabout-grid<?= $atShowMapSection ? '' : ' is-single' ?>">
      <div>
        <?php if ($atShowAbout): ?>
          <p class="kicker">About</p>
          <h2 id="tabout-h">About <?= v2_e($atName) ?></h2>
          <div class="tabout-body"><?php foreach (v2_paragraphs($atRealDesc) as $atPi => $atP): ?><p<?= $atPi === 0 ? ' class="tabout-lead"' : '' ?>><?= v2_e($atP) ?></p><?php endforeach; ?></div>
          <?php if (is_array($attraction['description_credit'] ?? null) && !empty($attraction['description_credit']['source_url'])): $atTextCredit = $attraction['description_credit']; ?>
          <p class="th-credit tabout-credit">Text from <a href="<?= v2_e($atTextCredit['source_url']) ?>" target="_blank" rel="noopener nofollow"><?= v2_e($atTextCredit['source'] ?? 'the source') ?></a><?php if (!empty($atTextCredit['license'])): ?>, available under <a href="<?= v2_e($atTextCredit['license_url'] ?? '#') ?>" target="_blank" rel="noopener nofollow license"><?= v2_e($atTextCredit['license']) ?></a><?php endif; ?>.</p>
          <?php endif; ?>
        <?php endif; ?>

        <?php if (count($lightbox) > 1): ?>
          <ul class="tthumbs">
            <?php foreach (array_slice($lightbox, 1, 6) as $gi => $g): ?>
            <li><button type="button" data-gallery="<?= $gi + 1 ?>" aria-haspopup="dialog" aria-controls="lb"><img src="<?= v2_e(v2_thumb($g, 480, 320)) ?>" alt="<?= v2_e($atName) ?>" loading="lazy" decoding="async"></button></li>
            <?php endforeach; ?>
          </ul>
          <?php if ($atGalleryCredits): ?>
          <p class="th-credit tabout-credit">Photos: <?php foreach ($atGalleryCredits as $gci => $gc): ?><?= $gci ? ' · ' : '' ?><a href="<?= v2_e($gc['source_url'] ?? '#') ?>" target="_blank" rel="noopener nofollow"><?= v2_e(($gc['author'] ?? '') !== '' ? $gc['author'] : 'unknown author') ?></a> (<?= v2_e($gc['license']) ?>)<?php endforeach; ?>, Wikimedia Commons.</p>
          <?php endif; ?>
        <?php endif; ?>

        <?php if ($atFactRows): ?>
        <div class="tfacts">
          <h2 class="tfacts-h">Good to know</h2>
          <dl>
            <?php foreach ($atFactRows as [$fIcon, $fLabel, $fHtml]): ?>
            <div><dt><?= v2_ic($fIcon) ?><?= v2_e($fLabel) ?></dt><dd><?= $fHtml ?></dd></div>
            <?php endforeach; ?>
          </dl>
        </div>
        <?php endif; ?>
      </div>

      <?php if ($atShowMapSection): ?>
      <div class="tmap">
        <iframe title="Map: <?= v2_e($atName) ?>" loading="lazy" referrerpolicy="no-referrer-when-downgrade" src="https://www.google.com/maps?q=<?= urlencode($atLat . ',' . $atLng) ?>&z=15&output=embed"></iframe>
        <a class="tmap-link" href="<?= v2_e($mapsUrl) ?>" target="_blank" rel="noopener"><?= v2_ic('map-pin') ?>Open in Google Maps<?= v2_ic('arrow-right') ?></a>
      </div>
      <?php endif; ?>
    </div>
  </section>
  <?php endif; ?>

  <!-- ===================== LINKED ACTIVITIES ===================== -->
  <?php if (!empty($atActivities) || !empty($atPartner['items'])): // with nothing to book here the section would only say so ?>
  <section class="sec tact" id="things-to-do" aria-labelledby="tact-h">
    <div class="wrap">
      <div class="sec-head">
        <div><p class="kicker">Tours and experiences</p><h2 id="tact-h">Things to do at <?= v2_e($atName) ?></h2></div>
        <?php if ($atCityPage): ?><a class="sec-link" href="<?= v2_e($atCityPage) ?>">All <?= v2_e($atCityThings) ?> in <?= v2_e($atCityName) ?><?= v2_ic('arrow-right') ?></a><?php endif; ?>
      </div>

      <?php if (!empty($atActivities)): ?>
      <ul class="xp-grid" data-reveal>
        <?php foreach ($atActivities as $ai => $a): $aImg = v2_media_url($a['cover_image_url'] ?? null); ?>
        <li class="xp">
          <a href="<?= v2_e($cardUrl($a)) ?>">
            <span class="xp-media"><?= $aImg ? v2_photo([$aImg, 0, 0, '']) : v2_fallback($a['title'] ?? '', $ai) ?><?php if (!empty($a['category']['name'])): ?><span class="xp-badges"><span><?= v2_e($a['category']['name']) ?></span></span><?php endif; ?></span>
            <span class="xp-body">
              <span class="xp-title"><?= v2_e($a['title'] ?? '') ?></span>
              <span class="xp-meta"><?php if (!empty($a['city']['name'])): ?><span><?= v2_ic('map-pin') ?><?= v2_e($a['city']['name']) ?></span><?php endif; ?><?php if (!empty($a['duration_minutes'])): ?><span><?= v2_ic('clock') ?><?= v2_e($durationLabel((int) $a['duration_minutes'])) ?></span><?php endif; ?></span>
              <span class="xp-foot"><span class="xp-go"><?= v2_ic('arrow-right') ?></span><?php if (!empty($a['cheapest_price_cents'])): ?><span class="xp-price">from<b><?= v2_e(v2_own_price_label($a['cheapest_price_cents'], $a['currency'] ?? null, $a['cheapest_price_eur_cents'] ?? null)) ?></b></span><?php endif; ?></span>
            </span>
          </a>
        </li>
        <?php endforeach; ?>
      </ul>
      <?php elseif ($atPartner['items']): ?>
      <ul class="xp-grid" data-reveal><?= v2_partner_cards($atPartner['items'], 'wegotrip', $atPartnerSub) ?></ul>
      <?= v2_partner_note('wegotrip') ?>
      <?php else: ?>
      <p class="tnone"><?= v2_ic('info') ?>Nothing with tickets is listed at <?= v2_e($atName) ?> yet.<?php if ($atCityPage): ?> <a href="<?= v2_e($atCityPage) ?>">See what else there is in <?= v2_e($atCityName) ?><?= v2_ic('arrow-right') ?></a><?php endif; ?></p>
      <?php endif; ?>

      <?php if (!empty($atActivities) && $atPartner['items']): ?>
      <div class="partner-more">
        <h3>Audio tours and tickets from our partner</h3>
        <ul class="xp-grid" data-reveal><?= v2_partner_cards($atPartner['items'], 'wegotrip', $atPartnerSub) ?></ul>
        <?= v2_partner_note('wegotrip') ?>
      </div>
      <?php endif; ?>
    </div>
  </section>
  <?php endif; ?>

  <!-- ===================== EXPLORE CITY CTA ===================== -->
  <?php if ($atCityPage && $atCover): ?>
  <section class="texp" aria-labelledby="texp-h">
    <img src="<?= v2_e($atCover) ?>" alt="<?= v2_e($atCityName) ?>" loading="lazy" decoding="async">
    <div class="wrap texp-in">
      <p class="kicker">Explore</p>
      <h2 id="texp-h"><?= v2_e($atCityName) ?></h2>
      <a class="btn btn-light" href="<?= v2_e($atCityPage) ?>">All <?= v2_e($atCityThings) ?> in <?= v2_e($atCityName) ?><?= v2_ic('arrow-right') ?></a>
    </div>
  </section>
  <?php endif; ?>

  <!-- ===================== NEARBY ATTRACTIONS ===================== -->
  <?php if (empty($countyAttractions) && $atNearby) { $countyAttractions = array_slice($atNearby, 0, 6); $atCounty = ''; } // by distance, where the catalogue has no counties ?>
  <?php if (!empty($cityAttractions) || !empty($countyAttractions)): ?>
  <section class="sec tnear" aria-label="Other attractions">
    <?php readfile(__DIR__ . '/includes/v2/topo.svg'); ?>
    <div class="wrap tnear-grid">
      <?php if (!empty($cityAttractions)): ?>
      <div>
        <p class="kicker">In <?= v2_e($atCityName ?: 'the city') ?></p>
        <h2>More to see here</h2>
        <ul class="trows">
          <?php foreach ($cityAttractions as $i => $p) { $renderAttractionRow($p, $i); } ?>
        </ul>
      </div>
      <?php endif; ?>

      <?php if (!empty($countyAttractions)): ?>
      <div>
        <p class="kicker"><?= $atCounty ? v2_e($atCounty) : 'Nearby' ?></p>
        <h2>Attractions nearby</h2>
        <ul class="trows">
          <?php foreach ($countyAttractions as $i => $p) { $renderAttractionRow($p, $i + 1); } ?>
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
      <p class="lb-title" id="lb-title"><?= v2_e($atName) ?></p>
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
<?php include __DIR__ . '/includes/v2/footer.php'; ?>
