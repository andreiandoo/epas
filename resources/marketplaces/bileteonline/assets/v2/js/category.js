/* bilete.online v2: category page. The activities on the page are filtered and sorted in the browser (quick-filter
   popovers, the filters dialog, chips, sort), plus the subcategory panel, favourites and the map dialog.
   Filter rules and labels match the previous version of the page. */
(function () {
  'use strict';
  var root = document.documentElement;
  var reduce = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
  var $ = function (id) { return document.getElementById(id); };
  var data = {};
  try { data = JSON.parse(($('v2-data') || {}).textContent || '{}'); } catch (e) {}
  var acts = Array.isArray(data.activities) ? data.activities : [];
  var cap = data.priceMax || 250;
  var labels = data.labels || {};
  var LISTS = ['categories', 'interests', 'travelerTypes', 'languages', 'durations', 'features'];
  var TOP_KEYS = { search: ['search'], price: ['maxPrice'], duration: ['durations'], languages: ['languages'], features: ['features'], interests: ['interests'], traveler: ['travelerTypes'] };
  var TAB_KEYS = { categories: ['categories'], interests: ['interests'], traveler: ['travelerTypes'], price: ['maxPrice'], languages: ['languages'], duration: ['durations'], features: ['features'], rating: ['minRating'] };

  function blank() {
    return { search: '', categories: [], interests: [], travelerTypes: [], languages: [], durations: [], features: [], maxPrice: cap, minRating: 0 };
  }
  var state = blank(), sortBy = 'recommended';

  function lei(n) { return new Intl.NumberFormat('ro-RO').format(n || 0) + ' lei'; }
  function each(sel, fn) { [].forEach.call(document.querySelectorAll(sel), fn); }
  function node(tag, cls, text) {
    var n = document.createElement(tag);
    if (cls) n.className = cls;
    if (text !== undefined && text !== null) n.textContent = text;
    return n;
  }
  function icon(name) {
    var s = document.createElementNS('http://www.w3.org/2000/svg', 'svg');
    var u = document.createElementNS('http://www.w3.org/2000/svg', 'use');
    s.setAttribute('class', 'ic');
    s.setAttribute('aria-hidden', 'true');
    u.setAttribute('href', '#i-' + name);
    s.appendChild(u);
    return s;
  }

  /* ---------- filter state ---------- */
  function hasValue(k) {
    if (k === 'search') return state.search.trim() !== '';
    if (k === 'maxPrice') return state.maxPrice < cap;
    if (k === 'minRating') return state.minRating > 0;
    return state[k].length > 0;
  }
  function anyOf(keys) { return (keys || []).some(hasValue); }
  function overlap(want, have) { return want.some(function (v) { return (have || []).indexOf(v) !== -1; }); }
  function matches(a) {
    var q = state.search.toLowerCase().trim();
    if (q && (a.text || '').indexOf(q) === -1) return false;
    if (state.categories.length && state.categories.indexOf(a.categorySlug) === -1) return false;
    if (state.interests.length && !overlap(state.interests, a.interests)) return false;
    if (state.travelerTypes.length && !overlap(state.travelerTypes, a.travelerTypes)) return false;
    if (a.price > 0 && a.price > state.maxPrice) return false;
    if (state.languages.length && !overlap(state.languages, a.languages)) return false;
    if (state.durations.length && state.durations.indexOf(a.duration) === -1) return false;
    if (state.features.length && !state.features.every(function (f) { return (a.features || []).indexOf(f) !== -1; })) return false;
    if (state.minRating > 0 && a.rating < state.minRating) return false;
    return true;
  }
  function sorted(list) {
    var l = list.slice();
    if (sortBy === 'rating') l.sort(function (a, b) { return b.rating - a.rating; });
    else if (sortBy === 'priceAsc') l.sort(function (a, b) { return (a.price || 1e9) - (b.price || 1e9); });
    else if (sortBy === 'priceDesc') l.sort(function (a, b) { return b.price - a.price; });
    else if (sortBy === 'duration') {
      var o = { short: 1, medium: 2, long: 3 };
      l.sort(function (a, b) { return (o[a.duration] || 4) - (o[b.duration] || 4); });
    } else l.sort(function (a, b) { return (b.rating - a.rating) || (b.reviews - a.reviews); });
    return l;
  }
  function activeCount() {
    var c = hasValue('search') ? 1 : 0;
    LISTS.forEach(function (k) { c += state[k].length; });
    if (hasValue('maxPrice')) c++;
    if (hasValue('minRating')) c++;
    return c;
  }
  function without(k, v) { return function () { state[k] = state[k].filter(function (x) { return x !== v; }); }; }
  function label(k, v) { return (labels[k] && labels[k][v]) || v; }
  function chipList() {
    var out = [];
    if (hasValue('search')) out.push(['Caută: ' + state.search, function () { state.search = ''; }]);
    ['categories', 'interests', 'travelerTypes'].forEach(function (k) { state[k].forEach(function (v) { out.push([label(k, v), without(k, v)]); }); });
    if (hasValue('maxPrice')) out.push(['Max ' + lei(state.maxPrice), function () { state.maxPrice = cap; }]);
    ['languages', 'durations', 'features'].forEach(function (k) { state[k].forEach(function (v) { out.push([label(k, v), without(k, v)]); }); });
    if (hasValue('minRating')) out.push([String(state.minRating).replace('.', ',') + '+ stele', function () { state.minRating = 0; }]);
    return out;
  }

  /* ---------- render ---------- */
  var grid = $('k-grid'), empty = $('k-empty'), chipBox = $('k-chips'), sortSel = $('k-sort');
  var itemById = {};
  if (grid) [].forEach.call(grid.children, function (li) { itemById[li.getAttribute('data-id')] = li; });

  function syncInputs() {
    each('[data-f]', function (el) {
      var f = el.getAttribute('data-f');
      if (el.type === 'checkbox') el.checked = state[f].indexOf(el.value) !== -1;
      else if (el.type === 'range') el.value = state.maxPrice;
      else if (el.type === 'search') { if (el.value !== state.search) el.value = state.search; }
      else if (el.tagName === 'BUTTON') el.setAttribute('aria-pressed', String(parseFloat(el.getAttribute('data-v')) === state.minRating));
    });
    each('[data-out="maxPrice"]', function (el) { el.textContent = lei(state.maxPrice); });
  }
  function renderChips() {
    if (!chipBox) return;
    chipBox.textContent = '';
    chipList().forEach(function (c) {
      var b = node('button', 'achip', c[0]);
      b.type = 'button';
      b.appendChild(icon('x'));
      b.appendChild(node('span', 'sr', ' (elimină)'));
      b.addEventListener('click', function () { c[1](); apply(); });
      chipBox.appendChild(b);
    });
  }
  function apply() {
    var visible = sorted(acts.filter(matches));
    var shown = {};
    visible.forEach(function (a) {
      shown[a.id] = true;
      if (grid && itemById[a.id]) grid.appendChild(itemById[a.id]);
    });
    Object.keys(itemById).forEach(function (id) { itemById[id].hidden = !shown[id]; });
    if (empty) empty.hidden = visible.length > 0;
    each('[data-count]', function (el) { el.textContent = visible.length; });
    var n = activeCount();
    each('[data-fcount]', function (el) { el.textContent = n; el.hidden = n === 0; });
    each('[data-reset-bar]', function (el) { el.hidden = n === 0; });
    each('[data-top]', function (b) { b.classList.toggle('is-set', anyOf(TOP_KEYS[b.getAttribute('data-top')])); });
    each('[data-tabdot]', function (d) { d.hidden = !anyOf(TAB_KEYS[d.getAttribute('data-tabdot')]); });
    renderChips();
    syncInputs();
    if (map && !map.hidden) renderMap(visible);
  }

  /* ---------- quick-filter popovers ---------- */
  var openPop = null, popBtn = null;
  function closePop(focusBtn) {
    if (!openPop) return;
    openPop.hidden = true;
    if (popBtn) {
      popBtn.setAttribute('aria-expanded', 'false');
      if (focusBtn) popBtn.focus();
    }
    openPop = popBtn = null;
  }
  function togglePop(key, btn) {
    var pop = $('kp-' + key);
    if (!pop) return;
    if (openPop === pop) { closePop(true); return; }
    closePop(false);
    openPop = pop;
    popBtn = btn;
    pop.hidden = false;
    btn.setAttribute('aria-expanded', 'true');
    var first = pop.querySelector('input, [data-f]');
    if (first) first.focus({ preventScroll: true });
  }
  function clearKeys(keys) {
    (keys || []).forEach(function (k) { state[k] = k === 'search' ? '' : (k === 'maxPrice' ? cap : []); });
    apply();
  }

  /* ---------- subcategory panel ---------- */
  var sub = $('k-sub'), bar = $('k-bar');
  function toggleSub() {
    if (!sub) return;
    var open = !sub.classList.contains('is-open');
    sub.classList.toggle('is-open', open);
    each('button[data-subcat][aria-expanded]', function (b) { b.setAttribute('aria-expanded', String(open)); });
    if (open && bar) {
      // scrolled past it: bring the panel up under the sticky bar
      var gap = sub.getBoundingClientRect().top - bar.getBoundingClientRect().bottom;
      if (gap < 0) window.scrollTo({ top: window.scrollY + gap, behavior: reduce ? 'auto' : 'smooth' });
    }
  }

  /* ---------- dialogs (filters, map) ---------- */
  var filters = $('k-filters'), map = $('k-map'), stack = [];
  function openDlg(dlg, opener, tab) {
    if (!dlg) return;
    closePop(false);
    if (tab && $('kft-' + tab)) $('kft-' + tab).click();
    dlg.hidden = false;
    stack.push({ dlg: dlg, opener: opener });
    root.classList.add('k-lock');
    if (dlg === map) renderMap(sorted(acts.filter(matches)));
    var f = dlg.querySelector('[role="tab"][aria-selected="true"]') || dlg.querySelector('button, a[href], input');
    if (f) f.focus();
  }
  function closeDlg(dlg) {
    if (!dlg || dlg.hidden) return;
    dlg.hidden = true;
    var entry = null;
    stack = stack.filter(function (s) { if (s.dlg === dlg) { entry = s; return false; } return true; });
    if (!stack.length) root.classList.remove('k-lock');
    if (entry && entry.opener && document.body.contains(entry.opener)) entry.opener.focus();
  }
  function trap(e, box) {
    var f = [].slice.call(box.querySelectorAll('a[href], button:not([disabled]), input, select')).filter(function (el) { return el.offsetParent !== null; });
    if (!f.length) return;
    var first = f[0], last = f[f.length - 1];
    if (e.shiftKey && document.activeElement === first) { e.preventDefault(); last.focus(); }
    else if (!e.shiftKey && document.activeElement === last) { e.preventDefault(); first.focus(); }
  }

  /* ---------- map ---------- */
  var mapList = $('k-map-list'), mapPins = $('k-map-pins'), mapCard = $('k-map-card'), selected = null;
  function media(a, cls) {
    var s = node('span', cls);
    if (a.image) {
      var img = node('img');
      img.src = a.image;
      img.alt = '';
      img.loading = 'lazy';
      s.appendChild(img);
    }
    return s;
  }
  function metaLine(a) { return [a.place, a.durationLabel].filter(Boolean).join(' · '); }
  function markPins() {
    each('[data-pin]', function (b) { b.setAttribute('aria-pressed', String(b.getAttribute('data-pin') === selected)); });
  }
  function renderCard() {
    if (!mapCard) return;
    var a = null;
    acts.forEach(function (x) { if (String(x.id) === selected) a = x; });
    mapCard.textContent = '';
    mapCard.hidden = !a;
    if (!a) return;
    mapCard.appendChild(media(a, 'kmc-media'));
    var body = node('div', 'kmc-body'), top = node('div', 'kmc-top'), text = node('div');
    text.appendChild(node('b', null, a.title));
    if (metaLine(a)) text.appendChild(node('p', 'kmc-meta', metaLine(a)));
    var close = node('button', 'icon-btn');
    close.type = 'button';
    close.setAttribute('data-unpin', '');
    close.appendChild(icon('x'));
    close.appendChild(node('span', 'sr', 'Închide'));
    top.appendChild(text);
    top.appendChild(close);
    body.appendChild(top);
    var row = node('div', 'kmc-row');
    row.appendChild(node('span', null, a.rating > 0 ? '★ ' + String(a.rating).replace('.', ',') : ''));
    row.appendChild(node('strong', null, a.price ? lei(a.price) : ''));
    body.appendChild(row);
    var go = node('a', 'btn btn-primary', 'Vezi bilete');
    go.href = a.href;
    body.appendChild(go);
    mapCard.appendChild(body);
  }
  function renderMap(list) {
    if (!mapList || !mapPins) return;
    mapList.textContent = '';
    mapPins.textContent = '';
    list.forEach(function (a) {
      var li = node('li'), b = node('button', 'kml'), text = node('span');
      b.type = 'button';
      b.setAttribute('data-pin', String(a.id));
      b.appendChild(media(a, 'kml-media'));
      text.appendChild(node('b', null, a.title));
      if (metaLine(a)) text.appendChild(node('small', null, metaLine(a)));
      if (a.rating > 0) text.appendChild(node('small', 'kml-rating', '★ ' + String(a.rating).replace('.', ',')));
      text.appendChild(node('span', 'kml-price', a.price ? 'de la ' + lei(a.price) : 'Vezi preț'));
      b.appendChild(text);
      li.appendChild(b);
      mapList.appendChild(li);
      var pin = node('button', 'kpin', a.price ? lei(a.price) : '•');
      pin.type = 'button';
      pin.setAttribute('data-pin', String(a.id));
      pin.setAttribute('aria-label', a.title + (a.price ? ', de la ' + lei(a.price) : ''));
      pin.style.left = a.map.x + '%';
      pin.style.top = a.map.y + '%';
      mapPins.appendChild(pin);
    });
    if (selected && !list.some(function (a) { return String(a.id) === selected; })) selected = null;
    markPins();
    renderCard();
  }
  if (mapList) {
    var hot = function (id) { each('.kpin', function (p) { p.classList.toggle('is-hot', p.getAttribute('data-pin') === id); }); };
    mapList.addEventListener('mouseover', function (e) { var b = e.target.closest('[data-pin]'); hot(b ? b.getAttribute('data-pin') : null); });
    mapList.addEventListener('mouseleave', function () { hot(null); });
  }

  /* ---------- events ---------- */
  document.addEventListener('input', function (e) {
    var el = e.target;
    if (!el.matches || !el.matches('input[data-f]') || el.type === 'checkbox') return;
    if (el.type === 'range') state.maxPrice = parseInt(el.value, 10) || 0;
    else state.search = el.value;
    apply();
  });
  document.addEventListener('change', function (e) {
    var el = e.target;
    if (el.matches && el.matches('input[type="checkbox"][data-f]')) {
      var f = el.getAttribute('data-f');
      state[f] = state[f].filter(function (v) { return v !== el.value; });
      if (el.checked) state[f].push(el.value);
      apply();
    } else if (el === sortSel) {
      sortBy = sortSel.value;
      apply();
    }
  });
  document.addEventListener('click', function (e) {
    var t = e.target, b;
    if (!t.closest) return;
    if ((b = t.closest('button[data-f="minRating"]'))) { state.minRating = parseFloat(b.getAttribute('data-v')) || 0; apply(); return; }
    if ((b = t.closest('[data-top]'))) { togglePop(b.getAttribute('data-top'), b); return; }
    if ((b = t.closest('[data-clear]'))) { clearKeys(TOP_KEYS[b.getAttribute('data-clear')]); return; }
    if (t.closest('[data-pop-close]')) { closePop(true); return; }
    if (t.closest('[data-reset]')) {
      state = blank();
      sortBy = 'recommended';
      if (sortSel) sortSel.value = 'recommended';
      apply();
      return;
    }
    if ((b = t.closest('[data-open-filters]'))) { openDlg(filters, b, b.getAttribute('data-open-filters')); return; }
    if ((b = t.closest('[data-open-map]'))) { openDlg(map, b); return; }
    if ((b = t.closest('[data-dlg-close]'))) { closeDlg(b.closest('[role="dialog"]')); return; }
    if (t === filters || t === map) { closeDlg(t); return; }
    if (t.closest('[data-subcat]')) { toggleSub(); return; }
    if ((b = t.closest('[data-fav]'))) { b.setAttribute('aria-pressed', String(b.getAttribute('aria-pressed') !== 'true')); return; }
    if ((b = t.closest('[data-pin]'))) { selected = b.getAttribute('data-pin'); markPins(); renderCard(); return; }
    if (t.closest('[data-unpin]')) { selected = null; markPins(); renderCard(); return; }
    if (openPop && !t.closest('.kpop')) closePop(false);
  });
  document.addEventListener('keydown', function (e) {
    if (e.key === 'Escape') {
      if (stack.length) { e.preventDefault(); closeDlg(stack[stack.length - 1].dlg); }
      else if (openPop) closePop(true);
      return;
    }
    if (e.key === 'Tab' && stack.length) trap(e, stack[stack.length - 1].dlg);
  });

  apply();
})();
