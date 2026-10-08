<?php
/**
 * Cookie policy: /cookies (v2 design).
 *
 * The policy text, the four categories and the ways to change the choice. The banner and the settings dialog are
 * global (includes/v2/footer.php + base.js); the buttons here open that dialog through data-cc-action="open", so
 * nothing is wiped and the page doesn't reload (the old page deleted the saved choice and reloaded to bring the banner
 * back). cookies.js shows, on each category card and under the hero buttons, what the visitor has allowed right now,
 * and follows the dialog as it saves.
 *
 * The old text pointed to a floating cookie button that the v2 pages don't have; that sentence now names the buttons
 * on this page and the Cookies link in the footer.
 *
 * The policy text itself (.cp-prose) is plain English and does not go through v2_t(): like the other legal documents
 * it is translated as a whole file per language. The hero, the category cards and the buttons do go through it.
 */

require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/api.php';

// 30-minute page cache: static content (the visitor's own choice is read in the browser).
$pageCacheTTL = 1800;
require_once __DIR__ . '/includes/page-cache.php';

require_once __DIR__ . '/includes/nav-helpers.php';
require_once __DIR__ . '/includes/v2/helpers.php';
require_once __DIR__ . '/includes/v2/nav.php';

$cpCards = [
    ['essential', v2_t('Necessary'), v2_t('Essential'), v2_t('Cart, checkout, sign-in, session, security, remembering your consent and strictly aggregate first-party audience measurement. Always on.'), ''],
    ['analytics', v2_t('Measurement'), v2_t('Analytics'), v2_t('Third-party tools (Google Analytics), conversions and errors. Turned on only with your consent.'), 'is-soft'],
    ['personalization', v2_t('Recommendations'), v2_t('Personalisation'), v2_t('Recommendations based on the city and categories you visited, preferred filters. Local, with no external ads.'), 'is-mint'],
    ['marketing', v2_t('Campaigns'), v2_t('Marketing'), v2_t('Meta, Google and TikTok pixels for campaigns and remarketing. Only after you explicitly accept.'), 'is-deep'],
];

$pageTitle = v2_t('Cookie policy');
$pageDescription = v2_t('How viaqui.com uses cookies for the operation of the platform, analytics, personalisation and marketing. How you manage your preferences.');
$canonicalUrl = SITE_URL . '/cookies';

$v2Styles = ['cookies.css'];
$v2Scripts = ['cookies.js'];
$v2HeaderOverlay = true;

include __DIR__ . '/includes/v2/head.php';
include __DIR__ . '/includes/v2/header.php';
?>
<main id="main" tabindex="-1">
  <section class="cp-hero" aria-labelledby="cp-h">
    <svg class="deco-arches" viewBox="0 0 400 400" aria-hidden="true" focusable="false"><path d="M40 400V200a160 160 0 0 1 320 0v200"/><path d="M90 400V200a110 110 0 0 1 220 0v200"/><path d="M140 400V200a60 60 0 0 1 120 0v200"/></svg>
    <div class="cp-in">
      <p class="cp-kicker"><?= v2_te('Cookie policy') ?></p>
      <h1 class="cp-h" id="cp-h"><?= v2_te('How we use cookies on viaqui.com') ?></h1>
      <p class="cp-lead"><?= v2_te('We use cookies for the operation of the platform (cart, checkout, sign-in) and, only with your consent, for analytics, personalisation and marketing. You can change your choice at any time from the banner or from the button below.') ?></p>
      <div class="cp-cta">
        <button class="btn btn-light" type="button" data-cc-action="open"><?= v2_ic('lock-simple') ?><?= v2_te('Change preferences') ?></button>
        <a class="btn btn-outline-light" href="/privacy"><?= v2_te('Privacy policy') ?></a>
      </div>
      <p class="cp-status" id="cp-status" role="status"></p>
    </div>
    <div id="hdr-sentinel" aria-hidden="true"></div>
  </section>

  <section class="sec cp-body" aria-label="<?= v2_te('Categories and policy') ?>">
    <div class="wrap">
      <div class="cp-cards">
        <?php foreach ($cpCards as [$cardKey, $cardK, $cardTitle, $cardText, $cardTone]): ?>
        <article class="cp-card <?= $cardTone ?>">
          <div class="cp-card-top">
            <p class="cp-card-k"><?= v2_e($cardK) ?></p>
            <span class="cp-pill" data-cp-state="<?= $cardKey ?>" data-on="<?= $cardKey === 'essential' ? 'true' : 'false' ?>"><?= $cardKey === 'essential' ? v2_te('Always on') : v2_te('Off') ?></span>
          </div>
          <h2><?= v2_e($cardTitle) ?></h2>
          <p><?= v2_e($cardText) ?></p>
        </article>
        <?php endforeach; ?>
      </div>

      <div class="cp-prose">
        <h2>What are cookies?</h2>
        <p>Cookies are small files stored by your browser to make your online experience predictable: your cart stays full, your sign-in session persists, your preferences are remembered.</p>

        <h2>Our cookie categories</h2>
        <p><strong>Essential:</strong> necessary for the operation of the platform (cart, checkout, sign-in, security), plus first-party audience measurement that is strictly aggregate: no sharing with third parties, no cross-site tracking, with anonymised IP and limited retention. Under the guidance of the French data protection authority (CNIL) on audience measurement, this measurement can be exempt from consent, so it always stays on. These cookies cannot be turned off.</p>
        <p><strong>Analytics:</strong> third-party tools (for example Google Analytics), conversions and advanced reports. Turned on only with your consent.</p>
        <p><strong>Personalisation:</strong> we use your preferences (the city, the categories you visited) for more relevant recommendations. Optional.</p>
        <p><strong>Marketing:</strong> pixels for Meta, Google Ads and TikTok campaigns and custom audiences. Only after you explicitly accept.</p>

        <h2>How you manage your choice</h2>
        <p>On your first visit a banner appears where you can choose: <strong>Accept all</strong>, <strong>Reject optional</strong> or <strong>Customise</strong>.</p>
        <p>You can change the settings at any time from the "Change preferences" button at the top of this page or from the one at the end of it. This page is linked from the bottom of every page, under <strong>Cookies</strong>.</p>

        <h2>Your rights</h2>
        <p>Under the GDPR, you have the right to be informed about the processing of your data, to access it, to rectify it, to erase it or to object to its processing. For any GDPR request, write to us on the <a href="/contact">contact page</a> or see the <a href="/privacy">Privacy policy</a>.</p>
      </div>

      <div class="cp-final">
        <div>
          <p class="cp-final-k"><?= v2_te('Change at any time') ?></p>
          <h2><?= v2_te('Want to change your preferences?') ?></h2>
        </div>
        <button class="btn cp-btn-white" type="button" data-cc-action="open"><?= v2_te('Open cookie settings') ?></button>
      </div>
    </div>
  </section>
</main>
<?php include __DIR__ . '/includes/v2/footer.php'; ?>
