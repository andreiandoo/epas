/* viaqui.com v2: accept a team invitation (/organizator/accept-invite). The head script has already moved token and
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
  var ROLES = { admin: VQ.t('Administrator'), manager: VQ.t('Manager'), staff: VQ.t('Staff') };
  var STRENGTH = { 1: [VQ.t('Weak'), 'bad'], 2: [VQ.t('Fair'), 'mid'], 3: [VQ.t('Strong'), 'ok'], 4: [VQ.t('Very strong'), 'ok'] };
  var BAD = {
    invalid: [VQ.t('Invalid link'), VQ.t('The invitation is no longer valid'), VQ.t('The invitation has expired, was already used, or the link is incomplete. Ask the operator to send it again: an invitation is valid for 7 days.')],
    busy: [VQ.t('Too many attempts'), VQ.t('Try again in a minute'), VQ.t('We received too many checks in a short time. The link has not changed, you can still use it.')],
    error: [VQ.t('Connection error'), VQ.t('We could not check the invitation'), VQ.t('Check your internet connection and try again.')],
  };
  var pass = $('ai-pass'), pass2 = $('ai-pass2'), phone = $('ai-phone'), submit = $('ai-submit'), error = $('ai-error');
  var meter = $('ai-meter'), strength = $('ai-strength'), match = $('ai-match'), status = $('ai-status');
  var LABEL = submit.textContent, busy = false, visible = false, reuse = false, invite = null, link = readLink();

  function txt(v) {
    if (v && typeof v === 'object') v = v[VQ.locale] || v.en || v.ro || '';
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
  function esc(v) { return String(v == null ? '' : v).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;'); }

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
    $('ai-form-p').textContent = on ? VQ.t('Choose the password you will sign in with, on the site and in the mobile app.') : VQ.t('You use the password you already have on Viaqui.');
  }

  // ---------- the invitation ----------
  function check(focus) {
    if (!link || !link.token || !EMAIL.test(link.email || '')) { bad('invalid', focus); return; }
    show('ai-loading-view', focus ? 'ai-loading-h' : null);
    status.textContent = VQ.t('Checking the invitation…');
    call('organizer.validate-invite', { token: link.token, email: link.email }).then(function (r) {
      var d = r.data || {}, m = d.member || {}, o = d.organizer || {};
      var org = txt(o.name) || txt(o.company_name), company = txt(o.company_name), name = txt(m.name), email = txt(m.email) || link.email, role = ROLES[m.role] || '';
      reuse = !!d.has_existing_password;
      invite = { org: org, email: email };
      $('ai-org').textContent = org || VQ.t('Viaqui operator');
      $('ai-company').textContent = company;
      $('ai-company').hidden = !company || company === org;
      $('ai-name').textContent = name;
      $('ai-name-row').hidden = !name;
      $('ai-email').textContent = email;
      $('ai-role').textContent = role || VQ.t('Team member');
      $('ai-username').value = email;
      $('ai-lead').textContent = (role
        ? VQ.t('{org} added you to the team, as {role}.', { org: org || VQ.t('An operator'), role: role })
        : VQ.t('{org} added you to the team.', { org: org || VQ.t('An operator') }))
        + ' ' + (reuse ? VQ.t('Confirm and the account is active right away.') : VQ.t('Choose a password and the account is active right away.'));
      usePassword(!reuse);
      show('ai-form-view', focus ? 'ai-form-h' : null);
      status.textContent = VQ.t('The invitation is valid.');
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
    else if (pass.value === pass2.value) hint(match, VQ.t('✓ The passwords match'), 'ok');
    else hint(match, VQ.t('The passwords do not match'), 'bad');
  }
  pass.addEventListener('input', function () {
    var p = pass.value, s = score(p);
    meter.setAttribute('data-score', String(s));
    if (!p) hint(strength, '');
    else if (p.length < 8) hint(strength, VQ.t('Too short · at least 8 characters'), 'bad');
    else hint(strength, STRENGTH[s][0], STRENGTH[s][1]);
    checkMatch();
  });
  pass2.addEventListener('input', checkMatch);

  [].forEach.call(document.querySelectorAll('[data-toggle-pass]'), function (btn) {
    btn.addEventListener('click', function () {
      visible = !visible;
      [pass, pass2].forEach(function (el) { el.type = visible ? 'text' : 'password'; });
      [].forEach.call(document.querySelectorAll('[data-toggle-pass]'), function (b) {
        b.textContent = visible ? VQ.t('hide') : VQ.t('show');
        b.setAttribute('aria-pressed', String(visible));
        b.setAttribute('aria-label', visible ? VQ.t('Hide password') : VQ.t('Show password'));
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
      if (!pass.value) { say(VQ.t('Choose a password for the account.'), pass); return; }
      if (pass.value.length < 8) { say(VQ.t('The password must have at least 8 characters.'), pass); return; }
      if (pass.value !== pass2.value) { say(VQ.t('The passwords do not match.'), pass2); return; }
      body.password = pass.value;
      body.password_confirmation = pass2.value;
    }
    if (ph && (!/^\+?[\d\s().\/-]+$/.test(ph) || ph.replace(/\D/g, '').length < 6)) { say(VQ.t('Enter a valid phone number or leave the field empty.'), phone); return; }
    if (ph) body.phone = ph;

    busy = true;
    submit.disabled = true;
    submit.textContent = VQ.t('Activating…');
    call('organizer.accept-invite', null, body).then(function (r) {
      var d = r.data || {}, reused = !!d.reused_existing_password;
      forget();
      pass.value = pass2.value = '';
      // one whole sentence per case; the two names are escaped and set in bold
      var who = { org: '<strong>' + esc(txt(d.organizer_name) || invite.org || VQ.t('the operator')) + '</strong>', email: '<strong>' + esc(invite.email) + '</strong>' };
      // core keeps the password this e-mail already has in another team, even when one was typed here
      $('ai-done-p').innerHTML = !reused
        ? VQ.t('Sign in to the account of {org} with the email {email} and the password you chose.', who)
        : body.password
          ? VQ.t('Sign in to the account of {org} with the email {email} and the password you already had on Viaqui, not the one you chose now.', who)
          : VQ.t('Sign in to the account of {org} with the email {email} and the password you already use on Viaqui.', who);
      $('ai-login').href = VQ.url('/login') + '?ca=venue&email=' + encodeURIComponent(invite.email);
      status.textContent = VQ.t('The account is active.');
      show('ai-done-view', 'ai-done-h');
    }).catch(function (err) {
      var s = err && err.status, errors = (err && err.data && err.data.errors) || {};
      if (s === 400 || s === 404 || (s === 422 && (errors.token || errors.email))) { bad('invalid', true); return; }
      if (s === 422 && errors.password) {
        // the password this e-mail had elsewhere is gone since the check: ask for one
        if (reuse) { reuse = false; usePassword(true); say(VQ.t('Choose a password for the account: we could not find your Viaqui password any more.'), pass); return; }
        if (/confirm/i.test(String(errors.password[0] || ''))) say(VQ.t('The passwords do not match.'), pass2);
        else say(VQ.t('The password must have at least 8 characters.'), pass);
        return;
      }
      if (s === 422 && errors.phone) { say(VQ.t('The phone number can have at most 30 characters.'), phone); return; }
      if (s === 429) say(VQ.t('Too many attempts. Try again in a minute.'));
      else if (s === 0) say(VQ.t('We could not connect. Check your internet and try again.'));
      else say(VQ.t('We could not activate the account right now. Try again in a few moments.'));
    }).then(function () {
      busy = false;
      submit.disabled = false;
      submit.textContent = LABEL;
    });
  });

  check(false);
})();
