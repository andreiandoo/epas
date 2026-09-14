<?php
/**
 * City × Intent SEO landing page (v2 "Arcada").
 * Handles BOTH:
 *   /{city}/{intent}     — city-scoped (e.g. /brasov/activitati-indoor)
 *   /{intent}            — global (e.g. /activitati-azi)
 *
 * One PHP file → 100 cities × 25 intents × pagination = thousands of SEO pages.
 * Data and SEO meta come from the marketplace API; this file is pure render +
 * cross-link composition.
 *
 * Top to bottom: hero (breadcrumbs, intent, intro, CTAs, cover or brand arch with the intent icon), results
 * (cards, pagination) or the empty state, cross links (other intents here, the same intent in other cities), SEO copy.
 */

$pageCacheTTL = 300;
require_once __DIR__ . '/includes/page-cache.php';
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/api.php';

$citySlug = $_GET['city'] ?? null;
$intentSlug = $_GET['intent'] ?? null;
$pageNum = max(1, (int) ($_GET['page'] ?? 1));

if (!$intentSlug || !preg_match('/^[a-z0-9-]+$/', $intentSlug)) {
    http_response_code(404);
    require_once __DIR__ . '/404.php';
    exit;
}

if ($citySlug && !preg_match('/^[a-z0-9-]+$/', $citySlug)) {
    http_response_code(404);
    require_once __DIR__ . '/404.php';
    exit;
}

// Fetch + cache
$cacheKey = 'intent_' . $intentSlug . '_' . ($citySlug ?? 'global') . '_p' . $pageNum;
$apiData = api_cached($cacheKey, function () use ($intentSlug, $citySlug, $pageNum) {
    $endpoint = $citySlug
        ? '/intents/' . urlencode($intentSlug) . '/cities/' . urlencode($citySlug) . '/events'
        : '/intents/' . urlencode($intentSlug) . '/events';
    return api_get($endpoint, ['page' => $pageNum, 'per_page' => 24]);
}, 300);

// Hard 404 only if both intent + city were rejected by the API
// (the API returns success=false with error key)
if (!is_array($apiData) || empty($apiData['success']) || !isset($apiData['data'])) {
    http_response_code(404);
    require_once __DIR__ . '/404.php';
    exit;
}

require_once __DIR__ . '/includes/nav-helpers.php';
require_once __DIR__ . '/includes/v2/helpers.php';
require_once __DIR__ . '/includes/v2/nav.php';

$data = $apiData['data'];
$intent = is_array($data['intent'] ?? null) ? $data['intent'] : [];
$city = is_array($data['city'] ?? null) ? $data['city'] : null;
$meta = is_array($data['meta'] ?? null) ? $data['meta'] : [];
$events = is_array($data['events'] ?? null) ? $data['events'] : [];
$pagination = array_merge(['current_page' => 1, 'last_page' => 1, 'total' => 0], is_array($data['pagination'] ?? null) ? $data['pagination'] : []);
$crossLinks = array_merge(['other_intents_for_city' => [], 'same_intent_for_cities' => []], is_array($data['cross_links'] ?? null) ? $data['cross_links'] : []);

$currentPageNum = max(1, (int) $pagination['current_page']);
$lastPage = max(1, (int) $pagination['last_page']);
$total = max(0, (int) $pagination['total']);
$otherIntents = array_values(array_filter((array) $crossLinks['other_intents_for_city'], fn ($l) => is_array($l) && !empty($l['path']) && !empty($l['name'])));
$sameIntent = array_values(array_filter((array) $crossLinks['same_intent_for_cities'], fn ($l) => is_array($l) && !empty($l['path']) && !empty($l['name'])));

// Global intents come with the city left out of the text templates ("… în  · bilete.online", "pentru : …"):
// drop the dangling preposition and tidy the spacing it leaves. Texts with a city are not affected.
$clean = static function ($text): string {
    $text = (string) $text;
    $text = preg_replace('/[ \t]+(?:în|din|pentru|la)[ \t]*(?=[·:;.,!?]|$)/um', '', $text);
    $text = preg_replace('/[ \t]*·[ \t]*/u', ' · ', $text);
    $text = preg_replace('/[ \t]{2,}/u', ' ', $text);
    return trim($text);
};

