<?php
/**
 * Venue sales: /organizator/locatie/vanzari (venue-sales.php), v2 design.
 *
 * Fifth screen of the "Locație" section, ported from Ambilet: what the venue sold over a period, online and at the
 * counter. Gross, the ticketing commission and what is left, each split by channel; orders, the average basket, the
 * tickets issued and their categories; cash, card and online takings; the sales over time; each ticket type; the
 * cash sessions of the period; and the company invoices asked for at the counter. The per-ticket CSV comes from core.
 * Ambilet sent the sessions and the invoices to two pages of their own; here they are panels of this one.
 *
 * org-venue-sales.js reads /organizer/events, then .../leisure/sales-timeline and .../leisure/sales/summary for the
 * period, .../leisure/invoices for the panel, and downloads .../leisure/sales/range-csv.
 */

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/api.php';
require_once __DIR__ . '/../includes/nav-helpers.php';
require_once __DIR__ . '/../includes/v2/helpers.php';
require_once __DIR__ . '/../includes/v2/organizer.php';

$pageTitleRaw = 'Vânzări — Locație — ' . SITE_NAME;
$pageDescription = 'Vânzările locației pe perioade: brut, comision și net, online și la casă, pe tipuri de bilet și sesiuni de casă.';
$canonicalUrl = SITE_URL . '/organizator/locatie/vanzari';
$noindex = true;
$skipPageCache = true;

$v2Styles = ['organizer.css', 'org-venue.css'];
$v2Scripts = ['organizer.js', 'org-venue-sales.js'];
$v2LegacyScripts = ['assets/js/config.js', 'assets/js/utils.js', 'assets/js/api.js', 'assets/js/auth.js'];
$v2HeadExtra = v2_account_client_config();

$vsSplit = function (string $key) {
    return '<dl class="ve-split"><div><dt>' . v2_ic('globe-simple') . 'Online</dt><dd id="vs-' . $key . '-online">—</dd></div>'
        . '<div><dt>' . v2_ic('coins') . 'La casă</dt><dd id="vs-' . $key . '-pos">—</dd></div></dl>';
};
$vsPay = function (string $key, string $icon, string $label) {
    return '<article class="ve-kpi"><span class="ve-kpi-ic">' . v2_ic($icon) . '</span><div><b id="vs-pay-' . $key . '">—</b><p>' . $label
        . '</p><small><span id="vs-pay-' . $key . '-pct">—</span> din încasări</small></div></article>';
};

