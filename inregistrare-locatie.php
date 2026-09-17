<?php
/**
 * Venue signup: /inregistrare-locatie (v2 design). Three-step onboarding form for venues and organizers.
 *
 * Step 1 what they sell (category cards or a short description), step 2 who they are, step 3 the venue. onboarding.js
 * checks each step before moving on (saying what's missing instead of greying the button out), then posts to the lead
 * pipeline (proxy leads.create → core LeadsController::create) and pings the funnel (leads.track page_view_onboarding)
 * on the bo_lead_sid session shared with /devino-partener. Core's English errors are said in Romanian and the form
 * goes back to the step that holds the field.
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

$pageTitleRaw = 'Înregistrare locație — ' . SITE_NAME;
$pageDescription = 'Începe în 5 minute. Spune-ne ce vinzi, cine ești și cum te contactăm. Te ghidăm prin restul.';
$canonicalUrl = SITE_URL . '/inregistrare-locatie';
$noindex = true;
$hideFromSitemap = true;
$skipPageCache = true;

$v2Styles = ['onboarding.css'];
$v2Scripts = ['onboarding.js'];
$v2HeaderOverlay = true;
$v2ClientData = ['supportEmail' => SUPPORT_EMAIL];
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
      <p class="ob-lead">Spune-ne ce vinzi, cine ești și cum te contactăm. Vorbim cu tine în următoarea zi lucrătoare și te ghidăm prin restul.</p>
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
            <p class="ob-step-p">Datele tale de contact. Le folosim doar ca să te sunăm și să-ți răspundem.</p>
            <div class="ob-fields">
              <div class="ob-field is-wide"><label for="ob-name">Nume și prenume *</label><input id="ob-name" name="contact_name" type="text" autocomplete="name" maxlength="120" required></div>
              <div class="ob-field"><label for="ob-email">Email *</label><input id="ob-email" name="email" type="email" inputmode="email" autocomplete="email" spellcheck="false" maxlength="160" required></div>
              <div class="ob-field"><label for="ob-phone">Telefon</label><input id="ob-phone" name="phone" type="tel" autocomplete="tel" maxlength="40" placeholder="07xx xxx xxx"></div>
            </div>
            <div class="ob-nav">
              <button class="ob-back" type="button" data-prev><?= v2_ic('arrow-left') ?>Înapoi</button>
              <button class="btn btn-primary" type="button" data-next>Continuă<?= v2_ic('arrow-right') ?></button>
            </div>
          </fieldset>

          <!-- STEP 3 — the venue -->
          <fieldset class="ob-step" data-step="3" hidden>
            <legend class="ob-step-h" tabindex="-1">Despre locație</legend>
            <p class="ob-step-p">Detaliile despre locația ta. Câteva minute și am terminat.</p>
            <div class="ob-fields">
              <div class="ob-field is-wide"><label for="ob-venue">Numele locației / organizației *</label><input id="ob-venue" name="location_name" type="text" autocomplete="organization" maxlength="160" required value="<?= v2_e($obPrefillLoc) ?>"></div>
              <div class="ob-field"><label for="ob-city">Oraș *</label><input id="ob-city" name="city" type="text" autocomplete="address-level2" maxlength="80" required placeholder="ex. Cluj-Napoca"></div>
              <div class="ob-field"><label for="ob-website">Site web (opțional)</label><input id="ob-website" name="website" type="text" inputmode="url" autocomplete="url" spellcheck="false" maxlength="200" placeholder="https://…"></div>
              <div class="ob-field is-wide"><label for="ob-volume">Volum estimat de bilete / lună</label><select class="select" id="ob-volume" name="volume_estimate"><?php foreach ($obVolumes as $volValue => $volLabel): ?><option value="<?= v2_e($volValue) ?>"><?= v2_e($volLabel) ?></option><?php endforeach; ?></select></div>
              <div class="ob-field is-wide"><label for="ob-notes">Spune-ne ce e important (opțional)</label><textarea id="ob-notes" name="notes" rows="3" maxlength="800" placeholder="ex. avem sloturi la 30 min, vrem să integrăm cu casa de marcat existentă, etc."></textarea></div>
              <label class="ob-check is-wide"><input id="ob-gdpr" name="gdpr" type="checkbox" required><span>Sunt de acord cu prelucrarea datelor în scopul contactării — datele sunt folosite doar pentru a-ți răspunde.</span></label>
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
          <p>Cererea ta a ajuns la echipa bilete.online. Te contactăm în următoarea zi lucrătoare pe <strong id="ob-done-email"></strong>.</p>
          <a class="btn btn-ghost" href="/devino-partener"><?= v2_ic('arrow-left') ?>Înapoi la prezentare</a>
        </div>
      </div>
      <p class="ob-note">Fără cost de pornire · Activități nelimitate · Comision 2%* plătit de cumpărător</p>
    </div>
  </section>
</main>
<?php include __DIR__ . '/includes/v2/footer.php'; ?>
