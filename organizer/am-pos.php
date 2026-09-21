<?php
/**
 * The operator's cash desk (POS): /organizator/pos (activities module), v2 design.
 *
 * Selling at the location: the products with their POS prices (POS-only ones included), what is left today (seats,
 * start times), a cart with quantities, start times, package times and plates, the operator's commission as online,
 * an optional customer (name, email, "send the tickets"), payment in cash or by card. The tickets come out at once:
 * printed from the browser (QR codes) or on a thermal printer (WebUSB, PosPrinter). The cash session is opened with
 * the cash in the drawer and closed with the cash counted; the day's sales are listed under the cart.
 *
 * org-am-pos.js reads .../locations, .../pos/catalog, .../pos/day, .../pos/session and .../pos/sales; it opens and closes
 * the session (POST .../pos/session, .../pos/session/{id}/close) and records sales (POST .../pos/sale).
 */

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/api.php';
require_once __DIR__ . '/../includes/nav-helpers.php';
require_once __DIR__ . '/../includes/v2/helpers.php';
require_once __DIR__ . '/../includes/v2/organizer.php';
require_once __DIR__ . '/../includes/v2/am-labels.php';

$pageTitleRaw = 'Casă & POS — ' . SITE_NAME;
$pageDescription = 'Vânzarea la casa locației: bilete de acces, experiențe și pachete, plată numerar sau card, bilete tipărite pe loc.';
$canonicalUrl = SITE_URL . '/organizator/pos';
$noindex = true;
$skipPageCache = true;

$v2Styles = ['organizer.css', 'org-venue.css', 'org-am.css', 'org-am-pos.css'];
$v2Scripts = ['vendor/qrcode.js', 'pos-printer.js', 'organizer.js', 'org-am.js', 'org-am-pos.js'];
$v2LegacyScripts = ['assets/js/config.js', 'assets/js/utils.js', 'assets/js/api.js', 'assets/js/auth.js'];
$v2HeadExtra = v2_account_client_config('organizer');
$v2ClientData = ['am' => am_client_labels()];

