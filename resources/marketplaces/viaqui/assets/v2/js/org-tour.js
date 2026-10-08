/* viaqui.com v2: the guided tour of the operator's panel (/organizator/panou). Thirteen short steps anchored to the
   real chrome — the menu entries in the sidebar, the start checklist, the panel's figures — that say what every page is
   for and walk the selling flow end to end: locație → produse → aprobare → vânzare online / widget / casă → rezervări
   → raport → sold. It runs by itself the first time an operator opens the panel (remembered per operator in
   localStorage) and can be replayed any time from the "Ghid rapid" button on the page.
   A step whose anchor is missing or hidden is skipped; on phones the sidebar is opened first so its entries can be
   pointed at, and the bubble is a sheet that never sits on top of what it explains. The bubble is a modal dialog: the
   focus stays inside it, Escape closes it, and every step is announced through an aria-live line.
   Exposes window.BO_TOUR {start, autostart}. Markup built here, styles in assets/v2/css/org-tour.css. */
(function () {
  'use strict';
  var O = window.BO_ORG;
  if (!O || !document.getElementById('od')) return;
  var el = O.el;
  var KEY = 'bo_org_tour_v1_';
  var M = 14, PAD = 6;
  var SIDE = '#org-side .org-link[href$="'; // ends with: a language prefix may come before the address

  var STEPS = [
    { title: VQ.t('Welcome to your dashboard'),
      text: VQ.t('In a few steps we show you what each page does and in which order to work to reach your first sale. Move with Next and Back; leave at any time with Close or the Escape key.') },
    { sel: '#ob', tight: '#ob .org-panel-head',
      title: VQ.t('Your first steps'),
      text: VQ.t('Here you see, point by point, what is already done and what is left. We read the ticks from your account, you do not fill them in. When all seven are done, the section leaves the dashboard.') },
    { sel: SIDE + '/organizator/locatii"]', drawer: true,
      title: VQ.t('Step 1: My venues'),
      text: VQ.t('The venue is where customers come: address, map, opening hours, photos and rules. Everything you sell sits under a venue, so it comes first.') },
    { sel: SIDE + '/organizator/produse"]', drawer: true,
      title: VQ.t('Step 2: Products'),
      text: VQ.t('Products are what you sell: access tickets, experiences with a start time, or packages. Each has its own price, capacity and schedule, under the venue it belongs to.') },
    { title: VQ.t('Step 3: Approval'),
      text: VQ.t('When the venue and the products are complete, you send them for approval. We check them within 1–2 working days. After approval you publish them yourself, with one button: only then can they be bought.') },
    { sel: SIDE + '/organizator/pos"]', drawer: true,
      title: VQ.t('Step 4: Sell online and at the desk'),
      text: VQ.t('Published products sell by themselves on Viaqui. On site you open the desk from Desk & POS: you sell on the spot, scan the tickets and close the shift with the desk report.') },
    { sel: SIDE + '/organizator/widget-uri"]', drawer: true,
      title: VQ.t('Step 5: Embed widgets'),
      text: VQ.t('The same basket, but on your own site: you copy a code and paste it into your page, and customers buy without leaving it. First add the sites where it is allowed to run.') },
    { sel: SIDE + '/organizator/rezervari"]', drawer: true,
      title: VQ.t('Step 6: Bookings'),
      text: VQ.t('All sales, arranged by the day of the visit: who comes, at what time, with which confirmation code. From here you do the check-in and mark who did not show up.') },
    { sel: SIDE + '/organizator/raport"]', drawer: true,
      title: VQ.t('Step 7: Report'),
      text: VQ.t('How much you sold in a period, by day and by product, with the Viaqui commission taken off so you see what you keep. You can download it as CSV.') },
    { sel: SIDE + '/organizator/sold"]', drawer: true,
      title: VQ.t('Step 8: Balance'),
      text: VQ.t('Your money: how much is available, how much is still pending and what we have paid you so far. This is also where you request a payout.') },
    { sel: SIDE + '/organizator/setari"]', drawer: true,
      title: VQ.t('The account, the contract and the money'),
      text: VQ.t('In Account & company you have the company details, the contract to sign and the bank account we pay you into. Next to it are Billing, with the commission invoices, and Account team, where you add colleagues with their own rights.') },
    { sel: '.od-stats',
      title: VQ.t('The dashboard figures'),
      text: VQ.t('The figures of the current month for your account, with the chart below. For figures by booking and by product go to Report, and for money to Balance.') },
    { sel: '#ob-guide',
      title: VQ.t('Done: you can replay it at any time'),
      text: VQ.t('This button starts the guide again, whenever you need it. If you get stuck, the Help centre and Support tickets are in the menu, and from Extra services you can ask us for promotion or photos.') },
  ];

  function recall(k) { try { return localStorage.getItem(k); } catch (e) { return null; } }
  function remember(k, v) { try { localStorage.setItem(k, v); } catch (e) {} }
  function orgId() { var p = O.profile && O.profile(); return p && p.id != null ? String(p.id) : 'x'; }

  /* ---------- the overlay ---------- */
  var box = null, veil, ring, bubble, elStep, elTitle, elText, bPrev, bNext, bClose, liveRegion;
  var plan = STEPS, open = false, at = 0, anchor = null, sheetTop = false, opened = false, drawered = false, raf = 0, before = null;

  function build() {
    elStep = el('p', { class: 'ot-count', id: 'ot-count' });
    elTitle = el('h2', { class: 'ot-t', id: 'ot-t' });
    elText = el('p', { class: 'ot-p' });
    bClose = el('button', { class: 'ot-b', id: 'ot-close', type: 'button', text: VQ.t('Close') });
    bPrev = el('button', { class: 'ot-b', id: 'ot-prev', type: 'button', text: VQ.t('Back') });
    bNext = el('button', { class: 'ot-b is-primary', id: 'ot-next', type: 'button', text: VQ.t('Next') });
    bubble = el('div', { class: 'ot-bubble', role: 'dialog', 'aria-modal': 'true', 'aria-labelledby': 'ot-t', 'aria-describedby': 'ot-count', tabindex: '-1' }, [
      elStep, elTitle, elText,
      el('div', { class: 'ot-nav' }, [bClose, el('span', { class: 'ot-sp' }), bPrev, bNext]),
    ]);
    veil = el('div', { class: 'ot-veil' });
    ring = el('div', { class: 'ot-ring' });
    liveRegion = el('p', { class: 'sr', 'aria-live': 'polite' });
    box = el('div', { class: 'ot' }, [veil, ring, bubble, liveRegion]);
    document.body.appendChild(box);

    veil.addEventListener('click', close);
    bClose.addEventListener('click', close);
    bPrev.addEventListener('click', function () { go(at - 1, -1); });
    bNext.addEventListener('click', function () { if (at >= plan.length - 1) close(); else go(at + 1, 1); });
    document.addEventListener('keydown', onKey, true);
    window.addEventListener('resize', schedule);
    window.addEventListener('scroll', schedule, true);
  }

  function onKey(e) {
    if (!open) return;
    if (e.key === 'Escape') { e.preventDefault(); e.stopPropagation(); close(); return; }
    if (e.key !== 'Tab') return;
    var f = [bClose, bPrev, bNext].filter(function (b) { return !b.disabled; });
    if (!f.length) return;
    var i = f.indexOf(document.activeElement);
    if (i < 0) { e.preventDefault(); (e.shiftKey ? f[f.length - 1] : f[0]).focus(); return; }
    if (e.shiftKey && i === 0) { e.preventDefault(); f[f.length - 1].focus(); }
    else if (!e.shiftKey && i === f.length - 1) { e.preventDefault(); f[0].focus(); }
  }

  /* ---------- anchors ---------- */
  function shown(n) {
    if (!n) return false;
    var r = n.getBoundingClientRect();
    if (r.width < 2 || r.height < 2) return false;
    var p = n;
    while (p && p.nodeType === 1) {
      var cs = window.getComputedStyle(p);
      if (cs.display === 'none' || cs.visibility === 'hidden') return false;
      p = p.parentElement;
    }
    return true;
  }
  function find(sel) { try { return sel ? document.querySelector(sel) : null; } catch (e) { return null; } }
  function drawer(want) {
    var burger = document.getElementById('org-burger');
    if (!burger || !shown(burger)) return false; // wide screen: the sidebar is there anyway
    var isOpen = burger.getAttribute('aria-expanded') === 'true';
    if (want === isOpen) return isOpen;
    var btn = want ? burger : (document.querySelector('.org-x[data-org-drawer="close"]') || burger);
    try { btn.click(); } catch (e) {}
    return want;
  }
  /** The nearest ancestor that scrolls, or null for the page itself. */
  function scroller(n) {
    var p = n.parentElement;
    while (p && p !== document.body && p !== document.documentElement) {
      var cs = window.getComputedStyle(p);
      if (/(auto|scroll)/.test(cs.overflowY) && p.scrollHeight > p.clientHeight + 2) return p;
      p = p.parentElement;
    }
    return null;
  }
  function fixedInside(n) {
    var p = n;
    while (p && p.nodeType === 1) {
      if (window.getComputedStyle(p).position === 'fixed') return true;
      p = p.parentElement;
    }
    return false;
  }
  /** Scroll whatever can be scrolled so that n sits between top and bottom of the viewport. */
  function intoBand(n, top, bottom) {
    var h = bottom - top;
    function once(target) {
      var r = n.getBoundingClientRect();
      if (r.top >= top - 1 && r.bottom <= bottom + 1) return;
      var want = r.height <= h ? top + (h - r.height) / 2 : top;
      var d = r.top - want;
      if (!d) return;
      if (target) target.scrollTop += d;
      else if (!fixedInside(n)) window.scrollBy(0, d);
    }
    once(scroller(n));
    once(null);
  }

  /* ---------- placing ---------- */
  function narrow() { return document.documentElement.clientWidth < 760; }
  function place() {
    raf = 0;
    if (!open) return;
    var vw = document.documentElement.clientWidth, vh = window.innerHeight;
    if (!anchor || !shown(anchor)) {
      ring.className = 'ot-ring is-off';
      bubble.className = 'ot-bubble is-center';
      bubble.style.left = bubble.style.top = bubble.style.bottom = '';
      return;
    }
    var r = anchor.getBoundingClientRect();
    var t = Math.max(-PAD, r.top - PAD), l = Math.max(-PAD, r.left - PAD);
    var b = Math.min(vh + PAD, r.bottom + PAD), rr = Math.min(vw + PAD, r.right + PAD);
    ring.className = 'ot-ring';
    ring.style.left = l + 'px';
    ring.style.top = t + 'px';
    ring.style.width = Math.max(8, rr - l) + 'px';
    ring.style.height = Math.max(8, b - t) + 'px';

    bubble.className = 'ot-bubble' + (narrow() ? ' is-sheet' : '');
    bubble.style.left = bubble.style.top = bubble.style.bottom = '';
    var bw = bubble.offsetWidth, bh = bubble.offsetHeight;
    if (narrow()) {
      if (sheetTop) bubble.style.top = M + 'px';
      else bubble.style.bottom = M + 'px';
      return;
    }
    var top;
    if (r.right + M + bw <= vw - M) { bubble.style.left = (r.right + M) + 'px'; top = r.top; }
    else if (r.left - M - bw >= M) { bubble.style.left = (r.left - M - bw) + 'px'; top = r.top; }
    else if (vh - r.bottom >= bh + M + M) { bubble.style.left = Math.max(M, Math.min(vw - bw - M, r.left)) + 'px'; top = r.bottom + M; }
    else if (r.top >= bh + M + M) { bubble.style.left = Math.max(M, Math.min(vw - bw - M, r.left)) + 'px'; top = r.top - bh - M; }
    else { bubble.style.left = (vw - bw - M) + 'px'; top = vh - bh - M; }
    bubble.style.top = Math.max(M, Math.min(vh - bh - M, top)) + 'px';
  }
  function schedule() { if (open && !raf) raf = window.requestAnimationFrame(place); }

  /* ---------- steps ---------- */
  function resolve(i) {
    var s = plan[i];
    if (!s.sel) return null;
    var n = find(s.sel);
    if (!n) return undefined; // nothing to point at: skip the step
    if (!shown(n) && s.drawer) drawered = drawer(true) || drawered;
    if (!shown(n)) return undefined;
    if (s.tight && n.getBoundingClientRect().height > window.innerHeight - 240) {
      var t = find(s.tight); // too tall to leave room for the bubble: point at its head instead
      if (t && shown(t)) return t;
    }
    return n;
  }

  function go(i, dir) {
    var n;
    while (i >= 0 && i < plan.length) {
      n = resolve(i);
      if (n !== undefined) break;
      i += dir || 1;
    }
    if (i < 0 || i >= plan.length) { close(); return; }
    at = i;
    var s = plan[i];
    if (!s.drawer && drawered) { drawer(false); drawered = false; }
    anchor = n || null;

    elStep.textContent = VQ.t('Step {n} of {total}', { n: i + 1, total: plan.length });
    elTitle.textContent = s.title;
    elText.textContent = s.text;
    bPrev.disabled = i === 0;
    bNext.textContent = i >= plan.length - 1 ? VQ.t('Done') : VQ.t('Next');

    // the sidebar may still be sliding in: settle the position over the next frames
    sheetTop = false;
    place();
    if (anchor) {
      var vh = window.innerHeight;
      if (narrow()) {
        var bh = bubble.offsetHeight;
        // the sheet sits where it leaves the most room: below when what we point at fits above it, above otherwise
        sheetTop = anchor.getBoundingClientRect().height > vh - bh - 3 * M;
        place();
        bh = bubble.offsetHeight;
        if (sheetTop) intoBand(anchor, bh + 2 * M, vh - M);
        else intoBand(anchor, M, vh - bh - 2 * M);
        var ar = anchor.getBoundingClientRect(), br = bubble.getBoundingClientRect();
        if (!(ar.bottom <= br.top || ar.top >= br.bottom)) { // nothing could scroll out of the way: sheet to the other side
          sheetTop = !sheetTop;
          place();
          bh = bubble.offsetHeight;
          if (sheetTop) intoBand(anchor, bh + 2 * M, vh - M);
          else intoBand(anchor, M, vh - bh - 2 * M);
        }
      } else intoBand(anchor, 24, vh - 24);
    }
    place();
    window.setTimeout(place, 60);
    window.setTimeout(place, 320);
    window.setTimeout(function () { liveRegion.textContent = VQ.t('Step {n} of {total}', { n: at + 1, total: plan.length }) + ': ' + s.title + '. ' + s.text; }, 80);
  }

  function start() {
    if (open) { go(0, 1); return; }
    if (!box) build();
    before = document.activeElement;
    open = true;
    opened = true;
    drawered = false;
    box.classList.add('is-on');
    document.documentElement.classList.add('ot-on');
    remember(KEY + orgId(), '1');
    // a step whose element is not on this page at all (the checklist once it is done) never gets a number
    plan = STEPS.filter(function (s) { var n = s.sel ? find(s.sel) : null; return !s.sel || !!(n && !n.hidden); });
    go(0, 1);
    bubble.focus();
  }
  function close() {
    if (!open) return;
    open = false;
    box.classList.remove('is-on');
    document.documentElement.classList.remove('ot-on');
    liveRegion.textContent = '';
    if (drawered) { drawer(false); drawered = false; }
    anchor = null;
    var back = document.getElementById('ob-guide') || before;
    try { if (back && back.focus && document.contains(back)) back.focus(); } catch (e) {}
  }
  function autostart() {
    if (opened || open) return;
    opened = true;
    // "Ghid rapid" in the account menu lands here with ?ghid=1, from any page
    if (/[?&]ghid=1/.test(window.location.search)) {
      try { window.history.replaceState(null, '', window.location.pathname); } catch (e) {}
      start();
      return;
    }
    if (recall(KEY + orgId()) === '1') return;
    start();
  }

  window.BO_TOUR = { start: start, autostart: autostart };

  O.ready.then(function (ok) {
    if (!ok) return;
    var btn = document.getElementById('ob-guide');
    if (btn) btn.addEventListener('click', function () { opened = true; start(); });
    // the checklist calls autostart() once it knows where the operator stands; this is the fallback
    window.setTimeout(autostart, 3000);
  });
})();
