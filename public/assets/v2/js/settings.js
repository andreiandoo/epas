/* viaqui.com v2: customer settings (/cont/setari). Seven tabs (base.js [data-tabs]); the selected tab is kept in the
   URL hash, and the older hashes (#preferinte, #securitate, #gdpr, …) still open the right one.
   - personal data: GET /customer/me, PUT /customer/profile, POST /customer/resend-verification (with the email core
     requires; the old page sent none, so every request failed);
   - security: PUT /customer/password, /customer/2fa/* (the QR is drawn locally by BO_ACCOUNT.qr), /customer/sessions;
   - preferences: categories and cities from the public lists, PUT /customer/settings {interests}, main city on the profile;
   - family: /customer/beneficiaries (a save only counts when the answer carries the beneficiary);
   - notifications: PUT /customer/settings {notification_preferences}; core replaces that object, so the older keys it
     still reads are sent back unchanged;
   - payments: /customer/payment-methods, with Stripe.js loaded only when a card is added;
   - privacy: GDPR export with status polling and the archive downloaded through the proxy (core's link is a 404),
     personalisation, cookie settings, account deletion behind a last confirmation.
   Results go to one toast (#st-flash); form errors stay under their form. Text from the API is always written as text. */
(function () {
  'use strict';
  var $ = function (id) { return document.getElementById(id); };
  if (!$('st-content') || !window.BO_ACCOUNT || typeof BileteOnlineAPI === 'undefined') return;
  var account = window.BO_ACCOUNT, API = BileteOnlineAPI;
  var reduce = !!(window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches);

  var LOC = VQ.locale === 'en' ? 'en-GB' : VQ.locale;
  var TAB_HASH = { personal: 'date-personale', security: 'securitate', preferences: 'profil-preferinte', family: 'familie', notifications: 'notificari', payments: 'plati', privacy: 'gdpr' };
  var HASH_RULES = [
    ['preferences', /preferint|preferenc|recomand|interes/],
    ['family', /famil|beneficiar/],
    ['security', /securit|parol|password|2fa|sesiun|session/],
    ['notifications', /notific|newsletter/],
    ['payments', /plat|payment|card/],
    ['privacy', /privacy|gdpr|danger|sterg|confiden|export/],
    ['personal', /date|personal|profil/]
  ];
  var NOTIF = ['tickets', 'points', 'recommendations', 'newsletter', 'reviews', 'support'];
  var NOTIF_LEGACY = ['favorites', 'history', 'marketing'];
  var LIFESTYLE = ['radius', 'budget', 'frequency', 'moment'];
  var RELATIONS = { self: VQ.t('me'), partner: VQ.t('partner'), child: VQ.t('child'), parent: VQ.t('parent'), sibling: VQ.t('brother / sister'), friend: VQ.t('friend'), other: VQ.t('other relationship') };
  var BRANDS = { visa: 'Visa', mastercard: 'Mastercard', amex: 'Amex', discover: 'Discover', maestro: 'Maestro', unionpay: 'UnionPay', jcb: 'JCB', diners: 'Diners' };
  var EMOJI = {
    'escape-rooms': '🔐', 'muzee-expozitii': '🏛️', 'parcuri-de-distractii': '🎢', 'parcuri-de-aventura': '🌲',
    'acvarii-zoo-animale': '🐠', 'ateliere-experiente-creative': '🎨', 'spa-wellness': '💆', 'sport-fitness': '🏃',
    'tururi-experiente': '🚶', 'gastronomie': '🍽️'
  };
  var FALLBACK_CATEGORIES = [
    { slug: 'escape-rooms', name: VQ.t('Escape rooms') }, { slug: 'muzee-expozitii', name: VQ.t('Museums & exhibitions') },
    { slug: 'parcuri-de-distractii', name: VQ.t('Amusement parks') }, { slug: 'parcuri-de-aventura', name: VQ.t('Adventure parks') },
    { slug: 'natura-outdoor', name: VQ.t('Nature & outdoors') }, { slug: 'acvarii-zoo-animale', name: VQ.t('Aquariums, zoos & animals') },
    { slug: 'ateliere-experiente-creative', name: VQ.t('Workshops & creative experiences') }, { slug: 'tururi-experiente-turistice', name: VQ.t('Tours & sightseeing') },
    { slug: 'educatie-invatare-experientiala', name: VQ.t('Education & learning') }, { slug: 'familie-copii', name: VQ.t('Family & kids') },
    { slug: 'corporate-grupuri', name: VQ.t('Corporate & groups') }, { slug: 'cultura-arta', name: VQ.t('Culture & art') }
  ];
  // shown only when the list of covered cities does not load (names of places are data, not translated)
  var FALLBACK_CITIES = ['Amsterdam', 'Athens', 'Barcelona', 'Berlin', 'Brussels', 'Budapest', 'Copenhagen', 'Dublin', 'Edinburgh', 'Florence', 'Lisbon', 'London', 'Madrid', 'Milan', 'Munich', 'Paris', 'Porto', 'Prague', 'Rome', 'Stockholm', 'Venice', 'Vienna', 'Warsaw', 'Zurich'];
  var PROFILE_FIELDS = { first_name: 'st-first', last_name: 'st-last', phone: 'st-phone', birth_date: 'st-birth', gender: 'st-gender', city: 'st-city' };
  var PROFILE_ERRORS = {
    first_name: VQ.t('The first name can have at most 100 characters.'), last_name: VQ.t('The last name can have at most 100 characters.'),
    phone: VQ.t('The phone number can have at most 50 characters.'), birth_date: VQ.t('The date of birth must be a valid date in the past.'),
    gender: VQ.t('Choose one of the gender options.'), city: VQ.t('Choose a city from the list.')
  };
  var BEN_FIELDS = { name: 'st-ben-name', relation: 'st-ben-relation', birth_date: 'st-ben-birth', email: 'st-ben-email', phone: 'st-ben-phone', notes: 'st-ben-notes' };
  var BEN_ERRORS = {
    name: VQ.t('The name can have at most 150 characters.'), relation: VQ.t('Choose a relationship from the list.'), birth_date: VQ.t('The date of birth cannot be in the future.'),
    email: VQ.t('The email address does not look valid.'), phone: VQ.t('The phone number is too long.'), notes: VQ.t('Notes can have at most 1,000 characters.')
  };
  var MAX_PICK = 20, MAX_BENEFICIARIES = 25, POLL_MS = 5000, POLL_LIMIT = 10 * 60000;
  var num = new Intl.NumberFormat(LOC), dec = new Intl.NumberFormat(LOC, { maximumFractionDigits: 1 });

  var state = {
    ready: false,
    profile: { first_name: '', last_name: '', email: '', phone: '', birth_date: '', gender: '', city: '' },
    savedCity: '', verified: false,
    interests: { preferred_cities: [], event_categories: [] },
    lifestyle: { radius: '', budget: '', frequency: '', moment: '' },
    notif: { tickets: true, points: true, recommendations: true, newsletter: false, reviews: true, support: true },
    notifLegacy: {}, personalization: true,
    tfa: null, sessions: null, beneficiaries: null, cards: [], stripe: { configured: false, key: '' }, gdpr: null
  };
  var cities = FALLBACK_CITIES.slice(), savedCities = [], categories = null, tfaSetup = false, benEditing = null, benOpener = null, verifyTimer = 0;
  var stripe = null, stripeKey = '', stripePromise = null, cardEl = null, cardComplete = false;
  var pollTimer = 0, pollStarted = 0, pollSlow = false, flashTimer = 0, flashSet = 0;

  // ---------- helpers ----------
  function el(tag, cls, text) { var n = document.createElement(tag); if (cls) n.className = cls; if (text != null) n.textContent = text; return n; }
  function icon(name) {
    var ns = 'http://www.w3.org/2000/svg', svg = document.createElementNS(ns, 'svg'), use = document.createElementNS(ns, 'use');
    svg.setAttribute('class', 'ic'); svg.setAttribute('aria-hidden', 'true'); use.setAttribute('href', '#i-' + name); svg.appendChild(use);
    return svg;
  }
  function button(label, cls) { var b = el('button', 'btn ' + cls, label); b.type = 'button'; return b; }
  function show(id, on) { var n = typeof id === 'string' ? $(id) : id; if (n) n.hidden = !on; }
  function obj(x) { return !!x && typeof x === 'object' && !Array.isArray(x); }
  function txt(v) {
    if (v && typeof v === 'object') v = v[VQ.locale] || v.en || v.ro || v.name || Object.keys(v).map(function (k) { return v[k]; })[0];
    return v == null || typeof v === 'object' ? '' : String(v).trim();
  }
  function strings(a) { return Array.isArray(a) ? a.map(txt).filter(function (x, i, all) { return x && all.indexOf(x) === i; }) : []; }
  function bool(v) {
    if (typeof v === 'boolean') return v;
    if (v === 1 || v === '1' || v === 'true') return true;
    if (v === 0 || v === '0' || v === 'false') return false;
    return undefined;
  }
  function pick() { for (var i = 0; i < arguments.length; i++) { var b = bool(arguments[i]); if (b !== undefined) return b; } return undefined; }
  function count(v) { var n = Number(v); return isFinite(n) && n > 0 ? Math.floor(n) : 0; }

  function pad(n) { return String(n).padStart(2, '0'); }
  function toDate(v) {
    if (!v) return null;
    var m = /^(\d{4})-(\d{2})-(\d{2})$/.exec(String(v)); // date-only values are local days, not UTC midnight
    var d = m ? new Date(+m[1], +m[2] - 1, +m[3]) : new Date(v);
    return isNaN(d.getTime()) ? null : d;
  }
  function day(v) { var d = toDate(v); return d ? d.toLocaleDateString(LOC, { day: 'numeric', month: 'short', year: 'numeric' }) : ''; }
  function dayTime(v) { var d = toDate(v); return d ? day(v) + ', ' + pad(d.getHours()) + ':' + pad(d.getMinutes()) : ''; }
  function ago(v) {
    var d = toDate(v);
    if (!d) return '';
    var s = Math.round((Date.now() - d.getTime()) / 1000);
    if (s < 60) return VQ.t('just now');
    if (s < 3600) return VQ.t('{time} ago', { time: VQ.n(Math.floor(s / 60), 'minute', 'minutes') });
    if (s < 86400) return VQ.t('{time} ago', { time: VQ.n(Math.floor(s / 3600), 'hour', 'hours') });
    if (s < 86400 * 30) return VQ.t('{time} ago', { time: VQ.n(Math.floor(s / 86400), 'day', 'days') });
    return VQ.t('on {date}', { date: day(v) });
  }
  function bytes(n) {
    n = Number(n);
    if (!(n > 0)) return '';
    var units = ['B', 'KB', 'MB', 'GB'], i = 0;
    while (n >= 1024 && i < units.length - 1) { n /= 1024; i++; }
    return dec.format(n) + ' ' + units[i];
  }
  function norm(s) { return String(s || '').toLowerCase().normalize('NFD').replace(/[̀-ͯ]/g, '').trim(); }
  function todayIso() { var d = new Date(); return d.getFullYear() + '-' + pad(d.getMonth() + 1) + '-' + pad(d.getDate()); }
  function fresh(path) { return API.request(path, { method: 'GET', noCache: true }); }
  function apiUrl() { return (window.BILETEONLINE && window.BILETEONLINE.apiUrl) || '/api/proxy.php'; }
  function token() { try { return BileteOnlineAuth.getToken() || ''; } catch (e) { return ''; } }
  function isRo(m) { return /[ăâîșțşţ]/i.test(m) || /\b(nu|este|sau|pentru|contul|parola|codul|cardul)\b/i.test(m); }
  /** A message for the customer: core's own text when it is Romanian and the site is shown in Romanian, otherwise ours. */
  function errMessage(err, fallback) {
    if (!err || !err.status) return VQ.t('We could not reach the server. Check your connection and try again.');
    if (err.status === 401) return VQ.t('Your session has expired. Sign in again and retry.');
    if (err.status === 429) return VQ.t('Too many attempts in a short time. Try again in a minute.');
    var m = String(err.message || '').trim();
    return m && m.length < 300 && isRo(m) && VQ.locale === 'ro' ? m : fallback;
  }
  function say(message, tone) {
    var f = $('st-flash');
    clearTimeout(flashTimer);
    clearTimeout(flashSet);
    f.textContent = '';
    f.classList.toggle('is-error', tone === 'error');
    flashSet = setTimeout(function () { f.textContent = message; }, 40); // emptied first, so a repeated message is announced again
    flashTimer = setTimeout(function () { f.textContent = ''; }, tone === 'error' ? 10000 : 6000);
  }
  function formError(target, message, field) {
    var p = typeof target === 'string' ? $(target) : target;
    p.textContent = message || '';
    p.hidden = !message;
    if (message && field) { field.setAttribute('aria-invalid', 'true'); field.focus(); }
  }
  function clearInvalid(root) { [].forEach.call(root.querySelectorAll('[aria-invalid]'), function (n) { n.removeAttribute('aria-invalid'); }); }
  function busy(b, on, label) {
    if (!b) return;
    if (on) {
      if (b.dataset.html == null) b.dataset.html = b.innerHTML;
      b.disabled = true;
      b.setAttribute('aria-busy', 'true');
      if (label) b.textContent = label;
      return;
    }
    b.removeAttribute('aria-busy');
    b.disabled = false;
    if (b.dataset.html != null) { b.innerHTML = b.dataset.html; delete b.dataset.html; }
  }
  /** A status line with an optional retry link. */
  function stateMsg(id, text, retry) {
    var p = $(id);
    p.textContent = text;
    p.classList.toggle('is-error', !!retry);
    p.hidden = false;
    if (!retry) return;
    var b = el('button', 'st-retry', VQ.t('Try again'));
    b.type = 'button';
    b.addEventListener('click', function () { p.textContent = VQ.t('Loading…'); p.classList.remove('is-error'); retry(); });
    p.appendChild(document.createTextNode(' '));
    p.appendChild(b);
  }
  /** Swaps a row's buttons for "question [yes] [Renunță]"; run(done) performs the action, done(false) brings the row back. */
  function confirmRow(box, question, yesLabel, run) {
    var kept = [].slice.call(box.childNodes), opener = document.activeElement;
    var q = el('span', 'st-confirm-q', question), yes = button(yesLabel, 'st-danger'), no = button(VQ.t('Cancel'), 'btn-ghost');
    function restore() {
      box.textContent = '';
      kept.forEach(function (n) { box.appendChild(n); });
      if (opener && opener.isConnected) opener.focus();
    }
    box.textContent = '';
    box.appendChild(q);
    box.appendChild(yes);
    box.appendChild(no);
    no.addEventListener('click', restore);
    yes.addEventListener('click', function () {
      busy(yes, true, VQ.t('Working…'));
      no.disabled = true;
      run(function (ok) { if (!ok && box.isConnected) restore(); });
    });
    no.focus();
  }
  function copyText(text, b) {
    if (b.dataset.copied) return;
    function manual() { say(VQ.t('We could not copy it automatically. Select the text and copy it by hand.'), 'error'); }
    if (!navigator.clipboard || !navigator.clipboard.writeText) { manual(); return; }
    navigator.clipboard.writeText(text).then(function () {
      var label = b.textContent;
      b.dataset.copied = '1';
      b.textContent = VQ.t('Copied');
      setTimeout(function () { b.textContent = label; delete b.dataset.copied; }, 2000);
    }, manual);
  }
  function guard() { show('st-content', false); show('st-guard', true); account.toLogin(); } // the message shows only while the login page loads
  function remember(u) {
    try { if (BileteOnlineAuth.updateCustomerData) BileteOnlineAuth.updateCustomerData(u); } catch (e) {}
    account.setUser(u);
  }

  // edits made while the page is still loading are not overwritten by the answer
  function touch(e) {
    var t = e.target;
    if (!t || !t.getAttribute) return;
    if (t.getAttribute('aria-invalid')) t.removeAttribute('aria-invalid');
    if (t.id && t.closest && t.closest('#st-content')) t.dataset.touched = '1';
  }
  document.addEventListener('input', touch, true);
  document.addEventListener('change', touch, true);

  // ---------- tabs ----------
  var tabList = document.querySelector('.st-tabs');
  function currentTab() { var t = tabList.querySelector('[aria-selected="true"]'); return t ? t.id.replace('st-tab-', '') : 'personal'; }
  function tabFromHash(hash) {
    var h = '';
    try { h = norm(decodeURIComponent(String(hash || '').replace(/^#/, ''))); } catch (e) { return ''; }
    if (!h) return '';
    for (var i = 0; i < HASH_RULES.length; i++) if (HASH_RULES[i][1].test(h)) return HASH_RULES[i][0];
    return '';
  }
  function revealTab(key, smooth) {
    var t = $('st-tab-' + key);
    if (!t || tabList.scrollWidth <= tabList.clientWidth) return;
    var box = tabList.getBoundingClientRect(), r = t.getBoundingClientRect();
    var left = Math.max(0, tabList.scrollLeft + (r.left - box.left) - (tabList.clientWidth - r.width) / 2);
    if (tabList.scrollTo) tabList.scrollTo({ left: left, behavior: smooth && !reduce ? 'smooth' : 'auto' });
    else tabList.scrollLeft = left;
  }
  function afterTab() {
    var key = currentTab();
    revealTab(key, true);
    try { history.replaceState(history.state, '', window.location.pathname + window.location.search + (key === 'personal' ? '' : '#' + TAB_HASH[key])); } catch (e) {}
  }
  [].forEach.call(tabList.querySelectorAll('[role="tab"]'), function (t) { t.addEventListener('click', afterTab); });
  tabList.addEventListener('keydown', function (e) { if (/^(ArrowLeft|ArrowRight|Home|End)$/.test(e.key)) afterTab(); });
  function openTab(key, scroll) {
    var t = $('st-tab-' + key);
    if (!t) return;
    t.click();
    if (!scroll) return;
    var panel = $(t.getAttribute('aria-controls'));
    if (panel) panel.scrollIntoView({ block: 'start', behavior: reduce ? 'auto' : 'smooth' });
    try { t.focus({ preventScroll: true }); } catch (e) { t.focus(); }
  }
  [].forEach.call(document.querySelectorAll('[data-open-tab]'), function (b) {
    b.addEventListener('click', function () { openTab(b.getAttribute('data-open-tab'), true); });
  });
  window.addEventListener('hashchange', function () { var k = tabFromHash(window.location.hash); if (k) openTab(k, true); });

  // ---------- personal data ----------
  function setValue(id, value) { var n = $(id); if (n && !n.dataset.touched) n.value = value; }
  function setSelect(s, value) {
    value = value == null ? '' : String(value);
    if (value && ![].some.call(s.options, function (o) { return o.value === value; })) s.appendChild(new Option(value, value));
    s.value = value;
  }
  function isoToDisplay(iso) { var m = /^(\d{4})-(\d{2})-(\d{2})/.exec(iso || ''); return m ? m[3] + '/' + m[2] + '/' + m[1] : ''; }
  function displayToIso(v) {
    v = String(v || '').trim();
    if (!v) return { iso: '' };
    var m = /^(\d{1,2})[\/.\-](\d{1,2})[\/.\-](\d{4})$/.exec(v);
    if (!m) return { error: VQ.t('Write the date of birth as day/month/year, for example 15/06/1992.') };
    var d = +m[1], mo = +m[2], y = +m[3], dt = new Date(y, mo - 1, d), today = new Date();
    today.setHours(0, 0, 0, 0);
    if (dt.getFullYear() !== y || dt.getMonth() !== mo - 1 || dt.getDate() !== d) return { error: VQ.t('That date of birth is not in the calendar. Check the day and the month.') };
    if (y < 1900) return { error: VQ.t('Check the year of birth.') };
    if (dt >= today) return { error: VQ.t('The date of birth must be in the past.') };
    return { iso: y + '-' + pad(mo) + '-' + pad(d) };
  }
  function readProfile() {
    return {
      first_name: $('st-first').value.trim(), last_name: $('st-last').value.trim(), phone: $('st-phone').value.trim(),
      birth_date: $('st-birth').value.trim(), gender: $('st-gender').value, city: state.profile.city
    };
  }

  function applyMe(u, server) {
    if (!obj(u)) return;
    var p = state.profile;
    ['first_name', 'last_name', 'email', 'phone', 'gender'].forEach(function (k) { if (server || u[k] != null) p[k] = txt(u[k]); });
    if (server || u.birth_date) p.birth_date = txt(u.birth_date).slice(0, 10);
    if ((server || u.city != null) && !$('st-city').dataset.touched && !$('st-city2').dataset.touched) p.city = txt(u.city);
    if (server) state.savedCity = txt(u.city);
    if (typeof u.email_verified === 'boolean') state.verified = u.email_verified;
    else if (server) state.verified = !!u.email_verified_at;
    setValue('st-first', p.first_name);
    setValue('st-last', p.last_name);
    setValue('st-phone', p.phone);
    setValue('st-birth', isoToDisplay(p.birth_date));
    $('st-email').value = p.email;
    if (!$('st-gender').dataset.touched) setSelect($('st-gender'), p.gender);

    var s = obj(u.settings) ? u.settings : (obj(u.preferences) ? u.preferences : null);
    if (s) {
      var np = obj(s.notification_preferences) ? s.notification_preferences : {};
      state.notif = {
        tickets: pick(np.tickets, np.reminders, s.email_reminders, true), points: pick(np.points, true),
        recommendations: pick(np.recommendations, s.email_recommendations, true), newsletter: pick(np.newsletter, s.email_newsletter, s.newsletter, false),
        reviews: pick(np.reviews, true), support: pick(np.support, true)
      };
      state.notifLegacy = {};
      NOTIF_LEGACY.forEach(function (k) { var b = bool(np[k]); if (b !== undefined) state.notifLegacy[k] = b; });
      var it = obj(s.interests) ? s.interests : {}, ls = obj(it.lifestyle) ? it.lifestyle : {};
      state.interests = { preferred_cities: strings(it.preferred_cities), event_categories: strings(it.event_categories) };
      // saved cities missing from the public list stay selectable, so removing one by mistake can be undone
      state.interests.preferred_cities.forEach(function (n) { if (savedCities.indexOf(n) === -1) savedCities.push(n); });
      LIFESTYLE.forEach(function (k) { state.lifestyle[k] = txt(ls[k]); });
      var pe = bool(s.personalization_enabled);
      state.personalization = pe === undefined ? true : pe;
    }

    renderVerified();
    renderCitySelects();
    renderCategories();
    renderSecondary();
    LIFESTYLE.forEach(function (k) { var sel = $('st-' + k); if (!sel.dataset.touched) setSelect(sel, state.lifestyle[k]); });
    NOTIF.forEach(function (k) { var c = $('st-n-' + k); if (!c.dataset.touched) c.checked = !!state.notif[k]; });
    $('st-personalization').checked = state.personalization;
    renderPersonalization();
    renderNotifCount();
    renderCompletion();
    renderSecurity();
  }

  function setReady(on) {
    state.ready = on;
    ['st-profile-save', 'st-prefs-save', 'st-notif-save', 'st-personalization'].forEach(function (id) { $(id).disabled = !on; });
    renderVerified();
  }

  function renderVerified() {
    var b = $('st-verified');
    b.textContent = '';
    if (!state.profile.email) return;
    if (state.verified) b.appendChild(icon('check'));
    b.appendChild(document.createTextNode(state.verified ? VQ.t('Email verified') : VQ.t('Email not verified')));
    b.className = 'st-verified ' + (state.verified ? 'is-ok' : 'is-bad');
    if (!verifyTimer) show('st-verify-send', state.ready && !state.verified);
  }

  function renderCompletion() {
    var p = readProfile(), it = state.interests;
    var checks = [
      [VQ.t('date of birth'), p.birth_date], [VQ.t('main city'), p.city], [VQ.t('favourite categories'), it.event_categories.length], [VQ.t('phone'), p.phone],
      [VQ.t('gender'), p.gender], [VQ.t('other cities'), it.preferred_cities.length], [VQ.t('first name'), p.first_name], [VQ.t('last name'), p.last_name]
    ];
    var missing = checks.filter(function (c) { return !c[1]; }).map(function (c) { return c[0]; });
    var pct = Math.round((checks.length - missing.length) / checks.length * 100), bar = $('st-completion-bar');
    $('st-completion').textContent = pct;
    bar.setAttribute('aria-valuenow', String(pct));
    bar.firstElementChild.style.width = pct + '%';
    $('st-missing').textContent = missing.length
      ? (missing.length > 3
        ? VQ.t('Still to fill in: {fields} and {n} more.', { fields: missing.slice(0, 3).join(', '), n: missing.length - 3 })
        : VQ.t('Still to fill in: {fields}.', { fields: missing.join(', ') }))
      : VQ.t('All the essential fields are filled in.');

    var signals = { cats: it.event_categories.length > 0, cities: !!(p.city || it.preferred_cities.length), budget: !!$('st-budget').value, frequency: !!$('st-frequency').value, moment: !!$('st-moment').value };
    var on = 0;
    Object.keys(signals).forEach(function (k) {
      var li = document.querySelector('#st-signals [data-signal="' + k + '"]');
      if (signals[k]) on++;
      if (!li) return;
      li.classList.toggle('is-on', signals[k]);
      var sr = li.querySelector('.sr') || li.appendChild(el('span', 'sr'));
      sr.textContent = signals[k] ? VQ.t(' (filled in)') : VQ.t(' (not filled in)');
    });
    var catCount = document.querySelector('#st-signals [data-signal="cats"] span:not(.sr)');
    if (catCount) catCount.textContent = it.event_categories.length ? '(' + it.event_categories.length + ')' : '';
    $('st-s-prefs').textContent = Math.round(on / 5 * 100) + '%';
  }

  $('st-birth').addEventListener('input', function (e) {
    var v = this.value, iso = /^(\d{4})-(\d{2})-(\d{2})$/.exec(v.trim());
    if (iso) this.value = iso[3] + '/' + iso[2] + '/' + iso[1];
    else if (!(e.inputType && e.inputType.indexOf('delete') === 0) && !/^\d{0,2}(\/\d{0,2}(\/\d{0,4})?)?$/.test(v)) {
      var d = v.replace(/\D/g, '').slice(0, 8);
      this.value = d.length > 4 ? d.slice(0, 2) + '/' + d.slice(2, 4) + '/' + d.slice(4) : d.length > 2 ? d.slice(0, 2) + '/' + d.slice(2) : d;
    }
    renderCompletion();
  });
  ['st-first', 'st-last', 'st-phone'].forEach(function (id) { $(id).addEventListener('input', renderCompletion); });
  $('st-gender').addEventListener('change', renderCompletion);

  $('st-profile-form').addEventListener('submit', function (e) {
    e.preventDefault();
    if (!state.ready) return;
    var form = this, p = readProfile(), save = $('st-profile-save');
    clearInvalid(form);
    formError('st-profile-error', '');
    if (!p.first_name) return formError('st-profile-error', VQ.t('Fill in your first name.'), $('st-first'));
    if (!p.last_name) return formError('st-profile-error', VQ.t('Fill in your last name.'), $('st-last'));
    if (p.phone && (p.phone.replace(/\D/g, '').length < 6 || /[^\d\s+().\/-]/.test(p.phone))) {
      return formError('st-profile-error', VQ.t('The phone number does not look valid. Use digits, spaces and, if needed, the country prefix (for example +43).'), $('st-phone'));
    }
    var birth = displayToIso(p.birth_date);
    if (birth.error) return formError('st-profile-error', birth.error, $('st-birth'));
    busy(save, true, VQ.t('Saving…'));
    API.customer.updateProfile({ first_name: p.first_name, last_name: p.last_name, phone: p.phone || null, birth_date: birth.iso || null, gender: p.gender || null, city: p.city || null })
      .then(function (resp) {
        var u = resp && resp.data && obj(resp.data.customer) ? resp.data.customer : null;
        if (u) remember(u);
        state.profile.first_name = p.first_name;
        state.profile.last_name = p.last_name;
        state.profile.phone = p.phone;
        state.profile.birth_date = birth.iso;
        state.profile.gender = p.gender;
        state.savedCity = p.city;
        if (birth.iso) $('st-birth').value = isoToDisplay(birth.iso);
        say(VQ.t('Your details were saved.'));
      }, function (err) {
        var field = err && obj(err.errors) ? Object.keys(err.errors).filter(function (k) { return PROFILE_FIELDS[k]; })[0] : null;
        if (field) formError('st-profile-error', PROFILE_ERRORS[field], $(PROFILE_FIELDS[field]));
        else formError('st-profile-error', errMessage(err, VQ.t('We could not save your details. Try again.')));
      })
      .then(function () { busy(save, false); });
  });

  $('st-verify-send').addEventListener('click', function () {
    var b = this;
    if (b.disabled || !state.profile.email) return;
    busy(b, true, VQ.t('Sending…'));
    API.customer.resendVerification(state.profile.email).then(function (resp) {
      if (resp && /already verified/i.test(String(resp.message || ''))) {
        busy(b, false);
        state.verified = true;
        renderVerified();
        renderSecurity();
        say(VQ.t('Your email is already verified.'));
        return;
      }
      say(VQ.t('We sent the verification link to your email. If it does not arrive in a few minutes, check your spam folder too.'));
      cooldown(b, 60);
    }, function (err) {
      if (err && err.status === 429) { say(VQ.t('We sent you a link a moment ago. You can ask for another one in a minute.'), 'error'); cooldown(b, 60); return; }
      busy(b, false);
      say(errMessage(err, VQ.t('We could not send the verification link. Try again.')), 'error');
    });
  });
  function cooldown(b, seconds) {
    var left = seconds;
    delete b.dataset.html;
    b.removeAttribute('aria-busy');
    b.disabled = true;
    b.textContent = VQ.t('You can send again in {n} s', { n: left });
    clearInterval(verifyTimer);
    verifyTimer = setInterval(function () {
      left--;
      if (left > 0) { b.textContent = VQ.t('You can send again in {n} s', { n: left }); return; }
      clearInterval(verifyTimer);
      verifyTimer = 0;
      b.disabled = false;
      b.textContent = VQ.t('Send verification link');
      renderVerified();
    }, 1000);
  }

  // ---------- security: password ----------
  $('st-pass-form').addEventListener('submit', function (e) {
    e.preventDefault();
    var form = this, cur = $('st-pass-current'), pw = $('st-pass-new'), conf = $('st-pass-confirm'), save = $('st-pass-save');
    clearInvalid(form);
    formError('st-pass-error', '');
    if (!cur.value) return formError('st-pass-error', VQ.t('Enter your current password.'), cur);
    if (pw.value.length < 8) return formError('st-pass-error', VQ.t('The new password must have at least 8 characters.'), pw);
    if (pw.value === cur.value) return formError('st-pass-error', VQ.t('The new password must be different from the current one.'), pw);
    if (pw.value !== conf.value) return formError('st-pass-error', VQ.t('The new passwords do not match.'), conf);
    busy(save, true, VQ.t('Updating…'));
    API.customer.changePassword(cur.value, pw.value, conf.value).then(function () {
      form.reset();
      say(VQ.t('Your password was changed.'));
    }, function (err) {
      var status = err && err.status;
      if (status === 422 && /current password/i.test(String(err.message || ''))) formError('st-pass-error', VQ.t('The current password is not correct.'), cur);
      else if (status === 422 && obj(err.errors) && err.errors.password) formError('st-pass-error', VQ.t('The new password is not accepted. Use at least 8 characters.'), pw);
      else formError('st-pass-error', errMessage(err, VQ.t('We could not change the password. Try again.')));
    }).then(function () { busy(save, false); });
  });

  // ---------- security: 2FA ----------
  function loadTfa() {
    return fresh('/customer/2fa/status').then(function (resp) {
      state.tfa = resp && obj(resp.data) ? resp.data : {};
      renderTfa();
    }, function (err) {
      if (err && err.status === 401) return;
      state.tfa = null;
      renderTfa(true);
    });
  }
  function renderTfa(failed) {
    var t = state.tfa, active = !!(t && t.two_factor_active), pending = !!(t && t.has_pending_setup), tag = $('st-2fa-tag');
    show('st-2fa-off', !active && !tfaSetup);
    show('st-2fa-setup', !active && tfaSetup);
    show('st-2fa-on', active);
    tag.textContent = failed ? VQ.t('status unavailable') : active ? VQ.t('on') : (tfaSetup || pending) ? VQ.t('setup started') : VQ.t('off');
    tag.className = 'acc-tag ' + (active ? 'is-ok' : (tfaSetup || pending) ? 'is-wait' : 'is-muted');
    $('st-2fa-start').textContent = pending ? VQ.t('Resume 2FA setup') : VQ.t('Turn on 2FA');
    if (active) {
      var n = count(t.recovery_codes_remaining), since = day(t.confirmed_at);
      $('st-2fa-summary').textContent = (since ? VQ.t('On since {date}.', { date: since }) : VQ.t('On.')) + ' '
        + (n ? VQ.t('You have {codes}.', { codes: VQ.n(n, 'unused recovery code', 'unused recovery codes') }) + (n <= 3 ? ' ' + VQ.t('Only a few are left: make new ones in good time.') : '') : VQ.t('You have no recovery codes left: make new ones now.'));
    }
    renderSecurity();
  }
  function fillCodes(id, codes) {
    var ol = $(id), list = strings(codes);
    ol.textContent = '';
    list.forEach(function (c) { ol.appendChild(el('li', null, c)); });
    return list.length;
  }
  $('st-2fa-start').addEventListener('click', function () {
    var b = this;
    busy(b, true, VQ.t('Getting ready…'));
    API.post('/customer/2fa/initiate', {}).then(function (resp) {
      var d = resp && resp.data;
      if (!obj(d) || !d.secret) { say(VQ.t('We could not start the 2FA setup. Try again.'), 'error'); return; }
      var box = $('st-2fa-qr'), svg = d.qr_url ? account.qr(String(d.qr_url), VQ.t('QR code for the authenticator app')) : null;
      box.textContent = '';
      box.appendChild(svg || el('p', 'st-note', VQ.t('The QR code cannot be shown. Enter the secret by hand.')));
      $('st-2fa-secret').textContent = String(d.secret).replace(/\s+/g, '').replace(/(.{4})/g, '$1 ').trim();
      $('st-2fa-secret-copy').dataset.secret = String(d.secret).replace(/\s+/g, '');
      show($('st-2fa-codes').closest('.st-recovery'), fillCodes('st-2fa-codes', d.recovery_codes) > 0);
      $('st-2fa-code').value = '';
      $('st-2fa-confirm').disabled = true;
      formError('st-2fa-error', '');
      show('st-2fa-newcodes', false);
      tfaSetup = true;
      renderTfa();
      $('st-2fa-setup').scrollIntoView({ block: 'nearest', behavior: reduce ? 'auto' : 'smooth' });
      try { $('st-2fa-code').focus({ preventScroll: true }); } catch (e) {}
    }, function (err) {
      if (err && err.status === 422) loadTfa();
      say(errMessage(err, VQ.t('We could not start the 2FA setup. Try again.')), 'error');
    }).then(function () { busy(b, false); renderTfa(); });
  });
  $('st-2fa-secret-copy').addEventListener('click', function () { copyText(this.dataset.secret || '', this); });
  $('st-2fa-code').addEventListener('input', function () {
    this.value = this.value.replace(/\D/g, '').slice(0, 6);
    $('st-2fa-confirm').disabled = this.value.length !== 6;
  });
  $('st-2fa-code').addEventListener('keydown', function (e) { if (e.key === 'Enter') { e.preventDefault(); if (!$('st-2fa-confirm').disabled) $('st-2fa-confirm').click(); } });
  $('st-2fa-confirm').addEventListener('click', function () {
    var b = this, code = $('st-2fa-code');
    if (code.value.length !== 6) return;
    formError('st-2fa-error', '');
    busy(b, true, VQ.t('Checking…'));
    API.post('/customer/2fa/confirm', { code: code.value }).then(function () {
      var codes = [].map.call($('st-2fa-codes').children, function (li) { return li.textContent; });
      tfaSetup = false;
      $('st-2fa-qr').textContent = '';
      $('st-2fa-secret').textContent = '';
      delete $('st-2fa-secret-copy').dataset.secret;
      busy(b, false);
      if (codes.length) {
        fillCodes('st-2fa-newcodes-list', codes);
        $('st-2fa-newcodes').querySelector('b').textContent = VQ.t('Your recovery codes');
        $('st-2fa-newcodes').querySelector('p').textContent = VQ.t('Save them somewhere safe now. We do not show them again after you close the page.');
        show('st-2fa-newcodes', true);
      }
      say(VQ.t('Two-step sign-in is on.'));
      state.tfa = Object.assign({}, state.tfa || {}, { two_factor_active: true, has_pending_setup: false, recovery_codes_remaining: codes.length });
      renderTfa();
      loadTfa();
    }, function (err) {
      busy(b, false);
      b.disabled = code.value.length !== 6;
      formError('st-2fa-error', errMessage(err, VQ.t('The code is not valid. Check the time on your phone and try again.')), code);
      code.select();
    });
  });
  $('st-2fa-cancel').addEventListener('click', function () {
    tfaSetup = false;
    renderTfa();
    $('st-2fa-start').focus();
  });

  function inlineOpen(form, on, opener) {
    form.hidden = !on;
    [].forEach.call(document.querySelectorAll('[data-inline="' + form.id + '"]'), function (b) { b.setAttribute('aria-expanded', String(on)); });
    if (on) {
      form.reset();
      formError(form.querySelector('.st-error'), '');
      clearInvalid(form);
      form.querySelector('input').focus();
    } else if (opener) opener.focus();
  }
  [].forEach.call(document.querySelectorAll('[data-inline]'), function (b) {
    var form = $(b.getAttribute('data-inline'));
    b.setAttribute('aria-controls', form.id);
    b.setAttribute('aria-expanded', 'false');
    b.addEventListener('click', function () {
      [].forEach.call(document.querySelectorAll('#st-2fa-on .st-inline'), function (f) { if (f !== form) inlineOpen(f, false); });
      inlineOpen(form, form.hidden);
    });
    [].forEach.call(form.querySelectorAll('[data-inline-close]'), function (c) { c.addEventListener('click', function () { inlineOpen(form, false, b); }); });
  });
  function tfaPasswordForm(id, endpoint, done) {
    var form = $(id), input = form.querySelector('input'), error = form.querySelector('.st-error');
    form.addEventListener('submit', function (e) {
      e.preventDefault();
      var submit = form.querySelector('[type="submit"]');
      formError(error, '');
      if (!input.value) return formError(error, VQ.t('Enter your account password.'), input);
      busy(submit, true, VQ.t('Working…'));
      API.post(endpoint, { password: input.value }).then(function (resp) {
        busy(submit, false);
        inlineOpen(form, false);
        done(resp);
      }, function (err) {
        busy(submit, false);
        formError(error, errMessage(err, VQ.t('We could not finish. Try again.')), input);
      });
    });
  }
  tfaPasswordForm('st-2fa-disable', '/customer/2fa/disable', function () {
    show('st-2fa-newcodes', false);
    state.tfa = Object.assign({}, state.tfa || {}, { two_factor_active: false, has_pending_setup: false });
    renderTfa();
    say(VQ.t('Two-step sign-in was turned off.'));
    $('st-2fa-start').focus();
    loadTfa();
  });
  tfaPasswordForm('st-2fa-regen', '/customer/2fa/recovery-codes/regenerate', function (resp) {
    var n = fillCodes('st-2fa-newcodes-list', resp && resp.data && resp.data.recovery_codes);
    $('st-2fa-newcodes').querySelector('b').textContent = VQ.t('Your new recovery codes');
    $('st-2fa-newcodes').querySelector('p').textContent = VQ.t('Keep them somewhere safe. The old ones no longer work.');
    show('st-2fa-newcodes', n > 0);
    say(VQ.t('New recovery codes were made. The old ones no longer work.'));
    loadTfa();
  });
  [].forEach.call(document.querySelectorAll('[data-copy-codes]'), function (b) {
    b.addEventListener('click', function () {
      var codes = [].map.call($(b.getAttribute('data-copy-codes')).children, function (li) { return li.textContent; });
      if (codes.length) copyText(codes.join('\n'), b);
    });
  });

  // ---------- security: sessions ----------
  function loadSessions() {
    return fresh('/customer/sessions').then(function (resp) {
      var d = resp && resp.data;
      state.sessions = (obj(d) && Array.isArray(d.sessions) ? d.sessions : []).filter(obj);
      renderSessions();
    }, function (err) {
      if (err && err.status === 401) return;
      state.sessions = null;
      show('st-sessions', false);
      stateMsg('st-sessions-state', VQ.t('We could not load the sessions.'), loadSessions);
      renderSecurity();
    });
  }
  function sessionName(s) {
    return txt(s.device) || [txt(s.browser), txt(s.os)].filter(Boolean).join(' · ') || txt(s.platform) || txt(s.name) || VQ.t('Unknown device');
  }
  function renderSessions() {
    var ul = $('st-sessions'), list = state.sessions.slice().sort(function (a, b) { return (b.is_current ? 1 : 0) - (a.is_current ? 1 : 0); });
    var frag = document.createDocumentFragment();
    list.forEach(function (s) {
      var li = el('li'), main = el('div'), actions = el('div', 'st-row-actions'), name = sessionName(s);
      var platform = txt(s.platform); // core marks browser sessions "web" and old tokens "legacy": no use to the customer
      var meta = [platform && !/^(web|legacy)$/i.test(platform) && name.indexOf(platform) === -1 ? platform : '', txt(s.ip) ? 'IP ' + txt(s.ip) : '',
        s.last_used_at ? VQ.t('last activity {when}', { when: ago(s.last_used_at) }) : ''].filter(Boolean).join(' · ');
      main.appendChild(el('b', null, name));
      if (meta) main.appendChild(el('small', null, meta));
      if (s.created_at) main.appendChild(el('small', null, VQ.t('Signed in on {date}', { date: dayTime(s.created_at) })));
      if (s.is_current) actions.appendChild(el('span', 'acc-tag is-ok', VQ.t('this device')));
      else {
        var close = button(VQ.t('Close'), 'st-danger');
        close.setAttribute('aria-label', VQ.t('Close the session {name}', { name: name }));
        close.addEventListener('click', function () {
          confirmRow(actions, VQ.t('Close this session?'), VQ.t('Yes, close'), function (done) { revokeSession(s, name, done); });
        });
        actions.appendChild(close);
      }
      li.appendChild(main);
      li.appendChild(actions);
      frag.appendChild(li);
    });
    ul.textContent = '';
    ul.appendChild(frag);
    show(ul, list.length > 0);
    if (list.length) show('st-sessions-state', false);
    else stateMsg('st-sessions-state', VQ.t('No active sessions found.'));
    document.querySelector('[data-confirm-sessions="others"]').disabled = !list.some(function (s) { return !s.is_current; });
    renderSecurity();
  }
  function revokeSession(s, name, done) {
    API.delete('/customer/sessions/' + encodeURIComponent(s.id), {}).then(function (resp) {
      done(true);
      if (resp && resp.data && resp.data.logged_out_current) { leave(VQ.t('You closed the session on this device.')); return; }
      say(VQ.t('The session “{name}” was closed.', { name: name }));
      loadSessions().then(focusSessions);
    }, function (err) {
      if (err && err.status === 404) { done(true); say(VQ.t('The session was already closed.')); loadSessions().then(focusSessions); return; }
      done(false);
      say(errMessage(err, VQ.t('We could not close the session. Try again.')), 'error');
    });
  }
  function focusSessions() {
    var next = document.querySelector('#st-sessions .st-danger');
    (next || document.querySelector('[data-confirm-sessions="all"]')).focus();
  }
  function leave(message) {
    say(message + ' ' + VQ.t('Taking you to sign in…'));
    try { BileteOnlineAuth.clearCustomerSession(); } catch (e) {}
    setTimeout(function () { window.location.href = VQ.url('/login') + '?redirect=' + encodeURIComponent(VQ.url('/account/settings')); }, 1200);
  }
  var sessionsMode = '', sessionsOpener = null;
  [].forEach.call(document.querySelectorAll('[data-confirm-sessions]'), function (b) {
    b.addEventListener('click', function () {
      sessionsMode = b.getAttribute('data-confirm-sessions');
      sessionsOpener = b;
      $('st-sessions-confirm-t').textContent = sessionsMode === 'all'
        ? VQ.t('We will sign you out on every device, this one included. You will have to sign in again.')
        : VQ.t('We will close all the other sessions. You stay signed in only on this device.');
      show('st-sessions-confirm', true);
      $('st-sessions-no').focus();
    });
  });
  $('st-sessions-no').addEventListener('click', function () {
    show('st-sessions-confirm', false);
    if (sessionsOpener) sessionsOpener.focus();
  });
  $('st-sessions-yes').addEventListener('click', function () {
    var b = this, all = sessionsMode === 'all';
    busy(b, true, VQ.t('Closing sessions…'));
    $('st-sessions-no').disabled = true;
    API.delete('/customer/sessions/all', {}).then(function (resp) {
      if (all) return null;
      var n = count(resp && resp.data && resp.data.revoked);
      say(n ? VQ.t('Closed: {sessions}. You stay signed in only on this device.', { sessions: VQ.n(n, 'session', 'sessions') }) : VQ.t('No other session was open.'));
      return loadSessions();
    }, function (err) {
      if (all) return null; // this device is signed out below anyway
      say(errMessage(err, VQ.t('We could not close the sessions. Try again.')), 'error');
      return null;
    }).then(function () {
      if (all) {
        var out = API.customer && API.customer.logout ? API.customer.logout() : null;
        return Promise.resolve(out).catch(function () {}).then(function () { leave(VQ.t('You were signed out on every device.')); });
      }
      busy(b, false);
      $('st-sessions-no').disabled = false;
      show('st-sessions-confirm', false);
      if (sessionsOpener) (sessionsOpener.disabled ? document.querySelector('[data-confirm-sessions="all"]') : sessionsOpener).focus();
    });
  });

  function renderSecurity() {
    var verified = state.verified, tfaOn = !!(state.tfa && state.tfa.two_factor_active);
    if (!state.ready) return;
    $('st-s-security').textContent = verified && tfaOn ? VQ.t('excellent') : verified ? VQ.t('good') : VQ.t('fair');
    $('st-s-security-p').textContent = verified ? (tfaOn ? VQ.t('email verified · 2FA on') : VQ.t('email verified')) : VQ.t('verify your email');
    var lines = [verified ? VQ.t('Your email is verified.') : VQ.t('Your email is not verified yet: send the link from “Personal details”.')];
    lines.push(tfaOn ? VQ.t('Two-step sign-in is on.') : VQ.t('Two-step sign-in is off. Turn it on for extra protection.'));
    if (Array.isArray(state.sessions) && state.sessions.length) {
      lines.push(state.sessions.length === 1 ? VQ.t('You are signed in only on this device.') : VQ.t('You are signed in on {devices}.', { devices: VQ.n(state.sessions.length, 'device', 'devices') }));
    }
    $('st-security-status').textContent = lines.join(' ');
  }

  // ---------- preferences ----------
  function loadCategories() {
    return API.get('/marketplace-events/categories', { all: 1, parents_only: 1 }).then(function (resp) {
      var d = resp && resp.data, rows = Array.isArray(d) ? d : (obj(d) && Array.isArray(d.categories) ? d.categories : []);
      categories = rows.filter(obj).map(function (c) { return { slug: txt(c.slug), name: txt(c.name) || txt(c.slug) }; }).filter(function (c) { return c.slug && c.name; });
    }, function () { categories = []; }).then(function () {
      if (!categories.length) categories = FALLBACK_CATEGORIES.slice();
      renderCategories();
    });
  }
  function slugName(slug) { var s = String(slug).replace(/[-_]+/g, ' ').trim(); return s.charAt(0).toUpperCase() + s.slice(1); }
  function renderCategories() {
    if (!categories) return;
    var box = $('st-cats'), sel = state.interests.event_categories, list = categories.slice(), frag = document.createDocumentFragment();
    sel.forEach(function (slug) { if (!list.some(function (c) { return c.slug === slug; })) list.push({ slug: slug, name: slugName(slug) }); });
    list.forEach(function (c) {
      var b = el('button', 'st-chip'), emoji = el('span', 'st-chip-e', EMOJI[c.slug] || '✨');
      b.type = 'button';
      b.dataset.slug = c.slug;
      b.setAttribute('aria-pressed', String(sel.indexOf(c.slug) !== -1));
      emoji.setAttribute('aria-hidden', 'true');
      b.appendChild(emoji);
      b.appendChild(document.createTextNode(c.name));
      frag.appendChild(b);
    });
    box.textContent = '';
    box.appendChild(frag);
    show('st-cats-state', false);
  }
  $('st-cats').addEventListener('click', function (e) {
    var b = e.target.closest('.st-chip');
    if (!b) return;
    var sel = state.interests.event_categories, i = sel.indexOf(b.dataset.slug);
    if (i !== -1) sel.splice(i, 1);
    else if (sel.length >= MAX_PICK) { say(VQ.t('You can choose at most 20 categories. Unselect one to add another.'), 'error'); return; }
    else sel.push(b.dataset.slug);
    b.setAttribute('aria-pressed', String(i === -1));
    renderCompletion();
  });

  function loadCities() {
    return API.get('/marketplace-events/cities', { per_page: 60 }).then(function (resp) {
      var d = resp && resp.data, rows = Array.isArray(d) ? d : (obj(d) && Array.isArray(d.cities) ? d.cities : []);
      var names = rows.map(function (c) { return obj(c) ? txt(c.name) || txt(c.label) || txt(c.city) : txt(c); }).filter(Boolean);
      if (names.length) cities = names;
    }, function () {}).then(function () {
      renderCitySelects();
      renderSecondary();
    });
  }
  function cityNames() { // the list in its own order, then any saved city it lacks
    var seen = {}, out = [];
    cities.concat([state.profile.city], savedCities, state.interests.preferred_cities).forEach(function (n) {
      var key = norm(n);
      if (!n || seen[key]) return;
      seen[key] = true;
      out.push(n);
    });
    return out;
  }
  function renderCitySelects() {
    var names = cityNames();
    [$('st-city'), $('st-city2')].forEach(function (s) {
      var frag = document.createDocumentFragment();
      frag.appendChild(new Option(VQ.t('choose a city'), ''));
      names.forEach(function (n) { frag.appendChild(new Option(n, n)); });
      s.textContent = '';
      s.appendChild(frag);
      s.value = state.profile.city;
    });
  }
  [$('st-city'), $('st-city2')].forEach(function (s) {
    s.addEventListener('change', function () {
      state.profile.city = s.value;
      $('st-city').value = s.value;
      $('st-city2').value = s.value;
      $('st-city').dataset.touched = $('st-city2').dataset.touched = '1';
      renderCompletion();
    });
  });
  function renderSecondary() {
    var chips = $('st-sec-chips'), frag = document.createDocumentFragment();
    state.interests.preferred_cities.forEach(function (name) {
      var b = el('button', 'st-chip');
      b.type = 'button';
      b.dataset.city = name;
      b.setAttribute('aria-label', VQ.t('Remove {name}', { name: name }));
      b.appendChild(document.createTextNode(name));
      b.appendChild(icon('x'));
      frag.appendChild(b);
    });
    chips.textContent = '';
    chips.appendChild(frag);
    renderCityList();
  }
  function renderCityList() {
    var raw = $('st-city-search').value.trim(), q = norm(raw), ul = $('st-city-list'), sel = state.interests.preferred_cities, frag = document.createDocumentFragment();
    var names = cityNames().filter(function (n) { return !q || norm(n).indexOf(q) !== -1; });
    names.forEach(function (n) {
      var li = el('li'), label = el('label'), input = el('input');
      input.type = 'checkbox';
      input.value = n;
      input.checked = sel.indexOf(n) !== -1;
      label.appendChild(input);
      label.appendChild(document.createTextNode(n));
      li.appendChild(label);
      frag.appendChild(li);
    });
    if (!names.length) frag.appendChild(el('li', 'st-none', VQ.t('No city found for “{query}”.', { query: raw })));
    ul.textContent = '';
    ul.appendChild(frag);
  }
  $('st-city-search').addEventListener('input', renderCityList);
  $('st-city-list').addEventListener('change', function (e) {
    var input = e.target, sel = state.interests.preferred_cities;
    if (!input || input.type !== 'checkbox') return;
    var i = sel.indexOf(input.value);
    if (input.checked && i === -1) {
      if (sel.length >= MAX_PICK) { input.checked = false; say(VQ.t('You can choose at most 20 other cities.'), 'error'); return; }
      sel.push(input.value);
    } else if (!input.checked && i !== -1) sel.splice(i, 1);
    var chips = $('st-sec-chips'), keep = document.activeElement;
    renderSecondaryChipsOnly(chips);
    if (keep && keep.isConnected) keep.focus();
    renderCompletion();
  });
  function renderSecondaryChipsOnly(chips) {
    var frag = document.createDocumentFragment();
    state.interests.preferred_cities.forEach(function (name) {
      var b = el('button', 'st-chip');
      b.type = 'button';
      b.dataset.city = name;
      b.setAttribute('aria-label', VQ.t('Remove {name}', { name: name }));
      b.appendChild(document.createTextNode(name));
      b.appendChild(icon('x'));
      frag.appendChild(b);
    });
    chips.textContent = '';
    chips.appendChild(frag);
  }
  $('st-sec-chips').addEventListener('click', function (e) {
    var b = e.target.closest('.st-chip');
    if (!b) return;
    var sel = state.interests.preferred_cities, i = sel.indexOf(b.dataset.city), next = b.nextElementSibling || b.previousElementSibling;
    if (i !== -1) sel.splice(i, 1);
    var nextCity = next && next.dataset.city;
    renderSecondary();
    var target = nextCity ? $('st-sec-chips').querySelector('[data-city="' + CSS.escape(nextCity) + '"]') : null;
    (target || $('st-city-search')).focus();
    renderCompletion();
  });
  LIFESTYLE.forEach(function (k) { $('st-' + k).addEventListener('change', renderCompletion); });

  $('st-prefs-save').addEventListener('click', function () {
    if (!state.ready) return;
    var b = this, city = state.profile.city;
    busy(b, true, VQ.t('Saving…'));
    var step = city !== state.savedCity
      ? API.customer.updateProfile({ city: city || null }).then(function (resp) {
        state.savedCity = city;
        if (resp && resp.data && obj(resp.data.customer)) remember(resp.data.customer);
      })
      : Promise.resolve();
    step.then(function () {
      var lifestyle = {};
      LIFESTYLE.forEach(function (k) { lifestyle[k] = $('st-' + k).value || null; });
      return API.put('/customer/settings', { interests: { preferred_cities: state.interests.preferred_cities.slice(), event_categories: state.interests.event_categories.slice(), lifestyle: lifestyle } });
    }).then(function () {
      LIFESTYLE.forEach(function (k) { state.lifestyle[k] = $('st-' + k).value; });
      say(VQ.t('Your preferences were saved.'));
    }, function (err) {
      say(errMessage(err, VQ.t('We could not save your preferences. Try again.')), 'error');
    }).then(function () { busy(b, false); });
  });

  // ---------- family ----------
  function loadBeneficiaries() {
    return fresh('/customer/beneficiaries').then(function (resp) {
      var d = resp && resp.data;
      state.beneficiaries = (obj(d) && Array.isArray(d.beneficiaries) ? d.beneficiaries : []).filter(obj);
      renderBeneficiaries();
    }, function (err) {
      if (err && err.status === 401) return;
      show('st-bens', false);
      stateMsg('st-ben-state', VQ.t('We could not load your guests.'), loadBeneficiaries);
    });
  }
  function ageOf(b) {
    if (b.age !== null && b.age !== undefined && b.age !== '' && isFinite(b.age)) return Math.max(0, Math.floor(Number(b.age)));
    var d = toDate(b.birth_date);
    if (!d) return null;
    var now = new Date(), a = now.getFullYear() - d.getFullYear();
    if (now.getMonth() < d.getMonth() || (now.getMonth() === d.getMonth() && now.getDate() < d.getDate())) a--;
    return a >= 0 ? a : null;
  }
  function renderBeneficiaries() {
    var ul = $('st-bens'), list = state.beneficiaries, frag = document.createDocumentFragment();
    list.forEach(function (b) {
      var li = el('li', 'st-ben'), name = txt(b.name) || VQ.t('No name'), age = ageOf(b), actions = el('div', 'st-row-actions');
      li.id = 'ben-' + b.id;
      var relation = RELATIONS[b.relation] || txt(b.relation);
      li.appendChild(el('h3', null, name));
      var bits = [relation, age === null ? '' : age === 0 ? VQ.t('under 1 year') : VQ.n(age, 'year old', 'years old')].filter(Boolean).join(' · ');
      if (bits) li.appendChild(el('p', null, bits));
      if (b.birth_date) li.appendChild(el('p', null, VQ.t('Date of birth: {date}', { date: day(String(b.birth_date).slice(0, 10)) })));
      var contact = [txt(b.email), txt(b.phone)].filter(Boolean).join(' · ');
      if (contact) li.appendChild(el('p', 'st-ben-contact', contact));
      if (txt(b.notes)) li.appendChild(el('p', 'st-ben-notes', txt(b.notes)));
      var interests = strings(b.interests);
      if (interests.length) {
        var chips = el('div', 'st-chips');
        interests.slice(0, 6).forEach(function (x) { chips.appendChild(el('span', 'acc-tag', x)); });
        li.appendChild(chips);
      }
      var edit = button(VQ.t('Edit'), 'btn-ghost st-ben-edit'), del = button(VQ.t('Delete'), 'st-danger');
      edit.setAttribute('aria-label', VQ.t('Edit {name}', { name: name }));
      del.setAttribute('aria-label', VQ.t('Delete {name}', { name: name }));
      edit.addEventListener('click', function () { openBenForm(b, edit); });
      del.addEventListener('click', function () {
        confirmRow(actions, VQ.t('Delete this guest?'), VQ.t('Yes, delete'), function (done) { deleteBeneficiary(b, name, done); });
      });
      actions.appendChild(edit);
      actions.appendChild(del);
      li.appendChild(actions);
      frag.appendChild(li);
    });
    var left = MAX_BENEFICIARIES - list.length, tile = el('li');
    if (left > 0) {
      var add = el('button', 'st-ben-add');
      add.type = 'button';
      add.id = 'st-ben-add';
      add.appendChild(icon('plus'));
      add.appendChild(el('span', null, VQ.t('Add a guest')));
      add.appendChild(el('small', 'st-note', list.length ? VQ.n(left, 'place left', 'places left') : VQ.t('a child, a partner, a parent or a friend')));
      add.addEventListener('click', function () { openBenForm(null, add); });
      tile.appendChild(add);
    } else tile.appendChild(el('p', 'st-note st-ben-full', VQ.t('You have reached the limit of 25 guests. Delete one to add another.')));
    frag.appendChild(tile);
    ul.textContent = '';
    ul.appendChild(frag);
    show(ul, true);
    if (list.length) show('st-ben-state', false);
    else stateMsg('st-ben-state', VQ.t('You have not saved any guests yet. Add the first one so you do not type the name with every order.'));
  }
  function openBenForm(b, opener) {
    var form = $('st-ben-form');
    benEditing = b ? b.id : 'new';
    benOpener = opener || null;
    clearInvalid(form);
    form.reset();
    formError('st-ben-error', '');
    [].forEach.call(form.querySelectorAll('[data-touched]'), function (n) { delete n.dataset.touched; });
    $('st-ben-form-h').textContent = b ? VQ.t('Edit: {name}', { name: txt(b.name) || VQ.t('guest') }) : VQ.t('New guest');
    $('st-ben-save').textContent = b ? VQ.t('Save changes') : VQ.t('Add the guest');
    $('st-ben-birth').max = todayIso();
    if (b) {
      $('st-ben-name').value = txt(b.name);
      setSelect($('st-ben-relation'), txt(b.relation));
      $('st-ben-birth').value = txt(b.birth_date).slice(0, 10);
      $('st-ben-email').value = txt(b.email);
      $('st-ben-phone').value = txt(b.phone);
      $('st-ben-notes').value = txt(b.notes);
    }
    show(form, true);
    form.scrollIntoView({ block: 'nearest', behavior: reduce ? 'auto' : 'smooth' });
    try { $('st-ben-name').focus({ preventScroll: true }); } catch (e) { $('st-ben-name').focus(); }
  }
  function closeBenForm(focusBack) {
    show('st-ben-form', false);
    benEditing = null;
    if (focusBack && benOpener && benOpener.isConnected) benOpener.focus();
    else if (focusBack && $('st-ben-add')) $('st-ben-add').focus();
  }
  $('st-ben-cancel').addEventListener('click', function () { closeBenForm(true); });
  $('st-ben-form').addEventListener('submit', function (e) {
    e.preventDefault();
    var form = this, save = $('st-ben-save'), isNew = benEditing === 'new', id = benEditing;
    var data = {
      name: $('st-ben-name').value.trim(), relation: $('st-ben-relation').value || null, birth_date: $('st-ben-birth').value || null,
      email: $('st-ben-email').value.trim() || null, phone: $('st-ben-phone').value.trim() || null, notes: $('st-ben-notes').value.trim() || null
    };
    clearInvalid(form);
    formError('st-ben-error', '');
    if (!data.name) return formError('st-ben-error', VQ.t('Enter the name of the guest.'), $('st-ben-name'));
    if (data.birth_date && data.birth_date > todayIso()) return formError('st-ben-error', BEN_ERRORS.birth_date, $('st-ben-birth'));
    if (data.email && !/^[^\s@]+@[^\s@]+\.[^\s@]{2,}$/.test(data.email)) return formError('st-ben-error', BEN_ERRORS.email, $('st-ben-email'));
    busy(save, true, VQ.t('Saving…'));
    var req = isNew ? API.post('/customer/beneficiaries', data) : API.put('/customer/beneficiaries/' + encodeURIComponent(id), data);
    req.then(function (resp) {
      busy(save, false);
      if (!(resp && resp.data && obj(resp.data.beneficiary))) { formError('st-ben-error', VQ.t('We could not save the guest. Try again.')); return; }
      closeBenForm(false);
      say(isNew ? VQ.t('{name} was added to your guests.', { name: data.name }) : VQ.t('The details for {name} were updated.', { name: data.name }));
      loadBeneficiaries().then(function () { // the list is drawn again: focus its new add tile / edit button
        var li = isNew ? null : $('ben-' + id), focus = li ? li.querySelector('.st-ben-edit') : $('st-ben-add');
        if (focus) focus.focus();
      });
    }, function (err) {
      busy(save, false);
      var field = err && obj(err.errors) ? Object.keys(err.errors).filter(function (k) { return BEN_FIELDS[k]; })[0] : null;
      if (field) formError('st-ben-error', BEN_ERRORS[field], $(BEN_FIELDS[field]));
      else formError('st-ben-error', errMessage(err, VQ.t('We could not save the guest. Try again.')));
    });
  });
  function deleteBeneficiary(b, name, done) {
    API.delete('/customer/beneficiaries/' + encodeURIComponent(b.id), {}).then(function (resp) {
      if (resp && resp.success === false) { done(false); say(VQ.t('We could not delete the guest. Try again.'), 'error'); return; }
      done(true);
      if (benEditing === b.id) closeBenForm(false);
      say(VQ.t('{name} was removed from your guests.', { name: name }));
      loadBeneficiaries().then(function () { if ($('st-ben-add')) $('st-ben-add').focus(); });
    }, function (err) {
      if (err && err.status === 404) { done(true); say(VQ.t('The guest had already been deleted.')); loadBeneficiaries(); return; }
      done(false);
      say(errMessage(err, VQ.t('We could not delete the guest. Try again.')), 'error');
    });
  }

  // ---------- notifications ----------
  function renderNotifCount() {
    var on = NOTIF.filter(function (k) { return $('st-n-' + k).checked; }).length;
    $('st-s-notif').textContent = on + '/' + NOTIF.length;
  }
  NOTIF.forEach(function (k) { $('st-n-' + k).addEventListener('change', renderNotifCount); });
  $('st-notif-save').addEventListener('click', function () {
    if (!state.ready) return;
    var b = this, prefs = Object.assign({}, state.notifLegacy);
    NOTIF.forEach(function (k) { prefs[k] = $('st-n-' + k).checked; });
    prefs.reminders = prefs.tickets; // the older key the reminder e-mails still read
    busy(b, true, VQ.t('Saving…'));
    API.put('/customer/settings', { notification_preferences: prefs }).then(function () {
      NOTIF.forEach(function (k) { state.notif[k] = prefs[k]; });
      say(VQ.t('Your notifications were saved.'));
    }, function (err) {
      say(errMessage(err, VQ.t('We could not save your notifications. Try again.')), 'error');
    }).then(function () { busy(b, false); });
  });

  // ---------- payments ----------
  function loadCards() {
    return fresh('/customer/payment-methods').then(function (resp) {
      var d = resp && obj(resp.data) ? resp.data : {};
      state.cards = (Array.isArray(d.payment_methods) ? d.payment_methods : []).filter(obj);
      state.stripe = { configured: !!d.stripe_configured, key: txt(d.stripe_publishable_key) };
      renderCards();
    }, function (err) {
      if (err && err.status === 401) return;
      show('st-cards', false);
      show('st-cards-empty', false);
      show('st-card-add-row', false);
      stateMsg('st-cards-state', VQ.t('We could not load your payment methods.'), loadCards);
    });
  }
  function canAddCards() { return !!(state.stripe.configured && state.stripe.key); }
  function focusCards() { var add = $('st-card-add'); if (add && !$('st-card-add-row').hidden) add.focus(); }
  function renderCards() {
    var ul = $('st-cards'), list = state.cards, frag = document.createDocumentFragment();
    list.forEach(function (c) {
      var li = el('li'), id = el('div', 'st-card-id'), info = el('div'), small = el('small'), actions = el('div', 'st-row-actions');
      var brandKey = norm(c.brand), brand = BRANDS[brandKey] || txt(c.brand) || txt(c.provider) || VQ.t('Card'), last4 = txt(c.last4);
      var label = brand + (last4 ? ' •••• ' + last4 : '');
      id.appendChild(el('span', 'st-brand', brand));
      info.appendChild(el('b', null, last4 ? '•••• ' + last4 : brand));
      var bits = [];
      if (c.exp_month && c.exp_year) bits.push(VQ.t('expires {date}', { date: pad(c.exp_month) + '/' + String(c.exp_year).slice(-2) }));
      var name = txt(c.label) || txt(c.cardholder);
      if (name) bits.push(name);
      small.appendChild(document.createTextNode(bits.join(' · ')));
      if (c.is_expired) { small.appendChild(document.createTextNode(bits.length ? ' · ' : '')); small.appendChild(el('span', 'is-bad', VQ.t('expired'))); }
      if (c.is_default) { small.appendChild(document.createTextNode(small.textContent ? ' · ' : '')); small.appendChild(el('span', 'is-ok', VQ.t('default'))); }
      info.appendChild(small);
      id.appendChild(info);
      if (!c.is_default && !c.is_expired) {
        var def = button(VQ.t('Make default'), 'btn-ghost');
        def.setAttribute('aria-label', VQ.t('Make {card} the default', { card: label }));
        def.addEventListener('click', function () { setDefaultCard(c, label, def); });
        actions.appendChild(def);
      }
      var del = button(VQ.t('Delete'), 'st-danger');
      del.setAttribute('aria-label', VQ.t('Delete {name}', { name: label }));
      del.addEventListener('click', function () {
        confirmRow(actions, VQ.t('Delete this card?'), VQ.t('Yes, delete'), function (done) { deleteCard(c, label, done); });
      });
      actions.appendChild(del);
      li.appendChild(id);
      li.appendChild(actions);
      frag.appendChild(li);
    });
    ul.textContent = '';
    ul.appendChild(frag);
    show('st-cards-state', false);
    show('st-pay-off', !canAddCards());
    show(ul, list.length > 0);
    show('st-cards-empty', !list.length && canAddCards());
    show('st-card-add-row', canAddCards() && $('st-card-form').hidden);
  }
  function setDefaultCard(c, label, b) {
    busy(b, true, VQ.t('Setting…'));
    API.put('/customer/payment-methods/' + encodeURIComponent(c.id) + '/default', {}).then(function () {
      say(VQ.t('{card} is now your default card.', { card: label }));
      return loadCards().then(focusCards);
    }, function (err) {
      busy(b, false);
      say(errMessage(err, VQ.t('We could not set the default card. Try again.')), 'error');
    });
  }
  function deleteCard(c, label, done) {
    API.delete('/customer/payment-methods/' + encodeURIComponent(c.id), {}).then(function () {
      done(true);
      say(VQ.t('{name} was deleted.', { name: label }));
      loadCards().then(focusCards);
    }, function (err) {
      if (err && err.status === 404) { done(true); say(VQ.t('The card had already been deleted.')); loadCards(); return; }
      done(false);
      say(errMessage(err, VQ.t('We could not delete the card. Try again.')), 'error');
    });
  }
  function loadStripe() {
    if (window.Stripe) return Promise.resolve(window.Stripe);
    if (stripePromise) return stripePromise;
    stripePromise = new Promise(function (resolve, reject) {
      var s = document.createElement('script');
      s.src = 'https://js.stripe.com/v3/';
      s.async = true;
      s.onload = function () { if (window.Stripe) resolve(window.Stripe); else { stripePromise = null; reject(new Error('stripe')); } };
      s.onerror = function () { stripePromise = null; s.remove(); reject(new Error('stripe')); };
      document.head.appendChild(s);
    });
    return stripePromise;
  }
  function closeCardForm(focusAdd) {
    if (cardEl) { try { cardEl.destroy(); } catch (e) {} }
    cardEl = null;
    cardComplete = false;
    show('st-card-form', false);
    formError('st-card-error', '');
    show('st-card-add-row', canAddCards());
    if (focusAdd && !$('st-card-add-row').hidden) $('st-card-add').focus();
  }
  $('st-card-add').addEventListener('click', function () {
    if (!canAddCards()) { say(VQ.t('The payment processor is not set up yet.'), 'error'); return; }
    var box = $('st-card-element'), save = $('st-card-save');
    show('st-card-form', true);
    show('st-card-add-row', false);
    formError('st-card-error', '');
    save.disabled = true;
    cardComplete = false;
    box.textContent = '';
    box.appendChild(el('p', 'st-note', VQ.t('Loading the secure form…')));
    $('st-card-form').scrollIntoView({ block: 'nearest', behavior: reduce ? 'auto' : 'smooth' });
    loadStripe().then(function (Stripe) {
      if ($('st-card-form').hidden || cardEl) return;
      if (!stripe || stripeKey !== state.stripe.key) { stripe = Stripe(state.stripe.key); stripeKey = state.stripe.key; }
      box.textContent = '';
      cardEl = stripe.elements({ locale: VQ.locale }).create('card', {
        hidePostalCode: true,
        style: {
          base: { fontFamily: getComputedStyle(document.body).fontFamily, fontSize: '16px', color: '#212121', '::placeholder': { color: '#6B6F6C' } },
          invalid: { color: '#C8322B', iconColor: '#C8322B' }
        }
      });
      cardEl.mount(box);
      cardEl.on('change', function (ev) {
        cardComplete = !!ev.complete;
        save.disabled = !cardComplete || save.getAttribute('aria-busy') === 'true';
        formError('st-card-error', ev.error ? ev.error.message : '');
      });
      cardEl.on('ready', function () { try { cardEl.focus(); } catch (e) {} });
    }, function () {
      box.textContent = '';
      formError('st-card-error', VQ.t('We could not load the secure Stripe form. Check your connection or any extensions that block scripts, then try again.'));
    });
  });
  $('st-card-cancel').addEventListener('click', function () { closeCardForm(true); });
  $('st-card-save').addEventListener('click', function () {
    if (!stripe || !cardEl || !cardComplete) return;
    var b = this, holder = (state.profile.first_name + ' ' + state.profile.last_name).trim();
    busy(b, true, VQ.t('Saving…'));
    formError('st-card-error', '');
    API.post('/customer/payment-methods/setup-intent', {}).then(function (resp) {
      var secret = resp && resp.data && resp.data.client_secret;
      if (!secret) throw { custom: VQ.t('We could not start saving the card. Try again.') };
      return stripe.confirmCardSetup(secret, { payment_method: { card: cardEl, billing_details: { name: holder || undefined, email: state.profile.email || undefined } } });
    }).then(function (result) {
      if (!result || result.error || !result.setupIntent) throw { custom: (result && result.error && result.error.message) || VQ.t('The card could not be verified.') };
      return API.post('/customer/payment-methods/confirm', { setup_intent_id: result.setupIntent.id });
    }).then(function (resp) {
      if (!(resp && resp.data && obj(resp.data.payment_method))) throw { custom: VQ.t('The card was verified, but we could not save it. Try again.') };
      busy(b, false);
      closeCardForm(false);
      say(VQ.t('The card was saved.'));
      return loadCards();
    }).catch(function (err) {
      busy(b, false);
      b.disabled = !cardComplete;
      if (err && err.status === 503) { state.stripe.configured = false; closeCardForm(false); renderCards(); say(VQ.t('The payment processor is not set up yet.'), 'error'); return; }
      formError('st-card-error', (err && err.custom) || errMessage(err, VQ.t('We could not save the card. Try again.')));
    });
  });

  // ---------- privacy: GDPR export ----------
  function exportBusy(r) { return !!r && (r.status === 'pending' || r.status === 'processing'); }
  function exportExpired(r) { var d = toDate(r && r.expires_at); return !!d && d.getTime() <= Date.now(); }
  function loadGdpr(silent) {
    var before = state.gdpr;
    return fresh('/customer/gdpr/export/status').then(function (resp) {
      var d = resp && resp.data;
      state.gdpr = obj(d) && obj(d.latest) ? d.latest : null;
      if (exportBusy(before) && state.gdpr && !exportBusy(state.gdpr)) {
        if (state.gdpr.status === 'completed') say(VQ.t('The archive with your data is ready to download.'));
        else if (state.gdpr.status === 'failed') say(VQ.t('The data export did not work. You can try again.'), 'error');
      }
      if (!(silent && exportBusy(before) && exportBusy(state.gdpr))) renderGdpr();
      schedulePoll();
    }, function (err) {
      if (err && err.status === 401) return;
      if (silent) { schedulePoll(); return; }
      var box = $('st-export');
      box.textContent = '';
      box.appendChild(el('p', 'st-state', ''));
      box.firstChild.id = 'st-export-state';
      stateMsg('st-export-state', VQ.t('We could not check for exports.'), function () { loadGdpr(false); });
    });
  }
  function schedulePoll() {
    clearTimeout(pollTimer);
    if (!exportBusy(state.gdpr)) { pollStarted = 0; pollSlow = false; return; }
    if (!pollStarted) pollStarted = Date.now();
    if (Date.now() - pollStarted > POLL_LIMIT) { if (!pollSlow) { pollSlow = true; renderGdpr(); } return; }
    pollTimer = setTimeout(function () {
      if (document.hidden) { schedulePoll(); return; }
      loadGdpr(true);
    }, POLL_MS);
  }
  function renderGdpr() {
    var box = $('st-export'), r = state.gdpr, row = el('div', 'st-actions'), c;
    box.textContent = '';
    if (exportBusy(r)) {
      c = el('div', 'st-callout is-wait');
      c.appendChild(el('b', null, r.status === 'processing' ? VQ.t('Preparing the archive…') : VQ.t('Your request is waiting…')));
      c.appendChild(el('p', null, (r.requested_at ? VQ.t('Sent {when}.', { when: ago(r.requested_at) }) + ' ' : '')
        + (pollSlow ? VQ.t('It is taking longer than usual. We will email you when the archive is ready.') : VQ.t('This page updates by itself, and you also get an email when it is ready.'))));
      if (pollSlow) {
        var again = button(VQ.t('Check again'), 'btn-ghost');
        again.addEventListener('click', function () { pollStarted = 0; pollSlow = false; busy(again, true, VQ.t('Checking…')); loadGdpr(false); });
        row.appendChild(again);
        c.appendChild(row);
      }
      box.appendChild(c);
      return;
    }
    if (r && r.status === 'completed' && r.download_url && !exportExpired(r)) {
      c = el('div', 'st-callout is-ok');
      c.appendChild(el('b', null, VQ.t('Your archive is ready')));
      c.appendChild(el('p', null, ['ZIP' + (bytes(r.file_size_bytes) ? ', ' + bytes(r.file_size_bytes) : ''), r.expires_at ? VQ.t('available until {date}', { date: day(r.expires_at) }) : '', r.downloaded_at ? VQ.t('downloaded {when}', { when: ago(r.downloaded_at) }) : ''].filter(Boolean).join(' · ')));
      var dl = button(VQ.t('Download the archive'), 'btn-primary');
      dl.addEventListener('click', function () { downloadExport(r, dl); });
      row.appendChild(dl);
      row.appendChild(requestButton(VQ.t('Ask for a new export'), 'btn-ghost'));
      c.appendChild(row);
      box.appendChild(c);
      return;
    }
    if (r && r.status === 'failed') {
      c = el('div', 'st-callout is-bad');
      c.appendChild(el('b', null, VQ.t('The last export did not work')));
      var why = txt(r.error_message);
      c.appendChild(el('p', null, why && isRo(why) && VQ.locale === 'ro' ? why : VQ.t('Something went wrong while preparing the archive. You can try again.')));
      box.appendChild(c);
      row.appendChild(requestButton(VQ.t('Try again'), 'btn-primary'));
    } else {
      var note = r && r.status === 'completed'
        ? (day(r.processed_at || r.requested_at)
          ? VQ.t('The archive from {date} has expired. You can ask for a new one at any time.', { date: day(r.processed_at || r.requested_at) })
          : VQ.t('The archive from your last request has expired. You can ask for a new one at any time.'))
        : VQ.t('We prepare a ZIP archive in a few minutes. You get an email when it is ready, and the download link stays valid for 14 days.');
      box.appendChild(el('p', 'st-note', note));
      row.appendChild(requestButton(VQ.t('Ask for the export'), 'btn-primary'));
    }
    box.appendChild(row);
  }
  function requestButton(label, cls) {
    var b = button(label, cls);
    b.addEventListener('click', function () { requestExport(b); });
    return b;
  }
  function requestExport(b) {
    busy(b, true, VQ.t('Sending…'));
    API.post('/customer/gdpr/export', {}).then(function (resp) {
      var r = resp && resp.data && obj(resp.data.request) ? resp.data.request : null;
      if (!r) { busy(b, false); say(VQ.t('We could not record the request. Try again.'), 'error'); return; }
      state.gdpr = r;
      pollStarted = 0;
      pollSlow = false;
      say(resp.data.reused ? VQ.t('You already have an export request in progress. We will let you know when the archive is ready.') : VQ.t('Your request is recorded. We are preparing the archive and will email you when it is ready.'));
      renderGdpr();
      schedulePoll();
      $('st-export').focus();
    }, function (err) {
      busy(b, false);
      say(err && err.status === 429 ? VQ.t('You have already asked for several exports in the last hour. Try again a little later.') : errMessage(err, VQ.t('We could not record the export request. Try again.')), 'error');
    });
  }
  function downloadExport(r, b) {
    var m = /\/gdpr\/download\/([A-Za-z0-9]+)\/?$/.exec(String(r.download_url || '')), auth = token();
    if (!m) { say(VQ.t('The download link is not valid. Ask for a new export.'), 'error'); return; }
    if (!auth) { say(VQ.t('Your session has expired. Sign in again to download the archive.'), 'error'); return; }
    busy(b, true, VQ.t('Downloading…'));
    fetch(apiUrl() + '?action=customer.gdpr.download&export=' + encodeURIComponent(m[1]), {
      headers: { Authorization: 'Bearer ' + auth, Accept: 'application/zip' }, credentials: 'same-origin', cache: 'no-store'
    }).then(function (res) {
      if (res.status === 401) return 'auth';
      if (res.status === 404 || res.status === 410) return 'gone';
      if (!res.ok || (res.headers.get('Content-Type') || '').indexOf('zip') === -1) return 'fail';
      var cd = res.headers.get('Content-Disposition') || '', fm = /filename\*?=(?:UTF-8'')?"?([^";]+)"?/i.exec(cd), name = 'viaqui-personal-data.zip';
      if (fm) { try { name = decodeURIComponent(fm[1]); } catch (e) { name = fm[1]; } }
      return res.blob().then(function (blob) { account.save(blob, name); return 'ok'; });
    }, function () { return 'network'; }).then(function (result) {
      busy(b, false);
      if (result === 'ok') say(VQ.t('The archive with your data was downloaded.'));
      else if (result === 'auth') say(VQ.t('Your session has expired. Sign in again to download the archive.'), 'error');
      else if (result === 'gone') { say(VQ.t('The archive has expired or no longer exists. Ask for a new export.'), 'error'); loadGdpr(false); }
      else if (result === 'network') say(VQ.t('We could not download the archive. Check your connection and try again.'), 'error');
      else say(VQ.t('We could not download the archive right now. Try again in a few minutes.'), 'error');
    });
  }

  // ---------- privacy: personalisation ----------
  function renderPersonalization() { $('st-personalization-l').textContent = $('st-personalization').checked ? VQ.t('On') : VQ.t('Off'); }
  $('st-personalization').addEventListener('change', function () {
    var input = this, on = input.checked;
    renderPersonalization();
    input.disabled = true;
    API.put('/customer/settings', { personalization_enabled: on }).then(function () {
      state.personalization = on;
      say(on ? VQ.t('Personalised recommendations are on.') : VQ.t('Personalisation is off. You will see general recommendations.'));
    }, function (err) {
      input.checked = !on;
      renderPersonalization();
      say(errMessage(err, VQ.t('We could not save the setting. Try again.')), 'error');
    }).then(function () { input.disabled = !state.ready; });
  });

  // ---------- privacy: account deletion ----------
  $('st-delete-confirm').addEventListener('change', function () {
    $('st-delete-submit').disabled = !this.checked;
    if (!this.checked) show('st-delete-final', false);
  });
  $('st-delete-form').addEventListener('submit', function (e) {
    e.preventDefault();
    var pass = $('st-delete-pass');
    clearInvalid(this);
    formError('st-delete-error', '');
    if (!$('st-delete-confirm').checked) return;
    if (!pass.value) return formError('st-delete-error', VQ.t('Enter your current password to confirm the deletion.'), pass);
    $('st-delete-submit').disabled = true;
    show('st-delete-final', true);
    $('st-delete-no').focus();
  });
  $('st-delete-no').addEventListener('click', function () {
    show('st-delete-final', false);
    $('st-delete-submit').disabled = !$('st-delete-confirm').checked;
    $('st-delete-submit').focus();
  });
  $('st-delete-yes').addEventListener('click', function () {
    var b = this, pass = $('st-delete-pass');
    busy(b, true, VQ.t('Deleting the account…'));
    $('st-delete-no').disabled = true;
    API.customer.deleteAccount(pass.value, $('st-delete-reason').value.trim() || null).then(function (resp) {
      if (resp && resp.success === false) throw { status: 400, message: resp.message || '' };
      say(VQ.t('Your account was deleted. Taking you to the home page…'));
      [].forEach.call(document.querySelectorAll('#st-content button, #st-content input, #st-content select, #st-content textarea'), function (n) { n.disabled = true; });
      setTimeout(function () {
        try { BileteOnlineAuth.clearCustomerSession(); } catch (e) {}
        window.location.href = VQ.url('/');
      }, 1500);
    }).catch(function (err) {
      busy(b, false);
      $('st-delete-no').disabled = false;
      show('st-delete-final', false);
      $('st-delete-submit').disabled = !$('st-delete-confirm').checked;
      var status = err && err.status;
      if (status === 422) formError('st-delete-error', VQ.t('The current password is not correct.'), pass);
      else if (status === 400) formError('st-delete-error', VQ.t('We cannot delete the account while you have tickets for activities that have not taken place yet. Write to us if you want to cancel them.'));
      else formError('st-delete-error', errMessage(err, VQ.t('We could not delete the account. Try again.')));
    });
  });

  // ---------- load ----------
  function loadMe() {
    show('st-load-error', false);
    return fresh('/customer/me').then(function (resp) {
      var d = resp && resp.data, u = obj(d) && obj(d.customer) ? d.customer : d;
      if (!obj(u) || !u.email) { show('st-load-error', true); return; }
      remember(u);
      state.ready = true;
      applyMe(u, true);
      setReady(true);
    }, function (err) {
      if (err && err.status === 401) { guard(); return; }
      show('st-load-error', true);
    });
  }
  $('st-retry').addEventListener('click', function () {
    var b = this;
    busy(b, true, VQ.t('Loading…'));
    loadMe().then(function () { busy(b, false); });
  });

  setReady(false);
  if (!account.isCustomer()) { guard(); return; }
  applyMe(account.cachedUser(), false);
  var startTab = tabFromHash(window.location.hash);
  if (startTab && startTab !== 'personal') openTab(startTab, true);
  loadMe();
  loadCategories();
  loadCities();
  loadTfa();
  loadSessions();
  loadBeneficiaries();
  loadCards();
  loadGdpr(false);
})();
