<?php
/**
 * Guides index: /ghiduri (v2 design).
 *
 * Editorial guides authored in the Tixello admin (/marketplace/blog-articles), served by `/blog-articles`; cards link
 * to /ghiduri/{slug}. The topic filter lists the blog categories that have guides. Search and topics filter the
 * server-rendered cards (guides.js) and live in the URL (?q=, ?topic=).
 *
 * Top to bottom: hero (search, topic chips, recommended guide), topics + guide cards (image, topic, read time, title,
 * excerpt; the whole card is the link), activities by context (local, weekend, weather, gift).
 * Hero, search and topic sidebar styles come from cities.css; guides.css adds the cards and the context tiles.
 */

$pageCacheTTL = 300;
require_once __DIR__ . '/includes/page-cache.php';
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/api.php';
require_once __DIR__ . '/includes/nav-helpers.php';
require_once __DIR__ . '/includes/v2/helpers.php';
require_once __DIR__ . '/includes/v2/nav.php';

// ------------------------------------------------------------------ guides + topics
$articlesResp = api_cached('guides_index', fn () => api_get('/blog-articles', ['per_page' => 48, 'status' => 'published']), 300);
$rawArticles = $articlesResp['data']['articles'] ?? $articlesResp['data'] ?? [];
$catsResp = api_cached('guides_categories', fn () => api_get('/blog-categories'), 600);
$rawCats = $catsResp['data']['categories'] ?? $catsResp['data'] ?? [];

$guides = [];
foreach ((array) $rawArticles as $a) {
    if (!is_array($a)) {
        continue;
    }
    $gTitle = navFlatName($a['title'] ?? '');
    $gSlug = (string) ($a['slug'] ?? '');
    if ($gTitle === '' || !preg_match('/^[a-z0-9][a-z0-9-]*$/', $gSlug)) {
        continue;
    }
    $cat = is_array($a['category'] ?? null) ? $a['category'] : [];
    $catName = navFlatName($cat['name'] ?? '');
    $image = v2_media_url($a['image_url'] ?? null);
    $cover = V2_GUIDE_COVERS[$gSlug] ?? null;
    $guides[] = [
        'title' => $gTitle,
        'slug' => $gSlug,
        'href' => '/ghiduri/' . $gSlug,
        'excerpt' => trim((string) ($a['excerpt'] ?? '')),
        // the guide's own image, else a provisional local photo (credited in the footer)
        'photo' => $image ? [$image, 0, 0, ''] : ($cover ? [v2_asset($cover[0]), $cover[1], $cover[2], ''] : (isset(V2_GUIDE_THUMBS[$gSlug]) ? [v2_asset(V2_GUIDE_THUMBS[$gSlug]), 0, 0, ''] : null)),
        'topic' => (string) ($cat['slug'] ?? ''),
        'topicLabel' => $catName !== '' ? (V2_BLOG_CATEGORIES[$catName] ?? $catName) : 'Ghid',
        'readTime' => (int) ($a['read_time'] ?? 0) > 0 ? (int) $a['read_time'] . ' min' : '5 min',
        'featured' => !empty($a['is_featured']),
    ];
}

$topicCounts = array_count_values(array_filter(array_column($guides, 'topic'), 'strlen'));
$topics = [];
foreach ((array) $rawCats as $c) {
    $tSlug = is_array($c) ? (string) ($c['slug'] ?? '') : '';
    $tName = is_array($c) ? navFlatName($c['name'] ?? '') : '';
    if ($tSlug !== '' && $tName !== '' && !empty($topicCounts[$tSlug])) {
        $topics[] = ['key' => $tSlug, 'label' => V2_BLOG_CATEGORIES[$tName] ?? $tName, 'count' => $topicCounts[$tSlug]];
    }
}
$quickTopics = array_slice($topics, 0, 5);

$featuredGuide = null;
foreach ($guides as $g) {
    if ($g['featured']) {
        $featuredGuide = $g;
        break;
    }
}
$featuredGuide = $featuredGuide ?? ($guides[0] ?? null);

// Activities by context: straight to pages that list what can be booked (not more reading).
$gdCityCount = count($V2NAV['allCities'] ?? []);
$contextTiles = [
    ['map-pin', 'Local', 'Activități în orașul tău', $gdCityCount > 0 ? 'Alege dintre ' . v2_num($gdCityCount, 'oraș', 'orașe') . ' și vezi ce e de făcut acolo.' : 'Alege orașul și vezi ce e de făcut acolo.', '/orase', 'is-green'],
    ['sun', 'Weekend', 'Ce faci sâmbătă și duminică', 'Activități cu locuri libere în weekendul care vine.', '/activitati-weekend', 'is-yellow'],
    ['cloud-rain', 'Vreme', 'Plouă? Mergi la adăpost', 'Activități în interior, pentru zilele ploioase.', '/activitati-zile-ploioase', 'is-blue'],
    ['gift', 'Cadou', 'O experiență de dăruit', 'Calculatorul găsește activitatea potrivită și valoarea cardului cadou.', '/experiente-cadou', 'is-red'],
];

