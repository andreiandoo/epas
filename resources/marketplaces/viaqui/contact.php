<?php
/**
 * Contact and help: /contact (v2 design).
 *
 * Support routing page: hero with a route card, 4 fast actions, the contact form (reason and priority change the hints
 * and the routing note), contact routes, FAQ.
 *
 * contact.js posts the form to `POST /contact` through the API proxy (core MarketplaceClient\ConfigController::contact),
 * which emails the marketplace inbox with reply-to set to the visitor. Core validates first_name, last_name, email,
 * subject (≤50), message (≤5000), optional phone and order_id, plus a honeypot (website_url). The old page sent name,
 * reason, priority and reference_id instead, so every message failed validation, and its error handler showed "sent"
 * anyway: messages were lost. The form now asks for first and last name, maps the reason to core's subject, keeps the
 * visitor's own subject line, priority and reference inside the message, and says when sending fails.
 */

require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/api.php';

// 30-minute page cache: static content, the form posts through the proxy.
$pageCacheTTL = 1800;
require_once __DIR__ . '/includes/page-cache.php';

require_once __DIR__ . '/includes/nav-helpers.php';
require_once __DIR__ . '/includes/v2/helpers.php';
require_once __DIR__ . '/includes/v2/nav.php';

// reason => label, core subject (a known key where one fits, else a short label), reference field, placeholders
$ctReasons = [
    'order' => ['label' => v2_t('Order / tickets'), 'subject' => 'bilete', 'ref' => v2_t('Order number (optional)'), 'refPh' => v2_t('e.g. {code}', ['code' => 'MKT-W08ABJWH']), 'orderRef' => true,
        'subjectPh' => v2_t('E.g. I did not receive my tickets'), 'messagePh' => v2_t('Describe the problem: which activity you bought, which email you used, which error message you see.')],
    'refund' => ['label' => v2_t('Refund'), 'subject' => 'rambursare', 'ref' => v2_t('Order number'), 'refPh' => v2_t('e.g. {code}', ['code' => 'MKT-W08ABJWH']), 'orderRef' => true,
        'subjectPh' => v2_t('E.g. I want to check the status of my refund request'), 'messagePh' => v2_t('Tell us which tickets you want to return, why, and whether you bought ticket protection.')],
    'gift' => ['label' => v2_t('Gift card / voucher'), 'subject' => 'Gift card / voucher', 'ref' => v2_t('Gift card code (optional)'), 'refPh' => 'GIFT-2026-WOW', 'orderRef' => false,
        'subjectPh' => v2_t('E.g. The gift card is not applied at checkout'), 'messagePh' => v2_t('Include the voucher code and what happens when you enter it.')],
    'venue' => ['label' => v2_t('Venue / organiser'), 'subject' => 'organizator', 'ref' => v2_t('Venue website / social link'), 'refPh' => 'https://...', 'orderRef' => false,
        'subjectPh' => v2_t('E.g. I want to list my venue on Viaqui'), 'messagePh' => v2_t('Describe the venue, the city, the types of activities, the opening hours and how you sell tickets today.')],
    'partnership' => ['label' => v2_t('Partnership / affiliation'), 'subject' => 'parteneriat', 'ref' => v2_t('Website / channel'), 'refPh' => v2_t('website / Instagram / newsletter'), 'orderRef' => false,
        'subjectPh' => v2_t('E.g. Collaboration / affiliate proposal'), 'messagePh' => v2_t('Describe your audience, your channel, the kind of collaboration and the results you are after.')],
    'press' => ['label' => v2_t('Press / brand'), 'subject' => 'Press / brand', 'ref' => v2_t('Publication / organisation'), 'refPh' => v2_t('name of the publication / company'), 'orderRef' => false,
        'subjectPh' => v2_t('E.g. Press request / brand assets'), 'messagePh' => v2_t('Tell us what information you need, your deadline and the context of the piece.')],
    'other' => ['label' => v2_t('Another reason'), 'subject' => 'altele', 'ref' => v2_t('Reference (optional)'), 'refPh' => '', 'orderRef' => false,
        'subjectPh' => v2_t('E.g. A question about the platform'), 'messagePh' => v2_t('Write your question or situation as clearly as you can.')],
];
$ctPriorities = ['normal' => v2_t('Normal'), 'today' => v2_t('Activity today'), 'payment' => v2_t('Payment problem'), 'access' => v2_t('Problem at the entrance')];
$ctMessageMax = 4500; // core allows 5000; the rest holds the subject, priority and reference lines

