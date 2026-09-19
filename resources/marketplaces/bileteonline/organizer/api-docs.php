<?php
/**
 * Operator API documentation: /organizator/apidoc (api-docs.php), v2 design.
 *
 * Inside the v2 operator shell. The same reference as before, restyled: the API key (shown, copied, regenerated after a
 * confirmation that is now a dialog instead of the browser's confirm), the contents (a side column on wide screens, a
 * row of links on phones, where the old page had none), the overview with the base URL, authentication with the cURL
 * example, the rate limit, the HTTP codes, the activities endpoints (list with its query parameters and response
 * example, create with its parameters), ticket validation with its response, webhooks (the URL form and the events)
 * and the help box. The technical content is unchanged. org-apidocs.js calls /organizer/api-key,
 * /organizer/api-key/regenerate and /organizer/webhook through the proxy (organizer.api-key, .regenerate, .webhook).
 *
 * Kept as it was, to raise: core has no /organizer/webhook route, so saving the webhook URL fails (the page says so).
 */

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/api.php';
require_once __DIR__ . '/../includes/nav-helpers.php';
require_once __DIR__ . '/../includes/v2/helpers.php';
require_once __DIR__ . '/../includes/v2/organizer.php';

$apiBase = 'https://api.' . SITE_NAME . '/v1';

$pageTitleRaw = 'Documentație API — ' . SITE_NAME;
$pageDescription = 'Documentația API-ului bilete.online pentru operatori: autentificare, limite, erori, endpoint-uri și webhooks.';
$canonicalUrl = SITE_URL . '/organizator/apidoc';
$noindex = true;
$skipPageCache = true;

$v2Styles = ['organizer.css', 'org-apidocs.css'];
$v2Scripts = ['organizer.js', 'org-apidocs.js'];
$v2LegacyScripts = ['assets/js/config.js', 'assets/js/utils.js', 'assets/js/api.js', 'assets/js/auth.js'];
$v2HeadExtra = v2_account_client_config('organizer');

/** A dark code block: label, trusted markup for the code, with or without a copy button. */
$oadCode = function (string $label, string $code, bool $copy = true) {
    return '<div class="oad-code"><div class="oad-code-head"><span>' . $label . '</span>'
        . ($copy ? '<button class="oad-copy" type="button" data-copy aria-label="Copiază codul ' . $label . '">' . v2_ic('copy') . '<span data-label>Copiază</span></button>' : '')
        . '</div><pre tabindex="0"><code>' . $code . '</code></pre></div>';
};
/** A parameters table: rows of [name, type, required, description]. The roles keep it a table when phones lay the rows out as cards. */
$oadParams = function (array $rows) {
    $out = '<table class="oad-table oad-params" role="table"><thead role="rowgroup"><tr role="row"><th scope="col" role="columnheader">Parametru</th><th scope="col" role="columnheader">Tip</th>'
        . '<th scope="col" role="columnheader">Oblig.</th><th scope="col" role="columnheader">Descriere</th></tr></thead><tbody role="rowgroup">';
    foreach ($rows as [$name, $type, $req, $desc]) {
        $out .= '<tr role="row"><td role="cell"><code class="oad-param">' . $name . '</code></td><td role="cell"><span class="oad-type">' . $type . '</span></td>'
            . '<td role="cell">' . ($req ? '<span class="oad-req is-yes">Da</span>' : '<span class="oad-req">Nu</span>') . '</td><td class="oad-desc" role="cell">' . $desc . '</td></tr>';
    }
    return $out . '</tbody></table>';
};
$oadMethod = function (string $m) {
    return '<span class="oad-m is-' . strtolower($m) . '">' . $m . '</span>';
};

