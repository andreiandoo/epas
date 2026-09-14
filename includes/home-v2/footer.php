<?php
/**
 * bilete.online — homepage v2 footer, cookie consent and page scripts.
 * Expects $HV2 from home-v2/data.php.
 */
$hv2FootCities = array_slice($HV2['destinations'] ?: $HV2['citiesList'], 0, 6);
$hv2FootCats = array_slice($HV2['categories'], 0, 6);
$hv2ClientData = [
    'libs' => [hv2_asset('vendor/gsap-3.15.0.min.js'), hv2_asset('vendor/ScrollTrigger-3.15.0.min.js'), hv2_asset('vendor/lenis-1.3.26.min.js')],
    'cities' => $HV2['suggest']['cities'],
    'attractions' => $HV2['suggest']['attractions'],
    'categories' => $HV2['suggest']['categories'],
];
?>
<footer class="ftr" aria-labelledby="ftr-h">
  <svg class="ftr-line draw-clip" viewBox="0 590 3240 310" aria-hidden="true"><use href="#drum-g"/></svg>
  <h2 class="sr" id="ftr-h">Despre bilete.online</h2>
  <div class="wrap ftr-grid">
    <div class="ftr-brand">
      <a href="/" aria-label="bilete.online, pagina principală"><?= hv2_brand('brand brand-lg') ?></a>
      <p>Experiențele încep înainte de intrare. Bilete la atracții, muzee, parcuri și experiențe din toată România.</p>
      <ul class="ftr-contact">
        <li><a href="mailto:<?= hv2_e(SUPPORT_EMAIL) ?>"><?= hv2_ic('envelope-simple') ?><?= hv2_e(SUPPORT_EMAIL) ?></a></li>
        <li><a href="tel:+40750292962"><?= hv2_ic('phone') ?>0750 292 962</a></li>
        <li><span><?= hv2_ic('clock') ?>Luni - vineri, 09:00 - 18:00</span></li>
      </ul>
    </div>
    <nav aria-label="Orașe"><h3>Explorează</h3><ul class="fl"><?php foreach ($hv2FootCities as $c): ?><li><a href="<?= hv2_e($c['href']) ?>"><?= hv2_e($c['name']) ?></a></li><?php endforeach; ?><li><a href="/orase">Toate orașele</a></li></ul></nav>
    <nav aria-label="Activități"><h3>Activități</h3><ul class="fl"><?php foreach ($hv2FootCats as $c): ?><li><a href="<?= hv2_e($c['href']) ?>"><?= hv2_e($c['name']) ?></a></li><?php endforeach; ?><li><a href="/categorii">Toate categoriile</a></li></ul></nav>
    <nav aria-label="Despre"><h3>bilete.online</h3><ul class="fl"><li><a href="/cum-functioneaza">Cum funcționează</a></li><li><a href="/card-cadou">Card cadou</a></li><li><a href="/ghiduri">Ghiduri</a></li><li><a href="/pentru-locatii">Pentru locații</a></li><li><a href="/contact">Contact</a></li></ul></nav>
    <nav aria-label="Ajutor"><h3>Ajutor</h3><ul class="fl"><li><a href="/ajutor">Centrul de ajutor</a></li><li><a href="/recuperare-comanda">Recuperează comanda</a></li><li><a href="/cont/bilete">Biletele mele</a></li><li><a href="/cookies">Politica de cookies</a></li></ul></nav>
  </div>
  <div class="wrap ftr-bottom">
    <div>
      <p>© <?= date('Y') ?> bilete.online. Platformă operată tehnologic de Tixello (SC TIXELLO SRL).</p>
      <div class="ftr-legal"><a href="/termeni">Termeni</a><a href="/confidentialitate">Confidențialitate</a><a href="/cookies">Cookies</a><button type="button" data-cc-action="open">Setări cookies</button></div>
    </div>
    <details class="credits"><summary>Fotografii provizorii și licențe</summary><ul><?php readfile(__DIR__ . '/credits.html'); ?></ul></details>
  </div>
</footer>

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
      <button class="icon-btn" type="button" data-cc-action="close"><?= hv2_ic('x') ?><span class="sr">Închide setările</span></button>
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

<script type="application/json" id="hv2-data"><?= json_encode($hv2ClientData, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP) ?></script>
<script defer src="<?= hv2_asset('home.js') ?>"></script>
</body>
</html>
