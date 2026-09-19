<?php
/**
 * Organizer balance: /organizator/sold (v2 design).
 *
 * Inside the v2 organizer shell. Four figures for the whole account (total sales after discounts, available balance,
 * payouts in progress, paid out), each opening the activities or deconts behind it; the balance of every activity
 * (gross revenue, commission, discounts, net revenue, paid out, in progress, available) with how much was received, a
 * payout request and details: payments (with the organizer's own requests, which can be cancelled while they wait),
 * transactions and discounts; filters Toate / Active / Încheiate and a search; the payout request dialog.
 * org-finance.js reads /organizer/finance, /organizer/balance (minimum payout), /organizer/bank-accounts and POSTs /
 * DELETEs /organizer/payouts.
 *
 * Fixed on the way (the Ambilet page already had the balance fixes; this copy was older):
 * - the cards added up each activity's balance clamped at 0, so an over-paid activity inflated the total: they show
 *   core's reconciled account-wide figures, and an over-paid activity shows what is owed back;
 * - the bank account in the payout dialog always read "Cont - ****" (core sends bank, not bank_name);
 * - the minimum payout was fixed at 100 lei, core reads it from the marketplace settings;
 * - an organizer's own request (status pending) never appeared under the activity until approved, and could not be
 *   cancelled; core's English answers came through as they were.
 */

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/api.php';
require_once __DIR__ . '/../includes/nav-helpers.php';
require_once __DIR__ . '/../includes/v2/helpers.php';
require_once __DIR__ . '/../includes/v2/organizer.php';

$pageTitleRaw = 'Sold și finanțe — ' . SITE_NAME;
$pageDescription = 'Soldul tău pe bilete.online: vânzări, deconturi, plăți și soldul fiecărei activități.';
$canonicalUrl = SITE_URL . '/organizator/sold';
$noindex = true;
$skipPageCache = true;

$v2Styles = ['organizer.css', 'org-finance.css'];
$v2Scripts = ['organizer.js', 'org-finance.js'];
$v2LegacyScripts = ['assets/js/config.js', 'assets/js/utils.js', 'assets/js/api.js', 'assets/js/auth.js'];
$v2HeadExtra = v2_account_client_config('organizer');

$ofCard = function (string $key, string $cls, string $label, string $help, string $toggle) {
    return '<article class="of-card ' . $cls . '">'
        . '<p class="of-card-k">' . $label . '</p>'
        . '<p class="of-card-v" id="of-c-' . $key . '"><span class="org-skel of-sk"></span></p>'
        . '<p class="of-card-p" id="of-c-' . $key . '-p">' . $help . '</p>'
        . '<button class="of-bd-t" type="button" data-bd="' . $key . '" aria-expanded="false" aria-controls="of-bd" disabled>' . $toggle . v2_ic('caret-down') . '</button>'
        . '</article>';
};

