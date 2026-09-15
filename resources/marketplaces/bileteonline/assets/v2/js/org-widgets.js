/* bilete.online v2: organizer widgets (/organizator/widget-uri). Settings from /organizer/me (widget_enabled,
   embed_domains, widget_config, widget_terms, widget_privacy), saved through /organizer/widget-settings; images uploaded
   through organizer.widget-image (multipart, token in a header); embed codes for one activity or the list with a live
   preview from /embed/tixello-widget.js; the whitelabel ZIP from /embed/generate-package.php. Runs inside the organizer
   shell (window.BO_ORG); nothing from the API is written as HTML. */
(function () {
  'use strict';
  var O = window.BO_ORG, root = document.getElementById('ow');
  if (!O || !root) return;
  var F = O.fmt, el = O.el;
  var $ = function (id) { return document.getElementById(id); };
  var SITE = (window.BO_WIDGETS && window.BO_WIDGETS.siteUrl) || location.origin;
  var HEX = /^#[0-9a-f]{6}$/i, MAX_IMG = 5 * 1024 * 1024, FIELD = { logo: 'logo', hero: 'hero_image', background: 'bg_image' };
  var org = null, slug = '', images = { logo: '', hero: '', background: '' };

  function val(id) { return String($(id).value || '').trim(); }
  function busyBtn(btn, on, text) {
    var l = btn.querySelector('[data-label]');
    if (on) { btn.setAttribute('aria-busy', 'true'); if (l) { btn.setAttribute('data-idle', l.textContent); l.textContent = text; } }
    else { btn.removeAttribute('aria-busy'); if (l && btn.hasAttribute('data-idle')) l.textContent = btn.getAttribute('data-idle'); btn.removeAttribute('data-idle'); }
  }
  function tabs(ids, prefixTab, prefixPanel) {
    ids.forEach(function (k, i) {
      var t = $(prefixTab + k);
      function pick(focus) {
        ids.forEach(function (x) { var on = x === k; $(prefixTab + x).setAttribute('aria-selected', String(on)); $(prefixTab + x).tabIndex = on ? 0 : -1; $(prefixPanel + x).hidden = !on; });
        if (focus) t.focus();
      }
      t.addEventListener('click', function () { pick(false); });
      t.addEventListener('keydown', function (e) {
        if (e.key !== 'ArrowRight' && e.key !== 'ArrowLeft') return;
        e.preventDefault();
        $(prefixTab + ids[(i + (e.key === 'ArrowRight' ? 1 : ids.length - 1)) % ids.length]).click();
        $(prefixTab + ids[(i + (e.key === 'ArrowRight' ? 1 : ids.length - 1)) % ids.length]).focus();
      });
    });
  }
  tabs(['branding', 'terms', 'privacy'], 'ow-it-', 'ow-ip-');
  tabs(['single', 'list'], 'ow-t-', 'ow-p-');

  function save(settings) { return O.api('/organizer/widget-settings', { method: 'PUT', body: { settings: settings } }); }

  /* =================== LOAD =================== */
  O.ready.then(function (ok) {
    if (!ok) return;
    O.api('/organizer/me').then(function (r) {
      org = (r && r.data && (r.data.organizer || r.data)) || {};
      slug = F.flat(org.slug);
      var s = org.settings || {};
      $('ow-loading').hidden = true;
      if (!s.widget_enabled) { $('ow-disabled').hidden = false; return; }
      ['ow-domain', 'ow-wl', 'ow-embed'].forEach(function (id) { $(id).hidden = false; });
      var domain = Array.isArray(s.embed_domains) && s.embed_domains[0] ? F.flat(s.embed_domains[0]) : '';
      if (domain) { $('ow-domain-in').value = domain; showDomain(domain); }
      var wc = s.widget_config || {};
      [['ow-title', wc.home_title], ['ow-subtitle', wc.home_subtitle], ['ow-address', wc.address], ['ow-phone', wc.phone], ['ow-terms', s.widget_terms], ['ow-privacy', s.widget_privacy]].forEach(function (x) { $(x[0]).value = F.flat(x[1]); });
      if (HEX.test(F.flat(wc.accent))) { $('ow-accent').value = wc.accent; $('ow-accent-hex').value = wc.accent.toUpperCase(); }
      images = { logo: F.flat(wc.logo || org.logo), hero: F.flat(wc.hero_image), background: F.flat(wc.bg_image) };
      Object.keys(images).forEach(function (k) { if (images[k]) preview(k, images[k]); });
      $('ow-package').href = SITE + '/embed/generate-package.php?organizer=' + encodeURIComponent(slug);
      loadEvents();
      code('single');
      code('list');
    }, function (err) {
      if (err && err.status === 401) return;
      $('ow-loading').hidden = true;
      O.flash('Nu am putut încărca setările widget-urilor. Reîncarcă pagina.', true);
    });
  });

  /* =================== DOMAIN =================== */
  function showDomain(d) {
    $('ow-domain-now').textContent = 'Domeniu configurat: ' + d;
    $('ow-domain-now').hidden = false;
    var host = d.replace(/^https?:\/\//i, '').replace(/^\*\./, 'www.').replace(/\/.*$/, '');
    $('ow-return').value = (/^http:\/\//i.test(d) ? 'http://' : 'https://') + host + '/multumim';
  }
  $('ow-domain-form').addEventListener('submit', function (e) {
    e.preventDefault();
    var btn = $('ow-domain-go'), d = val('ow-domain-in'), input = $('ow-domain-in'), err = $('ow-domain-err');
    var okFormat = /^(https?:\/\/)?(\*\.)?([a-z0-9-]+\.)+[a-z]{2,}(:\d+)?\/?$/i.test(d);
    err.hidden = okFormat;
    err.textContent = okFormat ? '' : d ? 'Scrie un domeniu valid, de exemplu https://site-meu.ro sau *.site-meu.ro.' : 'Scrie domeniul site-ului.';
    if (!okFormat) { input.setAttribute('aria-invalid', 'true'); input.focus(); return; }
    input.removeAttribute('aria-invalid');
    if (btn.getAttribute('aria-busy') === 'true') return;
    d = d.replace(/\/$/, '');
    busyBtn(btn, true, 'Se salvează…');
    save({ embed_domains: [d] }).then(function () {
      showDomain(d);
      O.flash('Domeniul a fost salvat.');
    }, function (er) { if (!(er && er.status === 401)) O.flash('Nu am putut salva domeniul. Încearcă din nou.', true); }).then(function () { busyBtn(btn, false); });
  });

  /* =================== BRANDING =================== */
  $('ow-accent').addEventListener('input', function () { $('ow-accent-hex').value = this.value.toUpperCase(); $('ow-accent-hex').removeAttribute('aria-invalid'); });
  $('ow-accent-hex').addEventListener('input', function () {
    var v = this.value.trim();
    if (v && v[0] !== '#') v = '#' + v;
    if (HEX.test(v)) { $('ow-accent').value = v; this.removeAttribute('aria-invalid'); } else this.setAttribute('aria-invalid', 'true');
  });
  function preview(type, src) {
    var box = $('ow-prev-' + type), zone = box.parentNode;
    box.textContent = '';
    box.appendChild(el('img', { src: src, alt: '' }));
    box.hidden = false;
    zone.classList.add('has-image');
  }
  Object.keys(FIELD).forEach(function (type) {
    var input = $('ow-file-' + type), msg = $('ow-up-' + type + '-msg');
    root.querySelector('[data-pick="' + type + '"]').addEventListener('click', function () { input.click(); });
    input.addEventListener('change', function () {
      var file = input.files && input.files[0];
      input.value = '';
      if (!file) return;
      if (!/^image\/(png|jpeg|webp|svg\+xml)$/.test(file.type)) { msg.textContent = 'Alege o imagine PNG, JPG, WebP sau SVG.'; return; }
      if (file.size > MAX_IMG) { msg.textContent = 'Imaginea are peste 5MB. Alege una mai mică.'; return; }
      var token = typeof BileteOnlineAuth !== 'undefined' && BileteOnlineAuth.getToken ? BileteOnlineAuth.getToken() : null;
      if (!token) { O.flash('Sesiunea a expirat. Autentifică-te din nou.', true); return; }
      var fd = new FormData();
      fd.append('image', file);
      fd.append('type', type);
      msg.textContent = 'Se încarcă…';
      fetch(((window.BILETEONLINE && window.BILETEONLINE.apiUrl) || '/api/proxy.php') + '?action=organizer.widget-image', { method: 'POST', body: fd, headers: { Authorization: 'Bearer ' + token, Accept: 'application/json' } }).then(function (res) {
        return res.json().catch(function () { return {}; }).then(function (j) { if (!res.ok || !j || !j.success || !j.data || !j.data.url) { var e = new Error('upload'); e.status = res.status; throw e; } return j.data.url; });
      }).then(function (url) {
        images[type] = String(url);
        preview(type, images[type]);
        msg.textContent = 'Imaginea a fost încărcată. Salvează setările ca s-o folosești în pachet.';
      }, function (e) {
        msg.textContent = e && e.status === 422 ? 'Imaginea nu a fost acceptată. Alege PNG, JPG, WebP sau SVG sub 5MB.' : 'Nu am putut încărca imaginea. Încearcă din nou.';
      });
    });
  });
  $('ow-save').addEventListener('click', function () {
    var btn = this, accent = $('ow-accent-hex').value.trim();
    if (!HEX.test(accent)) { $('ow-it-branding').click(); $('ow-accent-hex').setAttribute('aria-invalid', 'true'); $('ow-accent-hex').focus(); O.flash('Culoarea principală trebuie să fie un cod de forma #D4A843.', true); return; }
    if (btn.getAttribute('aria-busy') === 'true') return;
    busyBtn(btn, true, 'Se salvează…');
    save({
      widget_config: { logo: images.logo, bg_image: images.background, hero_image: images.hero, home_title: val('ow-title'), home_subtitle: val('ow-subtitle'), address: val('ow-address'), phone: val('ow-phone'), accent: accent.toUpperCase(), return_url: val('ow-return') },
      widget_terms: val('ow-terms'),
      widget_privacy: val('ow-privacy'),
    }).then(function () { O.flash('Setările au fost salvate.'); }, function (er) { if (!(er && er.status === 401)) O.flash('Nu am putut salva setările. Încearcă din nou.', true); }).then(function () { busyBtn(btn, false); });
  });

  /* =================== EMBED CODES =================== */
  function loadEvents() {
    O.api('/organizer/events?status=published&per_page=50', { quiet: true }).then(function (r) {
      var list = Array.isArray(r && r.data) ? r.data : [], sel = $('ow-s-event');
      list.forEach(function (e) { if (e && e.slug) sel.appendChild(el('option', { value: F.flat(e.slug), text: F.flat(e.name || e.title) || 'Activitate' })); });
    }, function () {});
  }
  function attr(name, value) { return '\n  data-' + name + '="' + String(value).replace(/["<>&]/g, '') + '"'; }
  function code(type) {
    var box = $(type === 'single' ? 'ow-s-preview' : 'ow-l-preview'), out = $(type === 'single' ? 'ow-s-code' : 'ow-l-code'), attrs;
    if (type === 'single') {
      var ev = $('ow-s-event').value, style = $('ow-s-style').value;
      if (!ev) { out.value = '<!-- Selectează o activitate -->'; box.textContent = ''; box.appendChild(el('p', { text: 'Selectează o activitate din listă.' })); return; }
      attrs = attr('type', 'single') + attr('event', ev) + attr('organizer', slug) + attr('theme', $('ow-s-theme').value) + (style !== 'card' ? attr('style', style) : '');
      out.value = '<div id="tixello-event"></div>\n<script src="' + SITE + '/embed/tixello-widget.js"' + attrs + '>\n</' + 'script>';
      load(box, 'ow-s-live', { type: 'single', event: ev, organizer: slug, theme: $('ow-s-theme').value });
    } else {
      var n = Math.max(1, Math.min(20, parseInt($('ow-l-limit').value, 10) || 6)), layout = $('ow-l-layout').value;
      $('ow-l-limit').value = n;
      attrs = attr('type', 'list') + attr('organizer', slug) + attr('limit', n) + attr('theme', $('ow-l-theme').value) + (layout !== 'grid' ? attr('layout', layout) : '');
      out.value = '<div id="tixello-events"></div>\n<script src="' + SITE + '/embed/tixello-widget.js"' + attrs + '>\n</' + 'script>';
      load(box, 'ow-l-live', { type: 'list', organizer: slug, theme: $('ow-l-theme').value, limit: n });
    }
  }
  function load(box, id, data) {
    box.textContent = '';
    box.appendChild(el('div', { id: id }));
    var old = document.getElementById('ow-preview-script');
    if (old) old.remove();
    var styles = document.getElementById('txw-styles');
    if (styles) styles.remove();
    var s = document.createElement('script');
    s.id = 'ow-preview-script';
    s.src = SITE + '/embed/tixello-widget.js?_t=' + Date.now();
    s.setAttribute('data-container', id);
    Object.keys(data).forEach(function (k) { if (data[k] !== '' && data[k] != null) s.setAttribute('data-' + k, String(data[k])); });
    s.onerror = function () { box.textContent = ''; box.appendChild(el('p', { text: 'Previzualizarea nu s-a putut încărca.' })); };
    document.body.appendChild(s);
  }
  ['ow-s-event', 'ow-s-theme', 'ow-s-style'].forEach(function (id) { $(id).addEventListener('change', function () { code('single'); }); });
  ['ow-l-limit', 'ow-l-theme', 'ow-l-layout'].forEach(function (id) { $(id).addEventListener('change', function () { code('list'); }); });
  root.querySelectorAll('[data-copy]').forEach(function (b) {
    b.addEventListener('click', function () {
      var ta = $(b.getAttribute('data-copy'));
      var done = function () { O.flash('Codul a fost copiat.'); }, failed = function () { ta.focus(); ta.select(); O.flash('Nu am putut copia. Codul e selectat: apasă Ctrl+C.', true); };
      if (navigator.clipboard && window.isSecureContext) navigator.clipboard.writeText(ta.value).then(done, failed);
      else { ta.select(); try { if (document.execCommand('copy')) done(); else failed(); } catch (e) { failed(); } }
    });
  });
})();
