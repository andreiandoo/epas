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

$pageTitleRaw = 'Comenzile mele — Servicii — ' . SITE_NAME;
$pageDescription = 'Istoricul comenzilor de servicii extra ale unui organizator pe bilete.online.';
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
      <nav class="sq-crumbs" aria-label="Breadcrumb"><a href="/organizator/servicii">Servicii extra</a><span aria-hidden="true">›</span><span aria-current="page">Comenzile mele</span></nav>
      <h1 class="sq-h">Comenzile mele</h1>
      <p class="sq-lead">Istoric comenzi servicii extra.</p>
    </div>
    <a class="btn btn-ghost" href="/organizator/servicii"><?= v2_ic('arrow-left') ?>Înapoi la servicii</a>
  </header>

  <section class="sq-stats" aria-label="Pe scurt">
    <?= $sqStat('total', 'Total comenzi', 'receipt') ?>
    <?= $sqStat('active', 'Servicii active', 'chart-line-up') ?>
    <?= $sqStat('pending', 'În așteptare', 'clock') ?>
    <?= $sqStat('spent', 'Investit total', 'bank') ?>
  </section>

  <section class="org-panel" aria-labelledby="sq-list-h">
    <h2 class="sq-sr" id="sq-list-h">Comenzi</h2>
    <div class="sq-filters">
      <label class="sq-search"><?= v2_ic('magnifying-glass') ?><input id="sq-q" type="search" autocomplete="off" placeholder="Caută după număr comandă sau activitate…" aria-label="Caută comenzi" aria-controls="sq-rows"></label>
      <span class="sq-select"><select id="sq-status" aria-label="Status"><option value="">Toate statusurile</option><option value="pending_payment">Așteaptă plata</option><option value="processing">În procesare</option><option value="active">Activ</option><option value="completed">Finalizat</option><option value="cancelled">Anulat</option></select><?= v2_ic('caret-down') ?></span>
      <span class="sq-select"><select id="sq-type" aria-label="Tip serviciu"><option value="">Toate tipurile</option><option value="featuring">Promovare</option><option value="email">Email marketing</option><option value="tracking">Ad tracking</option><option value="campaign">Creare campanie</option></select><?= v2_ic('caret-down') ?></span>
    </div>
    <div class="sq-table-wrap"><table class="sq-table">
      <thead><tr><th scope="col">Comandă</th><th scope="col">Tip serviciu</th><th scope="col">Activitate</th><th scope="col">Perioadă</th><th scope="col" class="sq-right">Total</th><th scope="col">Status</th><th scope="col" class="sq-right">Data</th></tr></thead>
      <tbody id="sq-rows"><tr><td colspan="7" class="sq-state">Se încarcă…</td></tr></tbody>
    </table></div>
    <nav class="sq-pages" aria-label="Pagini">
      <p id="sq-info" aria-live="polite">Se încarcă…</p>
      <div><button class="sq-pill" type="button" id="sq-prev"><?= v2_ic('arrow-left') ?>Înapoi</button><button class="sq-pill" type="button" id="sq-next">Înainte<?= v2_ic('arrow-right') ?></button></div>
    </nav>
  </section>
</div>
<?php
v2_org_end();
include __DIR__ . '/../includes/v2/foot.php';
