<?php
/**
 * Gift card landing: /gift-card (v2 design).
 *
 * Static landing with a live configurator: value, recipient, delivery, design and message update the card previews
 * (gift.js). Buying a gift card is not wired on this site (no proxy action, cart item or payment step), so "Add to
 * cart" says so and opens the contact page with the configuration filled in, instead of silently doing nothing.
 * Experiences picked in the finder (/gift-experiences) arrive through localStorage: the configurator starts from
 * their value and lists them.
 *
 * Top to bottom: hero (live card), why, configurator + preview, how it works, occasions, eligible activities,
 * balance check, FAQ, final CTA.
 */

$pageCacheTTL = 1800;
require_once __DIR__ . '/includes/page-cache.php';
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/api.php';
require_once __DIR__ . '/includes/nav-helpers.php';
require_once __DIR__ . '/includes/v2/helpers.php';
require_once __DIR__ . '/includes/v2/nav.php';

$gcMoney = fn (int $value): string => v2_money($value);
$amounts = [50, 100, 150, 250, 500, 1000];
$defaultAmount = 250;
$themes = [['wow', v2_t('Wow / surprise')], ['natura', v2_t('Nature / calm')], ['sarbatoare', v2_t('Celebration')], ['premium', v2_t('Premium')]];
$tomorrow = (new DateTimeImmutable('tomorrow', new DateTimeZone('Europe/Bucharest')))->format('Y-m-d');

$why = [
    [v2_t('Freedom to choose'), v2_t('The recipient picks the city, the category, the date and the activity that suits them.'), ''],
    [v2_t('Fast delivery'), v2_t('The card can be delivered digitally by email, right away or on a set date.'), 'is-mint'],
    [v2_t('Personal message'), v2_t('You add a message that turns the card into a personal gift.'), ''],
    [v2_t('Reusable balance'), v2_t('If it is not used in full, the balance can stay available, according to the gift card rules.'), 'is-deep'],
];
$steps = [
    [v2_t('Choose the value'), v2_t('Pick the amount that fits, or a custom value.')],
    [v2_t('Write the message'), v2_t('Add the name of the recipient and a personal wish.')],
    [v2_t('Send it'), v2_t('The card arrives by email right away or on the date you choose.')],
    [v2_t('They choose the experience'), v2_t('The code is used in the cart or at checkout for eligible activities.')],
];
$useCases = [
    [v2_t('Birthday'), v2_t('For someone who prefers memories to objects.')],
    [v2_t('Couple'), v2_t('An outing for two: a museum, a workshop, an escape room or a tour.')],
    [v2_t('Family'), v2_t('Activities for children, weekends and holidays.')],
    [v2_t('Corporate'), v2_t('Gifts for teams, clients or partners.')],
    [v2_t('Last minute'), v2_t('A digital gift, fast, with no physical delivery.')],
    [v2_t('Thank you'), v2_t('An elegant gesture for someone who helped.')],
];
$eligible = [
    ['escape-rooms', v2_t('Escape rooms')], ['muzee-expozitii', v2_t('Museums')], ['parcuri-de-distractii', v2_t('Parks')],
    ['natura-outdoor', v2_t('Nature')], ['ateliere-experiente-creative', v2_t('Workshops')], ['familie-copii', v2_t('Family')],
];
$faqs = [
    [v2_t('How is the gift card delivered?'), v2_t('The gift card is delivered digitally by email, either to you or straight to the recipient, depending on the option you choose.')],
    [v2_t('Where can it be used?'), v2_t('It can be used for the eligible activities on viaqui.com: escape rooms, museums, parks, workshops, nature and other listed experiences.')],
    [v2_t('Can it be used in part?'), v2_t('Yes. If the card balance is higher than the order value, the difference can stay available until the card expires, according to the gift card rules.')],
    [v2_t('Can I schedule the delivery?'), v2_t('Yes, the card can be sent right away or scheduled for a date you choose, if this option is active at checkout.')],
    [v2_t('Can I buy gift cards for a company?'), v2_t('Yes. For larger volumes or corporate gifts, you can use the contact form or a page dedicated to bulk orders.')],
];

