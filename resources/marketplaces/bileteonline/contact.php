<?php
/**
 * Contact and help: /contact (v2 design).
 *
 * Support routing page: hero with a route card, 4 fast actions, the contact form (reason and priority change the hints
 * and the routing note), contact routes, FAQ.
 *
 * contact.js posts the form to `POST /contact` through the API proxy (core MarketplaceClient\ConfigController::contact),
 * which emails the marketplace inbox with reply-to set to the visitor. Core validates first_name, last_name, email,
 * subject (≤50), message (≤5000), optional phone and order_id, plus a honeypot (website_url). The old page sent name,
 * reason, priority and reference_id instead, so every message failed validation, and its error handler showed "sent"
 * anyway: messages were lost. The form now asks for first and last name, maps the reason to core's subject, keeps the
 * visitor's own subject line, priority and reference inside the message, and says when sending fails.
 */

require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/api.php';

// 30-minute page cache: static content, the form posts through the proxy.
$pageCacheTTL = 1800;
require_once __DIR__ . '/includes/page-cache.php';

require_once __DIR__ . '/includes/nav-helpers.php';
require_once __DIR__ . '/includes/v2/helpers.php';
require_once __DIR__ . '/includes/v2/nav.php';

// reason => label, core subject (a known key where one fits, else a short label), reference field, placeholders
$ctReasons = [
    'order' => ['label' => 'Comandă / bilete', 'subject' => 'bilete', 'ref' => 'Număr comandă opțional', 'refPh' => 'ex. MKT-W08ABJWH', 'orderRef' => true,
        'subjectPh' => 'Ex: Nu am primit biletele', 'messagePh' => 'Descrie problema: ce activitate ai cumpărat, ce email ai folosit, ce mesaj de eroare apare.'],
    'refund' => ['label' => 'Retur / rambursare', 'subject' => 'rambursare', 'ref' => 'Număr comandă', 'refPh' => 'ex. MKT-W08ABJWH', 'orderRef' => true,
        'subjectPh' => 'Ex: Vreau să verific statusul cererii de retur', 'messagePh' => 'Spune ce bilete vrei să returnezi, motivul și dacă ai cumpărat protecție bilet.'],
    'gift' => ['label' => 'Card cadou / voucher', 'subject' => 'Card cadou / voucher', 'ref' => 'Cod card cadou opțional', 'refPh' => 'GIFT-2026-WOW', 'orderRef' => false,
        'subjectPh' => 'Ex: Cardul cadou nu se aplică în checkout', 'messagePh' => 'Include codul voucherului și ce se întâmplă când îl introduci.'],
    'venue' => ['label' => 'Locație / organizator', 'subject' => 'organizator', 'ref' => 'Website locație / link social', 'refPh' => 'https://...', 'orderRef' => false,
        'subjectPh' => 'Ex: Vreau să listez locația pe bilete.online', 'messagePh' => 'Descrie locația, orașul, tipurile de activități, programul și cum vinzi acum biletele.'],
    'partnership' => ['label' => 'Parteneriat / afiliere', 'subject' => 'parteneriat', 'ref' => 'Website / canal', 'refPh' => 'website / Instagram / newsletter', 'orderRef' => false,
        'subjectPh' => 'Ex: Propunere colaborare / afiliere', 'messagePh' => 'Descrie audiența, canalul, tipul de colaborare și ce rezultate urmărești.'],
    'press' => ['label' => 'Presă / brand', 'subject' => 'Presă / brand', 'ref' => 'Publicație / organizație', 'refPh' => 'nume publicație / companie', 'orderRef' => false,
        'subjectPh' => 'Ex: Solicitare presă / brand assets', 'messagePh' => 'Spune ce informații ai nevoie, termenul limită și contextul materialului.'],
    'other' => ['label' => 'Alt motiv', 'subject' => 'altele', 'ref' => 'Referință opțională', 'refPh' => '', 'orderRef' => false,
        'subjectPh' => 'Ex: Întrebare despre platformă', 'messagePh' => 'Scrie cât mai clar întrebarea sau situația.'],
];
$ctPriorities = ['normal' => 'Normal', 'today' => 'Activitate azi', 'payment' => 'Problemă plată', 'access' => 'Problemă la intrare'];
$ctMessageMax = 4500; // core allows 5000; the rest holds the subject, priority and reference lines

