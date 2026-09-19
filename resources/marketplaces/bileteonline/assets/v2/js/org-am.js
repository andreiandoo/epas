/* bilete.online v2: shared kit of the operator's activities-module screens (/organizator/locatii, /organizator/produse,
   /organizator/rezervari). Calls go to /organizer/activities-module/* (api.js maps them to the proxy's organizer.am.*
   actions); pictures go up as multipart. The form kit binds each control to a plain object: every change is written back
   at once, an empty input becomes null, numbers are numbers. Labels for the core's option keys come from the page
   (#v2-data .am, filled from includes/v2/am-labels.php). Runs inside the organizer shell (window.BO_ORG) and exposes
   window.BO_AM for the page scripts. Text from the API is always written as text. */
(function () {
  'use strict';
  var O = window.BO_ORG;
  if (!O) return;
  var el = O.el;
  var data = {};
  try { data = JSON.parse((document.getElementById('v2-data') || {}).textContent || '{}'); } catch (e) {}
  var L = data.am || {};
  var BASE = '/organizer/activities-module';
  var MAX_IMAGE = 10 * 1024 * 1024;
  var uid = 0;

  function nid(p) { uid += 1; return 'am-' + (p || 'f') + '-' + uid; }

  /* ---------- API ---------- */
  function api(path, opts) { return O.api(BASE + path, opts); }
  /** The first validation message of a refused save, or the error's own message. */
  function errText(err, fallback) {
    if (!err) return fallback;
    var e = err.errors || (err.data && err.data.errors);
    // "Mai completează: …" lists everything in its message; the missing keys come along only for the page
    if (e && typeof e === 'object' && !Array.isArray(e) && e.missing && err.message) return err.message;
    if (e && typeof e === 'object') {
      var k = Object.keys(e)[0];
      var v = k ? e[k] : null;
      if (Array.isArray(v) && v[0]) return String(v[0]);
      if (typeof v === 'string') return v;
    }
    return (err.message && err.message !== 'An error occurred') ? err.message : fallback;
  }
  function upload(file, kind) {
    if (!file) return Promise.reject(new Error('Niciun fișier.'));
    if (!/^image\/(jpeg|png|webp)$/.test(file.type)) return Promise.reject(new Error('Doar poze JPG, PNG sau WebP.'));
    if (file.size > MAX_IMAGE) return Promise.reject(new Error('Poza depășește 10 MB.'));
    var fd = new FormData();
    fd.append('file', file);
    fd.append('kind', kind);
    return BileteOnlineAPI._postMultipart(BASE + '/uploads', fd).then(function (r) {
      var d = (r && r.data) || {};
      if (!d.path) throw new Error('Încărcarea nu a întors poza.');
      return { path: d.path, url: d.url || null };
    });
  }

  /* ---------- values ---------- */
  function conv(v, type) {
    v = v == null ? '' : String(v);
    if (type === 'number') {
      if (v.trim() === '') return null;
      var n = Number(v.replace(',', '.'));
      return isFinite(n) ? n : null;
    }
    return v.trim() === '' ? null : v;
  }
  function imgUrl(img) {
    var u = img && typeof img === 'object' ? img.url : null;
    if (typeof u !== 'string' || !u) return null;
    if (/^\/(?!\/)/.test(u)) return u;
    try { var x = new URL(u); return /^https?:$/.test(x.protocol) ? x.href : null; } catch (e) { return null; }
  }
  function path(img) { return img && typeof img === 'object' ? (img.path || null) : (typeof img === 'string' ? img : null); }
  function slug(s) {
    return String(s || '').toLowerCase().normalize('NFD').replace(/[̀-ͯ]/g, '').replace(/[^a-z0-9]+/g, '-').replace(/^-|-$/g, '').slice(0, 48);
  }

  /* ---------- form kit ---------- */
  function field(label, control, opts) {
    opts = opts || {};
    var wrap = el('span', { class: 'po-field' + (opts.wide ? ' is-wide' : '') + (opts.cls ? ' ' + opts.cls : '') });
    wrap.appendChild(el('label', { for: control.getAttribute('data-for') || control.id || null, text: label }));
    wrap.appendChild(control);
    if (opts.hint) wrap.appendChild(el('span', { class: 've-hint', text: opts.hint }));
    return wrap;
  }
  /** <input> bound to obj[key]. opts: type, max (length), min, maxv, step, ph, on(value). */
  function input(obj, key, opts) {
    opts = opts || {};
    var type = opts.type || 'text';
    var i = el('input', {
      class: 'po-input', id: nid(key), type: type, maxlength: opts.max || null, min: opts.min != null ? opts.min : null,
      max: opts.maxv != null ? opts.maxv : null, step: opts.step || null, placeholder: opts.ph || null, autocomplete: 'off',
      inputmode: type === 'number' ? (opts.step && String(opts.step).indexOf('.') >= 0 ? 'decimal' : 'numeric') : null,
    });
    i.value = obj[key] == null ? '' : String(obj[key]);
    i.addEventListener('input', function () {
      obj[key] = conv(i.value, type);
      if (opts.on) opts.on(obj[key]);
    });
    return i;
  }
  function textarea(obj, key, opts) {
    opts = opts || {};
    var t = el('textarea', { class: 've-ta', id: nid(key), rows: opts.rows || 3, maxlength: opts.max || null, placeholder: opts.ph || null });
    t.value = obj[key] == null ? '' : String(obj[key]);
    t.addEventListener('input', function () { obj[key] = conv(t.value); if (opts.on) opts.on(obj[key]); });
    return t;
  }
  /** Textarea of one item per line ↔ an array of strings. */
  function lines(obj, key, opts) {
    opts = opts || {};
    var t = el('textarea', { class: 've-ta', id: nid(key), rows: opts.rows || 3, placeholder: opts.ph || null });
    t.value = (Array.isArray(obj[key]) ? obj[key] : []).join('\n');
    t.addEventListener('input', function () {
      obj[key] = t.value.split('\n').map(function (s) { return s.trim(); }).filter(Boolean).slice(0, opts.limit || 20);
    });
    return t;
  }
  /** <select> bound to obj[key]; options [[value, label]]; opts.num keeps numbers; '' is null. */
  function select(obj, key, options, opts) {
    opts = opts || {};
    var s = el('select', { id: nid(key) });
    options.forEach(function (o) {
      var op = el('option', { value: o[0] == null ? '' : String(o[0]), text: o[1] });
      s.appendChild(op);
    });
    s.value = obj[key] == null ? '' : String(obj[key]);
    if (s.value === '' && obj[key] != null && !opts.keep) obj[key] = null;
    s.addEventListener('change', function () {
      var v = s.value === '' ? null : (opts.num ? parseInt(s.value, 10) : s.value);
      obj[key] = v;
      if (opts.on) opts.on(v);
    });
    var w = el('span', { class: 'po-select', 'data-for': s.id }, [s]);
    w.appendChild(O.icon('caret-down'));
    return w;
  }
  function check(obj, key, label, opts) {
    opts = opts || {};
    var i = el('input', { type: 'checkbox', id: nid(key) });
    i.checked = !!obj[key];
    i.addEventListener('change', function () { obj[key] = i.checked; if (opts.on) opts.on(i.checked); });
    var lab = el('label', { class: 'po-check' + (opts.wide ? ' is-wide' : '') }, [i, el('span', { text: label })]);
    return lab;
  }
  /** Checkboxes for a set: obj[key] is the array of the checked keys; labels {key: text}. */
  function checkset(obj, key, labels) {
    if (!Array.isArray(obj[key])) obj[key] = [];
    var box = el('div', { class: 've-checks' });
    Object.keys(labels).forEach(function (k) {
      var i = el('input', { type: 'checkbox', value: k });
      i.checked = obj[key].indexOf(k) >= 0;
      i.addEventListener('change', function () {
        var set = obj[key].filter(function (x) { return x !== k; });
        if (i.checked) set.push(k);
        obj[key] = set;
      });
      box.appendChild(el('label', { class: 'po-check' }, [i, el('span', { text: labels[k] })]));
    });
    return box;
  }
  function button(icon, label, cls, attrs) {
    var b = el('button', Object.assign({ class: cls || 'btn btn-ghost', type: 'button' }, attrs || {}));
    if (icon) b.appendChild(O.icon(icon));
    if (label) b.appendChild(el('span', { text: label }));
    return b;
  }
  /**
   * A repeatable list: list is the array (edited in place), build(item, index) returns the row's fields, blank()
   * makes a new item. Returns {box, addBtn, redraw}.
   */
  function rows(list, build, blank, opts) {
    opts = opts || {};
    var box = el('div', { class: 've-rows' });
    function redraw() {
      box.textContent = '';
      list.forEach(function (item, i) {
        var row = el('div', { class: 've-row' + (opts.rowCls ? ' ' + opts.rowCls : '') });
        [].concat(build(item, i, redraw)).forEach(function (n) { if (n) row.appendChild(n); });
        var rm = button('trash', null, 've-icon-btn', { 'aria-label': (opts.removeLabel || 'Șterge rândul') + ' ' + (i + 1) });
        rm.addEventListener('click', function () { list.splice(i, 1); redraw(); if (opts.on) opts.on(); });
        row.appendChild(rm);
        box.appendChild(row);
      });
      if (opts.empty && !list.length) box.appendChild(el('p', { class: 've-sub', text: opts.empty }));
    }
    var add = button('plus', opts.addLabel || 'Adaugă');
    add.addEventListener('click', function () {
      if (opts.limit && list.length >= opts.limit) { O.flash('Cel mult ' + opts.limit + '.', true); return; }
      list.push(blank());
      redraw();
      if (opts.on) opts.on();
      var inputs = box.querySelectorAll('.ve-row:last-child input, .ve-row:last-child select');
      if (inputs[0]) inputs[0].focus();
    });
    redraw();
    return { box: box, addBtn: add, redraw: redraw };
  }
  /** One picture in obj[key] ({path, url} or null). */
  function image(obj, key, kind, opts) {
    opts = opts || {};
    var box = el('div', { class: 've-image' });
    var thumb = el('img', { alt: '' });
    var empty = el('span', { class: 've-sub', text: opts.hint || 'JPG, PNG sau WebP, cel mult 10 MB.' });
    var file = el('input', { type: 'file', accept: 'image/jpeg,image/png,image/webp', class: 've-sr', id: nid(key + '-file') });
    var pick = el('label', { class: 'btn btn-ghost', for: file.id }, [O.icon('plus'), el('span', { text: 'Alege o poză' })]);
    var rm = button('trash', 'Scoate poza');
    function draw() {
      var src = imgUrl(obj[key]);
      thumb.hidden = !src;
      if (src) thumb.src = src; else thumb.removeAttribute('src');
      empty.hidden = !!src;
      rm.hidden = !obj[key];
      pick.lastChild.textContent = obj[key] ? 'Schimbă poza' : 'Alege o poză';
    }
    file.addEventListener('change', function () {
      var f = file.files && file.files[0];
      if (!f) return;
      box.classList.add('is-busy');
      upload(f, kind).then(function (img) { obj[key] = img; draw(); }, function (err) { O.flash(errText(err, 'Nu am putut încărca poza.'), true); })
        .then(function () { box.classList.remove('is-busy'); file.value = ''; });
    });
    rm.addEventListener('click', function () { obj[key] = null; draw(); });
    box.appendChild(thumb);
    box.appendChild(empty);
    box.appendChild(el('span', { class: 've-image-tools' }, [pick, file, rm]));
    draw();
    return box;
  }
  /** Several pictures in obj[key] ([{path, url}]). */
  function gallery(obj, key, kind, limit) {
    if (!Array.isArray(obj[key])) obj[key] = [];
    limit = limit || 20;
    var box = el('div', { class: 'am-gal' });
    var list = el('ul', { class: 'am-gal-list' });
    var file = el('input', { type: 'file', accept: 'image/jpeg,image/png,image/webp', multiple: true, class: 've-sr', id: nid(key + '-files') });
    var pick = el('label', { class: 'btn btn-ghost', for: file.id }, [O.icon('plus'), el('span', { text: 'Adaugă poze' })]);
    function draw() {
      list.textContent = '';
      obj[key].forEach(function (img, i) {
        var li = el('li', { class: 'am-gal-item' });
        var src = imgUrl(img);
        if (src) li.appendChild(el('img', { src: src, alt: '' }));
        var rm = button('x', null, 've-icon-btn', { 'aria-label': 'Scoate poza ' + (i + 1) });
        rm.addEventListener('click', function () { obj[key].splice(i, 1); draw(); });
        li.appendChild(rm);
        list.appendChild(li);
      });
      pick.hidden = obj[key].length >= limit;
    }
    file.addEventListener('change', function () {
      var files = Array.prototype.slice.call(file.files || []).slice(0, limit - obj[key].length);
      if (!files.length) return;
      box.classList.add('is-busy');
      files.reduce(function (p, f) {
        return p.then(function () {
          return upload(f, kind).then(function (img) { obj[key].push(img); draw(); }, function (err) { O.flash(errText(err, 'O poză nu s-a încărcat.'), true); });
        });
      }, Promise.resolve()).then(function () { box.classList.remove('is-busy'); file.value = ''; });
    });
    box.appendChild(list);
    box.appendChild(el('span', { class: 've-image-tools' }, [pick, file]));
    draw();
    return box;
  }
  /** A titled block of the editor, with an anchor for the jump list. */
  function section(id, title, lead, kids) {
    var sec = el('section', { class: 'org-panel am-sec', id: id, 'aria-labelledby': id + '-h' });
    var head = el('div', { class: 'org-panel-head' }, [el('div', null, [
      el('h2', { class: 'org-panel-h', id: id + '-h', text: title }),
      lead ? el('p', { class: 'org-panel-p', text: lead }) : null,
    ])]);
    sec.appendChild(head);
    [].concat(kids || []).forEach(function (k) { if (k) sec.appendChild(k); });
    return sec;
  }
  function form(kids, cls) {
    var f = el('div', { class: 've-form' + (cls ? ' ' + cls : '') });
    [].concat(kids).forEach(function (k) { if (k) f.appendChild(k); });
    return f;
  }

  /* ---------- review state ---------- */
  var REVIEW = {
    draft: ['Ciornă', 'is-muted'], pending: ['În verificare', 'is-wait'], rejected: ['Respins', 'is-bad'], approved: ['Aprobat', 'is-ok'],
  };
  /** Tags for a location or product: its review step and whether it is on the site. */
  function statusTags(item) {
    var out = [];
    var r = item.review_status ? REVIEW[item.review_status] : ['Adăugat de bilete.online', 'is-ok'];
    if (r) out.push(el('span', { class: 'org-tag ' + r[1], text: r[0] }));
    if (!item.review_status || item.review_status === 'approved') {
      out.push(el('span', { class: 'org-tag ' + (item.is_published ? 'is-ok' : 'is-muted'), text: item.is_published ? 'Pe site' : 'Ascuns' }));
    }
    if (item.pos_only) out.push(el('span', { class: 'org-tag is-muted', text: 'Doar la casă' }));
    return out;
  }
  /** What the operator can do next, as text under the tags. */
  function statusHint(item) {
    if (item.review_status === 'rejected') return 'Respins' + (item.rejection_reason ? ': ' + item.rejection_reason : '') + '. Corectează și trimite din nou.';
    if (item.review_status === 'pending') return 'Echipa bilete.online verifică și îți scrie. Modificările se pot face în continuare.';
    if (item.review_status === 'draft') return 'Ciornă. Când e gata, trimite-o spre aprobare.';
    if (!item.is_published) return 'Aprobat, dar ascuns de pe site.';
    return '';
  }

  window.BO_AM = {
    L: L, api: api, errText: errText, upload: upload, conv: conv, imgUrl: imgUrl, path: path, slug: slug, nid: nid,
    field: field, input: input, textarea: textarea, lines: lines, select: select, check: check, checkset: checkset,
    button: button, rows: rows, image: image, gallery: gallery, section: section, form: form,
    statusTags: statusTags, statusHint: statusHint,
  };
})();
