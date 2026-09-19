<?php
/**
 * Organizer widgets: /organizator/widget-uri (widgets.php), v2 design.
 *
 * Inside the v2 organizer shell. Every section of the old page, restyled: the "widgets not enabled" notice, the site
 * domain, the whitelabel package (branding: accent colour, logo, hero and background images, hero title and subtitle,
 * address, phone, return URL; terms; privacy; save; download the ZIP) and the embed code generators for one activity
 * and for the list, each with theme / style options, the code, copy and a live preview. org-widgets.js reads
 * /organizer/me and /organizer/events, saves through /organizer/widget-settings (core PUT /organizer/settings) and
 * uploads images through organizer.widget-image.
 *
 * Fixed on the way: image previews and the preview container were written as HTML; the domain was saved unchecked;
 * a failed image upload still looked uploaded; errors came as alerts; copy said "Copiat!" even when it failed.
 */

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/api.php';
require_once __DIR__ . '/../includes/nav-helpers.php';
require_once __DIR__ . '/../includes/v2/helpers.php';
require_once __DIR__ . '/../includes/v2/organizer.php';

$pageTitleRaw = 'Widget-uri embed — ' . SITE_NAME;
$pageDescription = 'Widget-uri și pachet whitelabel pentru organizatorii de pe bilete.online.';
$canonicalUrl = SITE_URL . '/organizator/widget-uri';
$noindex = true;
$skipPageCache = true;

$v2Styles = ['organizer.css', 'org-widgets.css'];
$v2Scripts = ['organizer.js', 'org-widgets.js'];
$v2LegacyScripts = ['assets/js/config.js', 'assets/js/utils.js', 'assets/js/api.js', 'assets/js/auth.js'];
$v2HeadExtra = v2_account_client_config('organizer') . '<script>window.BO_WIDGETS=' . json_encode(['siteUrl' => rtrim(SITE_URL, '/')], JSON_UNESCAPED_SLASHES | JSON_HEX_TAG) . ';</script>';

$owUpload = function (string $type, string $label, string $hint) {
    return '<div class="ow-up" data-up="' . $type . '"><span class="ow-f-l">' . $label . '</span>'
        . '<button class="ow-up-zone" type="button" data-pick="' . $type . '"><span class="ow-up-prev" id="ow-prev-' . $type . '" hidden></span>'
        . '<span class="ow-up-text">' . v2_ic('upload-simple') . '<b>Încarcă imagine</b><small>' . $hint . '</small></span></button>'
        . '<input type="file" id="ow-file-' . $type . '" accept="image/png,image/jpeg,image/webp,image/svg+xml" class="ow-sr" tabindex="-1">'
        . '<span class="ow-help" id="ow-up-' . $type . '-msg" aria-live="polite"></span></div>';
};

