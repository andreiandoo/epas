<?php
/**
 * Customer reviews: /cont/recenzii (v2 design).
 *
 * Inside the v2 account shell (includes/v2/account.php). Hero with the average rating given, four counters, "De
 * evaluat" (one activity at a time: stars, text, who it suits, detailed ratings, photos, draft / publish, writing
 * guide), "Istoric" (search, status and rating filters, review cards with edit / delete, drafts), three info cards.
 * reviews.js talks to /customer/reviews (list, paginated), /customer/reviews/events-to-review, /customer/reviews/meta,
 * POST /customer/reviews (JSON, or multipart when photos are attached), PUT + DELETE /customer/reviews/{id}.
 *
 * Fixed on the way: "Salvează draft" POSTed the review, and core has no drafts (every review goes to moderation), so a
 * draft was really submitted: drafts are kept on this device now. Photos went as data URLs inside JSON, which core
 * rejects (it takes uploaded files only), so any review with photos failed: they are uploaded as files now (the proxy
 * forwards multipart). Core needs at least 20 characters; the old page let a 10-character review through to a 422.
 * Rejected reviews were shown as published. Editing loaded a review into the form of another activity and re-sent the
 * rating and text every time, which sends an approved review back to moderation: it opens in its own dialog and only
 * changed fields are sent. confirm() / alert() became inline confirmations and messages.
 */

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/api.php';
require_once __DIR__ . '/../includes/nav-helpers.php';
require_once __DIR__ . '/../includes/v2/helpers.php';
require_once __DIR__ . '/../includes/v2/nav.php';
require_once __DIR__ . '/../includes/v2/account.php';

$pageTitleRaw = v2_t('My reviews: {site}', ['site' => SITE_NAME]);
$pageDescription = v2_t('Your reviews on Viaqui: write about the activities you went to, edit drafts and help other customers choose.');
$canonicalUrl = SITE_URL . '/account/reviews';
$noindex = true;
$skipPageCache = true;

$v2Styles = ['account.css', 'reviews.css'];
$v2Scripts = ['account.js', 'reviews.js'];
$v2LegacyScripts = ['assets/js/config.js', 'assets/js/utils.js', 'assets/js/api.js', 'assets/js/auth.js'];
$v2HeadExtra = v2_account_client_config();
$v2FooterCompact = true;

$rvStar = '<svg class="rv-star-ic" viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path d="M12 2.6l2.84 5.93 6.53.86-4.78 4.53 1.2 6.47L12 17.25l-5.79 3.15 1.2-6.47L2.63 9.39l6.53-.86z"/></svg>';
$rvStars = function (string $name, bool $small = false, bool $optional = false) use ($rvStar): string {
    $html = '<div class="rv-stars' . ($small ? ' is-small' : '') . '" data-stars="' . $name . '"' . ($optional ? ' data-optional' : '') . '>';
    for ($n = 1; $n <= 5; $n++) {
        $html .= '<label class="rv-star"><input type="radio" name="' . $name . '" value="' . $n . '"><span class="sr">'
            . v2_e(v2_num($n, 'star', 'stars')) . '</span>' . $rvStar . '</label>';
    }
    return $html . '</div>';
};
$rvSuitable = [['children', v2_t('Children')], ['family', v2_t('Family')], ['couple', v2_t('Couple')], ['groups', v2_t('Groups')], ['team_building', v2_t('Team building')], ['solo', v2_t('Solo')]];
$rvAges = [['3-6', v2_t('Ages 3 to 6')], ['6-10', v2_t('Ages 6 to 10')], ['10-14', v2_t('Ages 10 to 14')], ['14-18', v2_t('Ages 14 to 18')], ['adults', v2_t('Adults')], ['all', v2_t('All ages')]];
$rvAspects = ['show' => v2_t('The experience'), 'venue' => v2_t('The venue'), 'organization' => v2_t('The organisation'), 'value' => v2_t('Value for money')];
$rvCaret = v2_ic('caret-down');

