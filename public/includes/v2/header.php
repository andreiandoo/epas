<?php
/**
 * viaqui.com v2 header: brand sprite, header with mega menu, mobile menu.
 *
 * Expects $V2NAV from v2/nav.php. Page variables:
 *   $v2HeaderOverlay  true only on pages with a dark hero (homepage): the header starts transparent
 *                     and turns solid once #hdr-sentinel scrolls under it. Otherwise it is solid.
 *   $v2BodyClass      extra classes for <body>
 */
$v2Overlay = !empty($v2HeaderOverlay);
$v2Ideas = [
    ['sun', v2_t('Weekend ideas'), '/weekend-ideas'], ['users-three', v2_t('With kids'), '/with-kids'],
    ['cloud-rain', v2_t('Indoors when it rains'), '/rainy-days'], ['coins', v2_t('Lowest prices first'), '/search?sort=price'],
    ['heart', v2_t('For couples'), '/for-couples'], ['gift', v2_t('Gift experiences'), '/gift-experiences'],
];
// "Locații": the cities with published locations (v2/nav.php). With none, the header keeps the plain link.
$v2Loc = $V2NAV['locations'] ?? ['total' => 0, 'cities' => []];
// What you buy at a location, in the order the location page shows it.
$v2LocHow = [
    ['ticket', v2_t('Entry ticket'), v2_t('Walk straight in, no queue at the ticket office.')],
    ['star', v2_t('Experiences on site'), v2_t('Tours, workshops and rentals, added to the same ticket.')],
    ['gift', v2_t('Packages'), v2_t('Entry and experiences for one price.')],
];

// "Planifică": the three travel tools, each with the long line the mega panel shows and the short one the phone shows.
$v2PlanTools = [
    ['compass', v2_t('Trip planner'), '/plan',
        v2_t('For a few days away: say where you are going and for how long, and the itinerary builds itself.'),
        v2_t('A day-by-day itinerary, built for you')],
    ['map-trifold', v2_t('Attractions map'), '/map',
        v2_t('For seeing what is nearby: every attraction on one map, with filters.'),
        v2_t('Every attraction, on a map')],
    ['path', v2_t('Routes'), '/routes',
        v2_t('For those who would rather not plan: ready-made itineraries with the stops already in order.'),
        v2_t('Ready-made itineraries, day by day')],
];

// Under "Explorează": attractions are the places to see (points of interest); tickets are sold on /locatii.
// The planner, the map and the routes used to sit here too; they have their own "Planifică" entry now.
$v2AttrLinks = [
    ['castle-turret', v2_t('Castles'), '/attractions?type=castles'],
    ['buildings', v2_t('Museums'), '/attractions?type=museums'],
    ['heart', v2_t('Cathedrals'), '/attractions?type=cathedrals'],
    ['sun', v2_t('National parks'), '/attractions?type=national-parks'],
    ['map-pin', v2_t('All attractions'), '/attractions'],
];
$v2IntentCities = array_values(array_filter(array_map(function ($s) use ($V2NAV) {
    return $V2NAV['cities'][$s] ?? null;
}, $V2NAV['intentCities'] ?? ['brasov', 'sibiu', 'cluj-napoca', 'bucuresti', 'constanta', 'sinaia'])));
// The language menu: one row per open language ([code, name, address of this page in it, current?]).
$v2Langs = v2_language_links();
$v2LangNow = v2_locales()['names'][v2_locale()] ?? strtoupper(v2_locale());
// The default language has no prefix, so the link rewriter (v2_i18n_links) would put the current one on a plain
// "/rome": that one link is written as a full address, which the rewriter leaves alone.
$v2LangHref = function (array $row): string {
    return ($row[0] === v2_locales()['default'] ? SITE_URL : '') . $row[2];
};
?>
<body<?= !empty($v2BodyClass) ? ' class="' . v2_e($v2BodyClass) . '"' : '' ?>>
<?php readfile(__DIR__ . '/sprite.svg'); ?>
<?php if (!empty($v2PlaceIcons) || in_array('map.js', $v2Scripts ?? [], true) || in_array('plan.js', $v2Scripts ?? [], true)) { require_once __DIR__ . '/product-icons.php'; echo am_product_icon_sprite(AM_PLACE_ICON_KEYS); } ?>

<a class="skip" href="#main"><?= v2_te('Skip to content') ?></a>

