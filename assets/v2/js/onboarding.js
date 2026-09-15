/* bilete.online v2: venue signup, three steps. Each step is checked before moving on and says what's missing (the old
   form greyed the button out and said nothing). The request goes to the lead pipeline (proxy leads.create → core
   LeadsController::create); core's English errors are said in Romanian and the form goes back to the step that holds
   the field. The page pings the funnel (leads.track page_view_onboarding) on the bo_lead_sid session shared with
   /devino-partener, and keeps the UTM captured in <head>. */
(function () {
  'use strict';
  var $ = function (id) { return document.getElementById(id); };
  var form = $('ob-form');
  if (!form) return;

  var SUPPORT = '';
  try { SUPPORT = JSON.parse($('v2-data').textContent).supportEmail || ''; } catch (e) {}
  var steps = [].slice.call(form.querySelectorAll('.ob-step'));
  var dots = [].slice.call(document.querySelectorAll('.ob-dots li'));
  var LABELS = ['Activitate', 'Tu', 'Locație'];
  var f = { other: $('ob-other'), name: $('ob-name'), email: $('ob-email'), phone: $('ob-phone'), venue: $('ob-venue'), city: $('ob-city'),
    website: $('ob-website'), volume: $('ob-volume'), notes: $('ob-notes'), gdpr: $('ob-gdpr'), trap: $('ob-fax') };
  var error = $('ob-error'), submit = $('ob-submit'), SUBMIT_HTML = submit.innerHTML;
  var current = 1, busy = false;
  // core field → [message, field, step]
  var FIELD_ERRORS = {
    contact_name: ['Completează numele și prenumele.', 'name', 2], email: ['Adresa de email nu pare corectă. Verific-o și încearcă din nou.', 'email', 2],
    phone: ['Numărul de telefon este prea lung.', 'phone', 2], location_name: ['Completează numele locației sau al organizației.', 'venue', 3],
    city: ['Completează orașul.', 'city', 3], website: ['Adresa site-ului este prea lungă.', 'website', 3], notes: ['Mesajul este prea lung.', 'notes', 3],
    category_other: ['Descrierea activității este prea lungă.', 'other', 1]
  };

  function chosen() { return form.querySelector('input[name="category_slug"]:checked'); }
  function clearInvalid() { Object.keys(f).forEach(function (k) { if (f[k]) f[k].removeAttribute('aria-invalid'); }); }

  function go(n, focus) {
    current = n;
    steps.forEach(function (s) { s.hidden = Number(s.getAttribute('data-step')) !== n; });
    $('ob-step-t').textContent = 'Pasul ' + n + ' din 3';
    $('ob-step-l').textContent = LABELS[n - 1];
    $('ob-bar').style.width = (n / 3 * 100) + '%';
    dots.forEach(function (d, i) { d.classList.toggle('is-on', i === n - 1); d.classList.toggle('is-done', i < n - 1); });
    error.hidden = true;
    if (!focus) return;
    var card = $('ob-card'), top = card.getBoundingClientRect().top;
    if (top < 0 || top > window.innerHeight * 0.5) card.scrollIntoView({ block: 'start', behavior: window.matchMedia('(prefers-reduced-motion: reduce)').matches ? 'auto' : 'smooth' });
    steps[n - 1].querySelector('.ob-step-h').focus({ preventScroll: true });
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

  function stepIsValid(n) {
    if (n === 1 && !chosen() && f.other.value.trim().length < 2) { fail('Alege o categorie sau descrie pe scurt ce vinzi.', [f.other]); return false; }
    if (n === 2) {
      if (f.name.value.trim().length < 2) { fail('Completează numele și prenumele.', [f.name]); return false; }
      if (!/^[^@\s]+@[^@\s]+\.[^@\s]+$/.test(f.email.value.trim())) { fail('Adresa de email nu pare corectă. Verific-o și încearcă din nou.', [f.email]); return false; }
    }
    if (n === 3) {
      var missing = [f.venue, f.city].filter(function (el) { return el.value.trim().length < 2; });
      if (missing.length) { fail(missing[0] === f.venue ? 'Completează numele locației sau al organizației.' : 'Completează orașul.', missing); return false; }
      if (!f.gdpr.checked) { fail('Bifează acordul pentru prelucrarea datelor.', [f.gdpr]); return false; }
    }
    return true;
  }

  [].forEach.call(form.querySelectorAll('[data-next]'), function (b) {
    b.addEventListener('click', function () { if (stepIsValid(current)) go(current + 1, true); });
  });
  [].forEach.call(form.querySelectorAll('[data-prev]'), function (b) {
    b.addEventListener('click', function () { if (current > 1) go(current - 1, true); });
  });
  Object.keys(f).forEach(function (k) {
    if (!f[k] || k === 'trap') return;
    f[k].addEventListener(f[k].type === 'checkbox' ? 'change' : 'input', function () { f[k].removeAttribute('aria-invalid'); });
  });
  [].forEach.call(form.querySelectorAll('input[name="category_slug"]'), function (r) {
    r.addEventListener('change', function () { f.other.removeAttribute('aria-invalid'); error.hidden = true; });
  });

  // ---------- funnel ----------
  function sessionToken() {
    var m = document.cookie.match(/(?:^|;\s*)bo_lead_sid=([^;]+)/);
    if (m) return decodeURIComponent(m[1]);
    var sid = (window.crypto && crypto.randomUUID) ? crypto.randomUUID() : Date.now().toString(36) + Math.random().toString(36).slice(2);
    var year = new Date();
    year.setFullYear(year.getFullYear() + 1);
    document.cookie = 'bo_lead_sid=' + sid + '; expires=' + year.toUTCString() + '; path=/; SameSite=Lax';
    return sid;
  }
  var UTM = (window.BO_UTM && typeof window.BO_UTM === 'object') ? window.BO_UTM : {};
  var prefillTip = form.getAttribute('data-prefill-tip') || null, prefillLoc = form.getAttribute('data-prefill-loc') || null;
  try {
    fetch('/api/proxy.php?action=leads.track', {
      method: 'POST', headers: { 'Content-Type': 'application/json' }, keepalive: true,
      body: JSON.stringify({ session_token: sessionToken(), event_type: 'page_view_onboarding', page_url: (window.location.pathname + window.location.search).slice(0, 500),
        referrer: document.referrer ? document.referrer.slice(0, 1000) : null, prefill_tip: prefillTip, prefill_loc: prefillLoc, utm: UTM })
    }).catch(function () {});
  } catch (e) {}

  // ---------- send ----------
  function done(email) {
    var first = f.name.value.trim().split(/\s+/)[0];
    $('ob-done-h').textContent = first ? 'Mulțumim, ' + first + '!' : 'Mulțumim!';
    $('ob-done-email').textContent = email;
    form.hidden = true;
    $('ob-step-t').textContent = 'Trimis';
    $('ob-step-l').textContent = '';
    $('ob-bar').style.width = '100%';
    dots.forEach(function (d) { d.classList.remove('is-on'); d.classList.add('is-done'); });
    $('ob-done').hidden = false;
    $('ob-done-h').focus();
  }

  form.addEventListener('submit', function (e) {
    e.preventDefault();
    if (busy) return;
    if (current < 3) { if (stepIsValid(current)) go(current + 1, true); return; } // Enter in steps 1–2 moves on
    error.hidden = true;
    if (!stepIsValid(3)) return;
    if (f.trap.value) { done(f.email.value.trim()); return; }

    var cat = chosen(), website = f.website.value.trim();
    if (website && !/^[a-z][a-z0-9+.-]*:\/\//i.test(website)) website = 'https://' + website;
    var payload = {
      session_token: sessionToken(),
      contact_name: f.name.value.trim(),
      email: f.email.value.trim(),
      phone: f.phone.value.trim() || null,
      location_name: f.venue.value.trim(),
      city: f.city.value.trim(),
      website: website ? website.slice(0, 255) : null,
      category_slug: cat ? cat.value : null,
      category_name: cat ? cat.getAttribute('data-name') : null,
      category_other: f.other.value.trim() || null,
      volume_estimate: f.volume.value || null,
      notes: f.notes.value.trim() || null,
      prefill_tip: prefillTip,
      prefill_loc: prefillLoc,
      referrer: document.referrer ? document.referrer.slice(0, 1000) : null,
      utm: UTM
    };

    busy = true;
    submit.disabled = true;
    submit.textContent = 'Se trimite…';
    fetch('/api/proxy.php?action=leads.create', { method: 'POST', headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' }, body: JSON.stringify(payload) })
      .then(function (res) {
        return res.json().catch(function () { return {}; }).then(function (data) { return { status: res.status, ok: res.ok, data: data }; });
      }, function () { return { status: 0, ok: false, data: {} }; })
      .then(function (r) {
        if (r.ok && r.data && r.data.success !== false) { done(payload.email); return; }
        var errors = (r.data && r.data.errors) || {};
        var keys = Object.keys(errors).filter(function (k) { return FIELD_ERRORS[k]; });
        if (r.status === 422 && keys.length) {
          var first = FIELD_ERRORS[keys[0]];
          if (first[2] !== current) go(first[2], false);
          fail(first[0], keys.filter(function (k) { return FIELD_ERRORS[k][2] === first[2]; }).map(function (k) { return f[FIELD_ERRORS[k][1]]; }));
        }
        else if (r.status === 429) fail('Am primit prea multe cereri într-un timp scurt. Încearcă din nou mai târziu.', null, true);
        else if (r.status === 0) fail('Nu ne-am putut conecta. Verifică internetul și încearcă din nou.', null, true);
        else fail('Trimiterea nu a reușit. Te rugăm să încerci din nou.', null, true);
      })
      .then(function () {
        busy = false;
        submit.disabled = false;
        submit.innerHTML = SUBMIT_HTML;
      });
  });
})();
