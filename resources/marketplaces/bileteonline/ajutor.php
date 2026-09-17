<?php
/**
 * Help center: /ajutor (v2 design). /faqs, /faq and /intrebari redirect here, to the questions (#intrebari).
 *
 * Hero with search, quick chips and a route card, 4 fast actions, the FAQ list (categories with counts, search that
 * ignores diacritics and matches every word, links under answers), "still need help" contact routes, final CTA.
 * Every question is rendered on the server (indexable, FAQPage JSON-LD); help.js only filters. Filter state lives in
 * the URL (?q=&categorie=) so a filtered view can be shared.
 *
 * Shares the hero, route card and tile styles with /contact (contact.css); help.css adds the FAQ list and the lower
 * sections. Contact links carry ?motiv= so the contact form opens with the right reason.
 */

require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/api.php';

// 30-minute page cache: static content.
$pageCacheTTL = 1800;
require_once __DIR__ . '/includes/page-cache.php';

require_once __DIR__ . '/includes/nav-helpers.php';
require_once __DIR__ . '/includes/v2/helpers.php';
require_once __DIR__ . '/includes/v2/nav.php';

// All FAQs inline (server-rendered for SEO + JSON-LD).
$faqs = [
    ['category' => 'orders', 'categoryLabel' => 'Comenzi', 'q' => 'Când primesc biletele după plată?',
        'a' => 'Biletele sunt emise după confirmarea plății și sunt trimise pe email. Dacă ai cont, le găsești și în zona Biletele mele.',
        'links' => [['Biletele mele', '/cont/bilete'], ['Recuperează comanda', '/recuperare-comanda']]],
    ['category' => 'orders', 'categoryLabel' => 'Comenzi', 'q' => 'Ce fac dacă nu am primit emailul de confirmare?',
        'a' => 'Verifică folderele Spam, Promotions sau Updates. Apoi folosește pagina de recuperare comandă cu emailul folosit la checkout și numărul comenzii, dacă îl ai.',
        'links' => [['Recuperare comandă', '/recuperare-comanda']]],
    ['category' => 'tickets', 'categoryLabel' => 'Bilete & QR', 'q' => 'Trebuie să printez biletul?',
        'a' => 'În mod normal nu. Poți arăta codul QR de pe telefon. Dacă o locație cere altceva, această informație apare pe pagina activității și în bilet.'],
    ['category' => 'tickets', 'categoryLabel' => 'Bilete & QR', 'q' => 'Pot pune nume diferite pe bilete?',
        'a' => 'Da. În checkout poți alege dacă biletele au același beneficiar sau dacă fiecare bilet are nume diferit. Util pentru grupuri, cadouri și comenzi corporate.'],
    ['category' => 'tickets', 'categoryLabel' => 'Bilete & QR', 'q' => 'Ce se întâmplă dacă QR-ul nu se scanează?',
        'a' => 'Personalul locației poate verifica biletul după cod, număr comandă sau datele beneficiarului, în funcție de procedura locației.'],
    ['category' => 'payments', 'categoryLabel' => 'Plăți & taxe', 'q' => 'Ce metode de plată sunt disponibile?',
        'a' => 'Checkout-ul include card (Visa, Mastercard), Apple Pay, Google Pay și Card Cultural (Edenred / Sodexo / Up România), în funcție de procesator.'],
    ['category' => 'payments', 'categoryLabel' => 'Plăți & taxe', 'q' => 'De ce apar comisioane separate în coș?',
        'a' => 'Comisioanele platformei și eventualele taxe de procesare sunt afișate separat pentru transparență. Totalul final apare înainte de confirmarea plății.'],
    ['category' => 'payments', 'categoryLabel' => 'Plăți & taxe', 'q' => 'Ce fac dacă plata a eșuat dar banii par blocați?',
        'a' => 'Unele plăți pot apărea temporar ca autorizări în contul bancar. Dacă plata nu este confirmată, comanda nu se emite. Verifică statusul comenzii sau contactează suportul.',
        'links' => [['Contact suport', '/contact']]],
    ['category' => 'refunds', 'categoryLabel' => 'Retururi', 'q' => 'Pot cere retur pentru bilete?',
        'a' => 'Returul depinde de politica activității, de statusul biletului, de momentul solicitării și de eventualele opțiuni cumpărate (de exemplu protecția bilet).',
        'links' => [['Trimite cerere', '/contact?motiv=retur#formular']]],
    ['category' => 'refunds', 'categoryLabel' => 'Retururi', 'q' => 'Ce este protecția bilet?',
        'a' => 'Protecția bilet este o opțiune suplimentară care poate oferi flexibilitate dacă nu mai poți ajunge. Condițiile exacte sunt afișate în checkout.'],
    ['category' => 'refunds', 'categoryLabel' => 'Retururi', 'q' => 'Cât durează rambursarea?',
        'a' => 'Durata depinde de procesator, banca emitentă și statusul cererii. După aprobarea returului, rambursarea poate dura câteva zile lucrătoare.'],
    ['category' => 'bonus', 'categoryLabel' => 'Puncte & carduri cadou', 'q' => 'Cum funcționează punctele bonus?',
        'a' => 'La comenzile eligibile primești puncte bonus. Acestea apar în cont după confirmarea comenzii și pot fi folosite la comenzile viitoare.',
        'links' => [['Punctele mele', '/cont/puncte']]],
    ['category' => 'bonus', 'categoryLabel' => 'Puncte & carduri cadou', 'q' => 'Pot folosi punctele în aceeași comandă?',
        'a' => 'În mod normal, punctele se câștigă după confirmarea unei comenzi și se folosesc la comenzi viitoare.'],
    ['category' => 'bonus', 'categoryLabel' => 'Puncte & carduri cadou', 'q' => 'Cum verific un card cadou?',
        'a' => 'Poți verifica soldul și validitatea unui card cadou sau voucher în pagina dedicată.',
        'links' => [['Verifică voucher', '/voucher'], ['Card cadou', '/card-cadou']]],
    ['category' => 'account', 'categoryLabel' => 'Cont client', 'q' => 'Mi se poate crea cont automat după checkout?',
        'a' => 'Da. Checkout-ul permite plasarea comenzii fără cont și crearea automată a contului, urmând să setezi ulterior parola.'],
    ['category' => 'account', 'categoryLabel' => 'Cont client', 'q' => 'Unde găsesc comenzile și biletele?',
        'a' => 'În contul client, în secțiunile Biletele mele și Comenzile mele. Acolo vezi statusuri, PDF-uri, QR-uri și istoricul.',
        'links' => [['Contul meu', '/cont']]],
    ['category' => 'venues', 'categoryLabel' => 'Locații', 'q' => 'Cum listez o locație pe bilete.online?',
        'a' => 'Accesează pagina Pentru locații și trimite detaliile despre locație, oraș, tipul activităților și cum vinzi acum biletele.',
        'links' => [['Pentru locații', '/pentru-locatii']]],
    ['category' => 'venues', 'categoryLabel' => 'Locații', 'q' => 'O locație poate avea mai multe activități?',
        'a' => 'Da. O locație poate avea o pagină principală și mai multe activități: escape rooms, tururi, ateliere, pachete, bilete de acces.'],
    ['category' => 'venues', 'categoryLabel' => 'Locații', 'q' => 'Cum se face check-in-ul la intrare?',
        'a' => 'Biletele sunt emise cu QR unic, iar staff-ul locației le poate scana pentru validare și controlul accesului.'],
];

