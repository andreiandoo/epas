<?php
/**
 * Organizer service orders: /organizator/servicii/comenzi (service-orders.php), v2 design.
 *
 * Inside the v2 organizer shell. Every section of the old page, restyled: breadcrumb, back to services, the figures
 * (total orders, active services, pending, total spent), search with status and type filters, the orders table and
 * the pages. org-service-orders.js reads /organizer/services/orders (every page) and /organizer/services/stats.
 *
 * Fixed on the way: only the first 20 orders were ever loaded (core pages them), so search and pages missed older
 * orders; the figures called /orders/stats, which api.js sent to the order detail; rows led nowhere: each opens its
 * order; totals were "123,00 RON" typed by hand, dates without the Bucharest timezone.
 */

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/api.php';
require_once __DIR__ . '/../includes/nav-helpers.php';
require_once __DIR__ . '/../includes/v2/helpers.php';
require_once __DIR__ . '/../includes/v2/organizer.php';

$pageTitleRaw = v2_t('My orders · Services') . ' · ' . SITE_NAME;
$pageDescription = v2_t('The history of extra service orders of an operator on Viaqui.');
$canonicalUrl = SITE_URL . '/organizator/servicii/comenzi';
$noindex = true;
$skipPageCache = true;

$v2Styles = ['organizer.css', 'org-service-orders.css'];
$v2Scripts = ['organizer.js', 'org-service-orders.js'];
$v2LegacyScripts = ['assets/js/config.js', 'assets/js/utils.js', 'assets/js/api.js', 'assets/js/auth.js'];
$v2HeadExtra = v2_account_client_config('organizer');

$sqStat = function (string $key, string $label, string $icon) {
    return '<article class="sq-stat"><span class="sq-stat-ic">' . v2_ic($icon) . '</span><div><p class="sq-stat-k">' . $label . '</p><p class="sq-stat-v" id="sq-s-' . $key . '"><span class="org-skel sq-sk"></span></p></div></article>';
};

include __DIR__ . '/../includes/v2/head.php';
v2_org_start('services');
?>
<div class="sq" id="sq">
  <header class="sq-head">
    <div>
      <nav class="sq-crumbs" aria-label="<?= v2_te('Breadcrumb') ?>"><a href="/organizator/servicii"><?= v2_te('Extra services') ?></a><span aria-hidden="true">›</span><span aria-current="page"><?= v2_te('My orders') ?></span></nav>
      <h1 class="sq-h"><?= v2_te('My orders') ?></h1>
      <p class="sq-lead"><?= v2_te('The history of your extra service orders.') ?></p>
    </div>
    <a class="btn btn-ghost" href="/organizator/servicii"><?= v2_ic('arrow-left') ?><?= v2_te('Back to services') ?></a>
  </header>

  <section class="sq-stats" aria-label="<?= v2_te('At a glance') ?>">
    <?= $sqStat('total', v2_te('Total orders'), 'receipt') ?>
    <?= $sqStat('active', v2_te('Active services'), 'chart-line-up') ?>
    <?= $sqStat('pending', v2_te('Pending'), 'clock') ?>
    <?= $sqStat('spent', v2_te('Total spent'), 'bank') ?>
  </section>

  <section class="org-panel" aria-labelledby="sq-list-h">
    <h2 class="sq-sr" id="sq-list-h"><?= v2_te('Orders') ?></h2>
    <div class="sq-filters">
      <label class="sq-search"><?= v2_ic('magnifying-glass') ?><input id="sq-q" type="search" autocomplete="off" placeholder="<?= v2_te('Search by order number or experience…') ?>" aria-label="<?= v2_te('Search orders') ?>" aria-controls="sq-rows"></label>
      <span class="sq-select"><select id="sq-status" aria-label="<?= v2_te('Status') ?>"><option value=""><?= v2_te('All statuses') ?></option><option value="pending_payment"><?= v2_te('Awaiting payment') ?></option><option value="processing"><?= v2_te('Processing') ?></option><option value="active"><?= v2_te('Active') ?></option><option value="completed"><?= v2_te('Completed') ?></option><option value="cancelled"><?= v2_te('Cancelled') ?></option></select><?= v2_ic('caret-down') ?></span>
      <span class="sq-select"><select id="sq-type" aria-label="<?= v2_te('Service type') ?>"><option value=""><?= v2_te('All types') ?></option><option value="featuring"><?= v2_te('Experience promotion') ?></option><option value="location_featuring"><?= v2_te('Venue promotion') ?></option><option value="email"><?= v2_te('Email marketing') ?></option><option value="tracking"><?= v2_te('Ad tracking') ?></option><option value="campaign"><?= v2_te('Campaign creation') ?></option></select><?= v2_ic('caret-down') ?></span>
    </div>
    <div class="sq-table-wrap"><table class="sq-table">
      <thead><tr><th scope="col"><?= v2_te('Order') ?></th><th scope="col"><?= v2_te('Service type') ?></th><th scope="col"><?= v2_te('Experience') ?></th><th scope="col"><?= v2_te('Period') ?></th><th scope="col" class="sq-right"><?= v2_te('Total') ?></th><th scope="col"><?= v2_te('Status') ?></th><th scope="col" class="sq-right"><?= v2_te('Date') ?></th></tr></thead>
      <tbody id="sq-rows"><tr><td colspan="7" class="sq-state"><?= v2_te('Loading…') ?></td></tr></tbody>
    </table></div>
    <nav class="sq-pages" aria-label="<?= v2_te('Pages') ?>">
      <p id="sq-info" aria-live="polite"><?= v2_te('Loading…') ?></p>
      <div><button class="sq-pill" type="button" id="sq-prev"><?= v2_ic('arrow-left') ?><?= v2_te('Previous') ?></button><button class="sq-pill" type="button" id="sq-next"><?= v2_te('Next') ?><?= v2_ic('arrow-right') ?></button></div>
    </nav>
  </section>
</div>
<?php
v2_org_end();
include __DIR__ . '/../includes/v2/foot.php';
