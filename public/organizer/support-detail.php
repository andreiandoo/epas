<?php
/**
 * Operator support ticket: /organizator/suport/{id} (support-detail.php?id=), v2 design.
 *
 * Inside the v2 operator shell. Every section of the old page, restyled: the way back to the list, the loading state,
 * the error states (ticket missing or not the operator's, access not activated during the beta, and now a failed load
 * that can be retried), the ticket (number, status, department, problem type, subject, opened on, the extra details it
 * was opened with: page URL, decont series and number, activity, module), "Marchează ca rezolvat" (confirmed in a dialog
 * instead of the browser's confirm) and "Redeschide tichetul", the conversation (messages with author, time and
 * attachments; status changes as lines between them), the reply with attachments and the "ticket closed" note.
 * org-support.js reads /organizer/support/tickets/{id}, replies as multipart (BileteOnlineAPI.organizer.replySupportTicket)
 * and posts …/close and …/reopen. ?trimis=1 (after a new ticket) says it was sent.
 *
 * Changed on the way: the activity in the details was a link to /organizator/events, which now sends operators to their
 * products: it is shown as text; the open-tickets badge in the menu follows a close or a reopen.
 */

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/api.php';
require_once __DIR__ . '/../includes/nav-helpers.php';
require_once __DIR__ . '/../includes/v2/helpers.php';
require_once __DIR__ . '/../includes/v2/organizer.php';

$sdtId = isset($_GET['id']) ? (int) $_GET['id'] : 0;
if ($sdtId <= 0) {
    header('Location: ' . v2_url('/organizator/suport'));
    exit;
}

$pageTitleRaw = v2_t('Support ticket') . ' · ' . SITE_NAME;
$pageDescription = v2_t('A support ticket of an operator on Viaqui: the conversation with our team and your reply.');
$canonicalUrl = SITE_URL . '/organizator/suport/' . $sdtId;
$noindex = true;
$skipPageCache = true;

$v2Styles = ['organizer.css', 'org-support.css'];
$v2Scripts = ['organizer.js', 'org-support.js'];
$v2LegacyScripts = ['assets/js/config.js', 'assets/js/utils.js', 'assets/js/api.js', 'assets/js/auth.js'];
$v2HeadExtra = v2_account_client_config('organizer');

