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
      <div class="lang-wrap">
        <button class="icon-btn lang" id="lang-btn" type="button" aria-expanded="false" aria-controls="lang-menu"><?= v2_ic('globe-simple') ?><span aria-hidden="true">RO</span><span class="sr">Limba site-ului: română</span></button>
        <div class="lang-menu" id="lang-menu" hidden>
          <p class="lang-h">Limba site-ului</p>
          <p class="lang-opt" aria-current="true" lang="ro"><?= v2_ic('check') ?>Română</p>
        </div>
      </div>
      <a class="icon-btn" href="/cos" aria-label="Coșul de cumpărături"><?= v2_ic('shopping-cart-simple') ?><span class="hdr-badge" data-cart-count hidden></span></a>
      <a class="icon-btn" href="/cont" aria-label="Contul meu" data-account><?= v2_ic('user-circle') ?><span class="acct-ini" hidden></span></a>
      <button class="icon-btn mm-sbtn" type="button" data-mm-search aria-controls="menu" aria-label="Caută pe bilete.online"><?= v2_ic('magnifying-glass') ?></button>
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
      <div class="ma-main">
        <div class="ma-top">
          <p class="mega-label">Toate categoriile, dintr-o privire</p>
          <a class="mega-all" href="/categorii">Pagina categoriilor<?= v2_ic('arrow-right') ?></a>
        </div>
        <ul class="ma-dir">
          <?php foreach ($V2NAV['categories'] as $c):
              $maSubs = array_slice($c['subs'], 0, 4);
              $maMore = count($c['subs']) - count($maSubs);
          ?>
          <li class="ma-cat">
            <a class="ma-head" href="<?= v2_e($c['href']) ?>"><span class="thumb"><?= $c['thumb'] ? v2_photo([$c['thumb'], 0, 0, '']) : '' ?></span><span class="ma-t"><b><?= v2_e($c['name']) ?></b><?php if ($c['desc']): ?><small><?= v2_e($c['desc']) ?></small><?php endif; ?></span></a>
            <ul class="ma-subs">
              <?php foreach ($maSubs as $s): ?><li><a href="<?= v2_e($s['href']) ?>"><?= v2_e($s['name']) ?></a></li><?php endforeach; ?>
              <li class="ma-more-li"><a class="ma-more" href="<?= v2_e($c['href']) ?>"><?= $maMore > 0 ? '+ încă ' . $maMore : 'Vezi tot' ?><?= v2_ic('arrow-right') ?></a></li>
            </ul>
          </li>
          <?php endforeach; ?>
        </ul>
        <div class="ma-ideas">
          <p class="mega-label">Alege după situație</p>
          <ul class="ideas ideas-row">
            <?php foreach ($v2Ideas as [$icon, $text, $href]): ?>
            <li><a class="idea" href="<?= $href ?>"><?= v2_ic($icon) ?><?= v2_e($text) ?></a></li>
            <?php endforeach; ?>
          </ul>
        </div>
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

