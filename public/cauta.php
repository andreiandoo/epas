<?php
/**
 * Faceted activity search: /search (v2 design).
 *
 * Every facet lives in the query string, so results stay shareable without client state:
 * text (q), day (data=Y-m-d), city, category, max price, traveller type, interests, sort, page.
 * The filters are a GET form of checkboxes (search.js applies a change at once on large screens and on "Show
 * results" on phones; city, category and price keep one value). "Other dates" opens a month calendar of links.
 * Also backs /interese/{slug} and /pentru-cine/{slug} (preset facets, see .htaccess).
 */
$pageCacheTTL = 120;
require_once __DIR__ . '/includes/page-cache.php';
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/api.php';
require_once __DIR__ . '/includes/nav-helpers.php';
require_once __DIR__ . '/includes/v2/helpers.php';
require_once __DIR__ . '/includes/v2/nav.php';
require_once __DIR__ . '/includes/v2/promoted.php';

// ---- Input ----
// English query names used by the Viaqui pages: date= (the day) and who= (traveller type).
if (!isset($_GET['data']) && isset($_GET['date'])) {
    $_GET['data'] = $_GET['date'];
}
if (!isset($_GET['traveler_types']) && isset($_GET['who']) && is_string($_GET['who'])) {
    $_GET['traveler_types'] = ['families' => 'familii', 'friends' => 'prieteni', 'couples' => 'cupluri'][$_GET['who']] ?? '';
}
$slugParam = function (string $key): string {
    $v = $_GET[$key] ?? '';
    return is_string($v) && preg_match('/^[a-z][a-z0-9-]+$/', $v) ? $v : '';
};
$csvParam = function (string $key): array {
    $v = $_GET[$key] ?? '';
    if (is_array($v)) { // the form without JavaScript sends interests[]=a&interests[]=b
        $v = implode(',', array_filter($v, 'is_string'));
    }
    return is_string($v) ? array_values(array_unique(array_filter(array_map('trim', explode(',', $v)), function ($s) {
        return (bool) preg_match('/^[a-z0-9-]+$/', $s);
    }))) : [];
};
$q        = isset($_GET['q']) && is_string($_GET['q']) ? mb_substr(trim($_GET['q']), 0, 80) : '';
$cityF    = $slugParam('city');
$catF     = $slugParam('category');
$intF     = $csvParam('interests');
$travF    = $csvParam('traveler_types');
$priceAllowed = [50, 100, 200, 500];
$maxPrice = (isset($_GET['max_price']) && in_array((int) $_GET['max_price'], $priceAllowed, true)) ? (int) $_GET['max_price'] : null;
$sortOptions = ['recommended' => v2_t('Recommended'), 'cheapest' => v2_t('Cheapest first'), 'soon' => v2_t('Soonest first')];
$sort     = (isset($_GET['sort']) && is_string($_GET['sort']) && isset($sortOptions[$_GET['sort']])) ? $_GET['sort'] : 'recommended';
$page     = max(1, (int) ($_GET['page'] ?? 1));

