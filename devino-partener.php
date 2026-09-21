<?php
/**
 * Become a partner: /devino-partener (v2 design). Sales page for venues and organizers.
 *
 * Top to bottom: hero (personalised by ?tip=<type>&loc=<name>), a strip of live categories, results in numbers, the
 * problem, booking with an animated demo (#booking), benefits, analytics and tracking (#analytics), payments, the 2%
 * commission (#bani — linked from /pentru-locatii), mobile app and local sales, fiscal / ANAF with an animated flow,
 * four steps (#cum), the Tixello engine (#tehnologie), categories, FAQ, final CTA (#contact).
 *
 * partner.js does what Alpine did: the personalisation (profile copy + signup links that carry tip/loc on to
 * /inregistrare-locatie), the count-up numbers, both animated demos (stopped under reduced motion), the funnel ping
 * (leads.track page_view_landing) and CTA click tracking ([data-track-cta]) on the bo_lead_sid session.
 *
 * Categories come from the v2 nav data (local photos) instead of a second API call. Payment methods follow checkout
 * (cards through Stripe incl. Apple Pay / Google Pay, Card Cultural where accepted); the old page also listed Revolut,
 * RoPay and Netopia. The old share image (/assets/images/og-default.jpg) was a 404, so the v2 default is used.
 */

require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/api.php';

// 15-minute page cache; the personalisation is applied in the browser.
$pageCacheTTL = 900;
require_once __DIR__ . '/includes/page-cache.php';

require_once __DIR__ . '/includes/nav-helpers.php';
require_once __DIR__ . '/includes/v2/helpers.php';
require_once __DIR__ . '/includes/v2/nav.php';
require_once __DIR__ . '/includes/v2/partner-profiles.php';

$dpAccent = '<strong class="dp-accent">doar 2%*</strong>';
$dpProfiles = v2_partner_profiles($dpAccent);
$dpAliases = V2_PARTNER_ALIASES;

