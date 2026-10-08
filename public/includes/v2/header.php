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
    ['sun', 'Weekend ideas', '/weekend-ideas'], ['users-three', 'With kids', '/with-kids'],
    ['cloud-rain', 'Indoors when it rains', '/rainy-days'], ['coins', 'Lowest prices first', '/search?sort=price'],
    ['heart', 'For couples', '/for-couples'], ['gift', 'Gift experiences', '/gift-experiences'],
];
// "Locații": the cities with published locations (v2/nav.php). With none, the header keeps the plain link.
$v2Loc = $V2NAV['locations'] ?? ['total' => 0, 'cities' => []];
// What you buy at a location, in the order the location page shows it.
$v2LocHow = [
    ['ticket', 'Entry ticket', 'Walk straight in, no queue at the ticket office.'],
    ['star', 'Experiences on site', 'Tours, workshops and rentals, added to the same ticket.'],
    ['gift', 'Packages', 'Entry and experiences for one price.'],
];

// "Planifică": the three travel tools, each with the long line the mega panel shows and the short one the phone shows.
$v2PlanTools = [
    ['compass', 'Trip planner', '/plan',
        'For a few days away: say where you are going and for how long, and the itinerary builds itself.',
        'A day-by-day itinerary, built for you'],
    ['map-trifold', 'Attractions map', '/map',
        'For seeing what is nearby: every attraction on one map, with filters.',
        'Every attraction, on a map'],
    ['path', 'Routes', '/routes',
        'For those who would rather not plan: ready-made itineraries with the stops already in order.',
        'Ready-made itineraries, day by day'],
];

// Under "Explorează": attractions are the places to see (points of interest); tickets are sold on /locatii.
// The planner, the map and the routes used to sit here too; they have their own "Planifică" entry now.
$v2AttrLinks = [
    ['castle-turret', 'Castles', '/attractions?type=castles'],
    ['buildings', 'Museums', '/attractions?type=museums'],
    ['heart', 'Cathedrals', '/attractions?type=cathedrals'],
    ['sun', 'National parks', '/attractions?type=national-parks'],
    ['map-pin', 'All attractions', '/attractions'],
];
$v2IntentCities = array_values(array_filter(array_map(function ($s) use ($V2NAV) {
    return $V2NAV['cities'][$s] ?? null;
}, $V2NAV['intentCities'] ?? ['brasov', 'sibiu', 'cluj-napoca', 'bucuresti', 'constanta', 'sinaia'])));
?>
<body<?= !empty($v2BodyClass) ? ' class="' . v2_e($v2BodyClass) . '"' : '' ?>>
<?php readfile(__DIR__ . '/sprite.svg'); ?>
<?php if (!empty($v2PlaceIcons) || in_array('map.js', $v2Scripts ?? [], true) || in_array('plan.js', $v2Scripts ?? [], true)) { require_once __DIR__ . '/product-icons.php'; echo am_product_icon_sprite(AM_PLACE_ICON_KEYS); } ?>

<a class="skip" href="#main">Skip to content</a>