// Day ("When" in the homepage search): a real calendar day from today up to 90 days ahead.
$tz = new DateTimeZone('Europe/Bucharest');
$today = new DateTimeImmutable('today', $tz);
$dateF = '';
if (isset($_GET['data']) && is_string($_GET['data']) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $_GET['data'])) {
    $d = DateTimeImmutable::createFromFormat('!Y-m-d', $_GET['data'], $tz);
    if ($d && $d->format('Y-m-d') === $_GET['data'] && $d >= $today && $d <= $today->modify('+90 days')) {
        $dateF = $_GET['data'];
    }
}
// Weekday and month names in the visitor's language (the variable names are from the Romanian site this page came from).
$roDays = [v2_t('Sunday'), v2_t('Monday'), v2_t('Tuesday'), v2_t('Wednesday'), v2_t('Thursday'), v2_t('Friday'), v2_t('Saturday')];
$roDaysShort = [v2_t('Sun'), v2_t('Mon'), v2_t('Tue'), v2_t('Wed'), v2_t('Thu'), v2_t('Fri'), v2_t('Sat')];
$roMonths = [v2_t('January'), v2_t('February'), v2_t('March'), v2_t('April'), v2_t('May'), v2_t('June'), v2_t('July'), v2_t('August'), v2_t('September'), v2_t('October'), v2_t('November'), v2_t('December')];
$roMonthsShort = [v2_t('Jan'), v2_t('Feb'), v2_t('Mar'), v2_t('Apr'), v2_t('May'), v2_t('Jun'), v2_t('Jul'), v2_t('Aug'), v2_t('Sep'), v2_t('Oct'), v2_t('Nov'), v2_t('Dec')];
/** "Friday, 10 October". */
$dayName = function (DateTimeImmutable $d) use ($roDays, $roMonths): string {
    return v2_t('{weekday}, {day} {month}', ['weekday' => $roDays[(int) $d->format('w')], 'day' => $d->format('j'), 'month' => $roMonths[(int) $d->format('n') - 1]]);
};
/** "10 Oct". */
$dayShort = function (DateTimeImmutable $d) use ($roMonthsShort): string {
    return v2_t('{day} {month}', ['day' => $d->format('j'), 'month' => $roMonthsShort[(int) $d->format('n') - 1]]);
};
/** "today, Friday, 10 October", "tomorrow, Saturday, 11 October", else the day alone. */
$dayLabel = function (string $iso) use ($tz, $today, $dayName): string {
    $d = new DateTimeImmutable($iso, $tz);
    $diff = (int) $today->diff($d)->format('%r%a');
    $label = $dayName($d);
    return $diff === 0 ? v2_t('today, {date}', ['date' => $label]) : ($diff === 1 ? v2_t('tomorrow, {date}', ['date' => $label]) : $label);
};
/** On a card: "Available today", "Available tomorrow", "Available on Friday". */
$availLabel = function (string $iso) use ($tz, $today, $roDays): string {
    $d = new DateTimeImmutable($iso, $tz);
    $diff = (int) $today->diff($d)->format('%r%a');
    return $diff === 0 ? v2_t('Available today') : ($diff === 1 ? v2_t('Available tomorrow') : v2_t('Available on {weekday}', ['weekday' => $roDays[(int) $d->format('w')]]));
};

// ---- Fetch ----
$params = ['per_page' => 24, 'page' => $page];
if ($q !== '')   $params['search'] = $q;
if ($cityF)      $params['city'] = $cityF;
if ($catF)       $params['category'] = $catF;
if ($intF)       $params['interests'] = implode(',', $intF);
if ($travF)      $params['traveler_types'] = implode(',', $travF);
if ($maxPrice)   $params['max_price_ron'] = $maxPrice;
if ($sort !== 'recommended') $params['sort'] = $sort;
if ($dateF)      $params['date'] = $dateF;

$resp = api_cached('search_' . md5(json_encode($params)), fn () => api_get('/activities', $params), 120);
$items = $resp['data']['items'] ?? [];
if (!is_array($items)) $items = [];
$pagination = $resp['data']['pagination'] ?? ['current_page' => 1, 'last_page' => 1, 'total' => count($items)];
$total = (int) ($pagination['total'] ?? count($items));
if ($dateF && ($resp['data']['applied_date'] ?? null) !== $dateF && $items) {
    // Core deployment without the `date` filter: check this page's activities one by one
    // (available-dates, cached 10 min). The count then covers this page only.
    $horizon = (int) $today->diff(new DateTimeImmutable($dateF, $tz))->format('%a') + 1;
    $dateJobs = [];
    foreach ($items as $i => $a) {
        if (!empty($a['slug'])) {
            $dateJobs[$i] = ['key' => 'avail_dates_' . $a['slug'] . '_' . $horizon, 'endpoint' => '/activities/' . rawurlencode($a['slug']) . '/available-dates', 'params' => ['days' => $horizon], 'ttl' => 600];
        }
    }
    $dateRes = $dateJobs ? api_cached_many($dateJobs) : [];
    $items = array_values(array_filter($items, fn ($a, $i) => in_array($dateF, (array) ($dateRes[$i]['data']['dates'] ?? []), true), ARRAY_FILTER_USE_BOTH));
    $total = count($items);
    $pagination = ['current_page' => 1, 'last_page' => 1, 'total' => $total];
}
// Locations with online tickets that match the text or the city (activities module), shown above the experiences.
$amLocs = [];
if ($q !== '' || $cityF) {
    $locParams = array_filter(['q' => $q, 'city' => $cityF, 'per_page' => 6]);
    $locResp = api_cached('search_locs_' . md5(json_encode($locParams)), fn () => api_get('/activities-module/locations', $locParams), 300);
    $amLocs = (!empty($locResp['success']) && is_array($locResp['data']['items'] ?? null)) ? array_values(array_filter($locResp['data']['items'], fn ($l) => !empty($l['slug']))) : [];
}

