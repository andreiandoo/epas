<?php
/**
 * Venue settings: /organizator/locatie/setari (venue-settings.php), v2 design.
 *
 * First of the settings screens of the "Locație" section, ported from Ambilet's leisure.php (split in three there:
 * this one, the ticket and service editor, and the public page editor). It holds the ticket types with the company
 * that issues each, the sales per issuing company over a period, the two issuing companies themselves (fiscal data,
 * bank account, invoice numbering, VAT; the second one can be switched on) and the access gates of the venue.
 *
 * Ambilet's gate form sent name/code/description while core requires a type and takes a location, so adding a gate
 * never worked there; this form follows core.
 *
 * org-venue-settings.js reads /organizer/events, then .../leisure/config, .../leisure/reports/by-issuer,
 * .../leisure/issuers (and saves it) and /organizer/venues/{venue}/gates (and changes them).
 */

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/api.php';
require_once __DIR__ . '/../includes/nav-helpers.php';
require_once __DIR__ . '/../includes/v2/helpers.php';
require_once __DIR__ . '/../includes/v2/organizer.php';

$pageTitleRaw = v2_t('Venue settings: {site}', ['site' => SITE_NAME]);
$pageDescription = v2_t('The companies that issue the venue\'s tickets, the sales of each and the access gates.');
$canonicalUrl = SITE_URL . '/organizator/locatie/setari';
$noindex = true;
$skipPageCache = true;

$v2Styles = ['organizer.css', 'org-venue.css'];
$v2Scripts = ['organizer.js', 'org-venue-settings.js'];
$v2LegacyScripts = ['assets/js/config.js', 'assets/js/utils.js', 'assets/js/api.js', 'assets/js/auth.js'];
$v2HeadExtra = v2_account_client_config('organizer');

/** One issuing company's form. Field names follow the core contract. */
$vxForm = function (string $key) {
    $f = function (string $field, string $label, string $attrs = '', string $cls = '', string $inputCls = '') use ($key) {
        $id = 'vx-' . $key . '-' . $field;
        return '<span class="po-field' . ($cls ? ' ' . $cls : '') . '"><label for="' . $id . '">' . $label . '</label>'
            . '<input class="po-input' . ($inputCls ? ' ' . $inputCls : '') . '" id="' . $id . '" data-f="' . $field . '" ' . $attrs . '></span>';
    };
    return '<div class="ve-form" data-form="' . $key . '">'
        . $f('name', $key === 'primary' ? v2_te('Company name *') : v2_te('Company name'), 'maxlength="255" autocomplete="organization"', 'is-wide')
        . $f('tax_id', $key === 'primary' ? v2_te('Tax ID *') : v2_te('Tax ID'), 'maxlength="32" autocomplete="off"')
        . $f('registration', v2_te('Company registration number'), 'maxlength="32" autocomplete="off"')
        . $f('address', v2_te('Address'), 'maxlength="1024" autocomplete="street-address"', 'is-wide')
        . $f('city', v2_te('Town or city'), 'maxlength="100"')
        . $f('county', v2_te('County or region'), 'maxlength="100"')
        . $f('zip', v2_te('Postcode'), 'maxlength="20" inputmode="numeric"')
        . '<p class="ve-form-k is-wide">' . v2_te('Bank account') . '</p>'
        . $f('bank_name', v2_te('Bank'), 'maxlength="255"')
        . $f('iban', v2_te('IBAN'), 'maxlength="34" autocomplete="off"', '', 've-mono ve-upper')
        . '<p class="ve-form-k is-wide">' . v2_te('Invoice numbering') . '</p>'
        . $f('invoice_series', v2_te('Invoice series'), 'maxlength="16" autocomplete="off"', '', 've-mono ve-upper')
        . '<span class="po-field"><label for="vx-' . $key . '-next_invoice_number">' . v2_te('Next invoice number') . '</label>'
        . '<input class="po-input ve-mono" id="vx-' . $key . '-next_invoice_number" data-f="next_invoice_number" type="number" min="1" max="9999999" inputmode="numeric">'
        . '<span class="ve-hint" data-next-hint>' . v2_te('Empty = the numbering continues.') . '</span></span>'
        . '<p class="ve-form-k is-wide">' . v2_te('VAT') . '</p>'
        . '<label class="po-check is-wide"><input type="checkbox" data-f="vat_payer" id="vx-' . $key . '-vat_payer"><span>' . v2_te('The company is registered for VAT') . '</span></label>'
        . '<span class="po-field" data-vat-rate><label for="vx-' . $key . '-vat_rate">' . v2_te('VAT rate (%)') . '</label>'
        . '<input class="po-input ve-mono" id="vx-' . $key . '-vat_rate" data-f="vat_rate" type="number" step="0.01" min="0" max="100" inputmode="decimal" placeholder="19"></span>'
        . '</div>';
};

