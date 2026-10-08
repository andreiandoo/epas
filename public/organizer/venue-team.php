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

$pageTitleRaw = v2_t('Venue staff and rota: {site}', ['site' => SITE_NAME]);
$pageDescription = v2_t('The venue\'s staff, the weekly rota by shift and the clock-ins from scanning the code.');
$canonicalUrl = SITE_URL . '/organizator/locatie/echipa';
$noindex = true;
$skipPageCache = true;

$v2Styles = ['organizer.css', 'org-venue.css'];
$v2Scripts = ['organizer.js', 'org-venue-team.js'];
$v2LegacyScripts = ['assets/js/config.js', 'assets/js/utils.js', 'assets/js/api.js', 'assets/js/auth.js'];
$v2HeadExtra = v2_account_client_config('organizer');

$vtRoles = [
    'gate_scanner' => v2_t('Gate scanning'), 'sales_operator' => v2_t('Sales'), 'shift_manager' => v2_t('Shift manager'),
    'accountant' => v2_t('Accounting'), 'operator_boats' => v2_t('Boats'), 'operator_pontoon' => v2_t('Pontoon'),
    'operator_pontoon_rental' => v2_t('Pontoon rentals'), 'operator_sled' => v2_t('Sledge'), 'operator_tow_validation' => v2_t('Ski tow validation'),
    'admin_mobile' => v2_t('Mobile administration'), 'field_seller' => v2_t('Field sales'),
];

