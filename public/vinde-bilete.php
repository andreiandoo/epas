<?php
/**
 * Sales page for venues and operators: /vinde-bilete (v2 design).
 *
 * A product-led page, not a feature list: it shows the operator's own screens — the dashboard, the on-site POS
 * ("InfoPoint"), and the scanning app — with the labels they really carry, a day at the venue from opening to closing
 * the register, what the hardware needs to be (a thermal printer, a phone or a tablet), what the money looks like
 * (2% commission, a calculator) and a demo request that goes into the same lead pipeline as /pentru-locatii.
 *
 * The screens are mock-ups built in HTML with sample figures, each marked as such; the catalogue numbers come from the
 * API. The on-site POS and the scanning app run on the platform and are switched on per account.
 */

$pageCacheTTL = 900;
require_once __DIR__ . '/includes/page-cache.php';
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/api.php';
require_once __DIR__ . '/includes/nav-helpers.php';
require_once __DIR__ . '/includes/v2/helpers.php';
require_once __DIR__ . '/includes/v2/nav.php';

$slR = api_cached_many([
    'attractions' => ['key' => 'v2_attractions_total', 'endpoint' => '/attractions', 'params' => ['per_page' => 1], 'ttl' => 21600],
]);
$slAttractions = (int) ($slR['attractions']['data']['pagination']['total'] ?? 0);
$slCities = count($V2NAV['allCities'] ?? []);
$slCategories = count($V2NAV['categories']);

$pageTitleRaw = v2_t('Sell tickets online and on site: {site}', ['site' => SITE_NAME]);
$pageDescription = v2_t('Operator panel, POS for ticket office sales, scanning app on phones and tablets, receipts on a thermal printer and clear payouts. 2% commission, no subscription.');
$canonicalUrl = SITE_URL . '/vinde-bilete';
$noindex = true; // /partners is the page to find; this one stays for old links
$ogImage = SITE_URL . '/assets/v2/img/hero-1440.webp';

$slFaq = [
    [v2_t('How long until I start selling?'), v2_t('After you send us the venue details, we prepare your account, activities and ticket types. Publishing depends on how quickly we receive the opening hours, prices and photos. Usually it is a matter of days, not months.')],
    [v2_t('What does it cost?'), v2_t('The commission is 2%, added on top of the ticket price and not taken out of it, for exclusive sales through viaqui.com. If you also sell your tickets elsewhere, the commission is 4%: 2% included in the price and 2% added. There is no monthly subscription and no setup fee.')],
    [v2_t('Can I sell at the ticket office too, not only online?'), v2_t('Yes. The POS issues tickets on the spot, keeps the cart, takes cash or card, prints the receipt and gives you the register statement and the register closing at the end of the shift. Online and ticket office sales end up in the same report.')],
    [v2_t('What hardware do I need?'), v2_t('A phone or a tablet for scanning and, if you want a printed receipt, a 58 or 80 mm thermal printer. The receipt prints straight from the browser, with no special drivers. The POS stands in for the till, inside the app.')],
    [v2_t('How do we scan tickets at the entrance?'), v2_t('With the scanning app, installed on a phone or a tablet. It scans with the camera, and if a code cannot be read, you can type it. It shows at once whether the ticket is valid, was already scanned or is not recognised, with vibration and sound.')],
    [v2_t('Who answers if something goes wrong?'), v2_t('You have support by email and phone, Monday to Friday, between 09:00 and 18:00, plus support tickets straight from the panel. For the day of a big event, we agree in advance who is available.')],
    [v2_t('What happens to the money from sales?'), v2_t('In the panel you see the available balance, what is being processed and how much you have received so far, for each activity. You request a payout whenever you want, and the documents and invoices stay in your account.')],
];

$structuredData = [[
    '@context' => 'https://schema.org', '@type' => 'FAQPage',
    'mainEntity' => array_map(function ($f) {
        return ['@type' => 'Question', 'name' => $f[0], 'acceptedAnswer' => ['@type' => 'Answer', 'text' => $f[1]]];
    }, $slFaq),
], [
    '@context' => 'https://schema.org', '@type' => 'BreadcrumbList',
    'itemListElement' => [
        ['@type' => 'ListItem', 'position' => 1, 'name' => v2_t('Home'), 'item' => SITE_URL . '/'],
        ['@type' => 'ListItem', 'position' => 2, 'name' => v2_t('Sell tickets'), 'item' => $canonicalUrl],
    ],
]];

$v2Styles = ['sell.css'];
$v2Scripts = ['sell.js', 'for-venues.js'];
$v2HeaderOverlay = true;

/** A row in the mock operator sidebar. */
$slNav = function (string $icon, string $label, bool $on = false): string {
    return '<span class="sl-ui-nav' . ($on ? ' is-on' : '') . '">' . v2_ic($icon) . '<b>' . v2_e($label) . '</b></span>';
};
/** A figure in the mock dashboard. */
$slKpi = function (string $label, string $value, string $delta = '', string $tone = ''): string {
    return '<div class="sl-ui-kpi"><p>' . v2_e($label) . '</p><b>' . v2_e($value) . '</b>'
        . ($delta ? '<small class="' . $tone . '">' . v2_ic('trend-up') . v2_e($delta) . '</small>' : '') . '</div>';
};

