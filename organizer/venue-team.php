<?php
/**
 * Venue team and rota: /organizator/locatie/echipa (venue-team.php), v2 design.
 *
 * Fourth screen of the "Locație" section, ported from Ambilet, where it was split in two. It holds the two kinds of
 * people a venue works with, and they are deliberately labelled apart because the core keeps them apart:
 *  - the venue's own people (leisure staff): each gets a code the scanning app reads when they start work, and their
 *    arrivals are the timesheet at the bottom of the page;
 *  - the account's members (the team from /organizator/echipa), who are the ones a weekly rota can be built on.
 *
 * org-venue-team.js reads /organizer/leisure/staff, .../staff-checkins and .../staff-export, and the rota through
 * /organizer/events/{id}/leisure/shifts.
 */

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/api.php';
require_once __DIR__ . '/../includes/nav-helpers.php';
require_once __DIR__ . '/../includes/v2/helpers.php';
require_once __DIR__ . '/../includes/v2/organizer.php';

$pageTitleRaw = 'Echipă & program — Locație — ' . SITE_NAME;
$pageDescription = 'Oamenii locației, programul săptămânal pe ture și pontajul de la scanarea codului.';
$canonicalUrl = SITE_URL . '/organizator/locatie/echipa';
$noindex = true;
$skipPageCache = true;

$v2Styles = ['organizer.css', 'org-venue.css'];
$v2Scripts = ['organizer.js', 'org-venue-team.js'];
$v2LegacyScripts = ['assets/js/config.js', 'assets/js/utils.js', 'assets/js/api.js', 'assets/js/auth.js'];
$v2HeadExtra = v2_account_client_config();

$vtRoles = [
    'gate_scanner' => 'Scanare la poartă', 'sales_operator' => 'Vânzare', 'shift_manager' => 'Șef de tură',
    'accountant' => 'Contabilitate', 'operator_boats' => 'Bărci', 'operator_pontoon' => 'Ponton',
    'operator_pontoon_rental' => 'Închirieri ponton', 'operator_sled' => 'Sanie', 'operator_tow_validation' => 'Validare teleschi',
    'admin_mobile' => 'Administrare mobilă', 'field_seller' => 'Vânzare pe teren',
];

