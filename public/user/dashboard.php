<?php
/**
 * Customer dashboard: /cont (v2 design).
 *
 * Inside the v2 account shell (includes/v2/account.php). Sections: hero with the next ticket (or the points balance),
 * four stats, upcoming tickets, the three-step personalisation checklist, the referral link, recommendations, recent
 * orders, and three utility cards (support, reviews, gift card). Visitors without a customer session see the login
 * prompt straight away.
 *
 * dashboard.js loads everything with one request (GET /customer/dashboard-bundle) and falls back to the separate
 * endpoints if the bundle fails. Upcoming tickets come from core as `upcoming_events[]` with the details under `event`;
 * the old page looked for `events` / flat fields, so that list and the next-ticket card were always empty.
 * "Deschide bilet" opens /cont/bilete and "Calendar" downloads an .ics file: the old links went to /cont/comenzi/{id},
 * which has no route. The gift card card links to /voucher (/cont/carduri-cadou has no page).
 */

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/api.php';
require_once __DIR__ . '/../includes/nav-helpers.php';
require_once __DIR__ . '/../includes/v2/helpers.php';
require_once __DIR__ . '/../includes/v2/nav.php';
require_once __DIR__ . '/../includes/v2/account.php';

$pageTitleRaw = v2_t('Your account: {site}', ['site' => SITE_NAME]);
$pageDescription = v2_t('Your Viaqui account: tickets, orders, bonus points, gift cards, personal recommendations, reviews, support and account settings.');
$canonicalUrl = SITE_URL . '/account';
$noindex = true;
$skipPageCache = true;

$v2Styles = ['account.css', 'dashboard.css'];
$v2Scripts = ['account.js', 'dashboard.js'];
$v2LegacyScripts = ['assets/js/config.js', 'assets/js/utils.js', 'assets/js/api.js', 'assets/js/auth.js'];
$v2HeadExtra = v2_account_client_config();
$v2FooterCompact = true;

