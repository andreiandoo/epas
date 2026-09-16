<?php
/**
 * Venue orders: /organizator/locatie/comenzi (venue-orders.php), v2 design.
 *
 * Third screen of the "Locație" section, ported from Ambilet: every order of the venue, online and at the counter,
 * with the tickets behind each one. An order can be deleted, which is irreversible and therefore asks for a written
 * reason; the core keeps that reason, the whole order and its tickets in the deletion history, which is the panel at
 * the bottom of this page.
 *
 * org-venue-orders.js reads /organizer/events, then .../leisure/orders, .../leisure/orders/{id} for one row's
 * tickets, .../leisure/orders/deletion-history for the panel, and deletes through DELETE .../leisure/orders/{id}.
 */

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/api.php';
require_once __DIR__ . '/../includes/nav-helpers.php';
require_once __DIR__ . '/../includes/v2/helpers.php';
require_once __DIR__ . '/../includes/v2/organizer.php';

$pageTitleRaw = 'Comenzi — Locație — ' . SITE_NAME;
$pageDescription = 'Comenzile locației, online și la casă, cu biletele din fiecare comandă și istoricul ștergerilor.';
$canonicalUrl = SITE_URL . '/organizator/locatie/comenzi';
$noindex = true;
$skipPageCache = true;

$v2Styles = ['organizer.css', 'org-venue.css'];
$v2Scripts = ['organizer.js', 'org-venue-orders.js'];
$v2LegacyScripts = ['assets/js/config.js', 'assets/js/utils.js', 'assets/js/api.js', 'assets/js/auth.js'];
$v2HeadExtra = v2_account_client_config();

