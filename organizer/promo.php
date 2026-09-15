<?php
/**
 * Organizer promo codes: /organizator/promo (promo.php), v2 design.
 *
 * Inside the v2 organizer shell. Every section of the old page, restyled: the figures (active codes, uses, discount
 * given, order value), search and the state filter, the code cards (state, code with copy, discount, activity, uses and
 * limit, expiry, the admin badge) with the "new code" tile, and the create / edit window (code with a generator,
 * percentage or fixed amount, activity, ticket types, total and per-customer limits, start and end dates).
 * org-promo.js reads /organizer/promo-codes, /organizer/events and each activity's ticket types through the proxy.
 *
 * Fixed on the way:
 * - editing sent the code, discount and activity, which core ignores on update: they are shown locked, with why;
 * - "Venituri generate" was always 0 and "Reduceri acordate" multiplied uses by the value (wrong for percentages):
 *   both now add up each code's /stats (discount given, order value);
 * - a code "valid until" a day stopped at 03:00 that day (core keeps UTC): days are sent as Bucharest 00:00-23:59:59;
 * - the "Dezactivate" filter matched nothing (core says inactive), expired / used-up / not-yet-started codes looked
 *   active, a limit of 0 was accepted by the form and refused by core;
 * - new: pause and reactivate a code, the orders that used it, minimum order / maximum discount / minimum tickets;
 * - errors came as browser alerts or English API text, deleting asked with confirm().
 */

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/api.php';
require_once __DIR__ . '/../includes/nav-helpers.php';
require_once __DIR__ . '/../includes/v2/helpers.php';
require_once __DIR__ . '/../includes/v2/organizer.php';

$pageTitleRaw = 'Coduri promoționale — ' . SITE_NAME;
$pageDescription = 'Codurile de reducere ale unui organizator pe bilete.online: creare, limite, perioade și utilizări.';
$canonicalUrl = SITE_URL . '/organizator/promo';
$noindex = true;
$skipPageCache = true;

$v2Styles = ['organizer.css', 'org-promo.css'];
$v2Scripts = ['organizer.js', 'org-promo.js'];
$v2LegacyScripts = ['assets/js/config.js', 'assets/js/utils.js', 'assets/js/api.js', 'assets/js/auth.js'];
$v2HeadExtra = v2_account_client_config();

$opStat = function (string $key, string $label, string $icon, string $note = '') {
    return '<article class="op-stat"><span class="op-stat-ic">' . v2_ic($icon) . '</span><div><p class="op-stat-k">' . $label . '</p>'
        . '<p class="op-stat-v" id="op-s-' . $key . '"><span class="org-skel op-sk"></span></p>'
        . ($note !== '' ? '<p class="op-stat-p">' . $note . '</p>' : '') . '</div></article>';
};
$opX = '<button class="op-x" type="button" data-close aria-label="Închide">' . v2_ic('x') . '</button>';
$opErr = function (string $id) { return '<span class="op-err" id="' . $id . '-err" hidden></span>'; };