include __DIR__ . '/../includes/v2/head.php';
v2_org_start('venue-sales');
?>
<div class="ve" id="ve">
  <header class="ve-head">
    <div>
      <p class="ve-eyebrow"><?= v2_ic('chart-line-up') ?>Locație · Vânzări</p>
      <h1 class="ve-h">Vânzări</h1>
      <p class="ve-lead">Ce a vândut locația într-o perioadă, pe site și la casă: cât a intrat, cât rămâne după comision și din ce.</p>
    </div>
    <div class="ve-head-tools">
      <span class="po-field"><label for="ve-event">Locația</label><span class="po-select"><select id="ve-event" disabled><option>Se încarcă…</option></select><?= v2_ic('caret-down') ?></span></span>
    </div>
  </header>

  <div class="org-empty" id="ve-none" hidden>
    <span class="org-empty-ic"><?= v2_ic('door-open') ?></span>
    <b>Nicio locație pregătită</b>
    <p>Paginile de locație funcționează pe o activitate configurată ca locație de agrement.</p>
    <a class="btn btn-primary" href="/organizator/suport">Cere activarea<?= v2_ic('arrow-right') ?></a>
  </div>

  <div class="org-empty is-error" id="ve-failed" hidden>
    <span class="org-empty-ic"><?= v2_ic('warning-circle') ?></span>
    <b>Nu am putut încărca vânzările</b>
    <p>Reîncearcă în câteva secunde.</p>
    <button class="btn btn-primary" type="button" id="ve-retry">Reîncearcă</button>
  </div>

  <div class="ve-main" id="ve-main" hidden>
    <section class="org-panel" aria-labelledby="vs-filters-h">
      <h2 class="ve-sr" id="vs-filters-h">Perioada</h2>
      <div class="ve-filters">
        <div class="ve-ranges" role="group" aria-label="Perioada plății">
          <?php foreach ([['7', 'Ultimele 7 zile'], ['14', '14 zile'], ['30', 'O lună'], ['90', '3 luni'], ['180', '6 luni'], ['custom', 'Altă perioadă']] as [$val, $label]): ?>
          <button class="ve-range" type="button" data-range="<?= $val ?>" aria-pressed="<?= $val === '7' ? 'true' : 'false' ?>"><?= $label ?></button>
          <?php endforeach; ?>
        </div>
        <div class="ve-dates" id="vs-custom" hidden>
          <span class="po-field"><label for="vs-from">De la</label><input class="po-input" type="date" id="vs-from"></span>
          <span class="po-field"><label for="vs-to">Până la</label><input class="po-input" type="date" id="vs-to"></span>
          <button class="btn btn-ghost" type="button" id="vs-apply">Aplică</button>
        </div>
        <div class="ve-tools">
          <span class="po-select"><select id="vs-group" aria-label="Graficul pe"><option value="day">Graficul pe zile</option><option value="week">Graficul pe săptămâni</option><option value="month">Graficul pe luni</option></select><?= v2_ic('caret-down') ?></span>
          <button class="btn btn-ghost" type="button" id="vs-csv"><?= v2_ic('download-simple') ?>Export CSV pe bilete</button>
          <span class="ve-sub" id="vs-period">—</span>
        </div>
      </div>
    </section>

    <section class="ve-money" aria-label="Bani">
      <article class="ve-mcard is-gross"><p>Total vândut</p><b id="vs-rev">—</b><?= $vsSplit('rev') ?></article>
      <article class="ve-mcard is-fee"><p>Comision ticketing</p><b id="vs-comm">—</b><?= $vsSplit('comm') ?></article>
      <article class="ve-mcard is-net"><p>Rămâne după comision</p><b id="vs-net">—</b><?= $vsSplit('net') ?></article>
    </section>

    <section class="ve-kpis is-4" aria-label="Comenzi și bilete">
      <article class="ve-kpi"><span class="ve-kpi-ic"><?= v2_ic('receipt') ?></span><div><b id="vs-orders">—</b><p>Comenzi plătite</p></div></article>
      <article class="ve-kpi"><span class="ve-kpi-ic"><?= v2_ic('shopping-cart-simple') ?></span><div><b id="vs-avg">—</b><p>Coș mediu</p></div></article>
      <article class="ve-kpi"><span class="ve-kpi-ic"><?= v2_ic('ticket') ?></span><div><b id="vs-physical">—</b><p>Bilete emise</p><small><span id="vs-transactions">—</span> cu valoare (un pachet = una)</small></div></article>
      <article class="ve-kpi is-wide"><div><p>Pe categorii</p><ul class="ve-catlist" id="vs-cats"><li><span>—</span></li></ul></div></article>
    </section>

    <section class="ve-kpis is-4" aria-label="Cum s-a plătit">
      <?= $vsPay('cash', 'coins', 'Numerar la casă') ?>
      <?= $vsPay('card', 'credit-card', 'Card la casă') ?>
      <?= $vsPay('online', 'globe-simple', 'Online') ?>
      <article class="ve-kpi"><span class="ve-kpi-ic"><?= v2_ic('clock') ?></span><div><b id="vs-sessions-n">—</b><p>Sesiuni de casă</p><small><a href="#vs-sessions-h">vezi turele</a></small></div></article>
    </section>

    <div class="ve-grid">
      <section class="org-panel" aria-labelledby="vs-chart-h">
        <div class="org-panel-head">
          <div><h2 class="org-panel-h" id="vs-chart-h">Vânzări în timp</h2><p class="org-panel-p">Linia arată încasările, coloanele biletele cu valoare.</p></div>
          <p class="ve-legend"><span class="ve-lg">Încasări</span><span class="ve-lg is-vis">Bilete</span></p>
        </div>
        <div class="ve-chart" id="vs-chart"></div>
        <p class="ve-state" id="vs-chart-empty" hidden>Nicio vânzare în perioada aleasă.</p>
      </section>
      <section class="org-panel" aria-labelledby="vs-types-h">
        <div class="org-panel-head"><div><h2 class="org-panel-h" id="vs-types-h">Pe tip de bilet</h2><p class="org-panel-p">Bilete cu valoare și încasări.</p></div></div>
        <ul class="ve-types-bars" id="vs-types"><li class="ve-state">Se încarcă…</li></ul>
      </section>
    </div>

    <section class="org-panel" aria-labelledby="vs-sessions-h">
      <div class="org-panel-head"><div><h2 class="org-panel-h" id="vs-sessions-h">Sesiunile de casă</h2><p class="org-panel-p">Turele deschise sau închise în perioadă, cu ce a încasat fiecare.</p></div></div>
      <div class="ve-table-wrap"><table class="ve-table ve-sessions-table">
        <thead><tr>
          <th scope="col">Casier</th><th scope="col">Deschisă</th><th scope="col">Închisă</th>
          <th scope="col" class="ve-r">Numerar</th><th scope="col" class="ve-r">Card</th><th scope="col" class="ve-r">Comenzi</th>
          <th scope="col" class="ve-r">Bilete</th><th scope="col" class="ve-r">Încasări</th>
        </tr></thead>
        <tbody id="vs-sessions"><tr><td colspan="8" class="ve-state">Se încarcă…</td></tr></tbody>
      </table></div>
    </section>

    <section class="org-panel ve-hist" aria-labelledby="vs-inv-h">
      <div class="org-panel-head">
        <div><h2 class="org-panel-h" id="vs-inv-h"><?= v2_ic('buildings') ?> Facturi pe firmă</h2>
        <p class="org-panel-p">Comenzile de la casă pentru care s-a cerut sau s-a emis factură, în aceeași perioadă.</p></div>
        <button class="btn btn-ghost ve-hist-btn" type="button" id="vs-inv-btn" aria-expanded="false" aria-controls="vs-inv-body">Arată facturile<?= v2_ic('caret-down') ?></button>
      </div>
      <div id="vs-inv-body" hidden>
        <div class="ve-hist-row">
          <label class="ve-search"><?= v2_ic('magnifying-glass') ?><input id="vs-inv-q" type="search" autocomplete="off" placeholder="Firmă, CUI, nr. factură sau comandă" aria-label="Caută în facturi"></label>
        </div>
        <div class="ve-table-wrap"><table class="ve-table ve-inv-table">
          <thead><tr>
            <th scope="col">Comandă</th><th scope="col">Factura</th><th scope="col">Firma</th><th scope="col">Contact</th>
            <th scope="col">Plată</th><th scope="col" class="ve-r">Bilete</th><th scope="col" class="ve-r">Total</th>
          </tr></thead>
          <tbody id="vs-inv"><tr><td colspan="7" class="ve-state">Se încarcă…</td></tr></tbody>
        </table></div>
        <div class="ve-pager">
          <span id="vs-inv-count"></span>
          <span class="ve-pg">
            <button type="button" id="vs-inv-prev" disabled><?= v2_ic('arrow-left') ?>Anterioare</button>
            <button type="button" id="vs-inv-next" disabled>Următoare<?= v2_ic('arrow-right') ?></button>
          </span>
        </div>
      </div>
    </section>
  </div>
</div>
<?php
v2_org_end();
include __DIR__ . '/../includes/v2/foot.php';
