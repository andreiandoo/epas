<?php
/**
 * bilete.online v2 footer, cookie consent and scripts.
 *
 * Expects $V2NAV from v2/nav.php. Page variables:
 *   $v2Scripts        page scripts under assets/v2/js, loaded after base.js, e.g. ['home.js']
 *   $v2LegacyScripts  scripts of the previous stack a page still needs, paths from the site root, loaded
 *                     before base.js, e.g. ['assets/js/config.js', 'assets/js/cart.js'] for the cart
 *   $v2ClientData     data for the page script, printed as JSON in #v2-data
 */
$v2FootCities = array_slice($V2NAV['citiesList'], 0, 8);
$v2FootCats = array_slice($V2NAV['categories'], 0, 8);
?>
<footer class="ftr" aria-labelledby="ftr-h">
  <svg class="ftr-line draw-clip" viewBox="0 590 3240 310" aria-hidden="true"><use href="#drum-g"/></svg>
  <h2 class="sr" id="ftr-h">Despre bilete.online</h2>
  <div class="wrap ftr-top">
    <section class="ftr-card" aria-labelledby="ftr-nl-h">
      <h3 id="ftr-nl-h">Primește idei de weekend înainte să întrebi „ce facem?”.</h3>
      <p>Ghiduri, activități noi, idei pentru copii, experiențe cadou și puncte bonus care expiră.</p>
      <form class="ftr-nl-form" data-newsletter="footer" data-msg="ftr-nl-msg" data-ok="Gata! Verifică emailul pentru confirmare." data-err="Nu am putut finaliza abonarea. Încearcă din nou.">
        <label class="sr" for="ftr-email">Email</label>
        <input id="ftr-email" name="email" type="email" required placeholder="emailul tău" autocomplete="email">
        <label class="sr" for="ftr-city">Orașul tău (opțional)</label>
        <select id="ftr-city" name="city">
          <option value="">Orașul tău (opțional)</option>
          <?php foreach ($v2FootCities as $c): ?><option value="<?= v2_e($c['name']) ?>"><?= v2_e($c['name']) ?></option><?php endforeach; ?>
        </select>
        <button class="btn btn-light" type="submit">Abonează-mă</button>
      </form>
      <p class="form-msg" id="ftr-nl-msg" role="status" hidden></p>
      <p class="ftr-fine">Prin abonare accepți să primești comunicări editoriale și comerciale. Te poți dezabona oricând.</p>
    </section>
    <section class="ftr-card" aria-labelledby="ftr-venue-h">
      <p class="ftr-k">Pentru locații</p>
      <h3 id="ftr-venue-h">Ai activități care pot fi rezervate online?</h3>
      <p>Listează locația, creează activități, vinde bilete, scanează QR și ajungi mai ușor la oamenii care caută experiențe ca ale tale.</p>
      <div class="ftr-cta">
        <a class="btn btn-light" href="/pentru-locatii">Pentru locații</a>
        <a class="btn btn-outline-light" href="/contact?motiv=locatie">Cere demo</a>
      </div>
    </section>
  </div>
  <div class="wrap ftr-grid">
    <div class="ftr-brand">
      <a href="/" aria-label="bilete.online, pagina principală"><?= v2_brand('brand brand-lg') ?></a>
      <p>Experiențele încep înainte de intrare. Bilete la atracții, muzee, parcuri și experiențe din toată România.</p>
      <ul class="ftr-contact">
        <li><a href="mailto:<?= v2_e(SUPPORT_EMAIL) ?>"><?= v2_ic('envelope-simple') ?><?= v2_e(SUPPORT_EMAIL) ?></a></li>
        <li><a href="tel:+40750292962"><?= v2_ic('phone') ?>0750 292 962</a></li>
        <li><span><?= v2_ic('clock') ?>Luni - vineri, 09:00 - 18:00</span></li>
      </ul>
    </div>
    <nav aria-label="Orașe"><h3>Explorează</h3><ul class="fl"><?php foreach ($v2FootCities as $c): ?><li><a href="<?= v2_e($c['href']) ?>"><?= v2_e($c['name']) ?></a></li><?php endforeach; ?><li><a href="/orase">Toate orașele</a></li></ul></nav>
    <nav aria-label="Activități"><h3>Activități</h3><ul class="fl"><?php foreach ($v2FootCats as $c): ?><li><a href="<?= v2_e($c['href']) ?>"><?= v2_e($c['name']) ?></a></li><?php endforeach; ?><li><a href="/categorii">Toate categoriile</a></li></ul></nav>
    <nav aria-label="Despre"><h3>bilete.online</h3><ul class="fl"><li><a href="/cum-functioneaza">Cum funcționează</a></li><li><a href="/card-cadou">Card cadou</a></li><li><a href="/ghiduri">Ghiduri</a></li><li><a href="/pentru-locatii">Pentru locații</a></li><li><a href="/contact">Contact</a></li></ul></nav>
    <nav aria-label="Ajutor"><h3>Ajutor</h3><ul class="fl"><li><a href="/ajutor">Centrul de ajutor</a></li><li><a href="/faqs">Întrebări frecvente</a></li><li><a href="/recuperare-comanda">Recuperează comanda</a></li><li><a href="/cont/bilete">Biletele mele</a></li><li><a href="/voucher">Verifică voucher</a></li><li><a href="/contact">Contact suport</a></li><li><a href="/cookies">Politica de cookies</a></li></ul></nav>
  </div>
  <div class="wrap ftr-bottom">
    <div>
      <p>© <?= date('Y') ?> bilete.online. Toate drepturile rezervate. Platformă operată tehnologic de <a href="https://tixello.ro" rel="noopener">Tixello</a> (SC TIXELLO SRL).</p>
      <div class="ftr-legal"><a href="/termeni">Termeni</a><a href="/confidentialitate">Confidențialitate</a><a href="/cookies">Cookies</a><button type="button" data-cc-action="open">Setări cookies</button><a href="/contact">Contact</a></div>
      <div class="ftr-meta"><p class="ftr-pay"><?= v2_ic('credit-card') ?>Card · Apple Pay · Google Pay · Revolut</p><a class="ftr-up" href="#">Înapoi sus<?= v2_ic('arrow-right') ?></a></div>
    </div>
    <details class="credits"><summary>Fotografii provizorii și licențe</summary><ul><?php readfile(__DIR__ . '/credits.html'); ?></ul></details>
  </div>
</footer>

<?php include __DIR__ . '/foot.php';
