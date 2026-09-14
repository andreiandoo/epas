<?php
/**
 * bilete.online — homepage v2: hero and every section of <main>.
 * Expects $HV2 from home-v2/data.php. Sections without data are left out.
 */
$hv2Meta = require __DIR__ . '/photo-meta.php';
$hv2Arches = '<svg class="deco-arches" viewBox="0 0 400 400" aria-hidden="true" focusable="false"><path d="M40 400V200a160 160 0 0 1 320 0v200"/><path d="M90 400V200a110 110 0 0 1 220 0v200"/><path d="M140 400V200a60 60 0 0 1 120 0v200"/></svg>';
$hv2Peles = $HV2['heroAttraction'];
$hv2Chips = [['familii', 'Familii'], ['prieteni', 'Prieteni'], ['cupluri', 'Cupluri'], ['copii', 'Copii'], ['grupuri', 'Grupuri'], ['solo', 'Solo'], ['turisti', 'Turiști'], ['seniori', 'Seniori']];
?>
<section class="hero" id="hero" aria-labelledby="hero-h">
  <div class="hero-in">
    <div class="hero-copy" id="hero-copy">
      <h1 class="hero-h" id="hero-h"><span class="l">Experiențele încep</span> <span class="l"><em>înainte</em> de intrare.</span></h1>
      <p class="hero-sub">Bilete la atracții, muzee, parcuri și experiențe din toată România.</p>

      <form class="search" id="search" role="search" action="/cauta" method="get" aria-label="Caută bilete">
        <div class="sf sf-q">
          <label for="q">Unde sau ce</label>
          <input id="q" name="q" type="text" placeholder="Oraș, atracție sau experiență" autocomplete="off" role="combobox" aria-autocomplete="list" aria-expanded="false" aria-controls="q-list">
          <div class="pop suggest" id="q-list" role="listbox" aria-label="Sugestii" data-lenis-prevent hidden></div>
        </div>
        <div class="sf sf-when">
          <span class="lbl" id="when-lbl">Când</span>
          <button class="sf-btn" id="when-btn" type="button" aria-haspopup="dialog" aria-expanded="false" aria-controls="when-pop" aria-describedby="when-lbl">
            <span id="when-val">Oricând</span><?= hv2_ic('caret-down') ?>
          </button>
          <div class="pop" id="when-pop" role="dialog" aria-label="Alege când" hidden>
            <div class="opt-list">
              <button class="opt" type="button" data-when="" aria-pressed="true">Oricând</button>
              <button class="opt" type="button" data-when="azi" aria-pressed="false">Azi <small data-day="0"></small></button>
              <button class="opt" type="button" data-when="maine" aria-pressed="false">Mâine <small data-day="1"></small></button>
              <button class="opt" type="button" data-when="weekend" aria-pressed="false">Weekendul acesta <small data-day="weekend"></small></button>
            </div>
            <label class="pop-date" for="when-date">Alege o dată
              <input id="when-date" type="date">
            </label>
          </div>
          <input type="hidden" name="data" id="when-input">
        </div>
        <div class="sf sf-who">
          <span class="lbl" id="who-lbl">Cu cine</span>
          <button class="sf-btn" id="who-btn" type="button" aria-haspopup="dialog" aria-expanded="false" aria-controls="who-pop" aria-describedby="who-lbl">
            <span id="who-val">Oricine</span><?= hv2_ic('caret-down') ?>
          </button>
          <div class="pop" id="who-pop" role="dialog" aria-label="Alege cu cine mergi" hidden>
            <p class="pop-title">Poți alege mai multe</p>
            <div class="chips">
              <?php foreach ($hv2Chips as [$slug, $label]): ?><button class="chip" type="button" data-who="<?= $slug ?>" aria-pressed="false"><?= $label ?></button><?php endforeach; ?>
            </div>
            <div class="pop-foot">
              <button class="link-btn" type="button" id="who-clear">Șterge</button>
              <button class="done-btn" type="button" id="who-done">Gata</button>
            </div>
          </div>
          <input type="hidden" name="traveler_types" id="who-input">
        </div>
        <button class="search-go" type="submit"><?= hv2_ic('magnifying-glass') ?>Caută</button>
      </form>

      <?php if ($HV2['popular']): ?>
      <div class="popular">
        <span>Populare:</span>
        <?php foreach ($HV2['popular'] as $p): ?><a href="<?= hv2_e($p['href']) ?>"><?= hv2_e($p['name']) ?></a><?php endforeach; ?>
      </div>
      <?php endif; ?>
    </div>

    <svg class="hero-line" id="hero-line" viewBox="0 590 3240 310" aria-hidden="true"><use href="#drum-g"/></svg>

    <div class="arch-slot" id="arch-slot">
      <div class="hero-media" id="hero-media">
        <img id="hero-img" src="<?= hv2_asset('img/hero-1920.webp') ?>" srcset="<?= hv2_asset('img/hero-900.webp') ?> 900w, <?= hv2_asset('img/hero-1440.webp') ?> 1440w, <?= hv2_asset('img/hero-1920.webp') ?> 1920w" sizes="100vw" width="1920" height="1372" decoding="async" alt="<?= hv2_e($hv2Meta['hero_alt']) ?>">
        <div class="hero-feature" id="hero-feature">
          <a class="hf-card" href="<?= hv2_e($hv2Peles['href'] ?? hv2_cauta('Castelul Peleș')) ?>">
            <span class="hf-kicker">În fotografie</span>
            <strong><?= hv2_e($hv2Peles ? $hv2Peles['name'] . ($hv2Peles['city'] ? ', ' . $hv2Peles['city'] : '') : 'Castelul Peleș, Sinaia') ?></strong>
            <span class="hf-meta"><span><?= hv2_e($hv2Peles['type'] ?? 'Castel & palat') ?></span><span class="hf-cta">Vezi atracția<?= hv2_ic('arrow-right') ?></span></span>
          </a>
        </div>
      </div>
    </div>
  </div>
