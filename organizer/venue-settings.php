<?php
/**
 * Venue settings: /organizator/locatie/setari (venue-settings.php), v2 design.
 *
 * First of the settings screens of the "Locație" section, ported from Ambilet's leisure.php (split in three there:
 * this one, the ticket and service editor, and the public page editor). It holds the ticket types with the company
 * that issues each, the sales per issuing company over a period, the two issuing companies themselves (fiscal data,
 * bank account, invoice numbering, VAT; the second one can be switched on) and the access gates of the venue.
 *
 * Ambilet's gate form sent name/code/description while core requires a type and takes a location, so adding a gate
 * never worked there; this form follows core.
 *
 * org-venue-settings.js reads /organizer/events, then .../leisure/config, .../leisure/reports/by-issuer,
 * .../leisure/issuers (and saves it) and /organizer/venues/{venue}/gates (and changes them).
 */

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/api.php';
require_once __DIR__ . '/../includes/nav-helpers.php';
require_once __DIR__ . '/../includes/v2/helpers.php';
require_once __DIR__ . '/../includes/v2/organizer.php';

$pageTitleRaw = 'Setări locație — ' . SITE_NAME;
$pageDescription = 'Societățile care emit biletele locației, vânzările pe fiecare și porțile de acces.';
$canonicalUrl = SITE_URL . '/organizator/locatie/setari';
$noindex = true;
$skipPageCache = true;

$v2Styles = ['organizer.css', 'org-venue.css'];
$v2Scripts = ['organizer.js', 'org-venue-settings.js'];
$v2LegacyScripts = ['assets/js/config.js', 'assets/js/utils.js', 'assets/js/api.js', 'assets/js/auth.js'];
$v2HeadExtra = v2_account_client_config('organizer');

/** One issuing company's form. Field names follow the core contract. */
$vxForm = function (string $key) {
    $f = function (string $field, string $label, string $attrs = '', string $cls = '', string $inputCls = '') use ($key) {
        $id = 'vx-' . $key . '-' . $field;
        return '<span class="po-field' . ($cls ? ' ' . $cls : '') . '"><label for="' . $id . '">' . $label . '</label>'
            . '<input class="po-input' . ($inputCls ? ' ' . $inputCls : '') . '" id="' . $id . '" data-f="' . $field . '" ' . $attrs . '></span>';
    };
    return '<div class="ve-form" data-form="' . $key . '">'
        . $f('name', 'Denumirea societății' . ($key === 'primary' ? ' *' : ''), 'maxlength="255" autocomplete="organization"', 'is-wide')
        . $f('tax_id', 'CUI / CIF' . ($key === 'primary' ? ' *' : ''), 'maxlength="32" autocomplete="off"')
        . $f('registration', 'Nr. Registrul Comerțului', 'maxlength="32" autocomplete="off"')
        . $f('address', 'Adresa', 'maxlength="1024" autocomplete="street-address"', 'is-wide')
        . $f('city', 'Localitatea', 'maxlength="100"')
        . $f('county', 'Județul', 'maxlength="100"')
        . $f('zip', 'Cod poștal', 'maxlength="20" inputmode="numeric"')
        . '<p class="ve-form-k is-wide">Cont bancar</p>'
        . $f('bank_name', 'Banca', 'maxlength="255"')
        . $f('iban', 'IBAN', 'maxlength="34" autocomplete="off"', '', 've-mono ve-upper')
        . '<p class="ve-form-k is-wide">Numerotarea facturilor</p>'
        . $f('invoice_series', 'Seria facturii', 'maxlength="16" autocomplete="off"', '', 've-mono ve-upper')
        . '<span class="po-field"><label for="vx-' . $key . '-next_invoice_number">Următorul număr de factură</label>'
        . '<input class="po-input ve-mono" id="vx-' . $key . '-next_invoice_number" data-f="next_invoice_number" type="number" min="1" max="9999999" inputmode="numeric">'
        . '<span class="ve-hint" data-next-hint>Gol = numerotarea continuă.</span></span>'
        . '<p class="ve-form-k is-wide">TVA</p>'
        . '<label class="po-check is-wide"><input type="checkbox" data-f="vat_payer" id="vx-' . $key . '-vat_payer"><span>Societatea este plătitoare de TVA</span></label>'
        . '<span class="po-field" data-vat-rate><label for="vx-' . $key . '-vat_rate">Cota TVA (%)</label>'
        . '<input class="po-input ve-mono" id="vx-' . $key . '-vat_rate" data-f="vat_rate" type="number" step="0.01" min="0" max="100" inputmode="decimal" placeholder="19"></span>'
        . '</div>';
};

