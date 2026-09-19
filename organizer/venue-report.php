<?php
/**
 * Venue report: /organizator/locatie/raport (venue-report.php), v2 design.
 *
 * Sixth screen of the "Locație" section, ported from Ambilet: the accounting view of a period. The totals and the
 * commission formula in force; what each issuing company took (gross, net, VAT when it pays VAT, commission, by
 * payment method); by payment method; online against the counter; by POS operator; by ticket type with its issuer;
 * the physical tickets issued; and the scans of a thirty-day window, each day openable into every scan it had. The
 * report exports as CSV from what is loaded.
 *
 * org-venue-report.js reads /organizer/events, then .../leisure/raport, .../leisure/scans and .../leisure/scans-detail.
 */

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/api.php';
require_once __DIR__ . '/../includes/nav-helpers.php';
require_once __DIR__ . '/../includes/v2/helpers.php';
require_once __DIR__ . '/../includes/v2/organizer.php';

$pageTitleRaw = 'Raport — Locație — ' . SITE_NAME;
$pageDescription = 'Raportul contabil al locației: pe societăți, metode de plată, operatori, tipuri de bilet și scanări.';
$canonicalUrl = SITE_URL . '/organizator/locatie/raport';
$noindex = true;
$skipPageCache = true;

$v2Styles = ['organizer.css', 'org-venue.css'];
$v2Scripts = ['organizer.js', 'org-venue-report.js'];
$v2LegacyScripts = ['assets/js/config.js', 'assets/js/utils.js', 'assets/js/api.js', 'assets/js/auth.js'];
$v2HeadExtra = v2_account_client_config('organizer');

$vrKpi = function (string $id, string $icon, string $label) {
    return '<article class="ve-kpi"><span class="ve-kpi-ic">' . v2_ic($icon) . '</span><div><b id="vr-' . $id . '">—</b><p>' . $label . '</p></div></article>';
};