include __DIR__ . '/../includes/v2/head.php';
v2_org_start('widgets');
?>
<div class="ow" id="ow">
  <header class="ow-head">
    <p class="org-k">Marketing</p>
    <h1 class="ow-h">Widget-uri embed</h1>
    <p class="ow-lead">Generează coduri de embed pentru a vinde bilete direct de pe site-ul tău.</p>
  </header>

  <div class="ow-loading" id="ow-loading"><span class="org-skel"></span></div>
  <div class="ow-alert" id="ow-disabled" role="status" hidden><?= v2_ic('warning-circle') ?><p><strong>Widget-urile nu sunt activate.</strong> Contactează administratorul marketplace-ului pentru a activa funcționalitatea de embed.</p></div>

  <section class="org-panel" id="ow-domain" aria-labelledby="ow-domain-h" hidden>
    <div class="org-panel-head"><div><h2 class="org-panel-h" id="ow-domain-h">Domeniu site</h2><p class="org-panel-p">Introdu domeniul site-ului unde vei folosi widget-urile. Pentru subdomenii folosește wildcard: <code>*.numedomeniu.ro</code></p></div></div>
    <form class="ow-row" id="ow-domain-form" novalidate>
      <label class="ow-sr" for="ow-domain-in">Domeniul site-ului</label>
      <input id="ow-domain-in" type="text" inputmode="url" autocomplete="off" spellcheck="false" placeholder="ex: https://site-meu.ro sau *.site-meu.ro" aria-describedby="ow-domain-err">
      <button class="btn btn-primary" type="submit" id="ow-domain-go"><span data-label>Salvează</span></button>
    </form>
    <span class="ow-err" id="ow-domain-err" hidden></span>
    <p class="ow-ok" id="ow-domain-now" hidden></p>
  </section>

  <section class="org-panel" id="ow-wl" aria-labelledby="ow-wl-h" hidden>
    <div class="org-panel-head"><div><p class="org-k">Widget Full</p><h2 class="org-panel-h" id="ow-wl-h">Pachet whitelabel</h2><p class="org-panel-p">Site propriu de bilete. Configurează branding-ul, salvează, apoi descarcă pachetul ZIP.</p></div></div>
    <div class="ow-seg" role="tablist" aria-label="Setări pachet">
      <button type="button" role="tab" id="ow-it-branding" aria-controls="ow-ip-branding" aria-selected="true">Branding</button>
      <button type="button" role="tab" id="ow-it-terms" aria-controls="ow-ip-terms" aria-selected="false" tabindex="-1">Termeni</button>
      <button type="button" role="tab" id="ow-it-privacy" aria-controls="ow-ip-privacy" aria-selected="false" tabindex="-1">Confidențialitate</button>
    </div>
    <div role="tabpanel" id="ow-ip-branding" aria-labelledby="ow-it-branding" class="ow-grid">
      <div class="ow-col">
        <div class="ow-f">
          <label class="ow-f-l" for="ow-accent-hex">Culoare principală</label>
          <span class="ow-color"><input type="color" id="ow-accent" value="#D4A843" aria-label="Alege culoarea"><input type="text" id="ow-accent-hex" value="#D4A843" maxlength="7" spellcheck="false" aria-describedby="ow-accent-help"></span>
          <span class="ow-help" id="ow-accent-help">Butoane, link-uri, accente</span>
        </div>
        <?= $owUpload('logo', 'Logo organizator', 'PNG, SVG sau JPG · max 5MB') ?>
        <?= $owUpload('hero', 'Imagine hero homepage', 'Recomandat: 1920×800px') ?>
        <?= $owUpload('background', 'Imagine de fundal (opțional)', 'Se aplică pe toate paginile') ?>
      </div>
      <div class="ow-col">
        <div class="ow-f"><label class="ow-f-l" for="ow-title">Titlu hero (acceptă HTML)</label><input id="ow-title" type="text" placeholder="ex: Seara perfectă<br>începe cu <em>noi.</em>" aria-describedby="ow-title-help"><span class="ow-help" id="ow-title-help">Folosește &lt;em&gt; pentru accent, &lt;br&gt; pentru rând nou.</span></div>
        <div class="ow-f"><label class="ow-f-l" for="ow-subtitle">Subtitlu hero</label><input id="ow-subtitle" type="text" placeholder="ex: Cele mai bune activități din orașul tău"></div>
        <div class="ow-f"><label class="ow-f-l" for="ow-address">Adresă</label><input id="ow-address" type="text" placeholder="ex: Str. Lipscani 45, București"></div>
        <div class="ow-f"><label class="ow-f-l" for="ow-phone">Telefon</label><input id="ow-phone" type="tel" placeholder="ex: +40 721 234 567"></div>
        <div class="ow-f"><label class="ow-f-l" for="ow-return">Return URL (după plată)</label><input id="ow-return" type="text" readonly aria-describedby="ow-return-help"><span class="ow-help" id="ow-return-help">Generat automat din domeniul configurat.</span></div>
      </div>
    </div>
    <div role="tabpanel" id="ow-ip-terms" aria-labelledby="ow-it-terms" hidden>
      <p class="ow-help">Conținutul paginii „Termeni și condiții” de pe site-ul whitelabel. Acceptă HTML.</p>
      <textarea id="ow-terms" rows="14" placeholder="Introdu termenii și condițiile aici…" aria-label="Termeni și condiții"></textarea>
    </div>
    <div role="tabpanel" id="ow-ip-privacy" aria-labelledby="ow-it-privacy" hidden>
      <p class="ow-help">Conținutul paginii „Politica de confidențialitate” de pe site-ul whitelabel. Acceptă HTML.</p>
      <textarea id="ow-privacy" rows="14" placeholder="Introdu politica de confidențialitate aici…" aria-label="Politica de confidențialitate"></textarea>
    </div>
    <div class="ow-act">
      <button class="btn btn-primary" type="button" id="ow-save"><?= v2_ic('check') ?><span data-label>Salvează setări</span></button>
      <a class="btn btn-ghost" id="ow-package" href="#"><?= v2_ic('download-simple') ?>Descarcă pachet (.zip)</a>
    </div>
  </section>

  <section class="org-panel" id="ow-embed" aria-labelledby="ow-embed-h" hidden>
    <div class="org-panel-head"><div><p class="org-k">Embed</p><h2 class="org-panel-h" id="ow-embed-h">Coduri de embed</h2></div>
      <div class="ow-seg" role="tablist" aria-label="Tip widget">
        <button type="button" role="tab" id="ow-t-single" aria-controls="ow-p-single" aria-selected="true">Widget activitate</button>
        <button type="button" role="tab" id="ow-t-list" aria-controls="ow-p-list" aria-selected="false" tabindex="-1">Widget listă</button>
      </div>
    </div>
    <div role="tabpanel" id="ow-p-single" aria-labelledby="ow-t-single" class="ow-grid">
      <div class="ow-card">
        <h3 class="ow-card-h">Configurare</h3>
        <div class="ow-f"><label class="ow-f-l" for="ow-s-event">Activitate</label><select id="ow-s-event"><option value="">Selectează o activitate…</option></select></div>
        <div class="ow-f"><label class="ow-f-l" for="ow-s-theme">Temă</label><select id="ow-s-theme"><option value="light">Light</option><option value="dark">Dark</option></select></div>
        <div class="ow-f"><label class="ow-f-l" for="ow-s-style">Stil card</label><select id="ow-s-style"><option value="card">Card vertical</option><option value="horizontal">Card orizontal</option><option value="compact">Compact (doar buton)</option></select></div>
        <div class="ow-f"><label class="ow-f-l" for="ow-s-code">Cod de embed</label><textarea id="ow-s-code" rows="6" readonly spellcheck="false"></textarea></div>
        <button class="btn btn-ghost ow-sm" type="button" data-copy="ow-s-code"><?= v2_ic('copy') ?>Copiază codul</button>
      </div>
      <div class="ow-card"><h3 class="ow-card-h">Preview</h3><div class="ow-preview" id="ow-s-preview"></div></div>
    </div>
    <div role="tabpanel" id="ow-p-list" aria-labelledby="ow-t-list" class="ow-grid" hidden>
      <div class="ow-card">
        <h3 class="ow-card-h">Configurare</h3>
        <div class="ow-f"><label class="ow-f-l" for="ow-l-limit">Nr. maxim activități</label><input id="ow-l-limit" type="number" min="1" max="20" value="6" inputmode="numeric"></div>
        <div class="ow-f"><label class="ow-f-l" for="ow-l-theme">Temă</label><select id="ow-l-theme"><option value="light">Light</option><option value="dark">Dark</option></select></div>
        <div class="ow-f"><label class="ow-f-l" for="ow-l-layout">Layout</label><select id="ow-l-layout"><option value="grid">Grid (carduri)</option><option value="list">Listă (orizontal)</option></select></div>
        <div class="ow-f"><label class="ow-f-l" for="ow-l-code">Cod de embed</label><textarea id="ow-l-code" rows="6" readonly spellcheck="false"></textarea></div>
        <button class="btn btn-ghost ow-sm" type="button" data-copy="ow-l-code"><?= v2_ic('copy') ?>Copiază codul</button>
      </div>
      <div class="ow-card"><h3 class="ow-card-h">Preview</h3><div class="ow-preview" id="ow-l-preview"></div></div>
    </div>
  </section>
</div>
<?php
v2_org_end();
include __DIR__ . '/../includes/v2/foot.php';
