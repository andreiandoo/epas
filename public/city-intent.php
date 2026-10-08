<?php
/**
 * City × Intent SEO landing page (v2 "Arcada").
 * Handles BOTH:
 *   /{city}/{intent}     — city-scoped (e.g. /lisbon/weekend-ideas, /lisbon/activitati-indoor)
 *   /{intent}            — global (e.g. /weekend-ideas, /activitati-azi)
 *
 * Intent slugs (activitati-…) are identifiers shared with the database and the API and are never renamed. Four of
 * them have an English address in .htaccess; intent_path() gives the segment to print in links.
 *
 * One PHP file → 100 cities × 25 intents × pagination = thousands of SEO pages.
 * SEO meta, events and cross links come from the marketplace intent API. That API only reads events, while most of
 * what viaqui.com sells are activities, so the activities that meet the same rule are read from /activities
 * (date, price, audience and flag filters mirror the intent's filter_rule_json in MarketplaceCityIntentsSeeder) and
 * listed first, on the first page. An intent whose rule has no activity equivalent lists events only.
 *
 * Top to bottom: compact dark hero (breadcrumbs, intent, intro, facts, CTAs, the other ideas), results (the rule in
 * plain words, city filter and sort, cards, pagination) or the empty state with what is available meanwhile, cross
 * links (other intents here, the same intent in other cities). The intent's seo_copy is not shown: one templated
 * paragraph repeated in every city added nothing for readers.
 */

$pageCacheTTL = 300;
require_once __DIR__ . '/includes/page-cache.php';
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/api.php';

/** The address segment visitors see for an intent: the English alias from .htaccess, or the slug when it has none. */
function intent_path(string $slug): string
{
    return [
        'activitati-weekend' => 'weekend-ideas',
        'activitati-copii' => 'with-kids',
        'activitati-zile-ploioase' => 'rainy-days',
        'activitati-cuplu' => 'for-couples',
    ][$slug] ?? $slug;
}

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
require_once __DIR__ . '/includes/v2/promoted.php';
require_once __DIR__ . '/includes/v2/partners.php';

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
// Paths that come from the API end in the intent slug: print the English address where the intent has one
$intentHref = static fn ($path): string => (string) preg_replace_callback('~(?<=/)[a-z0-9-]+(?=/?(?:[?#]|$))~', fn ($m) => intent_path($m[0]), (string) $path, 1);
$withHref = static function (array $l) use ($intentHref): array {
    $l['path'] = $intentHref($l['path']);
    return $l;
};
$otherIntents = array_map($withHref, array_values(array_filter((array) $crossLinks['other_intents_for_city'], fn ($l) => is_array($l) && !empty($l['path']) && !empty($l['name']))));
$sameIntent = array_map($withHref, array_values(array_filter((array) $crossLinks['same_intent_for_cities'], fn ($l) => is_array($l) && !empty($l['path']) && !empty($l['name']))));

// Global intents come with the city left out of the text templates ("… in  · Viaqui", "for : …"):
// drop the dangling preposition and tidy the spacing it leaves. Texts with a city are not affected.
// English prepositions go only when a gap follows them, so "what you are looking for." stays whole.
$clean = static function ($text): string {
    $text = (string) $text;
    $text = preg_replace('/[ \t]+(?:în|din|pentru|la)[ \t]*(?=[·:;.,!?]|$)/um', '', $text);
    $text = preg_replace('/[ \t]+(?:in|from|for|at|near)(?:[ \t]+(?=[·:;.,!?])|[ \t]+$)/um', '', $text);
    $text = preg_replace('/[ \t]*·[ \t]*/u', ' · ', $text);
    $text = preg_replace('/[ \t]{2,}/u', ' ', $text);
    return trim($text);
};

