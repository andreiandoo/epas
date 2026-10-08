<?php
/**
 * Organizer team: /organizator/echipa (team.php), v2 design.
 *
 * Inside the v2 organizer shell. Who works in the account and with what access: the figures (members, active, pending
 * invitations, administrators), the member list with search, adding a member (name, e-mail, a password with a
 * generator, role, permissions, which activities they see, the welcome e-mail), editing the role, permissions and
 * activities, a new password for a member, activating a pending invitation with a password, resending invitations,
 * removing a member, and what each role can do. org-team.js reads /organizer/team and /organizer/events through the proxy.
 *
 * Fixed on the way:
 * - adding a member always failed: core creates members directly now and requires a password, which the form never sent;
 * - a pending member could only get the invitation again: they can be activated with a password, and active members can
 *   get a new password (the proxy gets organizer.team.activate and organizer.team.reset-password);
 * - the activities core lets each member see were neither shown nor editable;
 * - errors came as browser alerts, success messages without diacritics; the search matched only the start of the data.
 */

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/api.php';
require_once __DIR__ . '/../includes/nav-helpers.php';
require_once __DIR__ . '/../includes/v2/helpers.php';
require_once __DIR__ . '/../includes/v2/organizer.php';

$pageTitleRaw = v2_t('Team') . ' · ' . SITE_NAME;
$pageDescription = v2_t('The team members of an operator on Viaqui: roles, permissions and the experiences they have access to.');
$canonicalUrl = SITE_URL . '/organizator/echipa';
$noindex = true;
$skipPageCache = true;

$v2Styles = ['organizer.css', 'org-team.css'];
$v2Scripts = ['organizer.js', 'org-team.js'];
$v2LegacyScripts = ['assets/js/config.js', 'assets/js/utils.js', 'assets/js/api.js', 'assets/js/auth.js'];
$v2HeadExtra = v2_account_client_config('organizer');

$otStat = function (string $key, string $label, string $icon) {
    return '<article class="ot-stat" id="ot-st-' . $key . '"><span class="ot-stat-ic">' . v2_ic($icon) . '</span><div><p class="ot-stat-k">' . $label . '</p><p class="ot-stat-v" id="ot-s-' . $key . '"><span class="org-skel ot-sk"></span></p></div></article>';
};
$otX = '<button class="ot-x" type="button" data-close aria-label="' . v2_te('Close') . '">' . v2_ic('x') . '</button>';
$otPassField = function (string $id, string $label, string $help) {
    return '<label class="ot-f"><span class="ot-f-l">' . $label . '</span>'
        . '<span class="ot-pass"><input id="' . $id . '" type="password" autocomplete="new-password" maxlength="100" spellcheck="false" aria-describedby="' . $id . '-help ' . $id . '-err">'
        . '<button class="ot-eye" type="button" data-eye="' . $id . '" aria-label="' . v2_te('Show password') . '" aria-pressed="false">' . v2_ic('eye') . '</button></span>'
        . '<span class="ot-pass-tools"><button class="ot-linkbtn" type="button" data-gen="' . $id . '">' . v2_ic('lightning') . v2_te('Generate a password') . '</button><button class="ot-linkbtn" type="button" data-copy="' . $id . '">' . v2_ic('copy') . v2_te('Copy') . '</button></span>'
        . '<span class="ot-help" id="' . $id . '-help">' . $help . '</span>'
        . '<span class="ot-err" id="' . $id . '-err" hidden></span></label>';
};
$otPerms = ['events' => v2_t('Experiences'), 'orders' => v2_t('Orders'), 'reports' => v2_t('Reports'), 'team' => v2_t('Team'), 'checkin' => v2_t('Check-in (mobile app)')];

