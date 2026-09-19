<?php
/**
 * The operator's booking widgets: /organizator/widget-uri (activities module), v2 design.
 *
 * The booking of a location (or of one of its products) on the operator's own site, in an iframe served by
 * embed/locatie.php; payment happens on bilete.online. Here: whether widgets are on for the account (the admin's
 * "Activează widget-uri embed"), the sites allowed to show them (settings.embed_domains, saved through
 * /organizer/widget-settings), the pick of location and product, the code to paste (iframe + a line that fits its
 * height), a plain "Cumpără bilete" button for sites that cannot embed, and a live preview.
 *
 * org-am-widgets.js reads /organizer/me and /organizer/activities-module/locations|products.
 */

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/api.php';
require_once __DIR__ . '/../includes/nav-helpers.php';
require_once __DIR__ . '/../includes/v2/helpers.php';
require_once __DIR__ . '/../includes/v2/organizer.php';

$pageTitleRaw = 'Widget-uri embed — ' . SITE_NAME;
$pageDescription = 'Rezervarea locației tale pe site-ul tău: codul widget-ului, site-urile permise și previzualizarea.';
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
      <p class="ve-eyebrow"><?= v2_ic('code') ?>Promovare</p>
      <h1 class="ve-h">Widget-uri embed</h1>
      <p class="ve-lead">Rezervarea locației tale, direct pe site-ul tău. Vizitatorii aleg ziua și biletele acolo, iar plata se face pe bilete.online.</p>
    </div>
  </header>

  <p class="ve-state" id="wg-loading">Se încarcă…</p>

  <div class="org-empty" id="wg-off" hidden>
    <span class="org-empty-ic"><?= v2_ic('code') ?></span>
    <b>Widget-urile nu sunt activate pentru contul tău</b>
    <p>Le activăm noi. Scrie-ne un tichet și revenim cât de repede putem.</p>
    <a class="btn btn-primary" href="/organizator/suport">Deschide un tichet</a>
  </div>

  <div class="am-stack" id="wg-on" hidden>
    <section class="org-panel" aria-labelledby="wg-dom-h">
      <div class="org-panel-head"><div>
        <h2 class="org-panel-h" id="wg-dom-h">1. Site-urile tale</h2>
        <p class="org-panel-p">Widget-ul se deschide doar pe site-urile din listă. Scrie adresa, de exemplu site-meu.ro (varianta cu www merge automat) sau *.site-meu.ro pentru toate subdomeniile.</p>
      </div></div>
      <ul class="wg-domains" id="wg-domains" aria-live="polite"></ul>
      <form class="wg-add" id="wg-dom-form" novalidate>
        <span class="po-field"><label for="wg-dom-in">Adresa site-ului</label><input class="po-input" id="wg-dom-in" type="text" inputmode="url" autocomplete="off" spellcheck="false" placeholder="site-meu.ro" aria-describedby="wg-dom-err"></span>
        <button class="btn btn-primary" type="submit" id="wg-dom-go"><?= v2_ic('plus') ?>Adaugă</button>
      </form>
      <p class="wg-err" id="wg-dom-err" role="alert" hidden></p>
    </section>

    <section class="org-panel" aria-labelledby="wg-what-h">
      <div class="org-panel-head"><div>
        <h2 class="org-panel-h" id="wg-what-h">2. Ce vinzi în widget</h2>
        <p class="org-panel-p">Toate biletele unei locații sau un singur produs. Pentru o experiență care cere bilet de acces, apar și biletele de acces.</p>
      </div></div>
      <div class="wg-pick">
        <span class="po-field"><label for="wg-loc">Locația</label><span class="po-select"><select id="wg-loc"></select><?= v2_ic('caret-down') ?></span></span>
        <span class="po-field"><label for="wg-prod">Produsul</label><span class="po-select"><select id="wg-prod"><option value="">Toate biletele locației</option></select><?= v2_ic('caret-down') ?></span></span>
      </div>
      <p class="wg-note" id="wg-loc-note" hidden></p>
    </section>

    <section class="org-panel" aria-labelledby="wg-code-h" id="wg-code-box">
      <div class="org-panel-head"><div>
        <h2 class="org-panel-h" id="wg-code-h">3. Codul pentru site</h2>
        <p class="org-panel-p">Lipește codul în pagina site-ului, acolo unde vrei să apară rezervarea. Înălțimea se potrivește singură.</p>
      </div></div>
      <label class="sr" for="wg-code">Codul widget-ului</label>
      <textarea class="po-input wg-code" id="wg-code" rows="7" readonly spellcheck="false"></textarea>
      <div class="wg-tools"><button class="btn btn-primary" type="button" data-copy="wg-code"><?= v2_ic('copy') ?><span>Copiază codul</span></button></div>

      <h3 class="wg-sub">Doar un buton</h3>
      <p class="org-panel-p">Dacă site-ul tău nu acceptă cod de tip iframe, pune un buton care deschide pagina ta de pe bilete.online.</p>
      <label class="sr" for="wg-link">Codul butonului</label>
      <textarea class="po-input wg-code" id="wg-link" rows="3" readonly spellcheck="false"></textarea>
      <div class="wg-tools"><button class="btn btn-ghost" type="button" data-copy="wg-link"><?= v2_ic('copy') ?><span>Copiază butonul</span></button></div>
    </section>

    <section class="org-panel" aria-labelledby="wg-prev-h" id="wg-prev-box">
      <div class="org-panel-head"><div>
        <h2 class="org-panel-h" id="wg-prev-h">Previzualizare</h2>
        <p class="org-panel-p">Așa arată widget-ul pe site-ul tău. Poți încerca rezervarea; plata se deschide într-o filă nouă.</p>
      </div></div>
      <div class="wg-frame"><iframe id="wg-preview" title="Previzualizarea widget-ului" loading="lazy"></iframe></div>
    </section>
  </div>
</div>
<?php
v2_org_end();
include __DIR__ . '/../includes/v2/foot.php';
