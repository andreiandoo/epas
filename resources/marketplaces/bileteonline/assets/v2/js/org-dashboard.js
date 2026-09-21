/* bilete.online v2: organizer dashboard (/organizator/panou). Fills in organizer/dashboard.php inside the organizer
   shell (window.BO_ORG from organizer.js). Everything comes from the activities module, through the same endpoint
   /organizator/raport reads, so the two pages show the same numbers:
   - /organizer/activities-module/summary?from=<1st of the month>&to=<today>: the figures since the account started
     (all_time, tickets), the month (totals, by_source.period), the catalogue, the next seven days (arrivals_7_days)
     and the last months (by_month)
   - .../summary?from=&to= for the chosen period: the chart (by_day), its totals and the best-selling products
   - .../bookings/day?date=<today>: who arrives today, per product and start time
   - .../bookings?from=<today>&to=<+30 days>: the bookings that are coming
   Nothing is computed here: the commission has a floor of 1,50 lei per ticket, so only what the API sends is shown.
   Text from the API is always written as text. */
(function () {
  'use strict';
  var O = window.BO_ORG, root = document.getElementById('od');
  if (!O || !root) return;
  var F = O.fmt, el = O.el, icon = O.icon;
  var $ = function (id) { return document.getElementById(id); };
  var SVGNS = 'http://www.w3.org/2000/svg';
  var AM = '/organizer/activities-module';
  var today = F.ymd();
  var monthFrom = today.slice(0, 8) + '01';
  var monthName = F.date(new Date(), { month: 'long' });
  var state = { chart: null, g: null, parts: null, range: { days: 30 }, active: -1, req: 0, width: 0, raf: 0, acc: null, day: null, dayErr: false };

  var STATUS = {
    pending: ['În așteptare', 'is-wait'], paid: ['Plătită', 'is-ok'], confirmed: ['Confirmată', 'is-ok'], checked_in: ['Validată', 'is-ok'],
    no_show: ['Nu s-a prezentat', 'is-bad'], cancelled: ['Anulată', 'is-muted'], expired: ['Expirată', 'is-muted'],
  };

  function cap(s) { return s ? s.charAt(0).toUpperCase() + s.slice(1) : s; }
  function text(id, v) { var n = $(id); if (n) n.textContent = v; }
  function fill(id, kids) {
    var n = $(id);
    if (!n) return null;
    n.textContent = '';
    [].concat(kids).forEach(function (k) { if (k) n.appendChild(typeof k === 'string' ? document.createTextNode(k) : k); });
    return n;
  }
  function dayDiff(a, b) { return Math.round((Date.parse(a + 'T00:00:00Z') - Date.parse(b + 'T00:00:00Z')) / 864e5); }
  function addDays(ymd, n) { var d = new Date(ymd + 'T12:00:00'); d.setDate(d.getDate() + n); return F.ymd(d); }
  function qs(o) {
    return Object.keys(o).filter(function (k) { return o[k] !== '' && o[k] != null; })
      .map(function (k) { return encodeURIComponent(k) + '=' + encodeURIComponent(o[k]); }).join('&');
  }
  /** Big money figures: whole lei from 10 000 up (the exact amount stays in the title), with a smaller " lei". */
  function setBig(id, v) {
    var n = F.toNum(v), exact = F.money(n), shown = Math.abs(n) >= 10000 ? F.num(n) : exact.replace(/ lei$/, '');
    var node = fill(id, [shown, el('small', { text: ' lei' })]);
    if (node) node.title = exact;
  }
  function refocus(container, target) { // a re-render removed the focused control: put the focus somewhere sensible
    if (document.activeElement === document.body || !document.contains(document.activeElement) || container.contains(document.activeElement)) target.focus();
  }
  function tag(status) { var s = STATUS[status] || [String(status || ''), 'is-muted']; return el('span', { class: 'org-tag ' + s[1], text: s[0] }); }
  function dayLabel(ymd, opts) { var d = F.dateOf(ymd); return d ? F.date(d, opts || { weekday: 'short', day: 'numeric', month: 'short' }) : String(ymd || ''); }
  /** "lun., 21 sept." — and "21 sept. – 23 sept." when the visit spans days. The API's long date_label stays as a title. */
  function shortDate(from, to) {
    var a = F.dateOf(from);
    if (!a) return '';
    var year = String(from).slice(0, 4) !== today.slice(0, 4) ? 'numeric' : undefined;
    if (!to || to === from) return F.date(a, { weekday: 'short', day: 'numeric', month: 'short', year: year });
    var b = F.dateOf(to);
    return F.date(a, { day: 'numeric', month: 'short' }) + ' – ' + (b ? F.date(b, { day: 'numeric', month: 'short', year: year }) : String(to));
  }

  /* =================== THE ACCOUNT AND THE MONTH (summary, 1st → today) =================== */
  function renderAllTime(d) {
    var at = d.all_time || {}, tk = d.tickets || {}, views = F.toNum((d.catalogue || {}).views);
    setBig('od-all-value', at.value);
    setBig('od-all-net', at.net);
    text('od-all-tickets', F.num(tk.valid));
    $('od-all-views-box').hidden = !(views > 0);
    text('od-all-views', F.num(views));
  }

  function renderMonth(d) {
    var t = d.totals || {}, value = F.toNum(t.value);
    text('od-month-k', 'Luna aceasta · ' + monthName);
    setBig('od-month-v', value);
    text('od-month-p', value > 0
      ? 'Comision bilete.online: ' + F.money(t.commission) + ' · îți rămân ' + F.money(t.net) + '.'
      : 'Nicio vânzare din 1 ' + monthName + ' până azi.');
  }

  function renderSource(d) {
    var p = (d.by_source && d.by_source.period) || {}, on = p.online || {}, pos = p.pos || {};
    var onV = F.toNum(on.value), posV = F.toNum(pos.value), total = onV + posV, bar = $('od-src-bar');
    if (!(total > 0)) {
      fill('od-src-v', '—');
      bar.hidden = true;
      text('od-src-p', 'Nicio vânzare luna aceasta, nici online, nici la casă.');
      return;
    }
    fill('od-src-v', [F.pct(onV / total * 100, 0), el('small', { text: ' online' })]);
    bar.hidden = false;
    bar.children[0].style.width = (onV / total * 100) + '%';
    bar.children[1].style.width = (posV / total * 100) + '%';
    text('od-src-p', 'Online: ' + F.money(onV) + ' din ' + F.count(on.bookings, 'rezervare', 'rezervări')
      + ' · la casă: ' + F.money(posV) + ' din ' + F.count(pos.bookings, 'rezervare', 'rezervări') + '.');
  }

  function renderCatalogue(d) {
    var c = d.catalogue || {}, live = F.toNum(c.live), products = F.toNum(c.products), pending = F.toNum(c.pending);
    var rest = Math.max(0, products - live - pending), bits = [];
    fill('od-cat-v', [F.num(live), el('small', { text: ' din ' + F.num(products) })]);
    if (pending > 0) bits.push(F.count(pending, 'produs', 'produse') + ' în verificare la noi');
    if (rest > 0) bits.push(F.count(rest, 'produs nepublicat', 'produse nepublicate'));
    text('od-cat-p', products === 0
      ? 'Niciun produs creat încă. Începe cu o locație, apoi adaugă primul produs.'
      : bits.length ? cap(bits.join(' · ')) + '.' : 'Toate produsele tale sunt publicate.');
  }

  function renderConversion(d) {
    var views = F.toNum((d.catalogue || {}).views), bookings = F.toNum((d.all_time || {}).bookings);
    $('od-card-conv').hidden = !(views > 0);
    if (!(views > 0)) return;
    text('od-conv', F.pct(bookings / views * 100));
    text('od-conv-p', F.count(bookings, 'rezervare', 'rezervări') + ' la ' + F.count(views, 'vizualizare', 'vizualizări')
      + ' ale paginilor de produs, de la început.');
  }

  function monthValue(m) { return F.toNum(m.online && m.online.value) + F.toNum(m.pos && m.pos.value); }
  function monthNet(m) { return F.toNum(m.online && m.online.net) + F.toNum(m.pos && m.pos.net); }
  function monthLabel(key) {
    var d = F.dateOf(String(key || '') + '-01');
    return d ? F.date(d, { month: 'long', year: String(key).slice(0, 4) === today.slice(0, 4) ? undefined : 'numeric' }) : String(key || '');
  }
  function renderMonths(d) {
    var rows = (Array.isArray(d.by_month) ? d.by_month : []).filter(function (m) { return m && m.month; }).slice(0, 6);
    var body = $('od-months-body');
    body.textContent = '';
    if (!rows.length) {
      body.appendChild(el('p', { class: 'od-inline', text: 'Nicio lună cu vânzări încă.' }));
      return;
    }
    var max = rows.reduce(function (m, x) { return Math.max(m, monthValue(x)); }, 0);
    body.appendChild(el('ul', { class: 'od-mo-list' }, rows.map(function (m) {
      var value = monthValue(m), bar = el('span', { class: 'od-mo-bar', 'aria-hidden': 'true' }), inner = el('i');
      inner.style.width = (max > 0 ? Math.max(2, Math.round(value / max * 100)) : 2) + '%';
      bar.appendChild(inner);
      return el('li', { class: 'od-mo' + (m.month === today.slice(0, 7) ? ' is-now' : '') }, [
        el('p', { class: 'od-mo-k', text: cap(monthLabel(m.month)) }),
        bar,
        el('p', { class: 'od-mo-v' }, [el('b', { text: F.money(value) }), el('small', { text: 'îți rămân ' + F.money(monthNet(m)) })]),
      ]);
    })));
  }

  function accountFailed() {
    ['od-all-value', 'od-all-net', 'od-all-tickets', 'od-month-v', 'od-src-v', 'od-cat-v'].forEach(function (k) { text(k, '—'); });
    ['od-month-p', 'od-src-p', 'od-cat-p'].forEach(function (k) { text(k, ''); });
    $('od-all-views-box').hidden = true;
    $('od-src-bar').hidden = true;
    $('od-card-conv').hidden = true;
    text('od-week', 'Nu am putut încărca datele panoului.');
    $('od-today-tag').hidden = true;
    fill('od-today-body', el('p', { class: 'od-today-loc', text: 'Nu am putut încărca sosirile de azi.' }));
    fill('od-months-body', el('p', { class: 'od-inline is-error', text: 'Nu am putut încărca lunile.' }));
  }

  function loadAccount() {
    return O.api(AM + '/summary?' + qs({ from: monthFrom, to: today })).then(function (r) {
      var d = r && r.data;
      if (!d || typeof d !== 'object' || Array.isArray(d)) throw { status: 0 };
      $('od-fail').hidden = true;
      state.acc = d;
      renderAllTime(d);
      renderMonth(d);
      renderSource(d);
      renderCatalogue(d);
      renderConversion(d);
      renderMonths(d);
      renderToday();
      drawLead();
      return true;
    }).catch(function (err) {
      if (err && err.status === 401) return false;
      state.acc = null;
      $('od-fail').hidden = false;
      accountFailed();
      return false;
    });
  }

  /* =================== TODAY (bookings/day) =================== */
  function dayBookings(d) {
    var n = 0;
    (d.products || []).forEach(function (p) {
      (p.groups || []).forEach(function (g) { n += (g.bookings || []).length; });
    });
    return n;
  }
  /**
   * How many people are expected today. The day's own answer counts everyone booked for today, validated ones
   * included; arrivals_7_days.today counts only the ones still to be validated, so it is the fallback.
   */
  function todayPersons() {
    if (state.day && !state.dayErr) return F.toNum(state.day.persons);
    return F.toNum((state.acc && state.acc.arrivals_7_days && state.acc.arrivals_7_days.today) || 0);
  }
  /** The day's arrivals as lines: {time, title, persons}, earliest first. */
  function dayLines(d) {
    var out = [];
    (d.products || []).forEach(function (p) {
      (p.groups || []).forEach(function (g) {
        out.push({ time: g.time === 'toată ziua' ? 'Toată ziua' : String(g.time || ''), title: F.flat(p.title) || 'Produs', persons: F.toNum(g.persons) });
      });
    });
    return out.sort(function (a, b) { return a.time < b.time ? -1 : a.time > b.time ? 1 : 0; });
  }

  /** True once the day's own answer is in, one way or the other: before that the card must not claim "no arrivals". */
  function daySettled() { return state.day !== null || state.dayErr; }

  function renderToday() {
    var body = $('od-today-body'), tagNode = $('od-today-tag'), acc = state.acc, d = state.day;
    if (!acc) return; // the account decides what this card says; until then the skeleton stays
    var arrivals = acc.arrivals_7_days || {}, week = F.toNum(arrivals.persons);
    var sold = F.toNum((acc.all_time && acc.all_time.bookings) || 0);
    if (sold > 0 && !daySettled()) return;
    var persons = todayPersons();
    body.textContent = '';
    tagNode.textContent = cap(F.date(new Date(), { weekday: 'long', day: 'numeric', month: 'short' }));
    tagNode.hidden = false;

    if (sold <= 0) {
      body.appendChild(el('p', { class: 'od-today-t', text: 'Încă nicio vânzare' }));
      body.appendChild(el('p', { class: 'od-today-loc', text: 'Pașii tăi de pornire, chiar sub panou, îți arată ce mai ai de făcut: locația, produsele, aprobarea și publicarea lor.' }));
      body.appendChild(el('div', { class: 'od-today-cta' }, [
        el('a', { class: 'btn btn-primary', href: '/organizator/produse?nou=1' }, [icon('plus'), 'Adaugă un produs']),
        el('a', { class: 'btn btn-ghost', href: '/organizator/locatii', text: 'Locațiile mele' }),
      ]));
      return;
    }

    if (persons > 0) {
      body.appendChild(el('p', { class: 'od-today-t', text: F.count(persons, 'persoană', 'persoane') }));
      var bookings = d && !state.dayErr ? dayBookings(d) : 0;
      body.appendChild(el('p', { class: 'od-today-loc', text: bookings > 0 ? 'în ' + F.count(bookings, 'rezervare', 'rezervări') : 'așteptate azi' }));
    } else {
      body.appendChild(el('p', { class: 'od-today-t', text: 'Nicio sosire azi' }));
      body.appendChild(el('p', { class: 'od-today-loc', text: week > 0 ? 'Dar au cumpărat deja pentru zilele care vin.' : 'Nicio rezervare nici pentru următoarele 7 zile.' }));
    }

    var lines = persons > 0 && d && !state.dayErr ? dayLines(d) : [];
    if (lines.length) {
      body.appendChild(el('ul', { class: 'od-today-list' }, lines.slice(0, 4).map(function (x) {
        return el('li', null, [
          el('b', { text: x.time }),
          el('span', { text: x.title }),
          el('em', { text: F.num(x.persons) + ' pers.' }),
        ]);
      }).concat(lines.length > 4 ? [el('li', { class: 'is-more', text: '+ încă ' + F.count(lines.length - 4, 'grup', 'grupuri') })] : [])));
    } else if (state.dayErr) {
      body.appendChild(el('p', { class: 'od-today-note', text: 'Nu am putut încărca programul zilei.' }));
    }

    body.appendChild(el('dl', { class: 'od-today-stats' }, [
      el('div', null, [el('dt', { text: 'Sosiri azi' }), el('dd', { text: F.num(persons) })]),
      el('div', null, [el('dt', { text: 'Următoarele 7 zile' }), el('dd', { text: F.num(week) })]),
    ]));
    body.appendChild(el('div', { class: 'od-today-cta' }, [
      el('a', { class: 'btn btn-primary', href: '/organizator/rezervari' }, [icon('list'), 'Vezi rezervările']),
    ]));
  }

  function drawLead() {
    var acc = state.acc;
    if (!acc) return;
    var at = acc.all_time || {}, arrivals = acc.arrivals_7_days || {};
    if (!(F.toNum(at.bookings) > 0)) {
      text('od-week', 'Încă nicio vânzare pe bilete.online. Pașii tăi de pornire, chiar sub panou, îți arată exact ce mai ai de făcut până la prima rezervare.');
      return;
    }
    if (!daySettled()) return; // don't announce "nicio sosire" while the day is still on its way
    var persons = todayPersons(), week = F.toNum(arrivals.persons);
    var bookings = persons > 0 && state.day && !state.dayErr ? dayBookings(state.day) : 0;
    var first = persons > 0
      ? 'Azi aștepți ' + F.count(persons, 'persoană', 'persoane') + (bookings > 0 ? ', în ' + F.count(bookings, 'rezervare', 'rezervări') : '') + '.'
      : 'Azi nu ai nicio sosire programată.';
    var rest = week > 0
      ? ' Următoarele 7 zile, azi inclus: ' + F.count(week, 'persoană', 'persoane') + '.'
      : ' Nici în următoarele 7 zile nu ai rezervări.';
    text('od-week', first + rest);
  }

  function loadToday() {
    return O.api(AM + '/bookings/day?' + qs({ date: today }), { quiet: true }).then(function (r) {
      state.day = (r && r.data) || {};
      state.dayErr = false;
    }, function () {
      state.day = null;
      state.dayErr = true;
    }).then(function () { renderToday(); drawLead(); });
  }

  /* =================== THE BOOKINGS THAT ARE COMING =================== */
  function renderBookings(d) {
    var body = $('od-bk-body'), rows = Array.isArray(d.bookings) ? d.bookings : [], total = F.toNum((d.pagination || {}).total);
    body.textContent = '';
    text('od-bk-p', total > 0
      ? (total > rows.length ? 'Primele ' + F.num(rows.length) + ' din ' + F.count(total, 'rezervare', 'rezervări') : cap(F.count(total, 'rezervare', 'rezervări')))
        + ' din următoarele 30 de zile, după ziua vizitei.'
      : 'Următoarele 30 de zile, după ziua vizitei.');
    if (!rows.length) {
      body.appendChild(el('div', { class: 'org-empty' }, [
        el('span', { class: 'org-empty-ic' }, icon('calendar-blank')),
        el('b', { text: 'Nicio rezervare în următoarele 30 de zile' }),
        el('p', { text: 'Rezervările apar aici de îndată ce cineva cumpără, de pe bilete.online, din widget-ul tău sau la casă.' }),
        el('div', { class: 'od-empty-cta' }, [
          el('a', { class: 'btn btn-primary', href: '/organizator/produse?nou=1' }, [icon('plus'), 'Adaugă un produs']),
          el('a', { class: 'btn btn-ghost', href: '/organizator/rezervari', text: 'Toate rezervările' }),
        ]),
      ]));
      return;
    }
    body.appendChild(el('ul', { class: 'od-bk-list' }, rows.map(function (b) {
      var meta = [b.location, b.variant, b.package ? 'din „' + b.package + '”' : null].filter(Boolean).join(' · ');
      var when = [shortDate(b.date, b.end_date), b.time_label].filter(Boolean).join(' · ');
      return el('li', { class: 'od-bk' }, [
        el('div', { class: 'od-bk-main' }, [
          when ? el('p', { class: 'od-bk-when' }, el('time', { datetime: b.date || null, title: b.date_label || null, text: when })) : null,
          el('h3', { text: F.flat(b.title) || 'Produs' }),
          meta ? el('p', { class: 'od-bk-meta', text: meta }) : null,
          el('p', { class: 'od-bk-who', text: F.count(b.quantity, 'persoană', 'persoane') }),
        ]),
        el('div', { class: 'od-bk-side' }, [el('b', { text: F.money(b.value) }), tag(b.status)]),
      ]);
    })));
  }
  function loadBookings() {
    var body = $('od-bk-body');
    return O.api(AM + '/bookings?' + qs({ from: today, to: addDays(today, 30), per_page: 6 })).then(function (r) {
      renderBookings((r && r.data) || {});
    }).catch(function (err) {
      if (err && err.status === 401) return;
      var hadFocus = body.contains(document.activeElement);
      body.textContent = '';
      text('od-bk-p', '');
      var retry = el('button', { class: 'od-link-btn', type: 'button', text: 'Reîncearcă' });
      retry.addEventListener('click', function () {
        retry.disabled = true;
        retry.textContent = 'Se încarcă…';
        loadBookings().then(function () { refocus(body, $('od-bk-h')); });
      });
      body.appendChild(el('p', { class: 'od-inline is-error' }, ['Nu am putut încărca rezervările. ', retry]));
      if (hadFocus) retry.focus();
    });
  }

  /* =================== THE BEST-SELLING PRODUCTS (by_product of the period) =================== */
  function renderTop(d) {
    var body = $('od-top-body'), rows = (Array.isArray(d.by_product) ? d.by_product : []).slice(0, 6);
    body.textContent = '';
    text('od-top-p', periodLabel(state.range) + ', după vânzări.');
    if (!rows.length) {
      body.appendChild(el('p', { class: 'od-inline', text: 'Nimic vândut în perioada aleasă.' }));
      return;
    }
    var max = rows.reduce(function (m, x) { return Math.max(m, F.toNum(x.value)); }, 0);
    body.appendChild(el('ol', { class: 'od-top-list' }, rows.map(function (x, i) {
      var value = F.toNum(x.value), bar = el('span', { class: 'od-top-bar', 'aria-hidden': 'true' }), inner = el('i');
      inner.style.width = (max > 0 ? Math.max(2, Math.round(value / max * 100)) : 2) + '%';
      bar.appendChild(inner);
      var title = F.flat(x.title) || 'Produs';
      return el('li', { class: 'od-tp' }, [
        el('span', { class: 'od-tp-n', 'aria-hidden': 'true', text: String(i + 1) }),
        el('div', { class: 'od-tp-main' }, [
          el('h3', null, x.product_id != null
            ? el('a', { href: '/organizator/produse?id=' + encodeURIComponent(x.product_id), text: title })
            : document.createTextNode(title)),
          el('p', { class: 'od-tp-meta', text: F.count(x.bookings, 'rezervare', 'rezervări') + ' · ' + F.count(x.persons, 'persoană', 'persoane') }),
          bar,
        ]),
        el('div', { class: 'od-tp-v' }, [el('b', { text: F.money(value) }), el('small', { text: 'îți rămân ' + F.money(x.net) })]),
      ]);
    })));
  }
  function topFailed() {
    text('od-top-p', '');
    fill('od-top-body', el('p', { class: 'od-inline is-error', text: 'Nu am putut încărca produsele.' }));
  }

  /* =================== CHART (summary by_day of the period) =================== */
  function niceNum(x) {
    if (!(x > 0)) return 1;
    var e = Math.pow(10, Math.floor(Math.log10(x))), f = x / e;
    return (f <= 1 ? 1 : f <= 2 ? 2 : f <= 2.5 ? 2.5 : f <= 5 ? 5 : 10) * e;
  }
  function valScale(min, max) { // four equal steps that cover [min, max] and include 0
    var step = niceNum((max - min) / 4);
    for (var k = 0; k < 8; k++) {
      var lo = Math.floor(min / step) * step;
      if (lo + 4 * step >= max - 1e-9) return { lo: lo, hi: lo + 4 * step };
      step = niceNum(step * 1.001);
    }
    return { lo: Math.floor(min / step) * step, hi: Math.floor(min / step) * step + 4 * step };
  }
  var compact = new Intl.NumberFormat('ro-RO', { notation: 'compact', maximumFractionDigits: 1 });
  function svgNode(tag_, attrs, txt) {
    var n = document.createElementNS(SVGNS, tag_);
    Object.keys(attrs).forEach(function (k) { n.setAttribute(k, attrs[k]); });
    if (txt != null) n.textContent = txt;
    return n;
  }
  function periodLabel(range) {
    if (range.days) return 'Ultimele ' + F.count(range.days, 'zi', 'zile');
    var a = F.dateOf(range.from), b = F.dateOf(range.to);
    if (!a || !b) return '';
    if (range.from === range.to) return F.date(a, { day: 'numeric', month: 'long', year: 'numeric' });
    return F.date(a, { day: 'numeric', month: 'short', year: range.from.slice(0, 4) === range.to.slice(0, 4) ? undefined : 'numeric' })
      + ' – ' + F.date(b, { day: 'numeric', month: 'short', year: 'numeric' });
  }
  /** Every day between from and to, the ones without a sale too. */
  function series(d) {
    var by = {}, out = [], cur = d.from, end = d.to, guard = 0;
    (d.by_day || []).forEach(function (x) { if (x && x.date) by[x.date] = x; });
    if (!cur || !end) return out;
    while (cur <= end && guard++ < 400) {
      var x = by[cur] || {};
      out.push({ date: cur, value: F.toNum(x.value), bookings: F.toNum(x.bookings), net: F.toNum(x.net) });
      cur = addDays(cur, 1);
    }
    return out;
  }

  function drawChart() {
    var plot = $('od-plot'), old = plot.querySelector('svg');
    if (old) old.remove();
    state.parts = null;
    var days = state.chart;
    if (!days || !days.length) { state.g = null; return; }
    var W = Math.max(260, Math.floor(plot.clientWidth)), narrow = W < 560, H = narrow ? 220 : 280;
    var n = days.length;
    var val = days.map(function (x) { return x.value; }), bk = days.map(function (x) { return x.bookings; });
    var padL = narrow ? 42 : 58, padR = narrow ? 30 : 40, padT = 14, padB = 34;
    var vs = valScale(Math.min(0, Math.min.apply(null, val)), Math.max(0, Math.max.apply(null, val)));
    var bMax = Math.max(1, Math.ceil(niceNum(Math.max.apply(null, bk.concat([0])) / 4))) * 4;
    var g = state.g = { W: W, H: H, n: n, val: val, bk: bk, padL: padL, padR: padR, padT: padT, iw: W - padL - padR, ih: H - padT - padB };
    state.width = W;
    var y = function (v) { return padT + g.ih * (vs.hi - v) / (vs.hi - vs.lo); };
    var yb = function (v) { return padT + g.ih * (1 - v / bMax); };
    var bw = g.iw / n, barW = Math.max(1, Math.min(26, bw * 0.62));
    var svg = svgNode('svg', { class: 'od-svg', width: W, height: H, viewBox: '0 0 ' + W + ' ' + H, 'aria-hidden': 'true', focusable: 'false' });

    for (var i = 0; i <= 4; i++) { // grid: the money on the left, the bookings on the right, same lines
      var v = vs.lo + (vs.hi - vs.lo) * i / 4, yy = Math.round(y(v)) + 0.5;
      svg.appendChild(svgNode('line', { class: 'od-gl' + (Math.abs(v) < 1e-9 ? ' is-zero' : ''), x1: padL, x2: W - padR, y1: yy, y2: yy }));
      svg.appendChild(svgNode('text', { class: 'od-ax', x: padL - 8, y: yy + 4, 'text-anchor': 'end' }, compact.format(v)));
      svg.appendChild(svgNode('text', { class: 'od-ax is-r', x: W - padR + 8, y: yy + 4, 'text-anchor': 'start' }, F.num(bMax * i / 4)));
    }
    var band = svgNode('rect', { class: 'od-band', x: padL, y: padT, width: bw, height: g.ih, visibility: 'hidden' });
    svg.appendChild(band);
    var zero = y(0), bars = val.map(function (value, k) {
      if (!value) return null;
      var bar = svgNode('rect', {
        class: 'od-bar' + (value < 0 ? ' is-neg' : ''), x: (padL + k * bw + (bw - barW) / 2).toFixed(2),
        y: Math.min(y(value), zero).toFixed(2), width: barW.toFixed(2),
        height: Math.max(1, Math.abs(zero - y(value))).toFixed(2), rx: Math.min(4, barW / 3).toFixed(2),
      });
      svg.appendChild(bar);
      return bar;
    });
    var pts = bk.map(function (value, k) { return [padL + (k + 0.5) * bw, yb(value)]; });
    if (bk.some(Boolean) && n > 1) {
      var line = pts.map(function (p, k) { return (k ? 'L' : 'M') + p[0].toFixed(1) + ' ' + p[1].toFixed(1); }).join('');
      svg.appendChild(svgNode('path', { class: 'od-area', d: line + 'L' + pts[n - 1][0].toFixed(1) + ' ' + (padT + g.ih) + 'L' + pts[0][0].toFixed(1) + ' ' + (padT + g.ih) + 'Z' }));
      svg.appendChild(svgNode('path', { class: 'od-line', d: line }));
    }
    var slots = Math.min(n, narrow ? 4 : 7), seen = {};
    for (var s = 0; s < slots; s++) {
      var idx = slots === 1 ? 0 : Math.round(s * (n - 1) / (slots - 1));
      if (seen[idx]) continue;
      seen[idx] = true;
      var anchor = slots === 1 ? 'middle' : s === 0 ? 'start' : s === slots - 1 ? 'end' : 'middle';
      var lx = anchor === 'start' ? padL : anchor === 'end' ? W - padR : pts[idx][0];
      svg.appendChild(svgNode('text', { class: 'od-ax', x: lx.toFixed(1), y: H - 6, 'text-anchor': anchor }, dayLabel(days[idx].date, { day: 'numeric', month: 'short' })));
    }
    var dot = svgNode('circle', { class: 'od-pt', cx: 0, cy: 0, r: 4.5, visibility: 'hidden' });
    svg.appendChild(dot);
    svg.appendChild(svgNode('rect', { class: 'od-hit', x: padL, y: 0, width: g.iw, height: H }));
    plot.insertBefore(svg, plot.firstChild);
    state.parts = { band: band, dot: dot, bars: bars, pts: pts, bw: bw };
    $('od-plot-msg').hidden = val.some(Boolean) || bk.some(Boolean);
    if (state.active >= 0 && state.active < n) show(state.active, false);
    else hide();
  }

  function show(i, announce) {
    var days = state.chart, g = state.g, P = state.parts;
    if (!days || !g || !P || i < 0 || i >= g.n) return;
    state.active = i;
    P.band.setAttribute('x', (g.padL + i * P.bw).toFixed(2));
    P.band.setAttribute('visibility', 'visible');
    P.bars.forEach(function (b, k) { if (b) b.classList.toggle('is-on', k === i); });
    P.dot.setAttribute('cx', P.pts[i][0].toFixed(1));
    P.dot.setAttribute('cy', P.pts[i][1].toFixed(1));
    P.dot.setAttribute('visibility', 'visible');
    var row = days[i], day = F.dateOf(row.date);
    var title = day ? cap(F.date(day, { weekday: 'long', day: 'numeric', month: 'long', year: 'numeric' })) : row.date;
    var tip = $('od-tip');
    tip.textContent = '';
    tip.appendChild(el('b', { text: title }));
    tip.appendChild(el('dl', null, [
      el('dt', { text: 'Vânzări' }), el('dd', { text: F.money(row.value) }),
      el('dt', { text: 'Rezervări' }), el('dd', { text: F.num(row.bookings) }),
      el('dt', { text: 'Îți rămân' }), el('dd', { text: F.money(row.net) }),
    ]));
    tip.hidden = false;
    var cx = P.pts[i][0], tw = tip.offsetWidth, left = cx + 16;
    if (left + tw > g.W - 2) left = cx - 16 - tw;
    tip.style.left = Math.max(0, Math.min(left, g.W - tw)) + 'px';
    if (announce) {
      text('od-plot-live', title + ': vânzări ' + F.money(row.value) + ', ' + F.count(row.bookings, 'rezervare', 'rezervări') + ', îți rămân ' + F.money(row.net) + '.');
    }
  }
  function hide() {
    state.active = -1;
    var P = state.parts;
    if (P) {
      P.band.setAttribute('visibility', 'hidden');
      P.dot.setAttribute('visibility', 'hidden');
      P.bars.forEach(function (b) { if (b) b.classList.remove('is-on'); });
    }
    $('od-tip').hidden = true;
  }
  function lastWithData() {
    var g = state.g;
    for (var i = g.n - 1; i >= 0; i--) if (g.val[i] || g.bk[i]) return i;
    return g.n - 1;
  }
  function renderTotals(d) {
    var t = d.totals || {};
    text('od-t-val', F.money(t.value));
    text('od-t-bk', F.num(t.bookings));
    text('od-t-pers', F.num(t.persons));
    text('od-t-com', F.money(t.commission));
    text('od-t-net', F.money(t.net));
    $('od-plot').setAttribute('aria-label', 'Grafic vânzări, ' + $('od-period').textContent + ': '
      + F.money(t.value) + ', ' + F.count(t.bookings, 'rezervare', 'rezervări') + ', îți rămân ' + F.money(t.net));
  }

  function rangeDates(range) {
    return range.days ? { from: addDays(today, -(range.days - 1)), to: today } : { from: range.from, to: range.to };
  }
  function loadChart(range) {
    var id = ++state.req, plot = $('od-plot'), dates = rangeDates(range);
    state.range = range;
    text('od-period', periodLabel(range));
    plot.setAttribute('aria-busy', 'true');
    $('od-plot-err').hidden = true;
    return O.api(AM + '/summary?' + qs(dates)).then(function (r) {
      var d = r && r.data;
      if (!d || typeof d !== 'object' || Array.isArray(d)) throw { status: 0 };
      if (id !== state.req) return;
      if (!range.days && d.from && d.to) text('od-period', periodLabel({ from: d.from, to: d.to })); // the core may shorten the range
      state.chart = series(d);
      state.active = -1;
      drawChart();
      renderTotals(d);
      renderTop(d);
    }).catch(function (err) {
      if (err && err.status === 401) return;
      if (id !== state.req) return;
      state.chart = null;
      drawChart();
      $('od-tip').hidden = true;
      $('od-plot-msg').hidden = true;
      $('od-plot-err').hidden = false;
      ['od-t-val', 'od-t-bk', 'od-t-pers', 'od-t-com', 'od-t-net'].forEach(function (k) { text(k, '—'); });
      topFailed();
    }).then(function () { if (id === state.req) plot.setAttribute('aria-busy', 'false'); });
  }

  /* =================== CONTROLS =================== */
  var plot = $('od-plot'), periods = [].slice.call(root.querySelectorAll('.od-period')), custom = $('od-custom'), form = $('od-range');
  function press(btn) { periods.forEach(function (b) { b.setAttribute('aria-pressed', String(b === btn)); }); }
  periods.forEach(function (btn) {
    btn.addEventListener('click', function () {
      if (btn === custom) {
        var open = form.hidden;
        form.hidden = !open;
        custom.setAttribute('aria-expanded', String(open));
        if (open) {
          var f = $('od-from'), t = $('od-to');
          f.max = t.max = today;
          if (!t.value) t.value = today;
          if (!f.value) f.value = addDays(today, -29);
          f.focus();
        }
        return;
      }
      form.hidden = true;
      custom.setAttribute('aria-expanded', 'false');
      text('od-range-err', '');
      var days = +btn.getAttribute('data-days');
      if (btn.getAttribute('aria-pressed') === 'true' && state.chart && state.range.days === days) return;
      press(btn);
      loadChart({ days: days });
    });
  });
  form.addEventListener('submit', function (e) {
    e.preventDefault();
    var f = $('od-from'), t = $('od-to'), from = f.value, to = t.value, bad = null, msg = '';
    f.removeAttribute('aria-invalid');
    t.removeAttribute('aria-invalid');
    if (!from || !to) { msg = 'Alege ambele date.'; bad = from ? t : f; }
    else if (from > to) { msg = 'Data de început trebuie să fie înainte de data de sfârșit.'; bad = f; }
    else if (to > today) { msg = 'Data de sfârșit poate fi cel mult azi.'; bad = t; }
    else if (dayDiff(to, from) + 1 > 365) { msg = 'Alege o perioadă de cel mult 365 de zile.'; bad = f; }
    text('od-range-err', msg);
    if (bad) { bad.setAttribute('aria-invalid', 'true'); bad.focus(); return; }
    press(custom);
    loadChart({ from: from, to: to });
  });
  $('od-plot-retry').addEventListener('click', function () { loadChart(state.range); plot.focus(); });

  function indexAt(clientX) {
    var g = state.g, x = clientX - plot.getBoundingClientRect().left - g.padL;
    return Math.max(0, Math.min(g.n - 1, Math.floor(x / (g.iw / g.n))));
  }
  plot.addEventListener('pointermove', function (e) {
    if (!state.g || !state.parts) return;
    var x = e.clientX - plot.getBoundingClientRect().left;
    if (x < state.g.padL - 4 || x > state.g.W - state.g.padR + 4) { if (document.activeElement !== plot) hide(); return; }
    show(indexAt(e.clientX), false);
  });
  plot.addEventListener('pointerdown', function (e) { if (state.g && state.parts && e.pointerType !== 'mouse') show(indexAt(e.clientX), false); });
  plot.addEventListener('pointerleave', function (e) { if (e.pointerType === 'mouse' && document.activeElement !== plot) hide(); });
  plot.addEventListener('keydown', function (e) {
    if (!state.g || !state.parts) return;
    var n = state.g.n, i = state.active;
    if (e.key === 'ArrowRight' || e.key === 'ArrowLeft') i = i < 0 ? lastWithData() : Math.max(0, Math.min(n - 1, i + (e.key === 'ArrowRight' ? 1 : -1)));
    else if (e.key === 'Home') i = 0;
    else if (e.key === 'End') i = n - 1;
    else if (e.key === 'Escape') { if (state.active >= 0) { e.preventDefault(); e.stopPropagation(); hide(); } return; }
    else return;
    e.preventDefault();
    show(i, true);
  });
  plot.addEventListener('blur', function () { hide(); });
  if ('ResizeObserver' in window) {
    new ResizeObserver(function () {
      if (!state.chart || Math.floor(plot.clientWidth) === state.width) return;
      cancelAnimationFrame(state.raf);
      state.raf = requestAnimationFrame(drawChart);
    }).observe(plot);
  }

  $('od-retry').addEventListener('click', function () {
    var btn = this;
    btn.disabled = true;
    btn.textContent = 'Se încarcă…';
    Promise.all([loadAccount(), loadToday(), loadBookings(), state.chart ? null : loadChart(state.range)]).then(function (res) {
      btn.disabled = false;
      btn.textContent = 'Reîncearcă';
      if (res[0]) $('od-h').focus();
    });
  });

  /* =================== START =================== */
  O.ready.then(function (ok) {
    if (!ok) return;
    text('od-kicker', 'Panou operator · ' + cap(F.date(new Date(), { month: 'long', year: 'numeric' })));
    O.onProfile(function (o) {
      var who = String(o.contact_name || '').trim().split(/\s+/)[0] || String(o.representative_first_name || '').trim() || String(o.name || '').trim();
      text('od-name', who ? ', ' + who : '');
      if (typeof o.has_payout_details === 'boolean') $('od-alert').hidden = o.has_payout_details;
    });
    loadAccount();
    loadToday();
    loadChart({ days: 30 });
    loadBookings();
  });
})();
