<?php
/**
 * bilete.online v2: the map destination page, shared by /harta and the sixteen /harta/{slug}
 * landings.
 *
 * The map is the page. Everything that used to be a section below it — types, historical regions,
 * counties, cities — is now an *explorer* docked to the top of the map frame: three or four
 * triggers that open a panel and filter the map in place. Every entry in those panels is still a
 * real <a href>, so the page keeps the internal links and the crawlable content it had as a stack
 * of grids; JavaScript only intercepts the click and turns it into a filter. With JavaScript off
 * the panels are simply open.
 *
 * Under the map there is one dark band with what is genuinely editorial — the ready-made routes
 * and a rail of places worth opening — and then the prose and the FAQ.
 *
 * The page file sets the usual head variables plus one $mapPage array:
 *   kicker, h1, h1em (second line, optional), lead, stats [[number, label]], total
 *   breadcrumbs [[name, url]]
 *   config      EPMap config (see assets/v2/js/map.js); `dialog` is forced off here
 *   summary     v2_map_summary() output
 *   explorer    [[id, icon, label, sub, kind, rows, unit?, note?, presets?, more?, search?]]
 *               kind: type (multi-select) | region | zone | city — what a click sets on the map
 *               rows: [[key, emoji, name, count, href]]
 *               more: [text, href] — a link printed in the panel footer
 *   picks       rows [[slug, name, type, emoji, city, citySlug, img]]
 *   routeCards  see route-cards.php
 *   prose       [paragraph html]
 *   faq         [[question, answer]]
 */

require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/nav.php';

$mpSummary  = $mapPage['summary'] ?? [];
$mpPicks    = $mapPage['picks'] ?? ($mpSummary['picks'] ?? []);
$mpFaq      = $mapPage['faq'] ?? [];
$mpExplorer = array_values(array_filter($mapPage['explorer'] ?? [], fn ($p) => !empty($p['rows'])));

// What the map starts on, so the explorer knows when to offer "Resetează".
$mpCfg  = ['dialog' => false] + $mapPage['config'];
$mpBase = [
    'types'  => array_values($mpCfg['types'] ?? []),
    'region' => (string) ($mpCfg['region'] ?? ''),
    'zone'   => (string) ($mpCfg['zone'] ?? ''),
    'city'   => (string) ($mpCfg['city'] ?? ''),
    'preset' => empty($mpCfg['types']) ? (string) ($mpCfg['preset'] ?? '') : '',
];

