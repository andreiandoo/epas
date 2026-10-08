/* Viaqui: the trip list. Places a visitor keeps for later ("Add to your trip"), shown on /plan and turned into a plan
 * by the planner of their country.
 *
 * The list lives in the browser (localStorage, key vq_trip_list), so it works without an account and nothing is sent
 * anywhere. One entry: { s: slug, n: name, c: country slug, cn: country name, city, t: type, img }.
 *
 *   [data-trip-add]        a button on an attraction page; data-trip holds the entry as JSON
 *   [data-trip-list]       on /plan: the saved places, grouped by country, each group with a link to its planner
 *   [data-trip-country]    on /plan/{country}: a line that offers to build a plan from the places saved there
 *
 * window.VQTrip = { all(), has(slug), add(entry), remove(slug), forCountry(slug) }
 */
(function () {
  'use strict';

  var KEY = 'vq_trip_list', MAX = 200;

  function all() {
    try {
      var v = JSON.parse(localStorage.getItem(KEY) || '[]');
      return Array.isArray(v) ? v.filter(function (e) { return e && typeof e.s === 'string' && e.s; }) : [];
    } catch (e) { return []; }
  }
  function write(list) {
    try { localStorage.setItem(KEY, JSON.stringify(list.slice(0, MAX))); } catch (e) {}
    paint();
  }
  function has(slug) { return all().some(function (e) { return e.s === slug; }); }
  function add(entry) {
    if (!entry || !entry.s || has(entry.s)) return;
    var list = all();
    list.unshift(entry);
    write(list);
  }
  function remove(slug) { write(all().filter(function (e) { return e.s !== slug; })); }
  function forCountry(c) { return all().filter(function (e) { return e.c === c; }); }

  window.VQTrip = { all: all, has: has, add: add, remove: remove, forCountry: forCountry };

  function el(tag, cls, text) {
    var n = document.createElement(tag);
    if (cls) n.className = cls;
    if (text !== undefined && text !== null) n.textContent = text;
    return n;
  }
  function thumb(name) {
    if (!name) return '';
    if (name.indexOf('http') === 0 || name.charAt(0) === '/') return name;
    return '/api/img.php?c=' + encodeURIComponent(name) + '&w=240&h=240';
  }

  /* ---------------------------------------------------------------- the button on an attraction page */
  function paintButtons() {
    [].forEach.call(document.querySelectorAll('[data-trip-add]'), function (b) {
      var entry = {};
      try { entry = JSON.parse(b.getAttribute('data-trip') || '{}'); } catch (e) {}
      var on = !!entry.s && has(entry.s);
      b.setAttribute('aria-pressed', String(on));
      var label = b.querySelector('[data-trip-label]');
      if (label) label.textContent = on ? VQ.t('In your trip') : VQ.t('Add to your trip');
      var note = document.querySelector('[data-trip-note]');
      if (note) {
        note.hidden = !on;
        var n = all().length, a = note.querySelector('a');
        if (a) a.textContent = VQ.t('See your trip list ({count})', { count: VQ.n(n, 'place', 'places') });
      }
    });
  }
  document.addEventListener('click', function (ev) {
    var b = ev.target.closest ? ev.target.closest('[data-trip-add]') : null;
    if (!b) return;
    var entry = {};
    try { entry = JSON.parse(b.getAttribute('data-trip') || '{}'); } catch (e) {}
    if (!entry.s) return;
    if (has(entry.s)) remove(entry.s); else add(entry);
  });

  /* ---------------------------------------------------------------- /plan: everything that was saved */
  function paintList() {
    var host = document.querySelector('[data-trip-list]');
    if (!host) return;
    var list = all(), box = host.querySelector('[data-trip-groups]'), empty = host.querySelector('[data-trip-empty]');
    var sum = host.querySelector('[data-trip-sum]');
    if (!box) return;
    box.textContent = '';
    if (empty) empty.hidden = list.length > 0;
    if (sum) sum.textContent = list.length ? VQ.t('{count} saved', { count: VQ.n(list.length, 'place', 'places') }) : '';
    var groups = {}, order = [];
    list.forEach(function (e) {
      var k = e.c || '';
      if (!groups[k]) { groups[k] = []; order.push(k); }
      groups[k].push(e);
    });
    order.forEach(function (k) {
      var items = groups[k], g = el('section', 'tl-group');
      var head = el('div', 'tl-head');
      head.appendChild(el('h3', '', (items[0].cn || VQ.t('Other places')) + ' · ' + VQ.n(items.length, 'place', 'places')));
      if (k) {
        var go = el('a', 'btn btn-primary tl-go', VQ.t('Plan a trip with these'));
        go.href = VQ.url('/plan/' + encodeURIComponent(k) + '?list=1');
        head.appendChild(go);
      }
      g.appendChild(head);
      var ul = el('ul', 'tl-items');
      items.forEach(function (e) {
        var li = el('li', 'tl-item');
        var a = el('a', 'tl-link');
        a.href = VQ.url('/attraction/' + encodeURIComponent(e.s));
        var media = el('span', 'tl-media');
        if (e.img) {
          var img = el('img');
          img.src = thumb(e.img);
          img.alt = '';
          img.loading = 'lazy';
          img.decoding = 'async';
          media.appendChild(img);
        }
        a.appendChild(media);
        var text = el('span', 'tl-text');
        text.appendChild(el('b', '', e.n || e.s));
        text.appendChild(el('span', '', [e.t, e.city].filter(Boolean).join(' · ')));
        a.appendChild(text);
        li.appendChild(a);
        var x = el('button', 'tl-x', '×');
        x.type = 'button';
        x.setAttribute('aria-label', e.n ? VQ.t('Remove {name} from your trip list', { name: e.n }) : VQ.t('Remove this place from your trip list'));
        x.addEventListener('click', function () { remove(e.s); });
        li.appendChild(x);
        ul.appendChild(li);
      });
      g.appendChild(ul);
      box.appendChild(g);
    });
  }

  /* ---------------------------------------------------------------- /plan/{country}: what was saved in this country */
  function paintCountry() {
    var host = document.querySelector('[data-trip-country]');
    if (!host) return;
    var n = forCountry(host.getAttribute('data-trip-country')).length;
    host.hidden = n === 0;
    var t = host.querySelector('[data-trip-count]');
    if (t) t.textContent = VQ.n(n, 'place', 'places');
  }

  function paint() { paintButtons(); paintList(); paintCountry(); }
  paint();
  // another tab changed the list
  window.addEventListener('storage', function (e) { if (e.key === KEY) paint(); });
})();
