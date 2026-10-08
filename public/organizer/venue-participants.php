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

$pageTitleRaw = v2_t('Venue participants: {site}', ['site' => SITE_NAME]);
$pageDescription = v2_t('The venue\'s visitors: who bought, who checked in, and manual check-in when the code cannot be scanned.');
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
      <p class="ve-eyebrow"><?= v2_ic('users-three') ?><?= v2_te('Venue · Participants') ?></p>
      <h1 class="ve-h"><?= v2_te('Participants') ?></h1>
      <p class="ve-lead"><?= v2_te('Who bought tickets for the venue and who checked in. Filters are applied on the server, so the figures at the top always match the list.') ?></p>
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
    <b><?= v2_te('We could not load the participants') ?></b>
    <p><?= v2_te('Try again in a few seconds.') ?></p>
    <button class="btn btn-primary" type="button" id="ve-retry"><?= v2_te('Try again') ?></button>
  </div>

  <div class="ve-main" id="ve-main" hidden>
    <section class="org-panel" aria-labelledby="vp-filters-h">
      <h2 class="ve-sr" id="vp-filters-h"><?= v2_te('Filters') ?></h2>
      <div class="ve-filters">
        <div class="ve-ranges" role="group" aria-label="<?= v2_te('Payment period') ?>">
          <?php foreach ([['7', v2_t('Last 7 days')], ['14', v2_t('14 days')], ['30', v2_t('One month')], ['90', v2_t('3 months')], ['180', v2_t('6 months')], ['custom', v2_t('Custom period')]] as $i => [$val, $label]): ?>
          <button class="ve-range" type="button" data-range="<?= $val ?>" aria-pressed="<?= $val === '30' ? 'true' : 'false' ?>"><?= v2_e($label) ?></button>
          <?php endforeach; ?>
        </div>
        <div class="ve-dates" id="vp-custom" hidden>
          <span class="po-field"><label for="vp-from"><?= v2_te('From') ?></label><input class="po-input" type="date" id="vp-from"></span>
          <span class="po-field"><label for="vp-to"><?= v2_te('To') ?></label><input class="po-input" type="date" id="vp-to"></span>
          <button class="btn btn-ghost" type="button" id="vp-apply"><?= v2_te('Apply') ?></button>
        </div>

        <div class="ve-filter-row">
          <label class="ve-search"><?= v2_ic('magnifying-glass') ?><input id="vp-q" type="search" autocomplete="off" placeholder="<?= v2_te('Search by name, email or ticket code') ?>" aria-label="<?= v2_te('Search participants') ?>"></label>
          <span class="po-select"><select id="vp-status" aria-label="<?= v2_te('Ticket status') ?>"><option value=""><?= v2_te('All statuses') ?></option><option value="valid"><?= v2_te('Valid') ?></option><option value="used"><?= v2_te('Used') ?></option><option value="cancelled"><?= v2_te('Cancelled') ?></option><option value="refunded"><?= v2_te('Refunded') ?></option></select><?= v2_ic('caret-down') ?></span>
          <span class="po-select"><select id="vp-checkin" aria-label="<?= v2_te('Check-in') ?>"><option value=""><?= v2_te('Checked in or not') ?></option><option value="checked_in"><?= v2_te('Checked in only') ?></option><option value="not_checked_in"><?= v2_te('Not checked in only') ?></option></select><?= v2_ic('caret-down') ?></span>
          <details class="po-fold ve-types" id="vp-types">
            <summary><?= v2_ic('ticket') ?><span id="vp-types-label"><?= v2_te('All types') ?></span></summary>
            <div class="po-fold-in" id="vp-types-list"><p class="ve-state"><?= v2_te('Loading…') ?></p></div>
          </details>
          <button class="ve-reset" type="button" id="vp-reset" hidden><?= v2_ic('x') ?><?= v2_te('Clear filters') ?></button>
        </div>

        <div class="ve-tools">
          <span class="ve-dates"><span><?= v2_te('Visit date:') ?></span>
            <span class="po-field"><label class="ve-sr" for="vp-visit-from"><?= v2_te('Visit from') ?></label><input class="po-input" type="date" id="vp-visit-from"></span>
            <span class="po-field"><label class="ve-sr" for="vp-visit-to"><?= v2_te('Visit to') ?></label><input class="po-input" type="date" id="vp-visit-to"></span>
          </span>
          <button class="btn btn-ghost" type="button" id="vp-csv"><?= v2_ic('download-simple') ?><?= v2_te('Export CSV') ?></button>
        </div>
      </div>
    </section>

    <section class="ve-kpis" aria-label="<?= v2_te('In short') ?>">
      <?= $vpStat('total', v2_te('Participants'), '', v2_te('Access tickets; packages are counted by their parts')) ?>
      <?= $vpStat('checked', v2_te('Checked in'), '') ?>
      <?= $vpStat('rate', v2_te('Check-in rate'), '') ?>
      <?= $vpStat('noshow', v2_te('No-shows'), '') ?>
    </section>

    <section class="org-panel" aria-labelledby="vp-list-h">
      <div class="org-panel-head">
        <div><h2 class="org-panel-h" id="vp-list-h"><?= v2_te('Participant list') ?></h2><p class="org-panel-p" id="vp-period">—</p></div>
      </div>
      <div class="ve-table-wrap"><table class="ve-table ve-part-table">
        <thead><tr>
          <th scope="col"><?= v2_te('Order') ?></th><th scope="col"><?= v2_te('Ticket code') ?></th><th scope="col"><?= v2_te('Customer') ?></th><th scope="col"><?= v2_te('Number plate') ?></th>
          <th scope="col"><?= v2_te('Ticket type') ?></th><th scope="col"><?= v2_te('Visit date') ?></th><th scope="col"><?= v2_te('Status') ?></th><th scope="col"><?= v2_te('Check-in') ?></th>
        </tr></thead>
        <tbody id="vp-rows"><tr><td colspan="8" class="ve-state"><?= v2_te('Loading…') ?></td></tr></tbody>
      </table></div>
      <p class="ve-count" id="vp-count"></p>
      <div class="ve-more"><button class="btn btn-ghost" type="button" id="vp-more" hidden><?= v2_te('Load more') ?></button></div>
    </section>
  </div>
</div>
<?php
v2_org_end();
include __DIR__ . '/../includes/v2/foot.php';
