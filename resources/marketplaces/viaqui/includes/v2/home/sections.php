<?php
/**
 * Viaqui v2 homepage: hero and every section of <main>.
 *
 * Data: $V2 from v2/home/data.php. Styles: assets/v2/css/home.css (every class starts with "v-", so nothing here
 * collides with the shared shell). Script: assets/v2/js/home.js; the page is complete without it.
 */
$hvHero = array_keys(V2_HERO_PLACES);
$hvFirst = $hvHero[0];
$hvBadges = [
    'inst' => ['lightning', v2_t('Instant confirmation'), ' is-inst'], 'mob' => ['qr-code', v2_t('Mobile ticket'), ''],
    'free' => ['check-circle', v2_t('Free cancellation'), ''], 'fam' => ['users-three', v2_t('Family friendly'), ''],
];
$hvGlyph = function (string $name, string $cls = 'v-glyph'): string {
    return '<svg class="' . $cls . '" viewBox="0 0 48 48" aria-hidden="true"><use href="#vg-' . $name . '"/></svg>';
};
$hvSaturday = (new DateTimeImmutable('saturday this week', new DateTimeZone('Europe/Bucharest')))->format('Y-m-d');
?>
<svg width="0" height="0" style="position:absolute" aria-hidden="true" focusable="false">
  <defs>
    <linearGradient id="vg-line" x1="0" x2="1" y1="0" y2="0"><stop offset="0" stop-color="#38A169"/><stop offset=".55" stop-color="#C6A15B"/><stop offset="1" stop-color="#D9722E"/></linearGradient>
    <symbol id="vg-arch" viewBox="0 0 48 48"><path d="M8 42V22a16 16 0 0 1 32 0v20M17 42V24a7 7 0 0 1 14 0v18M4 42h40"/></symbol>
    <symbol id="vg-castle" viewBox="0 0 48 48"><path d="M8 42V14h5v5h5v-5h5v5h5v-5h5v5h5v-5h2v28M4 42h40M20 42v-9a4 4 0 0 1 8 0v9"/></symbol>
    <symbol id="vg-tree" viewBox="0 0 48 48"><path d="M24 5 14 19h5l-8 11h6l-7 9h28l-7-9h6l-8-11h5zM24 39v5"/></symbol>
    <symbol id="vg-column" viewBox="0 0 48 48"><path d="M10 12h28M12 12c0 4 3 4 3 7h18c0-3 3-3 3-7M17 19v19M24 19v19M31 19v19M10 42h28M12 38h24"/></symbol>
    <symbol id="vg-route" viewBox="0 0 48 48"><path d="M10 38c0-10 28-6 28-18S14 18 14 11"/><circle cx="14" cy="8" r="3"/><circle cx="10" cy="41" r="3"/></symbol>
    <symbol id="vg-mount" viewBox="0 0 48 48"><path d="M4 40 18 16l8 12 5-7 13 19zM18 16l4 9"/></symbol>
    <symbol id="vg-wheel" viewBox="0 0 48 48"><circle cx="24" cy="20" r="14"/><path d="M24 6v28M10 20h28M14 10l20 20M34 10 14 30M24 34l-8 9M24 34l8 9M12 43h24"/></symbol>
    <symbol id="vg-paw" viewBox="0 0 48 48"><path d="M24 41c-7 0-11-3-11-7 0-5 6-9 11-9s11 4 11 9c0 4-4 7-11 7z"/><ellipse cx="12" cy="21" rx="3" ry="4"/><ellipse cx="20" cy="13" rx="3" ry="4"/><ellipse cx="28" cy="13" rx="3" ry="4"/><ellipse cx="36" cy="21" rx="3" ry="4"/></symbol>
    <symbol id="vg-kite" viewBox="0 0 48 48"><path d="m24 5 12 15-12 19-12-19zM12 20h24M24 5v34M24 39c0 4 5 2 5 6"/></symbol>
    <symbol id="vg-key" viewBox="0 0 48 48"><circle cx="16" cy="32" r="8"/><path d="M22 26 40 8M34 14l5 5M29 19l4 4"/></symbol>
    <symbol id="vg-brush" viewBox="0 0 48 48"><path d="m30 6 12 12-18 18H12V24zM12 36l-6 6M26 10l12 12"/></symbol>
    <symbol id="vg-star" viewBox="0 0 48 48"><path d="m24 6 5.500 11.500 12.500 1.700-9.200 8.800 2.300 12.500L24 34.500l-11.100 6 2.300-12.500L6 19.200l12.500-1.700z"/></symbol>
    <symbol id="vg-group" viewBox="0 0 48 48"><circle cx="24" cy="15" r="5"/><circle cx="10" cy="21" r="4"/><circle cx="38" cy="21" r="4"/><path d="M15 40v-5a9 9 0 0 1 18 0v5M3 38v-3a7 7 0 0 1 9-6.700M45 38v-3a7 7 0 0 0-9-6.700"/></symbol>
    <symbol id="vg-ticket" viewBox="0 0 48 48"><path d="M6 16a3 3 0 0 1 3-3h30a3 3 0 0 1 3 3v4a4 4 0 0 0 0 8v4a3 3 0 0 1-3 3H9a3 3 0 0 1-3-3v-4a4 4 0 0 0 0-8zM29 13v22"/></symbol>
  </defs>
