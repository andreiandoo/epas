<?php
/**
 * Venue payouts: /organizator/locatie/deconturi (venue-payouts.php), v2 design.
 *
 * Seventh screen of the "Locație" section, ported from Ambilet. A venue is paid in two directions at once: online
 * sales are collected by bilete.online, which owes the venue their net, while the counter takes cash and card itself
 * and owes the POS commission. The page shows the running totals, the settlement of a half-month (who pays whom, and
 * per issuing company), and every payout issued, grouped by period, each with its tickets, its invoices and their
 * lines, and the PDFs.
 *
 * org-venue-payouts.js reads /organizer/events, then .../leisure/settlement/cumulative, .../leisure/settlement and
 * .../leisure/payouts.
 */

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/api.php';
require_once __DIR__ . '/../includes/nav-helpers.php';
require_once __DIR__ . '/../includes/v2/helpers.php';
require_once __DIR__ . '/../includes/v2/organizer.php';

$pageTitleRaw = 'Deconturi — Locație — ' . SITE_NAME;
$pageDescription = 'Deconturile locației: compensarea dintre vânzările online și cele de la casă, deconturile emise și facturile lor.';
$canonicalUrl = SITE_URL . '/organizator/locatie/deconturi';
$noindex = true;
$skipPageCache = true;

$v2Styles = ['organizer.css', 'org-venue.css'];
$v2Scripts = ['organizer.js', 'org-venue-payouts.js'];
$v2LegacyScripts = ['assets/js/config.js', 'assets/js/utils.js', 'assets/js/api.js', 'assets/js/auth.js'];
$v2HeadExtra = v2_account_client_config('organizer');

include __DIR__ . '/../includes/v2/head.php';
v2_org_start('venue-payouts');
?>
<div class="ve" id="ve">
  <header class="ve-head">
    <div>
      <p class="ve-eyebrow"><?= v2_ic('wallet') ?>Locație · Deconturi</p>
      <h1 class="ve-h">Deconturi</h1>
      <p class="ve-lead">Online încasează bilete.online, la casă încasezi tu. Aici vezi cine cui datorează și toate deconturile emise.</p>
    </div>
    <div class="ve-head-tools">
      <span class="po-field"><label for="ve-event">Locația</label><span class="po-select"><select id="ve-event" disabled><option>Se încarcă…</option></select><?= v2_ic('caret-down') ?></span></span>
      <button class="btn btn-ghost" type="button" id="vd-refresh"><?= v2_ic('arrow-counter-clockwise') ?>Reîmprospătează</button>
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
    <b>Nu am putut încărca deconturile</b>
    <p>Reîncearcă în câteva secunde.</p>
    <button class="btn btn-primary" type="button" id="ve-retry">Reîncearcă</button>
  </div>

  <div class="ve-main" id="ve-main" hidden>
    <section class="ve-kpis ve-kpis-5" aria-label="Cumulat">
      <article class="ve-kpi"><span class="ve-kpi-ic"><?= v2_ic('calendar-blank') ?></span><div><b id="vd-days">—</b><p>Zile de vânzări</p><small id="vd-since">—</small></div></article>
      <article class="ve-kpi"><span class="ve-kpi-ic"><?= v2_ic('globe-simple') ?></span><div><b id="vd-online">—</b><p>Vânzări online</p></div></article>
      <article class="ve-kpi"><span class="ve-kpi-ic"><?= v2_ic('coins') ?></span><div><b id="vd-pos">—</b><p>Vânzări la casă</p></div></article>
      <article class="ve-kpi"><span class="ve-kpi-ic"><?= v2_ic('percent') ?></span><div><b id="vd-online-comm">—</b><p>Comisioane online</p></div></article>
      <article class="ve-kpi"><span class="ve-kpi-ic"><?= v2_ic('percent') ?></span><div><b id="vd-pos-comm">—</b><p>Comisioane la casă</p></div></article>
    </section>

    <section class="org-panel" aria-labelledby="vd-settle-h">
      <div class="org-panel-head">
        <div><h2 class="org-panel-h" id="vd-settle-h">Compensarea pe perioadă</h2>
        <p class="org-panel-p">Netul online datorat locației se compensează cu comisionul datorat pentru vânzările de la casă.</p></div>
        <span class="po-field"><label for="vd-period">Perioada</label><span class="po-select"><select id="vd-period"></select><?= v2_ic('caret-down') ?></span></span>
      </div>
      <div id="vd-settle"><p class="ve-state">Se încarcă…</p></div>
    </section>

    <section class="org-panel" aria-labelledby="vd-list-h">
      <div class="org-panel-head">
        <div><h2 class="org-panel-h" id="vd-list-h">Deconturile emise</h2>
        <p class="org-panel-p">Pe perioade. Deschide o perioadă ca să vezi deconturile și facturile ei, apoi un decont pentru biletele lui.</p></div>
      </div>
      <div class="ve-periods" id="vd-list"><p class="ve-state">Se încarcă…</p></div>
    </section>
  </div>
</div>
<?php
v2_org_end();
include __DIR__ . '/../includes/v2/foot.php';