<?php
// Mobile menu data: every city for the search suggestions (featured first), categories and guides.
$v2MmCities = [];
foreach ($V2NAV['citiesList'] as $c) {
    $v2MmCities[$c['slug']] = [$c['name'], $c['region'], $c['href']];
}
foreach ($V2NAV['allCities'] ?? [] as $c) {
    if (!isset($v2MmCities[$c['slug']]) && $c['name'] !== '') {
        $v2MmCities[$c['slug']] = [$c['name'], $c['region'], '/' . $c['slug']];
    }
}
$v2MmCityTotal = count($V2NAV['allCities'] ?? []) ?: array_sum(array_column($V2NAV['regions'], 'citiesCount'));
?>
<div class="mm" id="menu" hidden>
  <div class="mm-scrim" data-mm-close></div>
  <div class="mm-sheet" role="dialog" aria-modal="true" aria-label="Meniu" tabindex="-1" data-lenis-prevent>
    <svg class="mm-line" viewBox="0 590 3240 310" aria-hidden="true" focusable="false"><use href="#drum-g"/></svg>
    <div class="mm-searchbar" style="--i:0">
      <form class="mm-search" role="search" action="/cauta" method="get">
        <?= v2_ic('magnifying-glass') ?>
        <label class="sr" for="mm-q">Caută pe bilete.online</label>
        <input id="mm-q" name="q" type="search" placeholder="Atracție, oraș sau activitate" autocomplete="off" enterkeyhint="search" role="combobox" aria-expanded="false" aria-controls="mm-suggest" aria-autocomplete="list">
        <button type="submit" aria-label="Caută"><?= v2_ic('arrow-right') ?></button>
      </form>
      <div class="mm-suggest" id="mm-suggest" role="listbox" aria-label="Sugestii" hidden></div>
    </div>

    <div class="mm-stage">
      <section class="mm-panel is-current" id="mm-root" aria-label="Meniu principal">
        <ul class="mm-main">
          <li style="--i:1"><button class="mm-row" type="button" data-mm-go="mm-explore"><span class="mm-ic"><?= v2_ic('map-trifold') ?></span><span class="mm-t"><b>Explorează</b><small><?= $v2MmCityTotal ? v2_num($v2MmCityTotal, 'oraș', 'orașe') . ' în ' . count($V2NAV['regions']) . ' regiuni' : 'Orașe și regiuni' ?></small></span><?= v2_ic('arrow-right') ?></button></li>
          <li style="--i:2"><button class="mm-row" type="button" data-mm-go="mm-activities"><span class="mm-ic"><?= v2_ic('squares-four') ?></span><span class="mm-t"><b>Activități</b><small><?= v2_num(count($V2NAV['categories']), 'categorie', 'categorii') ?> de experiențe</small></span><?= v2_ic('arrow-right') ?></button></li>
          <li style="--i:3"><button class="mm-row" type="button" data-mm-go="mm-inspiration"><span class="mm-ic"><?= v2_ic('sun') ?></span><span class="mm-t"><b>Inspirație</b><small>Ghiduri și idei de weekend</small></span><?= v2_ic('arrow-right') ?></button></li>
          <li style="--i:4"><a class="mm-row" href="/card-cadou"><span class="mm-ic is-gold"><?= v2_ic('gift') ?></span><span class="mm-t"><b>Card cadou</b><small>Dăruiește o experiență</small></span><?= v2_ic('arrow-right') ?></a></li>
          <li style="--i:5"><a class="mm-row" href="/operatori"><span class="mm-ic"><?= v2_ic('buildings') ?></span><span class="mm-t"><b>Operatori</b><small>Locațiile de pe bilete.online</small></span><?= v2_ic('arrow-right') ?></a></li>
        </ul>

        <div class="mm-block" style="--i:6">
          <p class="mm-k">Idei rapide</p>
          <ul class="mm-chips">
            <?php foreach ($v2Ideas as [$icon, $text, $href]): ?><li><a href="<?= $href ?>"><?= v2_ic($icon) ?><?= v2_e($text) ?></a></li><?php endforeach; ?>
          </ul>
        </div>

        <?php if ($V2NAV['citiesList']): ?>
        <div class="mm-block" style="--i:7">
          <p class="mm-k">Orașe populare</p>
          <ul class="mm-rail">
            <?php foreach (array_slice($V2NAV['citiesList'], 0, 10) as $ci => $c): ?>
            <li><a class="mm-city" href="<?= v2_e($c['href']) ?>"><span class="mm-city-media"><?= $c['photo'] ? v2_photo([$c['photo'][0], 0, 0, '']) : v2_fallback($c['name'], $ci) ?></span><b><?= v2_e($c['name']) ?></b></a></li>
            <?php endforeach; ?>
          </ul>
        </div>
        <?php endif; ?>

        <div class="mm-foot" style="--i:8">
          <a class="btn btn-primary" href="/cont"><?= v2_ic('user-circle') ?>Contul meu</a>
          <a class="btn mm-ghost" href="/cos"><?= v2_ic('shopping-cart-simple') ?>Coșul meu</a>
          <a class="mm-small" href="/recuperare-comanda"><?= v2_ic('ticket') ?>Recuperează comanda</a>
          <p>Suport: <?= v2_e(SUPPORT_EMAIL) ?>, luni - vineri, 09:00 - 18:00</p>
        </div>
      </section>

      <section class="mm-panel" id="mm-explore" aria-labelledby="mm-explore-h" hidden>
        <div class="mm-phead"><button class="mm-back" type="button" data-mm-back><?= v2_ic('arrow-left') ?><span class="sr">Înapoi la meniu</span></button><h2 class="mm-ph" id="mm-explore-h">Explorează</h2><a class="mm-plink" href="/orase">Toate orașele</a></div>
        <div class="mm-tabs" role="tablist" aria-label="Regiuni" data-tabs>
          <?php foreach ($V2NAV['regions'] as $i => $r): ?><button class="mm-tab" type="button" role="tab" id="mmt-<?= $i ?>" aria-controls="mmr-<?= $i ?>" aria-selected="<?= $i ? 'false' : 'true' ?>" tabindex="<?= $i ? -1 : 0 ?>"><?= v2_e($r['name']) ?><small><?= (int) $r['citiesCount'] ?></small></button><?php endforeach; ?>
        </div>
        <?php foreach ($V2NAV['regions'] as $i => $r):
            $mmTiles = array_slice($r['featured'], 0, 4);
            $mmLinks = array_slice(array_merge(array_map(function ($c) {
                return ['name' => $c['name'], 'href' => $c['href']];
            }, array_slice($r['featured'], 4)), $r['more']), 0, 12);
        ?>
        <div class="mm-region" id="mmr-<?= $i ?>" role="tabpanel" aria-labelledby="mmt-<?= $i ?>"<?= $i ? ' hidden' : '' ?>>
          <?php if ($mmTiles): ?>
          <ul class="mm-tiles">
            <?php foreach ($mmTiles as $ti => $c): ?>
            <li><a class="mm-tile" href="<?= v2_e($c['href']) ?>"><?= $c['photo'] ? v2_photo([$c['photo'][0], 0, 0, '']) : v2_fallback($c['name'], $ti) ?><span><b><?= v2_e($c['name']) ?></b><small><?= $c['count'] ? v2_exp($c['count']) : 'Descoperă orașul' ?></small></span></a></li>
            <?php endforeach; ?>
          </ul>
          <?php endif; ?>
          <?php if ($mmLinks): ?>
          <p class="mm-k">Alte orașe din <?= v2_e($r['name']) ?></p>
          <ul class="mm-links"><?php foreach ($mmLinks as $l): ?><li><a href="<?= v2_e($l['href']) ?>"><?= v2_e($l['name']) ?></a></li><?php endforeach; ?></ul>
          <?php endif; ?>
          <a class="mm-all" href="/orase">Toate cele <?= v2_num($r['citiesCount'], 'oraș', 'orașe') ?> din <?= v2_e($r['name']) ?><?= v2_ic('arrow-right') ?></a>
        </div>
        <?php endforeach; ?>
      </section>

      <section class="mm-panel" id="mm-activities" aria-labelledby="mm-activities-h" hidden>
        <div class="mm-phead"><button class="mm-back" type="button" data-mm-back><?= v2_ic('arrow-left') ?><span class="sr">Înapoi la meniu</span></button><h2 class="mm-ph" id="mm-activities-h">Activități</h2><a class="mm-plink" href="/categorii">Toate</a></div>
        <ul class="mm-cats">
          <?php foreach ($V2NAV['categories'] as $i => $c): ?>
          <li class="mm-cat">
            <div class="mm-cat-row">
              <a class="mm-cat-link" href="<?= v2_e($c['href']) ?>"><span class="mm-cat-media"><?= $c['thumb'] ? v2_photo([$c['thumb'], 0, 0, '']) : v2_fallback($c['name'], $i) ?></span><span class="mm-t"><b><?= v2_e($c['name']) ?></b><small><?= $c['count'] ? v2_exp($c['count']) : 'Vezi categoria' ?></small></span></a>
              <?php if ($c['subs']): ?><button class="mm-cat-tg" type="button" aria-expanded="false" aria-controls="mmc-<?= v2_e($c['slug']) ?>"><?= v2_ic('caret-down') ?><span class="sr">Tipuri de <?= v2_e($c['name']) ?></span></button><?php endif; ?>
            </div>
            <?php if ($c['subs']): ?>
            <div class="mm-sub" id="mmc-<?= v2_e($c['slug']) ?>"><div class="mm-sub-in">
              <ul class="mm-subs">
                <?php foreach ($c['subs'] as $s): ?><li><a href="<?= v2_e($s['href']) ?>"><?= v2_e($s['name']) ?></a></li><?php endforeach; ?>
                <li class="mm-subs-all"><a href="<?= v2_e($c['href']) ?>">Toată categoria<?= v2_ic('arrow-right') ?></a></li>
              </ul>
            </div></div>
            <?php endif; ?>
          </li>
          <?php endforeach; ?>
        </ul>
      </section>

      <section class="mm-panel" id="mm-inspiration" aria-labelledby="mm-inspiration-h" hidden>
        <div class="mm-phead"><button class="mm-back" type="button" data-mm-back><?= v2_ic('arrow-left') ?><span class="sr">Înapoi la meniu</span></button><h2 class="mm-ph" id="mm-inspiration-h">Inspirație</h2><a class="mm-plink" href="/ghiduri">Toate</a></div>
        <?php if ($V2NAV['guides']): ?>
        <ul class="mm-guides">
          <?php foreach ($V2NAV['guides'] as $g): ?>
          <li><a class="mm-guide" href="<?= v2_e($g['href']) ?>"><span class="mm-guide-media"><?= $g['thumb'] ? v2_photo([$g['thumb'], 160, 160, '']) : v2_fallback($g['slug']) ?></span><span><b><?= v2_e($g['title']) ?></b><small><?= v2_e(trim($g['category'] . ($g['readTime'] ? ', ' . $g['readTime'] . ' min de citit' : ''), ', ')) ?></small></span></a></li>
          <?php endforeach; ?>
        </ul>
        <?php endif; ?>
        <?php if ($v2IntentCities): ?>
        <p class="mm-k">Weekend în</p>
        <ul class="mm-chips"><?php foreach ($v2IntentCities as $c): ?><li><a href="/<?= v2_e($c['slug']) ?>/activitati-weekend"><?= v2_e($c['name']) ?></a></li><?php endforeach; ?></ul>
        <p class="mm-k">Cu copiii în</p>
        <ul class="mm-chips"><?php foreach ($v2IntentCities as $c): ?><li><a href="/<?= v2_e($c['slug']) ?>/activitati-copii"><?= v2_e($c['name']) ?></a></li><?php endforeach; ?></ul>
        <?php endif; ?>
        <a class="mm-gift" href="/card-cadou">
          <span class="mm-gift-k">Card cadou</span>
          <b>Alegi suma, ei aleg experiența.</b>
          <span>Valabil 12 luni, la orice locație.</span>
          <span class="mm-gift-cta">Vezi cardul cadou<?= v2_ic('arrow-right') ?></span>
          <svg class="mm-gift-line" viewBox="900 585 2340 310" aria-hidden="true" focusable="false"><use href="#drum-g"/></svg>
        </a>
      </section>
    </div>
  </div>
</div>
<script type="application/json" id="mm-data"><?= json_encode([
    'c' => array_values($v2MmCities),
    'k' => array_map(function ($c) { return [$c['name'], $c['href']]; }, $V2NAV['categories']),
    'g' => array_map(function ($g) { return [$g['title'], $g['href']]; }, $V2NAV['guides']),
], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP) ?></script>
