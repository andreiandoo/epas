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

$pageTitleRaw = 'Echipa — ' . SITE_NAME;
$pageDescription = 'Membrii echipei unui organizator pe bilete.online: roluri, permisiuni și activitățile la care au acces.';
$canonicalUrl = SITE_URL . '/organizator/echipa';
$noindex = true;
$skipPageCache = true;

$v2Styles = ['organizer.css', 'org-team.css'];
$v2Scripts = ['organizer.js', 'org-team.js'];
$v2LegacyScripts = ['assets/js/config.js', 'assets/js/utils.js', 'assets/js/api.js', 'assets/js/auth.js'];
$v2HeadExtra = v2_account_client_config();

$otStat = function (string $key, string $label, string $icon) {
    return '<article class="ot-stat" id="ot-st-' . $key . '"><span class="ot-stat-ic">' . v2_ic($icon) . '</span><div><p class="ot-stat-k">' . $label . '</p><p class="ot-stat-v" id="ot-s-' . $key . '"><span class="org-skel ot-sk"></span></p></div></article>';
};
$otX = '<button class="ot-x" type="button" data-close aria-label="Închide">' . v2_ic('x') . '</button>';
$otPassField = function (string $id, string $label, string $help) {
    return '<label class="ot-f"><span class="ot-f-l">' . $label . '</span>'
        . '<span class="ot-pass"><input id="' . $id . '" type="password" autocomplete="new-password" maxlength="100" spellcheck="false" aria-describedby="' . $id . '-help ' . $id . '-err">'
        . '<button class="ot-eye" type="button" data-eye="' . $id . '" aria-label="Arată parola" aria-pressed="false">' . v2_ic('eye') . '</button></span>'
        . '<span class="ot-pass-tools"><button class="ot-linkbtn" type="button" data-gen="' . $id . '">' . v2_ic('lightning') . 'Generează o parolă</button><button class="ot-linkbtn" type="button" data-copy="' . $id . '">' . v2_ic('copy') . 'Copiază</button></span>'
        . '<span class="ot-help" id="' . $id . '-help">' . $help . '</span>'
        . '<span class="ot-err" id="' . $id . '-err" hidden></span></label>';
};
$otPerms = ['events' => 'Activități', 'orders' => 'Comenzi', 'reports' => 'Rapoarte', 'team' => 'Echipă', 'checkin' => 'Check-in (aplicația mobilă)'];