// ------------------------------------------------------------------ page
$searchQuery = is_string($_GET['q'] ?? null) ? mb_substr(trim($_GET['q']), 0, 60) : '';

$pageTitleRaw = 'Ghiduri de activități — ' . SITE_NAME;
$pageDescription = 'Ghiduri locale și tematice pentru activități: ce să faci în weekend, unde mergi cu copiii, ce alegi când plouă și cum cumperi bilete online fără haos.';
$canonicalUrl = SITE_URL . '/ghiduri';
$ogImage = $featuredGuide['photo'][0] ?? null;
$collection = [
    '@context' => 'https://schema.org',
    '@type' => 'CollectionPage',
    'name' => $pageTitleRaw,
    'description' => $pageDescription,
    'url' => $canonicalUrl,
    'inLanguage' => 'ro-RO',
];
if ($guides) {
    $collection['mainEntity'] = [
        '@type' => 'ItemList',
        'numberOfItems' => count($guides),
        'itemListElement' => array_map(fn ($pos, $g) => ['@type' => 'ListItem', 'position' => $pos + 1, 'name' => $g['title'], 'url' => SITE_URL . $g['href']], array_keys($guides), $guides),
    ];
}
$structuredData = [$collection];

$gdArches = '<svg class="deco-arches" viewBox="0 0 400 400" aria-hidden="true" focusable="false"><path d="M40 400V200a160 160 0 0 1 320 0v200"/><path d="M90 400V200a110 110 0 0 1 220 0v200"/><path d="M140 400V200a60 60 0 0 1 120 0v200"/></svg>';
$v2Styles = ['cities.css', 'guides.css'];
$v2Scripts = ['guides.js'];
$v2HeaderOverlay = true;

