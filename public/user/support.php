<?php
/**
 * Customer support tickets: /cont/tichete-support and /cont/tichete-support/{id} (v2 design).
 *
 * Inside the v2 account shell (includes/v2/account.php). Sections: hero with the open-ticket count, the ticket list
 * with status filters, empty and error states, the new-ticket dialog (department, problem type, subject, message,
 * priority), the thread dialog with the reply form, and the login prompt. support.js talks to
 * /customer/support-tickets, /customer/support-tickets/{id}, /{id}/messages and /customer/support-meta.
 * /cont/tichete-support/{id} (or #t-<id>) opens that ticket.
 *
 * Fixed on the way: creating a ticket never worked. api.js maps POST /customer/support-tickets to the list action and
 * the proxy forced that action to GET, so "Trimite tichetul" loaded the list, got a success back, closed the form, and
 * no ticket was created. The proxy now dispatches on the method, and the page only reports success when the answer
 * carries the new ticket. /cont/tichete-support/{id} pointed at a file that doesn't exist; it now opens the ticket here.
 */

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/api.php';
require_once __DIR__ . '/../includes/nav-helpers.php';
require_once __DIR__ . '/../includes/v2/helpers.php';
require_once __DIR__ . '/../includes/v2/nav.php';
require_once __DIR__ . '/../includes/v2/account.php';

$spTicket = isset($_GET['ticket']) && ctype_digit((string) $_GET['ticket']) ? (int) $_GET['ticket'] : 0;

$pageTitleRaw = v2_t('Support tickets: {site}', ['site' => SITE_NAME]);
$pageDescription = v2_t('Send and follow your requests to the Viaqui team.');
$canonicalUrl = SITE_URL . '/account/support';
$noindex = true;
$skipPageCache = true;

$v2Styles = ['account.css', 'support.css'];
$v2Scripts = ['account.js', 'support.js'];
$v2LegacyScripts = ['assets/js/config.js', 'assets/js/utils.js', 'assets/js/api.js', 'assets/js/auth.js'];
$v2HeadExtra = v2_account_client_config();
$v2FooterCompact = true;
$v2ClientData = ['ticket' => $spTicket];

