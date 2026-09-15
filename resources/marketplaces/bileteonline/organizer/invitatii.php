<?php
/**
 * Organizer invitations: /organizator/invitatii?event={id} (invitatii.php), v2 design.
 *
 * Inside the v2 organizer shell. Every section of the old page, restyled: the activity, step 1 (series name and
 * quantity, or the seats on the map for seated activities, with the 50-per-series note), step 2 (the guests, typed in
 * a table or loaded from a CSV, with the CSV template), step 3 (done, download the ZIP, another series), the series of
 * this activity (counts, status, the guests with per-invitation PDF and delete, regenerate, ZIP) and the seat picker
 * (pan, zoom, legend, clear, confirm). org-invitations.js talks to /organizer/invitations* and the seating map.
 *
 * Fixed on the way:
 * - the quantity, the CSV and the seat picker allowed up to 1000 while core accepts 50 per series: all stop at 50;
 * - a CSV saved by Excel with ";" was refused; rows without a name or e-mail were dropped without a word;
 * - the seat map drew the venue's icon SVG as raw markup: it is rebuilt from its shapes only;
 * - a slow generation showed "failed" while core finished it (proxy timeout): the page says so and reloads the series;
 * - a sign-in page could be saved as the ZIP / PDF / CSV when the session had expired: downloads are checked first;
 * - every message was a browser alert or confirm; the seated flow had no series name;
 * - new: the label printed on the invitation and the watermark (core options), picking the activity on this page.
 */

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/api.php';
require_once __DIR__ . '/../includes/nav-helpers.php';
require_once __DIR__ . '/../includes/v2/helpers.php';
require_once __DIR__ . '/../includes/v2/organizer.php';

$pageTitleRaw = 'Invitații — ' . SITE_NAME;
$pageDescription = 'Invitații PDF cu cod QR pentru activitățile unui organizator pe bilete.online.';
$canonicalUrl = SITE_URL . '/organizator/invitatii';
$noindex = true;
$skipPageCache = true;

$v2Styles = ['organizer.css', 'org-invitations.css'];
$v2Scripts = ['organizer.js', 'org-invitations.js'];
$v2LegacyScripts = ['assets/js/config.js', 'assets/js/utils.js', 'assets/js/api.js', 'assets/js/auth.js'];
$v2HeadExtra = v2_account_client_config();

$oiX = '<button class="oi-x" type="button" data-close aria-label="Închide">' . v2_ic('x') . '</button>';

