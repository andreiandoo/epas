<?php
/**
 * Share link view: /view/{code} (view.php?code=), v2 design. Public, no account needed.
 *
 * What an organizer's monitoring link shows a partner or a sponsor: the activities in the link with their date and place,
 * the tickets sold out of the tickets on sale and the fill rate, the ticket types and, when the organizer turned them on,
 * the takings and the participant list. Protected links ask for the password; a stopped, missing or malformed link says
 * so. The figures refresh every 30 seconds while the page is in view (paused in a background tab, slower when the server
 * asks for it). share-view.js reads /share/{code}/data through the proxy (share-link.data).
 *
 * The page did not exist on viaqui.com: every link created from /organizator/setari led to a 404.
 */

require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/api.php';
require_once __DIR__ . '/includes/nav-helpers.php';
require_once __DIR__ . '/includes/v2/helpers.php';
require_once __DIR__ . '/includes/v2/nav.php';

$svCode = isset($_GET['code']) && is_string($_GET['code']) && preg_match('/^[A-Za-z0-9]{6,20}$/', $_GET['code']) ? $_GET['code'] : '';
if ($svCode === '') {
    http_response_code(404);
}

$pageTitleRaw = ($svCode === '' ? v2_t('Link not valid') : v2_t('Sales monitor')) . ' · ' . SITE_NAME;
$pageDescription = v2_t('Sales of the tracked activities, updated in real time.');
$canonicalUrl = SITE_URL . '/view' . ($svCode !== '' ? '/' . $svCode : '');
$noindex = true;
$skipPageCache = true;
$v2Styles = ['share-view.css'];
$v2Scripts = $svCode !== '' ? ['share-view.js'] : [];

include __DIR__ . '/includes/v2/head.php';
include __DIR__ . '/includes/v2/header.php';
?>
<main id="main" class="sv" data-code="<?= v2_e($svCode) ?>">
  <section class="sv-hero" aria-labelledby="sv-title">
    <div class="wrap sv-hero-in">
      <p class="kicker"><?= v2_te('Sales monitor') ?></p>
      <h1 id="sv-title"><?= v2_te('Sales in real time') ?></h1>
      <p class="sv-sub" id="sv-sub"><?= v2_te('The activities shared by the organiser, with tickets sold updated automatically.') ?></p>
      <p class="sv-live" id="sv-live" hidden><i aria-hidden="true"></i><span id="sv-live-t"></span></p>
    </div>
  </section>

  <div class="wrap sv-body">
    <?php if ($svCode === ''): ?>
    <div class="sv-state is-bad" role="alert">
      <span class="sv-state-ic"><?= v2_ic('x') ?></span>
      <h2><?= v2_te('This link is not valid') ?></h2>
      <p><?= v2_te('Check the link you received or ask the organiser for a new one.') ?></p>
      <a class="btn btn-ghost" href="/"><?= v2_ic('arrow-left') ?><?= v2_te('Back to the homepage') ?></a>
    </div>
    <?php else: ?>
    <div class="sv-state" id="sv-loading" aria-live="polite"><span class="sv-spin" aria-hidden="true"></span><p><?= v2_te('Loading the figures…') ?></p></div>

    <form class="sv-state sv-pass" id="sv-pass" novalidate hidden>
      <span class="sv-state-ic"><?= v2_ic('lock-simple') ?></span>
      <h2><?= v2_te('This link is password protected') ?></h2>
      <p><?= v2_te('Enter the password the organiser gave you to see the figures.') ?></p>
      <label class="sv-f">
        <span class="sr"><?= v2_te('Password') ?></span>
        <span class="sv-pass-in"><input id="sv-pw" type="password" autocomplete="off" maxlength="100" aria-describedby="sv-pw-err"><button class="sv-eye" type="button" id="sv-eye" aria-pressed="false"><?= v2_te('Show') ?></button></span>
      </label>
      <p class="sv-err" id="sv-pw-err" role="alert" hidden></p>
      <button class="btn btn-primary" type="submit" id="sv-pw-go"><span data-label><?= v2_te('See the figures') ?></span></button>
    </form>

    <div class="sv-state" id="sv-error" role="alert" hidden></div>

    <div class="sv-content" id="sv-content" hidden>
      <section class="sv-stats" id="sv-stats" aria-label="<?= v2_te('At a glance') ?>"></section>
      <p class="sv-note" id="sv-note" aria-live="polite" hidden></p>
      <ol class="sv-events" id="sv-events"></ol>
      <p class="sv-foot"><?= v2_te('The figures come straight from the {site} ticketing system. Tickets sold count paid orders only.', ['site' => SITE_NAME]) ?></p>
    </div>
    <?php endif; ?>
  </div>
</main>
<?php include __DIR__ . '/includes/v2/footer.php'; ?>
