<?php
/**
 * Single guide: /ghiduri/{slug} (v2 design).
 *
 * One editorial guide authored in /marketplace/blog-articles. The body is the admin RichEditor's HTML, shown as
 * written; `[activities …]` shortcodes in it become activity cards: ids="1,5,9" hand-picked, or city="…"
 * category="…" limit="6" sort="recent|cheapest|soon" pulled from the listing; style="small|large|long". FAQs come
 * from the guide, with a generic set when it has none. There is deliberately no table of contents: the body has no
 * stable heading anchors.
 *
 * Top to bottom: hero (topic, read time, date, title, excerpt, and the guide's own image in an arch beside them),
 * "activities for this guide" strip, body + FAQ
 * beside the sidebar (linked event, gift card, share), recommended activities rail, related guides.
 */

$pageCacheTTL = 300;
require_once __DIR__ . '/includes/page-cache.php';
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/api.php';
require_once __DIR__ . '/includes/nav-helpers.php';

$slug = $_GET['slug'] ?? '';
if (!is_string($slug) || !preg_match('/^[a-z0-9][a-z0-9-]*$/', $slug)) {
    http_response_code(404);
    require __DIR__ . '/404.php';
    exit;
}

$resp = api_cached("guide_detail_{$slug}", fn () => api_get('/blog-articles/' . urlencode($slug)), 180);
$article = $resp['data']['article'] ?? $resp['data'] ?? null;
if (!is_array($article) || empty($article['title'])) {
    http_response_code(404);
    require __DIR__ . '/404.php';
    exit;
}

require_once __DIR__ . '/includes/v2/helpers.php';
require_once __DIR__ . '/includes/v2/nav.php';

// ------------------------------------------------------------------ activity cards (rail + shortcodes)
// One card (base.css .xp) for the recommendations rail and every shortcode layout; "long" only changes the CSS.
$gdCard = function (array $card, int $pos, string $extraClass = ''): string {
    $media = $card['image'] ? v2_photo([$card['image'], 0, 0, '']) : v2_fallback($card['title'], $pos);
    $badge = $card['catName'] !== '' ? '<span class="xp-badges"><span>' . v2_e($card['catName']) . '</span></span>' : '';
    $meta = ($card['city'] !== '' ? '<span>' . v2_ic('map-pin') . v2_e($card['city']) . '</span>' : '')
        . ($card['dur'] !== '' ? '<span>' . v2_ic('clock') . v2_e($card['dur']) . '</span>' : '');
    $price = $card['price'] > 0 ? '<span class="xp-price">de la<b>' . v2_thousands($card['price']) . ' lei</b></span>' : '';
    return '<li class="xp' . $extraClass . '"><a href="' . v2_e($card['href']) . '"><span class="xp-media">' . $media . $badge . '</span>'
        . '<span class="xp-body"><span class="xp-title">' . v2_e($card['title']) . '</span><span class="xp-meta">' . $meta . '</span>'
        . '<span class="xp-foot"><span class="xp-go">' . v2_ic('arrow-right') . '</span>' . $price . '</span></span></a></li>';
};