include __DIR__ . '/../includes/v2/head.php';
v2_org_start('support');
?>
<div class="osp osd" id="osd" data-id="<?= $sdtId ?>">
  <nav class="osd-crumbs" aria-label="<?= v2_te('Breadcrumb') ?>">
    <a class="osd-back" href="/organizator/suport"><?= v2_ic('arrow-left') ?><?= v2_te('Back to tickets') ?></a>
    <span class="osd-crumb" aria-hidden="true">·</span>
    <span class="osd-crumb" id="osd-crumb" aria-current="page"><?= v2_te('Ticket') ?></span>
  </nav>

  <div class="org-panel osd-loading" id="osd-loading" role="status">
    <span class="org-skel osd-sk-a"></span><span class="org-skel osd-sk-b"></span>
    <p class="osd-loading-t"><?= v2_te('Loading the ticket…') ?></p>
  </div>

  <div class="org-empty is-error osd-error" id="osd-error" role="alert" hidden>
    <span class="org-empty-ic"><?= v2_ic('warning-circle') ?></span>
    <b id="osd-err-h"><?= v2_te('Ticket not found') ?></b>
    <p id="osd-err-p"><?= v2_te('Check the link or go back to the ticket list.') ?></p>
    <div class="osd-err-act">
      <button class="btn btn-primary" type="button" id="osd-retry" hidden><?= v2_te('Try again') ?></button>
      <a class="btn btn-ghost" href="/organizator/suport"><?= v2_ic('arrow-left') ?><?= v2_te('Back to tickets') ?></a>
    </div>
  </div>

  <div class="osd-content" id="osd-content" hidden>
    <header class="org-panel osd-head">
      <div class="osd-head-top">
        <div class="osd-head-t">
          <p class="osp-tags">
            <span class="osp-num" id="osd-num"></span>
            <span class="org-tag" id="osd-status"></span>
            <span class="osp-dept" id="osd-dept"></span>
            <span class="osp-dept" id="osd-pt" hidden></span>
          </p>
          <h1 class="osd-h" id="osd-subject"></h1>
          <p class="osd-opened"><?= v2_t('Opened on {date}', ['date' => '<time id="osd-opened"></time>']) ?></p>
        </div>
        <div class="osd-actions">
          <button class="btn btn-ghost" type="button" id="osd-close" hidden><?= v2_ic('check-circle') ?><?= v2_te('Mark as resolved') ?></button>
          <button class="btn btn-primary" type="button" id="osd-reopen" hidden><?= v2_ic('arrow-counter-clockwise') ?><span data-label><?= v2_te('Reopen ticket') ?></span></button>
        </div>
      </div>
      <dl class="osd-meta" id="osd-meta" hidden></dl>
    </header>

    <div class="osd-grid">
      <section class="org-panel osd-conv" aria-labelledby="osd-conv-h">
        <h2 class="osd-sec-h" id="osd-conv-h"><?= v2_te('Conversation') ?></h2>
        <ol class="osd-thread" id="osd-thread"></ol>
      </section>

      <div class="osd-side">
        <section class="org-panel osd-reply-card" id="osd-reply-card" aria-labelledby="osd-reply-h" hidden>
          <h2 class="osd-sec-h" id="osd-reply-h"><?= v2_te('Send a reply') ?></h2>
          <form class="osd-reply" id="osd-reply" novalidate>
            <label class="sr" for="osd-body"><?= v2_te('Your reply') ?></label>
            <textarea class="osp-in" id="osd-body" rows="6" maxlength="10000" required placeholder="<?= v2_te('Write your reply…') ?>" aria-describedby="osd-body-n osd-reply-err"></textarea>
            <p class="osp-help osp-count" id="osd-body-n"><?= v2_te('{n} / 10,000', ['n' => 0]) ?></p>
            <div class="osp-f">
              <label class="osp-l" for="osd-files"><?= v2_t('Attachments <small>(optional)</small>') ?></label>
              <input class="osp-file" type="file" id="osd-files" multiple accept=".jpg,.jpeg,.png,.pdf" aria-describedby="osd-files-help">
              <p class="osp-help" id="osd-files-help"><?= v2_te('{types}: {size} MB per file at most, up to {max} files.', ['types' => 'jpg, png, pdf', 'size' => 3, 'max' => 5]) ?></p>
              <ul class="osp-files" id="osd-files-list" aria-label="<?= v2_te('Chosen files') ?>" hidden></ul>
            </div>
            <p class="osp-err" id="osd-reply-err" role="alert" hidden></p>
            <div class="osd-reply-act"><button class="btn btn-primary" type="submit" id="osd-send"><span data-label><?= v2_te('Send reply') ?></span></button></div>
          </form>
        </section>
        <div class="osd-closed" id="osd-closed" hidden>
          <?= v2_ic('check-circle') ?>
          <p><?= v2_te('The ticket is closed. If the problem comes back, reopen it with the button above.') ?></p>
        </div>
      </div>
    </div>
  </div>

  <dialog class="osp-dialog is-small" id="osd-confirm" aria-labelledby="osd-confirm-h" aria-describedby="osd-confirm-p">
    <div class="osp-d-inner">
      <h2 class="osp-d-h" id="osd-confirm-h"><?= v2_te('Mark the ticket as resolved?') ?></h2>
      <p class="osp-d-p" id="osd-confirm-p"><?= v2_te('You can reopen it if the problem comes back.') ?></p>
      <div class="osp-d-act">
        <button class="btn btn-ghost" type="button" data-close><?= v2_te('Cancel') ?></button>
        <button class="btn btn-primary" type="button" id="osd-confirm-go"><span data-label><?= v2_te('Mark as resolved') ?></span></button>
      </div>
    </div>
  </dialog>
</div>
<?php
v2_org_end();
include __DIR__ . '/../includes/v2/foot.php';
