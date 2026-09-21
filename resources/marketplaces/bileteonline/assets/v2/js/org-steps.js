/* bilete.online v2: the operator's start checklist on /organizator/panou ("Pașii tăi de pornire"). Seven steps, in the
   order an operator has to do them, each one read from the API and never guessed:
   - /organizer/me: the company fields, the payout details, the widget settings
   - /organizer/contract: whether a signature is required and whether it is already there
   - /organizer/activities-module/locations and /products: created, waiting for approval, rejected, approved, published
   - /organizer/activities-module/summary (the last year): whether anything has ever been sold
   A step whose source failed stays "de verificat" — the panel never turns into an error page. When all seven are done
   the panel goes away for good (remembered per operator in localStorage, next to the derived state).
   Markup in organizer/dashboard.php, styles in assets/v2/css/org-steps.css. Text from the API is written as text. */
(function () {
  'use strict';
  var O = window.BO_ORG, root = document.getElementById('ob');
  if (!O || !root) return;
  var el = O.el, icon = O.icon, F = O.fmt;
  var $ = function (id) { return document.getElementById(id); };
  var KEY = 'bo_org_start_v1_';
  var TOTAL = 7;
  var st = { profile: undefined, contract: undefined, locations: undefined, products: undefined, sales: undefined };
  var orgId = '', settled = false, shown = false, gone = false, pending = 0;

  function recall(k) { try { return localStorage.getItem(k); } catch (e) { return null; } }
  function remember(k, v) { try { localStorage.setItem(k, v); } catch (e) {} }
  function doneKey() { return KEY + (orgId || 'x'); }

  /* ---------- small helpers ---------- */
  function txt(v) { return v == null ? '' : String(v).trim(); }
  function has(o, k) { return !!(o && txt(o[k])); }
  function arr(v) { return Array.isArray(v) ? v : []; }
  function pick(a, fn) { for (var i = 0; i < a.length; i++) { if (fn(a[i])) return a[i]; } return null; }
  function tally(a, fn) { var n = 0; for (var i = 0; i < a.length; i++) { if (fn(a[i])) n += 1; } return n; }
  // "on sale" means the same here as on the panel and in the catalogue: published, approved, and not kept for the desk
  function live(x) { return !!(x && x.is_published && !x.pos_only && (!x.review_status || x.review_status === 'approved')); }
  function nameOf(x) { return F.flat(x && (x.name || x.title)) || ''; }
  function ymdBack(days) { var d = new Date(); d.setDate(d.getDate() - days); return F.ymd(d); }

  /* ---------- the seven steps ---------- */
  /** The shared "sent for approval / approved / published" reading of a location or a product. */
  function approval(items, w) {
    if (!items) return { state: 'unknown' };
    var ok = pick(items, live);
    if (ok) {
      var n = tally(items, live);
      return { state: 'done', detail: n > 1 ? 'Ai ' + F.count(n, w.one + ' publicat' + w.a, w.many + ' publicate') + '.' : w.Cap + ' „' + (nameOf(ok) || w.one) + '” este publicat' + w.a + '.' };
    }
    if (!items.length) return { state: 'todo', detail: w.first };
    var rej = pick(items, function (x) { return x.review_status === 'rejected'; });
    if (rej) {
      return { state: 'todo', href: w.base + '?id=' + encodeURIComponent(rej.id), cta: 'Corectează și retrimite',
        detail: 'Am respins „' + (nameOf(rej) || w.one) + '”' + (txt(rej.rejection_reason) ? ': ' + txt(rej.rejection_reason) : '.') + ' Corectează și trimite din nou.' };
    }
    var wait = pick(items, function (x) { return x.review_status === 'pending'; });
    if (wait) {
      return { state: 'todo', href: w.base + '?id=' + encodeURIComponent(wait.id), cta: 'Vezi ' + w.one,
        detail: w.Cap + ' „' + (nameOf(wait) || w.one) + '” așteaptă aprobarea noastră. Îți dăm un răspuns în 1–2 zile lucrătoare.' };
    }
    var appr = pick(items, function (x) { return x.review_status === 'approved'; });
    if (appr) {
      return { state: 'todo', href: w.base + '?id=' + encodeURIComponent(appr.id), cta: 'Publică',
        detail: w.Cap + ' „' + (nameOf(appr) || w.one) + '” e aprobat' + w.a + '. Mai apeși o dată pe Publică și apare pe bilete.online.' };
    }
    return { state: 'todo', href: w.base + '?id=' + encodeURIComponent(items[0].id), cta: 'Trimite spre aprobare', detail: w.draft };
  }

  function steps() {
    var p = st.profile, c = st.contract, L = st.locations, P = st.products, S = st.sales;
    var out = [];

    /* 1. company data + signed contract — /organizer/me and /organizer/contract */
    var s1 = { key: 'cont', title: 'Datele firmei și contractul', href: '/organizator/setari#company', cta: 'Deschide setările',
      why: 'Le completezi o singură dată: fără ele nu putem emite bilete și facturi în numele tău.' };
    var companyOk = p ? (has(p, 'company_name') && has(p, 'company_tax_id') && has(p, 'company_address') && has(p, 'company_city')) : null;
    var signOk = c ? (c.is_signed === true || c.signature_required === false) : null;
    if (companyOk === null || signOk === null) s1.state = 'unknown';
    else if (!companyOk) { s1.state = 'todo'; s1.detail = 'Mai lipsesc datele firmei: denumire, CUI, adresă și oraș.'; s1.cta = 'Completează datele'; }
    else if (!signOk) {
      s1.state = 'todo';
      s1.href = '/organizator/setari#contract';
      s1.cta = c.has_contract ? 'Semnează contractul' : 'Încarcă documentele';
      s1.detail = c.has_contract ? 'Datele firmei sunt complete. Mai rămâne să semnezi contractul.' : 'Datele firmei sunt complete. Încarcă CI și certificatul CUI ca să-ți generăm contractul.';
    } else { s1.state = 'done'; s1.detail = c.is_signed ? 'Datele firmei sunt complete și contractul e semnat.' : 'Datele firmei sunt complete, iar contractul tău nu cere semnătură.'; }
    out.push(s1);

    /* 2. a location exists — /organizer/activities-module/locations */
    var s2 = { key: 'loc', title: 'Prima ta locație', href: '/organizator/locatii?nou=1', cta: 'Adaugă locația',
      why: 'Locația e locul în care vin clienții: adresă, program, poze. Toate produsele stau sub ea.' };
    if (!L) s2.state = 'unknown';
    else if (!L.length) { s2.state = 'todo'; s2.detail = 'Nu ai nicio locație încă.'; }
    else { s2.state = 'done'; s2.detail = 'Ai ' + F.count(L.length, 'locație', 'locații') + '.'; s2.href = '/organizator/locatii'; }
    out.push(s2);

    /* 3. that location approved and published */
    var a3 = approval(L, { base: '/organizator/locatii', one: 'locație', many: 'locații', Cap: 'Locația', a: 'ă',
      first: 'Întâi creează locația, apoi o trimiți spre aprobare.',
      draft: 'Locația e încă ciornă. Deschide-o și apasă „Trimite spre aprobare”.' });
    out.push({ key: 'loc-ok', title: 'Locația aprobată și publicată', state: a3.state, detail: a3.detail,
      why: 'O verificăm înainte să apară pe bilete.online; după aprobare o publici tu, cu un buton.',
      href: a3.href || '/organizator/locatii', cta: a3.cta || 'Deschide locațiile' });

    /* 4. a product exists — /organizer/activities-module/products */
    var s4 = { key: 'prod', title: 'Primul produs', href: '/organizator/produse?nou=1', cta: 'Adaugă un produs',
      why: 'Produsele sunt ce vinzi: bilete de acces, experiențe sau pachete, fiecare cu prețul și programul lui.' };
    if (!P) s4.state = 'unknown';
    else if (!P.length) {
      s4.state = 'todo';
      s4.detail = L && !L.length ? 'Întâi ai nevoie de o locație, apoi adaugi produsul sub ea.' : 'Nu ai niciun produs încă.';
      if (L && !L.length) { s4.href = '/organizator/locatii?nou=1'; s4.cta = 'Adaugă locația'; }
    } else { s4.state = 'done'; s4.detail = 'Ai ' + F.count(P.length, 'produs', 'produse') + '.'; s4.href = '/organizator/produse'; }
    out.push(s4);

    /* 5. that product approved and published */
    var a5 = approval(P, { base: '/organizator/produse', one: 'produs', many: 'produse', Cap: 'Produsul', a: '',
      first: 'Întâi creează produsul, apoi îl trimiți spre aprobare.',
      draft: 'Produsul e încă ciornă. Deschide-l și apasă „Trimite spre aprobare”.' });
    out.push({ key: 'prod-ok', title: 'Produsul aprobat și publicat', state: a5.state, detail: a5.detail,
      why: 'Un produs publicat se poate cumpăra: pe bilete.online, în widget-ul de pe site-ul tău și la casă.',
      href: a5.href || '/organizator/produse', cta: a5.cta || 'Deschide produsele' });

    /* 6. payout details — /organizer/me */
    var s6 = { key: 'iban', title: 'Contul bancar pentru încasări', href: '/organizator/setari#bank', cta: 'Adaugă IBAN-ul',
      why: 'Aici îți trimitem banii din vânzări, la fiecare decont.' };
    if (!p || typeof p.has_payout_details !== 'boolean') s6.state = 'unknown';
    else if (p.has_payout_details) { s6.state = 'done'; s6.detail = 'Datele de plată sunt completate.'; }
    else { s6.state = 'todo'; s6.detail = 'Nu ai încă un cont bancar salvat.'; }
    out.push(s6);

    /* 7. the widget on their own site, or a first sale — /organizer/me and .../summary */
    var s7 = { key: 'sale', title: 'Prima vânzare', href: '/organizator/widget-uri', cta: 'Configurează widget-ul',
      why: 'Vinzi în trei feluri: pe bilete.online, cu widget-ul de pe site-ul tău și la casă, din Casă & POS.' };
    var sold = S ? Math.round(F.toNum(S.bookings)) : null;
    var set = p && p.settings && typeof p.settings === 'object' ? p.settings : null;
    var doms = set ? arr(set.embed_domains).length : 0;
    var widgetOk = set ? (set.widget_enabled === true && doms > 0) : null;
    if (sold > 0) { s7.state = 'done'; s7.detail = 'Ai ' + F.count(sold, 'rezervare', 'rezervări') + ' în ultimul an.'; s7.href = '/organizator/rezervari'; s7.cta = 'Vezi rezervările'; }
    else if (widgetOk) { s7.state = 'done'; s7.detail = 'Widget-ul e pornit pe ' + F.count(doms, 'site', 'site-uri') + '.'; }
    else if (sold === null || widgetOk === null) s7.state = 'unknown';
    else { s7.state = 'todo'; s7.detail = 'Nicio rezervare încă. Pune widget-ul pe site-ul tău sau trimite clienților linkul locației.'; }
    out.push(s7);

    return out;
  }

  /* ---------- drawing ---------- */
  var STATE_LABEL = { done: 'Gata', todo: 'De făcut', unknown: 'De verificat' };

  function row(s, i, isNext) {
    var mark = s.state === 'done' ? el('span', { class: 'ob-mark' }, icon('check'))
      : el('span', { class: 'ob-mark', text: s.state === 'unknown' ? '?' : String(i + 1) });
    var body = [el('h3', { class: 'ob-t', text: s.title })];
    if (s.state !== 'done') body.push(el('p', { class: 'ob-why', text: s.why }));
    if (s.detail) body.push(el('p', { class: 'ob-p', text: s.detail }));
    if (s.state === 'unknown') body.push(el('p', { class: 'ob-p', text: 'Nu am putut verifica pasul ăsta acum.' }));
    if (s.state !== 'done' && s.href) {
      body.push(isNext
        ? el('a', { class: 'btn btn-primary ob-cta', href: s.href }, [el('span', { text: s.cta || 'Deschide' }), icon('arrow-right')])
        : el('a', { class: 'ob-link', href: s.href }, [el('span', { text: s.cta || 'Deschide' }), icon('arrow-right')]));
    }
    return el('li', { class: 'ob-step is-' + s.state + (isNext ? ' is-next' : '') }, [
      mark,
      el('div', { class: 'ob-body' }, body),
      el('span', { class: 'ob-state', text: isNext ? 'Urmează' : STATE_LABEL[s.state] }),
    ]);
  }

  function draw() {
    if (gone) return;
    if (!orgId && st.profile && st.profile.id != null) { // the account is known only after /organizer/me on a fresh login
      orgId = String(st.profile.id);
      if (recall(doneKey()) === '1') { hideForGood(); return; }
    }
    if (!settled) { // nothing is shown until every source has answered: no flashing half-counts
      if (shown) $('ob-sub').textContent = 'Verificăm unde ai ajuns…';
      return;
    }
    var list = steps(), done = tally(list, function (s) { return s.state === 'done'; });
    var unknown = tally(list, function (s) { return s.state === 'unknown'; });
    var nextIdx = -1, i;
    for (i = 0; i < list.length; i++) { if (list[i].state !== 'done') { nextIdx = i; break; } }
    if (done === TOTAL) { hideForGood(); return; }

    $('ob-count').textContent = F.num(done) + ' din ' + TOTAL;
    $('ob-count').hidden = false;
    $('ob-fill').style.width = Math.round(done / TOTAL * 100) + '%';
    $('ob-track').hidden = false;

    var sub = $('ob-sub');
    sub.textContent = '';
    if (unknown) {
      sub.appendChild(document.createTextNode('Câțiva pași nu au putut fi verificați acum. '));
      var again = el('button', { class: 'ob-retry', type: 'button', text: 'Reîncearcă' });
      again.addEventListener('click', function () { again.disabled = true; load(); });
      sub.appendChild(again);
    } else sub.appendChild(document.createTextNode(nextIdx < 0 ? 'Ai terminat tot.' : 'Urmează: ' + list[nextIdx].title + '.'));

    var ul = $('ob-list');
    ul.textContent = '';
    list.forEach(function (s, n) { ul.appendChild(row(s, n, n === nextIdx)); });
    root.hidden = false;
    shown = true;
    $('ob-live').textContent = 'Pașii de pornire: ' + F.num(done) + ' din ' + TOTAL + ' gata.' + (nextIdx < 0 ? '' : ' Urmează: ' + list[nextIdx].title + '.');
  }

  function hideForGood() {
    gone = true;
    root.hidden = true;
    root.textContent = '';
    remember(doneKey(), '1');
  }

  /* ---------- loading ---------- */
  function settle() {
    pending -= 1;
    if (pending > 0) return;
    settled = true;
    draw();
    if (window.BO_TOUR && window.BO_TOUR.autostart) window.BO_TOUR.autostart();
  }
  function grab(path, take, key) {
    pending += 1;
    O.api(path, { quiet: true }).then(function (r) { st[key] = take((r && r.data) || {}); }, function () { st[key] = null; }).then(settle, settle);
  }
  function load() {
    settled = false;
    pending = 0;
    grab('/organizer/me', function (d) { return d.organizer || d; }, 'profile');
    grab('/organizer/contract', function (d) { return d; }, 'contract');
    grab('/organizer/activities-module/locations', function (d) { return arr(d.locations); }, 'locations');
    grab('/organizer/activities-module/products', function (d) { return arr(d.products); }, 'products');
    grab('/organizer/activities-module/summary?from=' + ymdBack(365) + '&to=' + F.ymd(), function (d) { return d.totals || null; }, 'sales');
    draw();
  }

  /* ---------- start ---------- */
  O.ready.then(function (ok) {
    if (!ok) return;
    var p0 = O.profile && O.profile();
    if (p0 && p0.id != null) orgId = String(p0.id);
    if (orgId && recall(doneKey()) === '1') { // nothing left to do on this account: no panel and no calls
      gone = true;
      if (window.BO_TOUR && window.BO_TOUR.autostart) window.BO_TOUR.autostart();
      return;
    }
    O.onProfile(function (o) { if (!orgId && o && o.id != null) orgId = String(o.id); });
    load();
  });
})();
