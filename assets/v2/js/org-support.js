/* bilete.online v2: operator support tickets. Two pages share this file:
   - /organizator/suport (#osp): the list from /organizer/support/tickets with the status filters and "load more", the
     beta gate (core answers 403 while the account is not on the support allow-list), the empty and error states, and the
     new-ticket dialog (department → problem type → the fields that type asks for → subject, description, attachments),
     sent as multipart through BileteOnlineAPI.organizer.createSupportTicket with the page context;
   - /organizator/suport/{id} (#osd): one ticket from /organizer/support/tickets/{id} with its thread, the reply with
     attachments (…/messages), "Marchează ca rezolvat" (…/close, confirmed in a dialog) and "Redeschide" (…/reopen).
   Runs inside the operator shell (window.BO_ORG); text from the API is always written as text, links from the API are
   kept only when they are http(s). */
(function () {
  'use strict';
  var O = window.BO_ORG;
  if (!O) return;
  var F = O.fmt, el = O.el, icon = O.icon;
  var $ = function (id) { return document.getElementById(id); };

  var STATUS = {
    open: ['Deschis', 'is-info'],
    in_progress: ['În lucru', 'is-ok'],
    awaiting_organizer: ['Așteaptă răspunsul tău', 'is-wait'],
    resolved: ['Rezolvat', 'is-done'],
    closed: ['Închis', 'is-muted'],
  };
  var RULES = { max_size_kb: 3072, allowed_mimes: ['jpg', 'png', 'pdf'], max_per_message: 5 };
  var mbFmt = new Intl.NumberFormat('ro-RO', { maximumFractionDigits: 1 });

  /* ---------- shared ---------- */
  function statusOf(s) { return STATUS[s] || [F.flat(s) || '—', 'is-muted']; }
  function dateText(v) { var d = F.dateOf(v); return d ? F.date(d, { day: 'numeric', month: 'short', year: 'numeric' }) : '—'; }
  function dateTimeText(v) { var d = F.dateOf(v); return d ? F.date(d, { day: 'numeric', month: 'short', year: 'numeric', hour: '2-digit', minute: '2-digit' }) : '—'; }
  /** An absolute http(s) address, or null (relative values are refused: core validates the page URL as a full URL). */
  function httpUrl(u) {
    if (typeof u !== 'string' || !/^https?:\/\//i.test(u.trim())) return null;
    try { var x = new URL(u.trim()); return /^https?:$/.test(x.protocol) && x.hostname ? x.href : null; } catch (e) { return null; }
  }
  function busyBtn(btn, on, text) {
    var l = btn.querySelector('[data-label]') || btn;
    if (on) { btn.setAttribute('aria-busy', 'true'); btn.disabled = true; if (!btn.hasAttribute('data-idle')) btn.setAttribute('data-idle', l.textContent); l.textContent = text; }
    else { btn.removeAttribute('aria-busy'); btn.disabled = false; if (btn.hasAttribute('data-idle')) l.textContent = btn.getAttribute('data-idle'); btn.removeAttribute('data-idle'); }
  }
  /** A message for the operator: core's validation text, else core's message when it is not a generic English one. */
  function errText(err, fallback) {
    if (!err) return fallback;
    if (err.status === 0 || err.status == null) return 'Nu am putut ajunge la server. Verifică conexiunea și încearcă din nou.';
    if (err.status === 413) return 'Fișierele sunt prea mari. Încearcă din nou cu fișiere mai mici.';
    if (err.status === 429) return 'Prea multe încercări într-un timp scurt. Așteaptă un minut și încearcă din nou.';
    var e = err.errors || (err.data && err.data.errors);
    if (e && typeof e === 'object' && !Array.isArray(e)) {
      var k = Object.keys(e)[0], v = k ? e[k] : null;
      if (Array.isArray(v) && v[0]) return String(v[0]);
      if (typeof v === 'string' && v) return v;
    }
    var m = typeof err.message === 'string' ? err.message.trim() : '';
    if (m && !/^(An error occurred|Network error|HTTP \d+|Unauthorized|Unauthenticated|Unknown endpoint|Server Error|Failed to fetch)/i.test(m)) return m;
    return fallback;
  }
  /** Multipart calls bypass BO_ORG.api: a 401 there is checked with a normal call, which sends a dead session to the login. */
  function sessionCheck() { O.api('/organizer/me').catch(function () {}); }
  function refreshBadge() {
    O.api('/organizer/support/tickets?status=open&per_page=1', { quiet: true }).then(function (r) { O.setBadge('support', O.metaOf(r).total); }, function () {});
  }
  function loadRules() {
    return O.api('/organizer/support/departments', { quiet: true }).then(function (r) {
      setRules(r && r.data && r.data.attachment_rules);
      return r;
    });
  }
  function setRules(x) {
    if (!x || typeof x !== 'object') return;
    var mimes = Array.isArray(x.allowed_mimes) ? x.allowed_mimes.map(function (m) { return String(m).toLowerCase().replace(/^\./, ''); }).filter(Boolean) : [];
    RULES = {
      max_size_kb: F.toNum(x.max_size_kb) > 0 ? F.toNum(x.max_size_kb) : RULES.max_size_kb,
      allowed_mimes: mimes.length ? mimes : RULES.allowed_mimes,
      max_per_message: F.toNum(x.max_per_message) > 0 ? Math.round(F.toNum(x.max_per_message)) : RULES.max_per_message,
    };
  }
  function rulesText() {
    return RULES.allowed_mimes.join(', ') + ' — maxim ' + mbFmt.format(RULES.max_size_kb / 1024) + ' MB pe fișier, max ' + RULES.max_per_message + ' fișiere.';
  }
  /** jpg in core's rules also accepts .jpeg files. */
  function acceptOf() {
    var ext = RULES.allowed_mimes.slice();
    if (ext.indexOf('jpg') > -1 && ext.indexOf('jpeg') < 0) ext.push('jpeg');
    return ext.map(function (m) { return '.' + m; }).join(',');
  }
  /** Checks the chosen files against core's rules and lists them; clears the field when one breaks a rule. Returns an error text or ''. */
  function checkFiles(input, list) {
    var files = [].slice.call(input.files || []), max = RULES.max_per_message, maxBytes = RULES.max_size_kb * 1024, msg = '';
    var ext = acceptOf().split(',').map(function (x) { return x.slice(1); });
    if (files.length > max) msg = 'Poți atașa cel mult ' + max + ' fișiere.';
    else {
      files.some(function (f) {
        var e = String(f.name || '').split('.').pop().toLowerCase();
        if (ext.indexOf(e) < 0) { msg = 'Fișierul „' + f.name + '” nu are un format acceptat (' + RULES.allowed_mimes.join(', ') + ').'; return true; }
        if (f.size > maxBytes) { msg = 'Fișierul „' + f.name + '” depășește limita de ' + mbFmt.format(RULES.max_size_kb / 1024) + ' MB.'; return true; }
        return false;
      });
    }
    list.textContent = '';
    if (msg) { input.value = ''; files = []; }
    files.forEach(function (f) {
      list.appendChild(el('li', null, [icon('file-text'), el('span', { class: 'osp-fname', text: f.name }), el('span', { class: 'osp-fsize', text: F.num(Math.max(1, f.size / 1024)) + ' KB' })]));
    });
    list.hidden = !files.length;
    return msg;
  }
  function showErr(node, text) { node.textContent = text || ''; node.hidden = !text; }

  var list = $('osp'), detail = $('osd');
  if (list) listPage(list);
  if (detail) detailPage(detail);

  /* =================== LIST + NEW TICKET =================== */
  function listPage(root) {
    var FIELDS = {
      url: { label: 'URL-ul paginii', ph: 'https://bilete.online/…', type: 'url', max: 2048 },
      invoice_series: { label: 'Seria decontului', ph: 'ex: AB', type: 'text', max: 32 },
      invoice_number: { label: 'Număr decont', ph: 'ex: 12345', type: 'text', max: 64 },
      module_name: { label: 'Modulul afectat', ph: 'ex: Rezervări, Sold, Produse', type: 'text', max: 100 },
      event_id: { label: 'Activitate', type: 'event' },
    };
    var ADJ = { open: ['activ', 'active'], resolved: ['rezolvat', 'rezolvate'], closed: ['închis', 'închise'] };
    var status = 'open', page = 1, more = false, items = [], total = 0, seq = 0;
    var taxonomy = null, events = null, cfSeq = 0, cfPending = false, sending = false, opener = null;
    var dialog = $('osp-new'), form = $('osp-form'), send = $('osp-send');

    function url(p) { return '/organizer/support/tickets?per_page=20&page=' + p + (status ? '&status=' + encodeURIComponent(status) : ''); }
    function state(which) {
      var box = $('osp-list');
      if (which === 'loading') {
        box.textContent = '';
        for (var i = 0; i < 3; i++) box.appendChild(el('li', { class: 'osp-sk', 'aria-hidden': 'true' }, el('span', { class: 'org-skel' })));
        $('osp-live').textContent = 'Se încarcă…';
      }
      box.hidden = which !== 'loading' && which !== 'list';
      $('osp-empty').hidden = which !== 'empty';
      $('osp-error').hidden = which !== 'error';
      if (which !== 'list') $('osp-more').hidden = true;
      if (which !== 'list') $('osp-list-p').textContent = '';
    }
    function gate(on, focus) {
      $('osp-gate').hidden = !on;
      $('osp-panel').hidden = !!on;
      if (on) $('osp-live').textContent = '';
      if (on && focus) $('osp-gate').focus();
    }
    function summary(n) {
      var a = ADJ[status];
      return F.count(n, 'tichet', 'tichete') + (a ? ' ' + (Math.round(n) === 1 ? a[0] : a[1]) : ' în total') + '.';
    }
    function load(append) {
      var my = ++seq, p = append ? page + 1 : 1, btn = $('osp-more');
      if (append) busyBtn(btn, true, 'Se încarcă…'); else state('loading');
      return O.api(url(p)).then(function (r) {
        if (my !== seq) return;
        var rows = (Array.isArray(r && r.data) ? r.data : []).filter(function (t) { return t && /^\d+$/.test(String(t.id)); });
        var meta = O.metaOf(r);
        items = append ? items.concat(rows) : rows;
        page = p;
        more = F.toNum(meta.last_page) > p;
        total = F.toNum(meta.total) || items.length;
        busyBtn(btn, false);
        gate(false);
        draw(append ? rows[0] : null);
      }, function (err) {
        busyBtn(btn, false);
        if (my !== seq || (err && err.status === 401)) return;
        if (err && err.status === 403) { gate(true); return; }
        if (append) { O.flash('Nu am putut încărca mai multe tichete. Încearcă din nou.', true); return; }
        state('error');
        $('osp-live').textContent = 'Nu am putut încărca tichetele.';
      });
    }
    function draw(firstNew) {
      var box = $('osp-list');
      box.textContent = '';
      if (!items.length) { state('empty'); $('osp-live').textContent = 'Nu ai niciun tichet aici.'; return; }
      state('list');
      items.forEach(function (t) { box.appendChild(row(t)); });
      $('osp-more').hidden = !more;
      $('osp-list-p').textContent = summary(total);
      $('osp-live').textContent = summary(total);
      if (firstNew) { var a = box.querySelector('[data-id="' + firstNew.id + '"]'); if (a) a.focus(); }
    }
    function row(t) {
      var st = statusOf(t.status), n = F.toNum(t.messages_count);
      var dept = (t.department && F.flat(t.department.name)) || '—';
      var meta = [F.count(n, 'mesaj', 'mesaje'), 'Ultima activitate: ' + (F.ago(t.last_activity_at) || '—'), 'Deschis pe ' + dateText(t.opened_at)];
      return el('li', { class: 'osp-item' + (t.status === 'awaiting_organizer' ? ' is-attn' : '') }, el('a', { class: 'osp-row', href: '/organizator/suport/' + t.id, 'data-id': t.id }, [
        el('span', { class: 'osp-row-t' }, [
          el('span', { class: 'osp-tags' }, [
            el('span', { class: 'osp-num', text: F.flat(t.ticket_number) || '#' + t.id }),
            el('span', { class: 'org-tag ' + st[1], text: st[0] }),
            el('span', { class: 'osp-dept', text: dept }),
          ]),
          el('b', { class: 'osp-subj', text: F.flat(t.subject) || 'Fără subiect' }),
          el('span', { class: 'osp-meta', text: meta.join(' · ') }),
        ]),
        icon('arrow-right', 'ic osp-go'),
      ]));
    }

    [].forEach.call(root.querySelectorAll('.osp-seg [data-status]'), function (b, i, all) {
      b.addEventListener('click', function () {
        if (b.getAttribute('aria-pressed') === 'true') return;
        [].forEach.call(all, function (x) { x.setAttribute('aria-pressed', String(x === b)); });
        status = b.getAttribute('data-status') || '';
        load();
      });
    });
    $('osp-retry').addEventListener('click', function () { load(); });
    $('osp-more').addEventListener('click', function () { if (this.getAttribute('aria-busy') !== 'true') load(true); });

    /* ---------- the new-ticket dialog ---------- */
    function findDept() { var v = $('osp-dept').value; return (taxonomy || []).filter(function (d) { return String(d.id) === v; })[0] || null; }
    function findPt() {
      var d = findDept(), v = $('osp-pt').value;
      return d && Array.isArray(d.problem_types) ? d.problem_types.filter(function (p) { return String(p.id) === v; })[0] || null : null;
    }
    function setText(node, text) { node.textContent = text || ''; node.hidden = !text; }
    function setStep(n) {
      $('osp-st-1').toggleAttribute('aria-current', false);
      $('osp-st-2').toggleAttribute('aria-current', false);
      $('osp-st-' + n).setAttribute('aria-current', 'step');
      $('osp-st-1').classList.toggle('is-done', n > 1);
    }
    function formErr(text) { showErr($('osp-form-err'), text); }
    function cfInputs() { return [].slice.call($('osp-cf').querySelectorAll('.osp-cf-in')); }
    function update() {
      var ok = !!findPt() && !cfPending && !$('osp-step2').hidden && !!$('osp-subject').value.trim() && !!$('osp-desc').value.trim()
        && cfInputs().every(function (i) { return !i.disabled && i.value.trim() !== ''; });
      if (send.getAttribute('aria-busy') !== 'true') send.disabled = sending || !ok;
      $('osp-desc-n').textContent = F.num($('osp-desc').value.length) + ' / 10.000';
    }
    function filesHelp() {
      $('osp-files-help').textContent = rulesText();
      $('osp-files').setAttribute('accept', acceptOf());
    }
    function fillDepts() {
      var sel = $('osp-dept');
      sel.textContent = '';
      sel.appendChild(el('option', { value: '', text: 'Alege un departament…' }));
      (taxonomy || []).forEach(function (d) { if (d && d.id != null) sel.appendChild(el('option', { value: d.id, text: F.flat(d.name) || 'Departament' })); });
    }
    function loadTaxonomy() {
      if (taxonomy) { fillDepts(); filesHelp(); return; }
      setText($('osp-dept-msg'), 'Se încarcă departamentele…');
      O.api('/organizer/support/departments').then(function (r) {
        var d = (r && r.data) || {};
        taxonomy = Array.isArray(d.departments) ? d.departments : [];
        setRules(d.attachment_rules);
        fillDepts();
        filesHelp();
        setText($('osp-dept-msg'), taxonomy.length ? '' : 'Nu am găsit niciun departament. Încearcă din nou mai târziu.');
      }, function (err) {
        setText($('osp-dept-msg'), '');
        if (err && err.status === 401) return;
        if (err && err.status === 403) { closeDialog(); gate(true, true); return; }
        formErr('Nu am putut încărca categoriile. Închide fereastra și încearcă din nou.');
      });
    }
    function loadEvents() {
      if (events) return Promise.resolve(events);
      return O.api('/organizer/events?per_page=50', { quiet: true }).then(function (r) {
        var d = r && r.data, rows = Array.isArray(d) ? d : (d && (d.events || d.data || d.items)) || [];
        events = rows.filter(function (e) { return e && /^\d+$/.test(String(e.id)); });
        return events;
      }, function () { return null; });
    }
    function field(key) {
      var m = FIELDS[key] || { label: key, type: 'text', max: 255 }, id = 'osp-cf-' + String(key).replace(/[^a-z0-9_]/gi, '');
      var label = el('label', { class: 'osp-l', for: id }, [m.label + ' ', el('span', { class: 'osp-req', 'aria-hidden': 'true', text: '*' })]);
      var help = el('p', { class: 'osp-fe', id: id + '-err', hidden: true });
      if (m.type === 'event') {
        return loadEvents().then(function (rows) {
          var sel = el('select', { class: 'osp-cf-in', id: id, 'data-meta': key, required: true, 'aria-describedby': id + '-err' }, [el('option', { value: '', text: 'Alege o activitate…' })]);
          (rows || []).forEach(function (ev) { sel.appendChild(el('option', { value: ev.id, text: F.flat(ev.name || ev.title) || 'Activitate #' + ev.id })); });
          if (!rows || !rows.length) {
            sel.disabled = true;
            setText(help, rows ? 'Acest tip de problemă cere o activitate, iar contul tău nu are niciuna. Alege alt tip de problemă.' : 'Nu am putut încărca activitățile. Alege din nou tipul problemei ca să reîncerci.');
          }
          return el('div', { class: 'osp-f' }, [label, el('span', { class: 'osp-select' }, [sel, icon('caret-down')]), help]);
        });
      }
      var inp = el('input', {
        class: 'osp-in osp-cf-in', id: id, type: m.type, 'data-meta': key, required: true, maxlength: m.max, autocomplete: 'off',
        spellcheck: m.type === 'url' ? 'false' : null, inputmode: m.type === 'url' ? 'url' : null, placeholder: m.ph || null, 'aria-describedby': id + '-err',
      });
      return Promise.resolve(el('div', { class: 'osp-f' }, [label, inp, help]));
    }
    function resetForm() {
      form.reset();
      cfSeq++;
      cfPending = false;
      $('osp-cf').textContent = '';
      $('osp-cf').hidden = true;
      $('osp-pt-f').hidden = true;
      $('osp-step2').hidden = true;
      setText($('osp-dept-desc'), '');
      setText($('osp-pt-desc'), '');
      setText($('osp-dept-msg'), '');
      $('osp-files-list').textContent = '';
      $('osp-files-list').hidden = true;
      formErr('');
      setStep(1);
      sending = false;
      dialog.removeAttribute('data-busy');
      busyBtn(send, false);
      update();
    }
    function openDialog(from) {
      opener = from || document.activeElement;
      resetForm();
      if (typeof dialog.showModal === 'function') dialog.showModal(); else dialog.setAttribute('open', '');
      $('osp-d-body').scrollTop = 0;
      $('osp-dept').focus();
      loadTaxonomy();
    }
    function closeDialog() {
      if (!dialog.open && !dialog.hasAttribute('open')) return;
      if (typeof dialog.close === 'function') dialog.close();
      else { dialog.removeAttribute('open'); dialog.dispatchEvent(new Event('close')); }
    }
    [].forEach.call(document.querySelectorAll('[data-osp-new]'), function (b) { b.addEventListener('click', function () { openDialog(b); }); });
    dialog.addEventListener('click', function (e) { if (!dialog.hasAttribute('data-busy') && e.target.closest('[data-close]')) closeDialog(); });
    dialog.addEventListener('cancel', function (e) { if (dialog.hasAttribute('data-busy')) e.preventDefault(); });
    dialog.addEventListener('close', function () {
      resetForm();
      var back = opener && document.contains(opener) && opener.getClientRects().length ? opener : null;
      opener = null;
      if (!$('osp-gate').hidden) return; // the gate took the focus
      if (back) back.focus();
    });

    $('osp-dept').addEventListener('change', function () {
      var dept = findDept(), sel = $('osp-pt');
      cfSeq++;
      cfPending = false;
      $('osp-cf').textContent = '';
      $('osp-cf').hidden = true;
      $('osp-step2').hidden = true;
      setText($('osp-pt-desc'), '');
      setStep(1);
      formErr('');
      if (!dept) { $('osp-pt-f').hidden = true; setText($('osp-dept-desc'), ''); update(); return; }
      setText($('osp-dept-desc'), F.flat(dept.description));
      sel.textContent = '';
      sel.appendChild(el('option', { value: '', text: 'Alege tipul…' }));
      var types = Array.isArray(dept.problem_types) ? dept.problem_types : [];
      types.forEach(function (p) { if (p && p.id != null) sel.appendChild(el('option', { value: p.id, text: F.flat(p.name) || 'Tip' })); });
      if (!types.length) setText($('osp-pt-desc'), 'Departamentul nu are încă tipuri de probleme. Alege alt departament.');
      $('osp-pt-f').hidden = false;
      update();
    });
    $('osp-pt').addEventListener('change', function () {
      var pt = findPt(), box = $('osp-cf'), my = ++cfSeq;
      box.textContent = '';
      formErr('');
      if (!pt) { box.hidden = true; cfPending = false; $('osp-step2').hidden = true; setText($('osp-pt-desc'), ''); setStep(1); update(); return; }
      setText($('osp-pt-desc'), F.flat(pt.description));
      var req = (Array.isArray(pt.required_fields) ? pt.required_fields : []).filter(function (k) { return typeof k === 'string' && k; });
      box.hidden = !req.length;
      cfPending = req.length > 0;
      if (req.length) {
        box.appendChild(el('p', { class: 'osp-help', text: 'Se pregătesc câmpurile…' }));
        Promise.all(req.map(field)).then(function (nodes) {
          if (my !== cfSeq) return;
          box.textContent = '';
          nodes.forEach(function (n) { box.appendChild(n); });
          cfPending = false;
          update();
        });
      }
      $('osp-step2').hidden = false;
      setStep(2);
      update();
    });
    form.addEventListener('input', function (e) {
      if (e.target && e.target.classList.contains('osp-cf-in')) { e.target.removeAttribute('aria-invalid'); var fe = $(e.target.id + '-err'); if (fe && !e.target.disabled) setText(fe, ''); }
      update();
    });
    form.addEventListener('change', update);
    $('osp-files').addEventListener('change', function () {
      var msg = checkFiles(this, $('osp-files-list'));
      formErr(msg);
    });

    form.addEventListener('submit', function (e) {
      e.preventDefault();
      if (sending || send.disabled) return;
      var pt = findPt(), bad = null, meta = {};
      cfInputs().forEach(function (i) {
        var v = i.value.trim(), fe = $(i.id + '-err');
        if (i.type === 'url' && !httpUrl(v)) {
          i.setAttribute('aria-invalid', 'true');
          if (fe) setText(fe, 'Scrie adresa completă a paginii, cu https:// în față.');
          bad = bad || i;
        }
        meta[i.getAttribute('data-meta')] = v;
      });
      if (bad) { bad.focus(); return; }
      var ctx = {
        source_url: window.location.href,
        screen_resolution: screen.width + 'x' + screen.height,
        viewport: window.innerWidth + 'x' + window.innerHeight,
        user_agent: navigator.userAgent,
      };
      var files = [].slice.call($('osp-files').files || []);
      sending = true;
      formErr('');
      busyBtn(send, true, 'Se trimite…');
      dialog.setAttribute('data-busy', '');
      var lib = typeof BileteOnlineAPI !== 'undefined' && BileteOnlineAPI.organizer;
      var go = lib && lib.createSupportTicket
        ? lib.createSupportTicket({ support_problem_type_id: pt.id, subject: $('osp-subject').value.trim(), description: $('osp-desc').value.trim(), meta: meta, context: ctx }, files)
        : Promise.reject({ status: 0 });
      go.then(function (r) {
        var t = r && r.data && r.data.ticket;
        if (t && /^\d+$/.test(String(t.id))) { window.location.href = '/organizator/suport/' + t.id + '?trimis=1'; return; }
        dialog.removeAttribute('data-busy');
        closeDialog();
        O.flash('Tichet trimis. Îți răspundem cât de curând.');
        refreshBadge();
        load();
      }, function (err) {
        sending = false;
        dialog.removeAttribute('data-busy');
        busyBtn(send, false);
        update();
        if (err && err.status === 403) { closeDialog(); gate(true, true); return; }
        if (err && err.status === 401) { sessionCheck(); formErr('Sesiunea a expirat. Autentifică-te din nou, apoi trimite tichetul.'); return; }
        var errs = err && (err.errors || (err.data && err.data.errors));
        if (errs && typeof errs === 'object') {
          Object.keys(errs).forEach(function (k) {
            var mk = /^meta\.(.+)$/.exec(k), inp = mk ? root.querySelector('[data-meta="' + mk[1] + '"]') : null, fe = inp && $(inp.id + '-err');
            if (inp && fe) { inp.setAttribute('aria-invalid', 'true'); setText(fe, Array.isArray(errs[k]) ? String(errs[k][0]) : String(errs[k])); }
          });
        }
        formErr(errText(err, 'A apărut o eroare. Reîncearcă.'));
      });
    });

    O.ready.then(function (ok) { if (ok) load(); });
  }

  /* =================== ONE TICKET =================== */
  function detailPage(root) {
    var id = root.getAttribute('data-id');
    if (!/^\d+$/.test(String(id))) return;
    var META = { url: 'URL pagină', invoice_series: 'Seria decont', invoice_number: 'Număr decont', event_id: 'Activitate', module_name: 'Modul' };
    var shown = false, sending = false, closed = false;
    var confirmD = $('osd-confirm'), replyBtn = $('osd-send');

    function state(which) {
      $('osd-loading').hidden = which !== 'loading';
      $('osd-error').hidden = which !== 'error';
      $('osd-content').hidden = which !== 'content';
    }
    function fail(err) {
      var h = 'Tichet inexistent', p = 'Verifică linkul sau întoarce-te la lista de tichete.', retry = false;
      if (err && err.status === 403) { h = 'Acces restricționat'; p = 'Sistemul de tichete este în testare și nu este încă activat pentru contul tău.'; }
      else if (err && err.status === 404) { p = 'Tichetul nu există sau nu îți aparține.'; }
      else if (err && err.status !== 'missing') { h = 'Nu am putut încărca tichetul'; p = 'Verifică conexiunea și încearcă din nou.'; retry = true; }
      $('osd-err-h').textContent = h;
      $('osd-err-p').textContent = p;
      $('osd-retry').hidden = !retry;
      state('error');
    }
    function load(after) {
      return O.api('/organizer/support/tickets/' + id).then(function (r) {
        var d = (r && r.data) || {}, t = d.ticket;
        if (!t || typeof t !== 'object') throw { status: 'missing' };
        shown = true;
        state('content');
        render(t, Array.isArray(d.messages) ? d.messages : []);
        if (after) after();
      }).catch(function (err) {
        if (err && err.status === 401) return;
        if (shown && !(err && (err.status === 403 || err.status === 404))) { O.flash('Nu am putut reîncărca tichetul. Reîncarcă pagina.', true); return; }
        fail(err);
      });
    }
    function render(t, messages) {
      var num = F.flat(t.ticket_number) || '#' + t.id, st = statusOf(t.status);
      closed = typeof t.is_closed === 'boolean' ? t.is_closed : (t.status === 'resolved' || t.status === 'closed');
      document.title = num + ' — ' + (F.flat(t.subject) || 'Tichet suport');
      $('osd-num').textContent = num;
      $('osd-crumb').textContent = num;
      var tag = $('osd-status');
      tag.className = 'org-tag ' + st[1];
      tag.textContent = st[0];
      $('osd-dept').textContent = (t.department && F.flat(t.department.name)) || '—';
      var pt = t.problem_type && F.flat(t.problem_type.name);
      $('osd-pt').textContent = pt || '';
      $('osd-pt').hidden = !pt;
      $('osd-subject').textContent = F.flat(t.subject) || 'Fără subiect';
      var opened = $('osd-opened');
      opened.textContent = dateTimeText(t.opened_at);
      if (t.opened_at) opened.setAttribute('datetime', String(t.opened_at));
      renderMeta(t.meta && typeof t.meta === 'object' ? t.meta : {});
      $('osd-close').hidden = closed;
      $('osd-reopen').hidden = !closed;
      $('osd-reply-card').hidden = closed;
      $('osd-closed').hidden = !closed;
      renderThread(messages);
    }
    function renderMeta(meta) {
      var dl = $('osd-meta');
      dl.textContent = '';
      Object.keys(META).forEach(function (k) {
        var v = meta[k];
        if (v == null || v === '' || typeof v === 'object') return;
        var dd, s = String(v);
        if (k === 'url' && httpUrl(s)) dd =el('dd', null, el('a', { href: httpUrl(s), target: '_blank', rel: 'noopener noreferrer', text: s }));
        else if (k === 'event_id') dd = el('dd', { text: 'Activitate #' + s });
        else dd = el('dd', { text: s });
        dl.appendChild(el('div', null, [el('dt', { text: META[k] }), dd]));
      });
      dl.hidden = !dl.children.length;
    }
    function renderThread(messages) {
      var box = $('osd-thread');
      box.textContent = '';
      var rows = messages.filter(function (m) { return m && typeof m === 'object'; });
      if (!rows.length) { box.appendChild(el('li', { class: 'osd-none', text: 'Încă nu a fost trimis niciun mesaj.' })); return; }
      rows.forEach(function (m) { box.appendChild(message(m)); });
    }
    function message(m) {
      var when = dateTimeText(m.created_at), time = el('time', { datetime: m.created_at || null, text: when });
      if (m.event_type) {
        var by = F.flat(m.author_name) || (m.author_type === 'staff' ? 'Echipa bilete.online' : 'Tu');
        return el('li', { class: 'osd-ev' }, el('span', { class: 'osd-ev-t' }, [icon('info'), el('span', null, [el('b', { text: F.flat(m.body) }), ' de ' + by + ' · ', time])]));
      }
      var mine = m.author_type === 'organizer' || m.author_type === 'customer';
      var who = F.flat(m.author_name) || (mine ? 'Tu' : 'Echipa bilete.online');
      var atts = (Array.isArray(m.attachments) ? m.attachments : []).map(function (a) {
        var href = a && httpUrl(a.url), name = (a && F.flat(a.original_name)) || 'Fișier';
        return href
          ? el('a', { class: 'osd-att', href: href, target: '_blank', rel: 'noopener noreferrer' }, [icon('file-text'), el('span', { text: name })])
          : el('span', { class: 'osd-att' }, [icon('file-text'), el('span', { text: name })]);
      });
      return el('li', { class: 'osd-msg ' + (mine ? 'is-mine' : 'is-staff') }, [
        el('span', { class: 'osd-av', 'aria-hidden': 'true', text: who.trim().charAt(0).toUpperCase() || '?' }),
        el('div', { class: 'osd-bub' }, [
          el('p', { class: 'osd-who', text: who }),
          el('p', { class: 'osd-body', text: F.flat(m.body) }),
          atts.length ? el('div', { class: 'osd-atts' }, atts) : null,
          el('p', { class: 'osd-when' }, time),
        ]),
      ]);
    }

    /* ---------- reply ---------- */
    function replyErr(text) { showErr($('osd-reply-err'), text); }
    function counter() { $('osd-body-n').textContent = F.num($('osd-body').value.length) + ' / 10.000'; }
    $('osd-body').addEventListener('input', function () { counter(); if (this.value.trim()) replyErr(''); });
    $('osd-files').addEventListener('change', function () { replyErr(checkFiles(this, $('osd-files-list'))); });
    $('osd-reply').addEventListener('submit', function (e) {
      e.preventDefault();
      if (sending) return;
      var body = $('osd-body').value.trim();
      if (!body) { replyErr('Scrie mesajul înainte să-l trimiți.'); $('osd-body').focus(); return; }
      var files = [].slice.call($('osd-files').files || []);
      var lib = typeof BileteOnlineAPI !== 'undefined' && BileteOnlineAPI.organizer;
      sending = true;
      replyErr('');
      busyBtn(replyBtn, true, 'Se trimite…');
      (lib && lib.replySupportTicket ? lib.replySupportTicket(id, body, files) : Promise.reject({ status: 0 })).then(function () {
        sending = false;
        busyBtn(replyBtn, false);
        $('osd-reply').reset();
        $('osd-files-list').textContent = '';
        $('osd-files-list').hidden = true;
        counter();
        O.flash('Mesaj trimis.');
        load(function () { var last = $('osd-thread').lastElementChild; if (last) last.scrollIntoView({ block: 'nearest' }); });
      }, function (err) {
        sending = false;
        busyBtn(replyBtn, false);
        if (err && err.status === 401) { sessionCheck(); replyErr('Sesiunea a expirat. Autentifică-te din nou, apoi trimite mesajul.'); return; }
        if (err && err.status === 409) { replyErr('Tichetul este închis. Redeschide-l ca să poți răspunde.'); load(); return; }
        if (err && err.status === 403) { fail(err); return; }
        replyErr(errText(err, 'Nu am putut trimite mesajul.'));
      });
    });

    /* ---------- close / reopen ---------- */
    /** POST close / reopen; `settle` runs first either way (it closes the confirm dialog, so the focus can move after). */
    function act(verb, done, failText, focusId, settle) {
      return O.api('/organizer/support/tickets/' + id + '/' + verb, { method: 'POST', body: {} }).then(function () {
        settle();
        O.flash(done);
        refreshBadge();
        return load(function () { var f = $(focusId); if (f && !f.hidden) f.focus(); });
      }, function (err) {
        settle();
        if (err && err.status === 401) return;
        O.flash(errText(err, failText), true);
        if (err && err.status === 409) { refreshBadge(); return load(); }
      });
    }
    function openConfirm() {
      if (typeof confirmD.showModal === 'function') confirmD.showModal(); else confirmD.setAttribute('open', '');
      confirmD.querySelector('[data-close]').focus();
    }
    function closeConfirm() {
      if (!confirmD.open && !confirmD.hasAttribute('open')) return;
      if (typeof confirmD.close === 'function') confirmD.close();
      else { confirmD.removeAttribute('open'); confirmD.dispatchEvent(new Event('close')); }
    }
    $('osd-close').addEventListener('click', openConfirm);
    confirmD.addEventListener('click', function (e) { if (!confirmD.hasAttribute('data-busy') && e.target.closest('[data-close]')) closeConfirm(); });
    confirmD.addEventListener('cancel', function (e) { if (confirmD.hasAttribute('data-busy')) e.preventDefault(); });
    confirmD.addEventListener('close', function () { var b = $('osd-close'); if (!b.hidden && document.activeElement === document.body) b.focus(); });
    $('osd-confirm-go').addEventListener('click', function () {
      var btn = this;
      if (btn.getAttribute('aria-busy') === 'true') return;
      busyBtn(btn, true, 'Se marchează…');
      confirmD.setAttribute('data-busy', '');
      act('close', 'Tichet marcat ca rezolvat.', 'Nu am putut închide tichetul.', 'osd-reopen', function () {
        confirmD.removeAttribute('data-busy');
        busyBtn(btn, false);
        closeConfirm();
      });
    });
    $('osd-reopen').addEventListener('click', function () {
      var btn = this;
      if (btn.getAttribute('aria-busy') === 'true') return;
      busyBtn(btn, true, 'Se redeschide…');
      act('reopen', 'Tichet redeschis.', 'Nu am putut redeschide tichetul.', 'osd-body', function () { busyBtn(btn, false); });
    });
    $('osd-retry').addEventListener('click', function () { state('loading'); load(); });

    O.ready.then(function (ok) {
      if (!ok) return;
      try {
        var u = new URL(window.location.href);
        if (u.searchParams.get('trimis') === '1') {
          O.flash('Tichet trimis. Îți răspundem cât de curând.');
          u.searchParams.delete('trimis');
          history.replaceState(null, '', u.pathname + u.search + u.hash);
        }
      } catch (e) {}
      load();
      loadRules().then(function () { $('osd-files-help').textContent = rulesText(); $('osd-files').setAttribute('accept', acceptOf()); }, function () {});
    });
  }
})();