include __DIR__ . '/../includes/v2/head.php';
v2_org_start('team');
?>
<div class="ot" id="ot">
  <header class="ot-head">
    <a class="ot-back" href="/organizator/setari"><?= v2_ic('arrow-left') ?><?= v2_te('Back to settings') ?></a>
    <div class="ot-head-row">
      <div class="ot-head-t">
        <p class="org-k"><?= v2_te('Settings') ?></p>
        <h1 class="ot-h"><?= v2_te('Team') ?></h1>
        <p class="ot-lead"><?= v2_te('The colleagues who work in the account: their role, what they can do and which experiences they see.') ?></p>
      </div>
      <div class="ot-actions"><button class="btn btn-primary" type="button" id="ot-add" disabled><?= v2_ic('user-plus') ?><?= v2_te('Add a member') ?></button></div>
    </div>
  </header>

  <section class="ot-stats" aria-label="<?= v2_te('At a glance') ?>">
    <?= $otStat('total', v2_te('Members'), 'users-three') ?>
    <?= $otStat('active', v2_te('Active'), 'check-circle') ?>
    <?= $otStat('pending', v2_te('Pending invitations'), 'clock') ?>
    <?= $otStat('admins', v2_te('Administrators'), 'lock-simple') ?>
  </section>

  <div class="ot-alert" id="ot-pending" role="status" hidden>
    <span class="ot-alert-ic"><?= v2_ic('envelope-simple') ?></span>
    <div><b id="ot-pending-t"></b><p><?= v2_te('Invitations sent by email expire after 7 days. You can resend them or activate the member directly, with a password.') ?></p></div>
    <button class="btn btn-ghost ot-sm" type="button" id="ot-resend-all"><span data-label><?= v2_te('Resend invitations') ?></span></button>
  </div>

  <div class="org-empty is-error" id="ot-load-err" role="alert" hidden>
    <span class="org-empty-ic"><?= v2_ic('warning-circle') ?></span>
    <b><?= v2_te('We could not load the team') ?></b>
    <p><?= v2_te('Check your connection and try again.') ?></p>
    <div class="ot-empty-cta"><button class="btn btn-primary" type="button" id="ot-retry"><?= v2_te('Try again') ?></button></div>
  </div>

  <section class="org-panel ot-panel" id="ot-panel" aria-labelledby="ot-list-h">
    <div class="org-panel-head">
      <div><p class="org-k"><?= v2_te('Members') ?></p><h2 class="org-panel-h" id="ot-list-h"><?= v2_te('Team members') ?></h2><p class="org-panel-p" id="ot-list-p"></p></div>
      <label class="ot-search"><?= v2_ic('magnifying-glass') ?><input id="ot-q" type="search" autocomplete="off" spellcheck="false" placeholder="<?= v2_te('Search by name or email') ?>" aria-label="<?= v2_te('Search members') ?>" aria-controls="ot-list"></label>
    </div>
    <ul class="ot-list" id="ot-list" aria-live="polite"><li><span class="org-skel ot-sk-row"></span></li></ul>
  </section>

  <section class="org-panel" aria-labelledby="ot-roles-h">
    <div class="org-panel-head"><div><p class="org-k"><?= v2_te('Roles') ?></p><h2 class="org-panel-h" id="ot-roles-h"><?= v2_te('About roles and permissions') ?></h2><p class="org-panel-p"><?= v2_t('Members sign in with their own email and password, <a href="{url}">on Viaqui</a> and in the mobile app.', ['url' => '/login?ca=venue']) ?></p></div></div>
    <div class="ot-roles">
      <article class="ot-role is-owner"><span class="ot-role-dot" aria-hidden="true"></span><b><?= v2_te('Owner') ?></b><p><?= v2_te('Full access to every feature, including settings and deleting the account.') ?></p></article>
      <article class="ot-role is-admin"><span class="ot-role-dot" aria-hidden="true"></span><b><?= v2_te('Administrator') ?></b><p><?= v2_te('Manages all experiences, orders, reports and team members.') ?></p></article>
      <article class="ot-role is-manager"><span class="ot-role-dot" aria-hidden="true"></span><b><?= v2_te('Manager') ?></b><p><?= v2_te('Manages the experiences they have access to and processes orders, within the permissions given.') ?></p></article>
      <article class="ot-role is-staff"><span class="ot-role-dot" aria-hidden="true"></span><b><?= v2_te('Staff') ?></b><p><?= v2_te('Checks guests in at the entrance, from the mobile app, for the experiences they have access to.') ?></p></article>
    </div>
  </section>

  <dialog class="ot-dialog is-wide" id="ot-member-d" aria-labelledby="ot-member-h">
    <form class="ot-d-inner" id="ot-member-form" novalidate>
      <div class="ot-d-head"><div><h2 class="ot-d-h" id="ot-member-h"><?= v2_te('New member') ?></h2><p class="ot-d-p" id="ot-member-p"></p></div><?= $otX ?></div>
      <div class="ot-grid" id="ot-ident">
        <label class="ot-f"><span class="ot-f-l"><?= v2_t('Name <small>(optional)</small>') ?></span><input id="ot-name" type="text" maxlength="255" autocomplete="off" placeholder="<?= v2_te('e.g. Ana Silva') ?>" aria-describedby="ot-name-err"><span class="ot-err" id="ot-name-err" hidden></span></label>
        <label class="ot-f"><span class="ot-f-l"><?= v2_te('Email') ?></span><input id="ot-email" type="email" maxlength="255" autocomplete="off" spellcheck="false" placeholder="<?= v2_te('e.g. ana@your-company.com') ?>" aria-describedby="ot-email-err"><span class="ot-err" id="ot-email-err" hidden></span></label>
        <div class="ot-wide" id="ot-pass-f"><?= $otPassField('ot-pass', v2_te('Password'), v2_te('At least 8 characters. They see it only once, in the welcome email or from you.')) ?></div>
      </div>
      <label class="ot-f">
        <span class="ot-f-l"><?= v2_te('Role') ?></span>
        <span class="ot-select"><select id="ot-role" aria-describedby="ot-role-help"><option value="staff"><?= v2_te('Staff') ?></option><option value="manager"><?= v2_te('Manager') ?></option><option value="admin"><?= v2_te('Administrator') ?></option></select><?= v2_ic('caret-down') ?></span>
        <span class="ot-help" id="ot-role-help"></span>
      </label>
      <fieldset class="ot-fs" id="ot-perms">
        <legend><?= v2_te('Permissions') ?></legend>
        <div class="ot-checks">
          <?php foreach ($otPerms as $otKey => $otLabel): ?><label class="ot-check"><input type="checkbox" value="<?= $otKey ?>" data-perm><span><?= v2_e($otLabel) ?></span></label><?php endforeach; ?>
        </div>
        <span class="ot-err" id="ot-perms-err" hidden></span>
      </fieldset>
      <fieldset class="ot-fs" id="ot-scope">
        <legend><?= v2_te('Which experiences they see') ?></legend>
        <div class="ot-radios">
          <label class="ot-check"><input type="radio" name="ot-scope" value="all" checked><span><b><?= v2_te('All experiences') ?></b><small><?= v2_te('Including those added from now on.') ?></small></span></label>
          <label class="ot-check"><input type="radio" name="ot-scope" value="some"><span><b><?= v2_te('Only the chosen experiences') ?></b><small><?= v2_te('They see nothing of the others.') ?></small></span></label>
        </div>
        <div class="ot-some" id="ot-some" hidden>
          <span class="ot-psearch" id="ot-psearch" hidden><?= v2_ic('magnifying-glass') ?><input id="ot-pq" type="search" autocomplete="off" placeholder="<?= v2_te('Search for an experience') ?>" aria-label="<?= v2_te('Search for an experience') ?>"></span>
          <ul class="ot-picks" id="ot-picks"><li class="ot-picks-msg"><?= v2_te('Loading experiences…') ?></li></ul>
          <span class="ot-help" id="ot-picks-n" aria-live="polite"></span>
          <span class="ot-err" id="ot-picks-err" hidden></span>
        </div>
      </fieldset>
      <label class="ot-check" id="ot-welcome-f"><input type="checkbox" id="ot-welcome" checked><span><b><?= v2_te('Send them the welcome email') ?></b><small><?= v2_te('With the email, the password and the link to the mobile app. Without the email, you pass the details on yourself.') ?></small></span></label>
      <div class="ot-form-err" id="ot-member-err" role="alert" hidden></div>
      <div class="ot-d-act"><button class="btn btn-ghost" type="button" data-close><?= v2_te('Cancel') ?></button><button class="btn btn-primary" type="submit" id="ot-member-go"><span data-label><?= v2_te('Add member') ?></span></button></div>
    </form>
  </dialog>

  <dialog class="ot-dialog" id="ot-pass-d" aria-labelledby="ot-pass-h">
    <form class="ot-d-inner" id="ot-pass-form" novalidate>
      <div class="ot-d-head"><div><h2 class="ot-d-h" id="ot-pass-h"><?= v2_te('New password') ?></h2><p class="ot-d-p" id="ot-pass-p"></p></div><?= $otX ?></div>
      <?= $otPassField('ot-pw2', v2_te('New password'), v2_te('At least 8 characters. Pass it on to the member: we do not send them an email.')) ?>
      <div class="ot-form-err" id="ot-pass-err" role="alert" hidden></div>
      <div class="ot-d-act"><button class="btn btn-ghost" type="button" data-close><?= v2_te('Cancel') ?></button><button class="btn btn-primary" type="submit" id="ot-pass-go"><span data-label><?= v2_te('Save password') ?></span></button></div>
    </form>
  </dialog>

  <dialog class="ot-dialog" id="ot-cred-d" aria-labelledby="ot-cred-h">
    <div class="ot-d-inner">
      <div class="ot-d-head"><div><h2 class="ot-d-h" id="ot-cred-h"><?= v2_te('Sign-in details') ?></h2><p class="ot-d-p" id="ot-cred-p"></p></div><?= $otX ?></div>
      <dl class="ot-cred">
        <div><dt><?= v2_te('Email') ?></dt><dd id="ot-cred-email"></dd></div>
        <div><dt><?= v2_te('Password') ?></dt><dd id="ot-cred-pass"></dd></div>
        <div><dt><?= v2_te('Sign in') ?></dt><dd id="ot-cred-url"></dd></div>
      </dl>
      <p class="ot-help"><?= v2_te('The password is not shown anywhere again once you close this window.') ?></p>
      <div class="ot-d-act"><button class="btn btn-ghost" type="button" id="ot-cred-copy"><?= v2_ic('copy') ?><?= v2_te('Copy details') ?></button><button class="btn btn-primary" type="button" data-close><?= v2_te('Done') ?></button></div>
    </div>
  </dialog>

  <dialog class="ot-dialog is-small" id="ot-del-d" aria-labelledby="ot-del-h" aria-describedby="ot-del-p">
    <div class="ot-d-inner">
      <h2 class="ot-d-h" id="ot-del-h"><?= v2_te('Remove the member?') ?></h2>
      <p class="ot-d-p" id="ot-del-p"></p>
      <div class="ot-form-err" id="ot-del-err" role="alert" hidden></div>
      <div class="ot-d-act"><button class="btn btn-ghost" type="button" data-close><?= v2_te('Cancel') ?></button><button class="btn ot-danger" type="button" id="ot-del-go"><span data-label><?= v2_te('Remove') ?></span></button></div>
    </div>
  </dialog>
</div>
<?php
v2_org_end();
include __DIR__ . '/../includes/v2/foot.php';
