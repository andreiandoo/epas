<?php
/**
 * How it works: /how-it-works (v2 design). Static content, no API calls.
 *
 * Top to bottom: hero with a ticket mockup, a strip of activity types, three value props, the five steps (#pasii),
 * activity types with photos, the checkout explained (with a sample summary), the customer account, four secondary
 * props, the pitch for venues, FAQ, final CTA. JSON-LD: HowTo, FAQPage.
 *
 * Payment methods follow what checkout offers (card with Apple Pay / Google Pay, Card Cultural when the event accepts
 * it); the old page also listed Revolut Pay and RoPay, which checkout doesn't have.
 */

require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/api.php';

// 30-minute page cache: static content.
$pageCacheTTL = 1800;
require_once __DIR__ . '/includes/page-cache.php';

require_once __DIR__ . '/includes/nav-helpers.php';
require_once __DIR__ . '/includes/v2/helpers.php';
require_once __DIR__ . '/includes/v2/nav.php';

$hwPayments = [v2_t('Card'), 'Apple Pay', 'Google Pay', 'Card Cultural'];

// the mockup ticket always shows an upcoming Saturday, never a date in the past; short months keep the ticket's
// Date / Time row on one line
$hwMonths = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
$hwTicketDay = strtotime('saturday +4 weeks');
$hwTicketDate = date('j', $hwTicketDay) . ' ' . $hwMonths[(int) date('n', $hwTicketDay) - 1] . ' ' . date('Y', $hwTicketDay);

$hwProps = [
    ['map-pin', 'is-green', v2_t('Find places to go, not just events.'), v2_t('Viaqui is built for activities: escape rooms, museums, parks, tours, nature, workshops, things to do with kids and much more.')],
    ['check-circle', 'is-deep', v2_t('Know what you are buying before you get there.'), v2_t('Every page shows what the ticket includes, how long it takes, who it suits, where to go, how to get in and the rules you need to know.')],
    ['coins', 'is-yellow', v2_t('Get something back with every purchase.'), v2_t('Orders can earn bonus points, gift cards can be used on the platform, and ticket protection gives you extra flexibility.')],
];

$hwSteps = [
    [v2_t('Discover'), v2_t('Choose what you want to do.'), v2_t('Search by city, category, occasion or context: things to do with kids, indoors, at the weekend, on a budget, on rainy days, for couples, for groups or for team building.'), v2_t('Examples'), 'chips', ['Lisbon', v2_t('kids'), v2_t('indoor'), v2_t('today')]],
    [v2_t('Select'), v2_t('Check the details and choose your tickets.'), v2_t('On the activity page you see the description, opening hours, price, location, duration, recommended age, rules, what is included and the frequently asked questions.'), v2_t('Decisions'), 'list', [v2_t('date and time'), v2_t('ticket type'), v2_t('number of people'), v2_t('optional extras')]],
    [v2_t('Checkout'), v2_t('Pay online and personalise your order.'), v2_t('You can continue as a guest, sign in to your account or have an account created for you automatically. If the tickets are for several people, you can put a different name on each one.'), v2_t('Payment'), 'chips', $hwPayments],
    [v2_t('Issue'), v2_t('Get your tickets with a QR code.'), v2_t('Once the payment is confirmed, the tickets are issued electronically. You receive them by email, see them in your account and can download them as PDF or add them to your calendar.'), v2_t('You get'), 'list', [v2_t('ticket PDF'), v2_t('QR code'), v2_t('confirmation email'), v2_t('calendar')]],
    [v2_t('Entry'), v2_t('Go to the venue and scan your ticket.'), v2_t('At the entrance you show the QR code on your phone. The venue validates the ticket and you walk in: no need to print anything or dig through your emails.'), '', 'qr', []],
];

// the slugs name the photos (img/cat-<slug>.webp) and the category pages; they are not shown
$hwActivities = [
    ['escape-rooms', v2_t('Groups'), v2_t('Escape rooms'), v2_t('Choose the time and the number of people, and get tickets for the whole team.'), v2_t('A team in an escape room')],
    ['muzee-expozitii', v2_t('Culture'), v2_t('Museums and exhibitions'), v2_t('Buy entry tickets, guided tours or temporary exhibitions.'), v2_t('Visitors in a museum gallery')],
    ['parcuri-de-aventura', v2_t('Outdoor'), v2_t('Parks and adventure'), v2_t('Choose packages, age groups, time slots or entry tickets.'), v2_t('A treetop adventure course')],
    ['natura-outdoor', v2_t('Nature'), v2_t('Caves and nature reserves'), v2_t('See opening hours, access rules, difficulty and visiting details.'), v2_t('A mountain landscape')],
];

