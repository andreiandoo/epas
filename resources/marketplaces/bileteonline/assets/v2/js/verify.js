/* bilete.online v2: email verification. With a link (moved out of the address bar by the head script) it verifies against
   the customer or organizer endpoint, as the link's type says, and shows success or why the link failed. Without one it
   is the "check your inbox" card. Resend works for the link's email, the signed-in account's, or one typed in, and waits
   60 seconds between sends like the backend does. */
(function () {
  'use strict';
  var $ = function (id) { return document.getElementById(id); };
  var card = $('af-card');
  if (!card || !$('vf-pending')) return;

  var KEY = 'bo_verify_link', TTL = 24 * 60 * 60 * 1000, COOLDOWN = 60;
  var copy = {};
  try { copy = JSON.parse($('v2-data').textContent).copy || {}; } catch (e) {}
  var STATES = ['processing', 'success', 'error', 'pending'];

  // ---------- who and what ----------
  function readLink() {
    if (window.BO_VERIFY_LINK) return window.BO_VERIFY_LINK;
    try {
      var saved = JSON.parse(sessionStorage.getItem(KEY) || 'null');
      if (saved && saved.token && saved.email && Date.now() - (saved.at || 0) < TTL) return saved;
    } catch (e) {}
    return null;
  }
  function sessionUser() {
    try {
      if (typeof BileteOnlineAuth === 'undefined' || !BileteOnlineAuth.isLoggedIn()) return null;
      return { type: BileteOnlineAuth.getUserType() === 'organizer' ? 'organizer' : 'customer', data: BileteOnlineAuth.getUser() || {} };
    } catch (e) { return null; }
  }
  var link = readLink(), user = sessionUser();
  var type = link ? link.type : (user ? user.type : 'customer');
  var knownEmail = (link && link.email) || (user && user.data.email) || '';

  // ---------- rendering ----------
  function setHeading(el, text) {
    el.textContent = '';
    text.split(/(\s+)/).forEach(function (part) {
      if (/\S-\S/.test(part)) {
        var span = document.createElement('span');
        span.className = 'af-nw';
        span.textContent = part;
        el.appendChild(span);
      } else {
        el.appendChild(document.createTextNode(part));
      }
    });
  }
  function show(state, focusId) {
    STATES.forEach(function (s) { $('vf-' + s).hidden = s !== state; });
    if (copy[state]) { setHeading($('af-h'), copy[state][0]); $('vf-lead').textContent = copy[state][1]; }
    var h = $('vf-' + state).querySelector('.af-card-h');
    $('vf-status').textContent = h ? h.textContent : '';
    if (focusId) $(focusId).focus();
  }

  if (type === 'organizer') {
    $('vf-kicker').textContent = 'Verificare email · cont operator';
    [].forEach.call(document.querySelectorAll('[data-vf-account]'), function (a) { a.href = '/organizator/panou'; a.textContent = 'Mergi la panou'; });
    [].forEach.call(document.querySelectorAll('[data-vf-account-text]'), function (a) { a.href = '/organizator/panou'; a.textContent = 'mergi la panou'; });
  }
  $('vf-sent-to').hidden = !knownEmail;
  $('vf-sent-any').hidden = !!knownEmail;
  $('vf-user-email').textContent = knownEmail;
  $('vf-pending-field').hidden = $('vf-error-field').hidden = !!knownEmail;

  // ---------- verify ----------
  var verifying = false;
  function remember(data) {
    // refresh the stored profile of the signed-in account this link belongs to, so it shows as verified
    try {
      var fresh = data && (type === 'organizer' ? data.organizer : data.customer);
      if (!fresh || !user || user.type !== type || String(user.data.email || '').toLowerCase() !== link.email.toLowerCase()) return;
      localStorage.setItem(type === 'organizer' ? 'bileteonline_organizer_data' : 'bileteonline_customer_data', JSON.stringify(fresh));
    } catch (e) {}
  }
  function verify(byUser) {
    if (verifying) return;
    if (!link.token || !link.email) {
      $('vf-error-p').textContent = 'Linkul de verificare este incomplet. Solicită unul nou.';
      $('vf-retry').hidden = true;
      show('error', byUser ? 'vf-error-h' : null);
      return;
    }
    if (typeof BileteOnlineAPI === 'undefined') {
      $('vf-error-p').textContent = 'Nu am putut încărca sistemul de verificare. Reîncarcă pagina.';
      show('error');
      return;
    }
    verifying = true;
    show('processing');
    BileteOnlineAPI.post(type === 'organizer' ? '/organizer/verify-email' : '/customer/verify-email', { token: link.token, email: link.email })
      .then(function (resp) {
        if (!(resp && resp.success)) throw { status: -1 };
        remember(resp.data);
        show('success', byUser ? 'vf-success-h' : null);
      })
      .catch(function (err) {
        var status = err && err.status, message = String((err && err.message) || '');
        var text = 'A apărut o eroare la verificare. Reîncearcă mai târziu.', canRetry = false;
        if (status === 400 && /expired/i.test(message)) text = 'Linkul de verificare a expirat. Solicită unul nou.';
        else if (status === 400) text = 'Linkul de verificare este invalid sau a fost deja folosit. Solicită unul nou.';
        else if (status === 404) text = 'Nu am găsit un cont pentru acest email. Verifică linkul sau creează un cont.';
        else if (status === 422) text = 'Linkul de verificare este incomplet. Solicită unul nou.';
        else if (status === 429) { text = 'Prea multe încercări. Încearcă din nou peste un minut.'; canRetry = true; }
        else if (status === 0) { text = 'Nu ne-am putut conecta. Verifică internetul și încearcă din nou.'; canRetry = true; }
        else canRetry = true;
        $('vf-error-p').textContent = text;
        $('vf-retry').hidden = !canRetry;
        show('error', byUser ? 'vf-error-h' : null);
      })
      .then(function () { verifying = false; });
  }
  $('vf-retry').addEventListener('click', function () { verify(true); });

  // ---------- resend ----------
  var sending = false;
  function resender(btn, input, field, msg, note, busyText, doneText) {
    var idle = btn.textContent, timer = null;
    function say(text, focus) {
      msg.textContent = text;
      msg.hidden = false;
      input.removeAttribute('aria-invalid');
      if (focus) { input.setAttribute('aria-invalid', 'true'); input.focus(); }
    }
    btn.addEventListener('click', function () {
      if (sending || btn.disabled) return;
      msg.hidden = true;
      note.hidden = true;
      var typed = !field.hidden, email = typed ? input.value.trim() : knownEmail;
      if (typed) input.value = email;
      if (!email) { say('Introdu emailul contului.', typed); return; }
      if (typed && !input.checkValidity()) { say('Adresa de email nu pare corectă. Verific-o și încearcă din nou.', true); return; }
      if (typeof BileteOnlineAPI === 'undefined') { say('Nu am putut retrimite emailul. Reîncearcă în câteva minute.'); return; }

      sending = true;
      btn.disabled = true;
      btn.textContent = busyText;
      input.removeAttribute('aria-invalid');
      BileteOnlineAPI.post(type === 'organizer' ? '/organizer/resend-verification' : '/customer/resend-verification', { email: email })
        .then(function (resp) {
          if (!(resp && resp.success !== false)) throw { status: -1 };
          if (/already verified/i.test(resp.message || '')) { btn.disabled = false; btn.textContent = idle; show('success', 'vf-success-h'); return; }
          note.hidden = false;
          var left = COOLDOWN;
          btn.textContent = doneText(left);
          clearInterval(timer);
          timer = setInterval(function () {
            left--;
            if (left > 0) { btn.textContent = doneText(left); return; }
            clearInterval(timer);
            btn.disabled = false;
            btn.textContent = idle;
          }, 1000);
        })
        .catch(function (err) {
          var status = err && err.status;
          if (status === 429) say('Așteaptă un minut înainte să ceri alt email de verificare.');
          else if (status === 422) say('Adresa de email nu pare corectă. Verific-o și încearcă din nou.', typed);
          else if (status === 0) say('Nu ne-am putut conecta. Verifică internetul și încearcă din nou.');
          else say('Nu am putut retrimite emailul. Reîncearcă în câteva minute.');
          btn.disabled = false;
          btn.textContent = idle;
        })
        .then(function () { sending = false; });
    });
  }
  resender($('vf-pending-resend'), $('vf-pending-email'), $('vf-pending-field'), $('vf-pending-msg'), $('vf-pending-note'), 'Se trimite…',
    function (left) { return 'Trimis ✓ — poți retrimite în ' + left + ' s'; });
  resender($('vf-error-resend'), $('vf-error-email'), $('vf-error-field'), $('vf-error-msg'), $('vf-error-note'), 'Se trimite…',
    function (left) { return '✓ Email retrimis · ' + left + ' s'; });

  if (link) verify(false);
  else show('pending');
})();
