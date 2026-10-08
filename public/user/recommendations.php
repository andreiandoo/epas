<?php
/**
 * Customer recommendations: /cont/recomandari (v2 design).
 *
 * Inside the v2 account shell (includes/v2/account.php). Hero with the profile signals behind the list, four counters,
 * filters (search, reason, city, budget, quick pills; kept in the URL), the recommendation cards (match, points,
 * family, reasons, "Nu mă interesează" with undo) and the side column: what the engine used, bonus points, discovery.
 * recommendations.js reads GET /customer/recommendations (scored on the server), /customer/rewards/config and, when the
 * programme makes points expire, the dashboard summary for the points expiring soon.
 *
 * Fixed on the way: the "Expiră" counter read expiring points from /customer/rewards, which never sends them, so it was
 * always 0. The "Control personalizare" checkboxes re-scored the cards in the browser with rules of their own (the
 * server's match scores and reasons were replaced) and saved nothing: the section shows the signals the engine really
 * used and links to the settings where they change. The interest signal showed a category slug ("escape-rooms"); it
 * shows the category name. "Vezi puncte" pointed at the old /cont/punctele-mele alias.
 */

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/api.php';
require_once __DIR__ . '/../includes/nav-helpers.php';
require_once __DIR__ . '/../includes/v2/helpers.php';
require_once __DIR__ . '/../includes/v2/nav.php';
require_once __DIR__ . '/../includes/v2/account.php';

$pageTitleRaw = v2_t('Recommended for you: {site}', ['site' => SITE_NAME]);
$pageDescription = v2_t('Personal recommendations on Viaqui: activities picked from your favourite cities, orders, reviews, points and family profile.');
$canonicalUrl = SITE_URL . '/cont/recomandari';
$noindex = true;
$skipPageCache = true;

$v2Styles = ['account.css', 'recommendations.css'];
$v2Scripts = ['account.js', 'recommendations.js'];
$v2LegacyScripts = ['assets/js/config.js', 'assets/js/utils.js', 'assets/js/api.js', 'assets/js/auth.js'];
$v2HeadExtra = v2_account_client_config();
$v2FooterCompact = true;
$rcCaret = v2_ic('caret-down');
$rcPills = [['profile', v2_t('For your profile')], ['family', v2_t('With kids')], ['points', v2_t('Use points')], ['weather', v2_t('Weekend / weather')]];

