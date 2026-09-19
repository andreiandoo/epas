<?php
/**
 * The operator's locations: /organizator/locatii (activities module), v2 design.
 *
 * A list and an editor on one page (?id=N edits a location, ?nou=1 starts one). The editor covers what the public
 * location page shows: texts, city and category, address and map, pictures, seasons with their weekly hours and the
 * closed days, facilities, rules, questions, the ticket categories the products are grouped by, and the lodging
 * (information and the operator's own booking links; nothing is sold here). A new location is a draft; the first
 * publication waits for bilete.online's approval, later edits go live at once.
 *
 * org-am-locations.js reads /organizer/activities-module/meta and .../locations, saves with POST/PUT .../locations,
 * asks for approval (.../submit), shows or hides an approved location (.../publish) and deletes an empty one.
 */

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/api.php';
require_once __DIR__ . '/../includes/nav-helpers.php';
require_once __DIR__ . '/../includes/v2/helpers.php';
require_once __DIR__ . '/../includes/v2/organizer.php';
require_once __DIR__ . '/../includes/v2/am-labels.php';

$pageTitleRaw = 'Locațiile mele — ' . SITE_NAME;
$pageDescription = 'Locațiile tale de pe bilete.online: informații, poze, program, facilități și cazare.';
$canonicalUrl = SITE_URL . '/organizator/locatii';
$noindex = true;
$skipPageCache = true;

$v2Styles = ['organizer.css', 'org-venue.css', 'org-am.css'];
$v2Scripts = ['organizer.js', 'org-am.js', 'org-am-locations.js'];
$v2LegacyScripts = ['assets/js/config.js', 'assets/js/utils.js', 'assets/js/api.js', 'assets/js/auth.js'];
$v2HeadExtra = v2_account_client_config('organizer');
$v2ClientData = ['am' => am_client_labels()];

include __DIR__ . '/../includes/v2/head.php';
v2_org_start('am-locations');
?>
<div class="ve am" id="am-loc">
  <header class="ve-head" id="am-loc-head">
    <div>
      <p class="ve-eyebrow"><?= v2_ic('map-pin') ?>Locații și produse</p>
      <h1 class="ve-h" id="am-loc-h">Locațiile mele</h1>
      <p class="ve-lead" id="am-loc-lead">Locurile în care vinzi bilete: ce văd vizitatorii pe pagina locației, programul și cazarea.</p>
    </div>
    <div class="ve-head-btns" id="am-loc-tools">
      <a class="btn btn-primary" href="/organizator/locatii?nou=1" id="am-loc-new"><?= v2_ic('plus') ?>Adaugă o locație</a>
    </div>
  </header>

  <div class="org-empty is-error" id="am-loc-failed" hidden>
    <span class="org-empty-ic"><?= v2_ic('warning-circle') ?></span>
    <b>Nu am putut încărca locațiile</b>
    <p>Reîncearcă în câteva secunde.</p>
    <button class="btn btn-primary" type="button" id="am-loc-retry">Reîncearcă</button>
  </div>

  <div id="am-loc-list" aria-live="polite"><p class="ve-state">Se încarcă…</p></div>
  <div id="am-loc-edit" hidden></div>
</div>
<?php
v2_org_end();
include __DIR__ . '/../includes/v2/foot.php';