include __DIR__ . '/includes/v2/head.php';
include __DIR__ . '/includes/v2/header.php';
?>
<main id="main" tabindex="-1">

  <!-- ===================== HERO ===================== -->
  <section class="sl-hero" aria-labelledby="sl-h">
    <svg class="sl-hero-line draw-clip" viewBox="0 590 3240 310" aria-hidden="true" focusable="false"><use href="#drum-g"/></svg>
    <div class="wrap sl-hero-in">
      <div class="sl-hero-copy">
        <p class="sl-kicker"><?= v2_ic('buildings') ?><?= v2_te('For venues and operators') ?></p>
        <h1 class="sl-h" id="sl-h"><?= v2_t('Sell online and at the ticket office. <em>From a single account.</em>') ?></h1>
        <p class="sl-lead"><?= v2_te('An operator panel that reads at a glance, a POS for selling on the spot, a scanning app on a phone or tablet and receipts on a thermal printer. Everything sold, wherever it was sold, ends up in the same report.') ?></p>
        <div class="sl-cta">
          <a class="btn btn-primary" href="#demo"><?= v2_ic('lightning') ?><?= v2_te('Request a demonstration') ?></a>
          <a class="btn btn-outline-light" href="#panou"><?= v2_te('See the operator panel') ?><?= v2_ic('arrow-right') ?></a>
        </div>
        <ul class="sl-facts">
          <li><?= v2_ic('percent') ?><span><b><?= v2_te('2% commission') ?></b><?= v2_te('without touching your price') ?></span></li>
          <li><?= v2_ic('wallet') ?><span><b><?= v2_te('No subscription') ?></b><?= v2_te('and no setup cost') ?></span></li>
          <li><?= v2_ic('scan') ?><span><b><?= v2_te('Online + on site') ?></b><?= v2_te('in the same account') ?></span></li>
        </ul>
      </div>

      <div class="sl-devices" aria-label="<?= v2_te('The screens of the platform: panel, POS and the scanning app') ?>">
        <div class="sl-dev sl-dev-desk">
          <div class="sl-ui">
            <div class="sl-ui-top"><span class="sl-ui-dots" aria-hidden="true"><i></i><i></i><i></i></span><span class="sl-ui-url">viaqui.com/organizator/panou</span></div>
            <div class="sl-ui-body">
              <div class="sl-ui-side">
                <?= $slNav('squares-four', v2_t('Panel'), true) ?>
                <?= $slNav('calendar-blank', v2_t('Activities')) ?>
                <?= $slNav('users-three', v2_t('Participants')) ?>
                <?= $slNav('shopping-cart-simple', v2_t('Sales')) ?>
                <?= $slNav('wallet', v2_t('Balance')) ?>
              </div>
              <div class="sl-ui-main">
                <p class="sl-ui-h"><?= v2_te('Welcome back') ?></p>
                <div class="sl-ui-kpis">
                  <?= $slKpi(v2_t('Revenue this month'), v2_money(18240), '+12%', 'is-up') ?>
                  <?= $slKpi(v2_t('Tickets sold'), '412', '+8%', 'is-up') ?>
                </div>
                <div class="sl-ui-chart" aria-hidden="true">
                  <?php foreach ([38, 52, 44, 68, 59, 82, 74] as $h): ?><i style="--h:<?= $h ?>%"></i><?php endforeach; ?>
                </div>
              </div>
            </div>
          </div>
        </div>
        <div class="sl-dev sl-dev-phone">
          <div class="sl-ph">
            <div class="sl-ph-top"><span></span></div>
            <div class="sl-ph-scan">
              <p class="sl-ph-k"><?= v2_te('Scanning') ?></p>
              <div class="sl-ph-frame" aria-hidden="true"><?= v2_ic('qr-code') ?><span class="sl-ph-laser"></span></div>
              <p class="sl-ph-state is-ok"><?= v2_ic('check-circle') ?><?= v2_te('ACCESS APPROVED') ?></p>
              <p class="sl-ph-sub"><?= v2_te('Adult ticket · Gate 1') ?></p>
            </div>
          </div>
        </div>
      </div>
    </div>
  </section>
  <div id="hdr-sentinel" aria-hidden="true"></div>

  <!-- ===================== A DAY AT THE VENUE ===================== -->
  <section class="sec sl-day" aria-labelledby="sl-day-h">
    <div class="wrap">
      <div class="sec-head">
        <h2 id="sl-day-h"><?= v2_te('A day at your venue, from the first sale to closing the register') ?></h2>
        <p class="sl-sub"><?= v2_te('The same tickets, the same reports, whether the person bought from home or at the ticket office.') ?></p>
      </div>
      <ol class="sl-steps">
        <?php foreach ([
            ['clock', '08:40', v2_t('You open the day'), v2_t('You see how many tickets are sold for today and how many people are expected in each time slot.')],
            ['shopping-cart-simple', '09:15', v2_t('Online sales'), v2_t('People buy from your activity page or from the widget on your own website. The ticket goes out by email, with a QR code.')],
            ['printer', '10:30', v2_t('Sales at the ticket office'), v2_t('At the entrance, the POS issues the ticket on the spot: cart, cash or card, receipt printed on the thermal printer.')],
            ['scan', '11:00', v2_t('Scanning at the entrance'), v2_t('With a phone or a tablet. The ticket is valid, was already scanned or is not recognised: you see it at once, with sound and vibration.')],
            ['door-open', '18:00', v2_t('You close the register'), v2_t('The register statement shows how much cash you hand over and how much was taken by card, for each operator\'s shift.')],
            ['chart-line-up', '18:20', v2_t('You see the result'), v2_t('The day\'s report adds up online and ticket office, by activity, by time slot and by ticket type.')],
        ] as $i => [$icon, $time, $title, $text]): ?>
        <li class="sl-step" style="--i:<?= $i ?>">
          <span class="sl-step-ic"><?= v2_ic($icon) ?></span>
          <p class="sl-step-time"><?= $time ?></p>
          <h3><?= v2_e($title) ?></h3>
          <p><?= v2_e($text) ?></p>
        </li>
        <?php endforeach; ?>
      </ol>
    </div>
  </section>

  <!-- ===================== THE OPERATOR PANEL ===================== -->
  <section class="sec sl-panel" id="panou" aria-labelledby="sl-panel-h">
    <div class="wrap">
      <div class="sec-head">
        <h2 id="sl-panel-h"><?= v2_te('The operator panel') ?></h2>
        <p class="sl-sub"><?= v2_te('Every screen has one purpose and reads at a glance. Pick a section and see what it looks like.') ?></p>
      </div>

      <div class="sl-demo">
        <div class="sl-tabs" role="tablist" aria-label="<?= v2_te('Sections of the panel') ?>" data-tabs>
          <?php foreach ([
              ['panou', 'squares-four', v2_t('Panel')],
              ['vanzari', 'shopping-cart-simple', v2_t('Sales')],
              ['participanti', 'users-three', v2_t('Participants')],
              ['sold', 'wallet', v2_t('Balance')],
              ['marketing', 'megaphone', v2_t('Marketing')],
          ] as $i => [$key, $icon, $label]): ?>
          <button class="sl-tab" type="button" role="tab" id="slt-<?= $key ?>" aria-controls="slp-<?= $key ?>" aria-selected="<?= $i ? 'false' : 'true' ?>" tabindex="<?= $i ? -1 : 0 ?>"><?= v2_ic($icon) ?><?= v2_e($label) ?></button>
          <?php endforeach; ?>
        </div>

        <div class="sl-screen">
          <div class="sl-ui is-wide">
            <div class="sl-ui-top"><span class="sl-ui-dots" aria-hidden="true"><i></i><i></i><i></i></span><span class="sl-ui-url" id="sl-url">viaqui.com/organizator/panou</span></div>
            <div class="sl-ui-body">
              <div class="sl-ui-side">
                <?= $slNav('squares-four', v2_t('Panel'), true) ?>
                <?= $slNav('calendar-blank', v2_t('Activities')) ?>
                <?= $slNav('users-three', v2_t('Participants')) ?>
                <?= $slNav('shopping-cart-simple', v2_t('Sales')) ?>
                <?= $slNav('wallet', v2_t('Balance')) ?>
                <?= $slNav('file-text', v2_t('Documents')) ?>
                <?= $slNav('tag', v2_t('Promo codes')) ?>
                <?= $slNav('code', v2_t('Widgets')) ?>
                <?= $slNav('receipt', v2_t('Billing')) ?>
              </div>

              <div class="sl-ui-main">
                <div class="sl-p" id="slp-panou" role="tabpanel" aria-labelledby="slt-panou" data-url="viaqui.com/organizator/panou">
                  <p class="sl-ui-h"><?= v2_te('This month\'s figures') ?></p>
                  <div class="sl-ui-kpis is-four">
                    <?= $slKpi(v2_t('Revenue this month'), v2_money(18240), '+12%', 'is-up') ?>
                    <?= $slKpi(v2_t('Tickets sold this month'), '412', '+8%', 'is-up') ?>
                    <?= $slKpi(v2_t('Activities running'), '6') ?>
                    <?= $slKpi(v2_t('Conversion rate'), '4.8%', v2_t('+0.6 pp'), 'is-up') ?>
                  </div>
                  <p class="sl-ui-h2"><?= v2_te('Ticket sales') ?></p>
                  <div class="sl-ui-chart is-big" aria-hidden="true">
                    <?php foreach ([32, 46, 38, 60, 52, 74, 66, 81, 58, 69, 77, 92] as $h): ?><i style="--h:<?= $h ?>%"></i><?php endforeach; ?>
                  </div>
                  <div class="sl-ui-quick">
                    <span><?= v2_ic('plus') ?><?= v2_te('New activity') ?></span>
                    <span><?= v2_ic('tag') ?><?= v2_te('Promo code') ?></span>
                    <span><?= v2_ic('scan') ?><?= v2_te('Scanning at the entrance') ?></span>
                  </div>
                </div>

                <div class="sl-p" id="slp-vanzari" role="tabpanel" aria-labelledby="slt-vanzari" data-url="viaqui.com/organizator/vanzari" hidden>
                  <p class="sl-ui-h"><?= v2_te('Sales') ?></p>
                  <div class="sl-ui-chips"><span class="is-on"><?= v2_te('All channels') ?></span><span><?= v2_te('Online') ?></span><span><?= v2_te('Ticket office') ?></span><span><?= v2_te('This month') ?></span></div>
                  <table class="sl-ui-table">
                    <thead><tr><th><?= v2_te('Order') ?></th><th><?= v2_te('Activity') ?></th><th><?= v2_te('Channel') ?></th><th><?= v2_te('Total') ?></th><th><?= v2_te('Status') ?></th></tr></thead>
                    <tbody>
                      <?php foreach ([
                          ['BO-24817', v2_t('Guided tour · 11:00'), v2_t('Online'), v2_money(160), v2_t('Paid'), 'is-ok'],
                          ['BO-24816', v2_t('Adult admission'), v2_t('Ticket office'), v2_money(45), v2_t('Cash'), 'is-info'],
                          ['BO-24815', v2_t('Kids workshop · Saturday'), v2_t('Online'), v2_money(220), v2_t('Paid'), 'is-ok'],
                          ['BO-24814', v2_t('Family admission'), v2_t('Ticket office'), v2_money(120), v2_t('Card'), 'is-info'],
                      ] as [$no, $act, $chan, $total, $status, $tone]): ?>
                      <tr><td><b><?= $no ?></b></td><td><?= v2_e($act) ?></td><td><?= v2_e($chan) ?></td><td class="sl-right"><?= $total ?></td><td><span class="sl-pill <?= $tone ?>"><?= v2_e($status) ?></span></td></tr>
                      <?php endforeach; ?>
                    </tbody>
                  </table>
                </div>

                <div class="sl-p" id="slp-participanti" role="tabpanel" aria-labelledby="slt-participanti" data-url="viaqui.com/organizator/participanti" hidden>
                  <p class="sl-ui-h"><?= v2_te('Participants') ?></p>
                  <div class="sl-ui-search"><?= v2_ic('magnifying-glass') ?><span><?= v2_te('Search by name, email or ticket code') ?></span></div>
                  <ul class="sl-ui-list">
                    <?php foreach ([
                        ['AM', 'Andrei M.', v2_t('Guided tour · 11:00'), v2_t('Entered 10:58'), 'is-ok'],
                        ['IR', 'Ioana R.', v2_t('Kids workshop · 12:30'), v2_t('Expected'), ''],
                        ['DP', 'Dan P.', v2_t('Adult admission'), v2_t('Entered 10:41'), 'is-ok'],
                        ['MS', 'Maria S.', v2_t('Family admission · 4 people'), v2_t('Expected'), ''],
                    ] as [$ini, $name, $act, $state, $tone]): ?>
                    <li><span class="sl-ui-av"><?= $ini ?></span><span class="sl-ui-t"><b><?= v2_e($name) ?></b><small><?= v2_e($act) ?></small></span><span class="sl-pill <?= $tone ?>"><?= v2_e($state) ?></span></li>
                    <?php endforeach; ?>
                  </ul>
                </div>

                <div class="sl-p" id="slp-sold" role="tabpanel" aria-labelledby="slt-sold" data-url="viaqui.com/organizator/sold" hidden>
                  <p class="sl-ui-h"><?= v2_te('Balance') ?></p>
                  <div class="sl-ui-kpis">
                    <?= $slKpi(v2_t('Available balance'), v2_money(9420)) ?>
                    <?= $slKpi(v2_t('Processing'), v2_money(1180)) ?>
                    <?= $slKpi(v2_t('Total received'), v2_money(64700)) ?>
                  </div>
                  <div class="sl-ui-payout"><span><?= v2_ic('bank') ?><?= v2_te('The payout goes to the venue\'s account') ?></span><span class="sl-ui-btn"><?= v2_te('Request payout') ?></span></div>
                  <ul class="sl-ui-mini">
                    <li><span><?= v2_te('Guided tour') ?></span><b><?= v2_money(3900) ?></b></li>
                    <li><span><?= v2_te('Kids workshop') ?></span><b><?= v2_money(2640) ?></b></li>
                    <li><span><?= v2_te('Daily admission') ?></span><b><?= v2_money(2880) ?></b></li>
                  </ul>
                </div>

                <div class="sl-p" id="slp-marketing" role="tabpanel" aria-labelledby="slt-marketing" data-url="viaqui.com/organizator/promo" hidden>
                  <p class="sl-ui-h"><?= v2_te('Marketing') ?></p>
                  <ul class="sl-ui-list">
                    <li><span class="sl-ui-av is-tag"><?= v2_ic('tag') ?></span><span class="sl-ui-t"><b>AUTUMN10</b><small><?= v2_te('-10% · 214 uses') ?></small></span><span class="sl-pill is-ok"><?= v2_te('Active') ?></span></li>
                    <li><span class="sl-ui-av is-tag"><?= v2_ic('percent') ?></span><span class="sl-ui-t"><b>GROUP20</b><small><?= v2_te('-20% from 10 tickets · 38 uses') ?></small></span><span class="sl-pill is-ok"><?= v2_te('Active') ?></span></li>
                  </ul>
                  <p class="sl-ui-h2"><?= v2_te('Widget for your website') ?></p>
                  <pre class="sl-ui-code">&lt;script src="viaqui.com/widget.js"
  data-locatie="your-museum"&gt;&lt;/script&gt;</pre>
                </div>
              </div>
            </div>
          </div>
          <p class="sl-note"><?= v2_ic('info') ?><?= v2_te('Screens from the real panel, with sample data.') ?></p>
        </div>
      </div>
    </div>
  </section>

  <!-- ===================== POS ===================== -->
  <section class="sec sl-pos" id="ghiseu" aria-labelledby="sl-pos-h">
    <div class="wrap sl-split">
      <div class="sl-split-copy">
        <p class="sl-kicker is-light"><?= v2_ic('printer') ?><?= v2_te('On site') ?></p>
        <h2 id="sl-pos-h"><?= v2_te('Your ticket office, with receipts and register closing') ?></h2>
        <p class="sl-sub is-light"><?= v2_te('The POS issues the ticket on the spot and stands in for the till, inside the app: cart, cash or card payment, receipt printed on a 58 or 80 mm thermal printer, straight from the browser, with no drivers to install.') ?></p>
        <ul class="sl-checks">
          <?php foreach ([
              v2_t('A cart with your ticket types, packages and extras'),
              v2_t('Cash or card payment, on each operator\'s shift'),
              v2_t('A receipt printed automatically after each order, if you want'),
              v2_t('Register statement: how much cash you hand over, how much was taken by card'),
              v2_t('Register closing at the end of the shift, with the total of the orders'),
              v2_t('Invoices for companies, straight from the order'),
          ] as $t): ?>
          <li><?= v2_ic('check-circle') ?><?= v2_e($t) ?></li>
          <?php endforeach; ?>
        </ul>
        <p class="sl-hint"><?= v2_ic('info') ?><?= v2_te('The on-site sales module is switched on for the venue\'s account.') ?></p>
      </div>

      <div class="sl-tablet" aria-label="<?= v2_te('The POS screen, with sample data') ?>">
        <div class="sl-tb">
          <div class="sl-tb-head"><b><?= v2_ic('ticket') ?><?= v2_te('InfoPoint: issue tickets') ?></b><span class="sl-tb-open"><?= v2_te('Register open') ?></span></div>
          <div class="sl-tb-body">
            <div class="sl-tb-items">
              <?php foreach ([[v2_t('Adult admission'), 45], [v2_t('Child admission'), 25], [v2_t('Guided tour'), 60], [v2_t('Family (2+2)'), 120], [v2_t('Audio guide'), 15], [v2_t('Workshop'), 80]] as $i => [$n, $p]): ?>
              <button class="sl-tb-item" type="button" data-sl-add="<?= $i ?>" data-name="<?= v2_e($n) ?>" data-price="<?= (int) $p ?>"><b><?= v2_e($n) ?></b><small><?= v2_money((int) $p) ?></small></button>
              <?php endforeach; ?>
            </div>
            <div class="sl-tb-cart">
              <p class="sl-tb-k"><?= v2_te('Cart') ?></p>
              <ul id="sl-cart" class="sl-tb-lines"><li class="sl-tb-empty"><?= v2_te('Tap a ticket to add it') ?></li></ul>
              <p class="sl-tb-total"><span><?= v2_te('Total') ?></span><b id="sl-total"><?= v2_money(0) ?></b></p>
              <div class="sl-tb-pay">
                <button class="sl-tb-btn is-cash" type="button" data-sl-pay="cash"><?= v2_ic('coins') ?><?= v2_te('Cash') ?></button>
                <button class="sl-tb-btn is-card" type="button" data-sl-pay="card"><?= v2_ic('credit-card') ?><?= v2_te('Card') ?></button>
              </div>
              <p class="sl-tb-print" id="sl-print" role="status"><?= v2_ic('printer') ?><?= v2_te('Receipt on the thermal printer, after each order') ?></p>
            </div>
          </div>
        </div>
        <div class="sl-receipt" id="sl-receipt" aria-hidden="true">
          <p class="sl-rc-h"><?= v2_te('YOUR MUSEUM') ?></p>
          <p class="sl-rc-sub"><?= v2_te('Non-fiscal receipt · example') ?></p>
          <ul id="sl-rc-lines"></ul>
          <p class="sl-rc-total"><span><?= v2_te('TOTAL') ?></span><b id="sl-rc-total"><?= v2_money(0) ?></b></p>
          <p class="sl-rc-qr"><?= v2_ic('qr-code') ?></p>
        </div>
      </div>
    </div>
  </section>

  <!-- ===================== SCANNING ===================== -->
  <section class="sec sl-scan" id="scanare" aria-labelledby="sl-scan-h">
    <div class="wrap sl-split is-rev">
      <div class="sl-split-copy">
        <p class="sl-kicker"><?= v2_ic('scan') ?><?= v2_te('At the entrance') ?></p>
        <h2 id="sl-scan-h"><?= v2_te('The scanning app, on phones and tablets') ?></h2>
        <p class="sl-sub"><?= v2_te('It installs on the phone\'s home screen, like any app. It scans with the camera, and if a code cannot be read, you type it. The answer comes at once, with sound and vibration, so the person at the gate doesn\'t have to keep their eyes on the screen.') ?></p>
        <ul class="sl-checks">
          <?php foreach ([
              v2_t('Three clear answers: access approved, already scanned, invalid ticket'),
              v2_t('Code typed by hand, when the ticket is creased or the screen is cracked'),
              v2_t('Access gates and people assigned to each gate'),
              v2_t('Guest list and check-in without a printed ticket'),
              v2_t('The screen stays on while you scan, and the app runs on tablets too'),
              v2_t('Shift reports: how many came in, when, through which gate'),
          ] as $t): ?>
          <li><?= v2_ic('check-circle') ?><?= v2_e($t) ?></li>
          <?php endforeach; ?>
        </ul>
        <p class="sl-hint"><?= v2_ic('info') ?><?= v2_te('The scanning app is switched on for the venue\'s account.') ?></p>
      </div>

      <div class="sl-scanwrap">
        <div class="sl-ph is-big" id="sl-scanner" aria-label="<?= v2_te('The scanning app, example') ?>">
          <div class="sl-ph-top"><span></span></div>
          <div class="sl-ph-scan">
            <p class="sl-ph-k"><?= v2_te('Scanning · Gate 1') ?></p>
            <div class="sl-ph-frame"><?= v2_ic('qr-code') ?><span class="sl-ph-laser"></span></div>
            <p class="sl-ph-state" id="sl-state"><?= v2_ic('check-circle') ?><span id="sl-state-t"><?= v2_te('ACCESS APPROVED') ?></span></p>
            <p class="sl-ph-sub" id="sl-state-sub"><?= v2_te('Adult ticket · 11:00') ?></p>
            <div class="sl-ph-stats"><span><b id="sl-rate">18</b><?= v2_te('scans/min') ?></span><span><b>412</b><?= v2_te('entered') ?></span><span><b>37</b><?= v2_te('waiting') ?></span></div>
          </div>
        </div>
      </div>
    </div>
  </section>

  <!-- ===================== HARDWARE ===================== -->
  <section class="sec sl-hw" aria-labelledby="sl-hw-h">
    <div class="wrap">
      <div class="sec-head">
        <h2 id="sl-hw-h"><?= v2_te('What you need to get started') ?></h2>
        <p class="sl-sub"><?= v2_te('No server, no licences and no complicated installs. Most often, venues start with what they already have in the house.') ?></p>
      </div>
      <ul class="sl-hw-grid">
        <?php foreach ([
            ['scan', v2_t('A phone or a tablet'), v2_t('Android or iPhone, for scanning at the entrance and for selling on the spot.')],
            ['printer', v2_t('A thermal printer'), v2_t('58 or 80 mm, for the receipt. It prints from the browser, with no special drivers.')],
            ['squares-four', v2_t('A computer for the ticket office'), v2_t('Any laptop or desktop with a modern browser. The POS runs in the browser.')],
            ['code', v2_t('Your website, if you have one'), v2_t('You put the sales widget on your page and sell straight from there, with the same tickets.')],
            ['file-text', v2_t('Documents and invoices'), v2_t('Invoices for companies, the account documents and the reports stay in the panel.')],
            ['headset', v2_t('People who answer'), v2_t('Support by email and phone, Monday to Friday, 09:00 to 18:00, plus tickets from the panel.')],
        ] as [$icon, $title, $text]): ?>
        <li><span class="sl-hw-ic"><?= v2_ic($icon) ?></span><b><?= v2_e($title) ?></b><span><?= v2_e($text) ?></span></li>
        <?php endforeach; ?>
      </ul>
    </div>
  </section>

  <!-- ===================== THE MONEY ===================== -->
  <section class="sec sl-money" id="costuri" aria-labelledby="sl-money-h">
    <div class="wrap sl-split">
      <div class="sl-split-copy">
        <p class="sl-kicker is-light"><?= v2_ic('percent') ?><?= v2_te('Costs') ?></p>
        <h2 id="sl-money-h"><?= v2_te('2% commission. Your price stays yours.') ?></h2>
        <p class="sl-sub is-light"><?= v2_te('No monthly subscription, no setup cost and no fee for each ticket issued at the ticket office. The commission is 2% if you sell exclusively through viaqui.com and 4% if you also sell your tickets elsewhere: 2% included in the price and 2% added.') ?></p>
        <ul class="sl-checks is-light">
          <li><?= v2_ic('check-circle') ?><?= v2_te('You see the available balance and request a payout whenever you want') ?></li>
          <li><?= v2_ic('check-circle') ?><?= v2_te('Tickets sold at the ticket office carry no platform commission') ?></li>
          <li><?= v2_ic('check-circle') ?><?= v2_te('Documents and invoices stay in your account') ?></li>
        </ul>
      </div>
      <div class="sl-calc">
        <p class="sl-calc-h"><?= v2_te('What it means for you') ?></p>
        <div class="sl-calc-row">
          <label for="sl-qty"><?= v2_te('Tickets sold online per month') ?></label>
          <input id="sl-qty" type="number" inputmode="numeric" min="0" max="100000" step="10" value="400">
        </div>
        <div class="sl-calc-row">
          <label for="sl-price"><?= v2_te('Average price per ticket (€)') ?></label>
          <input id="sl-price" type="number" inputmode="numeric" min="0" max="10000" step="5" value="45">
        </div>
        <dl class="sl-calc-out">
          <div><dt><?= v2_te('You take in from tickets') ?></dt><dd id="sl-out-rev"><?= v2_money(18000) ?></dd></div>
          <div><dt><?= v2_te('2% commission, added on top of the price') ?></dt><dd id="sl-out-fee"><?= v2_money(360) ?></dd></div>
          <div class="is-total"><dt><?= v2_te('Stays with you') ?></dt><dd id="sl-out-net"><?= v2_money(18000) ?></dd></div>
        </dl>
        <p class="sl-calc-note"><?= v2_t('The commission is added to the ticket price, so your price stays whole. The final price is {price} per ticket.', ['price' => '<span id="sl-out-buyer">' . v2_money(45.9) . '</span>']) ?></p>
      </div>
    </div>
  </section>

  <!-- ===================== THE PLATFORM ===================== -->
  <section class="sec sl-reach" aria-labelledby="sl-reach-h">
    <div class="wrap">
      <div class="sec-head">
        <h2 id="sl-reach-h"><?= v2_te('You don\'t sell from a panel alone. You sell from a place where people are already looking') ?></h2>
      </div>
      <ul class="sl-reach-grid">
        <?php if ($slAttractions): ?><li><b><?= v2_thousands($slAttractions) ?></b><span><?= v2_te('attractions in the catalogue') ?></span></li><?php endif; ?>
        <?php if ($slCities): ?><li><b><?= $slCities ?></b><span><?= v2_te('cities with their own pages') ?></span></li><?php endif; ?>
        <?php if ($slCategories): ?><li><b><?= $slCategories ?></b><span><?= v2_te('categories of experiences') ?></span></li><?php endif; ?>
        <li><b>2%</b><span><?= v2_te('commission, without touching your price') ?></span></li>
      </ul>
      <ul class="sl-reach-list">
        <?php foreach ([
            ['magnifying-glass', v2_t('Your page is built to be found'), v2_t('Every activity and every venue has its own page, optimised for search, with opening hours, prices and availability.')],
            ['map-pin', v2_t('You show up in the city and in the category'), v2_t('You are on the city pages, in the categories and in the editorial guides, next to activities the same people are looking for.')],
            ['gift', v2_t('Gift cards and points'), v2_t('Gift cards and bonus points bring people back, without you building the loyalty programme.')],
            ['star', v2_t('Reviews and recommendations'), v2_t('Customer reviews and automatic recommendations send new people to the right activities.')],
        ] as [$icon, $title, $text]): ?>
        <li><span class="sl-hw-ic"><?= v2_ic($icon) ?></span><div><b><?= v2_e($title) ?></b><p><?= v2_e($text) ?></p></div></li>
        <?php endforeach; ?>
      </ul>
    </div>
  </section>

  <!-- ===================== DEMO + FAQ ===================== -->
  <section class="sec sl-demo-sec" id="demo" aria-labelledby="sl-demo-h">
    <div class="wrap sl-demo-grid">
      <div class="sl-form-wrap">
        <h2 id="sl-demo-h"><?= v2_te('Let\'s look together at what your venue would look like') ?></h2>
        <p class="sl-sub"><?= v2_te('We show you the panel on your own data: which activities you would publish, which ticket types fit and what a day at the ticket office would look like. No long presentation.') ?></p>
        <form class="sl-form" id="fv-form" novalidate>
          <p class="sl-form-err" id="fv-error" role="alert" tabindex="-1" hidden></p>
          <div class="sl-hp"><label for="fv-fax"><?= v2_te('Leave this field empty') ?></label><input id="fv-fax" name="fax" type="text" tabindex="-1" autocomplete="off"></div>
          <div class="sl-f2">
            <p class="sl-field"><label for="fv-name"><?= v2_te('Full name') ?><span aria-hidden="true">*</span></label><input id="fv-name" name="contact_name" type="text" required maxlength="120" autocomplete="name"></p>
            <p class="sl-field"><label for="fv-email"><?= v2_te('Email') ?><span aria-hidden="true">*</span></label><input id="fv-email" name="email" type="email" required maxlength="120" autocomplete="email"></p>
          </div>
          <div class="sl-f2">
            <p class="sl-field"><label for="fv-phone"><?= v2_te('Phone') ?></label><input id="fv-phone" name="phone" type="tel" maxlength="30" autocomplete="tel"></p>
            <p class="sl-field"><label for="fv-role"><?= v2_te('Your role') ?></label><span class="sl-sel"><select id="fv-role" name="role"><option value=""><?= v2_te('Choose') ?></option><option><?= v2_te('Owner') ?></option><option><?= v2_te('Venue manager') ?></option><option><?= v2_te('Marketing') ?></option><option><?= v2_te('Operations / ticket office') ?></option><option><?= v2_te('Other') ?></option></select><?= v2_ic('caret-down') ?></span></p>
          </div>
          <div class="sl-f2">
            <p class="sl-field"><label for="fv-venue"><?= v2_te('Venue name') ?><span aria-hidden="true">*</span></label><input id="fv-venue" name="location_name" type="text" required maxlength="160"></p>
            <p class="sl-field"><label for="fv-city"><?= v2_te('City') ?><span aria-hidden="true">*</span></label><input id="fv-city" name="city" type="text" required maxlength="80" list="ftr-cities"></p>
          </div>
          <div class="sl-f2">
            <p class="sl-field"><label for="fv-type"><?= v2_te('Venue type') ?></label><span class="sl-sel"><select id="fv-type" name="venue_type"><option value=""><?= v2_te('Choose') ?></option><?php foreach (array_slice($V2NAV['categories'], 0, 12) as $c): ?><option value="<?= v2_e($c['slug']) ?>"><?= v2_e($c['name']) ?></option><?php endforeach; ?><option value="other"><?= v2_te('Something else') ?></option></select><?= v2_ic('caret-down') ?></span></p>
            <p class="sl-field"><label for="fv-count"><?= v2_te('How many activities you have') ?></label><span class="sl-sel"><select id="fv-count" name="activities_count"><option value=""><?= v2_te('Choose') ?></option><option>1 - 3</option><option>4 - 10</option><option>11 - 30</option><option><?= v2_te('over 30') ?></option></select><?= v2_ic('caret-down') ?></span></p>
          </div>
          <p class="sl-field"><label for="fv-message"><?= v2_te('What you would like to solve') ?></label><textarea id="fv-message" name="message" rows="3" maxlength="2000" placeholder="<?= v2_te('For example: we only sell at the ticket office and want online too, or we have queues at the entrance at weekends.') ?>"></textarea></p>
          <p class="sl-consent"><input id="fv-consent" name="consent" type="checkbox" required><label for="fv-consent"><?= v2_t('I agree to be contacted about listing my venue. <a href="{url}">Privacy policy</a>', ['url' => '/privacy']) ?></label></p>
          <button class="btn btn-primary" type="submit" id="fv-submit"><?= v2_te('Send the request') ?><?= v2_ic('arrow-right') ?></button>
        </form>
        <div class="sl-done" id="fv-done" hidden>
          <span class="sl-done-ic"><?= v2_ic('check-circle') ?></span>
          <h3 id="fv-done-h"><?= v2_te('We have received your request') ?></h3>
          <p><?= v2_t('We have sent a confirmation to {email}. We will come back with a proposal for a demonstration.', ['email' => '<b id="fv-done-email"></b>']) ?></p>
        </div>
        <p class="sl-alt"><?= v2_t('Prefer to get in touch directly? Write to us at {email} or call {phone}, Monday to Friday, 09:00 to 18:00.', ['email' => '<a href="mailto:' . v2_e(SUPPORT_EMAIL) . '">' . v2_e(SUPPORT_EMAIL) . '</a>', 'phone' => '<a href="tel:+40750292962">+40 750 292 962</a>']) ?></p>
      </div>

      <div class="sl-faq">
        <h2 class="sl-faq-h"><?= v2_te('Frequently asked questions') ?></h2>
        <?php foreach ($slFaq as $i => [$q, $a]): ?>
        <details class="qa"<?= $i === 0 ? ' open' : '' ?>><summary><?= v2_e($q) ?><span class="pm"><?= v2_ic('plus') ?></span></summary><p><?= v2_e($a) ?></p></details>
        <?php endforeach; ?>
      </div>
    </div>
  </section>

  <div class="sl-bar" id="sl-bar" hidden>
    <span><b><?= v2_te('Want to see the panel on your own data?') ?></b><small><?= v2_te('A 20-minute demonstration, no obligation.') ?></small></span>
    <a class="btn btn-primary" href="#demo"><?= v2_te('Request a demo') ?></a>
  </div>
</main>
<?php
include __DIR__ . '/includes/v2/footer.php';
