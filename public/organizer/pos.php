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

$pageTitleRaw = v2_t('Register and point of sale: {site}', ['site' => SITE_NAME]);
$pageDescription = v2_t('On-site sales for a venue on viaqui.com: the register, tickets issued on the spot, receipts on a thermal printer.');
$canonicalUrl = SITE_URL . '/organizator/pos';
$noindex = true;
$skipPageCache = true;

$v2Styles = ['organizer.css', 'org-pos.css'];
$v2Scripts = ['organizer.js', 'pos-printer.js', 'org-pos.js'];
$v2LegacyScripts = ['assets/js/config.js', 'assets/js/utils.js', 'assets/js/api.js', 'assets/js/auth.js'];
$v2HeadExtra = v2_account_client_config('organizer');

$poStat = function (string $key, string $label, string $tone = '') {
    return '<div class="po-x' . ($tone ? ' ' . $tone : '') . '"><p>' . $label . '</p><b id="po-x-' . $key . '">—</b></div>';
};

include __DIR__ . '/../includes/v2/head.php';
v2_org_start('pos');
?>
<div class="po" id="po">
  <header class="po-head">
    <div>
      <p class="po-eyebrow"><?= v2_ic('scan') ?><?= v2_te('On-site sales') ?></p>
      <h1 class="po-h"><?= v2_te('Register & point of sale') ?></h1>
      <p class="po-lead"><?= v2_te('Issue tickets on the spot, take cash or card and print the receipt. These sales go into the same reports as the online ones.') ?></p>
    </div>
    <div class="po-head-tools">
      <span class="po-field"><label for="po-event"><?= v2_te('Venue') ?></label><span class="po-select"><select id="po-event" disabled><option><?= v2_te('Loading…') ?></option></select><?= v2_ic('caret-down') ?></span></span>
      <span class="po-field"><label for="po-date"><?= v2_te('Visit date') ?></label><input class="po-input" type="date" id="po-date"></span>
    </div>
  </header>

  <div class="org-empty" id="po-none" hidden>
    <span class="org-empty-ic"><?= v2_ic('door-open') ?></span>
    <b><?= v2_te('No venue set up for on-site sales yet') ?></b>
    <p><?= v2_te('The point of sale works on an experience set up as a venue, with products marked for sale at the register. Write to us and we will set yours up.') ?></p>
    <a class="btn btn-primary" href="/organizator/suport"><?= v2_te('Ask for activation') ?><?= v2_ic('arrow-right') ?></a>
  </div>

  <div class="org-empty is-error" id="po-failed" hidden>
    <span class="org-empty-ic"><?= v2_ic('warning-circle') ?></span>
    <b><?= v2_te('We could not load the point of sale') ?></b>
    <p id="po-failed-t"><?= v2_te('Try again in a few seconds.') ?></p>
    <button class="btn btn-primary" type="button" id="po-retry"><?= v2_te('Try again') ?></button>
  </div>

  <div class="po-main" id="po-main" hidden>
    <!-- ===== register bar ===== -->
    <section class="po-cash" id="po-cash" aria-labelledby="po-cash-h">
      <h2 class="po-sr" id="po-cash-h"><?= v2_te('Register') ?></h2>
      <div class="po-cash-row">
        <p class="po-cash-state" id="po-cash-state"><span class="po-dot" id="po-dot"></span><b id="po-cash-label"><?= v2_te('Checking…') ?></b><small id="po-cash-since"></small></p>
        <div class="po-cash-btns">
          <button class="btn btn-primary" type="button" id="po-open" hidden><?= v2_ic('door-open') ?><?= v2_te('Open the register') ?></button>
          <button class="btn btn-ghost" type="button" id="po-xtoggle" hidden aria-expanded="false" aria-controls="po-x"><?= v2_ic('chart-line-up') ?><?= v2_te('X report') ?></button>
          <button class="btn btn-ghost" type="button" id="po-sessions-btn"><?= v2_ic('clock') ?><?= v2_te('Session log') ?></button>
          <button class="btn btn-ghost" type="button" id="po-close" hidden><?= v2_ic('lock-simple') ?><?= v2_te('Close the register') ?></button>
        </div>
      </div>
      <div class="po-xrep" id="po-x" hidden>
        <?= $poStat('cash', v2_te('Cash to hand over'), 'is-warm') ?>
        <?= $poStat('card', v2_te('Card payments'), 'is-info') ?>
        <?= $poStat('total', v2_te('Total in the register'), 'is-mint') ?>
        <?= $poStat('orders', v2_te('Orders this session')) ?>
        <p class="po-xrep-note" id="po-x-note"><?= v2_te('On-site sales only. Online sales do not go through the register.') ?></p>
      </div>
    </section>

    <div class="po-locked" id="po-locked" hidden>
      <span><?= v2_ic('lock-simple') ?></span>
      <div><b><?= v2_te('The register is closed') ?></b><p><?= v2_te('Open the register to start selling. The opening and closing times are kept in the session log.') ?></p></div>
    </div>

    <div class="po-grid">
      <!-- ===== products ===== -->
      <section class="org-panel po-products" aria-labelledby="po-prod-h">
        <div class="org-panel-head">
          <div><h2 class="org-panel-h" id="po-prod-h"><?= v2_te('Tickets and services') ?></h2><p class="org-panel-p" id="po-prod-sub"><?= v2_te('Press a product to add it to the basket.') ?></p></div>
          <label class="po-search"><?= v2_ic('magnifying-glass') ?><input id="po-q" type="search" placeholder="<?= v2_te('Search products') ?>" autocomplete="off" aria-label="<?= v2_te('Search products') ?>"></label>
        </div>
        <div class="po-skel" id="po-prod-skel"><span class="org-skel"></span><span class="org-skel"></span><span class="org-skel"></span><span class="org-skel"></span></div>
        <div class="po-cats" id="po-cats"></div>
        <p class="po-empty" id="po-prod-empty" hidden><?= v2_te('No products marked for sale at the register. Tick "Register only" or set a register price on the venue\'s products.') ?></p>
      </section>

      <!-- ===== cart ===== -->
      <section class="org-panel po-cart" aria-labelledby="po-cart-h">
        <div class="po-cart-head">
          <h2 class="org-panel-h" id="po-cart-h"><?= v2_te('Basket') ?></h2>
          <button class="po-clear" type="button" id="po-clear" hidden><?= v2_ic('trash') ?><?= v2_te('Empty') ?></button>
        </div>
        <ul class="po-lines" id="po-lines"><li class="po-lines-empty"><?= v2_te('The basket is empty. Press a product to add it.') ?></li></ul>
        <p class="po-access" id="po-access" hidden></p>

        <details class="po-fold">
          <summary><?= v2_ic('user-plus') ?><?= v2_te('Customer details (optional)') ?></summary>
          <div class="po-fold-in">
            <span class="po-field"><label for="po-c-name"><?= v2_te('Name') ?></label><input class="po-input" type="text" id="po-c-name" maxlength="120" autocomplete="off"></span>
            <span class="po-field"><label for="po-c-email"><?= v2_te('Email, to send the tickets') ?></label><input class="po-input" type="email" id="po-c-email" maxlength="120" autocomplete="off"></span>
            <span class="po-field"><label for="po-c-phone"><?= v2_te('Phone') ?></label><input class="po-input" type="tel" id="po-c-phone" maxlength="30" autocomplete="off"></span>
            <span class="po-field"><label for="po-c-plate"><?= v2_te('Number plate, for parking') ?></label><input class="po-input" type="text" id="po-c-plate" maxlength="20" autocomplete="off"></span>
            <span class="po-field"><label for="po-c-notes"><?= v2_te('Notes') ?></label><textarea class="po-input" id="po-c-notes" rows="2" maxlength="500"></textarea></span>
          </div>
        </details>

        <details class="po-fold" id="po-company">
          <summary><?= v2_ic('identification-card') ?><?= v2_te('Company details, for the invoice') ?></summary>
          <div class="po-fold-in">
            <span class="po-field"><label for="po-co-name"><?= v2_te('Company name') ?></label><input class="po-input" type="text" id="po-co-name" maxlength="200" autocomplete="off"></span>
            <div class="po-cui">
              <span class="po-field"><label for="po-co-cui"><?= v2_te('Tax ID') ?></label><input class="po-input" type="text" id="po-co-cui" maxlength="30" placeholder="RO12345678" autocomplete="off"></span>
              <button class="btn btn-ghost" type="button" id="po-anaf"><?= v2_ic('magnifying-glass') ?><?= v2_te('Look up the company') ?></button>
            </div>
            <p class="po-msg" id="po-anaf-msg" role="status" hidden></p>
            <span class="po-field"><label for="po-co-reg"><?= v2_te('Company registration number') ?></label><input class="po-input" type="text" id="po-co-reg" maxlength="60" autocomplete="off"></span>
            <span class="po-field"><label for="po-co-address"><?= v2_te('Registered address') ?></label><input class="po-input" type="text" id="po-co-address" maxlength="255" autocomplete="off"></span>
            <div class="po-two">
              <span class="po-field"><label for="po-co-iban"><?= v2_te('IBAN') ?></label><input class="po-input" type="text" id="po-co-iban" maxlength="34" autocomplete="off"></span>
              <span class="po-field"><label for="po-co-contact"><?= v2_te('Contact person') ?></label><input class="po-input" type="text" id="po-co-contact" maxlength="120" autocomplete="off"></span>
            </div>
            <label class="po-check"><input type="checkbox" id="po-co-invoice"><span><?= v2_te('Generate a tax invoice after the sale') ?></span></label>
            <p class="po-hint"><?= v2_te('Without this ticked, the invoice cannot be issued later: its number is reserved only when the sale is completed.') ?></p>
          </div>
        </details>

        <div class="po-lang">
          <p class="po-k"><?= v2_te('Language of the ticket and the email') ?></p>
          <div class="po-lang-btns" role="group" aria-label="<?= v2_te('Ticket language') ?>">
            <button class="po-lang-b is-on" type="button" data-lang="ro" aria-pressed="true">RO</button>
            <button class="po-lang-b" type="button" data-lang="hu" aria-pressed="false">HU</button>
            <button class="po-lang-b" type="button" data-lang="en" aria-pressed="false">EN</button>
          </div>
        </div>

        <dl class="po-sums">
          <div><dt><?= v2_te('Subtotal') ?></dt><dd id="po-subtotal"><?= v2_e(v2_money(0)) ?></dd></div>
          <div id="po-com-row" hidden><dt><?= v2_te('Ticketing commission') ?></dt><dd id="po-commission"><?= v2_e(v2_money(0)) ?></dd></div>
          <div class="po-total"><dt><?= v2_te('Total') ?></dt><dd id="po-total"><?= v2_e(v2_money(0)) ?></dd></div>
        </dl>

        <p class="po-k"><?= v2_te('Payment method') ?></p>
        <div class="po-pay" role="group" aria-label="<?= v2_te('Payment method') ?>">
          <button class="po-pay-b is-on" type="button" data-pay="cash" aria-pressed="true"><?= v2_ic('coins') ?><?= v2_te('Cash') ?></button>
          <button class="po-pay-b" type="button" data-pay="card" aria-pressed="false"><?= v2_ic('credit-card') ?><?= v2_te('Card') ?></button>
          <button class="po-pay-b" type="button" data-pay="invoice" aria-pressed="false"><?= v2_ic('envelope-simple') ?><?= v2_te('Link by email') ?></button>
        </div>
        <p class="po-hint" id="po-pay-hint"><?= v2_te('Cash: you take the money now and the tickets are valid straight away. Card: the payment is made on your own terminal and you record it here. Link by email: the customer receives a payment link.') ?></p>
        <p class="po-msg is-error" id="po-error" role="alert" hidden></p>
        <button class="btn btn-primary po-checkout" type="button" id="po-checkout" disabled><?= v2_te('Complete the sale') ?></button>
      </section>
    </div>

    <!-- ===== printer ===== -->
    <details class="org-panel po-printer" id="po-printer">
      <summary><?= v2_ic('printer') ?><b><?= v2_te('Thermal printer') ?></b><span class="po-pr-state" id="po-pr-state"><?= v2_te('Checking…') ?></span></summary>
      <div class="po-pr-in">
        <p class="po-hint" id="po-pr-info"></p>
        <p class="po-msg" id="po-pr-paper" role="status" hidden></p>
        <p class="po-msg is-error" id="po-pr-error" role="alert" hidden></p>
        <div class="po-pr-btns">
          <button class="btn btn-ghost" type="button" id="po-pr-connect"><?= v2_ic('printer') ?><?= v2_te('Connect the printer') ?></button>
          <button class="btn btn-ghost" type="button" id="po-pr-test"><?= v2_te('Print test') ?></button>
          <button class="btn btn-ghost" type="button" id="po-pr-reprint" hidden><?= v2_ic('arrow-counter-clockwise') ?><?= v2_te('Reprint the last order') ?><small id="po-pr-reprint-meta"></small></button>
        </div>
        <label class="po-check"><input type="checkbox" id="po-pr-auto"><span><?= v2_te('Print the tickets automatically after each order') ?></span></label>
        <p class="po-hint"><?= v2_te('Works in Chrome or Edge. On Windows, if the printer is not in the list, replace its driver with WinUSB using Zadig. Without a printer, the receipt is printed through the browser\'s print dialog.') ?></p>
      </div>
    </details>

    <!-- ===== sessions ===== -->
    <section class="org-panel po-sessions" id="po-sessions" hidden aria-labelledby="po-sess-h">
      <div class="org-panel-head">
        <div><h2 class="org-panel-h" id="po-sess-h"><?= v2_te('Register session log') ?></h2><p class="org-panel-p"><?= v2_te('Today\'s sessions, with what each one took.') ?></p></div>
        <div class="po-sess-tools">
          <button class="btn btn-ghost" type="button" id="po-csv"><?= v2_ic('download-simple') ?><?= v2_te('Export CSV') ?></button>
          <button class="btn btn-ghost" type="button" id="po-sess-refresh"><?= v2_ic('arrow-counter-clockwise') ?><?= v2_te('Refresh') ?></button>
        </div>
      </div>
      <div id="po-sess-body"><p class="po-empty"><?= v2_te('Loading…') ?></p></div>
    </section>
  </div>

  <!-- the receipt printed through the browser when there is no thermal printer -->
  <div class="po-receipt" id="po-receipt" hidden aria-hidden="true"></div>

  <dialog class="po-dialog" id="po-slot-d" aria-labelledby="po-slot-h">
    <div class="po-d-in">
      <div class="po-d-head"><h2 class="po-d-h" id="po-slot-h"><?= v2_te('Choose the time') ?></h2><button class="po-x-btn" type="button" data-po-close aria-label="<?= v2_te('Close') ?>"><?= v2_ic('x') ?></button></div>
      <p class="po-hint"><b id="po-slot-t"></b><br><span id="po-slot-hint"></span></p>
      <span class="po-field"><label for="po-slot-time"><?= v2_te('Time') ?></label><input class="po-input" type="time" id="po-slot-time" step="300"></span>
      <div class="po-d-act">
        <button class="btn btn-ghost" type="button" data-po-close><?= v2_te('Cancel') ?></button>
        <button class="btn btn-primary" type="button" id="po-slot-ok"><?= v2_te('Add to basket') ?></button>
      </div>
    </div>
  </dialog>

  <dialog class="po-dialog" id="po-close-d" aria-labelledby="po-close-h">
    <div class="po-d-in">
      <div class="po-d-head"><h2 class="po-d-h" id="po-close-h"><?= v2_te('Close the register?') ?></h2><button class="po-x-btn" type="button" data-po-close aria-label="<?= v2_te('Close') ?>"><?= v2_ic('x') ?></button></div>
      <div id="po-close-body"><p class="po-hint"><?= v2_te('A snapshot of this session\'s takings is saved: how much cash you hand over and how much was paid by card.') ?></p></div>
      <div class="po-d-act">
        <button class="btn btn-ghost" type="button" data-po-close><?= v2_te('Cancel') ?></button>
        <button class="btn btn-primary" type="button" id="po-close-confirm"><?= v2_te('Close the register') ?></button>
      </div>
    </div>
  </dialog>
</div>
<?php
v2_org_end();
include __DIR__ . '/../includes/v2/foot.php';