include __DIR__ . '/../includes/v2/head.php';
v2_org_start('venue-team');
?>
<div class="ve" id="ve">
  <header class="ve-head">
    <div>
      <p class="ve-eyebrow"><?= v2_ic('user-plus') ?>Locație · Echipă</p>
      <h1 class="ve-h">Echipă &amp; program</h1>
      <p class="ve-lead">Oamenii locației și programul lor. Cine vine la lucru se pontează scanând codul propriu.</p>
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
    <b>Nu am putut încărca echipa</b>
    <p>Reîncearcă în câteva secunde.</p>
    <button class="btn btn-primary" type="button" id="ve-retry">Reîncearcă</button>
  </div>

  <div class="ve-main" id="ve-main" hidden>
    <section class="org-panel" aria-labelledby="vt-people-h">
      <div class="org-panel-head">
        <div><h2 class="org-panel-h" id="vt-people-h">Oamenii locației</h2>
        <p class="org-panel-p">Fiecare primește un cod propriu. Aplicația de scanare îl citește când omul intră în tură.</p></div>
        <button class="btn btn-primary" type="button" id="vt-add"><?= v2_ic('plus') ?>Adaugă un om</button>
      </div>
      <div class="ve-table-wrap"><table class="ve-table ve-people-table">
        <thead><tr>
          <th scope="col">Nume</th><th scope="col">Rol la locație</th><th scope="col">Telefon</th>
          <th scope="col">Cod de pontaj</th><th scope="col" class="ve-r">Pontaje</th><th scope="col">Ultimul pontaj</th>
          <th scope="col"><span class="ve-sr">Acțiuni</span></th>
        </tr></thead>
        <tbody id="vt-people"><tr><td colspan="7" class="ve-state">Se încarcă…</td></tr></tbody>
      </table></div>
      <p class="ve-count" id="vt-people-count"></p>
    </section>

    <section class="org-panel" aria-labelledby="vt-week-h">
      <div class="org-panel-head">
        <div><h2 class="org-panel-h" id="vt-week-h">Programul săptămânii</h2>
        <p class="org-panel-p">Turele se dau membrilor contului, cei din <a href="/organizator/echipa">Echipă</a>. Apasă pe o zi ca să adaugi o tură, pe o tură ca s-o schimbi.</p></div>
        <span class="ve-weeknav">
          <button class="btn btn-ghost" type="button" id="vt-prev" aria-label="Săptămâna anterioară"><?= v2_ic('arrow-left') ?></button>
          <b id="vt-week-label">—</b>
          <button class="btn btn-ghost" type="button" id="vt-next" aria-label="Săptămâna următoare"><?= v2_ic('arrow-right') ?></button>
          <button class="btn btn-ghost" type="button" id="vt-today">Săptămâna asta</button>
        </span>
      </div>
      <div class="ve-table-wrap"><table class="ve-table ve-week-table">
        <thead><tr id="vt-week-head"><th scope="col">Membru</th></tr></thead>
        <tbody id="vt-week"><tr><td colspan="8" class="ve-state">Se încarcă…</td></tr></tbody>
      </table></div>
    </section>

    <section class="org-panel ve-hist" aria-labelledby="vt-time-h">
      <div class="org-panel-head">
        <div><h2 class="org-panel-h" id="vt-time-h"><?= v2_ic('clock') ?> Pontaj</h2>
        <p class="org-panel-p">Când a intrat fiecare în tură, după codul scanat.</p></div>
        <button class="btn btn-ghost ve-hist-btn" type="button" id="vt-time-btn" aria-expanded="false" aria-controls="vt-time-body">Arată pontajul<?= v2_ic('caret-down') ?></button>
      </div>
      <div id="vt-time-body" hidden>
        <div class="ve-hist-row">
          <span class="po-field"><label class="ve-sr" for="vt-tfrom">De la</label><input class="po-input" type="date" id="vt-tfrom"></span>
          <span class="po-field"><label class="ve-sr" for="vt-tto">Până la</label><input class="po-input" type="date" id="vt-tto"></span>
          <span class="po-select"><select id="vt-tstaff" aria-label="Omul"><option value="">Toți oamenii</option></select><?= v2_ic('caret-down') ?></span>
          <button class="btn btn-ghost" type="button" id="vt-tcsv"><?= v2_ic('download-simple') ?>Export CSV</button>
        </div>
        <div class="ve-sum-cards" id="vt-per-staff"></div>
        <div class="ve-table-wrap"><table class="ve-table ve-time-table">
          <thead><tr><th scope="col">Om</th><th scope="col">Rol</th><th scope="col">Activitate</th><th scope="col">Punct</th><th scope="col">Data și ora</th></tr></thead>
          <tbody id="vt-times"><tr><td colspan="5" class="ve-state">Se încarcă…</td></tr></tbody>
        </table></div>
        <p class="ve-count" id="vt-time-count"></p>
      </div>
    </section>
  </div>

  <div class="ve-modal" id="vt-modal" role="dialog" aria-modal="true" aria-labelledby="vt-modal-h" hidden>
    <div class="ve-modal-card">
      <div class="ve-modal-head"><h2 id="vt-modal-h">Adaugă un om</h2></div>
      <div class="ve-modal-body">
        <div class="ve-two">
          <span class="po-field"><label for="vt-f-first">Prenume</label><input class="po-input" id="vt-f-first" maxlength="100" autocomplete="off"></span>
          <span class="po-field"><label for="vt-f-last">Nume</label><input class="po-input" id="vt-f-last" maxlength="100" autocomplete="off"></span>
        </div>
        <div class="ve-two">
          <span class="po-field"><label for="vt-f-phone">Telefon</label><input class="po-input" id="vt-f-phone" maxlength="30" autocomplete="off"></span>
          <span class="po-field"><label for="vt-f-pos">Rol la locație</label><input class="po-input" id="vt-f-pos" maxlength="120" placeholder="Casier, poartă, ghid…" autocomplete="off"></span>
        </div>
        <span class="po-field"><label for="vt-f-notes">Notițe</label><textarea class="ve-ta" id="vt-f-notes" rows="2" maxlength="1000"></textarea></span>
        <p class="ve-note-line" id="vt-f-qr" hidden></p>
      </div>
      <div class="ve-modal-foot">
        <button class="ve-danger" type="button" id="vt-f-off" hidden><?= v2_ic('trash') ?>Scoate din echipă</button>
        <button class="btn btn-ghost" type="button" id="vt-f-cancel">Renunță</button>
        <button class="btn btn-primary" type="button" id="vt-f-save"><?= v2_ic('check') ?>Salvează</button>
      </div>
    </div>
  </div>

  <div class="ve-modal" id="vs-modal" role="dialog" aria-modal="true" aria-labelledby="vs-modal-h" hidden>
    <div class="ve-modal-card">
      <div class="ve-modal-head"><h2 id="vs-modal-h">Adaugă o tură</h2></div>
      <div class="ve-modal-body">
        <span class="po-field"><label for="vs-f-member">Membru</label><span class="po-select"><select id="vs-f-member"></select><?= v2_ic('caret-down') ?></span></span>
        <div class="ve-two">
          <span class="po-field"><label for="vs-f-start">Începe</label><input class="po-input" type="datetime-local" id="vs-f-start"></span>
          <span class="po-field"><label for="vs-f-end">Se termină</label><input class="po-input" type="datetime-local" id="vs-f-end"></span>
        </div>
        <div class="ve-two">
          <span class="po-field"><label for="vs-f-role">Ce face</label><span class="po-select"><select id="vs-f-role">
            <?php foreach ($vtRoles as $key => $label): ?><option value="<?= $key ?>"><?= $label ?></option><?php endforeach; ?>
          </select><?= v2_ic('caret-down') ?></span></span>
          <span class="po-field"><label for="vs-f-gate">Poarta</label><input class="po-input" id="vs-f-gate" maxlength="32" placeholder="A, B, Parcare…" autocomplete="off"></span>
        </div>
        <span class="po-field"><label for="vs-f-notes">Notițe</label><textarea class="ve-ta" id="vs-f-notes" rows="2" maxlength="500"></textarea></span>
      </div>
      <div class="ve-modal-foot">
        <button class="ve-danger" type="button" id="vs-f-del" hidden><?= v2_ic('trash') ?>Șterge tura</button>
        <button class="btn btn-ghost" type="button" id="vs-f-cancel">Renunță</button>
        <button class="btn btn-primary" type="button" id="vs-f-save"><?= v2_ic('check') ?>Salvează</button>
      </div>
    </div>
  </div>
</div>
<?php
v2_org_end();
include __DIR__ . '/../includes/v2/foot.php';