$intentName = (string) ($intent['name'] ?? 'Activități');
$intentSlugSafe = (string) ($intent['slug'] ?? $intentSlug);
$cityName = $city ? (string) ($city['name'] ?? '') : '';
// The heading comes from meta h1 (or the title when there is none); either can end in " · bilete.online",
// which belongs in <title> only
$h1 = $clean(preg_replace('/\s*·\s*' . preg_quote(SITE_NAME, '/') . '\s*$/u', '', (string) ($meta['h1'] ?? $meta['title'] ?? $intentName)));
$intro = $clean($meta['intro_copy'] ?? '');
$intentIcon = (string) ($meta['icon'] ?? '');
$cover = v2_media_url($meta['cover_image_url'] ?? null);
$basePath = (string) ($meta['canonical_path'] ?? '/');
$accentClass = ['vermilion' => 'is-red', 'forest' => 'is-green', 'ochre' => 'is-yellow', 'sky' => 'is-blue'][$meta['accent_color'] ?? 'vermilion'] ?? 'is-red';
$seoParagraphs = [];
if (!empty($meta['seo_copy'])) {
    // seo_copy is plain text: paragraphs split on blank lines
    $seoParagraphs = array_values(array_filter(array_map($clean, preg_split('/\n\s*\n/', trim((string) $meta['seo_copy'])))));
}

// SEO setup for head.php
$pageTitleRaw = $clean($meta['title'] ?? ('Activități · ' . SITE_NAME));
$pageDescription = $clean($meta['description'] ?? SITE_TAGLINE);
$canonicalUrl = SITE_URL . $basePath . ($pageNum > 1 ? '?page=' . $pageNum : '');
$ogImage = $cover;
$noindex = !empty($meta['noindex']);
$currentPage = 'intent';
$v2Styles = ['intent.css'];

$pageUrl = static fn (int $p): string => $basePath . ($p > 1 ? '?page=' . $p : '');
$searchUrl = '/cauta?intent=' . urlencode($intentSlugSafe) . ($city ? '&city=' . urlencode((string) ($city['slug'] ?? '')) : '');

// Breadcrumbs
$breadcrumbs = [['name' => 'Acasă', 'url' => SITE_URL . '/']];
if ($city) {
    $breadcrumbs[] = ['name' => $cityName, 'url' => SITE_URL . '/' . ($city['slug'] ?? '')];
}
$breadcrumbs[] = ['name' => $intentName, 'url' => SITE_URL . $basePath];

// Cards
$cards = [];
foreach ($events as $ev) {
    if (!is_array($ev)) {
        continue;
    }
    $title = navFlatName($ev['title'] ?? '');
    if ($title === '') {
        $title = 'Activitate';
    }
    $slug = (string) ($ev['slug'] ?? '');
    $category = is_array($ev['marketplace_event_category'] ?? null) ? navFlatName($ev['marketplace_event_category']['name'] ?? '') : '';
    $cityLabel = is_array($ev['marketplace_city'] ?? null)
        ? navFlatName($ev['marketplace_city']['name'] ?? '')
        : (string) (is_array($ev['venue'] ?? null) ? ($ev['venue']['city'] ?? '') : '');
    $cents = $ev['cheapest_price_cents'] ?? null;
    $cards[] = [
        'title' => $title,
        'href' => $slug !== '' ? '/bilete/' . $slug : v2_cauta($title),
        'category' => $category,
        'city' => $cityLabel,
        'image' => v2_media_url($ev['cover_image_url'] ?? $ev['image_url'] ?? null),
        'cents' => $cents === null ? null : (int) $cents,
    ];
}

// Structured data: CollectionPage + ItemList of top 10 events, BreadcrumbList
$structuredData = [];
if ($cards) {
    $itemListElements = [];
    foreach (array_slice($events, 0, 10) as $i => $ev) {
        $itemListElements[] = [
            '@type' => 'ListItem',
            'position' => $i + 1,
            'name' => $cards[$i]['title'],
            'url' => SITE_URL . '/bilete/' . ($ev['slug'] ?? ''),
        ];
    }
    $structuredData[] = [
        '@context' => 'https://schema.org',
        '@type' => 'CollectionPage',
        'name' => $pageTitleRaw,
        'description' => $pageDescription,
        'url' => $canonicalUrl,
        'inLanguage' => 'ro-RO',
        'isPartOf' => ['@type' => 'WebSite', 'name' => SITE_NAME, 'url' => SITE_URL],
        'mainEntity' => [
            '@type' => 'ItemList',
            'numberOfItems' => $total,
            'itemListElement' => $itemListElements,
        ],
    ];
}
$structuredData[] = [
    '@context' => 'https://schema.org',
    '@type' => 'BreadcrumbList',
    'itemListElement' => array_map(fn ($bc, $i) => ['@type' => 'ListItem', 'position' => $i + 1, 'name' => $bc['name'], 'item' => $bc['url']], $breadcrumbs, array_keys($breadcrumbs)),
];

