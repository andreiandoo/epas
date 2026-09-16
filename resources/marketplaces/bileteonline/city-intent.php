<?php
/**
 * City × Intent SEO landing page (v2 "Arcada").
 * Handles BOTH:
 *   /{city}/{intent}     — city-scoped (e.g. /brasov/activitati-indoor)
 *   /{intent}            — global (e.g. /activitati-azi)
 *
 * One PHP file → 100 cities × 25 intents × pagination = thousands of SEO pages.
 * SEO meta, events and cross links come from the marketplace intent API. That API only reads events, while most of
 * what bilete.online sells are activities, so the activities that meet the same rule are read from /activities
 * (date, price, audience and flag filters mirror the intent's filter_rule_json in MarketplaceCityIntentsSeeder) and
 * listed first, on the first page. An intent whose rule has no activity equivalent lists events only.
 *
 * Top to bottom: compact dark hero (breadcrumbs, intent, intro, facts, CTAs, the other ideas), results (the rule in
 * plain words, city filter and sort, cards, pagination) or the empty state with what is available meanwhile, cross
 * links (other intents here, the same intent in other cities), SEO copy.
 */

$pageCacheTTL = 300;
require_once __DIR__ . '/includes/page-cache.php';
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/api.php';

$citySlug = $_GET['city'] ?? null;
$intentSlug = $_GET['intent'] ?? null;
$pageNum = max(1, (int) ($_GET['page'] ?? 1));

if (!is_string($intentSlug) || !preg_match('/^[a-z0-9-]+$/', $intentSlug)) {
    http_response_code(404);
    require_once __DIR__ . '/404.php';
    exit;
}

