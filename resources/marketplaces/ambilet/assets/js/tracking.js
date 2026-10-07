/**
 * EPAS Marketplace Tracking Library
 *
 * Lightweight JavaScript tracking library for marketplace event analytics.
 * Tracks page views, clicks, add to cart, purchases, and other user interactions.
 *
 * Usage:
 *   <script src="/js/epas-marketplace-tracking.js"></script>
 *   <script>
 *     EPASTracking.init({
 *       apiUrl: 'https://api.eventpilot.com/api/marketplace-tracking',
 *       marketplaceClientId: 123,
 *       marketplaceEventId: 456, // optional, for event-specific pages
 *       autoTrackPageViews: true,
 *       autoTrackClicks: true
 *     });
 *   </script>
 */

(function(window, document) {
    'use strict';

    const STORAGE_KEY_VISITOR = 'epas_visitor_id';
    const STORAGE_KEY_SESSION = 'epas_session_id';
    const SESSION_TIMEOUT = 30 * 60 * 1000; // 30 minutes

    let config = {
        apiUrl: '',
        marketplaceClientId: null,
        marketplaceEventId: null,
        autoTrackPageViews: true,
        autoTrackClicks: false,
        autoTrackScroll: false,
        debug: false,
        batchSize: 10,
        batchInterval: 5000 // 5 seconds
    };

    let eventQueue = [];
    let batchTimer = null;
    let visitorId = null;
    let sessionId = null;
    let sessionStartTime = null;

    /**
     * Initialize the tracking library
     */
    function init(options) {
        config = { ...config, ...options };

        if (!config.apiUrl) {
            console.error('[EPASTracking] apiUrl is required');
            return;
        }

        if (!config.marketplaceClientId) {
            console.error('[EPASTracking] marketplaceClientId is required');
            return;
        }

        // Initialize visitor and session IDs
        initializeIds();

        // Setup auto-tracking
        if (config.autoTrackPageViews) {
            trackPageView();
            setupHistoryTracking();
        }

        if (config.autoTrackClicks) {
            setupClickTracking();
        }

        if (config.autoTrackScroll) {
            setupScrollTracking();
        }

        // Start batch processing
        startBatchProcessor();

        log('Initialized with config:', config);
    }

    /**
     * Initialize visitor and session IDs
     */
    function initializeIds() {
        // Get or create visitor ID (persistent)
        visitorId = localStorage.getItem(STORAGE_KEY_VISITOR);
        if (!visitorId) {
            visitorId = generateUUID();
            localStorage.setItem(STORAGE_KEY_VISITOR, visitorId);
        }

        // Get or create session ID (session-based with timeout)
        const sessionData = sessionStorage.getItem(STORAGE_KEY_SESSION);
        if (sessionData) {
            const parsed = JSON.parse(sessionData);
            const elapsed = Date.now() - parsed.lastActivity;
            if (elapsed < SESSION_TIMEOUT) {
                sessionId = parsed.id;
                sessionStartTime = parsed.startTime;
            }
        }

        if (!sessionId) {
            sessionId = generateUUID();
            sessionStartTime = Date.now();
        }

        updateSessionActivity();
    }

    /**
     * Update session activity timestamp
     */
    function updateSessionActivity() {
        sessionStorage.setItem(STORAGE_KEY_SESSION, JSON.stringify({
            id: sessionId,
            startTime: sessionStartTime,
            lastActivity: Date.now()
        }));
    }

    /**
     * Generate a UUID v4
     */
    function generateUUID() {
        return 'xxxxxxxx-xxxx-4xxx-yxxx-xxxxxxxxxxxx'.replace(/[xy]/g, function(c) {
            const r = Math.random() * 16 | 0;
            const v = c === 'x' ? r : (r & 0x3 | 0x8);
            return v.toString(16);
        });
    }

    /**
     * Read a first-party cookie by name (returns null if not set).
     */
    function getCookie(name) {
        const value = '; ' + document.cookie;
        const parts = value.split('; ' + name + '=');
        if (parts.length === 2) return decodeURIComponent(parts.pop().split(';').shift());
        return null;
    }

    /**
     * Set a first-party cookie. Used only as a fallback when the Meta
     * Pixel JS isn't loaded (CAPI-only org, or pixel blocked).
     * Never overwrites an existing _fbp/_fbc — pixel-set values take
     * precedence so attribution stays consistent.
     */
    function setCookie(name, value, days) {
        const expires = new Date(Date.now() + days * 86400 * 1000).toUTCString();
        const secure = window.location.protocol === 'https:' ? '; Secure' : '';
        document.cookie = name + '=' + encodeURIComponent(value) + '; expires=' + expires + '; path=/; SameSite=Lax' + secure;
    }

    /**
     * Ensure first-party Facebook cookies exist for CAPI deduplication.
     * Format follows Meta's official spec:
     *   _fbc = fb.1.{timestamp_ms}.{fbclid}
     *   _fbp = fb.1.{timestamp_ms}.{random_10digit}
     * Both with 90-day max-age.
     */
    function ensureFacebookCookies() {
        try {
            // _fbc derived from fbclid in URL
            if (!getCookie('_fbc')) {
                const params = new URLSearchParams(window.location.search);
                const fbclid = params.get('fbclid');
                if (fbclid) {
                    setCookie('_fbc', 'fb.1.' + Date.now() + '.' + fbclid, 90);
                }
            }
            // _fbp generated if missing
            if (!getCookie('_fbp')) {
                const rand = Math.floor(Math.random() * 9000000000) + 1000000000;
                setCookie('_fbp', 'fb.1.' + Date.now() + '.' + rand, 90);
            }
        } catch (e) {
            // Cookie API may be blocked (private mode, strict ITP) — silent fallback
        }
    }

    // Browser Meta Pixel events, mirrored from the tracking events. Each one reuses the
    // event's client_event_id as eventID — the same id the backend bridge sends to CAPI
    // (Purchase: purchase_{order_id}, same as SendFacebookCapiPurchaseJob) — so Meta
    // dedupes browser + server. PageView is fired by the pixel snippet itself.
    const META_EVENTS = {
        view_item: 'ViewContent',
        add_to_cart: 'AddToCart',
        begin_checkout: 'InitiateCheckout',
        add_payment_info: 'AddPaymentInfo',
        view_cart: 'ViewCart',
        purchase: 'Purchase',
        search: 'Search',
        sign_up: 'CompleteRegistration',
        lead: 'Lead'
    };

    // Not standard Meta events: sent with trackCustom under the same name the backend uses
    const META_CUSTOM_EVENTS = ['ViewCart'];

    // head.php injects the pixel lazily (first interaction or ~1s after load), so early
    // events (ViewContent) are held until fbq exists instead of being dropped.
    let metaPending = [];
    let metaWaitTimer = null;

    function waitForMetaPixel() {
        if (metaWaitTimer) return;
        const startedAt = Date.now();
        metaWaitTimer = setInterval(function () {
            if (typeof window.fbq === 'function' || Date.now() - startedAt > 30000) {
                clearInterval(metaWaitTimer);
                metaWaitTimer = null;
                const pending = metaPending;
                metaPending = [];
                if (typeof window.fbq === 'function') {
                    pending.forEach(function (p) { sendMetaPixelEvent(p[0], p[1]); });
                }
            }
        }, 250);
    }

    function sendMetaPixelEvent(eventType, event) {
        const name = META_EVENTS[eventType];
        if (!name) return;
        if (typeof window.fbq !== 'function') {
            metaPending.push([eventType, event]);
            waitForMetaPixel();
            return;
        }
        try {
            // A refresh of the thank-you page must not send the purchase twice
            let sentKey = null;
            if (eventType === 'purchase') {
                sentKey = 'epas_fb_sent_' + event.client_event_id;
                if (localStorage.getItem(sentKey)) return;
            }

            // Same custom_data as buildCapiCustomData() on the backend
            const params = {};
            if (eventType === 'search') {
                if (event.event_label) params.search_string = String(event.event_label);
            } else if (eventType === 'lead' || eventType === 'sign_up') {
                if (event.event_label) params.content_name = String(event.event_label);
            } else {
                if (event.event_value !== null && event.event_value !== undefined && event.event_value !== '') {
                    params.value = parseFloat(event.event_value) || 0;
                }
                if (event.currency) params.currency = event.currency;
                params.content_type = event.content_type || 'product';
                if (event.content_id) params.content_ids = [String(event.content_id)];
                if (event.content_name) params.content_name = event.content_name;
                const items = event.quantity || event.num_items;
                if (items) params.num_items = parseInt(items, 10);
            }

            window.fbq(META_CUSTOM_EVENTS.includes(name) ? 'trackCustom' : 'track', name, params, { eventID: String(event.client_event_id) });
            if (sentKey) localStorage.setItem(sentKey, '1');
            log('Meta Pixel:', name, params, event.client_event_id);
        } catch (e) {
            // tracking must never break the page
        }
    }

    /**
     * Identity of the logged-in customer (email, name, phone), added to every event so
     * the server-side funnel events match in Meta the way Purchase does. Only with
     * marketing consent, like the pixel itself. Empty for guests.
     */
    function getCustomerIdentity() {
        try {
            const consent = JSON.parse(localStorage.getItem('ambilet_cookie_consent') || 'null');
            if (!consent || !consent.marketing) return {};
            if (localStorage.getItem('ambilet_user_type') !== 'customer') return {};
            const c = JSON.parse(localStorage.getItem('ambilet_customer_data') || 'null');
            if (!c) return {};

            const identity = {};
            const email = String(c.email || '').trim();
            // The backend rejects the whole event for an invalid email
            if (/^[^\s@]+@[^\s@]+\.[^\s@]{2,}$/.test(email)) {
                identity.email = email;
                identity.customer_email = email;
            }
            const name = [c.first_name, c.last_name].filter(Boolean).join(' ').trim() || String(c.name || '').trim();
            if (name) identity.customer_name = name.substring(0, 255);
            const phone = String(c.phone || '').trim();
            if (phone) identity.customer_phone = phone.substring(0, 50);
            return identity;
        } catch (e) {
            return {};
        }
    }

    /**
     * Track a custom event
     */
    function track(eventType, data = {}) {
        updateSessionActivity();
        ensureFacebookCookies();

        const event = {
            event_type: eventType,
            marketplace_client_id: config.marketplaceClientId,
            marketplace_event_id: data.marketplace_event_id || config.marketplaceEventId,
            visitor_id: visitorId,
            session_id: sessionId,
            page_url: window.location.href,
            page_path: window.location.pathname,
            page_title: document.title,
            referrer: document.referrer,
            screen_width: window.screen.width,
            screen_height: window.screen.height,
            ...getUtmParams(),
            ...getCustomerIdentity(),
            ...data,
            // FB CAPI extras (kept last so caller's data cannot override)
            client_event_id: data.client_event_id || generateUUID(),
            fbp: getCookie('_fbp'),
            fbc: getCookie('_fbc'),
            timestamp: new Date().toISOString()
        };

        eventQueue.push(event);
        log('Event queued:', event);

        sendMetaPixelEvent(eventType, event);

        // Flush immediately for important events
        if (['purchase', 'add_to_cart', 'begin_checkout', 'add_payment_info'].includes(eventType)) {
            flushEvents();
        }
    }

    /**
     * Track a page view
     */
    let firstPageView = true;

    function trackPageView(data = {}) {
        // The first page_view is the pageview the pixel snippet sends to Meta. Whichever
        // runs first creates the id in window.__fbPageViewEventId and the other reuses it
        // (the snippet usually loads later), so the CAPI PageView dedupes with the pixel's.
        if (firstPageView && !data.client_event_id) {
            window.__fbPageViewEventId = window.__fbPageViewEventId || ('pv_' + generateUUID());
            data = { ...data, client_event_id: window.__fbPageViewEventId };
        }
        firstPageView = false;
        track('page_view', data);
    }

    /**
     * Track viewing an event/item
     */
    function trackViewItem(eventId, eventName, data = {}) {
        track('view_item', {
            marketplace_event_id: eventId,
            content_id: String(eventId),
            content_type: 'event',
            content_name: eventName,
            ...data
        });
    }

    /**
     * Track add to cart
     */
    function trackAddToCart(eventId, ticketType, quantity, price, currency = 'RON', data = {}) {
        track('add_to_cart', {
            marketplace_event_id: eventId,
            content_id: String(ticketType),
            content_type: 'ticket',
            quantity: quantity,
            event_value: price * quantity,
            currency: currency,
            ...data
        });
    }

    /**
     * Track begin checkout
     */
    function trackBeginCheckout(eventId, totalValue, currency = 'RON', data = {}) {
        track('begin_checkout', {
            marketplace_event_id: eventId,
            event_value: totalValue,
            currency: currency,
            ...data
        });
    }

    /**
     * Track the cart page being viewed with items in it
     */
    function trackViewCart(eventId, totalValue, currency = 'RON', data = {}) {
        track('view_cart', {
            marketplace_event_id: eventId,
            event_value: totalValue,
            currency: currency,
            ...data
        });
    }

    /**
     * Track the buyer submitting the checkout form (payment step)
     */
    function trackAddPaymentInfo(eventId, totalValue, currency = 'RON', data = {}) {
        track('add_payment_info', {
            marketplace_event_id: eventId,
            event_value: totalValue,
            currency: currency,
            ...data
        });
    }

    /**
     * Track purchase
     */
    function trackPurchase(eventId, orderId, totalValue, currency = 'RON', data = {}) {
        track('purchase', {
            marketplace_event_id: eventId,
            content_id: String(orderId),
            content_type: 'order',
            order_id: orderId,
            event_value: totalValue,
            currency: currency,
            ...data
        });
    }

    /**
     * Track user signup
     */
    function trackSignUp(method = 'email', data = {}) {
        track('sign_up', {
            event_label: method,
            ...data
        });
    }

    /**
     * Track user login
     */
    function trackLogin(method = 'email', data = {}) {
        track('login', {
            event_label: method,
            ...data
        });
    }

    /**
     * Track search
     */
    function trackSearch(searchTerm, resultsCount = null, data = {}) {
        track('search', {
            event_label: searchTerm,
            event_value: resultsCount,
            ...data
        });
    }

    /**
     * Track a lead (newsletter, contact form, demo request, etc.)
     */
    function trackLead(formType, data = {}) {
        track('lead', {
            event_label: formType,
            ...data
        });
    }

    /**
     * Extract fbclid from the _fbc cookie set on the first visit.
     * Meta's spec: `fb.1.{timestamp_ms}.{fbclid}` — we split on the
     * third dot and rejoin the rest so an fbclid containing dots
     * survives intact.
     */
    function fbclidFromCookie() {
        const fbc = getCookie('_fbc');
        if (!fbc) return null;
        const idx1 = fbc.indexOf('.');
        if (idx1 < 0) return null;
        const idx2 = fbc.indexOf('.', idx1 + 1);
        if (idx2 < 0) return null;
        const idx3 = fbc.indexOf('.', idx2 + 1);
        if (idx3 < 0) return null;
        return fbc.substring(idx3 + 1) || null;
    }

    /**
     * Get UTM parameters from URL, falling back to first-party cookies
     * for click ids when the URL no longer carries them (e.g. on the
     * thank-you page, which is reached without query params).
     */
    function getUtmParams() {
        const params = new URLSearchParams(window.location.search);
        return {
            utm_source: params.get('utm_source'),
            utm_medium: params.get('utm_medium'),
            utm_campaign: params.get('utm_campaign'),
            utm_term: params.get('utm_term'),
            utm_content: params.get('utm_content'),
            gclid: params.get('gclid'),
            fbclid: params.get('fbclid') || fbclidFromCookie(),
            ttclid: params.get('ttclid')
        };
    }

    /**
     * Setup tracking for SPA navigation
     */
    function setupHistoryTracking() {
        // Track history changes (for SPAs)
        const originalPushState = history.pushState;
        history.pushState = function() {
            originalPushState.apply(history, arguments);
            trackPageView();
        };

        const originalReplaceState = history.replaceState;
        history.replaceState = function() {
            originalReplaceState.apply(history, arguments);
            trackPageView();
        };

        window.addEventListener('popstate', function() {
            trackPageView();
        });
    }

    /**
     * Setup click tracking
     */
    function setupClickTracking() {
        document.addEventListener('click', function(e) {
            const target = e.target.closest('a, button, [data-track-click]');
            if (!target) return;

            const trackData = target.dataset.trackClick;
            if (trackData) {
                try {
                    const data = JSON.parse(trackData);
                    track('click', data);
                } catch (err) {
                    track('click', { event_label: trackData });
                }
            } else if (target.tagName === 'A' && target.href) {
                track('click', {
                    event_label: target.textContent.trim().substring(0, 100),
                    content_id: target.href
                });
            }
        }, true);
    }

    /**
     * Setup scroll depth tracking
     */
    function setupScrollTracking() {
        const milestones = [25, 50, 75, 100];
        const reached = new Set();

        function checkScroll() {
            const scrollTop = window.scrollY;
            const docHeight = document.documentElement.scrollHeight - window.innerHeight;
            const scrollPercent = Math.round((scrollTop / docHeight) * 100);

            for (const milestone of milestones) {
                if (scrollPercent >= milestone && !reached.has(milestone)) {
                    reached.add(milestone);
                    track('scroll', {
                        event_value: milestone,
                        event_label: `${milestone}%`
                    });
                }
            }
        }

        let scrollTimeout;
        window.addEventListener('scroll', function() {
            clearTimeout(scrollTimeout);
            scrollTimeout = setTimeout(checkScroll, 100);
        }, { passive: true });
    }

    /**
     * Start the batch event processor
     */
    function startBatchProcessor() {
        batchTimer = setInterval(flushEvents, config.batchInterval);

        // Flush on page unload
        window.addEventListener('beforeunload', function() {
            flushEvents(true);
        });

        // Flush on visibility change (when user leaves tab)
        document.addEventListener('visibilitychange', function() {
            if (document.visibilityState === 'hidden') {
                flushEvents(true);
            }
        });
    }

    /**
     * Flush queued events to the server
     */
    function flushEvents(sync = false) {
        if (eventQueue.length === 0) return;

        const events = eventQueue.splice(0, config.batchSize);

        if (events.length === 1) {
            sendSingleEvent(events[0], sync);
        } else {
            sendBatchEvents(events, sync);
        }

        // If there are more events, flush again
        if (eventQueue.length > 0) {
            setTimeout(() => flushEvents(), 100);
        }
    }

    /**
     * Send a single event to the server
     */
    function sendSingleEvent(event, sync = false) {
        const url = `${config.apiUrl}/track`;

        if (sync && navigator.sendBeacon) {
            navigator.sendBeacon(url, JSON.stringify(event));
            log('Event sent via beacon:', event);
            return;
        }

        fetch(url, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json'
            },
            body: JSON.stringify(event),
            keepalive: sync
        })
        .then(response => response.json())
        .then(data => {
            log('Event sent successfully:', data);
        })
        .catch(error => {
            console.error('[EPASTracking] Failed to send event:', error);
            // Re-queue the event for retry
            eventQueue.unshift(event);
        });
    }

    /**
     * Send batch events to the server
     */
    function sendBatchEvents(events, sync = false) {
        const url = `${config.apiUrl}/batch`;

        if (sync && navigator.sendBeacon) {
            navigator.sendBeacon(url, JSON.stringify({ events }));
            log('Batch sent via beacon:', events.length, 'events');
            return;
        }

        fetch(url, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json'
            },
            body: JSON.stringify({ events }),
            keepalive: sync
        })
        .then(response => response.json())
        .then(data => {
            log('Batch sent successfully:', data);
        })
        .catch(error => {
            console.error('[EPASTracking] Failed to send batch:', error);
            // Re-queue all events for retry
            eventQueue.unshift(...events);
        });
    }

    /**
     * Debug logging
     */
    function log(...args) {
        if (config.debug) {
            console.log('[EPASTracking]', ...args);
        }
    }

    /**
     * Get current visitor ID
     */
    function getVisitorId() {
        return visitorId;
    }

    /**
     * Get current session ID
     */
    function getSessionId() {
        return sessionId;
    }

    /**
     * Set marketplace event ID (for SPA navigation to event pages)
     */
    function setMarketplaceEventId(eventId) {
        config.marketplaceEventId = eventId;
    }

    /**
     * Enable/disable debug mode
     */
    function setDebug(enabled) {
        config.debug = enabled;
    }

    // Expose public API
    window.EPASTracking = {
        init,
        track,
        trackPageView,
        trackViewItem,
        trackAddToCart,
        trackBeginCheckout,
        trackViewCart,
        trackAddPaymentInfo,
        trackPurchase,
        trackSignUp,
        trackLogin,
        trackSearch,
        trackLead,
        getVisitorId,
        getSessionId,
        setMarketplaceEventId,
        setDebug,
        flush: flushEvents
    };

})(window, document);
