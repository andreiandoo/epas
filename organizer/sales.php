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

$pageTitleRaw = 'Vânzări — ' . SITE_NAME;
$pageDescription = 'Vânzările tale pe bilete.online: comenzi, bilete, venituri pe perioadă și pe activitate, cu export CSV.';
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
      <p class="org-k">Rapoarte</p>
      <h1 class="os-h" id="os-h">Vânzări</h1>
      <p class="os-lead">Toate comenzile și rezervările tale într-un singur loc.</p>
    </div>
    <button class="btn btn-ghost" type="button" id="os-export"><?= v2_ic('file-text') ?><span data-label>Export CSV</span></button>
  </header>

  <section class="org-panel os-filters" aria-labelledby="os-f-h">
    <h2 class="sr" id="os-f-h">Filtre</h2>
    <div class="os-f-top">
      <div class="os-seg" role="group" aria-label="Cum alegi perioada">
        <button class="os-seg-b" type="button" data-mode="range" aria-pressed="true">Perioadă</button>
        <button class="os-seg-b" type="button" data-mode="month" aria-pressed="false">Pe lună</button>
      </div>
      <label class="os-switch"><input type="checkbox" id="os-breakdown"><span class="os-switch-ui" aria-hidden="true"></span><span>Defalcat pe activități</span></label>
      <button class="os-reset" type="button" id="os-reset" hidden><?= v2_ic('x') ?>Resetează filtrele</button>
    </div>
    <div class="os-f-grid">
      <label class="os-f is-event"><span class="os-f-l">Activitate</span><span class="os-select"><select id="os-event"><option value="">Toate activitățile</option></select><?= v2_ic('caret-down') ?></span></label>
      <label class="os-f is-status"><span class="os-f-l">Status</span><span class="os-select"><select id="os-status">
        <option value="">Toate statusurile</option>
        <option value="completed" selected>Finalizate</option>
        <option value="pending">În așteptare</option>
        <option value="failed">Eșuate</option>
        <option value="expired">Expirate</option>
        <option value="cancelled">Anulate</option>
        <option value="refunded">Rambursate</option>
      </select><?= v2_ic('caret-down') ?></span></label>
      <label class="os-f" data-for-mode="range"><span class="os-f-l">De la data</span><input type="date" id="os-from" aria-describedby="os-f-err"></label>
      <label class="os-f" data-for-mode="range"><span class="os-f-l">Până la data</span><input type="date" id="os-to" aria-describedby="os-f-err"></label>
      <label class="os-f is-month" data-for-mode="month" hidden><span class="os-f-l">Luna</span><input type="month" id="os-month"></label>
      <label class="os-f is-search"><span class="os-f-l">Caută</span><span class="os-input"><?= v2_ic('magnifying-glass') ?><input type="search" id="os-q" maxlength="100" autocomplete="off" spellcheck="false" placeholder="Nume, email, comandă…"></span></label>
    </div>
    <p class="os-f-err" id="os-f-err" role="alert" hidden></p>
  </section>

  <section class="os-stats" aria-labelledby="os-stats-h">
    <h2 class="sr" id="os-stats-h">Sumar pentru filtrele alese</h2>
    <article class="os-stat is-mint"><p class="os-stat-k">Comenzi finalizate</p><p class="os-stat-v" id="os-s-orders"><span class="org-skel os-sk"></span></p></article>
    <article class="os-stat"><p class="os-stat-k">Bilete / rezervări</p><p class="os-stat-v" id="os-s-tickets"><span class="org-skel os-sk"></span></p></article>
    <article class="os-stat is-warm"><p class="os-stat-k">Venituri nete</p><p class="os-stat-v" id="os-s-net"><span class="org-skel os-sk"></span></p><p class="os-stat-p">Prețul biletelor, după reduceri, fără comision</p></article>
    <article class="os-stat"><p class="os-stat-k">Încasări brute</p><p class="os-stat-v" id="os-s-gross"><span class="org-skel os-sk"></span></p><p class="os-stat-p">Totalul plătit de clienți pe comenzile finalizate</p></article>
  </section>

  <section class="org-panel os-bd" id="os-bd" aria-labelledby="os-bd-h" hidden>
    <div class="org-panel-head">
      <div><p class="org-k">Pe activități</p><h2 class="org-panel-h" id="os-bd-h">Defalcare pe activități</h2><p class="org-panel-p" id="os-bd-period"></p></div>
      <p class="os-bd-progress" id="os-bd-progress" role="status"></p>
    </div>
    <div class="os-table-wrap">
      <table class="os-table os-bd-table">
        <caption class="sr">Comenzi, bilete și venituri nete pentru fiecare activitate</caption>
        <thead><tr><th scope="col">Activitate</th><th scope="col" class="is-num">Comenzi</th><th scope="col" class="is-num">Bilete</th><th scope="col" class="is-num">Venituri nete</th></tr></thead>
        <tbody id="os-bd-body"></tbody>
        <tfoot id="os-bd-foot"></tfoot>
      </table>
    </div>
  </section>

  <section class="org-panel os-orders" aria-labelledby="os-o-h">
    <div class="org-panel-head">
      <div><p class="org-k">Comenzi</p><h2 class="org-panel-h" id="os-o-h" tabindex="-1">Lista comenzilor</h2><p class="org-panel-p" id="os-o-count">Se încarcă…</p></div>
      <label class="os-f os-sort-m"><span class="os-f-l">Sortează</span><span class="os-select"><select id="os-sort-m">
        <option value="created_at:desc">Cele mai noi</option>
        <option value="created_at:asc">Cele mai vechi</option>
        <option value="total:desc">Valoare, descrescător</option>
        <option value="total:asc">Valoare, crescător</option>
        <option value="order_number:asc">Număr comandă</option>
        <option value="customer_name:asc">Participant, A–Z</option>
        <option value="status:asc">Status</option>
        <option value="source:asc">Sursă</option>
      </select><?= v2_ic('caret-down') ?></span></label>
    </div>
    <div class="os-mix" id="os-mix" hidden><p class="os-mix-k">Comenzi pe statusuri</p><div class="os-mix-list" id="os-mix-list"></div></div>
    <p class="sr" id="os-live" aria-live="polite"></p>
    <div class="os-table-wrap is-orders" id="os-orders-wrap">
      <table class="os-table os-o-table" id="os-table">
        <caption class="sr">Comenzile pentru filtrele alese</caption>
        <thead>
          <tr>
            <?= $osSortTh('order_number', 'Comandă') ?>
            <?= $osSortTh('customer_name', 'Participant') ?>
            <th scope="col">Bilete</th>
            <?= $osSortTh('total', 'Valoare', 'is-num') ?>
            <?= $osSortTh('status', 'Status') ?>
            <?= $osSortTh('source', 'Sursă') ?>
            <?= $osSortTh('created_at', 'Data') ?>
          </tr>
        </thead>
        <tbody id="os-rows">
          <?php for ($i = 0; $i < 4; $i++): ?><tr class="is-skel" aria-hidden="true"><td colspan="7"><span class="org-skel os-sk-row"></span></td></tr><?php endfor; ?>
        </tbody>
      </table>
    </div>
    <div class="org-empty os-empty" id="os-empty" hidden></div>
    <nav class="os-pager" id="os-pager" aria-label="Paginile comenzilor" hidden>
      <p class="os-page-info" id="os-page-info"></p>
      <div class="os-pages" id="os-pages"></div>
    </nav>
  </section>
</div>
<?php
v2_org_end();
include __DIR__ . '/../includes/v2/foot.php';
