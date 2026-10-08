<?php
/**
 * Organizer extra services: /organizator/servicii (services.php), v2 design.
 *
 * Inside the v2 organizer shell. Every visible section of the old page, restyled: the payment success / cancelled
 * banners, "Comenzile mele", the service cards (activity promotion, LOCATION promotion, ad tracking), the services
 * list with the type filter and the missing pixel alert, the order window (what it applies to, configuration,
 * payment) and the placement preview. org-services.js talks to /organizer/services/* and, for the operator's own
 * locations, to /organizer/activities-module/locations through the proxy; card payment goes to the payment page core
 * returns.
 *
 * viaqui.com sells more than single activities, so two things differ from the shared core flow (both guarded in
 * core by the activities-module microservice, so Ambilet is untouched):
 * - ad tracking is an ACCOUNT-level service: one order covers every location, experience and product the operator
 *   sells, so the window has no activity step;
 * - "Promovare locatie" promotes a whole location (its page and its products) on the same four placements.
 *
 * Kept hidden, as on the old page: the "Email Marketing" and "Creare Campanii Ads" cards (their order flow exists in
 * core but was never shown to organizers); their orders still appear in the list and the type filter.
 *
 * Fixed on the way:
 * - the bank transfer box showed a placeholder IBAN (RO49 AAAA 1B31 0075 9384 0000) as if it were the account to pay
 *   into: it is gone, the organizer gets the payment instructions by e-mail, as the confirmation already said;
 * - the tracking card showed a fixed "99 RON / lună" while the price came from core (49): prices now come from core;
 * - the placement previews were HTML strings with photos from picsum.photos: they are drawn from the page's own blocks;
 * - validation messages were browser alerts; the payment return (?payment=success / cancel) is read and cleaned.
 */

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/api.php';
require_once __DIR__ . '/../includes/nav-helpers.php';
require_once __DIR__ . '/../includes/v2/helpers.php';
require_once __DIR__ . '/../includes/v2/organizer.php';

$pageTitleRaw = v2_t('Extra services') . ' · ' . SITE_NAME;
$pageDescription = v2_t('Promotion and ad tracking for the experiences of an operator on Viaqui.');
$canonicalUrl = SITE_URL . '/organizator/servicii';
$noindex = true;
$skipPageCache = true;

$v2Styles = ['organizer.css', 'org-services.css'];
$v2Scripts = ['organizer.js', 'org-services.js'];
$v2LegacyScripts = ['assets/js/config.js', 'assets/js/utils.js', 'assets/js/api.js', 'assets/js/auth.js'];
$v2HeadExtra = v2_account_client_config('organizer');

// "Prima pagină - Hero" is not sold while the promoted hero is only a preview on the homepage (?preview=1): a paid
// hero slot would show nowhere. Add ['home_hero', 'Prima pagină - Hero', 'Vizibilitate maximă, banner principal']
// back here and 'Hero prima pagină' to the two tag lists when the promoted hero goes live.
$oxLocations = [
    ['home_recommendations', v2_t('Home page: Recommendations'), v2_t('The "Recommended for you" section')],
    ['category', v2_t('Category page'), v2_t('An audience targeted by category')],
    ['city', v2_t('City page'), v2_t('A local audience from your city')],
];
$oxPlatforms = [
    ['facebook', 'Facebook Pixel', v2_t('Conversion tracking and retargeting'), 'Facebook Pixel ID', '1234567890123456'],
    ['google', 'Google Ads', v2_t('Full conversion tracking'), 'Google Ads Conversion ID', 'AW-XXXXXXXXX'],
    ['tiktok', 'TikTok Pixel', v2_t('A targeted young audience'), 'TikTok Pixel ID', 'CXXXXXXXXXXXXXXXXX'],
];

