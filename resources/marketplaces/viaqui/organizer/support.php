<?php
/**
 * Operator support tickets: /organizator/suport (support.php), v2 design.
 *
 * Inside the v2 operator shell. Every section of the old page, restyled: the "Tichet nou" button, the beta gate notice
 * (core answers 403 while the account is not on the support allow-list), the status filters (Active, Toate, Rezolvate,
 * Închise), the ticket list (number, status, department, subject, messages, last activity, opened on), the loading and
 * empty states, and the new-ticket dialog: step 1 is the department (with its description), the problem type and the
 * extra fields that type asks for (page URL, decont series and number, affected module, activity); step 2 is the
 * subject, the description and the attachments (types, size and count from core's rules, with a preview).
 * org-support.js reads /organizer/support/tickets and /organizer/support/departments through the proxy and sends the
 * ticket as multipart (BileteOnlineAPI.organizer.createSupportTicket) with the page context, like before.
 *
 * Changed on the way:
 * - a failed list load showed the empty state plus a toast: it now says so, with "Reîncearcă";
 * - core pages the list by 20: "Încarcă mai multe" reaches the older tickets;
 * - the success message is shown on the ticket page the operator is sent to (it used to vanish with the redirect);
 * - the problem type's description from core is shown under the select; an activity field with no activities in the
 *   account says so instead of offering an empty list.
 */

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/api.php';
require_once __DIR__ . '/../includes/nav-helpers.php';
require_once __DIR__ . '/../includes/v2/helpers.php';
require_once __DIR__ . '/../includes/v2/organizer.php';

$pageTitleRaw = v2_t('Support tickets') . ' · ' . SITE_NAME;
$pageDescription = v2_t('The support tickets of an operator on Viaqui: open a ticket and follow the reply from our team.');
$canonicalUrl = SITE_URL . '/organizator/suport';
$noindex = true;
$skipPageCache = true;

$v2Styles = ['organizer.css', 'org-support.css'];
$v2Scripts = ['organizer.js', 'org-support.js'];
$v2LegacyScripts = ['assets/js/config.js', 'assets/js/utils.js', 'assets/js/api.js', 'assets/js/auth.js'];
$v2HeadExtra = v2_account_client_config('organizer');

$spFilters = [['open', v2_t('Active')], ['', v2_t('All')], ['resolved', v2_t('Resolved')], ['closed', v2_t('Closed')]];

