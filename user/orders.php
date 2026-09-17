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

$pageTitleRaw = 'Comenzile mele — ' . SITE_NAME;
$pageDescription = 'Comenzile mele pe bilete.online: bilete, plăți, comisioane, puncte câștigate, documente, retururi.';
$canonicalUrl = SITE_URL . '/cont/comenzi';
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
      <h1 id="od-guard-h">Trebuie să fii autentificat</h1>
      <p>Intră în cont pentru a vedea comenzile.</p>
      <a class="btn btn-primary" href="/autentificare?redirect=%2Fcont%2Fcomenzi">Intră în cont<?= v2_ic('arrow-right') ?></a>
    </section>

    <div class="acc-body" id="od-content">
      <!-- HERO -->
      <section class="acc-hero od-hero" aria-labelledby="od-h">
        <div>
          <p class="acc-kicker">Comenzi client</p>
          <h1 class="acc-h" id="od-h">Comenzile mele</h1>
          <p class="acc-lead">Vezi istoricul complet al comenzilor, statusul plăților, biletele emise, comisioanele, punctele câștigate, documentele și opțiunile de retur.</p>
        </div>
        <dl class="od-kpis" aria-label="Pe scurt">
          <div><dt>Total comenzi</dt><dd id="od-k-orders">0</dd></div>
          <div><dt>Cheltuit</dt><dd id="od-k-spent">0 lei</dd></div>
          <div><dt>Puncte</dt><dd id="od-k-points">0</dd></div>
          <div><dt>Retururi</dt><dd id="od-k-refunds">0</dd></div>
        </dl>
      </section>

      <!-- FILTERS -->
      <section class="acc-panel" aria-label="Filtre comenzi">
        <form class="acc-filter-row" id="od-filters" role="search">
          <label class="acc-field is-search"><span>Caută comandă</span>
            <span class="acc-input"><?= v2_ic('magnifying-glass') ?><input type="search" id="od-q" maxlength="100" placeholder="Număr comandă, activitate, oraș, metodă plată…" autocomplete="off" enterkeyhint="search"></span>
          </label>
          <label class="acc-field"><span>Status</span>
            <span class="acc-select"><select id="od-status">
              <option value="all" selected>Toate</option>
              <option value="confirmed">Confirmate</option>
              <option value="pending">În așteptare</option>
              <option value="refunded">Retur / rambursare</option>
              <option value="failed">Eșuate</option>
            </select><?= v2_ic('caret-down') ?></span>
          </label>
          <label class="acc-field"><span>Perioadă</span>
            <span class="acc-select"><select id="od-period">
              <option value="all" selected>Toate</option>
              <option value="30">Ultimele 30 zile</option>
              <option value="90">Ultimele 90 zile</option>
              <option value="year">Anul curent</option>
            </select><?= v2_ic('caret-down') ?></span>
          </label>
          <label class="acc-field"><span>Sortare</span>
            <span class="acc-select"><select id="od-sort">
              <option value="newest" selected>Cele mai noi</option>
              <option value="oldest">Cele mai vechi</option>
              <option value="value_desc">Valoare desc.</option>
              <option value="value_asc">Valoare asc.</option>
            </select><?= v2_ic('caret-down') ?></span>
          </label>
          <button class="btn btn-ghost acc-reset" type="button" id="od-reset">Reset</button>
        </form>
        <div class="acc-pills" role="group" aria-label="Filtre rapide">
          <button class="acc-pill" type="button" data-status="all" aria-pressed="true">Toate</button>
          <button class="acc-pill is-ok" type="button" data-status="confirmed" aria-pressed="false">Confirmate</button>
          <button class="acc-pill is-warn" type="button" data-status="pending" aria-pressed="false">În așteptare</button>
          <button class="acc-pill is-bad" type="button" data-status="refunded" aria-pressed="false">Retururi</button>
          <button class="acc-pill is-danger" type="button" data-status="failed" aria-pressed="false">Eșuate</button>
        </div>
      </section>

      <!-- ORDERS -->
      <section class="acc-results" id="comenzi" aria-labelledby="od-count">
        <div class="acc-results-head">
          <div><p class="acc-k">Rezultate</p><h2 class="acc-count" id="od-count">0 comenzi</h2></div>
          <div class="acc-bulk">
            <button class="btn btn-ghost" type="button" id="od-csv" disabled>Export CSV</button>
            <button class="btn btn-primary" type="button" id="od-history" disabled>Descarcă istoric</button>
          </div>
        </div>
        <p class="acc-status" id="od-status-line" role="status"></p>
        <div class="acc-skel" id="od-skel" aria-hidden="true"><i></i><i></i></div>
        <ul class="od-list" id="od-list" hidden></ul>
        <div class="acc-empty" id="od-empty" hidden>
          <span class="acc-empty-ic" aria-hidden="true"><?= v2_ic('shopping-cart-simple') ?></span>
          <b id="od-empty-h">Nu ai comenzi încă</b>
          <p>Descoperă activități și rezervă online.</p>
          <a class="btn btn-primary" id="od-empty-cta" href="/categorii">Descoperă activități</a>
          <button class="btn btn-primary" id="od-empty-reset" type="button" hidden>Resetează filtrele</button>
        </div>
        <div class="acc-empty is-error" id="od-error" hidden>
          <b>Nu am putut încărca comenzile.</b>
          <p>Verifică conexiunea și încearcă din nou.</p>
          <button class="btn btn-ghost" type="button" id="od-retry">Încearcă din nou</button>
        </div>
      </section>
    </div>

    <?php v2_account_end(); ?>
  </div>
</main>
<?php include __DIR__ . '/../includes/v2/footer.php'; ?>