$ctTiles = [
    ['/find-order', 'ticket', v2_t('Find your order'), v2_t('Did not get the email or cannot find your tickets?'), ''],
    ['#formular', 'coins', v2_t('Refund request'), v2_t('Check eligibility and send a request.'), 'refund'],
    ['/gift-card', 'gift', v2_t('Gift card'), v2_t('Buy a voucher or check its balance.'), ''],
    ['/partners', 'map-pin', v2_t('For venues'), v2_t('List activities and sell tickets online.'), ''],
];
$ctRoutes = [
    [v2_t('Customer'), 'ticket', v2_t('Orders and tickets'), v2_t('For tickets not delivered, PDF, QR, ticket holder name or calendar.'), '/find-order', v2_t('Order recovery'), '', ''],
    [v2_t('Refund'), 'coins', v2_t('Refunds and ticket protection'), v2_t('For cancellations, status, ticket protection or refunds.'), '#formular', v2_t('Send a request'), 'refund', 'is-mint'],
    [v2_t('Gift'), 'gift', v2_t('Gift cards'), v2_t('For codes, balance, delivery or an invalid voucher.'), '/voucher', v2_t('Check a voucher'), '', ''],
    [v2_t('B2B'), 'map-pin', v2_t('Venues and organisers'), v2_t('For listing, a demo, the dashboard or new activities.'), '/partners', v2_t('For venues'), '', ''],
    [v2_t('Partnership'), 'users-three', v2_t('Affiliates and collaborations'), v2_t('For local guides, influencers, media, tourism.'), '#formular', v2_t('Send a proposal'), 'partnership', 'is-deep'],
    [v2_t('Legal'), 'lock-simple', v2_t('Privacy, cookies, terms'), v2_t('For GDPR requests, terms, cookies or reports.'), '/privacy', v2_t('Privacy'), '', ''],
];
$faqs = [
    [v2_t('I did not get my tickets. What do I do?'), v2_t('Check the Spam / Promotions folder, then use the order recovery page with your email and order number. If you still cannot find the tickets, send us a message with the order number.')],
    [v2_t('Can I ask for a refund on tickets?'), v2_t('It depends on the policy of the activity, the status of the ticket and the options you bought. Send a message with the reason Refund.')],
    [v2_t('My gift card does not work. What do I do?'), v2_t('First check the code on its own page. If it shows as invalid or with the wrong balance, include the code in your message to support.')],
    [v2_t('How do I list a venue on Viaqui?'), v2_t('Choose the reason Venue / organiser in the form, and include the city, the type of activities and how you sell tickets today. We will get back to you with a demo and pricing.')],
    [v2_t('What personal data is processed through the form?'), v2_t('The data you send is used to handle your request. See the Privacy policy for details.')],
];
$defaultReason = $ctReasons['order'];

$pageTitleRaw = v2_t('Contact and help') . ' · ' . SITE_NAME;
$pageDescription = v2_t('Have a question about an order, tickets, a refund, a gift card or listing a venue? Pick the right reason and get to the answer faster.');
$canonicalUrl = SITE_URL . '/contact';