$v2Styles  = array_merge(['map.css', 'map-page.css'], !empty($mapPage['routeCards']) ? ['routes.css'] : [], $v2Styles ?? []);
$v2Scripts = array_merge($v2Scripts ?? [], ['map.js', 'map-explorer.js']);

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

  <!-- ============================== MAP + EXPLORER ============================== -->
  <section class="mp-band" aria-label="Hartă interactivă">
    <div class="wrap">
      <div class="mp-shell">
        <?php if ($mpExplorer): ?>
        <div class="mpx" data-mpx-root data-mpx-base="<?= v2_e(json_encode($mpBase, JSON_UNESCAPED_UNICODE)) ?>">
          <div class="mpx-bar">
            <div class="mpx-tabs">
              <?php foreach ($mpExplorer as $px): ?>
              <button type="button" class="mpx-tab" data-mpx-tab="<?= v2_e($px['id']) ?>" data-mpx-sub="<?= v2_e($px['sub']) ?>" aria-expanded="false" aria-controls="mpx-p-<?= v2_e($px['id']) ?>">
                <span class="mpx-tab-ic"><?= v2_ic($px['icon']) ?></span>
                <span class="mpx-tab-t"><b><?= v2_e($px['label']) ?></b><span data-mpx-tabsub><?= v2_e($px['sub']) ?></span></span>
                <?= v2_ic('caret-down', 'ic mpx-tab-chev') ?>
              </button>
              <?php endforeach; ?>
            </div>
            <div class="mpx-state">
              <p class="mpx-count" data-mpx-count aria-live="polite"><b><?= v2_e(v2_thousands((int) ($mapPage['total'] ?? 0))) ?></b> <span>locuri pe hartă</span></p>
              <button type="button" class="mpx-reset" data-mpx-reset hidden><?= v2_ic('x') ?>Resetează</button>
            </div>
          </div>

          <?php foreach ($mpExplorer as $px): $pxTiles = in_array($px['kind'], ['type', 'region'], true); ?>
          <div class="mpx-panel" id="mpx-p-<?= v2_e($px['id']) ?>" data-mpx-panel="<?= v2_e($px['id']) ?>" data-mpx-kind="<?= v2_e($px['kind']) ?>" hidden>
            <?php if (!empty($px['note']) || !empty($px['presets']) || !empty($px['search'])): ?>
            <div class="mpx-top">
              <?php if (!empty($px['note'])): ?><p class="mpx-note"><?= v2_e($px['note']) ?></p><?php endif; ?>
              <div class="mpx-top-end">
                <?php if (!empty($px['presets'])): ?>
                <span class="mpx-presets">
                  <button type="button" class="mpx-preset" data-mpx-set="preset" data-mpx-key="popular">Doar populare</button>
                  <button type="button" class="mpx-preset" data-mpx-set="preset" data-mpx-key="all">Toate tipurile</button>
                </span>
                <?php endif; ?>
                <?php if (!empty($px['search'])): ?>
                <label class="mpx-find"><?= v2_ic('magnifying-glass') ?><span class="sr">Caută în listă</span><input type="search" data-mpx-find="<?= v2_e($px['id']) ?>" placeholder="<?= v2_e($px['search']) ?>" autocomplete="off" spellcheck="false"></label>
                <?php endif; ?>
              </div>
            </div>
            <?php endif; ?>

            <ul class="<?= $pxTiles ? 'mpx-tiles' : 'mpx-pills' ?>">
              <?php foreach ($px['rows'] as [$rKey, $rEmoji, $rName, $rCount, $rHref]): ?>
              <li>
                <a class="<?= $pxTiles ? 'mpx-tile' : 'mpx-pill' ?>" href="<?= v2_e($rHref) ?>"
                   data-mpx-set="<?= v2_e($px['kind']) ?>" data-mpx-key="<?= v2_e($rKey) ?>" data-mpx-label="<?= v2_e($rName) ?>" aria-pressed="false">
                  <?php if ($pxTiles): ?>
                  <span class="mpx-tile-ic" aria-hidden="true"><?= v2_e($rEmoji ?: '📍') ?></span>
                  <span class="mpx-tile-t"><b><?= v2_e($rName) ?></b><span><?= v2_e(v2_thousands((int) $rCount)) ?> <?= v2_e($px['unit'] ?? 'locuri') ?></span></span>
                  <span class="mpx-tile-on" aria-hidden="true"><?= v2_ic('check') ?></span>
                  <?php else: ?>
                  <span><?= v2_e($rName) ?></span><b><?= v2_e(v2_thousands((int) $rCount)) ?></b>
                  <?php endif; ?>
                </a>
              </li>
              <?php endforeach; ?>
            </ul>

            <p class="mpx-empty" data-mpx-empty hidden>Nimic cu numele ăsta în listă.</p>
            <?php if (!empty($px['more'])): ?>
            <p class="mpx-more"><a href="<?= v2_e($px['more'][1]) ?>"><?= v2_e($px['more'][0]) ?><?= v2_ic('arrow-right') ?></a></p>
            <?php endif; ?>
          </div>
          <?php endforeach; ?>
        </div>
        <noscript><style>.mpx-panel{display:block!important}.mpx-tab-chev,.mpx-state{display:none}</style></noscript>
        <?php endif; ?>

        <div class="mp-frame">
          <div data-epm-root data-epm-config="<?= v2_e(json_encode($mpCfg, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)) ?>"></div>
        </div>
      </div>
      <noscript>
        <p class="mp-noscript">Harta are nevoie de JavaScript. Lista completă a atracțiilor este pe <a href="/atractii">pagina de atracții</a>, filtrabilă după tip și oraș.</p>
      </noscript>
    </div>
  </section>

  <?php if ($mpPicks || !empty($mapPage['routeCards'])): ?>
  <!-- ============================== IDEAS (dark band) ============================== -->
  <section class="pt-sec pt-dark mpd" aria-labelledby="mpd-h">
    <div class="wrap">
      <div class="pt-head">
        <p class="pt-k"><?= v2_ic('target') ?><?= v2_e($mapPage['ideasKicker'] ?? 'Idei de plecare') ?></p>
        <h2 id="mpd-h"><?= v2_e($mapPage['ideasHeading'] ?? 'Nu știi de unde să începi?') ?></h2>
        <p class="pt-sub"><?= v2_e($mapPage['ideasLead'] ?? 'Harta le arată pe toate deodată. Astea sunt drumurile deja făcute și locurile pe care le-am deschide noi primele.') ?></p>
      </div>

      <?php if (!empty($mapPage['routeCards'])): ?>
      <div class="mpd-block">
        <div class="mpd-h">
          <h3><?= v2_e($mapPage['routesHeading'] ?? 'Trasee gata făcute') ?></h3>
          <div class="mpd-h-end"><a class="mpd-link" href="/trasee">Toate traseele<?= v2_ic('arrow-right') ?></a></div>
        </div>
        <?php $routeCards = $mapPage['routeCards']; require __DIR__ . '/route-cards.php'; ?>
      </div>
      <?php endif; ?>

      <?php if ($mpPicks): ?>
      <div class="mpd-block">
        <div class="mpd-h">
          <h3><?= v2_e($mapPage['picksHeading'] ?? 'Locuri de deschis pe hartă') ?></h3>
          <div class="mpd-h-end">
            <a class="mpd-link" href="/atractii">Vezi lista completă<?= v2_ic('arrow-right') ?></a>
            <span class="mpd-arrows" data-mpr-arrows hidden>
              <button type="button" class="mpd-arrow" data-mpr-prev aria-label="Derulează înapoi"><?= v2_ic('arrow-left') ?></button>
              <button type="button" class="mpd-arrow" data-mpr-next aria-label="Derulează înainte"><?= v2_ic('arrow-right') ?></button>
            </span>
          </div>
        </div>
        <ul class="mpr" data-mpr>
          <?php foreach ($mpPicks as [$pSlug, $pName, $pType, $pEmoji, $pCity, $pCitySlug, $pImg]): ?>
          <li>
            <a class="mp-pick" href="/atractie/<?= v2_e($pSlug) ?>">
              <span class="mp-pick-media">
                <?php if ($pImg): ?><img src="<?= v2_e(v2_thumb($pImg, 320, 240)) ?>" alt="" width="320" height="240" loading="lazy" decoding="async"><?php else: ?><?= v2_fallback($pName) ?><?php endif; ?>
                <?php if ($pType): ?><span class="mp-pick-tag"><span aria-hidden="true"><?= v2_e($pEmoji) ?></span><?= v2_e($pType) ?></span><?php endif; ?>
              </span>
              <span class="mp-pick-b"><b><?= v2_e($pName) ?></b><?php if ($pCity): ?><span><?= v2_ic('map-pin') ?><?= v2_e($pCity) ?></span><?php endif; ?></span>
            </a>
          </li>
          <?php endforeach; ?>
        </ul>
      </div>
      <?php endif; ?>

      <?php if (!empty($mapPage['ideasCta'])): ?>
      <p class="mpd-cta"><a class="btn btn-online" href="<?= v2_e($mapPage['ideasCta'][1]) ?>"><?= v2_e($mapPage['ideasCta'][0]) ?><?= v2_ic('arrow-right') ?></a></p>
      <?php endif; ?>
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
