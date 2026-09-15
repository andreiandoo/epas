/* bilete.online v2: set a new password from an emailed link. The head script has already moved token and email out of
   the address bar (window.BO_RESET_LINK, and sessionStorage for reloads). This checks the password as it's typed
   (length, strength, match), posts it through the API proxy, and shows success or the expired-link card.
   In organizer mode (form data-type="venue") it posts to /organizer/reset-password and the links keep ?ca=venue. */
(function () {
  'use strict';
  var $ = function (id) { return document.getElementById(id); };
  var form = $('rp-form');
  if (!form) return;

  var KEY = 'bo_reset_link', TTL = 24 * 60 * 60 * 1000;
  var pass = $('rp-pass'), pass2 = $('rp-pass2'), submit = $('rp-submit'), error = $('rp-error');
  var meter = $('rp-meter'), strength = $('rp-strength'), match = $('rp-match');
  var LABEL = submit.textContent, busy = false, visible = false;
  var venue = form.getAttribute('data-type') === 'venue';
  function query(email) {
    var parts = [];
    if (venue) parts.push('ca=venue');
    if (email) parts.push('email=' + encodeURIComponent(email));
    return parts.length ? '?' + parts.join('&') : '';
  }
  var STRENGTH = { 1: ['Slabă', 'bad'], 2: ['Medie', 'mid'], 3: ['Puternică', 'ok'], 4: ['Foarte puternică', 'ok'] };

  function readLink() {
    var fromUrl = window.BO_RESET_LINK;
    if (fromUrl) return fromUrl.token && fromUrl.email ? fromUrl : { token: '', email: fromUrl.email || '' };
    try {
      var saved = JSON.parse(sessionStorage.getItem(KEY) || 'null');
      if (saved && saved.token && saved.email && Date.now() - (saved.at || 0) < TTL) return saved;
    } catch (e) {}
    return null;
  }
  function forget() { try { sessionStorage.removeItem(KEY); } catch (e) {} }
  var link = readLink();

  function show(id, focusId) {
    ['rp-form-view', 'rp-done-view', 'rp-expired-view'].forEach(function (v) { $(v).hidden = v !== id; });
    if (focusId) $(focusId).focus();
  }
  function expired(focus) {
    forget();
    var email = link && link.email;
    $('rp-new-link').href = '/parola-uitata' + query(email);
    show('rp-expired-view', focus ? 'rp-expired-h' : null);
  }
  function say(text, field) {
    error.textContent = text;
    error.hidden = false;
    [pass, pass2].forEach(function (el) { el.removeAttribute('aria-invalid'); });
    if (field) { field.setAttribute('aria-invalid', 'true'); field.focus(); }
  }

  if (link && link.token && link.email) {
    $('rp-email').textContent = link.email;
    $('rp-for').hidden = false;
    $('rp-username').value = link.email;
    show('rp-form-view');
  } else {
    expired(false);
  }

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

  // ---------- save ----------
  function signOutLocally(email) {
    // core ends every session of this account; drop a stored one for the same email so the header stops showing it
    try {
      var kind = venue ? 'organizer' : 'customer';
      var data = JSON.parse(localStorage.getItem('bileteonline_' + kind + '_data') || 'null');
      if (data && String(data.email || '').toLowerCase() === email.toLowerCase()) {
        ['bileteonline_' + kind + '_token', 'bileteonline_' + kind + '_data'].forEach(function (k) { localStorage.removeItem(k); });
        if (localStorage.getItem('bileteonline_user_type') === kind) localStorage.removeItem('bileteonline_user_type');
      }
    } catch (e) {}
  }

  form.addEventListener('submit', function (e) {
    e.preventDefault();
    if (busy || !link) return;
    error.hidden = true;
    if (pass.value.length < 8) { say('Parola trebuie să aibă minim 8 caractere.', pass); return; }
    if (pass.value !== pass2.value) { say('Parolele nu coincid.', pass2); return; }
    if (typeof BileteOnlineAPI === 'undefined') { say('Nu am putut salva parola acum. Încearcă din nou în câteva momente.'); return; }

    busy = true;
    submit.disabled = true;
    submit.textContent = 'Se salvează…';
    BileteOnlineAPI.post(venue ? '/organizer/reset-password' : '/customer/reset-password', { token: link.token, email: link.email, password: pass.value, password_confirmation: pass2.value })
      .then(function (resp) {
        if (!(resp && resp.success !== false)) throw { status: -1 };
        forget();
        signOutLocally(link.email);
        pass.value = pass2.value = '';
        $('rp-login').href = '/autentificare' + query(link.email);
        show('rp-done-view', 'rp-done-h');
      })
      .catch(function (err) {
        var status = err && err.status, errors = (err && err.data && err.data.errors) || {};
        if (status === 400 || status === 404 || (status === 422 && (errors.token || errors.email))) { expired(true); return; }
        if (status === 422 && errors.password) {
          var text = String(errors.password[0] || '');
          if (/confirm/i.test(text)) say('Parolele nu coincid.', pass2);
          else say('Parola trebuie să aibă minim 8 caractere.', pass);
        }
        else if (status === 429) say('Prea multe încercări. Încearcă din nou peste un minut.');
        else if (status === 0) say('Nu ne-am putut conecta. Verifică internetul și încearcă din nou.');
        else say('Nu am putut salva parola acum. Încearcă din nou în câteva momente.');
      })
      .then(function () {
        busy = false;
        submit.disabled = false;
        submit.textContent = LABEL;
      });
  });
})();