$intentName = (string) ($intent['name'] ?? v2_t('Things to do'));
$intentSlugSafe = (string) ($intent['slug'] ?? $intentSlug);
$cityName = $city ? (string) ($city['name'] ?? '') : '';
$citySlugSafe = $city ? (string) ($city['slug'] ?? '') : '';
// The heading comes from meta h1 (or the title when there is none); either can end in " · Viaqui",
// which belongs in <title> only
$h1 = $clean(preg_replace('/\s*·\s*' . preg_quote(SITE_NAME, '/') . '\s*$/u', '', (string) ($meta['h1'] ?? $meta['title'] ?? $intentName)));
$intro = $clean($meta['intro_copy'] ?? '');
$intentIcon = (string) ($meta['icon'] ?? '');
$cover = v2_media_url($meta['cover_image_url'] ?? null);
$basePath = $intentHref($meta['canonical_path'] ?? '/');
$accentClass = ['vermilion' => 'is-red', 'forest' => 'is-green', 'ochre' => 'is-yellow', 'sky' => 'is-blue'][$meta['accent_color'] ?? 'vermilion'] ?? 'is-red';

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
// "12 October": the month through the language layer, not PHP's English names
$itMonths = [
    1 => v2_t('January'), 2 => v2_t('February'), 3 => v2_t('March'), 4 => v2_t('April'), 5 => v2_t('May'), 6 => v2_t('June'),
    7 => v2_t('July'), 8 => v2_t('August'), 9 => v2_t('September'), 10 => v2_t('October'), 11 => v2_t('November'), 12 => v2_t('December'),
];
$dayLabel = fn (DateTimeImmutable $d): string => v2_t('{day} {month}', ['day' => $d->format('j'), 'month' => $itMonths[(int) $d->format('n')]]);
$weekendLabel = count($weekendDays) === 2
    ? ($weekendDays[0]->format('n') === $weekendDays[1]->format('n')
        ? v2_t('{first}–{last}', ['first' => $weekendDays[0]->format('j'), 'last' => $dayLabel($weekendDays[1])])
        : v2_t('{first} – {last}', ['first' => $dayLabel($weekendDays[0]), 'last' => $dayLabel($weekendDays[1])]))
    : $dayLabel($weekendDays[0]);
$tomorrow = $today->modify('+1 day');

// Each rule: API params the activity list understands, then checks on the returned rows; `why` says it in words.
$itRules = [
    'activitati-azi' => ['dates' => [$today], 'why' => v2_t('activities with at least one open slot today, {date}', ['date' => $dayLabel($today)])],
    'activitati-maine' => ['dates' => [$tomorrow], 'why' => v2_t('activities with at least one open slot tomorrow, {date}', ['date' => $dayLabel($tomorrow)])],
    'activitati-weekend' => ['dates' => $weekendDays, 'why' => count($weekendDays) === 2
        ? v2_t('activities with places left on Saturday or Sunday ({dates})', ['dates' => $weekendLabel])
        : v2_t('activities with places left on Sunday ({dates})', ['dates' => $weekendLabel])],
    'activitati-indoor' => ['flag' => 'is_indoor', 'why' => v2_t('activities that take place indoors')],
    'activitati-zile-ploioase' => ['flag' => 'is_indoor', 'why' => v2_t('indoor activities, out of the rain')],
    'activitati-outdoor' => ['flag' => 'is_outdoor', 'why' => v2_t('outdoor activities')],
    'activitati-copii' => ['flag' => 'is_kid_friendly', 'why' => v2_t('activities the organiser has marked as suitable for children')],
    'activitati-accesibile' => ['flag' => 'is_accessible', 'why' => v2_t('activities marked as accessible to people with disabilities')],
    'activitati-gratuite' => ['min' => 0, 'max' => 0, 'why' => v2_t('activities with free entry')],
    'activitati-sub-50-lei' => ['min' => 1, 'max' => 50, 'why' => v2_t('activities with tickets from {min} to {max}', ['min' => v2_money(1), 'max' => v2_money(50)])],
    'activitati-sub-100-lei' => ['max' => 100, 'why' => v2_t('activities with tickets of {max} or less', ['max' => v2_money(100)])],
    'activitati-familie' => ['params' => ['traveler_types' => 'familii'], 'why' => v2_t('activities organisers recommend for families')],
    'activitati-cuplu' => ['params' => ['traveler_types' => 'cupluri'], 'why' => v2_t('activities organisers recommend for couples')],
    'activitati-romantice' => ['params' => ['interests' => 'romantic'], 'why' => v2_t('activities tagged "Romantic"')],
    'activitati-grupuri' => ['params' => ['traveler_types' => 'grupuri'], 'why' => v2_t('activities organisers recommend for groups')],
    'activitati-team-building' => ['params' => ['traveler_types' => 'team-building'], 'why' => v2_t('activities organisers recommend for team building')],
    'activitati-seniori' => ['params' => ['traveler_types' => 'seniori'], 'why' => v2_t('activities organisers recommend for seniors')],
    'activitati-adolescenti' => ['params' => ['traveler_types' => 'adolescenti'], 'why' => v2_t('activities organisers recommend for teenagers')],
    'activitati-educationale' => ['params' => ['interests' => 'educational'], 'why' => v2_t('activities tagged "Educational"')],
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
                'promoted' => $n['promoted'],
                'cta' => v2_t('View activity'),
            ];
        }
    }
    usort($actCards, fn ($a, $b) => [(int) $b['promoted'], (int) $b['featured'], $a['title']] <=> [(int) $a['promoted'], (int) $a['featured'], $b['title']]);
}
$actTotal = count($actCards);
// Partner products that fit the idea (WeGoTrip), after our own, on the first page
$partnerItems = $pageNum === 1 ? v2_wegotrip_for_intent($intentSlugSafe, $city ? $citySlugSafe : '', 24) : [];
$total = $actTotal + $eventTotal + count($partnerItems);

