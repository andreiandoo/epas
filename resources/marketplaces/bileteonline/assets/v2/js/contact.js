/* bilete.online v2: contact form. The reason sets the reference label and the placeholders, reason and priority set the
   routing note, and links marked data-reason preselect a reason. Sending maps the form onto what core validates
   (first_name, last_name, email, phone, subject, order_id, message, website_url): the reason picks core's subject, and the
   visitor's own subject line, the priority and a non-order reference go at the top of the message so nothing typed is
   lost. Failures are said, with the email address as a way out; "sent" shows only when core accepted the message. */
(function () {
  'use strict';
  var $ = function (id) { return document.getElementById(id); };
  var form = $('ct-form');
  if (!form) return;

  var data = {};
  try { data = JSON.parse($('v2-data').textContent) || {}; } catch (e) {}
  var REASONS = data.reasons || {}, PRIORITIES = data.priorities || {}, SUPPORT = data.supportEmail || '', MAX = data.messageMax || 4500;
  var f = {
    reason: $('ct-reason'), priority: $('ct-priority'), first: $('ct-first'), last: $('ct-last'), email: $('ct-email'), phone: $('ct-phone'),
    ref: $('ct-ref'), subject: $('ct-subject'), message: $('ct-message'), consent: $('ct-consent'), trap: $('ct-website')
  };
  var sent = $('ct-sent'), error = $('ct-error'), submit = $('ct-submit'), count = $('ct-count');
  var LABEL = submit.textContent, busy = false;
  var FIELD_ERRORS = {
    first_name: ['Completează prenumele.', 'first'], last_name: ['Completează numele de familie.', 'last'],
    email: ['Adresa de email nu pare corectă. Verific-o și încearcă din nou.', 'email'], phone: ['Numărul de telefon este prea lung.', 'phone'],
    order_id: ['Numărul comenzii este prea lung.', 'ref'], message: ['Mesajul lipsește sau este prea lung.', 'message'], subject: ['Alege motivul contactului.', 'reason']
  };

  function reason() { return REASONS[f.reason.value] || REASONS.other || {}; }

  // ---------- hints that follow the choices ----------
  function updateRouting() {
    var p = f.priority.value, title, text;
    if (p === 'today') { title = 'Solicitare cu activitate azi'; text = 'Include ora activității și numărul comenzii. Acest tip de solicitare este prioritizat.'; }
    else if (p === 'access') { title = 'Solicitare legată de acces / intrare'; text = 'Include numele locației, ora activității și o captură cu biletul sau eroarea.'; }
    else if (p === 'payment') { title = 'Solicitare legată de plată'; text = 'Include metoda de plată, ora plății și orice mesaj primit de la procesator.'; }
    else if (f.reason.value === 'venue') { title = 'Mesaj direcționat către zona B2B / locații'; text = 'Include orașul, tipul locației și ce activități vrei să vinzi online.'; }
    else { title = 'Mesaj direcționat către suport'; text = 'Include detalii clare ca solicitarea să poată fi procesată rapid.'; }
    $('ct-route').setAttribute('data-tone', p === 'today' || p === 'access' ? 'urgent' : 'calm');
    $('ct-route-t').textContent = title;
    $('ct-route-p').textContent = text;
  }
  function updateReason() {
    var r = reason();
    $('ct-ref-label').textContent = r.ref || 'Referință opțională';
    f.ref.placeholder = r.refPh || '';
    f.subject.placeholder = r.subjectPh || '';
    f.message.placeholder = r.messagePh || '';
    updateRouting();
  }
  function updateCount() {
    var n = f.message.value.length;
    count.textContent = n + ' / ' + MAX;
    count.classList.toggle('is-near', n >= MAX * 0.9);
  }
  f.reason.addEventListener('change', updateReason);
  f.priority.addEventListener('change', updateRouting);
  f.message.addEventListener('input', updateCount);
  [].forEach.call(document.querySelectorAll('a[data-reason]'), function (a) {
    a.addEventListener('click', function () {
      if (!REASONS[a.getAttribute('data-reason')]) return;
      f.reason.value = a.getAttribute('data-reason');
      updateReason();
    });
  });
  [f.first, f.last, f.email, f.subject, f.message, f.phone, f.ref, f.consent].forEach(function (el) {
    el.addEventListener(el === f.consent ? 'change' : 'input', function () { el.removeAttribute('aria-invalid'); });
  });

  // ---------- messages ----------
  function clearInvalid() {
    [f.first, f.last, f.email, f.subject, f.message, f.phone, f.ref, f.consent, f.reason].forEach(function (el) { el.removeAttribute('aria-invalid'); });
  }
  function fail(text, fields, withEmail) {
    sent.hidden = true;
    error.textContent = text;
    if (withEmail && SUPPORT) {
      var a = document.createElement('a');
      a.href = 'mailto:' + SUPPORT;
      a.textContent = SUPPORT;
      error.appendChild(document.createTextNode(' Dacă problema persistă, scrie-ne la '));
      error.appendChild(a);
      error.appendChild(document.createTextNode('.'));
    }
    error.hidden = false;
    clearInvalid();
    (fields || []).forEach(function (el) { el.setAttribute('aria-invalid', 'true'); });
    if (fields && fields[0]) fields[0].focus();
    else error.focus();
  }

  // ---------- send ----------
  form.addEventListener('submit', function (e) {
    e.preventDefault();
    if (busy) return;
    error.hidden = true;
    ['first', 'last', 'email', 'phone', 'ref', 'subject'].forEach(function (k) { f[k].value = f[k].value.trim(); });

    var missing = [f.first, f.last, f.email, f.subject].filter(function (el) { return !el.value; });
    if (!f.message.value.trim()) missing.push(f.message);
    if (missing.length) { fail('Te rugăm să completezi toate câmpurile obligatorii.', missing); return; }
    if (!f.email.checkValidity()) { fail('Adresa de email nu pare corectă. Verific-o și încearcă din nou.', [f.email]); return; }
    if (!f.consent.checked) { fail('Te rugăm să bifezi acceptul.', [f.consent]); return; }
    if (typeof BileteOnlineAPI === 'undefined') { fail('Nu am putut trimite mesajul acum.', null, true); return; }

    var r = reason(), priority = f.priority.value, ref = f.ref.value;
    var head = ['Subiect: ' + f.subject.value, 'Motiv: ' + (r.label || f.reason.value)];
    if (priority !== 'normal') head.push('Prioritate: ' + (PRIORITIES[priority] || priority));
    if (ref && !r.orderRef) head.push((r.ref || 'Referință').replace(/ opțional$/, '') + ': ' + ref);
    var payload = {
      first_name: f.first.value,
      last_name: f.last.value,
      email: f.email.value,
      phone: f.phone.value,
      subject: r.subject || 'altele',
      order_id: r.orderRef ? ref : '',
      message: head.join('\n') + '\n\n' + f.message.value.trim(),
      website_url: f.trap.value
    };

    busy = true;
    submit.disabled = true;
    submit.textContent = 'Se trimite…';
    BileteOnlineAPI.post('/contact', payload)
      .then(function (resp) {
        if (!(resp && resp.success !== false)) throw { status: -1 };
        ['first', 'last', 'email', 'phone', 'ref', 'subject', 'message'].forEach(function (k) { f[k].value = ''; });
        f.consent.checked = false;
        clearInvalid();
        updateCount();
        sent.hidden = false;
        sent.focus();
      })
      .catch(function (err) {
        var status = err && err.status, errors = (err && err.data && err.data.errors) || {};
        if (status === 422 && Object.keys(errors).length) {
          var keys = Object.keys(errors).filter(function (k) { return FIELD_ERRORS[k]; });
          if (keys.length) { fail(FIELD_ERRORS[keys[0]][0], keys.map(function (k) { return f[FIELD_ERRORS[k][1]]; })); return; }
        }
        if (status === 429) fail('Prea multe mesaje într-un timp scurt. Încearcă din nou peste un minut.');
        else if (status === 0) fail('Nu ne-am putut conecta. Verifică internetul și încearcă din nou.', null, true);
        else fail('Nu am putut trimite mesajul acum. Încearcă din nou în câteva minute.', null, true);
      })
      .then(function () {
        busy = false;
        submit.disabled = false;
        submit.textContent = LABEL;
      });
  });

  // links from other pages say why: /contact?motiv=retur (comanda, retur, locatie, …) opens the form on that reason
  var MOTIV = { comanda: 'order', bilete: 'order', retur: 'refund', rambursare: 'refund', card: 'gift', voucher: 'gift', 'card-cadou': 'gift',
    locatie: 'venue', organizator: 'venue', parteneriat: 'partnership', presa: 'press', altele: 'other' };
  try {
    var motiv = String(new URLSearchParams(window.location.search).get('motiv') || '').toLowerCase();
    var picked = MOTIV[motiv] || (REASONS[motiv] ? motiv : '');
    if (picked) {
      f.reason.value = picked;
      // a gift card configured on /card-cadou (gift.js) arrives written out; typed text is never overwritten
      var pre = JSON.parse(localStorage.getItem('bo_contact_prefill') || 'null');
      if (pre && pre.v === 1 && pre.reason === picked && Date.now() - Number(pre.ts) < 3600 * 1000) {
        if (!f.subject.value && typeof pre.subject === 'string') f.subject.value = pre.subject.slice(0, Number(f.subject.getAttribute('maxlength')) || 150);
        if (!f.message.value && typeof pre.message === 'string') f.message.value = pre.message.slice(0, MAX);
        localStorage.removeItem('bo_contact_prefill');
      }
      if (!window.location.hash) {
        window.addEventListener('load', function () { $('formular').scrollIntoView({ block: 'start' }); });
      }
    }
  } catch (e) {}

  updateReason();
  updateCount();
})();