$pageTitleRaw = v2_t('viaqui.com gift card: give an experience, not an object');
$pageDescription = v2_t('The viaqui.com digital gift card for activities, experiences and memorable outings. You choose the value and write the message, the recipient gets an email with a unique code.');
$canonicalUrl = SITE_URL . '/gift-card';
$ogImage = v2_asset('img/cat-familie-copii.webp');
$structuredData = [[
    '@context' => 'https://schema.org',
    '@type' => 'Product',
    'name' => v2_t('viaqui.com gift card'),
    'description' => v2_t('A digital gift card for activities and experiences: escape rooms, museums, parks, workshops, nature.'),
    'brand' => ['@type' => 'Brand', 'name' => 'viaqui.com'],
    'offers' => ['@type' => 'AggregateOffer', 'priceCurrency' => SITE_CURRENCY, 'lowPrice' => (string) min($amounts), 'highPrice' => (string) max($amounts), 'offerCount' => (string) count($amounts)],
], [
    '@context' => 'https://schema.org',
    '@type' => 'FAQPage',
    'mainEntity' => array_map(fn ($f) => ['@type' => 'Question', 'name' => $f[0], 'acceptedAnswer' => ['@type' => 'Answer', 'text' => $f[1]]], $faqs),
]];

$gcArches = '<svg class="deco-arches" viewBox="0 0 400 400" aria-hidden="true" focusable="false"><path d="M40 400V200a160 160 0 0 1 320 0v200"/><path d="M90 400V200a110 110 0 0 1 220 0v200"/><path d="M140 400V200a60 60 0 0 1 120 0v200"/></svg>';
$v2Styles = ['gift.css'];
$v2Scripts = ['gift.js'];
$v2HeaderOverlay = true;

