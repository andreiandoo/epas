/* viaqui.com v2: the operator's balance (/organizator/sold). One call, /organizer/activities-module/summary, gives
   every figure: totals and by_source.period for the chosen period (this month by default), by_month for the last
   thirteen months, all_time for the empty state. /organizer/contract adds the commission rate and the invoice due
   days (the shell's /organizer/me carries the rate too, and would carry has_payout_details if core ever sent it — the
   bank note stays neutral until then). viaqui.com does not do payouts, so nothing here requests one: the commission
   on desk sales is invoiced monthly, the online money is owed to the operator, and the page says plainly that split
   payment is not live yet. The monthly CSV is built in the browser from the same data. Runs inside the organizer shell
   (window.BO_ORG); text from the API is always written as text. */
(function () {
  'use strict';
  var O = window.BO_ORG, root = document.getElementById('of');
  if (!O || !root) return;
  var F = O.fmt, el = O.el, icon = O.icon;
  var $ = function (id) { return document.getElementById(id); };
  var BASE = '/organizer/activities-module';
  var today = F.ymd(new Date()), seq = 0;
  var onTop = true, modeKnown = false, rate = null, dueDays = 5, floor = null, hasBank = null, months = [], last = null;

  /* =================== HELPERS =================== */
  function addDays(ymd, n) { var d = new Date(ymd + 'T12:00:00'); d.setDate(d.getDate() + n); return F.ymd(d); }
  function monthStart(ymd, back) { var d = new Date(ymd.slice(0, 7) + '-01T12:00:00'); d.setMonth(d.getMonth() - (back || 0)); return F.ymd(d); }
  function monthEnd(ymd) { var d = new Date(ymd.slice(0, 7) + '-01T12:00:00'); d.setMonth(d.getMonth() + 1); d.setDate(0); return F.ymd(d); }
  function qs(o) { return Object.keys(o).filter(function (k) { return o[k] !== '' && o[k] != null; }).map(function (k) { return encodeURIComponent(k) + '=' + encodeURIComponent(o[k]); }).join('&'); }
  function n(v) { return F.toNum(v); }
  function money(v) { return F.money(n(v)); }
  function dayLabel(ymd) { var d = F.dateOf(ymd); return d ? F.date(d, { day: 'numeric', month: 'long', year: 'numeric' }) : ''; }
  function monthLabel(m) {
    var d = F.dateOf(/^\d{4}-\d{2}$/.test(String(m || '')) ? m + '-01' : m);
    var s = d ? F.date(d, { month: 'long', year: 'numeric' }) : String(m || '');
    return s.charAt(0).toUpperCase() + s.slice(1); // a month name reads better as a row label capitalised
  }
  function bookings(x) { return VQ.n(Math.round(n(x && x.bookings)), 'booking', 'bookings'); }
  function set(id, text) { var node = $(id); if (node) node.textContent = text; }
  function errText(err, fallback) {
    return (err && err.message && err.message !== 'An error occurred') ? String(err.message) : fallback;
  }

  /* =================== PERIOD =================== */
  var PRESETS = {
    today: function () { return [today, today]; },
    7: function () { return [addDays(today, -6), today]; },
    30: function () { return [addDays(today, -29), today]; },
    month: function () { return [monthStart(today), today]; },
    'last-month': function () { var s = monthStart(today, 1); return [s, monthEnd(s)]; },
    year: function () { return [today.slice(0, 4) + '-01-01', today]; },
  };
  function setRange(r, preset) {
    $('of-from').value = r[0];
    $('of-to').value = r[1];
    [].forEach.call(document.querySelectorAll('#of-presets .fchip'), function (b) {
      b.setAttribute('aria-current', String(b.getAttribute('data-preset') === preset));
    });
  }

  /* =================== THE MODEL (rate, mode, due days, bank) =================== */
  /** The page is written for the commission added on top; an account billed the other way gets the other sentence. */
  function applyMode() {
    var lead = $('of-lead');
    if (lead && !onTop) {
      lead.textContent = VQ.t('The Viaqui commission is withheld from your prices. The money from online sales is collected through Viaqui and is owed to you, and for tickets sold at the desk we send you a monthly invoice for the commission.');
    }
  }
  function setMode(mode) {
    if (!mode) return;
    modeKnown = true;
    onTop = mode === 'added_on_top' || mode === 'on_top';
    applyMode();
  }
  function renderModel() {
    var box = $('of-model');
    if (!box) return;
    if (rate == null) { box.hidden = true; return; }
    // the minimum per ticket comes from the account (/organizer/contract), never from a number typed here
    var v = { rate: F.pct(rate), min: F.money(floor) };
    var first = onTop
      ? (floor > 0 ? VQ.t('Your commission is {rate}, added on top of your prices, minimum {min} per ticket.', v) : VQ.t('Your commission is {rate}, added on top of your prices.', v))
      : (floor > 0 ? VQ.t('Your commission is {rate}, withheld from the ticket price, minimum {min} per ticket.', v) : VQ.t('Your commission is {rate}, withheld from the ticket price.', v));
    set('of-model-t', first + ' ' + VQ.t('The amounts below are the actual ones, calculated by Viaqui for each booking.'));
    box.hidden = false;
  }
  /** /organizer/me does not say whether a bank account is on file, so the note is neutral until something does. */
  function renderBank() {
    var box = $('of-bank-note');
    if (!box) return;
    box.textContent = '';
    box.appendChild(icon('bank'));
    // one sentence per state; {link} marks where the link to the settings goes, so a translation can move it
    var parts = (hasBank === true ? VQ.t('You have a bank account in your profile. That is where we send the money, once payment straight into your account is live. You can change it in {link}.')
      : hasBank === false ? VQ.t('You have no bank account in your profile yet. Fill it in now, so it is ready when payment straight into your account goes live: {link}.')
        : VQ.t('We send the money to the bank account in your profile, once payment straight into your account is live. Check it or fill it in at {link}.')).split('{link}');
    box.appendChild(el('span', { text: parts[0] }));
    box.appendChild(el('a', { href: VQ.url('/organizator/setari#bank'), text: VQ.t('Account & company') }));
    box.appendChild(el('span', { text: parts.slice(1).join('') }));
    box.hidden = false;
  }
  /** The sentence under "to pay": what the desk commission of the period is, and when the invoice for it is due. */
  function renderDue() {
    var pos = ((last && last.by_source && last.by_source.period) || {}).pos || {};
    var v = { bookings: bookings(pos), days: VQ.n(dueDays == null ? 0 : dueDays, 'calendar day', 'calendar days') };
    set('of-d-pos-p', n(pos.commission)
      ? (dueDays == null
        ? VQ.t('The commission for {bookings} at the desk in the chosen period, which you collected yourself. We invoice it once a month.', v)
        : VQ.t('The commission for {bookings} at the desk in the chosen period, which you collected yourself. We invoice it once a month, with payment due in {days}.', v))
      : (dueDays == null
        ? VQ.t('No desk sales in the chosen period, so nothing to invoice for it. The commission on desk takings is invoiced once a month.')
        : VQ.t('No desk sales in the chosen period, so nothing to invoice for it. The commission on desk takings is invoiced once a month, with payment due in {days}.', v)));
  }

  /* =================== DRAW =================== */
  function skeleton() {
    ['online', 'pos', 'com', 'net'].forEach(function (k) {
      var node = $('of-c-' + k);
      node.textContent = '';
      node.appendChild(el('span', { class: 'org-skel of-sk' }));
    });
    ['of-d-pos', 'of-d-online'].forEach(function (id) {
      var node = $(id);
      node.textContent = '';
      node.appendChild(el('span', { class: 'org-skel of-sk' }));
    });
  }
  function draw(d) {
    last = d;
    var t = d.totals || {}, src = (d.by_source && d.by_source.period) || {};
    var on = src.online || {}, pos = src.pos || {};
    if (empty(d)) return;
    // Until /organizer/contract answers, the data says it: with the commission on top, what stays is the whole value.
    if (!modeKnown && n(t.commission) > 0) { onTop = Math.abs(n(t.net) - n(t.value)) < 0.005; applyMode(); renderModel(); }

    set('of-c-online', money(on.value));
    set('of-c-online-p', VQ.t('{bookings} through Viaqui, at your prices.', { bookings: bookings(on) }));
    set('of-c-pos', money(pos.value));
    set('of-c-pos-p', VQ.t('{bookings} at the desk, collected directly by you.', { bookings: bookings(pos) }));
    set('of-c-com', money(t.commission));
    set('of-c-com-p', VQ.t('Of which {amount} for desk sales.', { amount: money(pos.commission) }));
    set('of-c-net', money(t.net));
    set('of-c-net-p', onTop
      ? VQ.t('Your prices, in full: the commission is added on top of them.')
      : VQ.t('What is left after the Viaqui commission is deducted.'));

    set('of-period', VQ.t('For {from} – {to}.', { from: dayLabel(d.from || $('of-from').value), to: dayLabel(d.to || $('of-to').value) }));
    set('of-d-pos', money(pos.commission));
    renderDue();
    set('of-d-online', money(on.net));
    set('of-d-online-p', VQ.t('This is what you are owed from the online sales of the period. The money is collected by Viaqui; automatic payment straight into your account (split payment) is not live yet, so the amount above shows what you are owed, not a transfer already made.'));

    drawMonths(Array.isArray(d.by_month) ? d.by_month : []);
    $('of-live').textContent = VQ.t('Period {from} – {to}: taken online {online}, at the desk {desk}, commission {commission}, you keep {net}.',
      { from: dayLabel(d.from), to: dayLabel(d.to), online: money(on.value), desk: money(pos.value), commission: money(t.commission), net: money(t.net) });
  }
  function drawMonths(list) {
    months = list;
    var tb = $('of-months'), foot = $('of-months-foot');
    tb.textContent = '';
    if (!list.length) {
      tb.appendChild(el('tr', null, el('td', { colspan: 5, class: 've-state', text: VQ.t('No sales in the last 13 months.') })));
      foot.hidden = true;
      $('of-csv').disabled = true;
      return;
    }
    var sum = { online: 0, pos: 0, com: 0, net: 0 };
    list.forEach(function (m) {
      var o = m.online || {}, p = m.pos || {};
      var com = n(o.commission) + n(p.commission), net = n(o.net) + n(p.net);
      sum.online += n(o.value);
      sum.pos += n(p.value);
      sum.com += com;
      sum.net += net;
      tb.appendChild(el('tr', null, [
        el('td', { text: monthLabel(m.month) }),
        el('td', { text: money(o.value) }),
        el('td', { text: money(p.value) }),
        el('td', null, [money(com), el('small', { text: VQ.t('at the desk {amount}', { amount: money(p.commission) }) })]),
        el('td', { text: money(net) }),
      ]));
    });
    set('of-t-online', money(sum.online));
    set('of-t-pos', money(sum.pos));
    set('of-t-com', money(sum.com));
    set('of-t-net', money(sum.net));
    foot.hidden = false;
    $('of-csv').disabled = false;
  }

  /* =================== EMPTY / ERROR =================== */
  function box() {
    var b = $('of-empty');
    b.textContent = '';
    b.classList.remove('is-error');
    $('of-body').hidden = true;
    b.hidden = false;
    return b;
  }
  /** True when this operator has nothing sold at all (for the chosen location): the page has nothing to show. */
  function empty(d) {
    if (n(d.all_time && d.all_time.bookings) > 0) {
      $('of-empty').hidden = true;
      $('of-empty').classList.remove('is-error');
      $('of-body').hidden = false;
      return false;
    }
    var b = box();
    b.appendChild(el('span', { class: 'org-empty-ic' }, icon('wallet')));
    if ($('of-loc').value) {
      b.appendChild(el('b', { text: VQ.t('No sales for the chosen venue') }));
      b.appendChild(el('p', { text: VQ.t('This venue has not sold anything yet. Choose another venue or show them all.') }));
      var all = el('button', { class: 'btn btn-ghost', type: 'button', text: VQ.t('Show all venues') });
      all.addEventListener('click', function () { $('of-loc').value = ''; load(); });
      b.appendChild(el('div', { class: 'of-empty-cta' }, all));
    } else {
      b.appendChild(el('b', { text: VQ.t('You have no sales yet') }));
      b.appendChild(el('p', { text: VQ.t('Here you see what you took online and at the desk, the Viaqui commission and what you keep. They show up here from the first paid booking.') }));
      b.appendChild(el('div', { class: 'of-empty-cta' }, [
        el('a', { class: 'btn btn-primary', href: VQ.url('/organizator/produse'), text: VQ.t('My products') }),
        el('a', { class: 'btn btn-ghost', href: VQ.url('/organizator/rezervari'), text: VQ.t('Bookings') }),
      ]));
    }
    $('of-live').textContent = VQ.t('No sales to show.');
    return true;
  }
  function fail(err) {
    var b = box();
    b.classList.add('is-error');
    b.appendChild(el('span', { class: 'org-empty-ic' }, icon('warning-circle')));
    b.appendChild(el('b', { text: VQ.t('We could not load the balance') }));
    b.appendChild(el('p', { text: errText(err, VQ.t('Check your connection and try again.')) }));
    var retry = el('button', { class: 'btn btn-primary', type: 'button', text: VQ.t('Try again') });
    retry.addEventListener('click', load);
    b.appendChild(el('div', { class: 'of-empty-cta' }, retry));
  }

  /* =================== CSV (built here, from by_month) =================== */
  function dec(v) { return n(v).toFixed(2).replace('.', ','); }
  function cell(v) {
    var s = String(v == null ? '' : v);
    if (/^[=+\-@]/.test(s)) s = "'" + s;
    return /[";\r\n]/.test(s) ? '"' + s.replace(/"/g, '""') + '"' : s;
  }
  function csv() {
    if (!months.length) return;
    // the summary carries no currency of its own today: the site currency (euro) unless the data names one
    var cur = { currency: String((last && (last.currency || (last.totals && last.totals.currency))) || 'EUR') };
    var rows = [[VQ.t('Month'), VQ.t('Taken online ({currency})', cur), VQ.t('Taken at the desk ({currency})', cur), VQ.t('Viaqui commission ({currency})', cur), VQ.t('Desk commission ({currency})', cur), VQ.t('You keep ({currency})', cur)]];
    var sum = { online: 0, pos: 0, com: 0, posCom: 0, net: 0 };
    months.forEach(function (m) {
      var o = m.online || {}, p = m.pos || {};
      var com = n(o.commission) + n(p.commission), net = n(o.net) + n(p.net);
      sum.online += n(o.value);
      sum.pos += n(p.value);
      sum.com += com;
      sum.posCom += n(p.commission);
      sum.net += net;
      rows.push([monthLabel(m.month), dec(o.value), dec(p.value), dec(com), dec(p.commission), dec(net)]);
    });
    rows.push([VQ.t('Total'), dec(sum.online), dec(sum.pos), dec(sum.com), dec(sum.posCom), dec(sum.net)]);
    var body = rows.map(function (r) { return r.map(cell).join(';'); }).join('\r\n');
    var url = URL.createObjectURL(new Blob(['﻿' + body], { type: 'text/csv;charset=utf-8' }));
    var a = el('a', { href: url, download: 'viaqui-monthly-balance-' + today + '.csv' });
    document.body.appendChild(a);
    a.click();
    setTimeout(function () { URL.revokeObjectURL(url); a.remove(); }, 1000);
  }

  /* =================== LOAD =================== */
  function load() {
    var from = $('of-from').value || today, to = $('of-to').value || today;
    if (to < from) { var swap = from; from = to; to = swap; setRange([from, to], null); }
    var mine = ++seq;
    root.classList.add('is-busy');
    $('of-empty').hidden = true;
    $('of-body').hidden = false;
    skeleton();
    O.api(BASE + '/summary?' + qs({ from: from, to: to, location_id: $('of-loc').value })).then(function (r) {
      if (mine !== seq) return;
      root.classList.remove('is-busy');
      draw((r && r.data) || {});
    }, function (e) {
      if (mine !== seq || (e && e.status === 401)) return;
      root.classList.remove('is-busy');
      fail(e);
    });
  }

  /* =================== WIRING =================== */
  $('of-presets').addEventListener('click', function (e) {
    var b = e.target.closest('[data-preset]');
    if (!b) return;
    setRange(PRESETS[b.getAttribute('data-preset')](), b.getAttribute('data-preset'));
    load();
  });
  $('of-form').addEventListener('submit', function (e) { e.preventDefault(); setRange([$('of-from').value, $('of-to').value], null); load(); });
  $('of-loc').addEventListener('change', load);
  $('of-csv').addEventListener('click', csv);

  O.onProfile(function (p) {
    if (!p || typeof p !== 'object') return;
    if (p.commission_mode) setMode(p.commission_mode);
    if (p.commission_rate != null) { rate = n(p.commission_rate); renderModel(); }
    if (typeof p.has_payout_details === 'boolean') { hasBank = p.has_payout_details; renderBank(); }
  });

  O.ready.then(function (ok) {
    if (!ok) return;
    renderBank();
    setRange(PRESETS.month(), 'month');
    O.api('/organizer/contract', { quiet: true }).then(function (r) {
      var c = (r && r.data) || {};
      if (c.commission_mode) setMode(c.commission_mode);
      if (c.commission_rate != null) rate = n(c.commission_rate);
      if (c.invoice_due_days != null) dueDays = Math.max(0, Math.round(n(c.invoice_due_days)));
      if (c.commission_floor != null) floor = n(c.commission_floor);
      renderModel();
      if (last) renderDue();
    }, function () { renderModel(); });
    O.api(BASE + '/locations', { quiet: true }).then(function (r) {
      ((r && r.data && r.data.locations) || []).forEach(function (l) {
        $('of-loc').appendChild(el('option', { value: String(l.id), text: F.flat(l.name) || VQ.t('Venue {id}', { id: l.id }) }));
      });
    }, function () {});
    load();
  });
})();
