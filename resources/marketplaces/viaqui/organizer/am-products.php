<?php
/**
 * The operator's products: /organizator/produse (activities module), v2 design.
 *
 * What a location sells, in three kinds: access tickets (entry for the whole day or several days, parking, camping,
 * adult / child / group tickets), experiences (rentals, tours, workshops, with start times or for the whole day,
 * per person or per boat / group) and packages (access tickets and experiences together, at one price). A list with
 * filters and, on the same page, a step-by-step editor (?id=N edits, ?nou=1 starts one, ?locatie=N filters or
 * preselects). A new product is a draft; the first publication waits for viaqui.com's approval, later edits go live
 * at once. Product icons are SVG (includes/v2/product-icons.php): the whole set is printed here for the editor.
 *
 * org-am-products.js reads .../meta, .../locations and .../products, saves with POST/PUT .../products, asks for
 * approval (.../submit), shows or hides (.../publish), copies (.../duplicate) and deletes a product never sold.
 */

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/api.php';
require_once __DIR__ . '/../includes/nav-helpers.php';
require_once __DIR__ . '/../includes/v2/helpers.php';
require_once __DIR__ . '/../includes/v2/organizer.php';
require_once __DIR__ . '/../includes/v2/am-labels.php';

$pageTitleRaw = v2_t('Products: {site}', ['site' => SITE_NAME]);
$pageDescription = v2_t('The access tickets, experiences and packages of your venues.');
$canonicalUrl = SITE_URL . '/organizator/produse';
$noindex = true;
$skipPageCache = true;

$v2Styles = ['organizer.css', 'org-venue.css', 'org-am.css', 'org-am-wizard.css'];
$v2Scripts = ['organizer.js', 'org-am.js', 'org-am-products.js'];
$v2LegacyScripts = ['assets/js/config.js', 'assets/js/utils.js', 'assets/js/api.js', 'assets/js/auth.js'];
$v2HeadExtra = v2_account_client_config('organizer');
$v2ClientData = ['am' => am_client_labels()];

include __DIR__ . '/../includes/v2/head.php';
v2_org_start('am-products');
echo am_product_icon_sprite();
?>
<div class="ve am" id="am-prod">
  <header class="ve-head" id="am-prod-head">
    <div>
      <p class="ve-eyebrow"><?= v2_ic('ticket') ?><?= v2_te('Venues and products') ?></p>
      <h1 class="ve-h"><?= v2_te('Products') ?></h1>
      <p class="ve-lead"><?= v2_te('Access tickets, experiences and packages. They can all go in the same basket, on the same date.') ?></p>
    </div>
    <div class="ve-head-btns">
      <a class="btn btn-primary" href="/organizator/produse?nou=1"><?= v2_ic('plus') ?><?= v2_te('New product') ?></a>
    </div>
  </header>

  <div class="am-filters" id="am-prod-filters">
    <span class="po-field"><label for="am-f-loc"><?= v2_te('Venue') ?></label><span class="po-select"><select id="am-f-loc"><option value=""><?= v2_te('All venues') ?></option></select><?= v2_ic('caret-down') ?></span></span>
    <span class="po-field"><label for="am-f-type"><?= v2_te('Type') ?></label><span class="po-select"><select id="am-f-type">
      <option value=""><?= v2_te('All') ?></option><option value="access"><?= v2_te('Access tickets') ?></option><option value="experience"><?= v2_te('Experiences') ?></option><option value="package"><?= v2_te('Packages') ?></option>
    </select><?= v2_ic('caret-down') ?></span></span>
  </div>

  <div class="org-empty is-error" id="am-prod-failed" hidden>
    <span class="org-empty-ic"><?= v2_ic('warning-circle') ?></span>
    <b><?= v2_te('We could not load the products') ?></b>
    <p><?= v2_te('Try again in a few seconds.') ?></p>
    <button class="btn btn-primary" type="button" id="am-prod-retry"><?= v2_te('Try again') ?></button>
  </div>

  <div id="am-prod-list" aria-live="polite"><p class="ve-state"><?= v2_te('Loading…') ?></p></div>
  <div id="am-prod-edit" hidden></div>
</div>
<?php
v2_org_end();
include __DIR__ . '/../includes/v2/foot.php';
