/* bilete.online v2: customer account shell (/cont/*). Fills in the user card from the session, keeps the section
   badges (pages call BO_ACCOUNT.setBadges), wires both logout buttons (hidden without a session) and brings the current
   section into view in the phone section bar. Exposes window.BO_ACCOUNT for the page scripts, with the tools several
   account pages share: calendar files (.ics), saving a Blob as a download, and QR codes (the page must also load
   js/vendor/qrcode.js). */
(function () {
  'use strict';
  var root = document.querySelector('.acc');
  if (!root) return;

  function auth() { return typeof BileteOnlineAuth !== 'undefined' ? BileteOnlineAuth : null; }
  function isCustomer() {
    var a = auth();
    try {
      if (!a || !a.isLoggedIn()) return false;
      var type = a.getUserType();
      return !type || type === 'customer';
    } catch (e) { return false; }
  }
  function cachedUser() {
    var a = auth();
    try { return (a && a.getCustomerData && a.getCustomerData()) || null; } catch (e) { return null; }
  }

  function setUser(u) {
    if (!u) return;
    var full = ((u.first_name || '') + ' ' + (u.last_name || '')).trim() || u.name || '';
    var label = full || u.email || 'Client';
    var initials = label.split(/\s+/).filter(Boolean).map(function (s) { return s.charAt(0); }).join('').slice(0, 2).toUpperCase() || '?';
    [].forEach.call(root.querySelectorAll('[data-acc-initials]'), function (el) { el.textContent = initials; });
    [].forEach.call(root.querySelectorAll('[data-acc-name]'), function (el) { el.textContent = label; });
    [].forEach.call(root.querySelectorAll('[data-acc-email]'), function (el) { el.textContent = u.email || '—'; });
  }

  var fmt = new Intl.NumberFormat('ro-RO');
  function setBadges(badges) {
    Object.keys(badges || {}).forEach(function (key) {
      var n = Number(badges[key]);
      [].forEach.call(root.querySelectorAll('[data-acc-badge="' + key + '"]'), function (el) {
        var show = !isNaN(n) && n > 0;
        el.textContent = show ? fmt.format(n) : '';
        el.hidden = !show;
      });
    });
  }

  if (isCustomer()) setUser(cachedUser());
  window.addEventListener('bileteonline:auth:login', function (e) { if (e.detail && e.detail.type === 'customer') setUser(e.detail.user); });
  window.addEventListener('bileteonline:customer:loaded', function (e) { setUser(e.detail); });

  [].forEach.call(root.querySelectorAll('[data-acc-logout]'), function (btn) {
    btn.hidden = !isCustomer(); // nothing to sign out of on the login prompt
    btn.addEventListener('click', function () {
      [].forEach.call(root.querySelectorAll('[data-acc-logout]'), function (b) { b.disabled = true; });
      var label = btn.querySelector('span');
      if (label) label.textContent = 'Se deconectează…';
      var a = auth();
      if (a && typeof a.logoutCustomer === 'function') a.logoutCustomer(); // clears the session and goes to /
      else window.location.href = '/';
    });
  });

  // phone section bar: start with the current section visible
  var current = root.querySelector('.acc-mnav [aria-current="page"]');
  var track = root.querySelector('.acc-mnav-track');
  if (current && track && track.scrollWidth > track.clientWidth) {
    track.scrollLeft = Math.max(0, current.offsetLeft - (track.clientWidth - current.offsetWidth) / 2);
  }

  // ---------- downloads ----------
  function save(blob, filename) {
    var url = URL.createObjectURL(blob), a = document.createElement('a');
    a.href = url;
    a.download = filename;
    a.hidden = true;
    document.body.appendChild(a);
    a.click();
    setTimeout(function () { URL.revokeObjectURL(url); a.remove(); }, 1500);
  }

  // ---------- calendar (.ics) ----------
  var encoder = window.TextEncoder ? new TextEncoder() : null;
  function icsText(s) { return String(s || '').replace(/\\/g, '\\\\').replace(/;/g, '\\;').replace(/,/g, '\\,').replace(/\r?\n/g, '\\n'); }
  function icsDate(d) { return d.toISOString().replace(/[-:]/g, '').replace(/\.\d{3}/, ''); }
  function icsFold(line) { // RFC 5545: at most 75 octets per line, continuation lines start with a space
    var done = '', cur = '', size = 0;
    Array.from(line).forEach(function (ch) {
      var b = encoder ? encoder.encode(ch).length : 4;
      if (size + b > 75) { done += cur + '\r\n '; cur = ''; size = 1; }
      cur += ch; size += b;
    });
    return done + cur;
  }
  function fileSlug(s) { return String(s || '').toLowerCase().normalize('NFD').replace(/[\u0300-\u036f]/g, '').replace(/[^a-z0-9]+/g, '-').slice(0, 60).replace(/^-+|-+$/g, ''); }
  /** Downloads one .ics with a VEVENT per item: {title, date: Date, end?: Date, venue, city, uid, note?}. */
  function calendar(events, name) {
    events = (events || []).filter(function (e) { return e && e.date instanceof Date && !isNaN(e.date.getTime()); });
    if (!events.length) return false;
    var page = window.location.origin + '/cont/bilete', stamp = icsDate(new Date());
    var lines = ['BEGIN:VCALENDAR', 'VERSION:2.0', 'PRODID:-//bilete.online//Contul meu//RO', 'CALSCALE:GREGORIAN', 'METHOD:PUBLISH'];
    events.forEach(function (e) {
      var where = [e.venue, e.city].filter(Boolean).join(', ');
      var hasEnd = e.end instanceof Date && !isNaN(e.end.getTime()) && e.end > e.date;
      lines.push('BEGIN:VEVENT', 'UID:' + icsText(e.uid || 'bilet-' + e.date.getTime()) + '@bilete.online', 'DTSTAMP:' + stamp,
        'DTSTART:' + icsDate(e.date), hasEnd ? 'DTEND:' + icsDate(e.end) : 'DURATION:PT2H', 'SUMMARY:' + icsText(e.title));
      if (where) lines.push('LOCATION:' + icsText(where));
      lines.push('DESCRIPTION:' + icsText(e.note || 'Biletele tale sunt în contul bilete.online: ' + page), 'URL:' + page,
        'BEGIN:VALARM', 'ACTION:DISPLAY', 'DESCRIPTION:' + icsText(e.title), 'TRIGGER:-PT2H', 'END:VALARM', 'END:VEVENT');
    });
    lines.push('END:VCALENDAR');
    save(new Blob([lines.map(icsFold).join('\r\n') + '\r\n'], { type: 'text/calendar;charset=utf-8' }), (fileSlug(name || events[0].title) || 'bilet') + '.ics');
    return true;
  }

  // ---------- QR codes ----------
  var SVG = 'http://www.w3.org/2000/svg';
  /** An <svg> QR code for `text` (dark modules on white, 2-module quiet zone), or null without the library. */
  function qr(text, label) {
    var lib = window.qrcode;
    if (typeof lib !== 'function' || !text) return null;
    var code;
    try {
      if (lib.stringToBytesFuncs && lib.stringToBytesFuncs['UTF-8']) lib.stringToBytes = lib.stringToBytesFuncs['UTF-8'];
      code = lib(0, 'M');
      code.addData(String(text));
      code.make();
    } catch (e) { return null; }
    var n = code.getModuleCount(), quiet = 2, size = n + quiet * 2, d = '';
    for (var r = 0; r < n; r++) {
      for (var c = 0; c < n; c++) if (code.isDark(r, c)) d += 'M' + (c + quiet) + ' ' + (r + quiet) + 'h1v1h-1z';
    }
    var svg = document.createElementNS(SVG, 'svg'), bg = document.createElementNS(SVG, 'rect'), path = document.createElementNS(SVG, 'path');
    svg.setAttribute('viewBox', '0 0 ' + size + ' ' + size);
    svg.setAttribute('shape-rendering', 'crispEdges');
    svg.setAttribute('class', 'acc-qr');
    if (label) { svg.setAttribute('role', 'img'); svg.setAttribute('aria-label', label); } else svg.setAttribute('aria-hidden', 'true');
    bg.setAttribute('width', size); bg.setAttribute('height', size); bg.setAttribute('fill', '#FFFFFF');
    path.setAttribute('d', d); path.setAttribute('fill', '#0B1F18');
    svg.appendChild(bg); svg.appendChild(path);
    return svg;
  }

  // A session the API no longer accepts (401) or none at all: forget it (otherwise the login page, seeing a token,
  // would send the visitor straight back here) and go to the login page, which returns to this page afterwards.
  function toLogin() {
    var a = auth();
    try { if (a && a.clearCustomerSession && a.getUserType() !== 'organizer') a.clearCustomerSession(); } catch (e) {}
    window.location.replace('/autentificare?redirect=' + encodeURIComponent(window.location.pathname + window.location.search + window.location.hash));
  }

  window.BO_ACCOUNT = { isCustomer: isCustomer, cachedUser: cachedUser, setUser: setUser, setBadges: setBadges, save: save, calendar: calendar, qr: qr, toLogin: toLogin };
})();
