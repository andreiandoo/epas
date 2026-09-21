/* bilete.online v2: the operator's locations (/organizator/locatii). A list, and an editor opened with ?id=N or ?nou=1
   (history keeps the back button working). The editor is one long form in sections with a jump list and a save bar;
   each control writes straight into the location object, which is sent whole on save (pictures as their paths).
   Asking for approval saves first. Uses window.BO_AM (org-am.js) inside the organizer shell. */
(function () {
  'use strict';
  var O = window.BO_ORG, A = window.BO_AM, root = document.getElementById('am-loc');
  if (!O || !A || !root) return;
  var el = O.el, L = A.L;
  var $ = function (id) { return document.getElementById(id); };
  var DAYS = Object.keys(L.days || { mon: 'luni', tue: 'marți', wed: 'miercuri', thu: 'joi', fri: 'vineri', sat: 'sâmbătă', sun: 'duminică' });
  var MONTHS = L.months || [];
  var meta = null, locations = [], cur = null, dirty = false, busy = false;
  // Where the location is, as the editor asks for it: the country and the county narrow the
  // city list. Only the city is saved (city_id); the other two are read back from it.
  var geo = { country: 'RO', county: null };
  var richBoxes = [];

  function cap(s) { s = String(s || ''); return s.charAt(0).toUpperCase() + s.slice(1); }
  function byName(a, b) { try { return String(a).localeCompare(String(b), 'ro'); } catch (e) { return a < b ? -1 : 1; } }
  function params() { return new URLSearchParams(window.location.search); }
  function go(url, replace) {
    if (replace) history.replaceState(null, '', url); else history.pushState(null, '', url);
    route();
  }

  /* =================== data =================== */
  function loadMeta() {
    if (meta) return Promise.resolve(meta);
    return A.api('/meta').then(function (r) { meta = (r && r.data) || {}; return meta; });
  }
  function loadList() {
    return A.api('/locations').then(function (r) {
      locations = ((r && r.data && r.data.locations) || []).filter(function (l) { return l && l.id != null; });
      return locations;
    });
  }

  /* =================== list =================== */
  function showList() {
    $('am-loc-edit').hidden = true;
    $('am-loc-list').hidden = false;
    $('am-loc-head').hidden = false;
    var box = $('am-loc-list');
    box.textContent = '';
    box.appendChild(el('p', { class: 've-state', text: 'Se încarcă…' }));
    Promise.all([loadMeta(), loadList()]).then(drawList, failed);
  }
  function failed(err) {
    if (err && err.status === 401) return;
    $('am-loc-list').hidden = true;
    $('am-loc-failed').hidden = false;
  }
  function cityOf(id) {
    var list = (meta && meta.cities) || [];
    for (var i = 0; i < list.length; i++) if (list[i].id === id) return list[i];
    return null;
  }
  function cityName(id) {
    var c = cityOf(id);
    return c ? c.name : '';
  }
  /** The countries the marketplace has cities in; România first, and always there. */
  function countryOpts() {
    var list = (meta && meta.countries) || [];
    if (!list.length) list = [{ code: 'RO', name: 'România' }];
    return list.map(function (c) { return [c.code, c.name]; });
  }
  /** The counties of one country, as the cities know them. */
  function countyOpts(country) {
    var seen = {}, out = [];
    ((meta && meta.cities) || []).forEach(function (c) {
      if ((c.country || 'RO') !== country || !c.county || seen[c.county]) return;
      seen[c.county] = 1;
      out.push(c.county);
    });
    return out.sort(byName).map(function (n) { return [n, n]; });
  }
  /** The cities of one country, kept to one county when there is one. */
  function cityOpts(country, county) {
    return ((meta && meta.cities) || [])
      .filter(function (c) { return (c.country || 'RO') === country && (!county || c.county === county); })
      .sort(function (a, b) { return byName(a.name, b.name); })
      .map(function (c) { return [c.id, c.name + (!county && c.county ? ' · ' + c.county : '')]; });
  }
  function drawList() {
    var box = $('am-loc-list');
    box.textContent = '';
    if (!locations.length) {
      box.appendChild(el('div', { class: 'org-empty' }, [
        el('span', { class: 'org-empty-ic' }, [O.icon('map-pin')]),
        el('b', { text: 'Nicio locație încă' }),
        el('p', { text: 'Începe cu locația: numele, poza, adresa și programul. Apoi adaugi biletele și experiențele.' }),
        el('a', { class: 'btn btn-primary', href: '/organizator/locatii?nou=1' }, [O.icon('plus'), el('span', { text: 'Adaugă prima locație' })]),
      ]));
      return;
    }
    var list = el('div', { class: 've-prods' });
    locations.forEach(function (l) { list.appendChild(locRow(l)); });
    box.appendChild(list);
  }
  function locRow(l) {
    var src = A.imgUrl(l.cover_image);
    var media = el('span', { class: 've-prod-media' }, src ? [el('img', { src: src, alt: '' })] : [O.icon('map-pin')]);
    var hint = A.statusHint(l);
    var t = el('div', { class: 've-prod-t' }, [
      el('b', { text: l.name || 'Locație fără nume' }),
      el('small', { text: [cityName(l.city_id), l.products_count ? l.products_count + (l.products_count === 1 ? ' produs' : ' produse') : 'fără produse'].filter(Boolean).join(' · ') }),
      el('span', { class: 've-prod-tags' }, A.statusTags(l)),
      hint ? el('small', { class: 'am-hint', text: hint }) : null,
    ]);
    var tools = el('div', { class: 've-prod-tools' });
    tools.appendChild(el('a', { class: 'btn btn-ghost', href: '/organizator/locatii?id=' + l.id }, [O.icon('gear-six'), el('span', { text: 'Editează' })]));
    tools.appendChild(el('a', { class: 'btn btn-ghost', href: '/organizator/produse?locatie=' + l.id }, [O.icon('ticket'), el('span', { text: 'Produse' })]));
    if (l.is_published && (!l.review_status || l.review_status === 'approved')) {
      tools.appendChild(el('a', { class: 've-icon-btn', href: l.public_path, target: '_blank', rel: 'noopener', 'aria-label': 'Vezi pe site' }, [O.icon('arrow-right')]));
    }
    return el('article', { class: 've-prod' + (l.is_published ? '' : ' is-off') + (l.review_status === 'pending' ? ' am-waiting' : '') + (l.review_status === 'rejected' ? ' am-rejected' : '') }, [media, t, el('span'), tools]);
  }

  /* =================== editor =================== */
  function blank() {
    return {
      id: null, name: null, subtitle: null, short_description: null, description: null, rules: null, city_id: null, category_id: null,
      address: null, latitude: null, longitude: null, google_maps_url: null, phone: null, email: null, website_url: null,
      cover_image: null, gallery: [], facilities: [], seasons: [], closed_dates: [], max_advance_days: 90,
      display_categories: [], faqs: [], lodging: { enabled: false, facilities: [], rooms: [], links: [], gallery: [] },
      review_status: 'draft', is_published: false,
    };
  }
  function openEditor(id) {
    $('am-loc-list').hidden = true;
    $('am-loc-failed').hidden = true;
    $('am-loc-head').hidden = true;
    var box = $('am-loc-edit');
    box.hidden = false;
    box.textContent = '';
    box.appendChild(el('p', { class: 've-state', text: 'Se încarcă…' }));
    var load = id ? A.api('/locations/' + id).then(function (r) { return (r && r.data && r.data.location) || null; }) : Promise.resolve(blank());
    Promise.all([loadMeta(), load]).then(function (res) {
      if (!res[1]) { O.flash('Locația nu există.', true); go('/organizator/locatii', true); return; }
      cur = normalize(res[1]);
      dirty = false;
      drawEditor();
    }, function (err) {
      if (err && err.status === 401) return;
      box.textContent = '';
      box.appendChild(el('div', { class: 'org-empty is-error' }, [el('b', { text: 'Nu am putut încărca locația' }), el('p', { text: A.errText(err, 'Reîncearcă.') })]));
    });
  }
  function normalize(l) {
    var b = blank();
    Object.keys(b).forEach(function (k) { if (l[k] === undefined || l[k] === null) l[k] = b[k]; });
    l.lodging = Object.assign(b.lodging, l.lodging || {});
    ['facilities', 'rooms', 'links', 'gallery'].forEach(function (k) { if (!Array.isArray(l.lodging[k])) l.lodging[k] = []; });
    l.seasons = (l.seasons || []).map(function (s) {
      var sch = {};
      DAYS.forEach(function (d) { var h = (s.schedule || {})[d]; sch[d] = h && h.open && h.close ? { open: h.open, close: h.close } : null; });
      return { name: s.name || '', start: s.start || '01-01', end: s.end || '12-31', last_entry: s.last_entry || null, schedule: sch };
    });
    l.display_categories = (l.display_categories || []).map(function (c) { return { id: c.id, name: c.name || '', auto: false }; });
    return l;
  }

  function mdPicker(obj, key) {
    var parts = String(obj[key] || '01-01').split('-');
    var day = el('select', { 'aria-label': 'Ziua' }), mon = el('select', { 'aria-label': 'Luna' });
    for (var d = 1; d <= 31; d++) day.appendChild(el('option', { value: (d < 10 ? '0' : '') + d, text: String(d) }));
    MONTHS.forEach(function (m, i) { mon.appendChild(el('option', { value: (i < 9 ? '0' : '') + (i + 1), text: m })); });
    day.value = parts[1] || '01';
    mon.value = parts[0] || '01';
    function sync() { obj[key] = mon.value + '-' + day.value; dirty = true; }
    day.addEventListener('change', sync);
    mon.addEventListener('change', sync);
    return el('span', { class: 'am-md' }, [el('span', { class: 'po-select' }, [day, O.icon('caret-down')]), el('span', { class: 'po-select' }, [mon, O.icon('caret-down')])]);
  }
  function seasonCard(s, i, redraw) {
    var grid = el('div', { class: 'am-week' });
    DAYS.forEach(function (d) {
      var h = s.schedule[d];
      var on = el('input', { type: 'checkbox', 'aria-label': 'Deschis ' + L.days[d] });
      on.checked = !!h;
      var open = el('input', { class: 'po-input', type: 'time', value: h ? h.open : '09:00', 'aria-label': 'Deschide ' + L.days[d] });
      var close = el('input', { class: 'po-input', type: 'time', value: h ? h.close : '18:00', 'aria-label': 'Închide ' + L.days[d] });
      function sync() {
        s.schedule[d] = on.checked ? { open: open.value || '09:00', close: close.value || '18:00' } : null;
        open.disabled = close.disabled = !on.checked;
        dirty = true;
      }
      on.addEventListener('change', sync);
      open.addEventListener('input', sync);
      close.addEventListener('input', sync);
      open.disabled = close.disabled = !h;
      grid.appendChild(el('label', { class: 'po-check am-week-d' }, [on, el('span', { text: cap(L.days[d]) })]));
      grid.appendChild(open);
      grid.appendChild(close);
    });
    var copy = A.button('check', 'Programul de luni pentru toată săptămâna');
    copy.addEventListener('click', function () {
      var m = s.schedule.mon;
      DAYS.forEach(function (d) { s.schedule[d] = m ? { open: m.open, close: m.close } : null; });
      dirty = true;
      redraw();
    });
    return el('div', { class: 'am-season' }, [
      A.form([
        A.field('Numele sezonului', A.input(s, 'name', { max: 60, ph: 'Vara', on: function () { dirty = true; } })),
        A.field('Ultima intrare', A.input(s, 'last_entry', { type: 'time', on: function () { dirty = true; } }), { hint: 'Opțional. După ora asta nu se mai intră.' }),
        A.field('De la', mdPicker(s, 'start')),
        A.field('Până la', mdPicker(s, 'end'), { hint: 'Un sezon poate trece peste anul nou, de pildă 1 noiembrie – 31 martie.' }),
      ]),
      el('p', { class: 've-sec-k', text: 'Programul săptămânii' }),
      grid,
      el('div', { class: 've-sec-tools' }, [copy]),
    ]);
  }

  function drawEditor() {
    var l = cur, box = $('am-loc-edit');
    var mark = function () { dirty = true; };
    box.textContent = '';
    richBoxes = [];

    var catOpts = [[null, 'Alege categoria']].concat((meta.categories || []).filter(function (c) { return !c.parent_id; }).map(function (c) { return [c.id, c.name]; }));
    var countries = countryOpts();
    var city0 = cityOf(l.city_id);
    geo.country = (city0 && city0.country) || (countries[0] && countries[0][0]) || 'RO';
    geo.county = (city0 && city0.county) || null;

    // ----- head -----
    var title = el('h1', { class: 've-h', text: l.id ? (l.name || 'Locație') : 'Locație nouă' });
    var tags = el('span', { class: 've-prod-tags' }, l.id ? A.statusTags(l) : []);
    box.appendChild(el('header', { class: 've-head' }, [el('div', null, [
      el('a', { class: 'am-back', href: '/organizator/locatii' }, [O.icon('arrow-left'), el('span', { text: 'Toate locațiile' })]),
      title, tags,
    ])]));

    // ----- where this is in the approval -----
    box.appendChild(l.id
      ? A.statusPanel(l, 'locația')
      : A.statusPanel({ review_status: 'draft', is_published: false }, 'locația'));

    // ----- jump list -----
    var SECS = [['am-l-about', 'Despre'], ['am-l-where', 'Adresă'], ['am-l-photos', 'Poze'], ['am-l-hours', 'Program'],
      ['am-l-info', 'Facilități'], ['am-l-cats', 'Categorii'], ['am-l-lodging', 'Cazare']];
    box.appendChild(el('nav', { class: 'am-jump', 'aria-label': 'Secțiunile locației' }, SECS.map(function (s) { return el('a', { href: '#' + s[0], text: s[1] }); })));

    // ----- about -----
    // Country → county → city: each list is the one below it, so the operator never types a place name.
    var cityCombo = A.combo(l, 'city_id', cityOpts(geo.country, geo.county), {
      num: true, ph: 'Scrie primele litere…',
      on: function (id) {
        var c = cityOf(id);
        if (c && c.county && c.county !== geo.county) { geo.county = c.county; countyCombo.setValue(geo.county); }
        mark();
      },
    });
    var countyCombo = A.combo(geo, 'county', countyOpts(geo.country), {
      ph: 'Scrie primele litere…', empty: 'Toate județele',
      on: function () { cityCombo.setOptions(cityOpts(geo.country, geo.county)); mark(); },
    });
    var countryCombo = A.combo(geo, 'country', countries, {
      ph: 'Scrie primele litere…',
      on: function () {
        geo.county = null;
        countyCombo.setValue(null);
        countyCombo.setOptions(countyOpts(geo.country));
        cityCombo.setOptions(cityOpts(geo.country, null));
        countyField.hidden = !countyOpts(geo.country).length;
        mark();
      },
    });
    var countyField = A.field('Județul', countyCombo, { hint: 'Alege întâi județul: lista de orașe rămâne doar cu ale lui.' });
    countyField.hidden = !countyOpts(geo.country).length;
    var descRich = A.rich(l, 'description', { label: 'Descrierea locației', max: 20000, min: 220, ph: 'Ce vede și ce face vizitatorul aici…', on: mark });
    richBoxes.push([descRich, 'Descrierea']);
    box.appendChild(A.section('am-l-about', 'Despre locație', 'Ce apare sus pe pagina locației și în căutări.', [A.form([
      A.field('Numele *', A.input(l, 'name', { max: 190, ph: 'Lacul Sfânta Ana', on: mark }), { wide: true }),
      A.field('Subtitlu', A.input(l, 'subtitle', { max: 190, ph: 'Singurul lac vulcanic din România', on: mark }), { wide: true }),
      A.field('Țara', countryCombo),
      countyField,
      A.field('Orașul *', cityCombo, { hint: 'Orașul sau stațiunea sub care apare locația pe site.' }),
      A.field('Categoria', A.select(l, 'category_id', catOpts, { num: true, on: mark })),
      A.field('Pe scurt *', A.textarea(l, 'short_description', { max: 280, rows: 2, ph: 'O frază despre ce găsește vizitatorul aici.', on: mark }), { wide: true, hint: 'Cel mult 280 de caractere. Apare pe carduri și în căutări.' }),
      A.field('Descrierea', descRich, { wide: true, hint: 'Scrii ca într-un document: îngroșat, cursiv, liste și linkuri.' }),
    ])]));

    // ----- where -----
    // Latitude and longitude stay in the record (the map on the public page uses them), but the
    // operator never types them: we set the point from the address.
    var coords = (l.latitude != null && l.longitude != null)
      ? el('p', { class: 've-note' }, [O.icon('map-pin'), document.createTextNode(' Punctul de pe hartă e pus: ' + l.latitude + ', ' + l.longitude + '. Îl mutăm noi dacă adresa se schimbă.')])
      : el('p', { class: 've-note', text: 'Punctul de pe hartă îl punem noi, după adresă. Dacă ai un link Google Maps, lasă-l mai jos.' });
    box.appendChild(A.section('am-l-where', 'Adresă și contact', 'Adresa apare pe pagina locației, sub hartă, și în indicațiile de drum.', [A.form([
      A.field('Adresa *', A.input(l, 'address', { max: 255, ph: 'Str. Lacului 1, Bixad', on: mark }), { wide: true, hint: 'Strada și numărul. Orașul și județul se aleg sus, la „Despre locație”.' }),
      A.field('Link Google Maps', A.input(l, 'google_maps_url', { type: 'url', max: 500, ph: 'https://maps.app.goo.gl/…', on: mark }), { wide: true }),
      A.field('Telefon', A.input(l, 'phone', { type: 'tel', max: 40, on: mark })),
      A.field('Email', A.input(l, 'email', { type: 'email', max: 255, on: mark })),
      A.field('Site', A.input(l, 'website_url', { type: 'url', max: 500, ph: 'https://', on: mark }), { wide: true }),
    ]), coords]));

    // ----- photos -----
    box.appendChild(A.section('am-l-photos', 'Poze', 'Poza principală e obligatorie. Recomandat 1600 × 1200, peisaj.', [
      el('p', { class: 've-sec-k', text: 'Poza principală *' }),
      A.image(l, 'cover_image', 'location'),
      el('p', { class: 've-sec-k', text: 'Galeria' }),
      A.gallery(l, 'gallery', 'location-gallery', 20),
    ]));

    // ----- hours -----
    var seasons = A.rows(l.seasons, function (s, i, redraw) { return [seasonCard(s, i, redraw)]; }, function () {
      var sch = {};
      DAYS.forEach(function (d) { sch[d] = { open: '09:00', close: '18:00' }; });
      return { name: l.seasons.length ? 'Iarna' : 'Vara', start: l.seasons.length ? '11-01' : '04-01', end: l.seasons.length ? '03-31' : '10-31', last_entry: null, schedule: sch };
    }, { addLabel: 'Adaugă un sezon', rowCls: 'am-row-block', limit: 12, removeLabel: 'Șterge sezonul', on: mark });
    var closed = A.rows(l.closed_dates, function (d, i) {
      var inp = el('input', { class: 'po-input', type: 'date', value: d || '', 'aria-label': 'Ziua închisă ' + (i + 1) });
      inp.addEventListener('input', function () { l.closed_dates[i] = inp.value || null; mark(); });
      return [A.field('Închis pe', inp)];
    }, function () { return null; }, { addLabel: 'Adaugă o zi închisă', limit: 366, removeLabel: 'Scoate ziua', on: mark });
    box.appendChild(A.section('am-l-hours', 'Program și sezoane', 'Orele de deschidere pe sezoane. Biletele de acces se pot folosi în programul zilei; produsele pot prelua programul locației.', [
      seasons.box, el('div', { class: 've-sec-tools' }, [seasons.addBtn]),
      el('p', { class: 've-sec-k', text: 'Zile în care e închis' }),
      closed.box, el('div', { class: 've-sec-tools' }, [closed.addBtn]),
      A.form([A.field('Cu câte zile înainte se poate rezerva', A.input(l, 'max_advance_days', { type: 'number', min: 1, maxv: 365, ph: '90', on: mark }))]),
    ]));

    // ----- facilities, rules, FAQ -----
    var faqs = A.rows(l.faqs, function (f) {
      return [
        A.field('Întrebarea', A.input(f, 'q', { max: 200, on: mark }), { cls: 've-row-wide' }),
        A.field('Răspunsul', A.textarea(f, 'a', { max: 2000, rows: 2, on: mark }), { cls: 've-row-wide' }),
      ];
    }, function () { return { q: null, a: null }; }, { addLabel: 'Adaugă o întrebare', rowCls: 'am-row-block', limit: 30, removeLabel: 'Șterge întrebarea', on: mark });
    var rulesRich = A.rich(l, 'rules', { label: 'Reguli de vizitare', max: 6000, min: 140, ph: 'Înotul este interzis. Câinii se țin în lesă.', on: mark });
    richBoxes.push([rulesRich, 'Reguli de vizitare']);
    box.appendChild(A.section('am-l-info', 'Facilități, reguli, întrebări', 'Bifează ce găsește vizitatorul la tine. Ce nu e în listă adaugi singur, jos.', [
      el('p', { class: 've-sec-k', text: 'Facilități' }),
      A.checkset(l, 'facilities', L.facilities || {}, {
        groups: L.facility_groups || null, custom: L.custom_facility || 'custom:', on: mark,
        customLabel: 'Facilitățile tale', customHint: 'Adaugă o facilitate care nu e în listă',
      }),
      A.form([A.field('Reguli de vizitare', rulesRich, { wide: true })]),
      el('p', { class: 've-sec-k', text: 'Întrebări frecvente' }),
      faqs.box, el('div', { class: 've-sec-tools' }, [faqs.addBtn]),
    ]));

    // ----- display categories -----
    var cats = A.rows(l.display_categories, function (c) {
      var inp = A.input(c, 'name', { max: 60, ph: 'Bilete individuale', on: function () { if (c.auto || !c.id) { c.id = A.slug(c.name); c.auto = true; } mark(); } });
      return [A.field('Numele categoriei', inp, { cls: 've-row-wide' })];
    }, function () { return { id: null, name: null, auto: true }; }, { addLabel: 'Adaugă o categorie', limit: 20, removeLabel: 'Șterge categoria', on: mark });
    box.appendChild(A.section('am-l-cats', 'Categorii de bilete', 'Grupează produsele pe pagina locației, de pildă „Bilete individuale”, „Familie”, „Plimbări și închirieri”. Ordinea de aici e ordinea de pe pagină.', [
      cats.box, el('div', { class: 've-sec-tools' }, [cats.addBtn]),
    ]));

    // ----- lodging -----
    var lg = l.lodging;
    var lgBody = el('div', { class: 'am-stack' });
    function drawLodging() {
      lgBody.textContent = '';
      lgBody.hidden = !lg.enabled;
      if (!lg.enabled) return;
      var typeOpts = [[null, 'Alege tipul']].concat(Object.keys(L.lodging_types || {}).map(function (k) { return [k, L.lodging_types[k]]; }));
      var lgDesc = A.rich(lg, 'description', { label: 'Descrierea cazării', max: 6000, min: 140, ph: 'Câteva rânduri despre camere, mic dejun, priveliște…', on: mark });
      var lgPol = A.rich(lg, 'policies', { label: 'Reguli și politici', max: 4000, min: 120, ph: 'Anulare, animale, fumat…', on: mark });
      richBoxes.push([lgDesc, 'Descrierea cazării'], [lgPol, 'Reguli și politici']);
      var rooms = A.rows(lg.rooms, function (r) {
        return [
          A.field('Camera', A.input(r, 'name', { max: 80, ph: 'Cameră dublă', on: mark })),
          A.field('Persoane', A.input(r, 'capacity', { type: 'number', min: 1, maxv: 50, on: mark })),
          A.field('Paturi', A.input(r, 'beds', { max: 80, ph: '1 pat dublu', on: mark })),
          A.field('Câte camere', A.input(r, 'count', { type: 'number', min: 1, maxv: 500, on: mark })),
          A.field('De la (lei/noapte)', A.input(r, 'price_from', { type: 'number', min: 0, step: '1', on: mark })),
        ];
      }, function () { return { name: null, capacity: 2, beds: null, count: 1, price_from: null, description: null, facilities: [], images: [] }; },
      { addLabel: 'Adaugă un tip de cameră', limit: 30, removeLabel: 'Șterge camera', on: mark });
      var platOpts = Object.keys(L.link_platforms || {}).map(function (k) { return [k, L.link_platforms[k]]; });
      var links = A.rows(lg.links, function (x) {
        return [
          A.field('Platforma', A.select(x, 'platform', platOpts, { keep: true, on: mark })),
          A.field('Linkul', A.input(x, 'url', { type: 'url', max: 1000, ph: 'https://www.booking.com/hotel/…', on: mark }), { cls: 've-row-wide' }),
          A.field('Textul butonului', A.input(x, 'label', { max: 60, ph: 'opțional', on: mark })),
        ];
      }, function () { return { platform: 'booking', url: null, label: null }; }, { addLabel: 'Adaugă un link de rezervare', limit: 6, removeLabel: 'Șterge linkul', on: mark });
      [
        A.form([
          A.field('Tipul', A.select(lg, 'type', typeOpts, { on: mark })),
          A.field('Clasificare', A.input(lg, 'classification', { max: 40, ph: '3 flori, 4 stele…', on: mark })),
          A.field('Check-in de la', A.input(lg, 'check_in', { type: 'time', on: mark })),
          A.field('Check-out până la', A.input(lg, 'check_out', { type: 'time', on: mark })),
          A.field('Preț de la (lei/noapte)', A.input(lg, 'price_from', { type: 'number', min: 0, step: '1', on: mark })),
          A.field('Telefon rezervări', A.input(lg, 'phone', { type: 'tel', max: 40, on: mark })),
          A.field('Email rezervări', A.input(lg, 'email', { type: 'email', max: 255, on: mark }), { wide: true }),
          A.field('Descrierea cazării', lgDesc, { wide: true }),
          A.field('Reguli și politici', lgPol, { wide: true }),
        ]),
        el('p', { class: 've-sec-k', text: 'Dotări' }),
        A.checkset(lg, 'facilities', L.lodging_facilities || {}, {
          custom: L.custom_facility || 'custom:', on: mark,
          customLabel: 'Dotările tale', customHint: 'Adaugă o dotare care nu e în listă',
        }),
        el('p', { class: 've-sec-k', text: 'Camere' }),
        rooms.box, el('div', { class: 've-sec-tools' }, [rooms.addBtn]),
        el('p', { class: 've-sec-k', text: 'Unde se rezervă cazarea' }),
        el('p', { class: 've-sub', text: 'bilete.online nu vinde cazarea: vizitatorii ajung pe platforma ta (Booking, Airbnb sau site-ul propriu).' }),
        links.box, el('div', { class: 've-sec-tools' }, [links.addBtn]),
        el('p', { class: 've-sec-k', text: 'Poze cu cazarea' }),
        A.gallery(lg, 'gallery', 'lodging', 20),
      ].forEach(function (n) { lgBody.appendChild(n); });
    }
    box.appendChild(A.section('am-l-lodging', 'Cazare', 'Dacă ai și cazare, o prezinți pe pagina locației, cu linkuri spre locul în care se rezervă.', [
      A.check(lg, 'enabled', 'Locația are cazare', { on: function () { mark(); drawLodging(); } }),
      lgBody,
    ]));
    drawLodging();

    // ----- save bar -----
    var save = A.button('check', 'Salvează', 'btn btn-primary');
    var submit = A.button('arrow-right', 'Trimite spre aprobare', 'btn btn-ghost');
    submit.hidden = !(l.review_status === 'draft' || l.review_status === 'rejected');
    var pub = A.button(l.is_published ? 'x' : 'check', l.is_published ? 'Ascunde de pe site' : 'Pune pe site', 'btn btn-ghost');
    pub.hidden = !(l.id && l.review_status === 'approved');
    var del = A.button('trash', 'Șterge', 've-danger');
    del.hidden = !(l.id && !l.products_count);
    var view = el('a', { class: 'btn btn-ghost', href: l.public_path || '#', target: '_blank', rel: 'noopener' }, [O.icon('arrow-right'), el('span', { text: 'Vezi pe site' })]);
    view.hidden = !(l.id && l.is_published && (!l.review_status || l.review_status === 'approved'));
    save.addEventListener('click', function () { doSave().then(function (ok) { if (ok) O.flash(ok.message || 'Salvat.'); }); });
    submit.addEventListener('click', function () {
      doSave().then(function (ok) {
        if (!ok) return;
        return A.api('/locations/' + cur.id + '/submit', { method: 'POST', body: {} }).then(function (r) {
          O.flash((r && r.message) || 'Trimisă spre aprobare.');
          cur = normalize(r.data.location);
          drawEditor();
          showSent();
        }, function (err) { O.flash(A.errText(err, 'Nu am putut trimite locația.'), true); });
      });
    });
    pub.addEventListener('click', function () {
      A.api('/locations/' + cur.id + '/publish', { method: 'POST', body: { published: !cur.is_published } }).then(function (r) {
        O.flash((r && r.message) || 'Gata.');
        cur = normalize(r.data.location);
        drawEditor();
      }, function (err) { O.flash(A.errText(err, 'Nu am putut schimba vizibilitatea.'), true); });
    });
    var armed = false;
    del.addEventListener('click', function () {
      if (!armed) { armed = true; del.lastChild.textContent = 'Apasă din nou ca să ștergi'; setTimeout(function () { armed = false; del.lastChild.textContent = 'Șterge'; }, 4000); return; }
      A.api('/locations/' + cur.id, { method: 'DELETE' }).then(function (r) {
        dirty = false;
        O.flash((r && r.message) || 'Locația a fost ștearsă.');
        go('/organizator/locatii', true);
      }, function (err) { O.flash(A.errText(err, 'Nu am putut șterge locația.'), true); });
    });
    box.appendChild(el('div', { class: 'am-bar' }, [el('div', { class: 'am-bar-in' }, [del, el('span', { class: 'am-bar-gap' }), view, pub, submit, save])]));
  }

  /** After "Trimite spre aprobare": bring the new state under the operator's eyes and say it out loud. */
  function showSent() {
    var panel = $('am-loc-edit').querySelector('.am-state');
    if (!panel) return;
    panel.classList.add('is-fresh');
    try { panel.scrollIntoView({ behavior: 'smooth', block: 'center' }); } catch (e) { panel.scrollIntoView(); }
    if (panel.focus) { panel.setAttribute('tabindex', '-1'); panel.focus(); }
    setTimeout(function () { panel.classList.remove('is-fresh'); }, 2600);
  }

  function payload(l) {
    var p = {};
    ['name', 'subtitle', 'short_description', 'description', 'rules', 'city_id', 'category_id', 'address', 'latitude', 'longitude',
      'google_maps_url', 'phone', 'email', 'website_url', 'facilities', 'max_advance_days'].forEach(function (k) { p[k] = l[k]; });
    p.cover_image = A.path(l.cover_image);
    p.gallery = (l.gallery || []).map(A.path).filter(Boolean);
    p.seasons = l.seasons.map(function (s) {
      var sch = {};
      DAYS.forEach(function (d) { sch[d] = s.schedule[d] || null; });
      return { name: s.name || 'Program', start: s.start, end: s.end, last_entry: s.last_entry || null, schedule: sch };
    });
    p.closed_dates = l.closed_dates.filter(function (d) { return /^\d{4}-\d{2}-\d{2}$/.test(d || ''); });
    var used = {};
    p.display_categories = l.display_categories.filter(function (c) { return c.name; }).map(function (c, i) {
      var id = c.id || A.slug(c.name) || ('categorie-' + (i + 1));
      while (used[id]) id += '-' + (i + 1);
      used[id] = true;
      c.id = id;
      return { id: id, name: c.name, sort_order: i + 1 };
    });
    p.faqs = l.faqs.filter(function (f) { return f.q && f.a; });
    var lg = l.lodging;
    p.lodging = {
      enabled: !!lg.enabled, type: lg.type || null, classification: lg.classification || null, check_in: lg.check_in || null, check_out: lg.check_out || null,
      price_from: lg.price_from, description: lg.description || null, policies: lg.policies || null, phone: lg.phone || null, email: lg.email || null,
      facilities: lg.facilities || [], gallery: (lg.gallery || []).map(A.path).filter(Boolean),
      rooms: (lg.rooms || []).filter(function (r) { return r.name; }).map(function (r) {
        return { name: r.name, capacity: r.capacity, beds: r.beds || null, count: r.count, price_from: r.price_from, description: r.description || null, facilities: r.facilities || [], images: (r.images || []).map(A.path).filter(Boolean) };
      }),
      links: (lg.links || []).filter(function (x) { return x.url; }).map(function (x) { return { platform: x.platform || 'other', url: x.url, label: x.label || null }; }),
    };
    return p;
  }
  /** Saves; resolves {message} on success, false after showing why it didn't. */
  function doSave() {
    if (busy) return Promise.resolve(false);
    if (!cur.name) { O.flash('Scrie numele locației.', true); return Promise.resolve(false); }
    for (var i = 0; i < richBoxes.length; i++) {
      var b = richBoxes[i][0];
      if (document.contains(b) && b.richOver()) {
        O.flash('„' + richBoxes[i][1] + '” e prea lungă: taie din text (cel mult ' + b.richMax + ' de caractere cu tot cu formatare).', true);
        b.scrollIntoView({ block: 'center' });
        return Promise.resolve(false);
      }
    }
    busy = true;
    root.classList.add('is-busy');
    var isNew = !cur.id;
    var req = isNew ? A.api('/locations', { method: 'POST', body: payload(cur) }) : A.api('/locations/' + cur.id, { method: 'PUT', body: payload(cur) });
    return req.then(function (r) {
      var saved = r && r.data && r.data.location;
      if (saved) {
        cur = normalize(saved);
        dirty = false;
        if (isNew) history.replaceState(null, '', '/organizator/locatii?id=' + cur.id);
        drawEditor();
      }
      return { message: r && r.message };
    }, function (err) {
      O.flash(A.errText(err, 'Nu am putut salva locația.'), true);
      return false;
    }).then(function (res) { busy = false; root.classList.remove('is-busy'); return res; });
  }

  /* =================== routing =================== */
  function route() {
    var p = params();
    var id = parseInt(p.get('id'), 10);
    if (id > 0) openEditor(id);
    else if (p.get('nou')) openEditor(null);
    else showList();
    window.scrollTo(0, 0);
  }
  window.addEventListener('popstate', route);
  window.addEventListener('beforeunload', function (e) { if (dirty) { e.preventDefault(); e.returnValue = ''; } });
  root.addEventListener('click', function (e) {
    var a = e.target.closest('a[href^="/organizator/locatii"]');
    if (!a || e.ctrlKey || e.metaKey || e.shiftKey || a.target === '_blank') return;
    if (dirty && !window.confirm('Ai modificări nesalvate. Pleci fără să le salvezi?')) { e.preventDefault(); return; }
    e.preventDefault();
    dirty = false;
    go(a.getAttribute('href'));
  });
  $('am-loc-edit').addEventListener('input', function () { dirty = true; });
  $('am-loc-edit').addEventListener('change', function () { dirty = true; });
  $('am-loc-retry').addEventListener('click', function () { $('am-loc-failed').hidden = true; route(); });

  O.ready.then(function (ok) { if (ok) route(); });
})();
