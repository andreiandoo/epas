<?php
/**
 * bilete.online v2: listing hub shared by /locatii, /experiente, /atractii and their /{oras}/… versions.
 *
 * The page file fetches its data and sets, besides the usual head variables ($pageTitleRaw, $pageDescription,
 * $canonicalUrl, $ogImage, $structuredData), one $hub array:
 *   kicker, title, titleEm (second line, optional), lead, stats [text], image (hero, optional), breadcrumbs,
 *   filters   [[label, [[text, href, active]]]]            links, so every filtered list has its own URL
 *   items     [[href, image, kicker, title, meta [[icon, text]], price (lei, optional), badges [text]]]
 *   heading   screen-reader title of the results
 *   page, last, pageUrl (fn int → href)
 *   empty     [title, text, [cta text, href] | null]
 * Top to bottom: hero, filters, results grid, pager.
 */

require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/nav.php';

$hubArches = '<svg class="deco-arches" viewBox="0 0 400 400" aria-hidden="true" focusable="false"><path d="M40 400V200a160 160 0 0 1 320 0v200"/><path d="M90 400V200a110 110 0 0 1 220 0v200"/><path d="M140 400V200a60 60 0 0 1 120 0v200"/></svg>';
$hubItems = $hub['items'] ?? [];
$hubPage = max(1, (int) ($hub['page'] ?? 1));
$hubLast = max(1, (int) ($hub['last'] ?? 1));

$v2Styles = array_merge(['category.css', 'hub.css'], $v2Styles ?? []);
$v2HeadExtra = !empty($hub['image']) ? '<link rel="preload" as="image" href="' . v2_e($hub['image']) . '" fetchpriority="high">' : '';
$v2HeaderOverlay = true;