include __DIR__ . '/../includes/v2/head.php';
v2_org_start('venue-settings');
?>
<div class="ve" id="ve">
  <header class="ve-head">
    <div>
      <p class="ve-eyebrow"><?= v2_ic('gear-six') ?><?= v2_te('Venue · Settings') ?></p>
      <h1 class="ve-h"><?= v2_te('Venue settings') ?></h1>
      <p class="ve-lead"><?= v2_te('Who issues the tickets and invoices, how much each company sold and the gates people come in through.') ?></p>
    </div>
    <div class="ve-head-tools">
      <span class="po-field"><label for="ve-event"><?= v2_te('Venue') ?></label><span class="po-select"><select id="ve-event" disabled><option><?= v2_te('Loading…') ?></option></select><?= v2_ic('caret-down') ?></span></span>
    </div>
  </header>

  <div class="org-empty" id="ve-none" hidden>
    <span class="org-empty-ic"><?= v2_ic('door-open') ?></span>
    <b><?= v2_te('No venue set up yet') ?></b>
    <p><?= v2_te('Venue pages work on an experience set up as a venue.') ?></p>
    <a class="btn btn-primary" href="/organizator/suport"><?= v2_te('Ask for activation') ?><?= v2_ic('arrow-right') ?></a>
  </div>

  <div class="org-empty is-error" id="ve-failed" hidden>
    <span class="org-empty-ic"><?= v2_ic('warning-circle') ?></span>
    <b><?= v2_te('We could not load the settings') ?></b>
    <p><?= v2_te('Try again in a few seconds.') ?></p>
    <button class="btn btn-primary" type="button" id="ve-retry"><?= v2_te('Try again') ?></button>
  </div>

  <div class="ve-main" id="ve-main" hidden>
    <section class="org-panel" aria-labelledby="vx-types-h">
      <div class="org-panel-head"><div><h2 class="org-panel-h" id="vx-types-h"><?= v2_te('Ticket types and their issuer') ?></h2>
        <p class="org-panel-p"><?= v2_te('Each ticket is issued by the main company or by the second one.') ?></p></div></div>
      <div class="ve-table-wrap"><table class="ve-table ve-types-table">
        <thead><tr><th scope="col"><?= v2_te('Ticket') ?></th><th scope="col"><?= v2_te('Category') ?></th><th scope="col" class="ve-r"><?= v2_te('Places per day') ?></th><th scope="col"><?= v2_te('Issuer') ?></th><th scope="col"><?= v2_te('State') ?></th></tr></thead>
        <tbody id="vx-types"><tr><td colspan="5" class="ve-state"><?= v2_te('Loading…') ?></td></tr></tbody>
      </table></div>
    </section>

    <section class="org-panel" aria-labelledby="vx-rep-h">
      <div class="org-panel-head">
        <div><h2 class="org-panel-h" id="vx-rep-h"><?= v2_te('Sales by company') ?></h2><p class="org-panel-p" id="vx-rep-period">—</p></div>
        <span class="ve-dates">
          <span class="po-field"><label class="ve-sr" for="vx-from"><?= v2_te('From') ?></label><input class="po-input" type="date" id="vx-from"></span>
          <span class="po-field"><label class="ve-sr" for="vx-to"><?= v2_te('To') ?></label><input class="po-input" type="date" id="vx-to"></span>
          <button class="btn btn-ghost" type="button" id="vx-apply"><?= v2_te('Apply') ?></button>
        </span>
      </div>
      <div class="ve-issuers" id="vx-rep"><p class="ve-state"><?= v2_te('Loading…') ?></p></div>
    </section>

    <section class="org-panel" aria-labelledby="vx-iss-h">
      <div class="org-panel-head"><div><h2 class="org-panel-h" id="vx-iss-h"><?= v2_te('Issuing companies') ?></h2>
        <p class="org-panel-p"><?= v2_te('The tax details, bank account, invoice numbering and VAT of each company.') ?></p></div></div>
      <p class="ve-warn"><?= v2_ic('warning-circle') ?><span><?= v2_t('The main company is the company of your account: the name, tax ID and IBAN here are the same as in <a href="{url}">Account &amp; company</a>. An IBAN changed here changes the account your payouts are paid into.', ['url' => '/organizator/setari']) ?></span></p>
      <div class="ve-iss-forms" id="vx-iss" hidden>
        <article class="ve-issuer" data-company="primary">
          <div class="ve-iss-head">
            <p class="ve-issuer-k"><?= v2_ic('buildings') ?><?= v2_te('Main company') ?></p>
            <button class="btn btn-primary" type="button" data-save="primary"><?= v2_ic('check') ?><?= v2_te('Save') ?></button>
          </div>
          <?= $vxForm('primary') ?>
        </article>
        <article class="ve-issuer is-second" data-company="secondary">
          <div class="ve-iss-head">
            <p class="ve-issuer-k"><?= v2_ic('buildings') ?><?= v2_te('Second company') ?></p>
            <label class="po-check"><input type="checkbox" id="vx-sec-on"><span><?= v2_te('I use a second company') ?></span></label>
            <button class="btn btn-primary" type="button" data-save="secondary"><?= v2_ic('check') ?><?= v2_te('Save') ?></button>
          </div>
          <?= $vxForm('secondary') ?>
        </article>
      </div>
      <p class="ve-state" id="vx-iss-state"><?= v2_te('Loading…') ?></p>
    </section>

    <section class="org-panel" aria-labelledby="vx-gates-h">
      <div class="org-panel-head">
        <div><h2 class="org-panel-h" id="vx-gates-h"><?= v2_te('Access gates') ?></h2><p class="org-panel-p" id="vx-venue-line"><?= v2_te('The points where tickets are scanned on the way in.') ?></p></div>
        <button class="btn btn-primary" type="button" id="vx-gate-add"><?= v2_ic('plus') ?><?= v2_te('Add a gate') ?></button>
      </div>
      <ul class="ve-gates" id="vx-gates"><li class="ve-state"><?= v2_te('Loading…') ?></li></ul>
    </section>
  </div>

  <div class="ve-modal" id="vx-modal" role="dialog" aria-modal="true" aria-labelledby="vx-modal-h" hidden>
    <div class="ve-modal-card">
      <div class="ve-modal-head ve-modal-plain"><h2 id="vx-modal-h"><?= v2_te('Add a gate') ?></h2></div>
      <div class="ve-modal-body">
        <span class="po-field"><label for="vx-g-name"><?= v2_te('Gate name *') ?></label><input class="po-input" id="vx-g-name" maxlength="255" placeholder="<?= v2_te('Gate A, Main entrance…') ?>" autocomplete="off"></span>
        <div class="ve-two">
          <span class="po-field"><label for="vx-g-type"><?= v2_te('Type') ?></label><span class="po-select"><select id="vx-g-type">
            <option value="entry"><?= v2_te('Entry') ?></option><option value="exit"><?= v2_te('Exit') ?></option><option value="vip"><?= v2_te('VIP') ?></option><option value="pos"><?= v2_te('Register / point of sale') ?></option>
          </select><?= v2_ic('caret-down') ?></span></span>
          <span class="po-field"><label for="vx-g-loc"><?= v2_te('Where it is') ?></label><input class="po-input" id="vx-g-loc" maxlength="255" placeholder="<?= v2_te('North car park, next to the register…') ?>" autocomplete="off"></span>
        </div>
        <label class="po-check" id="vx-g-active-wrap" hidden><input type="checkbox" id="vx-g-active"><span><?= v2_te('The gate is in use') ?></span></label>
      </div>
      <div class="ve-modal-foot">
        <button class="ve-danger" type="button" id="vx-g-del" hidden><?= v2_ic('trash') ?><span><?= v2_te('Delete the gate') ?></span></button>
        <button class="btn btn-ghost" type="button" id="vx-g-cancel"><?= v2_te('Cancel') ?></button>
        <button class="btn btn-primary" type="button" id="vx-g-save"><?= v2_ic('check') ?><?= v2_te('Save') ?></button>
      </div>
    </div>
  </div>
</div>
<?php
v2_org_end();
include __DIR__ . '/../includes/v2/foot.php';
