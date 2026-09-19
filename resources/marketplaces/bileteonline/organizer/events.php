<?php
/**
 * Organizer activities: /organizator/activities (v2 design); also /organizator/events, /organizator/activities/{id}
 * and the older /organizator/event/{id}.
 *
 * Inside the v2 organizer shell. One page, two views (org-events.js switches them with the History API):
 * - the catalogue: search, filters by state with counts (in progress, drafts, ended, all), a card per activity (image,
 *   state and sales tags, countdown, date and place, views / tickets sold / invitations / takings, and every action the
 *   old page had: edit, participants, analytics or report, sales, documents, invitations, staff report, promote,
 *   delete);
 * - the editor: sticky bar (back, title, date, place, state, last save, preview, save, submit, delete), a contents
 *   outline with what is still missing, seven sections (details, schedule, place, content, media, tickets, sales
 *   settings), the actions of a live activity (sold out, door sales only, postpone, cancel), a live preview, and an
 *   action bar on phones.
 * Data: /organizer/events (every page), /organizer/events/{id} GET / PUT / DELETE, POST /organizer/events, /submit,
 * /cancel, PATCH /status, POST /images, /organizer/event-categories, /event-genres, /venues, GET + POST /artists.
 *
 * Fixed on the way:
 * - a draft with an unlimited ticket type came back with stock "-1", which core rejects, so it could not be saved;
 *   dates were pre-filled from UTC (an activity starting after midnight showed the day before) and the default end
 *   23:59 was written back as a real end time;
 * - on a published activity every ticket type was sent again (a renamed one came back as a duplicate) and approval was
 *   requested again, which core refuses ("already published"); a pending one hit "already submitted". Only new ticket
 *   types go out now, and the save reports core's answer (published, or waiting for approval);
 * - free tickets with a code (set by an administrator) would have been re-sent as plain free tickets;
 * - cancelling promised refunds core does not make (paid orders go to a manual review); "Anulează anularea" and
 *   "Anulează amânarea" did nothing: a cancellation is final, a postponement can be revoked;
 * - "Pagină activitate" opened /bilete/{slug}, a 404: activities managed here have no public page on bilete.online yet;
 * - images: core takes JPG, PNG or WebP up to 10 MB (the page promised any image up to 5 MB), and removing a saved
 *   image only hid it;
 * - core's English errors reached the organizer; confirm() / alert() became dialogs; leaving with unsaved changes is
 *   now caught.
 * Capacity, tags and the sales window are accepted by core but not stored yet: the fields stay, with a plain note.
 */

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/api.php';
require_once __DIR__ . '/../includes/nav-helpers.php';
require_once __DIR__ . '/../includes/v2/helpers.php';
require_once __DIR__ . '/../includes/v2/organizer.php';

$pageTitleRaw = 'Activitățile tale — ' . SITE_NAME;
$pageDescription = 'Activitățile tale pe bilete.online: creează, editează, publică și urmărește fiecare activitate.';
$canonicalUrl = SITE_URL . '/organizator/activities';
$noindex = true;
$skipPageCache = true;

$v2Styles = ['organizer.css', 'org-events.css'];
$v2Scripts = ['organizer.js', 'org-events.js'];
$v2LegacyScripts = ['assets/js/config.js', 'assets/js/utils.js', 'assets/js/api.js', 'assets/js/auth.js'];
$v2HeadExtra = v2_account_client_config('organizer');