$hwCheckout = [
    [v2_t('Different ticket holders'), v2_t('Put a different name on each ticket: handy for groups and gifts.')],
    [v2_t('Automatic account'), v2_t('Buy quickly, and an account can be created for you after the order.')],
    [v2_t('Bonus points'), v2_t('See what you earn and use the points you have.')],
    [v2_t('Ticket protection'), v2_t('Add extra flexibility for refunds, where it is available.')],
];
// a sample order: the amounts are the original example's, shown in the site's currency
$hwSummary = [
    [v2_t('Tickets'), v2_money(316), ''],
    [v2_t('Platform fee: 2% per ticket'), v2_money(6.32), ''],
    [v2_t('Ticket protection'), v2_money(60), ''],
    [v2_t('Bonus points used'), '− ' . v2_money(8.20), 'is-minus'],
    [v2_t('Payment processing fee'), v2_money(5.83), ''],
];

$hwAccount = [
    [v2_t('My tickets'), v2_t('PDFs, QR codes, statuses, calendar and access details.'), ''],
    [v2_t('My orders'), v2_t('History, totals, fees, charges, receipts and statuses.'), ''],
    [v2_t('My points'), v2_t('Available balance, history and conversion into a discount.'), 'is-mint'],
    [v2_t('Gift cards'), v2_t('Cards received and bought, balances and active codes.'), 'is-deep'],
];
$hwSecondary = [
    [v2_t('QR'), v2_t('Unique codes'), v2_t('Every ticket has its own code, status and validation at the entrance.')],
    [v2_t('Payment'), v2_t('Modern payments'), v2_t('Card and digital wallets, depending on the setup available.')],
    [v2_t('Support'), v2_t('Order recovery'), v2_t('Recover your tickets with your email and order number.')],
    [v2_t('SEO'), v2_t('Clear pages'), v2_t('Every activity comes with useful information, not just a buy button.')],
];
$hwVenue = [
    [v2_t('SEO'), v2_t('Pages for the venue, its activities, cities and categories.')],
    [v2_t('QR'), v2_t('Fast scanning and clear ticket statuses.')],
    [v2_t('Data'), v2_t('Orders, customers, reports and availability.')],
    [v2_t('Growth'), v2_t('Promotions, gift cards, points and reviews.')],
];
$hwFaqs = [
    [v2_t('Do I need to print my ticket?'), v2_t('No. Normally you can show the QR code on your phone. If a venue has special rules, they are shown on the activity page and on the ticket.')],
    [v2_t('When do I get my tickets?'), v2_t('Once the payment is confirmed, the tickets are issued electronically and sent by email. If you have an account, you also find them under “My tickets”.')],
    [v2_t('Can I buy for someone else?'), v2_t('Yes. You can buy tickets for another person, put different names on the tickets and use gift cards for experiences.')],
    [v2_t('What happens if the payment stays pending?'), v2_t('If the payment is pending, the order is not lost. The tickets are issued automatically once the payment processor confirms it. If the payment fails, you can go through checkout again.')],
    [v2_t('How do bonus points work?'), v2_t('Eligible orders earn bonus points. They show up in your account and can be used as a discount on future orders, under the rules of the programme.')],
];
$hwMarquee = [v2_t('Escape rooms'), v2_t('Museums'), v2_t('Theme parks'), v2_t('Caves'), v2_t('Nature reserves'), v2_t('Workshops'), v2_t('Gift cards'), v2_t('Bonus points')];

