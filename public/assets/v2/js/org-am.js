/* viaqui.com v2: shared kit of the operator's activities-module screens (/organizator/locatii, /organizator/produse,
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
    // "Still to fill in: …" lists everything in its message; the missing keys come along only for the page
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
    if (!file) return Promise.reject(new Error(VQ.t('No file.')));
    if (!/^image\/(jpeg|png|webp)$/.test(file.type)) return Promise.reject(new Error(VQ.t('Only JPG, PNG or WebP photos.')));
    if (file.size > MAX_IMAGE) return Promise.reject(new Error(VQ.t('The photo is larger than 10 MB.')));
    var fd = new FormData();
    fd.append('file', file);
    fd.append('kind', kind);
    return BileteOnlineAPI._postMultipart(BASE + '/uploads', fd).then(function (r) {
      var d = (r && r.data) || {};
      if (!d.path) throw new Error(VQ.t('The upload did not return the photo.'));
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
  /** "São João" → "sao joao": what a search in a list compares. */
  function fold(s) {
    return String(s == null ? '' : s).toLowerCase().normalize('NFD').replace(/[̀-ͯ]/g, '').replace(/\s+/g, ' ').trim();
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
  /**
   * A list you pick one value from by typing: an <input role="combobox"> and a
   * filtered listbox under it. options are [[value, label]]; opts: num (values
   * are numbers), empty (label of the "nothing chosen" row), ph, on(value).
   * The returned node carries setOptions(list) and setValue(v) — the city list
   * is redrawn whenever the county above it changes.
   */
  function combo(obj, key, options, opts) {
    opts = opts || {};
    var id = nid(key);
    var listId = id + '-list';
    var input = el('input', {
      class: 'po-input po-combo-in', id: id, type: 'text', autocomplete: 'off', spellcheck: 'false',
      role: 'combobox', 'aria-expanded': 'false', 'aria-controls': listId, 'aria-autocomplete': 'list',
      placeholder: opts.ph || VQ.t('Search the list…'),
    });
    var caret = el('button', { class: 'po-combo-btn', type: 'button', tabindex: '-1', 'aria-label': VQ.t('Open the list') }, [O.icon('caret-down')]);
    var list = el('ul', { class: 'po-combo-list', id: listId, role: 'listbox', hidden: true });
    var wrap = el('span', { class: 'po-combo', 'data-for': id }, [input, caret, list]);
    var all = [], shown = [], active = -1, open = false;

    function labelOf(v) {
      for (var i = 0; i < all.length; i++) if (String(all[i][0]) === String(v)) return all[i][1];
      return null;
    }
    function rows(q) {
      q = fold(q);
      if (!q) return all.slice(0);
      return all.filter(function (o) { return o[0] === null ? true : fold(o[1]).indexOf(q) >= 0; });
    }
    function draw() {
      list.textContent = '';
      if (!shown.length) {
        list.appendChild(el('li', { class: 'po-combo-none', text: VQ.t('Nothing found. Delete some of the text and try again.') }));
        return;
      }
      shown.forEach(function (o, i) {
        var li = el('li', {
          class: 'po-combo-opt' + (i === active ? ' is-active' : ''), id: listId + '-' + i, role: 'option',
          'aria-selected': String(o[0]) === String(obj[key] == null ? '' : obj[key]) ? 'true' : 'false', text: o[1],
        });
        li.addEventListener('mousedown', function (e) { e.preventDefault(); pick(o[0]); });
        list.appendChild(li);
      });
      input.setAttribute('aria-activedescendant', active >= 0 ? listId + '-' + active : '');
    }
    function show(q) {
      shown = rows(q);
      active = -1;
      for (var i = 0; i < shown.length; i++) {
        if (String(shown[i][0]) === String(obj[key] == null ? '' : obj[key])) { active = i; break; }
      }
      if (active < 0 && shown.length) active = 0;
      open = true;
      list.hidden = false;
      wrap.classList.add('is-open');
      input.setAttribute('aria-expanded', 'true');
      draw();
      scrollActive();
    }
    function hide(restore) {
      open = false;
      list.hidden = true;
      wrap.classList.remove('is-open');
      input.setAttribute('aria-expanded', 'false');
      input.removeAttribute('aria-activedescendant');
      if (restore !== false) input.value = labelOf(obj[key]) || '';
    }
    function scrollActive() {
      var n = active >= 0 ? list.children[active] : null;
      if (!n || !n.scrollIntoView) return;
      if (n.offsetTop < list.scrollTop) list.scrollTop = n.offsetTop;
      else if (n.offsetTop + n.offsetHeight > list.scrollTop + list.clientHeight) list.scrollTop = n.offsetTop + n.offsetHeight - list.clientHeight;
    }
    function pick(v) {
      obj[key] = v == null || v === '' ? null : (opts.num ? parseInt(v, 10) : v);
      hide();
      if (opts.on) opts.on(obj[key]);
    }
    function move(step) {
      if (!open) { show(''); return; }
      if (!shown.length) return;
      active = (active + step + shown.length) % shown.length;
      draw();
      scrollActive();
    }

    input.addEventListener('focus', function () { input.select(); });
    input.addEventListener('mousedown', function () { if (!open) setTimeout(function () { show(''); }, 0); });
    input.addEventListener('input', function () { show(input.value); });
    input.addEventListener('keydown', function (e) {
      if (e.key === 'ArrowDown') { e.preventDefault(); move(1); }
      else if (e.key === 'ArrowUp') { e.preventDefault(); move(-1); }
      else if (e.key === 'Home' && open) { e.preventDefault(); active = 0; draw(); scrollActive(); }
      else if (e.key === 'End' && open) { e.preventDefault(); active = shown.length - 1; draw(); scrollActive(); }
      else if (e.key === 'Enter') { if (open) { e.preventDefault(); if (active >= 0) pick(shown[active][0]); else hide(); } }
      else if (e.key === 'Escape') { if (open) { e.preventDefault(); e.stopPropagation(); hide(); } }
      else if (e.key === 'Tab') hide();
    });
    input.addEventListener('blur', function () { setTimeout(function () { if (open) hide(); }, 0); });
    caret.addEventListener('mousedown', function (e) { e.preventDefault(); });
    caret.addEventListener('click', function () { if (open) hide(); else { input.focus(); show(''); } });

    // silent: the first call, while the form is being built — nothing counts as an edit yet.
    wrap.setOptions = function (list2, silent) {
      all = (opts.empty ? [[null, opts.empty]] : []).concat(list2 || []);
      if (obj[key] != null && labelOf(obj[key]) === null) { obj[key] = null; if (opts.on && !silent) opts.on(null); }
      input.value = labelOf(obj[key]) || '';
      if (open) show(input.value === (labelOf(obj[key]) || '') ? '' : input.value);
    };
    wrap.setValue = function (v) { obj[key] = v; input.value = labelOf(v) || ''; };
    wrap.setOptions(options, true);
    return wrap;
  }

  /* ---------- rich text ---------- */
  // Exactly what the core keeps (OrganizerCatalog::html → HTMLPurifier
  // "p,br,b,strong,i,em,u,ul,ol,li,h3,h4,a[href|title]"). Anything else is
  // unwrapped, so nothing the operator sees is lost on save.
  var RICH_OK = { P: 1, BR: 1, B: 1, STRONG: 1, I: 1, EM: 1, U: 1, UL: 1, OL: 1, LI: 1, H3: 1, H4: 1, A: 1 };
  // Thrown away with everything inside them: their text is code or chrome, not writing.
  var RICH_DROP = {
    SCRIPT: 1, STYLE: 1, NOSCRIPT: 1, TEMPLATE: 1, IFRAME: 1, OBJECT: 1, EMBED: 1, SVG: 1, MATH: 1,
    LINK: 1, META: 1, HEAD: 1, TITLE: 1, AUDIO: 1, VIDEO: 1, CANVAS: 1, FORM: 1, INPUT: 1, BUTTON: 1,
    SELECT: 1, OPTION: 1, TEXTAREA: 1, IMG: 1, PICTURE: 1, SOURCE: 1,
  };
  var RICH_BLOCK = 'p,ul,ol,h3,h4';
  var RICH_HREF = /^(https?:\/\/|mailto:|\/)/i;
  /** Strips everything outside the allow-list, in place. */
  function richClean(root) {
    var kids = [].slice.call(root.childNodes);
    for (var i = 0; i < kids.length; i++) {
      var n = kids[i];
      if (n.nodeType === 3) continue;
      if (n.nodeType !== 1) { root.removeChild(n); continue; }
      if (RICH_DROP[n.tagName]) { root.removeChild(n); continue; }
      if (n.tagName === 'DIV') {
        var p = document.createElement('p');
        while (n.firstChild) p.appendChild(n.firstChild);
        root.replaceChild(p, n);
        n = p;
      }
      if (n.tagName === 'H1' || n.tagName === 'H2') {
        var h = document.createElement('h3');
        while (n.firstChild) h.appendChild(n.firstChild);
        root.replaceChild(h, n);
        n = h;
      }
      if (!RICH_OK[n.tagName]) {
        richClean(n);
        while (n.firstChild) root.insertBefore(n.firstChild, n);
        root.removeChild(n);
        continue;
      }
      var attrs = [].slice.call(n.attributes);
      for (var j = 0; j < attrs.length; j++) {
        if (!(n.tagName === 'A' && attrs[j].name === 'href')) n.removeAttribute(attrs[j].name);
      }
      if (n.tagName === 'A' && !RICH_HREF.test(n.getAttribute('href') || '')) {
        while (n.firstChild) root.insertBefore(n.firstChild, n);
        root.removeChild(n);
        continue;
      }
      richClean(n);
      // execCommand likes to put a list inside the paragraph it started from; the parser (and
      // HTMLPurifier on save) would never accept that, so the paragraph steps aside.
      if (n.tagName === 'P' && n.querySelector(RICH_BLOCK)) {
        while (n.firstChild) root.insertBefore(n.firstChild, n);
        root.removeChild(n);
      }
    }
  }
  /** Loose text and <b>…</b> at the top become paragraphs, so what is saved is always blocks. */
  function richWrap(root) {
    var run = null;
    var kids = [].slice.call(root.childNodes);
    for (var i = 0; i < kids.length; i++) {
      var n = kids[i];
      var block = n.nodeType === 1 && (n.tagName === 'P' || n.tagName === 'UL' || n.tagName === 'OL' || n.tagName === 'H3' || n.tagName === 'H4');
      if (block) { run = null; continue; }
      if (n.nodeType === 3 && !n.nodeValue.replace(/\s| /g, '')) { root.removeChild(n); continue; }
      if (!run) { run = document.createElement('p'); root.insertBefore(run, n); }
      run.appendChild(n);
    }
  }
  /** The HTML of a node once cleaned, or '' when it holds no text. */
  function richHtml(node) {
    var tmp = document.createElement('div');
    tmp.innerHTML = node.innerHTML;
    richClean(tmp);
    richWrap(tmp);
    var text = (tmp.textContent || '').replace(/​| /g, ' ').trim();
    if (!text && !tmp.querySelector('br')) return '';
    return tmp.innerHTML.trim();
  }
  function richInsert(html) {
    try { document.execCommand('insertHTML', false, html); } catch (e) {}
  }
  function esc(s) {
    return String(s).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;');
  }
  /**
   * A small what-you-see editor bound to obj[key] (HTML). opts: label (what
   * the screen reader announces), max (characters of HTML the core accepts),
   * ph, min (px of writing room), on(value).
   */
  function rich(obj, key, opts) {
    opts = opts || {};
    var max = opts.max || 20000;
    var ed = el('div', {
      class: 'po-rich-ed', contenteditable: 'true', role: 'textbox', 'aria-multiline': 'true',
      'aria-label': opts.label || null, 'data-ph': opts.ph || VQ.t('Write here…'), spellcheck: 'true',
    });
    if (opts.min) ed.style.minHeight = opts.min + 'px';
    var count = el('span', { class: 'po-rich-count' });
    var foot = el('span', { class: 'po-rich-foot' }, [
      el('small', { text: VQ.t('Bold, italic, lists and links. Any other formatting is lost on save.') }), count,
    ]);
    var bar = el('span', { class: 'po-rich-bar', role: 'group', 'aria-label': VQ.t('Formatting') });
    var box = el('div', { class: 'po-rich' }, [bar, ed, foot]);

    // quiet: the first read, while the form is being built — nothing counts as an edit yet.
    var last = null;
    function sync(quiet) {
      var html = richHtml(ed);
      obj[key] = html || null;
      var over = html.length > max;
      count.textContent = html.length + ' / ' + max;
      foot.classList.toggle('is-over', over);
      count.setAttribute('aria-live', over ? 'polite' : 'off');
      ed.setAttribute('data-empty', html ? '0' : '1');
      if (opts.on && quiet !== true && html !== last) opts.on(obj[key]);
      last = html;
    }
    function cmd(name, arg) {
      ed.focus();
      try { document.execCommand(name, false, arg == null ? null : arg); } catch (e) {}
      sync();
      state();
    }
    var buttons = [];
    function tool(label, aria, name, arg, cls) {
      var b = el('button', { class: 'po-rich-b' + (cls ? ' ' + cls : ''), type: 'button', title: aria, 'aria-label': aria });
      b.innerHTML = label;
      b.addEventListener('mousedown', function (e) { e.preventDefault(); });
      b.addEventListener('click', function () {
        if (name === 'createLink') { linkIt(); return; }
        if (name === 'clear') { cmd('removeFormat'); cmd('unlink'); cmd('formatBlock', '<p>'); return; }
        cmd(name, arg);
      });
      if (name !== 'createLink' && name !== 'clear') { b.setAttribute('aria-pressed', 'false'); buttons.push([b, name]); }
      bar.appendChild(b);
      return b;
    }
    function linkIt() {
      var url = window.prompt(VQ.t('Link address (https://…)'), 'https://');
      if (url === null) return;
      url = String(url).trim();
      if (!url) { cmd('unlink'); return; }
      if (!RICH_HREF.test(url)) { O.flash(VQ.t('The link must start with https:// or mailto:.'), true); return; }
      var sel = window.getSelection();
      if (!sel || sel.isCollapsed) { ed.focus(); richInsert('<a href="' + esc(url) + '">' + esc(url) + '</a>'); sync(); return; }
      cmd('createLink', url);
    }
    function state() {
      for (var i = 0; i < buttons.length; i++) {
        var ok = false;
        try { ok = document.queryCommandState(buttons[i][1]); } catch (e) {}
        buttons[i][0].setAttribute('aria-pressed', ok ? 'true' : 'false');
      }
    }
    tool('<b>B</b>', VQ.t('Bold'), 'bold');
    tool('<i>I</i>', VQ.t('Italic'), 'italic');
    tool('<u>U</u>', VQ.t('Underline'), 'underline');
    tool('<b>H</b>', VQ.t('Subheading'), 'formatBlock', '<h3>');
    tool('&bull;&nbsp;&mdash;', VQ.t('Bulleted list'), 'insertUnorderedList');
    tool('1.&nbsp;&mdash;', VQ.t('Numbered list'), 'insertOrderedList');
    tool('&#128279;', VQ.t('Link'), 'createLink');
    tool('&#10005;', VQ.t('Clear formatting'), 'clear');

    ed.innerHTML = obj[key] == null ? '' : String(obj[key]);
    richClean(ed);
    richWrap(ed);
    ed.addEventListener('input', function () { sync(); });
    ed.addEventListener('keyup', state);
    ed.addEventListener('mouseup', state);
    ed.addEventListener('focus', function () {
      try { document.execCommand('defaultParagraphSeparator', false, 'p'); } catch (e) {}
      state();
    });
    ed.addEventListener('blur', function () { richClean(ed); richWrap(ed); sync(); });
    ed.addEventListener('paste', function (e) {
      var cd = e.clipboardData || window.clipboardData;
      if (!cd) return;
      e.preventDefault();
      var html = cd.getData('text/html');
      if (html) {
        var doc = null;
        try { doc = new DOMParser().parseFromString(html, 'text/html'); } catch (err) { doc = null; }
        if (doc && doc.body) { richClean(doc.body); richInsert(doc.body.innerHTML); sync(); return; }
      }
      richInsert(esc(cd.getData('text/plain') || '').replace(/\r\n|\r|\n/g, '<br>'));
      sync();
    });
    ed.addEventListener('drop', function (e) {
      e.preventDefault();
      var dt = e.dataTransfer;
      if (!dt) return;
      richInsert(esc(dt.getData('text/plain') || '').replace(/\r\n|\r|\n/g, '<br>'));
      sync();
    });
    sync(true);
    box.richOver = function () { return richHtml(ed).length > max; };
    box.richMax = max;
    return box;
  }

  function check(obj, key, label, opts) {
    opts = opts || {};
    var i = el('input', { type: 'checkbox', id: nid(key) });
    i.checked = !!obj[key];
    i.addEventListener('change', function () { obj[key] = i.checked; if (opts.on) opts.on(i.checked); });
    var lab = el('label', { class: 'po-check' + (opts.wide ? ' is-wide' : '') }, [i, el('span', { text: label })]);
    return lab;
  }
  /**
   * Checkboxes for a set: obj[key] is the array of the checked keys, labels is
   * {key: text}. opts: groups ({title: [keys]}) to lay them out in blocks,
   * custom (the prefix of the operator's own entries, e.g. "custom:") with
   * limit, and on().
   */
  function checkset(obj, key, labels, opts) {
    opts = opts || {};
    if (!Array.isArray(obj[key])) obj[key] = [];
    var box = el('div', { class: 've-checkset' });
    var limit = opts.limit || 40;

    function toggle(k, on) {
      var set = obj[key].filter(function (x) { return x !== k; });
      if (on) {
        if (set.length >= limit) { O.flash(VQ.t('At most {n} facilities.', { n: limit }), true); return false; }
        set.push(k);
      }
      obj[key] = set;
      if (opts.on) opts.on(obj[key]);
      return true;
    }
    function boxOf(keys) {
      var g = el('div', { class: 've-checks' });
      keys.forEach(function (k) {
        if (!labels[k]) return;
        var i = el('input', { type: 'checkbox', value: k });
        i.checked = obj[key].indexOf(k) >= 0;
        i.addEventListener('change', function () { if (!toggle(k, i.checked)) i.checked = false; });
        g.appendChild(el('label', { class: 'po-check' }, [i, el('span', { text: labels[k] })]));
      });
      return g;
    }
    if (opts.groups && Object.keys(opts.groups).length) {
      Object.keys(opts.groups).forEach(function (title) {
        box.appendChild(el('div', { class: 've-checkgroup' }, [el('p', { class: 've-sec-k', text: title }), boxOf(opts.groups[title])]));
      });
    } else {
      box.appendChild(boxOf(Object.keys(labels)));
    }

    if (!opts.custom) return box;

    var prefix = opts.custom;
    var chips = el('ul', { class: 'am-chips' });
    var add = el('input', { class: 'po-input', type: 'text', maxlength: 40, id: nid(key + '-custom'), placeholder: VQ.t('E.g. Boat ramp'), autocomplete: 'off' });
    var addBtn = button('plus', VQ.t('Add'), 'btn btn-ghost');
    function drawChips() {
      chips.textContent = '';
      var own = obj[key].filter(function (x) { return String(x).indexOf(prefix) === 0; });
      own.forEach(function (k) {
        var li = el('li', { class: 'am-chip' }, [el('span', { text: String(k).slice(prefix.length) })]);
        var rm = button('x', null, 've-icon-btn', { 'aria-label': VQ.t('Remove {name}', { name: String(k).slice(prefix.length) }) });
        rm.addEventListener('click', function () { toggle(k, false); drawChips(); });
        li.appendChild(rm);
        chips.appendChild(li);
      });
      chips.hidden = !own.length;
    }
    function addOne() {
      var label = add.value.replace(/\s+/g, ' ').trim().slice(0, 40);
      if (!label) { add.focus(); return; }
      var k = prefix + label;
      if (obj[key].indexOf(k) >= 0) { O.flash(VQ.t('It is already in the list.'), true); add.value = ''; return; }
      if (!toggle(k, true)) return;
      add.value = '';
      drawChips();
      add.focus();
    }
    addBtn.addEventListener('click', addOne);
    add.addEventListener('keydown', function (e) { if (e.key === 'Enter') { e.preventDefault(); addOne(); } });
    drawChips();
    box.appendChild(el('div', { class: 've-checkgroup' }, [
      el('p', { class: 've-sec-k', text: opts.customLabel || VQ.t('Your own') }),
      chips,
      el('div', { class: 'am-chip-add' }, [
        el('span', { class: 'po-field' }, [el('label', { for: add.id, text: opts.customHint || VQ.t('Add a facility that is not in the list') }), add]),
        addBtn,
      ]),
    ]));
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
        var rm = button('trash', null, 've-icon-btn', { 'aria-label': (opts.removeLabel || VQ.t('Remove row')) + ' ' + (i + 1) });
        rm.addEventListener('click', function () { list.splice(i, 1); redraw(); if (opts.on) opts.on(); });
        row.appendChild(rm);
        box.appendChild(row);
      });
      if (opts.empty && !list.length) box.appendChild(el('p', { class: 've-sub', text: opts.empty }));
    }
    var add = button('plus', opts.addLabel || VQ.t('Add'));
    add.addEventListener('click', function () {
      if (opts.limit && list.length >= opts.limit) { O.flash(VQ.t('At most {n}.', { n: opts.limit }), true); return; }
      list.push(blank());
      redraw();
      if (opts.on) opts.on();
      var inputs = box.querySelectorAll('.ve-row:last-child input, .ve-row:last-child select');
      if (inputs[0]) inputs[0].focus();
    });
    redraw();
    return { box: box, addBtn: add, redraw: redraw };
  }
  /**
   * Makes a box take pictures dropped on it. onFiles gets the image files of
   * the drop; anything else is ignored (and the page never opens the file).
   */
  function dropzone(box, onFiles) {
    var depth = 0;
    function imagesOf(dt) {
      return Array.prototype.slice.call((dt && dt.files) || []).filter(function (f) { return f && /^image\//.test(f.type); });
    }
    box.addEventListener('dragenter', function (e) {
      if (!e.dataTransfer) return;
      e.preventDefault();
      depth += 1;
      box.classList.add('is-over');
    });
    box.addEventListener('dragover', function (e) {
      if (!e.dataTransfer) return;
      e.preventDefault();
      try { e.dataTransfer.dropEffect = 'copy'; } catch (err) {}
      box.classList.add('is-over');
    });
    box.addEventListener('dragleave', function () {
      depth -= 1;
      if (depth <= 0) { depth = 0; box.classList.remove('is-over'); }
    });
    box.addEventListener('drop', function (e) {
      e.preventDefault();
      depth = 0;
      box.classList.remove('is-over');
      var files = imagesOf(e.dataTransfer);
      if (!files.length) { O.flash(VQ.t('Drop a JPG, PNG or WebP photo.'), true); return; }
      onFiles(files);
    });
    return box;
  }
  /** One picture in obj[key] ({path, url} or null). Drag a file over it, or use the buttons. */
  function image(obj, key, kind, opts) {
    opts = opts || {};
    var box = el('div', { class: 've-image am-drop' });
    var shot = el('div', { class: 'am-shot' });
    var thumb = el('img', { class: 'am-shot-img', alt: '' });
    var file = el('input', { type: 'file', accept: 'image/jpeg,image/png,image/webp', class: 've-sr', id: nid(key + '-file') });
    var pickEmpty = el('label', { class: 'btn btn-ghost am-shot-btn', for: file.id }, [O.icon('plus'), el('span', { text: VQ.t('Choose a photo') })]);
    var pickOver = el('label', { class: 'btn btn-ghost am-shot-btn', for: file.id }, [O.icon('image'), el('span', { text: VQ.t('Change the photo') })]);
    var rm = button('trash', VQ.t('Remove the photo'), 'btn btn-ghost am-shot-btn');
    var empty = el('div', { class: 'am-shot-empty' }, [
      O.icon('image'), el('b', { text: VQ.t('Drop the photo here') }), el('small', { text: VQ.t('or') }), pickEmpty,
    ]);
    var tools = el('div', { class: 'am-shot-tools' }, [pickOver, rm]);
    var hint = el('span', { class: 've-sub', text: opts.hint || VQ.t('JPG, PNG or WebP, 10 MB at most. You can drop the photo straight onto the box.') });

    function put(f) {
      if (!f) return;
      box.classList.add('is-busy');
      upload(f, kind).then(function (img) { obj[key] = img; draw(); if (opts.on) opts.on(img); },
        function (err) { O.flash(errText(err, VQ.t('We could not upload the photo.')), true); })
        .then(function () { box.classList.remove('is-busy'); file.value = ''; });
    }
    function draw() {
      var src = imgUrl(obj[key]);
      thumb.hidden = !src;
      if (src) thumb.src = src; else thumb.removeAttribute('src');
      empty.hidden = !!src;
      tools.hidden = !src;
      shot.classList.toggle('is-empty', !src);
    }
    file.addEventListener('change', function () { put(file.files && file.files[0]); });
    rm.addEventListener('click', function () { obj[key] = null; draw(); if (opts.on) opts.on(null); });
    shot.appendChild(thumb);
    shot.appendChild(empty);
    shot.appendChild(tools);
    box.appendChild(shot);
    box.appendChild(el('span', { class: 've-image-tools' }, [file, hint]));
    dropzone(box, function (files) { put(files[0]); });
    draw();
    return box;
  }
  /** Several pictures in obj[key] ([{path, url}]). The whole box takes drops. on() runs after each change. */
  function gallery(obj, key, kind, limit, on) {
    if (!Array.isArray(obj[key])) obj[key] = [];
    limit = limit || 20;
    var box = el('div', { class: 'am-gal am-drop' });
    var list = el('ul', { class: 'am-gal-list' });
    var file = el('input', { type: 'file', accept: 'image/jpeg,image/png,image/webp', multiple: true, class: 've-sr', id: nid(key + '-files') });
    var pick = el('label', { class: 'btn btn-ghost', for: file.id }, [O.icon('plus'), el('span', { text: VQ.t('Add photos') })]);
    var full = el('p', { class: 've-sub', text: VQ.t('You have reached {n} photos.', { n: limit }) });
    var zone = el('div', { class: 'am-gal-zone' }, [
      O.icon('image'), el('b', { text: VQ.t('Drop the photos here') }), el('small', { text: VQ.t('or') }), pick, full,
    ]);
    function put(files) {
      files = files.slice(0, limit - obj[key].length);
      if (!files.length) { O.flash(VQ.t('At most {n} photos.', { n: limit }), true); return; }
      box.classList.add('is-busy');
      files.reduce(function (p, f) {
        return p.then(function () {
          return upload(f, kind).then(function (img) { obj[key].push(img); draw(); if (on) on(); }, function (err) { O.flash(errText(err, VQ.t('One photo did not upload.')), true); });
        });
      }, Promise.resolve()).then(function () { box.classList.remove('is-busy'); file.value = ''; });
    }
    function draw() {
      list.textContent = '';
      obj[key].forEach(function (img, i) {
        var li = el('li', { class: 'am-gal-item' });
        var src = imgUrl(img);
        if (src) li.appendChild(el('img', { src: src, alt: '' }));
        var rm = button('x', null, 've-icon-btn', { 'aria-label': VQ.t('Remove photo {n}', { n: i + 1 }) });
        rm.addEventListener('click', function () { obj[key].splice(i, 1); draw(); if (on) on(); });
        li.appendChild(rm);
        list.appendChild(li);
      });
      pick.hidden = obj[key].length >= limit;
      full.hidden = obj[key].length < limit;
    }
    file.addEventListener('change', function () { put(Array.prototype.slice.call(file.files || [])); });
    box.appendChild(list);
    box.appendChild(zone);
    box.appendChild(file);
    dropzone(box, put);
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
    draft: [VQ.t('Draft'), 'is-muted'], pending: [VQ.t('In review'), 'is-wait'], rejected: [VQ.t('Rejected'), 'is-bad'], approved: [VQ.t('Approved'), 'is-ok'],
  };
  /** Tags for a location or product: its review step and whether it is on the site. */
  function statusTags(item) {
    var out = [];
    var r = item.review_status ? REVIEW[item.review_status] : [VQ.t('Added by Viaqui'), 'is-ok'];
    if (r) out.push(el('span', { class: 'org-tag ' + r[1], text: r[0] }));
    if (!item.review_status || item.review_status === 'approved') {
      out.push(el('span', { class: 'org-tag ' + (item.is_published ? 'is-ok' : 'is-muted'), text: item.is_published ? VQ.t('On the site') : VQ.t('Hidden') }));
    }
    if (item.pos_only) out.push(el('span', { class: 'org-tag is-muted', text: VQ.t('Counter only') }));
    return out;
  }
  /** What the operator can do next, as text under the tags. */
  function statusHint(item) {
    if (item.review_status === 'rejected') return item.rejection_reason ? VQ.t('Rejected: {reason}. Fix it and send it again.', { reason: item.rejection_reason }) : VQ.t('Rejected. Fix it and send it again.');
    if (item.review_status === 'pending') return VQ.t('The Viaqui team is checking it and will write to you. You can keep making changes.');
    if (item.review_status === 'draft') return VQ.t('Draft. When it is ready, send it for approval.');
    if (!item.is_published) return VQ.t('Approved, but hidden from the site.');
    return '';
  }
  /** "21 September 2026, 14:30" from an ISO date, '' when there isn't one. */
  function when(iso) {
    if (!iso) return '';
    var d = new Date(iso);
    if (isNaN(d.getTime())) return '';
    try {
      var loc = VQ.locale === 'en' ? 'en-GB' : VQ.locale;
      return d.toLocaleDateString(loc, { day: 'numeric', month: 'long', year: 'numeric' })
        + ', ' + d.toLocaleTimeString(loc, { hour: '2-digit', minute: '2-digit' });
    } catch (e) { return ''; }
  }
  var WHY = VQ.t('Viaqui checks every new place once: the name, the address, the photos and the prices. This keeps empty or wrong pages off the site, and visitors trust what they buy. The check is done only once: after approval, your changes go straight to the site.');
  /**
   * The big "where is this at" panel of an editor: the state, what it means,
   * and what happens next. kind says what the thing is: 'venue' (the default) or 'product'.
   */
  function statusPanel(item, kind) {
    var st = item.review_status || 'approved';
    // one whole sentence per kind: the headings are not built from a noun and a tail
    var H = kind === 'product' ? {
      draft: VQ.t('This product is a draft: nobody sees it yet'),
      pending: VQ.t('This product has been sent. It is waiting for Viaqui to approve it'),
      rejected: VQ.t('This product was rejected'),
      live: VQ.t('This product is approved and on the site'),
      hidden: VQ.t('This product is approved, but hidden from the site'),
    } : {
      draft: VQ.t('This venue is a draft: nobody sees it yet'),
      pending: VQ.t('This venue has been sent. It is waiting for Viaqui to approve it'),
      rejected: VQ.t('This venue was rejected'),
      live: VQ.t('This venue is approved and on the site'),
      hidden: VQ.t('This venue is approved, but hidden from the site'),
    };
    var map = {
      draft: ['is-draft', 'file-text', H.draft,
        VQ.t('Only you can see it. Fill in what is missing and press "Send for approval": the Viaqui team looks it over and, if all is well, it appears on the site.')],
      pending: ['is-wait', 'hourglass', H.pending,
        VQ.t('There is nothing more you need to do. We email you when it gets an answer, usually within one working day. Until then you can keep editing: we check the latest version.')],
      rejected: ['is-bad', 'warning-circle', H.rejected,
        VQ.t('Fix what is written below and send it again. Nothing you wrote is lost.')],
      approved: ['is-ok', 'check-circle', item.is_published ? H.live : H.hidden,
        item.is_published
          ? VQ.t('The changes you make from now on go straight to the site, with no new approval.')
          : VQ.t('Nobody sees it until you press "Put on the site". Changes go straight in, with no new approval.')],
    };
    var m = map[st] || map.approved;
    var body = [el('b', { text: m[2] }), el('p', { text: m[3] })];
    if (st === 'rejected' && item.rejection_reason) {
      body.push(el('p', { class: 'am-state-why' }, [el('b', { text: VQ.t('Reason:') + ' ' }), document.createTextNode(item.rejection_reason)]));
    }
    var stamp = st === 'pending' ? when(item.submitted_at) : (st === 'approved' || st === 'rejected' ? when(item.reviewed_at) : '');
    if (stamp) body.push(el('p', { class: 'am-state-when', text: st === 'pending' ? VQ.t('Sent on {date}', { date: stamp }) : VQ.t('Answered on {date}', { date: stamp }) }));
    if (st === 'draft' || st === 'pending' || st === 'rejected') {
      body.push(el('details', { class: 'am-state-more' }, [
        el('summary', { text: VQ.t('Why is approval needed?') }), el('p', { text: WHY }),
      ]));
    }
    return el('div', { class: 'am-state ' + m[0], role: st === 'rejected' ? 'alert' : 'status' }, [
      el('span', { class: 'am-state-ic' }, [O.icon(m[1])]), el('div', { class: 'am-state-t' }, body),
    ]);
  }

  window.BO_AM = {
    L: L, api: api, errText: errText, upload: upload, conv: conv, imgUrl: imgUrl, path: path, slug: slug, fold: fold, nid: nid,
    field: field, input: input, textarea: textarea, rich: rich, lines: lines, select: select, combo: combo,
    check: check, checkset: checkset, button: button, rows: rows, image: image, gallery: gallery, dropzone: dropzone,
    section: section, form: form, when: when,
    statusTags: statusTags, statusHint: statusHint, statusPanel: statusPanel,
  };
})();
