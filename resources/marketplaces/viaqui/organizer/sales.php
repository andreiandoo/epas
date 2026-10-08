<?php
/**
 * Organizer sales: /organizator/vanzari (v2 design).
 *
 * Inside the v2 organizer shell. Every order and booking of the organizer: period (from / to or a month), activity,
 * status and free-text filters; four figures (completed orders, tickets, net revenue, gross takings); how the orders
 * split by status, each a filter; an optional breakdown of completed sales per activity with a total row; the orders
 * table (sortable columns, activity, participant with masked e-mail, ticket types and seats, value with the discount,
 * status, source, date; cards on phones) with pages; CSV export. org-sales.js reads /organizer/orders,
 * /organizer/orders/export (through the proxy, as CSV) and /organizer/events.
 *
 * Fixed on the way:
 * - export with "Finalizate" asked core for status "completed" only, and core's export matches the status exactly, so
 *   paid and confirmed orders were left out (the list shows them): the three are exported and merged by date;
 * - export ignored the month picker (it always read the from / to fields, empty in month mode);
 * - core counts the figures on completed orders inside the status filter, so any other status showed four zeros (and
 *   the breakdown zero tickets): the figures and the split by status now come from the other filters;
 * - the per-activity breakdown only saw the first 50 activities (it asked for 100, the proxy caps at 50) and fetched
 *   them one by one: every activity that sold, three at a time under the proxy's limit, with progress and a total;
 * - paid / confirmed orders showed the raw status word; the table didn't say which activity an order was for;
 * - an answer for older filters could overwrite a newer one while typing; filters, sort and page now stay in the
 *   address, so a view can be reloaded or shared.
 */

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/api.php';
require_once __DIR__ . '/../includes/nav-helpers.php';
require_once __DIR__ . '/../includes/v2/helpers.php';
require_once __DIR__ . '/../includes/v2/organizer.php';

$pageTitle = v2_t('Sales');
$pageDescription = v2_t('Your sales on Viaqui: orders, tickets and revenue by period and by experience, with CSV export.');
$canonicalUrl = SITE_URL . '/organizator/vanzari';
$noindex = true;
$skipPageCache = true;

$v2Styles = ['organizer.css', 'org-sales.css'];
$v2Scripts = ['organizer.js', 'org-sales.js'];
$v2LegacyScripts = ['assets/js/config.js', 'assets/js/utils.js', 'assets/js/api.js', 'assets/js/auth.js'];
$v2HeadExtra = v2_account_client_config('organizer');

$osSortTh = function (string $key, string $label, string $cls = '') {
    return '<th scope="col"' . ($cls ? ' class="' . $cls . '"' : '') . ' data-sort="' . $key . '" aria-sort="none"><button class="os-th" type="button">' . $label . v2_ic('caret-down', 'ic os-arrow') . '</button></th>';
};