$currentPage = 'intent';
// SEO setup for head.php
$pageTitleRaw = $clean($meta['title'] ?? v2_t('Things to do · {site}', ['site' => SITE_NAME]));
$pageDescription = $clean($meta['description'] ?? SITE_TAGLINE);
$canonicalUrl = SITE_URL . $basePath . ($pageNum > 1 ? '?page=' . $pageNum : '');
$ogImage = $cover;
// the API decides from its events alone (fewer than 3 → noindex); the activities listed here count too
$noindex = !empty($meta['noindex']) && $total < 3;
$v2Styles = ['intent.css'];
$v2Scripts = ['intent.js'];
$v2HeaderOverlay = true;

$pageUrl = static fn (int $p): string => $basePath . ($p > 1 ? '?page=' . $p : '');
$searchUrl = '/search?intent=' . urlencode($intentSlugSafe) . ($city ? '&city=' . urlencode($citySlugSafe) : '');

// Breadcrumbs
$breadcrumbs = [['name' => v2_t('Home'), 'url' => SITE_URL . '/']];
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
        $title = v2_t('Activity');
    }
    $slug = (string) ($ev['slug'] ?? '');
    $category = is_array($ev['marketplace_event_category'] ?? null) ? navFlatName($ev['marketplace_event_category']['name'] ?? '') : '';
    $cityLabel = is_array($ev['marketplace_city'] ?? null)
        ? navFlatName($ev['marketplace_city']['name'] ?? '')
        : (string) (is_array($ev['venue'] ?? null) ? ($ev['venue']['city'] ?? '') : '');
    $cents = $ev['cheapest_price_cents'] ?? null;
    $cards[] = [
        'title' => $title,
        'href' => $slug !== '' ? '/bilete/' . $slug : '/search?q=' . rawurlencode($title),
        'category' => $category,
        'city' => $cityLabel,
        'citySlug' => is_array($ev['marketplace_city'] ?? null) ? (string) ($ev['marketplace_city']['slug'] ?? '') : '',
        'image' => v2_media_url($ev['cover_image_url'] ?? $ev['image_url'] ?? null),
        'cents' => $cents === null ? null : (int) $cents,
        'dur' => '',
        'minutes' => 0,
        'featured' => false,
        'promoted' => false,
        'cta' => v2_t('View tickets'),
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
$altInCity = false;      // whether the cards below are from this city (else from the whole site)
$altHeading = '';
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
    usort($pool, fn ($a, $b) => [(int) $b['promoted'], (int) $b['featured'], $a['title']] <=> [(int) $a['promoted'], (int) $a['featured'], $b['title']]);
    $inCity = $city ? array_values(array_filter($pool, fn ($a) => $a['citySlug'] === $citySlugSafe)) : [];
    $altCards = array_slice($inCity ?: $pool, 0, 4);
    $altInCity = (bool) $inCity;
    $altHeading = $altInCity ? v2_t('Available now in {city}', ['city' => $cityName]) : v2_t('Available now on {site}', ['site' => SITE_NAME]);
}

