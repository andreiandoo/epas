<?php
/**
 * Customer reviews: /cont/recenzii (v2 design).
 *
 * Inside the v2 account shell (includes/v2/account.php). Hero with the average rating given, four counters, "De
 * evaluat" (one activity at a time: stars, text, who it suits, detailed ratings, photos, draft / publish, writing
 * guide), "Istoric" (search, status and rating filters, review cards with edit / delete, drafts), three info cards.
 * reviews.js talks to /customer/reviews (list, paginated), /customer/reviews/events-to-review, /customer/reviews/meta,
 * POST /customer/reviews (JSON, or multipart when photos are attached), PUT + DELETE /customer/reviews/{id}.
 *
 * Fixed on the way: "Salvează draft" POSTed the review, and core has no drafts (every review goes to moderation), so a
 * draft was really submitted: drafts are kept on this device now. Photos went as data URLs inside JSON, which core
 * rejects (it takes uploaded files only), so any review with photos failed: they are uploaded as files now (the proxy
 * forwards multipart). Core needs at least 20 characters; the old page let a 10-character review through to a 422.
 * Rejected reviews were shown as published. Editing loaded a review into the form of another activity and re-sent the
 * rating and text every time, which sends an approved review back to moderation: it opens in its own dialog and only
 * changed fields are sent. confirm() / alert() became inline confirmations and messages.
 */

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/api.php';
require_once __DIR__ . '/../includes/nav-helpers.php';
require_once __DIR__ . '/../includes/v2/helpers.php';
require_once __DIR__ . '/../includes/v2/nav.php';
require_once __DIR__ . '/../includes/v2/account.php';

$pageTitleRaw = 'Recenziile mele — ' . SITE_NAME;
$pageDescription = 'Recenziile mele pe bilete.online: scrie pentru activitățile la care ai fost, editează drafturi și ajută alți clienți să aleagă.';
$canonicalUrl = SITE_URL . '/cont/recenzii';
$noindex = true;
$skipPageCache = true;

$v2Styles = ['account.css', 'reviews.css'];
$v2Scripts = ['account.js', 'reviews.js'];
$v2LegacyScripts = ['assets/js/config.js', 'assets/js/utils.js', 'assets/js/api.js', 'assets/js/auth.js'];
$v2HeadExtra = v2_account_client_config();

$rvStar = '<svg class="rv-star-ic" viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path d="M12 2.6l2.84 5.93 6.53.86-4.78 4.53 1.2 6.47L12 17.25l-5.79 3.15 1.2-6.47L2.63 9.39l6.53-.86z"/></svg>';
$rvStars = function (string $name, bool $small = false, bool $optional = false) use ($rvStar): string {
    $html = '<div class="rv-stars' . ($small ? ' is-small' : '') . '" data-stars="' . $name . '"' . ($optional ? ' data-optional' : '') . '>';
    for ($n = 1; $n <= 5; $n++) {
        $html .= '<label class="rv-star"><input type="radio" name="' . $name . '" value="' . $n . '"><span class="sr">'
            . ($n === 1 ? '1 stea' : $n . ' stele') . '</span>' . $rvStar . '</label>';
    }
    return $html . '</div>';
};
$rvSuitable = [['children', 'Copii'], ['family', 'Familie'], ['couple', 'Cuplu'], ['groups', 'Grupuri'], ['team_building', 'Team building'], ['solo', 'Solo']];
$rvAges = [['3-6', '3–6 ani'], ['6-10', '6–10 ani'], ['10-14', '10–14 ani'], ['14-18', '14–18 ani'], ['adults', 'Adulți'], ['all', 'Toate vârstele']];
$rvAspects = ['show' => 'Experiența', 'venue' => 'Locația', 'organization' => 'Organizarea', 'value' => 'Raport calitate-preț'];
$rvCaret = v2_ic('caret-down');