</section>

<main id="main" tabindex="-1">
  <div id="hdr-sentinel" aria-hidden="true"></div>

  <?php if ($HV2['categories']): ?>
  <section class="sec cats" id="categorii" aria-labelledby="cats-h">
    <?= $hv2Arches ?>
    <div class="wrap">
      <div class="sec-head">
        <h2 id="cats-h">Alege-ți genul de aventură</h2>
        <a class="sec-link" href="/categorii">Toate categoriile<?= hv2_ic('arrow-right') ?></a>
      </div>
      <ul class="cat-grid" data-reveal>
        <?php foreach ($HV2['categories'] as $c): ?>
        <li class="cat">
          <a href="<?= hv2_e($c['href']) ?>">
            <span class="cat-media"><?= $c['image'] ? hv2_photo([$c['image'], 640, 800, ''], $c['srcset'] ? ' srcset="' . hv2_e($c['srcset']) . '" sizes="(min-width: 1280px) 215px, (min-width: 1024px) 320px, (min-width: 768px) 30vw, 72vw"' : '') : hv2_fallback($c['name']) ?></span>
            <span class="cat-body">
              <span class="cat-title"><?= hv2_e($c['name']) ?><?= hv2_ic('arrow-right') ?></span>
              <span class="cat-desc"><?= hv2_e($c['desc']) ?></span>
              <span class="cat-meta"><?= $c['count'] ? hv2_exp($c['count']) : '' ?></span>
            </span>
          </a>
        </li>
        <?php endforeach; ?>
      </ul>
    </div>
  </section>
  <?php endif; ?>

  <?php if ($HV2['activities']): ?>
  <section class="sec days" id="experiente" aria-labelledby="days-h">
    <div class="wrap">
      <div class="sec-head">
        <h2 id="days-h">Ce faci în zilele următoare?</h2>
        <a class="sec-link" href="/cauta">Toate experiențele<?= hv2_ic('arrow-right') ?></a>
      </div>
      <?php if ($HV2['hasDates']): ?>
      <p class="days-month" id="days-month"><?= hv2_e($HV2['monthLabel']) ?></p>
      <ul class="daystrip" id="daystrip" aria-label="Alege ziua" data-reveal>
        <?php foreach ($HV2['days'] as $d): ?>
        <li><button class="day" type="button" data-date="<?= $d['iso'] ?>" aria-pressed="false"><span class="day-dow"><?= hv2_e($d['label']) ?></span><span class="day-num"><?= $d['day'] ?></span><span class="day-opt"><?= hv2_num($d['count'], 'opțiune', 'opțiuni') ?></span><span class="day-bar" aria-hidden="true"></span></button></li>
        <?php endforeach; ?>
        <li><span class="day day-more"><?= hv2_ic('calendar-blank') ?><span class="day-opt">Alte date</span><label class="sr" for="day-date">Alege altă dată</label><input id="day-date" type="date" min="<?= $HV2['today'] ?>"></span></li>
      </ul>
      <?php endif; ?>
      <?php if (count($HV2['activityTabs']) > 2): ?>
      <div class="days-tabs" id="days-tabs" role="tablist" aria-label="Tip de experiență">
        <?php $first = true; foreach ($HV2['activityTabs'] as $slug => $name): ?>
        <button class="tab" type="button" role="tab" data-cat="<?= hv2_e($slug) ?>" aria-selected="<?= $first ? 'true' : 'false' ?>" aria-controls="xp-grid" tabindex="<?= $first ? 0 : -1 ?>"><?= hv2_e($name) ?></button>
        <?php $first = false; endforeach; ?>
      </div>
      <?php endif; ?>
      <div class="days-bar">
        <p class="days-count" id="days-count" aria-live="polite"><b><?= hv2_num(count($HV2['activities']), 'opțiune', 'opțiuni') ?></b><?= $HV2['minPrice'] ? ' <span>de la ' . $HV2['minPrice'] . ' lei</span>' : '' ?></p>
        <label class="sr" for="days-sort">Sortează experiențele</label>
        <select class="select" id="days-sort"><option value="pop">Recomandate</option><option value="price">Preț crescător</option><option value="rating">Cele mai bine notate</option></select>
      </div>
      <ul class="xp-grid" id="xp-grid" data-reveal>
        <?php foreach ($HV2['activities'] as $i => $a): ?>
        <li class="xp" data-cat="<?= hv2_e($a['cat']) ?>" data-dates="<?= hv2_e(implode(',', $a['dates'])) ?>" data-price="<?= $a['price'] ?>" data-rating="<?= $a['rating'] ?>" data-pop="<?= $i + 1 ?>"<?= $i >= 8 ? ' hidden' : '' ?>>
          <a href="<?= hv2_e($a['href']) ?>">
            <span class="xp-media"><?= $a['image'] ? hv2_photo([$a['image'], 0, 0, '']) : hv2_fallback($a['title'], $i) ?></span>
            <span class="xp-body">
              <span class="xp-cat"><?= hv2_e($a['catName']) ?></span>
              <span class="xp-title"><?= hv2_e($a['title']) ?></span>
              <span class="xp-meta"><?php if ($a['city']): ?><span><?= hv2_ic('map-pin') ?><?= hv2_e($a['city']) ?></span><?php endif; ?><?php if ($a['dur']): ?><span><?= hv2_ic('clock') ?><?= hv2_e($a['dur']) ?></span><?php endif; ?><?php if ($a['reviews']): ?><span class="xp-rating"><?= hv2_ic('star') ?><?= str_replace('.', ',', (string) $a['rating']) ?> (<?= hv2_thousands($a['reviews']) ?>)</span><?php endif; ?></span>
              <span class="xp-foot"><span class="xp-avail"><?= hv2_ic('check-circle') ?><span class="xp-avail-t"><?= $a['nextLabel'] ? 'Disponibil ' . hv2_e($a['nextLabel']) : 'Verifică disponibilitatea' ?></span></span><?php if ($a['price']): ?><span class="xp-price">de la<b><?= $a['price'] ?> lei</b></span><?php endif; ?></span>
            </span>
          </a>
        </li>
        <?php endforeach; ?>
      </ul>
      <p class="days-empty" id="days-empty" hidden>Nu avem încă disponibilitate afișată pentru alegerea asta. <a href="/cauta">Vezi toate experiențele</a>.</p>
    </div>
  </section>
  <?php endif; ?>

  <?php if ($HV2['attractions']): ?>
  <section class="sec attr" aria-labelledby="attr-h">
    <svg class="attr-line" viewBox="0 590 3240 310" aria-hidden="true" focusable="false"><use href="#drum-g"/></svg>
    <div class="wrap">
      <div class="sec-head">
        <h2 id="attr-h">Atracții de neratat</h2>
        <div class="sec-tools">
          <a class="sec-link" href="/orase">Explorează orașele<?= hv2_ic('arrow-right') ?></a>
          <div class="rail-btns" data-for="attr-rail">
            <button class="rail-btn" type="button" data-dir="-1" aria-label="Atracțiile anterioare"><?= hv2_ic('arrow-left') ?></button>
            <button class="rail-btn" type="button" data-dir="1" aria-label="Atracțiile următoare"><?= hv2_ic('arrow-right') ?></button>
          </div>
        </div>
      </div>
      <ul class="rail" id="attr-rail">
        <?php foreach ($HV2['attractions'] as $a): ?>
        <li class="at"><a href="<?= hv2_e($a['href']) ?>">
          <span class="at-media"><?= $a['image'] ? hv2_photo([$a['image'], 0, 0, '']) : hv2_fallback($a['name']) ?></span>
          <span class="at-name"><?= hv2_e($a['name']) ?><?= hv2_ic('arrow-right') ?></span>
          <span class="at-meta"><span><?= hv2_e(trim($a['type'] . ($a['city'] ? ', ' . $a['city'] : ''), ', ')) ?></span></span>
        </a></li>
        <?php endforeach; ?>
      </ul>
    </div>
  </section>
  <?php endif; ?>

  <section class="sec rec" aria-labelledby="rec-h">
    <div class="wrap rec-head">
      <h2 id="rec-h">Recomandat pentru</h2>
      <p>Alege cu cine ieși. Îți arătăm experiențele pe care locațiile le recomandă pentru familii, prieteni sau cupluri.</p>
    </div>
    <ul class="who">
      <?php foreach ($HV2['who'] as $i => $w): $on = $i === 0; ?>
      <li class="wp<?= $on ? ' is-open' : '' ?>" style="--wc:<?= $w['color'] ?>;--pos:<?= $w['pos'] ?>">
        <img class="wp-img" src="<?= hv2_asset('img/' . $w['img'] . '-800.webp') ?>" srcset="<?= hv2_asset('img/' . $w['img'] . '-800.webp') ?> 800w, <?= hv2_asset('img/' . $w['img'] . '-1200.webp') ?> 1200w" sizes="(min-width: 1024px) 60vw, 100vw" alt="" loading="lazy" decoding="async">
        <div class="wp-content">
          <h3 class="wp-h"><button class="wp-tab" type="button" id="wt-<?= $w['k'] ?>" aria-expanded="<?= $on ? 'true' : 'false' ?>" aria-controls="wb-<?= $w['k'] ?>"><span class="wp-idx">0<?= $i + 1 ?></span><span class="wp-name"><?= hv2_e($w['label']) ?></span><span class="wp-plus"><?= hv2_ic('plus') ?></span></button></h3>
          <div class="wp-body" id="wb-<?= $w['k'] ?>" role="region" aria-labelledby="wt-<?= $w['k'] ?>">
            <div class="wp-body-in">
              <p class="wp-intro"><?= hv2_e($w['intro']) ?></p>
              <?php if ($w['cards']): ?>
              <ul class="wp-cards">
                <?php foreach ($w['cards'] as $c): ?>
                <li><a class="wpc" href="<?= hv2_e($c['href']) ?>"><span class="wpc-media"><?= $c['image'] ? hv2_photo([$c['image'], 0, 0, '']) : hv2_fallback($c['title']) ?></span><span class="wpc-body"><span class="wpc-t"><?= hv2_e($c['title']) ?></span><span class="wpc-m"><?= $c['meta'] ?></span></span></a></li>
                <?php endforeach; ?>
              </ul>
              <?php endif; ?>
              <a class="wp-all" href="<?= hv2_e($w['href']) ?>">Toate experiențele pentru <?= hv2_e(mb_strtolower($w['label'])) ?><?= hv2_ic('arrow-right') ?></a>
            </div>
          </div>
        </div>
      </li>
      <?php endforeach; ?>
    </ul>
  </section>

  <?php if (count($HV2['destinations']) >= 5): ?>
  <section class="sec dest" aria-labelledby="dest-h">
    <?php readfile(__DIR__ . '/topo.svg'); ?>
    <div class="wrap">
      <div class="sec-head">
        <h2 id="dest-h">Destinații populare</h2>
        <a class="sec-link" href="/orase">Toate <?= $HV2['totals']['cities'] ? 'cele ' . hv2_num($HV2['totals']['cities'], 'oraș', 'orașe') : 'orașele' ?><?= hv2_ic('arrow-right') ?></a>
      </div>
      <ul class="bento">
        <?php foreach (array_slice($HV2['destinations'], 0, 7) as $i => $c): $ph = $c['photo']; ?>
        <li class="dc<?= $i === 0 ? ' dc-xl' : '' ?><?= $ph ? '' : ' dc-fb' ?>"><a href="<?= hv2_e($c['href']) ?>"><?= $ph ? hv2_photo($ph) : hv2_fallback($c['name']) ?><span class="dc-body"><span class="dc-name"><?= hv2_e($c['name']) ?></span><span class="dc-meta"><?= $c['count'] ? hv2_exp($c['count']) : hv2_e($c['region']) ?></span></span></a></li>
        <?php endforeach; ?>
      </ul>
    </div>
  </section>
  <?php endif; ?>

  <section class="sec why" aria-labelledby="why-h">
    <div class="why-stage">
      <svg class="why-bgline" viewBox="0 590 3240 310" aria-hidden="true" focusable="false"><use href="#drum-g"/></svg>
      <div class="why-in">
        <div class="why-head">
          <div>
            <p class="kicker">De ce bilete.online</p>
            <h2 id="why-h">Simplu de cumpărat. <em>Simplu de intrat.</em></h2>
          </div>
          <p class="why-lead">De la alegerea experienței până la scanarea biletului, totul se face de pe telefon, cu reguli afișate înainte de plată și oameni care îți răspund când ai nevoie.</p>
        </div>
        <ul class="why-bento">
          <li class="bt bt-qr">
            <div class="bt-copy">
              <span class="bt-ic"><?= hv2_ic('qr-code') ?></span>
              <h3>Intri cu QR de pe telefon</h3>
              <p>Biletul ajunge imediat pe email și în cont. La intrare doar îl scanezi, printarea e opțională.</p>
            </div>
            <div class="phone" aria-hidden="true">
              <div class="ph-screen">
                <div class="ph-bar"><span>10:24</span><span class="ph-notch"></span><span class="ph-bat"></span></div>
                <div class="tk">
                  <div class="tk-top"><span class="tk-kicker">Bilet de intrare</span><b>Castelul Peleș</b><span>Sâmbătă, 10:30 · 2 adulți</span></div>
                  <div class="tk-cut"></div>
                  <div class="tk-qr"><?php readfile(__DIR__ . '/qr.svg'); ?><span class="tk-scan"></span><span class="tk-ok"><?= hv2_ic('check') ?><b>Validat la intrare</b></span></div>
                  <span class="tk-code">BO 7K4F 29QX</span>
                </div>
                <p class="ph-note"><?= hv2_ic('envelope-simple') ?>Trimis și pe email</p>
              </div>
            </div>
          </li>
          <li class="bt bt-pay">
            <div class="bt-copy">
              <span class="bt-ic"><?= hv2_ic('credit-card') ?></span>
              <h3>Plată sigură, și fără cont</h3>
              <p>Plătești online, securizat, în câteva secunde. Poți cumpăra și ca invitat, doar cu emailul.</p>
            </div>
            <div class="pay" aria-hidden="true">
              <div class="pay-field"><small>Email pentru bilete</small><span class="pay-typed">ana.popescu@exemplu.ro</span></div>
              <div class="pay-row"><span>Cumpăr fără cont</span><span class="switch"><span></span></span></div>
              <div class="pay-go"><?= hv2_ic('lock-simple') ?>Plătește 120 lei</div>
            </div>
          </li>
          <li class="bt bt-cancel">
            <div class="bt-copy">
              <span class="bt-ic"><?= hv2_ic('calendar-blank') ?></span>
              <h3>Reguli de anulare clare</h3>
              <p>Fiecare locație își stabilește politica de anulare, afișată pe pagina experienței înainte de plată.</p>
            </div>
            <div class="cx" aria-hidden="true">
              <span class="cx-rail"><span class="cx-fill"></span></span>
              <div class="cx-row" style="--c:#2BB673"><b>Anulare gratuită</b><span>cu peste 48 de ore înainte</span></div>
              <div class="cx-row" style="--c:#F2A900"><b>Rambursare 50%</b><span>între 48 și 24 de ore înainte</span></div>
              <div class="cx-row" style="--c:#E43A33"><b>Fără rambursare</b><span>cu mai puțin de 24 de ore</span></div>
              <small class="cx-note">Exemplu. Regulile fiecărei locații apar pe pagina ei.</small>
            </div>
          </li>
          <li class="bt bt-help">
            <div class="bt-copy">
              <span class="bt-ic"><?= hv2_ic('headset') ?></span>
              <h3>Suport real, de la oameni</h3>
              <p>Ne scrii sau ne suni de luni până vineri, între 09:00 și 18:00.</p>
            </div>
            <div class="chat" aria-hidden="true">
              <p class="msg msg-me">Nu mai găsesc emailul cu biletele.</p>
              <p class="msg msg-typing"><i></i><i></i><i></i></p>
              <p class="msg msg-us">Le găsești oricând în cont, la Biletele mele, sau le recuperezi din pagina Recuperează comanda.</p>
            </div>
            <div class="help-links">
              <a href="mailto:<?= hv2_e(SUPPORT_EMAIL) ?>"><?= hv2_ic('envelope-simple') ?><?= hv2_e(SUPPORT_EMAIL) ?></a>
              <a href="tel:+40750292962"><?= hv2_ic('phone') ?>0750 292 962</a>
            </div>
          </li>
          <li class="bt bt-map">
            <h3 class="bt-kicker">Toată România</h3>
            <?php if ($HV2['totals']['attractions']): ?>
            <p class="big"><span data-count="<?= $HV2['totals']['attractions'] ?>"><?= hv2_thousands($HV2['totals']['attractions']) ?></span><small>de atracții</small></p>
            <?php endif; ?>
            <?php if ($HV2['totals']['cities']): ?>
            <p class="bt-sub">în <b data-count="<?= $HV2['totals']['cities'] ?>"><?= $HV2['totals']['cities'] ?></b> de orașe, din toate cele <?= count(HV2_REGIONS) ?> regiuni</p>
            <?php endif; ?>
            <ul class="reg"><?php foreach (HV2_REGIONS as $i => $r): ?><li style="--i:<?= $i ?>"><?= hv2_e($r) ?></li><?php endforeach; ?></ul>
          </li>
        </ul>
      </div>
    </div>
  </section>

  <section class="sec gift" id="card-cadou" aria-labelledby="gift-h">
    <?= $hv2Arches ?>
    <div class="wrap gift-wrap">
      <div class="gift-copy">
        <h2 id="gift-h">Dăruiește o experiență, nu încă un obiect.</h2>
        <p>Cardul cadou se folosește la orice experiență de pe bilete.online, timp de 12 luni. Tu alegi suma, ei aleg aventura.</p>
        <fieldset class="amounts">
          <legend>Alege valoarea</legend>
          <div class="amount-list">
            <?php foreach ([50, 100, 150, 200, 300] as $v): ?><input type="radio" name="gift-amount" id="ga-<?= $v ?>" value="<?= $v ?>"<?= $v === 150 ? ' checked' : '' ?>><label for="ga-<?= $v ?>"><?= $v ?> lei</label><?php endforeach; ?>
            <input type="radio" name="gift-amount" id="ga-custom" value="custom"><label for="ga-custom">Altă sumă</label>
          </div>
          <div class="amount-custom"><label for="amount-custom">Suma dorită, în lei</label><input id="amount-custom" type="number" inputmode="numeric" min="20" max="5000" step="10" value="250"></div>
        </fieldset>
        <div class="gift-cta">
          <a class="btn btn-primary" href="/card-cadou"><?= hv2_ic('gift') ?>Cumpără card cadou</a>
          <span class="gift-note">Ajunge instant pe email, cu mesajul tău.</span>
        </div>
      </div>
      <div class="gift-visual">
        <div class="gc-wrap">
          <div class="giftcard" id="giftcard" role="img" aria-label="Previzualizare card cadou bilete.online">
            <span class="gc-top"><?= hv2_brand('gc-brand') ?><span class="gc-kind">Card cadou</span></span>
            <span class="gc-amount"><span id="gc-amount">150</span><small>lei</small></span>
            <span class="gc-bottom"><span>Valabil 12 luni</span><span>la orice experiență</span></span>
            <svg class="gc-line" viewBox="0 590 3240 310" aria-hidden="true"><use href="#drum-g"/></svg>
          </div>
        </div>
      </div>
    </div>
  </section>

  <?php if ($HV2['guideLead']): $lead = $HV2['guideLead']; ?>
  <section class="sec insp" aria-labelledby="insp-h">
    <div class="wrap">
      <div class="sec-head">
        <h2 id="insp-h">Inspirație pentru următoarea ieșire</h2>
        <a class="sec-link" href="/ghiduri">Toate ghidurile<?= hv2_ic('arrow-right') ?></a>
      </div>
      <div class="insp-grid">
        <article class="ia ia-lead"><a href="<?= hv2_e($lead['href']) ?>"><span class="ia-media"><?= $lead['cover'] ? hv2_photo($lead['cover']) : hv2_fallback($lead['slug']) ?></span><span class="ia-text"><span class="ia-cat"><?= hv2_e($lead['category']) ?></span><h3><?= hv2_e($lead['title']) ?></h3><p><?= hv2_e($lead['excerpt']) ?></p></span></a></article>
        <?php if ($HV2['guideSide']): ?>
        <div class="ia-side">
          <?php foreach ($HV2['guideSide'] as $g): ?>
          <article class="ia"><a href="<?= hv2_e($g['href']) ?>"><span class="ia-media"><?= hv2_photo($g['cover']) ?></span><span class="ia-text"><span class="ia-cat"><?= hv2_e($g['category']) ?></span><h3><?= hv2_e($g['title']) ?></h3><p><?= hv2_e($g['excerpt']) ?></p></span></a></article>
          <?php endforeach; ?>
        </div>
        <?php endif; ?>
      </div>
    </div>
  </section>
  <?php endif; ?>

  <section class="sec partners" aria-labelledby="partners-h">
    <div class="wrap partners-wrap">
      <div>
        <h2 id="partners-h">Ai o atracție sau o experiență? <em>Vinde bilete pe bilete.online.</em></h2>
        <div class="partners-cta">
          <a class="btn btn-light" href="/pentru-locatii">Listează-ți locația<?= hv2_ic('arrow-right') ?></a>
          <a class="link-light" href="/cum-functioneaza">Cum funcționează</a>
        </div>
      </div>
      <ul class="partners-list">
        <?php foreach ([['Comision de 1%', 'Fără abonament și fără costuri ascunse.'], ['Pagină dedicată', 'O pagină proprie, făcută să fie găsită în căutări.'], ['Scanare QR la intrare', 'De pe telefon sau cu un scanner USB.'], ['Plăți și decontări clare', 'Vezi vânzările și banii în timp real.']] as [$t, $d]): ?>
        <li><?= hv2_ic('check-circle') ?><span><b><?= $t ?></b><span><?= $d ?></span></span></li>
        <?php endforeach; ?>
      </ul>
    </div>
    <svg class="partners-line draw-clip" viewBox="0 590 3240 310" aria-hidden="true"><use href="#drum-g"/></svg>
  </section>

  <?php
  $hv2Top = [];
  if ($HV2['topAttractions']) {
      $hv2Top['atractii'] = ['Top atracții', '', array_map(function ($a) {
          return [$a['href'], $a['name'], trim($a['type'] . ($a['city'] ? ', ' . $a['city'] : ''), ', ')];
      }, $HV2['topAttractions'])];
  }
  if ($HV2['citiesList']) {
      $hv2Top['destinatii'] = ['Top destinații', '', array_map(function ($c) {
          return [$c['href'], $c['name'], $c['count'] ? hv2_exp($c['count']) : $c['region']];
      }, array_slice($HV2['citiesList'], 0, 24))];
  }
  $hv2Top['regiuni'] = ['Top regiuni', ' four', array_map(function ($r) {
      $names = implode(', ', array_column(array_slice($r['featured'], 0, 3), 'name'));
      return ['/orase', $r['name'], hv2_num($r['citiesCount'], 'oraș', 'orașe') . ($names ? ': ' . $names : '')];
  }, $HV2['regions'])];
  if ($HV2['types']) {
      $hv2Top['tipuri'] = ['Top tipuri de atracții', ' five', array_map(function ($t) {
          return [hv2_cauta($t['name']), $t['name'], hv2_num($t['count'], 'atracție', 'atracții')];
      }, $HV2['types'])];
  }
  $hv2TopKeys = array_keys($hv2Top);
  ?>
  <section class="sec top" aria-labelledby="top-h">
    <div class="wrap">
      <h2 id="top-h">Top pe bilete.online</h2>
      <div class="top-tabs" role="tablist" aria-label="Top pe bilete.online" data-tabs>
        <?php foreach ($hv2Top as $k => $panel): $on = $k === $hv2TopKeys[0]; ?>
        <button class="tab" type="button" role="tab" id="tt-<?= $k ?>" aria-controls="tp-<?= $k ?>" aria-selected="<?= $on ? 'true' : 'false' ?>" tabindex="<?= $on ? 0 : -1 ?>"><?= $panel[0] ?></button>
        <?php endforeach; ?>
      </div>
      <?php foreach ($hv2Top as $k => $panel): $on = $k === $hv2TopKeys[0]; ?>
      <div class="top-panel" id="tp-<?= $k ?>" role="tabpanel" aria-labelledby="tt-<?= $k ?>"<?= $on ? '' : ' hidden' ?>>
        <ul class="toplinks<?= $panel[1] ?>">
          <?php foreach ($panel[2] as [$href, $title, $meta]): ?><li><a href="<?= hv2_e($href) ?>"><b><?= hv2_e($title) ?></b><span><?= hv2_e($meta) ?></span></a></li><?php endforeach; ?>
        </ul>
      </div>
      <?php endforeach; ?>
    </div>
  </section>

  <section class="sec faq" aria-labelledby="faq-h">
    <div class="wrap faq-wrap">
      <div class="seo">
        <h2 id="seo-h">Bilete la experiențe și atracții din toată România</h2>
        <p><strong>bilete.online</strong> este platforma de ticketing pentru experiențe și atracții turistice: un <a href="/escape-rooms">escape room</a> cu prietenii, o zi la <a href="/parcuri-de-distractii">parcul de distracții</a> cu copiii, o vizită la <a href="/muzee-expozitii">muzeu</a> sau o tiroliană într-un <a href="/parcuri-de-aventura">parc de aventură</a>.</p>
        <p>Fiecare locație are propria pagină, cu prețuri actualizate, program și disponibilitate, iar biletul se cumpără pe loc. Cauți după oraș, categorie sau zi, fie că ești în <a href="/bucuresti">București</a>, <a href="/cluj-napoca">Cluj-Napoca</a>, <a href="/brasov">Brașov</a> sau <a href="/constanta">Constanța</a>.</p>
        <p>Platforma e operată tehnologic de Tixello, infrastructura de ticketing folosită de teatre, muzee și operatori de agrement din toată țara: bilete cu cod QR, scanare rapidă la intrare și plăți securizate.</p>
      </div>
      <div class="faq-col">
        <h2 id="faq-h">Întrebări frecvente</h2>
        <?php foreach (HV2_FAQ as [$question, $answer]): ?>
        <details class="qa"><summary><?= hv2_e($question) ?><span class="pm"><?= hv2_ic('plus') ?></span></summary><p><?= hv2_e($answer) ?></p></details>
        <?php endforeach; ?>
      </div>
    </div>
  </section>
</main>