$cards = [];
foreach ($items as $a) {
    if (is_array($a) && ($n = v2_activity($a))) {
        $cards[] = $n;
    }
}

// Interest and traveller-type facets come from the current results (plus whatever is already selected).
$prettySlug = fn (string $s) => mb_convert_case(str_replace('-', ' ', $s), MB_CASE_TITLE, 'UTF-8');
$intNames = [];
$travNames = [];
foreach ($items as $a) {
    foreach ((array) ($a['interests'] ?? []) as $x) if (!empty($x['slug'])) $intNames[$x['slug']] = $x['name'] ?? $x['slug'];
    foreach ((array) ($a['traveler_types'] ?? []) as $x) if (!empty($x['slug'])) $travNames[$x['slug']] = $x['name'] ?? $x['slug'];
}
foreach ($intF as $s) $intNames[$s] = $intNames[$s] ?? $prettySlug($s);
foreach ($travF as $s) $travNames[$s] = $travNames[$s] ?? $prettySlug($s);

// ---- Links (only the validated parameters are carried over) ----
$baseGet = array_filter([
    'q' => $q, 'data' => $dateF, 'city' => $cityF, 'category' => $catF,
    'interests' => implode(',', $intF), 'traveler_types' => implode(',', $travF),
    'max_price' => $maxPrice ? (string) $maxPrice : '', 'sort' => $sort === 'recommended' ? '' : $sort,
], fn ($v) => $v !== '');
$qs = function (array $over = []) use ($baseGet): string {
    $p = array_filter(array_merge($baseGet, $over), fn ($v) => $v !== '' && $v !== null);
    return $p ? '/search?' . http_build_query($p) : '/search';
};
$toggleCsv = function (string $key, array $current, string $val) use ($qs): string {
    $next = in_array($val, $current, true) ? array_values(array_diff($current, [$val])) : array_merge($current, [$val]);
    return $qs([$key => implode(',', $next)]);
};

// Every visible city for the city filter (searchable): the featured ones in the menu's order (most activities first),
// then the rest alphabetically; the list shows the first 8 until "all" or a search.
$foldName = fn (string $s): string => strtr(mb_strtolower($s), ['ă' => 'a', 'â' => 'a', 'î' => 'i', 'ș' => 's', 'ş' => 's', 'ț' => 't', 'ţ' => 't']);
$cityOptions = [];
foreach ($V2NAV['citiesList'] as $c) {
    $cityOptions[$c['slug']] = ['slug' => $c['slug'], 'name' => $c['name'], 'count' => (int) $c['count'], 'county' => (string) ($V2NAV['allCities'][$c['slug']]['county'] ?? '')];
}
$restCities = array_filter($V2NAV['allCities'] ?? [], fn ($c) => !isset($cityOptions[$c['slug']]) && $c['name'] !== '');
uasort($restCities, fn ($a, $b) => strcmp($foldName($a['name']), $foldName($b['name'])));
foreach ($restCities as $c) {
    $cityOptions[$c['slug']] = ['slug' => $c['slug'], 'name' => $c['name'], 'count' => 0, 'county' => (string) ($c['county'] ?? '')];
}
if ($cityF && !isset($cityOptions[$cityF])) {
    $cityOptions = [$cityF => ['slug' => $cityF, 'name' => $prettySlug($cityF), 'count' => 0, 'county' => '']] + $cityOptions;
}
$cityOptions = array_values($cityOptions);
$cityShown = 8;
$cityName = $cityF ? ($V2NAV['cities'][$cityF]['name'] ?? $prettySlug($cityF)) : '';
$catName = $catF ? ($V2NAV['categoryBySlug'][$catF]['name'] ?? $prettySlug($catF)) : '';