include __DIR__ . '/../includes/v2/head.php';
v2_org_start('venue-team');
?>
<div class="ve" id="ve">
  <header class="ve-head">
    <div>
      <p class="ve-eyebrow"><?= v2_ic('user-plus') ?><?= v2_te('Venue · Staff') ?></p>
      <h1 class="ve-h"><?= v2_te('Staff & rota') ?></h1>
      <p class="ve-lead"><?= v2_te('The venue staff and their rota. Whoever comes to work clocks in by scanning their own code.') ?></p>
    </div>
    <div class="ve-head-tools">
      <span class="po-field"><label for="ve-event"><?= v2_te('Venue') ?></label><span class="po-select"><select id="ve-event" disabled><option><?= v2_te('Loading…') ?></option></select><?= v2_ic('caret-down') ?></span></span>
    </div>
  </header>

  <div class="org-empty" id="ve-none" hidden>
    <span class="org-empty-ic"><?= v2_ic('door-open') ?></span>
    <b><?= v2_te('No venue set up yet') ?></b>
    <p><?= v2_te('Venue pages work on an experience set up as a venue.') ?></p>
    <a class="btn btn-primary" href="/organizator/suport"><?= v2_te('Ask for activation') ?><?= v2_ic('arrow-right') ?></a>
  </div>

  <div class="org-empty is-error" id="ve-failed" hidden>
    <span class="org-empty-ic"><?= v2_ic('warning-circle') ?></span>
    <b><?= v2_te('We could not load the staff') ?></b>
    <p><?= v2_te('Try again in a few seconds.') ?></p>
    <button class="btn btn-primary" type="button" id="ve-retry"><?= v2_te('Try again') ?></button>
  </div>

  <div class="ve-main" id="ve-main" hidden>
    <section class="org-panel" aria-labelledby="vt-people-h">
      <div class="org-panel-head">
        <div><h2 class="org-panel-h" id="vt-people-h"><?= v2_te('Venue staff') ?></h2>
        <p class="org-panel-p"><?= v2_te('Each person gets their own code. The scanning app reads it when they start their shift.') ?></p></div>
        <button class="btn btn-primary" type="button" id="vt-add"><?= v2_ic('plus') ?><?= v2_te('Add a staff member') ?></button>
      </div>
      <div class="ve-table-wrap"><table class="ve-table ve-people-table">
        <thead><tr>
          <th scope="col"><?= v2_te('Name') ?></th><th scope="col"><?= v2_te('Role at the venue') ?></th><th scope="col"><?= v2_te('Phone') ?></th>
          <th scope="col"><?= v2_te('Clock-in code') ?></th><th scope="col" class="ve-r"><?= v2_te('Clock-ins') ?></th><th scope="col"><?= v2_te('Last clock-in') ?></th>
          <th scope="col"><span class="ve-sr"><?= v2_te('Actions') ?></span></th>
        </tr></thead>
        <tbody id="vt-people"><tr><td colspan="7" class="ve-state"><?= v2_te('Loading…') ?></td></tr></tbody>
      </table></div>
      <p class="ve-count" id="vt-people-count"></p>
    </section>

    <section class="org-panel" aria-labelledby="vt-week-h">
      <div class="org-panel-head">
        <div><h2 class="org-panel-h" id="vt-week-h"><?= v2_te('The week\'s rota') ?></h2>
        <p class="org-panel-p"><?= v2_t('Shifts are given to the members of the account, the ones in <a href="{url}">Team</a>. Press a day to add a shift, a shift to change it.', ['url' => '/organizator/echipa']) ?></p></div>
        <span class="ve-weeknav">
          <button class="btn btn-ghost" type="button" id="vt-prev" aria-label="<?= v2_te('Previous week') ?>"><?= v2_ic('arrow-left') ?></button>
          <b id="vt-week-label">—</b>
          <button class="btn btn-ghost" type="button" id="vt-next" aria-label="<?= v2_te('Next week') ?>"><?= v2_ic('arrow-right') ?></button>
          <button class="btn btn-ghost" type="button" id="vt-today"><?= v2_te('This week') ?></button>
        </span>
      </div>
      <div class="ve-table-wrap"><table class="ve-table ve-week-table">
        <thead><tr id="vt-week-head"><th scope="col"><?= v2_te('Member') ?></th></tr></thead>
        <tbody id="vt-week"><tr><td colspan="8" class="ve-state"><?= v2_te('Loading…') ?></td></tr></tbody>
      </table></div>
    </section>

    <section class="org-panel ve-hist" aria-labelledby="vt-time-h">
      <div class="org-panel-head">
        <div><h2 class="org-panel-h" id="vt-time-h"><?= v2_ic('clock') ?> <?= v2_te('Clock-ins') ?></h2>
        <p class="org-panel-p"><?= v2_te('When each person started their shift, by the code scanned.') ?></p></div>
        <button class="btn btn-ghost ve-hist-btn" type="button" id="vt-time-btn" aria-expanded="false" aria-controls="vt-time-body"><?= v2_te('Show clock-ins') ?><?= v2_ic('caret-down') ?></button>
      </div>
      <div id="vt-time-body" hidden>
        <div class="ve-hist-row">
          <span class="po-field"><label class="ve-sr" for="vt-tfrom"><?= v2_te('From') ?></label><input class="po-input" type="date" id="vt-tfrom"></span>
          <span class="po-field"><label class="ve-sr" for="vt-tto"><?= v2_te('To') ?></label><input class="po-input" type="date" id="vt-tto"></span>
          <span class="po-select"><select id="vt-tstaff" aria-label="<?= v2_te('Staff member') ?>"><option value=""><?= v2_te('All staff') ?></option></select><?= v2_ic('caret-down') ?></span>
          <button class="btn btn-ghost" type="button" id="vt-tcsv"><?= v2_ic('download-simple') ?><?= v2_te('Export CSV') ?></button>
        </div>
        <div class="ve-sum-cards" id="vt-per-staff"></div>
        <div class="ve-table-wrap"><table class="ve-table ve-time-table">
          <thead><tr><th scope="col"><?= v2_te('Staff member') ?></th><th scope="col"><?= v2_te('Role') ?></th><th scope="col"><?= v2_te('Experience') ?></th><th scope="col"><?= v2_te('Point') ?></th><th scope="col"><?= v2_te('Date and time') ?></th></tr></thead>
          <tbody id="vt-times"><tr><td colspan="5" class="ve-state"><?= v2_te('Loading…') ?></td></tr></tbody>
        </table></div>
        <p class="ve-count" id="vt-time-count"></p>
      </div>
    </section>
  </div>

  <div class="ve-modal" id="vt-modal" role="dialog" aria-modal="true" aria-labelledby="vt-modal-h" hidden>
    <div class="ve-modal-card">
      <div class="ve-modal-head"><h2 id="vt-modal-h"><?= v2_te('Add a staff member') ?></h2></div>
      <div class="ve-modal-body">
        <div class="ve-two">
          <span class="po-field"><label for="vt-f-first"><?= v2_te('First name') ?></label><input class="po-input" id="vt-f-first" maxlength="100" autocomplete="off"></span>
          <span class="po-field"><label for="vt-f-last"><?= v2_te('Surname') ?></label><input class="po-input" id="vt-f-last" maxlength="100" autocomplete="off"></span>
        </div>
        <div class="ve-two">
          <span class="po-field"><label for="vt-f-phone"><?= v2_te('Phone') ?></label><input class="po-input" id="vt-f-phone" maxlength="30" autocomplete="off"></span>
          <span class="po-field"><label for="vt-f-pos"><?= v2_te('Role at the venue') ?></label><input class="po-input" id="vt-f-pos" maxlength="120" placeholder="<?= v2_te('Cashier, gate, guide…') ?>" autocomplete="off"></span>
        </div>
        <span class="po-field"><label for="vt-f-notes"><?= v2_te('Notes') ?></label><textarea class="ve-ta" id="vt-f-notes" rows="2" maxlength="1000"></textarea></span>
        <p class="ve-note-line" id="vt-f-qr" hidden></p>
      </div>
      <div class="ve-modal-foot">
        <button class="ve-danger" type="button" id="vt-f-off" hidden><?= v2_ic('trash') ?><?= v2_te('Remove from the staff') ?></button>
        <button class="btn btn-ghost" type="button" id="vt-f-cancel"><?= v2_te('Cancel') ?></button>
        <button class="btn btn-primary" type="button" id="vt-f-save"><?= v2_ic('check') ?><?= v2_te('Save') ?></button>
      </div>
    </div>
  </div>

  <div class="ve-modal" id="vs-modal" role="dialog" aria-modal="true" aria-labelledby="vs-modal-h" hidden>
    <div class="ve-modal-card">
      <div class="ve-modal-head"><h2 id="vs-modal-h"><?= v2_te('Add a shift') ?></h2></div>
      <div class="ve-modal-body">
        <span class="po-field"><label for="vs-f-member"><?= v2_te('Member') ?></label><span class="po-select"><select id="vs-f-member"></select><?= v2_ic('caret-down') ?></span></span>
        <div class="ve-two">
          <span class="po-field"><label for="vs-f-start"><?= v2_te('Starts') ?></label><input class="po-input" type="datetime-local" id="vs-f-start"></span>
          <span class="po-field"><label for="vs-f-end"><?= v2_te('Ends') ?></label><input class="po-input" type="datetime-local" id="vs-f-end"></span>
        </div>
        <div class="ve-two">
          <span class="po-field"><label for="vs-f-role"><?= v2_te('What they do') ?></label><span class="po-select"><select id="vs-f-role">
            <?php foreach ($vtRoles as $key => $label): ?><option value="<?= $key ?>"><?= v2_e($label) ?></option><?php endforeach; ?>
          </select><?= v2_ic('caret-down') ?></span></span>
          <span class="po-field"><label for="vs-f-gate"><?= v2_te('Gate') ?></label><input class="po-input" id="vs-f-gate" maxlength="32" placeholder="<?= v2_te('A, B, Car park…') ?>" autocomplete="off"></span>
        </div>
        <span class="po-field"><label for="vs-f-notes"><?= v2_te('Notes') ?></label><textarea class="ve-ta" id="vs-f-notes" rows="2" maxlength="500"></textarea></span>
      </div>
      <div class="ve-modal-foot">
        <button class="ve-danger" type="button" id="vs-f-del" hidden><?= v2_ic('trash') ?><?= v2_te('Delete the shift') ?></button>
        <button class="btn btn-ghost" type="button" id="vs-f-cancel"><?= v2_te('Cancel') ?></button>
        <button class="btn btn-primary" type="button" id="vs-f-save"><?= v2_ic('check') ?><?= v2_te('Save') ?></button>
      </div>
    </div>
  </div>
</div>
<?php
v2_org_end();
include __DIR__ . '/../includes/v2/foot.php';
