<?php
/**
 * bilete.online v2 header: brand sprite, header with mega menu, mobile menu.
 *
 * Expects $V2NAV from v2/nav.php. Page variables:
 *   $v2HeaderOverlay  true only on pages with a dark hero (homepage): the header starts transparent
 *                     and turns solid once #hdr-sentinel scrolls under it. Otherwise it is solid.
 *   $v2BodyClass      extra classes for <body>
 */
$v2Overlay = !empty($v2HeaderOverlay);
$v2Ideas = [
    ['sun', 'Idei de weekend', '/activitati-weekend'], ['users-three', 'Activități cu copiii', '/activitati-copii'],
    ['cloud-rain', 'Indoor când plouă', '/activitati-zile-ploioase'], ['coins', 'Sub 50 lei', '/activitati-sub-50-lei'],
    ['heart', 'Pentru cupluri', '/activitati-cupluri'], ['gift', 'Experiențe cadou', '/card-cadou'],
];
$v2IntentCities = array_values(array_filter(array_map(function ($s) use ($V2NAV) {
    return $V2NAV['cities'][$s] ?? null;
}, ['brasov', 'sibiu', 'cluj-napoca', 'bucuresti', 'constanta', 'sinaia'])));
?>
<body<?= !empty($v2BodyClass) ? ' class="' . v2_e($v2BodyClass) . '"' : '' ?>>
<?php readfile(__DIR__ . '/sprite.svg'); ?>

<a class="skip" href="#main">Sari la conținut</a>