</svg>

<main id="main" class="v-home">
<!-- ============================ HERO ============================ -->
<section class="v-hero" id="hero" aria-labelledby="hero-h">
  <div class="v-hero-topo" aria-hidden="true"></div>
  <canvas class="v-hero-gl" id="hero-gl" aria-hidden="true"></canvas>
  <div class="wrap v-hero-grid">
    <div class="v-hero-copy">
      <p class="v-eyebrow"><?= v2_t('Travel closer.<br>Experience more.') ?></p>
      <h1 class="v-hero-h" id="hero-h"><?= v2_t('<span><i>Experiences</i></span> <span><i><em>begin</em> before</i></span> <span><i>you arrive.</i></span>') ?></h1>
      <p class="v-hero-tag"><?= v2_te('Your way in.') ?></p>
      <p class="v-hero-sub"><?= v2_te('Tickets for castles, museums, tours and days out. Pick a date, pay securely and walk in with the QR code on your phone.') ?></p>

      <form class="v-search" id="hero-search" role="search" action="/search" method="get" aria-label="<?= v2_te('Find experiences') ?>">
        <label class="v-search-f v-search-what" for="hs-q"><span><?= v2_te('What are you looking for?') ?></span><input id="hs-q" name="q" type="search" placeholder="<?= v2_te('City, attraction or experience') ?>" autocomplete="off" role="combobox" aria-expanded="false" aria-controls="hs-suggest" aria-autocomplete="list"></label>
        <label class="v-search-f" for="hs-when"><span><?= v2_te('When') ?></span><select id="hs-when" name="date">
          <option value=""><?= v2_te('Anytime') ?></option>
          <option value="<?= v2_e($V2['days'][0]['iso']) ?>"><?= v2_te('Today') ?></option>
          <option value="<?= v2_e($V2['days'][1]['iso']) ?>"><?= v2_te('Tomorrow') ?></option>
          <option value="<?= v2_e($hvSaturday) ?>"><?= v2_te('This weekend') ?></option>
        </select></label>
        <label class="v-search-f" for="hs-who"><span><?= v2_te('Who') ?></span><select id="hs-who" name="who">
          <option value=""><?= v2_te('Anyone') ?></option>
          <option value="families"><?= v2_te('Family with kids') ?></option>
          <option value="friends"><?= v2_te('Friends') ?></option>
          <option value="couples"><?= v2_te('Two of us') ?></option>
        </select></label>
        <button class="btn btn-primary v-search-go" type="submit"><?= v2_ic('magnifying-glass') ?><span><?= v2_te('Search') ?></span></button>
        <div class="v-suggest" id="hs-suggest" role="listbox" aria-label="<?= v2_te('Suggestions') ?>" hidden></div>
      </form>
      <p class="v-popular"><span><?= v2_te('Popular:') ?></span>
        <?php foreach (array_slice($V2['citiesList'], 0, 5) as $c): ?><a href="<?= v2_e($c['href']) ?>"><?= v2_e($c['name']) ?></a><?php endforeach; ?>
      </p>
    </div>

    <div class="v-hero-art">
      <svg class="v-hero-lines" viewBox="0 0 520 582" aria-hidden="true">
        <path id="hero-l1" pathLength="1" stroke="#38A169" d="M14 582V250C14 110 120 6 262 6s248 104 248 244v332"/>
        <path id="hero-l2" pathLength="1" stroke="#C6A15B" d="M-22 582V330c0-70 44-118 104-118"/>
      </svg>
      <div class="v-hero-arch" id="hero-arch">
        <?php foreach ($hvHero as $i => $key): [$hvName] = V2_HERO_PLACES[$key]; ?>
        <?php /* only the first photo loads with the page; home.js gives the others their address after the load event */ ?>
        <img<?= $i === 0 ? ' class="is-on" fetchpriority="high"' : '' ?> decoding="async" width="1440" height="1029" alt="<?= v2_e($hvName) ?>"
             <?= $i === 0 ? '' : 'data-' ?>src="<?= v2_asset('img/hero-' . $key . '-900.webp') ?>" <?= $i === 0 ? '' : 'data-' ?>srcset="<?= v2_e(v2_home_hero_srcset($key)) ?>" sizes="(min-width:1024px) 40vw, 90vw">
        <?php endforeach; ?>
      </div>
      <p class="v-hero-place" aria-live="polite"><small><?= v2_te('In the picture') ?></small><strong id="hero-place-n"><?= v2_e(V2_HERO_PLACES[$hvFirst][0]) ?></strong><span id="hero-place-m"><?= v2_e(V2_HERO_PLACES[$hvFirst][1]) ?></span></p>
      <div class="v-hero-dots" id="hero-dots" role="group" aria-label="<?= v2_te('Hero photo') ?>">
        <?php foreach ($hvHero as $i => $key): ?>
        <button type="button" aria-pressed="<?= $i === 0 ? 'true' : 'false' ?>" aria-label="<?= v2_e(V2_HERO_PLACES[$key][0]) ?>" data-name="<?= v2_e(V2_HERO_PLACES[$key][0]) ?>" data-meta="<?= v2_e(V2_HERO_PLACES[$key][1]) ?>"></button>
        <?php endforeach; ?>
      </div>
    </div>
  </div>
