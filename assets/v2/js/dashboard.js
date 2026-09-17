/* bilete.online v2: customer dashboard (/cont). One request (GET /customer/dashboard-bundle) fills every section. If it
   fails for any reason other than an expired session, the page asks the separate endpoints instead. Without a customer
   session, or on a 401, it shows the login prompt. Text from the API is always written as text, and links from the API
   must be site paths or http(s).
   Upcoming tickets arrive as upcoming_events[] with the details under `event` (the old page read other keys, so its
   list and next-ticket card were always empty). The old fallback also asked /activities and /customer/gift-cards,
   which api.js has no proxy action for; recommendations now come from /customer/recommendations, the bundle's source. */
(function () {
  'use strict';
  var $ = function (id) { return document.getElementById(id); };
  if (!$('db-content') || !window.BO_ACCOUNT) return;
  var account = window.BO_ACCOUNT;

  var MONTHS = ['ianuarie', 'februarie', 'martie', 'aprilie', 'mai', 'iunie', 'iulie', 'august', 'septembrie', 'octombrie', 'noiembrie', 'decembrie'];
  var WEEKDAYS = ['duminică', 'luni', 'marți', 'miercuri', 'joi', 'vineri', 'sâmbătă'];
  var num = new Intl.NumberFormat('ro-RO');
  var whole = new Intl.NumberFormat('ro-RO', { maximumFractionDigits: 0 });
  var cents = new Intl.NumberFormat('ro-RO', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
  var PLACEHOLDER = 'data:image/svg+xml;utf8,' + encodeURIComponent('<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 160 120"><rect width="160" height="120" fill="#E6F4EC"/><path d="M58 44h44a6 6 0 0 1 6 6v6a8 8 0 0 0 0 16v6a6 6 0 0 1-6 6H58a6 6 0 0 1-6-6v-6a8 8 0 0 0 0-16v-6a6 6 0 0 1 6-6z" fill="none" stroke="#1B7F4E" stroke-width="4" stroke-linejoin="round"/></svg>');
  var pointsPerLei = 100, next = null, refReady = false;

  // ---------- helpers ----------
  function el(tag, cls, text) { var n = document.createElement(tag); if (cls) n.className = cls; if (text != null) n.textContent = text; return n; }
  function ic(name) {
    var ns = 'http://www.w3.org/2000/svg', svg = document.createElementNS(ns, 'svg'), use = document.createElementNS(ns, 'use');
    svg.setAttribute('class', 'ic'); svg.setAttribute('aria-hidden', 'true'); use.setAttribute('href', '#i-' + name); svg.appendChild(use);
    return svg;
  }
  function txt(v) {
    if (v && typeof v === 'object') v = v.ro || v.en || Object.keys(v).map(function (k) { return v[k]; })[0];
    return v == null ? '' : String(v);
  }
  function safeUrl(u, fallback) { u = typeof u === 'string' ? u.trim() : ''; return /^(\/(?![\/\\])|https?:\/\/)/i.test(u) ? u : fallback; }
  function img(src) {
    var i = el('img');
    i.alt = ''; i.loading = 'lazy'; i.decoding = 'async';
    i.addEventListener('error', function () { if (i.getAttribute('src') !== PLACEHOLDER) i.src = PLACEHOLDER; });
    i.src = safeUrl(src, PLACEHOLDER);
    return i;
  }
  function show(id, on) { $(id).hidden = !on; }
  function count(n) { n = Number(n); return isFinite(n) && n > 0 ? Math.floor(n) : 0; }
  function toLei(points) { return whole.format(Math.floor(count(points) / (pointsPerLei || 100))) + ' lei'; }
  function money(n) { n = Number(n) || 0; return (Math.round(n * 100) % 100 ? cents : whole).format(n) + ' lei'; }
  function plural(n, one, many) {
    n = count(n);
    if (n === 1) return '1 ' + one;
    var r = n % 100;
    return num.format(n) + (n && (r === 0 || r >= 20) ? ' de ' : ' ') + many;
  }
  function longDate(d) { return d ? d.getDate() + ' ' + MONTHS[d.getMonth()] + ' ' + d.getFullYear() : ''; }
  function shortDate(d) { return d.getDate() + ' ' + MONTHS[d.getMonth()].slice(0, 3) + ' ' + d.getFullYear(); }
  function relTime(iso) {
    var d = new Date(iso);
    if (!iso || isNaN(d.getTime())) return '';
    var s = Math.max(0, (Date.now() - d.getTime()) / 1000);
    if (s < 3600) return 'acum ' + Math.max(1, Math.floor(s / 60)) + ' min';
    if (s < 86400) return 'acum ' + plural(Math.floor(s / 3600), 'oră', 'ore');
    if (s < 86400 * 30) return 'acum ' + plural(Math.floor(s / 86400), 'zi', 'zile');
    return 'pe ' + longDate(d);
  }
  function guard() { show('db-content', false); show('db-guard', true); account.toLogin(); } // the message shows only while the login page loads

  // ---------- upcoming tickets ----------
  function upcomingList(u) {
    if (Array.isArray(u)) return u;
    if (u && Array.isArray(u.upcoming_events)) return u.upcoming_events;
    if (u && Array.isArray(u.events)) return u.events;
    return [];
  }
  function normUpcoming(raw) {
    raw = raw && typeof raw === 'object' ? raw : {};
    var ev = raw.event && typeof raw.event === 'object' ? raw.event : raw;
    var d = new Date(ev.date || ev.starts_at || ev.start_date || '');
    var when = isNaN(d.getTime()) ? null : d;
    var venue = ev.venue && typeof ev.venue === 'object' ? ev.venue : null;
    return {
      title: txt(ev.name || ev.title) || 'Activitate',
      venue: venue ? txt(venue.name) : txt(ev.venue || ev.location),
      city: txt(ev.city || (venue && venue.city)),
      date: when,
      time: txt(ev.time) || (when ? String(when.getHours()).padStart(2, '0') + ':' + String(when.getMinutes()).padStart(2, '0') : ''),
      image: ev.image || ev.image_url || ev.cover_image_url || '',
      tickets: count(raw.tickets_count || ev.tickets_count) || 1,
      order: txt(raw.order_number || raw.order_id),
      id: txt(ev.id || ev.slug)
    };
  }

  // ---------- calendar (.ics), made by the account shell ----------
  function downloadIcs(t) {
    if (!t || !t.date) return;
    account.calendar([{ title: t.title, date: t.date, venue: t.venue, city: t.city, uid: 'bilet-' + (t.order || 'x') + '-' + (t.id || t.date.getTime()) }], t.title);
  }
  $('db-next-cal').addEventListener('click', function () { downloadIcs(next); });

  // ---------- renderers ----------
  function renderFirstName(u) {
    var first = u ? txt(u.first_name) || txt(u.name).split(' ')[0] : '';
    if (first) $('db-first').textContent = first;
  }
  function renderTasks(pc) {
    var fields = (pc && pc.fields) || {};
    $('db-stat-profile').textContent = count(pc && pc.percentage);
    var tasks = [
      ['Adaugă orașul preferat', 'recomandări locale mai bune', !!fields.city, '/cont/setari#profil-preferinte'],
      ['Alege tipuri de activități', 'copii, muzee, natură, escape rooms', !!fields.interests, '/cont/setari#profil-preferinte'],
      ['Adaugă vârstele copiilor', 'filtrare activități potrivite', !!fields.family || !!fields.beneficiaries, '/cont/setari#familie']
    ];
    var list = $('db-tasks');
    list.textContent = '';
    tasks.forEach(function (t) {
      var li = el('li'), a = el('a', 'db-task' + (t[2] ? ' is-done' : '')), mark = el('span', 'db-task-mark'), label = el('span', 'db-task-t');
      a.href = t[3];
      mark.appendChild(ic(t[2] ? 'check' : 'plus'));
      label.appendChild(el('b', null, t[0]));
      label.appendChild(el('small', null, t[1]));
      a.appendChild(mark); a.appendChild(label); a.appendChild(el('span', 'db-sr', t[2] ? ' (făcut)' : ' (de făcut)'));
      li.appendChild(a); list.appendChild(li);
    });
  }
  function renderPoints(balance) {
    $('db-stat-points').textContent = num.format(count(balance));
    $('db-stat-points-lei').textContent = toLei(balance);
    $('db-points-big').textContent = num.format(count(balance));
    $('db-points-lei').textContent = toLei(balance);
  }
  function renderStats(s) {
    s = s && typeof s === 'object' ? s : {};
    var tickets = count(s.upcoming_tickets_count != null ? s.upcoming_tickets_count : s.tickets_count);
    var activities = s.upcoming_activities_count != null ? count(s.upcoming_activities_count) : (s.upcoming_events_count != null ? count(s.upcoming_events_count) : tickets);
    var orders = count(s.orders_count != null ? s.orders_count : s.total_orders);
    $('db-stat-tickets').textContent = num.format(tickets);
    $('db-stat-activities').textContent = plural(activities, 'activitate confirmată', 'activități confirmate');
    $('db-stat-orders').textContent = num.format(orders);
    $('db-stat-orders-last').textContent = relTime(s.last_order_at) ? 'ultima ' + relTime(s.last_order_at) : 'fără comenzi încă';
    $('db-greet-tail').textContent = tickets > 0 ? 'Ai ' + plural(tickets, 'bilet viitor.', 'bilete viitoare.') : 'Bine ai revenit.';
    account.setBadges({ tickets: tickets, orders: orders });
  }
  function renderUpcoming(raw) {
    var items = upcomingList(raw).map(normUpcoming).slice(0, 4);
    show('db-upcoming-skel', false);
    show('db-hero-skel', false);
    show('db-upcoming-empty', !items.length);
    var list = $('db-upcoming');
    list.textContent = '';
    items.forEach(function (t) {
      var li = el('li', 'db-tk'), body = el('div', 'db-tk-body'), tags = el('div', 'db-tk-tags'), actions = el('div', 'db-tk-cta');
      li.appendChild(img(t.image));
      tags.appendChild(el('span', 'db-pill is-ok', 'confirmat'));
      if (t.city) tags.appendChild(el('span', 'db-pill', t.city));
      body.appendChild(tags);
      body.appendChild(el('h3', null, t.title));
      body.appendChild(el('p', null, [longDate(t.date), t.time, t.tickets > 1 ? t.tickets + ' beneficiari' : ''].filter(Boolean).join(' · ')));
      li.appendChild(body);
      var open = el('a', 'btn btn-primary', 'Deschide');
      open.href = '/cont/bilete';
      open.setAttribute('aria-label', 'Deschide biletul: ' + t.title);
      var cal = el('button', 'btn btn-ghost');
      cal.type = 'button';
      cal.appendChild(ic('calendar-blank'));
      cal.appendChild(document.createTextNode('Calendar'));
      cal.setAttribute('aria-label', 'Adaugă în calendar: ' + t.title);
      cal.hidden = !t.date;
      cal.addEventListener('click', function () { downloadIcs(t); });
      actions.appendChild(open); actions.appendChild(cal); li.appendChild(actions);
      list.appendChild(li);
    });
    show('db-upcoming', items.length > 0);

    next = items[0] || null;
    if (next) {
      $('db-next-title').textContent = next.title;
      $('db-next-loc').textContent = [next.venue, next.city].filter(Boolean).join(', ');
      $('db-next-weekday').textContent = next.date ? WEEKDAYS[next.date.getDay()] : '';
      $('db-next-time').textContent = next.time;
      $('db-next-date').textContent = longDate(next.date) + (next.tickets > 1 ? ' · ' + next.tickets + ' beneficiari' : '');
      $('db-next-cal').hidden = !next.date;
    }
    show('db-next', !!next);
    show('db-points-card', !next);
  }
  function renderRecos(items) {
    items = (Array.isArray(items) ? items : []).filter(function (it) { return it && typeof it === 'object'; }).slice(0, 4);
    show('db-recos-skel', false);
    show('db-recos-empty', !items.length);
    var list = $('db-recos');
    list.textContent = '';
    items.forEach(function (it) {
      var li = el('li', 'db-reco'), a = el('a', 'db-reco-a'), media = el('span', 'db-reco-media'), body = el('div', 'db-reco-body');
      a.href = safeUrl(it.url, '/categorii');
      media.appendChild(img(it.image));
      a.appendChild(media);
      body.appendChild(el('span', 'db-pill', txt((it.reasons && it.reasons[0]) || it.reason_primary || it.reason) || 'recomandare'));
      body.appendChild(el('h3', null, txt(it.title)));
      if (it.price_label || it.meta) body.appendChild(el('p', null, txt(it.price_label || it.meta)));
      var cta = el('span', 'db-reco-cta', 'Vezi activitatea');
      cta.appendChild(ic('arrow-right'));
      body.appendChild(cta);
      a.appendChild(body); li.appendChild(a); list.appendChild(li);
    });
    show('db-recos', items.length > 0);
  }
  function tone(label) {
    var s = String(label || '').toLowerCase();
    if (/retur|anulat|refund|cancel|fail|eșuat|esuat|expir/.test(s)) return 'is-bad';
    if (/confirm|finaliz|paid|complet|plătit|platit/.test(s)) return 'is-ok';
    return 'is-wait';
  }
  function renderOrders(orders) {
    orders = (Array.isArray(orders) ? orders : []).filter(function (o) { return o && typeof o === 'object'; }).slice(0, 4);
    show('db-orders-skel', false);
    show('db-orders-empty', !orders.length);
    var body = $('db-orders');
    body.textContent = '';
    orders.forEach(function (o) {
      var tr = el('tr'), first = el('td'), link = el('a', 'db-order-link', txt(o.id)), status = el('td');
      link.href = safeUrl(o.url, '/cont/comenzile-mele');
      first.appendChild(link);
      first.appendChild(el('small', 'db-order-date', txt(o.date)));
      tr.appendChild(first);
      tr.appendChild(el('td', 'is-date', txt(o.date)));
      tr.appendChild(el('td', 'is-total', txt(o.total)));
      status.appendChild(el('span', 'db-pill ' + tone(o.status), txt(o.status)));
      tr.appendChild(status);
      body.appendChild(tr);
    });
    show('db-orders-table', orders.length > 0);
  }
  function renderUtility(u) {
    u = u && typeof u === 'object' ? u : {};
    var support = count(u.supportActive), reviews = count(u.reviewsPending), gift = Number(u.giftBalance) > 0 ? Number(u.giftBalance) : 0;
    $('db-u-support-h').textContent = support ? plural(support, 'tichet activ', 'tichete active') : 'Niciun tichet activ';
    $('db-u-support-p').textContent = support ? 'Vezi statusul răspunsurilor de la echipa noastră.' : 'Deschide un tichet dacă ai nelămuriri.';
    $('db-u-reviews-h').textContent = reviews ? plural(reviews, 'recenzie de scris', 'recenzii de scris') : 'Toate scrise';
    $('db-u-reviews-p').textContent = reviews ? 'Scrie despre activitățile la care ai participat.' : 'Mulțumim că împărtășești experiențele tale.';
    $('db-u-gift').classList.toggle('is-hot', gift > 0);
    $('db-u-gift-h').textContent = gift ? 'Ai ' + money(gift) + ' disponibili' : 'Verifică un card cadou';
    $('db-u-gift-p').textContent = gift ? 'Card activ — folosește-l la următoarea comandă.' : 'Introdu codul cardului pentru a vedea soldul.';
    account.setBadges({ support: support });
  }
  function renderReferral(ref, legacyCode) {
    ref = ref && typeof ref === 'object' ? ref : {};
    var code = txt(ref.code || ref.referral_code || (ref.referralCode && ref.referralCode.code) || legacyCode);
    var link = txt(ref.link || ref.referral_link || ref.share_url);
    if (!code && !link) return;
    // core's link is https://<site>/?ref=<code> (what auth.js reads); /r/<code> is a 404 on this site
    $('db-ref-url').value = /^https?:\/\//i.test(link) ? link.replace(/^https?:\/\//i, '') : window.location.host.replace(/^www\./, '') + '/?ref=' + encodeURIComponent(code);
    refReady = true;
  }

  var copyBtn = $('db-ref-copy'), copyTimer = 0;
  copyBtn.addEventListener('click', function () {
    var input = $('db-ref-url'), status = $('db-ref-status');
    if (!refReady) { status.textContent = 'Linkul tău apare imediat ce se încarcă datele contului.'; return; }
    var manual = function () { input.focus(); input.select(); status.textContent = 'Linkul e selectat. Copiază-l manual.'; };
    if (!(navigator.clipboard && navigator.clipboard.writeText)) { manual(); return; }
    navigator.clipboard.writeText(window.location.protocol + '//' + input.value).then(function () {
      status.textContent = 'Link copiat în clipboard.';
      copyBtn.textContent = 'Copiat';
      clearTimeout(copyTimer);
      copyTimer = setTimeout(function () { copyBtn.textContent = 'Copiază'; }, 2000);
    }, manual);
  });

  // ---------- load ----------
  if (!account.isCustomer()) { guard(); return; }

  var cached = account.cachedUser() || {};
  renderFirstName(cached);
  renderTasks(cached.profile_completion || null);
  if (cached.points != null) renderPoints(cached.points);

  function fromBundle(d) {
    var u = d.customer && typeof d.customer === 'object' ? d.customer : {};
    renderFirstName(u);
    if (u.email) account.setUser(u);
    if (u.profile_completion) renderTasks(u.profile_completion);
    if (d.rewards_config && Number(d.rewards_config.points_per_lei) > 0) pointsPerLei = Number(d.rewards_config.points_per_lei);
    var balance = d.rewards_summary && d.rewards_summary.balance != null ? d.rewards_summary.balance : u.points;
    renderPoints(balance);
    account.setBadges({ points: count(balance) });
    renderReferral(d.referrals, u.referral_code);
    renderStats(d.stats);
    renderUpcoming(d.upcoming);
    renderRecos(d.recommendations);
    renderOrders(d.recent_orders);
    renderUtility(d.utility);
  }

  function fallback() {
    var api = BileteOnlineAPI, c = api.customer || {};
    function settle(fn) {
      return new Promise(function (resolve) {
        try { Promise.resolve(fn()).then(function (v) { resolve({ ok: true, v: v }); }, function (e) { resolve({ ok: false, e: e }); }); }
        catch (e) { resolve({ ok: false, e: e }); }
      });
    }
    Promise.all([
      settle(function () { return api.get('/customer/rewards/config'); }),
      settle(function () { return c.getProfile(); }),
      settle(function () { return api.get('/customer/referrals'); }),
      settle(function () { return c.getDashboardStats(); }),
      settle(function () { return c.getUpcomingEvents(6); }),
      settle(function () { return api.get('/customer/recommendations'); }),
      settle(function () { return c.getOrders({ per_page: 4 }); }),
      settle(function () { return api.get('/customer/support-tickets', { status: 'open', per_page: 1 }); }),
      settle(function () { return api.get('/customer/reviews/events-to-review'); })
    ]).then(function (r) {
      if (r.some(function (x) { return !x.ok && x.e && x.e.status === 401; })) { guard(); return; }
      var data = function (i) { var v = r[i].ok && r[i].v ? r[i].v.data : null; return v && typeof v === 'object' ? v : {}; };
      var list = function (v, keys) {
        if (Array.isArray(v)) return v;
        for (var k = 0; k < keys.length; k++) if (Array.isArray(v[keys[k]])) return v[keys[k]];
        return [];
      };

      if (Number(data(0).points_per_lei) > 0) pointsPerLei = Number(data(0).points_per_lei);
      var me = data(1).customer && typeof data(1).customer === 'object' ? data(1).customer : data(1);
      renderFirstName(me);
      if (me.email) account.setUser(me);
      if (me.profile_completion) renderTasks(me.profile_completion);
      var stats = data(3).stats && typeof data(3).stats === 'object' ? data(3).stats : data(3);
      var balance = stats.points_balance != null ? stats.points_balance : (stats.points && stats.points.balance != null ? stats.points.balance : me.points);
      renderPoints(balance);
      account.setBadges({ points: count(balance) });
      renderReferral(data(2), me.referral_code);
      renderStats(stats);
      renderUpcoming(data(4));
      renderRecos(list(data(5), ['items', 'recommendations']));
      var labels = { paid: 'confirmată', confirmed: 'confirmată', completed: 'finalizată', refunded: 'retur', cancelled: 'anulată', pending: 'în așteptare' };
      renderOrders(list(data(6), ['orders', 'items', 'data']).filter(function (o) { return o && typeof o === 'object'; }).map(function (o) {
        var s = String(o.status || '').toLowerCase(), d = new Date(o.created_at), ref = txt(o.order_number) || 'BO-' + txt(o.id);
        return {
          id: '#' + ref.replace(/^#/, ''),
          date: isNaN(d.getTime()) ? '' : shortDate(d),
          total: money(o.total_amount != null ? o.total_amount : o.total),
          status: labels[s] || s,
          url: '/cont/comenzile-mele#' + encodeURIComponent(txt(o.order_number) || txt(o.id))
        };
      }));
      var support = data(7);
      renderUtility({
        supportActive: support.total != null ? support.total : (support.meta && support.meta.total != null ? support.meta.total : list(support, ['tickets', 'items']).length),
        reviewsPending: list(data(8), ['events', 'items']).length,
        giftBalance: 0
      });
    });
  }

  BileteOnlineAPI.get('/customer/dashboard-bundle').then(function (resp) {
    if (!(resp && resp.success && resp.data && typeof resp.data === 'object')) throw { status: -1 };
    fromBundle(resp.data);
  }).catch(function (err) {
    if (err && err.status === 401) { guard(); return; }
    if (!(err && typeof err.status === 'number')) console.error(err);
    fallback();
  });
})();
