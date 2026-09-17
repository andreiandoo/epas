<?php
/**
 * How it works: /cum-functioneaza (v2 design). Static content, no API calls.
 *
 * Top to bottom: hero with a ticket mockup, a strip of activity types, three value props, the five steps (#pasii),
 * activity types with photos, the checkout explained (with a sample summary), the customer account, four secondary
 * props, the pitch for venues, FAQ, final CTA. JSON-LD: HowTo, FAQPage.
 *
 * Payment methods follow what checkout offers (card with Apple Pay / Google Pay, Card Cultural when the event accepts
 * it); the old page also listed Revolut Pay and RoPay, which checkout doesn't have.
 */

require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/api.php';

// 30-minute page cache: static content.
$pageCacheTTL = 1800;
require_once __DIR__ . '/includes/page-cache.php';

require_once __DIR__ . '/includes/nav-helpers.php';
require_once __DIR__ . '/includes/v2/helpers.php';
require_once __DIR__ . '/includes/v2/nav.php';

$hwPayments = ['Card', 'Apple Pay', 'Google Pay', 'Card Cultural'];

// the mockup ticket always shows an upcoming Saturday, never a date in the past; short months keep the ticket's
// Data / Ora row on one line
$hwMonths = ['ian.', 'feb.', 'mar.', 'apr.', 'mai', 'iun.', 'iul.', 'aug.', 'sept.', 'oct.', 'nov.', 'dec.'];
$hwTicketDay = strtotime('saturday +4 weeks');
$hwTicketDate = date('j', $hwTicketDay) . ' ' . $hwMonths[(int) date('n', $hwTicketDay) - 1] . ' ' . date('Y', $hwTicketDay);

$hwProps = [
    ['map-pin', 'is-green', 'Găsești locuri de făcut, nu doar evenimente.', 'bilete.online este gândit pentru activități: camere de evadare, muzee, parcuri, tururi, natură, ateliere, experiențe pentru copii și multe altele.'],
    ['check-circle', 'is-deep', 'Știi ce cumperi înainte să ajungi acolo.', 'Fiecare pagină arată ce include biletul, cât durează, pentru cine e potrivit, unde mergi, cum intri și ce reguli trebuie să știi.'],
    ['coins', 'is-yellow', 'Primești beneficii la fiecare achiziție.', 'Comenzile pot aduce puncte bonus, cardurile cadou se pot folosi pe platformă, iar protecția bilet îți oferă flexibilitate suplimentară.'],
];

$hwSteps = [
    ['Descoperire', 'Alegi ce vrei să faci.', 'Cauți după oraș, categorie, intenție sau context: activități pentru copii, indoor, weekend, sub 50 lei, zile ploioase, cupluri, grupuri sau team building.', 'Exemple', 'chips', ['Brașov', 'copii', 'indoor', 'azi']],
    ['Selecție', 'Verifici detaliile și alegi biletele.', 'Pe pagina activității vezi descrierea, programul, prețul, locația, durata, vârsta recomandată, regulile, beneficiile incluse și întrebările frecvente.', 'Decizii', 'list', ['dată & oră', 'tip bilet', 'număr persoane', 'beneficii opționale']],
    ['Checkout', 'Plătești online și poți personaliza comanda.', 'Poți continua ca vizitator, te poți loga în cont sau îți poți crea cont automat. Dacă sunt mai mulți beneficiari, poți pune nume diferite pe biletele cumpărate.', 'Plată', 'chips', $hwPayments],
    ['Emitere', 'Primești biletele cu QR.', 'După confirmarea plății, biletele sunt emise electronic. Le primești pe email, le vezi în cont și le poți descărca PDF sau adăuga în calendar.', 'Ai acces la', 'list', ['PDF bilet', 'QR code', 'email confirmare', 'calendar']],
    ['Acces', 'Mergi la locație și scanezi biletul.', 'La intrare arăți codul QR de pe telefon. Locația validează biletul, iar tu intri fără tipărire obligatorie și fără să cauți confirmări prin email.', '', 'qr', []],
];