include __DIR__ . '/../includes/v2/head.php';
include __DIR__ . '/../includes/v2/header.php';
?>
<main id="main" tabindex="-1" class="page-main acc-page">
  <div class="wrap">
    <?php v2_account_start('dashboard'); ?>

    <!-- not signed in -->
    <section class="acc-guard" id="db-guard" hidden aria-labelledby="db-guard-h">
      <span class="acc-guard-ic" aria-hidden="true"><?= v2_ic('lock-simple') ?></span>
      <h1 id="db-guard-h"><?= v2_te('You need to sign in') ?></h1>
      <p><?= v2_te('Sign in to see your account.') ?></p>
      <a class="btn btn-primary" href="/login?redirect=%2Faccount"><?= v2_te('Sign in') ?><?= v2_ic('arrow-right') ?></a>
    </section>

    <div id="db-content">
      <!-- HERO -->
      <section class="db-hero" aria-labelledby="db-greet">
        <div class="db-hero-copy">
          <p class="db-kicker"><?= v2_te('Your account') ?></p>
          <h1 class="db-h" id="db-greet"><?= v2_t('Hi, <span id="db-first">friend</span>. <span id="db-greet-tail">Welcome back.</span>') ?></h1>
          <p class="db-lead"><?= v2_te('See what is coming up, what you have bought, how many points you have, which recommendations suit you and what is left to sort out before your next activity.') ?></p>
          <div class="db-cta">
            <a class="btn btn-light" href="/account/tickets"><?= v2_ic('ticket') ?><?= v2_te('View tickets') ?></a>
            <a class="btn btn-outline-light" href="/account/recommendations"><?= v2_te('Recommendations') ?></a>
          </div>
        </div>
        <div class="db-hero-card">
          <div class="db-skel is-card" id="db-hero-skel" aria-hidden="true"></div>
          <article class="db-next" id="db-next" hidden>
            <p class="db-card-k"><?= v2_te('Next ticket') ?></p>
            <h2 class="db-next-t" id="db-next-title"></h2>
            <p class="db-next-loc" id="db-next-loc"></p>
            <div class="db-next-when">
              <div><p class="db-next-wd" id="db-next-weekday"></p><p class="db-next-time" id="db-next-time"></p><p class="db-next-date" id="db-next-date"></p></div>
              <span class="db-qr" aria-hidden="true"><?= v2_ic('qr-code') ?><small><?= v2_te('QR ready') ?></small></span>
            </div>
            <div class="db-next-cta">
              <a class="btn btn-primary" id="db-next-open" href="/account/tickets"><?= v2_te('Open ticket') ?></a>
              <button class="btn btn-ghost" type="button" id="db-next-cal"><?= v2_ic('calendar-blank') ?><?= v2_te('Calendar') ?></button>
            </div>
          </article>
          <article class="db-pointscard" id="db-points-card" hidden>
            <p class="db-card-k"><?= v2_te('Bonus points') ?></p>
            <p class="db-points-big" id="db-points-big">0</p>
            <p class="db-points-lei"><?= v2_t('≈ <span id="db-points-lei">{amount}</span> off', ['amount' => v2_e(v2_money(0))]) ?></p>
            <a class="btn btn-primary" href="/account/points"><?= v2_te('Use your points') ?></a>
          </article>
        </div>
      </section>

      <!-- STATS -->
      <section class="db-stats" aria-label="<?= v2_te('At a glance') ?>">
        <article class="db-stat"><p class="db-card-k"><?= v2_te('Upcoming tickets') ?></p><p class="db-stat-v" id="db-stat-tickets">0</p><p class="db-stat-p" id="db-stat-activities"><?= v2_e(v2_num(0, 'confirmed activity', 'confirmed activities')) ?></p></article>
        <article class="db-stat is-mint"><p class="db-card-k"><?= v2_te('Bonus points') ?></p><p class="db-stat-v" id="db-stat-points">0</p><p class="db-stat-p"><?= v2_t('≈ <span id="db-stat-points-lei">{amount}</span> off', ['amount' => v2_e(v2_money(0))]) ?></p></article>
        <article class="db-stat"><p class="db-card-k"><?= v2_te('Orders') ?></p><p class="db-stat-v" id="db-stat-orders">0</p><p class="db-stat-p" id="db-stat-orders-last"><?= v2_te('no orders yet') ?></p></article>
        <article class="db-stat is-warm"><p class="db-card-k"><?= v2_te('Profile') ?></p><p class="db-stat-v"><span id="db-stat-profile">0</span>%</p><p class="db-stat-p"><?= v2_te('add your preferences') ?></p></article>
      </section>

      <!-- UPCOMING + PERSONALISATION + REFERRAL -->
      <section class="db-row is-main">
        <div class="db-panel">
          <div class="db-panel-head">
            <div><p class="db-card-k"><?= v2_te('Coming up') ?></p><h2><?= v2_te('Upcoming tickets') ?></h2></div>
            <a class="btn btn-ghost" href="/account/tickets"><?= v2_te('All tickets') ?></a>
          </div>
          <div class="db-skel-list" id="db-upcoming-skel" aria-hidden="true"><i class="db-skel"></i><i class="db-skel"></i></div>
          <div class="db-empty" id="db-upcoming-empty" hidden>
            <span class="db-empty-ic" aria-hidden="true"><?= v2_ic('ticket') ?></span>
            <b><?= v2_te('You have no upcoming tickets') ?></b>
            <p><?= v2_te('Find things to do and book online.') ?></p>
            <a class="btn btn-primary" href="/categories"><?= v2_te('Find things to do') ?></a>
          </div>
          <ul class="db-upcoming" id="db-upcoming" hidden></ul>
        </div>

        <aside class="db-side">
          <div class="db-panel">
            <p class="db-card-k"><?= v2_te('Personalisation') ?></p>
            <h2><?= v2_te('Better recommendations in 3 steps') ?></h2>
            <ul class="db-tasks" id="db-tasks"></ul>
            <a class="btn btn-primary db-mt" href="/account/settings#profil-preferinte"><?= v2_te('Complete your profile') ?></a>
          </div>
          <div class="db-panel is-mint">
            <p class="db-card-k"><?= v2_te('Referrals') ?></p>
            <h2><?= v2_te('Invite friends. Earn points.') ?></h2>
            <p class="db-p"><?= v2_te('Share your link and earn bonus points when your friends buy their first eligible activity.') ?></p>
            <label class="db-sr" for="db-ref-url"><?= v2_te('Your invitation link') ?></label>
            <input class="db-ref" id="db-ref-url" type="text" readonly value="viaqui.com/r/—">
            <div class="db-ref-cta">
              <button class="btn btn-primary" type="button" id="db-ref-copy"><?= v2_te('Copy') ?></button>
              <a class="btn btn-ghost" href="/account/points#afiliere"><?= v2_te('Details') ?></a>
            </div>
            <p class="db-ref-status" id="db-ref-status" role="status"></p>
          </div>
        </aside>
      </section>

      <!-- RECOMMENDATIONS + ORDERS -->
      <section class="db-row">
        <div class="db-panel">
          <div class="db-panel-head">
            <div><p class="db-card-k"><?= v2_te('For you') ?></p><h2><?= v2_te('Recommendations') ?></h2></div>
            <a class="db-link" href="/account/recommendations"><?= v2_te('View all') ?><?= v2_ic('arrow-right') ?></a>
          </div>
          <div class="db-skel-grid" id="db-recos-skel" aria-hidden="true"><i class="db-skel is-tall"></i><i class="db-skel is-tall"></i></div>
          <p class="db-empty is-inline" id="db-recos-empty" hidden><?= v2_t('Add your preferences in <a href="{url}">Settings</a> to get recommendations.', ['url' => '/account/settings#profil-preferinte']) ?></p>
          <ul class="db-recos" id="db-recos" hidden></ul>
        </div>

        <div class="db-panel">
          <div class="db-panel-head">
            <div><p class="db-card-k"><?= v2_te('History') ?></p><h2><?= v2_te('Recent orders') ?></h2></div>
            <a class="db-link" href="/account/orders"><?= v2_te('All orders') ?><?= v2_ic('arrow-right') ?></a>
          </div>
          <div class="db-skel-list is-thin" id="db-orders-skel" aria-hidden="true"><i class="db-skel"></i><i class="db-skel"></i></div>
          <p class="db-empty is-inline" id="db-orders-empty" hidden><?= v2_te('You have no orders yet.') ?></p>
          <div class="db-table-wrap" id="db-orders-table" hidden>
            <table class="db-table">
              <thead><tr><th scope="col"><?= v2_te('Order') ?></th><th scope="col" class="is-date"><?= v2_te('Date') ?></th><th scope="col"><?= v2_te('Total') ?></th><th scope="col"><?= v2_te('Status') ?></th></tr></thead>
              <tbody id="db-orders"></tbody>
            </table>
          </div>
        </div>
      </section>

      <!-- UTILITY -->
      <section class="db-utility" aria-label="<?= v2_te('Other things to sort out') ?>">
        <article class="db-panel">
          <p class="db-card-k"><?= v2_te('Support') ?></p>
          <h2 id="db-u-support-h"><?= v2_te('No open tickets') ?></h2>
          <p class="db-p" id="db-u-support-p"><?= v2_te('Open a ticket if you have a question.') ?></p>
          <a class="btn btn-ghost db-mt" href="/account/support"><?= v2_te('View tickets') ?></a>
        </article>
        <article class="db-panel">
          <p class="db-card-k"><?= v2_te('Reviews') ?></p>
          <h2 id="db-u-reviews-h"><?= v2_te('All written') ?></h2>
          <p class="db-p" id="db-u-reviews-p"><?= v2_te('Thank you for sharing your experiences.') ?></p>
          <a class="btn btn-ghost db-mt" href="/account/reviews"><?= v2_te('Write a review') ?></a>
        </article>
        <article class="db-panel is-gift" id="db-u-gift">
          <p class="db-card-k"><?= v2_te('Gift card') ?></p>
          <h2 id="db-u-gift-h"><?= v2_te('Check a gift card') ?></h2>
          <p class="db-p" id="db-u-gift-p"><?= v2_te('Enter the card code to see its balance.') ?></p>
          <a class="btn btn-ghost db-mt" href="/voucher"><?= v2_te('Check balance') ?></a>
        </article>
      </section>
    </div>

    <?php v2_account_end(); ?>
  </div>
</main>
<?php include __DIR__ . '/../includes/v2/footer.php'; ?>
