<?php
/**
 * Single venue: /locatie/{slug} (v2 design).
 *
 * Pure render: expects $_GET['slug']. Reads the venue from `GET /venues/{slug}` and 404s cleanly when the
 * slug doesn't match. Understands both the core API shape (city as plain text, cover_image, schedule,
 * coordinates, similar_venues) and the older one the page was first built on (city {name, slug},
 * cover_image_url, opening_hours, rating, activities), whichever the response carries.
 *
 * Top to bottom: hero (type · city, title, lead, stats, photo carousel), sticky section nav, activities with
 * the venue card, about, program & address with the map, FAQ, similar venues, final CTA, gallery lightbox.
 * Hero, map, empty state and lightbox come from attraction.css + attraction.js (the same family of place pages).
 */

$pageCacheTTL = 300;
require_once __DIR__ . '/includes/page-cache.php';
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/api.php';
require_once __DIR__ . '/includes/nav-helpers.php';

$slug = $_GET['slug'] ?? '';
if (!is_string($slug) || !preg_match('/^[a-z][a-z0-9-]+$/', $slug)) {
    http_response_code(404);
    require __DIR__ . '/404.php';
    exit;
}

$venueResp = api_cached("venue_detail_{$slug}", fn () => api_get('/venues/' . urlencode($slug)), 300);
$venue = $venueResp['data']['venue'] ?? $venueResp['data'] ?? null;
if (!is_array($venue) || empty($venue['name'])) {
    http_response_code(404);
    require __DIR__ . '/404.php';
    exit;
}

require_once __DIR__ . '/includes/v2/helpers.php';
require_once __DIR__ . '/includes/v2/nav.php';

// Plain text from a scalar, a translations array or a {name} object.
$vnText = function ($v): string {
    if (is_array($v) && array_key_exists('name', $v)) {
        $v = $v['name'];
    }
    if (is_array($v)) {
        $v = $v['ro'] ?? $v['en'] ?? reset($v);
    }
    return is_scalar($v) ? trim((string) $v) : '';
};

// Prose without markup (headings left out, blocks spaced, inline tags joined), cut at a word boundary.
$vnExcerpt = function (string $text, int $max): string {
    $text = preg_replace('#<(script|style|h[1-6])\b[^>]*>.*?</\1\s*>#is', ' ', $text);
    $text = preg_replace(['#</li>\s*</(?:ul|ol)>#i', '#</li>#i'], ['. ', '; '], $text); // list items read as a sentence
    $text = preg_replace('#<(?:br|/p|/div|/blockquote|/ul|/ol)\b[^>]*>#i', ' ', $text);
    $text = trim(preg_replace('/\s+/u', ' ', html_entity_decode(strip_tags($text), ENT_QUOTES | ENT_HTML5, 'UTF-8')));
    if (mb_strlen($text) <= $max) {
        return $text;
    }
    $cut = mb_substr($text, 0, $max);
    $space = mb_strrpos($cut, ' ');
    return rtrim($space !== false && $space > $max * 0.6 ? mb_substr($cut, 0, $space) : $cut, ' ,.;:–-') . '…';
};

// Descriptions come from the admin rich editor: keep the text formatting, drop scripts, styles and attributes.
$vnRich = function (string $html): string {
    $html = trim(preg_replace('#<(script|style|iframe|object|embed|noscript|template)\b[^>]*>.*?</\1\s*>#is', '', $html));
    if ($html === strip_tags($html)) {
        return implode('', array_map(fn ($p) => '<p>' . nl2br(v2_e(trim($p))) . '</p>', preg_split('/\R\s*\R/u', $html)));
    }
    $html = strip_tags($html, '<p><br><strong><b><em><i><u><ul><ol><li><a><h2><h3><h4><blockquote>');
    $html = preg_replace_callback('#<(/?)([a-z0-9]+)\b([^>]*)>#i', function ($m) {
        $tag = strtolower($m[2]);
        if ($tag === 'h2') {
            $tag = 'h3'; // the description sits under the section's own h2
        }
        if ($m[1] === '/') {
            return '</' . $tag . '>';
        }
        if ($tag === 'a') {
            if (preg_match('#\bhref\s*=\s*(["\'])((?:https?://|/|mailto:|tel:)[^"\']*)\1#i', $m[3], $h)) {
                $href = html_entity_decode($h[2], ENT_QUOTES | ENT_HTML5, 'UTF-8');
                return '<a href="' . v2_e($href) . '"' . (preg_match('#^https?://#i', $href) ? ' target="_blank" rel="noopener nofollow"' : '') . '>';
            }
            return ''; // no safe link: keep the text only (the stray </a> is ignored by the parser)
        }
        return '<' . $tag . '>';
    }, $html);
    return trim($html);
};

