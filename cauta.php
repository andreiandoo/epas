<?php
/**
 * Faceted activity search — /cauta (v2 design).
 *
 * Every facet lives in the query string, so results stay shareable without client state:
 * text (q), day (data=Y-m-d), city, category, max price, traveller type, interests, sort, page.
 * The filters are a GET form of checkboxes (search.js applies a change at once on large screens and on "Arată
 * rezultatele" on phones; city, category and price keep one value). "Alte date" opens a month calendar of links.
 * Also backs /interese/{slug} and /pentru-cine/{slug} (preset facets, see .htaccess).
 */
$pageCacheTTL = 120;
require_once __DIR__ . '/includes/page-cache.php';
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/api.php';
require_once __DIR__ . '/includes/nav-helpers.php';
require_once __DIR__ . '/includes/v2/helpers.php';
require_once __DIR__ . '/includes/v2/nav.php';

// ---- Input ----
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
$sortOptions = ['recommended' => 'Recomandate', 'cheapest' => 'Cele mai ieftine', 'soon' => 'Cele mai apropiate'];
$sort     = (isset($_GET['sort']) && is_string($_GET['sort']) && isset($sortOptions[$_GET['sort']])) ? $_GET['sort'] : 'recommended';
$page     = max(1, (int) ($_GET['page'] ?? 1));

