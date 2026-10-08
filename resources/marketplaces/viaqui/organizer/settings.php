<?php
/**
 * Organizer account settings: /organizator/setari, v2 design.
 *
 * Inside the v2 organizer shell. Seven tabs, each with its own address (#profile, #company, #bank, #contract,
 * #notifications, #security, #sharelinks; other pages link to #bank, #company and #contract): the organizer profile and
 * the guarantor details captured at sign-up; the main company (with the ANAF check) and a second issuing company; bank
 * accounts (add, primary, issuing company, delete); the contract (commission, work mode, terms, read it in a window
 * before signing, download, the electronic signature, the ID and CUI documents); which notifications arrive and where;
 * the password; share links for sponsors and partners (create, copy, open, refresh, switch off, delete). A summary on
 * top says what is still missing.
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
 * - there was no way to READ the contract before signing it: "Vezi contractul" opens the PDF in a window (a frame on a
 *   wide screen, the two links that work on a phone below it), and only then comes the signature;
 * - the contract terms came from core as Ambilet's model ("decontarea in 7 zile lucratoare"), which viaqui.com does
 *   not do: the wording is written here and only the numbers (commission, work mode, invoice due days) come from core;
 * - the CNP was shown in full: it stays masked until asked for;
 * - errors came as browser alerts, some in English ("Current password is incorrect"); deleting used confirm().
 */

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/api.php';
require_once __DIR__ . '/../includes/nav-helpers.php';
require_once __DIR__ . '/../includes/v2/helpers.php';
require_once __DIR__ . '/../includes/v2/organizer.php';

$pageTitleRaw = v2_t('Account settings') . ' · ' . SITE_NAME;
$pageDescription = v2_t('Your operator account settings on Viaqui: profile, company, bank accounts, contract, notifications, security and share links.');
$canonicalUrl = SITE_URL . '/organizator/setari';
$noindex = true;
$skipPageCache = true;

$v2Styles = ['organizer.css', 'org-settings.css'];
$v2Scripts = ['organizer.js', 'org-settings.js'];
$v2LegacyScripts = ['assets/js/config.js', 'assets/js/utils.js', 'assets/js/api.js', 'assets/js/auth.js'];
$v2HeadExtra = v2_account_client_config('organizer');

$osTabs = ['profile' => v2_t('Profile'), 'company' => v2_t('Company'), 'bank' => v2_t('Bank accounts'), 'contract' => v2_t('Contract'), 'notifications' => v2_t('Notifications'), 'security' => v2_t('Security'), 'sharelinks' => v2_t('Share links')];

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
$osX = '<button class="os-x" type="button" data-close aria-label="' . v2_te('Close') . '">' . v2_ic('x') . '</button>';

