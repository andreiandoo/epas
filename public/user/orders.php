<?php
/**
 * Customer orders: /cont/comenzi and /cont/comenzile-mele (v2 design).
 *
 * Inside the v2 account shell (includes/v2/account.php). Sections: hero with four counters (orders, spent, points,
 * refunds), filters (search, status, period, sort, quick filters), the orders with their cost breakdown and actions,
 * the details of an order (tickets, history, financial summary), empty and error states, and the login prompt.
 * orders.js loads every page of GET /customer/orders, an order's details when it is opened (GET /customer/orders/{id}),
 * and the points earned (GET /customer/rewards). A #<order number> hash opens that order (the tickets page links so).
 *
 * Fixed on the way: "Confirmare PDF" and "Factură" called proxy actions that don't exist, "Cere retur" opened a page
 * that doesn't exist, "Retrimite email" posted to an endpoint core doesn't have, the fee boxes read fields the API never
 * sends (always 0 lei), the period filter parsed an already formatted date (so it matched nothing), and the ticket list
 * never loaded. Now: the order's tickets PDF, the contact form for an invoice / a refund / a lost email, the service fee
 * and ticket protection worked out from the order totals, and the tickets from the order details.
 */

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/api.php';
require_once __DIR__ . '/../includes/nav-helpers.php';
require_once __DIR__ . '/../includes/v2/helpers.php';
require_once __DIR__ . '/../includes/v2/nav.php';
require_once __DIR__ . '/../includes/v2/account.php';

$pageTitleRaw = v2_t('My orders: {site}', ['site' => SITE_NAME]);
$pageDescription = v2_t('Your orders on Viaqui: tickets, payments, fees, points earned, documents and refunds.');
$canonicalUrl = SITE_URL . '/account/orders';
$noindex = true;
$skipPageCache = true;

$v2Styles = ['account.css', 'orders.css'];
$v2Scripts = ['account.js', 'orders.js'];
$v2LegacyScripts = ['assets/js/config.js', 'assets/js/utils.js', 'assets/js/api.js', 'assets/js/auth.js'];
$v2HeadExtra = v2_account_client_config();
$v2FooterCompact = true;

