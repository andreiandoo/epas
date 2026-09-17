/* bilete.online v2: /parteneri, "product theatre".
   - personalisation from ?tip=<type>&loc=<name> (same profiles as /devino-partener): greeting, chip, headline, lead,
     signup button; signup links carry tip/loc on to /inregistrare-locatie
   - funnel pings to leads.track (page_view_landing, cta_click) on the bo_lead_sid session, like /devino-partener
   - the stage: entrance, then small loops while it is on screen (new orders, a receipt printing, tickets scanned,
     live toasts); a slight tilt that follows the pointer
   - large screens with motion allowed: GSAP + ScrollTrigger + Lenis, loaded only there; the hero is pinned while the
     stage turns to face the visitor and the copy steps aside
   - the ecosystem numbers count up when they come into view
   Everything is readable without JavaScript and without motion. */
(function () {
  'use strict';
  var $ = function (id) { return document.getElementById(id); };
  var hero = $('pt-hero');
  if (!hero) return;

  var reduce = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
  var desktopMQ = window.matchMedia('(min-width: 1024px)');
  var finePointer = window.matchMedia('(hover: hover) and (pointer: fine)').matches;
  var canObserve = 'IntersectionObserver' in window;
  var data = {};
  try { data = JSON.parse(($('v2-data') || {}).textContent || '{}'); } catch (e) {}
  var sleep = function (ms) { return new Promise(function (r) { setTimeout(r, ms); }); };

  /* ---------- personalisation ---------- */
  var PROFILES = data.profiles || {}, ALIASES = data.aliases || {};
  var params = null;
  try { params = new URLSearchParams(window.location.search); } catch (e) {}
  var rawTip = params ? String(params.get('tip') || '').trim().toLowerCase().slice(0, 80) : '';
  var loc = params ? String(params.get('loc') || '').trim().slice(0, 200) : '';
  var typeKey = ALIASES[rawTip] || '';
  var profile = typeKey ? PROFILES[typeKey] : null;

  if (loc) {
    var hello = $('pt-hello'), name = document.createElement('strong');
    name.textContent = loc;
    hello.textContent = 'Salut, ';
    hello.appendChild(name);
    hello.appendChild(document.createTextNode('! Iată ce putem face împreună.'));
    hello.hidden = false;
  }
  if (profile) {
    $('pt-chip-t').textContent = 'Ticketing & booking pentru ' + profile.label;
    $('pt-h1a').textContent = profile.h1a;
    $('pt-h1b').textContent = profile.h1b;
    $('pt-h1c').textContent = profile.h1c;
    $('pt-sub').innerHTML = profile.sub; // the page's own copy (from the server), never text from the address
    $('pt-cta-t').textContent = 'Pune ' + profile.label + ' online';
  }
  var carry = [];
  if (typeKey) carry.push('tip=' + encodeURIComponent(typeKey));
  if (loc) carry.push('loc=' + encodeURIComponent(loc));
  if (carry.length) {
    [].forEach.call(document.querySelectorAll('a[data-signup]'), function (a) {
      a.setAttribute('href', a.getAttribute('href').split('?')[0] + '?' + carry.join('&'));
    });
  }

  /* ---------- funnel ---------- */
  function sessionToken() {
    var m = document.cookie.match(/(?:^|;\s*)bo_lead_sid=([^;]+)/);
    if (m) return decodeURIComponent(m[1]);
    var sid = (window.crypto && crypto.randomUUID) ? crypto.randomUUID() : 'sid-' + Date.now() + '-' + Math.random().toString(36).slice(2);
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

  /* ---------- entrance ---------- */
  requestAnimationFrame(function () { requestAnimationFrame(function () { hero.classList.add('is-in'); }); });

  /* ---------- loops while the stage is on screen ---------- */
  var stage = $('pt-stage'), stageVisible = true;
  if (canObserve && stage) {
    new IntersectionObserver(function (entries) { stageVisible = entries[0].isIntersecting; }, { threshold: 0.15 }).observe(stage);
  }
  function loop(cycle, pause) {
    if (reduce) return;
    (async function () {
      await sleep(2600); // after the entrance
      for (;;) {
        if (stageVisible && !document.hidden) await cycle();
        await sleep(pause);
      }
    })();
  }
  var fmt = new Intl.NumberFormat('ro-RO');

  // new orders arrive at the top of the list, the day's figures follow
  var ORDERS = [
    ['is-green', 'Parc de aventură · Traseu roșu', '3 bilete · 12:00', '195 lei', 195, 3],
    ['is-yellow', 'Atelier ceramică', '2 locuri · 17:30', '170 lei', 170, 2],
    ['is-red', 'Escape room · Camera 1', '5 bilete · 20:00', '225 lei', 225, 5],
    ['is-green', 'Muzeu · Tur ghidat', '4 bilete · 10:00', '120 lei', 120, 4],
    ['is-yellow', 'Tur ghidat · Centrul vechi', '2 bilete · 16:00', '70 lei', 70, 2]
  ];
  var orders = $('pt-orders'), sales = 12480, tickets = 286, orderIndex = 0;
  if (orders) {
    loop(async function () {
      var o = ORDERS[orderIndex++ % ORDERS.length];
      var li = document.createElement('li'), dot = document.createElement('i'), b = document.createElement('b'), small = document.createElement('small'), em = document.createElement('em');
      dot.className = o[0]; b.textContent = o[1]; small.textContent = o[2]; em.textContent = o[3];
      li.appendChild(dot); li.appendChild(b); li.appendChild(small); li.appendChild(em);
      li.className = 'is-new';
      orders.insertBefore(li, orders.firstChild);
      while (orders.children.length > 3) orders.removeChild(orders.lastChild);
      sales += o[4]; tickets += o[5];
      $('pt-kpi-sales').textContent = fmt.format(sales);
      $('pt-kpi-tickets').textContent = fmt.format(tickets);
    }, 3200);
  }

  // the receipt prints again from time to time
  var receipt = $('pt-receipt');
  if (receipt) {
    loop(async function () {
      receipt.classList.remove('is-printing');
      void receipt.offsetWidth;
      receipt.classList.add('is-printing');
      await sleep(2600);
    }, 6400);
  }

  // tickets scanned at the entrance: mostly valid, now and then one already used
  var result = $('pt-result'), resultText = $('pt-result-t'), entered = $('pt-in'), inside = 128;
  if (result) {
    var scans = 0;
    loop(async function () {
      scans++;
      var used = scans % 5 === 0;
      result.style.background = used ? '#F2A900' : '';
      resultText.textContent = used ? 'Deja scanat · 14:02' : 'Bilet valid';
      if (!used && inside < 150) { inside++; entered.textContent = inside; }
      if (inside >= 150) inside = 118;
      result.classList.add('is-pulse');
      await sleep(320);
      result.classList.remove('is-pulse');
    }, 2400);
  }

  // live toasts take turns
  var toasts = [$('pt-toast-a'), $('pt-toast-b')].filter(Boolean);
  if (toasts.length) {
    var turn = 0;
    loop(async function () {
      var t = toasts[turn++ % toasts.length];
      t.classList.add('is-away');
      await sleep(700);
      t.classList.remove('is-away');
    }, 4200);
  }

  /* ---------- the stage leans towards the pointer ---------- */
  var scene = $('pt-scene');
  if (scene && finePointer && !reduce) {
    var tx = 0, ty = 0, raf = 0;
    stage.parentNode.addEventListener('pointermove', function (e) {
      var r = stage.getBoundingClientRect();
      tx = Math.max(-1, Math.min(1, (e.clientX - (r.left + r.width / 2)) / (r.width / 2)));
      ty = Math.max(-1, Math.min(1, (e.clientY - (r.top + r.height / 2)) / (r.height / 2)));
      if (!raf) raf = requestAnimationFrame(function () {
        raf = 0;
        if (hero.classList.contains('is-scrolled')) return; // the scroll scene owns the angle then
        scene.style.setProperty('--ry', (-13 + tx * 5).toFixed(2) + 'deg');
        scene.style.setProperty('--rx', (9 - ty * 4).toFixed(2) + 'deg');
      });
    });
    stage.parentNode.addEventListener('pointerleave', function () {
      scene.style.removeProperty('--ry');
      scene.style.removeProperty('--rx');
    });
  }

  /* ---------- scroll scenes (large screens, motion allowed) ---------- */
  function loadAll(list) {
    return list.reduce(function (p, src) {
      return p.then(function () {
        return new Promise(function (resolve, reject) {
          var s = document.createElement('script');
          s.src = src; s.async = false; s.onload = resolve; s.onerror = reject;
          document.head.appendChild(s);
        });
      });
    }, Promise.resolve());
  }
  var motionStarted = false;
  function initMotion() {
    var gsap = window.gsap, ST = window.ScrollTrigger;
    if (!gsap || !ST || !window.Lenis) return;
    gsap.registerPlugin(ST);
    gsap.matchMedia().add('(min-width: 1024px)', function () {
      var lenis = new Lenis({ autoRaf: false, lerp: 0.12 });
      window.v2Lenis = lenis; // base.js pauses it while the mobile menu is open
      lenis.on('scroll', ST.update);
      var tick = function (t) { lenis.raf(t * 1000); };
      gsap.ticker.add(tick);
      gsap.ticker.lagSmoothing(0);

      // the curtain: the stage turns to face the visitor and grows, the copy steps back, the tools spread out
      var tl = gsap.timeline({
        defaults: { ease: 'none' },
        scrollTrigger: {
          trigger: hero, start: 'top top', end: '+=70%', pin: true, scrub: 0.6, anticipatePin: 1,
          onUpdate: function (self) { hero.classList.toggle('is-scrolled', self.progress > 0.02); }
        }
      });
      // the angle lives in CSS variables on the scene, size and place on the stage: the two transforms never collide
      tl.to(scene, { '--rx': '0deg', '--ry': '0deg', '--rz': '0deg', duration: 1 }, 0)
        .to(stage, { scale: 1.08, xPercent: -6, duration: 1 }, 0)
        .to('#pt-copy', { autoAlpha: 0.15, x: -40, duration: 0.8 }, 0.1)
        .to('.pt-pos', { x: '-6%', y: '4%', duration: 1 }, 0)
        .to('.pt-phone', { x: '6%', y: '2%', duration: 1 }, 0)
        .to('.pt-cue', { autoAlpha: 0, duration: 0.2 }, 0);

      return function () {
        gsap.ticker.remove(tick);
        lenis.destroy();
        window.v2Lenis = null;
        hero.classList.remove('is-scrolled');
      };
    });
  }
  function applyMotion() {
    if (reduce || motionStarted || !desktopMQ.matches || !(data.libs || []).length) return;
    motionStarted = true;
    loadAll(data.libs).then(initMotion, function () { motionStarted = false; });
  }
  applyMotion();
  desktopMQ.addEventListener('change', applyMotion);

  /* ---------- numbers ---------- */
  var counters = [].slice.call(document.querySelectorAll('[data-count]'));
  if (!reduce && canObserve && counters.length) {
    var countIO = new IntersectionObserver(function (entries) {
      entries.forEach(function (en) {
        if (!en.isIntersecting) return;
        countIO.unobserve(en.target);
        var el = en.target, target = Number(el.getAttribute('data-count')) || 0, start = null;
        var frame = function (t) {
          if (start === null) start = t;
          var p = Math.min(1, (t - start) / 1600);
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
})();
