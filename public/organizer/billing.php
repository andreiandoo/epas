<?php
/**
 * Organizer billing: /organizator/facturare (billing.php), v2 design.
 *
 * Inside the v2 organizer shell. Every section of the old page, restyled: export, the invoices / payouts toggle, the
 * invoice history (status filter, table, pagination), the payout history (status filter, table), the billing details
 * with the edit link, and the detail window for an invoice (issuer, client, lines, totals, status, dates) or a payout.
 * org-billing.js reads /organizer/invoices, /organizer/payouts and /organizer/billing-info through the proxy.
 *
 * Fixed on the way:
 * - the PDF buttons opened /organizer/invoices/{id}/pdf, which core doesn't have (404): the invoice is printed or saved
 *   as PDF from its detail window instead;
 * - export and PDF put the session token in the URL of a new window: the CSV is fetched with the token in a header and
 *   checked before saving (an expired session returns the sign-in page, never saved as a CSV);
 * - errors came as browser alerts; the payouts tab can be opened directly (#deconturi).
 */

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/api.php';
require_once __DIR__ . '/../includes/nav-helpers.php';
require_once __DIR__ . '/../includes/v2/helpers.php';
require_once __DIR__ . '/../includes/v2/organizer.php';

$pageTitle = v2_t('Invoices and payouts');
$pageDescription = v2_t('The invoices, payouts and billing details of an operator on Viaqui.');
$canonicalUrl = SITE_URL . '/organizator/facturare';
$noindex = true;
$skipPageCache = true;

$v2Styles = ['organizer.css', 'org-billing.css'];
$v2Scripts = ['organizer.js', 'org-billing.js'];
$v2LegacyScripts = ['assets/js/config.js', 'assets/js/utils.js', 'assets/js/api.js', 'assets/js/auth.js'];
$v2HeadExtra = v2_account_client_config('organizer');

$obSeg = function (string $group, array $items) {
    $out = '<div class="ob-seg" role="group" aria-label="' . v2_te('Filter by status') . '">';
    foreach ($items as $i => [$value, $label]) {
        $out .= '<button type="button" data-' . $group . '="' . $value . '" aria-pressed="' . ($i === 0 ? 'true' : 'false') . '">' . $label . '</button>';
    }
    return $out . '</div>';
};
$obInfo = [['company', v2_t('Company name')], ['cui', v2_t('Tax ID')], ['reg', v2_t('Company registration no.')], ['address', v2_t('Address')], ['email', v2_t('Billing email')]];

