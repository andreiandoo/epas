<?php
/**
 * Operator help centre: /organizator/help (help.php), v2 design.
 *
 * Inside the v2 operator shell. Every section of the old page, restyled: the search over the questions, the three topic
 * cards, the three groups of questions (Începe rapid, the catalogue, Plăți și finanțe; the anchors #getting-started,
 * #activities and #payments are kept for old links) and the contact block (support hours, a ticket, the e-mail and the
 * phone when core's /config has one). org-help.js filters the questions while typing.
 *
 * Rewritten for operators (bilete.online operators sell locations and products and get bookings; they sell no events):
 * every question is still there, but answers that sent them to "Activități", "Participanți" or "Promo" (screens they no
 * longer have) now point to Locațiile mele, Produse, Rezervări, Sold and Cont & companie. The promo codes question says
 * codes are not available for operators yet. New: a message when the search finds nothing.
 */

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/api.php';
require_once __DIR__ . '/../includes/nav-helpers.php';
require_once __DIR__ . '/../includes/v2/helpers.php';
require_once __DIR__ . '/../includes/v2/organizer.php';

$configData = api_cached('client_config', fn() => api_get('/config'), 3600);
$contactPhone = trim((string) ($configData['data']['contact']['phone'] ?? SUPPORT_PHONE));
$contactEmail = trim((string) ($configData['data']['contact']['email'] ?? SUPPORT_EMAIL));
if ($contactEmail === '') {
    $contactEmail = SUPPORT_EMAIL;
}

$pageTitleRaw = 'Centru de ajutor — ' . SITE_NAME;
$pageDescription = 'Răspunsuri pentru operatorii bilete.online: locații, produse, rezervări, verificarea biletelor, comisioane și plăți.';
$canonicalUrl = SITE_URL . '/organizator/help';
$noindex = true;
$skipPageCache = true;

$v2Styles = ['organizer.css', 'org-help.css'];
$v2Scripts = ['organizer.js', 'org-help.js'];
$v2LegacyScripts = ['assets/js/config.js', 'assets/js/utils.js', 'assets/js/api.js', 'assets/js/auth.js'];
$v2HeadExtra = v2_account_client_config('organizer');

/** One question: the question (plain text) and the answer (trusted markup). */
$ohpQ = function (string $q, string $a) {
    return '<details class="ohp-q"><summary><span class="ohp-q-t">' . v2_e($q) . '</span>' . v2_ic('caret-down', 'ic ohp-q-ic') . '</summary>'
        . '<div class="ohp-a">' . $a . '</div></details>';
};
$ohpTopic = function (string $href, string $icon, string $title, string $sub) {
    return '<a class="ohp-topic" href="' . $href . '"><span class="ohp-topic-ic">' . v2_ic($icon) . '</span>'
        . '<span class="ohp-topic-t"><b>' . $title . '</b><span>' . $sub . '</span></span>' . v2_ic('arrow-right', 'ic ohp-topic-go') . '</a>';
};
$ohpSite = v2_e(SITE_NAME);

