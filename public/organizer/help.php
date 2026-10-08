<?php
/**
 * Operator help centre: /organizator/help (help.php), v2 design.
 *
 * Inside the v2 operator shell. Every section of the old page, restyled: the search over the questions, the three topic
 * cards, the three groups of questions (Începe rapid, the catalogue, Plăți și finanțe; the anchors #getting-started,
 * #activities and #payments are kept for old links) and the contact block (support hours, a ticket, the e-mail and the
 * phone when core's /config has one). org-help.js filters the questions while typing.
 *
 * Rewritten for operators (viaqui.com operators sell locations and products and get bookings; they sell no events):
 * every question is still there, but answers that sent them to "Activități", "Participanți" or "Promo" (screens they no
 * longer have) now point to Locațiile mele, Produse, Rezervări, Sold and Cont & companie. The promo codes question says
 * codes are not available for operators yet. New: a message when the search finds nothing.
 */

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/api.php';
require_once __DIR__ . '/../includes/nav-helpers.php';
require_once __DIR__ . '/../includes/v2/helpers.php';
require_once __DIR__ . '/../includes/v2/organizer.php';

$configData = api_cached('client_config', fn() => api_get('/config'), 3600);
$contactPhone = trim((string) ($configData['data']['contact']['phone'] ?? SUPPORT_PHONE));
$contactEmail = trim((string) ($configData['data']['contact']['email'] ?? SUPPORT_EMAIL));
if ($contactEmail === '') {
    $contactEmail = SUPPORT_EMAIL;
}

$pageTitle = v2_t('Help centre');
$pageDescription = v2_t('Answers for Viaqui operators: venues, products, bookings, checking tickets, commissions and payouts.');
$canonicalUrl = SITE_URL . '/organizator/help';
$noindex = true;
$skipPageCache = true;

$v2Styles = ['organizer.css', 'org-help.css'];
$v2Scripts = ['organizer.js', 'org-help.js'];
$v2LegacyScripts = ['assets/js/config.js', 'assets/js/utils.js', 'assets/js/api.js', 'assets/js/auth.js'];
$v2HeadExtra = v2_account_client_config('organizer');

/** One question: the question (plain text) and the answer (trusted markup). */
$ohpQ = function (string $q, string $a) {
    return '<details class="ohp-q"><summary><span class="ohp-q-t">' . v2_e($q) . '</span>' . v2_ic('caret-down', 'ic ohp-q-ic') . '</summary>'
        . '<div class="ohp-a">' . $a . '</div></details>';
};
$ohpTopic = function (string $href, string $icon, string $title, string $sub) {
    return '<a class="ohp-topic" href="' . $href . '"><span class="ohp-topic-ic">' . v2_ic($icon) . '</span>'
        . '<span class="ohp-topic-t"><b>' . $title . '</b><span>' . $sub . '</span></span>' . v2_ic('arrow-right', 'ic ohp-topic-go') . '</a>';
};
$ohpSite = v2_e(SITE_NAME);