// Day ("Când" in the homepage search): a real calendar day from today up to 90 days ahead.
$tz = new DateTimeZone('Europe/Bucharest');
$today = new DateTimeImmutable('today', $tz);
$dateF = '';
if (isset($_GET['data']) && is_string($_GET['data']) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $_GET['data'])) {
    $d = DateTimeImmutable::createFromFormat('!Y-m-d', $_GET['data'], $tz);
    if ($d && $d->format('Y-m-d') === $_GET['data'] && $d >= $today && $d <= $today->modify('+90 days')) {
        $dateF = $_GET['data'];
    }
}
$roDays = ['duminică', 'luni', 'marți', 'miercuri', 'joi', 'vineri', 'sâmbătă'];
$roDaysShort = ['dum', 'lun', 'mar', 'mie', 'joi', 'vin', 'sâm'];
$roMonths = ['ianuarie', 'februarie', 'martie', 'aprilie', 'mai', 'iunie', 'iulie', 'august', 'septembrie', 'octombrie', 'noiembrie', 'decembrie'];
$roMonthsShort = ['ian', 'feb', 'mar', 'apr', 'mai', 'iun', 'iul', 'aug', 'sep', 'oct', 'noi', 'dec'];
$dayLabel = function (string $iso) use ($tz, $today, $roDays, $roMonths): string {
    $d = new DateTimeImmutable($iso, $tz);
    $diff = (int) $today->diff($d)->format('%r%a');
    $label = $roDays[(int) $d->format('w')] . ', ' . $d->format('j') . ' ' . $roMonths[(int) $d->format('n') - 1];
    return $diff === 0 ? 'azi, ' . $label : ($diff === 1 ? 'mâine, ' . $label : $label);
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
    return $p ? '/cauta?' . http_build_query($p) : '/cauta';
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
$heading = 'Caută activități';
if ($q !== '') {
    $heading = 'Rezultate pentru „' . $q . '”';
} elseif ($travF) {
    $heading = 'Activități pentru ' . mb_strtolower($travNames[$travF[0]]);
} elseif ($intF) {
    $heading = 'Activități · ' . $intNames[$intF[0]];
} elseif ($catF) {
    $heading = $catName;
} elseif ($cityF) {
    $heading = 'Activități în ' . $cityName;
} elseif ($dateF) {
    $heading = 'Activități disponibile ' . $dayLabel($dateF);
}

$active = [];
if ($q !== '') $active[] = ['„' . $q . '”', $qs(['q' => ''])];
if ($dateF) $active[] = [mb_convert_case(mb_substr($dayLabel($dateF), 0, 1), MB_CASE_UPPER, 'UTF-8') . mb_substr($dayLabel($dateF), 1), $qs(['data' => ''])];
if ($cityF) $active[] = [$cityName, $qs(['city' => ''])];
if ($catF) $active[] = [$catName, $qs(['category' => ''])];
if ($maxPrice) $active[] = ['Sub ' . $maxPrice . ' lei', $qs(['max_price' => ''])];
foreach ($travF as $s) $active[] = [$travNames[$s], $toggleCsv('traveler_types', $travF, $s)];
foreach ($intF as $s) $active[] = [$intNames[$s], $toggleCsv('interests', $intF, $s)];
$activeCount = count($active);
$clearAll = $qs(['data' => '', 'city' => '', 'category' => '', 'interests' => '', 'traveler_types' => '', 'max_price' => '']);

$farDate = $dateF && $today->diff(new DateTimeImmutable($dateF, $tz))->days >= 10;

// ---- Page ----
$pageTitle = $q !== '' ? $heading : 'Caută activități, experiențe și atracții';
$pageDescription = 'Caută și filtrează activități pe bilete.online după zi, oraș, categorie, preț, interese și pentru cine. Rezervi online, intri cu bilet QR.';
$canonicalUrl = SITE_URL . '/cauta';
$structuredData = [];
$v2Styles = ['search.css'];
$v2Scripts = ['search.js'];

include __DIR__ . '/includes/v2/head.php';
include __DIR__ . '/includes/v2/header.php';
?>
<main id="main" class="page-main" tabindex="-1">
  <section class="sr-hero" aria-labelledby="sr-h">
    <div class="wrap">
      <h1 class="sr-h" id="sr-h"><?= v2_e($heading) ?></h1>
      <p class="sr-count"><b><?= $total ?></b> <?= $total === 1 ? 'rezultat' : 'rezultate' ?><?= $dateF ? ' disponibile ' . v2_e($dayLabel($dateF)) : '' ?></p>

      <form class="sr-search" action="/cauta" method="get" role="search">
        <?= v2_ic('magnifying-glass') ?>
        <label class="sr" for="sr-q">Caută activități</label>
        <input id="sr-q" name="q" type="search" value="<?= v2_e($q) ?>" placeholder="Caută activități, atracții sau orașe" autocomplete="off">
        <?php foreach ($baseGet as $k => $v): if ($k === 'q') continue; ?><input type="hidden" name="<?= v2_e($k) ?>" value="<?= v2_e($v) ?>"><?php endforeach; ?>
        <button class="btn btn-primary" type="submit">Caută</button>
      </form>

      <ul class="sr-days" aria-label="Alege ziua">
        <li><a class="day day-any" href="<?= v2_e($qs(['data' => ''])) ?>"<?= $dateF === '' ? ' aria-current="true"' : '' ?>><span class="day-dow">Oricând</span><?= v2_ic('calendar-blank') ?></a></li>
        <?php for ($i = 0; $i < 10; $i++): $day = $today->modify('+' . $i . ' day'); $iso = $day->format('Y-m-d'); ?>
        <li><a class="day" href="<?= v2_e($qs(['data' => $iso])) ?>"<?= $dateF === $iso ? ' aria-current="true"' : '' ?>><span class="day-dow"><?= $i === 0 ? 'Azi' : ($i === 1 ? 'Mâine' : $roDaysShort[(int) $day->format('w')]) ?></span><span class="day-num"><?= $day->format('j') ?></span><span class="day-mon" aria-hidden="true"><?= $roMonthsShort[(int) $day->format('n') - 1] ?></span><span class="sr">, <?= v2_e($roDays[(int) $day->format('w')] . ' ' . $day->format('j') . ' ' . $roMonths[(int) $day->format('n') - 1]) ?></span></a></li>
        <?php endfor; ?>
        <li class="day-more-li">
          <button class="day day-more day-more-btn<?= $farDate ? ' is-picked' : '' ?>" type="button" id="sr-cal-btn" aria-haspopup="dialog" aria-expanded="false" aria-controls="sr-cal"><?= v2_ic('calendar-blank') ?><span class="day-opt"><?= $farDate ? v2_e((new DateTimeImmutable($dateF, $tz))->format('j') . ' ' . $roMonthsShort[(int) (new DateTimeImmutable($dateF, $tz))->format('n') - 1]) : 'Alte date' ?></span></button>
          <form class="day day-more day-more-form<?= $farDate ? ' is-picked' : '' ?>" action="/cauta" method="get">
            <?= v2_ic('calendar-blank') ?><span class="day-opt"><?= $farDate ? v2_e((new DateTimeImmutable($dateF, $tz))->format('j') . ' ' . mb_substr($roMonths[(int) (new DateTimeImmutable($dateF, $tz))->format('n') - 1], 0, 3)) : 'Alte date' ?></span>
            <?php foreach ($baseGet as $k => $v): if ($k === 'data') continue; ?><input type="hidden" name="<?= v2_e($k) ?>" value="<?= v2_e($v) ?>"><?php endforeach; ?>
            <label class="sr" for="sr-date">Alege altă dată</label>
            <input id="sr-date" type="date" name="data" value="<?= v2_e($dateF) ?>" min="<?= $today->format('Y-m-d') ?>" max="<?= $today->modify('+90 days')->format('Y-m-d') ?>">
          </form>
        </li>
      </ul>
      <div class="sr-cal" id="sr-cal" role="dialog" aria-labelledby="sr-cal-title" hidden
        data-min="<?= $today->format('Y-m-d') ?>" data-max="<?= $today->modify('+90 days')->format('Y-m-d') ?>" data-selected="<?= v2_e($dateF) ?>"
        data-href="<?= v2_e($qs(['data' => '0000-00-00'])) ?>">
        <div class="sr-cal-head">
          <button class="icon-btn" type="button" data-cal-step="-1" aria-label="Luna anterioară"><?= v2_ic('arrow-left') ?></button>
          <p class="sr-cal-title" id="sr-cal-title" aria-live="polite"></p>
          <button class="icon-btn" type="button" data-cal-step="1" aria-label="Luna următoare"><?= v2_ic('arrow-right') ?></button>
        </div>
        <div class="sr-cal-dow" aria-hidden="true"><span>L</span><span>Ma</span><span>Mi</span><span>J</span><span>V</span><span>S</span><span>D</span></div>
        <div class="sr-cal-grid" id="sr-cal-grid"></div>
        <div class="sr-cal-foot">
          <small>Poți alege până <?= v2_e('pe ' . $dayLabel($today->modify('+90 days')->format('Y-m-d'))) ?>.</small>
          <button class="link-btn" type="button" data-cal-close>Închide</button>
        </div>
      </div>
    </div>
  </section>

  <section class="sr-body" aria-labelledby="sr-results-h">
    <div class="wrap sr-grid">
      <aside class="sr-filters" aria-labelledby="sr-filters-h">
        <div class="sr-mbar">
          <button class="sr-filter-toggle" type="button" aria-expanded="false" aria-controls="sr-filter-body"><?= v2_ic('list') ?>Filtre<?php if ($activeCount): ?><span class="sr-badge"><?= $activeCount ?></span><?php endif; ?><?= v2_ic('caret-down') ?></button>
          <form class="sr-msort" action="/cauta" method="get">
            <?php foreach ($baseGet as $k => $v): if ($k === 'sort') continue; ?><input type="hidden" name="<?= v2_e($k) ?>" value="<?= v2_e($v) ?>"><?php endforeach; ?>
            <label class="sr" for="sr-sort-m">Sortare</label>
            <select class="select" id="sr-sort-m" name="sort">
              <?php foreach ($sortOptions as $key => $label): ?><option value="<?= $key === 'recommended' ? '' : v2_e($key) ?>"<?= $sort === $key ? ' selected' : '' ?>><?= v2_e($label) ?></option><?php endforeach; ?>
            </select>
            <noscript><button class="btn btn-ghost" type="submit">OK</button></noscript>
          </form>
        </div>
        <h2 class="sr-filters-h" id="sr-filters-h">Filtre</h2>
        <form class="sr-filter-body" id="sr-filter-body" action="/cauta" method="get">
          <?php foreach (['q' => $q, 'data' => $dateF, 'sort' => $sort === 'recommended' ? '' : $sort] as $k => $v): if ($v === '') continue; ?><input type="hidden" name="<?= $k ?>" value="<?= v2_e($v) ?>"><?php endforeach; ?>
          <?php if ($cityOptions): ?>
          <fieldset class="fgroup" data-group="city" data-single>
            <legend class="flabel">Oraș</legend>
            <div class="fsearch">
              <?= v2_ic('magnifying-glass') ?>
              <label class="sr" for="sr-city-q">Caută orașul</label>
              <input id="sr-city-q" type="search" placeholder="Caută orașul" autocomplete="off" maxlength="40" aria-controls="sr-city-list">
            </div>
            <ul class="fchecks" id="sr-city-list">
              <?php foreach ($cityOptions as $ci => $c): $on = $cityF === $c['slug']; ?>
              <li<?= $ci >= $cityShown && !$on ? ' class="is-extra"' : '' ?> data-q="<?= v2_e($foldName($c['name'] . ' ' . $c['county'])) ?>"><label class="fcheck"><input type="checkbox" name="city" value="<?= v2_e($c['slug']) ?>"<?= $on ? ' checked' : '' ?>><span class="fbox" aria-hidden="true"><?= v2_ic('check') ?></span><span class="fname"><?= v2_e($c['name']) ?></span><?php if ($c['count'] > 0): ?><small><?= $c['count'] ?></small><?php endif; ?></label></li>
              <?php endforeach; ?>
            </ul>
            <p class="fnone" id="sr-city-none" hidden>Niciun oraș cu acest nume.</p>
            <?php if (count($cityOptions) > $cityShown): ?><button class="fmore" type="button" id="sr-city-more" aria-controls="sr-city-list" aria-expanded="false">Arată toate cele <?= v2_e(v2_num(count($cityOptions), 'oraș', 'orașe')) ?></button><?php endif; ?>
          </fieldset>
          <?php endif; ?>
          <?php if ($V2NAV['categories']): ?>
          <fieldset class="fgroup" data-group="category" data-single>
            <legend class="flabel">Categorie</legend>
            <ul class="fchecks">
              <?php foreach ($V2NAV['categories'] as $c): ?><li><label class="fcheck"><input type="checkbox" name="category" value="<?= v2_e($c['slug']) ?>"<?= $catF === $c['slug'] ? ' checked' : '' ?>><span class="fbox" aria-hidden="true"><?= v2_ic('check') ?></span><span class="fname"><?= v2_e($c['name']) ?></span></label></li><?php endforeach; ?>
            </ul>
          </fieldset>
          <?php endif; ?>
          <fieldset class="fgroup" data-group="max_price" data-single>
            <legend class="flabel">Preț maxim</legend>
            <ul class="fchecks">
              <?php foreach ($priceAllowed as $p): ?><li><label class="fcheck"><input type="checkbox" name="max_price" value="<?= $p ?>"<?= $maxPrice === $p ? ' checked' : '' ?>><span class="fbox" aria-hidden="true"><?= v2_ic('check') ?></span><span class="fname">Sub <?= $p ?> lei</span></label></li><?php endforeach; ?>
            </ul>
          </fieldset>
          <?php if ($travNames): ?>
          <fieldset class="fgroup" data-group="traveler_types">
            <legend class="flabel">Pentru cine</legend>
            <ul class="fchecks">
              <?php foreach ($travNames as $sl => $n): ?><li><label class="fcheck"><input type="checkbox" name="traveler_types[]" value="<?= v2_e($sl) ?>"<?= in_array($sl, $travF, true) ? ' checked' : '' ?>><span class="fbox" aria-hidden="true"><?= v2_ic('check') ?></span><span class="fname"><?= v2_e($n) ?></span></label></li><?php endforeach; ?>
            </ul>
          </fieldset>
          <?php endif; ?>
          <?php if ($intNames): ?>
          <fieldset class="fgroup" data-group="interests">
            <legend class="flabel">Interese</legend>
            <ul class="fchecks">
              <?php foreach ($intNames as $sl => $n): ?><li><label class="fcheck"><input type="checkbox" name="interests[]" value="<?= v2_e($sl) ?>"<?= in_array($sl, $intF, true) ? ' checked' : '' ?>><span class="fbox" aria-hidden="true"><?= v2_ic('check') ?></span><span class="fname"><?= v2_e($n) ?></span></label></li><?php endforeach; ?>
            </ul>
          </fieldset>
          <?php endif; ?>
          <div class="sr-apply">
            <button class="btn btn-primary" type="submit">Arată rezultatele</button>
            <?php if ($activeCount): ?><a class="aclear" href="<?= v2_e($clearAll) ?>">Șterge filtrele</a><?php endif; ?>
          </div>
        </form>
      </aside>

      <div class="sr-results">
        <h2 class="sr" id="sr-results-h">Rezultate</h2>
        <div class="sr-bar">
          <?php if ($active): ?>
          <ul class="sr-active" aria-label="Filtre active">
            <?php foreach ($active as [$label, $href]): ?><li><a class="achip" href="<?= v2_e($href) ?>"><?= v2_e($label) ?><?= v2_ic('x') ?><span class="sr"> (elimină)</span></a></li><?php endforeach; ?>
            <?php if ($activeCount > 1): ?><li><a class="aclear" href="<?= v2_e($clearAll) ?>">Șterge filtrele</a></li><?php endif; ?>
          </ul>
          <?php endif; ?>
          <nav class="sr-sort" aria-label="Ordonează rezultatele">
            <?php foreach ($sortOptions as $key => $label): ?><a href="<?= v2_e($qs(['sort' => $key === 'recommended' ? '' : $key])) ?>"<?= $sort === $key ? ' aria-current="true"' : '' ?>><?= $label ?></a><?php endforeach; ?>
          </nav>
        </div>

        <?php if ($cards): ?>
        <ul class="xp-grid" data-reveal>
          <?php foreach ($cards as $i => $a): ?>
          <li class="xp">
            <a href="<?= v2_e($a['href']) ?>">
              <span class="xp-media"><?= $a['image'] ? v2_photo([$a['image'], 0, 0, '']) : v2_fallback($a['title'], $i) ?></span>
              <span class="xp-body">
                <span class="xp-cat"><?= v2_e($a['catName']) ?></span>
                <span class="xp-title" title="<?= v2_e($a['title']) ?>"><?= v2_e($a['title']) ?></span>
                <span class="xp-meta"><?php if ($a['city']): ?><span><?= v2_ic('map-pin') ?><?= v2_e($a['city']) ?></span><?php endif; ?><?php if ($a['dur']): ?><span><?= v2_ic('clock') ?><?= v2_e($a['dur']) ?></span><?php endif; ?></span>
                <span class="xp-foot">
                  <?php if ($dateF): ?><span class="xp-avail"><?= v2_ic('check-circle') ?><span>Disponibil <?= v2_e(explode(',', $dayLabel($dateF))[0]) ?></span></span>
                  <?php else: ?><span class="xp-avail is-muted"><?= v2_ic('calendar-blank') ?><span>Vezi zilele disponibile</span></span><?php endif; ?>
                  <?php if ($a['price']): ?><span class="xp-price">de la<b><?= $a['price'] ?> lei</b></span><?php endif; ?>
                </span>
              </span>
            </a>
          </li>
          <?php endforeach; ?>
        </ul>

        <?php $last = max(1, (int) ($pagination['last_page'] ?? 1)); if ($last > 1): ?>
        <nav class="pager" aria-label="Pagini de rezultate">
          <?php if ($page > 1): ?><a href="<?= v2_e($qs(['page' => $page - 1 > 1 ? $page - 1 : ''])) ?>" aria-label="Pagina anterioară"><?= v2_ic('arrow-left') ?></a><?php endif; ?>
          <?php for ($p = 1; $p <= min($last, 12); $p++): ?>
            <?php if ($p === $page): ?><span aria-current="page"><?= $p ?></span><?php else: ?><a href="<?= v2_e($qs(['page' => $p === 1 ? '' : $p])) ?>"><?= $p ?></a><?php endif; ?>
          <?php endfor; ?>
          <?php if ($page < $last): ?><a href="<?= v2_e($qs(['page' => $page + 1])) ?>" aria-label="Pagina următoare"><?= v2_ic('arrow-right') ?></a><?php endif; ?>
        </nav>
        <?php endif; ?>

        <?php else: ?>
        <div class="sr-empty">
          <h2>Nu am găsit nimic pentru căutarea asta.</h2>
          <p><?= $dateF ? 'Încearcă altă zi, ' : 'Încearcă ' ?>alt oraș sau renunță la câteva filtre.</p>
          <div class="sr-empty-cta">
            <?php if ($activeCount): ?><a class="btn btn-light" href="<?= v2_e($clearAll) ?>">Șterge filtrele</a><?php endif; ?>
            <a class="btn btn-ghost" href="/cauta">Vezi toate experiențele</a>
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