$hwActivities = [
    ['escape-rooms', 'Grupuri', 'Escape rooms', 'Alegi ora, numărul de persoane și primești biletele pentru toată echipa.', 'Echipă într-un escape room'],
    ['muzee-expozitii', 'Cultură', 'Muzee & expoziții', 'Cumperi bilete de acces, tururi ghidate sau expoziții temporare.', 'Vizitatori într-o sală de muzeu'],
    ['parcuri-de-aventura', 'Outdoor', 'Parcuri & aventură', 'Alegi pachete, categorii de vârstă, intervale sau bilete de acces.', 'Traseu de aventură prin copaci'],
    ['natura-outdoor', 'Natură', 'Peșteri & rezervații', 'Vezi program, reguli de acces, dificultate și detalii pentru vizitare.', 'Peisaj de munte'],
];

$hwCheckout = [
    ['Beneficiari diferiți', 'Poți pune alt nume pe fiecare bilet, util pentru grupuri sau cadouri.'],
    ['Cont automat', 'Poți cumpăra rapid, iar contul se poate crea automat după comandă.'],
    ['Puncte bonus', 'Vezi ce câștigi și poți folosi punctele disponibile.'],
    ['Protecție bilet', 'Poți adăuga flexibilitate suplimentară pentru retur, unde este disponibilă.'],
];
$hwSummary = [
    ['Bilete', '316,00 lei', ''],
    ['Comision platformă — 2% / bilet', '6,32 lei', ''],
    ['Protecție bilet', '60,00 lei', ''],
    ['Puncte bonus folosite', '− 8,20 lei', 'is-minus'],
    ['Taxă procesare plată', '5,83 lei', ''],
];

$hwAccount = [
    ['Biletele mele', 'PDF-uri, coduri QR, statusuri, calendar și detalii de acces.', ''],
    ['Comenzile mele', 'Istoric, totaluri, comisioane, taxe, chitanțe și statusuri.', ''],
    ['Punctele mele', 'Sold disponibil, istoric și conversie în discount.', 'is-mint'],
    ['Carduri cadou', 'Carduri primite, cumpărate, solduri și coduri active.', 'is-deep'],
];
$hwSecondary = [
    ['QR', 'Coduri unice', 'Fiecare bilet are cod propriu, status și validare la intrare.'],
    ['Payment', 'Plăți moderne', 'Card și wallet-uri digitale, în funcție de configurarea disponibilă.'],
    ['Support', 'Recuperare comandă', 'Poți recupera biletele cu emailul și numărul comenzii.'],
    ['SEO', 'Pagini clare', 'Fiecare activitate are informații utile, nu doar un buton de cumpărare.'],
];
$hwVenue = [
    ['SEO', 'Pagini pentru locație, activități, orașe și categorii.'],
    ['QR', 'Scanare rapidă și statusuri clare pentru bilete.'],
    ['Data', 'Comenzi, clienți, rapoarte și disponibilitate.'],
    ['Growth', 'Promoții, carduri cadou, puncte și recenzii.'],
];
$hwFaqs = [
    ['Trebuie să printez biletul?', 'Nu. În mod normal poți arăta codul QR de pe telefon. Dacă o locație are reguli speciale, acestea sunt afișate pe pagina activității și în bilet.'],
    ['Când primesc biletele?', 'După confirmarea plății, biletele sunt emise electronic și trimise pe email. Dacă ai cont, le găsești și în zona „Biletele mele”.'],
    ['Pot cumpăra pentru altcineva?', 'Da. Poți cumpăra bilete pentru alt beneficiar, poți pune nume diferite pe bilete și poți folosi carduri cadou pentru experiențe.'],
    ['Ce se întâmplă dacă plata rămâne în așteptare?', 'Dacă plata este în așteptare, comanda nu este pierdută. Biletele se emit automat după confirmarea procesatorului. Dacă plata eșuează, poți relua checkout-ul.'],
    ['Cum funcționează punctele bonus?', 'La comenzile eligibile primești puncte bonus. Ele apar în cont și pot fi folosite ca reducere în comenzile viitoare, conform regulamentului programului.'],
];
$hwMarquee = ['Escape rooms', 'Muzee', 'Parcuri de distracții', 'Peșteri', 'Rezervații', 'Ateliere', 'Carduri cadou', 'Puncte bonus'];