include __DIR__ . '/../includes/v2/head.php';
v2_org_start('venue-report');
?>
<div class="ve" id="ve">
  <header class="ve-head">
    <div>
      <p class="ve-eyebrow"><?= v2_ic('file-text') ?>Locație · Raport</p>
      <h1 class="ve-h">Raport</h1>
      <p class="ve-lead">Perioada pe societăți, metode de plată, operatori și tipuri de bilet, plus scanările de la porți.</p>
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
    <b>Nu am putut încărca raportul</b>
    <p>Reîncearcă în câteva secunde.</p>
    <button class="btn btn-primary" type="button" id="ve-retry">Reîncearcă</button>
  </div>

  <div class="ve-main" id="ve-main" hidden>
    <section class="org-panel" aria-labelledby="vr-filters-h">
      <h2 class="ve-sr" id="vr-filters-h">Perioada</h2>
      <div class="ve-filters">
        <div class="ve-ranges" role="group" aria-label="Perioada plății">
          <?php foreach ([['7', 'Ultimele 7 zile'], ['14', '14 zile'], ['30', 'O lună'], ['90', '3 luni'], ['180', '6 luni'], ['custom', 'Altă perioadă']] as [$val, $label]): ?>
          <button class="ve-range" type="button" data-range="<?= $val ?>" aria-pressed="<?= $val === '30' ? 'true' : 'false' ?>"><?= $label ?></button>
          <?php endforeach; ?>
        </div>
        <div class="ve-dates" id="vr-custom" hidden>
          <span class="po-field"><label for="vr-from">De la</label><input class="po-input" type="date" id="vr-from"></span>
          <span class="po-field"><label for="vr-to">Până la</label><input class="po-input" type="date" id="vr-to"></span>
          <button class="btn btn-ghost" type="button" id="vr-apply">Aplică</button>
        </div>
        <div class="ve-tools">
          <button class="btn btn-ghost" type="button" id="vr-csv"><?= v2_ic('download-simple') ?>Export CSV</button>
          <span class="ve-sub" id="vr-period">—</span>
        </div>
      </div>
    </section>

    <section class="ve-money" aria-label="Totaluri">
      <article class="ve-mcard is-gross"><p>Venit total</p><b id="vr-revenue">—</b><p class="ve-mnote" id="vr-avg-line">—</p></article>
      <article class="ve-mcard is-fee"><p>Comision ticketing</p><b id="vr-commission">—</b><p class="ve-mnote" id="vr-formula">—</p></article>
      <article class="ve-mcard is-net"><p>Venit net</p><b id="vr-net">—</b><p class="ve-mnote">după comision</p></article>
    </section>

    <section class="ve-kpis is-4" aria-label="Volume">
      <?= $vrKpi('orders', 'receipt', 'Comenzi') ?>
      <?= $vrKpi('tickets', 'ticket', 'Bilete vândute (un pachet = unul)') ?>
      <?= $vrKpi('physical-kpi', 'scan', 'Bilete fizice emise') ?>
      <?= $vrKpi('avg', 'shopping-cart-simple', 'Coș mediu') ?>
    </section>

    <section class="ve-issuers" id="vr-issuers" aria-label="Pe societăți emitente"></section>

    <div class="ve-grid ve-grid-even">
      <section class="org-panel" aria-labelledby="vr-pay-h">
        <div class="org-panel-head"><div><h2 class="org-panel-h" id="vr-pay-h">Pe metodă de plată</h2><p class="org-panel-p">Numerar și card la casă, online.</p></div></div>
        <ul class="ve-brows" id="vr-pay"><li class="ve-state">Se încarcă…</li></ul>
      </section>
      <section class="org-panel" aria-labelledby="vr-src-h">
        <div class="org-panel-head"><div><h2 class="org-panel-h" id="vr-src-h">Online sau la casă</h2><p class="org-panel-p">De unde au venit vânzările.</p></div></div>
        <ul class="ve-brows" id="vr-sources"><li class="ve-state">Se încarcă…</li></ul>
      </section>
    </div>

    <section class="org-panel" aria-labelledby="vr-cash-h">
      <div class="org-panel-head"><div><h2 class="org-panel-h" id="vr-cash-h">Pe operator</h2><p class="org-panel-p">Cine a vândut la casă, cu comenzile, biletele și încasările fiecăruia.</p></div></div>
      <div class="ve-table-wrap"><table class="ve-table ve-cashier-table">
        <thead><tr><th scope="col">Operator</th><th scope="col" class="ve-r">Comenzi</th><th scope="col" class="ve-r">Bilete</th><th scope="col" class="ve-r">Încasări</th><th scope="col" class="ve-r">Comision</th></tr></thead>
        <tbody id="vr-cashiers"><tr><td colspan="5" class="ve-state">Se încarcă…</td></tr></tbody>
      </table></div>
    </section>

    <section class="org-panel" aria-labelledby="vr-types-h">
      <div class="org-panel-head"><div><h2 class="org-panel-h" id="vr-types-h">Pe tip de bilet</h2><p class="org-panel-p">Tranzacțiile: pachetele și biletele vândute separat, cu societatea care le emite.</p></div></div>
      <div class="ve-table-wrap"><table class="ve-table ve-report-table">
        <thead><tr><th scope="col">Bilet</th><th scope="col">Categorie</th><th scope="col">Emitent</th><th scope="col" class="ve-r">Bilete</th><th scope="col" class="ve-r">Venit</th><th scope="col" class="ve-r">Comision</th></tr></thead>
        <tbody id="vr-types"><tr><td colspan="6" class="ve-state">Se încarcă…</td></tr></tbody>
      </table></div>
    </section>

    <section class="org-panel" aria-labelledby="vr-comp-h">
      <div class="org-panel-head"><div><h2 class="org-panel-h" id="vr-comp-h">Bilete fizice emise</h2><p class="org-panel-p">Ce s-a tipărit sau trimis de fapt: componentele pachetelor și biletele individuale, fără biletul-pachet. În total: <b id="vr-physical">—</b>.</p></div></div>
      <div class="ve-table-wrap"><table class="ve-table">
        <thead><tr><th scope="col">Bilet</th><th scope="col">Categorie</th><th scope="col" class="ve-r">Bucăți</th></tr></thead>
        <tbody id="vr-comps"><tr><td colspan="3" class="ve-state">Se încarcă…</td></tr></tbody>
      </table></div>
    </section>

    <section class="org-panel" aria-labelledby="vr-scan-h">
      <div class="org-panel-head">
        <div><h2 class="org-panel-h" id="vr-scan-h">Scanări</h2><p class="org-panel-p"><span id="vr-scan-range">—</span>. Apasă pe o zi ca să vezi fiecare scanare.</p></div>
        <span class="ve-weeknav">
          <button class="btn btn-ghost" type="button" id="vr-scan-prev" aria-label="Cu 30 de zile în urmă"><?= v2_ic('arrow-left') ?></button>
          <button class="btn btn-ghost" type="button" id="vr-scan-today">Azi la mijloc</button>
          <button class="btn btn-ghost" type="button" id="vr-scan-next" aria-label="Cu 30 de zile înainte"><?= v2_ic('arrow-right') ?></button>
        </span>
      </div>
      <p class="ve-legend ve-scan-legend"><span class="ve-sl is-exp">Așteptate</span><span class="ve-sl is-valid">Bilete valide</span><span class="ve-sl is-staff">Angajați</span><span class="ve-sl is-bad">Refuzate</span></p>
      <div class="ve-chart ve-scan-chart" id="vr-scan-chart"></div>
      <p class="ve-count" id="vr-scan-totals"></p>
    </section>
  </div>

  <div class="ve-modal" id="vr-modal" role="dialog" aria-modal="true" aria-labelledby="vr-modal-h" hidden>
    <div class="ve-modal-card ve-modal-wide">
      <div class="ve-modal-head ve-modal-plain"><h2 id="vr-modal-h">Scanările zilei</h2><p class="ve-sub" id="vr-modal-totals">—</p></div>
      <div class="ve-modal-body">
        <div class="ve-table-wrap"><table class="ve-table ve-scan-table">
          <thead><tr><th scope="col">Ora</th><th scope="col">Cine</th><th scope="col">Rezultat</th><th scope="col">Cod</th><th scope="col">Email</th><th scope="col">Detaliu</th></tr></thead>
          <tbody id="vr-modal-rows"><tr><td colspan="6" class="ve-state">Se încarcă…</td></tr></tbody>
        </table></div>
      </div>
      <div class="ve-modal-foot"><button class="btn btn-ghost" type="button" id="vr-modal-close">Închide</button></div>
    </div>
  </div>
</div>
<?php
v2_org_end();
include __DIR__ . '/../includes/v2/foot.php';
