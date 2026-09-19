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
require_once __DIR__ . '/includes/v2/nav.php';

$atName       = $attraction['name'] ?? 'Atracție';
$atSubtitle   = $attraction['subtitle'] ?? '';
$atDesc       = $attraction['description'] ?? '';
$atType       = $attraction['type']['name'] ?? '';
$atTypeIcon   = $attraction['type']['icon'] ?? '';
$atCity       = $attraction['city'] ?? null;
$atCitySlug   = $atCity['slug'] ?? '';
$atCityName   = $atCity['name'] ?? '';
$atCounty     = $attraction['county'] ?? '';
$atCover      = v2_media_url($attraction['cover_image_url'] ?? null) ?? '';
$atGallery    = array_values(array_filter(array_map('v2_media_url', (array) ($attraction['gallery'] ?? []))));
$atAddress    = $attraction['address'] ?? '';
$atLat        = $attraction['latitude'] ?? null;
$atLng        = $attraction['longitude'] ?? null;
$atActivities = is_array($attraction['activities'] ?? null) ? array_values($attraction['activities']) : [];
$cityAttractions   = is_array($attraction['city_attractions'] ?? null) ? array_values($attraction['city_attractions']) : [];
$countyAttractions = is_array($attraction['county_attractions'] ?? null) ? array_values($attraction['county_attractions']) : [];

// Lightbox images: cover first (if present), then the gallery.
$lightbox = array_values(array_filter(array_merge($atCover ? [$atCover] : [], $atGallery)));

$durationLabel = function (int $m): string {
    if ($m <= 0) return '';
    if ($m < 60) return $m . ' min';
    $h = intdiv($m, 60); $rest = $m % 60;
    return $rest ? "{$h}h {$rest}m" : "{$h}h";
};
$pricedFromCents = function ($c): string {
    if (!$c) return '';
    return number_format($c / 100, 0, ',', '.') . ' lei';
};
$cardUrl = fn ($a) => '/experienta/' . ($a['slug'] ?? '');

$breadcrumbs = [['name' => 'Acasă', 'url' => SITE_URL . '/']];
if ($atCityName && $atCitySlug) {
    $breadcrumbs[] = ['name' => $atCityName, 'url' => SITE_URL . '/' . $atCitySlug];
}
$breadcrumbs[] = ['name' => $atName, 'url' => SITE_URL . '/atractie/' . $slug];

// Kicker line (type · city)
$kicker = trim($atType . ($atCityName ? ' · ' . $atCityName : ''), ' ·') ?: 'Atracție';

$pageTitleRaw = $atName . ($atCityName ? ' — ' . $atCityName : '') . ' | bilete.online';
$pageDescription = $atDesc !== '' ? mb_substr(trim(strip_tags($atDesc)), 0, 160) : ('Activități, tururi și bilete pentru ' . $atName . ($atCityName ? ' din ' . $atCityName : '') . '.');
$canonicalUrl = SITE_URL . '/atractie/' . $slug;
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
    'itemListElement' => array_map(fn ($bc, $i) => [
        '@type' => 'ListItem',
        'position' => $i + 1,
        'name' => $bc['name'],
        'item' => $bc['url'],
    ], $breadcrumbs, array_keys($breadcrumbs)),
]];

$mapsUrl = ($atLat && $atLng) ? 'https://www.google.com/maps/search/?api=1&query=' . urlencode($atLat . ',' . $atLng) : '';
$attrArches = '<svg class="deco-arches" viewBox="0 0 400 400" aria-hidden="true" focusable="false"><path d="M40 400V200a160 160 0 0 1 320 0v200"/><path d="M90 400V200a110 110 0 0 1 220 0v200"/><path d="M140 400V200a60 60 0 0 1 120 0v200"/></svg>';

// Row for the "other attractions" columns.
$renderAttractionRow = function (array $p, int $i) {
    $img = v2_media_url($p['cover_image_url'] ?? null);
    $name = $p['name'] ?? '';
    $meta = trim(($p['type']['name'] ?? '') . (!empty($p['city']['name']) ? ' · ' . $p['city']['name'] : ''), ' ·');
    ?>
    <li><a class="trow" href="/atractie/<?= v2_e($p['slug'] ?? '') ?>">
      <span class="trow-media"><?= $img ? v2_photo([$img, 0, 0, '']) : v2_fallback($name, $i) ?></span>
      <span class="trow-text"><b><?= v2_e($name) ?></b><?php if ($meta): ?><small><?= v2_e($meta) ?></small><?php endif; ?></span>
      <?= v2_ic('arrow-right') ?>
    </a></li>
    <?php
};