include __DIR__ . '/head.php';
include __DIR__ . '/header.php';
?>
<main id="main" tabindex="-1">
  <!-- ============================== HERO ============================== -->
  <section class="kh" aria-labelledby="kh-h">
    <?= $hubArches ?>
    <svg class="kh-line draw-clip" viewBox="0 590 3240 310" aria-hidden="true" focusable="false"><use href="#drum-g"/></svg>
    <div class="kh-in">
      <div class="kh-copy">
        <nav class="crumbs" aria-label="Breadcrumb">
          <?php foreach ($hub['breadcrumbs'] as $i => $bc): ?>
            <?php if ($i > 0): ?><span aria-hidden="true">/</span><?php endif; ?>
            <?php if ($i < count($hub['breadcrumbs']) - 1): ?><a href="<?= v2_e(substr($bc['url'], strlen(SITE_URL)) ?: '/') ?>"><?= v2_e($bc['name']) ?></a><?php else: ?><span aria-current="page"><?= v2_e($bc['name']) ?></span><?php endif; ?>
          <?php endforeach; ?>
        </nav>
        <p class="kh-kicker"><i aria-hidden="true"></i><?= v2_e($hub['kicker']) ?></p>
        <h1 class="kh-h" id="kh-h"><?= v2_e($hub['title']) ?><?php if (!empty($hub['titleEm'])): ?> <em><?= v2_e($hub['titleEm']) ?></em><?php endif; ?></h1>
        <?php if (!empty($hub['lead'])): ?><p class="kh-lead"><?= v2_e($hub['lead']) ?></p><?php endif; ?>
        <?php if (!empty($hub['stats'])): ?>
        <ul class="kh-stats"><?php foreach ($hub['stats'] as $s): ?><li><?= v2_e($s) ?></li><?php endforeach; ?></ul>
        <?php endif; ?>
      </div>
      <div class="kh-media">
        <div class="kh-arch">
          <?php if (!empty($hub['image'])): ?>
          <img src="<?= v2_e($hub['image']) ?>" width="640" height="800" alt="" fetchpriority="high" decoding="async">
          <?php else: ?>
          <?= v2_fallback($hub['title']) ?>
          <?php endif; ?>
        </div>
      </div>
    </div>
  </section>
  <div id="hdr-sentinel" aria-hidden="true"></div>

  <!-- ============================== FILTERS + RESULTS ============================== -->
  <section class="kres hub-res" aria-labelledby="hub-res-h">
    <div class="wrap">
      <?php if (!empty($hub['filters'])): ?>
      <div class="hub-filters">
        <?php foreach ($hub['filters'] as [$flabel, $chips]): if (!$chips) continue; ?>
        <div class="fgroup">
          <p class="flabel"><?= v2_e($flabel) ?></p>
          <div class="fchips">
            <?php foreach ($chips as [$ctext, $chref, $cactive]): ?>
            <a class="fchip" href="<?= v2_e($chref) ?>"<?= $cactive ? ' aria-current="true"' : '' ?>><?= v2_e($ctext) ?></a>
            <?php endforeach; ?>
          </div>
        </div>
        <?php endforeach; ?>
      </div>
      <?php endif; ?>

      <h2 class="sr" id="hub-res-h"><?= v2_e($hub['heading']) ?></h2>
      <?php if ($hubItems): ?>
      <ul class="xp-grid" data-reveal>
        <?php foreach ($hubItems as $i => $it): ?>
        <li class="xp">
          <a href="<?= v2_e($it['href']) ?>">
            <span class="xp-media"><?= !empty($it['image']) ? v2_photo([$it['image'], 0, 0, '']) : v2_fallback($it['title'], $i) ?><?php if (!empty($it['badges'])): ?><span class="xp-badges"><?php foreach ($it['badges'] as $b): ?><span><?= v2_e($b) ?></span><?php endforeach; ?></span><?php endif; ?></span>
            <span class="xp-body">
              <?php if (!empty($it['kicker'])): ?><span class="xp-cat"><?= v2_e($it['kicker']) ?></span><?php endif; ?>
              <span class="xp-title"><?= v2_e($it['title']) ?></span>
              <?php if (!empty($it['meta'])): ?><span class="xp-meta"><?php foreach ($it['meta'] as [$mi, $mt]): ?><span><?= $mi ? v2_ic($mi) : '' ?><?= v2_e($mt) ?></span><?php endforeach; ?></span><?php endif; ?>
              <span class="xp-foot"><span class="xp-go">Vezi<?= v2_ic('arrow-right') ?></span><?php if (!empty($it['price'])): ?><span class="xp-price">de la<b><?= v2_e(v2_thousands((int) $it['price'])) ?> lei</b></span><?php endif; ?></span>
            </span>
          </a>
        </li>
        <?php endforeach; ?>
      </ul>
      <?php else: ?>
      <div class="k-empty">
        <h3><?= v2_e($hub['empty'][0]) ?></h3>
        <p><?= v2_e($hub['empty'][1]) ?></p>
        <?php if (!empty($hub['empty'][2])): ?><div class="k-empty-cta"><a class="btn btn-light" href="<?= v2_e($hub['empty'][2][1]) ?>"><?= v2_e($hub['empty'][2][0]) ?><?= v2_ic('arrow-right') ?></a></div><?php endif; ?>
        <svg class="k-empty-line" viewBox="0 590 3240 310" aria-hidden="true" focusable="false"><use href="#drum-g"/></svg>
      </div>
      <?php endif; ?>

      <?php if ($hubLast > 1 && $hubItems):
          $start = max(1, $hubPage - 3);
          $end = min($hubLast, $start + 6);
          $start = max(1, $end - 6);
      ?>
      <nav class="pager" aria-label="Pagini">
        <?php if ($hubPage > 1): ?><a class="pg-step" href="<?= v2_e(($hub['pageUrl'])($hubPage - 1)) ?>" rel="prev"><?= v2_ic('arrow-left') ?>Anterior</a><?php endif; ?>
        <?php for ($p = $start; $p <= $end; $p++): ?>
          <?php if ($p === $hubPage): ?><span aria-current="page"><?= $p ?></span><?php else: ?><a href="<?= v2_e(($hub['pageUrl'])($p)) ?>"><?= $p ?></a><?php endif; ?>
        <?php endfor; ?>
        <?php if ($hubPage < $hubLast): ?><a class="pg-step" href="<?= v2_e(($hub['pageUrl'])($hubPage + 1)) ?>" rel="next">Următor<?= v2_ic('arrow-right') ?></a><?php endif; ?>
      </nav>
      <?php endif; ?>
    </div>
  </section>
</main>
<?php include __DIR__ . '/footer.php'; ?>
