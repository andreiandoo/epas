/* viaqui.com v2: organizer activities (/organizator/activities). One page, two views switched with the History API:
   the catalogue (/organizator/activities, ?stare=&q=) and the editor (?action=create, /organizator/activities/{id}; the
   older /organizator/events, /organizator/event/{id} and ?id= still open and are rewritten to these). Runs inside the
   organizer shell (window.BO_ORG from organizer.js). Text from the API is always written as text; the organizer's own
   HTML (description, terms) only goes into TinyMCE or is read through DOMParser. Markup in organizer/events.php. */
(function () {
  'use strict';
  var O = window.BO_ORG, root = document.getElementById('oe');
  if (!O || !root) return;
  var F = O.fmt, el = O.el, icon = O.icon;
  var LOC = VQ.locale === 'en' ? 'en-GB' : VQ.locale;
  var SITE = document.title.lastIndexOf(' · ') > -1 ? document.title.slice(document.title.lastIndexOf(' · ')) : ''; // " · Viaqui", as head.php wrote it
  var $ = function (id) { return document.getElementById(id); };
  function qsa(sel, ctx) { return [].slice.call((ctx || document).querySelectorAll(sel)); }
  var LIST_URL = VQ.url('/organizator/activities'); // with the language prefix, so the History API keeps it
  var AWAITING = ['draft', 'pending_review', 'rejected'];
  var STATES = ['ongoing', 'draft', 'ended', 'all'];
  var TINY = 'https://cdn.jsdelivr.net/npm/tinymce@6.8.5';
  var desktop = window.matchMedia('(min-width: 1024px)');
  var reduced = window.matchMedia('(prefers-reduced-motion: reduce)').matches;

  /* =================== HELPERS =================== */
  function norm(s) { return String(s || '').toLowerCase().normalize('NFD').replace(/[̀-ͯ]/g, '').trim(); }
  function words(s) { return String(s || '').trim().split(/\s+/).filter(Boolean).length; }
  function cap(s) { return s ? s.charAt(0).toUpperCase() + s.slice(1) : s; }
  function debounce(fn, ms) {
    var t = 0;
    return function () { var a = arguments, c = this; clearTimeout(t); t = setTimeout(function () { fn.apply(c, a); }, ms); };
  }
  /** "2026-09-20T19:00:00" (core sends naive local times) → {date, time}; no Date, so no timezone shift. */
  function naive(v) {
    var m = /^(\d{4}-\d{2}-\d{2})(?:[T ](\d{2}:\d{2}))?/.exec(String(v || ''));
    return m ? { date: m[1], time: m[2] || '' } : null;
  }
  function nowNaive() {
    var p = {};
    new Intl.DateTimeFormat('en-GB', { timeZone: 'Europe/Bucharest', year: 'numeric', month: '2-digit', day: '2-digit', hour: '2-digit', minute: '2-digit', hourCycle: 'h23' })
      .formatToParts(new Date()).forEach(function (x) { p[x.type] = x.value; });
    return p.year + '-' + p.month + '-' + p.day + 'T' + p.hour + ':' + p.minute;
  }
  function dayLabel(ymd, opts) { var d = F.dateOf(ymd); return d ? F.date(d, opts) : ''; }
  function val(id) { var n = $(id); return n ? String(n.value || '') : ''; }
  function setVal(id, v) { var n = $(id); if (n) n.value = v == null ? '' : String(v); }
  function isUrl(s) { try { var u = new URL(s); return u.protocol === 'http:' || u.protocol === 'https:'; } catch (e) { return false; } }
  function plain(html) {
    if (!html) return '';
    try { return (new DOMParser().parseFromString(String(html), 'text/html').body.textContent || '').replace(/\s+/g, ' ').trim(); } catch (e) { return ''; }
  }
  function richEmpty(html) { return !String(html || '').replace(/<(?!img|iframe)[^>]*>/gi, '').replace(/&nbsp;|\s/g, '').length; }
  function focusQuiet(n) { if (n) { try { n.focus({ preventScroll: true }); } catch (e) { n.focus(); } } }

  var MSG = [
    [/account must be approved/i, VQ.t('Your account must be approved before you can create activities.')],
    [/Event not found/i, VQ.t('The activity was not found.')],
    [/not accessible/i, VQ.t('You do not have access to this activity.')],
    [/Only unpublished events can be deleted/i, VQ.t('Only unpublished activities can be deleted.')],
    [/existing orders/i, VQ.t('The activity has orders, so it can no longer be deleted.')],
    [/at least one ticket type/i, VQ.t('Add at least one ticket type.')],
    [/already cancelled/i, VQ.t('The activity is already cancelled.')],
    [/deja publicat/i, VQ.t('The activity is already published.')],
    [/deja trimis spre aprobare/i, VQ.t('The activity has already been submitted for approval.')],
    [/semnezi contractul/i, VQ.t('You need to sign the contract before publishing activities.')],
    [/No images provided/i, VQ.t('Choose an image.')],
  ];
  function errText(err, fallback) {
    if (err && err.status === 0) return VQ.t('No connection. Check your internet and try again.');
    var m = String((err && (err.message || (err.data && err.data.message))) || '').trim();
    for (var i = 0; i < MSG.length; i++) if (MSG[i][0].test(m)) return MSG[i][1];
    if (/[ăâîșțĂÂÎȘȚ]/.test(m) && m.length < 300) return m.replace(/Evenimentul/g, 'Activitatea').replace(/evenimentul/g, 'activitatea').replace(/evenimente/g, 'activități');
    return fallback;
  }

  /* dialogs */
  function showDialog(d) { if (typeof d.showModal === 'function') { if (!d.open) d.showModal(); } else d.setAttribute('open', ''); }
  function closeDialog(d) { if (typeof d.close === 'function') { if (d.open) d.close(); } else d.removeAttribute('open'); }
  qsa('dialog', root).forEach(function (d) {
    qsa('[data-d-close]', d).forEach(function (b) { b.addEventListener('click', function () { closeDialog(d); }); });
    d.addEventListener('click', function (e) { if (e.target === d) closeDialog(d); }); // a click on the backdrop
  });
  function confirmBox(o) {
    var d = $('oe-confirm-d');
    $('oe-cf-h').textContent = o.title;
    $('oe-cf-p').textContent = o.text || '';
    $('oe-cf-p').hidden = !o.text;
    var ok = $('oe-cf-ok');
    ok.textContent = o.ok || VQ.t('Confirm');
    ok.className = 'btn ' + (o.danger ? 'oe-btn-danger' : 'btn-primary');
    $('oe-cf-cancel').textContent = o.cancel || VQ.t('Never mind');
    return new Promise(function (resolve) {
      d.returnValue = '';
      d.addEventListener('close', function () { resolve(d.returnValue === 'ok'); }, { once: true });
      showDialog(d);
      setTimeout(function () { focusQuiet($('oe-cf-cancel')); }, 30);
    });
  }
  function confirmLeave() {
    return confirmBox({ title: VQ.t('You have unsaved changes'), text: VQ.t('If you leave now, you lose what you changed since the last save.'), ok: VQ.t('Leave without saving'), cancel: VQ.t('Stay here'), danger: true });
  }

  /* =================== ROUTING =================== */
  var view = null, lastListUrl = LIST_URL;
  function routeOf() {
    var p = new URLSearchParams(location.search);
    var m = location.pathname.match(/\/organizator\/(?:activities|events|event)\/(\d+)/);
    var id = m ? m[1] : (/^\d+$/.test(p.get('id') || '') ? p.get('id') : null);
    if (id) return { view: 'edit', id: id };
    if (p.get('action') === 'create') return { view: 'create' };
    return { view: 'list' };
  }
  function route() {
    var r = routeOf();
    var canon = r.view === 'edit' ? LIST_URL + '/' + encodeURIComponent(r.id) : r.view === 'create' ? LIST_URL + '?action=create' : null;
    if (canon && location.pathname + location.search !== canon) history.replaceState({ oe: 1 }, '', canon);
    if (r.view === 'list') showList();
    else openEditor(r.view === 'edit' ? r.id : null);
  }
  function navigate(url, replace) {
    history[replace ? 'replaceState' : 'pushState']({ oe: 1 }, '', url);
    route();
  }
  window.addEventListener('popstate', function () {
    var r = routeOf();
    if (view === 'editor' && ED.dirty && !(r.view === 'edit' && String(r.id) === String(ED.id))) {
      var back = ED.url;
      confirmLeave().then(function (leave) {
        if (leave) { markClean(); route(); } else history.pushState({ oe: 1 }, '', back);
      });
      return;
    }
    route();
  });
  function leaveEditor() {
    if (!ED.dirty) { navigate(lastListUrl); return; }
    confirmLeave().then(function (leave) { if (leave) { markClean(); navigate(lastListUrl); } });
  }

  /* =================== CATALOGUE =================== */
  var L = { events: [], loaded: false, loading: null, error: false, status: 'ongoing', q: '' };
  var BUCKET = { ongoing: VQ.t('In progress'), draft: VQ.t('Drafts'), ended: VQ.t('Ended'), all: VQ.t('All') };
  var STATUS_LABEL = { published: VQ.t('Published'), draft: VQ.t('Draft'), ended: VQ.t('Activity ended'), pending_review: VQ.t('Under review · waiting for approval'), rejected: VQ.t('Rejected'), cancelled: VQ.t('Cancelled'), postponed: VQ.t('Postponed') };
  var STATUS_TONE = { published: 'is-ok', draft: 'is-wait', ended: 'is-muted', pending_review: 'is-info', rejected: 'is-bad', cancelled: 'is-bad', postponed: 'is-wait' };
  var SALE_TONE = { on_sale: 'is-ok', sold_out: 'is-info', door_sales: 'is-wait' };
  var SALE_LABEL = { on_sale: VQ.t('On sale'), sold_out: VQ.t('Sold out'), door_sales: VQ.t('Door sales') };

  function startKey(e) { var n = naive(e.starts_at); return n ? n.date + 'T' + (n.time || '00:00') : ''; }
  function isEnded(e) {
    if (e.status === 'ended' || e.is_past === true || e.is_ended === true) return true;
    var end = naive(e.ends_at) || naive(e.starts_at);
    return !!end && (end.date + 'T' + (end.time || '23:59')) < nowNaive();
  }
  function displayStatus(e) {
    if (e.is_cancelled || e.status === 'cancelled') return 'cancelled';
    if (e.is_postponed || e.status === 'postponed') return 'postponed';
    if (isEnded(e)) return 'ended';
    if (AWAITING.indexOf(e.status) > -1) return 'draft'; // rejected = not approved: a draft, not "in progress"
    return 'ongoing';
  }
  function bucket(e) { var s = displayStatus(e); return s === 'ongoing' || s === 'draft' ? s : 'ended'; }

  function renderSkeleton() {
    var list = $('oe-cards');
    list.textContent = '';
    list.setAttribute('aria-busy', 'true');
    list.hidden = false;
    $('oe-empty').hidden = true;
    for (var i = 0; i < 3; i++) {
      list.appendChild(el('li', { class: 'oe-card is-skel', 'aria-hidden': 'true' }, [
        el('span', { class: 'org-skel oe-sk-media' }),
        el('span', { class: 'oe-sk-main' }, [el('span', { class: 'org-skel oe-sk-a' }), el('span', { class: 'org-skel oe-sk-b' }), el('span', { class: 'org-skel oe-sk-c' })]),
      ]));
    }
  }
  function loadList() {
    if (L.loading) return L.loading;
    L.error = false;
    renderSkeleton();
    var rows = [];
    function page(n) { // the list is paginated (50 per page through the proxy): read every page
      return O.api('/organizer/events?per_page=50&page=' + n).then(function (r) {
        rows = rows.concat(Array.isArray(r && r.data) ? r.data : []);
        var last = F.toNum(O.metaOf(r).last_page);
        if (last > n && n < 60) return page(n + 1);
      });
    }
    L.loading = page(1).then(function () { L.events = rows; L.loaded = true; }, function (err) {
      if (!err || err.status !== 401) L.error = true;
    }).then(function () { L.loading = null; if (view === 'list') renderList(); });
    return L.loading;
  }
  function syncListUrl() {
    var p = new URLSearchParams();
    if (L.status !== 'ongoing') p.set('stare', L.status);
    if (L.q) p.set('q', L.q);
    var url = LIST_URL + (p.toString() ? '?' + p.toString() : '');
    lastListUrl = url;
    if (view === 'list' && location.pathname + location.search !== url) history.replaceState({ oe: 1 }, '', url);
  }
  function setStatus(s) {
    L.status = STATES.indexOf(s) > -1 ? s : 'ongoing';
    syncListUrl();
    renderList();
  }
  function showList() {
    var fromEditor = view === 'editor';
    view = 'list';
    closeMenus();
    closeCombos();
    $('oe-editor').hidden = true;
    $('oe-list').hidden = false;
    var p = new URLSearchParams(location.search);
    L.status = STATES.indexOf(p.get('stare')) > -1 ? p.get('stare') : 'ongoing';
    L.q = (p.get('q') || '').slice(0, 100);
    $('oe-q').value = L.q;
    syncListUrl();
    document.title = VQ.t('Your activities') + SITE;
    if (!L.loaded) loadList(); else renderList();
    if (fromEditor) { window.scrollTo(0, 0); focusQuiet($('oe-list-h')); }
  }

  function renderList() {
    var counts = { ongoing: 0, draft: 0, ended: 0, all: L.events.length };
    L.events.forEach(function (e) { counts[bucket(e)]++; });
    qsa('[data-count]', root).forEach(function (n) { n.textContent = L.loaded ? F.num(counts[n.getAttribute('data-count')] || 0) : '·'; });
    qsa('.oe-pill', root).forEach(function (b) { b.setAttribute('aria-pressed', String(b.getAttribute('data-status') === L.status)); });
    var list = $('oe-cards');
    list.textContent = '';
    list.removeAttribute('aria-busy');
    closeMenus();
    if (L.error) { list.hidden = true; renderEmpty('error', counts); $('oe-live').textContent = VQ.t('We could not load the activities.'); return; }
    var q = norm(L.q);
    var rows = L.events.filter(function (e) { return L.status === 'all' || bucket(e) === L.status; }).filter(function (e) {
      return !q || norm([F.flat(e.name || e.title), F.flat(e.venue_name), F.flat(e.venue_city)].join(' ')).indexOf(q) > -1;
    });
    rows.sort(function (a, b) {
      var ak = startKey(a), bk = startKey(b);
      if (displayStatus(a) === 'ongoing' && displayStatus(b) === 'ongoing') return ak < bk ? -1 : ak > bk ? 1 : 0; // soonest first
      return ak > bk ? -1 : ak < bk ? 1 : 0; // the rest: most recent first
    });
    if (!rows.length) {
      list.hidden = true;
      renderEmpty(L.events.length ? (q ? 'search' : 'status') : 'none', counts);
    } else {
      $('oe-empty').hidden = true;
      list.hidden = false;
      rows.forEach(function (e) { list.appendChild(card(e)); });
    }
    $('oe-live').textContent = rows.length ? VQ.t('{count} in “{filter}”', { count: VQ.n(rows.length, 'activity', 'activities'), filter: BUCKET[L.status] }) : VQ.t('No activity found');
  }

  function renderEmpty(kind, counts) {
    var box = $('oe-empty'), actions = [], ic, title, text;
    box.textContent = '';
    box.classList.toggle('is-error', kind === 'error');
    function button(label, cls, fn) { var b = el('button', { type: 'button', class: cls, text: label }); b.addEventListener('click', fn); return b; }
    function create(label) { return el('button', { type: 'button', class: 'btn btn-primary', 'data-oe-new': true }, [icon('plus'), label]); }
    if (kind === 'error') {
      ic = 'warning-circle'; title = VQ.t('We could not load the activities'); text = VQ.t('Check your connection and try again.');
      actions.push(button(VQ.t('Try again'), 'btn btn-primary', function () { L.loaded = false; loadList(); }));
    } else if (kind === 'none') {
      ic = 'calendar-blank'; title = VQ.t('You have no activities yet'); text = VQ.t('Create your first activity and start selling tickets!');
      actions.push(create(VQ.t('Create an activity')));
    } else if (kind === 'search') {
      ic = 'magnifying-glass'; title = VQ.t('No activity matches “{query}”', { query: L.q });
      text = L.status !== 'all' ? VQ.t('We only searched in “{filter}”.', { filter: BUCKET[L.status] }) : VQ.t('Try another name, venue or city.');
      actions.push(button(VQ.t('Clear the search'), 'btn btn-ghost', function () { L.q = ''; $('oe-q').value = ''; syncListUrl(); renderList(); focusQuiet($('oe-q')); }));
      if (L.status !== 'all') actions.push(button(VQ.t('Search in all'), 'btn btn-primary', function () { setStatus('all'); }));
    } else {
      ic = 'calendar-blank';
      title = { ongoing: VQ.t('No activity in progress'), draft: VQ.t('No drafts'), ended: VQ.t('No ended activity') }[L.status];
      text = { ongoing: VQ.t('Published activities that have not ended show up here.'), draft: VQ.t('Unpublished activities, those under review and those rejected show up here.'), ended: VQ.t('Past, postponed or cancelled activities show up here.') }[L.status];
      ['ongoing', 'draft', 'ended'].forEach(function (k) {
        if (k !== L.status && counts[k]) actions.push(button(BUCKET[k] + ' (' + F.num(counts[k]) + ')', 'btn btn-ghost', function () { setStatus(k); }));
      });
      actions.push(create(VQ.t('New activity')));
    }
    box.appendChild(el('span', { class: 'org-empty-ic' }, icon(ic)));
    box.appendChild(el('b', { text: title }));
    box.appendChild(el('p', { text: text }));
    box.appendChild(el('div', { class: 'oe-empty-cta' }, actions));
    box.hidden = false;
  }

  function card(e) {
    var id = encodeURIComponent(e.id), name = F.flat(e.name || e.title) || VQ.t('Untitled activity');
    var ended = isEnded(e), awaiting = AWAITING.indexOf(e.status) > -1;
    var ongoing = e.status === 'published' && !ended && !e.is_cancelled && !e.is_postponed;
    var badge = e.status, sale = '';
    if (e.is_cancelled || e.status === 'cancelled') badge = 'cancelled';
    else if (e.is_postponed || e.status === 'postponed') badge = 'postponed';
    else if (e.is_sold_out) { badge = 'published'; sale = 'sold_out'; }
    else if (e.is_door_sales_only || e.door_sales_only) { badge = 'published'; sale = 'door_sales'; }
    else if (ended) badge = 'ended';
    else if (ongoing) sale = 'on_sale';

    var media = el('span', { class: 'oe-card-media', 'aria-hidden': 'true' }), letter = name.charAt(0).toUpperCase();
    if (e.image) {
      var im = el('img', { src: O.img(e.image), alt: '', loading: 'lazy', decoding: 'async' });
      im.addEventListener('error', function () { media.textContent = letter; }, { once: true });
      media.appendChild(im);
    } else media.textContent = letter;

    var s = naive(e.starts_at), en = naive(e.ends_at), when = '';
    if (s) {
      when = cap(dayLabel(s.date, { weekday: 'short', day: 'numeric', month: 'short', year: 'numeric' })) + (s.time && s.time !== '00:00' ? ', ' + s.time : '');
      if (en && en.date !== s.date) when += ' – ' + dayLabel(en.date, { day: 'numeric', month: 'short', year: 'numeric' });
    }
    var place = [F.flat(e.venue_name), F.flat(e.venue_city)].filter(Boolean).join(', ');
    var editable = e.is_editable !== false;
    var titleNode = editable ? el('a', { href: LIST_URL + '/' + id, 'data-oe-edit': true, text: name }) : name;

    var sold = e.tickets_paid != null ? e.tickets_paid : e.tickets_sold;
    var stats = [[VQ.t('Views'), F.num(e.views)], [VQ.t('Tickets sold'), F.num(sold)], [VQ.t('Invitations'), F.num(e.invitations)], [VQ.t('Net revenue'), F.money(e.revenue)]];

    var countdown = null, days = e.days_until;
    if (!ended && days != null && days >= 0) {
      countdown = days === 0 ? el('span', { class: 'oe-count is-soon' }, el('b', { text: VQ.t('Today') }))
        : days === 1 ? el('span', { class: 'oe-count is-soon' }, el('b', { text: VQ.t('Tomorrow') }))
        : el('span', { class: 'oe-count' }, [el('b', { text: F.num(days) }), ' ', el('span', { text: VQ.plural(days, 'day left', 'days left') })]);
    }

    var primary = [], more = [];
    if (editable) primary.push(el('a', { class: 'oe-act is-primary', href: LIST_URL + '/' + id, 'data-oe-edit': true }, [icon('pencil-simple'), VQ.t('Edit'), el('span', { class: 'sr', text: ': ' + name })]));
    if (!awaiting) primary.push(el('a', { class: 'oe-act', href: VQ.url('/organizator/participanti?event=' + id) }, [icon('users-three'), VQ.t('Participants'), el('span', { class: 'sr', text: ': ' + name })]));
    if (!awaiting) {
      primary.push(ended
        ? el('a', { class: 'oe-act', href: VQ.url('/organizator/report/' + id) }, [icon('file-text'), VQ.t('Report'), el('span', { class: 'sr', text: ': ' + name })])
        : el('a', { class: 'oe-act', href: VQ.url('/organizator/analytics/' + id) }, [icon('chart-line-up'), VQ.t('Analytics'), el('span', { class: 'sr', text: ': ' + name })]));
      more.push(el('a', { class: 'oe-mi', href: VQ.url('/organizator/sold?event=' + id) }, [icon('wallet'), VQ.t('Sales')]));
      more.push(el('a', { class: 'oe-mi', href: VQ.url('/organizator/documente?event=' + id) }, [icon('file-text'), VQ.t('Documents')]));
      if (!ended) more.push(el('a', { class: 'oe-mi', href: VQ.url('/organizator/invitatii?event=' + id) }, [icon('envelope-simple'), VQ.t('Invitations')]));
      if (ended) more.push(el('a', { class: 'oe-mi', href: VQ.url('/organizator/raport-staff?event=' + id) }, [icon('users-three'), VQ.t('Staff report')]));
    }
    if (ongoing) more.push(el('a', { class: 'oe-mi', href: VQ.url('/organizator/servicii?event=' + id) }, [icon('megaphone'), VQ.t('Promote')]));
    if (awaiting) {
      if (more.length) more.push(el('hr'));
      more.push(el('button', { class: 'oe-mi is-danger', type: 'button', 'data-oe-delete': String(e.id) }, [icon('trash'), VQ.t('Delete the activity')]));
    }
    var actions = primary.slice();
    if (more.length) {
      var menuId = 'oe-menu-' + e.id;
      actions.push(el('div', { class: 'oe-more' }, [
        el('button', { class: 'oe-act', type: 'button', 'data-oe-menu': true, 'aria-expanded': 'false', 'aria-controls': menuId }, [icon('dots-three'), VQ.t('More'), el('span', { class: 'sr', text: ' ' + VQ.t('for {name}', { name: name }) })]),
        el('div', { class: 'oe-menu', id: menuId, hidden: true }, more),
      ]));
    }

    return el('li', { class: 'oe-card' }, [
      media,
      el('div', { class: 'oe-card-main' }, [
        el('div', { class: 'oe-card-tags' }, [
          el('span', { class: 'org-tag ' + (STATUS_TONE[badge] || 'is-muted'), text: STATUS_LABEL[badge] || badge || VQ.t('Unknown') }),
          sale ? el('span', { class: 'org-tag ' + SALE_TONE[sale], text: SALE_LABEL[sale] }) : null,
        ]),
        el('h2', { class: 'oe-card-h' }, titleNode),
        (when || place) ? el('p', { class: 'oe-card-meta' }, [
          when ? el('span', null, [icon('calendar-blank'), when]) : null,
          place ? el('span', null, [icon('map-pin'), place]) : null,
        ]) : null,
      ]),
      el('dl', { class: 'oe-card-stats' }, stats.map(function (x) { return el('div', null, [el('dt', { text: x[0] }), el('dd', { text: x[1] })]); })),
      el('div', { class: 'oe-card-foot' }, [countdown || el('span', { class: 'oe-count', 'aria-hidden': 'true' }), el('div', { class: 'oe-card-act' }, actions)]),
    ]);
  }

  /* card menus */
  var openMenu = null;
  function closeMenus() {
    if (!openMenu) return;
    openMenu.btn.setAttribute('aria-expanded', 'false');
    openMenu.menu.hidden = true;
    var li = openMenu.btn.closest('.oe-card');
    if (li) li.classList.remove('has-menu');
    openMenu = null;
  }
  function toggleMenu(btn) {
    var menu = $(btn.getAttribute('aria-controls')), open = menu.hidden;
    closeMenus();
    if (!open) return;
    menu.hidden = false;
    btn.setAttribute('aria-expanded', 'true');
    btn.closest('.oe-card').classList.add('has-menu');
    openMenu = { btn: btn, menu: menu };
    focusQuiet(menu.querySelector('a, button'));
  }
  document.addEventListener('click', function (e) {
    if (openMenu && !openMenu.menu.contains(e.target) && !openMenu.btn.contains(e.target)) closeMenus();
  });
  document.addEventListener('keydown', function (e) {
    if (!openMenu) return;
    if (e.key === 'Escape') { var b = openMenu.btn; closeMenus(); focusQuiet(b); e.stopPropagation(); e.preventDefault(); return; }
    if (e.key === 'ArrowDown' || e.key === 'ArrowUp') {
      var items = qsa('a, button', openMenu.menu), i = items.indexOf(document.activeElement);
      if (!items.length) return;
      e.preventDefault();
      i = e.key === 'ArrowDown' ? (i + 1) % items.length : (i <= 0 ? items.length - 1 : i - 1);
      focusQuiet(items[i]);
    }
  }, true);
  document.addEventListener('focusin', function (e) { if (openMenu && !openMenu.menu.contains(e.target) && !openMenu.btn.contains(e.target)) closeMenus(); });

  function deleteActivity(ev) {
    var name = F.flat(ev.name || ev.title) || VQ.t('this activity');
    return confirmBox({ title: VQ.t('Delete the activity?'), text: VQ.t('“{name}” is deleted for good, together with its ticket types. This cannot be undone.', { name: name }), ok: VQ.t('Delete the activity'), danger: true }).then(function (yes) {
      if (!yes) return false;
      return O.api('/organizer/events/' + encodeURIComponent(ev.id), { method: 'DELETE' }).then(function () {
        L.events = L.events.filter(function (x) { return String(x.id) !== String(ev.id); });
        O.flash(VQ.t('The activity was deleted.'));
        return true;
      }).catch(function (err) {
        if (!err || err.status !== 401) O.flash(errText(err, VQ.t('We could not delete the activity.')), true);
        return false;
      });
    });
  }

  root.addEventListener('click', function (e) {
    var t = e.target;
    if (!t || !t.closest) return;
    if (t.closest('[data-oe-new]')) { e.preventDefault(); navigate(LIST_URL + '?action=create'); return; }
    var edit = t.closest('a[data-oe-edit]');
    if (edit && !e.ctrlKey && !e.metaKey && !e.shiftKey && !e.altKey && e.button === 0) { e.preventDefault(); navigate(edit.getAttribute('href')); return; }
    if (t.closest('[data-oe-back]')) { e.preventDefault(); leaveEditor(); return; }
    var menuBtn = t.closest('[data-oe-menu]');
    if (menuBtn) { e.preventDefault(); toggleMenu(menuBtn); return; }
    var del = t.closest('[data-oe-delete]');
    if (del) {
      e.preventDefault();
      closeMenus();
      var ev = L.events.filter(function (x) { return String(x.id) === del.getAttribute('data-oe-delete'); })[0];
      if (ev) deleteActivity(ev).then(function (done) { if (done) { renderList(); focusQuiet($('oe-list-h')); } });
      return;
    }
    var pill = t.closest('.oe-pill');
    if (pill) { setStatus(pill.getAttribute('data-status')); return; }
    var pick = t.closest('[data-media-pick]');
    if (pick) { $('oe-' + pick.getAttribute('data-media-pick') + '-file').click(); return; }
    var undo = t.closest('[data-media-undo]');
    if (undo) {
      var kind = undo.getAttribute('data-media-undo');
      ED.files[kind] = null;
      revoke(kind);
      renderMedia(kind);
      refreshSoon();
      focusQuiet(document.querySelector('[data-media-pick="' + kind + '"]') || $('oe-' + kind + '-file'));
    }
  });
  $('oe-q').addEventListener('input', debounce(function () { L.q = $('oe-q').value.trim().slice(0, 100); syncListUrl(); renderList(); }, 150));

  /* =================== EDITOR =================== */
  var ED = {
    id: null, url: '', event: null, status: 'new', published: false, allowLive: false, flags: {}, dirty: false, filling: false,
    saving: false, loadingId: null, files: { poster: null, cover: null }, server: { poster: null, cover: null }, orig: {},
    genresAvail: [], genres: [], artists: [], venueId: null, locked: [], free: [], sentRows: [],
  };
  var HEAD_STATUS = { published: [VQ.t('Published'), 'is-ok'], draft: [VQ.t('Draft'), 'is-muted'], pending_review: [VQ.t('Under review'), 'is-info'], rejected: [VQ.t('Rejected'), 'is-bad'], cancelled: [VQ.t('Cancelled'), 'is-bad'], postponed: [VQ.t('Postponed'), 'is-wait'], ended: [VQ.t('Ended'), 'is-muted'] };
  var TEXT_FIELDS = ['oe-name', 'oe-short', 'oe-tags', 'oe-start-date', 'oe-start-time', 'oe-end-date', 'oe-end-time', 'oe-end-time-single', 'oe-door-time', 'oe-door-time-range', 'oe-venue', 'oe-city', 'oe-address', 'oe-website', 'oe-facebook', 'oe-video', 'oe-capacity', 'oe-max-order', 'oe-sales-start', 'oe-sales-end'];

  function markDirty() { if (!ED.filling && view === 'editor') ED.dirty = true; }
  function markClean() { ED.dirty = false; }

  function openEditor(id) {
    closeMenus();
    var same = view === 'editor' && String(ED.id || '') === String(id || '') && !ED.loadingId;
    view = 'editor';
    $('oe-list').hidden = true;
    $('oe-editor').hidden = false;
    ED.url = location.pathname + location.search;
    if (same) return;
    window.scrollTo(0, 0);
    resetEditor();
    ensureCategories();
    ensureTinyMce();
    initSpy();
    if (!id) { refreshAll(); focusQuiet($('oe-ed-h')); return; }
    loadForEdit(String(id));
  }

  function setLoading(on) {
    $('oe-bar').classList.toggle('is-loading', on);
    $('oe-form').classList.toggle('is-loading', on);
    $('oe-form').setAttribute('aria-busy', String(on));
    qsa('[data-oe-save],[data-oe-submit],#oe-preview-open').forEach(function (b) { b.disabled = on; });
  }
  function loadForEdit(id) {
    ED.loadingId = id;
    setLoading(true);
    $('oe-ed-h').textContent = VQ.t('Loading the activity…');
    function abandon(message) {
      ED.loadingId = null;
      setLoading(false);
      O.flash(message, true);
      navigate(lastListUrl, true);
    }
    O.api('/organizer/events/' + encodeURIComponent(id)).then(function (r) {
      if (ED.loadingId !== id) return null;
      var ev = r && r.data && (r.data.event || r.data);
      if (!ev || ev.id == null) throw { status: 404 };
      if (ev.is_editable === false || ev.is_past === true) {
        abandon(VQ.t('This activity has ended or was cancelled and can no longer be edited.'));
        return null;
      }
      return ensureCategories().then(function () { if (ED.loadingId === id) fillEditor(ev); });
    }).catch(function (err) {
      if (ED.loadingId !== id) return;
      if (err && err.status === 401) { ED.loadingId = null; return; }
      abandon(err && err.status === 404 ? VQ.t('The activity was not found.') : err && err.status === 403 ? VQ.t('You do not have access to this activity.') : errText(err, VQ.t('We could not load the activity.')));
    }).then(function () {
      if (ED.loadingId !== id) return;
      ED.loadingId = null;
      setLoading(false);
      refreshAll();
      focusQuiet($('oe-ed-h'));
    });
  }

  function resetEditor() {
    ED.filling = true;
    ED.id = null; ED.event = null; ED.status = 'new'; ED.published = false; ED.allowLive = false; ED.flags = {};
    ED.files = { poster: null, cover: null }; ED.server = { poster: null, cover: null }; ED.orig = {};
    ED.genres = []; ED.genresAvail = []; ED.artists = []; ED.venueId = null; ED.locked = []; ED.free = []; ED.sentRows = [];
    clearErrors();
    hideAlert();
    TEXT_FIELDS.forEach(function (fid) { setVal(fid, ''); });
    $('oe-category').value = '';
    genreSeq++;
    $('oe-genres-f').hidden = true;
    setMode('single_day');
    renderChips('genres');
    renderChips('artists');
    $('oe-venue-notice').hidden = true;
    setRich('desc', '');
    setRich('terms', '');
    ['poster', 'cover'].forEach(function (k) { $('oe-' + k + '-file').value = ''; revoke(k); renderMedia(k); });
    $('oe-tt-list').textContent = '';
    addTicketRow(null);
    renderLocked();
    ['oe-rejected', 'oe-review', 'oe-changes', 'oe-status', 'oe-delete'].forEach(function (x) { $(x).hidden = true; });
    $('oe-saved').textContent = '';
    qsa('.oe-sec', root).forEach(function (sec) { setOpen(sec, sec.getAttribute('data-step') === '1'); });
    setActionLabels();
    ED.dirty = false;
    ED.filling = false;
  }

  function flagsOf(ev) { return { sold: !!ev.is_sold_out, door: !!(ev.door_sales_only || ev.is_door_sales_only), postponed: !!ev.is_postponed, cancelled: !!ev.is_cancelled }; }
  function datesOf(d) { return { starts_at: d.starts_at || null, ends_at: d.ends_at || null, doors_open_at: d.doors_open_at || null, duration_mode: d.duration_mode }; }

  function fillEditor(ev) {
    ED.filling = true;
    ED.id = String(ev.id);
    ED.event = ev;
    ED.status = ev.status || 'draft';
    ED.published = !!(ev.is_public || ev.status === 'published');
    ED.allowLive = !!ev.allow_live_edits;
    ED.flags = flagsOf(ev);

    setVal('oe-name', F.flat(ev.name || ev.title));
    setVal('oe-short', F.flat(ev.short_description));
    setVal('oe-tags', Array.isArray(ev.tags) ? ev.tags.join(', ') : (typeof ev.tags === 'string' ? ev.tags : ''));
    var sel = $('oe-category');
    if (ev.marketplace_event_category_id != null) {
      var catId = String(ev.marketplace_event_category_id);
      sel.value = catId;
      if (sel.value !== catId) { // a category no longer offered: keep it so saving doesn't drop it
        sel.appendChild(el('option', { value: catId, text: F.flat(ev.category) || VQ.t('Current category') }));
        sel.value = catId;
      }
    }
    ED.genres = (Array.isArray(ev.genres) ? ev.genres : []).map(function (g) { return { id: g.id, name: F.flat(g.name) }; });
    renderChips('genres');
    loadGenres(ev.marketplace_event_category_id, true);
    ED.artists = (Array.isArray(ev.artists) ? ev.artists : []).map(function (a) { return { id: a.id, name: F.flat(a.name) }; });
    renderChips('artists');

    var s = naive(ev.starts_at), en = naive(ev.ends_at), dr = naive(ev.doors_open_at);
    var mode = (ev.duration_mode === 'range' || ev.duration_mode === 'multi_day' || (s && en && en.date !== s.date)) ? 'range' : 'single_day';
    setMode(mode);
    if (s) { setVal('oe-start-date', s.date); setVal('oe-start-time', s.time); }
    if (mode === 'range') {
      if (en) { setVal('oe-end-date', en.date); if (en.time && en.time !== '23:59') setVal('oe-end-time', en.time); } // 23:59 is core's "no end time"
      if (dr) setVal('oe-door-time-range', dr.time);
    } else {
      if (en && s && en.date === s.date && en.time && en.time !== '23:59') setVal('oe-end-time-single', en.time);
      if (dr) setVal('oe-door-time', dr.time);
    }

    setVal('oe-venue', F.flat(ev.venue_name));
    setVal('oe-city', F.flat(ev.venue_city));
    setVal('oe-address', F.flat(ev.venue_address));
    ED.venueId = ev.venue_id != null ? ev.venue_id : null;
    setVal('oe-website', ev.event_website_url || ev.website_url || '');
    setVal('oe-facebook', ev.facebook_url || '');
    setVal('oe-video', ev.video_url || '');

    setRich('desc', F.flat(ev.description));
    setRich('terms', F.flat(ev.ticket_terms));

    ED.server.poster = ev.image || null;
    ED.server.cover = ev.cover_image || null;
    renderMedia('poster');
    renderMedia('cover');

    var types = Array.isArray(ev.ticket_types) ? ev.ticket_types.filter(Boolean) : [];
    ED.free = types.filter(function (t) { return t.free_with_code; }); // set by an administrator: never sent back
    var regular = types.filter(function (t) { return !t.free_with_code; });
    $('oe-tt-list').textContent = '';
    if (ED.published) ED.locked = regular;
    else {
      ED.locked = [];
      regular.forEach(function (t) { addTicketRow(t); });
      if (!regular.length) addTicketRow(null);
    }
    renderLocked();
    if (F.toNum(ev.capacity) > 0) setVal('oe-capacity', String(Math.round(F.toNum(ev.capacity))));

    $('oe-rejected').hidden = ED.status !== 'rejected';
    $('oe-rejected-reason').textContent = F.flat(ev.rejection_reason) || VQ.t('not given');
    $('oe-review').hidden = ED.status !== 'pending_review';
    $('oe-changes').hidden = !ev.has_pending_changes;
    $('oe-status').hidden = !ED.published;
    renderFlags();
    $('oe-delete').hidden = !(AWAITING.indexOf(ED.status) > -1 && !ED.published);

    ED.orig = datesOf(collect().data);
    setActionLabels();
    refreshAll();
    ED.dirty = false;
    ED.filling = false;
  }

  /* ---------- schedule ---------- */
  function modeValue() { var r = document.querySelector('input[name="oe-duration"]:checked'); return r ? r.value : 'single_day'; }
  function setMode(mode) {
    qsa('input[name="oe-duration"]', root).forEach(function (r) { r.checked = r.value === mode; });
    var range = mode === 'range';
    $('oe-range-end').hidden = !range;
    $('oe-range-door').hidden = !range;
    $('oe-single-end').hidden = range;
    $('oe-dm-hint').hidden = true;
    var lab = root.querySelector('[data-range-label]');
    if (lab) {
      if (!lab.getAttribute('data-single')) lab.setAttribute('data-single', lab.textContent);
      lab.textContent = range ? lab.getAttribute('data-range-label') : lab.getAttribute('data-single');
    }
  }

  /* ---------- rich text ---------- */
  var RICH = { desc: 'oe-description', terms: 'oe-terms' }, editors = {}, pendingRich = {}, tinyLoad = null;
  function setRich(key, html) {
    var ed = editors[key];
    if (ed) { var was = ED.filling; ED.filling = true; ed.setContent(html || ''); ED.filling = was; return; }
    $(RICH[key]).value = html || '';
    pendingRich[key] = html || '';
  }
  function getRich(key) {
    var ed = editors[key], v = ed ? ed.getContent() : $(RICH[key]).value;
    return richEmpty(v) ? '' : v;
  }
  function ensureTinyMce() {
    if (tinyLoad) return tinyLoad;
    tinyLoad = new Promise(function (resolve) {
      if (window.tinymce) { resolve(window.tinymce); return; }
      var s = document.createElement('script');
      s.src = TINY + '/tinymce.min.js';
      s.referrerPolicy = 'origin';
      s.onload = function () { resolve(window.tinymce || null); };
      s.onerror = function () { resolve(null); };
      document.head.appendChild(s);
      setTimeout(function () { resolve(window.tinymce || null); }, 20000);
    }).then(function (tm) {
      if (!tm) return null; // no editor: the plain textareas stay and are saved as they are
      var font = location.origin + '/assets/v2/fonts/Geist-latin.woff2';
      var base = {
        base_url: TINY, suffix: '.min', menubar: false, statusbar: false, branding: false, promotion: false, license_key: 'gpl',
        plugins: 'lists link autolink', toolbar: 'bold italic underline | bullist numlist | link | hr | undo redo | removeformat',
        content_style: "@font-face{font-family:Geist;src:url('" + font + "') format('woff2');font-weight:100 900}body{font-family:Geist,system-ui,sans-serif;font-size:15px;line-height:1.6;color:#212121;margin:12px 14px}",
        paste_as_text: false, smart_paste: true,
      };
      function setup(key) {
        return function (editor) {
          editor.on('init', function () {
            editors[key] = editor;
            if (pendingRich[key] != null) { var was = ED.filling; ED.filling = true; editor.setContent(pendingRich[key]); ED.filling = was; pendingRich[key] = null; }
          });
          editor.on('input change undo redo', function () {
            if (!ED.filling) markDirty();
            var ta = $(RICH[key]);
            if (ta && ta.getAttribute('aria-invalid')) clearErr(ta);
            refreshSoon();
          });
        };
      }
      return Promise.all([
        tm.init(Object.assign({}, base, {
          selector: '#oe-description', height: 300, placeholder: VQ.t('Write the description of the activity here…'), iframe_aria_text: VQ.t('Full description'),
          plugins: 'lists link autolink media', toolbar: 'bold italic underline | bullist numlist | link media | hr | undo redo | removeformat',
          extended_valid_elements: 'iframe[src|frameborder|style|scrolling|class|width|height|name|align|allow|allowfullscreen|loading|referrerpolicy|title]',
          valid_children: '+body[iframe],+div[iframe]', media_live_embeds: true, media_alt_source: false, media_poster: false, setup: setup('desc'),
        })),
        tm.init(Object.assign({}, base, { selector: '#oe-terms', height: 230, placeholder: VQ.t('Terms of participation, restrictions, refund policy…'), iframe_aria_text: VQ.t('Activity terms'), setup: setup('terms') })),
      ]).then(function () { return tm; }, function () { return null; });
    });
    return tinyLoad;
  }

  /* ---------- category, genres, artists, venue ---------- */
  var CATS = null, catsLoad = null, genreSeq = 0;
  function ensureCategories() {
    if (CATS) return Promise.resolve(CATS);
    if (catsLoad) return catsLoad;
    catsLoad = O.api('/organizer/event-categories').then(function (r) {
      CATS = (r && r.data && Array.isArray(r.data.categories)) ? r.data.categories : [];
      var sel = $('oe-category'), cur = sel.value;
      qsa('option', sel).forEach(function (o, i) { if (i) o.remove(); });
      CATS.forEach(function (c) { sel.appendChild(el('option', { value: String(c.id), text: (c.icon_emoji ? c.icon_emoji + ' ' : '') + F.flat(c.name) })); });
      if (cur) sel.value = cur;
      return CATS;
    }).catch(function () { catsLoad = null; return []; });
    return catsLoad;
  }
  function loadGenres(catId, keep) {
    var my = ++genreSeq;
    var cat = (CATS || []).filter(function (c) { return String(c.id) === String(catId); })[0];
    var types = cat && Array.isArray(cat.event_type_ids) ? cat.event_type_ids : [];
    if (!catId || !types.length) {
      ED.genresAvail = [];
      if (!keep) ED.genres = [];
      $('oe-genres-f').hidden = !ED.genres.length;
      renderChips('genres');
      return Promise.resolve();
    }
    return O.api('/organizer/event-genres?type_ids=' + types.map(function (t) { return encodeURIComponent(t); }).join(',')).then(function (r) {
      if (my !== genreSeq) return;
      ED.genresAvail = (r && r.data && Array.isArray(r.data.genres)) ? r.data.genres : [];
      if (!keep) ED.genres = ED.genres.filter(function (g) { return ED.genresAvail.some(function (a) { return a.id === g.id; }); });
      $('oe-genres-f').hidden = !ED.genresAvail.length && !ED.genres.length;
      renderChips('genres');
    }).catch(function () { if (my === genreSeq) $('oe-genres-f').hidden = !ED.genres.length; });
  }
  function renderChips(kind) {
    var list = $('oe-' + kind + '-chips');
    list.textContent = '';
    ED[kind].forEach(function (it) {
      var b = el('button', { type: 'button', 'aria-label': VQ.t('Remove {name}', { name: it.name }) }, icon('x'));
      b.addEventListener('click', function () {
        ED[kind] = ED[kind].filter(function (x) { return x.id !== it.id; });
        renderChips(kind);
        markDirty();
        refreshSoon();
        focusQuiet($('oe-' + kind + '-q'));
      });
      list.appendChild(el('li', { class: 'oe-chip' }, [el('span', { text: it.name }), b]));
    });
  }
  function addArtist(a) {
    if (ED.artists.some(function (x) { return x.id === a.id; })) return;
    ED.artists.push({ id: a.id, name: F.flat(a.name) });
    renderChips('artists');
    markDirty();
    refreshSoon();
  }

  var combos = [];
  function closeCombos() { combos.forEach(function (c) { c.close(); }); }
  function combo(input, list, cfg) {
    var items = [], active = -1, timer = 0, seq = 0;
    function open(on) {
      list.hidden = !on;
      input.setAttribute('aria-expanded', String(on));
      if (!on) { active = -1; input.removeAttribute('aria-activedescendant'); }
    }
    function paint() {
      [].forEach.call(list.children, function (li, i) { if (li.getAttribute('role') === 'option') li.setAttribute('aria-selected', String(i === active)); });
      var cur = list.children[active];
      if (cur) { input.setAttribute('aria-activedescendant', cur.id); cur.scrollIntoView({ block: 'nearest' }); }
    }
    function show(rows, message) {
      list.textContent = '';
      items = rows || [];
      active = -1;
      input.removeAttribute('aria-activedescendant');
      items.forEach(function (it, i) {
        var li = el('li', { class: 'oe-opt' + (it.create ? ' is-create' : ''), role: 'option', id: list.id + '-o' + i, 'aria-selected': 'false' }, cfg.render(it));
        li.addEventListener('mousedown', function (e) { e.preventDefault(); });
        li.addEventListener('click', function () { pick(i); });
        list.appendChild(li);
      });
      if (message) list.appendChild(el('li', { class: 'oe-opt-msg', role: 'presentation', text: message }));
      open(items.length > 0 || !!message);
    }
    function pick(i) { var it = items[i]; if (!it) return; open(false); cfg.onPick(it, api); }
    function run() {
      var q = input.value.trim(), my = ++seq;
      if (q.length < (cfg.min || 0)) { open(false); return; }
      if (cfg.loading) show([], VQ.t('Searching…'));
      Promise.resolve().then(function () { return cfg.fetch(q); }).then(function (res) {
        if (my === seq && document.activeElement === input) show(res.rows, res.message);
      }, function () { if (my === seq && document.activeElement === input) show([], VQ.t('The search did not work. Try again.')); });
    }
    input.addEventListener('input', function () {
      clearTimeout(timer);
      if (cfg.onInput) cfg.onInput();
      if (input.value.trim().length < (cfg.min || 0)) { seq++; open(false); return; }
      timer = setTimeout(run, cfg.delay || 0);
    });
    input.addEventListener('focus', function () { if (cfg.openOnFocus || input.value.trim().length >= (cfg.min || 0)) run(); });
    input.addEventListener('keydown', function (e) {
      if (e.key === 'ArrowDown' || e.key === 'ArrowUp') {
        if (list.hidden) { if (cfg.openOnFocus || input.value.trim().length >= (cfg.min || 0)) run(); return; }
        if (!items.length) return;
        e.preventDefault();
        active = e.key === 'ArrowDown' ? (active + 1) % items.length : (active <= 0 ? items.length - 1 : active - 1);
        paint();
      } else if (e.key === 'Enter') {
        if (!list.hidden && active >= 0) { e.preventDefault(); pick(active); }
      } else if (e.key === 'Escape') {
        if (!list.hidden) { e.preventDefault(); e.stopPropagation(); open(false); }
      } else if (e.key === 'Backspace' && !input.value && cfg.onBackspace) cfg.onBackspace();
    });
    input.addEventListener('blur', function () { setTimeout(function () { if (document.activeElement !== input) open(false); }, 150); });
    var api = { close: function () { open(false); }, run: run, input: input };
    combos.push(api);
    return api;
  }

  combo($('oe-genres-q'), $('oe-genres-list'), {
    min: 0, openOnFocus: true,
    fetch: function (q) {
      var t = norm(q);
      var rows = ED.genresAvail.filter(function (g) { return !ED.genres.some(function (s) { return s.id === g.id; }) && (!t || norm(F.flat(g.name)).indexOf(t) > -1); });
      return { rows: rows, message: rows.length ? '' : (ED.genresAvail.length ? VQ.t('No genre matches.') : VQ.t('The chosen category has no genres.')) };
    },
    render: function (g) { return [el('b', { text: F.flat(g.name) })]; },
    onPick: function (g, c) {
      ED.genres.push({ id: g.id, name: F.flat(g.name) });
      renderChips('genres');
      markDirty();
      refreshSoon();
      c.input.value = '';
      c.run();
    },
    onBackspace: function () { if (ED.genres.length) { ED.genres.pop(); renderChips('genres'); markDirty(); refreshSoon(); } },
  });
  combo($('oe-artists-q'), $('oe-artists-list'), {
    min: 2, delay: 300, loading: true,
    fetch: function (q) {
      return O.api('/organizer/artists?search=' + encodeURIComponent(q)).then(function (r) {
        var rows = ((r && r.data && r.data.artists) || []).filter(function (a) { return !ED.artists.some(function (s) { return s.id === a.id; }); });
        if (!rows.some(function (a) { return norm(F.flat(a.name)) === norm(q); })) rows = rows.concat([{ create: true, name: q }]);
        return { rows: rows };
      });
    },
    render: function (a) { return a.create ? [el('b', { text: VQ.t('+ Add “{name}” as a new artist', { name: a.name }) })] : [el('b', { text: F.flat(a.name) })]; },
    onPick: function (a, c) {
      if (!a.create) { addArtist(a); c.input.value = ''; return; }
      c.input.disabled = true;
      O.api('/organizer/artists', { method: 'POST', body: { name: a.name } }).then(function (r) {
        var art = r && r.data && r.data.artist;
        if (art) { addArtist(art); c.input.value = ''; O.flash(VQ.t('The artist “{name}” was added.', { name: F.flat(art.name) })); }
      }).catch(function (err) {
        if (!err || err.status !== 401) O.flash(errText(err, VQ.t('We could not add the artist.')), true);
      }).then(function () { c.input.disabled = false; focusQuiet(c.input); });
    },
    onBackspace: function () { if (ED.artists.length) { ED.artists.pop(); renderChips('artists'); markDirty(); refreshSoon(); } },
  });
  combo($('oe-venue'), $('oe-venue-list'), {
    min: 2, delay: 300, loading: true,
    onInput: function () { ED.venueId = null; $('oe-venue-notice').hidden = true; },
    fetch: function (q) {
      return O.api('/organizer/venues?search=' + encodeURIComponent(q)).then(function (r) {
        var rows = (r && r.data && Array.isArray(r.data.venues)) ? r.data.venues : [];
        $('oe-venue-notice').hidden = ED.venueId != null || rows.some(function (v) { return norm(F.flat(v.name)) === norm(q); });
        return { rows: rows, message: rows.length ? '' : VQ.t('No results found') };
      });
    },
    render: function (v) {
      return [
        el('b', null, [F.flat(v.name), v.is_marketplace ? el('span', { class: 'oe-partner', text: VQ.t('Viaqui partner') }) : null]),
        (v.city || v.address) ? el('small', { text: [F.flat(v.city), F.flat(v.address)].filter(Boolean).join(' – ') }) : null,
      ];
    },
    onPick: function (v) {
      ED.venueId = v.id;
      setVal('oe-venue', F.flat(v.name));
      if (v.city) setVal('oe-city', F.flat(v.city));
      if (v.address) setVal('oe-address', F.flat(v.address));
      $('oe-venue-notice').hidden = true;
      clearErr($('oe-venue'));
      clearErr($('oe-city'));
      markDirty();
      refreshSoon();
    },
  });
  ['genres', 'artists'].forEach(function (kind) {
    var box = $('oe-' + kind + '-box');
    box.addEventListener('mousedown', function (e) {
      if (e.target === box || e.target === $('oe-' + kind + '-chips')) { e.preventDefault(); focusQuiet($('oe-' + kind + '-q')); }
    });
  });
  $('oe-category').addEventListener('change', function () { loadGenres($('oe-category').value, false); });

  /* ---------- media ---------- */
  var objectUrls = {};
  function revoke(kind) { if (objectUrls[kind]) { URL.revokeObjectURL(objectUrls[kind]); objectUrls[kind] = null; } }
  function pickFile(kind, file) {
    var input = $('oe-' + kind + '-file');
    clearErr(input);
    if (!file) return;
    if (['image/jpeg', 'image/png', 'image/webp'].indexOf(file.type) < 0) { showErr(input, VQ.t('Choose a JPG, PNG or WebP image.')); return; }
    if (file.size > 10 * 1024 * 1024) { showErr(input, VQ.t('The image is {size} MB; the limit is 10 MB.', { size: new Intl.NumberFormat(LOC, { minimumFractionDigits: 1, maximumFractionDigits: 1 }).format(file.size / 1048576) })); return; }
    ED.files[kind] = file;
    revoke(kind);
    objectUrls[kind] = URL.createObjectURL(file);
    renderMedia(kind);
    markDirty();
    refreshSoon();
  }
  function renderMedia(kind) {
    var file = ED.files[kind], server = ED.server[kind];
    var src = file ? objectUrls[kind] : (server ? O.img(server) : '');
    $('oe-' + kind + '-preview').hidden = !src;
    $('oe-' + kind + '-drop').hidden = !!src;
    var img = $('oe-' + kind + '-img');
    if (src) { if (img.getAttribute('src') !== src) img.setAttribute('src', src); } else img.removeAttribute('src');
    root.querySelector('[data-media-undo="' + kind + '"]').hidden = !file;
    $('oe-' + kind + '-note').textContent = file ? VQ.t('New image: {name}. It uploads when you save.', { name: file.name }) : (server ? VQ.t('The saved image stays until you choose another.') : '');
  }
  ['poster', 'cover'].forEach(function (kind) {
    var input = $('oe-' + kind + '-file'), drop = $('oe-' + kind + '-drop');
    input.addEventListener('change', function () { var f = input.files && input.files[0]; pickFile(kind, f); input.value = ''; });
    ['dragenter', 'dragover'].forEach(function (t) { drop.addEventListener(t, function (e) { e.preventDefault(); drop.classList.add('is-over'); }); });
    ['dragleave', 'drop'].forEach(function (t) { drop.addEventListener(t, function (e) { e.preventDefault(); drop.classList.remove('is-over'); }); });
    drop.addEventListener('drop', function (e) { var f = e.dataTransfer && e.dataTransfer.files && e.dataTransfer.files[0]; if (f) pickFile(kind, f); });
    $('oe-' + kind + '-img').addEventListener('error', function () {
      if (!ED.files[kind] && ED.server[kind]) $('oe-' + kind + '-note').textContent = VQ.t('The saved image cannot be shown right now. It stays saved until you choose another.');
    });
  });
  ['dragover', 'drop'].forEach(function (t) { // a file dropped next to a drop zone must not open in the tab
    window.addEventListener(t, function (e) { if (view === 'editor' && !(e.target && e.target.closest && e.target.closest('.oe-drop'))) e.preventDefault(); });
  });

  /* ---------- ticket types ---------- */
  var ttUid = 0;
  function addTicketRow(t, focus) {
    var node = $('oe-tt-tpl').content.firstElementChild.cloneNode(true), uid = 'oe-tt' + (++ttUid);
    node.id = uid;
    qsa('[data-f]', node).forEach(function (inp) {
      var f = inp.getAttribute('data-f'), fid = uid + '-' + f, box = inp.closest('.oe-f');
      inp.id = fid;
      box.querySelector('label').setAttribute('for', fid);
      box.querySelector('.oe-err').id = fid + '-err';
      inp.setAttribute('aria-describedby', fid + '-err');
    });
    if (t) {
      var g = function (f) { return node.querySelector('[data-f="' + f + '"]'); };
      g('name').value = F.flat(t.name);
      g('price').value = t.price != null ? String(Math.round(F.toNum(t.price) * 100) / 100) : '';
      g('quantity').value = F.toNum(t.quantity) > 0 ? String(Math.round(F.toNum(t.quantity))) : ''; // -1 / 0 = unlimited, which core rejects as a stock
      g('description').value = F.flat(t.description);
      if (F.toNum(t.min_per_order) > 1) g('min').value = String(t.min_per_order);
      if (t.max_per_order != null && F.toNum(t.max_per_order) > 0 && F.toNum(t.max_per_order) !== 10) g('max').value = String(t.max_per_order);
    }
    $('oe-tt-list').appendChild(node);
    renumberTickets();
    if (focus) focusQuiet(node.querySelector('[data-f="name"]'));
    return node;
  }
  function renumberTickets() {
    var rows = qsa('#oe-tt-list [data-tt]');
    rows.forEach(function (row, i) {
      var title = ED.published ? VQ.t('New ticket type #{n}', { n: i + 1 }) : VQ.t('Ticket type #{n}', { n: i + 1 });
      row.querySelector('[data-tt-title]').textContent = title;
      var rm = row.querySelector('[data-tt-remove]');
      rm.hidden = !ED.published && rows.length < 2;
      rm.querySelector('.sr').textContent = VQ.t('Remove: {title}', { title: title });
    });
  }
  function renderLocked() {
    var list = $('oe-tt-locked-list');
    list.textContent = '';
    var rows = ED.locked.map(function (t) { return [t, false]; }).concat(ED.free.map(function (t) { return [t, true]; }));
    $('oe-tt-locked').hidden = !rows.length;
    $('oe-tt-locked-note').hidden = !(ED.published && ED.locked.length);
    $('oe-tt-locked-t').textContent = ED.allowLive
      ? VQ.t('Existing ticket types can no longer be changed after publishing, so that sold tickets are not affected. You can add new types.')
      : VQ.t('Existing ticket types can no longer be changed after publishing, so that sold tickets are not affected. You can add new types; they apply after approval.');
    rows.forEach(function (pair) {
      var t = pair[0], free = pair[1], q = F.toNum(t.quantity);
      var sub = free ? VQ.t('Free ticket with a code · managed by the administrator') : [q > 0 ? VQ.t('Stock: {n}', { n: F.num(q) }) : VQ.t('Unlimited stock'), F.flat(t.description)].filter(Boolean).join(' · ');
      list.appendChild(el('li', { class: 'oe-lk' }, [
        el('span', { class: 'oe-lk-ic', 'aria-hidden': 'true' }, icon(free ? 'gift' : 'lock-simple')),
        el('div', { class: 'oe-lk-t' }, [el('b', { text: F.flat(t.name) || VQ.t('Ticket') }), el('span', { text: sub })]),
        el('div', { class: 'oe-lk-v' }, [
          el('b', { text: F.money(t.price) }),
          t.is_sold_out ? el('span', { class: 'org-tag is-bad', text: VQ.t('Sold out') }) : el('span', { class: 'org-tag is-muted', text: VQ.t('Sold: {n}', { n: F.num(t.quantity_sold) }) }),
        ]),
      ]));
    });
    renumberTickets();
  }
  $('oe-tt-add').addEventListener('click', function () {
    clearErr($('oe-tt-add'));
    addTicketRow(null, true);
    markDirty();
    refreshSoon();
  });
  $('oe-tt-list').addEventListener('click', function (e) {
    var rm = e.target.closest && e.target.closest('[data-tt-remove]');
    if (!rm) return;
    var row = rm.closest('[data-tt]'), prev = row.previousElementSibling;
    row.remove();
    renumberTickets();
    markDirty();
    refreshSoon();
    focusQuiet(prev ? prev.querySelector('[data-f="name"]') : $('oe-tt-add'));
  });

  /* ---------- collect + validate ---------- */
  function rowData(row) {
    var g = function (f) { return row.querySelector('[data-f="' + f + '"]').value.trim(); };
    var name = g('name'), price = g('price');
    if (!name || price === '') return null;
    var t = { name: name, price: Math.round(parseFloat(price) * 100) / 100 };
    if (g('quantity')) t.quantity = parseInt(g('quantity'), 10);
    if (g('description')) t.description = g('description');
    if (g('min')) t.min_per_order = parseInt(g('min'), 10);
    if (g('max')) t.max_per_order = parseInt(g('max'), 10);
    return t;
  }
  function collect() {
    var mode = modeValue(), sd = val('oe-start-date'), st = val('oe-start-time');
    var d = { name: val('oe-name').trim(), duration_mode: mode, venue_name: val('oe-venue').trim(), venue_city: val('oe-city').trim() };
    if (sd) d.starts_at = sd + 'T' + (st || '00:00') + ':00';
    if (mode === 'range') {
      var ed = val('oe-end-date'), et = val('oe-end-time'), drr = val('oe-door-time-range');
      if (ed) d.ends_at = ed + 'T' + (et || '23:59') + ':00';
      if (drr && sd) d.doors_open_at = sd + 'T' + drr + ':00';
    } else {
      var ets = val('oe-end-time-single'), dr = val('oe-door-time');
      if (ets && sd) d.ends_at = sd + 'T' + ets + ':00';
      if (dr && sd) d.doors_open_at = sd + 'T' + dr + ':00';
    }
    var cat = val('oe-category');
    if (cat) d.marketplace_event_category_id = parseInt(cat, 10);
    if (ED.venueId != null) d.venue_id = parseInt(ED.venueId, 10);
    d.genre_ids = ED.genres.map(function (g) { return g.id; });
    d.artist_ids = ED.artists.map(function (a) { return a.id; });
    d.short_description = val('oe-short').trim();
    d.description = getRich('desc');
    d.ticket_terms = getRich('terms');
    var tags = val('oe-tags').split(',').map(function (s) { return s.trim(); }).filter(Boolean);
    if (tags.length) d.tags = tags;
    [['oe-address', 'venue_address'], ['oe-website', 'event_website_url'], ['oe-facebook', 'facebook_url'], ['oe-video', 'video_url']].forEach(function (p) {
      var v = val(p[0]).trim();
      if (v) d[p[1]] = v;
    });
    var capacity = val('oe-capacity').trim(), maxOrder = val('oe-max-order').trim();
    if (capacity) d.capacity = parseInt(capacity, 10);
    if (maxOrder) d.max_tickets_per_order = parseInt(maxOrder, 10);
    if (val('oe-sales-start')) d.sales_start_at = val('oe-sales-start');
    if (val('oe-sales-end')) d.sales_end_at = val('oe-sales-end');
    var rows = [], types = [];
    qsa('#oe-tt-list [data-tt]').forEach(function (row) { var t = rowData(row); if (t) { rows.push(row); types.push(t); } });
    if (types.length) d.ticket_types = types;
    return { data: d, rows: rows };
  }

  var errored = [];
  function showErr(input, msg) {
    var err = input && $(input.id + '-err');
    if (!input || !err) return;
    err.textContent = msg;
    err.hidden = false;
    input.setAttribute('aria-invalid', 'true');
    var box = input.closest('.oe-f');
    if (box) box.classList.add('is-invalid');
    if (errored.indexOf(input) < 0) errored.push(input);
  }
  function clearErr(input) {
    if (!input) return;
    var err = $(input.id + '-err');
    if (err) { err.hidden = true; err.textContent = ''; }
    input.removeAttribute('aria-invalid');
    var box = input.closest('.oe-f');
    if (box) box.classList.remove('is-invalid');
  }
  function clearErrors() { errored.forEach(clearErr); errored = []; }
  function setOpen(sec, on) {
    sec.setAttribute('data-open', String(on));
    var b = sec.querySelector('.oe-sec-btn');
    if (b) b.setAttribute('aria-expanded', String(on));
  }
  function openSection(n) { var s = $('oe-s' + n); if (s) setOpen(s, true); }
  function focusField(input) {
    if (!input) return;
    var key = input.id === RICH.desc ? 'desc' : input.id === RICH.terms ? 'terms' : null;
    var target = key && editors[key] ? editors[key].getContainer() : input;
    if (target.scrollIntoView) target.scrollIntoView({ block: 'center', behavior: reduced ? 'auto' : 'smooth' });
    if (key && editors[key]) editors[key].focus(); else focusQuiet(input);
  }
  function report(problems) {
    problems.forEach(function (p) { showErr(p[0], p[1]); });
    var first = problems[0];
    openSection(first[2]);
    O.flash(problems.length === 1 ? first[1] : VQ.t('Check the {count}.', { count: VQ.n(problems.length, 'marked field', 'marked fields') }), true);
    focusField(first[0]);
  }
  function startsUnchanged() { var d = collect().data; return (d.starts_at || null) === ED.orig.starts_at; }
  function validate(level) {
    clearErrors();
    var problems = [];
    function bad(input, msg, step) { if (input) problems.push([input, msg, step]); }
    if (!val('oe-name').trim()) bad($('oe-name'), VQ.t('Enter the activity name.'), 1);
    var short = val('oe-short').trim();
    if (words(short) > 120) bad($('oe-short'), VQ.t('The short description has {count}; the limit is 120.', { count: VQ.n(words(short), 'word', 'words') }), 1);
    else if (short.length > 500) bad($('oe-short'), VQ.t('The short description has {count}; the limit is 500.', { count: VQ.n(short.length, 'character', 'characters') }), 1);
    var sd = val('oe-start-date'), st = val('oe-start-time'), mode = modeValue();
    if (level === 'full') {
      if (!sd) bad($('oe-start-date'), VQ.t('Choose the activity date.'), 2);
      if (!st) bad($('oe-start-time'), VQ.t('Choose the start time.'), 2);
      if (mode === 'range' && !val('oe-end-date')) bad($('oe-end-date'), VQ.t('Choose the end date.'), 2);
      if (sd && st && (sd + 'T' + st) <= nowNaive() && !(ED.published && startsUnchanged())) bad($('oe-start-date'), VQ.t('The start date and time must be in the future.'), 2);
    }
    if (mode === 'range' && sd && val('oe-end-date') && val('oe-end-date') < sd) bad($('oe-end-date'), VQ.t('The end date must be after the start date.'), 2);
    if (level === 'full') {
      if (!val('oe-venue').trim()) bad($('oe-venue'), VQ.t('Enter the venue name.'), 3);
      if (!val('oe-city').trim()) bad($('oe-city'), VQ.t('Enter the city.'), 3);
    }
    ['oe-website', 'oe-facebook', 'oe-video'].forEach(function (fid) {
      var v = val(fid).trim();
      if (v && !isUrl(v)) bad($(fid), VQ.t('Enter the full address, starting with https://.'), 3);
    });
    var rows = qsa('#oe-tt-list [data-tt]'), complete = 0;
    rows.forEach(function (row) {
      var g = function (f) { return row.querySelector('[data-f="' + f + '"]'); };
      var any = ['name', 'price', 'quantity', 'description', 'min', 'max'].some(function (f) { return g(f).value.trim(); });
      if (!any) return;
      var name = g('name').value.trim(), price = g('price').value.trim();
      if (!name) bad(g('name'), VQ.t('Enter the ticket name.'), 6);
      if (price === '') bad(g('price'), VQ.t('Enter the price (0 for free).'), 6);
      else if (!(parseFloat(price) >= 0)) bad(g('price'), VQ.t('The price cannot be negative.'), 6);
      ['quantity', 'min', 'max'].forEach(function (f) {
        var v = g(f).value.trim();
        if (v && !(/^\d+$/.test(v) && +v >= 1)) bad(g(f), VQ.t('Enter a whole number, at least 1.'), 6);
      });
      if (/^\d+$/.test(g('min').value.trim()) && /^\d+$/.test(g('max').value.trim()) && +g('min').value > +g('max').value) bad(g('max'), VQ.t('The maximum must be at least the minimum.'), 6);
      if (name && price !== '') complete++;
    });
    if (level === 'full' && complete + (ED.published ? ED.locked.length : 0) === 0) {
      var firstName = rows[0] && rows[0].querySelector('[data-f="name"]');
      bad(firstName && !firstName.value.trim() ? firstName : $('oe-tt-add'), VQ.t('Add at least one ticket type, with a name and a price.'), 6);
    }
    var capacity = val('oe-capacity').trim(), maxOrder = val('oe-max-order').trim();
    if (capacity && !(/^\d+$/.test(capacity) && +capacity >= 1)) bad($('oe-capacity'), VQ.t('Enter a whole number, at least 1.'), 7);
    if (maxOrder && !(/^\d+$/.test(maxOrder) && +maxOrder >= 1 && +maxOrder <= 50)) bad($('oe-max-order'), VQ.t('Choose a number between 1 and 50.'), 7);
    if (val('oe-sales-start') && val('oe-sales-end') && val('oe-sales-end') <= val('oe-sales-start')) bad($('oe-sales-end'), VQ.t('The end of sales must be after the start.'), 7);
    if (!problems.length) return true;
    report(problems);
    return false;
  }

  var SERVER_FIELDS = {
    name: ['oe-name', 1], short_description: ['oe-short', 1], tags: ['oe-tags', 1], marketplace_event_category_id: ['oe-category', 1], genre_ids: ['oe-genres-q', 1], artist_ids: ['oe-artists-q', 1],
    starts_at: ['oe-start-date', 2], doors_open_at: ['oe-door-time', 2], duration_mode: ['oe-start-date', 2],
    venue_name: ['oe-venue', 3], venue_id: ['oe-venue', 3], venue_city: ['oe-city', 3], venue_address: ['oe-address', 3], event_website_url: ['oe-website', 3], website_url: ['oe-website', 3], facebook_url: ['oe-facebook', 3], video_url: ['oe-video', 3],
    description: ['oe-description', 4], ticket_terms: ['oe-terms', 4], ticket_types: ['oe-tt-add', 6],
    capacity: ['oe-capacity', 7], max_tickets_per_order: ['oe-max-order', 7], sales_start_at: ['oe-sales-start', 7], sales_end_at: ['oe-sales-end', 7],
  };
  function tr(m) {
    var x;
    if (/required/i.test(m)) return VQ.t('Required field.');
    if (/valid URL|format is invalid/i.test(m)) return VQ.t('The address is not valid.');
    if (/must be a date after/i.test(m)) return VQ.t('The date must be in the future.');
    if ((x = /greater than (\d+) characters/i.exec(m))) return VQ.t('The text is too long ({n} characters at most).', { n: x[1] });
    if ((x = /greater than (\d+)/i.exec(m))) return VQ.t('The maximum value is {n}.', { n: x[1] });
    if ((x = /at least (\d+)/i.exec(m))) return VQ.t('The minimum value is {n}.', { n: x[1] });
    if (/integer|number|numeric/i.test(m)) return VQ.t('Enter a number.');
    if (/valid date/i.test(m)) return VQ.t('Invalid date.');
    if (/selected .* is invalid/i.test(m)) return VQ.t('This choice is no longer valid.');
    return /[ăâîșț]/i.test(m) ? m : VQ.t('Invalid value.');
  }
  function serverErrors(err) {
    var errs = err && (err.errors || (err.data && err.data.errors));
    if (!errs || typeof errs !== 'object') return false;
    clearErrors();
    var problems = [], range = modeValue() === 'range';
    Object.keys(errs).forEach(function (key) {
      var msg = tr(String([].concat(errs[key])[0] || '')), m = /^ticket_types\.(\d+)\.(\w+)$/.exec(key);
      if (m) {
        var row = ED.sentRows[+m[1]], f = { name: 'name', price: 'price', quantity: 'quantity', description: 'description', min_per_order: 'min', max_per_order: 'max' }[m[2]];
        if (row && f) problems.push([row.querySelector('[data-f="' + f + '"]'), msg, 6]);
        return;
      }
      if (key === 'ends_at') { problems.push([$(range ? 'oe-end-date' : 'oe-end-time-single'), msg, 2]); return; }
      if (key === 'doors_open_at' && range) { problems.push([$('oe-door-time-range'), msg, 2]); return; }
      var map = SERVER_FIELDS[key.split('.')[0]];
      if (map) problems.push([$(map[0]), msg, map[1]]);
    });
    if (!problems.length) return false;
    report(problems);
    return true;
  }

  /* ---------- save ---------- */
  var LABELS = {
    submit: { short: VQ.t('Submit for approval'), long: VQ.t('Save and submit for approval'), tiny: VQ.t('Submit') },
    update: { short: VQ.t('Save changes'), long: VQ.t('Save changes'), tiny: VQ.t('Save') },
    live: { short: VQ.t('Save and publish'), long: VQ.t('Save and publish the changes'), tiny: VQ.t('Publish') },
    draft: { short: VQ.t('Save draft'), long: VQ.t('Save draft'), tiny: VQ.t('Save draft') },
  };
  function primaryKind() { return ED.published || ED.status === 'pending_review' ? 'update' : 'submit'; }
  function setActionLabels(busyKind) {
    var pk = primaryKind(), key = pk === 'update' && ED.published && ED.allowLive ? 'live' : pk;
    qsa('[data-oe-submit]', root).forEach(function (b) {
      b.querySelector('[data-label]').textContent = busyKind && busyKind !== 'draft' ? (busyKind === 'submit' ? VQ.t('Sending…') : VQ.t('Saving…')) : LABELS[key][b.getAttribute('data-size')];
    });
    qsa('[data-oe-save]', root).forEach(function (b) {
      b.hidden = pk !== 'submit';
      b.querySelector('[data-label]').textContent = busyKind === 'draft' ? VQ.t('Saving…') : LABELS.draft[b.getAttribute('data-size')];
    });
  }
  function setBusy(kind) {
    qsa('[data-oe-save],[data-oe-submit],#oe-delete', root).forEach(function (b) {
      b.disabled = !!kind;
      if (kind) b.setAttribute('aria-busy', 'true'); else b.removeAttribute('aria-busy');
    });
    setActionLabels(kind);
  }
  function stampSaved() { $('oe-saved').textContent = VQ.t('Saved at {time}', { time: new Date().toLocaleTimeString(LOC, { hour: '2-digit', minute: '2-digit' }) }); }
  function showAlert(text, href, linkText) {
    $('oe-alert-t').textContent = text;
    var a = $('oe-alert-a');
    a.hidden = !href;
    if (href) { a.setAttribute('href', href); a.textContent = linkText || VQ.t('Open'); }
    $('oe-alert').hidden = false;
    $('oe-alert').scrollIntoView({ block: 'center', behavior: reduced ? 'auto' : 'smooth' });
  }
  function hideAlert() { $('oe-alert').hidden = true; }
  function liveMessage(r) {
    var m = String((r && r.message) || '');
    if (/aprobare|approval/i.test(m)) return VQ.t('The changes were sent for approval.');
    if (/publicate|published/i.test(m)) return VQ.t('The changes were published.');
    return VQ.t('The changes were saved.');
  }
  function applySaved(ev, imagesOnly) {
    ED.event = ev;
    if (ev.image) ED.server.poster = ev.image;
    if (ev.cover_image) ED.server.cover = ev.cover_image;
    if (imagesOnly) { renderMedia('poster'); renderMedia('cover'); return; }
    ED.status = ev.status || ED.status;
    ED.published = !!(ev.is_public || ev.status === 'published');
    ED.allowLive = !!ev.allow_live_edits;
    ED.flags = flagsOf(ev);
    $('oe-changes').hidden = !ev.has_pending_changes;
    $('oe-review').hidden = ED.status !== 'pending_review';
    $('oe-rejected').hidden = ED.status !== 'rejected';
    $('oe-delete').hidden = !(AWAITING.indexOf(ED.status) > -1 && !ED.published);
    if (ED.published && ED.allowLive && Array.isArray(ev.ticket_types)) { // the new ticket types were created right away
      ED.locked = ev.ticket_types.filter(function (t) { return t && !t.free_with_code; });
      ED.free = ev.ticket_types.filter(function (t) { return t && t.free_with_code; });
      $('oe-tt-list').textContent = '';
    }
    renderLocked();
    renderFlags();
    ED.orig = datesOf(collect().data);
    setActionLabels();
    refreshAll();
  }
  function uploadImages(id) {
    var p = ED.files.poster, c = ED.files.cover;
    var lib = typeof BileteOnlineAPI !== 'undefined' && BileteOnlineAPI.organizer;
    if ((!p && !c) || !lib || !lib.uploadEventImages) return Promise.resolve(null);
    return lib.uploadEventImages(id, p, c).then(function (r) {
      ED.files.poster = null;
      ED.files.cover = null;
      revoke('poster');
      revoke('cover');
      return (r && r.data && r.data.event) || null;
    }).catch(function (err) {
      O.flash(err && err.status === 422 ? VQ.t('The activity was saved, but the images did not upload. Use a JPG, PNG or WebP image of 10 MB at most.') : VQ.t('The activity was saved, but the images did not upload. Try again.'), true);
      return null;
    });
  }
  function submitForReview() {
    return O.api('/organizer/events/' + encodeURIComponent(ED.id) + '/submit', { method: 'POST', body: {} }).then(function () {
      O.flash(VQ.t('The activity was submitted for approval.'));
      navigate(lastListUrl);
      return true;
    }).catch(function (err) {
      if (err && err.status === 401) return false;
      if (err && err.status === 403 && /contract/i.test(String(err.message || ''))) {
        showAlert(VQ.t('The activity was saved. To submit it for approval, sign the contract first.'), VQ.url('/organizator/setari#contract'), VQ.t('Sign the contract'));
      } else {
        showAlert(VQ.t('The activity was saved, but could not be submitted for approval. {reason}', { reason: errText(err, VQ.t('Try again.')) }), null);
      }
      return false;
    });
  }
  function save(kind) {
    if (ED.saving || ED.loadingId || view !== 'editor') return Promise.resolve(false);
    hideAlert();
    if (!navigator.onLine) {
      if (kind === 'draft') { pendingSave = true; O.flash(VQ.t('No internet connection. We save the draft automatically when the connection is back.'), true); }
      else O.flash(kind === 'submit' ? VQ.t('You cannot submit for approval without an internet connection. Wait for the connection to come back.') : VQ.t('You cannot save the changes without an internet connection. Wait for the connection to come back.'), true);
      return Promise.resolve(false);
    }
    if (!validate(kind === 'draft' ? 'draft' : 'full')) return Promise.resolve(false);
    var c = collect(), d = c.data, id = ED.id;
    if (kind === 'draft') d.is_draft = true;
    if (ED.published) { // resending an unchanged start would fail core's "must be in the future" once the activity runs
      ['starts_at', 'ends_at', 'doors_open_at'].forEach(function (k) { if ((d[k] || null) === ED.orig[k] && d.duration_mode === ED.orig.duration_mode) delete d[k]; });
    }
    ED.sentRows = c.rows;
    ED.saving = true;
    setBusy(kind);
    var req = id
      ? O.api('/organizer/events/' + encodeURIComponent(id), { method: 'PUT', body: d })
      : O.api('/organizer/events', { method: 'POST', body: d });
    return req.then(function (r) {
      var ev = r && r.data && (r.data.event || (r.data.id != null ? r.data : null));
      var newId = ev && ev.id != null ? String(ev.id) : id;
      if (!newId) throw { status: 500, message: '' };
      if (!id) {
        var url = LIST_URL + '/' + encodeURIComponent(newId);
        history.replaceState({ oe: 1 }, '', url);
        ED.url = url;
      }
      ED.id = newId;
      if (ev) applySaved(ev);
      return uploadImages(newId).then(function (imgEv) {
        if (imgEv) applySaved(imgEv, true);
        L.loaded = false;
        markClean();
        stampSaved();
        if (kind === 'submit') return submitForReview();
        O.flash(kind === 'draft' ? VQ.t('The activity was saved as a draft.') : liveMessage(r));
        return true;
      });
    }).catch(function (err) {
      if (err && err.status === 401) return false;
      if (err && err.status === 422 && serverErrors(err)) return false;
      if (err && err.status === 403 && /approved/i.test(String(err.message || ''))) {
        showAlert(VQ.t('Your account must be approved before you can create activities. Fill in the account details and upload the documents requested.'), VQ.url('/organizator/setari'), VQ.t('Go to account settings'));
        return false;
      }
      O.flash(errText(err, kind === 'submit' ? VQ.t('We could not submit the activity. Try again.') : VQ.t('We could not save the activity. Try again.')), true);
      return false;
    }).then(function (ok) {
      ED.saving = false;
      setBusy(null);
      return ok;
    });
  }
  qsa('[data-oe-save]', root).forEach(function (b) { b.addEventListener('click', function () { save('draft'); }); });
  qsa('[data-oe-submit]', root).forEach(function (b) { b.addEventListener('click', function () { save(primaryKind()); }); });
  $('oe-delete').addEventListener('click', function () {
    if (!ED.id) return;
    var btn = this;
    deleteActivity({ id: ED.id, name: val('oe-name').trim() || F.flat(ED.event && ED.event.name) }).then(function (done) {
      if (done) { L.loaded = false; markClean(); navigate(lastListUrl); } else focusQuiet(btn);
    });
  });

  /* ---------- actions of a live activity ---------- */
  function renderFlags() {
    var f = ED.flags || {}, chips = $('oe-status-chips');
    chips.textContent = '';
    [[f.sold, VQ.t('Sold out'), 'is-info'], [f.door, VQ.t('Door sales only'), 'is-wait'], [f.postponed, VQ.t('Postponed'), 'is-wait'], [f.cancelled, VQ.t('Cancelled'), 'is-bad']].forEach(function (x) {
      if (x[0]) chips.appendChild(el('span', { class: 'org-tag ' + x[2], text: x[1] }));
    });
    var so = $('oe-sold-out'), ds = $('oe-door-sales'), pp = $('oe-postpone'), cx = $('oe-cancel');
    so.classList.toggle('is-on', !!f.sold);
    so.querySelector('span').textContent = f.sold ? VQ.t('Undo sold out') : VQ.t('Mark as sold out');
    ds.classList.toggle('is-on', !!f.door);
    ds.querySelector('span').textContent = f.door ? VQ.t('Undo door sales only') : VQ.t('Door sales only');
    pp.classList.toggle('is-on', !!f.postponed);
    pp.querySelector('span').textContent = f.postponed ? VQ.t('Revoke the postponement') : VQ.t('Postpone the activity');
    cx.querySelector('span').textContent = f.cancelled ? VQ.t('Activity cancelled') : VQ.t('Cancel the activity');
    [so, ds, pp, cx].forEach(function (b) { b.disabled = !!f.cancelled; });
    setHeaderStatus();
  }
  function patchStatus(body, okMsg, btn) {
    if (!ED.id) return Promise.resolve(false);
    btn.disabled = true;
    return O.api('/organizer/events/' + encodeURIComponent(ED.id) + '/status', { method: 'PATCH', body: body }).then(function (r) {
      var ev = r && r.data && r.data.event;
      if (ev) { ED.flags = flagsOf(ev); ED.event = ev; ED.status = ev.status || ED.status; }
      else {
        if ('is_sold_out' in body) ED.flags.sold = body.is_sold_out;
        if ('door_sales_only' in body) ED.flags.door = body.door_sales_only;
        if ('is_postponed' in body) ED.flags.postponed = body.is_postponed;
      }
      L.loaded = false;
      O.flash(okMsg);
      return true;
    }).catch(function (err) {
      if (!err || err.status !== 401) O.flash(errText(err, VQ.t('We could not change the status of the activity.')), true);
      return false;
    }).then(function (ok) { btn.disabled = false; renderFlags(); return ok; });
  }
  $('oe-sold-out').addEventListener('click', function () {
    var on = !ED.flags.sold;
    patchStatus({ is_sold_out: on }, on ? VQ.t('The activity was marked as sold out.') : VQ.t('Sold out was undone.'), this);
  });
  $('oe-door-sales').addEventListener('click', function () {
    var on = !ED.flags.door;
    patchStatus({ door_sales_only: on }, on ? VQ.t('Door sales only was turned on.') : VQ.t('Door sales only was turned off.'), this);
  });
  $('oe-postpone').addEventListener('click', function () {
    if (ED.flags.postponed) { patchStatus({ is_postponed: false }, VQ.t('The postponement was revoked.'), this); return; }
    var ev = ED.event || {};
    setVal('oe-pp-date', ev.postponed_date || '');
    setVal('oe-pp-start', ev.postponed_start_time || '');
    setVal('oe-pp-door', ev.postponed_door_time || '');
    setVal('oe-pp-end', ev.postponed_end_time || '');
    setVal('oe-pp-reason', ev.postponed_reason || '');
    clearErr($('oe-pp-date'));
    showDialog($('oe-postpone-d'));
    focusQuiet($('oe-pp-date'));
  });
  $('oe-pp-form').addEventListener('submit', function (e) {
    e.preventDefault();
    clearErr($('oe-pp-date'));
    var date = val('oe-pp-date');
    if (!date) { showErr($('oe-pp-date'), VQ.t('Choose the new date.')); focusQuiet($('oe-pp-date')); return; }
    var ok = $('oe-pp-ok');
    ok.disabled = true;
    patchStatus({
      is_postponed: true, postponed_date: date, postponed_start_time: val('oe-pp-start') || null, postponed_door_time: val('oe-pp-door') || null,
      postponed_end_time: val('oe-pp-end') || null, postponed_reason: val('oe-pp-reason').trim() || null,
    }, VQ.t('The activity was marked as postponed.'), $('oe-postpone')).then(function (done) {
      ok.disabled = false;
      if (done) closeDialog($('oe-postpone-d'));
    });
  });
  $('oe-cancel').addEventListener('click', function () {
    setVal('oe-cx-reason', '');
    $('oe-cx-ack').checked = false;
    clearErr($('oe-cx-reason'));
    clearErr($('oe-cx-ack'));
    showDialog($('oe-cancel-d'));
    focusQuiet($('oe-cx-reason'));
  });
  function cancelMessage(r) {
    var d = (r && r.data) || {}, parts = [VQ.t('The activity was cancelled.')];
    var unpaid = F.toNum(d.orders_cancelled), paid = F.toNum(d.paid_orders_pending_review);
    if (unpaid > 0) parts.push(VQ.n(unpaid, 'unpaid order was cancelled.', 'unpaid orders were cancelled.'));
    if (paid > 0) parts.push(VQ.n(paid, 'paid order is waiting for the refund decision of the Viaqui team.', 'paid orders are waiting for the refund decision of the Viaqui team.'));
    return parts.join(' ');
  }
  $('oe-cx-form').addEventListener('submit', function (e) {
    e.preventDefault();
    clearErr($('oe-cx-reason'));
    clearErr($('oe-cx-ack'));
    var reason = val('oe-cx-reason').trim(), first = null;
    if (!reason) { showErr($('oe-cx-reason'), VQ.t('Enter the reason for cancelling.')); first = $('oe-cx-reason'); }
    if (!$('oe-cx-ack').checked) { showErr($('oe-cx-ack'), VQ.t('Tick the box to confirm you understand that the cancellation is final.')); first = first || $('oe-cx-ack'); }
    if (first) { focusQuiet(first); return; }
    var ok = $('oe-cx-ok');
    ok.disabled = true;
    ok.textContent = VQ.t('Cancelling…');
    O.api('/organizer/events/' + encodeURIComponent(ED.id) + '/cancel', { method: 'POST', body: { reason: reason } }).then(function (r) {
      closeDialog($('oe-cancel-d'));
      L.loaded = false;
      markClean();
      O.flash(cancelMessage(r));
      navigate(lastListUrl);
    }).catch(function (err) {
      if (!err || err.status !== 401) O.flash(errText(err, VQ.t('We could not cancel the activity.')), true);
    }).then(function () { ok.disabled = false; ok.textContent = VQ.t('Confirm cancellation'); });
  });

  /* ---------- preview ---------- */
  function renderPreview() {
    var body = $('oe-pv-body');
    body.textContent = '';
    var src = ED.files.cover ? objectUrls.cover : ED.server.cover ? O.img(ED.server.cover) : ED.files.poster ? objectUrls.poster : ED.server.poster ? O.img(ED.server.poster) : '';
    body.appendChild(el('div', { class: 'oe-pv-hero' }, src ? el('img', { src: src, alt: '' }) : VQ.t('No image')));
    var sel = $('oe-category'), catText = sel.value && sel.selectedIndex > 0 ? sel.options[sel.selectedIndex].text : '';
    if (catText) body.appendChild(el('span', { class: 'org-tag is-muted oe-pv-cat', text: catText }));
    body.appendChild(el('h3', { class: 'oe-pv-h', text: val('oe-name').trim() || VQ.t('Untitled activity') }));
    var sd = val('oe-start-date'), st = val('oe-start-time');
    if (sd) {
      var when = cap(dayLabel(sd, { weekday: 'short', day: 'numeric', month: 'long', year: 'numeric' })) + (st ? ', ' + st : '');
      if (modeValue() === 'range' && val('oe-end-date')) when += ' – ' + dayLabel(val('oe-end-date'), { day: 'numeric', month: 'long', year: 'numeric' });
      body.appendChild(el('p', { class: 'oe-pv-line' }, [icon('calendar-blank'), when]));
    }
    var place = [val('oe-venue').trim(), val('oe-city').trim()].filter(Boolean).join(', ');
    if (place) body.appendChild(el('p', { class: 'oe-pv-line' }, [icon('map-pin'), place]));
    var short = val('oe-short').trim();
    if (short) body.appendChild(el('p', { class: 'oe-pv-desc', text: short }));
    body.appendChild(el('p', { class: 'org-k oe-pv-k', text: VQ.t('Tickets available') }));
    var tickets = ED.locked.map(function (t) { return [F.flat(t.name), F.money(t.price)]; }).concat(qsa('#oe-tt-list [data-tt]').map(function (row) {
      var n = row.querySelector('[data-f="name"]').value.trim(), p = row.querySelector('[data-f="price"]').value.trim();
      return n ? [n, p !== '' ? F.money(p) : '—'] : null;
    }).filter(Boolean));
    body.appendChild(tickets.length
      ? el('ul', { class: 'oe-pv-tickets' }, tickets.map(function (t) { return el('li', null, [el('span', { text: t[0] }), el('b', { text: t[1] })]); }))
      : el('p', { class: 'oe-help', text: VQ.t('No ticket types defined yet.') }));
  }
  $('oe-preview-open').addEventListener('click', function () { renderPreview(); showDialog($('oe-preview-d')); });

  /* ---------- summaries, steps, outline, header ---------- */
  function setSum(n, t) { var s = $('oe-sum-' + n); if (s) s.textContent = t; }
  function updateShortCount() {
    var v = val('oe-short'), w = words(v), chars = v.trim().length, n = $('oe-short-count');
    n.textContent = VQ.t('{words}/120 words · {chars}/500 characters', { words: w, chars: chars });
    n.classList.toggle('is-over', w > 120 || chars > 500);
  }
  function setHeaderStatus() {
    var t = $('oe-bar-status');
    var s = ED.id ? (ED.flags.cancelled ? 'cancelled' : ED.flags.postponed ? 'postponed' : ED.status) : null, m = s && HEAD_STATUS[s];
    t.hidden = !m;
    if (m) { t.textContent = m[0]; t.className = 'org-tag ' + m[1]; }
  }
  function setHeader() {
    var name = val('oe-name').trim();
    if (!ED.loadingId) $('oe-ed-h').textContent = ED.id ? (name || VQ.t('Untitled activity')) : VQ.t('New activity');
    var sd = val('oe-start-date'), st = val('oe-start-time'), dEl = $('oe-bar-date');
    dEl.hidden = !sd;
    if (sd) dEl.lastElementChild.textContent = dayLabel(sd, { day: 'numeric', month: 'short', year: 'numeric' }) + (st && st !== '00:00' ? ', ' + st : '');
    var place = [val('oe-venue').trim(), val('oe-city').trim()].filter(Boolean).join(', '), vEl = $('oe-bar-venue');
    vEl.hidden = !place;
    if (place) vEl.lastElementChild.textContent = place;
    setHeaderStatus();
    if (view === 'editor') document.title = (ED.id ? VQ.t('{name} · editing', { name: name || VQ.t('Activity') }) : VQ.t('New activity')) + SITE;
  }
  function refreshAll() {
    if (view !== 'editor') return;
    var mode = modeValue(), sd = val('oe-start-date'), st = val('oe-start-time');
    var sel = $('oe-category'), catText = sel.value && sel.selectedIndex > 0 ? sel.options[sel.selectedIndex].text : '';
    setSum(1, [val('oe-name').trim(), catText].filter(Boolean).join(' • '));
    var s2 = '';
    if (sd) {
      s2 = dayLabel(sd, { day: 'numeric', month: 'short', year: 'numeric' }) + (st ? ', ' + st : '');
      if (mode === 'range' && val('oe-end-date')) s2 += ' – ' + dayLabel(val('oe-end-date'), { day: 'numeric', month: 'short' });
    }
    setSum(2, s2);
    setSum(3, [val('oe-venue').trim(), val('oe-city').trim()].filter(Boolean).join(', '));
    var desc = plain(getRich('desc'));
    setSum(4, desc.length > 60 ? desc.slice(0, 60) + '…' : desc);
    var hasPoster = !!(ED.files.poster || ED.server.poster), hasCover = !!(ED.files.cover || ED.server.cover);
    setSum(5, hasPoster && hasCover ? VQ.t('Poster and cover added') : hasPoster ? VQ.t('Poster added') : hasCover ? VQ.t('Cover added') : '');
    var tickets = ED.locked.map(function (t) { return F.flat(t.name) + ': ' + F.money(t.price); }).concat(qsa('#oe-tt-list [data-tt]').map(function (row) {
      var n = row.querySelector('[data-f="name"]').value.trim(), p = row.querySelector('[data-f="price"]').value.trim();
      return n && p !== '' ? n + ': ' + F.money(p) : null;
    }).filter(Boolean));
    setSum(6, tickets.join(' | '));
    var s7 = [];
    if (val('oe-capacity')) s7.push(VQ.t('Capacity: {n}', { n: val('oe-capacity') }));
    if (val('oe-max-order')) s7.push(VQ.t('Max per order: {n}', { n: val('oe-max-order') }));
    setSum(7, s7.join(' • '));

    var firstRow = root.querySelector('#oe-tt-list [data-tt]');
    var done = {
      1: !!val('oe-name').trim(),
      2: !!sd && !!st,
      3: !!val('oe-venue').trim() && !!val('oe-city').trim(),
      4: !!desc,
      5: hasPoster && hasCover,
      6: ED.locked.length > 0 || (!!firstRow && !!firstRow.querySelector('[data-f="name"]').value.trim() && firstRow.querySelector('[data-f="price"]').value.trim() !== ''),
      7: !!val('oe-capacity') || !!val('oe-max-order'),
    };
    var issues = 0;
    for (var n = 1; n <= 7; n++) {
      var required = n <= 3 && !done[n];
      var step = root.querySelector('#oe-s' + n + ' .oe-step');
      if (step) {
        step.classList.toggle('is-done', done[n]);
        step.textContent = '';
        if (done[n]) step.appendChild(icon('check')); else step.textContent = String(n);
      }
      var st2 = root.querySelector('.oe-ol[data-ol="' + n + '"] [data-st]');
      if (st2) {
        st2.className = 'oe-ol-st ' + (done[n] ? 'is-done' : required ? 'is-req' : 'is-part');
        st2.querySelector('.sr').textContent = ' ' + (done[n] ? VQ.t('(complete)') : required ? VQ.t('(required fields missing)') : VQ.t('(not filled in)'));
      }
      if (required) issues++;
    }
    $('oe-issues').hidden = !issues;
    $('oe-issues-t').textContent = VQ.n(issues, 'issue left', 'issues left');
    setHeader();
    updateShortCount();
  }
  var refreshSoon = debounce(refreshAll, 150);

  /* ---------- scroll spy + sections ---------- */
  var spy = null;
  function initSpy() {
    if (spy || !('IntersectionObserver' in window)) return;
    spy = new IntersectionObserver(function (entries) {
      var visible = entries.filter(function (x) { return x.isIntersecting; }).sort(function (a, b) { return a.boundingClientRect.top - b.boundingClientRect.top; });
      if (!visible.length) return;
      var step = visible[0].target.getAttribute('data-step');
      qsa('.oe-ol', root).forEach(function (a) {
        var on = a.getAttribute('data-ol') === step;
        a.classList.toggle('is-active', on);
        if (on) a.setAttribute('aria-current', 'step'); else a.removeAttribute('aria-current');
      });
      qsa('.oe-sec', root).forEach(function (s) { s.classList.toggle('is-active', s.getAttribute('data-step') === step); });
    }, { rootMargin: '-190px 0px -55% 0px', threshold: 0 });
    qsa('.oe-sec', root).forEach(function (s) { spy.observe(s); });
  }
  qsa('.oe-ol', root).forEach(function (a) {
    a.addEventListener('click', function (e) {
      e.preventDefault();
      var sec = $('oe-s' + a.getAttribute('data-ol'));
      if (!sec) return;
      setOpen(sec, true);
      sec.scrollIntoView({ behavior: reduced ? 'auto' : 'smooth', block: 'start' });
      var h = sec.querySelector('.oe-sec-h');
      h.setAttribute('tabindex', '-1');
      focusQuiet(h);
    });
  });
  qsa('.oe-sec', root).forEach(function (sec) {
    sec.querySelector('.oe-sec-head').addEventListener('click', function (e) {
      if (desktop.matches || (e.target.closest && e.target.closest('a'))) return;
      setOpen(sec, sec.getAttribute('data-open') !== 'true');
    });
  });
  window.addEventListener('scroll', function () { $('oe-bar').classList.toggle('is-stuck', window.scrollY > 8); }, { passive: true });

  /* ---------- form events ---------- */
  var form = $('oe-form');
  form.addEventListener('submit', function (e) { e.preventDefault(); });
  form.addEventListener('input', function (e) {
    var t = e.target;
    if (e.isTrusted && !(t.matches && t.matches('#oe-genres-q, #oe-artists-q'))) markDirty();
    if (t.getAttribute && t.getAttribute('aria-invalid')) clearErr(t);
    refreshSoon();
  });
  form.addEventListener('change', function (e) {
    var t = e.target;
    if (t.name === 'oe-duration') setMode(modeValue());
    if (e.isTrusted && t.type !== 'file' && !(t.matches && t.matches('#oe-genres-q, #oe-artists-q'))) markDirty();
    refreshSoon();
  });
  qsa('.oe-f', root).forEach(function (box) { // label ↔ help / error for the fields rendered in PHP
    var c = box.querySelector('input:not([type="radio"]):not([type="file"]), select, textarea');
    if (!c || !c.id || c.hasAttribute('aria-describedby')) return;
    var ids = [c.id + '-help', c.id + '-err'].filter(function (x) { return $(x); });
    if (ids.length) c.setAttribute('aria-describedby', ids.join(' '));
  });
  window.addEventListener('beforeunload', function (e) {
    if (view === 'editor' && ED.dirty) { e.preventDefault(); e.returnValue = ''; }
  });
  document.addEventListener('keydown', function (e) {
    if ((e.ctrlKey || e.metaKey) && (e.key === 's' || e.key === 'S') && view === 'editor') {
      e.preventDefault();
      if (primaryKind() === 'submit') save('draft'); else save('update');
    }
  });

  /* ---------- offline ---------- */
  var pendingSave = false, offlineTimer = 0;
  function offlineBanner(text, back) {
    var b = $('oe-offline');
    clearTimeout(offlineTimer);
    b.textContent = text;
    b.classList.toggle('is-back', !!back);
    b.hidden = false;
    if (back) offlineTimer = setTimeout(function () { b.hidden = true; }, 4000);
  }
  window.addEventListener('offline', function () {
    var drafting = view === 'editor' && primaryKind() === 'submit';
    offlineBanner(view === 'editor'
      ? (drafting ? VQ.t('The internet connection was lost. You can keep editing; the draft is saved automatically when the connection is back.') : VQ.t('The internet connection was lost. You can keep editing; save the changes once the connection is back.'))
      : VQ.t('The internet connection was lost.'));
    if (drafting && ED.dirty) pendingSave = true;
  });
  window.addEventListener('online', function () {
    var auto = pendingSave && view === 'editor' && !!val('oe-name').trim() && primaryKind() === 'submit';
    offlineBanner(auto ? VQ.t('The connection is back. Saving the draft…') : VQ.t('The connection is back.'), true);
    if (!auto) { pendingSave = false; return; }
    setTimeout(function () {
      save('draft').then(function (ok) {
        if (ok) pendingSave = false;
        else O.flash(VQ.t('Automatic saving did not work. Save manually.'), true);
      });
    }, 1000);
  });

  /* =================== START =================== */
  O.ready.then(function (ok) { if (ok) route(); });
})();