include __DIR__ . '/../includes/v2/head.php';
v2_org_start('sales');
?>
<div class="os" id="os">
  <header class="os-head">
    <div>
      <p class="org-k"><?= v2_te('Reports') ?></p>
      <h1 class="os-h" id="os-h"><?= v2_te('Sales') ?></h1>
      <p class="os-lead"><?= v2_te('All your orders and bookings in one place.') ?></p>
    </div>
    <button class="btn btn-ghost" type="button" id="os-export"><?= v2_ic('file-text') ?><span data-label><?= v2_te('Export CSV') ?></span></button>
  </header>

  <section class="org-panel os-filters" aria-labelledby="os-f-h">
    <h2 class="sr" id="os-f-h"><?= v2_te('Filters') ?></h2>
    <div class="os-f-top">
      <div class="os-seg" role="group" aria-label="<?= v2_te('How to choose the period') ?>">
        <button class="os-seg-b" type="button" data-mode="range" aria-pressed="true"><?= v2_te('Date range') ?></button>
        <button class="os-seg-b" type="button" data-mode="month" aria-pressed="false"><?= v2_te('By month') ?></button>
      </div>
      <label class="os-switch"><input type="checkbox" id="os-breakdown"><span class="os-switch-ui" aria-hidden="true"></span><span><?= v2_te('Breakdown by experience') ?></span></label>
      <button class="os-reset" type="button" id="os-reset" hidden><?= v2_ic('x') ?><?= v2_te('Reset filters') ?></button>
    </div>
    <div class="os-f-grid">
      <label class="os-f is-event"><span class="os-f-l"><?= v2_te('Experience') ?></span><span class="os-select"><select id="os-event"><option value=""><?= v2_te('All experiences') ?></option></select><?= v2_ic('caret-down') ?></span></label>
      <label class="os-f is-status"><span class="os-f-l"><?= v2_te('Status') ?></span><span class="os-select"><select id="os-status">
        <option value=""><?= v2_te('All statuses') ?></option>
        <option value="completed" selected><?= v2_te('Completed') ?></option>
        <option value="pending"><?= v2_te('Pending') ?></option>
        <option value="failed"><?= v2_te('Failed') ?></option>
        <option value="expired"><?= v2_te('Expired') ?></option>
        <option value="cancelled"><?= v2_te('Cancelled') ?></option>
        <option value="refunded"><?= v2_te('Refunded') ?></option>
      </select><?= v2_ic('caret-down') ?></span></label>
      <label class="os-f" data-for-mode="range"><span class="os-f-l"><?= v2_te('From date') ?></span><input type="date" id="os-from" aria-describedby="os-f-err"></label>
      <label class="os-f" data-for-mode="range"><span class="os-f-l"><?= v2_te('To date') ?></span><input type="date" id="os-to" aria-describedby="os-f-err"></label>
      <label class="os-f is-month" data-for-mode="month" hidden><span class="os-f-l"><?= v2_te('Month') ?></span><input type="month" id="os-month"></label>
      <label class="os-f is-search"><span class="os-f-l"><?= v2_te('Search') ?></span><span class="os-input"><?= v2_ic('magnifying-glass') ?><input type="search" id="os-q" maxlength="100" autocomplete="off" spellcheck="false" placeholder="<?= v2_te('Name, email, order…') ?>"></span></label>
    </div>
    <p class="os-f-err" id="os-f-err" role="alert" hidden></p>
  </section>

  <section class="os-stats" aria-labelledby="os-stats-h">
    <h2 class="sr" id="os-stats-h"><?= v2_te('Summary for the chosen filters') ?></h2>
    <article class="os-stat is-mint"><p class="os-stat-k"><?= v2_te('Completed orders') ?></p><p class="os-stat-v" id="os-s-orders"><span class="org-skel os-sk"></span></p></article>
    <article class="os-stat"><p class="os-stat-k"><?= v2_te('Tickets / bookings') ?></p><p class="os-stat-v" id="os-s-tickets"><span class="org-skel os-sk"></span></p></article>
    <article class="os-stat is-warm"><p class="os-stat-k"><?= v2_te('Net revenue') ?></p><p class="os-stat-v" id="os-s-net"><span class="org-skel os-sk"></span></p><p class="os-stat-p"><?= v2_te('Ticket price, after discounts, without commission') ?></p></article>
    <article class="os-stat"><p class="os-stat-k"><?= v2_te('Gross takings') ?></p><p class="os-stat-v" id="os-s-gross"><span class="org-skel os-sk"></span></p><p class="os-stat-p"><?= v2_te('Total paid by customers on completed orders') ?></p></article>
  </section>

  <section class="org-panel os-bd" id="os-bd" aria-labelledby="os-bd-h" hidden>
    <div class="org-panel-head">
      <div><p class="org-k"><?= v2_te('By experience') ?></p><h2 class="org-panel-h" id="os-bd-h"><?= v2_te('Breakdown by experience') ?></h2><p class="org-panel-p" id="os-bd-period"></p></div>
      <p class="os-bd-progress" id="os-bd-progress" role="status"></p>
    </div>
    <div class="os-table-wrap">
      <table class="os-table os-bd-table">
        <caption class="sr"><?= v2_te('Orders, tickets and net revenue for each experience') ?></caption>
        <thead><tr><th scope="col"><?= v2_te('Experience') ?></th><th scope="col" class="is-num"><?= v2_te('Orders') ?></th><th scope="col" class="is-num"><?= v2_te('Tickets') ?></th><th scope="col" class="is-num"><?= v2_te('Net revenue') ?></th></tr></thead>
        <tbody id="os-bd-body"></tbody>
        <tfoot id="os-bd-foot"></tfoot>
      </table>
    </div>
  </section>

  <section class="org-panel os-orders" aria-labelledby="os-o-h">
    <div class="org-panel-head">
      <div><p class="org-k"><?= v2_te('Orders') ?></p><h2 class="org-panel-h" id="os-o-h" tabindex="-1"><?= v2_te('Order list') ?></h2><p class="org-panel-p" id="os-o-count"><?= v2_te('Loading…') ?></p></div>
      <label class="os-f os-sort-m"><span class="os-f-l"><?= v2_te('Sort') ?></span><span class="os-select"><select id="os-sort-m">
        <option value="created_at:desc"><?= v2_te('Newest first') ?></option>
        <option value="created_at:asc"><?= v2_te('Oldest first') ?></option>
        <option value="total:desc"><?= v2_te('Value, descending') ?></option>
        <option value="total:asc"><?= v2_te('Value, ascending') ?></option>
        <option value="order_number:asc"><?= v2_te('Order number') ?></option>
        <option value="customer_name:asc"><?= v2_te('Participant, A–Z') ?></option>
        <option value="status:asc"><?= v2_te('Status') ?></option>
        <option value="source:asc"><?= v2_te('Source') ?></option>
      </select><?= v2_ic('caret-down') ?></span></label>
    </div>
    <div class="os-mix" id="os-mix" hidden><p class="os-mix-k"><?= v2_te('Orders by status') ?></p><div class="os-mix-list" id="os-mix-list"></div></div>
    <p class="sr" id="os-live" aria-live="polite"></p>
    <div class="os-table-wrap is-orders" id="os-orders-wrap">
      <table class="os-table os-o-table" id="os-table">
        <caption class="sr"><?= v2_te('Orders for the chosen filters') ?></caption>
        <thead>
          <tr>
            <?= $osSortTh('order_number', v2_te('Order')) ?>
            <?= $osSortTh('customer_name', v2_te('Participant')) ?>
            <th scope="col"><?= v2_te('Tickets') ?></th>
            <?= $osSortTh('total', v2_te('Value'), 'is-num') ?>
            <?= $osSortTh('status', v2_te('Status')) ?>
            <?= $osSortTh('source', v2_te('Source')) ?>
            <?= $osSortTh('created_at', v2_te('Date')) ?>
          </tr>
        </thead>
        <tbody id="os-rows">
          <?php for ($i = 0; $i < 4; $i++): ?><tr class="is-skel" aria-hidden="true"><td colspan="7"><span class="org-skel os-sk-row"></span></td></tr><?php endfor; ?>
        </tbody>
      </table>
    </div>
    <div class="org-empty os-empty" id="os-empty" hidden></div>
    <nav class="os-pager" id="os-pager" aria-label="<?= v2_te('Order pages') ?>" hidden>
      <p class="os-page-info" id="os-page-info"></p>
      <div class="os-pages" id="os-pages"></div>
    </nav>
  </section>
</div>
<?php
v2_org_end();
include __DIR__ . '/../includes/v2/foot.php';
