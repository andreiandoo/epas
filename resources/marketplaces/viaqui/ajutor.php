<?php
/**
 * Help center: /help (v2 design). /faqs, /faq and /intrebari redirect here, to the questions (#intrebari).
 *
 * Hero with search, quick chips and a route card, 4 fast actions, the FAQ list (categories with counts, search that
 * ignores diacritics and matches every word, links under answers), "still need help" contact routes, final CTA.
 * Every question is rendered on the server (indexable, FAQPage JSON-LD); help.js only filters. Filter state lives in
 * the URL (?q=&categorie=) so a filtered view can be shared.
 *
 * Shares the hero, route card and tile styles with /contact (contact.css); help.css adds the FAQ list and the lower
 * sections. Contact links carry ?motiv= so the contact form opens with the right reason.
 */

require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/api.php';

// 30-minute page cache: static content.
$pageCacheTTL = 1800;
require_once __DIR__ . '/includes/page-cache.php';

require_once __DIR__ . '/includes/nav-helpers.php';
require_once __DIR__ . '/includes/v2/helpers.php';
require_once __DIR__ . '/includes/v2/nav.php';

// All FAQs inline (server-rendered for SEO + JSON-LD).
$faqs = [
    ['category' => 'orders', 'categoryLabel' => v2_t('Orders'), 'q' => v2_t('When do I get my tickets after paying?'),
        'a' => v2_t('Tickets are issued once the payment is confirmed and are sent by email. If you have an account, you also find them under My tickets.'),
        'links' => [[v2_t('My tickets'), '/account/tickets'], [v2_t('Find your order'), '/find-order']]],
    ['category' => 'orders', 'categoryLabel' => v2_t('Orders'), 'q' => v2_t('What do I do if I did not get the confirmation email?'),
        'a' => v2_t('Check the Spam, Promotions or Updates folders. Then use the order recovery page with the email you used at checkout and the order number, if you have it.'),
        'links' => [[v2_t('Order recovery'), '/find-order']]],
    ['category' => 'tickets', 'categoryLabel' => v2_t('Tickets and QR'), 'q' => v2_t('Do I need to print my ticket?'),
        'a' => v2_t('Normally not. You can show the QR code on your phone. If a venue asks for something else, this is stated on the activity page and on the ticket.')],
    ['category' => 'tickets', 'categoryLabel' => v2_t('Tickets and QR'), 'q' => v2_t('Can I put different names on the tickets?'),
        'a' => v2_t('Yes. At checkout you choose whether all tickets have the same holder or each ticket has a different name. Handy for groups, gifts and corporate orders.')],
    ['category' => 'tickets', 'categoryLabel' => v2_t('Tickets and QR'), 'q' => v2_t('What happens if the QR code does not scan?'),
        'a' => v2_t('The venue staff can check the ticket by its code, the order number or the details of the ticket holder, depending on how the venue works.')],
    ['category' => 'payments', 'categoryLabel' => v2_t('Payments and fees'), 'q' => v2_t('Which payment methods are available?'),
        'a' => v2_t('Checkout takes cards (Visa, Mastercard), Apple Pay, Google Pay and Card Cultural (Edenred / Sodexo / Up România), depending on the payment processor.')],
    ['category' => 'payments', 'categoryLabel' => v2_t('Payments and fees'), 'q' => v2_t('Why are fees shown separately in the cart?'),
        'a' => v2_t('The platform fees and any processing fees are shown separately to keep things transparent. The final total is shown before you confirm the payment.')],
    ['category' => 'payments', 'categoryLabel' => v2_t('Payments and fees'), 'q' => v2_t('What do I do if the payment failed but the money seems blocked?'),
        'a' => v2_t('Some payments can show up for a while as authorisations on your bank account. If the payment is not confirmed, the order is not issued. Check the order status or contact support.'),
        'links' => [[v2_t('Contact support'), '/contact']]],
    ['category' => 'refunds', 'categoryLabel' => v2_t('Refunds'), 'q' => v2_t('Can I ask for a refund on tickets?'),
        'a' => v2_t('A refund depends on the policy of the activity, the status of the ticket, when you ask and any options you bought (ticket protection, for example).'),
        'links' => [[v2_t('Send a request'), '/contact?motiv=refund#formular']]],
    ['category' => 'refunds', 'categoryLabel' => v2_t('Refunds'), 'q' => v2_t('What is ticket protection?'),
        'a' => v2_t('Ticket protection is an optional extra that can give you flexibility if you can no longer make it. The exact conditions are shown at checkout.')],
    ['category' => 'refunds', 'categoryLabel' => v2_t('Refunds'), 'q' => v2_t('How long does a refund take?'),
        'a' => v2_t('It depends on the payment processor, the bank that issued the card and the status of the request. Once the refund is approved, the money can take a few working days to arrive.')],
    ['category' => 'bonus', 'categoryLabel' => v2_t('Points and gift cards'), 'q' => v2_t('How do bonus points work?'),
        'a' => v2_t('Eligible orders earn bonus points. They show up in your account once the order is confirmed and can be used on future orders.'),
        'links' => [[v2_t('My points'), '/account/points']]],
    ['category' => 'bonus', 'categoryLabel' => v2_t('Points and gift cards'), 'q' => v2_t('Can I use the points on the same order?'),
        'a' => v2_t('Normally, points are earned once an order is confirmed and are used on future orders.')],
    ['category' => 'bonus', 'categoryLabel' => v2_t('Points and gift cards'), 'q' => v2_t('How do I check a gift card?'),
        'a' => v2_t('You can check the balance and validity of a gift card or voucher on its own page.'),
        'links' => [[v2_t('Check a voucher'), '/voucher'], [v2_t('Gift card'), '/gift-card']]],
    ['category' => 'account', 'categoryLabel' => v2_t('Customer account'), 'q' => v2_t('Can an account be created for me automatically after checkout?'),
        'a' => v2_t('Yes. You can place the order without an account and have one created automatically, then set the password later.')],
    ['category' => 'account', 'categoryLabel' => v2_t('Customer account'), 'q' => v2_t('Where do I find my orders and tickets?'),
        'a' => v2_t('In your customer account, under My tickets and My orders. There you see statuses, PDFs, QR codes and your history.'),
        'links' => [[v2_t('My account'), '/account']]],
    ['category' => 'venues', 'categoryLabel' => v2_t('Venues'), 'q' => v2_t('How do I list a venue on Viaqui?'),
        'a' => v2_t('Go to the For venues page and send us the details: the venue, the city, the type of activities and how you sell tickets today.'),
        'links' => [[v2_t('For venues'), '/partners']]],
    ['category' => 'venues', 'categoryLabel' => v2_t('Venues'), 'q' => v2_t('Can a venue have several activities?'),
        'a' => v2_t('Yes. A venue can have a main page and several activities: escape rooms, tours, workshops, packages, entry tickets.')],
    ['category' => 'venues', 'categoryLabel' => v2_t('Venues'), 'q' => v2_t('How does check-in at the entrance work?'),
        'a' => v2_t('Tickets are issued with a unique QR code, and the venue staff can scan them to validate them and control access.')],
];

