<?php
/**
 * Become a partner: /devino-partener (v2 design). Sales page for venues and organizers.
 *
 * Top to bottom: hero (personalised by ?tip=<type>&loc=<name>), a strip of live categories, results in numbers, the
 * problem, booking with an animated demo (#booking), benefits, analytics and tracking (#analytics), payments, the 2%
 * commission (#bani — linked from /pentru-locatii), mobile app and local sales, fiscal / ANAF with an animated flow,
 * four steps (#cum), the Tixello engine (#tehnologie), categories, FAQ, final CTA (#contact).
 *
 * partner.js does what Alpine did: the personalisation (profile copy + signup links that carry tip/loc on to
 * /list-your-venue), the count-up numbers, both animated demos (stopped under reduced motion), the funnel ping
 * (leads.track page_view_landing) and CTA click tracking ([data-track-cta]) on the bo_lead_sid session.
 *
 * Categories come from the v2 nav data (local photos) instead of a second API call. Payment methods follow checkout
 * (cards through Stripe incl. Apple Pay / Google Pay, Card Cultural where accepted); the old page also listed Revolut,
 * RoPay and Netopia. The old share image (/assets/images/og-default.jpg) was a 404, so the v2 default is used.
 */

require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/api.php';

// 15-minute page cache; the personalisation is applied in the browser.
$pageCacheTTL = 900;
require_once __DIR__ . '/includes/page-cache.php';

require_once __DIR__ . '/includes/nav-helpers.php';
require_once __DIR__ . '/includes/v2/helpers.php';
require_once __DIR__ . '/includes/v2/nav.php';
require_once __DIR__ . '/includes/v2/partner-profiles.php';

$dpAccent = '<strong class="dp-accent">' . v2_te('just 2%*') . '</strong>';
$dpProfiles = v2_partner_profiles($dpAccent);
$dpAliases = V2_PARTNER_ALIASES;

