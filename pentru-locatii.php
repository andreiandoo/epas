<?php
/**
 * For venues: /pentru-locatii (v2 design). Static sales page for venues and activity operators.
 *
 * Top to bottom: hero with a dashboard mockup, who it's for, what they get (#ce-primesti), the SEO engine, the flow
 * from listing to check-in, modules in tabs (base.js [data-tabs]), the commercial model, the demo request (#demo), FAQ,
 * final CTA. JSON-LD: Service, FAQPage (from the visible questions), BreadcrumbList.
 *
 * The demo form posted to /api/contact-locatii.php, which doesn't exist (404): every demo request was lost.
 * for-venues.js now sends it into the real lead pipeline (proxy leads.create → core LeadsController::create, the same
 * one /inregistrare-locatie uses), which requires the city. /pricing-locatii was a 404 too; the pricing links go to the
 * commission section of /devino-partener. Payment methods follow checkout; the SEO URL examples follow real routes.
 */

require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/api.php';

// 30-minute page cache: static content, the form posts through the proxy.
$pageCacheTTL = 1800;
require_once __DIR__ . '/includes/page-cache.php';

require_once __DIR__ . '/includes/nav-helpers.php';
require_once __DIR__ . '/includes/v2/helpers.php';
require_once __DIR__ . '/includes/v2/nav.php';

