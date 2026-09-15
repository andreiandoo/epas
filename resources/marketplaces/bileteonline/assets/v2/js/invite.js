/* bilete.online v2: accept a team invitation (/organizator/accept-invite). The head script has already moved token and
   email out of the address bar (window.BO_INVITE_LINK, and sessionStorage for reloads). This checks the invitation
   through the API proxy (organizer.validate-invite), shows who invites and with what role, asks for a password with
   confirmation (unless core already has one for this e-mail) and an optional phone, activates the membership
   (organizer.accept-invite) and points to the organizer sign-in. Text from the API is always written as text. */
(function () {
  'use strict';
  var $ = function (id) { return document.getElementById(id); };
  var form = $('ai-form');
  if (!form) return;

  var KEY = 'bo_invite_link', TTL = 7 * 24 * 60 * 60 * 1000;
  var API = (window.BILETEONLINE && window.BILETEONLINE.apiUrl) || '/api/proxy.php';
  var EMAIL = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
  var ROLES = { admin: 'Administrator', manager: 'Manager', staff: 'Staff' };
  var STRENGTH = { 1: ['Slabă', 'bad'], 2: ['Medie', 'mid'], 3: ['Puternică', 'ok'], 4: ['Foarte puternică', 'ok'] };
  var BAD = {
    invalid: ['Link invalid', 'Invitația nu mai e valabilă', 'Invitația a expirat, a fost deja folosită sau linkul e incomplet. Cere-i organizatorului să ți-o retrimită: o invitație e valabilă 7 zile.'],
    busy: ['Prea multe încercări', 'Încearcă din nou peste un minut', 'Am primit prea multe verificări într-un timp scurt. Linkul nu s-a schimbat, îl poți folosi în continuare.'],
    error: ['Eroare de conexiune', 'Nu am putut verifica invitația', 'Verifică conexiunea la internet și încearcă din nou.'],
  };
  var pass = $('ai-pass'), pass2 = $('ai-pass2'), phone = $('ai-phone'), submit = $('ai-submit'), error = $('ai-error');
  var meter = $('ai-meter'), strength = $('ai-strength'), match = $('ai-match'), status = $('ai-status');
  var LABEL = submit.textContent, busy = false, visible = false, reuse = false, invite = null, link = readLink();

  function txt(v) {
    if (v && typeof v === 'object') v = v.ro || v.en || '';
    return v == null ? '' : String(v).trim();
  }
  function readLink() {
    var fromUrl = window.BO_INVITE_LINK;
    if (fromUrl) return fromUrl.token && fromUrl.email ? fromUrl : { token: '', email: fromUrl.email || '' };
    try {
      var saved = JSON.parse(sessionStorage.getItem(KEY) || 'null');
      if (saved && saved.token && saved.email && Date.now() - (saved.at || 0) < TTL) return saved;
    } catch (e) {}
    return null;
  }
  function forget() { try { sessionStorage.removeItem(KEY); } catch (e) {} }

  /** GET with params or POST with a JSON body through the proxy; rejects with {status, data} (status 0: no connection). */
  function call(action, params, body) {
    var url = API + '?action=' + encodeURIComponent(action) + (params ? '&' + new URLSearchParams(params).toString() : '');
    var opts = { method: body ? 'POST' : 'GET', headers: { Accept: 'application/json' }, cache: 'no-store', credentials: 'same-origin' };
    if (body) { opts.headers['Content-Type'] = 'application/json'; opts.body = JSON.stringify(body); }
    return fetch(url, opts).then(function (r) {
      return r.text().then(function (t) {
        var d = null;
        try { d = t ? JSON.parse(t) : null; } catch (e) { d = null; }
        if (!r.ok) throw { status: r.status, data: d || {} };
        if (!d || d.success === false) throw { status: -1, data: d || {} };
        return d;
      });
    }, function () { throw { status: 0, data: {} }; });
  }

  function show(id, focusId) {
    ['ai-loading-view', 'ai-form-view', 'ai-done-view', 'ai-bad-view'].forEach(function (v) { $(v).hidden = v !== id; });
    if (focusId) $(focusId).focus();
  }
  function bad(kind, focus) {
    var t = BAD[kind];
    if (kind === 'invalid') forget();
    $('ai-bad-k').textContent = t[0];
    $('ai-bad-h').textContent = t[1];
    $('ai-bad-p').textContent = t[2];
    $('ai-retry').hidden = kind === 'invalid';
    $('ai-bad-login').className = 'btn ' + (kind === 'invalid' ? 'btn-primary' : 'btn-ghost');
    $('ai-bad-note').hidden = kind !== 'invalid';
    status.textContent = t[1] + '.';
    show('ai-bad-view', focus ? 'ai-bad-h' : null);
  }
  function usePassword(on) {
    $('ai-pass-f').hidden = !on;
    $('ai-pass2-f').hidden = !on;
    $('ai-existing').hidden = on;
    $('ai-form-p').textContent = on ? 'Alege parola cu care vei intra în cont, pe site și în aplicația mobilă.' : 'Folosești parola pe care o ai deja pe bilete.online.';
  }

  // ---------- the invitation ----------
  function check(focus) {
    if (!link || !link.token || !EMAIL.test(link.email || '')) { bad('invalid', focus); return; }
    show('ai-loading-view', focus ? 'ai-loading-h' : null);
    status.textContent = 'Se verifică invitația…';
    call('organizer.validate-invite', { token: link.token, email: link.email }).then(function (r) {
      var d = r.data || {}, m = d.member || {}, o = d.organizer || {};
      var org = txt(o.name) || txt(o.company_name), company = txt(o.company_name), name = txt(m.name), email = txt(m.email) || link.email, role = ROLES[m.role] || '';
      reuse = !!d.has_existing_password;
      invite = { org: org, email: email };
      $('ai-org').textContent = org || 'Organizator bilete.online';
      $('ai-company').textContent = company;
      $('ai-company').hidden = !company || company === org;
      $('ai-name').textContent = name;
      $('ai-name-row').hidden = !name;
      $('ai-email').textContent = email;
      $('ai-role').textContent = role || 'Membru al echipei';
      $('ai-username').value = email;
      $('ai-lead').textContent = (org || 'Un organizator') + ' te-a adăugat în echipă' + (role ? ', cu rolul ' + role : '') + '. ' + (reuse ? 'Confirmă și contul devine activ pe loc.' : 'Alege o parolă și contul devine activ pe loc.');
      usePassword(!reuse);
      show('ai-form-view', focus ? 'ai-form-h' : null);
      status.textContent = 'Invitația e valabilă.';
    }).catch(function (err) {
      var s = err && err.status;
      bad(s === 400 || s === 404 || s === 422 ? 'invalid' : s === 429 ? 'busy' : 'error', focus);
    });
  }
  $('ai-retry').addEventListener('click', function () { check(true); });

  // ---------- as it's typed ----------
  function score(p) {
    if (!p) return 0;
    if (p.length < 8) return 1;
    var s = 1;
    if (/[a-z]/.test(p) && /[A-Z]/.test(p)) s++;
    if (/\d/.test(p)) s++;
    if (/[^A-Za-z0-9]/.test(p)) s++;
    return s;
  }
  function hint(el, text, tone) {
    el.textContent = text;
    if (tone) el.setAttribute('data-tone', tone); else el.removeAttribute('data-tone');
  }
  function checkMatch() {
    if (!pass2.value) hint(match, '');
    else if (pass.value === pass2.value) hint(match, '✓ Parolele coincid', 'ok');
    else hint(match, 'Parolele nu coincid', 'bad');
  }
  pass.addEventListener('input', function () {
    var p = pass.value, s = score(p);
    meter.setAttribute('data-score', String(s));
    if (!p) hint(strength, '');
    else if (p.length < 8) hint(strength, 'Prea scurtă · minim 8 caractere', 'bad');
    else hint(strength, STRENGTH[s][0], STRENGTH[s][1]);
    checkMatch();
  });
  pass2.addEventListener('input', checkMatch);

  [].forEach.call(document.querySelectorAll('[data-toggle-pass]'), function (btn) {
    btn.addEventListener('click', function () {
      visible = !visible;
      [pass, pass2].forEach(function (el) { el.type = visible ? 'text' : 'password'; });
      [].forEach.call(document.querySelectorAll('[data-toggle-pass]'), function (b) {
        b.textContent = visible ? 'ascunde' : 'arată';
        b.setAttribute('aria-pressed', String(visible));
        b.setAttribute('aria-label', visible ? 'Ascunde parola' : 'Arată parola');
      });
    });
  });

  // ---------- activate ----------
  function say(text, field) {
    error.textContent = text;
    error.hidden = false;
    [pass, pass2, phone].forEach(function (el) { el.removeAttribute('aria-invalid'); });
    if (field) { field.setAttribute('aria-invalid', 'true'); field.focus(); }
  }
  form.addEventListener('submit', function (e) {
    e.preventDefault();
    if (busy || !invite) return;
    error.hidden = true;
    [pass, pass2, phone].forEach(function (el) { el.removeAttribute('aria-invalid'); });
    var body = { token: link.token, email: link.email }, ph = phone.value.trim();
    if (!reuse) {
      if (!pass.value) { say('Alege o parolă pentru cont.', pass); return; }
      if (pass.value.length < 8) { say('Parola trebuie să aibă minim 8 caractere.', pass); return; }
      if (pass.value !== pass2.value) { say('Parolele nu coincid.', pass2); return; }
      body.password = pass.value;
      body.password_confirmation = pass2.value;
    }
    if (ph && (!/^\+?[\d\s().\/-]+$/.test(ph) || ph.replace(/\D/g, '').length < 6)) { say('Scrie un număr de telefon valid sau lasă câmpul gol.', phone); return; }
    if (ph) body.phone = ph;

    busy = true;
    submit.disabled = true;
    submit.textContent = 'Se activează…';
    call('organizer.accept-invite', null, body).then(function (r) {
      var d = r.data || {}, reused = !!d.reused_existing_password;
      forget();
      pass.value = pass2.value = '';
      $('ai-done-org').textContent = txt(d.organizer_name) || invite.org || 'organizatorului';
      $('ai-done-email').textContent = invite.email;
      // core keeps the password this e-mail already has in another team, even when one was typed here
      $('ai-done-pass').textContent = !reused ? 'parola aleasă' : body.password ? 'parola pe care o aveai deja pe bilete.online, nu cea aleasă acum' : 'parola pe care o folosești deja pe bilete.online';
      $('ai-login').href = '/autentificare?ca=venue&email=' + encodeURIComponent(invite.email);
      status.textContent = 'Contul e activ.';
      show('ai-done-view', 'ai-done-h');
    }).catch(function (err) {
      var s = err && err.status, errors = (err && err.data && err.data.errors) || {};
      if (s === 400 || s === 404 || (s === 422 && (errors.token || errors.email))) { bad('invalid', true); return; }
      if (s === 422 && errors.password) {
        // the password this e-mail had elsewhere is gone since the check: ask for one
        if (reuse) { reuse = false; usePassword(true); say('Alege o parolă pentru cont: nu am mai găsit parola ta de pe bilete.online.', pass); return; }
        if (/confirm/i.test(String(errors.password[0] || ''))) say('Parolele nu coincid.', pass2);
        else say('Parola trebuie să aibă minim 8 caractere.', pass);
        return;
      }
      if (s === 422 && errors.phone) { say('Numărul de telefon poate avea cel mult 30 de caractere.', phone); return; }
      if (s === 429) say('Prea multe încercări. Încearcă din nou peste un minut.');
      else if (s === 0) say('Nu ne-am putut conecta. Verifică internetul și încearcă din nou.');
      else say('Nu am putut activa contul acum. Încearcă din nou în câteva momente.');
    }).then(function () {
      busy = false;
      submit.disabled = false;
      submit.textContent = LABEL;
    });
  });

  check(false);
})();
