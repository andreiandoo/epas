<?php
/**
 * viaqui.com v2: one editorial route — /routes/{slug}.
 *
 * The map is the same EPMap, in route mode: numbered pins on a dashed line, the list showing the
 * stops in order rather than by distance. Route mode builds its dataset from the stops the page
 * already prints, so this page never downloads the 7k-pin file.
 *
 * $routePage: slug, title, lead, intro, pace, emoji, km, stops, breadcrumbs, config, faq, others.
 */

require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/nav.php';

$rpStops = $routePage['stops'];
$rpFirst = $rpStops[0] ?? null;
$rpLast  = $rpStops[count($rpStops) - 1] ?? null;

$v2Styles  = array_merge(['map.css', 'map-page.css', 'routes.css'], $v2Styles ?? []);
$v2Scripts = array_merge($v2Scripts ?? [], ['map.js', 'routes.js']);

// Every stop as a waypoint, so the whole route opens in Google Maps in one tap.
$rpGmaps = 'https://www.google.com/maps/dir/?api=1'
    . '&origin=' . rawurlencode($rpFirst[7] . ',' . $rpFirst[8])
    . '&destination=' . rawurlencode($rpLast[7] . ',' . $rpLast[8])
    . (count($rpStops) > 2
        ? '&waypoints=' . rawurlencode(implode('|', array_map(
            fn ($s) => $s[7] . ',' . $s[8],
            array_slice($rpStops, 1, -1)
        )))
        : '');