$v2Styles = ['contact.css'];
$v2Scripts = ['contact.js'];
$v2LegacyScripts = ['assets/js/config.js', 'assets/js/utils.js', 'assets/js/api.js'];
$v2HeaderOverlay = true;
$v2ClientData = ['reasons' => $ctReasons, 'priorities' => $ctPriorities, 'supportEmail' => SUPPORT_EMAIL, 'messageMax' => $ctMessageMax];
$v2HeadExtra = '<script>window.BILETEONLINE = ' . json_encode([
    'siteName' => SITE_NAME,
    'siteUrl' => SITE_URL,
    'apiUrl' => '/api/proxy.php',
    'storageUrl' => STORAGE_URL,
    'env' => API_ENV,
    'locale' => SITE_LOCALE,
    'currency' => 'RON',
    'supportEmail' => SUPPORT_EMAIL,
], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG) . ';</script>';

include __DIR__ . '/includes/v2/head.php';
include __DIR__ . '/includes/v2/header.php';
?>
<main id="main" tabindex="-1">
  <!-- HERO -->
  <section class="ct-hero" aria-labelledby="ct-h">
    <svg class="deco-arches" viewBox="0 0 400 400" aria-hidden="true" focusable="false"><path d="M40 400V200a160 160 0 0 1 320 0v200"/><path d="M90 400V200a110 110 0 0 1 220 0v200"/><path d="M140 400V200a60 60 0 0 1 120 0v200"/></svg>
    <svg class="ct-line draw-clip" viewBox="0 590 3240 310" aria-hidden="true" focusable="false"><use href="#drum-g"/></svg>
    <div class="ct-in">
      <div class="ct-copy">
        <p class="ct-kicker"><?= v2_te('Support · orders · tickets · venues') ?></p>
        <h1 class="ct-h" id="ct-h"><?= v2_te('How can we help?') ?></h1>
        <p class="ct-lead"><?= v2_te('Have a question about an order, cannot find your tickets, want to list a venue or need help with a gift card? Pick the right reason and get to the answer faster.') ?></p>
        <div class="ct-cta">
          <a class="btn btn-light" href="/find-order"><?= v2_ic('ticket') ?><?= v2_te('Find your order') ?></a>
          <a class="btn btn-outline-light" href="#formular"><?= v2_ic('envelope-simple') ?><?= v2_te('Send a message') ?></a>
        </div>
      </div>
      <!-- the column always exists so the header turns solid at the white card on desktop and at the end of the hero
           on a phone, where the card is hidden (the fast actions below cover the same routes) -->
      <div class="ct-router-col">
        <div id="hdr-sentinel" aria-hidden="true"></div>
        <div class="ct-router">
          <p class="ct-router-k"><?= v2_te('Support router') ?></p>
          <h2 class="ct-router-h"><?= v2_te('Pick the right route.') ?></h2>
          <div class="ct-router-list">
            <a class="ct-route is-primary" href="/find-order"><span><b><?= v2_te('I cannot find my tickets') ?></b><small><?= v2_te('order recovery') ?></small></span><?= v2_ic('ticket') ?></a>
            <a class="ct-route" href="#formular" data-reason="refund"><span><b><?= v2_te('I want a refund') ?></b><small><?= v2_te('request / status') ?></small></span><?= v2_ic('coins') ?></a>
            <a class="ct-route is-mint" href="/voucher"><span><b><?= v2_te('Gift card') ?></b><small><?= v2_te('check / balance') ?></small></span><?= v2_ic('gift') ?></a>
          </div>
        </div>
      </div>
    </div>
  </section>

  <!-- FAST ACTIONS -->
  <section class="ct-fast" aria-label="<?= v2_te('Quick actions') ?>">
    <div class="wrap ct-tiles">
      <?php foreach ($ctTiles as $ti => [$href, $icon, $title, $text, $reason]): ?>
      <a class="ct-tile<?= $ti === 3 ? ' is-deep' : '' ?>" href="<?= v2_e($href) ?>"<?= $reason ? ' data-reason="' . v2_e($reason) . '"' : '' ?>>
        <span class="ct-tile-ic"><?= v2_ic($icon) ?></span>
        <h2><?= v2_e($title) ?></h2>
        <p><?= v2_e($text) ?></p>
        <span class="ct-tile-go" aria-hidden="true"><?= v2_ic('arrow-right') ?></span>
      </a>
      <?php endforeach; ?>
    </div>
  </section>

  <!-- CONTACT FORM -->
  <section class="ct-form-sec" id="formular" aria-labelledby="ct-form-h">
    <div class="wrap ct-form-grid">
      <div class="ct-form-intro">
        <p class="kicker"><?= v2_te('Contact form') ?></p>
        <h2 id="ct-form-h"><?= v2_te('Send us the right details from the start.') ?></h2>
        <p class="ct-form-lead"><?= v2_te('The better the reason fits and the more relevant details you include, the easier it is for your message to reach the right team.') ?></p>
        <div class="ct-include">
          <b><?= v2_te('For orders, include:') ?></b>
          <ul>
            <li><?= v2_ic('check') ?><?= v2_te('the order number, if you have it;') ?></li>
            <li><?= v2_ic('check') ?><?= v2_te('the email used for the order;') ?></li>
            <li><?= v2_ic('check') ?><?= v2_te('the name of the activity;') ?></li>
            <li><?= v2_ic('check') ?><?= v2_te('what exactly happened.') ?></li>
          </ul>
        </div>
      </div>

      <form class="ct-form" id="ct-form" novalidate>
        <div class="ct-sent" id="ct-sent" role="status" tabindex="-1" hidden>
          <?= v2_ic('check-circle') ?>
          <div><b><?= v2_te('Message sent ✓') ?></b><p><?= v2_te('We will reply to the email you gave as soon as we can. Check your inbox and the Spam folder.') ?></p></div>
        </div>
        <p class="ct-error" id="ct-error" role="alert" tabindex="-1" hidden></p>

        <!-- honeypot: people never see or reach it; bots that fill it are dropped by core -->
        <div class="ct-trap" aria-hidden="true"><label for="ct-website"><?= v2_te('Website (leave empty)') ?></label><input id="ct-website" name="website_url" type="text" tabindex="-1" autocomplete="off"></div>

        <div class="ct-fields">
          <div class="ct-field">
            <label for="ct-reason"><?= v2_te('Reason for contact') ?></label>
            <select class="select" id="ct-reason" name="reason">
              <?php foreach ($ctReasons as $key => $r): ?><option value="<?= $key ?>"><?= v2_e($r['label']) ?></option><?php endforeach; ?>
            </select>
          </div>
          <div class="ct-field">
            <label for="ct-priority"><?= v2_te('Priority') ?></label>
            <select class="select" id="ct-priority" name="priority">
              <?php foreach ($ctPriorities as $key => $label): ?><option value="<?= $key ?>"><?= v2_e($label) ?></option><?php endforeach; ?>
            </select>
          </div>
          <div class="ct-field">
            <label for="ct-first"><?= v2_te('First name') ?></label>
            <input id="ct-first" name="first_name" type="text" autocomplete="given-name" maxlength="100" required placeholder="<?= v2_te('First name') ?>">
          </div>
          <div class="ct-field">
            <label for="ct-last"><?= v2_te('Last name') ?></label>
            <input id="ct-last" name="last_name" type="text" autocomplete="family-name" maxlength="100" required placeholder="<?= v2_te('Last name') ?>">
          </div>
          <div class="ct-field">
            <label for="ct-email"><?= v2_te('Email') ?></label>
            <input id="ct-email" name="email" type="email" inputmode="email" autocomplete="email" spellcheck="false" maxlength="180" required placeholder="name@example.com">
          </div>
          <div class="ct-field">
            <label for="ct-phone"><?= v2_te('Phone (optional)') ?></label>
            <input id="ct-phone" name="phone" type="tel" autocomplete="tel" maxlength="50" placeholder="+43...">
          </div>
          <div class="ct-field is-wide">
            <label for="ct-ref" id="ct-ref-label"><?= v2_e($defaultReason['ref']) ?></label>
            <input id="ct-ref" name="reference" type="text" autocomplete="off" maxlength="80" placeholder="<?= v2_e($defaultReason['refPh']) ?>">
          </div>
          <div class="ct-field is-wide">
            <label for="ct-subject"><?= v2_te('Subject') ?></label>
            <input id="ct-subject" name="subject" type="text" maxlength="150" required placeholder="<?= v2_e($defaultReason['subjectPh']) ?>">
          </div>
          <div class="ct-field is-wide">
            <label for="ct-message"><?= v2_te('Message') ?></label>
            <textarea id="ct-message" name="message" rows="6" maxlength="<?= $ctMessageMax ?>" required placeholder="<?= v2_e($defaultReason['messagePh']) ?>" aria-describedby="ct-count"></textarea>
            <span class="ct-count" id="ct-count">0 / <?= $ctMessageMax ?></span>
          </div>
          <label class="ct-check is-wide">
            <input id="ct-consent" name="consent" type="checkbox" required>
            <span><?= v2_t('I confirm that the details I sent are correct and I agree to them being processed to handle my request, in line with the <a href="{url}">Privacy policy</a>.', ['url' => '/privacy']) ?></span>
          </label>
        </div>

        <div class="ct-route-note" id="ct-route" data-tone="calm">
          <b id="ct-route-t"><?= v2_te('Message routed to support') ?></b>
          <p id="ct-route-p"><?= v2_te('Include clear details so the request can be handled quickly.') ?></p>
        </div>

        <button class="btn btn-primary ct-submit" id="ct-submit" type="submit"><?= v2_te('Send the message') ?></button>
      </form>
    </div>
  </section>

  <!-- CONTACT ROUTES -->
  <section class="sec ct-routes" aria-labelledby="ct-routes-h">
    <div class="wrap">
      <div class="ct-routes-head">
        <p class="kicker"><?= v2_te('Contact routes') ?></p>
        <h2 id="ct-routes-h"><?= v2_te('Every request has a better route.') ?></h2>
        <p><?= v2_te('The contact page cuts down on incomplete messages and points you to the right action before you write to support.') ?></p>
      </div>
      <div class="ct-route-cards">
        <?php foreach ($ctRoutes as [$k, $icon, $title, $text, $href, $cta, $reason, $variant]): ?>
        <article class="ct-card <?= $variant ?>">
          <div class="ct-card-top"><p class="ct-card-k"><?= v2_e($k) ?></p><span class="ct-card-ic"><?= v2_ic($icon) ?></span></div>
          <h3><?= v2_e($title) ?></h3>
          <p><?= v2_e($text) ?></p>
          <a href="<?= v2_e($href) ?>"<?= $reason ? ' data-reason="' . v2_e($reason) . '"' : '' ?>><?= v2_e($cta) ?><?= v2_ic('arrow-right') ?></a>
        </article>
        <?php endforeach; ?>
      </div>
    </div>
  </section>

  <!-- FAQ -->
  <section class="sec ct-faq" aria-labelledby="ct-faq-h">
    <div class="wrap ct-faq-grid">
      <div>
        <p class="kicker"><?= v2_te('FAQ') ?></p>
        <h2 id="ct-faq-h"><?= v2_te('Frequently asked questions') ?></h2>
      </div>
      <div>
        <?php foreach ($faqs as $fi => [$faqQ, $faqA]): ?>
        <details class="qa"<?= $fi === 0 ? ' open' : '' ?>><summary><?= v2_e($faqQ) ?><span class="pm"><?= v2_ic('plus') ?></span></summary><p><?= v2_e($faqA) ?></p></details>
        <?php endforeach; ?>
      </div>
    </div>
  </section>
</main>
<?php include __DIR__ . '/includes/v2/footer.php'; ?>
