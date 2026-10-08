<?php
/**
 * Organizer service order: /organizator/services/{uuid} (service-order-detail.php?uuid=), v2 design.
 *
 * Inside the v2 organizer shell. Every section of the old page, restyled: breadcrumb and back link, the order (type,
 * number, status, activity, details, created, period), the payment (subtotal, VAT, total, method, payment status, paid
 * at), the e-mail campaign figures (sent, opened, clicks, failed, unsubscribed, open and click rates, audience, filters,
 * campaign status and dates), the pixel IDs of a paid ad tracking order (editable), the order configuration and the
 * "order not found" state. org-service-order-detail.js reads /organizer/services/orders/{uuid} and saves the pixel IDs.
 *
 * Fixed on the way: any failure (network, server) said the order did not exist: only a missing order says so, the rest
 * can be retried; the filter tags and pixel list were HTML strings; amounts were "123,00 RON" typed by hand, dates
 * without the Bucharest timezone; pixel IDs longer than core accepts (50) failed with a bare "Eroare".
 */

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/api.php';
require_once __DIR__ . '/../includes/nav-helpers.php';
require_once __DIR__ . '/../includes/v2/helpers.php';
require_once __DIR__ . '/../includes/v2/organizer.php';

$sdUuid = (string) ($_GET['uuid'] ?? '');
if (!preg_match('/^[A-Za-z0-9-]{8,64}$/', $sdUuid)) {
    $sdUuid = '';
}

$pageTitleRaw = v2_t('Service order') . ' · ' . SITE_NAME;
$pageDescription = v2_t('The details of an extra service order of an operator on Viaqui.');
$canonicalUrl = SITE_URL . ($sdUuid !== '' ? '/organizator/services/' . $sdUuid : '/organizator/servicii/comenzi');
$noindex = true;
$skipPageCache = true;

$v2Styles = ['organizer.css', 'org-service-order-detail.css'];
$v2Scripts = ['organizer.js', 'org-service-order-detail.js'];
$v2LegacyScripts = ['assets/js/config.js', 'assets/js/utils.js', 'assets/js/api.js', 'assets/js/auth.js'];
$v2HeadExtra = v2_account_client_config('organizer');

$sdFact = function (string $key, string $label) {
    return '<div class="sd-fact"><dt id="sd-' . $key . '-l">' . $label . '</dt><dd id="sd-' . $key . '">—</dd></div>';
};
$sdCount = function (string $key, string $label, string $tone) {
    return '<div class="sd-count is-' . $tone . '"><b id="sd-n-' . $key . '">0</b><span>' . $label . '</span></div>';
};