include __DIR__ . '/../includes/v2/head.php';
v2_org_start('help');
?>
<div class="oad" id="oad">
  <header class="oad-head">
    <p class="org-k">Pentru dezvoltatori</p>
    <h1 class="oad-h">Documentație API</h1>
    <p class="oad-lead">Integrează <?= v2_e(SITE_NAME) ?> în aplicația ta folosind API-ul nostru RESTful.</p>
  </header>

  <!-- API key -->
  <section class="oad-key" aria-labelledby="oad-key-h">
    <div class="oad-key-top">
      <h2 class="oad-key-h" id="oad-key-h"><?= v2_ic('lock-simple') ?>Cheia ta API</h2>
      <button class="oad-key-btn" type="button" id="oad-regen"><?= v2_ic('arrow-counter-clockwise') ?><span data-label>Regenerează</span></button>
    </div>
    <div class="oad-key-row">
      <code class="oad-key-v" id="oad-key" aria-live="polite">Se încarcă…</code>
      <button class="oad-key-copy" type="button" id="oad-key-copy" disabled><?= v2_ic('copy') ?><span class="sr">Copiază cheia API</span></button>
    </div>
    <p class="oad-key-note">Nu partaja niciodată cheia API. Dacă a fost compromisă, regenereaz-o imediat.</p>
  </section>

  <div class="oad-grid">
    <nav class="oad-toc" aria-label="Cuprinsul documentației">
      <div class="oad-toc-g">
        <p class="oad-toc-k" id="oad-toc-1">Introducere</p>
        <ul aria-labelledby="oad-toc-1">
          <li><a href="#overview">Prezentare generală</a></li>
          <li><a href="#authentication">Autentificare</a></li>
          <li><a href="#rate-limits">Limite rate</a></li>
          <li><a href="#errors">Gestionare erori</a></li>
        </ul>
      </div>
      <div class="oad-toc-g">
        <p class="oad-toc-k" id="oad-toc-2">Activități</p>
        <ul aria-labelledby="oad-toc-2">
          <li><a href="#list-events"><?= $oadMethod('GET') ?>Listă</a></li>
          <li><a href="#create-event"><?= $oadMethod('POST') ?>Creează</a></li>
        </ul>
      </div>
      <div class="oad-toc-g">
        <p class="oad-toc-k" id="oad-toc-3">Bilete</p>
        <ul aria-labelledby="oad-toc-3"><li><a href="#validate-ticket"><?= $oadMethod('POST') ?>Validare</a></li></ul>
      </div>
      <div class="oad-toc-g">
        <p class="oad-toc-k" id="oad-toc-4">Webhooks</p>
        <ul aria-labelledby="oad-toc-4"><li><a href="#webhooks">Configurare</a></li></ul>
      </div>
    </nav>

    <div class="oad-body">
      <section class="oad-sec" id="overview" aria-labelledby="oad-overview-h" tabindex="-1">
        <h2 class="oad-sec-h" id="oad-overview-h">Prezentare generală</h2>
        <p>API-ul <?= v2_e(SITE_NAME) ?> îți permite să integrezi funcționalitățile platformei direct în aplicația ta. Toate request-urile trebuie trimise către:</p>
        <?= $oadCode('Base URL', v2_e($apiBase), false) ?>
        <p class="oad-small">API-ul folosește format JSON. Setează header-ul <code class="oad-ic">Content-Type: application/json</code>.</p>
      </section>

      <section class="oad-sec" id="authentication" aria-labelledby="oad-auth-h" tabindex="-1">
        <h2 class="oad-sec-h" id="oad-auth-h">Autentificare</h2>
        <p>Toate request-urile trebuie să includă cheia API în header-ul <code class="oad-ic">Authorization</code>:</p>
        <?= $oadCode('cURL', 'curl ' . v2_e($apiBase) . '/events \\
  -H <span class="string">"Authorization: Bearer YOUR_API_KEY"</span> \\
  -H <span class="string">"Content-Type: application/json"</span>') ?>
      </section>

      <section class="oad-sec" id="rate-limits" aria-labelledby="oad-rate-h" tabindex="-1">
        <h2 class="oad-sec-h" id="oad-rate-h">Limite rate</h2>
        <div class="oad-note">
          <?= v2_ic('warning-circle') ?>
          <div>
            <p><strong>Limită:</strong> 1000 request-uri / minut per cheie API.</p>
            <p class="oad-note-p">Header-ele <code class="oad-ic">X-RateLimit-Remaining</code> și <code class="oad-ic">X-RateLimit-Reset</code> sunt incluse în fiecare response.</p>
          </div>
        </div>
      </section>

      <section class="oad-sec" id="errors" aria-labelledby="oad-err-h" tabindex="-1">
        <h2 class="oad-sec-h" id="oad-err-h">Gestionare erori</h2>
        <p>API-ul returnează coduri HTTP standard:</p>
        <table class="oad-table oad-codes">
          <thead><tr><th scope="col">Cod</th><th scope="col">Descriere</th></tr></thead>
          <tbody>
            <tr><td><span class="oad-code-n is-ok">200</span></td><td>Request reușit</td></tr>
            <tr><td><span class="oad-code-n is-ok">201</span></td><td>Resursă creată cu succes</td></tr>
            <tr><td><span class="oad-code-n is-bad">400</span></td><td>Request invalid — parametri lipsă sau incorecți</td></tr>
            <tr><td><span class="oad-code-n is-bad">401</span></td><td>Neautorizat — cheie API invalidă</td></tr>
            <tr><td><span class="oad-code-n is-bad">404</span></td><td>Resursă negăsită</td></tr>
            <tr><td><span class="oad-code-n is-bad">429</span></td><td>Prea multe request-uri — limită rate depășită</td></tr>
            <tr><td><span class="oad-code-n is-bad">500</span></td><td>Eroare server</td></tr>
          </tbody>
        </table>
      </section>

      <section class="oad-sec" id="list-events" aria-labelledby="oad-events-h" tabindex="-1">
        <h2 class="oad-sec-h" id="oad-events-h">Activități</h2>
        <article class="oad-ep" aria-label="GET /events">
          <div class="oad-ep-head"><?= $oadMethod('GET') ?><code class="oad-path">/events</code></div>
          <div class="oad-ep-body">
            <p>Returnează lista tuturor activităților tale.</p>
            <h3 class="oad-h3">Parametri query</h3>
            <?= $oadParams([
                ['status', 'string', false, 'draft, published, cancelled'],
                ['limit', 'integer', false, 'Număr rezultate (default 20, max 100)'],
                ['offset', 'integer', false, 'Offset pentru paginare'],
            ]) ?>
            <h3 class="oad-h3">Exemplu response</h3>
            <?= $oadCode('JSON', '{
  <span class="string">"success"</span>: <span class="keyword">true</span>,
  <span class="string">"data"</span>: [
    {
      <span class="string">"id"</span>: <span class="string">"evt_abc123"</span>,
      <span class="string">"name"</span>: <span class="string">"Atelier de olărit"</span>,
      <span class="string">"starts_at"</span>: <span class="string">"2026-06-15T18:00:00Z"</span>,
      <span class="string">"venue"</span>: <span class="string">"Studio Creativ"</span>,
      <span class="string">"status"</span>: <span class="string">"published"</span>,
      <span class="string">"tickets_sold"</span>: <span class="number">42</span>,
      <span class="string">"tickets_available"</span>: <span class="number">8</span>
    }
  ],
  <span class="string">"meta"</span>: { <span class="string">"total"</span>: <span class="number">15</span>, <span class="string">"limit"</span>: <span class="number">20</span>, <span class="string">"offset"</span>: <span class="number">0</span> }
}') ?>
          </div>
        </article>
      </section>

      <section class="oad-sec" id="create-event" aria-label="POST /events" tabindex="-1">
        <article class="oad-ep" aria-label="POST /events">
          <div class="oad-ep-head"><?= $oadMethod('POST') ?><code class="oad-path">/events</code></div>
          <div class="oad-ep-body">
            <p>Creează o activitate nouă.</p>
            <?= $oadParams([
                ['name', 'string', true, 'Numele activității'],
                ['starts_at', 'datetime', true, 'Data și ora (ISO 8601)'],
                ['venue_id', 'string', true, 'ID-ul locației'],
                ['description', 'string', false, 'Descrierea activității'],
                ['category_id', 'string', false, 'ID-ul categoriei'],
            ]) ?>
          </div>
        </article>
      </section>

      <section class="oad-sec" id="validate-ticket" aria-labelledby="oad-validate-h" tabindex="-1">
        <h2 class="oad-sec-h" id="oad-validate-h">Validare bilet</h2>
        <article class="oad-ep" aria-label="POST /tickets/{ticket_id}/validate">
          <div class="oad-ep-head"><?= $oadMethod('POST') ?><code class="oad-path">/tickets/{ticket_id}/validate</code></div>
          <div class="oad-ep-body">
            <p>Validează un bilet la intrare (check-in). Biletul poate fi scanat o singură dată.</p>
            <?= $oadCode('JSON', '{
  <span class="string">"success"</span>: <span class="keyword">true</span>,
  <span class="string">"data"</span>: {
    <span class="string">"ticket_id"</span>: <span class="string">"tkt_xyz789"</span>,
    <span class="string">"status"</span>: <span class="string">"validated"</span>,
    <span class="string">"validated_at"</span>: <span class="string">"2026-06-15T17:45:00Z"</span>,
    <span class="string">"holder_name"</span>: <span class="string">"Ion Popescu"</span>,
    <span class="string">"ticket_type"</span>: <span class="string">"Acces general"</span>
  }
}', false) ?>
          </div>
        </article>
      </section>

      <section class="oad-sec" id="webhooks" aria-labelledby="oad-hooks-h" tabindex="-1">
        <h2 class="oad-sec-h" id="oad-hooks-h">Webhooks</h2>
        <p>Configurează un webhook pentru a primi notificări în timp real despre evenimente importante.</p>
        <form class="oad-hook" id="oad-hook" novalidate>
          <label class="oad-hook-l" for="oad-hook-url">URL-ul tău webhook</label>
          <div class="oad-hook-row">
            <input type="url" id="oad-hook-url" inputmode="url" autocomplete="url" spellcheck="false" maxlength="2048" placeholder="https://example.com/webhook" aria-describedby="oad-hook-msg">
            <button class="btn btn-primary" type="submit" id="oad-hook-go"><span data-label>Salvează</span></button>
          </div>
          <p class="oad-hook-msg" id="oad-hook-msg" role="status" aria-live="polite"></p>
        </form>
        <table class="oad-table oad-events">
          <thead><tr><th scope="col">Eveniment</th><th scope="col">Descriere</th></tr></thead>
          <tbody>
            <tr><td><code class="oad-ev">order.created</code></td><td>O comandă nouă a fost creată</td></tr>
            <tr><td><code class="oad-ev">order.completed</code></td><td>O comandă a fost finalizată și plătită</td></tr>
            <tr><td><code class="oad-ev">order.refunded</code></td><td>O comandă a fost returnată</td></tr>
            <tr><td><code class="oad-ev">ticket.validated</code></td><td>Un bilet a fost validat (check-in)</td></tr>
            <tr><td><code class="oad-ev">event.soldout</code></td><td>O activitate s-a vândut complet</td></tr>
          </tbody>
        </table>
      </section>

      <section class="oad-help" aria-labelledby="oad-help-h">
        <h2 class="oad-help-h" id="oad-help-h">Ai nevoie de ajutor?</h2>
        <p>Contactează echipa noastră de suport pentru întrebări despre API.</p>
        <a class="oad-mail" href="mailto:<?= v2_e(SUPPORT_EMAIL) ?>"><?= v2_ic('envelope-simple') ?><?= v2_e(SUPPORT_EMAIL) ?></a>
      </section>
    </div>
  </div>

  <dialog class="oad-dialog" id="oad-regen-d" aria-labelledby="oad-regen-h" aria-describedby="oad-regen-p">
    <div class="oad-d-inner">
      <h2 class="oad-d-h" id="oad-regen-h">Regenerezi cheia API?</h2>
      <p class="oad-d-p" id="oad-regen-p">Ești sigur că vrei să regenerezi cheia API? Toate integrările existente vor înceta să funcționeze.</p>
      <p class="oad-d-err" id="oad-regen-err" role="alert" hidden></p>
      <div class="oad-d-act">
        <button class="btn btn-ghost" type="button" data-close>Renunță</button>
        <button class="btn oad-danger" type="button" id="oad-regen-go"><span data-label>Regenerează cheia</span></button>
      </div>
    </div>
  </dialog>
</div>
<?php
v2_org_end();
include __DIR__ . '/../includes/v2/foot.php';
