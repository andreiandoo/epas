/* viaqui.com v2: the venue's tickets and services (/organizator/locatie/bilete), ported from Ambilet's leisure.php.
   Two lists and one dialog. The display categories live in the venue config and are saved as a whole list (order,
   names in three languages, a picture each). The products keep their order through .../products/reorder. The dialog
   shows only the sections that fit what the product is (variants, add-ons, departures, inventory, access and blocked
   hours for rentals and activities; components for packages), and sends the fields and meta keys core accepts.
   Pictures go up as multipart through the proxy. Runs inside the organizer shell (window.BO_ORG); text from the API is
   always written as text. */
(function () {
  'use strict';
  var O = window.BO_ORG, root = document.getElementById('ve');
  if (!O || !root) return;
  var F = O.fmt, el = O.el;
  var $ = function (id) { return document.getElementById(id); };
  var qsa = function (sel, ctx) { return Array.prototype.slice.call((ctx || root).querySelectorAll(sel)); };
  var CAT = { access: VQ.t('Access'), parking: VQ.t('Parking'), rental: VQ.t('Rental'), activity: VQ.t('Activity'), extra: VQ.t('Extra'), package: VQ.t('Package') };
  var TR_FIELDS = ['name', 'description', 'unit_label', 'includes', 'usage_terms'];
  var MAX_IMAGE = 10 * 1024 * 1024;
  var eventId = null, products = [], cats = [], edit = null, delArmed = false, lastFocus = null;

  function txt(v) { return F.flat(v).trim(); }
  function show(id, on) { var e = $(id); if (e) e.hidden = !on; }
  function num(v) { var n = parseFloat(v); return isFinite(n) ? n : null; }
  function int(v) { var n = parseInt(v, 10); return isFinite(n) ? n : null; }
  function lei(v) { return F.money(F.toNum(v)); }
  function imgSrc(u) {
    if (typeof u !== 'string' || !u.trim()) return null;
    u = u.trim();
    if (/^\/(?!\/)/.test(u)) return u;
    try { var x = new URL(u); return x.protocol === 'https:' ? x.href : null; } catch (e) { return null; }
  }
  function slug(s) {
    return String(s || '').toLowerCase().normalize('NFD').replace(/[̀-ͯ]/g, '').replace(/[^a-z0-9]+/g, '-').replace(/^-|-$/g, '').slice(0, 32);
  }
  function btn(icon, label, cls, aria) {
    var b = el('button', { class: cls || 'btn btn-ghost', type: 'button', 'aria-label': aria || null });
    if (icon) b.appendChild(O.icon(icon));
    if (label) b.appendChild(document.createTextNode(label));
    return b;
  }
  function upload(file) {
    if (!file) return Promise.reject(new Error(VQ.t('No file.')));
    if (!/^image\/(jpeg|png|webp)$/.test(file.type)) return Promise.reject(new Error(VQ.t('JPG, PNG or WebP only.')));
    if (file.size > MAX_IMAGE) return Promise.reject(new Error(VQ.t('The picture is larger than 10 MB.')));
    var fd = new FormData();
    fd.append('image', file);
    return BileteOnlineAPI._postMultipart('/organizer/events/' + eventId + '/leisure/upload-image', fd).then(function (r) {
      var d = (r && r.data) || {};
      if (!d.url && !d.path) throw new Error(VQ.t('The upload did not return the picture.'));
      return d;
    });
  }

  /* =================== loading =================== */
  function loadAll() {
    var list = $('vi-list');
    list.textContent = '';
    list.appendChild(el('p', { class: 've-state', text: VQ.t('Loading…') }));
    return Promise.all([
      O.api('/organizer/events/' + eventId + '/leisure/products'),
      O.api('/organizer/events/' + eventId + '/leisure/config', { quiet: true }).then(null, function () { return null; }),
    ]).then(function (res) {
      products = ((res[0] && res[0].data && res[0].data.products) || []).filter(function (p) { return p && p.id != null; });
      var raw = (res[1] && res[1].data && res[1].data.ticket_categories) || [];
      cats = raw.map(function (c) {
        var n = c.name, ro = typeof n === 'object' && n ? (n.ro || '') : (n || '');
        return { id: String(c.id || ''), ro: ro, hu: typeof n === 'object' && n ? (n.hu || '') : '', en: typeof n === 'object' && n ? (n.en || '') : '', image: c.image || null, image_url: c.image_url || null };
      }).filter(function (c) { return c.id && (c.ro || c.hu || c.en); });
      drawCats();
      drawProducts();
    }, function (err) {
      if (err && err.status === 401) return;
      list.textContent = '';
      list.appendChild(el('p', { class: 've-state', text: VQ.t('We could not load the products.') }));
    });
  }

  /* =================== display categories =================== */
  function catName(c) { return c.ro || c.en || c.hu || c.id; }
  function drawCats() {
    var list = $('vi-cats');
    list.textContent = '';
    $('vi-cats-n').textContent = cats.length ? '(' + cats.length + ')' : '';
    if (!cats.length) { list.appendChild(el('li', { class: 've-state', text: VQ.t('No categories yet.') })); return; }
    cats.forEach(function (c, i) {
      var file = el('input', { type: 'file', accept: 'image/jpeg,image/png,image/webp', class: 've-sr', id: 'vi-cat-img-' + i });
      var pic = el('label', { class: 've-thumb', for: 'vi-cat-img-' + i, title: VQ.t('Category picture') });
      var src = imgSrc(c.image_url || c.image);
      if (src) pic.appendChild(el('img', { src: src, alt: '' }));
      else pic.appendChild(O.icon('plus'));
      file.addEventListener('change', function () {
        var f = file.files && file.files[0];
        if (!f) return;
        pic.classList.add('is-busy');
        upload(f).then(function (d) {
          c.image = d.path || d.url;
          c.image_url = d.url || null;
          drawCats();
          O.flash(VQ.t('The picture is in place. Save the categories to keep it.'));
        }, function (err) {
          pic.classList.remove('is-busy');
          O.flash((err && err.message) || VQ.t('We could not upload the picture.'), true);
        });
      });
      var ro = el('input', { class: 'po-input', value: c.ro, maxlength: 80, 'aria-label': VQ.t('Name in Romanian'), placeholder: VQ.t('Romanian') });
      var hu = el('input', { class: 'po-input', value: c.hu, maxlength: 80, 'aria-label': VQ.t('Name in Hungarian'), placeholder: VQ.t('Hungarian') });
      var en = el('input', { class: 'po-input', value: c.en, maxlength: 80, 'aria-label': VQ.t('Name in English'), placeholder: VQ.t('English') });
      ro.addEventListener('input', function () { c.ro = ro.value; });
      hu.addEventListener('input', function () { c.hu = hu.value; });
      en.addEventListener('input', function () { c.en = en.value; });
      var up = btn('caret-down', null, 've-icon-btn is-up', VQ.t('Move up')), down = btn('caret-down', null, 've-icon-btn', VQ.t('Move down'));
      up.disabled = i === 0;
      down.disabled = i === cats.length - 1;
      up.addEventListener('click', function () { cats.splice(i - 1, 0, cats.splice(i, 1)[0]); drawCats(); });
      down.addEventListener('click', function () { cats.splice(i + 1, 0, cats.splice(i, 1)[0]); drawCats(); });
      var rm = btn('trash', null, 've-icon-btn', VQ.t('Delete the category'));
      rm.addEventListener('click', function () {
        if (!rm.classList.contains('is-armed')) { rm.classList.add('is-armed'); rm.setAttribute('aria-label', VQ.t('Press again to delete')); O.flash(VQ.t('Press again to delete the category. Its products move to "Other products".')); return; }
        cats.splice(i, 1);
        drawCats();
      });
      list.appendChild(el('li', { class: 've-cat' }, [
        pic, file,
        el('span', { class: 've-cat-names' }, [ro, hu, en]),
        el('code', { class: 've-sub ve-mono', text: c.id, title: VQ.t('The category\'s internal code') }),
        el('span', { class: 've-cat-tools' }, [up, down, rm]),
      ]));
    });
  }
  function addCat() {
    var name = $('vi-cat-new').value.trim();
    if (!name) { O.flash(VQ.t('Enter the category name.'), true); $('vi-cat-new').focus(); return; }
    var base = slug(name) || 'categorie', id = base, n = 2;
    while (cats.some(function (c) { return c.id === id; })) { id = base + '-' + n; n++; }
    cats.push({ id: id, ro: name, hu: '', en: '', image: null, image_url: null });
    $('vi-cat-new').value = '';
    drawCats();
  }
  function saveCats() {
    var clean = cats.map(function (c, i) {
      var tr = {};
      ['ro', 'hu', 'en'].forEach(function (k) { var v = String(c[k] || '').trim(); if (v) tr[k] = v; });
      var keys = Object.keys(tr);
      return { id: c.id, name: keys.length === 1 && tr.ro ? tr.ro : tr, sort_order: (i + 1) * 10, image: c.image || null };
    }).filter(function (c) { return c.id && (typeof c.name === 'string' ? c.name : Object.keys(c.name).length); });
    var b = $('vi-cats-save');
    b.disabled = true;
    O.api('/organizer/events/' + eventId + '/leisure/venue-config', { method: 'PUT', body: { venue_config: { ticket_categories: clean } } }).then(function () {
      O.flash(VQ.t('The categories were saved.'));
      loadAll();
    }, function (err) {
      O.flash((err && err.message) || VQ.t('We could not save the categories.'), true);
    }).then(function () { b.disabled = false; });
  }

  /* =================== products =================== */
  function groups() {
    var out = [], used = {};
    cats.forEach(function (c) {
      var items = products.filter(function (p) { return p.ticket_group === c.id; });
      items.forEach(function (p) { used[p.id] = true; });
      if (items.length) out.push({ id: c.id, name: catName(c), items: items });
    });
    var rest = products.filter(function (p) { return !used[p.id]; });
    if (rest.length) out.push({ id: '', name: VQ.t('Other products'), items: rest });
    return out;
  }
  function drawProducts() {
    var list = $('vi-list');
    list.textContent = '';
    if (!products.length) { list.appendChild(el('p', { class: 've-state', text: VQ.t('No products yet. Add the first ticket.') })); return; }
    groups().forEach(function (g) {
      var box = el('div', { class: 've-prod-group' }, el('p', { class: 've-sec-k', text: g.name + ' · ' + g.items.length }));
      g.items.forEach(function (p, i) { box.appendChild(productCard(p, g, i)); });
      list.appendChild(box);
    });
  }
  function productCard(p, g, i) {
    var meta = p.meta || {}, media = el('span', { class: 've-prod-media' }), src = imgSrc(meta.image || meta.image_url);
    if (src) media.appendChild(el('img', { src: src, alt: '' }));
    else media.appendChild(el('span', { text: txt(meta.icon) || '🎫' }));
    var tags = el('span', { class: 've-prod-tags' }, [
      el('span', { class: 'org-tag is-info', text: CAT[p.service_category] || txt(p.service_category) || VQ.t('Access') }),
      el('span', { class: 'org-tag is-muted', text: p.issuing_company === 'secondary' ? VQ.t('Second company') : p.issuing_company === 'mix' ? VQ.t('Both companies') : VQ.t('Main company') }),
    ]);
    if (!p.is_active) tags.appendChild(el('span', { class: 'org-tag is-bad', text: VQ.t('Off') }));
    if (p.pos_only) tags.appendChild(el('span', { class: 'org-tag is-wait', text: VQ.t('Register only') }));
    var facts = [];
    if (p.daily_capacity) facts.push(VQ.t('{n} per day', { n: F.num(p.daily_capacity) }));
    facts.push(p.capacity ? VQ.t('stock {n}', { n: F.num(p.capacity) }) : VQ.t('unlimited stock'));
    if (p.min_per_order > 1) facts.push(VQ.t('minimum {n}', { n: F.num(p.min_per_order) }));
    var up = btn('caret-down', null, 've-icon-btn is-up', VQ.t('Move up')), down = btn('caret-down', null, 've-icon-btn', VQ.t('Move down')), ed = btn('pencil-simple', VQ.t('Edit'));
    up.disabled = i === 0;
    down.disabled = i === g.items.length - 1;
    up.addEventListener('click', function () { move(g, i, -1); });
    down.addEventListener('click', function () { move(g, i, 1); });
    ed.addEventListener('click', function () { openProduct(p); });
    var price = el('span', { class: 've-prod-price' }, [el('b', { text: lei(p.price) }), el('small', { class: 've-sub', text: '/ ' + (txt(meta.unit_label) || VQ.t('item')) })]);
    if (p.pos_price != null) price.appendChild(el('small', { class: 've-sub', text: VQ.t('at the register {price}', { price: lei(p.pos_price) }) }));
    return el('article', { class: 've-prod' + (p.is_active ? '' : ' is-off'), 'data-id': String(p.id) }, [
      media,
      el('span', { class: 've-prod-t' }, [el('b', { text: txt(p.name) || '—' }), tags, txt(p.description) ? el('small', { class: 've-sub', text: txt(p.description) }) : null, el('small', { class: 've-sub', text: facts.join(' · ') })]),
      price,
      el('span', { class: 've-prod-tools' }, [up, down, ed]),
    ]);
  }
  function move(g, i, dir) {
    var j = i + dir;
    if (j < 0 || j >= g.items.length) return;
    var a = g.items[i], b = g.items[j], ia = products.indexOf(a), ib = products.indexOf(b);
    products[ia] = b;
    products[ib] = a;
    drawProducts();
    var ids = [];
    groups().forEach(function (gr) { gr.items.forEach(function (p) { ids.push(F.toNum(p.id)); }); });
    O.api('/organizer/events/' + eventId + '/leisure/products/reorder', { method: 'POST', body: { ids: ids } }).then(null, function (err) {
      O.flash((err && err.message) || VQ.t('We could not save the order.'), true);
      loadAll();
    });
  }

  /* =================== the dialog: repeatable rows =================== */
  function rowShell(kids, onRemove) {
    var rm = btn('trash', null, 've-icon-btn', VQ.t('Remove the row'));
    var row = el('div', { class: 've-row' }, kids.concat([rm]));
    rm.addEventListener('click', function () { row.remove(); if (onRemove) onRemove(); });
    return row;
  }
  function field(label, input) { return el('span', { class: 'po-field' }, [el('label', { text: label }), input]); }
  function mini(attrs, value) {
    var i = el('input', Object.assign({ class: 'po-input' }, attrs));
    if (value != null && value !== '') i.value = String(value);
    return i;
  }
  function variantRow(v) {
    v = v || {};
    return rowShell([
      field(VQ.t('Label'), mini({ 'data-k': 'label', maxlength: 80, placeholder: VQ.t('30 minutes') }, v.label)),
      field(VQ.t('Minutes'), mini({ 'data-k': 'duration_minutes', type: 'number', min: 0, inputmode: 'numeric' }, v.duration_minutes)),
      field(VQ.t('Price (€)'), mini({ 'data-k': 'price', type: 'number', min: 0, step: '0.01', inputmode: 'decimal' }, v.price)),
      field(VQ.t('Code'), mini({ 'data-k': 'id', maxlength: 32, placeholder: VQ.t('automatic'), class: 'po-input ve-mono' }, v.id)),
    ]);
  }
  function addonRow(a) {
    a = a || {};
    return rowShell([
      field(VQ.t('Label'), mini({ 'data-k': 'label', maxlength: 80, placeholder: VQ.t('Extra towing') }, a.label)),
      field(VQ.t('Price (€)'), mini({ 'data-k': 'price', type: 'number', min: 0, step: '0.01', inputmode: 'decimal' }, a.price)),
      field(VQ.t('Included'), mini({ 'data-k': 'included_qty', type: 'number', min: 0, inputmode: 'numeric' }, a.included_qty != null ? a.included_qty : 0)),
      field(VQ.t('Paid maximum'), mini({ 'data-k': 'max_per_unit', type: 'number', min: 0, inputmode: 'numeric' }, a.max_per_unit != null ? a.max_per_unit : 5)),
      field(VQ.t('Code'), mini({ 'data-k': 'id', maxlength: 32, placeholder: VQ.t('automatic'), class: 'po-input ve-mono' }, a.id)),
    ]);
  }
  function blockRow(b) {
    b = b || {};
    return rowShell([
      field(VQ.t('Day'), mini({ 'data-k': 'date', type: 'date' }, b.date)),
      field(VQ.t('From'), mini({ 'data-k': 'start_time', type: 'time' }, b.start_time)),
      field(VQ.t('To'), mini({ 'data-k': 'end_time', type: 'time' }, b.end_time)),
      field(VQ.t('Reason'), mini({ 'data-k': 'reason', maxlength: 200, placeholder: VQ.t('Private group') }, b.reason)),
    ]);
  }
  function packageRow(o) {
    o = o || {};
    var comp = el('select', { 'data-k': 'ticket_type_id' });
    comp.appendChild(new Option(VQ.t('Choose the product'), ''));
    products.filter(function (p) { return p.service_category !== 'package' && (!edit || p.id !== edit.id); }).forEach(function (p) {
      comp.appendChild(new Option(txt(p.name) + ' · ' + (CAT[p.service_category] || ''), String(p.id)));
    });
    comp.value = o.ticket_type_id != null ? String(o.ticket_type_id) : '';
    var variant = el('select', { 'data-k': 'variant_id' });
    var fillVariants = function (keep) {
      variant.textContent = '';
      variant.appendChild(new Option(VQ.t('No variant'), ''));
      var p = products.filter(function (x) { return String(x.id) === comp.value; })[0];
      (p && Array.isArray(p.variants) ? p.variants : []).forEach(function (v) { variant.appendChild(new Option(txt(v.label) + ' · ' + lei(v.price), String(v.id))); });
      variant.value = keep || '';
      variant.disabled = variant.options.length < 2;
    };
    fillVariants(o.variant_id);
    comp.addEventListener('change', function () { fillVariants(''); pkgSummary(); });
    variant.addEventListener('change', pkgSummary);
    var qty = mini({ 'data-k': 'qty', type: 'number', min: 1, inputmode: 'numeric' }, o.qty != null ? o.qty : 1);
    var alloc = mini({ 'data-k': 'price', type: 'number', min: 0, step: '0.01', inputmode: 'decimal', placeholder: VQ.t('optional') }, o.price);
    qty.addEventListener('input', pkgSummary);
    alloc.addEventListener('input', pkgSummary);
    return rowShell([
      el('span', { class: 'po-field ve-row-wide' }, [el('label', { text: VQ.t('Product') }), el('span', { class: 'po-select' }, [comp, O.icon('caret-down')])]),
      el('span', { class: 'po-field' }, [el('label', { text: VQ.t('Variant') }), el('span', { class: 'po-select' }, [variant, O.icon('caret-down')])]),
      field(VQ.t('Quantity'), qty),
      field(VQ.t('Share of price (€)'), alloc),
    ], pkgSummary);
  }
  function collectRows(id, map) {
    return qsa('.ve-row', $(id)).map(function (row) {
      var item = {};
      qsa('[data-k]', row).forEach(function (i) { var v = String(i.value || '').trim(); if (v !== '') item[i.getAttribute('data-k')] = v; });
      return map(item);
    }).filter(Boolean);
  }
  function unitPrice(o) {
    var p = products.filter(function (x) { return x.id === o.ticket_type_id; })[0];
    if (!p) return null;
    var unit = F.toNum(p.price);
    if (o.variant_id && Array.isArray(p.variants)) {
      var v = p.variants.filter(function (x) { return String(x.id) === o.variant_id; })[0];
      if (v) unit = F.toNum(v.price);
    }
    return unit;
  }
  function packageOutputs() {
    return collectRows('vi-package', function (it) {
      if (!it.ticket_type_id) return null;
      var out = { ticket_type_id: parseInt(it.ticket_type_id, 10), qty: Math.max(1, int(it.qty) || 1) };
      if (it.variant_id) out.variant_id = it.variant_id;
      var px = num(it.price);
      if (px != null && px >= 0) out.price = Math.round(px * 100) / 100;
      return out;
    });
  }
  function pkgSummary() {
    var box = $('vi-pkg-sum'), outs = packageOutputs(), price = num($('vi-price').value) || 0, issuer = $('vi-issuer').value;
    var sum = 0, allocSum = 0, allocN = 0;
    outs.forEach(function (o) {
      var u = unitPrice(o);
      if (u != null) sum += u * o.qty;
      if (typeof o.price === 'number') { allocSum += o.price; allocN++; }
    });
    var parts = [];
    if (sum > 0 && price > 0) {
      var save = Math.round((sum - price) * 100) / 100;
      parts.push(save > 0 ? VQ.t('The components are worth {sum} · the customer saves {save} ({pct}%)', { sum: lei(sum), save: lei(save), pct: Math.round(save / sum * 100) })
        : save < 0 ? VQ.t('The components are worth {sum} · the package costs {more} more than its components', { sum: lei(sum), more: lei(-save) })
        : VQ.t('The components are worth {sum} · the price equals the sum of the components', { sum: lei(sum) }));
    }
    var state = '';
    if (allocN > 0 || issuer === 'mix') {
      var delta = Math.round((allocSum - price) * 100) / 100;
      state = Math.abs(delta) < 0.01 ? (allocN === outs.length ? 'is-ok' : 'is-wait') : 'is-bad';
      var sp = { done: lei(allocSum), price: lei(price) };
      parts.push(Math.abs(delta) < 0.01
        ? (allocN === outs.length ? VQ.t('Split {done} of {price} · correct', sp)
          : VQ.t('Split {done} of {price} · {missing}', { done: sp.done, price: sp.price, missing: VQ.n(outs.length - allocN, 'component without an amount', 'components without an amount') }))
        : delta > 0 ? VQ.t('Split {done} of {price} · {over} above the package price', { done: sp.done, price: sp.price, over: lei(delta) })
          : VQ.t('Split {done} of {price} · {left} still to split', { done: sp.done, price: sp.price, left: lei(-delta) }));
    }
    box.textContent = parts.join('. ');
    box.className = 've-note-line ve-pkg-sum ' + state;
    box.hidden = !parts.length;
    show('vi-mix-hint', issuer === 'mix');
  }
  function autoAllocate() {
    var price = num($('vi-price').value) || 0;
    if (price <= 0) { O.flash(VQ.t('Set the package price first.'), true); return; }
    var rows = qsa('.ve-row', $('vi-package')).map(function (row) {
      var o = { ticket_type_id: parseInt(row.querySelector('[data-k="ticket_type_id"]').value, 10), variant_id: row.querySelector('[data-k="variant_id"]').value, qty: Math.max(1, int(row.querySelector('[data-k="qty"]').value) || 1) };
      var u = unitPrice(o);
      return { row: row, ref: u == null ? 0 : u * o.qty };
    });
    var total = rows.reduce(function (s, r) { return s + r.ref; }, 0);
    if (total <= 0) { O.flash(VQ.t('The components have no starting price.'), true); return; }
    var given = 0;
    rows.forEach(function (r, k) {
      var v = k === rows.length - 1 ? Math.round((price - given) * 100) / 100 : Math.round(price * r.ref / total * 100) / 100;
      if (k < rows.length - 1) given += v;
      r.row.querySelector('[data-k="price"]').value = v.toFixed(2);
    });
    pkgSummary();
  }

  /* =================== the dialog =================== */
  function applyCategory() {
    var cat = $('vi-cat').value, pkg = cat === 'package';
    qsa('[data-when]', $('vi-modal')).forEach(function (n) { n.hidden = n.getAttribute('data-when').split(' ').indexOf(cat) === -1; });
    var mix = $('vi-issuer').querySelector('option[value="mix"]');
    mix.disabled = !pkg;
    mix.hidden = !pkg;
    if (!pkg && $('vi-issuer').value === 'mix') $('vi-issuer').value = 'primary';
    if (pkg) pkgSummary(); else show('vi-mix-hint', false);
  }
  function setImage(url) {
    var src = imgSrc(url), thumb = $('vi-image-thumb');
    $('vi-image-box').setAttribute('data-value', url || '');
    if (src) thumb.src = src; else thumb.removeAttribute('src');
    thumb.hidden = !src;
    show('vi-image-empty', !src);
    show('vi-image-rm', !!url);
    $('vi-image-label').textContent = url ? VQ.t('Change the picture') : VQ.t('Choose a picture');
  }
  function fillGroups(value) {
    var s = $('vi-group');
    s.textContent = '';
    s.appendChild(new Option(VQ.t('Other products'), ''));
    cats.forEach(function (c) { s.appendChild(new Option(catName(c), c.id)); });
    s.value = cats.some(function (c) { return c.id === value; }) ? value : '';
  }
  function setVal(id, v) { $(id).value = v == null ? '' : String(v); }
  function openProduct(p) {
    edit = p || null;
    delArmed = false;
    lastFocus = document.activeElement;
    var m = (p && p.meta) || {};
    $('vi-modal-h').textContent = p ? VQ.t('Edit the product') : VQ.t('Add a product');
    setVal('vi-name', p ? txt(p.name) : '');
    $('vi-cat').value = p && CAT[p.service_category] ? p.service_category : 'access';
    $('vi-issuer').value = p && ['primary', 'secondary', 'mix'].indexOf(p.issuing_company) > -1 ? p.issuing_company : 'primary';
    fillGroups(p && p.ticket_group);
    setVal('vi-sku', p && p.sku);
    setVal('vi-price', p ? F.toNum(p.price).toFixed(2) : '');
    setVal('vi-pos-price', p && p.pos_price != null ? p.pos_price : '');
    setVal('vi-capacity', p && p.capacity);
    setVal('vi-daily', p && p.daily_capacity);
    setVal('vi-min', p && p.min_per_order > 0 ? p.min_per_order : '');
    setVal('vi-max', p && p.max_per_order > 0 ? p.max_per_order : '');
    setVal('vi-step', m.step_qty);
    setVal('vi-duration', p && p.service_duration_minutes);
    setVal('vi-icon', m.icon);
    setVal('vi-unit', m.unit_label);
    $('vi-is-group').checked = !!m.is_group_ticket;
    $('vi-guide').checked = !!m.group_includes_guide;
    setVal('vi-guide-label', m.group_guide_label);
    show('vi-group-extra', !!m.is_group_ticket);
    setImage(m.image || m.image_url || '');
    setVal('vi-desc', p && p.description);
    setVal('vi-includes', Array.isArray(m.includes) ? m.includes.join('\n') : (m.includes || ''));
    setVal('vi-terms', p && p.usage_terms);
    var trs = m.translations && typeof m.translations === 'object' ? m.translations : {};
    qsa('[data-tr]', $('vi-modal')).forEach(function (i) {
      var f = i.getAttribute('data-tr'), l = i.getAttribute('data-lang'), v = trs[f] && trs[f][l];
      i.value = Array.isArray(v) ? v.join('\n') : (v || '');
    });
    $('vi-tr-btn').setAttribute('aria-expanded', 'false');
    show('vi-tr-body', false);
    tab('hu');
    [['vi-variants', variantRow, p && (p.variants || m.variants)], ['vi-addons', addonRow, p && (p.addons || m.addons)], ['vi-blocks', blockRow, p && (p.blocked_time_ranges || m.blocked_time_ranges)], ['vi-package', packageRow, p && (p.package_outputs || m.package_outputs)]].forEach(function (t) {
      var box = $(t[0]);
      box.textContent = '';
      (Array.isArray(t[2]) ? t[2] : []).forEach(function (x) { box.appendChild(t[1](x)); });
    });
    var sc = (p && (p.slots_config || m.slots_config)) || null;
    $('vi-slots-on').checked = !!(sc && sc.enabled);
    show('vi-slots', !!(sc && sc.enabled));
    setVal('vi-slot-first', sc && sc.first_slot);
    setVal('vi-slot-last', sc && sc.last_slot);
    setVal('vi-slot-interval', sc && sc.interval_minutes);
    setVal('vi-slot-duration', sc && sc.duration_minutes);
    setVal('vi-slot-capacity', sc && sc.capacity_per_slot);
    $('vi-slot-pricing').value = sc && sc.unit_pricing === 'per_slot' ? 'per_slot' : 'per_person';
    var pi = (p && (p.physical_inventory || m.physical_inventory)) || null;
    $('vi-phys-on').checked = !!(pi && pi.enabled);
    show('vi-phys', !!(pi && pi.enabled));
    setVal('vi-phys-count', pi && pi.count);
    $('vi-active').checked = p ? !!p.is_active : true;
    $('vi-parking').checked = !!(p && p.is_parking);
    $('vi-vehicle').checked = !!(p && p.requires_vehicle_info);
    $('vi-child').checked = !!(p && (p.is_child_ticket || m.is_child_ticket));
    $('vi-pos-only').checked = !!(p && (p.pos_only || m.pos_only));
    $('vi-access-req').value = p && ['none', 'any', 'adult_only'].indexOf(p.access_requirement) > -1 ? p.access_requirement : 'none';
    show('vi-del', !!p);
    $('vi-del').lastChild.textContent = VQ.t('Delete the product');
    applyCategory();
    show('vi-modal', true);
    $('vi-modal').querySelector('.ve-modal-card').scrollTop = 0;
    $('vi-name').focus();
  }
  function closeProduct() {
    show('vi-modal', false);
    edit = null;
    if (lastFocus && lastFocus.focus) lastFocus.focus();
  }
  function tab(lang) {
    qsa('.ve-tab', $('vi-modal')).forEach(function (t) { t.setAttribute('aria-selected', String(t.getAttribute('data-tab') === lang)); });
    qsa('[data-tr-pane]', $('vi-modal')).forEach(function (p) { p.hidden = p.getAttribute('data-tr-pane') !== lang; });
  }
  function translations() {
    var out = {};
    qsa('[data-tr]', $('vi-modal')).forEach(function (i) {
      var f = i.getAttribute('data-tr'), l = i.getAttribute('data-lang'), v = i.value.trim();
      if (!v) return;
      out[f] = out[f] || {};
      out[f][l] = f === 'includes' ? v.split('\n').map(function (s) { return s.trim(); }).filter(Boolean) : v;
    });
    return Object.keys(out).length ? out : null;
  }
  function collect() {
    var cat = $('vi-cat').value, timed = cat === 'rental' || cat === 'activity', pkg = cat === 'package';
    var incl = $('vi-includes').value.split('\n').map(function (s) { return s.trim(); }).filter(Boolean);
    var access = timed ? $('vi-access-req').value : 'none';
    var isGroup = $('vi-is-group').checked, guide = isGroup && $('vi-guide').checked;
    var body = {
      name: $('vi-name').value.trim(),
      sku: $('vi-sku').value.trim() || null,
      service_category: cat,
      issuing_company: $('vi-issuer').value,
      ticket_group: $('vi-group').value || null,
      price: num($('vi-price').value),
      capacity: int($('vi-capacity').value),
      daily_capacity: int($('vi-daily').value),
      min_per_order: $('vi-min').value !== '' ? Math.max(1, int($('vi-min').value) || 1) : null,
      max_per_order: int($('vi-max').value),
      service_duration_minutes: int($('vi-duration').value),
      description: $('vi-desc').value.trim() || null,
      usage_terms: $('vi-terms').value.trim() || null,
      is_active: $('vi-active').checked,
      is_parking: !pkg && $('vi-parking').checked,
      requires_vehicle_info: !pkg && $('vi-vehicle').checked,
      requires_access_ticket: access !== 'none',
      meta: {
        icon: $('vi-icon').value.trim() || null,
        unit_label: $('vi-unit').value.trim() || null,
        image: $('vi-image-box').getAttribute('data-value') || null,
        pos_price: $('vi-pos-price').value !== '' ? num($('vi-pos-price').value) : null,
        pos_only: $('vi-pos-only').checked,
        is_child_ticket: cat === 'access' && $('vi-child').checked,
        access_requirement: access,
        step_qty: $('vi-step').value !== '' ? Math.max(1, int($('vi-step').value) || 1) : null,
        is_group_ticket: isGroup,
        group_includes_guide: guide,
        group_guide_label: guide ? ($('vi-guide-label').value.trim() || VQ.t('Group guide')) : null,
        includes: incl,
        variants: timed ? collectRows('vi-variants', function (it) {
          if (!it.label) return null;
          return { id: it.id || slug(it.label) || 'v', label: it.label, duration_minutes: int(it.duration_minutes), price: num(it.price) || 0 };
        }) : [],
        addons: cat === 'access' || timed ? collectRows('vi-addons', function (it) {
          if (!it.label) return null;
          return { id: it.id || slug(it.label) || 'a', label: it.label, price: num(it.price) || 0, included_qty: Math.max(0, int(it.included_qty) || 0), max_per_unit: Math.max(0, int(it.max_per_unit) != null ? int(it.max_per_unit) : 5) };
        }) : [],
        blocked_time_ranges: timed ? collectRows('vi-blocks', function (it) {
          if (!it.date || !it.start_time || !it.end_time) return null;
          return { date: it.date, start_time: it.start_time, end_time: it.end_time, reason: it.reason || null };
        }) : [],
        package_outputs: pkg ? packageOutputs() : [],
        slots_config: timed && $('vi-slots-on').checked ? {
          enabled: true,
          first_slot: $('vi-slot-first').value || '09:00',
          last_slot: $('vi-slot-last').value || '18:00',
          interval_minutes: int($('vi-slot-interval').value) || 30,
          duration_minutes: int($('vi-slot-duration').value) || 30,
          capacity_per_slot: int($('vi-slot-capacity').value) || 1,
          unit_pricing: $('vi-slot-pricing').value,
        } : null,
        physical_inventory: timed && $('vi-phys-on').checked ? { enabled: true, count: Math.max(1, int($('vi-phys-count').value) || 1) } : null,
        translations: translations(),
      },
    };
    return body;
  }
  function saveProduct() {
    var body = collect();
    if (!body.name) { O.flash(VQ.t('Enter the product name.'), true); $('vi-name').focus(); return; }
    if (body.price == null || body.price < 0) { O.flash(VQ.t('Set the online price.'), true); $('vi-price').focus(); return; }
    if (body.service_category === 'package' && !body.meta.package_outputs.length) { O.flash(VQ.t('A package needs at least one component.'), true); return; }
    var bad = body.meta.blocked_time_ranges.filter(function (b) { return b.end_time <= b.start_time; })[0];
    if (bad) { O.flash(VQ.t('A blocked time slot ends before it starts.'), true); return; }
    var b = $('vi-save'), base = '/organizer/events/' + eventId + '/leisure/products';
    b.disabled = true;
    var req = edit ? O.api(base + '/' + F.toNum(edit.id), { method: 'PUT', body: body }) : O.api(base, { method: 'POST', body: body });
    req.then(function () {
      O.flash(edit ? VQ.t('The product was saved.') : VQ.t('The product was added.'));
      closeProduct();
      loadAll();
    }, function (err) {
      var first = err && err.errors && Object.keys(err.errors)[0];
      O.flash((first && err.errors[first] && err.errors[first][0]) || (err && err.message) || VQ.t('We could not save the product.'), true);
    }).then(function () { b.disabled = false; });
  }
  function deleteProduct() {
    if (!edit) return;
    var b = $('vi-del');
    if (!delArmed) { delArmed = true; b.lastChild.textContent = VQ.t('Press again to delete'); return; }
    b.disabled = true;
    O.api('/organizer/events/' + eventId + '/leisure/products/' + F.toNum(edit.id), { method: 'DELETE' }).then(function () {
      O.flash(VQ.t('The product was deleted.'));
      closeProduct();
      loadAll();
    }, function (err) {
      O.flash((err && err.message) || VQ.t('We could not delete the product.'), true);
      delArmed = false;
      b.lastChild.textContent = VQ.t('Delete the product');
    }).then(function () { b.disabled = false; });
  }

  /* =================== wiring =================== */
  $('vi-add').addEventListener('click', function () { openProduct(null); });
  $('vi-cats-btn').addEventListener('click', function () {
    var open = this.getAttribute('aria-expanded') !== 'true';
    this.setAttribute('aria-expanded', String(open));
    this.firstChild.nodeValue = open ? VQ.t('Hide categories') : VQ.t('Show categories');
    show('vi-cats-body', open);
  });
  $('vi-cat-add').addEventListener('click', addCat);
  $('vi-cat-new').addEventListener('keydown', function (ev) { if (ev.key === 'Enter') { ev.preventDefault(); addCat(); } });
  $('vi-cats-save').addEventListener('click', saveCats);
  $('vi-cat').addEventListener('change', applyCategory);
  $('vi-issuer').addEventListener('change', pkgSummary);
  $('vi-price').addEventListener('input', function () { if ($('vi-cat').value === 'package') pkgSummary(); });
  $('vi-is-group').addEventListener('change', function () { show('vi-group-extra', this.checked); });
  $('vi-slots-on').addEventListener('change', function () { show('vi-slots', this.checked); });
  $('vi-phys-on').addEventListener('change', function () { show('vi-phys', this.checked); });
  $('vi-tr-btn').addEventListener('click', function () {
    var open = this.getAttribute('aria-expanded') !== 'true';
    this.setAttribute('aria-expanded', String(open));
    show('vi-tr-body', open);
  });
  qsa('.ve-tab', $('vi-modal')).forEach(function (t) { t.addEventListener('click', function () { tab(t.getAttribute('data-tab')); }); });
  qsa('[data-add]', $('vi-modal')).forEach(function (b) {
    b.addEventListener('click', function () {
      var k = b.getAttribute('data-add');
      var target = { variant: ['vi-variants', variantRow], addon: ['vi-addons', addonRow], block: ['vi-blocks', blockRow], package: ['vi-package', packageRow] }[k];
      var row = target[1](null);
      $(target[0]).appendChild(row);
      var first = row.querySelector('input, select');
      if (first) first.focus();
      if (k === 'package') pkgSummary();
    });
  });
  $('vi-alloc').addEventListener('click', autoAllocate);
  $('vi-image-file').addEventListener('change', function () {
    var f = this.files && this.files[0], input = this;
    if (!f) return;
    $('vi-image-label').textContent = VQ.t('Loading…');
    upload(f).then(function (d) { setImage(d.url || d.path); }, function (err) {
      O.flash((err && err.message) || VQ.t('We could not upload the picture.'), true);
      setImage($('vi-image-box').getAttribute('data-value'));
    }).then(function () { input.value = ''; });
  });
  $('vi-image-rm').addEventListener('click', function () { setImage(''); });
  $('vi-save').addEventListener('click', saveProduct);
  $('vi-cancel').addEventListener('click', closeProduct);
  $('vi-del').addEventListener('click', deleteProduct);
  $('vi-modal').addEventListener('click', function (ev) { if (ev.target === $('vi-modal')) closeProduct(); });
  document.addEventListener('keydown', function (ev) { if (ev.key === 'Escape' && !$('vi-modal').hidden) closeProduct(); });
  $('ve-retry').addEventListener('click', function () { show('ve-failed', false); start(); });
  $('ve-event').addEventListener('change', function () { eventId = F.toNum(this.value); loadAll(); });

  function loadEvents() {
    return O.api('/organizer/events?per_page=50&page=1').then(function (r) {
      var list = Array.isArray(r && r.data) ? r.data : (r && r.data && r.data.items) || [];
      var events = list.filter(function (e) { return e && e.id != null; });
      var venues = events.filter(function (e) { return (e.display_template || '') === 'leisure_venue'; });
      if (!venues.length && events.length) return probe(events.slice(0, 8));
      return venues;
    });
  }
  function probe(list) {
    var found = [], chain = Promise.resolve();
    list.forEach(function (e) {
      chain = chain.then(function () {
        return O.api('/organizer/events/' + e.id + '/leisure/config', { quiet: true }).then(function () { found.push(e); }, function () {});
      });
    });
    return chain.then(function () { return found; });
  }
  function start() {
    show('ve-main', false);
    show('ve-none', false);
    loadEvents().then(function (venues) {
      if (!venues.length) { show('ve-none', true); $('vi-add').disabled = true; return; }
      var s = $('ve-event');
      s.textContent = '';
      venues.forEach(function (e) { s.appendChild(new Option(txt(e.title || e.name) || VQ.t('Venue #{id}', { id: e.id }), String(e.id))); });
      s.disabled = venues.length < 2;
      eventId = F.toNum(venues[0].id);
      show('ve-main', true);
      loadAll();
    }, function (err) {
      if (err && err.status === 401) return;
      show('ve-failed', true);
    });
  }
  O.ready.then(function (ok) { if (ok) start(); });
})();