include __DIR__ . '/../includes/v2/head.php';
v2_org_start('services');
?>
<div class="sd" id="sd" data-uuid="<?= htmlspecialchars($sdUuid, ENT_QUOTES, 'UTF-8') ?>">
  <header class="sd-head">
    <div>
      <nav class="sd-crumbs" aria-label="<?= v2_te('Breadcrumb') ?>"><a href="/organizator/servicii"><?= v2_te('Extra services') ?></a><span aria-hidden="true">›</span><a href="/organizator/servicii/comenzi"><?= v2_te('My orders') ?></a><span aria-hidden="true">›</span><span aria-current="page" id="sd-crumb"><?= v2_te('Order') ?></span></nav>
      <h1 class="sd-h" id="sd-title"><?= v2_te('Order details') ?></h1>
    </div>
    <a class="btn btn-ghost" href="/organizator/servicii/comenzi"><?= v2_ic('arrow-left') ?><?= v2_te('Back') ?></a>
  </header>

  <div class="sd-loading" id="sd-loading" role="status">
    <span class="org-skel sd-sk-a"></span><span class="org-skel sd-sk-b"></span>
    <span class="sd-sr"><?= v2_te('Loading the order…') ?></span>
  </div>

  <div class="sd-content" id="sd-content" hidden>
    <div class="sd-top">
      <section class="org-panel sd-order" aria-labelledby="sd-type">
        <div class="sd-order-head">
          <span class="sd-type-ic" id="sd-type-ic"></span>
          <div class="sd-order-id">
            <h2 id="sd-type"><?= v2_te('Service') ?></h2>
            <p id="sd-number"></p>
          </div>
          <span class="org-tag" id="sd-status"></span>
        </div>
        <dl class="sd-facts">
          <?= $sdFact('event', v2_te('Experience')) ?>
          <?= $sdFact('details', v2_te('Details')) ?>
          <?= $sdFact('created', v2_te('Created on')) ?>
          <?= $sdFact('period', v2_te('Period')) ?>
        </dl>
      </section>

      <section class="org-panel sd-pay" aria-labelledby="sd-pay-h">
        <h2 class="sd-card-h" id="sd-pay-h"><?= v2_ic('credit-card') ?><?= v2_te('Payment') ?></h2>
        <dl class="sd-sums">
          <div><dt><?= v2_te('Subtotal') ?></dt><dd id="sd-subtotal">—</dd></div>
          <div><dt><?= v2_te('VAT') ?></dt><dd id="sd-tax">—</dd></div>
          <div class="sd-total"><dt><?= v2_te('Total') ?></dt><dd id="sd-total">—</dd></div>
        </dl>
        <dl class="sd-sums is-small">
          <div><dt><?= v2_te('Method') ?></dt><dd id="sd-method">—</dd></div>
          <div><dt><?= v2_te('Payment status') ?></dt><dd id="sd-paystatus">—</dd></div>
          <div><dt><?= v2_te('Paid on') ?></dt><dd id="sd-paidat">—</dd></div>
        </dl>
      </section>
    </div>

    <section class="org-panel sd-email" id="sd-email" aria-labelledby="sd-email-h" hidden>
      <div class="org-panel-head"><h2 class="org-panel-h" id="sd-email-h"><?= v2_te('Email campaign statistics') ?></h2></div>
      <div class="sd-counts">
        <?= $sdCount('sent', v2_te('Sent'), 'info') ?>
        <?= $sdCount('opened', v2_te('Opened'), 'ok') ?>
        <?= $sdCount('clicked', v2_te('Clicks'), 'info') ?>
        <?= $sdCount('failed', v2_te('Failed'), 'bad') ?>
        <?= $sdCount('unsub', v2_te('Unsubscribes'), 'wait') ?>
      </div>
      <div class="sd-rates">
        <div class="sd-rate is-ok"><p><span><?= v2_te('Open rate') ?></span><b id="sd-open-rate">0%</b></p><span class="sd-bar"><span id="sd-open-bar"></span></span></div>
        <div class="sd-rate is-info"><p><span><?= v2_te('Click rate (of opened)') ?></span><b id="sd-click-rate">0%</b></p><span class="sd-bar"><span id="sd-click-bar"></span></span></div>
      </div>
      <div class="sd-block">
        <h3 class="sd-block-h"><?= v2_te('Audience') ?></h3>
        <dl class="sd-facts is-four">
          <?= $sdFact('aud-type', v2_te('Audience type')) ?>
          <?= $sdFact('aud-perfect', v2_te('Perfect match')) ?>
          <?= $sdFact('aud-partial', v2_te('Partial match')) ?>
          <?= $sdFact('aud-template', v2_te('Template')) ?>
        </dl>
        <div class="sd-filters" id="sd-filters" hidden>
          <p><?= v2_te('Filters applied') ?></p>
          <ul id="sd-filter-tags"></ul>
        </div>
      </div>
      <div class="sd-block sd-nl">
        <p><span><?= v2_te('Campaign status:') ?></span> <span class="org-tag" id="sd-nl-status" hidden></span></p>
        <p class="sd-nl-dates" id="sd-nl-dates"></p>
      </div>
    </section>

    <section class="org-panel sd-px" id="sd-px" aria-labelledby="sd-px-h" hidden>
      <div class="sd-px-head">
        <span class="sd-type-ic is-tracking"><?= v2_ic('target') ?></span>
        <div>
          <h2 class="sd-card-h" id="sd-px-h"><?= v2_te('Pixel IDs') ?></h2>
          <p><?= v2_te('Add or edit the pixel IDs for the platforms you bought. Tracking starts working automatically as soon as a Pixel ID is filled in.') ?></p>
        </div>
      </div>
      <form id="sd-px-form" novalidate>
        <div class="sd-px-list" id="sd-px-list"></div>
        <div class="sd-px-foot">
          <p id="sd-px-msg" role="status"></p>
          <button class="btn btn-primary" type="submit" id="sd-px-save"><?= v2_te('Save IDs') ?></button>
        </div>
      </form>
    </section>

    <details class="org-panel sd-config">
      <summary><?= v2_ic('code') ?><?= v2_te('Order configuration') ?><?= v2_ic('caret-down') ?></summary>
      <pre id="sd-config"></pre>
    </details>
  </div>

  <div class="org-empty" id="sd-missing" hidden>
    <span class="org-empty-ic"><?= v2_ic('receipt') ?></span>
    <b><?= v2_te('The order was not found') ?></b>
    <p><?= v2_te('Check the link or go back to the list of services.') ?></p>
    <a class="btn btn-primary" href="/organizator/servicii"><?= v2_te('Back to services') ?></a>
  </div>

  <div class="org-empty is-error" id="sd-failed" hidden>
    <span class="org-empty-ic"><?= v2_ic('warning-circle') ?></span>
    <b><?= v2_te('We could not load the order') ?></b>
    <p><?= v2_te('There was a connection problem. Try again.') ?></p>
    <button class="btn btn-primary" type="button" id="sd-retry"><?= v2_te('Try again') ?></button>
  </div>
</div>
<?php
v2_org_end();
include __DIR__ . '/../includes/v2/foot.php';