include __DIR__ . '/includes/v2/head.php';
include __DIR__ . '/includes/v2/header.php';
?>
<main id="main" class="page-main" tabindex="-1">

  <!-- ============================== HERO ============================== -->
  <section class="it-hero <?= $accentClass ?>" aria-labelledby="it-h">
    <div class="wrap it-grid">
      <div class="it-text">
        <nav class="crumbs" aria-label="Breadcrumb">
          <?php foreach ($breadcrumbs as $i => $bc): ?>
            <?php if ($i > 0): ?><span aria-hidden="true">/</span><?php endif; ?>
            <?php if ($i < count($breadcrumbs) - 1): ?>
              <a href="<?= v2_e(substr($bc['url'], strlen(SITE_URL)) ?: '/') ?>"><?= v2_e($bc['name']) ?></a>
            <?php else: ?>
              <span aria-current="page"><?= v2_e($bc['name']) ?></span>
            <?php endif; ?>
          <?php endforeach; ?>
        </nav>
        <?php if ($intentIcon !== ''): ?><span class="it-icon-sm" aria-hidden="true"><?= v2_e($intentIcon) ?></span><?php endif; ?>
        <p class="kicker"><?= $city ? 'Local · ' . v2_e($cityName) : 'Catalog' ?></p>
        <h1 class="it-h" id="it-h"><?= v2_e($h1) ?></h1>
        <?php if ($intro !== ''): ?><p class="it-lead"><?= v2_e($intro) ?></p><?php endif; ?>
        <div class="it-cta">
          <?php if ($total > 0): ?>
          <a class="btn btn-primary" href="#activitati">Vezi <?= v2_e(v2_num($total, 'activitate', 'activități')) ?><?= v2_ic('arrow-right') ?></a>
          <?php endif; ?>
          <?php /* with nothing listed yet, the way onward (the city, or all cities) becomes the main button */ ?>
          <?php $itMoreClass = $total > 0 ? 'it-link' : 'btn btn-primary'; ?>
          <?php if ($city): ?>
          <a class="<?= $itMoreClass ?>" href="/<?= v2_e($city['slug'] ?? '') ?>">Toate activitățile din <?= v2_e($cityName) ?><?= v2_ic('arrow-right') ?></a>
          <?php else: ?>
          <a class="<?= $itMoreClass ?>" href="/orase">Caută după oraș<?= v2_ic('arrow-right') ?></a>
          <?php endif; ?>
        </div>
      </div>
      <div class="it-art" aria-hidden="true">
        <div class="it-arch<?= $cover ? '' : ' is-empty' ?>">
          <?php if ($cover): ?><img src="<?= v2_e($cover) ?>" alt="" decoding="async" fetchpriority="high"><?php else: ?><?= v2_fallback($intentName) ?><?php endif; ?>
          <?php if ($intentIcon !== ''): ?><span class="it-icon"><?= v2_e($intentIcon) ?></span><?php endif; ?>
        </div>
      </div>
    </div>
  </section>

  <!-- ============================== ACTIVITĂȚI ============================== -->
  <section class="it-list" id="activitati" aria-labelledby="it-list-h">
    <div class="wrap">
      <?php if (!$cards): ?>
      <div class="it-empty">
        <h2 id="it-list-h">Nimic disponibil acum.</h2>
        <p>Pagina rămâne activă — verifică din nou peste câteva zile sau încearcă o altă intenție.</p>
        <?php if ($otherIntents): ?>
        <div class="chips-links it-chips">
          <?php foreach (array_slice($otherIntents, 0, 6) as $li): ?>
          <a href="<?= v2_e($li['path']) ?>"><?php if (!empty($li['icon'])): ?><span aria-hidden="true"><?= v2_e($li['icon']) ?></span><?php endif; ?><?= v2_e($li['name']) ?></a>
          <?php endforeach; ?>
        </div>
        <?php endif; ?>
        <?php if ($city): ?>
        <a class="btn btn-light" href="/<?= v2_e($city['slug'] ?? '') ?>">Toate activitățile din <?= v2_e($cityName) ?><?= v2_ic('arrow-right') ?></a>
        <?php else: ?>
        <a class="btn btn-light" href="/cauta"><?= v2_ic('magnifying-glass') ?>Caută activități</a>
        <?php endif; ?>
        <svg class="it-empty-line" viewBox="1455 585 1210 310" aria-hidden="true"><use href="#drum-g"/></svg>
      </div>
      <?php else: ?>
      <div class="sec-head it-head">
        <div>
          <p class="kicker">Activități</p>
          <h2 id="it-list-h"><?= v2_e($intentName) ?><?= $city ? ' în ' . v2_e($cityName) : '' ?></h2>
        </div>
        <div class="it-meta">
          <p><?= v2_e(v2_num($total, 'rezultat', 'rezultate')) ?><?php if ($lastPage > 1): ?> · pagina <?= $currentPageNum ?> din <?= $lastPage ?><?php endif; ?></p>
          <a class="sec-link" href="<?= v2_e($searchUrl) ?>">Filtre avansate<?= v2_ic('arrow-right') ?></a>
        </div>
      </div>

      <ul class="xp-grid">
        <?php foreach ($cards as $i => $c): ?>
        <li class="xp">
          <a href="<?= v2_e($c['href']) ?>">
            <span class="xp-media"><?= $c['image'] ? v2_photo([$c['image'], 0, 0, '']) : v2_fallback($c['title'], $i) ?></span>
            <span class="xp-body">
              <span class="xp-cat"><?= v2_e(implode(' · ', array_filter([$c['category'], $c['city']]))) ?></span>
              <span class="xp-title"><?= v2_e($c['title']) ?></span>
              <span class="xp-meta"><?php if ($c['city'] !== ''): ?><span><?= v2_ic('map-pin') ?><?= v2_e($c['city']) ?></span><?php endif; ?></span>
              <span class="xp-foot">
                <span class="xp-go">Vezi bilete<?= v2_ic('arrow-right') ?></span>
                <?php if ($c['cents'] === null): ?>
                <span class="xp-price"><b>—</b></span>
                <?php elseif ($c['cents'] === 0): ?>
                <span class="xp-price"><b>Gratuit</b></span>
                <?php else: ?>
                <span class="xp-price">de la<b><?= v2_thousands((int) round($c['cents'] / 100)) ?> lei</b></span>
                <?php endif; ?>
              </span>
            </span>
          </a>
        </li>
        <?php endforeach; ?>
      </ul>

      <?php if ($lastPage > 1):
          $maxLinks = 7;
          $start = max(1, $currentPageNum - 3);
          $end = min($lastPage, $start + $maxLinks - 1);
          $start = max(1, $end - $maxLinks + 1);
      ?>
      <nav class="pager" aria-label="Pagini">
        <?php if ($currentPageNum > 1): ?><a href="<?= v2_e($pageUrl($currentPageNum - 1)) ?>" rel="prev" aria-label="Pagina anterioară"><?= v2_ic('arrow-left') ?></a><?php endif; ?>
        <?php for ($p = $start; $p <= $end; $p++): ?>
          <?php if ($p === $currentPageNum): ?><span aria-current="page"><?= $p ?></span><?php else: ?><a href="<?= v2_e($pageUrl($p)) ?>"><?= $p ?></a><?php endif; ?>
        <?php endfor; ?>
        <?php if ($currentPageNum < $lastPage): ?><a href="<?= v2_e($pageUrl($currentPageNum + 1)) ?>" rel="next" aria-label="Pagina următoare"><?= v2_ic('arrow-right') ?></a><?php endif; ?>
      </nav>
      <?php endif; ?>
      <?php endif; ?>
    </div>
  </section>

  <!-- ============================== CROSS-LINKING ============================== -->
  <?php if ($otherIntents || $sameIntent): ?>
  <section class="it-cross" aria-label="Explorează mai departe">
    <div class="wrap it-cross-grid">
      <?php if ($otherIntents): ?>
      <div>
        <p class="kicker">Explorează alte intenții</p>
        <h2><?= $city ? 'Și mai multe activități în ' . v2_e($cityName) : 'Alte tipuri de activități' ?></h2>
        <div class="chips-links it-chips">
          <?php foreach ($otherIntents as $li): ?>
          <a href="<?= v2_e($li['path']) ?>"><?php if (!empty($li['icon'])): ?><span aria-hidden="true"><?= v2_e($li['icon']) ?></span><?php endif; ?><?= v2_e($li['name']) ?></a>
          <?php endforeach; ?>
        </div>
      </div>
      <?php endif; ?>
      <?php if ($sameIntent): ?>
      <div>
        <p class="kicker">În alte orașe</p>
        <h2><?= v2_e($intentName) ?> în alte orașe</h2>
        <div class="chips-links it-chips">
          <?php foreach ($sameIntent as $li): ?>
          <a href="<?= v2_e($li['path']) ?>"><?= v2_e($li['name']) ?></a>
          <?php endforeach; ?>
        </div>
      </div>
      <?php endif; ?>
    </div>
  </section>
  <?php endif; ?>

  <!-- ============================== SEO COPY ============================== -->
  <?php if ($seoParagraphs): ?>
  <section class="it-seo" aria-labelledby="it-seo-h">
    <div class="wrap">
      <article class="seo it-seo-card">
        <h2 id="it-seo-h"><?= v2_e($h1 !== '' ? $h1 : $intentName) ?></h2>
        <?php foreach ($seoParagraphs as $p): ?>
        <p><?= nl2br(v2_e($p)) ?></p>
        <?php endforeach; ?>
      </article>
    </div>
  </section>
  <?php endif; ?>
</main>

<?php include __DIR__ . '/includes/v2/footer.php'; ?>
