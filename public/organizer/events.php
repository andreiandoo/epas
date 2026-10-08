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
 * - cancelling promised refunds core does not make (paid orders go to a manual review); the "undo cancellation" and
 *   "undo postponement" buttons did nothing: a cancellation is final, a postponement can be revoked;
 * - the "activity page" link opened /bilete/{slug}, a 404: activities managed here have no public page on viaqui.com yet;
 * - images: core takes JPG, PNG or WebP up to 10 MB (the page promised any image up to 5 MB), and removing a saved
 *   image only hid it;
 * - core's English errors reached the organizer; confirm() / alert() became dialogs; leaving with unsaved changes is
 *   now caught.
 * Capacity, tags and the sales window are accepted by core but not stored yet: the fields stay, with a plain note.
 *
 * Every text goes through v2_t() / v2_te() (org-events.js: VQ.t / VQ.n). The closures below take labels that are
 * already translated: plain text for $oeSecStart / $oeDrop (escaped where printed), HTML for $oeField.
 */

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/api.php';
require_once __DIR__ . '/../includes/nav-helpers.php';
require_once __DIR__ . '/../includes/v2/helpers.php';
require_once __DIR__ . '/../includes/v2/organizer.php';

$pageTitle = v2_t('Your activities');
$pageDescription = v2_t('Your activities on Viaqui: create, edit, publish and follow each one.');
$canonicalUrl = SITE_URL . '/organizator/activities';
$noindex = true;
$skipPageCache = true;

$v2Styles = ['organizer.css', 'org-events.css'];
$v2Scripts = ['organizer.js', 'org-events.js'];
$v2LegacyScripts = ['assets/js/config.js', 'assets/js/utils.js', 'assets/js/api.js', 'assets/js/auth.js'];
$v2HeadExtra = v2_account_client_config('organizer');

$oeCurrency = defined('SITE_CURRENCY_SYMBOL') ? SITE_CURRENCY_SYMBOL : '€';