include __DIR__ . '/../includes/v2/head.php';
v2_org_start('support');
?>
<div class="osp" id="osp">
  <header class="osp-head">
    <div>
      <p class="org-k"><?= v2_te('Support') ?></p>
      <h1 class="osp-h"><?= v2_te('Support tickets') ?></h1>
      <p class="osp-lead"><?= v2_te('Open a ticket with our support team and follow its status.') ?></p>
    </div>
    <button class="btn btn-primary" type="button" data-osp-new><?= v2_ic('plus') ?><?= v2_te('New ticket') ?></button>
  </header>

  <div class="osp-gate" id="osp-gate" role="note" tabindex="-1" hidden>
    <span class="osp-gate-ic"><?= v2_ic('warning-circle') ?></span>
    <div>
      <h2 class="osp-gate-h"><?= v2_te('The ticket system is being tested') ?></h2>
      <p><?= v2_te('Access is not turned on for your account yet. For urgent questions, contact the team through the usual channels.') ?></p>
      <a class="osp-link" href="/organizator/help#contact"><?= v2_te('See the contact details') ?><?= v2_ic('arrow-right') ?></a>
    </div>
  </div>

  <section class="org-panel osp-panel" id="osp-panel" aria-labelledby="osp-list-h">
    <div class="org-panel-head">
      <div><p class="org-k"><?= v2_te('History') ?></p><h2 class="org-panel-h" id="osp-list-h"><?= v2_te('Your tickets') ?></h2><p class="org-panel-p" id="osp-list-p"></p></div>
      <div class="osp-seg" role="group" aria-label="<?= v2_te('Filter by status') ?>">
        <?php foreach ($spFilters as $i => [$spValue, $spLabel]): ?><button type="button" data-status="<?= $spValue ?>" aria-pressed="<?= $i === 0 ? 'true' : 'false' ?>" aria-controls="osp-list"><?= v2_e($spLabel) ?></button><?php endforeach; ?>
      </div>
    </div>
    <p class="sr" id="osp-live" role="status" aria-live="polite"><?= v2_te('Loading…') ?></p>
    <ul class="osp-list" id="osp-list">
      <li class="osp-sk" aria-hidden="true"><span class="org-skel"></span></li>
      <li class="osp-sk" aria-hidden="true"><span class="org-skel"></span></li>
      <li class="osp-sk" aria-hidden="true"><span class="org-skel"></span></li>
    </ul>
    <div class="org-empty" id="osp-empty" hidden>
      <span class="org-empty-ic"><?= v2_ic('envelope-simple') ?></span>
      <b><?= v2_te('You have no tickets here') ?></b>
      <p><?= v2_te('When you have a problem or a question, open a ticket and our team will reply.') ?></p>
      <button class="btn btn-primary" type="button" data-osp-new><?= v2_ic('plus') ?><?= v2_te('Open your first ticket') ?></button>
    </div>
    <div class="org-empty is-error" id="osp-error" role="alert" hidden>
      <span class="org-empty-ic"><?= v2_ic('warning-circle') ?></span>
      <b><?= v2_te('We could not load the tickets') ?></b>
      <p><?= v2_te('Try again in a few seconds.') ?></p>
      <button class="btn btn-primary" type="button" id="osp-retry"><?= v2_te('Try again') ?></button>
    </div>
    <div class="osp-more-row"><button class="btn btn-ghost" type="button" id="osp-more" hidden><span data-label><?= v2_te('Load more') ?></span></button></div>
  </section>

  <!-- ============ NEW TICKET ============ -->
  <dialog class="osp-dialog" id="osp-new" aria-labelledby="osp-new-h">
    <form class="osp-form" id="osp-form" novalidate>
      <div class="osp-d-head">
        <div>
          <p class="org-k"><?= v2_te('Support') ?></p>
          <h2 class="osp-d-h" id="osp-new-h"><?= v2_te('New ticket') ?></h2>
        </div>
        <button class="osp-x" type="button" data-close aria-label="<?= v2_te('Close') ?>"><?= v2_ic('x') ?></button>
      </div>
      <ol class="osp-steps" aria-label="<?= v2_te('Ticket steps') ?>">
        <li id="osp-st-1" aria-current="step"><span>1</span><?= v2_te('Problem category') ?></li>
        <li id="osp-st-2"><span>2</span><?= v2_te('Details') ?></li>
      </ol>
      <div class="osp-d-body" id="osp-d-body">
        <fieldset class="osp-fs">
          <legend class="osp-step-k" id="osp-s1-h"><?= v2_te('Step 1: Problem category') ?></legend>
          <div class="osp-f">
            <label class="osp-l" for="osp-dept"><?= v2_te('Department') ?> <span class="osp-req" aria-hidden="true">*</span></label>
            <span class="osp-select"><select id="osp-dept" required aria-describedby="osp-dept-desc"><option value=""><?= v2_te('Choose a department…') ?></option></select><?= v2_ic('caret-down') ?></span>
            <p class="osp-help" id="osp-dept-desc" hidden></p>
            <p class="osp-help" id="osp-dept-msg" role="status"></p>
          </div>
          <div class="osp-f" id="osp-pt-f" hidden>
            <label class="osp-l" for="osp-pt"><?= v2_te('Problem type') ?> <span class="osp-req" aria-hidden="true">*</span></label>
            <span class="osp-select"><select id="osp-pt" required aria-describedby="osp-pt-desc"><option value=""><?= v2_te('Choose the type…') ?></option></select><?= v2_ic('caret-down') ?></span>
            <p class="osp-help" id="osp-pt-desc" hidden></p>
          </div>
          <div class="osp-cf" id="osp-cf" hidden></div>
        </fieldset>

        <fieldset class="osp-fs osp-step2" id="osp-step2" hidden>
          <legend class="osp-step-k" id="osp-s2-h"><?= v2_te('Step 2: Details') ?></legend>
          <div class="osp-f">
            <label class="osp-l" for="osp-subject"><?= v2_te('Subject') ?> <span class="osp-req" aria-hidden="true">*</span></label>
            <input class="osp-in" type="text" id="osp-subject" required maxlength="255" autocomplete="off" placeholder="<?= v2_te('In short: what happened?') ?>">
          </div>
          <div class="osp-f">
            <label class="osp-l" for="osp-desc"><?= v2_te('Description') ?> <span class="osp-req" aria-hidden="true">*</span></label>
            <textarea class="osp-in" id="osp-desc" required rows="6" maxlength="10000" aria-describedby="osp-desc-n" placeholder="<?= v2_te('Describe the problem in detail: when it happened, what you did before, the exact error message. The more information you give, the faster we can reply.') ?>"></textarea>
            <p class="osp-help osp-count" id="osp-desc-n"><?= v2_te('{n} / 10,000', ['n' => 0]) ?></p>
          </div>
          <div class="osp-f">
            <label class="osp-l" for="osp-files"><?= v2_t('Attachments <small>(optional)</small>') ?></label>
            <input class="osp-file" type="file" id="osp-files" multiple accept=".jpg,.jpeg,.png,.pdf" aria-describedby="osp-files-help">
            <p class="osp-help" id="osp-files-help"><?= v2_te('{types}: {size} MB per file at most, up to {max} files.', ['types' => 'jpg, png, pdf', 'size' => 3, 'max' => 5]) ?></p>
            <ul class="osp-files" id="osp-files-list" aria-label="<?= v2_te('Chosen files') ?>" hidden></ul>
          </div>
        </fieldset>
        <p class="osp-err" id="osp-form-err" role="alert" hidden></p>
      </div>
      <div class="osp-d-foot">
        <button class="btn btn-ghost" type="button" data-close><?= v2_te('Cancel') ?></button>
        <button class="btn btn-primary" type="submit" id="osp-send" disabled><span data-label><?= v2_te('Send ticket') ?></span></button>
      </div>
    </form>
  </dialog>
</div>
<?php
v2_org_end();
include __DIR__ . '/../includes/v2/foot.php';
