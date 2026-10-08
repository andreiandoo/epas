<?php
/**
 * Venue signup: /list-your-venue (v2 design). Three-step onboarding form for venues and organizers.
 *
 * Step 1 what they sell (category cards or a short description), step 2 who they are and the password of the organizer
 * account that the request creates (pending until viaqui.com approves it; the page signs them in and offers the
 * way into the account on the thank-you screen), step 3 the venue: its name, the
 * company's CUI (checked at ANAF through proxy organizer.verify-cui; the company data is shown read-only, never typed,
 * and core looks it up again on submit), the city picked from our own list, what they need (ticked from what the
 * platform does) and a note. onboarding.js checks each step before moving on (saying what's missing instead of greying
 * the button out), then posts to the lead pipeline (proxy leads.create → core LeadsController::create) and pings the
 * funnel (leads.track page_view_onboarding) on the bo_lead_sid session shared with /devino-partener. Core's English
 * errors are said in the visitor's language and the form goes back to the step that holds the field.
 *
 * ?tip=<type> preselects the category and ?loc=<name> fills in the venue name, so a personalised campaign link on
 * /devino-partener continues here. No page cache: the URL fills the form. UTM parameters are captured in <head>
 * because v2 head.php strips them from the address bar after 15 s.
 */

require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/api.php';
require_once __DIR__ . '/includes/nav-helpers.php';
require_once __DIR__ . '/includes/v2/helpers.php';
require_once __DIR__ . '/includes/v2/nav.php';

$obTipToSlug = [
    'escape' => 'escape-rooms', 'escape-room' => 'escape-rooms', 'escape-rooms' => 'escape-rooms',
    'muzeu' => 'muzee-expozitii', 'muzee' => 'muzee-expozitii', 'muzee-expozitii' => 'muzee-expozitii',
    'parc-distractii' => 'parcuri-de-distractii', 'distractii' => 'parcuri-de-distractii', 'parcuri-de-distractii' => 'parcuri-de-distractii',
    'parc-aventura' => 'parcuri-de-aventura', 'aventura' => 'parcuri-de-aventura', 'parcuri-de-aventura' => 'parcuri-de-aventura',
    'natura' => 'natura-outdoor', 'natura-outdoor' => 'natura-outdoor',
    'acvarii-zoo' => 'acvarii-zoo-animale', 'zoo' => 'acvarii-zoo-animale', 'acvarii-zoo-animale' => 'acvarii-zoo-animale',
    'ateliere' => 'ateliere-experiente-creative', 'atelier' => 'ateliere-experiente-creative', 'ateliere-experiente-creative' => 'ateliere-experiente-creative',
    'tururi' => 'tururi-experiente-turistice', 'tur' => 'tururi-experiente-turistice', 'tururi-experiente-turistice' => 'tururi-experiente-turistice',
    'educatie' => 'educatie-invatare-experientiala', 'educatie-invatare-experientiala' => 'educatie-invatare-experientiala',
    'familie' => 'familie-copii', 'copii' => 'familie-copii', 'familie-copii' => 'familie-copii',
    'corporate' => 'corporate-grupuri', 'grupuri' => 'corporate-grupuri', 'corporate-grupuri' => 'corporate-grupuri',
    'cultura' => 'cultura-arta', 'cultura-arta' => 'cultura-arta',
];
$obPrefillTip = is_string($_GET['tip'] ?? null) ? substr(strtolower(trim($_GET['tip'])), 0, 80) : '';
$obPrefillSlug = $obTipToSlug[$obPrefillTip] ?? '';
$obPrefillLoc = is_string($_GET['loc'] ?? null) ? mb_substr(trim($_GET['loc']), 0, 160) : '';
$obCategories = array_values(array_filter($V2NAV['categories'] ?? [], fn ($c) => !empty($c['slug']) && !empty($c['name'])));
$obVolumes = ['' => v2_t('Choose a range'), '0-100' => v2_t('Up to 100 tickets'), '100-500' => v2_t('Between 100 and 500'), '500-2000' => v2_t('Between 500 and 2,000'), '2000-10000' => v2_t('Between 2,000 and 10,000'), '10000+' => v2_t('Over 10,000')];
// the cities the site has (the same list as /cities, featured ones first): [slug, name, county]
$obCities = array_values(array_map(fn ($c) => [$c['slug'], $c['name'], $c['county']], array_filter($V2NAV['allCities'] ?? [], fn ($c) => !empty($c['slug']) && !empty($c['name']))));
// what the platform does, as needs to tick; the keys are core's OrganizerLead::NEEDS
$obNeeds = [
    'online_sales' => ['shopping-cart-simple', v2_t('Selling tickets online'), v2_t('activity page, checkout, QR tickets')],
    'slots' => ['calendar-blank', v2_t('Bookings by day and hour'), v2_t('slots with capacity per time slot')],
    'groups' => ['users-three', v2_t('Group packages and prices by age'), v2_t('families, schools, teams')],
    'pos' => ['printer', v2_t('Selling at the ticket office (POS)'), v2_t('cash or card, printed receipt')],
    'scanning' => ['scan', v2_t('Scanning tickets at the entrance'), v2_t('with a phone, works without internet too')],
    'widget' => ['code', v2_t('Selling from my own website'), v2_t('a widget on your page')],
    'visibility' => ['magnifying-glass', v2_t('More customers and visibility'), v2_t('city pages, category pages, guides')],
    'tracking' => ['target', v2_t('Tracking for ads'), v2_t('conversions sent server-side')],
    'promo' => ['gift', v2_t('Promo codes and gift cards'), v2_t('campaigns, vouchers, bonus points')],
    'invoicing' => ['receipt', v2_t('Invoices and ANAF documents'), v2_t('invoices for companies, automatic documents')],
    'reports' => ['chart-line-up', v2_t('Reports and payouts'), v2_t('sales, takings, export')],
    'migration' => ['arrow-right', v2_t('Moving from another platform'), v2_t('you already sell tickets elsewhere')],
];