$gdShortcode = function (string $shortcode) use ($gdCard): string {
    // The RichEditor stores attribute quotes as &quot; and may turn them typographic: back to straight quotes.
    $sc = html_entity_decode($shortcode, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    $sc = str_replace(['“', '”', '„', '‟', '″', '＂', '«', '»'], '"', $sc);
    preg_match_all('/([a-z_]+)\s*=\s*["\']([^"\']*)["\']/i', $sc, $found, PREG_SET_ORDER);
    $attr = [];
    foreach ($found as $pair) {
        $attr[strtolower($pair[1])] = $pair[2];
    }
    $style = in_array($attr['style'] ?? '', ['small', 'large', 'long'], true) ? $attr['style'] : 'small';

    $params = [];
    if (!empty($attr['ids'])) {
        $ids = trim(preg_replace('/[^0-9,]/', '', $attr['ids']), ',');
        if ($ids === '') {
            return '';
        }
        $params['ids'] = $ids;
        $params['per_page'] = max(1, min(24, substr_count($ids, ',') + 1));
    } else {
        if (!empty($attr['city'])) {
            $params['city'] = $attr['city'];
        }
        if (!empty($attr['category'])) {
            $params['category'] = $attr['category'];
        }
        if (!$params) {
            return '';
        }
        $params['per_page'] = max(1, min(24, (int) ($attr['limit'] ?? 6)));
        $params['sort'] = in_array($attr['sort'] ?? '', ['recent', 'cheapest', 'soon'], true) ? $attr['sort'] : 'recent';
    }

    $listResp = api_cached('guide_acts_' . md5(json_encode($params)), fn () => api_get('/activities', $params), 300);
    $html = '';
    foreach ((array) ($listResp['data']['items'] ?? $listResp['data']['data'] ?? []) as $pos => $a) {
        if (is_array($a) && ($card = v2_activity($a))) {
            $html .= $gdCard($card, (int) $pos, $style === 'long' ? ' gd-long' : '');
        }
    }
    return $html === '' ? '' : '<ul class="gd-acts is-' . $style . '">' . $html . '</ul>';
};

$gdRenderShortcodes = function (string $html) use ($gdShortcode): string {
    if (stripos($html, '[activities') === false) {
        return $html;
    }
    // Usually wrapped in <p>…</p> by the editor: the cards replace the paragraph, then any bare shortcode.
    $html = preg_replace_callback('#<p>\s*(\[activities\b[^\]]*\])\s*</p>#i', fn ($m) => $gdShortcode($m[1]), $html);
    return preg_replace_callback('#\[activities\b[^\]]*\]#i', fn ($m) => $gdShortcode($m[0]), $html);
};

// ------------------------------------------------------------------ guide
$title = navFlatName($article['title']);
$excerpt = trim((string) ($article['excerpt'] ?? ''));
$cat = is_array($article['category'] ?? null) ? $article['category'] : [];
$catSlug = preg_match('/^[a-z0-9][a-z0-9-]*$/', (string) ($cat['slug'] ?? '')) ? (string) $cat['slug'] : '';
$catRaw = navFlatName($cat['name'] ?? '');
$catName = $catRaw !== '' ? (V2_BLOG_CATEGORIES[$catRaw] ?? $catRaw) : '';
// The topic is a blog category: a page of its own only when an activity category has the same slug, otherwise the
// guides filtered by it (/ghiduri-de-oras, /aventura… don't exist).
$topicHref = $catSlug === '' ? '' : (isset($V2NAV['categoryBySlug'][$catSlug]) ? '/' . $catSlug : '/ghiduri?topic=' . rawurlencode($catSlug));
$readTime = (int) ($article['read_time'] ?? 0) > 0 ? (int) $article['read_time'] . ' min' : '5 min';

$publishedAt = (string) ($article['published_at'] ?? '') ?: (string) ($article['created_at'] ?? '');
$ts = $publishedAt !== '' ? strtotime($publishedAt) : false;
$gdMonths = [1 => 'ianuarie', 2 => 'februarie', 3 => 'martie', 4 => 'aprilie', 5 => 'mai', 6 => 'iunie', 7 => 'iulie', 8 => 'august', 9 => 'septembrie', 10 => 'octombrie', 11 => 'noiembrie', 12 => 'decembrie'];
$dateIso = $ts ? date('c', $ts) : null;
$dateLabel = $ts ? (int) date('j', $ts) . ' ' . $gdMonths[(int) date('n', $ts)] . ' ' . date('Y', $ts) : '';

// Only the guide's own image (uploaded in the admin) is shown: no stand-in photos.
$coverUrl = v2_media_url($article['image_url'] ?? null);
$content = (string) ($article['content'] ?? '');
$contentHtml = trim(strip_tags($content, '<img><iframe>')) !== '' ? $gdRenderShortcodes($content) : '';

$event = is_array($article['event'] ?? null) && preg_match('/^[a-z0-9][a-z0-9-]*$/', (string) ($article['event']['slug'] ?? '')) ? $article['event'] : null;
$eventTitle = $event ? navFlatName($event['title'] ?? $event['name'] ?? '') : '';

$faqs = [];
foreach ((array) ($article['faqs'] ?? []) as $faq) {
    $faqQ = is_array($faq) ? trim((string) ($faq['q'] ?? $faq['question'] ?? '')) : '';
    $faqA = is_array($faq) ? trim((string) ($faq['a'] ?? $faq['answer'] ?? '')) : '';
    if ($faqQ !== '' && $faqA !== '') {
        $faqs[] = [$faqQ, $faqA];
    }
}
if (!$faqs) {
    $faqs = [
        ['Cum cumpăr bilete pentru activitățile din ghid?', 'Deschizi activitatea recomandată, alegi data și ora disponibile, completezi datele și primești biletul cu cod QR pe email.'],
        ['Trebuie să printez biletul?', 'Nu. Poți arăta codul QR de pe telefon. Dacă o locație cere altceva, găsești informația pe pagina activității.'],
        ['Pot anula sau reprograma?', 'Politica de anulare este stabilită de fiecare locație și apare pe pagina activității înainte de plată.'],
    ];
}

// Related guides: same topic, not this one.
$relatedResp = api_cached("guides_related_{$catSlug}", function () use ($catSlug) {
    $params = ['per_page' => 4, 'status' => 'published'];
    if ($catSlug !== '') {
        $params['category'] = $catSlug;
    }
    return api_get('/blog-articles', $params);
}, 300);
$related = [];
foreach ((array) ($relatedResp['data']['articles'] ?? $relatedResp['data'] ?? []) as $r) {
    $rSlug = is_array($r) ? (string) ($r['slug'] ?? '') : '';
    $rTitle = is_array($r) ? navFlatName($r['title'] ?? '') : '';
    if ($rSlug === $slug || $rTitle === '' || !preg_match('/^[a-z0-9][a-z0-9-]*$/', $rSlug)) {
        continue;
    }
    $rImage = v2_media_url($r['image_url'] ?? null);
    $rCat = is_array($r['category'] ?? null) ? navFlatName($r['category']['name'] ?? '') : '';
    $related[] = [
        'title' => $rTitle,
        'href' => '/ghiduri/' . $rSlug,
        'excerpt' => trim((string) ($r['excerpt'] ?? '')),
        'photo' => $rImage ? [$rImage, 0, 0, ''] : (isset(V2_GUIDE_THUMBS[$rSlug]) ? [v2_asset(V2_GUIDE_THUMBS[$rSlug]), 0, 0, ''] : null),
        'kicker' => $rCat !== '' ? (V2_BLOG_CATEGORIES[$rCat] ?? $rCat) : 'Ghid',
    ];
    if (count($related) >= 3) {
        break;
    }
}

// Every guide ends with a few bookable activities, whatever its shortcodes.
$railResp = api_cached('guide_rail_' . $slug, fn () => api_get('/activities', ['per_page' => 16, 'sort' => 'recent']), 300);
$recommended = [];
foreach ((array) ($railResp['data']['items'] ?? []) as $a) {
    if (is_array($a) && ($card = v2_activity($a))) {
        $recommended[] = $card;
    }
    if (count($recommended) >= 10) {
        break;
    }
}

// ------------------------------------------------------------------ page
$breadcrumbs = [['name' => 'Acasă', 'url' => '/'], ['name' => 'Ghiduri', 'url' => '/ghiduri'], ['name' => $title, 'url' => '/ghiduri/' . $slug]];
$canonicalUrl = SITE_URL . '/ghiduri/' . $slug;
$shareLinks = [
    ['Facebook', 'https://www.facebook.com/sharer/sharer.php?u=' . rawurlencode($canonicalUrl)],
    ['WhatsApp', 'https://wa.me/?text=' . rawurlencode($title . ' ' . $canonicalUrl)],
    ['Email', 'mailto:?subject=' . rawurlencode($title) . '&body=' . rawurlencode($canonicalUrl)],
];

$pageTitleRaw = $title . ' — ghid | ' . SITE_NAME;
$descSource = $excerpt !== '' ? $excerpt : 'Ghid de activități pe ' . SITE_NAME . ': ' . $title;
if (mb_strlen($descSource) > 160) {
    $cut = mb_substr($descSource, 0, 160);
    $descSource = rtrim(mb_substr($cut, 0, mb_strrpos($cut, ' ') ?: 160), ' ,.;:–-') . '…';
}
$pageDescription = $descSource;
$ogImage = $coverUrl;

$gdClean = function (array $data) use (&$gdClean): array {
    foreach ($data as $k => $v) {
        if (is_array($v)) {
            $data[$k] = $v = $gdClean($v);
        }
        if ($v === null || $v === '' || $v === []) {
            unset($data[$k]);
        }
    }
    return $data;
};
$structuredData = [$gdClean([
    '@context' => 'https://schema.org',
    '@type' => 'Article',
    'headline' => $title,
    'description' => $pageDescription,
    'url' => $canonicalUrl,
    'mainEntityOfPage' => $canonicalUrl,
    'image' => $coverUrl,
    'datePublished' => $dateIso,
    'articleSection' => $catName,
    'inLanguage' => 'ro-RO',
    'author' => ['@type' => 'Organization', 'name' => SITE_NAME],
    'publisher' => ['@type' => 'Organization', 'name' => SITE_NAME, 'url' => SITE_URL . '/'],
]), [
    '@context' => 'https://schema.org',
    '@type' => 'FAQPage',
    'mainEntity' => array_map(fn ($f) => ['@type' => 'Question', 'name' => $f[0], 'acceptedAnswer' => ['@type' => 'Answer', 'text' => $f[1]]], $faqs),
], [
    '@context' => 'https://schema.org',
    '@type' => 'BreadcrumbList',
    'itemListElement' => array_map(fn ($bc, $pos) => ['@type' => 'ListItem', 'position' => $pos + 1, 'name' => $bc['name'], 'item' => SITE_URL . $bc['url']], $breadcrumbs, array_keys($breadcrumbs)),
]];

$v2Styles = ['guide.css'];
$v2Scripts = ['guide.js'];
$v2HeaderOverlay = true;
$v2HeadExtra = $coverUrl ? '<link rel="preload" as="image" href="' . v2_e($coverUrl) . '" fetchpriority="high">' : '';

include __DIR__ . '/includes/v2/head.php';
include __DIR__ . '/includes/v2/header.php';
?>
<main id="main" tabindex="-1">
  <article class="gd" aria-labelledby="gd-h">
    <!-- ===================== HERO ===================== -->
    <header class="gd-hero<?= $coverUrl ? ' has-art' : '' ?>">
      <svg class="deco-arches" viewBox="0 0 400 400" aria-hidden="true" focusable="false"><path d="M40 400V200a160 160 0 0 1 320 0v200"/><path d="M90 400V200a110 110 0 0 1 220 0v200"/><path d="M140 400V200a60 60 0 0 1 120 0v200"/></svg>
      <svg class="gd-line draw-clip" viewBox="0 590 3240 310" aria-hidden="true" focusable="false"><use href="#drum-g"/></svg>
      <div class="wrap gd-hero-in">
        <div class="gd-hero-copy">
        <nav class="crumbs" aria-label="Breadcrumb">
          <?php foreach ($breadcrumbs as $bi => $bc): ?>
            <?php if ($bi > 0): ?><span aria-hidden="true">/</span><?php endif; ?>
            <?php if ($bi < count($breadcrumbs) - 1): ?><a href="<?= v2_e($bc['url']) ?>"><?= v2_e($bc['name']) ?></a><?php else: ?><span aria-current="page"><?= v2_e($bc['name']) ?></span><?php endif; ?>
          <?php endforeach; ?>
        </nav>
        <p class="gd-meta">
          <?php if ($catName !== ''): ?><?php if ($topicHref !== ''): ?><a class="gd-topic-link" href="<?= v2_e($topicHref) ?>"><?= v2_e($catName) ?></a><?php else: ?><span class="gd-topic-link"><?= v2_e($catName) ?></span><?php endif; ?><?php endif; ?>
          <span><?= v2_ic('clock') ?><?= v2_e($readTime) ?> citire</span>
          <?php if ($dateLabel !== ''): ?><span><?= v2_ic('calendar-blank') ?><time datetime="<?= v2_e($dateIso) ?>"><?= v2_e($dateLabel) ?></time></span><?php endif; ?>
        </p>
        <h1 class="gd-h" id="gd-h"><?= v2_e($title) ?></h1>
        <?php if ($excerpt !== ''): ?><p class="gd-lead"><?= v2_e($excerpt) ?></p><?php endif; ?>
        </div>
        <?php if ($coverUrl): ?>
        <figure class="gd-art">
          <span class="gd-art-ring" aria-hidden="true"></span>
          <span class="gd-art-frame"><img src="<?= v2_e($coverUrl) ?>" alt="<?= v2_e($title) ?>" fetchpriority="high" decoding="async"></span>
          <span class="gd-art-tag" aria-hidden="true"><?= v2_ic('clock') ?><?= v2_e($readTime) ?> de citit<?php if ($catName !== ''): ?><b><?= v2_e($catName) ?></b><?php endif; ?></span>
          <svg class="gd-art-line" viewBox="900 585 2340 310" aria-hidden="true" focusable="false"><use href="#drum-g"/></svg>
        </figure>
        <?php endif; ?>
      </div>
    </header>
    <div id="hdr-sentinel" aria-hidden="true"></div>


    <?php if ($topicHref !== ''): ?>
    <!-- ===================== ACTIVITIES FOR THIS GUIDE ===================== -->
    <div class="wrap">
      <div class="gd-strip">
        <div><small>Vrei direct activități?</small><b>Vezi activități legate de acest ghid.</b></div>
        <div class="gd-strip-cta">
          <a class="btn btn-primary" href="<?= v2_e($topicHref) ?>"><?= v2_e($catName ?: 'Vezi activități') ?><?= v2_ic('arrow-right') ?></a>
          <a class="btn btn-ghost" href="/categorii">Toate categoriile</a>
        </div>
      </div>
    </div>
    <?php endif; ?>

    <!-- ===================== BODY + SIDEBAR ===================== -->
    <div class="sec gd-body">
      <div class="wrap gd-layout">
        <div class="gd-main">
          <div class="gd-prose">
            <?php if ($contentHtml !== ''): ?>
            <?= $contentHtml /* authored in the admin RichEditor: trusted HTML */ ?>
            <?php else: ?>
            <p class="gd-empty">Conținutul acestui ghid va fi disponibil în curând.</p>
            <?php endif; ?>
          </div>

          <section class="gd-faq" aria-labelledby="faq">
            <h2 id="faq">Întrebări frecvente</h2>
            <?php foreach ($faqs as $fi => [$faqQ, $faqA]): ?>
            <details class="qa"<?= $fi === 0 ? ' open' : '' ?>><summary><?= v2_e($faqQ) ?><span class="pm"><?= v2_ic('plus') ?></span></summary><p><?= v2_e($faqA) ?></p></details>
            <?php endforeach; ?>
          </section>
        </div>

        <aside class="gd-side" aria-label="Mai departe">
          <?php if ($event): ?>
          <div class="gd-card is-event">
            <small>Eveniment</small>
            <h3><?= v2_e($eventTitle) ?></h3>
            <a class="btn btn-primary" href="/bilete/<?= v2_e($event['slug']) ?>">Vezi biletele<?= v2_ic('arrow-right') ?></a>
          </div>
          <?php endif; ?>

          <div class="gd-card is-gift">
            <small>Card cadou</small>
            <h3>Nu știi ce să alegi?</h3>
            <p>Trimite un card cadou și lasă destinatarul să aleagă experiența.</p>
            <a class="btn btn-primary" href="/card-cadou"><?= v2_ic('gift') ?>Cumpără card</a>
          </div>

          <div class="gd-card is-share">
            <small>Share</small>
            <h3>Trimite ghidul</h3>
            <div class="gd-share">
              <button class="is-native" type="button" data-native-share data-title="<?= v2_e($title) ?>" data-url="<?= v2_e($canonicalUrl) ?>" hidden>Trimite prin aplicații</button>
              <?php foreach ($shareLinks as [$shareLabel, $shareUrl]): ?>
              <a href="<?= v2_e($shareUrl) ?>"<?= $shareLabel !== 'Email' ? ' target="_blank" rel="noopener"' : '' ?> aria-label="Trimite ghidul pe <?= v2_e($shareLabel) ?>"><?= v2_e($shareLabel) ?></a>
              <?php endforeach; ?>
              <button type="button" data-copy="<?= v2_e($canonicalUrl) ?>">Copiază linkul</button>
            </div>
            <span class="sr" role="status" id="gd-copy-status"></span>
          </div>
        </aside>
      </div>
    </div>
  </article>

  <?php if ($recommended): ?>
  <!-- ===================== RECOMMENDED ACTIVITIES ===================== -->
  <section class="sec gd-rail" aria-labelledby="gd-rail-h">
    <div class="wrap">
      <div class="sec-head">
        <h2 id="gd-rail-h">Activități recomandate</h2>
        <div class="rail-btns" data-for="gd-rail-list">
          <button class="rail-btn" type="button" data-dir="-1" aria-label="Activitățile anterioare"><?= v2_ic('arrow-left') ?></button>
          <button class="rail-btn" type="button" data-dir="1" aria-label="Activitățile următoare"><?= v2_ic('arrow-right') ?></button>
        </div>
      </div>
      <ul class="rail" id="gd-rail-list">
        <?php foreach ($recommended as $ri => $card): ?><?= $gdCard($card, $ri) ?><?php endforeach; ?>
      </ul>
    </div>
  </section>
  <?php endif; ?>

  <?php if ($related): ?>
  <!-- ===================== RELATED GUIDES ===================== -->
  <section class="sec gd-related" aria-labelledby="gd-related-h">
    <div class="wrap">
      <div class="sec-head">
        <h2 id="gd-related-h">Ghiduri similare</h2>
        <a class="sec-link" href="/ghiduri">Toate ghidurile<?= v2_ic('arrow-right') ?></a>
      </div>
      <ul class="gd-rel-grid">
        <?php foreach ($related as $rgi => $rg): ?>
        <li><a class="gd-rel" href="<?= v2_e($rg['href']) ?>">
          <span class="gd-rel-media"><?= $rg['photo'] ? v2_photo($rg['photo']) : v2_fallback($rg['title'], $rgi) ?></span>
          <span class="gd-rel-body"><small><?= v2_e($rg['kicker']) ?></small><b><?= v2_e($rg['title']) ?></b><?php if ($rg['excerpt'] !== ''): ?><span><?= v2_e($rg['excerpt']) ?></span><?php endif; ?></span>
        </a></li>
        <?php endforeach; ?>
      </ul>
    </div>
  </section>
  <?php endif; ?>
</main>
<?php include __DIR__ . '/includes/v2/footer.php'; ?>
