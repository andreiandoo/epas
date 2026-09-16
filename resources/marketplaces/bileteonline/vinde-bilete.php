<?php
/**
 * Sales page for venues and operators: /vinde-bilete (v2 design).
 *
 * A product-led page, not a feature list: it shows the operator's own screens — the dashboard, the on-site POS
 * ("InfoPoint"), and the scanning app — with the labels they really carry, a day at the venue from opening to closing
 * the register, what the hardware needs to be (a thermal printer, a phone or a tablet), what the money looks like
 * (2% commission, a calculator) and a demo request that goes into the same lead pipeline as /pentru-locatii.
 *
 * The screens are mock-ups built in HTML with sample figures, each marked as such; the catalogue numbers come from the
 * API. The on-site POS and the scanning app run on the platform and are switched on per account.
 */

$pageCacheTTL = 900;
require_once __DIR__ . '/includes/page-cache.php';
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/api.php';
require_once __DIR__ . '/includes/nav-helpers.php';
require_once __DIR__ . '/includes/v2/helpers.php';
require_once __DIR__ . '/includes/v2/nav.php';

$slR = api_cached_many([
    'attractions' => ['key' => 'v2_attractions_total', 'endpoint' => '/attractions', 'params' => ['per_page' => 1], 'ttl' => 21600],
]);
$slAttractions = (int) ($slR['attractions']['data']['pagination']['total'] ?? 0);
$slCities = count($V2NAV['allCities'] ?? []);
$slCategories = count($V2NAV['categories']);

$pageTitleRaw = 'Vinde bilete online și la fața locului — ' . SITE_NAME;
$pageDescription = 'Panou de operator, POS pentru vânzarea la ghișeu, aplicație de scanare pe telefon și tabletă, bon pe imprimantă termică și decontări clare. Comision 2%, fără abonament.';
$canonicalUrl = SITE_URL . '/vinde-bilete';
$ogImage = SITE_URL . '/assets/v2/img/hero-1440.webp';

$slFaq = [
    ['Cât durează până încep să vând?', 'După ce ne trimiți datele locației, îți pregătim contul, activitățile și tipurile de bilete. Publicarea depinde de cât de repede primim programul, prețurile și pozele. De obicei vorbim de zile, nu de luni.'],
    ['Ce costă?', 'Comisionul este de 2%, adăugat la prețul biletului și plătit de cumpărător, pentru vânzarea exclusivă prin bilete.online. Dacă vinzi biletele și în alte părți, comisionul este de 4%: 2% incluse în preț și 2% adăugate. Nu ai abonament lunar și nu plătești instalare.'],
    ['Pot vinde și la ghișeu, nu doar online?', 'Da. POS-ul emite bilete pe loc, ține coșul, încasează cash sau card, tipărește bonul și îți dă desfășurătorul casei și închiderea de casă la final de tură. Vânzările online și cele de la ghișeu ajung în același raport.'],
    ['Ce hardware îmi trebuie?', 'Un telefon sau o tabletă pentru scanare și, dacă vrei bon tipărit, o imprimantă termică de 58 sau 80 mm. Bonul se tipărește direct din browser, fără drivere speciale. POS-ul ține loc de casă în aplicație.'],
    ['Cum scanăm biletele la intrare?', 'Cu aplicația de scanare, instalată pe telefon sau tabletă. Scanează cu camera, iar dacă un cod nu se citește, îl poți tasta. Îți arată pe loc dacă biletul e valid, dacă a mai fost scanat sau dacă nu e recunoscut, cu vibrație și sunet.'],
    ['Cine îmi răspunde dacă apare o problemă?', 'Ai suport pe email și telefon, de luni până vineri, între 09:00 și 18:00, plus tichete de suport direct din panou. Pentru ziua unui eveniment mare, stabilim din timp cine e disponibil.'],
    ['Ce se întâmplă cu banii din vânzări?', 'Vezi în panou soldul disponibil, ce e în procesare și cât ai încasat până acum, pe fiecare activitate. Ceri plata când vrei, iar documentele și facturile rămân în cont.'],
];

$structuredData = [[
    '@context' => 'https://schema.org', '@type' => 'FAQPage',
    'mainEntity' => array_map(function ($f) {
        return ['@type' => 'Question', 'name' => $f[0], 'acceptedAnswer' => ['@type' => 'Answer', 'text' => $f[1]]];
    }, $slFaq),
], [
    '@context' => 'https://schema.org', '@type' => 'BreadcrumbList',
    'itemListElement' => [
        ['@type' => 'ListItem', 'position' => 1, 'name' => 'Acasă', 'item' => SITE_URL . '/'],
        ['@type' => 'ListItem', 'position' => 2, 'name' => 'Vinde bilete', 'item' => $canonicalUrl],
    ],
]];

