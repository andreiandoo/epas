/**
 * Whitelabel Tracking Library — sends events to /marketplace-tracking/track
 * through the local /api/proxy.php (which holds the API key server-side).
 *
 * Every event auto-tags `channel: 'whitelabel'` so the marketplace analytics
 * dashboard can split funnels by traffic source.
 *
 * Usage:
 *   <script src="/assets/js/tracking.js"></script>
 *   <script>
 *     WLTracking.init({
 *       marketplaceClientId: 1,
 *       marketplaceOrganizerId: 256,
 *       marketplaceEventId: null,
 *       debug: false
 *     });
 *   </script>
 *
 * Conversion calls (fire manually from page-specific JS):
 *   WLTracking.trackViewItem(eventId, eventName)
 *   WLTracking.trackAddToCart(eventId, ticketTypeId, qty, price)
 *   WLTracking.trackBeginCheckout(eventId, totalValue)
 *   WLTracking.trackPurchase(eventId, orderId, totalValue)
 */
(function (window, document) {
    'use strict';

    const STORAGE_KEY_VISITOR = 'wl_visitor_id';
    const STORAGE_KEY_SESSION = 'wl_session_id';
    const SESSION_TIMEOUT = 30 * 60 * 1000;

    // Proxy endpoint relative to the whitelabel site root. The proxy forwards
    // to API_BASE_URL + '/marketplace-tracking/track' on the Tixello core,
    // with the X-API-Key header injected server-side.
    const PROXY_URL = '/api/proxy.php?endpoint=/marketplace-tracking/track';
    const PROXY_BATCH_URL = '/api/proxy.php?endpoint=/marketplace-tracking/batch';

    let config = {
        marketplaceClientId: null,
        marketplaceOrganizerId: null,
        marketplaceEventId: null,
        channel: 'whitelabel',
        debug: false,
        batchSize: 10,
        batchInterval: 5000,
    };

    let eventQueue = [];
    let batchTimer = null;
    let visitorId = null;
    let sessionId = null;
    let sessionStartTime = null;

    function init(options) {
        config = Object.assign({}, config, options || {});
        if (!config.marketplaceClientId) {
            console.error('[WLTracking] marketplaceClientId is required');
            return;
        }
        initializeIds();
        trackPageView();
        startBatchProcessor();
        log('Initialized', config);
    }

    function initializeIds() {
        try {
            visitorId = localStorage.getItem(STORAGE_KEY_VISITOR);
            if (!visitorId) {
                visitorId = generateUUID();
                localStorage.setItem(STORAGE_KEY_VISITOR, visitorId);
            }
            const sessionData = sessionStorage.getItem(STORAGE_KEY_SESSION);
            if (sessionData) {
                const parsed = JSON.parse(sessionData);
                if (Date.now() - parsed.lastActivity < SESSION_TIMEOUT) {
                    sessionId = parsed.id;
                    sessionStartTime = parsed.startTime;
                }
            }
        } catch (e) {
            // Storage may be blocked (private mode, strict ITP) — fall back to ephemeral
        }
        if (!sessionId) {
            sessionId = generateUUID();
            sessionStartTime = Date.now();
        }
        updateSessionActivity();
    }

    function updateSessionActivity() {
        try {
            sessionStorage.setItem(STORAGE_KEY_SESSION, JSON.stringify({
                id: sessionId,
                startTime: sessionStartTime,
                lastActivity: Date.now(),
            }));
        } catch (e) {}
    }

    function generateUUID() {
        return 'xxxxxxxx-xxxx-4xxx-yxxx-xxxxxxxxxxxx'.replace(/[xy]/g, function (c) {
            const r = Math.random() * 16 | 0;
            const v = c === 'x' ? r : (r & 0x3 | 0x8);
            return v.toString(16);
        });
    }

    function getCookie(name) {
        const value = '; ' + document.cookie;
        const parts = value.split('; ' + name + '=');
        if (parts.length === 2) return decodeURIComponent(parts.pop().split(';').shift());
        return null;
    }

    function getUtmParams() {
        const params = new URLSearchParams(window.location.search);
        return {
            utm_source: params.get('utm_source'),
            utm_medium: params.get('utm_medium'),
            utm_campaign: params.get('utm_campaign'),
            utm_term: params.get('utm_term'),
            utm_content: params.get('utm_content'),
            gclid: params.get('gclid'),
            fbclid: params.get('fbclid'),
            ttclid: params.get('ttclid'),
        };
    }

    function track(eventType, data) {
        data = data || {};
        updateSessionActivity();

        const event = Object.assign({
            event_type: eventType,
            channel: config.channel,
            marketplace_client_id: config.marketplaceClientId,
            marketplace_event_id: data.marketplace_event_id || config.marketplaceEventId,
            visitor_id: visitorId,
            session_id: sessionId,
            page_url: window.location.href,
            page_path: window.location.pathname,
            page_title: document.title,
            referrer: document.referrer,
        }, getUtmParams(), data, {
            // Last so caller cannot override
            client_event_id: data.client_event_id || generateUUID(),
            fbp: getCookie('_fbp'),
            fbc: getCookie('_fbc'),
            timestamp: new Date().toISOString(),
        });

        eventQueue.push(event);
        log('Queued', event);

        // Flush immediately for important conversion events
        if (['purchase', 'add_to_cart', 'begin_checkout'].indexOf(eventType) !== -1) {
            flushEvents();
        }
    }

    function trackPageView(data) {
        track('page_view', data || {});
    }

    function trackViewItem(eventId, eventName, data) {
        track('view_item', Object.assign({
            marketplace_event_id: eventId,
            content_id: String(eventId),
            content_type: 'event',
            content_name: eventName,
        }, data || {}));
    }

    function trackAddToCart(eventId, ticketTypeId, quantity, price, currency, data) {
        track('add_to_cart', Object.assign({
            marketplace_event_id: eventId,
            content_id: String(ticketTypeId),
            content_type: 'ticket',
            quantity: quantity,
            event_value: price * quantity,
            currency: currency || 'RON',
        }, data || {}));
    }

    function trackBeginCheckout(eventId, totalValue, currency, data) {
        track('begin_checkout', Object.assign({
            marketplace_event_id: eventId,
            event_value: totalValue,
            currency: currency || 'RON',
        }, data || {}));
    }

    function trackPurchase(eventId, orderId, totalValue, currency, data) {
        track('purchase', Object.assign({
            marketplace_event_id: eventId,
            content_id: String(orderId),
            content_type: 'order',
            order_id: orderId,
            event_value: totalValue,
            currency: currency || 'RON',
            // Deterministic event id for Meta CAPI dedup across browser + server
            client_event_id: 'purchase_' + orderId + '_whitelabel',
        }, data || {}));
    }

    function startBatchProcessor() {
        batchTimer = setInterval(flushEvents, config.batchInterval);
        window.addEventListener('beforeunload', function () { flushEvents(true); });
        document.addEventListener('visibilitychange', function () {
            if (document.visibilityState === 'hidden') flushEvents(true);
        });
    }

    function flushEvents(sync) {
        if (eventQueue.length === 0) return;
        const events = eventQueue.splice(0, config.batchSize);
        if (events.length === 1) sendSingleEvent(events[0], sync);
        else sendBatchEvents(events, sync);
        if (eventQueue.length > 0) setTimeout(function () { flushEvents(); }, 100);
    }

    function sendSingleEvent(event, sync) {
        if (sync && navigator.sendBeacon) {
            navigator.sendBeacon(PROXY_URL, JSON.stringify(event));
            log('Beacon (single)', event.event_type);
            return;
        }
        fetch(PROXY_URL, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
            body: JSON.stringify(event),
            keepalive: sync,
        }).catch(function (err) {
            console.warn('[WLTracking] send failed, requeue:', err);
            eventQueue.unshift(event);
        });
    }

    function sendBatchEvents(events, sync) {
        if (sync && navigator.sendBeacon) {
            navigator.sendBeacon(PROXY_BATCH_URL, JSON.stringify({ events: events }));
            log('Beacon (batch)', events.length);
            return;
        }
        fetch(PROXY_BATCH_URL, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
            body: JSON.stringify({ events: events }),
            keepalive: sync,
        }).catch(function (err) {
            console.warn('[WLTracking] batch send failed, requeue:', err);
            eventQueue.unshift.apply(eventQueue, events);
        });
    }

    function log() {
        if (!config.debug) return;
        const args = ['[WLTracking]'].concat(Array.prototype.slice.call(arguments));
        console.log.apply(console, args);
    }

    function setMarketplaceEventId(id) { config.marketplaceEventId = id; }
    function setDebug(enabled) { config.debug = !!enabled; }

    window.WLTracking = {
        init: init,
        track: track,
        trackPageView: trackPageView,
        trackViewItem: trackViewItem,
        trackAddToCart: trackAddToCart,
        trackBeginCheckout: trackBeginCheckout,
        trackPurchase: trackPurchase,
        setMarketplaceEventId: setMarketplaceEventId,
        setDebug: setDebug,
        flush: flushEvents,
    };
})(window, document);
