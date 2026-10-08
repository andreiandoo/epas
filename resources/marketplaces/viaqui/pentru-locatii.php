<?php
/**
 * For venues: /pentru-locatii (v2 design). Static sales page for venues and activity operators.
 *
 * Top to bottom: hero with a dashboard mockup, who it's for, what they get (#ce-primesti), the SEO engine, the flow
 * from listing to check-in, modules in tabs (base.js [data-tabs]), the commercial model, the demo request (#demo), FAQ,
 * final CTA. JSON-LD: Service, FAQPage (from the visible questions).
 *
 * The demo form posted to /api/contact-locatii.php, which doesn't exist (404): every demo request was lost.
 * for-venues.js now sends it into the real lead pipeline (proxy leads.create → core LeadsController::create, the same
 * one /list-your-venue uses), which requires the city. /pricing-locatii was a 404 too; the pricing links go to the
 * commission section of /devino-partener. Payment methods follow checkout; the SEO URL examples follow real routes.
 */

require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/api.php';

// 30-minute page cache: static content, the form posts through the proxy.
$pageCacheTTL = 1800;
require_once __DIR__ . '/includes/page-cache.php';

require_once __DIR__ . '/includes/nav-helpers.php';
require_once __DIR__ . '/includes/v2/helpers.php';
require_once __DIR__ . '/includes/v2/nav.php';

