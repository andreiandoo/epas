<?php
/**
 * viaqui.com v2: cookie consent (banner + settings dialog), page data and scripts; closes </body></html>.
 *
 * Included at the end of v2/footer.php, and by the organizer pages after v2_org_end() (they have no public footer).
 * Page variables ($v2Scripts, $v2LegacyScripts, $v2ClientData) are described in v2/footer.php.
 */
?>
<section class="cc" id="cc-banner" aria-labelledby="cc-h" hidden>
  <div class="cc-in">
    <div class="cc-text">
      <h2 class="cc-h" id="cc-h"><?= v2_te('We use cookies so the site works well and to suggest things you might like.') ?></h2>
      <p><?= v2_te('Essential cookies are needed for the basket, checkout, sign-in and security. With your consent we also use cookies for analytics, personalisation and marketing.') ?> <a href="/cookies"><?= v2_te('Cookie policy') ?></a></p>
    </div>
    <div class="cc-actions">
      <button class="btn btn-primary" type="button" data-cc-action="accept"><?= v2_te('Accept all') ?></button>
      <button class="btn btn-ghost" type="button" data-cc-action="reject"><?= v2_te('Reject optional') ?></button>
      <button class="link-btn" type="button" data-cc-action="open"><?= v2_te('Customise') ?></button>
    </div>
  </div>
</section>
<div class="cc-dialog" id="cc-dialog" role="dialog" aria-modal="true" aria-labelledby="cc-title" data-lenis-prevent hidden>
  <div class="cc-panel">
    <div class="cc-top">
      <h2 id="cc-title"><?= v2_te('Cookie settings') ?></h2>
      <button class="icon-btn" type="button" data-cc-action="close"><?= v2_ic('x') ?><span class="sr"><?= v2_te('Close settings') ?></span></button>
    </div>
    <p><?= v2_te('Choose which categories you allow. Essential cookies stay on so the platform can work.') ?></p>
    <ul class="cc-list">
      <li><label><span><b><?= v2_te('Essential') ?></b><small><?= v2_te('Basket, checkout, sign-in, security and strictly aggregated audience measurement.') ?></small></span><input class="cc-switch" type="checkbox" checked disabled></label></li>
      <li><label><span><b><?= v2_te('Analytics') ?></b><small><?= v2_te('Traffic and error measurement with third-party tools such as Google Analytics.') ?></small></span><input class="cc-switch" type="checkbox" data-cc="analytics"></label></li>
      <li><label><span><b><?= v2_te('Personalisation') ?></b><small><?= v2_te('Suggestions based on the cities and categories you visit, remembered filters.') ?></small></span><input class="cc-switch" type="checkbox" data-cc="personalization"></label></li>
      <li><label><span><b><?= v2_te('Marketing') ?></b><small><?= v2_te('Pixels for campaigns and remarketing: Meta, Google Ads, TikTok.') ?></small></span><input class="cc-switch" type="checkbox" data-cc="marketing"></label></li>
    </ul>
    <div class="cc-foot">
      <button class="btn btn-ghost" type="button" data-cc-action="reject"><?= v2_te('Reject optional') ?></button>
      <button class="btn btn-ghost" type="button" data-cc-action="save"><?= v2_te('Save preferences') ?></button>
      <button class="btn btn-primary" type="button" data-cc-action="accept"><?= v2_te('Accept all') ?></button>
    </div>
  </div>
</div>

<?php if (!empty($v2ClientData)): ?>
<script type="application/json" id="v2-data"><?= json_encode($v2ClientData, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP) ?></script>
<?php endif; ?>
<script defer src="<?= v2_asset('js/i18n.js') ?>"></script><?php /* VQ.t() and friends: before every other script */ ?>
<?php foreach (($v2LegacyScripts ?? []) as $v2LegacyJs): ?>
<script defer src="<?= asset($v2LegacyJs) ?>"></script>
<?php endforeach; ?>
<script defer src="<?= v2_asset('js/base.js') ?>"></script>
<?php foreach (($v2Scripts ?? []) as $v2Js): ?>
<script defer src="<?= v2_asset('js/' . $v2Js) ?>"></script>
<?php endforeach; ?>
</body>
</html>
