<?php
/**
 * /trasee — the index of the editorial routes: twelve itineraries built out of the attraction
 * catalogue, each one an ordered list of real places (includes/v2/map-routes.php) — and, under
 * them, the roads people ride for the road itself (includes/v2/map-roads.php).
 */

$pageCacheTTL = 1800;
require_once __DIR__ . '/includes/page-cache.php';
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/api.php';
require_once __DIR__ . '/includes/nav-helpers.php';
require_once __DIR__ . '/includes/v2/helpers.php';
require_once __DIR__ . '/includes/v2/map-routes.php';
require_once __DIR__ . '/includes/v2/map-roads.php';
require_once __DIR__ . '/includes/v2/nav.php';

$summary = v2_map_summary();
if (!$summary || empty($summary['routes'])) {
    http_response_code(404);
    require __DIR__ . '/404.php';
    exit;
}

$routeCards = [];
$stopsTotal = 0;
$kmTotal = 0;
foreach (MAP_ROUTES as $slug => $r) {
    $d = $summary['routes'][$slug] ?? null;
    if (!$d) {
        continue;
    }
    $img = '';
    foreach ($d['stops'] as $s) {
        if ($s[9] !== '') {
            $img = $s[9];
            break;
        }
    }
    $routeCards[] = [$slug, $r['title'], $r['lead'], $r['emoji'], $r['pace'], $d['count'], $d['km'], $img, (int) ($d['road']['min'] ?? 0)];
    $stopsTotal += $d['count'];
    $kmTotal += $d['km'];
}

// The roads: one card each, and one overview map on which every road wears the number of its card.
$roadCards = [];
$roadPins = [];
$roadLines = [];
$roadKm = 0;
foreach (MAP_ROADS as $rSlug => $r) {
    $rd = v2_map_roads()[$rSlug] ?? null;
    if (!$rd) {
        continue;
    }
    $n = count($roadCards) + 1;
    $roadCards[] = [$rSlug, $r['title'], $r['ref'], $r['from'], $r['to'], $r['lead'], $r['modes'], $rd['km'], $rd['max'] ?? 0, $rd['up'] ?? 0, $rd['z'] ?? [], $n];
    $mid = $rd['mid'] ?? $rd['a'];
    $roadPins[] = ['', $r['title'], $r['from'] . ' – ' . $r['to'], '', '', $r['ref'] ?: 'Drum', 'pin', $mid[0], $mid[1], '', 0, 0];
    $roadLines[] = ['g' => $rd['geometry'], 'color' => $r['modes'][0] === 'bike' ? '#2D6CCD' : '#C8322B', 'modes' => $r['modes'], 'breaks' => $rd['breaks'] ?? []];
    $roadKm += (int) $rd['km'];
}

$v2Styles = ['map-page.css', 'routes.css'];
if ($roadCards) {
    array_unshift($v2Styles, 'map.css');
    $v2Scripts = ['map.js'];
}

$pageTitleRaw    = 'Trasee turistice în România — ' . count($routeCards) . ' itinerarii'
    . ($roadCards ? ' și ' . count($roadCards) . ' drumuri pentru motocicletă și bicicletă' : ' cu hartă') . ' | bilete.online';
$pageDescription = count($routeCards) . ' trasee prin România, de la castelele Transilvaniei la cetățile Dobrogei: '
    . $stopsTotal . ' de opriri reale, fiecare cu hartă, ordinea vizitării și navigare.'
    . ($roadCards ? ' Plus ' . count($roadCards) . ' drumuri de făcut pe două roți, de la Transfăgărășan la Clisura Dunării, cu profil de altitudine.' : '');
$canonicalUrl    = SITE_URL . '/trasee';
$ogImage         = $routeCards[0][7] ?? (SITE_URL . '/assets/images/og-default.jpg');

$breadcrumbs = [['Acasă', '/'], ['Hartă', '/harta'], ['Trasee', '/trasee']];
$structuredData = [[
    '@context' => 'https://schema.org',
    '@type' => 'CollectionPage',
    'name' => 'Trasee turistice în România',
    'description' => $pageDescription,
    'url' => $canonicalUrl,
    'inLanguage' => 'ro-RO',
], [
    '@context' => 'https://schema.org',
    '@type' => 'ItemList',
    'name' => 'Trasee',
    'numberOfItems' => count($routeCards) + count($roadCards),
    'itemListElement' => array_map(
        fn ($c, $i) => ['@type' => 'ListItem', 'position' => $i + 1, 'url' => SITE_URL . '/trasee/' . $c[0], 'name' => $c[1]],
        array_merge($routeCards, $roadCards),
        array_keys(array_merge($routeCards, $roadCards))
    ),
], [
    '@context' => 'https://schema.org',
    '@type' => 'BreadcrumbList',
    'itemListElement' => array_map(
        fn ($bc, $i) => ['@type' => 'ListItem', 'position' => $i + 1, 'name' => $bc[0], 'item' => SITE_URL . $bc[1]],
        $breadcrumbs,
        array_keys($breadcrumbs)
    ),
]];

