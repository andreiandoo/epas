<?php
/**
 * viaqui.com v2: one road — /trasee/{slug} for an entry of includes/v2/map-roads.php.
 *
 * Where an editorial route is its stops, a road is its line: the map draws the routed geometry, the
 * profile under it is the same line seen from the side, and dragging along the profile moves a
 * marker along the road. The catalogue's attractions beside the road are listed in the order you
 * meet them, with the kilometre at which they come up.
 *
 * $roadPage: slug, title, ref, from, to, lead, intro, season, modes, km, min, max, low, up, z,
 *            geometry, a, b, stops, points, others, breadcrumbs.
 */

require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/nav.php';
require_once __DIR__ . '/map-roads.php';

$rd = $roadPage;
$rdMode = $rd['modes'][0];
$rdColor = ['moto' => '#C8322B', 'bike' => '#2D6CCD'][$rdMode] ?? '#1E5B48';
[$rdLine, $rdArea] = v2_profile_paths($rd['z'], 1000, 120);

// The map's rows: the two ends, and between them what the catalogue has beside the road.
// [slug, name, city, citySlug, county, type, icon, lat, lng, img, legKm, legMin]
$rdRows = [['', $rd['from'], '', '', '', 'Pornire', 'pin', $rd['a'][0], $rd['a'][1], '', 0, 0]];
foreach ($rd['stops'] as $s) {
    $rdRows[] = [$s[0], $s[1], $s[2], $s[3], $s[4], $s[5], am_place_icon($s[6] ?: null), $s[7], $s[8], $s[9], $s[10], 0];
}
$rdRows[] = ['', $rd['to'], '', '', '', 'Sosire', 'pin', $rd['b'][0], $rd['b'][1], '', 0, 0];

$rdOsm = $rd['osm'] ?? [];
$rdGmaps = $rdOsm ? '' : 'https://www.google.com/maps/dir/?api=1&travelmode=' . ($rdMode === 'bike' ? 'bicycling' : 'driving')
    . '&origin=' . rawurlencode($rd['a'][0] . ',' . $rd['a'][1])
    . '&destination=' . rawurlencode($rd['b'][0] . ',' . $rd['b'][1])
    . (count($rd['points'] ?? []) > 2
        ? '&waypoints=' . rawurlencode(implode('|', array_map(fn ($p) => $p[0] . ',' . $p[1], array_slice($rd['points'], 1, -1))))
        : '');

$v2Styles  = array_merge(['map.css', 'map-page.css', 'routes.css'], $v2Styles ?? []);
$v2Scripts = array_merge($v2Scripts ?? [], ['map.js', 'routes.js']);

