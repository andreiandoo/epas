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

$pageTitle = v2_t('Invitations');
$pageDescription = v2_t('PDF invitations with a QR code for the experiences of an operator on Viaqui.');
$canonicalUrl = SITE_URL . '/organizator/invitatii';
$noindex = true;
$skipPageCache = true;

$v2Styles = ['organizer.css', 'org-invitations.css'];
$v2Scripts = ['organizer.js', 'org-invitations.js'];
$v2LegacyScripts = ['assets/js/config.js', 'assets/js/utils.js', 'assets/js/api.js', 'assets/js/auth.js'];
$v2HeadExtra = v2_account_client_config('organizer');

$oiX = '<button class="oi-x" type="button" data-close aria-label="' . v2_te('Close') . '">' . v2_ic('x') . '</button>';

include __DIR__ . '/../includes/v2/head.php';
v2_org_start('events');
?>
<div class="oi" id="oi">
  <header class="oi-head">
    <a class="oi-back" href="/organizator/events"><?= v2_ic('arrow-left') ?><?= v2_te('Back to experiences') ?></a>
    <p class="org-k"><?= v2_te('Experiences') ?></p>
    <h1 class="oi-h"><?= v2_te('Invitations') ?></h1>
    <p class="oi-lead"><?= v2_te('Generate PDF invitations for the chosen experience.') ?></p>
  </header>

  <div class="oi-loading" id="oi-loading" aria-hidden="true"><span class="org-skel"></span></div>

  <section class="oi-event" id="oi-event" aria-labelledby="oi-event-name" hidden>
    <span class="oi-event-ic" aria-hidden="true"><?= v2_ic('envelope-simple') ?></span>
    <div class="oi-event-t">
      <h2 class="oi-event-h" id="oi-event-name"></h2>
      <p class="oi-event-meta">
        <span id="oi-event-date-w"><?= v2_ic('calendar-blank') ?><span id="oi-event-date"></span></span>
        <span id="oi-event-venue-w"><?= v2_ic('map-pin') ?><span id="oi-event-venue"></span></span>
        <span class="org-tag is-info" id="oi-event-seated" hidden><?= v2_te('With a seating map') ?></span>
        <span class="org-tag is-muted" id="oi-event-over" hidden><?= v2_te('Ended') ?></span>
      </p>
    </div>
    <a class="oi-event-change" href="/organizator/invitatii"><?= v2_te('Another experience') ?></a>
  </section>

  <section class="org-panel" id="oi-choose" aria-labelledby="oi-choose-h" hidden>
    <div class="oi-missing" id="oi-missing" role="status" hidden><?= v2_ic('warning-circle') ?><p id="oi-missing-p"><?= v2_te('We could not find the selected experience. Choose one from your list.') ?></p></div>
    <div class="org-panel-head"><div><p class="org-k"><?= v2_te('Experience') ?></p><h2 class="org-panel-h" id="oi-choose-h"><?= v2_te('Choose the experience') ?></h2><p class="org-panel-p"><?= v2_te('Invitations are generated for one experience at a time. Here are your experiences that have not ended yet.') ?></p></div></div>
    <ul class="oi-events" id="oi-events" aria-live="polite"><li class="oi-msg"><?= v2_te('Loading experiences…') ?></li></ul>
    <p class="oi-small"><a href="/organizator/events"><?= v2_te('See all experiences') ?></a></p>
  </section>

  <section class="org-panel oi-builder" id="oi-builder" aria-labelledby="oi-builder-h" hidden>
    <div class="org-panel-head oi-builder-head">
      <div><p class="org-k"><?= v2_te('New series') ?></p><h2 class="org-panel-h" id="oi-builder-h"><?= v2_te('Generate invitations') ?></h2></div>
      <ol class="oi-steps" aria-label="<?= v2_te('Steps') ?>">
        <li data-step="1"><b>1</b><span id="oi-steps-1"><?= v2_te('Series details') ?></span></li>
        <li data-step="2"><b>2</b><span><?= v2_te('Guests') ?></span></li>
        <li data-step="3"><b>3</b><span><?= v2_te('Done') ?></span></li>
      </ol>
    </div>

    <div class="oi-step" id="oi-step-1">
      <h3 class="oi-step-h" id="oi-step1-h" tabindex="-1"><?= v2_te('Step 1: Series details') ?></h3>
      <p class="oi-step-p" id="oi-step1-p"><?= v2_te('Give the series a name (optional, to keep things organised, e.g. "Company X", "Sponsors") and choose the number of invitations.') ?></p>
      <div class="oi-grid2">
        <div class="oi-f">
          <label class="oi-f-l" for="oi-name"><?= v2_t('Series name <small>(optional)</small>') ?></label>
          <input id="oi-name" type="text" maxlength="120" autocomplete="off" placeholder="<?= v2_te('e.g. Company X - press') ?>" aria-describedby="oi-name-help">
          <span class="oi-help" id="oi-name-help"><?= v2_te('Left empty, the series gets the name of the experience and the date.') ?></span>
        </div>
        <div class="oi-f" id="oi-qty-f">
          <label class="oi-f-l" for="oi-qty"><?= v2_te('Number of invitations') ?></label>
          <input id="oi-qty" type="number" inputmode="numeric" min="1" max="50" step="1" value="1" aria-describedby="oi-qty-err">
          <span class="oi-err" id="oi-qty-err" hidden></span>
        </div>
      </div>
      <div class="oi-seats" id="oi-seats" hidden>
        <p class="oi-step-p"><?= v2_t('The experience has a seating map. Select the seats you want to hold for invitations. The chosen seats will be marked as <strong>sold</strong> and customers will not be able to buy them.') ?></p>
        <div class="oi-seats-row">
          <button class="btn btn-primary" type="button" id="oi-open-map"><?= v2_ic('map-trifold') ?><?= v2_te('Choose the seats on the map') ?></button>
          <span class="oi-seats-sum" id="oi-seats-sum" aria-live="polite"><?= v2_te('No seat selected.') ?></span>
        </div>
        <ul class="oi-chips" id="oi-chips" aria-label="<?= v2_te('Chosen seats') ?>"></ul>
        <span class="oi-err" id="oi-seats-err" role="alert" hidden></span>
      </div>
      <details class="oi-more" id="oi-more">
        <summary><?= v2_ic('caret-down') ?><?= v2_te('PDF options') ?></summary>
        <div class="oi-grid2">
          <div class="oi-f">
            <label class="oi-f-l" for="oi-label"><?= v2_t('Label on the invitation <small>(optional)</small>') ?></label>
            <input id="oi-label" type="text" maxlength="100" autocomplete="off" placeholder="<?= v2_te('e.g. VIP invitation') ?>" aria-describedby="oi-label-help">
            <span class="oi-help" id="oi-label-help"><?= v2_te('Shown on the ticket instead of the word "Invitation".') ?></span>
          </div>
          <div class="oi-f">
            <label class="oi-f-l" for="oi-watermark"><?= v2_t('Watermark <small>(optional)</small>') ?></label>
            <input id="oi-watermark" type="text" maxlength="50" autocomplete="off" placeholder="<?= v2_te('INVITATION') ?>" aria-describedby="oi-watermark-help">
            <span class="oi-help" id="oi-watermark-help"><?= v2_te('The text printed at the top of each invitation.') ?></span>
          </div>
        </div>
      </details>
      <div class="oi-note"><?= v2_ic('warning-circle') ?><p><?= v2_t('<strong>At most 50 invitations per series.</strong> Generating can take a few seconds: each invitation produces a PDF with a QR code and your ticket template. If you need more, create several series.') ?></p></div>
      <div class="oi-act"><button class="btn btn-primary" type="button" id="oi-next"><?= v2_te('Continue') ?><?= v2_ic('arrow-right') ?></button></div>
    </div>

    <div class="oi-step" id="oi-step-2" hidden>
      <h3 class="oi-step-h" id="oi-step2-h" tabindex="-1"><?= v2_te('Step 2: Guest details') ?></h3>
      <p class="oi-step-p"><?= v2_te('Fill in the guest details if you have them. All fields are optional: an invitation without a name shows on the PDF as "Guest 1", "Guest 2"…') ?></p>
      <div class="oi-seg" id="oi-modes" role="group" aria-label="<?= v2_te('How to add the guests') ?>">
        <button type="button" data-mode="manual" aria-pressed="true"><?= v2_te('Fill in by hand') ?></button>
        <button type="button" data-mode="csv" aria-pressed="false"><?= v2_te('Upload a CSV') ?></button>
      </div>
      <div id="oi-pane-manual">
        <div class="oi-table-wrap">
          <table class="oi-table">
            <thead><tr><th scope="col">#</th><th scope="col" id="oi-col-seat" hidden><?= v2_te('Seat') ?></th><th scope="col"><?= v2_te('First name') ?></th><th scope="col"><?= v2_te('Last name') ?></th><th scope="col"><?= v2_te('Email') ?></th><th scope="col"><?= v2_te('Phone') ?></th><th scope="col"><?= v2_te('Company') ?></th><th scope="col"><?= v2_te('Notes') ?></th></tr></thead>
            <tbody id="oi-rows"></tbody>
          </table>
        </div>
      </div>
      <div id="oi-pane-csv" hidden>
        <div class="oi-drop">
          <input type="file" id="oi-csv" accept=".csv,text/csv" class="oi-sr" tabindex="-1">
          <p><?= v2_t('Upload a CSV file with the columns: {columns}', ['columns' => '<code>first_name, last_name, email, phone, company, notes</code>']) ?></p>
          <div class="oi-drop-act">
            <button class="btn btn-primary" type="button" id="oi-csv-pick"><?= v2_ic('file-csv') ?><?= v2_te('Choose a CSV file') ?></button>
            <button class="btn btn-ghost" type="button" id="oi-csv-template"><?= v2_ic('download-simple') ?><span data-label><?= v2_te('Download the CSV template') ?></span></button>
          </div>
          <p class="oi-csv-name" id="oi-csv-name" aria-live="polite"></p>
          <p class="oi-err" id="oi-csv-err" role="alert" hidden></p>
        </div>
      </div>
      <p class="oi-wait" id="oi-wait" hidden><?= v2_te('Generating can take up to a minute for a large series. Do not close the page.') ?></p>
      <div class="oi-form-err" id="oi-gen-err" role="alert" hidden></div>
      <div class="oi-act is-split">
        <button class="btn btn-ghost" type="button" id="oi-back-1"><?= v2_ic('arrow-left') ?><?= v2_te('Back') ?></button>
        <button class="btn btn-primary" type="button" id="oi-generate"><span data-label><?= v2_te('Generate the PDFs') ?></span></button>
      </div>
    </div>

    <div class="oi-step" id="oi-step-3" hidden>
      <div class="oi-done">
        <span class="oi-done-ic" id="oi-done-ic" aria-hidden="true"><?= v2_ic('check') ?></span>
        <div><h3 class="oi-step-h" id="oi-step3-h" tabindex="-1"><?= v2_te('Done! The invitations were generated.') ?></h3><p class="oi-step-p" id="oi-done-p"></p></div>
      </div>
      <div class="oi-act">
        <button class="btn btn-primary" type="button" id="oi-done-zip"><?= v2_ic('download-simple') ?><span data-label><?= v2_te('Download the invitations') ?></span></button>
        <button class="btn btn-ghost" type="button" id="oi-again"><?= v2_te('Generate another series') ?></button>
      </div>
    </div>
  </section>

  <section class="org-panel" id="oi-history" aria-labelledby="oi-history-h" hidden>
    <div class="org-panel-head"><div><p class="org-k"><?= v2_te('Series') ?></p><h2 class="org-panel-h" id="oi-history-h"><?= v2_te('Invitation series for this experience') ?></h2><p class="org-panel-p" id="oi-history-p"></p></div></div>
    <ul class="oi-batches" id="oi-batches" aria-live="polite"><li><span class="org-skel oi-sk-row"></span></li></ul>
    <div class="oi-more-row"><button class="btn btn-ghost oi-sm" type="button" id="oi-history-more" hidden><span data-label><?= v2_te('Load more series') ?></span></button></div>
  </section>

  <dialog class="oi-map-d" id="oi-map-d" aria-labelledby="oi-map-h">
    <div class="oi-map-in">
      <div class="oi-map-head">
        <div><h2 class="oi-d-h" id="oi-map-h"><?= v2_te('Choose the seats for the invitations') ?></h2><p class="oi-d-p"><?= v2_te('Click a seat to choose it or to drop it. Zoom the map with the wheel, with two fingers or with the buttons.') ?></p></div>
        <span class="oi-map-count" id="oi-map-count" aria-live="polite"><?= v2_e(v2_num(0, 'seat', 'seats')) ?></span>
        <?= $oiX ?>
      </div>
      <div class="oi-map-body" id="oi-map-body">
        <div class="oi-map-msg" id="oi-map-msg"><?= v2_te('Loading the map…') ?></div>
        <div class="oi-map" id="oi-map" hidden></div>
        <div class="oi-zoom">
          <button type="button" id="oi-zoom-in" aria-label="<?= v2_te('Zoom in') ?>" title="<?= v2_te('Zoom in') ?>"><?= v2_ic('plus') ?></button>
          <button type="button" id="oi-zoom-out" aria-label="<?= v2_te('Zoom out') ?>" title="<?= v2_te('Zoom out') ?>"><?= v2_ic('minus') ?></button>
          <button type="button" id="oi-zoom-fit" aria-label="<?= v2_te('Fit to screen') ?>" title="<?= v2_te('Fit to screen') ?>"><?= v2_ic('arrows-out') ?></button>
        </div>
        <span class="oi-zoom-l" id="oi-zoom-l" aria-hidden="true">100%</span>
      </div>
      <div class="oi-map-foot">
        <ul class="oi-legend" aria-label="<?= v2_te('Legend') ?>">
          <li><i class="is-free"></i><?= v2_te('Available') ?></li>
          <li><i class="is-mine"></i><?= v2_te('Selected by you') ?></li>
          <li><i class="is-off"></i><?= v2_te('Unavailable') ?></li>
        </ul>
        <span class="oi-err" id="oi-map-err" role="alert" hidden></span>
        <div class="oi-map-act">
          <button class="btn btn-ghost" type="button" id="oi-map-clear"><?= v2_te('Clear the selection') ?></button>
          <button class="btn btn-primary" type="button" id="oi-map-ok"><?= v2_te('Confirm the selection') ?></button>
        </div>
      </div>
    </div>
  </dialog>

  <dialog class="oi-dialog" id="oi-confirm-d" aria-labelledby="oi-confirm-h" aria-describedby="oi-confirm-p">
    <div class="oi-d-inner">
      <h2 class="oi-d-h" id="oi-confirm-h"></h2>
      <p class="oi-d-p" id="oi-confirm-p"></p>
      <div class="oi-form-err" id="oi-confirm-err" role="alert" hidden></div>
      <div class="oi-d-act"><button class="btn btn-ghost" type="button" data-close><?= v2_te('Cancel') ?></button><button class="btn btn-primary" type="button" id="oi-confirm-go"><span data-label></span></button></div>
    </div>
  </dialog>
</div>
<?php
v2_org_end();
include __DIR__ . '/../includes/v2/foot.php';