</section>
<div id="hdr-sentinel" aria-hidden="true"></div>

<!-- ============================ QUICK STRIP ============================ -->
<section class="v-quick" aria-label="<?= v2_te('Browse by type') ?>">
  <div class="wrap"><ul data-vreveal>
    <li><a href="/attractions"><span class="v-ring"><?= $hvGlyph('arch') ?></span><span><strong><?= v2_te('Attractions') ?></strong><small><?= v2_te('Iconic places and landmarks') ?></small></span></a></li>
    <li><a href="/venues"><span class="v-ring"><?= $hvGlyph('ticket') ?></span><span><strong><?= v2_te('Venues') ?></strong><small><?= v2_te('Entry tickets, booked online') ?></small></span></a></li>
    <li><a href="/routes"><span class="v-ring"><?= $hvGlyph('route') ?></span><span><strong><?= v2_te('Routes') ?></strong><small><?= v2_te('Ready-made itineraries') ?></small></span></a></li>
    <li><a href="/experiences"><span class="v-ring"><?= $hvGlyph('star') ?></span><span><strong><?= v2_te('Experiences') ?></strong><small><?= v2_te('Tours, workshops, days out') ?></small></span></a></li>
  </ul></div>
</section>

<!-- ============================ CATEGORIES ============================ -->
<section class="v-sec" id="categories" aria-labelledby="cats-h">
  <div class="wrap">
    <div class="v-head"><div><p class="v-eyebrow"><?= v2_te('Choose your kind of day') ?></p><h2 class="v-slash" id="cats-h"><?= v2_te('what moves you') ?></h2></div><p class="v-lede"><?= count($V2['categories']) === 12 ? v2_te('Twelve ways in. Each one opens onto places you can book, with opening days, prices and the next free time slots.') : v2_te('{n} ways in. Each one opens onto places you can book, with opening days, prices and the next free time slots.', ['n' => count($V2['categories'])]) ?></p></div>
    <ul class="v-cats" data-vreveal>
      <?php foreach ($V2['categories'] as $i => $c): [$look, $glyph] = V2_CAT_LOOK[$i % count(V2_CAT_LOOK)]; if ($look === 'p' && !$c['image']) { $look = 'f'; } ?>
      <li><a class="v-cat is-<?= $look ?>" href="<?= v2_e($c['href']) ?>">
        <?php if ($look === 'p'): ?><img src="<?= v2_e($c['image']) ?>"<?= $c['srcset'] ? ' srcset="' . v2_e($c['srcset']) . '" sizes="(min-width:1024px) 25vw, 50vw"' : '' ?> alt="" loading="lazy" decoding="async" width="640" height="800"><?php else: ?><?= $hvGlyph($glyph) ?><?php endif; ?>
        <?php if ($c['count']): ?><span class="v-cat-n"><?= v2_e(v2_exp($c['count'])) ?></span><?php endif; ?>
        <h3><?= v2_e($c['name']) ?></h3>
        <?php if ($c['desc']): ?><p><?= v2_e($c['desc']) ?></p><?php endif; ?>
        <span class="v-go"><?= v2_ic('arrow-right') ?></span>
      </a></li>
      <?php endforeach; ?>
    </ul>
  </div>