include __DIR__ . '/../includes/v2/head.php';
include __DIR__ . '/../includes/v2/header.php';
?>
<main id="main" tabindex="-1" class="page-main acc-page">
  <div class="wrap">
    <?php v2_account_start('reviews'); ?>

    <!-- not signed in -->
    <section class="acc-guard" id="rv-guard" hidden aria-labelledby="rv-guard-h">
      <span class="acc-guard-ic" aria-hidden="true"><?= v2_ic('lock-simple') ?></span>
      <h1 id="rv-guard-h"><?= v2_te('You need to sign in') ?></h1>
      <p><?= v2_te('Sign in to see and write your reviews.') ?></p>
      <a class="btn btn-primary" href="/login?redirect=%2Faccount%2Freviews"><?= v2_te('Sign in') ?><?= v2_ic('arrow-right') ?></a>
    </section>

    <div class="acc-body" id="rv-content">
      <!-- HERO -->
      <section class="acc-hero rv-hero" aria-labelledby="rv-h">
        <div>
          <p class="acc-kicker"><?= v2_te('Your reviews') ?></p>
          <h1 class="acc-h" id="rv-h"><?= v2_te('My reviews') ?></h1>
          <p class="acc-lead"><?= v2_te('Write reviews for the activities you went to, see what you have published, edit drafts and help us recommend experiences that suit you better.') ?></p>
          <div class="rv-cta">
            <a class="btn btn-light" href="#de-evaluat"><?= v2_ic('star') ?><?= v2_te('Write a review') ?></a>
            <a class="btn btn-outline-light" href="/cont/recomandari"><?= v2_te('View recommendations') ?></a>
          </div>
        </div>
        <article class="rv-score" aria-labelledby="rv-score-k">
          <p class="acc-k" id="rv-score-k"><?= v2_te('Review score') ?></p>
          <p class="rv-score-v" id="rv-avg">0.0</p>
          <p class="rv-score-l"><?= v2_t('average rating you gave · <span id="rv-pub-label">{reviews}</span>', ['reviews' => v2_e(v2_num(0, 'published review', 'published reviews'))]) ?></p>
          <div class="rv-score-stars" id="rv-avg-stars" role="img" aria-label="<?= v2_te('Average rating {rating} out of 5', ['rating' => '0.0']) ?>"><?= str_repeat($rvStar, 5) ?></div>
          <p class="rv-score-hint" id="rv-score-hint"><?= v2_te('Checking for activities to review…') ?></p>
        </article>
      </section>

      <!-- COUNTERS -->
      <section class="rv-stats" aria-label="<?= v2_te('At a glance') ?>">
        <article class="rv-stat"><p class="acc-k"><?= v2_te('Published') ?></p><p class="rv-stat-v" id="rv-s-pub">—</p><p class="rv-stat-p"><?= v2_te('visible reviews') ?></p></article>
        <article class="rv-stat is-mint"><p class="acc-k"><?= v2_te('To review') ?></p><p class="rv-stat-v" id="rv-s-todo">—</p><p class="rv-stat-p" id="rv-s-todo-p"><?= v2_te('checking…') ?></p></article>
        <article class="rv-stat"><p class="acc-k"><?= v2_te('Drafts') ?></p><p class="rv-stat-v" id="rv-s-draft">—</p><p class="rv-stat-p"><?= v2_te('unfinished, on this device') ?></p></article>
        <article class="rv-stat is-rose"><p class="acc-k"><?= v2_te('Moderation') ?></p><p class="rv-stat-v" id="rv-s-mod">—</p><p class="rv-stat-p"><?= v2_te('being checked') ?></p></article>
      </section>
      <p class="rv-flash" id="rv-flash" role="status" aria-live="polite"></p>

      <!-- TO REVIEW -->
      <section class="acc-panel rv-panel" id="de-evaluat" aria-labelledby="rv-todo-h">
        <div class="rv-head">
          <div><p class="acc-k"><?= v2_te('To review') ?></p><h2 id="rv-todo-h"><?= v2_te('Activities waiting for your review') ?></h2></div>
          <div class="rv-pager" id="rv-pager" hidden>
            <button class="rv-pager-btn" type="button" id="rv-prev" aria-label="<?= v2_te('Previous activity') ?>"><?= v2_ic('arrow-left') ?></button>
            <span class="rv-pager-t" id="rv-pos"><?= v2_te('{n} of {total}', ['n' => 1, 'total' => 1]) ?></span>
            <button class="rv-pager-btn" type="button" id="rv-next" aria-label="<?= v2_te('Next activity') ?>"><?= v2_ic('arrow-right') ?></button>
          </div>
        </div>

        <div class="rv-skel is-tall" id="rv-todo-skel" aria-hidden="true"></div>
        <div class="rv-empty is-error" id="rv-todo-error" hidden>
          <h3><?= v2_te('We could not load the activities to review') ?></h3>
          <p><?= v2_te('Check your connection and try again.') ?></p>
          <button class="btn btn-ghost" type="button" id="rv-todo-retry"><?= v2_te('Try again') ?></button>
        </div>
        <div class="rv-empty" id="rv-todo-empty" hidden>
          <span class="rv-empty-ic" aria-hidden="true"><?= v2_ic('check-circle') ?></span>
          <h3 id="rv-todo-empty-h" tabindex="-1"><?= v2_te('Nothing to review right now') ?></h3>
          <p><?= v2_te('After you go to an activity, it will show up here for a review.') ?></p>
        </div>

        <div class="rv-todo" id="rv-todo" hidden>
          <article class="rv-write" aria-labelledby="rv-ev-title">
            <div class="rv-write-media is-empty" id="rv-ev-media"><?= v2_ic('star') ?></div>
            <div class="rv-write-body">
              <div class="rv-tags"><span class="acc-tag is-ok"><?= v2_te('attendance confirmed') ?></span><span class="acc-tag" id="rv-ev-date"></span></div>
              <h3 class="rv-ev-title" id="rv-ev-title" tabindex="-1"></h3>
              <p class="rv-ev-where" id="rv-ev-where"></p>

              <form class="rv-form" id="rv-form" novalidate>
                <fieldset class="rv-rate">
                  <legend><?= v2_te('Quick rating') ?></legend>
                  <?= $rvStars('rv-rating') ?>
                  <span class="rv-rate-t" id="rv-rating-t" aria-live="polite"><?= v2_te('Choose from 1 to 5 stars') ?></span>
                </fieldset>

                <div class="acc-field">
                  <label for="rv-text"><?= v2_te('What did you like, or what should others know?') ?></label>
                  <textarea class="rv-textarea" id="rv-text" rows="5" maxlength="2000" aria-describedby="rv-text-count" placeholder="<?= v2_te('Be honest, useful and specific. For example: how long it took, what age it suits, how the access was, whether you would go again.') ?>"></textarea>
                  <small class="rv-count" id="rv-text-count"><?= v2_te('0 / 2,000 · write 20 more characters') ?></small>
                </div>

                <div class="rv-grid">
                  <div class="acc-field">
                    <label for="rv-suitable"><?= v2_te('Suitable for') ?></label>
                    <span class="acc-select"><select id="rv-suitable"><?php foreach ($rvSuitable as [$rvValue, $rvLabel]): ?><option value="<?= v2_e($rvValue) ?>"<?= $rvValue === 'family' ? ' selected' : '' ?>><?= v2_e($rvLabel) ?></option><?php endforeach; ?></select><?= $rvCaret ?></span>
                  </div>
                  <div class="acc-field">
                    <label for="rv-age"><?= v2_te('Recommended age') ?></label>
                    <span class="acc-select"><select id="rv-age"><?php foreach ($rvAges as [$rvValue, $rvLabel]): ?><option value="<?= v2_e($rvValue) ?>"<?= $rvValue === 'all' ? ' selected' : '' ?>><?= v2_e($rvLabel) ?></option><?php endforeach; ?></select><?= $rvCaret ?></span>
                  </div>
                </div>

                <details class="rv-more" id="rv-more">
                  <summary><span><?= v2_t('Detailed rating and options <small>(optional)</small>') ?></span></summary>
                  <div class="rv-more-body">
                    <?php foreach ($rvAspects as $rvKey => $rvLabel): ?>
                    <fieldset class="rv-aspect"><legend><?= v2_e($rvLabel) ?></legend><?= $rvStars('rv-a-' . $rvKey, true, true) ?></fieldset>
                    <?php endforeach; ?>
                    <p class="rv-note"><?= v2_te('Press the chosen star again to clear a detailed rating.') ?></p>
                    <label class="rv-switch-row" for="rv-recommend"><span><?= v2_te('I recommend this activity') ?></span><input class="rv-switch" type="checkbox" role="switch" id="rv-recommend" checked></label>
                    <label class="rv-switch-row" for="rv-anonymous"><span><?= v2_te('Publish without my name') ?></span><input class="rv-switch" type="checkbox" role="switch" id="rv-anonymous"></label>
                  </div>
                </details>

                <div class="rv-photos">
                  <ul class="rv-thumbs" id="rv-thumbs" aria-label="<?= v2_te('Attached photos') ?>" hidden></ul>
                  <button class="btn btn-ghost" type="button" id="rv-attach"><?= v2_ic('plus') ?><?= v2_te('Attach photos') ?></button>
                  <input class="rv-file" type="file" id="rv-photo-input" accept="image/jpeg,image/png,image/webp,image/gif" multiple hidden>
                  <small class="rv-note" id="rv-photo-note"><?= v2_te('Up to 5 photos, JPG, PNG, WebP or GIF, no more than 5 MB each.') ?></small>
                </div>

                <p class="rv-error" id="rv-form-error" role="alert" hidden></p>
                <div class="rv-actions">
                  <button class="btn btn-ghost" type="button" id="rv-draft"><?= v2_te('Save draft') ?></button>
                  <button class="btn btn-primary" type="submit" id="rv-submit"><?= v2_te('Publish review') ?></button>
                </div>
                <small class="rv-note" id="rv-draft-note"><?= v2_te('Reviews appear on the site after a short check.') ?></small>
              </form>
            </div>
          </article>

          <aside class="rv-guide" aria-labelledby="rv-guide-h">
            <p class="acc-k"><?= v2_te('A good review') ?></p>
            <h3 id="rv-guide-h"><?= v2_te('Write for the person who is deciding.') ?></h3>
            <p><?= v2_t('<strong>Specific:</strong> say how long it really took, how the access was, how busy it got, what age it suits.') ?></p>
            <p><?= v2_t('<strong>Useful:</strong> say whether you would go again, and with whom.') ?></p>
            <p><?= v2_t('<strong>Fair:</strong> leave out personal details and anything that is not about the activity.') ?></p>
          </aside>
        </div>
      </section>

      <!-- HISTORY -->
      <section class="acc-panel rv-panel" id="istoric" aria-labelledby="rv-list-h">
        <p class="acc-k"><?= v2_te('History') ?></p>
        <h2 id="rv-list-h" tabindex="-1"><?= v2_te('Published reviews and drafts') ?></h2>
        <div class="rv-filters">
          <div class="acc-field">
            <label for="rv-q"><?= v2_te('Search') ?></label>
            <span class="acc-input"><?= v2_ic('magnifying-glass') ?><input id="rv-q" type="search" maxlength="100" placeholder="<?= v2_te('Search reviews…') ?>" autocomplete="off"></span>
          </div>
          <div class="acc-field">
            <label for="rv-status"><?= v2_te('Status') ?></label>
            <span class="acc-select"><select id="rv-status"><option value="all"><?= v2_te('All') ?></option><option value="published"><?= v2_te('Published') ?></option><option value="draft"><?= v2_te('Drafts') ?></option><option value="moderation"><?= v2_te('In moderation') ?></option><option value="rejected"><?= v2_te('Rejected') ?></option></select><?= $rvCaret ?></span>
          </div>
          <div class="acc-field">
            <label for="rv-rating-filter"><?= v2_te('Rating') ?></label>
            <span class="acc-select"><select id="rv-rating-filter"><option value="all"><?= v2_te('Any rating') ?></option><?php for ($rvN = 5; $rvN >= 1; $rvN--): ?><option value="<?= $rvN ?>"><?= v2_e(v2_num($rvN, 'star', 'stars')) ?></option><?php endfor; ?></select><?= $rvCaret ?></span>
          </div>
        </div>
        <div class="rv-list-head">
          <p class="rv-list-count" id="rv-list-count" aria-live="polite"></p>
          <button class="btn btn-ghost" type="button" id="rv-reset" hidden><?= v2_te('Reset filters') ?></button>
        </div>

        <div class="rv-skels" id="rv-list-skel" aria-hidden="true"><div class="rv-skel"></div><div class="rv-skel"></div></div>
        <div class="rv-empty is-error" id="rv-list-error" hidden>
          <h3><?= v2_te('We could not load your reviews') ?></h3>
          <p><?= v2_te('Check your connection and try again.') ?></p>
          <button class="btn btn-ghost" type="button" id="rv-list-retry"><?= v2_te('Try again') ?></button>
        </div>
        <ul class="rv-list" id="rv-list" hidden></ul>
        <div class="rv-empty" id="rv-list-empty" hidden>
          <h3 id="rv-list-empty-h"><?= v2_te('You have no reviews yet') ?></h3>
          <p id="rv-list-empty-p"><?= v2_te('The reviews you write appear here, together with your drafts.') ?></p>
          <button class="btn btn-ghost" type="button" id="rv-list-empty-reset" hidden><?= v2_te('Reset filters') ?></button>
        </div>
      </section>

      <!-- INFO -->
      <section class="rv-cards" aria-label="<?= v2_te('Why reviews matter') ?>">
        <article class="rv-info"><p class="acc-k"><?= v2_te('Personalisation') ?></p><h2><?= v2_te('Reviews shape your recommendations.') ?></h2><p><?= v2_te('The ratings and preferences in your reviews can improve what we recommend next.') ?></p></article>
        <article class="rv-info is-mint"><p class="acc-k"><?= v2_te('Community') ?></p><h2><?= v2_te('Help other customers.') ?></h2><p><?= v2_te('A good review takes away doubt and builds trust in the activities.') ?></p></article>
        <article class="rv-info is-deep"><p class="acc-k"><?= v2_te('Bonus') ?></p><h2><?= v2_te('Points for reviews?') ?></h2><p><?= v2_te('Eligible reviews can earn bonus points when campaigns are running.') ?></p></article>
      </section>

      <!-- EDIT -->
      <dialog class="rv-dialog" id="rv-edit" aria-labelledby="rv-edit-h">
        <form class="rv-d-form" id="rv-edit-form" novalidate>
          <div class="rv-d-head">
            <div><p class="acc-k"><?= v2_te('Edit review') ?></p><h2 id="rv-edit-h"></h2></div>
            <button class="rv-d-close" type="button" data-close aria-label="<?= v2_te('Close') ?>"><?= v2_ic('x') ?></button>
          </div>
          <div class="rv-d-body">
            <p class="rv-d-intro"><?= v2_te('If you change the rating or the text, the review is checked again and does not appear on the site until it is approved.') ?></p>
            <fieldset class="rv-rate">
              <legend><?= v2_te('Rating') ?></legend>
              <?= $rvStars('rv-e-rating') ?>
              <span class="rv-rate-t" id="rv-e-rating-t" aria-live="polite"></span>
            </fieldset>
            <div class="acc-field">
              <label for="rv-e-text"><?= v2_te('Your review') ?></label>
              <textarea class="rv-textarea" id="rv-e-text" rows="6" maxlength="2000" aria-describedby="rv-e-text-count"></textarea>
              <small class="rv-count" id="rv-e-text-count"></small>
            </div>
            <details class="rv-more" id="rv-e-more">
              <summary><span><?= v2_te('Detailed rating and options') ?></span></summary>
              <div class="rv-more-body">
                <?php foreach ($rvAspects as $rvKey => $rvLabel): ?>
                <fieldset class="rv-aspect"><legend><?= v2_e($rvLabel) ?></legend><?= $rvStars('rv-e-a-' . $rvKey, true, true) ?></fieldset>
                <?php endforeach; ?>
                <label class="rv-switch-row" for="rv-e-recommend"><span><?= v2_te('I recommend this activity') ?></span><input class="rv-switch" type="checkbox" role="switch" id="rv-e-recommend"></label>
                <label class="rv-switch-row" for="rv-e-anonymous"><span><?= v2_te('Publish without my name') ?></span><input class="rv-switch" type="checkbox" role="switch" id="rv-e-anonymous"></label>
              </div>
            </details>
            <p class="rv-note" id="rv-e-photos" hidden></p>
            <p class="rv-error" id="rv-edit-error" role="alert" hidden></p>
          </div>
          <div class="rv-d-foot">
            <button class="btn btn-primary" type="submit" id="rv-edit-save"><?= v2_te('Save changes') ?></button>
            <button class="btn btn-ghost" type="button" data-close><?= v2_te('Cancel') ?></button>
          </div>
        </form>
      </dialog>
    </div>

    <?php v2_account_end(); ?>
  </div>
</main>
<?php include __DIR__ . '/../includes/v2/footer.php'; ?>
