<?php
/**
 * Organizer account settings: /organizator/setari, v2 design.
 *
 * Inside the v2 organizer shell. Seven tabs, each with its own address (#profile, #company, #bank, #contract,
 * #notifications, #security, #sharelinks; other pages link to #bank, #company and #contract): the organizer profile and
 * the guarantor details captured at sign-up; the main company (with the ANAF check) and a second issuing company; bank
 * accounts (add, primary, issuing company, delete); the contract (commission, work mode, terms, download, the electronic
 * signature, the ID and CUI documents); which notifications arrive and where; the password; share links for sponsors and
 * partners (create, copy, open, refresh, switch off, delete). A summary on top says what is still missing.
 * org-settings.js reads /organizer/me, /organizer/bank-accounts, /organizer/contract, /organizer/share-links,
 * /organizer/notifications/types and /organizer/events through the proxy.
 *
 * Fixed on the way:
 * - "Semnează contractul" (where the activity editor and the account banner send organizers) had nothing to sign: the
 *   signature is drawn or typed here and sent to core;
 * - notification preferences were "saved" into the notifications list, which the proxy only reads, and core keeps no such
 *   preference: the tab says what really arrives and where;
 * - the contact e-mail and the VAT payer answer looked editable but core ignores both; the VAT answer was read from a
 *   field core doesn't send;
 * - the CNP was shown in full: it stays masked until asked for;
 * - errors came as browser alerts, some in English ("Current password is incorrect"); deleting used confirm().
 */

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/api.php';
require_once __DIR__ . '/../includes/nav-helpers.php';
require_once __DIR__ . '/../includes/v2/helpers.php';
require_once __DIR__ . '/../includes/v2/organizer.php';

$pageTitleRaw = 'Setări cont — ' . SITE_NAME;
$pageDescription = 'Setările contului de operator pe bilete.online: profil, companie, conturi bancare, contract, notificări, securitate și link-uri share.';
$canonicalUrl = SITE_URL . '/organizator/setari';
$noindex = true;
$skipPageCache = true;

$v2Styles = ['organizer.css', 'org-settings.css'];
$v2Scripts = ['organizer.js', 'org-settings.js'];
$v2LegacyScripts = ['assets/js/config.js', 'assets/js/utils.js', 'assets/js/api.js', 'assets/js/auth.js'];
$v2HeadExtra = v2_account_client_config('organizer');

$osTabs = ['profile' => 'Profil', 'company' => 'Companie', 'bank' => 'Conturi bancare', 'contract' => 'Contract', 'notifications' => 'Notificări', 'security' => 'Securitate', 'sharelinks' => 'Link-uri share'];

/** A labelled input: id, label (trusted markup), input attributes, optional help (trusted markup), extra class. */
$osField = function (string $id, string $label, string $attrs, string $help = '', string $cls = '') {
    $described = $id . '-err' . ($help !== '' ? ' ' . $id . '-help' : '');
    return '<label class="os-f' . ($cls !== '' ? ' ' . $cls : '') . '"><span class="os-f-l">' . $label . '</span>'
        . '<input id="' . $id . '" ' . $attrs . ' aria-describedby="' . $described . '">'
        . ($help !== '' ? '<span class="os-help" id="' . $id . '-help">' . $help . '</span>' : '')
        . '<span class="os-err" id="' . $id . '-err" hidden></span></label>';
};
$osSum = function (string $id, string $tab, string $icon, string $label) {
    return '<button class="os-sum-i" type="button" id="os-sum-' . $id . '" data-go="' . $tab . '"><span class="os-sum-ic">' . v2_ic($icon) . '</span>'
        . '<span class="os-sum-t"><span class="os-sum-k">' . $label . '</span><b class="os-sum-v"><span class="org-skel os-sk"></span></b><small class="os-sum-p"></small></span></button>';
};
$osX = '<button class="os-x" type="button" data-close aria-label="Închide">' . v2_ic('x') . '</button>';