$fvWho = [
    ['Escape rooms', 'Sloturi orare, capacitate per cameră, beneficiari diferiți, bilete de grup și check-in rapid.', ''],
    ['Muzee & expoziții', 'Bilete de acces, expoziții temporare, tururi ghidate, copii/adulți, gratuități și program.', 'is-mint'],
    ['Parcuri & agrement', 'Pachete, categorii de vârstă, acces pe zi, extra-opțiuni și capacitate.', ''],
    ['Peșteri & rezervații', 'Tururi, reguli de acces, nivel de dificultate, echipament, ghid și sezonalitate.', ''],
    ['Ateliere & educație', 'Locuri limitate, vârste recomandate, materiale incluse, grupuri școlare.', 'is-deep'],
    ['Tururi & experiențe', 'City walks, tururi gastronomice, tururi istorice, experiențe turistice și private.', ''],
];
$fvGet = [
    ['01 · SEO', 'Pagini care pot atrage trafic organic.', 'Pagină de locație, pagini pentru activități, categorii, orașe și intenții precum „activități copii”, „weekend”, „indoor”, „sub 50 lei”.', ''],
    ['02 · Checkout', 'Cumpărare rapidă, clară, modernă.', 'Card (inclusiv Apple Pay și Google Pay), Card Cultural acolo unde este acceptat, beneficiari diferiți, cont automat, taxe afișate separat și opțiuni comerciale.', 'is-deep'],
    ['03 · QR', 'Bilete digitale și check-in rapid.', 'Fiecare bilet are cod unic, status, beneficiar și poate fi scanat la intrare pentru control clar al accesului.', ''],
    ['04 · Dashboard', 'Comenzi, clienți, scanări și rapoarte.', 'Vezi vânzările, biletele emise, participanții, disponibilitatea, statusurile și performanța activităților.', ''],
    ['05 · Growth', 'Promoții, vouchere, carduri cadou.', 'Poți rula coduri promo, campanii sezoniere, carduri cadou, puncte bonus și oferte pentru audiențe specifice.', ''],
    ['06 · Trust', 'O experiență mai bună pentru clienți.', 'Clientul vede clar ce cumpără, unde merge, cum intră, ce include biletul și ce se întâmplă după plată.', 'is-mint'],
];
$fvSeoUrls = [
    ['/brasov/activitati-copii', 'oraș + intenție'],
    ['/escape-rooms', 'categorie'],
    ['/locatie/mystery-rooms', 'pagină locație'],
    ['/activitate/camera-13', 'pagină activitate'],
];
$fvAnatomy = [
    ['Titlu + descriere clare', 'Ce este, unde este, pentru cine este.'],
    ['Date structurate', 'Breadcrumbs, FAQ, local entity, activitate.'],
    ['Întrebări practice', 'Program, acces, vârstă, durată, reguli, parcare.'],
    ['Internal linking', 'Orașe, categorii, activități similare, ghiduri.'],
];
$fvOps = [
    ['Onboarding', 'Date locație, activități, bilete, politici.'],
    ['Publicare', 'Pagini SEO și activități disponibile online.'],
    ['Vânzare', 'Checkout, plăți, comisioane, bilete QR.'],
    ['Scanare', 'Validare rapidă la intrare, statusuri clare.'],
    ['Creștere', 'Rapoarte, recenzii, promoții, campanii.'],
];
$fvTiers = [
    ['Start', 'Listare', 'Pagini, checkout, bilete QR.', ''],
    ['Growth', 'Promovare', 'SEO, campanii, vizibilitate.', 'is-deep'],
    ['Pro', 'Operațional', 'Rapoarte, staff, integrări.', ''],
];
// venue type → core category slug ('' = none fits: sent as category_other)
$fvVenueTypes = [
    'escape-rooms' => 'Escape room',
    'muzee-expozitii' => 'Muzeu / expoziție',
    'parcuri-de-distractii' => 'Parc de distracții',
    'parcuri-de-aventura' => 'Parc de aventură',
    'natura-outdoor' => 'Peșteră / rezervație',
    'ateliere-experiente-creative' => 'Atelier / educație',
    'tururi-experiente-turistice' => 'Tururi / experiențe',
    'other' => 'Altceva',
];
$fvRoles = ['Owner / Administrator', 'Marketing', 'Operațional', 'Alt rol'];
$fvCounts = ['1 activitate', '2-5 activități', '6-15 activități', '15+ activități'];
$fvFaqs = [
    ['Ce tipuri de locații pot folosi platforma?', 'Platforma este potrivită pentru escape rooms, muzee, expoziții, parcuri de distracții, parcuri de aventură, peșteri, rezervații naturale, ateliere, tururi ghidate, ferme educative și alte experiențe care vând bilete sau rezervări.'],
    ['Pot avea mai multe activități în aceeași locație?', 'Da. O locație poate avea o pagină principală și mai multe pagini pentru activități, camere, tururi, pachete sau tipuri de acces.'],
    ['Cum se validează biletele?', 'Fiecare bilet este emis cu un cod QR unic. La intrare, personalul locației îl scanează din interfața de check-in, iar sistemul afișează statusul biletului.'],
    ['Mă ajută cu SEO?', 'Da. Platforma este gândită pentru pagini indexabile: locație, activități, orașe, categorii și pagini de intenție precum activități pentru copii, weekend, indoor sau outdoor.'],
    ['Pot crea promoții sau coduri de reducere?', 'Da, platforma poate include coduri promoționale, campanii sezoniere, vouchere, puncte bonus și carduri cadou, în funcție de configurare.'],
    ['Ce se întâmplă după ce primesc o comandă?', 'Comanda apare în dashboard, biletele sunt emise automat, clientul primește confirmarea, iar tu poți vedea participanții și valida biletele la intrare.'],
];