include __DIR__ . '/../includes/v2/head.php';
v2_org_start('am-pos');
?>
<div class="ve am pos" id="am-pos">
  <header class="ve-head">
    <div>
      <p class="ve-eyebrow"><?= v2_ic('scan') ?>Casă & POS</p>
      <h1 class="ve-h">Vânzare la casă</h1>
    </div>
    <div class="am-filters">
      <span class="po-field"><label for="pos-loc">Locația</label><span class="po-select"><select id="pos-loc"><option value="">Se încarcă…</option></select><?= v2_ic('caret-down') ?></span></span>
      <button class="btn btn-ghost" type="button" id="pos-printer" hidden><?= v2_ic('printer') ?><span>Conectează imprimanta</span></button>
    </div>
  </header>

  <div class="org-empty" id="pos-none" hidden>
    <span class="org-empty-ic"><?= v2_ic('map-pin') ?></span>
    <b>Nicio locație încă</b>
    <p>Casa vinde produsele unei locații. Adaug-o întâi, cu produsele ei.</p>
    <a class="btn btn-primary" href="/organizator/locatii?nou=1"><?= v2_ic('plus') ?>Adaugă locația</a>
  </div>

  <section class="pos-session" id="pos-session" aria-live="polite" hidden></section>

  <div class="pos-grid" id="pos-main" hidden>
    <section class="pos-products" aria-labelledby="pos-prod-h">
      <div class="pos-head">
        <h2 class="org-panel-h" id="pos-prod-h">Produse</h2>
        <p class="pos-hours" id="pos-hours"></p>
      </div>
      <div class="pos-cats" id="pos-cats" role="group" aria-label="Categorii" hidden></div>
      <div class="pos-list" id="pos-list"><p class="ve-state">Se încarcă…</p></div>
    </section>

    <aside class="pos-cart" aria-labelledby="pos-cart-h">
      <div class="pos-cart-in">
        <div class="pos-head"><h2 class="org-panel-h" id="pos-cart-h">Bon</h2><button class="ve-danger" type="button" id="pos-clear" hidden><?= v2_ic('trash') ?><span>Golește</span></button></div>
        <div class="pos-lines" id="pos-lines"><p class="ve-sub">Alege produsele din stânga.</p></div>
        <dl class="pos-totals" id="pos-totals" hidden>
          <div><dt>Subtotal</dt><dd id="pos-sub">0 lei</dd></div>
          <div id="pos-fee-row" hidden><dt>Comision bilete.online (<span id="pos-fee-rate"></span>%)</dt><dd id="pos-fee">0 lei</dd></div>
          <div class="pos-total"><dt>De încasat</dt><dd id="pos-total">0 lei</dd></div>
        </dl>
        <details class="pos-customer" id="pos-customer">
          <summary>Client (opțional)</summary>
          <div class="ve-form">
            <span class="po-field is-wide"><label for="pos-c-name">Nume</label><input class="po-input" id="pos-c-name" maxlength="160" autocomplete="off"></span>
            <span class="po-field"><label for="pos-c-email">Email</label><input class="po-input" id="pos-c-email" type="email" maxlength="190" autocomplete="off"></span>
            <span class="po-field"><label for="pos-c-phone">Telefon</label><input class="po-input" id="pos-c-phone" type="tel" maxlength="40" autocomplete="off"></span>
            <label class="po-check is-wide"><input type="checkbox" id="pos-c-send"><span>Trimite biletele pe email</span></label>
          </div>
        </details>
        <p class="pos-err" id="pos-err" role="alert" hidden></p>
        <div class="pos-pay">
          <button class="btn btn-primary" type="button" id="pos-cash" disabled><?= v2_ic('coins') ?><span>Numerar</span></button>
          <button class="btn btn-primary" type="button" id="pos-card" disabled><?= v2_ic('credit-card') ?><span>Card</span></button>
        </div>
      </div>
    </aside>
  </div>

  <section class="org-panel pos-sales" id="pos-sales-box" aria-labelledby="pos-sales-h" hidden>
    <div class="org-panel-head"><div><h2 class="org-panel-h" id="pos-sales-h">Vânzările de azi</h2><p class="org-panel-p" id="pos-sales-p"></p></div></div>
    <div class="ve-table-wrap"><table class="ve-table am-table">
      <thead><tr><th scope="col">Ora</th><th scope="col">Bon</th><th scope="col">Produse</th><th scope="col">Plata</th><th scope="col">Total</th></tr></thead>
      <tbody id="pos-sales"></tbody>
    </table></div>
  </section>

  <!-- start time picker -->
  <div class="ve-modal" id="pos-time" role="dialog" aria-modal="true" aria-labelledby="pos-time-h" hidden>
    <div class="ve-modal-card">
      <div class="ve-modal-head ve-modal-plain"><h2 id="pos-time-h">Alege ora</h2></div>
      <div class="ve-modal-body"><div class="pos-chips" id="pos-time-chips"></div></div>
      <div class="ve-modal-foot"><button class="btn btn-ghost" type="button" data-close>Renunță</button></div>
    </div>
  </div>

  <!-- closing the cash desk -->
  <div class="ve-modal" id="pos-close" role="dialog" aria-modal="true" aria-labelledby="pos-close-h" hidden>
    <div class="ve-modal-card">
      <div class="ve-modal-head ve-modal-plain"><h2 id="pos-close-h">Închide casa</h2></div>
      <div class="ve-modal-body">
        <dl class="pos-totals" id="pos-close-sum"></dl>
        <div class="ve-form">
          <span class="po-field"><label for="pos-counted">Numerar numărat în sertar (lei)</label><input class="po-input" id="pos-counted" type="number" min="0" step="0.01" inputmode="decimal"></span>
          <span class="po-field is-wide"><label for="pos-notes">Observații</label><textarea class="ve-ta" id="pos-notes" rows="2" maxlength="1000"></textarea></span>
        </div>
      </div>
      <div class="ve-modal-foot"><button class="btn btn-ghost" type="button" data-close>Renunță</button><button class="btn btn-primary" type="button" id="pos-close-go"><?= v2_ic('check') ?>Închide casa</button></div>
    </div>
  </div>

  <!-- the sale is done: tickets -->
  <div class="ve-modal" id="pos-done" role="dialog" aria-modal="true" aria-labelledby="pos-done-h" hidden>
    <div class="ve-modal-card ve-modal-wide">
      <div class="ve-modal-head ve-modal-plain"><h2 id="pos-done-h">Vânzare înregistrată</h2></div>
      <div class="ve-modal-body">
        <p class="pos-done-sum" id="pos-done-sum"></p>
        <ul class="pos-tickets" id="pos-done-tickets"></ul>
      </div>
      <div class="ve-modal-foot">
        <button class="btn btn-ghost" type="button" id="pos-print-thermal" hidden><?= v2_ic('printer') ?>Imprimanta termică</button>
        <button class="btn btn-ghost" type="button" id="pos-print"><?= v2_ic('printer') ?>Tipărește biletele</button>
        <button class="btn btn-primary" type="button" data-close><?= v2_ic('plus') ?>Vânzare nouă</button>
      </div>
    </div>
  </div>
</div>
<div class="pos-print" id="pos-print" aria-hidden="true"></div>
<?php
v2_org_end();
include __DIR__ . '/../includes/v2/foot.php';
