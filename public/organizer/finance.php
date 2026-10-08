<?php
/**
 * The operator's balance: /organizator/sold (activities module), v2 design.
 *
 * viaqui.com does not do payouts, and its operators have no events — so this page is not the Ambilet balance sheet
 * any more. It explains where the money is and shows it, all from the activities module:
 * - the model, in the lead: the commission is added on top of the operator's prices, the online money is collected by
 *   viaqui.com, and the commission on desk takings is invoiced once a month;
 * - four figures for the chosen period (this month by default): taken online, taken at the desk, the viaqui.com
 *   commission (and how much of it comes from the desk) and what the operator keeps;
 * - what is owed both ways: the commission on desk sales (the invoice that follows, with its due days) and what the
 *   operator is owed from the online sales, with one plain sentence saying split payment is not live yet;
 * - the last thirteen months, online and desk side by side, with a total row and a CSV built in the browser.
 *
 * Everything comes from one call, /organizer/activities-module/summary (totals, by_source.period, by_month, all_time);
 * /organizer/contract adds the commission rate and invoice_due_days (the shell's /organizer/me carries the rate too).
 * Removed with the rebuild: payout requests and their dialog, the per-event balances with their filters and search,
 * the account-wide payout cards and the payout bank-account picker — none of them exist on viaqui.com.
 */

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/api.php';
require_once __DIR__ . '/../includes/nav-helpers.php';
require_once __DIR__ . '/../includes/v2/helpers.php';
require_once __DIR__ . '/../includes/v2/organizer.php';

$pageTitle = v2_t('Balance and takings');
$pageDescription = v2_t('What you took online and at the desk, the Viaqui commission and what you keep, by period and month by month.');
$canonicalUrl = SITE_URL . '/organizator/sold';
$noindex = true;
$skipPageCache = true;

$v2Styles = ['organizer.css', 'org-venue.css', 'org-am.css', 'org-finance.css'];
$v2Scripts = ['organizer.js', 'org-finance.js'];
$v2LegacyScripts = ['assets/js/config.js', 'assets/js/utils.js', 'assets/js/api.js', 'assets/js/auth.js'];
$v2HeadExtra = v2_account_client_config('organizer');

/** One period figure: the value lands in #of-c-{key}, the sentence under it in #of-c-{key}-p. */
$ofCard = function (string $key, string $cls, string $label, string $help) {
    return '<article class="of-card ' . $cls . '">'
        . '<p class="of-card-k">' . $label . '</p>'
        . '<p class="of-card-v" id="of-c-' . $key . '"><span class="org-skel of-sk"></span></p>'
        . '<p class="of-card-p" id="of-c-' . $key . '-p">' . $help . '</p>'
        . '</article>';
};