include __DIR__ . '/../includes/v2/head.php';
include __DIR__ . '/../includes/v2/header.php';
?>
<main id="main" tabindex="-1" class="page-main acc-page">
  <div class="wrap">
    <?php v2_account_start('reviews'); ?>

    <!-- not signed in -->
    <section class="acc-guard" id="rv-guard" hidden aria-labelledby="rv-guard-h">
      <span class="acc-guard-ic" aria-hidden="true"><?= v2_ic('lock-simple') ?></span>
      <h1 id="rv-guard-h">Trebuie să fii autentificat</h1>
      <p>Intră în cont pentru a-ți vedea și scrie recenziile.</p>
      <a class="btn btn-primary" href="/autentificare?redirect=%2Fcont%2Frecenzii">Intră în cont<?= v2_ic('arrow-right') ?></a>
    </section>

    <div class="acc-body" id="rv-content">
      <!-- HERO -->
      <section class="acc-hero rv-hero" aria-labelledby="rv-h">
        <div>
          <p class="acc-kicker">Client reviews</p>
          <h1 class="acc-h" id="rv-h">Recenziile mele</h1>
          <p class="acc-lead">Scrie recenzii pentru activitățile la care ai fost, vezi ce ai publicat, editează drafturi și ajută sistemul să îți recomande experiențe mai potrivite.</p>
          <div class="rv-cta">
            <a class="btn btn-light" href="#de-evaluat"><?= v2_ic('star') ?>Scrie recenzie</a>
            <a class="btn btn-outline-light" href="/cont/recomandari">Vezi recomandări</a>
          </div>
        </div>
        <article class="rv-score" aria-labelledby="rv-score-k">
          <p class="acc-k" id="rv-score-k">Review score</p>
          <p class="rv-score-v" id="rv-avg">0,0</p>
          <p class="rv-score-l">rating mediu oferit · <span id="rv-pub-label">0 recenzii publicate</span></p>
          <div class="rv-score-stars" id="rv-avg-stars" role="img" aria-label="Rating mediu 0,0 din 5"><?= str_repeat($rvStar, 5) ?></div>
          <p class="rv-score-hint" id="rv-score-hint">Se verifică activitățile de evaluat…</p>
        </article>
      </section>

      <!-- COUNTERS -->
      <section class="rv-stats" aria-label="Pe scurt">
        <article class="rv-stat"><p class="acc-k">Publicate</p><p class="rv-stat-v" id="rv-s-pub">—</p><p class="rv-stat-p">recenzii vizibile</p></article>
        <article class="rv-stat is-mint"><p class="acc-k">De evaluat</p><p class="rv-stat-v" id="rv-s-todo">—</p><p class="rv-stat-p" id="rv-s-todo-p">se verifică…</p></article>
        <article class="rv-stat"><p class="acc-k">Drafturi</p><p class="rv-stat-v" id="rv-s-draft">—</p><p class="rv-stat-p">nefinalizate, pe acest dispozitiv</p></article>
        <article class="rv-stat is-rose"><p class="acc-k">Moderare</p><p class="rv-stat-v" id="rv-s-mod">—</p><p class="rv-stat-p">în verificare</p></article>
      </section>
      <p class="rv-flash" id="rv-flash" role="status" aria-live="polite"></p>

      <!-- TO REVIEW -->
      <section class="acc-panel rv-panel" id="de-evaluat" aria-labelledby="rv-todo-h">
        <div class="rv-head">
          <div><p class="acc-k">De evaluat</p><h2 id="rv-todo-h">Activități care așteaptă recenzia ta</h2></div>
          <div class="rv-pager" id="rv-pager" hidden>
            <button class="rv-pager-btn" type="button" id="rv-prev" aria-label="Activitatea anterioară"><?= v2_ic('arrow-left') ?></button>
            <span class="rv-pager-t" id="rv-pos">1 din 1</span>
            <button class="rv-pager-btn" type="button" id="rv-next" aria-label="Activitatea următoare"><?= v2_ic('arrow-right') ?></button>
          </div>
        </div>

        <div class="rv-skel is-tall" id="rv-todo-skel" aria-hidden="true"></div>
        <div class="rv-empty is-error" id="rv-todo-error" hidden>
          <h3>Nu am putut încărca activitățile de evaluat</h3>
          <p>Verifică conexiunea și încearcă din nou.</p>
          <button class="btn btn-ghost" type="button" id="rv-todo-retry">Reîncearcă</button>
        </div>
        <div class="rv-empty" id="rv-todo-empty" hidden>
          <span class="rv-empty-ic" aria-hidden="true"><?= v2_ic('check-circle') ?></span>
          <h3 id="rv-todo-empty-h" tabindex="-1">Nicio activitate de evaluat acum</h3>
          <p>După ce participi la o activitate, va apărea aici pentru recenzie.</p>
        </div>

        <div class="rv-todo" id="rv-todo" hidden>
          <article class="rv-write" aria-labelledby="rv-ev-title">
            <div class="rv-write-media is-empty" id="rv-ev-media"><?= v2_ic('star') ?></div>
            <div class="rv-write-body">
              <div class="rv-tags"><span class="acc-tag is-ok">participare confirmată</span><span class="acc-tag" id="rv-ev-date"></span></div>
              <h3 class="rv-ev-title" id="rv-ev-title" tabindex="-1"></h3>
              <p class="rv-ev-where" id="rv-ev-where"></p>

              <form class="rv-form" id="rv-form" novalidate>
                <fieldset class="rv-rate">
                  <legend>Rating rapid</legend>
                  <?= $rvStars('rv-rating') ?>
                  <span class="rv-rate-t" id="rv-rating-t" aria-live="polite">Alege de la 1 la 5 stele</span>
                </fieldset>

                <div class="acc-field">
                  <label for="rv-text">Ce ți-a plăcut sau ce ar trebui să știe alții?</label>
                  <textarea class="rv-textarea" id="rv-text" rows="5" maxlength="2000" aria-describedby="rv-text-count" placeholder="Scrie sincer, util și concret. De exemplu: cât a durat, pentru ce vârstă e potrivit, cum a fost accesul, dacă ai merge din nou."></textarea>
                  <small class="rv-count" id="rv-text-count">0 / 2.000 · mai scrie 20 de caractere</small>
                </div>

                <div class="rv-grid">
                  <div class="acc-field">
                    <label for="rv-suitable">Potrivit pentru</label>
                    <span class="acc-select"><select id="rv-suitable"><?php foreach ($rvSuitable as [$rvValue, $rvLabel]): ?><option value="<?= v2_e($rvValue) ?>"<?= $rvValue === 'family' ? ' selected' : '' ?>><?= v2_e($rvLabel) ?></option><?php endforeach; ?></select><?= $rvCaret ?></span>
                  </div>
                  <div class="acc-field">
                    <label for="rv-age">Vârsta recomandată</label>
                    <span class="acc-select"><select id="rv-age"><?php foreach ($rvAges as [$rvValue, $rvLabel]): ?><option value="<?= v2_e($rvValue) ?>"<?= $rvValue === 'all' ? ' selected' : '' ?>><?= v2_e($rvLabel) ?></option><?php endforeach; ?></select><?= $rvCaret ?></span>
                  </div>
                </div>

                <details class="rv-more" id="rv-more">
                  <summary><span>Evaluare detaliată și opțiuni <small>(opțional)</small></span></summary>
                  <div class="rv-more-body">
                    <?php foreach ($rvAspects as $rvKey => $rvLabel): ?>
                    <fieldset class="rv-aspect"><legend><?= v2_e($rvLabel) ?></legend><?= $rvStars('rv-a-' . $rvKey, true, true) ?></fieldset>
                    <?php endforeach; ?>
                    <p class="rv-note">Apasă din nou pe steaua aleasă ca să ștergi o notă detaliată.</p>
                    <label class="rv-switch-row" for="rv-recommend"><span>Recomand această activitate</span><input class="rv-switch" type="checkbox" role="switch" id="rv-recommend" checked></label>
                    <label class="rv-switch-row" for="rv-anonymous"><span>Publică fără numele meu</span><input class="rv-switch" type="checkbox" role="switch" id="rv-anonymous"></label>
                  </div>
                </details>

                <div class="rv-photos">
                  <ul class="rv-thumbs" id="rv-thumbs" aria-label="Poze atașate" hidden></ul>
                  <button class="btn btn-ghost" type="button" id="rv-attach"><?= v2_ic('plus') ?>Atașează poze</button>
                  <input class="rv-file" type="file" id="rv-photo-input" accept="image/jpeg,image/png,image/webp,image/gif" multiple hidden>
                  <small class="rv-note" id="rv-photo-note">Până la 5 poze JPG, PNG, WebP sau GIF, maximum 5 MB fiecare.</small>
                </div>

                <p class="rv-error" id="rv-form-error" role="alert" hidden></p>
                <div class="rv-actions">
                  <button class="btn btn-ghost" type="button" id="rv-draft">Salvează draft</button>
                  <button class="btn btn-primary" type="submit" id="rv-submit">Publică recenzia</button>
                </div>
                <small class="rv-note" id="rv-draft-note">Recenziile apar pe site după o scurtă verificare.</small>
              </form>
            </div>
          </article>

          <aside class="rv-guide" aria-labelledby="rv-guide-h">
            <p class="acc-k">Ghid recenzie bună</p>
            <h3 id="rv-guide-h">Scrie pentru omul care decide.</h3>
            <p><strong>Concret:</strong> spune durata reală, accesul, aglomerația, vârsta potrivită.</p>
            <p><strong>Util:</strong> menționează dacă ai merge din nou și cu cine.</p>
            <p><strong>Corect:</strong> evită date personale sau informații care nu țin de activitate.</p>
          </aside>
        </div>
      </section>

      <!-- HISTORY -->
      <section class="acc-panel rv-panel" id="istoric" aria-labelledby="rv-list-h">
        <p class="acc-k">Istoric</p>
        <h2 id="rv-list-h" tabindex="-1">Recenzii publicate și drafturi</h2>
        <div class="rv-filters">
          <div class="acc-field">
            <label for="rv-q">Caută</label>
            <span class="acc-input"><?= v2_ic('magnifying-glass') ?><input id="rv-q" type="search" maxlength="100" placeholder="Caută recenzie..." autocomplete="off"></span>
          </div>
          <div class="acc-field">
            <label for="rv-status">Status</label>
            <span class="acc-select"><select id="rv-status"><option value="all">Toate</option><option value="published">Publicate</option><option value="draft">Drafturi</option><option value="moderation">În moderare</option><option value="rejected">Respinse</option></select><?= $rvCaret ?></span>
          </div>
          <div class="acc-field">
            <label for="rv-rating-filter">Rating</label>
            <span class="acc-select"><select id="rv-rating-filter"><option value="all">Orice rating</option><option value="5">5 stele</option><option value="4">4 stele</option><option value="3">3 stele</option><option value="2">2 stele</option><option value="1">1 stea</option></select><?= $rvCaret ?></span>
          </div>
        </div>
        <div class="rv-list-head">
          <p class="rv-list-count" id="rv-list-count" aria-live="polite"></p>
          <button class="btn btn-ghost" type="button" id="rv-reset" hidden>Resetează filtrele</button>
        </div>

        <div class="rv-skels" id="rv-list-skel" aria-hidden="true"><div class="rv-skel"></div><div class="rv-skel"></div></div>
        <div class="rv-empty is-error" id="rv-list-error" hidden>
          <h3>Nu am putut încărca recenziile</h3>
          <p>Verifică conexiunea și încearcă din nou.</p>
          <button class="btn btn-ghost" type="button" id="rv-list-retry">Reîncearcă</button>
        </div>
        <ul class="rv-list" id="rv-list" hidden></ul>
        <div class="rv-empty" id="rv-list-empty" hidden>
          <h3 id="rv-list-empty-h">Încă nu ai recenzii</h3>
          <p id="rv-list-empty-p">Recenziile pe care le scrii apar aici, împreună cu drafturile.</p>
          <button class="btn btn-ghost" type="button" id="rv-list-empty-reset" hidden>Resetează filtrele</button>
        </div>
      </section>

      <!-- INFO -->
      <section class="rv-cards" aria-label="De ce contează recenziile">
        <article class="rv-info"><p class="acc-k">Personalizare</p><h2>Recenziile antrenează recomandările.</h2><p>Ratingurile și preferințele din review-uri pot îmbunătăți recomandările viitoare.</p></article>
        <article class="rv-info is-mint"><p class="acc-k">Comunitate</p><h2>Ajută alți clienți.</h2><p>O recenzie bună reduce incertitudinea și crește încrederea în activități.</p></article>
        <article class="rv-info is-deep"><p class="acc-k">Bonus</p><h2>Review-uri cu puncte?</h2><p>Recenziile eligibile pot aduce puncte bonus când campaniile sunt active.</p></article>
      </section>

      <!-- EDIT -->
      <dialog class="rv-dialog" id="rv-edit" aria-labelledby="rv-edit-h">
        <form class="rv-d-form" id="rv-edit-form" novalidate>
          <div class="rv-d-head">
            <div><p class="acc-k">Editează recenzia</p><h2 id="rv-edit-h"></h2></div>
            <button class="rv-d-close" type="button" data-close aria-label="Închide"><?= v2_ic('x') ?></button>
          </div>
          <div class="rv-d-body">
            <p class="rv-d-intro">Dacă schimbi ratingul sau textul, recenzia trece din nou prin verificare și nu apare pe site până la aprobare.</p>
            <fieldset class="rv-rate">
              <legend>Rating</legend>
              <?= $rvStars('rv-e-rating') ?>
              <span class="rv-rate-t" id="rv-e-rating-t" aria-live="polite"></span>
            </fieldset>
            <div class="acc-field">
              <label for="rv-e-text">Recenzia ta</label>
              <textarea class="rv-textarea" id="rv-e-text" rows="6" maxlength="2000" aria-describedby="rv-e-text-count"></textarea>
              <small class="rv-count" id="rv-e-text-count"></small>
            </div>
            <details class="rv-more" id="rv-e-more">
              <summary><span>Evaluare detaliată și opțiuni</span></summary>
              <div class="rv-more-body">
                <?php foreach ($rvAspects as $rvKey => $rvLabel): ?>
                <fieldset class="rv-aspect"><legend><?= v2_e($rvLabel) ?></legend><?= $rvStars('rv-e-a-' . $rvKey, true, true) ?></fieldset>
                <?php endforeach; ?>
                <label class="rv-switch-row" for="rv-e-recommend"><span>Recomand această activitate</span><input class="rv-switch" type="checkbox" role="switch" id="rv-e-recommend"></label>
                <label class="rv-switch-row" for="rv-e-anonymous"><span>Publică fără numele meu</span><input class="rv-switch" type="checkbox" role="switch" id="rv-e-anonymous"></label>
              </div>
            </details>
            <p class="rv-note" id="rv-e-photos" hidden></p>
            <p class="rv-error" id="rv-edit-error" role="alert" hidden></p>
          </div>
          <div class="rv-d-foot">
            <button class="btn btn-primary" type="submit" id="rv-edit-save">Salvează modificările</button>
            <button class="btn btn-ghost" type="button" data-close>Renunță</button>
          </div>
        </form>
      </dialog>
    </div>

    <?php v2_account_end(); ?>
  </div>
</main>
<?php include __DIR__ . '/../includes/v2/footer.php'; ?>
