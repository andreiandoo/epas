/* bilete.online v2: organizer documents (/organizator/documente). The activities from /organizer/documents/events as a
   searchable list (running ones first); choosing one shows the "service unavailable" notice, as on the old page:
   document generation stays off until core has document templates. ?event={id} preselects. Runs inside the organizer
   shell (window.BO_ORG); text from the API is always written as text. */
(function () {
  'use strict';
  var O = window.BO_ORG, root = document.getElementById('od');
  if (!O || !root) return;
  var F = O.fmt, el = O.el, icon = O.icon;
  var $ = function (id) { return document.getElementById(id); };
  var events = [], picked = null;

  function norm(s) { return String(s == null ? '' : s).normalize('NFD').replace(/[̀-ͯ]/g, '').toLowerCase(); }
  function naiveDay(v) { var m = /^(\d{4}-\d{2}-\d{2})/.exec(String(v || '')); return m ? m[1] : ''; }
  function live(e) {
    if (e.is_cancelled || e.is_postponed || e.is_past || e.is_ended) return false;
    if (e.status && e.status !== 'published' && e.status !== 'active') return false;
    var end = naiveDay(e.ends_at || e.starts_at);
    return !end || end >= F.ymd();
  }
  function meta(e) {
    var d = F.dateOf(naiveDay(e.starts_at));
    return [d ? F.date(d, { day: 'numeric', month: 'short', year: 'numeric' }) : '', F.flat(e.venue_name), live(e) ? 'în desfășurare' : 'încheiată'].filter(Boolean).join(' · ');
  }
  function load() {
    var box = $('od-list');
    O.api('/organizer/documents/events').then(function (r) {
      var list = r && r.data && Array.isArray(r.data.events) ? r.data.events : Array.isArray(r && r.data) ? r.data : [];
      events = list.filter(function (e) { return e && e.id != null; }).sort(function (a, b) {
        var al = live(a), bl = live(b), ad = String(a.starts_at || ''), bd = String(b.starts_at || '');
        if (al !== bl) return al ? -1 : 1;
        return al ? (ad < bd ? -1 : ad > bd ? 1 : 0) : (ad > bd ? -1 : ad < bd ? 1 : 0);
      });
      $('od-pick-p').textContent = events.length ? F.count(events.length, 'activitate', 'activități') + '; cele în desfășurare apar primele.' : 'Nu ai activități.';
      var pre = new URLSearchParams(location.search).get('event');
      if (pre) picked = events.filter(function (e) { return String(e.id) === pre; })[0] || null;
      draw();
      show();
    }, function (err) {
      if (err && err.status === 401) return;
      $('od-pick-p').textContent = 'Eroare la încărcarea activităților.';
      var retry = el('button', { type: 'button', text: 'Reîncearcă' });
      retry.addEventListener('click', function () { box.textContent = ''; load(); });
      box.textContent = '';
      box.appendChild(el('li', { class: 'od-msg' }, ['Nu am putut încărca activitățile.', retry]));
    });
  }
  function draw() {
    var box = $('od-list'), q = norm($('od-q').value.trim()), a = document.activeElement, key = a && box.contains(a) ? a.getAttribute('data-id') : null;
    var shown = events.filter(function (e) { return !q || norm(F.flat(e.name) + ' ' + F.flat(e.venue_name)).indexOf(q) > -1; });
    box.textContent = '';
    if (!events.length) { box.appendChild(el('li', { class: 'od-msg', text: 'Nu ai activități.' })); return; }
    if (!shown.length) box.appendChild(el('li', { class: 'od-msg', text: 'Niciun rezultat.' }));
    shown.forEach(function (e) {
      var on = !!picked && String(picked.id) === String(e.id);
      var b = el('button', { class: 'od-ev', type: 'button', 'data-id': String(e.id), 'aria-pressed': String(on) }, [
        el('span', { class: 'od-dot' + (live(e) ? ' is-live' : ''), 'aria-hidden': 'true' }),
        el('span', null, [el('b', { text: F.flat(e.name) || 'Activitate' }), el('small', { text: meta(e) })]),
        icon('check'),
      ]);
      b.addEventListener('click', function () { choose(e); });
      box.appendChild(el('li', null, b));
    });
    if (key) { var same = box.querySelector('[data-id="' + key + '"]'); if (same) same.focus(); }
  }
  function choose(e) {
    picked = e;
    try { history.replaceState(null, '', location.pathname + '?event=' + encodeURIComponent(e.id)); } catch (x) {}
    draw();
    show();
  }
  function show() {
    $('od-notice').hidden = !picked;
    $('od-picked').textContent = picked ? F.flat(picked.name) || 'Activitate' : '';
  }
  $('od-q').addEventListener('input', function () { if (events.length) draw(); });
  O.ready.then(function (ok) { if (ok) load(); });
})();