$ctTiles = [
    ['/recuperare-comanda', 'ticket', 'Recuperează comanda', 'Nu ai primit emailul sau nu găsești biletele?', ''],
    ['#formular', 'coins', 'Cerere retur', 'Verifică eligibilitatea și trimite o cerere.', 'refund'],
    ['/card-cadou', 'gift', 'Card cadou', 'Cumpără sau verifică sold-ul unui voucher.', ''],
    ['/pentru-locatii', 'map-pin', 'Pentru locații', 'Listează activități și vinde bilete online.', ''],
];
$ctRoutes = [
    ['Client', 'ticket', 'Comenzi & bilete', 'Pentru bilete nelivrate, PDF, QR, nume beneficiar sau calendar.', '/recuperare-comanda', 'Recuperare comandă', '', ''],
    ['Retur', 'coins', 'Retur & protecție bilet', 'Pentru anulări, status, protecție bilet sau rambursări.', '#formular', 'Trimite cerere', 'refund', 'is-mint'],
    ['Gift', 'gift', 'Carduri cadou', 'Pentru coduri, sold, livrare sau voucher invalid.', '/voucher', 'Verifică voucher', '', ''],
    ['B2B', 'map-pin', 'Locații & organizatori', 'Pentru listare, demo, dashboard sau activități noi.', '/pentru-locatii', 'Pentru locații', '', ''],
    ['Parteneriat', 'users-three', 'Afiliere & colaborări', 'Pentru ghiduri locale, influenceri, media, turism.', '#formular', 'Trimite propunere', 'partnership', 'is-deep'],
    ['Legal', 'lock-simple', 'Privacy, cookies, termeni', 'Pentru solicitări GDPR, termeni, cookies sau raportări.', '/confidentialitate', 'Confidențialitate', '', ''],
];
$faqs = [
    ['Nu am primit biletele. Ce fac?', 'Verifică folderul Spam / Promoții, apoi folosește pagina de recuperare comandă cu emailul și numărul comenzii. Dacă tot nu găsești biletele, trimite mesaj cu numărul comenzii.'],
    ['Pot cere retur pentru bilete?', 'Depinde de politica activității, statusul biletului și opțiunile cumpărate. Trimite un mesaj cu motivul Retur / rambursare.'],
    ['Am un card cadou care nu merge. Ce fac?', 'Verifică mai întâi codul în pagina dedicată. Dacă apare invalid sau sold greșit, include codul în mesajul către suport.'],
    ['Cum listez o locație pe bilete.online?', 'Selectează motivul Locație / organizator în formular, include orașul, tipul activităților și cum vinzi acum biletele. Te contactăm cu demo + pricing.'],
    ['Ce date personale sunt procesate prin formular?', 'Datele transmise sunt folosite pentru soluționarea solicitării. Vezi Politica de confidențialitate pentru detalii.'],
];
$defaultReason = $ctReasons['order'];

$pageTitleRaw = 'Contact și ajutor — ' . SITE_NAME;
$pageDescription = 'Ai o întrebare despre o comandă, bilete, retur, card cadou sau listare locație? Alege motivul potrivit și ajungi mai rapid la soluție.';
$canonicalUrl = SITE_URL . '/contact';

$v2Styles = ['contact.css'];
$v2Scripts = ['contact.js'];
$v2LegacyScripts = ['assets/js/config.js', 'assets/js/utils.js', 'assets/js/api.js'];
$v2HeaderOverlay = true;
$v2ClientData = ['reasons' => $ctReasons, 'priorities' => $ctPriorities, 'supportEmail' => SUPPORT_EMAIL, 'messageMax' => $ctMessageMax];
$v2HeadExtra = '<script>window.BILETEONLINE = ' . json_encode([
    'siteName' => SITE_NAME,
    'siteUrl' => SITE_URL,
    'apiUrl' => '/api/proxy.php',
    'storageUrl' => STORAGE_URL,
    'env' => API_ENV,
    'locale' => SITE_LOCALE,
    'currency' => 'RON',
    'supportEmail' => SUPPORT_EMAIL,
], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG) . ';</script>';