$vnPrice = function (int $cents): string {
    return number_format($cents / 100, $cents % 100 === 0 ? 0 : 2, ',', '.') . ' lei';
};

// ------------------------------------------------------------------ venue
$name = $vnText($venue['name']);
$cityRaw = $venue['city'] ?? null;
$cityName = is_array($cityRaw) ? $vnText($cityRaw) : $vnText($venue['city_name'] ?? $cityRaw);
$citySlug = is_array($cityRaw) ? (string) ($cityRaw['slug'] ?? '') : (string) ($venue['city_slug'] ?? '');
if ($citySlug === '' && $cityName !== '') {
    // The core API sends the city as plain text: link it only when it is one of the site's cities.
    $cityGuess = trim(preg_replace('/[^a-z0-9]+/', '-', mb_strtolower(strtr($cityName, [
        'ă' => 'a', 'â' => 'a', 'î' => 'i', 'ș' => 's', 'ş' => 's', 'ț' => 't', 'ţ' => 't',
        'Ă' => 'a', 'Â' => 'a', 'Î' => 'i', 'Ș' => 's', 'Ş' => 's', 'Ț' => 't', 'Ţ' => 't',
    ]))), '-');
    if (isset($V2NAV['cities'][$cityGuess])) {
        $citySlug = $cityGuess;
    }
}
if (!preg_match('/^[a-z0-9-]*$/', $citySlug)) {
    $citySlug = '';
}
$county = $vnText($venue['state'] ?? '');
$address = $vnText($venue['address'] ?? '');
$rating = is_numeric($venue['rating'] ?? null) && $venue['rating'] > 0 ? (float) $venue['rating'] : null;
$reviewsCount = (int) ($venue['reviews_count'] ?? 0);

$typeLabels = [
    'escape_room' => 'Escape room', 'museum' => 'Muzeu', 'park' => 'Parc',
    'adventure_park' => 'Parc aventură', 'workshop' => 'Atelier', 'tour' => 'Tur ghidat',
    'aquarium' => 'Acvariu', 'zoo' => 'Grădină zoologică', 'cave' => 'Peșteră',
    'leisure_venue' => 'Centru de agrement',
];
$vnType = function (array $v) use ($typeLabels, $vnText): string {
    $raw = $v['type'] ?? $v['venue_type'] ?? '';
    if (is_string($raw) && $raw !== '') {
        return $typeLabels[$raw] ?? ucfirst(str_replace('_', ' ', $raw));
    }
    $cats = is_array($v['categories'] ?? null) ? array_values($v['categories']) : [];
    return $vnText($raw) ?: ($cats ? $vnText($cats[0]) : '') ?: 'Locație';
};
$typeLabel = $vnType($venue);

$descRaw = $vnText($venue['description'] ?? '');
$descHtml = $descRaw !== '' ? $vnRich($descRaw) : '';
$hasAbout = trim(strip_tags($descHtml)) !== '';
$shortDescription = $vnText($venue['short_description'] ?? '');
$lead = $shortDescription !== '' ? $vnExcerpt($shortDescription, 260) : ($descRaw !== '' ? $vnExcerpt($descRaw, 220) : '');

// Photos: cover first, then the gallery. The hero shows up to 6, the lightbox all of them.
$coverImage = v2_media_url($venue['cover_image_url'] ?? $venue['cover_image'] ?? $venue['image'] ?? null);
$photos = $coverImage ? [$coverImage] : [];
foreach ((array) ($venue['gallery'] ?? []) as $photo) {
    $photo = v2_media_url(is_array($photo) ? ($photo['url'] ?? $photo['src'] ?? null) : $photo);
    if ($photo && !in_array($photo, $photos, true)) {
        $photos[] = $photo;
    }
}
$photos = array_slice($photos, 0, 20);
$heroPhotos = array_slice($photos, 0, 6);

// Opening hours: free text in the admin ("Luni - Vineri: 10:00 - 22:00" per line), or a list.
$hoursRaw = $venue['opening_hours'] ?? $venue['schedule'] ?? null;
$hours = [];
if (is_string($hoursRaw)) {
    $hours = preg_split('/\R/u', $hoursRaw);
} elseif (is_array($hoursRaw)) {
    foreach ($hoursRaw as $day => $time) {
        $hours[] = (is_string($day) ? $day . ': ' : '') . $vnText($time);
    }
}
$hours = array_values(array_filter(array_map('trim', $hours), 'strlen'));

