<?php
/**
 * Organizer on-site sale: /organizator/pos (pos.php), v2 design.
 *
 * The POS that Ambilet runs as "InfoPoint", ported to the v2 organizer shell: the register (open, X report, close,
 * the day's sessions and their CSV), the products of the venue (categories, variants, add-ons, time slots, POS price),
 * the cart with the customer, the company and the invoice, the language of the ticket, cash / card / payment link,
 * and the receipt — on a thermal printer over WebUSB (pos-printer.js) or through the browser's print dialog.
 *
 * It works on an activity set up as a venue (display_template = leisure_venue); the page says so when there is none.
 * org-pos.js reads /organizer/events, then /organizer/events/{id}/leisure/config, and sells through .../leisure/pos-sale.
 */

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/api.php';
require_once __DIR__ . '/../includes/nav-helpers.php';
require_once __DIR__ . '/../includes/v2/helpers.php';
require_once __DIR__ . '/../includes/v2/organizer.php';

$pageTitleRaw = 'Casă & POS — ' . SITE_NAME;
$pageDescription = 'Vânzarea la fața locului pentru o locație de pe bilete.online: casă, bilete emise pe loc, bon pe imprimantă termică.';
$canonicalUrl = SITE_URL . '/organizator/pos';
$noindex = true;
$skipPageCache = true;

$v2Styles = ['organizer.css', 'org-pos.css'];
$v2Scripts = ['organizer.js', 'pos-printer.js', 'org-pos.js'];
$v2LegacyScripts = ['assets/js/config.js', 'assets/js/utils.js', 'assets/js/api.js', 'assets/js/auth.js'];
$v2HeadExtra = v2_account_client_config();

$poStat = function (string $key, string $label, string $tone = '') {
    return '<div class="po-x' . ($tone ? ' ' . $tone : '') . '"><p>' . $label . '</p><b id="po-x-' . $key . '">—</b></div>';
};