include __DIR__ . '/../includes/v2/head.php';
include __DIR__ . '/../includes/v2/header.php';
?>
<main id="main" tabindex="-1" class="page-main acc-page">
  <div class="wrap">
    <?php v2_account_start('recommendations'); ?>

    <!-- not signed in -->
    <section class="acc-guard" id="rc-guard" hidden aria-labelledby="rc-guard-h">
      <span class="acc-guard-ic" aria-hidden="true"><?= v2_ic('lock-simple') ?></span>
      <h1 id="rc-guard-h"><?= v2_te('You need to sign in') ?></h1>
      <p><?= v2_te('Sign in to see your recommendations.') ?></p>
      <a class="btn btn-primary" href="/login?redirect=%2Fcont%2Frecomandari"><?= v2_te('Sign in') ?><?= v2_ic('arrow-right') ?></a>
    </section>

    <div class="acc-body" id="rc-content">
      <!-- HERO -->
      <section class="acc-hero rc-hero" aria-labelledby="rc-h">
        <div>
          <p class="acc-kicker"><?= v2_te('Picked for you') ?></p>
          <h1 class="acc-h" id="rc-h"><?= v2_te('Recommended for you') ?></h1>
          <p class="acc-lead"><?= v2_te('Activities recommended from your favourite cities, orders, reviews, available points, family profile and interests.') ?></p>
          <div class="rc-cta">
            <a class="btn btn-light" href="#recomandari"><?= v2_ic('star') ?><?= v2_te('View recommendations') ?></a>
            <a class="btn btn-outline-light" href="/account/settings#profil-preferinte"><?= v2_te('Refine your profile') ?></a>
          </div>
        </div>
        <article class="rc-signals" aria-labelledby="rc-signals-h">
          <p class="acc-k"><?= v2_te('Profile signals') ?></p>
          <h2 id="rc-signals-h"><?= v2_te('Why do you see these recommendations?') ?></h2>
          <dl class="rc-sig">
            <div><dt><?= v2_te('Favourite city') ?></dt><dd id="rc-sig-city">…</dd></div>
            <div><dt><?= v2_te('Interest') ?></dt><dd id="rc-sig-interest">…</dd></div>
            <div><dt><?= v2_te('Family') ?></dt><dd id="rc-sig-family">…</dd></div>
            <div><dt><?= v2_te('Points') ?></dt><dd id="rc-sig-points">…</dd></div>
          </dl>
        </article>
      </section>

      <!-- COUNTERS -->
      <section class="rc-stats" aria-label="<?= v2_te('At a glance') ?>">
        <article class="rc-stat"><p class="acc-k"><?= v2_te('Good match') ?></p><p class="rc-stat-v" id="rc-s-good">—</p><p class="rc-stat-p"><?= v2_te('activities that suit you') ?></p></article>
        <article class="rc-stat is-mint"><p class="acc-k"><?= v2_te('With points') ?></p><p class="rc-stat-v" id="rc-s-points">—</p><p class="rc-stat-p"><?= v2_te('you can apply a discount') ?></p></article>
        <article class="rc-stat"><p class="acc-k"><?= v2_te('Family') ?></p><p class="rc-stat-v" id="rc-s-family">—</p><p class="rc-stat-p"><?= v2_te('suitable for children') ?></p></article>
        <article class="rc-stat is-rose"><p class="acc-k"><?= v2_te('Expiring') ?></p><p class="rc-stat-v" id="rc-s-exp">—</p><p class="rc-stat-p" id="rc-s-exp-p"><?= v2_te('checking…') ?></p></article>
      </section>
      <div class="rc-flash" id="rc-flash" role="status" aria-live="polite" hidden><span id="rc-flash-t"></span><button class="rc-flash-undo" type="button" id="rc-flash-undo" hidden><?= v2_te('Undo') ?></button></div>

      <!-- FILTERS -->
      <section class="acc-panel rc-filters" aria-label="<?= v2_te('Filter the recommendations') ?>">
        <div class="rc-filter-row">
          <div class="acc-field is-search">
            <label for="rc-q"><?= v2_te('Search') ?></label>
            <span class="acc-input"><?= v2_ic('magnifying-glass') ?><input id="rc-q" type="search" maxlength="80" placeholder="<?= v2_te('Escape room, kids, museum, Vienna…') ?>" autocomplete="off"></span>
          </div>
          <div class="acc-field">
            <label for="rc-reason"><?= v2_te('Reason') ?></label>
            <span class="acc-select"><select id="rc-reason"><option value="all"><?= v2_te('All') ?></option><option value="profile"><?= v2_te('Profile') ?></option><option value="family"><?= v2_te('Family') ?></option><option value="points"><?= v2_te('Points') ?></option><option value="history"><?= v2_te('History') ?></option><option value="weather"><?= v2_te('Weather / season') ?></option></select><?= $rcCaret ?></span>
          </div>
          <div class="acc-field">
            <label for="rc-city"><?= v2_te('City') ?></label>
            <span class="acc-select"><select id="rc-city"><option value="all"><?= v2_te('All') ?></option></select><?= $rcCaret ?></span>
          </div>
          <div class="acc-field">
            <label for="rc-budget"><?= v2_te('Budget') ?></label>
            <span class="acc-select"><select id="rc-budget"><option value="all"><?= v2_te('Any') ?></option><option value="low"><?= v2_te('under {amount}', ['amount' => v2_money(50)]) ?></option><option value="mid"><?= v2_te('{from} to {to}', ['from' => v2_money(50), 'to' => v2_money(120)]) ?></option><option value="high"><?= v2_te('{amount} and over', ['amount' => v2_money(120)]) ?></option></select><?= $rcCaret ?></span>
          </div>
          <button class="btn btn-ghost rc-reset" type="button" id="rc-reset"><?= v2_te('Reset') ?></button>
        </div>
        <div class="rc-pills" role="group" aria-label="<?= v2_te('Quick filters') ?>">
          <?php foreach ($rcPills as [$rcKey, $rcLabel]): ?>
          <button class="rc-pill is-<?= $rcKey ?>" type="button" data-reason="<?= $rcKey ?>" aria-pressed="false"><?= v2_e($rcLabel) ?></button>
          <?php endforeach; ?>
        </div>
      </section>

      <!-- RESULTS + SIDE -->
      <section class="rc-layout" id="recomandari" aria-labelledby="rc-results-h">
        <div class="rc-results">
          <div class="rc-results-head">
            <div><p class="acc-k"><?= v2_te('Results') ?></p><h2 id="rc-results-h" tabindex="-1"><?= v2_te('Recommendations') ?></h2></div>
            <div class="rc-results-actions">
              <button class="btn btn-ghost" type="button" id="rc-unhide" hidden><?= v2_te('Show hidden') ?></button>
              <a class="btn btn-primary" href="/account/settings#profil-preferinte"><?= v2_te('Refine your profile') ?></a>
            </div>
          </div>

          <div class="rc-skels" id="rc-skel" aria-hidden="true"><div class="rc-skel"></div><div class="rc-skel"></div></div>
          <div class="rc-empty is-error" id="rc-error" hidden>
            <h3><?= v2_te('We could not load your recommendations') ?></h3>
            <p><?= v2_te('Check your connection and try again.') ?></p>
            <button class="btn btn-ghost" type="button" id="rc-retry"><?= v2_te('Try again') ?></button>
          </div>
          <ul class="rc-grid" id="rc-grid" hidden></ul>
          <div class="rc-empty" id="rc-empty" hidden>
            <h3 id="rc-empty-h"><?= v2_te('No recommendations found.') ?></h3>
            <p id="rc-empty-p"><?= v2_te('Change the filters or complete your profile for better suggestions.') ?></p>
            <div class="rc-empty-actions">
              <button class="btn btn-ghost" type="button" id="rc-empty-reset"><?= v2_te('Reset filters') ?></button>
              <a class="btn btn-primary" href="/account/settings#profil-preferinte"><?= v2_te('Add preferences') ?></a>
            </div>
          </div>
        </div>

        <aside class="rc-aside" aria-label="<?= v2_te('About the recommendations') ?>">
          <div class="rc-box">
            <p class="acc-k"><?= v2_te('Personalisation controls') ?></p>
            <h2><?= v2_te('What shapes your recommendations') ?></h2>
            <ul class="rc-controls">
              <li id="rc-c-history"><span class="rc-dot" aria-hidden="true"></span><div><b><?= v2_te('Order history') ?></b><small id="rc-c-history-t"><?= v2_te('activities you bought before') ?></small></div></li>
              <li id="rc-c-reviews" class="is-neutral"><span class="rc-dot" aria-hidden="true"></span><div><b><?= v2_te('Reviews') ?></b><small><?= v2_t('ratings and feedback · <a href="{url}">your reviews</a>', ['url' => '/account/reviews']) ?></small></div></li>
              <li id="rc-c-family"><span class="rc-dot" aria-hidden="true"></span><div><b><?= v2_te('Family profile') ?></b><small id="rc-c-family-t"><?= v2_te('ages of children / types of activities') ?></small></div></li>
              <li id="rc-c-cities"><span class="rc-dot" aria-hidden="true"></span><div><b><?= v2_te('Favourite cities') ?></b><small id="rc-c-cities-t"><?= v2_te('nothing chosen yet') ?></small></div></li>
            </ul>
            <p class="rc-note"><?= v2_te('Recommendations are worked out from these signals. You change them in your account settings.') ?></p>
            <a class="btn btn-primary" href="/account/settings#profil-preferinte"><?= v2_te('Edit the signals') ?></a>
          </div>
          <div class="rc-box is-mint">
            <p class="acc-k"><?= v2_te('Bonus points') ?></p>
            <h2><?= v2_t('You have <span id="rc-p-points">0</span> points') ?></h2>
            <p><?= v2_t('You can take about <strong id="rc-p-lei">{amount}</strong> off your next order, depending on the checkout rules.', ['amount' => v2_e(v2_money(0))]) ?></p>
            <a class="btn btn-primary" href="/account/points"><?= v2_te('View points') ?></a>
          </div>
          <div class="rc-box is-deep">
            <p class="acc-k"><?= v2_te('Discover') ?></p>
            <h2><?= v2_te('Want something else?') ?></h2>
            <p><?= v2_te('Browse all the categories or search for activities in your city yourself.') ?></p>
            <a class="btn btn-light" href="/categories"><?= v2_te('View categories') ?><?= v2_ic('arrow-right') ?></a>
          </div>
        </aside>
      </section>
    </div>

    <?php v2_account_end(); ?>
  </div>
</main>
<?php include __DIR__ . '/../includes/v2/footer.php'; ?>