<header class="hdr<?= $v2Overlay ? '' : ' is-solid' ?>" id="hdr">
  <div class="hdr-in">
    <a class="brand" href="/" aria-label="<?= v2_te('Viaqui, home') ?>">
      <?= v2_brand_mark() ?>
    </a>
    <nav class="mnav" aria-label="<?= v2_te('Main') ?>">
      <button class="mnav-btn" type="button" data-mega="explore" aria-expanded="false" aria-controls="mega-explore"><?= v2_te('Explore') ?><?= v2_ic('caret-down') ?></button>
      <button class="mnav-btn" type="button" data-mega="plan" aria-expanded="false" aria-controls="mega-plan"><?= v2_te('Plan') ?><?= v2_ic('caret-down') ?></button>
      <?php if ($v2Loc['cities']): ?>
      <button class="mnav-btn" type="button" data-mega="locations" aria-expanded="false" aria-controls="mega-locations"><?= v2_te('Venues') ?><?= v2_ic('caret-down') ?></button>
      <?php else: ?>
      <a class="mnav-link" href="/venues"><?= v2_te('Venues') ?></a>
      <?php endif; ?>
      <button class="mnav-btn" type="button" data-mega="activities" aria-expanded="false" aria-controls="mega-activities"><?= v2_te('Experiences') ?><?= v2_ic('caret-down') ?></button>
      <button class="mnav-btn" type="button" data-mega="inspiration" aria-expanded="false" aria-controls="mega-inspiration"><?= v2_te('Inspiration') ?><?= v2_ic('caret-down') ?></button>
    </nav>
    <form class="hdr-search" role="search" action="/search" method="get">
      <?= v2_ic('magnifying-glass') ?>
      <label class="sr" for="hdr-q"><?= v2_te('Search Viaqui') ?></label>
      <input id="hdr-q" name="q" type="search" placeholder="<?= v2_te('Search attractions or cities') ?>" autocomplete="off">
      <button type="submit" aria-label="<?= v2_te('Search') ?>"><?= v2_ic('arrow-right') ?></button>
    </form>
    <div class="hdr-tools">
      <div class="lang-wrap">
        <button class="icon-btn lang" id="lang-btn" type="button" aria-expanded="false" aria-controls="lang-menu"><?= v2_ic('globe-simple') ?><span aria-hidden="true"><?= v2_e(strtoupper(v2_locale())) ?><?= ($hdrCur = v2_display_currency()) !== null ? ' · ' . v2_e($hdrCur) : '' ?></span><span class="sr"><?= $hdrCur !== null ? v2_te('Language and currency: {language}, {currency}', ['language' => $v2LangNow, 'currency' => v2_currency_choices()[$hdrCur] ?? $hdrCur]) : v2_te('Language and currency: {language}, local currency', ['language' => $v2LangNow]) ?></span></button>
        <div class="lang-menu" id="lang-menu" hidden>
          <p class="lang-h"><?= v2_te('Site language') ?></p>
          <?php if (count($v2Langs) < 2): ?>
          <p class="lang-opt" aria-current="true" lang="<?= v2_e(v2_locale()) ?>"><?= v2_ic('check') ?><?= v2_e($v2LangNow) ?></p>
          <?php else: ?>
          <ul class="lang-list">
            <?php foreach ($v2Langs as $v2Lang): ?>
            <li><a class="lang-opt" href="<?= v2_e($v2LangHref($v2Lang)) ?>" hreflang="<?= v2_e($v2Lang[0]) ?>" lang="<?= v2_e($v2Lang[0]) ?>"<?= $v2Lang[3] ? ' aria-current="true"' : '' ?>><?= $v2Lang[3] ? v2_ic('check') : '' ?><?= v2_e($v2Lang[1]) ?></a></li>
            <?php endforeach; ?>
          </ul>
          <?php endif; ?>
          <p class="lang-h lang-h-cur"><?= v2_te('Currency') ?></p>
          <ul class="cur-list">
            <li><a href="<?= v2_e(v2_currency_href('')) ?>" rel="nofollow"<?= $hdrCur === null ? ' aria-current="true"' : '' ?>><b><?= v2_te('Local') ?></b><span><?= v2_te('Each country\'s own') ?></span></a></li>
            <?php foreach (v2_currency_choices() as $curCode => $curName): ?>
            <li><a href="<?= v2_e(v2_currency_href($curCode)) ?>" rel="nofollow"<?= $hdrCur === $curCode ? ' aria-current="true"' : '' ?>><b><?= v2_e($curCode) ?></b><span><?= v2_e($curName) ?></span></a></li>
            <?php endforeach; ?>
          </ul>
        </div>
      </div>
      <a class="icon-btn" href="/cart" aria-label="<?= v2_te('Basket') ?>"><?= v2_ic('shopping-cart-simple') ?><span class="hdr-badge" data-cart-count hidden></span></a>
      <a class="icon-btn" href="/account" aria-label="<?= v2_te('My account') ?>" data-account><?= v2_ic('user-circle') ?><span class="acct-ini" hidden></span></a>
      <a class="icon-btn hdr-slink" href="/search" aria-label="<?= v2_te('Search Viaqui') ?>"><?= v2_ic('magnifying-glass') ?></a>
      <button class="icon-btn mm-sbtn" type="button" data-mm-search aria-controls="menu" aria-label="<?= v2_te('Search Viaqui') ?>"><?= v2_ic('magnifying-glass') ?></button>
      <button class="icon-btn menu-btn" id="menu-btn" type="button" aria-expanded="false" aria-controls="menu" aria-label="<?= v2_te('Open menu') ?>"><?= v2_ic('list') ?></button>
    </div>
  </div>

  <div class="mega mega-explore" id="mega-explore" data-lenis-prevent hidden>
    <div class="mega-in">
      <div>
        <p class="mega-label"><?= v2_te('Countries') ?></p>
        <div class="mega-tabs" role="tablist" aria-orientation="vertical" aria-label="<?= v2_te('Countries') ?>" data-tabs data-hover>
          <?php foreach ($V2NAV['regions'] as $i => $r): ?>
          <button class="mega-tab" type="button" role="tab" id="mxt-<?= $i ?>" aria-controls="mx-<?= $i ?>" aria-selected="<?= $i === 0 ? 'true' : 'false' ?>" tabindex="<?= $i === 0 ? 0 : -1 ?>"><span><?= v2_e($r['name']) ?></span><small><?= v2_e(v2_num($r['citiesCount'], 'city', 'cities')) ?></small></button>
          <?php endforeach; ?>
        </div>
        <?php if (count($V2NAV['countriesAll'] ?? []) > count($V2NAV['regions'])): ?>
        <a class="mega-all" href="/cities"><?= v2_te('All {n} countries', ['n' => count($V2NAV['countriesAll'])]) ?><?= v2_ic('arrow-right') ?></a>
        <?php endif; ?>
      </div>
      <div>
        <?php foreach ($V2NAV['regions'] as $i => $r):
            $tiles = array_slice($r['featured'], 0, 4);
            $links = array_slice(array_merge(array_map(function ($c) {
                return ['name' => $c['name'], 'href' => $c['href']];
            }, array_slice($r['featured'], 4)), $r['more']), 0, 15);
        ?>
        <div class="mega-panel" id="mx-<?= $i ?>" role="tabpanel" aria-labelledby="mxt-<?= $i ?>"<?= $i ? ' hidden' : '' ?>>
          <p class="mega-h"><?= v2_e($r['name']) ?></p>
          <?php if ($tiles): ?>
          <ul class="mega-cities">
            <?php foreach ($tiles as $c): ?>
            <li><a class="mcity" href="<?= v2_e($c['href']) ?>"><span class="mcity-media"><?= $c['photo'] ? v2_photo([$c['photo'][0], 0, 0, '']) : v2_fallback($c['name']) ?></span><span><b><?= v2_e($c['name']) ?></b><small><?= $c['count'] ? v2_e(v2_exp($c['count'])) : v2_te('Discover the city') ?></small></span></a></li>
            <?php endforeach; ?>
          </ul>
          <?php endif; ?>
          <?php if ($links): ?>
          <p class="mega-label"><?= v2_te('More cities in {country}', ['country' => $r['name']]) ?></p>
          <ul class="mega-links">
            <?php foreach ($links as $l): ?><li><a href="<?= v2_e($l['href']) ?>"><?= v2_e($l['name']) ?></a></li><?php endforeach; ?>
          </ul>
          <?php endif; ?>
          <a class="mega-all" href="/<?= v2_e($r['slug']) ?>"><?= v2_te('All {cities} in {country}', ['cities' => v2_num($r['citiesCount'], 'city', 'cities'), 'country' => $r['name']]) ?><?= v2_ic('arrow-right') ?></a>
        </div>
        <?php endforeach; ?>
      </div>
      <aside class="mega-aside" aria-label="<?= v2_te('Quick ideas') ?>">
        <p class="mega-label"><?= v2_te('Quick ideas') ?></p>
        <ul class="ideas">
          <?php foreach ($v2Ideas as [$v2hIcon, $v2hText, $v2hHref]): ?>
          <li><a class="idea" href="<?= $v2hHref ?>"><?= v2_ic($v2hIcon) ?><?= v2_e($v2hText) ?></a></li>
          <?php endforeach; ?>
        </ul>
        <p class="mega-label"><?= v2_te('Attractions') ?></p>
        <ul class="ideas">
          <?php foreach ($v2AttrLinks as [$v2hIcon, $v2hText, $v2hHref]): ?>
          <li><a class="idea" href="<?= $v2hHref ?>"><?= v2_ic($v2hIcon) ?><?= v2_e($v2hText) ?></a></li>
          <?php endforeach; ?>
        </ul>
      </aside>
    </div>
  </div>

  <div class="mega mega-plan" id="mega-plan" data-lenis-prevent hidden>
    <div class="mega-in">
      <div>
        <div class="ma-top">
          <p class="mega-label"><?= v2_te('Tools for the road') ?></p>
          <a class="mega-all" href="/attractions"><?= v2_te('All attractions') ?><?= v2_ic('arrow-right') ?></a>
        </div>
        <ul class="mp-tools">
          <?php foreach ($v2PlanTools as [$v2hIcon, $v2hTitle, $v2hHref, $v2hText]): ?>
          <li><a class="mp-tool" href="<?= $v2hHref ?>">
            <span class="mp-ic"><?= v2_ic($v2hIcon) ?></span>
            <span class="mp-t"><b><?= v2_e($v2hTitle) ?></b><small><?= v2_e($v2hText) ?></small></span>
            <?= v2_ic('arrow-right') ?>
          </a></li>
          <?php endforeach; ?>
        </ul>
      </div>
      <aside class="mega-aside mp-aside" aria-labelledby="mp-aside-h">
        <p class="mega-label"><?= v2_te('Recommended') ?></p>
        <p class="mp-pitch-h" id="mp-aside-h"><?= v2_te('A city break sorted in two minutes.') ?></p>
        <p class="mp-pitch"><?= v2_te('Choose the city and how many days you are staying. The planner spreads the attractions and experiences nearby across your days, in the order they link up on the road, with the distance between them. Then move whatever you like.') ?></p>
        <a class="btn btn-primary mp-cta" href="/plan"><?= v2_te('Open the planner') ?><?= v2_ic('arrow-right') ?></a>
      </aside>
    </div>
  </div>

  <?php if ($v2Loc['cities']): ?>
  <div class="mega mega-loc" id="mega-locations" data-lenis-prevent hidden>
    <div class="mega-in">
      <div>
        <p class="mega-label"><?= v2_te('Cities with venues') ?></p>
        <div class="mega-tabs" role="tablist" aria-orientation="vertical" aria-label="<?= v2_te('Cities with venues') ?>" data-tabs data-hover>
          <?php foreach ($v2Loc['cities'] as $i => $c): ?>
          <button class="mega-tab" type="button" role="tab" id="mlt-<?= $i ?>" aria-controls="ml-<?= $i ?>" aria-selected="<?= $i === 0 ? 'true' : 'false' ?>" tabindex="<?= $i === 0 ? 0 : -1 ?>"><span><?= v2_e($c['name']) ?></span><small><?= v2_e(v2_num($c['count'], 'venue', 'venues')) ?></small></button>
          <?php endforeach; ?>
        </div>
        <a class="mega-all" href="/venues"><?= v2_te('All venues') ?><?= v2_ic('arrow-right') ?></a>
      </div>
      <div>
        <?php foreach ($v2Loc['cities'] as $i => $c): ?>
        <div class="mega-panel" id="ml-<?= $i ?>" role="tabpanel" aria-labelledby="mlt-<?= $i ?>"<?= $i ? ' hidden' : '' ?>>
          <p class="mega-h"><?= v2_te('Venues in {city}', ['city' => $c['name']]) ?></p>
          <ul class="mloc-list">
            <?php foreach (array_slice($c['items'], 0, 6) as $li => $l): ?>
            <li><a class="mloc" href="<?= v2_e($l['href']) ?>">
              <span class="mloc-media"><?= $l['photo'] ? v2_photo([$l['photo'], 0, 0, '']) : v2_fallback($l['name'], $li) ?></span>
              <span class="mloc-t">
                <?php if ($l['category']): ?><small class="mloc-k"><?= v2_e($l['category']) ?></small><?php endif; ?>
                <b><?= v2_e($l['name']) ?></b>
                <small class="mloc-m"><?= v2_e($l['offer'] ?: v2_t('Entry tickets online')) ?><?= $l['lodging'] ? ' · ' . v2_te('stays') : '' ?></small>
              </span>
              <?php if ($l['price']): ?><span class="mloc-p"><?= v2_t('from <b>{price}</b>', ['price' => v2_e($l['priceLabel'] ?? v2_money($l['price']))]) ?></span><?php endif; ?>
            </a></li>
            <?php endforeach; ?>
          </ul>
          <p class="mega-label"><?= v2_te('Everything to do in {city}', ['city' => $c['name']]) ?></p>
          <ul class="mega-links two">
            <li><a href="/<?= v2_e($c['slug']) ?>/experiences"><?= v2_te('Experiences in {city}', ['city' => $c['name']]) ?></a></li>
            <li><a href="/<?= v2_e($c['slug']) ?>/attractions"><?= v2_te('Attractions in {city}', ['city' => $c['name']]) ?></a></li>
            <li><a href="/<?= v2_e($c['slug']) ?>"><?= v2_te('{city} city guide', ['city' => $c['name']]) ?></a></li>
            <li><a href="/<?= v2_e($c['slug']) ?>/venues"><?= $c['count'] > 6 ? v2_te('All {venues} in {city}', ['venues' => v2_num($c['count'], 'venue', 'venues'), 'city' => $c['name']]) : v2_te('All venues in {city}', ['city' => $c['name']]) ?></a></li>
          </ul>
        </div>
        <?php endforeach; ?>
      </div>
      <aside class="mega-aside" aria-label="<?= v2_te('How a venue works') ?>">
        <p class="mega-label"><?= v2_te('What you book online') ?></p>
        <ul class="mloc-how">
          <?php foreach ($v2LocHow as [$v2hIcon, $v2hTitle, $v2hText]): ?>
          <li><?= v2_ic($v2hIcon) ?><span><b><?= v2_e($v2hTitle) ?></b><small><?= v2_e($v2hText) ?></small></span></li>
          <?php endforeach; ?>
        </ul>
        <a class="mloc-op" href="/partners">
          <span class="mloc-op-k"><?= v2_te('Run a venue?') ?></span>
          <span class="mloc-op-t"><?= v2_te('Sell entry tickets online and pay a commission only on what you sell.') ?></span>
          <span class="mloc-op-cta"><?= v2_te('See how it works') ?><?= v2_ic('arrow-right') ?></span>
        </a>
      </aside>
    </div>
  </div>
  <?php endif; ?>

  <div class="mega mega-act" id="mega-activities" data-lenis-prevent hidden>
    <div class="mega-in">
      <div class="ma-main">
        <div class="ma-top">
          <p class="mega-label"><?= v2_te('Every category at a glance') ?></p>
          <a class="mega-all" href="/experiences"><?= v2_te('All experiences') ?><?= v2_ic('arrow-right') ?></a>
        </div>
        <ul class="ma-dir">
          <?php foreach ($V2NAV['categories'] as $c):
              $maSubs = array_slice($c['subs'], 0, 4);
              $maMore = count($c['subs']) - count($maSubs);
          ?>
          <li class="ma-cat">
            <a class="ma-head" href="<?= v2_e($c['href']) ?>"><span class="thumb"><?= $c['thumb'] ? v2_photo([$c['thumb'], 0, 0, '']) : '' ?></span><span class="ma-t"><b><?= v2_e($c['name']) ?></b><?php if ($c['desc']): ?><small><?= v2_e($c['desc']) ?></small><?php endif; ?></span></a>
            <ul class="ma-subs">
              <?php foreach ($maSubs as $s): ?><li><a href="<?= v2_e($s['href']) ?>"><?= v2_e($s['name']) ?></a></li><?php endforeach; ?>
              <li class="ma-more-li"><a class="ma-more" href="<?= v2_e($c['href']) ?>"><?= $maMore > 0 ? v2_te('+ {n} more', ['n' => $maMore]) : v2_te('See all') ?><?= v2_ic('arrow-right') ?></a></li>
            </ul>
          </li>
          <?php endforeach; ?>
        </ul>
        <div class="ma-ideas">
          <p class="mega-label"><?= v2_te('Choose by occasion') ?></p>
          <ul class="ideas ideas-row">
            <?php foreach ($v2Ideas as [$v2hIcon, $v2hText, $v2hHref]): ?>
            <li><a class="idea" href="<?= $v2hHref ?>"><?= v2_ic($v2hIcon) ?><?= v2_e($v2hText) ?></a></li>
            <?php endforeach; ?>
          </ul>
        </div>
      </div>
    </div>
  </div>

  <div class="mega mega-insp" id="mega-inspiration" data-lenis-prevent hidden>
    <div class="mega-in">
      <div>
        <p class="mega-label"><?= v2_te('Guides') ?></p>
        <ul class="guides">
          <?php foreach ($V2NAV['guides'] as $g): ?>
          <li><a class="guide" href="<?= v2_e($g['href']) ?>"><span class="guide-media"><?= $g['thumb'] ? v2_photo([$g['thumb'], 160, 160, '']) : v2_fallback($g['slug']) ?></span><span class="guide-text"><b><?= v2_e($g['title']) ?></b><small><?= v2_e(trim($g['category'] . ($g['readTime'] ? ', ' . v2_t('{n} min read', ['n' => $g['readTime']]) : ''), ', ')) ?></small></span></a></li>
          <?php endforeach; ?>
        </ul>
        <a class="mega-all" href="/guides"><?= v2_te('All guides') ?><?= v2_ic('arrow-right') ?></a>
      </div>
      <div class="intents">
        <div class="intent"><p class="mega-label"><?= v2_te('A weekend in') ?></p><div class="chips-links"><?php foreach ($v2IntentCities as $c): ?><a href="/<?= v2_e($c['slug']) ?>/weekend-ideas"><?= v2_e($c['name']) ?></a><?php endforeach; ?></div></div>
        <div class="intent"><p class="mega-label"><?= v2_te('With kids in') ?></p><div class="chips-links"><?php foreach ($v2IntentCities as $c): ?><a href="/<?= v2_e($c['slug']) ?>/with-kids"><?= v2_e($c['name']) ?></a><?php endforeach; ?></div></div>
      </div>
      <a class="mini-gift" href="/gift-card">
        <?= v2_brand('gc-brand') ?>
        <span class="mg-kind"><?= v2_te('Gift card') ?></span>
        <span class="mg-text"><?= v2_te('You choose the amount, they choose the experience. Valid for 12 months at any venue.') ?></span>
        <span class="mg-cta"><?= v2_te('See the gift card') ?><?= v2_ic('arrow-right') ?></span>
        <svg class="mg-line" viewBox="900 585 2340 310" aria-hidden="true"><use href="#drum-g"/></svg>
      </a>
    </div>
  </div>
