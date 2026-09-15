/* bilete.online v2: customer reviews (/cont/recenzii).
   - "De evaluat": GET /customer/reviews/events-to-review, one activity at a time (pager). Publishing POSTs
     /customer/reviews (JSON; multipart with the files when photos are attached, downscaled in the browser first) and
     only counts when the answer carries the review id. Core has no drafts (every review goes to moderation), so drafts
     are kept in localStorage per customer and activity; what is typed is kept as a draft when switching activities or
     leaving the page.
   - "Istoric": every page of GET /customer/reviews (approved = publicată, pending = în moderare, rejected = respinsă)
     plus the drafts; search / status / rating filters kept in the URL; editing opens a dialog that sends only what
     changed (a new rating or text sends the review back to moderation, so nothing else does); inline delete.
   Results go to one toast (#rv-flash); form errors stay under their form. Text from the API is always written as text. */
(function () {
  'use strict';
  var $ = function (id) { return document.getElementById(id); };
  if (!$('rv-content') || !window.BO_ACCOUNT || typeof BileteOnlineAPI === 'undefined') return;
  var account = window.BO_ACCOUNT, API = BileteOnlineAPI;
  var reduce = !!(window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches);

  var MONTHS = ['ian', 'feb', 'mar', 'apr', 'mai', 'iun', 'iul', 'aug', 'sep', 'oct', 'noi', 'dec'];
  var WORDS = ['', 'Slab', 'Sub așteptări', 'OK', 'Foarte bun', 'Excelent'];
  var ASPECTS = ['show', 'venue', 'organization', 'value'];
  var STATUS = { published: ['publicată', 'is-ok'], moderation: ['în moderare', 'is-wait'], rejected: ['respinsă', 'is-bad'], draft: ['draft', 'is-muted'] };
  var STATUS_FILTERS = ['all', 'published', 'draft', 'moderation', 'rejected'];
  var RATING_FILTERS = ['all', '5', '4', '3', '2', '1'];
  var TYPES = /^image\/(jpeg|png|webp|gif)$/;
  var DRAFT_KEY = 'bo_review_drafts_v1', MAX_SIDE = 1920;
  var STAR_PATH = 'M12 2.6l2.84 5.93 6.53.86-4.78 4.53 1.2 6.47L12 17.25l-5.79 3.15 1.2-6.47L2.63 9.39l6.53-.86z';
  var PLACEHOLDER = 'data:image/svg+xml;utf8,' + encodeURIComponent('<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 88 88"><rect width="88" height="88" fill="#E6F4EC"/><path d="M44 24l5.7 11.9 13 1.7-9.5 9 2.4 12.9L44 53.3l-11.6 6.2 2.4-12.9-9.5-9 13-1.7z" fill="none" stroke="#1B7F4E" stroke-width="3" stroke-linejoin="round"/></svg>');
  var limits = { minText: 20, maxText: 2000, photos: 5, mb: 5 };
  var num = new Intl.NumberFormat('ro-RO'), one = new Intl.NumberFormat('ro-RO', { minimumFractionDigits: 1, maximumFractionDigits: 1 });

  var todo = [], todoOk = false, todoFailed = false, idx = 0, shownId = null;
  var reviews = [], reviewsOk = false, photos = [], dirty = false, drafts = {};
  var editing = null, editOpener = null, flashTimer = 0, flashSet = 0;
  var params = new URLSearchParams(window.location.search);
  var filters = {
    q: (params.get('q') || '').slice(0, 100),
    status: STATUS_FILTERS.indexOf(params.get('status')) !== -1 ? params.get('status') : 'all',
    rating: RATING_FILTERS.indexOf(params.get('rating')) !== -1 ? params.get('rating') : 'all'
  };

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
    if (typeof v === 'string' && /^\s*\{/.test(v)) { try { var parsed = JSON.parse(v); if (obj(parsed)) v = parsed; } catch (e) {} }
    if (obj(v)) v = v.ro || v.en || Object.keys(v).map(function (k) { return v[k]; }).filter(function (x) { return typeof x === 'string'; })[0];
    return v == null || typeof v === 'object' ? '' : String(v).trim();
  }
  function count(v) { var n = Number(v); return isFinite(n) && n > 0 ? Math.floor(n) : 0; }
  function stars5(v) { var n = Math.round(Number(v)); return n >= 1 && n <= 5 ? n : 0; }
  function plural(n, oneWord, many) {
    n = count(n);
    if (n === 1) return '1 ' + oneWord;
    var r = n % 100;
    return num.format(n) + (n && (r === 0 || r >= 20) ? ' de ' : ' ') + many;
  }
  function pad(n) { return String(n).padStart(2, '0'); }
  function toDate(v) {
    if (!v) return null;
    var s = String(v).trim(), m = /^(\d{4})-(\d{2})-(\d{2})$/.exec(s);
    var d = m ? new Date(+m[1], +m[2] - 1, +m[3]) : new Date(/^\d{4}-\d{2}-\d{2} \d/.test(s) ? s.replace(' ', 'T') : s);
    return isNaN(d.getTime()) ? null : d;
  }
  function day(v) { var d = v instanceof Date ? v : toDate(v); return d ? d.getDate() + ' ' + MONTHS[d.getMonth()] + ' ' + d.getFullYear() : ''; }
  function dayTime(v) { var d = v instanceof Date ? v : toDate(v); return d ? day(d) + ', ' + pad(d.getHours()) + ':' + pad(d.getMinutes()) : ''; }
  function norm(s) { return String(s || '').toLowerCase().normalize('NFD').replace(/[̀-ͯ]/g, '').trim(); }
  function fresh(path) { return API.request(path, { method: 'GET', noCache: true }); }
  function apiUrl() { return (window.BILETEONLINE && window.BILETEONLINE.apiUrl) || '/api/proxy.php'; }
  function token() { try { return BileteOnlineAuth.getToken() || ''; } catch (e) { return ''; } }
  /** Image URLs from core: absolute, site-relative, core's /storage/… (photos) or a bare storage path (event images). */
  function imgUrl(v) {
    v = typeof v === 'string' ? v.trim() : '';
    if (!v) return '';
    var storage = ((window.BILETEONLINE && window.BILETEONLINE.storageUrl) || '').replace(/\/+$/, '');
    if (/^https?:\/\//i.test(v)) return v;
    if (/^\/storage\//.test(v)) return storage ? storage.replace(/\/storage$/, '') + v : '';
    if (/^\/(?![\/\\])/.test(v)) return v;
    if (/^[a-z][a-z0-9+.-]*:/i.test(v) || /^[\/\\]/.test(v)) return '';
    return storage ? storage + '/' + v : '';
  }
  function image(src) {
    var i = el('img');
    i.alt = '';
    i.loading = 'lazy';
    i.decoding = 'async';
    i.addEventListener('error', function () { if (i.getAttribute('src') !== PLACEHOLDER) i.src = PLACEHOLDER; });
    i.src = src || PLACEHOLDER;
    return i;
  }
  /** An image box; without an image (or when it fails to load) a small star on the tinted ground (hidden on phones). */
  function mediaBox(cls, src) {
    var box = el('div', cls);
    function empty() { box.textContent = ''; box.classList.add('is-empty'); box.appendChild(icon('star')); }
    if (!src) { empty(); return box; }
    var i = el('img');
    i.alt = '';
    i.loading = 'lazy';
    i.decoding = 'async';
    i.addEventListener('error', empty);
    i.src = src;
    box.appendChild(i);
    return box;
  }
  function starsRow(n, label) {
    var ns = 'http://www.w3.org/2000/svg', box = el('div', 'rv-card-stars');
    box.setAttribute('role', 'img');
    box.setAttribute('aria-label', label);
    for (var i = 1; i <= 5; i++) {
      var svg = document.createElementNS(ns, 'svg'), path = document.createElementNS(ns, 'path');
      svg.setAttribute('viewBox', '0 0 24 24');
      svg.setAttribute('class', 'rv-star-ic' + (i <= n ? ' is-on' : ''));
      svg.setAttribute('aria-hidden', 'true');
      path.setAttribute('d', STAR_PATH);
      svg.appendChild(path);
      box.appendChild(svg);
    }
    return box;
  }
  function isRo(m) { return /[ăâîșțşţ]/i.test(m) || /\b(nu|este|sau|pentru|recenzia|activitatea)\b/i.test(m); }
  function errMessage(err, fallback) {
    if (!err || !err.status) return 'Nu am putut contacta serverul. Verifică conexiunea și încearcă din nou.';
    if (err.status === 401) return 'Sesiunea a expirat. Intră din nou în cont și reîncearcă.';
    if (err.status === 429) return 'Prea multe încercări într-un timp scurt. Mai încearcă peste un minut.';
    var m = String(err.message || '').trim();
    return m && m.length < 300 && isRo(m) ? m : fallback;
  }
  function say(message, tone) {
    var f = $('rv-flash');
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
  /** Swaps a row's buttons for "question [yes] [Renunță]"; run(done) performs the action, done(false) brings the row back. */
  function confirmRow(box, question, yesLabel, run) {
    var kept = [].slice.call(box.childNodes), opener = document.activeElement;
    var q = el('span', 'rv-confirm-q', question), yes = button(yesLabel, 'rv-danger'), no = button('Renunță', 'btn-ghost');
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
      busy(yes, true, 'Se șterge…');
      no.disabled = true;
      run(function (ok) { if (!ok && box.isConnected) restore(); });
    });
    no.focus();
  }
  function guard() { show('rv-content', false); show('rv-guard', true); }

  // ---------- drafts (this device only) ----------
  function userKey() { var u = account.cachedUser() || {}; return String(u.id || u.email || 'client').toLowerCase(); }
  function readDrafts() {
    try {
      var all = JSON.parse(localStorage.getItem(DRAFT_KEY) || '{}'), mine = obj(all) ? all[userKey()] : null;
      return obj(mine) ? mine : {};
    } catch (e) { return {}; }
  }
  function persistDrafts(quiet) {
    try {
      var all = {};
      try { all = JSON.parse(localStorage.getItem(DRAFT_KEY) || '{}'); } catch (e) {}
      if (!obj(all)) all = {};
      if (Object.keys(drafts).length) all[userKey()] = drafts; else delete all[userKey()];
      localStorage.setItem(DRAFT_KEY, JSON.stringify(all));
      return true;
    } catch (e) {
      if (!quiet) say('Nu am putut salva draftul în acest browser (spațiu plin sau navigare privată).', 'error');
      return false;
    }
  }
  function activeDrafts() {
    return Object.keys(drafts).map(function (k) { return drafts[k]; }).filter(function (d) {
      return obj(d) && obj(d.event) && (!todoOk || todo.some(function (e) { return String(e.id) === String(d.event.id); }));
    });
  }
  function hasContent(f) { return !!(f.rating || f.text || Object.keys(f.aspects).length); }
  function saveDraft(ev, quiet) {
    var f = readForm();
    if (!hasContent(f)) {
      if (!quiet) say('Scrie ceva sau alege un rating înainte să salvezi draftul.', 'error');
      return false;
    }
    drafts[ev.id] = Object.assign(f, {
      saved: new Date().toISOString(),
      event: { id: ev.id, name: ev.name, image: ev.image, date: ev.date ? ev.date.toISOString() : null, venue: ev.venue, city: ev.city }
    });
    if (!persistDrafts(quiet)) return false;
    dirty = false;
    draftNote(drafts[ev.id].saved);
    renderStats();
    renderList();
    return true;
  }

  // ---------- star inputs ----------
  function group(name) { return document.querySelector('[data-stars="' + name + '"]'); }
  function starValue(name) { var c = group(name).querySelector('input:checked'); return c ? +c.value : 0; }
  function paint(g, preview) {
    var v = preview || starValue(g.dataset.stars), out = $(g.dataset.stars + '-t');
    [].forEach.call(g.querySelectorAll('.rv-star'), function (l, i) { l.classList.toggle('is-on', i < v); });
    if (out && !preview) out.textContent = v ? v + ' din 5 · ' + WORDS[v] : 'Alege de la 1 la 5 stele';
  }
  function setStars(name, v) {
    [].forEach.call(group(name).querySelectorAll('input'), function (i) { i.checked = +i.value === v; });
    paint(group(name));
  }
  function changed(node) {
    if (node.closest('#rv-form')) dirty = true;
    var set = node.closest('fieldset');
    if (set) [].forEach.call(set.querySelectorAll('[aria-invalid]'), function (n) { n.removeAttribute('aria-invalid'); });
  }
  [].forEach.call(document.querySelectorAll('[data-stars]'), function (g) {
    var pressed = null;
    g.addEventListener('pointerdown', function (e) { var l = e.target.closest('.rv-star'); pressed = l && l.querySelector('input').checked ? l : null; });
    g.addEventListener('click', function (e) {
      var l = e.target.closest('.rv-star');
      if (!l || !g.hasAttribute('data-optional') || pressed !== l) return;
      l.querySelector('input').checked = false; // a detailed rating is optional: the chosen star again clears it
      pressed = null;
      paint(g);
      changed(g);
    });
    g.addEventListener('change', function () { paint(g); changed(g); });
    g.addEventListener('mouseover', function (e) { var l = e.target.closest('.rv-star'); if (l) paint(g, +l.querySelector('input').value); });
    g.addEventListener('mouseleave', function () { paint(g); });
    paint(g);
  });
  function readAspects(prefix) {
    var out = {};
    ASPECTS.forEach(function (a) { var v = starValue(prefix + a); if (v) out[a] = v; });
    return out;
  }

  // ---------- writer ----------
  function setSelect(s, value) {
    value = value == null ? '' : String(value);
    if (value && ![].some.call(s.options, function (o) { return o.value === value; })) s.appendChild(new Option(value, value));
    s.value = value;
  }
  function readForm() {
    return {
      rating: starValue('rv-rating'), text: $('rv-text').value.trim(), suitable: $('rv-suitable').value, age: $('rv-age').value,
      aspects: readAspects('rv-a-'), recommend: $('rv-recommend').checked, anonymous: $('rv-anonymous').checked
    };
  }
  function counter(inputId, outId) {
    var input = $(inputId), out = $(outId), left = limits.minText - input.value.trim().length;
    out.textContent = num.format(input.value.length) + ' / ' + num.format(limits.maxText) + (left > 0 ? ' · mai scrie ' + plural(left, 'caracter', 'caractere') : ' · gata de publicare');
    out.classList.toggle('is-ok', left <= 0);
  }
  function draftNote(saved) {
    $('rv-draft-note').textContent = (saved && toDate(saved) ? 'Draft salvat pe acest dispozitiv pe ' + dayTime(saved) + '. ' : '') + 'Recenziile apar pe site după o scurtă verificare.';
  }
  function fillForm(d) {
    d = obj(d) ? d : {};
    var aspects = obj(d.aspects) ? d.aspects : {};
    setStars('rv-rating', stars5(d.rating));
    $('rv-text').value = typeof d.text === 'string' ? d.text.slice(0, limits.maxText) : '';
    setSelect($('rv-suitable'), d.suitable || 'family');
    setSelect($('rv-age'), d.age || 'all');
    ASPECTS.forEach(function (a) { setStars('rv-a-' + a, stars5(aspects[a])); });
    $('rv-recommend').checked = d.recommend !== false;
    $('rv-anonymous').checked = !!d.anonymous;
    $('rv-more').open = Object.keys(aspects).length > 0 || !!d.anonymous || d.recommend === false;
    clearPhotos();
    clearInvalid($('rv-form'));
    formError('rv-form-error', '');
    counter('rv-text', 'rv-text-count');
    draftNote(d.saved);
    dirty = false;
  }
  function renderTodo(force) {
    show('rv-todo-skel', false);
    show('rv-todo-error', false);
    show('rv-todo-empty', todoOk && !todo.length);
    show('rv-todo', todo.length > 0);
    show('rv-pager', todo.length > 1);
    if (!todo.length) { shownId = null; return; }
    idx = Math.max(0, Math.min(idx, todo.length - 1));
    var ev = todo[idx];
    $('rv-pos').textContent = (idx + 1) + ' din ' + todo.length;
    $('rv-prev').disabled = idx === 0;
    $('rv-next').disabled = idx === todo.length - 1;
    var slot = $('rv-ev-media'), box = mediaBox('rv-write-media', ev.image);
    box.id = 'rv-ev-media';
    slot.parentNode.replaceChild(box, slot);
    $('rv-ev-date').textContent = ev.date ? day(ev.date) : '';
    $('rv-ev-title').textContent = ev.name;
    $('rv-ev-where').textContent = [ev.venue, ev.city].filter(Boolean).join(', ');
    if (force || String(ev.id) !== String(shownId) || !dirty) fillForm(drafts[ev.id]);
    shownId = ev.id;
  }
  function go(step) {
    var next = idx + step;
    if (next < 0 || next >= todo.length) return;
    if (dirty && todo[idx] && saveDraft(todo[idx], true)) say('Am păstrat ce ai scris pentru „' + todo[idx].name + '” ca draft pe acest dispozitiv.');
    idx = next;
    renderTodo(true);
    var b = step < 0 ? $('rv-prev') : $('rv-next');
    if (b.disabled) (step < 0 ? $('rv-next') : $('rv-prev')).focus();
  }
  $('rv-prev').addEventListener('click', function () { go(-1); });
  $('rv-next').addEventListener('click', function () { go(1); });
  $('rv-text').addEventListener('input', function () { counter('rv-text', 'rv-text-count'); dirty = true; this.removeAttribute('aria-invalid'); });
  ['rv-suitable', 'rv-age', 'rv-recommend', 'rv-anonymous'].forEach(function (id) { $(id).addEventListener('change', function () { dirty = true; }); });
  function autosave() { if (dirty && todo[idx]) saveDraft(todo[idx], true); }
  document.addEventListener('visibilitychange', function () { if (document.visibilityState === 'hidden') autosave(); });
  window.addEventListener('pagehide', autosave);

  // ---------- photos ----------
  function clearPhotos() {
    photos.forEach(function (p) { try { URL.revokeObjectURL(p.url); } catch (e) {} });
    photos = [];
    renderThumbs();
  }
  function renderThumbs(focusIndex) {
    var ul = $('rv-thumbs');
    ul.textContent = '';
    photos.forEach(function (p, i) {
      var li = el('li'), img = el('img'), x = el('button', 'rv-thumb-x');
      img.src = p.url;
      img.alt = 'Poza ' + (i + 1);
      x.type = 'button';
      x.setAttribute('aria-label', 'Elimină poza ' + (i + 1));
      x.appendChild(icon('x'));
      x.addEventListener('click', function () {
        try { URL.revokeObjectURL(p.url); } catch (e) {}
        photos.splice(i, 1);
        dirty = true;
        renderThumbs(i);
      });
      li.appendChild(img);
      li.appendChild(x);
      ul.appendChild(li);
    });
    show(ul, photos.length > 0);
    $('rv-attach').disabled = photos.length >= limits.photos;
    $('rv-photo-note').textContent = (photos.length ? plural(photos.length, 'poză atașată', 'poze atașate') + ' din ' + limits.photos + '. ' : '')
      + 'Până la ' + limits.photos + ' poze JPG, PNG, WebP sau GIF, maximum ' + limits.mb + ' MB fiecare.';
    if (focusIndex != null) {
      var xs = ul.querySelectorAll('.rv-thumb-x');
      (xs[Math.min(focusIndex, xs.length - 1)] || $('rv-attach')).focus();
    }
  }
  /** Large JPEG / PNG / WebP photos are resized (longest side 1920 px) and sent as JPEG; GIFs and small photos stay as they are. */
  function shrink(file) {
    if (!/^image\/(jpeg|png|webp)$/.test(file.type) || typeof createImageBitmap !== 'function' || !HTMLCanvasElement.prototype.toBlob) return Promise.resolve(file);
    return createImageBitmap(file).then(function (bmp) {
      var scale = Math.min(1, MAX_SIDE / Math.max(bmp.width, bmp.height));
      if (scale === 1 && file.size <= 1.5 * 1048576) { if (bmp.close) bmp.close(); return file; }
      var canvas = document.createElement('canvas'), ctx = canvas.getContext('2d');
      canvas.width = Math.max(1, Math.round(bmp.width * scale));
      canvas.height = Math.max(1, Math.round(bmp.height * scale));
      ctx.fillStyle = '#FFFFFF';
      ctx.fillRect(0, 0, canvas.width, canvas.height);
      ctx.drawImage(bmp, 0, 0, canvas.width, canvas.height);
      if (bmp.close) bmp.close();
      return new Promise(function (resolve) {
        canvas.toBlob(function (blob) {
          resolve(blob && blob.size < file.size ? new File([blob], (file.name || 'poza').replace(/\.[^.]+$/, '') + '.jpg', { type: 'image/jpeg' }) : file);
        }, 'image/jpeg', 0.85);
      });
    }).catch(function () { return file; });
  }
  $('rv-attach').addEventListener('click', function () { $('rv-photo-input').click(); });
  $('rv-photo-input').addEventListener('change', function () {
    var files = [].slice.call(this.files || []), room = limits.photos - photos.length;
    this.value = '';
    if (!files.length) return;
    formError('rv-form-error', '');
    var wrong = files.filter(function (f) { return !TYPES.test(f.type); }).length;
    var accepted = files.filter(function (f) { return TYPES.test(f.type); }), extra = Math.max(0, accepted.length - room);
    accepted = accepted.slice(0, Math.max(0, room));
    Promise.all(accepted.map(shrink)).then(function (list) {
      var big = 0;
      list.forEach(function (f) {
        if (f.size > limits.mb * 1048576) { big++; return; }
        photos.push({ file: f, url: URL.createObjectURL(f) });
      });
      if (list.length > big) dirty = true;
      renderThumbs();
      var problems = [];
      if (wrong) problems.push(plural(wrong, 'fișier nu este o poză acceptată', 'fișiere nu sunt poze acceptate') + ' (JPG, PNG, WebP, GIF)');
      if (big) problems.push(plural(big, 'poză depășește', 'poze depășesc') + ' ' + limits.mb + ' MB');
      if (extra) problems.push('poți atașa cel mult ' + limits.photos + ' poze, așa că ' + plural(extra, 'poză nu a fost adăugată', 'poze nu au fost adăugate'));
      if (problems.length) formError('rv-form-error', problems.join('; ').replace(/^./, function (c) { return c.toUpperCase(); }) + '.');
    });
  });

  // ---------- publish ----------
  function payload(ev, f) {
    var data = { event_id: ev.id, rating: f.rating, text: f.text, recommend: f.recommend, anonymous: f.anonymous, suitable_for: f.suitable || null, age_group: f.age || null };
    if (Object.keys(f.aspects).length) data.detailed_ratings = f.aspects;
    return data;
  }
  function send(ev, f) {
    var data = payload(ev, f);
    if (!photos.length) return API.post('/customer/reviews', data);
    var fd = new FormData();
    Object.keys(data).forEach(function (k) {
      var v = data[k];
      if (v == null) return;
      if (k === 'detailed_ratings') Object.keys(v).forEach(function (a) { fd.append('detailed_ratings[' + a + ']', String(v[a])); });
      else fd.append(k, typeof v === 'boolean' ? (v ? '1' : '0') : String(v));
    });
    photos.forEach(function (p, i) { fd.append('photos[]', p.file, p.file.name || 'poza-' + (i + 1) + '.jpg'); });
    return fetch(apiUrl() + '?action=customer.review.store', {
      method: 'POST', body: fd, credentials: 'same-origin', headers: { Authorization: 'Bearer ' + token(), Accept: 'application/json' }
    }).then(function (res) {
      return res.text().then(function (t) {
        var body = null;
        try { body = JSON.parse(t); } catch (e) {}
        if (!res.ok) throw { status: res.status, message: body ? body.message || body.error || '' : '', errors: body && body.errors };
        if (!body) throw { status: 502, message: '' };
        return body;
      });
    }, function () { throw { status: 0 }; });
  }
  function firstStar(name) { return group(name).querySelector('input:checked') || group(name).querySelector('input'); }
  $('rv-form').addEventListener('submit', function (e) {
    e.preventDefault();
    var ev = todo[idx], form = this, submit = $('rv-submit'), draftBtn = $('rv-draft');
    if (!ev || submit.disabled) return;
    var f = readForm();
    clearInvalid(form);
    formError('rv-form-error', '');
    if (!f.rating) return formError('rv-form-error', 'Alege un rating de la 1 la 5 stele.', firstStar('rv-rating'));
    if (f.text.length < limits.minText) {
      return formError('rv-form-error', 'Scrie cel puțin ' + plural(limits.minText, 'caracter', 'caractere') + ', ca recenzia să fie utilă (acum ' + f.text.length + ').', $('rv-text'));
    }
    busy(submit, true, photos.length ? 'Se încarcă pozele…' : 'Se publică…');
    draftBtn.disabled = true;
    send(ev, f).then(function (resp) {
      if (!(resp && obj(resp.data) && resp.data.review_id)) throw { custom: 'Nu am putut trimite recenzia. Încearcă din nou.' };
      delete drafts[ev.id];
      persistDrafts(true);
      clearPhotos();
      dirty = false;
      shownId = null;
      say('Mulțumim! Recenzia pentru „' + ev.name + '” a fost trimisă și apare pe site după verificare.');
      return Promise.all([loadTodo(true), loadReviews(true)]).then(function () {
        var target = todo.length ? $('rv-ev-title') : $('rv-todo-empty-h');
        if (target) target.focus();
      });
    }).catch(function (err) {
      var status = err && err.status, message = String((err && err.message) || ''), keys = err && obj(err.errors) ? Object.keys(err.errors) : [];
      if (err && err.custom) formError('rv-form-error', err.custom);
      else if (status === 403) formError('rv-form-error', 'Poți scrie recenzii doar pentru activitățile la care ai participat, după ce au avut loc.');
      else if (status === 422 && /already reviewed/i.test(message)) {
        delete drafts[ev.id];
        persistDrafts(true);
        formError('rv-form-error', 'Ai trimis deja o recenzie pentru această activitate. O găsești în istoric.');
        loadTodo(true);
        loadReviews(true);
      } else if (status === 413) formError('rv-form-error', 'Pozele sunt prea mari pentru încărcare. Încearcă mai puține poze sau poze mai mici.', $('rv-attach'));
      else if (status === 422 && keys.some(function (k) { return /^photos/.test(k); })) formError('rv-form-error', 'Pozele trebuie să fie imagini JPG, PNG, WebP sau GIF de cel mult ' + limits.mb + ' MB, maximum ' + limits.photos + '.', $('rv-attach'));
      else if (status === 422 && keys.indexOf('text') !== -1) formError('rv-form-error', 'Textul trebuie să aibă între ' + limits.minText + ' și ' + num.format(limits.maxText) + ' de caractere.', $('rv-text'));
      else if (status === 422 && keys.indexOf('rating') !== -1) formError('rv-form-error', 'Alege un rating de la 1 la 5 stele.', firstStar('rv-rating'));
      else formError('rv-form-error', errMessage(err, 'Nu am putut trimite recenzia. Încearcă din nou.'));
    }).then(function () {
      busy(submit, false);
      draftBtn.disabled = false;
    });
  });
  $('rv-draft').addEventListener('click', function () {
    var ev = todo[idx];
    if (!ev) return;
    var hadPhotos = photos.length > 0;
    if (saveDraft(ev, false)) say(hadPhotos ? 'Draft salvat pe acest dispozitiv. Pozele nu se păstrează în draft: le atașezi când publici.' : 'Draft salvat pe acest dispozitiv.');
  });

  // ---------- history ----------
  function statusKey(s) {
    s = String(s || '').toLowerCase();
    if (s === 'approved' || s === 'published') return 'published';
    if (s === 'rejected') return 'rejected';
    return 'moderation'; // pending, flagged, anything new: not public yet
  }
  function normReview(r) {
    var ev = obj(r.event) ? r.event : {}, detailed = obj(r.detailed_ratings) ? r.detailed_ratings : {}, aspects = {};
    ASPECTS.forEach(function (a) { var v = stars5(detailed[a]); if (v) aspects[a] = v; });
    return {
      id: r.id, rating: stars5(r.rating), text: txt(r.text), aspects: aspects,
      photos: (Array.isArray(r.photos) ? r.photos : []).map(imgUrl).filter(Boolean),
      recommend: r.recommend !== false && r.recommend !== 0, anonymous: !!r.is_anonymous, status: statusKey(r.status),
      created: toDate(r.created_at), event: { id: ev.id, name: txt(ev.name) || 'Activitate', date: toDate(ev.date), image: imgUrl(ev.image) }
    };
  }
  function normEvent(e) {
    return { id: e.id, name: txt(e.name) || 'Activitate', date: toDate(e.date), venue: txt(e.venue), city: txt(e.city), image: imgUrl(e.image) };
  }
  function items() {
    var list = reviews.map(function (r) { return { kind: 'review', r: r, status: r.status, rating: r.rating, name: r.event.name, text: r.text, date: r.created }; });
    activeDrafts().forEach(function (d) {
      list.push({ kind: 'draft', d: d, status: 'draft', rating: stars5(d.rating), name: txt(d.event.name) || 'Activitate', text: txt(d.text), date: toDate(d.saved) });
    });
    return list.sort(function (a, b) { return (b.date ? b.date.getTime() : 0) - (a.date ? a.date.getTime() : 0); });
  }
  function filtering() { return !!(filters.q || filters.status !== 'all' || filters.rating !== 'all'); }
  function renderList() {
    if (!reviewsOk) return;
    var all = items(), q = norm(filters.q), ul = $('rv-list'), frag = document.createDocumentFragment();
    var list = all.filter(function (it) {
      return (filters.status === 'all' || it.status === filters.status) && (filters.rating === 'all' || String(it.rating) === filters.rating)
        && (!q || norm(it.name + ' ' + it.text).indexOf(q) !== -1);
    });
    list.forEach(function (it) { frag.appendChild(it.kind === 'draft' ? draftCard(it) : reviewCard(it)); });
    ul.textContent = '';
    ul.appendChild(frag);
    $('rv-list-count').textContent = filtering() ? num.format(list.length) + ' din ' + plural(all.length, 'recenzie', 'recenzii') : plural(all.length, 'recenzie', 'recenzii');
    show('rv-reset', filtering());
    show('rv-list-skel', false);
    show('rv-list-error', false);
    show(ul, list.length > 0);
    show('rv-list-empty', !list.length);
    if (!list.length) {
      $('rv-list-empty-h').textContent = all.length ? 'Nu am găsit recenzii pentru filtre' : 'Încă nu ai recenzii';
      $('rv-list-empty-p').textContent = all.length ? 'Schimbă filtrele sau caută după alt termen.' : 'Recenziile pe care le scrii apar aici, împreună cu drafturile.';
      show('rv-list-empty-reset', all.length > 0);
    }
  }
  function cardShell(it, cls) {
    var li = el('li', 'rv-card' + (cls ? ' ' + cls : '')), media = mediaBox('rv-card-media', it.kind === 'draft' ? imgUrl(it.d.event.image) : it.r.event.image), body = el('div', 'rv-card-body'), tags = el('div', 'rv-tags');
    var st = STATUS[it.status];
    tags.appendChild(el('span', 'acc-tag ' + st[1], st[0]));
    body.appendChild(tags);
    li.appendChild(media);
    li.appendChild(body);
    return { li: li, body: body, tags: tags };
  }
  function reviewCard(it) {
    var r = it.r, c = cardShell(it), actions = el('div', 'rv-row-actions'), extras = [];
    c.li.id = 'rv-r-' + r.id;
    if (r.created) c.tags.appendChild(el('span', 'acc-tag', day(r.created)));
    c.body.appendChild(el('h3', null, r.event.name));
    if (r.event.date) c.body.appendChild(el('p', 'rv-card-meta', 'Activitatea din ' + day(r.event.date)));
    if (r.rating) c.body.appendChild(starsRow(r.rating, 'Rating ' + r.rating + ' din 5'));
    if (r.text) c.body.appendChild(el('p', 'rv-card-text', r.text));
    if (r.anonymous) extras.push('publicată fără nume');
    if (!r.recommend) extras.push('nu o recomanzi');
    if (Object.keys(r.aspects).length) extras.push('cu evaluare detaliată');
    if (extras.length) c.body.appendChild(el('p', 'rv-card-meta', extras.join(' · ')));
    if (r.photos.length) {
      var gallery = el('div', 'rv-card-photos');
      r.photos.slice(0, 5).forEach(function (src, i) {
        var a = el('a');
        a.href = src;
        a.target = '_blank';
        a.rel = 'noopener';
        a.setAttribute('aria-label', 'Poza ' + (i + 1) + ' din recenzie (se deschide într-o filă nouă)');
        a.appendChild(image(src));
        gallery.appendChild(a);
      });
      c.body.appendChild(gallery);
    }
    if (r.status === 'moderation') c.body.appendChild(el('p', 'rv-card-note', 'O verificăm înainte să apară pe site.'));
    if (r.status === 'rejected') c.body.appendChild(el('p', 'rv-card-note is-bad', 'Recenzia nu a fost aprobată. O poți edita și retrimite la verificare.'));
    var edit = button('Editează', 'btn-ghost rv-edit-btn'), search = el('a', 'btn btn-ghost', 'Caută activitatea'), del = button('Șterge', 'rv-danger');
    edit.setAttribute('aria-label', 'Editează recenzia pentru ' + r.event.name);
    del.setAttribute('aria-label', 'Șterge recenzia pentru ' + r.event.name);
    search.href = '/cauta?q=' + encodeURIComponent(r.event.name);
    edit.addEventListener('click', function () { openEdit(r, edit); });
    del.addEventListener('click', function () {
      confirmRow(actions, 'Ștergi recenzia?', 'Da, șterge', function (done) { deleteReview(r, done); });
    });
    actions.appendChild(edit);
    actions.appendChild(search);
    actions.appendChild(del);
    c.body.appendChild(actions);
    return c.li;
  }
  function draftCard(it) {
    var d = it.d, c = cardShell(it, 'is-draft'), actions = el('div', 'rv-row-actions');
    if (it.date) c.tags.appendChild(el('span', 'acc-tag', 'salvat ' + day(it.date)));
    c.body.appendChild(el('h3', null, it.name));
    if (it.rating) c.body.appendChild(starsRow(it.rating, 'Rating ' + it.rating + ' din 5'));
    if (it.text) c.body.appendChild(el('p', 'rv-card-text', it.text));
    c.body.appendChild(el('p', 'rv-card-note is-muted', 'Draftul este salvat doar pe acest dispozitiv și nu a fost trimis.'));
    var go = button('Continuă recenzia', 'btn-ghost'), del = button('Șterge draftul', 'rv-danger');
    go.setAttribute('aria-label', 'Continuă recenzia pentru ' + it.name);
    del.setAttribute('aria-label', 'Șterge draftul pentru ' + it.name);
    go.addEventListener('click', function () { continueDraft(d); });
    del.addEventListener('click', function () {
      confirmRow(actions, 'Ștergi draftul?', 'Da, șterge', function (done) {
        delete drafts[d.event.id];
        if (!persistDrafts(false)) { done(false); return; }
        done(true);
        if (todo[idx] && String(todo[idx].id) === String(d.event.id)) fillForm(null);
        say('Am șters draftul pentru „' + it.name + '”.');
        renderStats();
        renderList();
        $('rv-list-h').focus();
      });
    });
    actions.appendChild(go);
    actions.appendChild(del);
    c.body.appendChild(actions);
    return c.li;
  }
  function continueDraft(d) {
    var at = -1;
    todo.forEach(function (e, i) { if (String(e.id) === String(d.event.id)) at = i; });
    if (at === -1) return;
    if (at !== idx && dirty && todo[idx]) saveDraft(todo[idx], true);
    idx = at;
    renderTodo(true);
    $('de-evaluat').scrollIntoView({ block: 'start', behavior: reduce ? 'auto' : 'smooth' });
    try { $('rv-text').focus({ preventScroll: true }); } catch (e) { $('rv-text').focus(); }
  }
  function deleteReview(r, done) {
    API.delete('/customer/reviews/' + encodeURIComponent(r.id), {}).then(function () {
      done(true);
      say('Am șters recenzia pentru „' + r.event.name + '”.');
      Promise.all([loadReviews(true), loadTodo(true)]).then(function () { $('rv-list-h').focus(); });
    }, function (err) {
      if (err && err.status === 404) { done(true); say('Recenzia fusese deja ștearsă.'); loadReviews(true); loadTodo(true); return; }
      done(false);
      say(errMessage(err, 'Nu am putut șterge recenzia. Încearcă din nou.'), 'error');
    });
  }

  // ---------- filters ----------
  function syncUrl() {
    var p = new URLSearchParams(window.location.search);
    ['q', 'status', 'rating'].forEach(function (k) { var v = filters[k]; if (v && v !== 'all') p.set(k, v); else p.delete(k); });
    var qs = p.toString();
    try { history.replaceState(history.state, '', window.location.pathname + (qs ? '?' + qs : '') + window.location.hash); } catch (e) {}
  }
  $('rv-q').value = filters.q;
  $('rv-status').value = filters.status;
  $('rv-rating-filter').value = filters.rating;
  $('rv-q').addEventListener('input', function () { filters.q = this.value.trim().slice(0, 100); syncUrl(); renderList(); });
  $('rv-status').addEventListener('change', function () { filters.status = this.value; syncUrl(); renderList(); });
  $('rv-rating-filter').addEventListener('change', function () { filters.rating = this.value; syncUrl(); renderList(); });
  function resetFilters() {
    filters = { q: '', status: 'all', rating: 'all' };
    $('rv-q').value = '';
    $('rv-status').value = 'all';
    $('rv-rating-filter').value = 'all';
    syncUrl();
    renderList();
    $('rv-q').focus();
  }
  $('rv-reset').addEventListener('click', resetFilters);
  $('rv-list-empty-reset').addEventListener('click', resetFilters);

  // ---------- edit dialog ----------
  var dialog = $('rv-edit');
  function openEdit(r, from) {
    editing = r;
    editOpener = from;
    $('rv-edit-h').textContent = r.event.name;
    setStars('rv-e-rating', r.rating);
    $('rv-e-text').value = r.text;
    counter('rv-e-text', 'rv-e-text-count');
    ASPECTS.forEach(function (a) { setStars('rv-e-a-' + a, r.aspects[a] || 0); });
    $('rv-e-recommend').checked = r.recommend;
    $('rv-e-anonymous').checked = r.anonymous;
    $('rv-e-more').open = Object.keys(r.aspects).length > 0;
    $('rv-e-photos').textContent = r.photos.length ? 'Pozele încărcate (' + r.photos.length + ') rămân neschimbate.' : '';
    show('rv-e-photos', r.photos.length > 0);
    clearInvalid(dialog);
    formError('rv-edit-error', '');
    if (typeof dialog.showModal === 'function') { if (!dialog.open) dialog.showModal(); } else dialog.setAttribute('open', '');
    firstStar('rv-e-rating').focus();
  }
  function closeEdit() {
    if (typeof dialog.close === 'function') { if (dialog.open) dialog.close(); }
    else { dialog.removeAttribute('open'); dialog.dispatchEvent(new Event('close')); }
  }
  dialog.addEventListener('click', function (e) { if (e.target === dialog) closeEdit(); });
  dialog.addEventListener('close', function () {
    var id = editing && editing.id, card = id != null ? $('rv-r-' + id) : null;
    if (editOpener && editOpener.isConnected) editOpener.focus();
    else if (card) card.querySelector('.rv-edit-btn').focus();
    else $('rv-list-h').focus();
    editing = null;
    editOpener = null;
  });
  [].forEach.call(dialog.querySelectorAll('[data-close]'), function (b) { b.addEventListener('click', closeEdit); });
  $('rv-e-text').addEventListener('input', function () { counter('rv-e-text', 'rv-e-text-count'); this.removeAttribute('aria-invalid'); });
  function sameAspects(a, b) {
    return ASPECTS.every(function (k) { return (a[k] || 0) === (b[k] || 0); });
  }
  $('rv-edit-form').addEventListener('submit', function (e) {
    e.preventDefault();
    var r = editing, save = $('rv-edit-save');
    if (!r || save.disabled) return;
    var f = { rating: starValue('rv-e-rating'), text: $('rv-e-text').value.trim(), aspects: readAspects('rv-e-a-'), recommend: $('rv-e-recommend').checked, anonymous: $('rv-e-anonymous').checked };
    clearInvalid(dialog);
    formError('rv-edit-error', '');
    if (!f.rating) return formError('rv-edit-error', 'Alege un rating de la 1 la 5 stele.', firstStar('rv-e-rating'));
    if (f.text.length < limits.minText) return formError('rv-edit-error', 'Scrie cel puțin ' + plural(limits.minText, 'caracter', 'caractere') + ' (acum ' + f.text.length + ').', $('rv-e-text'));
    var data = {};
    if (f.rating !== r.rating) data.rating = f.rating;
    if (f.text !== r.text) data.text = f.text;
    if (!sameAspects(f.aspects, r.aspects)) data.detailed_ratings = f.aspects;
    if (f.recommend !== r.recommend) data.recommend = f.recommend;
    if (f.anonymous !== r.anonymous) data.anonymous = f.anonymous;
    if (!Object.keys(data).length) { closeEdit(); say('Nu ai modificat nimic.'); return; }
    var remoderate = data.rating != null || data.text != null;
    busy(save, true, 'Se salvează…');
    API.put('/customer/reviews/' + encodeURIComponent(r.id), data).then(function () {
      busy(save, false);
      closeEdit();
      say(remoderate ? 'Recenzia a fost actualizată și trece din nou prin verificare.' : 'Recenzia a fost actualizată.');
      loadReviews(true).then(function () {
        var card = $('rv-r-' + r.id);
        if (card) card.querySelector('.rv-edit-btn').focus();
      });
    }, function (err) {
      busy(save, false);
      var keys = err && obj(err.errors) ? Object.keys(err.errors) : [];
      if (err && err.status === 404) { closeEdit(); say('Recenzia nu mai există.', 'error'); loadReviews(true); loadTodo(true); return; }
      if (err && err.status === 422 && keys.indexOf('text') !== -1) formError('rv-edit-error', 'Textul trebuie să aibă între ' + limits.minText + ' și ' + num.format(limits.maxText) + ' de caractere.', $('rv-e-text'));
      else if (err && err.status === 422 && keys.indexOf('rating') !== -1) formError('rv-edit-error', 'Alege un rating de la 1 la 5 stele.', firstStar('rv-e-rating'));
      else formError('rv-edit-error', errMessage(err, 'Nu am putut salva modificările. Încearcă din nou.'));
    });
  });

  // ---------- counters ----------
  function renderStats() {
    var published = reviews.filter(function (r) { return r.status === 'published'; });
    var moderation = reviews.filter(function (r) { return r.status === 'moderation'; }).length;
    var avg = published.length ? published.reduce(function (s, r) { return s + r.rating; }, 0) / published.length : 0;
    $('rv-s-pub').textContent = reviewsOk ? num.format(published.length) : '—';
    $('rv-s-mod').textContent = reviewsOk ? num.format(moderation) : '—';
    $('rv-s-draft').textContent = num.format(activeDrafts().length);
    $('rv-s-todo').textContent = todoOk ? num.format(todo.length) : '—';
    $('rv-s-todo-p').textContent = todoOk ? (todo.length ? 'experiențe recente' : 'toate scrise') : todoFailed ? 'nu am putut verifica' : 'se verifică…';
    $('rv-avg').textContent = one.format(avg);
    $('rv-pub-label').textContent = plural(published.length, 'recenzie publicată', 'recenzii publicate');
    [].forEach.call($('rv-avg-stars').querySelectorAll('svg'), function (s, i) { s.classList.toggle('is-on', i < Math.round(avg)); });
    $('rv-avg-stars').setAttribute('aria-label', 'Rating mediu ' + one.format(avg) + ' din 5');
    $('rv-score-hint').textContent = todoOk
      ? (todo.length ? plural(todo.length, 'recenzie așteaptă să fie scrisă.', 'recenzii așteaptă să fie scrise.') : 'Felicitări! Toate sunt scrise.')
      : todoFailed ? 'Nu am putut verifica activitățile de evaluat.' : 'Se verifică activitățile de evaluat…';
    account.setBadges({ reviews: todoOk ? todo.length : 0 });
  }

  // ---------- load ----------
  function loadMeta() {
    return API.get('/customer/reviews/meta').then(function (resp) {
      var d = resp && obj(resp.data) ? resp.data : {};
      fillOptions($('rv-suitable'), d.suitable_for);
      fillOptions($('rv-age'), d.age_groups);
      if (count(d.text_min_chars) > limits.minText) limits.minText = Math.min(count(d.text_min_chars), 500); // core itself needs at least 20
      if (count(d.text_max_chars)) limits.maxText = Math.max(limits.minText, Math.min(count(d.text_max_chars), 2000));
      if (count(d.photos_max_count)) limits.photos = Math.min(count(d.photos_max_count), 5);
      if (count(d.photos_max_size_mb)) limits.mb = Math.min(count(d.photos_max_size_mb), 5);
      $('rv-text').maxLength = limits.maxText;
      $('rv-e-text').maxLength = limits.maxText;
      counter('rv-text', 'rv-text-count');
      renderThumbs();
    }, function () {});
  }
  function fillOptions(sel, list) {
    var opts = (Array.isArray(list) ? list : []).filter(obj).map(function (o) { return [txt(o.value), txt(o.label)]; }).filter(function (o) { return o[0] && o[1]; });
    if (!opts.length) return;
    var keep = sel.value;
    sel.textContent = '';
    opts.forEach(function (o) { sel.appendChild(new Option(o[1], o[0])); });
    setSelect(sel, keep);
  }
  function loadTodo(silent) {
    if (!silent) { show('rv-todo-skel', true); show('rv-todo-error', false); show('rv-todo-empty', false); show('rv-todo', false); }
    return fresh('/customer/reviews/events-to-review').then(function (resp) {
      var d = resp && resp.data, list = obj(d) && Array.isArray(d.events) ? d.events : (Array.isArray(d) ? d : []);
      var keep = todo[idx] ? todo[idx].id : null;
      todo = list.filter(obj).map(normEvent).filter(function (e) { return e.id != null; });
      todoOk = true;
      todoFailed = false;
      var at = -1;
      todo.forEach(function (e, i) { if (keep != null && String(e.id) === String(keep)) at = i; });
      idx = at !== -1 ? at : Math.min(idx, Math.max(0, todo.length - 1));
      Object.keys(drafts).forEach(function (k) { // reviewed or no longer reviewable (also on another device)
        if (!todo.some(function (e) { return String(e.id) === String(k); })) delete drafts[k];
      });
      persistDrafts(true);
      renderTodo(false);
      renderStats();
      renderList();
    }, function (err) {
      if (err && err.status === 401) { guard(); return; }
      todoFailed = true;
      if (!todoOk) { show('rv-todo-skel', false); show('rv-todo', false); show('rv-todo-empty', false); show('rv-todo-error', true); }
      else say('Nu am putut reîmprospăta activitățile de evaluat.', 'error');
      renderStats();
    });
  }
  function loadReviews(silent) {
    var all = [];
    if (!silent) { show('rv-list-skel', true); show('rv-list-error', false); show('rv-list-empty', false); show('rv-list', false); }
    function page(n) {
      return fresh('/customer/reviews?per_page=50&page=' + n).then(function (resp) {
        var d = resp && resp.data;
        (Array.isArray(d) ? d : (obj(d) && Array.isArray(d.reviews) ? d.reviews : [])).filter(obj).forEach(function (r) { all.push(r); });
        var last = count(resp && resp.meta && resp.meta.last_page);
        return n < last && n < 20 ? page(n + 1) : null;
      });
    }
    return page(1).then(function () {
      reviews = all.map(normReview);
      reviewsOk = true;
      renderStats();
      renderList();
    }, function (err) {
      if (err && err.status === 401) { guard(); return; }
      if (!reviewsOk) { show('rv-list-skel', false); show('rv-list', false); show('rv-list-empty', false); show('rv-list-error', true); }
      else say('Nu am putut reîmprospăta lista de recenzii.', 'error');
    });
  }
  $('rv-todo-retry').addEventListener('click', function () { loadTodo(false); });
  $('rv-list-retry').addEventListener('click', function () { loadReviews(false); });

  if (!account.isCustomer()) { guard(); return; }
  drafts = readDrafts();
  counter('rv-text', 'rv-text-count');
  renderThumbs();
  renderStats();
  loadMeta();
  loadTodo(false);
  loadReviews(false);
})();