include __DIR__ . '/../includes/v2/head.php';
v2_org_start('billing');
?>
<div class="ob" id="ob">
  <header class="ob-head">
    <div>
      <p class="org-k"><?= v2_te('Settings') ?></p>
      <h1 class="ob-h"><?= v2_te('Invoices and payouts') ?></h1>
      <p class="ob-lead"><?= v2_te('Manage your invoices, payouts and billing details.') ?></p>
    </div>
    <button class="btn btn-ghost" type="button" id="ob-export"><?= v2_ic('download-simple') ?><span data-label><?= v2_te('Export') ?></span></button>
  </header>

  <div class="ob-grid">
    <div class="ob-main">
      <div class="ob-tabs" role="tablist" aria-label="<?= v2_te('History') ?>">
        <button type="button" role="tab" id="ob-tab-invoices" aria-controls="ob-invoices" aria-selected="true"><?= v2_te('Invoice history') ?></button>
        <button type="button" role="tab" id="ob-tab-payouts" aria-controls="ob-payouts" aria-selected="false" tabindex="-1"><?= v2_te('Payout history') ?></button>
      </div>

      <section class="org-panel" id="ob-invoices" role="tabpanel" aria-labelledby="ob-tab-invoices">
        <div class="org-panel-head">
          <div><h2 class="org-panel-h"><?= v2_te('Invoice history') ?></h2><p class="org-panel-p" id="ob-inv-info"><?= v2_te('Showing {from}-{to} of {total}', ['from' => 0, 'to' => 0, 'total' => v2_num(0, 'invoice', 'invoices')]) ?></p></div>
          <?= $obSeg('inv', [['all', v2_te('All')], ['paid', v2_te('Paid')], ['pending', v2_te('Pending')]]) ?>
        </div>
        <div class="ob-table-wrap"><table class="ob-table">
          <thead><tr><th scope="col"><?= v2_te('Invoice no.') ?></th><th scope="col"><?= v2_te('Date') ?></th><th scope="col" class="ob-md"><?= v2_te('Description') ?></th><th scope="col"><?= v2_te('Amount') ?></th><th scope="col"><?= v2_te('Status') ?></th><th scope="col" class="ob-right"><?= v2_te('Actions') ?></th></tr></thead>
          <tbody id="ob-inv-rows"></tbody>
        </table></div>
        <div class="ob-state" id="ob-inv-state" aria-live="polite"><?= v2_te('Loading…') ?></div>
        <nav class="ob-pages" id="ob-inv-pages" aria-label="<?= v2_te('Invoice pages') ?>" hidden>
          <button class="ob-pill" type="button" id="ob-inv-prev"><?= v2_ic('arrow-left') ?><?= v2_te('Back') ?></button>
          <span id="ob-inv-page"></span>
          <button class="ob-pill" type="button" id="ob-inv-next"><?= v2_te('Next') ?><?= v2_ic('arrow-right') ?></button>
        </nav>
      </section>

      <section class="org-panel" id="ob-payouts" role="tabpanel" aria-labelledby="ob-tab-payouts" hidden>
        <div class="org-panel-head">
          <div><h2 class="org-panel-h"><?= v2_te('Payout history') ?></h2><p class="org-panel-p" id="ob-pay-info"></p></div>
          <?= $obSeg('pay', [['all', v2_te('All')], ['completed', v2_te('Completed')], ['pending', v2_te('Pending')]]) ?>
        </div>
        <div class="ob-table-wrap"><table class="ob-table">
          <thead><tr><th scope="col"><?= v2_te('Reference') ?></th><th scope="col"><?= v2_te('Date') ?></th><th scope="col"><?= v2_te('Experience') ?></th><th scope="col"><?= v2_te('Value') ?></th><th scope="col"><?= v2_te('Status') ?></th><th scope="col" class="ob-right"><?= v2_te('Actions') ?></th></tr></thead>
          <tbody id="ob-pay-rows"></tbody>
        </table></div>
        <div class="ob-state" id="ob-pay-state" aria-live="polite"><?= v2_te('Loading…') ?></div>
      </section>
    </div>

    <aside class="org-panel ob-info" aria-labelledby="ob-info-h">
      <div class="ob-info-head"><h2 class="org-panel-h" id="ob-info-h"><?= v2_te('Billing details') ?></h2><a class="ob-pill" href="/organizator/setari#company"><?= v2_ic('pencil-simple') ?><?= v2_te('Edit') ?></a></div>
      <dl class="ob-dl">
        <?php foreach ($obInfo as [$obKey, $obLabel]): ?><div><dt><?= v2_e($obLabel) ?></dt><dd id="ob-b-<?= $obKey ?>">—</dd></div><?php endforeach; ?>
      </dl>
    </aside>
  </div>

  <dialog class="ob-dialog" id="ob-d" aria-labelledby="ob-d-h">
    <div class="ob-d-inner">
      <div class="ob-d-head"><div><h2 class="ob-d-h" id="ob-d-h"><?= v2_te('Invoice details') ?></h2><p class="ob-d-p" id="ob-d-p">—</p></div><button class="ob-x" type="button" data-close aria-label="<?= v2_te('Close') ?>"><?= v2_ic('x') ?></button></div>
      <div id="ob-d-body" class="ob-d-body" aria-live="polite"></div>
      <div class="ob-d-act"><button class="btn btn-ghost" type="button" data-close><?= v2_te('Close') ?></button><button class="btn btn-primary" type="button" id="ob-print" hidden><?= v2_ic('download-simple') ?><?= v2_te('Print or save as PDF') ?></button></div>
    </div>
  </dialog>
</div>
<?php
v2_org_end();
include __DIR__ . '/../includes/v2/foot.php';