/** A form field: label, control, optional help, error slot. org-events.js links them with aria-describedby. */
$oeField = function (string $id, string $label, string $control, array $o = []): string {
    return '<div class="oe-f' . (isset($o['class']) ? ' ' . $o['class'] : '') . '"' . (isset($o['wrap']) ? ' id="' . $o['wrap'] . '"' : '') . (!empty($o['hidden']) ? ' hidden' : '') . '>'
        . '<label class="oe-label" for="' . $id . '">' . $label . (!empty($o['req']) ? ' <b class="oe-req" aria-hidden="true">*</b>' : '') . '</label>'
        . $control
        . (isset($o['help']) ? '<p class="oe-help" id="' . $id . '-help">' . $o['help'] . '</p>' : '')
        . '<p class="oe-err" id="' . $id . '-err" hidden></p></div>';
};
$oeSecStart = function (int $n, string $title) {
    ?>
      <section class="oe-sec" id="oe-s<?= $n ?>" data-step="<?= $n ?>" data-open="<?= $n === 1 ? 'true' : 'false' ?>" aria-labelledby="oe-s<?= $n ?>-h">
        <div class="oe-sec-head">
          <span class="oe-step" aria-hidden="true"><?= $n ?></span>
          <div class="oe-sec-t"><h2 class="oe-sec-h" id="oe-s<?= $n ?>-h"><?= $title ?></h2><p class="oe-sum" id="oe-sum-<?= $n ?>"></p></div>
          <button class="oe-sec-btn" type="button" aria-expanded="<?= $n === 1 ? 'true' : 'false' ?>" aria-controls="oe-s<?= $n ?>-body"><?= v2_ic('caret-down') ?><span class="sr">Arată sau ascunde: <?= $title ?></span></button>
        </div>
        <div class="oe-sec-body" id="oe-s<?= $n ?>-body">
    <?php
};
$oeSecEnd = function () {
    ?>
        </div>
      </section>
    <?php
};
$oeSteps = [[1, 'Detalii activitate', 'pencil-simple'], [2, 'Program', 'calendar-blank'], [3, 'Locație', 'map-pin'], [4, 'Conținut', 'file-text'], [5, 'Media', 'image'], [6, 'Bilete', 'ticket'], [7, 'Setări vânzări', 'gear-six']];
$oeDrop = function (string $kind, string $label, string $hint, string $ratio) {
    ?>
          <div class="oe-media" data-media="<?= $kind ?>">
            <p class="oe-label" id="oe-<?= $kind ?>-l"><?= $label ?></p>
            <div class="oe-media-preview" id="oe-<?= $kind ?>-preview" hidden>
              <div class="oe-media-frame is-<?= $ratio ?>"><img id="oe-<?= $kind ?>-img" alt="<?= $label ?>, previzualizare"></div>
              <p class="oe-help" id="oe-<?= $kind ?>-note"></p>
              <div class="oe-media-act">
                <button class="oe-mini" type="button" data-media-pick="<?= $kind ?>"><?= v2_ic('upload-simple') ?>Alege altă imagine</button>
                <button class="oe-mini is-danger" type="button" data-media-undo="<?= $kind ?>" hidden><?= v2_ic('x') ?>Renunță la imaginea nouă</button>
              </div>
            </div>
            <label class="oe-drop" id="oe-<?= $kind ?>-drop" data-drop="<?= $kind ?>">
              <?= v2_ic('image') ?>
              <b>Trage imaginea aici sau alege un fișier</b>
              <small><?= $hint ?></small>
              <input class="sr" type="file" id="oe-<?= $kind ?>-file" accept="image/jpeg,image/png,image/webp" aria-labelledby="oe-<?= $kind ?>-l" aria-describedby="oe-<?= $kind ?>-file-err">
            </label>
            <p class="oe-err" id="oe-<?= $kind ?>-file-err" hidden></p>
          </div>
    <?php
};