include __DIR__ . '/../includes/v2/head.php';
include __DIR__ . '/../includes/v2/header.php';
?>
<main id="main" tabindex="-1" class="page-main acc-page">
  <div class="wrap">
    <?php v2_account_start('support'); ?>

    <!-- not signed in -->
    <section class="acc-guard" id="sp-guard" hidden aria-labelledby="sp-guard-h">
      <span class="acc-guard-ic" aria-hidden="true"><?= v2_ic('lock-simple') ?></span>
      <h1 id="sp-guard-h"><?= v2_te('You need to sign in') ?></h1>
      <p><?= v2_te('Sign in to see your support tickets.') ?></p>
      <a class="btn btn-primary" href="/login?redirect=%2Faccount%2Fsupport"><?= v2_te('Sign in') ?><?= v2_ic('arrow-right') ?></a>
    </section>

    <div class="acc-body" id="sp-content">
      <!-- HERO -->
      <section class="acc-hero sp-hero" aria-labelledby="sp-h">
        <div>
          <p class="acc-kicker"><?= v2_te('Customer support') ?></p>
          <h1 class="acc-h" id="sp-h"><?= v2_te('Support tickets') ?></h1>
          <p class="acc-lead"><?= v2_te('Send a request to the team and follow the status of every ticket in one place.') ?></p>
          <div class="sp-cta">
            <button class="btn btn-light" type="button" data-sp-new><?= v2_ic('plus') ?><?= v2_te('New ticket') ?></button>
            <a class="btn btn-outline-light" href="/help"><?= v2_te('See the FAQ') ?></a>
          </div>
        </div>
        <article class="sp-count" aria-labelledby="sp-count-k">
          <p class="acc-k" id="sp-count-k"><?= v2_te('Open') ?></p>
          <p class="sp-count-v" id="sp-open">0</p>
          <p class="sp-count-l"><?= v2_te('active requests') ?></p>
          <p class="sp-count-t" id="sp-total"><?= v2_te('{tickets} in total', ['tickets' => v2_num(0, 'ticket', 'tickets')]) ?></p>
        </article>
      </section>

      <!-- TICKETS -->
      <section class="acc-results" id="tichete" aria-labelledby="sp-count">
        <div class="acc-results-head">
          <div><p class="acc-k"><?= v2_te('Requests') ?></p><h2 class="acc-count" id="sp-count"><?= v2_e(v2_num(0, 'ticket', 'tickets')) ?></h2></div>
          <div class="acc-bulk"><button class="btn btn-primary" type="button" data-sp-new><?= v2_ic('plus') ?><?= v2_te('New ticket') ?></button></div>
        </div>
        <div class="acc-pills sp-pills" role="group" aria-label="<?= v2_te('Filter the tickets') ?>">
          <button class="acc-pill" type="button" data-filter="all" aria-pressed="true"><?= v2_te('All') ?></button>
          <button class="acc-pill is-warn" type="button" data-filter="active" aria-pressed="false"><?= v2_te('Active') ?></button>
          <button class="acc-pill" type="button" data-filter="closed" aria-pressed="false"><?= v2_te('Closed') ?></button>
        </div>
        <p class="acc-status" id="sp-status-line" role="status"></p>
        <div class="acc-skel sp-skel" id="sp-skel" aria-hidden="true"><i></i><i></i></div>
        <ul class="sp-list" id="sp-list" hidden></ul>
        <div class="acc-empty" id="sp-empty" hidden>
          <span class="acc-empty-ic" aria-hidden="true"><?= v2_ic('headset') ?></span>
          <b id="sp-empty-h"><?= v2_te('No open tickets') ?></b>
          <p id="sp-empty-p"><?= v2_te('Send the team a request if you need help.') ?></p>
          <button class="btn btn-primary" type="button" data-sp-new id="sp-empty-new"><?= v2_te('New ticket') ?></button>
        </div>
        <div class="acc-empty is-error" id="sp-error" hidden>
          <b><?= v2_te('We could not load your tickets.') ?></b>
          <p><?= v2_te('Check your connection and try again.') ?></p>
          <button class="btn btn-ghost" type="button" id="sp-retry"><?= v2_te('Try again') ?></button>
        </div>
      </section>
    </div>

    <!-- NEW TICKET -->
    <dialog class="sp-dialog" id="sp-new" aria-labelledby="sp-new-h">
      <form class="sp-form" id="sp-new-form" novalidate>
        <div class="sp-d-head">
          <div><p class="acc-k"><?= v2_te('Support') ?></p><h2 id="sp-new-h"><?= v2_te('New ticket') ?></h2></div>
          <button class="sp-d-close" type="button" data-close aria-label="<?= v2_te('Close') ?>"><?= v2_ic('x') ?></button>
        </div>
        <div class="sp-d-body">
          <p class="sp-d-intro"><?= v2_te('Tell us what happened. If it is about an order, add its number so we can help you faster.') ?></p>
          <div class="acc-field" id="sp-dep-field" hidden>
            <label for="sp-dep"><?= v2_te('Department') ?></label>
            <span class="acc-select"><select id="sp-dep"><option value=""><?= v2_te('choose') ?></option></select><?= v2_ic('caret-down') ?></span>
          </div>
          <div class="acc-field" id="sp-type-field" hidden>
            <label for="sp-type"><?= v2_te('Type of problem') ?></label>
            <span class="acc-select"><select id="sp-type"><option value=""><?= v2_te('choose') ?></option></select><?= v2_ic('caret-down') ?></span>
          </div>
          <div class="acc-field">
            <label for="sp-subject"><?= v2_te('Subject') ?></label>
            <span class="acc-input is-plain"><input id="sp-subject" maxlength="200" autocomplete="off" required aria-describedby="sp-new-error"></span>
          </div>
          <div class="acc-field">
            <label for="sp-message"><?= v2_te('Message') ?></label>
            <textarea class="sp-textarea" id="sp-message" maxlength="5000" rows="6" required aria-describedby="sp-message-count sp-new-error"></textarea>
            <small class="sp-counter" id="sp-message-count">0 / 5,000</small>
          </div>
          <div class="acc-field">
            <label for="sp-priority"><?= v2_te('Priority') ?></label>
            <span class="acc-select"><select id="sp-priority">
              <option value="normal" selected><?= v2_te('Normal') ?></option>
              <option value="high"><?= v2_te('High') ?></option>
              <option value="urgent"><?= v2_te('Urgent') ?></option>
              <option value="low"><?= v2_te('Low') ?></option>
            </select><?= v2_ic('caret-down') ?></span>
          </div>
          <p class="sp-error" id="sp-new-error" role="alert" hidden></p>
        </div>
        <div class="sp-d-foot">
          <button class="btn btn-primary" type="submit" id="sp-new-submit"><?= v2_te('Send ticket') ?></button>
          <button class="btn btn-ghost" type="button" data-close><?= v2_te('Cancel') ?></button>
        </div>
      </form>
    </dialog>

    <!-- THREAD -->
    <dialog class="sp-dialog is-thread" id="sp-thread" aria-labelledby="sp-t-h">
      <div class="sp-d-head">
        <div class="sp-t-head">
          <p class="acc-k" id="sp-t-num">—</p>
          <h2 id="sp-t-h"><?= v2_te('Ticket') ?></h2>
          <div class="sp-tags" id="sp-t-tags"></div>
        </div>
        <button class="sp-d-close" type="button" data-close aria-label="<?= v2_te('Close') ?>"><?= v2_ic('x') ?></button>
      </div>
      <div class="sp-d-body" id="sp-t-body">
        <p class="sp-t-state" id="sp-t-state" role="status"><?= v2_te('Loading the conversation…') ?></p>
        <ol class="sp-thread" id="sp-t-messages" aria-label="<?= v2_te('Messages') ?>"></ol>
        <p class="sp-closed" id="sp-t-closed" hidden><?= v2_te('This ticket is closed. Open a new one for a new request.') ?></p>
      </div>
      <form class="sp-d-foot sp-reply" id="sp-reply" hidden novalidate>
        <p class="sp-reopen" id="sp-reopen" hidden><?= v2_te('Is the problem not solved? Reply here and the ticket opens again.') ?></p>
        <label class="sp-sr" for="sp-reply-text"><?= v2_te('Your reply') ?></label>
        <textarea class="sp-textarea" id="sp-reply-text" maxlength="5000" rows="3" placeholder="<?= v2_te('Reply…') ?>" required aria-describedby="sp-reply-count sp-reply-error"></textarea>
        <p class="sp-error" id="sp-reply-error" role="alert" hidden></p>
        <div class="sp-reply-row">
          <small class="sp-counter" id="sp-reply-count">0 / 5,000</small>
          <button class="btn btn-primary" type="submit" id="sp-reply-submit" disabled><?= v2_te('Send reply') ?></button>
        </div>
      </form>
    </dialog>

    <?php v2_account_end(); ?>
  </div>
</main>
<?php include __DIR__ . '/../includes/v2/footer.php'; ?>