$dpStats = [[4294, 'Evenimente & activități', ''], [96341, 'Clienți în bază', ''], [301310, 'Bilete vândute', ''], [4409557, 'Vânzări generate', ' €']];
$dpProblems = [
    ['coins', 'Comisioane din marja ta', 'Plătești tu, la fiecare bilet. La volum, e o gaură reală în buget.'],
    ['calendar-blank', 'Booking inflexibil', 'Activitățile au sloturi, zile, capacități. Majoritatea platformelor nu le suportă.'],
    ['x', 'Tracking pierdut', 'Ad blockerele și iOS blochează datele de conversie — plătești mai mult pe reclame.'],
];
$dpBookingList = ['Selecție de zile disponibile pe calendar', 'Sloturi orare cu capacitate configurabilă', 'Booking detaliat: participanți, opțiuni, add-on-uri', 'Pachete de grup și prețuri pe categorie de vârstă'];
$dpBenefits = [
    ['plus', 'Activități nelimitate', 'Adaugi oricâte activități, de orice tip și în orice formă. Fără limită, fără costuri de pornire.'],
    ['list', 'Gestiune avansată', 'Capacități, sloturi, variante de preț, disponibilitate, add-on-uri — controlezi fiecare detaliu al fiecărei activități.'],
    ['gift', 'Coduri de reducere', 'Creezi coduri promoționale și campanii de discount, cu reguli proprii, ca să-ți crești vânzările când vrei.'],
    ['users-three', 'Pachete de grup', 'Vinzi pachete pentru grupuri, familii, clase sau echipe corporate, cu prețuri și capacități dedicate.'],
    ['star', 'Sistem de recomandare', 'Motorul propriu expune activitățile tale celor mai potriviți cumpărători din baza de peste 96.000 de clienți.'],
    ['lock-simple', 'Bilete sigure', 'Validare QR, verificare și protecție anti-fraudă — tehnologie testată în producție pe Tixello.'],
];
$dpSmall = [
    ['ticket', 'Pagină dedicată per activitate', 'Pagină SEO-friendly, link de partajat și schema markup pentru Google.'],
    ['clock', 'Liste de așteptare', 'Slot plin? Clientul se înscrie pe waitlist și e anunțat dacă se eliberează locuri.'],
    ['qr-code', 'Check-in & control acces', 'Validare QR cu prevenirea dublei intrări, ideal la sloturi cu capacitate fixă.'],
    ['map-pin', 'Multi-locație', 'Gestionezi mai multe locații sau puncte de lucru dintr-un singur cont.'],
    ['user-circle', 'Roluri & echipă', 'Adaugi colegi cu permisiuni (casier, scanare, manager), fără acces total.'],
    ['heart', 'Branding propriu', 'Logo, culori și aspect pe paginile tale, ca să arate ca brandul tău.'],
    ['star', 'Recenzii & rating', 'Clienții lasă recenzii care cresc conversia pentru următorii cumpărători.'],
    ['list', 'Export & rapoarte', 'Exporți comenzi, participanți și încasări pentru raportare și contabilitate.'],
];
$dpAnalytics = [
    'Vezi exact ce activitate, slot și zi se vând cel mai bine',
    'Înțelegi de unde vin cumpărătorii și ce canal aduce profit',
    'Optimizezi prețurile și capacitatea pe baza cererii reale',
    'Urmărești conversia din vizită în vânzare, în timp real',
    'Identifici sloturile goale și le umpli cu promoții țintite',
    'Iei decizii pe date, nu pe presupuneri',
];
$dpPayments = ['Card bancar', 'Apple Pay', 'Google Pay', 'Carduri culturale', 'Stripe'];
$dpFiscal = [
    ['list', 'Documente ANAF', 'Generare automată a documentelor necesare pentru ANAF.'],
    ['credit-card', 'Facturi fiscale', 'Emiți facturi fiscale către clienți direct din platformă.'],
    ['check-circle', 'Contabilitate RO', 'Integrare cu sisteme de contabilitate din România.'],
    ['coins', 'Deconturi clare', 'Deconturi periodice sau la cerere, cu evidență transparentă.'],
];
$dpDocs = [['credit-card', 'Factură fiscală', 'serie BO · client'], ['list', 'Document ANAF', 'raportare automată'], ['check-circle', 'Înregistrare contabilă', 'sync contabilitate RO']];
$dpSteps = [
    ['user-circle', 'Îți faci contul', 'Te înregistrezi în câteva minute. Fără taxe de pornire, fără abonament, fără card la înscriere.', '≈ 5 minute'],
    ['plus', 'Adaugi activitățile', 'Oricâte, de orice tip. Setezi sloturi orare, zile, capacități, variante de preț și pachete de grup.', 'activități nelimitate'],
    ['arrow-right', 'Mergi live', 'Publici și ești în piață, cu pagini gata de partajat și tracking conectat. Intri direct în baza de 96.000+ clienți.', 'go-live în max 1 zi'],
    ['coins', 'Vinzi & încasezi', 'Online și local, în același sistem. bilete.online încasează de la client și îți face deconturi periodice sau la cerere.', 'prețul tău, întreg'],
];
$dpTixello = [['Evenimente & activități', '4.294'], ['Clienți în bază', '96.341'], ['Bilete vândute', '301.310'], ['Vânzări generate', '4.409.557 €'], ['Scanare offline', 'Da, cu sync']];
$dpFaqs = [
    ['Cât e comisionul?', 'Comisionul este de 2%*, fără să se scadă din prețul tău: se adaugă peste el, în prețul final. Tu îți stabilești prețul și îl primești integral la decont. *Cei 2% se aplică pentru vânzarea exclusivă prin bilete.online.'],
    ['Cum și când primesc banii?', 'bilete.online încasează plata de la client și îți face deconturi periodice — sau la cerere, ori de câte ori vrei să-ți fie decontați banii.'],
    ['Pot vinde activități cu sloturi și pe zile?', 'Da. Clientul alege ziua din calendar, slotul orar, numărul de participanți și opțiunile. Tu controlezi capacitatea fiecărui slot și faci booking în detaliu.'],
    ['Ce metode de plată sunt acceptate?', 'Card bancar (Visa, Mastercard, Maestro), Apple Pay și Google Pay, procesate securizat prin Stripe, plus Card Cultural (Edenred, Sodexo, Up România) acolo unde este acceptat.'],
    ['Cum îmi reduce costul reclamelor?', 'Platforma se integrează cu toți pixelii de tracking și cu Facebook CAPI, trimițând 100% din evenimentele de conversie fără să fie blocate de ad blockere sau de iOS. Rezultatul: costul reclamelor pe Facebook, Instagram, TikTok și Google scade cu până la 60%.'],
    ['Pot vinde și la fața locului?', 'Da. Pe lângă dashboard-ul online, ai un panou de vânzări locale — vinzi și emiți bilete la casă: acces, servicii suplimentare și închirieri.'],
    ['Mă ajută cu partea fiscală?', 'Da. Generare automată de documente ANAF, emitere facturi fiscale către clienți și integrare cu sisteme de contabilitate din România.'],
    ['Cât durează să încep?', 'Onboarding în aproximativ 5 minute, fără costuri de pornire. Mergi live în maxim o zi, în funcție de câte activități adaugi.'],
];

$dpCategories = $V2NAV['categories'] ?? [];
$dpMarquee = array_values(array_filter(array_map(fn ($c) => (string) ($c['name'] ?? ''), $dpCategories)));
if (count($dpMarquee) > 0 && count($dpMarquee) < 6) {
    $dpMarquee = array_merge($dpMarquee, $dpMarquee);
}

