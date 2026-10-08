<?php
/**
 * The operator's booking widgets: /organizator/widget-uri (activities module), v2 design.
 *
 * The booking of a location (or of one of its products) on the operator's own site, in an iframe served by
 * embed/locatie.php; with embed code v2 (embed/bo-widget.js) the checkout runs in the widget too. Here: whether widgets are on for the account (the admin's
 * "Enable embed widgets"), the sites allowed to show them (settings.embed_domains, saved through
 * /organizer/widget-settings), the pick of location and product, the code to paste (iframe + a line that fits its
 * height), a plain "Buy tickets" button for sites that cannot embed, and a live preview.
 *
 * org-am-widgets.js reads /organizer/me and /organizer/activities-module/locations|products.
 */

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/api.php';
require_once __DIR__ . '/../includes/nav-helpers.php';
require_once __DIR__ . '/../includes/v2/helpers.php';
require_once __DIR__ . '/../includes/v2/organizer.php';

$pageTitleRaw = v2_t('Embed widgets: {site}', ['site' => SITE_NAME]);
$pageDescription = v2_t('Booking for your venue on your own website: the widget code, the allowed sites and the preview.');
$canonicalUrl = SITE_URL . '/organizator/widget-uri';
$noindex = true;
$skipPageCache = true;

$v2Styles = ['organizer.css', 'org-venue.css', 'org-am.css'];
$v2Scripts = ['organizer.js', 'org-am.js', 'org-am-widgets.js'];
$v2LegacyScripts = ['assets/js/config.js', 'assets/js/utils.js', 'assets/js/api.js', 'assets/js/auth.js'];
$v2HeadExtra = v2_account_client_config('organizer');
$v2ClientData = ['widgets' => ['site' => rtrim(SITE_URL, '/')]];

