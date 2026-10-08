<?php
/**
 * Customer points and referrals: /cont/puncte and /cont/punctele-mele (v2 design).
 *
 * Inside the v2 account shell (includes/v2/account.php). Sections: hero with the balance and the progress to the next
 * level, four counters, how points work (with the marketplace's real conversion and limits), the customer level and its
 * perks, the referral block (#afiliere: link, stats, copy / share / new code), the points history with a type filter,
 * the expiry and rules cards, and the login prompt. rewards.js reads /customer/rewards/config, /customer/rewards,
 * /customer/referrals and /customer/rewards/history; points about to expire come from the dashboard summary, and only
 * when the programme makes points expire.
 *
 * Fixed on the way: the referral link was built as /r/<code>, which is a 404 (core's link is /?ref=<code>); "Cod nou"
 * posted to an endpoint api.js doesn't map, and the proxy pointed at a core route that doesn't exist; the "maximum per
 * order" line showed a number of points as lei; "Expiră curând" read a field /customer/rewards never sends; the
 * regulations link (/termeni-program-puncte) is a 404, so the rules card links to the points questions in the help
 * centre instead.
 */

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/api.php';
require_once __DIR__ . '/../includes/nav-helpers.php';
require_once __DIR__ . '/../includes/v2/helpers.php';
require_once __DIR__ . '/../includes/v2/nav.php';
require_once __DIR__ . '/../includes/v2/account.php';

$pageTitleRaw = v2_t('My points: {site}', ['site' => SITE_NAME]);
$pageDescription = v2_t('Your bonus points on Viaqui: available balance, expiry, history, customer level and referral link.');
$canonicalUrl = SITE_URL . '/account/points';
$noindex = true;
$skipPageCache = true;

$v2Styles = ['account.css', 'rewards.css'];
$v2Scripts = ['account.js', 'rewards.js'];
$v2LegacyScripts = ['assets/js/config.js', 'assets/js/utils.js', 'assets/js/api.js', 'assets/js/auth.js'];
$v2HeadExtra = v2_account_client_config();
$v2FooterCompact = true;

