/**
 * Tixello Leisure Embed — parent-side script for tenant leisure venues.
 *
 * Mirrors the Ambilet embed pattern (iframe + postMessage) but targets a
 * leisure tenant by slug. CORS is handled at the iframe (same-origin from
 * Tixello), so we don't need a token here — the embedded page itself
 * decides what's publicly viewable.
 *
 * Usage on the tenant's external website:
 *   <div id="tixello-leisure"></div>
 *   <script src="https://tixello.com/embed/tixello-leisure-embed.js"
 *     data-tenant="aquapark-splash"
 *     data-return-url="https://aquapark-splash.ro/multumesc"
 *     data-theme="light"
 *     data-accent-color="#10b981">
 *   </script>
 */
(function () {
    'use strict';

    var script = document.currentScript;
    if (!script) return;

    var tenant = script.getAttribute('data-tenant');
    if (!tenant) {
        console.error('Tixello Leisure Embed: data-tenant attribute is required');
        return;
    }

    var returnUrl = script.getAttribute('data-return-url') || window.location.href;
    var theme = script.getAttribute('data-theme') || 'light';
    var accent = script.getAttribute('data-accent-color') || '';
    var logo = script.getAttribute('data-logo') || '';
    var bgImage = script.getAttribute('data-bg-image') || '';
    var ticketTypeId = script.getAttribute('data-ticket-type') || '';
    var containerId = script.getAttribute('data-container') || 'tixello-leisure';

    var container = document.getElementById(containerId) || script.parentElement;

    // Determine the base URL where this script lives (e.g. https://tixello.com).
    var baseUrl = script.src.replace(/\/embed\/tixello-leisure-embed\.js.*$/, '');

    var params = [
        'return_url=' + encodeURIComponent(returnUrl),
        'theme=' + encodeURIComponent(theme),
    ];
    if (accent)         params.push('accent=' + encodeURIComponent(accent));
    if (logo)           params.push('logo=' + encodeURIComponent(logo));
    if (bgImage)        params.push('bg_image=' + encodeURIComponent(bgImage));
    if (ticketTypeId)   params.push('ticket_type=' + encodeURIComponent(ticketTypeId));

    var iframe = document.createElement('iframe');
    iframe.src = baseUrl + '/embed/leisure/' + encodeURIComponent(tenant) + '?' + params.join('&');
    iframe.style.cssText = 'width:100%;border:none;min-height:600px;display:block;';
    iframe.setAttribute('allowpaymentrequest', '');
    iframe.setAttribute('allow', 'payment; camera');  // camera for in-iframe QR scan
    iframe.setAttribute('loading', 'lazy');
    iframe.setAttribute('title', 'Tixello — Bilete');

    container.appendChild(iframe);

    window.addEventListener('message', function (e) {
        if (!iframe.contentWindow || e.source !== iframe.contentWindow) return;
        var data = e.data;
        if (!data || typeof data !== 'object') return;

        // Auto-resize iframe to its content
        if (data.type === 'tixello:resize' && typeof data.height === 'number') {
            iframe.style.height = Math.max(data.height, 200) + 'px';
        }

        // Order completed inside the iframe — dispatch event so the host
        // page (e.g. GA snippet, redirect logic) can react.
        if (data.type === 'tixello:order:complete') {
            window.dispatchEvent(new CustomEvent('tixello:order:complete', {
                detail: { orderNumber: data.orderNumber || '', tenant: tenant }
            }));
        }
    });
})();
