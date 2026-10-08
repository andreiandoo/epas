<?php
/**
 * The operator's bookings: /organizator/rezervari (activities module), v2 design.
 *
 * On top, the last 30 days (bookings, persons, sales, what the operator keeps) and the arrivals of today and the next
 * 7 days. Two views: "By day" (who comes on a date, per product and start time, with the seats left, and marking a
 * paid visitor who did not come) and "All bookings" (by visit date, filtered by product, status and a search,
 * with a CSV export).
 *
 * org-am-bookings.js reads /organizer/activities-module/summary, .../bookings/day, .../bookings and .../products, marks
 * no-shows (.../bookings/{id}/no-show) and downloads .../bookings/export.
 */

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/api.php';
require_once __DIR__ . '/../includes/nav-helpers.php';
require_once __DIR__ . '/../includes/v2/helpers.php';
require_once __DIR__ . '/../includes/v2/organizer.php';
require_once __DIR__ . '/../includes/v2/am-labels.php';

$pageTitleRaw = v2_t('Bookings: {site}', ['site' => SITE_NAME]);
$pageDescription = v2_t('The bookings for your venues and products: by day, by start time, with an export.');
$canonicalUrl = SITE_URL . '/organizator/rezervari';
$noindex = true;
$skipPageCache = true;

$v2Styles = ['organizer.css', 'org-venue.css', 'org-am.css'];
$v2Scripts = ['organizer.js', 'org-am.js', 'org-am-bookings.js'];
$v2LegacyScripts = ['assets/js/config.js', 'assets/js/utils.js', 'assets/js/api.js', 'assets/js/auth.js'];
$v2HeadExtra = v2_account_client_config('organizer');
$v2ClientData = ['am' => am_client_labels()];

include __DIR__ . '/../includes/v2/head.php';
v2_org_start('am-bookings');
?>
<div class="ve am" id="am-bk">
  <header class="ve-head">
    <div>
      <p class="ve-eyebrow"><?= v2_ic('calendar-blank') ?><?= v2_te('Bookings') ?></p>
      <h1 class="ve-h"><?= v2_te('Bookings') ?></h1>
      <p class="ve-lead"><?= v2_te('Who comes and when, for each product. Tickets are checked in with the scanning app, at the entrance.') ?></p>
    </div>
    <div class="am-filters">
      <span class="po-field"><label for="am-bk-loc"><?= v2_te('Venue') ?></label><span class="po-select"><select id="am-bk-loc"><option value=""><?= v2_te('All venues') ?></option></select><?= v2_ic('caret-down') ?></span></span>
    </div>
  </header>

  <section class="am-kpis" id="am-bk-kpis" aria-label="<?= v2_te('The last 30 days') ?>"></section>

  <div class="ve-tabs" role="tablist" aria-label="<?= v2_te('Views') ?>">
    <button class="ve-tab" type="button" role="tab" id="am-tab-day" aria-selected="true" aria-controls="am-bk-day"><?= v2_te('By day') ?></button>
    <button class="ve-tab" type="button" role="tab" id="am-tab-all" aria-selected="false" aria-controls="am-bk-all"><?= v2_te('All bookings') ?></button>
  </div>

  <section class="org-panel am-stack" id="am-bk-day" role="tabpanel" aria-labelledby="am-tab-day">
    <div class="am-daynav">
      <button class="ve-icon-btn" type="button" id="am-day-prev" aria-label="<?= v2_te('Previous day') ?>"><?= v2_ic('arrow-left') ?></button>
      <label class="ve-sr" for="am-day"><?= v2_te('Day') ?></label>
      <input class="po-input" type="date" id="am-day">
      <button class="ve-icon-btn" type="button" id="am-day-next" aria-label="<?= v2_te('Next day') ?>"><?= v2_ic('arrow-right') ?></button>
      <button class="btn btn-ghost" type="button" id="am-day-today"><?= v2_te('Today') ?></button>
      <b id="am-day-total" aria-live="polite"></b>
    </div>
    <div class="am-stack" id="am-day-body"><p class="ve-state"><?= v2_te('Loading…') ?></p></div>
  </section>

  <section class="org-panel am-stack" id="am-bk-all" role="tabpanel" aria-labelledby="am-tab-all" hidden>
    <form class="am-filters" id="am-all-form">
      <span class="po-field"><label for="am-all-from"><?= v2_te('From') ?></label><input class="po-input" type="date" id="am-all-from"></span>
      <span class="po-field"><label for="am-all-to"><?= v2_te('Until') ?></label><input class="po-input" type="date" id="am-all-to"></span>
      <span class="po-field"><label for="am-all-prod"><?= v2_te('Product') ?></label><span class="po-select"><select id="am-all-prod"><option value=""><?= v2_te('All products') ?></option></select><?= v2_ic('caret-down') ?></span></span>
      <span class="po-field"><label for="am-all-status"><?= v2_te('Status') ?></label><span class="po-select"><select id="am-all-status">
        <option value=""><?= v2_te('Sold and cancelled') ?></option><option value="paid"><?= v2_te('Paid') ?></option><option value="checked_in"><?= v2_te('Checked in') ?></option><option value="no_show"><?= v2_te('No-show') ?></option><option value="cancelled"><?= v2_te('Cancelled') ?></option>
      </select><?= v2_ic('caret-down') ?></span></span>
      <span class="po-field"><label for="am-all-q"><?= v2_te('Search') ?></label><input class="po-input" type="search" id="am-all-q" placeholder="<?= v2_te('Name, email, code') ?>" autocomplete="off"></span>
      <button class="btn btn-primary" type="submit"><?= v2_te('Show') ?></button>
      <button class="btn btn-ghost" type="button" id="am-all-export"><?= v2_ic('file-text') ?><?= v2_te('Export CSV') ?></button>
    </form>
    <div class="ve-table-wrap"><table class="ve-table am-table">
      <thead><tr><th scope="col"><?= v2_te('Date') ?></th><th scope="col"><?= v2_te('Product') ?></th><th scope="col"><?= v2_te('Customer') ?></th><th scope="col"><?= v2_te('Value') ?></th><th scope="col"><?= v2_te('Status') ?></th></tr></thead>
      <tbody id="am-all-rows"><tr><td colspan="5" class="ve-state"><?= v2_te('Loading…') ?></td></tr></tbody>
    </table></div>
    <nav class="ve-pager am-daynav" id="am-all-pager" aria-label="<?= v2_te('Pages') ?>" hidden>
      <button class="btn btn-ghost" type="button" id="am-all-prev"><?= v2_ic('arrow-left') ?><?= v2_te('Back') ?></button>
      <span id="am-all-page"></span>
      <button class="btn btn-ghost" type="button" id="am-all-next"><?= v2_te('Next') ?><?= v2_ic('arrow-right') ?></button>
    </nav>
  </section>
</div>
<?php
v2_org_end();
include __DIR__ . '/../includes/v2/foot.php';
