<?php
/**
 * bilete.online — homepage v2 header: brand sprite, header with mega menu, mobile drawer.
 * Expects $HV2 from home-v2/data.php.
 */
$hv2Ideas = [
    ['sun', 'Idei de weekend', '/activitati-weekend'], ['users-three', 'Activități cu copiii', '/activitati-copii'],
    ['cloud-rain', 'Indoor când plouă', '/activitati-zile-ploioase'], ['coins', 'Sub 50 lei', '/activitati-sub-50-lei'],
    ['heart', 'Pentru cupluri', '/activitati-cupluri'], ['gift', 'Experiențe cadou', '/card-cadou'],
];
$hv2IntentCities = array_values(array_filter(array_map(function ($s) use ($HV2) {
    return $HV2['cities'][$s] ?? null;
}, ['brasov', 'sibiu', 'cluj-napoca', 'bucuresti', 'constanta', 'sinaia'])));
?>
<body>
<?php readfile(__DIR__ . '/sprite.svg'); ?>

<a class="skip" href="#main">Sari la conținut</a>

<header class="hdr" id="hdr">
  <div class="hdr-in">
    <a class="brand" href="/" aria-label="bilete.online, pagina principală">
      <svg class="s" viewBox="24 33 148 205" aria-hidden="true"><use href="#sym-g"/></svg>
      <svg class="w" viewBox="52 68 514 74" aria-hidden="true"><use href="#logo-g"/></svg>
    </a>
    <nav class="mnav" aria-label="Principal">
      <button class="mnav-btn" type="button" data-mega="explore" aria-expanded="false" aria-controls="mega-explore">Explorează<?= hv2_ic('caret-down') ?></button>
      <button class="mnav-btn" type="button" data-mega="activities" aria-expanded="false" aria-controls="mega-activities">Activități<?= hv2_ic('caret-down') ?></button>
      <button class="mnav-btn" type="button" data-mega="inspiration" aria-expanded="false" aria-controls="mega-inspiration">Inspirație<?= hv2_ic('caret-down') ?></button>
      <a class="mnav-link" href="/card-cadou">Card cadou</a>
    </nav>
    <form class="hdr-search" role="search" action="/cauta" method="get">
      <?= hv2_ic('magnifying-glass') ?>
      <label class="sr" for="hdr-q">Caută pe bilete.online</label>
      <input id="hdr-q" name="q" type="search" placeholder="Caută o atracție sau un oraș" autocomplete="off">
      <button type="submit" aria-label="Caută"><?= hv2_ic('arrow-right') ?></button>
    </form>
    <div class="hdr-tools">
      <a class="icon-btn" href="/cos" aria-label="Coșul de cumpărături"><?= hv2_ic('shopping-cart-simple') ?></a>
      <a class="icon-btn" href="/cont" aria-label="Contul meu"><?= hv2_ic('user-circle') ?></a>
      <button class="icon-btn menu-btn" id="menu-btn" type="button" aria-expanded="false" aria-controls="menu" aria-label="Deschide meniul"><?= hv2_ic('list') ?></button>
    </div>
  </div>

  <div class="mega mega-explore" id="mega-explore" data-lenis-prevent hidden>
    <div class="mega-in">
      <div>
        <p class="mega-label">Regiuni</p>
        <div class="mega-tabs" role="tablist" aria-orientation="vertical" aria-label="Regiuni" data-tabs data-hover>
          <?php foreach ($HV2['regions'] as $i => $r): ?>
          <button class="mega-tab" type="button" role="tab" id="mxt-<?= $i ?>" aria-controls="mx-<?= $i ?>" aria-selected="<?= $i === 0 ? 'true' : 'false' ?>" tabindex="<?= $i === 0 ? 0 : -1 ?>"><span><?= hv2_e($r['name']) ?></span><small><?= hv2_num($r['citiesCount'], 'oraș', 'orașe') ?></small></button>
          <?php endforeach; ?>
        </div>
      </div>
      <div>
        <?php foreach ($HV2['regions'] as $i => $r):
            $tiles = array_slice($r['featured'], 0, 4);
            $links = array_slice(array_merge(array_map(function ($c) {
                return ['name' => $c['name'], 'href' => $c['href']];
            }, array_slice($r['featured'], 4)), $r['more']), 0, 15);
        ?>
        <div class="mega-panel" id="mx-<?= $i ?>" role="tabpanel" aria-labelledby="mxt-<?= $i ?>"<?= $i ? ' hidden' : '' ?>>
          <p class="mega-h"><?= hv2_e($r['name']) ?></p>
          <?php if ($tiles): ?>
          <ul class="mega-cities">
            <?php foreach ($tiles as $c): ?>
            <li><a class="mcity" href="<?= hv2_e($c['href']) ?>"><span class="mcity-media"><?= $c['photo'] ? hv2_photo([$c['photo'][0], 0, 0, '']) : hv2_fallback($c['name']) ?></span><span><b><?= hv2_e($c['name']) ?></b><small><?= $c['count'] ? hv2_exp($c['count']) : 'Descoperă orașul' ?></small></span></a></li>
            <?php endforeach; ?>
          </ul>
          <?php endif; ?>
          <?php if ($links): ?>
          <p class="mega-label">Alte orașe din <?= hv2_e($r['name']) ?></p>
          <ul class="mega-links">
            <?php foreach ($links as $l): ?><li><a href="<?= hv2_e($l['href']) ?>"><?= hv2_e($l['name']) ?></a></li><?php endforeach; ?>
          </ul>
          <?php endif; ?>
          <a class="mega-all" href="/orase">Toate cele <?= hv2_num($r['citiesCount'], 'oraș', 'orașe') ?> din <?= hv2_e($r['name']) ?><?= hv2_ic('arrow-right') ?></a>
        </div>
        <?php endforeach; ?>
      </div>
      <aside class="mega-aside" aria-label="Idei rapide">
        <p class="mega-label">Idei rapide</p>
        <ul class="ideas">
          <?php foreach ($hv2Ideas as [$icon, $text, $href]): ?>
          <li><a class="idea" href="<?= $href ?>"><?= hv2_ic($icon) ?><?= hv2_e($text) ?></a></li>
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
          <?php foreach ($HV2['categories'] as $i => $c): ?>
          <button class="mega-tab" type="button" role="tab" id="mat-<?= hv2_e($c['slug']) ?>" aria-controls="ma-<?= hv2_e($c['slug']) ?>" aria-selected="<?= $i === 0 ? 'true' : 'false' ?>" tabindex="<?= $i === 0 ? 0 : -1 ?>"><span class="tt"><span class="thumb"><?= $c['thumb'] ? hv2_photo([$c['thumb'], 0, 0, '']) : '' ?></span><span><?= hv2_e($c['name']) ?></span></span></button>
          <?php endforeach; ?>
        </div>
      </div>
      <div>
        <?php foreach ($HV2['categories'] as $i => $c): ?>
        <div class="mega-panel" id="ma-<?= hv2_e($c['slug']) ?>" role="tabpanel" aria-labelledby="mat-<?= hv2_e($c['slug']) ?>"<?= $i ? ' hidden' : '' ?>>
          <div class="ma-grid">
            <div>
              <p class="mega-h"><?= hv2_e($c['name']) ?></p>
              <?php if ($c['desc']): ?><p class="ma-desc"><?= hv2_e($c['desc']) ?></p><?php endif; ?>
              <?php if ($c['subs']): ?>
              <ul class="mega-links two">
                <?php foreach ($c['subs'] as $s): ?><li><a href="<?= hv2_e($s['href']) ?>"><?= hv2_e($s['name']) ?></a></li><?php endforeach; ?>
              </ul>
              <?php endif; ?>
              <a class="mega-all" href="<?= hv2_e($c['href']) ?>"><?= $c['count'] ? 'Vezi toate cele ' . hv2_exp($c['count']) : 'Vezi categoria' ?><?= hv2_ic('arrow-right') ?></a>
            </div>
            <a class="mega-feature" href="<?= hv2_e($c['href']) ?>" aria-label="<?= hv2_e($c['name']) ?>"><span class="mf-media"><?= $c['thumb'] ? hv2_photo([$c['thumb'], 0, 0, '']) : hv2_fallback($c['name']) ?></span></a>
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
          <?php foreach ($HV2['guides'] as $g): ?>
          <li><a class="guide" href="<?= hv2_e($g['href']) ?>"><span class="guide-media"><?= $g['thumb'] ? hv2_photo([$g['thumb'], 160, 160, '']) : hv2_fallback($g['slug']) ?></span><span class="guide-text"><b><?= hv2_e($g['title']) ?></b><small><?= hv2_e(trim($g['category'] . ($g['readTime'] ? ', ' . $g['readTime'] . ' min de citit' : ''), ', ')) ?></small></span></a></li>
          <?php endforeach; ?>
        </ul>
        <a class="mega-all" href="/ghiduri">Toate ghidurile<?= hv2_ic('arrow-right') ?></a>
      </div>
      <div class="intents">
        <div class="intent"><p class="mega-label">Weekend în</p><div class="chips-links"><?php foreach ($hv2IntentCities as $c): ?><a href="/<?= hv2_e($c['slug']) ?>/activitati-weekend"><?= hv2_e($c['name']) ?></a><?php endforeach; ?></div></div>
        <div class="intent"><p class="mega-label">Cu copiii în</p><div class="chips-links"><?php foreach ($hv2IntentCities as $c): ?><a href="/<?= hv2_e($c['slug']) ?>/activitati-copii"><?= hv2_e($c['name']) ?></a><?php endforeach; ?></div></div>
      </div>
      <a class="mini-gift" href="/card-cadou">
        <?= hv2_brand('gc-brand') ?>
        <span class="mg-kind">Card cadou</span>
        <span class="mg-text">Alegi suma, ei aleg experiența. Valabil 12 luni, la orice locație.</span>
        <span class="mg-cta">Vezi cardul cadou<?= hv2_ic('arrow-right') ?></span>
        <svg class="mg-line" viewBox="900 585 2340 310" aria-hidden="true"><use href="#drum-g"/></svg>
      </a>
    </div>
  </div>
