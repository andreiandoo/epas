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

$pageTitleRaw = v2_t('API documentation') . ' · ' . SITE_NAME;
$pageDescription = v2_t('The Viaqui API documentation for operators: authentication, limits, errors, endpoints and webhooks.');
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
        . ($copy ? '<button class="oad-copy" type="button" data-copy aria-label="' . v2_te('Copy the {label} code', ['label' => $label]) . '">' . v2_ic('copy') . '<span data-label>' . v2_te('Copy') . '</span></button>' : '')
        . '</div><pre tabindex="0"><code>' . $code . '</code></pre></div>';
};
/** A parameters table: rows of [name, type, required, description]. The roles keep it a table when phones lay the rows out as cards. */
$oadParams = function (array $rows) {
    $out = '<table class="oad-table oad-params" role="table"><thead role="rowgroup"><tr role="row"><th scope="col" role="columnheader">' . v2_te('Parameter') . '</th><th scope="col" role="columnheader">' . v2_te('Type') . '</th>'
        . '<th scope="col" role="columnheader">' . v2_te('Required') . '</th><th scope="col" role="columnheader">' . v2_te('Description') . '</th></tr></thead><tbody role="rowgroup">';
    foreach ($rows as [$name, $type, $req, $desc]) {
        $out .= '<tr role="row"><td role="cell"><code class="oad-param">' . $name . '</code></td><td role="cell"><span class="oad-type">' . $type . '</span></td>'
            . '<td role="cell">' . ($req ? '<span class="oad-req is-yes">' . v2_te('Yes') . '</span>' : '<span class="oad-req">' . v2_te('No') . '</span>') . '</td><td class="oad-desc" role="cell">' . $desc . '</td></tr>';
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
    <p class="org-k"><?= v2_te('For developers') ?></p>
    <h1 class="oad-h"><?= v2_te('API documentation') ?></h1>
    <p class="oad-lead"><?= v2_te('Integrate {site} into your app with our RESTful API.', ['site' => SITE_NAME]) ?></p>
  </header>

  <!-- API key -->
  <section class="oad-key" aria-labelledby="oad-key-h">
    <div class="oad-key-top">
      <h2 class="oad-key-h" id="oad-key-h"><?= v2_ic('lock-simple') ?><?= v2_te('Your API key') ?></h2>
      <button class="oad-key-btn" type="button" id="oad-regen"><?= v2_ic('arrow-counter-clockwise') ?><span data-label><?= v2_te('Regenerate') ?></span></button>
    </div>
    <div class="oad-key-row">
      <code class="oad-key-v" id="oad-key" aria-live="polite"><?= v2_te('Loading…') ?></code>
      <button class="oad-key-copy" type="button" id="oad-key-copy" disabled><?= v2_ic('copy') ?><span class="sr"><?= v2_te('Copy the API key') ?></span></button>
    </div>
    <p class="oad-key-note"><?= v2_te('Never share your API key. If it has been exposed, regenerate it straight away.') ?></p>
  </section>

  <div class="oad-grid">
    <nav class="oad-toc" aria-label="<?= v2_te('Documentation contents') ?>">
      <div class="oad-toc-g">
        <p class="oad-toc-k" id="oad-toc-1"><?= v2_te('Introduction') ?></p>
        <ul aria-labelledby="oad-toc-1">
          <li><a href="#overview"><?= v2_te('Overview') ?></a></li>
          <li><a href="#authentication"><?= v2_te('Authentication') ?></a></li>
          <li><a href="#rate-limits"><?= v2_te('Rate limits') ?></a></li>
          <li><a href="#errors"><?= v2_te('Error handling') ?></a></li>
        </ul>
      </div>
      <div class="oad-toc-g">
        <p class="oad-toc-k" id="oad-toc-2"><?= v2_te('Experiences') ?></p>
        <ul aria-labelledby="oad-toc-2">
          <li><a href="#list-events"><?= $oadMethod('GET') ?><?= v2_te('List') ?></a></li>
          <li><a href="#create-event"><?= $oadMethod('POST') ?><?= v2_te('Create') ?></a></li>
        </ul>
      </div>
      <div class="oad-toc-g">
        <p class="oad-toc-k" id="oad-toc-3"><?= v2_te('Tickets') ?></p>
        <ul aria-labelledby="oad-toc-3"><li><a href="#validate-ticket"><?= $oadMethod('POST') ?><?= v2_te('Validate') ?></a></li></ul>
      </div>
      <div class="oad-toc-g">
        <p class="oad-toc-k" id="oad-toc-4"><?= v2_te('Webhooks') ?></p>
        <ul aria-labelledby="oad-toc-4"><li><a href="#webhooks"><?= v2_te('Setup') ?></a></li></ul>
      </div>
    </nav>

    <div class="oad-body">
      <section class="oad-sec" id="overview" aria-labelledby="oad-overview-h" tabindex="-1">
        <h2 class="oad-sec-h" id="oad-overview-h"><?= v2_te('Overview') ?></h2>
        <p><?= v2_te('The {site} API lets you build the features of the platform straight into your app. All requests must be sent to:', ['site' => SITE_NAME]) ?></p>
        <?= $oadCode('Base URL', v2_e($apiBase), false) ?>
        <p class="oad-small"><?= v2_t('The API uses JSON. Set the {header} header.', ['header' => '<code class="oad-ic">Content-Type: application/json</code>']) ?></p>
      </section>

      <section class="oad-sec" id="authentication" aria-labelledby="oad-auth-h" tabindex="-1">
        <h2 class="oad-sec-h" id="oad-auth-h"><?= v2_te('Authentication') ?></h2>
        <p><?= v2_t('All requests must include the API key in the {header} header:', ['header' => '<code class="oad-ic">Authorization</code>']) ?></p>
        <?= $oadCode('cURL', 'curl ' . v2_e($apiBase) . '/events \\
  -H <span class="string">"Authorization: Bearer YOUR_API_KEY"</span> \\
  -H <span class="string">"Content-Type: application/json"</span>') ?>
      </section>

      <section class="oad-sec" id="rate-limits" aria-labelledby="oad-rate-h" tabindex="-1">
        <h2 class="oad-sec-h" id="oad-rate-h"><?= v2_te('Rate limits') ?></h2>
        <div class="oad-note">
          <?= v2_ic('warning-circle') ?>
          <div>
            <p><?= v2_t('<strong>Limit:</strong> 1000 requests / minute per API key.') ?></p>
            <p class="oad-note-p"><?= v2_t('The {remaining} and {reset} headers are included in every response.', ['remaining' => '<code class="oad-ic">X-RateLimit-Remaining</code>', 'reset' => '<code class="oad-ic">X-RateLimit-Reset</code>']) ?></p>
          </div>
        </div>
      </section>

      <section class="oad-sec" id="errors" aria-labelledby="oad-err-h" tabindex="-1">
        <h2 class="oad-sec-h" id="oad-err-h"><?= v2_te('Error handling') ?></h2>
        <p><?= v2_te('The API returns standard HTTP codes:') ?></p>
        <table class="oad-table oad-codes">
          <thead><tr><th scope="col"><?= v2_te('Code') ?></th><th scope="col"><?= v2_te('Description') ?></th></tr></thead>
          <tbody>
            <tr><td><span class="oad-code-n is-ok">200</span></td><td><?= v2_te('Request succeeded') ?></td></tr>
            <tr><td><span class="oad-code-n is-ok">201</span></td><td><?= v2_te('Resource created') ?></td></tr>
            <tr><td><span class="oad-code-n is-bad">400</span></td><td><?= v2_te('Invalid request: missing or incorrect parameters') ?></td></tr>
            <tr><td><span class="oad-code-n is-bad">401</span></td><td><?= v2_te('Unauthorised: invalid API key') ?></td></tr>
            <tr><td><span class="oad-code-n is-bad">404</span></td><td><?= v2_te('Resource not found') ?></td></tr>
            <tr><td><span class="oad-code-n is-bad">429</span></td><td><?= v2_te('Too many requests: rate limit exceeded') ?></td></tr>
            <tr><td><span class="oad-code-n is-bad">500</span></td><td><?= v2_te('Server error') ?></td></tr>
          </tbody>
        </table>
      </section>

      <section class="oad-sec" id="list-events" aria-labelledby="oad-events-h" tabindex="-1">
        <h2 class="oad-sec-h" id="oad-events-h"><?= v2_te('Experiences') ?></h2>
        <article class="oad-ep" aria-label="GET /events">
          <div class="oad-ep-head"><?= $oadMethod('GET') ?><code class="oad-path">/events</code></div>
          <div class="oad-ep-body">
            <p><?= v2_te('Returns the list of all your experiences.') ?></p>
            <h3 class="oad-h3"><?= v2_te('Query parameters') ?></h3>
            <?= $oadParams([
                ['status', 'string', false, 'draft, published, cancelled'],
                ['limit', 'integer', false, v2_te('Number of results (default 20, max 100)')],
                ['offset', 'integer', false, v2_te('Offset for paging')],
            ]) ?>
            <h3 class="oad-h3"><?= v2_te('Example response') ?></h3>
            <?= $oadCode('JSON', '{
  <span class="string">"success"</span>: <span class="keyword">true</span>,
  <span class="string">"data"</span>: [
    {
      <span class="string">"id"</span>: <span class="string">"evt_abc123"</span>,
      <span class="string">"name"</span>: <span class="string">"Pottery workshop"</span>,
      <span class="string">"starts_at"</span>: <span class="string">"2026-06-15T18:00:00Z"</span>,
      <span class="string">"venue"</span>: <span class="string">"Creative Studio"</span>,
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
            <p><?= v2_te('Creates a new experience.') ?></p>
            <?= $oadParams([
                ['name', 'string', true, v2_te('The name of the experience')],
                ['starts_at', 'datetime', true, v2_te('The date and time (ISO 8601)')],
                ['venue_id', 'string', true, v2_te('The ID of the venue')],
                ['description', 'string', false, v2_te('The description of the experience')],
                ['category_id', 'string', false, v2_te('The ID of the category')],
            ]) ?>
          </div>
        </article>
      </section>

      <section class="oad-sec" id="validate-ticket" aria-labelledby="oad-validate-h" tabindex="-1">
        <h2 class="oad-sec-h" id="oad-validate-h"><?= v2_te('Ticket validation') ?></h2>
        <article class="oad-ep" aria-label="POST /tickets/{ticket_id}/validate">
          <div class="oad-ep-head"><?= $oadMethod('POST') ?><code class="oad-path">/tickets/{ticket_id}/validate</code></div>
          <div class="oad-ep-body">
            <p><?= v2_te('Validates a ticket at the entrance (check-in). A ticket can be scanned only once.') ?></p>
            <?= $oadCode('JSON', '{
  <span class="string">"success"</span>: <span class="keyword">true</span>,
  <span class="string">"data"</span>: {
    <span class="string">"ticket_id"</span>: <span class="string">"tkt_xyz789"</span>,
    <span class="string">"status"</span>: <span class="string">"validated"</span>,
    <span class="string">"validated_at"</span>: <span class="string">"2026-06-15T17:45:00Z"</span>,
    <span class="string">"holder_name"</span>: <span class="string">"Ana Silva"</span>,
    <span class="string">"ticket_type"</span>: <span class="string">"General access"</span>
  }
}', false) ?>
          </div>
        </article>
      </section>

      <section class="oad-sec" id="webhooks" aria-labelledby="oad-hooks-h" tabindex="-1">
        <h2 class="oad-sec-h" id="oad-hooks-h"><?= v2_te('Webhooks') ?></h2>
        <p><?= v2_te('Set up a webhook to receive real-time notifications about important events.') ?></p>
        <form class="oad-hook" id="oad-hook" novalidate>
          <label class="oad-hook-l" for="oad-hook-url"><?= v2_te('Your webhook URL') ?></label>
          <div class="oad-hook-row">
            <input type="url" id="oad-hook-url" inputmode="url" autocomplete="url" spellcheck="false" maxlength="2048" placeholder="https://example.com/webhook" aria-describedby="oad-hook-msg">
            <button class="btn btn-primary" type="submit" id="oad-hook-go"><span data-label><?= v2_te('Save') ?></span></button>
          </div>
          <p class="oad-hook-msg" id="oad-hook-msg" role="status" aria-live="polite"></p>
        </form>
        <table class="oad-table oad-events">
          <thead><tr><th scope="col"><?= v2_te('Event') ?></th><th scope="col"><?= v2_te('Description') ?></th></tr></thead>
          <tbody>
            <tr><td><code class="oad-ev">order.created</code></td><td><?= v2_te('A new order was created') ?></td></tr>
            <tr><td><code class="oad-ev">order.completed</code></td><td><?= v2_te('An order was completed and paid') ?></td></tr>
            <tr><td><code class="oad-ev">order.refunded</code></td><td><?= v2_te('An order was refunded') ?></td></tr>
            <tr><td><code class="oad-ev">ticket.validated</code></td><td><?= v2_te('A ticket was validated (check-in)') ?></td></tr>
            <tr><td><code class="oad-ev">event.soldout</code></td><td><?= v2_te('An experience sold out') ?></td></tr>
          </tbody>
        </table>
      </section>

      <section class="oad-help" aria-labelledby="oad-help-h">
        <h2 class="oad-help-h" id="oad-help-h"><?= v2_te('Need help?') ?></h2>
        <p><?= v2_te('Contact our support team with questions about the API.') ?></p>
        <a class="oad-mail" href="mailto:<?= v2_e(SUPPORT_EMAIL) ?>"><?= v2_ic('envelope-simple') ?><?= v2_e(SUPPORT_EMAIL) ?></a>
      </section>
    </div>
  </div>

  <dialog class="oad-dialog" id="oad-regen-d" aria-labelledby="oad-regen-h" aria-describedby="oad-regen-p">
    <div class="oad-d-inner">
      <h2 class="oad-d-h" id="oad-regen-h"><?= v2_te('Regenerate the API key?') ?></h2>
      <p class="oad-d-p" id="oad-regen-p"><?= v2_te('Are you sure you want to regenerate the API key? All existing integrations will stop working.') ?></p>
      <p class="oad-d-err" id="oad-regen-err" role="alert" hidden></p>
      <div class="oad-d-act">
        <button class="btn btn-ghost" type="button" data-close><?= v2_te('Cancel') ?></button>
        <button class="btn oad-danger" type="button" id="oad-regen-go"><span data-label><?= v2_te('Regenerate key') ?></span></button>
      </div>
    </div>
  </dialog>
</div>
<?php
v2_org_end();
include __DIR__ . '/../includes/v2/foot.php';