include __DIR__ . '/../includes/v2/head.php';
v2_org_start('venue-settings');
?>
<div class="ve" id="ve">
  <header class="ve-head">
    <div>
      <p class="ve-eyebrow"><?= v2_ic('gear-six') ?>Locație · Setări</p>
      <h1 class="ve-h">Setări locație</h1>
      <p class="ve-lead">Cine emite biletele și facturile, cât a vândut fiecare societate și porțile pe unde se intră.</p>
    </div>
    <div class="ve-head-tools">
      <span class="po-field"><label for="ve-event">Locația</label><span class="po-select"><select id="ve-event" disabled><option>Se încarcă…</option></select><?= v2_ic('caret-down') ?></span></span>
    </div>
  </header>

  <div class="org-empty" id="ve-none" hidden>
    <span class="org-empty-ic"><?= v2_ic('door-open') ?></span>
    <b>Nicio locație pregătită</b>
    <p>Paginile de locație funcționează pe o activitate configurată ca locație de agrement.</p>
    <a class="btn btn-primary" href="/organizator/suport">Cere activarea<?= v2_ic('arrow-right') ?></a>
  </div>

  <div class="org-empty is-error" id="ve-failed" hidden>
    <span class="org-empty-ic"><?= v2_ic('warning-circle') ?></span>
    <b>Nu am putut încărca setările</b>
    <p>Reîncearcă în câteva secunde.</p>
    <button class="btn btn-primary" type="button" id="ve-retry">Reîncearcă</button>
  </div>

  <div class="ve-main" id="ve-main" hidden>
    <section class="org-panel" aria-labelledby="vx-types-h">
      <div class="org-panel-head"><div><h2 class="org-panel-h" id="vx-types-h">Tipurile de bilete și emitentul lor</h2>
        <p class="org-panel-p">Fiecare bilet e emis de societatea principală sau de cea secundară.</p></div></div>
      <div class="ve-table-wrap"><table class="ve-table ve-types-table">
        <thead><tr><th scope="col">Bilet</th><th scope="col">Categorie</th><th scope="col" class="ve-r">Locuri pe zi</th><th scope="col">Emitent</th><th scope="col">Stare</th></tr></thead>
        <tbody id="vx-types"><tr><td colspan="5" class="ve-state">Se încarcă…</td></tr></tbody>
      </table></div>
    </section>

    <section class="org-panel" aria-labelledby="vx-rep-h">
      <div class="org-panel-head">
        <div><h2 class="org-panel-h" id="vx-rep-h">Vânzări pe societate</h2><p class="org-panel-p" id="vx-rep-period">—</p></div>
        <span class="ve-dates">
          <span class="po-field"><label class="ve-sr" for="vx-from">De la</label><input class="po-input" type="date" id="vx-from"></span>
          <span class="po-field"><label class="ve-sr" for="vx-to">Până la</label><input class="po-input" type="date" id="vx-to"></span>
          <button class="btn btn-ghost" type="button" id="vx-apply">Aplică</button>
        </span>
      </div>
      <div class="ve-issuers" id="vx-rep"><p class="ve-state">Se încarcă…</p></div>
    </section>

    <section class="org-panel" aria-labelledby="vx-iss-h">
      <div class="org-panel-head"><div><h2 class="org-panel-h" id="vx-iss-h">Societățile emitente</h2>
        <p class="org-panel-p">Datele fiscale, contul bancar, numerotarea facturilor și TVA-ul fiecărei societăți.</p></div></div>
      <p class="ve-warn"><?= v2_ic('warning-circle') ?><span>Societatea principală este chiar firma contului tău: denumirea, CUI-ul și IBAN-ul de aici sunt aceleași ca în <a href="/organizator/setari">Cont &amp; companie</a>. Un IBAN schimbat aici schimbă contul în care primești deconturile.</span></p>
      <div class="ve-iss-forms" id="vx-iss" hidden>
        <article class="ve-issuer" data-company="primary">
          <div class="ve-iss-head">
            <p class="ve-issuer-k"><?= v2_ic('buildings') ?>Societatea principală</p>
            <button class="btn btn-primary" type="button" data-save="primary"><?= v2_ic('check') ?>Salvează</button>
          </div>
          <?= $vxForm('primary') ?>
        </article>
        <article class="ve-issuer is-second" data-company="secondary">
          <div class="ve-iss-head">
            <p class="ve-issuer-k"><?= v2_ic('buildings') ?>Societatea secundară</p>
            <label class="po-check"><input type="checkbox" id="vx-sec-on"><span>Folosesc a doua societate</span></label>
            <button class="btn btn-primary" type="button" data-save="secondary"><?= v2_ic('check') ?>Salvează</button>
          </div>
          <?= $vxForm('secondary') ?>
        </article>
      </div>
      <p class="ve-state" id="vx-iss-state">Se încarcă…</p>
    </section>

    <section class="org-panel" aria-labelledby="vx-gates-h">
      <div class="org-panel-head">
        <div><h2 class="org-panel-h" id="vx-gates-h">Porțile de acces</h2><p class="org-panel-p" id="vx-venue-line">Punctele pe unde se scanează biletele la intrare.</p></div>
        <button class="btn btn-primary" type="button" id="vx-gate-add"><?= v2_ic('plus') ?>Adaugă o poartă</button>
      </div>
      <ul class="ve-gates" id="vx-gates"><li class="ve-state">Se încarcă…</li></ul>
    </section>
  </div>

  <div class="ve-modal" id="vx-modal" role="dialog" aria-modal="true" aria-labelledby="vx-modal-h" hidden>
    <div class="ve-modal-card">
      <div class="ve-modal-head ve-modal-plain"><h2 id="vx-modal-h">Adaugă o poartă</h2></div>
      <div class="ve-modal-body">
        <span class="po-field"><label for="vx-g-name">Numele porții *</label><input class="po-input" id="vx-g-name" maxlength="255" placeholder="Poarta A, Intrarea principală…" autocomplete="off"></span>
        <div class="ve-two">
          <span class="po-field"><label for="vx-g-type">Tipul</label><span class="po-select"><select id="vx-g-type">
            <option value="entry">Intrare</option><option value="exit">Ieșire</option><option value="vip">VIP</option><option value="pos">Casă / POS</option>
          </select><?= v2_ic('caret-down') ?></span></span>
          <span class="po-field"><label for="vx-g-loc">Unde se află</label><input class="po-input" id="vx-g-loc" maxlength="255" placeholder="Parcare nord, lângă casă…" autocomplete="off"></span>
        </div>
        <label class="po-check" id="vx-g-active-wrap" hidden><input type="checkbox" id="vx-g-active"><span>Poarta este în funcțiune</span></label>
      </div>
      <div class="ve-modal-foot">
        <button class="ve-danger" type="button" id="vx-g-del" hidden><?= v2_ic('trash') ?><span>Șterge poarta</span></button>
        <button class="btn btn-ghost" type="button" id="vx-g-cancel">Renunță</button>
        <button class="btn btn-primary" type="button" id="vx-g-save"><?= v2_ic('check') ?>Salvează</button>
      </div>
    </div>
  </div>
</div>
<?php
v2_org_end();
include __DIR__ . '/../includes/v2/foot.php';