</section>

<!-- ============================ THIS WEEK ============================ -->
<section class="v-sec v-week" id="this-week" aria-labelledby="week-h">
  <div class="wrap">
    <div class="v-head"><div><p class="v-eyebrow"><?= v2_te('Time-based') ?></p><h2 class="v-slash" id="week-h"><?= v2_te('this week') ?></h2></div><p class="v-lede"><?= v2_te('Pick a day, then narrow it down. Every listing shows what the ticket includes before you pay.') ?></p></div>
    <div class="v-days" id="week-days" role="group" aria-label="<?= v2_te('Choose a day') ?>">
      <?php foreach ($V2['days'] as $i => $d): ?>
      <a class="v-day" href="/search?date=<?= v2_e($d['iso']) ?>" data-day="<?= $i ?>"<?= $i === 0 ? ' aria-current="date"' : '' ?>><small><?= v2_e($d['label']) ?></small><b><?= $d['day'] ?></b><span><?= v2_e($d['month']) ?></span></a>
      <?php endforeach; ?>
    </div>
    <div class="v-week-meta">
      <div class="v-chips" id="week-chips" role="group" aria-label="<?= v2_te('Filter by type') ?>">
        <button class="v-chip" type="button" aria-pressed="true" data-f="all"><?= v2_te('Popular') ?></button>
        <?php foreach ($V2['filters'] as $k => $label): ?><button class="v-chip" type="button" aria-pressed="false" data-f="<?= v2_e($k) ?>"><?= v2_e($label) ?></button><?php endforeach; ?>
      </div>
      <?php if ($V2['sample']): ?><p class="v-note"><?= v2_te('Sample listings, shown until the first venues go live.') ?></p><?php endif; ?>
    </div>
    <ul class="v-cards" id="week-cards">
      <?php foreach ($V2['cards'] as $i => $card): ?>
      <li data-k="<?= v2_e($card['k']) ?>"><a class="v-card" href="<?= v2_e($card['href']) ?>">
        <span class="v-card-ph"><?= $card['image'] ? '<img src="' . v2_e($card['image']) . '" alt="" loading="lazy" decoding="async" width="640" height="560">' : v2_fallback($card['title'], $i) ?><span class="v-fav"><?= v2_ic('heart') ?></span></span>
        <span class="v-card-b">
          <small><?= v2_e($card['place']) ?></small>
          <strong><?= v2_e($card['title']) ?></strong>
          <span class="v-badges"><?php foreach ($card['badges'] as $b): [$bIcon, $bText, $bCls] = $hvBadges[$b]; ?><span class="v-badge<?= $bCls ?>"><?= v2_ic($bIcon) ?><?= v2_e($bText) ?></span><?php endforeach; ?></span>
          <span class="v-card-f">
            <?php if ($card['rating']): ?><span class="v-rate"><?= v2_ic('star') ?><?= v2_e(number_format($card['rating'][0], 1)) ?> (<?= (int) $card['rating'][1] ?>)</span><?php else: ?><span></span><?php endif; ?>
            <?php if ($card['price']): ?><span><?= v2_t('from <b>{price}</b>', ['price' => v2_e($card['price'])]) ?></span><?php endif; ?>
          </span>
        </span>
      </a></li>
      <?php endforeach; ?>
    </ul>
    <p class="v-more"><a class="v-link" href="/experiences"><?= v2_te('All experiences') ?><?= v2_ic('arrow-right') ?></a></p>
  </div>
</section>

