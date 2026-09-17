/* bilete.online v2: customer support tickets (/cont/tichete-support). Lists the customer's tickets
   (GET /customer/support-tickets, filtered in the browser), creates one (POST, with the departments and problem types
   from /customer/support-meta), opens a thread (GET /customer/support-tickets/{id}) and replies
   (POST /customer/support-tickets/{id}/messages). Lists and threads are asked for fresh (noCache), so a new ticket or
   reply shows at once. A ticket id from /cont/tichete-support/{id} (v2-data) or #t-<id> opens that thread.
   Creating only counts as done when the answer carries the new ticket: before the proxy fix a POST came back as the
   ticket list with success: true, and the old page closed the form as if the ticket had been sent.
   Text from the API is always written as text. */
(function () {
  'use strict';
  var $ = function (id) { return document.getElementById(id); };
  if (!$('sp-content') || !window.BO_ACCOUNT) return;
  var account = window.BO_ACCOUNT;

  var data = {};
  try { data = JSON.parse(($('v2-data') || {}).textContent || '{}'); } catch (e) {}
  var MONTHS = ['ian', 'feb', 'mar', 'apr', 'mai', 'iun', 'iul', 'aug', 'sep', 'oct', 'noi', 'dec'];
  var STATUS = { open: ['deschis', 'is-wait'], in_progress: ['în lucru', 'is-ok'], awaiting_organizer: ['în așteptare', 'is-wait'], resolved: ['rezolvat', 'is-muted'], closed: ['închis', 'is-muted'] };
  var PRIORITY = { low: ['prioritate scăzută', 'is-muted'], high: ['prioritate ridicată', 'is-wait'], urgent: ['urgent', 'is-bad'] };
  var MAX = 5000;
  var num = new Intl.NumberFormat('ro-RO');
  var tickets = [], counts = null, meta = { departments: [], problem_types: [] }, filter = 'all', current = null, opener = null, openerRow = '', sayTimer = 0;
  var hashId = /^#t-(\d+)$/.exec(window.location.hash || '');
  var wantTicket = Number(data.ticket) || (hashId ? Number(hashId[1]) : 0);

  // ---------- helpers ----------
  function el(tag, cls, text) { var n = document.createElement(tag); if (cls) n.className = cls; if (text != null) n.textContent = text; return n; }
  function ic(name) {
    var ns = 'http://www.w3.org/2000/svg', svg = document.createElementNS(ns, 'svg'), use = document.createElementNS(ns, 'use');
    svg.setAttribute('class', 'ic'); svg.setAttribute('aria-hidden', 'true'); use.setAttribute('href', '#i-' + name); svg.appendChild(use);
    return svg;
  }
  function txt(v) {
    if (v && typeof v === 'object') v = v.ro || v.en || v.name || Object.keys(v).map(function (k) { return v[k]; })[0];
    return v == null ? '' : String(v);
  }
  function obj(x) { return x && typeof x === 'object'; }
  function count(v) { var n = Number(v); return isFinite(n) && n > 0 ? Math.floor(n) : 0; }
  function plural(n, one, many) {
    n = count(n);
    if (n === 1) return '1 ' + one;
    var r = n % 100;
    return num.format(n) + (n && (r === 0 || r >= 20) ? ' de ' : ' ') + many;
  }
  function show(id, on) { $(id).hidden = !on; }
  function when(v) {
    var d = v ? new Date(v) : null;
    if (!d || isNaN(d.getTime())) return '';
    return d.getDate() + ' ' + MONTHS[d.getMonth()] + ' ' + d.getFullYear() + ' · ' + String(d.getHours()).padStart(2, '0') + ':' + String(d.getMinutes()).padStart(2, '0');
  }
  function say(message, tone) {
    var line = $('sp-status-line');
    clearTimeout(sayTimer);
    line.textContent = message;
    line.classList.toggle('is-error', tone === 'error');
    if (tone !== 'error') sayTimer = setTimeout(function () { line.textContent = ''; }, 8000);
  }
  function guard() { show('sp-content', false); show('sp-guard', true); account.toLogin(); } // the message shows only while the login page loads
  function isActive(t) { return !t.is_closed; }
  function tag(list, label, tone) { if (label) list.appendChild(el('span', 'acc-tag' + (tone ? ' ' + tone : ''), label)); }
  function fillTags(box, t) {
    box.textContent = '';
    var s = STATUS[t.status] || [txt(t.status) || '—', ''], p = PRIORITY[t.priority];
    tag(box, s[0], s[1]);
    if (p) tag(box, p[0], p[1]);
    if (obj(t.department)) tag(box, txt(t.department.name), '');
    if (obj(t.problem_type)) tag(box, txt(t.problem_type.name), '');
  }
  function fresh(path) { return BileteOnlineAPI.request(path, { method: 'GET', noCache: true }); }

  // ---------- list ----------
  function renderCounts() {
    var open = counts && counts.open != null ? count(counts.open) : tickets.filter(isActive).length;
    var total = counts && counts.total != null ? count(counts.total) : tickets.length;
    $('sp-open').textContent = num.format(open);
    $('sp-total').textContent = plural(total, 'tichet în total', 'tichete în total');
    account.setBadges({ support: open });
  }
  function render() {
    var list = tickets.filter(function (t) { return filter === 'all' || (filter === 'active' ? isActive(t) : !isActive(t)); });
    var ul = $('sp-list'), frag = document.createDocumentFragment();
    $('sp-count').textContent = plural(list.length, 'tichet', 'tichete');
    [].forEach.call(document.querySelectorAll('.sp-pills [data-filter]'), function (b) { b.setAttribute('aria-pressed', String(b.getAttribute('data-filter') === filter)); });
    list.forEach(function (t) {
      var li = el('li', 'sp-item'), row = el('button', 'sp-row'), main = el('span', 'sp-row-main'), tags = el('span', 'sp-tags'), go = el('span', 'sp-open', 'Deschide');
      li.id = 't-' + txt(t.id);
      row.type = 'button';
      row.setAttribute('aria-haspopup', 'dialog');
      fillTags(tags, t);
      main.appendChild(tags);
      main.appendChild(el('span', 'sp-subject', txt(t.subject) || 'Fără subiect'));
      main.appendChild(el('span', 'sp-meta', [txt(t.ticket_number), (t.last_activity || t.opened_at) ? 'ultima activitate ' + when(t.last_activity || t.opened_at) : ''].filter(Boolean).join(' · ')));
      go.appendChild(ic('arrow-right'));
      row.appendChild(main);
      row.appendChild(go);
      row.addEventListener('click', function () { openThread(t.id, row); });
      li.appendChild(row);
      frag.appendChild(li);
    });
    ul.textContent = '';
    ul.appendChild(frag);
    show('sp-skel', false);
    show('sp-error', false);
    show('sp-list', list.length > 0);
    show('sp-empty', !list.length);
    if (!list.length) {
      var none = !tickets.length;
      $('sp-empty-h').textContent = none ? 'Niciun tichet deschis' : (filter === 'active' ? 'Niciun tichet activ' : 'Niciun tichet închis');
      $('sp-empty-p').textContent = none ? 'Trimite o solicitare echipei dacă ai nevoie de ajutor.' : 'Schimbă filtrul ca să vezi celelalte tichete.';
    }
  }
  function load(silent) {
    if (!silent) { show('sp-error', false); show('sp-empty', false); show('sp-list', false); show('sp-skel', true); }
    return fresh('/customer/support-tickets').then(function (resp) {
      var d = resp && resp.data;
      tickets = (obj(d) && Array.isArray(d.tickets) ? d.tickets : []).filter(obj);
      counts = obj(d) && obj(d.counts) ? d.counts : null;
      renderCounts();
      render();
      if (wantTicket) {
        var id = wantTicket, li = $('t-' + id);
        wantTicket = 0;
        if (li) li.classList.add('is-target');
        openThread(id, li ? li.querySelector('.sp-row') : null);
      }
    }, function (err) {
      if (err && err.status === 401) { guard(); return; }
      if (silent) return;
      show('sp-skel', false);
      show('sp-error', true);
    });
  }
  [].forEach.call(document.querySelectorAll('.sp-pills [data-filter]'), function (b) {
    b.addEventListener('click', function () { filter = b.getAttribute('data-filter'); render(); });
  });
  $('sp-retry').addEventListener('click', function () { load(false); });

  // ---------- dialogs ----------
  var dialogNew = $('sp-new'), dialogThread = $('sp-thread');
  function openDialog(d, from) {
    opener = from || document.activeElement;
    // the list re-renders after a reply or a new ticket; remember the row so focus can return to its new button
    openerRow = opener && opener.closest && opener.closest('.sp-item') ? opener.closest('.sp-item').id : '';
    if (typeof d.showModal === 'function') { if (!d.open) d.showModal(); }
    else d.setAttribute('open', '');
  }
  function closeDialog(d) {
    if (typeof d.close === 'function') { if (d.open) d.close(); }
    else { d.removeAttribute('open'); d.dispatchEvent(new Event('close')); }
  }
  [dialogNew, dialogThread].forEach(function (d) {
    d.addEventListener('click', function (e) { if (e.target === d) closeDialog(d); });
    d.addEventListener('close', function () {
      if (d === dialogThread) current = null;
      var row = openerRow && $(openerRow);
      if (opener && document.body.contains(opener)) opener.focus();
      else if (row) row.querySelector('.sp-row').focus();
      opener = null;
      openerRow = '';
    });
    [].forEach.call(d.querySelectorAll('[data-close]'), function (b) { b.addEventListener('click', function () { closeDialog(d); }); });
  });

  // ---------- new ticket ----------
  function fillMeta() {
    var deps = (Array.isArray(meta.departments) ? meta.departments : []).filter(obj), sel = $('sp-dep');
    while (sel.options.length > 1) sel.remove(1);
    deps.forEach(function (d) { sel.appendChild(new Option(txt(d.name), String(d.id))); });
    show('sp-dep-field', deps.length > 0);
    fillTypes();
  }
  function fillTypes() {
    var dep = $('sp-dep').value, sel = $('sp-type'), keep = sel.value;
    var types = (Array.isArray(meta.problem_types) ? meta.problem_types : []).filter(obj).filter(function (t) {
      return !dep || t.support_department_id == null || String(t.support_department_id) === dep;
    });
    while (sel.options.length > 1) sel.remove(1);
    types.forEach(function (t) { sel.appendChild(new Option(txt(t.name), String(t.id))); });
    sel.value = types.some(function (t) { return String(t.id) === keep; }) ? keep : '';
    show('sp-type-field', types.length > 0);
  }
  $('sp-dep').addEventListener('change', fillTypes);
  function counter(input, out) { out.textContent = num.format(input.value.length) + ' / ' + num.format(MAX); }
  $('sp-message').addEventListener('input', function () { counter(this, $('sp-message-count')); this.removeAttribute('aria-invalid'); });
  $('sp-subject').addEventListener('input', function () { this.removeAttribute('aria-invalid'); });
  function openNew(from) {
    $('sp-new-form').reset();
    fillMeta();
    counter($('sp-message'), $('sp-message-count'));
    show('sp-new-error', false);
    ['sp-subject', 'sp-message'].forEach(function (id) { $(id).removeAttribute('aria-invalid'); });
    openDialog(dialogNew, from);
    ($('sp-dep-field').hidden ? $('sp-subject') : $('sp-dep')).focus();
  }
  [].forEach.call(document.querySelectorAll('[data-sp-new]'), function (b) {
    b.addEventListener('click', function (e) { openNew(e.currentTarget); });
  });
  function newError(message, field) {
    $('sp-new-error').textContent = message;
    show('sp-new-error', true);
    if (field) { field.setAttribute('aria-invalid', 'true'); field.focus(); }
  }
  $('sp-new-form').addEventListener('submit', function (e) {
    e.preventDefault();
    var subject = $('sp-subject').value.trim(), message = $('sp-message').value.trim(), btn = $('sp-new-submit');
    show('sp-new-error', false);
    if (!subject || !message) { newError('Completează subiectul și mesajul.', !subject ? $('sp-subject') : $('sp-message')); return; }
    var payload = { subject: subject, message: message, priority: $('sp-priority').value };
    if ($('sp-dep').value) payload.support_department_id = Number($('sp-dep').value);
    if ($('sp-type').value) payload.support_problem_type_id = Number($('sp-type').value);
    btn.disabled = true;
    btn.textContent = 'Se trimite…';
    BileteOnlineAPI.post('/customer/support-tickets', payload).then(function (resp) {
      var ticket = resp && resp.success && obj(resp.data) ? resp.data.ticket : null;
      if (!obj(ticket)) throw { status: -1 };
      closeDialog(dialogNew);
      say('Tichetul ' + (txt(ticket.ticket_number) || '') + ' a fost trimis. Vei vedea răspunsul echipei aici.'.replace('Tichetul  a', 'Tichetul a'), 'ok');
      tickets.unshift(ticket);
      counts = null;
      filter = 'all';
      renderCounts();
      render();
      load(true);
    }).catch(function (err) {
      var errors = (err && (err.errors || (err.data && err.data.errors))) || {};
      if (err && err.status === 422) {
        if (errors.subject) newError('Subiectul lipsește sau depășește 200 de caractere.', $('sp-subject'));
        else if (errors.message) newError('Mesajul lipsește sau depășește 5.000 de caractere.', $('sp-message'));
        else newError('Verifică datele din formular și încearcă din nou.');
      } else if (err && err.status === 429) {
        newError('Ai trimis mai multe tichete într-un timp scurt. Încearcă din nou peste un minut.');
      } else if (err && err.status === 401) {
        newError('Sesiunea a expirat. Intră din nou în cont ca să trimiți tichetul.');
      } else {
        newError('Nu am putut trimite tichetul. Încearcă din nou.');
      }
    }).then(function () {
      btn.disabled = false;
      btn.textContent = 'Trimite tichetul';
    });
  });

  // ---------- thread ----------
  function head(t) {
    $('sp-t-num').textContent = t ? txt(t.ticket_number) || 'Tichet' : 'Tichet';
    $('sp-t-h').textContent = t ? txt(t.subject) || 'Fără subiect' : 'Tichet';
    if (t) fillTags($('sp-t-tags'), t); else $('sp-t-tags').textContent = '';
  }
  function bubble(m) {
    var li = el('li', 'sp-msg ' + (m.is_staff ? 'is-staff' : 'is-mine'));
    li.appendChild(el('p', 'sp-msg-body', txt(m.body)));
    li.appendChild(el('p', 'sp-msg-meta', (m.is_staff ? 'Echipa' : 'Tu') + (m.created_at ? ' · ' + when(m.created_at) : '')));
    return li;
  }
  function drawThread() {
    var t = current.ticket, ol = $('sp-t-messages'), state = $('sp-t-state');
    head(t);
    ol.textContent = '';
    current.messages.forEach(function (m) { ol.appendChild(bubble(m)); });
    state.classList.remove('is-error');
    state.textContent = current.messages.length ? '' : 'Nu există mesaje în acest tichet.';
    show('sp-t-state', !current.messages.length);
    var closed = t.status === 'closed';
    show('sp-t-closed', closed);
    show('sp-reply', !closed);
    show('sp-reopen', t.status === 'resolved');
    var body = $('sp-t-body');
    body.scrollTop = body.scrollHeight;
  }
  function openThread(id, from) {
    var summary = tickets.filter(function (t) { return String(t.id) === String(id); })[0] || null;
    current = { id: id, ticket: summary, messages: [] };
    head(summary);
    $('sp-t-messages').textContent = '';
    $('sp-t-state').classList.remove('is-error');
    $('sp-t-state').textContent = 'Se încarcă conversația…';
    show('sp-t-state', true);
    show('sp-t-closed', false);
    show('sp-reply', false);
    show('sp-reply-error', false);
    $('sp-reply-text').value = '';
    counter($('sp-reply-text'), $('sp-reply-count'));
    $('sp-reply-submit').disabled = true;
    openDialog(dialogThread, from);
    fresh('/customer/support-tickets/' + encodeURIComponent(id)).then(function (resp) {
      if (!current || String(current.id) !== String(id)) return;
      var d = resp && resp.data;
      if (!(obj(d) && obj(d.ticket))) throw { status: -1 };
      current.ticket = d.ticket;
      current.messages = (Array.isArray(d.messages) ? d.messages : []).filter(obj);
      drawThread();
    }).catch(function (err) {
      if (!current || String(current.id) !== String(id)) return;
      if (err && err.status === 401) { closeDialog(dialogThread); guard(); return; }
      $('sp-t-state').textContent = err && err.status === 404 ? 'Tichetul nu există sau nu îți aparține.' : 'Nu am putut încărca conversația. Închide și încearcă din nou.';
      $('sp-t-state').classList.add('is-error');
    });
  }
  $('sp-reply-text').addEventListener('input', function () {
    counter(this, $('sp-reply-count'));
    $('sp-reply-submit').disabled = !this.value.trim();
  });
  $('sp-reply').addEventListener('submit', function (e) {
    e.preventDefault();
    if (!current || !current.ticket) return;
    var text = $('sp-reply-text').value.trim(), btn = $('sp-reply-submit'), id = current.id;
    if (!text) return;
    btn.disabled = true;
    btn.textContent = 'Se trimite…';
    show('sp-reply-error', false);
    BileteOnlineAPI.post('/customer/support-tickets/' + encodeURIComponent(id) + '/messages', { message: text }).then(function (resp) {
      if (!(resp && resp.success)) throw { status: -1 };
      var d = obj(resp.data) ? resp.data : {};
      if (current && String(current.id) === String(id)) {
        current.messages.push(obj(d.message) ? Object.assign({ is_staff: false }, d.message) : { body: text, is_staff: false, created_at: new Date().toISOString() });
        if (obj(d.ticket)) current.ticket = d.ticket;
        $('sp-reply-text').value = '';
        counter($('sp-reply-text'), $('sp-reply-count'));
        drawThread();
      }
      load(true);
    }).catch(function (err) {
      var message = err && err.status === 400 ? (txt(err.message) || 'Tichetul este închis. Deschide unul nou pentru o solicitare nouă.')
        : err && err.status === 429 ? 'Ai trimis multe mesaje într-un timp scurt. Încearcă din nou peste un minut.'
        : err && err.status === 401 ? 'Sesiunea a expirat. Intră din nou în cont ca să răspunzi.'
        : 'Nu am putut trimite răspunsul. Încearcă din nou.';
      $('sp-reply-error').textContent = message;
      show('sp-reply-error', true);
    }).then(function () {
      btn.textContent = 'Trimite răspunsul';
      btn.disabled = !$('sp-reply-text').value.trim();
    });
  });

  // ---------- load ----------
  if (!account.isCustomer()) { guard(); return; }
  load(false);
  BileteOnlineAPI.get('/customer/support-meta').then(function (resp) {
    if (resp && obj(resp.data)) { meta = resp.data; fillMeta(); }
  }, function () {});
})();