include __DIR__ . '/../includes/v2/head.php';
v2_org_start('promo');
?>
<div class="op" id="op">
  <header class="op-head">
    <div class="op-head-t">
      <p class="org-k">Marketing</p>
      <h1 class="op-h">Coduri promoționale</h1>
      <p class="op-lead">Creează și gestionează coduri de reducere pentru activitățile tale.</p>
    </div>
    <div class="op-actions"><button class="btn btn-primary" type="button" id="op-add" disabled><?= v2_ic('plus') ?>Cod nou</button></div>
  </header>

  <section class="op-stats" aria-label="Pe scurt">
    <?= $opStat('active', 'Coduri active', 'tag', 'Pot fi folosite acum') ?>
    <?= $opStat('uses', 'Utilizări', 'check-circle', 'Comenzi cu cod, în total') ?>
    <?= $opStat('discount', 'Reduceri acordate', 'receipt', 'Suma scăzută din comenzi') ?>
    <?= $opStat('revenue', 'Venituri generate', 'chart-line-up', 'Valoarea comenzilor cu cod') ?>
  </section>
  <p class="op-note" id="op-s-note" hidden></p>

  <div class="org-empty is-error" id="op-load-err" role="alert" hidden>
    <span class="org-empty-ic"><?= v2_ic('warning-circle') ?></span>
    <b>Nu am putut încărca codurile</b>
    <p>Verifică conexiunea și încearcă din nou.</p>
    <div class="op-empty-cta"><button class="btn btn-primary" type="button" id="op-retry">Reîncearcă</button></div>
  </div>

  <section class="org-panel op-panel" id="op-panel" aria-labelledby="op-list-h">
    <div class="org-panel-head">
      <div><p class="org-k">Coduri</p><h2 class="org-panel-h" id="op-list-h">Codurile tale</h2><p class="org-panel-p" id="op-list-p"></p></div>
      <div class="op-filters">
        <label class="op-search"><?= v2_ic('magnifying-glass') ?><input id="op-q" type="search" autocomplete="off" spellcheck="false" placeholder="Caută cod…" aria-label="Caută după cod sau activitate" aria-controls="op-grid"></label>
        <span class="op-select"><select id="op-status" aria-label="Arată codurile după stare" aria-controls="op-grid">
          <option value="">Toate</option>
          <option value="active">Active</option>
          <option value="scheduled">Programate</option>
          <option value="expired">Expirate</option>
          <option value="exhausted">Epuizate</option>
          <option value="inactive">Dezactivate</option>
        </select><?= v2_ic('caret-down') ?></span>
      </div>
    </div>
    <ul class="op-grid" id="op-grid" aria-live="polite">
      <li class="op-sk-card"><span class="org-skel"></span></li><li class="op-sk-card"><span class="org-skel"></span></li><li class="op-sk-card"><span class="org-skel"></span></li>
    </ul>
  </section>

  <dialog class="op-dialog is-wide" id="op-code-d" aria-labelledby="op-code-h">
    <form class="op-d-inner" id="op-code-form" novalidate>
      <div class="op-d-head"><div><h2 class="op-d-h" id="op-code-h">Cod promoțional nou</h2><p class="op-d-p" id="op-code-p"></p></div><?= $opX ?></div>

      <fieldset class="op-lock" id="op-fixed">
        <legend class="op-sr">Codul și reducerea</legend>
        <div class="op-grid2">
          <div class="op-f op-wide">
            <label class="op-f-l" for="op-code">Codul promoțional</label>
            <span class="op-codefield">
              <input id="op-code" type="text" maxlength="50" autocomplete="off" spellcheck="false" autocapitalize="characters" placeholder="ex: VARA2026" aria-describedby="op-code-help op-code-err">
              <button class="op-gen" type="button" id="op-gen"><?= v2_ic('arrow-counter-clockwise') ?>Generează</button>
            </span>
            <span class="op-help" id="op-code-help">3–50 caractere: litere fără diacritice, cifre, - sau _.</span>
            <?= $opErr('op-code') ?>
          </div>
          <fieldset class="op-fs op-wide" id="op-type">
            <legend>Tipul reducerii</legend>
            <div class="op-types">
              <label class="op-type"><input type="radio" name="op-type" value="percentage" checked><span><b>Procent</b><small>Ex: 10% reducere</small></span></label>
              <label class="op-type"><input type="radio" name="op-type" value="fixed"><span><b>Sumă fixă</b><small>Ex: 50 lei reducere</small></span></label>
            </div>
          </fieldset>
          <div class="op-f">
            <label class="op-f-l" for="op-value">Valoarea reducerii</label>
            <span class="op-suffix"><input id="op-value" type="number" inputmode="decimal" min="0.01" max="100" step="0.01" aria-describedby="op-value-err"><span id="op-value-suffix" aria-hidden="true">%</span></span>
            <?= $opErr('op-value') ?>
          </div>
          <div class="op-f">
            <label class="op-f-l" for="op-event">Activitatea</label>
            <span class="op-select"><select id="op-event" aria-describedby="op-event-help op-event-err"><option value="">— Alege o activitate —</option></select><?= v2_ic('caret-down') ?></span>
            <span class="op-help" id="op-event-help">Codul se aplică doar la această activitate.</span>
            <?= $opErr('op-event') ?>
          </div>
        </div>
      </fieldset>

      <fieldset class="op-fs" id="op-tts">
        <legend>Tipuri de bilete</legend>
        <div class="op-tts-head">
          <span class="op-help" id="op-tts-n" aria-live="polite">Bifează unul sau mai multe. Codul funcționează doar pentru tipurile bifate.</span>
          <span class="op-tts-all" id="op-tts-all" hidden><button class="op-linkbtn" type="button" data-tts="all">Toate</button><button class="op-linkbtn" type="button" data-tts="none">Niciunul</button></span>
        </div>
        <ul class="op-tt-list" id="op-tt-list"><li class="op-msg">Alege mai întâi activitatea.</li></ul>
        <?= $opErr('op-tts') ?>
      </fieldset>

      <div class="op-grid2">
        <div class="op-f">
          <label class="op-f-l" for="op-limit">Limită utilizări totale</label>
          <input id="op-limit" type="number" inputmode="numeric" min="1" step="1" placeholder="Nelimitat" aria-describedby="op-limit-help op-limit-err">
          <span class="op-help" id="op-limit-help">Câte comenzi pot folosi codul, în total.</span>
          <?= $opErr('op-limit') ?>
        </div>
        <div class="op-f">
          <label class="op-f-l" for="op-limit-cust">Limită per client</label>
          <input id="op-limit-cust" type="number" inputmode="numeric" min="1" step="1" placeholder="Nelimitat" aria-describedby="op-limit-cust-help op-limit-cust-err">
          <span class="op-help" id="op-limit-cust-help">De câte ori îl poate folosi același client.</span>
          <?= $opErr('op-limit-cust') ?>
        </div>
        <div class="op-f">
          <label class="op-f-l" for="op-start">Data de început</label>
          <input id="op-start" type="date" aria-describedby="op-start-help op-start-err">
          <span class="op-help" id="op-start-help">De la ora 00:00.</span>
          <?= $opErr('op-start') ?>
        </div>
        <div class="op-f">
          <label class="op-f-l" for="op-end">Data de sfârșit</label>
          <input id="op-end" type="date" aria-describedby="op-end-help op-end-err">
          <span class="op-help" id="op-end-help">Codul funcționează până la sfârșitul acestei zile.</span>
          <?= $opErr('op-end') ?>
        </div>
      </div>

      <details class="op-more" id="op-more">
        <summary><?= v2_ic('caret-down') ?>Condiții opționale</summary>
        <div class="op-grid2">
          <div class="op-f">
            <label class="op-f-l" for="op-min-amount">Comandă minimă</label>
            <span class="op-suffix"><input id="op-min-amount" type="number" inputmode="decimal" min="0" step="0.01" placeholder="Fără minim" aria-describedby="op-min-amount-err"><span class="op-cur" aria-hidden="true">lei</span></span>
            <?= $opErr('op-min-amount') ?>
          </div>
          <div class="op-f" id="op-max-f">
            <label class="op-f-l" for="op-max-disc">Reducere maximă</label>
            <span class="op-suffix"><input id="op-max-disc" type="number" inputmode="decimal" min="0" step="0.01" placeholder="Fără plafon" aria-describedby="op-max-disc-help op-max-disc-err"><span class="op-cur" aria-hidden="true">lei</span></span>
            <span class="op-help" id="op-max-disc-help">Plafonul reducerii procentuale, pe comandă.</span>
            <?= $opErr('op-max-disc') ?>
          </div>
          <div class="op-f">
            <label class="op-f-l" for="op-min-tickets">Bilete minime în comandă</label>
            <input id="op-min-tickets" type="number" inputmode="numeric" min="1" step="1" placeholder="Fără minim" aria-describedby="op-min-tickets-err">
            <?= $opErr('op-min-tickets') ?>
          </div>
        </div>
      </details>

      <div class="op-form-err" id="op-form-err" role="alert" hidden></div>
      <div class="op-d-act"><button class="btn btn-ghost" type="button" data-close>Anulează</button><button class="btn btn-primary" type="submit" id="op-code-go"><span data-label>Creează codul</span></button></div>
    </form>
  </dialog>

  <dialog class="op-dialog is-wide" id="op-usage-d" aria-labelledby="op-usage-h">
    <div class="op-d-inner">
      <div class="op-d-head"><div><h2 class="op-d-h" id="op-usage-h">Utilizările codului</h2><p class="op-d-p" id="op-usage-p"></p></div><?= $opX ?></div>
      <dl class="op-ustats">
        <div><dt>Utilizări</dt><dd id="op-u-uses">—</dd></div>
        <div><dt>Clienți unici</dt><dd id="op-u-customers">—</dd></div>
        <div><dt>Reducere acordată</dt><dd id="op-u-discount">—</dd></div>
        <div><dt>Valoarea comenzilor</dt><dd id="op-u-orders">—</dd></div>
      </dl>
      <ul class="op-uses" id="op-uses" tabindex="-1" aria-live="polite"></ul>
      <div class="op-more-row"><button class="btn btn-ghost op-sm" type="button" id="op-usage-more" hidden><span data-label>Încarcă mai multe</span></button></div>
      <div class="op-d-act"><button class="btn btn-primary" type="button" data-close>Închide</button></div>
    </div>
  </dialog>

  <dialog class="op-dialog is-small" id="op-del-d" aria-labelledby="op-del-h" aria-describedby="op-del-p">
    <div class="op-d-inner">
      <h2 class="op-d-h" id="op-del-h">Ștergi codul?</h2>
      <p class="op-d-p" id="op-del-p">Codul nu mai poate fi folosit la comenzi noi. Comenzile în care a fost folosit rămân la fel.</p>
      <div class="op-form-err" id="op-del-err" role="alert" hidden></div>
      <div class="op-d-act"><button class="btn btn-ghost" type="button" data-close>Renunță</button><button class="btn op-danger" type="button" id="op-del-go"><span data-label>Șterge codul</span></button></div>
    </div>
  </dialog>
</div>
<?php
v2_org_end();
include __DIR__ . '/../includes/v2/foot.php';
