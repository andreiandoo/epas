<?php
/**
 * Venue signup: /inregistrare-locatie (v2 design). Three-step onboarding form for venues and organizers.
 *
 * Step 1 what they sell (category cards or a short description), step 2 who they are and the password of the organizer
 * account that the request creates (pending until bilete.online approves it; the page signs them in and offers the
 * way into the account on the thank-you screen), step 3 the venue: its name, the
 * company's CUI (checked at ANAF through proxy organizer.verify-cui; the company data is shown read-only, never typed,
 * and core looks it up again on submit), the city picked from our own list, what they need (ticked from what the
 * platform does) and a note. onboarding.js checks each step before moving on (saying what's missing instead of greying
 * the button out), then posts to the lead pipeline (proxy leads.create → core LeadsController::create) and pings the
 * funnel (leads.track page_view_onboarding) on the bo_lead_sid session shared with /devino-partener. Core's English
 * errors are said in Romanian and the form goes back to the step that holds the field.
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
$obVolumes = ['' => 'Alege un interval', '0-100' => 'Până la 100 bilete', '100-500' => 'Între 100 și 500', '500-2000' => 'Între 500 și 2.000', '2000-10000' => 'Între 2.000 și 10.000', '10000+' => 'Peste 10.000'];
// the cities the site has (the same list as /orase, featured ones first): [slug, name, county]
$obCities = array_values(array_map(fn ($c) => [$c['slug'], $c['name'], $c['county']], array_filter($V2NAV['allCities'] ?? [], fn ($c) => !empty($c['slug']) && !empty($c['name']))));
// what the platform does, as needs to tick; the keys are core's OrganizerLead::NEEDS
$obNeeds = [
    'online_sales' => ['shopping-cart-simple', 'Vânzare online de bilete', 'pagină de activitate, checkout, bilete cu QR'],
    'slots' => ['calendar-blank', 'Rezervări pe zile și ore', 'sloturi cu capacitate pe interval'],
    'groups' => ['users-three', 'Pachete de grup și prețuri pe vârste', 'familii, școli, echipe'],
    'pos' => ['printer', 'Vânzare la ghișeu (POS)', 'cash sau card, bon tipărit'],
    'scanning' => ['scan', 'Scanare bilete la intrare', 'cu telefonul, merge și fără internet'],
    'widget' => ['code', 'Vânzare de pe site-ul meu', 'widget pe pagina ta'],
    'visibility' => ['magnifying-glass', 'Mai mulți clienți și vizibilitate', 'pagini de oraș, categorie, ghiduri'],
    'tracking' => ['target', 'Tracking pentru reclame', 'conversii trimise server-side'],
    'promo' => ['gift', 'Coduri promo și carduri cadou', 'campanii, vouchere, puncte bonus'],
    'invoicing' => ['receipt', 'Facturi și documente ANAF', 'facturi pentru firme, documente automate'],
    'reports' => ['chart-line-up', 'Rapoarte și deconturi', 'vânzări, încasări, export'],
    'migration' => ['arrow-right', 'Mutare de pe altă platformă', 'vinzi deja bilete în altă parte'],
];

$pageTitleRaw = 'Înregistrare locație — ' . SITE_NAME;
$pageDescription = 'Începe în 5 minute. Lasă-ne câteva detalii și îți creăm pe loc contul de operator: e rapid și simplu, iar dacă vrei ajutor, te ghidăm online.';
$canonicalUrl = SITE_URL . '/inregistrare-locatie';
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
      <p class="ob-kicker">Onboarding · 5 minute</p>
      <h1 class="ob-h" id="ob-h">Hai <span class="ob-nw">să-ți</span> punem locația online.</h1>
      <p class="ob-lead">Lasă-ne câteva detalii despre tine și locația ta și îți creăm pe loc contul de operator. E rapid și simplu, poți face totul singur. Iar dacă vrei ajutor, ne conectăm online oricând și te ghidăm pas cu pas.</p>
    </div>
  </section>

  <section class="ob-body" aria-label="Formular înregistrare locație">
    <div class="ob-wrap">
      <div class="ob-card" id="ob-card">
        <!-- the header turns solid when the white card reaches it -->
        <div id="hdr-sentinel" aria-hidden="true"></div>
        <div class="ob-progress">
          <p class="ob-progress-top"><span id="ob-step-t">Pasul 1 din 3</span><span id="ob-step-l">Activitate</span></p>
          <div class="ob-bar" aria-hidden="true"><i id="ob-bar" style="width:33.333%"></i></div>
          <ol class="ob-dots" aria-hidden="true"><li class="is-on">Activitate</li><li>Tu</li><li>Locație</li></ol>
        </div>

        <form class="ob-form" id="ob-form" novalidate data-prefill-tip="<?= v2_e($obPrefillTip) ?>" data-prefill-loc="<?= v2_e($obPrefillLoc) ?>">
          <p class="ob-error" id="ob-error" role="alert" tabindex="-1" hidden></p>
          <!-- people never see or reach this; a bot that fills it gets a quiet "sent" -->
          <div class="ob-trap" aria-hidden="true"><label for="ob-fax">Fax (nu completa)</label><input id="ob-fax" name="fax" type="text" tabindex="-1" autocomplete="off"></div>

          <!-- STEP 1 — what they sell -->
          <fieldset class="ob-step" data-step="1">
            <legend class="ob-step-h" tabindex="-1">Ce vinzi?</legend>
            <p class="ob-step-p">Alege categoria care se potrivește cel mai bine. O folosim doar ca să-ți pregătim setup-ul.</p>
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
              <label for="ob-other">Sau descrie scurt (opțional)</label>
              <input id="ob-other" name="category_other" type="text" maxlength="120" placeholder="ex. Centru de echitație, planetariu, observator">
            </div>
            <div class="ob-nav is-end">
              <button class="btn btn-primary" type="button" data-next>Continuă<?= v2_ic('arrow-right') ?></button>
            </div>
          </fieldset>

          <!-- STEP 2 — who they are -->
          <fieldset class="ob-step" data-step="2" hidden>
            <legend class="ob-step-h" tabindex="-1">Cine ești?</legend>
            <p class="ob-step-p">Datele tale de contact și parola cu care intri în contul de operator. Contactul îl folosim doar ca să-ți răspundem.</p>
            <div class="ob-fields">
              <div class="ob-field is-wide"><label for="ob-name">Nume și prenume *</label><input id="ob-name" name="contact_name" type="text" autocomplete="name" maxlength="120" required></div>
              <div class="ob-field"><label for="ob-email">Email *</label><input id="ob-email" name="email" type="email" inputmode="email" autocomplete="email" spellcheck="false" maxlength="160" required></div>
              <div class="ob-field"><label for="ob-phone">Telefon</label><input id="ob-phone" name="phone" type="tel" autocomplete="tel" maxlength="40" placeholder="07xx xxx xxx"></div>
              <div class="ob-field is-wide">
                <label for="ob-password">Parola contului *</label>
                <div class="ob-pass">
                  <input id="ob-password" name="password" type="password" autocomplete="new-password" minlength="8" maxlength="100" required aria-describedby="ob-password-hint">
                  <button class="ob-pass-btn" id="ob-password-btn" type="button" aria-controls="ob-password" aria-pressed="false">Arată</button>
                </div>
                <p class="ob-hint" id="ob-password-hint">Minimum 8 caractere. Cu ea intri în contul tău de operator imediat după ce trimiți cererea.</p>
              </div>
            </div>
            <div class="ob-nav">
              <button class="ob-back" type="button" data-prev><?= v2_ic('arrow-left') ?>Înapoi</button>
              <button class="btn btn-primary" type="button" data-next>Continuă<?= v2_ic('arrow-right') ?></button>
            </div>
          </fieldset>

          <!-- STEP 3 — the venue -->
          <fieldset class="ob-step" data-step="3" hidden>
            <legend class="ob-step-h" tabindex="-1">Despre locație</legend>
            <p class="ob-step-p">Detaliile despre locația ta și firma care o operează. Câteva minute și am terminat.</p>
            <div class="ob-fields">
              <div class="ob-field is-wide"><label for="ob-venue">Numele locației / organizației *</label><input id="ob-venue" name="location_name" type="text" autocomplete="organization" maxlength="160" required value="<?= v2_e($obPrefillLoc) ?>"></div>
              <div class="ob-field is-wide">
                <label for="ob-cui">CUI-ul firmei *</label>
                <div class="ob-cui-row">
                  <input id="ob-cui" name="cui" type="text" inputmode="numeric" autocomplete="off" spellcheck="false" maxlength="14" required placeholder="ex. 12345678 sau RO12345678" aria-describedby="ob-cui-msg">
                  <button class="ob-cui-btn" id="ob-cui-btn" type="button"><?= v2_ic('magnifying-glass') ?><span>Verifică la ANAF</span></button>
                </div>
                <p class="ob-hint" id="ob-cui-msg" aria-live="polite">Datele firmei le preluăm automat de la ANAF, nu trebuie să le completezi.</p>
              </div>
              <div class="ob-company is-wide" id="ob-company" hidden>
                <p class="ob-company-h"><?= v2_ic('check-circle') ?><span>Date preluate de la ANAF</span><small><?= v2_ic('lock-simple') ?>completate automat</small></p>
                <dl class="ob-company-dl">
                  <div class="is-wide"><dt>Denumire</dt><dd id="ob-co-name"></dd></div>
                  <div><dt>CUI</dt><dd id="ob-co-cui"></dd></div>
                  <div><dt>Nr. Reg. Comerțului</dt><dd id="ob-co-reg"></dd></div>
                  <div class="is-wide"><dt>Sediul social</dt><dd id="ob-co-address"></dd></div>
                  <div><dt>TVA</dt><dd id="ob-co-vat"></dd></div>
                  <div><dt>Stare la ANAF</dt><dd id="ob-co-status"></dd></div>
                </dl>
              </div>
              <div class="ob-field">
                <label for="ob-city" id="ob-city-l">Orașul locației *</label>
                <div class="ob-combo">
                  <input id="ob-city" name="city" type="text" autocomplete="off" spellcheck="false" maxlength="80" required placeholder="Caută orașul" role="combobox" aria-autocomplete="list" aria-expanded="false" aria-controls="ob-city-list" aria-describedby="ob-city-hint">
                  <?= v2_ic('caret-down', 'ic ob-combo-caret') ?>
                  <div class="ob-combo-list" id="ob-city-list" role="listbox" aria-labelledby="ob-city-l" hidden></div>
                </div>
                <p class="ob-hint" id="ob-city-hint">Nu găsești localitatea? Alege orașul cel mai apropiat și spune-ne în mesaj.</p>
              </div>
              <div class="ob-field"><label for="ob-website">Site web (opțional)</label><input id="ob-website" name="website" type="text" inputmode="url" autocomplete="url" spellcheck="false" maxlength="200" placeholder="https://…"></div>
              <fieldset class="ob-needs is-wide" id="ob-channels">
                <legend>Vinzi sau vei vinde bilete și prin alt canal? *</legend>
                <p class="ob-hint">Alt site sau altă platformă de bilete, agenții, parteneri. De răspuns depinde comisionul, pe care îl plătește cumpărătorul, peste prețul biletului.</p>
                <div class="ob-needs-grid">
                  <label class="ob-need">
                    <input type="radio" name="sells_elsewhere" value="0">
                    <span class="ob-need-ic" aria-hidden="true"><?= v2_ic('check-circle') ?></span>
                    <span class="ob-need-t"><b>Nu, doar prin bilete.online</b><small>Exclusiv · comision 2%</small></span>
                    <span class="ob-need-check" aria-hidden="true"><?= v2_ic('check') ?></span>
                  </label>
                  <label class="ob-need">
                    <input type="radio" name="sells_elsewhere" value="1">
                    <span class="ob-need-ic" aria-hidden="true"><?= v2_ic('link') ?></span>
                    <span class="ob-need-t"><b>Da, și prin alte canale</b><small>Neexclusiv · comision 4%</small></span>
                    <span class="ob-need-check" aria-hidden="true"><?= v2_ic('check') ?></span>
                  </label>
                </div>
              </fieldset>
              <div class="ob-field is-wide"><label for="ob-volume">Volum estimat de bilete / lună</label><select class="select" id="ob-volume" name="volume_estimate"><?php foreach ($obVolumes as $volValue => $volLabel): ?><option value="<?= v2_e($volValue) ?>"><?= v2_e($volLabel) ?></option><?php endforeach; ?></select></div>
              <fieldset class="ob-needs is-wide">
                <legend>De ce ai nevoie? <span>(opțional)</span></legend>
                <p class="ob-hint">Bifează tot ce se potrivește. Îți pregătim contul pe ce contează pentru tine.</p>
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
              <div class="ob-field is-wide"><label for="ob-notes">Spune-ne ce e important (opțional)</label><textarea id="ob-notes" name="notes" rows="3" maxlength="800" placeholder="ex. avem sloturi la 30 min, vrem să integrăm cu casa de marcat existentă, etc."></textarea></div>
              <label class="ob-check is-wide"><input id="ob-gdpr" name="gdpr" type="checkbox" required><span>Accept <a href="/termeni" target="_blank" rel="noopener">Termenii și condițiile</a> și sunt de acord cu prelucrarea datelor pentru crearea contului și pentru a primi răspuns — datele nu sunt folosite în alt scop. Detalii în <a href="/confidentialitate" target="_blank" rel="noopener">Politica de confidențialitate</a>.</span></label>
            </div>
            <div class="ob-nav">
              <button class="ob-back" type="button" data-prev><?= v2_ic('arrow-left') ?>Înapoi</button>
              <button class="btn btn-primary" id="ob-submit" type="submit">Trimite cererea<?= v2_ic('arrow-right') ?></button>
            </div>
          </fieldset>
        </form>

        <div class="ob-done" id="ob-done" hidden>
          <span class="ob-done-ic" aria-hidden="true"><?= v2_ic('check') ?></span>
          <h2 id="ob-done-h" tabindex="-1">Mulțumim!</h2>
          <p id="ob-done-p">Cererea ta a ajuns la echipa bilete.online. Îți scriem pe <strong id="ob-done-email"></strong> cu pașii următori. Dacă vrei ajutor, ne conectăm online oricând și îi parcurgem împreună.</p>
          <p class="ob-done-note" id="ob-done-note" hidden></p>
          <div class="ob-done-cta">
            <a class="btn btn-primary" id="ob-done-account" href="/organizator/panou" hidden>Intră în contul tău<?= v2_ic('arrow-right') ?></a>
            <a class="btn btn-ghost" href="/parteneri"><?= v2_ic('arrow-left') ?>Înapoi la prezentare</a>
          </div>
        </div>
      </div>
      <p class="ob-note">Fără cost de pornire · Activități nelimitate · Comision 2% plătit de cumpărător (4% dacă vinzi și prin alte canale)</p>
    </div>
  </section>
</main>
<?php include __DIR__ . '/includes/v2/footer.php'; ?>
