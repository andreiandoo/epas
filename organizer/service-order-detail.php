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

$pageTitleRaw = 'Comandă serviciu — ' . SITE_NAME;
$pageDescription = 'Detaliile unei comenzi de servicii extra a unui operator pe bilete.online.';
$canonicalUrl = SITE_URL . ($sdUuid !== '' ? '/organizator/services/' . $sdUuid : '/organizator/servicii/comenzi');
$noindex = true;
$skipPageCache = true;

$v2Styles = ['organizer.css', 'org-service-order-detail.css'];
$v2Scripts = ['organizer.js', 'org-service-order-detail.js'];
$v2LegacyScripts = ['assets/js/config.js', 'assets/js/utils.js', 'assets/js/api.js', 'assets/js/auth.js'];
$v2HeadExtra = v2_account_client_config('organizer');

$sdFact = function (string $key, string $label) {
    return '<div class="sd-fact"><dt>' . $label . '</dt><dd id="sd-' . $key . '">—</dd></div>';
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
      <nav class="sd-crumbs" aria-label="Breadcrumb"><a href="/organizator/servicii">Servicii extra</a><span aria-hidden="true">›</span><a href="/organizator/servicii/comenzi">Comenzile mele</a><span aria-hidden="true">›</span><span aria-current="page" id="sd-crumb">Comandă</span></nav>
      <h1 class="sd-h" id="sd-title">Detalii comandă</h1>
    </div>
    <a class="btn btn-ghost" href="/organizator/servicii/comenzi"><?= v2_ic('arrow-left') ?>Înapoi</a>
  </header>

  <div class="sd-loading" id="sd-loading" role="status">
    <span class="org-skel sd-sk-a"></span><span class="org-skel sd-sk-b"></span>
    <span class="sd-sr">Se încarcă comanda…</span>
  </div>

  <div class="sd-content" id="sd-content" hidden>
    <div class="sd-top">
      <section class="org-panel sd-order" aria-labelledby="sd-type">
        <div class="sd-order-head">
          <span class="sd-type-ic" id="sd-type-ic"></span>
          <div class="sd-order-id">
            <h2 id="sd-type">Serviciu</h2>
            <p id="sd-number"></p>
          </div>
          <span class="org-tag" id="sd-status"></span>
        </div>
        <dl class="sd-facts">
          <?= $sdFact('event', 'Activitate') ?>
          <?= $sdFact('details', 'Detalii') ?>
          <?= $sdFact('created', 'Creat la') ?>
          <?= $sdFact('period', 'Perioadă') ?>
        </dl>
      </section>

      <section class="org-panel sd-pay" aria-labelledby="sd-pay-h">
        <h2 class="sd-card-h" id="sd-pay-h"><?= v2_ic('credit-card') ?>Plată</h2>
        <dl class="sd-sums">
          <div><dt>Subtotal</dt><dd id="sd-subtotal">—</dd></div>
          <div><dt>TVA</dt><dd id="sd-tax">—</dd></div>
          <div class="sd-total"><dt>Total</dt><dd id="sd-total">—</dd></div>
        </dl>
        <dl class="sd-sums is-small">
          <div><dt>Metodă</dt><dd id="sd-method">—</dd></div>
          <div><dt>Status plată</dt><dd id="sd-paystatus">—</dd></div>
          <div><dt>Plătit la</dt><dd id="sd-paidat">—</dd></div>
        </dl>
      </section>
    </div>

    <section class="org-panel sd-email" id="sd-email" aria-labelledby="sd-email-h" hidden>
      <div class="org-panel-head"><h2 class="org-panel-h" id="sd-email-h">Statistici campanie email</h2></div>
      <div class="sd-counts">
        <?= $sdCount('sent', 'Trimise', 'info') ?>
        <?= $sdCount('opened', 'Deschise', 'ok') ?>
        <?= $sdCount('clicked', 'Click-uri', 'info') ?>
        <?= $sdCount('failed', 'Eșuate', 'bad') ?>
        <?= $sdCount('unsub', 'Dezabonări', 'wait') ?>
      </div>
      <div class="sd-rates">
        <div class="sd-rate is-ok"><p><span>Rata deschidere</span><b id="sd-open-rate">0%</b></p><span class="sd-bar"><span id="sd-open-bar"></span></span></div>
        <div class="sd-rate is-info"><p><span>Rata click (din deschise)</span><b id="sd-click-rate">0%</b></p><span class="sd-bar"><span id="sd-click-bar"></span></span></div>
      </div>
      <div class="sd-block">
        <h3 class="sd-block-h">Audiență</h3>
        <dl class="sd-facts is-four">
          <?= $sdFact('aud-type', 'Tip audiență') ?>
          <?= $sdFact('aud-perfect', 'Perfect match') ?>
          <?= $sdFact('aud-partial', 'Partial match') ?>
          <?= $sdFact('aud-template', 'Template') ?>
        </dl>
        <div class="sd-filters" id="sd-filters" hidden>
          <p>Filtre aplicate</p>
          <ul id="sd-filter-tags"></ul>
        </div>
      </div>
      <div class="sd-block sd-nl">
        <p><span>Status campanie:</span> <span class="org-tag" id="sd-nl-status" hidden></span></p>
        <p class="sd-nl-dates" id="sd-nl-dates"></p>
      </div>
    </section>

    <section class="org-panel sd-px" id="sd-px" aria-labelledby="sd-px-h" hidden>
      <div class="sd-px-head">
        <span class="sd-type-ic is-tracking"><?= v2_ic('target') ?></span>
        <div>
          <h2 class="sd-card-h" id="sd-px-h">Pixel ID-uri</h2>
          <p>Adaugă sau editează ID-urile pixel-urilor pentru platformele cumpărate. Tracking-ul începe să funcționeze automat în momentul în care un Pixel ID este completat.</p>
        </div>
      </div>
      <form id="sd-px-form" novalidate>
        <div class="sd-px-list" id="sd-px-list"></div>
        <div class="sd-px-foot">
          <p id="sd-px-msg" role="status"></p>
          <button class="btn btn-primary" type="submit" id="sd-px-save">Salvează ID-uri</button>
        </div>
      </form>
    </section>

    <details class="org-panel sd-config">
      <summary><?= v2_ic('code') ?>Configurație comandă<?= v2_ic('caret-down') ?></summary>
      <pre id="sd-config"></pre>
    </details>
  </div>

  <div class="org-empty" id="sd-missing" hidden>
    <span class="org-empty-ic"><?= v2_ic('receipt') ?></span>
    <b>Comanda nu a fost găsită</b>
    <p>Verifică link-ul sau întoarce-te la lista de servicii.</p>
    <a class="btn btn-primary" href="/organizator/servicii">Înapoi la servicii</a>
  </div>

  <div class="org-empty is-error" id="sd-failed" hidden>
    <span class="org-empty-ic"><?= v2_ic('warning-circle') ?></span>
    <b>Nu am putut încărca comanda</b>
    <p>A apărut o problemă de conexiune. Încearcă din nou.</p>
    <button class="btn btn-primary" type="button" id="sd-retry">Reîncearcă</button>
  </div>
</div>
<?php
v2_org_end();
include __DIR__ . '/../includes/v2/foot.php';
