/* bilete.online v2: gift card balance check. Posts the code (and PIN) through the API proxy, then turns the check
   card into the result: balance, validity, status, and why a card can't be used. Every error is said in Romanian. */
(function () {
  'use strict';
  var $ = function (id) { return document.getElementById(id); };
  var form = $('vc-form');
  if (!form) return;

  var formView = $('vc-form-view'), result = $('vc-result'), error = $('vc-error');
  var code = $('vc-code'), pin = $('vc-pin'), submit = $('vc-submit');
  var LABEL = submit.textContent, sending = false;
  var MONTHS = ['ianuarie', 'februarie', 'martie', 'aprilie', 'mai', 'iunie', 'iulie', 'august', 'septembrie', 'octombrie', 'noiembrie', 'decembrie'];
  var STATUS = { active: 'Activ', usable: 'Activ', pending: 'În așteptare', used: 'Folosit integral', depleted: 'Folosit integral', redeemed: 'Folosit integral', expired: 'Expirat', cancelled: 'Anulat', canceled: 'Anulat', suspended: 'Suspendat', blocked: 'Blocat' };
  var money = new Intl.NumberFormat('ro-RO', { minimumFractionDigits: 2, maximumFractionDigits: 2 });

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
    return d && !isNaN(d) ? d.getDate() + ' ' + MONTHS[d.getMonth()] + ' ' + d.getFullYear() : '—';
  }
  function expiryLabel(days) {
    if (days == null) return '';
    days = Math.floor(Number(days));
    if (days < 0) return 'expirat';
    if (days === 0) return 'expiră astăzi';
    if (days === 1) return 'mai e 1 zi';
    if (days <= 30) return 'mai sunt ' + days + ' zile';
    return days + ' zile rămase';
  }
  function statusLabel(data) {
    return STATUS[String(data.status || '').toLowerCase()] || data.status_label || data.status || '—';
  }
  function unusableReason(data) {
    if (data.balance == null || Number(data.balance) <= 0) return 'Soldul cardului este 0 lei.';
    if (data.days_until_expiry != null && Number(data.days_until_expiry) < 0) return 'Cardul a expirat.';
    var status = String(data.status || '').toLowerCase();
    if (status && status !== 'active' && status !== 'usable') return 'Status curent: ' + statusLabel(data) + '.';
    return 'Contactează suportul pentru detalii.';
  }

  function render(data) {
    var usable = !!data.is_usable;
    var currency = data.currency || 'RON';
    result.setAttribute('data-state', usable ? 'ok' : 'bad');
    $('vc-r-kicker').textContent = usable ? 'Card valabil' : 'Card indisponibil';
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
    if (!value) { showError('Introdu codul cardului.', code); return; }
    if (typeof BileteOnlineAPI === 'undefined') { showError('Cardul nu poate fi verificat acum. Încearcă din nou.'); return; }

    var payload = { code: value };
    if (pin.value.trim()) payload.pin = pin.value.trim();
    sending = true;
    submit.disabled = true;
    submit.textContent = 'Se verifică…';

    BileteOnlineAPI.post('/customer/gift-cards/check-balance', payload)
      .then(function (resp) {
        if (resp && resp.success && resp.data) { render(resp.data); return; }
        showError('Cardul nu poate fi verificat acum. Încearcă din nou.');
      })
      .catch(function (err) {
        var status = err && err.status;
        if (status === 404) showError('Codul introdus nu este valid sau cardul nu există.', code);
        else if (status === 403) showError(payload.pin ? 'PIN incorect. Verifică PIN-ul de pe cardul fizic.' : 'Cardul are PIN. Introdu PIN-ul de pe spatele cardului.', pin);
        else if (status === 422) showError('Introdu codul cardului.', code);
        else if (status === 429) showError('Prea multe încercări. Încearcă din nou peste un minut.');
        else if (status === 0) showError('Nu ne-am putut conecta. Verifică internetul și încearcă din nou.');
        else showError('Cardul nu poate fi verificat acum. Încearcă din nou.');
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
