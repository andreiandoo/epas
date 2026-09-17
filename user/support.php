<?php
/**
 * Customer support tickets: /cont/tichete-support and /cont/tichete-support/{id} (v2 design).
 *
 * Inside the v2 account shell (includes/v2/account.php). Sections: hero with the open-ticket count, the ticket list
 * with status filters, empty and error states, the new-ticket dialog (department, problem type, subject, message,
 * priority), the thread dialog with the reply form, and the login prompt. support.js talks to
 * /customer/support-tickets, /customer/support-tickets/{id}, /{id}/messages and /customer/support-meta.
 * /cont/tichete-support/{id} (or #t-<id>) opens that ticket.
 *
 * Fixed on the way: creating a ticket never worked. api.js maps POST /customer/support-tickets to the list action and
 * the proxy forced that action to GET, so "Trimite tichetul" loaded the list, got a success back, closed the form, and
 * no ticket was created. The proxy now dispatches on the method, and the page only reports success when the answer
 * carries the new ticket. /cont/tichete-support/{id} pointed at a file that doesn't exist; it now opens the ticket here.
 */

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/api.php';
require_once __DIR__ . '/../includes/nav-helpers.php';
require_once __DIR__ . '/../includes/v2/helpers.php';
require_once __DIR__ . '/../includes/v2/nav.php';
require_once __DIR__ . '/../includes/v2/account.php';

$spTicket = isset($_GET['ticket']) && ctype_digit((string) $_GET['ticket']) ? (int) $_GET['ticket'] : 0;

$pageTitleRaw = 'Tichete suport — ' . SITE_NAME;
$pageDescription = 'Trimite și urmărește solicitările tale către echipa bilete.online.';
$canonicalUrl = SITE_URL . '/cont/tichete-support';
$noindex = true;
$skipPageCache = true;

$v2Styles = ['account.css', 'support.css'];
$v2Scripts = ['account.js', 'support.js'];
$v2LegacyScripts = ['assets/js/config.js', 'assets/js/utils.js', 'assets/js/api.js', 'assets/js/auth.js'];
$v2HeadExtra = v2_account_client_config();
$v2FooterCompact = true;
$v2ClientData = ['ticket' => $spTicket];

