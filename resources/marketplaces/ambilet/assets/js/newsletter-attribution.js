/**
 * Newsletter attribution helper.
 *
 * Captures the `nl=<newsletter_id>` URL param dropped on the landing
 * page by the platform's newsletter click-redirect, stores it in
 * localStorage with a 30-day TTL, and exposes a getter so the checkout
 * layer can include it in the order POST.
 *
 * Wiring (already done):
 *   - NewsletterRenderer rewrites every <a href> through
 *     core.tixello.com/newsletter/click/{token}, which redirects with
 *     ?utm_source=newsletter&utm_campaign=nl_<id>&nl=<id>.
 *   - OrdersController::create accepts `nl` (or
 *     `newsletter_attribution_id`) on the request, validates it
 *     against the marketplace, and persists on orders.
 *   - OrderObserver credits the campaign on status=paid.
 *
 * This file is the missing piece: it must be loaded on every page of
 * the marketplace site (head or before-checkout) and the checkout
 * payload must spread in `getNewsletterAttribution()`.
 */
(function () {
    'use strict';

    var STORAGE_KEY = 'amb_nl_attr';
    var TTL_DAYS = 30;

    function nowMs() { return Date.now ? Date.now() : new Date().getTime(); }
    function ttlMs() { return TTL_DAYS * 24 * 60 * 60 * 1000; }

    function read() {
        try {
            var raw = window.localStorage.getItem(STORAGE_KEY);
            if (!raw) return null;
            var parsed = JSON.parse(raw);
            if (!parsed || !parsed.id) return null;
            if (parsed.expires_at && parsed.expires_at < nowMs()) {
                window.localStorage.removeItem(STORAGE_KEY);
                return null;
            }
            return parsed;
        } catch (e) {
            return null;
        }
    }

    function write(id) {
        try {
            window.localStorage.setItem(STORAGE_KEY, JSON.stringify({
                id: id,
                captured_at: nowMs(),
                expires_at: nowMs() + ttlMs()
            }));
        } catch (e) {
            // Storage may be full / blocked; degrade silently.
        }
    }

    function captureFromUrl() {
        try {
            var params = new URLSearchParams(window.location.search);
            var raw = params.get('nl');
            if (!raw) return;
            var id = parseInt(raw, 10);
            if (!isNaN(id) && id > 0) write(id);
        } catch (e) {
            // URLSearchParams not available on ancient browsers — ignore.
        }
    }

    // Run on script load. Capture takes precedence over a prior value
    // so the most recent newsletter click wins (last-touch attribution).
    captureFromUrl();

    // Expose: returns the current newsletter id (or null) and the
    // payload shape expected by OrdersController::create.
    window.AmbiletNewsletterAttribution = {
        getId: function () {
            var entry = read();
            return entry ? entry.id : null;
        },
        getPayload: function () {
            var id = this.getId();
            return id ? { newsletter_attribution_id: id, nl: id } : {};
        },
        clear: function () {
            try { window.localStorage.removeItem(STORAGE_KEY); } catch (e) {}
        }
    };
})();
