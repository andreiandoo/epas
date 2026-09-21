<?php
/**
 * bilete.online v2: the map destination page, shared by /harta and (next) the /harta/{tip|regiune}
 * landings. The map is the hero; everything under it is server-rendered from the small
 * assets/v2/data/atractii.summary.json so the page has real, indexable content and real internal
 * links even with JavaScript off.
 *
 * The page file sets the usual head variables plus one $mapPage array:
 *   kicker, h1, h1em (second line, optional), lead, stats [text]
 *   breadcrumbs [[name, url]]
 *   config      EPMap config (see assets/v2/js/map.js); `dialog` is forced off here
 *   summary     v2_map_summary() output
 *   grids       [[id, h, rows [[emoji, name, count, href]], unit?, more? [text, href]]]
 *   cities      rows [[slug, name, county, region, count]]  (defaults to the whole-country list)
 *   picks       rows [[slug, name, type, emoji, city, citySlug, img]]
 *   sections    which blocks to print: 'cities' and/or 'picks' (default: both)
 *   prose       [paragraph html]
 *   faq         [[question, answer]]
 */

require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/nav.php';

$mpSummary  = $mapPage['summary'] ?? [];
$mpSections = $mapPage['sections'] ?? ['cities', 'picks'];
$mpHas      = fn (string $s) => in_array($s, $mpSections, true);
$mpCities   = $mapPage['cities'] ?? ($mpSummary['cities'] ?? []);
$mpPicks    = $mapPage['picks'] ?? ($mpSummary['picks'] ?? []);
$mpFaq      = $mapPage['faq'] ?? [];

$v2Styles  = array_merge(['map.css', 'map-page.css'], $v2Styles ?? []);
$v2Scripts = array_merge($v2Scripts ?? [], ['map.js']);

