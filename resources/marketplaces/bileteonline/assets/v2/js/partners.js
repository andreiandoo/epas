/* bilete.online v2: /parteneri, "product theatre".
   - personalisation from ?tip=<type>&loc=<name> (same profiles as /devino-partener): greeting, chip, headline, lead,
     signup button; signup links carry tip/loc on to /inregistrare-locatie
   - funnel pings to leads.track (page_view_landing, cta_click) on the bo_lead_sid session, like /devino-partener
   - the stage: entrance, then small loops while it is on screen (new orders, a receipt printing, tickets scanned,
     live toasts); a slight tilt that follows the pointer
   - the demos: booking in six steps, the operator panel's address bar, a ticket office you can use, the scanner's
     three answers (and a spell offline), the SEO address typing itself, the tracking flow, the ANAF documents
   - the calculator, the chapter nav, the ask that follows on phones
   - large screens with motion allowed: GSAP + ScrollTrigger + Lenis, loaded only there; the hero, the booking and the
     day at the venue are pinned scenes driven by the scroll; elsewhere the booking plays on its own
   The demo form is for-venues.js. Everything is readable without JavaScript and without motion. */
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
  var clamp = function (v) { return v < 0 ? 0 : v > 1 ? 1 : v; };
  var fmt = new Intl.NumberFormat('ro-RO');
  var fmt2 = new Intl.NumberFormat('ro-RO', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
  var each = function (list, fn) { Array.prototype.forEach.call(list, fn); };

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
    each(document.querySelectorAll('a[data-signup]'), function (a) {
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

  /* ---------- helpers: run while visible, reveal once ---------- */
  function whenVisible(el, onChange, threshold) {
    if (!el || !canObserve) return;
    new IntersectionObserver(function (entries) { onChange(entries[0].isIntersecting); }, { threshold: threshold || 0.25 }).observe(el);
  }
  // below the fold at load and motion allowed: start hidden (.is-armed), play once in view (.is-in); otherwise final state
  function revealOnce(el, onIn, threshold) {
    if (!el) return;
    var go = function () { el.classList.add('is-in'); if (onIn) onIn(); };
    if (reduce || !canObserve || el.getBoundingClientRect().top < window.innerHeight) { go(); return; }
    el.classList.add('is-armed');
    var io = new IntersectionObserver(function (entries) {
      if (!entries[0].isIntersecting) return;
      io.disconnect();
      requestAnimationFrame(go);
    }, { threshold: threshold || 0.3 });
    io.observe(el);
  }
  function loopWhileVisible(el, cycle, threshold) {
    if (!el || reduce || !canObserve) return;
    var visible = false, running = false;
    whenVisible(el, function (on) {
      visible = on;
      if (!visible || running) return;
      running = true;
      (async function () {
        while (visible && !document.hidden) await cycle();
        running = false;
      })();
    }, threshold);
  }

  /* ---------- hero: entrance ---------- */
  requestAnimationFrame(function () { requestAnimationFrame(function () { hero.classList.add('is-in'); }); });

  /* ---------- hero: loops while the stage is on screen ---------- */
  var stage = $('pt-stage'), stageVisible = true;
  whenVisible(stage, function (on) { stageVisible = on; }, 0.15);
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

  // the stage leans towards the pointer
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

  /* ---------- problem → fix ---------- */
  var flips = $('pt-flips');
  revealOnce(flips, function () {
    each(flips.querySelectorAll('.pt-flip'), function (card) { card.classList.add('is-fixed'); });
  }, 0.35);

  /* ---------- booking: one state per point of progress (0 → 1) ---------- */
  var bk = $('pt-bk'), bookingMode = 'auto', renderBooking = null;
  if (bk) {
    var TITLES = ['Alege ziua și ora', 'Tipul de bilet', 'Extra & rentals', 'Personalizează', 'Plată', 'Gata!'];
    var steps = [].slice.call(bk.querySelectorAll('.pt-bk-step'));
    var rail = [].slice.call(bk.querySelectorAll('[data-rail]'));
    var slots = [].slice.call(bk.querySelectorAll('.pt-bk-slot')), bkTickets = [].slice.call(bk.querySelectorAll('.pt-bk-tk'));
    var extras = [].slice.call(bk.querySelectorAll('.pt-bk-extra')), msgs = [].slice.call(bk.querySelectorAll('.pt-bk-msg'));
    var bkTitle = $('pt-bk-title'), bkCount = $('pt-bk-count'), bkBar = $('pt-bk-progress');
    var typed = $('pt-bk-typed'), gift = $('pt-bk-gift'), pay = $('pt-bk-pay'), payText = $('pt-bk-pay-t');
    var NAME = data.demoName || typed.textContent, GIFT = data.demoGift || gift.textContent;
    var shown = -1;
    var toggle = function (el, cls, on) { if (el.classList.contains(cls) !== on) el.classList.toggle(cls, on); };
    var text = function (el, value) { if (el.textContent !== value) el.textContent = value; };
    renderBooking = function (p) {
      p = clamp(p);
      var s = Math.min(5, Math.floor(p * 6)), t = p * 6 - s;
      if (s === 5) t = Math.min(1, t);
      if (s !== shown) {
        shown = s;
        steps.forEach(function (el) { el.hidden = Number(el.getAttribute('data-step')) !== s; });
        bkTitle.textContent = TITLES[s];
        bkCount.textContent = (s + 1) + '/6';
        bkBar.style.width = ((s + 1) / 6 * 100) + '%';
        rail.forEach(function (li, i) { toggle(li, 'is-past', i < s); toggle(li, 'is-on', i === s); });
      }
      slots.forEach(function (el, i) { toggle(el, 'is-on', i === 2 && (s > 0 || t > 0.45)); });
      bkTickets.forEach(function (el, i) { toggle(el, 'is-on', i === 1 && (s > 1 || (s === 1 && t > 0.45))); });
      toggle(extras[0], 'is-on', s > 2 || (s === 2 && t > 0.3));
      toggle(extras[1], 'is-on', s > 2 || (s === 2 && t > 0.62));
      var chars = s > 3 ? NAME.length : s < 3 ? 0 : Math.round(clamp((t - 0.08) / 0.55) * NAME.length);
      text(typed, NAME.slice(0, chars));
      text(gift, s > 3 || (s === 3 && t > 0.72) ? GIFT : '');
      var paid = s > 4 ? 1 : s < 4 ? 0 : clamp((t - 0.12) / 0.6);
      pay.style.width = Math.round(paid * 100) + '%';
      text(payText, paid >= 1 ? 'Plată confirmată' : 'Se procesează plata…');
      msgs.forEach(function (el, i) { toggle(el, 'is-on', s === 5 && t > 0.08 + i * 0.2); });
    };

    // on its own (phones, tablets, short screens): plays while in view, holds the end, starts again
    if (!reduce && canObserve) {
      var bkVisible = false, bkRaf = 0, bkStart = 0, BK_PLAY = 16000, BK_HOLD = 2800;
      var bkTick = function (now) {
        bkRaf = 0;
        if (bookingMode !== 'auto' || !bkVisible || document.hidden) { bkStart = 0; return; }
        if (!bkStart) bkStart = now;
        var elapsed = now - bkStart;
        if (elapsed > BK_PLAY + BK_HOLD) { bkStart = now; elapsed = 0; }
        renderBooking(elapsed / BK_PLAY);
        bkRaf = requestAnimationFrame(bkTick);
      };
      var bkWake = function () { if (!bkRaf && bookingMode === 'auto' && bkVisible && !document.hidden) bkRaf = requestAnimationFrame(bkTick); };
      whenVisible(bk, function (on) { bkVisible = on; bkWake(); }, 0.3);
      document.addEventListener('visibilitychange', bkWake);
    }
  }

  /* ---------- operator panel: the address bar and the sidebar follow the tab ---------- */
  var url = $('pt-url'), screen = $('pt-screen');
  if (url && screen) {
    var sideItems = screen.querySelectorAll('.pt-ui-nav');
    var SIDE = { panou: 0, participanti: 2, vanzari: 3, sold: 4, marketing: 6 };
    var syncPanel = function (tab) {
      if (tab.getAttribute('aria-selected') !== 'true') return;
      var panel = $(tab.getAttribute('aria-controls'));
      if (panel && panel.getAttribute('data-url')) url.textContent = panel.getAttribute('data-url');
      var key = tab.id.replace('ptt-', '');
      each(sideItems, function (item, i) { item.classList.toggle('is-on', i === SIDE[key]); });
    };
    each(document.querySelectorAll('.pt-tab'), function (tab) {
      tab.addEventListener('click', function () { syncPanel(tab); });
      tab.addEventListener('focus', function () { syncPanel(tab); });
    });
    revealOnce(screen, null, 0.25);
  }

  /* ---------- the ticket office: a cart, a total, a receipt (nothing is sold) ---------- */
  var cart = $('pt-cart'), totalEl = $('pt-total');
  if (cart && totalEl) {
    var lines = [], resetTimer = null;
    var slip = $('pt-slip'), slipLines = $('pt-slip-lines'), slipTotal = $('pt-slip-total'), printEl = $('pt-print'), printText = $('pt-print-t');
    var payBtns = [].slice.call(document.querySelectorAll('[data-pos-pay]'));
    var lei = function (n) { return fmt.format(n) + ' lei'; };
    var total = function () { return lines.reduce(function (s, l) { return s + l.price * l.qty; }, 0); };
    var row = function (label, value) {
      var li = document.createElement('li'), a = document.createElement('span'), b = document.createElement('b');
      a.textContent = label;
      b.textContent = value;
      li.appendChild(a);
      li.appendChild(b);
      return li;
    };
    var draw = function () {
      cart.textContent = '';
      if (!lines.length) {
        var empty = document.createElement('li');
        empty.className = 'pt-tb-empty';
        empty.textContent = 'Atinge un bilet ca să îl adaugi';
        cart.appendChild(empty);
      } else {
        lines.forEach(function (l) { cart.appendChild(row(l.qty + ' × ' + l.name, lei(l.price * l.qty))); });
      }
      totalEl.textContent = lei(total());
      payBtns.forEach(function (b) { b.disabled = !lines.length; });
    };
    var resetPrint = function () {
      slip.classList.remove('is-out');
      printEl.classList.remove('is-done');
      printText.textContent = 'Bon pe imprimanta termică, după fiecare comandă';
    };
    each(document.querySelectorAll('[data-pos-add]'), function (btn) {
      btn.addEventListener('click', function () {
        if (slip.classList.contains('is-out')) { clearTimeout(resetTimer); resetPrint(); }
        var itemName = btn.getAttribute('data-name'), price = parseInt(btn.getAttribute('data-price'), 10) || 0;
        var found = lines.filter(function (l) { return l.name === itemName; })[0];
        if (found) found.qty = Math.min(99, found.qty + 1);
        else lines.push({ name: itemName, price: price, qty: 1 });
        btn.classList.add('is-hit');
        setTimeout(function () { btn.classList.remove('is-hit'); }, 180);
        draw();
      });
    });
    payBtns.forEach(function (b) {
      b.addEventListener('click', function () {
        if (!lines.length) return;
        clearTimeout(resetTimer);
        slipLines.textContent = '';
        lines.forEach(function (l) { slipLines.appendChild(row(l.qty + 'x ' + l.name, fmt.format(l.price * l.qty))); });
        slipTotal.textContent = lei(total());
        slip.classList.add('is-out');
        printEl.classList.add('is-done');
        printText.textContent = (b.getAttribute('data-pos-pay') === 'cash' ? 'Încasat cash. ' : 'Încasat pe card. ') + 'Bon tipărit.';
        lines = [];
        draw();
        var focusBack = document.querySelector('[data-pos-add]');
        if (document.activeElement === b && focusBack) focusBack.focus(); // the pay button is now disabled
        resetTimer = setTimeout(resetPrint, reduce ? 3000 : 4600);
      });
    });
    draw();
  }

  /* ---------- the scanner: its three answers, and a spell without internet ---------- */
  var scanState = $('pt-state'), scanText = $('pt-state-t'), scanSub = $('pt-state-sub');
  if (scanState) {
    var STATES = [
      ['is-ok', 'check-circle', 'ACCES APROBAT', 'Bilet adult · 11:00'],
      ['is-wait', 'clock', 'DEJA SCANAT', 'Bilet folosit anterior, la 10:48'],
      ['is-ok', 'check-circle', 'ACCES APROBAT', 'Bilet familie · 4 persoane'],
      ['is-bad', 'x', 'BILET INVALID', 'Bilet nerecunoscut'],
      ['is-ok', 'check-circle', 'ACCES APROBAT', 'Tur ghidat · 11:00']
    ];
    var net = $('pt-net'), netText = $('pt-net-t'), rate = $('pt-rate'), insideEl = $('pt-inside'), queue = $('pt-queue');
    var si = 0, beat = 0, gate = 412, pending = 0;
    loopWhileVisible($('pt-scanner'), async function () {
      await sleep(2200);
      beat++;
      var offline = beat % 12 >= 7 && beat % 12 <= 10; // four scans without internet, then it syncs
      si = (si + 1) % STATES.length;
      var s = STATES[si];
      scanState.className = 'pt-bp-state ' + s[0] + ' is-pulse';
      var use = scanState.querySelector('use');
      if (use) use.setAttribute('href', '#i-' + s[1]);
      scanText.textContent = s[2];
      scanSub.textContent = s[3];
      if (s[0] === 'is-ok') { gate++; insideEl.textContent = fmt.format(gate); if (offline) pending++; }
      rate.textContent = String(14 + Math.floor(Math.random() * 9));
      net.classList.toggle('is-off', offline);
      netText.textContent = offline ? 'Offline' : (pending ? 'Se sincronizează' : 'Online');
      if (!offline && pending) pending = 0;
      queue.textContent = String(pending);
      await sleep(300);
      scanState.classList.remove('is-pulse');
    }, 0.35);
  }

  /* ---------- the SEO address types itself ---------- */
  var typedUrl = $('pt-typed-url');
  if (typedUrl) {
    var urlItems = [].slice.call(document.querySelectorAll('[data-url-i]'));
    var URLS = urlItems.map(function (li) { return li.querySelector('b').textContent; });
    var ui = 0;
    urlItems.forEach(function (li, i) { li.classList.toggle('is-on', i === 0); });
    loopWhileVisible(typedUrl.parentNode, async function () {
      await sleep(1800);
      var current = typedUrl.textContent;
      while (current.length) { current = current.slice(0, -1); typedUrl.textContent = current; await sleep(22); }
      ui = (ui + 1) % URLS.length;
      urlItems.forEach(function (li, i) { li.classList.toggle('is-on', i === ui); });
      await sleep(260);
      for (var c = 1; c <= URLS[ui].length; c++) { typedUrl.textContent = URLS[ui].slice(0, c); await sleep(55); }
    }, 0.5);
  }

  /* ---------- tracking flow runs only while in view ---------- */
  var flow = $('pt-flow');
  if (flow && !reduce) whenVisible(flow, function (on) { flow.classList.toggle('is-on', on); }, 0.2);

  /* ---------- the money: bars, calculator ---------- */
  revealOnce($('pt-compare'), null, 0.35);
  var qty = $('pt-qty'), price = $('pt-price');
  if (qty && price) {
    var outRev = $('pt-out-rev'), outFee = $('pt-out-fee'), outNet = $('pt-out-net'), outClassic = $('pt-out-classic'), outBuyer = $('pt-out-buyer');
    var num = function (el, max) {
      var v = parseFloat(String(el.value).replace(',', '.'));
      if (!isFinite(v) || v < 0) v = 0;
      return Math.min(v, max);
    };
    var calc = function () {
      var q = Math.round(num(qty, 100000)), p = num(price, 10000), revenue = q * p;
      outRev.textContent = fmt.format(Math.round(revenue)) + ' lei';
      outFee.textContent = fmt.format(Math.round(revenue * 0.02)) + ' lei';
      outNet.textContent = fmt.format(Math.round(revenue)) + ' lei';
      outClassic.textContent = fmt.format(Math.round(revenue * 0.905)) + ' lei';
      outBuyer.textContent = fmt2.format(p * 1.02) + ' lei';
    };
    each(document.querySelectorAll('[data-mirror]'), function (range) {
      var field = $(range.getAttribute('data-mirror'));
      range.addEventListener('input', function () { field.value = range.value; calc(); });
      field.addEventListener('input', function () { range.value = String(Math.min(num(field, 1e9), Number(range.max))); calc(); });
    });
    calc();
  }

  /* ---------- ANAF: a sale, then its documents ---------- */
  var anaf = $('pt-anaf'), docs = [].slice.call(document.querySelectorAll('.pt-doc'));
  if (anaf && docs.length) {
    var elapsedEl = $('pt-elapsed');
    loopWhileVisible(anaf, async function () {
      docs.forEach(function (d) { d.classList.remove('is-done', 'is-busy'); });
      elapsedEl.textContent = '0s';
      anaf.classList.add('is-busy');
      await sleep(900);
      for (var i = 0; i < docs.length; i++) {
        docs[i].classList.add('is-busy'); await sleep(1100);
        docs[i].classList.remove('is-busy'); docs[i].classList.add('is-done');
        elapsedEl.textContent = Math.min(3, i + 1) + 's'; await sleep(450);
      }
      anaf.classList.remove('is-busy');
      await sleep(2600);
    }, 0.3);
  }

  /* ---------- how to start: the path draws ---------- */
  revealOnce($('pt-steps'), null, 0.3);

  /* ---------- the ask follows on phones and tablets: past the hero, until the finale ---------- */
  // measured on scroll rather than observed: a jump (anchor, fling) can carry the zero-height sentinel past the screen
  // without it ever intersecting
  var bar = $('pt-bar'), finale = $('demo'), sentinel = $('hdr-sentinel');
  if (bar && finale && sentinel) bar.hidden = false;
  function followBar() {
    if (!bar || bar.hidden || !bar.offsetHeight) return;
    var on = sentinel.getBoundingClientRect().top < 0 && finale.getBoundingClientRect().top > window.innerHeight;
    if (bar.classList.contains('is-in') !== on) bar.classList.toggle('is-in', on);
  }

  /* ---------- chapter nav: the chapter on screen, smooth jumps ---------- */
  var chapterLinks = {};
  each(document.querySelectorAll('[data-chapter-link]'), function (a) { chapterLinks[a.getAttribute('data-chapter-link')] = a; });
  var chapterSecs = [].slice.call(document.querySelectorAll('[data-chapter]'));
  var navEl = $('pt-nav'), activeChapter = null, navRaf = 0;
  var markChapter = function () {
    navRaf = 0;
    followBar();
    if (!navEl || !navEl.offsetHeight) return;
    var line = window.innerHeight * 0.4, current = null;
    chapterSecs.forEach(function (sec) { if (sec.getBoundingClientRect().top <= line) current = sec.getAttribute('data-chapter'); });
    if (current === activeChapter) return;
    if (activeChapter && chapterLinks[activeChapter]) { chapterLinks[activeChapter].classList.remove('is-on'); chapterLinks[activeChapter].removeAttribute('aria-current'); }
    activeChapter = current;
    if (current && chapterLinks[current]) {
      var link = chapterLinks[current];
      link.classList.add('is-on');
      link.setAttribute('aria-current', 'true');
      var list = link.closest('.pt-nav-list');
      if (list && list.scrollWidth > list.clientWidth) list.scrollLeft = link.offsetLeft - 24;
    }
  };
  window.addEventListener('scroll', function () { if (!navRaf) navRaf = requestAnimationFrame(markChapter); }, { passive: true });
  window.addEventListener('resize', function () { if (!navRaf) navRaf = requestAnimationFrame(markChapter); });
  markChapter();

  var main = $('main');
  document.addEventListener('click', function (e) {
    var a = e.target.closest ? e.target.closest('a[href^="#"]') : null;
    if (!a || e.defaultPrevented || e.button !== 0 || e.metaKey || e.ctrlKey || e.shiftKey || e.altKey) return;
    var id = a.getAttribute('href').slice(1);
    var target = id ? document.getElementById(id) : null;
    if (!target || !main.contains(target)) return;
    if (!window.v2Lenis) return; // native jump (with scroll-margin) when there is no smooth scroll
    e.preventDefault();
    window.v2Lenis.scrollTo(target, { duration: 1.2 }); // Lenis honours the sections' scroll-margin-top (header + nav)
    if (history.replaceState) history.replaceState(null, '', '#' + id);
    if (!target.hasAttribute('tabindex')) target.setAttribute('tabindex', '-1');
    target.focus({ preventScroll: true });
  });


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
    var mm = gsap.matchMedia();

    mm.add('(min-width: 1024px)', function () {
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

      // the panel window lies back, then stands up as it arrives
      var ui = document.querySelector('.pt-ui');
      if (ui) {
        gsap.fromTo(ui, { rotationX: 14, scale: 0.94, y: 40 }, {
          rotationX: 0, scale: 1, y: 0, ease: 'none',
          scrollTrigger: { trigger: screen, start: 'top 95%', end: 'top 35%', scrub: 0.6 }
        });
      }
      // the devices drift a little against the scroll
      ['.pt-till', '.pt-bigphone'].forEach(function (sel) {
        var el = document.querySelector(sel);
        if (!el) return;
        gsap.fromTo(el, { y: 50 }, { y: -50, ease: 'none', scrollTrigger: { trigger: el.closest('section'), start: 'top bottom', end: 'bottom top', scrub: true } });
      });
      // the 2% grows into place
      var big = $('pt-big2');
      if (big) {
        gsap.fromTo(big, { scale: 0.62, opacity: 0.25 }, { scale: 1, opacity: 1, ease: 'none', scrollTrigger: { trigger: '#bani', start: 'top 85%', end: 'top 30%', scrub: 0.6 } });
      }

      return function () {
        gsap.ticker.remove(tick);
        lenis.destroy();
        window.v2Lenis = null;
        hero.classList.remove('is-scrolled');
      };
    });

    // pinned scenes need the room: wide and tall enough screens only
    mm.add('(min-width: 1024px) and (min-height: 760px)', function () {
      var cleanups = [];

      // booking: the scroll plays the six steps
      var bookingSec = $('booking');
      if (bookingSec && renderBooking) {
        bookingMode = 'scroll';
        renderBooking(0);
        ST.create({
          trigger: bookingSec, start: 'top top', end: '+=160%', pin: true, anticipatePin: 1,
          onUpdate: function (self) { renderBooking(self.progress); }
        });
        cleanups.push(function () { bookingMode = 'auto'; renderBooking(1); });
      }

      // a day at the venue: the strip slides from morning to evening, the sky follows
      var day = $('o-zi'), track = $('pt-day-track');
      if (day && track) {
        day.classList.add('is-pinned');
        var cards = [].slice.call(track.children), clock = $('pt-day-time'), now = -1;
        var distance = function () { return Math.max(0, track.scrollWidth - document.documentElement.clientWidth); };
        var paint = function (p) {
          var dusk = clamp((p - 0.45) / 0.5);
          day.style.setProperty('--dusk', dusk.toFixed(3));
          day.style.setProperty('--sun-x', (12 + p * 76).toFixed(2) + '%');
          day.style.setProperty('--sun-y', (40 - Math.sin(p * Math.PI) * 22).toFixed(2) + '%');
          day.classList.toggle('is-dusk', dusk > 0.5);
          var n = Math.min(cards.length - 1, Math.round(p * (cards.length - 1)));
          if (n !== now) {
            if (cards[now]) cards[now].classList.remove('is-now');
            now = n;
            cards[n].classList.add('is-now');
            clock.textContent = cards[n].getAttribute('data-time');
          }
        };
        paint(0);
        gsap.to(track, {
          x: function () { return -distance(); }, ease: 'none',
          scrollTrigger: {
            trigger: day, start: 'top top', end: function () { return '+=' + Math.max(distance(), window.innerHeight * 0.8); },
            pin: true, scrub: 0.6, anticipatePin: 1, invalidateOnRefresh: true,
            onUpdate: function (self) { paint(self.progress); }
          }
        });
        cleanups.push(function () {
          day.classList.remove('is-pinned', 'is-dusk');
          ['--dusk', '--sun-x', '--sun-y'].forEach(function (k) { day.style.removeProperty(k); });
          cards.forEach(function (c) { c.classList.remove('is-now'); });
        });
      }

      return function () { cleanups.forEach(function (fn) { fn(); }); };
    });
  }
  function applyMotion() {
    if (reduce || motionStarted || !desktopMQ.matches || !(data.libs || []).length) return;
    motionStarted = true;
    loadAll(data.libs).then(initMotion, function () { motionStarted = false; });
  }
  applyMotion();
  desktopMQ.addEventListener('change', applyMotion);
})();
