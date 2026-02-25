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
     * Track a custom event
     */
    function track(eventType, data = {}) {
        updateSessionActivity();

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
            ...data,
            timestamp: new Date().toISOString()
        };

        eventQueue.push(event);
        log('Event queued:', event);

        // Flush immediately for important events
        if (['purchase', 'add_to_cart', 'begin_checkout'].includes(eventType)) {
            flushEvents();
        }
    }

    /**
     * Track a page view
     */
    function trackPageView(data = {}) {
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
     * Track purchase
     */
    function trackPurchase(eventId, orderId, totalValue, currency = 'RON', data = {}) {
        track('purchase', {
            marketplace_event_id: eventId,
            content_id: String(orderId),
            content_type: 'order',
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
     * Get UTM parameters from URL
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
            fbclid: params.get('fbclid'),
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
        trackPurchase,
        trackSignUp,
        trackLogin,
        trackSearch,
        getVisitorId,
        getSessionId,
        setMarketplaceEventId,
        setDebug,
        flush: flushEvents
    };

})(window, document);
