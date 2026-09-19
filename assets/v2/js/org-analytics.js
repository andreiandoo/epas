/* bilete.online v2: organizer activity analytics (/organizator/analytics/{id}?perioada=7z|30z|90z). One activity over a
   period: totals from /organizer/events/{id}/analytics, the chosen period beside them and compared with the period before
   (a second call with start_date/end_date), the sales chart with campaigns marked, estimates, ticket types, traffic,
   locations with a map of Romania, recent sales. Goals and campaigns load on their own and can be added, edited and
   deleted. Export: the period as CSV (built here) and the PDF report. Switching the activity or the period doesn't reload
   the page. Runs inside the organizer shell (window.BO_ORG); text from the API is always written as text. */
(function () {
  'use strict';
  var O = window.BO_ORG, root = document.getElementById('oa');
  if (!O || !root) return;
  var F = O.fmt, el = O.el, icon = O.icon, SVGNS = 'http://www.w3.org/2000/svg';
  var $ = function (id) { return document.getElementById(id); };
  function qsa(sel, ctx) { return [].slice.call((ctx || document).querySelectorAll(sel)); }
  function has(obj, k) { return Object.prototype.hasOwnProperty.call(obj, k); }

  var PERIODS = {
    '7z': { api: '7d', label: 'Ultimele 7 zile', file: '7-zile' },
    '30z': { api: '30d', label: 'Ultimele 30 de zile', file: '30-zile' },
    '90z': { api: '90d', label: 'Ultimele 90 de zile', file: '90-zile' },
    tot: { api: 'all', label: 'De la publicare', file: 'tot' },
  };
  var COLORS = ['#1B7F4E', '#F2A900', '#2D6CCD', '#E4572E', '#7A5AC8', '#0E8C8C', '#8E958F', '#C2185B'];
  var SOURCES = { Direct: 'Direct', Organic: 'Alte site-uri și căutări', Facebook: 'Facebook', Google: 'Google', Instagram: 'Instagram', TikTok: 'TikTok', Email: 'Email' };
  var GOALS = {
    revenue: { label: 'Venituri nete', unit: 'lei', target: 'Ținta, în lei', example: '10.000', name: 'ex: Venituri de 10.000 lei', help: 'Ce îți revine din bilete după comision și reduceri, de la începutul vânzărilor.' },
    tickets: { label: 'Bilete vândute', unit: 'bilete', target: 'Ținta, în bilete', example: '500', name: 'ex: 500 de bilete până la final de lună', help: 'Toate biletele valide: online, la ușă și invitații.' },
    visitors: { label: 'Vizitatori', unit: 'vizitatori', target: 'Ținta, în vizitatori', example: '5.000', name: 'ex: 5.000 de vizitatori', help: 'Vizitatorii unici ai paginii activității, adunați pe zile.' },
    conversion_rate: { label: 'Rată de conversie', unit: '%', target: 'Ținta, în procente', example: '3,5', name: 'ex: Conversie de 3,5%', help: 'Media zilnică a ratei de conversie a paginii: bilete la 100 de vizite.' },
  };
  var CAMPS = {
    campaign_fb: { label: 'Facebook Ads', ad: true, source: 'facebook', medium: 'cpc' },
    campaign_instagram: { label: 'Instagram Ads', ad: true, source: 'facebook', medium: 'cpc' },
    campaign_google: { label: 'Google Ads', ad: true, source: 'google', medium: 'cpc' },
    campaign_tiktok: { label: 'TikTok Ads', ad: true, source: 'tiktok', medium: 'cpc' },
    campaign_other: { label: 'Influencer sau alte reclame', ad: true, source: 'organic', medium: 'cpc' },
    email: { label: 'Campanie email', ad: false },
    price: { label: 'Schimbare de preț', ad: false },
    announcement: { label: 'Anunț', ad: false },
    press: { label: 'Comunicat de presă', ad: false },
    lineup: { label: 'Program nou sau invitați noi', ad: false },
    custom: { label: 'Altceva', ad: false },
  };
  var ISO = /^\d{4}-\d{2}-\d{2}$/;

  /* Romanian county seats and larger towns (coordinates from resources/data/ro/cities), placed on the map by name. */
  var CITY_ROWS = 'Alba Iulia|46.067|23.583;Sebeș|45.956|23.571;Aiud|46.31|23.721;Blaj|46.175|23.916;Arad|46.183|21.317;Pitești|44.85|24.867;Câmpulung|45.267|25.05;Curtea de Argeș|45.134|24.674;Mioveni|44.959|24.942;Bacău|46.567|26.914;Onești|46.252|26.77;Moinești|46.475|26.489;Oradea|47.046|21.918;Salonta|46.8|21.65;Bistrița|47.133|24.5;Botoșani|47.75|26.667;Dorohoi|47.95|26.4;Brăila|45.272|27.974;Brașov|45.649|25.606;Făgăraș|45.85|24.967;Săcele|45.617|25.711;Codlea|45.7|25.45;Râșnov|45.583|25.45;București|44.432|26.106;Buzău|45.15|26.833;Râmnicu Sărat|45.383|27.05;Reșița|45.301|21.889;Caransebeș|45.417|22.217;Călărași|44.205|27.314;Oltenița|44.083|26.633;Cluj-Napoca|46.767|23.6;Florești|46.746|23.494;Turda|46.567|23.783;Dej|47.15|23.867;Câmpia Turzii|46.55|23.883;Gherla|47.033|23.917;Constanța|44.181|28.634;Mangalia|43.8|28.583;Medgidia|44.25|28.283;Năvodari|44.317|28.6;Sfântu Gheorghe|45.867|25.783;Târgu Secuiesc|46.0|26.133;Târgoviște|44.925|25.457;Moreni|44.983|25.644;Craiova|44.317|23.8;Băilești|44.017|23.35;Calafat|43.991|22.933;Galați|45.437|28.05;Tecuci|45.85|27.434;Giurgiu|43.887|25.963;Târgu Jiu|45.05|23.283;Motru|44.803|22.972;Miercurea Ciuc|46.35|25.8;Odorheiu Secuiesc|46.3|25.3;Gheorgheni|46.723|25.601;Deva|45.883|22.9;Hunedoara|45.75|22.9;Petroșani|45.417|23.367;Orăștie|45.833|23.2;Slobozia|44.565|27.363;Fetești|44.383|27.833;Urziceni|44.717|26.633;Iași|47.167|27.6;Pașcani|47.247|26.723;Buftea|44.561|25.949;Voluntari|44.49|26.173;Pantelimon|44.45|26.2;Popești-Leordeni|44.383|26.167;Otopeni|44.55|26.067;Bragadiru|44.371|25.977;Chiajna|44.46|25.973;Mogoșoaia|44.529|26.0;Măgurele|44.35|26.033;Baia Mare|47.657|23.568;Sighetu Marmației|47.919|23.887;Drobeta-Turnu Severin|44.627|22.653;Târgu Mureș|46.55|24.56;Sighișoara|46.217|24.791;Reghin|46.772|24.669;Piatra Neamț|46.917|26.333;Roman|46.921|26.926;Slatina|44.433|24.367;Caracal|44.117|24.35;Ploiești|44.95|26.017;Câmpina|45.126|25.735;Sinaia|45.35|25.55;Bușteni|45.4|25.533;Zalău|47.2|23.05;Satu Mare|47.799|22.863;Carei|47.683|22.467;Sibiu|45.8|24.15;Mediaș|46.167|24.35;Suceava|47.633|26.25;Rădăuți|47.85|25.917;Fălticeni|47.45|26.3;Câmpulung Moldovenesc|47.533|25.567;Vatra Dornei|47.35|25.367;Alexandria|43.983|25.333;Turnu Măgurele|43.747|24.868;Roșiorii de Vede|44.117|24.983;Timișoara|45.754|21.226;Lugoj|45.689|21.903;Tulcea|45.179|28.805;Vaslui|46.633|27.733;Bârlad|46.232|27.669;Huși|46.674|28.059;Râmnicu Vâlcea|45.1|24.367;Drăgășani|44.65|24.267;Focșani|45.7|27.183;Adjud|46.1|27.167';
  var CITIES = {};
  CITY_ROWS.split(';').forEach(function (r) { var p = r.split('|'); CITIES[norm(p[0])] = { name: p[0], lat: +p[1], lng: +p[2], cc: 'RO' }; });
  ['bucharest', 'sector 1', 'sector 2', 'sector 3', 'sector 4', 'sector 5', 'sector 6', 'sectorul 1', 'sectorul 2', 'sectorul 3', 'sectorul 4', 'sectorul 5', 'sectorul 6'].forEach(function (k) { CITIES[k] = CITIES[norm('București')]; });
  CITIES[norm('Chișinău')] = { name: 'Chișinău', lat: 47.011, lng: 28.864, cc: 'MD' };
  CITIES[norm('Bălți')] = { name: 'Bălți', lat: 47.762, lng: 27.929, cc: 'MD' };
  /* the same projection as includes/v2/map-romania.svg: equirectangular, true at 46°N, window 20–30°E × 43.4–48.5°N */
  var MAP = { lon0: 20, lat0: 48.5, k: 0.6946583704589973, s: 100 };

  var S = { event: '', period: 'tot' };
  var loaded = { event: '', period: '' };
  var data = null, prev = null, goals = null, camps = null, events = null, eventsReq = null, eventsFailed = false;
  var seq = 0, goalsSeq = 0, campsSeq = 0;
  var chart = null, parts = null, activeDay = -1, show = { rev: true, tix: true }, redrawTimer = 0;
  var opener = null, openerKey = null, fallbackFocus = null, editGoal = null, editCamp = null, delTarget = null, formEvent = '';
  var geo = null;
  try { geo = new Intl.DisplayNames(['ro'], { type: 'region' }); } catch (e) { geo = null; }

  /* =================== HELPERS =================== */
  function num(v) { return F.toNum(v); }
  function money(v) { return F.money(v); }
  function sum(list) { return (Array.isArray(list) ? list : []).reduce(function (s, v) { return s + num(v); }, 0); }
  function cap(s) { return s ? s.charAt(0).toUpperCase() + s.slice(1) : s; }
  function norm(s) { return String(s == null ? '' : s).normalize('NFD').replace(/[̀-ͯ]/g, '').toLowerCase().replace(/[-_]+/g, ' ').replace(/\s+/g, ' ').trim(); }
  function naiveDay(v) { var m = /^(\d{4}-\d{2}-\d{2})/.exec(String(v || '')); return m ? m[1] : ''; }
  function naiveTime(v) { var m = /T(\d{2}):(\d{2})/.exec(String(v || '')); return m && m[1] + m[2] !== '0000' ? m[1] + ':' + m[2] : ''; }
  function dayLabel(ymd, opts) { var d = F.dateOf(ymd); return d ? F.date(d, opts || { day: 'numeric', month: 'long', year: 'numeric' }) : ''; }
  function dayShort(ymd) { return dayLabel(ymd, { day: 'numeric', month: 'short', year: 'numeric' }); }
  function rangeLabel(a, b) { return a === b ? dayShort(a) : dayShort(a) + ' – ' + dayShort(b); }
  function stamp(iso) { var d = F.dateOf(iso); return d ? F.date(d, { day: '2-digit', month: '2-digit', year: 'numeric', hour: '2-digit', minute: '2-digit' }) : ''; }
  function addDays(ymd, n) { var d = new Date(ymd + 'T00:00:00Z'); d.setUTCDate(d.getUTCDate() + n); return d.toISOString().slice(0, 10); }
  function svgNode(tag, attrs, txt) {
    var n = document.createElementNS(SVGNS, tag);
    Object.keys(attrs || {}).forEach(function (k) { n.setAttribute(k, attrs[k]); });
    if (txt != null) n.textContent = txt;
    return n;
  }
  function slug(s) { return String(s || '').normalize('NFD').replace(/[̀-ͯ]/g, '').toLowerCase().replace(/[^a-z0-9]+/g, '-').replace(/^-+|-+$/g, '').slice(0, 60); }
  var compact = new Intl.NumberFormat('ro-RO', { notation: 'compact', maximumFractionDigits: 1 });
  var dec1 = new Intl.NumberFormat('ro-RO', { maximumFractionDigits: 1 });
  var dec2 = new Intl.NumberFormat('ro-RO', { maximumFractionDigits: 2 });
  var plainFmt = new Intl.NumberFormat('ro-RO', { maximumFractionDigits: 2, useGrouping: false });
  function niceNum(x) {
    if (!(x > 0)) return 1;
    var e = Math.pow(10, Math.floor(Math.log10(x))), f = x / e;
    return (f <= 1 ? 1 : f <= 2 ? 2 : f <= 2.5 ? 2.5 : f <= 5 ? 5 : 10) * e;
  }
  /** "1.234,50", "1234,5", "12.5" and "500" all read as numbers; anything else is NaN. */
  function parseAmount(v) {
    var s = String(v == null ? '' : v).replace(/\s+/g, '').replace(/(lei|%)$/i, '');
    if (/^\d{1,3}(\.\d{3})+(,\d+)?$/.test(s)) return parseFloat(s.replace(/\./g, '').replace(',', '.'));
    if (/^\d+(,\d+)?$/.test(s)) return parseFloat(s.replace(',', '.'));
    if (/^\d+\.\d{1,2}$/.test(s)) return parseFloat(s);
    return NaN;
  }
  function approx(v, one, many) { var r = Math.round(v); return '~' + (Math.abs(v - r) < 0.05 ? F.count(r, one, many) : dec1.format(v) + ' ' + many); }
  function parseCount(v) { var s = String(v == null ? '' : v).replace(/[\s.]+/g, ''); return /^\d+$/.test(s) ? parseInt(s, 10) : NaN; }
  function initials(name) {
    var w = String(name || '').split(/\s+/).map(function (x) { return x.replace(/[^\p{L}\p{N}]/gu, ''); }).filter(Boolean);
    return ((w.length > 1 ? w[0].charAt(0) + w[w.length - 1].charAt(0) : (w[0] || '').slice(0, 2)) || '?').toUpperCase();
  }
  function countryName(code) {
    code = String(code || '').toUpperCase();
    try { return geo && /^[A-Z]{2}$/.test(code) ? geo.of(code) : code; } catch (e) { return code; }
  }
  /** Carbon's English "3 hours ago", in Romanian. Anything else passes through. */
  function roAgo(s) {
    s = String(s || '').trim();
    if (/^just now$/i.test(s)) return 'chiar acum';
    var m = /^(\d+|an?|one)\s+(second|minute|hour|day|week|month|year)s?\s+ago$/i.exec(s);
    if (!m) return s;
    var n = /^\d+$/.test(m[1]) ? parseInt(m[1], 10) : 1, u = m[2].toLowerCase();
    if (u === 'second') return 'chiar acum';
    var W = { minute: ['un minut', 'minut', 'minute'], hour: ['o oră', 'oră', 'ore'], day: ['o zi', 'zi', 'zile'], week: ['o săptămână', 'săptămână', 'săptămâni'], month: ['o lună', 'lună', 'luni'], year: ['un an', 'an', 'ani'] }[u];
    return 'acum ' + (n === 1 ? W[0] : F.count(n, W[1], W[2]));
  }
  function evName(e) { return F.flat(e && (e.name || e.title)) || 'Activitatea #' + (e && e.id); }
  function evMeta(e) { var d = naiveDay(e.starts_at); return [d ? dayShort(d) : '', F.flat(e.venue_name), F.flat(e.venue_city)].filter(Boolean).join(' · '); }
  function isLive(ev) {
    if (ev.is_cancelled || ev.is_postponed || ev.is_past || ev.is_ended) return false;
    if (ev.status !== 'published' && ev.status !== 'active') return false;
    var end = naiveDay(ev.ends_at || ev.starts_at);
    return !end || end >= F.ymd();
  }
  function daysUntil() {
    var e = (data && data.event) || {}, o = (data && data.overview) || {}, v = e.days_until != null ? e.days_until : o.days_until;
    return v == null || v === '' || !isFinite(parseFloat(v)) ? null : Math.round(num(v));
  }
  function activityDay() { var e = (data && data.event) || {}; return naiveDay(e.ends_at || e.starts_at || e.date); }
  /** Seats across the ticket types, or 0 when one of them is unlimited (null or below zero in core). */
  function capacityOf() {
    var list = data && Array.isArray(data.ticket_performance) ? data.ticket_performance : [];
    if (!list.length || list.some(function (t) { return t.capacity == null || t.capacity === '' || num(t.capacity) < 0; })) return 0;
    return sum(list.map(function (t) { return t.capacity; }));
  }
  function visitorsOf(d) { return sum(((d && Array.isArray(d.traffic_sources)) ? d.traffic_sources : []).map(function (s) { return s.visitors; })); }
  function skel(box, tag) { box.textContent = ''; box.appendChild(tag ? el(tag, null, el('span', { class: 'org-skel oa-sk-row' })) : el('span', { class: 'org-skel oa-sk-row' })); }
  function emptyBox(box, tag, text, retry) {
    box.textContent = '';
    var p = el(tag || 'p', { class: 'oa-empty-p' }, [text]);
    if (retry) {
      var b = el('button', { type: 'button', text: 'Reîncearcă' });
      b.addEventListener('click', retry);
      p.appendChild(b);
    }
    box.appendChild(p);
  }
  function download(blob, name) {
    var url = URL.createObjectURL(blob), a = el('a', { href: url, download: name, hidden: true });
    document.body.appendChild(a);
    a.click();
    setTimeout(function () { URL.revokeObjectURL(url); a.remove(); }, 1500);
  }

  /* =================== ADDRESS =================== */
  function readUrl(first) {
    var m = /^\/organizator\/analytics\/(\d+)\/?$/.exec(location.pathname), p = new URLSearchParams(location.search), per = p.get('perioada') || '';
    var attr = first && /^\d+$/.test(root.getAttribute('data-event') || '') ? root.getAttribute('data-event') : '';
    S.event = m ? m[1] : /^\d+$/.test(p.get('event') || '') ? p.get('event') : attr;
    S.period = has(PERIODS, per) ? per : 'tot';
  }
  function writeUrl(push) {
    var pretty = /^\/organizator\/analytics(\/|$)/.test(location.pathname), p = new URLSearchParams(location.search);
    p.delete('perioada');
    if (!pretty) { if (S.event) p.set('event', S.event); else p.delete('event'); }
    if (S.period !== 'tot') p.set('perioada', S.period);
    var qs = p.toString(), url = (pretty ? '/organizator/analytics' + (S.event ? '/' + S.event : '') : location.pathname) + (qs ? '?' + qs : '');
    if (url !== location.pathname + location.search) history[push ? 'pushState' : 'replaceState'](null, '', url);
  }
  function syncPeriod() {
    qsa('.oa-seg-b', root).forEach(function (b) { b.setAttribute('aria-pressed', String(b.getAttribute('data-period') === S.period)); });
    $('oa-csv-p').textContent = PERIODS[S.period].label + ': zile, bilete, trafic și locații';
  }

  /* =================== LOAD =================== */
  function start() {
    readUrl(true);
    syncPeriod();
    window.addEventListener('popstate', function () { readUrl(false); syncPeriod(); closeDialogs(); load(); });
    load();
  }
  function load() {
    var my = ++seq, fresh = S.event !== loaded.event || !data;
    root.classList.toggle('is-none', !S.event);
    closeMenu(false);
    if (!S.event) { data = null; loaded.event = ''; showPicker(); return; }
    $('oa-pickall').hidden = true;
    $('oa-none').hidden = true;
    $('oa-body').hidden = false;
    $('oa-actions').hidden = false;
    $('oa-report').href = '/organizator/report/' + S.event;
    $('oa-all-sales').href = '/organizator/vanzari?event=' + S.event;
    if (fresh) {
      data = null;
      prev = null;
      resetView();
      loadGoals(false);
      loadCamps(false);
    }
    setBusy(true);
    O.api('/organizer/events/' + S.event + '/analytics?period=' + PERIODS[S.period].api).then(function (r) {
      if (my !== seq) return;
      data = (r && r.data) || {};
      if (!data.event && r && r.event) data.event = r.event;
      loaded.event = S.event;
      loaded.period = S.period;
      setBusy(false);
      renderAll();
      loadPrevious(my);
    }).catch(function (err) {
      if (my !== seq) return;
      setBusy(false);
      if (err && err.status === 401) return;
      if (!fresh) {
        O.flash('Nu am putut încărca perioada aleasă. Încearcă din nou.', true);
        S.period = loaded.period;
        syncPeriod();
        writeUrl(false);
        return;
      }
      loaded.event = '';
      if (err && err.status === 404) showNone('Activitatea nu a fost găsită', 'Poate a fost ștearsă sau nu este în contul tău. Alege altă activitate mai sus.', false);
      else showNone('Nu am putut încărca analiza', 'Verifică conexiunea și încearcă din nou.', true);
    });
  }
  function setBusy(on) { qsa('.oa-dep', root).forEach(function (n) { n.setAttribute('aria-busy', on ? 'true' : 'false'); }); }
  function resetView() {
    ['revenue', 'tickets', 'views', 'conversion', 'days'].forEach(function (k) {
      var v = $('oa-s-' + k);
      v.textContent = '';
      v.appendChild(el('span', { class: 'org-skel oa-sk' }));
      $('oa-s-' + k + '-p').textContent = '';
      $('oa-s-' + k + '-t').textContent = '';
      $('oa-s-' + k + '-m').hidden = true;
      $('oa-s-' + k + '-per').hidden = true;
    });
    $('oa-s-revenue-set').hidden = true;
    $('oa-info').textContent = '';
    $('oa-thumb').hidden = true;
    chart = null;
    parts = null;
    var svg = $('oa-plot').querySelector('svg');
    if (svg) svg.remove();
    $('oa-tip').hidden = true;
    $('oa-plot-msg').hidden = true;
    $('oa-marks').hidden = true;
    $('oa-totals').textContent = '';
    $('oa-chart-p').textContent = '';
    ['oa-fc-rev', 'oa-fc-tix', 'oa-fc-trev', 'oa-fc-ttix'].forEach(function (id) { $(id).textContent = '—'; });
    $('oa-fc-note').textContent = '';
    $('oa-types').textContent = '';
    $('oa-types').appendChild(el('tr', null, el('td', { colspan: '6' }, el('span', { class: 'org-skel oa-sk-row' }))));
    $('oa-types-foot').textContent = '';
    skel($('oa-traffic'), 'li');
    skel($('oa-locations'), 'li');
    skel($('oa-sales'), 'li');
    $('oa-live').hidden = true;
    ['oa-export', 'oa-map-open', 'oa-loc-map', 'oa-goal-add', 'oa-camp-add'].forEach(function (id) { $(id).disabled = true; });
  }
  function showNone(title, text, retry) {
    $('oa-body').hidden = true;
    $('oa-actions').hidden = true;
    $('oa-title').textContent = 'Analiză activitate';
    $('oa-info').textContent = '';
    $('oa-thumb').hidden = true;
    document.title = 'Analiză activitate — bilete.online';
    var box = $('oa-none');
    box.textContent = '';
    box.classList.toggle('is-error', !!retry);
    box.appendChild(el('span', { class: 'org-empty-ic' }, icon(retry ? 'warning-circle' : 'chart-line-up')));
    box.appendChild(el('b', { text: title }));
    box.appendChild(el('p', { text: text }));
    var cta = retry ? el('button', { class: 'btn btn-primary', type: 'button', text: 'Reîncearcă' }) : el('a', { class: 'btn btn-ghost', href: '/organizator/activities', text: 'Vezi activitățile' });
    if (retry) cta.addEventListener('click', function () { load(); });
    box.appendChild(el('div', { class: 'oa-empty-cta' }, cta));
    box.hidden = false;
  }
  function showPicker() {
    $('oa-body').hidden = true;
    $('oa-none').hidden = true;
    $('oa-actions').hidden = true;
    $('oa-title').textContent = 'Analiză activitate';
    $('oa-info').textContent = '';
    $('oa-thumb').hidden = true;
    document.title = 'Analiză activitate — bilete.online';
    if (document.activeElement !== input) input.value = '';
    $('oa-pickall').hidden = false;
    var my = seq;
    loadEvents().then(function () { if (my === seq) renderEvList(); }, function () {
      if (my === seq) emptyBox($('oa-evlist'), 'li', 'Nu am putut încărca activitățile.', function () { skel($('oa-evlist'), 'li'); showPicker(); });
    });
  }
  function renderAll() {
    renderHead();
    renderStats();
    prepareChart();
    renderForecast();
    renderTypes();
    renderTraffic();
    renderLocations();
    renderSales();
    renderLive();
    renderPeriodLines();
    renderGoalMeter();
    ['oa-export', 'oa-map-open', 'oa-loc-map', 'oa-goal-add', 'oa-camp-add'].forEach(function (id) { $(id).disabled = false; });
    if ($('oa-map-d').open) renderMap();
  }
  /** The same number of days just before the chosen period, for the change next to each figure. */
  function loadPrevious(my) {
    prev = null;
    if (S.period === 'tot') { renderPeriodLines(); return; }
    var dates = (data.chart && Array.isArray(data.chart.raw_dates)) ? data.chart.raw_dates : [], n = dates.length, created = naiveDay(data.event && data.event.created_at);
    if (!n || !ISO.test(dates[0])) { prev = { none: true }; renderPeriodLines(); return; }
    var end = addDays(dates[0], -1), begin = addDays(dates[0], -n);
    if (created && begin < created) { prev = { none: true }; renderPeriodLines(); return; } // the activity didn't exist for the whole period before
    renderPeriodLines();
    O.api('/organizer/events/' + S.event + '/analytics?start_date=' + begin + '&end_date=' + end, { quiet: true }).then(function (r) {
      if (my !== seq) return;
      var d = (r && r.data) || {}, c = d.chart || {};
      prev = { rev: sum(c.revenue), tix: sum(c.tickets), vis: visitorsOf(d), label: rangeLabel(begin, end) };
      renderPeriodLines();
    }, function () {
      if (my !== seq) return;
      prev = { failed: true };
      renderPeriodLines();
    });
  }

  /* =================== HEAD + FIGURES =================== */
  function renderHead() {
    var e = data.event || {}, title = F.flat(e.title) || 'Activitatea #' + S.event;
    $('oa-title').textContent = title;
    document.title = 'Analiză: ' + title + ' — bilete.online';
    if (document.activeElement !== input) input.value = title;
    var thumb = $('oa-thumb'), src = typeof e.image === 'string' && /^https?:\/\//i.test(e.image) ? e.image : '';
    thumb.textContent = '';
    if (src) {
      var img = el('img', { src: src, alt: '', loading: 'lazy', decoding: 'async' });
      img.addEventListener('error', function () { thumb.hidden = true; });
      thumb.appendChild(img);
    }
    thumb.hidden = !src;
    var info = $('oa-info'), day = naiveDay(e.starts_at || e.date), time = naiveTime(e.starts_at || e.date), bits = [], du = daysUntil();
    info.textContent = '';
    if (day) bits.push(dayLabel(day, { weekday: 'short', day: 'numeric', month: 'long', year: 'numeric' }) + (time ? ', ' + time : ''));
    [F.flat(e.venue), F.flat(e.venue_city)].forEach(function (v) { if (v && bits.indexOf(v) < 0) bits.push(v); });
    if (bits.length) info.appendChild(el('span', { text: bits.join(' · ') }));
    if (e.is_cancelled) info.appendChild(el('span', { class: 'org-tag is-bad', text: 'Anulată' }));
    else if (du !== null) info.appendChild(el('span', { class: 'org-tag ' + (du < 0 ? 'is-muted' : 'is-ok'), text: du < 0 ? 'Încheiată' : du === 0 ? 'Azi' : du === 1 ? 'Mâine' : 'Peste ' + F.count(du, 'zi', 'zile') }));
    if (e.is_sold_out) info.appendChild(el('span', { class: 'org-tag is-wait', text: 'Sold out' }));
  }
  function stat(key, value, sub) { $('oa-s-' + key).textContent = value; $('oa-s-' + key + '-p').textContent = sub || ''; }
  function statTag(key, text, cls) {
    var t = $('oa-s-' + key + '-t');
    t.textContent = '';
    if (text) t.appendChild(el('span', { class: 'org-tag ' + (cls || ''), text: text }));
  }
  function meter(key, pct, text) {
    var m = $('oa-s-' + key + '-m');
    if (pct == null) { m.hidden = true; return; }
    var bar = m.querySelector('.oa-meter'), p = Math.max(0, Math.min(100, pct));
    bar.querySelector('i').style.width = p.toFixed(1) + '%';
    bar.setAttribute('aria-valuenow', String(Math.round(p)));
    bar.setAttribute('aria-label', text);
    m.querySelector('.oa-stat-mp').textContent = text;
    m.hidden = false;
  }
  function renderStats() {
    var o = data.overview || {}, e = data.event || {}, sold = num(o.tickets_sold), views = num(o.page_views), capacity = capacityOf(), du = daysUntil();
    stat('revenue', money(o.net_revenue != null ? o.net_revenue : o.total_revenue), 'Ce îți revine din bilete, după comision și reduceri.');
    stat('tickets', F.num(sold), capacity > 0 ? '' : 'Online, la ușă și invitații.');
    statTag('tickets', num(o.tickets_today) > 0 ? '+' + F.num(o.tickets_today) + ' azi' : '', 'is-ok');
    meter('tickets', capacity > 0 ? sold / capacity * 100 : null, capacity > 0 ? F.pct(sold / capacity * 100, 0) + ' din ' + F.count(capacity, 'loc', 'locuri') : '');
    stat('views', F.num(views), 'Vizualizări ale paginii activității.');
    if (views > 0) stat('conversion', F.pct(o.conversion_rate, 1), F.count(sold, 'bilet', 'bilete') + ' la ' + F.count(views, 'vizualizare', 'vizualizări') + '.');
    else stat('conversion', '—', 'Nu avem vizualizări măsurate.');
    var day = naiveDay(e.starts_at || e.date), when = day ? dayLabel(day) : '';
    if (du === null) stat('days', '—', 'Data activității nu este stabilită.');
    else if (du > 1) stat('days', F.count(du, 'zi', 'zile'), when ? 'Pe ' + when + '.' : '');
    else if (du === 1) stat('days', 'Mâine', when);
    else if (du === 0) stat('days', 'Azi', when);
    else stat('days', 'Încheiată', (when ? 'Pe ' + when + ', ' : '') + 'acum ' + F.count(-du, 'zi', 'zile') + '.');
    statTag('days', e.is_cancelled ? 'Anulată' : e.is_sold_out ? 'Sold out' : du !== null && du >= 0 ? 'Activă' : '', e.is_cancelled ? 'is-bad' : e.is_sold_out ? 'is-wait' : 'is-ok');
  }
  function goalAmount(type, v) {
    v = num(v);
    if (type === 'revenue') return money(v / 100);
    if (type === 'conversion_rate') return F.pct(v / 100, 2);
    if (type === 'tickets') return F.count(v, 'bilet', 'bilete');
    if (type === 'visitors') return F.count(v, 'vizitator', 'vizitatori');
    return F.num(v);
  }
  function goalValue(type, v) { return type === 'tickets' || type === 'visitors' ? F.num(v) : goalAmount(type, v); }
  function revenueGoal() { return (goals || []).filter(function (g) { return g && g.type === 'revenue' && g.status !== 'cancelled'; })[0] || null; }
  function renderGoalMeter() {
    if (!data) return;
    var g = revenueGoal(), set = $('oa-s-revenue-set');
    if (!g) {
      meter('revenue', null);
      set.hidden = !Array.isArray(goals);
      return;
    }
    set.hidden = true;
    var target = num(g.target_value), p = target > 0 ? num(g.current_value) / target * 100 : num(g.progress_percent);
    meter('revenue', p, F.pct(p, 0) + ' din obiectivul de ' + goalAmount('revenue', target));
  }
  function change(cur, before, fmt) {
    if (S.period === 'tot' || !prev || prev.none || prev.failed) return null;
    var sr = el('span', { class: 'sr', text: ' față de perioada dinainte, ' + prev.label });
    if (!(before > 0)) {
      if (!(cur > 0)) return el('span', { class: 'oa-chg is-flat', title: prev.label + ': 0' }, ['0%', sr]);
      return el('span', { class: 'oa-chg is-up', title: prev.label + ': 0' }, [icon('trend-up'), 'nou', el('span', { class: 'sr', text: ', în perioada dinainte (' + prev.label + ') nu a fost nimic' })]);
    }
    var d = Math.round((cur - before) / before * 100), cls = d > 0 ? 'is-up' : d < 0 ? 'is-down' : 'is-flat';
    return el('span', { class: 'oa-chg ' + cls, title: prev.label + ': ' + fmt(before) }, [d ? icon(d > 0 ? 'trend-up' : 'trend-down') : null, (d > 0 ? '+' : d < 0 ? '−' : '') + F.num(Math.abs(d)) + '%', sr]);
  }
  function periodLine(key, text, badge) {
    var box = $('oa-s-' + key + '-per');
    box.textContent = '';
    if (!text) { box.hidden = true; return; }
    box.appendChild(el('p', { class: 'oa-per' }, [el('span', { text: text }), badge]));
    box.hidden = false;
  }
  function renderPeriodLines() {
    if (!data) return;
    var P = PERIODS[S.period], c = data.chart || {}, rev = sum(c.revenue), tix = sum(c.tickets), vis = visitorsOf(data), all = S.period === 'tot';
    periodLine('revenue', all ? '' : P.label + ': ' + money(rev), change(rev, prev && prev.rev, money));
    periodLine('tickets', all ? '' : P.label + ': ' + F.count(tix, 'bilet', 'bilete'), change(tix, prev && prev.tix, F.num));
    periodLine('views', P.label + ': ' + F.count(vis, 'vizitator', 'vizitatori'), change(vis, prev && prev.vis, F.num));
  }
  function renderLive() {
    var n = num((data.overview || {}).live_visitors);
    $('oa-live-n').textContent = n > 0 ? F.num(n) + ' online acum' : '';
    $('oa-live').hidden = !(n > 0);
  }

  /* =================== SALES CHART =================== */
  function prepareChart() {
    var c = data.chart || {}, dates = Array.isArray(c.raw_dates) ? c.raw_dates.filter(function (d) { return ISO.test(d); }) : [];
    var rev = dates.map(function (_, i) { return num((c.revenue || [])[i]); }), tix = dates.map(function (_, i) { return num((c.tickets || [])[i]); });
    var end = activityDay();
    if (end && dates.length) { // nothing is sold after the activity: stop at its last day, unless a sale came later
      var cut = dates.findIndex(function (d) { return d > end; });
      if (cut > 0) {
        var last = -1;
        rev.forEach(function (v, i) { if (v > 0 || tix[i] > 0) last = i; });
        var keep = Math.max(cut, last + 1);
        dates = dates.slice(0, keep);
        rev = rev.slice(0, keep);
        tix = tix.slice(0, keep);
      }
    }
    chart = dates.length ? { dates: dates, rev: rev, tix: tix } : null;
    activeDay = -1;
    $('oa-chart-p').textContent = chart ? rangeLabel(dates[0], dates[dates.length - 1]) : PERIODS[S.period].label;
    drawChart();
    renderTotals();
  }
  function renderTotals() {
    var box = $('oa-totals');
    box.textContent = '';
    if (!chart) return;
    var revSum = sum(chart.rev), tixSum = sum(chart.tix), saleDays = chart.tix.filter(Boolean).length, best = -1;
    chart.rev.forEach(function (v, i) { if (v > 0 && (best < 0 || v > chart.rev[best])) best = i; });
    [['Venit net în perioadă', money(revSum)], ['Bilete în perioadă', F.num(tixSum)], ['Zile cu vânzări', F.num(saleDays)], ['Cea mai bună zi', best < 0 ? '—' : dayLabel(chart.dates[best], { day: 'numeric', month: 'short' }) + ' · ' + money(chart.rev[best])]]
      .forEach(function (t) { box.appendChild(el('div', null, [el('dt', { text: t[0] }), el('dd', { text: t[1] })])); });
    $('oa-plot').setAttribute('aria-label', 'Grafic vânzări, ' + $('oa-chart-p').textContent + ': venit net ' + money(revSum) + ', ' + F.count(tixSum, 'bilet vândut', 'bilete vândute') + '. Folosește săgețile pentru fiecare zi.');
  }
  function campaignMarks() {
    if (!chart || !Array.isArray(camps)) return [];
    return camps.filter(function (c) { return c && ISO.test(String(c.start_date || '')) && chart.dates.indexOf(c.start_date) > -1; })
      .sort(function (a, b) { return a.start_date < b.start_date ? -1 : a.start_date > b.start_date ? 1 : 0; })
      .map(function (c, i) { return { n: i + 1, camp: c, idx: chart.dates.indexOf(c.start_date) }; });
  }
  function running(day) {
    return (camps || []).filter(function (c) { var s = String(c.start_date || ''), e = String(c.end_date || ''); return ISO.test(s) && s <= day && (!e || e >= day); });
  }
  function renderMarks(marks) {
    var box = $('oa-marks');
    box.textContent = '';
    marks.forEach(function (m) {
      var t = CAMPS[m.camp.type];
      box.appendChild(el('li', null, [el('span', { class: 'oa-mk-chip', 'aria-hidden': 'true', text: String(m.n) }), el('span', null, [el('b', { text: F.flat(m.camp.title) || 'Campanie' }), ' · ' + [t ? t.label : '', dayLabel(m.camp.start_date, { day: 'numeric', month: 'short' })].filter(Boolean).join(', ')])]));
    });
    box.hidden = !marks.length;
  }
  function drawChart() {
    var plot = $('oa-plot'), old = plot.querySelector('svg');
    if (old) old.remove();
    parts = null;
    $('oa-plot-msg').hidden = true;
    if (!chart) { renderMarks([]); $('oa-tip').hidden = true; $('oa-plot-msg').hidden = false; return; }
    var marks = campaignMarks(), W = Math.max(260, Math.floor(plot.clientWidth)), narrow = W < 560, H = narrow ? 230 : 290, n = chart.dates.length;
    var padL = narrow ? 42 : 58, padR = narrow ? 30 : 40, padT = marks.length ? 34 : 14, padB = 34, iw = W - padL - padR, ih = H - padT - padB;
    var rMax = niceNum(Math.max.apply(null, chart.rev.concat([0])) / 4) * 4 || 4, tMax = Math.max(1, Math.ceil(niceNum(Math.max.apply(null, chart.tix.concat([0])) / 4))) * 4;
    var y = function (v) { return padT + ih * (1 - v / rMax); }, yt = function (v) { return padT + ih * (1 - v / tMax); };
    var bw = iw / n, barW = Math.max(1, Math.min(26, bw * 0.62));
    var svg = svgNode('svg', { class: 'oa-svg', width: W, height: H, viewBox: '0 0 ' + W + ' ' + H, 'aria-hidden': 'true', focusable: 'false' });
    for (var i = 0; i <= 4; i++) {
      var yy = Math.round(y(rMax * i / 4)) + 0.5;
      svg.appendChild(svgNode('line', { class: 'oa-gl' + (i === 0 ? ' is-zero' : ''), x1: padL, x2: W - padR, y1: yy, y2: yy }));
      if (show.rev) svg.appendChild(svgNode('text', { class: 'oa-ax', x: padL - 8, y: yy + 4, 'text-anchor': 'end' }, compact.format(rMax * i / 4)));
      if (show.tix) svg.appendChild(svgNode('text', { class: 'oa-ax', x: W - padR + 8, y: yy + 4, 'text-anchor': 'start' }, F.num(tMax * i / 4)));
    }
    var band = svgNode('rect', { class: 'oa-band', x: padL, y: padT, width: bw, height: ih, visibility: 'hidden' });
    svg.appendChild(band);
    var pillLabel = function (nums) { return nums.length === 1 ? String(nums[0]) : nums.length === 2 ? nums.join('·') : nums[0] + '–' + nums[nums.length - 1]; };
    var pillW = function (nums) { return Math.max(20, 10 + pillLabel(nums).length * 7); };
    var days = [], pills = []; // one dashed line per day with campaigns; pills that would touch share one ("1–3")
    marks.forEach(function (m) { var d = days[days.length - 1]; if (d && d.idx === m.idx) d.nums.push(m.n); else days.push({ idx: m.idx, nums: [m.n] }); });
    days.forEach(function (d) {
      var x = padL + (d.idx + 0.5) * bw, last = pills[pills.length - 1];
      if (last && x - (last.x0 + last.x1) / 2 < (pillW(last.nums) + pillW(d.nums)) / 2 + 4) { last.nums = last.nums.concat(d.nums); last.xs.push(x); last.x1 = x; }
      else pills.push({ nums: d.nums.slice(), xs: [x], x0: x, x1: x });
    });
    pills.forEach(function (p) {
      var label = pillLabel(p.nums), w = pillW(p.nums), cx = Math.max(w / 2, Math.min(W - w / 2, (p.x0 + p.x1) / 2));
      p.xs.forEach(function (x) { svg.appendChild(svgNode('line', { class: 'oa-mk', x1: x.toFixed(1), x2: x.toFixed(1), y1: 24, y2: padT + ih })); });
      svg.appendChild(svgNode('rect', { class: 'oa-mk-pill', x: (cx - w / 2).toFixed(1), y: 4, width: w, height: 20, rx: 10 }));
      svg.appendChild(svgNode('text', { class: 'oa-mk-n', x: cx.toFixed(1), y: 18, 'text-anchor': 'middle' }, label));
    });
    var bars = chart.rev.map(function (v, k) {
      if (!show.rev || !(v > 0)) return null;
      var b = svgNode('rect', { class: 'oa-bar', x: (padL + k * bw + (bw - barW) / 2).toFixed(2), y: y(v).toFixed(2), width: barW.toFixed(2), height: Math.max(1, padT + ih - y(v)).toFixed(2), rx: Math.min(4, barW / 3).toFixed(2) });
      svg.appendChild(b);
      return b;
    });
    var pts = chart.tix.map(function (v, k) { return [padL + (k + 0.5) * bw, yt(v)]; });
    if (show.tix && chart.tix.some(Boolean) && n > 1) {
      var line = pts.map(function (p, k) { return (k ? 'L' : 'M') + p[0].toFixed(1) + ' ' + p[1].toFixed(1); }).join('');
      svg.appendChild(svgNode('path', { class: 'oa-area', d: line + 'L' + pts[n - 1][0].toFixed(1) + ' ' + (padT + ih) + 'L' + pts[0][0].toFixed(1) + ' ' + (padT + ih) + 'Z' }));
      svg.appendChild(svgNode('path', { class: 'oa-line', d: line }));
    }
    var slots = Math.min(n, narrow ? 4 : 7), seen = {};
    for (var s = 0; s < slots; s++) {
      var idx = slots === 1 ? 0 : Math.round(s * (n - 1) / (slots - 1));
      if (seen[idx]) continue;
      seen[idx] = true;
      var anchor = slots === 1 ? 'middle' : s === 0 ? 'start' : s === slots - 1 ? 'end' : 'middle';
      var lx = anchor === 'start' ? padL : anchor === 'end' ? W - padR : pts[idx][0];
      svg.appendChild(svgNode('text', { class: 'oa-ax', x: lx.toFixed(1), y: H - 6, 'text-anchor': anchor }, dayLabel(chart.dates[idx], { day: 'numeric', month: 'short' })));
    }
    var dot = svgNode('circle', { class: 'oa-pt', cx: 0, cy: 0, r: 4.5, visibility: 'hidden' });
    svg.appendChild(dot);
    svg.appendChild(svgNode('rect', { class: 'oa-hit', x: padL, y: 0, width: iw, height: H }));
    plot.insertBefore(svg, plot.firstChild);
    parts = { band: band, dot: dot, bars: bars, pts: pts, bw: bw, padL: padL, W: W };
    $('oa-plot-msg').hidden = chart.rev.some(Boolean) || chart.tix.some(Boolean);
    renderMarks(marks);
    if (activeDay >= 0 && activeDay < n) showDay(activeDay, false); else hideDay();
  }
  function showDay(i, announce) {
    if (!chart || !parts || i < 0 || i >= chart.dates.length) return;
    activeDay = i;
    parts.band.setAttribute('x', (parts.padL + i * parts.bw).toFixed(2));
    parts.band.setAttribute('visibility', 'visible');
    parts.bars.forEach(function (b, k) { if (b) b.classList.toggle('is-on', k === i); });
    parts.dot.setAttribute('cx', parts.pts[i][0].toFixed(1));
    parts.dot.setAttribute('cy', parts.pts[i][1].toFixed(1));
    parts.dot.setAttribute('visibility', show.tix ? 'visible' : 'hidden');
    var title = cap(dayLabel(chart.dates[i], { weekday: 'long', day: 'numeric', month: 'long', year: 'numeric' })), tip = $('oa-tip');
    var live = running(chart.dates[i]).map(function (c) { return F.flat(c.title) || 'Campanie'; });
    var liveText = live.length ? live.slice(0, 3).join(', ') + (live.length > 3 ? ' și încă ' + (live.length - 3) : '') : '';
    tip.textContent = '';
    tip.appendChild(el('b', { text: title }));
    tip.appendChild(el('dl', null, [el('dt', { text: 'Venit net' }), el('dd', { text: money(chart.rev[i]) }), el('dt', { text: 'Bilete' }), el('dd', { text: F.num(chart.tix[i]) })]));
    if (liveText) tip.appendChild(el('p', { class: 'oa-tip-c', text: (live.length === 1 ? 'Campanie: ' : 'Campanii: ') + liveText }));
    tip.hidden = false;
    var cx = parts.pts[i][0], tw = tip.offsetWidth, left = cx + 16;
    if (left + tw > parts.W - 2) left = cx - 16 - tw;
    tip.style.left = Math.max(0, Math.min(left, parts.W - tw)) + 'px';
    if (announce) $('oa-plot-live').textContent = title + ': venit net ' + money(chart.rev[i]) + ', ' + F.count(chart.tix[i], 'bilet', 'bilete') + (liveText ? '; ' + (live.length === 1 ? 'campanie: ' : 'campanii: ') + liveText : '') + '.';
  }
  function hideDay() {
    activeDay = -1;
    if (parts) {
      parts.band.setAttribute('visibility', 'hidden');
      parts.dot.setAttribute('visibility', 'hidden');
      parts.bars.forEach(function (b) { if (b) b.classList.remove('is-on'); });
    }
    $('oa-tip').hidden = true;
  }
  function indexAt(clientX) {
    var svg = $('oa-plot').querySelector('svg');
    if (!svg || !parts) return -1;
    var r = svg.getBoundingClientRect(), x = (clientX - r.left) * (parts.W / r.width);
    return Math.max(0, Math.min(chart.dates.length - 1, Math.floor((x - parts.padL) / parts.bw)));
  }
  var plotEl = $('oa-plot');
  plotEl.addEventListener('pointermove', function (e) { if (chart && e.target.classList && e.target.classList.contains('oa-hit')) showDay(indexAt(e.clientX), false); });
  plotEl.addEventListener('pointerleave', function () { if (document.activeElement !== plotEl) hideDay(); });
  plotEl.addEventListener('blur', hideDay);
  plotEl.addEventListener('keydown', function (e) {
    if (!chart) return;
    var n = chart.dates.length, i = activeDay;
    if (e.key === 'ArrowRight') i = i < 0 ? 0 : Math.min(n - 1, i + 1);
    else if (e.key === 'ArrowLeft') i = i < 0 ? n - 1 : Math.max(0, i - 1);
    else if (e.key === 'Home') i = 0;
    else if (e.key === 'End') i = n - 1;
    else if (e.key === 'Escape') { hideDay(); return; }
    else return;
    e.preventDefault();
    showDay(i, true);
  });
  window.addEventListener('resize', function () {
    clearTimeout(redrawTimer);
    redrawTimer = setTimeout(function () { if (chart && plotEl.querySelector('svg') && Math.floor(plotEl.clientWidth) !== (parts && parts.W)) drawChart(); }, 150);
  });
  qsa('.oa-tg', root).forEach(function (b) {
    b.addEventListener('click', function () {
      var k = b.getAttribute('data-metric'), other = k === 'rev' ? 'tix' : 'rev';
      if (!has(show, k)) return;
      if (show[k] && !show[other]) show[other] = true; // one of the two always stays on
      show[k] = !show[k];
      qsa('.oa-tg', root).forEach(function (t) { t.setAttribute('aria-pressed', String(!!show[t.getAttribute('data-metric')])); });
      drawChart();
    });
  });

  /* =================== ESTIMATES =================== */
  function renderForecast() {
    var e = data.event || {}, o = data.overview || {}, c = data.chart || {}, du = daysUntil();
    var dates = Array.isArray(c.raw_dates) ? c.raw_dates : [], revs = dates.map(function (_, i) { return num((c.revenue || [])[i]); }), tixs = dates.map(function (_, i) { return num((c.tickets || [])[i]); });
    var n = dates.length, sold = num(o.tickets_sold), net = num(o.net_revenue != null ? o.net_revenue : o.total_revenue), capacity = capacityOf();
    var endBox = $('oa-fc-end'), note = $('oa-fc-note');
    function set(id, v) { $(id).textContent = v; }
    endBox.hidden = false;
    $('oa-fc-end-k').textContent = 'La data activității';
    if (e.is_cancelled) {
      $('oa-fc-next-k').textContent = 'Activitatea a fost anulată';
      set('oa-fc-rev', '—');
      set('oa-fc-tix', '—');
      endBox.hidden = true;
      note.textContent = 'Pentru o activitate anulată nu facem estimări.';
      return;
    }
    if (du !== null && du < 0) {
      $('oa-fc-next-k').textContent = 'Activitatea s-a încheiat';
      set('oa-fc-rev', '—');
      set('oa-fc-tix', '—');
      $('oa-fc-end-k').textContent = 'Rezultatul final';
      set('oa-fc-trev', money(net));
      set('oa-fc-ttix', F.num(sold));
      note.textContent = 'Nu mai sunt zile de vânzare, așa că îți arătăm cifrele finale.';
      return;
    }
    var avgR = 0, avgT = 0;
    if (n) {
      var k = Math.min(7, n), sR = sum(revs), sT = sum(tixs), rR = sum(revs.slice(-k)), rT = sum(tixs.slice(-k));
      if (n > k) { avgR = rR / k * 0.6 + sR / n * 0.4; avgT = rT / k * 0.6 + sT / n * 0.4; } else { avgR = sR / n; avgT = sT / n; }
    }
    var left = capacity > 0 ? Math.max(0, capacity - sold) : Infinity;
    function est(days) {
      var t = avgT * days, r = avgR * days, capped = false;
      if (left !== Infinity && t >= left) { if (t > left) { r = t > 0 ? r * left / t : 0; t = left; } capped = true; } // reaches the seats left
      return { t: Math.round(t), r: Math.round(r * 100) / 100, capped: capped };
    }
    $('oa-fc-next-k').textContent = du === null || du >= 7 ? 'Următoarele 7 zile' : du === 0 ? 'Azi este ziua activității' : 'Până la activitate · ' + F.count(du, 'zi', 'zile');
    if (du === 0) { set('oa-fc-rev', '—'); set('oa-fc-tix', '—'); }
    else { var a = est(du === null ? 7 : Math.min(7, du)); set('oa-fc-rev', '+' + money(a.r)); set('oa-fc-tix', '+' + F.num(a.t)); }
    var end = du === null ? null : est(du);
    if (!end) endBox.hidden = true;
    else { set('oa-fc-trev', money(net + end.r)); set('oa-fc-ttix', F.num(sold + end.t)); }
    var bits = [];
    if (!(avgT > 0) && !(avgR > 0)) bits.push('În perioada aleasă nu au fost vânzări, așa că estimarea nu adaugă nimic. O perioadă mai lungă dă un ritm mai sigur.');
    else bits.push('Ritmul folosit: ' + approx(avgT, 'bilet', 'bilete') + ' și ~' + money(avgR) + ' pe zi.');
    if (end && end.capped) bits.push('Estimarea se oprește la capacitatea de ' + F.count(capacity, 'loc', 'locuri') + '.');
    if (du === null) bits.push('Activitatea nu are o dată, așa că nu estimăm totalul final.');
    note.textContent = bits.join(' ');
  }

  /* =================== TICKET TYPES =================== */
  function renderTypes() {
    var list = (Array.isArray(data.ticket_performance) ? data.ticket_performance.slice() : []).sort(function (a, b) { return num(b.sold) - num(a.sold); });
    var body = $('oa-types'), foot = $('oa-types-foot'), views = num((data.overview || {}).page_views), all = S.period === 'tot';
    $('oa-table').classList.toggle('is-all', all);
    $('oa-types-p').textContent = 'Conversia: bilete vândute la 100 de vizualizări ale paginii. ' + (all ? 'Alege 7, 30 sau 90 de zile ca să vezi și trendul.' : 'Trendul: biletele din ' + PERIODS[S.period].label.toLowerCase() + ', față de perioada dinainte.');
    body.textContent = '';
    foot.textContent = '';
    if (!list.length) { body.appendChild(el('tr', null, el('td', { colspan: '6', class: 'oa-empty-td' }, el('p', { class: 'oa-empty-p', text: 'Activitatea nu are tipuri de bilete.' })))); return; }
    var totalRev = 0, totalSold = 0;
    list.forEach(function (t, i) {
      var sold = num(t.sold), rev = num(t.revenue), capq = t.capacity == null || t.capacity === '' ? -1 : num(t.capacity), color = COLORS[i % COLORS.length], tr = Math.round(num(t.trend));
      totalRev += rev;
      totalSold += sold;
      var type = el('div', { class: 'oa-type' }, [el('i', { 'aria-hidden': 'true' }), el('b', null, [F.flat(t.name) || 'Bilet', t.is_invitation ? el('small', { text: 'titlu gratuit (invitație)' }) : t.is_entry_ticket ? el('small', { text: 'încasat de operator' }) : null])]);
      type.querySelector('i').style.setProperty('--c', color);
      var soldCell = el('td', { class: 'is-num' }, [F.num(sold)]);
      if (capq > 0) {
        var mini = el('span', { class: 'oa-mini', 'aria-hidden': 'true' }, el('i'));
        mini.firstChild.style.width = Math.min(100, sold / capq * 100).toFixed(1) + '%';
        soldCell.appendChild(el('span', { class: 'oa-sub', text: 'din ' + F.num(capq) }));
        soldCell.appendChild(mini);
      }
      var trend = tr > 0 ? el('span', { class: 'oa-trend is-up' }, [icon('trend-up'), '+' + F.num(tr) + '%'])
        : tr < 0 ? el('span', { class: 'oa-trend is-down' }, [icon('trend-down'), '−' + F.num(-tr) + '%'])
        : el('span', { class: 'oa-trend', text: '0%' });
      body.appendChild(el('tr', null, [
        el('td', null, type),
        el('td', { class: 'is-num', text: money(t.price) }),
        soldCell,
        el('td', { class: 'is-num', text: money(rev) }),
        el('td', { class: 'is-num is-conv', text: views > 0 ? F.pct(t.conversion_rate, 1) : '—' }),
        el('td', { class: 'is-num is-trend' }, trend),
      ]));
    });
    foot.appendChild(el('tr', null, [el('td', { text: 'Total' }), el('td'), el('td', { class: 'is-num', text: F.num(totalSold) }), el('td', { class: 'is-num', text: money(totalRev) }), el('td', { class: 'is-conv' }), el('td', { class: 'is-trend' })]));
  }

  /* =================== TRAFFIC + LOCATIONS + SALES =================== */
  function renderTraffic() {
    var box = $('oa-traffic'), list = (Array.isArray(data.traffic_sources) ? data.traffic_sources : []).filter(function (s) { return num(s.visitors) > 0; });
    $('oa-traffic-p').textContent = PERIODS[S.period].label + ' · vizitatori unici pe sursă.';
    box.textContent = '';
    if (!list.length) { box.appendChild(el('li', { class: 'oa-empty-p', text: 'Nu există date despre trafic în perioada aleasă.' })); return; }
    list.sort(function (a, b) { return num(b.visitors) - num(a.visitors); });
    var total = sum(list.map(function (s) { return s.visitors; }));
    list.forEach(function (s) {
      var pct = total ? num(s.visitors) / total * 100 : 0, bar = el('i');
      bar.style.width = pct.toFixed(2) + '%';
      box.appendChild(el('li', null, [
        el('b', { text: SOURCES[s.source] || F.flat(s.source) || 'Direct' }),
        el('span', { class: 'oa-bar-v' }, [F.count(s.visitors, 'vizitator', 'vizitatori'), el('small', { text: F.pct(pct, 0) })]),
        el('span', { class: 'oa-meter', 'aria-hidden': 'true' }, bar),
        num(s.revenue) > 0 ? el('span', { class: 'oa-sub', text: 'Vânzări atribuite: ' + money(s.revenue) }) : null,
      ]));
    });
  }
  function place(l) {
    var city = F.flat(l.city).trim(), cc = String(l.country_code || l.country || '').toUpperCase();
    if (city === 'Unknown') city = '';
    return { city: city, cc: cc, country: countryName(cc), visitors: num(l.visitors || l.count) };
  }
  function renderLocations() {
    var box = $('oa-locations'), list = (Array.isArray(data.top_locations) ? data.top_locations : []).map(place).filter(function (p) { return p.visitors > 0; }).slice(0, 8);
    $('oa-loc-p').textContent = PERIODS[S.period].label + ' · vizitatorii paginii, după oraș.';
    box.textContent = '';
    if (!list.length) { box.appendChild(el('li', { class: 'oa-empty-p', text: 'Nu există date despre locații în perioada aleasă.' })); return; }
    list.forEach(function (p, i) {
      box.appendChild(el('li', null, [
        el('span', { class: 'oa-rank-n', text: String(i + 1) }),
        el('span', null, [el('b', { text: p.city || p.country || 'Necunoscut' }), p.city && p.country ? el('small', { text: p.country }) : !p.city ? el('small', { text: 'oraș necunoscut' }) : null]),
        el('span', { class: 'oa-rank-v', text: F.num(p.visitors) }),
      ]));
    });
  }
  function renderSales() {
    var box = $('oa-sales'), rows = Array.isArray(data.recent_sales) ? data.recent_sales.slice(0, 8) : [];
    box.textContent = '';
    if (!rows.length) { box.appendChild(el('li', { class: 'oa-empty-p', text: 'Nicio vânzare încă.' })); return; }
    rows.forEach(function (s) {
      var name = F.flat(s.buyer_name).trim() || 'Client';
      box.appendChild(el('li', null, [
        el('span', { class: 'oa-av', 'aria-hidden': 'true', text: initials(name) }),
        el('div', { class: 'oa-sale-t' }, [el('b', { text: name }), el('span', { class: 'oa-sub', text: [roAgo(s.time_ago), num(s.tickets) > 0 ? F.count(s.tickets, 'bilet', 'bilete') : ''].filter(Boolean).join(' · ') })]),
        el('span', { class: 'oa-sale-v', text: '+' + money(s.amount) }),
      ]));
    });
  }

  /* =================== MAP =================== */
  function project(lat, lng) { return [(lng - MAP.lon0) * MAP.k * MAP.s, (MAP.lat0 - lat) * MAP.s]; }
  function renderMap() {
    var list = (data && Array.isArray(data.top_locations) ? data.top_locations : []).map(place).filter(function (p) { return p.visitors > 0; });
    var dots = $('oa-map-dots'), side = $('oa-map-list'), total = sum(list.map(function (p) { return p.visitors; })), byCity = {}, rows = [];
    dots.textContent = '';
    side.textContent = '';
    $('oa-map-p').textContent = 'De unde au venit vizitatorii paginii activității · ' + PERIODS[S.period].label.toLowerCase() + '.';
    list.forEach(function (p) {
      var c = p.city ? CITIES[norm(p.city)] : null;
      if (c && (!p.cc || p.cc === c.cc)) {
        if (!byCity[c.name]) { byCity[c.name] = { name: c.name, city: c, visitors: 0, sub: countryName(c.cc) }; rows.push(byCity[c.name]); }
        byCity[c.name].visitors += p.visitors;
      } else {
        rows.push({ name: p.city || p.country || 'Necunoscut', visitors: p.visitors, sub: !p.city ? 'oraș necunoscut' : p.cc === 'RO' || p.cc === 'MD' ? p.country + ' · nu apare pe hartă' : p.country });
      }
    });
    rows.sort(function (a, b) { return b.visitors - a.visitors; });
    var placed = rows.filter(function (r) { return r.city; }), max = Math.max.apply(null, placed.map(function (r) { return r.visitors; }).concat([1])), labels = [];
    placed.forEach(function (r, i) {
      var xy = project(r.city.lat, r.city.lng), rad = 6 + 22 * Math.sqrt(r.visitors / max), g = svgNode('g', null);
      g.appendChild(svgNode('title', null, r.name + ': ' + F.count(r.visitors, 'vizitator', 'vizitatori')));
      g.appendChild(svgNode('circle', { class: 'oa-city' + (i < 3 ? ' is-top' : ''), cx: xy[0].toFixed(1), cy: xy[1].toFixed(1), r: rad.toFixed(1) }));
      dots.appendChild(g);
      var ly = xy[1] - rad - 6;
      if (i < 6 && !labels.some(function (l) { return Math.abs(l[0] - xy[0]) < 90 && Math.abs(l[1] - ly) < 20; })) {
        labels.push([xy[0], ly]);
        dots.appendChild(svgNode('text', { class: 'oa-city-l', x: xy[0].toFixed(1), y: Math.max(14, ly).toFixed(1), 'text-anchor': 'middle' }, r.name));
      }
    });
    var top = rows[0] ? rows[0].visitors : 1;
    rows.forEach(function (r, i) {
      var bar = el('i');
      bar.style.width = (r.visitors / top * 100).toFixed(1) + '%';
      side.appendChild(el('li', null, [
        el('span', { class: 'oa-rank-n', text: String(i + 1) }),
        el('span', null, [el('b', { text: r.name }), r.sub ? el('small', { text: r.sub }) : null]),
        el('span', { class: 'oa-rank-v', text: F.num(r.visitors) }),
        el('span', { class: 'oa-meter', 'aria-hidden': 'true' }, bar),
      ]));
    });
    $('oa-map-sum').textContent = total ? F.count(total, 'vizitator', 'vizitatori') + ' din ' + F.count(rows.length, 'locație', 'locații') : 'Nu avem vizitatori cu locația cunoscută în perioada aleasă.';
    $('oa-map-cap').textContent = placed.length
      ? 'Harta României cu ' + F.count(placed.length, 'oraș', 'orașe') + ': ' + placed.slice(0, 3).map(function (r) { return r.name + ' ' + F.num(r.visitors); }).join(', ') + (placed.length > 3 ? ' și altele' : '') + '. Cercurile cresc cu numărul de vizitatori.'
      : 'Harta României. Niciun oraș de pus pe hartă în perioada aleasă.';
    $('oa-map-note').textContent = list.length >= 10 ? 'Sunt primele 10 locații după numărul de vizitatori.' : '';
  }
  function openMap(from) {
    if (!data) return;
    renderMap();
    fallbackFocus = $('oa-map-open');
    openDialog($('oa-map-d'), from);
  }
  $('oa-map-open').addEventListener('click', function () { openMap(this); });
  $('oa-loc-map').addEventListener('click', function () { openMap(this); });

  /* =================== GOALS =================== */
  function loadGoals(keep) {
    var my = ++goalsSeq, id = S.event;
    if (!keep) { goals = null; skel($('oa-goals'), null); }
    O.api('/organizer/events/' + id + '/goals', { quiet: true }).then(function (r) {
      if (my !== goalsSeq) return;
      var d = r && r.data;
      goals = (d && Array.isArray(d.goals) ? d.goals : Array.isArray(d) ? d : []).filter(function (g) { return g && g.id != null; });
      var keep = focusKey($('oa-goals'));
      renderGoals();
      restoreFocus($('oa-goals'), keep, $('oa-goal-add'));
      renderGoalMeter();
    }, function () {
      if (my !== goalsSeq) return;
      goals = null;
      emptyBox($('oa-goals'), 'p', 'Nu am putut încărca obiectivele.', function () { loadGoals(false); });
      renderGoalMeter();
    });
  }
  /** A list re-rendered under the focus (after a save or a delete) gives it back to the same button, or to "Adaugă". */
  function focusKey(box) { var a = document.activeElement; return a && a !== document.body && box.contains(a) ? a.getAttribute('data-focus') || '' : null; }
  function restoreFocus(box, key, fallback) {
    if (key === null) return;
    var same = key ? box.querySelector('[data-focus="' + key + '"]') : null;
    if (same) same.focus();
    else if (fallback && !fallback.disabled) fallback.focus();
  }
  function cardActions(kind, item) {
    if (!/^\d+$/.test(String(item.id))) return null;
    var what = kind === 'goal' ? 'obiectivul ' + (F.flat(item.name) || '') : 'campania ' + (F.flat(item.title) || '');
    var edit = el('button', { class: 'oa-ib', type: 'button', 'aria-label': 'Modifică ' + what.trim(), title: 'Modifică', 'data-focus': kind + '-edit-' + item.id }, icon('pencil-simple'));
    var del = el('button', { class: 'oa-ib is-del', type: 'button', 'aria-label': 'Șterge ' + what.trim(), title: 'Șterge', 'data-focus': kind + '-del-' + item.id }, icon('trash'));
    edit.addEventListener('click', function () { if (kind === 'goal') openGoal(item, null, edit); else openCamp(item, edit); });
    del.addEventListener('click', function () { openDelete(kind, item, del); });
    return el('div', { class: 'oa-card-act' }, [edit, del]);
  }
  function renderGoals() {
    var box = $('oa-goals');
    box.textContent = '';
    if (!goals.length) { box.appendChild(el('p', { class: 'oa-empty-p', text: 'Niciun obiectiv setat. Un obiectiv îți arată cât mai ai până la țintă.' })); return; }
    goals.forEach(function (g) {
      var T = GOALS[g.type], target = num(g.target_value), cur = num(g.current_value);
      var real = Math.max(0, target > 0 ? cur / target * 100 : num(g.progress_percent)), p = Math.min(100, real);
      var done = g.status === 'achieved' || !!g.is_achieved || p >= 100, cancelled = g.status === 'cancelled', missed = !done && !cancelled && (g.status === 'missed' || !!g.is_overdue);
      var deadline = ISO.test(String(g.deadline || '').slice(0, 10)) ? String(g.deadline).slice(0, 10) : '';
      var status = cancelled ? ['Obiectiv anulat.', '']
        : done ? ['Obiectiv atins' + (g.achieved_at ? ' pe ' + dayLabel(naiveDay(g.achieved_at)) : '') + '.', 'is-done']
        : missed ? ['Termenul a trecut' + (deadline ? ' (' + dayLabel(deadline) + ')' : '') + ' fără să atingi ținta.', 'is-late']
        : ['Mai ai ' + goalAmount(g.type, Math.max(0, target - cur)) + ' până la țintă' + (deadline ? ' · termen ' + dayLabel(deadline) + (num(g.days_remaining) > 0 ? ', ' + F.count(g.days_remaining, 'zi rămasă', 'zile rămase') : '') : '') + '.', ''];
      var bar = el('i', { class: done ? null : p >= 50 ? 'is-mid' : 'is-low' });
      bar.style.width = p.toFixed(1) + '%';
      box.appendChild(el('article', { class: 'oa-card' + (done ? ' is-done' : '') }, [
        el('div', { class: 'oa-card-top' }, [
          el('div', { class: 'oa-card-t' }, [el('b', { text: F.flat(g.name) || (T ? T.label : 'Obiectiv') }), el('span', { class: 'org-tag is-muted', text: T ? T.label : 'Obiectiv' })]),
          cardActions('goal', g),
        ]),
        el('p', { class: 'oa-goal-v' }, [el('strong', { text: goalValue(g.type, cur) }), el('span', { text: ' / ' + goalAmount(g.type, target) })]),
        el('span', { class: 'oa-meter', role: 'progressbar', 'aria-valuemin': '0', 'aria-valuemax': '100', 'aria-valuenow': String(Math.round(p)), 'aria-label': 'Progres: ' + (F.flat(g.name) || 'obiectiv') }, bar),
        el('p', { class: 'oa-goal-s ' + status[1] }, [el('b', { text: F.pct(real, 0) }), ' · ' + status[0]]),
      ]));
    });
  }

  /* =================== CAMPAIGNS =================== */
  function loadCamps(keep) {
    var my = ++campsSeq, id = S.event;
    if (!keep) { camps = null; skel($('oa-camps'), null); }
    O.api('/organizer/events/' + id + '/milestones', { quiet: true }).then(function (r) {
      if (my !== campsSeq) return;
      var d = r && r.data;
      camps = (d && Array.isArray(d.milestones) ? d.milestones : Array.isArray(d) ? d : []).filter(function (c) { return c && c.id != null; });
      var keep = focusKey($('oa-camps'));
      renderCamps();
      restoreFocus($('oa-camps'), keep, $('oa-camp-add'));
      if (chart) drawChart();
    }, function () {
      if (my !== campsSeq) return;
      camps = null;
      emptyBox($('oa-camps'), 'p', 'Nu am putut încărca campaniile.', function () { loadCamps(false); });
    });
  }
  function campStatus(c) {
    var t = F.ymd(), s = String(c.start_date || ''), e = String(c.end_date || '');
    if (c.is_active === false || c.is_active === 0 || c.is_active === '0') return ['Oprită', 'is-muted'];
    if (s && s > t) return ['Programată', 'is-info'];
    if (e && e < t) return ['Încheiată', 'is-muted'];
    return ['În desfășurare', 'is-ok'];
  }
  function utmString(c) {
    var p = new URLSearchParams();
    ['utm_source', 'utm_medium', 'utm_campaign'].forEach(function (k) { if (c[k]) p.set(k, String(c[k])); });
    return p.toString();
  }
  function copyText(text) {
    if (navigator.clipboard && window.isSecureContext) return navigator.clipboard.writeText(text);
    return new Promise(function (resolve, reject) {
      var ta = el('textarea', { class: 'sr', readonly: true });
      ta.value = text;
      document.body.appendChild(ta);
      ta.select();
      var ok = false;
      try { ok = document.execCommand('copy'); } catch (e) { ok = false; }
      ta.remove();
      if (ok) resolve(); else reject(new Error('copy'));
    });
  }
  function renderCamps() {
    var box = $('oa-camps');
    box.textContent = '';
    if (!camps.length) { box.appendChild(el('p', { class: 'oa-empty-p', text: 'Nicio campanie înregistrată. Adaugă reclamele, emailurile sau anunțurile ca să vezi ce au adus.' })); return; }
    camps.forEach(function (c) {
      var T = CAMPS[c.type] || { label: 'Campanie', ad: !!c.is_ad_campaign }, budget = num(c.budget), revenue = num(c.attributed_revenue), roi = budget > 0 ? Math.round((revenue - budget) / budget * 100) : null, st = campStatus(c);
      var start = ISO.test(String(c.start_date || '')) ? c.start_date : '', end = ISO.test(String(c.end_date || '')) ? c.end_date : '';
      var dates = start && end ? rangeLabel(start, end) : start ? 'din ' + dayShort(start) : '';
      var figs = [['Buget', budget > 0 ? money(budget) : '—'], ['Venituri atribuite', money(revenue), 'is-net'], ['Conversii', F.num(c.conversions)]];
      if (num(c.impressions) > 0) figs.push(['Afișări', F.num(c.impressions)]);
      if (num(c.clicks) > 0) figs.push(['Clickuri', F.num(c.clicks)]);
      if (num(c.roas) > 0) figs.push(['ROAS', dec2.format(num(c.roas)) + '×']);
      var utm = (T.ad || c.type === 'email') ? utmString(c) : '', utmRow = null;
      if (utm) {
        var copy = el('button', { class: 'oa-copy', type: 'button', 'aria-label': 'Copiază parametrii UTM ai campaniei ' + (F.flat(c.title) || ''), 'data-focus': 'camp-copy-' + c.id }, [icon('copy'), 'Copiază']);
        copy.addEventListener('click', function () {
          copyText(utm).then(function () { O.flash('Parametrii UTM au fost copiați.'); }, function () { O.flash('Nu am putut copia. Selectează textul și copiază-l manual.', true); });
        });
        utmRow = el('div', { class: 'oa-utm' }, [el('code', { text: utm, title: utm }), copy]);
      }
      box.appendChild(el('article', { class: 'oa-card' }, [
        el('div', { class: 'oa-card-top' }, [
          el('div', { class: 'oa-card-t' }, [el('b', { text: F.flat(c.title) || 'Campanie' }), el('span', { class: 'oa-sub', text: [T.label, dates].filter(Boolean).join(' · ') })]),
          cardActions('camp', c),
        ]),
        el('div', { class: 'oa-card-tags' }, [el('span', { class: 'org-tag ' + st[1], text: st[0] }), roi !== null ? el('span', { class: 'org-tag ' + (roi >= 0 ? 'is-ok' : 'is-bad'), text: (roi >= 0 ? '+' : '−') + F.num(Math.abs(roi)) + '% ROI' }) : null]),
        el('dl', { class: 'oa-figs' }, figs.map(function (f) { return el('div', null, [el('dt', { text: f[0] }), el('dd', { class: f[2] || null, text: f[1] })]); })),
        F.flat(c.description).trim() ? el('p', { class: 'oa-card-p', text: F.flat(c.description).trim() }) : null,
        utmRow,
      ]));
    });
  }

  /* =================== DIALOGS =================== */
  function openDialog(d, from) {
    opener = from || document.activeElement;
    openerKey = opener && opener.getAttribute ? opener.getAttribute('data-focus') : null;
    if (typeof d.showModal === 'function') d.showModal(); else d.setAttribute('open', '');
  }
  function closeDialog(d) {
    if (!d.open && !d.hasAttribute('open')) return;
    if (typeof d.close === 'function') d.close();
    else { d.removeAttribute('open'); d.dispatchEvent(new Event('close')); }
  }
  function closeDialogs() { qsa('.oa-dialog', root).forEach(function (d) { d.removeAttribute('data-busy'); closeDialog(d); }); }
  qsa('.oa-dialog', root).forEach(function (d) {
    d.addEventListener('click', function (e) {
      if (d.hasAttribute('data-busy')) return;
      if (e.target.closest('[data-close]') || (e.target === d && d.id === 'oa-map-d')) closeDialog(d);
    });
    d.addEventListener('cancel', function (e) { if (d.hasAttribute('data-busy')) e.preventDefault(); });
    d.addEventListener('close', function () {
      // the list may have been drawn again before the dialog closed: the same button, found by its key, or "Adaugă"
      var back = opener && document.contains(opener) ? opener : (openerKey && root.querySelector('[data-focus="' + openerKey + '"]')) || fallbackFocus;
      opener = null;
      openerKey = null;
      if (back && typeof back.focus === 'function' && !back.disabled) back.focus();
    });
  });
  function busyBtn(btn, on, text) {
    var l = btn.querySelector('[data-label]');
    if (on) { btn.setAttribute('aria-busy', 'true'); btn.setAttribute('data-idle', l.textContent); l.textContent = text; }
    else { btn.removeAttribute('aria-busy'); if (btn.hasAttribute('data-idle')) l.textContent = btn.getAttribute('data-idle'); btn.removeAttribute('data-idle'); }
  }
  function fieldErr(id, msg) {
    var f = $(id), e = $(id + '-err');
    if (e) { e.textContent = msg || ''; e.hidden = !msg; }
    if (f) { if (msg) f.setAttribute('aria-invalid', 'true'); else f.removeAttribute('aria-invalid'); }
  }
  function formErr(id, msg) { var b = $(id); b.textContent = msg || ''; b.hidden = !msg; }
  function saveError(err, notFound) {
    var s = err && err.status;
    if (s === 404) return notFound;
    if (s === 422) return 'Unele câmpuri nu sunt completate corect. Verifică-le și încearcă din nou.';
    if (s === 429) return 'Prea multe încercări într-un timp scurt. Așteaptă un minut și încearcă din nou.';
    if (s === 0 || s == null) return 'Nu am putut ajunge la server. Verifică conexiunea și încearcă din nou.';
    return 'Nu am putut salva. Încearcă din nou.';
  }
  function markServerErrors(err, map) {
    var errors = (err && err.errors) || (err && err.data && err.data.errors) || null, first = null;
    if (!errors || typeof errors !== 'object') return;
    Object.keys(errors).forEach(function (k) { if (map[k]) { fieldErr(map[k], 'Verifică această valoare.'); if (!first) first = map[k]; } });
    if (first && $(first)) $(first).focus();
  }

  /* ----- goal ----- */
  var goalType = $('oa-goal-type');
  function syncGoalType() {
    var type = editGoal ? editGoal.type : goalType.value, T = GOALS[type] || GOALS.tickets;
    $('oa-goal-type-help').textContent = editGoal ? T.help + ' Tipul unui obiectiv nu se mai poate schimba.' : T.help;
    $('oa-goal-unit').textContent = T.unit;
    $('oa-goal-target-l').textContent = T.target;
    $('oa-goal-target').placeholder = 'ex: ' + T.example;
    $('oa-goal-target').setAttribute('inputmode', type === 'tickets' || type === 'visitors' ? 'numeric' : 'decimal');
    $('oa-goal-name').placeholder = T.name;
    $('oa-goal-target').parentNode.classList.toggle('is-long', T.unit.length > 3);
  }
  goalType.addEventListener('change', syncGoalType);
  function openGoal(g, preset, from) {
    if (!data) return;
    editGoal = g || null;
    formEvent = S.event;
    $('oa-goal-h').textContent = g ? 'Modifică obiectivul' : 'Obiectiv nou';
    goalType.value = g && GOALS[g.type] ? g.type : preset && GOALS[preset] ? preset : 'tickets';
    goalType.disabled = !!g;
    $('oa-goal-name').value = g ? F.flat(g.name) : '';
    $('oa-goal-target').value = g ? plainFmt.format(g.type === 'revenue' || g.type === 'conversion_rate' ? num(g.target_value) / 100 : num(g.target_value)) : '';
    var dl = $('oa-goal-deadline'), day = activityDay(), today = F.ymd();
    dl.value = g && ISO.test(String(g.deadline || '').slice(0, 10)) ? String(g.deadline).slice(0, 10) : '';
    dl.min = g ? '' : today;
    dl.max = day && day >= today ? day : '';
    $('oa-goal-deadline-help').textContent = dl.max ? 'Cel târziu ' + dayLabel(dl.max) + ', ziua activității.' : '';
    ['oa-goal-name', 'oa-goal-target', 'oa-goal-deadline'].forEach(function (id) { fieldErr(id, ''); });
    formErr('oa-goal-err', '');
    $('oa-goal-go').querySelector('[data-label]').textContent = g ? 'Salvează' : 'Adaugă obiectivul';
    syncGoalType();
    fallbackFocus = $('oa-goal-add');
    openDialog($('oa-goal-d'), from);
    (g ? $('oa-goal-name') : goalType).focus();
  }
  $('oa-goal-add').addEventListener('click', function () { openGoal(null, null, this); });
  $('oa-s-revenue-set').addEventListener('click', function () { openGoal(null, 'revenue', this); });
  $('oa-goal-form').addEventListener('submit', function (ev) {
    ev.preventDefault();
    var d = $('oa-goal-d'), btn = $('oa-goal-go');
    if (btn.getAttribute('aria-busy') === 'true') return;
    var g = editGoal, type = g ? g.type : goalType.value, T = GOALS[type] || GOALS.tickets, name = $('oa-goal-name').value.trim(), raw = $('oa-goal-target').value.trim(), target = parseAmount(raw);
    var dl = $('oa-goal-deadline'), deadline = dl.value, bad = null;
    function need(id, msg) { fieldErr(id, msg); if (msg && !bad) bad = id; }
    need('oa-goal-name', name ? '' : 'Scrie un nume pentru obiectiv.');
    need('oa-goal-target', !raw ? 'Scrie ținta, de exemplu ' + T.example + '.'
      : !isFinite(target) ? 'Scrie un număr, de exemplu ' + T.example + '.'
      : (type === 'tickets' || type === 'visitors') && Math.floor(target) !== target ? 'Scrie un număr întreg.'
      : target < 1 ? 'Ținta trebuie să fie cel puțin 1.'
      : type === 'conversion_rate' && target > 100 ? 'Rata de conversie nu poate trece de 100%.'
      : target > 1e9 ? 'Ținta este prea mare.' : '');
    var changed = !g || deadline !== String(g.deadline || '').slice(0, 10);
    need('oa-goal-deadline', deadline && !ISO.test(deadline) ? 'Alege o dată validă.'
      : deadline && changed && dl.min && deadline < dl.min ? 'Alege o dată de azi încolo.'
      : deadline && changed && dl.max && deadline > dl.max ? 'Alege o dată până la ' + dayLabel(dl.max) + ', ziua activității.' : '');
    formErr('oa-goal-err', '');
    if (bad) { $(bad).focus(); return; }
    var body = { name: name, target_value: Math.round(target * 100) / 100, deadline: deadline || null };
    if (!g) body.type = type;
    var id = formEvent;
    busyBtn(btn, true, g ? 'Se salvează…' : 'Se adaugă…');
    d.setAttribute('data-busy', '');
    O.api('/organizer/events/' + id + '/goals' + (g ? '/' + g.id : ''), { method: g ? 'PUT' : 'POST', body: body }).then(function () {
      d.removeAttribute('data-busy');
      busyBtn(btn, false);
      closeDialog(d);
      O.flash(g ? 'Obiectivul a fost salvat.' : 'Obiectivul a fost adăugat.');
      if (id === S.event) loadGoals(true);
    }).catch(function (err) {
      d.removeAttribute('data-busy');
      busyBtn(btn, false);
      if (err && err.status === 401) return;
      formErr('oa-goal-err', saveError(err, 'Nu am mai găsit obiectivul. Poate a fost șters între timp; reîncarcă pagina.'));
      if (err && err.status === 422) markServerErrors(err, { type: 'oa-goal-type', name: 'oa-goal-name', target_value: 'oa-goal-target', deadline: 'oa-goal-deadline' });
    });
  });

  /* ----- campaign ----- */
  var campType = $('oa-camp-type');
  function syncCampType() {
    var edit = !!editCamp, type = edit ? editCamp.type : campType.value, T = CAMPS[type] || CAMPS.custom;
    $('oa-camp-track').hidden = edit || !T.ad;
    $('oa-camp-impact').hidden = edit || T.ad;
    $('oa-camp-results').hidden = !(edit && T.ad);
    $('oa-camp-active-f').hidden = !edit;
    $('oa-camp-utm_source').placeholder = T.source || 'organic';
    $('oa-camp-utm_medium').placeholder = T.medium || 'cpc';
    $('oa-camp-utm_campaign').placeholder = slug($('oa-camp-title').value) || 'din numele campaniei';
    $('oa-camp-type-help').textContent = edit ? 'Tipul unei campanii nu se mai poate schimba.' : T.ad ? 'Reclamă plătită: vânzările se atribuie după parametrii UTM din link.' : 'Apare în grafic, ca să vezi ce s-a schimbat după ea.';
  }
  campType.addEventListener('change', syncCampType);
  $('oa-camp-title').addEventListener('input', function () { $('oa-camp-utm_campaign').placeholder = slug(this.value) || 'din numele campaniei'; });
  function openCamp(c, from) {
    if (!data) return;
    editCamp = c || null;
    formEvent = S.event;
    $('oa-campd-h').textContent = c ? 'Modifică campania' : 'Campanie nouă';
    campType.value = c ? (CAMPS[c.type] ? c.type : 'custom') : 'campaign_fb';
    campType.disabled = !!c;
    $('oa-camp-title').value = c ? F.flat(c.title) : '';
    $('oa-camp-start').value = c ? (ISO.test(String(c.start_date || '')) ? c.start_date : '') : F.ymd();
    $('oa-camp-end').value = c && ISO.test(String(c.end_date || '')) ? c.end_date : '';
    $('oa-camp-budget').value = c && num(c.budget) > 0 ? plainFmt.format(num(c.budget)) : '';
    $('oa-camp-desc').value = c ? F.flat(c.description) : '';
    ['oa-camp-pid', 'oa-camp-utm_source', 'oa-camp-utm_medium', 'oa-camp-utm_campaign', 'oa-camp-utm_content', 'oa-camp-base', 'oa-camp-post'].forEach(function (id) { $(id).value = ''; });
    $('oa-camp-metric').value = '';
    $('oa-camp-impr').value = c && c.impressions != null && c.impressions !== '' ? String(Math.round(num(c.impressions))) : '';
    $('oa-camp-clicks').value = c && c.clicks != null && c.clicks !== '' ? String(Math.round(num(c.clicks))) : '';
    $('oa-camp-active').checked = !c || !(c.is_active === false || c.is_active === 0 || c.is_active === '0');
    var created = naiveDay(data.event && data.event.created_at), day = activityDay();
    $('oa-camp-start').min = c ? '' : created;
    $('oa-camp-end').max = day;
    $('oa-camp-dates-help').textContent = [created && !c ? 'Activitatea a fost adăugată pe ' + dayLabel(created) + '.' : '', day ? 'Campania se poate termina cel târziu pe ' + dayLabel(day) + '.' : ''].filter(Boolean).join(' ');
    ['oa-camp-title', 'oa-camp-start', 'oa-camp-end', 'oa-camp-budget', 'oa-camp-base', 'oa-camp-post', 'oa-camp-impr', 'oa-camp-clicks'].forEach(function (id) { fieldErr(id, ''); });
    formErr('oa-camp-err', '');
    $('oa-camp-go').querySelector('[data-label]').textContent = c ? 'Salvează' : 'Adaugă campania';
    syncCampType();
    fallbackFocus = $('oa-camp-add');
    openDialog($('oa-camp-d'), from);
    $('oa-camp-title').focus();
  }
  $('oa-camp-add').addEventListener('click', function () { openCamp(null, this); });
  $('oa-camp-form').addEventListener('submit', function (ev) {
    ev.preventDefault();
    var d = $('oa-camp-d'), btn = $('oa-camp-go');
    if (btn.getAttribute('aria-busy') === 'true') return;
    var c = editCamp, type = c ? c.type : campType.value, T = CAMPS[type] || CAMPS.custom, bad = null;
    function need(id, msg) { fieldErr(id, msg); if (msg && !bad) bad = id; }
    var title = $('oa-camp-title').value.trim(), startIn = $('oa-camp-start'), endIn = $('oa-camp-end'), start = startIn.value, end = endIn.value;
    var startChanged = !c || start !== String(c.start_date || ''), endChanged = !c || end !== String(c.end_date || '');
    need('oa-camp-title', title ? '' : 'Scrie un nume pentru campanie.');
    need('oa-camp-start', !start ? 'Alege ziua în care începe campania.' : !ISO.test(start) ? 'Alege o dată validă.'
      : startChanged && startIn.min && start < startIn.min ? 'Alege o dată de pe ' + dayLabel(startIn.min) + ' încolo.' : '');
    need('oa-camp-end', end && !ISO.test(end) ? 'Alege o dată validă.'
      : end && ISO.test(start) && end < start ? 'Campania nu se poate termina înainte să înceapă.'
      : end && endChanged && endIn.max && end > endIn.max ? 'Alege o dată până la ' + dayLabel(endIn.max) + '.' : '');
    var budgetRaw = $('oa-camp-budget').value.trim(), budget = budgetRaw ? parseAmount(budgetRaw) : null;
    need('oa-camp-budget', budgetRaw && !isFinite(budget) ? 'Scrie suma în lei, de exemplu 500 sau 1.250,50.' : '');
    var body = { title: title, start_date: start, end_date: end || null, budget: budgetRaw && isFinite(budget) ? Math.round(budget * 100) / 100 : null, description: $('oa-camp-desc').value.trim() || null };
    if (!c) {
      body.type = type;
      if (T.ad) {
        body.platform_campaign_id = $('oa-camp-pid').value.trim() || null;
        ['utm_source', 'utm_medium', 'utm_campaign', 'utm_content'].forEach(function (k) { body[k] = $('oa-camp-' + k).value.trim() || null; });
      } else {
        var baseRaw = $('oa-camp-base').value.trim(), postRaw = $('oa-camp-post').value.trim(), base = baseRaw ? parseAmount(baseRaw) : null, post = postRaw ? parseAmount(postRaw) : null;
        need('oa-camp-base', baseRaw && !isFinite(base) ? 'Scrie un număr, de exemplu 120.' : '');
        need('oa-camp-post', postRaw && !isFinite(post) ? 'Scrie un număr, de exemplu 180.' : '');
        body.impact_metric = $('oa-camp-metric').value || null;
        body.baseline_value = baseRaw && isFinite(base) ? base : null;
        body.post_value = postRaw && isFinite(post) ? post : null;
      }
    } else {
      body.is_active = $('oa-camp-active').checked;
      if (T.ad) {
        ['impr', 'clicks'].forEach(function (k) {
          var raw = $('oa-camp-' + k).value.trim(), v = raw ? parseCount(raw) : null;
          need('oa-camp-' + k, raw && !isFinite(v) ? 'Scrie un număr întreg, de exemplu 12.500.' : '');
          body[k === 'impr' ? 'impressions' : 'clicks'] = raw && isFinite(v) ? v : null;
        });
      }
    }
    formErr('oa-camp-err', '');
    if (bad) { $(bad).focus(); return; }
    var id = formEvent;
    busyBtn(btn, true, c ? 'Se salvează…' : 'Se adaugă…');
    d.setAttribute('data-busy', '');
    O.api('/organizer/events/' + id + '/milestones' + (c ? '/' + c.id : ''), { method: c ? 'PUT' : 'POST', body: body }).then(function () {
      d.removeAttribute('data-busy');
      busyBtn(btn, false);
      closeDialog(d);
      O.flash(c ? 'Campania a fost salvată.' : 'Campania a fost adăugată.');
      if (id === S.event) loadCamps(true);
    }).catch(function (err) {
      d.removeAttribute('data-busy');
      busyBtn(btn, false);
      if (err && err.status === 401) return;
      formErr('oa-camp-err', saveError(err, 'Nu am mai găsit campania. Poate a fost ștearsă între timp; reîncarcă pagina.'));
      if (err && err.status === 422) markServerErrors(err, { title: 'oa-camp-title', start_date: 'oa-camp-start', end_date: 'oa-camp-end', budget: 'oa-camp-budget', baseline_value: 'oa-camp-base', post_value: 'oa-camp-post', impressions: 'oa-camp-impr', clicks: 'oa-camp-clicks' });
    });
  });

  /* ----- delete ----- */
  function openDelete(kind, item, from) {
    delTarget = { kind: kind, item: item, event: S.event };
    var goal = kind === 'goal', name = goal ? F.flat(item.name) : F.flat(item.title);
    $('oa-del-h').textContent = goal ? 'Ștergi obiectivul?' : 'Ștergi campania?';
    $('oa-del-p').textContent = (name ? '„' + name + '” dispare' : (goal ? 'Obiectivul dispare' : 'Campania dispare')) + (goal ? ' din analiză și din raport.' : ' din grafic, din analiză și din raport, cu tot cu cifrele ei.') + ' Vânzările nu sunt afectate.';
    $('oa-del-go').querySelector('[data-label]').textContent = goal ? 'Șterge obiectivul' : 'Șterge campania';
    formErr('oa-del-err', '');
    fallbackFocus = goal ? $('oa-goal-add') : $('oa-camp-add');
    openDialog($('oa-del-d'), from);
    $('oa-del-d').querySelector('[data-close]').focus();
  }
  $('oa-del-go').addEventListener('click', function () {
    var btn = this, d = $('oa-del-d'), t = delTarget;
    if (!t || btn.getAttribute('aria-busy') === 'true') return;
    var goal = t.kind === 'goal';
    function reload() { if (t.event === S.event) { if (goal) loadGoals(true); else loadCamps(true); } }
    busyBtn(btn, true, 'Se șterge…');
    d.setAttribute('data-busy', '');
    O.api('/organizer/events/' + t.event + (goal ? '/goals/' : '/milestones/') + t.item.id, { method: 'DELETE' }).then(function () {
      d.removeAttribute('data-busy');
      busyBtn(btn, false);
      closeDialog(d);
      O.flash(goal ? 'Obiectivul a fost șters.' : 'Campania a fost ștearsă.');
      reload();
    }).catch(function (err) {
      d.removeAttribute('data-busy');
      busyBtn(btn, false);
      if (err && err.status === 401) return;
      if (err && err.status === 404) {
        closeDialog(d);
        O.flash(goal ? 'Obiectivul fusese deja șters.' : 'Campania fusese deja ștearsă.');
        reload();
        return;
      }
      formErr('oa-del-err', err && err.status === 429 ? 'Prea multe încercări într-un timp scurt. Așteaptă un minut și încearcă din nou.' : err && (err.status === 0 || err.status == null) ? 'Nu am putut ajunge la server. Verifică conexiunea și încearcă din nou.' : 'Nu am putut șterge. Încearcă din nou.');
    });
  });

  /* =================== EXPORT =================== */
  var menuBtn = $('oa-export'), menu = $('oa-export-menu');
  function menuItems() { return qsa('[role="menuitem"]', menu); }
  function openMenu(last) {
    menu.hidden = false;
    menuBtn.setAttribute('aria-expanded', 'true');
    var items = menuItems();
    if (items.length) items[last ? items.length - 1 : 0].focus();
  }
  function closeMenu(focusBtn) {
    if (menu.hidden) return;
    menu.hidden = true;
    menuBtn.setAttribute('aria-expanded', 'false');
    if (focusBtn) menuBtn.focus();
  }
  menuBtn.addEventListener('click', function () { if (menu.hidden) openMenu(false); else closeMenu(false); });
  menuBtn.addEventListener('keydown', function (e) {
    if (e.key === 'ArrowDown' || e.key === 'ArrowUp') { e.preventDefault(); openMenu(e.key === 'ArrowUp'); }
  });
  menu.addEventListener('keydown', function (e) {
    var items = menuItems(), i = items.indexOf(document.activeElement), n = items.length;
    if (e.key === 'ArrowDown') { e.preventDefault(); items[(i + 1) % n].focus(); }
    else if (e.key === 'ArrowUp') { e.preventDefault(); items[(i - 1 + n) % n].focus(); }
    else if (e.key === 'Home') { e.preventDefault(); items[0].focus(); }
    else if (e.key === 'End') { e.preventDefault(); items[n - 1].focus(); }
    else if (e.key === 'Escape') { e.preventDefault(); closeMenu(true); }
    else if (e.key === 'Tab') closeMenu(false);
  });
  menu.addEventListener('click', function (e) {
    var b = e.target.closest('[data-export]');
    if (!b) return;
    closeMenu(true);
    if (b.getAttribute('data-export') === 'csv') exportCsv(); else exportPdf();
  });
  document.addEventListener('pointerdown', function (e) { if (!menu.hidden && !$('oa-menu').contains(e.target)) closeMenu(false); });

  function csvCell(v) {
    if (typeof v === 'number') return isFinite(v) ? String(Math.round(v * 100) / 100) : '';
    var s = String(v == null ? '' : v);
    if (/^[=+\-@\t\r]/.test(s)) s = "'" + s; // a cell starting like a formula stays text in spreadsheets
    return /[",\n\r]/.test(s) ? '"' + s.replace(/"/g, '""') + '"' : s;
  }
  function exportCsv() {
    if (!data) return;
    var e = data.event || {}, o = data.overview || {}, c = data.chart || {}, all = S.period === 'tot', title = F.flat(e.title) || 'Activitatea #' + S.event;
    var dates = Array.isArray(c.raw_dates) ? c.raw_dates : [], rows = [];
    rows.push(['Analiză activitate', title]);
    rows.push(['Perioada', PERIODS[S.period].label + (dates.length ? ' (' + dates[0] + ' – ' + dates[dates.length - 1] + ')' : '')]);
    rows.push(['Generat la', stamp(new Date().toISOString())]);
    rows.push([]);
    rows.push(['Totaluri de la începutul vânzărilor', '']);
    rows.push(['Venituri nete (lei)', num(o.net_revenue != null ? o.net_revenue : o.total_revenue)]);
    rows.push(['Bilete vândute', num(o.tickets_sold)]);
    rows.push(['Vizualizări', num(o.page_views)]);
    rows.push(['Rată conversie (%)', num(o.conversion_rate)]);
    rows.push([]);
    rows.push(['Data', 'Venit net (lei)', 'Bilete vândute']);
    dates.forEach(function (d, i) { rows.push([d, num((c.revenue || [])[i]), num((c.tickets || [])[i])]); });
    rows.push([]);
    var head = ['Tip bilet', 'Preț (lei)', 'Vândute', 'Capacitate', 'Venituri nete (lei)', 'Conversie (%)'];
    if (!all) head.push('Trend față de perioada dinainte (%)');
    rows.push(head);
    (Array.isArray(data.ticket_performance) ? data.ticket_performance : []).forEach(function (t) {
      var capq = t.capacity == null || t.capacity === '' ? '' : num(t.capacity) < 0 ? 'nelimitat' : num(t.capacity), row = [F.flat(t.name) || 'Bilet', num(t.price), num(t.sold), capq, num(t.revenue), num(t.conversion_rate)];
      if (!all) row.push(num(t.trend));
      rows.push(row);
    });
    rows.push([]);
    rows.push(['Sursă de trafic', 'Vizitatori', 'Vânzări atribuite (lei)']);
    (Array.isArray(data.traffic_sources) ? data.traffic_sources : []).forEach(function (s) { rows.push([SOURCES[s.source] || F.flat(s.source) || 'Direct', num(s.visitors), num(s.revenue)]); });
    rows.push([]);
    rows.push(['Oraș', 'Țară', 'Vizitatori']);
    (Array.isArray(data.top_locations) ? data.top_locations : []).map(place).forEach(function (p) { rows.push([p.city || 'necunoscut', p.country, p.visitors]); });
    var csv = '﻿' + rows.map(function (r) { return r.map(csvCell).join(','); }).join('\r\n') + '\r\n';
    download(new Blob([csv], { type: 'text/csv;charset=utf-8' }), 'analiza-' + (slug(title) || 'activitate') + '-' + PERIODS[S.period].file + '-' + F.ymd() + '.csv');
    O.flash('Datele perioadei au fost descărcate (CSV).');
  }
  function exportPdf() {
    var btn = menuBtn, id = S.event;
    if (!id || btn.getAttribute('aria-busy') === 'true') return;
    var token = typeof BileteOnlineAuth !== 'undefined' && BileteOnlineAuth.getToken ? BileteOnlineAuth.getToken() : null;
    if (!token) { O.flash('Sesiunea a expirat. Autentifică-te din nou.', true); return; }
    var base = (window.BILETEONLINE && window.BILETEONLINE.apiUrl) || '/api/proxy.php', name = 'raport-' + (slug($('oa-title').textContent) || 'activitate') + '-' + F.ymd() + '.pdf';
    busyBtn(btn, true, 'Se generează…');
    fetch(base + '?action=organizer.event.report.export&event_id=' + encodeURIComponent(id), { headers: { Authorization: 'Bearer ' + token, Accept: 'application/pdf' } }).then(function (res) {
      if (!res.ok) { var e = new Error('pdf'); e.status = res.status; throw e; }
      return res.blob();
    }).then(function (blob) {
      return blob.slice(0, 5).text().then(function (head) {
        // only core's PDF is saved; a refused token gets its sign-in page instead (200, the proxy follows the redirect)
        if (head !== '%PDF-') { var e = new Error('pdf'); e.html = true; throw e; }
        download(blob, name);
        O.flash('Raportul PDF a fost descărcat.');
      });
    }).catch(function (err) {
      function failed() { O.flash('Nu am putut genera raportul PDF. Încearcă din nou.', true); }
      function signOut() {
        O.flash('Sesiunea a expirat. Te trimitem la autentificare.', true);
        setTimeout(function () { O.api('/organizer/me').catch(function () {}); }, 1500);
      }
      if (err && err.status === 401) return signOut();
      if (err && err.status === 404) { O.flash('Activitatea nu a fost găsită.', true); return; }
      if (err && err.html) return O.api('/organizer/me', { quiet: true }).then(failed, function (e) { if (e && e.status === 401) signOut(); else failed(); });
      failed();
    }).then(function () { busyBtn(btn, false); });
  }

  /* =================== ACTIVITY PICKER =================== */
  var input = $('oa-event-q'), list = $('oa-event-list'), combo = { items: [], active: -1, typed: false };
  function loadEvents() {
    if (eventsReq) return eventsReq;
    var got = [];
    eventsFailed = false;
    function page(n) {
      return O.api('/organizer/events?per_page=50&page=' + n, { quiet: true }).then(function (r) {
        got = got.concat(Array.isArray(r && r.data) ? r.data : []);
        if (num(O.metaOf(r).last_page) > n && n < 20) return page(n + 1);
      });
    }
    eventsReq = page(1).then(function () {
      events = got.filter(function (e) { return e && /^\d+$/.test(String(e.id)); }).sort(function (a, b) {
        var al = isLive(a), bl = isLive(b), ad = String(a.starts_at || ''), bd = String(b.starts_at || '');
        if (al !== bl) return al ? -1 : 1;
        return al ? (ad < bd ? -1 : ad > bd ? 1 : 0) : (ad > bd ? -1 : ad < bd ? 1 : 0);
      });
      return events;
    }, function (err) {
      eventsReq = null;
      eventsFailed = true;
      throw err;
    });
    return eventsReq;
  }
  function currentTitle() { return data && data.event && loaded.event === S.event ? F.flat(data.event.title) || 'Activitatea #' + S.event : ''; }
  function comboOpen() { return !list.hidden; }
  function renderOptions() {
    list.textContent = '';
    if (!events) {
      list.appendChild(el('li', { class: 'oa-opt-empty', role: 'presentation', text: eventsFailed ? 'Nu am putut încărca activitățile. Închide lista și încearcă din nou.' : 'Se încarcă activitățile…' }));
      setActive(-1);
      return;
    }
    var q = combo.typed ? norm(input.value.trim()) : '';
    combo.items = events.filter(function (e) { return !q || norm(evName(e) + ' ' + evMeta(e)).indexOf(q) > -1; });
    if (!combo.items.length) {
      list.appendChild(el('li', { class: 'oa-opt-empty', role: 'presentation', text: events.length ? 'Nicio activitate nu se potrivește căutării.' : 'Nu ai încă activități.' }));
      setActive(-1);
      return;
    }
    combo.items.forEach(function (e, i) {
      var live = isLive(e);
      list.appendChild(el('li', { class: 'oa-opt', id: 'oa-opt-' + e.id, role: 'option', 'aria-selected': String(String(e.id) === S.event), 'data-i': String(i) }, [
        el('span', { class: 'oa-evdot' + (live ? ' is-live' : ''), 'aria-hidden': 'true' }),
        el('span', { class: 'oa-opt-t' }, [evName(e), el('span', { class: 'sr', text: live ? ' (în derulare)' : '' })]),
        el('span', { class: 'oa-opt-m', text: evMeta(e) }),
      ]));
    });
    setActive(Math.min(Math.max(combo.active, 0), combo.items.length - 1));
  }
  function setActive(i) {
    combo.active = i;
    qsa('.oa-opt', list).forEach(function (li, n) { li.classList.toggle('is-active', n === i); });
    var li = i > -1 ? qsa('.oa-opt', list)[i] : null;
    if (li) {
      input.setAttribute('aria-activedescendant', li.id);
      if (li.scrollIntoView) li.scrollIntoView({ block: 'nearest' });
    } else input.removeAttribute('aria-activedescendant');
  }
  function selectedIndex() { return events ? Math.max(0, events.findIndex(function (e) { return String(e.id) === S.event; })) : -1; }
  function openCombo(selectText) {
    if (comboOpen()) return;
    combo.typed = false;
    list.hidden = false;
    input.setAttribute('aria-expanded', 'true');
    combo.active = selectedIndex();
    renderOptions();
    if (!events) {
      loadEvents().then(function () { if (comboOpen()) { if (!combo.typed) combo.active = selectedIndex(); renderOptions(); } }, function () { if (comboOpen()) renderOptions(); });
    }
    if (selectText) input.select(); // the first key typed replaces the activity's name
  }
  function closeCombo(restore) {
    list.hidden = true;
    input.setAttribute('aria-expanded', 'false');
    input.removeAttribute('aria-activedescendant');
    combo.typed = false;
    if (restore) input.value = currentTitle();
    $('oa-event-clear').hidden = true;
  }
  var justFocused = false;
  input.addEventListener('focus', function () { openCombo(true); input.select(); justFocused = true; });
  input.addEventListener('mouseup', function (e) { // the mouseup after a click-to-focus would drop the selection: typing then replaces the name
    if (justFocused) e.preventDefault();
    justFocused = false;
  });
  input.addEventListener('blur', function () { justFocused = false; });
  input.addEventListener('click', function () { if (!comboOpen()) openCombo(true); });
  input.addEventListener('input', function () {
    var fresh = !comboOpen() || !combo.typed, name = currentTitle();
    if (!comboOpen()) openCombo(false);
    if (fresh && name) { // typed next to the name still in the field (after Escape, say): keep only what was typed
      var v = input.value;
      if (v.length > name.length && v.indexOf(name) === 0) input.value = v.slice(name.length);
      else if (v.length > name.length && v.lastIndexOf(name) === v.length - name.length) input.value = v.slice(0, v.length - name.length);
    }
    combo.typed = true;
    combo.active = 0;
    $('oa-event-clear').hidden = !input.value;
    renderOptions();
  });
  input.addEventListener('keydown', function (e) {
    var open = comboOpen(), n = combo.items.length && events ? combo.items.length : 0;
    if (e.key === 'ArrowDown' || e.key === 'ArrowUp') {
      e.preventDefault();
      if (!open) { openCombo(true); return; }
      if (n) setActive(e.key === 'ArrowDown' ? (combo.active + 1) % n : (combo.active - 1 + n) % n);
    } else if ((e.key === 'Home' || e.key === 'End') && open && n) {
      e.preventDefault();
      setActive(e.key === 'Home' ? 0 : n - 1);
    } else if (e.key === 'Enter' && open) {
      e.preventDefault();
      if (n && combo.active > -1 && combo.items[combo.active]) pickEvent(combo.items[combo.active].id);
    } else if (e.key === 'Escape' && open) {
      e.preventDefault();
      closeCombo(true);
    } else if (e.key === 'Tab' && open) {
      closeCombo(true);
    }
  });
  list.addEventListener('mousedown', function (e) { e.preventDefault(); }); // keep the focus in the field
  list.addEventListener('click', function (e) {
    var li = e.target.closest('.oa-opt');
    if (li && combo.items[+li.getAttribute('data-i')]) pickEvent(combo.items[+li.getAttribute('data-i')].id);
  });
  $('oa-event-clear').addEventListener('click', function () {
    input.value = '';
    combo.typed = true;
    $('oa-event-clear').hidden = true;
    renderOptions();
    input.focus();
  });
  document.addEventListener('pointerdown', function (e) { if (comboOpen() && !$('oa-combo').contains(e.target)) closeCombo(true); });
  function pickEvent(id) {
    closeCombo(false);
    var same = String(id) === S.event && loaded.event === S.event;
    var ev = (events || []).filter(function (x) { return String(x.id) === String(id); })[0];
    input.value = same ? currentTitle() : ev ? evName(ev) : '';
    input.blur();
    if (same) return;
    S.event = String(id);
    writeUrl(true);
    if (ev) $('oa-title').textContent = evName(ev);
    load();
  }
  function renderEvList() {
    var box = $('oa-evlist'), top = (events || []).slice(0, 8);
    box.textContent = '';
    if (!top.length) {
      box.appendChild(el('li', { class: 'oa-empty-p' }, ['Nu ai încă activități. ', el('a', { href: '/organizator/activities', text: 'Adaugă prima activitate' })]));
      return;
    }
    top.forEach(function (e) {
      var live = isLive(e), a = el('a', { href: '/organizator/analytics/' + e.id }, [
        el('span', { class: 'oa-evdot' + (live ? ' is-live' : ''), 'aria-hidden': 'true' }),
        el('span', { class: 'oa-ev-t' }, [el('b', { text: evName(e) }), el('small', { text: evMeta(e) || (live ? 'În desfășurare' : '') })]),
        icon('arrow-right'),
      ]);
      a.addEventListener('click', function (ev) {
        if (ev.metaKey || ev.ctrlKey || ev.shiftKey || ev.altKey || ev.button) return;
        ev.preventDefault();
        pickEvent(e.id);
      });
      box.appendChild(el('li', null, a));
    });
  }

  /* =================== PERIOD =================== */
  qsa('.oa-seg-b', root).forEach(function (b) {
    b.addEventListener('click', function () {
      var p = b.getAttribute('data-period');
      if (!has(PERIODS, p) || p === S.period) return;
      S.period = p;
      syncPeriod();
      writeUrl(false);
      if (S.event) load();
    });
  });

  O.ready.then(function (ok) { if (ok) start(); });
})();