include __DIR__ . '/../includes/v2/head.php';
v2_org_start('venue-orders');
?>
<div class="ve" id="ve">
  <header class="ve-head">
    <div>
      <p class="ve-eyebrow"><?= v2_ic('receipt') ?>Locație · Comenzi</p>
      <h1 class="ve-h">Comenzi</h1>
      <p class="ve-lead">Toate comenzile locației, de pe site și de la casă. Deschide un rând ca să vezi biletele emise.</p>
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
    <b>Nu am putut încărca comenzile</b>
    <p>Reîncearcă în câteva secunde.</p>
    <button class="btn btn-primary" type="button" id="ve-retry">Reîncearcă</button>
  </div>

  <div class="ve-main" id="ve-main" hidden>
    <section class="org-panel" aria-labelledby="vo-filters-h">
      <h2 class="ve-sr" id="vo-filters-h">Filtre</h2>
      <div class="ve-filters">
        <div class="ve-ranges" role="group" aria-label="Perioada plății">
          <?php foreach ([['7', 'Ultimele 7 zile'], ['14', '14 zile'], ['30', 'O lună'], ['90', '3 luni'], ['180', '6 luni'], ['custom', 'Altă perioadă']] as [$val, $label]): ?>
          <button class="ve-range" type="button" data-range="<?= $val ?>" aria-pressed="<?= $val === '30' ? 'true' : 'false' ?>"><?= $label ?></button>
          <?php endforeach; ?>
        </div>
        <div class="ve-dates" id="vo-custom" hidden>
          <span class="po-field"><label for="vo-from">De la</label><input class="po-input" type="date" id="vo-from"></span>
          <span class="po-field"><label for="vo-to">Până la</label><input class="po-input" type="date" id="vo-to"></span>
          <button class="btn btn-ghost" type="button" id="vo-apply">Aplică</button>
        </div>

        <div class="ve-filter-row">
          <label class="ve-search"><?= v2_ic('magnifying-glass') ?><input id="vo-q" type="search" autocomplete="off" placeholder="Caută după nr. comandă, nume sau email" aria-label="Caută comenzi"></label>
          <span class="po-select"><select id="vo-source" aria-label="Sursa comenzii"><option value="">De peste tot</option><option value="pos">De la casă</option><option value="online">De pe site</option></select><?= v2_ic('caret-down') ?></span>
          <span class="po-select"><select id="vo-status" aria-label="Statusul comenzii"><option value="">Toate statusurile</option><option value="paid">Plătită</option><option value="completed">Finalizată</option><option value="pending">În așteptare</option><option value="refunded">Restituită</option><option value="cancelled">Anulată</option></select><?= v2_ic('caret-down') ?></span>
          <button class="ve-reset" type="button" id="vo-reset" hidden><?= v2_ic('x') ?>Șterge filtrele</button>
        </div>
      </div>
    </section>

    <section class="org-panel" aria-labelledby="vo-list-h">
      <div class="org-panel-head">
        <div><h2 class="org-panel-h" id="vo-list-h">Lista comenzilor</h2><p class="org-panel-p" id="vo-period">—</p></div>
      </div>
      <div class="ve-table-wrap"><table class="ve-table ve-ord-table">
        <thead><tr>
          <th scope="col"><span class="ve-sr">Deschide</span></th>
          <th scope="col">Nr. comandă</th><th scope="col">Data plății</th><th scope="col">Client</th>
          <th scope="col">Sursă</th><th scope="col">Plată</th><th scope="col">Operator</th>
          <th scope="col" class="ve-r">Bilete</th><th scope="col" class="ve-r">Total</th>
          <th scope="col">Status</th><th scope="col"><span class="ve-sr">Acțiuni</span></th>
        </tr></thead>
        <tbody id="vo-rows"><tr><td colspan="11" class="ve-state">Se încarcă…</td></tr></tbody>
      </table></div>
      <div class="ve-pager">
        <span id="vo-count"></span>
        <span class="ve-pg">
          <button type="button" id="vo-prev" disabled><?= v2_ic('arrow-left') ?>Anterioare</button>
          <button type="button" id="vo-next" disabled>Următoare<?= v2_ic('arrow-right') ?></button>
        </span>
      </div>
    </section>

    <section class="org-panel ve-hist" aria-labelledby="vo-hist-h">
      <div class="org-panel-head">
        <div><h2 class="org-panel-h" id="vo-hist-h"><?= v2_ic('clock') ?> Istoric ștergeri</h2>
        <p class="org-panel-p">Ce s-a șters, de către cine, când și din ce motiv. Se păstrează integral.</p></div>
        <button class="btn btn-ghost ve-hist-btn" type="button" id="vo-hist-btn" aria-expanded="false" aria-controls="vo-hist-body">Arată istoricul<?= v2_ic('caret-down') ?></button>
      </div>
      <div id="vo-hist-body" hidden>
        <div class="ve-hist-row">
          <label class="ve-search"><?= v2_ic('magnifying-glass') ?><input id="vo-hq" type="search" autocomplete="off" placeholder="Caută în istoric" aria-label="Caută în istoricul ștergerilor"></label>
          <span class="po-field"><label class="ve-sr" for="vo-hfrom">De la</label><input class="po-input" type="date" id="vo-hfrom"></span>
          <span class="po-field"><label class="ve-sr" for="vo-hto">Până la</label><input class="po-input" type="date" id="vo-hto"></span>
        </div>
        <div class="ve-table-wrap"><table class="ve-table ve-hist-table">
          <thead><tr>
            <th scope="col">Nr. comandă</th><th scope="col">Ștearsă la</th><th scope="col">De către</th>
            <th scope="col">Client</th><th scope="col" class="ve-r">Bilete</th><th scope="col" class="ve-r">Total</th>
            <th scope="col">Motiv</th>
          </tr></thead>
          <tbody id="vo-hrows"><tr><td colspan="7" class="ve-state">Se încarcă…</td></tr></tbody>
        </table></div>
        <div class="ve-pager">
          <span id="vo-hcount"></span>
          <span class="ve-pg">
            <button type="button" id="vo-hprev" disabled><?= v2_ic('arrow-left') ?>Anterioare</button>
            <button type="button" id="vo-hnext" disabled>Următoare<?= v2_ic('arrow-right') ?></button>
          </span>
        </div>
      </div>
    </section>
  </div>

  <div class="ve-modal" id="vo-modal" role="dialog" aria-modal="true" aria-labelledby="vo-modal-h" hidden>
    <div class="ve-modal-card">
      <div class="ve-modal-head"><h2 id="vo-modal-h">Ștergi comanda <span id="vo-del-nr"></span>?</h2></div>
      <div class="ve-modal-body">
        <p class="ve-warn"><?= v2_ic('warning-circle') ?><span>Ștergerea este <strong>ireversibilă</strong>. Comanda, biletele ei și check-in-urile dispar din baza de date. Dacă era într-o sesiune de casă închisă, raportul sesiunii se recalculează. Totul rămâne în istoricul ștergerilor.</span></p>
        <dl class="ve-sum" id="vo-del-sum"></dl>
        <span class="po-field">
          <label for="vo-del-note">Motivul ștergerii (obligatoriu)</label>
          <textarea class="ve-ta" id="vo-del-note" rows="3" minlength="3" maxlength="1000" placeholder="Ex: comandă dublată din greșeală, eroare de operator…"></textarea>
          <span class="ve-hint">Minimum 3 caractere, maximum 1000. Apare în istoric.</span>
        </span>
      </div>
      <div class="ve-modal-foot">
        <button class="btn btn-ghost" type="button" id="vo-del-cancel">Renunță</button>
        <button class="ve-danger" type="button" id="vo-del-ok"><?= v2_ic('trash') ?>Șterge definitiv</button>
      </div>
    </div>
  </div>
</div>
<?php
v2_org_end();
include __DIR__ . '/../includes/v2/foot.php';