include __DIR__ . '/../includes/v2/head.php';
include __DIR__ . '/../includes/v2/header.php';
?>
<main id="main" tabindex="-1" class="page-main acc-page">
  <div class="wrap">
    <?php v2_account_start('orders'); ?>

    <!-- not signed in -->
    <section class="acc-guard" id="od-guard" hidden aria-labelledby="od-guard-h">
      <span class="acc-guard-ic" aria-hidden="true"><?= v2_ic('lock-simple') ?></span>
      <h1 id="od-guard-h"><?= v2_te('You need to sign in') ?></h1>
      <p><?= v2_te('Sign in to see your orders.') ?></p>
      <a class="btn btn-primary" href="/login?redirect=%2Faccount%2Forders"><?= v2_te('Sign in') ?><?= v2_ic('arrow-right') ?></a>
    </section>

    <div class="acc-body" id="od-content">
      <!-- HERO -->
      <section class="acc-hero od-hero" aria-labelledby="od-h">
        <div>
          <p class="acc-kicker"><?= v2_te('Your orders') ?></p>
          <h1 class="acc-h" id="od-h"><?= v2_te('My orders') ?></h1>
          <p class="acc-lead"><?= v2_te('See your full order history, payment status, issued tickets, fees, points earned, documents and refund options.') ?></p>
        </div>
        <dl class="od-kpis" aria-label="<?= v2_te('At a glance') ?>">
          <div><dt><?= v2_te('Total orders') ?></dt><dd id="od-k-orders">0</dd></div>
          <div><dt><?= v2_te('Spent') ?></dt><dd id="od-k-spent"><?= v2_e(v2_money(0)) ?></dd></div>
          <div><dt><?= v2_te('Points') ?></dt><dd id="od-k-points">0</dd></div>
          <div><dt><?= v2_te('Refunds') ?></dt><dd id="od-k-refunds">0</dd></div>
        </dl>
      </section>

      <!-- FILTERS -->
      <section class="acc-panel" aria-label="<?= v2_te('Order filters') ?>">
        <form class="acc-filter-row" id="od-filters" role="search">
          <label class="acc-field is-search"><span><?= v2_te('Search orders') ?></span>
            <span class="acc-input"><?= v2_ic('magnifying-glass') ?><input type="search" id="od-q" maxlength="100" placeholder="<?= v2_te('Order number, activity, city, payment method…') ?>" autocomplete="off" enterkeyhint="search"></span>
          </label>
          <label class="acc-field"><span><?= v2_te('Status') ?></span>
            <span class="acc-select"><select id="od-status">
              <option value="all" selected><?= v2_te('All') ?></option>
              <option value="confirmed"><?= v2_te('Confirmed') ?></option>
              <option value="pending"><?= v2_te('Pending') ?></option>
              <option value="refunded"><?= v2_te('Returned or refunded') ?></option>
              <option value="failed"><?= v2_te('Failed') ?></option>
            </select><?= v2_ic('caret-down') ?></span>
          </label>
          <label class="acc-field"><span><?= v2_te('Period') ?></span>
            <span class="acc-select"><select id="od-period">
              <option value="all" selected><?= v2_te('All') ?></option>
              <option value="30"><?= v2_te('Last 30 days') ?></option>
              <option value="90"><?= v2_te('Last 90 days') ?></option>
              <option value="year"><?= v2_te('This year') ?></option>
            </select><?= v2_ic('caret-down') ?></span>
          </label>
          <label class="acc-field"><span><?= v2_te('Sort by') ?></span>
            <span class="acc-select"><select id="od-sort">
              <option value="newest" selected><?= v2_te('Newest first') ?></option>
              <option value="oldest"><?= v2_te('Oldest first') ?></option>
              <option value="value_desc"><?= v2_te('Highest value') ?></option>
              <option value="value_asc"><?= v2_te('Lowest value') ?></option>
            </select><?= v2_ic('caret-down') ?></span>
          </label>
          <button class="btn btn-ghost acc-reset" type="button" id="od-reset"><?= v2_te('Reset') ?></button>
        </form>
        <div class="acc-pills" role="group" aria-label="<?= v2_te('Quick filters') ?>">
          <button class="acc-pill" type="button" data-status="all" aria-pressed="true"><?= v2_te('All') ?></button>
          <button class="acc-pill is-ok" type="button" data-status="confirmed" aria-pressed="false"><?= v2_te('Confirmed') ?></button>
          <button class="acc-pill is-warn" type="button" data-status="pending" aria-pressed="false"><?= v2_te('Pending') ?></button>
          <button class="acc-pill is-bad" type="button" data-status="refunded" aria-pressed="false"><?= v2_te('Refunds') ?></button>
          <button class="acc-pill is-danger" type="button" data-status="failed" aria-pressed="false"><?= v2_te('Failed') ?></button>
        </div>
      </section>

      <!-- ORDERS -->
      <section class="acc-results" id="comenzi" aria-labelledby="od-count">
        <div class="acc-results-head">
          <div><p class="acc-k"><?= v2_te('Results') ?></p><h2 class="acc-count" id="od-count"><?= v2_e(v2_num(0, 'order', 'orders')) ?></h2></div>
          <div class="acc-bulk">
            <button class="btn btn-ghost" type="button" id="od-csv" disabled><?= v2_te('Export CSV') ?></button>
            <button class="btn btn-primary" type="button" id="od-history" disabled><?= v2_te('Download history') ?></button>
          </div>
        </div>
        <p class="acc-status" id="od-status-line" role="status"></p>
        <div class="acc-skel" id="od-skel" aria-hidden="true"><i></i><i></i></div>
        <ul class="od-list" id="od-list" hidden></ul>
        <div class="acc-empty" id="od-empty" hidden>
          <span class="acc-empty-ic" aria-hidden="true"><?= v2_ic('shopping-cart-simple') ?></span>
          <b id="od-empty-h"><?= v2_te('You have no orders yet') ?></b>
          <p><?= v2_te('Find things to do and book online.') ?></p>
          <a class="btn btn-primary" id="od-empty-cta" href="/categories"><?= v2_te('Find things to do') ?></a>
          <button class="btn btn-primary" id="od-empty-reset" type="button" hidden><?= v2_te('Reset filters') ?></button>
        </div>
        <div class="acc-empty is-error" id="od-error" hidden>
          <b><?= v2_te('We could not load your orders.') ?></b>
          <p><?= v2_te('Check your connection and try again.') ?></p>
          <button class="btn btn-ghost" type="button" id="od-retry"><?= v2_te('Try again') ?></button>
        </div>
      </section>
    </div>

    <?php v2_account_end(); ?>
  </div>
</main>
<?php include __DIR__ . '/../includes/v2/footer.php'; ?>