include __DIR__ . '/head.php';
include __DIR__ . '/header.php';
?>
<main id="main" tabindex="-1">
  <!-- ============================== HERO ============================== -->
  <section class="mph" aria-labelledby="mph-h">
    <div class="wrap mph-in">
      <div class="mph-copy">
      <nav class="crumbs" aria-label="Breadcrumb">
        <?php foreach ($mapPage['breadcrumbs'] as $i => [$bcName, $bcUrl]): ?>
          <?php if ($i > 0): ?><span aria-hidden="true">/</span><?php endif; ?>
          <?php if ($i < count($mapPage['breadcrumbs']) - 1): ?><a href="<?= v2_e($bcUrl) ?>"><?= v2_e($bcName) ?></a><?php else: ?><span aria-current="page"><?= v2_e($bcName) ?></span><?php endif; ?>
        <?php endforeach; ?>
      </nav>
      <?php if (!empty($mapPage['kicker'])): ?><p class="kicker"><?= v2_e($mapPage['kicker']) ?></p><?php endif; ?>
      <h1 id="mph-h"><?= v2_e($mapPage['h1']) ?><?php if (!empty($mapPage['h1em'])): ?> <em><?= v2_e($mapPage['h1em']) ?></em><?php endif; ?></h1>
      <?php if (!empty($mapPage['lead'])): ?><p class="mph-lead"><?= v2_e($mapPage['lead']) ?></p><?php endif; ?>
      </div>
      <?php if (!empty($mapPage['stats'])): ?>
      <ul class="mph-stats"><?php foreach ($mapPage['stats'] as [$statNum, $statLabel]): ?><li><b><?= v2_e($statNum) ?></b><?= v2_e($statLabel) ?></li><?php endforeach; ?></ul>
      <?php endif; ?>
    </div>
  </section>

  <!-- ============================== MAP ============================== -->
  <section class="mp-band" aria-label="Hartă interactivă">
    <div class="wrap">
      <div class="mp-frame">
        <div data-epm-root data-epm-config="<?= v2_e(json_encode(['dialog' => false] + $mapPage['config'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)) ?>"></div>
      </div>
      <noscript>
        <p class="mp-noscript">Harta are nevoie de JavaScript. Lista completă a atracțiilor este pe <a href="/atractii">pagina de atracții</a>, filtrabilă după tip și oraș.</p>
      </noscript>
    </div>
  </section>

  <?php foreach ($mapPage['grids'] ?? [] as $grid): if (empty($grid['rows'])) continue; ?>
  <!-- ============================== GRID: <?= v2_e($grid['id']) ?> ============================== -->
  <section class="sec" aria-labelledby="mp-<?= v2_e($grid['id']) ?>-h">
    <div class="wrap">
      <div class="sec-head">
        <h2 id="mp-<?= v2_e($grid['id']) ?>-h"><?= v2_e($grid['h']) ?></h2>
        <?php if (!empty($grid['more'])): ?><a class="sec-link" href="<?= v2_e($grid['more'][1]) ?>"><?= v2_e($grid['more'][0]) ?><?= v2_ic('arrow-right') ?></a><?php endif; ?>
      </div>
      <ul class="mp-grid">
        <?php foreach ($grid['rows'] as [$gEmoji, $gName, $gCount, $gHref]): ?>
        <li>
          <a class="mp-card" href="<?= v2_e($gHref) ?>">
            <span class="mp-card-emoji" aria-hidden="true"><?= v2_e($gEmoji ?: '📍') ?></span>
            <span class="mp-card-t"><b><?= v2_e($gName) ?></b><span><?= v2_e(v2_thousands((int) $gCount)) ?> <?= v2_e($grid['unit'] ?? 'pe hartă') ?></span></span>
          </a>
        </li>
        <?php endforeach; ?>
      </ul>
    </div>
  </section>
  <?php endforeach; ?>

  <?php if ($mpHas('cities') && $mpCities): ?>
  <!-- ============================== CITIES ============================== -->
  <section class="sec" aria-labelledby="mp-city-h">
    <div class="wrap">
      <div class="sec-head">
        <h2 id="mp-city-h"><?= v2_e($mapPage['citiesHeading'] ?? 'Orașele cu cele mai multe atracții') ?></h2>
        <a class="sec-link" href="/orase">Toate orașele<?= v2_ic('arrow-right') ?></a>
      </div>
      <ul class="mp-chips">
        <?php foreach ($mpCities as [$cSlug, $cName, $cCounty, $cRegion, $cCount]): ?>
        <li><a class="mp-chip" href="/<?= v2_e($cSlug) ?>/atractii"><?= v2_e($cName) ?><b><?= v2_e(v2_thousands((int) $cCount)) ?></b></a></li>
        <?php endforeach; ?>
      </ul>
    </div>
  </section>
  <?php endif; ?>

  <?php if ($mpHas('picks') && $mpPicks): ?>
  <!-- ============================== PICKS ============================== -->
  <section class="sec" aria-labelledby="mp-picks-h">
    <div class="wrap">
      <div class="sec-head">
        <h2 id="mp-picks-h"><?= v2_e($mapPage['picksHeading'] ?? 'Locuri de deschis pe hartă') ?></h2>
        <a class="sec-link" href="/atractii">Vezi lista completă<?= v2_ic('arrow-right') ?></a>
      </div>
      <ul class="mp-picks">
        <?php foreach ($mpPicks as [$pSlug, $pName, $pType, $pEmoji, $pCity, $pCitySlug, $pImg]): ?>
        <li>
          <a class="mp-pick" href="/atractie/<?= v2_e($pSlug) ?>">
            <span class="mp-pick-media">
              <?php if ($pImg): ?><img src="<?= v2_e($pImg) ?>" alt="" width="320" height="240" loading="lazy" decoding="async"><?php else: ?><?= v2_fallback($pName) ?><?php endif; ?>
              <?php if ($pType): ?><span class="mp-pick-tag"><span aria-hidden="true"><?= v2_e($pEmoji) ?></span><?= v2_e($pType) ?></span><?php endif; ?>
            </span>
            <span class="mp-pick-b"><b><?= v2_e($pName) ?></b><?php if ($pCity): ?><span><?= v2_ic('map-pin') ?><?= v2_e($pCity) ?></span><?php endif; ?></span>
          </a>
        </li>
        <?php endforeach; ?>
      </ul>
    </div>
  </section>
  <?php endif; ?>

  <?php if (!empty($mapPage['prose']) || $mpFaq): ?>
  <!-- ============================== TEXT + FAQ ============================== -->
  <section class="sec" aria-labelledby="mp-text-h">
    <div class="wrap mp-text">
      <div class="mp-prose">
        <h2 id="mp-text-h" class="sr">Despre harta atracțiilor</h2>
        <?= implode("\n", $mapPage['prose'] ?? []) ?>
      </div>
      <?php if ($mpFaq): ?>
      <div class="mp-faq">
        <?php foreach ($mpFaq as $i => [$fq, $fa]): ?>
        <details<?= $i === 0 ? ' open' : '' ?>><summary><?= v2_e($fq) ?></summary><p><?= v2_e($fa) ?></p></details>
        <?php endforeach; ?>
      </div>
      <?php endif; ?>
    </div>
  </section>
  <?php endif; ?>
</main>
<?php include __DIR__ . '/footer.php'; ?>