include __DIR__ . '/../includes/v2/head.php';
v2_org_start('events');
?>
<div class="oi" id="oi">
  <header class="oi-head">
    <a class="oi-back" href="/organizator/events"><?= v2_ic('arrow-left') ?>Înapoi la activități</a>
    <p class="org-k">Activități</p>
    <h1 class="oi-h">Invitații</h1>
    <p class="oi-lead">Generează invitații în format PDF pentru activitatea aleasă.</p>
  </header>

  <div class="oi-loading" id="oi-loading" aria-hidden="true"><span class="org-skel"></span></div>

  <section class="oi-event" id="oi-event" aria-labelledby="oi-event-name" hidden>
    <span class="oi-event-ic" aria-hidden="true"><?= v2_ic('envelope-simple') ?></span>
    <div class="oi-event-t">
      <h2 class="oi-event-h" id="oi-event-name"></h2>
      <p class="oi-event-meta">
        <span id="oi-event-date-w"><?= v2_ic('calendar-blank') ?><span id="oi-event-date"></span></span>
        <span id="oi-event-venue-w"><?= v2_ic('map-pin') ?><span id="oi-event-venue"></span></span>
        <span class="org-tag is-info" id="oi-event-seated" hidden>Cu hartă de locuri</span>
        <span class="org-tag is-muted" id="oi-event-over" hidden>Încheiată</span>
      </p>
    </div>
    <a class="oi-event-change" href="/organizator/invitatii">Altă activitate</a>
  </section>

  <section class="org-panel" id="oi-choose" aria-labelledby="oi-choose-h" hidden>
    <div class="oi-missing" id="oi-missing" role="status" hidden><?= v2_ic('warning-circle') ?><p id="oi-missing-p">Nu am găsit activitatea selectată. Alege o activitate din lista ta.</p></div>
    <div class="org-panel-head"><div><p class="org-k">Activitatea</p><h2 class="org-panel-h" id="oi-choose-h">Alege activitatea</h2><p class="org-panel-p">Invitațiile se generează pentru o singură activitate. Iată activitățile tale care n-au trecut încă.</p></div></div>
    <ul class="oi-events" id="oi-events" aria-live="polite"><li class="oi-msg">Se încarcă activitățile…</li></ul>
    <p class="oi-small"><a href="/organizator/events">Vezi toate activitățile</a></p>
  </section>

  <section class="org-panel oi-builder" id="oi-builder" aria-labelledby="oi-builder-h" hidden>
    <div class="org-panel-head oi-builder-head">
      <div><p class="org-k">Serie nouă</p><h2 class="org-panel-h" id="oi-builder-h">Generează invitații</h2></div>
      <ol class="oi-steps" aria-label="Pașii">
        <li data-step="1"><b>1</b><span id="oi-steps-1">Detalii serie</span></li>
        <li data-step="2"><b>2</b><span>Invitații</span></li>
        <li data-step="3"><b>3</b><span>Gata</span></li>
      </ol>
    </div>

    <div class="oi-step" id="oi-step-1">
      <h3 class="oi-step-h" id="oi-step1-h" tabindex="-1">Pasul 1 — Detalii serie</h3>
      <p class="oi-step-p" id="oi-step1-p">Dă un nume seriei (opțional, pentru organizare — ex. „Firma X”, „Sponsori”) și alege numărul de invitații.</p>
      <div class="oi-grid2">
        <div class="oi-f">
          <label class="oi-f-l" for="oi-name">Nume serie <small>(opțional)</small></label>
          <input id="oi-name" type="text" maxlength="120" autocomplete="off" placeholder="ex. Firma X - presa" aria-describedby="oi-name-help">
          <span class="oi-help" id="oi-name-help">Gol, seria primește numele activității și data.</span>
        </div>
        <div class="oi-f" id="oi-qty-f">
          <label class="oi-f-l" for="oi-qty">Număr invitații</label>
          <input id="oi-qty" type="number" inputmode="numeric" min="1" max="50" step="1" value="1" aria-describedby="oi-qty-err">
          <span class="oi-err" id="oi-qty-err" hidden></span>
        </div>
      </div>
      <div class="oi-seats" id="oi-seats" hidden>
        <p class="oi-step-p">Activitatea are hartă de locuri. Selectează locurile pe care vrei să le blochezi pentru invitații. Locurile alese vor fi marcate ca <strong>vândute</strong> și nu vor putea fi cumpărate de clienți.</p>
        <div class="oi-seats-row">
          <button class="btn btn-primary" type="button" id="oi-open-map"><?= v2_ic('map-trifold') ?>Alege locurile pe hartă</button>
          <span class="oi-seats-sum" id="oi-seats-sum" aria-live="polite">Niciun loc selectat.</span>
        </div>
        <ul class="oi-chips" id="oi-chips" aria-label="Locurile alese"></ul>
        <span class="oi-err" id="oi-seats-err" role="alert" hidden></span>
      </div>
      <details class="oi-more" id="oi-more">
        <summary><?= v2_ic('caret-down') ?>Opțiuni pentru PDF</summary>
        <div class="oi-grid2">
          <div class="oi-f">
            <label class="oi-f-l" for="oi-label">Eticheta de pe invitație <small>(opțional)</small></label>
            <input id="oi-label" type="text" maxlength="100" autocomplete="off" placeholder="ex. Invitație VIP" aria-describedby="oi-label-help">
            <span class="oi-help" id="oi-label-help">Apare pe bilet în locul cuvântului „Invitație”.</span>
          </div>
          <div class="oi-f">
            <label class="oi-f-l" for="oi-watermark">Filigran <small>(opțional)</small></label>
            <input id="oi-watermark" type="text" maxlength="50" autocomplete="off" placeholder="INVITATIE" aria-describedby="oi-watermark-help">
            <span class="oi-help" id="oi-watermark-help">Textul tipărit în partea de sus a fiecărei invitații.</span>
          </div>
        </div>
      </details>
      <div class="oi-note"><?= v2_ic('warning-circle') ?><p><strong>Maxim 50 de invitații per serie.</strong> Generarea poate dura câteva secunde — fiecare invitație produce un PDF cu QR + șablonul tău de bilet. Dacă ai nevoie de mai multe, creează mai multe serii.</p></div>
      <div class="oi-act"><button class="btn btn-primary" type="button" id="oi-next">Continuă<?= v2_ic('arrow-right') ?></button></div>
    </div>

    <div class="oi-step" id="oi-step-2" hidden>
      <h3 class="oi-step-h" id="oi-step2-h" tabindex="-1">Pasul 2 — Datele invitaților</h3>
      <p class="oi-step-p">Completează datele invitaților dacă le ai. Toate câmpurile sunt opționale: o invitație fără nume apare pe PDF ca „Invitat 1”, „Invitat 2”…</p>
      <div class="oi-seg" id="oi-modes" role="group" aria-label="Cum adaugi invitații">
        <button type="button" data-mode="manual" aria-pressed="true">Completare manuală</button>
        <button type="button" data-mode="csv" aria-pressed="false">Încarcă CSV</button>
      </div>
      <div id="oi-pane-manual">
        <div class="oi-table-wrap">
          <table class="oi-table">
            <thead><tr><th scope="col">#</th><th scope="col" id="oi-col-seat" hidden>Loc</th><th scope="col">Prenume</th><th scope="col">Nume</th><th scope="col">Email</th><th scope="col">Telefon</th><th scope="col">Companie</th><th scope="col">Note</th></tr></thead>
            <tbody id="oi-rows"></tbody>
          </table>
        </div>
      </div>
      <div id="oi-pane-csv" hidden>
        <div class="oi-drop">
          <input type="file" id="oi-csv" accept=".csv,text/csv" class="oi-sr" tabindex="-1">
          <p>Încarcă un fișier CSV cu coloanele: <code>first_name, last_name, email, phone, company, notes</code></p>
          <div class="oi-drop-act">
            <button class="btn btn-primary" type="button" id="oi-csv-pick"><?= v2_ic('file-csv') ?>Alege fișier CSV</button>
            <button class="btn btn-ghost" type="button" id="oi-csv-template"><?= v2_ic('download-simple') ?><span data-label>Descarcă template CSV</span></button>
          </div>
          <p class="oi-csv-name" id="oi-csv-name" aria-live="polite"></p>
          <p class="oi-err" id="oi-csv-err" role="alert" hidden></p>
        </div>
      </div>
      <p class="oi-wait" id="oi-wait" hidden>Generarea poate dura până la un minut pentru o serie mare. Nu închide pagina.</p>
      <div class="oi-form-err" id="oi-gen-err" role="alert" hidden></div>
      <div class="oi-act is-split">
        <button class="btn btn-ghost" type="button" id="oi-back-1"><?= v2_ic('arrow-left') ?>Înapoi</button>
        <button class="btn btn-primary" type="button" id="oi-generate"><span data-label>Generează PDF-uri</span></button>
      </div>
    </div>

    <div class="oi-step" id="oi-step-3" hidden>
      <div class="oi-done">
        <span class="oi-done-ic" id="oi-done-ic" aria-hidden="true"><?= v2_ic('check') ?></span>
        <div><h3 class="oi-step-h" id="oi-step3-h" tabindex="-1">Gata! Invitațiile au fost generate.</h3><p class="oi-step-p" id="oi-done-p"></p></div>
      </div>
      <div class="oi-act">
        <button class="btn btn-primary" type="button" id="oi-done-zip"><?= v2_ic('download-simple') ?><span data-label>Descarcă invitațiile</span></button>
        <button class="btn btn-ghost" type="button" id="oi-again">Generează altă serie</button>
      </div>
    </div>
  </section>

  <section class="org-panel" id="oi-history" aria-labelledby="oi-history-h" hidden>
    <div class="org-panel-head"><div><p class="org-k">Serii</p><h2 class="org-panel-h" id="oi-history-h">Serii de invitații pentru această activitate</h2><p class="org-panel-p" id="oi-history-p"></p></div></div>
    <ul class="oi-batches" id="oi-batches" aria-live="polite"><li><span class="org-skel oi-sk-row"></span></li></ul>
    <div class="oi-more-row"><button class="btn btn-ghost oi-sm" type="button" id="oi-history-more" hidden><span data-label>Încarcă mai multe serii</span></button></div>
  </section>

  <dialog class="oi-map-d" id="oi-map-d" aria-labelledby="oi-map-h">
    <div class="oi-map-in">
      <div class="oi-map-head">
        <div><h2 class="oi-d-h" id="oi-map-h">Alege locurile pentru invitații</h2><p class="oi-d-p">Apasă pe un loc ca să-l alegi sau să renunți la el. Mărește harta cu rotița, cu două degete sau cu butoanele.</p></div>
        <span class="oi-map-count" id="oi-map-count" aria-live="polite">0 locuri</span>
        <?= $oiX ?>
      </div>
      <div class="oi-map-body" id="oi-map-body">
        <div class="oi-map-msg" id="oi-map-msg">Se încarcă harta…</div>
        <div class="oi-map" id="oi-map" hidden></div>
        <div class="oi-zoom">
          <button type="button" id="oi-zoom-in" aria-label="Mărește" title="Mărește"><?= v2_ic('plus') ?></button>
          <button type="button" id="oi-zoom-out" aria-label="Micșorează" title="Micșorează"><?= v2_ic('minus') ?></button>
          <button type="button" id="oi-zoom-fit" aria-label="Potrivește pe ecran" title="Potrivește pe ecran"><?= v2_ic('arrows-out') ?></button>
        </div>
        <span class="oi-zoom-l" id="oi-zoom-l" aria-hidden="true">100%</span>
      </div>
      <div class="oi-map-foot">
        <ul class="oi-legend" aria-label="Legenda">
          <li><i class="is-free"></i>Disponibil</li>
          <li><i class="is-mine"></i>Selectat de tine</li>
          <li><i class="is-off"></i>Indisponibil</li>
        </ul>
        <span class="oi-err" id="oi-map-err" role="alert" hidden></span>
        <div class="oi-map-act">
          <button class="btn btn-ghost" type="button" id="oi-map-clear">Deselectează tot</button>
          <button class="btn btn-primary" type="button" id="oi-map-ok">Confirmă selecția</button>
        </div>
      </div>
    </div>
  </dialog>

  <dialog class="oi-dialog" id="oi-confirm-d" aria-labelledby="oi-confirm-h" aria-describedby="oi-confirm-p">
    <div class="oi-d-inner">
      <h2 class="oi-d-h" id="oi-confirm-h"></h2>
      <p class="oi-d-p" id="oi-confirm-p"></p>
      <div class="oi-form-err" id="oi-confirm-err" role="alert" hidden></div>
      <div class="oi-d-act"><button class="btn btn-ghost" type="button" data-close>Renunță</button><button class="btn btn-primary" type="button" id="oi-confirm-go"><span data-label></span></button></div>
    </div>
  </dialog>
</div>
<?php
v2_org_end();
include __DIR__ . '/../includes/v2/foot.php';