</header>

<?php
// Mobile menu data: every city for the search suggestions (featured first), categories and guides.
$v2MmCities = [];
foreach ($V2NAV['citiesList'] as $c) {
    $v2MmCities[$c['slug']] = [$c['name'], $c['region'], $c['href']];
}
foreach ($V2NAV['allCities'] ?? [] as $c) {
    if (!isset($v2MmCities[$c['slug']]) && $c['name'] !== '') {
        $v2MmCities[$c['slug']] = [$c['name'], $c['region'], '/' . $c['slug']];
    }
}
$v2MmCityTotal = ($V2NAV['citiesTotal'] ?? 0) ?: array_sum(array_column($V2NAV['regions'], 'citiesCount'));
?>
<div class="mm" id="menu" hidden>
  <div class="mm-scrim" data-mm-close></div>
  <div class="mm-sheet" role="dialog" aria-modal="true" aria-label="<?= v2_te('Menu') ?>" tabindex="-1" data-lenis-prevent>
    <svg class="mm-line" viewBox="0 590 3240 310" aria-hidden="true" focusable="false"><use href="#drum-g"/></svg>
    <div class="mm-searchbar" style="--i:0">
      <form class="mm-search" role="search" action="/search" method="get">
        <?= v2_ic('magnifying-glass') ?>
        <label class="sr" for="mm-q"><?= v2_te('Search Viaqui') ?></label>
        <input id="mm-q" name="q" type="search" placeholder="<?= v2_te('Attraction, city or activity') ?>" autocomplete="off" enterkeyhint="search" role="combobox" aria-expanded="false" aria-controls="mm-suggest" aria-autocomplete="list">
        <button type="submit" aria-label="<?= v2_te('Search') ?>"><?= v2_ic('arrow-right') ?></button>
      </form>
      <div class="mm-suggest" id="mm-suggest" role="listbox" aria-label="<?= v2_te('Suggestions') ?>" hidden></div>
    </div>

    <div class="mm-stage">
      <section class="mm-panel is-current" id="mm-root" aria-label="<?= v2_te('Main menu') ?>">
        <ul class="mm-main">
          <li style="--i:1"><button class="mm-row" type="button" data-mm-go="mm-explore"><span class="mm-ic"><?= v2_ic('map-trifold') ?></span><span class="mm-t"><b><?= v2_te('Explore') ?></b><small><?= $v2MmCityTotal ? v2_te('{cities} in {countries}', ['cities' => v2_num($v2MmCityTotal, 'city', 'cities'), 'countries' => v2_num(count($V2NAV['countriesAll'] ?? $V2NAV['regions']), 'country', 'countries')]) : v2_te('Cities and countries') ?></small></span><?= v2_ic('arrow-right') ?></button></li>
          <li style="--i:2"><?php if ($v2Loc['cities']): ?><button class="mm-row" type="button" data-mm-go="mm-locations"><span class="mm-ic"><?= v2_ic('ticket') ?></span><span class="mm-t"><b><?= v2_te('Venues') ?></b><small><?= v2_te('Entry tickets, online') ?></small></span><?= v2_ic('arrow-right') ?></a></button><?php else: ?><a class="mm-row" href="/venues"><span class="mm-ic"><?= v2_ic('ticket') ?></span><span class="mm-t"><b><?= v2_te('Venues') ?></b><small><?= v2_te('Entry tickets, online') ?></small></span><?= v2_ic('arrow-right') ?></a></a><?php endif; ?></li>
          <li style="--i:3"><button class="mm-row" type="button" data-mm-go="mm-plan"><span class="mm-ic"><?= v2_ic('compass') ?></span><span class="mm-t"><b><?= v2_te('Plan') ?></b><small><?= v2_te('Planner, map and routes') ?></small></span><?= v2_ic('arrow-right') ?></button></li>
          <li style="--i:4"><button class="mm-row" type="button" data-mm-go="mm-activities"><span class="mm-ic"><?= v2_ic('squares-four') ?></span><span class="mm-t"><b><?= v2_te('Experiences') ?></b><small><?= v2_e(v2_num(count($V2NAV['categories']), 'category', 'categories')) ?></small></span><?= v2_ic('arrow-right') ?></button></li>
          <li style="--i:5"><button class="mm-row" type="button" data-mm-go="mm-inspiration"><span class="mm-ic"><?= v2_ic('sun') ?></span><span class="mm-t"><b><?= v2_te('Inspiration') ?></b><small><?= v2_te('Guides and weekend ideas') ?></small></span><?= v2_ic('arrow-right') ?></button></li>
          <li style="--i:6"><a class="mm-row" href="/gift-card"><span class="mm-ic is-gold"><?= v2_ic('gift') ?></span><span class="mm-t"><b><?= v2_te('Gift card') ?></b><small><?= v2_te('Give an experience') ?></small></span><?= v2_ic('arrow-right') ?></a></li>
          <li style="--i:7"><a class="mm-row" href="/operators"><span class="mm-ic"><?= v2_ic('buildings') ?></span><span class="mm-t"><b><?= v2_te('Operators') ?></b><small><?= v2_te('The companies that sell on Viaqui') ?></small></span><?= v2_ic('arrow-right') ?></a></li>
        </ul>

        <div class="mm-block" style="--i:8">
          <p class="mm-k"><?= v2_te('Quick ideas') ?></p>
          <ul class="mm-chips">
            <?php foreach ($v2Ideas as [$v2hIcon, $v2hText, $v2hHref]): ?><li><a href="<?= $v2hHref ?>"><?= v2_ic($v2hIcon) ?><?= v2_e($v2hText) ?></a></li><?php endforeach; ?>
          </ul>
        </div>

        <?php if ($V2NAV['citiesList']): ?>
        <div class="mm-block" style="--i:9">
          <p class="mm-k"><?= v2_te('Popular cities') ?></p>
          <ul class="mm-rail">
            <?php foreach (array_slice($V2NAV['citiesList'], 0, 10) as $ci => $c): ?>
            <li><a class="mm-city" href="<?= v2_e($c['href']) ?>"><span class="mm-city-media"><?= $c['photo'] ? v2_photo([$c['photo'][0], 0, 0, '']) : v2_fallback($c['name'], $ci) ?></span><b><?= v2_e($c['name']) ?></b></a></li>
            <?php endforeach; ?>
          </ul>
        </div>
        <?php endif; ?>

        <?php if (count($v2Langs) > 1): ?>
        <div class="mm-block" style="--i:10">
          <p class="mm-k"><?= v2_te('Site language') ?></p>
          <ul class="mm-chips">
            <?php foreach ($v2Langs as $v2Lang): ?><li><a href="<?= v2_e($v2LangHref($v2Lang)) ?>" hreflang="<?= v2_e($v2Lang[0]) ?>" lang="<?= v2_e($v2Lang[0]) ?>"<?= $v2Lang[3] ? ' aria-current="true"' : '' ?>><?= $v2Lang[3] ? v2_ic('check') : '' ?><?= v2_e($v2Lang[1]) ?></a></li><?php endforeach; ?>
          </ul>
        </div>
        <?php endif; ?>

        <div class="mm-foot" style="--i:10">
          <a class="btn btn-primary" href="/account"><?= v2_ic('user-circle') ?><?= v2_te('My account') ?></a>
          <a class="btn mm-ghost" href="/cart"><?= v2_ic('shopping-cart-simple') ?><?= v2_te('My basket') ?></a>
          <a class="mm-small" href="/find-order"><?= v2_ic('ticket') ?><?= v2_te('Find my order') ?></a>
          <p><?= v2_te('Support: {email}, Monday to Friday, 09:00 - 18:00 (EET)', ['email' => SUPPORT_EMAIL]) ?></p>
        </div>
      </section>

      <section class="mm-panel" id="mm-explore" aria-labelledby="mm-explore-h" hidden>
        <div class="mm-phead"><button class="mm-back" type="button" data-mm-back><?= v2_ic('arrow-left') ?><span class="sr"><?= v2_te('Back to the menu') ?></span></button><h2 class="mm-ph" id="mm-explore-h"><?= v2_te('Explore') ?></h2><a class="mm-plink" href="/cities"><?= v2_te('All cities') ?></a></div>
        <div class="mm-tabs" role="tablist" aria-label="<?= v2_te('Countries') ?>" data-tabs>
          <?php foreach ($V2NAV['regions'] as $i => $r): ?><button class="mm-tab" type="button" role="tab" id="mmt-<?= $i ?>" aria-controls="mmr-<?= $i ?>" aria-selected="<?= $i ? 'false' : 'true' ?>" tabindex="<?= $i ? -1 : 0 ?>"><?= v2_e($r['name']) ?><small><?= (int) $r['citiesCount'] ?></small></button><?php endforeach; ?>
        </div>
        <?php foreach ($V2NAV['regions'] as $i => $r):
            $mmTiles = array_slice($r['featured'], 0, 4);
            $mmLinks = array_slice(array_merge(array_map(function ($c) {
                return ['name' => $c['name'], 'href' => $c['href']];
            }, array_slice($r['featured'], 4)), $r['more']), 0, 12);
        ?>
        <div class="mm-region" id="mmr-<?= $i ?>" role="tabpanel" aria-labelledby="mmt-<?= $i ?>"<?= $i ? ' hidden' : '' ?>>
          <?php if ($mmTiles): ?>
          <ul class="mm-tiles">
            <?php foreach ($mmTiles as $ti => $c): ?>
            <li><a class="mm-tile" href="<?= v2_e($c['href']) ?>"><?= $c['photo'] ? v2_photo([$c['photo'][0], 0, 0, '']) : v2_fallback($c['name'], $ti) ?><span><b><?= v2_e($c['name']) ?></b><small><?= $c['count'] ? v2_e(v2_exp($c['count'])) : v2_te('Discover the city') ?></small></span></a></li>
            <?php endforeach; ?>
          </ul>
          <?php endif; ?>
          <?php if ($mmLinks): ?>
          <p class="mm-k"><?= v2_te('More cities in {country}', ['country' => $r['name']]) ?></p>
          <ul class="mm-links"><?php foreach ($mmLinks as $l): ?><li><a href="<?= v2_e($l['href']) ?>"><?= v2_e($l['name']) ?></a></li><?php endforeach; ?></ul>
          <?php endif; ?>
          <a class="mm-all" href="/<?= v2_e($r['slug']) ?>"><?= v2_te('All {cities} in {country}', ['cities' => v2_num($r['citiesCount'], 'city', 'cities'), 'country' => $r['name']]) ?><?= v2_ic('arrow-right') ?></a>
        </div>
        <?php endforeach; ?>
        <p class="mm-k"><?= v2_te('Attractions') ?></p>
        <ul class="mm-chips"><?php foreach ($v2AttrLinks as [$v2hIcon, $v2hText, $v2hHref]): ?><li><a href="<?= $v2hHref ?>"><?= v2_ic($v2hIcon) ?><?= v2_e($v2hText) ?></a></li><?php endforeach; ?></ul>
      </section>

      <?php if ($v2Loc['cities']): ?>
      <section class="mm-panel" id="mm-locations" aria-labelledby="mm-locations-h" hidden>
        <div class="mm-phead"><button class="mm-back" type="button" data-mm-back><?= v2_ic('arrow-left') ?><span class="sr"><?= v2_te('Back to the menu') ?></span></button><h2 class="mm-ph" id="mm-locations-h"><?= v2_te('Venues') ?></h2><a class="mm-plink" href="/venues"><?= v2_te('All venues') ?></a></div>
        <div class="mm-tabs" role="tablist" aria-label="<?= v2_te('Cities with venues') ?>" data-tabs>
          <?php foreach ($v2Loc['cities'] as $i => $c): ?><button class="mm-tab" type="button" role="tab" id="mmlt-<?= $i ?>" aria-controls="mml-<?= $i ?>" aria-selected="<?= $i ? 'false' : 'true' ?>" tabindex="<?= $i ? -1 : 0 ?>"><?= v2_e($c['name']) ?><small><?= (int) $c['count'] ?></small></button><?php endforeach; ?>
        </div>
        <?php foreach ($v2Loc['cities'] as $i => $c): ?>
        <div class="mm-region" id="mml-<?= $i ?>" role="tabpanel" aria-labelledby="mmlt-<?= $i ?>"<?= $i ? ' hidden' : '' ?>>
          <ul class="mm-tiles">
            <?php foreach (array_slice($c['items'], 0, 4) as $li => $l): ?>
            <li><a class="mm-tile" href="<?= v2_e($l['href']) ?>"><?= $l['photo'] ? v2_photo([$l['photo'], 0, 0, '']) : v2_fallback($l['name'], $li) ?><span><b><?= v2_e($l['name']) ?></b><small><?= v2_e($l['offer'] ?: v2_t('Tickets online')) ?></small></span></a></li>
            <?php endforeach; ?>
          </ul>
          <a class="mm-all" href="/<?= v2_e($c['slug']) ?>/venues"><?= v2_te('Venues in {city}', ['city' => $c['name']]) ?><?= v2_ic('arrow-right') ?></a>
        </div>
        <?php endforeach; ?>
        <p class="mm-k"><?= v2_te('What you book online') ?></p>
        <ul class="mm-chips"><?php foreach ($v2LocHow as [$v2hIcon, $v2hTitle, $v2hText]): ?><li><a href="/venues"><?= v2_ic($v2hIcon) ?><?= v2_e($v2hTitle) ?></a></li><?php endforeach; ?></ul>
      </section>
      <?php endif; ?>

      <section class="mm-panel" id="mm-plan" aria-labelledby="mm-plan-h" hidden>
        <div class="mm-phead"><button class="mm-back" type="button" data-mm-back><?= v2_ic('arrow-left') ?><span class="sr"><?= v2_te('Back to the menu') ?></span></button><h2 class="mm-ph" id="mm-plan-h"><?= v2_te('Plan') ?></h2><a class="mm-plink" href="/attractions"><?= v2_te('Attractions') ?></a></div>
        <ul class="mm-main">
          <?php foreach ($v2PlanTools as [$v2hIcon, $v2hTitle, $v2hHref, $v2hText, $v2hShort]): ?>
          <li><a class="mm-row" href="<?= $v2hHref ?>"><span class="mm-ic"><?= v2_ic($v2hIcon) ?></span><span class="mm-t"><b><?= v2_e($v2hTitle) ?></b><small><?= v2_e($v2hShort) ?></small></span><?= v2_ic('arrow-right') ?></a></li>
          <?php endforeach; ?>
        </ul>
        <p class="mm-k"><?= v2_te('How it helps') ?></p>
        <p class="mm-note"><?= v2_te('The planner builds a day-by-day itinerary from the attractions, experiences and venues nearby, in the order they link up on the road. The map and the routes are shortcuts to the same places.') ?></p>
      </section>

      <section class="mm-panel" id="mm-activities" aria-labelledby="mm-activities-h" hidden>
        <div class="mm-phead"><button class="mm-back" type="button" data-mm-back><?= v2_ic('arrow-left') ?><span class="sr"><?= v2_te('Back to the menu') ?></span></button><h2 class="mm-ph" id="mm-activities-h"><?= v2_te('Experiences') ?></h2><a class="mm-plink" href="/experiences"><?= v2_te('All') ?></a></div>
        <ul class="mm-cats">
          <?php foreach ($V2NAV['categories'] as $i => $c): ?>
          <li class="mm-cat">
            <div class="mm-cat-row">
              <a class="mm-cat-link" href="<?= v2_e($c['href']) ?>"><span class="mm-cat-media"><?= $c['thumb'] ? v2_photo([$c['thumb'], 0, 0, '']) : v2_fallback($c['name'], $i) ?></span><span class="mm-t"><b><?= v2_e($c['name']) ?></b><small><?= $c['count'] ? v2_e(v2_exp($c['count'])) : v2_te('See the category') ?></small></span></a>
              <?php if ($c['subs']): ?><button class="mm-cat-tg" type="button" aria-expanded="false" aria-controls="mmc-<?= v2_e($c['slug']) ?>"><?= v2_ic('caret-down') ?><span class="sr"><?= v2_te('Types of {category}', ['category' => $c['name']]) ?></span></button><?php endif; ?>
            </div>
            <?php if ($c['subs']): ?>
            <div class="mm-sub" id="mmc-<?= v2_e($c['slug']) ?>"><div class="mm-sub-in">
              <ul class="mm-subs">
                <?php foreach ($c['subs'] as $s): ?><li><a href="<?= v2_e($s['href']) ?>"><?= v2_e($s['name']) ?></a></li><?php endforeach; ?>
                <li class="mm-subs-all"><a href="<?= v2_e($c['href']) ?>"><?= v2_te('The whole category') ?><?= v2_ic('arrow-right') ?></a></li>
              </ul>
            </div></div>
            <?php endif; ?>
          </li>
          <?php endforeach; ?>
        </ul>
      </section>

      <section class="mm-panel" id="mm-inspiration" aria-labelledby="mm-inspiration-h" hidden>
        <div class="mm-phead"><button class="mm-back" type="button" data-mm-back><?= v2_ic('arrow-left') ?><span class="sr"><?= v2_te('Back to the menu') ?></span></button><h2 class="mm-ph" id="mm-inspiration-h"><?= v2_te('Inspiration') ?></h2><a class="mm-plink" href="/guides"><?= v2_te('All') ?></a></div>
        <?php if ($V2NAV['guides']): ?>
        <ul class="mm-guides">
          <?php foreach ($V2NAV['guides'] as $g): ?>
          <li><a class="mm-guide" href="<?= v2_e($g['href']) ?>"><span class="mm-guide-media"><?= $g['thumb'] ? v2_photo([$g['thumb'], 160, 160, '']) : v2_fallback($g['slug']) ?></span><span><b><?= v2_e($g['title']) ?></b><small><?= v2_e(trim($g['category'] . ($g['readTime'] ? ', ' . v2_t('{n} min read', ['n' => $g['readTime']]) : ''), ', ')) ?></small></span></a></li>
          <?php endforeach; ?>
        </ul>
        <?php endif; ?>
        <?php if ($v2IntentCities): ?>
        <p class="mm-k"><?= v2_te('A weekend in') ?></p>
        <ul class="mm-chips"><?php foreach ($v2IntentCities as $c): ?><li><a href="/<?= v2_e($c['slug']) ?>/weekend-ideas"><?= v2_e($c['name']) ?></a></li><?php endforeach; ?></ul>
        <p class="mm-k"><?= v2_te('With kids in') ?></p>
        <ul class="mm-chips"><?php foreach ($v2IntentCities as $c): ?><li><a href="/<?= v2_e($c['slug']) ?>/with-kids"><?= v2_e($c['name']) ?></a></li><?php endforeach; ?></ul>
        <?php endif; ?>
        <a class="mm-gift" href="/gift-card">
          <span class="mm-gift-k"><?= v2_te('Gift card') ?></span>
          <b><?= v2_te('You choose the amount, they choose the experience.') ?></b>
          <span><?= v2_te('Valid for 12 months at any venue.') ?></span>
          <span class="mm-gift-cta"><?= v2_te('See the gift card') ?><?= v2_ic('arrow-right') ?></span>
          <svg class="mm-gift-line" viewBox="900 585 2340 310" aria-hidden="true" focusable="false"><use href="#drum-g"/></svg>
        </a>
      </section>
    </div>
  </div>
</div>
<script type="application/json" id="mm-data"><?= json_encode([
    'c' => array_values($v2MmCities),
    'k' => array_map(function ($c) { return [$c['name'], $c['href']]; }, $V2NAV['categories']),
    'g' => array_map(function ($g) { return [$g['title'], $g['href']]; }, $V2NAV['guides']),
], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP) ?></script>
