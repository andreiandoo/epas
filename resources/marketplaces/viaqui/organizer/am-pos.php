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

$pageTitleRaw = v2_t('Counter & POS: {site}', ['site' => SITE_NAME]);
$pageDescription = v2_t('Selling at the counter of the venue: access tickets, experiences and packages, payment in cash or by card, tickets printed on the spot.');
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
echo am_product_icon_sprite();
?>
<div class="ve am pos" id="am-pos">
  <header class="ve-head">
    <div>
      <p class="ve-eyebrow"><?= v2_ic('scan') ?><?= v2_te('Counter & POS') ?></p>
      <h1 class="ve-h"><?= v2_te('Counter sale') ?></h1>
    </div>
    <div class="am-filters">
      <span class="po-field"><label for="pos-loc"><?= v2_te('Venue') ?></label><span class="po-select"><select id="pos-loc"><option value=""><?= v2_te('Loading…') ?></option></select><?= v2_ic('caret-down') ?></span></span>
      <button class="btn btn-ghost" type="button" id="pos-printer" hidden><?= v2_ic('printer') ?><span><?= v2_te('Connect the printer') ?></span></button>
    </div>
  </header>

  <div class="org-empty" id="pos-none" hidden>
    <span class="org-empty-ic"><?= v2_ic('map-pin') ?></span>
    <b><?= v2_te('No venues yet') ?></b>
    <p><?= v2_te('The counter sells the products of a venue. Add it first, with its products.') ?></p>
    <a class="btn btn-primary" href="/organizator/locatii?nou=1"><?= v2_ic('plus') ?><?= v2_te('Add the venue') ?></a>
  </div>

  <section class="pos-session" id="pos-session" aria-live="polite" hidden></section>

  <div class="pos-grid" id="pos-main" hidden>
    <section class="pos-products" aria-labelledby="pos-prod-h">
      <div class="pos-head">
        <h2 class="org-panel-h" id="pos-prod-h"><?= v2_te('Products') ?></h2>
        <p class="pos-hours" id="pos-hours"></p>
      </div>
      <div class="pos-cats" id="pos-cats" role="group" aria-label="<?= v2_te('Categories') ?>" hidden></div>
      <div class="pos-list" id="pos-list"><p class="ve-state"><?= v2_te('Loading…') ?></p></div>
    </section>

    <aside class="pos-cart" aria-labelledby="pos-cart-h">
      <div class="pos-cart-in">
        <div class="pos-head"><h2 class="org-panel-h" id="pos-cart-h"><?= v2_te('Receipt') ?></h2><button class="ve-danger" type="button" id="pos-clear" hidden><?= v2_ic('trash') ?><span><?= v2_te('Clear') ?></span></button></div>
        <div class="pos-lines" id="pos-lines"><p class="ve-sub"><?= v2_te('Choose the products on the left.') ?></p></div>
        <dl class="pos-totals" id="pos-totals" hidden>
          <div><dt><?= v2_te('Subtotal') ?></dt><dd id="pos-sub"><?= v2_e(v2_money(0)) ?></dd></div>
          <div id="pos-fee-row" hidden><dt><?= v2_te('Ticketing cost') ?></dt><dd id="pos-fee"><?= v2_e(v2_money(0)) ?></dd></div>
          <div class="pos-total"><dt><?= v2_te('To collect') ?></dt><dd id="pos-total"><?= v2_e(v2_money(0)) ?></dd></div>
        </dl>
        <details class="pos-customer" id="pos-customer">
          <summary><?= v2_te('Customer (optional)') ?></summary>
          <div class="ve-form">
            <span class="po-field is-wide"><label for="pos-c-name"><?= v2_te('Name') ?></label><input class="po-input" id="pos-c-name" maxlength="160" autocomplete="off"></span>
            <span class="po-field"><label for="pos-c-email"><?= v2_te('Email') ?></label><input class="po-input" id="pos-c-email" type="email" maxlength="190" autocomplete="off"></span>
            <span class="po-field"><label for="pos-c-phone"><?= v2_te('Phone') ?></label><input class="po-input" id="pos-c-phone" type="tel" maxlength="40" autocomplete="off"></span>
            <span class="po-field is-wide"><label for="pos-c-notes"><?= v2_te('Notes') ?></label><textarea class="po-input" id="pos-c-notes" rows="2" maxlength="500"></textarea></span>
            <label class="po-check is-wide"><input type="checkbox" id="pos-c-send"><span><?= v2_te('Send the tickets by email') ?></span></label>
          </div>
        </details>

        <details class="pos-customer" id="pos-company">
          <summary><?= v2_te('Invoice to a company (optional)') ?></summary>
          <div class="ve-form">
            <div class="pos-cui is-wide">
              <span class="po-field"><label for="pos-co-cui"><?= v2_te('Tax ID (CUI / CIF)') ?></label><input class="po-input" id="pos-co-cui" maxlength="30" placeholder="RO12345678" autocomplete="off"></span>
              <button class="btn btn-ghost" type="button" id="pos-anaf"><?= v2_ic('magnifying-glass') ?><span data-label><?= v2_te('Look up at ANAF') ?></span></button>
            </div>
            <p class="pos-hint is-wide" id="pos-anaf-msg" role="status" hidden></p>
            <span class="po-field is-wide"><label for="pos-co-name"><?= v2_te('Company name') ?></label><input class="po-input" id="pos-co-name" maxlength="200" autocomplete="off"></span>
            <span class="po-field"><label for="pos-co-reg"><?= v2_te('Trade register number') ?></label><input class="po-input" id="pos-co-reg" maxlength="60" autocomplete="off"></span>
            <span class="po-field"><label for="pos-co-iban"><?= v2_te('IBAN') ?></label><input class="po-input" id="pos-co-iban" maxlength="34" autocomplete="off"></span>
            <span class="po-field is-wide"><label for="pos-co-address"><?= v2_te('Registered office') ?></label><input class="po-input" id="pos-co-address" maxlength="255" autocomplete="off"></span>
            <span class="po-field is-wide"><label for="pos-co-contact"><?= v2_te('Contact person') ?></label><input class="po-input" id="pos-co-contact" maxlength="120" autocomplete="off"></span>
            <label class="po-check is-wide"><input type="checkbox" id="pos-co-invoice"><span><?= v2_te('Issue an invoice for this sale') ?></span></label>
            <p class="pos-hint is-wide"><?= v2_te('The invoice number is taken when the sale is completed, so tick this now: later it can no longer be issued for this receipt.') ?></p>
          </div>
        </details>
        <p class="pos-err" id="pos-err" role="alert" hidden></p>
        <div class="pos-pay">
          <button class="btn btn-primary" type="button" id="pos-cash" disabled><?= v2_ic('coins') ?><span><?= v2_te('Cash') ?></span></button>
          <button class="btn btn-primary" type="button" id="pos-card" disabled><?= v2_ic('credit-card') ?><span><?= v2_te('Card') ?></span></button>
        </div>
      </div>
    </aside>
  </div>

  <section class="org-panel pos-sales" id="pos-sales-box" aria-labelledby="pos-sales-h" hidden>
    <div class="org-panel-head"><div><h2 class="org-panel-h" id="pos-sales-h"><?= v2_te('Sales today') ?></h2><p class="org-panel-p" id="pos-sales-p"></p></div></div>
    <div class="ve-table-wrap"><table class="ve-table am-table">
      <thead><tr><th scope="col"><?= v2_te('Time') ?></th><th scope="col"><?= v2_te('Receipt') ?></th><th scope="col"><?= v2_te('Products') ?></th><th scope="col"><?= v2_te('Payment') ?></th><th scope="col"><?= v2_te('Total') ?></th></tr></thead>
      <tbody id="pos-sales"></tbody>
    </table></div>
  </section>

  <!-- start time picker -->
  <div class="ve-modal" id="pos-time" role="dialog" aria-modal="true" aria-labelledby="pos-time-h" hidden>
    <div class="ve-modal-card">
      <div class="ve-modal-head ve-modal-plain"><h2 id="pos-time-h"><?= v2_te('Choose the time') ?></h2></div>
      <div class="ve-modal-body"><div class="pos-chips" id="pos-time-chips"></div></div>
      <div class="ve-modal-foot"><button class="btn btn-ghost" type="button" data-close><?= v2_te('Cancel') ?></button></div>
    </div>
  </div>

  <!-- closing the cash desk -->
  <div class="ve-modal" id="pos-close" role="dialog" aria-modal="true" aria-labelledby="pos-close-h" hidden>
    <div class="ve-modal-card">
      <div class="ve-modal-head ve-modal-plain"><h2 id="pos-close-h"><?= v2_te('Close the counter') ?></h2></div>
      <div class="ve-modal-body">
        <dl class="pos-totals" id="pos-close-sum"></dl>
        <div class="ve-form">
          <span class="po-field"><label for="pos-counted"><?= v2_te('Cash counted in the drawer (€)') ?></label><input class="po-input" id="pos-counted" type="number" min="0" step="0.01" inputmode="decimal"></span>
          <span class="po-field is-wide"><label for="pos-notes"><?= v2_te('Remarks') ?></label><textarea class="ve-ta" id="pos-notes" rows="2" maxlength="1000"></textarea></span>
        </div>
      </div>
      <div class="ve-modal-foot"><button class="btn btn-ghost" type="button" data-close><?= v2_te('Cancel') ?></button><button class="btn btn-primary" type="button" id="pos-close-go"><?= v2_ic('check') ?><?= v2_te('Close the counter') ?></button></div>
    </div>
  </div>

  <!-- the sale is done: tickets -->
  <div class="ve-modal" id="pos-done" role="dialog" aria-modal="true" aria-labelledby="pos-done-h" hidden>
    <div class="ve-modal-card ve-modal-wide">
      <div class="ve-modal-head ve-modal-plain"><h2 id="pos-done-h"><?= v2_te('Sale recorded') ?></h2></div>
      <div class="ve-modal-body">
        <p class="pos-done-sum" id="pos-done-sum"></p>
        <ul class="pos-tickets" id="pos-done-tickets"></ul>
      </div>
      <div class="ve-modal-foot">
        <button class="btn btn-ghost" type="button" id="pos-print-thermal" hidden><?= v2_ic('printer') ?><?= v2_te('Thermal printer') ?></button>
        <button class="btn btn-ghost" type="button" id="pos-print"><?= v2_ic('printer') ?><?= v2_te('Print the tickets') ?></button>
        <button class="btn btn-primary" type="button" data-close><?= v2_ic('plus') ?><?= v2_te('New sale') ?></button>
      </div>
    </div>
  </div>
</div>
<div class="pos-print" id="pos-print" aria-hidden="true"></div>
<?php
v2_org_end();
include __DIR__ . '/../includes/v2/foot.php';
