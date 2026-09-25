/* =============================================================================
 * Scan App (venue) — Evenimente
 * -----------------------------------------------------------------------------
 * Port of the Android venue-owner Evenimente tab: upcoming / past events at
 * the venue's locations (any organizer), each with sold / checked-in counts
 * and what the venue collected at the door and owes the organizer. Tapping
 * an event opens its details with Scanare / Vânzare / Participanți; those
 * select the event (EventContext) and open the matching tab.
 * ============================================================================= */
(function () {
  'use strict';

  var scope = 'upcoming';
  var events = [];

  function $(id) { return document.getElementById(id); }
  function esc(s) {
    if (s == null) return '';
    return String(s).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;').replace(/'/g, '&#39;');
  }
  function money(n) {
    return (Number(n) || 0).toLocaleString('ro-RO', { minimumFractionDigits: 2, maximumFractionDigits: 2 }) + ' lei';
  }
  function dateLabel(ev) {
    var src = ev.start_date || ev.starts_at;
    if (!src) return '';
    var d = new Date(src);
    if (isNaN(d.getTime())) return '';
    var out = d.toLocaleDateString('ro-RO', { day: '2-digit', month: 'short', year: 'numeric' });
    if (ev.start_time) out += ' · ' + String(ev.start_time).slice(0, 5);
    return out;
  }
  function organizerName(ev) {
    return (ev.marketplace_organizer && ev.marketplace_organizer.name) || '';
  }

  // Same wording as the Android HandoverLine.
  function handoverHtml(h, organizer, compact) {
    if (!h) return '';
    var total = Number(h.total || 0);
    var test = Number(h.test_tickets || 0);
    if (compact && total <= 0 && test <= 0) return '';
    var html = '<div class="scanapp-handover__row"><span class="scanapp-handover__label">De predat' +
      (organizer ? ' către ' + esc(organizer) : '') + '</span><span class="scanapp-handover__total">' + money(total) + '</span></div>';
    if (total > 0) {
      html += '<div class="scanapp-handover__split">Numerar ' + money(h.cash) + ' · Card POS ' + money(h.card) +
        ' · ' + (h.tickets || 0) + ' bilete</div>';
    }
    if (test > 0) {
      html += '<div class="scanapp-handover__test">+ ' + test + (test === 1 ? ' bilet' : ' bilete') +
        ' Test POS (' + money(h.test_total) + '), nu se predă</div>';
    }
    return '<div class="scanapp-handover scanapp-handover--inline">' + html + '</div>';
  }

  function render() {
    var host = $('scanapp-venue-events');
    if (!host) return;
    if (!events.length) {
      host.innerHTML = '<div class="scanapp-card scanapp-card--placeholder"><p class="scanapp-card__text">' +
        (scope === 'upcoming' ? 'Nu ai evenimente viitoare în locațiile tale.' : 'Nu ai evenimente trecute.') + '</p></div>';
      return;
    }
    host.innerHTML = events.map(function (ev) {
      var st = ev.stats || {};
      var sold = st.tickets_sold || 0;
      var cap = st.stock_total || 0;
      var badge = ev.is_cancelled ? '<span class="scanapp-venue-event__badge scanapp-venue-event__badge--red">Anulat</span>'
        : (ev.is_postponed ? '<span class="scanapp-venue-event__badge">Reprogramat</span>' : '');
      return '<button type="button" class="scanapp-venue-event" data-id="' + esc(ev.id) + '">' +
        '<div class="scanapp-venue-event__title">' + esc(ev.title || ev.name || 'Eveniment') + badge + '</div>' +
        '<div class="scanapp-venue-event__meta">' + esc(dateLabel(ev)) + (ev.venue_name ? ' · ' + esc(ev.venue_name) : '') + '</div>' +
        (organizerName(ev) ? '<div class="scanapp-venue-event__org">Organizator: ' + esc(organizerName(ev)) + '</div>' : '') +
        '<div class="scanapp-venue-event__stats"><span><b>' + (cap > 0 ? sold + ' / ' + cap : sold) + '</b> vândute</span>' +
        '<span><b>' + (st.checked_in_count || 0) + '</b> check-in</span></div>' +
        handoverHtml(ev.handover, organizerName(ev), true) +
        '</button>';
    }).join('');
    host.querySelectorAll('[data-id]').forEach(function (el) {
      el.addEventListener('click', function () { openDetail(el.getAttribute('data-id')); });
    });
  }

  function load() {
    var host = $('scanapp-venue-events');
    if (host && !events.length) {
      host.innerHTML = '<div class="scanapp-card scanapp-card--placeholder"><p class="scanapp-card__text">Se încarcă evenimentele…</p></div>';
    }
    return ScanAPI.get('/organizer/events', { scope: scope }).then(function (resp) {
      var list = (resp && resp.data && (resp.data.events || resp.data)) || [];
      events = Array.isArray(list) ? list : [];
      render();
    }).catch(function (err) {
      console.error('[evenimente] load failed:', err);
      if (host) host.innerHTML = '<div class="scanapp-card scanapp-card--placeholder"><p class="scanapp-card__text">Nu am putut încărca evenimentele. Trage în jos sau apasă reîncarcă.</p></div>';
    });
  }

  function openDetail(id) {
    var ev = events.find(function (e) { return String(e.id) === String(id); });
    if (!ev) return;
    var st = ev.stats || {};
    var past = scope === 'past';
    var box = $('scanapp-venue-event-detail');
    box.innerHTML =
      '<h2 class="scanapp-sheet__title">' + esc(ev.title || ev.name || 'Eveniment') + '</h2>' +
      '<div class="scanapp-venue-event__meta">' + esc(dateLabel(ev)) + (ev.venue_name ? ' · ' + esc(ev.venue_name) : '') + '</div>' +
      (organizerName(ev) ? '<div class="scanapp-venue-event__org">Organizator: ' + esc(organizerName(ev)) + '</div>' : '') +
      '<div class="scanapp-venue-detail__stats">' +
        '<div><b>' + (st.tickets_sold || 0) + '</b><span>vândute</span></div>' +
        '<div><b>' + (st.checked_in_count || 0) + '</b><span>check-in</span></div>' +
      '</div>' +
      handoverHtml(ev.handover, organizerName(ev), false) +
      '<div class="scanapp-venue-detail__actions">' +
        (past ? '' :
          '<button type="button" class="scanapp-btn scanapp-btn--primary scanapp-btn--block" data-go="scanare">Scanare</button>' +
          '<button type="button" class="scanapp-btn scanapp-btn--primary scanapp-btn--block" data-go="vanzare">Vânzare</button>') +
        '<button type="button" class="scanapp-btn scanapp-btn--block" data-go="guest-list">Participanți</button>' +
        '<button type="button" class="scanapp-btn scanapp-btn--block" data-close>Închide</button>' +
      '</div>';
    box.querySelectorAll('[data-go]').forEach(function (b) {
      b.addEventListener('click', function () { go(ev, b.getAttribute('data-go')); });
    });
    box.querySelector('[data-close]').addEventListener('click', closeDetail);
    $('scanapp-venue-event-sheet').classList.add('scanapp-sheet-backdrop--open');
  }

  function closeDetail() {
    $('scanapp-venue-event-sheet').classList.remove('scanapp-sheet-backdrop--open');
  }

  function go(ev, page) {
    // selectEvent() persists the choice; the next page hydrates it first.
    try { EventContext.selectEvent(ev); } catch (e) { /* navigation still works */ }
    location.href = '/venue/scan/' + page;
  }

  function init() {
    var nameEl = $('scanapp-venue-name');
    if (nameEl && typeof ScanAuth !== 'undefined') {
      var o = ScanAuth.getOrganizer();
      nameEl.textContent = (o && (o.public_name || o.name)) || '—';
    }
    document.querySelectorAll('[data-scope]').forEach(function (btn) {
      btn.addEventListener('click', function () {
        scope = btn.getAttribute('data-scope');
        document.querySelectorAll('[data-scope]').forEach(function (b) {
          b.classList.toggle('scanapp-venue-seg__btn--active', b === btn);
        });
        events = [];
        load();
      });
    });
    var sheet = $('scanapp-venue-event-sheet');
    if (sheet) sheet.addEventListener('click', function (e) { if (e.target === sheet) closeDetail(); });
    var refresh = $('scanapp-refresh');
    if (refresh) refresh.addEventListener('click', load);
    load();
    // Amounts change as the venue sells on other phones.
    setInterval(function () { if (!document.hidden) load(); }, 60000);
  }

  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', init);
  else init();
})();