include __DIR__ . '/includes/v2/head.php';
$v2PlaceIcons = true; // the route cards' icons (product-icons.php), printed by the header
include __DIR__ . '/includes/v2/header.php';
require __DIR__ . '/includes/v2/plan-icons.php';
?>
<main id="main" tabindex="-1">
  <section class="mph" aria-labelledby="mph-h">
    <div class="wrap mph-in">
      <div class="mph-copy">
        <nav class="crumbs" aria-label="Breadcrumb">
          <?php foreach ($breadcrumbs as $i => [$bcName, $bcUrl]): ?>
            <?php if ($i > 0): ?><span aria-hidden="true">/</span><?php endif; ?>
            <?php if ($i < count($breadcrumbs) - 1): ?><a href="<?= v2_e($bcUrl) ?>"><?= v2_e($bcName) ?></a><?php else: ?><span aria-current="page"><?= v2_e($bcName) ?></span><?php endif; ?>
          <?php endforeach; ?>
        </nav>
        <h1 id="mph-h">Trasee <em>prin România</em></h1>
        <p class="mph-lead">Itinerarii gata făcute, din locurile care sunt deja pe hartă: ordinea vizitării, distanțele între opriri și un buton care deschide tot drumul în Google Maps.</p>
      </div>
      <ul class="mph-stats">
        <li><b><?= count($routeCards) ?></b> trasee</li>
        <li><b><?= v2_e(v2_thousands($stopsTotal)) ?></b> opriri</li>
        <li><b><?= v2_e(v2_thousands($kmTotal)) ?></b> km</li>
        <?php if ($roadCards): ?><li><a href="#drumuri"><b><?= count($roadCards) ?></b> drumuri pe două roți</a></li><?php endif; ?>
      </ul>
    </div>
  </section>

  <section class="sec" aria-labelledby="rx-h">
    <div class="wrap">
      <h2 class="sr" id="rx-h">Toate traseele</h2>
      <?php require __DIR__ . '/includes/v2/route-cards.php'; ?>
    </div>
  </section>

<?php if ($roadCards): ?>
  <!-- ============================== THE ROADS ============================== -->
  <section class="sec rdx" id="drumuri" aria-labelledby="rdx-h">
    <div class="wrap">
      <div class="sec-head">
        <div>
          <h2 id="rdx-h">Drumuri de făcut pe două roți</h2>
          <p class="rdx-lead">Trecătorile și văile pe care le caută motocicliștii, plus traseele lungi de bicicletă: <?= count($roadCards) ?> drumuri, <?= v2_e(v2_thousands($roadKm)) ?> km, fiecare cu profilul de altitudine, ce vezi pe margine și un buton care îl deschide ca plan.</p>
        </div>
        <div class="rdx-tabs" role="group" aria-label="Arată drumurile pentru" id="rdx-tabs">
          <button type="button" data-rdx="all" aria-pressed="true">Toate</button>
          <button type="button" data-rdx="moto" aria-pressed="false"><?= v2_ic('pl-moto') ?>Motocicletă</button>
          <button type="button" data-rdx="bike" aria-pressed="false"><?= v2_ic('pi-bicycle') ?>Bicicletă</button>
        </div>
      </div>
      <div class="rdx-map">
        <div data-epm-root data-epm-config="<?= v2_e(json_encode([
            'cartoKey'   => defined('CARTO_API_KEY') ? CARTO_API_KEY : '',
            'urlState'   => false,
            'fixed'      => true,
            'bare'       => true,
            'routeLine'  => false,
            'title'      => 'Drumuri pe două roți',
            'routeStops' => $roadPins,
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)) ?>"></div>
        <p class="rdx-key"><span><i style="background:#C8322B"></i>motocicletă</span><span><i style="background:#2D6CCD"></i>bicicletă</span></p>
      </div>
      <?php require __DIR__ . '/includes/v2/road-cards.php'; ?>
      <p class="rp-note">Liniile sunt calculate pe drumurile din <a href="https://www.openstreetmap.org/copyright" target="_blank" rel="noopener">OpenStreetMap</a>, iar altitudinile pe un model de teren european; lungimea, altitudinea maximă și urcarea sunt măsurate pe ele, nu preluate din alte surse. Traseele lungi de bicicletă — EuroVelo 6, etapele Via Transilvanica și cele din jurul Sighișoarei — sunt preluate ca atare din OpenStreetMap, unde le-a cartografiat comunitatea (© contribuitorii OpenStreetMap, licență <a href="https://www.openstreetmap.org/copyright" target="_blank" rel="noopener">ODbL</a>); pagina fiecăruia trimite la relația din OpenStreetMap și la cei care îl marchează pe teren.</p>
    </div>
    <script type="application/json" id="rdx-lines"><?= json_encode($roadLines, JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP) ?></script>
    <script>
    /* Every road on one map, numbered like its card; the filter narrows both. */
    (function () {
      var lines = [];
      try { lines = JSON.parse(document.getElementById('rdx-lines').textContent || '[]'); } catch (e) {}
      var cards = [].slice.call(document.querySelectorAll('.rdx .rd-grid > li'));
      function decode(str) {
        var pts = [], i = 0, lat = 0, lng = 0;
        while (i < str.length) {
          for (var k = 0; k < 2; k++) {
            var res = 0, shift = 0, c;
            do { c = str.charCodeAt(i++) - 63; res |= (c & 31) << shift; shift += 5; } while (c >= 32);
            var d = (res & 1) ? ~(res >> 1) : (res >> 1);
            if (k === 0) lat += d; else lng += d;
          }
          pts.push([lat / 1e5, lng / 1e5]);
        }
        return pts;
      }
      function draw(mode) {
        var inst = window.EPMap && window.EPMap.instance;
        if (!inst || !inst.setGhosts) return;
        var out = [];
        lines.forEach(function (l) {
          if (mode !== 'all' && l.modes.indexOf(mode) === -1) return;
          if (!l.breaks || !l.breaks.length) { out.push(l); return; }
          // mapped in pieces: one line per piece, so nothing is drawn across a gap
          var pts = decode(l.g), from = 0;
          l.breaks.concat([pts.length]).forEach(function (to) { if (to - from > 1) out.push({ g: pts.slice(from, to), color: l.color }); from = to; });
        });
        inst.setGhosts(out);
      }
      window.addEventListener('load', function () {
        var inst = window.EPMap && window.EPMap.instance;
        if (!inst) return;
        draw('all');
        if (inst.onPin) inst.onPin(function (i) {
          var card = document.querySelector('.rd[data-road="' + (i + 1) + '"]');
          if (!card) return;
          card.scrollIntoView({ behavior: 'smooth', block: 'center' });
          card.classList.remove('is-flash');
          void card.offsetWidth;
          card.classList.add('is-flash');
        });
      });
      document.getElementById('rdx-tabs').addEventListener('click', function (e) {
        var b = e.target.closest('[data-rdx]');
        if (!b) return;
        var mode = b.getAttribute('data-rdx');
        [].forEach.call(this.querySelectorAll('[data-rdx]'), function (x) { x.setAttribute('aria-pressed', String(x === b)); });
        cards.forEach(function (li) { li.hidden = mode !== 'all' && (li.getAttribute('data-modes') || '').split(' ').indexOf(mode) === -1; });
        draw(mode);
      });
    })();
    </script>
  </section>
