/* bilete.online v2: organizer team (/organizator/echipa). The owner and the members from /organizer/team; adding a member
   (core creates the account at once and can e-mail the credentials), editing the role, permissions and activities, a new
   password for a member, activating a pending invitation with a password, resending invitations, removing. Activities
   for the picker come from /organizer/events when a dialog needs them. Runs inside the organizer shell (window.BO_ORG);
   text from the API is always written as text. */
(function () {
  'use strict';
  var O = window.BO_ORG, root = document.getElementById('ot');
  if (!O || !root) return;
  var F = O.fmt, el = O.el, icon = O.icon;
  var $ = function (id) { return document.getElementById(id); };
  function qsa(sel, ctx) { return [].slice.call((ctx || document).querySelectorAll(sel)); }

  var ROLES = { owner: 'Proprietar', admin: 'Administrator', manager: 'Manager', staff: 'Staff' };
  var ROLE_HELP = { admin: 'Are toate permisiunile și vede toate activitățile.', manager: 'Lucrează doar cu ce îi permiți mai jos, la activitățile alese.', staff: 'Face check-in la intrare, din aplicația mobilă.' };
  var PERMS = { events: 'Activități', orders: 'Comenzi', reports: 'Rapoarte', team: 'Echipă', checkin: 'Check-in' };
  var ALL_PERMS = ['events', 'orders', 'reports', 'team', 'checkin'];
  var DEFAULT_PERMS = { staff: ['checkin'], manager: ['events', 'orders', 'reports', 'checkin'], admin: ALL_PERMS };
  var EMAIL = /^[^\s@]+@[^\s@]+\.[^\s@]{2,}$/;

  var members = null, events = null, eventsReq = null, edit = null, passTarget = null, delTarget = null, picked = {};
  var opener = null, openerKey = null, fallbackFocus = null, permsTouched = false;

  /* =================== HELPERS =================== */
  function val(id) { var n = $(id); return n ? String(n.value || '').trim() : ''; }
  function norm(s) { return String(s == null ? '' : s).normalize('NFD').replace(/[̀-ͯ]/g, '').toLowerCase(); }
  function naiveDay(v) { var m = /^(\d{4}-\d{2}-\d{2})/.exec(String(v || '')); return m ? m[1] : ''; }
  function dayLabel(v) { var d = F.dateOf(v); return d ? F.date(d, { day: 'numeric', month: 'long', year: 'numeric' }) : ''; }
  function tag(text, cls) { return el('span', { class: 'org-tag ' + (cls || ''), text: text }); }
  function initials(name) {
    var w = String(name || '').split(/[\s@._-]+/).map(function (x) { return x.replace(/[^\p{L}\p{N}]/gu, ''); }).filter(Boolean);
    return ((w.length > 1 ? w[0].charAt(0) + w[1].charAt(0) : (w[0] || '').slice(0, 2)) || '?').toUpperCase();
  }
  function memberName(m) { return F.flat(m.name).trim() || F.flat(m.email).trim() || 'Membru'; }
  function busyBtn(btn, on, text) {
    var l = btn.querySelector('[data-label]');
    if (on) { btn.setAttribute('aria-busy', 'true'); if (l) { btn.setAttribute('data-idle', l.textContent); l.textContent = text; } }
    else { btn.removeAttribute('aria-busy'); if (l && btn.hasAttribute('data-idle')) l.textContent = btn.getAttribute('data-idle'); btn.removeAttribute('data-idle'); }
  }
  function isBusy(btn) { return btn.getAttribute('aria-busy') === 'true'; }
  function fieldErr(id, msg) {
    var f = $(id), e = $(id + '-err');
    if (e) { e.textContent = msg || ''; e.hidden = !msg; }
    if (f && /^(INPUT|SELECT|TEXTAREA)$/.test(f.tagName)) { if (msg) f.setAttribute('aria-invalid', 'true'); else f.removeAttribute('aria-invalid'); }
  }
  function formErr(id, msg) { var b = $(id); if (b) { b.textContent = msg || ''; b.hidden = !msg; } }
  function errMessage(err) { return String((err && (err.message || (err.data && err.data.message))) || ''); }
  function saveError(err) {
    var s = err && err.status;
    if (s === 422) return 'Unele câmpuri nu sunt completate corect. Verifică-le și încearcă din nou.';
    if (s === 429) return 'Prea multe încercări într-un timp scurt. Așteaptă un minut și încearcă din nou.';
    if (s === 403) return 'Contul tău nu are voie să schimbe echipa.';
    if (s === 0 || s == null) return 'Nu am putut ajunge la server. Verifică conexiunea și încearcă din nou.';
    return 'Nu am putut salva. Încearcă din nou.';
  }
  function markServer(err, map) {
    var errors = (err && err.errors) || (err && err.data && err.data.errors) || null, first = null;
    if (!errors || typeof errors !== 'object') return null;
    Object.keys(errors).forEach(function (k) {
      var key = k.replace(/\.\d+$/, '');
      if (map[key] && $(map[key])) { fieldErr(map[key], 'Verifică această valoare.'); if (!first) first = map[key]; }
    });
    if (first && $(first).focus) $(first).focus();
    return first;
  }
  function copyText(text) {
    if (navigator.clipboard && window.isSecureContext) return navigator.clipboard.writeText(text);
    return new Promise(function (resolve, reject) {
      var ta = el('textarea', { class: 'sr', readonly: true });
      ta.value = text;
      document.body.appendChild(ta);
      ta.select();
      var ok = false;
      try { ok = document.execCommand('copy'); } catch (e) { ok = false; }
      ta.remove();
      if (ok) resolve(); else reject(new Error('copy'));
    });
  }
  /** A 14-character password without look-alike characters, with lower and upper case, a digit and a symbol. */
  function generatePassword() {
    var sets = ['abcdefghjkmnpqrstuvwxyz', 'ABCDEFGHJKLMNPQRSTUVWXYZ', '23456789', '!@#$%&*?'], all = sets.join(''), out = [], rnd = new Uint32Array(28);
    (window.crypto || window.msCrypto).getRandomValues(rnd);
    sets.forEach(function (s, i) { out.push(s.charAt(rnd[i] % s.length)); });
    for (var i = out.length; i < 14; i++) out.push(all.charAt(rnd[i] % all.length));
    for (var j = out.length - 1; j > 0; j--) { var k = rnd[14 + (j % 14)] % (j + 1), t = out[j]; out[j] = out[k]; out[k] = t; }
    return out.join('');
  }
  function focusKey(box) { var a = document.activeElement; return a && a !== document.body && box.contains(a) ? a.getAttribute('data-focus') || '' : null; }
  function keepKey(box, wanted) { var k = focusKey(box), a = document.activeElement; return k === null && wanted && (!a || a === document.body) ? wanted : k; }
  function restoreFocus(box, key, fallback) {
    if (key === null) return;
    var same = key ? box.querySelector('[data-focus="' + key + '"]') : null;
    if (same) same.focus();
    else if (fallback && !fallback.disabled) fallback.focus();
  }
  function pill(ic, text, attrs) { return el('button', Object.assign({ class: 'ot-pill', type: 'button' }, attrs || {}), [icon(ic), text]); }

  /* =================== LOAD + LIST =================== */
  function load(wanted) {
    $('ot-load-err').hidden = true;
    return O.api('/organizer/team').then(function (r) {
      var d = r && r.data;
      members = (d && Array.isArray(d.members) ? d.members : []).filter(function (m) { return m && m.id != null; });
      var box = $('ot-list'), keep = keepKey(box, wanted);
      $('ot-panel').hidden = false;
      render();
      restoreFocus(box, keep, $('ot-add'));
      $('ot-add').disabled = false;
      // the names behind "Doar …" in the list, only when someone is limited to some activities
      if (!events && members.some(function (m) { return m.role !== 'owner' && m.role !== 'admin' && Array.isArray(m.event_ids) && m.event_ids.length; })) loadEvents().catch(function () {});
    }).catch(function (err) {
      if (err && err.status === 401) return;
      members = null;
      $('ot-load-err').hidden = false;
      $('ot-panel').hidden = true;
      ['total', 'active', 'pending', 'admins'].forEach(function (k) { $('ot-s-' + k).textContent = '—'; });
    });
  }
  $('ot-retry').addEventListener('click', function () { load(); });
  function render() {
    var total = members.length, active = members.filter(function (m) { return m.status === 'active'; }).length, pending = members.filter(function (m) { return m.status === 'pending'; }).length, admins = members.filter(function (m) { return m.role === 'owner' || m.role === 'admin'; }).length;
    $('ot-s-total').textContent = F.num(total);
    $('ot-s-active').textContent = F.num(active);
    $('ot-s-pending').textContent = F.num(pending);
    $('ot-s-admins').textContent = F.num(admins);
    $('ot-pending').hidden = !pending;
    $('ot-pending-t').textContent = F.count(pending, 'invitație în așteptare', 'invitații în așteptare');
    $('ot-list-p').textContent = F.count(total, 'persoană', 'persoane') + ' cu acces la cont, dintre care ' + F.count(active, 'activă', 'active') + '.';
    drawList();
  }
  function scopeText(m) {
    if (m.role === 'owner' || m.role === 'admin') return 'Toate activitățile';
    var ids = Array.isArray(m.event_ids) ? m.event_ids : [];
    if (!ids.length) return 'Toate activitățile';
    var names = events ? ids.map(function (id) { var e = events.filter(function (x) { return String(x.id) === String(id); })[0]; return e ? F.flat(e.name || e.title) : ''; }).filter(Boolean) : [];
    if (!names.length) return 'Doar ' + F.count(ids.length, 'activitate aleasă', 'activități alese');
    return 'Doar ' + names.slice(0, 2).join(', ') + (ids.length > 2 ? ' și încă ' + (ids.length - 2) : '');
  }
  function drawList() {
    var box = $('ot-list'), q = norm(val('ot-q')), shown = members.filter(function (m) { return !q || norm(memberName(m) + ' ' + F.flat(m.email)).indexOf(q) > -1; });
    box.textContent = '';
    shown.forEach(function (m) { box.appendChild(row(m)); });
    if (q && !shown.length) box.appendChild(el('li', { class: 'ot-empty-p', text: 'Niciun membru nu se potrivește căutării.' }));
    if (!q && members.length <= 1) {
      var cta = el('button', { class: 'btn btn-primary ot-sm', type: 'button', 'data-focus': 'member-first' }, [icon('user-plus'), 'Adaugă primul coleg']);
      cta.addEventListener('click', function () { openMember(null, cta); });
      box.appendChild(el('li', { class: 'ot-empty' }, [el('b', { text: 'Niciun coleg încă' }), el('p', { text: 'Adaugă colegii care vând, verifică biletele la intrare sau gestionează activitățile.' }), cta]));
    }
  }
  function row(m) {
    var role = ROLES[m.role] ? m.role : 'staff', owner = m.role === 'owner', pending = m.status === 'pending', inactive = m.status === 'inactive', id = String(m.id), name = memberName(m);
    var tags = [tag(ROLES[m.role] || F.flat(m.role) || 'Membru', owner ? 'is-ok' : role === 'admin' ? 'is-info' : 'is-muted')];
    if (pending) tags.push(tag('Invitație trimisă', 'is-wait'));
    else if (inactive) tags.push(tag('Inactiv', 'is-bad'));
    else tags.push(tag('Activ', 'is-ok'));
    var perms = owner || role === 'admin' ? ['Acces complet'] : (Array.isArray(m.permissions) ? m.permissions : []).map(function (p) { return PERMS[p] || p; });
    var meta = [scopeText(m)];
    if (pending && m.invite_sent_at) meta.push('invitat ' + F.ago(m.invite_sent_at));
    else if (!owner && m.accepted_at) meta.push('în echipă din ' + dayLabel(m.accepted_at));
    var acts = [];
    if (owner) acts.push(el('span', { class: 'ot-you', text: m.is_current_user ? 'Proprietarul contului (tu)' : 'Proprietarul contului' }));
    else if (pending) {
      var resend = pill('arrow-counter-clockwise', 'Retrimite invitația', { 'data-focus': 'member-resend-' + id, 'aria-label': 'Retrimite invitația către ' + name });
      resend.addEventListener('click', function () { resendOne(m, resend); });
      var activate = pill('lock-simple', 'Activează', { 'data-focus': 'member-activate-' + id, 'aria-label': 'Activează contul lui ' + name + ' cu o parolă' });
      activate.addEventListener('click', function () { openPass(m, 'activate', activate); });
      var cancel = pill('x', 'Anulează invitația', { class: 'ot-pill is-danger', 'data-focus': 'member-del-' + id, 'aria-label': 'Anulează invitația lui ' + name });
      cancel.addEventListener('click', function () { openDelete(m, cancel); });
      acts.push(resend, activate, cancel);
    } else {
      var editBtn = pill('pencil-simple', 'Accesul', { 'data-focus': 'member-edit-' + id, 'aria-label': 'Modifică accesul lui ' + name });
      editBtn.addEventListener('click', function () { openMember(m, editBtn); });
      var passBtn = pill('lock-simple', 'Parolă nouă', { 'data-focus': 'member-pass-' + id, 'aria-label': 'Setează o parolă nouă pentru ' + name });
      passBtn.addEventListener('click', function () { openPass(m, 'reset', passBtn); });
      var del = pill('trash', 'Elimină', { class: 'ot-pill is-danger', 'data-focus': 'member-del-' + id, 'aria-label': 'Elimină pe ' + name + ' din echipă' });
      del.addEventListener('click', function () { openDelete(m, del); });
      acts.push(editBtn, passBtn, del);
    }
    return el('li', { class: 'ot-m' + (pending ? ' is-pending' : '') }, [
      el('span', { class: 'ot-av is-' + (owner ? 'owner' : role), 'aria-hidden': 'true', text: initials(F.flat(m.name) || F.flat(m.email)) }),
      el('div', { class: 'ot-m-t' }, [
        el('div', { class: 'ot-m-top' }, [el('b', { text: name })].concat(tags)),
        F.flat(m.name).trim() ? el('span', { class: 'ot-m-mail', text: F.flat(m.email) }) : null,
        el('ul', { class: 'ot-m-perms', 'aria-label': 'Permisiuni' }, perms.length ? perms.map(function (p) { return el('li', { text: p }); }) : el('li', { text: 'Fără permisiuni' })),
        el('span', { class: 'ot-m-meta', text: meta.join(' · ') }),
      ]),
      el('div', { class: 'ot-m-act' }, acts),
    ]);
  }
  $('ot-q').addEventListener('input', function () { if (members) drawList(); });

  /* =================== EVENTS PICKER =================== */
  function isOver(ev) {
    if (ev.is_cancelled || ev.is_past || ev.is_ended) return true;
    var end = naiveDay(ev.ends_at || ev.starts_at);
    return !!end && end < F.ymd();
  }
  function loadEvents() {
    if (eventsReq) return eventsReq;
    var got = [];
    function page(n) {
      return O.api('/organizer/events?per_page=50&page=' + n, { quiet: true }).then(function (r) {
        got = got.concat(Array.isArray(r && r.data) ? r.data : []);
        if (F.toNum(O.metaOf(r).last_page) > n && n < 20) return page(n + 1);
      });
    }
    eventsReq = page(1).then(function () {
      events = got.filter(function (e) { return e && /^\d+$/.test(String(e.id)); }).sort(function (a, b) {
        var ao = isOver(a), bo = isOver(b), ad = String(a.starts_at || ''), bd = String(b.starts_at || '');
        if (ao !== bo) return ao ? 1 : -1;
        return ao ? (ad > bd ? -1 : ad < bd ? 1 : 0) : (ad < bd ? -1 : ad > bd ? 1 : 0);
      });
      if (members) { var box = $('ot-list'), k = focusKey(box); drawList(); restoreFocus(box, k, $('ot-add')); }
      return events;
    }, function (err) { eventsReq = null; throw err; });
    return eventsReq;
  }
  function pickedIds() { return Object.keys(picked).filter(function (k) { return picked[k]; }); }
  function renderPicks() {
    var box = $('ot-picks'), q = norm(val('ot-pq')), n = pickedIds().length;
    box.textContent = '';
    $('ot-psearch').hidden = !(events && events.length > 8);
    if (!events.length) { box.appendChild(el('li', { class: 'ot-picks-msg', text: 'Nu ai încă activități.' })); $('ot-picks-n').textContent = ''; return; }
    var shown = events.filter(function (e) { return !q || norm(F.flat(e.name || e.title) + ' ' + F.flat(e.venue_name) + ' ' + F.flat(e.venue_city)).indexOf(q) > -1; });
    if (!shown.length) box.appendChild(el('li', { class: 'ot-picks-msg', text: 'Nicio activitate nu se potrivește căutării.' }));
    shown.forEach(function (e) {
      var id = String(e.id), day = naiveDay(e.starts_at), cb = el('input', { type: 'checkbox', value: id });
      cb.checked = !!picked[id];
      cb.addEventListener('change', function () {
        picked[id] = cb.checked;
        fieldErr('ot-picks', '');
        $('ot-picks-n').textContent = pickedCount();
      });
      box.appendChild(el('li', null, el('label', { class: 'ot-pick' }, [cb, el('span', null, [el('b', { text: F.flat(e.name || e.title) || 'Activitatea #' + id }), el('small', { text: [day ? dayLabel(day) : '', F.flat(e.venue_name), isOver(e) ? 'încheiată' : ''].filter(Boolean).join(' · ') })])])));
    });
    $('ot-picks-n').textContent = pickedCount();
    void n;
  }
  function pickedCount() { var n = pickedIds().length; return n ? F.count(n, 'activitate aleasă', 'activități alese') : 'Alege activitățile pe care le vede.'; }
  $('ot-pq').addEventListener('input', function () { if (events) renderPicks(); });
  function scopeValue() { var r = root.querySelector('input[name="ot-scope"]:checked'); return r ? r.value : 'all'; }
  function syncScope() {
    var some = scopeValue() === 'some' && $('ot-role').value !== 'admin';
    $('ot-some').hidden = !some;
    if (!some) return;
    var box = $('ot-picks');
    if (!events) { box.textContent = ''; box.appendChild(el('li', { class: 'ot-picks-msg', text: 'Se încarcă activitățile…' })); }
    loadEvents().then(renderPicks, function () {
      box.textContent = '';
      var retry = el('button', { class: 'ot-linkbtn', type: 'button', text: 'Reîncearcă' });
      retry.addEventListener('click', syncScope);
      box.appendChild(el('li', { class: 'ot-picks-msg' }, ['Nu am putut încărca activitățile. ', retry]));
    });
  }
  qsa('input[name="ot-scope"]', root).forEach(function (r) { r.addEventListener('change', syncScope); });

  /* =================== ADD / EDIT =================== */
  function setPerms(list) { qsa('[data-perm]', root).forEach(function (c) { c.checked = list.indexOf(c.value) > -1; }); }
  function readPerms() { return qsa('[data-perm]', root).filter(function (c) { return c.checked; }).map(function (c) { return c.value; }); }
  qsa('[data-perm]', root).forEach(function (c) { c.addEventListener('change', function () { permsTouched = true; fieldErr('ot-perms', ''); }); });
  function syncRole() {
    var role = $('ot-role').value, admin = role === 'admin';
    $('ot-role-help').textContent = ROLE_HELP[role] || '';
    $('ot-perms').disabled = admin;
    if (admin) setPerms(ALL_PERMS);
    else if (!edit && !permsTouched) setPerms(DEFAULT_PERMS[role] || []);
    $('ot-scope').hidden = admin;
    syncScope();
  }
  $('ot-role').addEventListener('change', function () {
    if (edit && $('ot-role').value !== 'admin' && edit.role === 'admin' && !permsTouched) setPerms(DEFAULT_PERMS[$('ot-role').value] || []);
    syncRole();
  });
  function openMember(m, from) {
    if (!members) return;
    edit = m || null;
    permsTouched = false;
    $('ot-member-h').textContent = m ? 'Accesul lui ' + memberName(m) : 'Membru nou';
    $('ot-member-p').textContent = m ? F.flat(m.email) : 'Contul se creează imediat și e activ. Membrul se autentifică cu emailul și parola de aici.';
    $('ot-ident').hidden = !!m;
    $('ot-welcome-f').hidden = !!m;
    ['ot-name', 'ot-email', 'ot-pass', 'ot-pq'].forEach(function (id) { $(id).value = ''; });
    $('ot-welcome').checked = true;
    $('ot-role').value = m && ROLES[m.role] && m.role !== 'owner' ? m.role : 'staff';
    setPerms(m ? (m.role === 'admin' ? ALL_PERMS : Array.isArray(m.permissions) ? m.permissions : []) : DEFAULT_PERMS.staff);
    picked = {};
    var ids = m && Array.isArray(m.event_ids) ? m.event_ids : [];
    ids.forEach(function (id) { picked[String(id)] = true; });
    qsa('input[name="ot-scope"]', root).forEach(function (r) { r.checked = r.value === (ids.length ? 'some' : 'all'); });
    ['ot-name', 'ot-email', 'ot-pass', 'ot-perms', 'ot-picks'].forEach(function (id) { fieldErr(id, ''); });
    formErr('ot-member-err', '');
    $('ot-member-go').querySelector('[data-label]').textContent = m ? 'Salvează accesul' : 'Adaugă membrul';
    resetEye('ot-pass');
    syncRole();
    fallbackFocus = $('ot-add');
    openDialog($('ot-member-d'), from);
    (m ? $('ot-role') : $('ot-email')).focus();
  }
  $('ot-add').addEventListener('click', function () { openMember(null, this); });
  $('ot-member-form').addEventListener('submit', function (ev) {
    ev.preventDefault();
    var d = $('ot-member-d'), btn = $('ot-member-go'), m = edit, bad = null;
    if (isBusy(btn)) return;
    function need(id, msg) { fieldErr(id, msg); if (msg && !bad) bad = id; }
    var role = $('ot-role').value, perms = role === 'admin' ? ALL_PERMS.slice() : readPerms(), some = role !== 'admin' && scopeValue() === 'some', ids = some ? pickedIds().map(Number) : [];
    var email = val('ot-email').toLowerCase(), pw = $('ot-pass').value;
    if (!m) {
      need('ot-email', !email ? 'Scrie emailul colegului.' : !EMAIL.test(email) ? 'Scrie o adresă de email validă, de exemplu ion@firma.ro.' : '');
      need('ot-pass', !pw ? 'Scrie o parolă sau generează una.' : pw.length < 8 ? 'Parola trebuie să aibă cel puțin 8 caractere.' : pw.length > 100 ? 'Parola poate avea cel mult 100 de caractere.' : '');
    }
    need('ot-perms', role !== 'admin' && !perms.length ? 'Alege cel puțin o permisiune.' : '');
    need('ot-picks', some && !ids.length ? 'Alege cel puțin o activitate sau lasă „Toate activitățile”.' : '');
    formErr('ot-member-err', '');
    if (bad) { var focusable = $(bad).tagName === 'FIELDSET' ? $(bad).querySelector('input') : $(bad); if (focusable) focusable.focus(); return; }
    var body, path;
    if (m) { path = '/organizer/team/update'; body = { member_id: String(m.id), role: role, permissions: perms, event_ids: ids }; }
    else { path = '/organizer/team/invite'; body = { name: val('ot-name') || null, email: email, password: pw, role: role, permissions: perms, event_ids: ids, send_welcome_email: $('ot-welcome').checked }; }
    busyBtn(btn, true, m ? 'Se salvează…' : 'Se adaugă…');
    d.setAttribute('data-busy', '');
    O.api(path, { method: 'POST', body: body }).then(function (r) {
      d.removeAttribute('data-busy');
      busyBtn(btn, false);
      closeDialog(d);
      if (m) {
        O.flash('Accesul lui ' + memberName(m) + ' a fost salvat.');
        load('member-edit-' + m.id);
        return;
      }
      var data = (r && r.data) || {}, sent = !!data.email_sent, who = val('ot-name') || email;
      load();
      if (body.send_welcome_email && sent) { O.flash(who + ' face parte acum din echipă. I-am trimis pe email datele de autentificare.'); return; }
      openCred(email, pw, body.send_welcome_email ? 'Contul e activ, dar emailul de bun venit nu a plecat. Trimite-i tu datele de mai jos.' : 'Contul e activ. Nu i-am trimis email, așa că trimite-i tu datele de mai jos.');
    }).catch(function (err) {
      d.removeAttribute('data-busy');
      busyBtn(btn, false);
      if (err && err.status === 401) return;
      var msg = norm(errMessage(err));
      if (/adresa ta de email/.test(msg)) { fieldErr('ot-email', 'Nu poți adăuga propria adresă de email.'); $('ot-email').focus(); return; }
      if (/deja in echipa/.test(msg)) { fieldErr('ot-email', 'Acest email este deja în echipă.'); $('ot-email').focus(); return; }
      if (/numarul maxim/.test(msg)) { var mx = /\((\d+)\)/.exec(msg); formErr('ot-member-err', 'Ai atins numărul maxim de membri în echipă' + (mx ? ' (' + mx[1] + ')' : '') + '. Elimină pe cineva ca să adaugi un coleg nou.'); return; }
      if (/proprietarul/.test(msg)) { formErr('ot-member-err', 'Accesul proprietarului nu se poate modifica.'); return; }
      if (err && err.status === 404) { formErr('ot-member-err', 'Membrul nu mai există. Reîncarcă lista.'); load(); return; }
      formErr('ot-member-err', saveError(err));
      markServer(err, { email: 'ot-email', password: 'ot-pass', name: 'ot-name', permissions: 'ot-perms', event_ids: 'ot-picks' });
    });
  });

  /* =================== PASSWORDS =================== */
  function resetEye(id) {
    var input = $(id), b = root.querySelector('[data-eye="' + id + '"]');
    input.type = 'password';
    if (b) { b.setAttribute('aria-pressed', 'false'); b.setAttribute('aria-label', 'Arată parola'); var u = b.querySelector('use'); if (u) u.setAttribute('href', '#i-eye'); }
  }
  qsa('[data-eye]', root).forEach(function (b) {
    b.addEventListener('click', function () {
      var input = $(b.getAttribute('data-eye')), show = input.type === 'password';
      input.type = show ? 'text' : 'password';
      b.setAttribute('aria-pressed', String(show));
      b.setAttribute('aria-label', show ? 'Ascunde parola' : 'Arată parola');
      var u = b.querySelector('use');
      if (u) u.setAttribute('href', '#i-' + (show ? 'eye-slash' : 'eye'));
    });
  });
  qsa('[data-gen]', root).forEach(function (b) {
    b.addEventListener('click', function () {
      var id = b.getAttribute('data-gen'), input = $(id), eye = root.querySelector('[data-eye="' + id + '"]');
      input.value = generatePassword();
      fieldErr(id, '');
      if (input.type === 'password' && eye) eye.click();
      input.focus();
    });
  });
  qsa('[data-copy]', root).forEach(function (b) {
    b.addEventListener('click', function () {
      var v = $(b.getAttribute('data-copy')).value;
      if (!v) { O.flash('Scrie sau generează mai întâi parola.', true); return; }
      copyText(v).then(function () { O.flash('Parola a fost copiată.'); }, function () { O.flash('Nu am putut copia. Selectează parola și copiaz-o manual.', true); });
    });
  });
  function openPass(m, mode, from) {
    passTarget = { m: m, mode: mode };
    var name = memberName(m);
    $('ot-pass-h').textContent = mode === 'activate' ? 'Activează contul lui ' + name : 'Parolă nouă pentru ' + name;
    $('ot-pass-p').textContent = mode === 'activate'
      ? name + ' nu a acceptat încă invitația. Cu o parolă, contul devine activ acum și invitația nu mai e necesară.'
      : 'Parola se schimbă imediat, pe bilete.online și în aplicația mobilă. Dacă aceeași adresă e în echipa altor operatori, se schimbă și acolo.';
    $('ot-pw2').value = '';
    resetEye('ot-pw2');
    fieldErr('ot-pw2', '');
    formErr('ot-pass-err', '');
    $('ot-pass-go').querySelector('[data-label]').textContent = mode === 'activate' ? 'Activează contul' : 'Salvează parola';
    fallbackFocus = $('ot-add');
    openDialog($('ot-pass-d'), from);
    $('ot-pw2').focus();
  }
  $('ot-pass-form').addEventListener('submit', function (ev) {
    ev.preventDefault();
    var d = $('ot-pass-d'), btn = $('ot-pass-go'), t = passTarget, pw = $('ot-pw2').value;
    if (!t || isBusy(btn)) return;
    formErr('ot-pass-err', '');
    var msg = !pw ? 'Scrie o parolă sau generează una.' : pw.length < 8 ? 'Parola trebuie să aibă cel puțin 8 caractere.' : pw.length > 100 ? 'Parola poate avea cel mult 100 de caractere.' : '';
    fieldErr('ot-pw2', msg);
    if (msg) { $('ot-pw2').focus(); return; }
    busyBtn(btn, true, t.mode === 'activate' ? 'Se activează…' : 'Se salvează…');
    d.setAttribute('data-busy', '');
    O.api('/organizer/team/' + (t.mode === 'activate' ? 'activate' : 'reset-password'), { method: 'POST', body: { member_id: String(t.m.id), password: pw } }).then(function () {
      d.removeAttribute('data-busy');
      busyBtn(btn, false);
      closeDialog(d);
      load(t.mode === 'activate' ? 'member-edit-' + t.m.id : 'member-pass-' + t.m.id);
      openCred(F.flat(t.m.email), pw, t.mode === 'activate' ? 'Contul lui ' + memberName(t.m) + ' e activ. Trimite-i datele de mai jos; nu îi trimitem email.' : 'Parola lui ' + memberName(t.m) + ' a fost schimbată. Trimite-i-o pe un canal sigur; nu îi trimitem email.', t.mode === 'activate' ? 'member-edit-' + t.m.id : 'member-pass-' + t.m.id);
    }).catch(function (err) {
      d.removeAttribute('data-busy');
      busyBtn(btn, false);
      if (err && err.status === 401) return;
      var m = norm(errMessage(err));
      if (/deja activ/.test(m)) { closeDialog(d); O.flash('Contul era deja activ.'); load(); return; }
      if (err && err.status === 404) { closeDialog(d); O.flash('Membrul nu mai există în echipă.', true); load(); return; }
      formErr('ot-pass-err', saveError(err));
      markServer(err, { password: 'ot-pw2' });
    });
  });
  /** key: the row button that gets the focus back when the window closes (the list is redrawn meanwhile). */
  function openCred(email, pw, text, key) {
    $('ot-cred-p').textContent = text;
    $('ot-cred-email').textContent = email;
    $('ot-cred-pass').textContent = pw;
    $('ot-cred-url').textContent = location.origin + '/autentificare?ca=venue';
    var from = key ? root.querySelector('[data-focus="' + key + '"]') : null;
    fallbackFocus = $('ot-add');
    openDialog($('ot-cred-d'), from || $('ot-add'));
    if (key) { openerKey = key; if (!from) opener = null; }
    $('ot-cred-copy').focus();
  }
  $('ot-cred-copy').addEventListener('click', function () {
    var text = 'Email: ' + $('ot-cred-email').textContent + '\nParolă: ' + $('ot-cred-pass').textContent + '\nAutentificare: ' + $('ot-cred-url').textContent + '\nAplicația mobilă: aceleași date.';
    copyText(text).then(function () { O.flash('Datele de autentificare au fost copiate.'); }, function () { O.flash('Nu am putut copia. Selectează datele și copiază-le manual.', true); });
  });
  $('ot-cred-d').addEventListener('close', function () { $('ot-cred-pass').textContent = ''; });

  /* =================== INVITATIONS =================== */
  function resendOne(m, btn) {
    btn.disabled = true;
    O.api('/organizer/team/resend-invite', { method: 'POST', body: { member_id: String(m.id) } }).then(function () {
      O.flash('Invitația a fost retrimisă către ' + F.flat(m.email) + '.');
      load('member-resend-' + m.id);
    }).catch(function (err) {
      btn.disabled = false;
      if (err && err.status === 401) return;
      if (err && err.status === 429) { O.flash('Poți retrimite invitația la 5 minute după ultima trimitere.', true); return; }
      if (err && err.status === 500) { O.flash('Emailul nu a putut fi trimis. Activează contul cu o parolă sau încearcă mai târziu.', true); return; }
      if (err && err.status === 422) { O.flash('Membrul nu mai are o invitație în așteptare.', true); load(); return; }
      if (err && err.status === 404) { O.flash('Membrul nu mai există în echipă.', true); load(); return; }
      O.flash('Nu am putut retrimite invitația. Încearcă din nou.', true);
    });
  }
  $('ot-resend-all').addEventListener('click', function () {
    var btn = this;
    if (isBusy(btn)) return;
    busyBtn(btn, true, 'Se trimit…');
    O.api('/organizer/team/resend-all-invites', { method: 'POST', body: {} }).then(function (r) {
      busyBtn(btn, false);
      var d = (r && r.data) || {}, sent = F.toNum(d.sent_count), failed = F.toNum(d.failed_count);
      O.flash(F.count(sent, 'invitație retrimisă', 'invitații retrimise') + (failed ? ', ' + F.count(failed, 'nu a plecat', 'nu au plecat') : '') + '.', failed > 0);
      load();
    }).catch(function (err) {
      busyBtn(btn, false);
      if (err && err.status === 401) return;
      if (err && err.status === 422) { O.flash('Nu sunt invitații de retrimis acum. O invitație se poate retrimite la 5 minute după ultima trimitere.', true); return; }
      if (err && err.status === 500) { O.flash('Emailurile nu au putut fi trimise. Activează membrii cu o parolă sau încearcă mai târziu.', true); return; }
      O.flash('Nu am putut retrimite invitațiile. Încearcă din nou.', true);
    });
  });

  /* =================== REMOVE =================== */
  function openDelete(m, from) {
    var pending = m.status === 'pending', name = memberName(m);
    delTarget = m;
    $('ot-del-h').textContent = pending ? 'Anulezi invitația?' : 'Elimini membrul?';
    $('ot-del-p').textContent = pending
      ? 'Invitația trimisă la ' + F.flat(m.email) + ' nu mai poate fi folosită.'
      : name + ' pierde accesul imediat, pe bilete.online și în aplicația mobilă. Ce a lucrat în cont rămâne.';
    $('ot-del-go').querySelector('[data-label]').textContent = pending ? 'Anulează invitația' : 'Elimină membrul';
    formErr('ot-del-err', '');
    fallbackFocus = $('ot-add');
    openDialog($('ot-del-d'), from);
    $('ot-del-d').querySelector('[data-close]').focus();
  }
  $('ot-del-go').addEventListener('click', function () {
    var btn = this, d = $('ot-del-d'), m = delTarget;
    if (!m || isBusy(btn)) return;
    var pending = m.status === 'pending';
    busyBtn(btn, true, 'Se elimină…');
    d.setAttribute('data-busy', '');
    O.api('/organizer/team/remove', { method: 'POST', body: { member_id: String(m.id) } }).then(function () {
      d.removeAttribute('data-busy');
      busyBtn(btn, false);
      closeDialog(d);
      O.flash(pending ? 'Invitația a fost anulată.' : memberName(m) + ' nu mai face parte din echipă.');
      load();
    }).catch(function (err) {
      d.removeAttribute('data-busy');
      busyBtn(btn, false);
      if (err && err.status === 401) return;
      if (err && err.status === 404) { closeDialog(d); O.flash('Membrul fusese deja eliminat.'); load(); return; }
      formErr('ot-del-err', /proprietarul/.test(norm(errMessage(err))) ? 'Proprietarul nu poate fi eliminat.' : err && (err.status === 0 || err.status == null) ? 'Nu am putut ajunge la server. Verifică conexiunea și încearcă din nou.' : 'Nu am putut elimina membrul. Încearcă din nou.');
    });
  });

  /* =================== DIALOGS =================== */
  function openDialog(d, from) {
    opener = from || document.activeElement;
    openerKey = opener && opener.getAttribute ? opener.getAttribute('data-focus') : null;
    if (typeof d.showModal === 'function') d.showModal(); else d.setAttribute('open', '');
  }
  function closeDialog(d) {
    if (!d.open && !d.hasAttribute('open')) return;
    if (typeof d.close === 'function') d.close();
    else { d.removeAttribute('open'); d.dispatchEvent(new Event('close')); }
  }
  qsa('.ot-dialog', root).forEach(function (d) {
    d.addEventListener('click', function (e) { if (!d.hasAttribute('data-busy') && e.target.closest('[data-close]')) closeDialog(d); });
    d.addEventListener('cancel', function (e) { if (d.hasAttribute('data-busy')) e.preventDefault(); });
    d.addEventListener('close', function () {
      if (qsa('.ot-dialog', root).some(function (x) { return x.open; })) return; // another dialog took over
      var back = opener && document.contains(opener) ? opener : (openerKey && root.querySelector('[data-focus="' + openerKey + '"]')) || fallbackFocus;
      opener = null;
      openerKey = null;
      if (back && typeof back.focus === 'function' && !back.disabled) back.focus();
    });
  });

  O.ready.then(function (ok) { if (ok) load(); });
})();
