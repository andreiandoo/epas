/**
 * Tixello Embed — parent-side script for Widget A (Full iframe).
 *
 * Usage:
 *   <div id="tixello-widget"></div>
 *   <script src="https://ambilet.ro/embed/tixello-embed.js"
 *     data-organizer="slug-organizator"
 *     data-return-url="https://site-organizator.ro/multumesc"
 *     data-theme="light"
 *     data-accent-color="#6366f1">
 *   </script>
 */
(function () {
    'use strict';

    var script = document.currentScript;
    if (!script) return;

    var organizer = script.getAttribute('data-organizer');
    if (!organizer) { console.error('Tixello Embed: data-organizer is required'); return; }

    var returnUrl = script.getAttribute('data-return-url') || window.location.href;
    var theme = script.getAttribute('data-theme') || 'light';
    var accent = script.getAttribute('data-accent-color') || '';
    var logo = script.getAttribute('data-logo') || '';
    var bgImage = script.getAttribute('data-bg-image') || '';
    var containerId = script.getAttribute('data-container') || 'tixello-widget';

    var container = document.getElementById(containerId) || script.parentElement;

    // Build iframe URL
    var baseUrl = script.src.replace(/\/embed\/tixello-embed\.js.*$/, '');
    var params = 'return_url=' + encodeURIComponent(returnUrl) +
        '&theme=' + encodeURIComponent(theme) +
        (accent ? '&accent=' + encodeURIComponent(accent) : '') +
        (logo ? '&logo=' + encodeURIComponent(logo) : '') +
        (bgImage ? '&bg_image=' + encodeURIComponent(bgImage) : '');

    var iframe = document.createElement('iframe');
    iframe.src = baseUrl + '/embed/' + encodeURIComponent(organizer) + '?' + params;
    iframe.style.cssText = 'width:100%;border:none;min-height:600px;display:block;';
    iframe.setAttribute('allowpaymentrequest', '');
    iframe.setAttribute('allow', 'payment');
    iframe.setAttribute('loading', 'lazy');
    iframe.setAttribute('title', 'Tixello — Bilete');

    container.appendChild(iframe);

    // Listen for postMessage from iframe
    window.addEventListener('message', function (e) {
        // Verify source is our iframe
        if (!iframe.contentWindow || e.source !== iframe.contentWindow) return;

        var data = e.data;
        if (!data || typeof data !== 'object') return;

        // Auto-resize iframe to content height
        if (data.type === 'tixello:resize' && typeof data.height === 'number') {
            iframe.style.height = Math.max(data.height, 200) + 'px';
        }

        // Order completion event
        if (data.type === 'tixello:order:complete') {
            var event = new CustomEvent('tixello:order:complete', {
                detail: { orderNumber: data.orderNumber || '' }
            });
            window.dispatchEvent(event);
        }
    });

})();
