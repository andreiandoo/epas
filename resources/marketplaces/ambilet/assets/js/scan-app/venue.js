/* =============================================================================
 * Scan App (venue) — "De predat către {organizator}" bar
 * -----------------------------------------------------------------------------
 * Loaded only in the venue app (/venue/scan/*). Fills #scanapp-handover (under
 * the event strip on Scanare / Vânzare) with what the venue collected at the
 * door for the selected event, all phones included, and owes the organizer.
 * Same data and wording as the Android app. Refreshes on event change, after
 * every sale made here and every 30 seconds.
 * ============================================================================= */
(function () {
  'use strict';

  var host = document.getElementById('scanapp-handover');
  if (!host) return;

  var eventId = null;
  var organizer = '';
  var timer = null;

  function esc(s) {
    if (s == null) return '';
    return String(s).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;').replace(/'/g, '&#39;');
  }
  function money(n) {
    return (Number(n) || 0).toLocaleString('ro-RO', { minimumFractionDigits: 2, maximumFractionDigits: 2 }) + ' lei';
  }

  function render(h) {
    if (!h) { host.hidden = true; return; }
    var total = Number(h.total || 0);
    var test = Number(h.test_tickets || 0);
    var who = h.organizer_name || organizer;
    var html = '<div class="scanapp-handover__row"><span class="scanapp-handover__label">De predat' +
      (who ? ' către ' + esc(who) : '') + '</span><span class="scanapp-handover__total">' + money(total) + '</span></div>';
    if (total > 0) {
      html += '<div class="scanapp-handover__split">Numerar ' + money(h.cash) + ' · Card POS ' + money(h.card) +
        ' · ' + (h.tickets || 0) + ' bilete</div>';
    }
    if (test > 0) {
      html += '<div class="scanapp-handover__test">+ ' + test + (test === 1 ? ' bilet' : ' bilete') +
        ' Test POS (' + money(h.test_total) + '), nu se predă</div>';
    }
    host.innerHTML = html;
    host.hidden = false;
  }

  function load() {
    if (!eventId || typeof ScanAPI === 'undefined') return;
    ScanAPI.get('/venue-owner/events/' + eventId + '/settlement')
      .then(function (resp) { render(resp && resp.data); })
      .catch(function () { /* keep the last value — informative only */ });
  }

  function setEvent(ev) {
    eventId = ev ? ev.id : null;
    organizer = (ev && ev.marketplace_organizer && ev.marketplace_organizer.name) || '';
    if (!eventId) { render(null); return; }
    load();
    if (timer) clearInterval(timer);
    timer = setInterval(function () { if (!document.hidden) load(); }, 30000);
  }

  function init() {
    if (typeof EventContext === 'undefined') return;
    setEvent(EventContext.getState().selectedEvent);
    EventContext.subscribe('event-selected', function (e) { setEvent(e.detail.event); });
    EventContext.subscribe('events-loaded', function () {
      var ev = EventContext.getState().selectedEvent;
      if (ev && String(ev.id) !== String(eventId)) setEvent(ev);
    });
    if (typeof AppContext !== 'undefined' && AppContext.subscribe) {
      AppContext.subscribe('sale-added', function () { setTimeout(load, 800); });
    }
    document.addEventListener('visibilitychange', function () { if (!document.hidden) load(); });
  }

  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', init);
  else init();
})();