$pageTitleRaw = 'Cum funcționează bilete.online — rezervi activități, primești QR, intri rapid';
$pageDescription = 'Află cum funcționează bilete.online: descoperi activități, alegi data și biletele, plătești online, primești QR instant, câștigi puncte bonus și intri rapid la locație.';
$canonicalUrl = SITE_URL . '/cum-functioneaza';
$structuredData = [
    [
        '@context' => 'https://schema.org',
        '@type' => 'HowTo',
        'name' => 'Cum cumperi bilete pe bilete.online',
        'description' => 'Pașii principali pentru a cumpăra bilete la activități prin bilete.online.',
        'totalTime' => 'PT2M',
        'step' => [
            ['@type' => 'HowToStep', 'name' => 'Alegi activitatea', 'text' => 'Cauți după oraș, categorie, public, buget sau momentul potrivit.'],
            ['@type' => 'HowToStep', 'name' => 'Selectezi biletele', 'text' => 'Alegi data, ora, tipul de bilet și numărul de participanți.'],
            ['@type' => 'HowToStep', 'name' => 'Plătești online', 'text' => 'Plătești securizat cu cardul, Apple Pay sau Google Pay, ori cu Card Cultural acolo unde este acceptat.'],
            ['@type' => 'HowToStep', 'name' => 'Primești QR', 'text' => 'După confirmarea plății, biletele sunt emise electronic și trimise pe email și în cont.'],
            ['@type' => 'HowToStep', 'name' => 'Intri la locație', 'text' => 'La intrare arăți codul QR de pe telefon sau din PDF-ul biletului.'],
        ],
    ],
    [
        '@context' => 'https://schema.org',
        '@type' => 'FAQPage',
        'mainEntity' => array_map(fn ($f) => ['@type' => 'Question', 'name' => $f[0], 'acceptedAnswer' => ['@type' => 'Answer', 'text' => $f[1]]], $hwFaqs),
    ],
];

$v2Styles = ['how.css'];
$v2HeaderOverlay = true;
$v2HeadExtra = '<meta name="keywords" content="cum funcționează bilete online, bilete QR activități, rezervare activități online, cumpărare bilete online, puncte bonus bilete, protecție bilet, card cadou experiențe">';

include __DIR__ . '/includes/v2/head.php';
include __DIR__ . '/includes/v2/header.php';

