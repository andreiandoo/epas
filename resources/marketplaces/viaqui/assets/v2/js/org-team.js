/* viaqui.com v2: organizer team (/organizator/echipa). The owner and the members from /organizer/team; adding a member
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

  var ROLES = { owner: VQ.t('Owner'), admin: VQ.t('Administrator'), manager: VQ.t('Manager'), staff: VQ.t('Staff') };
  var ROLE_HELP = { admin: VQ.t('Has every permission and sees all experiences.'), manager: VQ.t('Works only with what you allow below, for the chosen experiences.'), staff: VQ.t('Checks guests in at the entrance, from the mobile app.') };
  var PERMS = { events: VQ.t('Experiences'), orders: VQ.t('Orders'), reports: VQ.t('Reports'), team: VQ.t('Team'), checkin: VQ.t('Check-in') };
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
  function memberName(m) { return F.flat(m.name).trim() || F.flat(m.email).trim() || VQ.t('Member'); }
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
    if (s === 422) return VQ.t('Some fields are not filled in correctly. Check them and try again.');
    if (s === 429) return VQ.t('Too many attempts in a short time. Wait a minute and try again.');
    if (s === 403) return VQ.t('Your account is not allowed to change the team.');
    if (s === 0 || s == null) return VQ.t('We could not reach the server. Check your connection and try again.');
    return VQ.t('We could not save. Try again.');
  }
  function markServer(err, map) {
    var errors = (err && err.errors) || (err && err.data && err.data.errors) || null, first = null;
    if (!errors || typeof errors !== 'object') return null;
    Object.keys(errors).forEach(function (k) {
      var key = k.replace(/\.\d+$/, '');
      if (map[key] && $(map[key])) { fieldErr(map[key], VQ.t('Check this value.')); if (!first) first = map[key]; }
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
    $('ot-pending-t').textContent = VQ.n(pending, 'pending invitation', 'pending invitations');
    $('ot-list-p').textContent = VQ.t('{people} with access to the account, {active} active.', { people: VQ.n(total, 'person', 'people'), active: F.num(active) });
    drawList();
  }
  function scopeText(m) {
    if (m.role === 'owner' || m.role === 'admin') return VQ.t('All experiences');
    var ids = Array.isArray(m.event_ids) ? m.event_ids : [];
    if (!ids.length) return VQ.t('All experiences');
    var names = events ? ids.map(function (id) { var e = events.filter(function (x) { return String(x.id) === String(id); })[0]; return e ? F.flat(e.name || e.title) : ''; }).filter(Boolean) : [];
    if (!names.length) return VQ.t('Only {chosen}', { chosen: VQ.n(ids.length, 'chosen experience', 'chosen experiences') });
    return ids.length > 2 ? VQ.t('Only {names} and {n} more', { names: names.slice(0, 2).join(', '), n: ids.length - 2 }) : VQ.t('Only {names}', { names: names.slice(0, 2).join(', ') });
  }
  function drawList() {
    var box = $('ot-list'), q = norm(val('ot-q')), shown = members.filter(function (m) { return !q || norm(memberName(m) + ' ' + F.flat(m.email)).indexOf(q) > -1; });
    box.textContent = '';
    shown.forEach(function (m) { box.appendChild(row(m)); });
    if (q && !shown.length) box.appendChild(el('li', { class: 'ot-empty-p', text: VQ.t('No member matches your search.') }));
    if (!q && members.length <= 1) {
      var cta = el('button', { class: 'btn btn-primary ot-sm', type: 'button', 'data-focus': 'member-first' }, [icon('user-plus'), VQ.t('Add your first colleague')]);
      cta.addEventListener('click', function () { openMember(null, cta); });
      box.appendChild(el('li', { class: 'ot-empty' }, [el('b', { text: VQ.t('No colleagues yet') }), el('p', { text: VQ.t('Add the colleagues who sell, check tickets at the entrance or manage the experiences.') }), cta]));
    }
  }
  function row(m) {
    var role = ROLES[m.role] ? m.role : 'staff', owner = m.role === 'owner', pending = m.status === 'pending', inactive = m.status === 'inactive', id = String(m.id), name = memberName(m);
    var tags = [tag(ROLES[m.role] || F.flat(m.role) || VQ.t('Member'), owner ? 'is-ok' : role === 'admin' ? 'is-info' : 'is-muted')];
    if (pending) tags.push(tag(VQ.t('Invitation sent'), 'is-wait'));
    else if (inactive) tags.push(tag(VQ.t('Inactive'), 'is-bad'));
    else tags.push(tag(VQ.t('Active'), 'is-ok'));
    var perms = owner || role === 'admin' ? [VQ.t('Full access')] : (Array.isArray(m.permissions) ? m.permissions : []).map(function (p) { return PERMS[p] || p; });
    var meta = [scopeText(m)];
    if (pending && m.invite_sent_at) meta.push(VQ.t('invited {when}', { when: F.ago(m.invite_sent_at) }));
    else if (!owner && m.accepted_at) meta.push(VQ.t('in the team since {date}', { date: dayLabel(m.accepted_at) }));
    var acts = [];
    if (owner) acts.push(el('span', { class: 'ot-you', text: m.is_current_user ? VQ.t('Account owner (you)') : VQ.t('Account owner') }));
    else if (pending) {
      var resend = pill('arrow-counter-clockwise', VQ.t('Resend invitation'), { 'data-focus': 'member-resend-' + id, 'aria-label': VQ.t('Resend the invitation to {name}', { name: name }) });
      resend.addEventListener('click', function () { resendOne(m, resend); });
      var activate = pill('lock-simple', VQ.t('Activate'), { 'data-focus': 'member-activate-' + id, 'aria-label': VQ.t('Activate the account of {name} with a password', { name: name }) });
      activate.addEventListener('click', function () { openPass(m, 'activate', activate); });
      var cancel = pill('x', VQ.t('Cancel invitation'), { class: 'ot-pill is-danger', 'data-focus': 'member-del-' + id, 'aria-label': VQ.t('Cancel the invitation of {name}', { name: name }) });
      cancel.addEventListener('click', function () { openDelete(m, cancel); });
      acts.push(resend, activate, cancel);
    } else {
      var editBtn = pill('pencil-simple', VQ.t('Access'), { 'data-focus': 'member-edit-' + id, 'aria-label': VQ.t('Change the access of {name}', { name: name }) });
      editBtn.addEventListener('click', function () { openMember(m, editBtn); });
      var passBtn = pill('lock-simple', VQ.t('New password'), { 'data-focus': 'member-pass-' + id, 'aria-label': VQ.t('Set a new password for {name}', { name: name }) });
      passBtn.addEventListener('click', function () { openPass(m, 'reset', passBtn); });
      var del = pill('trash', VQ.t('Remove'), { class: 'ot-pill is-danger', 'data-focus': 'member-del-' + id, 'aria-label': VQ.t('Remove {name} from the team', { name: name }) });
      del.addEventListener('click', function () { openDelete(m, del); });
      acts.push(editBtn, passBtn, del);
    }
    return el('li', { class: 'ot-m' + (pending ? ' is-pending' : '') }, [
      el('span', { class: 'ot-av is-' + (owner ? 'owner' : role), 'aria-hidden': 'true', text: initials(F.flat(m.name) || F.flat(m.email)) }),
      el('div', { class: 'ot-m-t' }, [
        el('div', { class: 'ot-m-top' }, [el('b', { text: name })].concat(tags)),
        F.flat(m.name).trim() ? el('span', { class: 'ot-m-mail', text: F.flat(m.email) }) : null,
        el('ul', { class: 'ot-m-perms', 'aria-label': VQ.t('Permissions') }, perms.length ? perms.map(function (p) { return el('li', { text: p }); }) : el('li', { text: VQ.t('No permissions') })),
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
    if (!events.length) { box.appendChild(el('li', { class: 'ot-picks-msg', text: VQ.t('You have no experiences yet.') })); $('ot-picks-n').textContent = ''; return; }
    var shown = events.filter(function (e) { return !q || norm(F.flat(e.name || e.title) + ' ' + F.flat(e.venue_name) + ' ' + F.flat(e.venue_city)).indexOf(q) > -1; });
    if (!shown.length) box.appendChild(el('li', { class: 'ot-picks-msg', text: VQ.t('No experience matches your search.') }));
    shown.forEach(function (e) {
      var id = String(e.id), day = naiveDay(e.starts_at), cb = el('input', { type: 'checkbox', value: id });
      cb.checked = !!picked[id];
      cb.addEventListener('change', function () {
        picked[id] = cb.checked;
        fieldErr('ot-picks', '');
        $('ot-picks-n').textContent = pickedCount();
      });
      box.appendChild(el('li', null, el('label', { class: 'ot-pick' }, [cb, el('span', null, [el('b', { text: F.flat(e.name || e.title) || VQ.t('Experience #{id}', { id: id }) }), el('small', { text: [day ? dayLabel(day) : '', F.flat(e.venue_name), isOver(e) ? VQ.t('ended') : ''].filter(Boolean).join(' · ') })])])));
    });
    $('ot-picks-n').textContent = pickedCount();
    void n;
  }
  function pickedCount() { var n = pickedIds().length; return n ? VQ.n(n, 'experience chosen', 'experiences chosen') : VQ.t('Choose the experiences they see.'); }
  $('ot-pq').addEventListener('input', function () { if (events) renderPicks(); });
  function scopeValue() { var r = root.querySelector('input[name="ot-scope"]:checked'); return r ? r.value : 'all'; }
  function syncScope() {
    var some = scopeValue() === 'some' && $('ot-role').value !== 'admin';
    $('ot-some').hidden = !some;
    if (!some) return;
    var box = $('ot-picks');
    if (!events) { box.textContent = ''; box.appendChild(el('li', { class: 'ot-picks-msg', text: VQ.t('Loading experiences…') })); }
    loadEvents().then(renderPicks, function () {
      box.textContent = '';
      var retry = el('button', { class: 'ot-linkbtn', type: 'button', text: VQ.t('Try again') });
      retry.addEventListener('click', syncScope);
      box.appendChild(el('li', { class: 'ot-picks-msg' }, [VQ.t('We could not load the experiences.') + ' ', retry]));
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
    $('ot-member-h').textContent = m ? VQ.t('Access of {name}', { name: memberName(m) }) : VQ.t('New member');
    $('ot-member-p').textContent = m ? F.flat(m.email) : VQ.t('The account is created straight away and is active. The member signs in with the email and password from here.');
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
    $('ot-member-go').querySelector('[data-label]').textContent = m ? VQ.t('Save access') : VQ.t('Add member');
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
      need('ot-email', !email ? VQ.t('Enter the email of your colleague.') : !EMAIL.test(email) ? VQ.t('Enter a valid email address, for example ana@your-company.com.') : '');
      need('ot-pass', !pw ? VQ.t('Enter a password or generate one.') : pw.length < 8 ? VQ.t('The password must have at least 8 characters.') : pw.length > 100 ? VQ.t('The password can have 100 characters at most.') : '');
    }
    need('ot-perms', role !== 'admin' && !perms.length ? VQ.t('Choose at least one permission.') : '');
    need('ot-picks', some && !ids.length ? VQ.t('Choose at least one experience or keep "All experiences".') : '');
    formErr('ot-member-err', '');
    if (bad) { var focusable = $(bad).tagName === 'FIELDSET' ? $(bad).querySelector('input') : $(bad); if (focusable) focusable.focus(); return; }
    var body, path;
    if (m) { path = '/organizer/team/update'; body = { member_id: String(m.id), role: role, permissions: perms, event_ids: ids }; }
    else { path = '/organizer/team/invite'; body = { name: val('ot-name') || null, email: email, password: pw, role: role, permissions: perms, event_ids: ids, send_welcome_email: $('ot-welcome').checked }; }
    busyBtn(btn, true, m ? VQ.t('Saving…') : VQ.t('Adding…'));
    d.setAttribute('data-busy', '');
    O.api(path, { method: 'POST', body: body }).then(function (r) {
      d.removeAttribute('data-busy');
      busyBtn(btn, false);
      closeDialog(d);
      if (m) {
        O.flash(VQ.t('The access of {name} has been saved.', { name: memberName(m) }));
        load('member-edit-' + m.id);
        return;
      }
      var data = (r && r.data) || {}, sent = !!data.email_sent, who = val('ot-name') || email;
      load();
      if (body.send_welcome_email && sent) { O.flash(VQ.t('{name} is now part of the team. We have emailed them their sign-in details.', { name: who })); return; }
      openCred(email, pw, body.send_welcome_email ? VQ.t('The account is active, but the welcome email was not sent. Send them the details below yourself.') : VQ.t('The account is active. We did not send an email, so send them the details below yourself.'));
    }).catch(function (err) {
      d.removeAttribute('data-busy');
      busyBtn(btn, false);
      if (err && err.status === 401) return;
      var msg = norm(errMessage(err));
      if (/adresa ta de email/.test(msg)) { fieldErr('ot-email', VQ.t('You cannot add your own email address.')); $('ot-email').focus(); return; }
      if (/deja in echipa/.test(msg)) { fieldErr('ot-email', VQ.t('This email is already in the team.')); $('ot-email').focus(); return; }
      if (/numarul maxim/.test(msg)) { var mx = /\((\d+)\)/.exec(msg); formErr('ot-member-err', mx ? VQ.t('You have reached the maximum number of team members ({max}). Remove someone to add a new colleague.', { max: mx[1] }) : VQ.t('You have reached the maximum number of team members. Remove someone to add a new colleague.')); return; }
      if (/proprietarul/.test(msg)) { formErr('ot-member-err', VQ.t('The access of the owner cannot be changed.')); return; }
      if (err && err.status === 404) { formErr('ot-member-err', VQ.t('The member no longer exists. Reload the list.')); load(); return; }
      formErr('ot-member-err', saveError(err));
      markServer(err, { email: 'ot-email', password: 'ot-pass', name: 'ot-name', permissions: 'ot-perms', event_ids: 'ot-picks' });
    });
  });

  /* =================== PASSWORDS =================== */
  function resetEye(id) {
    var input = $(id), b = root.querySelector('[data-eye="' + id + '"]');
    input.type = 'password';
    if (b) { b.setAttribute('aria-pressed', 'false'); b.setAttribute('aria-label', VQ.t('Show password')); var u = b.querySelector('use'); if (u) u.setAttribute('href', '#i-eye'); }
  }
  qsa('[data-eye]', root).forEach(function (b) {
    b.addEventListener('click', function () {
      var input = $(b.getAttribute('data-eye')), show = input.type === 'password';
      input.type = show ? 'text' : 'password';
      b.setAttribute('aria-pressed', String(show));
      b.setAttribute('aria-label', show ? VQ.t('Hide password') : VQ.t('Show password'));
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
      if (!v) { O.flash(VQ.t('Enter or generate the password first.'), true); return; }
      copyText(v).then(function () { O.flash(VQ.t('The password has been copied.')); }, function () { O.flash(VQ.t('We could not copy it. Select the password and copy it by hand.'), true); });
    });
  });
  function openPass(m, mode, from) {
    passTarget = { m: m, mode: mode };
    var name = memberName(m);
    $('ot-pass-h').textContent = mode === 'activate' ? VQ.t('Activate the account of {name}', { name: name }) : VQ.t('New password for {name}', { name: name });
    $('ot-pass-p').textContent = mode === 'activate'
      ? VQ.t('{name} has not accepted the invitation yet. With a password, the account becomes active now and the invitation is no longer needed.', { name: name })
      : VQ.t('The password changes straight away, on Viaqui and in the mobile app. If the same address is in the team of other operators, it changes there too.');
    $('ot-pw2').value = '';
    resetEye('ot-pw2');
    fieldErr('ot-pw2', '');
    formErr('ot-pass-err', '');
    $('ot-pass-go').querySelector('[data-label]').textContent = mode === 'activate' ? VQ.t('Activate account') : VQ.t('Save password');
    fallbackFocus = $('ot-add');
    openDialog($('ot-pass-d'), from);
    $('ot-pw2').focus();
  }
  $('ot-pass-form').addEventListener('submit', function (ev) {
    ev.preventDefault();
    var d = $('ot-pass-d'), btn = $('ot-pass-go'), t = passTarget, pw = $('ot-pw2').value;
    if (!t || isBusy(btn)) return;
    formErr('ot-pass-err', '');
    var msg = !pw ? VQ.t('Enter a password or generate one.') : pw.length < 8 ? VQ.t('The password must have at least 8 characters.') : pw.length > 100 ? VQ.t('The password can have 100 characters at most.') : '';
    fieldErr('ot-pw2', msg);
    if (msg) { $('ot-pw2').focus(); return; }
    busyBtn(btn, true, t.mode === 'activate' ? VQ.t('Activating…') : VQ.t('Saving…'));
    d.setAttribute('data-busy', '');
    O.api('/organizer/team/' + (t.mode === 'activate' ? 'activate' : 'reset-password'), { method: 'POST', body: { member_id: String(t.m.id), password: pw } }).then(function () {
      d.removeAttribute('data-busy');
      busyBtn(btn, false);
      closeDialog(d);
      load(t.mode === 'activate' ? 'member-edit-' + t.m.id : 'member-pass-' + t.m.id);
      openCred(F.flat(t.m.email), pw, t.mode === 'activate' ? VQ.t('The account of {name} is active. Send them the details below: we do not send an email.', { name: memberName(t.m) }) : VQ.t('The password of {name} has been changed. Send it to them over a safe channel: we do not send an email.', { name: memberName(t.m) }), t.mode === 'activate' ? 'member-edit-' + t.m.id : 'member-pass-' + t.m.id);
    }).catch(function (err) {
      d.removeAttribute('data-busy');
      busyBtn(btn, false);
      if (err && err.status === 401) return;
      var m = norm(errMessage(err));
      if (/deja activ/.test(m)) { closeDialog(d); O.flash(VQ.t('The account was already active.')); load(); return; }
      if (err && err.status === 404) { closeDialog(d); O.flash(VQ.t('The member is no longer in the team.'), true); load(); return; }
      formErr('ot-pass-err', saveError(err));
      markServer(err, { password: 'ot-pw2' });
    });
  });
  /** key: the row button that gets the focus back when the window closes (the list is redrawn meanwhile). */
  function openCred(email, pw, text, key) {
    $('ot-cred-p').textContent = text;
    $('ot-cred-email').textContent = email;
    $('ot-cred-pass').textContent = pw;
    $('ot-cred-url').textContent = location.origin + VQ.url('/login?ca=venue');
    var from = key ? root.querySelector('[data-focus="' + key + '"]') : null;
    fallbackFocus = $('ot-add');
    openDialog($('ot-cred-d'), from || $('ot-add'));
    if (key) { openerKey = key; if (!from) opener = null; }
    $('ot-cred-copy').focus();
  }
  $('ot-cred-copy').addEventListener('click', function () {
    var text = [VQ.t('Email: {email}', { email: $('ot-cred-email').textContent }), VQ.t('Password: {password}', { password: $('ot-cred-pass').textContent }), VQ.t('Sign in: {url}', { url: $('ot-cred-url').textContent }), VQ.t('Mobile app: the same details.')].join('\n');
    copyText(text).then(function () { O.flash(VQ.t('The sign-in details have been copied.')); }, function () { O.flash(VQ.t('We could not copy them. Select the details and copy them by hand.'), true); });
  });
  $('ot-cred-d').addEventListener('close', function () { $('ot-cred-pass').textContent = ''; });

  /* =================== INVITATIONS =================== */
  function resendOne(m, btn) {
    btn.disabled = true;
    O.api('/organizer/team/resend-invite', { method: 'POST', body: { member_id: String(m.id) } }).then(function () {
      O.flash(VQ.t('The invitation has been resent to {email}.', { email: F.flat(m.email) }));
      load('member-resend-' + m.id);
    }).catch(function (err) {
      btn.disabled = false;
      if (err && err.status === 401) return;
      if (err && err.status === 429) { O.flash(VQ.t('You can resend the invitation 5 minutes after the last one was sent.'), true); return; }
      if (err && err.status === 500) { O.flash(VQ.t('The email could not be sent. Activate the account with a password or try again later.'), true); return; }
      if (err && err.status === 422) { O.flash(VQ.t('The member no longer has a pending invitation.'), true); load(); return; }
      if (err && err.status === 404) { O.flash(VQ.t('The member is no longer in the team.'), true); load(); return; }
      O.flash(VQ.t('We could not resend the invitation. Try again.'), true);
    });
  }
  $('ot-resend-all').addEventListener('click', function () {
    var btn = this;
    if (isBusy(btn)) return;
    busyBtn(btn, true, VQ.t('Sending…'));
    O.api('/organizer/team/resend-all-invites', { method: 'POST', body: {} }).then(function (r) {
      busyBtn(btn, false);
      var d = (r && r.data) || {}, sent = F.toNum(d.sent_count), failed = F.toNum(d.failed_count);
      O.flash(failed ? VQ.t('{sent} resent, {failed} not sent.', { sent: VQ.n(sent, 'invitation', 'invitations'), failed: F.num(failed) }) : VQ.t('{sent} resent.', { sent: VQ.n(sent, 'invitation', 'invitations') }), failed > 0);
      load();
    }).catch(function (err) {
      busyBtn(btn, false);
      if (err && err.status === 401) return;
      if (err && err.status === 422) { O.flash(VQ.t('There are no invitations to resend right now. An invitation can be resent 5 minutes after the last one was sent.'), true); return; }
      if (err && err.status === 500) { O.flash(VQ.t('The emails could not be sent. Activate the members with a password or try again later.'), true); return; }
      O.flash(VQ.t('We could not resend the invitations. Try again.'), true);
    });
  });

  /* =================== REMOVE =================== */
  function openDelete(m, from) {
    var pending = m.status === 'pending', name = memberName(m);
    delTarget = m;
    $('ot-del-h').textContent = pending ? VQ.t('Cancel the invitation?') : VQ.t('Remove the member?');
    $('ot-del-p').textContent = pending
      ? VQ.t('The invitation sent to {email} can no longer be used.', { email: F.flat(m.email) })
      : VQ.t('{name} loses access straight away, on Viaqui and in the mobile app. The work they did in the account stays.', { name: name });
    $('ot-del-go').querySelector('[data-label]').textContent = pending ? VQ.t('Cancel invitation') : VQ.t('Remove member');
    formErr('ot-del-err', '');
    fallbackFocus = $('ot-add');
    openDialog($('ot-del-d'), from);
    $('ot-del-d').querySelector('[data-close]').focus();
  }
  $('ot-del-go').addEventListener('click', function () {
    var btn = this, d = $('ot-del-d'), m = delTarget;
    if (!m || isBusy(btn)) return;
    var pending = m.status === 'pending';
    busyBtn(btn, true, VQ.t('Removing…'));
    d.setAttribute('data-busy', '');
    O.api('/organizer/team/remove', { method: 'POST', body: { member_id: String(m.id) } }).then(function () {
      d.removeAttribute('data-busy');
      busyBtn(btn, false);
      closeDialog(d);
      O.flash(pending ? VQ.t('The invitation has been cancelled.') : VQ.t('{name} is no longer part of the team.', { name: memberName(m) }));
      load();
    }).catch(function (err) {
      d.removeAttribute('data-busy');
      busyBtn(btn, false);
      if (err && err.status === 401) return;
      if (err && err.status === 404) { closeDialog(d); O.flash(VQ.t('The member had already been removed.')); load(); return; }
      formErr('ot-del-err', /proprietarul/.test(norm(errMessage(err))) ? VQ.t('The owner cannot be removed.') : err && (err.status === 0 || err.status == null) ? VQ.t('We could not reach the server. Check your connection and try again.') : VQ.t('We could not remove the member. Try again.'));
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