include __DIR__ . '/../includes/v2/head.php';
v2_org_start('events');
?>
<div class="oe" id="oe">

  <!-- =================== CATALOGUE =================== -->
  <section class="oe-list" id="oe-list" aria-labelledby="oe-list-h">
    <header class="oe-head">
      <div>
        <p class="org-k">Catalog</p>
        <h1 class="oe-h" id="oe-list-h" tabindex="-1">Activitățile tale</h1>
        <p class="oe-lead">Gestionează și monitorizează activitățile tale</p>
      </div>
      <button class="btn btn-primary" type="button" data-oe-new><?= v2_ic('plus') ?>Activitate nouă</button>
    </header>

    <div class="oe-toolbar">
      <label class="oe-search"><?= v2_ic('magnifying-glass') ?><span class="sr">Caută activități</span><input id="oe-q" type="search" autocomplete="off" spellcheck="false" placeholder="Caută după nume, locație sau oraș…"></label>
      <div class="oe-pills" role="group" aria-label="Filtrează după stare">
        <button class="oe-pill" type="button" data-status="ongoing" aria-pressed="true">În derulare <span class="oe-pill-n" data-count="ongoing">·</span></button>
        <button class="oe-pill" type="button" data-status="draft" aria-pressed="false">Ciorne <span class="oe-pill-n" data-count="draft">·</span></button>
        <button class="oe-pill" type="button" data-status="ended" aria-pressed="false">Încheiate <span class="oe-pill-n" data-count="ended">·</span></button>
        <button class="oe-pill" type="button" data-status="all" aria-pressed="false">Toate <span class="oe-pill-n" data-count="all">·</span></button>
      </div>
    </div>
    <p class="sr" id="oe-live" aria-live="polite"></p>

    <ul class="oe-cards" id="oe-cards" aria-busy="true">
      <?php for ($i = 0; $i < 3; $i++): ?>
      <li class="oe-card is-skel" aria-hidden="true"><span class="org-skel oe-sk-media"></span><span class="oe-sk-main"><span class="org-skel oe-sk-a"></span><span class="org-skel oe-sk-b"></span><span class="org-skel oe-sk-c"></span></span></li>
      <?php endfor; ?>
    </ul>
    <div class="org-empty oe-empty" id="oe-empty" hidden></div>
  </section>

  <!-- =================== EDITOR =================== -->
  <section class="oe-editor" id="oe-editor" aria-labelledby="oe-ed-h" hidden>
    <div class="oe-bar" id="oe-bar">
      <button class="oe-back" type="button" data-oe-back title="Înapoi la activități"><?= v2_ic('arrow-left') ?><span class="sr">Înapoi la activități</span></button>
      <div class="oe-bar-t">
        <h1 class="oe-bar-h" id="oe-ed-h" tabindex="-1">Activitate nouă</h1>
        <p class="oe-bar-meta">
          <span id="oe-bar-date" hidden><?= v2_ic('calendar-blank') ?><span></span></span>
          <span id="oe-bar-venue" hidden><?= v2_ic('map-pin') ?><span></span></span>
          <span class="org-tag" id="oe-bar-status" hidden></span>
          <span class="oe-saved" id="oe-saved" role="status"></span>
        </p>
      </div>
      <div class="oe-bar-actions">
        <button class="btn btn-ghost oe-bar-icon" type="button" id="oe-preview-open"><?= v2_ic('eye') ?><span>Previzualizare</span></button>
        <button class="btn btn-ghost oe-bar-icon is-danger" type="button" id="oe-delete" hidden><?= v2_ic('trash') ?><span>Șterge</span></button>
        <button class="btn btn-ghost" type="button" data-oe-save data-size="short"><span data-label>Salvează ciornă</span></button>
        <button class="btn btn-primary" type="button" data-oe-submit data-size="short"><span data-label>Trimite spre aprobare</span></button>
      </div>
    </div>

    <div class="oe-layout">
      <nav class="oe-outline" id="oe-outline" aria-label="Cuprinsul activității">
        <p class="oe-ol-k">Cuprins</p>
        <?php foreach ($oeSteps as [$n, $label, $ic]): ?>
        <a class="oe-ol" href="#oe-s<?= $n ?>" data-ol="<?= $n ?>"><?= v2_ic($ic) ?><span class="oe-ol-t"><?= $label ?></span><span class="oe-ol-st" data-st><span class="sr"></span></span></a>
        <?php endforeach; ?>
        <p class="oe-issues" id="oe-issues" hidden><?= v2_ic('warning-circle') ?><span id="oe-issues-t"></span></p>
      </nav>

      <form class="oe-form" id="oe-form" autocomplete="off" novalidate>
        <div class="oe-banner is-bad" id="oe-rejected" hidden>
          <?= v2_ic('warning-circle') ?>
          <div><p><b>Activitatea a fost respinsă</b></p><p>Motiv: <span id="oe-rejected-reason"></span></p><p class="oe-banner-s">Editează activitatea după indicații și apasă „Salvează și trimite spre aprobare” pentru o nouă verificare.</p></div>
        </div>
        <div class="oe-banner is-info" id="oe-review" hidden>
          <?= v2_ic('clock-countdown') ?>
          <div><p><b>Activitatea e în verificare</b></p><p>Echipa bilete.online o verifică înainte de publicare. Poți face în continuare modificări.</p></div>
        </div>
        <div class="oe-banner is-wait" id="oe-changes" hidden>
          <?= v2_ic('info') ?>
          <div><p><b>Ai modificări trimise spre aprobare</b></p><p>Până la aprobare, activitatea rămâne publicată cu varianta aprobată anterior.</p></div>
        </div>
        <div class="oe-banner is-bad" id="oe-alert" role="alert" hidden>
          <?= v2_ic('warning-circle') ?>
          <div><p id="oe-alert-t"></p><p><a id="oe-alert-a" href="/organizator/setari#contract" hidden>Mergi la contract</a></p></div>
        </div>

        <!-- actions of a live activity -->
        <div class="oe-status" id="oe-status" hidden>
          <div class="oe-status-head"><h2 class="oe-status-h">Acțiuni activitate</h2><div class="oe-status-chips" id="oe-status-chips"></div></div>
          <div class="oe-status-row">
            <button class="oe-toggle" type="button" id="oe-sold-out"><?= v2_ic('ticket') ?><span>Marchează Sold Out</span></button>
            <button class="oe-toggle" type="button" id="oe-door-sales"><?= v2_ic('door-open') ?><span>Door Sales Only</span></button>
            <button class="oe-toggle is-warn" type="button" id="oe-postpone"><?= v2_ic('clock-countdown') ?><span>Amână activitatea</span></button>
            <button class="oe-toggle is-danger" type="button" id="oe-cancel"><?= v2_ic('prohibit') ?><span>Anulează activitatea</span></button>
          </div>
          <p class="oe-help">Sold Out oprește vânzarea online; Door Sales Only lasă vânzarea doar la intrare.</p>
        </div>

        <?php $oeSecStart(1, 'Detalii activitate'); ?>
          <?= $oeField('oe-name', 'Numele activității', '<input type="text" id="oe-name" maxlength="255" aria-required="true" placeholder="ex: Tur cu barca pe lac">', ['req' => true]) ?>
          <div class="oe-grid is-3">
            <?= $oeField('oe-category', 'Categorie', '<div class="oe-select"><select id="oe-category"><option value="">Selectează categoria</option></select>' . v2_ic('caret-down') . '</div>') ?>
            <?= $oeField('oe-genres-q', 'Gen activitate', '<div class="oe-ms" id="oe-genres-box"><ul class="oe-chips" id="oe-genres-chips" aria-label="Genuri alese"></ul><input type="text" id="oe-genres-q" role="combobox" aria-expanded="false" aria-controls="oe-genres-list" aria-autocomplete="list" autocomplete="off" placeholder="Caută genuri…"><ul class="oe-listbox" id="oe-genres-list" role="listbox" aria-label="Genuri" hidden></ul></div>', ['help' => 'Selectează genurile aplicabile', 'wrap' => 'oe-genres-f', 'hidden' => true]) ?>
            <?= $oeField('oe-artists-q', 'Artiști', '<div class="oe-ms" id="oe-artists-box"><ul class="oe-chips" id="oe-artists-chips" aria-label="Artiști aleși"></ul><input type="text" id="oe-artists-q" role="combobox" aria-expanded="false" aria-controls="oe-artists-list" aria-autocomplete="list" autocomplete="off" placeholder="Caută artiști…"><ul class="oe-listbox" id="oe-artists-list" role="listbox" aria-label="Artiști" hidden></ul></div>', ['help' => 'Caută în bibliotecă sau scrie un nume nou']) ?>
          </div>
          <?= $oeField('oe-short', 'Descriere scurtă', '<textarea id="oe-short" rows="3" placeholder="O scurtă descriere a activității (max. 120 de cuvinte)"></textarea>', ['help' => '<span id="oe-short-count">0/120 cuvinte · 0/500 caractere</span>']) ?>
          <?= $oeField('oe-tags', 'Etichete', '<input type="text" id="oe-tags" placeholder="aventură, outdoor, familie (separate cu virgulă)">', ['help' => 'Separă etichetele cu virgulă. Platforma nu le salvează încă.']) ?>
        <?php $oeSecEnd(); ?>

        <?php $oeSecStart(2, 'Program'); ?>
          <fieldset class="oe-fs">
            <legend class="oe-label">Tipul duratei <b class="oe-req" aria-hidden="true">*</b></legend>
            <div class="oe-modes">
              <label class="oe-mode"><input type="radio" name="oe-duration" value="single_day" checked><span><b>O singură zi</b><small>Activitatea are loc într-o singură zi</small></span></label>
              <label class="oe-mode"><input type="radio" name="oe-duration" value="range"><span><b>Interval de zile</b><small>Activitatea se întinde pe mai multe zile</small></span></label>
            </div>
          </fieldset>
          <p class="oe-help" id="oe-dm-hint" hidden>Selectează tipul duratei pentru a configura programul.</p>
          <div class="oe-grid is-2">
            <?= $oeField('oe-start-date', '<span data-range-label="Data de început">Data activității</span>', '<input type="date" id="oe-start-date" aria-required="true">', ['req' => true]) ?>
            <?= $oeField('oe-start-time', 'Ora de începere', '<input type="time" id="oe-start-time" aria-required="true">', ['req' => true]) ?>
          </div>
          <div class="oe-grid is-2" id="oe-range-end" hidden>
            <?= $oeField('oe-end-date', 'Data de sfârșit', '<input type="date" id="oe-end-date" aria-required="true">', ['req' => true]) ?>
            <?= $oeField('oe-end-time', 'Ora de sfârșit', '<input type="time" id="oe-end-time">') ?>
          </div>
          <div class="oe-grid is-2" id="oe-single-end">
            <?= $oeField('oe-end-time-single', 'Ora de sfârșit', '<input type="time" id="oe-end-time-single">') ?>
            <?= $oeField('oe-door-time', 'Ora deschiderii ușilor', '<input type="time" id="oe-door-time">', ['help' => 'Ora la care se deschid ușile']) ?>
          </div>
          <div class="oe-grid is-2" id="oe-range-door" hidden>
            <?= $oeField('oe-door-time-range', 'Ora deschiderii ușilor', '<input type="time" id="oe-door-time-range">', ['help' => 'Ora la care se deschid ușile']) ?>
          </div>
        <?php $oeSecEnd(); ?>

        <?php $oeSecStart(3, 'Locație'); ?>
          <?= $oeField('oe-venue', 'Nume locație / sală', '<div class="oe-combo"><input type="text" id="oe-venue" role="combobox" aria-expanded="false" aria-controls="oe-venue-list" aria-autocomplete="list" aria-required="true" autocomplete="off" placeholder="Caută sau scrie numele locației…"><ul class="oe-listbox" id="oe-venue-list" role="listbox" aria-label="Locații găsite" hidden></ul></div>', ['req' => true, 'help' => 'Caută în biblioteca de locații sau scrie manual']) ?>
          <div class="oe-note is-wait" id="oe-venue-notice" hidden><?= v2_ic('info') ?><span>Această locație nu există în biblioteca noastră. Numele introdus va fi trimis ca sugestie către administratorul platformei.</span></div>
          <div class="oe-grid is-2">
            <?= $oeField('oe-city', 'Oraș', '<input type="text" id="oe-city" maxlength="100" aria-required="true" placeholder="ex: București">', ['req' => true]) ?>
            <?= $oeField('oe-address', 'Adresă', '<input type="text" id="oe-address" maxlength="500" placeholder="ex: Str. Lipscani nr. 10">') ?>
          </div>
          <div class="oe-grid is-2">
            <?= $oeField('oe-website', 'Website activitate', '<input type="url" id="oe-website" maxlength="500" inputmode="url" placeholder="https://…">') ?>
            <?= $oeField('oe-facebook', 'Link Facebook', '<input type="url" id="oe-facebook" maxlength="500" inputmode="url" placeholder="https://facebook.com/events/…">') ?>
          </div>
          <?= $oeField('oe-video', 'Videoclip YouTube', '<input type="url" id="oe-video" maxlength="500" inputmode="url" placeholder="https://www.youtube.com/watch?v=…">', ['help' => 'Orice link YouTube (watch, share sau embed). Va fi afișat ca videoclip pe pagina publică a activității.']) ?>
        <?php $oeSecEnd(); ?>

        <?php $oeSecStart(4, 'Conținut și descriere'); ?>
          <?= $oeField('oe-description', 'Descriere completă', '<textarea id="oe-description" rows="8" placeholder="Scrie descrierea activității aici…"></textarea>', ['help' => 'Descrie activitatea în detaliu: program, reguli de acces etc.', 'class' => 'is-rte']) ?>
          <?= $oeField('oe-terms', 'Condiții activitate', '<textarea id="oe-terms" rows="6" placeholder="Condiții de participare, restricții, politica de retur…"></textarea>', ['help' => 'Condiții de participare, restricții de vârstă, reguli speciale, politica de retur etc.', 'class' => 'is-rte']) ?>
        <?php $oeSecEnd(); ?>

        <?php $oeSecStart(5, 'Media'); ?>
          <div class="oe-grid is-2 oe-media-grid">
            <?php $oeDrop('poster', 'Poster (vertical)', 'JPG, PNG sau WebP · recomandat 800×1200 · max. 10 MB', 'poster'); ?>
            <?php $oeDrop('cover', 'Imagine cover (orizontală)', 'JPG, PNG sau WebP · recomandat 1200×630 · max. 10 MB', 'cover'); ?>
          </div>
        <?php $oeSecEnd(); ?>

        <?php $oeSecStart(6, 'Bilete'); ?>
          <p class="oe-help oe-lead-s">Adaugă cel puțin un tip de bilet. Poți adăuga mai multe categorii (ex: Early Bird, Standard, VIP).</p>
          <div class="oe-locked" id="oe-tt-locked" hidden>
            <p class="oe-note" id="oe-tt-locked-note" hidden><?= v2_ic('lock-simple') ?><span id="oe-tt-locked-t">Tipurile de bilet existente nu se mai pot modifica după publicare, ca să nu afecteze biletele vândute. Poți adăuga tipuri noi.</span></p>
            <ul class="oe-lk-list" id="oe-tt-locked-list"></ul>
          </div>
          <div class="oe-tt-list" id="oe-tt-list"></div>
          <button class="oe-add" type="button" id="oe-tt-add" aria-describedby="oe-tt-add-err"><?= v2_ic('plus') ?>Adaugă alt tip de bilet</button>
          <p class="oe-err" id="oe-tt-add-err" hidden></p>
        <?php $oeSecEnd(); ?>

        <?php $oeSecStart(7, 'Setări vânzări'); ?>
          <p class="oe-note"><?= v2_ic('info') ?><span>Momentan, platforma folosește doar maximul de bilete pe comandă, ca valoare implicită pentru tipurile de bilet noi. Capacitatea totală se calculează din stocul tipurilor de bilet, iar perioada de vânzare nu se salvează încă.</span></p>
          <div class="oe-grid is-2">
            <?= $oeField('oe-capacity', 'Capacitate totală', '<input type="number" id="oe-capacity" min="1" step="1" inputmode="numeric" placeholder="ex: 500">', ['help' => 'Numărul total de locuri disponibile']) ?>
            <?= $oeField('oe-max-order', 'Max. bilete per comandă', '<input type="number" id="oe-max-order" min="1" max="50" step="1" inputmode="numeric" placeholder="10">', ['help' => 'Câte bilete poate cumpăra un client într-o comandă']) ?>
          </div>
          <div class="oe-grid is-2">
            <?= $oeField('oe-sales-start', 'Început vânzări', '<input type="datetime-local" id="oe-sales-start">', ['help' => 'Când încep vânzările (gol = imediat)']) ?>
            <?= $oeField('oe-sales-end', 'Sfârșit vânzări', '<input type="datetime-local" id="oe-sales-end">', ['help' => 'Când se opresc vânzările (gol = la începutul activității)']) ?>
          </div>
        <?php $oeSecEnd(); ?>

        <div class="oe-foot">
          <button class="btn btn-ghost" type="button" data-oe-back><?= v2_ic('arrow-left') ?>Înapoi la activități</button>
          <div class="oe-foot-act">
            <button class="btn btn-ghost" type="button" data-oe-save data-size="long"><span data-label>Salvează ciornă</span></button>
            <button class="btn btn-primary" type="button" data-oe-submit data-size="long"><span data-label>Salvează și trimite spre aprobare</span></button>
          </div>
        </div>
      </form>
    </div>

    <div class="oe-mbar" id="oe-mbar">
      <button class="oe-icon-btn" type="button" data-oe-back><?= v2_ic('arrow-left') ?><span class="sr">Înapoi la activități</span></button>
      <button class="btn btn-ghost" type="button" data-oe-save data-size="tiny"><span data-label>Salvează ciornă</span></button>
      <button class="btn btn-primary" type="button" data-oe-submit data-size="tiny"><span data-label>Trimite</span></button>
    </div>
  </section>

  <template id="oe-tt-tpl">
    <div class="oe-tt" data-tt>
      <div class="oe-tt-head"><h3 class="oe-tt-h" data-tt-title>Tip bilet #1</h3><button class="oe-icon-btn is-danger" type="button" data-tt-remove><?= v2_ic('trash') ?><span class="sr">Elimină tipul de bilet</span></button></div>
      <div class="oe-grid is-3">
        <div class="oe-f"><label class="oe-label">Nume bilet <b class="oe-req" aria-hidden="true">*</b></label><input type="text" data-f="name" maxlength="255" aria-required="true" placeholder="ex: Standard, VIP, Early Bird"><p class="oe-err" hidden></p></div>
        <div class="oe-f"><label class="oe-label">Preț (lei) <b class="oe-req" aria-hidden="true">*</b></label><input type="number" data-f="price" min="0" step="0.01" inputmode="decimal" aria-required="true" placeholder="0,00"><p class="oe-err" hidden></p></div>
        <div class="oe-f"><label class="oe-label">Stoc bilete</label><input type="number" data-f="quantity" min="1" step="1" inputmode="numeric" placeholder="Nelimitat"><p class="oe-err" hidden></p></div>
      </div>
      <div class="oe-f"><label class="oe-label">Descriere bilet</label><input type="text" data-f="description" maxlength="500" placeholder="ex: Acces general, loc nenumerotat"><p class="oe-err" hidden></p></div>
      <div class="oe-grid is-2">
        <div class="oe-f"><label class="oe-label">Min. bilete/comandă</label><input type="number" data-f="min" min="1" step="1" inputmode="numeric" placeholder="1"><p class="oe-err" hidden></p></div>
        <div class="oe-f"><label class="oe-label">Max. bilete/comandă</label><input type="number" data-f="max" min="1" step="1" inputmode="numeric" placeholder="10"><p class="oe-err" hidden></p></div>
      </div>
    </div>
  </template>

  <dialog class="oe-dialog" id="oe-postpone-d" aria-labelledby="oe-pp-h">
    <form class="oe-d-in" id="oe-pp-form" novalidate>
      <div class="oe-d-head"><h2 id="oe-pp-h">Amână activitatea</h2><button class="oe-icon-btn" type="button" data-d-close><?= v2_ic('x') ?><span class="sr">Închide</span></button></div>
      <?= $oeField('oe-pp-date', 'Noua dată a activității', '<input type="date" id="oe-pp-date" aria-required="true">', ['req' => true]) ?>
      <div class="oe-grid is-3">
        <?= $oeField('oe-pp-start', 'Ora de start', '<input type="time" id="oe-pp-start">') ?>
        <?= $oeField('oe-pp-door', 'Ora deschiderii', '<input type="time" id="oe-pp-door">') ?>
        <?= $oeField('oe-pp-end', 'Ora de sfârșit', '<input type="time" id="oe-pp-end">') ?>
      </div>
      <?= $oeField('oe-pp-reason', 'Motivul amânării', '<textarea id="oe-pp-reason" rows="3" maxlength="1000" placeholder="Ex: Din motive tehnice, activitatea a fost amânată…"></textarea>') ?>
      <div class="oe-d-foot"><button class="btn btn-ghost" type="button" data-d-close>Renunță</button><button class="btn oe-btn-warn" type="submit" id="oe-pp-ok">Marchează ca amânată</button></div>
    </form>
  </dialog>

  <dialog class="oe-dialog" id="oe-cancel-d" aria-labelledby="oe-cx-h">
    <form class="oe-d-in" id="oe-cx-form" novalidate>
      <div class="oe-d-head"><h2 id="oe-cx-h">Anulează activitatea</h2><button class="oe-icon-btn" type="button" data-d-close><?= v2_ic('x') ?><span class="sr">Închide</span></button></div>
      <div class="oe-banner is-bad"><?= v2_ic('warning-circle') ?><div><p><b>Atenție!</b> Anularea activității nu poate fi revocată.</p><p>Comenzile neplătite se anulează imediat. Comenzile plătite rămân la echipa bilete.online, care decide rambursarea fiecăreia.</p></div></div>
      <?= $oeField('oe-cx-reason', 'Motivul anulării', '<textarea id="oe-cx-reason" rows="3" maxlength="500" aria-required="true" placeholder="Ex: Din cauza condițiilor meteo, activitatea a fost anulată…"></textarea>', ['req' => true]) ?>
      <div class="oe-f"><label class="oe-check"><input type="checkbox" id="oe-cx-ack" aria-describedby="oe-cx-ack-err"><span>Înțeleg că anularea este definitivă.</span></label><p class="oe-err" id="oe-cx-ack-err" hidden></p></div>
      <div class="oe-d-foot"><button class="btn btn-ghost" type="button" data-d-close>Înapoi</button><button class="btn oe-btn-danger" type="submit" id="oe-cx-ok">Confirmă anularea</button></div>
    </form>
  </dialog>

  <dialog class="oe-dialog" id="oe-confirm-d" aria-labelledby="oe-cf-h" aria-describedby="oe-cf-p">
    <form class="oe-d-in" method="dialog">
      <div class="oe-d-head"><h2 id="oe-cf-h">Confirmă</h2></div>
      <p class="oe-d-p" id="oe-cf-p"></p>
      <div class="oe-d-foot"><button class="btn btn-ghost" value="cancel" id="oe-cf-cancel">Renunță</button><button class="btn btn-primary" value="ok" id="oe-cf-ok">Confirmă</button></div>
    </form>
  </dialog>

  <dialog class="oe-drawer" id="oe-preview-d" aria-labelledby="oe-pv-h">
    <div class="oe-drawer-in">
      <div class="oe-drawer-head">
        <div><h2 id="oe-pv-h">Previzualizare live</h2><p>Cum va apărea activitatea publicului</p></div>
        <button class="oe-icon-btn" type="button" data-d-close><?= v2_ic('x') ?><span class="sr">Închide previzualizarea</span></button>
      </div>
      <div class="oe-drawer-body" id="oe-pv-body"></div>
    </div>
  </dialog>

  <div class="oe-offline" id="oe-offline" role="status" hidden></div>
</div>
<?php
v2_org_end();
include __DIR__ . '/../includes/v2/foot.php';