include __DIR__ . '/../includes/v2/head.php';
v2_org_start('settings');
?>
<div class="os" id="os">
  <header class="os-head">
    <div class="os-head-t">
      <p class="org-k">Setări</p>
      <h1 class="os-h">Cont și companie</h1>
      <p class="os-lead">Profilul, datele firmei, conturile în care primești banii, contractul și securitatea contului.</p>
    </div>
    <div class="os-actions">
      <a class="btn btn-ghost" href="/organizator/echipa"><?= v2_ic('users-three') ?>Echipă</a>
      <a class="btn btn-ghost" href="/organizator/apidoc"><?= v2_ic('code') ?>API</a>
    </div>
  </header>

  <section class="os-sum" aria-label="Starea contului">
    <?= $osSum('status', 'profile', 'user-circle', 'Contul') ?>
    <?= $osSum('contract', 'contract', 'signature', 'Contractul') ?>
    <?= $osSum('docs', 'contract', 'identification-card', 'Documentele') ?>
    <?= $osSum('bank', 'bank', 'bank', 'Plățile') ?>
  </section>

  <div class="os-tabs" role="tablist" aria-label="Secțiuni setări" data-tabs>
    <?php $osFirst = true; foreach ($osTabs as $osKey => $osLabel): ?><button class="os-tab" type="button" role="tab" id="os-tab-<?= $osKey ?>" aria-controls="os-p-<?= $osKey ?>" aria-selected="<?= $osFirst ? 'true' : 'false' ?>"<?= $osFirst ? '' : ' tabindex="-1"' ?>><?= $osLabel ?><span class="os-dot" id="os-dot-<?= $osKey ?>" hidden><span class="sr"> (de completat)</span></span></button><?php $osFirst = false; endforeach; ?>
  </div>

  <div class="org-empty is-error os-load-err" id="os-load-err" role="alert" hidden>
    <span class="org-empty-ic"><?= v2_ic('warning-circle') ?></span>
    <b>Nu am putut încărca datele contului</b>
    <p>Până nu le încărcăm, nu salvăm nimic, ca să nu suprascriem ce ai deja.</p>
    <div class="os-empty-cta"><button class="btn btn-primary" type="button" id="os-retry">Reîncearcă</button></div>
  </div>

  <!-- ============ PROFILE ============ -->
  <section class="org-panel os-panel" id="os-p-profile" role="tabpanel" aria-labelledby="os-tab-profile">
    <div class="org-panel-head"><div><p class="org-k">Profil</p><h2 class="org-panel-h">Profil operator</h2><p class="org-panel-p">Numele și datele de contact apar pe pagina ta publică și pe biletele vândute.</p></div></div>
    <form class="os-form" id="os-profile-form" novalidate>
      <fieldset class="os-fs" disabled>
        <div class="os-grid">
          <?= $osField('os-name', 'Nume operator', 'type="text" maxlength="255" autocomplete="organization" required') ?>
          <?= $osField('os-contact', 'Persoana de contact <small>(opțional)</small>', 'type="text" maxlength="255" autocomplete="name"') ?>
          <?= $osField('os-email', 'Email cont', 'type="email" readonly', 'Adresa cu care te autentifici și la care primești emailurile. Pentru s-o schimbi, <a href="/organizator/suport">scrie-ne</a>.') ?>
          <?= $osField('os-phone', 'Telefon <small>(opțional)</small>', 'type="tel" maxlength="50" autocomplete="tel" inputmode="tel"') ?>
          <?= $osField('os-website', 'Website <small>(opțional)</small>', 'type="url" maxlength="255" autocomplete="url" placeholder="ex: www.firma-ta.ro" inputmode="url"', '', 'is-wide') ?>
          <label class="os-f is-wide">
            <span class="os-f-l">Descriere <small>(opțional)</small></span>
            <textarea id="os-desc" rows="4" maxlength="2000" aria-describedby="os-desc-n"></textarea>
            <span class="os-help os-count" id="os-desc-n">0 / 2.000</span>
          </label>
        </div>
      </fieldset>
      <div class="os-form-err" id="os-profile-err" role="alert" hidden></div>
      <div class="os-act"><button class="btn btn-primary" type="submit" id="os-profile-go" disabled><span data-label>Salvează profilul</span></button></div>
    </form>

    <div class="os-block" id="os-guarantor" hidden>
      <div class="os-block-head"><div><h3>Date personale / garant</h3><p class="os-p">Informațiile completate la înregistrare. Pentru modificări, <a href="/organizator/suport">scrie-ne</a>.</p></div></div>
      <dl class="os-dl" id="os-guarantor-dl"></dl>
    </div>
  </section>

  <!-- ============ COMPANY ============ -->
  <section class="org-panel os-panel" id="os-p-company" role="tabpanel" aria-labelledby="os-tab-company" hidden>
    <div class="org-panel-head"><div><p class="org-k">Companie</p><h2 class="org-panel-h">Datele companiei</h2><p class="org-panel-p">Apar pe contract, pe facturile de comision și pe deconturi.</p></div></div>
    <form class="os-form" id="os-company-form" novalidate>
      <fieldset class="os-fs" disabled>
        <div class="os-block-head"><div><h3>Compania principală (SC1)</h3><p class="os-p" id="os-company-lock" hidden>Datele firmei sunt cele din ANAF și nu se modifică din cont. Dacă s-a schimbat ceva, <a href="/organizator/suport">scrie-ne</a>.</p><p class="os-p" id="os-company-how" hidden>Scrie CUI-ul firmei și verifică-l în ANAF: datele se completează singure, apoi le salvezi.</p></div></div>
        <div class="os-grid">
          <label class="os-f">
            <span class="os-f-l">CUI / CIF</span>
            <span class="os-inline"><input id="os-cui" type="text" maxlength="50" autocomplete="off" spellcheck="false" required aria-describedby="os-cui-err os-anaf-msg"><button class="btn btn-ghost os-sm" type="button" id="os-anaf"><span data-label>Verifică în ANAF</span></button></span>
            <span class="os-help" id="os-anaf-msg" aria-live="polite"></span>
            <span class="os-err" id="os-cui-err" hidden></span>
          </label>
          <?= $osField('os-cname', 'Denumirea firmei', 'type="text" maxlength="255" autocomplete="organization" readonly') ?>
          <?= $osField('os-creg', 'Nr. Registrul Comerțului', 'type="text" maxlength="100" autocomplete="off" spellcheck="false" readonly') ?>
          <div class="os-f">
            <span class="os-f-l">Plătitor de TVA</span>
            <p class="os-static" id="os-vat">—</p>
          </div>
          <?= $osField('os-caddr', 'Adresa sediului', 'type="text" maxlength="500" autocomplete="street-address" readonly', '', 'is-wide') ?>
          <?= $osField('os-ccity', 'Localitatea', 'type="text" maxlength="100" autocomplete="address-level2" readonly') ?>
          <?= $osField('os-ccounty', 'Județul', 'type="text" maxlength="100" autocomplete="address-level1" readonly') ?>
          <?= $osField('os-czip', 'Cod poștal', 'type="text" maxlength="20" autocomplete="postal-code" readonly') ?>
        </div>
      </fieldset>
      <div class="os-form-err" id="os-company-err" role="alert" hidden></div>
      <div class="os-act" id="os-company-act"><button class="btn btn-primary" type="submit" id="os-company-go" disabled><span data-label>Salvează datele firmei</span></button></div>
    </form>

    <form class="os-block" id="os-sc2-form" novalidate>
      <div class="os-block-head">
        <div><h3>Compania secundară (SC2)</h3><p class="os-p">Biletele de acces pot fi emise pe SC1, iar serviciile conexe (parcare, închirieri, activități) pe SC2. Fiecare cont bancar se leagă de societatea lui, din „Conturi bancare”.</p></div>
        <label class="os-switch"><input type="checkbox" id="os-sc2-on" disabled><span>Am o a doua societate emitentă</span></label>
      </div>
      <fieldset class="os-fs" id="os-sc2-fields" hidden>
        <p class="os-p" id="os-sc2-lock" hidden>Datele celei de-a doua firme sunt cele din ANAF și nu se modifică din cont. Pentru o schimbare, <a href="/organizator/suport">scrie-ne</a>.</p>
        <div class="os-grid">
          <label class="os-f">
            <span class="os-f-l">CUI / CIF</span>
            <span class="os-inline"><input id="os-s-cui" type="text" maxlength="50" autocomplete="off" spellcheck="false" aria-describedby="os-s-cui-err os-s-anaf-msg"><button class="btn btn-ghost os-sm" type="button" id="os-s-anaf"><span data-label>Verifică în ANAF</span></button></span>
            <span class="os-help" id="os-s-anaf-msg" aria-live="polite"></span>
            <span class="os-err" id="os-s-cui-err" hidden></span>
          </label>
        </div>
        <div class="os-grid" id="os-s-data" hidden>
          <?= $osField('os-s-name', 'Denumirea firmei', 'type="text" maxlength="255" autocomplete="off" readonly') ?>
          <?= $osField('os-s-reg', 'Nr. Registrul Comerțului', 'type="text" maxlength="100" autocomplete="off" spellcheck="false" readonly') ?>
          <?= $osField('os-s-addr', 'Adresa sediului', 'type="text" maxlength="500" autocomplete="off" readonly', '', 'is-wide') ?>
          <?= $osField('os-s-city', 'Localitatea', 'type="text" maxlength="100" autocomplete="off" readonly') ?>
          <?= $osField('os-s-county', 'Județul', 'type="text" maxlength="100" autocomplete="off" readonly') ?>
          <?= $osField('os-s-zip', 'Cod poștal', 'type="text" maxlength="20" autocomplete="off" readonly') ?>
        </div>
      </fieldset>
      <div class="os-form-err" id="os-sc2-err" role="alert" hidden></div>
      <div class="os-act" id="os-sc2-act" hidden><button class="btn btn-primary" type="submit" id="os-sc2-go" disabled><span data-label>Salvează SC2</span></button></div>
    </form>
  </section>

  <!-- ============ BANK ============ -->
  <section class="org-panel os-panel" id="os-p-bank" role="tabpanel" aria-labelledby="os-tab-bank" hidden>
    <div class="org-panel-head">
      <div><p class="org-k">Plăți</p><h2 class="org-panel-h">Conturi bancare</h2><p class="org-panel-p">Deconturile se plătesc în contul principal. Poți avea cel mult 5 conturi.</p></div>
      <button class="btn btn-primary os-sm" type="button" id="os-bank-add" disabled><?= v2_ic('plus') ?>Adaugă cont</button>
    </div>
    <ul class="os-list" id="os-bank-list"><li><span class="org-skel os-sk-row"></span></li></ul>
  </section>

  <!-- ============ CONTRACT ============ -->
  <section class="org-panel os-panel" id="os-p-contract" role="tabpanel" aria-labelledby="os-tab-contract" hidden>
    <div class="org-panel-head">
      <div><p class="org-k">Contract</p><h2 class="org-panel-h">Contractul cu <?= htmlspecialchars(SITE_NAME) ?></h2><p class="org-panel-p" id="os-contract-p">Se generează automat din datele firmei tale și din condițiile comerciale agreate.</p></div>
      <button class="btn btn-ghost os-sm" type="button" id="os-contract-dl" hidden><?= v2_ic('file-text') ?><span data-label>Descarcă contractul</span></button>
    </div>
    <div class="os-callout" id="os-contract-state"><span class="org-skel os-sk"></span></div>
    <dl class="os-figs">
      <div class="os-fig"><dt>Comision</dt><dd id="os-k-comm">—</dd><p id="os-k-comm-p">conform contractului</p></div>
      <div class="os-fig"><dt>Mod de lucru</dt><dd id="os-k-work">—</dd><p id="os-k-work-p"></p></div>
      <div class="os-fig"><dt>Cum se aplică comisionul</dt><dd id="os-k-mode">—</dd><p id="os-k-mode-p"></p></div>
    </dl>
    <div class="os-block">
      <h3>Condiții contractuale</h3>
      <ul class="os-terms" id="os-terms"></ul>
    </div>

    <form class="os-block is-warm os-sign" id="os-sign" novalidate hidden>
      <div class="os-block-head"><div><h3>Semnează contractul</h3><p class="os-p">Semnătura electronică se aplică pe contract și primești imediat PDF-ul semnat. Până la semnare nu poți cere plăți.</p></div><a class="os-linkbtn" id="os-sign-read" href="#" target="_blank" rel="noopener" hidden><?= v2_ic('file-text') ?>Citește contractul</a></div>
      <div class="os-pad-wrap" id="os-pad-wrap">
        <canvas class="os-pad" id="os-pad" role="img" aria-label="Zona pentru semnătură. Desenează cu mouse-ul sau cu degetul, sau scrie-ți numele mai jos."></canvas>
        <span class="os-pad-line" aria-hidden="true"></span>
        <span class="os-pad-hint" id="os-pad-hint" aria-hidden="true">Semnează aici</span>
      </div>
      <div class="os-pad-tools">
        <label class="os-f os-typed"><span class="os-f-l">Sau scrie-ți numele</span><input id="os-sign-typed" type="text" maxlength="80" autocomplete="name" aria-describedby="os-sign-typed-help"><span class="os-help" id="os-sign-typed-help">Numele scris devine semnătura din casetă.</span></label>
        <button class="btn btn-ghost os-sm" type="button" id="os-pad-clear"><?= v2_ic('arrow-counter-clockwise') ?>Șterge semnătura</button>
      </div>
      <label class="os-check"><input type="checkbox" id="os-agree"><span>Am citit și sunt de acord cu termenii contractului.</span></label>
      <div class="os-form-err" id="os-sign-err" role="alert" hidden></div>
      <div class="os-act"><button class="btn btn-primary" type="submit" id="os-sign-go"><?= v2_ic('signature') ?><span data-label>Semnează contractul</span></button></div>
    </form>

    <div class="os-block">
      <div class="os-block-head"><div><h3>Documente necesare</h3><p class="os-p">Pentru activarea contului și pentru plăți avem nevoie de ambele documente. După ce le încarci, contractul se generează automat.</p></div></div>
      <div class="os-docs">
        <?php foreach (['id_card' => ['identification-card', 'Copie CI / buletin', 'a reprezentantului legal'], 'cui_document' => ['buildings', 'Copie certificat CUI', 'certificatul de înregistrare al firmei']] as $osDoc => [$osDocIc, $osDocT, $osDocP]): ?>
        <div class="os-doc" id="os-doc-<?= $osDoc ?>" data-doc="<?= $osDoc ?>">
          <div class="os-doc-top"><span class="os-doc-ic"><?= v2_ic($osDocIc) ?></span><div><b><?= $osDocT ?></b><small><?= $osDocP ?> · PDF, JPG sau PNG, cel mult 5&nbsp;MB</small></div><span data-doc-tag></span></div>
          <label class="os-drop" data-drop>
            <input class="sr" type="file" accept=".pdf,.jpg,.jpeg,.png,application/pdf,image/jpeg,image/png" data-doc-input aria-describedby="os-doc-<?= $osDoc ?>-s">
            <span class="os-drop-t"><?= v2_ic('upload-simple') ?><b data-doc-cta>Alege fișierul</b><span>sau trage-l aici</span></span>
          </label>
          <p class="os-doc-s" id="os-doc-<?= $osDoc ?>-s" data-doc-status aria-live="polite"></p>
          <a class="os-linkbtn" data-doc-view href="#" target="_blank" rel="noopener" hidden><?= v2_ic('arrow-up-right') ?>Vezi documentul încărcat</a>
        </div>
        <?php endforeach; ?>
      </div>
    </div>
  </section>

  <!-- ============ NOTIFICATIONS ============ -->
  <section class="org-panel os-panel" id="os-p-notifications" role="tabpanel" aria-labelledby="os-tab-notifications" hidden>
    <div class="org-panel-head"><div><p class="org-k">Notificări</p><h2 class="org-panel-h">Ce primești și unde</h2><p class="org-panel-p">Te ținem la curent în contul tău și pe email, fără să fie nevoie să setezi ceva.</p></div><a class="org-more" href="/organizator/notificari">Toate notificările<?= v2_ic('arrow-right') ?></a></div>
    <div class="os-cards">
      <article class="os-block"><span class="os-card-ic"><?= v2_ic('bell') ?></span><h3>În cont, la clopoțel</h3><p class="os-p">Apar în bara de sus a contului, cu numărul celor necitite.</p><ul class="os-chips" id="os-ntypes"><li><span class="org-skel os-sk"></span></li></ul></article>
      <article class="os-block"><span class="os-card-ic"><?= v2_ic('envelope-simple') ?></span><h3>Pe email</h3><p class="os-p" id="os-nmail">Primești email când o cerere de plată este înregistrată, aprobată, în procesare, plătită sau respinsă.</p></article>
      <article class="os-block"><span class="os-card-ic"><?= v2_ic('clock') ?></span><h3>Rapoarte pe email</h3><p class="os-p">Emailul la fiecare vânzare, raportul zilnic al vânzărilor și alertele când se termină biletele nu se pot încă alege din cont. Vânzările le vezi oricând în <a href="/organizator/vanzari">Vânzări</a>.</p><p class="os-p"><a href="/organizator/suport">Spune-ne dacă ai nevoie de ele</a></p></article>
    </div>
  </section>

  <!-- ============ SECURITY ============ -->
  <section class="org-panel os-panel" id="os-p-security" role="tabpanel" aria-labelledby="os-tab-security" hidden>
    <div class="org-panel-head"><div><p class="org-k">Securitate</p><h2 class="org-panel-h">Schimbă parola</h2><p class="org-panel-p">Parola contului de operator. Membrii echipei au parolele lor, din <a href="/organizator/echipa">Echipă</a>.</p></div></div>
    <div class="os-sec">
      <form class="os-form" id="os-pass-form" novalidate>
        <?php foreach (['cur' => ['Parola curentă', 'current-password'], 'new' => ['Parola nouă', 'new-password'], 'conf' => ['Confirmă parola nouă', 'new-password']] as $osP => [$osPl, $osPa]): ?>
        <label class="os-f">
          <span class="os-f-l"><?= $osPl ?></span>
          <span class="os-pass"><input id="os-pass-<?= $osP ?>" type="password" autocomplete="<?= $osPa ?>" maxlength="128" aria-describedby="os-pass-<?= $osP ?>-err<?= $osP === 'new' ? ' os-rules' : '' ?>"><button class="os-eye" type="button" data-eye="os-pass-<?= $osP ?>" aria-label="Arată parola" aria-pressed="false"><?= v2_ic('eye') ?></button></span>
          <span class="os-err" id="os-pass-<?= $osP ?>-err" hidden></span>
        </label>
        <?php if ($osP === 'new'): ?>
        <ul class="os-rules" id="os-rules" aria-live="polite">
          <li data-rule="len"><?= v2_ic('check-circle') ?>Cel puțin 8 caractere</li>
          <li data-rule="mix"><?= v2_ic('check-circle') ?>Litere mari și mici <small>(recomandat)</small></li>
          <li data-rule="num"><?= v2_ic('check-circle') ?>O cifră sau un simbol <small>(recomandat)</small></li>
        </ul>
        <?php endif; ?>
        <?php endforeach; ?>
        <div class="os-form-err" id="os-pass-err" role="alert" hidden></div>
        <div class="os-act"><button class="btn btn-primary" type="submit" id="os-pass-go"><?= v2_ic('lock-simple') ?><span data-label>Schimbă parola</span></button></div>
      </form>
      <aside class="os-block os-aside">
        <h3>Câteva sfaturi</h3>
        <ul class="os-terms">
          <li><?= v2_ic('check-circle') ?>Folosește o parolă pe care nu o mai ai la alt cont.</li>
          <li><?= v2_ic('check-circle') ?>Nu o trimite pe email sau pe chat; echipa noastră nu îți cere niciodată parola.</li>
          <li><?= v2_ic('check-circle') ?>Pentru colegi, adaugă-i în <a href="/organizator/echipa">Echipă</a> în loc să împarți contul.</li>
        </ul>
      </aside>
    </div>
  </section>

  <!-- ============ SHARE LINKS ============ -->
  <section class="org-panel os-panel" id="os-p-sharelinks" role="tabpanel" aria-labelledby="os-tab-sharelinks" hidden>
    <div class="org-panel-head">
      <div><p class="org-k">Link-uri share</p><h2 class="org-panel-h">Link-uri de monitorizare</h2><p class="org-panel-p">Linkuri unice prin care partenerii și sponsorii văd vânzările activităților tale în timp real.</p></div>
      <button class="btn btn-primary os-sm" type="button" id="os-share-add" disabled><?= v2_ic('plus') ?>Link nou</button>
    </div>
    <div class="os-callout">
      <?= v2_ic('info') ?>
      <div><b>Cum funcționează?</b><p>Alegi una sau mai multe activități și generezi un link. Oricine îl deschide vede numele activităților, locul, data și ora, câte bilete sunt puse în vânzare și câte s-au vândut. Poți adăuga o parolă, încasările și lista participanților.</p></div>
    </div>
    <ul class="os-list" id="os-share-list"><li><span class="org-skel os-sk-row"></span></li></ul>
  </section>

  <!-- ============ DIALOGS ============ -->
  <dialog class="os-dialog" id="os-bank-d" aria-labelledby="os-bank-h">
    <form class="os-d-inner" id="os-bank-form" novalidate>
      <div class="os-d-head"><h2 class="os-d-h" id="os-bank-h">Adaugă un cont bancar</h2><?= $osX ?></div>
      <label class="os-f">
        <span class="os-f-l">IBAN</span>
        <input id="os-iban" type="text" maxlength="42" autocomplete="off" spellcheck="false" autocapitalize="characters" placeholder="RO49 AAAA 1B31 0075 9384 0000" aria-describedby="os-iban-msg os-iban-err">
        <span class="os-help" id="os-iban-msg" aria-live="polite"></span>
        <span class="os-err" id="os-iban-err" hidden></span>
      </label>
      <?= $osField('os-bname', 'Banca', 'type="text" maxlength="100" autocomplete="off"') ?>
      <?= $osField('os-holder', 'Titularul contului', 'type="text" maxlength="255" autocomplete="off"', 'Exact ca în extrasul de cont.') ?>
      <label class="os-f" id="os-issuer-f" hidden>
        <span class="os-f-l">Societatea emitentă</span>
        <span class="os-select"><select id="os-issuer"><option value="primary">Compania principală (SC1)</option><option value="secondary">Compania secundară (SC2)</option></select><?= v2_ic('caret-down') ?></span>
        <span class="os-help">Contul primește încasările acestei societăți.</span>
      </label>
      <div class="os-form-err" id="os-bank-err" role="alert" hidden></div>
      <div class="os-d-act"><button class="btn btn-ghost" type="button" data-close>Anulează</button><button class="btn btn-primary" type="submit" id="os-bank-go"><span data-label>Adaugă contul</span></button></div>
    </form>
  </dialog>

  <dialog class="os-dialog is-wide" id="os-share-d" aria-labelledby="os-share-h">
    <form class="os-d-inner" id="os-share-form" novalidate>
      <div class="os-d-head"><h2 class="os-d-h" id="os-share-h">Link de monitorizare nou</h2><?= $osX ?></div>
      <?= $osField('os-sname', 'Numele linkului <small>(opțional)</small>', 'type="text" maxlength="100" autocomplete="off" placeholder="ex: Link pentru sponsor"') ?>
      <label class="os-f">
        <span class="os-f-l">Parolă de acces <small>(opțional)</small></span>
        <span class="os-pass"><input id="os-spass" type="password" maxlength="100" autocomplete="new-password" aria-describedby="os-spass-help"><button class="os-eye" type="button" data-eye="os-spass" aria-label="Arată parola" aria-pressed="false"><?= v2_ic('eye') ?></button></span>
        <span class="os-help" id="os-spass-help">Cu parolă, vizitatorii o introduc înainte să vadă datele. Lasă gol pentru acces liber.</span>
      </label>
      <fieldset class="os-f os-fs-plain">
        <legend class="os-f-l">Activitățile din link</legend>
        <span class="os-inline os-psearch" id="os-psearch" hidden><input id="os-pq" type="search" autocomplete="off" placeholder="Caută o activitate" aria-label="Caută o activitate"></span>
        <ul class="os-picks" id="os-picks" aria-describedby="os-picks-n"><li class="os-picks-msg">Se încarcă activitățile…</li></ul>
        <span class="os-help" id="os-picks-n" aria-live="polite"></span>
        <span class="os-err" id="os-picks-err" hidden></span>
      </fieldset>
      <label class="os-check"><input type="checkbox" id="os-sp-participants"><span><b>Arată participanții</b><small>Numele și telefonul participanților devin vizibile pentru oricine are linkul.</small></span></label>
      <label class="os-check"><input type="checkbox" id="os-sp-revenue"><span><b>Arată încasările</b><small>Încasările nete pe activitate și în total.</small></span></label>
      <div class="os-form-err" id="os-share-err" role="alert" hidden></div>
      <div class="os-d-act"><button class="btn btn-ghost" type="button" data-close>Anulează</button><button class="btn btn-primary" type="submit" id="os-share-go"><span data-label>Generează linkul</span></button></div>
    </form>
  </dialog>

  <dialog class="os-dialog is-small" id="os-del-d" aria-labelledby="os-del-h" aria-describedby="os-del-p">
    <div class="os-d-inner">
      <h2 class="os-d-h" id="os-del-h">Ștergi?</h2>
      <p class="os-d-p" id="os-del-p"></p>
      <div class="os-form-err" id="os-del-err" role="alert" hidden></div>
      <div class="os-d-act"><button class="btn btn-ghost" type="button" data-close>Renunță</button><button class="btn os-danger" type="button" id="os-del-go"><span data-label>Șterge</span></button></div>
    </div>
  </dialog>
</div>
<?php
v2_org_end();
include __DIR__ . '/../includes/v2/foot.php';
