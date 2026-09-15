/* bilete.online v2: organizer notifications (/organizator/notificari). The list from /organizer/notifications with the
   type and read filters and paging, the figures from core's totals, mark one or all as read (/{id}/read, /read-all),
   delete one. Runs inside the organizer shell (window.BO_ORG); text from the API is always written as text and links
   from core are kept only when they point inside the site. */
(function () {
  'use strict';
  var O = window.BO_ORG, root = document.getElementById('on');
  if (!O || !root) return;
  var F = O.fmt, el = O.el, icon = O.icon;
  var $ = function (id) { return document.getElementById(id); };

  var ICONS = { ticket_sale: 'ticket', refund_request: 'arrow-counter-clockwise', document_generated: 'file-text', service_order_invoice: 'receipt', emergency_report: 'warning-circle' };
  var TONES = { success: 'is-ok', green: 'is-ok', warning: 'is-wait', amber: 'is-wait', danger: 'is-bad', red: 'is-bad', info: 'is-info', blue: 'is-info', primary: 'is-info' };
  var TYPES = {
    ticket_sale: 'Vânzări bilete', refund_request: 'Cereri de rambursare', document_generated: 'Documente generate',
    service_order: 'Comenzi servicii', service_order_started: 'Servicii pornite', service_order_completed: 'Servicii finalizate',
    service_order_invoice: 'Facturi servicii', service_order_results: 'Rezultate servicii', payout_request: 'Cereri de plată',
    payout_approved: 'Plăți aprobate', payout_processing: 'Plăți în procesare', payout_completed: 'Plăți finalizate', payout_rejected: 'Plăți respinse',
  };
  var items = [], page = 1, more = false, total = 0, seq = 0;

  function busyBtn(btn, on, text) {
    var l = btn.querySelector('[data-label]');
    if (on) { btn.setAttribute('aria-busy', 'true'); if (l) { btn.setAttribute('data-idle', l.textContent); l.textContent = text; } }
    else { btn.removeAttribute('aria-busy'); if (l && btn.hasAttribute('data-idle')) l.textContent = btn.getAttribute('data-idle'); btn.removeAttribute('data-idle'); }
  }
  function iconOf(t) { return ICONS[t] || (/^payout/.test(t) ? 'bank' : /^service_order/.test(t) ? 'lightning' : 'bell'); }
  function typeLabel(n) { return TYPES[n.type] || F.flat(n.type_label) || ''; }
  function filtered() { return !!($('on-type').value || $('on-read').value); }
  function url(p, perPage, extra) {
    var t = $('on-type').value, r = $('on-read').value;
    return '/organizer/notifications?per_page=' + perPage + '&page=' + p + (extra || ((t ? '&type=' + encodeURIComponent(t) : '') + (r !== '' ? '&read=' + r : '')));
  }

  function loadTypes() {
    O.api('/organizer/notifications/types', { quiet: true }).then(function (r) {
      var types = (r && r.data && r.data.types) || {}, sel = $('on-type');
      Object.keys(types).forEach(function (k) { sel.appendChild(el('option', { value: k, text: TYPES[k] || F.flat(types[k]) || k })); });
    }, function () {});
  }
  function stats() {
    [['total', ''], ['unread', '&read=0'], ['sales', '&type=ticket_sale'], ['docs', '&type=document_generated']].forEach(function (s) {
      O.api(url(1, 1, s[1]), { quiet: true }).then(function (r) {
        var n = F.toNum(O.metaOf(r).total);
        $('on-s-' + s[0]).textContent = F.num(n);
        if (s[0] === 'unread') $('on-all').hidden = n < 1;
      }, function () { $('on-s-' + s[0]).textContent = '—'; });
    });
  }
  function load(append, wanted) {
    var my = ++seq, p = append ? page + 1 : 1, btn = $('on-more');
    if (append) busyBtn(btn, true, 'Se încarcă…');
    return O.api(url(p, 20)).then(function (r) {
      if (my !== seq) return;
      var list = (Array.isArray(r && r.data) ? r.data : []).filter(function (n) { return n && n.id != null; }), meta = O.metaOf(r);
      items = append ? items.concat(list) : list;
      page = p;
      more = F.toNum(meta.last_page) > p;
      total = F.toNum(meta.total) || items.length;
      busyBtn(btn, false);
      draw(wanted);
    }, function (err) {
      busyBtn(btn, false);
      if (my !== seq || (err && err.status === 401)) return;
      if (append) { O.flash('Nu am putut încărca mai multe notificări. Încearcă din nou.', true); return; }
      items = [];
      var box = $('on-list'), retry = el('button', { type: 'button', text: 'Reîncearcă' });
      retry.addEventListener('click', function () { load(); });
      box.textContent = '';
      box.appendChild(el('li', { class: 'on-empty' }, [el('span', { class: 'on-ic is-bad' }, icon('warning-circle')), el('b', { text: 'Nu am putut încărca notificările' }), el('p', { text: 'Verifică conexiunea și încearcă din nou.' }), retry]));
      $('on-more').hidden = true;
    });
  }
  function draw(wanted) {
    var box = $('on-list'), a = document.activeElement, key = a && box.contains(a) ? a.getAttribute('data-focus') : null;
    key = key || wanted || null;
    box.textContent = '';
    $('on-list-p').textContent = items.length ? F.count(total, 'notificare', 'notificări') + (filtered() ? ' pentru filtrele alese' : '') + '.' : '';
    if (!items.length) {
      var kids = [el('span', { class: 'on-ic is-ok' }, icon('bell'))];
      if (filtered()) {
        var reset = el('button', { type: 'button', text: 'Arată toate notificările' });
        reset.addEventListener('click', function () { $('on-type').value = ''; $('on-read').value = ''; load(); $('on-type').focus(); });
        kids.push(el('b', { text: 'Nicio notificare pentru filtrele alese' }), reset);
      } else kids.push(el('b', { text: 'Nu ai notificări' }), el('p', { text: 'Vei primi notificări când apar vânzări noi, documente sau alte evenimente importante.' }));
      box.appendChild(el('li', { class: 'on-empty' }, kids));
    }
    items.forEach(function (n) { box.appendChild(row(n)); });
    $('on-more').hidden = !more;
    if (key) { var same = box.querySelector('[data-focus="' + key + '"]'); if (same) same.focus(); }
  }
  function row(n) {
    var id = String(n.id), unread = !n.is_read, href = O.safeHref(n.action_url, null), when = F.ago(n.created_at), type = typeLabel(n);
    var meta = [];
    if (unread) meta.push(el('span', { class: 'on-dot', 'aria-label': 'Necitită', role: 'img' }));
    if (when) meta.push(el('span', { text: when }));
    if (type) meta.push(el('span', { class: 'org-tag ' + (TONES[n.color] || 'is-muted'), text: type }));
    var acts = [];
    if (href) acts.push(el('a', { class: 'on-pill', href: href, 'data-focus': 'n-open-' + id }, [icon('arrow-right'), 'Vezi detalii']));
    if (unread) {
      var read = el('button', { class: 'on-pill', type: 'button', 'data-focus': 'n-read-' + id, 'aria-label': 'Marchează ca citită: ' + F.flat(n.title) }, [icon('check'), 'Marchează ca citită']);
      read.addEventListener('click', function () { markRead(n, read); });
      acts.push(read);
    }
    var del = el('button', { class: 'on-pill is-icon', type: 'button', 'data-focus': 'n-del-' + id, 'aria-label': 'Șterge notificarea: ' + F.flat(n.title), title: 'Șterge' }, icon('trash'));
    del.addEventListener('click', function () { remove(n, del); });
    acts.push(del);
    return el('li', { class: 'on-item' + (unread ? ' is-unread' : '') }, [
      el('span', { class: 'on-ic ' + (TONES[n.color] || ''), 'aria-hidden': 'true' }, icon(iconOf(n.type))),
      el('div', { class: 'on-t' }, [el('b', { text: F.flat(n.title) || 'Notificare' }), F.flat(n.message) ? el('p', { text: F.flat(n.message) }) : null, el('div', { class: 'on-meta' }, meta)]),
      el('div', { class: 'on-act' }, acts),
    ]);
  }
  function neighbour(n) {
    var i = items.indexOf(n), next = items[i + 1] || items[i - 1];
    return next ? 'n-del-' + next.id : null;
  }
  function markRead(n, btn) {
    btn.disabled = true;
    O.api('/organizer/notifications/' + n.id + '/read', { method: 'POST', body: {} }).then(function () {
      n.is_read = true;
      var key = 'n-del-' + n.id;
      if ($('on-read').value === '0') { key = neighbour(n); items = items.filter(function (x) { return x !== n; }); total = Math.max(0, total - 1); }
      draw(key);
      stats();
    }, function (err) {
      btn.disabled = false;
      if (err && err.status === 401) return;
      if (err && err.status === 404) { O.flash('Notificarea nu mai există.', true); load(); stats(); return; }
      O.flash('Nu am putut marca notificarea. Încearcă din nou.', true);
    });
  }
  function remove(n, btn) {
    btn.disabled = true;
    O.api('/organizer/notifications/' + n.id, { method: 'DELETE' }).then(function () {
      var key = neighbour(n);
      items = items.filter(function (x) { return x !== n; });
      total = Math.max(0, total - 1);
      O.flash('Notificarea a fost ștearsă.');
      draw(key);
      if (!key) $('on-type').focus();
      stats();
    }, function (err) {
      btn.disabled = false;
      if (err && err.status === 401) return;
      if (err && err.status === 404) { O.flash('Notificarea fusese deja ștearsă.'); load(); stats(); return; }
      O.flash('Nu am putut șterge notificarea. Încearcă din nou.', true);
    });
  }
  $('on-all').addEventListener('click', function () {
    var btn = this;
    if (btn.getAttribute('aria-busy') === 'true') return;
    busyBtn(btn, true, 'Se marchează…');
    O.api('/organizer/notifications/read-all', { method: 'POST', body: {} }).then(function () {
      busyBtn(btn, false);
      O.flash('Toate notificările au fost marcate ca citite.');
      $('on-type').focus();
      load();
      stats();
    }, function (err) {
      busyBtn(btn, false);
      if (err && err.status === 401) return;
      O.flash('Nu am putut marca notificările. Încearcă din nou.', true);
    });
  });
  $('on-type').addEventListener('change', function () { load(); });
  $('on-read').addEventListener('change', function () { load(); });
  $('on-more').addEventListener('click', function () { if (this.getAttribute('aria-busy') !== 'true') load(true); });

  O.ready.then(function (ok) { if (!ok) return; loadTypes(); stats(); load(); });
})();