$lat = $venue['latitude'] ?? $venue['lat'] ?? null;
$lng = $venue['longitude'] ?? $venue['lng'] ?? null;
$hasGeo = is_numeric($lat) && is_numeric($lng) && ((float) $lat != 0.0 || (float) $lng != 0.0);
$googleMaps = (string) ($venue['google_maps_url'] ?? '');
$mapsQuery = $hasGeo ? $lat . ',' . $lng : trim($address . ($address !== '' && $cityName !== '' ? ', ' : '') . ($address !== '' ? $cityName : ''));
$mapsUrl = preg_match('#^https?://\S+$#i', $googleMaps) ? $googleMaps
    : ($mapsQuery !== '' ? 'https://www.google.com/maps/search/?api=1&query=' . rawurlencode($mapsQuery) : '');

// ------------------------------------------------------------------ activities
$normalizedActivities = [];
$cheapestCents = null;
foreach ((array) ($venue['activities'] ?? []) as $a) {
    if (!is_array($a)) {
        continue;
    }
    $aTitle = $vnText($a['title'] ?? $a['name'] ?? '');
    $aSlug = (string) ($a['slug'] ?? '');
    if ($aTitle === '' || !preg_match('/^[a-z0-9][a-z0-9-]*$/', $aSlug)) {
        continue;
    }
    $cents = (int) ($a['cheapest_price_cents'] ?? 0);
    if ($cents > 0 && ($cheapestCents === null || $cents < $cheapestCents)) {
        $cheapestCents = $cents;
    }
    $aCity = is_array($a['city'] ?? null) ? (string) ($a['city']['slug'] ?? '') : '';
    $tags = [];
    foreach ((array) ($a['tags'] ?? []) as $tag) {
        if (($tag = $vnText($tag)) !== '' && count($tags) < 4) {
            $tags[] = $tag;
        }
    }
    $normalizedActivities[] = [
        'title' => $aTitle,
        'url' => preg_match('/^[a-z0-9][a-z0-9-]*$/', $aCity) ? '/' . $aCity . '/' . $aSlug : '/activitate/' . $aSlug,
        'image' => v2_media_url($a['cover_image_url'] ?? $a['image'] ?? null),
        'description' => $vnExcerpt($vnText($a['short_description'] ?? $a['description'] ?? ''), 240),
        'duration' => v2_duration((int) ($a['duration_minutes'] ?? 0)),
        'price' => $cents > 0 ? $vnPrice($cents) : '',
        'category' => $vnText($a['category'] ?? ''),
        'tags' => $tags,
    ];
}
$activityCount = count($normalizedActivities);

// ------------------------------------------------------------------ similar venues
$similarRaw = $venue['similar_venues'] ?? null;
if (!is_array($similarRaw) && $citySlug !== '') {
    // Older API: the detail had no similar list, ask for the city's venues.
    $simResp = api_cached("venues_city_{$citySlug}", fn () => api_get('/venues', ['per_page' => 7, 'city' => $citySlug]), 600);
    $similarRaw = $simResp['data']['venues'] ?? $simResp['data']['items'] ?? (is_array($simResp['data'] ?? null) ? $simResp['data'] : []);
}
$similarVenues = [];
foreach ((array) $similarRaw as $sv) {
    if (!is_array($sv)) {
        continue;
    }
    $svSlug = (string) ($sv['slug'] ?? '');
    $svName = $vnText($sv['name'] ?? '');
    if ($svName === '' || $svSlug === $slug || !preg_match('/^[a-z][a-z0-9-]+$/', $svSlug)) {
        continue;
    }
    $similarVenues[] = [
        'name' => $svName,
        'url' => '/locatie/' . $svSlug,
        'image' => v2_media_url($sv['cover_image_url'] ?? $sv['image'] ?? $sv['cover_image'] ?? null),
        'type' => $vnType($sv),
        'desc' => $vnExcerpt($vnText($sv['short_description'] ?? $sv['description'] ?? ''), 140),
        'city' => $vnText($sv['city'] ?? ''),
    ];
    if (count($similarVenues) >= 3) {
        break;
    }
}

// ------------------------------------------------------------------ page
$stats = [];
if ($rating) {
    $stats[] = ['star', number_format($rating, 1, ',', ''), 'rating mediu'];
}
if ($reviewsCount > 0) {
    $stats[] = ['users-three', v2_thousands($reviewsCount), 'recenzii'];
}
if ($cheapestCents !== null) {
    $stats[] = ['coins', $vnPrice($cheapestCents), 'de la / bilet'];
}
$stats[] = ['qr-code', 'QR', 'intrare rapidă'];

$navLinks = [['activitati', 'Activități']];
if ($hasAbout) {
    $navLinks[] = ['despre', 'Despre'];
}
$navLinks[] = ['program', 'Program & adresă'];
$navLinks[] = ['faq', 'FAQ'];
if ($similarVenues) {
    $navLinks[] = ['similare', 'Locații similare'];
}