<!-- ============================ JOURNEY ============================ -->
<section class="v-journey-sec" id="journey" aria-labelledby="journey-h">
  <div class="v-journey" id="journey-scene">
    <div class="wrap">
      <div class="v-journey-head"><div><p class="v-eyebrow"><?= v2_te('A path forward') ?></p><h2 id="journey-h"><?= v2_t('One road, six ways <em>in</em>.') ?></h2></div><p><?= v2_te('Follow the line from the capital to the sea. Every stop is a city with places you can book before you get there.') ?></p></div>
      <svg class="v-route" viewBox="0 0 1240 120" aria-hidden="true">
        <?php $hvRoute = 'M0 96c40 0 46-8 70-8 16 0 14-40 26-40s10 40 26 40c20 0 24 6 44 6 14 0 12-30 22-30s8 30 22 30h60l10-22h-8l12-18h-8l10-16 10 16h-8l12 18h-8l10 22h70c26 0 30-10 56-10h34V52h-8V38h10v8h10v-8h10v8h10v-8h10v14h-8v36c30 0 34 12 70 12 40 0 44-20 80-20 30 0 40 14 60 14 12 0 22-50 62-50s50 50 62 50h62V44h-10V32h70v12h-10v52h34c40 0 50-14 90-14 50 0 60 18 132 18M690 96c4-20 16-30 30-30s26 10 30 30M946 44v52M962 44v52M978 44v52'; ?>
        <path class="v-route-ghost" d="<?= $hvRoute ?>"/>
        <path id="journey-line" pathLength="1" d="<?= $hvRoute ?>"/>
      </svg>
    </div>
    <div class="v-stops" id="journey-stops">
      <?php foreach ($V2['journey'] as $s): ?>
      <a class="v-stop" href="<?= v2_e($s['href']) ?>"><span class="v-stop-ph"><img src="<?= v2_e($s['image']) ?>" alt="<?= v2_e($s['alt']) ?>" loading="lazy" decoding="async" width="640" height="672"><small><?= $s['count'] ? v2_e(v2_exp($s['count'])) : v2_e($s['region']) ?></small></span><strong><?= v2_e($s['label']) ?></strong><span class="v-stop-t"><?= v2_e($s['text']) ?></span></a>
      <?php endforeach; ?>
      <div class="v-stop is-end"><p class="v-eyebrow"><?= v2_te('More places. Same feeling.') ?></p><strong><?= $V2['totals']['cities'] ? v2_e(v2_num($V2['totals']['cities'], 'city', 'cities')) : v2_te('Across Europe') ?></strong><span class="v-stop-t"><?= v2_te('Every destination gets its own page with a map, opening days and what to book first.') ?></span><a class="btn v-btn-cream" href="/cities"><?= v2_te('All destinations') ?><?= v2_ic('arrow-right') ?></a></div>
    </div>
    <div class="wrap"><div class="v-progress" id="journey-progress" aria-hidden="true"><span>Bucharest</span><i></i><span>Black Sea</span></div></div>
  </div>
</section>

<!-- ============================ PLAN TOOLS ============================ -->
<section class="v-sec v-tools-sec" aria-labelledby="tools-h">
  <div class="wrap">
    <div class="v-head"><div><p class="v-eyebrow"><?= v2_te('Plan') ?></p><h2 id="tools-h" class="v-h2"><?= v2_te('Three ways to plan the trip') ?></h2></div></div>
    <ul class="v-tools" data-vreveal>
      <li><a class="v-tool" href="/plan"><span class="v-ring"><?= v2_ic('compass') ?></span><strong><?= v2_te('Trip planner') ?></strong><span><?= v2_te('Say where and for how long. The itinerary builds itself, day by day.') ?></span><em><?= v2_te('Open the planner') ?><?= v2_ic('arrow-right') ?></em></a></li>
      <li><a class="v-tool" href="/map"><span class="v-ring"><?= v2_ic('map-trifold') ?></span><strong><?= v2_te('Attractions map') ?></strong><span><?= v2_te('Everything nearby on one map, with filters for what you feel like.') ?></span><em><?= v2_te('Open the map') ?><?= v2_ic('arrow-right') ?></em></a></li>
      <li><a class="v-tool" href="/routes"><span class="v-ring"><?= v2_ic('path') ?></span><strong><?= v2_te('Routes') ?></strong><span><?= v2_te('Ready-made itineraries with the stops already in the right order.') ?></span><em><?= v2_te('See the routes') ?><?= v2_ic('arrow-right') ?></em></a></li>
    </ul>
  </div>
</section>