include __DIR__ . '/head.php';
include __DIR__ . '/header.php';
require __DIR__ . '/plan-icons.php';
?>
<main id="main" tabindex="-1" class="rdp" data-mode="<?= v2_e($rdMode) ?>">
  <!-- ============================== HERO ============================== -->
  <section class="mph" aria-labelledby="mph-h">
    <div class="wrap mph-in">
      <div class="mph-copy">
        <nav class="crumbs" aria-label="Breadcrumb">
          <?php foreach ($rd['breadcrumbs'] as $i => [$bcName, $bcUrl]): ?>
            <?php if ($i > 0): ?><span aria-hidden="true">/</span><?php endif; ?>
            <?php if ($i < count($rd['breadcrumbs']) - 1): ?><a href="<?= v2_e($bcUrl) ?>"><?= v2_e($bcName) ?></a><?php else: ?><span aria-current="page"><?= v2_e($bcName) ?></span><?php endif; ?>
          <?php endforeach; ?>
        </nav>
        <h1 id="mph-h"><?= v2_e($rd['title']) ?><?php if ($rd['ref'] !== ''): ?> <span class="rd-ref"><?= v2_e($rd['ref']) ?></span><?php endif; ?></h1>
        <p class="mph-lead"><?= v2_e($rd['from']) ?> – <?= v2_e($rd['to']) ?>. <?= v2_e($rd['lead']) ?></p>
      </div>
      <ul class="mph-stats">
        <li><b><?= v2_e(v2_thousands((int) $rd['km'])) ?></b> km</li>
        <?php if ($rd['max'] > 0): ?><li>până la <b><?= v2_e(v2_thousands((int) $rd['max'])) ?></b> m</li><?php endif; ?>
        <?php if ($rd['up'] > 0): ?><li><b><?= v2_e(v2_thousands((int) $rd['up'])) ?></b> m de urcare</li><?php endif; ?>
        <?php foreach ($rd['modes'] as $m): ?><li><?= v2_ic(MAP_ROAD_MODES[$m][1], 'ic rd-stat-ic') ?><?= v2_e(MAP_ROAD_MODES[$m][0]) ?></li><?php endforeach; ?>
      </ul>
    </div>
  </section>

  <!-- ============================== MAP + PROFILE ============================== -->
  <section class="mp-band" aria-label="Harta drumului">
    <div class="wrap">
      <div class="mp-shell rdp-shell">
        <div class="mp-frame rdp-frame">
          <div data-epm-root data-epm-config="<?= v2_e(json_encode([
              'cartoKey'      => defined('CARTO_API_KEY') ? CARTO_API_KEY : '',
              'urlState'      => false,
              'fixed'         => true,
              'bare'          => true,
              'title'         => $rd['title'],
              'base'          => '/atractie/',
              'routeColor'    => $rdColor,
              // a route mapped in pieces is drawn in its pieces (below), not as one line across the gaps
              'routeLine'     => $rdOsm ? false : true,
              'routeGeometry' => $rdOsm ? '' : $rd['geometry'],
              'routeStops'    => $rdRows,
          ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)) ?>"></div>
        </div>
        <?php if ($rdLine !== ''): ?>
        <div class="rdp-prof" id="rdp-prof" data-km="<?= (int) $rd['km'] ?>" data-z="<?= v2_e(implode(',', $rd['z'])) ?>" data-g="<?= v2_e($rd['geometry']) ?>">
          <div class="rdp-prof-h"><span id="rdp-prof-a">Profilul drumului · <?= v2_e(v2_thousands((int) $rd['low'])) ?>–<?= v2_e(v2_thousands((int) $rd['max'])) ?> m</span><span id="rdp-prof-b">trage pe profil ca să vezi locul pe hartă</span></div>
          <svg viewBox="0 0 1000 120" preserveAspectRatio="none" role="img" aria-label="Profil de altitudine: de la <?= (int) $rd['low'] ?> la <?= (int) $rd['max'] ?> de metri, cu <?= (int) $rd['up'] ?> de metri de urcare"><path class="rd-prof-a" d="<?= v2_e($rdArea) ?>"/><path class="rd-prof-l" d="<?= v2_e($rdLine) ?>"/><line class="rdp-prof-x" id="rdp-prof-x" y1="0" y2="120" style="display:none"/></svg>
          <div class="rdp-prof-ax"><span><?= v2_e($rd['from']) ?></span><span><?= v2_e($rd['to']) ?></span></div>
        </div>
        <?php endif; ?>
      </div>
      <?php if ($rdOsm): ?>
      <div class="rdp-acts" id="rdp-osm" data-g="<?= v2_e($rd['geometry']) ?>" data-breaks="<?= v2_e(implode(',', $rd['breaks'] ?? [])) ?>" data-color="<?= v2_e($rdColor) ?>">
        <a class="rdp-link" href="https://www.openstreetmap.org/relation/<?= (int) $rdOsm[0] ?>" target="_blank" rel="noopener">Vezi traseul în OpenStreetMap<?= v2_ic('arrow-right') ?></a>
        <?php if (!empty($rd['by'])): ?><a class="rdp-link" href="<?= v2_e($rd['by'][1]) ?>" target="_blank" rel="noopener"><?= v2_e($rd['by'][0]) ?><?= v2_ic('arrow-right') ?></a><?php endif; ?>
      </div>
      <?php else: ?>
      <div class="rdp-acts">
        <a class="btn btn-primary rdp-go" href="/plan?drum=<?= v2_e($rd['slug']) ?>"><?= v2_ic('compass') ?>Deschide ca plan</a>
        <a class="rdp-link" href="<?= v2_e($rdGmaps) ?>" target="_blank" rel="noopener">Navighează în Google Maps<?= v2_ic('arrow-right') ?></a>
      </div>
      <?php endif; ?>
      <?php if (!empty($rd['season'])): ?><p class="rdp-season"><?= v2_ic('info') ?><span><?= v2_e($rd['season']) ?></span></p><?php endif; ?>
      <?php if ($rdOsm): ?>
      <p class="rp-note"><strong>Sursa:</strong> <a href="https://www.openstreetmap.org/relation/<?= (int) $rdOsm[0] ?>" target="_blank" rel="noopener">OpenStreetMap</a>, <?= count($rdOsm) > 1 ? 'relațiile' : 'relația' ?> <?= v2_e(implode(', ', $rdOsm)) ?> · © contribuitorii OpenStreetMap, licență <a href="https://www.openstreetmap.org/copyright" target="_blank" rel="noopener">ODbL</a><?php if (!empty($rd['by'])): ?> · traseu <?= $rd['by'][0] === 'EuroVelo (ECF)' ? 'coordonat de' : 'marcat de' ?> <?= v2_e($rd['by'][0]) ?><?php endif; ?>. Unde linia e întreruptă, bucata aceea nu e încă trecută în OpenStreetMap. Altitudinile sunt măsurate pe un model de teren cu pasul de 25 m.</p>
      <?php else: ?>
      <p class="rp-note">Linia e calculată pe drumurile din OpenStreetMap; kilometrii și timpul sunt fără opriri și fără trafic. Altitudinile sunt măsurate pe un model de teren cu pasul de 25 m, deci sunt aproximative.</p>
      <?php endif; ?>
    </div>
  </section>

  <!-- ============================== ALONG THE ROAD ============================== -->
  <?php if ($rd['stops']): ?>
  <section class="sec" aria-labelledby="rd-stops-h">
    <div class="wrap">
      <div class="sec-head">
        <h2 id="rd-stops-h">Ce vezi pe drum</h2>
        <?php if (!$rdOsm): ?><a class="sec-link" href="/plan?drum=<?= v2_e($rd['slug']) ?>">Fă-ți planul<?= v2_ic('arrow-right') ?></a><?php endif; ?>
      </div>
      <ol class="rp-stops">
        <?php foreach ($rd['stops'] as $i => $stop): [$sSlug, $sName, $sCity, $sCitySlug, $sCounty, $sType, $sTypeSlug, $sLat, $sLng, $sImg] = $stop; $sAt = $stop[12] ?? 0; ?>
        <li class="rp-stop" id="rd-stop-<?= $i + 2 ?>" data-place="<?= v2_e($sSlug) ?>">
          <span class="rp-num" aria-hidden="true"><?= $i + 2 ?></span>
          <div class="rp-card">
            <button class="rp-main" type="button" aria-expanded="false">
              <span class="rp-media"><?php if ($sImg): ?><img src="<?= v2_e(v2_thumb($sImg, 320, 240)) ?>" alt="" width="200" height="150" loading="lazy" decoding="async"><?php else: ?><?= v2_fallback($sName, $i) ?><?php endif; ?></span>
              <span class="rp-body">
                <?php if ($sType): ?><span class="rp-kicker"><?= v2_e($sType) ?></span><?php endif; ?>
                <span class="rp-title"><?= v2_e($sName) ?></span>
                <span class="rp-meta">
                  <?php if ($sCity !== ''): ?><span><?= v2_ic('map-pin') ?><?= v2_e($sCity) ?><?= $sCounty !== '' && $sCounty !== $sCity ? ', ' . v2_e($sCounty) : '' ?></span><?php endif; ?>
                  <span class="rp-leg"><?= v2_ic('arrow-right') ?>la km <?= v2_e(str_replace('.', ',', (string) round((float) $sAt))) ?> de la <?= v2_e($rd['from']) ?></span>
                </span>
              </span>
              <?= v2_ic('caret-down', 'ic rp-car') ?>
            </button>
            <div class="rp-more"><div><div class="rp-in">
              <p class="rp-about" hidden></p>
              <div class="rp-acts">
                <a class="rp-go" href="/atractie/<?= v2_e($sSlug) ?>">Detalii<?= v2_ic('arrow-right') ?></a>
                <a class="rp-go is-quiet" href="https://www.google.com/maps/search/?api=1&amp;query=<?= v2_e($sLat . ',' . $sLng) ?>" target="_blank" rel="noopener">Arată în Google Maps</a>
              </div>
            </div></div></div>
          </div>
        </li>
        <?php endforeach; ?>
      </ol>
      <p class="rp-note">Sunt atracțiile din catalog aflate la cel mult câțiva kilometri de drum, în ordinea în care le întâlnești. Numerele sunt aceleași cu cele de pe hartă; 1 e pornirea, ultimul număr e sosirea.</p>
    </div>
  </section>
  <?php endif; ?>

  <!-- ============================== TEXT + FAQ ============================== -->
  <section class="sec" aria-labelledby="rd-text-h"<?= $rd['stops'] ? ' style="padding-top:0"' : '' ?>>
    <div class="wrap mp-text">
      <div class="mp-prose">
        <h2 id="rd-text-h" class="sr">Despre drum</h2>
        <?= implode("\n", $rd['prose']) ?>
      </div>
      <div class="mp-faq">
        <?php foreach ($rd['faq'] as $i => [$fq, $fa]): ?>
        <details<?= $i === 0 ? ' open' : '' ?>><summary><?= v2_e($fq) ?></summary><p><?= v2_e($fa) ?></p></details>
        <?php endforeach; ?>
      </div>
    </div>
  </section>

  <?php if (!empty($rd['others'])): $roadCards = $rd['others']; ?>
  <section class="sec" aria-labelledby="rd-more-h" style="padding-top:0">
    <div class="wrap">
      <div class="sec-head">
        <h2 id="rd-more-h">Alte drumuri</h2>
        <a class="sec-link" href="/trasee#drumuri">Toate drumurile<?= v2_ic('arrow-right') ?></a>
      </div>
      <?php require __DIR__ . '/road-cards.php'; ?>
    </div>
  </section>
  <?php endif; ?>
