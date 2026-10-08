/* viaqui.com v2: gift card balance check. Posts the code (and PIN) through the API proxy, then turns the check
   card into the result: balance, validity, status, and why a card can't be used. Every error is said in words. */
(function () {
  'use strict';
  var $ = function (id) { return document.getElementById(id); };
  var form = $('vc-form');
  if (!form) return;

  var formView = $('vc-form-view'), result = $('vc-result'), error = $('vc-error');
  var code = $('vc-code'), pin = $('vc-pin'), submit = $('vc-submit');
  var LABEL = submit.textContent, sending = false;
  var LOCALE = VQ.locale === 'en' ? 'en-GB' : VQ.locale;
  var STATUS = { active: VQ.t('Active'), usable: VQ.t('Active'), pending: VQ.t('Pending'), used: VQ.t('Fully used'), depleted: VQ.t('Fully used'), redeemed: VQ.t('Fully used'), expired: VQ.t('Expired'), cancelled: VQ.t('Cancelled'), canceled: VQ.t('Cancelled'), suspended: VQ.t('Suspended'), blocked: VQ.t('Blocked') };
  var money = new Intl.NumberFormat(LOCALE, { minimumFractionDigits: 2, maximumFractionDigits: 2 });

  function showError(text, field) {
    error.textContent = text;
    error.hidden = false;
    [code, pin].forEach(function (el) { el.removeAttribute('aria-invalid'); });
    if (field) { field.setAttribute('aria-invalid', 'true'); field.focus(); }
  }
  function formatMoney(n) {
    return n == null || n === '' ? '—' : money.format(Number(n) || 0);
  }
  function formatDate(value) {
    var d = value ? new Date(value) : null;
    return d && !isNaN(d) ? d.toLocaleDateString(LOCALE, { day: 'numeric', month: 'long', year: 'numeric' }) : '—';
  }
  function expiryLabel(days) {
    if (days == null) return '';
    days = Math.floor(Number(days));
    if (days < 0) return VQ.t('expired');
    if (days === 0) return VQ.t('expires today');
    return VQ.t('{days} left', { days: VQ.n(days, 'day', 'days') });
  }
  function statusLabel(data) {
    return STATUS[String(data.status || '').toLowerCase()] || data.status_label || data.status || '—';
  }
  function unusableReason(data) {
    if (data.balance == null || Number(data.balance) <= 0) return VQ.t('The card has no balance left.');
    if (data.days_until_expiry != null && Number(data.days_until_expiry) < 0) return VQ.t('The card has expired.');
    var status = String(data.status || '').toLowerCase();
    if (status && status !== 'active' && status !== 'usable') return VQ.t('Current status: {status}.', { status: statusLabel(data) });
    return VQ.t('Contact support for details.');
  }

  function render(data) {
    var usable = !!data.is_usable;
    var currency = data.currency || 'EUR';
    result.setAttribute('data-state', usable ? 'ok' : 'bad');
    $('vc-r-kicker').textContent = usable ? VQ.t('Valid card') : VQ.t('Card unavailable');
    $('vc-r-code').textContent = data.code || code.value.trim().toUpperCase();
    $('vc-r-balance').textContent = formatMoney(data.balance);
    $('vc-r-currency').textContent = currency;
    $('vc-r-expires').textContent = formatDate(data.expires_at);
    var label = $('vc-r-expiry-label');
    label.textContent = expiryLabel(data.days_until_expiry);
    label.className = data.days_until_expiry != null && Number(data.days_until_expiry) <= 30 ? 'is-soon' : '';
    $('vc-r-status').textContent = statusLabel(data);
    $('vc-r-initial').textContent = formatMoney(data.initial_amount) + ' ' + currency;
    $('vc-r-reason').textContent = usable ? '' : unusableReason(data);
    formView.hidden = true;
    result.hidden = false;
    $('vc-r-code').focus();
  }

  form.addEventListener('submit', function (e) {
    e.preventDefault();
    if (sending) return;
    error.hidden = true;
    var value = code.value.trim().toUpperCase();
    code.value = value;
    if (!value) { showError(VQ.t('Enter the card code.'), code); return; }
    if (typeof BileteOnlineAPI === 'undefined') { showError(VQ.t('The card cannot be checked right now. Please try again.')); return; }

    var payload = { code: value };
    if (pin.value.trim()) payload.pin = pin.value.trim();
    sending = true;
    submit.disabled = true;
    submit.textContent = VQ.t('Checking…');

    BileteOnlineAPI.post('/customer/gift-cards/check-balance', payload)
      .then(function (resp) {
        if (resp && resp.success && resp.data) { render(resp.data); return; }
        showError(VQ.t('The card cannot be checked right now. Please try again.'));
      })
      .catch(function (err) {
        var status = err && err.status;
        if (status === 404) showError(VQ.t('The code is not valid or the card does not exist.'), code);
        else if (status === 403) showError(payload.pin ? VQ.t('Wrong PIN. Check the PIN on the physical card.') : VQ.t('This card has a PIN. Enter the PIN from the back of the card.'), pin);
        else if (status === 422) showError(VQ.t('Enter the card code.'), code);
        else if (status === 429) showError(VQ.t('Too many attempts. Try again in a minute.'));
        else if (status === 0) showError(VQ.t('We could not connect. Check your internet connection and try again.'));
        else showError(VQ.t('The card cannot be checked right now. Please try again.'));
      })
      .then(function () {
        sending = false;
        submit.disabled = false;
        submit.textContent = LABEL;
      });
  });

  $('vc-again').addEventListener('click', function () {
    form.reset();
    code.value = '';
    error.hidden = true;
    [code, pin].forEach(function (el) { el.removeAttribute('aria-invalid'); });
    result.hidden = true;
    formView.hidden = false;
    code.focus();
  });
})();