$pageTitleRaw = 'Vinde bilete la activități pe ' . SITE_NAME . ' — comision 2%*, fără să-ți atingă prețul';
$pageDescription = 'Platforma de ticketing pentru activități: booking cu sloturi și calendar, analytics avansat, tracking 100% cu Facebook CAPI, deconturi periodice, app mobilă cu scanare offline. Comision 2%*, fără să-ți atingă prețul. Construit pe Tixello.';
$canonicalUrl = SITE_URL . '/devino-partener';
$noindex = true; // /parteneri is the page to find; this one stays for old links and personalised campaigns
$structuredData = [
    [
        '@context' => 'https://schema.org',
        '@type' => 'Service',
        'name' => 'bilete.online — Ticketing & booking pentru activități',
        'description' => 'Platformă de ticketing pentru locații și organizatori de activități. Comision 2%, fără să-ți atingă prețul. Booking cu sloturi orare, analytics avansat, scanare offline, deconturi periodice.',
        'provider' => ['@type' => 'Organization', 'name' => SITE_NAME, 'url' => SITE_URL],
        'areaServed' => ['@type' => 'Country', 'name' => 'Romania'],
        'offers' => ['@type' => 'Offer', 'priceCurrency' => 'RON', 'price' => '0', 'description' => '0 lei cost de pornire. Comision 2%* la fiecare bilet vândut, fără să-ți atingă prețul.'],
    ],
    [
        '@context' => 'https://schema.org',
        '@type' => 'FAQPage',
        'mainEntity' => array_map(fn ($f) => ['@type' => 'Question', 'name' => $f[0], 'acceptedAnswer' => ['@type' => 'Answer', 'text' => $f[1]]], $dpFaqs),
    ],
];

$v2Styles = ['partner.css'];
$v2Scripts = ['partner.js'];
$v2HeaderOverlay = true;
$v2ClientData = ['profiles' => $dpProfiles, 'aliases' => $dpAliases, 'demoName' => 'Andrei Popescu', 'demoGift' => 'La mulți ani! Distracție plăcută!'];
// head.php strips utm_* from the address bar 15 s after load; the funnel pings read them from here
$v2HeadExtra = '<script>(function(){try{var q=new URLSearchParams(location.search),u={};["utm_source","utm_medium","utm_campaign","utm_content","utm_term"].forEach(function(k){if(q.get(k))u[k]=q.get(k).slice(0,150)});window.BO_UTM=u;}catch(e){}})();</script>';

