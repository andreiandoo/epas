<?php
/**
 * Organizer participants: /organizator/participanti (v2 design).
 *
 * Inside the v2 organizer shell. The participants of one activity (picked in a searchable list, ?event= preselects it):
 * total tickets, checked in, waiting and the check-in rate; capacity, tickets sold online and at the door by ticket type,
 * check-ins per hour with the peak hour; the list (participant, phone, ticket code, ticket type, order, status, actions)
 * with a check-in filter, ticket type, search and pages; check-in per row and by code (a dialog that stays open for the
 * next ticket), undoing a check-in, CSV export. org-participants.js reads /organizer/events,
 * /organizer/events/{id}/participants, POST|DELETE /organizer/events/{id}/check-in/{code} and the CSV export.
 *
 * Fixed on the way (the old page read fields core never sends):
 * - the four figures were always 0 (they come in meta.stats, the page read data.stats);
 * - names showed "-", the ticket column showed no code and every ticket had a "Check-in" button that sent an empty code:
 *   core sends customer / attendee objects, code, barcode and checked_in_at, not name, control_code and checked_in;
 * - only the first 50 tickets were listed, and only the first page of activities could be picked;
 * - the "Check-in făcut / În așteptare" filter was dropped by the proxy (now forwarded, with the ticket type);
 * - check-in errors came back in English, a ticket already checked in said nothing about when.
 */

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/api.php';
require_once __DIR__ . '/../includes/nav-helpers.php';
require_once __DIR__ . '/../includes/v2/helpers.php';
require_once __DIR__ . '/../includes/v2/organizer.php';

$pageTitle = v2_t('Participants');
$pageDescription = v2_t('The participants of your experiences on Viaqui: check-in, tickets by type and channel, CSV export.');
$canonicalUrl = SITE_URL . '/organizator/participanti';
$noindex = true;
$skipPageCache = true;

$v2Styles = ['organizer.css', 'org-participants.css'];
$v2Scripts = ['organizer.js', 'org-participants.js'];
$v2LegacyScripts = ['assets/js/config.js', 'assets/js/utils.js', 'assets/js/api.js', 'assets/js/auth.js'];
$v2HeadExtra = v2_account_client_config('organizer');