include __DIR__ . '/../includes/v2/head.php';
v2_org_start('help');
?>
<div class="ohp" id="ohp">
  <header class="ohp-head">
    <p class="org-k">Ajutor</p>
    <h1 class="ohp-h">Centru de ajutor</h1>
    <p class="ohp-lead">Găsește răspunsuri rapide sau contactează-ne.</p>
    <form class="ohp-search" id="ohp-search" role="search" action="#" novalidate>
      <label class="sr" for="ohp-q">Caută în centrul de ajutor</label>
      <?= v2_ic('magnifying-glass', 'ic ohp-search-ic') ?>
      <input type="search" id="ohp-q" autocomplete="off" spellcheck="false" enterkeyhint="search" maxlength="80" placeholder="Caută în centrul de ajutor…" aria-describedby="ohp-found">
      <button class="ohp-clear" type="button" id="ohp-clear" hidden><?= v2_ic('x') ?><span class="sr">Șterge căutarea</span></button>
    </form>
    <p class="ohp-found" id="ohp-found" role="status" aria-live="polite"></p>
  </header>

  <nav class="ohp-topics" aria-label="Subiecte">
    <?= $ohpTopic('#getting-started', 'lightning', 'Începe rapid', 'Ghid pentru prima locație și primul produs') ?>
    <?= $ohpTopic('#activities', 'map-pin', 'Locații și produse', 'Tot despre locații, produse și rezervări') ?>
    <?= $ohpTopic('#payments', 'wallet', 'Plăți și finanțe', 'Comisioane și deconturi') ?>
  </nav>

  <div class="ohp-secs">
    <section class="org-panel ohp-sec" id="getting-started" aria-labelledby="ohp-s1-h" tabindex="-1">
      <div class="ohp-sec-head"><span class="ohp-sec-ic"><?= v2_ic('lightning') ?></span><h2 class="org-panel-h" id="ohp-s1-h">Începe rapid</h2></div>
      <div class="ohp-list">
        <?= $ohpQ('Cum încep să vând pe ' . SITE_NAME . '?', '<ol>'
            . '<li>Deschide <a href="/organizator/locatii">Locațiile mele</a> și apasă „Adaugă o locație”.</li>'
            . '<li>Completează pagina locației: textele, orașul, adresa, pozele și programul.</li>'
            . '<li>Trimite locația spre aprobare. Prima publicare așteaptă aprobarea echipei ' . $ohpSite . '.</li>'
            . '<li>Deschide <a href="/organizator/produse">Produse</a>, apasă „Produs nou” și alege ce vinzi: bilet de acces, experiență sau pachet.</li>'
            . '<li>Adaugă biletele, cu prețurile lor. Poza e opțională: fără ea, produsul folosește poza locației.</li>'
            . '<li>Trimite produsul spre aprobare. După aprobare, apare pe site.</li>'
            . '</ol>') ?>
        <?= $ohpQ('Ce tipuri de produse pot crea?', '<p>Un produs poate fi de trei feluri:</p>'
            . '<ul>'
            . '<li><strong>Bilet de acces</strong>: intrarea pentru o zi sau mai multe, parcare, camping; bilete de adult, copil sau grup.</li>'
            . '<li><strong>Experiență</strong>: închirieri, tururi, ateliere; cu ore de început sau pe toată ziua, la persoană sau la barcă / grup.</li>'
            . '<li><strong>Pachet</strong>: bilete de acces și experiențe împreună, la un singur preț.</li>'
            . '</ul>'
            . '<p>În fiecare produs pui câte bilete vrei, cu ce nume vrei. Numele biletului apare pe biletul clientului și la cumpărare, așa că alege-l clar și ușor de înțeles.</p>'
            . '<p class="ohp-a-k">Exemple de nume:</p>'
            . '<ul>'
            . '<li><strong>Adult</strong> / <strong>Acces general</strong>: intrarea obișnuită</li>'
            . '<li><strong>Copil</strong>: prețul pentru copii</li>'
            . '<li><strong>Grup</strong>: un bilet pentru mai multe persoane</li>'
            . '<li><strong>Bilet de o zi</strong> / <strong>Abonament</strong>: valabil într-o zi sau mai multe zile</li>'
            . '<li><strong>Parcare</strong> / <strong>Camping</strong>: ce mai oferă locația, pe lângă intrare</li>'
            . '</ul>'
            . '<p>Fiecare bilet are prețul și descrierea lui. Dacă ai un număr limitat de locuri pe zi, îl setezi în produs.</p>') ?>
        <?= $ohpQ('Cum verific biletele la intrare?', '<p>Ai două variante:</p>'
            . '<ul>'
            . '<li><strong>Scanare QR</strong>: biletele se validează la intrare cu aplicația de scanare, din camera telefonului.</li>'
            . '<li><strong>Lista zilei</strong>: în <a href="/organizator/rezervari">Rezervări</a>, la „Pe zile”, vezi cine vine în fiecare zi, pe produs și pe oră. Tot acolo marchezi un vizitator care a plătit, dar nu a venit.</li>'
            . '</ul>') ?>
      </div>
    </section>

    <section class="org-panel ohp-sec" id="activities" aria-labelledby="ohp-s2-h" tabindex="-1">
      <div class="ohp-sec-head"><span class="ohp-sec-ic"><?= v2_ic('map-pin') ?></span><h2 class="org-panel-h" id="ohp-s2-h">Locații și produse</h2></div>
      <div class="ohp-list">
        <?= $ohpQ('Cum modific o locație sau un produs publicat?', '<p>Deschide-l din <a href="/organizator/locatii">Locațiile mele</a> sau din <a href="/organizator/produse">Produse</a>, schimbă ce vrei și apasă „Salvează”. După prima aprobare, modificările apar pe site imediat.</p>'
            . '<p>Prețul rezervărilor deja plătite nu se modifică.</p>') ?>
        <?= $ohpQ('Pot opri vânzarea sau anula o zi?', '<p>Da, în funcție de ce ai nevoie:</p>'
            . '<ul>'
            . '<li>Ca o locație sau un produs să nu mai apară pe site, apasă „Ascunde de pe site” în pagina lor. Îl pui la loc cu „Pune pe site”.</li>'
            . '<li>Pentru o zi în care locația e închisă, adaug-o la „Zile în care e închis”, în programul locației.</li>'
            . '<li>Pentru rezervări deja plătite care trebuie anulate, <a href="/organizator/suport">contactează suportul</a>. Clienții vor fi notificați și rambursați conform politicii.</li>'
            . '</ul>') ?>
        <?= $ohpQ('Pot face coduri promoționale sau reduceri?', '<p>Codurile promoționale nu sunt încă disponibile în contul de operator.</p>'
            . '<p>Poți avea prețuri diferite pe bilete, de exemplu pentru adult, copil sau grup. Pentru o campanie cu reducere, <a href="/organizator/suport">deschide un tichet</a> și îți spunem ce opțiuni ai.</p>') ?>
      </div>
    </section>

    <section class="org-panel ohp-sec" id="payments" aria-labelledby="ohp-s3-h" tabindex="-1">
      <div class="ohp-sec-head"><span class="ohp-sec-ic"><?= v2_ic('wallet') ?></span><h2 class="org-panel-h" id="ohp-s3-h">Plăți și finanțe</h2></div>
      <div class="ohp-list">
        <?= $ohpQ('Care sunt comisioanele ' . SITE_NAME . '?', '<p>Comisionul este cel negociat, trecut în contractul tău. Îl vezi oricând în <a href="/organizator/setari#contract">Cont & companie › Contract</a>. Pe lângă el se pot aplica taxele legale, acolo unde e cazul.</p>') ?>
        <?= $ohpQ('Când primesc banii din vânzări?', '<p>Plata se face conform contractului, în contul bancar din <a href="/organizator/setari#bank">Cont & companie</a>. În <a href="/organizator/sold">Sold</a> vezi cât ai de primit și ce ți s-a plătit deja.</p>'
            . '<p>La cerere, plata se poate face și când vânzările ating un prag minim agreat.</p>') ?>
        <?= $ohpQ('Cum gestionez rambursările?', '<p>Rambursările pentru anulări se procesează automat. Pentru cereri individuale, <a href="/organizator/suport">contactează suportul</a>.</p>'
            . '<p>Condițiile de anulare ale fiecărui produs le scrii în pagina produsului, la „Anulare”.</p>') ?>
      </div>
    </section>
  </div>

  <div class="org-empty ohp-empty" id="ohp-empty" hidden>
    <span class="org-empty-ic"><?= v2_ic('magnifying-glass') ?></span>
    <b>Nicio întrebare nu se potrivește</b>
    <p>Încearcă alte cuvinte sau scrie-ne din secțiunea de mai jos.</p>
    <button class="btn btn-ghost" type="button" id="ohp-reset">Arată toate întrebările</button>
  </div>

  <section class="ohp-contact" id="contact" aria-labelledby="ohp-contact-h" tabindex="-1">
    <span class="ohp-contact-ic"><?= v2_ic('headset') ?></span>
    <h2 class="ohp-contact-h" id="ohp-contact-h">Nu ai găsit ce căutai?</h2>
    <p class="ohp-contact-p">Echipa de suport e disponibilă L–V, 9:00–18:00.</p>
    <div class="ohp-contact-act">
      <a class="btn btn-light" href="/organizator/suport"><?= v2_ic('headset') ?>Deschide un tichet</a>
      <a class="btn btn-outline-light" href="mailto:<?= v2_e($contactEmail) ?>"><?= v2_ic('envelope-simple') ?><span class="ohp-ellip"><?= v2_e($contactEmail) ?></span></a>
      <?php if ($contactPhone !== ''): ?>
      <a class="btn btn-outline-light" href="tel:<?= v2_e(preg_replace('/[^0-9+]/', '', $contactPhone)) ?>"><?= v2_ic('phone') ?><?= v2_e($contactPhone) ?></a>
      <?php endif; ?>
    </div>
  </section>
</div>
<?php
v2_org_end();
include __DIR__ . '/../includes/v2/foot.php';