$categoryCounts = [];
foreach ($faqs as $faq) {
    $categoryCounts[$faq['category']] = ($categoryCounts[$faq['category']] ?? 0) + 1;
}
$hpCategories = [
    ['key' => 'all', 'label' => 'Toate', 'count' => count($faqs)],
    ['key' => 'orders', 'label' => 'Comenzi', 'count' => $categoryCounts['orders'] ?? 0],
    ['key' => 'tickets', 'label' => 'Bilete & QR', 'count' => $categoryCounts['tickets'] ?? 0],
    ['key' => 'payments', 'label' => 'Plăți & taxe', 'count' => $categoryCounts['payments'] ?? 0],
    ['key' => 'refunds', 'label' => 'Retururi', 'count' => $categoryCounts['refunds'] ?? 0],
    ['key' => 'bonus', 'label' => 'Puncte & cadouri', 'count' => $categoryCounts['bonus'] ?? 0],
    ['key' => 'account', 'label' => 'Cont client', 'count' => $categoryCounts['account'] ?? 0],
    ['key' => 'venues', 'label' => 'Locații', 'count' => $categoryCounts['venues'] ?? 0],
];
$quickChips = [
    ['label' => 'Bilete QR', 'key' => 'tickets'],
    ['label' => 'Retururi', 'key' => 'refunds'],
    ['label' => 'Plăți', 'key' => 'payments'],
    ['label' => 'Puncte', 'key' => 'bonus'],
    ['label' => 'Locații', 'key' => 'venues'],
];
$hpTiles = [
    ['/recuperare-comanda', 'ticket', 'Recuperează comanda', 'Găsește biletele după email și număr comandă.'],
    ['/contact?motiv=retur#formular', 'coins', 'Cerere retur', 'Verifică politica și trimite o cerere structurată.'],
    ['/voucher', 'gift', 'Verifică voucher', 'Vezi soldul și validitatea cardului cadou.'],
    ['/contact', 'envelope-simple', 'Contact suport', 'Nu ai găsit răspunsul? Scrie-ne.'],
];

