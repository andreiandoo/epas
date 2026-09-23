<?php
/**
 * bilete.online v2 footer, cookie consent and scripts.
 *
 * Expects $V2NAV from v2/nav.php. Page variables:
 *   $v2Scripts        page scripts under assets/v2/js, loaded after base.js, e.g. ['home.js']
 *   $v2LegacyScripts  scripts of the previous stack a page still needs, paths from the site root, loaded
 *                     before base.js, e.g. ['assets/js/config.js', 'assets/js/cart.js'] for the cart
 *   $v2ClientData     data for the page script, printed as JSON in #v2-data
 *   $v2FooterCompact  true for the one-line footer (login page, customer account): brand, the help and legal links,
 *                     cookie settings, the ANPC badges the law asks for and the copyright; nothing else
 *   $v2FooterSwitch   true for the cart: both footers are printed and the page shows the short one as soon as there
 *                     are products in the cart (cart-page.js switches [data-ftr]); with an empty cart, or without
 *                     JavaScript, the full footer is the one on screen
 */
$v2FooterSwitch = !empty($v2FooterSwitch) && empty($v2FooterCompact);
if (!empty($v2FooterCompact) || $v2FooterSwitch) {
    ?>
<footer class="ftr-mini"<?= $v2FooterSwitch ? ' data-ftr="mini" hidden' : '' ?> aria-labelledby="<?= $v2FooterSwitch ? 'ftr-h-mini' : 'ftr-h' ?>">
  <h2 class="sr" id="<?= $v2FooterSwitch ? 'ftr-h-mini' : 'ftr-h' ?>">Despre bilete.online</h2>
  <div class="wrap ftr-mini-in">
    <a class="ftr-mini-brand" href="/" aria-label="bilete.online, pagina principală"><?= v2_brand('brand') ?></a>
    <nav class="ftr-mini-links" aria-label="Ajutor și informații legale">
      <a href="/ajutor">Ajutor</a>
      <a href="/contact">Contact</a>
      <a href="/termeni">Termeni</a>
      <a href="/confidentialitate">Confidențialitate</a>
      <a href="/cookies">Cookies</a>
      <button type="button" data-cc-action="open">Setări cookies</button>
    </nav>
    <div class="ftr-mini-anpc">
      <?php $ftrMiniPay = v2_payment_provider(); $ftrMiniLogo = v2_payment_logo($ftrMiniPay); ?>
      <?php if ($ftrMiniLogo): ?>
      <a class="ftr-plate ftr-psp" href="https://netopia-payments.com" target="_blank" rel="nofollow noopener" aria-label="Plăți procesate de <?= v2_e($ftrMiniPay['label']) ?>"><img src="<?= v2_e($ftrMiniLogo) ?>" alt="<?= v2_e($ftrMiniPay['label']) ?>" width="418" height="75" loading="lazy" decoding="async"></a>
      <?php endif; ?>
      <a class="ftr-plate" href="https://anpc.ro/ce-este-sal/" target="_blank" rel="nofollow noopener"><img src="<?= v2_asset('img/anpc-sal.png') ?>" alt="ANPC: Soluționarea alternativă a litigiilor" width="250" height="62" loading="lazy" decoding="async"></a>
      <a class="ftr-plate" href="https://ec.europa.eu/consumers/odr" target="_blank" rel="nofollow noopener"><img src="<?= v2_asset('img/anpc-sol.png') ?>" alt="Soluționarea online a litigiilor" width="250" height="62" loading="lazy" decoding="async"></a>
    </div>
    <p class="ftr-mini-copy">© <?= date('Y') ?> bilete.online · operat de <a href="https://tixello.ro" rel="noopener">Tixello</a></p>
  </div>
</footer>
<?php
    if (!$v2FooterSwitch) {
        include __DIR__ . '/foot.php';
        return;
    }
}
$v2FootCities = array_slice($V2NAV['citiesList'], 0, 8);
$v2FootCats = array_slice($V2NAV['categories'], 0, 8);
// Every visible city for the newsletter city field (the browser filters them while typing).
$v2Fold = function (string $s): string {
    return strtr(mb_strtolower($s), ['ă' => 'a', 'â' => 'a', 'î' => 'i', 'ș' => 's', 'ş' => 's', 'ț' => 't', 'ţ' => 't']);
};
$v2FootCityNames = array_values(array_unique(array_filter(array_column(!empty($V2NAV['allCities']) ? $V2NAV['allCities'] : $V2NAV['citiesList'], 'name'))));
usort($v2FootCityNames, function ($a, $b) use ($v2Fold) {
    return strcmp($v2Fold($a), $v2Fold($b));
});
?>
<footer class="ftr"<?= $v2FooterSwitch ? ' data-ftr="full"' : '' ?> aria-labelledby="ftr-h">
  <svg class="ftr-line draw-clip" viewBox="0 590 3240 310" aria-hidden="true"><use href="#drum-g"/></svg>
  <h2 class="sr" id="ftr-h">Despre bilete.online</h2>
  <div class="wrap ftr-top">
    <section class="ftr-card" aria-labelledby="ftr-nl-h">
      <h3 id="ftr-nl-h">Primește idei de weekend înainte să întrebi „ce facem?”.</h3>
      <p>Ghiduri, activități noi, idei pentru copii, experiențe cadou și puncte bonus care expiră.</p>
      <form class="ftr-nl-form" data-newsletter="footer" data-msg="ftr-nl-msg" data-ok="Gata! Verifică emailul pentru confirmare." data-err="Nu am putut finaliza abonarea. Încearcă din nou.">
        <label class="sr" for="ftr-email">Email</label>
        <input id="ftr-email" name="email" type="email" required placeholder="emailul tău" autocomplete="email">
        <span class="ftr-nl-city">
          <?= v2_ic('map-pin') ?>
          <label class="sr" for="ftr-city">Orașul tău (opțional)</label>
          <input id="ftr-city" name="city" type="text" list="ftr-cities" maxlength="80" placeholder="Orașul tău (opțional)" autocomplete="off">
        </span>
        <datalist id="ftr-cities"><?php foreach ($v2FootCityNames as $n): ?><option value="<?= v2_e($n) ?>"></option><?php endforeach; ?></datalist>
        <button class="ftr-nl-go" type="submit" aria-label="Abonează-mă" data-icon-only><?= v2_ic('arrow-right') ?></button>
      </form>
      <p class="form-msg" id="ftr-nl-msg" role="status" hidden></p>
      <p class="ftr-fine">Prin abonare accepți să primești comunicări editoriale și comerciale. Te poți dezabona oricând.</p>
    </section>
    <section class="ftr-card" aria-labelledby="ftr-venue-h">
      <p class="ftr-k">Pentru locații</p>
      <h3 id="ftr-venue-h">Ai activități care pot fi rezervate online?</h3>
      <p>Listează locația, creează activități, vinde bilete, scanează QR și ajungi mai ușor la oamenii care caută experiențe ca ale tale.</p>
      <div class="ftr-cta">
        <a class="btn btn-light" href="/parteneri">Pentru locații</a>
        <a class="btn btn-outline-light" href="/parteneri#demo">Cere demo</a>
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
    <nav aria-label="Orașe"><h3>Explorează</h3><ul class="fl"><?php foreach ($v2FootCities as $c): ?><li><a href="<?= v2_e($c['href']) ?>"><?= v2_e($c['name']) ?></a></li><?php endforeach; ?><li><a href="/orase">Toate orașele</a></li><li><a href="/locatii">Locații cu bilete</a></li><li><a href="/atractii">Atracții</a></li></ul></nav>
    <nav aria-label="Experiențe"><h3>Experiențe</h3><ul class="fl"><?php foreach ($v2FootCats as $c): ?><li><a href="<?= v2_e($c['href']) ?>"><?= v2_e($c['name']) ?></a></li><?php endforeach; ?><li><a href="/experiente">Toate experiențele</a></li></ul></nav>
    <nav aria-label="Despre"><h3>bilete.online</h3><ul class="fl"><li><a href="/cum-functioneaza">Cum funcționează</a></li><li><a href="/card-cadou">Card cadou</a></li><li><a href="/ghiduri">Ghiduri</a></li><li><a href="/parteneri">Pentru locații</a></li><li><a href="/contact">Contact</a></li></ul></nav>
    <nav aria-label="Ajutor"><h3>Ajutor</h3><ul class="fl"><li><a href="/ajutor">Centrul de ajutor</a></li><li><a href="/ajutor#intrebari">Întrebări frecvente</a></li><li><a href="/recuperare-comanda">Recuperează comanda</a></li><li><a href="/cont/bilete">Biletele mele</a></li><li><a href="/voucher">Verifică voucher</a></li><li><a href="/contact">Contact suport</a></li><li><a href="/cookies">Politica de cookies</a></li></ul></nav>
  </div>
  <div class="wrap ftr-bottom">
    <div class="ftr-trust">
      <section class="ftr-trust-col" aria-label="Plăți">
        <p class="ftr-trust-k">Plăți securizate prin</p>
        <div class="ftr-pay">
          <?php $ftrPay = v2_payment_provider(); $ftrPayLogo = v2_payment_logo($ftrPay); ?>
          <?php if ($ftrPayLogo): ?>
          <a class="ftr-plate ftr-psp" href="https://netopia-payments.com" target="_blank" rel="nofollow noopener" aria-label="Plăți procesate de <?= v2_e($ftrPay['label']) ?>">
            <img src="<?= v2_e($ftrPayLogo) ?>" alt="<?= v2_e($ftrPay['label']) ?>" width="418" height="75" loading="lazy" decoding="async">
          </a>
          <?php endif; ?>
          <ul class="pay-badges" aria-label="Metode de plată acceptate"><li>VISA</li><li>Mastercard</li><li>Google Pay</li><li>Apple Pay</li></ul>
        </div>
      </section>
      <section class="ftr-trust-col" aria-label="Protecția consumatorului">
        <p class="ftr-trust-k">Protecția consumatorului</p>
        <div class="ftr-anpc">
          <a class="ftr-plate" href="https://anpc.ro/ce-este-sal/" target="_blank" rel="nofollow noopener"><img src="<?= v2_asset('img/anpc-sal.png') ?>" alt="ANPC: Soluționarea alternativă a litigiilor" width="250" height="62" loading="lazy" decoding="async"></a>
          <a class="ftr-plate" href="https://ec.europa.eu/consumers/odr" target="_blank" rel="nofollow noopener"><img src="<?= v2_asset('img/anpc-sol.png') ?>" alt="Soluționarea online a litigiilor" width="250" height="62" loading="lazy" decoding="async"></a>
        </div>
      </section>
    </div>
    <div class="ftr-legal-row">
      <p class="ftr-copy">© <?= date('Y') ?> bilete.online. Toate drepturile rezervate. Platformă operată tehnologic de <a href="https://tixello.ro" rel="noopener">Tixello</a> (SC TIXELLO SRL).</p>
      <nav class="ftr-legal" aria-label="Informații legale">
        <a href="/termeni">Termeni</a>
        <a href="/confidentialitate">Confidențialitate</a>
        <a href="/cookies">Cookies</a>
        <a href="/contact">Contact</a>
        <button type="button" data-cc-action="open">Setări cookies</button>
      </nav>
      <a class="ftr-up" href="#" title="Înapoi sus"><?= v2_ic('arrow-right') ?><span class="sr">Înapoi sus</span></a>
    </div>
  </div>
</footer>

<?php include __DIR__ . '/foot.php';
