<?php
/**
 * Venue tickets and services: /organizator/locatie/bilete (venue-products.php), v2 design.
 *
 * Second of the settings screens of the "Locație" section, ported from the "Produse" tab of Ambilet's leisure.php.
 * Everything a venue sells: the display categories the public page groups tickets by (with a picture and Hungarian
 * and English names), and the products themselves in their order. A product carries its prices (online and at the
 * counter), stock, per-order quantities, group-ticket rules, picture, texts and translations, and, depending on what
 * it is, duration variants, add-ons, timed departures, a physical inventory, package components with the share of
 * the price each issuing company gets, the access ticket it needs and informative blocked hours.
 *
 * org-venue-products.js reads /organizer/events, then .../leisure/products and .../leisure/config; it saves products
 * (POST/PUT/DELETE .../leisure/products), their order (.../products/reorder), the categories (PUT .../venue-config)
 * and pictures (multipart .../leisure/upload-image).
 */

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/api.php';
require_once __DIR__ . '/../includes/nav-helpers.php';
require_once __DIR__ . '/../includes/v2/helpers.php';
require_once __DIR__ . '/../includes/v2/organizer.php';

$pageTitleRaw = 'Bilete și servicii — Locație — ' . SITE_NAME;
$pageDescription = 'Biletele și serviciile locației: prețuri online și la casă, stoc, variante, pachete și categoriile de afișare.';
$canonicalUrl = SITE_URL . '/organizator/locatie/bilete';
$noindex = true;
$skipPageCache = true;

$v2Styles = ['organizer.css', 'org-venue.css'];
$v2Scripts = ['organizer.js', 'org-venue-products.js'];
$v2LegacyScripts = ['assets/js/config.js', 'assets/js/utils.js', 'assets/js/api.js', 'assets/js/auth.js'];
$v2HeadExtra = v2_account_client_config('organizer');

$fld = function (string $id, string $label, string $input, string $hint = '', string $cls = '') {
    return '<span class="po-field' . ($cls ? ' ' . $cls : '') . '"><label for="' . $id . '">' . $label . '</label>' . $input
        . ($hint ? '<span class="ve-hint">' . $hint . '</span>' : '') . '</span>';
};
$inp = function (string $id, string $attrs = '', string $cls = '') {
    return '<input class="po-input' . ($cls ? ' ' . $cls : '') . '" id="' . $id . '" ' . $attrs . '>';
};
$sel = function (string $id, array $options) {
    $o = '';
    foreach ($options as $v => $l) $o .= '<option value="' . $v . '">' . $l . '</option>';
    return '<span class="po-select"><select id="' . $id . '">' . $o . '</select>' . v2_ic('caret-down') . '</span>';
};
$tr = function (string $lang, string $langName) {
    $f = function (string $field, string $label, bool $area = false) use ($lang) {
        $id = 'vi-tr-' . $lang . '-' . $field;
        $ctrl = $area
            ? '<textarea class="ve-ta" id="' . $id . '" data-tr="' . $field . '" data-lang="' . $lang . '" rows="2"></textarea>'
            : '<input class="po-input" id="' . $id . '" data-tr="' . $field . '" data-lang="' . $lang . '">';
        return '<span class="po-field"><label for="' . $id . '">' . $label . '</label>' . $ctrl . '</span>';
    };
    return '<div class="ve-form" data-tr-pane="' . $lang . '"' . ($lang === 'en' ? ' hidden' : '') . '>'
        . $f('name', 'Nume (' . $langName . ')')
        . $f('unit_label', 'Unitate de preț (' . $langName . ')')
        . '<span class="is-wide">' . $f('description', 'Descriere scurtă (' . $langName . ')', true) . '</span>'
        . '<span class="is-wide">' . $f('includes', 'Include, câte unul pe rând (' . $langName . ')', true) . '</span>'
        . '<span class="is-wide">' . $f('usage_terms', 'Termeni de utilizare (' . $langName . ')', true) . '</span>'
        . '</div>';
};

