/* viaqui.com v2: forgot password. Posts the email through the API proxy and turns the card into "check your inbox".
   The backend answers alike for known and unknown emails, so a successful answer always means "sent"; a failed request
   (no connection, too many attempts, server error) is said instead of pretending. Resend waits 30 seconds.
   In organizer mode (form data-type="venue", from ?ca=venue) the email goes to /organizer/forgot-password. */
(function () {
  'use strict';
  var $ = function (id) { return document.getElementById(id); };
  var form = $('fp-form');
  if (!form) return;

  var input = $('fp-email'), submit = $('fp-submit'), error = $('fp-error');
  var formView = $('fp-form-view'), sentView = $('fp-sent-view');
  var resend = $('fp-resend'), resendError = $('fp-resend-error'), resent = $('fp-resent');
  var LABEL = submit.textContent, RESEND_LABEL = resend.textContent, COOLDOWN = 30;
  var venue = form.getAttribute('data-type') === 'venue';
  var sentTo = '', busy = false, timer = null;

  function say(box, text, field) {
    box.textContent = text;
    box.hidden = false;
    input.removeAttribute('aria-invalid');
    if (field) { field.setAttribute('aria-invalid', 'true'); field.focus(); }
  }
  function failure(err) {
    var status = err && err.status;
    if (status === 422) return VQ.t('That email address does not look right. Check it and try again.');
    if (status === 429) return VQ.t('Too many attempts. Try again in a minute.');
    if (status === 0) return VQ.t('We could not connect. Check your internet connection and try again.');
    return VQ.t('We could not send the link just now. Try again in a few moments.');
  }
  function send(email) {
    if (typeof BileteOnlineAPI === 'undefined') return Promise.reject({ status: -1 });
    return BileteOnlineAPI.post(venue ? '/organizer/forgot-password' : '/customer/forgot-password', { email: email }).then(function (resp) {
      if (!(resp && resp.success !== false)) throw { status: -1 };
      return resp;
    });
  }
  function cooldown() {
    var left = COOLDOWN;
    resend.disabled = true;
    resend.textContent = VQ.t('Resend in {n} s', { n: left });
    clearInterval(timer);
    timer = setInterval(function () {
      left--;
      if (left > 0) { resend.textContent = VQ.t('Resend in {n} s', { n: left }); return; }
      clearInterval(timer);
      resend.disabled = false;
      resend.textContent = RESEND_LABEL;
    }, 1000);
  }

  form.addEventListener('submit', function (e) {
    e.preventDefault();
    if (busy) return;
    error.hidden = true;
    var email = input.value.trim();
    input.value = email;
    if (!email) { say(error, VQ.t('Please enter your email.'), input); return; }
    if (!input.checkValidity()) { say(error, VQ.t('That email address does not look right. Check it and try again.'), input); return; }

    busy = true;
    submit.disabled = true;
    submit.textContent = VQ.t('Sending…');
    send(email)
      .then(function () {
        sentTo = email;
        $('fp-sent-email').textContent = email;
        $('fp-login').href = VQ.url('/login') + '?' + (venue ? 'ca=venue&' : '') + 'email=' + encodeURIComponent(email);
        resendError.hidden = true;
        resent.hidden = true;
        formView.hidden = true;
        sentView.hidden = false;
        cooldown();
        $('fp-sent-h').focus();
      })
      .catch(function (err) { say(error, failure(err), err && err.status === 422 ? input : null); })
      .then(function () {
        busy = false;
        submit.disabled = false;
        submit.textContent = LABEL;
      });
  });

  resend.addEventListener('click', function () {
    if (busy || resend.disabled || !sentTo) return;
    clearInterval(timer);
    busy = true;
    resendError.hidden = true;
    resent.hidden = true;
    resend.disabled = true;
    resend.textContent = VQ.t('Resending…');
    send(sentTo)
      .then(function () { resent.hidden = false; cooldown(); })
      .catch(function (err) {
        say(resendError, failure(err));
        resend.disabled = false;
        resend.textContent = RESEND_LABEL;
      })
      .then(function () { busy = false; });
  });

  $('fp-change').addEventListener('click', function () {
    clearInterval(timer);
    resend.disabled = false;
    resend.textContent = RESEND_LABEL;
    sentView.hidden = true;
    formView.hidden = false;
    input.value = sentTo;
    input.focus();
    input.select();
  });
})();
