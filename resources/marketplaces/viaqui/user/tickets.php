<?php
/**
 * Customer tickets: /cont/bilete and /cont/biletele-mele (v2 design).
 *
 * Inside the v2 account shell (includes/v2/account.php). Sections: hero with the next ticket's QR, four counters,
 * filters (search, status, city, sort, quick filters), the tickets, three info cards (QR, beneficiaries, refunds), the
 * QR dialog, and the login prompt for visitors without a customer session. tickets.js loads
 * GET /customer/tickets/all?filter=all and keeps the filters in the address bar.
 *
 * Fixed on the way: the QR library URL was a 404 (the big QR never appeared); calendar links used a proxy action that
 * doesn't exist (now an .ics file made in the browser); "Editează nume" and "Retur" opened pages that don't exist (now
 * the contact form with the matching reason); the PDF link downloaded any ticket id without logging in (the proxy now
 * checks the ticket is the signed-in customer's, and the page sends the session token).
 */

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/api.php';
require_once __DIR__ . '/../includes/nav-helpers.php';
require_once __DIR__ . '/../includes/v2/helpers.php';
require_once __DIR__ . '/../includes/v2/nav.php';
require_once __DIR__ . '/../includes/v2/account.php';

$pageTitleRaw = v2_t('My tickets: {site}', ['site' => SITE_NAME]);
$pageDescription = v2_t('Your tickets on Viaqui: QR codes, PDFs, guest names, calendar, status, refunds and ticket protection.');
$canonicalUrl = SITE_URL . '/account/tickets';
$noindex = true;
$skipPageCache = true;

$v2Styles = ['account.css', 'tickets.css'];
$v2Scripts = ['vendor/qrcode.js', 'account.js', 'tickets.js'];
$v2LegacyScripts = ['assets/js/config.js', 'assets/js/utils.js', 'assets/js/api.js', 'assets/js/auth.js'];
$v2HeadExtra = v2_account_client_config();
$v2FooterCompact = true;