$v2Styles = ['attraction.css'];
$v2Scripts = ['attraction.js'];
$v2HeaderOverlay = true;
$v2HeadExtra = $lightbox ? '<link rel="preload" as="image" href="' . v2_e($lightbox[0]) . '" fetchpriority="high">' : '';
$v2ClientData = ['gallery' => array_map(fn ($src) => ['src' => $src, 'alt' => $atName], $lightbox)];

include __DIR__ . '/includes/v2/head.php';
include __DIR__ . '/includes/v2/header.php';
?>
<main id="main" tabindex="-1">
  <!-- ===================== HERO ===================== -->
  <section class="th" aria-labelledby="th-h">
    <?= $attrArches ?>
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
        <h1 class="th-h" id="th-h"><?= v2_e($atName) ?></h1>
        <?php if ($atSubtitle): ?><p class="th-sub"><?= v2_e($atSubtitle) ?></p><?php endif; ?>
        <?php if ($atType || $atAddress): ?>
        <ul class="th-chips">
          <?php if ($atType): ?><li><?php if ($atTypeIcon): ?><span aria-hidden="true"><?= v2_e($atTypeIcon) ?></span><?php endif; ?><?= v2_e($atType) ?></li><?php endif; ?>
          <?php if ($atAddress): ?><li><?= v2_ic('map-pin') ?><?= v2_e($atAddress) ?></li><?php endif; ?>
        </ul>
        <?php endif; ?>
        <div class="th-cta">
          <?php if (!empty($atActivities)): ?>
            <a class="btn btn-light" href="#activitati">Vezi ce poți face aici<?= v2_ic('arrow-right') ?></a>
          <?php elseif ($atCitySlug): ?>
            <a class="btn btn-light" href="/<?= v2_e($atCitySlug) ?>">Activități în <?= v2_e($atCityName) ?><?= v2_ic('arrow-right') ?></a>
          <?php endif; ?>
          <?php if ($mapsUrl): ?>
            <a class="btn btn-outline-light" href="<?= v2_e($mapsUrl) ?>" target="_blank" rel="noopener"><?= v2_ic('map-pin') ?>Deschide în Maps</a>
          <?php endif; ?>
        </div>
      </div>

      <div class="th-media">
        <?php if ($lightbox): ?>
        <button class="th-arch" type="button" data-gallery="0" aria-haspopup="dialog" aria-controls="lb" aria-label="Deschide galeria: <?= v2_e($atName) ?>">
          <img src="<?= v2_e($lightbox[0]) ?>" alt="<?= v2_e($atName) ?>" fetchpriority="high" decoding="async">
          <?php if (count($lightbox) > 1): ?><span class="th-gal"><?= v2_ic('magnifying-glass') ?>Vezi galeria (<?= count($lightbox) ?>)</span><?php endif; ?>
        </button>
        <?php else: ?>
        <div class="th-arch is-empty"><?= v2_fallback($atName) ?><?php if ($atCityName !== '' || $atType !== ''): ?><span class="th-arch-name" aria-hidden="true"><?php if ($atCityName !== '' && $atType !== ''): ?><small><?= v2_e($atType) ?></small><?php endif; ?><?= v2_e($atCityName !== '' ? $atCityName : $atType) ?></span><?php endif; ?></div>
        <?php endif; ?>
      </div>
    </div>
  </section>
  <div id="hdr-sentinel" aria-hidden="true"></div>

  <!-- ===================== ABOUT + MAP ===================== -->
  <?php if ($atDesc || count($lightbox) > 1 || $mapsUrl): ?>
  <section class="sec tabout" aria-labelledby="<?= $atDesc ? 'tabout-h' : 'th-h' ?>">
    <div class="wrap tabout-grid">
      <div>
        <?php if ($atDesc): ?>
          <p class="kicker">Despre</p>
          <h2 id="tabout-h">Despre <?= v2_e($atName) ?></h2>
          <div class="tabout-body"><?= nl2br(v2_e($atDesc)) ?></div>
        <?php endif; ?>

        <?php if (count($lightbox) > 1): ?>
          <ul class="tthumbs">
            <?php foreach (array_slice($lightbox, 1, 6) as $gi => $g): ?>
            <li><button type="button" data-gallery="<?= $gi + 1 ?>" aria-haspopup="dialog" aria-controls="lb"><img src="<?= v2_e($g) ?>" alt="<?= v2_e($atName) ?>" loading="lazy" decoding="async"></button></li>
            <?php endforeach; ?>
          </ul>
        <?php endif; ?>
      </div>

      <?php if ($mapsUrl): ?>
      <div class="tmap">
        <iframe title="Hartă <?= v2_e($atName) ?>" loading="lazy" referrerpolicy="no-referrer-when-downgrade" src="https://www.google.com/maps?q=<?= urlencode($atLat . ',' . $atLng) ?>&z=15&output=embed"></iframe>
        <a class="tmap-link" href="<?= v2_e($mapsUrl) ?>" target="_blank" rel="noopener"><?= v2_ic('map-pin') ?>Deschide în Google Maps<?= v2_ic('arrow-right') ?></a>
      </div>
      <?php endif; ?>
    </div>
  </section>
  <?php endif; ?>

  <!-- ===================== LINKED ACTIVITIES ===================== -->
  <section class="sec tact" id="activitati" aria-labelledby="tact-h">
    <div class="wrap">
      <div class="sec-head">
        <div><p class="kicker">Tururi și experiențe</p><h2 id="tact-h">Activități la <?= v2_e($atName) ?></h2></div>
        <?php if ($atCitySlug): ?><a class="sec-link" href="/<?= v2_e($atCitySlug) ?>">Toate activitățile din <?= v2_e($atCityName) ?><?= v2_ic('arrow-right') ?></a><?php endif; ?>
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
              <span class="xp-foot"><span class="xp-go"><?= v2_ic('arrow-right') ?></span><?php if (!empty($a['cheapest_price_cents'])): ?><span class="xp-price">de la<b><?= v2_e($pricedFromCents($a['cheapest_price_cents'])) ?></b></span><?php endif; ?></span>
            </span>
          </a>
        </li>
        <?php endforeach; ?>
      </ul>
      <?php else: ?>
      <div class="tempty">
        <h3>Momentan nu există activități direct asociate.</h3>
        <p>Descoperă experiențe și evenimente disponibile în <?= v2_e($atCityName ?: 'zonă') ?>.</p>
        <?php if ($atCitySlug): ?><a class="btn btn-light" href="/<?= v2_e($atCitySlug) ?>">Vezi activitățile din <?= v2_e($atCityName) ?><?= v2_ic('arrow-right') ?></a><?php endif; ?>
        <svg class="tempty-line" viewBox="0 590 3240 310" aria-hidden="true" focusable="false"><use href="#drum-g"/></svg>
      </div>
      <?php endif; ?>
    </div>
  </section>

  <!-- ===================== EXPLORE CITY CTA ===================== -->
  <?php if ($atCitySlug && $atCover): ?>
  <section class="texp" aria-labelledby="texp-h">
    <img src="<?= v2_e($atCover) ?>" alt="<?= v2_e($atCityName) ?>" loading="lazy" decoding="async">
    <div class="wrap texp-in">
      <p class="kicker">Explorează</p>
      <h2 id="texp-h"><?= v2_e($atCityName) ?></h2>
      <a class="btn btn-light" href="/<?= v2_e($atCitySlug) ?>">Toate activitățile din <?= v2_e($atCityName) ?><?= v2_ic('arrow-right') ?></a>
    </div>
  </section>
  <?php endif; ?>

  <!-- ===================== NEARBY ATTRACTIONS ===================== -->
  <?php if (!empty($cityAttractions) || !empty($countyAttractions)): ?>
  <section class="sec tnear" aria-label="Alte atracții">
    <?php readfile(__DIR__ . '/includes/v2/topo.svg'); ?>
    <div class="wrap tnear-grid">
      <?php if (!empty($cityAttractions)): ?>
      <div>
        <p class="kicker">În <?= v2_e($atCityName ?: 'oraș') ?></p>
        <h2>Alte atracții din oraș</h2>
        <ul class="trows">
          <?php foreach ($cityAttractions as $i => $p) { $renderAttractionRow($p, $i); } ?>
        </ul>
      </div>
      <?php endif; ?>

      <?php if (!empty($countyAttractions)): ?>
      <div>
        <p class="kicker"><?= $atCounty ? 'Județul ' . v2_e($atCounty) : 'În apropiere' ?></p>
        <h2>Atracții din apropiere</h2>
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
<?php include __DIR__ . '/includes/v2/footer.php'; ?>