include __DIR__ . '/../includes/v2/head.php';
v2_org_start('settings');
?>
<div class="os" id="os">
  <header class="os-head">
    <div class="os-head-t">
      <p class="org-k"><?= v2_te('Settings') ?></p>
      <h1 class="os-h"><?= v2_te('Account and company') ?></h1>
      <p class="os-lead"><?= v2_te('Your profile, company details, the accounts your money goes to, your contract and account security.') ?></p>
    </div>
    <div class="os-actions">
      <a class="btn btn-ghost" href="/organizator/echipa"><?= v2_ic('users-three') ?><?= v2_te('Team') ?></a>
      <a class="btn btn-ghost" href="/organizator/apidoc"><?= v2_ic('code') ?>API</a>
    </div>
  </header>

  <section class="os-sum" aria-label="<?= v2_te('Account status') ?>">
    <?= $osSum('status', 'profile', 'user-circle', v2_te('Account')) ?>
    <?= $osSum('contract', 'contract', 'signature', v2_te('Contract')) ?>
    <?= $osSum('docs', 'contract', 'identification-card', v2_te('Documents')) ?>
    <?= $osSum('bank', 'bank', 'bank', v2_te('Payments')) ?>
  </section>

  <div class="os-tabs" role="tablist" aria-label="<?= v2_te('Settings sections') ?>" data-tabs>
    <?php $osFirst = true; foreach ($osTabs as $osKey => $osLabel): ?><button class="os-tab" type="button" role="tab" id="os-tab-<?= $osKey ?>" aria-controls="os-p-<?= $osKey ?>" aria-selected="<?= $osFirst ? 'true' : 'false' ?>"<?= $osFirst ? '' : ' tabindex="-1"' ?>><?= v2_e($osLabel) ?><span class="os-dot" id="os-dot-<?= $osKey ?>" hidden><span class="sr"> <?= v2_te('(to complete)') ?></span></span></button><?php $osFirst = false; endforeach; ?>
  </div>

  <div class="org-empty is-error os-load-err" id="os-load-err" role="alert" hidden>
    <span class="org-empty-ic"><?= v2_ic('warning-circle') ?></span>
    <b><?= v2_te('We could not load your account details') ?></b>
    <p><?= v2_te('Until they load, nothing is saved, so we do not overwrite what you already have.') ?></p>
    <div class="os-empty-cta"><button class="btn btn-primary" type="button" id="os-retry"><?= v2_te('Try again') ?></button></div>
  </div>

  <!-- ============ PROFILE ============ -->
  <section class="org-panel os-panel" id="os-p-profile" role="tabpanel" aria-labelledby="os-tab-profile">
    <div class="org-panel-head"><div><p class="org-k"><?= v2_te('Profile') ?></p><h2 class="org-panel-h"><?= v2_te('Operator profile') ?></h2><p class="org-panel-p"><?= v2_te('Your name and contact details appear on your public page and on the tickets you sell.') ?></p></div></div>
    <form class="os-form" id="os-profile-form" novalidate>
      <fieldset class="os-fs" disabled>
        <div class="os-grid">
          <?= $osField('os-name', v2_te('Operator name'), 'type="text" maxlength="255" autocomplete="organization" required') ?>
          <?= $osField('os-contact', v2_t('Contact person <small>(optional)</small>'), 'type="text" maxlength="255" autocomplete="name"') ?>
          <?= $osField('os-email', v2_te('Account email'), 'type="email" readonly', v2_t('The address you sign in with and where you receive our emails. To change it, <a href="{url}">write to us</a>.', ['url' => '/organizator/suport'])) ?>
          <?= $osField('os-phone', v2_t('Phone <small>(optional)</small>'), 'type="tel" maxlength="50" autocomplete="tel" inputmode="tel"') ?>
          <?= $osField('os-website', v2_t('Website <small>(optional)</small>'), 'type="url" maxlength="255" autocomplete="url" placeholder="' . v2_te('e.g. www.your-company.com') . '" inputmode="url"', '', 'is-wide') ?>
          <label class="os-f is-wide">
            <span class="os-f-l"><?= v2_t('Description <small>(optional)</small>') ?></span>
            <textarea id="os-desc" rows="4" maxlength="2000" aria-describedby="os-desc-n"></textarea>
            <span class="os-help os-count" id="os-desc-n"><?= v2_te('{n} / 2,000', ['n' => 0]) ?></span>
          </label>
        </div>
      </fieldset>
      <div class="os-form-err" id="os-profile-err" role="alert" hidden></div>
      <div class="os-act"><button class="btn btn-primary" type="submit" id="os-profile-go" disabled><span data-label><?= v2_te('Save profile') ?></span></button></div>
    </form>

    <div class="os-block" id="os-guarantor" hidden>
      <div class="os-block-head"><div><h3><?= v2_te('Personal details / guarantor') ?></h3><p class="os-p"><?= v2_t('The details filled in at sign-up. For changes, <a href="{url}">write to us</a>.', ['url' => '/organizator/suport']) ?></p></div></div>
      <dl class="os-dl" id="os-guarantor-dl"></dl>
    </div>
  </section>

  <!-- ============ COMPANY ============ -->
  <section class="org-panel os-panel" id="os-p-company" role="tabpanel" aria-labelledby="os-tab-company" hidden>
    <div class="org-panel-head"><div><p class="org-k"><?= v2_te('Company') ?></p><h2 class="org-panel-h"><?= v2_te('Company details') ?></h2><p class="org-panel-p"><?= v2_te('They appear on the contract, on commission invoices and on payout statements.') ?></p></div></div>
    <form class="os-form" id="os-company-form" novalidate>
      <fieldset class="os-fs" disabled>
        <div class="os-block-head"><div><h3><?= v2_te('Main company (SC1)') ?></h3><p class="os-p" id="os-company-lock" hidden><?= v2_t('The company details come from ANAF and cannot be changed from the account. If something has changed, <a href="{url}">write to us</a>.', ['url' => '/organizator/suport']) ?></p><p class="os-p" id="os-company-how" hidden><?= v2_te('Enter the company tax ID (CUI) and check it with ANAF: the details fill in by themselves, then you save them.') ?></p></div></div>
        <div class="os-grid">
          <label class="os-f">
            <span class="os-f-l"><?= v2_te('Tax ID (CUI / CIF)') ?></span>
            <span class="os-inline"><input id="os-cui" type="text" maxlength="50" autocomplete="off" spellcheck="false" required aria-describedby="os-cui-err os-anaf-msg"><button class="btn btn-ghost os-sm" type="button" id="os-anaf"><span data-label><?= v2_te('Check with ANAF') ?></span></button></span>
            <span class="os-help" id="os-anaf-msg" aria-live="polite"></span>
            <span class="os-err" id="os-cui-err" hidden></span>
          </label>
          <?= $osField('os-cname', v2_te('Company name'), 'type="text" maxlength="255" autocomplete="organization" readonly') ?>
          <?= $osField('os-creg', v2_te('Trade register number'), 'type="text" maxlength="100" autocomplete="off" spellcheck="false" readonly') ?>
          <div class="os-f">
            <span class="os-f-l"><?= v2_te('VAT registered') ?></span>
            <p class="os-static" id="os-vat">—</p>
          </div>
          <?= $osField('os-caddr', v2_te('Registered office address'), 'type="text" maxlength="500" autocomplete="street-address" readonly', '', 'is-wide') ?>
          <?= $osField('os-ccity', v2_te('Town or city'), 'type="text" maxlength="100" autocomplete="address-level2" readonly') ?>
          <?= $osField('os-ccounty', v2_te('County'), 'type="text" maxlength="100" autocomplete="address-level1" readonly') ?>
          <?= $osField('os-czip', v2_te('Postcode'), 'type="text" maxlength="20" autocomplete="postal-code" readonly') ?>
        </div>
      </fieldset>
      <div class="os-form-err" id="os-company-err" role="alert" hidden></div>
      <div class="os-act" id="os-company-act"><button class="btn btn-primary" type="submit" id="os-company-go" disabled><span data-label><?= v2_te('Save company details') ?></span></button></div>
    </form>

    <form class="os-block" id="os-sc2-form" novalidate>
      <div class="os-block-head">
        <div><h3><?= v2_te('Second company (SC2)') ?></h3><p class="os-p"><?= v2_te('Access tickets can be issued by SC1 and related services (parking, rentals, activities) by SC2. Each bank account is linked to its company, under "Bank accounts".') ?></p></div>
        <label class="os-switch"><input type="checkbox" id="os-sc2-on" disabled><span><?= v2_te('I have a second issuing company') ?></span></label>
      </div>
      <fieldset class="os-fs" id="os-sc2-fields" hidden>
        <p class="os-p" id="os-sc2-lock" hidden><?= v2_t('The details of the second company come from ANAF and cannot be changed from the account. For a change, <a href="{url}">write to us</a>.', ['url' => '/organizator/suport']) ?></p>
        <div class="os-grid">
          <label class="os-f">
            <span class="os-f-l"><?= v2_te('Tax ID (CUI / CIF)') ?></span>
            <span class="os-inline"><input id="os-s-cui" type="text" maxlength="50" autocomplete="off" spellcheck="false" aria-describedby="os-s-cui-err os-s-anaf-msg"><button class="btn btn-ghost os-sm" type="button" id="os-s-anaf"><span data-label><?= v2_te('Check with ANAF') ?></span></button></span>
            <span class="os-help" id="os-s-anaf-msg" aria-live="polite"></span>
            <span class="os-err" id="os-s-cui-err" hidden></span>
          </label>
        </div>
        <div class="os-grid" id="os-s-data" hidden>
          <?= $osField('os-s-name', v2_te('Company name'), 'type="text" maxlength="255" autocomplete="off" readonly') ?>
          <?= $osField('os-s-reg', v2_te('Trade register number'), 'type="text" maxlength="100" autocomplete="off" spellcheck="false" readonly') ?>
          <?= $osField('os-s-addr', v2_te('Registered office address'), 'type="text" maxlength="500" autocomplete="off" readonly', '', 'is-wide') ?>
          <?= $osField('os-s-city', v2_te('Town or city'), 'type="text" maxlength="100" autocomplete="off" readonly') ?>
          <?= $osField('os-s-county', v2_te('County'), 'type="text" maxlength="100" autocomplete="off" readonly') ?>
          <?= $osField('os-s-zip', v2_te('Postcode'), 'type="text" maxlength="20" autocomplete="off" readonly') ?>
        </div>
      </fieldset>
      <div class="os-form-err" id="os-sc2-err" role="alert" hidden></div>
      <div class="os-act" id="os-sc2-act" hidden><button class="btn btn-primary" type="submit" id="os-sc2-go" disabled><span data-label><?= v2_te('Save SC2') ?></span></button></div>
    </form>
  </section>

  <!-- ============ BANK ============ -->
  <section class="org-panel os-panel" id="os-p-bank" role="tabpanel" aria-labelledby="os-tab-bank" hidden>
    <div class="org-panel-head">
      <div><p class="org-k"><?= v2_te('Payments') ?></p><h2 class="org-panel-h"><?= v2_te('Bank accounts') ?></h2><p class="org-panel-p"><?= v2_te('Payouts are paid into the primary account. You can have 5 accounts at most.') ?></p></div>
      <button class="btn btn-primary os-sm" type="button" id="os-bank-add" disabled><?= v2_ic('plus') ?><?= v2_te('Add account') ?></button>
    </div>
    <ul class="os-list" id="os-bank-list"><li><span class="org-skel os-sk-row"></span></li></ul>
  </section>

  <!-- ============ CONTRACT ============ -->
  <section class="org-panel os-panel" id="os-p-contract" role="tabpanel" aria-labelledby="os-tab-contract" hidden>
    <div class="org-panel-head">
      <div><p class="org-k"><?= v2_te('Contract') ?></p><h2 class="org-panel-h"><?= v2_te('Your contract with {site}', ['site' => SITE_NAME]) ?></h2><p class="org-panel-p" id="os-contract-p"><?= v2_te('It is generated automatically from your company details and the agreed commercial terms.') ?></p></div>
      <div class="os-cta-row">
        <button class="btn btn-primary os-sm" type="button" id="os-contract-view" hidden><?= v2_ic('eye') ?><span data-label><?= v2_te('View contract') ?></span></button>
        <button class="btn btn-ghost os-sm" type="button" id="os-contract-dl" hidden><?= v2_ic('download-simple') ?><span data-label><?= v2_te('Download contract') ?></span></button>
      </div>
    </div>
    <div class="os-callout" id="os-contract-state"><span class="org-skel os-sk"></span></div>
    <dl class="os-figs">
      <div class="os-fig"><dt><?= v2_te('Commission') ?></dt><dd id="os-k-comm">—</dd><p id="os-k-comm-p"><?= v2_te('as set in the contract') ?></p></div>
      <div class="os-fig"><dt><?= v2_te('Work mode') ?></dt><dd id="os-k-work">—</dd><p id="os-k-work-p"></p></div>
      <div class="os-fig"><dt><?= v2_te('How the commission is applied') ?></dt><dd id="os-k-mode">—</dd><p id="os-k-mode-p"></p></div>
    </dl>
    <div class="os-block">
      <h3><?= v2_te('Contract terms') ?></h3>
      <ul class="os-terms" id="os-terms">
        <li><?= v2_ic('check-circle') ?><span><?= v2_t('<b>{site} does not run payouts.</b> The commission is added on top of your price, and the money from online sales goes straight to your account, through split payment at checkout.', ['site' => v2_e(SITE_NAME)]) ?></span></li>
        <li><?= v2_ic('check-circle') ?><span><?= v2_t('<b>The POS commission is invoiced to you once a month.</b> For sales taken at the till, cash or card, we issue a single monthly invoice for the commission amount. Payment is due within {due}.', ['due' => '<b id="os-term-due">' . v2_te('5 calendar days') . '</b>']) ?></span></li>
        <li><?= v2_ic('check-circle') ?><span><?= v2_t('<b>You pay commission only on what you sell.</b> Your rate: {rate}. Work mode: {mode}. No fixed costs and no monthly subscription.', ['rate' => '<b id="os-term-comm">—</b>', 'mode' => '<b id="os-term-work">—</b>']) ?></span></li>
      </ul>
    </div>

    <form class="os-block is-warm os-sign" id="os-sign" novalidate hidden>
      <div class="os-block-head"><div><h3><?= v2_te('Sign the contract') ?></h3><p class="os-p"><?= v2_te('Your electronic signature is applied to the contract and you get the signed PDF straight away. Until you sign, you cannot request payouts.') ?></p></div><a class="os-linkbtn" id="os-sign-read" href="#" target="_blank" rel="noopener" hidden><?= v2_ic('file-text') ?><?= v2_te('Read the contract') ?></a></div>
      <div class="os-pad-wrap" id="os-pad-wrap">
        <canvas class="os-pad" id="os-pad" role="img" aria-label="<?= v2_te('Signature area. Draw with the mouse or your finger, or type your name below.') ?>"></canvas>
        <span class="os-pad-line" aria-hidden="true"></span>
        <span class="os-pad-hint" id="os-pad-hint" aria-hidden="true"><?= v2_te('Sign here') ?></span>
      </div>
      <div class="os-pad-tools">
        <label class="os-f os-typed"><span class="os-f-l"><?= v2_te('Or type your name') ?></span><input id="os-sign-typed" type="text" maxlength="80" autocomplete="name" aria-describedby="os-sign-typed-help"><span class="os-help" id="os-sign-typed-help"><?= v2_te('The typed name becomes the signature in the box.') ?></span></label>
        <button class="btn btn-ghost os-sm" type="button" id="os-pad-clear"><?= v2_ic('arrow-counter-clockwise') ?><?= v2_te('Clear signature') ?></button>
      </div>
      <label class="os-check"><input type="checkbox" id="os-agree"><span><?= v2_te('I have read and agree to the terms of the contract.') ?></span></label>
      <div class="os-form-err" id="os-sign-err" role="alert" hidden></div>
      <div class="os-act"><button class="btn btn-primary" type="submit" id="os-sign-go"><?= v2_ic('signature') ?><span data-label><?= v2_te('Sign the contract') ?></span></button></div>
    </form>

    <div class="os-block">
      <div class="os-block-head"><div><h3><?= v2_te('Required documents') ?></h3><p class="os-p"><?= v2_te('We need both documents to activate the account and to pay you. Once you upload them, the contract is generated automatically.') ?></p></div></div>
      <div class="os-docs">
        <?php foreach (['id_card' => ['identification-card', v2_t('Copy of ID card'), v2_t('Of the legal representative · PDF, JPG or PNG, 5 MB at most')], 'cui_document' => ['buildings', v2_t('Copy of tax registration certificate (CUI)'), v2_t('The company registration certificate · PDF, JPG or PNG, 5 MB at most')]] as $osDoc => [$osDocIc, $osDocT, $osDocP]): ?>
        <div class="os-doc" id="os-doc-<?= $osDoc ?>" data-doc="<?= $osDoc ?>">
          <div class="os-doc-top"><span class="os-doc-ic"><?= v2_ic($osDocIc) ?></span><div><b><?= v2_e($osDocT) ?></b><small><?= v2_e($osDocP) ?></small></div><span data-doc-tag></span></div>
          <label class="os-drop" data-drop>
            <input class="sr" type="file" accept=".pdf,.jpg,.jpeg,.png,application/pdf,image/jpeg,image/png" data-doc-input aria-describedby="os-doc-<?= $osDoc ?>-s">
            <span class="os-drop-t"><?= v2_ic('upload-simple') ?><b data-doc-cta><?= v2_te('Choose file') ?></b><span><?= v2_te('or drop it here') ?></span></span>
          </label>
          <p class="os-doc-s" id="os-doc-<?= $osDoc ?>-s" data-doc-status aria-live="polite"></p>
          <a class="os-linkbtn" data-doc-view href="#" target="_blank" rel="noopener" hidden><?= v2_ic('arrow-up-right') ?><?= v2_te('View uploaded document') ?></a>
        </div>
        <?php endforeach; ?>
      </div>
    </div>
  </section>

  <!-- ============ NOTIFICATIONS ============ -->
  <section class="org-panel os-panel" id="os-p-notifications" role="tabpanel" aria-labelledby="os-tab-notifications" hidden>
    <div class="org-panel-head"><div><p class="org-k"><?= v2_te('Notifications') ?></p><h2 class="org-panel-h"><?= v2_te('What you receive and where') ?></h2><p class="org-panel-p"><?= v2_te('We keep you up to date in your account and by email, with nothing to set up.') ?></p></div><a class="org-more" href="/organizator/notificari"><?= v2_te('All notifications') ?><?= v2_ic('arrow-right') ?></a></div>
    <div class="os-cards">
      <article class="os-block"><span class="os-card-ic"><?= v2_ic('bell') ?></span><h3><?= v2_te('In your account, under the bell') ?></h3><p class="os-p"><?= v2_te('They appear in the top bar of your account, with the number of unread ones.') ?></p><ul class="os-chips" id="os-ntypes"><li><span class="org-skel os-sk"></span></li></ul></article>
      <article class="os-block"><span class="os-card-ic"><?= v2_ic('envelope-simple') ?></span><h3><?= v2_te('By email') ?></h3><p class="os-p" id="os-nmail"><?= v2_te('You get an email when a payout request is registered, approved, in processing, paid or rejected.') ?></p></article>
      <article class="os-block"><span class="os-card-ic"><?= v2_ic('clock') ?></span><h3><?= v2_te('Email reports') ?></h3><p class="os-p"><?= v2_t('The email for each sale, the daily sales report and the alerts when tickets run out cannot be chosen from the account yet. You can see your sales at any time in <a href="{url}">Sales</a>.', ['url' => '/organizator/vanzari']) ?></p><p class="os-p"><a href="/organizator/suport"><?= v2_te('Tell us if you need them') ?></a></p></article>
    </div>
  </section>

  <!-- ============ SECURITY ============ -->
  <section class="org-panel os-panel" id="os-p-security" role="tabpanel" aria-labelledby="os-tab-security" hidden>
    <div class="org-panel-head"><div><p class="org-k"><?= v2_te('Security') ?></p><h2 class="org-panel-h"><?= v2_te('Change password') ?></h2><p class="org-panel-p"><?= v2_t('The password of the operator account. Team members have their own passwords, under <a href="{url}">Team</a>.', ['url' => '/organizator/echipa']) ?></p></div></div>
    <div class="os-sec">
      <form class="os-form" id="os-pass-form" novalidate>
        <?php foreach (['cur' => [v2_t('Current password'), 'current-password'], 'new' => [v2_t('New password'), 'new-password'], 'conf' => [v2_t('Confirm new password'), 'new-password']] as $osP => [$osPl, $osPa]): ?>
        <label class="os-f">
          <span class="os-f-l"><?= v2_e($osPl) ?></span>
          <span class="os-pass"><input id="os-pass-<?= $osP ?>" type="password" autocomplete="<?= $osPa ?>" maxlength="128" aria-describedby="os-pass-<?= $osP ?>-err<?= $osP === 'new' ? ' os-rules' : '' ?>"><button class="os-eye" type="button" data-eye="os-pass-<?= $osP ?>" aria-label="<?= v2_te('Show password') ?>" aria-pressed="false"><?= v2_ic('eye') ?></button></span>
          <span class="os-err" id="os-pass-<?= $osP ?>-err" hidden></span>
        </label>
        <?php if ($osP === 'new'): ?>
        <ul class="os-rules" id="os-rules" aria-live="polite">
          <li data-rule="len"><?= v2_ic('check-circle') ?><?= v2_te('At least 8 characters') ?></li>
          <li data-rule="mix"><?= v2_ic('check-circle') ?><?= v2_t('Upper and lower case letters <small>(recommended)</small>') ?></li>
          <li data-rule="num"><?= v2_ic('check-circle') ?><?= v2_t('A digit or a symbol <small>(recommended)</small>') ?></li>
        </ul>
        <?php endif; ?>
        <?php endforeach; ?>
        <div class="os-form-err" id="os-pass-err" role="alert" hidden></div>
        <div class="os-act"><button class="btn btn-primary" type="submit" id="os-pass-go"><?= v2_ic('lock-simple') ?><span data-label><?= v2_te('Change password') ?></span></button></div>
      </form>
      <aside class="os-block os-aside">
        <h3><?= v2_te('A few tips') ?></h3>
        <ul class="os-terms">
          <li><?= v2_ic('check-circle') ?><?= v2_te('Use a password you do not use for any other account.') ?></li>
          <li><?= v2_ic('check-circle') ?><?= v2_te('Do not send it by email or chat. Our team never asks for your password.') ?></li>
          <li><?= v2_ic('check-circle') ?><?= v2_t('For colleagues, add them under <a href="{url}">Team</a> instead of sharing the account.', ['url' => '/organizator/echipa']) ?></li>
        </ul>
      </aside>
    </div>
  </section>

  <!-- ============ SHARE LINKS ============ -->
  <section class="org-panel os-panel" id="os-p-sharelinks" role="tabpanel" aria-labelledby="os-tab-sharelinks" hidden>
    <div class="org-panel-head">
      <div><p class="org-k"><?= v2_te('Share links') ?></p><h2 class="org-panel-h"><?= v2_te('Monitoring links') ?></h2><p class="org-panel-p"><?= v2_te('Unique links that let partners and sponsors see the sales of your experiences in real time.') ?></p></div>
      <button class="btn btn-primary os-sm" type="button" id="os-share-add" disabled><?= v2_ic('plus') ?><?= v2_te('New link') ?></button>
    </div>
    <div class="os-callout">
      <?= v2_ic('info') ?>
      <div><b><?= v2_te('How does it work?') ?></b><p><?= v2_te('You pick one or more experiences and generate a link. Anyone who opens it sees the names of the experiences, the place, the date and time, how many tickets are on sale and how many have been sold. You can add a password, the revenue and the list of participants.') ?></p></div>
    </div>
    <ul class="os-list" id="os-share-list"><li><span class="org-skel os-sk-row"></span></li></ul>
  </section>

  <!-- ============ DIALOGS ============ -->
  <dialog class="os-dialog" id="os-bank-d" aria-labelledby="os-bank-h">
    <form class="os-d-inner" id="os-bank-form" novalidate>
      <div class="os-d-head"><h2 class="os-d-h" id="os-bank-h"><?= v2_te('Add a bank account') ?></h2><?= $osX ?></div>
      <label class="os-f">
        <span class="os-f-l">IBAN</span>
        <input id="os-iban" type="text" maxlength="42" autocomplete="off" spellcheck="false" autocapitalize="characters" placeholder="DE89 3704 0044 0532 0130 00" aria-describedby="os-iban-msg os-iban-err">
        <span class="os-help" id="os-iban-msg" aria-live="polite"></span>
        <span class="os-err" id="os-iban-err" hidden></span>
      </label>
      <?= $osField('os-bname', v2_te('Bank'), 'type="text" maxlength="100" autocomplete="off"') ?>
      <?= $osField('os-holder', v2_te('Account holder'), 'type="text" maxlength="255" autocomplete="off"', v2_te('Exactly as on the bank statement.')) ?>
      <label class="os-f" id="os-issuer-f" hidden>
        <span class="os-f-l"><?= v2_te('Issuing company') ?></span>
        <span class="os-select"><select id="os-issuer"><option value="primary"><?= v2_te('Main company (SC1)') ?></option><option value="secondary"><?= v2_te('Second company (SC2)') ?></option></select><?= v2_ic('caret-down') ?></span>
        <span class="os-help"><?= v2_te('This account receives the takings of this company.') ?></span>
      </label>
      <div class="os-form-err" id="os-bank-err" role="alert" hidden></div>
      <div class="os-d-act"><button class="btn btn-ghost" type="button" data-close><?= v2_te('Cancel') ?></button><button class="btn btn-primary" type="submit" id="os-bank-go"><span data-label><?= v2_te('Add account') ?></span></button></div>
    </form>
  </dialog>

  <dialog class="os-dialog is-wide" id="os-share-d" aria-labelledby="os-share-h">
    <form class="os-d-inner" id="os-share-form" novalidate>
      <div class="os-d-head"><h2 class="os-d-h" id="os-share-h"><?= v2_te('New monitoring link') ?></h2><?= $osX ?></div>
      <?= $osField('os-sname', v2_t('Link name <small>(optional)</small>'), 'type="text" maxlength="100" autocomplete="off" placeholder="' . v2_te('e.g. Link for a sponsor') . '"') ?>
      <label class="os-f">
        <span class="os-f-l"><?= v2_t('Access password <small>(optional)</small>') ?></span>
        <span class="os-pass"><input id="os-spass" type="password" maxlength="100" autocomplete="new-password" aria-describedby="os-spass-help"><button class="os-eye" type="button" data-eye="os-spass" aria-label="<?= v2_te('Show password') ?>" aria-pressed="false"><?= v2_ic('eye') ?></button></span>
        <span class="os-help" id="os-spass-help"><?= v2_te('With a password, visitors enter it before they see the data. Leave empty for open access.') ?></span>
      </label>
      <fieldset class="os-f os-fs-plain">
        <legend class="os-f-l"><?= v2_te('Experiences in the link') ?></legend>
        <span class="os-inline os-psearch" id="os-psearch" hidden><input id="os-pq" type="search" autocomplete="off" placeholder="<?= v2_te('Search for an experience') ?>" aria-label="<?= v2_te('Search for an experience') ?>"></span>
        <ul class="os-picks" id="os-picks" aria-describedby="os-picks-n"><li class="os-picks-msg"><?= v2_te('Loading experiences…') ?></li></ul>
        <span class="os-help" id="os-picks-n" aria-live="polite"></span>
        <span class="os-err" id="os-picks-err" hidden></span>
      </fieldset>
      <label class="os-check"><input type="checkbox" id="os-sp-participants"><span><b><?= v2_te('Show participants') ?></b><small><?= v2_te('The names and phone numbers of participants become visible to anyone who has the link.') ?></small></span></label>
      <label class="os-check"><input type="checkbox" id="os-sp-revenue"><span><b><?= v2_te('Show revenue') ?></b><small><?= v2_te('Net revenue per experience and in total.') ?></small></span></label>
      <div class="os-form-err" id="os-share-err" role="alert" hidden></div>
      <div class="os-d-act"><button class="btn btn-ghost" type="button" data-close><?= v2_te('Cancel') ?></button><button class="btn btn-primary" type="submit" id="os-share-go"><span data-label><?= v2_te('Generate link') ?></span></button></div>
    </form>
  </dialog>

  <dialog class="os-dialog is-doc" id="os-contract-d" aria-labelledby="os-contract-d-h">
    <div class="os-d-inner">
      <div class="os-d-head">
        <div><h2 class="os-d-h" id="os-contract-d-h"><?= v2_te('Your contract') ?></h2><p class="os-d-p" id="os-contract-d-p"><?= v2_te('Read it before you sign it.') ?></p></div>
        <?= $osX ?>
      </div>
      <div class="os-ct-view" id="os-ct-view" hidden>
        <iframe class="os-ct-frame" id="os-ct-frame" title="<?= v2_te('Your contract with {site}', ['site' => SITE_NAME]) ?>" src="about:blank"></iframe>
      </div>
      <div class="os-ct-fallback" id="os-ct-fallback" hidden>
        <span class="os-ct-ic"><?= v2_ic('file-text') ?></span>
        <p class="os-p"><?= v2_te('The contract is a PDF file. On a phone it reads best opened in a new tab or downloaded.') ?></p>
      </div>
      <p class="os-help" id="os-ct-note" hidden><?= v2_te('If the document does not show here, open it in a new tab.') ?></p>
      <div class="os-d-act">
        <a class="btn btn-ghost os-sm" id="os-ct-open" href="#" target="_blank" rel="noopener"><?= v2_ic('arrow-up-right') ?><?= v2_te('Open in a new tab') ?></a>
        <a class="btn btn-ghost os-sm" id="os-ct-dl" href="#" download target="_blank" rel="noopener"><?= v2_ic('download-simple') ?><?= v2_te('Download PDF') ?></a>
        <button class="btn btn-primary os-sm" type="button" data-close><?= v2_te('Close') ?></button>
      </div>
    </div>
  </dialog>

  <dialog class="os-dialog is-small" id="os-del-d" aria-labelledby="os-del-h" aria-describedby="os-del-p">
    <div class="os-d-inner">
      <h2 class="os-d-h" id="os-del-h"><?= v2_te('Delete?') ?></h2>
      <p class="os-d-p" id="os-del-p"></p>
      <div class="os-form-err" id="os-del-err" role="alert" hidden></div>
      <div class="os-d-act"><button class="btn btn-ghost" type="button" data-close><?= v2_te('Cancel') ?></button><button class="btn os-danger" type="button" id="os-del-go"><span data-label><?= v2_te('Delete') ?></span></button></div>
    </div>
  </dialog>
</div>
<?php
v2_org_end();
include __DIR__ . '/../includes/v2/foot.php';