include __DIR__ . '/../includes/v2/head.php';
v2_org_start('services');
?>
<div class="ox" id="ox">
  <div class="ox-banner is-ok" id="ox-success" role="status" hidden>
    <span class="ox-banner-ic"><?= v2_ic('check') ?></span>
    <div><b id="ox-success-h"><?= v2_te('Done') ?></b><p id="ox-success-p"><?= v2_te('The operation was completed successfully.') ?></p></div>
    <button class="ox-x" type="button" data-dismiss="ox-success" aria-label="<?= v2_te('Close') ?>"><?= v2_ic('x') ?></button>
  </div>
  <div class="ox-banner is-wait" id="ox-cancelled" role="status" hidden>
    <span class="ox-banner-ic"><?= v2_ic('warning-circle') ?></span>
    <div><b><?= v2_te('Payment cancelled') ?></b><p><?= v2_te('The payment was cancelled. You can try again whenever you like.') ?></p></div>
    <button class="ox-x" type="button" data-dismiss="ox-cancelled" aria-label="<?= v2_te('Close') ?>"><?= v2_ic('x') ?></button>
  </div>

  <header class="ox-head">
    <div><p class="org-k"><?= v2_te('Marketing') ?></p><h1 class="ox-h"><?= v2_te('Extra services') ?></h1><p class="ox-lead"><?= v2_te('Promote your venues and experiences and track your ad campaigns.') ?></p></div>
    <a class="btn btn-ghost" href="/organizator/servicii/comenzi"><?= v2_ic('receipt') ?><?= v2_te('My orders') ?></a>
  </header>

  <section class="ox-cards" aria-label="<?= v2_te('Services') ?>">
    <article class="ox-card is-feat">
      <span class="ox-card-ic"><?= v2_ic('lightning') ?></span>
      <h2 class="ox-card-h"><?= v2_te('Experience promotion') ?></h2>
      <p><?= v2_te('Show your experience in the recommendations on the home page, on its category page or on its city page, ahead of the others.') ?></p>
      <ul class="ox-tags"><li><?= v2_te('Home page recommendations') ?></li><li><?= v2_te('Category') ?></li><li><?= v2_te('City') ?></li></ul>
      <p class="ox-price"><?= v2_t('From {price} / day', ['price' => '<b id="ox-price-feat">—</b>']) ?></p>
      <button class="btn btn-primary" type="button" data-open="featuring"><?= v2_ic('plus') ?><?= v2_te('Buy promotion') ?></button>
    </article>
    <article class="ox-card is-loc">
      <span class="ox-card-ic"><?= v2_ic('map-trifold') ?></span>
      <h2 class="ox-card-h"><?= v2_te('Venue promotion') ?></h2>
      <p><?= v2_te('Bring a whole venue forward, its page and all the products you sell there, not just a single experience.') ?></p>
      <ul class="ox-tags"><li><?= v2_te('Home page recommendations') ?></li><li><?= v2_te('Category') ?></li><li><?= v2_te('City') ?></li></ul>
      <p class="ox-price"><?= v2_t('From {price} / day', ['price' => '<b id="ox-price-loc">—</b>']) ?></p>
      <button class="btn btn-primary" type="button" data-open="location_featuring"><?= v2_ic('plus') ?><?= v2_te('Buy promotion') ?></button>
    </article>
    <article class="ox-card is-track">
      <span class="ox-card-ic"><?= v2_ic('chart-line-up') ?></span>
      <h2 class="ox-card-h"><?= v2_te('Ad campaign tracking') ?></h2>
      <p><?= v2_te('You connect Facebook, Google or TikTok once and track conversions for everything you sell on {site}: venues, experiences and products. It is not bought per experience.', ['site' => SITE_NAME]) ?></p>
      <ul class="ox-tags"><li>Facebook Ads</li><li>Google Ads</li><li>TikTok Ads</li><li><?= v2_te('Whole account') ?></li></ul>
      <p class="ox-price"><?= v2_t('From {price} / month', ['price' => '<b id="ox-price-track">—</b>']) ?></p>
      <button class="btn btn-primary" type="button" data-open="tracking"><?= v2_ic('plus') ?><?= v2_te('Turn on tracking') ?></button>
    </article>
  </section>

  <section class="org-panel" aria-labelledby="ox-list-h">
    <div class="org-panel-head">
      <div><h2 class="org-panel-h" id="ox-list-h"><?= v2_te('Active services') ?></h2><p class="org-panel-p" id="ox-list-p"></p></div>
      <span class="ox-select"><select id="ox-filter" aria-label="<?= v2_te('Filter by service') ?>"><option value=""><?= v2_te('All') ?></option><option value="featuring"><?= v2_te('Experience promotion') ?></option><option value="location_featuring"><?= v2_te('Venue promotion') ?></option><option value="email"><?= v2_te('Email marketing') ?></option><option value="tracking"><?= v2_te('Ad tracking') ?></option><option value="campaign"><?= v2_te('Ad campaigns') ?></option></select><?= v2_ic('caret-down') ?></span>
    </div>
    <div class="ox-table-wrap"><table class="ox-table">
      <thead><tr><th scope="col"><?= v2_te('Service') ?></th><th scope="col"><?= v2_te('Applies to') ?></th><th scope="col"><?= v2_te('Details') ?></th><th scope="col"><?= v2_te('Period') ?></th><th scope="col"><?= v2_te('Status') ?></th><th scope="col" class="ox-right"><?= v2_te('Actions') ?></th></tr></thead>
      <tbody id="ox-rows"><tr><td colspan="6" class="ox-state"><?= v2_te('Loading…') ?></td></tr></tbody>
    </table></div>
  </section>

  <dialog class="ox-dialog" id="ox-d" aria-labelledby="ox-d-h">
    <form class="ox-d-inner" id="ox-form" novalidate>
      <div class="ox-d-head"><h2 class="ox-d-h" id="ox-d-h"><?= v2_te('Set up the service') ?></h2><button class="ox-x" type="button" data-close aria-label="<?= v2_te('Close') ?>"><?= v2_ic('x') ?></button></div>
      <ol class="ox-steps"><li data-step="1"><b>1</b><span data-step-label><?= v2_te('Choose the experience') ?></span></li><li data-step="2"><b>2</b><span data-step-label><?= v2_te('Set up') ?></span></li><li data-step="3"><b>3</b><span data-step-label><?= v2_te('Payment') ?></span></li></ol>

      <div class="ox-step" id="ox-step-1">
        <div class="ox-f" id="ox-pick-event"><label class="ox-f-l" for="ox-event"><?= v2_te('Experience') ?></label><span class="ox-select"><select id="ox-event"><option value=""><?= v2_te('Choose an experience…') ?></option></select><?= v2_ic('caret-down') ?></span><span class="ox-err" id="ox-event-err" hidden></span></div>
        <div class="ox-f" id="ox-pick-place" hidden><label class="ox-f-l" for="ox-place"><?= v2_te('Venue') ?></label><span class="ox-select"><select id="ox-place"><option value=""><?= v2_te('Choose a venue…') ?></option></select><?= v2_ic('caret-down') ?></span><span class="ox-help"><?= v2_te('The promotion covers the venue page and the products you sell there.') ?></span><span class="ox-err" id="ox-place-err" hidden></span></div>
        <div class="ox-ev" id="ox-ev" hidden><span class="ox-ev-img" id="ox-ev-img"></span><div><b id="ox-ev-name"></b><small id="ox-ev-date"></small><small id="ox-ev-venue"></small></div></div>
      </div>

      <div class="ox-step" id="ox-step-2" hidden>
        <fieldset class="ox-fs" id="ox-feat" hidden>
          <legend id="ox-feat-legend"><?= v2_te('Where do you want the experience to appear?') ?></legend>
          <div class="ox-opts">
            <?php foreach ($oxLocations as [$oxKey, $oxLabel, $oxDesc]): ?>
            <div class="ox-opt">
              <label><input type="checkbox" name="ox-loc" value="<?= $oxKey ?>"><span><b><?= v2_e($oxLabel) ?></b><small><?= v2_e($oxDesc) ?></small><em data-price-loc="<?= $oxKey ?>"><?= v2_te('{price} / day', ['price' => '—']) ?></em></span></label>
              <button class="ox-peek" type="button" data-peek="<?= $oxKey ?>" aria-label="<?= v2_te('Preview: {name}', ['name' => $oxLabel]) ?>"><?= v2_ic('eye') ?></button>
            </div>
            <?php endforeach; ?>
          </div>
          <span class="ox-err" id="ox-loc-err" hidden></span>
          <div class="ox-grid2">
            <div class="ox-f"><label class="ox-f-l" for="ox-start"><?= v2_te('Start date') ?></label><input id="ox-start" type="date"></div>
            <div class="ox-f"><label class="ox-f-l" for="ox-end"><?= v2_te('End date') ?></label><input id="ox-end" type="date"></div>
          </div>
          <span class="ox-err" id="ox-dates-err" hidden></span>
        </fieldset>
        <fieldset class="ox-fs" id="ox-track" hidden>
          <legend><?= v2_te('Tracking platforms') ?></legend>
          <p class="ox-note"><?= v2_t('Tracking is turned on for <b>your whole account</b>: all the venues, experiences and products you sell on {site}. You do not need to choose an experience.', ['site' => v2_e(SITE_NAME)]) ?></p>
          <p class="ox-help"><?= v2_te('Tick the platforms you want. The Pixel ID can be filled in now (optional) or later from your account.') ?></p>
          <div class="ox-plats">
            <?php foreach ($oxPlatforms as [$oxKey, $oxLabel, $oxDesc, $oxIdLabel, $oxPh]): ?>
            <div class="ox-plat">
              <label><input type="checkbox" name="ox-plat" value="<?= $oxKey ?>"><span><b><?= v2_e($oxLabel) ?></b><small><?= v2_e($oxDesc) ?></small></span><em data-price-plat><?= v2_te('{price} / month', ['price' => '—']) ?></em></label>
              <div class="ox-f ox-pixel" id="ox-pixel-f-<?= $oxKey ?>" hidden><label class="ox-f-l" for="ox-pixel-<?= $oxKey ?>"><?= v2_t('{label} <small>(optional)</small>', ['label' => v2_e($oxIdLabel)]) ?></label><input id="ox-pixel-<?= $oxKey ?>" type="text" maxlength="50" spellcheck="false" placeholder="<?= $oxPh ?>"><span class="ox-help"><?= v2_te('Leave empty if you want to fill it in later from your account.') ?></span></div>
            </div>
            <?php endforeach; ?>
          </div>
          <span class="ox-err" id="ox-plat-err" hidden></span>
          <div class="ox-f"><label class="ox-f-l" for="ox-duration"><?= v2_te('Subscription length') ?></label><span class="ox-select"><select id="ox-duration"><option value="1"><?= v2_te('1 month') ?></option><option value="3" selected><?= v2_te('3 months (-10%)') ?></option><option value="6"><?= v2_te('6 months (-15%)') ?></option><option value="12"><?= v2_te('12 months (-25%)') ?></option></select><?= v2_ic('caret-down') ?></span></div>
        </fieldset>
      </div>

      <div class="ox-step" id="ox-step-3" hidden>
        <div class="ox-summary"><h3><?= v2_te('Order summary') ?></h3><dl id="ox-summary"></dl><p class="ox-total"><span><?= v2_te('Total to pay') ?></span><b id="ox-total">—</b></p></div>
        <fieldset class="ox-fs">
          <legend><?= v2_te('Payment method') ?></legend>
          <label class="ox-pay"><input type="radio" name="ox-pay" value="card" checked><span><b><?= v2_te('Bank card') ?></b><small><?= v2_te('Visa, Mastercard, Maestro · through Netopia Payments') ?></small></span></label>
          <label class="ox-pay"><input type="radio" name="ox-pay" value="transfer"><span><b><?= v2_te('Bank transfer') ?></b><small><?= v2_te('Activated in 1-2 working days') ?></small></span></label>
          <p class="ox-note" id="ox-pay-card"><?= v2_te('You will be redirected to Netopia Payments to complete the transaction safely.') ?></p>
          <p class="ox-note" id="ox-pay-transfer" hidden><?= v2_te('We email you the instructions for paying by bank transfer. The service is turned on once the payment is confirmed (1-2 working days).') ?></p>
        </fieldset>
      </div>

      <div class="ox-form-err" id="ox-form-err" role="alert" hidden></div>
      <div class="ox-d-act">
        <button class="btn btn-ghost" type="button" id="ox-back" hidden><?= v2_ic('arrow-left') ?><?= v2_te('Back') ?></button>
        <button class="btn btn-primary" type="button" id="ox-next"><?= v2_te('Continue') ?><?= v2_ic('arrow-right') ?></button>
        <button class="btn btn-primary" type="submit" id="ox-pay" hidden><?= v2_ic('lock-simple') ?><span data-label><?= v2_te('Pay now') ?></span></button>
      </div>
    </form>
  </dialog>

  <dialog class="ox-dialog is-wide" id="ox-peek-d" aria-labelledby="ox-peek-h">
    <div class="ox-d-inner">
      <div class="ox-d-head"><h2 class="ox-d-h" id="ox-peek-h"><?= v2_te('Placement preview') ?></h2><button class="ox-x" type="button" data-close aria-label="<?= v2_te('Close') ?>"><?= v2_ic('x') ?></button></div>
      <p class="ox-note" id="ox-peek-p"></p>
      <div class="ox-mock" id="ox-mock"></div>
      <div class="ox-d-act"><button class="btn btn-primary" type="button" data-close><?= v2_te('Close') ?></button></div>
    </div>
  </dialog>
</div>
<?php
v2_org_end();
include __DIR__ . '/../includes/v2/foot.php';
