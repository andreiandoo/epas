/* bilete.online v2: the sales page for venues (/vinde-bilete). Four small things, all optional to the content:
   the address bar of the dashboard mock follows the chosen section, the POS mock adds tickets to a cart and prints a
   receipt, the scanner mock cycles through the three answers it really gives, and the commission calculator works out
   what a month looks like. Nothing here talks to the API; the demo form is handled by for-venues.js. */
(function () {
  'use strict';
  var reduce = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
  var $ = function (id) { return document.getElementById(id); };
  var nf = new Intl.NumberFormat('ro-RO');
  var nf2 = new Intl.NumberFormat('ro-RO', { minimumFractionDigits: 2, maximumFractionDigits: 2 });

  /* ---------- the mock address bar follows the section ---------- */
  var url = $('sl-url');
  if (url) {
    Array.prototype.forEach.call(document.querySelectorAll('.sl-tab'), function (tab) {
      tab.addEventListener('click', function () {
        var panel = $(tab.getAttribute('aria-controls'));
        if (panel && panel.getAttribute('data-url')) url.textContent = panel.getAttribute('data-url');
      });
    });
  }

  /* ---------- POS: cart, total, receipt ---------- */
  var cart = $('sl-cart'), totalEl = $('sl-total'), printEl = $('sl-print');
  if (cart && totalEl) {
    var lines = [], printTimer = null, resetTimer = null;
    var receipt = $('sl-receipt'), rcLines = $('sl-rc-lines'), rcTotal = $('sl-rc-total');
    var payBtns = [].slice.call(document.querySelectorAll('[data-sl-pay]'));
    var lei = function (n) { return nf.format(n) + ' lei'; };
    function total() {
      return lines.reduce(function (s, l) { return s + l.price * l.qty; }, 0);
    }
    function row(text, value, cls) {
      var li = document.createElement('li'), a = document.createElement('span'), b = document.createElement('b');
      a.textContent = text;
      b.textContent = value;
      if (cls) li.className = cls;
      li.appendChild(a);
      li.appendChild(b);
      return li;
    }
    function draw() {
      cart.textContent = '';
      if (!lines.length) {
        var empty = document.createElement('li');
        empty.className = 'sl-tb-empty';
        empty.textContent = 'Atinge un bilet ca să îl adaugi';
        cart.appendChild(empty);
      } else {
        lines.forEach(function (l) { cart.appendChild(row(l.qty + ' × ' + l.name, lei(l.price * l.qty))); });
      }
      totalEl.textContent = lei(total());
      payBtns.forEach(function (b) { b.disabled = !lines.length; });
    }
    function add(btn) {
      var name = btn.getAttribute('data-name'), price = parseInt(btn.getAttribute('data-price'), 10) || 0;
      var found = lines.filter(function (l) { return l.name === name; })[0];
      if (found) found.qty++;
      else lines.push({ name: name, price: price, qty: 1 });
      btn.classList.add('is-hit');
      setTimeout(function () { btn.classList.remove('is-hit'); }, 180);
      draw();
    }
    function print(method) {
      if (!lines.length) return;
      clearTimeout(printTimer);
      clearTimeout(resetTimer);
      rcLines.textContent = '';
      lines.forEach(function (l) { rcLines.appendChild(row(l.qty + 'x ' + l.name, nf.format(l.price * l.qty))); });
      rcTotal.textContent = lei(total());
      receipt.classList.add('is-out');
      printEl.classList.add('is-done');
      printEl.textContent = method === 'cash' ? 'Încasat cash. Bon tipărit.' : 'Încasat pe card. Bon tipărit.';
      resetTimer = setTimeout(function () {
        lines = [];
        draw();
        receipt.classList.remove('is-out');
        printEl.classList.remove('is-done');
        printEl.textContent = 'Bon pe imprimanta termică, după fiecare comandă';
      }, reduce ? 2400 : 4200);
    }
    Array.prototype.forEach.call(document.querySelectorAll('[data-sl-add]'), function (btn) {
      btn.addEventListener('click', function () { add(btn); });
    });
    payBtns.forEach(function (b) {
      b.addEventListener('click', function () { print(b.getAttribute('data-sl-pay')); });
    });
    draw();
  }

  /* ---------- the scanner mock: the three answers it really gives ---------- */
  var state = $('sl-state'), stateT = $('sl-state-t'), stateSub = $('sl-state-sub'), rate = $('sl-rate');
  if (state && stateT && !reduce && 'IntersectionObserver' in window) {
    var STATES = [
      ['is-ok', 'check-circle', 'ACCES APROBAT', 'Bilet adult · 11:00'],
      ['is-wait', 'clock', 'DEJA SCANAT', 'Bilet folosit anterior, la 10:48'],
      ['is-ok', 'check-circle', 'ACCES APROBAT', 'Bilet familie · 4 persoane'],
      ['is-bad', 'x', 'BILET INVALID', 'Bilet nerecunoscut'],
      ['is-ok', 'check-circle', 'ACCES APROBAT', 'Tur ghidat · 11:00'],
    ];
    var i = 0, timer = null;
    function step() {
      i = (i + 1) % STATES.length;
      var s = STATES[i];
      state.className = 'sl-ph-state ' + s[0];
      var use = state.querySelector('use');
      if (use) use.setAttribute('href', '#i-' + s[1]);
      stateT.textContent = s[2];
      if (stateSub) stateSub.textContent = s[3];
      if (rate) rate.textContent = String(14 + Math.floor(Math.random() * 9));
    }
    var io = new IntersectionObserver(function (entries) {
      entries.forEach(function (en) {
        if (en.isIntersecting && !timer) timer = setInterval(step, 2600);
        else if (!en.isIntersecting && timer) { clearInterval(timer); timer = null; }
      });
    }, { threshold: 0.4 });
    io.observe($('sl-scanner') || state);
  }

  /* ---------- what a month looks like ---------- */
  var qty = $('sl-qty'), price = $('sl-price');
  if (qty && price) {
    var outRev = $('sl-out-rev'), outFee = $('sl-out-fee'), outNet = $('sl-out-net'), outBuyer = $('sl-out-buyer');
    function num(el, max) {
      var v = parseFloat(String(el.value).replace(',', '.'));
      if (!isFinite(v) || v < 0) v = 0;
      return Math.min(v, max);
    }
    function calc() {
      var q = Math.round(num(qty, 100000)), p = num(price, 10000);
      var revenue = q * p, fee = revenue * 0.02;
      outRev.textContent = nf.format(Math.round(revenue)) + ' lei';
      outFee.textContent = nf.format(Math.round(fee)) + ' lei';
      outNet.textContent = nf.format(Math.round(revenue)) + ' lei';
      outBuyer.textContent = nf2.format(p * 1.02) + ' lei';
    }
    [qty, price].forEach(function (el) { el.addEventListener('input', calc); });
    calc();
  }

  /* ---------- the ask follows on phones, until the form is in view ---------- */
  var bar = $('sl-bar'), demo = $('demo');
  if (bar && demo && 'IntersectionObserver' in window) {
    bar.hidden = false;
    var formIn = false, passedHero = false;
    var show = function () { bar.classList.toggle('is-in', passedHero && !formIn); };
    new IntersectionObserver(function (e) { formIn = e[0].isIntersecting; show(); }, { threshold: 0.08 }).observe(demo);
    var sentinel = $('hdr-sentinel');
    if (sentinel) {
      new IntersectionObserver(function (e) { passedHero = !e[0].isIntersecting && e[0].boundingClientRect.top < 0; show(); }, { threshold: 0 }).observe(sentinel);
    }
  }
})();
