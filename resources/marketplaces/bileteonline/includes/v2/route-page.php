<?php
/**
 * bilete.online v2: one editorial route — /trasee/{slug}.
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
$v2Scripts = array_merge($v2Scripts ?? [], ['map.js']);

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
        <nav class="crumbs" aria-label="Breadcrumb">
          <?php foreach ($routePage['breadcrumbs'] as $i => [$bcName, $bcUrl]): ?>
            <?php if ($i > 0): ?><span aria-hidden="true">/</span><?php endif; ?>
            <?php if ($i < count($routePage['breadcrumbs']) - 1): ?><a href="<?= v2_e($bcUrl) ?>"><?= v2_e($bcName) ?></a><?php else: ?><span aria-current="page"><?= v2_e($bcName) ?></span><?php endif; ?>
          <?php endforeach; ?>
        </nav>
        <h1 id="mph-h"><span class="rp-emoji" aria-hidden="true"><?= v2_e($routePage['emoji']) ?></span><?= v2_e($routePage['title']) ?></h1>
        <p class="mph-lead"><?= v2_e($routePage['lead']) ?></p>
      </div>
      <ul class="mph-stats">
        <li><b><?= count($rpStops) ?></b> opriri</li>
        <li><b><?= v2_e(v2_thousands((int) $routePage['km'])) ?></b> km<?= !empty($routePage['road']) ? ' pe șosea' : ' în linie dreaptă' ?></li>
        <?php if (!empty($routePage['drive'])): ?><li><b><?= v2_e($routePage['drive']) ?></b> de condus</li><?php endif; ?>
        <li><b><?= v2_e($routePage['pace']) ?></b></li>
      </ul>
    </div>
  </section>

  <!-- ============================== MAP ============================== -->
  <section class="mp-band" aria-label="Harta traseului">
    <div class="wrap">
      <div class="mp-frame">
        <div data-epm-root data-epm-config="<?= v2_e(json_encode($routePage['config'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)) ?>"></div>
      </div>
      <p class="rp-note"><?php if (!empty($routePage['road'])): ?>Distanțele și timpii sunt calculați pe drumurile reale (OpenStreetMap), fără opriri și fără trafic.<?php else: ?>Pentru acest traseu distanța e măsurată în linie dreaptă între opriri, nu pe șosea.<?php endif; ?> <a href="<?= v2_e($rpGmaps) ?>" target="_blank" rel="noopener">Deschide tot traseul în Google Maps<?= v2_ic('arrow-right') ?></a></p>
    </div>
  </section>

  <!-- ============================== STOPS ============================== -->
  <section class="sec" aria-labelledby="rp-stops-h">
    <div class="wrap">
      <div class="sec-head">
        <h2 id="rp-stops-h">Opririle, în ordine</h2>
        <a class="sec-link" href="<?= v2_e($rpGmaps) ?>" target="_blank" rel="noopener">Navighează<?= v2_ic('arrow-right') ?></a>
      </div>
      <ol class="rp-stops">
        <?php foreach ($rpStops as $i => $stop): [$sSlug, $sName, $sCity, $sCitySlug, $sCounty, $sType, $sEmoji, $sLat, $sLng, $sImg, $sLeg] = $stop; $sMin = $stop[11] ?? 0; ?>
        <li class="rp-stop">
          <span class="rp-num" aria-hidden="true"><?= $i + 1 ?></span>
          <a class="rp-card" href="/atractie/<?= v2_e($sSlug) ?>">
            <span class="rp-media"><?php if ($sImg): ?><img src="<?= v2_e($sImg) ?>" alt="" width="200" height="150" loading="lazy" decoding="async"><?php else: ?><?= v2_fallback($sName, $i) ?><?php endif; ?></span>
            <span class="rp-body">
              <?php if ($sType): ?><span class="rp-kicker"><span aria-hidden="true"><?= v2_e($sEmoji) ?></span> <?= v2_e($sType) ?></span><?php endif; ?>
              <span class="rp-title"><?= v2_e($sName) ?></span>
              <span class="rp-meta">
                <?php if ($sCity !== ''): ?><span><?= v2_ic('map-pin') ?><?= v2_e($sCity) ?><?= $sCounty !== '' && $sCounty !== $sCity ? ', ' . v2_e($sCounty) : '' ?></span><?php endif; ?>
                <?php if ($i > 0 && $sLeg > 0): ?><span class="rp-leg"><?= v2_ic('arrow-right') ?><?= v2_e(str_replace('.', ',', (string) $sLeg)) ?> km<?php if (!empty($sMin)): ?> · <?= v2_e(v2_hm((int) $sMin)) ?><?php endif; ?> de la oprirea anterioară</span><?php endif; ?>
              </span>
            </span>
          </a>
        </li>
        <?php endforeach; ?>
      </ol>
    </div>
  </section>

  <!-- ============================== TEXT + FAQ ============================== -->
  <section class="sec" aria-labelledby="rp-text-h">
    <div class="wrap mp-text">
      <div class="mp-prose">
        <h2 id="rp-text-h" class="sr">Despre traseu</h2>
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
        <h2 id="rp-more-h">Alte trasee</h2>
        <a class="sec-link" href="/trasee">Toate traseele<?= v2_ic('arrow-right') ?></a>
      </div>
      <?php require __DIR__ . '/route-cards.php'; ?>
    </div>
  </section>
  <?php endif; ?>
</main>
<?php include __DIR__ . '/footer.php'; ?>