include __DIR__ . '/includes/v2/head.php';
include __DIR__ . '/includes/v2/header.php';
?>
<main id="main" tabindex="-1">
  <!-- ===================== HERO ===================== -->
  <section class="ct-hero" aria-labelledby="ct-h">
    <?= $gdArches ?>
    <svg class="ct-line draw-clip" viewBox="0 590 3240 310" aria-hidden="true" focusable="false"><use href="#drum-g"/></svg>
    <div class="ct-in">
      <div>
        <p class="ct-kicker">Ghiduri · idei de ieșit · SEO editorial</p>
        <h1 class="ct-h" id="ct-h">Idei bune pentru când vrei să faci ceva.</h1>
        <p class="ct-lead">Ghiduri locale și tematice pentru activități: ce să faci în weekend, unde mergi cu copiii, ce alegi când plouă și ce experiențe merită în orașul tău.</p>

        <form class="ct-search" id="ct-form" action="/ghiduri" method="get" role="search">
          <label class="sr" for="ct-q">Caută ghiduri</label>
          <input id="ct-q" name="q" type="search" autocomplete="off" enterkeyhint="search" maxlength="60" placeholder="Caută: weekend, copii, Brașov, muzeu, ploaie..." value="<?= v2_e($searchQuery) ?>">
          <button type="submit" aria-label="Arată ghidurile găsite"><?= v2_ic('magnifying-glass') ?></button>
        </form>
        <p class="ct-status" id="ct-status" role="status"></p>
        <?php if ($quickTopics): ?>
        <ul class="ct-chips" aria-label="Topicuri">
          <?php foreach ($quickTopics as $topic): ?><li><button type="button" data-topic-chip="<?= v2_e($topic['key']) ?>" aria-pressed="false"><?= v2_e($topic['label']) ?></button></li><?php endforeach; ?>
        </ul>
        <?php endif; ?>
      </div>

      <?php if ($featuredGuide): ?>
      <div class="ct-art">
        <article class="gd-feature">
          <a class="gd-feature-media" href="<?= v2_e($featuredGuide['href']) ?>" tabindex="-1" aria-hidden="true"><?= $featuredGuide['photo'] ? v2_photo($featuredGuide['photo']) : v2_fallback($featuredGuide['title']) ?></a>
          <div class="gd-feature-body">
            <p class="kicker">Ghid recomandat</p>
            <h2><a href="<?= v2_e($featuredGuide['href']) ?>"><?= v2_e($featuredGuide['title']) ?></a></h2>
            <?php if ($featuredGuide['excerpt'] !== ''): ?><p><?= v2_e($featuredGuide['excerpt']) ?></p><?php endif; ?>
            <a class="btn btn-primary" href="<?= v2_e($featuredGuide['href']) ?>">Citește ghidul<?= v2_ic('arrow-right') ?></a>
          </div>
        </article>
      </div>
      <?php endif; ?>
    </div>
  </section>
  <div id="hdr-sentinel" aria-hidden="true"></div>

  <!-- ===================== TOPICS + GUIDES ===================== -->
  <section class="sec ct-main" id="lista" aria-labelledby="ct-title">
    <div class="wrap ct-layout">
      <aside class="ct-side" aria-label="Filtrează după topic">
        <div class="ct-filter">
          <p class="kicker">Topicuri</p>
          <ul class="ct-regions">
            <li><button type="button" data-topic="all" data-label="Toate ghidurile" aria-pressed="true">Toate ghidurile<span><?= count($guides) ?></span></button></li>
            <?php foreach ($topics as $topic): ?>
            <li><button type="button" data-topic="<?= v2_e($topic['key']) ?>" data-label="<?= v2_e($topic['label']) ?>" aria-pressed="false"><?= v2_e($topic['label']) ?><span><?= $topic['count'] ?></span></button></li>
            <?php endforeach; ?>
          </ul>
        </div>
      </aside>

      <div>
        <div class="ct-head">
          <div><p class="kicker">Ghiduri</p><h2 id="ct-title" tabindex="-1">Toate ghidurile</h2></div>
          <p id="ct-count" aria-live="polite"><?= count($guides) ?> din <?= count($guides) ?> ghiduri</p>
        </div>

        <?php if (!$guides): ?>
        <div class="ct-none">
          <span class="ct-none-ic"><?= v2_ic('list') ?></span>
          <p>Încă nu sunt ghiduri publicate.</p>
          <p class="gd-none-text">Revino în curând — pregătim conținut editorial pentru activități.</p>
          <a class="btn btn-primary" href="/categorii">Explorează categorii<?= v2_ic('arrow-right') ?></a>
        </div>
        <?php else: ?>
        <ul class="gd-cards" id="gd-grid">
          <?php foreach ($guides as $gi => $g): ?>
          <li class="gd-card" data-topic-key="<?= v2_e($g['topic']) ?>" data-q="<?= v2_e(implode(' ', [$g['title'], $g['excerpt'], $g['topicLabel']])) ?>">
            <a href="<?= v2_e($g['href']) ?>">
              <span class="gd-card-media"><?= $g['photo'] ? v2_photo($g['photo']) : v2_fallback($g['title'], $gi) ?></span>
              <span class="gd-card-meta"><span class="gd-card-topic"><?= v2_e($g['topicLabel']) ?></span><span class="gd-card-time"><?= v2_ic('clock') ?><?= v2_e($g['readTime']) ?></span></span>
              <h3 class="gd-card-title"><?= v2_e($g['title']) ?></h3>
              <?php if ($g['excerpt'] !== ''): ?><span class="gd-card-text"><?= v2_e($g['excerpt']) ?></span><?php endif; ?>
            </a>
          </li>
          <?php endforeach; ?>
        </ul>
        <div class="ct-none" id="ct-none" hidden>
          <span class="ct-none-ic"><?= v2_ic('magnifying-glass') ?></span>
          <p>Niciun ghid nu se potrivește căutării.</p>
          <button class="btn btn-ghost" type="button" id="ct-reset">Arată toate ghidurile</button>
        </div>
        <?php endif; ?>
      </div>
    </div>
  </section>

  <!-- ===================== ACTIVITIES BY CONTEXT ===================== -->
  <section class="sec gd-context" aria-labelledby="gd-context-h">
    <div class="wrap">
      <div class="gd-context-head">
        <p class="kicker">Activități după context</p>
        <h2 id="gd-context-h">Pornește de la ce ai chef azi.</h2>
      </div>
      <ul class="gd-context-grid">
        <?php foreach ($contextTiles as [$tIcon, $tKicker, $tTitle, $tText, $tHref, $tTone]): ?>
        <li><a class="gd-ctx <?= $tTone ?>" href="<?= v2_e($tHref) ?>">
          <span class="gd-ctx-ic" aria-hidden="true"><?= v2_ic($tIcon) ?></span>
          <small><?= v2_e($tKicker) ?></small>
          <b><?= v2_e($tTitle) ?></b>
          <span><?= v2_e($tText) ?></span>
          <span class="gd-ctx-go" aria-hidden="true"><?= v2_ic('arrow-right') ?></span>
        </a></li>
        <?php endforeach; ?>
      </ul>
    </div>
  </section>
</main>
<?php include __DIR__ . '/includes/v2/footer.php'; ?>