include __DIR__ . '/../includes/v2/head.php';
include __DIR__ . '/../includes/v2/header.php';
?>
<main id="main" tabindex="-1" class="page-main acc-page">
  <div class="wrap">
    <?php v2_account_start('support'); ?>

    <!-- not signed in -->
    <section class="acc-guard" id="sp-guard" hidden aria-labelledby="sp-guard-h">
      <span class="acc-guard-ic" aria-hidden="true"><?= v2_ic('lock-simple') ?></span>
      <h1 id="sp-guard-h">Trebuie să fii autentificat</h1>
      <p>Intră în cont pentru a vedea tichetele de suport.</p>
      <a class="btn btn-primary" href="/autentificare?redirect=%2Fcont%2Ftichete-support">Intră în cont<?= v2_ic('arrow-right') ?></a>
    </section>

    <div class="acc-body" id="sp-content">
      <!-- HERO -->
      <section class="acc-hero sp-hero" aria-labelledby="sp-h">
        <div>
          <p class="acc-kicker">Suport client</p>
          <h1 class="acc-h" id="sp-h">Tichete suport</h1>
          <p class="acc-lead">Trimite o solicitare către echipă și urmărește statusul fiecărui tichet într-un singur loc.</p>
          <div class="sp-cta">
            <button class="btn btn-light" type="button" data-sp-new><?= v2_ic('plus') ?>Tichet nou</button>
            <a class="btn btn-outline-light" href="/ajutor">Vezi FAQ</a>
          </div>
        </div>
        <article class="sp-count" aria-labelledby="sp-count-k">
          <p class="acc-k" id="sp-count-k">Deschise</p>
          <p class="sp-count-v" id="sp-open">0</p>
          <p class="sp-count-l">solicitări active</p>
          <p class="sp-count-t" id="sp-total">0 tichete în total</p>
        </article>
      </section>

      <!-- TICKETS -->
      <section class="acc-results" id="tichete" aria-labelledby="sp-count">
        <div class="acc-results-head">
          <div><p class="acc-k">Solicitări</p><h2 class="acc-count" id="sp-count">0 tichete</h2></div>
          <div class="acc-bulk"><button class="btn btn-primary" type="button" data-sp-new><?= v2_ic('plus') ?>Tichet nou</button></div>
        </div>
        <div class="acc-pills sp-pills" role="group" aria-label="Filtrează tichetele">
          <button class="acc-pill" type="button" data-filter="all" aria-pressed="true">Toate</button>
          <button class="acc-pill is-warn" type="button" data-filter="active" aria-pressed="false">Active</button>
          <button class="acc-pill" type="button" data-filter="closed" aria-pressed="false">Închise</button>
        </div>
        <p class="acc-status" id="sp-status-line" role="status"></p>
        <div class="acc-skel sp-skel" id="sp-skel" aria-hidden="true"><i></i><i></i></div>
        <ul class="sp-list" id="sp-list" hidden></ul>
        <div class="acc-empty" id="sp-empty" hidden>
          <span class="acc-empty-ic" aria-hidden="true"><?= v2_ic('headset') ?></span>
          <b id="sp-empty-h">Niciun tichet deschis</b>
          <p id="sp-empty-p">Trimite o solicitare echipei dacă ai nevoie de ajutor.</p>
          <button class="btn btn-primary" type="button" data-sp-new id="sp-empty-new">Tichet nou</button>
        </div>
        <div class="acc-empty is-error" id="sp-error" hidden>
          <b>Nu am putut încărca tichetele.</b>
          <p>Verifică conexiunea și încearcă din nou.</p>
          <button class="btn btn-ghost" type="button" id="sp-retry">Încearcă din nou</button>
        </div>
      </section>
    </div>

    <!-- NEW TICKET -->
    <dialog class="sp-dialog" id="sp-new" aria-labelledby="sp-new-h">
      <form class="sp-form" id="sp-new-form" novalidate>
        <div class="sp-d-head">
          <div><p class="acc-k">Suport</p><h2 id="sp-new-h">Tichet nou</h2></div>
          <button class="sp-d-close" type="button" data-close aria-label="Închide"><?= v2_ic('x') ?></button>
        </div>
        <div class="sp-d-body">
          <p class="sp-d-intro">Spune-ne ce s-a întâmplat. Dacă e vorba de o comandă, scrie și numărul ei, ca să te putem ajuta mai repede.</p>
          <div class="acc-field" id="sp-dep-field" hidden>
            <label for="sp-dep">Departament</label>
            <span class="acc-select"><select id="sp-dep"><option value="">— alege —</option></select><?= v2_ic('caret-down') ?></span>
          </div>
          <div class="acc-field" id="sp-type-field" hidden>
            <label for="sp-type">Tip problemă</label>
            <span class="acc-select"><select id="sp-type"><option value="">— alege —</option></select><?= v2_ic('caret-down') ?></span>
          </div>
          <div class="acc-field">
            <label for="sp-subject">Subiect</label>
            <span class="acc-input is-plain"><input id="sp-subject" maxlength="200" autocomplete="off" required aria-describedby="sp-new-error"></span>
          </div>
          <div class="acc-field">
            <label for="sp-message">Mesaj</label>
            <textarea class="sp-textarea" id="sp-message" maxlength="5000" rows="6" required aria-describedby="sp-message-count sp-new-error"></textarea>
            <small class="sp-counter" id="sp-message-count">0 / 5.000</small>
          </div>
          <div class="acc-field">
            <label for="sp-priority">Prioritate</label>
            <span class="acc-select"><select id="sp-priority">
              <option value="normal" selected>Normală</option>
              <option value="high">Ridicată</option>
              <option value="urgent">Urgentă</option>
              <option value="low">Scăzută</option>
            </select><?= v2_ic('caret-down') ?></span>
          </div>
          <p class="sp-error" id="sp-new-error" role="alert" hidden></p>
        </div>
        <div class="sp-d-foot">
          <button class="btn btn-primary" type="submit" id="sp-new-submit">Trimite tichetul</button>
          <button class="btn btn-ghost" type="button" data-close>Anulează</button>
        </div>
      </form>
    </dialog>

    <!-- THREAD -->
    <dialog class="sp-dialog is-thread" id="sp-thread" aria-labelledby="sp-t-h">
      <div class="sp-d-head">
        <div class="sp-t-head">
          <p class="acc-k" id="sp-t-num">—</p>
          <h2 id="sp-t-h">Tichet</h2>
          <div class="sp-tags" id="sp-t-tags"></div>
        </div>
        <button class="sp-d-close" type="button" data-close aria-label="Închide"><?= v2_ic('x') ?></button>
      </div>
      <div class="sp-d-body" id="sp-t-body">
        <p class="sp-t-state" id="sp-t-state" role="status">Se încarcă conversația…</p>
        <ol class="sp-thread" id="sp-t-messages" aria-label="Mesaje"></ol>
        <p class="sp-closed" id="sp-t-closed" hidden>Tichet închis. Deschide unul nou pentru o solicitare nouă.</p>
      </div>
      <form class="sp-d-foot sp-reply" id="sp-reply" hidden novalidate>
        <p class="sp-reopen" id="sp-reopen" hidden>Problema nu e rezolvată? Răspunde aici și tichetul se redeschide.</p>
        <label class="sp-sr" for="sp-reply-text">Răspunsul tău</label>
        <textarea class="sp-textarea" id="sp-reply-text" maxlength="5000" rows="3" placeholder="Răspuns…" required aria-describedby="sp-reply-count sp-reply-error"></textarea>
        <p class="sp-error" id="sp-reply-error" role="alert" hidden></p>
        <div class="sp-reply-row">
          <small class="sp-counter" id="sp-reply-count">0 / 5.000</small>
          <button class="btn btn-primary" type="submit" id="sp-reply-submit" disabled>Trimite răspunsul</button>
        </div>
      </form>
    </dialog>

    <?php v2_account_end(); ?>
  </div>
</main>
<?php include __DIR__ . '/../includes/v2/footer.php'; ?>
