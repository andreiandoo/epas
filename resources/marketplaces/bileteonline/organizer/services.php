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
 * bilete.online sells more than single activities, so two things differ from the shared core flow (both guarded in
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

$pageTitleRaw = 'Servicii extra — ' . SITE_NAME;
$pageDescription = 'Promovare și tracking pentru activitățile unui operator pe bilete.online.';
$canonicalUrl = SITE_URL . '/organizator/servicii';
$noindex = true;
$skipPageCache = true;

$v2Styles = ['organizer.css', 'org-services.css'];
$v2Scripts = ['organizer.js', 'org-services.js'];
$v2LegacyScripts = ['assets/js/config.js', 'assets/js/utils.js', 'assets/js/api.js', 'assets/js/auth.js'];
$v2HeadExtra = v2_account_client_config('organizer');

$oxLocations = [
    ['home_hero', 'Prima pagină - Hero', 'Vizibilitate maximă, banner principal'],
    ['home_recommendations', 'Prima pagină - Recomandări', 'Secțiunea de recomandări'],
    ['category', 'Pagina categoriei', 'Audiență targetată pe categorie'],
    ['city', 'Pagina orașului', 'Audiență locală din orașul tău'],
];
$oxPlatforms = [
    ['facebook', 'Facebook Pixel', 'Track conversii și retargeting', 'Facebook Pixel ID', '1234567890123456'],
    ['google', 'Google Ads', 'Conversion tracking complet', 'Google Ads Conversion ID', 'AW-XXXXXXXXX'],
    ['tiktok', 'TikTok Pixel', 'Audiență tânără targetată', 'TikTok Pixel ID', 'CXXXXXXXXXXXXXXXXX'],
];

include __DIR__ . '/../includes/v2/head.php';
v2_org_start('services');
?>
<div class="ox" id="ox">
  <div class="ox-banner is-ok" id="ox-success" role="status" hidden>
    <span class="ox-banner-ic"><?= v2_ic('check') ?></span>
    <div><b id="ox-success-h">Succes!</b><p id="ox-success-p">Operațiunea a fost finalizată cu succes.</p></div>
    <button class="ox-x" type="button" data-dismiss="ox-success" aria-label="Închide"><?= v2_ic('x') ?></button>
  </div>
  <div class="ox-banner is-wait" id="ox-cancelled" role="status" hidden>
    <span class="ox-banner-ic"><?= v2_ic('warning-circle') ?></span>
    <div><b>Plată anulată</b><p>Plata a fost anulată. Poți încerca din nou oricând dorești.</p></div>
    <button class="ox-x" type="button" data-dismiss="ox-cancelled" aria-label="Închide"><?= v2_ic('x') ?></button>
  </div>

  <header class="ox-head">
    <div><p class="org-k">Marketing</p><h1 class="ox-h">Servicii extra</h1><p class="ox-lead">Promovează-ți locațiile și activitățile și urmărește-ți campaniile de reclame.</p></div>
    <a class="btn btn-ghost" href="/organizator/servicii/comenzi"><?= v2_ic('receipt') ?>Comenzile mele</a>
  </header>

  <section class="ox-cards" aria-label="Servicii">
    <article class="ox-card is-feat">
      <span class="ox-card-ic"><?= v2_ic('lightning') ?></span>
      <h2 class="ox-card-h">Promovare activitate</h2>
      <p>Afișează activitatea ta pe prima pagină, în secțiunea de recomandări, pe pagina categoriei sau a orașului activității.</p>
      <ul class="ox-tags"><li>Hero prima pagină</li><li>Recomandări</li><li>Categorie</li><li>Oraș</li></ul>
      <p class="ox-price">De la <b id="ox-price-feat">—</b> / zi</p>
      <button class="btn btn-primary" type="button" data-open="featuring"><?= v2_ic('plus') ?>Cumpără promovare</button>
    </article>
    <article class="ox-card is-loc">
      <span class="ox-card-ic"><?= v2_ic('map-trifold') ?></span>
      <h2 class="ox-card-h">Promovare locație</h2>
      <p>Scoate în față o locație întreagă — pagina ei și toate produsele pe care le vinzi acolo — nu doar o singură activitate.</p>
      <ul class="ox-tags"><li>Hero prima pagină</li><li>Recomandări</li><li>Categorie</li><li>Oraș</li></ul>
      <p class="ox-price">De la <b id="ox-price-loc">—</b> / zi</p>
      <button class="btn btn-primary" type="button" data-open="location_featuring"><?= v2_ic('plus') ?>Cumpără promovare</button>
    </article>
    <article class="ox-card is-track">
      <span class="ox-card-ic"><?= v2_ic('chart-line-up') ?></span>
      <h2 class="ox-card-h">Tracking campanii ads</h2>
      <p>Conectezi Facebook, Google sau TikTok o singură dată și urmărești conversiile pentru tot ce vinzi pe <?= htmlspecialchars(SITE_NAME) ?>: locații, experiențe și produse. Nu se cumpără pe activitate.</p>
      <ul class="ox-tags"><li>Facebook Ads</li><li>Google Ads</li><li>TikTok Ads</li><li>Tot contul</li></ul>
      <p class="ox-price">De la <b id="ox-price-track">—</b> / lună</p>
      <button class="btn btn-primary" type="button" data-open="tracking"><?= v2_ic('plus') ?>Activează tracking</button>
    </article>
  </section>

  <section class="org-panel" aria-labelledby="ox-list-h">
    <div class="org-panel-head">
      <div><h2 class="org-panel-h" id="ox-list-h">Servicii active</h2><p class="org-panel-p" id="ox-list-p"></p></div>
      <span class="ox-select"><select id="ox-filter" aria-label="Filtrează după serviciu"><option value="">Toate</option><option value="featuring">Promovare activitate</option><option value="location_featuring">Promovare locație</option><option value="email">Email marketing</option><option value="tracking">Ad tracking</option><option value="campaign">Campanii ads</option></select><?= v2_ic('caret-down') ?></span>
    </div>
    <div class="ox-table-wrap"><table class="ox-table">
      <thead><tr><th scope="col">Serviciu</th><th scope="col">Se aplică la</th><th scope="col">Detalii</th><th scope="col">Perioadă</th><th scope="col">Status</th><th scope="col" class="ox-right">Acțiuni</th></tr></thead>
      <tbody id="ox-rows"><tr><td colspan="6" class="ox-state">Se încarcă…</td></tr></tbody>
    </table></div>
  </section>

  <dialog class="ox-dialog" id="ox-d" aria-labelledby="ox-d-h">
    <form class="ox-d-inner" id="ox-form" novalidate>
      <div class="ox-d-head"><h2 class="ox-d-h" id="ox-d-h">Configurează serviciul</h2><button class="ox-x" type="button" data-close aria-label="Închide"><?= v2_ic('x') ?></button></div>
      <ol class="ox-steps"><li data-step="1"><b>1</b><span data-step-label>Selectează activitatea</span></li><li data-step="2"><b>2</b><span data-step-label>Configurează</span></li><li data-step="3"><b>3</b><span data-step-label>Plată</span></li></ol>

      <div class="ox-step" id="ox-step-1">
        <div class="ox-f" id="ox-pick-event"><label class="ox-f-l" for="ox-event">Activitatea</label><span class="ox-select"><select id="ox-event"><option value="">Alege o activitate…</option></select><?= v2_ic('caret-down') ?></span><span class="ox-err" id="ox-event-err" hidden></span></div>
        <div class="ox-f" id="ox-pick-place" hidden><label class="ox-f-l" for="ox-place">Locația</label><span class="ox-select"><select id="ox-place"><option value="">Alege o locație…</option></select><?= v2_ic('caret-down') ?></span><span class="ox-help">Promovarea acoperă pagina locației și produsele pe care le vinzi acolo.</span><span class="ox-err" id="ox-place-err" hidden></span></div>
        <div class="ox-ev" id="ox-ev" hidden><span class="ox-ev-img" id="ox-ev-img"></span><div><b id="ox-ev-name"></b><small id="ox-ev-date"></small><small id="ox-ev-venue"></small></div></div>
      </div>

      <div class="ox-step" id="ox-step-2" hidden>
        <fieldset class="ox-fs" id="ox-feat" hidden>
          <legend id="ox-feat-legend">Unde vrei să apară activitatea?</legend>
          <div class="ox-opts">
            <?php foreach ($oxLocations as [$oxKey, $oxLabel, $oxDesc]): ?>
            <div class="ox-opt">
              <label><input type="checkbox" name="ox-loc" value="<?= $oxKey ?>"><span><b><?= $oxLabel ?></b><small><?= $oxDesc ?></small><em data-price-loc="<?= $oxKey ?>">— / zi</em></span></label>
              <button class="ox-peek" type="button" data-peek="<?= $oxKey ?>" aria-label="Previzualizează: <?= $oxLabel ?>"><?= v2_ic('eye') ?></button>
            </div>
            <?php endforeach; ?>
          </div>
          <span class="ox-err" id="ox-loc-err" hidden></span>
          <div class="ox-grid2">
            <div class="ox-f"><label class="ox-f-l" for="ox-start">Data început</label><input id="ox-start" type="date"></div>
            <div class="ox-f"><label class="ox-f-l" for="ox-end">Data sfârșit</label><input id="ox-end" type="date"></div>
          </div>
          <span class="ox-err" id="ox-dates-err" hidden></span>
        </fieldset>
        <fieldset class="ox-fs" id="ox-track" hidden>
          <legend>Platforme de tracking</legend>
          <p class="ox-note">Tracking-ul se activează pentru <b>tot contul tău</b>: toate locațiile, experiențele și produsele pe care le vinzi pe <?= htmlspecialchars(SITE_NAME) ?>. Nu trebuie să alegi o activitate.</p>
          <p class="ox-help">Bifează platformele dorite. Pixel ID-ul poate fi completat acum (opțional) sau mai târziu din contul tău.</p>
          <div class="ox-plats">
            <?php foreach ($oxPlatforms as [$oxKey, $oxLabel, $oxDesc, $oxIdLabel, $oxPh]): ?>
            <div class="ox-plat">
              <label><input type="checkbox" name="ox-plat" value="<?= $oxKey ?>"><span><b><?= $oxLabel ?></b><small><?= $oxDesc ?></small></span><em data-price-plat>— / lună</em></label>
              <div class="ox-f ox-pixel" id="ox-pixel-f-<?= $oxKey ?>" hidden><label class="ox-f-l" for="ox-pixel-<?= $oxKey ?>"><?= $oxIdLabel ?> <small>(opțional)</small></label><input id="ox-pixel-<?= $oxKey ?>" type="text" maxlength="50" spellcheck="false" placeholder="<?= $oxPh ?>"><span class="ox-help">Lasă gol dacă vrei să-l completezi mai târziu din contul tău.</span></div>
            </div>
            <?php endforeach; ?>
          </div>
          <span class="ox-err" id="ox-plat-err" hidden></span>
          <div class="ox-f"><label class="ox-f-l" for="ox-duration">Durata abonamentului</label><span class="ox-select"><select id="ox-duration"><option value="1">1 lună</option><option value="3" selected>3 luni (-10%)</option><option value="6">6 luni (-15%)</option><option value="12">12 luni (-25%)</option></select><?= v2_ic('caret-down') ?></span></div>
        </fieldset>
      </div>

      <div class="ox-step" id="ox-step-3" hidden>
        <div class="ox-summary"><h3>Sumar comandă</h3><dl id="ox-summary"></dl><p class="ox-total"><span>Total de plată</span><b id="ox-total">—</b></p></div>
        <fieldset class="ox-fs">
          <legend>Metoda de plată</legend>
          <label class="ox-pay"><input type="radio" name="ox-pay" value="card" checked><span><b>Card bancar</b><small>Visa, Mastercard, Maestro · prin Netopia Payments</small></span></label>
          <label class="ox-pay"><input type="radio" name="ox-pay" value="transfer"><span><b>Transfer bancar</b><small>Activare în 1-2 zile lucrătoare</small></span></label>
          <p class="ox-note" id="ox-pay-card">Vei fi redirecționat către Netopia Payments pentru a finaliza tranzacția în siguranță.</p>
          <p class="ox-note" id="ox-pay-transfer" hidden>Îți trimitem pe email instrucțiunile de plată prin transfer bancar. Serviciul se activează după confirmarea plății (1-2 zile lucrătoare).</p>
        </fieldset>
      </div>

      <div class="ox-form-err" id="ox-form-err" role="alert" hidden></div>
      <div class="ox-d-act">
        <button class="btn btn-ghost" type="button" id="ox-back" hidden><?= v2_ic('arrow-left') ?>Înapoi</button>
        <button class="btn btn-primary" type="button" id="ox-next">Continuă<?= v2_ic('arrow-right') ?></button>
        <button class="btn btn-primary" type="submit" id="ox-pay" hidden><?= v2_ic('lock-simple') ?><span data-label>Plătește acum</span></button>
      </div>
    </form>
  </dialog>

  <dialog class="ox-dialog is-wide" id="ox-peek-d" aria-labelledby="ox-peek-h">
    <div class="ox-d-inner">
      <div class="ox-d-head"><h2 class="ox-d-h" id="ox-peek-h">Previzualizare plasament</h2><button class="ox-x" type="button" data-close aria-label="Închide"><?= v2_ic('x') ?></button></div>
      <p class="ox-note" id="ox-peek-p"></p>
      <div class="ox-mock" id="ox-mock"></div>
      <div class="ox-d-act"><button class="btn btn-primary" type="button" data-close>Închide</button></div>
    </div>
  </dialog>
</div>
<?php
v2_org_end();
include __DIR__ . '/../includes/v2/foot.php';
