<?php
/**
 * Venue orders: /organizator/locatie/comenzi (venue-orders.php), v2 design.
 *
 * Third screen of the "Locație" section, ported from Ambilet: every order of the venue, online and at the counter,
 * with the tickets behind each one. An order can be deleted, which is irreversible and therefore asks for a written
 * reason; the core keeps that reason, the whole order and its tickets in the deletion history, which is the panel at
 * the bottom of this page.
 *
 * org-venue-orders.js reads /organizer/events, then .../leisure/orders, .../leisure/orders/{id} for one row's
 * tickets, .../leisure/orders/deletion-history for the panel, and deletes through DELETE .../leisure/orders/{id}.
 */

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/api.php';
require_once __DIR__ . '/../includes/nav-helpers.php';
require_once __DIR__ . '/../includes/v2/helpers.php';
require_once __DIR__ . '/../includes/v2/organizer.php';

$pageTitleRaw = v2_t('Venue orders: {site}', ['site' => SITE_NAME]);
$pageDescription = v2_t('The venue\'s orders, online and at the register, with the tickets in each order and the deletion history.');
$canonicalUrl = SITE_URL . '/organizator/locatie/comenzi';
$noindex = true;
$skipPageCache = true;

$v2Styles = ['organizer.css', 'org-venue.css'];
$v2Scripts = ['organizer.js', 'org-venue-orders.js'];
$v2LegacyScripts = ['assets/js/config.js', 'assets/js/utils.js', 'assets/js/api.js', 'assets/js/auth.js'];
$v2HeadExtra = v2_account_client_config('organizer');

