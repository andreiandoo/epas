/* bilete.online v2: venue demo request. The form used to post to /api/contact-locatii.php, which doesn't exist, so every
   request was lost. It now goes into the lead pipeline (proxy leads.create → core LeadsController::create, the same one
   /inregistrare-locatie uses): venue type becomes the category, role / number of activities / the message go into the
   notes, and the bo_lead_sid session links it to earlier visits of the partner pages. "Sent" shows only when core
   accepted it; failures are said, with the email address as a way out. The page is named in the notes from the form's
   data-lead-source (/parteneri sets it; /pentru-locatii and /vinde-bilete keep "Pentru locații"). */
(function () {
  'use strict';
  var $ = function (id) { return document.getElementById(id); };
  var form = $('fv-form');
  if (!form) return;

  var SUPPORT = '';
  try { SUPPORT = JSON.parse($('v2-data').textContent).supportEmail || ''; } catch (e) {}
  var f = { name: $('fv-name'), email: $('fv-email'), phone: $('fv-phone'), role: $('fv-role'), venue: $('fv-venue'), city: $('fv-city'),
    type: $('fv-type'), count: $('fv-count'), message: $('fv-message'), consent: $('fv-consent'), trap: $('fv-fax') };
  var error = $('fv-error'), submit = $('fv-submit'), LABEL = submit.textContent, busy = false;
  var FIELD_ERRORS = {
    contact_name: ['Completează numele persoanei de contact.', 'name'], email: ['Adresa de email nu pare corectă. Verific-o și încearcă din nou.', 'email'],
    phone: ['Numărul de telefon este prea lung.', 'phone'], location_name: ['Completează numele locației.', 'venue'],
    city: ['Completează orașul locației.', 'city'], notes: ['Mesajul este prea lung.', 'message']
  };

  function sessionToken() {
    var m = document.cookie.match(/(?:^|;\s*)bo_lead_sid=([^;]+)/);
    if (m) return decodeURIComponent(m[1]);
    var sid = (window.crypto && crypto.randomUUID) ? crypto.randomUUID() : Date.now().toString(36) + Math.random().toString(36).slice(2);
    var year = new Date();
    year.setFullYear(year.getFullYear() + 1);
    document.cookie = 'bo_lead_sid=' + sid + '; expires=' + year.toUTCString() + '; path=/; SameSite=Lax';
    return sid;
  }
  function utm() {
    // captured in <head> before head.php strips utm_* from the address bar
    if (window.BO_UTM && typeof window.BO_UTM === 'object') return window.BO_UTM;
    var out = {}, p;
    try { p = new URLSearchParams(window.location.search); } catch (e) { return out; }
    ['utm_source', 'utm_medium', 'utm_campaign', 'utm_content', 'utm_term'].forEach(function (k) { if (p.get(k)) out[k] = p.get(k).slice(0, 150); });
    return out;
  }
  function clearInvalid() {
    Object.keys(f).forEach(function (k) { if (f[k]) f[k].removeAttribute('aria-invalid'); });
  }
  function fail(text, fields, withEmail) {
    error.textContent = text;
    if (withEmail && SUPPORT) {
      var a = document.createElement('a');
      a.href = 'mailto:' + SUPPORT;
      a.textContent = SUPPORT;
      error.appendChild(document.createTextNode(' Sau scrie-ne la '));
      error.appendChild(a);
      error.appendChild(document.createTextNode('.'));
    }
    error.hidden = false;
    clearInvalid();
    (fields || []).forEach(function (el) { el.setAttribute('aria-invalid', 'true'); });
    if (fields && fields[0]) fields[0].focus();
    else error.focus();
  }
  function done(email) {
    var first = f.name.value.trim().split(/\s+/)[0];
    $('fv-done-h').textContent = first ? 'Mulțumim, ' + first + '!' : 'Mulțumim!';
    $('fv-done-email').textContent = email;
    form.hidden = true;
    $('fv-done').hidden = false;
    $('fv-done-h').focus();
  }
  [f.name, f.email, f.phone, f.venue, f.city, f.message].forEach(function (el) {
    el.addEventListener('input', function () { el.removeAttribute('aria-invalid'); });
  });
  f.consent.addEventListener('change', function () { f.consent.removeAttribute('aria-invalid'); });

  form.addEventListener('submit', function (e) {
    e.preventDefault();
    if (busy) return;
    error.hidden = true;
    ['name', 'email', 'phone', 'venue', 'city'].forEach(function (k) { f[k].value = f[k].value.trim(); });

    var missing = [f.name, f.email, f.venue, f.city].filter(function (el) { return !el.value; });
    if (missing.length) { fail('Te rugăm să completezi câmpurile obligatorii.', missing); return; }
    if (!f.email.checkValidity()) { fail('Adresa de email nu pare corectă. Verific-o și încearcă din nou.', [f.email]); return; }
    if (!f.consent.checked) { fail('Te rugăm să bifezi acordul de a fi contactat.', [f.consent]); return; }
    if (f.trap.value) { done(f.email.value); return; } // a bot: pretend, send nothing

    var typeOption = f.type.options[f.type.selectedIndex];
    var isOther = f.type.value === 'other';
    var notes = ['Solicitare demo din pagina ' + (form.getAttribute('data-lead-source') || 'Pentru locații'), 'Rol: ' + f.role.value, 'Activități: ' + f.count.value].join('\n');
    if (f.message.value.trim()) notes += '\n\n' + f.message.value.trim();
    var payload = {
      session_token: sessionToken(),
      contact_name: f.name.value,
      email: f.email.value,
      phone: f.phone.value || null,
      location_name: f.venue.value,
      city: f.city.value,
      category_slug: isOther ? null : f.type.value,
      category_name: isOther ? null : typeOption.text,
      category_other: isOther ? typeOption.text : null,
      notes: notes.slice(0, 2000),
      referrer: document.referrer ? document.referrer.slice(0, 1000) : null,
      utm: utm()
    };

    busy = true;
    submit.disabled = true;
    submit.textContent = 'Se trimite…';
    fetch('/api/proxy.php?action=leads.create', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
      body: JSON.stringify(payload)
    })
      .then(function (res) {
        return res.json().catch(function () { return {}; }).then(function (data) { return { status: res.status, ok: res.ok, data: data }; });
      }, function () { return { status: 0, ok: false, data: {} }; })
      .then(function (r) {
        if (r.ok && r.data && r.data.success !== false) { done(payload.email); return; }
        var errors = (r.data && r.data.errors) || {};
        var keys = Object.keys(errors).filter(function (k) { return FIELD_ERRORS[k]; });
        if (r.status === 422 && keys.length) fail(FIELD_ERRORS[keys[0]][0], keys.map(function (k) { return f[FIELD_ERRORS[k][1]]; }));
        else if (r.status === 429) fail('Am primit prea multe cereri într-un timp scurt. Încearcă din nou mai târziu.', null, true);
        else if (r.status === 0) fail('Nu ne-am putut conecta. Verifică internetul și încearcă din nou.', null, true);
        else fail('Nu am putut trimite solicitarea acum. Încearcă din nou în câteva minute.', null, true);
      })
      .then(function () {
        busy = false;
        submit.disabled = false;
        submit.textContent = LABEL;
      });
  });
})();
