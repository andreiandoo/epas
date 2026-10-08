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
 * a failed image upload still looked uploaded; errors came as alerts; copy said "Copied!" even when it failed.
 */

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/api.php';
require_once __DIR__ . '/../includes/nav-helpers.php';
require_once __DIR__ . '/../includes/v2/helpers.php';
require_once __DIR__ . '/../includes/v2/organizer.php';

$pageTitleRaw = v2_t('Embed widgets: {site}', ['site' => SITE_NAME]);
$pageDescription = v2_t('Widgets and a white-label package for operators on Viaqui.');
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
        . '<span class="ow-up-text">' . v2_ic('upload-simple') . '<b>' . v2_te('Upload an image') . '</b><small>' . $hint . '</small></span></button>'
        . '<input type="file" id="ow-file-' . $type . '" accept="image/png,image/jpeg,image/webp,image/svg+xml" class="ow-sr" tabindex="-1">'
        . '<span class="ow-help" id="ow-up-' . $type . '-msg" aria-live="polite"></span></div>';
};

include __DIR__ . '/../includes/v2/head.php';
v2_org_start('widgets');
?>
<div class="ow" id="ow">
  <header class="ow-head">
    <p class="org-k"><?= v2_te('Marketing') ?></p>
    <h1 class="ow-h"><?= v2_te('Embed widgets') ?></h1>
    <p class="ow-lead"><?= v2_te('Generate embed codes to sell tickets right from your own website.') ?></p>
  </header>

  <div class="ow-loading" id="ow-loading"><span class="org-skel"></span></div>
  <div class="ow-alert" id="ow-disabled" role="status" hidden><?= v2_ic('warning-circle') ?><p><?= v2_t('<strong>Widgets are not enabled.</strong> Contact the Viaqui team to have the embed feature enabled.') ?></p></div>

  <section class="org-panel" id="ow-domain" aria-labelledby="ow-domain-h" hidden>
    <div class="org-panel-head"><div><h2 class="org-panel-h" id="ow-domain-h"><?= v2_te('Site domain') ?></h2><p class="org-panel-p"><?= v2_t('Enter the domain of the site where you will use the widgets. For subdomains use a wildcard: <code>*.yourdomain.com</code>') ?></p></div></div>
    <form class="ow-row" id="ow-domain-form" novalidate>
      <label class="ow-sr" for="ow-domain-in"><?= v2_te('Domain of the site') ?></label>
      <input id="ow-domain-in" type="text" inputmode="url" autocomplete="off" spellcheck="false" placeholder="<?= v2_te('e.g. https://my-site.com or *.my-site.com') ?>" aria-describedby="ow-domain-err">
      <button class="btn btn-primary" type="submit" id="ow-domain-go"><span data-label><?= v2_te('Save') ?></span></button>
    </form>
    <span class="ow-err" id="ow-domain-err" hidden></span>
    <p class="ow-ok" id="ow-domain-now" hidden></p>
  </section>

  <section class="org-panel" id="ow-wl" aria-labelledby="ow-wl-h" hidden>
    <div class="org-panel-head"><div><p class="org-k"><?= v2_te('Full widget') ?></p><h2 class="org-panel-h" id="ow-wl-h"><?= v2_te('White-label package') ?></h2><p class="org-panel-p"><?= v2_te('Your own ticket site. Set up the branding, save, then download the ZIP package.') ?></p></div></div>
    <div class="ow-seg" role="tablist" aria-label="<?= v2_te('Package settings') ?>">
      <button type="button" role="tab" id="ow-it-branding" aria-controls="ow-ip-branding" aria-selected="true"><?= v2_te('Branding') ?></button>
      <button type="button" role="tab" id="ow-it-terms" aria-controls="ow-ip-terms" aria-selected="false" tabindex="-1"><?= v2_te('Terms') ?></button>
      <button type="button" role="tab" id="ow-it-privacy" aria-controls="ow-ip-privacy" aria-selected="false" tabindex="-1"><?= v2_te('Privacy') ?></button>
    </div>
    <div role="tabpanel" id="ow-ip-branding" aria-labelledby="ow-it-branding" class="ow-grid">
      <div class="ow-col">
        <div class="ow-f">
          <label class="ow-f-l" for="ow-accent-hex"><?= v2_te('Main colour') ?></label>
          <span class="ow-color"><input type="color" id="ow-accent" value="#D4A843" aria-label="<?= v2_te('Choose the colour') ?>"><input type="text" id="ow-accent-hex" value="#D4A843" maxlength="7" spellcheck="false" aria-describedby="ow-accent-help"></span>
          <span class="ow-help" id="ow-accent-help"><?= v2_te('Buttons, links, accents') ?></span>
        </div>
        <?= $owUpload('logo', v2_te('Operator logo'), v2_te('PNG, SVG or JPG · 5 MB at most')) ?>
        <?= $owUpload('hero', v2_te('Homepage hero image'), v2_te('Recommended: 1920×800px')) ?>
        <?= $owUpload('background', v2_te('Background image (optional)'), v2_te('It applies to all pages')) ?>
      </div>
      <div class="ow-col">
        <div class="ow-f"><label class="ow-f-l" for="ow-title"><?= v2_te('Hero title (HTML allowed)') ?></label><input id="ow-title" type="text" placeholder="<?= v2_te('e.g. The perfect day<br>starts with <em>us.</em>') ?>" aria-describedby="ow-title-help"><span class="ow-help" id="ow-title-help"><?= v2_te('Use <em> for emphasis, <br> for a new line.') ?></span></div>
        <div class="ow-f"><label class="ow-f-l" for="ow-subtitle"><?= v2_te('Hero subtitle') ?></label><input id="ow-subtitle" type="text" placeholder="<?= v2_te('e.g. The best things to do in your city') ?>"></div>
        <div class="ow-f"><label class="ow-f-l" for="ow-address"><?= v2_te('Address') ?></label><input id="ow-address" type="text" placeholder="<?= v2_te('e.g. Rua Augusta 45, Lisbon') ?>"></div>
        <div class="ow-f"><label class="ow-f-l" for="ow-phone"><?= v2_te('Phone') ?></label><input id="ow-phone" type="tel" placeholder="<?= v2_te('e.g. +351 21 234 5678') ?>"></div>
        <div class="ow-f"><label class="ow-f-l" for="ow-return"><?= v2_te('Return URL (after payment)') ?></label><input id="ow-return" type="text" readonly aria-describedby="ow-return-help"><span class="ow-help" id="ow-return-help"><?= v2_te('Generated automatically from the configured domain.') ?></span></div>
      </div>
    </div>
    <div role="tabpanel" id="ow-ip-terms" aria-labelledby="ow-it-terms" hidden>
      <p class="ow-help"><?= v2_te('The content of the "Terms and conditions" page of the white-label site. HTML allowed.') ?></p>
      <textarea id="ow-terms" rows="14" placeholder="<?= v2_te('Enter the terms and conditions here…') ?>" aria-label="<?= v2_te('Terms and conditions') ?>"></textarea>
    </div>
    <div role="tabpanel" id="ow-ip-privacy" aria-labelledby="ow-it-privacy" hidden>
      <p class="ow-help"><?= v2_te('The content of the "Privacy policy" page of the white-label site. HTML allowed.') ?></p>
      <textarea id="ow-privacy" rows="14" placeholder="<?= v2_te('Enter the privacy policy here…') ?>" aria-label="<?= v2_te('Privacy policy') ?>"></textarea>
    </div>
    <div class="ow-act">
      <button class="btn btn-primary" type="button" id="ow-save"><?= v2_ic('check') ?><span data-label><?= v2_te('Save the settings') ?></span></button>
      <a class="btn btn-ghost" id="ow-package" href="#"><?= v2_ic('download-simple') ?><?= v2_te('Download the package (.zip)') ?></a>
    </div>
  </section>

  <section class="org-panel" id="ow-embed" aria-labelledby="ow-embed-h" hidden>
    <div class="org-panel-head"><div><p class="org-k"><?= v2_te('Embed') ?></p><h2 class="org-panel-h" id="ow-embed-h"><?= v2_te('Embed codes') ?></h2></div>
      <div class="ow-seg" role="tablist" aria-label="<?= v2_te('Widget type') ?>">
        <button type="button" role="tab" id="ow-t-single" aria-controls="ow-p-single" aria-selected="true"><?= v2_te('Activity widget') ?></button>
        <button type="button" role="tab" id="ow-t-list" aria-controls="ow-p-list" aria-selected="false" tabindex="-1"><?= v2_te('List widget') ?></button>
      </div>
    </div>
    <div role="tabpanel" id="ow-p-single" aria-labelledby="ow-t-single" class="ow-grid">
      <div class="ow-card">
        <h3 class="ow-card-h"><?= v2_te('Setup') ?></h3>
        <div class="ow-f"><label class="ow-f-l" for="ow-s-event"><?= v2_te('Activity') ?></label><select id="ow-s-event"><option value=""><?= v2_te('Select an activity…') ?></option></select></div>
        <div class="ow-f"><label class="ow-f-l" for="ow-s-theme"><?= v2_te('Theme') ?></label><select id="ow-s-theme"><option value="light"><?= v2_te('Light') ?></option><option value="dark"><?= v2_te('Dark') ?></option></select></div>
        <div class="ow-f"><label class="ow-f-l" for="ow-s-style"><?= v2_te('Card style') ?></label><select id="ow-s-style"><option value="card"><?= v2_te('Vertical card') ?></option><option value="horizontal"><?= v2_te('Horizontal card') ?></option><option value="compact"><?= v2_te('Compact (button only)') ?></option></select></div>
        <div class="ow-f"><label class="ow-f-l" for="ow-s-code"><?= v2_te('Embed code') ?></label><textarea id="ow-s-code" rows="6" readonly spellcheck="false"></textarea></div>
        <button class="btn btn-ghost ow-sm" type="button" data-copy="ow-s-code"><?= v2_ic('copy') ?><?= v2_te('Copy the code') ?></button>
      </div>
      <div class="ow-card"><h3 class="ow-card-h"><?= v2_te('Preview') ?></h3><div class="ow-preview" id="ow-s-preview"></div></div>
    </div>
    <div role="tabpanel" id="ow-p-list" aria-labelledby="ow-t-list" class="ow-grid" hidden>
      <div class="ow-card">
        <h3 class="ow-card-h"><?= v2_te('Setup') ?></h3>
        <div class="ow-f"><label class="ow-f-l" for="ow-l-limit"><?= v2_te('Maximum number of activities') ?></label><input id="ow-l-limit" type="number" min="1" max="20" value="6" inputmode="numeric"></div>
        <div class="ow-f"><label class="ow-f-l" for="ow-l-theme"><?= v2_te('Theme') ?></label><select id="ow-l-theme"><option value="light"><?= v2_te('Light') ?></option><option value="dark"><?= v2_te('Dark') ?></option></select></div>
        <div class="ow-f"><label class="ow-f-l" for="ow-l-layout"><?= v2_te('Layout') ?></label><select id="ow-l-layout"><option value="grid"><?= v2_te('Grid (cards)') ?></option><option value="list"><?= v2_te('List (horizontal)') ?></option></select></div>
        <div class="ow-f"><label class="ow-f-l" for="ow-l-code"><?= v2_te('Embed code') ?></label><textarea id="ow-l-code" rows="6" readonly spellcheck="false"></textarea></div>
        <button class="btn btn-ghost ow-sm" type="button" data-copy="ow-l-code"><?= v2_ic('copy') ?><?= v2_te('Copy the code') ?></button>
      </div>
      <div class="ow-card"><h3 class="ow-card-h"><?= v2_te('Preview') ?></h3><div class="ow-preview" id="ow-l-preview"></div></div>
    </div>
  </section>
</div>
<?php
v2_org_end();
include __DIR__ . '/../includes/v2/foot.php';
