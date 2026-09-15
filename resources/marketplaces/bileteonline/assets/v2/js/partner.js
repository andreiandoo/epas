/* bilete.online v2: become a partner. Replaces the page's Alpine code:
   - personalisation from ?tip=<type>&loc=<name>: profile copy in the hero, and every signup link carries tip/loc on to
     /inregistrare-locatie (the location name is shown as text, never as markup);
   - count-up numbers, the booking demo and the ANAF flow, which run only while in view and stand still under reduced
     motion (the page already shows their final state without JavaScript);
   - funnel tracking on the bo_lead_sid session: one page_view_landing ping and a cta_click ping for [data-track-cta]. */
(function () {
  'use strict';
  var $ = function (id) { return document.getElementById(id); };
  if (!$('dp-h')) return;

  var data = {};
  try { data = JSON.parse($('v2-data').textContent) || {}; } catch (e) {}
  var PROFILES = data.profiles || {}, ALIASES = data.aliases || {};
  var reduce = !!(window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches);
  var canObserve = 'IntersectionObserver' in window;
  function sleep(ms) { return new Promise(function (resolve) { setTimeout(resolve, ms); }); }

  // ---------- personalisation ----------
  var params = null;
  try { params = new URLSearchParams(window.location.search); } catch (e) {}
  var rawTip = params ? String(params.get('tip') || '').trim().toLowerCase().slice(0, 80) : '';
  var loc = params ? String(params.get('loc') || '').trim().slice(0, 200) : '';
  var typeKey = ALIASES[rawTip] || '';
  var profile = typeKey ? PROFILES[typeKey] : null;

  if (loc) {
    var hello = $('dp-hello'), name = document.createElement('strong');
    name.textContent = loc;
    hello.textContent = 'Salut, ';
    hello.appendChild(name);
    hello.appendChild(document.createTextNode('! Iată ce putem face împreună.'));
    hello.hidden = false;
  }
  if (profile) {
    $('dp-chip-t').textContent = 'Ticketing & booking pentru ' + profile.label;
    $('dp-h1a').textContent = profile.h1a;
    $('dp-h1b').textContent = profile.h1b;
    $('dp-h1c').textContent = profile.h1c;
    $('dp-sub').innerHTML = profile.sub; // the page's own copy (from the server), not anything from the URL
    $('dp-cta-t').textContent = 'Pune ' + profile.label + ' online';
  }
  var carry = [];
  if (typeKey) carry.push('tip=' + encodeURIComponent(typeKey));
  if (loc) carry.push('loc=' + encodeURIComponent(loc));
  if (carry.length) {
    [].forEach.call(document.querySelectorAll('a[data-signup]'), function (a) {
      a.setAttribute('href', a.getAttribute('href').split('?')[0] + '?' + carry.join('&'));
    });
  }

  // ---------- count-up numbers ----------
  var counters = [].slice.call(document.querySelectorAll('[data-count]'));
  if (!reduce && canObserve && counters.length) {
    var fmt = new Intl.NumberFormat('ro-RO');
    var countIO = new IntersectionObserver(function (entries) {
      entries.forEach(function (en) {
        if (!en.isIntersecting) return;
        countIO.unobserve(en.target);
        var el = en.target, target = Number(el.getAttribute('data-count')) || 0, start = null;
        var frame = function (t) {
          if (start === null) start = t;
          var p = Math.min(1, (t - start) / 1400);
          el.textContent = fmt.format(Math.round(target * (1 - Math.pow(1 - p, 3))));
          if (p < 1) requestAnimationFrame(frame);
        };
        requestAnimationFrame(frame);
      });
    }, { threshold: 0.4 });
    counters.forEach(function (el) {
      if (el.getBoundingClientRect().top < window.innerHeight) return; // already on screen: keep the real number
      el.textContent = '0';
      countIO.observe(el);
    });
  }

  // ---------- loops that run while in view ----------
  function whileVisible(el, cycle) {
    if (!el || reduce || !canObserve) return;
    var visible = false, running = false;
    new IntersectionObserver(function (entries) {
      visible = entries[0].isIntersecting;
      if (!visible || running) return;
      running = true;
      (async function () {
        while (visible) await cycle();
        running = false;
      })();
    }, { threshold: 0.25 }).observe(el);
  }

  // booking demo
  var demo = $('dp-demo');
  if (demo) {
    var TITLES = ['Alege ora', 'Tipul de bilet', 'Extra & rentals', 'Personalizează', 'Plată', 'Gata!'];
    var steps = [].slice.call(demo.querySelectorAll('.dp-step'));
    var slots = [].slice.call(demo.querySelectorAll('.dp-slot')), tickets = [].slice.call(demo.querySelectorAll('.dp-tk'));
    var extras = [].slice.call(demo.querySelectorAll('.dp-extra')), msgs = [].slice.call(demo.querySelectorAll('.dp-msg'));
    var typed = $('dp-typed'), gift = $('dp-gift'), pay = $('dp-pay-progress'), payText = $('dp-pay-t');
    var NAME = data.demoName || 'Andrei Popescu', GIFT = data.demoGift || '';
    var show = function (n) {
      steps.forEach(function (s) { s.hidden = Number(s.getAttribute('data-step')) !== n; });
      $('dp-demo-title').textContent = TITLES[n];
      $('dp-demo-count').textContent = (n + 1) + '/' + TITLES.length;
      $('dp-demo-progress').style.width = ((n + 1) / TITLES.length * 100) + '%';
    };
    var only = function (list, i) { list.forEach(function (el, j) { el.classList.toggle('is-on', j === i); }); };
    var bookingCycle = async function () {
      only(slots, -1); only(tickets, -1); only(extras, -1); only(msgs, -1);
      typed.textContent = ''; gift.textContent = ''; pay.style.width = '0%'; payText.textContent = 'Se procesează plata…';
      show(0); await sleep(900); only(slots, 2); await sleep(1100);
      show(1); await sleep(900); only(tickets, 1); await sleep(1200);
      show(2); await sleep(800); extras[0].classList.add('is-on'); await sleep(750); extras[1].classList.add('is-on'); await sleep(1100);
      show(3); await sleep(500);
      for (var c = 0; c < NAME.length; c++) { typed.textContent += NAME.charAt(c); await sleep(70); }
      await sleep(400); gift.textContent = GIFT; await sleep(1300);
      show(4); await sleep(700);
      for (var w = 0; w <= 100; w += 8) { pay.style.width = w + '%'; await sleep(90); }
      pay.style.width = '100%'; payText.textContent = 'Plată confirmată'; await sleep(800);
      show(5); await sleep(600);
      for (var m = 0; m < msgs.length; m++) { msgs[m].classList.add('is-on'); await sleep(650); }
      await sleep(2600);
    };
    whileVisible(demo, bookingCycle);
  }

  // ANAF flow
  var docs = [].slice.call(document.querySelectorAll('.dp-doc'));
  if (docs.length) {
    var elapsed = $('dp-elapsed');
    whileVisible($('dp-anaf'), async function () {
      docs.forEach(function (d) { d.classList.remove('is-done', 'is-busy'); });
      elapsed.textContent = '0s';
      await sleep(900);
      for (var i = 0; i < docs.length; i++) {
        docs[i].classList.add('is-busy'); await sleep(1100);
        docs[i].classList.remove('is-busy'); docs[i].classList.add('is-done');
        elapsed.textContent = Math.min(3, i + 1) + 's'; await sleep(450);
      }
      await sleep(2600);
    });
  }

  // ---------- funnel tracking ----------
  function sessionToken() {
    var m = document.cookie.match(/(?:^|;\s*)bo_lead_sid=([^;]+)/);
    if (m) return decodeURIComponent(m[1]);
    var sid = (window.crypto && crypto.randomUUID) ? crypto.randomUUID() : Date.now().toString(36) + Math.random().toString(36).slice(2);
    var year = new Date();
    year.setFullYear(year.getFullYear() + 1);
    document.cookie = 'bo_lead_sid=' + sid + '; expires=' + year.toUTCString() + '; path=/; SameSite=Lax';
    return sid;
  }
  function track(type, extra) {
    var body = {
      session_token: sessionToken(),
      event_type: type,
      page_url: (window.location.pathname + window.location.search).slice(0, 500),
      referrer: document.referrer ? document.referrer.slice(0, 1000) : null,
      prefill_tip: rawTip || null,
      prefill_loc: loc || null,
      utm: (window.BO_UTM && typeof window.BO_UTM === 'object') ? window.BO_UTM : {}
    };
    Object.keys(extra || {}).forEach(function (k) { body[k] = extra[k]; });
    try {
      fetch('/api/proxy.php?action=leads.track', { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify(body), keepalive: true }).catch(function () {});
    } catch (e) {}
  }
  track('page_view_landing');
  document.addEventListener('click', function (e) {
    var el = e.target.closest ? e.target.closest('[data-track-cta]') : null;
    if (!el) return;
    track('cta_click', {
      cta_id: String(el.getAttribute('data-track-cta')).slice(0, 80),
      cta_label: (el.innerText || el.textContent || '').replace(/\s+/g, ' ').trim().slice(0, 200)
    });
  }, true);
})();
