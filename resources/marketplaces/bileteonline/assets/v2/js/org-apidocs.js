/* bilete.online v2: operator API documentation (/organizator/apidoc). Loads the API key (/organizer/api-key), copies it,
   regenerates it after a confirmation dialog (/organizer/api-key/regenerate), copies the code examples, saves the
   webhook URL (/organizer/webhook) and moves between sections from the contents (scroll, focus and the address hash).
   Runs inside the operator shell (window.BO_ORG); text from the API is always written as text. Markup in
   organizer/api-docs.php. */
(function () {
  'use strict';
  var O = window.BO_ORG, root = document.getElementById('oad');
  if (!O || !root) return;
  var $ = function (id) { return document.getElementById(id); };
  var apiKey = null;

  function busyBtn(btn, on, text) {
    var l = btn.querySelector('[data-label]') || btn;
    if (on) { btn.setAttribute('aria-busy', 'true'); btn.disabled = true; if (!btn.hasAttribute('data-idle')) btn.setAttribute('data-idle', l.textContent); l.textContent = text; }
    else { btn.removeAttribute('aria-busy'); btn.disabled = false; if (btn.hasAttribute('data-idle')) l.textContent = btn.getAttribute('data-idle'); btn.removeAttribute('data-idle'); }
  }
  function copyText(text) {
    if (navigator.clipboard && window.isSecureContext) return navigator.clipboard.writeText(text);
    return new Promise(function (resolve, reject) {
      var ta = document.createElement('textarea');
      ta.value = text;
      ta.setAttribute('readonly', '');
      ta.style.position = 'fixed';
      ta.style.opacity = '0';
      document.body.appendChild(ta);
      ta.select();
      var ok = false;
      try { ok = document.execCommand('copy'); } catch (e) {}
      document.body.removeChild(ta);
      if (ok) resolve(); else reject(new Error('copy'));
    });
  }
  function reduced() { return window.matchMedia('(prefers-reduced-motion: reduce)').matches; }

  /* ---------- API key ---------- */
  function showKey(k) {
    apiKey = typeof k === 'string' && k ? k : null;
    $('oad-key').textContent = apiKey || 'Nu există cheie API';
    $('oad-key').classList.toggle('is-empty', !apiKey);
    $('oad-key-copy').disabled = !apiKey;
  }
  function loadKey() {
    O.api('/organizer/api-key').then(function (r) {
      showKey(r && r.success && r.data ? r.data.api_key : null);
    }, function (err) {
      if (err && err.status === 401) return;
      $('oad-key').textContent = 'Eroare la încărcare';
      $('oad-key').classList.add('is-empty');
      $('oad-key-copy').disabled = true;
    });
  }
  $('oad-key-copy').addEventListener('click', function () {
    if (!apiKey) return;
    copyText(apiKey).then(function () { O.flash('Cheia API a fost copiată.'); }, function () { O.flash('Nu s-a putut copia cheia.', true); });
  });

  var dlg = $('oad-regen-d');
  function openDlg() {
    $('oad-regen-err').hidden = true;
    if (typeof dlg.showModal === 'function') dlg.showModal(); else dlg.setAttribute('open', '');
    dlg.querySelector('[data-close]').focus();
  }
  function closeDlg() {
    if (!dlg.open && !dlg.hasAttribute('open')) return;
    if (typeof dlg.close === 'function') dlg.close();
    else { dlg.removeAttribute('open'); dlg.dispatchEvent(new Event('close')); }
  }
  $('oad-regen').addEventListener('click', openDlg);
  dlg.addEventListener('click', function (e) { if (!dlg.hasAttribute('data-busy') && e.target.closest('[data-close]')) closeDlg(); });
  dlg.addEventListener('cancel', function (e) { if (dlg.hasAttribute('data-busy')) e.preventDefault(); });
  dlg.addEventListener('close', function () { if (document.activeElement === document.body) $('oad-regen').focus(); });
  $('oad-regen-go').addEventListener('click', function () {
    var btn = this;
    if (btn.getAttribute('aria-busy') === 'true') return;
    busyBtn(btn, true, 'Se regenerează…');
    dlg.setAttribute('data-busy', '');
    var done = function () { dlg.removeAttribute('data-busy'); busyBtn(btn, false); };
    O.api('/organizer/api-key/regenerate', { method: 'POST', body: {} }).then(function (r) {
      done();
      if (r && r.success && r.data && r.data.api_key) {
        closeDlg();
        showKey(r.data.api_key);
        O.flash('Cheia API a fost regenerată.');
        return;
      }
      var e = $('oad-regen-err');
      e.textContent = (r && typeof r.message === 'string' && r.message) || 'Eroare la regenerarea cheii.';
      e.hidden = false;
    }, function (err) {
      done();
      if (err && err.status === 401) return;
      var e = $('oad-regen-err');
      e.textContent = 'Eroare la regenerarea cheii API.';
      e.hidden = false;
    });
  });

  /* ---------- code examples ---------- */
  [].forEach.call(root.querySelectorAll('[data-copy]'), function (btn) {
    btn.addEventListener('click', function () {
      var pre = btn.closest('.oad-code').querySelector('pre'), l = btn.querySelector('[data-label]');
      copyText(pre.textContent).then(function () {
        l.textContent = 'Copiat!';
        O.flash('Codul a fost copiat.');
        setTimeout(function () { l.textContent = 'Copiază'; }, 2000);
      }, function () { O.flash('Nu s-a putut copia codul.', true); });
    });
  });

  /* ---------- webhook ---------- */
  function hookMsg(text, bad) {
    var m = $('oad-hook-msg');
    m.textContent = text || '';
    m.classList.toggle('is-bad', !!bad);
    if (bad) $('oad-hook-url').setAttribute('aria-invalid', 'true'); else $('oad-hook-url').removeAttribute('aria-invalid');
  }
  $('oad-hook-url').addEventListener('input', function () { if ($('oad-hook-msg').classList.contains('is-bad')) hookMsg(''); });
  $('oad-hook').addEventListener('submit', function (e) {
    e.preventDefault();
    var btn = $('oad-hook-go'), url = $('oad-hook-url').value.trim();
    if (btn.getAttribute('aria-busy') === 'true') return;
    if (!url) { hookMsg('Introdu URL-ul webhook.', true); $('oad-hook-url').focus(); return; }
    hookMsg('');
    busyBtn(btn, true, 'Se salvează…');
    O.api('/organizer/webhook', { method: 'POST', body: { url: url } }).then(function (r) {
      busyBtn(btn, false);
      if (r && r.success) { hookMsg('URL-ul webhook a fost salvat.'); O.flash('URL-ul webhook a fost salvat.'); }
      else hookMsg((r && typeof r.message === 'string' && r.message) || 'Eroare la salvarea webhook-ului.', true);
    }, function (err) {
      busyBtn(btn, false);
      if (err && err.status === 401) return;
      hookMsg('Eroare la salvarea webhook-ului.', true);
    });
  });

  /* ---------- contents ---------- */
  [].forEach.call(root.querySelectorAll('.oad-toc a[href^="#"]'), function (a) {
    a.addEventListener('click', function (e) {
      var t = document.getElementById(a.getAttribute('href').slice(1));
      if (!t) return;
      e.preventDefault();
      t.scrollIntoView({ behavior: reduced() ? 'auto' : 'smooth', block: 'start' });
      t.focus({ preventScroll: true });
      try { history.replaceState(null, '', '#' + t.id); } catch (err) {}
    });
  });

  O.ready.then(function (ok) { if (ok) loadKey(); });
})();