// [value, label, what is printed before the number]
$dpStats = [[4294, v2_t('Events & activities'), ''], [96341, v2_t('Customers in the base'), ''], [301310, v2_t('Tickets sold'), ''], [4409557, v2_t('Sales generated'), '€']];
$dpProblems = [
    ['coins', v2_t('Commissions out of your margin'), v2_t('You pay, on every ticket. At volume, it is a real hole in the budget.')],
    ['calendar-blank', v2_t('Inflexible booking'), v2_t('Activities have slots, days, capacities. Most platforms do not support them.')],
    ['x', v2_t('Lost tracking'), v2_t('Ad blockers and iOS block conversion data, so you pay more for ads.')],
];
$dpBookingList = [v2_t('Available days picked on a calendar'), v2_t('Time slots with configurable capacity'), v2_t('Detailed booking: participants, options, add-ons'), v2_t('Group packages and prices by age group')];
$dpBenefits = [
    ['plus', v2_t('Unlimited activities'), v2_t('Add as many activities as you like, of any type and in any form. No limit, no start-up costs.')],
    ['list', v2_t('Advanced management'), v2_t('Capacities, slots, price variants, availability, add-ons: you control every detail of every activity.')],
    ['gift', v2_t('Discount codes'), v2_t('Create promo codes and discount campaigns, with your own rules, to lift your sales whenever you want.')],
    ['users-three', v2_t('Group packages'), v2_t('Sell packages for groups, families, classes or corporate teams, with dedicated prices and capacities.')],
    ['star', v2_t('Recommendation system'), v2_t('Our own engine shows your activities to the best-matched buyers, from a base of over {count} customers.', ['count' => v2_thousands(96000)])],
    ['lock-simple', v2_t('Secure tickets'), v2_t('QR validation, verification and anti-fraud protection: technology tested in production on Tixello.')],
];
$dpSmall = [
    ['ticket', v2_t('A dedicated page per activity'), v2_t('An SEO-friendly page, a link to share and schema markup for Google.')],
    ['clock', v2_t('Waiting lists'), v2_t('Slot full? The customer joins the waiting list and is told if places open up.')],
    ['qr-code', v2_t('Check-in & access control'), v2_t('QR validation with double-entry prevention, ideal for slots with fixed capacity.')],
    ['map-pin', v2_t('Multi-venue'), v2_t('Manage several venues or sites from a single account.')],
    ['user-circle', v2_t('Roles & team'), v2_t('Add colleagues with permissions (cashier, scanning, manager), without full access.')],
    ['heart', v2_t('Your own branding'), v2_t('Logo, colours and look on your pages, so they look like your brand.')],
    ['star', v2_t('Reviews & ratings'), v2_t('Customers leave reviews that lift conversion for the next buyers.')],
    ['list', v2_t('Export & reports'), v2_t('Export orders, participants and takings for reporting and accounting.')],
];
$dpAnalytics = [
    v2_t('See exactly which activity, slot and day sell best'),
    v2_t('Understand where buyers come from and which channel brings profit'),
    v2_t('Tune prices and capacity to real demand'),
    v2_t('Follow conversion from visit to sale, in real time'),
    v2_t('Spot the empty slots and fill them with targeted promotions'),
    v2_t('Decide on data, not on guesses'),
];
$dpPayments = [v2_t('Bank card'), 'Apple Pay', 'Google Pay', v2_t('Culture cards'), 'Stripe'];
$dpFiscal = [
    ['list', v2_t('ANAF documents'), v2_t('Automatic generation of the documents ANAF requires.')],
    ['credit-card', v2_t('Tax invoices'), v2_t('Issue tax invoices to customers straight from the platform.')],
    ['check-circle', v2_t('Accounting RO'), v2_t('Integration with accounting systems in Romania.')],
    ['coins', v2_t('Clear payouts'), v2_t('Payouts at regular intervals or on request, with transparent records.')],
];
$dpDocs = [['credit-card', v2_t('Tax invoice'), v2_t('series BO · customer')], ['list', v2_t('ANAF document'), v2_t('automatic reporting')], ['check-circle', v2_t('Accounting entry'), v2_t('accounting sync RO')]];
$dpSteps = [
    ['user-circle', v2_t('Create your account'), v2_t('Sign up in a few minutes. No start-up fees, no subscription, no card at signup.'), v2_t('≈ 5 minutes')],
    ['plus', v2_t('Add your activities'), v2_t('As many as you like, of any type. Set time slots, days, capacities, price variants and group packages.'), v2_t('unlimited activities')],
    ['arrow-right', v2_t('Go live'), v2_t('Publish and you are on the market, with pages ready to share and tracking connected. You go straight into a base of {count}+ customers.', ['count' => v2_thousands(96000)]), v2_t('go live in 1 day at most')],
    ['coins', v2_t('Sell & get paid'), v2_t('Online and on site, in the same system. viaqui.com collects from the customer and pays you out at regular intervals or on request.'), v2_t('your price, in full')],
];
$dpTixello = [[v2_t('Events & activities'), v2_thousands(4294)], [v2_t('Customers in the base'), v2_thousands(96341)], [v2_t('Tickets sold'), v2_thousands(301310)], [v2_t('Sales generated'), '€' . v2_thousands(4409557)], [v2_t('Offline scanning'), v2_t('Yes, with sync')]];
$dpFaqs = [
    [v2_t('How much is the commission?'), v2_t('The commission is 2%*, and it is not taken out of your price: it is added on top, in the final price. You set your price and receive it in full at payout. *The 2% applies to exclusive sales through viaqui.com.')],
    [v2_t('How and when do I get my money?'), v2_t('viaqui.com collects the payment from the customer and pays you out at regular intervals, or on request, whenever you want your money settled.')],
    [v2_t('Can I sell activities with slots and by day?'), v2_t('Yes. The customer picks the day from the calendar, the time slot, the number of participants and the options. You control the capacity of each slot and take detailed bookings.')],
    [v2_t('Which payment methods are accepted?'), v2_t('Bank card (Visa, Mastercard, Maestro), Apple Pay and Google Pay, processed securely through Stripe, plus culture cards (Edenred, Sodexo, Up România) where accepted.')],
    [v2_t('How does it lower my ad costs?'), v2_t('The platform integrates with all tracking pixels and with Facebook CAPI, sending 100% of conversion events without them being blocked by ad blockers or iOS. The result: the cost of your ads on Facebook, Instagram, TikTok and Google drops by up to 60%.')],
    [v2_t('Can I sell on site too?'), v2_t('Yes. Besides the online dashboard, you have a panel for on-site sales: you sell and issue tickets at the till, for admission, extra services and rentals.')],
    [v2_t('Does it help with the tax side?'), v2_t('Yes. Automatic generation of ANAF documents, tax invoices issued to customers and integration with accounting systems in Romania.')],
    [v2_t('How long does it take to start?'), v2_t('Onboarding takes about 5 minutes, with no start-up costs. You go live within one day at most, depending on how many activities you add.')],
];

$dpCategories = $V2NAV['categories'] ?? [];
$dpMarquee = array_values(array_filter(array_map(fn ($c) => (string) ($c['name'] ?? ''), $dpCategories)));
if (count($dpMarquee) > 0 && count($dpMarquee) < 6) {
    $dpMarquee = array_merge($dpMarquee, $dpMarquee);
}

$pageTitleRaw = v2_t('Sell tickets to activities on {site}: 2%* commission that doesn\'t touch your price', ['site' => SITE_NAME]);
$pageDescription = v2_t('The ticketing platform for activities: booking with slots and a calendar, advanced analytics, 100% tracking with Facebook CAPI, regular payouts, a mobile app with offline scanning. 2%* commission that doesn\'t touch your price. Built on Tixello.');
$canonicalUrl = SITE_URL . '/devino-partener';
$noindex = true; // /partners is the page to find; this one stays for old links and personalised campaigns
$structuredData = [
    [
        '@context' => 'https://schema.org',
        '@type' => 'Service',
        'name' => v2_t('viaqui.com: ticketing & booking for activities'),
        'description' => v2_t('A ticketing platform for venues and activity organisers. 2% commission that doesn\'t touch your price. Booking with time slots, advanced analytics, offline scanning, regular payouts.'),
        'provider' => ['@type' => 'Organization', 'name' => SITE_NAME, 'url' => SITE_URL],
        'areaServed' => ['@type' => 'Place', 'name' => 'Europe'],
        'offers' => ['@type' => 'Offer', 'priceCurrency' => SITE_CURRENCY, 'price' => '0', 'description' => v2_t('{amount} start-up cost. 2%* commission on every ticket sold, without touching your price.', ['amount' => v2_money(0)])],
    ],
    [
        '@context' => 'https://schema.org',
        '@type' => 'FAQPage',
        'mainEntity' => array_map(fn ($f) => ['@type' => 'Question', 'name' => $f[0], 'acceptedAnswer' => ['@type' => 'Answer', 'text' => $f[1]]], $dpFaqs),
    ],
];