$pageTitleRaw = v2_t('How Viaqui works: book activities, get a QR code, walk straight in');
$pageDescription = v2_t('See how Viaqui works: discover activities, choose the date and tickets, pay online, get your QR code instantly, earn bonus points and walk straight in at the venue.');
$canonicalUrl = SITE_URL . '/how-it-works';
$structuredData = [
    [
        '@context' => 'https://schema.org',
        '@type' => 'HowTo',
        'name' => v2_t('How to buy tickets on Viaqui'),
        'description' => v2_t('The main steps for buying activity tickets through Viaqui.'),
        'totalTime' => 'PT2M',
        'step' => [
            ['@type' => 'HowToStep', 'name' => v2_t('Choose the activity'), 'text' => v2_t('Search by city, category, audience, budget or the right moment.')],
            ['@type' => 'HowToStep', 'name' => v2_t('Select your tickets'), 'text' => v2_t('Choose the date, time, ticket type and number of participants.')],
            ['@type' => 'HowToStep', 'name' => v2_t('Pay online'), 'text' => v2_t('Pay securely by card, Apple Pay or Google Pay, or with Card Cultural where it is accepted.')],
            ['@type' => 'HowToStep', 'name' => v2_t('Get your QR code'), 'text' => v2_t('Once the payment is confirmed, the tickets are issued electronically and sent to your email and your account.')],
            ['@type' => 'HowToStep', 'name' => v2_t('Walk in at the venue'), 'text' => v2_t('At the entrance you show the QR code on your phone or on the ticket PDF.')],
        ],
    ],
    [
        '@context' => 'https://schema.org',
        '@type' => 'FAQPage',
        'mainEntity' => array_map(fn ($f) => ['@type' => 'Question', 'name' => $f[0], 'acceptedAnswer' => ['@type' => 'Answer', 'text' => $f[1]]], $hwFaqs),
    ],
];

$v2Styles = ['how.css'];
$v2HeaderOverlay = true;
$v2HeadExtra = '<meta name="keywords" content="' . v2_te('how online tickets work, QR tickets for activities, book activities online, buy tickets online, ticket bonus points, ticket protection, experience gift card') . '">';

include __DIR__ . '/includes/v2/head.php';
include __DIR__ . '/includes/v2/header.php';