include __DIR__ . '/../includes/v2/head.php';
include __DIR__ . '/../includes/v2/header.php';
?>
<main id="main" tabindex="-1" class="page-main acc-page">
  <div class="wrap">
    <?php v2_account_start('tickets'); ?>

    <!-- not signed in -->
    <section class="acc-guard" id="tk-guard" hidden aria-labelledby="tk-guard-h">
      <span class="acc-guard-ic" aria-hidden="true"><?= v2_ic('lock-simple') ?></span>
      <h1 id="tk-guard-h"><?= v2_te('You need to sign in') ?></h1>
      <p><?= v2_te('Sign in to see your tickets.') ?></p>
      <a class="btn btn-primary" href="/login?redirect=%2Faccount%2Ftickets"><?= v2_te('Sign in') ?><?= v2_ic('arrow-right') ?></a>
    </section>

    <div id="tk-content">
      <!-- HERO -->
      <section class="tk-hero" aria-labelledby="tk-h">
        <div class="tk-hero-copy">
          <p class="tk-kicker"><?= v2_te('Your tickets') ?></p>
          <h1 class="tk-h" id="tk-h"><?= v2_te('My tickets') ?></h1>
          <p class="tk-lead"><?= v2_te('All your tickets in one place: QR codes, PDFs, guest names, calendar, status, access and refund options.') ?></p>
          <div class="tk-cta">
            <a class="btn btn-light" href="#bilete"><?= v2_ic('ticket') ?><?= v2_te('View tickets') ?></a>
            <a class="btn btn-outline-light" href="/find-order"><?= v2_te('Find an order') ?></a>
          </div>
        </div>
        <article class="tk-next" aria-labelledby="tk-next-t">
          <p class="tk-k"><?= v2_te('Next QR') ?></p>
          <h2 class="tk-next-t" id="tk-next-t"><?= v2_te('Coming soon') ?></h2>
          <p class="tk-next-sub" id="tk-next-sub"><?= v2_te('You have no upcoming tickets') ?></p>
          <div class="tk-next-row">
            <div>
              <p class="tk-next-n"><span id="tk-next-n">0</span>x</p>
              <p class="tk-next-l"><?= v2_te('upcoming tickets') ?></p>
            </div>
            <button class="tk-next-qr" type="button" id="tk-next-qr" disabled aria-label="<?= v2_te('QR code of your next ticket') ?>">
              <span class="tk-qr-slot" id="tk-next-qr-slot"><?= v2_ic('qr-code') ?></span>
              <small><?= v2_te('Open QR') ?></small>
              <i class="tk-scan" aria-hidden="true"></i>
            </button>
          </div>
        </article>
      </section>

      <!-- COUNTERS -->
      <section class="tk-stats" aria-label="<?= v2_te('At a glance') ?>">
        <article class="tk-stat"><p class="tk-k"><?= v2_te('Upcoming tickets') ?></p><p class="tk-stat-v" id="tk-c-upcoming">0</p><p class="tk-stat-p" id="tk-c-activities"><?= v2_te('in {activities}', ['activities' => v2_num(0, 'activity', 'activities')]) ?></p></article>
        <article class="tk-stat is-mint"><p class="tk-k"><?= v2_te('Valid') ?></p><p class="tk-stat-v" id="tk-c-valid">0</p><p class="tk-stat-p"><?= v2_te('ready to scan') ?></p></article>
        <article class="tk-stat"><p class="tk-k"><?= v2_te('Scanned') ?></p><p class="tk-stat-v" id="tk-c-used">0</p><p class="tk-stat-p"><?= v2_te('full history') ?></p></article>
        <article class="tk-stat is-warm"><p class="tk-k"><?= v2_te('To do') ?></p><p class="tk-stat-v" id="tk-c-action">0</p><p class="tk-stat-p"><?= v2_te('names to fill in') ?></p></article>
      </section>

      <!-- FILTERS -->
      <section class="tk-panel" aria-label="<?= v2_te('Ticket filters') ?>">
        <form class="tk-filter-row" id="tk-filters" role="search">
          <label class="tk-field is-search"><span><?= v2_te('Search tickets') ?></span>
            <span class="tk-input"><?= v2_ic('magnifying-glass') ?><input type="search" id="tk-q" maxlength="100" placeholder="<?= v2_te('Activity, city, guest, ticket code…') ?>" autocomplete="off" enterkeyhint="search"></span>
          </label>
          <label class="tk-field"><span><?= v2_te('Status') ?></span>
            <span class="tk-select"><select id="tk-status">
              <option value="all"><?= v2_te('All') ?></option>
              <option value="upcoming" selected><?= v2_te('Upcoming') ?></option>
              <option value="valid"><?= v2_te('Valid') ?></option>
              <option value="used"><?= v2_te('Scanned') ?></option>
              <option value="expired"><?= v2_te('Expired') ?></option>
              <option value="action"><?= v2_te('Needs action') ?></option>
            </select><?= v2_ic('caret-down') ?></span>
          </label>
          <label class="tk-field"><span><?= v2_te('City') ?></span>
            <span class="tk-select"><select id="tk-city"><option value="all"><?= v2_te('All cities') ?></option></select><?= v2_ic('caret-down') ?></span>
          </label>
          <label class="tk-field"><span><?= v2_te('Sort by') ?></span>
            <span class="tk-select"><select id="tk-sort">
              <option value="soon"><?= v2_te('Soonest first') ?></option>
              <option value="newest"><?= v2_te('Newest first') ?></option>
              <option value="activity"><?= v2_te('Activity A to Z') ?></option>
            </select><?= v2_ic('caret-down') ?></span>
          </label>
          <button class="btn btn-ghost tk-reset" type="button" id="tk-reset"><?= v2_te('Reset') ?></button>
        </form>
        <div class="tk-pills" role="group" aria-label="<?= v2_te('Quick filters') ?>">
          <button class="tk-pill" type="button" data-status="upcoming" aria-pressed="true"><?= v2_te('Upcoming') ?></button>
          <button class="tk-pill is-valid" type="button" data-status="valid" aria-pressed="false"><?= v2_te('Valid') ?></button>
          <button class="tk-pill is-action" type="button" data-status="action" aria-pressed="false"><?= v2_te('Needs action') ?></button>
          <button class="tk-pill" type="button" data-status="used" aria-pressed="false"><?= v2_te('Scanned') ?></button>
        </div>
      </section>

      <!-- TICKETS -->
      <section class="tk-results" id="bilete" aria-labelledby="tk-count">
        <div class="tk-results-head">
          <div><p class="tk-k"><?= v2_te('Results') ?></p><h2 class="tk-count" id="tk-count"><?= v2_e(v2_num(0, 'ticket', 'tickets')) ?></h2></div>
          <div class="tk-bulk">
            <button class="btn btn-ghost" type="button" id="tk-all-pdf" disabled><?= v2_te('Download all PDFs') ?></button>
            <button class="btn btn-primary" type="button" id="tk-all-cal" disabled><?= v2_ic('calendar-blank') ?><?= v2_te('Add all to calendar') ?></button>
          </div>
        </div>
        <p class="tk-status" id="tk-status-line" role="status"></p>
        <div class="tk-skel" id="tk-skel" aria-hidden="true"><i></i><i></i></div>
        <ul class="tk-list" id="tk-list" hidden></ul>
        <div class="tk-empty" id="tk-empty" hidden>
          <span class="tk-empty-ic" aria-hidden="true"><?= v2_ic('ticket') ?></span>
          <b id="tk-empty-h"><?= v2_te('You have no tickets yet') ?></b>
          <p id="tk-empty-p"><?= v2_te('Find things to do and book online.') ?></p>
          <a class="btn btn-primary" id="tk-empty-cta" href="/categories"><?= v2_te('Find things to do') ?></a>
          <button class="btn btn-primary" id="tk-empty-reset" type="button" hidden><?= v2_te('Reset') ?></button>
        </div>
        <div class="tk-error" id="tk-error" hidden>
          <b><?= v2_te('We could not load your tickets.') ?></b>
          <p><?= v2_te('Check your connection and try again.') ?></p>
          <button class="btn btn-ghost" type="button" id="tk-retry"><?= v2_te('Try again') ?></button>
        </div>
      </section>

      <!-- GOOD TO KNOW -->
      <section class="tk-info" aria-label="<?= v2_te('Good to know') ?>">
        <article class="tk-panel">
          <p class="tk-k"><?= v2_te('Access') ?></p>
          <h2><?= v2_te('How do you use the QR code?') ?></h2>
          <p><?= v2_te('Show the QR code on your phone, with the screen brightness turned up. Every ticket has its own code. If it does not scan the first time, try again: the operator has a hand scanner as a backup.') ?></p>
        </article>
        <article class="tk-panel is-mint">
          <p class="tk-k"><?= v2_te('Guests') ?></p>
          <h2><?= v2_te('Different names?') ?></h2>
          <p><?= v2_te('For groups, each ticket can carry a different guest name if the activity asks for it. Edit the names before the activity to avoid trouble at the entrance.') ?></p>
        </article>
        <article class="tk-panel is-warm">
          <p class="tk-k"><?= v2_te('Refunds') ?></p>
          <h2><?= v2_te('Can you no longer make it?') ?></h2>
          <p><?= v2_te('Check whether the ticket can be refunded. If you have active ticket protection (bought at checkout), you can get your money back without giving a reason.') ?></p>
        </article>
      </section>
    </div>

    <dialog class="tk-dialog" id="tk-dialog" aria-labelledby="tk-d-title">
      <div class="tk-d-head">
        <div>
          <p class="tk-k"><?= v2_te('Ticket QR') ?></p>
          <h2 id="tk-d-title"><?= v2_te('Ticket') ?></h2>
          <p class="tk-d-sub" id="tk-d-name"><?= v2_te('No guest name yet') ?></p>
        </div>
        <button class="tk-d-close" type="button" id="tk-d-close" aria-label="<?= v2_te('Close') ?>"><?= v2_ic('x') ?></button>
      </div>
      <div class="tk-d-stage"><div class="tk-d-qr" id="tk-d-qr"></div><i class="tk-scan" aria-hidden="true"></i></div>
      <p class="tk-d-code" id="tk-d-code"></p>
      <p class="tk-d-tip"><?= v2_te('Turn up the screen brightness before scanning.') ?></p>
      <p class="tk-d-msg" id="tk-d-msg" role="status"></p>
      <div class="tk-d-cta">
        <button class="btn btn-primary" type="button" id="tk-d-pdf"><?= v2_te('Download PDF') ?></button>
        <button class="btn btn-ghost" type="button" id="tk-d-cal"><?= v2_ic('calendar-blank') ?><?= v2_te('Add to calendar') ?></button>
      </div>
    </dialog>

    <?php v2_account_end(); ?>
  </div>
</main>
<?php include __DIR__ . '/../includes/v2/footer.php'; ?>
