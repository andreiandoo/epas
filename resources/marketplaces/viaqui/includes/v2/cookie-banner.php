<?php
/**
 * viaqui.com v2: the cookie banner (fixed to the bottom of the screen; the settings dialog is in v2/foot.php).
 *
 * Included right after <body> by v2/header.php, so it is painted with the first screen: on a phone its text is the
 * largest thing in view, and at the end of the page it was painted last. Pages without that header get it from
 * v2/foot.php.
 */
$v2CcBannerDone = true;
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
<?php /* The banner is the largest text on a phone's first screen, so it must not wait for base.js to download:
   shown here when no choice is stored (same key and version as base.js and head.php). */ ?>
<script>(function(){try{var s=JSON.parse(localStorage.getItem('bo_cookie_consent_v1'));if(s&&s.version==='2026-05-26'&&s.consent)return}catch(e){}document.getElementById('cc-banner').hidden=false})();</script>
