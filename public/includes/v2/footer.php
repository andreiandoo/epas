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
 *                     cookie settings, the payment processor's mark and the copyright; nothing else
 *   $v2FooterSwitch   true for the cart: both footers are printed and the page shows the short one as soon as there
 *                     are products in the cart (cart-page.js switches [data-ftr]); with an empty cart, or without
 *                     JavaScript, the full footer is the one on screen
 */
$v2FooterSwitch = !empty($v2FooterSwitch) && empty($v2FooterCompact);
if (!empty($v2FooterCompact) || $v2FooterSwitch) {
    ?>
<footer class="ftr-mini"<?= $v2FooterSwitch ? ' data-ftr="mini" hidden' : '' ?> aria-labelledby="<?= $v2FooterSwitch ? 'ftr-h-mini' : 'ftr-h' ?>">
  <h2 class="sr" id="<?= $v2FooterSwitch ? 'ftr-h-mini' : 'ftr-h' ?>"><?= v2_te('About Viaqui') ?></h2>
  <div class="wrap ftr-mini-in">
    <a class="ftr-mini-brand" href="/" aria-label="<?= v2_te('Viaqui, home') ?>"><?= v2_brand('brand') ?></a>
    <nav class="ftr-mini-links" aria-label="<?= v2_te('Help and legal information') ?>">
      <a href="/help"><?= v2_te('Help') ?></a>
      <a href="/contact"><?= v2_te('Contact') ?></a>
      <a href="/terms"><?= v2_te('Terms') ?></a>
      <a href="/privacy"><?= v2_te('Privacy') ?></a>
      <a href="/cookies"><?= v2_te('Cookies') ?></a>
      <button type="button" data-cc-action="open"><?= v2_te('Cookie settings') ?></button>
    </nav>
    <div class="ftr-mini-anpc">
      <?php $ftrMiniPay = v2_payment_provider(); $ftrMiniLogo = v2_payment_logo($ftrMiniPay); ?>
      <?php if ($ftrMiniLogo): ?>
      <a class="ftr-mark ftr-psp" href="https://netopia-payments.com" target="_blank" rel="nofollow noopener" aria-label="<?= v2_te('Payments processed by {provider}', ['provider' => $ftrMiniPay['label']]) ?>"><img src="<?= v2_e($ftrMiniLogo) ?>" alt="<?= v2_e($ftrMiniPay['label']) ?>" width="418" height="75" loading="lazy" decoding="async"></a>
      <?php endif; ?>
    </div>
    <p class="ftr-mini-copy"><?= v2_t('© {year} Viaqui · operated by <a href="https://tixello.com" rel="noopener">Tixello</a>', ['year' => date('Y')]) ?></p>
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
<?php $v2FootNl = ($v2FooterNewsletter ?? true) !== false; // a page with its own newsletter band (city.php) turns this off ?>
<footer class="vf<?= $v2FootNl ? '' : ' is-joined' ?>"<?= $v2FooterSwitch ? ' data-ftr="full"' : '' ?> aria-labelledby="ftr-h">
  <h2 class="sr" id="ftr-h"><?= v2_te('About Viaqui') ?></h2>
  <div class="wrap vf-top<?= $v2FootNl ? '' : ' is-solo' ?>">
    <div class="vf-say">
      <?php if ($v2FootNl): ?><p class="vf-line"><?= v2_t('More places.<br>Same feeling.<br><em>Your way in.</em>') ?></p><?php endif; ?>
      <div class="vf-venue">
        <p class="vf-k"><?= v2_te('For venues') ?></p>
        <p class="vf-venue-t"><?= v2_te('Do you run a place people can book online? List it, sell tickets and scan them at the gate.') ?></p>
        <div class="vf-cta">
          <a class="btn btn-light" href="/partners"><?= v2_te('For venues') ?></a>
          <a class="btn btn-outline-light" href="/partners#demo"><?= v2_te('Book a demo') ?></a>
        </div>
      </div>
    </div>
    <?php if ($v2FootNl): ?>
    <section class="vf-nl" aria-labelledby="ftr-nl-h">
      <p class="vf-k"><?= v2_te('Newsletter') ?></p>
      <h3 id="ftr-nl-h"><?= v2_te('Weekend ideas, before you ask “what shall we do?”') ?></h3>
      <p><?= v2_te('New places, routes and guides, ideas for the children and gift experiences. One email when there is something worth the trip.') ?></p>
      <form class="vf-nl-form" data-newsletter="footer" data-msg="ftr-nl-msg" data-ok="<?= v2_te('Done. Check your inbox to confirm.') ?>" data-err="<?= v2_te('We could not complete the subscription. Please try again.') ?>">
        <label class="vf-field"><span><?= v2_te('Email') ?></span><input id="ftr-email" name="email" type="email" required placeholder="<?= v2_te('you@example.com') ?>" autocomplete="email"></label>
        <label class="vf-field"><span><?= v2_t('Your city <i>(optional)</i>') ?></span><input id="ftr-city" name="city" type="text" list="ftr-cities" maxlength="80" placeholder="<?= v2_te('Where do you set off from?') ?>" autocomplete="off"></label>
        <datalist id="ftr-cities"><?php foreach ($v2FootCityNames as $n): ?><option value="<?= v2_e($n) ?>"></option><?php endforeach; ?></datalist>
        <button class="btn vf-nl-go" type="submit"><?= v2_te('Subscribe') ?><?= v2_ic('arrow-right') ?></button>
      </form>
      <p class="form-msg" id="ftr-nl-msg" role="status" hidden></p>
      <ul class="vf-nl-points">
        <li><?= v2_ic('check') ?><?= v2_te('No more than one email a week') ?></li>
        <li><?= v2_ic('check') ?><?= v2_te('Ideas near the city you choose') ?></li>
        <li><?= v2_ic('check') ?><?= v2_te('Unsubscribe with one click') ?></li>
      </ul>
      <p class="vf-fine"><?= v2_t('By subscribing you agree to receive editorial and commercial messages from Viaqui. See the <a href="/privacy">privacy policy</a>.') ?></p>
    </section>
    <?php endif; ?>
  </div>

  <?php readfile(__DIR__ . '/skyline.svg'); ?>

  <div class="wrap vf-grid">
    <div class="vf-brand">
      <a href="/" aria-label="<?= v2_te('Viaqui, home') ?>"><?= v2_brand('brand brand-lg') ?></a>
      <p class="vf-phon">/ viˈaːki /</p>
      <p><?= v2_te('Experiences begin before you arrive. Tickets for attractions, museums, tours and days out.') ?></p>
      <ul class="vf-contact">
        <li><a href="mailto:<?= v2_e(SUPPORT_EMAIL) ?>"><?= v2_ic('envelope-simple') ?><?= v2_e(SUPPORT_EMAIL) ?></a></li>
        <li><a href="tel:+40750292962"><?= v2_ic('phone') ?>+40 750 292 962</a></li>
        <li><span><?= v2_ic('clock') ?><?= v2_te('Monday to Friday, 09:00 - 18:00 (EET)') ?></span></li>
      </ul>
    </div>
    <nav aria-label="<?= v2_te('Cities') ?>"><h3><?= v2_te('Explore') ?></h3><ul class="vf-links"><?php foreach ($v2FootCities as $c): ?><li><a href="<?= v2_e($c['href']) ?>"><?= v2_e($c['name']) ?></a></li><?php endforeach; ?><li><a href="/cities"><?= v2_te('All cities') ?></a></li><li><a href="/venues"><?= v2_te('Venues with tickets') ?></a></li><li><a href="/attractions"><?= v2_te('Attractions') ?></a></li></ul></nav>
    <nav aria-label="<?= v2_te('Experiences') ?>"><h3><?= v2_te('Experiences') ?></h3><ul class="vf-links"><?php foreach ($v2FootCats as $c): ?><li><a href="<?= v2_e($c['href']) ?>"><?= v2_e($c['name']) ?></a></li><?php endforeach; ?><li><a href="/experiences"><?= v2_te('All experiences') ?></a></li></ul></nav>
    <nav aria-label="<?= v2_te('About') ?>"><h3>Viaqui</h3><ul class="vf-links"><li><a href="/how-it-works"><?= v2_te('How it works') ?></a></li><li><a href="/plan"><?= v2_te('Trip planner') ?></a></li><li><a href="/map"><?= v2_te('Attractions map') ?></a></li><li><a href="/routes"><?= v2_te('Routes') ?></a></li><li><a href="/gift-card"><?= v2_te('Gift card') ?></a></li><li><a href="/guides"><?= v2_te('Guides') ?></a></li><li><a href="/partners"><?= v2_te('For venues') ?></a></li><li><a href="/contact"><?= v2_te('Contact') ?></a></li></ul></nav>
    <nav aria-label="<?= v2_te('Help') ?>"><h3><?= v2_te('Help') ?></h3><ul class="vf-links"><li><a href="/help"><?= v2_te('Help centre') ?></a></li><li><a href="/help#questions"><?= v2_te('Frequent questions') ?></a></li><li><a href="/find-order"><?= v2_te('Find my order') ?></a></li><li><a href="/account/tickets"><?= v2_te('My tickets') ?></a></li><li><a href="/voucher"><?= v2_te('Check a voucher') ?></a></li><li><a href="/contact"><?= v2_te('Contact support') ?></a></li><li><a href="/cookies"><?= v2_te('Cookie policy') ?></a></li></ul></nav>
  </div>

  <div class="wrap vf-bottom">
    <div class="vf-legal-row">
      <nav class="vf-legal" aria-label="<?= v2_te('Legal information') ?>">
        <a href="/terms"><?= v2_te('Terms') ?></a>
        <a href="/privacy"><?= v2_te('Privacy') ?></a>
        <a href="/cookies"><?= v2_te('Cookies') ?></a>
        <button type="button" data-cc-action="open"><?= v2_te('Cookie settings') ?></button>
        <a href="/contact"><?= v2_te('Contact') ?></a>
      </nav>
      <form class="vf-cur" action="/currency" method="get">
        <label for="vf-cur-c"><?= v2_te('Currency') ?></label>
        <select id="vf-cur-c" name="c">
          <option value="local"<?= v2_display_currency() === null ? ' selected' : '' ?>><?= v2_te('Local (each country\'s own)') ?></option>
          <?php foreach (v2_currency_choices() as $curCode => $curName): ?><option value="<?= v2_e($curCode) ?>"<?= v2_display_currency() === $curCode ? ' selected' : '' ?>><?= v2_e($curCode . ' · ' . $curName) ?></option><?php endforeach; ?>
        </select>
        <input type="hidden" name="back" value="<?= v2_e(strtok((string) ($_SERVER['REQUEST_URI'] ?? '/'), '?')) ?>">
        <button type="submit"><?= v2_te('Change') ?></button>
      </form>
      <p class="vf-tags"><?= v2_te('Cities / Cultures / Nature / People / You') ?></p>
    </div>
    <div class="vf-fine-row">
      <p class="vf-copy"><?= v2_t('© {year} Viaqui · platform operated by <a href="https://tixello.com" rel="noopener">Tixello</a> (SC TIXELLO SRL)', ['year' => date('Y')]) ?></p>
      <div class="vf-marks">
        <?php $ftrPay = v2_payment_provider(); $ftrPayLogo = v2_payment_logo($ftrPay); ?>
        <?php if ($ftrPayLogo): ?>
        <a class="ftr-mark ftr-psp" href="https://netopia-payments.com" target="_blank" rel="nofollow noopener" aria-label="<?= v2_te('Payments processed by {provider}', ['provider' => $ftrPay['label']]) ?>">
          <img src="<?= v2_e($ftrPayLogo) ?>" alt="<?= v2_e($ftrPay['label']) ?>" width="418" height="75" loading="lazy" decoding="async">
        </a>
        <?php endif; ?>
        <p class="vf-pay">Visa · Mastercard · Google Pay · Apple Pay</p>
        <a class="vf-up" href="#"><?= v2_ic('arrow-right') ?><?= v2_te('Back to top') ?></a>
      </div>
    </div>
    <p class="vf-credits"><?= v2_t('City and attraction photographs come from Wikimedia Commons; the author and the licence of each one are on its page (<a href="/photo-credits">about the photos</a>). Place data: GeoNames, Wikidata, OpenStreetMap and Natural Earth. Flags: flag-icons.') ?></p>
  </div>
</footer>

<?php include __DIR__ . '/foot.php';