include __DIR__ . '/../includes/v2/head.php';
v2_org_start('help');
?>
<div class="ohp" id="ohp">
  <header class="ohp-head">
    <p class="org-k"><?= v2_te('Help') ?></p>
    <h1 class="ohp-h"><?= v2_te('Help centre') ?></h1>
    <p class="ohp-lead"><?= v2_te('Find quick answers or get in touch.') ?></p>
    <form class="ohp-search" id="ohp-search" role="search" action="#" novalidate>
      <label class="sr" for="ohp-q"><?= v2_te('Search the help centre') ?></label>
      <?= v2_ic('magnifying-glass', 'ic ohp-search-ic') ?>
      <input type="search" id="ohp-q" autocomplete="off" spellcheck="false" enterkeyhint="search" maxlength="80" placeholder="<?= v2_te('Search the help centre…') ?>" aria-describedby="ohp-found">
      <button class="ohp-clear" type="button" id="ohp-clear" hidden><?= v2_ic('x') ?><span class="sr"><?= v2_te('Clear the search') ?></span></button>
    </form>
    <p class="ohp-found" id="ohp-found" role="status" aria-live="polite"></p>
  </header>

  <nav class="ohp-topics" aria-label="<?= v2_te('Topics') ?>">
    <?= $ohpTopic('#getting-started', 'lightning', v2_te('Quick start'), v2_te('A guide to your first venue and first product')) ?>
    <?= $ohpTopic('#activities', 'map-pin', v2_te('Venues and products'), v2_te('All about venues, products and bookings')) ?>
    <?= $ohpTopic('#payments', 'wallet', v2_te('Payments and finance'), v2_te('Commissions and payouts')) ?>
  </nav>

  <div class="ohp-secs">
    <section class="org-panel ohp-sec" id="getting-started" aria-labelledby="ohp-s1-h" tabindex="-1">
      <div class="ohp-sec-head"><span class="ohp-sec-ic"><?= v2_ic('lightning') ?></span><h2 class="org-panel-h" id="ohp-s1-h"><?= v2_te('Quick start') ?></h2></div>
      <div class="ohp-list">
        <?= $ohpQ(v2_t('How do I start selling on {site}?', ['site' => SITE_NAME]), '<ol>'
            . '<li>' . v2_t('Open <a href="{url}">My venues</a> and press “Add a venue”.', ['url' => '/organizator/locatii']) . '</li>'
            . '<li>' . v2_t('Fill in the venue page: the texts, the city, the address, the photos and the opening hours.') . '</li>'
            . '<li>' . v2_t('Send the venue for approval. The first publication waits for the approval of the {site} team.', ['site' => $ohpSite]) . '</li>'
            . '<li>' . v2_t('Open <a href="{url}">Products</a>, press “New product” and choose what you sell: access ticket, experience or package.', ['url' => '/organizator/produse']) . '</li>'
            . '<li>' . v2_t('Add the tickets, with their prices. The photo is optional: without one, the product uses the photo of the venue.') . '</li>'
            . '<li>' . v2_t('Send the product for approval. After approval, it appears on the site.') . '</li>'
            . '</ol>') ?>
        <?= $ohpQ(v2_t('What types of products can I create?'), '<p>' . v2_t('A product can be one of three kinds:') . '</p>'
            . '<ul>'
            . '<li>' . v2_t('<strong>Access ticket</strong>: entry for one day or several, parking, camping; adult, child or group tickets.') . '</li>'
            . '<li>' . v2_t('<strong>Experience</strong>: rentals, tours, workshops; with start times or for the whole day, per person or per boat / group.') . '</li>'
            . '<li>' . v2_t('<strong>Package</strong>: access tickets and experiences together, at a single price.') . '</li>'
            . '</ul>'
            . '<p>' . v2_t('In each product you add as many tickets as you want, with the names you want. The ticket name appears on the customer\'s ticket and at purchase, so choose one that is clear and easy to understand.') . '</p>'
            . '<p class="ohp-a-k">' . v2_t('Examples of names:') . '</p>'
            . '<ul>'
            . '<li>' . v2_t('<strong>Adult</strong> / <strong>General admission</strong>: the regular entry') . '</li>'
            . '<li>' . v2_t('<strong>Child</strong>: the price for children') . '</li>'
            . '<li>' . v2_t('<strong>Group</strong>: one ticket for several people') . '</li>'
            . '<li>' . v2_t('<strong>Day ticket</strong> / <strong>Pass</strong>: valid for one day or several days') . '</li>'
            . '<li>' . v2_t('<strong>Parking</strong> / <strong>Camping</strong>: what else the venue offers, besides entry') . '</li>'
            . '</ul>'
            . '<p>' . v2_t('Each ticket has its own price and description. If you have a limited number of places per day, you set it in the product.') . '</p>') ?>
        <?= $ohpQ(v2_t('How do I check tickets at the entrance?'), '<p>' . v2_t('You have two options:') . '</p>'
            . '<ul>'
            . '<li>' . v2_t('<strong>QR scanning</strong>: tickets are validated at the entrance with the scanning app, using the phone camera.') . '</li>'
            . '<li>' . v2_t('<strong>The list of the day</strong>: in <a href="{url}">Bookings</a>, under “By day”, you see who comes each day, by product and by time. There you also mark a visitor who paid but did not come.', ['url' => '/organizator/rezervari']) . '</li>'
            . '</ul>') ?>
      </div>
    </section>

    <section class="org-panel ohp-sec" id="activities" aria-labelledby="ohp-s2-h" tabindex="-1">
      <div class="ohp-sec-head"><span class="ohp-sec-ic"><?= v2_ic('map-pin') ?></span><h2 class="org-panel-h" id="ohp-s2-h"><?= v2_te('Venues and products') ?></h2></div>
      <div class="ohp-list">
        <?= $ohpQ(v2_t('How do I change a published venue or product?'), '<p>' . v2_t('Open it from <a href="{venues}">My venues</a> or from <a href="{products}">Products</a>, change what you want and press “Save”. After the first approval, changes appear on the site right away.', ['venues' => '/organizator/locatii', 'products' => '/organizator/produse']) . '</p>'
            . '<p>' . v2_t('The price of bookings that are already paid does not change.') . '</p>') ?>
        <?= $ohpQ(v2_t('Can I stop selling or cancel a day?'), '<p>' . v2_t('Yes, depending on what you need:') . '</p>'
            . '<ul>'
            . '<li>' . v2_t('To take a venue or a product off the site, press “Hide from the site” on its page. You put it back with “Put on the site”.') . '</li>'
            . '<li>' . v2_t('For a day when the venue is closed, add it to “Days when it is closed”, in the opening hours of the venue.') . '</li>'
            . '<li>' . v2_t('For bookings that are already paid and must be cancelled, <a href="{url}">contact support</a>. Customers will be notified and refunded according to the policy.', ['url' => '/organizator/suport']) . '</li>'
            . '</ul>') ?>
        <?= $ohpQ(v2_t('Can I create promo codes or discounts?'), '<p>' . v2_t('Promo codes are not available in the operator account yet.') . '</p>'
            . '<p>' . v2_t('You can have different prices per ticket, for example for adult, child or group. For a discount campaign, <a href="{url}">open a ticket</a> and we will tell you what options you have.', ['url' => '/organizator/suport']) . '</p>') ?>
      </div>
    </section>

    <section class="org-panel ohp-sec" id="payments" aria-labelledby="ohp-s3-h" tabindex="-1">
      <div class="ohp-sec-head"><span class="ohp-sec-ic"><?= v2_ic('wallet') ?></span><h2 class="org-panel-h" id="ohp-s3-h"><?= v2_te('Payments and finance') ?></h2></div>
      <div class="ohp-list">
        <?= $ohpQ(v2_t('What are the {site} commissions?', ['site' => SITE_NAME]), '<p>' . v2_t('The commission is the one you negotiated, written in your contract. You can see it at any time in <a href="{url}">Account &amp; company › Contract</a>. Legal taxes may apply on top of it, where applicable.', ['url' => '/organizator/setari#contract']) . '</p>') ?>
        <?= $ohpQ(v2_t('When do I receive my sales money?'), '<p>' . v2_t('Payouts are made according to the contract, into the bank account from <a href="{account}">Account &amp; company</a>. In <a href="{balance}">Balance</a> you see how much you are due and what has already been paid to you.', ['account' => '/organizator/setari#bank', 'balance' => '/organizator/sold']) . '</p>'
            . '<p>' . v2_t('On request, a payout can also be made when sales reach an agreed minimum threshold.') . '</p>') ?>
        <?= $ohpQ(v2_t('How do I handle refunds?'), '<p>' . v2_t('Refunds for cancellations are processed automatically. For individual requests, <a href="{url}">contact support</a>.', ['url' => '/organizator/suport']) . '</p>'
            . '<p>' . v2_t('You write the cancellation terms of each product on the product page, under “Cancellation”.') . '</p>') ?>
      </div>
    </section>
  </div>

  <div class="org-empty ohp-empty" id="ohp-empty" hidden>
    <span class="org-empty-ic"><?= v2_ic('magnifying-glass') ?></span>
    <b><?= v2_te('No question matches') ?></b>
    <p><?= v2_te('Try other words or write to us from the section below.') ?></p>
    <button class="btn btn-ghost" type="button" id="ohp-reset"><?= v2_te('Show all questions') ?></button>
  </div>

  <section class="ohp-contact" id="contact" aria-labelledby="ohp-contact-h" tabindex="-1">
    <span class="ohp-contact-ic"><?= v2_ic('headset') ?></span>
    <h2 class="ohp-contact-h" id="ohp-contact-h"><?= v2_te('Did not find what you were looking for?') ?></h2>
    <p class="ohp-contact-p"><?= v2_te('The support team is available Monday to Friday, 9:00–18:00.') ?></p>
    <div class="ohp-contact-act">
      <a class="btn btn-light" href="/organizator/suport"><?= v2_ic('headset') ?><?= v2_te('Open a ticket') ?></a>
      <a class="btn btn-outline-light" href="mailto:<?= v2_e($contactEmail) ?>"><?= v2_ic('envelope-simple') ?><span class="ohp-ellip"><?= v2_e($contactEmail) ?></span></a>
      <?php if ($contactPhone !== ''): ?>
      <a class="btn btn-outline-light" href="tel:<?= v2_e(preg_replace('/[^0-9+]/', '', $contactPhone)) ?>"><?= v2_ic('phone') ?><?= v2_e($contactPhone) ?></a>
      <?php endif; ?>
    </div>
  </section>
</div>
<?php
v2_org_end();
include __DIR__ . '/../includes/v2/foot.php';
