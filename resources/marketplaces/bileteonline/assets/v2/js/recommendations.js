/* bilete.online v2: customer recommendations (/cont/recomandari).
   GET /customer/recommendations (scored on the server: items with match_score, reasons, reason_primary, can_use_points,
   is_family, budget_bucket; stats; signals; engine) fills the hero signals, the counters, the cards and the side column.
   /customer/rewards/config gives the points-to-lei rate; when the programme makes points expire, the dashboard summary
   gives the points expiring soon (/customer/rewards doesn't carry them). Filters (search, reason, city, budget, quick
   pills) run in the browser and stay in the URL. "Nu mă interesează" hides a card on this device (localStorage
   bo_rec_hidden, the key the old page used) with an undo, and "Arată ascunse" brings them back.
   Text from the API is always written as text. */
(function () {
  'use strict';
  var $ = function (id) { return document.getElementById(id); };
  if (!$('rc-content') || !window.BO_ACCOUNT || typeof BileteOnlineAPI === 'undefined') return;
  var account = window.BO_ACCOUNT, API = BileteOnlineAPI;
  var reduce = !!(window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches);

  var REASONS = ['all', 'profile', 'family', 'points', 'history', 'weather'];
  var BUDGETS = ['all', 'low', 'mid', 'high'];
  var HIDDEN_KEY = 'bo_rec_hidden';
  var SEGMENTS = [['1060 585 220 310', '220 / 310'], ['1455 585 290 310', '290 / 310'], ['2170 625 340 270', '340 / 270'], ['2665 625 250 270', '250 / 270']];
  var num = new Intl.NumberFormat('ro-RO');

  var items = [], loaded = false, stats = {}, signals = {}, engine = {}, perLei = 100, categoryNames = {};
  var hidden = readHidden(), flashTimer = 0, flashSet = 0, undoFn = null;
  var params = new URLSearchParams(window.location.search);
  var filters = {
    q: (params.get('q') || '').slice(0, 80),
    reason: REASONS.indexOf(params.get('motiv')) !== -1 ? params.get('motiv') : 'all',
    city: params.get('oras') ? params.get('oras').slice(0, 80) : 'all',
    budget: BUDGETS.indexOf(params.get('buget')) !== -1 ? params.get('buget') : 'all'
  };

  // ---------- helpers ----------
  function el(tag, cls, text) { var n = document.createElement(tag); if (cls) n.className = cls; if (text != null) n.textContent = text; return n; }
  function show(id, on) { var n = typeof id === 'string' ? $(id) : id; if (n) n.hidden = !on; }
  function obj(x) { return !!x && typeof x === 'object' && !Array.isArray(x); }
  function txt(v) {
    if (obj(v)) v = v.ro || v.en || Object.keys(v).map(function (k) { return v[k]; }).filter(function (x) { return typeof x === 'string'; })[0];
    return v == null || typeof v === 'object' ? '' : String(v).trim();
  }
  function count(v) { var n = Number(v); return isFinite(n) && n > 0 ? Math.floor(n) : 0; }
  function plural(n, one, many) {
    n = count(n);
    if (n === 1) return '1 ' + one;
    var r = n % 100;
    return num.format(n) + (n && (r === 0 || r >= 20) ? ' de ' : ' ') + many;
  }
  function norm(s) { return String(s || '').toLowerCase().normalize('NFD').replace(/[̀-ͯ]/g, '').trim(); }
  function fresh(path) { return API.request(path, { method: 'GET', noCache: true }); }
  function safeUrl(u) {
    u = typeof u === 'string' ? u.trim() : '';
    return /^(\/(?![\/\\])|https:\/\/)/i.test(u) ? u : '';
  }
  function imgUrl(v) {
    v = typeof v === 'string' ? v.trim() : '';
    if (!v) return '';
    if (/^https?:\/\//i.test(v) || /^\/(?![\/\\])/.test(v)) return v;
    if (/^[a-z][a-z0-9+.-]*:/i.test(v) || /^[\/\\]/.test(v)) return '';
    var storage = ((window.BILETEONLINE && window.BILETEONLINE.storageUrl) || '').replace(/\/+$/, '');
    return storage ? storage + '/' + v : '';
  }
  function slugName(slug) { var s = String(slug || '').replace(/[-_]+/g, ' ').trim(); return s ? s.charAt(0).toUpperCase() + s.slice(1) : ''; }
  function guard() { show('rc-content', false); show('rc-guard', true); account.toLogin(); } // the message shows only while the login page loads
  function say(message, tone, undo) {
    var box = $('rc-flash'), t = $('rc-flash-t'), undoBtn = $('rc-flash-undo');
    clearTimeout(flashTimer);
    clearTimeout(flashSet);
    t.textContent = '';
    box.classList.toggle('is-error', tone === 'error');
    undoFn = undo || null;
    undoBtn.hidden = !undo;
    box.hidden = false;
    flashSet = setTimeout(function () { t.textContent = message; }, 40); // emptied first, so a repeated message is announced again
    flashTimer = setTimeout(function () { box.hidden = true; t.textContent = ''; undoFn = null; }, undo ? 9000 : (tone === 'error' ? 10000 : 6000));
  }
  $('rc-flash-undo').addEventListener('click', function () {
    var fn = undoFn;
    $('rc-flash').hidden = true;
    undoFn = null;
    if (fn) fn();
  });
  function fallback(i) {
    var ns = 'http://www.w3.org/2000/svg', span = el('span', 'fb'), svg = document.createElementNS(ns, 'svg'), use = document.createElementNS(ns, 'use'), seg = SEGMENTS[i % SEGMENTS.length];
    span.setAttribute('aria-hidden', 'true');
    svg.setAttribute('viewBox', seg[0]);
    svg.style.aspectRatio = seg[1];
    use.setAttribute('href', '#drum-g');
    svg.appendChild(use);
    span.appendChild(svg);
    return span;
  }

  // ---------- hidden cards (this device) ----------
  function readHidden() {
    try { var list = JSON.parse(localStorage.getItem(HIDDEN_KEY) || '[]'); return Array.isArray(list) ? list.filter(function (x) { return typeof x === 'string'; }) : []; }
    catch (e) { return []; }
  }
  function saveHidden() { try { localStorage.setItem(HIDDEN_KEY, JSON.stringify(hidden.slice(-200))); } catch (e) {} }
  function keyOf(it) { return it.url || 'id:' + it.id; }
  function isHidden(it) { return hidden.indexOf(keyOf(it)) !== -1; }

  // ---------- data ----------
  function normItem(it, i) {
    var reasons = (Array.isArray(it.reasons) ? it.reasons : []).map(txt).filter(Boolean);
    return {
      id: it.id != null ? it.id : i, title: txt(it.title) || 'Activitate', url: safeUrl(it.url) || (it.slug ? '/activitate/' + encodeURIComponent(String(it.slug)) : ''),
      image: imgUrl(it.image), city: txt(it.city), category: txt(it.category), categorySlug: txt(it.category_slug), price: txt(it.price_label),
      description: txt(it.short_description), match: Math.max(0, Math.min(99, Math.round(Number(it.match_score) || 0))), reasons: reasons,
      reason: REASONS.indexOf(it.reason_primary) > 0 ? it.reason_primary : 'profile', points: !!it.can_use_points, family: !!it.is_family,
      budget: BUDGETS.indexOf(it.budget_bucket) > 0 ? it.budget_bucket : ''
    };
  }
  function load() {
    show('rc-error', false);
    show('rc-empty', false);
    show('rc-grid', false);
    show('rc-skel', true);
    return fresh('/customer/recommendations?limit=40').then(function (resp) {
      var d = resp && obj(resp.data) ? resp.data : {};
      items = (Array.isArray(d.items) ? d.items : []).filter(obj).map(normItem);
      stats = obj(d.stats) ? d.stats : {};
      signals = obj(d.signals) ? d.signals : {};
      engine = obj(d.engine) ? d.engine : {};
      loaded = true;
      items.forEach(function (it) { if (it.categorySlug && it.category) categoryNames[it.categorySlug] = it.category; });
      renderSignals();
      renderStats();
      fillCities();
      render();
      resolveInterest();
    }, function (err) {
      if (err && err.status === 401) { guard(); return; }
      show('rc-skel', false);
      show('rc-error', true);
      $('rc-results-h').textContent = 'Recomandări';
    });
  }
  function loadPoints() {
    API.get('/customer/rewards/config').then(function (resp) {
      var c = resp && obj(resp.data) ? resp.data : {};
      if (Number(c.points_per_lei) > 0) perLei = Number(c.points_per_lei);
      renderPoints();
      if (count(c.points_expire_days) > 0) {
        return fresh('/customer/dashboard-bundle').then(function (b) {
          var s = b && obj(b.data) && obj(b.data.rewards_summary) ? b.data.rewards_summary : {};
          renderExpiring(count(s.expiring_soon), count(s.expiring_days) || 30);
        });
      }
      renderExpiring(0, 0, true);
    }).catch(function () { $('rc-s-exp').textContent = '—'; $('rc-s-exp-p').textContent = 'nu am putut verifica'; });
  }
  function resolveInterest() {
    var slug = txt(signals.interest);
    if (!slug || categoryNames[slug] || !/^[a-z0-9-]+$/.test(slug)) return;
    API.get('/marketplace-events/categories', { all: 1, parents_only: 1 }).then(function (resp) {
      var d = resp && resp.data, rows = Array.isArray(d) ? d : (obj(d) && Array.isArray(d.categories) ? d.categories : []);
      rows.filter(obj).forEach(function (c) { var s = txt(c.slug), n = txt(c.name); if (s && n) categoryNames[s] = n; });
      renderSignals();
    }, function () {});
  }

  // ---------- render ----------
  function renderSignals() {
    var interest = txt(signals.interest);
    $('rc-sig-city').textContent = txt(signals.city) || 'neales';
    $('rc-sig-interest').textContent = interest ? (categoryNames[interest] || (/^[a-z0-9-]+$/.test(interest) ? slugName(interest) : interest)) : 'descoperire';
    $('rc-sig-family').textContent = txt(signals.family) || 'doar tu';
    $('rc-sig-points').textContent = num.format(count(signals.points));
    ['rc-sig-city', 'rc-sig-interest', 'rc-sig-family'].forEach(function (id) { $(id).title = $(id).textContent; });

    var history = !!engine.has_history, family = !!txt(signals.family) && txt(signals.family) !== 'doar tu';
    var cities = (Array.isArray(engine.pref_cities) ? engine.pref_cities : []).map(txt).filter(Boolean);
    control('rc-c-history', history, history ? 'activități cumpărate anterior' : 'nicio comandă în ultimele 12 luni');
    control('rc-c-family', family, family ? txt(signals.family) : 'adaugă copiii în profilul familiei');
    control('rc-c-cities', cities.length > 0, cities.length ? cities.join(', ') : 'încă nimic ales');
    renderPoints();
  }
  function control(id, on, text) {
    var li = $(id), small = $(id + '-t'), sr = li.querySelector('.sr') || li.querySelector('b').appendChild(el('span', 'sr'));
    li.classList.toggle('is-on', on);
    li.classList.toggle('is-off', !on);
    sr.textContent = on ? ' (folosit)' : ' (nefolosit)';
    if (small) small.textContent = text;
  }
  function renderPoints() {
    var p = count(signals.points);
    $('rc-p-points').textContent = num.format(p);
    $('rc-p-lei').textContent = num.format(Math.floor(p / perLei)) + ' lei';
  }
  function renderExpiring(n, days, never) {
    $('rc-s-exp').textContent = num.format(n);
    $('rc-s-exp-p').textContent = n ? 'puncte în ' + plural(days, 'zi', 'zile') : (never ? 'punctele nu expiră' : 'fără puncte care expiră');
  }
  function renderStats() {
    var pick = function (key, fallbackCount) { return stats[key] != null ? count(stats[key]) : fallbackCount; };
    $('rc-s-good').textContent = num.format(pick('good_match_count', items.filter(function (it) { return it.match >= 70; }).length));
    $('rc-s-points').textContent = num.format(pick('with_points_count', items.filter(function (it) { return it.points; }).length));
    $('rc-s-family').textContent = num.format(pick('family_count', items.filter(function (it) { return it.family; }).length));
  }
  function fillCities() {
    var sel = $('rc-city'), names = [];
    items.forEach(function (it) { if (it.city && names.indexOf(it.city) === -1) names.push(it.city); });
    names.sort(function (a, b) { return a.localeCompare(b, 'ro'); });
    if (filters.city !== 'all' && names.indexOf(filters.city) === -1) names.unshift(filters.city);
    while (sel.options.length > 1) sel.remove(1);
    names.forEach(function (n) { sel.appendChild(new Option(n, n)); });
    sel.value = filters.city;
  }
  function matches(it) {
    if (isHidden(it)) return false;
    var r = filters.reason;
    if (r !== 'all' && !(it.reason === r || (r === 'family' && it.family) || (r === 'points' && it.points))) return false;
    if (filters.city !== 'all' && it.city !== filters.city) return false;
    if (filters.budget !== 'all' && it.budget !== filters.budget) return false;
    var q = norm(filters.q);
    return !q || norm([it.title, it.description, it.city, it.category, it.reasons.join(' ')].join(' ')).indexOf(q) !== -1;
  }
  function filtering() { return !!(filters.q || filters.reason !== 'all' || filters.city !== 'all' || filters.budget !== 'all'); }
  function card(it, i) {
    var li = el('li', 'rc-card'), top = el(it.url ? 'a' : 'div', 'rc-top'), media = el('span', 'rc-media'), body = el('div', 'rc-body');
    li.id = 'rc-i-' + String(it.id).replace(/[^A-Za-z0-9_-]/g, '');
    if (it.url) top.href = it.url;
    if (it.image) {
      var img = el('img');
      img.alt = '';
      img.loading = i < 4 ? 'eager' : 'lazy';
      img.decoding = 'async';
      img.addEventListener('error', function () { media.textContent = ''; media.appendChild(fallback(i)); });
      img.src = it.image;
      media.appendChild(img);
    } else media.appendChild(fallback(i));
    top.appendChild(media);
    if (it.match > 0) top.appendChild(el('span', 'rc-badge is-match', it.match + '% match'));
    if (it.points) top.appendChild(el('span', 'rc-badge is-points', 'poți folosi puncte'));
    top.appendChild(el('h3', null, it.title));

    var tags = el('div', 'rc-tags');
    [it.city, it.category, it.price].filter(Boolean).forEach(function (t) { tags.appendChild(el('span', 'acc-tag', t)); });
    if (it.family) tags.appendChild(el('span', 'acc-tag is-ok', 'pentru copii'));
    if (tags.childNodes.length) body.appendChild(tags);
    if (it.description) body.appendChild(el('p', 'rc-desc', it.description));
    var why = el('div', 'rc-why'), ul = el('ul');
    why.appendChild(el('b', null, 'De ce ți-o recomandăm?'));
    (it.reasons.length ? it.reasons : ['Activitate populară pe care credem că o vei aprecia.']).forEach(function (r) { ul.appendChild(el('li', null, r)); });
    why.appendChild(ul);
    body.appendChild(why);

    var actions = el('div', 'rc-actions');
    if (it.url) {
      var go = el('a', 'btn btn-primary', 'Vezi bilete');
      go.href = it.url;
      go.setAttribute('aria-label', 'Vezi bilete: ' + it.title);
      actions.appendChild(go);
    }
    var no = el('button', 'btn btn-ghost rc-hide', 'Nu mă interesează');
    no.type = 'button';
    no.setAttribute('aria-label', 'Nu mă interesează: ' + it.title);
    no.addEventListener('click', function () { hide(it); });
    actions.appendChild(no);
    body.appendChild(actions);
    li.appendChild(top);
    li.appendChild(body);
    return li;
  }
  function render() {
    if (!loaded) return;
    var list = items.filter(matches), grid = $('rc-grid'), frag = document.createDocumentFragment();
    list.forEach(function (it, i) { frag.appendChild(card(it, i)); });
    grid.textContent = '';
    grid.appendChild(frag);
    var hiddenHere = items.filter(isHidden).length;
    $('rc-results-h').textContent = plural(list.length, 'recomandare', 'recomandări');
    $('rc-unhide').textContent = 'Arată ascunse (' + num.format(hiddenHere) + ')';
    show('rc-unhide', hiddenHere > 0);
    show('rc-skel', false);
    show('rc-error', false);
    show(grid, list.length > 0);
    show('rc-empty', !list.length);
    if (!list.length) {
      var none = !items.length;
      $('rc-empty-h').textContent = none ? 'Încă nu avem recomandări pentru tine.' : 'Nu am găsit recomandări.';
      $('rc-empty-p').textContent = none
        ? 'Completează preferințele (orașe, categorii, buget) ca să îți putem propune activități potrivite.'
        : (filtering() ? 'Schimbă filtrele sau completează profilul pentru sugestii mai bune.' : 'Ai ascuns toate recomandările. Le poți readuce oricând.');
      show('rc-empty-reset', filtering());
    }
    [].forEach.call(document.querySelectorAll('.rc-pill'), function (b) { b.setAttribute('aria-pressed', String(b.getAttribute('data-reason') === filters.reason)); });
  }
  function hide(it) {
    var cards = [].slice.call(document.querySelectorAll('#rc-grid .rc-card')), at = -1;
    cards.forEach(function (c, i) { if (c.id === 'rc-i-' + String(it.id).replace(/[^A-Za-z0-9_-]/g, '')) at = i; });
    hidden.push(keyOf(it));
    saveHidden();
    render();
    var buttons = document.querySelectorAll('#rc-grid .rc-hide');
    (buttons[Math.min(at, buttons.length - 1)] || $('rc-results-h')).focus();
    say('Am ascuns „' + it.title + '”.', null, function () {
      hidden = hidden.filter(function (k) { return k !== keyOf(it); });
      saveHidden();
      render();
      var back = $('rc-i-' + String(it.id).replace(/[^A-Za-z0-9_-]/g, ''));
      if (back) back.querySelector('.rc-hide').focus();
    });
  }
  $('rc-unhide').addEventListener('click', function () {
    var keys = items.map(keyOf), before = hidden.length;
    hidden = hidden.filter(function (k) { return keys.indexOf(k) === -1; });
    saveHidden();
    render();
    say('Am readus ' + plural(before - hidden.length, 'recomandare ascunsă', 'recomandări ascunse') + '.');
    $('rc-results-h').focus();
  });

  // ---------- filters ----------
  function syncUrl() {
    var p = new URLSearchParams(window.location.search);
    [['q', filters.q], ['motiv', filters.reason], ['oras', filters.city], ['buget', filters.budget]].forEach(function (pair) {
      if (pair[1] && pair[1] !== 'all') p.set(pair[0], pair[1]); else p.delete(pair[0]);
    });
    var qs = p.toString();
    try { history.replaceState(history.state, '', window.location.pathname + (qs ? '?' + qs : '') + window.location.hash); } catch (e) {}
  }
  function changed() { syncUrl(); render(); }
  $('rc-q').value = filters.q;
  $('rc-reason').value = filters.reason;
  $('rc-budget').value = filters.budget;
  $('rc-q').addEventListener('input', function () { filters.q = this.value.trim().slice(0, 80); changed(); });
  $('rc-reason').addEventListener('change', function () { filters.reason = this.value; changed(); });
  $('rc-city').addEventListener('change', function () { filters.city = this.value; changed(); });
  $('rc-budget').addEventListener('change', function () { filters.budget = this.value; changed(); });
  [].forEach.call(document.querySelectorAll('.rc-pill'), function (b) {
    b.addEventListener('click', function () {
      var r = b.getAttribute('data-reason');
      filters.reason = filters.reason === r ? 'all' : r;
      $('rc-reason').value = filters.reason;
      changed();
    });
  });
  function reset() {
    filters = { q: '', reason: 'all', city: 'all', budget: 'all' };
    $('rc-q').value = '';
    $('rc-reason').value = 'all';
    $('rc-city').value = 'all';
    $('rc-budget').value = 'all';
    changed();
    $('rc-q').focus();
  }
  $('rc-reset').addEventListener('click', reset);
  $('rc-empty-reset').addEventListener('click', reset);
  $('rc-retry').addEventListener('click', function () { load(); });

  if (!account.isCustomer()) { guard(); return; }
  load();
  loadPoints();
})();