include __DIR__ . '/../includes/v2/head.php';
v2_org_start('venue-orders');
?>
<div class="ve" id="ve">
  <header class="ve-head">
    <div>
      <p class="ve-eyebrow"><?= v2_ic('receipt') ?><?= v2_te('Venue · Orders') ?></p>
      <h1 class="ve-h"><?= v2_te('Orders') ?></h1>
      <p class="ve-lead"><?= v2_te('Every order of the venue, from the site and from the register. Open a row to see the tickets issued.') ?></p>
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
    <b><?= v2_te('We could not load the orders') ?></b>
    <p><?= v2_te('Try again in a few seconds.') ?></p>
    <button class="btn btn-primary" type="button" id="ve-retry"><?= v2_te('Try again') ?></button>
  </div>

  <div class="ve-main" id="ve-main" hidden>
    <section class="org-panel" aria-labelledby="vo-filters-h">
      <h2 class="ve-sr" id="vo-filters-h"><?= v2_te('Filters') ?></h2>
      <div class="ve-filters">
        <div class="ve-ranges" role="group" aria-label="<?= v2_te('Payment period') ?>">
          <?php foreach ([['7', v2_t('Last 7 days')], ['14', v2_t('14 days')], ['30', v2_t('One month')], ['90', v2_t('3 months')], ['180', v2_t('6 months')], ['custom', v2_t('Custom period')]] as [$val, $label]): ?>
          <button class="ve-range" type="button" data-range="<?= $val ?>" aria-pressed="<?= $val === '30' ? 'true' : 'false' ?>"><?= v2_e($label) ?></button>
          <?php endforeach; ?>
        </div>
        <div class="ve-dates" id="vo-custom" hidden>
          <span class="po-field"><label for="vo-from"><?= v2_te('From') ?></label><input class="po-input" type="date" id="vo-from"></span>
          <span class="po-field"><label for="vo-to"><?= v2_te('To') ?></label><input class="po-input" type="date" id="vo-to"></span>
          <button class="btn btn-ghost" type="button" id="vo-apply"><?= v2_te('Apply') ?></button>
        </div>

        <div class="ve-filter-row">
          <label class="ve-search"><?= v2_ic('magnifying-glass') ?><input id="vo-q" type="search" autocomplete="off" placeholder="<?= v2_te('Search by order number, name or email') ?>" aria-label="<?= v2_te('Search orders') ?>"></label>
          <span class="po-select"><select id="vo-source" aria-label="<?= v2_te('Order source') ?>"><option value=""><?= v2_te('All sources') ?></option><option value="pos"><?= v2_te('At the register') ?></option><option value="online"><?= v2_te('On the site') ?></option></select><?= v2_ic('caret-down') ?></span>
          <span class="po-select"><select id="vo-status" aria-label="<?= v2_te('Order status') ?>"><option value=""><?= v2_te('All statuses') ?></option><option value="paid"><?= v2_te('Paid') ?></option><option value="completed"><?= v2_te('Completed') ?></option><option value="pending"><?= v2_te('Pending') ?></option><option value="refunded"><?= v2_te('Refunded') ?></option><option value="cancelled"><?= v2_te('Cancelled') ?></option></select><?= v2_ic('caret-down') ?></span>
          <button class="ve-reset" type="button" id="vo-reset" hidden><?= v2_ic('x') ?><?= v2_te('Clear filters') ?></button>
        </div>
      </div>
    </section>

    <section class="org-panel" aria-labelledby="vo-list-h">
      <div class="org-panel-head">
        <div><h2 class="org-panel-h" id="vo-list-h"><?= v2_te('Order list') ?></h2><p class="org-panel-p" id="vo-period">—</p></div>
      </div>
      <div class="ve-table-wrap"><table class="ve-table ve-ord-table">
        <thead><tr>
          <th scope="col"><span class="ve-sr"><?= v2_te('Open') ?></span></th>
          <th scope="col"><?= v2_te('Order no.') ?></th><th scope="col"><?= v2_te('Paid on') ?></th><th scope="col"><?= v2_te('Customer') ?></th>
          <th scope="col"><?= v2_te('Source') ?></th><th scope="col"><?= v2_te('Payment') ?></th><th scope="col"><?= v2_te('Staff') ?></th>
          <th scope="col" class="ve-r"><?= v2_te('Tickets') ?></th><th scope="col" class="ve-r"><?= v2_te('Total') ?></th>
          <th scope="col"><?= v2_te('Status') ?></th><th scope="col"><span class="ve-sr"><?= v2_te('Actions') ?></span></th>
        </tr></thead>
        <tbody id="vo-rows"><tr><td colspan="11" class="ve-state"><?= v2_te('Loading…') ?></td></tr></tbody>
      </table></div>
      <div class="ve-pager">
        <span id="vo-count"></span>
        <span class="ve-pg">
          <button type="button" id="vo-prev" disabled><?= v2_ic('arrow-left') ?><?= v2_te('Previous') ?></button>
          <button type="button" id="vo-next" disabled><?= v2_te('Next') ?><?= v2_ic('arrow-right') ?></button>
        </span>
      </div>
    </section>

    <section class="org-panel ve-hist" aria-labelledby="vo-hist-h">
      <div class="org-panel-head">
        <div><h2 class="org-panel-h" id="vo-hist-h"><?= v2_ic('clock') ?> <?= v2_te('Deletion history') ?></h2>
        <p class="org-panel-p"><?= v2_te('What was deleted, by whom, when and why. Everything is kept.') ?></p></div>
        <button class="btn btn-ghost ve-hist-btn" type="button" id="vo-hist-btn" aria-expanded="false" aria-controls="vo-hist-body"><?= v2_te('Show history') ?><?= v2_ic('caret-down') ?></button>
      </div>
      <div id="vo-hist-body" hidden>
        <div class="ve-hist-row">
          <label class="ve-search"><?= v2_ic('magnifying-glass') ?><input id="vo-hq" type="search" autocomplete="off" placeholder="<?= v2_te('Search the history') ?>" aria-label="<?= v2_te('Search the deletion history') ?>"></label>
          <span class="po-field"><label class="ve-sr" for="vo-hfrom"><?= v2_te('From') ?></label><input class="po-input" type="date" id="vo-hfrom"></span>
          <span class="po-field"><label class="ve-sr" for="vo-hto"><?= v2_te('To') ?></label><input class="po-input" type="date" id="vo-hto"></span>
        </div>
        <div class="ve-table-wrap"><table class="ve-table ve-hist-table">
          <thead><tr>
            <th scope="col"><?= v2_te('Order no.') ?></th><th scope="col"><?= v2_te('Deleted on') ?></th><th scope="col"><?= v2_te('By') ?></th>
            <th scope="col"><?= v2_te('Customer') ?></th><th scope="col" class="ve-r"><?= v2_te('Tickets') ?></th><th scope="col" class="ve-r"><?= v2_te('Total') ?></th>
            <th scope="col"><?= v2_te('Reason') ?></th>
          </tr></thead>
          <tbody id="vo-hrows"><tr><td colspan="7" class="ve-state"><?= v2_te('Loading…') ?></td></tr></tbody>
        </table></div>
        <div class="ve-pager">
          <span id="vo-hcount"></span>
          <span class="ve-pg">
            <button type="button" id="vo-hprev" disabled><?= v2_ic('arrow-left') ?><?= v2_te('Previous') ?></button>
            <button type="button" id="vo-hnext" disabled><?= v2_te('Next') ?><?= v2_ic('arrow-right') ?></button>
          </span>
        </div>
      </div>
    </section>
  </div>

  <div class="ve-modal" id="vo-modal" role="dialog" aria-modal="true" aria-labelledby="vo-modal-h" hidden>
    <div class="ve-modal-card">
      <div class="ve-modal-head"><h2 id="vo-modal-h"><?= v2_t('Delete order {number}?', ['number' => '<span id="vo-del-nr"></span>']) ?></h2></div>
      <div class="ve-modal-body">
        <p class="ve-warn"><?= v2_ic('warning-circle') ?><span><?= v2_t('Deleting is <strong>irreversible</strong>. The order, its tickets and its check-ins are removed from the database. If it was part of a closed register session, the session report is recalculated. Everything stays in the deletion history.') ?></span></p>
        <dl class="ve-sum" id="vo-del-sum"></dl>
        <span class="po-field">
          <label for="vo-del-note"><?= v2_te('Reason for deleting (required)') ?></label>
          <textarea class="ve-ta" id="vo-del-note" rows="3" minlength="3" maxlength="1000" placeholder="<?= v2_te('E.g. order doubled by mistake, staff error…') ?>"></textarea>
          <span class="ve-hint"><?= v2_te('At least 3 characters, at most 1000. It is shown in the history.') ?></span>
        </span>
      </div>
      <div class="ve-modal-foot">
        <button class="btn btn-ghost" type="button" id="vo-del-cancel"><?= v2_te('Cancel') ?></button>
        <button class="ve-danger" type="button" id="vo-del-ok"><?= v2_ic('trash') ?><?= v2_te('Delete for good') ?></button>
      </div>
    </div>
  </div>
</div>
<?php
v2_org_end();
include __DIR__ . '/../includes/v2/foot.php';
