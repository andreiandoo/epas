/* bilete.online v2: operator profile. The contact dialog (message to the operator through the API proxy) and links
   that open a tab from elsewhere on the page. The tabs themselves are base.js. */
(function () {
  'use strict';
  var $ = function (id) { return document.getElementById(id); };
  var root = document.documentElement;
  var reduce = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
  var data = {};
  try { data = JSON.parse(($('v2-data') || {}).textContent || '{}'); } catch (e) {}

  /* ---------- [data-open-tab]: select a tab and bring the tab bar into view ---------- */
  document.querySelectorAll('[data-open-tab]').forEach(function (link) {
    link.addEventListener('click', function (e) {
      var tab = $(link.getAttribute('data-open-tab'));
      if (!tab) return;
      e.preventDefault();
      tab.click();
      $('op-tabs').scrollIntoView({ behavior: reduce ? 'auto' : 'smooth', block: 'start' });
      tab.focus({ preventScroll: true });
    });
  });

  /* ---------- contact dialog ---------- */
  var modal = $('op-contact'), form = $('op-form');
  if (!modal || !form) return;
  var msg = $('op-form-msg'), submit = $('op-submit'), opener = null, closeTimer = null, sending = false;
  var LABEL = submit.textContent;

  function open(btn) {
    opener = btn;
    clearTimeout(closeTimer);
    modal.hidden = false;
    root.classList.add('op-lock');
    form.querySelector('input').focus();
  }
  function close() {
    clearTimeout(closeTimer);
    modal.hidden = true;
    root.classList.remove('op-lock');
    if (opener) opener.focus();
  }
  function say(text, ok) {
    msg.textContent = text;
    msg.className = 'form-msg ' + (ok ? 'is-ok' : 'is-err');
    msg.hidden = false;
  }

  document.querySelectorAll('[data-contact]').forEach(function (btn) {
    btn.addEventListener('click', function () { open(btn); });
  });
  modal.addEventListener('click', function (e) {
    if (e.target === modal || e.target.closest('[data-close]')) close();
  });
  modal.addEventListener('keydown', function (e) {
    if (e.key === 'Escape') { e.preventDefault(); close(); return; }
    if (e.key !== 'Tab') return;
    var f = [].slice.call(modal.querySelectorAll('button, input, textarea, a[href]')).filter(function (el) { return !el.disabled && el.offsetParent !== null; });
    if (!f.length) return;
    if (e.shiftKey && document.activeElement === f[0]) { e.preventDefault(); f[f.length - 1].focus(); }
    else if (!e.shiftKey && document.activeElement === f[f.length - 1]) { e.preventDefault(); f[0].focus(); }
  });

  form.addEventListener('submit', function (e) {
    e.preventDefault();
    if (sending || !form.reportValidity()) return;
    if (typeof BileteOnlineAPI === 'undefined' || !data.slug) { say('A apărut o eroare. Încearcă din nou.', false); return; }
    var fields = new FormData(form), payload = {};
    ['first_name', 'last_name', 'email', 'phone', 'message'].forEach(function (k) { payload[k] = String(fields.get(k) || '').trim(); });
    sending = true;
    submit.disabled = true;
    submit.textContent = 'Se trimite...';
    msg.hidden = true;
    BileteOnlineAPI.post('/marketplace-events/organizers/' + encodeURIComponent(data.slug) + '/contact', payload)
      .then(function (res) {
        if (res && res.success === false) throw new Error(res.message || res.error || '');
        say('Mesajul a fost trimis cu succes! Organizatorul va reveni cu un răspuns.', true);
        form.reset();
        closeTimer = setTimeout(function () { close(); msg.hidden = true; }, 3000);
      })
      .catch(function (err) {
        say((err && err.message) || 'A apărut o eroare. Încearcă din nou.', false);
      })
      .then(function () {
        sending = false;
        submit.disabled = false;
        submit.textContent = LABEL;
      });
  });
})();
