<?php
/**
 * bilete.online v2: cookie consent (banner + settings dialog), page data and scripts; closes </body></html>.
 *
 * Included at the end of v2/footer.php, and by the organizer pages after v2_org_end() (they have no public footer).
 * Page variables ($v2Scripts, $v2LegacyScripts, $v2ClientData) are described in v2/footer.php.
 */
?>
<section class="cc" id="cc-banner" aria-labelledby="cc-h" hidden>
  <div class="cc-in">
    <div class="cc-text">
      <h2 class="cc-h" id="cc-h">Folosim cookies ca site-ul să funcționeze bine și să-ți recomandăm activități potrivite.</h2>
      <p>Cookies esențiale sunt necesare pentru coș, checkout, login și securitate. Cu acordul tău folosim și cookies pentru analytics, personalizare și marketing. <a href="/cookies">Politica de cookies</a></p>
    </div>
    <div class="cc-actions">
      <button class="btn btn-primary" type="button" data-cc-action="accept">Acceptă toate</button>
      <button class="btn btn-ghost" type="button" data-cc-action="reject">Refuză opționale</button>
      <button class="link-btn" type="button" data-cc-action="open">Personalizează</button>
    </div>
  </div>
</section>
<div class="cc-dialog" id="cc-dialog" role="dialog" aria-modal="true" aria-labelledby="cc-title" data-lenis-prevent hidden>
  <div class="cc-panel">
    <div class="cc-top">
      <h2 id="cc-title">Setări cookies</h2>
      <button class="icon-btn" type="button" data-cc-action="close"><?= v2_ic('x') ?><span class="sr">Închide setările</span></button>
    </div>
    <p>Alege ce categorii permiți. Cookies esențiale rămân active pentru funcționarea platformei.</p>
    <ul class="cc-list">
      <li><label><span><b>Esențiale</b><small>Coș, checkout, login, securitate și măsurare de audiență strict agregată.</small></span><input class="cc-switch" type="checkbox" checked disabled></label></li>
      <li><label><span><b>Analytics</b><small>Măsurarea traficului și a erorilor cu instrumente terțe, de exemplu Google Analytics.</small></span><input class="cc-switch" type="checkbox" data-cc="analytics"></label></li>
      <li><label><span><b>Personalizare</b><small>Recomandări după orașele și categoriile vizitate, filtre memorate.</small></span><input class="cc-switch" type="checkbox" data-cc="personalization"></label></li>
      <li><label><span><b>Marketing</b><small>Pixeli pentru campanii și remarketing: Meta, Google Ads, TikTok.</small></span><input class="cc-switch" type="checkbox" data-cc="marketing"></label></li>
    </ul>
    <div class="cc-foot">
      <button class="btn btn-ghost" type="button" data-cc-action="reject">Refuză opționale</button>
      <button class="btn btn-ghost" type="button" data-cc-action="save">Salvează preferințele</button>
      <button class="btn btn-primary" type="button" data-cc-action="accept">Acceptă toate</button>
    </div>
  </div>
</div>

<?php if (!empty($v2ClientData)): ?>
<script type="application/json" id="v2-data"><?= json_encode($v2ClientData, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP) ?></script>
<?php endif; ?>
<?php foreach (($v2LegacyScripts ?? []) as $v2LegacyJs): ?>
<script defer src="<?= asset($v2LegacyJs) ?>"></script>
<?php endforeach; ?>
<script defer src="<?= v2_asset('js/base.js') ?>"></script>
<?php foreach (($v2Scripts ?? []) as $v2Js): ?>
<script defer src="<?= v2_asset('js/' . $v2Js) ?>"></script>
<?php endforeach; ?>
</body>
</html>