if ($citySlug !== null && (!is_string($citySlug) || !preg_match('/^[a-z0-9-]+$/', $citySlug))) {
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
$eventTotal = max(0, (int) $pagination['total']);
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
$citySlugSafe = $city ? (string) ($city['slug'] ?? '') : '';
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

// Sprite icons for the ideas the menus use; any other intent keeps its API emoji.
const IT_ICONS = [
    'activitati-weekend' => 'sun', 'activitati-copii' => 'users-three', 'activitati-familie' => 'users-three',
    'activitati-zile-ploioase' => 'cloud-rain', 'activitati-indoor' => 'buildings', 'activitati-sub-50-lei' => 'coins',
    'activitati-sub-100-lei' => 'coins', 'activitati-gratuite' => 'gift', 'activitati-cuplu' => 'heart',
    'activitati-romantice' => 'heart', 'activitati-azi' => 'clock', 'activitati-maine' => 'calendar-blank',
    'activitati-grupuri' => 'users-three', 'activitati-outdoor' => 'map-pin',
];
$itIcon = function (string $slug, string $emoji = ''): string {
    if (isset(IT_ICONS[$slug])) {
        return v2_ic(IT_ICONS[$slug]);
    }
    return $emoji !== '' ? '<span class="it-emoji">' . v2_e($emoji) . '</span>' : v2_ic('sun');
};

// ------------------------------------------------------------------ activities that meet the intent's rule
$tz = new DateTimeZone('Europe/Bucharest');
$today = new DateTimeImmutable('today', $tz);
$dow = (int) $today->format('N');
$weekendDays = $dow === 7 ? [$today] : ($dow === 6 ? [$today, $today->modify('+1 day')] : [$today->modify('next saturday'), $today->modify('next sunday')]);
$roMonths = [1 => 'ianuarie', 'februarie', 'martie', 'aprilie', 'mai', 'iunie', 'iulie', 'august', 'septembrie', 'octombrie', 'noiembrie', 'decembrie'];
$dayLabel = fn (DateTimeImmutable $d): string => $d->format('j') . ' ' . $roMonths[(int) $d->format('n')];
$weekendLabel = count($weekendDays) === 2
    ? ($weekendDays[0]->format('n') === $weekendDays[1]->format('n') ? $weekendDays[0]->format('j') . '–' . $dayLabel($weekendDays[1]) : $dayLabel($weekendDays[0]) . ' – ' . $dayLabel($weekendDays[1]))
    : $dayLabel($weekendDays[0]);
$tomorrow = $today->modify('+1 day');

// Each rule: API params the activity list understands, then checks on the returned rows; `why` says it in words.
$itRules = [
    'activitati-azi' => ['dates' => [$today], 'why' => 'activități cu cel puțin un interval liber azi, ' . $dayLabel($today)],
    'activitati-maine' => ['dates' => [$tomorrow], 'why' => 'activități cu cel puțin un interval liber mâine, ' . $dayLabel($tomorrow)],
    'activitati-weekend' => ['dates' => $weekendDays, 'why' => 'activități cu locuri libere ' . (count($weekendDays) === 2 ? 'sâmbătă sau duminică' : 'duminică') . ' (' . $weekendLabel . ')'],
    'activitati-indoor' => ['flag' => 'is_indoor', 'why' => 'activități care se desfășoară în interior'],
    'activitati-zile-ploioase' => ['flag' => 'is_indoor', 'why' => 'activități în interior, la adăpost de ploaie'],
    'activitati-outdoor' => ['flag' => 'is_outdoor', 'why' => 'activități în aer liber'],
    'activitati-copii' => ['flag' => 'is_kid_friendly', 'why' => 'activități pe care organizatorul le-a marcat ca potrivite pentru copii'],
    'activitati-accesibile' => ['flag' => 'is_accessible', 'why' => 'activități marcate ca accesibile persoanelor cu dizabilități'],
    'activitati-gratuite' => ['min' => 0, 'max' => 0, 'why' => 'activități cu intrare gratuită'],
    'activitati-sub-50-lei' => ['min' => 1, 'max' => 50, 'why' => 'activități cu bilete între 1 și 50 lei'],
    'activitati-sub-100-lei' => ['max' => 100, 'why' => 'activități cu bilete de cel mult 100 lei'],
    'activitati-familie' => ['params' => ['traveler_types' => 'familii'], 'why' => 'activități recomandate de organizatori pentru familii'],
    'activitati-cuplu' => ['params' => ['traveler_types' => 'cupluri'], 'why' => 'activități recomandate de organizatori pentru cupluri'],
    'activitati-romantice' => ['params' => ['interests' => 'romantic'], 'why' => 'activități cu interesul „Romantic”'],
    'activitati-grupuri' => ['params' => ['traveler_types' => 'grupuri'], 'why' => 'activități recomandate de organizatori pentru grupuri'],
    'activitati-team-building' => ['params' => ['traveler_types' => 'team-building'], 'why' => 'activități recomandate de organizatori pentru team building'],
    'activitati-seniori' => ['params' => ['traveler_types' => 'seniori'], 'why' => 'activități recomandate de organizatori pentru seniori'],
    'activitati-adolescenti' => ['params' => ['traveler_types' => 'adolescenti'], 'why' => 'activități recomandate de organizatori pentru adolescenți'],
    'activitati-educationale' => ['params' => ['interests' => 'educational'], 'why' => 'activități cu interesul „Educațional”'],
];
$itRule = $itRules[$intentSlugSafe] ?? null;

$actCards = [];
if ($itRule && $pageNum === 1) {
    $base = ['per_page' => 50] + ($citySlugSafe !== '' ? ['city' => $citySlugSafe] : []) + ($itRule['params'] ?? []);
    if (!empty($itRule['max'])) {
        $base['max_price_ron'] = (int) $itRule['max'];
    }
    $jobs = [];
    foreach ($itRule['dates'] ?? [null] as $i => $day) {
        $params = $base + ($day ? ['date' => $day->format('Y-m-d')] : []);
        ksort($params);
        $jobs['d' . $i] = ['key' => 'v2_intent_acts_' . md5(json_encode($params)), 'endpoint' => '/activities', 'params' => $params, 'ttl' => 300];
    }
    $seen = [];
    foreach (api_cached_many($jobs) as $resp) {
        foreach ((array) ($resp['data']['items'] ?? []) as $a) {
            if (!is_array($a) || !($n = v2_activity($a)) || isset($seen[$n['slug']])) {
                continue;
            }
            $cents = isset($a['cheapest_price_cents']) ? (int) $a['cheapest_price_cents'] : null;
            if (isset($itRule['flag']) && empty($a['flags'][$itRule['flag']])) {
                continue;
            }
            if (isset($itRule['min']) && ($cents === null || $cents < $itRule['min'] * 100)) {
                continue;
            }
            if (isset($itRule['max']) && ($cents === null || $cents > $itRule['max'] * 100)) {
                continue;
            }
            $seen[$n['slug']] = true;
            $actCards[] = [
                'title' => $n['title'],
                'href' => $n['href'],
                'category' => $n['catName'],
                'city' => $n['city'],
                'citySlug' => (string) ($a['city']['slug'] ?? ''),
                'image' => $n['image'],
                'cents' => $cents,
                'dur' => $n['dur'],
                'minutes' => (int) ($a['duration_minutes'] ?? 0),
                'featured' => !empty($a['flags']['is_featured']),
                'cta' => 'Vezi activitatea',
            ];
        }
    }
    usort($actCards, fn ($a, $b) => [(int) $b['featured'], $a['title']] <=> [(int) $a['featured'], $b['title']]);
}
$actTotal = count($actCards);
$total = $actTotal + $eventTotal;

$currentPage = 'intent';
// SEO setup for head.php
$pageTitleRaw = $clean($meta['title'] ?? ('Activități · ' . SITE_NAME));
$pageDescription = $clean($meta['description'] ?? SITE_TAGLINE);
$canonicalUrl = SITE_URL . $basePath . ($pageNum > 1 ? '?page=' . $pageNum : '');
$ogImage = $cover;
// the API decides from its events alone (fewer than 3 → noindex); the activities listed here count too
$noindex = !empty($meta['noindex']) && $total < 3;
$v2Styles = ['intent.css'];
$v2Scripts = ['intent.js'];
$v2HeaderOverlay = true;

$pageUrl = static fn (int $p): string => $basePath . ($p > 1 ? '?page=' . $p : '');
$searchUrl = '/cauta?intent=' . urlencode($intentSlugSafe) . ($city ? '&city=' . urlencode($citySlugSafe) : '');

// Breadcrumbs
$breadcrumbs = [['name' => 'Acasă', 'url' => SITE_URL . '/']];
if ($city) {
    $breadcrumbs[] = ['name' => $cityName, 'url' => SITE_URL . '/' . $citySlugSafe];
}
$breadcrumbs[] = ['name' => $intentName, 'url' => SITE_URL . $basePath];

// Cards: the activities first (page 1), then this page of events
$cards = $actCards;
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
        'citySlug' => is_array($ev['marketplace_city'] ?? null) ? (string) ($ev['marketplace_city']['slug'] ?? '') : '',
        'image' => v2_media_url($ev['cover_image_url'] ?? $ev['image_url'] ?? null),
        'cents' => $cents === null ? null : (int) $cents,
        'dur' => '',
        'minutes' => 0,
        'featured' => false,
        'cta' => 'Vezi bilete',
    ];
}