// The ideas next to the heading: the menu's situations first, then the API's other intents, never this one
$ideaSlugs = ['activitati-weekend' => v2_t('Weekend'), 'activitati-copii' => v2_t('With kids'), 'activitati-zile-ploioase' => v2_t('Rainy days'), 'activitati-cuplu' => v2_t('For couples')];
$ideas = [];
foreach ($ideaSlugs as $slug => $name) {
    if ($slug !== $intentSlugSafe) {
        $ideas[$slug] = ['slug' => $slug, 'name' => $name, 'icon' => '', 'path' => ($city ? '/' . $citySlugSafe : '') . '/' . intent_path($slug)];
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
        return '<span class="xp-price"><b>' . v2_te('Free') . '</b></span>';
    }
    return '<span class="xp-price">' . v2_te('from') . '<b>' . v2_e(v2_money($cents / 100)) . '</b></span>';
};
$renderCard = function (array $c, int $i, bool $data) use ($priceHtml): void {
    ?>
        <li class="xp"<?php if ($data): ?> data-city="<?= v2_e($c['citySlug'] !== '' ? $c['citySlug'] : $c['city']) ?>" data-order="<?= $i ?>" data-price="<?= $c['cents'] === null ? '' : (int) $c['cents'] ?>" data-dur="<?= (int) $c['minutes'] ?>"<?php endif; ?>>
          <a href="<?= v2_e($c['href']) ?>">
            <span class="xp-media"><?= $c['image'] ? v2_photo([$c['image'], 0, 0, '']) : v2_fallback($c['title'], $i) ?><?= !empty($c['promoted']) ? v2_promoted_tag() : '' ?></span>
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
        'inLanguage' => v2_locale(),
        'isPartOf' => ['@type' => 'WebSite', 'name' => SITE_NAME, 'url' => SITE_URL],
        'mainEntity' => [
            '@type' => 'ItemList',
            'numberOfItems' => $total,
            'itemListElement' => $itemListElements,
        ],
    ];
}
// a trail only where there is a level between home and the page (/{city}/{intent}); global intents have none
if ($city) {
    $structuredData[] = [
        '@context' => 'https://schema.org',
        '@type' => 'BreadcrumbList',
        'itemListElement' => array_map(fn ($bc, $i) => ['@type' => 'ListItem', 'position' => $i + 1, 'name' => $bc['name'], 'item' => $bc['url']], $breadcrumbs, array_keys($breadcrumbs)),
    ];
}

$itArches = '<svg class="deco-arches" viewBox="0 0 400 400" aria-hidden="true" focusable="false"><path d="M40 400V200a160 160 0 0 1 320 0v200"/><path d="M90 400V200a110 110 0 0 1 220 0v200"/><path d="M140 400V200a60 60 0 0 1 120 0v200"/></svg>';