// every card leads to its category (the nav's link when it has one, else the short URL slug.php resolves)
$hwCatHref = static function (string $slug) use ($V2NAV): string {
    return (string) ($V2NAV['categoryBySlug'][$slug]['href'] ?? '/' . $slug);
};
?>
<main id="main" tabindex="-1">
  <!-- HERO -->
  <section class="hw-hero" aria-labelledby="hw-h">
    <svg class="deco-arches" viewBox="0 0 400 400" aria-hidden="true" focusable="false"><path d="M40 400V200a160 160 0 0 1 320 0v200"/><path d="M90 400V200a110 110 0 0 1 220 0v200"/><path d="M140 400V200a60 60 0 0 1 120 0v200"/></svg>
    <div class="hw-in">
      <div class="hw-copy">
        <p class="hw-kicker"><?= v2_te('Quick guide · QR tickets · activities') ?></p>
        <h1 class="hw-h" id="hw-h"><?= v2_te('Search. Book. Walk in with a QR code.') ?></h1>
        <p class="hw-lead"><?= v2_te('Viaqui brings activities, experiences and places to visit together in one place. Choose what you want to do, pay online, get your ticket instantly and go straight to the entrance.') ?></p>
        <div class="hw-cta">
          <a class="btn btn-light" href="/categories"><?= v2_te('Explore activities') ?><?= v2_ic('arrow-right') ?></a>
          <a class="btn btn-outline-light" href="#pasii"><?= v2_te('See the steps') ?></a>
        </div>
        <dl class="hw-stats">
          <div><dt><?= v2_te('Issued') ?></dt><dd><?= v2_te('instantly') ?></dd></div>
          <div><dt><?= v2_te('Entry') ?></dt><dd><?= v2_te('QR') ?></dd></div>
          <div><dt><?= v2_te('Bonus') ?></dt><dd><?= v2_te('points') ?></dd></div>
        </dl>
      </div>

      <div class="hw-mock-col">
        <!-- the header turns solid when the white ticket reaches it -->
        <div id="hdr-sentinel" aria-hidden="true"></div>
        <div class="hw-mock" aria-hidden="true">
          <div class="hw-ticket">
            <div class="hw-ticket-main">
              <div class="hw-ticket-top"><span class="hw-valid"><?= v2_te('Valid ticket') ?></span><span class="hw-ticket-no">MKT-W08ABJWH</span></div>
              <p class="hw-ticket-title"><?= v2_te('Room 13') ?></p>
              <p class="hw-ticket-sub"><?= v2_te('Escape room · {city}', ['city' => 'Lisbon']) ?></p>
              <dl class="hw-ticket-meta">
                <div><dt><?= v2_te('Date') ?></dt><dd><?= v2_e($hwTicketDate) ?></dd></div>
                <div><dt><?= v2_te('Time') ?></dt><dd>18:30</dd></div>
                <div><dt><?= v2_te('Ticket holder') ?></dt><dd>Anna K.</dd></div>
                <div><dt><?= v2_te('Entry') ?></dt><dd><?= v2_te('QR scan') ?></dd></div>
              </dl>
              <div class="hw-ticket-bonus"><b><?= v2_te('+{n} bonus points', ['n' => 95]) ?></b><span><?= v2_te('Added to your account once the payment is confirmed.') ?></span></div>
            </div>
            <div class="hw-ticket-stub">
              <div class="hw-qr">
                <svg viewBox="0 0 120 120" focusable="false"><rect width="120" height="120" fill="#FFFFFF"/><path d="M8 8h32v32H8zM80 8h32v32H80zM8 80h32v32H8z" fill="#0F3D2E"/><path d="M16 16h16v16H16zM88 16h16v16H88zM16 88h16v16H16z" fill="#FFFFFF"/><path d="M22 22h4v4h-4zM94 22h4v4h-4zM22 94h4v4h-4z" fill="#0F3D2E"/><path d="M54 12h8v8h-8zm12 0h8v20h-8zM52 52h12v12H52zm20 0h8v8h-8zm12 12h24v8H84zm-28 18h8v24h-8zm16 0h12v12H72zm24 12h12v16H96zM44 72h20v8H44zM48 36h8v8h-8zm20 40h8v8h-8z" fill="#0F3D2E"/></svg>
                <span class="hw-scan"></span>
              </div>
              <p><?= v2_te('scan at the entrance') ?></p>
            </div>
          </div>
          <div class="hw-float is-checkout"><small><?= v2_te('Checkout') ?></small><b><?= v2_te('2 minutes') ?></b><span><?= v2_te('choose the ticket, pay, get the QR code') ?></span></div>
          <div class="hw-float is-pay"><small><?= v2_te('Payment methods') ?></small><div class="hw-pay-chips"><?php foreach ($hwPayments as $pi => $pay): ?><span<?= $pi === 0 ? ' class="is-on"' : '' ?>><?= v2_e($pay) ?></span><?php endforeach; ?></div></div>
        </div>
      </div>
    </div>
  </section>

  <!-- MARQUEE -->
  <div class="hw-marquee" aria-hidden="true">
    <div class="hw-marquee-track">
      <?php for ($copy = 0; $copy < 2; $copy++): ?>
      <div class="hw-marquee-set"><?php foreach ($hwMarquee as $word): ?><span><?= v2_e($word) ?></span><?php endforeach; ?></div>
      <?php endfor; ?>
    </div>
  </div>

  <!-- VALUE PROPS -->
  <section class="sec hw-props" aria-label="<?= v2_te('Why Viaqui') ?>">
    <div class="wrap hw-props-grid">
      <?php foreach ($hwProps as [$propIcon, $propTone, $propTitle, $propText]): ?>
      <article class="hw-prop">
        <span class="hw-prop-ic <?= $propTone ?>"><?= v2_ic($propIcon) ?></span>
        <h2><?= v2_e($propTitle) ?></h2>
        <p><?= v2_e($propText) ?></p>
      </article>
      <?php endforeach; ?>
    </div>
  </section>

  <!-- 5 STEPS -->
  <section class="hw-steps-sec" id="pasii" aria-labelledby="hw-steps-h">
    <div class="wrap hw-steps-grid">
      <div class="hw-steps-intro">
        <p class="kicker"><?= v2_te('5 steps') ?></p>
        <h2 id="hw-steps-h"><?= v2_te('From idea to entrance, without friction.') ?></h2>
        <p><?= v2_te('The process is the same whether you choose an escape room, a museum, a cave, a nature reserve or a workshop for kids.') ?></p>
        <a class="btn btn-primary" href="/categories"><?= v2_te('Start with a category') ?><?= v2_ic('arrow-right') ?></a>
      </div>
      <ol class="hw-steps">
        <?php foreach ($hwSteps as $si => [$stepLabel, $stepTitle, $stepBody, $sideLabel, $sideType, $sideItems]): ?>
        <li class="hw-step<?= $sideType === 'qr' ? ' is-dark' : '' ?>" data-step="<?= $si + 1 ?>">
          <div class="hw-step-main">
            <div class="hw-step-top"><span class="hw-step-n"><?= $si + 1 ?></span><p><?= v2_e($stepLabel) ?></p></div>
            <h3><?= v2_e($stepTitle) ?></h3>
            <p><?= v2_e($stepBody) ?></p>
          </div>
          <div class="hw-step-side">
            <?php if ($sideType === 'qr'): ?>
            <div class="hw-step-qr"><?= v2_ic('qr-code') ?><span><?= v2_te('Valid QR') ?></span></div>
            <?php else: ?>
            <p class="hw-side-k"><?= v2_e($sideLabel) ?></p>
            <?php if ($sideType === 'chips'): ?>
            <div class="hw-side-chips"><?php foreach ($sideItems as $item): ?><span><?= v2_e($item) ?></span><?php endforeach; ?></div>
            <?php else: ?>
            <ul class="hw-side-list"><?php foreach ($sideItems as $item): ?><li><?= v2_ic('check') ?><?= v2_e($item) ?></li><?php endforeach; ?></ul>
            <?php endif; ?>
            <?php endif; ?>
          </div>
        </li>
        <?php endforeach; ?>
      </ol>
    </div>
  </section>

  <!-- FOR WHO / ACTIVITY TYPES -->
  <section class="sec hw-types" aria-labelledby="hw-types-h">
    <div class="wrap">
      <div class="hw-head">
        <p class="kicker"><?= v2_te('Who it is for') ?></p>
        <h2 id="hw-types-h"><?= v2_te('One platform, many ways to get out of the house.') ?></h2>
        <p><?= v2_te('Not all activities are bought the same way. An escape room has time slots, a museum may have simple tickets, a cave may have guided tours and a theme park may have packages. The platform can handle them all.') ?></p>
      </div>
      <div class="hw-types-grid">
        <?php foreach ($hwActivities as [$typeSlug, $typeKicker, $typeTitle, $typeText, $typeAlt]):
            $typeHref = $hwCatHref($typeSlug); ?>
        <article class="hw-type">
          <img src="<?= v2_asset('img/cat-' . $typeSlug . '.webp') ?>" srcset="<?= v2_asset('img/cat-' . $typeSlug . '-320.webp') ?> 320w, <?= v2_asset('img/cat-' . $typeSlug . '.webp') ?> 640w" sizes="(min-width:1024px) 25vw, (min-width:640px) 50vw, 100vw" width="640" height="480" alt="<?= v2_e($typeAlt) ?>" loading="lazy" decoding="async">
          <div class="hw-type-body">
            <p class="hw-type-k"><?= v2_e($typeKicker) ?></p>
            <h3><a class="hw-type-link" href="<?= v2_e($typeHref) ?>"><?= v2_e($typeTitle) ?></a></h3>
            <p><?= v2_e($typeText) ?></p>
            <span class="hw-type-go" aria-hidden="true"><?= v2_te('See the activities') ?><?= v2_ic('arrow-right') ?></span>
          </div>
        </article>
        <?php endforeach; ?>
      </div>
    </div>
  </section>

  <!-- CHECKOUT INTELLIGENCE -->
  <section class="hw-checkout" aria-labelledby="hw-checkout-h">
    <div class="wrap hw-checkout-grid">
      <div>
        <p class="hw-dark-k"><?= v2_te('Smart checkout') ?></p>
        <h2 id="hw-checkout-h"><?= v2_te('More than “pay and done”.') ?></h2>
        <p class="hw-dark-p"><?= v2_te('Checkout is where the order becomes clear: who is going, which tickets are issued, which fees apply, how many points you earn and which extras you choose.') ?></p>
        <div class="hw-features">
          <?php foreach ($hwCheckout as [$featTitle, $featText]): ?>
          <div class="hw-feature"><h3><?= v2_e($featTitle) ?></h3><p><?= v2_e($featText) ?></p></div>
          <?php endforeach; ?>
        </div>
      </div>
      <div class="hw-summary" aria-label="<?= v2_te('Sample checkout summary') ?>">
        <div class="hw-summary-head"><p><?= v2_te('Checkout summary') ?></p><h3><?= v2_te('A clear order') ?></h3></div>
        <dl class="hw-summary-rows">
          <?php foreach ($hwSummary as [$rowLabel, $rowValue, $rowTone]): ?>
          <div><dt><?= v2_e($rowLabel) ?></dt><dd class="<?= $rowTone ?>"><?= v2_e($rowValue) ?></dd></div>
          <?php endforeach; ?>
          <div class="is-total"><dt><?= v2_te('Total') ?></dt><dd><?= v2_e(v2_money(379.95)) ?></dd></div>
        </dl>
        <div class="hw-summary-bonus"><b><?= v2_te('You earn +{n} bonus points', ['n' => 158]) ?></b><span><?= v2_te('Added to your account once the order is confirmed.') ?></span></div>
      </div>
    </div>
  </section>

  <!-- ACCOUNT -->
  <section class="sec hw-account" aria-labelledby="hw-account-h">
    <div class="wrap hw-account-grid">
      <div class="hw-head">
        <p class="kicker"><?= v2_te('Customer account') ?></p>
        <h2 id="hw-account-h"><?= v2_te('After you buy, everything stays organised.') ?></h2>
        <p><?= v2_te('The customer account is more than a login. It is where you find your tickets, orders, bonus points, gift cards, reviews and settings.') ?></p>
      </div>
      <div class="hw-account-cards">
        <?php foreach ($hwAccount as [$accTitle, $accText, $accTone]): ?>
        <article class="hw-acc <?= $accTone ?>"><h3><?= v2_e($accTitle) ?></h3><p><?= v2_e($accText) ?></p></article>
        <?php endforeach; ?>
      </div>
    </div>
  </section>

  <!-- SECONDARY VALUE PROPS -->
  <section class="hw-secondary" aria-label="<?= v2_te('In short') ?>">
    <div class="wrap hw-secondary-grid">
      <?php foreach ($hwSecondary as [$secK, $secTitle, $secText]): ?>
      <article class="hw-sec-card"><p class="hw-sec-k"><?= v2_e($secK) ?></p><h3><?= v2_e($secTitle) ?></h3><p><?= v2_e($secText) ?></p></article>
      <?php endforeach; ?>
    </div>
  </section>

  <!-- FOR LOCATIONS -->
  <section class="sec hw-venue-sec" aria-labelledby="hw-venue-h">
    <div class="wrap">
      <div class="hw-venue">
        <div class="hw-venue-copy">
          <p class="hw-dark-k"><?= v2_te('For venues too') ?></p>
          <h2 id="hw-venue-h"><?= v2_te('Venues get a sales platform, not just a form.') ?></h2>
          <p class="hw-dark-p"><?= v2_te('For organisers and venues, Viaqui means SEO pages, QR tickets, checkout, a dashboard, scanning, reports, gift cards, bonus points and a way to turn activities into products that are easy to buy.') ?></p>
          <a class="btn btn-light" href="/partners"><?= v2_te('See the page for venues') ?><?= v2_ic('arrow-right') ?></a>
        </div>
        <div class="hw-venue-cards">
          <?php foreach ($hwVenue as [$venueK, $venueText]): ?>
          <div class="hw-venue-card"><b><?= v2_e($venueK) ?></b><p><?= v2_e($venueText) ?></p></div>
          <?php endforeach; ?>
        </div>
      </div>
    </div>
  </section>

  <!-- FAQ -->
  <section class="sec hw-faq" aria-labelledby="hw-faq-h">
    <div class="wrap hw-faq-in">
      <div class="hw-faq-head">
        <p class="kicker"><?= v2_te('FAQ') ?></p>
        <h2 id="hw-faq-h"><?= v2_te('Frequently asked questions') ?></h2>
        <p><?= v2_te('The most important things to know before you buy.') ?></p>
      </div>
      <div>
        <?php foreach ($hwFaqs as $fi => [$faqQ, $faqA]): ?>
        <details class="qa"<?= $fi === 0 ? ' open' : '' ?>><summary><?= v2_e($faqQ) ?><span class="pm"><?= v2_ic('plus') ?></span></summary><p><?= v2_e($faqA) ?></p></details>
        <?php endforeach; ?>
      </div>
    </div>
  </section>

  <!-- FINAL CTA -->
  <section class="sec hw-final-sec">
    <div class="wrap">
      <div class="hw-final">
        <div>
          <p class="hw-final-k"><?= v2_te('Ready?') ?></p>
          <h2><?= v2_te('Find something to do.') ?></h2>
          <p><?= v2_te('Choose the city, category or occasion and buy your tickets online in a few minutes.') ?></p>
        </div>
        <div class="hw-final-cta">
          <a class="btn hw-btn-white" href="/categories"><?= v2_te('Explore categories') ?></a>
          <a class="btn btn-outline-light" href="/cities"><?= v2_te('Choose a city') ?></a>
        </div>
      </div>
    </div>
  </section>
</main>
<?php include __DIR__ . '/includes/v2/footer.php'; ?>