// Facts for the hero and the city filter (global pages whose results span several cities)
$resultCities = [];
foreach ($cards as $c) {
    if ($c['city'] !== '') {
        $key = $c['citySlug'] !== '' ? $c['citySlug'] : $c['city'];
        $resultCities[$key] = ['name' => $c['city'], 'n' => ($resultCities[$key]['n'] ?? 0) + 1];
    }
}
uasort($resultCities, fn ($a, $b) => [$b['n'], $a['name']] <=> [$a['n'], $b['name']]);
$fromCents = null;
foreach ($cards as $c) {
    if ($c['cents'] !== null) {
        $fromCents = $fromCents === null ? $c['cents'] : min($fromCents, $c['cents']);
    }
}
$filterable = count($cards) > 1 && $lastPage === 1;

// Meanwhile: when nothing meets the rule, what is available now (in this city when it has any, else anywhere)
$altCards = [];
$altWhere = '';
if (!$cards) {
    $all = api_cached_many(['p1' => ['key' => 'v2_all_activities_p1', 'endpoint' => '/activities', 'params' => ['per_page' => 50, 'page' => 1], 'ttl' => 300]]);
    $pool = [];
    foreach ((array) ($all['p1']['data']['items'] ?? []) as $a) {
        if (is_array($a) && ($n = v2_activity($a))) {
            $n['cents'] = isset($a['cheapest_price_cents']) ? (int) $a['cheapest_price_cents'] : null;
            $n['citySlug'] = (string) ($a['city']['slug'] ?? '');
            $n['featured'] = !empty($a['flags']['is_featured']);
            $pool[] = $n;
        }
    }
    usort($pool, fn ($a, $b) => [(int) $b['featured'], $a['title']] <=> [(int) $a['featured'], $b['title']]);
    $inCity = $city ? array_values(array_filter($pool, fn ($a) => $a['citySlug'] === $citySlugSafe)) : [];
    $altCards = array_slice($inCity ?: $pool, 0, 4);
    $altWhere = $inCity ? 'în ' . $cityName : 'pe bilete.online';
}