$fvWho = [
    [v2_t('Escape rooms'), v2_t('Time slots, capacity per room, different ticket holders, group tickets and fast check-in.'), ''],
    [v2_t('Museums & exhibitions'), v2_t('Admission tickets, temporary exhibitions, guided tours, child/adult prices, free entries and opening hours.'), 'is-mint'],
    [v2_t('Parks & leisure'), v2_t('Packages, age groups, day access, extras and capacity.'), ''],
    [v2_t('Caves & nature reserves'), v2_t('Tours, access rules, difficulty level, equipment, guide and seasons.'), ''],
    [v2_t('Workshops & education'), v2_t('Limited places, recommended ages, materials included, school groups.'), 'is-deep'],
    [v2_t('Tours & experiences'), v2_t('City walks, food tours, history tours, sightseeing and private experiences.'), ''],
];
$fvGet = [
    [v2_t('01 · SEO'), v2_t('Pages that can bring organic traffic.'), v2_t('A venue page, pages for activities, categories, cities and intents such as “things to do with kids”, “weekend”, “indoor”, “under {amount}”.', ['amount' => v2_money(50)]), ''],
    [v2_t('02 · Checkout'), v2_t('Fast, clear, modern buying.'), v2_t('Card (including Apple Pay and Google Pay), culture card where accepted, different ticket holders, automatic account, fees shown separately and commercial options.'), 'is-deep'],
    [v2_t('03 · QR'), v2_t('Digital tickets and fast check-in.'), v2_t('Every ticket has a unique code, a status and a holder, and can be scanned at the entrance for clear access control.'), ''],
    [v2_t('04 · Dashboard'), v2_t('Orders, customers, scans and reports.'), v2_t('You see sales, tickets issued, participants, availability, statuses and how your activities perform.'), ''],
    [v2_t('05 · Growth'), v2_t('Promotions, vouchers, gift cards.'), v2_t('You can run promo codes, seasonal campaigns, gift cards, bonus points and offers for specific audiences.'), ''],
    [v2_t('06 · Trust'), v2_t('A better experience for customers.'), v2_t('The customer sees clearly what they are buying, where they are going, how they get in, what the ticket includes and what happens after payment.'), 'is-mint'],
];
$fvSeoUrls = [
    ['/lisbon/with-kids', v2_t('city + intent')],
    ['/escape-rooms', v2_t('category')],
    ['/venue/mystery-rooms', v2_t('venue page')],
    ['/activity/room-13', v2_t('activity page')],
];
$fvAnatomy = [
    [v2_t('Clear title + description'), v2_t('What it is, where it is, who it is for.')],
    [v2_t('Structured data'), v2_t('Breadcrumbs, FAQ, local entity, activity.')],
    [v2_t('Practical questions'), v2_t('Opening hours, access, age, duration, rules, parking.')],
    [v2_t('Internal linking'), v2_t('Cities, categories, similar activities, guides.')],
];
$fvOps = [
    [v2_t('Onboarding'), v2_t('Venue details, activities, tickets, policies.')],
    [v2_t('Publishing'), v2_t('SEO pages and activities available online.')],
    [v2_t('Selling'), v2_t('Checkout, payments, commissions, QR tickets.')],
    [v2_t('Scanning'), v2_t('Fast validation at the entrance, clear statuses.')],
    [v2_t('Growing'), v2_t('Reports, reviews, promotions, campaigns.')],
];
$fvTiers = [
    [v2_t('Start'), v2_t('Listing'), v2_t('Pages, checkout, QR tickets.'), ''],
    [v2_t('Growth'), v2_t('Promotion'), v2_t('SEO, campaigns, visibility.'), 'is-deep'],
    [v2_t('Pro'), v2_t('Operations'), v2_t('Reports, staff, integrations.'), ''],
];
// venue type → core category slug ('' = none fits: sent as category_other)
$fvVenueTypes = [
    'escape-rooms' => v2_t('Escape room'),
    'muzee-expozitii' => v2_t('Museum / exhibition'),
    'parcuri-de-distractii' => v2_t('Amusement park'),
    'parcuri-de-aventura' => v2_t('Adventure park'),
    'natura-outdoor' => v2_t('Cave / nature reserve'),
    'ateliere-experiente-creative' => v2_t('Workshop / education'),
    'tururi-experiente-turistice' => v2_t('Tours / experiences'),
    'other' => v2_t('Something else'),
];
$fvRoles = [v2_t('Owner / Administrator'), v2_t('Marketing'), v2_t('Operations'), v2_t('Another role')];
$fvCounts = [v2_t('1 activity'), v2_t('2-5 activities'), v2_t('6-15 activities'), v2_t('15+ activities')];
$fvFaqs = [
    [v2_t('What kinds of venues can use the platform?'), v2_t('The platform suits escape rooms, museums, exhibitions, amusement parks, adventure parks, caves, nature reserves, workshops, guided tours, educational farms and other experiences that sell tickets or bookings.')],
    [v2_t('Can I have several activities at the same venue?'), v2_t('Yes. A venue can have a main page and several pages for activities, rooms, tours, packages or access types.')],
    [v2_t('How are tickets validated?'), v2_t('Every ticket is issued with a unique QR code. At the entrance, the venue staff scan it from the check-in interface, and the system shows the status of the ticket.')],
    [v2_t('Does it help with SEO?'), v2_t('Yes. The platform is designed for indexable pages: venue, activities, cities, categories and intent pages such as activities for kids, weekend, indoor or outdoor.')],
    [v2_t('Can I create promotions or discount codes?'), v2_t('Yes, the platform can include promo codes, seasonal campaigns, vouchers, bonus points and gift cards, depending on the setup.')],
    [v2_t('What happens after I receive an order?'), v2_t('The order appears in the dashboard, the tickets are issued automatically, the customer receives the confirmation, and you can see the participants and validate the tickets at the entrance.')],
];

$pageTitleRaw = v2_t('For venues: sell tickets online for your activities on viaqui.com');
$pageDescription = v2_t('List your venue on viaqui.com and sell tickets online for escape rooms, museums, parks, workshops, caves, nature reserves and local experiences. SEO pages, checkout, QR, scanning, reports and dashboard.');
$canonicalUrl = SITE_URL . '/pentru-locatii';
$noindex = true; // /partners is the page to find; this one stays for old links
$structuredData = [
    [
        '@context' => 'https://schema.org',
        '@type' => 'Service',
        'name' => v2_t('viaqui.com for venues'),
        'serviceType' => v2_t('Platform for selling tickets online for activities and venues'),
        'provider' => ['@type' => 'Organization', 'name' => 'viaqui.com', 'url' => SITE_URL . '/'],
        'areaServed' => ['@type' => 'Place', 'name' => 'Europe'],
        'description' => v2_t('A platform for venues that sell tickets online to their activities: SEO pages, checkout, QR tickets, scanning, dashboard, reports, gift cards and bonus points.'),
        'audience' => ['@type' => 'BusinessAudience', 'audienceType' => v2_t('Leisure venues, museums, escape rooms, parks, workshops, tour and experience operators')],
    ],
    [
        '@context' => 'https://schema.org',
        '@type' => 'FAQPage',
        'mainEntity' => array_map(fn ($f) => ['@type' => 'Question', 'name' => $f[0], 'acceptedAnswer' => ['@type' => 'Answer', 'text' => $f[1]]], $fvFaqs),
    ],
];