include __DIR__ . '/../includes/v2/head.php';
v2_org_start('venue-products');
?>
<div class="ve" id="ve">
  <header class="ve-head">
    <div>
      <p class="ve-eyebrow"><?= v2_ic('ticket') ?>Locație · Bilete și servicii</p>
      <h1 class="ve-h">Bilete și servicii</h1>
      <p class="ve-lead">Tot ce vinde locația, online și la casă: prețuri, stoc, variante, pachete și cum apar pe pagina publică.</p>
    </div>
    <div class="ve-head-tools">
      <span class="po-field"><label for="ve-event">Locația</label><span class="po-select"><select id="ve-event" disabled><option>Se încarcă…</option></select><?= v2_ic('caret-down') ?></span></span>
      <button class="btn btn-primary" type="button" id="vi-add"><?= v2_ic('plus') ?>Adaugă un produs</button>
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
    <b>Nu am putut încărca produsele</b>
    <p>Reîncearcă în câteva secunde.</p>
    <button class="btn btn-primary" type="button" id="ve-retry">Reîncearcă</button>
  </div>

  <div class="ve-main" id="ve-main" hidden>
    <section class="org-panel ve-hist" aria-labelledby="vi-cats-h">
      <div class="org-panel-head">
        <div><h2 class="org-panel-h" id="vi-cats-h">Categoriile de afișare <span class="ve-sub" id="vi-cats-n"></span></h2>
        <p class="org-panel-p">Grupează biletele pe pagina publică, de pildă „Bilete individuale” sau „Pachete de familie”. Produsele fără categorie apar la „Alte produse”.</p></div>
        <button class="btn btn-ghost ve-hist-btn" type="button" id="vi-cats-btn" aria-expanded="false" aria-controls="vi-cats-body">Arată categoriile<?= v2_ic('caret-down') ?></button>
      </div>
      <div id="vi-cats-body" hidden>
        <ul class="ve-cats" id="vi-cats"></ul>
        <div class="ve-cat-add">
          <span class="po-field"><label for="vi-cat-new">Categorie nouă</label><input class="po-input" id="vi-cat-new" maxlength="80" placeholder="Bilete individuale" autocomplete="off"></span>
          <button class="btn btn-ghost" type="button" id="vi-cat-add"><?= v2_ic('plus') ?>Adaug-o</button>
          <button class="btn btn-primary" type="button" id="vi-cats-save"><?= v2_ic('check') ?>Salvează categoriile</button>
        </div>
      </div>
    </section>

    <section class="org-panel" aria-labelledby="vi-list-h">
      <div class="org-panel-head"><div><h2 class="org-panel-h" id="vi-list-h">Produsele</h2>
        <p class="org-panel-p">În ordinea în care apar. Săgețile mută un produs în cadrul categoriei lui.</p></div></div>
      <div class="ve-prods" id="vi-list"><p class="ve-state">Se încarcă…</p></div>
    </section>
  </div>

  <div class="ve-modal" id="vi-modal" role="dialog" aria-modal="true" aria-labelledby="vi-modal-h" hidden>
    <div class="ve-modal-card ve-modal-xl">
      <div class="ve-modal-head ve-modal-plain"><h2 id="vi-modal-h">Adaugă un produs</h2></div>
      <div class="ve-modal-body">
        <div class="ve-form">
          <?= $fld('vi-name', 'Numele *', $inp('vi-name', 'maxlength="160" autocomplete="off" placeholder="Bilet adult, Parcare auto, Barcă…"'), 'Așa apare pe pagina publică și pe bilet.', 'is-wide') ?>
          <?= $fld('vi-cat', 'Ce fel de produs', $sel('vi-cat', ['access' => 'Acces (bilet de intrare)', 'parking' => 'Parcare', 'rental' => 'Închiriere de echipament', 'activity' => 'Activitate cu operator', 'extra' => 'Extra', 'package' => 'Pachet (mai multe produse)'])) ?>
          <?= $fld('vi-issuer', 'Societatea emitentă', $sel('vi-issuer', ['primary' => 'Principală', 'secondary' => 'Secundară', 'mix' => 'Ambele (doar la pachete)']), '<span id="vi-mix-hint" hidden>La „Ambele”, împarte prețul pachetului pe componente, mai jos.</span>') ?>
          <?= $fld('vi-group', 'Categoria de afișare', $sel('vi-group', ['' => 'Alte produse'])) ?>
          <?= $fld('vi-sku', 'Cod intern (SKU)', $inp('vi-sku', 'maxlength="64" autocomplete="off"', 've-mono')) ?>
          <?= $fld('vi-price', 'Prețul online (lei) *', $inp('vi-price', 'type="number" min="0" max="999999" step="0.01" inputmode="decimal"')) ?>
          <?= $fld('vi-pos-price', 'Prețul la casă (lei)', $inp('vi-pos-price', 'type="number" min="0" max="999999" step="0.01" inputmode="decimal" placeholder="gol = prețul online"'), 'Un preț aici îl face vizibil și în POS.') ?>
          <?= $fld('vi-capacity', 'Stoc total', $inp('vi-capacity', 'type="number" min="0" inputmode="numeric" placeholder="gol = nelimitat"')) ?>
          <?= $fld('vi-daily', 'Locuri pe zi', $inp('vi-daily', 'type="number" min="0" inputmode="numeric" placeholder="gol = fără limită"')) ?>
          <?= $fld('vi-min', 'Cantitatea minimă în coș', $inp('vi-min', 'type="number" min="1" inputmode="numeric" placeholder="1"')) ?>
          <?= $fld('vi-max', 'Cantitatea maximă în coș', $inp('vi-max', 'type="number" min="0" inputmode="numeric" placeholder="gol = fără limită"')) ?>
          <?= $fld('vi-step', 'Pasul la + și −', $inp('vi-step', 'type="number" min="1" inputmode="numeric" placeholder="1"')) ?>
          <?= $fld('vi-duration', 'Durata serviciului (minute)', $inp('vi-duration', 'type="number" min="0" max="1440" inputmode="numeric"')) ?>
          <?= $fld('vi-icon', 'Iconița (un emoji)', $inp('vi-icon', 'maxlength="6" autocomplete="off" placeholder="🎟️"')) ?>
          <?= $fld('vi-unit', 'Unitatea de preț', $inp('vi-unit', 'maxlength="60" autocomplete="off" placeholder="bilet, persoană, mașină / 3 ore"')) ?>
        </div>

        <div class="ve-sec ve-sec-warm">
          <label class="po-check"><input type="checkbox" id="vi-is-group"><span><b>Bilet de grup</b> — prețul afișat devine „de la” cantitatea minimă × preț.</span></label>
          <div class="ve-form" id="vi-group-extra" hidden>
            <label class="po-check is-wide"><input type="checkbox" id="vi-guide"><span>La cantitatea minimă se emite gratuit un bilet pentru ghid</span></label>
            <?= $fld('vi-guide-label', 'Numele biletului de ghid', $inp('vi-guide-label', 'maxlength="80" placeholder="Ghid grup"')) ?>
          </div>
        </div>

        <div class="ve-sec">
          <p class="ve-sec-k">Imaginea cardului</p>
          <div class="ve-image" id="vi-image-box">
            <img id="vi-image-thumb" alt="" hidden>
            <span class="ve-sub" id="vi-image-empty">JPG, PNG sau WebP, cel mult 10 MB. Recomandat 800 × 600.</span>
            <span class="ve-image-tools">
              <label class="btn btn-ghost" for="vi-image-file"><?= v2_ic('plus') ?><span id="vi-image-label">Alege o imagine</span></label>
              <input type="file" id="vi-image-file" accept="image/jpeg,image/png,image/webp" class="ve-sr">
              <button class="btn btn-ghost" type="button" id="vi-image-rm" hidden><?= v2_ic('trash') ?>Scoate imaginea</button>
            </span>
          </div>
        </div>

        <div class="ve-form">
          <span class="po-field is-wide"><label for="vi-desc">Descriere scurtă</label><textarea class="ve-ta" id="vi-desc" rows="2" maxlength="2000"></textarea></span>
          <span class="po-field is-wide"><label for="vi-includes">Include, câte unul pe rând</label><textarea class="ve-ta" id="vi-includes" rows="3" placeholder="Acces toată ziua&#10;Hartă tipărită"></textarea></span>
          <span class="po-field is-wide"><label for="vi-terms">Termeni de utilizare</label><textarea class="ve-ta" id="vi-terms" rows="2" maxlength="10000"></textarea></span>
        </div>

        <div class="ve-sec">
          <button class="ve-sec-toggle" type="button" id="vi-tr-btn" aria-expanded="false" aria-controls="vi-tr-body"><?= v2_ic('caret-down') ?>Traduceri în maghiară și engleză <span class="ve-sub">— doar ce vrei; câmpurile goale rămân în română</span></button>
          <div id="vi-tr-body" hidden>
            <div class="ve-tabs" role="tablist">
              <button type="button" role="tab" class="ve-tab" data-tab="hu" aria-selected="true">Maghiară</button>
              <button type="button" role="tab" class="ve-tab" data-tab="en" aria-selected="false">Engleză</button>
            </div>
            <?= $tr('hu', 'HU') ?>
            <?= $tr('en', 'EN') ?>
          </div>
        </div>

        <div class="ve-sec" data-when="rental activity">
          <div class="ve-sec-head"><p class="ve-sec-k">Variante de durată și preț</p><button class="btn btn-ghost" type="button" data-add="variant"><?= v2_ic('plus') ?>Variantă</button></div>
          <p class="ve-sub">Aceeași unitate fizică, prețuri diferite pe durată. Fiecare rezervare consumă o unitate, oricare ar fi varianta.</p>
          <div class="ve-rows" id="vi-variants"></div>
        </div>

        <div class="ve-sec" data-when="access rental activity">
          <div class="ve-sec-head"><p class="ve-sec-k">Add-on-uri opționale</p><button class="btn btn-ghost" type="button" data-add="addon"><?= v2_ic('plus') ?>Add-on</button></div>
          <p class="ve-sub">„Incluse” = câte sunt gratuite la fiecare bilet. „Plătite maxim” = câte se mai pot adăuga contra cost.</p>
          <div class="ve-rows" id="vi-addons"></div>
        </div>

        <div class="ve-sec ve-sec-cool" data-when="rental activity">
          <label class="po-check"><input type="checkbox" id="vi-slots-on"><span><b>Curse la ore fixe</b> (rezervare pe sloturi)</span></label>
          <div class="ve-form ve-form-4" id="vi-slots" hidden>
            <?= $fld('vi-slot-first', 'Prima cursă', $inp('vi-slot-first', 'type="time"')) ?>
            <?= $fld('vi-slot-last', 'Ultima cursă', $inp('vi-slot-last', 'type="time"')) ?>
            <?= $fld('vi-slot-interval', 'La fiecare (min)', $inp('vi-slot-interval', 'type="number" min="5" inputmode="numeric" placeholder="30"')) ?>
            <?= $fld('vi-slot-duration', 'O cursă durează (min)', $inp('vi-slot-duration', 'type="number" min="5" inputmode="numeric" placeholder="30"')) ?>
            <?= $fld('vi-slot-capacity', 'Locuri pe cursă', $inp('vi-slot-capacity', 'type="number" min="1" inputmode="numeric" placeholder="14"')) ?>
            <?= $fld('vi-slot-pricing', 'Se vinde', $sel('vi-slot-pricing', ['per_person' => 'Pe persoană', 'per_slot' => 'Pe cursă întreagă'])) ?>
          </div>
        </div>

        <div class="ve-sec ve-sec-warm" data-when="rental activity">
          <label class="po-check"><input type="checkbox" id="vi-phys-on"><span><b>Inventar fizic</b> — o unitate nu poate fi în două intervale care se suprapun</span></label>
          <div class="ve-form" id="vi-phys" hidden>
            <?= $fld('vi-phys-count', 'Câte unități are locația', $inp('vi-phys-count', 'type="number" min="1" inputmode="numeric" placeholder="10"'), 'De pildă 10 bărci.') ?>
          </div>
        </div>

        <div class="ve-sec" data-when="package">
          <div class="ve-sec-head"><p class="ve-sec-k">Ce conține pachetul</p>
            <span class="ve-sec-tools"><button class="btn btn-ghost" type="button" id="vi-alloc"><?= v2_ic('percent') ?>Împarte prețul automat</button><button class="btn btn-ghost" type="button" data-add="package"><?= v2_ic('plus') ?>Componentă</button></span></div>
          <p class="ve-sub">La cumpărare, pachetul emite aceste bilete. Prețul pachetului e cel de mai sus; economia față de componente se calculează singură.</p>
          <div class="ve-rows" id="vi-package"></div>
          <p class="ve-note-line" id="vi-pkg-sum" hidden></p>
        </div>

        <div class="ve-sec">
          <p class="ve-sec-k">Opțiuni</p>
          <div class="ve-checks">
            <label class="po-check"><input type="checkbox" id="vi-active"><span>Activ</span></label>
            <label class="po-check" data-when="access parking rental activity extra"><input type="checkbox" id="vi-parking"><span>Este parcare</span></label>
            <label class="po-check" data-when="access parking rental activity extra"><input type="checkbox" id="vi-vehicle"><span>Cere numărul mașinii</span></label>
            <label class="po-check" data-when="access"><input type="checkbox" id="vi-child"><span>Bilet de copil (gratuit)</span></label>
            <label class="po-check"><input type="checkbox" id="vi-pos-only"><span>Doar la casă (ascuns online)</span></label>
          </div>
        </div>

        <div class="ve-form" data-when="rental activity">
          <?= $fld('vi-access-req', 'Cere un bilet de acces', $sel('vi-access-req', ['none' => 'Nu', 'any' => 'Da — orice bilet de acces', 'adult_only' => 'Da — doar bilet de adult']), 'Vaporaș: orice bilet (unul la pasager). Barcă: doar adult (un adult la barcă).', 'is-wide') ?>
        </div>

        <div class="ve-sec" data-when="rental activity">
          <div class="ve-sec-head"><p class="ve-sec-k">Ore blocate (informativ)</p><button class="btn btn-ghost" type="button" data-add="block"><?= v2_ic('plus') ?>Interval</button></div>
          <p class="ve-sub">Apare ca anunț pe pagina publică și în POS, de pildă pentru un grup privat. Nu oprește vânzarea.</p>
          <div class="ve-rows" id="vi-blocks"></div>
        </div>
      </div>
      <div class="ve-modal-foot">
        <button class="ve-danger" type="button" id="vi-del" hidden><?= v2_ic('trash') ?><span>Șterge produsul</span></button>
        <button class="btn btn-ghost" type="button" id="vi-cancel">Renunță</button>
        <button class="btn btn-primary" type="button" id="vi-save"><?= v2_ic('check') ?>Salvează</button>
      </div>
    </div>
  </div>
</div>
<?php
v2_org_end();
include __DIR__ . '/../includes/v2/foot.php';