</main>
<script>
/* A route taken from OpenStreetMap in several pieces is drawn piece by piece, never across a gap. */
(function () {
  var box = document.getElementById('rdp-osm');
  if (!box) return;
  var str = box.getAttribute('data-g'), pts = [], i = 0, lat = 0, lng = 0;
  while (i < str.length) {
    for (var k = 0; k < 2; k++) {
      var res = 0, shift = 0, c;
      do { c = str.charCodeAt(i++) - 63; res |= (c & 31) << shift; shift += 5; } while (c >= 32);
      var d = (res & 1) ? ~(res >> 1) : (res >> 1);
      if (k === 0) lat += d; else lng += d;
    }
    pts.push([lat / 1e5, lng / 1e5]);
  }
  var cuts = (box.getAttribute('data-breaks') || '').split(',').filter(Boolean).map(Number), pieces = [], from = 0;
  cuts.concat([pts.length]).forEach(function (to) { if (to - from > 1) pieces.push({ g: pts.slice(from, to), color: box.getAttribute('data-color') }); from = to; });
  window.addEventListener('load', function () {
    var inst = window.EPMap && window.EPMap.instance;
    if (!inst || !inst.setGhosts) return;
    inst.setGhosts(pieces);
    inst.fitRoute({ all: true });
  });
})();
/* The profile and the map are the same line: dragging along one moves a marker along the other. */
(function () {
  var box = document.getElementById('rdp-prof');
  if (!box) return;
  var z = box.getAttribute('data-z').split(',').map(Number), total = +box.getAttribute('data-km') || 0;
  var a = document.getElementById('rdp-prof-a'), b = document.getElementById('rdp-prof-b'), mark = document.getElementById('rdp-prof-x');
  var restA = a.textContent, restB = b.textContent, pts = null;
  function line() {
    if (pts) return pts;
    var str = box.getAttribute('data-g'), raw = [], i = 0, lat = 0, lng = 0;
    while (i < str.length) {
      for (var k = 0; k < 2; k++) {
        var res = 0, shift = 0, c;
        do { c = str.charCodeAt(i++) - 63; res |= (c & 31) << shift; shift += 5; } while (c >= 32);
        var d = (res & 1) ? ~(res >> 1) : (res >> 1);
        if (k === 0) lat += d; else lng += d;
      }
      raw.push([lat / 1e5, lng / 1e5]);
    }
    // the same equal-distance samples the builder took the heights at
    var cum = [0], r = Math.PI / 180;
    for (i = 1; i < raw.length; i++) {
      var x = Math.pow(Math.sin((raw[i][0] - raw[i - 1][0]) * r / 2), 2) + Math.cos(raw[i - 1][0] * r) * Math.cos(raw[i][0] * r) * Math.pow(Math.sin((raw[i][1] - raw[i - 1][1]) * r / 2), 2);
      cum.push(cum[i - 1] + 12742 * Math.asin(Math.min(1, Math.sqrt(x))));
    }
    var all = cum[cum.length - 1], j = 0;
    pts = [];
    for (var s = 0; s < z.length; s++) {
      var at = all * s / (z.length - 1);
      while (j < raw.length - 2 && cum[j + 1] < at) j++;
      var span = cum[j + 1] - cum[j], f = span > 0 ? (at - cum[j]) / span : 0;
      pts.push([raw[j][0] + (raw[j + 1][0] - raw[j][0]) * f, raw[j][1] + (raw[j + 1][1] - raw[j][1]) * f]);
    }
    return pts;
  }
  var on = false;
  function move(ev) {
    var r = box.querySelector('svg').getBoundingClientRect(), last = z.length - 1;
    var i = Math.max(0, Math.min(last, Math.round((ev.clientX - r.left) / r.width * last)));
    mark.setAttribute('x1', i / last * 1000);
    mark.setAttribute('x2', i / last * 1000);
    mark.style.display = '';
    a.textContent = 'km ' + Math.round(total * i / last);
    b.textContent = z[i].toLocaleString('ro-RO') + ' m';
    var inst = window.EPMap && window.EPMap.instance, p = line()[i];
    if (inst && inst.setDot) inst.setDot(p[0], p[1]);
  }
  function rest() {
    on = false;
    mark.style.display = 'none';
    a.textContent = restA;
    b.textContent = restB;
    var inst = window.EPMap && window.EPMap.instance;
    if (inst && inst.setDot) inst.setDot(null);
  }
  box.addEventListener('pointerdown', function (ev) { on = true; box.setPointerCapture(ev.pointerId); move(ev); });
  box.addEventListener('pointermove', function (ev) { if (on || ev.pointerType === 'mouse') move(ev); });
  box.addEventListener('pointerup', rest);
  box.addEventListener('pointercancel', rest);
  box.addEventListener('pointerleave', function () { if (!on) rest(); });

  // A pin leads to its row in the list; the two ends have no row.
  window.addEventListener('load', function () {
    var inst = window.EPMap && window.EPMap.instance;
    if (inst && inst.onPin) inst.onPin(function (i) {
      var row = document.getElementById('rd-stop-' + (i + 1));
      if (row) row.scrollIntoView({ behavior: 'smooth', block: 'center' });
    });
  });
})();
</script>
<?php include __DIR__ . '/footer.php'; ?>
