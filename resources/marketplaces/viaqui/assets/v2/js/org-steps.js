/* viaqui.com v2: the operator's start checklist on /organizator/panou ("Pașii tăi de pornire"). Seven steps, in the
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
      return { state: 'done', detail: n > 1 ? w.doneMany(n) : w.doneOne(nameOf(ok) || w.unnamed) };
    }
    if (!items.length) return { state: 'todo', detail: w.first };
    var rej = pick(items, function (x) { return x.review_status === 'rejected'; });
    if (rej) {
      var why = txt(rej.rejection_reason);
      return { state: 'todo', href: w.base + '?id=' + encodeURIComponent(rej.id), cta: VQ.t('Fix and send again'),
        detail: why
          ? VQ.t('We rejected “{name}”: {reason} Fix it and send it again.', { name: nameOf(rej) || w.unnamed, reason: /[.!?]$/.test(why) ? why : why + '.' })
          : VQ.t('We rejected “{name}”. Fix it and send it again.', { name: nameOf(rej) || w.unnamed }) };
    }
    var wait = pick(items, function (x) { return x.review_status === 'pending'; });
    if (wait) {
      return { state: 'todo', href: w.base + '?id=' + encodeURIComponent(wait.id), cta: w.see, detail: w.waiting(nameOf(wait) || w.unnamed) };
    }
    var appr = pick(items, function (x) { return x.review_status === 'approved'; });
    if (appr) {
      return { state: 'todo', href: w.base + '?id=' + encodeURIComponent(appr.id), cta: VQ.t('Publish'), detail: w.approved(nameOf(appr) || w.unnamed) };
    }
    return { state: 'todo', href: w.base + '?id=' + encodeURIComponent(items[0].id), cta: VQ.t('Send for approval'), detail: w.draft };
  }

  function steps() {
    var p = st.profile, c = st.contract, L = st.locations, P = st.products, S = st.sales;
    var out = [];

    /* 1. company data + signed contract — /organizer/me and /organizer/contract */
    var s1 = { key: 'cont', title: VQ.t('Company details and contract'), href: '/organizator/setari#company', cta: VQ.t('Open the settings'),
      why: VQ.t('You fill them in only once: without them we cannot issue tickets and invoices on your behalf.') };
    var companyOk = p ? (has(p, 'company_name') && has(p, 'company_tax_id') && has(p, 'company_address') && has(p, 'company_city')) : null;
    var signOk = c ? (c.is_signed === true || c.signature_required === false) : null;
    if (companyOk === null || signOk === null) s1.state = 'unknown';
    else if (!companyOk) { s1.state = 'todo'; s1.detail = VQ.t('Company details are still missing: name, tax ID, address and city.'); s1.cta = VQ.t('Fill in the details'); }
    else if (!signOk) {
      s1.state = 'todo';
      s1.href = '/organizator/setari#contract';
      s1.cta = c.has_contract ? VQ.t('Sign the contract') : VQ.t('Upload the documents');
      s1.detail = c.has_contract ? VQ.t('Company details are complete. All that is left is to sign the contract.') : VQ.t('Company details are complete. Upload your ID and the company registration certificate so we can generate your contract.');
    } else { s1.state = 'done'; s1.detail = c.is_signed ? VQ.t('Company details are complete and the contract is signed.') : VQ.t('Company details are complete, and your contract needs no signature.'); }
    out.push(s1);

    /* 2. a location exists — /organizer/activities-module/locations */
    var s2 = { key: 'loc', title: VQ.t('Your first venue'), href: '/organizator/locatii?nou=1', cta: VQ.t('Add the venue'),
      why: VQ.t('The venue is where customers come: address, opening hours, photos. Every product sits under it.') };
    if (!L) s2.state = 'unknown';
    else if (!L.length) { s2.state = 'todo'; s2.detail = VQ.t('You have no venue yet.'); }
    else { s2.state = 'done'; s2.detail = VQ.t('You have {n}.', { n: VQ.n(L.length, 'venue', 'venues') }); s2.href = '/organizator/locatii'; }
    out.push(s2);

    /* 3. that location approved and published */
    var a3 = approval(L, { base: '/organizator/locatii', unnamed: VQ.t('Untitled venue'), see: VQ.t('See the venue'),
      doneMany: function (n) { return VQ.t('You have {n} published.', { n: VQ.n(n, 'venue', 'venues') }); },
      doneOne: function (name) { return VQ.t('The venue “{name}” is published.', { name: name }); },
      waiting: function (name) { return VQ.t('The venue “{name}” is waiting for our approval. We reply within 1–2 working days.', { name: name }); },
      approved: function (name) { return VQ.t('The venue “{name}” is approved. Press Publish once more and it appears on Viaqui.', { name: name }); },
      first: VQ.t('Create the venue first, then send it for approval.'),
      draft: VQ.t('The venue is still a draft. Open it and press “Send for approval”.') });
    out.push({ key: 'loc-ok', title: VQ.t('Venue approved and published'), state: a3.state, detail: a3.detail,
      why: VQ.t('We check it before it appears on Viaqui; after approval you publish it yourself, with one button.'),
      href: a3.href || '/organizator/locatii', cta: a3.cta || VQ.t('Open the venues') });

    /* 4. a product exists — /organizer/activities-module/products */
    var s4 = { key: 'prod', title: VQ.t('Your first product'), href: '/organizator/produse?nou=1', cta: VQ.t('Add a product'),
      why: VQ.t('Products are what you sell: access tickets, experiences or packages, each with its own price and schedule.') };
    if (!P) s4.state = 'unknown';
    else if (!P.length) {
      s4.state = 'todo';
      s4.detail = L && !L.length ? VQ.t('You need a venue first, then you add the product under it.') : VQ.t('You have no product yet.');
      if (L && !L.length) { s4.href = '/organizator/locatii?nou=1'; s4.cta = VQ.t('Add the venue'); }
    } else { s4.state = 'done'; s4.detail = VQ.t('You have {n}.', { n: VQ.n(P.length, 'product', 'products') }); s4.href = '/organizator/produse'; }
    out.push(s4);

    /* 5. that product approved and published */
    var a5 = approval(P, { base: '/organizator/produse', unnamed: VQ.t('Untitled product'), see: VQ.t('See the product'),
      doneMany: function (n) { return VQ.t('You have {n} published.', { n: VQ.n(n, 'product', 'products') }); },
      doneOne: function (name) { return VQ.t('The product “{name}” is published.', { name: name }); },
      waiting: function (name) { return VQ.t('The product “{name}” is waiting for our approval. We reply within 1–2 working days.', { name: name }); },
      approved: function (name) { return VQ.t('The product “{name}” is approved. Press Publish once more and it appears on Viaqui.', { name: name }); },
      first: VQ.t('Create the product first, then send it for approval.'),
      draft: VQ.t('The product is still a draft. Open it and press “Send for approval”.') });
    out.push({ key: 'prod-ok', title: VQ.t('Product approved and published'), state: a5.state, detail: a5.detail,
      why: VQ.t('A published product can be bought: on Viaqui, in the widget on your own site and at the desk.'),
      href: a5.href || '/organizator/produse', cta: a5.cta || VQ.t('Open the products') });

    /* 6. payout details — /organizer/me */
    var s6 = { key: 'iban', title: VQ.t('Bank account for payouts'), href: '/organizator/setari#bank', cta: VQ.t('Add the IBAN'),
      why: VQ.t('This is where we send your sales money, with every payout.') };
    if (!p || typeof p.has_payout_details !== 'boolean') s6.state = 'unknown';
    else if (p.has_payout_details) { s6.state = 'done'; s6.detail = VQ.t('The payout details are filled in.'); }
    else { s6.state = 'todo'; s6.detail = VQ.t('You have no bank account saved yet.'); }
    out.push(s6);

    /* 7. the widget on their own site, or a first sale — /organizer/me and .../summary */
    var s7 = { key: 'sale', title: VQ.t('Your first sale'), href: '/organizator/widget-uri', cta: VQ.t('Set up the widget'),
      why: VQ.t('You sell in three ways: on Viaqui, with the widget on your own site and at the desk, from Desk & POS.') };
    var sold = S ? Math.round(F.toNum(S.bookings)) : null;
    var set = p && p.settings && typeof p.settings === 'object' ? p.settings : null;
    var doms = set ? arr(set.embed_domains).length : 0;
    var widgetOk = set ? (set.widget_enabled === true && doms > 0) : null;
    if (sold > 0) { s7.state = 'done'; s7.detail = VQ.t('You have {n} in the last year.', { n: VQ.n(sold, 'booking', 'bookings') }); s7.href = '/organizator/rezervari'; s7.cta = VQ.t('See the bookings'); }
    else if (widgetOk) { s7.state = 'done'; s7.detail = VQ.t('The widget is running on {n}.', { n: VQ.n(doms, 'site', 'sites') }); }
    else if (sold === null || widgetOk === null) s7.state = 'unknown';
    else { s7.state = 'todo'; s7.detail = VQ.t('No booking yet. Put the widget on your site or send customers the link to your venue.'); }
    out.push(s7);

    return out;
  }

  /* ---------- drawing ---------- */
  var STATE_LABEL = { done: VQ.t('Done'), todo: VQ.t('To do'), unknown: VQ.t('To check') };

  function row(s, i, isNext) {
    var mark = s.state === 'done' ? el('span', { class: 'ob-mark' }, icon('check'))
      : el('span', { class: 'ob-mark', text: s.state === 'unknown' ? '?' : String(i + 1) });
    var body = [el('h3', { class: 'ob-t', text: s.title })];
    if (s.state !== 'done') body.push(el('p', { class: 'ob-why', text: s.why }));
    if (s.detail) body.push(el('p', { class: 'ob-p', text: s.detail }));
    if (s.state === 'unknown') body.push(el('p', { class: 'ob-p', text: VQ.t('We could not check this step right now.') }));
    if (s.state !== 'done' && s.href) {
      body.push(isNext
        ? el('a', { class: 'btn btn-primary ob-cta', href: VQ.url(s.href) }, [el('span', { text: s.cta || VQ.t('Open') }), icon('arrow-right')])
        : el('a', { class: 'ob-link', href: VQ.url(s.href) }, [el('span', { text: s.cta || VQ.t('Open') }), icon('arrow-right')]));
    }
    return el('li', { class: 'ob-step is-' + s.state + (isNext ? ' is-next' : '') }, [
      mark,
      el('div', { class: 'ob-body' }, body),
      el('span', { class: 'ob-state', text: isNext ? VQ.t('Next') : STATE_LABEL[s.state] }),
    ]);
  }

  function draw() {
    if (gone) return;
    if (!orgId && st.profile && st.profile.id != null) { // the account is known only after /organizer/me on a fresh login
      orgId = String(st.profile.id);
      if (recall(doneKey()) === '1') { hideForGood(); return; }
    }
    if (!settled) { // nothing is shown until every source has answered: no flashing half-counts
      if (shown) $('ob-sub').textContent = VQ.t('Checking where you are…');
      return;
    }
    var list = steps(), done = tally(list, function (s) { return s.state === 'done'; });
    var unknown = tally(list, function (s) { return s.state === 'unknown'; });
    var nextIdx = -1, i;
    for (i = 0; i < list.length; i++) { if (list[i].state !== 'done') { nextIdx = i; break; } }
    if (done === TOTAL) { hideForGood(); return; }

    $('ob-count').textContent = VQ.t('{done} of {total}', { done: F.num(done), total: TOTAL });
    $('ob-count').hidden = false;
    $('ob-fill').style.width = Math.round(done / TOTAL * 100) + '%';
    $('ob-track').hidden = false;

    var sub = $('ob-sub');
    sub.textContent = '';
    if (unknown) {
      sub.appendChild(document.createTextNode(VQ.t('Some steps could not be checked right now.') + ' '));
      var again = el('button', { class: 'ob-retry', type: 'button', text: VQ.t('Try again') });
      again.addEventListener('click', function () { again.disabled = true; load(); });
      sub.appendChild(again);
    } else sub.appendChild(document.createTextNode(nextIdx < 0 ? VQ.t('You have finished everything.') : VQ.t('Next: {step}.', { step: list[nextIdx].title })));

    var ul = $('ob-list');
    ul.textContent = '';
    list.forEach(function (s, n) { ul.appendChild(row(s, n, n === nextIdx)); });
    root.hidden = false;
    shown = true;
    $('ob-live').textContent = VQ.t('Your first steps: {done} of {total} done.', { done: F.num(done), total: TOTAL }) + (nextIdx < 0 ? '' : ' ' + VQ.t('Next: {step}.', { step: list[nextIdx].title }));
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
