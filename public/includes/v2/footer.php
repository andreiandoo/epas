<?php
/**
 * Viaqui v2 footer, cookie consent and scripts.
 *
 * Expects $V2NAV from v2/nav.php. Page variables:
 *   $v2Scripts        page scripts under assets/v2/js, loaded after base.js, e.g. ['home.js']
 *   $v2LegacyScripts  scripts of the previous stack a page still needs, paths from the site root, loaded
 *                     before base.js, e.g. ['assets/js/config.js', 'assets/js/cart.js'] for the cart
 *   $v2ClientData     data for the page script, printed as JSON in #v2-data
 *   $v2FooterCompact  true for the one-line footer (login page, customer account): brand, the help and legal links,
 *                     cookie settings, the consumer-protection badges the law asks for and the copyright; nothing else
 *   $v2FooterSwitch   true for the cart: both footers are printed and the page shows the short one as soon as there
 *                     are products in the cart (cart-page.js switches [data-ftr]); with an empty cart, or without
 *                     JavaScript, the full footer is the one on screen
 */
$v2FooterSwitch = !empty($v2FooterSwitch) && empty($v2FooterCompact);
if (!empty($v2FooterCompact) || $v2FooterSwitch) {
    ?>
<footer class="ftr-mini"<?= $v2FooterSwitch ? ' data-ftr="mini" hidden' : '' ?> aria-labelledby="<?= $v2FooterSwitch ? 'ftr-h-mini' : 'ftr-h' ?>">
  <h2 class="sr" id="<?= $v2FooterSwitch ? 'ftr-h-mini' : 'ftr-h' ?>">About Viaqui</h2>
  <div class="wrap ftr-mini-in">
    <a class="ftr-mini-brand" href="/" aria-label="Viaqui, home"><?= v2_brand('brand') ?></a>
    <nav class="ftr-mini-links" aria-label="Help and legal information">
      <a href="/help">Help</a>
      <a href="/contact">Contact</a>
      <a href="/terms">Terms</a>
      <a href="/privacy">Privacy</a>
      <a href="/cookies">Cookies</a>
      <button type="button" data-cc-action="open">Cookie settings</button>
    </nav>
    <div class="ftr-mini-anpc">
      <?php $ftrMiniPay = v2_payment_provider(); $ftrMiniLogo = v2_payment_logo($ftrMiniPay); ?>
      <?php if ($ftrMiniLogo): ?>
      <a class="ftr-mark ftr-psp" href="https://netopia-payments.com" target="_blank" rel="nofollow noopener" aria-label="Payments processed by <?= v2_e($ftrMiniPay['label']) ?>"><img src="<?= v2_e($ftrMiniLogo) ?>" alt="<?= v2_e($ftrMiniPay['label']) ?>" width="418" height="75" loading="lazy" decoding="async"></a>
      <?php endif; ?>
      <a class="ftr-mark ftr-anpc" href="https://anpc.ro/ce-este-sal/" target="_blank" rel="nofollow noopener"><img src="<?= v2_asset('img/anpc-sal.png') ?>" alt="ANPC: alternative dispute resolution" width="250" height="62" loading="lazy" decoding="async"></a>
      <a class="ftr-mark ftr-anpc" href="https://ec.europa.eu/consumers/odr" target="_blank" rel="nofollow noopener"><img src="<?= v2_asset('img/anpc-sol.png') ?>" alt="EU online dispute resolution" width="250" height="62" loading="lazy" decoding="async"></a>
    </div>
    <p class="ftr-mini-copy">© <?= date('Y') ?> Viaqui · operated by <a href="https://tixello.ro" rel="noopener">Tixello</a></p>
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
$v2FootCityNames = array_values(array_unique(array_filter(array_column(!empty($V2NAV['allCities']) ? $V2NAV['allCities'] : $V2NAV['citiesList'], 'name'))));
sort($v2FootCityNames, SORT_FLAG_CASE | SORT_STRING);
?>
<footer class="vf"<?= $v2FooterSwitch ? ' data-ftr="full"' : '' ?> aria-labelledby="ftr-h">
  <h2 class="sr" id="ftr-h">About Viaqui</h2>
  <div class="wrap vf-top">
    <div class="vf-say">
      <p class="vf-line">More places.<br>Same feeling.<br><em>Your way in.</em></p>
      <div class="vf-venue">
        <p class="vf-k">For venues</p>
        <p class="vf-venue-t">Do you run a place people can book online? List it, sell tickets and scan them at the gate.</p>
        <div class="vf-cta">
          <a class="btn btn-light" href="/partners">For venues</a>
          <a class="btn btn-outline-light" href="/partners#demo">Book a demo</a>
        </div>
      </div>
    </div>
    <section class="vf-nl" aria-labelledby="ftr-nl-h">
      <p class="vf-k">Newsletter</p>
      <h3 id="ftr-nl-h">Weekend ideas, before you ask “what shall we do?”</h3>
      <p>New places, routes and guides, ideas for the children and gift experiences. One email when there is something worth the trip.</p>
      <form class="vf-nl-form" data-newsletter="footer" data-msg="ftr-nl-msg" data-ok="Done. Check your inbox to confirm." data-err="We could not complete the subscription. Please try again.">
        <label class="vf-field"><span>Email</span><input id="ftr-email" name="email" type="email" required placeholder="you@example.com" autocomplete="email"></label>
        <label class="vf-field"><span>Your city <i>(optional)</i></span><input id="ftr-city" name="city" type="text" list="ftr-cities" maxlength="80" placeholder="Where do you set off from?" autocomplete="off"></label>
        <datalist id="ftr-cities"><?php foreach ($v2FootCityNames as $n): ?><option value="<?= v2_e($n) ?>"></option><?php endforeach; ?></datalist>
        <button class="btn vf-nl-go" type="submit">Subscribe<?= v2_ic('arrow-right') ?></button>
      </form>
      <p class="form-msg" id="ftr-nl-msg" role="status" hidden></p>
      <ul class="vf-nl-points">
        <li><?= v2_ic('check') ?>No more than one email a week</li>
        <li><?= v2_ic('check') ?>Ideas near the city you choose</li>
        <li><?= v2_ic('check') ?>Unsubscribe with one click</li>
      </ul>
      <p class="vf-fine">By subscribing you agree to receive editorial and commercial messages from Viaqui. See the <a href="/privacy">privacy policy</a>.</p>
    </section>
  </div>

  <?php readfile(__DIR__ . '/skyline.svg'); ?>

  <div class="wrap vf-grid">
    <div class="vf-brand">
      <a href="/" aria-label="Viaqui, home"><?= v2_brand('brand brand-lg') ?></a>
      <p class="vf-phon">/ viˈaːki /</p>
      <p>Experiences begin before you arrive. Tickets for attractions, museums, tours and days out.</p>
      <ul class="vf-contact">
        <li><a href="mailto:<?= v2_e(SUPPORT_EMAIL) ?>"><?= v2_ic('envelope-simple') ?><?= v2_e(SUPPORT_EMAIL) ?></a></li>
        <li><a href="tel:+40750292962"><?= v2_ic('phone') ?>+40 750 292 962</a></li>
        <li><span><?= v2_ic('clock') ?>Monday to Friday, 09:00 - 18:00 (EET)</span></li>
      </ul>
    </div>
    <nav aria-label="Cities"><h3>Explore</h3><ul class="vf-links"><?php foreach ($v2FootCities as $c): ?><li><a href="<?= v2_e($c['href']) ?>"><?= v2_e($c['name']) ?></a></li><?php endforeach; ?><li><a href="/cities">All cities</a></li><li><a href="/venues">Venues with tickets</a></li><li><a href="/attractions">Attractions</a></li></ul></nav>
    <nav aria-label="Experiences"><h3>Experiences</h3><ul class="vf-links"><?php foreach ($v2FootCats as $c): ?><li><a href="<?= v2_e($c['href']) ?>"><?= v2_e($c['name']) ?></a></li><?php endforeach; ?><li><a href="/experiences">All experiences</a></li></ul></nav>
    <nav aria-label="About"><h3>Viaqui</h3><ul class="vf-links"><li><a href="/how-it-works">How it works</a></li><li><a href="/plan">Trip planner</a></li><li><a href="/map">Attractions map</a></li><li><a href="/routes">Routes</a></li><li><a href="/gift-card">Gift card</a></li><li><a href="/guides">Guides</a></li><li><a href="/partners">For venues</a></li><li><a href="/contact">Contact</a></li></ul></nav>
    <nav aria-label="Help"><h3>Help</h3><ul class="vf-links"><li><a href="/help">Help centre</a></li><li><a href="/help#questions">Frequent questions</a></li><li><a href="/find-order">Find my order</a></li><li><a href="/account/tickets">My tickets</a></li><li><a href="/voucher">Check a voucher</a></li><li><a href="/contact">Contact support</a></li><li><a href="/cookies">Cookie policy</a></li></ul></nav>
  </div>

  <div class="wrap vf-bottom">
    <div class="vf-legal-row">
      <nav class="vf-legal" aria-label="Legal information">
        <a href="/terms">Terms</a>
        <a href="/privacy">Privacy</a>
        <a href="/cookies">Cookies</a>
        <button type="button" data-cc-action="open">Cookie settings</button>
        <a href="/contact">Contact</a>
      </nav>
      <p class="vf-tags">Cities / Cultures / Nature / People / You</p>
      <a class="vf-up" href="#"><?= v2_ic('arrow-right') ?>Back to top</a>
    </div>
    <div class="vf-fine-row">
      <p class="vf-copy">© <?= date('Y') ?> Viaqui · platform operated by <a href="https://tixello.ro" rel="noopener">Tixello</a> (SC TIXELLO SRL)</p>
      <div class="vf-marks">
        <?php $ftrPay = v2_payment_provider(); $ftrPayLogo = v2_payment_logo($ftrPay); ?>
        <?php if ($ftrPayLogo): ?>
        <a class="ftr-mark ftr-psp" href="https://netopia-payments.com" target="_blank" rel="nofollow noopener" aria-label="Payments processed by <?= v2_e($ftrPay['label']) ?>">
          <img src="<?= v2_e($ftrPayLogo) ?>" alt="<?= v2_e($ftrPay['label']) ?>" width="418" height="75" loading="lazy" decoding="async">
        </a>
        <?php endif; ?>
        <p class="vf-pay">Visa · Mastercard · Google Pay · Apple Pay</p>
        <a class="ftr-mark ftr-anpc" href="https://anpc.ro/ce-este-sal/" target="_blank" rel="nofollow noopener"><img src="<?= v2_asset('img/anpc-sal.png') ?>" alt="ANPC: alternative dispute resolution" width="250" height="62" loading="lazy" decoding="async"></a>
        <a class="ftr-mark ftr-anpc" href="https://ec.europa.eu/consumers/odr" target="_blank" rel="nofollow noopener"><img src="<?= v2_asset('img/anpc-sol.png') ?>" alt="EU online dispute resolution" width="250" height="62" loading="lazy" decoding="async"></a>
      </div>
    </div>
    <p class="vf-credits">City and attraction photographs come from Wikimedia Commons; the author and the licence of each one are on its page. Place data: GeoNames, Wikidata and Natural Earth. Flags: flag-icons.</p>
  </div>
</footer>

<?php include __DIR__ . '/foot.php';