$pageTitleRaw = 'Pentru locații — vinde bilete online pentru activități pe bilete.online';
$pageDescription = 'Listează-ți locația pe bilete.online și vinde bilete online pentru escape rooms, muzee, parcuri, ateliere, peșteri, rezervații și experiențe locale. Pagini SEO, checkout, QR, scanare, rapoarte și dashboard.';
$canonicalUrl = SITE_URL . '/pentru-locatii';
$structuredData = [
    [
        '@context' => 'https://schema.org',
        '@type' => 'Service',
        'name' => 'bilete.online pentru locații',
        'serviceType' => 'Platformă de vânzare bilete online pentru activități și locații',
        'provider' => ['@type' => 'Organization', 'name' => 'bilete.online', 'url' => SITE_URL . '/'],
        'areaServed' => ['@type' => 'Country', 'name' => 'România'],
        'description' => 'Platformă pentru locații care vând bilete online la activități: pagini SEO, checkout, bilete QR, scanare, dashboard, rapoarte, carduri cadou și puncte bonus.',
        'audience' => ['@type' => 'BusinessAudience', 'audienceType' => 'Locații de agrement, muzee, escape rooms, parcuri, ateliere, operatori de tururi și experiențe'],
    ],
    [
        '@context' => 'https://schema.org',
        '@type' => 'FAQPage',
        'mainEntity' => array_map(fn ($f) => ['@type' => 'Question', 'name' => $f[0], 'acceptedAnswer' => ['@type' => 'Answer', 'text' => $f[1]]], $fvFaqs),
    ],
    [
        '@context' => 'https://schema.org',
        '@type' => 'BreadcrumbList',
        'itemListElement' => [
            ['@type' => 'ListItem', 'position' => 1, 'name' => 'Acasă', 'item' => SITE_URL . '/'],
            ['@type' => 'ListItem', 'position' => 2, 'name' => 'Pentru locații', 'item' => $canonicalUrl],
        ],
    ],
];

$v2Styles = ['for-venues.css'];
$v2Scripts = ['for-venues.js'];
$v2HeaderOverlay = true;
$v2ClientData = ['supportEmail' => SUPPORT_EMAIL];
$v2HeadExtra = '<meta name="keywords" content="vânzare bilete activități, platformă bilete locații, bilete online escape room, bilete online muzeu, bilete QR activități, sistem ticketing locații, platformă rezervări activități">'
    // head.php strips utm_* from the address bar a moment later; keep them for the demo lead (read at runtime, so the
    // page cache, which ignores utm_* in its key, never bakes one visitor's campaign into the HTML)
    . '<script>(function(){try{var q=new URLSearchParams(location.search),u={};["utm_source","utm_medium","utm_campaign","utm_content","utm_term"].forEach(function(k){if(q.get(k))u[k]=q.get(k).slice(0,150)});window.BO_UTM=u;}catch(e){}})();</script>';