include __DIR__ . '/../includes/v2/head.php';
v2_org_start('am-widgets');
?>
<div class="ve am" id="am-wg">
  <header class="ve-head">
    <div>
      <p class="ve-eyebrow"><?= v2_ic('code') ?><?= v2_te('Promotion') ?></p>
      <h1 class="ve-h"><?= v2_te('Embed widgets') ?></h1>
      <p class="ve-lead"><?= v2_te('Booking for your venue, right on your own website. Visitors choose the day and the tickets, fill in their details and pay without leaving your site.') ?></p>
    </div>
  </header>

  <p class="ve-state" id="wg-loading"><?= v2_te('Loading…') ?></p>

  <div class="org-empty" id="wg-off" hidden>
    <span class="org-empty-ic"><?= v2_ic('code') ?></span>
    <b><?= v2_te('Widgets are not enabled for your account') ?></b>
    <p><?= v2_te('We enable them for you. Open a support ticket and we get back to you as soon as we can.') ?></p>
    <a class="btn btn-primary" href="/organizator/suport"><?= v2_te('Open a ticket') ?></a>
  </div>

  <div class="am-stack" id="wg-on" hidden>
    <section class="org-panel" aria-labelledby="wg-dom-h">
      <div class="org-panel-head"><div>
        <h2 class="org-panel-h" id="wg-dom-h"><?= v2_te('1. Your sites') ?></h2>
        <p class="org-panel-p"><?= v2_te('The widget opens only on the sites in the list. Enter the address, for example my-site.com (the www version works automatically) or *.my-site.com for all subdomains.') ?></p>
      </div></div>
      <ul class="wg-domains" id="wg-domains" aria-live="polite"></ul>
      <form class="wg-add" id="wg-dom-form" novalidate>
        <span class="po-field"><label for="wg-dom-in"><?= v2_te('Site address') ?></label><input class="po-input" id="wg-dom-in" type="text" inputmode="url" autocomplete="off" spellcheck="false" placeholder="<?= v2_te('my-site.com') ?>" aria-describedby="wg-dom-err"></span>
        <button class="btn btn-primary" type="submit" id="wg-dom-go"><?= v2_ic('plus') ?><?= v2_te('Add') ?></button>
      </form>
      <p class="wg-err" id="wg-dom-err" role="alert" hidden></p>
      <p class="wg-status" id="wg-dom-status" role="status" aria-live="polite" hidden></p>
    </section>

    <section class="org-panel" aria-labelledby="wg-what-h">
      <div class="org-panel-head"><div>
        <h2 class="org-panel-h" id="wg-what-h"><?= v2_te('2. What you sell in the widget') ?></h2>
        <p class="org-panel-p"><?= v2_te('All the tickets of a venue or a single product. For an experience that needs an access ticket, the access tickets appear too.') ?></p>
      </div></div>
      <div class="wg-pick">
        <span class="po-field"><label for="wg-loc"><?= v2_te('Venue') ?></label><span class="po-select"><select id="wg-loc"></select><?= v2_ic('caret-down') ?></span></span>
        <span class="po-field"><label for="wg-prod"><?= v2_te('Product') ?></label><span class="po-select"><select id="wg-prod"><option value=""><?= v2_te('All the tickets of the venue') ?></option></select><?= v2_ic('caret-down') ?></span></span>
      </div>
      <p class="wg-note" id="wg-loc-note" hidden></p>
    </section>

    <section class="org-panel" aria-labelledby="wg-code-h" id="wg-code-box">
      <div class="org-panel-head"><div>
        <h2 class="org-panel-h" id="wg-code-h"><?= v2_te('3. The code for your site') ?></h2>
        <p class="org-panel-p"><?= v2_te('Paste the code into the page of your site, where you want the booking to appear. The height adjusts by itself. The customer fills in their details and pays right in the widget, on your site: they leave it only for the card page of the payment processor and then come back to your page, where they see the confirmation and the tickets.') ?></p>
        <p class="org-panel-p"><?= v2_te('Did you paste an older version of the code? Replace it with this one: with the old one, the payment opens in a new tab on viaqui.com.') ?></p>
        <p class="org-panel-p"><?= v2_t('If your site has a security policy (Content-Security-Policy), allow <code>frame-src {site}</code>, <code>script-src {site}</code> and, for the payment, <code>form-action {pay}</code> in it, otherwise the browser blocks the widget or the payment step.', ['site' => v2_e(rtrim(SITE_URL, '/')), 'pay' => 'https://secure.mobilpay.ro https://secure.netopia-payments.com']) ?></p>
        <p class="org-panel-p"><?= v2_t('For your pixel: after a successful payment, the page receives the event <code>{event}</code>, and the Meta Pixel and the Google tag on the page record the purchase automatically.', ['event' => 'bileteonline:purchase']) ?></p>
      </div></div>
      <label class="sr" for="wg-code"><?= v2_te('Widget code') ?></label>
      <textarea class="po-input wg-code" id="wg-code" rows="7" readonly spellcheck="false"></textarea>
      <div class="wg-tools"><button class="btn btn-primary" type="button" data-copy="wg-code"><?= v2_ic('copy') ?><span><?= v2_te('Copy the code') ?></span></button></div>

      <h3 class="wg-sub"><?= v2_te('Just a button') ?></h3>
      <p class="org-panel-p"><?= v2_te('If your site does not accept iframe code, add a button that opens your page on viaqui.com.') ?></p>
      <label class="sr" for="wg-link"><?= v2_te('Button code') ?></label>
      <textarea class="po-input wg-code" id="wg-link" rows="3" readonly spellcheck="false"></textarea>
      <div class="wg-tools"><button class="btn btn-ghost" type="button" data-copy="wg-link"><?= v2_ic('copy') ?><span><?= v2_te('Copy the button') ?></span></button></div>
    </section>

    <section class="org-panel" aria-labelledby="wg-prev-h" id="wg-prev-box">
      <div class="org-panel-head"><div>
        <h2 class="org-panel-h" id="wg-prev-h"><?= v2_te('Preview') ?></h2>
        <p class="org-panel-p"><?= v2_te('This is how the widget looks on your site. You can try a booking; the payment opens in a new tab.') ?></p>
      </div></div>
      <div class="wg-frame"><iframe id="wg-preview" title="<?= v2_te('Widget preview') ?>" loading="lazy"></iframe></div>
    </section>
  </div>
</div>
<?php
v2_org_end();
include __DIR__ . '/../includes/v2/foot.php';