$pageTitleRaw = v2_t('List your venue: {site}', ['site' => SITE_NAME]);
$pageDescription = v2_t('Start in 5 minutes. Leave us a few details and we create your operator account on the spot: it is quick and simple, and if you want help, we guide you online.');
$canonicalUrl = SITE_URL . '/list-your-venue';
$noindex = true;
$hideFromSitemap = true;
$skipPageCache = true;

$v2Styles = ['onboarding.css'];
$v2Scripts = ['onboarding.js'];
$v2HeaderOverlay = true;
$v2FooterCompact = true;
$v2ClientData = ['supportEmail' => SUPPORT_EMAIL, 'cities' => $obCities];
$v2HeadExtra = '<script>(function(){try{var q=new URLSearchParams(location.search),u={};["utm_source","utm_medium","utm_campaign","utm_content","utm_term"].forEach(function(k){if(q.get(k))u[k]=q.get(k).slice(0,150)});window.BO_UTM=u;}catch(e){}})();</script>';

include __DIR__ . '/includes/v2/head.php';
include __DIR__ . '/includes/v2/header.php';
?>
<main id="main" tabindex="-1">
  <section class="ob-hero" aria-labelledby="ob-h">
    <svg class="deco-arches" viewBox="0 0 400 400" aria-hidden="true" focusable="false"><path d="M40 400V200a160 160 0 0 1 320 0v200"/><path d="M90 400V200a110 110 0 0 1 220 0v200"/><path d="M140 400V200a60 60 0 0 1 120 0v200"/></svg>
    <div class="ob-hero-in">
      <p class="ob-kicker"><?= v2_te('Onboarding · 5 minutes') ?></p>
      <h1 class="ob-h" id="ob-h"><?= v2_te('Let\'s put your venue online.') ?></h1>
      <p class="ob-lead"><?= v2_te('Leave us a few details about you and your venue and we create your operator account on the spot. It is quick and simple, you can do it all yourself. And if you want help, we can connect online at any time and guide you step by step.') ?></p>
    </div>
  </section>

  <section class="ob-body" aria-label="<?= v2_te('Venue registration form') ?>">
    <div class="ob-wrap">
      <div class="ob-card" id="ob-card">
        <!-- the header turns solid when the white card reaches it -->
        <div id="hdr-sentinel" aria-hidden="true"></div>
        <div class="ob-progress">
          <p class="ob-progress-top"><span id="ob-step-t"><?= v2_te('Step {n} of 3', ['n' => 1]) ?></span><span id="ob-step-l"><?= v2_te('Activity') ?></span></p>
          <div class="ob-bar" aria-hidden="true"><i id="ob-bar" style="width:33.333%"></i></div>
          <ol class="ob-dots" aria-hidden="true"><li class="is-on"><?= v2_te('Activity') ?></li><li><?= v2_te('You') ?></li><li><?= v2_te('Venue') ?></li></ol>
        </div>

        <form class="ob-form" id="ob-form" novalidate data-prefill-tip="<?= v2_e($obPrefillTip) ?>" data-prefill-loc="<?= v2_e($obPrefillLoc) ?>">
          <p class="ob-error" id="ob-error" role="alert" tabindex="-1" hidden></p>
          <!-- people never see or reach this; a bot that fills it gets a quiet "sent" -->
          <div class="ob-trap" aria-hidden="true"><label for="ob-fax"><?= v2_te('Fax (leave empty)') ?></label><input id="ob-fax" name="fax" type="text" tabindex="-1" autocomplete="off"></div>

          <!-- STEP 1: what they sell -->
          <fieldset class="ob-step" data-step="1">
            <legend class="ob-step-h" tabindex="-1"><?= v2_te('What do you sell?') ?></legend>
            <p class="ob-step-p"><?= v2_te('Choose the category that fits best. We only use it to prepare your setup.') ?></p>
            <div class="ob-cats">
              <?php foreach ($obCategories as $cat): ?>
              <label class="ob-cat">
                <input type="radio" name="category_slug" value="<?= v2_e($cat['slug']) ?>" data-name="<?= v2_e($cat['name']) ?>"<?= $cat['slug'] === $obPrefillSlug ? ' checked' : '' ?>>
                <?php if (!empty($cat['thumb'])): ?><img src="<?= v2_e($cat['thumb']) ?>" alt="" width="320" height="200" loading="lazy" decoding="async"><?php else: ?><span class="ob-cat-ph"><?= v2_ic('ticket') ?></span><?php endif; ?>
                <span class="ob-cat-name"><?= v2_e($cat['name']) ?></span>
                <span class="ob-cat-check" aria-hidden="true"><?= v2_ic('check') ?></span>
              </label>
              <?php endforeach; ?>
            </div>
            <div class="ob-field">
              <label for="ob-other"><?= v2_te('Or describe it briefly (optional)') ?></label>
              <input id="ob-other" name="category_other" type="text" maxlength="120" placeholder="<?= v2_te('e.g. Riding centre, planetarium, observatory') ?>">
            </div>
            <div class="ob-nav is-end">
              <button class="btn btn-primary" type="button" data-next><?= v2_te('Continue') ?><?= v2_ic('arrow-right') ?></button>
            </div>
          </fieldset>

          <!-- STEP 2: who they are -->
          <fieldset class="ob-step" data-step="2" hidden>
            <legend class="ob-step-h" tabindex="-1"><?= v2_te('Who are you?') ?></legend>
            <p class="ob-step-p"><?= v2_te('Your contact details and the password you use to sign in to the operator account. We only use the contact details to answer you.') ?></p>
            <div class="ob-fields">
              <div class="ob-field is-wide"><label for="ob-name"><?= v2_te('Full name *') ?></label><input id="ob-name" name="contact_name" type="text" autocomplete="name" maxlength="120" required></div>
              <div class="ob-field"><label for="ob-email"><?= v2_te('Email *') ?></label><input id="ob-email" name="email" type="email" inputmode="email" autocomplete="email" spellcheck="false" maxlength="160" required></div>
              <div class="ob-field"><label for="ob-phone"><?= v2_te('Phone') ?></label><input id="ob-phone" name="phone" type="tel" autocomplete="tel" maxlength="40" placeholder="+351..."></div>
              <div class="ob-field is-wide">
                <label for="ob-password"><?= v2_te('Account password *') ?></label>
                <div class="ob-pass">
                  <input id="ob-password" name="password" type="password" autocomplete="new-password" minlength="8" maxlength="100" required aria-describedby="ob-password-hint">
                  <button class="ob-pass-btn" id="ob-password-btn" type="button" aria-controls="ob-password" aria-pressed="false"><?= v2_te('Show') ?></button>
                </div>
                <p class="ob-hint" id="ob-password-hint"><?= v2_te('At least 8 characters. You use it to sign in to your operator account right after you send the request.') ?></p>
              </div>
            </div>
            <div class="ob-nav">
              <button class="ob-back" type="button" data-prev><?= v2_ic('arrow-left') ?><?= v2_te('Back') ?></button>
              <button class="btn btn-primary" type="button" data-next><?= v2_te('Continue') ?><?= v2_ic('arrow-right') ?></button>
            </div>
          </fieldset>

          <!-- STEP 3: the venue -->
          <fieldset class="ob-step" data-step="3" hidden>
            <legend class="ob-step-h" tabindex="-1"><?= v2_te('About the venue') ?></legend>
            <p class="ob-step-p"><?= v2_te('The details of your venue and of the company that operates it. A few minutes and we are done.') ?></p>
            <div class="ob-fields">
              <div class="ob-field is-wide"><label for="ob-venue"><?= v2_te('Name of the venue / organisation *') ?></label><input id="ob-venue" name="location_name" type="text" autocomplete="organization" maxlength="160" required value="<?= v2_e($obPrefillLoc) ?>"></div>
              <div class="ob-field is-wide">
                <label for="ob-cui"><?= v2_te('Company tax ID (CUI) *') ?></label>
                <div class="ob-cui-row">
                  <input id="ob-cui" name="cui" type="text" inputmode="numeric" autocomplete="off" spellcheck="false" maxlength="14" required placeholder="<?= v2_te('e.g. 12345678 or RO12345678') ?>" aria-describedby="ob-cui-msg">
                  <button class="ob-cui-btn" id="ob-cui-btn" type="button"><?= v2_ic('magnifying-glass') ?><span><?= v2_te('Check with ANAF') ?></span></button>
                </div>
                <p class="ob-hint" id="ob-cui-msg" aria-live="polite"><?= v2_te('We take the company details automatically from ANAF, you don\'t need to fill them in.') ?></p>
              </div>
              <div class="ob-company is-wide" id="ob-company" hidden>
                <p class="ob-company-h"><?= v2_ic('check-circle') ?><span><?= v2_te('Details taken from ANAF') ?></span><small><?= v2_ic('lock-simple') ?><?= v2_te('filled in automatically') ?></small></p>
                <dl class="ob-company-dl">
                  <div class="is-wide"><dt><?= v2_te('Company name') ?></dt><dd id="ob-co-name"></dd></div>
                  <div><dt><?= v2_te('CUI') ?></dt><dd id="ob-co-cui"></dd></div>
                  <div><dt><?= v2_te('Trade Register no.') ?></dt><dd id="ob-co-reg"></dd></div>
                  <div class="is-wide"><dt><?= v2_te('Registered office') ?></dt><dd id="ob-co-address"></dd></div>
                  <div><dt><?= v2_te('VAT') ?></dt><dd id="ob-co-vat"></dd></div>
                  <div><dt><?= v2_te('Status at ANAF') ?></dt><dd id="ob-co-status"></dd></div>
                </dl>
              </div>
              <div class="ob-field">
                <label for="ob-city" id="ob-city-l"><?= v2_te('City of the venue *') ?></label>
                <div class="ob-combo">
                  <input id="ob-city" name="city" type="text" autocomplete="off" spellcheck="false" maxlength="80" required placeholder="<?= v2_te('Search for the city') ?>" role="combobox" aria-autocomplete="list" aria-expanded="false" aria-controls="ob-city-list" aria-describedby="ob-city-hint">
                  <?= v2_ic('caret-down', 'ic ob-combo-caret') ?>
                  <div class="ob-combo-list" id="ob-city-list" role="listbox" aria-labelledby="ob-city-l" hidden></div>
                </div>
                <p class="ob-hint" id="ob-city-hint"><?= v2_te('Can\'t find your town? Choose the nearest city and tell us in the message.') ?></p>
              </div>
              <div class="ob-field"><label for="ob-website"><?= v2_te('Website (optional)') ?></label><input id="ob-website" name="website" type="text" inputmode="url" autocomplete="url" spellcheck="false" maxlength="200" placeholder="https://…"></div>
              <fieldset class="ob-needs is-wide" id="ob-channels">
                <legend><?= v2_te('Do you sell, or will you sell, tickets through another channel too? *') ?></legend>
                <p class="ob-hint"><?= v2_te('Another website or ticketing platform, agencies, partners. The commission depends on the answer: it is added on top of the ticket price and not taken out of it.') ?></p>
                <div class="ob-needs-grid">
                  <label class="ob-need">
                    <input type="radio" name="sells_elsewhere" value="0">
                    <span class="ob-need-ic" aria-hidden="true"><?= v2_ic('check-circle') ?></span>
                    <span class="ob-need-t"><b><?= v2_te('No, only through viaqui.com') ?></b><small><?= v2_te('Exclusive · 2% commission') ?></small></span>
                    <span class="ob-need-check" aria-hidden="true"><?= v2_ic('check') ?></span>
                  </label>
                  <label class="ob-need">
                    <input type="radio" name="sells_elsewhere" value="1">
                    <span class="ob-need-ic" aria-hidden="true"><?= v2_ic('link') ?></span>
                    <span class="ob-need-t"><b><?= v2_te('Yes, through other channels too') ?></b><small><?= v2_te('Non-exclusive · 4% commission') ?></small></span>
                    <span class="ob-need-check" aria-hidden="true"><?= v2_ic('check') ?></span>
                  </label>
                </div>
              </fieldset>
              <div class="ob-field is-wide"><label for="ob-volume"><?= v2_te('Estimated ticket volume / month') ?></label><select class="select" id="ob-volume" name="volume_estimate"><?php foreach ($obVolumes as $volValue => $volLabel): ?><option value="<?= v2_e($volValue) ?>"><?= v2_e($volLabel) ?></option><?php endforeach; ?></select></div>
              <fieldset class="ob-needs is-wide">
                <legend><?= v2_t('What do you need? <span>(optional)</span>') ?></legend>
                <p class="ob-hint"><?= v2_te('Tick everything that applies. We prepare your account around what matters to you.') ?></p>
                <div class="ob-needs-grid">
                  <?php foreach ($obNeeds as $needKey => [$needIcon, $needLabel, $needHint]): ?>
                  <label class="ob-need">
                    <input type="checkbox" name="needs[]" value="<?= v2_e($needKey) ?>">
                    <span class="ob-need-ic" aria-hidden="true"><?= v2_ic($needIcon) ?></span>
                    <span class="ob-need-t"><b><?= v2_e($needLabel) ?></b><small><?= v2_e($needHint) ?></small></span>
                    <span class="ob-need-check" aria-hidden="true"><?= v2_ic('check') ?></span>
                  </label>
                  <?php endforeach; ?>
                </div>
              </fieldset>
              <div class="ob-field is-wide"><label for="ob-notes"><?= v2_te('Tell us what matters (optional)') ?></label><textarea id="ob-notes" name="notes" rows="3" maxlength="800" placeholder="<?= v2_te('e.g. we have slots every 30 min, we want to integrate with our existing till, etc.') ?>"></textarea></div>
              <label class="ob-check is-wide"><input id="ob-gdpr" name="gdpr" type="checkbox" required><span><?= v2_t('I accept the <a href="{terms}" target="_blank" rel="noopener">Terms and conditions</a> and agree to my data being processed to create the account and to receive an answer. The data is not used for any other purpose. Details in the <a href="{privacy}" target="_blank" rel="noopener">Privacy policy</a>.', ['terms' => '/terms', 'privacy' => '/privacy']) ?></span></label>
            </div>
            <div class="ob-nav">
              <button class="ob-back" type="button" data-prev><?= v2_ic('arrow-left') ?><?= v2_te('Back') ?></button>
              <button class="btn btn-primary" id="ob-submit" type="submit"><?= v2_te('Send the request') ?><?= v2_ic('arrow-right') ?></button>
            </div>
          </fieldset>
        </form>

        <div class="ob-done" id="ob-done" hidden>
          <span class="ob-done-ic" aria-hidden="true"><?= v2_ic('check') ?></span>
          <h2 id="ob-done-h" tabindex="-1"><?= v2_te('Thank you!') ?></h2>
          <p id="ob-done-p"><?= v2_t('Your request has reached the viaqui.com team. We will write to you at {email} with the next steps. If you want help, we can connect online at any time and go through them together.', ['email' => '<strong id="ob-done-email"></strong>']) ?></p>
          <p class="ob-done-note" id="ob-done-note" hidden></p>
          <div class="ob-done-cta">
            <a class="btn btn-primary" id="ob-done-account" href="/organizator/panou" hidden><?= v2_te('Go to your account') ?><?= v2_ic('arrow-right') ?></a>
            <a class="btn btn-ghost" href="/partners"><?= v2_ic('arrow-left') ?><?= v2_te('Back to the overview') ?></a>
          </div>
        </div>
      </div>
      <p class="ob-note"><?= v2_te('No start-up cost · Unlimited activities · 2% commission that doesn\'t touch your price (4% if you also sell through other channels)') ?></p>
    </div>
  </section>
</main>
<?php include __DIR__ . '/includes/v2/footer.php'; ?>
