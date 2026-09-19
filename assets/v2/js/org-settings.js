/* bilete.online v2: organizer account settings (/organizator/setari#tab). Seven tabs on base.js [data-tabs], the open tab
   kept in the address. Profile, companies and guarantor from /organizer/me; bank accounts; the contract with the
   electronic signature and the two documents; notification types; the password; share links with the activity picker.
   Every part loads on its own and a failing part never blocks the others; forms stay locked until their data is in, so
   a save can't overwrite what the account already has. Runs inside the organizer shell (window.BO_ORG); text from the
   API is always written as text. */
(function () {
  'use strict';
  var O = window.BO_ORG, root = document.getElementById('os');
  if (!O || !root) return;
  var F = O.fmt, el = O.el, icon = O.icon;
  var $ = function (id) { return document.getElementById(id); };
  function qsa(sel, ctx) { return [].slice.call((ctx || document).querySelectorAll(sel)); }
  function has(obj, k) { return Object.prototype.hasOwnProperty.call(obj, k); }

  var TABS = ['profile', 'company', 'bank', 'contract', 'notifications', 'security', 'sharelinks'];
  var BANKS = { BTRL: 'Banca Transilvania', BRDE: 'BRD', RNCB: 'BCR', INGB: 'ING Bank', RZBR: 'Raiffeisen Bank', BACX: 'UniCredit Bank', CECE: 'CEC Bank', PIRB: 'First Bank', UGBI: 'Garanti BBVA', LIBR: 'Libra Internet Bank', OTPV: 'OTP Bank', BUCU: 'Alpha Bank', CARP: 'Patria Bank', EXIM: 'Exim Banca Românească', TREZ: 'Trezoreria Statului', NBOR: 'Banca Națională a României' };
  var ID_TYPES = { ci: 'Carte de identitate', passport: 'Pașaport' };
  var WORK = { exclusive: ['Exclusiv', 'Vinzi bilete doar prin bilete.online.'], non_exclusive: ['Neexclusiv', 'Vinzi bilete și pe alte platforme.'] };
  var MODE = { included: ['Inclus în preț', 'Comisionul este cuprins în prețul afișat al biletului.'], added_on_top: ['Adăugat peste preț', 'Clientul plătește comisionul peste prețul biletului.'], on_top: ['Adăugat peste preț', 'Clientul plătește comisionul peste prețul biletului.'] };
  var TERMS = {
    'Comisionul se aplica doar biletelor vandute': 'Comisionul se aplică doar biletelor vândute.',
    'Plata comisionului se face automat la procesarea platilor': 'Plata comisionului se face automat, la procesarea plăților.',
    'Nu exista costuri fixe sau abonamente lunare': 'Nu există costuri fixe sau abonamente lunare.',
    'Decontarea se face in maxim 7 zile lucratoare dupa eveniment': 'Decontarea se face în maximum 7 zile lucrătoare după eveniment.',
  };
  var NTYPES = { 'Vanzari bilete': 'Vânzări bilete', 'Cereri rambursare': 'Cereri de rambursare', 'Comenzi servicii': 'Comenzi de servicii', 'Cereri plată': 'Cereri de plată' };
  var NTYPES_FALLBACK = ['Vânzări bilete', 'Cereri de rambursare', 'Documente generate', 'Comenzi de servicii', 'Servicii pornite și finalizate', 'Facturi și rezultate servicii', 'Cereri de plată', 'Plăți aprobate, în procesare, plătite sau respinse'];
  var DOC_KEY = { id_card: 'id_card', cui_document: 'cui' };
  var DOC_NAME = { id_card: 'copia CI', cui_document: 'certificatul CUI' };
  var MAX_LINK_EVENTS = 20;

  var me, accounts, contract, links, events = null, eventsReq = null; // undefined = still loading, null = failed
  var opener = null, openerKey = null, fallbackFocus = null, delTarget = null, picked = {};

  /* =================== HELPERS =================== */
  function val(id) { var n = $(id); return n ? String(n.value || '').trim() : ''; }
  function setVal(id, v) { var n = $(id); if (n) n.value = v == null ? '' : String(v); }
  function norm(s) { return String(s == null ? '' : s).normalize('NFD').replace(/[̀-ͯ]/g, '').toLowerCase(); }
  function naiveDay(v) { var m = /^(\d{4}-\d{2}-\d{2})/.exec(String(v || '')); return m ? m[1] : ''; }
  function dayLabel(v) { var d = F.dateOf(naiveDay(v) || v); return d ? F.date(d, { day: 'numeric', month: 'long', year: 'numeric' }) : ''; }
  function stamp(iso) { var d = F.dateOf(iso); return d ? F.date(d, { day: 'numeric', month: 'long', year: 'numeric', hour: '2-digit', minute: '2-digit' }) : ''; }
  function tag(text, cls) { return el('span', { class: 'org-tag ' + (cls || ''), text: text }); }
  function safeUrl(u) { return typeof u === 'string' && /^https?:\/\//i.test(u) ? u : ''; }
  function apiUrl() { return (window.BILETEONLINE && window.BILETEONLINE.apiUrl) || '/api/proxy.php'; }
  function token() { return typeof BileteOnlineAuth !== 'undefined' && BileteOnlineAuth.getToken ? BileteOnlineAuth.getToken() : null; }
  function tidyPlace(s) {
    s = String(s || '').trim().replace(/ş/g, 'ș').replace(/ţ/g, 'ț').replace(/Ş/g, 'Ș').replace(/Ţ/g, 'Ț');
    if (s && s === s.toUpperCase()) s = s.toLowerCase().replace(/(^|[\s\-.(])(\p{L})/gu, function (m, a, b) { return a + b.toUpperCase(); });
    return s;
  }
  function emptyBox(box, tagName, text, retry) {
    box.textContent = '';
    var p = el(tagName || 'p', { class: 'os-empty-p' }, [text]);
    if (retry) { var b = el('button', { type: 'button', text: 'Reîncearcă' }); b.addEventListener('click', retry); p.appendChild(b); }
    box.appendChild(p);
  }
  function copyText(text) {
    if (navigator.clipboard && window.isSecureContext) return navigator.clipboard.writeText(text);
    return new Promise(function (resolve, reject) {
      var ta = el('textarea', { class: 'sr', readonly: true });
      ta.value = text;
      document.body.appendChild(ta);
      ta.select();
      var ok = false;
      try { ok = document.execCommand('copy'); } catch (e) { ok = false; }
      ta.remove();
      if (ok) resolve(); else reject(new Error('copy'));
    });
  }
  function busyBtn(btn, on, text) {
    var l = btn.querySelector('[data-label]');
    if (on) { btn.setAttribute('aria-busy', 'true'); if (l) { btn.setAttribute('data-idle', l.textContent); l.textContent = text; } }
    else { btn.removeAttribute('aria-busy'); if (l && btn.hasAttribute('data-idle')) l.textContent = btn.getAttribute('data-idle'); btn.removeAttribute('data-idle'); }
  }
  function isBusy(btn) { return btn.getAttribute('aria-busy') === 'true'; }
  function fieldErr(id, msg) {
    var f = $(id), e = $(id + '-err');
    if (e) { e.textContent = msg || ''; e.hidden = !msg; }
    if (f) { if (msg) f.setAttribute('aria-invalid', 'true'); else f.removeAttribute('aria-invalid'); }
  }
  function formErr(id, msg) { var b = $(id); if (b) { b.textContent = msg || ''; b.hidden = !msg; } }
  function errMessage(err) { return String((err && (err.message || (err.data && err.data.message))) || ''); }
  function saveError(err) {
    var s = err && err.status;
    if (s === 422) return 'Unele câmpuri nu sunt completate corect. Verifică-le și încearcă din nou.';
    if (s === 429) return 'Prea multe încercări într-un timp scurt. Așteaptă un minut și încearcă din nou.';
    if (s === 403) return 'Contul nu are voie să facă această schimbare. Scrie-ne dacă e o greșeală.';
    if (s === 0 || s == null) return 'Nu am putut ajunge la server. Verifică conexiunea și încearcă din nou.';
    return 'Nu am putut salva. Încearcă din nou.';
  }
  /** Laravel field errors onto the form's fields; returns the first field it marked. */
  function markServer(err, map) {
    var errors = (err && err.errors) || (err && err.data && err.data.errors) || null, first = null;
    if (!errors || typeof errors !== 'object') return null;
    Object.keys(errors).forEach(function (k) { if (map[k] && $(map[k])) { fieldErr(map[k], 'Verifică această valoare.'); if (!first) first = map[k]; } });
    if (first) $(first).focus();
    return first;
  }
  function clearErrs(ids, box) { ids.forEach(function (id) { fieldErr(id, ''); }); if (box) formErr(box, ''); }
  function focusKey(box) { var a = document.activeElement; return a && a !== document.body && box.contains(a) ? a.getAttribute('data-focus') || '' : null; }
  /** The key to give the focus back to after a redraw: the focused control, or `wanted` when a disabled button dropped it. */
  function keepKey(box, wanted) {
    var k = focusKey(box), a = document.activeElement;
    return k === null && wanted && (!a || a === document.body) ? wanted : k;
  }
  function restoreFocus(box, key, fallback) {
    if (key === null) return;
    var same = key ? box.querySelector('[data-focus="' + key + '"]') : null;
    if (same) same.focus();
    else if (fallback && !fallback.disabled) fallback.focus();
  }
  function pill(ic, text, attrs) {
    var b = el('button', Object.assign({ class: 'os-pill', type: 'button' }, attrs || {}), [icon(ic), text]);
    return b;
  }

  /* =================== TABS =================== */
  function tabFromHash() { var k = String(location.hash || '').replace(/^#/, ''); return TABS.indexOf(k) > -1 ? k : ''; }
  function openTab(key, focus) {
    var t = $('os-tab-' + key);
    if (!t) return;
    if (t.getAttribute('aria-selected') !== 'true') t.click();
    if (focus) t.focus();
  }
  function onTabChange() { // base.js selects on click and on the arrow keys: follow whichever tab ends up selected
    var sel = root.querySelector('.os-tab[aria-selected="true"]'), key = sel ? sel.id.replace('os-tab-', '') : '';
    if (!key) return;
    if (tabFromHash() !== key) history.replaceState(null, '', location.pathname + location.search + '#' + key);
    if (key === 'contract') setTimeout(sizePad, 0);
  }
  if (typeof MutationObserver === 'function') {
    var tabObs = new MutationObserver(onTabChange);
    TABS.forEach(function (key) { tabObs.observe($('os-tab-' + key), { attributes: true, attributeFilter: ['aria-selected'] }); });
  } else TABS.forEach(function (key) { $('os-tab-' + key).addEventListener('click', onTabChange); });
  window.addEventListener('hashchange', function () { var k = tabFromHash(); if (k) openTab(k, false); });
  qsa('[data-go]', root).forEach(function (b) {
    b.addEventListener('click', function () { openTab(b.getAttribute('data-go'), true); });
  });

  /* =================== SUMMARY =================== */
  function sum(id, value, sub, cls) {
    var b = $('os-sum-' + id);
    b.classList.remove('is-ok', 'is-todo', 'is-bad');
    if (cls) b.classList.add(cls);
    b.querySelector('.os-sum-v').textContent = value;
    b.querySelector('.os-sum-p').textContent = sub || '';
  }
  function docsOf() { var d = (contract && contract.documents) || {}; return { id_card: safeUrl(d.id_card), cui_document: safeUrl(d.cui) }; }
  function renderSummary() {
    if (me === null) sum('status', '—', 'Nu am putut încărca contul.', 'is-bad');
    else if (me) {
      var s = String(me.status || '');
      if (s === 'active') sum('status', 'Activ', me.is_verified ? 'Date verificate.' : 'Datele firmei nu sunt verificate încă.', 'is-ok');
      else if (s === 'suspended') sum('status', 'Suspendat', 'Scrie-ne la suport ca să aflăm ce s-a întâmplat.', 'is-bad');
      else if (s === 'pending') sum('status', 'În așteptare', 'Îl activăm după ce verificăm documentele.', 'is-todo');
      else sum('status', F.flat(s) || '—', '', '');
    }
    var docs = docsOf(), nDocs = (docs.id_card ? 1 : 0) + (docs.cui_document ? 1 : 0);
    if (contract === null) { sum('contract', '—', 'Nu am putut încărca contractul.', 'is-bad'); sum('docs', '—', '', 'is-bad'); }
    else if (contract) {
      if (contract.is_signed) sum('contract', 'Semnat', contract.signed_at ? 'Pe ' + dayLabel(contract.signed_at) + '.' : '', 'is-ok');
      else if (contract.signature_required) sum('contract', 'De semnat', 'Semnează-l din tabul Contract.', 'is-todo');
      else if (contract.has_contract) sum('contract', 'Generat', 'Îl poți descărca oricând.', 'is-ok');
      else sum('contract', 'Negenerat', 'Se generează după ce încarci documentele.', 'is-todo');
      sum('docs', nDocs + ' din 2 încărcate', nDocs === 2 ? 'Copia CI și certificatul CUI.' : 'Lipsește ' + ['id_card', 'cui_document'].filter(function (k) { return !docs[k]; }).map(function (k) { return DOC_NAME[k]; }).join(' și ') + '.', nDocs === 2 ? 'is-ok' : 'is-todo');
    }
    if (accounts === null) sum('bank', '—', 'Nu am putut încărca conturile.', 'is-bad');
    else if (accounts) {
      var primary = accounts.filter(function (a) { return a.is_primary; })[0];
      if (!accounts.length) sum('bank', 'Niciun cont bancar', 'Adaugă contul în care primești banii.', 'is-todo');
      else sum('bank', F.count(accounts.length, 'cont bancar', 'conturi bancare'), primary ? 'Principal: ' + (F.flat(primary.bank) || 'cont') + ' •••• ' + String(primary.iban || '').replace(/\s+/g, '').slice(-4) : 'Alege un cont principal.', primary ? 'is-ok' : 'is-todo');
    }
    $('os-dot-company').hidden = !(me && !(F.flat(me.company_name).trim() && F.flat(me.company_tax_id).trim()));
    $('os-dot-bank').hidden = !(accounts && !accounts.length);
    $('os-dot-contract').hidden = !(contract && ((contract.signature_required && !contract.is_signed) || nDocs < 2));
  }

  /* =================== PROFILE =================== */
  function loadMe() {
    $('os-load-err').hidden = true;
    return O.api('/organizer/me').then(function (r) {
      var d = r && r.data;
      me = (d && d.organizer) || (d && !Array.isArray(d) ? d : {}) || {};
      fillProfile();
      fillCompany();
      renderNotifMail();
      lockForms(false);
      renderSummary();
      if (accounts) renderAccounts();
    }).catch(function (err) {
      if (err && err.status === 401) return;
      me = null;
      $('os-load-err').hidden = false;
      renderSummary();
    });
  }
  $('os-retry').addEventListener('click', function () { loadMe(); });
  function lockForms(on) {
    qsa('#os-profile-form .os-fs, #os-company-form .os-fs', root).forEach(function (f) { f.disabled = on; });
    ['os-profile-go', 'os-company-go', 'os-sc2-go', 'os-sc2-on'].forEach(function (id) { $(id).disabled = on; });
    if (!on && me) syncCompany();
  }
  function countDesc() { var n = $('os-desc').value.length; $('os-desc-n').textContent = F.num(n) + ' / 2.000'; }
  $('os-desc').addEventListener('input', countDesc);
  function fillProfile() {
    setVal('os-name', F.flat(me.name));
    setVal('os-contact', F.flat(me.contact_name));
    setVal('os-email', F.flat(me.email));
    setVal('os-phone', F.flat(me.phone));
    setVal('os-website', F.flat(me.website));
    setVal('os-desc', F.flat(me.description));
    countDesc();
    renderGuarantor();
  }
  function mask(v) { return v.length > 4 ? '•'.repeat(v.length - 4) + v.slice(-4) : v; }
  function renderGuarantor() {
    var g = me, box = $('os-guarantor'), dl = $('os-guarantor-dl');
    var rows = [['Prenume', g.guarantor_first_name], ['Nume', g.guarantor_last_name], ['CNP', g.guarantor_cnp, 'cnp'], ['Localitatea', g.guarantor_city], ['Adresa de domiciliu', g.guarantor_address, 'wide'],
      ['Tipul actului', ID_TYPES[g.guarantor_id_type] || g.guarantor_id_type], ['Seria', g.guarantor_id_series], ['Numărul', g.guarantor_id_number], ['Data eliberării', g.guarantor_id_issued_date ? dayLabel(g.guarantor_id_issued_date) : ''], ['Eliberat de', g.guarantor_id_issued_by]];
    dl.textContent = '';
    box.hidden = !(F.flat(g.guarantor_first_name) || F.flat(g.guarantor_last_name) || F.flat(g.guarantor_cnp));
    rows.forEach(function (r) {
      var v = F.flat(r[1]).trim(), dd = el('dd');
      if (r[2] === 'cnp' && v) {
        var shown = false, span = el('span', { text: mask(v) }), btn = el('button', { class: 'os-reveal', type: 'button', 'aria-pressed': 'false', 'aria-label': 'Arată CNP-ul', text: 'Arată' });
        btn.addEventListener('click', function () {
          shown = !shown;
          span.textContent = shown ? v : mask(v);
          btn.textContent = shown ? 'Ascunde' : 'Arată';
          btn.setAttribute('aria-pressed', String(shown));
          btn.setAttribute('aria-label', shown ? 'Ascunde CNP-ul' : 'Arată CNP-ul');
        });
        dd.appendChild(span);
        dd.appendChild(btn);
      } else dd.textContent = v || '—';
      dl.appendChild(el('div', { class: r[2] === 'wide' ? 'is-wide' : null }, [el('dt', { text: r[0] }), dd]));
    });
  }
  function normalizeUrl(v) {
    v = String(v || '').trim();
    if (!v) return '';
    if (!/^https?:\/\//i.test(v)) v = 'https://' + v;
    try { var u = new URL(v); return /^https?:$/.test(u.protocol) && u.hostname.indexOf('.') > 0 && !/\s/.test(v) ? v : null; } catch (e) { return null; }
  }
  function need(state, id, msg) { fieldErr(id, msg); if (msg && !state.bad) state.bad = id; }
  function saveProfile(btn, body, errBox, okText, map) {
    busyBtn(btn, true, 'Se salvează…');
    return O.api('/organizer/profile', { method: 'PUT', body: body }).then(function (r) {
      busyBtn(btn, false);
      var d = r && r.data;
      if (d && d.organizer) { me = d.organizer; renderSummary(); }
      else Object.keys(body).forEach(function (k) { me[k] = body[k]; });
      O.flash(okText);
      return true;
    }).catch(function (err) {
      busyBtn(btn, false);
      if (err && err.status === 401) return false;
      formErr(errBox, saveError(err));
      markServer(err, map);
      return false;
    });
  }
  $('os-profile-form').addEventListener('submit', function (ev) {
    ev.preventDefault();
    var btn = $('os-profile-go'), st = { bad: null };
    if (!me || isBusy(btn)) return;
    clearErrs(['os-name', 'os-phone', 'os-website'], 'os-profile-err');
    var name = val('os-name'), phone = val('os-phone'), website = normalizeUrl(val('os-website'));
    need(st, 'os-name', name ? '' : 'Scrie numele operatorului.');
    need(st, 'os-phone', phone && (!/^[+\d][\d\s().\/-]*$/.test(phone) || phone.replace(/\D/g, '').length < 6) ? 'Scrie un număr de telefon valid, de exemplu 0722 123 456.' : '');
    need(st, 'os-website', website === null ? 'Scrie o adresă validă, de exemplu www.firma-ta.ro.' : '');
    if (st.bad) { $(st.bad).focus(); return; }
    if (website) setVal('os-website', website);
    saveProfile(btn, { name: name, contact_name: val('os-contact') || null, phone: phone || null, website: website || null, description: $('os-desc').value.trim() || null }, 'os-profile-err', 'Profilul a fost salvat.', { name: 'os-name', contact_name: 'os-contact', phone: 'os-phone', website: 'os-website', description: 'os-desc' });
  });

  /* =================== COMPANY =================== */
  // The companies come from ANAF, never typed: once saved they are read-only (a change goes through support). Without
  // one yet, the CUI is checked at ANAF, the answer fills the fields and only then it can be saved. The second issuing
  // company follows the same steps, and only while "Am o a doua societate emitentă" is ticked.
  var CUI = /^(RO)?\s*\d{2,10}$/i;
  var SC1_FIELDS = [['os-cname', 'company_name'], ['os-creg', 'company_registration'], ['os-caddr', 'company_address'], ['os-ccity', 'company_city'], ['os-ccounty', 'company_county'], ['os-czip', 'company_zip']];
  var SC2_FIELDS = [['os-s-name', 'secondary_company_name'], ['os-s-reg', 'secondary_company_registration'], ['os-s-addr', 'secondary_company_address'], ['os-s-city', 'secondary_company_city'], ['os-s-county', 'secondary_company_county'], ['os-s-zip', 'secondary_company_zip']];
  var anaf1 = null, anaf2 = null; // the last ANAF answer for SC1 / SC2 (what gets saved)
  function hasSc1() { return !!(me && F.flat(me.company_tax_id).trim() && F.flat(me.company_name).trim()); }
  function hasSc2() { return !!(me && me.has_secondary_issuer && F.flat(me.secondary_company_tax_id).trim() && F.flat(me.secondary_company_name).trim()); }
  function fillCompany() {
    anaf1 = anaf2 = null;
    setVal('os-cui', F.flat(me.company_tax_id));
    SC1_FIELDS.forEach(function (p) { setVal(p[0], F.flat(me[p[1]])); });
    $('os-vat').textContent = me.vat_payer == null ? '—' : me.vat_payer ? 'Da' : 'Nu';
    $('os-sc2-on').checked = !!me.has_secondary_issuer;
    setVal('os-s-cui', F.flat(me.secondary_company_tax_id));
    SC2_FIELDS.forEach(function (p) { setVal(p[0], F.flat(me[p[1]])); });
    syncCompany();
  }
  function syncCompany() {
    var locked = hasSc1();
    $('os-cui').readOnly = locked;
    $('os-anaf').hidden = locked;
    $('os-company-lock').hidden = !locked;
    $('os-company-how').hidden = locked;
    $('os-company-act').hidden = locked;
    $('os-company-go').disabled = locked || !anaf1;
    syncSc2();
  }
  function syncSc2() {
    var on = $('os-sc2-on').checked, locked = hasSc2();
    $('os-sc2-fields').hidden = !on;
    $('os-s-cui').readOnly = locked;
    $('os-s-anaf').hidden = locked;
    $('os-sc2-lock').hidden = !locked;
    $('os-s-data').hidden = !(locked || anaf2);
    // save: a new SC2 after its ANAF check, or turning a saved one off
    var canSave = !!me && ((on && !locked && !!anaf2) || (!on && !!me.has_secondary_issuer));
    $('os-sc2-act').hidden = !canSave;
    $('os-sc2-go').disabled = !canSave;
    $('os-sc2-go').querySelector('[data-label]').textContent = on ? 'Salvează SC2' : 'Oprește a doua societate';
  }
  $('os-sc2-on').addEventListener('change', syncSc2);
  /** ANAF lookup for one CUI field; fills the read-only fields and resolves the company, or null after saying why. */
  function checkAnaf(btn, cuiId, msgId, fields) {
    var cui = val(cuiId), msg = $(msgId);
    if (isBusy(btn)) return Promise.resolve(null);
    fieldErr(cuiId, '');
    if (!CUI.test(cui)) { fieldErr(cuiId, 'Scrie CUI-ul firmei, de exemplu RO12345678.'); $(cuiId).focus(); return Promise.resolve(null); }
    busyBtn(btn, true, 'Se verifică…');
    msg.className = 'os-help';
    msg.textContent = 'Căutăm firma în registrul ANAF…';
    return O.api('/organizer/settings/verify-cui', { method: 'POST', body: { cui: cui }, quiet: true }).then(function (r) {
      busyBtn(btn, false);
      var d = r && r.data;
      if (!d || !F.flat(d.company_name).trim()) { var e = new Error('anaf'); e.status = 404; throw e; }
      if (d.deregistered || /RADI/i.test(F.flat(d.status))) {
        msg.className = 'os-help is-bad';
        msg.textContent = 'Firma cu acest CUI e radiată la ANAF. Folosește CUI-ul unei firme active.';
        return null;
      }
      var co = {
        cui: cui.toUpperCase().replace(/\s+/g, ''), name: F.flat(d.company_name).trim(), reg: F.flat(d.reg_com).trim(),
        addr: tidyPlace(d.address).replace(/\s{2,}/g, ' '), city: tidyPlace(d.city), county: tidyPlace(d.county), zip: F.flat(d.zip).trim(), vat: !!d.vat_payer,
      };
      [co.name, co.reg, co.addr, co.city, co.county, co.zip].forEach(function (v, i) { setVal(fields[i][0], v); });
      msg.className = 'os-help is-ok';
      msg.textContent = 'Am găsit firma în ANAF' + (F.flat(d.status).trim() ? ' (' + tidyPlace(d.status) + ')' : '') + '. Verifică datele și salvează.';
      return co;
    }).catch(function (err) {
      busyBtn(btn, false);
      msg.className = 'os-help is-bad';
      msg.textContent = err && err.status === 404 ? 'Nu am găsit firma în ANAF. Verifică CUI-ul.'
        : err && (err.status === 502 || err.status === 503) ? 'Serviciul ANAF nu răspunde acum. Încearcă din nou în câteva minute.'
        : 'Nu am putut verifica acum. Încearcă din nou.';
      return null;
    });
  }
  $('os-anaf').addEventListener('click', function () {
    checkAnaf(this, 'os-cui', 'os-anaf-msg', SC1_FIELDS).then(function (co) {
      anaf1 = co;
      if (co) $('os-vat').textContent = (co.vat ? 'Da' : 'Nu') + ', după ANAF';
      syncCompany();
    });
  });
  $('os-cui').addEventListener('input', function () { if (anaf1) { anaf1 = null; syncCompany(); } });
  $('os-s-anaf').addEventListener('click', function () {
    checkAnaf(this, 'os-s-cui', 'os-s-anaf-msg', SC2_FIELDS).then(function (co) { anaf2 = co; syncSc2(); });
  });
  $('os-s-cui').addEventListener('input', function () { if (anaf2) { anaf2 = null; syncSc2(); } });
  $('os-company-form').addEventListener('submit', function (ev) {
    ev.preventDefault();
    var btn = $('os-company-go');
    if (!me || isBusy(btn) || hasSc1()) return;
    clearErrs(['os-cui'], 'os-company-err');
    if (!anaf1) { fieldErr('os-cui', 'Verifică întâi CUI-ul în ANAF.'); $('os-cui').focus(); return; }
    var c = anaf1;
    saveProfile(btn, { company_tax_id: c.cui, company_name: c.name, company_registration: c.reg || null, company_address: c.addr || null, company_city: c.city || null, company_county: c.county || null, company_zip: c.zip || null },
      'os-company-err', 'Datele firmei au fost salvate.', { company_tax_id: 'os-cui' })
      .then(function (ok) { if (ok) fillCompany(); });
  });
  $('os-sc2-form').addEventListener('submit', function (ev) {
    ev.preventDefault();
    var btn = $('os-sc2-go'), on = $('os-sc2-on').checked;
    if (!me || isBusy(btn)) return;
    clearErrs(['os-s-cui'], 'os-sc2-err');
    var body;
    if (on) {
      if (hasSc2()) return;
      if (!anaf2) { fieldErr('os-s-cui', 'Verifică întâi CUI-ul în ANAF.'); $('os-s-cui').focus(); return; }
      var c = anaf2;
      body = { has_secondary_issuer: true, secondary_company_tax_id: c.cui, secondary_company_name: c.name, secondary_company_registration: c.reg || null, secondary_company_address: c.addr || null, secondary_company_city: c.city || null, secondary_company_county: c.county || null, secondary_company_zip: c.zip || null };
    } else {
      body = { has_secondary_issuer: false };
    }
    saveProfile(btn, body, 'os-sc2-err', on ? 'A doua societate a fost salvată.' : 'A doua societate emitentă a fost oprită.', { secondary_company_tax_id: 'os-s-cui' })
      .then(function (ok) { if (ok) { fillCompany(); if (accounts) renderAccounts(); } });
  });

  /* =================== BANK ACCOUNTS =================== */
  function compactIban(v) { return String(v || '').replace(/\s+/g, '').toUpperCase(); }
  function groupIban(v) { return compactIban(v).replace(/(.{4})/g, '$1 ').trim(); }
  function mod97(iban) {
    var r = iban.slice(4) + iban.slice(0, 4), rem = 0;
    for (var i = 0; i < r.length; i++) {
      var c = r.charCodeAt(i), part = c >= 65 && c <= 90 ? String(c - 55) : r.charAt(i);
      for (var j = 0; j < part.length; j++) rem = (rem * 10 + Number(part.charAt(j))) % 97;
    }
    return rem === 1;
  }
  function ibanCheck(v) {
    var s = compactIban(v);
    if (!s) return { ok: false, msg: '' };
    if (!/^[A-Z]{2}/.test(s)) return { ok: false, msg: 'Un IBAN începe cu codul țării, de exemplu RO.' };
    if (/[^A-Z0-9]/.test(s)) return { ok: false, msg: 'IBAN-ul are doar litere și cifre.' };
    if (s.slice(0, 2) === 'RO' && s.length < 24) return { ok: false, partial: true, msg: 'Mai lipsesc ' + F.count(24 - s.length, 'caracter', 'caractere') + ' (un IBAN românesc are 24).' };
    if (s.slice(0, 2) === 'RO' && s.length > 24) return { ok: false, msg: 'Are ' + F.count(s.length - 24, 'caracter', 'caractere') + ' în plus (un IBAN românesc are 24).' };
    if (!/^[A-Z]{2}\d{2}[A-Z0-9]{10,30}$/.test(s)) return { ok: false, partial: s.length < 15, msg: 'Formatul nu pare corect.' };
    if (!mod97(s)) return { ok: false, msg: 'Cifrele de control nu se potrivesc. Verifică IBAN-ul.' };
    var bank = s.slice(0, 2) === 'RO' ? BANKS[s.slice(4, 8)] || '' : '';
    return { ok: true, iban: s, bank: bank, msg: 'IBAN valid' + (bank ? ' · ' + bank : '') + '.' };
  }
  function loadAccounts(wanted) {
    var box = $('os-bank-list');
    return O.api('/organizer/bank-accounts', { quiet: true }).then(function (r) {
      var d = r && r.data;
      accounts = (d && Array.isArray(d.accounts) ? d.accounts : Array.isArray(d) ? d : []).filter(function (a) { return a && /^\d+$/.test(String(a.id)); });
      var keep = keepKey(box, wanted);
      renderAccounts();
      restoreFocus(box, keep, $('os-bank-add'));
      renderSummary();
    }, function (err) {
      if (err && err.status === 401) return;
      accounts = null;
      emptyBox(box, 'li', 'Nu am putut încărca conturile bancare.', function () { loadAccounts(); });
      renderSummary();
    });
  }
  function renderAccounts() {
    var box = $('os-bank-list'), sc2 = !!(me && me.has_secondary_issuer) || accounts.some(function (a) { return a.issuing_company === 'secondary'; });
    box.textContent = '';
    $('os-bank-add').disabled = accounts.length >= 5;
    if (!accounts.length) {
      var cta = el('button', { class: 'btn btn-primary os-sm', type: 'button', 'data-focus': 'bank-first' }, [icon('plus'), 'Adaugă primul cont']);
      cta.addEventListener('click', function () { openBank(cta); });
      box.appendChild(el('li', { class: 'os-empty' }, [el('b', { text: 'Niciun cont bancar' }), el('p', { text: 'Adaugă contul în care vrei să primești banii din vânzări.' }), cta]));
      return;
    }
    accounts.forEach(function (a) {
      var bank = F.flat(a.bank) || 'Cont bancar', iban = groupIban(a.iban), issuer = a.issuing_company === 'secondary' ? 'secondary' : 'primary', acts = [];
      var copy = pill('copy', 'Copiază IBAN-ul', { 'data-focus': 'bank-copy-' + a.id, 'aria-label': 'Copiază IBAN-ul contului ' + bank });
      copy.addEventListener('click', function () {
        copyText(compactIban(a.iban)).then(function () { O.flash('IBAN-ul a fost copiat.'); }, function () { O.flash('Nu am putut copia. Selectează IBAN-ul și copiază-l manual.', true); });
      });
      acts.push(copy);
      if (sc2) {
        var sel = el('select', { 'aria-label': 'Societatea emitentă pentru contul ' + bank, 'data-focus': 'bank-issuer-' + a.id }, [el('option', { value: 'primary', text: 'SC1 · principală' }), el('option', { value: 'secondary', text: 'SC2 · secundară' })]);
        sel.value = issuer;
        sel.addEventListener('change', function () {
          var next = sel.value;
          sel.disabled = true;
          O.api('/organizer/bank-accounts/' + a.id, { method: 'PUT', body: { issuing_company: next } }).then(function () {
            a.issuing_company = next;
            O.flash('Contul ' + bank + ' încasează acum pentru ' + (next === 'secondary' ? 'SC2' : 'SC1') + '.');
            loadAccounts('bank-issuer-' + a.id);
          }).catch(function (err) {
            sel.value = issuer;
            sel.disabled = false;
            if (err && err.status === 401) return;
            O.flash('Nu am putut schimba societatea emitentă. Încearcă din nou.', true);
          });
        });
        acts.push(el('span', { class: 'os-mini' }, [sel, icon('caret-down')]));
      }
      if (!a.is_primary) {
        var prim = pill('check-circle', 'Setează principal', { 'data-focus': 'bank-primary-' + a.id, 'aria-label': 'Setează ' + bank + ' ca cont principal' });
        prim.addEventListener('click', function () {
          prim.disabled = true;
          O.api('/organizer/bank-accounts/' + a.id + '/primary', { method: 'POST', body: {} }).then(function () {
            O.flash('Contul principal este acum ' + bank + '.');
            loadAccounts('bank-copy-' + a.id);
          }).catch(function (err) {
            prim.disabled = false;
            if (err && err.status === 401) return;
            O.flash('Nu am putut schimba contul principal. Încearcă din nou.', true);
          });
        });
        acts.push(prim);
      }
      var del = pill('trash', 'Șterge', { class: 'os-pill is-danger', 'data-focus': 'bank-del-' + a.id, 'aria-label': 'Șterge contul ' + bank });
      del.addEventListener('click', function () { openDelete('bank', a, del); });
      acts.push(del);
      box.appendChild(el('li', { class: 'os-acc' + (a.is_primary ? ' is-primary' : '') }, [
        el('span', { class: 'os-acc-ic', 'aria-hidden': 'true' }, icon('bank')),
        el('div', { class: 'os-acc-t' }, [
          el('div', { class: 'os-acc-top' }, [el('b', { text: bank }), a.is_primary ? tag('Principal', 'is-ok') : null, sc2 ? tag(issuer === 'secondary' ? 'SC2' : 'SC1', 'is-muted') : null]),
          el('span', { class: 'os-iban', text: iban }),
          el('span', { class: 'os-meta', text: F.flat(a.holder) ? 'Titular: ' + F.flat(a.holder) : '' }),
        ]),
        el('div', { class: 'os-acc-act' }, acts),
      ]));
    });
    if (accounts.length >= 5) box.appendChild(el('li', { class: 'os-limit', text: 'Ai atins numărul maxim de 5 conturi. Ca să adaugi altul, șterge unul pe care nu îl mai folosești.' }));
  }
  var bankAuto = '';
  function openBank(from) {
    if (accounts && accounts.length >= 5) return;
    setVal('os-iban', '');
    setVal('os-bname', '');
    setVal('os-holder', me ? F.flat(me.company_name) || F.flat(me.name) : '');
    bankAuto = '';
    $('os-iban-msg').textContent = '';
    $('os-iban-msg').className = 'os-help';
    $('os-issuer-f').hidden = !(me && me.has_secondary_issuer);
    $('os-issuer').value = 'primary';
    clearErrs(['os-iban', 'os-bname', 'os-holder'], 'os-bank-err');
    fallbackFocus = $('os-bank-add');
    openDialog($('os-bank-d'), from);
    $('os-iban').focus();
  }
  $('os-bank-add').addEventListener('click', function () { openBank(this); });
  $('os-iban').addEventListener('input', function () {
    var c = ibanCheck(this.value), msg = $('os-iban-msg');
    fieldErr('os-iban', '');
    msg.textContent = c.msg;
    msg.className = 'os-help' + (c.ok ? ' is-ok' : c.msg && !c.partial ? ' is-bad' : '');
    if (c.ok && c.bank && (!val('os-bname') || val('os-bname') === bankAuto)) { setVal('os-bname', c.bank); bankAuto = c.bank; }
  });
  $('os-iban').addEventListener('blur', function () { if (ibanCheck(this.value).ok) this.value = groupIban(this.value); });
  $('os-bank-form').addEventListener('submit', function (ev) {
    ev.preventDefault();
    var d = $('os-bank-d'), btn = $('os-bank-go'), st = { bad: null };
    if (isBusy(btn)) return;
    clearErrs(['os-iban', 'os-bname', 'os-holder'], 'os-bank-err');
    var c = ibanCheck(val('os-iban'));
    need(st, 'os-iban', !val('os-iban') ? 'Scrie IBAN-ul contului.' : c.ok ? '' : c.msg || 'IBAN-ul nu este valid.');
    need(st, 'os-bname', val('os-bname') ? '' : 'Scrie numele băncii.');
    need(st, 'os-holder', val('os-holder') ? '' : 'Scrie titularul contului.');
    if (st.bad) { $(st.bad).focus(); return; }
    var body = { bank: val('os-bname'), iban: c.iban, holder: val('os-holder'), issuing_company: me && me.has_secondary_issuer ? $('os-issuer').value : 'primary' };
    busyBtn(btn, true, 'Se adaugă…');
    d.setAttribute('data-busy', '');
    O.api('/organizer/bank-accounts', { method: 'POST', body: body }).then(function () {
      d.removeAttribute('data-busy');
      busyBtn(btn, false);
      closeDialog(d);
      O.flash('Contul bancar a fost adăugat.' + (accounts && !accounts.length ? ' Fiind primul, e și contul principal.' : ''));
      loadAccounts();
    }).catch(function (err) {
      d.removeAttribute('data-busy');
      busyBtn(btn, false);
      if (err && err.status === 401) return;
      var m = norm(errMessage(err));
      if (/deja adaugat/.test(m)) { fieldErr('os-iban', 'Acest IBAN este deja adăugat în cont.'); $('os-iban').focus(); return; }
      if (/numarul maxim/.test(m)) { formErr('os-bank-err', 'Poți avea cel mult 5 conturi bancare.'); return; }
      formErr('os-bank-err', saveError(err));
      markServer(err, { iban: 'os-iban', bank: 'os-bname', holder: 'os-holder' });
    });
  });

  /* =================== CONTRACT =================== */
  function loadContract() {
    return O.api('/organizer/contract', { quiet: true }).then(function (r) {
      contract = (r && r.data) || {};
      renderContract();
      renderSummary();
    }, function (err) {
      if (err && err.status === 401) return;
      contract = null;
      var box = $('os-contract-state');
      box.className = 'os-callout is-bad';
      box.textContent = '';
      var retry = el('button', { class: 'os-linkbtn', type: 'button', text: 'Reîncearcă' });
      retry.addEventListener('click', function () { loadContract(); });
      box.appendChild(icon('warning-circle'));
      box.appendChild(el('div', null, [el('b', { text: 'Nu am putut încărca contractul' }), el('p', null, ['Verifică conexiunea. ', retry])]));
      renderSummary();
    });
  }
  function setState(cls, ic, title, text) {
    var box = $('os-contract-state');
    box.className = 'os-callout' + (cls ? ' ' + cls : '');
    box.textContent = '';
    box.appendChild(icon(ic));
    box.appendChild(el('div', null, [el('b', { text: title }), text ? el('p', { text: text }) : null]));
  }
  function contractUrl() { return safeUrl(contract && contract.contract && contract.contract.download_url); }
  function renderContract() {
    var c = contract, w = WORK[c.work_mode] || [F.flat(c.work_mode) || '—', ''], m = MODE[c.commission_mode] || [F.flat(c.commission_mode) || '—', ''];
    $('os-k-comm').textContent = c.commission_rate != null && c.commission_rate !== '' ? F.pct(c.commission_rate, 2) : '—';
    $('os-k-comm-p').textContent = 'din valoarea biletelor vândute, conform contractului';
    $('os-k-work').textContent = w[0];
    $('os-k-work-p').textContent = w[1];
    $('os-k-mode').textContent = m[0];
    $('os-k-mode-p').textContent = m[1];
    var terms = $('os-terms'), list = Array.isArray(c.terms) && c.terms.length ? c.terms : Object.keys(TERMS);
    terms.textContent = '';
    list.forEach(function (t) { t = F.flat(t).trim(); if (t) terms.appendChild(el('li', null, [icon('check-circle'), TERMS[t.replace(/\.$/, '')] || t])); });
    if (c.is_signed) setState('is-ok', 'check-circle', 'Contract semnat', c.signed_at ? 'Semnat electronic pe ' + stamp(c.signed_at) + '.' : 'Semnat electronic.');
    else if (c.signature_required) setState('is-warm', 'signature', 'Contractul așteaptă semnătura ta', 'Semnează mai jos. Până atunci nu poți cere plăți.');
    else if (c.has_contract) setState('', 'file-text', 'Contract generat', c.contract && c.contract.issued_at ? 'Emis pe ' + dayLabel(c.contract.issued_at) + '.' : '');
    else setState('', 'info', 'Contractul nu este generat încă', 'Se generează automat după ce încarci ambele documente de mai jos și îți verificăm datele.');
    $('os-contract-dl').hidden = !contractUrl();
    var sign = $('os-sign'), read = $('os-sign-read');
    sign.hidden = !(c.signature_required && !c.is_signed);
    read.hidden = !contractUrl();
    if (contractUrl()) read.href = contractUrl();
    if (!sign.hidden) setTimeout(sizePad, 0);
    var docs = docsOf();
    renderDoc('id_card', docs.id_card);
    renderDoc('cui_document', docs.cui_document);
  }
  $('os-contract-dl').addEventListener('click', function () {
    var url = contractUrl();
    if (url) { window.open(url, '_blank', 'noopener'); return; }
    O.flash('Contractul nu este disponibil acum. Scrie-ne și ți-l trimitem.', true);
  });

  /* ----- signature ----- */
  var pad = $('os-pad'), ctx = null, drawn = false, typed = false, drawing = false, last = null, padW = 0, padH = 0;
  function sizePad() {
    var r = pad.getBoundingClientRect();
    if (!r.width || !r.height) return false;
    var dpr = Math.max(1, Math.min(3, window.devicePixelRatio || 1));
    if (ctx && padW === Math.round(r.width) && padH === Math.round(r.height)) return true;
    var keep = drawn ? pad.toDataURL('image/png') : null, oldW = padW, oldH = padH;
    padW = Math.round(r.width);
    padH = Math.round(r.height);
    pad.width = Math.round(r.width * dpr);
    pad.height = Math.round(r.height * dpr);
    ctx = pad.getContext('2d');
    ctx.setTransform(dpr, 0, 0, dpr, 0, 0);
    ctx.lineCap = 'round';
    ctx.lineJoin = 'round';
    ctx.strokeStyle = '#0E1F18';
    ctx.fillStyle = '#0E1F18';
    ctx.lineWidth = 2.4;
    if (typed) drawTyped();
    else if (keep && oldW) { var img = new Image(); img.onload = function () { ctx.drawImage(img, 0, 0, oldW, oldH); }; img.src = keep; }
    return true;
  }
  function point(e) { var r = pad.getBoundingClientRect(); return { x: e.clientX - r.left, y: e.clientY - r.top }; }
  function clearPad(keepTyped) {
    if (ctx) ctx.clearRect(0, 0, padW, padH);
    drawn = false;
    if (!keepTyped) { typed = false; setVal('os-sign-typed', ''); }
    $('os-pad-hint').hidden = typed;
  }
  function drawTyped() {
    var v = val('os-sign-typed');
    if (!ctx || !v) return;
    var size = Math.min(58, padH * 0.36);
    ctx.save();
    ctx.textAlign = 'center';
    ctx.textBaseline = 'alphabetic';
    do { ctx.font = 'italic 600 ' + size + 'px "Segoe Script", "Brush Script MT", "Snell Roundhand", "Apple Chancery", cursive'; size -= 2; } while (ctx.measureText(v).width > padW - 56 && size > 14);
    ctx.fillText(v, padW / 2, padH - 54);
    ctx.restore();
  }
  pad.addEventListener('pointerdown', function (e) {
    if (!sizePad()) return;
    e.preventDefault();
    if (typed) clearPad(false);
    drawing = true;
    try { pad.setPointerCapture(e.pointerId); } catch (x) {}
    last = point(e);
    ctx.beginPath();
    ctx.arc(last.x, last.y, 1.2, 0, Math.PI * 2);
    ctx.fill();
    drawn = true;
    $('os-pad-hint').hidden = true;
    formErr('os-sign-err', '');
  });
  pad.addEventListener('pointermove', function (e) {
    if (!drawing) return;
    var p = point(e);
    ctx.beginPath();
    ctx.moveTo(last.x, last.y);
    ctx.lineTo(p.x, p.y);
    ctx.stroke();
    last = p;
  });
  ['pointerup', 'pointercancel', 'lostpointercapture'].forEach(function (t) { pad.addEventListener(t, function () { drawing = false; }); });
  $('os-sign-typed').addEventListener('input', function () {
    if (!sizePad()) return;
    clearPad(true);
    typed = !!val('os-sign-typed');
    if (typed) drawTyped();
    $('os-pad-hint').hidden = typed;
    formErr('os-sign-err', '');
  });
  $('os-pad-clear').addEventListener('click', function () { clearPad(false); });
  window.addEventListener('resize', function () { if (!$('os-sign').hidden) sizePad(); });
  $('os-sign').addEventListener('submit', function (ev) {
    ev.preventDefault();
    var btn = $('os-sign-go');
    if (isBusy(btn) || !contract) return;
    formErr('os-sign-err', '');
    if (!drawn && !typed) { formErr('os-sign-err', 'Desenează semnătura în casetă sau scrie-ți numele.'); $('os-sign-typed').focus(); return; }
    if (!$('os-agree').checked) { formErr('os-sign-err', 'Bifează că ai citit contractul și ești de acord cu termenii lui.'); $('os-agree').focus(); return; }
    busyBtn(btn, true, 'Se semnează…');
    O.api('/organizer/contract/sign', { method: 'POST', body: { signature: pad.toDataURL('image/png'), agreement: true } }).then(function (r) {
      busyBtn(btn, false);
      var d = (r && r.data) || {};
      contract.is_signed = true;
      contract.signed_at = d.signed_at || contract.signed_at || new Date().toISOString();
      if (d.contract) { contract.has_contract = true; contract.contract = Object.assign({}, contract.contract || {}, d.contract); }
      clearPad(false);
      $('os-agree').checked = false;
      renderContract();
      renderSummary();
      O.flash(d.already_signed ? 'Contractul era deja semnat.' : 'Contractul a fost semnat. Îl poți descărca oricând de aici.');
      var state = $('os-contract-state');
      state.setAttribute('tabindex', '-1');
      state.focus();
    }).catch(function (err) {
      busyBtn(btn, false);
      if (err && err.status === 401) return;
      formErr('os-sign-err', err && err.status === 422 ? 'Nu am putut folosi semnătura. Desenează sau scrie-o din nou și încearcă iar.' : saveError(err));
    });
  });

  /* ----- documents ----- */
  function docBox(type) { return root.querySelector('[data-doc="' + type + '"]'); }
  function renderDoc(type, url) {
    var box = docBox(type), tagBox = box.querySelector('[data-doc-tag]'), view = box.querySelector('[data-doc-view]');
    tagBox.textContent = '';
    tagBox.appendChild(tag(url ? 'Încărcat' : 'Lipsește', url ? 'is-ok' : 'is-wait'));
    view.hidden = !url;
    if (url) view.href = url;
    box.querySelector('[data-doc-cta]').textContent = url ? 'Înlocuiește fișierul' : 'Alege fișierul';
  }
  function docStatus(type, text, cls) { var s = docBox(type).querySelector('[data-doc-status]'); s.textContent = text || ''; s.className = 'os-doc-s' + (cls ? ' ' + cls : ''); }
  function upload(type, file) {
    var box = docBox(type);
    if (!file || box.classList.contains('is-busy')) return;
    var okType = /^(application\/pdf|image\/jpe?g|image\/png)$/i.test(file.type || '') || (!file.type && /\.(pdf|jpe?g|png)$/i.test(file.name || ''));
    if (!okType) { docStatus(type, 'Alege un fișier PDF, JPG sau PNG.', 'is-bad'); return; }
    if (file.size > 5 * 1024 * 1024) { docStatus(type, 'Fișierul are ' + new Intl.NumberFormat('ro-RO', { maximumFractionDigits: 1 }).format(file.size / 1048576) + ' MB; limita este 5 MB.', 'is-bad'); return; }
    var tk = token();
    if (!tk) { O.flash('Sesiunea a expirat. Autentifică-te din nou.', true); return; }
    var fd = new FormData();
    fd.append('file', file, file.name || 'document');
    fd.append('type', type);
    box.classList.add('is-busy');
    docStatus(type, 'Se încarcă ' + (file.name || 'documentul') + '…', 'is-busy');
    fetch(apiUrl() + '?action=organizer.documents.upload', { method: 'POST', body: fd, credentials: 'same-origin', headers: { Authorization: 'Bearer ' + tk, Accept: 'application/json' } }).then(function (res) {
      return res.text().then(function (t) {
        var body = null;
        try { body = JSON.parse(t); } catch (e) { body = null; }
        if (!res.ok) throw { status: res.status, message: body ? body.message || body.error || '' : '' };
        if (!body || body.success === false) throw { status: res.status, html: !body };
        return body;
      });
    }, function () { throw { status: 0 }; }).then(function (body) {
      box.classList.remove('is-busy');
      var d = body.data || {};
      if (!contract) contract = {};
      contract.documents = contract.documents || {};
      contract.documents[DOC_KEY[type]] = safeUrl(d.url) || contract.documents[DOC_KEY[type]] || 'https://' + location.host + '/';
      renderDoc(type, safeUrl(contract.documents[DOC_KEY[type]]));
      renderSummary();
      docStatus(type, 'Documentul a fost încărcat.' + (d.both_documents_uploaded ? ' Ai încărcat ambele documente: contractul se generează în câteva minute.' : ''), 'is-ok');
      if (d.both_documents_uploaded) setTimeout(loadContract, 1500);
    }).catch(function (err) {
      box.classList.remove('is-busy');
      function failed(text) { docStatus(type, text || 'Nu am putut încărca documentul. Încearcă din nou.', 'is-bad'); }
      if (err && err.status === 401) { failed('Sesiunea a expirat. Te trimitem la autentificare.'); setTimeout(function () { O.api('/organizer/me').catch(function () {}); }, 1500); return; }
      if (err && err.html) { O.api('/organizer/me', { quiet: true }).then(function () { failed(); }, function (e) { if (e && e.status === 401) { failed('Sesiunea a expirat. Te trimitem la autentificare.'); setTimeout(function () { O.api('/organizer/me').catch(function () {}); }, 1500); } else failed(); }); return; }
      if (err && err.status === 413) return failed('Fișierul e prea mare pentru server. Alege unul mai mic de 5 MB.');
      if (err && err.status === 422) return failed('Fișierul nu a fost acceptat. Alege un PDF, JPG sau PNG de cel mult 5 MB.');
      if (err && err.status === 0) return failed('Nu am putut ajunge la server. Verifică conexiunea și încearcă din nou.');
      failed();
    });
  }
  qsa('[data-doc]', root).forEach(function (box) {
    var type = box.getAttribute('data-doc'), input = box.querySelector('[data-doc-input]'), drop = box.querySelector('[data-drop]');
    input.addEventListener('change', function () { if (input.files && input.files[0]) upload(type, input.files[0]); input.value = ''; });
    ['dragenter', 'dragover'].forEach(function (t) { drop.addEventListener(t, function (e) { e.preventDefault(); drop.classList.add('is-over'); }); });
    ['dragleave', 'dragend'].forEach(function (t) { drop.addEventListener(t, function () { drop.classList.remove('is-over'); }); });
    drop.addEventListener('drop', function (e) {
      e.preventDefault();
      drop.classList.remove('is-over');
      var f = e.dataTransfer && e.dataTransfer.files && e.dataTransfer.files[0];
      if (f) upload(type, f);
    });
  });

  /* =================== NOTIFICATIONS =================== */
  function renderTypes(list) {
    var box = $('os-ntypes');
    box.textContent = '';
    (list && list.length ? list : NTYPES_FALLBACK).forEach(function (t) { box.appendChild(el('li', { text: t })); });
  }
  function loadTypes() {
    O.api('/organizer/notifications/types', { quiet: true }).then(function (r) {
      var t = r && r.data && r.data.types;
      renderTypes(t && typeof t === 'object' ? Object.keys(t).map(function (k) { var s = F.flat(t[k]).trim(); return NTYPES[s] || s; }).filter(Boolean) : null);
    }, function () { renderTypes(null); });
  }
  function renderNotifMail() {
    var p = $('os-nmail'), email = F.flat(me && me.email).trim();
    p.textContent = '';
    p.appendChild(document.createTextNode('Primești email' + (email ? ' la ' : '')));
    if (email) p.appendChild(el('b', { text: email }));
    p.appendChild(document.createTextNode(' când o cerere de plată este înregistrată, aprobată, în procesare, plătită sau respinsă.'));
  }

  /* =================== SECURITY =================== */
  qsa('[data-eye]', root).forEach(function (b) {
    b.addEventListener('click', function () {
      var input = $(b.getAttribute('data-eye')), show = input.type === 'password';
      input.type = show ? 'text' : 'password';
      b.setAttribute('aria-pressed', String(show));
      b.setAttribute('aria-label', show ? 'Ascunde parola' : 'Arată parola');
      var use = b.querySelector('use');
      if (use) use.setAttribute('href', '#i-' + (show ? 'eye-slash' : 'eye'));
    });
  });
  function rules() {
    var v = $('os-pass-new').value;
    var state = { len: v.length >= 8, mix: /[a-zăâîșț]/.test(v) && /[A-ZĂÂÎȘȚ]/.test(v), num: /[\d\W_]/.test(v) };
    qsa('#os-rules [data-rule]', root).forEach(function (li) { li.classList.toggle('is-ok', !!state[li.getAttribute('data-rule')]); });
  }
  $('os-pass-new').addEventListener('input', rules);
  $('os-pass-form').addEventListener('submit', function (ev) {
    ev.preventDefault();
    var btn = $('os-pass-go'), st = { bad: null };
    if (isBusy(btn)) return;
    clearErrs(['os-pass-cur', 'os-pass-new', 'os-pass-conf'], 'os-pass-err');
    var cur = $('os-pass-cur').value, nw = $('os-pass-new').value, conf = $('os-pass-conf').value;
    need(st, 'os-pass-cur', cur ? '' : 'Scrie parola curentă.');
    need(st, 'os-pass-new', !nw ? 'Scrie parola nouă.' : nw.length < 8 ? 'Parola nouă trebuie să aibă cel puțin 8 caractere.' : nw === cur ? 'Parola nouă trebuie să fie diferită de cea curentă.' : '');
    need(st, 'os-pass-conf', !conf ? 'Scrie din nou parola nouă.' : conf !== nw ? 'Parolele nu se potrivesc.' : '');
    if (st.bad) { $(st.bad).focus(); return; }
    busyBtn(btn, true, 'Se schimbă…');
    O.api('/organizer/password', { method: 'PUT', body: { current_password: cur, password: nw, password_confirmation: conf } }).then(function () {
      busyBtn(btn, false);
      $('os-pass-form').reset();
      qsa('[data-eye]', $('os-pass-form')).forEach(function (b) { if (b.getAttribute('aria-pressed') === 'true') b.click(); });
      rules();
      O.flash('Parola a fost schimbată.');
    }).catch(function (err) {
      busyBtn(btn, false);
      if (err && err.status === 401) return;
      var m = errMessage(err);
      if (/current password/i.test(m) || (err && err.errors && err.errors.current_password)) { fieldErr('os-pass-cur', 'Parola curentă nu este corectă.'); $('os-pass-cur').focus(); return; }
      if (err && err.errors && err.errors.password) { fieldErr('os-pass-new', 'Alege o parolă de cel puțin 8 caractere, diferită de cele simple.'); $('os-pass-new').focus(); return; }
      formErr('os-pass-err', saveError(err));
    });
  });

  /* =================== SHARE LINKS =================== */
  function linkUrl(code) { return location.origin + '/view/' + code; }
  function loadLinks(newCode, wanted) {
    var box = $('os-share-list');
    return O.api('/organizer/share-links', { quiet: true }).then(function (r) {
      var d = r && r.data;
      links = (d && Array.isArray(d.links) ? d.links : Array.isArray(d) ? d : []).filter(function (l) { return l && /^[A-Za-z0-9]{6,20}$/.test(String(l.code)); });
      var keep = keepKey(box, wanted);
      renderLinks(newCode);
      restoreFocus(box, keep, $('os-share-add'));
      $('os-share-add').disabled = false;
    }, function (err) {
      if (err && err.status === 401) return;
      links = null;
      emptyBox(box, 'li', 'Nu am putut încărca linkurile.', function () { loadLinks(); });
    });
  }
  function updateLink(l, body, okText, btn) {
    var key = btn ? btn.getAttribute('data-focus') : null;
    if (btn) btn.disabled = true;
    return O.api('/organizer/share-links/' + l.code, { method: 'PUT', body: body }).then(function () {
      O.flash(okText);
      return loadLinks(null, key);
    }).catch(function (err) {
      if (btn) btn.disabled = false;
      if (err && err.status === 401) return;
      O.flash(err && err.status === 404 ? 'Linkul nu mai există.' : 'Nu am putut actualiza linkul. Încearcă din nou.', true);
      if (err && err.status === 404) loadLinks();
    });
  }
  function renderLinks(newCode) {
    var box = $('os-share-list');
    box.textContent = '';
    if (!links.length) {
      var cta = el('button', { class: 'btn btn-primary os-sm', type: 'button', 'data-focus': 'link-first' }, [icon('plus'), 'Creează primul link']);
      cta.addEventListener('click', function () { openShare(cta); });
      box.appendChild(el('li', { class: 'os-empty' }, [el('b', { text: 'Niciun link de monitorizare' }), el('p', { text: 'Creează un link pentru un partener sau un sponsor.' }), cta]));
      return;
    }
    links.forEach(function (l) {
      var active = l.is_active !== false && l.is_active !== 0, n = Array.isArray(l.event_ids) ? l.event_ids.length : 0, url = linkUrl(l.code), name = F.flat(l.name).trim() || 'Link';
      var meta = [F.count(n, 'activitate', 'activități'), F.count(l.access_count, 'accesare', 'accesări'), l.created_at ? 'creat pe ' + dayLabel(l.created_at) : '', l.last_accessed_at ? 'deschis ultima dată ' + F.ago(l.last_accessed_at) : ''].filter(Boolean).join(' · ');
      var copy = pill('copy', 'Copiază', { 'data-focus': 'link-copy-' + l.code, 'aria-label': 'Copiază linkul ' + name });
      copy.addEventListener('click', function () { copyText(url).then(function () { O.flash('Linkul a fost copiat.'); }, function () { O.flash('Nu am putut copia. Selectează linkul și copiază-l manual.', true); }); });
      var openA = el('a', { class: 'os-pill', href: url, target: '_blank', rel: 'noopener', 'data-focus': 'link-open-' + l.code, 'aria-label': 'Deschide linkul ' + name + ' într-o filă nouă' }, [icon('arrow-up-right'), 'Deschide']);
      var refresh = pill('arrow-counter-clockwise', 'Actualizează datele', { 'data-focus': 'link-refresh-' + l.code, 'aria-label': 'Actualizează datele linkului ' + name });
      refresh.addEventListener('click', function () { updateLink(l, { refresh_data: true }, 'Datele linkului au fost actualizate.', refresh); });
      var toggle = pill(active ? 'prohibit' : 'check-circle', active ? 'Oprește' : 'Pornește', { 'data-focus': 'link-toggle-' + l.code, 'aria-label': (active ? 'Oprește' : 'Pornește') + ' linkul ' + name });
      toggle.addEventListener('click', function () { updateLink(l, { is_active: !active }, active ? 'Linkul a fost oprit. Cine îl deschide vede că nu mai este activ.' : 'Linkul a fost pornit.', toggle); });
      var del = pill('trash', 'Șterge', { class: 'os-pill is-danger', 'data-focus': 'link-del-' + l.code, 'aria-label': 'Șterge linkul ' + name });
      del.addEventListener('click', function () { openDelete('link', l, del); });
      box.appendChild(el('li', { class: 'os-share' + (active ? '' : ' is-off') + (newCode && newCode === l.code ? ' is-new' : '') }, [
        el('div', { class: 'os-share-top' }, [el('b', { text: name }), tag(active ? 'Activ' : 'Oprit', active ? 'is-ok' : 'is-muted'), l.has_password ? tag('Cu parolă', 'is-info') : null, l.show_participants ? tag('Participanți', 'is-wait') : null, l.show_revenue ? tag('Încasări', 'is-info') : null]),
        el('p', { class: 'os-meta', text: meta }),
        el('div', { class: 'os-url' }, [icon('link'), el('code', { text: url, title: url })]),
        el('div', { class: 'os-share-act' }, [copy, openA, refresh, toggle, del]),
      ]));
    });
  }
  function isOver(ev) {
    if (ev.is_cancelled || ev.is_postponed || ev.is_past || ev.is_ended) return true;
    if (['ended', 'cancelled', 'postponed', 'archived'].indexOf(String(ev.status || '')) > -1) return true;
    var end = naiveDay(ev.ends_at || ev.starts_at || ev.start_date);
    return !!end && end < F.ymd();
  }
  function loadEvents() {
    if (eventsReq) return eventsReq;
    var got = [];
    function page(n) {
      return O.api('/organizer/events?per_page=50&page=' + n, { quiet: true }).then(function (r) {
        got = got.concat(Array.isArray(r && r.data) ? r.data : []);
        if (F.toNum(O.metaOf(r).last_page) > n && n < 20) return page(n + 1);
      });
    }
    eventsReq = page(1).then(function () {
      events = got.filter(function (e) { return e && /^\d+$/.test(String(e.id)) && !isOver(e); }).sort(function (a, b) {
        var ad = String(a.starts_at || ''), bd = String(b.starts_at || '');
        return ad < bd ? -1 : ad > bd ? 1 : 0;
      });
      return events;
    }, function (err) { eventsReq = null; throw err; });
    return eventsReq;
  }
  function pickedIds() { return Object.keys(picked).filter(function (k) { return picked[k]; }); }
  function renderPicks() {
    var box = $('os-picks'), q = norm(val('os-pq')), n = pickedIds().length, full = n >= MAX_LINK_EVENTS;
    box.textContent = '';
    $('os-psearch').hidden = !(events && events.length > 8);
    if (!events.length) { box.appendChild(el('li', { class: 'os-picks-msg', text: 'Nu ai activități în desfășurare sau viitoare.' })); $('os-picks-n').textContent = ''; return; }
    var shown = events.filter(function (e) { return !q || norm(F.flat(e.name || e.title) + ' ' + F.flat(e.venue_name) + ' ' + F.flat(e.venue_city)).indexOf(q) > -1; });
    if (!shown.length) box.appendChild(el('li', { class: 'os-picks-msg', text: 'Nicio activitate nu se potrivește căutării.' }));
    shown.forEach(function (e) {
      var id = String(e.id), on = !!picked[id], day = naiveDay(e.starts_at);
      var cb = el('input', { type: 'checkbox', value: id });
      cb.checked = on;
      cb.disabled = full && !on;
      cb.addEventListener('change', function () { picked[id] = cb.checked; fieldErr('os-picks', ''); renderPicks(); var again = box.querySelector('input[value="' + id + '"]'); if (again) again.focus(); });
      box.appendChild(el('li', null, el('label', { class: 'os-pick' }, [cb, el('span', null, [el('b', { text: F.flat(e.name || e.title) || 'Activitatea #' + id }), el('small', { text: [day ? dayLabel(day) : '', F.flat(e.venue_name), F.flat(e.venue_city)].filter(Boolean).join(' · ') })])])));
    });
    $('os-picks-n').textContent = n ? F.count(n, 'activitate aleasă', 'activități alese') + (full ? ' · ai atins maximul de ' + MAX_LINK_EVENTS : ' · cel mult ' + MAX_LINK_EVENTS) : 'Alege cel puțin o activitate (cel mult ' + MAX_LINK_EVENTS + ').';
  }
  $('os-pq').addEventListener('input', renderPicks);
  function openShare(from) {
    picked = {};
    setVal('os-sname', '');
    setVal('os-spass', '');
    setVal('os-pq', '');
    $('os-sp-participants').checked = false;
    $('os-sp-revenue').checked = false;
    clearErrs(['os-sname', 'os-picks'], 'os-share-err');
    fallbackFocus = $('os-share-add');
    openDialog($('os-share-d'), from);
    $('os-sname').focus();
    var box = $('os-picks');
    if (!events) { box.textContent = ''; box.appendChild(el('li', { class: 'os-picks-msg', text: 'Se încarcă activitățile…' })); }
    loadEvents().then(renderPicks, function () {
      box.textContent = '';
      var retry = el('button', { class: 'os-linkbtn', type: 'button', text: 'Reîncearcă' });
      retry.addEventListener('click', function () { box.textContent = ''; box.appendChild(el('li', { class: 'os-picks-msg', text: 'Se încarcă activitățile…' })); loadEvents().then(renderPicks, function () { emptyBox(box, 'li', 'Nu am putut încărca activitățile.'); }); });
      box.appendChild(el('li', { class: 'os-picks-msg' }, ['Nu am putut încărca activitățile. ', retry]));
    });
  }
  $('os-share-add').addEventListener('click', function () { openShare(this); });
  $('os-share-form').addEventListener('submit', function (ev) {
    ev.preventDefault();
    var d = $('os-share-d'), btn = $('os-share-go'), ids = pickedIds();
    if (isBusy(btn)) return;
    clearErrs(['os-sname', 'os-picks'], 'os-share-err');
    if (!ids.length) { fieldErr('os-picks', 'Alege cel puțin o activitate.'); var first = $('os-picks').querySelector('input'); if (first) first.focus(); return; }
    var body = { event_ids: ids.slice(0, MAX_LINK_EVENTS).map(Number) };
    if (val('os-sname')) body.name = val('os-sname');
    if ($('os-spass').value.trim()) body.password = $('os-spass').value.trim();
    if ($('os-sp-participants').checked) body.show_participants = true;
    if ($('os-sp-revenue').checked) body.show_revenue = true;
    busyBtn(btn, true, 'Se generează…');
    d.setAttribute('data-busy', '');
    O.api('/organizer/share-links', { method: 'POST', body: body }).then(function (r) {
      d.removeAttribute('data-busy');
      busyBtn(btn, false);
      closeDialog(d);
      var data = (r && r.data) || {}, link = data.data || data.link || data, code = link && /^[A-Za-z0-9]{6,20}$/.test(String(link.code)) ? String(link.code) : '';
      loadLinks(code);
      if (!code) { O.flash('Linkul a fost creat.'); return; }
      copyText(linkUrl(code)).then(function () { O.flash('Linkul a fost creat și copiat. Îl poți trimite partenerului.'); }, function () { O.flash('Linkul a fost creat. Copiază-l din listă.'); });
    }).catch(function (err) {
      d.removeAttribute('data-busy');
      busyBtn(btn, false);
      if (err && err.status === 401) return;
      if (/maximum 50/i.test(errMessage(err))) { formErr('os-share-err', 'Poți avea cel mult 50 de linkuri. Șterge unul pe care nu îl mai folosești.'); return; }
      formErr('os-share-err', saveError(err));
      markServer(err, { name: 'os-sname', event_ids: 'os-picks' });
    });
  });

  /* =================== DIALOGS =================== */
  function openDialog(d, from) {
    opener = from || document.activeElement;
    openerKey = opener && opener.getAttribute ? opener.getAttribute('data-focus') : null;
    if (typeof d.showModal === 'function') d.showModal(); else d.setAttribute('open', '');
  }
  function closeDialog(d) {
    if (!d.open && !d.hasAttribute('open')) return;
    if (typeof d.close === 'function') d.close();
    else { d.removeAttribute('open'); d.dispatchEvent(new Event('close')); }
  }
  qsa('.os-dialog', root).forEach(function (d) {
    d.addEventListener('click', function (e) { if (!d.hasAttribute('data-busy') && e.target.closest('[data-close]')) closeDialog(d); });
    d.addEventListener('cancel', function (e) { if (d.hasAttribute('data-busy')) e.preventDefault(); });
    d.addEventListener('close', function () {
      var back = opener && document.contains(opener) ? opener : (openerKey && root.querySelector('[data-focus="' + openerKey + '"]')) || fallbackFocus;
      opener = null;
      openerKey = null;
      if (back && typeof back.focus === 'function' && !back.disabled) back.focus();
    });
  });
  function openDelete(kind, item, from) {
    var bank = kind === 'bank', name = bank ? F.flat(item.bank) || 'contul' : F.flat(item.name).trim() || 'linkul';
    delTarget = { kind: kind, item: item };
    $('os-del-h').textContent = bank ? 'Ștergi contul bancar?' : 'Ștergi linkul?';
    $('os-del-p').textContent = bank
      ? '„' + name + '” (' + groupIban(item.iban) + ') dispare din cont. Plățile deja făcute nu sunt afectate.' + (item.is_primary && accounts && accounts.length > 1 ? ' Alt cont devine principal.' : item.is_primary ? ' Fără un cont bancar nu poți cere plăți.' : '')
      : '„' + name + '” nu se mai deschide pentru nimeni. Dacă vrei doar să-l pui pe pauză, folosește „Oprește”.';
    $('os-del-go').querySelector('[data-label]').textContent = bank ? 'Șterge contul' : 'Șterge linkul';
    formErr('os-del-err', '');
    fallbackFocus = bank ? $('os-bank-add') : $('os-share-add');
    openDialog($('os-del-d'), from);
    $('os-del-d').querySelector('[data-close]').focus();
  }
  $('os-del-go').addEventListener('click', function () {
    var btn = this, d = $('os-del-d'), t = delTarget;
    if (!t || isBusy(btn)) return;
    var bank = t.kind === 'bank', path = bank ? '/organizer/bank-accounts/' + t.item.id : '/organizer/share-links/' + t.item.code;
    busyBtn(btn, true, 'Se șterge…');
    d.setAttribute('data-busy', '');
    O.api(path, { method: 'DELETE' }).then(function () {
      d.removeAttribute('data-busy');
      busyBtn(btn, false);
      closeDialog(d);
      O.flash(bank ? 'Contul bancar a fost șters.' : 'Linkul a fost șters.');
      if (bank) loadAccounts(); else loadLinks();
    }).catch(function (err) {
      d.removeAttribute('data-busy');
      busyBtn(btn, false);
      if (err && err.status === 401) return;
      if (err && err.status === 404) { closeDialog(d); O.flash(bank ? 'Contul fusese deja șters.' : 'Linkul fusese deja șters.'); if (bank) loadAccounts(); else loadLinks(); return; }
      formErr('os-del-err', err && err.status === 429 ? 'Prea multe încercări într-un timp scurt. Așteaptă un minut și încearcă din nou.' : err && (err.status === 0 || err.status == null) ? 'Nu am putut ajunge la server. Verifică conexiunea și încearcă din nou.' : 'Nu am putut șterge. Încearcă din nou.');
    });
  });

  /* =================== START =================== */
  function start() {
    var k = tabFromHash();
    if (k) openTab(k, false);
    renderSummary();
    loadMe();
    loadAccounts();
    loadContract();
    loadTypes();
    loadLinks();
  }
  if (!has(window, 'BO_ORG')) return;
  O.ready.then(function (ok) { if (ok) start(); });
})();