$v2Styles = ['sell.css'];
$v2Scripts = ['sell.js', 'for-venues.js'];
$v2HeaderOverlay = true;

/** A row in the mock operator sidebar. */
$slNav = function (string $icon, string $label, bool $on = false): string {
    return '<span class="sl-ui-nav' . ($on ? ' is-on' : '') . '">' . v2_ic($icon) . '<b>' . v2_e($label) . '</b></span>';
};
/** A figure in the mock dashboard. */
$slKpi = function (string $label, string $value, string $delta = '', string $tone = ''): string {
    return '<div class="sl-ui-kpi"><p>' . v2_e($label) . '</p><b>' . v2_e($value) . '</b>'
        . ($delta ? '<small class="' . $tone . '">' . v2_ic('trend-up') . v2_e($delta) . '</small>' : '') . '</div>';
};

include __DIR__ . '/includes/v2/head.php';
include __DIR__ . '/includes/v2/header.php';
?>
<main id="main" tabindex="-1">

  <!-- ===================== HERO ===================== -->
  <section class="sl-hero" aria-labelledby="sl-h">
    <svg class="sl-hero-line draw-clip" viewBox="0 590 3240 310" aria-hidden="true" focusable="false"><use href="#drum-g"/></svg>
    <div class="wrap sl-hero-in">
      <div class="sl-hero-copy">
        <p class="sl-kicker"><?= v2_ic('buildings') ?>Pentru locații și operatori</p>
        <h1 class="sl-h" id="sl-h">Vinzi online și la ghișeu. <em>Dintr-un singur cont.</em></h1>
        <p class="sl-lead">Panou de operator care se citește dintr-o privire, POS pentru vânzarea pe loc, aplicație de scanare pe telefon sau tabletă și bon pe imprimantă termică. Tot ce s-a vândut, oriunde s-a vândut, ajunge în același raport.</p>
        <div class="sl-cta">
          <a class="btn btn-primary" href="#demo"><?= v2_ic('lightning') ?>Cere o demonstrație</a>
          <a class="btn btn-outline-light" href="#panou">Vezi panoul de operator<?= v2_ic('arrow-right') ?></a>
        </div>
        <ul class="sl-facts">
          <li><?= v2_ic('percent') ?><span><b>Comision 2%</b>plătit de cumpărător</span></li>
          <li><?= v2_ic('wallet') ?><span><b>Fără abonament</b>și fără cost de instalare</span></li>
          <li><?= v2_ic('scan') ?><span><b>Online + la fața locului</b>în același cont</span></li>
        </ul>
      </div>

      <div class="sl-devices" aria-label="Ecranele platformei: panou, POS și aplicația de scanare">
        <div class="sl-dev sl-dev-desk">
          <div class="sl-ui">
            <div class="sl-ui-top"><span class="sl-ui-dots" aria-hidden="true"><i></i><i></i><i></i></span><span class="sl-ui-url">bilete.online/organizator/panou</span></div>
            <div class="sl-ui-body">
              <div class="sl-ui-side">
                <?= $slNav('squares-four', 'Panou', true) ?>
                <?= $slNav('calendar-blank', 'Activități') ?>
                <?= $slNav('users-three', 'Participanți') ?>
                <?= $slNav('shopping-cart-simple', 'Vânzări') ?>
                <?= $slNav('wallet', 'Sold') ?>
              </div>
              <div class="sl-ui-main">
                <p class="sl-ui-h">Bun venit înapoi</p>
                <div class="sl-ui-kpis">
                  <?= $slKpi('Venituri luna aceasta', '18.240 lei', '+12%', 'is-up') ?>
                  <?= $slKpi('Bilete vândute', '412', '+8%', 'is-up') ?>
                </div>
                <div class="sl-ui-chart" aria-hidden="true">
                  <?php foreach ([38, 52, 44, 68, 59, 82, 74] as $h): ?><i style="--h:<?= $h ?>%"></i><?php endforeach; ?>
                </div>
              </div>
            </div>
          </div>
        </div>
        <div class="sl-dev sl-dev-phone">
          <div class="sl-ph">
            <div class="sl-ph-top"><span></span></div>
            <div class="sl-ph-scan">
              <p class="sl-ph-k">Scanare</p>
              <div class="sl-ph-frame" aria-hidden="true"><?= v2_ic('qr-code') ?><span class="sl-ph-laser"></span></div>
              <p class="sl-ph-state is-ok"><?= v2_ic('check-circle') ?>ACCES APROBAT</p>
              <p class="sl-ph-sub">Bilet adult · Poarta 1</p>
            </div>
          </div>
        </div>
      </div>
    </div>
  </section>
  <div id="hdr-sentinel" aria-hidden="true"></div>

  <!-- ===================== O ZI LA LOCAȚIE ===================== -->
  <section class="sec sl-day" aria-labelledby="sl-day-h">
    <div class="wrap">
      <div class="sec-head">
        <h2 id="sl-day-h">O zi la locația ta, de la prima vânzare la închiderea casei</h2>
        <p class="sl-sub">Aceleași bilete, aceleași rapoarte, indiferent dacă omul a cumpărat de acasă sau de la ghișeu.</p>
      </div>
      <ol class="sl-steps">
        <?php foreach ([
            ['clock', '08:40', 'Deschizi ziua', 'Vezi câte bilete sunt vândute pentru azi și câți oameni sunt așteptați pe fiecare interval.'],
            ['shopping-cart-simple', '09:15', 'Vânzări online', 'Oamenii cumpără de pe pagina activității tale sau din widgetul de pe site-ul propriu. Biletul pleacă pe email, cu cod QR.'],
            ['printer', '10:30', 'Vânzare la ghișeu', 'La intrare, POS-ul emite biletul pe loc: coș, cash sau card, bon tipărit pe imprimanta termică.'],
            ['scan', '11:00', 'Scanare la intrare', 'Cu telefonul sau tableta. Biletul e valid, a mai fost scanat sau nu e recunoscut: vezi pe loc, cu sunet și vibrație.'],
            ['door-open', '18:00', 'Închizi casa', 'Desfășurătorul casei arată cât cash predai și cât s-a încasat pe card, pe tura fiecărui operator.'],
            ['chart-line-up', '18:20', 'Vezi rezultatul', 'Raportul zilei adună online și ghișeu, pe activitate, pe interval și pe tip de bilet.'],
        ] as $i => [$icon, $time, $title, $text]): ?>
        <li class="sl-step" style="--i:<?= $i ?>">
          <span class="sl-step-ic"><?= v2_ic($icon) ?></span>
          <p class="sl-step-time"><?= $time ?></p>
          <h3><?= v2_e($title) ?></h3>
          <p><?= v2_e($text) ?></p>
        </li>
        <?php endforeach; ?>
      </ol>
    </div>
  </section>

  <!-- ===================== PANOUL DE OPERATOR ===================== -->
  <section class="sec sl-panel" id="panou" aria-labelledby="sl-panel-h">
    <div class="wrap">
      <div class="sec-head">
        <h2 id="sl-panel-h">Panoul de operator</h2>
        <p class="sl-sub">Fiecare ecran are un singur scop și se citește dintr-o privire. Alege o secțiune și vezi cum arată.</p>
      </div>

      <div class="sl-demo">
        <div class="sl-tabs" role="tablist" aria-label="Secțiuni din panou" data-tabs>
          <?php foreach ([
              ['panou', 'squares-four', 'Panou'],
              ['vanzari', 'shopping-cart-simple', 'Vânzări'],
              ['participanti', 'users-three', 'Participanți'],
              ['sold', 'wallet', 'Sold'],
              ['marketing', 'megaphone', 'Marketing'],
          ] as $i => [$key, $icon, $label]): ?>
          <button class="sl-tab" type="button" role="tab" id="slt-<?= $key ?>" aria-controls="slp-<?= $key ?>" aria-selected="<?= $i ? 'false' : 'true' ?>" tabindex="<?= $i ? -1 : 0 ?>"><?= v2_ic($icon) ?><?= v2_e($label) ?></button>
          <?php endforeach; ?>
        </div>

        <div class="sl-screen">
          <div class="sl-ui is-wide">
            <div class="sl-ui-top"><span class="sl-ui-dots" aria-hidden="true"><i></i><i></i><i></i></span><span class="sl-ui-url" id="sl-url">bilete.online/organizator/panou</span></div>
            <div class="sl-ui-body">
              <div class="sl-ui-side">
                <?= $slNav('squares-four', 'Panou', true) ?>
                <?= $slNav('calendar-blank', 'Activități') ?>
                <?= $slNav('users-three', 'Participanți') ?>
                <?= $slNav('shopping-cart-simple', 'Vânzări') ?>
                <?= $slNav('wallet', 'Sold') ?>
                <?= $slNav('file-text', 'Documente') ?>
                <?= $slNav('tag', 'Coduri promo') ?>
                <?= $slNav('code', 'Widget-uri') ?>
                <?= $slNav('receipt', 'Facturare') ?>
              </div>

              <div class="sl-ui-main">
                <div class="sl-p" id="slp-panou" role="tabpanel" aria-labelledby="slt-panou" data-url="bilete.online/organizator/panou">
                  <p class="sl-ui-h">Indicatorii lunii</p>
                  <div class="sl-ui-kpis is-four">
                    <?= $slKpi('Venituri luna aceasta', '18.240 lei', '+12%', 'is-up') ?>
                    <?= $slKpi('Bilete vândute luna aceasta', '412', '+8%', 'is-up') ?>
                    <?= $slKpi('Activități în derulare', '6') ?>
                    <?= $slKpi('Rată de conversie', '4,8%', '+0,6 p.p.', 'is-up') ?>
                  </div>
                  <p class="sl-ui-h2">Vânzări bilete</p>
                  <div class="sl-ui-chart is-big" aria-hidden="true">
                    <?php foreach ([32, 46, 38, 60, 52, 74, 66, 81, 58, 69, 77, 92] as $h): ?><i style="--h:<?= $h ?>%"></i><?php endforeach; ?>
                  </div>
                  <div class="sl-ui-quick">
                    <span><?= v2_ic('plus') ?>Activitate nouă</span>
                    <span><?= v2_ic('tag') ?>Cod promoțional</span>
                    <span><?= v2_ic('scan') ?>Scanare la intrare</span>
                  </div>
                </div>

                <div class="sl-p" id="slp-vanzari" role="tabpanel" aria-labelledby="slt-vanzari" data-url="bilete.online/organizator/vanzari" hidden>
                  <p class="sl-ui-h">Vânzări</p>
                  <div class="sl-ui-chips"><span class="is-on">Toate canalele</span><span>Online</span><span>Ghișeu</span><span>Luna aceasta</span></div>
                  <table class="sl-ui-table">
                    <thead><tr><th>Comandă</th><th>Activitate</th><th>Canal</th><th>Total</th><th>Status</th></tr></thead>
                    <tbody>
                      <?php foreach ([
                          ['BO-24817', 'Tur ghidat · 11:00', 'Online', '160 lei', 'Plătită', 'is-ok'],
                          ['BO-24816', 'Intrare adult', 'Ghișeu', '45 lei', 'Cash', 'is-info'],
                          ['BO-24815', 'Atelier copii · sâmbătă', 'Online', '220 lei', 'Plătită', 'is-ok'],
                          ['BO-24814', 'Intrare familie', 'Ghișeu', '120 lei', 'Card', 'is-info'],
                      ] as [$no, $act, $chan, $total, $status, $tone]): ?>
                      <tr><td><b><?= $no ?></b></td><td><?= v2_e($act) ?></td><td><?= $chan ?></td><td class="sl-right"><?= $total ?></td><td><span class="sl-pill <?= $tone ?>"><?= $status ?></span></td></tr>
                      <?php endforeach; ?>
                    </tbody>
                  </table>
                </div>

                <div class="sl-p" id="slp-participanti" role="tabpanel" aria-labelledby="slt-participanti" data-url="bilete.online/organizator/participanti" hidden>
                  <p class="sl-ui-h">Participanți</p>
                  <div class="sl-ui-search"><?= v2_ic('magnifying-glass') ?><span>Caută după nume, email sau cod bilet</span></div>
                  <ul class="sl-ui-list">
                    <?php foreach ([
                        ['AM', 'Andrei M.', 'Tur ghidat · 11:00', 'Intrat 10:58', 'is-ok'],
                        ['IR', 'Ioana R.', 'Atelier copii · 12:30', 'Așteptat', ''],
                        ['DP', 'Dan P.', 'Intrare adult', 'Intrat 10:41', 'is-ok'],
                        ['MS', 'Maria S.', 'Intrare familie · 4 pers.', 'Așteptat', ''],
                    ] as [$ini, $name, $act, $state, $tone]): ?>
                    <li><span class="sl-ui-av"><?= $ini ?></span><span class="sl-ui-t"><b><?= v2_e($name) ?></b><small><?= v2_e($act) ?></small></span><span class="sl-pill <?= $tone ?>"><?= $state ?></span></li>
                    <?php endforeach; ?>
                  </ul>
                </div>

                <div class="sl-p" id="slp-sold" role="tabpanel" aria-labelledby="slt-sold" data-url="bilete.online/organizator/sold" hidden>
                  <p class="sl-ui-h">Sold</p>
                  <div class="sl-ui-kpis">
                    <?= $slKpi('Sold disponibil', '9.420 lei') ?>
                    <?= $slKpi('În procesare', '1.180 lei') ?>
                    <?= $slKpi('Total încasat', '64.700 lei') ?>
                  </div>
                  <div class="sl-ui-payout"><span><?= v2_ic('bank') ?>Plata se face în contul locației</span><span class="sl-ui-btn">Solicită plata</span></div>
                  <ul class="sl-ui-mini">
                    <li><span>Tur ghidat</span><b>3.900 lei</b></li>
                    <li><span>Atelier copii</span><b>2.640 lei</b></li>
                    <li><span>Intrare zilnică</span><b>2.880 lei</b></li>
                  </ul>
                </div>

                <div class="sl-p" id="slp-marketing" role="tabpanel" aria-labelledby="slt-marketing" data-url="bilete.online/organizator/promo" hidden>
                  <p class="sl-ui-h">Marketing</p>
                  <ul class="sl-ui-list">
                    <li><span class="sl-ui-av is-tag"><?= v2_ic('tag') ?></span><span class="sl-ui-t"><b>TOAMNA10</b><small>-10% · 214 utilizări</small></span><span class="sl-pill is-ok">Activ</span></li>
                    <li><span class="sl-ui-av is-tag"><?= v2_ic('percent') ?></span><span class="sl-ui-t"><b>GRUP20</b><small>-20% de la 10 bilete · 38 utilizări</small></span><span class="sl-pill is-ok">Activ</span></li>
                  </ul>
                  <p class="sl-ui-h2">Widget pentru site-ul tău</p>
                  <pre class="sl-ui-code">&lt;script src="bilete.online/widget.js"
  data-locatie="muzeul-tau"&gt;&lt;/script&gt;</pre>
                </div>
              </div>
            </div>
          </div>
          <p class="sl-note"><?= v2_ic('info') ?>Ecrane din panoul real, cu date de exemplu.</p>
        </div>
      </div>
    </div>
  </section>

  <!-- ===================== POS ===================== -->
  <section class="sec sl-pos" id="ghiseu" aria-labelledby="sl-pos-h">
    <div class="wrap sl-split">
      <div class="sl-split-copy">
        <p class="sl-kicker is-light"><?= v2_ic('printer') ?>La fața locului</p>
        <h2 id="sl-pos-h">Ghișeul tău, cu bon și închidere de casă</h2>
        <p class="sl-sub is-light">POS-ul emite biletul pe loc și ține loc de casă în aplicație: coș, încasare cash sau card, bon tipărit pe imprimantă termică de 58 sau 80 mm, direct din browser, fără drivere instalate.</p>
        <ul class="sl-checks">
          <?php foreach ([
              'Coș cu tipurile tale de bilete, pachete și extra-opțiuni',
              'Încasare cash sau card, pe tura fiecărui operator',
              'Bon tipărit automat după fiecare comandă, dacă vrei',
              'Desfășurător de casă: cât cash predai, cât s-a încasat pe card',
              'Închidere de casă la final de tură, cu totalul comenzilor',
              'Factură pentru firme, direct din comandă',
          ] as $t): ?>
          <li><?= v2_ic('check-circle') ?><?= v2_e($t) ?></li>
          <?php endforeach; ?>
        </ul>
        <p class="sl-hint"><?= v2_ic('info') ?>Modulul de vânzare la fața locului se activează pe contul locației.</p>
      </div>

      <div class="sl-tablet" aria-label="Ecranul POS, cu date de exemplu">
        <div class="sl-tb">
          <div class="sl-tb-head"><b><?= v2_ic('ticket') ?>InfoPoint — Emite bilete</b><span class="sl-tb-open">Casă deschisă</span></div>
          <div class="sl-tb-body">
            <div class="sl-tb-items">
              <?php foreach ([['Intrare adult', '45 lei'], ['Intrare copil', '25 lei'], ['Tur ghidat', '60 lei'], ['Familie (2+2)', '120 lei'], ['Audioghid', '15 lei'], ['Atelier', '80 lei']] as $i => [$n, $p]): ?>
              <button class="sl-tb-item" type="button" data-sl-add="<?= $i ?>" data-name="<?= v2_e($n) ?>" data-price="<?= (int) $p ?>"><b><?= v2_e($n) ?></b><small><?= $p ?></small></button>
              <?php endforeach; ?>
            </div>
            <div class="sl-tb-cart">
              <p class="sl-tb-k">Coș</p>
              <ul id="sl-cart" class="sl-tb-lines"><li class="sl-tb-empty">Atinge un bilet ca să îl adaugi</li></ul>
              <p class="sl-tb-total"><span>Total</span><b id="sl-total">0 lei</b></p>
              <div class="sl-tb-pay">
                <button class="sl-tb-btn is-cash" type="button" data-sl-pay="cash"><?= v2_ic('coins') ?>Cash</button>
                <button class="sl-tb-btn is-card" type="button" data-sl-pay="card"><?= v2_ic('credit-card') ?>Card</button>
              </div>
              <p class="sl-tb-print" id="sl-print" role="status"><?= v2_ic('printer') ?>Bon pe imprimanta termică, după fiecare comandă</p>
            </div>
          </div>
        </div>
        <div class="sl-receipt" id="sl-receipt" aria-hidden="true">
          <p class="sl-rc-h">MUZEUL TĂU</p>
          <p class="sl-rc-sub">Bon fără valoare fiscală · exemplu</p>
          <ul id="sl-rc-lines"></ul>
          <p class="sl-rc-total"><span>TOTAL</span><b id="sl-rc-total">0 lei</b></p>
          <p class="sl-rc-qr"><?= v2_ic('qr-code') ?></p>
        </div>
      </div>
    </div>
  </section>

  <!-- ===================== SCANARE ===================== -->
  <section class="sec sl-scan" id="scanare" aria-labelledby="sl-scan-h">
    <div class="wrap sl-split is-rev">
      <div class="sl-split-copy">
        <p class="sl-kicker"><?= v2_ic('scan') ?>La intrare</p>
        <h2 id="sl-scan-h">Aplicația de scanare, pe telefon și pe tabletă</h2>
        <p class="sl-sub">Se instalează pe ecranul telefonului, ca orice aplicație. Scanează cu camera, iar dacă un cod nu se citește, îl tastezi. Răspunsul vine pe loc, cu sunet și vibrație, ca omul de la poartă să nu stea cu ochii pe ecran.</p>
        <ul class="sl-checks">
          <?php foreach ([
              'Trei răspunsuri clare: acces aprobat, deja scanat, bilet invalid',
              'Cod tastat manual, când biletul e șifonat sau ecranul e crăpat',
              'Porți de acces și oameni alocați pe fiecare poartă',
              'Listă de invitați și check-in fără bilet tipărit',
              'Ecranul nu se stinge cât scanezi, iar aplicația merge și pe tabletă',
              'Rapoarte pe tură: câți au intrat, când, pe ce poartă',
          ] as $t): ?>
          <li><?= v2_ic('check-circle') ?><?= v2_e($t) ?></li>
          <?php endforeach; ?>
        </ul>
        <p class="sl-hint"><?= v2_ic('info') ?>Aplicația de scanare se activează pe contul locației.</p>
      </div>

      <div class="sl-scanwrap">
        <div class="sl-ph is-big" id="sl-scanner" aria-label="Aplicația de scanare, exemplu">
          <div class="sl-ph-top"><span></span></div>
          <div class="sl-ph-scan">
            <p class="sl-ph-k">Scanare · Poarta 1</p>
            <div class="sl-ph-frame"><?= v2_ic('qr-code') ?><span class="sl-ph-laser"></span></div>
            <p class="sl-ph-state" id="sl-state"><?= v2_ic('check-circle') ?><span id="sl-state-t">ACCES APROBAT</span></p>
            <p class="sl-ph-sub" id="sl-state-sub">Bilet adult · 11:00</p>
            <div class="sl-ph-stats"><span><b id="sl-rate">18</b>scanări/min</span><span><b>412</b>intrați</span><span><b>37</b>așteptare</span></div>
          </div>
        </div>
      </div>
    </div>
  </section>

  <!-- ===================== HARDWARE ===================== -->
  <section class="sec sl-hw" aria-labelledby="sl-hw-h">
    <div class="wrap">
      <div class="sec-head">
        <h2 id="sl-hw-h">Ce îți trebuie ca să pornești</h2>
        <p class="sl-sub">Fără server, fără licențe și fără instalări complicate. Cel mai des, locațiile pornesc cu ce au deja în casă.</p>
      </div>
      <ul class="sl-hw-grid">
        <?php foreach ([
            ['scan', 'Un telefon sau o tabletă', 'Android sau iPhone, pentru scanare la intrare și pentru vânzare pe loc.'],
            ['printer', 'O imprimantă termică', 'De 58 sau 80 mm, pentru bon. Se tipărește din browser, fără drivere speciale.'],
            ['squares-four', 'Un calculator pentru ghișeu', 'Orice laptop sau desktop cu un browser modern. POS-ul rulează în browser.'],
            ['code', 'Site-ul tău, dacă ai unul', 'Pui widgetul de vânzare pe pagina ta și vinzi direct de acolo, cu aceleași bilete.'],
            ['file-text', 'Documente și facturi', 'Facturi pentru firme, documentele contului și rapoartele rămân în panou.'],
            ['headset', 'Oameni care răspund', 'Suport pe email și telefon, luni - vineri, 09:00 - 18:00, plus tichete din panou.'],
        ] as [$icon, $title, $text]): ?>
        <li><span class="sl-hw-ic"><?= v2_ic($icon) ?></span><b><?= v2_e($title) ?></b><span><?= v2_e($text) ?></span></li>
        <?php endforeach; ?>
      </ul>
    </div>
  </section>

  <!-- ===================== BANII ===================== -->
  <section class="sec sl-money" id="costuri" aria-labelledby="sl-money-h">
    <div class="wrap sl-split">
      <div class="sl-split-copy">
        <p class="sl-kicker is-light"><?= v2_ic('percent') ?>Costuri</p>
        <h2 id="sl-money-h">Comision 2%, plătit de cumpărător</h2>
        <p class="sl-sub is-light">Fără abonament lunar, fără cost de instalare și fără taxă pentru fiecare bilet emis la ghișeu. Comisionul este de 2% dacă vinzi exclusiv prin bilete.online și de 4% dacă vinzi biletele și în alte părți: 2% incluse în preț și 2% adăugate.</p>
        <ul class="sl-checks is-light">
          <li><?= v2_ic('check-circle') ?>Vezi soldul disponibil și ceri plata când vrei</li>
          <li><?= v2_ic('check-circle') ?>Biletele vândute la ghișeu nu au comision de platformă</li>
          <li><?= v2_ic('check-circle') ?>Documentele și facturile rămân în cont</li>
        </ul>
      </div>
      <div class="sl-calc">
        <p class="sl-calc-h">Cât înseamnă pentru tine</p>
        <div class="sl-calc-row">
          <label for="sl-qty">Bilete vândute online pe lună</label>
          <input id="sl-qty" type="number" inputmode="numeric" min="0" max="100000" step="10" value="400">
        </div>
        <div class="sl-calc-row">
          <label for="sl-price">Preț mediu pe bilet (lei)</label>
          <input id="sl-price" type="number" inputmode="numeric" min="0" max="10000" step="5" value="45">
        </div>
        <dl class="sl-calc-out">
          <div><dt>Încasezi din bilete</dt><dd id="sl-out-rev">18.000 lei</dd></div>
          <div><dt>Comision 2%, plătit de cumpărător</dt><dd id="sl-out-fee">360 lei</dd></div>
          <div class="is-total"><dt>Rămâne la tine</dt><dd id="sl-out-net">18.000 lei</dd></div>
        </dl>
        <p class="sl-calc-note">Comisionul se adaugă la prețul biletului, deci prețul tău rămâne întreg. Cumpărătorul plătește <span id="sl-out-buyer">45,90 lei</span> pe bilet.</p>
      </div>
    </div>
  </section>

  <!-- ===================== PLATFORMA ===================== -->
  <section class="sec sl-reach" aria-labelledby="sl-reach-h">
    <div class="wrap">
      <div class="sec-head">
        <h2 id="sl-reach-h">Nu vinzi doar dintr-un panou. Vinzi dintr-un loc unde oamenii caută deja</h2>
      </div>
      <ul class="sl-reach-grid">
        <?php if ($slAttractions): ?><li><b><?= v2_thousands($slAttractions) ?></b><span>atracții în catalog</span></li><?php endif; ?>
        <?php if ($slCities): ?><li><b><?= $slCities ?></b><span>orașe cu pagini proprii</span></li><?php endif; ?>
        <?php if ($slCategories): ?><li><b><?= $slCategories ?></b><span>categorii de experiențe</span></li><?php endif; ?>
        <li><b>2%</b><span>comision, plătit de cumpărător</span></li>
      </ul>
      <ul class="sl-reach-list">
        <?php foreach ([
            ['magnifying-glass', 'Pagina ta e făcută să fie găsită', 'Fiecare activitate și fiecare locație are pagina ei, optimizată pentru căutări, cu program, prețuri și disponibilitate.'],
            ['map-pin', 'Apari în oraș și în categorie', 'Ești în paginile de oraș, în categorii și în ghidurile editoriale, lângă activități căutate de aceiași oameni.'],
            ['gift', 'Carduri cadou și puncte', 'Cardurile cadou și punctele bonus aduc oameni înapoi, fără să construiești tu programul de fidelizare.'],
            ['star', 'Recenzii și recomandări', 'Recenziile clienților și recomandările automate îți trimit oameni noi către activitățile potrivite.'],
        ] as [$icon, $title, $text]): ?>
        <li><span class="sl-hw-ic"><?= v2_ic($icon) ?></span><div><b><?= v2_e($title) ?></b><p><?= v2_e($text) ?></p></div></li>
        <?php endforeach; ?>
      </ul>
    </div>
  </section>

  <!-- ===================== DEMO + FAQ ===================== -->
  <section class="sec sl-demo-sec" id="demo" aria-labelledby="sl-demo-h">
    <div class="wrap sl-demo-grid">
      <div class="sl-form-wrap">
        <h2 id="sl-demo-h">Hai să vedem împreună cum arată locația ta</h2>
        <p class="sl-sub">Îți arătăm panoul pe datele tale: ce activități ai publica, ce tipuri de bilete se potrivesc și cum ar arăta ziua de la ghișeu. Fără prezentare lungă.</p>
        <form class="sl-form" id="fv-form" novalidate>
          <p class="sl-form-err" id="fv-error" role="alert" tabindex="-1" hidden></p>
          <div class="sl-hp"><label for="fv-fax">Nu completa acest câmp</label><input id="fv-fax" name="fax" type="text" tabindex="-1" autocomplete="off"></div>
          <div class="sl-f2">
            <p class="sl-field"><label for="fv-name">Nume și prenume<span aria-hidden="true">*</span></label><input id="fv-name" name="contact_name" type="text" required maxlength="120" autocomplete="name"></p>
            <p class="sl-field"><label for="fv-email">Email<span aria-hidden="true">*</span></label><input id="fv-email" name="email" type="email" required maxlength="120" autocomplete="email"></p>
          </div>
          <div class="sl-f2">
            <p class="sl-field"><label for="fv-phone">Telefon</label><input id="fv-phone" name="phone" type="tel" maxlength="30" autocomplete="tel"></p>
            <p class="sl-field"><label for="fv-role">Rolul tău</label><span class="sl-sel"><select id="fv-role" name="role"><option value="">Alege</option><option>Proprietar</option><option>Manager locație</option><option>Marketing</option><option>Operațiuni / ghișeu</option><option>Altul</option></select><?= v2_ic('caret-down') ?></span></p>
          </div>
          <div class="sl-f2">
            <p class="sl-field"><label for="fv-venue">Numele locației<span aria-hidden="true">*</span></label><input id="fv-venue" name="location_name" type="text" required maxlength="160"></p>
            <p class="sl-field"><label for="fv-city">Orașul<span aria-hidden="true">*</span></label><input id="fv-city" name="city" type="text" required maxlength="80" list="ftr-cities"></p>
          </div>
          <div class="sl-f2">
            <p class="sl-field"><label for="fv-type">Tipul locației</label><span class="sl-sel"><select id="fv-type" name="venue_type"><option value="">Alege</option><?php foreach (array_slice($V2NAV['categories'], 0, 12) as $c): ?><option value="<?= v2_e($c['slug']) ?>"><?= v2_e($c['name']) ?></option><?php endforeach; ?><option value="other">Altceva</option></select><?= v2_ic('caret-down') ?></span></p>
            <p class="sl-field"><label for="fv-count">Câte activități ai</label><span class="sl-sel"><select id="fv-count" name="activities_count"><option value="">Alege</option><option>1 - 3</option><option>4 - 10</option><option>11 - 30</option><option>peste 30</option></select><?= v2_ic('caret-down') ?></span></p>
          </div>
          <p class="sl-field"><label for="fv-message">Ce ai vrea să rezolvi</label><textarea id="fv-message" name="message" rows="3" maxlength="2000" placeholder="De exemplu: vindem doar la ghișeu și vrem și online, sau avem cozi la intrare în weekend."></textarea></p>
          <p class="sl-consent"><input id="fv-consent" name="consent" type="checkbox" required><label for="fv-consent">Sunt de acord să fiu contactat despre listarea locației. <a href="/confidentialitate">Politica de confidențialitate</a></label></p>
          <button class="btn btn-primary" type="submit" id="fv-submit">Trimite solicitarea<?= v2_ic('arrow-right') ?></button>
        </form>
        <div class="sl-done" id="fv-done" hidden>
          <span class="sl-done-ic"><?= v2_ic('check-circle') ?></span>
          <h3 id="fv-done-h">Am primit solicitarea</h3>
          <p>Ți-am trimis o confirmare pe <b id="fv-done-email"></b>. Revenim cu o propunere de demonstrație.</p>
        </div>
        <p class="sl-alt">Preferi direct? Scrie-ne la <a href="mailto:<?= v2_e(SUPPORT_EMAIL) ?>"><?= v2_e(SUPPORT_EMAIL) ?></a> sau sună la <a href="tel:+40750292962">0750 292 962</a>, luni - vineri, 09:00 - 18:00.</p>
      </div>

      <div class="sl-faq">
        <h2 class="sl-faq-h">Întrebări frecvente</h2>
        <?php foreach ($slFaq as $i => [$q, $a]): ?>
        <details class="qa"<?= $i === 0 ? ' open' : '' ?>><summary><?= v2_e($q) ?><span class="pm"><?= v2_ic('plus') ?></span></summary><p><?= v2_e($a) ?></p></details>
        <?php endforeach; ?>
      </div>
    </div>
  </section>

  <div class="sl-bar" id="sl-bar" hidden>
    <span><b>Vrei să vezi panoul pe datele tale?</b><small>Demonstrație de 20 de minute, fără obligații.</small></span>
    <a class="btn btn-primary" href="#demo">Cere demo</a>
  </div>
</main>
<?php
include __DIR__ . '/includes/v2/footer.php';
