<?php
/**
 * Venue tickets and services: /organizator/locatie/bilete (venue-products.php), v2 design.
 *
 * Second of the settings screens of the "Locație" section, ported from the "Produse" tab of Ambilet's leisure.php.
 * Everything a venue sells: the display categories the public page groups tickets by (with a picture and Hungarian
 * and English names), and the products themselves in their order. A product carries its prices (online and at the
 * counter), stock, per-order quantities, group-ticket rules, picture, texts and translations, and, depending on what
 * it is, duration variants, add-ons, timed departures, a physical inventory, package components with the share of
 * the price each issuing company gets, the access ticket it needs and informative blocked hours.
 *
 * org-venue-products.js reads /organizer/events, then .../leisure/products and .../leisure/config; it saves products
 * (POST/PUT/DELETE .../leisure/products), their order (.../products/reorder), the categories (PUT .../venue-config)
 * and pictures (multipart .../leisure/upload-image).
 */

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/api.php';
require_once __DIR__ . '/../includes/nav-helpers.php';
require_once __DIR__ . '/../includes/v2/helpers.php';
require_once __DIR__ . '/../includes/v2/organizer.php';

$pageTitleRaw = v2_t('Venue tickets and services: {site}', ['site' => SITE_NAME]);
$pageDescription = v2_t('The venue\'s tickets and services: online and register prices, stock, variants, packages and display categories.');
$canonicalUrl = SITE_URL . '/organizator/locatie/bilete';
$noindex = true;
$skipPageCache = true;

$v2Styles = ['organizer.css', 'org-venue.css'];
$v2Scripts = ['organizer.js', 'org-venue-products.js'];
$v2LegacyScripts = ['assets/js/config.js', 'assets/js/utils.js', 'assets/js/api.js', 'assets/js/auth.js'];
$v2HeadExtra = v2_account_client_config('organizer');

$fld = function (string $id, string $label, string $input, string $hint = '', string $cls = '') {
    return '<span class="po-field' . ($cls ? ' ' . $cls : '') . '"><label for="' . $id . '">' . $label . '</label>' . $input
        . ($hint ? '<span class="ve-hint">' . $hint . '</span>' : '') . '</span>';
};
$inp = function (string $id, string $attrs = '', string $cls = '') {
    return '<input class="po-input' . ($cls ? ' ' . $cls : '') . '" id="' . $id . '" ' . $attrs . '>';
};
$sel = function (string $id, array $options) {
    $o = '';
    foreach ($options as $v => $l) $o .= '<option value="' . $v . '">' . $l . '</option>';
    return '<span class="po-select"><select id="' . $id . '">' . $o . '</select>' . v2_ic('caret-down') . '</span>';
};
$tr = function (string $lang, string $langName) {
    $f = function (string $field, string $label, bool $area = false) use ($lang) {
        $id = 'vi-tr-' . $lang . '-' . $field;
        $ctrl = $area
            ? '<textarea class="ve-ta" id="' . $id . '" data-tr="' . $field . '" data-lang="' . $lang . '" rows="2"></textarea>'
            : '<input class="po-input" id="' . $id . '" data-tr="' . $field . '" data-lang="' . $lang . '">';
        return '<span class="po-field"><label for="' . $id . '">' . $label . '</label>' . $ctrl . '</span>';
    };
    return '<div class="ve-form" data-tr-pane="' . $lang . '"' . ($lang === 'en' ? ' hidden' : '') . '>'
        . $f('name', v2_te('Name ({lang})', ['lang' => $langName]))
        . $f('unit_label', v2_te('Price unit ({lang})', ['lang' => $langName]))
        . '<span class="is-wide">' . $f('description', v2_te('Short description ({lang})', ['lang' => $langName]), true) . '</span>'
        . '<span class="is-wide">' . $f('includes', v2_te('Includes, one per line ({lang})', ['lang' => $langName]), true) . '</span>'
        . '<span class="is-wide">' . $f('usage_terms', v2_te('Terms of use ({lang})', ['lang' => $langName]), true) . '</span>'
        . '</div>';
};

