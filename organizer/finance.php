<?php
/**
 * The operator's balance: /organizator/sold (activities module), v2 design.
 *
 * bilete.online does not do payouts, and its operators have no events — so this page is not the Ambilet balance sheet
 * any more. It explains where the money is and shows it, all from the activities module:
 * - the model, in the lead: the commission is added on top of the operator's prices, the online money is collected by
 *   bilete.online, and the commission on desk takings is invoiced once a month;
 * - four figures for the chosen period (this month by default): taken online, taken at the desk, the bilete.online
 *   commission (and how much of it comes from the desk) and what the operator keeps;
 * - what is owed both ways: the commission on desk sales (the invoice that follows, with its due days) and what the
 *   operator is owed from the online sales, with one plain sentence saying split payment is not live yet;
 * - the last thirteen months, online and desk side by side, with a total row and a CSV built in the browser.
 *
 * Everything comes from one call, /organizer/activities-module/summary (totals, by_source.period, by_month, all_time);
 * /organizer/contract adds the commission rate and invoice_due_days (the shell's /organizer/me carries the rate too).
 * Removed with the rebuild: payout requests and their dialog, the per-event balances with their filters and search,
 * the account-wide payout cards and the payout bank-account picker — none of them exist on bilete.online.
 */

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/api.php';
require_once __DIR__ . '/../includes/nav-helpers.php';
require_once __DIR__ . '/../includes/v2/helpers.php';
require_once __DIR__ . '/../includes/v2/organizer.php';

$pageTitleRaw = 'Sold și încasări — ' . SITE_NAME;
$pageDescription = 'Ce ai încasat online și la casă, comisionul bilete.online și cât îți rămâne, pe perioadă și lună de lună.';
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
      <p class="ve-eyebrow"><?= v2_ic('wallet') ?>Bani</p>
      <h1 class="ve-h">Sold și încasări</h1>
      <p class="ve-lead" id="of-lead">Comisionul bilete.online se adaugă peste prețurile tale, așa că din fiecare bilet îți rămâne prețul tău întreg. Banii din vânzările online se încasează prin bilete.online și ți se cuvin ție, iar pentru biletele vândute la casă îți trimitem lunar o factură cu comisionul.</p>
    </div>
  </header>

  <p class="of-model" id="of-model" hidden><?= v2_ic('percent') ?><span id="of-model-t"></span></p>

  <form class="am-filters of-filters" id="of-form">
    <div class="fchips" id="of-presets" role="group" aria-label="Perioada">
      <button class="fchip" type="button" data-preset="today">Azi</button>
      <button class="fchip" type="button" data-preset="7">7 zile</button>
      <button class="fchip" type="button" data-preset="30">30 de zile</button>
      <button class="fchip" type="button" data-preset="month">Luna aceasta</button>
      <button class="fchip" type="button" data-preset="last-month">Luna trecută</button>
      <button class="fchip" type="button" data-preset="year">Anul acesta</button>
    </div>
    <span class="po-field"><label for="of-from">De la</label><input class="po-input" type="date" id="of-from"></span>
    <span class="po-field"><label for="of-to">Până la</label><input class="po-input" type="date" id="of-to"></span>
    <span class="po-field"><label for="of-loc">Locația</label><span class="po-select"><select id="of-loc"><option value="">Toate locațiile</option></select><?= v2_ic('caret-down') ?></span></span>
    <button class="btn btn-primary" type="submit">Arată</button>
  </form>

  <p class="sr" id="of-live" aria-live="polite"></p>

  <div class="of-body" id="of-body">
    <section class="of-cards" aria-labelledby="of-cards-h">
      <h2 class="sr" id="of-cards-h">Perioada aleasă</h2>
      <?= $ofCard('online', 'is-deep', 'Încasat online', 'Vânzările prin bilete.online, la prețurile tale.') ?>
      <?= $ofCard('pos', 'is-mint', 'Încasat la casă', 'Vânzările de la casă, pe care le iei direct de la client.') ?>
      <?= $ofCard('com', 'is-warm', 'Comision bilete.online', 'Comisionul real al perioadei, așa cum l-am calculat la fiecare rezervare.') ?>
      <?= $ofCard('net', '', 'Îți rămâne', 'Ce rămâne la tine din vânzările perioadei.') ?>
    </section>

    <section class="org-panel of-due" aria-labelledby="of-due-h">
      <div class="org-panel-head">
        <div>
          <p class="org-k">Perioada aleasă</p>
          <h2 class="org-panel-h" id="of-due-h">De plată și de încasat</h2>
          <p class="org-panel-p" id="of-period">Pentru perioada de mai sus.</p>
        </div>
      </div>
      <div class="of-due-grid">
        <article class="of-due-c is-warm">
          <p class="of-due-k">Comision de plătit către bilete.online</p>
          <p class="of-due-v" id="of-d-pos"><span class="org-skel of-sk"></span></p>
          <p class="of-due-p" id="of-d-pos-p">Comisionul pentru biletele vândute la casă. Îl facturăm o dată pe lună.</p>
          <a class="of-due-l" href="/organizator/facturare">Facturile tale<?= v2_ic('arrow-right') ?></a>
        </article>
        <article class="of-due-c is-mint">
          <p class="of-due-k">De încasat din vânzările online</p>
          <p class="of-due-v" id="of-d-online"><span class="org-skel of-sk"></span></p>
          <p class="of-due-p" id="of-d-online-p">Banii strânși online de bilete.online care ți se cuvin ție.</p>
          <a class="of-due-l" href="/organizator/setari#bank">Contul bancar<?= v2_ic('arrow-right') ?></a>
        </article>
      </div>
      <p class="of-note" id="of-bank-note" hidden></p>
    </section>

    <section class="org-panel of-months" aria-labelledby="of-m-h">
      <div class="org-panel-head">
        <div>
          <p class="org-k">Istoric</p>
          <h2 class="org-panel-h" id="of-m-h">Lună de lună</h2>
          <p class="org-panel-p">Ultimele 13 luni, după data plății. Perioada aleasă mai sus nu schimbă tabelul, dar locația da.</p>
        </div>
        <button class="btn btn-ghost" type="button" id="of-csv" disabled><?= v2_ic('file-csv') ?>Descarcă CSV</button>
      </div>
      <div class="ve-table-wrap"><table class="ve-table am-table of-table">
        <thead><tr>
          <th scope="col">Luna</th>
          <th scope="col">Încasat online</th>
          <th scope="col">Încasat la casă</th>
          <th scope="col">Comision bilete.online</th>
          <th scope="col">Îți rămâne</th>
        </tr></thead>
        <tbody id="of-months"><tr><td colspan="5" class="ve-state">Se încarcă…</td></tr></tbody>
        <tfoot id="of-months-foot" hidden><tr>
          <td>Total</td>
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
