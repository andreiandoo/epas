<?php
/**
 * The operator's products: /organizator/produse (activities module), v2 design.
 *
 * What a location sells, in three kinds: access tickets (entry for the whole day or several days, parking, camping,
 * adult / child / group tickets), experiences (rentals, tours, workshops, with start times or for the whole day,
 * per person or per boat / group) and packages (access tickets and experiences together, at one price). A list with
 * filters and an editor on the same page (?id=N edits, ?nou=1 starts one, ?locatie=N filters or preselects). A new
 * product is a draft; the first publication waits for bilete.online's approval, later edits go live at once.
 *
 * org-am-products.js reads .../meta, .../locations and .../products, saves with POST/PUT .../products, asks for
 * approval (.../submit), shows or hides (.../publish), copies (.../duplicate) and deletes a product never sold.
 */

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/api.php';
require_once __DIR__ . '/../includes/nav-helpers.php';
require_once __DIR__ . '/../includes/v2/helpers.php';
require_once __DIR__ . '/../includes/v2/organizer.php';
require_once __DIR__ . '/../includes/v2/am-labels.php';

$pageTitleRaw = 'Produse — ' . SITE_NAME;
$pageDescription = 'Biletele de acces, experiențele și pachetele locațiilor tale.';
$canonicalUrl = SITE_URL . '/organizator/produse';
$noindex = true;
$skipPageCache = true;

$v2Styles = ['organizer.css', 'org-venue.css', 'org-am.css'];
$v2Scripts = ['organizer.js', 'org-am.js', 'org-am-products.js'];
$v2LegacyScripts = ['assets/js/config.js', 'assets/js/utils.js', 'assets/js/api.js', 'assets/js/auth.js'];
$v2HeadExtra = v2_account_client_config('organizer');
$v2ClientData = ['am' => am_client_labels()];

include __DIR__ . '/../includes/v2/head.php';
v2_org_start('am-products');
?>
<div class="ve am" id="am-prod">
  <header class="ve-head" id="am-prod-head">
    <div>
      <p class="ve-eyebrow"><?= v2_ic('ticket') ?>Locații și produse</p>
      <h1 class="ve-h">Produse</h1>
      <p class="ve-lead">Bilete de acces, experiențe și pachete. Toate se pot pune în același coș, pe aceeași dată.</p>
    </div>
    <div class="ve-head-btns">
      <a class="btn btn-primary" href="/organizator/produse?nou=1"><?= v2_ic('plus') ?>Produs nou</a>
    </div>
  </header>

  <div class="am-filters" id="am-prod-filters">
    <span class="po-field"><label for="am-f-loc">Locația</label><span class="po-select"><select id="am-f-loc"><option value="">Toate locațiile</option></select><?= v2_ic('caret-down') ?></span></span>
    <span class="po-field"><label for="am-f-type">Tipul</label><span class="po-select"><select id="am-f-type">
      <option value="">Toate</option><option value="access">Bilete de acces</option><option value="experience">Experiențe</option><option value="package">Pachete</option>
    </select><?= v2_ic('caret-down') ?></span></span>
  </div>

  <div class="org-empty is-error" id="am-prod-failed" hidden>
    <span class="org-empty-ic"><?= v2_ic('warning-circle') ?></span>
    <b>Nu am putut încărca produsele</b>
    <p>Reîncearcă în câteva secunde.</p>
    <button class="btn btn-primary" type="button" id="am-prod-retry">Reîncearcă</button>
  </div>

  <div id="am-prod-list" aria-live="polite"><p class="ve-state">Se încarcă…</p></div>
  <div id="am-prod-edit" hidden></div>
</div>
<?php
v2_org_end();
include __DIR__ . '/../includes/v2/foot.php';