// The ideas next to the heading: the menu's situations first, then the API's other intents, never this one
$ideaSlugs = ['activitati-weekend' => 'Weekend', 'activitati-copii' => 'Copii', 'activitati-zile-ploioase' => 'Zile ploioase', 'activitati-sub-50-lei' => 'Sub 50 lei', 'activitati-cuplu' => 'Cuplu'];
$ideas = [];
foreach ($ideaSlugs as $slug => $name) {
    if ($slug !== $intentSlugSafe) {
        $ideas[$slug] = ['slug' => $slug, 'name' => $name, 'icon' => '', 'path' => ($city ? '/' . $citySlugSafe : '') . '/' . $slug];
    }
}
foreach ($otherIntents as $li) {
    $slug = (string) ($li['slug'] ?? '');
    if ($slug !== '' && $slug !== $intentSlugSafe && !isset($ideas[$slug])) {
        $ideas[$slug] = ['slug' => $slug, 'name' => (string) $li['name'], 'icon' => (string) ($li['icon'] ?? ''), 'path' => (string) $li['path']];
    }
}
$ideas = array_slice(array_values($ideas), 0, 8);

$priceHtml = function (?int $cents): string {
    if ($cents === null) {
        return '<span class="xp-price"><b>—</b></span>';
    }
    if ($cents === 0) {
        return '<span class="xp-price"><b>Gratuit</b></span>';
    }
    return '<span class="xp-price">de la<b>' . v2_thousands((int) round($cents / 100)) . ' lei</b></span>';
};
$renderCard = function (array $c, int $i, bool $data) use ($priceHtml): void {
    ?>
        <li class="xp"<?php if ($data): ?> data-city="<?= v2_e($c['citySlug'] !== '' ? $c['citySlug'] : $c['city']) ?>" data-order="<?= $i ?>" data-price="<?= $c['cents'] === null ? '' : (int) $c['cents'] ?>" data-dur="<?= (int) $c['minutes'] ?>"<?php endif; ?>>
          <a href="<?= v2_e($c['href']) ?>">
            <span class="xp-media"><?= $c['image'] ? v2_photo([$c['image'], 0, 0, '']) : v2_fallback($c['title'], $i) ?></span>
            <span class="xp-body">
              <span class="xp-cat"><?= v2_e($c['category']) ?></span>
              <span class="xp-title"><?= v2_e($c['title']) ?></span>
              <span class="xp-meta">
                <?php if ($c['city'] !== ''): ?><span><?= v2_ic('map-pin') ?><?= v2_e($c['city']) ?></span><?php endif; ?>
                <?php if ($c['dur'] !== ''): ?><span><?= v2_ic('clock') ?><?= v2_e($c['dur']) ?></span><?php endif; ?>
              </span>
              <span class="xp-foot"><span class="xp-go"><?= v2_e($c['cta']) ?><?= v2_ic('arrow-right') ?></span><?= $priceHtml($c['cents']) ?></span>
            </span>
          </a>
        </li>
    <?php
};