$faqs = [
    ['Cum cumpăr biletele pentru ' . $name . '?', 'Alegi activitatea de mai sus, selectezi data și ora, completezi datele și primești biletul cu QR pe email.'],
    ['Pot anula sau reprograma biletul?', 'Politica de anulare este stabilită de fiecare locație și e afișată pe pagina activității înainte de plată.'],
    ['Trebuie să-mi creez cont?', 'Nu, poți cumpăra ca invitat. Contul îți ajută însă să-ți regăsești biletele și istoricul.'],
    ['Cum intru cu biletul la locație?', 'Arăți codul QR de pe bilet — în email sau în cont. Personalul scanează și ești înăuntru.'],
];

$breadcrumbs = [['name' => 'Acasă', 'url' => '/']];
if ($cityName !== '' && $citySlug !== '') {
    $breadcrumbs[] = ['name' => $cityName, 'url' => '/' . $citySlug];
}
$breadcrumbs[] = ['name' => 'Locații', 'url' => '/operatori'];
$breadcrumbs[] = ['name' => $name, 'url' => '/locatie/' . $slug];

$pageTitleRaw = $name . ' — ' . ($cityName ?: 'România') . ' · ' . SITE_NAME;
$pageDescription = $shortDescription !== '' ? $vnExcerpt($shortDescription, 160)
    : ($descRaw !== '' ? $vnExcerpt($descRaw, 160) : "Bilete pentru activități la {$name}" . ($cityName ? " în {$cityName}" : '') . '. Rezervi online, primești QR pe email.');
$canonicalUrl = SITE_URL . '/locatie/' . $slug;
$ogImage = $photos[0] ?? (SITE_URL . '/assets/images/og-default.jpg');

$vnClean = function (array $data) use (&$vnClean): array {
    foreach ($data as $k => $v) {
        if (is_array($v)) {
            $data[$k] = $v = $vnClean($v);
        }
        if ($v === null || $v === '' || $v === []) {
            unset($data[$k]);
        }
    }
    return $data;
};
$structuredData = [$vnClean([
    '@context' => 'https://schema.org',
    '@type' => 'LocalBusiness',
    'name' => $name,
    'url' => $canonicalUrl,
    'image' => array_slice($photos, 0, 3),
    'description' => $pageDescription,
    'address' => ($address !== '' || $cityName !== '') ? [
        '@type' => 'PostalAddress',
        'streetAddress' => $address,
        'addressLocality' => $cityName,
        'addressRegion' => $county,
        'addressCountry' => 'RO',
    ] : null,
    'geo' => $hasGeo ? ['@type' => 'GeoCoordinates', 'latitude' => (float) $lat, 'longitude' => (float) $lng] : null,
    'aggregateRating' => ($rating && $reviewsCount > 0) ? ['@type' => 'AggregateRating', 'ratingValue' => $rating, 'reviewCount' => $reviewsCount] : null,
]), [
    '@context' => 'https://schema.org',
    '@type' => 'BreadcrumbList',
    'itemListElement' => array_map(fn ($bc, $pos) => [
        '@type' => 'ListItem',
        'position' => $pos + 1,
        'name' => $bc['name'],
        'item' => SITE_URL . $bc['url'],
    ], $breadcrumbs, array_keys($breadcrumbs)),
]];

$vnArches = '<svg class="deco-arches" viewBox="0 0 400 400" aria-hidden="true" focusable="false"><path d="M40 400V200a160 160 0 0 1 320 0v200"/><path d="M90 400V200a110 110 0 0 1 220 0v200"/><path d="M140 400V200a60 60 0 0 1 120 0v200"/></svg>';
$activitiesLabel = v2_num($activityCount, 'activitate', 'activități');

$v2Styles = ['attraction.css', 'venue.css'];
$v2Scripts = ['attraction.js', 'venue.js'];
$v2HeaderOverlay = true;
$v2HeadExtra = $photos ? '<link rel="preload" as="image" href="' . v2_e($photos[0]) . '" fetchpriority="high">' : '';
$v2ClientData = ['gallery' => array_map(fn ($src) => ['src' => $src, 'alt' => $name], $photos)];

