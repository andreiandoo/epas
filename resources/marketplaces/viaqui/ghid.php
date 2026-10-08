<?php
/**
 * Single guide: /ghiduri/{slug} (v2 design).
 *
 * One editorial guide authored in /marketplace/blog-articles. The body is the admin RichEditor's HTML, shown as
 * written; `[activities …]` shortcodes in it become activity cards: ids="1,5,9" hand-picked, or city="…"
 * category="…" limit="6" sort="recent|cheapest|soon" pulled from the listing; style="small|large|long". FAQs come
 * from the guide, with a generic set when it has none. The body's h2/h3 get ids (kept when the editor set one) and make
 * the table of contents.
 *
 * A guide reads like an article, not like a listing: a light editorial head (title, dek, byline with date and read
 * time), the guide's own image wide under it, then the text in a reading column with the contents beside it
 * (a folding box on phones) and a reading-progress line. After the text: share, FAQ, where to go next (the linked
 * event, the topic's activities, the gift card), bookable activities, related guides to read next.
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
require_once __DIR__ . '/includes/v2/partners.php';

// ------------------------------------------------------------------ activity cards (rail + shortcodes)
// One card (base.css .xp) for the recommendations rail and every shortcode layout; "long" only changes the CSS.
$gdCard = function (array $card, int $pos, string $extraClass = ''): string {
    $media = $card['image'] ? v2_photo([$card['image'], 0, 0, '']) : v2_fallback($card['title'], $pos);
    $badge = $card['catName'] !== '' ? '<span class="xp-badges"><span>' . v2_e($card['catName']) . '</span></span>' : '';
    $meta = ($card['city'] !== '' ? '<span>' . v2_ic('map-pin') . v2_e($card['city']) . '</span>' : '')
        . ($card['dur'] !== '' ? '<span>' . v2_ic('clock') . v2_e($card['dur']) . '</span>' : '');
    $price = $card['price'] > 0 ? '<span class="xp-price">' . v2_te('from') . '<b>' . v2_e($card['priceLabel']) . '</b></span>' : '';
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

// [partner attraction="eiffel-tower" match="eiffel" limit="4"]: what our partner (WeGoTrip) sells for one of our
// attractions; or city="paris"; or q="vatican museums" (the partner's own search). `match` keeps only the products
// whose title has one of the words. Prices and ratings are read when the page is shown, never written in the text.
$gdPartnerShortcode = function (string $shortcode) use ($slug): string {
    $sc = html_entity_decode($shortcode, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    $sc = str_replace(['“', '”', '„', '‟', '″', '＂', '«', '»'], '"', $sc);
    preg_match_all('/([a-z_]+)\s*=\s*["\']([^"\']*)["\']/i', $sc, $found, PREG_SET_ORDER);
    $attr = [];
    foreach ($found as $pair) {
        $attr[strtolower($pair[1])] = trim($pair[2]);
    }
    $limit = max(1, min(12, (int) ($attr['limit'] ?? 3)));
    $place = preg_match('/^[a-z0-9-]+$/', $attr['attraction'] ?? '') ? $attr['attraction'] : '';
    $city = preg_match('/^[a-z0-9-]+$/', $attr['city'] ?? '') ? $attr['city'] : '';
    $match = $attr['match'] ?? '';
    if ($place !== '') {
        $items = v2_wegotrip_products('attraction', $place, 40, $match)['items'];
    } elseif ($city !== '') {
        $items = v2_wegotrip_products('city', $city, 40, $match)['items'];
    } elseif (($attr['q'] ?? '') !== '') {
        $items = v2_wegotrip_search(mb_substr($attr['q'], 0, 80), 40);
    } else {
        return '';
    }
    $words = array_filter(preg_split('/[^a-z0-9]+/', strtolower(v2_partner_ascii($match))), fn ($w) => strlen($w) >= 3);
    $seen = [];
    $keep = [];
    foreach ($items as $p) {
        $title = strtolower(v2_partner_ascii($p['title']));
        if ($words && !array_filter($words, fn ($w) => strpos($title, $w) !== false)) {
            continue;
        }
        if (isset($seen[$title]) || !($p['price'] > 0)) {      // the same product listed twice, or one that cannot be priced
            continue;
        }
        $seen[$title] = true;
        $keep[] = $p;
        if (count($keep) >= $limit) {
            break;
        }
    }
    if (!$keep) {
        return '';
    }
    return '<ul class="gd-acts is-small gd-partner">' . v2_partner_cards($keep, 'wegotrip', 'guide-' . $slug) . '</ul>' . v2_partner_note('wegotrip');
};

$gdRenderShortcodes = function (string $html) use ($gdShortcode, $gdPartnerShortcode): string {
    if (stripos($html, '[partner') !== false) {
        // like the activities block: wrapped in a paragraph by the editor; with nothing to show, the line that
        // announces it (the paragraph before it, when it ends in a colon) goes too
        $html = preg_replace_callback('#(<p>(?:(?!</p>).)*:\s*</p>\s*)?<p>\s*(\[partner[^\]]*\])\s*</p>#is', function ($m) use ($gdPartnerShortcode) {
            $cards = $gdPartnerShortcode($m[2]);
            return $cards === '' ? '' : $m[1] . $cards;
        }, $html);
        $html = preg_replace_callback('#\[partner[^\]]*\]#i', fn ($m) => $gdPartnerShortcode($m[0]), $html);
    }
    if (stripos($html, '[activities') === false) {
        return $html;
    }
    // Usually wrapped in <p>…</p> by the editor: the cards replace the paragraph, then any bare shortcode. A block
    // with nothing to show takes along the line that announces it (the paragraph before it, when it ends in a colon).
    $html = preg_replace_callback('#(<p>(?:(?!</p>).)*:\s*</p>\s*)?<p>\s*(\[activities\b[^\]]*\])\s*</p>#is', function ($m) use ($gdShortcode) {
        $cards = $gdShortcode($m[2]);
        return $cards === '' ? '' : $m[1] . $cards;
    }, $html);
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
$topicHref = $catSlug === '' ? '' : (isset($V2NAV['categoryBySlug'][$catSlug]) ? '/' . $catSlug : '/guides?topic=' . rawurlencode($catSlug));
$readMinutes = (int) ($article['read_time'] ?? 0) > 0 ? (int) $article['read_time'] : 5;

$publishedAt = (string) ($article['published_at'] ?? '') ?: (string) ($article['created_at'] ?? '');
$ts = $publishedAt !== '' ? strtotime($publishedAt) : false;
$gdMonths = [1 => v2_t('January'), 2 => v2_t('February'), 3 => v2_t('March'), 4 => v2_t('April'), 5 => v2_t('May'), 6 => v2_t('June'), 7 => v2_t('July'), 8 => v2_t('August'), 9 => v2_t('September'), 10 => v2_t('October'), 11 => v2_t('November'), 12 => v2_t('December')];
$dateIso = $ts ? date('c', $ts) : null;
$dateLabel = $ts ? v2_t('{day} {month} {year}', ['day' => (int) date('j', $ts), 'month' => $gdMonths[(int) date('n', $ts)], 'year' => date('Y', $ts)]) : '';

// Only the guide's own image (uploaded in the admin) is shown: no stand-in photos.
$coverUrl = v2_media_url($article['image_url'] ?? null);
$content = (string) ($article['content'] ?? '');

// What place the guide is about, for the closing section (experiences and attractions there): a line
// [place attraction="eiffel-tower" city="paris"] anywhere in the body (it prints nothing), else the place of the first
// [partner …] block.
$gdPlace = ['attraction' => '', 'city' => ''];
$gdAttrs = function (string $shortcode): array {
    $sc = html_entity_decode($shortcode, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    $sc = str_replace(['“', '”', '„', '‟', '″', '＂', '«', '»'], '"', $sc);
    preg_match_all('/([a-z_]+)\s*=\s*["\']([^"\']*)["\']/i', $sc, $found, PREG_SET_ORDER);
    $attr = [];
    foreach ($found as $pair) {
        $attr[strtolower($pair[1])] = trim($pair[2]);
    }
    return $attr;
};
if (preg_match('/\[place\s[^\]]*\]/i', $content, $gdM) || preg_match('/\[partner\s[^\]]*\]/i', $content, $gdM)) {
    $gdA = $gdAttrs($gdM[0]);
    foreach (['attraction', 'city'] as $gdK) {
        if (preg_match('/^[a-z0-9-]+$/', $gdA[$gdK] ?? '')) {
            $gdPlace[$gdK] = $gdA[$gdK];
        }
    }
}
$content = preg_replace('#<p>\s*\[place\s[^\]]*\]\s*</p>|\[place\s[^\]]*\]#i', '', $content);
$contentHtml = trim(strip_tags($content, '<img><iframe>')) !== '' ? $gdRenderShortcodes($content) : '';

// Contents: every h2/h3 of the body gets an id (the editor's own when set) and an entry; h3 nest under their h2.
$gdToc = [];
if ($contentHtml !== '') {
    $gdUsed = [];
    $contentHtml = preg_replace_callback('#<(h[23])(\b[^>]*)>(.*?)</\1>#is', function ($m) use (&$gdToc, &$gdUsed) {
        $text = trim(preg_replace('/\s+/u', ' ', html_entity_decode(strip_tags($m[3]), ENT_QUOTES | ENT_HTML5, 'UTF-8')));
        if ($text === '') {
            return $m[0];
        }
        $attrs = $m[2];
        if (preg_match('/\sid\s*=\s*["\']([^"\']+)["\']/i', $attrs, $idm)) {
            $id = $idm[1];
        } else {
            $id = trim(preg_replace('/[^a-z0-9]+/', '-', strtr(mb_strtolower($text), ['ă' => 'a', 'â' => 'a', 'î' => 'i', 'ș' => 's', 'ş' => 's', 'ț' => 't', 'ţ' => 't'])), '-');
            $id = mb_substr($id !== '' ? $id : 'section', 0, 60);
            $baseId = $id;
            for ($n = 2; isset($gdUsed[$id]); $n++) {
                $id = $baseId . '-' . $n;
            }
            $attrs .= ' id="' . htmlspecialchars($id, ENT_QUOTES) . '"';
        }
        $gdUsed[$id] = true;
        $level = (int) substr($m[1], 1);
        if ($level === 3 && $gdToc) {
            $gdToc[count($gdToc) - 1]['subs'][] = ['id' => $id, 'text' => $text];
        } else {
            $gdToc[] = ['id' => $id, 'text' => $text, 'subs' => [], 'level' => $level];
        }
        return '<' . $m[1] . $attrs . '>' . $m[3] . '</' . $m[1] . '>';
    }, $contentHtml);
}
// a contents list earns its place from two sections up
$gdTocCount = count($gdToc) + array_sum(array_map(fn ($t) => count($t['subs']), $gdToc));
if ($gdTocCount < 2) {
    $gdToc = [];
}

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
        [v2_t('How do I buy tickets for the activities in this guide?'), v2_t('Open the recommended activity, pick an available date and time, fill in your details and you get the ticket with a QR code by email.')],
        [v2_t('Do I need to print the ticket?'), v2_t('No. You can show the QR code on your phone. If a venue asks for something else, it says so on the activity page.')],
        [v2_t('Can I cancel or reschedule?'), v2_t('Each venue sets its own cancellation policy. You can read it on the activity page before you pay.')],
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
        'href' => '/guides/' . $rSlug,
        'excerpt' => trim((string) ($r['excerpt'] ?? '')),
        'photo' => $rImage ? [$rImage, 0, 0, ''] : (isset(V2_GUIDE_THUMBS[$rSlug]) ? [v2_asset(V2_GUIDE_THUMBS[$rSlug]), 0, 0, ''] : null),
        'kicker' => $rCat !== '' ? (V2_BLOG_CATEGORIES[$rCat] ?? $rCat) : v2_t('Guide'),
    ];
    if (count($related) >= 3) {
        break;
    }
}

// Where the guide is about: experiences to book there (our partner's) and attractions to see around it.
$gdPlaceName = '';      // "the Eiffel Tower" is not known as a phrase: the attraction's name, as it is
$gdCityName = '';
$gdSights = [];
$gdThere = [];
if ($gdPlace['attraction'] !== '') {
    $gdAtResp = api_cached('attraction_' . $gdPlace['attraction'], fn () => api_get('/attractions/' . rawurlencode($gdPlace['attraction'])), 900);
    $gdAt = is_array($gdAtResp['data']['attraction'] ?? null) ? $gdAtResp['data']['attraction'] : [];
    $gdPlaceName = navFlatName($gdAt['name'] ?? '');
    if ($gdPlace['city'] === '' && !empty($gdAt['city']['slug'])) {
        $gdPlace['city'] = (string) $gdAt['city']['slug'];
    }
    $gdCityName = is_array($gdAt['city'] ?? null) ? navFlatName($gdAt['city']['name'] ?? '') : '';
    foreach (array_merge((array) ($gdAt['nearby'] ?? []), (array) ($gdAt['city_attractions'] ?? [])) as $gdN) {
        if (is_array($gdN) && ($gdS = v2_attraction($gdN)) && $gdS['slug'] !== $gdPlace['attraction'] && !isset($gdSights[$gdS['slug']])) {
            $gdSights[$gdS['slug']] = $gdS;
        }
    }
}
if ($gdPlace['city'] !== '') {
    if ($gdCityName === '') {
        $gdCityName = (string) ($V2NAV['cities'][$gdPlace['city']]['name'] ?? mb_convert_case(str_replace('-', ' ', $gdPlace['city']), MB_CASE_TITLE, 'UTF-8'));
    }
    if (count($gdSights) < 4) {
        $gdListResp = api_cached('v2_attractions_' . md5(json_encode(['city' => $gdPlace['city'], 'per_page' => 12, 'page' => 1])), fn () => api_get('/attractions', ['city' => $gdPlace['city'], 'per_page' => 12, 'page' => 1]), 900);
        foreach ((array) ($gdListResp['data']['items'] ?? []) as $gdN) {
            if (is_array($gdN) && ($gdS = v2_attraction($gdN)) && $gdS['slug'] !== $gdPlace['attraction'] && !isset($gdSights[$gdS['slug']])) {
                $gdSights[$gdS['slug']] = $gdS;
            }
        }
    }
    $gdThere = array_slice(v2_wegotrip_city_all($gdPlace['city']), 0, 10);
}
$gdSights = array_slice(array_values($gdSights), 0, 10);

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
$breadcrumbs = [['name' => v2_t('Home'), 'url' => '/'], ['name' => v2_t('Guides'), 'url' => '/guides'], ['name' => $title, 'url' => '/guides/' . $slug]];
$canonicalUrl = SITE_URL . '/guides/' . $slug;
$shareLinks = [
    ['Facebook', 'https://www.facebook.com/sharer/sharer.php?u=' . rawurlencode($canonicalUrl)],
    ['WhatsApp', 'https://wa.me/?text=' . rawurlencode($title . ' ' . $canonicalUrl)],
    [v2_t('Email'), 'mailto:?subject=' . rawurlencode($title) . '&body=' . rawurlencode($canonicalUrl)],
];

$pageTitleRaw = v2_t('{title}: guide', ['title' => $title]) . ' | ' . SITE_NAME;
$descSource = $excerpt !== '' ? $excerpt : v2_t('Activity guide on {site}: {title}', ['site' => SITE_NAME, 'title' => $title]);
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
    'inLanguage' => v2_locale(),
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
$v2HeadExtra = $coverUrl ? '<link rel="preload" as="image" href="' . v2_e($coverUrl) . '" fetchpriority="high">' : '';

include __DIR__ . '/includes/v2/head.php';
include __DIR__ . '/includes/v2/header.php';
?>
<main id="main" tabindex="-1">
  <div class="gd-progress" aria-hidden="true"><span id="gd-progress"></span></div>
  <article class="gd" aria-labelledby="gd-h">
    <!-- ===================== HEAD ===================== -->
    <header class="gd-head">
      <div class="wrap gd-head-in">
        <nav class="crumbs" aria-label="<?= v2_te('Breadcrumb') ?>">
          <?php foreach ($breadcrumbs as $bi => $bc): ?>
            <?php if ($bi > 0): ?><span aria-hidden="true">/</span><?php endif; ?>
            <?php if ($bi < count($breadcrumbs) - 1): ?><a href="<?= v2_e($bc['url']) ?>"><?= v2_e($bc['name']) ?></a><?php else: ?><span aria-current="page"><?= v2_e($bc['name']) ?></span><?php endif; ?>
          <?php endforeach; ?>
        </nav>
        <h1 class="gd-h" id="gd-h"><?= v2_e($title) ?></h1>
        <?php if ($excerpt !== ''): ?><p class="gd-lead"><?= v2_e($excerpt) ?></p><?php endif; ?>
        <div class="gd-byline">
          <span class="gd-avatar" aria-hidden="true"><svg viewBox="24 53 148 150"><use href="#sym-g"/></svg></span>
          <p class="gd-by"><b><?= v2_te('Viaqui guide') ?></b><span><?php if ($dateLabel !== ''): ?><time datetime="<?= v2_e($dateIso) ?>"><?= v2_e($dateLabel) ?></time><span aria-hidden="true">·</span><?php endif; ?><?= v2_te('{n} min read', ['n' => $readMinutes]) ?></span></p>
          <a class="gd-share-jump" href="#gd-share"><?= v2_ic('link') ?><?= v2_te('Share this guide') ?></a>
        </div>
      </div>
      <?php if ($coverUrl): ?>
      <figure class="wrap gd-cover">
        <img src="<?= v2_e($coverUrl) ?>" alt="<?= v2_e($title) ?>" fetchpriority="high" decoding="async">
      </figure>
      <?php endif; ?>
    </header>

    <!-- ===================== TEXT ===================== -->
    <div class="gd-body">
      <div class="wrap gd-layout<?= $gdToc ? ' has-toc' : '' ?>">
        <?php if ($gdToc): ?>
        <nav class="gd-toc" aria-labelledby="gd-toc-h">
          <button class="gd-toc-toggle" type="button" id="gd-toc-toggle" aria-expanded="false" aria-controls="gd-toc-list"><span id="gd-toc-h"><?= v2_te('In this guide') ?></span><small><?= v2_e(v2_num($gdTocCount, 'section', 'sections')) ?></small><?= v2_ic('caret-down') ?></button>
          <ol class="gd-toc-list" id="gd-toc-list">
            <?php foreach ($gdToc as $ti => $t): ?>
            <li><a href="#<?= v2_e($t['id']) ?>" data-toc="<?= v2_e($t['id']) ?>"><?php if ($t['level'] === 2): ?><span class="gd-toc-n" aria-hidden="true"><?= str_pad((string) (count(array_filter(array_slice($gdToc, 0, $ti + 1), fn ($x) => $x['level'] === 2))), 2, '0', STR_PAD_LEFT) ?></span><?php endif; ?><span><?= v2_e($t['text']) ?></span></a>
              <?php if ($t['subs']): ?>
              <ol>
                <?php foreach ($t['subs'] as $sub): ?><li><a href="#<?= v2_e($sub['id']) ?>" data-toc="<?= v2_e($sub['id']) ?>" data-toc-parent="<?= v2_e($t['id']) ?>"><span><?= v2_e($sub['text']) ?></span></a></li><?php endforeach; ?>
              </ol>
              <?php endif; ?>
            </li>
            <?php endforeach; ?>
          </ol>
        </nav>
        <?php endif; ?>

        <div class="gd-main">
          <?php if ($event): ?>
          <aside class="gd-event" aria-label="<?= v2_te('Event in this guide') ?>">
            <span class="gd-event-ic" aria-hidden="true"><?= v2_ic('ticket') ?></span>
            <p><small><?= v2_te('Event in this guide') ?></small><b><?= v2_e($eventTitle) ?></b></p>
            <a class="btn btn-primary" href="/bilete/<?= v2_e($event['slug']) ?>"><?= v2_te('See tickets') ?><?= v2_ic('arrow-right') ?></a>
          </aside>
          <?php endif; ?>

          <div class="gd-prose" id="gd-prose">
            <?php if ($contentHtml !== ''): ?>
            <?= $contentHtml /* authored in the admin RichEditor: trusted HTML */ ?>
            <?php else: ?>
            <p class="gd-empty"><?= v2_te('This guide will be available soon.') ?></p>
            <?php endif; ?>
          </div>

          <section class="gd-end" id="gd-share" aria-labelledby="gd-share-h">
            <h2 class="gd-end-h" id="gd-share-h"><?= v2_te('Was it useful? Send it to someone you go out with.') ?></h2>
            <div class="gd-share">
              <button class="is-native" type="button" data-native-share data-title="<?= v2_e($title) ?>" data-url="<?= v2_e($canonicalUrl) ?>" hidden><?= v2_te('Share via apps') ?></button>
              <?php foreach ($shareLinks as [$shareLabel, $shareUrl]): ?>
              <a href="<?= v2_e($shareUrl) ?>"<?= strncmp($shareUrl, 'mailto:', 7) !== 0 ? ' target="_blank" rel="noopener"' : '' ?> aria-label="<?= strncmp($shareUrl, 'mailto:', 7) !== 0 ? v2_te('Share this guide on {network}', ['network' => $shareLabel]) : v2_te('Share this guide by email') ?>"><?= v2_e($shareLabel) ?></a>
              <?php endforeach; ?>
              <button type="button" data-copy="<?= v2_e($canonicalUrl) ?>"><?= v2_ic('link') ?><?= v2_te('Copy link') ?></button>
            </div>
            <span class="sr" role="status" id="gd-copy-status"></span>
          </section>

          <section class="gd-faq" aria-labelledby="faq">
            <h2 id="faq"><?= v2_te('Frequently asked questions') ?></h2>
            <?php foreach ($faqs as $fi => [$faqQ, $faqA]): ?>
            <details class="qa"<?= $fi === 0 ? ' open' : '' ?>><summary><?= v2_e($faqQ) ?><span class="pm"><?= v2_ic('plus') ?></span></summary><p><?= v2_e($faqA) ?></p></details>
            <?php endforeach; ?>
          </section>

          <section class="gd-next" aria-labelledby="gd-next-h">
            <h2 class="sr" id="gd-next-h"><?= v2_te('Where to next') ?></h2>
            <?php if ($topicHref !== ''): ?>
            <a class="gd-next-card is-topic" href="<?= v2_e($topicHref) ?>">
              <small><?= v2_te('Want to go straight to activities?') ?></small>
              <b><?= v2_te('See activities related to this guide') ?></b>
              <span class="gd-next-go"><?= $catName !== '' ? v2_e($catName) : v2_te('See activities') ?><?= v2_ic('arrow-right') ?></span>
            </a>
            <?php endif; ?>
            <a class="gd-next-card is-gift" href="/gift-card">
              <small><?= v2_te('Gift card') ?></small>
              <b><?= v2_te('Not sure what to pick? Send a gift card and let them choose the experience.') ?></b>
              <span class="gd-next-go"><?= v2_ic('gift') ?><?= v2_te('Buy a gift card') ?></span>
            </a>
            <p class="gd-next-more"><a href="/categories"><?= v2_te('All categories') ?><?= v2_ic('arrow-right') ?></a></p>
          </section>
        </div>
      </div>
    </div>
  </article>

  <?php if ($recommended): ?>
  <!-- ===================== RECOMMENDED ACTIVITIES ===================== -->
  <section class="sec gd-rail" aria-labelledby="gd-rail-h">
    <div class="wrap">
      <div class="sec-head">
        <div><p class="kicker"><?= v2_te('After reading') ?></p><h2 id="gd-rail-h"><?= v2_te('Recommended activities') ?></h2></div>
        <div class="rail-btns" data-for="gd-rail-list">
          <button class="rail-btn" type="button" data-dir="-1" aria-label="<?= v2_te('Previous activities') ?>"><?= v2_ic('arrow-left') ?></button>
          <button class="rail-btn" type="button" data-dir="1" aria-label="<?= v2_te('Next activities') ?>"><?= v2_ic('arrow-right') ?></button>
        </div>
      </div>
      <ul class="rail" id="gd-rail-list">
        <?php foreach ($recommended as $ri => $card): ?><?= $gdCard($card, $ri) ?><?php endforeach; ?>
      </ul>
    </div>
  </section>
  <?php endif; ?>

  <?php if ($gdThere || $gdSights): ?>
  <!-- ===================== AROUND THE PLACE OF THE GUIDE ===================== -->
  <section class="sec gd-rail gd-around" aria-labelledby="gd-around-h">
    <div class="wrap">
      <?php if ($gdThere): ?>
      <div class="sec-head">
        <div><p class="kicker"><?= v2_te('Book for your trip') ?></p><h2 id="gd-around-h"><?= v2_te('Experiences in {city}', ['city' => $gdCityName]) ?></h2></div>
        <div class="rail-btns" data-for="gd-there-list">
          <button class="rail-btn" type="button" data-dir="-1" aria-label="<?= v2_te('Previous experiences') ?>"><?= v2_ic('arrow-left') ?></button>
          <button class="rail-btn" type="button" data-dir="1" aria-label="<?= v2_te('Next experiences') ?>"><?= v2_ic('arrow-right') ?></button>
        </div>
      </div>
      <ul class="rail" id="gd-there-list">
        <?= v2_partner_cards($gdThere, 'wegotrip', 'guide-' . $slug . '-more') ?>
      </ul>
      <?= v2_partner_note('wegotrip') ?>
      <p class="gd-around-more"><a class="sec-link" href="/<?= v2_e($gdPlace['city']) ?>"><?= v2_te('Everything to do in {city}', ['city' => $gdCityName]) ?><?= v2_ic('arrow-right') ?></a></p>
      <?php endif; ?>
      <?php if ($gdSights): ?>
      <div class="sec-head<?= $gdThere ? ' gd-around-second' : '' ?>">
        <div><p class="kicker"><?= v2_te('See while you are there') ?></p><h2<?= $gdThere ? '' : ' id="gd-around-h"' ?>><?= $gdPlaceName !== '' ? v2_te('Attractions near {place}', ['place' => $gdPlaceName]) : v2_te('Attractions in {city}', ['city' => $gdCityName]) ?></h2></div>
        <div class="rail-btns" data-for="gd-sights-list">
          <button class="rail-btn" type="button" data-dir="-1" aria-label="<?= v2_te('Previous attractions') ?>"><?= v2_ic('arrow-left') ?></button>
          <button class="rail-btn" type="button" data-dir="1" aria-label="<?= v2_te('Next attractions') ?>"><?= v2_ic('arrow-right') ?></button>
        </div>
      </div>
      <ul class="rail" id="gd-sights-list">
        <?php foreach ($gdSights as $gi => $gs): ?>
        <li class="xp"><a href="<?= v2_e($gs['href']) ?>">
          <span class="xp-media"><?= $gs['image'] ? v2_photo([v2_thumb($gs['image'], 480, 320), 480, 320, '']) : v2_fallback($gs['name'], $gi) ?></span>
          <span class="xp-body">
            <?php if ($gs['type'] !== ''): ?><span class="xp-cat"><?= v2_e($gs['type']) ?></span><?php endif; ?>
            <span class="xp-title"><?= v2_e($gs['name']) ?></span>
            <span class="xp-meta"><?php if ($gs['city'] !== ''): ?><span><?= v2_ic('map-pin') ?><?= v2_e($gs['city']) ?></span><?php endif; ?></span>
            <span class="xp-foot"><span class="xp-go"><?= v2_te('See the attraction') ?><?= v2_ic('arrow-right') ?></span></span>
          </span>
        </a></li>
        <?php endforeach; ?>
      </ul>
      <?php if ($gdPlace['city'] !== ''): ?><p class="gd-around-more"><a class="sec-link" href="/<?= v2_e($gdPlace['city']) ?>/attractions"><?= v2_te('All attractions in {city}', ['city' => $gdCityName]) ?><?= v2_ic('arrow-right') ?></a></p><?php endif; ?>
      <?php endif; ?>
    </div>
  </section>
  <?php endif; ?>

  <?php if ($related): ?>
  <!-- ===================== RELATED GUIDES ===================== -->
  <section class="sec gd-related" aria-labelledby="gd-related-h">
    <div class="wrap gd-related-in">
      <div class="gd-related-head">
        <p class="kicker"><?= v2_te('Read next') ?></p>
        <h2 id="gd-related-h"><?= v2_te('Related guides') ?></h2>
        <a class="sec-link" href="/guides"><?= v2_te('All guides') ?><?= v2_ic('arrow-right') ?></a>
      </div>
      <ol class="gd-rel-list">
        <?php foreach ($related as $rgi => $rg): ?>
        <li><a class="gd-rel" href="<?= v2_e($rg['href']) ?>">
          <span class="gd-rel-media"><?= $rg['photo'] ? v2_photo($rg['photo']) : v2_fallback($rg['title'], $rgi) ?></span>
          <span class="gd-rel-body"><small><?= v2_e($rg['kicker']) ?></small><b><?= v2_e($rg['title']) ?></b><?php if ($rg['excerpt'] !== ''): ?><span><?= v2_e($rg['excerpt']) ?></span><?php endif; ?></span>
          <?= v2_ic('arrow-right') ?>
        </a></li>
        <?php endforeach; ?>
      </ol>
    </div>
  </section>
  <?php endif; ?>
</main>
<?php include __DIR__ . '/includes/v2/footer.php'; ?>