</header>

<nav class="drawer" id="menu" aria-label="Meniu mobil" data-lenis-prevent>
  <details class="dr-sec"><summary>Explorează<?= hv2_ic('caret-down') ?></summary>
    <ul class="dr-links">
      <?php foreach (array_slice($HV2['citiesList'], 0, 12) as $c): ?><li><a href="<?= hv2_e($c['href']) ?>"><?= hv2_e($c['name']) ?></a></li><?php endforeach; ?>
      <li><a class="dr-all" href="/orase">Toate orașele</a></li>
    </ul>
  </details>
  <details class="dr-sec"><summary>Activități<?= hv2_ic('caret-down') ?></summary>
    <ul class="dr-links one">
      <?php foreach ($HV2['categories'] as $c): ?><li><a href="<?= hv2_e($c['href']) ?>"><?= hv2_e($c['name']) ?></a></li><?php endforeach; ?>
      <li><a class="dr-all" href="/categorii">Toate categoriile</a></li>
    </ul>
  </details>
  <details class="dr-sec"><summary>Inspirație<?= hv2_ic('caret-down') ?></summary>
    <ul class="dr-links one">
      <?php foreach ($HV2['guides'] as $g): ?><li><a href="<?= hv2_e($g['href']) ?>"><?= hv2_e($g['title']) ?></a></li><?php endforeach; ?>
      <li><a class="dr-all" href="/ghiduri">Toate ghidurile</a></li>
    </ul>
  </details>
  <a class="dr-link" href="/card-cadou">Card cadou</a>
  <div class="dr-foot">
    <a class="btn btn-primary" href="/cont"><?= hv2_ic('user-circle') ?>Contul meu</a>
    <a class="btn btn-ghost" href="/recuperare-comanda">Recuperează comanda</a>
    <p>Suport: <?= hv2_e(SUPPORT_EMAIL) ?>, luni - vineri, 09:00 - 18:00</p>
  </div>
</nav>