// ---- Heading, active filters ----
$heading = v2_t('Search activities');
if ($q !== '') {
    $heading = v2_t('Results for “{query}”', ['query' => $q]);
} elseif ($travF) {
    $heading = v2_t('Activities for {who}', ['who' => mb_strtolower($travNames[$travF[0]])]);
} elseif ($intF) {
    $heading = v2_t('Activities · {interest}', ['interest' => $intNames[$intF[0]]]);
} elseif ($catF) {
    $heading = $catName;
} elseif ($cityF) {
    $heading = v2_t('Things to do in {city}', ['city' => $cityName]);
} elseif ($dateF) {
    $heading = v2_t('Activities available {day}', ['day' => $dayLabel($dateF)]);
}

$active = [];
if ($q !== '') $active[] = ['“' . $q . '”', $qs(['q' => ''])];
if ($dateF) $active[] = [mb_convert_case(mb_substr($dayLabel($dateF), 0, 1), MB_CASE_UPPER, 'UTF-8') . mb_substr($dayLabel($dateF), 1), $qs(['data' => ''])];
if ($cityF) $active[] = [$cityName, $qs(['city' => ''])];
if ($catF) $active[] = [$catName, $qs(['category' => ''])];
if ($maxPrice) $active[] = [v2_t('Under {price}', ['price' => v2_money($maxPrice)]), $qs(['max_price' => ''])];
foreach ($travF as $s) $active[] = [$travNames[$s], $toggleCsv('traveler_types', $travF, $s)];
foreach ($intF as $s) $active[] = [$intNames[$s], $toggleCsv('interests', $intF, $s)];
$activeCount = count($active);
$clearAll = $qs(['data' => '', 'city' => '', 'category' => '', 'interests' => '', 'traveler_types' => '', 'max_price' => '']);

$farDate = $dateF && $today->diff(new DateTimeImmutable($dateF, $tz))->days >= 10;

// ---- Page ----
$pageTitle = $q !== '' ? $heading : v2_t('Search activities, experiences and attractions');
$pageDescription = v2_t('Search and filter activities on Viaqui by day, city, category, price, interests and who they are for. Book online, get in with a QR ticket.');
$canonicalUrl = SITE_URL . '/search';
$structuredData = [];
$v2Styles = ['search.css', 'hub.css'];
$v2Scripts = ['search.js'];