// every card leads to its category (the nav's link when it has one, else the short URL slug.php resolves)
$hwCatHref = static function (string $slug) use ($V2NAV): string {
    return (string) ($V2NAV['categoryBySlug'][$slug]['href'] ?? '/' . $slug);
};
?>
<main id="main" tabindex="-1">
  <!-- HERO -->
  <section class="hw-hero" aria-labelledby="hw-h">
    <svg class="deco-arches" viewBox="0 0 400 400" aria-hidden="true" focusable="false"><path d="M40 400V200a160 160 0 0 1 320 0v200"/><path d="M90 400V200a110 110 0 0 1 220 0v200"/><path d="M140 400V200a60 60 0 0 1 120 0v200"/></svg>
    <div class="hw-in">
      <div class="hw-copy">
        <p class="hw-kicker">Ghid rapid · bilete QR · activități</p>
        <h1 class="hw-h" id="hw-h">Cauți. Rezervi. Intri cu QR.</h1>
        <p class="hw-lead">bilete.online îți adună într-un singur loc activități, experiențe și locuri de vizitat. Alegi ce vrei să faci, plătești online, primești biletul instant și mergi direct la intrare.</p>
        <div class="hw-cta">
          <a class="btn btn-light" href="/categorii">Explorează activități<?= v2_ic('arrow-right') ?></a>
          <a class="btn btn-outline-light" href="#pasii">Vezi pașii</a>
        </div>
        <dl class="hw-stats">
          <div><dt>Emitere</dt><dd>instant</dd></div>
          <div><dt>Acces</dt><dd>QR</dd></div>
          <div><dt>Bonus</dt><dd>puncte</dd></div>
        </dl>
      </div>

      <div class="hw-mock-col">
        <!-- the header turns solid when the white ticket reaches it -->
        <div id="hdr-sentinel" aria-hidden="true"></div>
        <div class="hw-mock" aria-hidden="true">
          <div class="hw-ticket">
            <div class="hw-ticket-main">
              <div class="hw-ticket-top"><span class="hw-valid">Bilet valid</span><span class="hw-ticket-no">MKT-W08ABJWH</span></div>
              <p class="hw-ticket-title">Camera 13</p>
              <p class="hw-ticket-sub">Escape room · Brașov</p>
              <dl class="hw-ticket-meta">
                <div><dt>Data</dt><dd><?= v2_e($hwTicketDate) ?></dd></div>
                <div><dt>Ora</dt><dd>18:30</dd></div>
                <div><dt>Beneficiar</dt><dd>Andrei N.</dd></div>
                <div><dt>Acces</dt><dd>QR scan</dd></div>
              </dl>
              <div class="hw-ticket-bonus"><b>+95 puncte bonus</b><span>Se adaugă în cont după confirmarea plății.</span></div>
            </div>
            <div class="hw-ticket-stub">
              <div class="hw-qr">
                <svg viewBox="0 0 120 120" focusable="false"><rect width="120" height="120" fill="#FFFFFF"/><path d="M8 8h32v32H8zM80 8h32v32H80zM8 80h32v32H8z" fill="#0F3D2E"/><path d="M16 16h16v16H16zM88 16h16v16H88zM16 88h16v16H16z" fill="#FFFFFF"/><path d="M22 22h4v4h-4zM94 22h4v4h-4zM22 94h4v4h-4z" fill="#0F3D2E"/><path d="M54 12h8v8h-8zm12 0h8v20h-8zM52 52h12v12H52zm20 0h8v8h-8zm12 12h24v8H84zm-28 18h8v24h-8zm16 0h12v12H72zm24 12h12v16H96zM44 72h20v8H44zM48 36h8v8h-8zm20 40h8v8h-8z" fill="#0F3D2E"/></svg>
                <span class="hw-scan"></span>
              </div>
              <p>scanezi la intrare</p>
            </div>
          </div>
          <div class="hw-float is-checkout"><small>Checkout</small><b>2 minute</b><span>alegi biletul, plătești, primești QR</span></div>
          <div class="hw-float is-pay"><small>Metode plată</small><div class="hw-pay-chips"><?php foreach ($hwPayments as $pi => $pay): ?><span<?= $pi === 0 ? ' class="is-on"' : '' ?>><?= v2_e($pay) ?></span><?php endforeach; ?></div></div>
        </div>
      </div>
    </div>
  </section>

  <!-- MARQUEE -->
  <div class="hw-marquee" aria-hidden="true">
    <div class="hw-marquee-track">
      <?php for ($copy = 0; $copy < 2; $copy++): ?>
      <div class="hw-marquee-set"><?php foreach ($hwMarquee as $word): ?><span><?= v2_e($word) ?></span><?php endforeach; ?></div>
      <?php endfor; ?>
    </div>
  </div>

  <!-- VALUE PROPS -->
  <section class="sec hw-props" aria-label="De ce bilete.online">
    <div class="wrap hw-props-grid">
      <?php foreach ($hwProps as [$propIcon, $propTone, $propTitle, $propText]): ?>
      <article class="hw-prop">
        <span class="hw-prop-ic <?= $propTone ?>"><?= v2_ic($propIcon) ?></span>
        <h2><?= v2_e($propTitle) ?></h2>
        <p><?= v2_e($propText) ?></p>
      </article>
      <?php endforeach; ?>
    </div>
  </section>

  <!-- 5 STEPS -->
  <section class="hw-steps-sec" id="pasii" aria-labelledby="hw-steps-h">
    <div class="wrap hw-steps-grid">
      <div class="hw-steps-intro">
        <p class="kicker">5 pași</p>
        <h2 id="hw-steps-h">De la idee la intrare, fără fricțiune.</h2>
        <p>Procesul este același indiferent dacă alegi un escape room, un muzeu, o peșteră, o rezervație sau un atelier pentru copii.</p>
        <a class="btn btn-primary" href="/categorii">Începe cu o categorie<?= v2_ic('arrow-right') ?></a>
      </div>
      <ol class="hw-steps">
        <?php foreach ($hwSteps as $si => [$stepLabel, $stepTitle, $stepBody, $sideLabel, $sideType, $sideItems]): ?>
        <li class="hw-step<?= $sideType === 'qr' ? ' is-dark' : '' ?>" data-step="<?= $si + 1 ?>">
          <div class="hw-step-main">
            <div class="hw-step-top"><span class="hw-step-n"><?= $si + 1 ?></span><p><?= v2_e($stepLabel) ?></p></div>
            <h3><?= v2_e($stepTitle) ?></h3>
            <p><?= v2_e($stepBody) ?></p>
          </div>
          <div class="hw-step-side">
            <?php if ($sideType === 'qr'): ?>
            <div class="hw-step-qr"><?= v2_ic('qr-code') ?><span>QR valid</span></div>
            <?php else: ?>
            <p class="hw-side-k"><?= v2_e($sideLabel) ?></p>
            <?php if ($sideType === 'chips'): ?>
            <div class="hw-side-chips"><?php foreach ($sideItems as $item): ?><span><?= v2_e($item) ?></span><?php endforeach; ?></div>
            <?php else: ?>
            <ul class="hw-side-list"><?php foreach ($sideItems as $item): ?><li><?= v2_ic('check') ?><?= v2_e($item) ?></li><?php endforeach; ?></ul>
            <?php endif; ?>
            <?php endif; ?>
          </div>
        </li>
        <?php endforeach; ?>
      </ol>
    </div>
  </section>

  <!-- FOR WHO / ACTIVITY TYPES -->
  <section class="sec hw-types" aria-labelledby="hw-types-h">
    <div class="wrap">
      <div class="hw-head">
        <p class="kicker">Pentru cine</p>
        <h2 id="hw-types-h">Aceeași platformă, mai multe feluri de a ieși din casă.</h2>
        <p>Nu toate activitățile se cumpără la fel. Un escape room are sloturi, un muzeu poate avea bilete simple, o peșteră poate avea tururi ghidate, iar un parc de distracții poate avea pachete. Platforma le poate susține pe toate.</p>
      </div>
      <div class="hw-types-grid">
        <?php foreach ($hwActivities as [$typeSlug, $typeKicker, $typeTitle, $typeText, $typeAlt]):
            $typeHref = $hwCatHref($typeSlug); ?>
        <article class="hw-type">
          <img src="<?= v2_asset('img/cat-' . $typeSlug . '.webp') ?>" srcset="<?= v2_asset('img/cat-' . $typeSlug . '-320.webp') ?> 320w, <?= v2_asset('img/cat-' . $typeSlug . '.webp') ?> 640w" sizes="(min-width:1024px) 25vw, (min-width:640px) 50vw, 100vw" width="640" height="480" alt="<?= v2_e($typeAlt) ?>" loading="lazy" decoding="async">
          <div class="hw-type-body">
            <p class="hw-type-k"><?= v2_e($typeKicker) ?></p>
            <h3><a class="hw-type-link" href="<?= v2_e($typeHref) ?>"><?= v2_e($typeTitle) ?></a></h3>
            <p><?= v2_e($typeText) ?></p>
            <span class="hw-type-go" aria-hidden="true">Vezi activitățile<?= v2_ic('arrow-right') ?></span>
          </div>
        </article>
        <?php endforeach; ?>
      </div>
    </div>
  </section>

  <!-- CHECKOUT INTELLIGENCE -->
  <section class="hw-checkout" aria-labelledby="hw-checkout-h">
    <div class="wrap hw-checkout-grid">
      <div>
        <p class="hw-dark-k">Checkout inteligent</p>
        <h2 id="hw-checkout-h">Mai mult decât „plătește și gata”.</h2>
        <p class="hw-dark-p">Checkout-ul este locul unde comanda devine clară: cine merge, ce bilete se emit, ce taxe se aplică, câte puncte primești și ce opțiuni suplimentare alegi.</p>
        <div class="hw-features">
          <?php foreach ($hwCheckout as [$featTitle, $featText]): ?>
          <div class="hw-feature"><h3><?= v2_e($featTitle) ?></h3><p><?= v2_e($featText) ?></p></div>
          <?php endforeach; ?>
        </div>
      </div>
      <div class="hw-summary" aria-label="Exemplu de sumar checkout">
        <div class="hw-summary-head"><p>Sumar checkout</p><h3>Comandă clară</h3></div>
        <dl class="hw-summary-rows">
          <?php foreach ($hwSummary as [$rowLabel, $rowValue, $rowTone]): ?>
          <div><dt><?= v2_e($rowLabel) ?></dt><dd class="<?= $rowTone ?>"><?= v2_e($rowValue) ?></dd></div>
          <?php endforeach; ?>
          <div class="is-total"><dt>Total</dt><dd>379,95 lei</dd></div>
        </dl>
        <div class="hw-summary-bonus"><b>Primești +158 puncte bonus</b><span>Se adaugă în cont după confirmarea comenzii.</span></div>
      </div>
    </div>
  </section>

  <!-- ACCOUNT -->
  <section class="sec hw-account" aria-labelledby="hw-account-h">
    <div class="wrap hw-account-grid">
      <div class="hw-head">
        <p class="kicker">Cont client</p>
        <h2 id="hw-account-h">După cumpărare, totul rămâne organizat.</h2>
        <p>Contul clientului nu este doar un login. Este locul unde găsești biletele, comenzile, punctele bonus, cardurile cadou, recenziile și setările tale.</p>
      </div>
      <div class="hw-account-cards">
        <?php foreach ($hwAccount as [$accTitle, $accText, $accTone]): ?>
        <article class="hw-acc <?= $accTone ?>"><h3><?= v2_e($accTitle) ?></h3><p><?= v2_e($accText) ?></p></article>
        <?php endforeach; ?>
      </div>
    </div>
  </section>

  <!-- SECONDARY VALUE PROPS -->
  <section class="hw-secondary" aria-label="Pe scurt">
    <div class="wrap hw-secondary-grid">
      <?php foreach ($hwSecondary as [$secK, $secTitle, $secText]): ?>
      <article class="hw-sec-card"><p class="hw-sec-k"><?= v2_e($secK) ?></p><h3><?= v2_e($secTitle) ?></h3><p><?= v2_e($secText) ?></p></article>
      <?php endforeach; ?>
    </div>
  </section>

  <!-- FOR LOCATIONS -->
  <section class="sec hw-venue-sec" aria-labelledby="hw-venue-h">
    <div class="wrap">
      <div class="hw-venue">
        <div class="hw-venue-copy">
          <p class="hw-dark-k">Și pentru locații</p>
          <h2 id="hw-venue-h">Locațiile primesc o platformă de vânzare, nu doar un formular.</h2>
          <p class="hw-dark-p">Pentru organizatori și locații, bilete.online înseamnă pagini SEO, bilete QR, checkout, dashboard, scanări, rapoarte, carduri cadou, puncte bonus și posibilitatea de a transforma activitățile în produse ușor de cumpărat.</p>
          <a class="btn btn-light" href="/pentru-locatii">Vezi pagina pentru locații<?= v2_ic('arrow-right') ?></a>
        </div>
        <div class="hw-venue-cards">
          <?php foreach ($hwVenue as [$venueK, $venueText]): ?>
          <div class="hw-venue-card"><b><?= v2_e($venueK) ?></b><p><?= v2_e($venueText) ?></p></div>
          <?php endforeach; ?>
        </div>
      </div>
    </div>
  </section>

  <!-- FAQ -->
  <section class="sec hw-faq" aria-labelledby="hw-faq-h">
    <div class="wrap hw-faq-in">
      <div class="hw-faq-head">
        <p class="kicker">FAQ</p>
        <h2 id="hw-faq-h">Întrebări frecvente</h2>
        <p>Cele mai importante lucruri pe care trebuie să le știi înainte să cumperi.</p>
      </div>
      <div>
        <?php foreach ($hwFaqs as $fi => [$faqQ, $faqA]): ?>
        <details class="qa"<?= $fi === 0 ? ' open' : '' ?>><summary><?= v2_e($faqQ) ?><span class="pm"><?= v2_ic('plus') ?></span></summary><p><?= v2_e($faqA) ?></p></details>
        <?php endforeach; ?>
      </div>
    </div>
  </section>

  <!-- FINAL CTA -->
  <section class="sec hw-final-sec">
    <div class="wrap">
      <div class="hw-final">
        <div>
          <p class="hw-final-k">Ready?</p>
          <h2>Găsește ceva de făcut.</h2>
          <p>Alege orașul, categoria sau contextul potrivit și cumpără biletele online în câteva minute.</p>
        </div>
        <div class="hw-final-cta">
          <a class="btn hw-btn-white" href="/categorii">Explorează categorii</a>
          <a class="btn btn-outline-light" href="/orase">Alege orașul</a>
        </div>
      </div>
    </div>
  </section>
</main>
<?php include __DIR__ . '/includes/v2/footer.php'; ?>