$categoryCounts = [];
foreach ($faqs as $faq) {
    $categoryCounts[$faq['category']] = ($categoryCounts[$faq['category']] ?? 0) + 1;
}
$hpCategories = [
    ['key' => 'all', 'label' => v2_t('All'), 'count' => count($faqs)],
    ['key' => 'orders', 'label' => v2_t('Orders'), 'count' => $categoryCounts['orders'] ?? 0],
    ['key' => 'tickets', 'label' => v2_t('Tickets and QR'), 'count' => $categoryCounts['tickets'] ?? 0],
    ['key' => 'payments', 'label' => v2_t('Payments and fees'), 'count' => $categoryCounts['payments'] ?? 0],
    ['key' => 'refunds', 'label' => v2_t('Refunds'), 'count' => $categoryCounts['refunds'] ?? 0],
    ['key' => 'bonus', 'label' => v2_t('Points and gifts'), 'count' => $categoryCounts['bonus'] ?? 0],
    ['key' => 'account', 'label' => v2_t('Customer account'), 'count' => $categoryCounts['account'] ?? 0],
    ['key' => 'venues', 'label' => v2_t('Venues'), 'count' => $categoryCounts['venues'] ?? 0],
];
$quickChips = [
    ['label' => v2_t('QR tickets'), 'key' => 'tickets'],
    ['label' => v2_t('Refunds'), 'key' => 'refunds'],
    ['label' => v2_t('Payments'), 'key' => 'payments'],
    ['label' => v2_t('Points'), 'key' => 'bonus'],
    ['label' => v2_t('Venues'), 'key' => 'venues'],
];
$hpTiles = [
    ['/find-order', 'ticket', v2_t('Find your order'), v2_t('Find your tickets by email and order number.')],
    ['/contact?motiv=refund#formular', 'coins', v2_t('Refund request'), v2_t('Check the policy and send a structured request.')],
    ['/voucher', 'gift', v2_t('Check a voucher'), v2_t('See the balance and validity of your gift card.')],
    ['/contact', 'envelope-simple', v2_t('Contact support'), v2_t('Did not find the answer? Write to us.')],
];

