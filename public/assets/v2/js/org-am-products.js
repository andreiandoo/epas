/* viaqui.com v2: the operator's products (/organizator/produse): access tickets, experiences and packages.
   A filtered list and, for ?nou=1 (a new product) and ?id=N (an existing one), a step-by-step editor: what it is, where
   (location and the site's category), name and story, the tickets and prices (or, for a package, its contents and its
   price), when it can be used, add-ons, what the client should know, pictures, the last settings and a summary to check
   and send. Only the steps that fit the kind are shown; every step can be reopened from the list of steps or from the
   summary. Suggestions (ticket names, what is included, add-ons…) come from the product's category, never from one
   kind of operator. A draft saves itself when moving between steps; an approved product is live, so its changes wait
   for "Save". The product object is sent whole, exactly as the core validates it (OrganizerCatalog::validateProduct).
   Product icons are keys of includes/v2/product-icons.php (SVG, never emoji). Uses window.BO_ORG and window.BO_AM. */
(function () {
  'use strict';
  var O = window.BO_ORG, A = window.BO_AM, root = document.getElementById('am-prod');
  if (!O || !A || !root) return;
  var el = O.el, F = O.fmt, L = A.L;
  var $ = function (id) { return document.getElementById(id); };
  var TYPES = L.product_types || { access: 'Entry ticket', experience: 'Experience', package: 'Package' };
  var TYPE_ICON = L.product_type_icons || { access: 'ticket', experience: 'lightning', package: 'gift' };
  var ICONS = L.product_icons || [];
  var ICON = {};
  ICONS.forEach(function (x) { ICON[x[0]] = x; });
  var meta = null, locations = [], products = [];
  var details = {}; // product id → full product (package components need their variants)
  var W = null;     // the open editor

  var PROD_URL = VQ.url('/organizator/produse'), LOC_NEW_URL = VQ.url('/organizator/locatii?nou=1');
  function params() { return new URLSearchParams(window.location.search); }
  function go(url, replace) {
    if (replace) history.replaceState(null, '', url); else history.pushState(null, '', url);
    route();
  }
  function locById(id) { return locations.filter(function (l) { return l.id === id; })[0] || null; }
  function lei(v) { return typeof BileteOnlineUtils !== 'undefined' ? BileteOnlineUtils.formatCurrency(v || 0) : F.money(v || 0); }

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
    s.appendChild(el('option', { value: '', text: VQ.t('All venues') }));
    locations.forEach(function (l) { s.appendChild(el('option', { value: String(l.id), text: l.name || VQ.t('Venue {id}', { id: l.id }) })); });
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
    box.appendChild(el('p', { class: 've-state', text: VQ.t('Loading…') }));
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
        el('b', { text: VQ.t('First, the venue') }),
        el('p', { text: VQ.t('Access tickets and packages belong to a venue. Add it, then come back here. An experience can also have no venue.') }),
        el('a', { class: 'btn btn-primary', href: LOC_NEW_URL }, [O.icon('plus'), el('span', { text: VQ.t('Add the venue') })]),
        el('a', { class: 'btn btn-ghost', href: PROD_URL + '?nou=1' }, [O.icon('lightning'), el('span', { text: VQ.t('Experience without a venue') })]),
      ]));
      return;
    }
    var loc = parseInt($('am-f-loc').value, 10) || null, type = $('am-f-type').value || null;
    var shown = products.filter(function (p) { return (!loc || p.location_id === loc) && (!type || p.type === type); });
    if (!shown.length) {
      box.appendChild(el('div', { class: 'org-empty' }, [
        el('span', { class: 'org-empty-ic' }, [O.icon('ticket')]),
        el('b', { text: products.length ? VQ.t('No products for the chosen filters') : VQ.t('No products yet') }),
        el('p', { text: VQ.t('An access ticket for entry, an experience (a tour, a workshop, an activity) or a package with both.') }),
        el('a', { class: 'btn btn-primary', href: PROD_URL + '?nou=1' + (loc ? '&locatie=' + loc : '') }, [O.icon('plus'), el('span', { text: VQ.t('Add a product') })]),
      ]));
      return;
    }
    var list = el('div', { class: 've-prods' });
    ['access', 'experience', 'package'].forEach(function (t) {
      var group = shown.filter(function (p) { return p.type === t; });
      if (!group.length) return;
      var g = el('div', { class: 've-prod-group' }, [el('p', { class: 've-sec-k', text: { access: VQ.t('Access tickets'), experience: VQ.t('Experiences'), package: VQ.t('Packages') }[t] })]);
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
      el('b', { text: p.title || VQ.t('Untitled product') }),
      el('small', { text: [TYPES[p.type] || '', l ? l.name : '', p.booking_mode === 'slot' ? VQ.t('timed') : (p.type === 'package' ? '' : VQ.t('all day')), p.variants_count ? VQ.n(p.variants_count, 'ticket', 'tickets') : ''].filter(Boolean).join(' · ') }),
      el('span', { class: 've-prod-tags' }, A.statusTags(p)),
      hint ? el('small', { class: 'am-hint', text: hint }) : null,
    ]);
    var price = el('div', { class: 've-prod-price' }, p.min_price != null ? [el('small', { text: VQ.t('from') }), el('b', { text: lei(p.min_price) })] : []);
    var tools = el('div', { class: 've-prod-tools' });
    tools.appendChild(el('a', { class: 'btn btn-ghost', href: PROD_URL + '?id=' + p.id }, [O.icon('gear-six'), el('span', { text: VQ.t('Edit') })]));
    var dup = A.button('copy', VQ.t('Copy'), 'btn btn-ghost');
    dup.addEventListener('click', function () { duplicate(p.id); });
    tools.appendChild(dup);
    if (p.is_published && p.public_path && (!p.review_status || p.review_status === 'approved')) {
      tools.appendChild(el('a', { class: 've-icon-btn', href: p.public_path, target: '_blank', rel: 'noopener', 'aria-label': VQ.t('View on the site') }, [O.icon('arrow-right')]));
    }
    return el('article', { class: 've-prod' + (p.is_published ? '' : ' is-off') }, [media, t, price, tools]);
  }
  function duplicate(id) {
    return A.api('/products/' + id + '/duplicate', { method: 'POST', body: {} }).then(function (r) {
      O.flash((r && r.message) || VQ.t('The copy was created.'));
      var copy = r && r.data && r.data.product;
      if (copy) {
        products = products.filter(function (x) { return x.id !== copy.id; }).concat([copy]);
        if (W) W.dirty = false;
        go(PROD_URL + '?id=' + copy.id);
      }
    }, function (err) { O.flash(A.errText(err, VQ.t('We could not copy the product.')), true); });
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
  function durTxt(m) { if (!m) return ''; if (m < 60) return VQ.t('{n} min', { n: m }); var h = Math.floor(m / 60), r = m % 60; return r ? VQ.t('{hours} {n} min', { hours: VQ.n(h, 'hour', 'hours'), n: r }) : VQ.n(h, 'hour', 'hours'); }
  function reduced() { return window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches; }
  function getP(path) { return path.split('.').reduce(function (o, k) { return o == null ? o : o[k]; }, W.p); }
  function setP(path, val) {
    var ks = path.split('.'), o = W.p;
    for (var i = 0; i < ks.length - 1; i++) o = o[ks[i]];
    o[ks[ks.length - 1]] = val;
  }
  function idOf(path) { return 'wz-' + path.replace(/\./g, '-'); }

  var DAY_NAMES = L.days || {};
  var DAYS = [[1, DAY_NAMES.mon || 'Monday', 'mon'], [2, DAY_NAMES.tue || 'Tuesday', 'tue'], [3, DAY_NAMES.wed || 'Wednesday', 'wed'], [4, DAY_NAMES.thu || 'Thursday', 'thu'], [5, DAY_NAMES.fri || 'Friday', 'fri'], [6, DAY_NAMES.sat || 'Saturday', 'sat'], [7, DAY_NAMES.sun || 'Sunday', 'sun']];
  var DATE_LOC = VQ.locale === 'en' ? 'en-GB' : VQ.locale;
  var MONTHS = Array.from({ length: 12 }, function (_, i) { try { return new Date(2020, i, 1).toLocaleDateString(DATE_LOC, { month: 'short' }); } catch (e) { return String(i + 1); } });
  var MONTHS_LONG = (L.months && L.months.length === 12) ? L.months : ['January', 'February', 'March', 'April', 'May', 'June', 'July', 'August', 'September', 'October', 'November', 'December'];
  var LANGS = [['ro', VQ.t('Romanian')], ['en', VQ.t('English')], ['hu', VQ.t('Hungarian')], ['de', VQ.t('German')], ['fr', VQ.t('French')]];
  var CANCEL = [[VQ.t('Free cancellation up to 24 hours before.'), VQ.t('24 hours')], [VQ.t('Free cancellation up to 48 hours before.'), VQ.t('48 hours')], [VQ.t('Free cancellation up to 7 days before.'), VQ.t('7 days')], [VQ.t('The ticket is non-refundable.'), VQ.t('Non-refundable')]];
  function mdText(md) { if (!md || !/^\d{2}-\d{2}$/.test(md)) return ''; return (+md.slice(3)) + ' ' + MONTHS_LONG[+md.slice(0, 2) - 1]; }

  /* =================== suggestions, by category ===================
     KIT_BASE fits any operator; a category only overrides what is different for it. They are one-tap shortcuts:
     nothing here is required and the operator can write anything else. */
  var KIT_BASE = {
    titles: { access: [VQ.t('Entry'), VQ.t('Day ticket'), VQ.t('Season pass')], experience: [VQ.t('Guided tour'), VQ.t('Workshop'), VQ.t('Rental')], package: [VQ.t('Entry + experience'), VQ.t('Family package')] },
    vt: { access: [[VQ.t('Adult'), {}], [VQ.t('Child'), { is_child: true }], [VQ.t('Pupil / student'), { description: VQ.t('With a valid student card') }], [VQ.t('Senior'), {}], [VQ.t('Group'), { min_per_order: 8, description: VQ.t('Minimum 8 people') }], [VQ.t('Family 2+2'), { price_type: 'per_unit', persons_max: 4 }]],
      experience: [[VQ.t('Person'), {}], [VQ.t('Child'), {}], [VQ.t('Private group'), { price_type: 'per_unit', persons_max: 10 }]] },
    incl: { access: [VQ.t('All-day access')], experience: [VQ.t('Briefing')], package: [VQ.t('All the tickets in the package')] },
    notIncl: [VQ.t('Transport'), VQ.t('Food and drinks')],
    req: [VQ.t('Arrive 15 minutes early')],
    terms: [VQ.t('Show the ticket at the entrance, on your phone.')],
    addons: [VQ.t('Photo package'), VQ.t('Parking')],
    icons: ['ticket', 'star', 'group', 'lightning', 'gift', 'camera', 'parking', 'compass'],
    unit: { many: VQ.t('units'), eg: VQ.t('You have 10 units. One that left at 10:00 for an hour is free again at 11:00.') },
    perUnit: VQ.t('A team, a car, a tent pitch: one ticket, however many people.'),
    personsHint: VQ.t('How many people one ticket lets in.'),
    ph: { short: { access: VQ.t('Entry, valid all day.'), experience: VQ.t('What the customer does, how long it takes and what is included.'), package: VQ.t('What the customer gets, in one sentence.') }, desc: VQ.t('What the customer does, how long it takes, what they see or take home…'), meet: VQ.t('The reception, at the main entrance'), unit: { access: VQ.t('person / day'), experience: VQ.t('person'), package: VQ.t('package') } },
    langs: false,
  };
  var KITS = {
    fun: {
      titles: { access: [VQ.t('Day ticket, all attractions'), VQ.t('Season pass'), VQ.t('Evening ticket')], experience: [VQ.t('Karting'), VQ.t('Ferris wheel ride'), VQ.t('Birthday party')], package: [VQ.t('Family day'), VQ.t('Day ticket + fast pass')] },
      vt: { access: [[VQ.t('Adult'), {}], [VQ.t('Child under 1.20 m'), { is_child: true, description: VQ.t('Children under 1 m enter free') }], [VQ.t('Family 2+2'), { price_type: 'per_unit', persons_max: 4 }], [VQ.t('2-day pass'), { validity_days: 2 }], [VQ.t('Group'), { min_per_order: 15, description: VQ.t('Minimum 15 people') }]],
        experience: [[VQ.t('10 minutes'), { duration_minutes: 10 }], [VQ.t('One round'), {}], [VQ.t('Party, up to 15 children'), { price_type: 'per_unit', persons_max: 15 }]] },
      incl: { access: [VQ.t('Unlimited access to the attractions'), VQ.t('The playground')], experience: [VQ.t('Protective equipment'), VQ.t('Briefing')] },
      req: [VQ.t('Minimum height on some attractions'), VQ.t('Children under 12 must be accompanied')],
      terms: [VQ.t('Show the ticket at the entrance, on your phone.'), VQ.t('Keep the access wristband on all day.'), VQ.t('Some attractions close in bad weather.')],
      addons: [VQ.t('Fast pass'), VQ.t('Locker'), VQ.t('Photo package'), VQ.t('Parking')],
      icons: ['balloon', 'ticket', 'rocket', 'group', 'popcorn', 'camera', 'parking', 'gift'],
      unit: { many: VQ.t('karts / cars'), eg: VQ.t('You have 8 karts. One that left at 10:00 for 10 minutes is free again at 10:10.') },
      perUnit: VQ.t('A family, a party group: one ticket, however many people.'),
      personsHint: VQ.t('For the family ticket: 4.'),
      ph: { short: { access: VQ.t('A whole day in the park, with access to all the attractions.'), experience: VQ.t('A race on the track, helmet and briefing included.') }, desc: VQ.t('Which attractions are included, for what ages, what happens in bad weather…'), meet: VQ.t('The ticket office at the entrance'), unit: { experience: VQ.t('round') } } },
    museum: {
      titles: { access: [VQ.t('Entry, permanent exhibition'), VQ.t('Entry, temporary exhibition'), VQ.t('Combined ticket')], experience: [VQ.t('Guided tour of the exhibition'), VQ.t('Workshop for children'), VQ.t('Behind the scenes visit')], package: [VQ.t('Entry + guided tour'), VQ.t('Family ticket')] },
      vt: { access: [[VQ.t('Adult'), {}], [VQ.t('Pupil / student'), { description: VQ.t('With a valid student card') }], [VQ.t('Senior'), {}], [VQ.t('Child'), { is_child: true }], [VQ.t('School group'), { min_per_order: 15, description: VQ.t('Minimum 15 pupils'), companion_label: VQ.t('Accompanying teacher') }]],
        experience: [[VQ.t('Person'), {}], [VQ.t('Pupil / student'), {}], [VQ.t('Private group'), { price_type: 'per_unit', persons_max: 25 }]] },
      incl: { access: [VQ.t('Access to all the rooms'), VQ.t('The exhibition leaflet')], experience: [VQ.t('Museum guide'), VQ.t('Workshop materials')] },
      req: [VQ.t('Large bags stay in the cloakroom')],
      terms: [VQ.t('Show the ticket at the entrance, on your phone.'), VQ.t('Photography is allowed without flash.')],
      addons: [VQ.t('Audio guide'), VQ.t('Photography permit'), VQ.t('Exhibition catalogue')],
      icons: ['museum', 'columns', 'castle', 'theatre', 'compass', 'brush', 'ticket', 'group'],
      langs: true,
      ph: { short: { access: VQ.t('Access to the permanent exhibition, on the chosen day.'), experience: VQ.t('An hour through the main rooms, with the story of each object.') }, desc: VQ.t('What the visitor sees, how long the visit takes, what is new…'), meet: VQ.t('The entrance hall, next to the cloakroom') } },
    nature: {
      titles: { access: [VQ.t('Access to the reserve'), VQ.t('Park entry'), VQ.t('Camping pitch')], experience: [VQ.t('Bicycle rental'), VQ.t('Guided trail'), VQ.t('Boat rental')], package: [VQ.t('A day in nature'), VQ.t('Family 2+2 with one activity')] },
      incl: { access: [VQ.t('All-day access'), VQ.t('The trail map')], experience: [VQ.t('Equipment'), VQ.t('Briefing')] },
      req: [VQ.t('Comfortable footwear'), VQ.t('Clothes suited to the weather')],
      terms: [VQ.t('Show the ticket at the entrance, on your phone.'), VQ.t('Pets are welcome on a lead.'), VQ.t('Fires are allowed only in the designated places.')],
      addons: [VQ.t('Equipment rental'), VQ.t('Parking'), VQ.t('Photo package')],
      icons: ['tree', 'mountains', 'boat', 'bicycle', 'tent', 'hike', 'parking', 'ticket'],
      unit: { many: VQ.t('boats / bicycles'), eg: VQ.t('You have 10 bicycles. One that left at 10:00 for an hour is free again at 11:00.') },
      perUnit: VQ.t('A boat, a car, a tent pitch: one ticket, however many people.'),
      personsHint: VQ.t('For a 4-person boat: 4.'),
      ph: { short: { access: VQ.t('Entry, valid all day.'), experience: VQ.t('A bicycle for one hour, helmet included.') }, desc: VQ.t('The trails, what there is to see, how long a round takes…'), meet: VQ.t('The rental point at the entrance') } },
    adventure: {
      titles: { access: [VQ.t('Park access')], experience: [VQ.t('Course for children'), VQ.t('Course for adults'), VQ.t('The big zip line')], package: [VQ.t('All the courses')] },
      vt: { experience: [[VQ.t('Children course'), { description: VQ.t('For children taller than 1.10 m') }], [VQ.t('Adult course'), {}], [VQ.t('One zip line ride'), {}], [VQ.t('All the courses'), {}]] },
      incl: { experience: [VQ.t('Safety equipment'), VQ.t('Briefing')] },
      req: [VQ.t('Minimum height 1.10 m'), VQ.t('Sports shoes'), VQ.t('Maximum weight 110 kg')],
      addons: [VQ.t('Photo package'), VQ.t('Gloves')],
      icons: ['mountains', 'tree', 'rocket', 'hike', 'group', 'camera', 'ticket', 'star'],
      ph: { short: { experience: VQ.t('A course through the trees, briefing and equipment included.') }, meet: VQ.t('The equipment cabin') } },
    tours: {
      titles: { experience: [VQ.t('Guided tour of the old town'), VQ.t('Bicycle tour'), VQ.t('Boat trip')], package: [VQ.t('Two tours, one day')] },
      vt: { experience: [[VQ.t('Person'), {}], [VQ.t('Child'), {}], [VQ.t('Private tour'), { price_type: 'per_unit', persons_max: 10 }]] },
      incl: { experience: [VQ.t('Guide'), VQ.t('Entry fees along the route')] },
      req: [VQ.t('Comfortable footwear')],
      addons: [VQ.t('Tasting'), VQ.t('Hotel pick-up')],
      icons: ['compass', 'map', 'boat', 'bicycle', 'museum', 'camera', 'group', 'star'],
      langs: true,
      ph: { short: { experience: VQ.t('Two hours through the places the guidebooks leave out.') }, meet: VQ.t('In front of the main entrance') } },
    workshops: {
      titles: { experience: [VQ.t('Pottery workshop'), VQ.t('Painting workshop'), VQ.t('Cooking class')], package: [VQ.t('Two workshops')] },
      vt: { experience: [[VQ.t('Participant'), {}], [VQ.t('Child + parent'), { price_type: 'per_unit', persons_max: 2 }], [VQ.t('Private group'), { price_type: 'per_unit', persons_max: 12 }]] },
      incl: { experience: [VQ.t('All the materials'), VQ.t('You take home what you make')] },
      req: [VQ.t('Clothes you do not mind staining')],
      addons: [VQ.t('Gift wrapping')],
      icons: ['brush', 'palette', 'scissors', 'chef', 'cake', 'group', 'gift', 'star'],
      langs: true,
      ph: { short: { experience: VQ.t('Two hours of work, all the materials included.') }, meet: VQ.t('The workshop, at the main entrance') } },
    escape: {
      titles: { access: [VQ.t('Gift card')], experience: [VQ.t('The "Laboratory" room'), VQ.t('The "Vault" room')], package: [VQ.t('Two rooms, one evening')] },
      vt: { access: [[VQ.t('Team, 2–6 players'), { price_type: 'per_unit', persons_max: 6 }]], experience: [[VQ.t('Team, 2–6 players'), { price_type: 'per_unit', persons_max: 6 }], [VQ.t('Large team, 7–10 players'), { price_type: 'per_unit', persons_max: 10 }]] },
      incl: { experience: [VQ.t('Game master'), VQ.t('Briefing')] },
      req: [VQ.t('Arrive 10 minutes early'), VQ.t('Under 14 only with an adult')],
      addons: [VQ.t('Printed team photo'), VQ.t('Extra time')],
      icons: ['key', 'puzzle', 'group', 'lightning', 'gift', 'camera', 'theatre', 'star'],
      unit: { many: VQ.t('rooms'), eg: VQ.t('You have 3 rooms. A room started at 18:00 for an hour is free again at 19:00.') },
      perUnit: VQ.t('A whole team: one ticket, however many players.'),
      personsHint: VQ.t('How many players fit in a room.'),
      langs: true,
      ph: { short: { experience: VQ.t('You have one hour to get out. For 2–6 players.') }, desc: VQ.t('The story of the room, the difficulty, what ages it is for…'), meet: VQ.t('The reception'), unit: { experience: VQ.t('team') } } },
    zoo: {
      titles: { access: [VQ.t('Zoo entry'), VQ.t('Aquarium entry')], experience: [VQ.t('Feeding the animals'), VQ.t('Tour with the keeper')], package: [VQ.t('Entry + feeding')] },
      incl: { access: [VQ.t('Access to all the areas')], experience: [VQ.t('Keeper')] },
      req: [VQ.t('The animals are fed only with the keeper')],
      terms: [VQ.t('Show the ticket at the entrance, on your phone.'), VQ.t('Pets are not allowed.')],
      addons: [VQ.t('Animal feed'), VQ.t('Photo package')],
      icons: ['paw', 'bird', 'fish', 'tree', 'ticket', 'group', 'camera', 'gift'] },
    family: {
      titles: { access: [VQ.t('Playground entry'), VQ.t('10-entry pass')], experience: [VQ.t('Birthday party'), VQ.t('Workshop for children')], package: [VQ.t('Family day')] },
      vt: { access: [[VQ.t('Child'), { is_child: true }], [VQ.t('Accompanying adult'), {}], [VQ.t('10-entry pass'), { validity_days: 60 }]], experience: [[VQ.t('Child'), {}], [VQ.t('Party, up to 15 children'), { price_type: 'per_unit', persons_max: 15 }]] },
      incl: { access: [VQ.t('2 hours of access')], experience: [VQ.t('Entertainer'), VQ.t('Materials')] },
      req: [VQ.t('Socks are required'), VQ.t('Children stay under the supervision of an adult')],
      addons: [VQ.t('Cake'), VQ.t('Photo package'), VQ.t('Invitations')],
      icons: ['group', 'baby', 'balloon', 'cake', 'confetti', 'game', 'ticket', 'star'] },
  };
  /** The kit of a category (by its slug or name), or null. */
  function kitKeyOf(catId) {
    var c = catById(catId);
    if (!c) return null;
    var s = fold((c.slug || '') + ' ' + (c.name || ''));
    if (/distrac|amusement|theme.park/.test(s)) return 'fun';
    if (/muze|expozit|museum|exhibit/.test(s)) return 'museum';
    if (/aventur|adventur/.test(s)) return 'adventure';
    if (/escape/.test(s)) return 'escape';
    if (/zoo|acvar|aquar|animal/.test(s)) return 'zoo';
    if (/atelier|creativ|workshop|craft/.test(s)) return 'workshops';
    if (/(^|[\s-])tur(uri)?([\s-]|$)|turist|(^|[\s-])tours?([\s-]|$)|sightseeing/.test(s)) return 'tours';
    if (/natur|outdoor/.test(s)) return 'nature';
    if (/famil|copii|kids|children/.test(s)) return 'family';
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
    if (!p.variants.length) p.variants = [blankVariant(p.product_type, p.product_type === 'package' ? VQ.t('Package') : null)];
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
      if (t === 'package' && !o.name) o.name = VQ.t('Package');
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
  var PHASES = [VQ.t('The basics'), VQ.t('What you sell, exactly'), VQ.t('The presentation'), VQ.t('Finish')];
  var ALL_STEPS = [
    { id: 'tip', ph: 0, t: VQ.t('What you sell') },
    { id: 'unde', ph: 0, t: VQ.t('Where') },
    { id: 'nume', ph: 0, t: VQ.t('Name and description') },
    { id: 'bilete', ph: 1, t: VQ.t('Tickets and prices'), only: ['access', 'experience'] },
    { id: 'continut', ph: 1, t: VQ.t('What it contains'), only: ['package'] },
    { id: 'pret', ph: 1, t: VQ.t('Package price'), only: ['package'] },
    { id: 'cand', ph: 1, t: VQ.t('When'), only: ['access', 'experience'] },
    { id: 'extra', ph: 1, t: VQ.t('Add-ons'), only: ['access', 'experience'], opt: true },
    { id: 'info', ph: 2, t: VQ.t('Good to know') },
    { id: 'poze', ph: 2, t: VQ.t('Photos') },
    { id: 'setari', ph: 3, t: VQ.t('Final settings'), opt: true },
    { id: 'gata', ph: 3, t: VQ.t('Check and send') },
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
    function add(step, msg, save, sched) { out.push({ step: step, msg: msg, save: !!save, sched: !!sched }); }
    if (!t) { add('tip', VQ.t('Choose what you sell'), true); return out; }
    if (t !== 'experience' && !p.location_id) add('unde', VQ.t('Choose the venue'), true);
    if (needsCategory() && !p.category_id) add('unde', VQ.t('Choose the Viaqui category'));
    if (!p.title) add('nume', VQ.t('Enter the title'), true);
    var vs = t === 'package' ? 'pret' : 'bilete';
    if (!p.variants.length) add(vs, VQ.t('Add at least one ticket'), true);
    p.variants.forEach(function (v, i) {
      var who = t === 'package' ? VQ.t('The package') : (v.name || VQ.t('Ticket {n}', { n: i + 1 }));
      if (t !== 'package' && !v.name) add(vs, VQ.t('Ticket {n} has no name', { n: i + 1 }), true);
      if (v.price == null) add(vs, VQ.t('{name}: the price is missing', { name: who }), true);
      else if (v.price > 100000) add(vs, VQ.t('{name}: the price is too high', { name: who }), true);
      if (v.min_per_order && v.max_per_order && v.min_per_order > v.max_per_order) add(vs, VQ.t('{name}: the maximum per order is below the minimum', { name: who }), true);
      if (v.persons_min && v.persons_max && v.persons_min > v.persons_max) add(vs, VQ.t('{name}: the maximum number of people is below the minimum', { name: who }), true);
    });
    if (!p.variants.some(function (v) { return v.is_active; })) add(vs, VQ.t('At least one ticket must be on sale'));
    if (t === 'package') {
      if (!p.package_items.length) add('continut', VQ.t('Put at least one product in the package'), true);
      p.package_items.forEach(function (it) { if (!it.variant_id) add('continut', VQ.t('Choose the ticket for "{name}"', { name: it._title || VQ.t('product') })); });
    } else {
      var own = !p.use_location_schedule;
      if (p.booking_mode === 'slot') {
        if (!p.capacity_per_slot) add('cand', VQ.t('How many seats you have at each start time'), true);
        if (!p.duration_minutes || p.duration_minutes < 5) add('cand', VQ.t('How long it lasts (at least 5 minutes)'), true);
        if (!p.slot_interval_minutes || p.slot_interval_minutes < 5) add('cand', VQ.t('How many minutes between starts (at least 5)'), true);
        if (own && !toSchedules(p.periods).length) add('cand', VQ.t('Add the opening hours in which the start times can be booked'), true, true);
      }
      if (own) {
        p.periods.forEach(function (per, pi) {
          DAYS.forEach(function (x) {
            var d = per.days[x[0]];
            var who = p.periods.length > 1 ? VQ.t('{day} (period {n})', { day: x[1], n: pi + 1 }) : x[1];
            if (d && d.on) d.slots.forEach(function (s) {
              if (!s.open || !s.close) add('cand', VQ.t('{name}: fill in the opening hours', { name: who }), true, true);
              else if (s.open >= s.close) add('cand', VQ.t('{name}: the closing time must be after the opening time', { name: who }), true, true);
            });
          });
        });
      }
      p.exceptions.forEach(function (x) { if (x.date && !x.is_closed && x.open && x.close && x.open >= x.close) add('cand', VQ.t('Special day {date}: the closing time must be after the opening time', { date: x.date }), true, true); });
    }
    if (p.age_min != null && p.age_max != null && p.age_min > p.age_max) add('info', VQ.t('The minimum age is above the maximum'), true);
    if (!hasPhoto()) add('poze', VQ.t('Add a photo (the venue has none either)'));
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
      (label ? '<label class="wz-lbl"' + (o.for ? ' for="' + o.for + '"' : '') + '>' + label + (o.req ? ' <span class="wz-req" aria-hidden="true">*</span>' : '') + (o.opt ? ' <small>' + VQ.t('optional') + '</small>' : '') +
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
  function money(path, o) { return inp(path, Object.assign({ t: 'num', mode: 'decimal', suffix: '€', ph: '0' }, o || {})); }
  function ta(path, o) {
    o = o || {};
    return '<textarea class="wz-inp wz-ta" id="' + idOf(path) + '" data-bind="' + path + '" data-t="text" rows="' + (o.rows || 3) + '"' + (o.max ? ' maxlength="' + o.max + '"' : '') + (o.ph ? ' placeholder="' + esc(o.ph) + '"' : '') + '>' + esc(getP(path)) + '</textarea>';
  }
  function stepper(path, o) {
    o = o || {};
    return '<div class="wz-num"><button type="button" data-act="step" data-path="' + path + '" data-d="-' + (o.step || 1) + '" data-min="' + (o.min == null ? 0 : o.min) + '"' + (o.re ? ' data-re="1"' : '') + ' aria-label="' + VQ.t('Less') + '">' + ic('minus') + '</button>' +
      '<input id="' + idOf(path) + '" data-bind="' + path + '" data-t="int" data-min="' + (o.min == null ? 0 : o.min) + '" data-max="' + (o.max || 100000) + '" inputmode="numeric" value="' + esc(getP(path)) + '"' + (o.ph ? ' placeholder="' + esc(o.ph) + '"' : '') + (o.re ? ' data-re="1"' : '') + ' aria-label="' + esc(o.label || '') + '" autocomplete="off">' +
      (o.u ? '<span class="wz-u">' + o.u + '</span>' : '') +
      '<button type="button" data-act="step" data-path="' + path + '" data-d="' + (o.step || 1) + '" data-max="' + (o.max || 100000) + '" data-start="' + (o.start == null ? (o.min || 0) : o.start) + '"' + (o.re ? ' data-re="1"' : '') + ' aria-label="' + VQ.t('More') + '">' + ic('plus') + '</button></div>';
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
      return '<span class="wz-tag">' + esc(t) + '<button type="button" data-act="untag" data-path="' + path + '" data-i="' + i + '" aria-label="' + esc(VQ.t('Remove {name}', { name: t })) + '">' + ic('x') + '</button></span>';
    }).join('') + (arr.length < (limit || 20) ? '<input id="' + idOf(path) + '" data-tagin="' + path + '" maxlength="200" placeholder="' + esc(ph || VQ.t('Type and press Enter')) + '" enterkeyhint="done" autocomplete="off">' : '') + '</div>' +
      (left.length && arr.length < (limit || 20) ? '<div class="wz-sugg"><span>' + VQ.t('Suggestions:') + '</span>' + left.map(function (s) { return '<button type="button" data-act="tag" data-path="' + path + '" data-val="' + jv(s) + '">+ ' + esc(s) + '</button>'; }).join('') + '</div>' : '');
  }
  function block(title, lead, body, o) {
    o = o || {};
    return '<section class="wz-block">' + (title ? '<div class="wz-block-h"><h3>' + title + (o.opt ? '<span class="wz-opt">' + VQ.t('optional') + '</span>' : '') + '</h3>' + (lead ? '<p>' + lead + '</p>' : '') + '</div>' : '') + body + '</section>';
  }
  function choice(act, val, pressed, icon, title, desc, o) {
    o = o || {};
    return '<button type="button" class="wz-card is-row" data-act="' + act + '"' + (o.path ? ' data-path="' + o.path + '"' : '') + ' data-val="' + jv(val) + '" aria-pressed="' + !!pressed + '"' + (o.disabled ? ' disabled' : '') + '>' +
      '<span class="wz-card-ic">' + icon + '</span><span class="wz-card-t"><b>' + title + '</b>' + (desc ? '<small>' + desc + '</small>' : '') + '</span></button>';
  }
  function head(id, title, lead) {
    var a = steps(), i = stepIdx(id);
    var errs = W.serverErr && W.serverErr.step === id ? '<div class="wz-alert is-bad" role="alert">' + ic('warning-circle') + '<span>' + esc(W.serverErr.msg) + '</span></div>' : '';
    return '<header class="wz-head"><p class="wz-k">' + esc(PHASES[stepById(id).ph]) + ' <i>·</i> <i>' + VQ.t('step {n} of {total}', { n: i + 1, total: a.length }) + '</i></p><h1 class="wz-h">' + title + '</h1>' + (lead ? '<p class="wz-lead">' + lead + '</p>' : '') + '</header>' + errs;
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
      ['access', VQ.t('Access ticket'), VQ.t('Entry to the venue, for one day or several.'), VQ.t('Day ticket, adult / child, pass, parking')],
      ['experience', VQ.t('Experience'), VQ.t('Something to do, usually at a set time.'), VQ.t('Guided tour, workshop, game, rental, activity')],
      ['package', VQ.t('Package'), VQ.t('Tickets and experiences together, at one price.'), VQ.t('For example entry + one experience, for a family')],
    ];
    var h = head('tip', locked ? VQ.t('What you sell') : VQ.t('What do you want to sell?'), locked ? VQ.t('The type can no longer be changed after the first save. For another type, make a new product.') : VQ.t('Choose the type and we show you only the steps that matter for it. You can change it until the first save.'));
    h += '<div class="wz-cards is-3">' + K.map(function (k) {
      return '<button type="button" class="wz-card wz-kind" data-act="type" data-val="' + jv(k[0]) + '" aria-pressed="' + (p.product_type === k[0]) + '"' + (locked && p.product_type !== k[0] ? ' disabled' : '') + '><span class="wz-art">' + ART[k[0]] + '</span><span class="wz-kind-t"><b>' + k[1] + '</b><small>' + k[2] + '</small><span class="wz-eg">' + k[3] + '</span></span></button>';
    }).join('') + '</div>';
    if (p.product_type === 'access') {
      h += '<div class="wz-in">' + block(VQ.t('What kind of access?'), VQ.t('It helps us ask the right questions. For parking, for example, we suggest asking for the number plate.'),
        '<div class="wz-cards is-2">' + [['person', 'group', VQ.t('People'), VQ.t('Entry for people')], ['vehicle', 'car', VQ.t('Vehicle'), VQ.t('Parking, access by car')], ['camping', 'tent', VQ.t('Camping'), VQ.t('A pitch for a tent or a caravan')], ['other', 'sparkle', VQ.t('Something else'), VQ.t('Any other kind of access')]].map(function (o) {
          return choice('set', o[0], p.access_kind === o[0], pic(o[1]), o[2], o[3], { path: 'access_kind' });
        }).join('') + '</div>') + '</div>';
    } else if (p.product_type === 'experience') {
      h += '<div class="wz-in">' + block(VQ.t('What kind of experience?'), null,
        '<div class="wz-cards is-2">' + [['rental', 'key', VQ.t('Rental'), VQ.t('Something the customer uses for a while: equipment, a vehicle, a space')], ['guided', 'compass', VQ.t('Guided tour'), VQ.t('With a guide, at a fixed time')], ['workshop', 'brush', VQ.t('Workshop'), VQ.t('Classes, creative activities')], ['other', 'lightning', VQ.t('Something else'), VQ.t('A game, an attraction, a show, anything else')]].map(function (o) {
          return choice('set', o[0], p.service_type === o[0], pic(o[1]), o[2], o[3], { path: 'service_type' });
        }).join('') + '</div>') + '</div>';
    } else if (p.product_type === 'package') {
      var own = products.filter(function (x) { return x.type !== 'package' && x.id !== p.id; }).length;
      h += '<div class="wz-in"><div class="wz-alert' + (own ? '' : ' is-warn') + '">' + ic('info') + '<span>' + (own
        ? VQ.t('A package is made from the tickets and experiences you already have: you have {products} to choose from.', { products: VQ.n(own, 'product', 'products') })
        : VQ.t('A package is made from tickets and experiences you already have, and right now you have none. Add an access ticket or an experience first.')) + '</span></div></div>';
    }
    if (!locations.length && p.product_type && p.product_type !== 'experience') {
      h += '<div class="wz-alert is-warn">' + ic('map-pin') + '<span>' + VQ.t('Access tickets and packages belong to a venue, and you do not have one yet. <a href="{url}">Add the venue</a>, then come back here.', { url: LOC_NEW_URL }) + '</span></div>';
    }
    return h;
  }

  /* =================== step: where =================== */
  function locSeasonsText(l) {
    return (l.seasons || []).map(function (s) {
      var rows = [];
      DAYS.forEach(function (x) { var hh = s.schedule && s.schedule[x[2]]; rows.push(hh && hh.open && hh.close ? hh.open + '–' + hh.close : VQ.t('closed')); });
      var groups = [], start = 0;
      for (var i = 1; i <= 7; i++) {
        if (i === 7 || rows[i] !== rows[start]) {
          groups.push((i - 1 > start ? DAYS[start][1] + ' – ' + DAYS[i - 1][1] : DAYS[start][1]) + ' ' + rows[start]);
          start = i;
        }
      }
      var allSame = rows.every(function (r) { return r === rows[0]; });
      return { label: (s.name ? s.name + ': ' : '') + mdText(s.start) + ' – ' + mdText(s.end), rows: allSame ? [VQ.t('daily {hours}', { hours: rows[0] })] : groups };
    });
  }
  function stUnde() {
    var p = W.p, t = p.product_type, bad = tried('unde');
    var h = head('unde', t === 'experience' ? VQ.t('Where does the experience take place?') : VQ.t('Where is it used?'), VQ.t('Two things: the place the customer comes to and the shelf on Viaqui where they find it.'));
    var cards = locations.map(function (l) {
      var st = l.review_status === 'approved' || !l.review_status ? '' : (' · ' + (l.review_status === 'pending' ? VQ.t('the venue is in review') : VQ.t('the venue is a draft')));
      return choice('loc', l.id, p.location_id === l.id, ic('map-pin'), esc(l.name || VQ.t('Venue {id}', { id: l.id })), ((l.seasons || []).length ? VQ.t('It has opening hours by season') : VQ.t('No opening hours yet')) + st);
    });
    if (t === 'experience') cards.push(choice('loc', null, p.location_id === null && W.visited.unde_loc, pic('compass'), VQ.t('No venue'), VQ.t('A tour that starts somewhere else, a workshop at the customer')));
    h += block(VQ.t('The venue'), t === 'experience' ? VQ.t('Optional for experiences. With a venue, the experience appears on its page and can use its opening hours.') : VQ.t('The ticket belongs to a venue: it can take its opening hours from there and it appears on its page.'),
      '<div class="wz-cards">' + cards.join('') + '</div>' + (bad && t !== 'experience' && !p.location_id ? '<p class="wz-err">' + VQ.t('Choose the venue to be able to continue.') + '</p>' : '') +
      '<p class="wz-hint">' + VQ.t('Not in the list? <a href="{url}" target="_blank" rel="noopener">Add a new venue</a> (it opens alongside), then <button type="button" class="wz-link" data-act="reloadloc">reload the list</button>.', { url: LOC_NEW_URL }) + '</p>');
    var parents = parentCats();
    if (parents.length) {
      var subs = subCats(p.category_id);
      h += block(VQ.t('Where customers find you on Viaqui'), VQ.t('The category decides in which lists, city pages and filters the product appears. Choose it by what the customer does, not by the venue: a workshop held in a museum still belongs under "Workshops".'),
        '<div class="wz-cats">' + parents.map(function (c, i) {
          return '<button type="button" class="wz-cat" data-act="cat" data-val="' + jv(c.id) + '" aria-pressed="' + (String(p.category_id) === String(c.id)) + '"><i style="background:' + CAT_COLORS[i % CAT_COLORS.length] + '"></i>' + esc(c.name) + '</button>';
        }).join('') + '</div>' +
        (subs.length ? '<div class="wz-fl wz-in"><span class="wz-lbl">' + VQ.t('More precisely') + ' <small>' + VQ.t('optional') + '</small></span><div class="wz-chips">' + subs.map(function (c) {
          var on = String(p.subcategory_id) === String(c.id);
          return '<button type="button" class="wz-chip" data-act="set" data-path="subcategory_id" data-val="' + jv(on ? null : c.id) + '" aria-pressed="' + on + '">' + esc(c.name) + '</button>';
        }).join('') + '</div></div>' : '') +
        (bad && !p.category_id ? '<p class="wz-err is-soft">' + VQ.t('You can continue without it, but the category is required when you send the product for approval.') + '</p>' : ''));
    }
    var l = locById(p.location_id), groups = l ? (l.display_categories || []) : [];
    if (groups.length) {
      h += block(VQ.t('The group on the venue page'), VQ.t('On the page "{name}", the tickets sit in the groups you made at the venue. It is only your order there; it changes nothing else on the site.', { name: esc(l.name) }),
        '<div class="wz-chips">' + [[null, VQ.t('No group')]].concat(groups.map(function (g) { return [g.id, g.name]; })).map(function (g) {
          return '<button type="button" class="wz-chip" data-act="set" data-path="display_category" data-val="' + jv(g[0]) + '" aria-pressed="' + (p.display_category === g[0]) + '">' + esc(g[1]) + '</button>';
        }).join('') + '</div>', { opt: true });
    }
    return h;
  }

  /* =================== step: name =================== */
  function stNume() {
    var p = W.p, t = p.product_type, bad = tried('nume') && !p.title, K = kit(), eg = K.titles[t] || KIT_BASE.titles[t];
    var h = head('nume', VQ.t('What is it called?'), VQ.t('Write as you would to a friend: what they get and where. The title appears everywhere; the rest is optional, but helps the customer choose.'));
    var icons = W.allIcons ? null : K.icons.slice(0);
    if (icons && p.icon && icons.indexOf(p.icon) < 0) icons.push(p.icon);
    var iconHtml;
    if (icons) {
      iconHtml = '<div class="wz-icons">' + icons.map(function (k) {
        return '<button type="button" data-act="set" data-path="icon" data-val="' + jv(k) + '" aria-pressed="' + (p.icon === k) + '" aria-label="' + esc(ICON[k][1]) + '" title="' + esc(ICON[k][1]) + '">' + pic(k) + '</button>';
      }).join('') + '<button type="button" class="is-text" data-act="set" data-path="icon" data-val="null" aria-pressed="' + (!p.icon) + '">' + VQ.t('None') + '</button><button type="button" class="is-text" data-act="allicons">' + VQ.t('All') + '</button></div>';
    } else {
      var byGroup = {}, order = [];
      ICONS.forEach(function (x) { if (!byGroup[x[2]]) { byGroup[x[2]] = []; order.push(x[2]); } byGroup[x[2]].push(x); });
      iconHtml = '<div class="wz-icon-groups">' + order.map(function (g) {
        return '<div><span class="wz-icon-g">' + esc(g) + '</span><div class="wz-icons">' + byGroup[g].map(function (x) {
          return '<button type="button" data-act="set" data-path="icon" data-val="' + jv(x[0]) + '" aria-pressed="' + (p.icon === x[0]) + '" aria-label="' + esc(x[1]) + '" title="' + esc(x[1]) + '">' + pic(x[0]) + '</button>';
        }).join('') + '</div></div>';
      }).join('') + '<div class="wz-icons"><button type="button" class="is-text" data-act="set" data-path="icon" data-val="null" aria-pressed="' + (!p.icon) + '">' + VQ.t('No icon') + '</button><button type="button" class="is-text" data-act="fewicons">' + VQ.t('Fewer') + '</button></div></div>';
    }
    h += block(null, null,
      field(VQ.t('Title'), inp('title', { max: 190, ph: eg[0], big: true }), { req: true, for: 'wz-title', bad: bad, err: bad ? VQ.t('The title is the one thing we cannot save without.') : null, count: ['title', 190] }) +
      '<div class="wz-sugg"><span>' + VQ.t('Ideas:') + '</span>' + eg.map(function (e) { return '<button type="button" data-act="set" data-path="title" data-val="' + jv(e) + '">' + esc(e) + '</button>'; }).join('') + '</div>' +
      '<div class="wz-fl"><span class="wz-lbl">' + VQ.t('Icon') + ' <small>' + VQ.t('appears next to the title, in the list of tickets') + '</small></span>' + iconHtml + '</div>');
    h += block(VQ.t('In short'), VQ.t('What the customer sees before opening the product. Two good sentences do more than a page.'),
      field(VQ.t('Subtitle'), inp('subtitle', { max: 190, ph: t === 'experience' ? VQ.t('For the whole family') : VQ.t('Open all year') }), { opt: true, for: 'wz-subtitle' }) +
      field(VQ.t('Short description'), ta('short_description', { max: 280, rows: 2, ph: K.ph.short[t] }), { opt: true, for: 'wz-short_description', count: ['short_description', 280], hint: VQ.t('It appears under the title, in the list of tickets of the venue.') }));
    h += block(VQ.t('The full story'), VQ.t('For the product page: what the customer does, how long it takes, what they see. Short paragraphs.'), mount('description'), { opt: true });
    return h;
  }

  /* =================== step: tickets =================== */
  function varCard(v, i) {
    var p = W.p, t = p.product_type, day = p.booking_mode === 'day', base = 'variants.' + i, K = kit();
    var subt = [v.price_type === 'per_unit' ? (v.persons_max ? VQ.t('per unit, up to {n} people', { n: v.persons_max }) : VQ.t('per unit')) : VQ.t('per person'), v.is_child ? VQ.t('child') : '', !v.is_active ? VQ.t('not on sale') : '', v.pos_only ? VQ.t('counter only') : ''].filter(Boolean).join(' · ');
    var bn = tried('bilete') && !v.name, bp = tried('bilete') && v.price == null;
    var h = '<div class="wz-var' + (v._open ? ' is-open' : '') + (bn || bp ? ' is-bad' : '') + '">' +
      '<button type="button" class="wz-var-h" data-act="vopen" data-i="' + i + '" aria-expanded="' + !!v._open + '"><span class="wz-var-n">' + (i + 1) + '</span><span class="wz-var-t"><b data-live="' + base + '.name">' + esc(v.name || VQ.t('Unnamed ticket')) + '</b><small>' + esc(subt) + '</small></span><span class="wz-var-p" data-live-price="' + i + '">' + (v.price != null ? esc(lei(v.price)) : '<span class="wz-ph">' + VQ.t('price?') + '</span>') + '</span>' + ic('caret-down') + '</button>';
    if (v._open) {
      h += '<div class="wz-var-b"><div class="wz-g2">' +
        field(VQ.t('Ticket name'), inp(base + '.name', { max: 120, ph: ((K.vt[t] || [])[0] || [VQ.t('Adult')])[0] }), { req: true, for: idOf(base + '.name'), bad: bn }) +
        field(VQ.t('Price'), money(base + '.price', { ph: '50' }), { req: true, for: idOf(base + '.price'), bad: bp, hint: VQ.t('What you receive. The Viaqui commission is added on top, for the customer.') }) + '</div>' +
        '<div class="wz-fl"><span class="wz-lbl">' + VQ.t('How it is sold') + '</span>' + seg(base + '.price_type', [['per_person', VQ.t('Per person')], ['per_unit', VQ.t('Per unit')]]) +
        '<div class="wz-xp"><div class="' + (v.price_type !== 'per_unit' ? 'is-on' : '') + '"><b>' + VQ.t('Per person') + '</b><span class="wz-peeps"><i></i><i></i><i></i><i></i> = <em>×4</em></span>' + VQ.t('4 people buy 4 tickets.') + '</div>' +
        '<div class="' + (v.price_type === 'per_unit' ? 'is-on' : '') + '"><b>' + VQ.t('Per unit') + '</b><span class="wz-peeps"><i></i><i></i><i></i><i></i> = <em>×1</em></span>' + esc(K.perUnit) + '</div></div></div>' +
        (v.price_type === 'per_unit' ? field(VQ.t('How many people fit'), stepper(base + '.persons_max', { min: 1, max: 500, ph: VQ.t('any number'), label: VQ.t('People'), u: VQ.t('people'), start: 4 }), { hint: esc(K.personsHint) }) : '') +
        (t === 'access' ? toggle(base + '.is_child', VQ.t('It is a child ticket'), VQ.t('We place it next to the adult ticket and count it separately in reports.')) : '') +
        '<details class="wz-more" data-more="v' + i + '"' + (W.openMore['v' + i] ? ' open' : '') + '><summary>' + ic('caret-down') + VQ.t('More for this ticket') + '<small>' + VQ.t('limits, counter, refunds') + '</small></summary><div class="wz-more-b"><div class="wz-g2">' +
        (day ? field(VQ.t('Valid for how many days'), stepper(base + '.validity_days', { min: 1, max: 60, label: VQ.t('Days'), u: VQ.t('days'), start: 1 }), { hint: VQ.t('A 3-day pass: 3.') }) : '') +
        (!day ? field(VQ.t('Duration of this ticket'), stepper(base + '.duration_minutes', { min: 5, max: 1440, step: 5, ph: VQ.t('same as the product'), label: VQ.t('Minutes'), u: VQ.t('min'), start: p.duration_minutes || 60 }), { hint: VQ.t('Only if it differs from the duration of the product.') }) : '') +
        field(VQ.t('Price at the counter'), money(base + '.pos_price', { ph: VQ.t('same as online') }), { opt: true, hint: VQ.t('If you charge something else at the ticket office.') }) +
        field(VQ.t('Minimum per order'), stepper(base + '.min_per_order', { min: 0, max: 500, label: VQ.t('Minimum'), start: 1 }), { hint: VQ.t('For a group ticket: 8.') }) +
        field(VQ.t('Maximum per order'), stepper(base + '.max_per_order', { min: 1, max: 500, label: VQ.t('Maximum'), start: 20 })) +
        field(VQ.t('Added in steps of'), stepper(base + '.step_qty', { min: 1, max: 100, ph: '1', label: VQ.t('Step'), start: 1 }), { hint: VQ.t('For tickets sold only in pairs: 2.') }) +
        field(VQ.t('Free companion'), inp(base + '.companion_label', { max: 80, ph: VQ.t('Companion') }), { opt: true, hint: VQ.t('One extra free ticket per order, for example for the teacher of a group.') }) +
        '</div>' +
        field(VQ.t('Short description of the ticket'), inp(base + '.description', { max: 280, ph: v.is_child ? VQ.t('What ages or heights it is for') : VQ.t('Who can use it') }), { opt: true }) +
        toggle(base + '.is_active', VQ.t('On sale'), VQ.t('Stop it without deleting it; tickets already sold stay valid.')) +
        toggle(base + '.is_refundable', VQ.t('Refundable'), VQ.t('The customer can ask for the money back, under the cancellation rules.')) +
        toggle(base + '.pos_only', VQ.t('Counter only'), VQ.t('It does not appear online; you sell it only at the counter (POS).')) +
        '</div></details>' +
        (p.variants.length > 1 ? '<div class="wz-var-foot"><button type="button" class="ve-danger wz-del" data-act="vdel" data-i="' + i + '">' + ic('trash') + '<span>' + VQ.t('Remove the ticket') + '</span></button></div>' : '') +
        '</div>';
    }
    return h + '</div>';
  }
  function stBilete() {
    var p = W.p, t = p.product_type, K = kit();
    var h = head('bilete', t === 'experience' ? VQ.t('What options does it have?') : VQ.t('What tickets do you sell?'),
      t === 'experience' ? VQ.t('One option for each way of buying: per person, for a whole group or for different durations.') : VQ.t('One ticket for each kind of customer: adult, child, student, group. They all appear together and the customer puts them in the same basket.'));
    var names = p.variants.map(function (v) { return fold(v.name); });
    var tpl = (K.vt[t] || []).filter(function (x) { return names.indexOf(fold(x[0])) < 0; });
    h += '<div class="wz-vars">' + p.variants.map(varCard).join('') + '</div>';
    if (p.variants.length < 30) {
      h += '<div class="wz-fl"><span class="wz-lbl">' + VQ.t('Quick add') + '</span><div class="wz-chips">' + tpl.map(function (x) {
        return '<button type="button" class="wz-chip is-add" data-act="vadd" data-val="' + jv(x[0]) + '">' + ic('plus') + esc(x[0]) + '</button>';
      }).join('') + '<button type="button" class="wz-chip is-add" data-act="vadd" data-val="null">' + ic('plus') + VQ.t('Another ticket') + '</button></div></div>';
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
    var k2 = '<span class="wz-k2">' + VQ.t('What the customer sees') + '</span>';
    if (p.booking_mode === 'day') {
      return k2 + (w ? VQ.t('They choose <b>the day</b>; the ticket is valid between <b>{from}</b> and <b>{to}</b>.', { from: esc(w[0]), to: esc(w[1]) }) : VQ.t('They choose <b>the day</b>; the ticket is valid during the opening hours of the day.')) + ' ' +
        (p.daily_capacity ? VQ.t('At most <b>{n}</b> tickets are sold per day.', { n: esc(p.daily_capacity) }) : VQ.t('There is no limit of tickets per day.'));
    }
    var sl = slotList();
    var cap = p.capacity_mode === 'concurrent'
      ? VQ.t('You can have <b>{n}</b> bookings at the same time ({units}); a booking from {time} frees its place after <b>{duration}</b>.', { n: esc(p.capacity_per_slot || '?'), units: esc(kit().unit.many), time: esc(sl[0] || '10:00'), duration: esc(durTxt(p.duration_minutes)) })
      : VQ.t('Each start time has <b>{n}</b> seats.', { n: esc(p.capacity_per_slot || '?') });
    return k2 + VQ.t('They choose <b>the day and the time</b>. It lasts <b>{duration}</b>.', { duration: esc(durTxt(p.duration_minutes) || '?') }) + ' ' + cap +
      (sl.length ? '<div class="wz-slots">' + sl.slice(0, 14).map(function (s, i) { return '<span style="animation-delay:' + (i * 30) + 'ms">' + s + '</span>'; }).join('') + (sl.length > 14 ? '<span>+' + (sl.length - 14) + '</span>' : '') + '</div>'
        : (w ? '' : '<div class="wz-slots"><span>' + VQ.t('The times appear once you set the opening hours, below.') + '</span></div>'));
  }
  function mdSel(path) {
    var v = getP(path) || '01-01', mm = +v.split('-')[0], dd = +v.split('-')[1];
    var d = '<span class="wz-sel-w is-sm"><select class="wz-inp wz-sel" data-md="' + path + '" data-part="d" aria-label="' + VQ.t('Day') + '">' + Array.from({ length: 31 }, function (_, i) { return '<option value="' + (i + 1) + '"' + (i + 1 === dd ? ' selected' : '') + '>' + (i + 1) + '</option>'; }).join('') + '</select>' + ic('caret-down') + '</span>';
    var m = '<span class="wz-sel-w is-sm"><select class="wz-inp wz-sel" data-md="' + path + '" data-part="m" aria-label="' + VQ.t('Month') + '">' + MONTHS.map(function (x, i) { return '<option value="' + (i + 1) + '"' + (i + 1 === mm ? ' selected' : '') + '>' + esc(x) + '</option>'; }).join('') + '</select>' + ic('caret-down') + '</span>';
    return d + m;
  }
  function periodHtml(per, pi) {
    var p = W.p, b = 'periods.' + pi;
    return '<div class="wz-period"><div class="wz-period-h"><b>' + (p.periods.length > 1 ? VQ.t('Period {n}', { n: pi + 1 }) : VQ.t('Opening hours of the product')) + '</b>' +
      (per.season ? '' : '<span class="wz-pill is-g">' + VQ.t('all year') + '</span>') + '<span class="wz-sp"></span>' +
      (p.periods.length > 1 || p.booking_mode === 'day' ? '<button type="button" class="ve-danger wz-del" data-act="perdel" data-i="' + pi + '">' + ic('trash') + '<span>' + VQ.t('Delete') + '</span></button>' : '') + '</div>' +
      '<div class="wz-week">' + DAYS.map(function (x) {
        var d = per.days[x[0]];
        return '<div class="wz-day' + (d.on ? ' is-on' : '') + '"><button type="button" class="wz-day-t" data-act="day" data-pi="' + pi + '" data-d="' + x[0] + '" aria-pressed="' + d.on + '"><span class="wz-box">' + ic('check') + '</span>' + esc(x[1]) + '</button>' +
          (d.on ? '<div class="wz-day-hs">' + d.slots.map(function (s, si) {
            var sb = b + '.days.' + x[0] + '.slots.' + si;
            var badT = s.open && s.close && s.open >= s.close;
            return '<div class="wz-day-h' + (badT ? ' is-bad' : '') + '"><input class="wz-inp" type="time" data-bind="' + sb + '.open" data-t="text" value="' + esc(s.open) + '" aria-label="' + esc(VQ.t('{day} from', { day: x[1] })) + '"><span>–</span><input class="wz-inp" type="time" data-bind="' + sb + '.close" data-t="text" value="' + esc(s.close) + '" aria-label="' + esc(VQ.t('{day} until', { day: x[1] })) + '">' +
              (si > 0 ? '<button type="button" class="ve-icon-btn" data-act="slotdel" data-pi="' + pi + '" data-d="' + x[0] + '" data-si="' + si + '" aria-label="' + VQ.t('Remove the interval') + '">' + ic('x') + '</button>' : '') + '</div>';
          }).join('') + (d.slots.length < 3 ? '<button type="button" class="wz-link is-sm" data-act="slotadd" data-pi="' + pi + '" data-d="' + x[0] + '">' + VQ.t('+ one more interval (for example after the lunch break)') + '</button>' : '') + '</div>'
            : '<span class="wz-closed">' + VQ.t('Closed') + '</span>') + '</div>';
      }).join('') + '</div>' +
      '<div class="wz-chips"><button type="button" class="wz-chip is-ghost" data-act="copymon" data-i="' + pi + '">' + ic('copy') + VQ.t('Monday hours on every day') + '</button></div>' +
      (per.season
        ? '<div class="wz-fl"><span class="wz-lbl">' + VQ.t('Only in season') + '</span><div class="wz-season">' + VQ.t('from {start} until {end}', { start: mdSel(b + '.season.start'), end: mdSel(b + '.season.end') }) + '<button type="button" class="ve-danger wz-del" data-act="noseason" data-i="' + pi + '">' + ic('x') + '<span>' + VQ.t('All year') + '</span></button></div></div>'
        : '<button type="button" class="wz-link" data-act="season" data-i="' + pi + '">' + VQ.t('+ Only in one season (for example May to September)') + '</button>') +
      '</div>';
  }
  function stCand() {
    var p = W.p, l = locById(p.location_id), hasSeasons = !!(l && l.seasons && l.seasons.length), K = kit();
    var h = head('cand', VQ.t('When can it be used?'), VQ.t('Choose how the customer books, then how many seats you have. You see at once what they will see.'));
    h += block(VQ.t('How the customer books'), null,
      '<div class="wz-cards is-2">' +
      '<button type="button" class="wz-card" data-act="mode" data-val="&quot;day&quot;" aria-pressed="' + (p.booking_mode === 'day') + '"><span class="wz-m-art"><svg viewBox="0 0 120 74" aria-hidden="true" focusable="false"><path d="M10 70a50 50 0 01100 0" fill="none" stroke="#C9CEC6" stroke-width="2" stroke-dasharray="4 5"/><g class="wz-sun"><circle cx="60" cy="24" r="9" fill="#F2A900"/></g><rect x="0" y="70" width="120" height="4" fill="#1B7F4E" opacity=".4"/></svg></span><b>' + VQ.t('All day') + '</b><small>' + VQ.t('They choose only the day and come any time it is open. Good for entry tickets, passes, parking.') + '</small></button>' +
      '<button type="button" class="wz-card" data-act="mode" data-val="&quot;slot&quot;" aria-pressed="' + (p.booking_mode === 'slot') + '"><span class="wz-m-art"><svg viewBox="0 0 120 74" aria-hidden="true" focusable="false"><circle cx="60" cy="37" r="26" fill="#fff" stroke="#1B7F4E" stroke-width="3"/><g stroke="#C9CEC6" stroke-width="2"><path d="M60 15v5M60 54v5M38 37h5M77 37h5"/></g><path class="wz-hand" d="M60 37V20" stroke="#E43A33" stroke-width="3" stroke-linecap="round"/><path d="M60 37l9 6" stroke="#212121" stroke-width="3" stroke-linecap="round"/><circle cx="60" cy="37" r="3" fill="#212121"/></svg></span><b>' + VQ.t('At a fixed time') + '</b><small>' + VQ.t('They choose the day and the start time. Good for tours, workshops, games, rentals.') + '</small></button>' +
      '</div>');
    if (p.booking_mode === 'day') {
      h += block(VQ.t('How many tickets per day'), VQ.t('Empty if you have no limit. When they run out, the day shows "Sold out" in the calendar.'),
        '<div class="wz-g2">' + field(null, stepper('daily_capacity', { min: 1, max: 1000000, step: 10, ph: VQ.t('no limit'), label: VQ.t('Seats per day'), start: 100 })) + '</div>', { opt: true });
    } else {
      h += block(VQ.t('How long it lasts and how often it starts'), null,
        '<div class="wz-g2 is-keep">' +
        field(VQ.t('Duration'), stepper('duration_minutes', { min: 5, max: 1440, step: 5, re: true, label: VQ.t('Duration'), u: VQ.t('min'), start: 60 }), { req: true, hint: esc(durTxt(p.duration_minutes)) }) +
        field(VQ.t('One start every'), stepper('slot_interval_minutes', { min: 5, max: 1440, step: 5, re: true, label: VQ.t('Interval'), u: VQ.t('min'), start: 60 }), { req: true, hint: slotList().length ? VQ.t('Start times: {times}…', { times: esc(slotList().slice(0, 3).join(', ')) }) : '' }) +
        '</div>');
      h += block(VQ.t('How seats are counted'), null,
        '<div class="wz-cards is-2">' +
        '<button type="button" class="wz-card" data-act="set" data-path="capacity_mode" data-val="&quot;per_slot&quot;" aria-pressed="' + (p.capacity_mode !== 'concurrent') + '"><span class="wz-hours" aria-hidden="true">' + [70, 90, 60, 100, 80].map(function (x, i) { return '<i style="height:' + x + '%;animation-delay:' + i * 80 + 'ms"></i>'; }).join('') + '</span><b>' + VQ.t('Seats at each start time') + '</b><small>' + VQ.t('A tour with 20 seats at 10:00 and another 20 at 12:00. Each start time has its own seats.') + '</small></button>' +
        '<button type="button" class="wz-card" data-act="set" data-path="capacity_mode" data-val="&quot;concurrent&quot;" aria-pressed="' + (p.capacity_mode === 'concurrent') + '"><span class="wz-lanes" aria-hidden="true"><i></i><i></i><i></i></span><b>' + VQ.t('Units at the same time') + '</b><small>' + esc(K.unit.eg) + '</small></button>' +
        '</div>' +
        '<div class="wz-g2">' + field(p.capacity_mode === 'concurrent' ? VQ.t('How many {units} you have', { units: esc(K.unit.many) }) : VQ.t('Seats at each start time'), stepper('capacity_per_slot', { min: 1, max: 10000, label: VQ.t('Capacity'), u: p.capacity_mode === 'concurrent' ? VQ.t('units') : VQ.t('seats'), start: 10 }), { req: true }) + '</div>');
    }
    h += '<div class="wz-sentence" id="wz-when" aria-live="polite">' + whenSentence() + '</div>';

    var sch = '';
    if (hasSeasons) {
      sch += toggle('use_location_schedule', VQ.t('Use the opening hours of the venue'), VQ.t('The seasons and closed days of the venue "{name}". You change them once, at the venue, and they apply to every product that uses them.', { name: esc(l.name) }));
      if (p.use_location_schedule) {
        sch += '<div class="wz-loc-sum">' + locSeasonsText(l).map(function (s) { return '<div><b>' + esc(s.label) + '</b> · ' + esc(s.rows.join(', ')) + '</div>'; }).join('') + '</div>';
      }
    } else if (l) {
      sch += '<p class="wz-hint">' + VQ.t('The venue "{name}" has no opening hours by season yet. You can set them at the venue, for all products, or here, only for this one.', { name: esc(l.name) }) + '</p>';
    }
    if (!p.use_location_schedule) {
      if (!p.periods.length) {
        sch += p.booking_mode === 'day'
          ? '<div class="wz-alert">' + ic('info') + '<span>' + VQ.t('Without its own opening hours, the ticket can be used on any day.') + '</span></div><button type="button" class="wz-add" data-act="peradd">' + ic('calendar-blank') + VQ.t('Set the days and hours when it can be used') + '</button>'
          : '<button type="button" class="wz-add" data-act="peradd">' + ic('calendar-blank') + VQ.t('Set the opening hours in which the start times can be booked') + '</button>';
      } else {
        sch += p.periods.map(periodHtml).join('');
        sch += '<button type="button" class="wz-add" data-act="peradd">' + ic('plus') + VQ.t('Other opening hours for another period (winter, summer)') + '</button>';
      }
    }
    var schErr = tried('cand') ? problemsOf('cand', true).filter(function (x) { return x.sched; }) : [];
    h += block(VQ.t('Opening hours'), p.booking_mode === 'slot' ? VQ.t('The hours between which bookings start, on each day.') : VQ.t('The days and hours when the ticket can be used.'),
      sch + schErr.map(function (x) { return '<p class="wz-err">' + esc(x.msg) + '</p>'; }).join(''));

    h += block(VQ.t('Special days'), VQ.t('A day that is closed or has different hours than usual, only for this product.'),
      p.exceptions.map(function (x, i) {
        var b = 'exceptions.' + i;
        return '<div class="wz-row"><div class="wz-row-h"><b>' + (x.date ? esc(x.date.split('-').reverse().join('.')) : VQ.t('New day')) + '</b><button type="button" class="ve-icon-btn" data-act="exdel" data-i="' + i + '" aria-label="' + VQ.t('Delete the special day') + '">' + ic('trash') + '</button></div>' +
          '<div class="wz-g2">' + field(VQ.t('Date'), '<input class="wz-inp" type="date" id="' + idOf(b + '.date') + '" data-bind="' + b + '.date" data-t="text" data-re="1" value="' + esc(x.date) + '">', { for: idOf(b + '.date') }) + field(VQ.t('Reason'), inp(b + '.reason', { max: 190, ph: VQ.t('Public holiday') }), { opt: true }) + '</div>' +
          seg(b + '.is_closed', [[true, VQ.t('Closed')], [false, VQ.t('Different hours')]]) +
          (!x.is_closed ? '<div class="wz-day-h"><input class="wz-inp" type="time" data-bind="' + b + '.open" data-t="text" value="' + esc(x.open) + '" aria-label="' + VQ.t('From') + '"><span>–</span><input class="wz-inp" type="time" data-bind="' + b + '.close" data-t="text" value="' + esc(x.close) + '" aria-label="' + VQ.t('Until') + '"></div>' : '') + '</div>';
      }).join('') + (p.exceptions.length < 200 ? '<button type="button" class="wz-add" data-act="exadd">' + ic('calendar-blank') + VQ.t('Add a special day') + '</button>' : ''), { opt: true });

    h += block(VQ.t('When it is sold'), null, '<div class="wz-g2">' +
      field(VQ.t('Online sales stop'), stepper('booking_lead_time_hours', { min: 0, max: 720, label: VQ.t('Hours before'), u: VQ.t('hours before'), start: 0 }), { hint: VQ.t('0 = it can be bought up to the last moment.') }) +
      field(VQ.t('It can be booked at most'), stepper('booking_max_advance_days', { min: 1, max: 365, step: 7, ph: VQ.t('same as the venue'), label: VQ.t('Days'), u: VQ.t('days before'), start: 30 }), { hint: VQ.t('Empty = as far ahead as the venue allows.') }) + '</div>', { opt: true });
    return h;
  }

  /* =================== step: add-ons =================== */
  function stExtra() {
    var p = W.p;
    var h = head('extra', VQ.t('Do you want to offer something extra?'), VQ.t('Add-ons are chosen with each ticket: a photo, a locker, extra time. You can skip this step; it is not required.'));
    h += p.addons.map(function (a, i) {
      var b = 'addons.' + i, mx = a.max_per_unit == null ? 1 : a.max_per_unit, tot = (a.included_qty || 0) + mx;
      return '<div class="wz-row is-card"><div class="wz-row-h"><b>' + esc(a.name || VQ.t('New add-on')) + '</b><button type="button" class="ve-icon-btn" data-act="addel" data-i="' + i + '" aria-label="' + VQ.t('Delete the add-on') + '">' + ic('trash') + '</button></div>' +
        '<div class="wz-g2">' + field(VQ.t('Name'), inp(b + '.name', { max: 120, ph: kit().addons[0] }), { req: true }) + field(VQ.t('Price'), money(b + '.price', { ph: '0' })) +
        field(VQ.t('Included free with one ticket'), stepper(b + '.included_qty', { min: 0, max: 50, re: true, label: VQ.t('Included'), start: 0 })) +
        field(VQ.t('Maximum paid with one ticket'), stepper(b + '.max_per_unit', { min: 0, max: 50, re: true, label: VQ.t('Maximum paid'), start: 1 })) + '</div>' +
        '<div class="wz-sentence is-sm"><span class="wz-k2">' + VQ.t('With each ticket') + '</span>' + (a.included_qty
          ? (a.price ? VQ.t('<b>{free}</b> free + up to <b>{max}</b> paid ({price} each) = at most <b>{total}</b>. The page tells the customer the limit.', { free: a.included_qty, max: mx, price: esc(lei(a.price)), total: tot }) : VQ.t('<b>{free}</b> free + up to <b>{max}</b> paid = at most <b>{total}</b>. The page tells the customer the limit.', { free: a.included_qty, max: mx, total: tot }))
          : (a.price ? VQ.t('Up to <b>{max}</b> paid ({price} each) = at most <b>{total}</b>. The page tells the customer the limit.', { max: mx, price: esc(lei(a.price)), total: tot }) : VQ.t('Up to <b>{max}</b> paid = at most <b>{total}</b>. The page tells the customer the limit.', { max: mx, total: tot }))) + '</div>' +
        toggle(b + '.is_active', VQ.t('On sale'), null) + '</div>';
    }).join('');
    if (p.addons.length < 20) {
      var left = kit().addons.filter(function (s) { return !p.addons.some(function (a) { return a.name === s; }); });
      h += '<div class="wz-fl"><span class="wz-lbl">' + VQ.t('Add an add-on') + '</span><div class="wz-chips">' + left.map(function (s) { return '<button type="button" class="wz-chip is-add" data-act="adadd" data-val="' + jv(s) + '">' + ic('plus') + esc(s) + '</button>'; }).join('') +
        '<button type="button" class="wz-chip is-add" data-act="adadd" data-val="null">' + ic('plus') + VQ.t('Another one') + '</button></div></div>';
    }
    if (!p.addons.length) h += '<p class="wz-hint">' + VQ.t('Without add-ons, the page shows only the tickets. You can add some any time later.') + '</p>';
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
    var h = head('continut', VQ.t('What goes in the package?'), VQ.t('Choose from the products you already have. On purchase, the package issues one ticket for each part; timed experiences get their time chosen at booking.'));
    h += '<div class="wz-pk-items">' + p.package_items.map(function (it, i) {
      var pr = products.filter(function (x) { return x.id === it.product_id; })[0], d = details[it.product_id], b = 'package_items.' + i;
      var title = (pr && pr.title) || it._title || VQ.t('Product {id}', { id: it.product_id });
      var vs = d ? (d.variants || []).filter(function (v) { return v.is_active || v.id === it.variant_id; }) : null;
      return '<div class="wz-pk"><span class="wz-pk-ic">' + pic(pr ? TYPE_ICON[pr.type] : 'ticket') + '</span><div class="wz-pk-b"><div class="wz-pk-top"><div><b>' + esc(title) + '</b><small>' + esc(pr ? TYPES[pr.type] : '') + '</small></div><button type="button" class="ve-icon-btn" data-act="pkdel" data-i="' + i + '" aria-label="' + VQ.t('Remove from the package') + '">' + ic('x') + '</button></div>' +
        '<div class="wz-pk-row">' + (vs ? field(VQ.t('Ticket'), sel(b + '.variant_id', [[null, VQ.t('Choose the ticket')]].concat(vs.map(function (v) { return [v.id, (v.name || VQ.t('Ticket')) + ' · ' + lei(v.price)]; })), { re: true }), { bad: tried('continut') && !it.variant_id }) : '<p class="wz-hint">' + VQ.t('Loading the tickets…') + '</p>') +
        field(VQ.t('How many'), stepper(b + '.quantity', { min: 1, max: 50, re: true, label: VQ.t('Quantity'), start: 1 })) + '</div></div></div>';
    }).join('') + '</div>';
    var avail = componentChoices();
    h += block(VQ.t('Your products'), avail.length ? (p.package_items.length ? VQ.t('You can add the same product several times, with different tickets (for example 2 adults + 2 children).') : VQ.t('Press a product to put it in the package.')) : (p.location_id ? VQ.t('You have no access tickets or experiences at this venue yet. Add one first, then make the package.') : VQ.t('You have no access tickets or experiences yet. Add one first, then make the package.')),
      '<div class="wz-shelf">' + avail.map(function (x) {
        return '<button type="button" data-act="pkadd" data-val="' + jv(x.id) + '"' + (p.package_items.length >= 20 ? ' disabled' : '') + '><span class="wz-shelf-ic">' + pic(TYPE_ICON[x.type]) + '</span><b>' + esc(x.title || VQ.t('Product')) + '</b><small>' + (x.min_price != null ? VQ.t('from {price}', { price: esc(lei(x.min_price)) }) : '') + '</small>' + ic('plus') + '</button>';
      }).join('') + '</div>');
    var t = pkgTotals();
    if (t.known) h += '<div class="wz-sentence"><span class="wz-k2">' + VQ.t('Separately, the customer would pay') + '</span>' + VQ.t('<b class="is-big">{amount}</b>. At the next step you set the price of the package.', { amount: esc(lei(t.total)) }) + '</div>';
    return h;
  }
  function stPret() {
    var p = W.p, v = p.variants[0], t = pkgTotals(), price = v.price, save = t.known && price != null ? t.total - price : null;
    var h = head('pret', VQ.t('How much does the package cost?'), VQ.t('One price for everything. A good package is a little cheaper than the tickets bought separately.'));
    h += block(null, null, field(VQ.t('Package price'), money('variants.0.price', { ph: t.known ? String(Math.round(t.total * 0.9)) : '0', big: true }), { req: true, for: 'wz-variants-0-price', bad: tried('pret') && price == null, hint: VQ.t('What you receive. The Viaqui commission is added on top, for the customer.') }) +
      (t.known ? '<div class="wz-sugg"><span>' + VQ.t('Quick discount:') + '</span>' + [5, 10, 15, 20].map(function (pc) { var val = Math.round(t.total * (1 - pc / 100)); return '<button type="button" data-act="set" data-path="variants.0.price" data-val="' + val + '">−' + pc + '% · ' + esc(lei(val)) + '</button>'; }).join('') + '</div>' : ''));
    if (t.known) {
      var mx = Math.max(t.total, price || 0) || 1;
      h += '<div class="wz-save" aria-live="polite"><span class="wz-k2">' + (save != null && save < 0 ? VQ.t('Careful') : VQ.t('The customer saves')) + '</span>' +
        '<span class="wz-big' + (save != null && save < 0 ? ' is-bad' : '') + '" id="wz-save-big">' + (save == null ? '—' : esc(save < 0 ? VQ.t('{amount} more expensive', { amount: lei(-save) }) : lei(save))) + '</span>' +
        '<div class="wz-bars"><div>' + VQ.t('Separately') + '<span><i style="width:' + (t.total / mx * 100) + '%"></i></span><b>' + esc(lei(t.total)) + '</b></div><div>' + VQ.t('With the package') + '<span><i id="wz-bar-pk" style="width:' + ((price || 0) / mx * 100) + '%"></i></span><b id="wz-bar-pk-t">' + (price != null ? esc(lei(price)) : '—') + '</b></div></div></div>';
      h += block(VQ.t('How the revenue is split'), VQ.t('For reports and payouts: how much of the price goes to each part. Empty = we split it automatically, in proportion to the separate prices.'),
        '<div class="wz-alloc">' + p.package_items.map(function (it, i) {
          var pr = products.filter(function (x) { return x.id === it.product_id; })[0], vv = variantOf(it);
          var auto = vv && price != null && t.total ? Math.round(price * (vv.price * (it.quantity || 1)) / t.total * 100) / 100 : null;
          return '<div class="wz-alloc-r"><div><b>' + esc((pr && pr.title) || it._title || '') + '</b><small>' + esc(vv ? vv.name : '') + ' × ' + (it.quantity || 1) + '</small></div>' + money('package_items.' + i + '.allocated_price', { ph: auto != null ? VQ.t('{amount} (auto)', { amount: String(auto) }) : VQ.t('automatic') }) + '</div>';
        }).join('') + '</div>', { opt: true });
    }
    h += '<details class="wz-more" data-more="pk"' + (W.openMore.pk ? ' open' : '') + '><summary>' + ic('caret-down') + VQ.t('More for the package') + '<small>' + VQ.t('limits, counter, refunds') + '</small></summary><div class="wz-more-b"><div class="wz-g2">' +
      field(VQ.t('Minimum per order'), stepper('variants.0.min_per_order', { min: 0, max: 500, label: VQ.t('Minimum'), start: 1 })) +
      field(VQ.t('Maximum per order'), stepper('variants.0.max_per_order', { min: 1, max: 500, label: VQ.t('Maximum'), start: 20 })) +
      field(VQ.t('Price at the counter'), money('variants.0.pos_price', { ph: VQ.t('same as online') }), { opt: true }) +
      field(VQ.t('Free companion'), inp('variants.0.companion_label', { max: 80, ph: VQ.t('Companion') }), { opt: true }) + '</div>' +
      toggle('variants.0.is_active', VQ.t('On sale'), VQ.t('Stop the package without deleting it.')) + toggle('variants.0.is_refundable', VQ.t('Refundable'), null) + toggle('variants.0.pos_only', VQ.t('Counter only'), null) + '</div></details>';
    return h;
  }

  /* =================== step: what to know =================== */
  function langBlock(p, K) {
    var chips = '<div class="wz-chips">' + LANGS.map(function (l) {
      return '<button type="button" class="wz-chip" data-act="lang" data-val="' + jv(l[0]) + '" aria-pressed="' + (p.languages.indexOf(l[0]) >= 0) + '">' + esc(l[1]) + '</button>';
    }).join('') + '</div>';
    // Languages matter when someone speaks to the client (a tour, a workshop, a game master).
    var talks = K.langs || p.service_type === 'guided' || p.service_type === 'workshop' || p.languages.length;
    if (talks) return '<div class="wz-fl"><span class="wz-lbl">' + VQ.t('Languages spoken') + ' <small>' + VQ.t('optional') + '</small></span>' + chips + '</div>';
    return '<details class="wz-more" data-more="lang"' + (W.openMore.lang ? ' open' : '') + '><summary>' + ic('caret-down') + VQ.t('Languages spoken') + '<small>' + VQ.t('only if someone explains things') + '</small></summary><div class="wz-more-b">' + chips + '</div></details>';
  }
  function stInfo() {
    var p = W.p, t = p.product_type, v = p.variants[0], K = kit();
    var h = head('info', VQ.t('What does the customer need to know?'), VQ.t('Answer now the questions you would otherwise get on the phone. Everything here appears on the product page.'));
    var unitEx = (v && v.price != null ? lei(v.price) : lei(50)) + ' / ' + (p.unit_label || K.ph.unit[t]);
    h += block(VQ.t('The price, made clear'), null, field(VQ.t('Price unit'), inp('unit_label', { max: 60, ph: K.ph.unit[t] }), { opt: true, hint: VQ.t('It appears after the price. Now: {example}', { example: '<b id="wz-unit-ex">' + esc(unitEx) + '</b>' }) }));
    h += block(VQ.t('What is included'), VQ.t('Write what is included with you. The suggestions depend on the chosen category; ignore them if they do not fit.'), field(null, tags('included_items', K.incl[t] || [], VQ.t('Type and press Enter'))) +
      (t === 'experience' ? field(VQ.t('What is not included'), tags('not_included', K.notIncl, VQ.t('Type and press Enter')), { opt: true }) +
        field(VQ.t('Good to know beforehand'), tags('requirements', K.req, VQ.t('Type and press Enter')), { opt: true }) : ''));
    if (t === 'experience') {
      var ageBad = p.age_min != null && p.age_max != null && p.age_min > p.age_max;
      h += block(VQ.t('Who it is for'), null,
        '<div class="wz-g2 is-keep">' + field(VQ.t('Minimum age'), stepper('age_min', { min: 0, max: 99, ph: VQ.t('any'), label: VQ.t('Minimum age'), u: VQ.t('years'), start: 6 }), { bad: ageBad }) + field(VQ.t('Maximum age'), stepper('age_max', { min: 0, max: 99, ph: VQ.t('any'), label: VQ.t('Maximum age'), u: VQ.t('years'), start: 70 }), { bad: ageBad }) + '</div>' +
        (ageBad ? '<p class="wz-err">' + VQ.t('The minimum age is above the maximum.') + '</p>' : '') +
        field(VQ.t('Meeting point'), inp('meeting_point', { max: 500, ph: K.ph.meet }), { opt: true, hint: VQ.t('Where the customer comes. It appears on the ticket.') }) + langBlock(p, K));
    }
    h += block(VQ.t('Rules'), null,
      '<div class="wz-fl"><span class="wz-lbl">' + VQ.t('Cancellation') + ' <small>' + VQ.t('optional') + '</small></span><div class="wz-chips">' + CANCEL.map(function (c) {
        return '<button type="button" class="wz-chip" data-act="set" data-path="cancellation_policy" data-val="' + jv(c[0]) + '" aria-pressed="' + (p.cancellation_policy === c[0]) + '">' + esc(c[1]) + '</button>';
      }).join('') + '</div>' + ta('cancellation_policy', { max: 2000, rows: 2, ph: VQ.t('Choose above or write your own rule.') }) + '</div>' +
      field(VQ.t('Terms of use'), ta('usage_terms', { max: 2000, rows: 2, ph: VQ.t('Show the ticket at the entrance, on your phone.') }), { opt: true }) +
      (K.terms.filter(function (s) { return (p.usage_terms || '').indexOf(s) < 0; }).length ? '<div class="wz-sugg"><span>' + VQ.t('Add:') + '</span>' + K.terms.filter(function (s) { return (p.usage_terms || '').indexOf(s) < 0; }).map(function (s) { return '<button type="button" data-act="terms" data-val="' + jv(s) + '">+ ' + esc(s) + '</button>'; }).join('') + '</div>' : ''));
    return h;
  }

  /* =================== step: pictures =================== */
  function stPoze() {
    var p = W.p, t = p.product_type, l = locById(p.location_id), lcov = l && l.cover_image;
    var h = head('poze', VQ.t('Show them what it is like there'), lcov ? VQ.t('Without its own photo, the product uses the photo of the venue "{name}". But a photo of the product sells better.', { name: esc(l.name) }) : (l ? VQ.t('A photo is required when sending for approval, because the venue has none either.') : VQ.t('A photo is required when sending for approval.')));
    h += block(VQ.t('Main photo'), VQ.t('JPG, PNG or WebP, 10 MB at most. Recommended: 1200 × 900 or larger, landscape.'), mount('cover') +
      (tried('poze') && !hasPhoto() ? '<p class="wz-err">' + VQ.t('Add a photo to be able to send the product for approval.') + '</p>' : '') +
      '<div class="wz-ph-tips"><div><b>' + VQ.t('People in the frame') + '</b>' + VQ.t('Customers picture themselves there.') + '</div><div><b>' + VQ.t('Daylight') + '</b>' + VQ.t('No strong filters.') + '</div><div><b>' + VQ.t('No text on the photo') + '</b>' + VQ.t('We add the title ourselves.') + '</div></div>', lcov ? { opt: true } : null);
    if (t === 'experience') h += block(VQ.t('Gallery'), VQ.t('Up to 20 photos on the experience page.'), mount('gallery'), { opt: true });
    return h;
  }

  /* =================== step: last settings =================== */
  function stSetari() {
    var p = W.p, t = p.product_type;
    var h = head('setari', VQ.t('The last settings'), VQ.t('Most products work well with what is already chosen here. Just check whether anything applies to you.'));
    if (t === 'experience') {
      h += block(VQ.t('Does it also need an entry ticket?'), VQ.t('If the experience takes place inside the venue, the customer needs access too. On the experience page, the access tickets appear next to it.'),
        '<div class="wz-cards">' + [['none', 'x', VQ.t('No'), VQ.t('The experience is bought on its own.')], ['any', 'ticket', VQ.t('Yes, one for each person'), VQ.t('Every participant needs an access ticket on the same day.')], ['adult', 'ticket', VQ.t('Yes, one adult ticket'), VQ.t('For example one per group: whoever buys for the group needs access.')]].map(function (o) {
          return choice('set', o[0], p.access_requirement === o[0], o[1] === 'x' ? ic('x') : pic('ticket'), o[2], o[3], { path: 'access_requirement' });
        }).join('') + '</div>');
    }
    h += block(VQ.t('Sales'), null,
      toggle('pos_only', VQ.t('Counter only'), VQ.t('Not sold online, only at the counter (POS). Good for complimentary tickets or local discounts.')) +
      toggle('requires_vehicle_info', VQ.t('Ask for the number plate'), p.access_kind === 'vehicle' && t === 'access' ? VQ.t('The customer enters it when buying; it appears on the ticket and in the list at the entrance. <b>Recommended for parking.</b>') : VQ.t('The customer enters it when buying; it appears on the ticket and in the list at the entrance.')));
    if (meta && meta.has_secondary_issuer) {
      h += block(VQ.t('The company that issues the ticket'), VQ.t('You have two companies in your account. Choose which one issues the tickets and the tax documents for this product.'),
        seg('issuing_company', [['primary', VQ.t('Main company')], ['secondary', VQ.t('Second company')]]));
    }
    return h;
  }

  /* =================== step: check and send =================== */
  var STATUS = {
    draft: ['is-muted', 'file-text', VQ.t('Draft: only you can see it'), VQ.t('Fill in what is missing and send it for approval: the Viaqui team looks it over and, if all is well, it appears on the site.')],
    pending: ['is-wait', 'hourglass', VQ.t('Sent for approval'), VQ.t('There is nothing more you need to do. We email you when it gets an answer. Until then you can keep editing; we check the latest version.')],
    rejected: ['is-bad', 'warning-circle', VQ.t('Rejected'), VQ.t('Fix what is written below and send it again. Nothing you wrote is lost.')],
    approvedOn: ['is-ok', 'check-circle', VQ.t('Approved and on the site'), VQ.t('The changes you save from now on appear on the site straight away, with no new approval.')],
    approvedOff: ['is-muted', 'eye-slash', VQ.t('Approved, but hidden from the site'), VQ.t('Nobody sees it until you press "Put on the site". Changes appear straight away, with no new approval.')],
  };
  function statusKey(p) {
    if (!p.id || p.review_status === 'draft') return 'draft';
    if (p.review_status === 'pending' || p.review_status === 'rejected') return p.review_status;
    return p.is_published ? 'approvedOn' : 'approvedOff';
  }
  function sumCard(step, title, rows) {
    rows = rows.filter(function (r) { return r[1] !== '' && r[1] != null; });
    var warn = problemsOf(step).length;
    return '<div class="wz-sum-c' + (warn ? ' is-warn' : '') + '"><div class="wz-sum-h"><b>' + title + '</b>' + (warn ? '<span class="wz-pill is-y">' + VQ.t('to fill in') + '</span>' : '') + '<button type="button" class="wz-link-btn" data-act="edit" data-val="' + jv(step) + '">' + ic('pencil-simple') + VQ.t('Edit') + '</button></div>' +
      (rows.length ? '<dl>' + rows.map(function (r) { return '<dt>' + r[0] + '</dt><dd>' + r[1] + '</dd>'; }).join('') + '</dl>' : '<p class="wz-hint">' + VQ.t('Nothing filled in yet.') + '</p>') + '</div>';
  }
  function stGata() {
    var p = W.p, t = p.product_type, l = locById(p.location_id), c = catById(p.category_id), sc = catById(p.subcategory_id);
    var probs = problems(), draftish = isDraft();
    var st = STATUS[statusKey(p)];
    var h = head('gata', draftish ? (probs.length ? VQ.t('Almost done') : VQ.t('Everything looks good')) : VQ.t('Product summary'),
      draftish ? (probs.length ? VQ.t('Still to fill in before sending: {n}. Press any item to go straight there.', { n: probs.length }) : VQ.t('Check the summary. You can edit any section; we bring you back here afterwards.'))
        : (p.review_status === 'pending' ? VQ.t('Press "Edit" on any section. Save when you finish.') : VQ.t('Press "Edit" on any section. Save when you finish; the changes appear on the site straight away.')));
    if (p.id) {
      h += '<div class="wz-state ' + st[0] + '" role="status"><span class="wz-state-ic">' + ic(st[1]) + '</span><div><b>' + st[2] + '</b><p>' + st[3] + '</p>' +
        (p.review_status === 'rejected' && p.rejection_reason ? '<p class="wz-state-why"><b>' + VQ.t('Reason:') + '</b> ' + esc(p.rejection_reason) + '</p>' : '') +
        (p.review_status === 'pending' && p.submitted_at ? '<p class="wz-hint">' + VQ.t('Sent on {date}', { date: esc(A.when(p.submitted_at)) }) + '</p>' : '') + '</div></div>';
    }
    var cks = [];
    cks.push([!!p.title, VQ.t('The title'), 'nume']);
    if (t !== 'experience') cks.push([!!p.location_id, VQ.t('The venue'), 'unde']);
    if (needsCategory()) cks.push([!!p.category_id, VQ.t('The Viaqui category'), 'unde']);
    if (t === 'package') {
      cks.push([p.package_items.length > 0 && !problemsOf('continut').length, VQ.t('The contents of the package, with the ticket chosen on each row'), 'continut']);
      cks.push([!problemsOf('pret').length, VQ.t('The price of the package'), 'pret']);
    } else {
      cks.push([!problemsOf('bilete').length, VQ.t('At least one ticket on sale, with a name and a price'), 'bilete']);
      if (p.booking_mode === 'slot') cks.push([!problemsOf('cand').length, VQ.t('The opening hours in which the start times can be booked'), 'cand']);
      else if (problemsOf('cand').length) cks.push([false, VQ.t('The opening hours'), 'cand']);
    }
    cks.push([hasPhoto(), (l && l.cover_image && !p.cover_image ? VQ.t('A photo (we use the photo of the venue)') : VQ.t('A photo')), 'poze']);
    if (draftish) {
      h += block(VQ.t('Before sending'), null, '<div class="wz-checks">' + cks.map(function (x) {
        return '<div class="wz-ck ' + (x[0] ? 'is-ok' : 'is-no') + '"><span class="wz-dot">' + (x[0] ? ic('check') : '!') + '</span><div>' + x[1] + '</div>' + (x[0] ? '' : '<button type="button" class="wz-link-btn" data-act="edit" data-val="' + jv(x[2]) + '">' + VQ.t('Fill in') + '</button>') + '</div>';
      }).join('') + '</div>');
    } else if (probs.filter(function (x) { return x.save; }).length) {
      h += '<div class="wz-alert is-warn">' + ic('warning-circle') + '<span>' + VQ.t('It cannot be saved yet: {what}.', { what: esc(probs.filter(function (x) { return x.save; }).map(function (x) { return x.msg; }).join('; ')) }) + '</span></div>';
    }
    var sums = [];
    var kindTxt = t === 'access' ? { person: VQ.t('People'), vehicle: VQ.t('Vehicle'), camping: VQ.t('Camping'), other: VQ.t('Something else') }[p.access_kind] : t === 'experience' ? { rental: VQ.t('Rental'), guided: VQ.t('Guided tour'), workshop: VQ.t('Workshop'), other: VQ.t('Something else') }[p.service_type] : '';
    sums.push(sumCard('tip', VQ.t('What you sell'), [[VQ.t('Type'), esc(TYPES[t])], [VQ.t('Kind'), esc(kindTxt || '')]]));
    var grp = l && p.display_category ? (l.display_categories || []).filter(function (g) { return g.id === p.display_category; })[0] : null;
    sums.push(sumCard('unde', VQ.t('Where'), [[VQ.t('Venue'), l ? esc(l.name) : (t === 'experience' ? VQ.t('No venue') : '<span class="wz-ph">' + VQ.t('missing') + '</span>')], [VQ.t('Category'), c ? esc(c.name) + (sc ? ' › ' + esc(sc.name) : '') : (needsCategory() ? '<span class="wz-ph">' + VQ.t('missing') + '</span>' : '')], [VQ.t('Group'), grp ? esc(grp.name) : '']]));
    sums.push(sumCard('nume', VQ.t('Name and description'), [[VQ.t('Title'), p.title ? (p.icon ? pic(p.icon) : '') + esc(p.title) : '<span class="wz-ph">' + VQ.t('missing') + '</span>'], [VQ.t('Subtitle'), esc(p.subtitle || '')], [VQ.t('In short'), esc(p.short_description || '')], [VQ.t('Description'), p.description ? VQ.t('written') : '']]));
    if (t === 'package') {
      sums.push(sumCard('continut', VQ.t('What it contains'), p.package_items.map(function (it) {
        var pr = products.filter(function (x) { return x.id === it.product_id; })[0], v = variantOf(it);
        return [(it.quantity || 1) + ' ×', esc((pr && pr.title) || it._title || '') + (v ? ' · ' + esc(v.name) : (it._vname ? ' · ' + esc(it._vname) : ' · <span class="wz-ph">' + VQ.t('ticket not chosen') + '</span>'))];
      })));
      var tt = pkgTotals();
      sums.push(sumCard('pret', VQ.t('Price'), [[VQ.t('Package'), p.variants[0].price != null ? esc(lei(p.variants[0].price)) : '<span class="wz-ph">' + VQ.t('missing') + '</span>'], [VQ.t('Separately'), tt.known ? esc(lei(tt.total)) : '']]));
    } else {
      sums.push(sumCard('bilete', VQ.t('Tickets and prices'), p.variants.map(function (v) { return [esc(v.name || VQ.t('No name')), (v.price != null ? esc(lei(v.price)) : '<span class="wz-ph">' + VQ.t('no price') + '</span>') + ' / ' + (v.price_type === 'per_unit' ? VQ.t('unit') : VQ.t('person')) + (v.is_active ? '' : ' · ' + VQ.t('not on sale'))]; })));
      var hasLoc = p.use_location_schedule && l && l.seasons && l.seasons.length;
      sums.push(sumCard('cand', VQ.t('When'), [[VQ.t('Booking'), p.booking_mode === 'day' ? VQ.t('All day') : VQ.t('At a fixed time, {duration}, one start every {n} min', { duration: esc(durTxt(p.duration_minutes)), n: esc(p.slot_interval_minutes) })],
        [VQ.t('Seats'), p.booking_mode === 'day' ? (p.daily_capacity ? VQ.t('{n} per day', { n: esc(p.daily_capacity) }) : VQ.t('no limit')) : (p.capacity_mode === 'concurrent' ? VQ.t('{n} {units} at the same time', { n: esc(p.capacity_per_slot), units: esc(kit().unit.many) }) : VQ.t('{n} at each start time', { n: esc(p.capacity_per_slot) }))],
        [VQ.t('Opening hours'), hasLoc ? VQ.t('same as the venue') : (p.periods.length ? VQ.t('its own, {periods}', { periods: VQ.n(p.periods.length, 'period', 'periods') }) : (p.booking_mode === 'day' ? VQ.t('on any day') : '<span class="wz-ph">' + VQ.t('missing') + '</span>'))],
        [VQ.t('Special days'), p.exceptions.length ? String(p.exceptions.length) : '']]));
      sums.push(sumCard('extra', VQ.t('Add-ons'), p.addons.map(function (a) { return [esc(a.name || VQ.t('No name')), esc(lei(a.price))]; })));
    }
    sums.push(sumCard('info', VQ.t('Good to know'), [[VQ.t('Includes'), esc(p.included_items.join(', '))], [VQ.t('Cancellation'), esc(p.cancellation_policy || '')], [VQ.t('Terms'), esc(p.usage_terms || '')],
      [VQ.t('Languages'), esc(p.languages.map(function (k) { return (LANGS.filter(function (x) { return x[0] === k; })[0] || [k, k])[1]; }).join(', '))]]));
    sums.push(sumCard('poze', VQ.t('Photos'), [[VQ.t('Main photo'), p.cover_image ? VQ.t('chosen') : (l && l.cover_image ? VQ.t('the one of the venue') : '<span class="wz-ph">' + VQ.t('missing') + '</span>')], [VQ.t('Gallery'), p.gallery.length ? VQ.n(p.gallery.length, 'photo', 'photos') : '']]));
    sums.push(sumCard('setari', VQ.t('Final settings'), [[VQ.t('Sales'), p.pos_only ? VQ.t('counter only') : VQ.t('online and at the counter')], [VQ.t('Number plate'), p.requires_vehicle_info ? VQ.t('asked for') : ''], [VQ.t('Company'), meta && meta.has_secondary_issuer ? (p.issuing_company === 'secondary' ? VQ.t('second company') : VQ.t('main company')) : '']]));
    h += '<div class="wz-sum">' + sums.join('') + '</div>';
    if (draftish) {
      h += block(VQ.t('What comes next'), null, '<div class="wz-flow"><div class="is-on"><i>' + ic('file-text') + '</i><b>' + VQ.t('Draft') + '</b>' + VQ.t('Only you can see it') + '</div><div' + (p.review_status === 'pending' ? ' class="is-on"' : '') + '><i>' + ic('eye') + '</i><b>' + VQ.t('Review') + '</b>' + VQ.t('The Viaqui team looks it over') + '</div><div><i>' + ic('check') + '</i><b>' + VQ.t('On the site') + '</b>' + VQ.t('Customers can buy it') + '</div></div>' +
        '<p class="wz-hint">' + VQ.t('Only the first publication waits for approval. After that, your changes appear on the site at once.') + '</p>');
    }
    if (p.id) {
      var canPub = p.review_status === 'approved' || !p.review_status;
      h += block(VQ.t('Other actions'), null, '<div class="wz-actions">' +
        (canPub ? '<button type="button" class="btn btn-ghost" data-act="publish">' + ic(p.is_published ? 'eye-slash' : 'eye') + '<span>' + (p.is_published ? VQ.t('Hide from the site') : VQ.t('Put on the site')) + '</span></button>' : '') +
        (canPub && p.is_published && p.public_path ? '<a class="btn btn-ghost" href="' + esc(p.public_path) + '" target="_blank" rel="noopener">' + ic('arrow-up-right') + '<span>' + VQ.t('View on the site') + '</span></a>' : '') +
        '<button type="button" class="btn btn-ghost" data-act="dup">' + ic('copy') + '<span>' + VQ.t('Copy the product') + '</span></button>' +
        '<button type="button" class="ve-danger wz-del" data-act="delete">' + ic('trash') + '<span>' + (W.armDelete ? VQ.t('Press again to delete') : VQ.t('Delete the product')) + '</span></button>' +
        '</div><p class="wz-hint">' + VQ.t('Deleting works only for a product with no sales that is not part of a package. Otherwise, hide it from the site.') + '</p>');
    }
    return h;
  }

  var RENDER = { tip: stTip, unde: stUnde, nume: stNume, bilete: stBilete, continut: stContinut, pret: stPret, cand: stCand, extra: stExtra, info: stInfo, poze: stPoze, setari: stSetari, gata: stGata };
  var TIPS = {
    tip: [VQ.t('Not sure what to choose?'), VQ.t('If the customer "gets in", it is an access ticket. If they "do something" there, it is an experience. If you want to sell them together, for less, it is a package.')],
    unde: [VQ.t('The category matters a lot'), VQ.t('Customers who do not know you find you through the category lists and the city pages. Choose the category you would look in yourself.')],
    nume: [VQ.t('Titles that sell'), VQ.t('Short and concrete: say what the customer gets, not how lovely it will be. Keep the emotion for the description.')],
    bilete: [VQ.t('Your price, no surprises'), VQ.t('You enter the amount you receive. The Viaqui commission is added on top, for the customer, so you lose nothing from the price.')],
    continut: [VQ.t('A clear package'), VQ.t('A package is understood at a glance: entry and one experience, for a clear number of people.')],
    pret: [VQ.t('How big should the discount be?'), VQ.t('A discount of 10–15% off the separate price usually makes the package look like a good deal.')],
    cand: [VQ.t('Do you rent things out?'), VQ.t('Karts, bicycles, rooms, boats: choose "Units at the same time". We count how many are in use each minute, not how many started at a given time.')],
    extra: [VQ.t('Add-ons grow the basket'), VQ.t('A photo package or extra time, offered at the right moment, are chosen often. Do not offer more than 3–4.')],
    info: [VQ.t('Fewer phone calls'), VQ.t('Everything you write here is a question you no longer answer on the phone: what is included, for what ages, what to bring.')],
    poze: [VQ.t('The photo is seen first'), VQ.t('In lists, customers look at the photo first, then at the price, and only then at the title.')],
    setari: [VQ.t('You can leave everything as it is'), VQ.t('The settings here are for special cases. If none of them fits you, move on.')],
    gata: [VQ.t('Nothing is final'), VQ.t('After approval you can change anything: prices, opening hours, photos. The changes appear straight away.')],
  };

  /* =================== preview =================== */
  function preview() {
    var p = W.p, t = p.product_type, l = locById(p.location_id);
    var vars = p.variants.filter(function (v) { return v.is_active && !v.pos_only; });
    var prices = vars.map(function (v) { return v.price; }).filter(function (x) { return x != null; });
    var min = prices.length ? Math.min.apply(null, prices) : null;
    var K = kit();
    var unit = p.unit_label || (t === 'experience' && vars[0] && vars[0].price_type === 'per_unit' ? VQ.t('unit') : K.ph.unit[t || 'access']);
    var tg = [];
    if (t && t !== 'package') tg.push(p.booking_mode === 'slot' ? '<span class="wz-pill">' + ic('clock') + esc(p.duration_minutes ? durTxt(p.duration_minutes) : VQ.t('timed')) + '</span>' : '<span class="wz-pill">' + VQ.t('All day') + '</span>');
    if (p.cancellation_policy && /free|gratuit/i.test(p.cancellation_policy)) tg.push('<span class="wz-pill is-g">' + VQ.t('Free cancellation') + '</span>');
    if (p.languages.length) tg.push('<span class="wz-pill">' + esc(p.languages.join(' · ').toUpperCase()) + '</span>');
    if (p.age_min) tg.push('<span class="wz-pill">' + VQ.t('{n}+ years', { n: esc(p.age_min) }) + '</span>');
    var cover = A.imgUrl(p.cover_image) || (l ? A.imgUrl(l.cover_image) : null);
    var media = cover ? '<img src="' + esc(cover) + '" alt="">' : '<span class="wz-pv-ic">' + pic(p.icon || (t ? TYPE_ICON[t] : 'sparkle')) + '</span>';
    var stTxt = { draft: VQ.t('Draft'), pending: VQ.t('In review'), rejected: VQ.t('Rejected'), approvedOn: VQ.t('On the site'), approvedOff: VQ.t('Hidden') }[statusKey(p)];
    var h = '<div class="wz-pv"><div class="wz-pv-m">' + media + '<span class="wz-pv-st">' + stTxt + '</span>' + (cover && !p.cover_image ? '<span class="wz-pv-fb">' + VQ.t('photo of the venue') + '</span>' : '') + '</div><div class="wz-pv-b">' +
      '<span class="wz-pv-k">' + esc([t ? TYPES[t] : VQ.t('Product'), l ? l.name : ''].filter(Boolean).join(' · ')) + '</span>' +
      '<h3 class="wz-pv-t">' + (p.title ? (p.icon ? pic(p.icon) : '') + esc(p.title) : '<span class="wz-ph">' + VQ.t('Product title') + '</span>') + '</h3>' +
      (p.subtitle ? '<p class="wz-pv-s is-strong">' + esc(p.subtitle) + '</p>' : '') +
      (p.short_description ? '<p class="wz-pv-s">' + esc(p.short_description) + '</p>' : (!p.title ? '<p class="wz-pv-s wz-ph">' + VQ.t('The short description appears here.') + '</p>' : '')) +
      (tg.length ? '<div class="wz-pv-tags">' + tg.join('') + '</div>' : '');
    if (t === 'package') {
      h += p.package_items.length ? '<ul class="wz-pv-vars">' + p.package_items.map(function (it) {
        var pr = products.filter(function (x) { return x.id === it.product_id; })[0], v = variantOf(it);
        return '<li><span>' + (it.quantity || 1) + ' × ' + esc((pr && pr.title) || it._title || '') + (v ? ' · ' + esc(v.name) : '') + '</span></li>';
      }).join('') + '</ul>' : '';
      var pk = pkgTotals();
      if (pk.known && p.variants[0].price != null && pk.total > p.variants[0].price) h += '<span class="wz-pv-save">' + VQ.t('You save {amount}', { amount: esc(lei(pk.total - p.variants[0].price)) }) + '</span>';
    } else if (vars.length) {
      h += '<ul class="wz-pv-vars">' + vars.map(function (v) {
        return '<li><span>' + esc(v.name || VQ.t('Ticket')) + (v.price_type === 'per_unit' && v.persons_max ? ' <small>· ' + VQ.t('up to {n} people', { n: esc(v.persons_max) }) + '</small>' : '') + '</span><b>' + (v.price != null ? esc(lei(v.price)) : '—') + '</b><span class="wz-pv-q" aria-hidden="true"><i>−</i><i>+</i></span></li>';
      }).join('') + '</ul>';
    }
    var ad = p.addons.filter(function (a) { return a.name && a.is_active !== false; });
    if (ad.length && t !== 'package') h += '<p class="wz-pv-s">+ ' + esc(ad.map(function (a) { return a.name; }).join(', ')) + '</p>';
    h += '<div class="wz-pv-f"><div><small>' + VQ.t('from') + '</small><b>' + (min != null ? esc(lei(min)) : '—') + '</b> <span class="wz-pv-u">/ ' + esc(unit) + '</span></div><span class="wz-pv-cta">' + VQ.t('Book') + '</span></div></div></div>';
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
        return '<button type="button" class="wz-rail-s' + (st ? ' is-' + st : '') + '" data-act="go" data-val="' + jv(s.id) + '"' + (locked && s.id !== 'tip' ? ' disabled' : '') + (st === 'cur' ? ' aria-current="step"' : '') + '><span class="wz-dot">' + (st === 'done' ? ic('check') : st === 'warn' ? '!' : n) + '</span><span>' + s.t + '</span>' + (s.opt ? '<em>' + VQ.t('optional') + '</em>' : '') + '</button>';
      }).join('') + '</div>';
    });
    return html;
  }
  function drawChrome(nudge) {
    var a = steps(), i = stepIdx(W.step), s = stepById(W.step);
    $('wz-rail').innerHTML = railHtml();
    $('wz-steps-sheet').innerHTML = '<div class="wz-rail is-sheet">' + railHtml() + '</div>';
    $('wz-top-k').textContent = VQ.t('Step {n} of {total}', { n: i + 1, total: a.length }) + ' · ' + PHASES[s.ph];
    $('wz-top-t').textContent = s.t;
    $('wz-prog').style.width = ((i + 1) / a.length * 100) + '%';
    var tp = TIPS[W.step];
    $('wz-tip').innerHTML = '<b>' + ic('info') + esc(tp[0]) + '</b>' + esc(tp[1]);
    drawBar(nudge);
  }
  function saveLabel() {
    if (W.saving) return VQ.t('Saving…');
    if (W.saveErr) return VQ.t('Not saved');
    if (W.dirty && isDraft()) {
      var hard = problems().filter(function (x) { return x.save; })[0];
      if (hard) return VQ.t('It saves once this is done: {what}', { what: hard.msg });
    }
    if (!W.p.id) return W.dirty ? VQ.t('Not saved yet') : '';
    if (W.dirty) return VQ.t('Unsaved changes');
    return W.savedAt ? VQ.t('Saved at {time}', { time: W.savedAt }) : VQ.t('Saved');
  }
  function drawBar(nudge) {
    var a = steps(), i = stepIdx(W.step), next = a[i + 1], h = '';
    if (nudge) h += '<div class="wz-nudge">' + ic('warning-circle') + '<span>' + esc(nudge) + '</span></div>';
    h += '<div class="wz-bar-in">';
    if (i > 0) h += '<button type="button" class="btn btn-ghost wz-back" data-act="back" aria-label="' + VQ.t('Back') + '">' + ic('arrow-left') + '<span>' + VQ.t('Back') + '</span></button>';
    h += '<span class="wz-saved' + (W.dirty || W.saveErr ? ' is-dirty' : '') + '" id="wz-saved">' + esc(saveLabel()) + '</span><span class="wz-sp"></span>';
    if (W.p.product_type) h += '<button type="button" class="btn btn-ghost wz-pv-btn" data-act="pv" aria-label="' + VQ.t('Preview') + '">' + ic('eye') + '<span>' + VQ.t('Preview') + '</span></button>';
    var liveEdit = W.p.id && !isDraft();
    if (W.step === 'gata') {
      if (isDraft()) {
        h += '<button type="button" class="btn btn-ghost" data-act="save">' + ic('check') + '<span>' + VQ.t('Save the draft') + '</span></button>';
        h += '<button type="button" class="btn btn-primary" data-act="submit">' + ic('arrow-right') + '<span>' + VQ.t('Send for approval') + '</span></button>';
      } else {
        h += '<button type="button" class="btn btn-primary" data-act="save"' + (W.dirty ? '' : ' disabled') + '>' + ic('check') + '<span>' + VQ.t('Save the changes') + '</span></button>';
      }
    } else {
      if (liveEdit && W.dirty) h += '<button type="button" class="btn btn-ghost wz-save-btn" data-act="save">' + ic('check') + '<span>' + VQ.t('Save') + '</span></button>';
      if (W.returnTo) h += '<button type="button" class="btn btn-primary" data-act="return">' + ic('arrow-counter-clockwise') + '<span>' + VQ.t('Back to the summary') + '</span></button>';
      else if (W.step === 'tip' && !W.p.product_type) h += '<button type="button" class="btn btn-primary" disabled><span>' + VQ.t('Choose a type') + '</span>' + ic('arrow-right') + '</button>';
      else if (next) {
        var skip = stepById(W.step).opt && isEmptyOpt(W.step);
        h += '<button type="button" class="btn btn-primary wz-next" data-act="next"><span>' + (skip ? VQ.t('Skip') : VQ.t('Continue')) + '<span class="wz-nx">: ' + esc(next.t) + '</span></span>' + ic('arrow-right') + '</button>';
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
        m.appendChild(A.rich(W.p, 'description', { label: VQ.t('Product description'), max: 20000, ph: kit().ph.desc, min: 160, on: function () { changed(); } }));
      } else if (k === 'cover') {
        m.appendChild(A.image(W.p, 'cover_image', 'product', { on: function () { changed(); bump(); drawChrome(); }, hint: VQ.t('You can drop the photo straight onto the box.') }));
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
    if (W.step === 'unde' && p.product_type !== 'experience' && !p.location_id) return VQ.t('Choose the venue to continue.');
    if (W.step === 'nume' && !p.title) return VQ.t('Enter the title; it is the one field we cannot save without.');
    if (W.step === 'tip' && p.product_type === 'package' && !componentChoices().length && !p.package_items.length) return VQ.t('For a package you first need tickets or experiences.');
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
        drawBar(VQ.t('It cannot be saved yet: {what}.', { what: hard[0].msg }));
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
        if (isNew) history.replaceState(null, '', PROD_URL + '?id=' + saved.id);
      }
      if (W.rev === rev) W.dirty = false;
      var d = new Date();
      W.savedAt = String(d.getHours()).padStart(2, '0') + ':' + String(d.getMinutes()).padStart(2, '0');
      W.serverErr = null;
      if (!quiet) O.flash((r && r.message) || VQ.t('Saved.'));
      return true;
    }, function (err) {
      if (w !== W) return false;
      if (err && err.status === 401) return false;
      var se = serverError(err, VQ.t('We could not save the product.'));
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
  /** A draft saves itself between steps; a product already on the site (or in review) waits for "Save". */
  function autosave() {
    if (!W || !W.dirty || !isDraft() || !W.p.product_type) return;
    if (problems().some(function (x) { return x.save && (x.step === 'unde' || x.step === 'nume' || x.step === 'tip'); })) return;
    save(true);
  }
  function submit() {
    var must = problems();
    if (must.length) {
      W.tried[must[0].step] = true;
      drawBar(VQ.t('Still to fill in: {what}.', { what: must.map(function (x) { return x.msg; }).filter(function (v, i, a) { return a.indexOf(v) === i; }).slice(0, 4).join('; ') }));
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
        var se = serverError(err, VQ.t('We could not send the product.'));
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
        O.flash((r && r.message) || VQ.t('Done.'));
        rerender();
      }, function (err) { O.flash(A.errText(err, VQ.t('We could not change the visibility.')), true); });
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
      O.flash((r && r.message) || VQ.t('The product was deleted.'));
      go(PROD_URL, true);
    }, function (err) { W.armDelete = false; rerender(); O.flash(A.errText(err, VQ.t('We could not delete the product.')), true); });
  }

  /* =================== celebration =================== */
  function celebrate() {
    var box = $('wz-done');
    box.querySelector('.wz-done-c').innerHTML = '<div class="wz-done-ic"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M5 12.5l4.5 4.5L19 7.5"/></svg></div><h2 id="wz-done-t">' + VQ.t('Sent for approval') + '</h2>' +
      '<p>' + VQ.t('"{title}" is now with the Viaqui team. We email you when it is approved and appears on the site.', { title: esc(W.p.title) }) + '</p>' +
      '<div class="wz-flow"><div class="is-on"><i>' + ic('file-text') + '</i><b>' + VQ.t('Draft') + '</b></div><div class="is-on"><i>' + ic('eye') + '</i><b>' + VQ.t('Review') + '</b>' + VQ.t('now') + '</div><div><i>' + ic('check') + '</i><b>' + VQ.t('On the site') + '</b></div></div>' +
      '<div class="wz-done-btns"><a class="btn btn-primary" href="' + PROD_URL + '?nou=1">' + ic('plus') + '<span>' + VQ.t('Add another product') + '</span></a><a class="btn btn-ghost" href="' + PROD_URL + '">' + ic('list') + '<span>' + VQ.t('All products') + '</span></a><button type="button" class="wz-link" data-act="closedone">' + VQ.t('Stay here') + '</button></div>';
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
      '<div class="wz-top" id="wz-top"><div class="wz-top-in"><a class="wz-top-x" href="' + PROD_URL + '" aria-label="' + VQ.t('Back to all products') + '">' + ic('arrow-left') + '</a>' +
      '<button type="button" class="wz-top-step" data-act="steps" aria-haspopup="dialog"><small id="wz-top-k"></small><b><span id="wz-top-t"></span>' + ic('caret-down') + '</b></button></div><div class="wz-prog"><i id="wz-prog"></i></div></div>' +
      '<div class="wz-app"><nav class="wz-rail" id="wz-rail" aria-label="' + VQ.t('Steps of the product') + '"></nav>' +
      '<div class="wz-main"><a class="am-back wz-back-link" href="' + PROD_URL + '">' + ic('arrow-left') + '<span>' + VQ.t('All products') + '</span></a><div class="wz-stage" id="wz-stage"></div></div>' +
      '<aside class="wz-side" aria-label="' + VQ.t('Preview') + '"><div class="wz-side-h"><b>' + VQ.t('This is how customers see it') + '</b></div><div id="wz-pv"></div><div class="wz-tip" id="wz-tip"></div></aside></div>' +
      '<div class="wz-bar" id="wz-bar"></div>' +
      '<div class="wz-scrim" id="wz-scrim" data-act="closesheet" hidden></div>' +
      '<div class="wz-sheet" id="wz-sheet-pv" role="dialog" aria-modal="true" aria-labelledby="wz-sheet-pv-t" hidden><div class="wz-sheet-h"><b id="wz-sheet-pv-t">' + VQ.t('This is how customers see it') + '</b><button type="button" class="ve-icon-btn" data-act="closesheet" aria-label="' + VQ.t('Close') + '">' + ic('x') + '</button></div><div class="wz-sheet-b" id="wz-pv-sheet"></div></div>' +
      '<div class="wz-sheet" id="wz-sheet-steps" role="dialog" aria-modal="true" aria-labelledby="wz-sheet-steps-t" hidden><div class="wz-sheet-h"><b id="wz-sheet-steps-t">' + VQ.t('All the steps') + '</b><button type="button" class="ve-icon-btn" data-act="closesheet" aria-label="' + VQ.t('Close') + '">' + ic('x') + '</button></div><div class="wz-sheet-b" id="wz-steps-sheet"></div></div>' +
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
    if (O.fold) O.fold(true); // more room for the editor; the operator's own choice comes back with the list
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
    if (O.fold) O.fold();
    $('am-prod-edit').textContent = '';
  }
  function startNew(pre) {
    var box = $('am-prod-edit');
    hideListBits();
    box.hidden = false;
    box.textContent = '';
    box.appendChild(el('p', { class: 've-state', text: VQ.t('Loading…') }));
    Promise.all([loadBase(), loadProducts()]).then(function () {
      var loc = pre && locById(pre) ? pre : (locations.length === 1 ? locations[0].id : null);
      var p = blank(loc);
      openWizard(p, 'tip', false);
    }, function (err) {
      if (err && err.status === 401) return;
      box.textContent = '';
      box.appendChild(el('div', { class: 'org-empty is-error' }, [el('b', { text: VQ.t('We could not open the form') }), el('p', { text: A.errText(err, VQ.t('Try again in a few seconds.')) })]));
    });
  }
  function openEditor(id) {
    hideListBits();
    var box = $('am-prod-edit');
    box.hidden = false;
    box.textContent = '';
    box.appendChild(el('p', { class: 've-state', text: VQ.t('Loading…') }));
    Promise.all([loadBase(), A.api('/products/' + id), products.length ? Promise.resolve(products) : loadProducts()]).then(function (res) {
      var p = res[1] && res[1].data && res[1].data.product;
      if (!p) { O.flash(VQ.t('This product does not exist.'), true); go(PROD_URL, true); return; }
      details[p.id] = p;
      openWizard(fromApi(clone(p)), 'gata', true);
    }, function (err) {
      if (err && err.status === 401) return;
      box.textContent = '';
      box.appendChild(el('div', { class: 'org-empty is-error' }, [el('b', { text: VQ.t('We could not load the product') }), el('p', { text: A.errText(err, VQ.t('Try again.')) })]));
    });
  }

  /* =================== actions =================== */
  function setType(t) {
    var p = W.p, was = p.product_type;
    if (was === t || p.id) return;
    p.product_type = t;
    if (t === 'package') {
      p.variants = [blankVariant('package', VQ.t('Package'))];
      p.booking_mode = 'day';
    } else {
      if (was === 'package' || !p.variants.length) p.variants = [blankVariant(t, t === 'experience' ? null : VQ.t('Adult'))];
      p.booking_mode = t === 'experience' ? 'slot' : 'day';
      if (t === 'experience' && !p.use_location_schedule && !p.periods.length) p.periods = [{ season: null, days: blankWeek('10:00', '18:00') }];
    }
    if (t !== 'experience' && !p.location_id && locations.length === 1) p.location_id = locations[0].id;
    applyLocationDefaults();
    changed();
    rerender();
    if (was) O.flash(VQ.t('The type changed to "{type}". What you filled in stays.', { type: TYPES[t] }));
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
    }, function () { O.flash(VQ.t('We could not load the tickets of the product.'), true); });
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
        A.api('/locations').then(function (r) { locations = ((r && r.data && r.data.locations) || []); rerender(); O.flash(VQ.t('The list of venues is up to date.')); }, function (err) { O.flash(A.errText(err, VQ.t('We could not reload the venues.')), true); });
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
      case 'vdel': p.variants.splice(i, 1); changed(); rerender(); O.flash(VQ.t('The ticket was removed. Those already sold stay valid.')); break;
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
        changed(); rerender(); O.flash(VQ.t('Monday hours are now on every open day.')); break;
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
      var nm = root.querySelector('[data-live="variants.' + m[1] + '.name"]'); if (nm) nm.textContent = v.name || VQ.t('Unnamed ticket');
      var pr = root.querySelector('[data-live-price="' + m[1] + '"]'); if (pr) pr.innerHTML = v.price != null ? esc(lei(v.price)) : '<span class="wz-ph">' + VQ.t('price?') + '</span>';
    }
    var cnt = root.querySelector('[data-count="' + path + '"]');
    if (cnt) cnt.textContent = (getP(path) || '').length + ' / ' + cnt.textContent.split('/ ')[1];
    if (W.step === 'cand') { var ws = $('wz-when'); if (ws) ws.innerHTML = whenSentence(); }
    if (W.step === 'pret' && path === 'variants.0.price') {
      var t = pkgTotals(), price = p.variants[0].price, sb = $('wz-save-big');
      if (sb && t.known) {
        var sv = price == null ? null : t.total - price, mx = Math.max(t.total, price || 0) || 1;
        sb.textContent = sv == null ? '—' : sv < 0 ? VQ.t('{amount} more expensive', { amount: lei(-sv) }) : lei(sv);
        sb.classList.toggle('is-bad', sv != null && sv < 0);
        $('wz-bar-pk').style.width = ((price || 0) / mx * 100) + '%';
        $('wz-bar-pk-t').textContent = price != null ? lei(price) : '—';
      }
    }
    if (path === 'unit_label' && $('wz-unit-ex')) { var v0 = p.variants[0]; $('wz-unit-ex').textContent = (v0 && v0.price != null ? lei(v0.price) : lei(50)) + ' / ' + (p.unit_label || kit().ph.unit[p.product_type]); }
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
    if (W && W.dirty && !window.confirm(VQ.t('You have unsaved changes. Leave without saving them?'))) { history.pushState(null, '', W.p.id ? PROD_URL + '?id=' + W.p.id : PROD_URL + '?nou=1'); return; }
    route();
  });
  window.addEventListener('beforeunload', function (e) { if (W && (W.dirty || W.saving)) { e.preventDefault(); e.returnValue = ''; } });
  document.addEventListener('click', function (e) {
    var a = e.target.closest('a[href]');
    if (!a || e.defaultPrevented || e.ctrlKey || e.metaKey || e.shiftKey || a.target === '_blank') return;
    var href = a.getAttribute('href');
    if (href.indexOf(PROD_URL) !== 0) return;
    if (W && W.dirty) {
      // a draft can save itself on the way out
      if (isDraft() && !problems().some(function (x) { return x.save; })) {
        e.preventDefault();
        save(true).then(function (ok) { if (ok || window.confirm(VQ.t('We could not save the draft. Leave without saving it?'))) { if (W) W.dirty = false; go(href); } });
        return;
      }
      if (!window.confirm(VQ.t('You have unsaved changes. Leave without saving them?'))) { e.preventDefault(); return; }
      W.dirty = false;
    }
    e.preventDefault();
    go(href);
  });
  $('am-f-loc').addEventListener('change', function () {
    var v = $('am-f-loc').value;
    history.replaceState(null, '', PROD_URL + (v ? '?locatie=' + v : ''));
    drawList();
  });
  $('am-f-type').addEventListener('change', drawList);
  $('am-prod-retry').addEventListener('click', function () { $('am-prod-failed').hidden = true; route(); });

  O.ready.then(function (ok) { if (ok) route(); });
})();