$v2Styles = ['for-venues.css'];
$v2Scripts = ['for-venues.js'];
$v2HeaderOverlay = true;
$v2ClientData = ['supportEmail' => SUPPORT_EMAIL];
$v2HeadExtra = '<meta name="keywords" content="' . v2_te('selling tickets for activities, ticket platform for venues, online tickets escape room, online tickets museum, QR tickets for activities, ticketing system for venues, booking platform for activities') . '">'
    // head.php strips utm_* from the address bar a moment later; keep them for the demo lead (read at runtime, so the
    // page cache, which ignores utm_* in its key, never bakes one visitor's campaign into the HTML)
    . '<script>(function(){try{var q=new URLSearchParams(location.search),u={};["utm_source","utm_medium","utm_campaign","utm_content","utm_term"].forEach(function(k){if(q.get(k))u[k]=q.get(k).slice(0,150)});window.BO_UTM=u;}catch(e){}})();</script>';

include __DIR__ . '/includes/v2/head.php';
include __DIR__ . '/includes/v2/header.php';
?>
<main id="main" tabindex="-1">
  <!-- HERO -->
  <section class="fv-hero" aria-labelledby="fv-h">
    <svg class="deco-arches" viewBox="0 0 400 400" aria-hidden="true" focusable="false"><path d="M40 400V200a160 160 0 0 1 320 0v200"/><path d="M90 400V200a110 110 0 0 1 220 0v200"/><path d="M140 400V200a60 60 0 0 1 120 0v200"/></svg>
    <div class="fv-in">
      <div class="fv-copy">
        <p class="fv-kicker"><?= v2_te('Platform for venues · SEO · checkout · QR') ?></p>
        <h1 class="fv-h" id="fv-h"><?= v2_te('Turn your activities into tickets that sell online.') ?></h1>
        <p class="fv-lead"><?= v2_te('viaqui.com helps venues get discovered organically, sell tickets fast and manage access with QR, without building a ticketing platform from scratch.') ?></p>
        <div class="fv-cta">
          <a class="btn btn-light" href="#demo"><?= v2_te('Request a demo') ?><?= v2_ic('arrow-right') ?></a>
          <a class="btn btn-outline-light" href="#ce-primesti"><?= v2_te('See what you get') ?></a>
        </div>
        <dl class="fv-stats">
          <div><dt><?= v2_te('Pages') ?></dt><dd><?= v2_te('SEO') ?></dd></div>
          <div><dt><?= v2_te('Access') ?></dt><dd><?= v2_te('QR') ?></dd></div>
          <div><dt><?= v2_te('Data') ?></dt><dd><?= v2_te('live') ?></dd></div>
        </dl>
      </div>

      <div class="fv-mock-col">
        <!-- the header turns solid when the white dashboard reaches it -->
        <div id="hdr-sentinel" aria-hidden="true"></div>
        <div class="fv-mock" aria-hidden="true">
          <div class="fv-dash">
            <div class="fv-dash-top">
              <div><small><?= v2_te('Organizer dashboard') ?></small><b>Mystery Rooms Lisbon</b></div>
              <span class="fv-live"><?= v2_te('Live') ?></span>
            </div>
            <div class="fv-dash-stats">
              <div><small><?= v2_te('Sales') ?></small><b>18.4k</b></div>
              <div class="is-mint"><small><?= v2_te('Tickets') ?></small><b>214</b></div>
              <div><small><?= v2_te('Scanned') ?></small><b>38</b></div>
            </div>
            <div class="fv-dash-rows">
              <div><p><span><?= v2_te('Room 13') ?></span><span><?= v2_money(9200) ?></span></p><i><em class="is-pulse"></em></i></div>
              <div><p><span><?= v2_te('Lab 7') ?></span><span><?= v2_money(6140) ?></span></p><i><em style="width:54%"></em></i></div>
            </div>
          </div>
          <div class="fv-float is-checkin">
            <small><?= v2_te('Check-in') ?></small>
            <div class="fv-qr"><span><?= v2_t('QR<br>valid') ?></span><em class="fv-scan"></em></div>
          </div>
          <div class="fv-float is-seo">
            <small><?= v2_te('Local SEO') ?></small>
            <b>/lisbon/with-kids</b>
            <span><?= v2_te('Pages for city, category, venue and activities.') ?></span>
          </div>
        </div>
      </div>
    </div>
  </section>

  <!-- WHO -->
  <section class="sec fv-who" aria-labelledby="fv-who-h">
    <div class="wrap fv-split">
      <div class="fv-intro">
        <p class="kicker"><?= v2_te('Who it\'s for') ?></p>
        <h2 id="fv-who-h"><?= v2_te('You don\'t just sell tickets. You sell an experience that has to be discovered.') ?></h2>
        <p><?= v2_te('The platform is built for different activities, with different access models: time slots, simple tickets, packages, guided tours, day access, groups or private events.') ?></p>
      </div>
      <div class="fv-who-grid">
        <?php foreach ($fvWho as [$whoTitle, $whoText, $whoTone]): ?>
        <article class="fv-card <?= $whoTone ?>"><h3><?= v2_e($whoTitle) ?></h3><p><?= v2_e($whoText) ?></p></article>
        <?php endforeach; ?>
      </div>
    </div>
  </section>

  <!-- WHAT YOU GET -->
  <section class="fv-band" id="ce-primesti" aria-labelledby="fv-get-h">
    <div class="wrap">
      <div class="fv-head">
        <p class="kicker"><?= v2_te('What you get') ?></p>
        <h2 id="fv-get-h"><?= v2_te('A complete stack for selling your activities.') ?></h2>
        <p><?= v2_te('viaqui.com combines optimised public pages, a buying flow, ticket issuing, operations at the entrance and growth tools.') ?></p>
      </div>
      <div class="fv-get-grid">
        <?php foreach ($fvGet as [$getK, $getTitle, $getText, $getTone]): ?>
        <article class="fv-get <?= $getTone ?>"><p class="fv-get-k"><?= v2_e($getK) ?></p><h3><?= v2_e($getTitle) ?></h3><p><?= v2_e($getText) ?></p></article>
        <?php endforeach; ?>
      </div>
    </div>
  </section>

  <!-- SEO ENGINE -->
  <section class="sec fv-seo" aria-labelledby="fv-seo-h">
    <div class="wrap fv-seo-grid">
      <div>
        <p class="kicker"><?= v2_te('SEO engine') ?></p>
        <h2 id="fv-seo-h"><?= v2_te('You don\'t depend on ads alone.') ?></h2>
        <p class="fv-p"><?= v2_te('Every activity can become an optimised sales page. Your venue can appear on city, category and intent pages, not just in a generic list.') ?></p>
        <div class="fv-urls">
          <?php foreach ($fvSeoUrls as [$urlPath, $urlLabel]): ?>
          <div><b><?= v2_e($urlPath) ?></b><span><?= v2_e($urlLabel) ?></span></div>
          <?php endforeach; ?>
        </div>
      </div>
      <div class="fv-anatomy">
        <div class="fv-anatomy-head"><p><?= v2_te('Anatomy of an SEO page') ?></p><h3><?= v2_te('Your activity becomes findable.') ?></h3></div>
        <div class="fv-anatomy-list">
          <?php foreach ($fvAnatomy as [$anaTitle, $anaText]): ?>
          <div><b><?= v2_e($anaTitle) ?></b><span><?= v2_e($anaText) ?></span></div>
          <?php endforeach; ?>
        </div>
      </div>
    </div>
  </section>

  <!-- OPS FLOW -->
  <section class="fv-ops" aria-labelledby="fv-ops-h">
    <div class="wrap">
      <div class="fv-head">
        <p class="fv-dark-k"><?= v2_te('Operations') ?></p>
        <h2 id="fv-ops-h"><?= v2_te('From listing to check-in.') ?></h2>
        <p><?= v2_te('The flow is built for small teams: you publish the activity, sell tickets, scan at the entrance and follow the results.') ?></p>
      </div>
      <ol class="fv-ops-grid">
        <?php foreach ($fvOps as $oi => [$opTitle, $opText]): ?>
        <li><span class="fv-ops-n"><?= $oi + 1 ?></span><h3><?= v2_e($opTitle) ?></h3><p><?= v2_e($opText) ?></p></li>
        <?php endforeach; ?>
      </ol>
    </div>
  </section>

  <!-- MODULES (tabs) -->
  <section class="sec fv-modules" aria-labelledby="fv-mod-h">
    <div class="wrap fv-split is-wide">
      <div class="fv-intro">
        <p class="kicker"><?= v2_te('Modules') ?></p>
        <h2 id="fv-mod-h"><?= v2_te('Pick what you need. The platform can grow with you.') ?></h2>
      </div>
      <div>
        <div class="fv-tabs" role="tablist" aria-label="<?= v2_te('Modules') ?>" data-tabs>
          <button type="button" role="tab" id="fv-tab-tickets" aria-controls="fv-panel-tickets" aria-selected="true"><?= v2_te('Tickets') ?></button>
          <button type="button" role="tab" id="fv-tab-calendar" aria-controls="fv-panel-calendar" aria-selected="false" tabindex="-1"><?= v2_te('Availability') ?></button>
          <button type="button" role="tab" id="fv-tab-growth" aria-controls="fv-panel-growth" aria-selected="false" tabindex="-1"><?= v2_te('Growth') ?></button>
          <button type="button" role="tab" id="fv-tab-reports" aria-controls="fv-panel-reports" aria-selected="false" tabindex="-1"><?= v2_te('Reports') ?></button>
        </div>
        <div class="fv-panel" role="tabpanel" id="fv-panel-tickets" aria-labelledby="fv-tab-tickets">
          <h3><?= v2_te('Ticket types and packages') ?></h3>
          <p><?= v2_te('Create simple tickets, child/adult tickets, group packages, tickets with a time slot, extras or tickets for tours.') ?></p>
          <div class="fv-panel-grid"><div><?= v2_te('Adult · {price}', ['price' => v2_money(95)]) ?></div><div><?= v2_te('Child · {price}', ['price' => v2_money(45)]) ?></div><div><?= v2_te('Group · {price}', ['price' => v2_money(340)]) ?></div></div>
        </div>
        <div class="fv-panel" role="tabpanel" id="fv-panel-calendar" aria-labelledby="fv-tab-calendar" hidden>
          <h3><?= v2_te('Availability and slots') ?></h3>
          <p><?= v2_te('Control days, hours, capacity, closures, exceptions, seasons and time slots with limited availability.') ?></p>
          <div class="fv-days"><?php for ($day = 1; $day <= 14; $day++): ?><span<?= $day % 4 === 0 ? ' class="is-busy"' : '' ?>><?= $day ?></span><?php endfor; ?></div>
        </div>
        <div class="fv-panel" role="tabpanel" id="fv-panel-growth" aria-labelledby="fv-tab-growth" hidden>
          <h3><?= v2_te('Promotions, gift cards, points') ?></h3>
          <p><?= v2_te('Run promo codes, seasonal campaigns, benefits through bonus points and eligibility for gift cards or vouchers.') ?></p>
          <div class="fv-chips"><span class="is-on">WEEKEND10</span><span class="is-mint"><?= v2_te('Double points') ?></span><span><?= v2_te('Gift card') ?></span></div>
        </div>
        <div class="fv-panel" role="tabpanel" id="fv-panel-reports" aria-labelledby="fv-tab-reports" hidden>
          <h3><?= v2_te('Reports and useful data') ?></h3>
          <p><?= v2_te('See what sells, when, to whom, which activities perform and which time slots convert better.') ?></p>
          <div class="fv-panel-grid is-stats"><div><small><?= v2_te('Sales') ?></small><b>18.4k</b></div><div><small><?= v2_te('Orders') ?></small><b>96</b></div><div><small><?= v2_te('Conversion') ?></small><b>4.2%</b></div></div>
        </div>
      </div>
    </div>
  </section>

  <!-- PRICING TEASER -->
  <section class="fv-band" aria-labelledby="fv-price-h">
    <div class="wrap fv-price-grid">
      <div>
        <p class="kicker"><?= v2_te('Commercial model') ?></p>
        <h2 id="fv-price-h"><?= v2_te('Clear costs, with no infrastructure built from scratch.') ?></h2>
        <p class="fv-p"><?= v2_te('The model can include a commission per ticket, optional services or promotion packages. The idea is simple: you pay for infrastructure that sells, not for vague promises.') ?></p>
        <a class="btn btn-primary fv-price-cta" href="/partners#bani"><?= v2_te('See pricing for venues') ?><?= v2_ic('arrow-right') ?></a>
      </div>
      <div class="fv-tiers">
        <?php foreach ($fvTiers as [$tierK, $tierTitle, $tierText, $tierTone]): ?>
        <article class="fv-tier <?= $tierTone ?>"><p class="fv-get-k"><?= v2_e($tierK) ?></p><h3><?= v2_e($tierTitle) ?></h3><p><?= v2_e($tierText) ?></p></article>
        <?php endforeach; ?>
      </div>
    </div>
  </section>

  <!-- DEMO FORM -->
  <section class="sec fv-demo" id="demo" aria-labelledby="fv-demo-h">
    <div class="wrap fv-split">
      <div class="fv-intro">
        <p class="kicker"><?= v2_te('Request a demo') ?></p>
        <h2 id="fv-demo-h"><?= v2_te('Let\'s see what your venue would look like on viaqui.com.') ?></h2>
        <p><?= v2_te('Send a few details about your venue and activities. The ideal answer shows you which pages should be created, which ticket types fit and which SEO opportunities you have.') ?></p>
        <div class="fv-prep">
          <b><?= v2_te('What you can prepare beforehand:') ?></b>
          <ul>
            <li><?= v2_ic('check') ?><?= v2_te('the venue name and the city') ?></li>
            <li><?= v2_ic('check') ?><?= v2_te('the types of activities') ?></li>
            <li><?= v2_ic('check') ?><?= v2_te('prices and capacity') ?></li>
            <li><?= v2_ic('check') ?><?= v2_te('opening hours and access rules') ?></li>
          </ul>
        </div>
      </div>

      <div class="fv-form-wrap">
        <form class="fv-form" id="fv-form" novalidate>
          <p class="fv-error" id="fv-error" role="alert" tabindex="-1" hidden></p>
          <!-- people never see or reach this; a bot that fills it gets a quiet "sent" -->
          <div class="fv-trap" aria-hidden="true"><label for="fv-fax"><?= v2_te('Fax (leave empty)') ?></label><input id="fv-fax" name="fax" type="text" tabindex="-1" autocomplete="off"></div>
          <div class="fv-fields">
            <div class="fv-field"><label for="fv-name"><?= v2_te('Contact name') ?></label><input id="fv-name" name="contact_name" type="text" autocomplete="name" maxlength="160" required placeholder="<?= v2_te('Full name') ?>"></div>
            <div class="fv-field"><label for="fv-email"><?= v2_te('Email') ?></label><input id="fv-email" name="email" type="email" inputmode="email" autocomplete="email" spellcheck="false" maxlength="190" required placeholder="<?= v2_te('email@venue.com') ?>"></div>
            <div class="fv-field"><label for="fv-phone"><?= v2_te('Phone') ?></label><input id="fv-phone" name="phone" type="tel" autocomplete="tel" maxlength="40" placeholder="+351..."></div>
            <div class="fv-field"><label for="fv-role"><?= v2_te('Role') ?></label><select class="select" id="fv-role" name="role"><?php foreach ($fvRoles as $role): ?><option><?= v2_e($role) ?></option><?php endforeach; ?></select></div>
            <div class="fv-field"><label for="fv-venue"><?= v2_te('Venue name') ?></label><input id="fv-venue" name="location_name" type="text" autocomplete="organization" maxlength="200" required placeholder="<?= v2_te('e.g. Mystery Rooms Lisbon') ?>"></div>
            <div class="fv-field"><label for="fv-city"><?= v2_te('City') ?></label><input id="fv-city" name="city" type="text" autocomplete="address-level2" maxlength="120" required placeholder="<?= v2_te('e.g. Lisbon') ?>"></div>
            <div class="fv-field"><label for="fv-type"><?= v2_te('Venue type') ?></label><select class="select" id="fv-type" name="venue_type"><?php foreach ($fvVenueTypes as $typeSlug => $typeLabel): ?><option value="<?= v2_e($typeSlug) ?>"><?= v2_e($typeLabel) ?></option><?php endforeach; ?></select></div>
            <div class="fv-field"><label for="fv-count"><?= v2_te('How many activities do you sell?') ?></label><select class="select" id="fv-count" name="activities_count"><?php foreach ($fvCounts as $count): ?><option><?= v2_e($count) ?></option><?php endforeach; ?></select></div>
            <div class="fv-field is-wide"><label for="fv-message"><?= v2_te('What do you want to sell online?') ?></label><textarea id="fv-message" name="message" rows="5" maxlength="1800" placeholder="<?= v2_te('Describe your activities, ticket types, opening hours and the problems you have now with sales or bookings.') ?>"></textarea></div>
            <label class="fv-check is-wide"><input id="fv-consent" name="consent" type="checkbox" required><span><?= v2_te('I agree to be contacted for a conversation about listing my venue on viaqui.com.') ?></span></label>
          </div>
          <button class="btn btn-primary fv-submit" id="fv-submit" type="submit"><?= v2_te('Send the request') ?></button>
        </form>
        <div class="fv-done" id="fv-done" hidden>
          <span class="fv-done-ic" aria-hidden="true"><?= v2_ic('check') ?></span>
          <h3 id="fv-done-h" tabindex="-1"><?= v2_te('Thank you!') ?></h3>
          <p><?= v2_t('Your request has reached the viaqui.com team. We will contact you on the next working day at {email}.', ['email' => '<strong id="fv-done-email"></strong>']) ?></p>
          <a class="btn btn-ghost" href="/partners"><?= v2_te('See how the partnership works') ?></a>
        </div>
      </div>
    </div>
  </section>

  <!-- FAQ -->
  <section class="sec fv-faq" aria-labelledby="fv-faq-h">
    <div class="wrap fv-faq-grid">
      <div>
        <p class="kicker"><?= v2_te('FAQ') ?></p>
        <h2 id="fv-faq-h"><?= v2_te('Frequently asked questions') ?></h2>
      </div>
      <div>
        <?php foreach ($fvFaqs as $fi => [$faqQ, $faqA]): ?>
        <details class="qa"<?= $fi === 0 ? ' open' : '' ?>><summary><?= v2_e($faqQ) ?><span class="pm"><?= v2_ic('plus') ?></span></summary><p><?= v2_e($faqA) ?></p></details>
        <?php endforeach; ?>
      </div>
    </div>
  </section>

  <!-- FINAL CTA -->
  <section class="sec fv-final-sec">
    <div class="wrap">
      <div class="fv-final">
        <div>
          <p class="fv-final-k"><?= v2_te('Ready to list?') ?></p>
          <h2><?= v2_te('Your venue can become the next activity discovered online.') ?></h2>
          <p><?= v2_te('If you have an activity people should discover, viaqui.com can be the infrastructure that sells it.') ?></p>
        </div>
        <div class="fv-final-cta">
          <a class="btn fv-btn-white" href="#demo"><?= v2_te('Request a demo') ?></a>
          <a class="btn btn-outline-light" href="/partners#bani"><?= v2_te('See pricing') ?></a>
        </div>
      </div>
    </div>
  </section>
</main>
<?php include __DIR__ . '/includes/v2/footer.php'; ?>
