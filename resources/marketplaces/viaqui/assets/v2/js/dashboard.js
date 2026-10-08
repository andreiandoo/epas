/* viaqui.com v2: customer dashboard (/cont). One request (GET /customer/dashboard-bundle) fills every section. If it
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

  var LOC = VQ.locale === 'en' ? 'en-GB' : VQ.locale;
  var num = new Intl.NumberFormat(LOC);
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
    if (v && typeof v === 'object') v = v[VQ.locale] || v.en || v.ro || Object.keys(v).map(function (k) { return v[k]; })[0];
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
  // an amount is in the marketplace's currency unless the data names another one
  function money(n, cur) { n = Number(n) || 0; return typeof BileteOnlineUtils !== 'undefined' ? BileteOnlineUtils.formatCurrency(n, cur || true) : '€' + n; }
  function toLei(points) { return money(Math.floor(count(points) / (pointsPerLei || 100))); }
  function longDate(d) { return d ? d.toLocaleDateString(LOC, { day: 'numeric', month: 'long', year: 'numeric' }) : ''; }
  function shortDate(d) { return d.toLocaleDateString(LOC, { day: 'numeric', month: 'short', year: 'numeric' }); }
  function relTime(iso) {
    var d = new Date(iso);
    if (!iso || isNaN(d.getTime())) return '';
    var s = Math.max(0, (Date.now() - d.getTime()) / 1000);
    if (s < 3600) return VQ.t('{n} min ago', { n: Math.max(1, Math.floor(s / 60)) });
    if (s < 86400) return VQ.t('{time} ago', { time: VQ.n(Math.floor(s / 3600), 'hour', 'hours') });
    if (s < 86400 * 30) return VQ.t('{time} ago', { time: VQ.n(Math.floor(s / 86400), 'day', 'days') });
    return VQ.t('on {date}', { date: longDate(d) });
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
      title: txt(ev.name || ev.title) || VQ.t('Activity'),
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
      [VQ.t('Add your favourite city'), VQ.t('better local recommendations'), !!fields.city, VQ.url('/account/settings') + '#profil-preferinte'],
      [VQ.t('Choose types of activities'), VQ.t('kids, museums, nature, escape rooms'), !!fields.interests, VQ.url('/account/settings') + '#profil-preferinte'],
      [VQ.t('Add the ages of your children'), VQ.t('to filter activities that suit them'), !!fields.family || !!fields.beneficiaries, VQ.url('/account/settings') + '#familie']
    ];
    var list = $('db-tasks');
    list.textContent = '';
    tasks.forEach(function (t) {
      var li = el('li'), a = el('a', 'db-task' + (t[2] ? ' is-done' : '')), mark = el('span', 'db-task-mark'), label = el('span', 'db-task-t');
      a.href = t[3];
      mark.appendChild(ic(t[2] ? 'check' : 'plus'));
      label.appendChild(el('b', null, t[0]));
      label.appendChild(el('small', null, t[1]));
      a.appendChild(mark); a.appendChild(label); a.appendChild(el('span', 'db-sr', t[2] ? VQ.t(' (done)') : VQ.t(' (to do)')));
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
    $('db-stat-activities').textContent = VQ.n(activities, 'confirmed activity', 'confirmed activities');
    $('db-stat-orders').textContent = num.format(orders);
    $('db-stat-orders-last').textContent = relTime(s.last_order_at) ? VQ.t('last order {when}', { when: relTime(s.last_order_at) }) : VQ.t('no orders yet');
    $('db-greet-tail').textContent = tickets > 0 ? VQ.t('You have {tickets}.', { tickets: VQ.n(tickets, 'upcoming ticket', 'upcoming tickets') }) : VQ.t('Welcome back.');
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
      tags.appendChild(el('span', 'db-pill is-ok', VQ.t('confirmed')));
      if (t.city) tags.appendChild(el('span', 'db-pill', t.city));
      body.appendChild(tags);
      body.appendChild(el('h3', null, t.title));
      body.appendChild(el('p', null, [longDate(t.date), t.time, t.tickets > 1 ? VQ.n(t.tickets, 'guest', 'guests') : ''].filter(Boolean).join(' · ')));
      li.appendChild(body);
      var open = el('a', 'btn btn-primary', VQ.t('Open'));
      open.href = VQ.url('/account/tickets');
      open.setAttribute('aria-label', VQ.t('Open ticket: {title}', { title: t.title }));
      var cal = el('button', 'btn btn-ghost');
      cal.type = 'button';
      cal.appendChild(ic('calendar-blank'));
      cal.appendChild(document.createTextNode(VQ.t('Calendar')));
      cal.setAttribute('aria-label', VQ.t('Add to calendar: {title}', { title: t.title }));
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
      $('db-next-weekday').textContent = next.date ? next.date.toLocaleDateString(LOC, { weekday: 'long' }) : '';
      $('db-next-time').textContent = next.time;
      $('db-next-date').textContent = longDate(next.date) + (next.tickets > 1 ? ' · ' + VQ.n(next.tickets, 'guest', 'guests') : '');
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
      a.href = safeUrl(it.url, VQ.url('/categories'));
      media.appendChild(img(it.image));
      a.appendChild(media);
      body.appendChild(el('span', 'db-pill', txt((it.reasons && it.reasons[0]) || it.reason_primary || it.reason) || VQ.t('recommended')));
      body.appendChild(el('h3', null, txt(it.title)));
      if (it.price_label || it.meta) body.appendChild(el('p', null, txt(it.price_label || it.meta)));
      var cta = el('span', 'db-reco-cta', VQ.t('View activity'));
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
      link.href = safeUrl(o.url, VQ.url('/account/orders'));
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
    $('db-u-support-h').textContent = support ? VQ.n(support, 'open ticket', 'open tickets') : VQ.t('No open tickets');
    $('db-u-support-p').textContent = support ? VQ.t('See where the replies from our team stand.') : VQ.t('Open a ticket if you have a question.');
    $('db-u-reviews-h').textContent = reviews ? VQ.n(reviews, 'review to write', 'reviews to write') : VQ.t('All written');
    $('db-u-reviews-p').textContent = reviews ? VQ.t('Write about the activities you went to.') : VQ.t('Thank you for sharing your experiences.');
    $('db-u-gift').classList.toggle('is-hot', gift > 0);
    $('db-u-gift-h').textContent = gift ? VQ.t('You have {amount} available', { amount: money(gift) }) : VQ.t('Check a gift card');
    $('db-u-gift-p').textContent = gift ? VQ.t('Active card: use it on your next order.') : VQ.t('Enter the card code to see its balance.');
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
    if (!refReady) { status.textContent = VQ.t('Your link appears as soon as your account details load.'); return; }
    var manual = function () { input.focus(); input.select(); status.textContent = VQ.t('The link is selected. Copy it by hand.'); };
    if (!(navigator.clipboard && navigator.clipboard.writeText)) { manual(); return; }
    navigator.clipboard.writeText(window.location.protocol + '//' + input.value).then(function () {
      status.textContent = VQ.t('Link copied to the clipboard.');
      copyBtn.textContent = VQ.t('Copied');
      clearTimeout(copyTimer);
      copyTimer = setTimeout(function () { copyBtn.textContent = VQ.t('Copy'); }, 2000);
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
      var labels = { paid: VQ.t('confirmed'), confirmed: VQ.t('confirmed'), completed: VQ.t('completed'), refunded: VQ.t('refunded'), cancelled: VQ.t('cancelled'), pending: VQ.t('pending') };
      renderOrders(list(data(6), ['orders', 'items', 'data']).filter(function (o) { return o && typeof o === 'object'; }).map(function (o) {
        var s = String(o.status || '').toLowerCase(), d = new Date(o.created_at), ref = txt(o.order_number) || 'BO-' + txt(o.id);
        return {
          id: '#' + ref.replace(/^#/, ''),
          date: isNaN(d.getTime()) ? '' : shortDate(d),
          total: money(o.total_amount != null ? o.total_amount : o.total, o.currency),
          status: labels[s] || s,
          url: VQ.url('/account/orders') + '#' + encodeURIComponent(txt(o.order_number) || txt(o.id))
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