// Structured data: CollectionPage + ItemList of the first 10 results, BreadcrumbList
$structuredData = [];
if ($cards) {
    $itemListElements = [];
    foreach (array_slice($cards, 0, 10) as $i => $c) {
        $itemListElements[] = [
            '@type' => 'ListItem',
            'position' => $i + 1,
            'name' => $c['title'],
            'url' => SITE_URL . $c['href'],
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

$itArches = '<svg class="deco-arches" viewBox="0 0 400 400" aria-hidden="true" focusable="false"><path d="M40 400V200a160 160 0 0 1 320 0v200"/><path d="M90 400V200a110 110 0 0 1 220 0v200"/><path d="M140 400V200a60 60 0 0 1 120 0v200"/></svg>';

include __DIR__ . '/includes/v2/head.php';
include __DIR__ . '/includes/v2/header.php';
?>
<main id="main" class="page-main" tabindex="-1">

  <!-- ============================== HERO ============================== -->
  <section class="it-hero <?= $accentClass ?><?= $cover ? ' has-cover' : '' ?>" aria-labelledby="it-h">
    <?php if ($cover): ?><img class="it-cover" src="<?= v2_e($cover) ?>" alt="" decoding="async" fetchpriority="high"><?php endif; ?>
    <?= $itArches ?>
    <svg class="it-line draw-clip" viewBox="0 590 3240 310" aria-hidden="true" focusable="false"><use href="#drum-g"/></svg>
    <div class="it-in">
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
        <p class="it-kicker"><span class="it-icon" aria-hidden="true"><?= $itIcon($intentSlugSafe, $intentIcon) ?></span><?= $city ? 'Local · ' . v2_e($cityName) : 'Idei · toată țara' ?></p>
        <h1 class="it-h" id="it-h"><?= v2_e($h1) ?></h1>
        <?php if ($intro !== ''): ?><p class="it-lead"><?= v2_e($intro) ?></p><?php endif; ?>
        <?php if ($total > 0): ?>
        <ul class="it-facts" aria-label="Pe scurt">
          <li><?= v2_e(v2_num($total, 'rezultat', 'rezultate')) ?></li>
          <?php if (!$city && count($resultCities) > 1): ?><li>în <?= v2_e(v2_num(count($resultCities), 'oraș', 'orașe')) ?></li><?php endif; ?>
          <?php if ($fromCents !== null): ?><li><?= $fromCents === 0 ? 'și opțiuni gratuite' : 'de la ' . v2_e(v2_thousands((int) round($fromCents / 100))) . ' lei' ?></li><?php endif; ?>
        </ul>
        <?php endif; ?>
        <div class="it-cta">
          <?php if ($total > 0): ?>
          <a class="btn btn-light" href="#activitati">Vezi <?= v2_e(v2_num($total, 'rezultat', 'rezultate')) ?><?= v2_ic('arrow-right') ?></a>
          <?php endif; ?>
          <?php /* with nothing listed yet, the way onward (the city, or all cities) becomes the main button */ ?>
          <?php $itMoreClass = $total > 0 ? 'it-link' : 'btn btn-light'; ?>
          <?php if ($city): ?>
          <a class="<?= $itMoreClass ?>" href="/<?= v2_e($citySlugSafe) ?>">Toate activitățile din <?= v2_e($cityName) ?><?= v2_ic('arrow-right') ?></a>
          <?php else: ?>
          <a class="<?= $itMoreClass ?>" href="/orase">Caută după oraș<?= v2_ic('arrow-right') ?></a>
          <?php endif; ?>
        </div>
      </div>

      <?php if ($ideas): ?>
      <nav class="it-switch" aria-labelledby="it-switch-h">
        <p class="it-switch-h" id="it-switch-h">Alte idei<?= $city ? ' în ' . v2_e($cityName) : '' ?></p>
        <ul>
          <?php foreach ($ideas as $idea): ?>
          <li><a href="<?= v2_e($idea['path']) ?>"><span class="it-switch-ic" aria-hidden="true"><?= $itIcon($idea['slug'], $idea['icon']) ?></span><?= v2_e($idea['name']) ?></a></li>
          <?php endforeach; ?>
        </ul>
      </nav>
      <?php endif; ?>
    </div>
  </section>
  <div id="hdr-sentinel" aria-hidden="true"></div>

  <!-- ============================== ACTIVITĂȚI ============================== -->
  <section class="it-list" id="activitati" aria-labelledby="it-list-h">
    <div class="wrap">
      <?php if (!$cards): ?>
      <div class="it-empty">
        <span class="it-empty-ic" aria-hidden="true"><?= $itIcon($intentSlugSafe, $intentIcon) ?></span>
        <div class="it-empty-copy">
          <h2 id="it-list-h">Nimic disponibil acum<?= $city ? ' în ' . v2_e($cityName) : '' ?>.</h2>
          <p><?= $itRule ? 'Aici apar ' . v2_e($itRule['why']) . '. ' : '' ?>Pagina rămâne activă — verifică din nou peste câteva zile sau încearcă o altă intenție.</p>
          <?php if ($otherIntents): ?>
          <div class="chips-links it-chips">
            <?php foreach (array_slice($otherIntents, 0, 6) as $li): ?>
            <a href="<?= v2_e($li['path']) ?>"><?php if (!empty($li['icon'])): ?><span aria-hidden="true"><?= v2_e($li['icon']) ?></span><?php endif; ?><?= v2_e($li['name']) ?></a>
            <?php endforeach; ?>
          </div>
          <?php endif; ?>
        </div>
        <?php if ($city): ?>
        <a class="btn btn-primary" href="/<?= v2_e($citySlugSafe) ?>">Toate activitățile din <?= v2_e($cityName) ?><?= v2_ic('arrow-right') ?></a>
        <?php else: ?>
        <a class="btn btn-primary" href="/cauta"><?= v2_ic('magnifying-glass') ?>Caută activități</a>
        <?php endif; ?>
      </div>
      <?php if ($altCards): ?>
      <div class="it-alt">
        <div class="sec-head it-head">
          <div><p class="kicker">Între timp</p><h2>Disponibile acum <?= v2_e($altWhere) ?></h2></div>
          <a class="sec-link" href="<?= $city && $altWhere !== 'pe bilete.online' ? '/' . v2_e($citySlugSafe) : '/cauta' ?>">Vezi toate<?= v2_ic('arrow-right') ?></a>
        </div>
        <ul class="xp-grid">
          <?php foreach ($altCards as $i => $a): ?>
          <?php $renderCard(['title' => $a['title'], 'href' => $a['href'], 'category' => $a['catName'], 'city' => $a['city'], 'citySlug' => $a['citySlug'], 'image' => $a['image'], 'cents' => $a['cents'], 'dur' => $a['dur'], 'minutes' => 0, 'cta' => 'Vezi activitatea'], $i, false); ?>
          <?php endforeach; ?>
        </ul>
      </div>
      <?php endif; ?>
      <?php else: ?>
      <div class="it-head">
        <div>
          <p class="kicker">Rezultate</p>
          <h2 id="it-list-h"><?= v2_e($intentName) ?><?= $city ? ' în ' . v2_e($cityName) : '' ?></h2>
        </div>
        <div class="it-tools">
          <p class="it-count" id="it-count" aria-live="polite"><?= v2_e(v2_num($total, 'rezultat', 'rezultate')) ?><?php if ($lastPage > 1): ?> · pagina <?= $currentPageNum ?> din <?= $lastPage ?><?php endif; ?></p>
          <?php if ($filterable): ?>
          <label class="it-sort"><span>Sortare</span>
            <select class="select" id="it-sort">
              <option value="recommended">Recomandate</option>
              <option value="priceAsc">Preț crescător</option>
              <option value="duration">Durată scurtă</option>
            </select>
          </label>
          <?php endif; ?>
          <a class="sec-link" href="<?= v2_e($searchUrl) ?>">Filtre avansate<?= v2_ic('arrow-right') ?></a>
        </div>
      </div>
      <?php if ($itRule && $actTotal > 0): ?>
      <p class="it-why"><?= v2_ic('check-circle') ?><span><b>Cum alegem:</b> <?= v2_e($itRule['why']) ?><?= $city ? ', în ' . v2_e($cityName) : '' ?>.</span></p>
      <?php endif; ?>
      <?php if ($filterable && !$city && count($resultCities) > 1): ?>
      <div class="it-cities" role="group" aria-label="Filtrează după oraș">
        <button type="button" data-city="all" aria-pressed="true">Toate<span><?= count($cards) ?></span></button>
        <?php foreach ($resultCities as $key => $rc): ?>
        <button type="button" data-city="<?= v2_e($key) ?>" aria-pressed="false"><?= v2_e($rc['name']) ?><span><?= $rc['n'] ?></span></button>
        <?php endforeach; ?>
      </div>
      <?php endif; ?>

      <ul class="xp-grid" id="it-grid">
        <?php foreach ($cards as $i => $c) { $renderCard($c, $i, true); } ?>
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
      <div class="it-cross-card">
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
      <div class="it-cross-card">
        <p class="kicker">În alte orașe</p>
        <h2><?= v2_e($intentName) ?> în alte orașe</h2>
        <ul class="it-towns">
          <?php foreach ($sameIntent as $li): ?>
          <li><a href="<?= v2_e($li['path']) ?>"><?= v2_e($li['name']) ?><?= v2_ic('arrow-right') ?></a></li>
          <?php endforeach; ?>
        </ul>
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