$v2Styles = ['partner.css'];
$v2Scripts = ['partner.js'];
$v2HeaderOverlay = true;
$v2ClientData = ['profiles' => $dpProfiles, 'aliases' => $dpAliases, 'demoName' => 'Andrei Popescu', 'demoGift' => v2_t('Happy birthday! Have a great time!')];
// head.php strips utm_* from the address bar 15 s after load; the funnel pings read them from here
$v2HeadExtra = '<script>(function(){try{var q=new URLSearchParams(location.search),u={};["utm_source","utm_medium","utm_campaign","utm_content","utm_term"].forEach(function(k){if(q.get(k))u[k]=q.get(k).slice(0,150)});window.BO_UTM=u;}catch(e){}})();</script>';

include __DIR__ . '/includes/v2/head.php';
include __DIR__ . '/includes/v2/header.php';
?>
<main id="main" tabindex="-1">
  <!-- HERO -->
  <section class="dp-hero" aria-labelledby="dp-h">
    <svg class="deco-arches" viewBox="0 0 400 400" aria-hidden="true" focusable="false"><path d="M40 400V200a160 160 0 0 1 320 0v200"/><path d="M90 400V200a110 110 0 0 1 220 0v200"/><path d="M140 400V200a60 60 0 0 1 120 0v200"/></svg>
    <div class="dp-in">
      <div class="dp-copy">
        <p class="dp-hello" id="dp-hello" hidden></p>
        <p class="dp-chip"><span class="dp-dot" aria-hidden="true"></span><span id="dp-chip-t"><?= v2_te('Ticketing & booking for activities') ?></span></p>
        <h1 class="dp-h" id="dp-h"><span id="dp-h1a"><?= v2_te('Sell tickets') ?></span> <span id="dp-h1b" class="is-soft"><?= v2_te('to your activities.') ?></span> <span class="dp-h-line"><span id="dp-h1c" class="dp-mark"><?= v2_te('Your price stays yours.') ?></span></span></h1>
        <p class="dp-lead" id="dp-sub"><?= v2_t('Booking by time slot and calendar, advanced analytics, 100% tracking that lowers your ad costs, and a mobile app with offline scanning. The commission of {accent} doesn\'t touch your price: you keep the price you set.', ['accent' => $dpAccent]) ?></p>
        <div class="dp-cta">
          <a class="btn btn-light" href="/list-your-venue" data-signup data-track-cta="hero_primary"><span id="dp-cta-t"><?= v2_te('Put your activities on sale') ?></span><?= v2_ic('arrow-right') ?></a>
          <a class="btn btn-outline-light" href="#cum" data-track-cta="hero_secondary"><?= v2_te('See how it works') ?></a>
        </div>
        <ul class="dp-ticks">
          <li><?= v2_ic('check') ?><?= v2_te('No start-up costs') ?></li>
          <li><?= v2_ic('check') ?><?= v2_te('Unlimited activities') ?></li>
          <li><?= v2_ic('check') ?><?= v2_te('Onboarding in 5 minutes') ?></li>
        </ul>
      </div>
      <div class="dp-ticket-col">
        <!-- the header turns solid when the white ticket reaches it -->
        <div id="hdr-sentinel" aria-hidden="true"></div>
        <div class="dp-ticket" aria-hidden="true">
          <span class="dp-stamp"><?= v2_te('Booked') ?></span>
          <div class="dp-ticket-top"><b><?= v2_te('Booking') ?></b><span>No. 00 962</span></div>
          <div class="dp-ticket-mid">
            <small><?= v2_te('Your activity') ?></small>
            <p><?= v2_t('Time slot.<br>Day on the calendar.') ?></p>
            <div class="dp-ticket-stats">
              <div><b class="is-accent">2%*</b><span><?= v2_te('without touching your price') ?></span></div>
              <div><b>−60%</b><span><?= v2_te('ad cost') ?></span></div>
              <div><b class="is-yellow">∞</b><span><?= v2_te('activities') ?></span></div>
            </div>
          </div>
          <div class="dp-ticket-bot">
            <div><p><?= v2_te('Powered by Tixello') ?></p><p><?= v2_te('{count} tickets sold', ['count' => v2_thousands(301310)]) ?></p></div>
            <div class="dp-bars"><?php foreach ([100, 75, 100, 90, 65, 100, 100, 80, 90, 100, 65] as $barHeight): ?><i style="height:<?= $barHeight ?>%"></i><?php endforeach; ?></div>
          </div>
        </div>
      </div>
    </div>
  </section>

  <!-- MARQUEE: live categories (the grid further down lists them for everyone) -->
  <?php if ($dpMarquee): ?>
  <div class="dp-marquee" aria-hidden="true">
    <div class="dp-marquee-track">
      <?php for ($pass = 0; $pass < 2; $pass++): ?>
      <div class="dp-marquee-set"><?php foreach ($dpMarquee as $marqueeName): ?><span><?= v2_e($marqueeName) ?></span><?php endforeach; ?></div>
      <?php endfor; ?>
    </div>
  </div>
  <?php endif; ?>

  <!-- STATS -->
  <section class="dp-stats" aria-labelledby="dp-stats-h">
    <div class="wrap">
      <p class="dp-stats-cap" id="dp-stats-h"><?= v2_te('Real results in the Tixello ecosystem, which viaqui.com is built on') ?></p>
      <div class="dp-stats-grid">
        <?php foreach ($dpStats as [$statValue, $statLabel, $statPrefix]): ?>
        <div><p class="dp-stat-v"><?= v2_e($statPrefix) ?><span data-count="<?= $statValue ?>"><?= v2_thousands($statValue) ?></span></p><p class="dp-stat-l"><?= v2_e($statLabel) ?></p></div>
        <?php endforeach; ?>
      </div>
    </div>
  </section>

  <!-- PROBLEM -->
  <section class="sec dp-problem" aria-labelledby="dp-problem-h">
    <div class="wrap">
      <div class="dp-head">
        <p class="kicker"><?= v2_te('Today\'s reality') ?></p>
        <h2 id="dp-problem-h"><?= v2_te('You sell activities, but your tools hold you back.') ?></h2>
        <p><?= v2_te('High commissions taken out of your margin. Rigid booking that cannot handle slots or days. Tracking cut short by ad blockers and iOS, which inflates your ad costs. And no real help in finding new customers.') ?></p>
      </div>
      <div class="dp-problem-grid" data-reveal>
        <?php foreach ($dpProblems as [$probIcon, $probTitle, $probText]): ?>
        <article class="dp-card"><span class="dp-ic is-red"><?= v2_ic($probIcon) ?></span><h3><?= v2_e($probTitle) ?></h3><p><?= v2_e($probText) ?></p></article>
        <?php endforeach; ?>
      </div>
      <div class="dp-center"><a class="btn btn-primary" href="/list-your-venue" data-signup data-track-cta="problem_solve"><?= v2_te('Solve them all with viaqui.com') ?><?= v2_ic('arrow-right') ?></a></div>
    </div>
  </section>

  <!-- BOOKING -->
  <section class="dp-booking" id="booking" aria-labelledby="dp-booking-h">
    <div class="wrap dp-two">
      <div>
        <p class="dp-dark-k"><?= v2_te('Booking designed for activities') ?></p>
        <h2 id="dp-booking-h"><?= v2_te('Time slots. Days on a calendar. Detailed booking.') ?></h2>
        <p class="dp-dark-p"><?= v2_te('viaqui.com doesn\'t just sell “a ticket”. The customer picks the day from the calendar, the time slot, the number of participants and the options, exactly the way an escape room, a guided tour or a workshop works. You control the capacity of each slot.') ?></p>
        <ul class="dp-list is-dark">
          <?php foreach ($dpBookingList as $bookingItem): ?><li><?= v2_ic('check') ?><?= v2_e($bookingItem) ?></li><?php endforeach; ?>
        </ul>
        <a class="btn btn-light dp-mt" href="/list-your-venue" data-signup data-track-cta="booking_slots"><?= v2_te('I want booking by slots') ?><?= v2_ic('arrow-right') ?></a>
      </div>
      <!-- animated demo; the text beside it says the same thing, so screen readers skip it -->
      <div class="dp-demo" id="dp-demo" aria-hidden="true">
        <div class="dp-demo-head"><b id="dp-demo-title"><?= v2_te('Done!') ?></b><span id="dp-demo-count">6/6</span></div>
        <div class="dp-bar"><i id="dp-demo-progress" style="width:100%"></i></div>
        <div class="dp-demo-body">
          <div class="dp-step" data-step="0" hidden>
            <small><?= v2_te('Slots: 19 Oct') ?></small>
            <div class="dp-slots"><?php foreach (['10:00', '12:00', '14:00', '16:00', '18:00', '20:00'] as $slotIndex => $slotTime): ?><span class="dp-slot<?= $slotIndex === 1 ? ' is-gone' : '' ?>"><?= $slotTime ?></span><?php endforeach; ?></div>
          </div>
          <div class="dp-step" data-step="1" hidden>
            <?php foreach ([[v2_t('Standard admission'), v2_t('1 person'), v2_money(80)], [v2_t('Admission + experience'), v2_t('1 person'), v2_money(120)], [v2_t('Family package'), v2_t('2 adults + 2 children'), v2_money(260)]] as [$tkName, $tkNote, $tkPrice]): ?>
            <div class="dp-row dp-tk"><div><b><?= v2_e($tkName) ?></b><small><?= v2_e($tkNote) ?></small></div><span><?= v2_e($tkPrice) ?><i class="dp-radio"></i></span></div>
            <?php endforeach; ?>
          </div>
          <div class="dp-step" data-step="2" hidden>
            <small><?= v2_te('Add extras & rentals') ?></small>
            <?php foreach ([['map-pin', v2_t('Equipment (rental)'), v2_money(35)], ['star', v2_t('Photo guide'), v2_money(25)], ['gift', v2_t('Snack pack'), v2_money(18)]] as [$exIcon, $exName, $exPrice]): ?>
            <div class="dp-row dp-extra"><div class="dp-extra-name"><?= v2_ic($exIcon) ?><b><?= v2_e($exName) ?></b></div><span><?= v2_e($exPrice) ?><i class="dp-plus"></i></span></div>
            <?php endforeach; ?>
          </div>
          <div class="dp-step" data-step="3" hidden>
            <small><?= v2_te('Personalise the ticket') ?></small>
            <p class="dp-lbl"><?= v2_te('Name on the ticket') ?></p>
            <div class="dp-input"><span id="dp-typed"></span><i class="dp-caret"></i></div>
            <p class="dp-lbl"><?= v2_te('Gift message (optional)') ?></p>
            <div class="dp-textarea" id="dp-gift"></div>
            <p class="dp-okline"><?= v2_ic('check') ?><?= v2_te('Send the ticket by email & WhatsApp') ?></p>
          </div>
          <div class="dp-step" data-step="4" hidden>
            <small><?= v2_te('Order summary') ?></small>
            <div class="dp-sum"><p><span><?= v2_te('Admission + experience') ?></span><span><?= v2_money(120) ?></span></p><p><span><?= v2_te('Equipment (rental)') ?></span><span><?= v2_money(35) ?></span></p><p><span><?= v2_te('Photo guide') ?></span><span><?= v2_money(25) ?></span></p><p class="is-total"><span><?= v2_te('Estimated total') ?></span><span><?= v2_money(180) ?></span></p></div>
            <div class="dp-pay"><span class="is-on">Stripe</span><span>Apple Pay</span><span>Google Pay</span><span><?= v2_te('Culture card') ?></span></div>
            <div class="dp-bar is-pay"><i id="dp-pay-progress" style="width:100%"></i></div>
            <p class="dp-pay-t" id="dp-pay-t"><?= v2_te('Payment confirmed') ?></p>
          </div>
          <div class="dp-step is-done" data-step="5">
            <span class="dp-done-ic"><?= v2_ic('check') ?></span>
            <b><?= v2_te('Order confirmed!') ?></b>
            <p><?= v2_te('Ticket MKT-19024 · 14:00') ?></p>
            <ul class="dp-msgs">
              <?php foreach ([['envelope-simple', v2_t('The ticket was sent by email')], ['phone', v2_t('Confirmation sent on WhatsApp')], ['ticket', v2_t('Valid QR ticket: scan it at the entrance')], ['star', v2_t('Recommended for you: “Sunset photo tour”')]] as [$msgIcon, $msgText]): ?>
              <li class="dp-msg is-on"><?= v2_ic($msgIcon) ?><?= v2_e($msgText) ?></li>
              <?php endforeach; ?>
            </ul>
          </div>
        </div>
        <p class="dp-demo-foot"><?= v2_te('viaqui.com booking demo') ?></p>
      </div>
    </div>
  </section>

  <!-- BENEFITS -->
  <section class="sec dp-benefits" aria-labelledby="dp-benefits-h">
    <div class="wrap">
      <div class="dp-head">
        <p class="kicker"><?= v2_te('Everything you need') ?></p>
        <h2 id="dp-benefits-h"><?= v2_te('A complete platform for selling activities.') ?></h2>
      </div>
      <div class="dp-benefit-grid" data-reveal>
        <?php foreach ($dpBenefits as [$benIcon, $benTitle, $benText]): ?>
        <article class="dp-benefit"><span class="dp-ic is-light"><?= v2_ic($benIcon) ?></span><h3><?= v2_e($benTitle) ?></h3><p><?= v2_e($benText) ?></p></article>
        <?php endforeach; ?>
      </div>
      <div class="dp-small-grid" data-reveal>
        <?php foreach ($dpSmall as [$smIcon, $smTitle, $smText]): ?>
        <article class="dp-small"><span class="dp-ic"><?= v2_ic($smIcon) ?></span><h4><?= v2_e($smTitle) ?></h4><p><?= v2_e($smText) ?></p></article>
        <?php endforeach; ?>
      </div>
    </div>
  </section>

  <!-- ANALYTICS + TRACKING -->
  <section class="dp-analytics" id="analytics" aria-labelledby="dp-analytics-h">
    <div class="wrap">
      <div class="dp-head">
        <p class="dp-dark-k"><?= v2_te('Data that grows your sales') ?></p>
        <h2 id="dp-analytics-h"><?= v2_t('Advanced analytics + 100% tracking. <span class="dp-hl">Ads up to 60% cheaper.</span>') ?></h2>
      </div>
      <div class="dp-two is-top">
        <div>
          <h3 class="dp-sub-h"><?= v2_te('Why analytics matters') ?></h3>
          <ul class="dp-list is-dark is-arrows">
            <?php foreach ($dpAnalytics as $anItem): ?><li><?= v2_ic('arrow-right') ?><?= v2_e($anItem) ?></li><?php endforeach; ?>
          </ul>
        </div>
        <div class="dp-glass">
          <h3 class="dp-sub-h"><?= v2_te('Complete tracking, nothing lost') ?></h3>
          <p><?= v2_t('viaqui.com integrates with <strong>all tracking pixels</strong> and with <strong>Facebook CAPI</strong>. It sends <strong class="dp-yellow">100% of conversion events</strong> server-side, so ad blockers no longer stop you, and neither does the iOS update that cuts most tracking.') ?></p>
          <div class="dp-glass-stats"><div><b>100%</b><span><?= v2_te('events tracked') ?></span></div><div><b>−60%</b><span><?= v2_te('ad cost') ?></span></div></div>
          <p class="dp-glass-note"><?= v2_t('It works with ads on <strong>Facebook, Instagram, TikTok and Google</strong>. Correct data = more efficient algorithms = a lower cost per sale.') ?></p>
        </div>
      </div>
      <a class="btn btn-light dp-mt" href="/list-your-venue" data-signup data-track-cta="analytics_ads"><?= v2_te('I want cheaper ads') ?><?= v2_ic('arrow-right') ?></a>
    </div>
  </section>

  <!-- PAYMENTS -->
  <section class="sec dp-payments" aria-labelledby="dp-pay-h">
    <div class="wrap dp-two">
      <div>
        <p class="kicker"><?= v2_te('Payments for every customer') ?></p>
        <h2 class="dp-h2" id="dp-pay-h"><?= v2_te('Every payment method, within the buyer\'s reach.') ?></h2>
        <p class="dp-p"><?= v2_te('The simpler the payment, the more you sell. viaqui.com accepts the most used methods: the customer pays in two taps, with no friction.') ?></p>
        <p class="dp-p"><?= v2_t('viaqui.com collects the payment from the customer and pays you out <strong>at regular intervals</strong>, or on request, whenever you want your money settled.') ?></p>
      </div>
      <div class="dp-pay-grid">
        <?php foreach ($dpPayments as $payName): ?><div class="dp-pay-tile"><?= v2_e($payName) ?></div><?php endforeach; ?>
        <div class="dp-pay-tile is-dark"><?= v2_t('and more<br>coming soon') ?></div>
      </div>
    </div>
  </section>

  <!-- THE 2% -->
  <section class="dp-bani" id="bani" aria-labelledby="dp-bani-h">
    <div class="wrap dp-two">
      <div>
        <p class="dp-dark-k"><?= v2_te('The difference that changes everything') ?></p>
        <h2 id="dp-bani-h" class="dp-bani-h"><?= v2_t('Commission {rate}. {line}', ['rate' => '<span class="dp-hl">2%*</span>', 'line' => '<span class="dp-nl">' . v2_te('Your price stays yours.') . '</span>']) ?></h2>
        <p class="dp-dark-p"><?= v2_t('The 2%* commission is added transparently on top, in the final price. You set your price and receive it <strong class="dp-yellow">in full</strong> at payout, with nothing taken from your margin.') ?></p>
        <ul class="dp-list is-dark">
          <li><?= v2_ic('check') ?><?= v2_te('You set the price, you receive the price you set') ?></li>
          <li><?= v2_ic('check') ?><?= v2_te('The customer sees the 2%* clearly: honest, no surprises') ?></li>
          <li><?= v2_ic('check') ?><?= v2_te('Zero monthly costs, zero start-up fees') ?></li>
        </ul>
        <p class="dp-footnote"><?= v2_t('<strong>*</strong> The 2% commission applies to exclusive sales through viaqui.com. viaqui.com collects the payment from the customer and pays you out at regular intervals or on request.') ?></p>
      </div>
      <div class="dp-compare">
        <p class="dp-compare-h"><?= v2_te('A {amount} ticket: what do you receive?', ['amount' => v2_money(100)]) ?></p>
        <div class="dp-compare-row">
          <div class="dp-compare-top"><span><?= v2_te('A classic platform') ?></span><span class="is-red">−<?= v2_money(9.5) ?></span></div>
          <p class="dp-compare-note"><?= v2_te('8% commission + 1–2% card processing cost, both taken out of your money') ?></p>
          <div class="dp-bar is-thick is-red"><i style="width:90.5%"></i></div>
          <p class="dp-compare-get"><?= v2_t('You receive: {amount}', ['amount' => '<strong class="is-red">~' . v2_money(90.5) . '</strong>']) ?></p>
        </div>
        <div class="dp-compare-row is-ours">
          <div class="dp-compare-top"><span><?= v2_te('viaqui.com (2%* added on top)') ?></span><span class="is-green">100%</span></div>
          <div class="dp-bar is-thick"><i style="width:100%"></i></div>
          <p class="dp-compare-get"><?= v2_t('You receive: {amount}', ['amount' => '<strong class="is-green">' . v2_money(100) . '</strong>']) ?></p>
          <p class="dp-compare-note"><?= v2_te('The commission and the card cost are included in the final price. You receive your price, in full.') ?></p>
        </div>
        <p class="dp-compare-foot"><?= v2_te('At volume, the difference becomes huge.') ?></p>
      </div>
    </div>
  </section>

  <!-- MOBILE APP + LOCAL SALES -->
  <section class="sec dp-local" aria-labelledby="dp-local-h">
    <div class="wrap">
      <div class="dp-head">
        <p class="kicker"><?= v2_te('Online and on site') ?></p>
        <h2 id="dp-local-h"><?= v2_te('A mobile app + a till for on-site sales.') ?></h2>
      </div>
      <div class="dp-two is-cards" data-reveal>
        <article class="dp-feature">
          <span class="dp-ic"><?= v2_ic('phone') ?></span>
          <h3><?= v2_te('The mobile app: Android & iOS') ?></h3>
          <p><?= v2_t('You scan tickets fast at the entrance, <strong>offline too</strong>, with syncing later. At the same time you see <strong>sales and traffic live</strong>, wherever you are.') ?></p>
          <ul class="dp-list"><li><?= v2_ic('check') ?><?= v2_te('QR scanning with anti-fraud validation') ?></li><li><?= v2_ic('check') ?><?= v2_te('Works without a stable connection') ?></li><li><?= v2_ic('check') ?><?= v2_te('Sales and traffic in real time') ?></li></ul>
        </article>
        <article class="dp-feature is-dark">
          <span class="dp-ic is-light"><?= v2_ic('shopping-cart-simple') ?></span>
          <h3><?= v2_te('On-site sales panel') ?></h3>
          <p><?= v2_t('Besides the dashboard for online orders, you have a <strong class="dp-yellow">panel for managing on-site sales</strong>. You sell and issue tickets right at the till: admission, extra services or rentals.') ?></p>
          <ul class="dp-list is-dark"><li><?= v2_ic('check') ?><?= v2_te('Admission tickets sold at the ticket office') ?></li><li><?= v2_ic('check') ?><?= v2_te('Extra services & rentals') ?></li><li><?= v2_ic('check') ?><?= v2_te('Online + on site, in the same system') ?></li></ul>
        </article>
      </div>
      <div class="dp-center"><a class="btn btn-primary" href="/list-your-venue" data-signup data-track-cta="local_sales"><?= v2_te('I want to sell online and on site') ?><?= v2_ic('arrow-right') ?></a></div>
    </div>
  </section>

  <!-- FISCAL / ANAF -->
  <section class="dp-fiscal" aria-labelledby="dp-fiscal-h">
    <div class="wrap">
      <div class="dp-head">
        <p class="kicker"><?= v2_te('Tax, without the headaches') ?></p>
        <h2 id="dp-fiscal-h"><?= v2_te('Accounting and ANAF, handled automatically.') ?></h2>
      </div>
      <div class="dp-fiscal-grid" data-reveal>
        <?php foreach ($dpFiscal as [$fisIcon, $fisTitle, $fisText]): ?>
        <article class="dp-card"><span class="dp-ic"><?= v2_ic($fisIcon) ?></span><h3><?= v2_e($fisTitle) ?></h3><p><?= v2_e($fisText) ?></p></article>
        <?php endforeach; ?>
      </div>
      <div class="dp-anaf" id="dp-anaf">
        <p class="dp-anaf-k"><?= v2_te('One sale → documents generated automatically, in seconds') ?></p>
        <div class="dp-anaf-grid">
          <div class="dp-order">
            <p class="dp-order-top"><span><?= v2_te('New order') ?></span><i class="dp-live"></i></p>
            <b><?= v2_te('Admission ticket + rental') ?></b>
            <p class="dp-order-no">MKT-19024 · <?= v2_money(180) ?></p>
            <p class="dp-okline"><?= v2_ic('check') ?><?= v2_te('Payment confirmed') ?></p>
          </div>
          <div class="dp-docs" aria-hidden="true">
            <?php foreach ($dpDocs as [$docIcon, $docName, $docMeta]): ?>
            <div class="dp-doc is-done"><div class="dp-doc-top"><?= v2_ic($docIcon) ?><span class="dp-doc-state"><?= v2_ic('check') ?></span></div><b><?= v2_e($docName) ?></b><small><?= v2_e($docMeta) ?></small><i class="dp-doc-bar"></i></div>
            <?php endforeach; ?>
          </div>
        </div>
        <div class="dp-anaf-foot">
          <p><?= v2_t('Zero manual entry. The documents are ready within {time} of every sale.', ['time' => '<strong id="dp-elapsed">3s</strong>']) ?></p>
          <a class="btn btn-light" href="/list-your-venue" data-signup data-track-cta="anaf"><?= v2_te('I want my tax paperwork on autopilot') ?><?= v2_ic('arrow-right') ?></a>
        </div>
      </div>
    </div>
  </section>

  <!-- HOW IT WORKS -->
  <section class="sec dp-how" id="cum" aria-labelledby="dp-how-h">
    <div class="wrap">
      <div class="dp-head">
        <p class="kicker"><?= v2_te('From account to first sale') ?></p>
        <h2 id="dp-how-h"><?= v2_te('Four steps. Under a day. Zero start-up costs.') ?></h2>
      </div>
      <ol class="dp-steps" data-reveal>
        <?php foreach ($dpSteps as $stepIndex => [$stepIcon, $stepTitle, $stepText, $stepTag]): ?>
        <li class="dp-stepcard"><span class="dp-num"><?= $stepIndex + 1 ?></span><span class="dp-ic"><?= v2_ic($stepIcon) ?></span><h3><?= v2_e($stepTitle) ?></h3><p><?= v2_e($stepText) ?></p><p class="dp-tag"><?= v2_e($stepTag) ?></p></li>
        <?php endforeach; ?>
      </ol>
      <div class="dp-center"><a class="btn btn-primary" href="/list-your-venue" data-signup data-track-cta="how_it_works"><?= v2_te('Start now, for free') ?><?= v2_ic('arrow-right') ?></a></div>
    </div>
  </section>

  <!-- TIXELLO ENGINE -->
  <section class="dp-tech" id="tehnologie" aria-labelledby="dp-tech-h">
    <div class="wrap dp-two">
      <div>
        <span class="dp-badge"><?= v2_te('Powered by Tixello') ?></span>
        <h2 class="dp-h2" id="dp-tech-h"><?= v2_te('Mature infrastructure, tested at scale.') ?></h2>
        <p class="dp-p"><?= v2_te('viaqui.com runs on Tixello, the ticketing system that has already processed over €{millions} million in sales and over {tickets} tickets. You get production-grade technology, without building or maintaining it.', ['millions' => '4.4', 'tickets' => v2_thousands(301000)]) ?></p>
        <a class="btn btn-primary dp-mt" href="/list-your-venue" data-signup data-track-cta="tixello"><?= v2_te('Become a partner') ?><?= v2_ic('arrow-right') ?></a>
      </div>
      <div class="dp-numbers">
        <p class="dp-numbers-k"><?= v2_te('In numbers') ?></p>
        <dl>
          <?php foreach ($dpTixello as [$numLabel, $numValue]): ?><div><dt><?= v2_e($numLabel) ?></dt><dd><?= v2_e($numValue) ?></dd></div><?php endforeach; ?>
        </dl>
      </div>
    </div>
  </section>

  <!-- CATEGORIES -->
  <section class="sec dp-cats" aria-labelledby="dp-cats-h">
    <div class="wrap">
      <div class="dp-head is-center">
        <p class="kicker"><?= v2_te('For which type of activity') ?></p>
        <h2 id="dp-cats-h"><?= v2_te('Categories available on viaqui.com') ?></h2>
      </div>
      <?php if (!$dpCategories): ?>
      <p class="dp-empty"><?= v2_te('The categories will load soon. Reload the page in a few minutes.') ?></p>
      <?php else: ?>
      <div class="dp-cat-grid" id="grid-categorii" data-reveal>
        <?php foreach ($dpCategories as $cat): if (empty($cat['name'])) { continue; } ?>
        <a class="dp-cat" href="<?= v2_e($cat['href'] ?? '/' . ($cat['slug'] ?? '')) ?>" data-cat-slug="<?= v2_e($cat['slug'] ?? '') ?>">
          <?php if (!empty($cat['image'])): ?>
          <img src="<?= v2_e($cat['image']) ?>"<?= !empty($cat['srcset']) ? ' srcset="' . v2_e($cat['srcset']) . '" sizes="(min-width:1024px) 33vw, (min-width:640px) 50vw, 100vw"' : '' ?> alt="" width="640" height="400" loading="lazy" decoding="async">
          <?php else: ?>
          <span class="dp-cat-ph"><?= v2_ic('ticket') ?></span>
          <?php endif; ?>
          <span class="dp-cat-body"><b><?= v2_e($cat['name']) ?></b><?php if (!empty($cat['desc'])): ?><small><?= v2_e($cat['desc']) ?></small><?php endif; ?></span>
        </a>
        <?php endforeach; ?>
      </div>
      <?php endif; ?>
    </div>
  </section>

  <!-- FAQ -->
  <section class="sec dp-faq" aria-labelledby="dp-faq-h">
    <div class="wrap dp-faq-grid">
      <div>
        <p class="kicker"><?= v2_te('Frequently asked questions') ?></p>
        <h2 id="dp-faq-h"><?= v2_te('What you want to know before you start') ?></h2>
      </div>
      <div>
        <?php foreach ($dpFaqs as $fi => [$faqQ, $faqA]): ?>
        <details class="qa"<?= $fi === 0 ? ' open' : '' ?>><summary><?= v2_e($faqQ) ?><span class="pm"><?= v2_ic('plus') ?></span></summary><p><?= v2_e($faqA) ?></p></details>
        <?php endforeach; ?>
      </div>
    </div>
  </section>

  <!-- FINAL CTA -->
  <section class="sec dp-final-sec" id="contact">
    <div class="wrap">
      <div class="dp-final">
        <span class="dp-final-badge"><?= v2_te('Become a partner') ?></span>
        <h2><?= v2_t('Put your activities on sale <span class="dp-nl">and keep your price whole.</span>') ?></h2>
        <p><?= v2_te('No start-up costs. Unlimited activities. Onboarding in 5 minutes, go live today. 2%* commission, without touching your price.') ?></p>
        <div class="dp-final-cta">
          <a class="btn dp-btn-white" href="/list-your-venue" data-signup data-track-cta="final_primary"><?= v2_te('I want to sell my activities') ?><?= v2_ic('arrow-right') ?></a>
          <a class="btn btn-outline-light" href="mailto:contact@viaqui.com?subject=<?= rawurlencode(v2_t('Partnership question for viaqui.com')) ?>" data-track-cta="email_contact"><?= v2_te('Send us an email') ?></a>
        </div>
        <p class="dp-final-note"><?= v2_te('No start-up cost · Unlimited activities · Cancel anytime') ?></p>
      </div>
    </div>
  </section>
</main>
<?php include __DIR__ . '/includes/v2/footer.php'; ?>