include __DIR__ . '/../includes/v2/head.php';
v2_org_start('pos');
?>
<div class="po" id="po">
  <header class="po-head">
    <div>
      <p class="po-eyebrow"><?= v2_ic('scan') ?>Vânzare la fața locului</p>
      <h1 class="po-h">Casă &amp; POS</h1>
      <p class="po-lead">Emiți bilete pe loc, încasezi cash sau card și tipărești bonul. Vânzările intră în aceleași rapoarte ca cele online.</p>
    </div>
    <div class="po-head-tools">
      <span class="po-field"><label for="po-event">Locația</label><span class="po-select"><select id="po-event" disabled><option>Se încarcă…</option></select><?= v2_ic('caret-down') ?></span></span>
      <span class="po-field"><label for="po-date">Data vizitei</label><input class="po-input" type="date" id="po-date"></span>
    </div>
  </header>

  <div class="org-empty" id="po-none" hidden>
    <span class="org-empty-ic"><?= v2_ic('door-open') ?></span>
    <b>Nicio locație pregătită pentru vânzare la fața locului</b>
    <p>POS-ul funcționează pe o activitate configurată ca locație, cu produsele marcate pentru vânzare la ghișeu. Scrie-ne și îți pregătim locația.</p>
    <a class="btn btn-primary" href="/organizator/suport">Cere activarea<?= v2_ic('arrow-right') ?></a>
  </div>

  <div class="org-empty is-error" id="po-failed" hidden>
    <span class="org-empty-ic"><?= v2_ic('warning-circle') ?></span>
    <b>Nu am putut încărca POS-ul</b>
    <p id="po-failed-t">Reîncearcă în câteva secunde.</p>
    <button class="btn btn-primary" type="button" id="po-retry">Reîncearcă</button>
  </div>

  <div class="po-main" id="po-main" hidden>
    <!-- ===== register bar ===== -->
    <section class="po-cash" id="po-cash" aria-labelledby="po-cash-h">
      <h2 class="po-sr" id="po-cash-h">Casa</h2>
      <div class="po-cash-row">
        <p class="po-cash-state" id="po-cash-state"><span class="po-dot" id="po-dot"></span><b id="po-cash-label">Se verifică…</b><small id="po-cash-since"></small></p>
        <div class="po-cash-btns">
          <button class="btn btn-primary" type="button" id="po-open" hidden><?= v2_ic('door-open') ?>Deschide casa</button>
          <button class="btn btn-ghost" type="button" id="po-xtoggle" hidden aria-expanded="false" aria-controls="po-x"><?= v2_ic('chart-line-up') ?>Raport X</button>
          <button class="btn btn-ghost" type="button" id="po-sessions-btn"><?= v2_ic('clock') ?>Desfășurător</button>
          <button class="btn btn-ghost" type="button" id="po-close" hidden><?= v2_ic('lock-simple') ?>Închide casa</button>
        </div>
      </div>
      <div class="po-xrep" id="po-x" hidden>
        <?= $poStat('cash', 'Cash de predat', 'is-warm') ?>
        <?= $poStat('card', 'Card încasat', 'is-info') ?>
        <?= $poStat('total', 'Total în casă', 'is-mint') ?>
        <?= $poStat('orders', 'Comenzi în sesiune') ?>
        <p class="po-xrep-note" id="po-x-note">Doar vânzările din locație. Cele online nu intră în casă.</p>
      </div>
    </section>

    <div class="po-locked" id="po-locked" hidden>
      <span><?= v2_ic('lock-simple') ?></span>
      <div><b>Casa este închisă</b><p>Deschide casa ca să poți vinde. Ora deschiderii și cea a închiderii rămân în desfășurător.</p></div>
    </div>

    <div class="po-grid">
      <!-- ===== products ===== -->
      <section class="org-panel po-products" aria-labelledby="po-prod-h">
        <div class="org-panel-head">
          <div><h2 class="org-panel-h" id="po-prod-h">Bilete și servicii</h2><p class="org-panel-p" id="po-prod-sub">Apasă un produs ca să îl adaugi în coș.</p></div>
          <label class="po-search"><?= v2_ic('magnifying-glass') ?><input id="po-q" type="search" placeholder="Caută produs" autocomplete="off" aria-label="Caută produs"></label>
        </div>
        <div class="po-skel" id="po-prod-skel"><span class="org-skel"></span><span class="org-skel"></span><span class="org-skel"></span><span class="org-skel"></span></div>
        <div class="po-cats" id="po-cats"></div>
        <p class="po-empty" id="po-prod-empty" hidden>Niciun produs marcat pentru vânzare la ghișeu. Bifează „Doar pentru vânzare POS” sau setează un preț POS pe produsele locației.</p>
      </section>

      <!-- ===== cart ===== -->
      <section class="org-panel po-cart" aria-labelledby="po-cart-h">
        <div class="po-cart-head">
          <h2 class="org-panel-h" id="po-cart-h">Coș</h2>
          <button class="po-clear" type="button" id="po-clear" hidden><?= v2_ic('trash') ?>Golește</button>
        </div>
        <ul class="po-lines" id="po-lines"><li class="po-lines-empty">Coșul e gol. Apasă pe un produs ca să îl adaugi.</li></ul>
        <p class="po-access" id="po-access" hidden></p>

        <details class="po-fold">
          <summary><?= v2_ic('user-plus') ?>Date client (opțional)</summary>
          <div class="po-fold-in">
            <span class="po-field"><label for="po-c-name">Nume</label><input class="po-input" type="text" id="po-c-name" maxlength="120" autocomplete="off"></span>
            <span class="po-field"><label for="po-c-email">Email, pentru biletele pe mail</label><input class="po-input" type="email" id="po-c-email" maxlength="120" autocomplete="off"></span>
            <span class="po-field"><label for="po-c-phone">Telefon</label><input class="po-input" type="tel" id="po-c-phone" maxlength="30" autocomplete="off"></span>
            <span class="po-field"><label for="po-c-plate">Număr de înmatriculare, pentru parcare</label><input class="po-input" type="text" id="po-c-plate" maxlength="20" autocomplete="off"></span>
            <span class="po-field"><label for="po-c-notes">Observații</label><textarea class="po-input" id="po-c-notes" rows="2" maxlength="500"></textarea></span>
          </div>
        </details>

        <details class="po-fold" id="po-company">
          <summary><?= v2_ic('identification-card') ?>Date firmă, pentru factură</summary>
          <div class="po-fold-in">
            <span class="po-field"><label for="po-co-name">Denumire firmă</label><input class="po-input" type="text" id="po-co-name" maxlength="200" autocomplete="off"></span>
            <div class="po-cui">
              <span class="po-field"><label for="po-co-cui">CUI / CIF</label><input class="po-input" type="text" id="po-co-cui" maxlength="30" placeholder="RO12345678" autocomplete="off"></span>
              <button class="btn btn-ghost" type="button" id="po-anaf"><?= v2_ic('magnifying-glass') ?>Caută la ANAF</button>
            </div>
            <p class="po-msg" id="po-anaf-msg" role="status" hidden></p>
            <span class="po-field"><label for="po-co-reg">Număr registrul comerțului</label><input class="po-input" type="text" id="po-co-reg" maxlength="60" autocomplete="off"></span>
            <span class="po-field"><label for="po-co-address">Sediu</label><input class="po-input" type="text" id="po-co-address" maxlength="255" autocomplete="off"></span>
            <div class="po-two">
              <span class="po-field"><label for="po-co-iban">IBAN</label><input class="po-input" type="text" id="po-co-iban" maxlength="34" autocomplete="off"></span>
              <span class="po-field"><label for="po-co-contact">Persoană de contact</label><input class="po-input" type="text" id="po-co-contact" maxlength="120" autocomplete="off"></span>
            </div>
            <label class="po-check"><input type="checkbox" id="po-co-invoice"><span>Generează factură fiscală după finalizare</span></label>
            <p class="po-hint">Fără bifă, factura nu se poate emite mai târziu: numărul se rezervă doar la finalizare.</p>
          </div>
        </details>

        <div class="po-lang">
          <p class="po-k">Limba biletului și a emailului</p>
          <div class="po-lang-btns" role="group" aria-label="Limba biletului">
            <button class="po-lang-b is-on" type="button" data-lang="ro" aria-pressed="true">RO</button>
            <button class="po-lang-b" type="button" data-lang="hu" aria-pressed="false">HU</button>
            <button class="po-lang-b" type="button" data-lang="en" aria-pressed="false">EN</button>
          </div>
        </div>

        <dl class="po-sums">
          <div><dt>Subtotal</dt><dd id="po-subtotal">0,00 lei</dd></div>
          <div id="po-com-row" hidden><dt>Comision ticketing</dt><dd id="po-commission">0,00 lei</dd></div>
          <div class="po-total"><dt>Total</dt><dd id="po-total">0,00 lei</dd></div>
        </dl>

        <p class="po-k">Metodă de plată</p>
        <div class="po-pay" role="group" aria-label="Metodă de plată">
          <button class="po-pay-b is-on" type="button" data-pay="cash" aria-pressed="true"><?= v2_ic('coins') ?>Cash</button>
          <button class="po-pay-b" type="button" data-pay="card" aria-pressed="false"><?= v2_ic('credit-card') ?>Card</button>
          <button class="po-pay-b" type="button" data-pay="invoice" aria-pressed="false"><?= v2_ic('envelope-simple') ?>Link pe email</button>
        </div>
        <p class="po-hint" id="po-pay-hint">Cash: încasezi acum, biletele sunt valide imediat. Card: plata se face la terminalul tău, iar aici o înregistrezi. Link pe email: clientul primește un link de plată.</p>
        <p class="po-msg is-error" id="po-error" role="alert" hidden></p>
        <button class="btn btn-primary po-checkout" type="button" id="po-checkout" disabled>Finalizează vânzarea</button>
      </section>
    </div>

    <!-- ===== printer ===== -->
    <details class="org-panel po-printer" id="po-printer">
      <summary><?= v2_ic('printer') ?><b>Imprimantă termică</b><span class="po-pr-state" id="po-pr-state">Se verifică…</span></summary>
      <div class="po-pr-in">
        <p class="po-hint" id="po-pr-info"></p>
        <p class="po-msg" id="po-pr-paper" role="status" hidden></p>
        <p class="po-msg is-error" id="po-pr-error" role="alert" hidden></p>
        <div class="po-pr-btns">
          <button class="btn btn-ghost" type="button" id="po-pr-connect"><?= v2_ic('printer') ?>Conectează imprimanta</button>
          <button class="btn btn-ghost" type="button" id="po-pr-test">Test de tipărire</button>
          <button class="btn btn-ghost" type="button" id="po-pr-reprint" hidden><?= v2_ic('arrow-counter-clockwise') ?>Retipărește ultima comandă<small id="po-pr-reprint-meta"></small></button>
        </div>
        <label class="po-check"><input type="checkbox" id="po-pr-auto"><span>Tipărește automat biletele după fiecare comandă</span></label>
        <p class="po-hint">Merge în Chrome sau Edge. Pe Windows, dacă imprimanta nu apare în listă, schimbă-i driverul cu WinUSB folosind Zadig. Fără imprimantă, bonul se tipărește prin fereastra de print a browserului.</p>
      </div>
    </details>

    <!-- ===== sessions ===== -->
    <section class="org-panel po-sessions" id="po-sessions" hidden aria-labelledby="po-sess-h">
      <div class="org-panel-head">
        <div><h2 class="org-panel-h" id="po-sess-h">Desfășurător casă</h2><p class="org-panel-p">Sesiunile de azi, cu ce s-a încasat în fiecare.</p></div>
        <div class="po-sess-tools">
          <button class="btn btn-ghost" type="button" id="po-csv"><?= v2_ic('download-simple') ?>Export CSV</button>
          <button class="btn btn-ghost" type="button" id="po-sess-refresh"><?= v2_ic('arrow-counter-clockwise') ?>Reîmprospătează</button>
        </div>
      </div>
      <div id="po-sess-body"><p class="po-empty">Se încarcă…</p></div>
    </section>
  </div>

  <!-- the receipt printed through the browser when there is no thermal printer -->
  <div class="po-receipt" id="po-receipt" hidden aria-hidden="true"></div>

  <dialog class="po-dialog" id="po-slot-d" aria-labelledby="po-slot-h">
    <div class="po-d-in">
      <div class="po-d-head"><h2 class="po-d-h" id="po-slot-h">Alege ora</h2><button class="po-x-btn" type="button" data-po-close aria-label="Închide"><?= v2_ic('x') ?></button></div>
      <p class="po-hint"><b id="po-slot-t"></b><br><span id="po-slot-hint"></span></p>
      <span class="po-field"><label for="po-slot-time">Ora</label><input class="po-input" type="time" id="po-slot-time" step="300"></span>
      <div class="po-d-act">
        <button class="btn btn-ghost" type="button" data-po-close>Renunță</button>
        <button class="btn btn-primary" type="button" id="po-slot-ok">Adaugă în coș</button>
      </div>
    </div>
  </dialog>

  <dialog class="po-dialog" id="po-close-d" aria-labelledby="po-close-h">
    <div class="po-d-in">
      <div class="po-d-head"><h2 class="po-d-h" id="po-close-h">Închizi casa?</h2><button class="po-x-btn" type="button" data-po-close aria-label="Închide"><?= v2_ic('x') ?></button></div>
      <div id="po-close-body"><p class="po-hint">Se salvează o fotografie a încasărilor din această sesiune: cât cash predai și cât s-a încasat pe card.</p></div>
      <div class="po-d-act">
        <button class="btn btn-ghost" type="button" data-po-close>Renunță</button>
        <button class="btn btn-primary" type="button" id="po-close-confirm">Închide casa</button>
      </div>
    </div>
  </dialog>
</div>
<?php
v2_org_end();
include __DIR__ . '/../includes/v2/foot.php';