include __DIR__ . '/../includes/v2/head.php';
v2_org_start('finance');
?>
<div class="ve am of" id="of">
  <header class="ve-head">
    <div>
      <p class="ve-eyebrow"><?= v2_ic('wallet') ?><?= v2_te('Money') ?></p>
      <h1 class="ve-h"><?= v2_te('Balance and takings') ?></h1>
      <p class="ve-lead" id="of-lead"><?= v2_te('The Viaqui commission is added on top of your prices, so you keep your full price on every ticket. The money from online sales is collected through Viaqui and is owed to you, and for tickets sold at the desk we send you a monthly invoice for the commission.') ?></p>
    </div>
  </header>

  <p class="of-model" id="of-model" hidden><?= v2_ic('percent') ?><span id="of-model-t"></span></p>

  <form class="am-filters of-filters" id="of-form">
    <div class="fchips" id="of-presets" role="group" aria-label="<?= v2_te('Period') ?>">
      <button class="fchip" type="button" data-preset="today"><?= v2_te('Today') ?></button>
      <button class="fchip" type="button" data-preset="7"><?= v2_te('7 days') ?></button>
      <button class="fchip" type="button" data-preset="30"><?= v2_te('30 days') ?></button>
      <button class="fchip" type="button" data-preset="month"><?= v2_te('This month') ?></button>
      <button class="fchip" type="button" data-preset="last-month"><?= v2_te('Last month') ?></button>
      <button class="fchip" type="button" data-preset="year"><?= v2_te('This year') ?></button>
    </div>
    <span class="po-field"><label for="of-from"><?= v2_te('From') ?></label><input class="po-input" type="date" id="of-from"></span>
    <span class="po-field"><label for="of-to"><?= v2_te('To') ?></label><input class="po-input" type="date" id="of-to"></span>
    <span class="po-field"><label for="of-loc"><?= v2_te('Venue') ?></label><span class="po-select"><select id="of-loc"><option value=""><?= v2_te('All venues') ?></option></select><?= v2_ic('caret-down') ?></span></span>
    <button class="btn btn-primary" type="submit"><?= v2_te('Show') ?></button>
  </form>

  <p class="sr" id="of-live" aria-live="polite"></p>

  <div class="of-body" id="of-body">
    <section class="of-cards" aria-labelledby="of-cards-h">
      <h2 class="sr" id="of-cards-h"><?= v2_te('Chosen period') ?></h2>
      <?= $ofCard('online', 'is-deep', v2_te('Taken online'), v2_te('Sales through Viaqui, at your prices.')) ?>
      <?= $ofCard('pos', 'is-mint', v2_te('Taken at the desk'), v2_te('Sales at the desk, which you collect directly from the customer.')) ?>
      <?= $ofCard('com', 'is-warm', v2_te('Viaqui commission'), v2_te('The actual commission of the period, as we calculated it for each booking.')) ?>
      <?= $ofCard('net', '', v2_te('You keep'), v2_te('What stays with you from the sales of the period.')) ?>
    </section>

    <section class="org-panel of-due" aria-labelledby="of-due-h">
      <div class="org-panel-head">
        <div>
          <p class="org-k"><?= v2_te('Chosen period') ?></p>
          <h2 class="org-panel-h" id="of-due-h"><?= v2_te('To pay and to receive') ?></h2>
          <p class="org-panel-p" id="of-period"><?= v2_te('For the period above.') ?></p>
        </div>
      </div>
      <div class="of-due-grid">
        <article class="of-due-c is-warm">
          <p class="of-due-k"><?= v2_te('Commission to pay to Viaqui') ?></p>
          <p class="of-due-v" id="of-d-pos"><span class="org-skel of-sk"></span></p>
          <p class="of-due-p" id="of-d-pos-p"><?= v2_te('The commission for tickets sold at the desk. We invoice it once a month.') ?></p>
          <a class="of-due-l" href="/organizator/facturare"><?= v2_te('Your invoices') ?><?= v2_ic('arrow-right') ?></a>
        </article>
        <article class="of-due-c is-mint">
          <p class="of-due-k"><?= v2_te('To receive from online sales') ?></p>
          <p class="of-due-v" id="of-d-online"><span class="org-skel of-sk"></span></p>
          <p class="of-due-p" id="of-d-online-p"><?= v2_te('The money collected online by Viaqui that is owed to you.') ?></p>
          <a class="of-due-l" href="/organizator/setari#bank"><?= v2_te('Bank account') ?><?= v2_ic('arrow-right') ?></a>
        </article>
      </div>
      <p class="of-note" id="of-bank-note" hidden></p>
    </section>

    <section class="org-panel of-months" aria-labelledby="of-m-h">
      <div class="org-panel-head">
        <div>
          <p class="org-k"><?= v2_te('History') ?></p>
          <h2 class="org-panel-h" id="of-m-h"><?= v2_te('Month by month') ?></h2>
          <p class="org-panel-p"><?= v2_te('The last 13 months, by payment date. The period chosen above does not change the table, but the venue does.') ?></p>
        </div>
        <button class="btn btn-ghost" type="button" id="of-csv" disabled><?= v2_ic('file-csv') ?><?= v2_te('Download CSV') ?></button>
      </div>
      <div class="ve-table-wrap"><table class="ve-table am-table of-table">
        <thead><tr>
          <th scope="col"><?= v2_te('Month') ?></th>
          <th scope="col"><?= v2_te('Taken online') ?></th>
          <th scope="col"><?= v2_te('Taken at the desk') ?></th>
          <th scope="col"><?= v2_te('Viaqui commission') ?></th>
          <th scope="col"><?= v2_te('You keep') ?></th>
        </tr></thead>
        <tbody id="of-months"><tr><td colspan="5" class="ve-state"><?= v2_te('Loading…') ?></td></tr></tbody>
        <tfoot id="of-months-foot" hidden><tr>
          <td><?= v2_te('Total') ?></td>
          <td id="of-t-online">—</td>
          <td id="of-t-pos">—</td>
          <td id="of-t-com">—</td>
          <td id="of-t-net">—</td>
        </tr></tfoot>
      </table></div>
    </section>
  </div>

  <div class="org-empty of-empty" id="of-empty" hidden></div>
</div>
<?php
v2_org_end();
include __DIR__ . '/../includes/v2/foot.php';