<header class="hdr<?= $v2Overlay ? '' : ' is-solid' ?>" id="hdr">
  <div class="hdr-in">
    <a class="brand" href="/" aria-label="bilete.online, pagina principală">
      <svg class="s" viewBox="24 33 148 205" aria-hidden="true"><use href="#sym-g"/></svg>
      <svg class="w" viewBox="52 68 514 74" aria-hidden="true"><use href="#logo-g"/></svg>
    </a>
    <nav class="mnav" aria-label="Principal">
      <button class="mnav-btn" type="button" data-mega="explore" aria-expanded="false" aria-controls="mega-explore">Explorează<?= v2_ic('caret-down') ?></button>
      <button class="mnav-btn" type="button" data-mega="activities" aria-expanded="false" aria-controls="mega-activities">Activități<?= v2_ic('caret-down') ?></button>
      <button class="mnav-btn" type="button" data-mega="inspiration" aria-expanded="false" aria-controls="mega-inspiration">Inspirație<?= v2_ic('caret-down') ?></button>
      <a class="mnav-link" href="/card-cadou">Card cadou</a>
    </nav>
    <form class="hdr-search" role="search" action="/cauta" method="get">
      <?= v2_ic('magnifying-glass') ?>
      <label class="sr" for="hdr-q">Caută pe bilete.online</label>
      <input id="hdr-q" name="q" type="search" placeholder="Caută o atracție sau un oraș" autocomplete="off">
      <button type="submit" aria-label="Caută"><?= v2_ic('arrow-right') ?></button>
    </form>
    <div class="hdr-tools">
      <a class="icon-btn" href="/cos" aria-label="Coșul de cumpărături"><?= v2_ic('shopping-cart-simple') ?></a>
      <a class="icon-btn" href="/cont" aria-label="Contul meu"><?= v2_ic('user-circle') ?></a>
      <button class="icon-btn menu-btn" id="menu-btn" type="button" aria-expanded="false" aria-controls="menu" aria-label="Deschide meniul"><?= v2_ic('list') ?></button>
    </div>
  </div>

  <div class="mega mega-explore" id="mega-explore" data-lenis-prevent hidden>
    <div class="mega-in">
      <div>
        <p class="mega-label">Regiuni</p>
        <div class="mega-tabs" role="tablist" aria-orientation="vertical" aria-label="Regiuni" data-tabs data-hover>
          <?php foreach ($V2NAV['regions'] as $i => $r): ?>
          <button class="mega-tab" type="button" role="tab" id="mxt-<?= $i ?>" aria-controls="mx-<?= $i ?>" aria-selected="<?= $i === 0 ? 'true' : 'false' ?>" tabindex="<?= $i === 0 ? 0 : -1 ?>"><span><?= v2_e($r['name']) ?></span><small><?= v2_num($r['citiesCount'], 'oraș', 'orașe') ?></small></button>
          <?php endforeach; ?>
        </div>
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
            <li><a class="mcity" href="<?= v2_e($c['href']) ?>"><span class="mcity-media"><?= $c['photo'] ? v2_photo([$c['photo'][0], 0, 0, '']) : v2_fallback($c['name']) ?></span><span><b><?= v2_e($c['name']) ?></b><small><?= $c['count'] ? v2_exp($c['count']) : 'Descoperă orașul' ?></small></span></a></li>
            <?php endforeach; ?>
          </ul>
          <?php endif; ?>
          <?php if ($links): ?>
          <p class="mega-label">Alte orașe din <?= v2_e($r['name']) ?></p>
          <ul class="mega-links">
            <?php foreach ($links as $l): ?><li><a href="<?= v2_e($l['href']) ?>"><?= v2_e($l['name']) ?></a></li><?php endforeach; ?>
          </ul>
          <?php endif; ?>
          <a class="mega-all" href="/orase">Toate cele <?= v2_num($r['citiesCount'], 'oraș', 'orașe') ?> din <?= v2_e($r['name']) ?><?= v2_ic('arrow-right') ?></a>
        </div>
        <?php endforeach; ?>
      </div>
      <aside class="mega-aside" aria-label="Idei rapide">
        <p class="mega-label">Idei rapide</p>
        <ul class="ideas">
          <?php foreach ($v2Ideas as [$icon, $text, $href]): ?>
          <li><a class="idea" href="<?= $href ?>"><?= v2_ic($icon) ?><?= v2_e($text) ?></a></li>
          <?php endforeach; ?>
        </ul>
      </aside>
    </div>
  </div>

  <div class="mega mega-act" id="mega-activities" data-lenis-prevent hidden>
    <div class="mega-in">
      <div>
        <p class="mega-label">Categorii</p>
        <div class="mega-tabs" role="tablist" aria-orientation="vertical" aria-label="Categorii" data-tabs data-hover>
          <?php foreach ($V2NAV['categories'] as $i => $c): ?>
          <button class="mega-tab" type="button" role="tab" id="mat-<?= v2_e($c['slug']) ?>" aria-controls="ma-<?= v2_e($c['slug']) ?>" aria-selected="<?= $i === 0 ? 'true' : 'false' ?>" tabindex="<?= $i === 0 ? 0 : -1 ?>"><span class="tt"><span class="thumb"><?= $c['thumb'] ? v2_photo([$c['thumb'], 0, 0, '']) : '' ?></span><span><?= v2_e($c['name']) ?></span></span></button>
          <?php endforeach; ?>
        </div>
      </div>
      <div>
        <?php foreach ($V2NAV['categories'] as $i => $c): ?>
        <div class="mega-panel" id="ma-<?= v2_e($c['slug']) ?>" role="tabpanel" aria-labelledby="mat-<?= v2_e($c['slug']) ?>"<?= $i ? ' hidden' : '' ?>>
          <div class="ma-grid">
            <div>
              <p class="mega-h"><?= v2_e($c['name']) ?></p>
              <?php if ($c['desc']): ?><p class="ma-desc"><?= v2_e($c['desc']) ?></p><?php endif; ?>
              <?php if ($c['subs']): ?>
              <ul class="mega-links two">
                <?php foreach ($c['subs'] as $s): ?><li><a href="<?= v2_e($s['href']) ?>"><?= v2_e($s['name']) ?></a></li><?php endforeach; ?>
              </ul>
              <?php endif; ?>
              <a class="mega-all" href="<?= v2_e($c['href']) ?>"><?= $c['count'] ? 'Vezi toate cele ' . v2_exp($c['count']) : 'Vezi categoria' ?><?= v2_ic('arrow-right') ?></a>
            </div>
            <a class="mega-feature" href="<?= v2_e($c['href']) ?>" aria-label="<?= v2_e($c['name']) ?>"><span class="mf-media"><?= $c['thumb'] ? v2_photo([$c['thumb'], 0, 0, '']) : v2_fallback($c['name']) ?></span></a>
          </div>
        </div>
        <?php endforeach; ?>
      </div>
    </div>
  </div>

  <div class="mega mega-insp" id="mega-inspiration" data-lenis-prevent hidden>
    <div class="mega-in">
      <div>
        <p class="mega-label">Ghiduri</p>
        <ul class="guides">
          <?php foreach ($V2NAV['guides'] as $g): ?>
          <li><a class="guide" href="<?= v2_e($g['href']) ?>"><span class="guide-media"><?= $g['thumb'] ? v2_photo([$g['thumb'], 160, 160, '']) : v2_fallback($g['slug']) ?></span><span class="guide-text"><b><?= v2_e($g['title']) ?></b><small><?= v2_e(trim($g['category'] . ($g['readTime'] ? ', ' . $g['readTime'] . ' min de citit' : ''), ', ')) ?></small></span></a></li>
          <?php endforeach; ?>
        </ul>
        <a class="mega-all" href="/ghiduri">Toate ghidurile<?= v2_ic('arrow-right') ?></a>
      </div>
      <div class="intents">
        <div class="intent"><p class="mega-label">Weekend în</p><div class="chips-links"><?php foreach ($v2IntentCities as $c): ?><a href="/<?= v2_e($c['slug']) ?>/activitati-weekend"><?= v2_e($c['name']) ?></a><?php endforeach; ?></div></div>
        <div class="intent"><p class="mega-label">Cu copiii în</p><div class="chips-links"><?php foreach ($v2IntentCities as $c): ?><a href="/<?= v2_e($c['slug']) ?>/activitati-copii"><?= v2_e($c['name']) ?></a><?php endforeach; ?></div></div>
      </div>
      <a class="mini-gift" href="/card-cadou">
        <?= v2_brand('gc-brand') ?>
        <span class="mg-kind">Card cadou</span>
        <span class="mg-text">Alegi suma, ei aleg experiența. Valabil 12 luni, la orice locație.</span>
        <span class="mg-cta">Vezi cardul cadou<?= v2_ic('arrow-right') ?></span>
        <svg class="mg-line" viewBox="900 585 2340 310" aria-hidden="true"><use href="#drum-g"/></svg>
      </a>
    </div>
  </div>