include __DIR__ . '/../includes/v2/head.php';
v2_org_start('settings');
?>
<div class="ot" id="ot">
  <header class="ot-head">
    <a class="ot-back" href="/organizator/setari"><?= v2_ic('arrow-left') ?>Înapoi la setări</a>
    <div class="ot-head-row">
      <div class="ot-head-t">
        <p class="org-k">Setări</p>
        <h1 class="ot-h">Echipa</h1>
        <p class="ot-lead">Colegii care lucrează în cont: ce rol au, ce pot face și ce activități văd.</p>
      </div>
      <div class="ot-actions"><button class="btn btn-primary" type="button" id="ot-add" disabled><?= v2_ic('user-plus') ?>Adaugă un membru</button></div>
    </div>
  </header>

  <section class="ot-stats" aria-label="Pe scurt">
    <?= $otStat('total', 'Membri', 'users-three') ?>
    <?= $otStat('active', 'Activi', 'check-circle') ?>
    <?= $otStat('pending', 'Invitații în așteptare', 'clock') ?>
    <?= $otStat('admins', 'Administratori', 'lock-simple') ?>
  </section>

  <div class="ot-alert" id="ot-pending" role="status" hidden>
    <span class="ot-alert-ic"><?= v2_ic('envelope-simple') ?></span>
    <div><b id="ot-pending-t"></b><p>Invitațiile trimise pe email expiră la 7 zile. Le poți retrimite sau poți activa direct membrul, cu o parolă.</p></div>
    <button class="btn btn-ghost ot-sm" type="button" id="ot-resend-all"><span data-label>Retrimite invitațiile</span></button>
  </div>

  <div class="org-empty is-error" id="ot-load-err" role="alert" hidden>
    <span class="org-empty-ic"><?= v2_ic('warning-circle') ?></span>
    <b>Nu am putut încărca echipa</b>
    <p>Verifică conexiunea și încearcă din nou.</p>
    <div class="ot-empty-cta"><button class="btn btn-primary" type="button" id="ot-retry">Reîncearcă</button></div>
  </div>

  <section class="org-panel ot-panel" id="ot-panel" aria-labelledby="ot-list-h">
    <div class="org-panel-head">
      <div><p class="org-k">Membri</p><h2 class="org-panel-h" id="ot-list-h">Membrii echipei</h2><p class="org-panel-p" id="ot-list-p"></p></div>
      <label class="ot-search"><?= v2_ic('magnifying-glass') ?><input id="ot-q" type="search" autocomplete="off" spellcheck="false" placeholder="Caută după nume sau email" aria-label="Caută membri" aria-controls="ot-list"></label>
    </div>
    <ul class="ot-list" id="ot-list" aria-live="polite"><li><span class="org-skel ot-sk-row"></span></li></ul>
  </section>

  <section class="org-panel" aria-labelledby="ot-roles-h">
    <div class="org-panel-head"><div><p class="org-k">Roluri</p><h2 class="org-panel-h" id="ot-roles-h">Despre roluri și permisiuni</h2><p class="org-panel-p">Membrii se autentifică cu emailul și parola lor, <a href="/autentificare?ca=venue">pe bilete.online</a> și în aplicația mobilă.</p></div></div>
    <div class="ot-roles">
      <article class="ot-role is-owner"><span class="ot-role-dot" aria-hidden="true"></span><b>Proprietar</b><p>Acces complet la toate funcționalitățile, inclusiv la setări și la ștergerea contului.</p></article>
      <article class="ot-role is-admin"><span class="ot-role-dot" aria-hidden="true"></span><b>Administrator</b><p>Gestionează toate activitățile, comenzile, rapoartele și membrii echipei.</p></article>
      <article class="ot-role is-manager"><span class="ot-role-dot" aria-hidden="true"></span><b>Manager</b><p>Gestionează activitățile la care are acces și procesează comenzi, după permisiunile primite.</p></article>
      <article class="ot-role is-staff"><span class="ot-role-dot" aria-hidden="true"></span><b>Staff</b><p>Face check-in la intrare, din aplicația mobilă, la activitățile la care are acces.</p></article>
    </div>
  </section>

  <dialog class="ot-dialog is-wide" id="ot-member-d" aria-labelledby="ot-member-h">
    <form class="ot-d-inner" id="ot-member-form" novalidate>
      <div class="ot-d-head"><div><h2 class="ot-d-h" id="ot-member-h">Membru nou</h2><p class="ot-d-p" id="ot-member-p"></p></div><?= $otX ?></div>
      <div class="ot-grid" id="ot-ident">
        <label class="ot-f"><span class="ot-f-l">Nume <small>(opțional)</small></span><input id="ot-name" type="text" maxlength="255" autocomplete="off" placeholder="ex: Ion Popescu" aria-describedby="ot-name-err"><span class="ot-err" id="ot-name-err" hidden></span></label>
        <label class="ot-f"><span class="ot-f-l">Email</span><input id="ot-email" type="email" maxlength="255" autocomplete="off" spellcheck="false" placeholder="ex: ion@firma.ro" aria-describedby="ot-email-err"><span class="ot-err" id="ot-email-err" hidden></span></label>
        <div class="ot-wide" id="ot-pass-f"><?= $otPassField('ot-pass', 'Parola', 'Cel puțin 8 caractere. O vede o singură dată, în emailul de bun venit sau de la tine.') ?></div>
      </div>
      <label class="ot-f">
        <span class="ot-f-l">Rolul</span>
        <span class="ot-select"><select id="ot-role" aria-describedby="ot-role-help"><option value="staff">Staff</option><option value="manager">Manager</option><option value="admin">Administrator</option></select><?= v2_ic('caret-down') ?></span>
        <span class="ot-help" id="ot-role-help"></span>
      </label>
      <fieldset class="ot-fs" id="ot-perms">
        <legend>Permisiuni</legend>
        <div class="ot-checks">
          <?php foreach ($otPerms as $otKey => $otLabel): ?><label class="ot-check"><input type="checkbox" value="<?= $otKey ?>" data-perm><span><?= $otLabel ?></span></label><?php endforeach; ?>
        </div>
        <span class="ot-err" id="ot-perms-err" hidden></span>
      </fieldset>
      <fieldset class="ot-fs" id="ot-scope">
        <legend>Ce activități vede</legend>
        <div class="ot-radios">
          <label class="ot-check"><input type="radio" name="ot-scope" value="all" checked><span><b>Toate activitățile</b><small>Inclusiv cele adăugate de acum înainte.</small></span></label>
          <label class="ot-check"><input type="radio" name="ot-scope" value="some"><span><b>Doar activitățile alese</b><small>Nu vede nimic din celelalte.</small></span></label>
        </div>
        <div class="ot-some" id="ot-some" hidden>
          <span class="ot-psearch" id="ot-psearch" hidden><?= v2_ic('magnifying-glass') ?><input id="ot-pq" type="search" autocomplete="off" placeholder="Caută o activitate" aria-label="Caută o activitate"></span>
          <ul class="ot-picks" id="ot-picks"><li class="ot-picks-msg">Se încarcă activitățile…</li></ul>
          <span class="ot-help" id="ot-picks-n" aria-live="polite"></span>
          <span class="ot-err" id="ot-picks-err" hidden></span>
        </div>
      </fieldset>
      <label class="ot-check" id="ot-welcome-f"><input type="checkbox" id="ot-welcome" checked><span><b>Trimite-i emailul de bun venit</b><small>Cu emailul, parola și linkul spre aplicația mobilă. Fără email, îi comunici tu datele.</small></span></label>
      <div class="ot-form-err" id="ot-member-err" role="alert" hidden></div>
      <div class="ot-d-act"><button class="btn btn-ghost" type="button" data-close>Anulează</button><button class="btn btn-primary" type="submit" id="ot-member-go"><span data-label>Adaugă membrul</span></button></div>
    </form>
  </dialog>

  <dialog class="ot-dialog" id="ot-pass-d" aria-labelledby="ot-pass-h">
    <form class="ot-d-inner" id="ot-pass-form" novalidate>
      <div class="ot-d-head"><div><h2 class="ot-d-h" id="ot-pass-h">Parolă nouă</h2><p class="ot-d-p" id="ot-pass-p"></p></div><?= $otX ?></div>
      <?= $otPassField('ot-pw2', 'Parola nouă', 'Cel puțin 8 caractere. Comunică-i-o membrului; nu îi trimitem email.') ?>
      <div class="ot-form-err" id="ot-pass-err" role="alert" hidden></div>
      <div class="ot-d-act"><button class="btn btn-ghost" type="button" data-close>Anulează</button><button class="btn btn-primary" type="submit" id="ot-pass-go"><span data-label>Salvează parola</span></button></div>
    </form>
  </dialog>

  <dialog class="ot-dialog" id="ot-cred-d" aria-labelledby="ot-cred-h">
    <div class="ot-d-inner">
      <div class="ot-d-head"><div><h2 class="ot-d-h" id="ot-cred-h">Datele de autentificare</h2><p class="ot-d-p" id="ot-cred-p"></p></div><?= $otX ?></div>
      <dl class="ot-cred">
        <div><dt>Email</dt><dd id="ot-cred-email"></dd></div>
        <div><dt>Parolă</dt><dd id="ot-cred-pass"></dd></div>
        <div><dt>Autentificare</dt><dd id="ot-cred-url"></dd></div>
      </dl>
      <p class="ot-help">Parola nu mai apare nicăieri după ce închizi fereastra.</p>
      <div class="ot-d-act"><button class="btn btn-ghost" type="button" id="ot-cred-copy"><?= v2_ic('copy') ?>Copiază datele</button><button class="btn btn-primary" type="button" data-close>Gata</button></div>
    </div>
  </dialog>

  <dialog class="ot-dialog is-small" id="ot-del-d" aria-labelledby="ot-del-h" aria-describedby="ot-del-p">
    <div class="ot-d-inner">
      <h2 class="ot-d-h" id="ot-del-h">Elimini membrul?</h2>
      <p class="ot-d-p" id="ot-del-p"></p>
      <div class="ot-form-err" id="ot-del-err" role="alert" hidden></div>
      <div class="ot-d-act"><button class="btn btn-ghost" type="button" data-close>Renunță</button><button class="btn ot-danger" type="button" id="ot-del-go"><span data-label>Elimină</span></button></div>
    </div>
  </dialog>
</div>
<?php
v2_org_end();
include __DIR__ . '/../includes/v2/foot.php';
