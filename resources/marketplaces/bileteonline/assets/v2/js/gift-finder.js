/* bilete.online v2: gift experience finder (/experiente-cadou). Scores the server-rendered activities on the answers,
   says why each one fits, keeps the pick in a bar at the bottom and hands it to the gift card configurator
   (/card-cadou#cumpara) through localStorage. The answers survive a trip to an activity page (sessionStorage). */
(function () {
  'use strict';
  var $ = function (id) { return document.getElementById(id); };
  var form = $('gf-form'), list = $('gf-list');
  if (!form || !list) return;

  var data;
  try { data = JSON.parse($('gf-data').textContent); } catch (e) { return; }
  var ITEMS = {};
  (data.items || []).forEach(function (it) { ITEMS[it.slug] = it; });
  var LIKES = {};
  (data.likes || []).forEach(function (l) { LIKES[l.key] = l; });
  var WHO = {};
  (data.who || []).forEach(function (w) { WHO[w.key] = w; });
  var AMOUNTS = data.amounts || [50, 100, 150, 250, 500, 1000];
  var CITY_NAMES = {};
  (data.cities || []).forEach(function (c) { CITY_NAMES[c.slug] = c.name; });

  var rows = [].slice.call(list.querySelectorAll('.gf-item'));
  var serverOrder = rows.map(function (li) { return li.getAttribute('data-slug'); });
  var people = $('gf-people'), city = $('gf-city'), setting = $('gf-setting');
  var count = $('gf-count'), none = $('gf-none');
  var tray = $('gf-tray'), trayText = $('gf-tray-text'), trayList = $('gf-tray-list'), trayValue = $('gf-tray-value'), go = $('gf-go');
  var MIN_PEOPLE = 1, MAX_PEOPLE = 20;

  var state = { who: '', people: 2, budget: 0, likes: [], city: '', setting: '' };
  var picked = [];

  var money = new Intl.NumberFormat('ro-RO', { maximumFractionDigits: 0 });
  function lei(cents) { return money.format(Math.round(cents / 100)) + ' lei'; }
  function num(n, one, many) {
    if (n === 1) return '1 ' + one;
    var rem = n % 100;
    return n + ' ' + (n >= 20 && !(rem >= 1 && rem <= 19) ? 'de ' : '') + many;
  }
  function el(tag, cls, text) {
    var node = document.createElement(tag);
    if (cls) node.className = cls;
    if (text != null) node.textContent = text;
    return node;
  }
  function likes(it, key) {
    var l = LIKES[key];
    if (!l) return false;
    if (l.cats.indexOf(it.cat) !== -1) return true;
    if (it.interests.some(function (i) { return l.interests.indexOf(i) !== -1; })) return true;
    return key === 'natura' && it.outdoor;
  }
  function has(list, values) { return list.some(function (v) { return values.indexOf(v) !== -1; }); }

  /* What n people would pay, from the ticket variants [price, places each, min per order, max per order, min age,
     max age]: the cheapest variant that seats them within its order limits. Child tickets only count for the
     children (one for "Un copil", half the group for "Familie"); without variants, the lowest price per person. */
  function groupCost(it, n, forKids) {
    var best = null;
    (it.v || []).forEach(function (v) {
      var childOnly = v[5] != null && v[5] < 18, adultOnly = v[4] != null && v[4] >= 14;
      if ((forKids && adultOnly) || (!forKids && childOnly)) return;
      var q = Math.ceil(n / Math.max(1, v[1]));
      if ((v[2] && q < v[2]) || (v[3] && q > v[3])) return;
      if (best === null || q * v[0] < best) best = q * v[0];
    });
    return best;
  }
  function cost(it, n) {
    var kids = state.who === 'copil' ? 1 : state.who === 'familie' ? Math.floor(n / 2) : 0;
    var fallback = it.cents == null ? null : it.cents * n;
    if (!it.v || !it.v.length) return fallback;
    var adults = n - kids;
    var a = adults ? groupCost(it, adults, false) : 0;
    var k = kids ? groupCost(it, kids, true) : 0;
    return a === null || k === null ? fallback : a + k;
  }
  function anyAnswer() { return !!(state.who || state.budget || state.likes.length || state.city || state.setting); }

  /* Hard limits first (a miss hides the activity), then points, each with the reason in words. */
  function score(it) {
    var why = [], pts = 20, n = state.people;
    if (state.city && it.citySlug !== state.city) return null;
    if (state.setting === 'indoor' && !it.indoor) return null;
    if (state.setting === 'outdoor' && !it.outdoor) return null;
    if (state.who === 'copil' && !it.kid) return null;
    if (it.cap > 0 && it.cap < n) return null;
    var total = cost(it, n);
    if (state.budget && total != null && total > state.budget * 125) return null; // more than a quarter over budget

    if (state.likes.length) {
      var hit = state.likes.filter(function (k) { return likes(it, k); });
      if (!hit.length) return null;
      pts += 30 + 5 * (hit.length - 1);
      why.push('Pe gustul lor: ' + hit.map(function (k) { return LIKES[k].label.toLowerCase(); }).join(', '));
    }
    switch (state.who) {
      case 'partener':
        if (it.travelers.indexOf('cupluri') !== -1 || it.interests.indexOf('romantic') !== -1) { pts += 20; why.push('Recomandată pentru cupluri'); }
        else if (it.cap === 0 || it.cap >= 2) { pts += 8; why.push('Se poate merge în doi'); }
        break;
      case 'prieten':
        if (has(it.travelers, ['prieteni', 'grupuri'])) { pts += 18; why.push('Recomandată pentru prieteni'); }
        break;
      case 'copil':
        pts += 22; why.push('Potrivită pentru copii');
        break;
      case 'familie':
        if (it.kid) { pts += 20; why.push('Potrivită pentru familii cu copii'); }
        if (it.travelers.indexOf('familii') !== -1) { pts += 10; }
        break;
      case 'echipa':
        if (has(it.travelers, ['grupuri', 'team-building'])) { pts += 20; why.push('Recomandată pentru grupuri'); }
        break;
      case 'parinti':
        if (it.travelers.indexOf('seniori') !== -1) { pts += 20; why.push('Recomandată pentru seniori'); }
        if (it.accessible) { pts += 12; why.push('Accesibilă'); }
        if (it.cat === 'parcuri-de-aventura') pts -= 15;
        break;
    }
    if (n > 2 && it.cap >= n) { pts += 6; why.push('Loc pentru ' + n + ' persoane în același interval'); }
    if (state.budget && total != null) {
      if (total <= state.budget * 100) { pts += 25; why.push('În buget: ' + lei(total) + (n > 1 ? ' pentru ' + n + ' pers.' : '')); }
      else { pts += 5; why.push('Puțin peste buget: ' + lei(total) + (n > 1 ? ' pentru ' + n + ' pers.' : '')); }
    }
    if (state.city) why.push('În ' + (CITY_NAMES[state.city] || it.city));
    if (state.setting === 'indoor') why.push('În interior, indiferent de vreme');
    if (state.setting === 'outdoor') why.push('În aer liber');
    if (it.featured) pts += 5;
    return { pts: Math.max(0, Math.min(100, pts)), why: why, total: total };
  }

  function render() {
    var results = [];
    rows.forEach(function (li) {
      var it = ITEMS[li.getAttribute('data-slug')];
      var r = it ? score(it) : null;
      li.hidden = !r;
      if (!r) return;
      results.push({ li: li, it: it, r: r });

      var match = li.querySelector('[data-match]');
      match.hidden = !anyAnswer();
      match.textContent = r.pts >= 70 ? 'Potrivire excelentă' : r.pts >= 50 ? 'Potrivire foarte bună' : 'Potrivire bună';
      match.className = 'gf-match' + (r.pts >= 70 ? ' is-top' : '');

      var why = li.querySelector('[data-why]');
      why.textContent = '';
      r.why.slice(0, 3).forEach(function (w) { why.appendChild(el('li', null, w)); });

      var total = li.querySelector('[data-total]');
      total.hidden = !(r.total && state.people > 1);
      total.textContent = r.total ? lei(r.total) + ' pentru ' + state.people + ' pers.' : '';

      var on = picked.indexOf(it.slug) !== -1;
      var pick = li.querySelector('[data-pick]');
      pick.setAttribute('aria-pressed', String(on));
      pick.querySelector('span').textContent = on ? 'Adăugată la cadou' : 'Adaugă la cadou';
      li.classList.toggle('is-picked', on);
    });

    if (anyAnswer()) {
      results.sort(function (a, b) {
        return b.r.pts - a.r.pts || (a.it.cents == null) - (b.it.cents == null) || (a.it.cents || 0) - (b.it.cents || 0);
      });
      results.forEach(function (x) { list.appendChild(x.li); });
    } else {
      serverOrder.forEach(function (slug) {
        var li = rows.filter(function (r) { return r.getAttribute('data-slug') === slug; })[0];
        if (li) list.appendChild(li);
      });
    }

    var total = rows.length;
    count.textContent = anyAnswer() ? results.length + ' din ' + num(total, 'experiență', 'experiențe') + ' se potrivesc' : num(total, 'experiență', 'experiențe');
    none.hidden = results.length > 0 || !total;
    none.querySelectorAll('[data-relax]').forEach(function (b) {
      var k = b.getAttribute('data-relax');
      b.hidden = k === 'city' ? !state.city : k === 'budget' ? !state.budget : !state.likes.length;
    });

    // the answers as pressed buttons
    form.querySelectorAll('[data-q]').forEach(function (group) {
      var q = group.getAttribute('data-q');
      group.querySelectorAll('button').forEach(function (b) {
        var v = b.getAttribute('data-value');
        var on = q === 'likes' ? state.likes.indexOf(v) !== -1 : q === 'budget' ? Number(v) === state.budget : v === state.who;
        b.setAttribute('aria-pressed', String(on));
      });
    });
    people.textContent = state.people;
    $('gf-less').disabled = state.people <= MIN_PEOPLE;
    $('gf-more').disabled = state.people >= MAX_PEOPLE;
    $('gf-people-note').textContent = state.people === 1 ? 'persoană' : 'persoane, cu tot cu cel care primește';
    if (city) city.value = state.city;
    setting.value = state.setting;

    renderTray();
    save();
  }

  function suggestion(cents) {
    var leiTotal = Math.ceil(cents / 100);
    for (var i = 0; i < AMOUNTS.length; i++) if (AMOUNTS[i] >= leiTotal) return AMOUNTS[i];
    return AMOUNTS[AMOUNTS.length - 1];
  }
  function pickSummary() {
    var items = picked.map(function (slug) { return ITEMS[slug]; }).filter(Boolean);
    var cents = items.reduce(function (sum, it) { return sum + (cost(it, state.people) || 0); }, 0);
    return { items: items, cents: cents, value: suggestion(cents), over: Math.ceil(cents / 100) > AMOUNTS[AMOUNTS.length - 1] };
  }
  function renderTray() {
    var s = pickSummary();
    tray.hidden = !s.items.length;
    if (!s.items.length) return;
    trayText.textContent = num(s.items.length, 'experiență', 'experiențe') + ' · ' + (s.cents ? lei(s.cents) + ' pentru ' + num(state.people, 'persoană', 'persoane') : 'preț de confirmat') + (s.over ? ' · peste cel mai mare card' : '');
    trayList.textContent = '';
    s.items.forEach(function (it) {
      var li = el('li'), b = el('button', 'gf-tray-x');
      li.appendChild(el('span', null, it.title));
      b.type = 'button';
      b.setAttribute('data-unpick', it.slug);
      b.setAttribute('aria-label', 'Scoate ' + it.title + ' din cadou');
      b.textContent = '×';
      li.appendChild(b);
      trayList.appendChild(li);
    });
    trayValue.textContent = money.format(s.value) + ' lei';
  }

  function save() {
    try { sessionStorage.setItem('bo_gift_finder', JSON.stringify({ state: state, picked: picked })); } catch (e) {}
  }
  function restore() {
    try {
      var saved = JSON.parse(sessionStorage.getItem('bo_gift_finder') || 'null');
      if (!saved || !saved.state) return;
      var s = saved.state;
      state.who = WHO[s.who] ? s.who : '';
      state.people = Math.min(MAX_PEOPLE, Math.max(MIN_PEOPLE, parseInt(s.people, 10) || 2));
      state.budget = [0, 100, 250, 500, 1000].indexOf(Number(s.budget)) !== -1 ? Number(s.budget) : 0;
      state.likes = (s.likes || []).filter(function (k) { return LIKES[k]; });
      state.city = CITY_NAMES[s.city] ? s.city : '';
      state.setting = s.setting === 'indoor' || s.setting === 'outdoor' ? s.setting : '';
      picked = (saved.picked || []).filter(function (slug) { return ITEMS[slug]; });
    } catch (e) {}
  }

  form.addEventListener('click', function (e) {
    var b = e.target.closest('[data-q] button');
    if (!b) return;
    var q = b.closest('[data-q]').getAttribute('data-q'), v = b.getAttribute('data-value');
    if (q === 'who') {
      state.who = state.who === v ? '' : v;
      if (state.who) state.people = WHO[state.who].people; // a sensible group size for the choice, still editable
    } else if (q === 'budget') {
      state.budget = Number(v);
    } else if (q === 'likes') {
      var i = state.likes.indexOf(v);
      if (i === -1) state.likes.push(v); else state.likes.splice(i, 1);
    }
    render();
  });
  $('gf-less').addEventListener('click', function () { state.people = Math.max(MIN_PEOPLE, state.people - 1); render(); });
  $('gf-more').addEventListener('click', function () { state.people = Math.min(MAX_PEOPLE, state.people + 1); render(); });
  if (city) city.addEventListener('change', function () { state.city = city.value; render(); });
  setting.addEventListener('change', function () { state.setting = setting.value; render(); });
  form.addEventListener('submit', function (e) { e.preventDefault(); });
  $('gf-reset').addEventListener('click', function () {
    state = { who: '', people: 2, budget: 0, likes: [], city: '', setting: '' };
    render();
  });
  none.addEventListener('click', function (e) {
    var b = e.target.closest('[data-relax]');
    if (!b) return;
    var k = b.getAttribute('data-relax');
    if (k === 'city') state.city = '';
    if (k === 'budget') state.budget = 0;
    if (k === 'likes') state.likes = [];
    render();
    $('gf-res-h').focus();
  });

  list.addEventListener('click', function (e) {
    var b = e.target.closest('[data-pick]');
    if (!b) return;
    var slug = b.closest('.gf-item').getAttribute('data-slug');
    var i = picked.indexOf(slug);
    if (i === -1) picked.push(slug); else picked.splice(i, 1);
    render();
  });
  trayList.addEventListener('click', function (e) {
    var b = e.target.closest('[data-unpick]');
    if (!b) return;
    var slug = b.getAttribute('data-unpick');
    picked = picked.filter(function (s) { return s !== slug; });
    render();
    var row = list.querySelector('.gf-item[data-slug="' + slug + '"] [data-pick]');
    if (row && tray.hidden) row.focus();
  });

  // hand the pick to the gift card configurator
  go.addEventListener('click', function () {
    var s = pickSummary();
    var who = WHO[state.who];
    try {
      localStorage.setItem('bo_gift_pick', JSON.stringify({
        v: 1, ts: Date.now(), value: s.value, people: state.people, cents: s.cents, who: who ? who.label : '',
        items: s.items.map(function (it) { return { slug: it.slug, title: it.title, href: it.href, city: it.city, cents: it.cents }; }),
      }));
    } catch (e) {}
  });

  /* the panel's own scrollbar: a thumb over the content, shown while it scrolls or is dragged */
  (function () {
    var panel = form.parentNode, thumb = $('gf-thumb');
    if (!thumb || !panel.classList.contains('gf-panel')) return;
    var hideTimer = null, drag = null;
    function place() {
      var view = form.clientHeight, full = form.scrollHeight;
      if (full <= view + 1) { thumb.style.display = 'none'; return false; }
      thumb.style.display = '';
      var h = Math.max(32, Math.round(view * view / full));
      thumb.style.height = h + 'px';
      thumb.style.transform = 'translateY(' + Math.round(form.offsetTop + form.scrollTop / (full - view) * (view - h)) + 'px)';
      return true;
    }
    function show() {
      if (!place()) return;
      panel.classList.add('is-scrolling');
      clearTimeout(hideTimer);
      hideTimer = setTimeout(function () { if (!drag) panel.classList.remove('is-scrolling'); }, 900);
    }
    form.addEventListener('scroll', show, { passive: true });
    window.addEventListener('resize', place);
    thumb.addEventListener('pointerdown', function (e) {
      e.preventDefault();
      drag = { y: e.clientY, top: form.scrollTop };
      thumb.setPointerCapture(e.pointerId);
      panel.classList.add('is-dragging');
    });
    thumb.addEventListener('pointermove', function (e) {
      if (!drag) return;
      var view = form.clientHeight, full = form.scrollHeight, h = thumb.offsetHeight;
      form.scrollTop = drag.top + (e.clientY - drag.y) * (full - view) / Math.max(1, view - h);
    });
    function end() {
      if (!drag) return;
      drag = null;
      panel.classList.remove('is-dragging');
      show();
    }
    thumb.addEventListener('pointerup', end);
    thumb.addEventListener('pointercancel', end);
    place();
  })();

  restore();
  render();
})();