include __DIR__ . '/../includes/v2/head.php';
v2_org_start('venue-products');
?>
<div class="ve" id="ve">
  <header class="ve-head">
    <div>
      <p class="ve-eyebrow"><?= v2_ic('ticket') ?><?= v2_te('Venue · Tickets and services') ?></p>
      <h1 class="ve-h"><?= v2_te('Tickets and services') ?></h1>
      <p class="ve-lead"><?= v2_te('Everything the venue sells, online and at the register: prices, stock, variants, packages and how they look on the public page.') ?></p>
    </div>
    <div class="ve-head-tools">
      <span class="po-field"><label for="ve-event"><?= v2_te('Venue') ?></label><span class="po-select"><select id="ve-event" disabled><option><?= v2_te('Loading…') ?></option></select><?= v2_ic('caret-down') ?></span></span>
      <button class="btn btn-primary" type="button" id="vi-add"><?= v2_ic('plus') ?><?= v2_te('Add a product') ?></button>
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
    <b><?= v2_te('We could not load the products') ?></b>
    <p><?= v2_te('Try again in a few seconds.') ?></p>
    <button class="btn btn-primary" type="button" id="ve-retry"><?= v2_te('Try again') ?></button>
  </div>

  <div class="ve-main" id="ve-main" hidden>
    <section class="org-panel ve-hist" aria-labelledby="vi-cats-h">
      <div class="org-panel-head">
        <div><h2 class="org-panel-h" id="vi-cats-h"><?= v2_te('Display categories') ?> <span class="ve-sub" id="vi-cats-n"></span></h2>
        <p class="org-panel-p"><?= v2_te('They group the tickets on the public page, for example "Single tickets" or "Family packages". Products without a category are listed under "Other products".') ?></p></div>
        <button class="btn btn-ghost ve-hist-btn" type="button" id="vi-cats-btn" aria-expanded="false" aria-controls="vi-cats-body"><?= v2_te('Show categories') ?><?= v2_ic('caret-down') ?></button>
      </div>
      <div id="vi-cats-body" hidden>
        <ul class="ve-cats" id="vi-cats"></ul>
        <div class="ve-cat-add">
          <span class="po-field"><label for="vi-cat-new"><?= v2_te('New category') ?></label><input class="po-input" id="vi-cat-new" maxlength="80" placeholder="<?= v2_te('Single tickets') ?>" autocomplete="off"></span>
          <button class="btn btn-ghost" type="button" id="vi-cat-add"><?= v2_ic('plus') ?><?= v2_te('Add it') ?></button>
          <button class="btn btn-primary" type="button" id="vi-cats-save"><?= v2_ic('check') ?><?= v2_te('Save categories') ?></button>
        </div>
      </div>
    </section>

    <section class="org-panel" aria-labelledby="vi-list-h">
      <div class="org-panel-head"><div><h2 class="org-panel-h" id="vi-list-h"><?= v2_te('Products') ?></h2>
        <p class="org-panel-p"><?= v2_te('In the order they are shown. The arrows move a product within its category.') ?></p></div></div>
      <div class="ve-prods" id="vi-list"><p class="ve-state"><?= v2_te('Loading…') ?></p></div>
    </section>
  </div>

  <div class="ve-modal" id="vi-modal" role="dialog" aria-modal="true" aria-labelledby="vi-modal-h" hidden>
    <div class="ve-modal-card ve-modal-xl">
      <div class="ve-modal-head ve-modal-plain"><h2 id="vi-modal-h"><?= v2_te('Add a product') ?></h2></div>
      <div class="ve-modal-body">
        <div class="ve-form">
          <?= $fld('vi-name', v2_te('Name *'), $inp('vi-name', 'maxlength="160" autocomplete="off" placeholder="' . v2_te('Adult ticket, Car parking, Boat…') . '"'), v2_te('This is how it is shown on the public page and on the ticket.'), 'is-wide') ?>
          <?= $fld('vi-cat', v2_te('Kind of product'), $sel('vi-cat', ['access' => v2_te('Access (entry ticket)'), 'parking' => v2_te('Parking'), 'rental' => v2_te('Equipment rental'), 'activity' => v2_te('Guided activity'), 'extra' => v2_te('Extra'), 'package' => v2_te('Package (several products)')])) ?>
          <?= $fld('vi-issuer', v2_te('Issuing company'), $sel('vi-issuer', ['primary' => v2_te('Main'), 'secondary' => v2_te('Second'), 'mix' => v2_te('Both (packages only)')]), '<span id="vi-mix-hint" hidden>' . v2_te('With "Both", split the package price between its components, below.') . '</span>') ?>
          <?= $fld('vi-group', v2_te('Display category'), $sel('vi-group', ['' => v2_te('Other products')])) ?>
          <?= $fld('vi-sku', v2_te('Internal code (SKU)'), $inp('vi-sku', 'maxlength="64" autocomplete="off"', 've-mono')) ?>
          <?= $fld('vi-price', v2_te('Online price (€) *'), $inp('vi-price', 'type="number" min="0" max="999999" step="0.01" inputmode="decimal"')) ?>
          <?= $fld('vi-pos-price', v2_te('Register price (€)'), $inp('vi-pos-price', 'type="number" min="0" max="999999" step="0.01" inputmode="decimal" placeholder="' . v2_te('empty = the online price') . '"'), v2_te('A price here also makes it visible at the point of sale.')) ?>
          <?= $fld('vi-capacity', v2_te('Total stock'), $inp('vi-capacity', 'type="number" min="0" inputmode="numeric" placeholder="' . v2_te('empty = unlimited') . '"')) ?>
          <?= $fld('vi-daily', v2_te('Places per day'), $inp('vi-daily', 'type="number" min="0" inputmode="numeric" placeholder="' . v2_te('empty = no limit') . '"')) ?>
          <?= $fld('vi-min', v2_te('Minimum quantity in the basket'), $inp('vi-min', 'type="number" min="1" inputmode="numeric" placeholder="1"')) ?>
          <?= $fld('vi-max', v2_te('Maximum quantity in the basket'), $inp('vi-max', 'type="number" min="0" inputmode="numeric" placeholder="' . v2_te('empty = no limit') . '"')) ?>
          <?= $fld('vi-step', v2_te('Step for + and −'), $inp('vi-step', 'type="number" min="1" inputmode="numeric" placeholder="1"')) ?>
          <?= $fld('vi-duration', v2_te('Service length (minutes)'), $inp('vi-duration', 'type="number" min="0" max="1440" inputmode="numeric"')) ?>
          <?= $fld('vi-icon', v2_te('Icon (an emoji)'), $inp('vi-icon', 'maxlength="6" autocomplete="off" placeholder="🎟️"')) ?>
          <?= $fld('vi-unit', v2_te('Price unit'), $inp('vi-unit', 'maxlength="60" autocomplete="off" placeholder="' . v2_te('ticket, person, car / 3 hours') . '"')) ?>
        </div>

        <div class="ve-sec ve-sec-warm">
          <label class="po-check"><input type="checkbox" id="vi-is-group"><span><?= v2_t('<b>Group ticket</b>: the price shown becomes "from" the minimum quantity × price.') ?></span></label>
          <div class="ve-form" id="vi-group-extra" hidden>
            <label class="po-check is-wide"><input type="checkbox" id="vi-guide"><span><?= v2_te('At the minimum quantity a free ticket is issued for the guide') ?></span></label>
            <?= $fld('vi-guide-label', v2_te('Name of the guide\'s ticket'), $inp('vi-guide-label', 'maxlength="80" placeholder="' . v2_te('Group guide') . '"')) ?>
          </div>
        </div>

        <div class="ve-sec">
          <p class="ve-sec-k"><?= v2_te('Card picture') ?></p>
          <div class="ve-image" id="vi-image-box">
            <img id="vi-image-thumb" alt="" hidden>
            <span class="ve-sub" id="vi-image-empty"><?= v2_te('JPG, PNG or WebP, 10 MB at most. Recommended: 800 × 600.') ?></span>
            <span class="ve-image-tools">
              <label class="btn btn-ghost" for="vi-image-file"><?= v2_ic('plus') ?><span id="vi-image-label"><?= v2_te('Choose a picture') ?></span></label>
              <input type="file" id="vi-image-file" accept="image/jpeg,image/png,image/webp" class="ve-sr">
              <button class="btn btn-ghost" type="button" id="vi-image-rm" hidden><?= v2_ic('trash') ?><?= v2_te('Remove the picture') ?></button>
            </span>
          </div>
        </div>

        <div class="ve-form">
          <span class="po-field is-wide"><label for="vi-desc"><?= v2_te('Short description') ?></label><textarea class="ve-ta" id="vi-desc" rows="2" maxlength="2000"></textarea></span>
          <span class="po-field is-wide"><label for="vi-includes"><?= v2_te('Includes, one per line') ?></label><textarea class="ve-ta" id="vi-includes" rows="3" placeholder="<?= v2_te('All-day access') ?>&#10;<?= v2_te('Printed map') ?>"></textarea></span>
          <span class="po-field is-wide"><label for="vi-terms"><?= v2_te('Terms of use') ?></label><textarea class="ve-ta" id="vi-terms" rows="2" maxlength="10000"></textarea></span>
        </div>

        <div class="ve-sec">
          <button class="ve-sec-toggle" type="button" id="vi-tr-btn" aria-expanded="false" aria-controls="vi-tr-body"><?= v2_ic('caret-down') ?><?= v2_te('Translations into Hungarian and English') ?> <span class="ve-sub"><?= v2_te('only what you want; empty fields stay in the main language') ?></span></button>
          <div id="vi-tr-body" hidden>
            <div class="ve-tabs" role="tablist">
              <button type="button" role="tab" class="ve-tab" data-tab="hu" aria-selected="true"><?= v2_te('Hungarian') ?></button>
              <button type="button" role="tab" class="ve-tab" data-tab="en" aria-selected="false"><?= v2_te('English') ?></button>
            </div>
            <?= $tr('hu', 'HU') ?>
            <?= $tr('en', 'EN') ?>
          </div>
        </div>

        <div class="ve-sec" data-when="rental activity">
          <div class="ve-sec-head"><p class="ve-sec-k"><?= v2_te('Length and price variants') ?></p><button class="btn btn-ghost" type="button" data-add="variant"><?= v2_ic('plus') ?><?= v2_te('Variant') ?></button></div>
          <p class="ve-sub"><?= v2_te('The same physical unit, different prices by length. Each booking uses one unit, whatever the variant.') ?></p>
          <div class="ve-rows" id="vi-variants"></div>
        </div>

        <div class="ve-sec" data-when="access rental activity">
          <div class="ve-sec-head"><p class="ve-sec-k"><?= v2_te('Optional add-ons') ?></p><button class="btn btn-ghost" type="button" data-add="addon"><?= v2_ic('plus') ?><?= v2_te('Add-on') ?></button></div>
          <p class="ve-sub"><?= v2_te('"Included" = how many are free with each ticket. "Paid maximum" = how many more can be added for a fee.') ?></p>
          <div class="ve-rows" id="vi-addons"></div>
        </div>

        <div class="ve-sec ve-sec-cool" data-when="rental activity">
          <label class="po-check"><input type="checkbox" id="vi-slots-on"><span><?= v2_t('<b>Departures at fixed times</b> (booking by time slot)') ?></span></label>
          <div class="ve-form ve-form-4" id="vi-slots" hidden>
            <?= $fld('vi-slot-first', v2_te('First departure'), $inp('vi-slot-first', 'type="time"')) ?>
            <?= $fld('vi-slot-last', v2_te('Last departure'), $inp('vi-slot-last', 'type="time"')) ?>
            <?= $fld('vi-slot-interval', v2_te('Every (min)'), $inp('vi-slot-interval', 'type="number" min="5" inputmode="numeric" placeholder="30"')) ?>
            <?= $fld('vi-slot-duration', v2_te('A departure lasts (min)'), $inp('vi-slot-duration', 'type="number" min="5" inputmode="numeric" placeholder="30"')) ?>
            <?= $fld('vi-slot-capacity', v2_te('Places per departure'), $inp('vi-slot-capacity', 'type="number" min="1" inputmode="numeric" placeholder="14"')) ?>
            <?= $fld('vi-slot-pricing', v2_te('Sold'), $sel('vi-slot-pricing', ['per_person' => v2_te('Per person'), 'per_slot' => v2_te('Per whole departure')])) ?>
          </div>
        </div>

        <div class="ve-sec ve-sec-warm" data-when="rental activity">
          <label class="po-check"><input type="checkbox" id="vi-phys-on"><span><?= v2_t('<b>Physical inventory</b>: a unit cannot be in two overlapping time slots') ?></span></label>
          <div class="ve-form" id="vi-phys" hidden>
            <?= $fld('vi-phys-count', v2_te('How many units the venue has'), $inp('vi-phys-count', 'type="number" min="1" inputmode="numeric" placeholder="10"'), v2_te('For example 10 boats.')) ?>
          </div>
        </div>

        <div class="ve-sec" data-when="package">
          <div class="ve-sec-head"><p class="ve-sec-k"><?= v2_te('What the package contains') ?></p>
            <span class="ve-sec-tools"><button class="btn btn-ghost" type="button" id="vi-alloc"><?= v2_ic('percent') ?><?= v2_te('Split the price automatically') ?></button><button class="btn btn-ghost" type="button" data-add="package"><?= v2_ic('plus') ?><?= v2_te('Component') ?></button></span></div>
          <p class="ve-sub"><?= v2_te('When bought, the package issues these tickets. The package price is the one above; the saving against the components is worked out for you.') ?></p>
          <div class="ve-rows" id="vi-package"></div>
          <p class="ve-note-line" id="vi-pkg-sum" hidden></p>
        </div>

        <div class="ve-sec">
          <p class="ve-sec-k"><?= v2_te('Options') ?></p>
          <div class="ve-checks">
            <label class="po-check"><input type="checkbox" id="vi-active"><span><?= v2_te('Active') ?></span></label>
            <label class="po-check" data-when="access parking rental activity extra"><input type="checkbox" id="vi-parking"><span><?= v2_te('Is parking') ?></span></label>
            <label class="po-check" data-when="access parking rental activity extra"><input type="checkbox" id="vi-vehicle"><span><?= v2_te('Asks for the number plate') ?></span></label>
            <label class="po-check" data-when="access"><input type="checkbox" id="vi-child"><span><?= v2_te('Child\'s ticket (free)') ?></span></label>
            <label class="po-check"><input type="checkbox" id="vi-pos-only"><span><?= v2_te('Register only (hidden online)') ?></span></label>
          </div>
        </div>

        <div class="ve-form" data-when="rental activity">
          <?= $fld('vi-access-req', v2_te('Requires an access ticket'), $sel('vi-access-req', ['none' => v2_te('No'), 'any' => v2_te('Yes: any access ticket'), 'adult_only' => v2_te('Yes: adult ticket only')]), v2_te('Boat trip: any ticket (one per passenger). Rowing boat: adult only (one adult per boat).'), 'is-wide') ?>
        </div>

        <div class="ve-sec" data-when="rental activity">
          <div class="ve-sec-head"><p class="ve-sec-k"><?= v2_te('Blocked hours (for information)') ?></p><button class="btn btn-ghost" type="button" data-add="block"><?= v2_ic('plus') ?><?= v2_te('Time slot') ?></button></div>
          <p class="ve-sub"><?= v2_te('Shown as a notice on the public page and at the point of sale, for example for a private group. It does not stop sales.') ?></p>
          <div class="ve-rows" id="vi-blocks"></div>
        </div>
      </div>
      <div class="ve-modal-foot">
        <button class="ve-danger" type="button" id="vi-del" hidden><?= v2_ic('trash') ?><span><?= v2_te('Delete the product') ?></span></button>
        <button class="btn btn-ghost" type="button" id="vi-cancel"><?= v2_te('Cancel') ?></button>
        <button class="btn btn-primary" type="button" id="vi-save"><?= v2_ic('check') ?><?= v2_te('Save') ?></button>
      </div>
    </div>
  </div>
</div>
<?php
v2_org_end();
include __DIR__ . '/../includes/v2/foot.php';