<!-- ============================ WHO ============================ -->
<section class="v-sec" id="who" aria-labelledby="who-h">
  <div class="wrap">
    <div class="v-head"><div><p class="v-eyebrow"><?= v2_te('Experience type') ?></p><h2 id="who-h" class="v-h2"><?= v2_te('Who\'s coming along?') ?></h2></div><p class="v-lede"><?= v2_te('The same castle is a different day with a five-year-old, with old friends, or with one other person.') ?></p></div>
    <div class="v-who" data-vreveal>
      <a href="/with-kids"><img src="<?= v2_asset('img/who-familii-800.webp') ?>" alt="<?= v2_te('A family outdoors') ?>" loading="lazy" decoding="async" width="800" height="1000"><strong><?= v2_te('for families') ?></strong><span><?= v2_te('Meaningful moments for every generation.') ?></span><ul><li><?= v2_te('Stroller friendly') ?></li><li><?= v2_te('Under two hours') ?></li><li><?= v2_te('Hands-on') ?></li></ul></a>
      <a href="/search?who=friends"><img src="<?= v2_asset('img/who-prieteni-800.webp') ?>" alt="<?= v2_te('Friends in kayaks on a quiet river') ?>" loading="lazy" decoding="async" width="800" height="1000"><strong><?= v2_te('for friends') ?></strong><span><?= v2_te('Things you\'ll still be talking about on the drive home.') ?></span><ul><li><?= v2_te('Group rates') ?></li><li><?= v2_te('Adrenaline') ?></li><li><?= v2_te('Tastings') ?></li></ul></a>
      <a href="/for-couples"><img src="<?= v2_asset('img/who-cupluri-800.webp') ?>" alt="<?= v2_te('A couple on a trail') ?>" loading="lazy" decoding="async" width="800" height="1000"><strong><?= v2_te('for two') ?></strong><span><?= v2_te('Slow mornings, late light and a table with a view.') ?></span><ul><li><?= v2_te('Sunset slots') ?></li><li><?= v2_te('Private tours') ?></li><li><?= v2_te('Gift ready') ?></li></ul></a>
    </div>
  </div>
</section>

<!-- ============================ WHY ============================ -->
<section class="v-why-sec" aria-labelledby="why-h">
  <div class="wrap"><div class="v-why">
    <p class="v-eyebrow"><?= v2_te('Why Viaqui') ?></p>
    <h2 id="why-h"><?= v2_t('A simpler way to a more <em>open</em> world.') ?></h2>
    <div class="v-bento" data-vreveal>
      <div class="v-tile is-1"><h3><?= v2_te('Your phone is the ticket') ?></h3><p><?= v2_te('The QR code lands in your inbox right after payment. Show it at the gate, online or off.') ?></p>
        <div class="v-qr" aria-hidden="true"><svg viewBox="0 0 21 21" shape-rendering="crispEdges"><path fill="#0F4D3A" d="M0 0h7v7H0zM14 0h7v7h-7zM0 14h7v7H0z"/><path fill="#fff" d="M1 1h5v5H1zM15 1h5v5h-5zM1 15h5v5H1z"/><path fill="#0F4D3A" d="M2 2h3v3H2zM16 2h3v3h-3zM2 16h3v3H2zM8 0h1v2H8zM10 1h2v1h-2zM9 3h1v3H9zM11 4h2v1h-2zM8 7h2v1H8zM12 6h1v3h-1zM0 8h2v1H0zM3 9h3v1H3zM1 11h1v2H1zM4 11h2v1H4zM8 9h3v2H8zM12 10h2v1h-2zM15 8h2v2h-2zM18 8h3v1h-3zM19 10h1v3h-1zM16 11h2v1h-2zM8 12h1v3H8zM10 12h3v1h-3zM11 14h2v2h-2zM9 16h1v2H9zM8 19h3v1H8zM12 17h2v1h-2zM14 13h3v2h-3zM15 16h1v3h-1zM17 16h3v1h-3zM18 18h2v2h-2zM13 19h2v2h-2z"/></svg></div>
        <p class="v-pass" aria-hidden="true"><span>VQ-7F3K-9D2M</span><span><?= v2_te('2 adults · 10:00') ?></span></p></div>
      <div class="v-tile is-2"><?= v2_ic('lightning') ?><h3><?= v2_te('Instant confirmation') ?></h3><p><?= v2_te('No waiting for an email from the venue. Paid means booked.') ?></p><div class="v-steps" aria-hidden="true"><span></span><span></span><span></span></div></div>
      <div class="v-tile is-3"><?= v2_ic('check-circle') ?><h3><?= v2_te('The rules, before you pay') ?></h3><p><?= v2_te('Each listing states its cancellation and rescheduling terms next to the price.') ?></p><div class="v-timeline" aria-hidden="true"><span><?= v2_te('Booked') ?></span><span><?= v2_te('Change date') ?></span><span><?= v2_te('Visit') ?></span></div></div>
      <div class="v-tile is-4"><h3><?= v2_te('Real people, real answers') ?></h3><p><?= v2_te('Support that knows the venue, by email and phone.') ?></p><span class="v-bubble"><?= v2_te('Can we bring a stroller into the castle?') ?></span><span class="v-bubble is-me"><?= v2_te('Yes. There is a lift at the east gate.') ?></span></div>
      <div class="v-tile is-5"><?php if ($V2['totals']['attractions'] > 0): ?><span class="v-big" data-count="<?= (int) $V2['totals']['attractions'] ?>"><?= v2_e(number_format($V2['totals']['attractions'])) ?></span><h3><?= v2_te('places mapped') ?></h3><p><?= v2_te('Attractions in {cities}, each with a page, a pin and what to know before you go.', ['cities' => v2_num($V2['totals']['cities'], 'city', 'cities')]) ?></p><?php else: ?><span class="v-big"><?= v2_te('Europe') ?></span><h3><?= v2_te('one country at a time') ?></h3><p><?= v2_te('Every attraction gets a page, a pin on the map and what to know before you go. New cities are added as venues join.') ?></p><?php endif; ?></div>
    </div>
  </div></div>
