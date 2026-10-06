<?php
/**
 * Venue participants: /organizator/locatie/participanti (venue-participants.php), v2 design.
 *
 * Second screen of the "Locație" section, ported from Ambilet: who bought a ticket for the venue, whether they came
 * in, and the check-in done by hand when a code cannot be scanned. Every filter is applied by the server, so the four
 * figures always match the list: the period, the search, the ticket status, whether they checked in, the ticket types
 * and the visit day. The list is exported as CSV from what is loaded.
 *
 * org-venue-participants.js reads /organizer/events, then .../leisure/participants, and checks a ticket in through
 * .../leisure/tickets/{id}/manual-checkin.
 */

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/api.php';
require_once __DIR__ . '/../includes/nav-helpers.php';
require_once __DIR__ . '/../includes/v2/helpers.php';
require_once __DIR__ . '/../includes/v2/organizer.php';

$pageTitleRaw = 'Participanți — Locație — ' . SITE_NAME;
$pageDescription = 'Vizitatorii locației: cine a cumpărat, cine a intrat și check-in manual când codul nu se poate scana.';
$canonicalUrl = SITE_URL . '/organizator/locatie/participanti';
$noindex = true;
$skipPageCache = true;

$v2Styles = ['organizer.css', 'org-venue.css'];
$v2Scripts = ['organizer.js', 'org-venue-participants.js'];
$v2LegacyScripts = ['assets/js/config.js', 'assets/js/utils.js', 'assets/js/api.js', 'assets/js/auth.js'];
$v2HeadExtra = v2_account_client_config('organizer');

$vpStat = function (string $key, string $label, string $tone, string $hint = '') {
    return '<article class="ve-kpi' . ($tone ? ' ' . $tone : '') . '"><div><b id="vp-s-' . $key . '">—</b><p>' . $label . '</p>'
        . ($hint ? '<small>' . $hint . '</small>' : '') . '</div></article>';
};

include __DIR__ . '/../includes/v2/head.php';
v2_org_start('venue-participants');
?>
<div class="ve" id="ve">
  <header class="ve-head">
    <div>
      <p class="ve-eyebrow"><?= v2_ic('users-three') ?>Locație · Participanți</p>
      <h1 class="ve-h">Participanți</h1>
      <p class="ve-lead">Cine a cumpărat bilete pentru locație și cine a intrat. Filtrele se aplică pe server, deci cifrele de sus se potrivesc mereu cu lista.</p>
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
    <b>Nu am putut încărca participanții</b>
    <p>Reîncearcă în câteva secunde.</p>
    <button class="btn btn-primary" type="button" id="ve-retry">Reîncearcă</button>
  </div>

  <div class="ve-main" id="ve-main" hidden>
    <section class="org-panel" aria-labelledby="vp-filters-h">
      <h2 class="ve-sr" id="vp-filters-h">Filtre</h2>
      <div class="ve-filters">
        <div class="ve-ranges" role="group" aria-label="Perioada plății">
          <?php foreach ([['7', 'Ultimele 7 zile'], ['14', '14 zile'], ['30', 'O lună'], ['90', '3 luni'], ['180', '6 luni'], ['custom', 'Altă perioadă']] as $i => [$val, $label]): ?>
          <button class="ve-range" type="button" data-range="<?= $val ?>" aria-pressed="<?= $val === '30' ? 'true' : 'false' ?>"><?= $label ?></button>
          <?php endforeach; ?>
        </div>
        <div class="ve-dates" id="vp-custom" hidden>
          <span class="po-field"><label for="vp-from">De la</label><input class="po-input" type="date" id="vp-from"></span>
          <span class="po-field"><label for="vp-to">Până la</label><input class="po-input" type="date" id="vp-to"></span>
          <button class="btn btn-ghost" type="button" id="vp-apply">Aplică</button>
        </div>

        <div class="ve-filter-row">
          <label class="ve-search"><?= v2_ic('magnifying-glass') ?><input id="vp-q" type="search" autocomplete="off" placeholder="Caută după nume, email sau cod bilet" aria-label="Caută participanți"></label>
          <span class="po-select"><select id="vp-status" aria-label="Statusul biletului"><option value="">Toate statusurile</option><option value="valid">Valid</option><option value="used">Folosit</option><option value="cancelled">Anulat</option><option value="refunded">Restituit</option></select><?= v2_ic('caret-down') ?></span>
          <span class="po-select"><select id="vp-checkin" aria-label="Check-in"><option value="">Intrați și neintrați</option><option value="checked_in">Doar cei intrați</option><option value="not_checked_in">Doar cei neintrați</option></select><?= v2_ic('caret-down') ?></span>
          <details class="po-fold ve-types" id="vp-types">
            <summary><?= v2_ic('ticket') ?><span id="vp-types-label">Toate tipurile</span></summary>
            <div class="po-fold-in" id="vp-types-list"><p class="ve-state">Se încarcă…</p></div>
          </details>
          <button class="ve-reset" type="button" id="vp-reset" hidden><?= v2_ic('x') ?>Șterge filtrele</button>
        </div>

        <div class="ve-tools">
          <span class="ve-dates"><span>Data vizitei:</span>
            <span class="po-field"><label class="ve-sr" for="vp-visit-from">Vizită de la</label><input class="po-input" type="date" id="vp-visit-from"></span>
            <span class="po-field"><label class="ve-sr" for="vp-visit-to">Vizită până la</label><input class="po-input" type="date" id="vp-visit-to"></span>
          </span>
          <button class="btn btn-ghost" type="button" id="vp-csv"><?= v2_ic('download-simple') ?>Export CSV</button>
        </div>
      </div>
    </section>

    <section class="ve-kpis" aria-label="Pe scurt">
      <?= $vpStat('total', 'Participanți', '', 'Bilete de acces; pachetele se numără pe componente') ?>
      <?= $vpStat('checked', 'Au intrat', '') ?>
      <?= $vpStat('rate', 'Rată de intrare', '') ?>
      <?= $vpStat('noshow', 'Nu au venit', '') ?>
    </section>

    <section class="org-panel" aria-labelledby="vp-list-h">
      <div class="org-panel-head">
        <div><h2 class="org-panel-h" id="vp-list-h">Lista participanților</h2><p class="org-panel-p" id="vp-period">—</p></div>
      </div>
      <div class="ve-table-wrap"><table class="ve-table ve-part-table">
        <thead><tr>
          <th scope="col">Comandă</th><th scope="col">Cod bilet</th><th scope="col">Client</th><th scope="col">Nr. înmatriculare</th>
          <th scope="col">Tip bilet</th><th scope="col">Data vizitei</th><th scope="col">Status</th><th scope="col">Check-in</th>
        </tr></thead>
        <tbody id="vp-rows"><tr><td colspan="8" class="ve-state">Se încarcă…</td></tr></tbody>
      </table></div>
      <p class="ve-count" id="vp-count"></p>
      <div class="ve-more"><button class="btn btn-ghost" type="button" id="vp-more" hidden>Încarcă mai multe</button></div>
    </section>
  </div>
</div>
<?php
v2_org_end();
include __DIR__ . '/../includes/v2/foot.php';
