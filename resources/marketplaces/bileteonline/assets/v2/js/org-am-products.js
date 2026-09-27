/* bilete.online v2: the operator's products (/organizator/produse): access tickets, experiences and packages.
   A filtered list and, for ?nou=1 (a new product) and ?id=N (an existing one), a step-by-step editor: what it is, where
   (location and the site's category), name and story, the tickets and prices (or, for a package, its contents and its
   price), when it can be used, add-ons, what the client should know, pictures, the last settings and a summary to check
   and send. Only the steps that fit the kind are shown; every step can be reopened from the list of steps or from the
   summary. Suggestions (ticket names, what is included, add-ons…) come from the product's category, never from one
   kind of operator. A draft saves itself when moving between steps; an approved product is live, so its changes wait
   for "Salvează". The product object is sent whole, exactly as the core validates it (OrganizerCatalog::validateProduct).
   Product icons are keys of includes/v2/product-icons.php (SVG, never emoji). Uses window.BO_ORG and window.BO_AM. */
(function () {
  'use strict';
  var O = window.BO_ORG, A = window.BO_AM, root = document.getElementById('am-prod');
  if (!O || !A || !root) return;
  var el = O.el, F = O.fmt, L = A.L;
  var $ = function (id) { return document.getElementById(id); };
  var TYPES = L.product_types || { access: 'Bilet de acces', experience: 'Experiență', package: 'Pachet' };
  var TYPE_ICON = L.product_type_icons || { access: 'ticket', experience: 'lightning', package: 'gift' };
  var ICONS = L.product_icons || [];
  var ICON = {};
  ICONS.forEach(function (x) { ICON[x[0]] = x; });
  var meta = null, locations = [], products = [];
  var details = {}; // product id → full product (package components need their variants)
  var W = null;     // the open editor

  function params() { return new URLSearchParams(window.location.search); }
  function go(url, replace) {
    if (replace) history.replaceState(null, '', url); else history.pushState(null, '', url);
    route();
  }
  function locById(id) { return locations.filter(function (l) { return l.id === id; })[0] || null; }
  function lei(v) { return F.money(v || 0); }

  /* =================== data =================== */
  function loadBase() {
    return Promise.all([
      meta ? Promise.resolve(meta) : A.api('/meta').then(function (r) { meta = (r && r.data) || {}; return meta; }),
      A.api('/locations').then(function (r) { locations = ((r && r.data && r.data.locations) || []); return locations; }),
    ]);
  }
  function loadProducts() {
    return A.api('/products').then(function (r) { products = ((r && r.data && r.data.products) || []).filter(function (p) { return p && p.id != null; }); return products; });
  }
  function productDetail(id) {
    if (details[id]) return Promise.resolve(details[id]);
    return A.api('/products/' + id).then(function (r) { details[id] = (r && r.data && r.data.product) || null; return details[id]; });
  }

  /* =================== list =================== */
  function fillLocFilter() {
    var s = $('am-f-loc'), keep = s.value || params().get('locatie') || '';
    s.textContent = '';
    s.appendChild(el('option', { value: '', text: 'Toate locațiile' }));
    locations.forEach(function (l) { s.appendChild(el('option', { value: String(l.id), text: l.name || ('Locația ' + l.id) })); });
    s.value = keep;
    if (s.value !== keep) s.value = '';
  }
  function showList() {
    closeWizard();
    $('am-prod-edit').hidden = true;
    $('am-prod-list').hidden = false;
    $('am-prod-head').hidden = false;
    $('am-prod-filters').hidden = false;
    var box = $('am-prod-list');
    box.textContent = '';
    box.appendChild(el('p', { class: 've-state', text: 'Se încarcă…' }));
    Promise.all([loadBase(), loadProducts()]).then(function () { fillLocFilter(); drawList(); }, function (err) {
      if (err && err.status === 401) return;
      box.hidden = true;
      $('am-prod-failed').hidden = false;
    });
  }
  function drawList() {
    var box = $('am-prod-list');
    box.textContent = '';
    if (!locations.length) {
      box.appendChild(el('div', { class: 'org-empty' }, [
        el('span', { class: 'org-empty-ic' }, [O.icon('map-pin')]),
        el('b', { text: 'Întâi, locația' }),
        el('p', { text: 'Biletele de acces și pachetele țin de o locație. Adaug-o, apoi revino aici. O experiență poate fi și fără locație.' }),
        el('a', { class: 'btn btn-primary', href: '/organizator/locatii?nou=1' }, [O.icon('plus'), el('span', { text: 'Adaugă locația' })]),
        el('a', { class: 'btn btn-ghost', href: '/organizator/produse?nou=1' }, [O.icon('lightning'), el('span', { text: 'Experiență fără locație' })]),
      ]));
      return;
    }
    var loc = parseInt($('am-f-loc').value, 10) || null, type = $('am-f-type').value || null;
    var shown = products.filter(function (p) { return (!loc || p.location_id === loc) && (!type || p.type === type); });
    if (!shown.length) {
      box.appendChild(el('div', { class: 'org-empty' }, [
        el('span', { class: 'org-empty-ic' }, [O.icon('ticket')]),
        el('b', { text: products.length ? 'Niciun produs pentru filtrele alese' : 'Niciun produs încă' }),
        el('p', { text: 'Un bilet de acces pentru intrare, o experiență (un tur, un atelier, o activitate) sau un pachet cu amândouă.' }),
        el('a', { class: 'btn btn-primary', href: '/organizator/produse?nou=1' + (loc ? '&locatie=' + loc : '') }, [O.icon('plus'), el('span', { text: 'Adaugă un produs' })]),
      ]));
      return;
    }
    var list = el('div', { class: 've-prods' });
    ['access', 'experience', 'package'].forEach(function (t) {
      var group = shown.filter(function (p) { return p.type === t; });
      if (!group.length) return;
      var g = el('div', { class: 've-prod-group' }, [el('p', { class: 've-sec-k', text: { access: 'Bilete de acces', experience: 'Experiențe', package: 'Pachete' }[t] })]);
      group.forEach(function (p) { g.appendChild(prodRow(p)); });
      list.appendChild(g);
    });
    box.appendChild(list);
  }
  function prodRow(p) {
    var src = A.imgUrl(p.image);
    var l = locById(p.location_id);
    var media = el('span', { class: 've-prod-media' }, src ? [el('img', { src: src, alt: '' })] : [O.icon('pi-' + (TYPE_ICON[p.type] || 'ticket'))]);
    var hint = A.statusHint(p);
    var t = el('div', { class: 've-prod-t' }, [
      el('b', { text: p.title || 'Produs fără titlu' }),
      el('small', { text: [TYPES[p.type] || '', l ? l.name : '', p.booking_mode === 'slot' ? 'cu oră' : (p.type === 'package' ? '' : 'toată ziua'), p.variants_count ? p.variants_count + (p.variants_count === 1 ? ' bilet' : ' bilete') : ''].filter(Boolean).join(' · ') }),
      el('span', { class: 've-prod-tags' }, A.statusTags(p)),
      hint ? el('small', { class: 'am-hint', text: hint }) : null,
    ]);
    var price = el('div', { class: 've-prod-price' }, p.min_price != null ? [el('small', { text: 'de la' }), el('b', { text: lei(p.min_price) })] : []);
    var tools = el('div', { class: 've-prod-tools' });
    tools.appendChild(el('a', { class: 'btn btn-ghost', href: '/organizator/produse?id=' + p.id }, [O.icon('gear-six'), el('span', { text: 'Editează' })]));
    var dup = A.button('copy', 'Copiază', 'btn btn-ghost');
    dup.addEventListener('click', function () { duplicate(p.id); });
    tools.appendChild(dup);
    if (p.is_published && p.public_path && (!p.review_status || p.review_status === 'approved')) {
      tools.appendChild(el('a', { class: 've-icon-btn', href: p.public_path, target: '_blank', rel: 'noopener', 'aria-label': 'Vezi pe site' }, [O.icon('arrow-right')]));
    }
    return el('article', { class: 've-prod' + (p.is_published ? '' : ' is-off') }, [media, t, price, tools]);
  }
  function duplicate(id) {
    return A.api('/products/' + id + '/duplicate', { method: 'POST', body: {} }).then(function (r) {
      O.flash((r && r.message) || 'Copia a fost creată.');
      var copy = r && r.data && r.data.product;
      if (copy) {
        products = products.filter(function (x) { return x.id !== copy.id; }).concat([copy]);
        if (W) W.dirty = false;
        go('/organizator/produse?id=' + copy.id);
      }
    }, function (err) { O.flash(A.errText(err, 'Nu am putut copia produsul.'), true); });
  }
  function hideListBits() {
    $('am-prod-list').hidden = true;
    $('am-prod-failed').hidden = true;
    $('am-prod-head').hidden = true;
    $('am-prod-filters').hidden = true;
  }

  /* =================== helpers for the editor =================== */
  function esc(s) { return String(s == null ? '' : s).replace(/[&<>"']/g, function (c) { return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]; }); }
  function ic(name) { return '<svg class="ic" aria-hidden="true" focusable="false"><use href="#i-' + name + '"/></svg>'; }
  function pic(key) { return ic('pi-' + (ICON[key] ? key : 'ticket')); }
  function jv(v) { return esc(JSON.stringify(v === undefined ? null : v)); }
  function clone(o) { return JSON.parse(JSON.stringify(o)); }
  function fold(s) { return A.fold(s); }
  function num(v) { return v == null || v === '' || isNaN(v) ? null : Number(v); }
  function mins(t) { if (!t || !/^\d{2}:\d{2}$/.test(t)) return null; var a = t.split(':'); return +a[0] * 60 + +a[1]; }
  function hhmm(m) { return String(Math.floor(m / 60)).padStart(2, '0') + ':' + String(m % 60).padStart(2, '0'); }
  function durTxt(m) { if (!m) return ''; if (m < 60) return m + ' min'; var h = Math.floor(m / 60), r = m % 60; return h + (h === 1 ? ' oră' : ' ore') + (r ? ' ' + r + ' min' : ''); }
  function reduced() { return window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches; }
  function getP(path) { return path.split('.').reduce(function (o, k) { return o == null ? o : o[k]; }, W.p); }
  function setP(path, val) {
    var ks = path.split('.'), o = W.p;
    for (var i = 0; i < ks.length - 1; i++) o = o[ks[i]];
    o[ks[ks.length - 1]] = val;
  }
  function idOf(path) { return 'wz-' + path.replace(/\./g, '-'); }

  var DAYS = [[1, 'Luni', 'mon'], [2, 'Marți', 'tue'], [3, 'Miercuri', 'wed'], [4, 'Joi', 'thu'], [5, 'Vineri', 'fri'], [6, 'Sâmbătă', 'sat'], [7, 'Duminică', 'sun']];
  var MONTHS = ['ian', 'feb', 'mar', 'apr', 'mai', 'iun', 'iul', 'aug', 'sep', 'oct', 'noi', 'dec'];
  var MONTHS_LONG = (L.months && L.months.length === 12) ? L.months : ['ianuarie', 'februarie', 'martie', 'aprilie', 'mai', 'iunie', 'iulie', 'august', 'septembrie', 'octombrie', 'noiembrie', 'decembrie'];
  var LANGS = [['ro', 'Română'], ['en', 'Engleză'], ['hu', 'Maghiară'], ['de', 'Germană'], ['fr', 'Franceză']];
  var CANCEL = [['Anulare gratuită cu 24 de ore înainte.', '24 de ore'], ['Anulare gratuită cu 48 de ore înainte.', '48 de ore'], ['Anulare gratuită cu 7 zile înainte.', '7 zile'], ['Biletul nu se returnează.', 'Nereturnabil']];
  function mdText(md) { if (!md || !/^\d{2}-\d{2}$/.test(md)) return ''; return (+md.slice(3)) + ' ' + MONTHS_LONG[+md.slice(0, 2) - 1]; }

  /* =================== suggestions, by category ===================
     KIT_BASE fits any operator; a category only overrides what is different for it. They are one-tap shortcuts:
     nothing here is required and the operator can write anything else. */
  var KIT_BASE = {
    titles: { access: ['Intrare', 'Bilet de zi', 'Abonament'], experience: ['Tur ghidat', 'Atelier', 'Închiriere'], package: ['Intrare + experiență', 'Pachet de familie'] },
    vt: { access: [['Adult', {}], ['Copil', { is_child: true }], ['Elev / student', { description: 'Cu legitimație valabilă' }], ['Pensionar', {}], ['Grup', { min_per_order: 8, description: 'Minimum 8 persoane' }], ['Familie 2+2', { price_type: 'per_unit', persons_max: 4 }]],
      experience: [['Persoană', {}], ['Copil', {}], ['Grup privat', { price_type: 'per_unit', persons_max: 10 }]] },
    incl: { access: ['Acces toată ziua'], experience: ['Instructaj'], package: ['Toate biletele din pachet'] },
    notIncl: ['Transport', 'Mâncare și băuturi'],
    req: ['Vino cu 15 minute mai devreme'],
    terms: ['Biletul se arată la intrare, de pe telefon.'],
    addons: ['Pachet foto', 'Parcare'],
    icons: ['ticket', 'star', 'group', 'lightning', 'gift', 'camera', 'parking', 'compass'],
    unit: { many: 'unități', eg: 'Ai 10 unități. Una plecată la 10:00 pentru o oră e iar liberă la 11:00.' },
    perUnit: 'O echipă, o mașină, un loc de cort: un bilet, oricâți ar fi.',
    personsHint: 'Câte persoane intră pe un singur bilet.',
    ph: { short: { access: 'Intrarea, valabilă toată ziua.', experience: 'Ce face clientul, cât durează și ce e inclus.', package: 'Ce primește clientul, într-o frază.' }, desc: 'Ce face clientul, cât durează, ce vede sau ce ia acasă…', meet: 'Recepția, la intrarea principală', unit: { access: 'persoană / zi', experience: 'persoană', package: 'pachet' } },
    langs: false,
  };
  var KITS = {
    fun: {
      titles: { access: ['Bilet de zi, toate atracțiile', 'Pass sezon', 'Bilet de seară'], experience: ['Karting', 'Tur cu roata panoramică', 'Petrecere de aniversare'], package: ['Ziua familiei', 'Bilet de zi + fast pass'] },
      vt: { access: [['Adult', {}], ['Copil sub 1,20 m', { is_child: true, description: 'Copiii sub 1 m intră gratuit' }], ['Familie 2+2', { price_type: 'per_unit', persons_max: 4 }], ['Pass 2 zile', { validity_days: 2 }], ['Grup', { min_per_order: 15, description: 'Minimum 15 persoane' }]],
        experience: [['10 minute', { duration_minutes: 10 }], ['O tură', {}], ['Petrecere, până la 15 copii', { price_type: 'per_unit', persons_max: 15 }]] },
      incl: { access: ['Acces nelimitat la atracții', 'Locul de joacă'], experience: ['Echipament de protecție', 'Instructaj'] },
      req: ['Înălțime minimă la unele atracții', 'Copiii sub 12 ani intră însoțiți'],
      terms: ['Biletul se arată la intrare, de pe telefon.', 'Brățara de acces se păstrează toată ziua.', 'Unele atracții se închid pe vreme rea.'],
      addons: ['Fast pass', 'Locker', 'Pachet foto', 'Parcare'],
      icons: ['balloon', 'ticket', 'rocket', 'group', 'popcorn', 'camera', 'parking', 'gift'],
      unit: { many: 'karturi / mașinuțe', eg: 'Ai 8 karturi. Unul plecat la 10:00 pentru 10 minute e iar liber la 10:10.' },
      perUnit: 'O familie, un grup la petrecere: un bilet, oricâți ar fi.',
      personsHint: 'La biletul de familie: 4.',
      ph: { short: { access: 'O zi întreagă în parc, cu acces la toate atracțiile.', experience: 'Cursă pe pistă, cu cască și instructaj incluse.' }, desc: 'Ce atracții sunt incluse, pentru ce vârste, ce se întâmplă pe vreme rea…', meet: 'Casa de bilete de la intrare', unit: { experience: 'tură' } } },
    museum: {
      titles: { access: ['Intrare, expoziția permanentă', 'Intrare, expoziția temporară', 'Bilet combinat'], experience: ['Tur ghidat al expoziției', 'Atelier pentru copii', 'Vizită în culise'], package: ['Intrare + tur ghidat', 'Bilet de familie'] },
      vt: { access: [['Adult', {}], ['Elev / student', { description: 'Cu legitimație valabilă' }], ['Pensionar', {}], ['Copil', { is_child: true }], ['Grup școlar', { min_per_order: 15, description: 'Minimum 15 elevi', companion_label: 'Profesor însoțitor' }]],
        experience: [['Persoană', {}], ['Elev / student', {}], ['Grup privat', { price_type: 'per_unit', persons_max: 25 }]] },
      incl: { access: ['Acces la toate sălile', 'Broșura expoziției'], experience: ['Muzeograf', 'Materialele atelierului'] },
      req: ['Bagajele mari rămân la garderobă'],
      terms: ['Biletul se arată la intrare, de pe telefon.', 'Fotografiatul e permis fără bliț.'],
      addons: ['Audioghid', 'Permis de fotografiere', 'Catalogul expoziției'],
      icons: ['museum', 'columns', 'castle', 'theatre', 'compass', 'brush', 'ticket', 'group'],
      langs: true,
      ph: { short: { access: 'Acces la expoziția permanentă, în ziua aleasă.', experience: 'O oră prin sălile principale, cu povestea fiecărui obiect.' }, desc: 'Ce vede vizitatorul, cât durează vizita, ce e nou…', meet: 'Holul de la intrare, lângă garderobă' } },
    nature: {
      titles: { access: ['Acces în rezervație', 'Intrare în parc', 'Loc de camping'], experience: ['Închiriere biciclete', 'Traseu ghidat', 'Închiriere barcă'], package: ['Ziua în natură', 'Familie 2+2 cu o activitate'] },
      incl: { access: ['Acces toată ziua', 'Harta traseelor'], experience: ['Echipament', 'Instructaj'] },
      req: ['Încălțăminte comodă', 'Haine potrivite pentru vreme'],
      terms: ['Biletul se arată la intrare, de pe telefon.', 'Animalele de companie sunt acceptate în lesă.', 'Focul e permis doar în locurile amenajate.'],
      addons: ['Închiriere echipament', 'Parcare', 'Pachet foto'],
      icons: ['tree', 'mountains', 'boat', 'bicycle', 'tent', 'hike', 'parking', 'ticket'],
      unit: { many: 'bărci / biciclete', eg: 'Ai 10 biciclete. Una plecată la 10:00 pentru o oră e iar liberă la 11:00.' },
      perUnit: 'O barcă, o mașină, un loc de cort: un bilet, oricâți ar fi.',
      personsHint: 'La o barcă de 4 persoane: 4.',
      ph: { short: { access: 'Intrarea, valabilă toată ziua.', experience: 'Bicicletă pentru o oră, cu cască inclusă.' }, desc: 'Traseele, ce se poate vedea, cât durează o tură…', meet: 'Punctul de închirieri de la intrare' } },
    adventure: {
      titles: { access: ['Acces în parc'], experience: ['Traseu pentru copii', 'Traseu pentru adulți', 'Tiroliana mare'], package: ['Toate traseele'] },
      vt: { experience: [['Traseu copii', { description: 'Pentru copii peste 1,10 m' }], ['Traseu adulți', {}], ['O coborâre pe tiroliană', {}], ['Toate traseele', {}]] },
      incl: { experience: ['Echipament de siguranță', 'Instructaj'] },
      req: ['Înălțime minimă 1,10 m', 'Încălțăminte sport', 'Greutate maximă 110 kg'],
      addons: ['Pachet foto', 'Mănuși'],
      icons: ['mountains', 'tree', 'rocket', 'hike', 'group', 'camera', 'ticket', 'star'],
      ph: { short: { experience: 'Un traseu prin copaci, cu instructaj și echipament incluse.' }, meet: 'Cabana de echipare' } },
    tours: {
      titles: { experience: ['Tur ghidat prin centrul vechi', 'Tur cu bicicleta', 'Plimbare cu barca'], package: ['Două tururi, o zi'] },
      vt: { experience: [['Persoană', {}], ['Copil', {}], ['Tur privat', { price_type: 'per_unit', persons_max: 10 }]] },
      incl: { experience: ['Ghid', 'Intrările din traseu'] },
      req: ['Încălțăminte comodă'],
      addons: ['Degustare', 'Transport de la hotel'],
      icons: ['compass', 'map', 'boat', 'bicycle', 'museum', 'camera', 'group', 'star'],
      langs: true,
      ph: { short: { experience: 'Două ore prin locurile care nu apar în ghiduri.' }, meet: 'În fața intrării principale' } },
    workshops: {
      titles: { experience: ['Atelier de olărit', 'Atelier de pictură', 'Curs de gătit'], package: ['Două ateliere'] },
      vt: { experience: [['Participant', {}], ['Copil + părinte', { price_type: 'per_unit', persons_max: 2 }], ['Grup privat', { price_type: 'per_unit', persons_max: 12 }]] },
      incl: { experience: ['Toate materialele', 'Obiectul realizat îl iei acasă'] },
      req: ['Haine pe care nu te temi să le pătezi'],
      addons: ['Ambalaj cadou'],
      icons: ['brush', 'palette', 'scissors', 'chef', 'cake', 'group', 'gift', 'star'],
      langs: true,
      ph: { short: { experience: 'Două ore de lucru, cu toate materialele incluse.' }, meet: 'Atelierul, la intrarea principală' } },
    escape: {
      titles: { access: ['Card cadou'], experience: ['Camera „Laboratorul”', 'Camera „Seiful”'], package: ['Două camere, o seară'] },
      vt: { access: [['Echipă, 2–6 jucători', { price_type: 'per_unit', persons_max: 6 }]], experience: [['Echipă, 2–6 jucători', { price_type: 'per_unit', persons_max: 6 }], ['Echipă mare, 7–10 jucători', { price_type: 'per_unit', persons_max: 10 }]] },
      incl: { experience: ['Game master', 'Instructaj'] },
      req: ['Vino cu 10 minute mai devreme', 'Sub 14 ani, doar cu un adult'],
      addons: ['Poză de echipă printată', 'Timp în plus'],
      icons: ['key', 'puzzle', 'group', 'lightning', 'gift', 'camera', 'theatre', 'star'],
      unit: { many: 'camere', eg: 'Ai 3 camere. O cameră începută la 18:00 pentru o oră e iar liberă la 19:00.' },
      perUnit: 'O echipă întreagă: un bilet, oricâți jucători ar fi.',
      personsHint: 'Câți jucători intră într-o cameră.',
      langs: true,
      ph: { short: { experience: 'Aveți o oră să ieșiți. Pentru 2–6 jucători.' }, desc: 'Povestea camerei, dificultatea, pentru ce vârste e…', meet: 'Recepția', unit: { experience: 'echipă' } } },
    zoo: {
      titles: { access: ['Intrare la grădina zoologică', 'Intrare acvariu'], experience: ['Hrănirea animalelor', 'Tur cu îngrijitorul'], package: ['Intrare + hrănire'] },
      incl: { access: ['Acces în toate zonele'], experience: ['Îngrijitor'] },
      req: ['Animalele se hrănesc doar cu îngrijitorul'],
      terms: ['Biletul se arată la intrare, de pe telefon.', 'Animalele de companie nu au acces.'],
      addons: ['Hrană pentru animale', 'Pachet foto'],
      icons: ['paw', 'bird', 'fish', 'tree', 'ticket', 'group', 'camera', 'gift'] },
    family: {
      titles: { access: ['Intrare loc de joacă', 'Abonament 10 intrări'], experience: ['Petrecere de aniversare', 'Atelier pentru copii'], package: ['Ziua familiei'] },
      vt: { access: [['Copil', { is_child: true }], ['Adult însoțitor', {}], ['Abonament 10 intrări', { validity_days: 60 }]], experience: [['Copil', {}], ['Petrecere, până la 15 copii', { price_type: 'per_unit', persons_max: 15 }]] },
      incl: { access: ['Acces 2 ore'], experience: ['Animator', 'Materiale'] },
      req: ['Șosete obligatorii', 'Copiii rămân sub supravegherea unui adult'],
      addons: ['Tort', 'Pachet foto', 'Invitații'],
      icons: ['group', 'baby', 'balloon', 'cake', 'confetti', 'game', 'ticket', 'star'] },
  };
  /** The kit of a category (by its slug or name), or null. */
  function kitKeyOf(catId) {
    var c = catById(catId);
    if (!c) return null;
    var s = fold((c.slug || '') + ' ' + (c.name || ''));
    if (/distrac/.test(s)) return 'fun';
    if (/muze|expozit/.test(s)) return 'museum';
    if (/aventur/.test(s)) return 'adventure';
    if (/escape/.test(s)) return 'escape';
    if (/zoo|acvar|animal/.test(s)) return 'zoo';
    if (/atelier|creativ/.test(s)) return 'workshops';
    if (/(^|[\s-])tur(uri)?([\s-]|$)|turist/.test(s)) return 'tours';
    if (/natur|outdoor/.test(s)) return 'nature';
    if (/famil|copii/.test(s)) return 'family';
    return null;
  }
  function kit() {
    var p = W.p, l = locById(p.location_id), key = kitKeyOf(p.subcategory_id) || kitKeyOf(p.category_id) || (l ? kitKeyOf(l.category_id) : null);
    var k = (key && KITS[key]) || {}, out = clone(KIT_BASE);
    Object.keys(k).forEach(function (a) {
      var v = k[a];
      if (v && typeof v === 'object' && !Array.isArray(v) && out[a] && typeof out[a] === 'object' && !Array.isArray(out[a])) {
        Object.keys(v).forEach(function (b) {
          var w = v[b];
          if (w && typeof w === 'object' && !Array.isArray(w) && out[a][b] && typeof out[a][b] === 'object' && !Array.isArray(out[a][b])) out[a][b] = Object.assign({}, out[a][b], w);
          else out[a][b] = w;
        });
      } else out[a] = v;
    });
    out.icons = out.icons.filter(function (x) { return ICON[x]; });
    return out;
  }

  /* =================== categories =================== */
  function cats() { return (meta && meta.categories) || []; }
  function catById(id) { return id == null ? null : cats().filter(function (c) { return String(c.id) === String(id); })[0] || null; }
  function parentCats() { return cats().filter(function (c) { return !c.parent_id; }); }
  function subCats(pid) { return pid ? cats().filter(function (c) { return String(c.parent_id) === String(pid); }) : []; }
  var CAT_COLORS = ['#2BB673', '#2D6CCD', '#F2A900', '#E43A33', '#9B5DE5', '#FF8FA3', '#06A77D', '#3A86FF', '#1E5B48', '#C77DFF', '#6C757D', '#4361EE'];

  /* =================== the product, in and out =================== */
  function blankVariant(type, name, extra) {
    return Object.assign({
      id: null, name: name == null ? null : name, description: null, price: null, price_type: 'per_person', persons_min: null, persons_max: null,
      is_child: false, min_age: null, max_age: null, duration_minutes: null, validity_days: 1, min_per_order: 1, max_per_order: 20, step_qty: null,
      companion_label: null, pos_price: null, pos_only: false, capacity_share: 1, is_active: true, is_refundable: true, _open: true,
    }, extra || {});
  }
  function blankWeek(open, close) {
    var d = {};
    DAYS.forEach(function (x) { d[x[0]] = { on: true, slots: [{ open: open, close: close, is_active: true }] }; });
    return d;
  }
  function blank(locId) {
    return {
      id: null, product_type: null, access_kind: 'person', service_type: 'rental', location_id: locId || null,
      category_id: null, subcategory_id: null, display_category: null, city_id: null,
      title: null, icon: null, subtitle: null, short_description: null, description: null,
      variants: [], booking_mode: 'day', daily_capacity: null, duration_minutes: 60, slot_interval_minutes: 60,
      capacity_mode: 'per_slot', capacity_per_slot: 10, use_location_schedule: false, periods: [], exceptions: [],
      booking_lead_time_hours: 0, booking_max_advance_days: null, addons: [], package_items: [],
      unit_label: null, meeting_point: null, included_items: [], not_included: [], requirements: [], age_min: null, age_max: null,
      languages: [], usage_terms: null, cancellation_policy: null, cover_image: null, gallery: [],
      access_requirement: 'none', issuing_company: 'primary', requires_vehicle_info: false, pos_only: false,
      difficulty_level: null, is_indoor: false, is_outdoor: false, is_kid_friendly: false, is_accessible: false, is_weather_sensitive: false,
      review_status: null, is_published: false, public_path: null, rejection_reason: null, submitted_at: null, reviewed_at: null,
    };
  }
  /** Rows of the core's schedule → periods: one per season (or the whole year), each day with its hours. */
  function fromSchedules(rows) {
    var map = {}, order = [];
    (rows || []).forEach(function (r) {
      var k = (r.season_start || '') + '|' + (r.season_end || '');
      if (!map[k]) {
        map[k] = { season: r.season_start && r.season_end ? { start: r.season_start, end: r.season_end } : null, days: {} };
        DAYS.forEach(function (x) { map[k].days[x[0]] = { on: false, slots: [] }; });
        order.push(k);
      }
      var d = map[k].days[r.day_of_week];
      if (!d) return;
      d.on = true;
      d.slots.push({ open: r.open, close: r.close, is_active: r.is_active !== false });
    });
    return order.map(function (k) {
      DAYS.forEach(function (x) { var d = map[k].days[x[0]]; if (!d.slots.length) d.slots.push({ open: '09:00', close: '18:00', is_active: true }); });
      return map[k];
    });
  }
  function toSchedules(periods) {
    var out = [];
    periods.forEach(function (per) {
      DAYS.forEach(function (x) {
        var d = per.days[x[0]];
        if (!d || !d.on) return;
        d.slots.forEach(function (s) {
          if (!s.open || !s.close) return;
          out.push({ day_of_week: x[0], open: s.open, close: s.close, season_start: per.season ? per.season.start : null, season_end: per.season ? per.season.end : null, is_active: s.is_active !== false });
        });
      });
    });
    return out;
  }
  function plainToHtml(t) {
    if (t == null || t === '') return null;
    if (/<[a-z][\s\S]*>/i.test(t)) return t;
    return String(t).split(/\n{2,}/).map(function (par) { return '<p>' + esc(par.trim()).replace(/\n/g, '<br>') + '</p>'; }).join('');
  }
  var KEEP = ['id', 'access_kind', 'service_type', 'location_id', 'category_id', 'subcategory_id', 'display_category', 'city_id', 'title', 'subtitle',
    'short_description', 'description', 'booking_mode', 'daily_capacity', 'duration_minutes', 'slot_interval_minutes', 'capacity_mode', 'capacity_per_slot',
    'use_location_schedule', 'booking_lead_time_hours', 'booking_max_advance_days', 'unit_label', 'meeting_point', 'age_min', 'age_max', 'usage_terms',
    'cancellation_policy', 'access_requirement', 'issuing_company', 'requires_vehicle_info', 'pos_only', 'difficulty_level', 'is_indoor', 'is_outdoor',
    'is_kid_friendly', 'is_accessible', 'is_weather_sensitive', 'review_status', 'is_published', 'public_path', 'rejection_reason', 'submitted_at', 'reviewed_at'];
  function fromApi(src) {
    var p = blank(src.location_id);
    KEEP.forEach(function (k) { if (src[k] !== undefined && src[k] !== null) p[k] = src[k]; });
    p.product_type = src.product_type || src.type || 'access';
    p.icon = ICON[src.icon] ? src.icon : null;
    p.description = plainToHtml(p.description);
    ['languages', 'included_items', 'not_included', 'requirements'].forEach(function (k) { p[k] = Array.isArray(src[k]) ? src[k].slice(0) : []; });
    p.cover_image = src.cover_image || null;
    p.gallery = Array.isArray(src.gallery) ? src.gallery.filter(Boolean) : [];
    p.variants = (src.variants || []).map(function (v) { var o = blankVariant(p.product_type, v.name, v); o._open = false; return o; });
    if (!p.variants.length) p.variants = [blankVariant(p.product_type, p.product_type === 'package' ? 'Pachet' : null)];
    p.periods = fromSchedules(src.schedules);
    p.exceptions = (src.exceptions || []).map(function (x) { return { date: x.date, is_closed: !!x.is_closed, open: x.open || null, close: x.close || null, reason: x.reason || null }; });
    p.addons = (src.addons || []).map(function (a) { return { id: a.id, name: a.name, price: a.price, included_qty: a.included_qty, max_per_unit: a.max_per_unit, is_active: a.is_active !== false }; });
    p.package_items = (src.package_items || []).map(function (i) { return { product_id: i.product_id, variant_id: i.variant_id, quantity: i.quantity || 1, allocated_price: i.allocated_price, _title: i.product_title, _vname: i.variant_name }; });
    var l = locById(p.location_id);
    if (p.use_location_schedule && !(l && l.seasons && l.seasons.length)) p.use_location_schedule = false;
    return p;
  }
  /** What the core gets (the same keys and rules as OrganizerCatalog::validateProduct). */
  function payload(p) {
    var t = p.product_type, out = {};
    ['access_kind', 'service_type', 'location_id', 'category_id', 'subcategory_id', 'display_category', 'title', 'subtitle', 'short_description',
      'description', 'icon', 'booking_mode', 'daily_capacity', 'duration_minutes', 'slot_interval_minutes', 'capacity_mode', 'capacity_per_slot',
      'use_location_schedule', 'booking_lead_time_hours', 'booking_max_advance_days', 'unit_label', 'meeting_point', 'age_min', 'age_max', 'usage_terms',
      'cancellation_policy', 'access_requirement', 'issuing_company', 'requires_vehicle_info', 'pos_only', 'difficulty_level', 'is_indoor', 'is_outdoor',
      'is_kid_friendly', 'is_accessible', 'is_weather_sensitive', 'languages', 'included_items', 'not_included', 'requirements']
      .forEach(function (k) { out[k] = p[k] === undefined ? null : p[k]; });
    out.product_type = t;
    // A product at a location takes the location's city; one without keeps what it had.
    out.city_id = p.location_id ? null : (p.city_id || null);
    if (!ICON[out.icon]) out.icon = null;
    if (t === 'package') out.booking_mode = 'day';
    if (t !== 'experience') out.access_requirement = 'none';
    if (!(meta && meta.has_secondary_issuer)) out.issuing_company = 'primary';
    if (!p.category_id) out.subcategory_id = null;
    var l = locById(p.location_id);
    if (!(l && l.seasons && l.seasons.length)) out.use_location_schedule = false;
    if (!(l && (l.display_categories || []).some(function (c) { return c.id === p.display_category; }))) out.display_category = null;
    out.cover_image = A.path(p.cover_image);
    out.gallery = (p.gallery || []).map(A.path).filter(Boolean);
    out.variants = p.variants.map(function (v) {
      var o = {};
      ['id', 'name', 'description', 'price', 'price_type', 'persons_min', 'persons_max', 'is_child', 'min_age', 'max_age', 'duration_minutes',
        'validity_days', 'min_per_order', 'max_per_order', 'step_qty', 'companion_label', 'pos_price', 'pos_only', 'capacity_share', 'is_active', 'is_refundable']
        .forEach(function (k) { o[k] = v[k] === undefined ? null : v[k]; });
      if (t === 'package' && !o.name) o.name = 'Pachet';
      if (o.validity_days == null) o.validity_days = 1;
      return o;
    });
    out.schedules = out.use_location_schedule || t === 'package' ? [] : toSchedules(p.periods);
    out.exceptions = t === 'package' ? [] : p.exceptions.filter(function (x) { return x.date; }).map(function (x) {
      return { date: x.date, is_closed: !!x.is_closed, open: x.is_closed ? null : (x.open || null), close: x.is_closed ? null : (x.close || null), reason: x.reason || null };
    });
    out.addons = t === 'package' ? [] : p.addons.filter(function (a) { return a.name; }).map(function (a) {
      return { id: a.id || null, name: a.name, price: a.price || 0, included_qty: a.included_qty || 0, max_per_unit: a.max_per_unit == null ? 1 : a.max_per_unit, is_active: a.is_active !== false };
    });
    out.package_items = t === 'package' ? p.package_items.filter(function (i) { return i.product_id; }).map(function (i) {
      return { product_id: i.product_id, variant_id: i.variant_id || null, quantity: i.quantity || 1, allocated_price: i.allocated_price == null ? null : i.allocated_price };
    }) : [];
    return out;
  }

  /* =================== steps =================== */
  var PHASES = ['Bazele', 'Ce vinzi, concret', 'Prezentarea', 'Final'];
  var ALL_STEPS = [
    { id: 'tip', ph: 0, t: 'Ce vinzi' },
    { id: 'unde', ph: 0, t: 'Unde' },
    { id: 'nume', ph: 0, t: 'Nume și descriere' },
    { id: 'bilete', ph: 1, t: 'Bilete și prețuri', only: ['access', 'experience'] },
    { id: 'continut', ph: 1, t: 'Ce conține', only: ['package'] },
    { id: 'pret', ph: 1, t: 'Prețul pachetului', only: ['package'] },
    { id: 'cand', ph: 1, t: 'Când', only: ['access', 'experience'] },
    { id: 'extra', ph: 1, t: 'Suplimente', only: ['access', 'experience'], opt: true },
    { id: 'info', ph: 2, t: 'De știut' },
    { id: 'poze', ph: 2, t: 'Poze' },
    { id: 'setari', ph: 3, t: 'Setări finale', opt: true },
    { id: 'gata', ph: 3, t: 'Verifică și trimite' },
  ];
  function steps() {
    var t = W.p.product_type || 'access';
    return ALL_STEPS.filter(function (s) { return !s.only || s.only.indexOf(t) >= 0; });
  }
  function stepIdx(id) { var a = steps(); for (var i = 0; i < a.length; i++) if (a[i].id === id) return i; return 0; }
  function stepById(id) { return ALL_STEPS.filter(function (s) { return s.id === id; })[0]; }
  function isDraft() { return !W.p.id || W.p.review_status === 'draft' || W.p.review_status === 'rejected'; }
  function hasPhoto() { var l = locById(W.p.location_id); return !!(W.p.cover_image || (l && l.cover_image)); }
  function needsCategory() { return parentCats().length > 0; }

  /**
   * What is wrong or missing, as [{step, msg, save}]: save = the core refuses to save without it (the same rules as
   * OrganizerCatalog::validateProduct); the others only stop the request for approval (ProductsController::submit).
   */
  function problems() {
    var p = W.p, t = p.product_type, out = [];
    function add(step, msg, save) { out.push({ step: step, msg: msg, save: !!save }); }
    if (!t) { add('tip', 'Alege ce vinzi', true); return out; }
    if (t !== 'experience' && !p.location_id) add('unde', 'Alege locația', true);
    if (needsCategory() && !p.category_id) add('unde', 'Alege categoria de pe bilete.online');
    if (!p.title) add('nume', 'Scrie titlul', true);
    var vs = t === 'package' ? 'pret' : 'bilete';
    if (!p.variants.length) add(vs, 'Adaugă cel puțin un bilet', true);
    p.variants.forEach(function (v, i) {
      var who = t === 'package' ? 'Pachetul' : (v.name || 'Biletul ' + (i + 1));
      if (t !== 'package' && !v.name) add(vs, 'Biletul ' + (i + 1) + ' nu are nume', true);
      if (v.price == null) add(vs, who + ': lipsește prețul', true);
      else if (v.price > 100000) add(vs, who + ': prețul e prea mare', true);
      if (v.min_per_order && v.max_per_order && v.min_per_order > v.max_per_order) add(vs, who + ': maximul pe comandă e sub minim', true);
      if (v.persons_min && v.persons_max && v.persons_min > v.persons_max) add(vs, who + ': numărul maxim de persoane e sub minim', true);
    });
    if (!p.variants.some(function (v) { return v.is_active; })) add(vs, 'Cel puțin un bilet trebuie să se vândă');
    if (t === 'package') {
      if (!p.package_items.length) add('continut', 'Pune cel puțin un produs în pachet', true);
      p.package_items.forEach(function (it) { if (!it.variant_id) add('continut', 'Alege biletul pentru „' + (it._title || 'produs') + '”'); });
    } else {
      var own = !p.use_location_schedule;
      if (p.booking_mode === 'slot') {
        if (!p.capacity_per_slot) add('cand', 'Câte locuri ai la fiecare oră', true);
        if (!p.duration_minutes || p.duration_minutes < 5) add('cand', 'Cât durează (cel puțin 5 minute)', true);
        if (!p.slot_interval_minutes || p.slot_interval_minutes < 5) add('cand', 'La câte minute pleacă o tură (cel puțin 5)', true);
        if (own && !toSchedules(p.periods).length) add('cand', 'Adaugă programul în care se pot rezerva orele', true);
      }
      if (own) {
        p.periods.forEach(function (per, pi) {
          DAYS.forEach(function (x) {
            var d = per.days[x[0]];
            var who = x[1] + (p.periods.length > 1 ? ' (perioada ' + (pi + 1) + ')' : '');
            if (d && d.on) d.slots.forEach(function (s) {
              if (!s.open || !s.close) add('cand', who + ': completează orele programului', true);
              else if (s.open >= s.close) add('cand', who + ': ora de închidere trebuie să fie după cea de deschidere', true);
            });
          });
        });
      }
      p.exceptions.forEach(function (x) { if (x.date && !x.is_closed && x.open && x.close && x.open >= x.close) add('cand', 'Ziua specială ' + x.date + ': ora de închidere trebuie să fie după cea de deschidere', true); });
    }
    if (p.age_min != null && p.age_max != null && p.age_min > p.age_max) add('info', 'Vârsta minimă e peste cea maximă', true);
    if (!hasPhoto()) add('poze', 'Adaugă o poză (locația nu are nici ea)');
    return out;
  }
  function problemsOf(step, saveOnly) { return problems().filter(function (x) { return x.step === step && (!saveOnly || x.save); }); }
  function stepState(s) {
    if (s.id === W.step) return 'cur';
    if (!W.visited[s.id]) return '';
    return problemsOf(s.id).length ? 'warn' : 'done';
  }

  /* =================== field builders (HTML; every outside string goes through esc) =================== */
  function field(label, ctl, o) {
    o = o || {};
    return '<div class="wz-fl' + (o.bad ? ' is-bad' : '') + (o.cls ? ' ' + o.cls : '') + '">' +
      (label ? '<label class="wz-lbl"' + (o.for ? ' for="' + o.for + '"' : '') + '>' + label + (o.req ? ' <span class="wz-req" aria-hidden="true">*</span>' : '') + (o.opt ? ' <small>opțional</small>' : '') +
        (o.count ? '<span class="wz-count" data-count="' + o.count[0] + '">' + ((getP(o.count[0]) || '').length) + ' / ' + o.count[1] + '</span>' : '') + '</label>' : '') +
      ctl + (o.err ? '<p class="wz-err">' + o.err + '</p>' : '') + (o.hint ? '<p class="wz-hint">' + o.hint + '</p>' : '') + '</div>';
  }
  function inp(path, o) {
    o = o || {};
    var h = '<input class="wz-inp' + (o.big ? ' is-big' : '') + '" id="' + idOf(path) + '" data-bind="' + path + '" data-t="' + (o.t || 'text') + '" type="' + (o.type || 'text') + '"' +
      (o.mode ? ' inputmode="' + o.mode + '"' : '') + (o.max ? ' maxlength="' + o.max + '"' : '') + (o.ph ? ' placeholder="' + esc(o.ph) + '"' : '') +
      (o.re ? ' data-re="1"' : '') + ' value="' + esc(getP(path)) + '" autocomplete="off">';
    return o.suffix ? '<div class="wz-affix">' + h + '<span>' + o.suffix + '</span></div>' : h;
  }
  function money(path, o) { return inp(path, Object.assign({ t: 'num', mode: 'decimal', suffix: 'lei', ph: '0' }, o || {})); }
  function ta(path, o) {
    o = o || {};
    return '<textarea class="wz-inp wz-ta" id="' + idOf(path) + '" data-bind="' + path + '" data-t="text" rows="' + (o.rows || 3) + '"' + (o.max ? ' maxlength="' + o.max + '"' : '') + (o.ph ? ' placeholder="' + esc(o.ph) + '"' : '') + '>' + esc(getP(path)) + '</textarea>';
  }
  function stepper(path, o) {
    o = o || {};
    return '<div class="wz-num"><button type="button" data-act="step" data-path="' + path + '" data-d="-' + (o.step || 1) + '" data-min="' + (o.min == null ? 0 : o.min) + '"' + (o.re ? ' data-re="1"' : '') + ' aria-label="Mai puțin">' + ic('minus') + '</button>' +
      '<input id="' + idOf(path) + '" data-bind="' + path + '" data-t="int" data-min="' + (o.min == null ? 0 : o.min) + '" data-max="' + (o.max || 100000) + '" inputmode="numeric" value="' + esc(getP(path)) + '"' + (o.ph ? ' placeholder="' + esc(o.ph) + '"' : '') + (o.re ? ' data-re="1"' : '') + ' aria-label="' + esc(o.label || '') + '" autocomplete="off">' +
      (o.u ? '<span class="wz-u">' + o.u + '</span>' : '') +
      '<button type="button" data-act="step" data-path="' + path + '" data-d="' + (o.step || 1) + '" data-max="' + (o.max || 100000) + '" data-start="' + (o.start == null ? (o.min || 0) : o.start) + '"' + (o.re ? ' data-re="1"' : '') + ' aria-label="Mai mult">' + ic('plus') + '</button></div>';
  }
  function seg(path, opts) {
    var v = getP(path);
    return '<div class="wz-seg" role="group">' + opts.map(function (o) {
      return '<button type="button" data-act="set" data-path="' + path + '" data-val="' + jv(o[0]) + '" aria-pressed="' + (v === o[0]) + '">' + o[1] + '</button>';
    }).join('') + '</div>';
  }
  function toggle(path, title, desc) {
    var on = !!getP(path);
    return '<label class="wz-tg' + (on ? ' is-on' : '') + '"><span class="wz-tg-t"><b>' + title + '</b>' + (desc ? '<small>' + desc + '</small>' : '') + '</span>' +
      '<input type="checkbox" id="' + idOf(path) + '" data-bind="' + path + '" data-t="bool" data-re="1"' + (on ? ' checked' : '') + '><span class="wz-sw" aria-hidden="true"></span></label>';
  }
  function sel(path, opts, o) {
    o = o || {};
    var v = getP(path);
    return '<span class="wz-sel-w"><select class="wz-inp wz-sel" id="' + idOf(path) + '" data-bind="' + path + '" data-t="json"' + (o.re ? ' data-re="1"' : '') + '>' +
      opts.map(function (x) { return '<option value="' + jv(x[0]) + '"' + (JSON.stringify(x[0]) === JSON.stringify(v == null ? null : v) ? ' selected' : '') + '>' + esc(x[1]) + '</option>'; }).join('') + '</select>' + ic('caret-down') + '</span>';
  }
  function tags(path, sugg, ph, limit) {
    var arr = getP(path) || [];
    var left = (sugg || []).filter(function (s) { return arr.indexOf(s) < 0; });
    return '<div class="wz-tags">' + arr.map(function (t, i) {
      return '<span class="wz-tag">' + esc(t) + '<button type="button" data-act="untag" data-path="' + path + '" data-i="' + i + '" aria-label="Scoate ' + esc(t) + '">' + ic('x') + '</button></span>';
    }).join('') + (arr.length < (limit || 20) ? '<input id="' + idOf(path) + '" data-tagin="' + path + '" maxlength="200" placeholder="' + esc(ph || 'Scrie și apasă Enter') + '" enterkeyhint="done" autocomplete="off">' : '') + '</div>' +
      (left.length && arr.length < (limit || 20) ? '<div class="wz-sugg"><span>Sugestii:</span>' + left.map(function (s) { return '<button type="button" data-act="tag" data-path="' + path + '" data-val="' + jv(s) + '">+ ' + esc(s) + '</button>'; }).join('') + '</div>' : '');
  }
  function block(title, lead, body, o) {
    o = o || {};
    return '<section class="wz-block">' + (title ? '<div class="wz-block-h"><h3>' + title + (o.opt ? '<span class="wz-opt">opțional</span>' : '') + '</h3>' + (lead ? '<p>' + lead + '</p>' : '') + '</div>' : '') + body + '</section>';
  }
  function choice(act, val, pressed, icon, title, desc, o) {
    o = o || {};
    return '<button type="button" class="wz-card is-row" data-act="' + act + '"' + (o.path ? ' data-path="' + o.path + '"' : '') + ' data-val="' + jv(val) + '" aria-pressed="' + !!pressed + '"' + (o.disabled ? ' disabled' : '') + '>' +
      '<span class="wz-card-ic">' + icon + '</span><span class="wz-card-t"><b>' + title + '</b>' + (desc ? '<small>' + desc + '</small>' : '') + '</span></button>';
  }
  function head(id, title, lead) {
    var a = steps(), i = stepIdx(id);
    var errs = W.serverErr && W.serverErr.step === id ? '<div class="wz-alert is-bad" role="alert">' + ic('warning-circle') + '<span>' + esc(W.serverErr.msg) + '</span></div>' : '';
    return '<header class="wz-head"><p class="wz-k">' + esc(PHASES[stepById(id).ph]) + ' <i>·</i> <i>pasul ' + (i + 1) + ' din ' + a.length + '</i></p><h1 class="wz-h">' + title + '</h1>' + (lead ? '<p class="wz-lead">' + lead + '</p>' : '') + '</header>' + errs;
  }
  function tried(step) { return !!W.tried[step]; }
  function mount(name) { return '<div class="wz-mount" data-mount="' + name + '"></div>'; }

  /* =================== step: what =================== */
  var ART = {
    access: '<svg viewBox="0 0 200 128" aria-hidden="true" focusable="false"><g class="wz-a-ticket"><rect x="50" y="38" width="100" height="54" rx="8" fill="#F2A900"/><circle cx="50" cy="65" r="8" fill="var(--wz-art-bg)"/><circle cx="150" cy="65" r="8" fill="var(--wz-art-bg)"/><path d="M118 42v46" stroke="#7a5400" stroke-width="2" stroke-dasharray="4 4"/><rect x="62" y="52" width="42" height="6" rx="3" fill="#7a5400" opacity=".55"/><rect x="62" y="64" width="30" height="5" rx="2.5" fill="#7a5400" opacity=".35"/><rect x="62" y="75" width="36" height="5" rx="2.5" fill="#7a5400" opacity=".35"/></g><g class="wz-a-stub"><circle cx="134" cy="65" r="7" fill="none" stroke="#7a5400" stroke-width="2.5"/></g></svg>',
    experience: '<svg viewBox="0 0 200 128" aria-hidden="true" focusable="false"><g class="wz-a-orbit"><circle cx="100" cy="64" r="42" fill="none" stroke="#C9CEC6" stroke-width="2" stroke-dasharray="4 6"/><circle cx="142" cy="64" r="7" fill="#F2A900"/><circle cx="58" cy="64" r="5" fill="#2D6CCD"/><circle cx="100" cy="22" r="4" fill="#E43A33"/></g><circle cx="100" cy="64" r="26" fill="#1B7F4E"/><path class="wz-a-bolt" d="M104 44l-16 24h11l-4 16 16-24h-11z" fill="#fff"/></svg>',
    package: '<svg viewBox="0 0 200 128" aria-hidden="true" focusable="false"><rect x="64" y="58" width="72" height="50" rx="6" fill="#E43A33"/><rect x="95" y="58" width="10" height="50" fill="#F2A900"/><g class="wz-a-lid"><rect x="58" y="44" width="84" height="16" rx="5" fill="#FF5A52"/><rect x="95" y="44" width="10" height="16" fill="#F2A900"/><path d="M100 44c-8-14-24-12-20-2 3 6 20 2 20 2zm0 0c8-14 24-12 20-2-3 6-20 2-20 2z" fill="none" stroke="#F2A900" stroke-width="4"/></g><path class="wz-a-spark" d="M48 30l3 7 7 3-7 3-3 7-3-7-7-3 7-3z" fill="#F2A900"/><path class="wz-a-spark" d="M154 24l2 5 5 2-5 2-2 5-2-5-5-2 5-2z" fill="#2BB673"/><path class="wz-a-spark" d="M160 70l2 4 4 2-4 2-2 4-2-4-4-2 4-2z" fill="#F2A900"/></svg>',
  };
  function stTip() {
    var p = W.p, locked = !!p.id;
    var K = [
      ['access', 'Bilet de acces', 'Intrarea în locație, pe o zi sau mai multe.', 'Bilet de zi, adult / copil, abonament, parcare'],
      ['experience', 'Experiență', 'Ceva de făcut, de obicei la o oră anume.', 'Tur ghidat, atelier, joc, închiriere, activitate'],
      ['package', 'Pachet', 'Bilete și experiențe împreună, la un singur preț.', 'De pildă intrarea + o experiență, pentru o familie'],
    ];
    var h = head('tip', locked ? 'Ce vinzi' : 'Ce vrei să vinzi?', locked ? 'Tipul nu se mai schimbă după prima salvare. Pentru alt tip, fă un produs nou.' : 'Alege tipul, iar noi îți arătăm doar pașii care contează pentru el. Până la prima salvare îl poți schimba.');
    h += '<div class="wz-cards is-3">' + K.map(function (k) {
      return '<button type="button" class="wz-card wz-kind" data-act="type" data-val="' + jv(k[0]) + '" aria-pressed="' + (p.product_type === k[0]) + '"' + (locked && p.product_type !== k[0] ? ' disabled' : '') + '><span class="wz-art">' + ART[k[0]] + '</span><span class="wz-kind-t"><b>' + k[1] + '</b><small>' + k[2] + '</small><span class="wz-eg">' + k[3] + '</span></span></button>';
    }).join('') + '</div>';
    if (p.product_type === 'access') {
      h += '<div class="wz-in">' + block('Ce fel de acces?', 'Ne ajută să punem întrebările potrivite. La parcare, de pildă, îți propunem să ceri numărul mașinii.',
        '<div class="wz-cards is-2">' + [['person', 'group', 'Persoane', 'Intrare pentru oameni'], ['vehicle', 'car', 'Vehicul', 'Parcare, acces cu mașina'], ['camping', 'tent', 'Camping', 'Loc de cort sau rulotă'], ['other', 'sparkle', 'Altceva', 'Orice alt fel de acces']].map(function (o) {
          return choice('set', o[0], p.access_kind === o[0], pic(o[1]), o[2], o[3], { path: 'access_kind' });
        }).join('') + '</div>') + '</div>';
    } else if (p.product_type === 'experience') {
      h += '<div class="wz-in">' + block('Ce fel de experiență?', null,
        '<div class="wz-cards is-2">' + [['rental', 'key', 'Închiriere', 'Ceva ce clientul folosește o vreme: echipament, vehicul, spațiu'], ['guided', 'compass', 'Tur ghidat', 'Cu un ghid, la o oră fixă'], ['workshop', 'brush', 'Atelier', 'Cursuri, activități creative'], ['other', 'lightning', 'Altceva', 'Joc, atracție, spectacol, orice altceva']].map(function (o) {
          return choice('set', o[0], p.service_type === o[0], pic(o[1]), o[2], o[3], { path: 'service_type' });
        }).join('') + '</div>') + '</div>';
    } else if (p.product_type === 'package') {
      var own = products.filter(function (x) { return x.type !== 'package' && x.id !== p.id; }).length;
      h += '<div class="wz-in"><div class="wz-alert' + (own ? '' : ' is-warn') + '">' + ic('info') + '<span>' + (own
        ? 'Un pachet se face din biletele și experiențele pe care le ai deja: ai ' + own + (own === 1 ? ' produs' : ' produse') + ' din care să alegi.'
        : 'Un pachet se face din bilete și experiențe pe care le ai deja, iar acum nu ai niciunul. Adaugă întâi un bilet de acces sau o experiență.') + '</span></div></div>';
    }
    if (!locations.length && p.product_type && p.product_type !== 'experience') {
      h += '<div class="wz-alert is-warn">' + ic('map-pin') + '<span>Biletele de acces și pachetele țin de o locație, iar tu nu ai încă una. <a href="/organizator/locatii?nou=1">Adaugă locația</a>, apoi revino aici.</span></div>';
    }
    return h;
  }

  /* =================== step: where =================== */
  function locSeasonsText(l) {
    return (l.seasons || []).map(function (s) {
      var rows = [];
      DAYS.forEach(function (x) { var hh = s.schedule && s.schedule[x[2]]; rows.push(hh && hh.open && hh.close ? hh.open + '–' + hh.close : 'închis'); });
      var groups = [], start = 0;
      for (var i = 1; i <= 7; i++) {
        if (i === 7 || rows[i] !== rows[start]) {
          groups.push((i - 1 > start ? DAYS[start][1].toLowerCase() + ' – ' + DAYS[i - 1][1].toLowerCase() : DAYS[start][1].toLowerCase()) + ' ' + rows[start]);
          start = i;
        }
      }
      var allSame = rows.every(function (r) { return r === rows[0]; });
      return { label: (s.name ? s.name + ': ' : '') + mdText(s.start) + ' – ' + mdText(s.end), rows: allSame ? ['zilnic ' + rows[0]] : groups };
    });
  }
  function stUnde() {
    var p = W.p, t = p.product_type, bad = tried('unde');
    var h = head('unde', t === 'experience' ? 'Unde are loc experiența?' : 'Unde se folosește?', 'Două lucruri: locul unde vine clientul și raftul de pe bilete.online pe care îl găsește.');
    var cards = locations.map(function (l) {
      var st = l.review_status === 'approved' || !l.review_status ? '' : (l.review_status === 'pending' ? ' · locația e în verificare' : ' · locația e ciornă');
      return choice('loc', l.id, p.location_id === l.id, ic('map-pin'), esc(l.name || 'Locația ' + l.id), ((l.seasons || []).length ? 'Are program pe sezoane' : 'Fără program încă') + st);
    });
    if (t === 'experience') cards.push(choice('loc', null, p.location_id === null && W.visited.unde_loc, pic('compass'), 'Fără locație', 'Un tur care pleacă din alt loc, un atelier la client'));
    h += block('Locația', t === 'experience' ? 'Opțional pentru experiențe. Cu locație, experiența apare pe pagina ei și îi poate folosi programul.' : 'Biletul ține de o locație: de acolo își poate lua programul și pe pagina ei apare.',
      '<div class="wz-cards">' + cards.join('') + '</div>' + (bad && t !== 'experience' && !p.location_id ? '<p class="wz-err">Alege locația ca să poți merge mai departe.</p>' : '') +
      '<p class="wz-hint">Nu e în listă? <a href="/organizator/locatii?nou=1" target="_blank" rel="noopener">Adaugă o locație nouă</a> (se deschide alături), apoi <button type="button" class="wz-link" data-act="reloadloc">reîncarcă lista</button>.</p>');
    var parents = parentCats();
    if (parents.length) {
      var subs = subCats(p.category_id);
      h += block('Unde te găsesc clienții pe bilete.online', 'Categoria decide în ce liste, pagini de oraș și filtre apare produsul. Aleg-o după ce face clientul, nu după locație: un atelier ținut într-un muzeu e tot la „Ateliere”.',
        '<div class="wz-cats">' + parents.map(function (c, i) {
          return '<button type="button" class="wz-cat" data-act="cat" data-val="' + jv(c.id) + '" aria-pressed="' + (String(p.category_id) === String(c.id)) + '"><i style="background:' + CAT_COLORS[i % CAT_COLORS.length] + '"></i>' + esc(c.name) + '</button>';
        }).join('') + '</div>' +
        (subs.length ? '<div class="wz-fl wz-in"><span class="wz-lbl">Mai precis <small>opțional</small></span><div class="wz-chips">' + subs.map(function (c) {
          var on = String(p.subcategory_id) === String(c.id);
          return '<button type="button" class="wz-chip" data-act="set" data-path="subcategory_id" data-val="' + jv(on ? null : c.id) + '" aria-pressed="' + on + '">' + esc(c.name) + '</button>';
        }).join('') + '</div></div>' : '') +
        (bad && !p.category_id ? '<p class="wz-err is-soft">Poți continua și fără, dar categoria e obligatorie când trimiți produsul spre aprobare.</p>' : ''));
    }
    var l = locById(p.location_id), groups = l ? (l.display_categories || []) : [];
    if (groups.length) {
      h += block('Grupa pe pagina locației', 'Pe pagina „' + esc(l.name) + '”, biletele stau pe grupele pe care le-ai făcut tu la locație. E doar ordinea ta de acolo; nu schimbă nimic în rest pe site.',
        '<div class="wz-chips">' + [[null, 'Fără grupă']].concat(groups.map(function (g) { return [g.id, g.name]; })).map(function (g) {
          return '<button type="button" class="wz-chip" data-act="set" data-path="display_category" data-val="' + jv(g[0]) + '" aria-pressed="' + (p.display_category === g[0]) + '">' + esc(g[1]) + '</button>';
        }).join('') + '</div>', { opt: true });
    }
    return h;
  }

  /* =================== step: name =================== */
  function stNume() {
    var p = W.p, t = p.product_type, bad = tried('nume') && !p.title, K = kit(), eg = K.titles[t] || KIT_BASE.titles[t];
    var h = head('nume', 'Cum se numește?', 'Scrie ca pentru un prieten: ce primește și unde. Titlul apare peste tot; restul e opțional, dar ajută clientul să aleagă.');
    var icons = W.allIcons ? null : K.icons.slice(0);
    if (icons && p.icon && icons.indexOf(p.icon) < 0) icons.push(p.icon);
    var iconHtml;
    if (icons) {
      iconHtml = '<div class="wz-icons">' + icons.map(function (k) {
        return '<button type="button" data-act="set" data-path="icon" data-val="' + jv(k) + '" aria-pressed="' + (p.icon === k) + '" aria-label="' + esc(ICON[k][1]) + '" title="' + esc(ICON[k][1]) + '">' + pic(k) + '</button>';
      }).join('') + '<button type="button" class="is-text" data-act="set" data-path="icon" data-val="null" aria-pressed="' + (!p.icon) + '">Fără</button><button type="button" class="is-text" data-act="allicons">Toate</button></div>';
    } else {
      var byGroup = {}, order = [];
      ICONS.forEach(function (x) { if (!byGroup[x[2]]) { byGroup[x[2]] = []; order.push(x[2]); } byGroup[x[2]].push(x); });
      iconHtml = '<div class="wz-icon-groups">' + order.map(function (g) {
        return '<div><span class="wz-icon-g">' + esc(g) + '</span><div class="wz-icons">' + byGroup[g].map(function (x) {
          return '<button type="button" data-act="set" data-path="icon" data-val="' + jv(x[0]) + '" aria-pressed="' + (p.icon === x[0]) + '" aria-label="' + esc(x[1]) + '" title="' + esc(x[1]) + '">' + pic(x[0]) + '</button>';
        }).join('') + '</div></div>';
      }).join('') + '<div class="wz-icons"><button type="button" class="is-text" data-act="set" data-path="icon" data-val="null" aria-pressed="' + (!p.icon) + '">Fără iconiță</button><button type="button" class="is-text" data-act="fewicons">Mai puține</button></div></div>';
    }
    h += block(null, null,
      field('Titlul', inp('title', { max: 190, ph: eg[0], big: true }), { req: true, for: 'wz-title', bad: bad, err: bad ? 'Titlul e singurul lucru fără de care nu putem salva.' : null, count: ['title', 190] }) +
      '<div class="wz-sugg"><span>Idei:</span>' + eg.map(function (e) { return '<button type="button" data-act="set" data-path="title" data-val="' + jv(e) + '">' + esc(e) + '</button>'; }).join('') + '</div>' +
      '<div class="wz-fl"><span class="wz-lbl">Iconița <small>apare lângă titlu, în lista de bilete</small></span>' + iconHtml + '</div>');
    h += block('Pe scurt', 'Ce vede clientul înainte să deschidă produsul. Două fraze bune fac mai mult decât o pagină.',
      field('Subtitlu', inp('subtitle', { max: 190, ph: t === 'experience' ? 'Pentru toată familia' : 'Deschis tot anul' }), { opt: true, for: 'wz-subtitle' }) +
      field('Descrierea scurtă', ta('short_description', { max: 280, rows: 2, ph: K.ph.short[t] }), { opt: true, for: 'wz-short_description', count: ['short_description', 280], hint: 'Apare sub titlu, în lista de bilete a locației.' }));
    h += block('Povestea completă', 'Pentru pagina produsului: ce face clientul, cât durează, ce vede. Paragrafe scurte.', mount('description'), { opt: true });
    return h;
  }

  /* =================== step: tickets =================== */
  function varCard(v, i) {
    var p = W.p, t = p.product_type, day = p.booking_mode === 'day', base = 'variants.' + i, K = kit();
    var subt = [v.price_type === 'per_unit' ? 'pe unitate' + (v.persons_max ? ', până la ' + v.persons_max + ' pers.' : '') : 'pe persoană', v.is_child ? 'copil' : '', !v.is_active ? 'nu se vinde' : '', v.pos_only ? 'doar la casă' : ''].filter(Boolean).join(' · ');
    var bn = tried('bilete') && !v.name, bp = tried('bilete') && v.price == null;
    var h = '<div class="wz-var' + (v._open ? ' is-open' : '') + (bn || bp ? ' is-bad' : '') + '">' +
      '<button type="button" class="wz-var-h" data-act="vopen" data-i="' + i + '" aria-expanded="' + !!v._open + '"><span class="wz-var-n">' + (i + 1) + '</span><span class="wz-var-t"><b data-live="' + base + '.name">' + esc(v.name || 'Bilet fără nume') + '</b><small>' + esc(subt) + '</small></span><span class="wz-var-p" data-live-price="' + i + '">' + (v.price != null ? esc(lei(v.price)) : '<span class="wz-ph">preț?</span>') + '</span>' + ic('caret-down') + '</button>';
    if (v._open) {
      h += '<div class="wz-var-b"><div class="wz-g2">' +
        field('Numele biletului', inp(base + '.name', { max: 120, ph: ((K.vt[t] || [])[0] || ['Adult'])[0] }), { req: true, for: idOf(base + '.name'), bad: bn }) +
        field('Prețul', money(base + '.price', { ph: '50' }), { req: true, for: idOf(base + '.price'), bad: bp, hint: 'Cât primești tu. Comisionul bilete.online se adaugă peste, la client.' }) + '</div>' +
        '<div class="wz-fl"><span class="wz-lbl">Cum se vinde</span>' + seg(base + '.price_type', [['per_person', 'Pe persoană'], ['per_unit', 'Pe unitate']]) +
        '<div class="wz-xp"><div class="' + (v.price_type !== 'per_unit' ? 'is-on' : '') + '"><b>Pe persoană</b><span class="wz-peeps"><i></i><i></i><i></i><i></i> = <em>×4</em></span>4 oameni cumpără 4 bilete.</div>' +
        '<div class="' + (v.price_type === 'per_unit' ? 'is-on' : '') + '"><b>Pe unitate</b><span class="wz-peeps"><i></i><i></i><i></i><i></i> = <em>×1</em></span>' + esc(K.perUnit) + '</div></div></div>' +
        (v.price_type === 'per_unit' ? field('Câte persoane încap', stepper(base + '.persons_max', { min: 1, max: 500, ph: 'oricâte', label: 'Persoane', u: 'pers.', start: 4 }), { hint: esc(K.personsHint) }) : '') +
        (t === 'access' ? toggle(base + '.is_child', 'E bilet de copil', 'Îl punem lângă biletul de adult și îl numărăm separat în rapoarte.') : '') +
        '<details class="wz-more" data-more="v' + i + '"' + (W.openMore['v' + i] ? ' open' : '') + '><summary>' + ic('caret-down') + 'Mai multe pentru acest bilet<small>limite, casă, returnare</small></summary><div class="wz-more-b"><div class="wz-g2">' +
        (day ? field('Valabil câte zile', stepper(base + '.validity_days', { min: 1, max: 60, label: 'Zile', u: 'zile', start: 1 }), { hint: 'Un abonament de 3 zile: 3.' }) : '') +
        (!day ? field('Durata acestui bilet', stepper(base + '.duration_minutes', { min: 5, max: 1440, step: 5, ph: 'ca la produs', label: 'Minute', u: 'min', start: p.duration_minutes || 60 }), { hint: 'Doar dacă diferă de durata produsului.' }) : '') +
        field('Preț la casă', money(base + '.pos_price', { ph: 'ca online' }), { opt: true, hint: 'Dacă la casa de bilete ceri altceva.' }) +
        field('Minim pe comandă', stepper(base + '.min_per_order', { min: 0, max: 500, label: 'Minim', start: 1 }), { hint: 'La un bilet de grup: 8.' }) +
        field('Maxim pe comandă', stepper(base + '.max_per_order', { min: 1, max: 500, label: 'Maxim', start: 20 })) +
        field('Se adaugă câte', stepper(base + '.step_qty', { min: 1, max: 100, ph: '1', label: 'Pas', start: 1 }), { hint: 'La bilete care se vând doar în perechi: 2.' }) +
        field('Însoțitor gratuit', inp(base + '.companion_label', { max: 80, ph: 'Însoțitor' }), { opt: true, hint: 'Un bilet gratuit în plus pe comandă, de pildă pentru profesorul unui grup.' }) +
        '</div>' +
        field('Descriere scurtă a biletului', inp(base + '.description', { max: 280, ph: v.is_child ? 'Pentru ce vârste sau înălțimi e' : 'Cine îl poate folosi' }), { opt: true }) +
        toggle(base + '.is_active', 'Se vinde', 'Oprește-l fără să-l ștergi; biletele deja vândute rămân valabile.') +
        toggle(base + '.is_refundable', 'Se poate returna', 'Clientul poate cere banii înapoi, după regulile de anulare.') +
        toggle(base + '.pos_only', 'Doar la casă', 'Nu apare online; îl vinzi doar de la casă (POS).') +
        '</div></details>' +
        (p.variants.length > 1 ? '<div class="wz-var-foot"><button type="button" class="ve-danger wz-del" data-act="vdel" data-i="' + i + '">' + ic('trash') + '<span>Scoate biletul</span></button></div>' : '') +
        '</div>';
    }
    return h + '</div>';
  }
  function stBilete() {
    var p = W.p, t = p.product_type, K = kit();
    var h = head('bilete', t === 'experience' ? 'Ce variante are?' : 'Ce bilete vinzi?',
      t === 'experience' ? 'Câte o variantă pentru fiecare fel de a cumpăra: de persoană, pentru un grup întreg sau pe durate diferite.' : 'Câte un bilet pentru fiecare fel de client: adult, copil, elev, grup. Toate apar împreună, clientul le pune în același coș.');
    var names = p.variants.map(function (v) { return fold(v.name); });
    var tpl = (K.vt[t] || []).filter(function (x) { return names.indexOf(fold(x[0])) < 0; });
    h += '<div class="wz-vars">' + p.variants.map(varCard).join('') + '</div>';
    if (p.variants.length < 30) {
      h += '<div class="wz-fl"><span class="wz-lbl">Adaugă rapid</span><div class="wz-chips">' + tpl.map(function (x) {
        return '<button type="button" class="wz-chip is-add" data-act="vadd" data-val="' + jv(x[0]) + '">' + ic('plus') + esc(x[0]) + '</button>';
      }).join('') + '<button type="button" class="wz-chip is-add" data-act="vadd" data-val="null">' + ic('plus') + 'Alt bilet</button></div></div>';
    }
    return h;
  }

  /* =================== step: when =================== */
  function schedWindow() {
    var p = W.p, l = locById(p.location_id);
    if (p.use_location_schedule && l && l.seasons && l.seasons.length) {
      var s = l.seasons[0];
      for (var i = 0; i < 7; i++) { var hh = s.schedule && s.schedule[DAYS[i][2]]; if (hh && hh.open && hh.close) return [hh.open, hh.close]; }
      return null;
    }
    for (var pi = 0; pi < p.periods.length; pi++) {
      for (var k = 1; k <= 7; k++) { var d = p.periods[pi].days[k]; if (d && d.on && d.slots[0] && d.slots[0].open && d.slots[0].close) return [d.slots[0].open, d.slots[0].close]; }
    }
    return null;
  }
  function slotList() {
    var p = W.p, w = schedWindow();
    if (!w || !p.slot_interval_minutes || !p.duration_minutes || p.slot_interval_minutes < 5) return [];
    var a = mins(w[0]), b = mins(w[1]), out = [];
    if (a == null || b == null) return [];
    for (var m = a; m + p.duration_minutes <= b && out.length < 100; m += p.slot_interval_minutes) out.push(hhmm(m));
    return out;
  }
  function whenSentence() {
    var p = W.p, w = schedWindow();
    if (p.booking_mode === 'day') {
      return '<span class="wz-k2">Ce vede clientul</span>Alege <b>ziua</b>; biletul e valabil ' + (w ? 'între <b>' + esc(w[0]) + '</b> și <b>' + esc(w[1]) + '</b>' : 'în programul zilei') + '. ' +
        (p.daily_capacity ? 'Se vând cel mult <b>' + esc(p.daily_capacity) + '</b> bilete pe zi.' : 'Nu e o limită de bilete pe zi.');
    }
    var sl = slotList();
    var cap = p.capacity_mode === 'concurrent'
      ? 'Poți avea <b>' + esc(p.capacity_per_slot || '?') + '</b> rezervări în același timp (' + esc(kit().unit.many) + '); o rezervare de la ' + esc(sl[0] || '10:00') + ' eliberează locul după <b>' + esc(durTxt(p.duration_minutes)) + '</b>.'
      : 'La fiecare oră de plecare sunt <b>' + esc(p.capacity_per_slot || '?') + '</b> locuri.';
    return '<span class="wz-k2">Ce vede clientul</span>Alege <b>ziua și ora</b>. Durează <b>' + esc(durTxt(p.duration_minutes) || '?') + '</b>. ' + cap +
      (sl.length ? '<div class="wz-slots">' + sl.slice(0, 14).map(function (s, i) { return '<span style="animation-delay:' + (i * 30) + 'ms">' + s + '</span>'; }).join('') + (sl.length > 14 ? '<span>+' + (sl.length - 14) + '</span>' : '') + '</div>'
        : (w ? '' : '<div class="wz-slots"><span>Orele apar după ce pui programul, mai jos.</span></div>'));
  }
  function mdSel(path) {
    var v = getP(path) || '01-01', mm = +v.split('-')[0], dd = +v.split('-')[1];
    var d = '<span class="wz-sel-w is-sm"><select class="wz-inp wz-sel" data-md="' + path + '" data-part="d" aria-label="Ziua">' + Array.from({ length: 31 }, function (_, i) { return '<option value="' + (i + 1) + '"' + (i + 1 === dd ? ' selected' : '') + '>' + (i + 1) + '</option>'; }).join('') + '</select>' + ic('caret-down') + '</span>';
    var m = '<span class="wz-sel-w is-sm"><select class="wz-inp wz-sel" data-md="' + path + '" data-part="m" aria-label="Luna">' + MONTHS.map(function (x, i) { return '<option value="' + (i + 1) + '"' + (i + 1 === mm ? ' selected' : '') + '>' + x + '</option>'; }).join('') + '</select>' + ic('caret-down') + '</span>';
    return d + m;
  }
  function periodHtml(per, pi) {
    var p = W.p, b = 'periods.' + pi;
    return '<div class="wz-period"><div class="wz-period-h"><b>' + (p.periods.length > 1 ? 'Perioada ' + (pi + 1) : 'Programul produsului') + '</b>' +
      (per.season ? '' : '<span class="wz-pill is-g">tot anul</span>') + '<span class="wz-sp"></span>' +
      (p.periods.length > 1 || p.booking_mode === 'day' ? '<button type="button" class="ve-danger wz-del" data-act="perdel" data-i="' + pi + '">' + ic('trash') + '<span>Șterge</span></button>' : '') + '</div>' +
      '<div class="wz-week">' + DAYS.map(function (x) {
        var d = per.days[x[0]];
        return '<div class="wz-day' + (d.on ? ' is-on' : '') + '"><button type="button" class="wz-day-t" data-act="day" data-pi="' + pi + '" data-d="' + x[0] + '" aria-pressed="' + d.on + '"><span class="wz-box">' + ic('check') + '</span>' + x[1] + '</button>' +
          (d.on ? '<div class="wz-day-hs">' + d.slots.map(function (s, si) {
            var sb = b + '.days.' + x[0] + '.slots.' + si;
            var badT = s.open && s.close && s.open >= s.close;
            return '<div class="wz-day-h' + (badT ? ' is-bad' : '') + '"><input class="wz-inp" type="time" data-bind="' + sb + '.open" data-t="text" value="' + esc(s.open) + '" aria-label="' + x[1] + ' de la"><span>–</span><input class="wz-inp" type="time" data-bind="' + sb + '.close" data-t="text" value="' + esc(s.close) + '" aria-label="' + x[1] + ' până la">' +
              (si > 0 ? '<button type="button" class="ve-icon-btn" data-act="slotdel" data-pi="' + pi + '" data-d="' + x[0] + '" data-si="' + si + '" aria-label="Scoate intervalul">' + ic('x') + '</button>' : '') + '</div>';
          }).join('') + (d.slots.length < 3 ? '<button type="button" class="wz-link is-sm" data-act="slotadd" data-pi="' + pi + '" data-d="' + x[0] + '">+ încă un interval (de pildă după pauza de prânz)</button>' : '') + '</div>'
            : '<span class="wz-closed">Închis</span>') + '</div>';
      }).join('') + '</div>' +
      '<div class="wz-chips"><button type="button" class="wz-chip is-ghost" data-act="copymon" data-i="' + pi + '">' + ic('copy') + 'Orele de luni pe toate zilele</button></div>' +
      (per.season
        ? '<div class="wz-fl"><span class="wz-lbl">Doar în sezon</span><div class="wz-season">de la ' + mdSel(b + '.season.start') + ' până la ' + mdSel(b + '.season.end') + '<button type="button" class="ve-danger wz-del" data-act="noseason" data-i="' + pi + '">' + ic('x') + '<span>Tot anul</span></button></div></div>'
        : '<button type="button" class="wz-link" data-act="season" data-i="' + pi + '">+ Doar într-un sezon (de pildă mai – septembrie)</button>') +
      '</div>';
  }
  function stCand() {
    var p = W.p, l = locById(p.location_id), hasSeasons = !!(l && l.seasons && l.seasons.length), K = kit();
    var h = head('cand', 'Când se poate folosi?', 'Alege cum rezervă clientul, apoi câte locuri ai. Vezi imediat ce va vedea el.');
    h += block('Cum rezervă clientul', null,
      '<div class="wz-cards is-2">' +
      '<button type="button" class="wz-card" data-act="mode" data-val="&quot;day&quot;" aria-pressed="' + (p.booking_mode === 'day') + '"><span class="wz-m-art"><svg viewBox="0 0 120 74" aria-hidden="true" focusable="false"><path d="M10 70a50 50 0 01100 0" fill="none" stroke="#C9CEC6" stroke-width="2" stroke-dasharray="4 5"/><g class="wz-sun"><circle cx="60" cy="24" r="9" fill="#F2A900"/></g><rect x="0" y="70" width="120" height="4" fill="#1B7F4E" opacity=".4"/></svg></span><b>Toată ziua</b><small>Alege doar ziua și vine oricând e deschis. Potrivit pentru intrări, abonamente, parcare.</small></button>' +
      '<button type="button" class="wz-card" data-act="mode" data-val="&quot;slot&quot;" aria-pressed="' + (p.booking_mode === 'slot') + '"><span class="wz-m-art"><svg viewBox="0 0 120 74" aria-hidden="true" focusable="false"><circle cx="60" cy="37" r="26" fill="#fff" stroke="#1B7F4E" stroke-width="3"/><g stroke="#C9CEC6" stroke-width="2"><path d="M60 15v5M60 54v5M38 37h5M77 37h5"/></g><path class="wz-hand" d="M60 37V20" stroke="#E43A33" stroke-width="3" stroke-linecap="round"/><path d="M60 37l9 6" stroke="#212121" stroke-width="3" stroke-linecap="round"/><circle cx="60" cy="37" r="3" fill="#212121"/></svg></span><b>La o oră fixă</b><small>Alege ziua și ora de început. Potrivit pentru tururi, ateliere, jocuri, închirieri.</small></button>' +
      '</div>');
    if (p.booking_mode === 'day') {
      h += block('Câte bilete pe zi', 'Gol dacă nu ai o limită. Când se termină, ziua apare „Epuizat” în calendar.',
        '<div class="wz-g2">' + field(null, stepper('daily_capacity', { min: 1, max: 1000000, step: 10, ph: 'fără limită', label: 'Locuri pe zi', start: 100 })) + '</div>', { opt: true });
    } else {
      h += block('Cât durează și cât de des pleacă', null,
        '<div class="wz-g2 is-keep">' +
        field('Durata', stepper('duration_minutes', { min: 5, max: 1440, step: 5, re: true, label: 'Durata', u: 'min', start: 60 }), { req: true, hint: esc(durTxt(p.duration_minutes)) }) +
        field('O plecare la fiecare', stepper('slot_interval_minutes', { min: 5, max: 1440, step: 5, re: true, label: 'Interval', u: 'min', start: 60 }), { req: true, hint: slotList().length ? 'Ore de plecare: ' + esc(slotList().slice(0, 3).join(', ')) + '…' : '' }) +
        '</div>');
      h += block('Cum se numără locurile', null,
        '<div class="wz-cards is-2">' +
        '<button type="button" class="wz-card" data-act="set" data-path="capacity_mode" data-val="&quot;per_slot&quot;" aria-pressed="' + (p.capacity_mode !== 'concurrent') + '"><span class="wz-hours" aria-hidden="true">' + [70, 90, 60, 100, 80].map(function (x, i) { return '<i style="height:' + x + '%;animation-delay:' + i * 80 + 'ms"></i>'; }).join('') + '</span><b>Locuri la fiecare oră</b><small>Un tur cu 20 de locuri la 10:00 și încă 20 la 12:00. Fiecare oră are locurile ei.</small></button>' +
        '<button type="button" class="wz-card" data-act="set" data-path="capacity_mode" data-val="&quot;concurrent&quot;" aria-pressed="' + (p.capacity_mode === 'concurrent') + '"><span class="wz-lanes" aria-hidden="true"><i></i><i></i><i></i></span><b>Unități în același timp</b><small>' + esc(K.unit.eg) + '</small></button>' +
        '</div>' +
        '<div class="wz-g2">' + field(p.capacity_mode === 'concurrent' ? 'Câte ' + esc(K.unit.many) + ' ai' : 'Locuri la fiecare oră', stepper('capacity_per_slot', { min: 1, max: 10000, label: 'Capacitate', u: p.capacity_mode === 'concurrent' ? 'buc.' : 'locuri', start: 10 }), { req: true }) + '</div>');
    }
    h += '<div class="wz-sentence" id="wz-when" aria-live="polite">' + whenSentence() + '</div>';

    var sch = '';
    if (hasSeasons) {
      sch += toggle('use_location_schedule', 'Folosește programul locației', 'Sezoanele și zilele închise ale locației „' + esc(l.name) + '”. Le schimbi o dată, la locație, și se aplică tuturor produselor care îl folosesc.');
      if (p.use_location_schedule) {
        sch += '<div class="wz-loc-sum">' + locSeasonsText(l).map(function (s) { return '<div><b>' + esc(s.label) + '</b> · ' + esc(s.rows.join(', ')) + '</div>'; }).join('') + '</div>';
      }
    } else if (l) {
      sch += '<p class="wz-hint">Locația „' + esc(l.name) + '” nu are încă program pe sezoane. Îl poți pune la locație, pentru toate produsele, sau aici, doar pentru acesta.</p>';
    }
    if (!p.use_location_schedule) {
      if (!p.periods.length) {
        sch += p.booking_mode === 'day'
          ? '<div class="wz-alert">' + ic('info') + '<span>Fără program propriu, biletul se poate folosi în orice zi.</span></div><button type="button" class="wz-add" data-act="peradd">' + ic('calendar-blank') + 'Pune zilele și orele în care se poate folosi</button>'
          : '<button type="button" class="wz-add" data-act="peradd">' + ic('calendar-blank') + 'Pune programul în care se pot rezerva orele</button>';
      } else {
        sch += p.periods.map(periodHtml).join('');
        sch += '<button type="button" class="wz-add" data-act="peradd">' + ic('plus') + 'Alt program pentru altă perioadă (iarna, vara)</button>';
      }
    }
    var schErr = tried('cand') ? problemsOf('cand', true).filter(function (x) { return /program|închidere/.test(x.msg); }) : [];
    h += block('Programul', p.booking_mode === 'slot' ? 'Orele între care pleacă rezervările, pe fiecare zi.' : 'Zilele și orele în care se poate folosi biletul.',
      sch + schErr.map(function (x) { return '<p class="wz-err">' + esc(x.msg) + '</p>'; }).join(''));

    h += block('Zile speciale', 'O zi închisă sau cu alt program decât de obicei, doar pentru acest produs.',
      p.exceptions.map(function (x, i) {
        var b = 'exceptions.' + i;
        return '<div class="wz-row"><div class="wz-row-h"><b>' + (x.date ? esc(x.date.split('-').reverse().join('.')) : 'Zi nouă') + '</b><button type="button" class="ve-icon-btn" data-act="exdel" data-i="' + i + '" aria-label="Șterge ziua specială">' + ic('trash') + '</button></div>' +
          '<div class="wz-g2">' + field('Data', '<input class="wz-inp" type="date" id="' + idOf(b + '.date') + '" data-bind="' + b + '.date" data-t="text" data-re="1" value="' + esc(x.date) + '">', { for: idOf(b + '.date') }) + field('Motivul', inp(b + '.reason', { max: 190, ph: 'Sărbătoare' }), { opt: true }) + '</div>' +
          seg(b + '.is_closed', [[true, 'Închis'], [false, 'Alt program']]) +
          (!x.is_closed ? '<div class="wz-day-h"><input class="wz-inp" type="time" data-bind="' + b + '.open" data-t="text" value="' + esc(x.open) + '" aria-label="De la"><span>–</span><input class="wz-inp" type="time" data-bind="' + b + '.close" data-t="text" value="' + esc(x.close) + '" aria-label="Până la"></div>' : '') + '</div>';
      }).join('') + (p.exceptions.length < 200 ? '<button type="button" class="wz-add" data-act="exadd">' + ic('calendar-blank') + 'Adaugă o zi specială</button>' : ''), { opt: true });

    h += block('Când se vinde', null, '<div class="wz-g2">' +
      field('Vânzarea online se oprește cu', stepper('booking_lead_time_hours', { min: 0, max: 720, label: 'Ore înainte', u: 'ore înainte', start: 0 }), { hint: '0 = se poate cumpăra până în ultimul moment.' }) +
      field('Se poate rezerva cu cel mult', stepper('booking_max_advance_days', { min: 1, max: 365, step: 7, ph: 'ca la locație', label: 'Zile', u: 'zile înainte', start: 30 }), { hint: 'Gol = cât permite locația.' }) + '</div>', { opt: true });
    return h;
  }

  /* =================== step: add-ons =================== */
  function stExtra() {
    var p = W.p;
    var h = head('extra', 'Vrei să oferi ceva în plus?', 'Suplimentele se aleg la fiecare bilet: o poză, un locker, timp în plus. Poți sări peste pas; nu e obligatoriu.');
    h += p.addons.map(function (a, i) {
      var b = 'addons.' + i, mx = a.max_per_unit == null ? 1 : a.max_per_unit, tot = (a.included_qty || 0) + mx;
      return '<div class="wz-row is-card"><div class="wz-row-h"><b>' + esc(a.name || 'Supliment nou') + '</b><button type="button" class="ve-icon-btn" data-act="addel" data-i="' + i + '" aria-label="Șterge suplimentul">' + ic('trash') + '</button></div>' +
        '<div class="wz-g2">' + field('Numele', inp(b + '.name', { max: 120, ph: kit().addons[0] }), { req: true }) + field('Prețul', money(b + '.price', { ph: '0' })) +
        field('Incluse gratuit la un bilet', stepper(b + '.included_qty', { min: 0, max: 50, re: true, label: 'Incluse', start: 0 })) +
        field('Maxim plătite la un bilet', stepper(b + '.max_per_unit', { min: 0, max: 50, re: true, label: 'Maxim plătite', start: 1 })) + '</div>' +
        '<div class="wz-sentence is-sm"><span class="wz-k2">La fiecare bilet</span>' + (a.included_qty ? '<b>' + a.included_qty + '</b> ' + (a.included_qty > 1 ? 'gratuite' : 'gratuit') + ' + ' : '') + 'până la <b>' + mx + '</b> plătite' + (a.price ? ' (câte ' + esc(lei(a.price)) + ')' : '') + ' = cel mult <b>' + tot + '</b>. Pagina îi scrie clientului limita.</div>' +
        toggle(b + '.is_active', 'Se vinde', null) + '</div>';
    }).join('');
    if (p.addons.length < 20) {
      var left = kit().addons.filter(function (s) { return !p.addons.some(function (a) { return a.name === s; }); });
      h += '<div class="wz-fl"><span class="wz-lbl">Adaugă un supliment</span><div class="wz-chips">' + left.map(function (s) { return '<button type="button" class="wz-chip is-add" data-act="adadd" data-val="' + jv(s) + '">' + ic('plus') + esc(s) + '</button>'; }).join('') +
        '<button type="button" class="wz-chip is-add" data-act="adadd" data-val="null">' + ic('plus') + 'Altul</button></div></div>';
    }
    if (!p.addons.length) h += '<p class="wz-hint">Fără suplimente, pagina arată doar biletele. Poți adăuga oricând mai târziu.</p>';
    return h;
  }

  /* =================== package: contents and price =================== */
  function variantOf(it) {
    var d = details[it.product_id];
    return d ? (d.variants || []).filter(function (x) { return x.id === it.variant_id; })[0] || null : null;
  }
  function pkgTotals() {
    var p = W.p, total = 0, known = p.package_items.length > 0;
    p.package_items.forEach(function (it) {
      var v = variantOf(it);
      if (!v) { known = false; return; }
      total += (v.price || 0) * (it.quantity || 1);
    });
    return { total: Math.round(total * 100) / 100, known: known };
  }
  function componentChoices() {
    var p = W.p;
    return products.filter(function (x) { return x.type !== 'package' && x.id !== p.id && (!p.location_id || !x.location_id || x.location_id === p.location_id); });
  }
  function stContinut() {
    var p = W.p;
    var h = head('continut', 'Ce pui în pachet?', 'Alege din produsele pe care le ai deja. La cumpărare, pachetul emite câte un bilet pentru fiecare parte; experiențele cu oră își aleg ora la rezervare.');
    h += '<div class="wz-pk-items">' + p.package_items.map(function (it, i) {
      var pr = products.filter(function (x) { return x.id === it.product_id; })[0], d = details[it.product_id], b = 'package_items.' + i;
      var title = (pr && pr.title) || it._title || 'Produsul ' + it.product_id;
      var vs = d ? (d.variants || []).filter(function (v) { return v.is_active || v.id === it.variant_id; }) : null;
      return '<div class="wz-pk"><span class="wz-pk-ic">' + pic(pr ? TYPE_ICON[pr.type] : 'ticket') + '</span><div class="wz-pk-b"><div class="wz-pk-top"><div><b>' + esc(title) + '</b><small>' + esc(pr ? TYPES[pr.type] : '') + '</small></div><button type="button" class="ve-icon-btn" data-act="pkdel" data-i="' + i + '" aria-label="Scoate din pachet">' + ic('x') + '</button></div>' +
        '<div class="wz-pk-row">' + (vs ? field('Biletul', sel(b + '.variant_id', [[null, 'Alege biletul']].concat(vs.map(function (v) { return [v.id, (v.name || 'Bilet') + ' · ' + lei(v.price)]; })), { re: true }), { bad: tried('continut') && !it.variant_id }) : '<p class="wz-hint">Se încarcă biletele…</p>') +
        field('Câte', stepper(b + '.quantity', { min: 1, max: 50, re: true, label: 'Cantitate', start: 1 })) + '</div></div></div>';
    }).join('') + '</div>';
    var avail = componentChoices();
    h += block('Produsele tale', avail.length ? (p.package_items.length ? 'Poți pune același produs de mai multe ori, cu bilete diferite (de pildă 2 adulți + 2 copii).' : 'Apasă pe un produs ca să-l pui în pachet.') : 'Nu ai încă bilete de acces sau experiențe' + (p.location_id ? ' la această locație' : '') + '. Adaugă întâi unul, apoi fă pachetul.',
      '<div class="wz-shelf">' + avail.map(function (x) {
        return '<button type="button" data-act="pkadd" data-val="' + jv(x.id) + '"' + (p.package_items.length >= 20 ? ' disabled' : '') + '><span class="wz-shelf-ic">' + pic(TYPE_ICON[x.type]) + '</span><b>' + esc(x.title || 'Produs') + '</b><small>' + (x.min_price != null ? 'de la ' + esc(lei(x.min_price)) : '') + '</small>' + ic('plus') + '</button>';
      }).join('') + '</div>');
    var t = pkgTotals();
    if (t.known) h += '<div class="wz-sentence"><span class="wz-k2">Separat, clientul ar plăti</span><b class="is-big">' + esc(lei(t.total)) + '</b>. La pasul următor pui prețul pachetului.</div>';
    return h;
  }
  function stPret() {
    var p = W.p, v = p.variants[0], t = pkgTotals(), price = v.price, save = t.known && price != null ? t.total - price : null;
    var h = head('pret', 'Cât costă pachetul?', 'Un singur preț pentru tot. Un pachet bun e puțin mai ieftin decât biletele luate separat.');
    h += block(null, null, field('Prețul pachetului', money('variants.0.price', { ph: t.known ? String(Math.round(t.total * 0.9)) : '0', big: true }), { req: true, for: 'wz-variants-0-price', bad: tried('pret') && price == null, hint: 'Cât primești tu. Comisionul bilete.online se adaugă peste, la client.' }) +
      (t.known ? '<div class="wz-sugg"><span>Reducere rapidă:</span>' + [5, 10, 15, 20].map(function (pc) { var val = Math.round(t.total * (1 - pc / 100)); return '<button type="button" data-act="set" data-path="variants.0.price" data-val="' + val + '">−' + pc + '% · ' + esc(lei(val)) + '</button>'; }).join('') + '</div>' : ''));
    if (t.known) {
      var mx = Math.max(t.total, price || 0) || 1;
      h += '<div class="wz-save" aria-live="polite"><span class="wz-k2">' + (save != null && save < 0 ? 'Atenție' : 'Clientul economisește') + '</span>' +
        '<span class="wz-big' + (save != null && save < 0 ? ' is-bad' : '') + '" id="wz-save-big">' + (save == null ? '—' : esc(save < 0 ? 'Mai scump cu ' + lei(-save) : lei(save))) + '</span>' +
        '<div class="wz-bars"><div>Separat<span><i style="width:' + (t.total / mx * 100) + '%"></i></span><b>' + esc(lei(t.total)) + '</b></div><div>Cu pachetul<span><i id="wz-bar-pk" style="width:' + ((price || 0) / mx * 100) + '%"></i></span><b id="wz-bar-pk-t">' + (price != null ? esc(lei(price)) : '—') + '</b></div></div></div>';
      h += block('Cum se împarte încasarea', 'Pentru rapoarte și deconturi: cât din preț revine fiecărei părți. Gol = împărțim automat, proporțional cu prețurile separate.',
        '<div class="wz-alloc">' + p.package_items.map(function (it, i) {
          var pr = products.filter(function (x) { return x.id === it.product_id; })[0], vv = variantOf(it);
          var auto = vv && price != null && t.total ? Math.round(price * (vv.price * (it.quantity || 1)) / t.total * 100) / 100 : null;
          return '<div class="wz-alloc-r"><div><b>' + esc((pr && pr.title) || it._title || '') + '</b><small>' + esc(vv ? vv.name : '') + ' × ' + (it.quantity || 1) + '</small></div>' + money('package_items.' + i + '.allocated_price', { ph: auto != null ? String(auto).replace('.', ',') + ' (auto)' : 'automat' }) + '</div>';
        }).join('') + '</div>', { opt: true });
    }
    h += '<details class="wz-more" data-more="pk"' + (W.openMore.pk ? ' open' : '') + '><summary>' + ic('caret-down') + 'Mai multe pentru pachet<small>limite, casă, returnare</small></summary><div class="wz-more-b"><div class="wz-g2">' +
      field('Minim pe comandă', stepper('variants.0.min_per_order', { min: 0, max: 500, label: 'Minim', start: 1 })) +
      field('Maxim pe comandă', stepper('variants.0.max_per_order', { min: 1, max: 500, label: 'Maxim', start: 20 })) +
      field('Preț la casă', money('variants.0.pos_price', { ph: 'ca online' }), { opt: true }) +
      field('Însoțitor gratuit', inp('variants.0.companion_label', { max: 80, ph: 'Însoțitor' }), { opt: true }) + '</div>' +
      toggle('variants.0.is_active', 'Se vinde', 'Oprește pachetul fără să-l ștergi.') + toggle('variants.0.is_refundable', 'Se poate returna', null) + toggle('variants.0.pos_only', 'Doar la casă', null) + '</div></details>';
    return h;
  }

  /* =================== step: what to know =================== */
  function langBlock(p, K) {
    var chips = '<div class="wz-chips">' + LANGS.map(function (l) {
      return '<button type="button" class="wz-chip" data-act="lang" data-val="' + jv(l[0]) + '" aria-pressed="' + (p.languages.indexOf(l[0]) >= 0) + '">' + l[1] + '</button>';
    }).join('') + '</div>';
    // Languages matter when someone speaks to the client (a tour, a workshop, a game master).
    var talks = K.langs || p.service_type === 'guided' || p.service_type === 'workshop' || p.languages.length;
    if (talks) return '<div class="wz-fl"><span class="wz-lbl">În ce limbi se vorbește <small>opțional</small></span>' + chips + '</div>';
    return '<details class="wz-more" data-more="lang"' + (W.openMore.lang ? ' open' : '') + '><summary>' + ic('caret-down') + 'În ce limbi se vorbește<small>doar dacă e cineva care explică</small></summary><div class="wz-more-b">' + chips + '</div></details>';
  }
  function stInfo() {
    var p = W.p, t = p.product_type, v = p.variants[0], K = kit();
    var h = head('info', 'Ce trebuie să știe clientul?', 'Răspunde acum la întrebările pe care altfel le primești la telefon. Tot ce e aici apare pe pagina produsului.');
    var unitEx = (v && v.price != null ? lei(v.price) : '50 lei') + ' / ' + (p.unit_label || K.ph.unit[t]);
    h += block('Prețul, pe înțeles', null, field('Unitatea de preț', inp('unit_label', { max: 60, ph: K.ph.unit[t] }), { opt: true, hint: 'Apare după preț. Acum: <b id="wz-unit-ex">' + esc(unitEx) + '</b>' }));
    h += block('Ce include', 'Scrie ce e inclus la tine. Sugestiile țin de categoria aleasă; ignoră-le dacă nu se potrivesc.', field(null, tags('included_items', K.incl[t] || [], 'Scrie și apasă Enter')) +
      (t === 'experience' ? field('Ce nu include', tags('not_included', K.notIncl, 'Scrie și apasă Enter'), { opt: true }) +
        field('De știut înainte', tags('requirements', K.req, 'Scrie și apasă Enter'), { opt: true }) : ''));
    if (t === 'experience') {
      var ageBad = p.age_min != null && p.age_max != null && p.age_min > p.age_max;
      h += block('Pentru cine e', null,
        '<div class="wz-g2 is-keep">' + field('Vârsta minimă', stepper('age_min', { min: 0, max: 99, ph: 'oricare', label: 'Vârsta minimă', u: 'ani', start: 6 }), { bad: ageBad }) + field('Vârsta maximă', stepper('age_max', { min: 0, max: 99, ph: 'oricare', label: 'Vârsta maximă', u: 'ani', start: 70 }), { bad: ageBad }) + '</div>' +
        (ageBad ? '<p class="wz-err">Vârsta minimă e peste cea maximă.</p>' : '') +
        field('Punctul de întâlnire', inp('meeting_point', { max: 500, ph: K.ph.meet }), { opt: true, hint: 'Unde vine clientul. Apare pe bilet.' }) + langBlock(p, K));
    }
    h += block('Reguli', null,
      '<div class="wz-fl"><span class="wz-lbl">Anulare <small>opțional</small></span><div class="wz-chips">' + CANCEL.map(function (c) {
        return '<button type="button" class="wz-chip" data-act="set" data-path="cancellation_policy" data-val="' + jv(c[0]) + '" aria-pressed="' + (p.cancellation_policy === c[0]) + '">' + c[1] + '</button>';
      }).join('') + '</div>' + ta('cancellation_policy', { max: 2000, rows: 2, ph: 'Alege mai sus sau scrie regula ta.' }) + '</div>' +
      field('Condiții de folosire', ta('usage_terms', { max: 2000, rows: 2, ph: 'Biletul se arată la intrare, de pe telefon.' }), { opt: true }) +
      (K.terms.filter(function (s) { return (p.usage_terms || '').indexOf(s) < 0; }).length ? '<div class="wz-sugg"><span>Adaugă:</span>' + K.terms.filter(function (s) { return (p.usage_terms || '').indexOf(s) < 0; }).map(function (s) { return '<button type="button" data-act="terms" data-val="' + jv(s) + '">+ ' + esc(s) + '</button>'; }).join('') + '</div>' : ''));
    return h;
  }

  /* =================== step: pictures =================== */
  function stPoze() {
    var p = W.p, t = p.product_type, l = locById(p.location_id), lcov = l && l.cover_image;
    var h = head('poze', 'Arată-le cum e acolo', lcov ? 'Fără poză proprie, produsul folosește poza locației „' + esc(l.name) + '”. Dar o poză a produsului vinde mai bine.' : 'O poză e obligatorie la trimiterea spre aprobare' + (l ? ', pentru că locația nu are nici ea' : '') + '.');
    h += block('Poza principală', 'JPG, PNG sau WebP, cel mult 10 MB. Recomandat 1200 × 900 sau mai mare, pe orizontală.', mount('cover') +
      (tried('poze') && !hasPhoto() ? '<p class="wz-err">Adaugă o poză ca să poți trimite produsul spre aprobare.</p>' : '') +
      '<div class="wz-ph-tips"><div><b>Oameni în cadru</b>Clienții se văd pe ei acolo.</div><div><b>Lumină de zi</b>Fără filtre puternice.</div><div><b>Fără text pe poză</b>Titlul îl punem noi.</div></div>', lcov ? { opt: true } : null);
    if (t === 'experience') h += block('Galeria', 'Până la 20 de poze pe pagina experienței.', mount('gallery'), { opt: true });
    return h;
  }

  /* =================== step: last settings =================== */
  function stSetari() {
    var p = W.p, t = p.product_type;
    var h = head('setari', 'Ultimele setări', 'Majoritatea produselor merg bine cu ce e deja ales aici. Verifică doar dacă ceva se aplică la tine.');
    if (t === 'experience') {
      h += block('Cere și bilet de intrare?', 'Dacă experiența are loc în interiorul locației, clientul are nevoie și de acces. Pe pagina experienței, biletele de acces apar lângă ea.',
        '<div class="wz-cards">' + [['none', 'x', 'Nu', 'Experiența se cumpără singură.'], ['any', 'ticket', 'Da, câte unul pentru fiecare', 'Fiecare participant are nevoie de bilet de acces în aceeași zi.'], ['adult', 'ticket', 'Da, un bilet de adult', 'De pildă unul pe grup: cine cumpără pentru grup are nevoie de acces.']].map(function (o) {
          return choice('set', o[0], p.access_requirement === o[0], o[1] === 'x' ? ic('x') : pic('ticket'), o[2], o[3], { path: 'access_requirement' });
        }).join('') + '</div>');
    }
    h += block('Vânzarea', null,
      toggle('pos_only', 'Doar la casă', 'Nu se vinde online, doar de la casă (POS). Bun pentru bilete de protocol sau reduceri locale.') +
      toggle('requires_vehicle_info', 'Cere numărul de înmatriculare', 'Clientul îl scrie la cumpărare; apare pe bilet și în lista de la intrare.' + (p.access_kind === 'vehicle' && t === 'access' ? ' <b>Recomandat pentru parcare.</b>' : '')));
    if (meta && meta.has_secondary_issuer) {
      h += block('Firma care emite biletul', 'Ai două firme în cont. Alege pe care se emit biletele și documentele fiscale pentru acest produs.',
        seg('issuing_company', [['primary', 'Firma principală'], ['secondary', 'A doua firmă']]));
    }
    return h;
  }

  /* =================== step: check and send =================== */
  var STATUS = {
    draft: ['is-muted', 'file-text', 'Ciornă — doar tu o vezi', 'Completează ce lipsește și trimite-o spre aprobare: echipa bilete.online se uită peste ea și, dacă e în regulă, apare pe site.'],
    pending: ['is-wait', 'hourglass', 'Trimis spre aprobare', 'Nu mai trebuie să faci nimic. Te anunțăm pe e-mail când primește răspuns. Până atunci poți modifica în continuare; verificăm ultima variantă.'],
    rejected: ['is-bad', 'warning-circle', 'Respins', 'Corectează ce scrie mai jos și trimite din nou. Nu se pierde nimic din ce ai scris.'],
    approvedOn: ['is-ok', 'check-circle', 'Aprobat și pe site', 'Modificările pe care le salvezi de acum apar direct pe site, fără o nouă aprobare.'],
    approvedOff: ['is-muted', 'eye-slash', 'Aprobat, dar ascuns de pe site', 'Nu îl vede nimeni până nu apeși „Pune pe site”. Modificările apar direct, fără o nouă aprobare.'],
  };
  function statusKey(p) {
    if (!p.id || p.review_status === 'draft') return 'draft';
    if (p.review_status === 'pending' || p.review_status === 'rejected') return p.review_status;
    return p.is_published ? 'approvedOn' : 'approvedOff';
  }
  function sumCard(step, title, rows) {
    rows = rows.filter(function (r) { return r[1] !== '' && r[1] != null; });
    var warn = problemsOf(step).length;
    return '<div class="wz-sum-c' + (warn ? ' is-warn' : '') + '"><div class="wz-sum-h"><b>' + title + '</b>' + (warn ? '<span class="wz-pill is-y">de completat</span>' : '') + '<button type="button" class="wz-link-btn" data-act="edit" data-val="' + jv(step) + '">' + ic('pencil-simple') + 'Modifică</button></div>' +
      (rows.length ? '<dl>' + rows.map(function (r) { return '<dt>' + r[0] + '</dt><dd>' + r[1] + '</dd>'; }).join('') + '</dl>' : '<p class="wz-hint">Nimic completat încă.</p>') + '</div>';
  }
  function stGata() {
    var p = W.p, t = p.product_type, l = locById(p.location_id), c = catById(p.category_id), sc = catById(p.subcategory_id);
    var probs = problems(), draftish = isDraft();
    var st = STATUS[statusKey(p)];
    var h = head('gata', draftish ? (probs.length ? 'Aproape gata' : 'Totul arată bine') : 'Rezumatul produsului',
      draftish ? (probs.length ? 'Mai sunt ' + probs.length + ' lucruri de completat înainte de trimitere. Apasă pe oricare ca să mergi direct acolo.' : 'Verifică rezumatul. Poți modifica orice secțiune; te aducem înapoi aici după.')
        : 'Apasă „Modifică” pe orice secțiune. ' + (p.review_status === 'pending' ? 'Salvează când termini.' : 'Salvează când termini; modificările apar direct pe site.'));
    if (p.id) {
      h += '<div class="wz-state ' + st[0] + '" role="status"><span class="wz-state-ic">' + ic(st[1]) + '</span><div><b>' + st[2] + '</b><p>' + st[3] + '</p>' +
        (p.review_status === 'rejected' && p.rejection_reason ? '<p class="wz-state-why"><b>Motivul:</b> ' + esc(p.rejection_reason) + '</p>' : '') +
        (p.review_status === 'pending' && p.submitted_at ? '<p class="wz-hint">Trimis pe ' + esc(A.when(p.submitted_at)) + '</p>' : '') + '</div></div>';
    }
    var cks = [];
    cks.push([!!p.title, 'Titlul', 'nume']);
    if (t !== 'experience') cks.push([!!p.location_id, 'Locația', 'unde']);
    if (needsCategory()) cks.push([!!p.category_id, 'Categoria de pe bilete.online', 'unde']);
    if (t === 'package') {
      cks.push([p.package_items.length > 0 && !problemsOf('continut').length, 'Conținutul pachetului, cu biletul ales pe fiecare rând', 'continut']);
      cks.push([!problemsOf('pret').length, 'Prețul pachetului', 'pret']);
    } else {
      cks.push([!problemsOf('bilete').length, 'Cel puțin un bilet de vânzare, cu nume și preț', 'bilete']);
      if (p.booking_mode === 'slot') cks.push([!problemsOf('cand').length, 'Programul în care se pot rezerva orele', 'cand']);
      else if (problemsOf('cand').length) cks.push([false, 'Programul', 'cand']);
    }
    cks.push([hasPhoto(), 'O poză' + (l && l.cover_image && !p.cover_image ? ' (folosim poza locației)' : ''), 'poze']);
    if (draftish) {
      h += block('Înainte de trimitere', null, '<div class="wz-checks">' + cks.map(function (x) {
        return '<div class="wz-ck ' + (x[0] ? 'is-ok' : 'is-no') + '"><span class="wz-dot">' + (x[0] ? ic('check') : '!') + '</span><div>' + x[1] + '</div>' + (x[0] ? '' : '<button type="button" class="wz-link-btn" data-act="edit" data-val="' + jv(x[2]) + '">Completează</button>') + '</div>';
      }).join('') + '</div>');
    } else if (probs.filter(function (x) { return x.save; }).length) {
      h += '<div class="wz-alert is-warn">' + ic('warning-circle') + '<span>Nu se poate salva încă: ' + esc(probs.filter(function (x) { return x.save; }).map(function (x) { return x.msg.toLowerCase(); }).join('; ')) + '.</span></div>';
    }
    var sums = [];
    var kindTxt = t === 'access' ? { person: 'Persoane', vehicle: 'Vehicul', camping: 'Camping', other: 'Altceva' }[p.access_kind] : t === 'experience' ? { rental: 'Închiriere', guided: 'Tur ghidat', workshop: 'Atelier', other: 'Altceva' }[p.service_type] : '';
    sums.push(sumCard('tip', 'Ce vinzi', [['Tipul', esc(TYPES[t])], ['Felul', esc(kindTxt || '')]]));
    var grp = l && p.display_category ? (l.display_categories || []).filter(function (g) { return g.id === p.display_category; })[0] : null;
    sums.push(sumCard('unde', 'Unde', [['Locația', l ? esc(l.name) : (t === 'experience' ? 'Fără locație' : '<span class="wz-ph">lipsește</span>')], ['Categoria', c ? esc(c.name) + (sc ? ' › ' + esc(sc.name) : '') : (needsCategory() ? '<span class="wz-ph">lipsește</span>' : '')], ['Grupa', grp ? esc(grp.name) : '']]));
    sums.push(sumCard('nume', 'Nume și descriere', [['Titlul', p.title ? (p.icon ? pic(p.icon) : '') + esc(p.title) : '<span class="wz-ph">lipsește</span>'], ['Subtitlu', esc(p.subtitle || '')], ['Pe scurt', esc(p.short_description || '')], ['Descrierea', p.description ? 'scrisă' : '']]));
    if (t === 'package') {
      sums.push(sumCard('continut', 'Ce conține', p.package_items.map(function (it) {
        var pr = products.filter(function (x) { return x.id === it.product_id; })[0], v = variantOf(it);
        return [(it.quantity || 1) + ' ×', esc((pr && pr.title) || it._title || '') + (v ? ' · ' + esc(v.name) : (it._vname ? ' · ' + esc(it._vname) : ' · <span class="wz-ph">bilet neales</span>'))];
      })));
      var tt = pkgTotals();
      sums.push(sumCard('pret', 'Prețul', [['Pachetul', p.variants[0].price != null ? esc(lei(p.variants[0].price)) : '<span class="wz-ph">lipsește</span>'], ['Separat', tt.known ? esc(lei(tt.total)) : '']]));
    } else {
      sums.push(sumCard('bilete', 'Bilete și prețuri', p.variants.map(function (v) { return [esc(v.name || 'Fără nume'), (v.price != null ? esc(lei(v.price)) : '<span class="wz-ph">fără preț</span>') + ' / ' + (v.price_type === 'per_unit' ? 'unitate' : 'persoană') + (v.is_active ? '' : ' · nu se vinde')]; })));
      var hasLoc = p.use_location_schedule && l && l.seasons && l.seasons.length;
      sums.push(sumCard('cand', 'Când', [['Rezervare', p.booking_mode === 'day' ? 'Toată ziua' : 'La oră fixă, ' + esc(durTxt(p.duration_minutes)) + ', o plecare la ' + esc(p.slot_interval_minutes) + ' min'],
        ['Locuri', p.booking_mode === 'day' ? (p.daily_capacity ? esc(p.daily_capacity) + ' pe zi' : 'fără limită') : esc(p.capacity_per_slot) + (p.capacity_mode === 'concurrent' ? ' ' + esc(kit().unit.many) + ' în același timp' : ' la fiecare oră')],
        ['Program', hasLoc ? 'ca la locație' : (p.periods.length ? 'propriu, ' + p.periods.length + (p.periods.length === 1 ? ' perioadă' : ' perioade') : (p.booking_mode === 'day' ? 'în orice zi' : '<span class="wz-ph">lipsește</span>'))],
        ['Zile speciale', p.exceptions.length ? String(p.exceptions.length) : '']]));
      sums.push(sumCard('extra', 'Suplimente', p.addons.map(function (a) { return [esc(a.name || 'Fără nume'), esc(lei(a.price))]; })));
    }
    sums.push(sumCard('info', 'De știut', [['Include', esc(p.included_items.join(', '))], ['Anulare', esc(p.cancellation_policy || '')], ['Condiții', esc(p.usage_terms || '')],
      ['Limbi', esc(p.languages.map(function (k) { return (LANGS.filter(function (x) { return x[0] === k; })[0] || [k, k])[1]; }).join(', '))]]));
    sums.push(sumCard('poze', 'Poze', [['Poza principală', p.cover_image ? 'aleasă' : (l && l.cover_image ? 'a locației' : '<span class="wz-ph">lipsește</span>')], ['Galeria', p.gallery.length ? p.gallery.length + ' poze' : '']]));
    sums.push(sumCard('setari', 'Setări finale', [['Vânzare', p.pos_only ? 'doar la casă' : 'online și la casă'], ['Nr. înmatriculare', p.requires_vehicle_info ? 'se cere' : ''], ['Firma', meta && meta.has_secondary_issuer ? (p.issuing_company === 'secondary' ? 'a doua firmă' : 'principală') : '']]));
    h += '<div class="wz-sum">' + sums.join('') + '</div>';
    if (draftish) {
      h += block('Ce urmează', null, '<div class="wz-flow"><div class="is-on"><i>' + ic('file-text') + '</i><b>Ciornă</b>Doar tu o vezi</div><div' + (p.review_status === 'pending' ? ' class="is-on"' : '') + '><i>' + ic('eye') + '</i><b>Verificare</b>Echipa bilete.online se uită peste ea</div><div><i>' + ic('check') + '</i><b>Pe site</b>Clienții o pot cumpăra</div></div>' +
        '<p class="wz-hint">Doar prima publicare așteaptă aprobarea. După aceea, modificările tale apar imediat pe site.</p>');
    }
    if (p.id) {
      var canPub = p.review_status === 'approved' || !p.review_status;
      h += block('Alte acțiuni', null, '<div class="wz-actions">' +
        (canPub ? '<button type="button" class="btn btn-ghost" data-act="publish">' + ic(p.is_published ? 'eye-slash' : 'eye') + '<span>' + (p.is_published ? 'Ascunde de pe site' : 'Pune pe site') + '</span></button>' : '') +
        (canPub && p.is_published && p.public_path ? '<a class="btn btn-ghost" href="' + esc(p.public_path) + '" target="_blank" rel="noopener">' + ic('arrow-up-right') + '<span>Vezi pe site</span></a>' : '') +
        '<button type="button" class="btn btn-ghost" data-act="dup">' + ic('copy') + '<span>Copiază produsul</span></button>' +
        '<button type="button" class="ve-danger wz-del" data-act="delete">' + ic('trash') + '<span>' + (W.armDelete ? 'Apasă din nou ca să ștergi' : 'Șterge produsul') + '</span></button>' +
        '</div><p class="wz-hint">Ștergerea merge doar pentru un produs fără vânzări și care nu face parte dintr-un pachet. Altfel, ascunde-l de pe site.</p>');
    }
    return h;
  }

  var RENDER = { tip: stTip, unde: stUnde, nume: stNume, bilete: stBilete, continut: stContinut, pret: stPret, cand: stCand, extra: stExtra, info: stInfo, poze: stPoze, setari: stSetari, gata: stGata };
  var TIPS = {
    tip: ['Nu știi ce să alegi?', 'Dacă clientul „intră”, e bilet de acces. Dacă „face ceva” acolo, e experiență. Dacă vrei să le vinzi împreună, mai ieftin, e pachet.'],
    unde: ['Categoria contează mult', 'Clienții care nu te cunosc ajung la tine din listele pe categorii și din paginile de oraș. Alege categoria în care ai căuta tu.'],
    nume: ['Titluri care vând', 'Scurt și concret: spune ce primește clientul, nu cât de frumos va fi. Păstrează emoțiile pentru descriere.'],
    bilete: ['Prețul tău, fără surprize', 'Scrii suma pe care o primești. Comisionul bilete.online se adaugă peste, la client, așa că nu pierzi nimic din preț.'],
    continut: ['Un pachet clar', 'Un pachet se înțelege dintr-o privire: intrarea și o experiență, pentru un număr clar de persoane.'],
    pret: ['Cât de mare să fie reducerea?', 'O reducere de 10–15% față de prețul separat face de obicei pachetul să pară o afacere bună.'],
    cand: ['Ai lucruri care se închiriază?', 'Karturi, biciclete, camere, bărci: alege „Unități în același timp”. Numărăm câte sunt folosite în fiecare minut, nu câte au pornit la o anumită oră.'],
    extra: ['Suplimentele cresc coșul', 'Un pachet foto sau timp în plus, oferite la momentul potrivit, sunt alese des. Nu pune mai mult de 3–4.'],
    info: ['Mai puține telefoane', 'Tot ce scrii aici e o întrebare la care nu mai răspunzi la telefon: ce e inclus, pentru ce vârste, ce să aducă.'],
    poze: ['Poza e primul lucru văzut', 'În liste, clienții se uită întâi la poză, apoi la preț, abia apoi la titlu.'],
    setari: ['Poți lăsa totul așa', 'Setările de aici sunt pentru cazuri speciale. Dacă nu te regăsești în ele, mergi mai departe.'],
    gata: ['Nu e nimic definitiv', 'După aprobare poți schimba orice: prețuri, program, poze. Modificările apar imediat.'],
  };

  /* =================== preview =================== */
  function preview() {
    var p = W.p, t = p.product_type, l = locById(p.location_id);
    var vars = p.variants.filter(function (v) { return v.is_active && !v.pos_only; });
    var prices = vars.map(function (v) { return v.price; }).filter(function (x) { return x != null; });
    var min = prices.length ? Math.min.apply(null, prices) : null;
    var K = kit();
    var unit = p.unit_label || (t === 'experience' && vars[0] && vars[0].price_type === 'per_unit' ? 'unitate' : K.ph.unit[t || 'access']);
    var tg = [];
    if (t && t !== 'package') tg.push(p.booking_mode === 'slot' ? '<span class="wz-pill">' + ic('clock') + esc(p.duration_minutes ? durTxt(p.duration_minutes) : 'cu oră') + '</span>' : '<span class="wz-pill">Toată ziua</span>');
    if (p.cancellation_policy && /gratuit/i.test(p.cancellation_policy)) tg.push('<span class="wz-pill is-g">Anulare gratuită</span>');
    if (p.languages.length) tg.push('<span class="wz-pill">' + esc(p.languages.join(' · ').toUpperCase()) + '</span>');
    if (p.age_min) tg.push('<span class="wz-pill">' + esc(p.age_min) + '+ ani</span>');
    var cover = A.imgUrl(p.cover_image) || (l ? A.imgUrl(l.cover_image) : null);
    var media = cover ? '<img src="' + esc(cover) + '" alt="">' : '<span class="wz-pv-ic">' + pic(p.icon || (t ? TYPE_ICON[t] : 'sparkle')) + '</span>';
    var stTxt = { draft: 'Ciornă', pending: 'În verificare', rejected: 'Respins', approvedOn: 'Pe site', approvedOff: 'Ascuns' }[statusKey(p)];
    var h = '<div class="wz-pv"><div class="wz-pv-m">' + media + '<span class="wz-pv-st">' + stTxt + '</span>' + (cover && !p.cover_image ? '<span class="wz-pv-fb">poza locației</span>' : '') + '</div><div class="wz-pv-b">' +
      '<span class="wz-pv-k">' + esc([t ? TYPES[t] : 'Produs', l ? l.name : ''].filter(Boolean).join(' · ')) + '</span>' +
      '<h3 class="wz-pv-t">' + (p.title ? (p.icon ? pic(p.icon) : '') + esc(p.title) : '<span class="wz-ph">Titlul produsului</span>') + '</h3>' +
      (p.subtitle ? '<p class="wz-pv-s is-strong">' + esc(p.subtitle) + '</p>' : '') +
      (p.short_description ? '<p class="wz-pv-s">' + esc(p.short_description) + '</p>' : (!p.title ? '<p class="wz-pv-s wz-ph">Descrierea scurtă apare aici.</p>' : '')) +
      (tg.length ? '<div class="wz-pv-tags">' + tg.join('') + '</div>' : '');
    if (t === 'package') {
      h += p.package_items.length ? '<ul class="wz-pv-vars">' + p.package_items.map(function (it) {
        var pr = products.filter(function (x) { return x.id === it.product_id; })[0], v = variantOf(it);
        return '<li><span>' + (it.quantity || 1) + ' × ' + esc((pr && pr.title) || it._title || '') + (v ? ' · ' + esc(v.name) : '') + '</span></li>';
      }).join('') + '</ul>' : '';
      var pk = pkgTotals();
      if (pk.known && p.variants[0].price != null && pk.total > p.variants[0].price) h += '<span class="wz-pv-save">Economisești ' + esc(lei(pk.total - p.variants[0].price)) + '</span>';
    } else if (vars.length) {
      h += '<ul class="wz-pv-vars">' + vars.map(function (v) {
        return '<li><span>' + esc(v.name || 'Bilet') + (v.price_type === 'per_unit' && v.persons_max ? ' <small>· până la ' + esc(v.persons_max) + ' pers.</small>' : '') + '</span><b>' + (v.price != null ? esc(lei(v.price)) : '—') + '</b><span class="wz-pv-q" aria-hidden="true"><i>−</i><i>+</i></span></li>';
      }).join('') + '</ul>';
    }
    var ad = p.addons.filter(function (a) { return a.name && a.is_active !== false; });
    if (ad.length && t !== 'package') h += '<p class="wz-pv-s">+ ' + esc(ad.map(function (a) { return a.name; }).join(', ')) + '</p>';
    h += '<div class="wz-pv-f"><div><small>de la</small><b>' + (min != null ? esc(lei(min)) : '—') + '</b> <span class="wz-pv-u">/ ' + esc(unit) + '</span></div><span class="wz-pv-cta">Rezervă</span></div></div></div>';
    return h;
  }
  var pvTimer = 0;
  function updatePreview() {
    clearTimeout(pvTimer);
    pvTimer = setTimeout(function () {
      if (!W) return;
      var h = preview();
      var a = $('wz-pv'); if (a) a.innerHTML = h;
      var s = $('wz-pv-sheet'); if (s && $('wz-sheet-pv').classList.contains('is-on')) s.innerHTML = h;
    }, 40);
  }
  function bump() {
    ['wz-pv', 'wz-pv-sheet'].forEach(function (id) {
      setTimeout(function () { var n = $(id), c = n && n.querySelector('.wz-pv'); if (c) { c.classList.remove('is-bump'); void c.offsetWidth; c.classList.add('is-bump'); } }, 60);
    });
  }

  /* =================== chrome =================== */
  function railHtml() {
    var a = steps(), locked = !W.p.product_type, html = '';
    PHASES.forEach(function (ph, pi) {
      var ss = a.filter(function (s) { return s.ph === pi; });
      html += '<div class="wz-rail-ph"><span>' + ph + '</span>' + ss.map(function (s) {
        var st = stepState(s), n = a.indexOf(s) + 1;
        return '<button type="button" class="wz-rail-s' + (st ? ' is-' + st : '') + '" data-act="go" data-val="' + jv(s.id) + '"' + (locked && s.id !== 'tip' ? ' disabled' : '') + (st === 'cur' ? ' aria-current="step"' : '') + '><span class="wz-dot">' + (st === 'done' ? ic('check') : st === 'warn' ? '!' : n) + '</span><span>' + s.t + '</span>' + (s.opt ? '<em>opțional</em>' : '') + '</button>';
      }).join('') + '</div>';
    });
    return html;
  }
  function drawChrome(nudge) {
    var a = steps(), i = stepIdx(W.step), s = stepById(W.step);
    $('wz-rail').innerHTML = railHtml();
    $('wz-steps-sheet').innerHTML = '<div class="wz-rail is-sheet">' + railHtml() + '</div>';
    $('wz-top-k').textContent = 'Pasul ' + (i + 1) + ' din ' + a.length + ' · ' + PHASES[s.ph];
    $('wz-top-t').textContent = s.t;
    $('wz-prog').style.width = ((i + 1) / a.length * 100) + '%';
    var tp = TIPS[W.step];
    $('wz-tip').innerHTML = '<b>' + ic('info') + esc(tp[0]) + '</b>' + esc(tp[1]);
    drawBar(nudge);
  }
  function saveLabel() {
    if (W.saving) return 'Se salvează…';
    if (W.saveErr) return 'Nesalvat';
    if (W.dirty && isDraft()) {
      var hard = problems().filter(function (x) { return x.save; })[0];
      if (hard) return 'Se salvează după ce completezi: ' + hard.msg.toLowerCase();
    }
    if (!W.p.id) return W.dirty ? 'Nesalvat încă' : '';
    if (W.dirty) return 'Modificări nesalvate';
    return W.savedAt ? 'Salvat ' + W.savedAt : 'Salvat';
  }
  function drawBar(nudge) {
    var a = steps(), i = stepIdx(W.step), next = a[i + 1], h = '';
    if (nudge) h += '<div class="wz-nudge">' + ic('warning-circle') + '<span>' + esc(nudge) + '</span></div>';
    h += '<div class="wz-bar-in">';
    if (i > 0) h += '<button type="button" class="btn btn-ghost wz-back" data-act="back" aria-label="Înapoi">' + ic('arrow-left') + '<span>Înapoi</span></button>';
    h += '<span class="wz-saved' + (W.dirty || W.saveErr ? ' is-dirty' : '') + '" id="wz-saved">' + esc(saveLabel()) + '</span><span class="wz-sp"></span>';
    if (W.p.product_type) h += '<button type="button" class="btn btn-ghost wz-pv-btn" data-act="pv" aria-label="Previzualizare">' + ic('eye') + '<span>Previzualizare</span></button>';
    var liveEdit = W.p.id && !isDraft();
    if (W.step === 'gata') {
      if (isDraft()) {
        h += '<button type="button" class="btn btn-ghost" data-act="save">' + ic('check') + '<span>Salvează ciorna</span></button>';
        h += '<button type="button" class="btn btn-primary" data-act="submit">' + ic('arrow-right') + '<span>Trimite spre aprobare</span></button>';
      } else {
        h += '<button type="button" class="btn btn-primary" data-act="save"' + (W.dirty ? '' : ' disabled') + '>' + ic('check') + '<span>Salvează modificările</span></button>';
      }
    } else {
      if (liveEdit && W.dirty) h += '<button type="button" class="btn btn-ghost wz-save-btn" data-act="save">' + ic('check') + '<span>Salvează</span></button>';
      if (W.returnTo) h += '<button type="button" class="btn btn-primary" data-act="return">' + ic('arrow-counter-clockwise') + '<span>Înapoi la rezumat</span></button>';
      else if (W.step === 'tip' && !W.p.product_type) h += '<button type="button" class="btn btn-primary" disabled><span>Alege un tip</span>' + ic('arrow-right') + '</button>';
      else if (next) {
        var skip = stepById(W.step).opt && isEmptyOpt(W.step);
        h += '<button type="button" class="btn btn-primary wz-next" data-act="next"><span>' + (skip ? 'Sari peste' : 'Continuă') + '<span class="wz-nx">: ' + esc(next.t) + '</span></span>' + ic('arrow-right') + '</button>';
      }
    }
    $('wz-bar').innerHTML = h + '</div>';
  }
  function setSaved() { var n = $('wz-saved'); if (n) { n.textContent = saveLabel(); n.classList.toggle('is-dirty', !!(W.dirty || W.saveErr)); } }
  function isEmptyOpt(id) {
    var p = W.p;
    if (id === 'extra') return !p.addons.length;
    return false;
  }

  /* =================== rendering =================== */
  function mountAll() {
    var st = $('wz-stage');
    st.querySelectorAll('[data-mount]').forEach(function (m) {
      var k = m.getAttribute('data-mount');
      if (k === 'description') {
        m.appendChild(A.rich(W.p, 'description', { label: 'Descrierea produsului', max: 20000, ph: kit().ph.desc, min: 160, on: function () { changed(); } }));
      } else if (k === 'cover') {
        m.appendChild(A.image(W.p, 'cover_image', 'product', { on: function () { changed(); bump(); drawChrome(); }, hint: 'Poți trage poza direct peste chenar.' }));
      } else if (k === 'gallery') {
        m.appendChild(A.gallery(W.p, 'gallery', 'product-gallery', 20, function () { changed(); }));
      }
    });
  }
  function render(dir) {
    var st = $('wz-stage');
    W.serverErr = W.serverErr && W.serverErr.step === W.step ? W.serverErr : null;
    st.innerHTML = RENDER[W.step]();
    mountAll();
    st.classList.remove('is-in-f', 'is-in-b');
    if (dir && !reduced()) { void st.offsetWidth; st.classList.add(dir > 0 ? 'is-in-f' : 'is-in-b'); }
    drawChrome();
    updatePreview();
  }
  function rerender() {
    var y = window.scrollY, ae = document.activeElement, aid = ae && ae.id;
    $('wz-stage').innerHTML = RENDER[W.step]();
    mountAll();
    drawChrome();
    updatePreview();
    window.scrollTo(0, y);
    if (aid && $(aid) && ae.tagName !== 'BUTTON' && root.contains($(aid))) { try { $(aid).focus({ preventScroll: true }); } catch (e) { $(aid).focus(); } }
  }
  function changed() {
    W.dirty = true;
    W.rev += 1;
    W.saveErr = null;
    updatePreview();
    setSaved();
    if (W.p.id && !isDraft() && W.step !== 'gata' && !$('wz-bar').querySelector('.wz-save-btn')) drawBar();
    if (W.step === 'gata') drawBar();
  }

  /* =================== navigation =================== */
  function goStep(id, dir) {
    if (!W || id === W.step) return;
    var cur = stepIdx(W.step), nxt = stepIdx(id);
    autosave();
    W.step = id;
    W.visited[id] = true;
    W.armDelete = false;
    closeSheets();
    window.scrollTo({ top: 0, behavior: reduced() ? 'auto' : 'smooth' });
    render(dir || (nxt >= cur ? 1 : -1));
  }
  function blocker() {
    var p = W.p;
    if (W.step === 'unde' && p.product_type !== 'experience' && !p.location_id) return 'Alege locația ca să mergi mai departe.';
    if (W.step === 'nume' && !p.title) return 'Scrie titlul; e singurul câmp fără de care nu putem salva.';
    if (W.step === 'tip' && p.product_type === 'package' && !componentChoices().length && !p.package_items.length) return 'Pentru un pachet ai nevoie întâi de bilete sau experiențe.';
    return null;
  }
  function next() {
    var b = blocker();
    W.tried[W.step] = true;
    if (b) {
      rerender();
      drawBar(b);
      var bad = $('wz-stage').querySelector('.is-bad, .wz-err');
      if (bad) {
        bad.classList.add('wz-shake');
        bad.scrollIntoView({ block: 'center', behavior: reduced() ? 'auto' : 'smooth' });
        var f = bad.querySelector && bad.querySelector('input');
        if (f) { try { f.focus({ preventScroll: true }); } catch (e) { f.focus(); } }
      }
      return;
    }
    var a = steps(), i = stepIdx(W.step);
    if (a[i + 1]) goStep(a[i + 1].id, 1);
  }

  /* =================== saving =================== */
  function stepOfKey(k) {
    var top = String(k || '').split('.')[0];
    var map = { product_type: 'tip', access_kind: 'tip', service_type: 'tip', location_id: 'unde', category_id: 'unde', subcategory_id: 'unde', display_category: 'unde', city_id: 'unde',
      title: 'nume', subtitle: 'nume', short_description: 'nume', description: 'nume', icon: 'nume', schedules: 'cand', exceptions: 'cand', booking_mode: 'cand', capacity_mode: 'cand',
      capacity_per_slot: 'cand', daily_capacity: 'cand', duration_minutes: 'cand', slot_interval_minutes: 'cand', booking_lead_time_hours: 'cand', booking_max_advance_days: 'cand',
      use_location_schedule: 'cand', addons: 'extra', package_items: 'continut', cover_image: 'poze', gallery: 'poze', access_requirement: 'setari', issuing_company: 'setari',
      pos_only: 'setari', requires_vehicle_info: 'setari' };
    if (top === 'variants') return W.p.product_type === 'package' ? 'pret' : 'bilete';
    return map[top] || 'info';
  }
  function serverError(err, fallback) {
    var msg = A.errText(err, fallback);
    var e = err && (err.errors || (err.data && err.data.errors));
    var key = e && typeof e === 'object' && !Array.isArray(e) ? Object.keys(e).filter(function (k) { return k !== 'missing'; })[0] : null;
    return { msg: msg, step: key ? stepOfKey(key) : null };
  }
  /** Ids the core gave back, onto the rows that were sent (the core keeps their order). */
  function applySaved(saved, sent) {
    var p = W.p;
    p.id = saved.id;
    ['review_status', 'is_published', 'public_path', 'rejection_reason', 'submitted_at', 'reviewed_at', 'slug'].forEach(function (k) { if (saved[k] !== undefined) p[k] = saved[k]; });
    (saved.variants || []).forEach(function (v, i) { if (sent.variants[i]) sent.variants[i].id = v.id; });
    (saved.addons || []).forEach(function (a, i) { if (sent.addons[i]) sent.addons[i].id = a.id; });
    details[saved.id] = saved;
    var card = { id: saved.id, type: saved.type || p.product_type, title: saved.title, location_id: saved.location_id, booking_mode: saved.booking_mode, image: saved.image, min_price: saved.min_price, variants_count: saved.variants_count, review_status: saved.review_status, is_published: saved.is_published, pos_only: saved.pos_only, public_path: saved.public_path };
    products = products.filter(function (x) { return x.id !== saved.id; }).concat([card]);
  }
  var queue = Promise.resolve(false);
  /** Saves now (after any save in progress). quiet: an automatic save — no messages, nothing if it cannot save yet. */
  function save(quiet) {
    queue = queue.then(function () { return saveNow(quiet); }, function () { return saveNow(quiet); });
    return queue;
  }
  function saveNow(quiet) {
    if (!W) return Promise.resolve(false);
    if (W.p.id && !W.dirty) return Promise.resolve(true);
    var hard = problems().filter(function (x) { return x.save; });
    if (hard.length) {
      if (!quiet) {
        W.tried[hard[0].step] = true;
        if (W.step !== hard[0].step) goStep(hard[0].step, -1); else rerender();
        drawBar('Nu se poate salva încă: ' + hard[0].msg.toLowerCase() + '.');
      }
      return Promise.resolve(false);
    }
    var body = payload(W.p);
    var sent = { variants: W.p.variants.slice(0), addons: W.p.addons.filter(function (a) { return a.name; }) };
    var rev = W.rev, isNew = !W.p.id, w = W;
    W.saving = true;
    W.saveErr = null;
    setSaved();
    root.classList.add('is-busy');
    var req = isNew ? A.api('/products', { method: 'POST', body: body }) : A.api('/products/' + W.p.id, { method: 'PUT', body: body });
    return req.then(function (r) {
      if (w !== W) return false;
      var saved = r && r.data && r.data.product;
      if (saved) {
        applySaved(saved, sent);
        if (isNew) history.replaceState(null, '', '/organizator/produse?id=' + saved.id);
      }
      if (W.rev === rev) W.dirty = false;
      var d = new Date();
      W.savedAt = 'la ' + String(d.getHours()).padStart(2, '0') + ':' + String(d.getMinutes()).padStart(2, '0');
      W.serverErr = null;
      if (!quiet) O.flash((r && r.message) || 'Salvat.');
      return true;
    }, function (err) {
      if (w !== W) return false;
      if (err && err.status === 401) return false;
      var se = serverError(err, 'Nu am putut salva produsul.');
      W.saveErr = se.msg;
      W.serverErr = se.step ? se : null;
      if (!quiet) {
        O.flash(se.msg, true);
        if (se.step && se.step !== W.step) goStep(se.step, -1); else rerender();
      }
      return false;
    }).then(function (ok) {
      if (w === W) {
        W.saving = false;
        root.classList.remove('is-busy');
        drawChrome();
      }
      return ok;
    });
  }
  /** A draft saves itself between steps; a product already on the site (or in review) waits for "Salvează". */
  function autosave() {
    if (!W || !W.dirty || !isDraft() || !W.p.product_type) return;
    if (problems().some(function (x) { return x.save && (x.step === 'unde' || x.step === 'nume' || x.step === 'tip'); })) return;
    save(true);
  }
  function submit() {
    var must = problems();
    if (must.length) {
      W.tried[must[0].step] = true;
      drawBar('Mai ai de completat: ' + must.map(function (x) { return x.msg.toLowerCase(); }).filter(function (v, i, a) { return a.indexOf(v) === i; }).slice(0, 4).join(', ') + '.');
      var ck = $('wz-stage').querySelector('.wz-ck.is-no');
      if (ck) { ck.classList.add('wz-shake'); ck.scrollIntoView({ block: 'center', behavior: reduced() ? 'auto' : 'smooth' }); }
      return;
    }
    save(false).then(function (ok) {
      if (!ok || !W.p.id) return;
      return A.api('/products/' + W.p.id + '/submit', { method: 'POST', body: {} }).then(function (r) {
        var saved = r && r.data && r.data.product;
        if (saved) applySaved(saved, { variants: W.p.variants.slice(0), addons: W.p.addons.filter(function (a) { return a.name; }) });
        celebrate();
        rerender();
      }, function (err) {
        var se = serverError(err, 'Nu am putut trimite produsul.');
        O.flash(se.msg, true);
        drawBar(se.msg);
      });
    });
  }
  function publishToggle() {
    var want = !W.p.is_published;
    var go2 = W.dirty ? save(false) : Promise.resolve(true);
    go2.then(function (ok) {
      if (!ok) return;
      return A.api('/products/' + W.p.id + '/publish', { method: 'POST', body: { published: want } }).then(function (r) {
        var saved = r && r.data && r.data.product;
        if (saved) applySaved(saved, { variants: [], addons: [] });
        O.flash((r && r.message) || 'Gata.');
        rerender();
      }, function (err) { O.flash(A.errText(err, 'Nu am putut schimba vizibilitatea.'), true); });
    });
  }
  function removeProduct() {
    if (!W.armDelete) {
      W.armDelete = true;
      rerender();
      setTimeout(function () { if (W && W.armDelete) { W.armDelete = false; if (W.step === 'gata') rerender(); } }, 4000);
      return;
    }
    A.api('/products/' + W.p.id, { method: 'DELETE' }).then(function (r) {
      var id = W.p.id;
      W.dirty = false;
      products = products.filter(function (x) { return x.id !== id; });
      O.flash((r && r.message) || 'Produsul a fost șters.');
      go('/organizator/produse', true);
    }, function (err) { W.armDelete = false; rerender(); O.flash(A.errText(err, 'Nu am putut șterge produsul.'), true); });
  }

  /* =================== celebration =================== */
  function celebrate() {
    var box = $('wz-done');
    box.querySelector('.wz-done-c').innerHTML = '<div class="wz-done-ic"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M5 12.5l4.5 4.5L19 7.5"/></svg></div><h2 id="wz-done-t">Trimis spre aprobare</h2>' +
      '<p>„' + esc(W.p.title) + '” e acum la echipa bilete.online. Te anunțăm pe e-mail când e aprobat și apare pe site.</p>' +
      '<div class="wz-flow"><div class="is-on"><i>' + ic('file-text') + '</i><b>Ciornă</b></div><div class="is-on"><i>' + ic('eye') + '</i><b>Verificare</b>acum</div><div><i>' + ic('check') + '</i><b>Pe site</b></div></div>' +
      '<div class="wz-done-btns"><a class="btn btn-primary" href="/organizator/produse?nou=1">' + ic('plus') + '<span>Adaugă alt produs</span></a><a class="btn btn-ghost" href="/organizator/produse">' + ic('list') + '<span>Toate produsele</span></a><button type="button" class="wz-link" data-act="closedone">Rămân aici</button></div>';
    box.hidden = false;
    requestAnimationFrame(function () { box.classList.add('is-on'); });
    var b = box.querySelector('.btn');
    if (b) b.focus({ preventScroll: true });
    confetti();
  }
  function closeDone() { var box = $('wz-done'); box.classList.remove('is-on'); setTimeout(function () { box.hidden = true; }, 300); }
  function confetti() {
    if (reduced()) return;
    var c = $('wz-confetti'), x = c.getContext('2d'), Wd, H, parts = [], t0 = performance.now();
    c.hidden = false; Wd = c.width = innerWidth; H = c.height = innerHeight;
    var cols = ['#2BB673', '#F2A900', '#E43A33', '#2D6CCD', '#9BE3BE', '#1B7F4E'];
    for (var i = 0; i < 140; i++) parts.push({ x: Wd / 2, y: H * 0.45, vx: (Math.random() - 0.5) * 16, vy: -Math.random() * 16 - 4, r: Math.random() * 6 + 4, c: cols[i % cols.length], a: Math.random() * 6, va: (Math.random() - 0.5) * 0.4 });
    (function f(now) {
      var e = now - t0;
      x.clearRect(0, 0, Wd, H);
      parts.forEach(function (p) { p.vy += 0.45; p.vx *= 0.99; p.x += p.vx; p.y += p.vy; p.a += p.va; x.save(); x.translate(p.x, p.y); x.rotate(p.a); x.globalAlpha = Math.max(0, 1 - e / 2200); x.fillStyle = p.c; x.fillRect(-p.r / 2, -p.r / 4, p.r, p.r / 2); x.restore(); });
      if (e < 2200) requestAnimationFrame(f); else c.hidden = true;
    })(t0);
  }

  /* =================== sheets =================== */
  function openSheet(id) {
    $('wz-scrim').hidden = false;
    requestAnimationFrame(function () { $('wz-scrim').classList.add('is-on'); $(id).classList.add('is-on'); });
    $(id).hidden = false;
    if (id === 'wz-sheet-pv') $('wz-pv-sheet').innerHTML = preview();
    var b = $(id).querySelector('[data-act="closesheet"]');
    if (b) b.focus({ preventScroll: true });
  }
  function closeSheets() {
    var s = $('wz-scrim');
    if (!s) return;
    s.classList.remove('is-on');
    ['wz-sheet-pv', 'wz-sheet-steps'].forEach(function (id) { var n = $(id); if (n) n.classList.remove('is-on'); });
    setTimeout(function () { if (!s.classList.contains('is-on')) { s.hidden = true; ['wz-sheet-pv', 'wz-sheet-steps'].forEach(function (id) { var n = $(id); if (n && !n.classList.contains('is-on')) n.hidden = true; }); } }, 350);
  }

  /* =================== open / close =================== */
  function shell() {
    return '<div class="wz" id="wz">' +
      '<div class="wz-top" id="wz-top"><div class="wz-top-in"><a class="wz-top-x" href="/organizator/produse" aria-label="Înapoi la toate produsele">' + ic('arrow-left') + '</a>' +
      '<button type="button" class="wz-top-step" data-act="steps" aria-haspopup="dialog"><small id="wz-top-k"></small><b><span id="wz-top-t"></span>' + ic('caret-down') + '</b></button></div><div class="wz-prog"><i id="wz-prog"></i></div></div>' +
      '<div class="wz-app"><nav class="wz-rail" id="wz-rail" aria-label="Pașii produsului"></nav>' +
      '<div class="wz-main"><a class="am-back wz-back-link" href="/organizator/produse">' + ic('arrow-left') + '<span>Toate produsele</span></a><div class="wz-stage" id="wz-stage"></div></div>' +
      '<aside class="wz-side" aria-label="Previzualizare"><div class="wz-side-h"><b>Așa îl văd clienții</b></div><div id="wz-pv"></div><div class="wz-tip" id="wz-tip"></div></aside></div>' +
      '<div class="wz-bar" id="wz-bar"></div>' +
      '<div class="wz-scrim" id="wz-scrim" data-act="closesheet" hidden></div>' +
      '<div class="wz-sheet" id="wz-sheet-pv" role="dialog" aria-modal="true" aria-labelledby="wz-sheet-pv-t" hidden><div class="wz-sheet-h"><b id="wz-sheet-pv-t">Așa îl văd clienții</b><button type="button" class="ve-icon-btn" data-act="closesheet" aria-label="Închide">' + ic('x') + '</button></div><div class="wz-sheet-b" id="wz-pv-sheet"></div></div>' +
      '<div class="wz-sheet" id="wz-sheet-steps" role="dialog" aria-modal="true" aria-labelledby="wz-sheet-steps-t" hidden><div class="wz-sheet-h"><b id="wz-sheet-steps-t">Toți pașii</b><button type="button" class="ve-icon-btn" data-act="closesheet" aria-label="Închide">' + ic('x') + '</button></div><div class="wz-sheet-b" id="wz-steps-sheet"></div></div>' +
      '<div class="wz-done" id="wz-done" role="dialog" aria-modal="true" aria-labelledby="wz-done-t" hidden><div class="wz-done-c"></div></div>' +
      '<canvas class="wz-confetti" id="wz-confetti" hidden></canvas>' +
      '</div>';
  }
  function openWizard(p, step, allVisited) {
    hideListBits();
    var box = $('am-prod-edit');
    box.hidden = false;
    box.innerHTML = shell();
    document.body.classList.add('wz-on');
    W = { p: p, step: step, visited: {}, returnTo: null, tried: {}, dirty: false, rev: 0, saving: false, saveErr: null, serverErr: null, savedAt: null, allIcons: false, openMore: {}, armDelete: false };
    W.visited[step] = true;
    if (allVisited) { ALL_STEPS.forEach(function (s) { W.visited[s.id] = true; }); W.visited.unde_loc = true; }
    render(0);
    // package contents need their variants
    var ids = p.package_items.map(function (i) { return i.product_id; }).filter(function (v, i, a) { return v && a.indexOf(v) === i; });
    if (ids.length) Promise.all(ids.map(function (id) { return productDetail(id).catch(function () { return null; }); })).then(function () { if (W && W.p === p) rerender(); });
  }
  function closeWizard() {
    if (!W) return;
    W = null;
    document.body.classList.remove('wz-on');
    $('am-prod-edit').textContent = '';
  }
  function startNew(pre) {
    var box = $('am-prod-edit');
    hideListBits();
    box.hidden = false;
    box.textContent = '';
    box.appendChild(el('p', { class: 've-state', text: 'Se încarcă…' }));
    Promise.all([loadBase(), loadProducts()]).then(function () {
      var loc = pre && locById(pre) ? pre : (locations.length === 1 ? locations[0].id : null);
      var p = blank(loc);
      openWizard(p, 'tip', false);
    }, function (err) {
      if (err && err.status === 401) return;
      box.textContent = '';
      box.appendChild(el('div', { class: 'org-empty is-error' }, [el('b', { text: 'Nu am putut deschide formularul' }), el('p', { text: A.errText(err, 'Reîncearcă în câteva secunde.') })]));
    });
  }
  function openEditor(id) {
    hideListBits();
    var box = $('am-prod-edit');
    box.hidden = false;
    box.textContent = '';
    box.appendChild(el('p', { class: 've-state', text: 'Se încarcă…' }));
    Promise.all([loadBase(), A.api('/products/' + id), products.length ? Promise.resolve(products) : loadProducts()]).then(function (res) {
      var p = res[1] && res[1].data && res[1].data.product;
      if (!p) { O.flash('Produsul nu există.', true); go('/organizator/produse', true); return; }
      details[p.id] = p;
      openWizard(fromApi(clone(p)), 'gata', true);
    }, function (err) {
      if (err && err.status === 401) return;
      box.textContent = '';
      box.appendChild(el('div', { class: 'org-empty is-error' }, [el('b', { text: 'Nu am putut încărca produsul' }), el('p', { text: A.errText(err, 'Reîncearcă.') })]));
    });
  }

  /* =================== actions =================== */
  function setType(t) {
    var p = W.p, was = p.product_type;
    if (was === t || p.id) return;
    p.product_type = t;
    if (t === 'package') {
      p.variants = [blankVariant('package', 'Pachet')];
      p.booking_mode = 'day';
    } else {
      if (was === 'package' || !p.variants.length) p.variants = [blankVariant(t, t === 'experience' ? null : 'Adult')];
      p.booking_mode = t === 'experience' ? 'slot' : 'day';
      if (t === 'experience' && !p.use_location_schedule && !p.periods.length) p.periods = [{ season: null, days: blankWeek('10:00', '18:00') }];
    }
    if (t !== 'experience' && !p.location_id && locations.length === 1) p.location_id = locations[0].id;
    applyLocationDefaults();
    changed();
    rerender();
    if (was) O.flash('Tipul s-a schimbat în „' + TYPES[t] + '”. Ce ai completat rămâne.');
  }
  /** A location brings its schedule and, when the product has none yet, its category. */
  function applyLocationDefaults() {
    var p = W.p, l = locById(p.location_id);
    var seasons = !!(l && l.seasons && l.seasons.length);
    if (!p.id || !seasons) p.use_location_schedule = seasons;
    if (!(l && (l.display_categories || []).some(function (g) { return g.id === p.display_category; }))) p.display_category = null;
    if (!p.category_id && l && l.category_id) {
      var c = catById(l.category_id);
      if (c && c.parent_id && catById(c.parent_id)) { p.category_id = c.parent_id; p.subcategory_id = c.id; }
      else if (c) { p.category_id = c.id; p.subcategory_id = null; }
    }
    if (p.product_type === 'package') {
      p.package_items = p.package_items.filter(function (it) { var x = products.filter(function (y) { return y.id === it.product_id; })[0]; return !x || !p.location_id || !x.location_id || x.location_id === p.location_id; });
    }
    if (p.booking_mode === 'slot' && !p.use_location_schedule && !p.periods.length) p.periods = [{ season: null, days: blankWeek('10:00', '18:00') }];
  }
  function addVariant(name) {
    var p = W.p, t = p.product_type, tpl = (kit().vt[t] || []).filter(function (x) { return x[0] === name; })[0];
    p.variants.forEach(function (v) { v._open = false; });
    p.variants.push(blankVariant(t, name, Object.assign({}, tpl ? clone(tpl[1]) : {}, { _open: true })));
    changed();
    rerender();
    var cards = $('wz-stage').querySelectorAll('.wz-var');
    var last = cards[cards.length - 1];
    if (last) {
      last.scrollIntoView({ block: 'center', behavior: reduced() ? 'auto' : 'smooth' });
      var f = last.querySelector(name ? '[data-bind$=".price"]' : '[data-bind$=".name"]');
      if (f) setTimeout(function () { try { f.focus({ preventScroll: true }); } catch (e) { f.focus(); } }, 250);
    }
  }
  function addPackageItem(id) {
    var p = W.p;
    if (p.package_items.length >= 20) return;
    var it = { product_id: id, variant_id: null, quantity: 1, allocated_price: null };
    p.package_items.push(it);
    changed();
    rerender();
    bump();
    productDetail(id).then(function (d) {
      if (!W || W.p !== p) return;
      var vs = ((d && d.variants) || []).filter(function (v) { return v.is_active; });
      if (vs.length === 1 && !it.variant_id) it.variant_id = vs[0].id;
      rerender();
    }, function () { O.flash('Nu am putut încărca biletele produsului.', true); });
  }

  root.addEventListener('click', function (e) {
    if (!W) return;
    var b = e.target.closest('[data-act]');
    if (!b || !root.contains(b) || b.disabled) return;
    var act = b.getAttribute('data-act'), p = W.p, raw = b.getAttribute('data-val'), val = null;
    if (raw != null) { try { val = JSON.parse(raw); } catch (err) { val = raw; } }
    var i = +b.getAttribute('data-i');
    switch (act) {
      case 'type': setType(val); break;
      case 'set': {
        var path = b.getAttribute('data-path');
        setP(path, val);
        if (path === 'access_kind' && val === 'vehicle') p.requires_vehicle_info = true;
        changed(); rerender();
        if (path === 'icon' || path === 'title' || path === 'variants.0.price') bump();
        break;
      }
      case 'mode':
        p.booking_mode = val;
        if (val === 'slot' && !p.use_location_schedule && !p.periods.length) p.periods = [{ season: null, days: blankWeek('10:00', '18:00') }];
        changed(); rerender(); break;
      case 'loc':
        p.location_id = val;
        W.visited.unde_loc = true;
        applyLocationDefaults();
        changed(); rerender(); break;
      case 'reloadloc':
        A.api('/locations').then(function (r) { locations = ((r && r.data && r.data.locations) || []); rerender(); O.flash('Lista de locații e la zi.'); }, function (err) { O.flash(A.errText(err, 'Nu am putut reîncărca locațiile.'), true); });
        break;
      case 'cat':
        p.category_id = String(p.category_id) === String(val) ? null : val;
        p.subcategory_id = null;
        changed(); rerender(); break;
      case 'allicons': W.allIcons = true; rerender(); break;
      case 'fewicons': W.allIcons = false; rerender(); break;
      case 'go': goStep(val); break;
      case 'edit': W.returnTo = 'gata'; goStep(val, -1); break;
      case 'return': W.returnTo = null; goStep('gata', 1); break;
      case 'next': next(); break;
      case 'back': { var a = steps(), k = stepIdx(W.step); if (k > 0) goStep(a[k - 1].id, -1); break; }
      case 'pv': openSheet('wz-sheet-pv'); break;
      case 'steps': openSheet('wz-sheet-steps'); break;
      case 'closesheet': closeSheets(); break;
      case 'closedone': closeDone(); break;
      case 'step': {
        var sp = b.getAttribute('data-path'), d = +b.getAttribute('data-d'), cur = getP(sp);
        var nv = cur == null ? +(b.getAttribute('data-start') || 0) : cur + d;
        if (b.hasAttribute('data-min')) nv = Math.max(+b.getAttribute('data-min'), nv);
        if (b.hasAttribute('data-max')) nv = Math.min(+b.getAttribute('data-max'), nv);
        setP(sp, nv);
        var input = b.parentNode.querySelector('input');
        if (input) input.value = nv;
        changed();
        if (b.hasAttribute('data-re')) rerender(); else liveBits(sp);
        break;
      }
      case 'vopen': p.variants[i]._open = !p.variants[i]._open; rerender(); break;
      case 'vadd': addVariant(val); break;
      case 'vdel': p.variants.splice(i, 1); changed(); rerender(); O.flash('Biletul a fost scos. Cele deja vândute rămân valabile.'); break;
      case 'tag': { var arr = getP(b.getAttribute('data-path')); if (arr.length < 20 && arr.indexOf(val) < 0) arr.push(val); changed(); rerender(); break; }
      case 'untag': getP(b.getAttribute('data-path')).splice(i, 1); changed(); rerender(); break;
      case 'lang': { var li = p.languages.indexOf(val); if (li >= 0) p.languages.splice(li, 1); else p.languages.push(val); changed(); rerender(); break; }
      case 'terms': p.usage_terms = ((p.usage_terms ? p.usage_terms.trim() + ' ' : '') + val).slice(0, 2000); changed(); rerender(); break;
      case 'day': { var dd = p.periods[+b.getAttribute('data-pi')].days[+b.getAttribute('data-d')]; dd.on = !dd.on; changed(); rerender(); break; }
      case 'slotadd': {
        var ds = p.periods[+b.getAttribute('data-pi')].days[+b.getAttribute('data-d')], lastS = ds.slots[ds.slots.length - 1];
        var st2 = lastS && mins(lastS.close) != null ? Math.min(mins(lastS.close) + 60, 22 * 60) : 14 * 60;
        ds.slots.push({ open: hhmm(st2), close: hhmm(Math.min(st2 + 240, 23 * 60 + 59)), is_active: true });
        changed(); rerender(); break;
      }
      case 'slotdel': p.periods[+b.getAttribute('data-pi')].days[+b.getAttribute('data-d')].slots.splice(+b.getAttribute('data-si'), 1); changed(); rerender(); break;
      case 'copymon': {
        var per = p.periods[i], m = per.days[1];
        DAYS.forEach(function (x) { if (x[0] !== 1 && per.days[x[0]].on) per.days[x[0]].slots = clone(m.slots); });
        changed(); rerender(); O.flash('Orele de luni sunt acum pe toate zilele deschise.'); break;
      }
      case 'season': p.periods[i].season = { start: '05-01', end: '09-30' }; changed(); rerender(); break;
      case 'noseason': p.periods[i].season = null; changed(); rerender(); break;
      case 'peradd': p.periods.push(p.periods.length ? { season: { start: '10-01', end: '04-30' }, days: blankWeek('10:00', '16:00') } : { season: null, days: blankWeek('10:00', '18:00') }); changed(); rerender(); break;
      case 'perdel': p.periods.splice(i, 1); changed(); rerender(); break;
      case 'exadd': p.exceptions.push({ date: null, is_closed: true, open: null, close: null, reason: null }); changed(); rerender(); break;
      case 'exdel': p.exceptions.splice(i, 1); changed(); rerender(); break;
      case 'adadd': p.addons.push({ id: null, name: val, price: null, included_qty: 0, max_per_unit: 1, is_active: true }); changed(); rerender(); break;
      case 'addel': p.addons.splice(i, 1); changed(); rerender(); break;
      case 'pkadd': addPackageItem(val); break;
      case 'pkdel': p.package_items.splice(i, 1); changed(); rerender(); break;
      case 'save': save(false).then(function (ok) { if (ok) rerender(); }); break;
      case 'submit': submit(); break;
      case 'publish': publishToggle(); break;
      case 'dup': (W.dirty ? save(false) : Promise.resolve(true)).then(function (ok) { if (ok) duplicate(W.p.id); }); break;
      case 'delete': removeProduct(); break;
    }
  });
  root.addEventListener('toggle', function (e) {
    var d = e.target;
    if (W && d && d.matches && d.matches('details[data-more]')) W.openMore[d.getAttribute('data-more')] = d.open;
  }, true);

  function parseVal(n) {
    var t = n.getAttribute('data-t'), v = n.value;
    if (t === 'bool') return n.checked;
    if (t === 'json') { try { return JSON.parse(v); } catch (e) { return null; } }
    if (t === 'int') {
      v = String(v).replace(/[^\d]/g, '');
      if (v === '') return null;
      var x = parseInt(v, 10);
      if (n.hasAttribute('data-max')) x = Math.min(+n.getAttribute('data-max'), x);
      return x;
    }
    if (t === 'num') { v = String(v).replace(',', '.').replace(/[^\d.]/g, ''); var f = parseFloat(v); return v === '' || isNaN(f) ? null : Math.round(f * 100) / 100; }
    return String(v).trim() === '' ? null : v;
  }
  function liveBits(path) {
    var p = W.p;
    var m = path.match(/^variants\.(\d+)\.(name|price)$/);
    if (m) {
      var v = p.variants[+m[1]];
      var nm = root.querySelector('[data-live="variants.' + m[1] + '.name"]'); if (nm) nm.textContent = v.name || 'Bilet fără nume';
      var pr = root.querySelector('[data-live-price="' + m[1] + '"]'); if (pr) pr.innerHTML = v.price != null ? esc(lei(v.price)) : '<span class="wz-ph">preț?</span>';
    }
    var cnt = root.querySelector('[data-count="' + path + '"]');
    if (cnt) cnt.textContent = (getP(path) || '').length + ' / ' + cnt.textContent.split('/ ')[1];
    if (W.step === 'cand') { var ws = $('wz-when'); if (ws) ws.innerHTML = whenSentence(); }
    if (W.step === 'pret' && path === 'variants.0.price') {
      var t = pkgTotals(), price = p.variants[0].price, sb = $('wz-save-big');
      if (sb && t.known) {
        var sv = price == null ? null : t.total - price, mx = Math.max(t.total, price || 0) || 1;
        sb.textContent = sv == null ? '—' : sv < 0 ? 'Mai scump cu ' + lei(-sv) : lei(sv);
        sb.classList.toggle('is-bad', sv != null && sv < 0);
        $('wz-bar-pk').style.width = ((price || 0) / mx * 100) + '%';
        $('wz-bar-pk-t').textContent = price != null ? lei(price) : '—';
      }
    }
    if (path === 'unit_label' && $('wz-unit-ex')) { var v0 = p.variants[0]; $('wz-unit-ex').textContent = (v0 && v0.price != null ? lei(v0.price) : '50 lei') + ' / ' + (p.unit_label || kit().ph.unit[p.product_type]); }
  }
  root.addEventListener('input', function (e) {
    var n = e.target;
    if (!W || !n.getAttribute) return;
    var path = n.getAttribute('data-bind');
    if (path && n.type !== 'checkbox' && n.tagName !== 'SELECT' && n.type !== 'date') {
      setP(path, parseVal(n));
      changed();
      liveBits(path);
      var bad = n.closest('.is-bad');
      if (bad && bad.classList.contains('wz-fl')) bad.classList.remove('is-bad');
    }
  });
  root.addEventListener('change', function (e) {
    var n = e.target;
    if (!W || !n.getAttribute) return;
    var path = n.getAttribute('data-bind');
    if (path) {
      var val = parseVal(n);
      // a number under the field's minimum is taken as the minimum when the field is left (the core refuses it)
      if (n.getAttribute('data-t') === 'int' && val != null && n.hasAttribute('data-min') && val < +n.getAttribute('data-min')) { val = +n.getAttribute('data-min'); n.value = val; }
      setP(path, val);
      changed();
      if (n.hasAttribute('data-re') || n.type === 'checkbox' || n.tagName === 'SELECT') rerender();
      else if (n.type === 'time') rerender();
    }
    var md = n.getAttribute('data-md');
    if (md) {
      var cur = (getP(md) || '01-01').split('-');
      if (n.getAttribute('data-part') === 'm') cur[0] = String(n.value).padStart(2, '0'); else cur[1] = String(n.value).padStart(2, '0');
      // no 31 April: the day moves back to the month's last one
      var dim = [31, 29, 31, 30, 31, 30, 31, 31, 30, 31, 30, 31][+cur[0] - 1];
      if (+cur[1] > dim) cur[1] = String(dim);
      setP(md, cur[0] + '-' + cur[1]);
      changed();
      rerender();
    }
  });
  root.addEventListener('keydown', function (e) {
    var n = e.target;
    if (!W || !n.getAttribute) return;
    var tp = n.getAttribute('data-tagin');
    if (tp && (e.key === 'Enter' || e.key === ',')) {
      e.preventDefault();
      var v = n.value.replace(/\s+/g, ' ').trim().slice(0, 200);
      if (!v) return;
      var arr = getP(tp);
      if (arr.indexOf(v) < 0 && arr.length < 20) arr.push(v);
      n.value = '';
      changed();
      rerender();
      var again = root.querySelector('[data-tagin="' + tp + '"]'); if (again) again.focus();
    } else if (tp && e.key === 'Backspace' && !n.value) {
      var a2 = getP(tp);
      if (a2.length) { a2.pop(); changed(); rerender(); var ag = root.querySelector('[data-tagin="' + tp + '"]'); if (ag) ag.focus(); }
    } else if (e.key === 'Enter' && n.tagName === 'INPUT' && n.getAttribute('data-bind') && n.type !== 'checkbox') {
      e.preventDefault();
      n.blur();
    } else if (e.key === 'Escape') {
      closeSheets();
      if (!$('wz-done').hidden) closeDone();
    }
  });
  // A tag typed but not confirmed with Enter is kept when the field loses focus.
  root.addEventListener('focusout', function (e) {
    var n = e.target;
    if (!W || !n.getAttribute) return;
    var tp = n.getAttribute('data-tagin');
    if (!tp) return;
    var v = n.value.replace(/\s+/g, ' ').trim().slice(0, 200);
    if (!v) return;
    var arr = getP(tp);
    if (arr.indexOf(v) < 0 && arr.length < 20) arr.push(v);
    n.value = '';
    changed();
    setTimeout(function () { if (W) rerender(); }, 0);
  });

  /* =================== routing =================== */
  function route() {
    var q = params();
    var id = parseInt(q.get('id'), 10);
    closeWizard();
    if (id > 0) openEditor(id);
    else if (q.get('nou')) startNew(parseInt(q.get('locatie'), 10) || null);
    else showList();
    window.scrollTo(0, 0);
  }
  window.addEventListener('popstate', function () {
    if (W && W.dirty && !window.confirm('Ai modificări nesalvate. Pleci fără să le salvezi?')) { history.pushState(null, '', W.p.id ? '/organizator/produse?id=' + W.p.id : '/organizator/produse?nou=1'); return; }
    route();
  });
  window.addEventListener('beforeunload', function (e) { if (W && (W.dirty || W.saving)) { e.preventDefault(); e.returnValue = ''; } });
  document.addEventListener('click', function (e) {
    var a = e.target.closest('a[href]');
    if (!a || e.defaultPrevented || e.ctrlKey || e.metaKey || e.shiftKey || a.target === '_blank') return;
    var href = a.getAttribute('href');
    if (!/^\/organizator\/produse/.test(href)) return;
    if (W && W.dirty) {
      // a draft can save itself on the way out
      if (isDraft() && !problems().some(function (x) { return x.save; })) {
        e.preventDefault();
        save(true).then(function (ok) { if (ok || window.confirm('Nu am putut salva ciorna. Pleci fără să o salvezi?')) { if (W) W.dirty = false; go(href); } });
        return;
      }
      if (!window.confirm('Ai modificări nesalvate. Pleci fără să le salvezi?')) { e.preventDefault(); return; }
      W.dirty = false;
    }
    e.preventDefault();
    go(href);
  });
  $('am-f-loc').addEventListener('change', function () {
    var v = $('am-f-loc').value;
    history.replaceState(null, '', '/organizator/produse' + (v ? '?locatie=' + v : ''));
    drawList();
  });
  $('am-f-type').addEventListener('change', drawList);
  $('am-prod-retry').addEventListener('click', function () { $('am-prod-failed').hidden = true; route(); });

  O.ready.then(function (ok) { if (ok) route(); });
})();