</section>

<!-- ============================ GIFT ============================ -->
<section class="v-sec" id="gift" aria-labelledby="gift-h">
  <div class="wrap v-gift">
    <div class="v-gift-copy"><p class="v-eyebrow"><?= v2_te('Gift card') ?></p><h2 id="gift-h" class="v-h2"><?= v2_te('A gift for more journeys.') ?></h2><p class="v-lede"><?= v2_te('They choose the place and the day. You choose the amount. It arrives by email with your message and is valid for 12 months at any venue on Viaqui.') ?></p>
      <a class="btn v-btn-forest" href="/gift-card"><?= v2_te('Send a gift card') ?><?= v2_ic('arrow-right') ?></a></div>
    <div class="v-gift-stage" id="gift-stage"><div class="v-gift-card" id="gift-card">
      <div class="v-gift-top"><span class="v-word">VIAQUI</span><small><?= v2_t('Travel closer.<br>Experience more.') ?></small></div>
      <span class="v-gift-ln is-b"></span><span class="v-gift-ln"></span><div class="v-gift-ph"><img src="<?= v2_asset('img/hero-bran-900.webp') ?>" alt="" loading="lazy" decoding="async" width="900" height="643"></div>
      <div class="v-gift-bot"><strong><?= v2_te('A gift for more journeys.') ?></strong><small><?= v2_te('Gift card') ?></small></div>
      <span class="v-gift-shine"></span></div></div>
  </div>
</section>

<!-- ============================ GUIDES ============================ -->
<?php if ($V2['guideLead']): ?>
<section class="v-sec v-guides-sec" id="guides" aria-labelledby="guides-h">
  <div class="wrap">
    <div class="v-head"><div><p class="v-eyebrow"><?= v2_te('Guides') ?></p><h2 id="guides-h" class="v-h2"><?= v2_t('Curated experiences<br>for curious people.') ?></h2></div><a class="v-link" href="/guides"><?= v2_te('All guides') ?><?= v2_ic('arrow-right') ?></a></div>
    <div class="v-guides" data-vreveal>
      <?php foreach (array_merge([$V2['guideLead']], $V2['guideSide']) as $i => $g): $hvImg = $g['cover'] ?: ($g['thumb'] ? [$g['thumb'], 0, 0, ''] : null); ?>
      <a class="v-guide" href="<?= v2_e($g['href']) ?>"><span class="v-guide-ph"><?= $hvImg ? v2_photo($hvImg) : v2_fallback($g['slug'], $i) ?></span><small><?= v2_e(trim($g['category'] . ($g['readTime'] ? ' · ' . v2_t('{n} min read', ['n' => $g['readTime']]) : ''), ' ·')) ?></small><strong><?= v2_e($g['title']) ?></strong><?php if ($g['excerpt']): ?><span><?= v2_e($g['excerpt']) ?></span><?php endif; ?></a>
      <?php endforeach; ?>
    </div>
  </div>
</section>
<?php endif; ?>

