<?php
/**
 * The operator's bookings: /organizator/rezervari (activities module), v2 design.
 *
 * On top, the last 30 days (bookings, persons, sales, what the operator keeps) and the arrivals of today and the next
 * 7 days. Two views: "Pe zile" (who comes on a date, per product and start time, with the seats left, and marking a
 * paid visitor who did not come) and "Toate rezervările" (by visit date, filtered by product, status and a search,
 * with a CSV export).
 *
 * org-am-bookings.js reads /organizer/activities-module/summary, .../bookings/day, .../bookings and .../products, marks
 * no-shows (.../bookings/{id}/no-show) and downloads .../bookings/export.
 */

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/api.php';
require_once __DIR__ . '/../includes/nav-helpers.php';
require_once __DIR__ . '/../includes/v2/helpers.php';
require_once __DIR__ . '/../includes/v2/organizer.php';
require_once __DIR__ . '/../includes/v2/am-labels.php';

$pageTitleRaw = 'Rezervări — ' . SITE_NAME;
$pageDescription = 'Rezervările pentru locațiile și produsele tale: pe zile, pe ore, cu export.';
$canonicalUrl = SITE_URL . '/organizator/rezervari';
$noindex = true;
$skipPageCache = true;

$v2Styles = ['organizer.css', 'org-venue.css', 'org-am.css'];
$v2Scripts = ['organizer.js', 'org-am.js', 'org-am-bookings.js'];
$v2LegacyScripts = ['assets/js/config.js', 'assets/js/utils.js', 'assets/js/api.js', 'assets/js/auth.js'];
$v2HeadExtra = v2_account_client_config('organizer');
$v2ClientData = ['am' => am_client_labels()];

include __DIR__ . '/../includes/v2/head.php';
v2_org_start('am-bookings');
?>
<div class="ve am" id="am-bk">
  <header class="ve-head">
    <div>
      <p class="ve-eyebrow"><?= v2_ic('calendar-blank') ?>Rezervări</p>
      <h1 class="ve-h">Rezervări</h1>
      <p class="ve-lead">Cine vine și când, pe fiecare produs. Biletele se validează cu aplicația de scanare, la intrare.</p>
    </div>
    <div class="am-filters">
      <span class="po-field"><label for="am-bk-loc">Locația</label><span class="po-select"><select id="am-bk-loc"><option value="">Toate locațiile</option></select><?= v2_ic('caret-down') ?></span></span>
    </div>
  </header>

  <section class="am-kpis" id="am-bk-kpis" aria-label="Ultimele 30 de zile"></section>

  <div class="ve-tabs" role="tablist" aria-label="Vederi">
    <button class="ve-tab" type="button" role="tab" id="am-tab-day" aria-selected="true" aria-controls="am-bk-day">Pe zile</button>
    <button class="ve-tab" type="button" role="tab" id="am-tab-all" aria-selected="false" aria-controls="am-bk-all">Toate rezervările</button>
  </div>

  <section class="org-panel am-stack" id="am-bk-day" role="tabpanel" aria-labelledby="am-tab-day">
    <div class="am-daynav">
      <button class="ve-icon-btn" type="button" id="am-day-prev" aria-label="Ziua dinainte"><?= v2_ic('arrow-left') ?></button>
      <label class="ve-sr" for="am-day">Ziua</label>
      <input class="po-input" type="date" id="am-day">
      <button class="ve-icon-btn" type="button" id="am-day-next" aria-label="Ziua următoare"><?= v2_ic('arrow-right') ?></button>
      <button class="btn btn-ghost" type="button" id="am-day-today">Azi</button>
      <b id="am-day-total" aria-live="polite"></b>
    </div>
    <div class="am-stack" id="am-day-body"><p class="ve-state">Se încarcă…</p></div>
  </section>

  <section class="org-panel am-stack" id="am-bk-all" role="tabpanel" aria-labelledby="am-tab-all" hidden>
    <form class="am-filters" id="am-all-form">
      <span class="po-field"><label for="am-all-from">De la</label><input class="po-input" type="date" id="am-all-from"></span>
      <span class="po-field"><label for="am-all-to">Până la</label><input class="po-input" type="date" id="am-all-to"></span>
      <span class="po-field"><label for="am-all-prod">Produsul</label><span class="po-select"><select id="am-all-prod"><option value="">Toate produsele</option></select><?= v2_ic('caret-down') ?></span></span>
      <span class="po-field"><label for="am-all-status">Starea</label><span class="po-select"><select id="am-all-status">
        <option value="">Vândute și anulate</option><option value="paid">Plătite</option><option value="checked_in">Validate</option><option value="no_show">Nu s-au prezentat</option><option value="cancelled">Anulate</option>
      </select><?= v2_ic('caret-down') ?></span></span>
      <span class="po-field"><label for="am-all-q">Caută</label><input class="po-input" type="search" id="am-all-q" placeholder="Nume, email, cod" autocomplete="off"></span>
      <button class="btn btn-primary" type="submit">Arată</button>
      <button class="btn btn-ghost" type="button" id="am-all-export"><?= v2_ic('file-text') ?>Export CSV</button>
    </form>
    <div class="ve-table-wrap"><table class="ve-table am-table">
      <thead><tr><th scope="col">Data</th><th scope="col">Produsul</th><th scope="col">Client</th><th scope="col">Valoare</th><th scope="col">Starea</th></tr></thead>
      <tbody id="am-all-rows"><tr><td colspan="5" class="ve-state">Se încarcă…</td></tr></tbody>
    </table></div>
    <nav class="ve-pager am-daynav" id="am-all-pager" aria-label="Pagini" hidden>
      <button class="btn btn-ghost" type="button" id="am-all-prev"><?= v2_ic('arrow-left') ?>Înapoi</button>
      <span id="am-all-page"></span>
      <button class="btn btn-ghost" type="button" id="am-all-next">Mai departe<?= v2_ic('arrow-right') ?></button>
    </nav>
  </section>
</div>
<?php
v2_org_end();
include __DIR__ . '/../includes/v2/foot.php';
