<?php
/**
 * Operator support tickets: /organizator/suport (support.php), v2 design.
 *
 * Inside the v2 operator shell. Every section of the old page, restyled: the "Tichet nou" button, the beta gate notice
 * (core answers 403 while the account is not on the support allow-list), the status filters (Active, Toate, Rezolvate,
 * Închise), the ticket list (number, status, department, subject, messages, last activity, opened on), the loading and
 * empty states, and the new-ticket dialog: step 1 is the department (with its description), the problem type and the
 * extra fields that type asks for (page URL, decont series and number, affected module, activity); step 2 is the
 * subject, the description and the attachments (types, size and count from core's rules, with a preview).
 * org-support.js reads /organizer/support/tickets and /organizer/support/departments through the proxy and sends the
 * ticket as multipart (BileteOnlineAPI.organizer.createSupportTicket) with the page context, like before.
 *
 * Changed on the way:
 * - a failed list load showed the empty state plus a toast: it now says so, with "Reîncearcă";
 * - core pages the list by 20: "Încarcă mai multe" reaches the older tickets;
 * - the success message is shown on the ticket page the operator is sent to (it used to vanish with the redirect);
 * - the problem type's description from core is shown under the select; an activity field with no activities in the
 *   account says so instead of offering an empty list.
 */

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/api.php';
require_once __DIR__ . '/../includes/nav-helpers.php';
require_once __DIR__ . '/../includes/v2/helpers.php';
require_once __DIR__ . '/../includes/v2/organizer.php';

$pageTitleRaw = 'Tichete suport — ' . SITE_NAME;
$pageDescription = 'Tichetele de suport ale unui operator pe bilete.online: deschide un tichet și urmărește răspunsul echipei.';
$canonicalUrl = SITE_URL . '/organizator/suport';
$noindex = true;
$skipPageCache = true;

$v2Styles = ['organizer.css', 'org-support.css'];
$v2Scripts = ['organizer.js', 'org-support.js'];
$v2LegacyScripts = ['assets/js/config.js', 'assets/js/utils.js', 'assets/js/api.js', 'assets/js/auth.js'];
$v2HeadExtra = v2_account_client_config('organizer');

$spFilters = [['open', 'Active'], ['', 'Toate'], ['resolved', 'Rezolvate'], ['closed', 'Închise']];