<?php endif; ?>

  <section class="sec" aria-labelledby="rx-about-h">
    <div class="wrap mp-text">
      <div class="mp-prose">
        <h2 id="rx-about-h" class="sr">Despre trasee</h2>
        <p>Un traseu e o listă de opriri în ordinea în care se leagă pe drum. Fiecare oprire e o atracție reală din catalog, cu pagina ei, iar harta arată punctele numerotate și unite printr-o linie, ca să vezi dintr-o privire cum se desfășoară drumul.</p>
        <p>Kilometrii și timpii sunt calculați pe drumurile reale, cu datele OpenStreetMap, nu în linie dreaptă — deci sunt cifrele pe care le vei vedea și la bord. Nu includ opririle, traficul și ocolirile. Butonul de navigare trimite tot traseul în Google Maps, cu opririle ca puncte intermediare.</p>
        <p>Traseele sunt sugestii, nu programe. Le poți parcurge invers, le poți rupe în două zile sau poți lua doar ce îți iese în drum. Dacă vrei să pornești de la un loc anume și să vezi ce e în jurul lui, harta întreagă e pe <a href="/harta">/harta</a>.</p>
      </div>
      <div class="mp-faq">
        <details open><summary>De unde vin opririle?</summary><p>Din catalogul de atracții al bilete.online. Fiecare oprire are pagină proprie, coordonate verificate și apare pe harta generală.</p></details>
        <details><summary>Se plătește intrarea?</summary><p>Depinde de obiectiv. O parte au acces liber, altele au bilet stabilit de administrator. Unde se vinde bilet prin bilete.online, apare pe pagina obiectivului.</p></details>
        <details><summary>Pot vedea traseul pe telefon?</summary><p>Da. Harta ocupă tot ecranul, iar lista opririlor urcă de jos. Butonul de navigare deschide traseul direct în aplicația de hărți.</p></details>
        <details><summary>Ce e un „drum pe două roți”?</summary><p>Un drum pe care lumea îl face pentru el însuși: o trecătoare, o vale, un mal de apă. Are hartă, profil de altitudine și atracțiile din catalog aflate pe margine, și se deschide în planificator ca o zi de mers pe motocicletă sau pe bicicletă.</p></details>
        <details><summary>Adăugați trasee noi?</summary><p>Da, pe măsură ce intră locuri noi în catalog. Dacă ai o propunere de traseu, scrie-ne la <?= v2_e(SUPPORT_EMAIL) ?>.</p></details>
      </div>
    </div>
  </section>
</main>
<?php include __DIR__ . '/includes/v2/footer.php'; ?>
