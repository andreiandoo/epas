/* bilete.online v2: the operator's products (/organizator/produse): access tickets, experiences and packages.
   A filtered list and an editor (?id=N, ?nou=1 with a type choice first, ?locatie=N filters and preselects). The editor
   shows only what fits the kind: the tickets with their prices (per person or per unit, child, group minimum, days
   of validity or duration, free companion), when it can be used (all day or at start times, seats, the location's
   hours or its own, closed days), add-ons, the package contents with the share of the price each part gets, the
   details of an experience, options and pictures. The product object is sent whole on save. Uses window.BO_AM. */
(function () {
  'use strict';
  var O = window.BO_ORG, A = window.BO_AM, root = document.getElementById('am-prod');
  if (!O || !A || !root) return;
  var el = O.el, F = O.fmt, L = A.L;
  var $ = function (id) { return document.getElementById(id); };
  var TYPES = L.product_types || { access: 'Bilet de acces', experience: 'Experiență', package: 'Pachet' };
  var WEEK = [[1, 'Luni'], [2, 'Marți'], [3, 'Miercuri'], [4, 'Joi'], [5, 'Vineri'], [6, 'Sâmbătă'], [7, 'Duminică']];
  var LANGS = { ro: 'Română', en: 'Engleză', hu: 'Maghiară', de: 'Germană', fr: 'Franceză' };
  var meta = null, locations = [], products = [], cur = null, dirty = false, busy = false;
  var details = {}; // product id → full product (package components need their variants)

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
        el('p', { text: 'Biletele de acces și pachetele țin de o locație. Adaug-o, apoi revino aici.' }),
        el('a', { class: 'btn btn-primary', href: '/organizator/locatii?nou=1' }, [O.icon('plus'), el('span', { text: 'Adaugă locația' })]),
      ]));
      return;
    }
    var loc = parseInt($('am-f-loc').value, 10) || null, type = $('am-f-type').value || null;
    var shown = products.filter(function (p) { return (!loc || p.location_id === loc) && (!type || p.type === type); });
    if (!shown.length) {
      box.appendChild(el('div', { class: 'org-empty' }, [
        el('span', { class: 'org-empty-ic' }, [O.icon('ticket')]),
        el('b', { text: products.length ? 'Niciun produs pentru filtrele alese' : 'Niciun produs încă' }),
        el('p', { text: 'Un bilet de acces pentru intrare, o experiență (barcă, tur, atelier) sau un pachet cu amândouă.' }),
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
    var media = el('span', { class: 've-prod-media' }, src ? [el('img', { src: src, alt: '' })] : [O.icon('ticket')]);
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
    var dup = A.button('plus', 'Copiază', 'btn btn-ghost');
    dup.addEventListener('click', function () { duplicate(p.id); });
    tools.appendChild(dup);
    if (p.is_published && p.public_path && (!p.review_status || p.review_status === 'approved')) {
      tools.appendChild(el('a', { class: 've-icon-btn', href: p.public_path, target: '_blank', rel: 'noopener', 'aria-label': 'Vezi pe site' }, [O.icon('arrow-right')]));
    }
    return el('article', { class: 've-prod' + (p.is_published ? '' : ' is-off') }, [media, t, price, tools]);
  }
  function duplicate(id) {
    A.api('/products/' + id + '/duplicate', { method: 'POST', body: {} }).then(function (r) {
      O.flash((r && r.message) || 'Copia a fost creată.');
      var copy = r && r.data && r.data.product;
      if (copy) go('/organizator/produse?id=' + copy.id);
    }, function (err) { O.flash(A.errText(err, 'Nu am putut copia produsul.'), true); });
  }

  /* =================== new: the kind first =================== */
  function chooseType() {
    hideListBits();
    var box = $('am-prod-edit');
    box.hidden = false;
    box.textContent = '';
    var pre = parseInt(params().get('locatie'), 10) || null;
    var KINDS = [
      ['access', 'ticket', 'Bilet de acces', 'Intrarea în locație, pe o zi sau mai multe: adult, copil, elev, grup; parcare, camping.'],
      ['experience', 'lightning', 'Experiență', 'Ceva de făcut acolo: barcă, tur ghidat, atelier, echipament închiriat. Cu oră sau pe toată ziua.'],
      ['package', 'gift', 'Pachet', 'Bilete de acces și experiențe împreună, la un singur preț. De pildă „Familie 2+2 cu barcă”.'],
    ];
    var grid = el('div', { class: 'am-types' });
    KINDS.forEach(function (k) {
      var b = el('button', { class: 'am-type', type: 'button' }, [O.icon(k[1]), el('b', { text: k[2] }), el('small', { text: k[3] })]);
      b.addEventListener('click', function () { startNew(k[0], pre); });
      grid.appendChild(b);
    });
    box.appendChild(el('header', { class: 've-head' }, [el('div', null, [
      el('a', { class: 'am-back', href: '/organizator/produse' }, [O.icon('arrow-left'), el('span', { text: 'Toate produsele' })]),
      el('h1', { class: 've-h', text: 'Ce vrei să vinzi?' }),
      el('p', { class: 've-lead', text: 'Alege tipul produsului. Îl completezi pe pașii următori.' }),
    ])]));
    box.appendChild(grid);
  }
  function startNew(type, locId) {
    Promise.all([loadBase(), products.length ? Promise.resolve(products) : loadProducts()]).then(function () {
      var only = locations.length === 1 ? locations[0].id : null;
      cur = normalize(blank(type, locId || only));
      dirty = false;
      drawEditor();
    });
  }
  function hideListBits() {
    $('am-prod-list').hidden = true;
    $('am-prod-failed').hidden = true;
    $('am-prod-head').hidden = true;
    $('am-prod-filters').hidden = true;
  }

  /* =================== editor =================== */
  function blankVariant(type, first) {
    return {
      id: null, name: type === 'package' ? 'Pachet' : (first ? (type === 'experience' ? 'Standard' : 'Adult') : null), description: null,
      price: null, price_type: 'per_person', persons_min: null, persons_max: null, is_child: false, min_age: null, max_age: null,
      duration_minutes: null, validity_days: 1, min_per_order: 1, max_per_order: 20, step_qty: null, companion_label: null,
      pos_price: null, pos_only: false, capacity_share: 1, is_active: true, is_refundable: true,
    };
  }
  function blank(type, locId) {
    return {
      id: null, product_type: type, location_id: locId || null, access_kind: type === 'access' ? 'person' : null, service_type: type === 'experience' ? 'rental' : null,
      title: null, subtitle: null, short_description: null, description: null, icon: null, display_category: null,
      category_id: null, subcategory_id: null,
      booking_mode: type === 'experience' ? 'slot' : 'day', capacity_mode: 'per_slot', capacity_per_slot: 10, daily_capacity: null,
      duration_minutes: type === 'experience' ? 60 : null, slot_interval_minutes: type === 'experience' ? 60 : null,
      booking_lead_time_hours: 0, booking_max_advance_days: null, use_location_schedule: true,
      access_requirement: 'none', requires_vehicle_info: false, pos_only: false, issuing_company: 'primary',
      unit_label: null, usage_terms: null, meeting_point: null, languages: [], included_items: [], not_included: [], requirements: [],
      cancellation_policy: null, age_min: null, age_max: null, cover_image: null, gallery: [],
      variants: [blankVariant(type, true)], schedules: [], exceptions: [], addons: [], package_items: [],
      review_status: 'draft', is_published: false,
    };
  }
  function normalize(p) {
    var b = blank(p.product_type || p.type || 'access', p.location_id);
    p.product_type = p.product_type || p.type || b.product_type;
    Object.keys(b).forEach(function (k) { if (p[k] === undefined || p[k] === null) p[k] = b[k]; });
    ['languages', 'included_items', 'not_included', 'requirements', 'gallery', 'schedules', 'exceptions', 'addons', 'package_items'].forEach(function (k) { if (!Array.isArray(p[k])) p[k] = []; });
    if (!p.variants.length) p.variants = [blankVariant(p.product_type, true)];
    return p;
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
      cur = normalize(JSON.parse(JSON.stringify(p)));
      dirty = false;
      drawEditor();
    }, function (err) {
      if (err && err.status === 401) return;
      box.textContent = '';
      box.appendChild(el('div', { class: 'org-empty is-error' }, [el('b', { text: 'Nu am putut încărca produsul' }), el('p', { text: A.errText(err, 'Reîncearcă.') })]));
    });
  }

  function variantCard(v, i, type, mode, redraw) {
    var mark = function () { dirty = true; };
    var perUnit = el('span');
    function drawUnit() {
      perUnit.textContent = '';
      if (v.price_type === 'per_unit') {
        perUnit.appendChild(A.field('Câte persoane încap', A.input(v, 'persons_max', { type: 'number', min: 1, maxv: 500, ph: '4', on: mark }), { hint: 'La o barcă de 4 persoane: 4.' }));
      }
    }
    var more = el('details', null, [el('summary', { text: 'Mai multe opțiuni' }), A.form([
      mode === 'slot' && type !== 'package' ? A.field('Durata acestui bilet (minute)', A.input(v, 'duration_minutes', { type: 'number', min: 5, maxv: 1440, ph: 'ca la produs', on: mark }), { hint: 'De pildă 30 sau 60 de minute cu barca.' }) : null,
      mode === 'day' && type !== 'package' ? A.field('Valabil câte zile', A.input(v, 'validity_days', { type: 'number', min: 1, maxv: 60, ph: '1', on: mark }), { hint: 'Un abonament de 3 zile: 3.' }) : null,
      A.field('Minim pe comandă', A.input(v, 'min_per_order', { type: 'number', min: 1, maxv: 500, ph: '1', on: mark }), { hint: 'La un bilet de grup: 8.' }),
      A.field('Maxim pe comandă', A.input(v, 'max_per_order', { type: 'number', min: 1, maxv: 500, ph: '20', on: mark })),
      A.field('Se adaugă câte', A.input(v, 'step_qty', { type: 'number', min: 1, maxv: 100, ph: '1', on: mark })),
      A.field('Însoțitor gratuit', A.input(v, 'companion_label', { max: 80, ph: 'Ghid', on: mark }), { hint: 'Un bilet gratuit în plus pe comandă, de pildă pentru ghidul grupului.' }),
      A.field('Preț la casă (lei)', A.input(v, 'pos_price', { type: 'number', min: 0, step: '0.01', ph: 'ca online', on: mark })),
      A.field('Descriere scurtă', A.input(v, 'description', { max: 280, ph: 'Copii între 5 și 14 ani', on: mark }), { wide: true }),
      el('div', { class: 've-checks is-wide' }, [
        A.check(v, 'is_active', 'Se vinde', { on: mark }),
        A.check(v, 'is_refundable', 'Se poate returna', { on: mark }),
        A.check(v, 'pos_only', 'Doar la casă', { on: mark }),
      ]),
    ])]);
    drawUnit();
    var head = el('div', { class: 'am-var-head' }, [el('b', { text: (type === 'package' ? 'Prețul pachetului' : 'Biletul ' + (i + 1)) })]);
    return el('div', { class: 'am-var' }, [head, A.form([
      type === 'package' ? null : A.field('Numele biletului *', A.input(v, 'name', { max: 120, ph: type === 'experience' ? '30 de minute' : 'Adult', on: mark })),
      A.field('Prețul (lei) *', A.input(v, 'price', { type: 'number', min: 0, step: '0.01', ph: '41', on: mark })),
      type === 'package' ? null : A.field('Se vinde', A.select(v, 'price_type', [['per_person', 'Pe persoană'], ['per_unit', 'Pe unitate (barcă, mașină, cort)']], { keep: true, on: function () { mark(); drawUnit(); } })),
      perUnit,
      type === 'access' ? el('div', { class: 've-checks' }, [A.check(v, 'is_child', 'Bilet de copil', { on: mark })]) : null,
    ]), more]);
  }

  function scheduleRows(p) {
    var mark = function () { dirty = true; };
    return A.rows(p.schedules, function (s) {
      return [
        A.field('Ziua', A.select(s, 'day_of_week', WEEK, { num: true, keep: true, on: mark })),
        A.field('De la', A.input(s, 'open', { type: 'time', on: mark })),
        A.field('Până la', A.input(s, 'close', { type: 'time', on: mark })),
        A.field('Sezon de la (LL-ZZ)', A.input(s, 'season_start', { max: 5, ph: '04-01', on: mark })),
        A.field('până la (LL-ZZ)', A.input(s, 'season_end', { max: 5, ph: '10-31', on: mark })),
      ];
    }, function () {
      var last = p.schedules[p.schedules.length - 1];
      return { day_of_week: last ? (last.day_of_week % 7) + 1 : 1, open: last ? last.open : '09:00', close: last ? last.close : '18:00', season_start: last ? last.season_start : null, season_end: last ? last.season_end : null, is_active: true };
    }, { addLabel: 'Adaugă o zi', limit: 60, removeLabel: 'Șterge ziua', on: mark });
  }

  function drawEditor() {
    var p = cur, box = $('am-prod-edit'), type = p.product_type;
    var mark = function () { dirty = true; };
    hideListBits();
    box.hidden = false;
    box.textContent = '';
    var loc = locById(p.location_id);

    // ----- head -----
    box.appendChild(el('header', { class: 've-head' }, [el('div', null, [
      el('a', { class: 'am-back', href: '/organizator/produse' + (p.location_id ? '?locatie=' + p.location_id : '') }, [O.icon('arrow-left'), el('span', { text: 'Toate produsele' })]),
      el('p', { class: 've-eyebrow', text: TYPES[type] || 'Produs' }),
      el('h1', { class: 've-h', text: p.id ? (p.title || 'Produs') : (TYPES[type] || 'Produs') + ' nou' }),
      el('span', { class: 've-prod-tags' }, p.id ? A.statusTags(p) : []),
      el('p', { class: 've-lead', text: p.id ? A.statusHint(p) : 'Se salvează ca ciornă. Când e gata, îl trimiți spre aprobare; după aprobare apare pe site.' }),
    ])]));

    var SECS = [['am-p-main', 'Produsul'], ['am-p-tickets', 'Bilete și prețuri']];
    if (type !== 'package') SECS.push(['am-p-when', 'Când']);
    if (type === 'package') SECS.push(['am-p-pack', 'Conținut']);
    if (type !== 'package') SECS.push(['am-p-addons', 'Suplimente']);
    SECS.push(['am-p-details', 'Detalii'], ['am-p-photos', 'Poze'], ['am-p-options', 'Opțiuni']);
    box.appendChild(el('nav', { class: 'am-jump', 'aria-label': 'Secțiunile produsului' }, SECS.map(function (s) { return el('a', { href: '#' + s[0], text: s[1] }); })));

    // ----- main -----
    var locOpts = [[null, type === 'experience' ? 'Fără locație' : 'Alege locația']].concat(locations.map(function (l) { return [l.id, l.name || ('Locația ' + l.id)]; }));
    var catBox = el('span');
    function drawCats() {
      catBox.textContent = '';
      var lc = locById(p.location_id), cats = (lc && lc.display_categories) || [];
      if (!cats.length) { p.display_category = null; return; }
      catBox.appendChild(A.field('Categoria pe pagina locației', A.select(p, 'display_category', [[null, 'Fără categorie']].concat(cats.map(function (c) { return [c.id, c.name]; })), { on: mark }),
        { hint: 'Gruparea ta, folosită doar în lista de bilete de pe pagina locației.' }));
    }
    /* Where bilete.online files the product: the category pages, the city pages and the filters all read it, and
       it is the one thing that decides whether anyone browsing the site ever runs into the product. Not taken
       from the location on purpose — a boat rental at a museum is not a museum. */
    var mcatBox = el('span');
    function drawMcats() {
      mcatBox.textContent = '';
      var all = (meta && meta.categories) || [];
      var parents = all.filter(function (c) { return !c.parent_id; });
      if (!parents.length) { p.category_id = null; p.subcategory_id = null; return; }
      mcatBox.appendChild(A.field('Categoria pe bilete.online', A.select(p, 'category_id',
        [[null, 'Alege categoria']].concat(parents.map(function (c) { return [c.id, c.name]; })),
        { num: true, on: function () { p.subcategory_id = null; mark(); drawMcats(); } }),
        { hint: 'Unde apare produsul în listele și filtrele site-ului. Obligatorie când îl trimiți spre aprobare.' }));
      var subs = p.category_id ? all.filter(function (c) { return String(c.parent_id) === String(p.category_id); }) : [];
      if (!subs.length) {
        p.subcategory_id = null;
        return;
      }
      mcatBox.appendChild(A.field('Subcategoria', A.select(p, 'subcategory_id',
        [[null, 'Fără subcategorie']].concat(subs.map(function (c) { return [c.id, c.name]; })), { num: true, on: mark })));
    }
    box.appendChild(A.section('am-p-main', 'Produsul', null, [A.form([
      A.field('Titlul *', A.input(p, 'title', { max: 190, ph: type === 'access' ? 'Acces rezervație' : (type === 'experience' ? 'Închiriere barcă cu vâsle' : 'Familie 2+2 cu barcă'), on: mark }), { wide: true }),
      A.field('Locația' + (type === 'experience' ? '' : ' *'), A.select(p, 'location_id', locOpts, { num: true, on: function () { mark(); drawCats(); drawWhen(); } })),
      mcatBox,
      catBox,
      type === 'access' ? A.field('Ce fel de acces', A.select(p, 'access_kind', [['person', 'Persoane'], ['vehicle', 'Vehicul (parcare)'], ['camping', 'Camping'], ['other', 'Altceva']], { keep: true, on: mark })) : null,
      type === 'experience' ? A.field('Ce fel de experiență', A.select(p, 'service_type', [['rental', 'Închiriere'], ['guided', 'Tur ghidat'], ['workshop', 'Atelier'], ['other', 'Altceva']], { keep: true, on: mark })) : null,
      A.field('Iconița', A.input(p, 'icon', { max: 16, ph: '🎟️', on: mark }), { hint: 'Un emoji, apare lângă titlu în lista de bilete.' }),
      A.field('Subtitlu', A.input(p, 'subtitle', { max: 190, on: mark }), { wide: true }),
      A.field('Pe scurt', A.textarea(p, 'short_description', { max: 280, rows: 2, ph: 'Barcă pentru 4 persoane. Vestele sunt incluse.', on: mark }), { wide: true, hint: 'Apare sub titlu, în lista de bilete a locației.' }),
      A.field('Descrierea', A.textarea(p, 'description', { max: 20000, rows: 5, on: mark }), { wide: true }),
    ])]));
    drawMcats();
    drawCats();

    // ----- tickets -----
    var varBox = el('div', { class: 'am-stack' });
    function drawVariants() {
      varBox.textContent = '';
      p.variants.forEach(function (v, i) {
        var card = variantCard(v, i, type, p.booking_mode, drawVariants);
        if (type !== 'package' && p.variants.length > 1) {
          var rm = A.button('trash', 'Șterge biletul', 've-danger');
          rm.addEventListener('click', function () {
            // gone from the site at the next save; tickets already sold keep working
            p.variants.splice(i, 1);
            dirty = true;
            drawVariants();
          });
          card.querySelector('.am-var-head').appendChild(rm);
        }
        varBox.appendChild(card);
      });
    }
    drawVariants();
    var addVar = A.button('plus', 'Adaugă un bilet');
    addVar.hidden = type === 'package';
    addVar.addEventListener('click', function () {
      if (p.variants.length >= 30) return;
      var nv = blankVariant(type, false);
      if (type === 'access' && p.variants.length === 1) { nv.name = 'Copil'; nv.is_child = true; }
      p.variants.push(nv);
      dirty = true;
      drawVariants();
    });
    box.appendChild(A.section('am-p-tickets', type === 'package' ? 'Prețul pachetului' : 'Bilete și prețuri',
      type === 'access' ? 'Un rând pe fiecare fel de bilet: adult, copil, elev, grup. Prețurile sunt cele pe care le primești; comisionul bilete.online se adaugă peste, la client.'
        : (type === 'experience' ? 'Un rând pe fiecare variantă: durate diferite (30 de minute, o oră) sau persoană / barcă întreagă.' : 'Prețul întregului pachet. Ce conține îl alegi mai jos.'),
      [varBox, type === 'package' ? null : el('div', { class: 've-sec-tools' }, [addVar])]));

    // ----- when -----
    var whenBox = el('div', { class: 'am-stack' });
    function drawWhen() {
      whenBox.textContent = '';
      if (type === 'package') return;
      var lc = locById(p.location_id), hasSeasons = !!(lc && lc.seasons && lc.seasons.length);
      if (!hasSeasons) p.use_location_schedule = false;
      var modeSel = A.select(p, 'booking_mode', [['day', 'Toată ziua, fără oră'], ['slot', 'La o oră de început']], { keep: true, on: function () { mark(); drawWhen(); drawVariants(); } });
      whenBox.appendChild(A.form([
        A.field('Cum se folosește', modeSel, { hint: p.booking_mode === 'day' ? 'Vizitatorul alege ziua; biletul e valabil în programul zilei.' : 'Vizitatorul alege ziua și ora; locurile se numără pe fiecare oră.' }),
        p.booking_mode === 'day' ? A.field('Locuri pe zi', A.input(p, 'daily_capacity', { type: 'number', min: 1, ph: 'fără limită', on: mark }), { hint: 'Gol dacă nu e o limită.' }) : null,
      ]));
      if (p.booking_mode === 'slot') {
        whenBox.appendChild(A.form([
          A.field('Durata (minute)', A.input(p, 'duration_minutes', { type: 'number', min: 5, maxv: 1440, ph: '60', on: mark })),
          A.field('O nouă oră de început la fiecare (minute)', A.input(p, 'slot_interval_minutes', { type: 'number', min: 5, maxv: 1440, ph: '30', on: mark })),
          A.field('Cum se numără locurile', A.select(p, 'capacity_mode', [['per_slot', 'Locuri pe fiecare oră de început'], ['concurrent', 'Unități folosite în același timp (bărci, biciclete)']], { keep: true, on: mark }), { hint: 'La bărci: 10 bărci pot fi pe lac în același timp, oricare ar fi ora la care au plecat.' }),
          A.field(p.capacity_mode === 'concurrent' ? 'Câte unități ai' : 'Locuri pe oră', A.input(p, 'capacity_per_slot', { type: 'number', min: 1, maxv: 10000, ph: '10', on: mark })),
        ], 've-form-4'));
      }
      if (hasSeasons) {
        whenBox.appendChild(A.check(p, 'use_location_schedule', 'Folosește programul locației (sezoanele și zilele închise)', { on: function () { mark(); drawWhen(); } }));
      } else if (lc) {
        whenBox.appendChild(el('p', { class: 've-sub', text: 'Locația nu are încă program pe sezoane; îl poți pune la locație sau aici, pe produs.' }));
      }
      if (!p.use_location_schedule) {
        var sch = scheduleRows(p);
        whenBox.appendChild(el('p', { class: 've-sec-k', text: 'Programul produsului' }));
        whenBox.appendChild(el('p', { class: 've-sub', text: p.booking_mode === 'slot' ? 'Orele între care pornesc rezervările, pe fiecare zi a săptămânii. Sezonul e opțional (de pildă 04-01 → 10-31).' : 'Zilele și orele în care se poate folosi. Gol: în fiecare zi.' }));
        whenBox.appendChild(sch.box);
        whenBox.appendChild(el('div', { class: 've-sec-tools' }, [sch.addBtn]));
      }
      var exc = A.rows(p.exceptions, function (x) {
        return [
          A.field('Data', A.input(x, 'date', { type: 'date', on: mark })),
          el('div', { class: 've-checks' }, [A.check(x, 'is_closed', 'Închis', { on: mark })]),
          A.field('De la', A.input(x, 'open', { type: 'time', on: mark })),
          A.field('Până la', A.input(x, 'close', { type: 'time', on: mark })),
          A.field('Motivul', A.input(x, 'reason', { max: 190, ph: 'Sărbătoare', on: mark })),
        ];
      }, function () { return { date: null, is_closed: true, open: null, close: null, reason: null }; }, { addLabel: 'Adaugă o zi specială', limit: 200, removeLabel: 'Șterge ziua specială', on: mark });
      whenBox.appendChild(el('p', { class: 've-sec-k', text: 'Zile speciale' }));
      whenBox.appendChild(el('p', { class: 've-sub', text: 'O zi închisă sau cu alt program decât de obicei, doar pentru acest produs.' }));
      whenBox.appendChild(exc.box);
      whenBox.appendChild(el('div', { class: 've-sec-tools' }, [exc.addBtn]));
      whenBox.appendChild(A.form([
        A.field('Cu câte ore înainte se oprește vânzarea', A.input(p, 'booking_lead_time_hours', { type: 'number', min: 0, maxv: 720, ph: '0', on: mark })),
        A.field('Cu câte zile înainte se poate rezerva', A.input(p, 'booking_max_advance_days', { type: 'number', min: 1, maxv: 365, ph: 'ca la locație', on: mark })),
      ]));
    }
    if (type !== 'package') box.appendChild(A.section('am-p-when', 'Când se poate folosi', null, [whenBox]));
    drawWhen();

    // ----- package contents -----
    if (type === 'package') {
      var sum = el('p', { class: 've-pkg-sum' });
      var packBox = el('div', { class: 'am-stack' });
      var drawPack = function () {
        packBox.textContent = '';
        var choices = products.filter(function (x) { return x.type !== 'package' && x.id !== p.id && (!p.location_id || x.location_id === p.location_id || !x.location_id); });
        var r = A.rows(p.package_items, function (it) {
          var varSel = el('span');
          var fillVariants = function () {
            varSel.textContent = '';
            if (!it.product_id) return;
            productDetail(it.product_id).then(function (d) {
              var vs = ((d && d.variants) || []).filter(function (v) { return v.is_active; });
              if (it.variant_id && !vs.some(function (v) { return v.id === it.variant_id; })) it.variant_id = null;
              if (!it.variant_id && vs.length === 1) it.variant_id = vs[0].id;
              varSel.appendChild(A.field('Biletul', A.select(it, 'variant_id', [[null, 'Alege biletul']].concat(vs.map(function (v) { return [v.id, v.name + ' · ' + lei(v.price)]; })), { num: true, on: function () { mark(); updateSum(); } })));
              updateSum();
            });
          };
          fillVariants();
          return [
            A.field('Produsul', A.select(it, 'product_id', [[null, 'Alege']].concat(choices.map(function (x) { return [x.id, x.title + ' (' + (TYPES[x.type] || '') + ')']; })), { num: true, on: function () { it.variant_id = null; mark(); fillVariants(); } })),
            varSel,
            A.field('Câte', A.input(it, 'quantity', { type: 'number', min: 1, maxv: 50, ph: '1', on: function () { mark(); updateSum(); } })),
            A.field('Partea din preț (lei)', A.input(it, 'allocated_price', { type: 'number', min: 0, step: '0.01', ph: 'automat', on: function () { mark(); updateSum(); } })),
          ];
        }, function () { return { product_id: null, variant_id: null, quantity: 1, allocated_price: null }; }, { addLabel: 'Adaugă în pachet', limit: 20, removeLabel: 'Scoate din pachet', on: function () { mark(); updateSum(); } });
        packBox.appendChild(r.box);
        packBox.appendChild(el('div', { class: 've-sec-tools' }, [r.addBtn]));
        packBox.appendChild(sum);
        updateSum();
      };
      var updateSum = function () {
        var total = 0, known = true;
        p.package_items.forEach(function (it) {
          var d = it.product_id ? details[it.product_id] : null;
          var v = d && (d.variants || []).filter(function (x) { return x.id === it.variant_id; })[0];
          if (!v) { known = false; return; }
          total += (v.price || 0) * (it.quantity || 1);
        });
        var price = p.variants[0] ? (p.variants[0].price || 0) : 0;
        if (!p.package_items.length) { sum.hidden = true; return; }
        sum.hidden = false;
        sum.className = 've-pkg-sum ' + (!known ? 'is-wait' : (price <= total ? 'is-ok' : 'is-bad'));
        sum.textContent = !known ? 'Alege produsul și biletul pe fiecare rând ca să vezi economia.'
          : (price <= total ? 'Separat ar costa ' + lei(total) + '; cu pachetul clientul plătește ' + lei(price) + ' (economisește ' + lei(total - price) + ').'
            : 'Pachetul costă mai mult (' + lei(price) + ') decât biletele separat (' + lei(total) + ').');
      };
      box.appendChild(A.section('am-p-pack', 'Ce conține pachetul', 'La cumpărare, pachetul emite câte un bilet pentru fiecare parte. Experiențele cu oră își aleg ora la rezervare. „Partea din preț” împarte încasarea pe componente; gol = automat, proporțional.', [packBox]));
      drawPack();
    }

    // ----- add-ons -----
    if (type !== 'package') {
      var ad = A.rows(p.addons, function (a) {
        return [
          A.field('Suplimentul', A.input(a, 'name', { max: 120, ph: 'Pachet foto', on: mark })),
          A.field('Preț (lei)', A.input(a, 'price', { type: 'number', min: 0, step: '0.01', on: mark })),
          A.field('Incluse gratuit / bilet', A.input(a, 'included_qty', { type: 'number', min: 0, maxv: 50, ph: '0', on: mark })),
          A.field('Maxim plătite / bilet', A.input(a, 'max_per_unit', { type: 'number', min: 0, maxv: 50, ph: '1', on: mark })),
        ];
      }, function () { return { id: null, name: null, price: 0, included_qty: 0, max_per_unit: 1, is_active: true }; }, { addLabel: 'Adaugă un supliment', limit: 20, removeLabel: 'Șterge suplimentul', on: mark });
      box.appendChild(A.section('am-p-addons', 'Suplimente', 'Opționale, alese de client la fiecare bilet: poze, echipament, o oră în plus. La fiecare bilet clientul poate lua în total '
        + 'incluse + maxim plătite, iar pagina îi scrie limita. Pune 0 la „maxim plătite” dacă nu vrei să se cumpere peste ce e deja inclus.', [ad.box, el('div', { class: 've-sec-tools' }, [ad.addBtn])]));
    }

    // ----- details -----
    var langs = el('div', { class: 've-checks' });
    Object.keys(LANGS).forEach(function (k) {
      var i = el('input', { type: 'checkbox', value: k });
      i.checked = p.languages.indexOf(k) >= 0;
      i.addEventListener('change', function () { p.languages = p.languages.filter(function (x) { return x !== k; }); if (i.checked) p.languages.push(k); dirty = true; });
      langs.appendChild(el('label', { class: 'po-check' }, [i, el('span', { text: LANGS[k] })]));
    });
    box.appendChild(A.section('am-p-details', 'Detalii', null, [A.form([
      A.field('Unitatea de preț', A.input(p, 'unit_label', { max: 60, ph: type === 'access' ? 'persoană / zi' : 'barcă', on: mark }), { hint: 'Apare după preț: „41 lei / persoană / zi”.' }),
      type === 'experience' ? A.field('Punctul de întâlnire', A.input(p, 'meeting_point', { max: 500, ph: 'Debarcaderul de pe malul nordic', on: mark })) : null,
      A.field('Ce include, câte unul pe rând', A.lines(p, 'included_items', { rows: 3, ph: 'Veste de salvare\nVâsle' }), { wide: true }),
      type === 'experience' ? A.field('Ce nu include, câte unul pe rând', A.lines(p, 'not_included', { rows: 2 }), { wide: true }) : null,
      type === 'experience' ? A.field('De știut înainte, câte unul pe rând', A.lines(p, 'requirements', { rows: 2, ph: 'Încălțăminte comodă' }), { wide: true }) : null,
      type === 'experience' ? A.field('Vârsta minimă', A.input(p, 'age_min', { type: 'number', min: 0, maxv: 99, on: mark })) : null,
      type === 'experience' ? A.field('Vârsta maximă', A.input(p, 'age_max', { type: 'number', min: 0, maxv: 99, on: mark })) : null,
      A.field('Condiții de folosire', A.textarea(p, 'usage_terms', { max: 2000, rows: 2, ph: 'Biletul se arată la intrare, de pe telefon.', on: mark }), { wide: true }),
      A.field('Anulare', A.textarea(p, 'cancellation_policy', { max: 2000, rows: 2, ph: 'Anulare gratuită cu 24 de ore înainte.', on: mark }), { wide: true }),
    ]), type === 'experience' ? el('p', { class: 've-sec-k', text: 'Limbi' }) : null, type === 'experience' ? langs : null]));

    // ----- photos -----
    box.appendChild(A.section('am-p-photos', 'Poze', loc && loc.cover_image ? 'Fără poză proprie, produsul folosește poza locației.' : 'Recomandat 1200 × 900.', [
      el('p', { class: 've-sec-k', text: 'Poza principală' }),
      A.image(p, 'cover_image', 'product'),
      type === 'experience' ? el('p', { class: 've-sec-k', text: 'Galeria' }) : null,
      type === 'experience' ? A.gallery(p, 'gallery', 'product-gallery', 20) : null,
    ]));

    // ----- options -----
    box.appendChild(A.section('am-p-options', 'Opțiuni', null, [A.form([
      type === 'experience' ? A.field('Cere un bilet de acces în aceeași zi', A.select(p, 'access_requirement', [['none', 'Nu'], ['any', 'Da, un bilet de acces pentru fiecare'], ['adult', 'Da, un bilet de acces de adult (de pildă unul pe barcă)']], { keep: true, on: mark }), { wide: true, hint: 'Pe pagina experienței, biletele de acces ale locației apar lângă ea.' }) : null,
      meta && meta.has_secondary_issuer ? A.field('Firma care emite biletul', A.select(p, 'issuing_company', [['primary', 'Firma principală'], ['secondary', 'A doua firmă']], { keep: true, on: mark })) : null,
      el('div', { class: 've-checks is-wide' }, [
        A.check(p, 'requires_vehicle_info', 'Cere numărul de înmatriculare', { on: mark }),
        A.check(p, 'pos_only', 'Doar la casă (nu se vinde online)', { on: mark }),
      ]),
    ])]));

    // ----- save bar -----
    var save = A.button('check', 'Salvează', 'btn btn-primary');
    var submit = A.button('arrow-right', 'Trimite spre aprobare', 'btn btn-ghost');
    submit.hidden = !(p.review_status === 'draft' || p.review_status === 'rejected');
    var pub = A.button(p.is_published ? 'x' : 'check', p.is_published ? 'Ascunde de pe site' : 'Pune pe site', 'btn btn-ghost');
    pub.hidden = !(p.id && (p.review_status === 'approved' || !p.review_status));
    var dup = A.button('plus', 'Copiază', 'btn btn-ghost');
    dup.hidden = !p.id;
    var del = A.button('trash', 'Șterge', 've-danger');
    del.hidden = !p.id;
    var view = el('a', { class: 'btn btn-ghost', href: p.public_path || '#', target: '_blank', rel: 'noopener' }, [O.icon('arrow-right'), el('span', { text: 'Vezi pe site' })]);
    view.hidden = !(p.id && p.is_published && p.public_path && (!p.review_status || p.review_status === 'approved'));
    save.addEventListener('click', function () { doSave().then(function (ok) { if (ok) O.flash(ok.message || 'Salvat.'); }); });
    submit.addEventListener('click', function () {
      doSave().then(function (ok) {
        if (!ok) return;
        return A.api('/products/' + cur.id + '/submit', { method: 'POST', body: {} }).then(function (r) {
          O.flash((r && r.message) || 'Trimis spre aprobare.');
          cur = normalize(r.data.product);
          drawEditor();
        }, function (err) { O.flash(A.errText(err, 'Nu am putut trimite produsul.'), true); });
      });
    });
    pub.addEventListener('click', function () {
      A.api('/products/' + cur.id + '/publish', { method: 'POST', body: { published: !cur.is_published } }).then(function (r) {
        O.flash((r && r.message) || 'Gata.');
        cur = normalize(r.data.product);
        drawEditor();
      }, function (err) { O.flash(A.errText(err, 'Nu am putut schimba vizibilitatea.'), true); });
    });
    dup.addEventListener('click', function () { duplicate(cur.id); });
    var armed = false;
    del.addEventListener('click', function () {
      if (!armed) { armed = true; del.lastChild.textContent = 'Apasă din nou ca să ștergi'; setTimeout(function () { armed = false; del.lastChild.textContent = 'Șterge'; }, 4000); return; }
      A.api('/products/' + cur.id, { method: 'DELETE' }).then(function (r) {
        dirty = false;
        products = products.filter(function (x) { return x.id !== cur.id; });
        O.flash((r && r.message) || 'Produsul a fost șters.');
        go('/organizator/produse', true);
      }, function (err) { O.flash(A.errText(err, 'Nu am putut șterge produsul.'), true); });
    });
    box.appendChild(el('div', { class: 'am-bar' }, [el('div', { class: 'am-bar-in' }, [del, el('span', { class: 'am-bar-gap' }), view, dup, pub, submit, save])]));
  }

  function payload(p) {
    var keys = ['product_type', 'location_id', 'access_kind', 'service_type', 'title', 'subtitle', 'short_description', 'description', 'icon',
      'category_id', 'subcategory_id', 'display_category', 'booking_mode', 'capacity_mode', 'capacity_per_slot', 'daily_capacity', 'duration_minutes', 'slot_interval_minutes',
      'booking_lead_time_hours', 'booking_max_advance_days', 'use_location_schedule', 'access_requirement', 'requires_vehicle_info', 'pos_only',
      'issuing_company', 'unit_label', 'usage_terms', 'meeting_point', 'languages', 'included_items', 'not_included', 'requirements',
      'cancellation_policy', 'age_min', 'age_max'];
    var out = {};
    keys.forEach(function (k) { out[k] = p[k] === undefined ? null : p[k]; });
    if (p.product_type === 'package') out.booking_mode = 'day';
    if (p.product_type !== 'experience') out.access_requirement = 'none';
    out.cover_image = A.path(p.cover_image);
    out.gallery = (p.gallery || []).map(A.path).filter(Boolean);
    out.variants = p.variants.map(function (v) {
      var o = {};
      ['id', 'name', 'description', 'price', 'price_type', 'persons_min', 'persons_max', 'is_child', 'min_age', 'max_age', 'duration_minutes',
        'validity_days', 'min_per_order', 'max_per_order', 'step_qty', 'companion_label', 'pos_price', 'pos_only', 'capacity_share', 'is_active', 'is_refundable']
        .forEach(function (k) { o[k] = v[k] === undefined ? null : v[k]; });
      if (!o.name) o.name = p.product_type === 'package' ? 'Pachet' : null;
      if (o.price_type !== 'per_unit') o.persons_max = o.persons_max || null;
      return o;
    });
    out.schedules = p.use_location_schedule ? [] : p.schedules.filter(function (s) { return s.open && s.close; }).map(function (s) {
      return { day_of_week: s.day_of_week, open: s.open, close: s.close, season_start: s.season_start || null, season_end: s.season_end || null, is_active: s.is_active !== false };
    });
    out.exceptions = p.exceptions.filter(function (x) { return x.date; }).map(function (x) {
      return { date: x.date, is_closed: !!x.is_closed, open: x.is_closed ? null : (x.open || null), close: x.is_closed ? null : (x.close || null), reason: x.reason || null };
    });
    out.addons = p.product_type === 'package' ? [] : p.addons.filter(function (a) { return a.name; }).map(function (a) {
      return { id: a.id || null, name: a.name, price: a.price || 0, included_qty: a.included_qty || 0, max_per_unit: a.max_per_unit == null ? 1 : a.max_per_unit, is_active: a.is_active !== false };
    });
    out.package_items = p.product_type === 'package' ? p.package_items.filter(function (i) { return i.product_id; }).map(function (i) {
      return { product_id: i.product_id, variant_id: i.variant_id || null, quantity: i.quantity || 1, allocated_price: i.allocated_price };
    }) : [];
    return out;
  }
  function doSave() {
    if (busy) return Promise.resolve(false);
    if (!cur.title) { O.flash('Scrie titlul produsului.', true); return Promise.resolve(false); }
    if (cur.product_type !== 'experience' && !cur.location_id) { O.flash('Alege locația.', true); return Promise.resolve(false); }
    busy = true;
    root.classList.add('is-busy');
    var isNew = !cur.id;
    var req = isNew ? A.api('/products', { method: 'POST', body: payload(cur) }) : A.api('/products/' + cur.id, { method: 'PUT', body: payload(cur) });
    return req.then(function (r) {
      var saved = r && r.data && r.data.product;
      if (saved) {
        details[saved.id] = saved;
        cur = normalize(JSON.parse(JSON.stringify(saved)));
        dirty = false;
        products = products.filter(function (x) { return x.id !== saved.id; }).concat([saved]);
        if (isNew) history.replaceState(null, '', '/organizator/produse?id=' + cur.id);
        drawEditor();
      }
      return { message: r && r.message };
    }, function (err) {
      O.flash(A.errText(err, 'Nu am putut salva produsul.'), true);
      return false;
    }).then(function (res) { busy = false; root.classList.remove('is-busy'); return res; });
  }

  /* =================== routing =================== */
  function route() {
    var q = params();
    var id = parseInt(q.get('id'), 10);
    if (id > 0) openEditor(id);
    else if (q.get('nou')) loadBase().then(chooseType);
    else showList();
    window.scrollTo(0, 0);
  }
  window.addEventListener('popstate', route);
  window.addEventListener('beforeunload', function (e) { if (dirty) { e.preventDefault(); e.returnValue = ''; } });
  root.addEventListener('click', function (e) {
    var a = e.target.closest('a[href^="/organizator/produse"]');
    if (!a || e.ctrlKey || e.metaKey || e.shiftKey || a.target === '_blank') return;
    if (dirty && !window.confirm('Ai modificări nesalvate. Pleci fără să le salvezi?')) { e.preventDefault(); return; }
    e.preventDefault();
    dirty = false;
    go(a.getAttribute('href'));
  });
  $('am-prod-edit').addEventListener('input', function () { dirty = true; });
  $('am-prod-edit').addEventListener('change', function () { dirty = true; });
  $('am-f-loc').addEventListener('change', function () {
    var v = $('am-f-loc').value;
    history.replaceState(null, '', '/organizator/produse' + (v ? '?locatie=' + v : ''));
    drawList();
  });
  $('am-f-type').addEventListener('change', drawList);
  $('am-prod-retry').addEventListener('click', function () { $('am-prod-failed').hidden = true; route(); });

  O.ready.then(function (ok) { if (ok) route(); });
})();