</header>

<nav class="drawer" id="menu" aria-label="Meniu mobil" data-lenis-prevent>
  <details class="dr-sec"><summary>Explorează<?= v2_ic('caret-down') ?></summary>
    <ul class="dr-links">
      <?php foreach (array_slice($V2NAV['citiesList'], 0, 12) as $c): ?><li><a href="<?= v2_e($c['href']) ?>"><?= v2_e($c['name']) ?></a></li><?php endforeach; ?>
      <li><a class="dr-all" href="/orase">Toate orașele</a></li>
    </ul>
  </details>
  <details class="dr-sec"><summary>Activități<?= v2_ic('caret-down') ?></summary>
    <ul class="dr-links one">
      <?php foreach ($V2NAV['categories'] as $c): ?><li><a href="<?= v2_e($c['href']) ?>"><?= v2_e($c['name']) ?></a></li><?php endforeach; ?>
      <li><a class="dr-all" href="/categorii">Toate categoriile</a></li>
    </ul>
  </details>
  <details class="dr-sec"><summary>Inspirație<?= v2_ic('caret-down') ?></summary>
    <ul class="dr-links one">
      <?php foreach ($V2NAV['guides'] as $g): ?><li><a href="<?= v2_e($g['href']) ?>"><?= v2_e($g['title']) ?></a></li><?php endforeach; ?>
      <li><a class="dr-all" href="/ghiduri">Toate ghidurile</a></li>
    </ul>
  </details>
  <a class="dr-link" href="/card-cadou">Card cadou</a>
  <div class="dr-foot">
    <a class="btn btn-primary" href="/cont"><?= v2_ic('user-circle') ?>Contul meu</a>
    <a class="btn btn-ghost" href="/recuperare-comanda">Recuperează comanda</a>
    <p>Suport: <?= v2_e(SUPPORT_EMAIL) ?>, luni - vineri, 09:00 - 18:00</p>
  </div>
</nav>
