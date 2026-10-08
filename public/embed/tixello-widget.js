/**
 * Tixello Widget — self-contained embeddable event cards (Widget B).
 * No dependencies. Fetches data via CORS, renders with scoped CSS. It runs on the operator's own page, where the
 * site's language layer (VQ) does not exist: its few texts are plain English.
 *
 * Usage (single event):
 *   <div id="tixello-event"></div>
 *   <script src="https://viaqui.com/embed/tixello-widget.js"
 *     data-type="single"
 *     data-event="event-slug"
 *     data-organizer="organizer-slug"
 *     data-theme="light">
 *   </script>
 *
 * Usage (event list):
 *   <div id="tixello-events"></div>
 *   <script src="https://viaqui.com/embed/tixello-widget.js"
 *     data-type="list"
 *     data-organizer="organizer-slug"
 *     data-limit="6"
 *     data-theme="light">
 *   </script>
 */
(function () {
    'use strict';

    var script = document.currentScript;
    if (!script) return;

    var type = script.getAttribute('data-type') || 'list';
    var organizer = script.getAttribute('data-organizer') || '';
    var eventSlug = script.getAttribute('data-event') || '';
    var limit = parseInt(script.getAttribute('data-limit') || '6', 10);
    var theme = script.getAttribute('data-theme') || 'light';
    var containerId = script.getAttribute('data-container') || (type === 'single' ? 'tixello-event' : 'tixello-events');

    var container = document.getElementById(containerId) || script.parentElement;
    var baseUrl = script.src.replace(/\/embed\/tixello-widget\.js.*$/, '');
    var apiUrl = baseUrl + '/api/proxy.php';

    var isDark = theme === 'dark';
    var bgColor = isDark ? '#1e293b' : '#ffffff';
    var textColor = isDark ? '#e2e8f0' : '#1e293b';
    var mutedColor = isDark ? '#94a3b8' : '#64748b';
    var borderColor = isDark ? '#334155' : '#e2e8f0';

    // Inject scoped CSS
    var styleId = 'txw-styles';
    if (!document.getElementById(styleId)) {
        var style = document.createElement('style');
        style.id = styleId;
        style.textContent = [
            '.txw-card{background:' + bgColor + ';border:1px solid ' + borderColor + ';border-radius:12px;overflow:hidden;transition:box-shadow 0.2s,transform 0.2s;text-decoration:none;display:block;color:' + textColor + ';font-family:system-ui,-apple-system,sans-serif;}',
            '.txw-card:hover{box-shadow:0 4px 20px rgba(0,0,0,0.08);transform:translateY(-2px);text-decoration:none;color:' + textColor + ';}',
            '.txw-img{width:100%;aspect-ratio:16/10;object-fit:cover;display:block;}',
            '.txw-body{padding:12px 14px;}',
            '.txw-title{margin:0;font-size:15px;font-weight:600;line-height:1.3;color:' + textColor + ';}',
            '.txw-meta{margin:6px 0 0;font-size:12px;color:' + mutedColor + ';display:flex;align-items:center;gap:4px;}',
            '.txw-price{position:absolute;bottom:8px;right:8px;background:rgba(0,0,0,0.7);color:#fff;font-size:13px;font-weight:600;padding:4px 10px;border-radius:8px;}',
            '.txw-sold-out{position:absolute;top:8px;right:8px;background:#ef4444;color:#fff;font-size:11px;font-weight:700;padding:2px 8px;border-radius:6px;}',
            '.txw-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(280px,1fr));gap:16px;}',
            '.txw-loading{text-align:center;padding:20px;color:' + mutedColor + ';font-size:14px;}',
            '.txw-badge{display:inline-block;margin-top:12px;font-size:11px;color:' + mutedColor + ';}',
            '.txw-badge a{color:' + mutedColor + ';text-decoration:underline;}',
        ].join('\n');
        document.head.appendChild(style);
    }

    // Show loading
    container.innerHTML = '<div class="txw-loading">Loading…</div>';

    if (type === 'single' && eventSlug) {
        fetchSingleEvent();
    } else if (type === 'list' && organizer) {
        fetchEventList();
    } else {
        container.innerHTML = '<div class="txw-loading">The widget setup is incomplete.</div>';
    }

    function fetchSingleEvent() {
        fetch(apiUrl + '?action=event&slug=' + encodeURIComponent(eventSlug))
            .then(function (r) { return r.json(); })
            .then(function (data) {
                var ev = data.data?.event || data.event || null;
                if (!ev) { container.innerHTML = ''; return; }
                container.innerHTML = renderCard(ev) + poweredBy();
            })
            .catch(function () { container.innerHTML = ''; });
    }

    function fetchEventList() {
        fetch(apiUrl + '?action=organizer&slug=' + encodeURIComponent(organizer))
            .then(function (r) { return r.json(); })
            .then(function (data) {
                var events = data.data?.upcomingEvents || [];
                if (events.length === 0) { container.innerHTML = '<div class="txw-loading">No events available.</div>'; return; }
                events = events.slice(0, limit);
                var html = '<div class="txw-grid">';
                events.forEach(function (ev) { html += renderCard(ev); });
                html += '</div>';
                html += poweredBy();
                container.innerHTML = html;
            })
            .catch(function () { container.innerHTML = ''; });
    }

    function renderCard(ev) {
        var title = ev.name || ev.title || '';
        var slug = ev.slug || '';
        var image = ev.poster_url || ev.image || ev.image_url || '';
        var date = ev.event_date ? formatDate(ev.event_date) : '';
        var time = ev.start_time || '';
        var venue = ev.venue_name || '';
        var city = ev.venue_city || '';
        var price = ev.price;
        var soldOut = ev.is_sold_out;
        var url = baseUrl + '/bilete/' + encodeURIComponent(slug);

        var html = '<a href="' + esc(url) + '" target="_blank" rel="noopener" class="txw-card">';
        if (image) {
            html += '<div style="position:relative;">';
            html += '<img class="txw-img" src="' + esc(image) + '" alt="' + esc(title) + '" loading="lazy">';
            if (soldOut) html += '<div class="txw-sold-out">Sold out</div>';
            else if (price != null) html += '<div class="txw-price">from ' + (ev.currency && !/^eur$/i.test(ev.currency) ? Math.round(price) + ' ' + esc(ev.currency) : '€' + Math.round(price)) + '</div>';
            html += '</div>';
        }
        html += '<div class="txw-body">';
        html += '<h3 class="txw-title">' + esc(title) + '</h3>';
        if (date) html += '<p class="txw-meta"><svg style="width:14px;height:14px;flex-shrink:0;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>' + date + (time ? ' · ' + time : '') + '</p>';
        if (venue) html += '<p class="txw-meta"><svg style="width:14px;height:14px;flex-shrink:0;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/></svg>' + esc(venue) + (city ? ', ' + esc(city) : '') + '</p>';
        html += '</div></a>';
        return html;
    }

    function poweredBy() {
        // Derive marketplace name from domain
        var marketplaceName = 'viaqui.com';
        try {
            var host = new URL(baseUrl).hostname;
            if (host.includes('viaqui.com')) marketplaceName = 'viaqui.com';
            else if (host.includes('ambilet')) marketplaceName = 'AmBilet';
            else marketplaceName = host.replace(/^www\./, '');
        } catch(e) {}
        return '<div class="txw-badge">Tickets by <a href="' + baseUrl + '" target="_blank" rel="noopener">' + esc(marketplaceName) + '</a></div>';
    }

    function formatDate(dateStr) {
        try {
            var d = new Date(dateStr);
            return d.getDate().toString().padStart(2, '0') + '.' +
                (d.getMonth() + 1).toString().padStart(2, '0') + '.' +
                d.getFullYear();
        } catch (e) { return dateStr; }
    }

    function esc(str) {
        if (!str) return '';
        var d = document.createElement('div');
        d.textContent = str;
        return d.innerHTML;
    }

})();