include __DIR__ . '/../includes/v2/head.php';
include __DIR__ . '/../includes/v2/header.php';
?>
<main id="main" tabindex="-1" class="page-main acc-page">
  <div class="wrap">
    <?php v2_account_start('points'); ?>

    <!-- not signed in -->
    <section class="acc-guard" id="pt-guard" hidden aria-labelledby="pt-guard-h">
      <span class="acc-guard-ic" aria-hidden="true"><?= v2_ic('lock-simple') ?></span>
      <h1 id="pt-guard-h"><?= v2_te('You need to sign in') ?></h1>
      <p><?= v2_te('Sign in to see your points.') ?></p>
      <a class="btn btn-primary" href="/login?redirect=%2Faccount%2Fpoints"><?= v2_te('Sign in') ?><?= v2_ic('arrow-right') ?></a>
    </section>

    <div class="acc-body" id="pt-content">
      <!-- HERO -->
      <section class="acc-hero pt-hero" aria-labelledby="pt-h">
        <div>
          <p class="acc-kicker"><?= v2_te('Loyalty wallet') ?></p>
          <h1 class="acc-h" id="pt-h"><?= v2_te('My points') ?></h1>
          <p class="acc-lead"><?= v2_te('See your bonus points balance, what it is worth, how you can use it, which points are about to expire and how many you earned from orders or referrals.') ?></p>
          <div class="pt-cta">
            <a class="btn btn-light" href="/account/recommendations"><?= v2_ic('coins') ?><?= v2_te('Use points') ?></a>
            <a class="btn btn-outline-light" href="#afiliere"><?= v2_te('Invite friends') ?></a>
          </div>
        </div>
        <article class="pt-wallet" aria-labelledby="pt-wallet-k">
          <p class="acc-k" id="pt-wallet-k"><?= v2_te('Available balance') ?></p>
          <p class="pt-balance" id="pt-balance">0</p>
          <p class="pt-balance-sub"><?= v2_t('bonus points · about <strong id="pt-balance-lei">{amount}</strong>', ['amount' => v2_e(v2_money(0))]) ?></p>
          <p class="pt-pending" id="pt-pending" hidden></p>
          <div class="pt-bar" id="pt-bar-hero" role="progressbar" aria-label="<?= v2_te('Progress to the next level') ?>" aria-valuemin="0" aria-valuemax="100" aria-valuenow="0"><i></i></div>
          <p class="pt-bar-note" id="pt-next-hero"><?= v2_te('Loading your level…') ?></p>
        </article>
      </section>

      <!-- COUNTERS -->
      <section class="pt-stats" aria-label="<?= v2_te('At a glance') ?>">
        <article class="pt-stat"><p class="acc-k"><?= v2_te('Points available') ?></p><p class="pt-stat-v" id="pt-s-balance">0</p><p class="pt-stat-p">≈ <span id="pt-s-balance-lei"><?= v2_e(v2_money(0)) ?></span></p></article>
        <article class="pt-stat is-mint"><p class="acc-k"><?= v2_te('Earned in total') ?></p><p class="pt-stat-v" id="pt-s-earned">0</p><p class="pt-stat-p"><?= v2_te('from orders and referrals') ?></p></article>
        <article class="pt-stat"><p class="acc-k"><?= v2_te('Used') ?></p><p class="pt-stat-v" id="pt-s-spent">0</p><p class="pt-stat-p"><?= v2_t('≈ <span id="pt-s-spent-lei">{amount}</span> in discounts', ['amount' => v2_e(v2_money(0))]) ?></p></article>
        <article class="pt-stat is-warm"><p class="acc-k"><?= v2_te('Expiring soon') ?></p><p class="pt-stat-v" id="pt-s-expiring">0</p><p class="pt-stat-p" id="pt-s-expiring-p"><?= v2_te('nothing expiring soon') ?></p></article>
      </section>

      <!-- HOW IT WORKS + LEVEL -->
      <section class="pt-row">
        <div class="acc-panel">
          <div class="pt-panel-head">
            <div><p class="acc-k"><?= v2_te('How it works') ?></p><h2><?= v2_te('You use your points right at checkout.') ?></h2></div>
            <a class="btn btn-primary" href="/categories"><?= v2_te('Find activities') ?></a>
          </div>
          <ol class="pt-steps">
            <li><span aria-hidden="true">1</span><h3><?= v2_te('Buy') ?></h3><p><?= v2_te('Every paid order earns you points. They stay “pending” until the activity has taken place, then they are added to your account.') ?></p></li>
            <li><span aria-hidden="true">2</span><h3><?= v2_te('Collect') ?></h3><p><?= v2_te('You also get points on your birthday and when a friend you invited buys their first activity.') ?></p></li>
            <li><span aria-hidden="true">3</span><h3><?= v2_te('Save') ?></h3><p><?= v2_te('At checkout, signed in, tick “Use your points” and pay part of your tickets with them.') ?></p></li>
          </ol>
          <div class="pt-rate">
            <b><?= v2_te('Current rate') ?></b>
            <ul id="pt-rate"><li><?= v2_te('Loading the programme rules…') ?></li></ul>
          </div>
        </div>

        <div class="acc-panel" id="pt-level">
          <p class="acc-k"><?= v2_te('Customer level') ?></p>
          <h2 class="pt-tier" id="pt-tier">—</h2>
          <p class="pt-p" id="pt-tier-desc" hidden></p>
          <div class="pt-ladder">
            <div class="pt-ladder-names"><span id="pt-tier-from">—</span><span id="pt-tier-to">—</span></div>
            <div class="pt-bar is-green" id="pt-bar-tier" role="progressbar" aria-label="<?= v2_te('Progress in the current level') ?>" aria-valuemin="0" aria-valuemax="100" aria-valuenow="0"><i></i></div>
            <p class="pt-bar-note" id="pt-next-tier">—</p>
          </div>
          <ul class="pt-perks" id="pt-perks"></ul>
        </div>
      </section>

      <!-- REFERRALS -->
      <section class="pt-aff" id="afiliere" aria-labelledby="pt-aff-h">
        <div>
          <p class="acc-kicker"><?= v2_te('Referrals') ?></p>
          <h2 class="pt-aff-h" id="pt-aff-h"><?= v2_te('Invite friends. Earn points when they buy.') ?></h2>
          <p class="acc-lead"><?= v2_te('Share your referral link. When a friend creates an account and buys their first eligible activity, you get bonus points. Your friend may get a benefit too, if a campaign is running.') ?></p>
          <p class="pt-aff-reward" id="pt-aff-reward" hidden></p>
          <dl class="pt-aff-stats">
            <div><dt><?= v2_te('clicks') ?></dt><dd id="pt-r-clicks">0</dd></div>
            <div><dt><?= v2_te('accounts created') ?></dt><dd id="pt-r-accounts">0</dd></div>
            <div><dt><?= v2_te('eligible orders') ?></dt><dd id="pt-r-orders">0</dd></div>
          </dl>
        </div>
        <article class="pt-link" aria-labelledby="pt-link-k">
          <p class="acc-k" id="pt-link-k"><?= v2_te('Your link') ?></p>
          <p class="pt-link-big" id="pt-link-display">—</p>
          <label class="pt-sr" for="pt-link"><?= v2_te('Your referral link') ?></label>
          <input class="pt-link-input" id="pt-link" type="text" readonly value="">
          <div class="pt-link-cta">
            <button class="btn btn-primary" type="button" id="pt-copy" disabled><?= v2_te('Copy link') ?></button>
            <a class="btn btn-ghost" id="pt-wa" href="https://wa.me/" target="_blank" rel="noopener" hidden><?= v2_te('Share on WhatsApp') ?></a>
            <button class="btn btn-ghost" type="button" id="pt-share" hidden><?= v2_te('Share') ?></button>
            <button class="btn btn-ghost" type="button" id="pt-regen" disabled aria-controls="pt-confirm" aria-expanded="false"><?= v2_te('New code') ?></button>
          </div>
          <div class="pt-confirm" id="pt-confirm" hidden>
            <p><?= v2_te('Are you sure you want a new code? The current link will stop working.') ?></p>
            <div>
              <button class="btn btn-primary" type="button" id="pt-regen-yes"><?= v2_te('Yes, make a new code') ?></button>
              <button class="btn btn-ghost" type="button" id="pt-regen-no"><?= v2_te('Cancel') ?></button>
            </div>
          </div>
          <p class="pt-code"><?= v2_t('Referral code: <strong id="pt-code">—</strong>') ?></p>
          <p class="pt-link-status" id="pt-link-status" role="status"></p>
        </article>
      </section>

      <!-- HISTORY + SIDE -->
      <section class="pt-row is-history">
        <div class="acc-panel">
          <div class="pt-panel-head">
            <div><p class="acc-k"><?= v2_te('History') ?></p><h2><?= v2_te('Points transactions') ?></h2></div>
            <label class="acc-field pt-type"><span class="pt-sr"><?= v2_te('Transaction type') ?></span>
              <span class="acc-select"><select id="pt-type">
                <option value="all"><?= v2_te('All') ?></option>
                <option value="earned"><?= v2_te('Earned') ?></option>
                <option value="spent"><?= v2_te('Used') ?></option>
                <option value="expired"><?= v2_te('Expired') ?></option>
                <option value="affiliate"><?= v2_te('Referrals') ?></option>
              </select><?= v2_ic('caret-down') ?></span>
            </label>
          </div>
          <div class="acc-skel pt-rows-skel" id="pt-h-skel" aria-hidden="true"><i></i><i></i><i></i></div>
          <p class="pt-empty" id="pt-h-empty" hidden><?= v2_te('No transactions match this filter.') ?></p>
          <p class="pt-empty is-error" id="pt-h-error" hidden><?= v2_te('We could not load the history.') ?> <button type="button" id="pt-h-retry"><?= v2_te('Try again') ?></button></p>
          <div class="pt-table-wrap" id="pt-h-table" hidden>
            <table class="pt-table">
              <thead><tr><th scope="col"><?= v2_te('Date') ?></th><th scope="col"><?= v2_te('Description') ?></th><th scope="col" class="is-type"><?= v2_te('Type') ?></th><th scope="col" class="is-pts"><?= v2_te('Points') ?></th></tr></thead>
              <tbody id="pt-h-rows"></tbody>
            </table>
          </div>
          <button class="btn btn-ghost pt-more" type="button" id="pt-h-more" hidden><?= v2_te('Load more') ?></button>
        </div>

        <aside class="pt-side">
          <div class="acc-panel pt-exp" id="pt-exp">
            <p class="acc-k"><?= v2_te('Expiry') ?></p>
            <h2 id="pt-exp-h"><?= v2_te('No points about to expire') ?></h2>
            <p class="pt-p" id="pt-exp-p"><?= v2_te('Keep buying to earn new points.') ?></p>
            <a class="btn btn-primary" href="/account/recommendations"><?= v2_te('View recommendations') ?></a>
          </div>
          <div class="acc-panel">
            <p class="acc-k"><?= v2_te('Rules') ?></p>
            <h2><?= v2_te('In short') ?></h2>
            <ul class="pt-rules">
              <li id="pt-rule-earn"><?= v2_t('<b>Earning:</b> on eligible orders, once they are confirmed.') ?></li>
              <li><?= v2_t('<b>Using:</b> right at checkout, within the rules.') ?></li>
              <li id="pt-rule-bday" hidden></li>
              <li id="pt-rule-ref"><?= v2_t('<b>Referrals:</b> points after your friend’s first eligible order.') ?></li>
              <li id="pt-rule-exp"><?= v2_t('<b>Expiry:</b> points may have a time limit.') ?></li>
            </ul>
            <a class="pt-help" href="/help?categorie=bonus"><?= v2_te('Questions about points') ?><?= v2_ic('arrow-right') ?></a>
          </div>
        </aside>
      </section>
    </div>

    <?php v2_account_end(); ?>
  </div>
</main>
<?php include __DIR__ . '/../includes/v2/footer.php'; ?>