include __DIR__ . '/head.php';
include __DIR__ . '/header.php';
?>
<main id="main" tabindex="-1">
  <!-- ============================== HERO ============================== -->
  <section class="mph" aria-labelledby="mph-h">
    <div class="wrap mph-in">
      <div class="mph-copy">
        <nav class="crumbs" aria-label="<?= v2_te('Breadcrumb') ?>">
          <?php foreach ($routePage['breadcrumbs'] as $i => [$bcName, $bcUrl]): ?>
            <?php if ($i > 0): ?><span aria-hidden="true">/</span><?php endif; ?>
            <?php if ($i < count($routePage['breadcrumbs']) - 1): ?><a href="<?= v2_e($bcUrl) ?>"><?= v2_e($bcName) ?></a><?php else: ?><span aria-current="page"><?= v2_e($bcName) ?></span><?php endif; ?>
          <?php endforeach; ?>
        </nav>
        <h1 id="mph-h"><span class="rp-emoji" aria-hidden="true"><?= am_product_icon_svg(am_product_icon($routePage['emoji']) ?? 'map', 'ic-em') ?></span><?= v2_e($routePage['title']) ?></h1>
        <p class="mph-lead"><?= v2_e($routePage['lead']) ?></p>
      </div>
      <ul class="mph-stats">
        <li><b><?= count($rpStops) ?></b> <?= v2_e(v2_plural(count($rpStops), 'stop', 'stops')) ?></li>
        <li><?= !empty($routePage['road']) ? v2_t('<b>{n}</b> km by road', ['n' => v2_e(v2_thousands((int) $routePage['km']))]) : v2_t('<b>{n}</b> km in a straight line', ['n' => v2_e(v2_thousands((int) $routePage['km']))]) ?></li>
        <?php if (!empty($routePage['drive'])): ?><li><?= v2_t('<b>{time}</b> of driving', ['time' => v2_e($routePage['drive'])]) ?></li><?php endif; ?>
        <li><b><?= v2_e($routePage['pace']) ?></b></li>
      </ul>
    </div>
  </section>

  <!-- ============================== MAP ============================== -->
  <section class="mp-band" aria-label="<?= v2_te('Map of the route') ?>">
    <div class="wrap">
      <div class="mp-frame">
        <div data-epm-root data-epm-config="<?= v2_e(json_encode($routePage['config'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)) ?>"></div>
      </div>
      <p class="rp-note"><?php if (!empty($routePage['road'])): ?><?= v2_te('Distances and times are calculated on real roads (OpenStreetMap), with no stops and no traffic.') ?><?php else: ?><?= v2_te('For this route the distance is measured in a straight line between the stops, not by road.') ?><?php endif; ?></p>
      <div class="rdp-acts">
        <a class="btn btn-primary rdp-go" href="/plan?route=<?= v2_e($routePage['slug']) ?>"><?= v2_ic('compass') ?><?= v2_te('Open as a plan') ?></a>
        <p class="rdp-hint"><?= v2_t('The route goes into the planner with its stops, split over days: move, remove or add stops, see the times and look for a place to stay. Or <a href="{url}" target="_blank" rel="noopener">open the whole route in Google Maps</a>.', ['url' => v2_e($rpGmaps)]) ?></p>
      </div>
    </div>
  </section>

  <!-- ============================== STOPS ============================== -->
  <section class="sec" aria-labelledby="rp-stops-h">
    <div class="wrap">
      <div class="sec-head">
        <h2 id="rp-stops-h"><?= v2_te('The stops, in order') ?></h2>
        <a class="sec-link" href="<?= v2_e($rpGmaps) ?>" target="_blank" rel="noopener"><?= v2_te('Open in Google Maps') ?><?= v2_ic('arrow-right') ?></a>
      </div>
      <ol class="rp-stops">
        <?php foreach ($rpStops as $i => $stop): [$sSlug, $sName, $sCity, $sCitySlug, $sCounty, $sType, $sEmoji, $sLat, $sLng, $sImg, $sLeg] = $stop; $sMin = $stop[11] ?? 0; ?>
        <li class="rp-stop" data-place="<?= v2_e($sSlug) ?>">
          <span class="rp-num" aria-hidden="true"><?= $i + 1 ?></span>
          <div class="rp-card">
            <button class="rp-main" type="button" aria-expanded="false">
              <span class="rp-media"><?php if ($sImg): ?><img src="<?= v2_e(v2_thumb($sImg, 320, 240)) ?>" alt="" width="200" height="150" loading="lazy" decoding="async"><?php else: ?><?= v2_fallback($sName, $i) ?><?php endif; ?></span>
              <span class="rp-body">
                <?php if ($sType): ?><span class="rp-kicker"><?= v2_e($sType) ?></span><?php endif; ?>
                <span class="rp-title"><?= v2_e($sName) ?></span>
                <span class="rp-meta">
                  <?php if ($sCity !== ''): ?><span><?= v2_ic('map-pin') ?><?= v2_e($sCity) ?><?= $sCounty !== '' && $sCounty !== $sCity ? ', ' . v2_e($sCounty) : '' ?></span><?php endif; ?>
                  <?php if ($i > 0 && $sLeg > 0): ?><span class="rp-leg"><?= v2_ic('arrow-right') ?><?= !empty($sMin) ? v2_te('{km} km · {time} from the previous stop', ['km' => (string) $sLeg, 'time' => v2_hm((int) $sMin)]) : v2_te('{km} km from the previous stop', ['km' => (string) $sLeg]) ?></span><?php endif; ?>
                </span>
              </span>
              <?= v2_ic('caret-down', 'ic rp-car') ?>
            </button>
            <div class="rp-more"><div><div class="rp-in">
              <p class="rp-about" hidden></p>
              <div class="rp-acts">
                <a class="rp-go" href="/attraction/<?= v2_e($sSlug) ?>"><?= v2_te('Details') ?><?= v2_ic('arrow-right') ?></a>
                <a class="rp-go is-quiet" href="https://www.google.com/maps/search/?api=1&amp;query=<?= v2_e($sLat . ',' . $sLng) ?>" target="_blank" rel="noopener"><?= v2_te('Show in Google Maps') ?></a>
              </div>
            </div></div></div>
          </div>
        </li>
        <?php endforeach; ?>
      </ol>
    </div>
  </section>

  <?php if (!empty($routePage['trip'])): ?>
  <!-- ============================== BEFORE YOU SET OFF (partners: car hire, transfer, eSIM) ============================== -->
  <section class="sec ptrip-sec" id="before-you-go" aria-labelledby="rp-trip-h">
    <div class="wrap">
      <div class="sec-head">
        <div><p class="kicker"><?= v2_te('Before you set off') ?></p><h2 id="rp-trip-h"><?= v2_te('A car for this route') ?></h2></div>
      </div>
      <p class="ptrip-intro"><?= v2_te('Hire the car where you arrive, at the airport or in the first city, and check that it can be returned where the route ends.') ?></p>
      <?= v2_trip_tiles($routePage['trip'], 'route-' . $routePage['slug']) ?>
    </div>
  </section>
  <?php endif; ?>

  <!-- ============================== TEXT + FAQ ============================== -->
  <section class="sec" aria-labelledby="rp-text-h">
    <div class="wrap mp-text">
      <div class="mp-prose">
        <h2 id="rp-text-h" class="sr"><?= v2_te('About the route') ?></h2>
        <?= implode("\n", $routePage['prose']) ?>
      </div>
      <?php if (!empty($routePage['faq'])): ?>
      <div class="mp-faq">
        <?php foreach ($routePage['faq'] as $i => [$fq, $fa]): ?>
        <details<?= $i === 0 ? ' open' : '' ?>><summary><?= v2_e($fq) ?></summary><p><?= v2_e($fa) ?></p></details>
        <?php endforeach; ?>
      </div>
      <?php endif; ?>
    </div>
  </section>

  <?php if (!empty($routePage['others'])): ?>
  <!-- ============================== OTHER ROUTES ============================== -->
  <section class="sec" aria-labelledby="rp-more-h">
    <div class="wrap">
      <div class="sec-head">
        <h2 id="rp-more-h"><?= v2_te('Other routes') ?></h2>
        <a class="sec-link" href="/routes"><?= v2_te('All routes') ?><?= v2_ic('arrow-right') ?></a>
      </div>
      <?php require __DIR__ . '/route-cards.php'; ?>
    </div>
  </section>
  <?php endif; ?>
</main>
<?php include __DIR__ . '/footer.php'; ?>