$pageTitleRaw = 'Întrebări frecvente și ajutor — ' . SITE_NAME;
$pageDescription = 'Răspunsuri rapide despre comenzi, bilete QR, plăți, retururi, protecție bilet, puncte bonus, carduri cadou, cont client și acces pentru locații.';
$canonicalUrl = SITE_URL . '/ajutor';
$structuredData = [[
    '@context' => 'https://schema.org',
    '@type' => 'FAQPage',
    'mainEntity' => array_map(fn ($f) => [
        '@type' => 'Question',
        'name' => $f['q'],
        'acceptedAnswer' => ['@type' => 'Answer', 'text' => $f['a']],
    ], $faqs),
]];

$v2Styles = ['contact.css', 'help.css'];
$v2Scripts = ['help.js'];
$v2HeaderOverlay = true;
$v2ClientData = ['categories' => array_column($hpCategories, 'label', 'key')];

include __DIR__ . '/includes/v2/head.php';
include __DIR__ . '/includes/v2/header.php';
?>
<main id="main" tabindex="-1">
  <!-- HERO -->
  <section class="ct-hero hp-hero" aria-labelledby="hp-h">
    <svg class="deco-arches" viewBox="0 0 400 400" aria-hidden="true" focusable="false"><path d="M40 400V200a160 160 0 0 1 320 0v200"/><path d="M90 400V200a110 110 0 0 1 220 0v200"/><path d="M140 400V200a60 60 0 0 1 120 0v200"/></svg>
    <svg class="ct-line draw-clip" viewBox="0 590 3240 310" aria-hidden="true" focusable="false"><use href="#drum-g"/></svg>
    <div class="ct-in">
      <div class="ct-copy">
        <p class="ct-kicker">FAQ · comenzi · bilete · locații</p>
        <h1 class="ct-h hp-h" id="hp-h">Răspunsuri rapide, fără ping-pong cu suportul.</h1>
        <p class="ct-lead">Găsește răspunsuri despre comenzi, bilete QR, plăți, taxe, retururi, protecție bilet, puncte bonus, carduri cadou, cont client și acces pentru locații.</p>
        <form class="hp-search" id="hp-search-form" role="search" action="/ajutor" method="get">
          <label class="sr" for="hp-search">Caută în întrebări</label>
          <?= v2_ic('magnifying-glass') ?>
          <input id="hp-search" name="q" type="search" autocomplete="off" enterkeyhint="search" placeholder="Caută: bilete, retur, voucher, puncte, plată...">
          <button class="hp-clear" id="hp-clear" type="button" aria-label="Șterge căutarea" hidden><?= v2_ic('x') ?></button>
        </form>
        <p class="hp-found" id="hp-found" role="status"><span id="hp-found-t"></span><a href="#intrebari" id="hp-jump" hidden>Vezi răspunsurile<?= v2_ic('arrow-right') ?></a></p>
        <div class="hp-chips" aria-label="Subiecte frecvente">
          <?php foreach ($quickChips as $chip): ?>
          <button type="button" data-hp-chip="<?= v2_e($chip['key']) ?>"><?= v2_e($chip['label']) ?></button>
          <?php endforeach; ?>
        </div>
      </div>
      <!-- the column always exists so the header turns solid at the white card on desktop and at the end of the hero
           on a phone, where the card is hidden (the fast actions below cover the same routes) -->
      <div class="ct-router-col">
        <div id="hdr-sentinel" aria-hidden="true"></div>
        <div class="ct-router">
          <p class="ct-router-k">Help router</p>
          <h2 class="ct-router-h">Cele mai rapide rezolvări.</h2>
          <div class="ct-router-list">
            <a class="ct-route is-primary" href="/recuperare-comanda"><span><b>Nu găsesc biletele</b><small>recuperare comandă</small></span><?= v2_ic('ticket') ?></a>
            <a class="ct-route" href="/contact?motiv=retur#formular"><span><b>Vreau retur</b><small>cerere / status</small></span><?= v2_ic('coins') ?></a>
            <a class="ct-route is-mint" href="/voucher"><span><b>Card cadou</b><small>verificare sold / cod</small></span><?= v2_ic('gift') ?></a>
          </div>
        </div>
      </div>
    </div>
  </section>

  <!-- FAST ACTIONS -->
  <section class="ct-fast" aria-label="Acțiuni rapide">
    <div class="wrap ct-tiles">
      <?php foreach ($hpTiles as $ti => [$tileHref, $tileIcon, $tileTitle, $tileText]): ?>
      <a class="ct-tile<?= $ti === 3 ? ' is-deep' : '' ?>" href="<?= v2_e($tileHref) ?>">
        <span class="ct-tile-ic"><?= v2_ic($tileIcon) ?></span>
        <h2><?= v2_e($tileTitle) ?></h2>
        <p><?= v2_e($tileText) ?></p>
        <span class="ct-tile-go" aria-hidden="true"><?= v2_ic('arrow-right') ?></span>
      </a>
      <?php endforeach; ?>
    </div>
  </section>

  <!-- FAQ LIST -->
  <section class="hp-faq" id="intrebari" aria-labelledby="hp-cat-title">
    <div class="wrap hp-faq-grid">
      <aside class="hp-aside" aria-label="Categorii FAQ">
        <div class="hp-aside-card">
          <p class="hp-aside-k">Categorii FAQ</p>
          <div class="hp-cats">
            <?php foreach ($hpCategories as $cat): ?>
            <button type="button" data-hp-cat="<?= v2_e($cat['key']) ?>" aria-pressed="<?= $cat['key'] === 'all' ? 'true' : 'false' ?>"><span><?= v2_e($cat['label']) ?></span><small><?= (int) $cat['count'] ?></small></button>
            <?php endforeach; ?>
          </div>
          <div class="hp-tip">
            <b>Nu știi unde se încadrează?</b>
            <p>Caută după cuvinte simple: „QR”, „retur”, „voucher”, „taxă”, „nume bilet”.</p>
          </div>
        </div>
      </aside>

      <div class="hp-main">
        <div class="hp-main-head">
          <div>
            <p class="kicker">Întrebări</p>
            <h2 id="hp-cat-title" tabindex="-1">Toate</h2>
          </div>
          <p class="hp-count" id="hp-count"><?= count($faqs) ?> din <?= count($faqs) ?> întrebări</p>
        </div>
        <div class="hp-list" id="hp-list">
          <?php foreach ($faqs as $faq): ?>
          <details class="hp-item" data-cat="<?= v2_e($faq['category']) ?>">
            <summary>
              <span class="hp-q"><small><?= v2_e($faq['categoryLabel']) ?></small><?= v2_e($faq['q']) ?></span>
              <span class="pm"><?= v2_ic('plus') ?></span>
            </summary>
            <div class="hp-a">
              <p><?= v2_e($faq['a']) ?></p>
              <?php if (!empty($faq['links'])): ?>
              <div class="hp-links">
                <?php foreach ($faq['links'] as [$linkLabel, $linkHref]): ?><a href="<?= v2_e($linkHref) ?>"><?= v2_e($linkLabel) ?><?= v2_ic('arrow-right') ?></a><?php endforeach; ?>
              </div>
              <?php endif; ?>
            </div>
          </details>
          <?php endforeach; ?>
          <div class="hp-empty" id="hp-empty" hidden>
            <h3>Nu am găsit întrebări pentru filtrul ales.</h3>
            <p>Încearcă un termen mai general sau resetează categoria.</p>
            <button class="btn btn-primary" id="hp-reset" type="button">Resetează</button>
          </div>
        </div>
      </div>
    </div>
  </section>

  <!-- STILL NEED HELP -->
  <section class="hp-help" aria-labelledby="hp-help-h">
    <div class="wrap hp-help-grid">
      <div>
        <p class="hp-help-k">Suport</p>
        <h2 id="hp-help-h">Nu ai găsit răspunsul?</h2>
        <p class="hp-help-p">Folosește pagina de contact și alege motivul corect. Pentru comenzi, include emailul folosit, numărul comenzii și numele activității.</p>
      </div>
      <div class="hp-router">
        <div class="hp-router-head">
          <p class="hp-router-k">Contact router</p>
          <h3>Trimite-ne contextul corect</h3>
        </div>
        <div class="hp-router-list">
          <a href="/contact?motiv=comanda#formular"><span><b>Problemă cu o comandă</b><small>bilete, QR, email, plată</small></span><?= v2_ic('arrow-right') ?></a>
          <a href="/contact?motiv=retur#formular"><span><b>Retur sau rambursare</b><small>eligibilitate, status, protecție bilet</small></span><?= v2_ic('arrow-right') ?></a>
          <a href="/contact?motiv=locatie#formular"><span><b>Locație / organizator</b><small>listare, demo, dashboard</small></span><?= v2_ic('arrow-right') ?></a>
        </div>
      </div>
    </div>
  </section>

  <!-- FINAL CTA -->
</main>
<?php include __DIR__ . '/includes/v2/footer.php'; ?>
