/* bilete.online v2: the operator's booking widgets (/organizator/widget-uri). Whether widgets are on and the allowed
   sites come from /organizer/me (settings.widget_enabled, settings.embed_domains); the list is saved whole through
   /organizer/widget-settings (core merges it into the organizer's settings). Locations and products from
   /organizer/activities-module/*; the code points at /embed/locatie/{slug}[?produs=id] (embed/locatie.php) and carries a
   line that sets the frame's height from its {type: 'bo-embed-height'} messages. Uses window.BO_AM inside the
   organizer shell; nothing from the API is written as HTML. */
(function () {
  'use strict';
  var O = window.BO_ORG, A = window.BO_AM, root = document.getElementById('am-wg');
  if (!O || !A || !root) return;
  var el = O.el, F = O.fmt;
  var $ = function (id) { return document.getElementById(id); };
  var data = {};
  try { data = JSON.parse(($('v2-data') || {}).textContent || '{}'); } catch (e) {}
  var SITE = (data.widgets && data.widgets.site) || location.origin;
  var DOMAIN = /^(https?:\/\/)?(\*\.)?([a-z0-9-]+\.)+[a-z]{2,}(:\d+)?$/i;
  var domains = [], locations = [], products = [];

  function isPublic(x) { return !!(x && x.is_published && (!x.review_status || x.review_status === 'approved')); }
  function save(list) { return O.api('/organizer/widget-settings', { method: 'PUT', body: { settings: { embed_domains: list } } }); }

  /* =================== sites =================== */
  function drawDomains() {
    var ul = $('wg-domains');
    ul.textContent = '';
    if (!domains.length) {
      ul.appendChild(el('li', { class: 'wg-dom-none', text: 'Niciun site încă. Până adaugi unul, widget-ul se vede doar în previzualizarea de mai jos.' }));
      return;
    }
    domains.forEach(function (d, i) {
      var rm = el('button', { class: 've-icon-btn', type: 'button', 'aria-label': 'Șterge ' + d }, [O.icon('trash')]);
      rm.addEventListener('click', function () { update(domains.filter(function (_, j) { return j !== i; }), rm, 'Site-ul a fost scos din listă.'); });
      ul.appendChild(el('li', { class: 'wg-dom' }, [O.icon('globe-simple'), el('span', { text: d }), rm]));
    });
  }
  function update(list, btn, ok) {
    if (btn.disabled) return Promise.resolve(false);
    btn.disabled = true;
    return save(list).then(function () {
      domains = list;
      drawDomains();
      O.flash(ok);
      return true;
    }, function (e) {
      if (!(e && e.status === 401)) O.flash('Nu am putut salva lista de site-uri. Încearcă din nou.', true);
      return false;
    }).then(function (r) { btn.disabled = false; return r; });
  }
  function clean(v) {
    v = String(v || '').trim().toLowerCase().replace(/\/+$/, '');
    // a pasted page address keeps only its site
    var m = /^(https?:\/\/)?([^\/?#]+)/.exec(v);
    if (!m) return '';
    return (m[1] === 'http://' ? 'http://' : '') + m[2];
  }
  $('wg-dom-form').addEventListener('submit', function (e) {
    e.preventDefault();
    var input = $('wg-dom-in'), err = $('wg-dom-err'), d = clean(input.value);
    var bare = function (x) { return x.replace(/^https?:\/\//, '').replace(/^www\./, ''); };
    var msg = !d ? 'Scrie adresa site-ului.'
      : !DOMAIN.test(d) ? 'Scrie o adresă validă, de exemplu site-meu.ro sau *.site-meu.ro.'
      : domains.some(function (x) { return bare(x) === bare(d); }) ? 'Site-ul e deja în listă.' : '';
    err.textContent = msg;
    err.hidden = !msg;
    if (msg) { input.setAttribute('aria-invalid', 'true'); input.focus(); return; }
    input.removeAttribute('aria-invalid');
    update(domains.concat([d]), $('wg-dom-go'), 'Site-ul a fost adăugat.').then(function (ok) { if (ok) input.value = ''; });
  });
  $('wg-dom-in').addEventListener('input', function () { $('wg-dom-err').hidden = true; this.removeAttribute('aria-invalid'); });

  /* =================== location, product, code =================== */
  function loc() { var id = Number($('wg-loc').value); return locations.filter(function (l) { return l.id === id; })[0] || null; }
  function prod() { var id = Number($('wg-prod').value); return id ? products.filter(function (p) { return p.id === id; })[0] || null : null; }
  function fillProducts() {
    var l = loc(), sel = $('wg-prod'), keep = sel.value;
    sel.textContent = '';
    sel.appendChild(el('option', { value: '', text: 'Toate biletele locației' }));
    products.filter(function (p) { return l && p.location_id === l.id && isPublic(p); }).forEach(function (p) {
      sel.appendChild(el('option', { value: String(p.id), text: F.flat(p.title) || ('Produsul ' + p.id) }));
    });
    sel.value = keep;
    if (sel.value !== keep) sel.value = '';
  }
  function draw() {
    var l = loc(), p = prod(), note = $('wg-loc-note');
    var ready = isPublic(l);
    note.hidden = !l || ready;
    note.textContent = l && !ready ? 'Locația nu e încă pe site (aprobată și publicată). Codul e gata de pus, dar widget-ul arată biletele doar după ce locația apare pe bilete.online.' : '';
    if (!l) return;
    var src = SITE + '/embed/locatie/' + encodeURIComponent(l.slug) + (p ? '?produs=' + p.id : '');
    var name = F.flat(p ? p.title : l.name) || 'bilete.online';
    $('wg-code').value =
      '<iframe src="' + src + '" title="Bilete ' + attr(name) + '" loading="lazy" style="display:block;width:100%;height:720px;border:0"></iframe>\n' +
      '<script>window.addEventListener("message",function(e){if(e.origin!=="' + SITE + '"||!e.data||e.data.type!=="bo-embed-height")return;' +
      'document.querySelectorAll("iframe").forEach(function(f){if(f.contentWindow===e.source)f.style.height=e.data.height+"px";});});</' + 'script>';
    var page = SITE + ((p && p.public_path) || l.public_path || ('/locatie/' + l.slug)) + '#bilete';
    $('wg-link').value = '<a href="' + page + '" target="_blank" rel="noopener" style="display:inline-block;padding:12px 24px;border-radius:999px;background:#1E5B48;color:#fff;font:600 16px/1.2 system-ui,sans-serif;text-decoration:none">Cumpără bilete</a>';
    var fr = $('wg-preview');
    $('wg-prev-box').hidden = !ready;
    var local = '/embed/locatie/' + encodeURIComponent(l.slug) + (p ? '?produs=' + p.id : '');
    if (ready && fr.getAttribute('src') !== local) { fr.style.height = '720px'; fr.setAttribute('src', local); }
  }
  function attr(s) { return String(s).replace(/&/g, '&amp;').replace(/"/g, '&quot;').replace(/</g, '&lt;'); }
  $('wg-loc').addEventListener('change', function () { $('wg-prod').value = ''; fillProducts(); draw(); });
  $('wg-prod').addEventListener('change', draw);
  window.addEventListener('message', function (e) {
    var fr = $('wg-preview');
    if (e.origin !== location.origin || !e.data || e.data.type !== 'bo-embed-height' || e.source !== fr.contentWindow) return;
    fr.style.height = Math.max(200, Number(e.data.height) || 0) + 'px';
  });

  root.querySelectorAll('[data-copy]').forEach(function (b) {
    b.addEventListener('click', function () {
      var ta = $(b.getAttribute('data-copy'));
      var done = function () { O.flash('Codul a fost copiat.'); };
      var fallback = function () {
        ta.focus();
        ta.select();
        try { if (document.execCommand('copy')) { done(); return; } } catch (e) {}
        O.flash('Nu am putut copia. Selectează codul și copiază-l manual.', true);
      };
      if (navigator.clipboard && navigator.clipboard.writeText) navigator.clipboard.writeText(ta.value).then(done, fallback);
      else fallback();
    });
  });

  /* =================== start =================== */
  O.ready.then(function (ok) {
    if (!ok) return;
    O.api('/organizer/me').then(function (r) {
      var org = (r && r.data && (r.data.organizer || r.data)) || {};
      var s = org.settings || {};
      $('wg-loading').hidden = true;
      if (!s.widget_enabled) { $('wg-off').hidden = false; return; }
      domains = (Array.isArray(s.embed_domains) ? s.embed_domains : []).map(F.flat).filter(Boolean);
      drawDomains();
      $('wg-on').hidden = false;
      return Promise.all([A.api('/locations'), A.api('/products')]).then(function (res) {
        locations = ((res[0] && res[0].data && res[0].data.locations) || []).filter(function (l) { return l && l.id != null && l.slug; });
        products = ((res[1] && res[1].data && res[1].data.products) || []).filter(function (p) { return p && p.id != null; });
        var sel = $('wg-loc');
        sel.textContent = '';
        if (!locations.length) {
          sel.appendChild(el('option', { value: '', text: 'Nicio locație încă' }));
          sel.disabled = $('wg-prod').disabled = true;
          $('wg-code-box').hidden = $('wg-prev-box').hidden = true;
          var note = $('wg-loc-note');
          note.textContent = 'Adaugă întâi o locație în „Locațiile mele”; widget-ul vinde biletele ei.';
          note.hidden = false;
          return;
        }
        // the ones already on the site first
        locations.sort(function (a, b) { return (isPublic(b) ? 1 : 0) - (isPublic(a) ? 1 : 0); });
        locations.forEach(function (l) { sel.appendChild(el('option', { value: String(l.id), text: (F.flat(l.name) || ('Locația ' + l.id)) + (isPublic(l) ? '' : ' (nu e încă pe site)') })); });
        fillProducts();
        draw();
      });
    }).catch(function (e) {
      if (e && e.status === 401) return;
      $('wg-loading').hidden = false;
      $('wg-loading').textContent = A.errText(e, 'Nu am putut încărca widget-urile. Reîncarcă pagina.');
    });
  });
})();