$pageTitleRaw = v2_t('Help and frequently asked questions') . ' · ' . SITE_NAME;
$pageDescription = v2_t('Quick answers about orders, QR tickets, payments, refunds, ticket protection, bonus points, gift cards, the customer account and access for venues.');
$canonicalUrl = SITE_URL . '/help';
$structuredData = [[
    '@context' => 'https://schema.org',
    '@type' => 'FAQPage',
    'mainEntity' => array_map(fn ($f) => [
        '@type' => 'Question',
        'name' => $f['q'],
        'acceptedAnswer' => ['@type' => 'Answer', 'text' => $f['a']],
    ], $faqs),
]];

$v2Styles = ['contact.css', 'help.css'];
$v2Scripts = ['help.js'];
$v2HeaderOverlay = true;
$v2ClientData = ['categories' => array_column($hpCategories, 'label', 'key')];

include __DIR__ . '/includes/v2/head.php';
include __DIR__ . '/includes/v2/header.php';
?>
<main id="main" tabindex="-1">
  <!-- HERO -->
  <section class="ct-hero hp-hero" aria-labelledby="hp-h">
    <svg class="deco-arches" viewBox="0 0 400 400" aria-hidden="true" focusable="false"><path d="M40 400V200a160 160 0 0 1 320 0v200"/><path d="M90 400V200a110 110 0 0 1 220 0v200"/><path d="M140 400V200a60 60 0 0 1 120 0v200"/></svg>
    <svg class="ct-line draw-clip" viewBox="0 590 3240 310" aria-hidden="true" focusable="false"><use href="#drum-g"/></svg>
    <div class="ct-in">
      <div class="ct-copy">
        <p class="ct-kicker"><?= v2_te('FAQ · orders · tickets · venues') ?></p>
        <h1 class="ct-h hp-h" id="hp-h"><?= v2_te('Quick answers, without the back-and-forth with support.') ?></h1>
        <p class="ct-lead"><?= v2_te('Find answers about orders, QR tickets, payments, fees, refunds, ticket protection, bonus points, gift cards, the customer account and access for venues.') ?></p>
        <form class="hp-search" id="hp-search-form" role="search" action="/help" method="get">
          <label class="sr" for="hp-search"><?= v2_te('Search the questions') ?></label>
          <?= v2_ic('magnifying-glass') ?>
          <input id="hp-search" name="q" type="search" autocomplete="off" enterkeyhint="search" placeholder="<?= v2_te('Search: tickets, refund, voucher, points, payment...') ?>">
          <button class="hp-clear" id="hp-clear" type="button" aria-label="<?= v2_te('Clear the search') ?>" hidden><?= v2_ic('x') ?></button>
        </form>
        <p class="hp-found" id="hp-found" role="status"><span id="hp-found-t"></span><a href="#intrebari" id="hp-jump" hidden><?= v2_te('See the answers') ?><?= v2_ic('arrow-right') ?></a></p>
        <div class="hp-chips" aria-label="<?= v2_te('Popular topics') ?>">
          <?php foreach ($quickChips as $chip): ?>
          <button type="button" data-hp-chip="<?= v2_e($chip['key']) ?>"><?= v2_e($chip['label']) ?></button>
          <?php endforeach; ?>
        </div>
      </div>
      <!-- the column always exists so the header turns solid at the white card on desktop and at the end of the hero
           on a phone, where the card is hidden (the fast actions below cover the same routes) -->
      <div class="ct-router-col">
        <div id="hdr-sentinel" aria-hidden="true"></div>
        <div class="ct-router">
          <p class="ct-router-k"><?= v2_te('Help router') ?></p>
          <h2 class="ct-router-h"><?= v2_te('The fastest fixes.') ?></h2>
          <div class="ct-router-list">
            <a class="ct-route is-primary" href="/find-order"><span><b><?= v2_te('I cannot find my tickets') ?></b><small><?= v2_te('order recovery') ?></small></span><?= v2_ic('ticket') ?></a>
            <a class="ct-route" href="/contact?motiv=refund#formular"><span><b><?= v2_te('I want a refund') ?></b><small><?= v2_te('request / status') ?></small></span><?= v2_ic('coins') ?></a>
            <a class="ct-route is-mint" href="/voucher"><span><b><?= v2_te('Gift card') ?></b><small><?= v2_te('check balance / code') ?></small></span><?= v2_ic('gift') ?></a>
          </div>
        </div>
      </div>
    </div>
  </section>

  <!-- FAST ACTIONS -->
  <section class="ct-fast" aria-label="<?= v2_te('Quick actions') ?>">
    <div class="wrap ct-tiles">
      <?php foreach ($hpTiles as $ti => [$tileHref, $tileIcon, $tileTitle, $tileText]): ?>
      <a class="ct-tile<?= $ti === 3 ? ' is-deep' : '' ?>" href="<?= v2_e($tileHref) ?>">
        <span class="ct-tile-ic"><?= v2_ic($tileIcon) ?></span>
        <h2><?= v2_e($tileTitle) ?></h2>
        <p><?= v2_e($tileText) ?></p>
        <span class="ct-tile-go" aria-hidden="true"><?= v2_ic('arrow-right') ?></span>
      </a>
      <?php endforeach; ?>
    </div>
  </section>

  <!-- FAQ LIST -->
  <section class="hp-faq" id="intrebari" aria-labelledby="hp-cat-title">
    <div class="wrap hp-faq-grid">
      <aside class="hp-aside" aria-label="<?= v2_te('FAQ categories') ?>">
        <div class="hp-aside-card">
          <p class="hp-aside-k"><?= v2_te('FAQ categories') ?></p>
          <div class="hp-cats">
            <?php foreach ($hpCategories as $cat): ?>
            <button type="button" data-hp-cat="<?= v2_e($cat['key']) ?>" aria-pressed="<?= $cat['key'] === 'all' ? 'true' : 'false' ?>"><span><?= v2_e($cat['label']) ?></span><small><?= (int) $cat['count'] ?></small></button>
            <?php endforeach; ?>
          </div>
          <div class="hp-tip">
            <b><?= v2_te('Not sure where it fits?') ?></b>
            <p><?= v2_te('Search with simple words: “QR”, “refund”, “voucher”, “fee”, “name on ticket”.') ?></p>
          </div>
        </div>
      </aside>

      <div class="hp-main">
        <div class="hp-main-head">
          <div>
            <p class="kicker"><?= v2_te('Questions') ?></p>
            <h2 id="hp-cat-title" tabindex="-1"><?= v2_te('All') ?></h2>
          </div>
          <p class="hp-count" id="hp-count"><?= v2_te('{shown} of {total} questions', ['shown' => count($faqs), 'total' => count($faqs)]) ?></p>
        </div>
        <div class="hp-list" id="hp-list">
          <?php foreach ($faqs as $faq): ?>
          <details class="hp-item" data-cat="<?= v2_e($faq['category']) ?>">
            <summary>
              <span class="hp-q"><small><?= v2_e($faq['categoryLabel']) ?></small><?= v2_e($faq['q']) ?></span>
              <span class="pm"><?= v2_ic('plus') ?></span>
            </summary>
            <div class="hp-a">
              <p><?= v2_e($faq['a']) ?></p>
              <?php if (!empty($faq['links'])): ?>
              <div class="hp-links">
                <?php foreach ($faq['links'] as [$linkLabel, $linkHref]): ?><a href="<?= v2_e($linkHref) ?>"><?= v2_e($linkLabel) ?><?= v2_ic('arrow-right') ?></a><?php endforeach; ?>
              </div>
              <?php endif; ?>
            </div>
          </details>
          <?php endforeach; ?>
          <div class="hp-empty" id="hp-empty" hidden>
            <h3><?= v2_te('No questions match this filter.') ?></h3>
            <p><?= v2_te('Try a broader word or reset the category.') ?></p>
            <button class="btn btn-primary" id="hp-reset" type="button"><?= v2_te('Reset') ?></button>
          </div>
        </div>
      </div>
    </div>
  </section>

  <!-- STILL NEED HELP -->
  <section class="hp-help" aria-labelledby="hp-help-h">
    <div class="wrap hp-help-grid">
      <div>
        <p class="hp-help-k"><?= v2_te('Support') ?></p>
        <h2 id="hp-help-h"><?= v2_te('Did not find the answer?') ?></h2>
        <p class="hp-help-p"><?= v2_te('Use the contact page and pick the right reason. For orders, include the email you used, the order number and the name of the activity.') ?></p>
      </div>
      <div class="hp-router">
        <div class="hp-router-head">
          <p class="hp-router-k"><?= v2_te('Contact router') ?></p>
          <h3><?= v2_te('Send us the right context') ?></h3>
        </div>
        <div class="hp-router-list">
          <a href="/contact?motiv=order#formular"><span><b><?= v2_te('A problem with an order') ?></b><small><?= v2_te('tickets, QR, email, payment') ?></small></span><?= v2_ic('arrow-right') ?></a>
          <a href="/contact?motiv=refund#formular"><span><b><?= v2_te('Refund') ?></b><small><?= v2_te('eligibility, status, ticket protection') ?></small></span><?= v2_ic('arrow-right') ?></a>
          <a href="/contact?motiv=venue#formular"><span><b><?= v2_te('Venue / organiser') ?></b><small><?= v2_te('listing, demo, dashboard') ?></small></span><?= v2_ic('arrow-right') ?></a>
        </div>
      </div>
    </div>
  </section>

  <!-- FINAL CTA -->
</main>
<?php include __DIR__ . '/includes/v2/footer.php'; ?>