include __DIR__ . '/includes/v2/head.php';
include __DIR__ . '/includes/v2/header.php';
?>
<main id="main" class="page-main" tabindex="-1">
  <section class="sr-hero" aria-labelledby="sr-h">
    <div class="wrap">
      <h1 class="sr-h" id="sr-h"><?= v2_e($heading) ?></h1>
      <?php $shownTotal = $total + count($amLocs); ?>
      <p class="sr-count"><?= $dateF
          ? v2_t('<b>{n}</b> {results} available {day}', ['n' => $shownTotal, 'results' => v2_e(v2_plural($shownTotal, 'result', 'results')), 'day' => v2_e($dayLabel($dateF))])
          : v2_t('<b>{n}</b> {results}', ['n' => $shownTotal, 'results' => v2_e(v2_plural($shownTotal, 'result', 'results'))]) ?></p>

      <form class="sr-search" action="/search" method="get" role="search">
        <?= v2_ic('magnifying-glass') ?>
        <label class="sr" for="sr-q"><?= v2_te('Search activities') ?></label>
        <input id="sr-q" name="q" type="search" value="<?= v2_e($q) ?>" placeholder="<?= v2_te('Search activities, attractions or cities') ?>" autocomplete="off">
        <?php foreach ($baseGet as $k => $v): if ($k === 'q') continue; ?><input type="hidden" name="<?= v2_e($k) ?>" value="<?= v2_e($v) ?>"><?php endforeach; ?>
        <button class="btn btn-primary" type="submit"><?= v2_te('Search') ?></button>
      </form>

      <ul class="sr-days" aria-label="<?= v2_te('Pick a day') ?>">
        <li><a class="day day-any" href="<?= v2_e($qs(['data' => ''])) ?>"<?= $dateF === '' ? ' aria-current="true"' : '' ?>><span class="day-dow"><?= v2_te('Any time') ?></span><?= v2_ic('calendar-blank') ?></a></li>
        <?php for ($i = 0; $i < 10; $i++): $day = $today->modify('+' . $i . ' day'); $iso = $day->format('Y-m-d'); ?>
        <li><a class="day" href="<?= v2_e($qs(['data' => $iso])) ?>"<?= $dateF === $iso ? ' aria-current="true"' : '' ?>><span class="day-dow"><?= $i === 0 ? v2_te('Today') : ($i === 1 ? v2_te('Tomorrow') : v2_e($roDaysShort[(int) $day->format('w')])) ?></span><span class="day-num"><?= $day->format('j') ?></span><span class="day-mon" aria-hidden="true"><?= v2_e($roMonthsShort[(int) $day->format('n') - 1]) ?></span><span class="sr">, <?= v2_e($dayName($day)) ?></span></a></li>
        <?php endfor; ?>
        <li class="day-more-li">
          <button class="day day-more day-more-btn<?= $farDate ? ' is-picked' : '' ?>" type="button" id="sr-cal-btn" aria-haspopup="dialog" aria-expanded="false" aria-controls="sr-cal"><?= v2_ic('calendar-blank') ?><span class="day-opt"><?= $farDate ? v2_e($dayShort(new DateTimeImmutable($dateF, $tz))) : v2_te('Other dates') ?></span></button>
          <form class="day day-more day-more-form<?= $farDate ? ' is-picked' : '' ?>" action="/search" method="get">
            <?= v2_ic('calendar-blank') ?><span class="day-opt"><?= $farDate ? v2_e($dayShort(new DateTimeImmutable($dateF, $tz))) : v2_te('Other dates') ?></span>
            <?php foreach ($baseGet as $k => $v): if ($k === 'data') continue; ?><input type="hidden" name="<?= v2_e($k) ?>" value="<?= v2_e($v) ?>"><?php endforeach; ?>
            <label class="sr" for="sr-date"><?= v2_te('Pick another date') ?></label>
            <input id="sr-date" type="date" name="data" value="<?= v2_e($dateF) ?>" min="<?= $today->format('Y-m-d') ?>" max="<?= $today->modify('+90 days')->format('Y-m-d') ?>">
          </form>
        </li>
      </ul>
      <div class="sr-cal" id="sr-cal" role="dialog" aria-labelledby="sr-cal-title" hidden
        data-min="<?= $today->format('Y-m-d') ?>" data-max="<?= $today->modify('+90 days')->format('Y-m-d') ?>" data-selected="<?= v2_e($dateF) ?>"
        data-href="<?= v2_e($qs(['data' => '0000-00-00'])) ?>">
        <div class="sr-cal-head">
          <button class="icon-btn" type="button" data-cal-step="-1" aria-label="<?= v2_te('Previous month') ?>"><?= v2_ic('arrow-left') ?></button>
          <p class="sr-cal-title" id="sr-cal-title" aria-live="polite"></p>
          <button class="icon-btn" type="button" data-cal-step="1" aria-label="<?= v2_te('Next month') ?>"><?= v2_ic('arrow-right') ?></button>
        </div>
        <div class="sr-cal-dow" aria-hidden="true"><span><?= v2_te('Mo') ?></span><span><?= v2_te('Tu') ?></span><span><?= v2_te('We') ?></span><span><?= v2_te('Th') ?></span><span><?= v2_te('Fr') ?></span><span><?= v2_te('Sa') ?></span><span><?= v2_te('Su') ?></span></div>
        <div class="sr-cal-grid" id="sr-cal-grid"></div>
        <div class="sr-cal-foot">
          <small><?= v2_te('You can pick a day up to {day}.', ['day' => $dayName($today->modify('+90 days'))]) ?></small>
          <button class="link-btn" type="button" data-cal-close><?= v2_te('Close') ?></button>
        </div>
      </div>
    </div>
  </section>

  <section class="sr-body" aria-labelledby="sr-results-h">
    <div class="wrap sr-grid">
      <aside class="sr-filters" aria-labelledby="sr-filters-h">
        <div class="sr-mbar">
          <button class="sr-filter-toggle" type="button" aria-expanded="false" aria-controls="sr-filter-body"><?= v2_ic('list') ?><?= v2_te('Filters') ?><?php if ($activeCount): ?><span class="sr-badge"><?= $activeCount ?></span><?php endif; ?><?= v2_ic('caret-down') ?></button>
          <form class="sr-msort" action="/search" method="get">
            <?php foreach ($baseGet as $k => $v): if ($k === 'sort') continue; ?><input type="hidden" name="<?= v2_e($k) ?>" value="<?= v2_e($v) ?>"><?php endforeach; ?>
            <label class="sr" for="sr-sort-m"><?= v2_te('Sort by') ?></label>
            <select class="select" id="sr-sort-m" name="sort">
              <?php foreach ($sortOptions as $key => $label): ?><option value="<?= $key === 'recommended' ? '' : v2_e($key) ?>"<?= $sort === $key ? ' selected' : '' ?>><?= v2_e($label) ?></option><?php endforeach; ?>
            </select>
            <noscript><button class="btn btn-ghost" type="submit"><?= v2_te('OK') ?></button></noscript>
          </form>
        </div>
        <h2 class="sr-filters-h" id="sr-filters-h"><?= v2_te('Filters') ?></h2>
        <form class="sr-filter-body" id="sr-filter-body" action="/search" method="get">
          <?php foreach (['q' => $q, 'data' => $dateF, 'sort' => $sort === 'recommended' ? '' : $sort] as $k => $v): if ($v === '') continue; ?><input type="hidden" name="<?= $k ?>" value="<?= v2_e($v) ?>"><?php endforeach; ?>
          <?php if ($cityOptions): ?>
          <fieldset class="fgroup" data-group="city" data-single>
            <legend class="flabel"><?= v2_te('City') ?></legend>
            <div class="fsearch">
              <?= v2_ic('magnifying-glass') ?>
              <label class="sr" for="sr-city-q"><?= v2_te('Search for a city') ?></label>
              <input id="sr-city-q" type="search" placeholder="<?= v2_te('Search for a city') ?>" autocomplete="off" maxlength="40" aria-controls="sr-city-list">
            </div>
            <ul class="fchecks" id="sr-city-list">
              <?php foreach ($cityOptions as $ci => $c): $on = $cityF === $c['slug']; ?>
              <li<?= $ci >= $cityShown && !$on ? ' class="is-extra"' : '' ?> data-q="<?= v2_e($foldName($c['name'] . ' ' . $c['county'])) ?>"><label class="fcheck"><input type="checkbox" name="city" value="<?= v2_e($c['slug']) ?>"<?= $on ? ' checked' : '' ?>><span class="fbox" aria-hidden="true"><?= v2_ic('check') ?></span><span class="fname"><?= v2_e($c['name']) ?></span><?php if ($c['count'] > 0): ?><small><?= $c['count'] ?></small><?php endif; ?></label></li>
              <?php endforeach; ?>
            </ul>
            <p class="fnone" id="sr-city-none" hidden><?= v2_te('No city with this name.') ?></p>
            <?php if (count($cityOptions) > $cityShown): ?><button class="fmore" type="button" id="sr-city-more" aria-controls="sr-city-list" aria-expanded="false"><?= v2_te('Show all {cities}', ['cities' => v2_num(count($cityOptions), 'city', 'cities')]) ?></button><?php endif; ?>
          </fieldset>
          <?php endif; ?>
          <?php if ($V2NAV['categories']): ?>
          <fieldset class="fgroup" data-group="category" data-single>
            <legend class="flabel"><?= v2_te('Category') ?></legend>
            <ul class="fchecks">
              <?php foreach ($V2NAV['categories'] as $c): ?><li><label class="fcheck"><input type="checkbox" name="category" value="<?= v2_e($c['slug']) ?>"<?= $catF === $c['slug'] ? ' checked' : '' ?>><span class="fbox" aria-hidden="true"><?= v2_ic('check') ?></span><span class="fname"><?= v2_e($c['name']) ?></span></label></li><?php endforeach; ?>
            </ul>
          </fieldset>
          <?php endif; ?>
          <fieldset class="fgroup" data-group="max_price" data-single>
            <legend class="flabel"><?= v2_te('Maximum price') ?></legend>
            <ul class="fchecks">
              <?php foreach ($priceAllowed as $p): ?><li><label class="fcheck"><input type="checkbox" name="max_price" value="<?= $p ?>"<?= $maxPrice === $p ? ' checked' : '' ?>><span class="fbox" aria-hidden="true"><?= v2_ic('check') ?></span><span class="fname"><?= v2_te('Under {price}', ['price' => v2_money($p)]) ?></span></label></li><?php endforeach; ?>
            </ul>
          </fieldset>
          <?php if ($travNames): ?>
          <fieldset class="fgroup" data-group="traveler_types">
            <legend class="flabel"><?= v2_te('Who it is for') ?></legend>
            <ul class="fchecks">
              <?php foreach ($travNames as $sl => $n): ?><li><label class="fcheck"><input type="checkbox" name="traveler_types[]" value="<?= v2_e($sl) ?>"<?= in_array($sl, $travF, true) ? ' checked' : '' ?>><span class="fbox" aria-hidden="true"><?= v2_ic('check') ?></span><span class="fname"><?= v2_e($n) ?></span></label></li><?php endforeach; ?>
            </ul>
          </fieldset>
          <?php endif; ?>
          <?php if ($intNames): ?>
          <fieldset class="fgroup" data-group="interests">
            <legend class="flabel"><?= v2_te('Interests') ?></legend>
            <ul class="fchecks">
              <?php foreach ($intNames as $sl => $n): ?><li><label class="fcheck"><input type="checkbox" name="interests[]" value="<?= v2_e($sl) ?>"<?= in_array($sl, $intF, true) ? ' checked' : '' ?>><span class="fbox" aria-hidden="true"><?= v2_ic('check') ?></span><span class="fname"><?= v2_e($n) ?></span></label></li><?php endforeach; ?>
            </ul>
          </fieldset>
          <?php endif; ?>
          <div class="sr-apply">
            <button class="btn btn-primary" type="submit"><?= v2_te('Show results') ?></button>
            <?php if ($activeCount): ?><a class="aclear" href="<?= v2_e($clearAll) ?>"><?= v2_te('Clear filters') ?></a><?php endif; ?>
          </div>
        </form>
      </aside>

      <div class="sr-results">
        <h2 class="sr" id="sr-results-h"><?= v2_te('Results') ?></h2>
        <?php if ($amLocs): ?>
        <div class="sr-locs">
          <p class="flabel"><?= v2_te('Venues with online tickets') ?></p>
          <ul class="sr-locs-list">
            <?php foreach ($amLocs as $li => $l):
                $lImg = v2_media_url($l['cover_image'] ?? null);
                $lPrice = !empty($l['min_price_cents']) ? v2_own_price_label((int) $l['min_price_cents'], $l['currency'] ?? null) : '';
                $lLine = implode(' · ', array_filter([navFlatName($l['city']['name'] ?? ''), $lPrice !== '' ? v2_t('from {price}', ['price' => $lPrice]) : '']));
            ?>
            <li><a class="sr-loc" href="/venue/<?= v2_e($l['slug']) ?>">
              <span class="sr-loc-media"><?= $lImg ? v2_photo([$lImg, 0, 0, '']) : v2_fallback(navFlatName($l['name'] ?? ''), $li) ?></span>
              <span class="sr-loc-t"><b><?= v2_e(navFlatName($l['name'] ?? '')) ?></b><small><?= !empty($l['is_promoted']) ? '<em class="sr-loc-promo">' . v2_te('Promoted') . '</em> · ' : '' ?><?= v2_e($lLine) ?></small></span>
              <?= v2_ic('arrow-right') ?>
            </a></li>
            <?php endforeach; ?>
          </ul>
        </div>
        <?php endif; ?>
        <div class="sr-bar">
          <?php if ($active): ?>
          <ul class="sr-active" aria-label="<?= v2_te('Active filters') ?>">
            <?php foreach ($active as [$label, $href]): ?><li><a class="achip" href="<?= v2_e($href) ?>"><?= v2_e($label) ?><?= v2_ic('x') ?><span class="sr"> <?= v2_te('(remove)') ?></span></a></li><?php endforeach; ?>
            <?php if ($activeCount > 1): ?><li><a class="aclear" href="<?= v2_e($clearAll) ?>"><?= v2_te('Clear filters') ?></a></li><?php endif; ?>
          </ul>
          <?php endif; ?>
          <nav class="sr-sort" aria-label="<?= v2_te('Sort the results') ?>">
            <?php foreach ($sortOptions as $key => $label): ?><a href="<?= v2_e($qs(['sort' => $key === 'recommended' ? '' : $key])) ?>"<?= $sort === $key ? ' aria-current="true"' : '' ?>><?= v2_e($label) ?></a><?php endforeach; ?>
          </nav>
        </div>

        <?php if ($cards): ?>
        <ul class="xp-grid" data-reveal>
          <?php foreach ($cards as $i => $a): ?>
          <li class="xp">
            <a href="<?= v2_e($a['href']) ?>">
              <span class="xp-media"><?= $a['image'] ? v2_photo([$a['image'], 0, 0, '']) : v2_fallback($a['title'], $i) ?><?= $a['promoted'] ? v2_promoted_tag() : '' ?></span>
              <span class="xp-body">
                <span class="xp-cat"><?= v2_e($a['catName']) ?></span>
                <span class="xp-title" title="<?= v2_e($a['title']) ?>"><?= v2_e($a['title']) ?></span>
                <span class="xp-meta"><?php if ($a['city']): ?><span><?= v2_ic('map-pin') ?><?= v2_e($a['city']) ?></span><?php endif; ?><?php if ($a['dur']): ?><span><?= v2_ic('clock') ?><?= v2_e($a['dur']) ?></span><?php endif; ?></span>
                <span class="xp-foot">
                  <?php if ($dateF): ?><span class="xp-avail"><?= v2_ic('check-circle') ?><span><?= v2_e($availLabel($dateF)) ?></span></span>
                  <?php else: ?><span class="xp-avail is-muted"><?= v2_ic('calendar-blank') ?><span><?= v2_te('See available days') ?></span></span><?php endif; ?>
                  <?php if ($a['price']): ?><span class="xp-price"><?= v2_t('from<b>{price}</b>', ['price' => v2_e($a['priceLabel'] !== '' ? $a['priceLabel'] : v2_money($a['price']))]) ?></span><?php endif; ?>
                </span>
              </span>
            </a>
          </li>
          <?php endforeach; ?>
        </ul>

        <?php $last = max(1, (int) ($pagination['last_page'] ?? 1)); if ($last > 1): ?>
        <nav class="pager" aria-label="<?= v2_te('Result pages') ?>">
          <?php if ($page > 1): ?><a href="<?= v2_e($qs(['page' => $page - 1 > 1 ? $page - 1 : ''])) ?>" aria-label="<?= v2_te('Previous page') ?>"><?= v2_ic('arrow-left') ?></a><?php endif; ?>
          <?php for ($p = 1; $p <= min($last, 12); $p++): ?>
            <?php if ($p === $page): ?><span aria-current="page"><?= $p ?></span><?php else: ?><a href="<?= v2_e($qs(['page' => $p === 1 ? '' : $p])) ?>"><?= $p ?></a><?php endif; ?>
          <?php endfor; ?>
          <?php if ($page < $last): ?><a href="<?= v2_e($qs(['page' => $page + 1])) ?>" aria-label="<?= v2_te('Next page') ?>"><?= v2_ic('arrow-right') ?></a><?php endif; ?>
        </nav>
        <?php endif; ?>

        <?php else: ?>
        <div class="sr-empty">
          <h2><?= $amLocs ? v2_te('No experiences for this search.') : v2_te('We found nothing for this search.') ?></h2>
          <p><?= $dateF ? v2_te('Try another day, another city, or drop a few filters.') : v2_te('Try another city, or drop a few filters.') ?></p>
          <div class="sr-empty-cta">
            <?php if ($activeCount): ?><a class="btn btn-light" href="<?= v2_e($clearAll) ?>"><?= v2_te('Clear filters') ?></a><?php endif; ?>
            <a class="btn btn-ghost" href="/search"><?= v2_te('See all experiences') ?></a>
          </div>
          <?php if ($V2NAV['categories']): ?>
          <div class="fchips">
            <?php foreach (array_slice($V2NAV['categories'], 0, 6) as $c): ?><a class="fchip" href="<?= v2_e($c['href']) ?>"><?= v2_e($c['name']) ?></a><?php endforeach; ?>
          </div>
          <?php endif; ?>
          <svg class="sr-empty-line" viewBox="0 590 3240 310" aria-hidden="true"><use href="#drum-g"/></svg>
        </div>
        <?php endif; ?>
      </div>
    </div>
  </section>
</main>
<?php include __DIR__ . '/includes/v2/footer.php'; ?>
