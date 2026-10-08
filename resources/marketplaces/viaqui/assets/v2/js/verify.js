/* viaqui.com v2: email verification. With a link (moved out of the address bar by the head script) it verifies against
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
    $('vf-kicker').textContent = VQ.t('Email verification · operator account');
    [].forEach.call(document.querySelectorAll('[data-vf-account]'), function (a) { a.href = VQ.url('/organizator/panou'); a.textContent = VQ.t('Go to dashboard'); });
    [].forEach.call(document.querySelectorAll('[data-vf-account-text]'), function (a) { a.href = VQ.url('/organizator/panou'); a.textContent = VQ.t('go to your dashboard'); });
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
      $('vf-error-p').textContent = VQ.t('This verification link is incomplete. Request a new one.');
      $('vf-retry').hidden = true;
      show('error', byUser ? 'vf-error-h' : null);
      return;
    }
    if (typeof BileteOnlineAPI === 'undefined') {
      $('vf-error-p').textContent = VQ.t('Verification could not load. Please reload the page.');
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
        var text = VQ.t('Something went wrong while verifying. Please try again later.'), canRetry = false;
        if (status === 400 && /expired/i.test(message)) text = VQ.t('This verification link has expired. Request a new one.');
        else if (status === 400) text = VQ.t('This verification link is invalid or has already been used. Request a new one.');
        else if (status === 404) text = VQ.t('We could not find an account for this email. Check the link or create an account.');
        else if (status === 422) text = VQ.t('This verification link is incomplete. Request a new one.');
        else if (status === 429) { text = VQ.t('Too many attempts. Try again in a minute.'); canRetry = true; }
        else if (status === 0) { text = VQ.t('We could not connect. Check your internet connection and try again.'); canRetry = true; }
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
      if (!email) { say(VQ.t('Enter your account email.'), typed); return; }
      if (typed && !input.checkValidity()) { say(VQ.t('That email address does not look right. Check it and try again.'), true); return; }
      if (typeof BileteOnlineAPI === 'undefined') { say(VQ.t('We could not resend the email. Try again in a few minutes.')); return; }

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
          if (status === 429) say(VQ.t('Please wait a minute before asking for another verification email.'));
          else if (status === 422) say(VQ.t('That email address does not look right. Check it and try again.'), typed);
          else if (status === 0) say(VQ.t('We could not connect. Check your internet connection and try again.'));
          else say(VQ.t('We could not resend the email. Try again in a few minutes.'));
          btn.disabled = false;
          btn.textContent = idle;
        })
        .then(function () { sending = false; });
    });
  }
  resender($('vf-pending-resend'), $('vf-pending-email'), $('vf-pending-field'), $('vf-pending-msg'), $('vf-pending-note'), VQ.t('Sending…'),
    function (left) { return VQ.t('Sent ✓ You can resend in {n} s', { n: left }); });
  resender($('vf-error-resend'), $('vf-error-email'), $('vf-error-field'), $('vf-error-msg'), $('vf-error-note'), VQ.t('Sending…'),
    function (left) { return VQ.t('✓ Email sent again · {n} s', { n: left }); });

  if (link) verify(false);
  else show('pending');
})();