include __DIR__ . '/../includes/v2/head.php';
v2_org_start('finance');
?>
<div class="of" id="of">
  <header class="of-head">
    <div>
      <p class="org-k">Bani</p>
      <h1 class="of-h">Sold și finanțe</h1>
      <p class="of-lead">Gestionează balanța și plățile tale, pe activitate.</p>
    </div>
  </header>

  <div class="of-notice" id="of-notice" role="note" hidden><?= v2_ic('info') ?><p id="of-notice-t"></p></div>

  <section class="of-cards" aria-labelledby="of-cards-h">
    <h2 class="sr" id="of-cards-h">Soldul contului</h2>
    <?= $ofCard('sales', 'is-deep', 'Total vânzări', 'Tot ce ai câștigat din bilete, după reduceri.', 'Din ce activități') ?>
    <?= $ofCard('available', 'is-mint', 'Sold disponibil', 'Bani din bilete pentru care nu s-a emis încă un decont.', 'Din ce activități') ?>
    <?= $ofCard('pending', 'is-warm', 'În procesare', 'Deconturi aprobate, în curs de plată către tine.', 'Din ce deconturi') ?>
    <?= $ofCard('paid', '', 'Total încasat', 'Suma deconturilor deja plătite către tine.', 'Din ce deconturi') ?>
  </section>

  <section class="org-panel of-bd" id="of-bd" aria-labelledby="of-bd-h" hidden>
    <div class="of-bd-head">
      <div><h2 class="org-panel-h" id="of-bd-h" tabindex="-1"></h2><p class="org-panel-p" id="of-bd-p"></p></div>
      <button class="of-x" type="button" id="of-bd-x" aria-label="Închide detaliile"><?= v2_ic('x') ?></button>
    </div>
    <ul class="of-bd-list" id="of-bd-list"></ul>
    <p class="of-bd-total" id="of-bd-total" hidden></p>
  </section>

  <section class="org-panel of-events" aria-labelledby="of-ev-h">
    <div class="org-panel-head">
      <div><p class="org-k">Pe activități</p><h2 class="org-panel-h" id="of-ev-h">Sold per activitate</h2><p class="org-panel-p">Cât s-a încasat, cât ți se cuvine și cât ai primit deja, pentru fiecare activitate.</p></div>
    </div>
    <div class="of-filters">
      <div class="of-seg" role="group" aria-label="Arată activitățile">
        <button class="of-seg-b" type="button" data-stare="" aria-pressed="true">Toate <b data-n=""></b></button>
        <button class="of-seg-b" type="button" data-stare="active" aria-pressed="false">Active <b data-n="active"></b></button>
        <button class="of-seg-b" type="button" data-stare="incheiate" aria-pressed="false">Încheiate <b data-n="incheiate"></b></button>
      </div>
      <label class="of-search"><span class="sr">Caută o activitate</span><?= v2_ic('magnifying-glass') ?><input type="search" id="of-q" maxlength="100" autocomplete="off" spellcheck="false" placeholder="Caută o activitate…"></label>
    </div>
    <p class="sr" id="of-live" aria-live="polite"></p>
    <ul class="of-list" id="of-list">
      <?php for ($i = 0; $i < 3; $i++): ?><li class="of-ev is-skel" aria-hidden="true"><span class="org-skel of-sk-card"></span></li><?php endfor; ?>
    </ul>
    <div class="org-empty of-empty" id="of-empty" hidden></div>
  </section>

  <dialog class="of-dialog" id="of-pay-d" aria-labelledby="of-pay-h">
    <form class="of-d-inner" id="of-pay-form" novalidate>
      <div class="of-d-head">
        <h2 class="of-d-h" id="of-pay-h">Solicită plata</h2>
        <button class="of-x" type="button" data-close aria-label="Închide"><?= v2_ic('x') ?></button>
      </div>
      <div class="of-pay-ev"><p class="of-d-k">Activitate</p><p class="of-pay-name" id="of-pay-name"></p></div>
      <div class="of-pay-avail"><p class="of-d-k">Suma disponibilă</p><p class="of-pay-sum" id="of-pay-avail"></p></div>
      <label class="of-f">
        <span class="of-f-l">Suma de retras</span>
        <span class="of-amount"><input id="of-pay-amount" type="text" inputmode="decimal" autocomplete="off" aria-describedby="of-pay-hint of-pay-amount-err"><span aria-hidden="true">lei</span></span>
        <span class="of-row"><span class="of-help" id="of-pay-hint"></span><button class="of-link" type="button" id="of-pay-all">Toată suma</button></span>
        <span class="of-err" id="of-pay-amount-err" hidden></span>
      </label>
      <label class="of-f">
        <span class="of-f-l">Cont bancar</span>
        <span class="of-select"><select id="of-pay-account" aria-describedby="of-pay-account-err"><option value="">Se încarcă conturile…</option></select><?= v2_ic('caret-down') ?></span>
        <span class="of-help"><a href="/organizator/setari#bank">Gestionează conturile bancare</a></span>
        <span class="of-err" id="of-pay-account-err" hidden></span>
      </label>
      <label class="of-f">
        <span class="of-f-l">Note (opțional)</span>
        <textarea id="of-pay-notes" rows="3" maxlength="500" placeholder="Adaugă note sau detalii…" aria-describedby="of-pay-notes-n"></textarea>
        <span class="of-help of-count" id="of-pay-notes-n">0 / 500</span>
      </label>
      <div class="of-form-err" id="of-pay-err" role="alert" hidden></div>
      <div class="of-d-act">
        <button class="btn btn-ghost" type="button" data-close>Anulează</button>
        <button class="btn btn-primary" type="submit" id="of-pay-go"><span data-label>Solicită plata</span></button>
      </div>
    </form>
  </dialog>

  <dialog class="of-dialog is-small" id="of-cancel-d" aria-labelledby="of-cancel-h">
    <div class="of-d-inner">
      <h2 class="of-d-h" id="of-cancel-h">Anulezi cererea de plată?</h2>
      <p class="of-d-p" id="of-cancel-p"></p>
      <div class="of-d-act">
        <button class="btn btn-ghost" type="button" data-close>Renunță</button>
        <button class="btn of-danger" type="button" id="of-cancel-ok">Anulează cererea</button>
      </div>
    </div>
  </dialog>
</div>
<?php
v2_org_end();
include __DIR__ . '/../includes/v2/foot.php';