include __DIR__ . '/../includes/v2/head.php';
v2_org_start('participants');
?>
<div class="op" id="op">
  <header class="op-head">
    <div>
      <p class="org-k"><?= v2_te('Experiences') ?></p>
      <h1 class="op-h"><?= v2_te('Participants') ?></h1>
      <p class="op-lead"><?= v2_te('Manage the participants of your experiences.') ?></p>
    </div>
    <div class="op-head-act">
      <button class="btn btn-primary" type="button" id="op-manual" disabled><?= v2_ic('scan') ?><?= v2_te('Manual check-in') ?></button>
      <button class="btn btn-ghost" type="button" id="op-export" disabled><?= v2_ic('file-text') ?><span data-label><?= v2_te('Export CSV') ?></span></button>
    </div>
  </header>

  <section class="org-panel op-pick" aria-label="<?= v2_te('Experience') ?>">
    <div class="op-combo" id="op-combo">
      <label class="op-f-l" for="op-event-q" id="op-pick-l"><?= v2_te('Choose the experience') ?></label>
      <div class="op-combo-box">
        <?= v2_ic('magnifying-glass', 'ic op-combo-ic') ?>
        <input id="op-event-q" type="text" role="combobox" aria-expanded="false" aria-controls="op-event-list" aria-autocomplete="list" autocomplete="off" spellcheck="false" placeholder="<?= v2_te('Loading experiences…') ?>" disabled>
        <button class="op-combo-x" type="button" id="op-event-clear" aria-label="<?= v2_te('Clear the search') ?>" hidden><?= v2_ic('x') ?></button>
        <?= v2_ic('caret-down', 'ic op-combo-caret') ?>
      </div>
      <ul class="op-combo-list" id="op-event-list" role="listbox" aria-labelledby="op-pick-l" hidden></ul>
    </div>
    <div class="op-ev" id="op-ev" hidden>
      <p class="op-ev-when" id="op-ev-when"></p>
      <p class="op-ev-links"><a id="op-ev-edit" href="/organizator/activities"><?= v2_ic('pencil-simple') ?><?= v2_te('Edit the experience') ?></a><a id="op-ev-sales" href="/organizator/vanzari"><?= v2_ic('receipt') ?><?= v2_te('Sales') ?></a></p>
    </div>
  </section>

  <section class="op-stats" id="op-stats" aria-labelledby="op-stats-h">
    <h2 class="sr" id="op-stats-h"><?= v2_te('Check-in for the chosen experience') ?></h2>
    <article class="op-stat"><p class="op-stat-k"><?= v2_te('Total participants') ?></p><p class="op-stat-v" id="op-s-total"><span class="org-skel op-sk"></span></p></article>
    <article class="op-stat is-mint"><p class="op-stat-k"><?= v2_te('Checked in') ?></p><p class="op-stat-v" id="op-s-in"><span class="org-skel op-sk"></span></p></article>
    <article class="op-stat is-warm"><p class="op-stat-k"><?= v2_te('Waiting') ?></p><p class="op-stat-v" id="op-s-wait"><span class="org-skel op-sk"></span></p></article>
    <article class="op-stat is-rate"><p class="op-stat-k"><?= v2_te('Check-in rate') ?></p><p class="op-stat-v" id="op-s-rate"><span class="org-skel op-sk"></span></p><div class="op-meter" aria-hidden="true"><span id="op-s-rate-bar"></span></div></article>
  </section>

  <section class="op-insights" id="op-insights" aria-label="<?= v2_te('Tickets by channel and hour') ?>" hidden>
    <article class="org-panel op-card" id="op-cap-card" hidden>
      <p class="org-k"><?= v2_te('Capacity') ?></p>
      <h2 class="op-card-h" id="op-cap-h"></h2>
      <div class="op-meter is-big" aria-hidden="true"><span id="op-cap-bar"></span></div>
      <p class="op-card-p" id="op-cap-p"></p>
    </article>
    <article class="org-panel op-card" id="op-src-card">
      <p class="org-k"><?= v2_te('Channels') ?></p>
      <h2 class="op-card-h"><?= v2_te('Online and at the door') ?></h2>
      <div class="op-split" id="op-split" aria-hidden="true"><span class="is-online" id="op-split-on"></span><span class="is-door" id="op-split-door"></span></div>
      <div class="op-src" id="op-src"></div>
    </article>
    <article class="org-panel op-card" id="op-hour-card" hidden>
      <p class="org-k"><?= v2_te('Arrivals') ?></p>
      <h2 class="op-card-h"><?= v2_te('Check-ins by hour') ?></h2>
      <p class="op-card-p" id="op-peak"></p>
      <div class="op-hours" id="op-hours" role="img"></div>
    </article>
  </section>

  <section class="org-panel op-list" id="op-list" aria-labelledby="op-list-h">
    <div class="org-panel-head">
      <div><p class="org-k"><?= v2_te('Tickets') ?></p><h2 class="org-panel-h" id="op-list-h" tabindex="-1"><?= v2_te('Participant list') ?></h2><p class="org-panel-p" id="op-count"><?= v2_te('Loading…') ?></p></div>
    </div>
    <div class="op-filters">
      <div class="op-seg" role="group" aria-label="<?= v2_te('Filter by check-in') ?>">
        <button class="op-seg-b" type="button" data-checkin="" aria-pressed="true"><?= v2_te('All participants') ?> <b data-n="all"></b></button>
        <button class="op-seg-b" type="button" data-checkin="da" aria-pressed="false"><?= v2_te('Checked in') ?> <b data-n="in"></b></button>
        <button class="op-seg-b" type="button" data-checkin="nu" aria-pressed="false"><?= v2_te('Waiting') ?> <b data-n="wait"></b></button>
      </div>
      <div class="op-f-row">
        <label class="op-f is-type"><span class="op-f-l"><?= v2_te('Ticket type') ?></span><span class="op-select"><select id="op-type"><option value=""><?= v2_te('All types') ?></option></select><?= v2_ic('caret-down') ?></span></label>
        <label class="op-f is-q"><span class="op-f-l"><?= v2_te('Search participants') ?></span><span class="op-input"><?= v2_ic('magnifying-glass') ?><input type="search" id="op-q" maxlength="100" autocomplete="off" spellcheck="false" placeholder="<?= v2_te('Name, email, phone, ticket code…') ?>"></span></label>
      </div>
    </div>
    <p class="op-note" id="op-mode-note" hidden><?= v2_ic('info') ?><span></span></p>
    <p class="sr" id="op-live" aria-live="polite"></p>
    <div class="op-table-wrap" id="op-wrap">
      <table class="op-table" id="op-table">
        <caption class="sr"><?= v2_te('Participants of the chosen experience') ?></caption>
        <thead>
          <tr>
            <th scope="col"><?= v2_te('Participant') ?></th>
            <th scope="col"><?= v2_te('Phone') ?></th>
            <th scope="col"><?= v2_te('Ticket') ?></th>
            <th scope="col"><?= v2_te('Ticket type') ?></th>
            <th scope="col"><?= v2_te('Order') ?></th>
            <th scope="col"><?= v2_te('Status') ?></th>
            <th scope="col" class="is-act"><?= v2_te('Actions') ?></th>
          </tr>
        </thead>
        <tbody id="op-rows">
          <?php for ($i = 0; $i < 4; $i++): ?><tr class="is-skel" aria-hidden="true"><td colspan="7"><span class="org-skel op-sk-row"></span></td></tr><?php endfor; ?>
        </tbody>
      </table>
    </div>
    <div class="org-empty op-empty" id="op-empty" hidden></div>
    <nav class="op-pager" id="op-pager" aria-label="<?= v2_te('Participant list pages') ?>" hidden>
      <p class="op-page-info" id="op-page-info"></p>
      <div class="op-pages" id="op-pages"></div>
    </nav>
  </section>

  <dialog class="op-dialog" id="op-manual-d" aria-labelledby="op-manual-h">
    <form class="op-d-inner" id="op-manual-form" novalidate>
      <div class="op-d-head">
        <h2 class="op-d-h" id="op-manual-h"><?= v2_te('Manual check-in') ?></h2>
        <button class="op-d-x" type="button" data-close aria-label="<?= v2_te('Close') ?>"><?= v2_ic('x') ?></button>
      </div>
      <p class="op-d-ev" id="op-manual-ev"></p>
      <label class="op-f">
        <span class="op-f-l"><?= v2_te('Control code') ?></span>
        <input class="op-code-in" id="op-code" type="text" autocomplete="off" autocapitalize="characters" spellcheck="false" maxlength="300" placeholder="<?= v2_te('E.g. X1SG7TLS') ?>" aria-describedby="op-code-help op-code-err">
        <span class="op-help" id="op-code-help"><?= v2_te('Enter the control code shown under the QR code of the ticket.') ?></span>
        <span class="op-err" id="op-code-err" hidden></span>
      </label>
      <button class="btn btn-primary op-d-go" type="submit" id="op-code-go"><?= v2_ic('check') ?><span data-label><?= v2_te('Verify and check in') ?></span></button>
      <div class="op-result" id="op-result" role="status" aria-live="polite" hidden></div>
    </form>
  </dialog>

  <dialog class="op-dialog is-small" id="op-undo-d" aria-labelledby="op-undo-h">
    <div class="op-d-inner">
      <h2 class="op-d-h" id="op-undo-h"><?= v2_te('Undo the check-in?') ?></h2>
      <p class="op-d-p" id="op-undo-p"></p>
      <div class="op-d-act">
        <button class="btn btn-ghost" type="button" data-close><?= v2_te('Keep it') ?></button>
        <button class="btn op-danger" type="button" id="op-undo-ok"><?= v2_te('Undo the check-in') ?></button>
      </div>
    </div>
  </dialog>
</div>
<?php
v2_org_end();
include __DIR__ . '/../includes/v2/foot.php';
