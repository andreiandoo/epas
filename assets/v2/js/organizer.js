/* bilete.online v2: operator area shell (/organizator/*). Guards the area (a signed-out visitor goes to the operator
   login and comes back after it), fills in the operator card ("Operator · <location>"), the account menu and the
   "account pending" banner from the session, /organizer/me and the operator's locations, keeps the open support tickets
   badge, loads the unread notifications (and refreshes them while the tab is visible), runs the search over the
   operator's products and locations, the two menus and the phone drawer. Exposes window.BO_ORG for the page scripts: ready (a promise, false while the visitor is sent to the
   login), api(), el() / icon() for building markup, the formatters and one toast. Text from the API is always written as
   text. Markup in includes/v2/organizer.php. */
(function () {
  'use strict';
  var root = document.getElementById('org');
  if (!root) return;
  var $ = function (id) { return document.getElementById(id); };
  var SVG = 'http://www.w3.org/2000/svg';
  var TZ = 'Europe/Bucharest';

  function auth() { return typeof BileteOnlineAuth !== 'undefined' ? BileteOnlineAuth : null; }
  function each(sel, fn) { [].forEach.call(document.querySelectorAll(sel), fn); }

  /* ---------- markup ---------- */
  function el(tag, attrs, kids) {
    var n = document.createElement(tag);
    Object.keys(attrs || {}).forEach(function (k) {
      var v = attrs[k];
      if (v == null || v === false) return;
      if (k === 'class') n.className = v;
      else if (k === 'text') n.textContent = v;
      else n.setAttribute(k, v === true ? '' : String(v));
    });
    [].concat(kids == null ? [] : kids).forEach(function (c) {
      if (c == null || c === false || c === '') return;
      n.appendChild(typeof c === 'string' || typeof c === 'number' ? document.createTextNode(String(c)) : c);
    });
    return n;
  }
  function icon(name, cls) {
    var s = document.createElementNS(SVG, 'svg'), u = document.createElementNS(SVG, 'use');
    s.setAttribute('class', cls || 'ic');
    s.setAttribute('aria-hidden', 'true');
    s.setAttribute('focusable', 'false');
    u.setAttribute('href', '#i-' + name);
    s.appendChild(u);
    return s;
  }

  /* ---------- formatting ---------- */
  var nf = new Intl.NumberFormat('ro-RO');
  var nf2 = new Intl.NumberFormat('ro-RO', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
  function toNum(v) { var n = typeof v === 'number' ? v : parseFloat(v); return isFinite(n) ? n : 0; }
  function num(v) { return nf.format(Math.round(toNum(v))); }
  function money(v) {
    var n = Math.round(toNum(v) * 100) / 100;
    return (n % 1 === 0 ? nf.format(n) : nf2.format(n)) + ' lei';
  }
  function pct(v, digits) {
    return new Intl.NumberFormat('ro-RO', { minimumFractionDigits: 0, maximumFractionDigits: digits == null ? 1 : digits }).format(toNum(v)) + '%';
  }
  /** "1 bilet", "5 bilete", "20 de bilete" (Romanian adds "de" when the last two digits are 00 or 20–99). */
  function count(n, one, many) {
    n = Math.round(toNum(n));
    if (n === 1) return '1 ' + one;
    var r = Math.abs(n) % 100;
    return nf.format(n) + (n !== 0 && (r === 0 || r >= 20) ? ' de ' : ' ') + many;
  }
  function flat(v) {
    if (v && typeof v === 'object') return String(v.ro || v.en || Object.keys(v).map(function (k) { return v[k]; }).filter(Boolean)[0] || '');
    return v == null ? '' : String(v);
  }
  function dateOf(v) {
    if (!v) return null;
    var d = /^\d{4}-\d{2}-\d{2}$/.test(String(v)) ? new Date(v + 'T12:00:00') : new Date(v);
    return isNaN(d.getTime()) ? null : d;
  }
  function fmtDate(d, opts) { return new Intl.DateTimeFormat('ro-RO', Object.assign({ timeZone: TZ }, opts || {})).format(d); }
  /** YYYY-MM-DD of a moment in Bucharest. */
  function ymd(d) {
    var p = {};
    new Intl.DateTimeFormat('en-GB', { timeZone: TZ, year: 'numeric', month: '2-digit', day: '2-digit' }).formatToParts(d || new Date()).forEach(function (x) { p[x.type] = x.value; });
    return p.year + '-' + p.month + '-' + p.day;
  }
  function ago(v) {
    var d = dateOf(v);
    if (!d) return '';
    var s = Math.round((Date.now() - d.getTime()) / 1000);
    if (s < 45) return 'chiar acum';
    var m = Math.round(s / 60);
    if (m < 60) return 'acum ' + count(m, 'minut', 'minute');
    var h = Math.round(m / 60);
    if (h < 24) return 'acum ' + count(h, 'oră', 'ore');
    var days = Math.round(h / 24);
    if (days === 1) return 'ieri';
    if (days < 7) return 'acum ' + count(days, 'zi', 'zile');
    return fmtDate(d, { day: 'numeric', month: 'short', year: ymd(d).slice(0, 4) === ymd().slice(0, 4) ? undefined : 'numeric' });
  }
  /** Only links inside the site; anything else falls back. */
  function safeHref(u, fallback) {
    if (typeof u !== 'string' || !u.trim()) return fallback;
    u = u.trim();
    if (/^\/(?!\/)/.test(u)) return u;
    try {
      var x = new URL(u);
      if (x.origin === window.location.origin) return x.pathname + x.search + x.hash;
    } catch (e) {}
    return fallback;
  }
  function img(path) {
    if (!path || typeof path !== 'string') return '';
    try { return typeof getStorageUrl === 'function' ? getStorageUrl(path) : path; } catch (e) { return path; }
  }
  function metaOf(r) { return (r && (r.meta || (r.data && !Array.isArray(r.data) && r.data.meta))) || {}; }

  /* ---------- toast ---------- */
  var flashTimer = 0;
  function flash(text, isError) {
    var f = $('org-flash');
    if (!f) return;
    clearTimeout(flashTimer);
    f.classList.toggle('is-error', !!isError);
    f.textContent = text;
    flashTimer = setTimeout(function () { f.textContent = ''; }, 6000);
  }

  /* ---------- session ---------- */
  var expiring = false;
  function expired() { // the token was refused: sign the organizer out here and send them to the login, back to this page after
    if (expiring) return;
    expiring = true;
    var a = auth();
    try {
      if (a && a.clearOrganizerSession) a.clearOrganizerSession();
      if (a && a.requireOrganizerAuth) { a.requireOrganizerAuth(); return; }
    } catch (e) {}
    window.location.href = '/autentificare?ca=venue';
  }
  /** GET by default, never from the client cache. {quiet: true}: a 401 doesn't end the session (secondary calls). */
  function api(path, opts) {
    opts = opts || {};
    if (typeof BileteOnlineAPI === 'undefined') return Promise.reject({ status: 0, message: 'API indisponibil' });
    var o = { method: (opts.method || 'GET').toUpperCase(), noCache: true };
    if (opts.body !== undefined) o.body = JSON.stringify(opts.body);
    return BileteOnlineAPI.request(path, o).catch(function (err) {
      if (err && err.status === 401 && !opts.quiet) expired();
      throw err;
    });
  }

  var allowed = false;
  try { var a0 = auth(); allowed = !!(a0 && a0.requireOrganizerAuth && a0.requireOrganizerAuth()); } catch (e) { allowed = false; }
  if (!allowed && !auth()) flash('Nu am putut încărca contul. Reîncarcă pagina.', true);
  var ready = Promise.resolve(allowed);

  /* ---------- organizer ---------- */
  var profile = null, profileCbs = [];
  function setProfile(o) {
    if (!o || typeof o !== 'object') return;
    profile = o;
    var name = String(o.public_name || o.name || o.company_name || o.contact_name || '').trim() || 'Operator';
    var words = name.split(/\s+/).map(function (w) { return w.replace(/[^\p{L}\p{N}]/gu, ''); }).filter(Boolean);
    var initials = (words.length > 1 ? words[0].charAt(0) + words[words.length - 1].charAt(0) : (words[0] || '').slice(0, 2)).toUpperCase() || '·';
    each('[data-org-initials]', function (n) { n.textContent = initials; });
    each('[data-org-name]', function (n) { n.textContent = name; });
    each('[data-org-email]', function (n) { n.textContent = o.email || ''; });
    drawPlan();
    var pending = $('org-pending');
    if (pending) pending.hidden = !(o.status && o.status !== 'active');
    profileCbs.forEach(function (cb) { try { cb(o); } catch (e) {} });
  }
  function onProfile(cb) { profileCbs.push(cb); if (profile) { try { cb(profile); } catch (e) {} } }

  /* ---------- the operator's locations and products (activities module) ---------- */
  var amLoad = null;
  /** {locations, products} of this operator, read once per page. */
  function amCatalog() {
    if (!amLoad) {
      amLoad = Promise.all([
        api('/organizer/activities-module/locations', { quiet: true }).then(function (r) { return (r && r.data && r.data.locations) || []; }, function () { return []; }),
        api('/organizer/activities-module/products', { quiet: true }).then(function (r) { return (r && r.data && r.data.products) || []; }, function () { return []; }),
      ]).then(function (res) { return { locations: res[0], products: res[1] }; });
    }
    return amLoad;
  }
  /** Under the name: "Operator · <location>" (the first one, "+N" for the others). */
  var planLocs = null;
  function drawPlan() {
    var text = 'Operator';
    if (planLocs && planLocs.length) text += ' · ' + (flat(planLocs[0].name) || 'locația ta') + (planLocs.length > 1 ? ' +' + (planLocs.length - 1) : '');
    each('[data-org-plan]', function (n) { n.textContent = text; });
  }

  /* ---------- badges ---------- */
  function setBadge(key, n) {
    each('[data-org-badge="' + key + '"]', function (b) {
      var v = Math.round(toNum(n)), show = n != null && v > 0;
      b.textContent = show ? nf.format(v) : '';
      if (show && b.getAttribute('data-sr')) b.appendChild(el('span', { class: 'sr', text: b.getAttribute('data-sr') }));
      b.hidden = !show;
    });
  }

  /* ---------- notifications ---------- */
  var N_TYPE = { ticket_sale: 'ticket', refund_request: 'arrow-left', document_generated: 'file-text', service_order: 'lightning', service_order_completed: 'check-circle', payout_request: 'wallet' };
  var N_TONE = { warning: 'is-warn', danger: 'is-bad', info: 'is-info' };
  var notif = { total: 0, items: [], at: 0, error: false, loading: null };
  function markRead(id) {
    return api('/organizer/notifications/' + encodeURIComponent(id) + '/read', { method: 'POST', body: {}, quiet: true }).catch(function () {});
  }
  function renderNotifications() {
    var list = $('org-notif-list'), n = notif.total;
    if (!list) return;
    $('org-dot').hidden = !(n > 0);
    $('org-bell-t').textContent = n > 0 ? 'Notificări, ' + count(n, 'necitită', 'necitite') : 'Notificări';
    $('org-notif-count').textContent = n > 0 ? count(n, 'nouă', 'noi') : '';
    list.textContent = '';
    if (!notif.items.length) {
      list.appendChild(el('p', { class: 'org-pop-empty', text: notif.error ? 'Nu am putut încărca notificările.' : 'Nu ai notificări noi.' }));
      return;
    }
    notif.items.forEach(function (x) {
      var href = safeHref(x.action_url, '/organizator/notificari');
      var link = el('a', { class: 'org-n', href: href }, [
        el('span', { class: 'org-n-ic ' + (N_TONE[x.color] || '') }, icon(N_TYPE[x.type] || 'bell')),
        el('span', { class: 'org-n-t' }, [
          el('b', { text: flat(x.title) || 'Notificare' }),
          x.message ? el('span', { class: 'org-n-msg', text: flat(x.message) }) : null,
          el('time', { datetime: x.created_at || null, text: x.time_ago || ago(x.created_at) }),
        ]),
        x.is_read ? null : el('span', { class: 'org-n-dot' }, el('span', { class: 'sr', text: 'necitită' })),
      ]);
      link.addEventListener('click', function (e) {
        if (x.is_read || x.id == null) return;
        if (e.ctrlKey || e.metaKey || e.shiftKey || e.button === 1) { markRead(x.id); return; }
        e.preventDefault(); // mark it read first: leaving the page could cancel the request
        var go = function () { window.location.href = href; };
        Promise.race([markRead(x.id), new Promise(function (r) { setTimeout(r, 1200); })]).then(go, go);
      });
      list.appendChild(link);
    });
  }
  function loadNotifications() {
    if (notif.loading) return notif.loading;
    notif.loading = api('/organizer/notifications?per_page=5&read=0', { quiet: true }).then(function (r) {
      var rows = Array.isArray(r && r.data) ? r.data : ((r && r.data && r.data.data) || []);
      notif.items = rows.slice(0, 5);
      var total = metaOf(r).total;
      notif.total = total != null ? toNum(total) : notif.items.length;
      notif.error = false;
    }, function () { notif.error = true; }).then(function () {
      notif.at = Date.now();
      notif.loading = null;
      renderNotifications();
    });
    return notif.loading;
  }

  /* ---------- menus ---------- */
  var openPop = null;
  function setPop(btn, pop, open, focusBack) {
    if (open && openPop && openPop.btn !== btn) setPop(openPop.btn, openPop.pop, false);
    pop.hidden = !open;
    btn.setAttribute('aria-expanded', String(open));
    if (open) openPop = { btn: btn, pop: pop };
    else if (openPop && openPop.btn === btn) openPop = null;
    if (!open && focusBack) btn.focus();
  }
  function bindPop(btnId, popId, onOpen) {
    var btn = $(btnId), pop = $(popId);
    if (!btn || !pop) return;
    btn.addEventListener('click', function () {
      var open = pop.hidden;
      setPop(btn, pop, open);
      if (open && onOpen) onOpen();
    });
    btn.parentNode.addEventListener('focusout', function (e) {
      if (!pop.hidden && e.relatedTarget && !btn.parentNode.contains(e.relatedTarget)) setPop(btn, pop, false);
    });
  }
  bindPop('org-bell', 'org-notif', function () { if (allowed && Date.now() - notif.at > 20000) loadNotifications(); });
  bindPop('org-user', 'org-usermenu');
  document.addEventListener('click', function (e) {
    if (openPop && !openPop.pop.contains(e.target) && !openPop.btn.contains(e.target)) setPop(openPop.btn, openPop.pop, false);
  });

  /* ---------- drawer (phones, tablets) ---------- */
  var side = $('org-side'), scrim = root.querySelector('.org-scrim'), burger = $('org-burger');
  var desk = window.matchMedia('(min-width: 1024px)');
  function drawer(open) {
    if (desk.matches) open = false;
    var was = root.classList.contains('is-drawer');
    root.classList.toggle('is-drawer', open);
    scrim.hidden = !open;
    burger.setAttribute('aria-expanded', String(open));
    document.documentElement.classList.toggle('org-lock', open);
    if (open && !was) {
      var first = side.querySelector('.org-link[aria-current="page"]') || side.querySelector('a[href]');
      setTimeout(function () { if (first) first.focus(); }, 60);
    } else if (!open && was && (side.contains(document.activeElement) || document.activeElement === document.body)) {
      burger.focus();
    }
  }
  [].forEach.call(root.querySelectorAll('[data-org-drawer]'), function (b) {
    b.addEventListener('click', function () { drawer(b.getAttribute('data-org-drawer') === 'open'); });
  });
  side.addEventListener('keydown', function (e) { // keep Tab inside the open drawer
    if (e.key !== 'Tab' || !root.classList.contains('is-drawer')) return;
    var f = [].filter.call(side.querySelectorAll('a[href], button:not([disabled])'), function (n) { return n.getClientRects().length > 0; });
    if (!f.length) return;
    if (e.shiftKey && document.activeElement === f[0]) { f[f.length - 1].focus(); e.preventDefault(); }
    else if (!e.shiftKey && document.activeElement === f[f.length - 1]) { f[0].focus(); e.preventDefault(); }
  });
  var onDesk = function () { if (desk.matches) drawer(false); };
  if (desk.addEventListener) desk.addEventListener('change', onDesk); else if (desk.addListener) desk.addListener(onDesk);
  var nav = side.querySelector('.org-nav'), current = nav && nav.querySelector('[aria-current="page"]');
  if (current && current.offsetTop + current.offsetHeight > nav.clientHeight) nav.scrollTop = current.offsetTop - nav.clientHeight / 2;

  /* ---------- logout ---------- */
  each('[data-org-logout]', function (btn) {
    btn.addEventListener('click', function () {
      each('[data-org-logout]', function (b) { b.disabled = true; });
      var label = btn.querySelector('span:not(.sr)');
      if (label) label.textContent = 'Se deconectează…';
      var a = auth();
      if (a && typeof a.logoutOrganizer === 'function') a.logoutOrganizer(); // ends the session and goes to /
      else window.location.href = '/autentificare?ca=venue';
    });
  });

  /* ---------- activity search ---------- */
  var search = $('org-search'), q = $('org-q'), qPop = $('org-q-pop'), qList = $('org-q-list'), qMsg = $('org-q-msg'), qOpen = $('org-q-open'), top = $('org-top');
  var catalog = null, results = [], active = -1, qTimer = 0;
  var KIND = { access: 'Bilet de acces', experience: 'Experiență', package: 'Pachet' };
  var REVIEW = { draft: ['Ciornă', 'is-muted'], pending: ['În verificare', 'is-wait'], rejected: ['Respins', 'is-bad'] };
  function norm(s) { return String(s || '').toLowerCase().normalize('NFD').replace(/[̀-ͯ]/g, ''); }
  /** Locations and products as search rows: {kind, id, name, where, image, status}. */
  function loadCatalog() {
    if (catalog) return Promise.resolve(catalog);
    return amCatalog().then(function (c) {
      var locName = {};
      c.locations.forEach(function (l) { locName[l.id] = flat(l.name); });
      catalog = c.locations.map(function (l) {
        return { kind: 'location', id: l.id, name: flat(l.name), where: 'Locație', image: l.cover_image && l.cover_image.url, st: l.review_status };
      }).concat(c.products.map(function (p) {
        return { kind: 'product', id: p.id, name: flat(p.title), where: [KIND[p.type] || 'Produs', locName[p.location_id]].filter(Boolean).join(' · '), image: p.image && p.image.url, st: p.review_status };
      }));
      return catalog;
    });
  }
  function highlight(text, term) {
    var t = norm(term), flatText = '', map = [];
    for (var i = 0; i < text.length; i++) {
      var c = norm(text[i]);
      for (var j = 0; j < c.length; j++) { flatText += c[j]; map.push(i); }
    }
    var at = t ? flatText.indexOf(t) : -1;
    if (at < 0) return [text];
    var s = map[at], e = map[at + t.length - 1] + 1;
    return [text.slice(0, s), el('mark', { text: text.slice(s, e) }), text.slice(e)];
  }
  function setOpen(open) {
    qPop.hidden = !open;
    q.setAttribute('aria-expanded', String(open));
    if (!open) { active = -1; q.removeAttribute('aria-activedescendant'); }
  }
  function markActive() {
    [].forEach.call(qList.children, function (li, i) { li.setAttribute('aria-selected', String(i === active)); });
    var cur = qList.children[active];
    if (cur) { q.setAttribute('aria-activedescendant', cur.id); cur.scrollIntoView({ block: 'nearest' }); }
    else q.removeAttribute('aria-activedescendant');
  }
  function openResult(i) {
    var x = results[i];
    if (x && x.e.id != null) window.location.href = (x.e.kind === 'location' ? '/organizator/locatii?id=' : '/organizator/produse?id=') + encodeURIComponent(x.e.id);
  }
  function renderResults(term) {
    qList.textContent = '';
    active = -1;
    q.removeAttribute('aria-activedescendant');
    results.forEach(function (x, i) {
      var e = x.e, sub = e.where;
      var st = REVIEW[e.st];
      var thumb = el('span', { class: 'org-q-img', 'aria-hidden': 'true' });
      var letter = x.name.charAt(0) || '·';
      if (e.image) {
        var im = el('img', { src: img(e.image), alt: '', loading: 'lazy', decoding: 'async' });
        im.addEventListener('error', function () { thumb.textContent = letter; }, { once: true });
        thumb.appendChild(im);
      } else thumb.textContent = letter;
      var li = el('li', { class: 'org-q-item', role: 'option', id: 'org-q-o' + i, 'aria-selected': 'false' }, [
        thumb,
        el('span', { class: 'org-q-t' }, [el('b', null, highlight(x.name, term)), el('span', { text: sub })]),
        st ? el('span', { class: 'org-tag ' + st[1], text: st[0] }) : null,
      ]);
      li.addEventListener('mousedown', function (ev) { ev.preventDefault(); }); // keep the focus in the field
      li.addEventListener('click', function () { openResult(i); });
      qList.appendChild(li);
    });
    qMsg.textContent = results.length ? '' : 'Niciun produs și nicio locație nu se potrivesc cu „' + term + '”.';
    setOpen(true);
  }
  function runSearch() {
    var term = q.value.trim();
    if (norm(term).length < 2) { setOpen(false); qList.textContent = ''; results = []; return; }
    if (!catalog) { qList.textContent = ''; results = []; qMsg.textContent = 'Se caută…'; setOpen(true); }
    loadCatalog().then(function (rows) {
      if (q.value.trim() !== term) return;
      var t = norm(term);
      results = rows.map(function (e) {
        var name = e.name, n = norm(name), place = norm(e.where);
        return { e: e, name: name, s: n.indexOf(t) === 0 ? 0 : n.indexOf(t) > -1 ? 1 : place.indexOf(t) > -1 ? 2 : -1 };
      }).filter(function (x) { return x.s >= 0 && x.name; }).sort(function (a, b) { return a.s - b.s; }).slice(0, 8);
      renderResults(term);
    }, function () {
      if (q.value.trim() !== term) return;
      results = [];
      qList.textContent = '';
      qMsg.textContent = 'Nu am putut încărca produsele. Încearcă din nou.';
      setOpen(true);
    });
  }
  function closeMobileSearch(focusBack) {
    if (!top.classList.contains('is-searching')) return;
    top.classList.remove('is-searching');
    qOpen.setAttribute('aria-expanded', 'false');
    setOpen(false);
    if (focusBack) qOpen.focus();
  }
  if (q && allowed) {
    q.addEventListener('input', function () { clearTimeout(qTimer); qTimer = setTimeout(runSearch, 180); });
    q.addEventListener('focus', function () { if (norm(q.value.trim()).length >= 2) runSearch(); });
    q.addEventListener('keydown', function (e) {
      if (e.key === 'ArrowDown' || e.key === 'ArrowUp') {
        if (!results.length || qPop.hidden) return;
        e.preventDefault();
        var n = results.length;
        active = e.key === 'ArrowDown' ? (active + 1) % n : (active <= 0 ? n - 1 : active - 1);
        markActive();
      } else if (e.key === 'Enter') {
        if (qPop.hidden) return;
        if (active >= 0) { e.preventDefault(); openResult(active); }
        else if (results.length === 1) { e.preventDefault(); openResult(0); }
      } else if (e.key === 'Escape') {
        e.stopPropagation();
        if (!qPop.hidden) { e.preventDefault(); setOpen(false); }
        else closeMobileSearch(true);
      }
    });
    search.addEventListener('focusout', function (e) {
      if (e.relatedTarget && search.contains(e.relatedTarget)) return;
      setTimeout(function () {
        if (search.contains(document.activeElement)) return;
        setOpen(false);
        closeMobileSearch(false);
      }, 0);
    });
    qOpen.addEventListener('click', function () {
      top.classList.add('is-searching');
      qOpen.setAttribute('aria-expanded', 'true');
      q.focus();
    });
    window.matchMedia('(min-width: 768px)').addEventListener('change', function (m) { if (m.matches) closeMobileSearch(false); });
  }

  document.addEventListener('keydown', function (e) {
    if (e.key !== 'Escape') return;
    if (openPop) { setPop(openPop.btn, openPop.pop, false, true); e.preventDefault(); }
    else if (root.classList.contains('is-drawer')) drawer(false);
  });

  /* ---------- start ---------- */
  if (allowed) {
    var a = auth();
    try { setProfile(a.getOrganizerData && a.getOrganizerData()); } catch (e) {}
    window.addEventListener('bileteonline:auth:update', function (e) { if (e.detail && e.detail.type === 'organizer') setProfile(e.detail.user); });
    // The account is painted from what the browser remembers, then from the API. Coming back to the tab reads it
    // again, so a change made meanwhile in the admin (status, commission, contract) shows up without a reload.
    var profileAt = 0;
    /** quiet: a refresh must not end the session on a hiccup; the first read still does, as before. */
    function loadProfile(quiet) {
      profileAt = Date.now();
      return api('/organizer/me', quiet ? { quiet: true } : undefined).then(function (r) {
        var d = r && r.data, o = d && (d.organizer || d);
        if (!o || typeof o !== 'object' || !(o.id || o.email || o.name)) return;
        try {
          if (a.updateOrganizerData) a.updateOrganizerData(o); // dispatches bileteonline:auth:update, which fills the page in
          else { localStorage.setItem('bileteonline_organizer_data', JSON.stringify(o)); setProfile(o); }
        } catch (e) { setProfile(o); }
      }, function () {});
    }
    function refreshAccount() {
      if (document.hidden || Date.now() - profileAt < 30000) return;
      loadProfile(true);
      amLoad = null;
      amCatalog().then(function (c) { planLocs = c.locations; drawPlan(); });
    }
    loadProfile(false);
    amCatalog().then(function (c) { planLocs = c.locations; drawPlan(); });
    window.addEventListener('focus', refreshAccount);
    window.addEventListener('pageshow', function (e) { if (e.persisted) refreshAccount(); });
    api('/organizer/support/tickets?status=open&per_page=1', { quiet: true }).then(function (r) { // 403 for organizers outside the support beta: no badge
      setBadge('support', metaOf(r).total);
    }, function () {});
    loadNotifications();
    setInterval(function () { if (!document.hidden) loadNotifications(); }, 60000);
    document.addEventListener('visibilitychange', function () {
      if (document.hidden) return;
      if (Date.now() - notif.at > 60000) loadNotifications();
      refreshAccount();
    });
  } else {
    document.documentElement.classList.add('org-redirecting');
  }

  window.BO_ORG = {
    ready: ready, api: api, el: el, icon: icon, flash: flash, onProfile: onProfile, setBadge: setBadge,
    profile: function () { return profile; }, refreshNotifications: loadNotifications,
    fmt: { toNum: toNum, num: num, money: money, pct: pct, count: count, flat: flat, dateOf: dateOf, date: fmtDate, ymd: ymd, ago: ago },
    safeHref: safeHref, img: img, metaOf: metaOf,
  };
})();