<!-- ============================ VENUES ============================ -->
<section class="v-venues-sec" aria-labelledby="ven-h">
  <div class="wrap"><div class="v-venues">
    <div><p class="v-eyebrow"><?= v2_te('For venues and operators') ?></p><h2 id="ven-h"><?= v2_te('Run a place people should see? Sell tickets on Viaqui.') ?></h2><p class="v-lede"><?= v2_te('Timed entry, QR scanning at the gate and payouts on a schedule you choose. Listing is free; you pay a commission only on tickets sold.') ?></p><div class="v-btns"><a class="btn v-btn-forest" href="/partners"><?= v2_te('List your venue') ?><?= v2_ic('arrow-right') ?></a><a class="btn v-btn-ghost" href="/partners#demo"><?= v2_te('Book a demo') ?></a></div></div>
    <dl><div><dt><?= v2_te('Timed tickets and capacity') ?></dt><dd><?= v2_te('Slots, closed days and seasons set once, sold everywhere.') ?></dd></div><div><dt><?= v2_te('Scan at the gate') ?></dt><dd><?= v2_te('Any phone becomes a validator, even when the signal drops.') ?></dd></div><div><dt><?= v2_te('Your own widget') ?></dt><dd><?= v2_te('Sell on your website with the same checkout, in your colours.') ?></dd></div></dl>
  </div></div>
</section>

<!-- ============================ TOP LISTS ============================ -->
<section class="v-sec" id="top" aria-labelledby="top-h">
  <div class="wrap">
    <div class="v-head"><div><p class="v-eyebrow"><?= v2_te('Across the map') ?></p><h2 id="top-h" class="v-h2"><?= v2_te('Where people are heading') ?></h2></div></div>
    <div class="v-tabs" role="tablist" aria-label="<?= v2_te('Top lists') ?>" id="top-tabs">
      <button class="v-tab" type="button" role="tab" id="tt-0" aria-controls="tp-0" aria-selected="true"><?= v2_te('Destinations') ?></button>
      <button class="v-tab" type="button" role="tab" id="tt-1" aria-controls="tp-1" aria-selected="false" tabindex="-1"><?= v2_te('Categories') ?></button>
      <button class="v-tab" type="button" role="tab" id="tt-2" aria-controls="tp-2" aria-selected="false" tabindex="-1"><?= v2_te('Countries') ?></button>
    </div>
    <div id="tp-0" role="tabpanel" aria-labelledby="tt-0"><ul class="v-toplist">
      <?php foreach (array_slice($V2['citiesList'], 0, 24) as $i => $c): ?><li><a href="<?= v2_e($c['href']) ?>"><i><?= sprintf('%02d', $i + 1) ?></i><?= v2_e($c['name']) ?><span><?= v2_e($c['count'] ? v2_exp($c['count']) : $c['region']) ?></span></a></li><?php endforeach; ?>
    </ul></div>
    <div id="tp-1" role="tabpanel" aria-labelledby="tt-1" hidden><ul class="v-toplist">
      <?php foreach ($V2['categories'] as $i => $c): ?><li><a href="<?= v2_e($c['href']) ?>"><i><?= sprintf('%02d', $i + 1) ?></i><?= v2_e($c['name']) ?><?php if ($c['count']): ?><span><?= (int) $c['count'] ?></span><?php endif; ?></a></li><?php endforeach; ?>
    </ul></div>
    <div id="tp-2" role="tabpanel" aria-labelledby="tt-2" hidden><ul class="v-toplist">
      <?php foreach (($V2['countriesAll'] ?? $V2['regions']) as $i => $r): ?><li><a href="/<?= v2_e($r['slug']) ?>"><i><?= sprintf('%02d', $i + 1) ?></i><?= v2_e($r['name']) ?><span><?= v2_e(v2_num($r['citiesCount'], 'city', 'cities')) ?></span></a></li><?php endforeach; ?>
    </ul></div>
  </div>
</section>

<!-- ============================ FAQ ============================ -->
<section class="v-sec v-faq-sec" id="faq" aria-labelledby="faq-h">
  <div class="wrap v-faq">
    <div class="v-faq-l"><p class="v-eyebrow"><?= v2_te('Good to know') ?></p><h2 id="faq-h" class="v-h2"><?= v2_te('Questions before you book') ?></h2><p class="v-lede"><?= v2_te('Still unsure? Write to us and a person answers.') ?></p><a class="v-link" href="/help"><?= v2_te('Help centre') ?><?= v2_ic('arrow-right') ?></a></div>
    <div>
      <?php foreach (v2_home_faq() as $i => [$q, $a]): ?>
      <details<?= $i === 0 ? ' open' : '' ?>><summary><?= v2_e($q) ?></summary><p><?= v2_e($a) ?></p></details>
      <?php endforeach; ?>
    </div>
  </div>
</section>
</main>
