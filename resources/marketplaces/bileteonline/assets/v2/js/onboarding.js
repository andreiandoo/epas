/* bilete.online v2: venue signup, three steps. Each step is checked before moving on and says what's missing (the old
   form greyed the button out and said nothing). Step 3 asks for the company's CUI and checks it at ANAF (proxy
   organizer.verify-cui): the company data is shown read-only, never typed, and a struck-off company can't go on; when
   ANAF doesn't answer the request still goes, with the CUI for core to check again. The city is picked from the site's
   own list (#v2-data cities), searched without diacritics. The request goes to the lead pipeline (proxy leads.create →
   core LeadsController::create), which with the password from step 2 also creates the organizer account (pending until
   bilete.online approves it): the page keeps the session the way auth.js does and the thank-you screen leads into the
   account. Core's English errors are said in Romanian and the form goes back to the step that holds the field. The page pings the funnel (leads.track page_view_onboarding) on the bo_lead_sid session shared with
   /devino-partener, and keeps the UTM captured in <head>. */
(function () {
  'use strict';
  var $ = function (id) { return document.getElementById(id); };
  var form = $('ob-form');
  if (!form) return;

  var SUPPORT = '', CITIES = [];
  try {
    var data = JSON.parse($('v2-data').textContent);
    SUPPORT = data.supportEmail || '';
    CITIES = Array.isArray(data.cities) ? data.cities : [];
  } catch (e) {}
  var steps = [].slice.call(form.querySelectorAll('.ob-step'));
  var dots = [].slice.call(document.querySelectorAll('.ob-dots li'));
  var LABELS = ['Activitate', 'Tu', 'Locație'];
  var f = { other: $('ob-other'), name: $('ob-name'), email: $('ob-email'), phone: $('ob-phone'), password: $('ob-password'), venue: $('ob-venue'), cui: $('ob-cui'), city: $('ob-city'),
    website: $('ob-website'), volume: $('ob-volume'), notes: $('ob-notes'), gdpr: $('ob-gdpr'), trap: $('ob-fax') };
  var error = $('ob-error'), submit = $('ob-submit'), SUBMIT_HTML = submit.innerHTML;
  var current = 1, busy = false;
  // core field → [message, field, step]
  var FIELD_ERRORS = {
    contact_name: ['Completează numele și prenumele.', 'name', 2], email: ['Adresa de email nu pare corectă. Verific-o și încearcă din nou.', 'email', 2],
    phone: ['Numărul de telefon este prea lung.', 'phone', 2], password: ['Parola trebuie să aibă cel puțin 8 caractere.', 'password', 2],
    location_name: ['Completează numele locației sau al organizației.', 'venue', 3],
    cui: ['CUI-ul nu pare corect. Verifică cifrele.', 'cui', 3], city: ['Alege orașul din listă.', 'city', 3], website: ['Adresa site-ului este prea lungă.', 'website', 3],
    notes: ['Mesajul este prea lung.', 'notes', 3], category_other: ['Descrierea activității este prea lungă.', 'other', 1]
  };
  var fold = function (s) { return String(s || '').toLowerCase().normalize('NFD').replace(/[̀-ͯ]/g, '').replace(/\s+/g, ' ').trim(); };
  var esc = function (s) { return String(s).replace(/[&<>"']/g, function (c) { return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]; }); };

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

  // ---------- CUI → ANAF ----------
  var cuiBtn = $('ob-cui-btn'), cuiMsg = $('ob-cui-msg'), company = $('ob-company');
  var CUI_BTN_HTML = cuiBtn.innerHTML, CUI_HINT = cuiMsg.textContent;
  // status: idle | checking | ok | notfound | deregistered | unavailable
  var cui = { digits: '', status: 'idle', data: null, promise: null }, cuiTimer = 0;
  function cuiDigits() { return f.cui.value.toUpperCase().replace(/\s+/g, '').replace(/^RO/, '').replace(/[.\-]/g, ''); }
  // the Romanian CUI check digit (key 753217532)
  function cuiValid(d) {
    if (!/^\d{2,10}$/.test(d)) return false;
    var body = d.slice(0, -1), key = '753217532', sum = 0;
    while (body.length < 9) body = '0' + body;
    for (var i = 0; i < 9; i++) sum += Number(body[i]) * Number(key[i]);
    var check = sum * 10 % 11;
    return (check === 10 ? 0 : check) === Number(d.slice(-1));
  }
  function cuiMessage(text, tone) {
    cuiMsg.textContent = text;
    cuiMsg.className = 'ob-hint' + (tone ? ' is-' + tone : '');
  }
  function paintCui() {
    var s = cui.status;
    company.hidden = s !== 'ok';
    cuiBtn.disabled = s === 'checking' || s === 'ok';
    cuiBtn.classList.toggle('is-ok', s === 'ok');
    cuiBtn.innerHTML = s === 'checking' ? '<span class="ob-spin" aria-hidden="true"></span><span>Verificăm…</span>'
      : s === 'ok' ? '<svg class="ic" aria-hidden="true"><use href="#i-check"/></svg><span>Verificat</span>' : CUI_BTN_HTML;
    if (s === 'idle') cuiMessage(CUI_HINT);
    else if (s === 'checking') cuiMessage('Căutăm firma la ANAF…');
    else if (s === 'ok') cuiMessage('');
    else if (s === 'notfound') cuiMessage('Nu am găsit CUI-ul ăsta la ANAF. Verifică cifrele.', 'bad');
    else if (s === 'deregistered') cuiMessage('Firma cu acest CUI e radiată la ANAF. Folosește CUI-ul firmei care operează locația.', 'bad');
    else if (s === 'unavailable') cuiMessage('ANAF nu răspunde acum. Poți trimite cererea, verificăm noi CUI-ul.', 'warn');
  }
  function showCompany(d) {
    var place = [d.address, d.city, d.county, d.zip].map(function (x) { return String(x || '').trim(); }).filter(Boolean).join(', ');
    $('ob-co-name').textContent = d.company_name || '—';
    $('ob-co-cui').textContent = (d.vat_payer ? 'RO' : '') + (d.cui || cui.digits);
    $('ob-co-reg').textContent = d.reg_com || '—';
    $('ob-co-address').textContent = place || '—';
    $('ob-co-vat').textContent = d.vat_payer ? 'Plătitor de TVA' : 'Neplătitor de TVA';
    // ANAF says "INREGISTRAT din data 29.08.2006"
    var state = String(d.status || '').trim(), since = (state.match(/din data\s+(\S+)/i) || [])[1];
    state = state.replace(/\s+din data.*$/i, '');
    state = /^inregistrat/i.test(state) || !state ? 'Înregistrată' : state.charAt(0) + state.slice(1).toLowerCase();
    $('ob-co-status').textContent = state + (since ? ' din ' + since : '') + (d.inactive ? ' · inactivă fiscal' : '');
    $('ob-co-status').classList.toggle('is-warn', !!d.inactive);
  }
  // the venue city from the company's registered office, if the visitor hasn't picked one: "Sector 6 Mun. Bucureşti" → București
  function cityFromCompany(d) {
    if (city.slug || f.city.value.trim() || !CITIES.length) return;
    var county = fold(d.county).replace(/^(municipiul|judetul)\s+/, '');
    var names = fold(d.city).replace(/\bsector\s*\d+\b/g, ' ').replace(/\b(mun|municipiul|oras|or|com|comuna)\b\.?/g, ' ').split(/\bsat\b/);
    for (var i = 0; i < names.length; i++) {
      var name = names[i].replace(/[.,]/g, ' ').replace(/\s+/g, ' ').trim();
      if (!name) continue;
      var hits = CITIES.filter(function (c) { return fold(c[1]) === name; });
      if (hits.length > 1) hits = hits.filter(function (c) { return fold(c[2]) === county; });
      if (hits.length === 1) { pickCity(hits[0]); return; }
    }
  }
  // asks ANAF once per CUI; `retry` (the button) asks again after ANAF didn't answer
  function verifyCui(retry) {
    var d = cuiDigits();
    if (d === cui.digits && cui.status !== 'idle' && !(retry && cui.status === 'unavailable')) return cui.promise || Promise.resolve(cui.status);
    cui = { digits: d, status: 'checking', data: null, promise: null };
    paintCui();
    var ctrl = window.AbortController ? new AbortController() : null, timer = setTimeout(function () { if (ctrl) ctrl.abort(); }, 20000);
    var mine = cui;
    mine.promise = fetch('/api/proxy.php?action=organizer.verify-cui', { method: 'POST', headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' }, body: JSON.stringify({ cui: d }), signal: ctrl ? ctrl.signal : undefined })
      .then(function (res) { return res.json().catch(function () { return {}; }).then(function (body) { return { status: res.status, body: body }; }); })
      .then(function (r) {
        var co = r.body && r.body.data;
        if (r.status === 200 && r.body.success && co) return (co.deregistered || /RADI/i.test(co.status || '')) ? ['deregistered', co] : ['ok', co];
        if (r.status === 404 || r.status === 400) return ['notfound', null];
        return ['unavailable', null];
      }, function () { return ['unavailable', null]; })
      .then(function (out) {
        clearTimeout(timer);
        if (cui !== mine) return mine.status; // the CUI changed meanwhile
        cui.status = out[0];
        cui.data = out[1];
        if (cui.status === 'ok') { showCompany(cui.data); cityFromCompany(cui.data); f.cui.removeAttribute('aria-invalid'); }
        paintCui();
        return cui.status;
      });
    return mine.promise;
  }
  f.cui.addEventListener('input', function () {
    clearTimeout(cuiTimer);
    if (cuiDigits() !== cui.digits && cui.status !== 'idle') { cui = { digits: '', status: 'idle', data: null, promise: null }; paintCui(); }
    if (cuiValid(cuiDigits())) cuiTimer = setTimeout(verifyCui, 450);
  });
  f.cui.addEventListener('blur', function () {
    var d = cuiDigits();
    if (!d || cui.status !== 'idle') return;
    if (cuiValid(d)) { clearTimeout(cuiTimer); verifyCui(); } else cuiMessage('CUI-ul nu pare corect. Verifică cifrele.', 'bad');
  });
  f.cui.addEventListener('keydown', function (e) {
    if (e.key !== 'Enter') return;
    e.preventDefault();
    cuiBtn.click();
  });
  cuiBtn.addEventListener('click', function () {
    var d = cuiDigits();
    clearTimeout(cuiTimer);
    if (!d) { cuiMessage('Scrie CUI-ul firmei, apoi verificăm.', 'bad'); f.cui.focus(); return; }
    if (!cuiValid(d)) { cuiMessage('CUI-ul nu pare corect. Verifică cifrele.', 'bad'); f.cui.setAttribute('aria-invalid', 'true'); f.cui.focus(); return; }
    verifyCui(true);
  });

  // ---------- city (from the site's list) ----------
  var list = $('ob-city-list'), cityHint = $('ob-city-hint');
  var city = { slug: '', name: '' }, options = [], active = -1;
  if (!CITIES.length) { f.city.removeAttribute('role'); f.city.removeAttribute('aria-controls'); f.city.removeAttribute('aria-autocomplete'); f.city.removeAttribute('aria-expanded'); f.city.placeholder = 'ex. Cluj-Napoca'; cityHint.hidden = true; form.querySelector('.ob-combo').classList.add('is-plain'); }
  function matches(term) {
    var t = fold(term);
    if (!t) return CITIES.slice(0, 8); // the list comes featured cities first
    var starts = [], words = [], inside = [];
    CITIES.forEach(function (c) {
      var n = fold(c[1]);
      if (n.indexOf(t) === 0) starts.push(c);
      else if ((' ' + n.replace(/-/g, ' ')).indexOf(' ' + t) !== -1) words.push(c);
      else if (n.indexOf(t) !== -1 || fold(c[2]).indexOf(t) === 0) inside.push(c);
    });
    return starts.concat(words, inside).slice(0, 60);
  }
  function highlight(name, term) {
    var t = fold(term), n = fold(name), at = t ? n.indexOf(t) : -1;
    if (at < 0) return esc(name);
    return esc(name.slice(0, at)) + '<mark>' + esc(name.slice(at, at + t.length)) + '</mark>' + esc(name.slice(at + t.length));
  }
  function renderCities() {
    var term = city.slug && f.city.value === city.name ? '' : f.city.value;
    var found = matches(term);
    list.innerHTML = found.length ? (term ? '' : '<div class="ob-combo-group" role="presentation">Orașe populare</div>') + found.map(function (c, i) {
      return '<div class="ob-combo-opt" role="option" id="ob-city-' + i + '" data-slug="' + esc(c[0]) + '" aria-selected="' + (c[0] === city.slug) + '">' +
        '<b>' + highlight(c[1], term) + '</b>' + (c[2] ? '<small>' + esc(c[2]) + '</small>' : '') + '</div>';
    }).join('') : '<div class="ob-combo-empty">Nu avem „' + esc(term.trim()) + '” în listă. Alege orașul cel mai apropiat și spune-ne în mesaj.</div>';
    options = [].slice.call(list.querySelectorAll('.ob-combo-opt'));
    active = -1;
    f.city.removeAttribute('aria-activedescendant');
  }
  function openCities() {
    if (!CITIES.length) return;
    renderCities();
    list.hidden = false;
    f.city.setAttribute('aria-expanded', 'true');
  }
  function closeCities() {
    if (list.hidden) return;
    list.hidden = true;
    f.city.setAttribute('aria-expanded', 'false');
    f.city.removeAttribute('aria-activedescendant');
  }
  function setActive(i) {
    if (!options.length) return;
    active = (i + options.length) % options.length;
    options.forEach(function (o, k) { o.classList.toggle('is-active', k === active); });
    f.city.setAttribute('aria-activedescendant', options[active].id);
    options[active].scrollIntoView({ block: 'nearest' });
  }
  function pickCity(c) {
    city = { slug: c[0], name: c[1] };
    f.city.value = c[1];
    f.city.removeAttribute('aria-invalid');
    closeCities();
  }
  function pickBySlug(slug) {
    for (var i = 0; i < CITIES.length; i++) if (CITIES[i][0] === slug) return pickCity(CITIES[i]);
  }
  // typed a name exactly (and it's the only city by that name): take it without the click
  function pickTyped() {
    if (city.slug || !CITIES.length) return;
    var t = fold(f.city.value);
    if (!t) return;
    var hits = CITIES.filter(function (c) { return fold(c[1]) === t; });
    if (hits.length === 1) pickCity(hits[0]);
  }
  if (CITIES.length) {
    f.city.addEventListener('focus', openCities);
    f.city.addEventListener('click', openCities);
    f.city.addEventListener('input', function () {
      if (city.slug && f.city.value !== city.name) city = { slug: '', name: '' };
      openCities();
    });
    f.city.addEventListener('keydown', function (e) {
      if (e.key === 'ArrowDown') { e.preventDefault(); if (list.hidden) openCities(); setActive(active + 1); }
      else if (e.key === 'ArrowUp') { e.preventDefault(); if (list.hidden) openCities(); setActive(active - 1); }
      else if (e.key === 'Enter') {
        if (!list.hidden && (active > -1 || options.length === 1)) { e.preventDefault(); pickBySlug(options[active > -1 ? active : 0].getAttribute('data-slug')); }
        else if (!city.slug) { e.preventDefault(); pickTyped(); if (!city.slug) openCities(); }
      }
      else if (e.key === 'Escape' && !list.hidden) { e.preventDefault(); closeCities(); }
      else if (e.key === 'Tab') { pickTyped(); closeCities(); }
    });
    f.city.addEventListener('blur', function () { setTimeout(function () { if (document.activeElement !== f.city) { pickTyped(); closeCities(); } }, 120); });
    list.addEventListener('pointerdown', function (e) {
      var opt = e.target.closest('.ob-combo-opt');
      e.preventDefault(); // keep the focus in the field
      if (opt) pickBySlug(opt.getAttribute('data-slug'));
    });
  }

  // ---------- steps ----------
  function stepIsValid(n) {
    if (n === 1 && !chosen() && f.other.value.trim().length < 2) { fail('Alege o categorie sau descrie pe scurt ce vinzi.', [f.other]); return false; }
    if (n === 2) {
      if (f.name.value.trim().length < 2) { fail('Completează numele și prenumele.', [f.name]); return false; }
      if (!/^[^@\s]+@[^@\s]+\.[^@\s]+$/.test(f.email.value.trim())) { fail('Adresa de email nu pare corectă. Verific-o și încearcă din nou.', [f.email]); return false; }
      if (f.password.value.length < 8) { fail(f.password.value ? 'Parola trebuie să aibă cel puțin 8 caractere.' : 'Alege o parolă pentru contul tău de operator.', [f.password]); return false; }
    }
    if (n === 3) {
      if (f.venue.value.trim().length < 2) { fail('Completează numele locației sau al organizației.', [f.venue]); return false; }
      var d = cuiDigits();
      if (!d) { fail('Completează CUI-ul firmei. Datele ei le preluăm de la ANAF.', [f.cui]); return false; }
      if (!cuiValid(d)) { fail('CUI-ul nu pare corect. Verifică cifrele.', [f.cui]); return false; }
      pickTyped();
      if (CITIES.length ? !city.slug : f.city.value.trim().length < 2) { fail(CITIES.length ? 'Alege orașul locației din listă.' : 'Completează orașul.', [f.city]); return false; }
      if (!f.gdpr.checked) { fail('Bifează acordul pentru termeni și prelucrarea datelor.', [f.gdpr]); return false; }
    }
    return true;
  }
  // the company must be known to ANAF (checked here if it wasn't yet); ANAF not answering doesn't stop the request
  function companyIsValid() {
    return verifyCui().then(function (status) {
      if (status === 'ok' || status === 'unavailable') return true;
      fail(status === 'deregistered' ? 'Firma cu acest CUI e radiată la ANAF. Folosește CUI-ul firmei care operează locația.' : 'Nu am găsit CUI-ul ăsta la ANAF. Verifică cifrele.', [f.cui]);
      return false;
    });
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

  // ---------- password: show / hide ----------
  var passBtn = $('ob-password-btn');
  passBtn.addEventListener('click', function () {
    var show = f.password.type === 'password';
    f.password.type = show ? 'text' : 'password';
    passBtn.textContent = show ? 'Ascunde' : 'Arată';
    passBtn.setAttribute('aria-pressed', String(show));
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
  // the organizer session, kept as auth.js setOrganizerSession keeps it (this page doesn't load auth.js)
  function signIn(token, organizer) {
    try {
      localStorage.setItem('bileteonline_organizer_token', token);
      localStorage.setItem('bileteonline_organizer_data', JSON.stringify(organizer || {}));
      localStorage.setItem('bileteonline_user_type', 'organizer');
      localStorage.removeItem('bileteonline_customer_token');
      localStorage.removeItem('bileteonline_customer_data');
      return true;
    } catch (e) { return false; }
  }
  function sentence(parts) { // text with the email in bold, built without innerHTML
    var p = $('ob-done-p');
    p.textContent = '';
    parts.forEach(function (x) {
      if (x && x.strong) { var b = document.createElement('strong'); b.id = 'ob-done-email'; b.textContent = x.strong; p.appendChild(b); }
      else p.appendChild(document.createTextNode(x));
    });
  }
  // account: undefined when core didn't create one (older core, the honeypot), else core's {created, reason, token, organizer}
  function done(email, account) {
    var first = f.name.value.trim().split(/\s+/)[0], note = $('ob-done-note');
    $('ob-done-h').textContent = first ? 'Mulțumim, ' + first + '!' : 'Mulțumim!';
    if (account && account.created && account.token) {
      var signed = signIn(account.token, account.organizer);
      $('ob-done-h').textContent = first ? 'Contul tău e gata, ' + first + '!' : 'Contul tău e gata!';
      sentence(['Ți-am creat contul de operator pentru ', { strong: email }, '. Poți intra chiar acum să-l explorezi și să-ți pregătești locația. Tot ce adaugi rămâne privat până când un operator bilete.online îți aprobă cererea, în maximum 24 de ore.']);
      note.textContent = signed ? 'Ți-am trimis și un email ca să confirmi adresa.' : 'Ți-am trimis și un email ca să confirmi adresa. Intră în cont cu emailul și parola alese.';
      note.hidden = false;
      $('ob-done-account').hidden = false;
    } else if (account && account.created === false) {
      sentence(['Cererea ta a ajuns la echipa bilete.online. Pentru ', { strong: email }, ' nu am putut crea contul automat, așa că ți-l activăm noi și îți scriem cu pașii următori. Dacă vrei ajutor, ne conectăm online oricând.']);
    } else {
      sentence(['Cererea ta a ajuns la echipa bilete.online. Îți scriem pe ', { strong: email }, ' cu pașii următori. Dacă vrei ajutor, ne conectăm online oricând și îi parcurgem împreună.']);
    }
    form.hidden = true;
    $('ob-step-t').textContent = 'Trimis';
    $('ob-step-l').textContent = '';
    $('ob-bar').style.width = '100%';
    dots.forEach(function (d) { d.classList.remove('is-on'); d.classList.add('is-done'); });
    $('ob-done').hidden = false;
    $('ob-done-h').focus();
  }
  function release() {
    busy = false;
    submit.disabled = false;
    submit.innerHTML = SUBMIT_HTML;
  }

  function send() {
    var cat = chosen(), website = f.website.value.trim();
    if (website && !/^[a-z][a-z0-9+.-]*:\/\//i.test(website)) website = 'https://' + website;
    var payload = {
      session_token: sessionToken(),
      contact_name: f.name.value.trim(),
      email: f.email.value.trim(),
      phone: f.phone.value.trim() || null,
      location_name: f.venue.value.trim(),
      cui: cuiDigits(),
      city: (city.name || f.city.value).trim(),
      city_slug: city.slug || null,
      website: website ? website.slice(0, 255) : null,
      category_slug: cat ? cat.value : null,
      category_name: cat ? cat.getAttribute('data-name') : null,
      category_other: f.other.value.trim() || null,
      password: f.password.value,
      terms_accepted: true,
      volume_estimate: f.volume.value || null,
      needs: [].map.call(form.querySelectorAll('input[name="needs[]"]:checked'), function (c) { return c.value; }),
      notes: f.notes.value.trim() || null,
      prefill_tip: prefillTip,
      prefill_loc: prefillLoc,
      referrer: document.referrer ? document.referrer.slice(0, 1000) : null,
      utm: UTM
    };
    submit.textContent = 'Se trimite…';
    fetch('/api/proxy.php?action=leads.create', { method: 'POST', headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' }, body: JSON.stringify(payload) })
      .then(function (res) {
        return res.json().catch(function () { return {}; }).then(function (data) { return { status: res.status, ok: res.ok, data: data }; });
      }, function () { return { status: 0, ok: false, data: {} }; })
      .then(function (r) {
        if (r.ok && r.data && r.data.success !== false) { done(payload.email, r.data.data ? r.data.data.account : undefined); return; }
        var errors = (r.data && r.data.errors) || {};
        var keys = Object.keys(errors).filter(function (k) { return FIELD_ERRORS[k]; });
        if (r.status === 422 && keys.length) {
          var first = FIELD_ERRORS[keys[0]];
          // core's own Romanian answers for the account: an operator account with this email already, another account
          // with another password
          var said = String((errors[keys[0]] || [])[0] || '');
          var own = /^Există deja/.test(said);
          if (first[2] !== current) go(first[2], false);
          fail(own ? said : first[0], keys.filter(function (k) { return FIELD_ERRORS[k][2] === first[2]; }).map(function (k) { return f[FIELD_ERRORS[k][1]]; }));
          if (own && keys[0] === 'email') {
            var login = document.createElement('a');
            login.href = '/autentificare?ca=venue';
            login.textContent = 'Intră în cont';
            error.appendChild(document.createTextNode(' '));
            error.appendChild(login);
          }
        }
        else if (r.status === 429) fail('Am primit prea multe cereri într-un timp scurt. Încearcă din nou mai târziu.', null, true);
        else if (r.status === 0) fail('Nu ne-am putut conecta. Verifică internetul și încearcă din nou.', null, true);
        else fail('Trimiterea nu a reușit. Te rugăm să încerci din nou.', null, true);
      })
      .then(release);
  }

  form.addEventListener('submit', function (e) {
    e.preventDefault();
    if (busy) return;
    if (current < 3) { if (stepIsValid(current)) go(current + 1, true); return; } // Enter in steps 1–2 moves on
    error.hidden = true;
    if (!stepIsValid(3)) return;
    if (f.trap.value) { done(f.email.value.trim()); return; }
    busy = true;
    submit.disabled = true;
    submit.textContent = 'Verificăm CUI-ul…';
    companyIsValid().then(function (ok) {
      if (ok) send();
      else release();
    });
  });
})();