include __DIR__ . '/includes/v2/head.php';
include __DIR__ . '/includes/v2/header.php';
?>
<main id="main" tabindex="-1">
  <!-- HERO -->
  <section class="dp-hero" aria-labelledby="dp-h">
    <svg class="deco-arches" viewBox="0 0 400 400" aria-hidden="true" focusable="false"><path d="M40 400V200a160 160 0 0 1 320 0v200"/><path d="M90 400V200a110 110 0 0 1 220 0v200"/><path d="M140 400V200a60 60 0 0 1 120 0v200"/></svg>
    <div class="dp-in">
      <div class="dp-copy">
        <p class="dp-hello" id="dp-hello" hidden></p>
        <p class="dp-chip"><span class="dp-dot" aria-hidden="true"></span><span id="dp-chip-t">Ticketing &amp; booking pentru activități</span></p>
        <h1 class="dp-h" id="dp-h"><span id="dp-h1a">Vinzi bilete</span> <span id="dp-h1b" class="is-soft">la activitățile tale.</span> <span class="dp-h-line"><span id="dp-h1c" class="dp-mark">Prețul tău rămâne al tău.</span></span></h1>
        <p class="dp-lead" id="dp-sub">Booking pe sloturi orare și calendar, analytics avansat, tracking 100% care îți reduce costul reclamelor, și o aplicație mobilă cu scanare offline. Comisionul de <?= $dpAccent ?> nu-ți atinge prețul — tu îți păstrezi prețul stabilit.</p>
        <div class="dp-cta">
          <a class="btn btn-light" href="/inregistrare-locatie" data-signup data-track-cta="hero_primary"><span id="dp-cta-t">Pune-ți activitățile la vânzare</span><?= v2_ic('arrow-right') ?></a>
          <a class="btn btn-outline-light" href="#cum" data-track-cta="hero_secondary">Vezi cum funcționează</a>
        </div>
        <ul class="dp-ticks">
          <li><?= v2_ic('check') ?>Fără costuri de pornire</li>
          <li><?= v2_ic('check') ?>Activități nelimitate</li>
          <li><?= v2_ic('check') ?>Onboarding în 5 minute</li>
        </ul>
      </div>
      <div class="dp-ticket-col">
        <!-- the header turns solid when the white ticket reaches it -->
        <div id="hdr-sentinel" aria-hidden="true"></div>
        <div class="dp-ticket" aria-hidden="true">
          <span class="dp-stamp">Rezervat</span>
          <div class="dp-ticket-top"><b>Booking</b><span>No. 00 962</span></div>
          <div class="dp-ticket-mid">
            <small>Activitatea ta</small>
            <p>Slot orar.<br>Zi din calendar.</p>
            <div class="dp-ticket-stats">
              <div><b class="is-accent">2%*</b><span>fără să-ți atingă prețul</span></div>
              <div><b>−60%</b><span>cost reclame</span></div>
              <div><b class="is-yellow">∞</b><span>activități</span></div>
            </div>
          </div>
          <div class="dp-ticket-bot">
            <div><p>Powered by Tixello</p><p>301.310 bilete vândute</p></div>
            <div class="dp-bars"><?php foreach ([100, 75, 100, 90, 65, 100, 100, 80, 90, 100, 65] as $barHeight): ?><i style="height:<?= $barHeight ?>%"></i><?php endforeach; ?></div>
          </div>
        </div>
      </div>
    </div>
  </section>

  <!-- MARQUEE: live categories (the grid further down lists them for everyone) -->
  <?php if ($dpMarquee): ?>
  <div class="dp-marquee" aria-hidden="true">
    <div class="dp-marquee-track">
      <?php for ($pass = 0; $pass < 2; $pass++): ?>
      <div class="dp-marquee-set"><?php foreach ($dpMarquee as $marqueeName): ?><span><?= v2_e($marqueeName) ?></span><?php endforeach; ?></div>
      <?php endfor; ?>
    </div>
  </div>
  <?php endif; ?>

  <!-- STATS -->
  <section class="dp-stats" aria-labelledby="dp-stats-h">
    <div class="wrap">
      <p class="dp-stats-cap" id="dp-stats-h">Rezultate reale în ecosistemul Tixello — pe care e construit bilete.online</p>
      <div class="dp-stats-grid">
        <?php foreach ($dpStats as [$statValue, $statLabel, $statSuffix]): ?>
        <div><p class="dp-stat-v"><span data-count="<?= $statValue ?>"><?= number_format($statValue, 0, ',', '.') ?></span><?= v2_e($statSuffix) ?></p><p class="dp-stat-l"><?= v2_e($statLabel) ?></p></div>
        <?php endforeach; ?>
      </div>
    </div>
  </section>

  <!-- PROBLEM -->
  <section class="sec dp-problem" aria-labelledby="dp-problem-h">
    <div class="wrap">
      <div class="dp-head">
        <p class="kicker">Realitatea de azi</p>
        <h2 id="dp-problem-h">Vinzi activități, dar instrumentele te trag înapoi.</h2>
        <p>Comisioane mari scăzute din marja ta. Booking rigid care nu suportă sloturi sau zile. Tracking ciuntit de ad blockere și iOS, care îți umflă costul reclamelor. Și zero ajutor real ca să găsești clienți noi.</p>
      </div>
      <div class="dp-problem-grid" data-reveal>
        <?php foreach ($dpProblems as [$probIcon, $probTitle, $probText]): ?>
        <article class="dp-card"><span class="dp-ic is-red"><?= v2_ic($probIcon) ?></span><h3><?= v2_e($probTitle) ?></h3><p><?= v2_e($probText) ?></p></article>
        <?php endforeach; ?>
      </div>
      <div class="dp-center"><a class="btn btn-primary" href="/inregistrare-locatie" data-signup data-track-cta="problem_solve">Rezolvă-le pe toate cu bilete.online<?= v2_ic('arrow-right') ?></a></div>
    </div>
  </section>

  <!-- BOOKING -->
  <section class="dp-booking" id="booking" aria-labelledby="dp-booking-h">
    <div class="wrap dp-two">
      <div>
        <p class="dp-dark-k">Booking gândit pentru activități</p>
        <h2 id="dp-booking-h">Sloturi orare. Zile pe calendar. Rezervare în detaliu.</h2>
        <p class="dp-dark-p">bilete.online nu vinde doar „un bilet”. Clientul alege ziua din calendar, slotul orar, numărul de participanți și opțiunile — exact cum funcționează un escape room, un tur ghidat sau un atelier. Tu controlezi capacitatea fiecărui slot.</p>
        <ul class="dp-list is-dark">
          <?php foreach ($dpBookingList as $bookingItem): ?><li><?= v2_ic('check') ?><?= v2_e($bookingItem) ?></li><?php endforeach; ?>
        </ul>
        <a class="btn btn-light dp-mt" href="/inregistrare-locatie" data-signup data-track-cta="booking_slots">Vreau booking pe sloturi<?= v2_ic('arrow-right') ?></a>
      </div>
      <!-- animated demo; the text beside it says the same thing, so screen readers skip it -->
      <div class="dp-demo" id="dp-demo" aria-hidden="true">
        <div class="dp-demo-head"><b id="dp-demo-title">Gata!</b><span id="dp-demo-count">6/6</span></div>
        <div class="dp-bar"><i id="dp-demo-progress" style="width:100%"></i></div>
        <div class="dp-demo-body">
          <div class="dp-step" data-step="0" hidden>
            <small>Sloturi — 19 oct</small>
            <div class="dp-slots"><?php foreach (['10:00', '12:00', '14:00', '16:00', '18:00', '20:00'] as $slotIndex => $slotTime): ?><span class="dp-slot<?= $slotIndex === 1 ? ' is-gone' : '' ?>"><?= $slotTime ?></span><?php endforeach; ?></div>
          </div>
          <div class="dp-step" data-step="1" hidden>
            <?php foreach ([['Acces standard', '1 persoană', '80 lei'], ['Acces + experiență', '1 persoană', '120 lei'], ['Pachet familie', '2 adulți + 2 copii', '260 lei']] as [$tkName, $tkNote, $tkPrice]): ?>
            <div class="dp-row dp-tk"><div><b><?= v2_e($tkName) ?></b><small><?= v2_e($tkNote) ?></small></div><span><?= v2_e($tkPrice) ?><i class="dp-radio"></i></span></div>
            <?php endforeach; ?>
          </div>
          <div class="dp-step" data-step="2" hidden>
            <small>Adaugă extra &amp; rentals</small>
            <?php foreach ([['map-pin', 'Echipament (rental)', '35 lei'], ['star', 'Ghid foto', '25 lei'], ['gift', 'Pachet gustare', '18 lei']] as [$exIcon, $exName, $exPrice]): ?>
            <div class="dp-row dp-extra"><div class="dp-extra-name"><?= v2_ic($exIcon) ?><b><?= v2_e($exName) ?></b></div><span><?= v2_e($exPrice) ?><i class="dp-plus"></i></span></div>
            <?php endforeach; ?>
          </div>
          <div class="dp-step" data-step="3" hidden>
            <small>Personalizează biletul</small>
            <p class="dp-lbl">Nume pe bilet</p>
            <div class="dp-input"><span id="dp-typed"></span><i class="dp-caret"></i></div>
            <p class="dp-lbl">Mesaj cadou (opțional)</p>
            <div class="dp-textarea" id="dp-gift"></div>
            <p class="dp-okline"><?= v2_ic('check') ?>Trimite biletul pe email &amp; WhatsApp</p>
          </div>
          <div class="dp-step" data-step="4" hidden>
            <small>Sumar comandă</small>
            <div class="dp-sum"><p><span>Acces + experiență</span><span>120 lei</span></p><p><span>Echipament (rental)</span><span>35 lei</span></p><p><span>Ghid foto</span><span>25 lei</span></p><p class="is-total"><span>Total estimat</span><span>180 lei</span></p></div>
            <div class="dp-pay"><span class="is-on">Stripe</span><span>Apple Pay</span><span>Google Pay</span><span>Card Cultural</span></div>
            <div class="dp-bar is-pay"><i id="dp-pay-progress" style="width:100%"></i></div>
            <p class="dp-pay-t" id="dp-pay-t">Plată confirmată</p>
          </div>
          <div class="dp-step is-done" data-step="5">
            <span class="dp-done-ic"><?= v2_ic('check') ?></span>
            <b>Comandă confirmată!</b>
            <p>Bilet MKT-19024 · 14:00</p>
            <ul class="dp-msgs">
              <?php foreach ([['envelope-simple', 'Biletul a fost trimis pe email'], ['phone', 'Confirmare trimisă pe WhatsApp'], ['ticket', 'Bilet QR valabil — îl scanezi la intrare'], ['star', 'Recomandare: „Tur foto la apus” pentru tine']] as [$msgIcon, $msgText]): ?>
              <li class="dp-msg is-on"><?= v2_ic($msgIcon) ?><?= v2_e($msgText) ?></li>
              <?php endforeach; ?>
            </ul>
          </div>
        </div>
        <p class="dp-demo-foot">Demo booking bilete.online</p>
      </div>
    </div>
  </section>

  <!-- BENEFITS -->
  <section class="sec dp-benefits" aria-labelledby="dp-benefits-h">
    <div class="wrap">
      <div class="dp-head">
        <p class="kicker">Tot ce-ți trebuie</p>
        <h2 id="dp-benefits-h">O platformă completă pentru vânzarea de activități.</h2>
      </div>
      <div class="dp-benefit-grid" data-reveal>
        <?php foreach ($dpBenefits as [$benIcon, $benTitle, $benText]): ?>
        <article class="dp-benefit"><span class="dp-ic is-light"><?= v2_ic($benIcon) ?></span><h3><?= v2_e($benTitle) ?></h3><p><?= v2_e($benText) ?></p></article>
        <?php endforeach; ?>
      </div>
      <div class="dp-small-grid" data-reveal>
        <?php foreach ($dpSmall as [$smIcon, $smTitle, $smText]): ?>
        <article class="dp-small"><span class="dp-ic"><?= v2_ic($smIcon) ?></span><h4><?= v2_e($smTitle) ?></h4><p><?= v2_e($smText) ?></p></article>
        <?php endforeach; ?>
      </div>
    </div>
  </section>

  <!-- ANALYTICS + TRACKING -->
  <section class="dp-analytics" id="analytics" aria-labelledby="dp-analytics-h">
    <div class="wrap">
      <div class="dp-head">
        <p class="dp-dark-k">Date care îți cresc vânzările</p>
        <h2 id="dp-analytics-h">Analytics avansat + tracking 100%. <span class="dp-hl">Reclame până la 60% mai ieftine.</span></h2>
      </div>
      <div class="dp-two is-top">
        <div>
          <h3 class="dp-sub-h">De ce contează analytics-ul</h3>
          <ul class="dp-list is-dark is-arrows">
            <?php foreach ($dpAnalytics as $anItem): ?><li><?= v2_ic('arrow-right') ?><?= v2_e($anItem) ?></li><?php endforeach; ?>
          </ul>
        </div>
        <div class="dp-glass">
          <h3 class="dp-sub-h">Tracking complet, fără pierderi</h3>
          <p>bilete.online se integrează cu <strong>toți pixelii de tracking</strong> și cu <strong>Facebook CAPI</strong>. Trimite <strong class="dp-yellow">100% din evenimentele de conversie</strong> server-side — deci nu te mai blochează ad blockerele și nici update-ul iOS care taie majoritatea trackingului.</p>
          <div class="dp-glass-stats"><div><b>100%</b><span>evenimente urmărite</span></div><div><b>−60%</b><span>cost reclame</span></div></div>
          <p class="dp-glass-note">Funcționează cu reclame pe <strong>Facebook, Instagram, TikTok și Google</strong>. Date corecte = algoritmi mai eficienți = cost pe vânzare mai mic.</p>
        </div>
      </div>
      <a class="btn btn-light dp-mt" href="/inregistrare-locatie" data-signup data-track-cta="analytics_ads">Vreau reclame mai ieftine<?= v2_ic('arrow-right') ?></a>
    </div>
  </section>

  <!-- PAYMENTS -->
  <section class="sec dp-payments" aria-labelledby="dp-pay-h">
    <div class="wrap dp-two">
      <div>
        <p class="kicker">Plăți pentru orice client</p>
        <h2 class="dp-h2" id="dp-pay-h">Toate metodele de plată, la îndemâna cumpărătorului.</h2>
        <p class="dp-p">Cu cât plata e mai simplă, cu atât vinzi mai mult. bilete.online acceptă cele mai folosite metode — clientul plătește în două atingeri, fără fricțiune.</p>
        <p class="dp-p">bilete.online încasează plata de la client și îți face <strong>deconturi periodice</strong> — sau la cerere, ori de câte ori vrei să-ți fie decontați banii.</p>
      </div>
      <div class="dp-pay-grid">
        <?php foreach ($dpPayments as $payName): ?><div class="dp-pay-tile"><?= v2_e($payName) ?></div><?php endforeach; ?>
        <div class="dp-pay-tile is-dark">și altele <br>în curând</div>
      </div>
    </div>
  </section>

  <!-- THE 2% -->
  <section class="dp-bani" id="bani" aria-labelledby="dp-bani-h">
    <div class="wrap dp-two">
      <div>
        <p class="dp-dark-k">Diferența care schimbă tot</p>
        <h2 id="dp-bani-h" class="dp-bani-h">Comision <span class="dp-hl">2%*</span>. <span class="dp-nl">Prețul tău rămâne al tău.</span></h2>
        <p class="dp-dark-p">Comisionul de 2%* este adăugat transparent în prețul final și achitat de client. Tu îți stabilești prețul și îl primești <strong class="dp-yellow">integral</strong> la decont — fără să scazi nimic din marja ta.</p>
        <ul class="dp-list is-dark">
          <li><?= v2_ic('check') ?>Tu setezi prețul — tu primești prețul stabilit</li>
          <li><?= v2_ic('check') ?>Clientul vede clar cei 2%* — onest, fără surprize</li>
          <li><?= v2_ic('check') ?>Zero costuri lunare, zero taxe de pornire</li>
        </ul>
        <p class="dp-footnote"><strong>*</strong> Comisionul de 2% se aplică pentru vânzarea exclusivă prin bilete.online. bilete.online încasează plata de la client și îți face deconturi periodice sau la cerere.</p>
      </div>
      <div class="dp-compare">
        <p class="dp-compare-h">Bilet de 100 lei — ce primești?</p>
        <div class="dp-compare-row">
          <div class="dp-compare-top"><span>Platformă clasică</span><span class="is-red">−9,50 lei</span></div>
          <p class="dp-compare-note">comision 8% + cost tranzacționare card 1–2%, ambele scăzute din banii tăi</p>
          <div class="dp-bar is-thick is-red"><i style="width:90.5%"></i></div>
          <p class="dp-compare-get">Primești: <strong class="is-red">~90,50 lei</strong></p>
        </div>
        <div class="dp-compare-row is-ours">
          <div class="dp-compare-top"><span>bilete.online (2%* pe client)</span><span class="is-green">100%</span></div>
          <div class="dp-bar is-thick"><i style="width:100%"></i></div>
          <p class="dp-compare-get">Primești: <strong class="is-green">100 lei</strong></p>
          <p class="dp-compare-note">Comisionul și costul cardului sunt incluse în prețul final. Tu primești prețul tău, întreg.</p>
        </div>
        <p class="dp-compare-foot">La volum, diferența devine uriașă.</p>
      </div>
    </div>
  </section>

  <!-- MOBILE APP + LOCAL SALES -->
  <section class="sec dp-local" aria-labelledby="dp-local-h">
    <div class="wrap">
      <div class="dp-head">
        <p class="kicker">Online și la fața locului</p>
        <h2 id="dp-local-h">Aplicație mobilă + casă de marcat pentru vânzări locale.</h2>
      </div>
      <div class="dp-two is-cards" data-reveal>
        <article class="dp-feature">
          <span class="dp-ic"><?= v2_ic('phone') ?></span>
          <h3>Aplicația mobilă — Android &amp; iOS</h3>
          <p>Scanezi bilete rapid la intrare, <strong>inclusiv offline</strong> cu sincronizare ulterioară. În același timp vezi <strong>live vânzările și traficul</strong>, oriunde te-ai afla.</p>
          <ul class="dp-list"><li><?= v2_ic('check') ?>Scanare QR cu validare anti-fraudă</li><li><?= v2_ic('check') ?>Funcționează fără internet stabil</li><li><?= v2_ic('check') ?>Vânzări și trafic în timp real</li></ul>
        </article>
        <article class="dp-feature is-dark">
          <span class="dp-ic is-light"><?= v2_ic('shopping-cart-simple') ?></span>
          <h3>Panou de vânzări locale</h3>
          <p>Pe lângă dashboard-ul de comenzi online, ai un <strong class="dp-yellow">panou de gestiune a vânzărilor la fața locului</strong>. Vinzi și emiți bilete direct la casă — acces, servicii suplimentare sau închirieri (rentals).</p>
          <ul class="dp-list is-dark"><li><?= v2_ic('check') ?>Vânzare bilete de acces la ghișeu</li><li><?= v2_ic('check') ?>Servicii suplimentare &amp; rentals</li><li><?= v2_ic('check') ?>Online + local, în același sistem</li></ul>
        </article>
      </div>
      <div class="dp-center"><a class="btn btn-primary" href="/inregistrare-locatie" data-signup data-track-cta="local_sales">Vreau să vând online și local<?= v2_ic('arrow-right') ?></a></div>
    </div>
  </section>

  <!-- FISCAL / ANAF -->
  <section class="dp-fiscal" aria-labelledby="dp-fiscal-h">
    <div class="wrap">
      <div class="dp-head">
        <p class="kicker">Fiscal, fără bătăi de cap</p>
        <h2 id="dp-fiscal-h">Contabilitatea și ANAF, rezolvate automat.</h2>
      </div>
      <div class="dp-fiscal-grid" data-reveal>
        <?php foreach ($dpFiscal as [$fisIcon, $fisTitle, $fisText]): ?>
        <article class="dp-card"><span class="dp-ic"><?= v2_ic($fisIcon) ?></span><h3><?= v2_e($fisTitle) ?></h3><p><?= v2_e($fisText) ?></p></article>
        <?php endforeach; ?>
      </div>
      <div class="dp-anaf" id="dp-anaf">
        <p class="dp-anaf-k">O vânzare → documente generate automat, în secunde</p>
        <div class="dp-anaf-grid">
          <div class="dp-order">
            <p class="dp-order-top"><span>Comandă nouă</span><i class="dp-live"></i></p>
            <b>Bilet acces + rental</b>
            <p class="dp-order-no">MKT-19024 · 180 lei</p>
            <p class="dp-okline"><?= v2_ic('check') ?>Plată confirmată</p>
          </div>
          <div class="dp-docs" aria-hidden="true">
            <?php foreach ($dpDocs as [$docIcon, $docName, $docMeta]): ?>
            <div class="dp-doc is-done"><div class="dp-doc-top"><?= v2_ic($docIcon) ?><span class="dp-doc-state"><?= v2_ic('check') ?></span></div><b><?= v2_e($docName) ?></b><small><?= v2_e($docMeta) ?></small><i class="dp-doc-bar"></i></div>
            <?php endforeach; ?>
          </div>
        </div>
        <div class="dp-anaf-foot">
          <p>Zero introducere manuală. Documentele sunt gata în <strong id="dp-elapsed">3s</strong> de la fiecare vânzare.</p>
          <a class="btn btn-light" href="/inregistrare-locatie" data-signup data-track-cta="anaf">Vreau fiscalitatea pe pilot automat<?= v2_ic('arrow-right') ?></a>
        </div>
      </div>
    </div>
  </section>

  <!-- HOW IT WORKS -->
  <section class="sec dp-how" id="cum" aria-labelledby="dp-how-h">
    <div class="wrap">
      <div class="dp-head">
        <p class="kicker">De la cont la prima vânzare</p>
        <h2 id="dp-how-h">Patru pași. Sub o zi. Zero costuri de pornire.</h2>
      </div>
      <ol class="dp-steps" data-reveal>
        <?php foreach ($dpSteps as $stepIndex => [$stepIcon, $stepTitle, $stepText, $stepTag]): ?>
        <li class="dp-stepcard"><span class="dp-num"><?= $stepIndex + 1 ?></span><span class="dp-ic"><?= v2_ic($stepIcon) ?></span><h3><?= v2_e($stepTitle) ?></h3><p><?= v2_e($stepText) ?></p><p class="dp-tag"><?= v2_e($stepTag) ?></p></li>
        <?php endforeach; ?>
      </ol>
      <div class="dp-center"><a class="btn btn-primary" href="/inregistrare-locatie" data-signup data-track-cta="how_it_works">Începe acum, gratuit<?= v2_ic('arrow-right') ?></a></div>
    </div>
  </section>

  <!-- TIXELLO ENGINE -->
  <section class="dp-tech" id="tehnologie" aria-labelledby="dp-tech-h">
    <div class="wrap dp-two">
      <div>
        <span class="dp-badge">Powered by Tixello</span>
        <h2 class="dp-h2" id="dp-tech-h">Infrastructură matură, testată la scară.</h2>
        <p class="dp-p">bilete.online rulează pe Tixello — sistemul de ticketing care a procesat deja peste 4,4 milioane EUR în vânzări și peste 301.000 de bilete. Primești tehnologie de producție, fără s-o construiești sau s-o întreții.</p>
        <a class="btn btn-primary dp-mt" href="/inregistrare-locatie" data-signup data-track-cta="tixello">Devino partener<?= v2_ic('arrow-right') ?></a>
      </div>
      <div class="dp-numbers">
        <p class="dp-numbers-k">În cifre</p>
        <dl>
          <?php foreach ($dpTixello as [$numLabel, $numValue]): ?><div><dt><?= v2_e($numLabel) ?></dt><dd><?= v2_e($numValue) ?></dd></div><?php endforeach; ?>
        </dl>
      </div>
    </div>
  </section>

  <!-- CATEGORIES -->
  <section class="sec dp-cats" aria-labelledby="dp-cats-h">
    <div class="wrap">
      <div class="dp-head is-center">
        <p class="kicker">Pentru ce tip de activitate</p>
        <h2 id="dp-cats-h">Categoriile disponibile pe bilete.online</h2>
      </div>
      <?php if (!$dpCategories): ?>
      <p class="dp-empty">Categoriile se încarcă în curând. Reîncarcă pagina în câteva minute.</p>
      <?php else: ?>
      <div class="dp-cat-grid" id="grid-categorii" data-reveal>
        <?php foreach ($dpCategories as $cat): if (empty($cat['name'])) { continue; } ?>
        <a class="dp-cat" href="<?= v2_e($cat['href'] ?? '/' . ($cat['slug'] ?? '')) ?>" data-cat-slug="<?= v2_e($cat['slug'] ?? '') ?>">
          <?php if (!empty($cat['image'])): ?>
          <img src="<?= v2_e($cat['image']) ?>"<?= !empty($cat['srcset']) ? ' srcset="' . v2_e($cat['srcset']) . '" sizes="(min-width:1024px) 33vw, (min-width:640px) 50vw, 100vw"' : '' ?> alt="" width="640" height="400" loading="lazy" decoding="async">
          <?php else: ?>
          <span class="dp-cat-ph"><?= v2_ic('ticket') ?></span>
          <?php endif; ?>
          <span class="dp-cat-body"><b><?= v2_e($cat['name']) ?></b><?php if (!empty($cat['desc'])): ?><small><?= v2_e($cat['desc']) ?></small><?php endif; ?></span>
        </a>
        <?php endforeach; ?>
      </div>
      <?php endif; ?>
    </div>
  </section>

  <!-- FAQ -->
  <section class="sec dp-faq" aria-labelledby="dp-faq-h">
    <div class="wrap dp-faq-grid">
      <div>
        <p class="kicker">Întrebări frecvente</p>
        <h2 id="dp-faq-h">Ce vrei să știi înainte să începi</h2>
      </div>
      <div>
        <?php foreach ($dpFaqs as $fi => [$faqQ, $faqA]): ?>
        <details class="qa"<?= $fi === 0 ? ' open' : '' ?>><summary><?= v2_e($faqQ) ?><span class="pm"><?= v2_ic('plus') ?></span></summary><p><?= v2_e($faqA) ?></p></details>
        <?php endforeach; ?>
      </div>
    </div>
  </section>

  <!-- FINAL CTA -->
  <section class="sec dp-final-sec" id="contact">
    <div class="wrap">
      <div class="dp-final">
        <span class="dp-final-badge">Devino partener</span>
        <h2>Pune-ți activitățile la vânzare <span class="dp-nl">și păstrează prețul tău întreg.</span></h2>
        <p>Fără costuri de pornire. Activități nelimitate. Onboarding în 5 minute, go-live azi. Comision 2%*, fără să-ți atingă prețul.</p>
        <div class="dp-final-cta">
          <a class="btn dp-btn-white" href="/inregistrare-locatie" data-signup data-track-cta="final_primary">Vreau să-mi vând activitățile<?= v2_ic('arrow-right') ?></a>
          <a class="btn btn-outline-light" href="mailto:contact@bilete.online?subject=%C3%8Entrebare%20parteneriat%20bilete.online" data-track-cta="email_contact">Trimite-ne un email</a>
        </div>
        <p class="dp-final-note">Fără cost de pornire · Activități nelimitate · Anulezi oricând</p>
      </div>
    </div>
  </section>
</main>
<?php include __DIR__ . '/includes/v2/footer.php'; ?>