include __DIR__ . '/includes/v2/head.php';
include __DIR__ . '/includes/v2/header.php';
?>
<main id="main" tabindex="-1">

  <!-- ============================== HERO ============================== -->
  <section class="it-hero <?= $accentClass ?><?= $cover ? ' has-cover' : '' ?>" aria-labelledby="it-h">
    <?php if ($cover): ?><img class="it-cover" src="<?= v2_e($cover) ?>" alt="" decoding="async" fetchpriority="high"><?php endif; ?>
    <?= $itArches ?>
    <svg class="it-line draw-clip" viewBox="0 590 3240 310" aria-hidden="true" focusable="false"><use href="#drum-g"/></svg>
    <div class="it-in">
      <div class="it-text">
        <?php if ($city): ?>
        <nav class="crumbs" aria-label="<?= v2_te('Breadcrumb') ?>">
          <?php foreach ($breadcrumbs as $i => $bc): ?>
            <?php if ($i > 0): ?><span aria-hidden="true">/</span><?php endif; ?>
            <?php if ($i < count($breadcrumbs) - 1): ?>
              <a href="<?= v2_e(substr($bc['url'], strlen(SITE_URL)) ?: '/') ?>"><?= v2_e($bc['name']) ?></a>
            <?php else: ?>
              <span aria-current="page"><?= v2_e($bc['name']) ?></span>
            <?php endif; ?>
          <?php endforeach; ?>
        </nav>
        <?php endif; ?>
        <p class="it-kicker"><span class="it-icon" aria-hidden="true"><?= $itIcon($intentSlugSafe, $intentIcon) ?></span><?= $city ? v2_te('Local · {city}', ['city' => $cityName]) : v2_te('Ideas · all of Europe') ?></p>
        <h1 class="it-h" id="it-h"><?= v2_e($h1) ?></h1>
        <?php if ($intro !== ''): ?><p class="it-lead"><?= v2_e($intro) ?></p><?php endif; ?>
        <?php if ($total > 0): ?>
        <ul class="it-facts" aria-label="<?= v2_te('At a glance') ?>">
          <li><?= v2_e(v2_num($total, 'result', 'results')) ?></li>
          <?php if (!$city && count($resultCities) > 1): ?><li><?= v2_te('in {cities}', ['cities' => v2_num(count($resultCities), 'city', 'cities')]) ?></li><?php endif; ?>
          <?php if ($fromCents !== null): ?><li><?= $fromCents === 0 ? v2_te('including free options') : v2_te('from {price}', ['price' => v2_money($fromCents / 100)]) ?></li><?php endif; ?>
        </ul>
        <?php endif; ?>
        <div class="it-cta">
          <?php if ($total > 0): ?>
          <a class="btn btn-light" href="#activitati"><?= v2_te('See {results}', ['results' => v2_num($total, 'result', 'results')]) ?><?= v2_ic('arrow-right') ?></a>
          <?php endif; ?>
          <?php /* with nothing listed yet, the way onward (the city, or all cities) becomes the main button */ ?>
          <?php $itMoreClass = $total > 0 ? 'it-link' : 'btn btn-light'; ?>
          <?php if ($city): ?>
          <a class="<?= $itMoreClass ?>" href="/<?= v2_e($citySlugSafe) ?>"><?= v2_te('All activities in {city}', ['city' => $cityName]) ?><?= v2_ic('arrow-right') ?></a>
          <?php else: ?>
          <a class="<?= $itMoreClass ?>" href="/cities"><?= v2_te('Browse by city') ?><?= v2_ic('arrow-right') ?></a>
          <?php endif; ?>
        </div>
      </div>

      <?php if ($ideas): ?>
      <nav class="it-switch" aria-labelledby="it-switch-h">
        <p class="it-switch-h" id="it-switch-h"><?= $city ? v2_te('More ideas in {city}', ['city' => $cityName]) : v2_te('More ideas') ?></p>
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

  <!-- ============================== ACTIVITIES ============================== -->
  <section class="it-list" id="activitati" aria-labelledby="it-list-h">
    <div class="wrap">
      <?php if (!$cards && !$partnerItems): ?>
      <div class="it-empty">
        <span class="it-empty-ic" aria-hidden="true"><?= $itIcon($intentSlugSafe, $intentIcon) ?></span>
        <div class="it-empty-copy">
          <h2 id="it-list-h"><?= $city ? v2_te('Nothing available right now in {city}.', ['city' => $cityName]) : v2_te('Nothing available right now.') ?></h2>
          <p><?= $itRule ? v2_te('This page lists {what}. It stays live: check back in a few days or try another idea.', ['what' => $itRule['why']]) : v2_te('It stays live: check back in a few days or try another idea.') ?></p>
          <?php if ($otherIntents): ?>
          <div class="chips-links it-chips">
            <?php foreach (array_slice($otherIntents, 0, 6) as $li): ?>
            <a href="<?= v2_e($li['path']) ?>"><?php if (!empty($li['icon'])): ?><span aria-hidden="true"><?= v2_e($li['icon']) ?></span><?php endif; ?><?= v2_e($li['name']) ?></a>
            <?php endforeach; ?>
          </div>
          <?php endif; ?>
        </div>
        <?php if ($city): ?>
        <a class="btn btn-primary" href="/<?= v2_e($citySlugSafe) ?>"><?= v2_te('All activities in {city}', ['city' => $cityName]) ?><?= v2_ic('arrow-right') ?></a>
        <?php else: ?>
        <a class="btn btn-primary" href="/search"><?= v2_ic('magnifying-glass') ?><?= v2_te('Search activities') ?></a>
        <?php endif; ?>
      </div>
      <?php if ($altCards): ?>
      <div class="it-alt">
        <div class="sec-head it-head">
          <div><p class="kicker"><?= v2_te('In the meantime') ?></p><h2><?= v2_e($altHeading) ?></h2></div>
          <a class="sec-link" href="<?= $city && $altInCity ? '/' . v2_e($citySlugSafe) : '/search' ?>"><?= v2_te('See all') ?><?= v2_ic('arrow-right') ?></a>
        </div>
        <ul class="xp-grid">
          <?php foreach ($altCards as $i => $a): ?>
          <?php $renderCard(['title' => $a['title'], 'href' => $a['href'], 'category' => $a['catName'], 'city' => $a['city'], 'citySlug' => $a['citySlug'], 'image' => $a['image'], 'cents' => $a['cents'], 'dur' => $a['dur'], 'minutes' => 0, 'cta' => v2_t('View activity')], $i, false); ?>
          <?php endforeach; ?>
        </ul>
      </div>
      <?php endif; ?>
      <?php else: ?>
      <div class="it-head">
        <div>
          <p class="kicker"><?= v2_te('Results') ?></p>
          <h2 id="it-list-h"><?= $city ? v2_te('{what} in {city}', ['what' => $intentName, 'city' => $cityName]) : v2_e($intentName) ?></h2>
        </div>
        <div class="it-tools">
          <p class="it-count" id="it-count" aria-live="polite"><?= $lastPage > 1 ? v2_te('{results} · page {page} of {pages}', ['results' => v2_num($total, 'result', 'results'), 'page' => $currentPageNum, 'pages' => $lastPage]) : v2_e(v2_num($total, 'result', 'results')) ?></p>
          <?php if ($filterable): ?>
          <label class="it-sort"><span><?= v2_te('Sort by') ?></span>
            <select class="select" id="it-sort">
              <option value="recommended"><?= v2_te('Recommended') ?></option>
              <option value="priceAsc"><?= v2_te('Price: low to high') ?></option>
              <option value="duration"><?= v2_te('Shortest first') ?></option>
            </select>
          </label>
          <?php endif; ?>
          <a class="sec-link" href="<?= v2_e($searchUrl) ?>"><?= v2_te('More filters') ?><?= v2_ic('arrow-right') ?></a>
        </div>
      </div>
      <?php if ($itRule && $actTotal > 0): ?>
      <p class="it-why"><?= v2_ic('check-circle') ?><span><?= $city ? v2_t('<b>How we choose:</b> {what}, in {city}.', ['what' => v2_e($itRule['why']), 'city' => v2_e($cityName)]) : v2_t('<b>How we choose:</b> {what}.', ['what' => v2_e($itRule['why'])]) ?></span></p>
      <?php endif; ?>
      <?php if ($filterable && !$city && count($resultCities) > 1): ?>
      <div class="it-cities" role="group" aria-label="<?= v2_te('Filter by city') ?>">
        <button type="button" data-city="all" aria-pressed="true"><?= v2_te('All') ?><span><?= count($cards) ?></span></button>
        <?php foreach ($resultCities as $key => $rc): ?>
        <button type="button" data-city="<?= v2_e($key) ?>" aria-pressed="false"><?= v2_e($rc['name']) ?><span><?= $rc['n'] ?></span></button>
        <?php endforeach; ?>
      </div>
      <?php endif; ?>

      <ul class="xp-grid" id="it-grid"<?= $cards ? '' : ' hidden' ?>>
        <?php foreach ($cards as $i => $c) { $renderCard($c, $i, true); } ?>
      </ul>
      <?php if ($partnerItems): ?>
      <?php if ($cards): ?><h3 class="it-partner-h"><?= v2_te('More, through our partner') ?></h3><?php endif; ?>
      <ul class="xp-grid it-partner-grid">
        <?= v2_partner_cards($partnerItems, 'wegotrip', 'intent-' . $intentSlugSafe . ($city ? '-' . $citySlugSafe : ''), !$city) ?>
      </ul>
      <?= v2_partner_note('wegotrip') ?>
      <?php endif; ?>

      <?php if ($lastPage > 1):
          $maxLinks = 7;
          $start = max(1, $currentPageNum - 3);
          $end = min($lastPage, $start + $maxLinks - 1);
          $start = max(1, $end - $maxLinks + 1);
      ?>
      <nav class="pager" aria-label="<?= v2_te('Pages') ?>">
        <?php if ($currentPageNum > 1): ?><a href="<?= v2_e($pageUrl($currentPageNum - 1)) ?>" rel="prev" aria-label="<?= v2_te('Previous page') ?>"><?= v2_ic('arrow-left') ?></a><?php endif; ?>
        <?php for ($p = $start; $p <= $end; $p++): ?>
          <?php if ($p === $currentPageNum): ?><span aria-current="page"><?= $p ?></span><?php else: ?><a href="<?= v2_e($pageUrl($p)) ?>"><?= $p ?></a><?php endif; ?>
        <?php endfor; ?>
        <?php if ($currentPageNum < $lastPage): ?><a href="<?= v2_e($pageUrl($currentPageNum + 1)) ?>" rel="next" aria-label="<?= v2_te('Next page') ?>"><?= v2_ic('arrow-right') ?></a><?php endif; ?>
      </nav>
      <?php endif; ?>
      <?php endif; ?>
    </div>
  </section>

  <!-- ============================== CROSS-LINKING ============================== -->
  <?php if ($otherIntents || $sameIntent): ?>
  <section class="it-cross" aria-label="<?= v2_te('Keep exploring') ?>">
    <div class="wrap it-cross-grid">
      <?php if ($otherIntents): ?>
      <div class="it-cross-card">
        <p class="kicker"><?= v2_te('More ideas') ?></p>
        <h2><?= $city ? v2_te('More things to do in {city}', ['city' => $cityName]) : v2_te('Other kinds of activities') ?></h2>
        <div class="chips-links it-chips">
          <?php foreach ($otherIntents as $li): ?>
          <a href="<?= v2_e($li['path']) ?>"><?php if (!empty($li['icon'])): ?><span aria-hidden="true"><?= v2_e($li['icon']) ?></span><?php endif; ?><?= v2_e($li['name']) ?></a>
          <?php endforeach; ?>
        </div>
      </div>
      <?php endif; ?>
      <?php if ($sameIntent): ?>
      <div class="it-cross-card">
        <p class="kicker"><?= v2_te('Elsewhere') ?></p>
        <h2><?= v2_te('{what} in other cities', ['what' => $intentName]) ?></h2>
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

</main>

<?php include __DIR__ . '/includes/v2/footer.php'; ?>