include __DIR__ . '/includes/v2/head.php';
include __DIR__ . '/includes/v2/header.php';
?>
<main id="main" tabindex="-1">
  <!-- ===================== HERO ===================== -->
  <section class="gc-hero" aria-labelledby="gc-h">
    <?= $gcArches ?>
    <svg class="gc-line draw-clip" viewBox="0 590 3240 310" aria-hidden="true" focusable="false"><use href="#drum-g"/></svg>
    <div class="gc-hero-in">
      <div>
        <p class="gc-kicker"><?= v2_te('Digital gift card · experiences · QR tickets') ?></p>
        <h1 class="gc-h" id="gc-h"><?= v2_te('Give something to do.') ?></h1>
        <p class="gc-lead"><?= v2_te('A viaqui.com gift card does not make anyone choose an object. You let them choose an experience: an escape room, a museum, a park, a workshop, nature or a weekend outing.') ?></p>
        <div class="gc-cta">
          <a class="btn btn-light" href="#cumpara"><?= v2_te('Buy a gift card') ?><?= v2_ic('arrow-right') ?></a>
          <a class="btn btn-outline-light" href="#cum-functioneaza"><?= v2_te('How it works') ?></a>
        </div>
        <a class="gc-finder-link" href="/gift-experiences"><?= v2_ic('gift') ?><?= v2_te('Not sure which experience to give? Use the gift calculator') ?><?= v2_ic('arrow-right') ?></a>
      </div>

      <div class="gc-hero-art" aria-hidden="true">
        <div class="gc-card is-hero" data-theme="wow">
          <div class="gc-card-top"><span><?= v2_te('Gift card') ?></span><span>viaqui.com</span></div>
          <p class="gc-card-amount" data-gc="amount"><?= v2_e($gcMoney($defaultAmount)) ?></p>
          <p class="gc-card-for"><?= v2_t('for {name}', ['name' => '<strong data-gc="recipient" data-fallback="' . v2_te('someone who deserves a good day out') . '">' . v2_te('someone who deserves a good day out') . '</strong>']) ?></p>
          <div class="gc-card-bottom">
            <div><small><?= v2_te('Sample code') ?></small><b>GIFT-2026-WOW</b></div>
            <span class="gc-card-gift"><?= v2_ic('gift') ?></span>
          </div>
        </div>
        <div class="gc-note is-quote"><small><?= v2_te('Personal message') ?></small><p><?= v2_te('"Choose an experience that gets you out of the house."') ?></p></div>
        <div class="gc-note is-uses"><small><?= v2_te('Can be used for') ?></small><ul><li><?= v2_te('escape rooms') ?></li><li><?= v2_te('museums') ?></li><li><?= v2_te('parks') ?></li><li><?= v2_te('workshops') ?></li><li><?= v2_te('nature') ?></li></ul></div>
      </div>
    </div>
  </section>
  <div id="hdr-sentinel" aria-hidden="true"></div>

  <!-- ===================== WHY ===================== -->
  <section class="sec gc-why" aria-labelledby="gc-why-h">
    <div class="wrap gc-why-grid">
      <div class="gc-why-intro">
        <p class="kicker"><?= v2_te('Why') ?></p>
        <h2 id="gc-why-h"><?= v2_te('A gift that does not sit on a shelf.') ?></h2>
        <p><?= v2_te('The gift card is perfect when you do not know exactly which activity someone would prefer, but you are sure they would enjoy an outing, an experience or a memorable moment.') ?></p>
      </div>
      <ul class="gc-why-list">
        <?php foreach ($why as [$whyTitle, $whyText, $whyClass]): ?>
        <li class="gc-why-card <?= $whyClass ?>"><h3><?= v2_e($whyTitle) ?></h3><p><?= v2_e($whyText) ?></p></li>
        <?php endforeach; ?>
      </ul>
    </div>
  </section>

  <!-- ===================== CONFIGURATOR ===================== -->
  <section class="sec gc-build" id="cumpara" aria-labelledby="gc-build-h">
    <div class="wrap gc-build-grid">
      <div>
        <p class="kicker"><?= v2_te('Configurator') ?></p>
        <h2 id="gc-build-h"><?= v2_te('Build the gift card.') ?></h2>
        <p class="gc-build-lead"><?= v2_te('Choose the value, the recipient, the message and when it is delivered. The card is generated and sent digitally by email.') ?></p>

        <form class="gc-form" id="gc-form" novalidate>
          <div class="gc-picked" id="gc-picked" hidden>
            <div class="gc-picked-head">
              <span class="gc-picked-ic"><?= v2_ic('check-circle') ?></span>
              <p><b><?= v2_te('The experiences chosen for the gift') ?></b><span id="gc-picked-sum"></span></p>
            </div>
            <ul class="gc-picked-list" id="gc-picked-list"></ul>
            <div class="gc-picked-actions">
              <a class="sec-link" href="/gift-experiences#calculator"><?= v2_te('Change the experiences') ?><?= v2_ic('arrow-right') ?></a>
              <button class="link-btn" type="button" id="gc-picked-clear"><?= v2_te('Remove from the card') ?></button>
            </div>
          </div>
          <p class="gc-finder" id="gc-finder"><?= v2_ic('gift') ?><span><?= v2_t('Not sure what to choose? <a href="{url}">Find the right experience</a> and we work out the card value for you.', ['url' => '/gift-experiences']) ?></span></p>
          <div class="gc-fields">
            <div class="gc-field">
              <label for="gc-amount"><?= v2_te('Card value') ?></label>
              <select id="gc-amount">
                <?php foreach ($amounts as $amount): ?><option value="<?= $amount ?>"<?= $amount === $defaultAmount ? ' selected' : '' ?>><?= v2_e(v2_money($amount)) ?></option><?php endforeach; ?>
              </select>
            </div>
            <div class="gc-field">
              <label for="gc-recipient"><?= v2_te('Who is it for?') ?></label>
              <input id="gc-recipient" type="text" maxlength="60" autocomplete="off" placeholder="<?= v2_te('e.g. Maria, Alex, Anna and Tom') ?>">
            </div>
            <div class="gc-field">
              <label for="gc-email"><?= v2_te('Recipient email') ?></label>
              <input id="gc-email" type="email" autocomplete="off" placeholder="<?= v2_te('recipient@example.com') ?>">
            </div>
            <div class="gc-field">
              <label for="gc-delivery"><?= v2_te('When is it sent?') ?></label>
              <select id="gc-delivery">
                <option value="now"><?= v2_te('Right after purchase') ?></option>
                <option value="scheduled"><?= v2_te('On a date I choose') ?></option>
                <option value="me"><?= v2_te('I will send it myself later') ?></option>
              </select>
            </div>
            <div class="gc-field" id="gc-date-field" hidden>
              <label for="gc-date"><?= v2_te('Delivery date') ?></label>
              <input id="gc-date" type="date" min="<?= v2_e($tomorrow) ?>">
            </div>
            <div class="gc-field">
              <label for="gc-theme"><?= v2_te('Design') ?></label>
              <select id="gc-theme">
                <?php foreach ($themes as [$themeKey, $themeLabel]): ?><option value="<?= $themeKey ?>"><?= v2_e($themeLabel) ?></option><?php endforeach; ?>
              </select>
            </div>
            <div class="gc-field is-wide">
              <label for="gc-message"><?= v2_te('Personal message') ?></label>
              <textarea id="gc-message" rows="4" maxlength="180" placeholder="<?= v2_te('Write a short message for the recipient.') ?>" aria-describedby="gc-count"></textarea>
              <span class="gc-count" id="gc-count"><?= v2_te('{n}/180 characters', ['n' => 0]) ?></span>
            </div>
          </div>

          <div class="gc-info">
            <span class="gc-info-ic"><?= v2_ic('envelope-simple') ?></span>
            <p><b><?= v2_te('What does the recipient get?') ?></b><?= v2_te('An email with the gift card, a unique code, your message and a direct link to the eligible activities.') ?></p>
          </div>

          <div class="gc-actions">
            <button class="btn btn-primary" type="button" id="gc-add"><?= v2_ic('shopping-cart-simple') ?><?= v2_te('Add to cart') ?></button>
            <button class="btn btn-ghost" type="button" id="gc-show" aria-controls="gc-preview"><?= v2_te('Preview') ?></button>
          </div>
          <p class="gc-msg" id="gc-msg" role="status"></p>
        </form>
      </div>

      <aside class="gc-preview-wrap" aria-label="<?= v2_te('Gift card preview') ?>">
        <div class="gc-card is-preview" id="gc-preview" data-theme="wow" tabindex="-1">
          <div class="gc-card-top"><span><?= v2_te('Gift card') ?></span><span>viaqui.com</span></div>
          <p class="gc-card-amount" data-gc="amount"><?= v2_e($gcMoney($defaultAmount)) ?></p>
          <p class="gc-card-for"><?= v2_t('for {name}', ['name' => '<strong data-gc="recipient" data-fallback="' . v2_te('someone dear') . '">' . v2_te('someone dear') . '</strong>']) ?></p>
          <p class="gc-card-message" data-gc="message" data-fallback="<?= v2_te('Choose an experience that gets you out of the house.') ?>"><?= v2_te('Choose an experience that gets you out of the house.') ?></p>
          <div class="gc-card-bottom">
            <div><small><?= v2_te('Card code') ?></small><b>GIFT-2026-WOW</b></div>
            <span class="gc-card-gift"><?= v2_ic('gift') ?></span>
          </div>
        </div>
      </aside>
    </div>
  </section>

  <!-- ===================== HOW IT WORKS ===================== -->
  <section class="sec gc-how" id="cum-functioneaza" aria-labelledby="gc-how-h">
    <div class="wrap">
      <div class="gc-how-intro"><p class="kicker"><?= v2_te('How it works') ?></p><h2 id="gc-how-h"><?= v2_te('From gift to ticket, in a few steps.') ?></h2></div>
      <ol class="gc-steps">
        <?php foreach ($steps as $si => [$stepTitle, $stepText]): ?>
        <li class="gc-step<?= $si === count($steps) - 1 ? ' is-last' : '' ?>"><span class="gc-step-n"><?= $si + 1 ?></span><h3><?= v2_e($stepTitle) ?></h3><p><?= v2_e($stepText) ?></p></li>
        <?php endforeach; ?>
      </ol>
    </div>
  </section>

  <!-- ===================== OCCASIONS ===================== -->
  <section class="sec gc-uses" aria-labelledby="gc-uses-h">
    <?php readfile(__DIR__ . '/includes/v2/topo.svg'); ?>
    <div class="wrap gc-uses-grid">
      <div>
        <p class="kicker"><?= v2_te('For which occasions') ?></p>
        <h2 id="gc-uses-h"><?= v2_te('When you do not want one more generic gift.') ?></h2>
        <p><?= v2_te('The gift card works for different people because it does not assume you know exactly what they want. You give them options, not a forced choice.') ?></p>
      </div>
      <ul class="gc-uses-list">
        <?php foreach ($useCases as [$useTitle, $useText]): ?><li><h3><?= v2_e($useTitle) ?></h3><p><?= v2_e($useText) ?></p></li><?php endforeach; ?>
      </ul>
    </div>
  </section>

  <!-- ===================== ELIGIBLE ===================== -->
  <section class="sec gc-eligible" aria-labelledby="gc-eligible-h">
    <div class="wrap">
      <div class="sec-head">
        <div>
          <p class="kicker"><?= v2_te('Eligible activities') ?></p>
          <h2 id="gc-eligible-h"><?= v2_te('What can it be used for?') ?></h2>
          <p class="gc-eligible-lead"><?= v2_te('The gift card can be used for the eligible activities on the platform: escape rooms, museums, parks, workshops, nature or family experiences.') ?></p>
        </div>
        <a class="btn btn-ghost" href="/categories"><?= v2_te('See all categories') ?><?= v2_ic('arrow-right') ?></a>
      </div>
      <ul class="gc-cats">
        <?php foreach ($eligible as [$catSlug, $catTitle]): ?>
        <li><a class="gc-cat" href="/<?= v2_e($catSlug) ?>">
          <span class="gc-cat-media"><img src="<?= v2_e(v2_asset('img/cat-' . $catSlug . '-320.webp')) ?>" srcset="<?= v2_e(v2_asset('img/cat-' . $catSlug . '-320.webp')) ?> 320w, <?= v2_e(v2_asset('img/cat-' . $catSlug . '.webp')) ?> 640w" sizes="(min-width: 1024px) 15vw, (min-width: 600px) 30vw, 45vw" width="640" height="800" alt="" loading="lazy" decoding="async"></span>
          <b><?= v2_e($catTitle) ?><?= v2_ic('arrow-right') ?></b>
        </a></li>
        <?php endforeach; ?>
      </ul>
    </div>
  </section>

  <!-- ===================== BALANCE ===================== -->
  <section class="sec gc-balance" aria-labelledby="gc-balance-h">
    <div class="wrap gc-balance-grid">
      <div>
        <p class="kicker"><?= v2_te('Balance management') ?></p>
        <h2 id="gc-balance-h"><?= v2_te('One code. A clear balance. Simple to use.') ?></h2>
        <p><?= v2_te('The recipient enters the code in the cart or at checkout. If the order value is lower than the available balance, the difference can stay on the card, according to the gift card rules.') ?></p>
      </div>
      <div class="gc-check">
        <small><?= v2_te('Card check') ?></small>
        <h3>GIFT-2026-WOW</h3>
        <dl>
          <div class="is-mint"><dt><?= v2_te('Available balance') ?></dt><dd><?= v2_e(v2_money(180)) ?></dd></div>
          <div><dt><?= v2_te('Valid until') ?></dt><dd>2027</dd></div>
        </dl>
        <a class="btn btn-primary" href="/voucher"><?= v2_te('Check a card') ?><?= v2_ic('arrow-right') ?></a>
      </div>
    </div>
  </section>

  <!-- ===================== FAQ ===================== -->
  <section class="sec gc-faq" aria-labelledby="gc-faq-h">
    <div class="wrap gc-faq-grid">
      <div><p class="kicker"><?= v2_te('FAQ') ?></p><h2 id="gc-faq-h"><?= v2_te('Frequently asked questions') ?></h2></div>
      <div>
        <?php foreach ($faqs as $fi => [$faqQ, $faqA]): ?>
        <details class="qa"<?= $fi === 0 ? ' open' : '' ?>><summary><?= v2_e($faqQ) ?><span class="pm"><?= v2_ic('plus') ?></span></summary><p><?= v2_e($faqA) ?></p></details>
        <?php endforeach; ?>
      </div>
    </div>
  </section>

  <!-- ===================== FINAL CTA ===================== -->
  <section class="gc-final" aria-labelledby="gc-final-h">
    <div class="wrap">
      <div class="gc-final-in">
        <?= $gcArches ?>
        <div>
          <p class="kicker"><?= v2_te('Digital gift') ?></p>
          <h2 id="gc-final-h"><?= v2_te('Send an experience, not one more object.') ?></h2>
          <p><?= v2_te('Choose the value, write the message and let the recipient pick the activity that suits them.') ?></p>
        </div>
        <a class="btn btn-light" href="#cumpara"><?= v2_te('Buy a gift card') ?><?= v2_ic('arrow-right') ?></a>
      </div>
    </div>
  </section>
</main>
<?php include __DIR__ . '/includes/v2/footer.php'; ?>