/** A form field: label (HTML), control, optional help (HTML), error slot. org-events.js links them with aria-describedby. */
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
          <div class="oe-sec-t"><h2 class="oe-sec-h" id="oe-s<?= $n ?>-h"><?= v2_e($title) ?></h2><p class="oe-sum" id="oe-sum-<?= $n ?>"></p></div>
          <button class="oe-sec-btn" type="button" aria-expanded="<?= $n === 1 ? 'true' : 'false' ?>" aria-controls="oe-s<?= $n ?>-body"><?= v2_ic('caret-down') ?><span class="sr"><?= v2_te('Show or hide: {section}', ['section' => $title]) ?></span></button>
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
$oeSteps = [[1, v2_t('Activity details'), 'pencil-simple'], [2, v2_t('Schedule'), 'calendar-blank'], [3, v2_t('Venue'), 'map-pin'], [4, v2_t('Content'), 'file-text'], [5, v2_t('Media'), 'image'], [6, v2_t('Tickets'), 'ticket'], [7, v2_t('Sales settings'), 'gear-six']];
$oeDrop = function (string $kind, string $label, string $hint, string $ratio) {
    ?>
          <div class="oe-media" data-media="<?= $kind ?>">
            <p class="oe-label" id="oe-<?= $kind ?>-l"><?= v2_e($label) ?></p>
            <div class="oe-media-preview" id="oe-<?= $kind ?>-preview" hidden>
              <div class="oe-media-frame is-<?= $ratio ?>"><img id="oe-<?= $kind ?>-img" alt="<?= v2_te('{image}, preview', ['image' => $label]) ?>"></div>
              <p class="oe-help" id="oe-<?= $kind ?>-note"></p>
              <div class="oe-media-act">
                <button class="oe-mini" type="button" data-media-pick="<?= $kind ?>"><?= v2_ic('upload-simple') ?><?= v2_te('Choose another image') ?></button>
                <button class="oe-mini is-danger" type="button" data-media-undo="<?= $kind ?>" hidden><?= v2_ic('x') ?><?= v2_te('Discard the new image') ?></button>
              </div>
            </div>
            <label class="oe-drop" id="oe-<?= $kind ?>-drop" data-drop="<?= $kind ?>">
              <?= v2_ic('image') ?>
              <b><?= v2_te('Drag the image here or choose a file') ?></b>
              <small><?= v2_e($hint) ?></small>
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
        <p class="org-k"><?= v2_te('Catalogue') ?></p>
        <h1 class="oe-h" id="oe-list-h" tabindex="-1"><?= v2_te('Your activities') ?></h1>
        <p class="oe-lead"><?= v2_te('Manage and follow your activities') ?></p>
      </div>
      <button class="btn btn-primary" type="button" data-oe-new><?= v2_ic('plus') ?><?= v2_te('New activity') ?></button>
    </header>

    <div class="oe-toolbar">
      <label class="oe-search"><?= v2_ic('magnifying-glass') ?><span class="sr"><?= v2_te('Search activities') ?></span><input id="oe-q" type="search" autocomplete="off" spellcheck="false" placeholder="<?= v2_te('Search by name, venue or city…') ?>"></label>
      <div class="oe-pills" role="group" aria-label="<?= v2_te('Filter by status') ?>">
        <button class="oe-pill" type="button" data-status="ongoing" aria-pressed="true"><?= v2_te('In progress') ?> <span class="oe-pill-n" data-count="ongoing">·</span></button>
        <button class="oe-pill" type="button" data-status="draft" aria-pressed="false"><?= v2_te('Drafts') ?> <span class="oe-pill-n" data-count="draft">·</span></button>
        <button class="oe-pill" type="button" data-status="ended" aria-pressed="false"><?= v2_te('Ended') ?> <span class="oe-pill-n" data-count="ended">·</span></button>
        <button class="oe-pill" type="button" data-status="all" aria-pressed="false"><?= v2_te('All') ?> <span class="oe-pill-n" data-count="all">·</span></button>
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
      <button class="oe-back" type="button" data-oe-back title="<?= v2_te('Back to activities') ?>"><?= v2_ic('arrow-left') ?><span class="sr"><?= v2_te('Back to activities') ?></span></button>
      <div class="oe-bar-t">
        <h1 class="oe-bar-h" id="oe-ed-h" tabindex="-1"><?= v2_te('New activity') ?></h1>
        <p class="oe-bar-meta">
          <span id="oe-bar-date" hidden><?= v2_ic('calendar-blank') ?><span></span></span>
          <span id="oe-bar-venue" hidden><?= v2_ic('map-pin') ?><span></span></span>
          <span class="org-tag" id="oe-bar-status" hidden></span>
          <span class="oe-saved" id="oe-saved" role="status"></span>
        </p>
      </div>
      <div class="oe-bar-actions">
        <button class="btn btn-ghost oe-bar-icon" type="button" id="oe-preview-open"><?= v2_ic('eye') ?><span><?= v2_te('Preview') ?></span></button>
        <button class="btn btn-ghost oe-bar-icon is-danger" type="button" id="oe-delete" hidden><?= v2_ic('trash') ?><span><?= v2_te('Delete') ?></span></button>
        <button class="btn btn-ghost" type="button" data-oe-save data-size="short"><span data-label><?= v2_te('Save draft') ?></span></button>
        <button class="btn btn-primary" type="button" data-oe-submit data-size="short"><span data-label><?= v2_te('Submit for approval') ?></span></button>
      </div>
    </div>

    <div class="oe-layout">
      <nav class="oe-outline" id="oe-outline" aria-label="<?= v2_te('Contents of the activity') ?>">
        <p class="oe-ol-k"><?= v2_te('Contents') ?></p>
        <?php foreach ($oeSteps as [$n, $label, $ic]): ?>
        <a class="oe-ol" href="#oe-s<?= $n ?>" data-ol="<?= $n ?>"><?= v2_ic($ic) ?><span class="oe-ol-t"><?= v2_e($label) ?></span><span class="oe-ol-st" data-st><span class="sr"></span></span></a>
        <?php endforeach; ?>
        <p class="oe-issues" id="oe-issues" hidden><?= v2_ic('warning-circle') ?><span id="oe-issues-t"></span></p>
      </nav>

      <form class="oe-form" id="oe-form" autocomplete="off" novalidate>
        <div class="oe-banner is-bad" id="oe-rejected" hidden>
          <?= v2_ic('warning-circle') ?>
          <div><p><b><?= v2_te('The activity was rejected') ?></b></p><p><?= v2_te('Reason:') ?> <span id="oe-rejected-reason"></span></p><p class="oe-banner-s"><?= v2_te('Edit the activity as advised, then press “Save and submit for approval” for a new review.') ?></p></div>
        </div>
        <div class="oe-banner is-info" id="oe-review" hidden>
          <?= v2_ic('clock-countdown') ?>
          <div><p><b><?= v2_te('The activity is under review') ?></b></p><p><?= v2_te('The Viaqui team checks it before it is published. You can keep making changes.') ?></p></div>
        </div>
        <div class="oe-banner is-wait" id="oe-changes" hidden>
          <?= v2_ic('info') ?>
          <div><p><b><?= v2_te('You have changes waiting for approval') ?></b></p><p><?= v2_te('Until they are approved, the activity stays published in its last approved version.') ?></p></div>
        </div>
        <div class="oe-banner is-bad" id="oe-alert" role="alert" hidden>
          <?= v2_ic('warning-circle') ?>
          <div><p id="oe-alert-t"></p><p><a id="oe-alert-a" href="/organizator/setari#contract" hidden><?= v2_te('Go to the contract') ?></a></p></div>
        </div>

        <!-- actions of a live activity -->
        <div class="oe-status" id="oe-status" hidden>
          <div class="oe-status-head"><h2 class="oe-status-h"><?= v2_te('Activity actions') ?></h2><div class="oe-status-chips" id="oe-status-chips"></div></div>
          <div class="oe-status-row">
            <button class="oe-toggle" type="button" id="oe-sold-out"><?= v2_ic('ticket') ?><span><?= v2_te('Mark as sold out') ?></span></button>
            <button class="oe-toggle" type="button" id="oe-door-sales"><?= v2_ic('door-open') ?><span><?= v2_te('Door sales only') ?></span></button>
            <button class="oe-toggle is-warn" type="button" id="oe-postpone"><?= v2_ic('clock-countdown') ?><span><?= v2_te('Postpone the activity') ?></span></button>
            <button class="oe-toggle is-danger" type="button" id="oe-cancel"><?= v2_ic('prohibit') ?><span><?= v2_te('Cancel the activity') ?></span></button>
          </div>
          <p class="oe-help"><?= v2_te('Sold out stops online sales. Door sales only leaves sales at the entrance.') ?></p>
        </div>

        <?php $oeSecStart(1, v2_t('Activity details')); ?>
          <?= $oeField('oe-name', v2_te('Activity name'), '<input type="text" id="oe-name" maxlength="255" aria-required="true" placeholder="' . v2_te('e.g. Boat tour on the lake') . '">', ['req' => true]) ?>
          <div class="oe-grid is-3">
            <?= $oeField('oe-category', v2_te('Category'), '<div class="oe-select"><select id="oe-category"><option value="">' . v2_te('Choose a category') . '</option></select>' . v2_ic('caret-down') . '</div>') ?>
            <?= $oeField('oe-genres-q', v2_te('Activity genre'), '<div class="oe-ms" id="oe-genres-box"><ul class="oe-chips" id="oe-genres-chips" aria-label="' . v2_te('Chosen genres') . '"></ul><input type="text" id="oe-genres-q" role="combobox" aria-expanded="false" aria-controls="oe-genres-list" aria-autocomplete="list" autocomplete="off" placeholder="' . v2_te('Search genres…') . '"><ul class="oe-listbox" id="oe-genres-list" role="listbox" aria-label="' . v2_te('Genres') . '" hidden></ul></div>', ['help' => v2_te('Choose the genres that apply'), 'wrap' => 'oe-genres-f', 'hidden' => true]) ?>
            <?= $oeField('oe-artists-q', v2_te('Artists'), '<div class="oe-ms" id="oe-artists-box"><ul class="oe-chips" id="oe-artists-chips" aria-label="' . v2_te('Chosen artists') . '"></ul><input type="text" id="oe-artists-q" role="combobox" aria-expanded="false" aria-controls="oe-artists-list" aria-autocomplete="list" autocomplete="off" placeholder="' . v2_te('Search artists…') . '"><ul class="oe-listbox" id="oe-artists-list" role="listbox" aria-label="' . v2_te('Artists') . '" hidden></ul></div>', ['help' => v2_te('Search the library or type a new name')]) ?>
          </div>
          <?= $oeField('oe-short', v2_te('Short description'), '<textarea id="oe-short" rows="3" placeholder="' . v2_te('A short description of the activity (120 words at most)') . '"></textarea>', ['help' => '<span id="oe-short-count">' . v2_te('{words}/120 words · {chars}/500 characters', ['words' => 0, 'chars' => 0]) . '</span>']) ?>
          <?= $oeField('oe-tags', v2_te('Tags'), '<input type="text" id="oe-tags" placeholder="' . v2_te('adventure, outdoor, family (separated by commas)') . '">', ['help' => v2_te('Separate the tags with commas. The platform does not save them yet.')]) ?>
        <?php $oeSecEnd(); ?>

        <?php $oeSecStart(2, v2_t('Schedule')); ?>
          <fieldset class="oe-fs">
            <legend class="oe-label"><?= v2_te('Type of duration') ?> <b class="oe-req" aria-hidden="true">*</b></legend>
            <div class="oe-modes">
              <label class="oe-mode"><input type="radio" name="oe-duration" value="single_day" checked><span><b><?= v2_te('A single day') ?></b><small><?= v2_te('The activity takes place on one day') ?></small></span></label>
              <label class="oe-mode"><input type="radio" name="oe-duration" value="range"><span><b><?= v2_te('A range of days') ?></b><small><?= v2_te('The activity runs over several days') ?></small></span></label>
            </div>
          </fieldset>
          <p class="oe-help" id="oe-dm-hint" hidden><?= v2_te('Choose the type of duration to set up the schedule.') ?></p>
          <div class="oe-grid is-2">
            <?= $oeField('oe-start-date', '<span data-range-label="' . v2_te('Start date') . '">' . v2_te('Activity date') . '</span>', '<input type="date" id="oe-start-date" aria-required="true">', ['req' => true]) ?>
            <?= $oeField('oe-start-time', v2_te('Start time'), '<input type="time" id="oe-start-time" aria-required="true">', ['req' => true]) ?>
          </div>
          <div class="oe-grid is-2" id="oe-range-end" hidden>
            <?= $oeField('oe-end-date', v2_te('End date'), '<input type="date" id="oe-end-date" aria-required="true">', ['req' => true]) ?>
            <?= $oeField('oe-end-time', v2_te('End time'), '<input type="time" id="oe-end-time">') ?>
          </div>
          <div class="oe-grid is-2" id="oe-single-end">
            <?= $oeField('oe-end-time-single', v2_te('End time'), '<input type="time" id="oe-end-time-single">') ?>
            <?= $oeField('oe-door-time', v2_te('Doors open at'), '<input type="time" id="oe-door-time">', ['help' => v2_te('The time the doors open')]) ?>
          </div>
          <div class="oe-grid is-2" id="oe-range-door" hidden>
            <?= $oeField('oe-door-time-range', v2_te('Doors open at'), '<input type="time" id="oe-door-time-range">', ['help' => v2_te('The time the doors open')]) ?>
          </div>
        <?php $oeSecEnd(); ?>

        <?php $oeSecStart(3, v2_t('Venue')); ?>
          <?= $oeField('oe-venue', v2_te('Venue name'), '<div class="oe-combo"><input type="text" id="oe-venue" role="combobox" aria-expanded="false" aria-controls="oe-venue-list" aria-autocomplete="list" aria-required="true" autocomplete="off" placeholder="' . v2_te('Search or type the venue name…') . '"><ul class="oe-listbox" id="oe-venue-list" role="listbox" aria-label="' . v2_te('Venues found') . '" hidden></ul></div>', ['req' => true, 'help' => v2_te('Search the venue library or type it yourself')]) ?>
          <div class="oe-note is-wait" id="oe-venue-notice" hidden><?= v2_ic('info') ?><span><?= v2_te('This venue is not in our library. The name you typed will be sent to the platform administrator as a suggestion.') ?></span></div>
          <div class="oe-grid is-2">
            <?= $oeField('oe-city', v2_te('City'), '<input type="text" id="oe-city" maxlength="100" aria-required="true" placeholder="' . v2_te('e.g. Lisbon') . '">', ['req' => true]) ?>
            <?= $oeField('oe-address', v2_te('Address'), '<input type="text" id="oe-address" maxlength="500" placeholder="' . v2_te('e.g. Rua Augusta 10') . '">') ?>
          </div>
          <div class="oe-grid is-2">
            <?= $oeField('oe-website', v2_te('Activity website'), '<input type="url" id="oe-website" maxlength="500" inputmode="url" placeholder="https://…">') ?>
            <?= $oeField('oe-facebook', v2_te('Facebook link'), '<input type="url" id="oe-facebook" maxlength="500" inputmode="url" placeholder="https://facebook.com/events/…">') ?>
          </div>
          <?= $oeField('oe-video', v2_te('YouTube video'), '<input type="url" id="oe-video" maxlength="500" inputmode="url" placeholder="https://www.youtube.com/watch?v=…">', ['help' => v2_te('Any YouTube link (watch, share or embed). It is shown as a video on the public page of the activity.')]) ?>
        <?php $oeSecEnd(); ?>

        <?php $oeSecStart(4, v2_t('Content and description')); ?>
          <?= $oeField('oe-description', v2_te('Full description'), '<textarea id="oe-description" rows="8" placeholder="' . v2_te('Write the description of the activity here…') . '"></textarea>', ['help' => v2_te('Describe the activity in detail: schedule, access rules and so on.'), 'class' => 'is-rte']) ?>
          <?= $oeField('oe-terms', v2_te('Activity terms'), '<textarea id="oe-terms" rows="6" placeholder="' . v2_te('Terms of participation, restrictions, refund policy…') . '"></textarea>', ['help' => v2_te('Terms of participation, age restrictions, special rules, refund policy and so on.'), 'class' => 'is-rte']) ?>
        <?php $oeSecEnd(); ?>

        <?php $oeSecStart(5, v2_t('Media')); ?>
          <div class="oe-grid is-2 oe-media-grid">
            <?php $oeDrop('poster', v2_t('Poster (portrait)'), v2_t('JPG, PNG or WebP · 800×1200 recommended · 10 MB at most'), 'poster'); ?>
            <?php $oeDrop('cover', v2_t('Cover image (landscape)'), v2_t('JPG, PNG or WebP · 1200×630 recommended · 10 MB at most'), 'cover'); ?>
          </div>
        <?php $oeSecEnd(); ?>

        <?php $oeSecStart(6, v2_t('Tickets')); ?>
          <p class="oe-help oe-lead-s"><?= v2_te('Add at least one ticket type. You can add several categories (e.g. Early Bird, Standard, VIP).') ?></p>
          <div class="oe-locked" id="oe-tt-locked" hidden>
            <p class="oe-note" id="oe-tt-locked-note" hidden><?= v2_ic('lock-simple') ?><span id="oe-tt-locked-t"><?= v2_te('Existing ticket types can no longer be changed after publishing, so that sold tickets are not affected. You can add new types.') ?></span></p>
            <ul class="oe-lk-list" id="oe-tt-locked-list"></ul>
          </div>
          <div class="oe-tt-list" id="oe-tt-list"></div>
          <button class="oe-add" type="button" id="oe-tt-add" aria-describedby="oe-tt-add-err"><?= v2_ic('plus') ?><?= v2_te('Add another ticket type') ?></button>
          <p class="oe-err" id="oe-tt-add-err" hidden></p>
        <?php $oeSecEnd(); ?>

        <?php $oeSecStart(7, v2_t('Sales settings')); ?>
          <p class="oe-note"><?= v2_ic('info') ?><span><?= v2_te('For now the platform only uses the maximum number of tickets per order, as the default for new ticket types. Total capacity is worked out from the stock of the ticket types, and the sales period is not saved yet.') ?></span></p>
          <div class="oe-grid is-2">
            <?= $oeField('oe-capacity', v2_te('Total capacity'), '<input type="number" id="oe-capacity" min="1" step="1" inputmode="numeric" placeholder="' . v2_te('e.g. 500') . '">', ['help' => v2_te('The total number of places available')]) ?>
            <?= $oeField('oe-max-order', v2_te('Max. tickets per order'), '<input type="number" id="oe-max-order" min="1" max="50" step="1" inputmode="numeric" placeholder="10">', ['help' => v2_te('How many tickets a customer can buy in one order')]) ?>
          </div>
          <div class="oe-grid is-2">
            <?= $oeField('oe-sales-start', v2_te('Sales start'), '<input type="datetime-local" id="oe-sales-start">', ['help' => v2_te('When sales start (empty = right away)')]) ?>
            <?= $oeField('oe-sales-end', v2_te('Sales end'), '<input type="datetime-local" id="oe-sales-end">', ['help' => v2_te('When sales stop (empty = when the activity starts)')]) ?>
          </div>
        <?php $oeSecEnd(); ?>

        <div class="oe-foot">
          <button class="btn btn-ghost" type="button" data-oe-back><?= v2_ic('arrow-left') ?><?= v2_te('Back to activities') ?></button>
          <div class="oe-foot-act">
            <button class="btn btn-ghost" type="button" data-oe-save data-size="long"><span data-label><?= v2_te('Save draft') ?></span></button>
            <button class="btn btn-primary" type="button" data-oe-submit data-size="long"><span data-label><?= v2_te('Save and submit for approval') ?></span></button>
          </div>
        </div>
      </form>
    </div>

    <div class="oe-mbar" id="oe-mbar">
      <button class="oe-icon-btn" type="button" data-oe-back><?= v2_ic('arrow-left') ?><span class="sr"><?= v2_te('Back to activities') ?></span></button>
      <button class="btn btn-ghost" type="button" data-oe-save data-size="tiny"><span data-label><?= v2_te('Save draft') ?></span></button>
      <button class="btn btn-primary" type="button" data-oe-submit data-size="tiny"><span data-label><?= v2_te('Submit') ?></span></button>
    </div>
  </section>

  <template id="oe-tt-tpl">
    <div class="oe-tt" data-tt>
      <div class="oe-tt-head"><h3 class="oe-tt-h" data-tt-title><?= v2_te('Ticket type #{n}', ['n' => 1]) ?></h3><button class="oe-icon-btn is-danger" type="button" data-tt-remove><?= v2_ic('trash') ?><span class="sr"><?= v2_te('Remove the ticket type') ?></span></button></div>
      <div class="oe-grid is-3">
        <div class="oe-f"><label class="oe-label"><?= v2_te('Ticket name') ?> <b class="oe-req" aria-hidden="true">*</b></label><input type="text" data-f="name" maxlength="255" aria-required="true" placeholder="<?= v2_te('e.g. Standard, VIP, Early Bird') ?>"><p class="oe-err" hidden></p></div>
        <div class="oe-f"><label class="oe-label"><?= v2_te('Price ({currency})', ['currency' => $oeCurrency]) ?> <b class="oe-req" aria-hidden="true">*</b></label><input type="number" data-f="price" min="0" step="0.01" inputmode="decimal" aria-required="true" placeholder="0.00"><p class="oe-err" hidden></p></div>
        <div class="oe-f"><label class="oe-label"><?= v2_te('Ticket stock') ?></label><input type="number" data-f="quantity" min="1" step="1" inputmode="numeric" placeholder="<?= v2_te('Unlimited') ?>"><p class="oe-err" hidden></p></div>
      </div>
      <div class="oe-f"><label class="oe-label"><?= v2_te('Ticket description') ?></label><input type="text" data-f="description" maxlength="500" placeholder="<?= v2_te('e.g. General admission, unnumbered seat') ?>"><p class="oe-err" hidden></p></div>
      <div class="oe-grid is-2">
        <div class="oe-f"><label class="oe-label"><?= v2_te('Min. tickets per order') ?></label><input type="number" data-f="min" min="1" step="1" inputmode="numeric" placeholder="1"><p class="oe-err" hidden></p></div>
        <div class="oe-f"><label class="oe-label"><?= v2_te('Max. tickets per order') ?></label><input type="number" data-f="max" min="1" step="1" inputmode="numeric" placeholder="10"><p class="oe-err" hidden></p></div>
      </div>
    </div>
  </template>

  <dialog class="oe-dialog" id="oe-postpone-d" aria-labelledby="oe-pp-h">
    <form class="oe-d-in" id="oe-pp-form" novalidate>
      <div class="oe-d-head"><h2 id="oe-pp-h"><?= v2_te('Postpone the activity') ?></h2><button class="oe-icon-btn" type="button" data-d-close><?= v2_ic('x') ?><span class="sr"><?= v2_te('Close') ?></span></button></div>
      <?= $oeField('oe-pp-date', v2_te('New date of the activity'), '<input type="date" id="oe-pp-date" aria-required="true">', ['req' => true]) ?>
      <div class="oe-grid is-3">
        <?= $oeField('oe-pp-start', v2_te('Start time'), '<input type="time" id="oe-pp-start">') ?>
        <?= $oeField('oe-pp-door', v2_te('Doors open at'), '<input type="time" id="oe-pp-door">') ?>
        <?= $oeField('oe-pp-end', v2_te('End time'), '<input type="time" id="oe-pp-end">') ?>
      </div>
      <?= $oeField('oe-pp-reason', v2_te('Reason for postponing'), '<textarea id="oe-pp-reason" rows="3" maxlength="1000" placeholder="' . v2_te('e.g. For technical reasons, the activity has been postponed…') . '"></textarea>') ?>
      <div class="oe-d-foot"><button class="btn btn-ghost" type="button" data-d-close><?= v2_te('Never mind') ?></button><button class="btn oe-btn-warn" type="submit" id="oe-pp-ok"><?= v2_te('Mark as postponed') ?></button></div>
    </form>
  </dialog>

  <dialog class="oe-dialog" id="oe-cancel-d" aria-labelledby="oe-cx-h">
    <form class="oe-d-in" id="oe-cx-form" novalidate>
      <div class="oe-d-head"><h2 id="oe-cx-h"><?= v2_te('Cancel the activity') ?></h2><button class="oe-icon-btn" type="button" data-d-close><?= v2_ic('x') ?><span class="sr"><?= v2_te('Close') ?></span></button></div>
      <div class="oe-banner is-bad"><?= v2_ic('warning-circle') ?><div><p><?= v2_t('<b>Warning!</b> Cancelling the activity cannot be undone.') ?></p><p><?= v2_te('Unpaid orders are cancelled right away. Paid orders stay with the Viaqui team, who decide on the refund of each one.') ?></p></div></div>
      <?= $oeField('oe-cx-reason', v2_te('Reason for cancelling'), '<textarea id="oe-cx-reason" rows="3" maxlength="500" aria-required="true" placeholder="' . v2_te('e.g. Because of the weather, the activity has been cancelled…') . '"></textarea>', ['req' => true]) ?>
      <div class="oe-f"><label class="oe-check"><input type="checkbox" id="oe-cx-ack" aria-describedby="oe-cx-ack-err"><span><?= v2_te('I understand that the cancellation is final.') ?></span></label><p class="oe-err" id="oe-cx-ack-err" hidden></p></div>
      <div class="oe-d-foot"><button class="btn btn-ghost" type="button" data-d-close><?= v2_te('Back') ?></button><button class="btn oe-btn-danger" type="submit" id="oe-cx-ok"><?= v2_te('Confirm cancellation') ?></button></div>
    </form>
  </dialog>

  <dialog class="oe-dialog" id="oe-confirm-d" aria-labelledby="oe-cf-h" aria-describedby="oe-cf-p">
    <form class="oe-d-in" method="dialog">
      <div class="oe-d-head"><h2 id="oe-cf-h"><?= v2_te('Confirm') ?></h2></div>
      <p class="oe-d-p" id="oe-cf-p"></p>
      <div class="oe-d-foot"><button class="btn btn-ghost" value="cancel" id="oe-cf-cancel"><?= v2_te('Never mind') ?></button><button class="btn btn-primary" value="ok" id="oe-cf-ok"><?= v2_te('Confirm') ?></button></div>
    </form>
  </dialog>

  <dialog class="oe-drawer" id="oe-preview-d" aria-labelledby="oe-pv-h">
    <div class="oe-drawer-in">
      <div class="oe-drawer-head">
        <div><h2 id="oe-pv-h"><?= v2_te('Live preview') ?></h2><p><?= v2_te('How the public will see the activity') ?></p></div>
        <button class="oe-icon-btn" type="button" data-d-close><?= v2_ic('x') ?><span class="sr"><?= v2_te('Close the preview') ?></span></button>
      </div>
      <div class="oe-drawer-body" id="oe-pv-body"></div>
    </div>
  </dialog>

  <div class="oe-offline" id="oe-offline" role="status" hidden></div>
</div>
<?php
v2_org_end();
include __DIR__ . '/../includes/v2/foot.php';