include __DIR__ . '/../includes/v2/head.php';
v2_org_start('support');
?>
<div class="osp" id="osp">
  <header class="osp-head">
    <div>
      <p class="org-k">Suport</p>
      <h1 class="osp-h">Tichete suport</h1>
      <p class="osp-lead">Deschide un tichet către echipa de suport și urmărește statusul.</p>
    </div>
    <button class="btn btn-primary" type="button" data-osp-new><?= v2_ic('plus') ?>Tichet nou</button>
  </header>

  <div class="osp-gate" id="osp-gate" role="note" tabindex="-1" hidden>
    <span class="osp-gate-ic"><?= v2_ic('warning-circle') ?></span>
    <div>
      <h2 class="osp-gate-h">Sistemul de tichete este în testare</h2>
      <p>Accesul nu este încă activat pentru contul tău. Pentru întrebări urgente, contactează echipa pe canalele obișnuite.</p>
      <a class="osp-link" href="/organizator/help#contact">Vezi datele de contact<?= v2_ic('arrow-right') ?></a>
    </div>
  </div>

  <section class="org-panel osp-panel" id="osp-panel" aria-labelledby="osp-list-h">
    <div class="org-panel-head">
      <div><p class="org-k">Istoric</p><h2 class="org-panel-h" id="osp-list-h">Tichetele tale</h2><p class="org-panel-p" id="osp-list-p"></p></div>
      <div class="osp-seg" role="group" aria-label="Filtrează după status">
        <?php foreach ($spFilters as $i => [$spValue, $spLabel]): ?><button type="button" data-status="<?= $spValue ?>" aria-pressed="<?= $i === 0 ? 'true' : 'false' ?>" aria-controls="osp-list"><?= $spLabel ?></button><?php endforeach; ?>
      </div>
    </div>
    <p class="sr" id="osp-live" role="status" aria-live="polite">Se încarcă…</p>
    <ul class="osp-list" id="osp-list">
      <li class="osp-sk" aria-hidden="true"><span class="org-skel"></span></li>
      <li class="osp-sk" aria-hidden="true"><span class="org-skel"></span></li>
      <li class="osp-sk" aria-hidden="true"><span class="org-skel"></span></li>
    </ul>
    <div class="org-empty" id="osp-empty" hidden>
      <span class="org-empty-ic"><?= v2_ic('envelope-simple') ?></span>
      <b>Nu ai niciun tichet aici</b>
      <p>Când ai o problemă sau o întrebare, deschide un tichet și echipa noastră îți răspunde.</p>
      <button class="btn btn-primary" type="button" data-osp-new><?= v2_ic('plus') ?>Deschide primul tichet</button>
    </div>
    <div class="org-empty is-error" id="osp-error" role="alert" hidden>
      <span class="org-empty-ic"><?= v2_ic('warning-circle') ?></span>
      <b>Nu am putut încărca tichetele</b>
      <p>Reîncearcă în câteva secunde.</p>
      <button class="btn btn-primary" type="button" id="osp-retry">Reîncearcă</button>
    </div>
    <div class="osp-more-row"><button class="btn btn-ghost" type="button" id="osp-more" hidden><span data-label>Încarcă mai multe</span></button></div>
  </section>

  <!-- ============ NEW TICKET ============ -->
  <dialog class="osp-dialog" id="osp-new" aria-labelledby="osp-new-h">
    <form class="osp-form" id="osp-form" novalidate>
      <div class="osp-d-head">
        <div>
          <p class="org-k">Suport</p>
          <h2 class="osp-d-h" id="osp-new-h">Tichet nou</h2>
        </div>
        <button class="osp-x" type="button" data-close aria-label="Închide"><?= v2_ic('x') ?></button>
      </div>
      <ol class="osp-steps" aria-label="Pașii tichetului">
        <li id="osp-st-1" aria-current="step"><span>1</span>Categoria problemei</li>
        <li id="osp-st-2"><span>2</span>Detalii</li>
      </ol>
      <div class="osp-d-body" id="osp-d-body">
        <fieldset class="osp-fs">
          <legend class="osp-step-k" id="osp-s1-h">Pasul 1 — Categoria problemei</legend>
          <div class="osp-f">
            <label class="osp-l" for="osp-dept">Departament <span class="osp-req" aria-hidden="true">*</span></label>
            <span class="osp-select"><select id="osp-dept" required aria-describedby="osp-dept-desc"><option value="">Alege un departament…</option></select><?= v2_ic('caret-down') ?></span>
            <p class="osp-help" id="osp-dept-desc" hidden></p>
            <p class="osp-help" id="osp-dept-msg" role="status"></p>
          </div>
          <div class="osp-f" id="osp-pt-f" hidden>
            <label class="osp-l" for="osp-pt">Tip problemă <span class="osp-req" aria-hidden="true">*</span></label>
            <span class="osp-select"><select id="osp-pt" required aria-describedby="osp-pt-desc"><option value="">Alege tipul…</option></select><?= v2_ic('caret-down') ?></span>
            <p class="osp-help" id="osp-pt-desc" hidden></p>
          </div>
          <div class="osp-cf" id="osp-cf" hidden></div>
        </fieldset>

        <fieldset class="osp-fs osp-step2" id="osp-step2" hidden>
          <legend class="osp-step-k" id="osp-s2-h">Pasul 2 — Detalii</legend>
          <div class="osp-f">
            <label class="osp-l" for="osp-subject">Subiect <span class="osp-req" aria-hidden="true">*</span></label>
            <input class="osp-in" type="text" id="osp-subject" required maxlength="255" autocomplete="off" placeholder="Pe scurt: ce s-a întâmplat?">
          </div>
          <div class="osp-f">
            <label class="osp-l" for="osp-desc">Descriere <span class="osp-req" aria-hidden="true">*</span></label>
            <textarea class="osp-in" id="osp-desc" required rows="6" maxlength="10000" aria-describedby="osp-desc-n" placeholder="Descrie problema în detaliu: când s-a întâmplat, ce ai făcut înainte, mesajul de eroare exact. Cu cât mai multe informații, cu atât răspundem mai repede."></textarea>
            <p class="osp-help osp-count" id="osp-desc-n">0 / 10.000</p>
          </div>
          <div class="osp-f">
            <label class="osp-l" for="osp-files">Atașamente <small>(opțional)</small></label>
            <input class="osp-file" type="file" id="osp-files" multiple accept=".jpg,.jpeg,.png,.pdf" aria-describedby="osp-files-help">
            <p class="osp-help" id="osp-files-help">jpg, png, pdf — maxim 3 MB pe fișier, max 5 fișiere.</p>
            <ul class="osp-files" id="osp-files-list" aria-label="Fișiere alese" hidden></ul>
          </div>
        </fieldset>
        <p class="osp-err" id="osp-form-err" role="alert" hidden></p>
      </div>
      <div class="osp-d-foot">
        <button class="btn btn-ghost" type="button" data-close>Renunță</button>
        <button class="btn btn-primary" type="submit" id="osp-send" disabled><span data-label>Trimite tichetul</span></button>
      </div>
    </form>
  </dialog>
</div>
<?php
v2_org_end();
include __DIR__ . '/../includes/v2/foot.php';