<header class="hdr<?= $v2Overlay ? '' : ' is-solid' ?>" id="hdr">
  <div class="hdr-in">
    <a class="brand" href="/" aria-label="Viaqui, home">
      <?= v2_brand_mark() ?>
    </a>
    <nav class="mnav" aria-label="Main">
      <button class="mnav-btn" type="button" data-mega="explore" aria-expanded="false" aria-controls="mega-explore">Explore<?= v2_ic('caret-down') ?></button>
      <button class="mnav-btn" type="button" data-mega="plan" aria-expanded="false" aria-controls="mega-plan">Plan<?= v2_ic('caret-down') ?></button>
      <?php if ($v2Loc['cities']): ?>
      <button class="mnav-btn" type="button" data-mega="locations" aria-expanded="false" aria-controls="mega-locations">Venues<?= v2_ic('caret-down') ?></button>
      <?php else: ?>
      <a class="mnav-link" href="/venues">Venues</a>
      <?php endif; ?>
      <button class="mnav-btn" type="button" data-mega="activities" aria-expanded="false" aria-controls="mega-activities">Experiences<?= v2_ic('caret-down') ?></button>
      <button class="mnav-btn" type="button" data-mega="inspiration" aria-expanded="false" aria-controls="mega-inspiration">Inspiration<?= v2_ic('caret-down') ?></button>
    </nav>
    <form class="hdr-search" role="search" action="/search" method="get">
      <?= v2_ic('magnifying-glass') ?>
      <label class="sr" for="hdr-q">Search Viaqui</label>
      <input id="hdr-q" name="q" type="search" placeholder="Search attractions or cities" autocomplete="off">
      <button type="submit" aria-label="Search"><?= v2_ic('arrow-right') ?></button>
    </form>
    <div class="hdr-tools">
      <div class="lang-wrap">
        <button class="icon-btn lang" id="lang-btn" type="button" aria-expanded="false" aria-controls="lang-menu"><?= v2_ic('globe-simple') ?><span aria-hidden="true">EN<?= ($hdrCur = v2_display_currency()) !== null ? ' · ' . v2_e($hdrCur) : '' ?></span><span class="sr">Language and currency: English, <?= $hdrCur !== null ? v2_e(v2_currency_choices()[$hdrCur] ?? $hdrCur) : 'local currency' ?></span></button>
        <div class="lang-menu" id="lang-menu" hidden>
          <p class="lang-h">Site language</p>
          <p class="lang-opt" aria-current="true" lang="en"><?= v2_ic('check') ?>English</p>
          <p class="lang-h lang-h-cur">Currency</p>
          <ul class="cur-list">
            <li><a href="<?= v2_e(v2_currency_href('')) ?>" rel="nofollow"<?= $hdrCur === null ? ' aria-current="true"' : '' ?>><b>Local</b><span>Each country's own</span></a></li>
            <?php foreach (v2_currency_choices() as $curCode => $curName): ?>
            <li><a href="<?= v2_e(v2_currency_href($curCode)) ?>" rel="nofollow"<?= $hdrCur === $curCode ? ' aria-current="true"' : '' ?>><b><?= v2_e($curCode) ?></b><span><?= v2_e($curName) ?></span></a></li>
            <?php endforeach; ?>
          </ul>
        </div>
      </div>
      <a class="icon-btn" href="/cart" aria-label="Basket"><?= v2_ic('shopping-cart-simple') ?><span class="hdr-badge" data-cart-count hidden></span></a>
      <a class="icon-btn" href="/account" aria-label="My account" data-account><?= v2_ic('user-circle') ?><span class="acct-ini" hidden></span></a>
      <a class="icon-btn hdr-slink" href="/search" aria-label="Search Viaqui"><?= v2_ic('magnifying-glass') ?></a>
      <button class="icon-btn mm-sbtn" type="button" data-mm-search aria-controls="menu" aria-label="Search Viaqui"><?= v2_ic('magnifying-glass') ?></button>
      <button class="icon-btn menu-btn" id="menu-btn" type="button" aria-expanded="false" aria-controls="menu" aria-label="Open menu"><?= v2_ic('list') ?></button>
    </div>
  </div>

  <div class="mega mega-explore" id="mega-explore" data-lenis-prevent hidden>
    <div class="mega-in">
      <div>
        <p class="mega-label">Countries</p>
        <div class="mega-tabs" role="tablist" aria-orientation="vertical" aria-label="Countries" data-tabs data-hover>
          <?php foreach ($V2NAV['regions'] as $i => $r): ?>
          <button class="mega-tab" type="button" role="tab" id="mxt-<?= $i ?>" aria-controls="mx-<?= $i ?>" aria-selected="<?= $i === 0 ? 'true' : 'false' ?>" tabindex="<?= $i === 0 ? 0 : -1 ?>"><span><?= v2_e($r['name']) ?></span><small><?= v2_num($r['citiesCount'], 'city', 'cities') ?></small></button>
          <?php endforeach; ?>
        </div>
        <?php if (count($V2NAV['countriesAll'] ?? []) > count($V2NAV['regions'])): ?>
        <a class="mega-all" href="/cities">All <?= count($V2NAV['countriesAll']) ?> countries<?= v2_ic('arrow-right') ?></a>
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
            <li><a class="mcity" href="<?= v2_e($c['href']) ?>"><span class="mcity-media"><?= $c['photo'] ? v2_photo([$c['photo'][0], 0, 0, '']) : v2_fallback($c['name']) ?></span><span><b><?= v2_e($c['name']) ?></b><small><?= $c['count'] ? v2_exp($c['count']) : 'Discover the city' ?></small></span></a></li>
            <?php endforeach; ?>
          </ul>
          <?php endif; ?>
          <?php if ($links): ?>
          <p class="mega-label">More cities in <?= v2_e($r['name']) ?></p>
          <ul class="mega-links">
            <?php foreach ($links as $l): ?><li><a href="<?= v2_e($l['href']) ?>"><?= v2_e($l['name']) ?></a></li><?php endforeach; ?>
          </ul>
          <?php endif; ?>
          <a class="mega-all" href="/<?= v2_e($r['slug']) ?>">All <?= v2_num($r['citiesCount'], 'city', 'cities') ?> in <?= v2_e($r['name']) ?><?= v2_ic('arrow-right') ?></a>
        </div>
        <?php endforeach; ?>
      </div>
      <aside class="mega-aside" aria-label="Quick ideas">
        <p class="mega-label">Quick ideas</p>
        <ul class="ideas">
          <?php foreach ($v2Ideas as [$v2hIcon, $v2hText, $v2hHref]): ?>
          <li><a class="idea" href="<?= $v2hHref ?>"><?= v2_ic($v2hIcon) ?><?= v2_e($v2hText) ?></a></li>
          <?php endforeach; ?>
        </ul>
        <p class="mega-label">Attractions</p>
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
          <p class="mega-label">Tools for the road</p>
          <a class="mega-all" href="/attractions">All attractions<?= v2_ic('arrow-right') ?></a>
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
        <p class="mega-label">Recommended</p>
        <p class="mp-pitch-h" id="mp-aside-h">A city break sorted in two minutes.</p>
        <p class="mp-pitch">Choose the city and how many days you are staying. The planner spreads the attractions and experiences nearby across your days, in the order they link up on the road, with the distance between them. Then move whatever you like.</p>
        <a class="btn btn-primary mp-cta" href="/plan">Open the planner<?= v2_ic('arrow-right') ?></a>
      </aside>
    </div>
  </div>

  <?php if ($v2Loc['cities']): ?>
  <div class="mega mega-loc" id="mega-locations" data-lenis-prevent hidden>
    <div class="mega-in">
      <div>
        <p class="mega-label">Cities with venues</p>
        <div class="mega-tabs" role="tablist" aria-orientation="vertical" aria-label="Cities with venues" data-tabs data-hover>
          <?php foreach ($v2Loc['cities'] as $i => $c): ?>
          <button class="mega-tab" type="button" role="tab" id="mlt-<?= $i ?>" aria-controls="ml-<?= $i ?>" aria-selected="<?= $i === 0 ? 'true' : 'false' ?>" tabindex="<?= $i === 0 ? 0 : -1 ?>"><span><?= v2_e($c['name']) ?></span><small><?= v2_num($c['count'], 'venue', 'venues') ?></small></button>
          <?php endforeach; ?>
        </div>
        <a class="mega-all" href="/venues">All venues<?= v2_ic('arrow-right') ?></a>
      </div>
      <div>
        <?php foreach ($v2Loc['cities'] as $i => $c): ?>
        <div class="mega-panel" id="ml-<?= $i ?>" role="tabpanel" aria-labelledby="mlt-<?= $i ?>"<?= $i ? ' hidden' : '' ?>>
          <p class="mega-h">Venues in <?= v2_e($c['name']) ?></p>
          <ul class="mloc-list">
            <?php foreach (array_slice($c['items'], 0, 6) as $li => $l): ?>
            <li><a class="mloc" href="<?= v2_e($l['href']) ?>">
              <span class="mloc-media"><?= $l['photo'] ? v2_photo([$l['photo'], 0, 0, '']) : v2_fallback($l['name'], $li) ?></span>
              <span class="mloc-t">
                <?php if ($l['category']): ?><small class="mloc-k"><?= v2_e($l['category']) ?></small><?php endif; ?>
                <b><?= v2_e($l['name']) ?></b>
                <small class="mloc-m"><?= v2_e($l['offer'] ?: 'Entry tickets online') ?><?= $l['lodging'] ? ' · stays' : '' ?></small>
              </span>
              <?php if ($l['price']): ?><span class="mloc-p">from <b><?= v2_e($l['priceLabel'] ?? v2_money($l['price'])) ?></b></span><?php endif; ?>
            </a></li>
            <?php endforeach; ?>
          </ul>
          <p class="mega-label">Everything to do in <?= v2_e($c['name']) ?></p>
          <ul class="mega-links two">
            <li><a href="/<?= v2_e($c['slug']) ?>/experiences">Experiences in <?= v2_e($c['name']) ?></a></li>
            <li><a href="/<?= v2_e($c['slug']) ?>/attractions">Attractions in <?= v2_e($c['name']) ?></a></li>
            <li><a href="/<?= v2_e($c['slug']) ?>"><?= v2_e($c['name']) ?> city guide</a></li>
            <li><a href="/<?= v2_e($c['slug']) ?>/venues"><?= $c['count'] > 6 ? 'All ' . v2_num($c['count'], 'venue', 'venues') : 'All venues' ?> in <?= v2_e($c['name']) ?></a></li>
          </ul>
        </div>
        <?php endforeach; ?>
      </div>
      <aside class="mega-aside" aria-label="How a venue works">
        <p class="mega-label">What you book online</p>
        <ul class="mloc-how">
          <?php foreach ($v2LocHow as [$v2hIcon, $v2hTitle, $v2hText]): ?>
          <li><?= v2_ic($v2hIcon) ?><span><b><?= v2_e($v2hTitle) ?></b><small><?= v2_e($v2hText) ?></small></span></li>
          <?php endforeach; ?>
        </ul>
        <a class="mloc-op" href="/partners">
          <span class="mloc-op-k">Run a venue?</span>
          <span class="mloc-op-t">Sell entry tickets online and pay a commission only on what you sell.</span>
          <span class="mloc-op-cta">See how it works<?= v2_ic('arrow-right') ?></span>
        </a>
      </aside>
    </div>
  </div>
  <?php endif; ?>

  <div class="mega mega-act" id="mega-activities" data-lenis-prevent hidden>
    <div class="mega-in">
      <div class="ma-main">
        <div class="ma-top">
          <p class="mega-label">Every category at a glance</p>
          <a class="mega-all" href="/experiences">All experiences<?= v2_ic('arrow-right') ?></a>
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
              <li class="ma-more-li"><a class="ma-more" href="<?= v2_e($c['href']) ?>"><?= $maMore > 0 ? '+ ' . $maMore . ' more' : 'See all' ?><?= v2_ic('arrow-right') ?></a></li>
            </ul>
          </li>
          <?php endforeach; ?>
        </ul>
        <div class="ma-ideas">
          <p class="mega-label">Choose by occasion</p>
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
        <p class="mega-label">Guides</p>
        <ul class="guides">
          <?php foreach ($V2NAV['guides'] as $g): ?>
          <li><a class="guide" href="<?= v2_e($g['href']) ?>"><span class="guide-media"><?= $g['thumb'] ? v2_photo([$g['thumb'], 160, 160, '']) : v2_fallback($g['slug']) ?></span><span class="guide-text"><b><?= v2_e($g['title']) ?></b><small><?= v2_e(trim($g['category'] . ($g['readTime'] ? ', ' . $g['readTime'] . ' min read' : ''), ', ')) ?></small></span></a></li>
          <?php endforeach; ?>
        </ul>
        <a class="mega-all" href="/guides">All guides<?= v2_ic('arrow-right') ?></a>
      </div>
      <div class="intents">
        <div class="intent"><p class="mega-label">A weekend in</p><div class="chips-links"><?php foreach ($v2IntentCities as $c): ?><a href="/<?= v2_e($c['slug']) ?>/weekend-ideas"><?= v2_e($c['name']) ?></a><?php endforeach; ?></div></div>
        <div class="intent"><p class="mega-label">With kids in</p><div class="chips-links"><?php foreach ($v2IntentCities as $c): ?><a href="/<?= v2_e($c['slug']) ?>/with-kids"><?= v2_e($c['name']) ?></a><?php endforeach; ?></div></div>
      </div>
      <a class="mini-gift" href="/gift-card">
        <?= v2_brand('gc-brand') ?>
        <span class="mg-kind">Gift card</span>
        <span class="mg-text">You choose the amount, they choose the experience. Valid for 12 months at any venue.</span>
        <span class="mg-cta">See the gift card<?= v2_ic('arrow-right') ?></span>
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
  <div class="mm-sheet" role="dialog" aria-modal="true" aria-label="Menu" tabindex="-1" data-lenis-prevent>
    <svg class="mm-line" viewBox="0 590 3240 310" aria-hidden="true" focusable="false"><use href="#drum-g"/></svg>
    <div class="mm-searchbar" style="--i:0">
      <form class="mm-search" role="search" action="/search" method="get">
        <?= v2_ic('magnifying-glass') ?>
        <label class="sr" for="mm-q">Search Viaqui</label>
        <input id="mm-q" name="q" type="search" placeholder="Attraction, city or activity" autocomplete="off" enterkeyhint="search" role="combobox" aria-expanded="false" aria-controls="mm-suggest" aria-autocomplete="list">
        <button type="submit" aria-label="Search"><?= v2_ic('arrow-right') ?></button>
      </form>
      <div class="mm-suggest" id="mm-suggest" role="listbox" aria-label="Suggestions" hidden></div>
    </div>

    <div class="mm-stage">
      <section class="mm-panel is-current" id="mm-root" aria-label="Main menu">
        <ul class="mm-main">
          <li style="--i:1"><button class="mm-row" type="button" data-mm-go="mm-explore"><span class="mm-ic"><?= v2_ic('map-trifold') ?></span><span class="mm-t"><b>Explore</b><small><?= $v2MmCityTotal ? v2_num($v2MmCityTotal, 'city', 'cities') . ' in ' . count($V2NAV['countriesAll'] ?? $V2NAV['regions']) . ' countries' : 'Cities and countries' ?></small></span><?= v2_ic('arrow-right') ?></button></li>
          <li style="--i:2"><?php if ($v2Loc['cities']): ?><button class="mm-row" type="button" data-mm-go="mm-locations"><span class="mm-ic"><?= v2_ic('ticket') ?></span><span class="mm-t"><b>Venues</b><small>Entry tickets, online</small></span><?= v2_ic('arrow-right') ?></a></button><?php else: ?><a class="mm-row" href="/venues"><span class="mm-ic"><?= v2_ic('ticket') ?></span><span class="mm-t"><b>Venues</b><small>Entry tickets, online</small></span><?= v2_ic('arrow-right') ?></a></a><?php endif; ?></li>
          <li style="--i:3"><button class="mm-row" type="button" data-mm-go="mm-plan"><span class="mm-ic"><?= v2_ic('compass') ?></span><span class="mm-t"><b>Plan</b><small>Planner, map and routes</small></span><?= v2_ic('arrow-right') ?></button></li>
          <li style="--i:4"><button class="mm-row" type="button" data-mm-go="mm-activities"><span class="mm-ic"><?= v2_ic('squares-four') ?></span><span class="mm-t"><b>Experiences</b><small><?= v2_num(count($V2NAV['categories']), 'category', 'categories') ?></small></span><?= v2_ic('arrow-right') ?></button></li>
          <li style="--i:5"><button class="mm-row" type="button" data-mm-go="mm-inspiration"><span class="mm-ic"><?= v2_ic('sun') ?></span><span class="mm-t"><b>Inspiration</b><small>Guides and weekend ideas</small></span><?= v2_ic('arrow-right') ?></button></li>
          <li style="--i:6"><a class="mm-row" href="/gift-card"><span class="mm-ic is-gold"><?= v2_ic('gift') ?></span><span class="mm-t"><b>Gift card</b><small>Give an experience</small></span><?= v2_ic('arrow-right') ?></a></li>
          <li style="--i:7"><a class="mm-row" href="/operators"><span class="mm-ic"><?= v2_ic('buildings') ?></span><span class="mm-t"><b>Operators</b><small>The companies that sell on Viaqui</small></span><?= v2_ic('arrow-right') ?></a></li>
        </ul>

        <div class="mm-block" style="--i:8">
          <p class="mm-k">Quick ideas</p>
          <ul class="mm-chips">
            <?php foreach ($v2Ideas as [$v2hIcon, $v2hText, $v2hHref]): ?><li><a href="<?= $v2hHref ?>"><?= v2_ic($v2hIcon) ?><?= v2_e($v2hText) ?></a></li><?php endforeach; ?>
          </ul>
        </div>

        <?php if ($V2NAV['citiesList']): ?>
        <div class="mm-block" style="--i:9">
          <p class="mm-k">Popular cities</p>
          <ul class="mm-rail">
            <?php foreach (array_slice($V2NAV['citiesList'], 0, 10) as $ci => $c): ?>
            <li><a class="mm-city" href="<?= v2_e($c['href']) ?>"><span class="mm-city-media"><?= $c['photo'] ? v2_photo([$c['photo'][0], 0, 0, '']) : v2_fallback($c['name'], $ci) ?></span><b><?= v2_e($c['name']) ?></b></a></li>
            <?php endforeach; ?>
          </ul>
        </div>
        <?php endif; ?>

        <div class="mm-foot" style="--i:10">
          <a class="btn btn-primary" href="/account"><?= v2_ic('user-circle') ?>My account</a>
          <a class="btn mm-ghost" href="/cart"><?= v2_ic('shopping-cart-simple') ?>My basket</a>
          <a class="mm-small" href="/find-order"><?= v2_ic('ticket') ?>Find my order</a>
          <p>Support: <?= v2_e(SUPPORT_EMAIL) ?>, Monday to Friday, 09:00 - 18:00 (EET)</p>
        </div>
      </section>

      <section class="mm-panel" id="mm-explore" aria-labelledby="mm-explore-h" hidden>
        <div class="mm-phead"><button class="mm-back" type="button" data-mm-back><?= v2_ic('arrow-left') ?><span class="sr">Back to the menu</span></button><h2 class="mm-ph" id="mm-explore-h">Explore</h2><a class="mm-plink" href="/cities">All cities</a></div>
        <div class="mm-tabs" role="tablist" aria-label="Countries" data-tabs>
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
            <li><a class="mm-tile" href="<?= v2_e($c['href']) ?>"><?= $c['photo'] ? v2_photo([$c['photo'][0], 0, 0, '']) : v2_fallback($c['name'], $ti) ?><span><b><?= v2_e($c['name']) ?></b><small><?= $c['count'] ? v2_exp($c['count']) : 'Discover the city' ?></small></span></a></li>
            <?php endforeach; ?>
          </ul>
          <?php endif; ?>
          <?php if ($mmLinks): ?>
          <p class="mm-k">More cities in <?= v2_e($r['name']) ?></p>
          <ul class="mm-links"><?php foreach ($mmLinks as $l): ?><li><a href="<?= v2_e($l['href']) ?>"><?= v2_e($l['name']) ?></a></li><?php endforeach; ?></ul>
          <?php endif; ?>
          <a class="mm-all" href="/<?= v2_e($r['slug']) ?>">All <?= v2_num($r['citiesCount'], 'city', 'cities') ?> in <?= v2_e($r['name']) ?><?= v2_ic('arrow-right') ?></a>
        </div>
        <?php endforeach; ?>
        <p class="mm-k">Attractions</p>
        <ul class="mm-chips"><?php foreach ($v2AttrLinks as [$v2hIcon, $v2hText, $v2hHref]): ?><li><a href="<?= $v2hHref ?>"><?= v2_ic($v2hIcon) ?><?= v2_e($v2hText) ?></a></li><?php endforeach; ?></ul>
      </section>

      <?php if ($v2Loc['cities']): ?>
      <section class="mm-panel" id="mm-locations" aria-labelledby="mm-locations-h" hidden>
        <div class="mm-phead"><button class="mm-back" type="button" data-mm-back><?= v2_ic('arrow-left') ?><span class="sr">Back to the menu</span></button><h2 class="mm-ph" id="mm-locations-h">Venues</h2><a class="mm-plink" href="/venues">All venues</a></div>
        <div class="mm-tabs" role="tablist" aria-label="Cities with venues" data-tabs>
          <?php foreach ($v2Loc['cities'] as $i => $c): ?><button class="mm-tab" type="button" role="tab" id="mmlt-<?= $i ?>" aria-controls="mml-<?= $i ?>" aria-selected="<?= $i ? 'false' : 'true' ?>" tabindex="<?= $i ? -1 : 0 ?>"><?= v2_e($c['name']) ?><small><?= (int) $c['count'] ?></small></button><?php endforeach; ?>
        </div>
        <?php foreach ($v2Loc['cities'] as $i => $c): ?>
        <div class="mm-region" id="mml-<?= $i ?>" role="tabpanel" aria-labelledby="mmlt-<?= $i ?>"<?= $i ? ' hidden' : '' ?>>
          <ul class="mm-tiles">
            <?php foreach (array_slice($c['items'], 0, 4) as $li => $l): ?>
            <li><a class="mm-tile" href="<?= v2_e($l['href']) ?>"><?= $l['photo'] ? v2_photo([$l['photo'], 0, 0, '']) : v2_fallback($l['name'], $li) ?><span><b><?= v2_e($l['name']) ?></b><small><?= v2_e($l['offer'] ?: 'Tickets online') ?></small></span></a></li>
            <?php endforeach; ?>
          </ul>
          <a class="mm-all" href="/<?= v2_e($c['slug']) ?>/venues">Venues in <?= v2_e($c['name']) ?><?= v2_ic('arrow-right') ?></a>
        </div>
        <?php endforeach; ?>
        <p class="mm-k">What you book online</p>
        <ul class="mm-chips"><?php foreach ($v2LocHow as [$v2hIcon, $v2hTitle, $v2hText]): ?><li><a href="/venues"><?= v2_ic($v2hIcon) ?><?= v2_e($v2hTitle) ?></a></li><?php endforeach; ?></ul>
      </section>
      <?php endif; ?>

      <section class="mm-panel" id="mm-plan" aria-labelledby="mm-plan-h" hidden>
        <div class="mm-phead"><button class="mm-back" type="button" data-mm-back><?= v2_ic('arrow-left') ?><span class="sr">Back to the menu</span></button><h2 class="mm-ph" id="mm-plan-h">Plan</h2><a class="mm-plink" href="/attractions">Attractions</a></div>
        <ul class="mm-main">
          <?php foreach ($v2PlanTools as [$v2hIcon, $v2hTitle, $v2hHref, $v2hText, $v2hShort]): ?>
          <li><a class="mm-row" href="<?= $v2hHref ?>"><span class="mm-ic"><?= v2_ic($v2hIcon) ?></span><span class="mm-t"><b><?= v2_e($v2hTitle) ?></b><small><?= v2_e($v2hShort) ?></small></span><?= v2_ic('arrow-right') ?></a></li>
          <?php endforeach; ?>
        </ul>
        <p class="mm-k">How it helps</p>
        <p class="mm-note">The planner builds a day-by-day itinerary from the attractions, experiences and venues nearby, in the order they link up on the road. The map and the routes are shortcuts to the same places.</p>
      </section>

      <section class="mm-panel" id="mm-activities" aria-labelledby="mm-activities-h" hidden>
        <div class="mm-phead"><button class="mm-back" type="button" data-mm-back><?= v2_ic('arrow-left') ?><span class="sr">Back to the menu</span></button><h2 class="mm-ph" id="mm-activities-h">Experiences</h2><a class="mm-plink" href="/experiences">All</a></div>
        <ul class="mm-cats">
          <?php foreach ($V2NAV['categories'] as $i => $c): ?>
          <li class="mm-cat">
            <div class="mm-cat-row">
              <a class="mm-cat-link" href="<?= v2_e($c['href']) ?>"><span class="mm-cat-media"><?= $c['thumb'] ? v2_photo([$c['thumb'], 0, 0, '']) : v2_fallback($c['name'], $i) ?></span><span class="mm-t"><b><?= v2_e($c['name']) ?></b><small><?= $c['count'] ? v2_exp($c['count']) : 'See the category' ?></small></span></a>
              <?php if ($c['subs']): ?><button class="mm-cat-tg" type="button" aria-expanded="false" aria-controls="mmc-<?= v2_e($c['slug']) ?>"><?= v2_ic('caret-down') ?><span class="sr">Types of <?= v2_e($c['name']) ?></span></button><?php endif; ?>
            </div>
            <?php if ($c['subs']): ?>
            <div class="mm-sub" id="mmc-<?= v2_e($c['slug']) ?>"><div class="mm-sub-in">
              <ul class="mm-subs">
                <?php foreach ($c['subs'] as $s): ?><li><a href="<?= v2_e($s['href']) ?>"><?= v2_e($s['name']) ?></a></li><?php endforeach; ?>
                <li class="mm-subs-all"><a href="<?= v2_e($c['href']) ?>">The whole category<?= v2_ic('arrow-right') ?></a></li>
              </ul>
            </div></div>
            <?php endif; ?>
          </li>
          <?php endforeach; ?>
        </ul>
      </section>

      <section class="mm-panel" id="mm-inspiration" aria-labelledby="mm-inspiration-h" hidden>
        <div class="mm-phead"><button class="mm-back" type="button" data-mm-back><?= v2_ic('arrow-left') ?><span class="sr">Back to the menu</span></button><h2 class="mm-ph" id="mm-inspiration-h">Inspiration</h2><a class="mm-plink" href="/guides">All</a></div>
        <?php if ($V2NAV['guides']): ?>
        <ul class="mm-guides">
          <?php foreach ($V2NAV['guides'] as $g): ?>
          <li><a class="mm-guide" href="<?= v2_e($g['href']) ?>"><span class="mm-guide-media"><?= $g['thumb'] ? v2_photo([$g['thumb'], 160, 160, '']) : v2_fallback($g['slug']) ?></span><span><b><?= v2_e($g['title']) ?></b><small><?= v2_e(trim($g['category'] . ($g['readTime'] ? ', ' . $g['readTime'] . ' min read' : ''), ', ')) ?></small></span></a></li>
          <?php endforeach; ?>
        </ul>
        <?php endif; ?>
        <?php if ($v2IntentCities): ?>
        <p class="mm-k">A weekend in</p>
        <ul class="mm-chips"><?php foreach ($v2IntentCities as $c): ?><li><a href="/<?= v2_e($c['slug']) ?>/weekend-ideas"><?= v2_e($c['name']) ?></a></li><?php endforeach; ?></ul>
        <p class="mm-k">With kids in</p>
        <ul class="mm-chips"><?php foreach ($v2IntentCities as $c): ?><li><a href="/<?= v2_e($c['slug']) ?>/with-kids"><?= v2_e($c['name']) ?></a></li><?php endforeach; ?></ul>
        <?php endif; ?>
        <a class="mm-gift" href="/gift-card">
          <span class="mm-gift-k">Gift card</span>
          <b>You choose the amount, they choose the experience.</b>
          <span>Valid for 12 months at any venue.</span>
          <span class="mm-gift-cta">See the gift card<?= v2_ic('arrow-right') ?></span>
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