include __DIR__ . '/includes/v2/head.php';
include __DIR__ . '/includes/v2/header.php';
?>
<main id="main" tabindex="-1">
  <!-- HERO -->
  <section class="fv-hero" aria-labelledby="fv-h">
    <svg class="deco-arches" viewBox="0 0 400 400" aria-hidden="true" focusable="false"><path d="M40 400V200a160 160 0 0 1 320 0v200"/><path d="M90 400V200a110 110 0 0 1 220 0v200"/><path d="M140 400V200a60 60 0 0 1 120 0v200"/></svg>
    <div class="fv-in">
      <div class="fv-copy">
        <nav class="crumbs" aria-label="Breadcrumb"><a href="/">Acasă</a><span aria-hidden="true">/</span><span aria-current="page">Pentru locații</span></nav>
        <p class="fv-kicker">Platformă pentru locații · SEO · checkout · QR</p>
        <h1 class="fv-h" id="fv-h">Transformă activitățile tale în bilete care se vând online.</h1>
        <p class="fv-lead">bilete.online ajută locațiile să fie descoperite organic, să vândă bilete rapid și să gestioneze accesul cu QR — fără să construiască de la zero o platformă de ticketing.</p>
        <div class="fv-cta">
          <a class="btn btn-light" href="#demo">Solicită demo<?= v2_ic('arrow-right') ?></a>
          <a class="btn btn-outline-light" href="#ce-primesti">Vezi ce primești</a>
        </div>
        <dl class="fv-stats">
          <div><dt>Pagini</dt><dd>SEO</dd></div>
          <div><dt>Acces</dt><dd>QR</dd></div>
          <div><dt>Date</dt><dd>live</dd></div>
        </dl>
      </div>

      <div class="fv-mock-col">
        <!-- the header turns solid when the white dashboard reaches it -->
        <div id="hdr-sentinel" aria-hidden="true"></div>
        <div class="fv-mock" aria-hidden="true">
          <div class="fv-dash">
            <div class="fv-dash-top">
              <div><small>Organizer dashboard</small><b>Mystery Rooms Brașov</b></div>
              <span class="fv-live">Live</span>
            </div>
            <div class="fv-dash-stats">
              <div><small>Vânzări</small><b>18.4k</b></div>
              <div class="is-mint"><small>Bilete</small><b>214</b></div>
              <div><small>Scanate</small><b>38</b></div>
            </div>
            <div class="fv-dash-rows">
              <div><p><span>Camera 13</span><span>9.200 lei</span></p><i><em class="is-pulse"></em></i></div>
              <div><p><span>Laboratorul 7</span><span>6.140 lei</span></p><i><em style="width:54%"></em></i></div>
            </div>
          </div>
          <div class="fv-float is-checkin">
            <small>Check-in</small>
            <div class="fv-qr"><span>QR<br>valid</span><em class="fv-scan"></em></div>
          </div>
          <div class="fv-float is-seo">
            <small>SEO local</small>
            <b>/brasov/activitati-copii</b>
            <span>Pagini pentru oraș, categorie, locație și activități.</span>
          </div>
        </div>
      </div>
    </div>
  </section>

  <!-- WHO -->
  <section class="sec fv-who" aria-labelledby="fv-who-h">
    <div class="wrap fv-split">
      <div class="fv-intro">
        <p class="kicker">Pentru cine</p>
        <h2 id="fv-who-h">Nu vinzi doar bilete. Vinzi o experiență care trebuie descoperită.</h2>
        <p>Platforma este construită pentru activități diferite, cu modele diferite de acces: sloturi orare, bilete simple, pachete, tururi ghidate, acces pe zi, grupuri sau evenimente private.</p>
      </div>
      <div class="fv-who-grid">
        <?php foreach ($fvWho as [$whoTitle, $whoText, $whoTone]): ?>
        <article class="fv-card <?= $whoTone ?>"><h3><?= v2_e($whoTitle) ?></h3><p><?= v2_e($whoText) ?></p></article>
        <?php endforeach; ?>
      </div>
    </div>
  </section>

  <!-- WHAT YOU GET -->
  <section class="fv-band" id="ce-primesti" aria-labelledby="fv-get-h">
    <div class="wrap">
      <div class="fv-head">
        <p class="kicker">Ce primești</p>
        <h2 id="fv-get-h">Un stack complet pentru vânzarea activităților tale.</h2>
        <p>bilete.online combină pagini publice optimizate, flux de cumpărare, emitere bilete, operațiuni la intrare și instrumente de creștere.</p>
      </div>
      <div class="fv-get-grid">
        <?php foreach ($fvGet as [$getK, $getTitle, $getText, $getTone]): ?>
        <article class="fv-get <?= $getTone ?>"><p class="fv-get-k"><?= v2_e($getK) ?></p><h3><?= v2_e($getTitle) ?></h3><p><?= v2_e($getText) ?></p></article>
        <?php endforeach; ?>
      </div>
    </div>
  </section>

  <!-- SEO ENGINE -->
  <section class="sec fv-seo" aria-labelledby="fv-seo-h">
    <div class="wrap fv-seo-grid">
      <div>
        <p class="kicker">SEO engine</p>
        <h2 id="fv-seo-h">Nu depinzi doar de reclame.</h2>
        <p class="fv-p">Fiecare activitate poate deveni o pagină de vânzare optimizată. Locația ta poate apărea în pagini de oraș, categorie și intenție — nu doar într-o listă generică.</p>
        <div class="fv-urls">
          <?php foreach ($fvSeoUrls as [$urlPath, $urlLabel]): ?>
          <div><b><?= v2_e($urlPath) ?></b><span><?= v2_e($urlLabel) ?></span></div>
          <?php endforeach; ?>
        </div>
      </div>
      <div class="fv-anatomy">
        <div class="fv-anatomy-head"><p>Anatomia unei pagini SEO</p><h3>Activitatea ta devine găsibilă.</h3></div>
        <div class="fv-anatomy-list">
          <?php foreach ($fvAnatomy as [$anaTitle, $anaText]): ?>
          <div><b><?= v2_e($anaTitle) ?></b><span><?= v2_e($anaText) ?></span></div>
          <?php endforeach; ?>
        </div>
      </div>
    </div>
  </section>

  <!-- OPS FLOW -->
  <section class="fv-ops" aria-labelledby="fv-ops-h">
    <div class="wrap">
      <div class="fv-head">
        <p class="fv-dark-k">Operațional</p>
        <h2 id="fv-ops-h">De la listare la check-in.</h2>
        <p>Fluxul este construit pentru echipe mici: publici activitatea, vinzi bilete, scanezi la intrare și urmărești rezultatele.</p>
      </div>
      <ol class="fv-ops-grid">
        <?php foreach ($fvOps as $oi => [$opTitle, $opText]): ?>
        <li><span class="fv-ops-n"><?= $oi + 1 ?></span><h3><?= v2_e($opTitle) ?></h3><p><?= v2_e($opText) ?></p></li>
        <?php endforeach; ?>
      </ol>
    </div>
  </section>

  <!-- MODULES (tabs) -->
  <section class="sec fv-modules" aria-labelledby="fv-mod-h">
    <div class="wrap fv-split is-wide">
      <div class="fv-intro">
        <p class="kicker">Module</p>
        <h2 id="fv-mod-h">Alegi ce ai nevoie. Platforma poate crește cu tine.</h2>
      </div>
      <div>
        <div class="fv-tabs" role="tablist" aria-label="Module" data-tabs>
          <button type="button" role="tab" id="fv-tab-tickets" aria-controls="fv-panel-tickets" aria-selected="true">Bilete</button>
          <button type="button" role="tab" id="fv-tab-calendar" aria-controls="fv-panel-calendar" aria-selected="false" tabindex="-1">Disponibilitate</button>
          <button type="button" role="tab" id="fv-tab-growth" aria-controls="fv-panel-growth" aria-selected="false" tabindex="-1">Growth</button>
          <button type="button" role="tab" id="fv-tab-reports" aria-controls="fv-panel-reports" aria-selected="false" tabindex="-1">Rapoarte</button>
        </div>
        <div class="fv-panel" role="tabpanel" id="fv-panel-tickets" aria-labelledby="fv-tab-tickets">
          <h3>Tipuri de bilete și pachete</h3>
          <p>Creezi bilete simple, bilete copil/adult, pachete de grup, bilete cu interval orar, extra-opțiuni sau bilete pentru tururi.</p>
          <div class="fv-panel-grid"><div>Adult · 95 lei</div><div>Copil · 45 lei</div><div>Grup · 340 lei</div></div>
        </div>
        <div class="fv-panel" role="tabpanel" id="fv-panel-calendar" aria-labelledby="fv-tab-calendar" hidden>
          <h3>Disponibilitate și sloturi</h3>
          <p>Controlezi zile, ore, capacitate, închideri, excepții, sezonalitate și intervale cu disponibilitate limitată.</p>
          <div class="fv-days"><?php for ($day = 1; $day <= 14; $day++): ?><span<?= $day % 4 === 0 ? ' class="is-busy"' : '' ?>><?= $day ?></span><?php endfor; ?></div>
        </div>
        <div class="fv-panel" role="tabpanel" id="fv-panel-growth" aria-labelledby="fv-tab-growth" hidden>
          <h3>Promoții, carduri cadou, puncte</h3>
          <p>Rulezi coduri promo, campanii sezoniere, beneficii prin puncte bonus și eligibilitate pentru carduri cadou sau vouchere.</p>
          <div class="fv-chips"><span class="is-on">WEEKEND10</span><span class="is-mint">Puncte duble</span><span>Card cadou</span></div>
        </div>
        <div class="fv-panel" role="tabpanel" id="fv-panel-reports" aria-labelledby="fv-tab-reports" hidden>
          <h3>Rapoarte și date utile</h3>
          <p>Vezi ce se vinde, când, pentru cine, care activități performează și ce intervale au conversie mai bună.</p>
          <div class="fv-panel-grid is-stats"><div><small>Vânzări</small><b>18.4k</b></div><div><small>Comenzi</small><b>96</b></div><div><small>Conversie</small><b>4.2%</b></div></div>
        </div>
      </div>
    </div>
  </section>

  <!-- PRICING TEASER -->
  <section class="fv-band" aria-labelledby="fv-price-h">
    <div class="wrap fv-price-grid">
      <div>
        <p class="kicker">Model comercial</p>
        <h2 id="fv-price-h">Costuri clare, fără infrastructură construită de la zero.</h2>
        <p class="fv-p">Modelul poate include comision per bilet, servicii opționale sau pachete de promovare. Ideea este simplă: plătești pentru infrastructură care vinde, nu pentru promisiuni vagi.</p>
        <a class="btn btn-primary fv-price-cta" href="/devino-partener#bani">Vezi pricing locații<?= v2_ic('arrow-right') ?></a>
      </div>
      <div class="fv-tiers">
        <?php foreach ($fvTiers as [$tierK, $tierTitle, $tierText, $tierTone]): ?>
        <article class="fv-tier <?= $tierTone ?>"><p class="fv-get-k"><?= v2_e($tierK) ?></p><h3><?= v2_e($tierTitle) ?></h3><p><?= v2_e($tierText) ?></p></article>
        <?php endforeach; ?>
      </div>
    </div>
  </section>

  <!-- DEMO FORM -->
  <section class="sec fv-demo" id="demo" aria-labelledby="fv-demo-h">
    <div class="wrap fv-split">
      <div class="fv-intro">
        <p class="kicker">Solicită demo</p>
        <h2 id="fv-demo-h">Hai să vedem cum ar arăta locația ta pe bilete.online.</h2>
        <p>Trimite câteva detalii despre locație și activități. Răspunsul ideal îți arată ce pagini ar trebui create, ce tipuri de bilete se potrivesc și ce oportunități SEO ai.</p>
        <div class="fv-prep">
          <b>Ce poți pregăti înainte:</b>
          <ul>
            <li><?= v2_ic('check') ?>numele locației și orașul;</li>
            <li><?= v2_ic('check') ?>tipurile de activități;</li>
            <li><?= v2_ic('check') ?>prețuri și capacitate;</li>
            <li><?= v2_ic('check') ?>program și reguli de acces.</li>
          </ul>
        </div>
      </div>

      <div class="fv-form-wrap">
        <form class="fv-form" id="fv-form" novalidate>
          <p class="fv-error" id="fv-error" role="alert" tabindex="-1" hidden></p>
          <!-- people never see or reach this; a bot that fills it gets a quiet "sent" -->
          <div class="fv-trap" aria-hidden="true"><label for="fv-fax">Fax (nu completa)</label><input id="fv-fax" name="fax" type="text" tabindex="-1" autocomplete="off"></div>
          <div class="fv-fields">
            <div class="fv-field"><label for="fv-name">Nume contact</label><input id="fv-name" name="contact_name" type="text" autocomplete="name" maxlength="160" required placeholder="Nume și prenume"></div>
            <div class="fv-field"><label for="fv-email">Email</label><input id="fv-email" name="email" type="email" inputmode="email" autocomplete="email" spellcheck="false" maxlength="190" required placeholder="email@locatie.ro"></div>
            <div class="fv-field"><label for="fv-phone">Telefon</label><input id="fv-phone" name="phone" type="tel" autocomplete="tel" maxlength="40" placeholder="+40..."></div>
            <div class="fv-field"><label for="fv-role">Rol</label><select class="select" id="fv-role" name="role"><?php foreach ($fvRoles as $role): ?><option><?= v2_e($role) ?></option><?php endforeach; ?></select></div>
            <div class="fv-field"><label for="fv-venue">Nume locație</label><input id="fv-venue" name="location_name" type="text" autocomplete="organization" maxlength="200" required placeholder="ex. Mystery Rooms Brașov"></div>
            <div class="fv-field"><label for="fv-city">Oraș</label><input id="fv-city" name="city" type="text" autocomplete="address-level2" maxlength="120" required placeholder="ex. Brașov"></div>
            <div class="fv-field"><label for="fv-type">Tip locație</label><select class="select" id="fv-type" name="venue_type"><?php foreach ($fvVenueTypes as $typeSlug => $typeLabel): ?><option value="<?= v2_e($typeSlug) ?>"><?= v2_e($typeLabel) ?></option><?php endforeach; ?></select></div>
            <div class="fv-field"><label for="fv-count">Câte activități vinzi?</label><select class="select" id="fv-count" name="activities_count"><?php foreach ($fvCounts as $count): ?><option><?= v2_e($count) ?></option><?php endforeach; ?></select></div>
            <div class="fv-field is-wide"><label for="fv-message">Ce vrei să vinzi online?</label><textarea id="fv-message" name="message" rows="5" maxlength="1800" placeholder="Descrie activitățile, tipurile de bilete, programul și ce probleme ai acum cu vânzarea sau rezervările."></textarea></div>
            <label class="fv-check is-wide"><input id="fv-consent" name="consent" type="checkbox" required><span>Accept să fiu contactat pentru o discuție despre listarea locației pe bilete.online.</span></label>
          </div>
          <button class="btn btn-primary fv-submit" id="fv-submit" type="submit">Trimite solicitarea</button>
        </form>
        <div class="fv-done" id="fv-done" hidden>
          <span class="fv-done-ic" aria-hidden="true"><?= v2_ic('check') ?></span>
          <h3 id="fv-done-h" tabindex="-1">Mulțumim!</h3>
          <p>Cererea ta a ajuns la echipa bilete.online. Te contactăm în următoarea zi lucrătoare pe <strong id="fv-done-email"></strong>.</p>
          <a class="btn btn-ghost" href="/devino-partener">Vezi cum funcționează parteneriatul</a>
        </div>
      </div>
    </div>
  </section>

  <!-- FAQ -->
  <section class="sec fv-faq" aria-labelledby="fv-faq-h">
    <div class="wrap fv-faq-grid">
      <div>
        <p class="kicker">FAQ</p>
        <h2 id="fv-faq-h">Întrebări frecvente</h2>
      </div>
      <div>
        <?php foreach ($fvFaqs as $fi => [$faqQ, $faqA]): ?>
        <details class="qa"<?= $fi === 0 ? ' open' : '' ?>><summary><?= v2_e($faqQ) ?><span class="pm"><?= v2_ic('plus') ?></span></summary><p><?= v2_e($faqA) ?></p></details>
        <?php endforeach; ?>
      </div>
    </div>
  </section>

  <!-- FINAL CTA -->
  <section class="sec fv-final-sec">
    <div class="wrap">
      <div class="fv-final">
        <div>
          <p class="fv-final-k">Ready to list?</p>
          <h2>Locația ta poate deveni următoarea activitate descoperită online.</h2>
          <p>Dacă ai o activitate pe care oamenii ar trebui să o descopere, bilete.online poate fi infrastructura care o vinde.</p>
        </div>
        <div class="fv-final-cta">
          <a class="btn fv-btn-white" href="#demo">Solicită demo</a>
          <a class="btn btn-outline-light" href="/devino-partener#bani">Vezi pricing</a>
        </div>
      </div>
    </div>
  </section>
</main>
<?php include __DIR__ . '/includes/v2/footer.php'; ?>
