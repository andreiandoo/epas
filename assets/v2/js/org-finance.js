/* bilete.online v2: the operator's balance (/organizator/sold). One call, /organizer/activities-module/summary, gives
   every figure: totals and by_source.period for the chosen period (this month by default), by_month for the last
   thirteen months, all_time for the empty state. /organizer/contract adds the commission rate and the invoice due
   days (the shell's /organizer/me carries the rate too, and would carry has_payout_details if core ever sent it — the
   bank note stays neutral until then). bilete.online does not do payouts, so nothing here requests one: the commission
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
    return s.charAt(0).toUpperCase() + s.slice(1); // "septembrie 2026" reads better as a row label capitalised
  }
  function bookings(x) { return F.count(n(x && x.bookings), 'rezervare', 'rezervări'); }
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
      lead.textContent = 'Comisionul bilete.online se reține din prețurile tale. Banii din vânzările online se încasează prin '
        + 'bilete.online și ți se cuvin ție, iar pentru biletele vândute la casă îți trimitem lunar o factură cu comisionul.';
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
    set('of-model-t', 'Comisionul tău e ' + F.pct(rate) + (onTop ? ', adăugat peste prețurile tale' : ', reținut din prețul biletului')
      + (floor > 0 ? ', minimum ' + F.money(floor) + ' pe bilet' : '')
      + '. Sumele de mai jos sunt cele reale, calculate de bilete.online la fiecare rezervare.');
    box.hidden = false;
  }
  /** /organizer/me does not say whether a bank account is on file, so the note is neutral until something does. */
  function renderBank() {
    var box = $('of-bank-note');
    if (!box) return;
    box.textContent = '';
    box.appendChild(icon('bank'));
    box.appendChild(el('span', {
      text: hasBank === true ? 'Ai un cont bancar în profil. Acolo îți trimitem banii, când plata direct în contul tău va fi activă. Îl schimbi la '
        : hasBank === false ? 'Nu ai încă un cont bancar în profil. Completează-l acum, ca să fie gata când plata direct în contul tău va fi activă: '
          : 'Banii ți-i trimitem în contul bancar din profil, când plata direct în contul tău va fi activă. Verifică-l sau completează-l la ',
    }));
    box.appendChild(el('a', { href: '/organizator/setari#bank', text: 'Cont & companie' }));
    box.appendChild(el('span', { text: '.' }));
    box.hidden = false;
  }
  function term() {
    return dueDays == null ? '' : ', cu termen de plată de ' + F.count(dueDays, 'zi calendaristică', 'zile calendaristice');
  }
  /** The sentence under "de plătit": what the desk commission of the period is, and when the invoice for it is due. */
  function renderDue() {
    var pos = ((last && last.by_source && last.by_source.period) || {}).pos || {};
    set('of-d-pos-p', n(pos.commission)
      ? 'Comisionul pentru ' + bookings(pos) + ' de la casă din perioada aleasă, pe care le-ai încasat direct tu. Îl facturăm o dată pe lună' + term() + '.'
      : 'Nicio vânzare la casă în perioada aleasă, deci nimic de facturat pentru ea. Comisionul pe încasările la casă se facturează o dată pe lună' + term() + '.');
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
    set('of-c-online-p', bookings(on) + ' prin bilete.online, la prețurile tale.');
    set('of-c-pos', money(pos.value));
    set('of-c-pos-p', bookings(pos) + ' la casă, încasate direct de tine.');
    set('of-c-com', money(t.commission));
    set('of-c-com-p', 'Din care ' + money(pos.commission) + ' pentru vânzările la casă.');
    set('of-c-net', money(t.net));
    set('of-c-net-p', onTop
      ? 'Prețurile tale, întregi: comisionul îl plătește clientul, peste ele.'
      : 'Ce rămâne după ce se scade comisionul bilete.online.');

    set('of-period', 'Pentru ' + dayLabel(d.from || $('of-from').value) + ' – ' + dayLabel(d.to || $('of-to').value) + '.');
    set('of-d-pos', money(pos.commission));
    renderDue();
    set('of-d-online', money(on.net));
    set('of-d-online-p', 'Atât ți se cuvine din vânzările online ale perioadei. Banii sunt încasați de bilete.online; '
      + 'plata automată direct în contul tău (split payment) nu este încă activă, deci suma de mai sus arată cât ți se cuvine, nu un transfer deja făcut.');

    drawMonths(Array.isArray(d.by_month) ? d.by_month : []);
    $('of-live').textContent = 'Perioada ' + dayLabel(d.from) + ' – ' + dayLabel(d.to) + ': încasat online ' + money(on.value)
      + ', la casă ' + money(pos.value) + ', comision ' + money(t.commission) + ', îți rămâne ' + money(t.net) + '.';
  }
  function drawMonths(list) {
    months = list;
    var tb = $('of-months'), foot = $('of-months-foot');
    tb.textContent = '';
    if (!list.length) {
      tb.appendChild(el('tr', null, el('td', { colspan: 5, class: 've-state', text: 'Nicio vânzare în ultimele 13 luni.' })));
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
        el('td', null, [money(com), el('small', { text: 'la casă ' + money(p.commission) })]),
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
      b.appendChild(el('b', { text: 'Nicio vânzare pentru locația aleasă' }));
      b.appendChild(el('p', { text: 'Locația asta nu a vândut încă nimic. Alege altă locație sau arată-le pe toate.' }));
      var all = el('button', { class: 'btn btn-ghost', type: 'button', text: 'Arată toate locațiile' });
      all.addEventListener('click', function () { $('of-loc').value = ''; load(); });
      b.appendChild(el('div', { class: 'of-empty-cta' }, all));
    } else {
      b.appendChild(el('b', { text: 'Încă nu ai vânzări' }));
      b.appendChild(el('p', { text: 'Aici vezi ce ai încasat online și la casă, comisionul bilete.online și cât îți rămâne. Apar aici de la prima rezervare plătită.' }));
      b.appendChild(el('div', { class: 'of-empty-cta' }, [
        el('a', { class: 'btn btn-primary', href: '/organizator/produse', text: 'Produsele mele' }),
        el('a', { class: 'btn btn-ghost', href: '/organizator/rezervari', text: 'Rezervări' }),
      ]));
    }
    $('of-live').textContent = 'Nicio vânzare de arătat.';
    return true;
  }
  function fail(err) {
    var b = box();
    b.classList.add('is-error');
    b.appendChild(el('span', { class: 'org-empty-ic' }, icon('warning-circle')));
    b.appendChild(el('b', { text: 'Nu am putut încărca soldul' }));
    b.appendChild(el('p', { text: errText(err, 'Verifică conexiunea și încearcă din nou.') }));
    var retry = el('button', { class: 'btn btn-primary', type: 'button', text: 'Reîncearcă' });
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
    var rows = [['Luna', 'Încasat online (lei)', 'Încasat la casă (lei)', 'Comision bilete.online (lei)', 'Comision la casă (lei)', 'Îți rămâne (lei)']];
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
    rows.push(['Total', dec(sum.online), dec(sum.pos), dec(sum.com), dec(sum.posCom), dec(sum.net)]);
    var body = rows.map(function (r) { return r.map(cell).join(';'); }).join('\r\n');
    var url = URL.createObjectURL(new Blob(['﻿' + body], { type: 'text/csv;charset=utf-8' }));
    var a = el('a', { href: url, download: 'sold-lunar-' + today + '.csv' });
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
        $('of-loc').appendChild(el('option', { value: String(l.id), text: F.flat(l.name) || ('Locația ' + l.id) }));
      });
    }, function () {});
    load();
  });
})();