include __DIR__ . '/includes/v2/head.php';
include __DIR__ . '/includes/v2/header.php';
?>
<main id="main" tabindex="-1">
  <!-- ===================== HERO ===================== -->
  <section class="th vn-hero" aria-labelledby="th-h">
    <?= $vnArches ?>
    <svg class="th-line draw-clip" viewBox="0 590 3240 310" aria-hidden="true" focusable="false"><use href="#drum-g"/></svg>
    <div class="th-in">
      <div class="th-copy">
        <nav class="crumbs" aria-label="Breadcrumb">
          <?php foreach ($breadcrumbs as $bi => $bc): ?>
            <?php if ($bi > 0): ?><span aria-hidden="true">/</span><?php endif; ?>
            <?php if ($bi < count($breadcrumbs) - 1): ?><a href="<?= v2_e($bc['url']) ?>"><?= v2_e($bc['name']) ?></a><?php else: ?><span aria-current="page"><?= v2_e($bc['name']) ?></span><?php endif; ?>
          <?php endforeach; ?>
        </nav>
        <p class="th-kicker"><?= v2_e($typeLabel) ?><?= $cityName !== '' ? ' · ' . v2_e($cityName) : '' ?></p>
        <h1 class="th-h" id="th-h"><?= v2_e($name) ?></h1>
        <?php if ($lead !== ''): ?><p class="th-sub"><?= v2_e($lead) ?></p><?php endif; ?>
        <?php if ($activityCount || $address !== ''): ?>
        <ul class="th-chips">
          <?php if ($activityCount): ?><li><?= v2_ic('ticket') ?><?= v2_e($activitiesLabel) ?></li><?php endif; ?>
          <?php if ($address !== ''): ?><li><?= v2_ic('map-pin') ?><?= v2_e($address) ?></li><?php endif; ?>
        </ul>
        <?php endif; ?>
        <div class="th-cta">
          <?php if ($activityCount): ?><a class="btn btn-light" href="#activitati">Vezi activitățile<?= v2_ic('arrow-right') ?></a><?php endif; ?>
          <a class="btn btn-outline-light" href="#program"><?= v2_ic('map-pin') ?>Program &amp; adresă</a>
          <a class="btn btn-outline-light" href="#faq">Întrebări</a>
        </div>
        <dl class="vn-stats">
          <?php foreach ($stats as [$statIcon, $statValue, $statLabel]): ?>
          <div><dt><?= v2_e($statLabel) ?></dt><dd><?= v2_ic($statIcon) ?><?= v2_e($statValue) ?></dd></div>
          <?php endforeach; ?>
        </dl>
      </div>

      <div class="th-media vn-media">
        <?php if ($heroPhotos): ?>
        <div class="vn-frame">
          <button class="th-arch" id="vn-arch" type="button" data-gallery="0" aria-haspopup="dialog" aria-controls="lb" aria-label="Deschide galeria: <?= v2_e($name) ?>">
            <?php foreach ($heroPhotos as $pi => $photo): ?>
            <img class="vn-slide<?= $pi === 0 ? ' is-on' : '' ?>" src="<?= v2_e($photo) ?>" alt="" <?= $pi === 0 ? 'fetchpriority="high"' : 'loading="lazy"' ?> decoding="async">
            <?php endforeach; ?>
            <?php if (count($photos) > 1): ?><span class="th-gal"><?= v2_ic('magnifying-glass') ?>Vezi galeria (<?= count($photos) ?>)</span><?php endif; ?>
          </button>
        </div>
        <div class="vn-cap">
          <p><small>Galerie locație</small><b><?= v2_e($typeLabel) ?></b></p>
          <?php if (count($heroPhotos) > 1): ?>
          <div class="vn-dots" role="group" aria-label="Fotografii">
            <?php foreach ($heroPhotos as $pi => $photo): ?>
            <button type="button" data-slide="<?= $pi ?>" aria-pressed="<?= $pi === 0 ? 'true' : 'false' ?>"><span class="sr">Fotografia <?= $pi + 1 ?> din <?= count($heroPhotos) ?></span></button>
            <?php endforeach; ?>
          </div>
          <?php endif; ?>
        </div>
        <?php else: ?>
        <div class="vn-frame"><div class="th-arch is-empty"><?= v2_fallback($name) ?><span class="th-arch-name" aria-hidden="true"><?php if ($cityName !== ''): ?><small><?= v2_e($typeLabel) ?></small><?= v2_e($cityName) ?><?php else: ?><?= v2_e($typeLabel) ?><?php endif; ?></span></div></div>
        <?php endif; ?>
      </div>
    </div>
  </section>
  <div id="hdr-sentinel" aria-hidden="true"></div>

  <!-- ===================== SECTION NAV ===================== -->
  <nav class="vn-nav" aria-label="Secțiunile paginii">
    <div class="wrap">
      <ul id="vn-nav">
        <?php foreach ($navLinks as $ni => [$navId, $navLabel]): ?>
        <li><a href="#<?= $navId ?>"<?= $ni === 0 ? ' aria-current="true"' : '' ?>><?= v2_e($navLabel) ?></a></li>
        <?php endforeach; ?>
      </ul>
    </div>
  </nav>

  <!-- ===================== ACTIVITIES + VENUE CARD ===================== -->
  <section class="sec vn-acts" id="activitati" aria-labelledby="vn-acts-h">
    <div class="wrap">
      <div class="vn-acts-head">
        <div>
          <p class="kicker">Activități în această locație</p>
          <h2 id="vn-acts-h">Alege experiența potrivită</h2>
          <p class="vn-lead">Aceeași locație poate avea bilete diferite: acces general, tururi ghidate, intervale orare, camere tematice, ateliere sau abonamente.</p>
        </div>
        <div class="vn-note">
          <span class="vn-note-ic"><?= v2_ic('ticket') ?></span>
          <p><b>Cumperi mai multe activități?</b>Rezervi separat fiecare activitate. Toate biletele ajung pe email, cu QR.</p>
        </div>
      </div>

      <div class="vn-acts-grid">
        <div>
          <?php if ($normalizedActivities): ?>
          <p class="vn-count"><?= v2_e(v2_num($activityCount, 'activitate disponibilă', 'activități disponibile')) ?></p>
          <ul class="vn-list">
            <?php foreach ($normalizedActivities as $ai => $a): ?>
            <li class="vn-act">
              <span class="vn-act-media">
                <?= $a['image'] ? v2_photo([$a['image'], 0, 0, '']) : v2_fallback($a['title'], $ai) ?>
                <?php if ($a['category'] !== ''): ?><span class="vn-badge"><?= v2_e($a['category']) ?></span><?php endif; ?>
                <?php if ($a['duration'] !== ''): ?><span class="vn-dur"><?= v2_ic('clock') ?><?= v2_e($a['duration']) ?></span><?php endif; ?>
              </span>
              <div class="vn-act-body">
                <h3><a href="<?= v2_e($a['url']) ?>"><?= v2_e($a['title']) ?></a></h3>
                <?php if ($a['description'] !== ''): ?><p><?= v2_e($a['description']) ?></p><?php endif; ?>
                <?php if ($a['tags']): ?>
                <ul class="vn-tags"><?php foreach ($a['tags'] as $tag): ?><li><?= v2_e($tag) ?></li><?php endforeach; ?></ul>
                <?php endif; ?>
              </div>
              <div class="vn-act-buy">
                <?php if ($a['price'] !== ''): ?><p class="vn-price">de la<b><?= v2_e($a['price']) ?></b></p><?php endif; ?>
                <span class="btn btn-primary" aria-hidden="true">Rezervă<?= v2_ic('arrow-right') ?></span>
              </div>
            </li>
            <?php endforeach; ?>
          </ul>
          <?php else: ?>
          <div class="tempty">
            <h3>Nu există încă activități listate pentru această locație.</h3>
            <p>Revino în curând sau caută în alte locații apropiate.</p>
            <?php if ($citySlug !== ''): ?>
            <a class="btn btn-light" href="/<?= v2_e($citySlug) ?>">Vezi activități în <?= v2_e($cityName) ?><?= v2_ic('arrow-right') ?></a>
            <?php else: ?>
            <a class="btn btn-light" href="/categorii">Explorează categorii<?= v2_ic('arrow-right') ?></a>
            <?php endif; ?>
            <svg class="tempty-line" viewBox="0 590 3240 310" aria-hidden="true" focusable="false"><use href="#drum-g"/></svg>
          </div>
          <?php endif; ?>
        </div>

        <aside class="vn-side" aria-label="Fișa locației">
          <div class="vn-card">
            <p class="vn-card-k">Fișa locației</p>
            <h3><?= v2_e($name) ?></h3>
            <?php if ($address !== '' || $hours || $activityCount): ?>
            <ul class="vn-facts">
              <?php if ($address !== ''): ?><li><?= v2_ic('map-pin') ?><span><?= v2_e($address . ($cityName !== '' ? ', ' . $cityName : '')) ?></span></li><?php endif; ?>
              <?php if ($hours): ?><li><?= v2_ic('clock') ?><span><?= implode('<br>', array_map('v2_e', $hours)) ?></span></li><?php endif; ?>
              <?php if ($activityCount): ?><li><?= v2_ic('ticket') ?><span><?= v2_e(v2_num($activityCount, 'activitate disponibilă', 'activități disponibile')) ?></span></li><?php endif; ?>
            </ul>
            <?php endif; ?>
            <div class="vn-card-cta">
              <a class="btn btn-outline-light" href="#program">Adresă</a>
              <a class="btn btn-light" href="#activitati">Bilete</a>
            </div>
          </div>
          <?php if ($citySlug !== ''): ?>
          <div class="vn-search">
            <h3>Căutări utile</h3>
            <div class="chips-links">
              <a href="/<?= v2_e($citySlug) ?>">activități <?= v2_e($cityName) ?></a>
              <a href="/<?= v2_e($citySlug) ?>/activitati-copii">copii <?= v2_e($cityName) ?></a>
              <a href="/<?= v2_e($citySlug) ?>/activitati-weekend">weekend <?= v2_e($cityName) ?></a>
            </div>
          </div>
          <?php endif; ?>
        </aside>
      </div>
    </div>
  </section>

  <?php if ($hasAbout): ?>
  <!-- ===================== ABOUT ===================== -->
  <section class="sec vn-about" id="despre" aria-labelledby="vn-about-h">
    <div class="wrap vn-about-grid">
      <div>
        <p class="kicker">Despre locație</p>
        <h2 id="vn-about-h"><?= v2_e($name) ?> — <span>despre</span></h2>
      </div>
      <div class="vn-prose"><?= $descHtml ?></div>
    </div>
  </section>
  <?php endif; ?>

  <!-- ===================== PROGRAM & ADDRESS ===================== -->
  <section class="sec vn-prog" id="program" aria-labelledby="vn-prog-h">
    <div class="wrap vn-prog-grid">
      <div>
        <p class="kicker">Program, adresă &amp; acces</p>
        <h2 id="vn-prog-h">Cum ajungi la <?= v2_e($name) ?></h2>
        <ul class="vn-info">
          <?php if ($address !== ''): ?>
          <li>
            <span class="vn-info-ic"><?= v2_ic('map-pin') ?></span>
            <h3>Adresă</h3>
            <p><?= v2_e($address) ?><?php if ($cityName !== ''): ?><br><?= v2_e($cityName) ?><?php endif; ?></p>
            <?php if ($mapsUrl): ?><a href="<?= v2_e($mapsUrl) ?>" target="_blank" rel="noopener">Deschide în Maps<?= v2_ic('arrow-right') ?></a><?php endif; ?>
          </li>
          <?php endif; ?>
          <?php if ($hours): ?>
          <li>
            <span class="vn-info-ic"><?= v2_ic('clock') ?></span>
            <h3>Program</h3>
            <p><?= implode('<br>', array_map('v2_e', $hours)) ?></p>
          </li>
          <?php endif; ?>
          <li>
            <span class="vn-info-ic"><?= v2_ic('users-three') ?></span>
            <h3>Recomandare</h3>
            <p>Ajungi cu 10–15 minute înainte de intervalul ales, mai ales pentru activitățile cu grup.</p>
          </li>
          <li>
            <span class="vn-info-ic"><?= v2_ic('qr-code') ?></span>
            <h3>Bilet QR</h3>
            <p>După plată primești biletul cu QR pe email și în cont. Nu trebuie să-l printezi.</p>
          </li>
        </ul>
      </div>

      <?php if ($hasGeo): ?>
      <div class="tmap vn-map">
        <iframe title="Hartă <?= v2_e($name) ?>" loading="lazy" referrerpolicy="no-referrer-when-downgrade" src="https://www.google.com/maps?q=<?= rawurlencode($lat . ',' . $lng) ?>&amp;z=15&amp;output=embed"></iframe>
        <a class="tmap-link" href="<?= v2_e($mapsUrl) ?>" target="_blank" rel="noopener"><?= v2_ic('map-pin') ?>Deschide în Google Maps<?= v2_ic('arrow-right') ?></a>
      </div>
      <?php else: ?>
      <div class="vn-mapart">
        <span class="vn-pin"><?= v2_ic('map-pin') ?></span>
        <p class="vn-mapart-city"><?= v2_e($cityName ?: 'România') ?></p>
        <p class="vn-mapart-addr"><?= v2_e($address !== '' ? $address : 'Vezi pagina locației pentru detalii despre poziție.') ?></p>
        <?php if ($mapsUrl): ?><a class="btn btn-outline-light" href="<?= v2_e($mapsUrl) ?>" target="_blank" rel="noopener">Deschide în Maps<?= v2_ic('arrow-right') ?></a><?php endif; ?>
      </div>
      <?php endif; ?>
    </div>
  </section>

  <!-- ===================== FAQ ===================== -->
  <section class="sec vn-faq" id="faq" aria-labelledby="vn-faq-h">
    <div class="wrap vn-faq-grid">
      <div>
        <p class="kicker">FAQ</p>
        <h2 id="vn-faq-h">Întrebări frecvente</h2>
      </div>
      <div>
        <?php foreach ($faqs as $fi => [$faqQ, $faqA]): ?>
        <details class="qa"<?= $fi === 0 ? ' open' : '' ?>><summary><?= v2_e($faqQ) ?><span class="pm"><?= v2_ic('plus') ?></span></summary><p><?= v2_e($faqA) ?></p></details>
        <?php endforeach; ?>
      </div>
    </div>
  </section>

  <?php if ($similarVenues): ?>
  <!-- ===================== SIMILAR VENUES ===================== -->
  <section class="sec vn-sim" id="similare" aria-labelledby="vn-sim-h">
    <?php readfile(__DIR__ . '/includes/v2/topo.svg'); ?>
    <div class="wrap">
      <div class="sec-head">
        <div><p class="kicker">Locații similare</p><h2 id="vn-sim-h">Mai multe locuri de vizitat<?= $cityName !== '' ? ' în ' . v2_e($cityName) : '' ?></h2></div>
        <?php if ($citySlug !== ''): ?><a class="sec-link" href="/<?= v2_e($citySlug) ?>">Vezi toate locațiile<?= v2_ic('arrow-right') ?></a><?php endif; ?>
      </div>
      <ul class="vn-sim-grid">
        <?php foreach ($similarVenues as $svi => $sv): ?>
        <li><a class="vn-simcard" href="<?= v2_e($sv['url']) ?>">
          <span class="vn-simcard-media"><?= $sv['image'] ? v2_photo([$sv['image'], 0, 0, '']) : v2_fallback($sv['name'], $svi) ?></span>
          <span class="vn-simcard-body">
            <span class="vn-simcard-k"><?= v2_e($sv['type']) ?></span>
            <span class="vn-simcard-t"><?= v2_e($sv['name']) ?></span>
            <?php if ($sv['desc'] !== ''): ?><span class="vn-simcard-d"><?= v2_e($sv['desc']) ?></span><?php endif; ?>
            <span class="vn-simcard-f"><?php if ($sv['city'] !== ''): ?><span class="vn-simcard-city"><?= v2_ic('map-pin') ?><?= v2_e($sv['city']) ?></span><?php endif; ?><span class="xp-go"><?= v2_ic('arrow-right') ?></span></span>
          </span>
        </a></li>
        <?php endforeach; ?>
      </ul>
    </div>
  </section>
  <?php endif; ?>

  <!-- ===================== FINAL CTA ===================== -->
  <section class="vn-final" aria-labelledby="vn-final-h">
    <div class="wrap">
      <div class="vn-final-in">
        <?= $vnArches ?>
        <p class="kicker"><?= v2_e($name) ?></p>
        <h2 id="vn-final-h">Alege activitatea, rezervă online, intră cu QR.</h2>
        <p class="vn-final-text">Toate experiențele acestei locații într-un singur loc: program, prețuri, disponibilitate și bilete digitale.</p>
        <div class="vn-final-cta">
          <?php if ($activityCount): ?><a class="btn btn-light" href="#activitati">Vezi activitățile<?= v2_ic('arrow-right') ?></a><?php endif; ?>
          <?php if ($citySlug !== ''): ?>
          <a class="btn btn-outline-light" href="/<?= v2_e($citySlug) ?>">Explorează <?= v2_e($cityName) ?></a>
          <?php else: ?>
          <a class="btn btn-outline-light" href="/categorii">Explorează categorii</a>
          <?php endif; ?>
        </div>
        <svg class="vn-final-line" viewBox="0 590 3240 310" aria-hidden="true" focusable="false"><use href="#drum-g"/></svg>
      </div>
    </div>
  </section>

  <?php if ($photos): ?>
  <!-- ===================== LIGHTBOX ===================== -->
  <div class="lb" id="lb" role="dialog" aria-modal="true" aria-labelledby="lb-title" hidden>
    <div class="lb-top">
      <p class="lb-title" id="lb-title"><?= v2_e($name) ?></p>
      <span class="lb-count" id="lb-count">1 / <?= count($photos) ?></span>
      <button class="icon-btn" type="button" data-lb="close"><?= v2_ic('x') ?><span class="sr">Închide galeria</span></button>
    </div>
    <figure class="lb-fig"><img id="lb-img" src="" alt=""></figure>
    <div class="lb-nav"<?= count($photos) < 2 ? ' hidden' : '' ?>>
      <button class="rail-btn" type="button" data-lb="prev" aria-label="Fotografia anterioară"><?= v2_ic('arrow-left') ?></button>
      <button class="rail-btn" type="button" data-lb="next" aria-label="Fotografia următoare"><?= v2_ic('arrow-right') ?></button>
    </div>
  </div>
  <?php endif; ?>
</main>
<?php include __DIR__ . '/includes/v2/footer.php'; ?>