include __DIR__ . '/includes/v2/head.php';
include __DIR__ . '/includes/v2/header.php';
?>
<main id="main" tabindex="-1">
  <!-- HERO -->
  <section class="ct-hero" aria-labelledby="ct-h">
    <svg class="deco-arches" viewBox="0 0 400 400" aria-hidden="true" focusable="false"><path d="M40 400V200a160 160 0 0 1 320 0v200"/><path d="M90 400V200a110 110 0 0 1 220 0v200"/><path d="M140 400V200a60 60 0 0 1 120 0v200"/></svg>
    <svg class="ct-line draw-clip" viewBox="0 590 3240 310" aria-hidden="true" focusable="false"><use href="#drum-g"/></svg>
    <div class="ct-in">
      <div class="ct-copy">
        <p class="ct-kicker">Suport · comenzi · bilete · locații</p>
        <h1 class="ct-h" id="ct-h">Cu ce te putem ajuta?</h1>
        <p class="ct-lead">Ai o întrebare despre o comandă, nu găsești biletele, vrei să listezi o locație sau ai nevoie de ajutor cu un card cadou? Alege motivul potrivit și ajungi mai repede la soluție.</p>
        <div class="ct-cta">
          <a class="btn btn-light" href="/recuperare-comanda"><?= v2_ic('ticket') ?>Recuperează comanda</a>
          <a class="btn btn-outline-light" href="#formular"><?= v2_ic('envelope-simple') ?>Trimite mesaj</a>
        </div>
      </div>
      <!-- the column always exists so the header turns solid at the white card on desktop and at the end of the hero
           on a phone, where the card is hidden (the fast actions below cover the same routes) -->
      <div class="ct-router-col">
        <div id="hdr-sentinel" aria-hidden="true"></div>
        <div class="ct-router">
          <p class="ct-router-k">Support router</p>
          <h2 class="ct-router-h">Alege traseul corect.</h2>
          <div class="ct-router-list">
            <a class="ct-route is-primary" href="/recuperare-comanda"><span><b>Nu găsesc biletele</b><small>recuperare comandă</small></span><?= v2_ic('ticket') ?></a>
            <a class="ct-route" href="#formular" data-reason="refund"><span><b>Vreau retur</b><small>cerere / status</small></span><?= v2_ic('coins') ?></a>
            <a class="ct-route is-mint" href="/voucher"><span><b>Card cadou</b><small>verificare / sold</small></span><?= v2_ic('gift') ?></a>
          </div>
        </div>
      </div>
    </div>
  </section>

  <!-- FAST ACTIONS -->
  <section class="ct-fast" aria-label="Acțiuni rapide">
    <div class="wrap ct-tiles">
      <?php foreach ($ctTiles as $ti => [$href, $icon, $title, $text, $reason]): ?>
      <a class="ct-tile<?= $ti === 3 ? ' is-deep' : '' ?>" href="<?= v2_e($href) ?>"<?= $reason ? ' data-reason="' . v2_e($reason) . '"' : '' ?>>
        <span class="ct-tile-ic"><?= v2_ic($icon) ?></span>
        <h2><?= v2_e($title) ?></h2>
        <p><?= v2_e($text) ?></p>
        <span class="ct-tile-go" aria-hidden="true"><?= v2_ic('arrow-right') ?></span>
      </a>
      <?php endforeach; ?>
    </div>
  </section>

  <!-- CONTACT FORM -->
  <section class="ct-form-sec" id="formular" aria-labelledby="ct-form-h">
    <div class="wrap ct-form-grid">
      <div class="ct-form-intro">
        <p class="kicker">Formular contact</p>
        <h2 id="ct-form-h">Trimite-ne detaliile corecte de la început.</h2>
        <p class="ct-form-lead">Cu cât alegi motivul potrivit și incluzi datele relevante, cu atât este mai ușor să ajungă mesajul la fluxul corect.</p>
        <div class="ct-include">
          <b>Pentru comenzi, include:</b>
          <ul>
            <li><?= v2_ic('check') ?>numărul comenzii, dacă îl ai;</li>
            <li><?= v2_ic('check') ?>emailul folosit la comandă;</li>
            <li><?= v2_ic('check') ?>numele activității;</li>
            <li><?= v2_ic('check') ?>ce s-a întâmplat concret.</li>
          </ul>
        </div>
      </div>

      <form class="ct-form" id="ct-form" novalidate>
        <div class="ct-sent" id="ct-sent" role="status" tabindex="-1" hidden>
          <?= v2_ic('check-circle') ?>
          <div><b>Mesajul a fost trimis ✓</b><p>Îți răspundem pe emailul indicat în cel mai scurt timp. Verifică inbox-ul + folderul Spam.</p></div>
        </div>
        <p class="ct-error" id="ct-error" role="alert" tabindex="-1" hidden></p>

        <!-- honeypot: people never see or reach it; bots that fill it are dropped by core -->
        <div class="ct-trap" aria-hidden="true"><label for="ct-website">Website (nu completa)</label><input id="ct-website" name="website_url" type="text" tabindex="-1" autocomplete="off"></div>

        <div class="ct-fields">
          <div class="ct-field">
            <label for="ct-reason">Motiv contact</label>
            <select class="select" id="ct-reason" name="reason">
              <?php foreach ($ctReasons as $key => $r): ?><option value="<?= $key ?>"><?= v2_e($r['label']) ?></option><?php endforeach; ?>
            </select>
          </div>
          <div class="ct-field">
            <label for="ct-priority">Prioritate</label>
            <select class="select" id="ct-priority" name="priority">
              <?php foreach ($ctPriorities as $key => $label): ?><option value="<?= $key ?>"><?= v2_e($label) ?></option><?php endforeach; ?>
            </select>
          </div>
          <div class="ct-field">
            <label for="ct-first">Prenume</label>
            <input id="ct-first" name="first_name" type="text" autocomplete="given-name" maxlength="100" required placeholder="Prenume">
          </div>
          <div class="ct-field">
            <label for="ct-last">Nume</label>
            <input id="ct-last" name="last_name" type="text" autocomplete="family-name" maxlength="100" required placeholder="Nume de familie">
          </div>
          <div class="ct-field">
            <label for="ct-email">Email</label>
            <input id="ct-email" name="email" type="email" inputmode="email" autocomplete="email" spellcheck="false" maxlength="180" required placeholder="email@example.ro">
          </div>
          <div class="ct-field">
            <label for="ct-phone">Telefon (opțional)</label>
            <input id="ct-phone" name="phone" type="tel" autocomplete="tel" maxlength="50" placeholder="+40...">
          </div>
          <div class="ct-field is-wide">
            <label for="ct-ref" id="ct-ref-label"><?= v2_e($defaultReason['ref']) ?></label>
            <input id="ct-ref" name="reference" type="text" autocomplete="off" maxlength="80" placeholder="<?= v2_e($defaultReason['refPh']) ?>">
          </div>
          <div class="ct-field is-wide">
            <label for="ct-subject">Subiect</label>
            <input id="ct-subject" name="subject" type="text" maxlength="150" required placeholder="<?= v2_e($defaultReason['subjectPh']) ?>">
          </div>
          <div class="ct-field is-wide">
            <label for="ct-message">Mesaj</label>
            <textarea id="ct-message" name="message" rows="6" maxlength="<?= $ctMessageMax ?>" required placeholder="<?= v2_e($defaultReason['messagePh']) ?>" aria-describedby="ct-count"></textarea>
            <span class="ct-count" id="ct-count">0 / <?= $ctMessageMax ?></span>
          </div>
          <label class="ct-check is-wide">
            <input id="ct-consent" name="consent" type="checkbox" required>
            <span>Confirm că datele trimise sunt corecte și accept prelucrarea lor pentru soluționarea solicitării conform <a href="/confidentialitate">Politicii de confidențialitate</a>.</span>
          </label>
        </div>

        <div class="ct-route-note" id="ct-route" data-tone="calm">
          <b id="ct-route-t">Mesaj direcționat către suport</b>
          <p id="ct-route-p">Include detalii clare ca solicitarea să poată fi procesată rapid.</p>
        </div>

        <button class="btn btn-primary ct-submit" id="ct-submit" type="submit">Trimite mesajul</button>
      </form>
    </div>
  </section>

  <!-- CONTACT ROUTES -->
  <section class="sec ct-routes" aria-labelledby="ct-routes-h">
    <div class="wrap">
      <div class="ct-routes-head">
        <p class="kicker">Rute contact</p>
        <h2 id="ct-routes-h">Fiecare solicitare are un traseu mai bun.</h2>
        <p>Pagina de contact reduce mesajele incomplete și trimite utilizatorul către acțiunea potrivită înainte să scrie suportului.</p>
      </div>
      <div class="ct-route-cards">
        <?php foreach ($ctRoutes as [$k, $icon, $title, $text, $href, $cta, $reason, $variant]): ?>
        <article class="ct-card <?= $variant ?>">
          <div class="ct-card-top"><p class="ct-card-k"><?= v2_e($k) ?></p><span class="ct-card-ic"><?= v2_ic($icon) ?></span></div>
          <h3><?= v2_e($title) ?></h3>
          <p><?= v2_e($text) ?></p>
          <a href="<?= v2_e($href) ?>"<?= $reason ? ' data-reason="' . v2_e($reason) . '"' : '' ?>><?= v2_e($cta) ?><?= v2_ic('arrow-right') ?></a>
        </article>
        <?php endforeach; ?>
      </div>
    </div>
  </section>

  <!-- FAQ -->
  <section class="sec ct-faq" aria-labelledby="ct-faq-h">
    <div class="wrap ct-faq-grid">
      <div>
        <p class="kicker">FAQ</p>
        <h2 id="ct-faq-h">Întrebări frecvente</h2>
      </div>
      <div>
        <?php foreach ($faqs as $fi => [$faqQ, $faqA]): ?>
        <details class="qa"<?= $fi === 0 ? ' open' : '' ?>><summary><?= v2_e($faqQ) ?><span class="pm"><?= v2_ic('plus') ?></span></summary><p><?= v2_e($faqA) ?></p></details>
        <?php endforeach; ?>
      </div>
    </div>
  </section>
</main>
<?php include __DIR__ . '/includes/v2/footer.php'; ?>
