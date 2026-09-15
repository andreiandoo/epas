<?php
/**
 * Organizer documents: /organizator/documente (documents.php), v2 design.
 *
 * Inside the v2 organizer shell. Every section of the old page, restyled: the activity selector (search, activities
 * still running first) and, once an activity is chosen, the "service unavailable" notice. Like the old page (and
 * Ambilet), generating fiscal documents stays switched off: core has no document templates configured, so the
 * organizer.documents.* actions are not called. ?event={id} preselects an activity.
 *
 * Fixed on the way: the selector was a hand-made dropdown that only a mouse could use; it is now a searchable list
 * that works with the keyboard, and the choice is kept in the address.
 */

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/api.php';
require_once __DIR__ . '/../includes/nav-helpers.php';
require_once __DIR__ . '/../includes/v2/helpers.php';
require_once __DIR__ . '/../includes/v2/organizer.php';

$pageTitleRaw = 'Documente — ' . SITE_NAME;
$pageDescription = 'Documentele fiscale ale activităților unui organizator pe bilete.online.';
$canonicalUrl = SITE_URL . '/organizator/documente';
$noindex = true;
$skipPageCache = true;

$v2Styles = ['organizer.css', 'org-documents.css'];
$v2Scripts = ['organizer.js', 'org-documents.js'];
$v2LegacyScripts = ['assets/js/config.js', 'assets/js/utils.js', 'assets/js/api.js', 'assets/js/auth.js'];
$v2HeadExtra = v2_account_client_config();

include __DIR__ . '/../includes/v2/head.php';
v2_org_start('documents');
?>
<div class="od" id="od">
  <header class="od-head">
    <p class="org-k">Activități</p>
    <h1 class="od-h">Documente</h1>
    <p class="od-lead">Generează și descarcă documentele fiscale pentru activitățile tale.</p>
  </header>

  <section class="org-panel" aria-labelledby="od-pick-h">
    <div class="org-panel-head"><div><h2 class="org-panel-h" id="od-pick-h">Selectează activitatea</h2><p class="org-panel-p" id="od-pick-p">Se încarcă activitățile…</p></div></div>
    <label class="od-search"><?= v2_ic('magnifying-glass') ?><input id="od-q" type="search" autocomplete="off" placeholder="Caută activitate…" aria-label="Caută activitate" aria-controls="od-list"></label>
    <ul class="od-list" id="od-list" aria-live="polite"><li class="od-sk"><span class="org-skel"></span></li><li class="od-sk"><span class="org-skel"></span></li></ul>
  </section>

  <section class="od-notice" id="od-notice" aria-live="polite" hidden>
    <span class="od-notice-ic" aria-hidden="true"><?= v2_ic('warning-circle') ?></span>
    <div>
      <p class="od-notice-k" id="od-picked"></p>
      <h2 class="od-notice-h">Serviciu indisponibil momentan</h2>
      <p>Generarea documentelor fiscale va fi disponibilă în curând. Lucrăm la configurare.</p>
    </div>
  </section>
</div>
<?php
v2_org_end();
include __DIR__ . '/../includes/v2/foot.php';
