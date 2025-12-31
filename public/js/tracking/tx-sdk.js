/**
 * TX Tracking SDK v1.0.0
 * Event Intelligence Rail - Client-side tracking
 *
 * Usage:
 *   const tx = new TxTracker({
 *     tenantId: 'your-tenant-id',
 *     apiEndpoint: 'https://core.tixello.com/api/tx/events/batch'
 *   });
 *   tx.pageView();
 */

(function(window, document) {
  'use strict';

  // Constants
  const SDK_VERSION = '1.0.0';
  const STORAGE_PREFIX = 'tx_';
  const SESSION_TIMEOUT_MS = 30 * 60 * 1000; // 30 minutes
  const VISITOR_ID_EXPIRY_DAYS = 365;
  const DEFAULT_FLUSH_INTERVAL_MS = 5000;
  const MAX_QUEUE_SIZE = 100;
  const MAX_RETRY_ATTEMPTS = 3;

  /**
   * Generate UUID v4
   */
  function generateUUID() {
    if (typeof crypto !== 'undefined' && crypto.randomUUID) {
      return crypto.randomUUID();
    }
    // Fallback for older browsers
    return 'xxxxxxxx-xxxx-4xxx-yxxx-xxxxxxxxxxxx'.replace(/[xy]/g, function(c) {
      const r = Math.random() * 16 | 0;
      const v = c === 'x' ? r : (r & 0x3 | 0x8);
      return v.toString(16);
    });
  }

  /**
   * Safe JSON parse with fallback
   */
  function safeJsonParse(str, fallback) {
    try {
      return JSON.parse(str);
    } catch (e) {
      return fallback;
    }
  }

  /**
   * Get value from localStorage safely
   */
  function storageGet(key) {
    try {
      return localStorage.getItem(STORAGE_PREFIX + key);
    } catch (e) {
      return null;
    }
  }

  /**
   * Set value in localStorage safely
   */
  function storageSet(key, value) {
    try {
      localStorage.setItem(STORAGE_PREFIX + key, value);
      return true;
    } catch (e) {
      return false;
    }
  }

  /**
   * Remove value from localStorage safely
   */
  function storageRemove(key) {
    try {
      localStorage.removeItem(STORAGE_PREFIX + key);
      return true;
    } catch (e) {
      return false;
    }
  }

  /**
   * TxTracker - Main tracking class
   */
  class TxTracker {
    constructor(config) {
      // Validate required config
      if (!config.tenantId) {
        console.error('[TX] tenantId is required');
        return;
      }

      // Configuration
      this.tenantId = config.tenantId;
      this.siteId = config.siteId || null;
      this.apiEndpoint = config.apiEndpoint || '/api/tx/events/batch';
      this.debug = config.debug || false;
      this.flushInterval = config.flushInterval || DEFAULT_FLUSH_INTERVAL_MS;
      this.autoTrackPageView = config.autoTrackPageView !== false;
      this.autoTrackEngagement = config.autoTrackEngagement !== false;
      this.respectDoNotTrack = config.respectDoNotTrack || false;

      // Check Do Not Track
      if (this.respectDoNotTrack && this._isDoNotTrackEnabled()) {
        this._log('Do Not Track enabled, tracking disabled');
        this.disabled = true;
        return;
      }

      this.disabled = false;

      // Initialize identifiers
      this.visitorId = this._initVisitorId();
      this.sessionId = null;
      this.sequenceNo = 0;
      this._initSession();

      // Capture first touch attribution
      this._captureFirstTouch();

      // Check for cross-domain identity hint
      this._checkIdentityHint();

      // Initialize consent
      this.consentSnapshot = this._getConsentSnapshot();

      // Event queue
      this.eventQueue = [];
      this.isFlushing = false;
      this.retryCount = 0;

      // Engagement tracking
      this.engagementData = {
        startTime: Date.now(),
        activeTime: 0,
        lastActiveCheck: Date.now(),
        isVisible: !document.hidden,
        isFocused: document.hasFocus(),
        visibilityChanges: 0,
        focusChanges: 0,
        maxScrollPct: 0,
        interactions: 0
      };

      // Initialize engagement tracking
      if (this.autoTrackEngagement) {
        this._initEngagementTracking();
      }

      // Start auto-flush
      this._startAutoFlush();

      // Auto page view
      if (this.autoTrackPageView) {
        this._onDOMReady(() => this.pageView());
      }

      this._log('Initialized', { visitorId: this.visitorId, sessionId: this.sessionId });
    }

    // =========================================================================
    // VISITOR & SESSION MANAGEMENT
    // =========================================================================

    /**
     * Initialize or retrieve visitor ID
     */
    _initVisitorId() {
      // Check URL for cross-domain identity hint first
      const urlParams = new URLSearchParams(window.location.search);
      const hintVid = urlParams.get('_txvid');

      if (hintVid && this._isValidUUID(hintVid)) {
        storageSet('vid', hintVid);
        // Clean URL without reload
        this._cleanUrlParam('_txvid');
        return hintVid;
      }

      // Check existing visitor ID
      let vid = storageGet('vid');
      if (vid && this._isValidUUID(vid)) {
        return vid;
      }

      // Generate new visitor ID
      vid = generateUUID();
      storageSet('vid', vid);
      return vid;
    }

    /**
     * Initialize or continue session
     */
    _initSession() {
      const sessionData = safeJsonParse(storageGet('session'), null);
      const now = Date.now();

      if (sessionData && sessionData.sid && sessionData.lastActivity) {
        const timeSinceLastActivity = now - sessionData.lastActivity;

        if (timeSinceLastActivity < SESSION_TIMEOUT_MS) {
          // Continue existing session
          this.sessionId = sessionData.sid;
          this.sequenceNo = sessionData.seq || 0;
          this._updateSessionActivity();
          return;
        }
      }

      // Start new session
      this.sessionId = generateUUID();
      this.sequenceNo = 0;
      this._saveSessionData();

      this._log('New session started', { sessionId: this.sessionId });
    }

    /**
     * Update session last activity timestamp
     */
    _updateSessionActivity() {
      this._saveSessionData();
    }

    /**
     * Save session data to storage
     */
    _saveSessionData() {
      const sessionData = {
        sid: this.sessionId,
        lastActivity: Date.now(),
        seq: this.sequenceNo
      };
      storageSet('session', JSON.stringify(sessionData));
    }

    /**
     * Get and increment sequence number
     */
    _getNextSequenceNo() {
      this.sequenceNo++;
      this._saveSessionData();
      return this.sequenceNo;
    }

    /**
     * Get current visitor ID (for external use)
     */
    getVisitorId() {
      return this.visitorId;
    }

    /**
     * Get current session ID (for external use)
     */
    getSessionId() {
      return this.sessionId;
    }

    // =========================================================================
    // FIRST TOUCH ATTRIBUTION
    // =========================================================================

    /**
     * Capture first touch attribution data
     */
    _captureFirstTouch() {
      // Only capture if not already set
      if (storageGet('first_touch')) {
        return;
      }

      const params = new URLSearchParams(window.location.search);

      const firstTouch = {
        referrer: document.referrer || null,
        landing_page: window.location.pathname,
        landing_url: window.location.href,
        utm: {
          source: params.get('utm_source'),
          medium: params.get('utm_medium'),
          campaign: params.get('utm_campaign'),
          content: params.get('utm_content'),
          term: params.get('utm_term')
        },
        click_ids: {
          gclid: params.get('gclid'),
          gbraid: params.get('gbraid'),
          wbraid: params.get('wbraid'),
          fbclid: params.get('fbclid'),
          fbp: this._getCookie('_fbp'),
          fbc: this._getCookie('_fbc'),
          ttclid: params.get('ttclid'),
          li_fat_id: params.get('li_fat_id'),
          msclkid: params.get('msclkid')
        },
        captured_at: new Date().toISOString()
      };

      // Clean up null values in nested objects
      Object.keys(firstTouch.utm).forEach(k => {
        if (firstTouch.utm[k] === null) delete firstTouch.utm[k];
      });
      Object.keys(firstTouch.click_ids).forEach(k => {
        if (firstTouch.click_ids[k] === null) delete firstTouch.click_ids[k];
      });

      storageSet('first_touch', JSON.stringify(firstTouch));
      this._log('First touch captured', firstTouch);
    }

    /**
     * Get first touch data
     */
    getFirstTouch() {
      return safeJsonParse(storageGet('first_touch'), {});
    }

    // =========================================================================
    // CROSS-DOMAIN IDENTITY
    // =========================================================================

    /**
     * Check for identity hint in URL (for cross-domain tracking)
     */
    _checkIdentityHint() {
      const params = new URLSearchParams(window.location.search);
      const hintVid = params.get('_txvid');

      if (hintVid && this._isValidUUID(hintVid) && hintVid !== this.visitorId) {
        // Store as linked visitor (for server-side resolution)
        const linkedVisitors = safeJsonParse(storageGet('linked_vids'), []);
        if (!linkedVisitors.includes(hintVid)) {
          linkedVisitors.push(hintVid);
          storageSet('linked_vids', JSON.stringify(linkedVisitors.slice(-10))); // Keep last 10
        }
      }
    }

    /**
     * Get URL with visitor ID for cross-domain links
     */
    getLinkedUrl(url) {
      try {
        const urlObj = new URL(url, window.location.origin);
        urlObj.searchParams.set('_txvid', this.visitorId);
        return urlObj.toString();
      } catch (e) {
        return url;
      }
    }

    /**
     * Decorate all external links with visitor ID
     */
    decorateLinks(selector, domains) {
      if (!domains || !Array.isArray(domains)) return;

      document.querySelectorAll(selector || 'a[href]').forEach(link => {
        try {
          const href = link.getAttribute('href');
          if (!href) return;

          const url = new URL(href, window.location.origin);

          // Check if it's one of our domains
          if (domains.some(d => url.hostname.includes(d))) {
            link.href = this.getLinkedUrl(href);
          }
        } catch (e) {
          // Invalid URL, skip
        }
      });
    }

    // =========================================================================
    // CONSENT MANAGEMENT
    // =========================================================================

    /**
     * Get current consent snapshot
     */
    _getConsentSnapshot() {
      // Try to get from our storage first
      const stored = safeJsonParse(storageGet('consent'), null);
      if (stored) {
        return stored;
      }

      // Try to integrate with common consent managers
      // CookieYes
      if (window.getCkyConsent) {
        try {
          const cky = window.getCkyConsent();
          return {
            analytics: cky.categories?.analytics === 'yes',
            marketing: cky.categories?.advertisement === 'yes',
            data_processing: cky.categories?.functional === 'yes',
            captured_at: new Date().toISOString()
          };
        } catch (e) {}
      }

      // OneTrust
      if (window.OnetrustActiveGroups) {
        try {
          const groups = window.OnetrustActiveGroups;
          return {
            analytics: groups.includes('C0002'),
            marketing: groups.includes('C0004'),
            data_processing: groups.includes('C0003'),
            captured_at: new Date().toISOString()
          };
        } catch (e) {}
      }

      // Default: necessary only (most restrictive)
      return {
        analytics: false,
        marketing: false,
        data_processing: false,
        captured_at: null
      };
    }

    /**
     * Update consent (call this when user updates preferences)
     */
    setConsent(consent) {
      this.consentSnapshot = {
        ...this.consentSnapshot,
        ...consent,
        captured_at: new Date().toISOString()
      };
      storageSet('consent', JSON.stringify(this.consentSnapshot));

      this._log('Consent updated', this.consentSnapshot);

      // Track consent change
      this.track('consent_updated', {
        analytics: this.consentSnapshot.analytics,
        marketing: this.consentSnapshot.marketing,
        data_processing: this.consentSnapshot.data_processing
      });
    }

    /**
     * Check if we have specific consent
     */
    hasConsent(scope) {
      return this.consentSnapshot[scope] === true;
    }

    // =========================================================================
    // ENGAGEMENT TRACKING
    // =========================================================================

    /**
     * Initialize engagement tracking
     */
    _initEngagementTracking() {
      // Visibility change
      document.addEventListener('visibilitychange', () => {
        this.engagementData.visibilityChanges++;

        if (document.hidden) {
          this._updateActiveTime();
        } else {
          this.engagementData.lastActiveCheck = Date.now();
        }
        this.engagementData.isVisible = !document.hidden;
      });

      // Focus/blur
      window.addEventListener('focus', () => {
        this.engagementData.focusChanges++;
        this.engagementData.isFocused = true;
        this.engagementData.lastActiveCheck = Date.now();
      });

      window.addEventListener('blur', () => {
        this.engagementData.focusChanges++;
        this._updateActiveTime();
        this.engagementData.isFocused = false;
      });

      // Scroll tracking
      let scrollTimeout;
      window.addEventListener('scroll', () => {
        clearTimeout(scrollTimeout);
        scrollTimeout = setTimeout(() => {
          const scrollPct = this._getScrollPercentage();
          this.engagementData.maxScrollPct = Math.max(
            this.engagementData.maxScrollPct,
            scrollPct
          );
        }, 100);
      }, { passive: true });

      // Interaction tracking (clicks, keypresses)
      ['click', 'keydown', 'touchstart'].forEach(eventType => {
        document.addEventListener(eventType, () => {
          this.engagementData.interactions++;
        }, { passive: true });
      });

      // Send engagement on page exit
      window.addEventListener('pagehide', () => this._sendEngagement());
      window.addEventListener('beforeunload', () => this._sendEngagement());

      // Periodic engagement update (every 30 seconds)
      setInterval(() => {
        if (this.engagementData.isVisible && this.engagementData.isFocused) {
          this._updateActiveTime();
          this.engagementData.lastActiveCheck = Date.now();
        }
      }, 30000);
    }

    /**
     * Update active time counter
     */
    _updateActiveTime() {
      if (this.engagementData.isVisible && this.engagementData.isFocused) {
        const now = Date.now();
        this.engagementData.activeTime += now - this.engagementData.lastActiveCheck;
        this.engagementData.lastActiveCheck = now;
      }
    }

    /**
     * Get current scroll percentage
     */
    _getScrollPercentage() {
      const scrollHeight = document.documentElement.scrollHeight - window.innerHeight;
      if (scrollHeight <= 0) return 100;
      return Math.min(100, Math.round((window.scrollY / scrollHeight) * 100));
    }

    /**
     * Send engagement event
     */
    _sendEngagement() {
      this._updateActiveTime();

      this.track('page_engagement', {
        active_ms: Math.round(this.engagementData.activeTime),
        total_ms: Date.now() - this.engagementData.startTime,
        scroll_max_pct: this.engagementData.maxScrollPct,
        visibility_changes: this.engagementData.visibilityChanges,
        focus_changes: this.engagementData.focusChanges,
        interactions: this.engagementData.interactions
      }, { sendImmediately: true });
    }

    // =========================================================================
    // CORE TRACKING API
    // =========================================================================

    /**
     * Track a custom event
     */
    track(eventName, payload = {}, options = {}) {
      if (this.disabled) return null;

      const { entities = {}, sendImmediately = false } = options;

      // Check consent for non-necessary events
      const scope = this._getEventScope(eventName);
      if (scope === 'analytics' && !this.hasConsent('analytics')) {
        this._log('Blocked by consent (analytics):', eventName);
        return null;
      }
      if (scope === 'marketing' && !this.hasConsent('marketing')) {
        this._log('Blocked by consent (marketing):', eventName);
        return null;
      }

      const event = this._buildEnvelope(eventName, payload, entities);

      if (sendImmediately) {
        this._sendEvents([event]);
      } else {
        this.eventQueue.push(event);

        // Flush if queue is getting full
        if (this.eventQueue.length >= MAX_QUEUE_SIZE) {
          this.flush();
        }
      }

      this._updateSessionActivity();
      this._log('Event tracked:', eventName, payload);

      return event.event_id;
    }

    /**
     * Build event envelope
     */
    _buildEnvelope(eventName, payload, entities) {
      const firstTouch = this.getFirstTouch();
      const params = new URLSearchParams(window.location.search);

      return {
        event_id: generateUUID(),
        event_name: eventName,
        event_version: 1,
        occurred_at: new Date().toISOString(),
        tenant_id: this.tenantId,
        site_id: this.siteId,
        source_system: 'web',
        visitor_id: this.visitorId,
        session_id: this.sessionId,
        sequence_no: this._getNextSequenceNo(),
        consent_snapshot: this.consentSnapshot,
        context: {
          page: {
            url: window.location.href,
            path: window.location.pathname,
            title: document.title,
            page_type: this._detectPageType()
          },
          referrer: document.referrer || null,
          utm: {
            source: params.get('utm_source') || firstTouch.utm?.source || null,
            medium: params.get('utm_medium') || firstTouch.utm?.medium || null,
            campaign: params.get('utm_campaign') || firstTouch.utm?.campaign || null,
            content: params.get('utm_content') || firstTouch.utm?.content || null,
            term: params.get('utm_term') || firstTouch.utm?.term || null
          },
          click_ids: {
            gclid: params.get('gclid') || firstTouch.click_ids?.gclid || null,
            fbclid: params.get('fbclid') || firstTouch.click_ids?.fbclid || null,
            ttclid: params.get('ttclid') || firstTouch.click_ids?.ttclid || null
          },
          device: {
            device_type: this._getDeviceType(),
            screen_width: window.screen?.width,
            screen_height: window.screen?.height,
            viewport_width: window.innerWidth,
            viewport_height: window.innerHeight,
            pixel_ratio: window.devicePixelRatio || 1,
            language: navigator.language
          }
        },
        entities: entities,
        payload: payload,
        debug: this.debug ? { sdk_version: SDK_VERSION } : undefined
      };
    }

    // =========================================================================
    // CONVENIENCE TRACKING METHODS
    // =========================================================================

    /**
     * Track page view
     */
    pageView(pageType, additionalData = {}) {
      const isFirstTouch = !storageGet('has_visited');
      storageSet('has_visited', '1');

      return this.track('page_view', {
        page_type: pageType || this._detectPageType(),
        is_first_touch: isFirstTouch,
        navigation_type: this._getNavigationType(),
        ...additionalData
      });
    }

    /**
     * Track event view (ticket event page)
     */
    eventView(eventEntityId, data = {}) {
      return this.track('event_view', {
        price_from: data.priceFrom,
        currency: data.currency,
        availability_snapshot: data.availability
      }, {
        entities: { event_entity_id: String(eventEntityId) }
      });
    }

    /**
     * Track artist view
     */
    artistView(artistId, data = {}) {
      return this.track('artist_view', {
        artist_id: String(artistId),
        ...data
      }, {
        entities: { artist_ids: [String(artistId)] }
      });
    }

    /**
     * Track venue view
     */
    venueView(venueId, data = {}) {
      return this.track('venue_view', {
        venue_id: String(venueId),
        ...data
      }, {
        entities: { venue_id: String(venueId) }
      });
    }

    /**
     * Track ticket type selection
     */
    ticketTypeSelected(eventEntityId, ticketTypeId, data = {}) {
      return this.track('ticket_type_selected', {
        ticket_type_id: String(ticketTypeId),
        qty: data.qty || 1,
        unit_price: data.unitPrice,
        currency: data.currency
      }, {
        entities: {
          event_entity_id: String(eventEntityId),
          ticket_type_id: String(ticketTypeId)
        }
      });
    }

    /**
     * Track add to cart
     */
    addToCart(cartId, eventEntityId, items, data = {}) {
      return this.track('add_to_cart', {
        items: items,
        cart_value: data.cartValue,
        currency: data.currency
      }, {
        entities: {
          cart_id: String(cartId),
          event_entity_id: String(eventEntityId)
        }
      });
    }

    /**
     * Track remove from cart
     */
    removeFromCart(cartId, eventEntityId, ticketTypeId, data = {}) {
      return this.track('remove_from_cart', {
        ticket_type_id: String(ticketTypeId),
        qty_removed: data.qtyRemoved || 1,
        cart_value_after: data.cartValueAfter,
        currency: data.currency
      }, {
        entities: {
          cart_id: String(cartId),
          event_entity_id: String(eventEntityId)
        }
      });
    }

    /**
     * Track checkout started
     */
    checkoutStarted(cartId, data = {}) {
      return this.track('checkout_started', {
        cart_value: data.cartValue,
        currency: data.currency,
        login_state: data.isAuthenticated ? 'authenticated' : 'guest',
        item_count: data.itemCount
      }, {
        entities: { cart_id: String(cartId) }
      });
    }

    /**
     * Track checkout step completed
     */
    checkoutStepCompleted(cartId, stepName, data = {}) {
      return this.track('checkout_step_completed', {
        step_name: stepName,
        step_duration_ms: data.stepDurationMs,
        validation_errors: data.validationErrors || []
      }, {
        entities: { cart_id: String(cartId) }
      });
    }

    /**
     * Track payment attempted
     */
    paymentAttempted(cartId, data = {}) {
      return this.track('payment_attempted', {
        method: data.method,
        amount: data.amount,
        currency: data.currency
      }, {
        entities: { cart_id: String(cartId) }
      });
    }

    /**
     * Track search performed
     */
    searchPerformed(query, data = {}) {
      return this.track('search_performed', {
        query: query,
        filters: data.filters || {},
        results_count: data.resultsCount
      });
    }

    /**
     * Track filter changed
     */
    filterChanged(filterName, filterValue, allFilters = {}) {
      return this.track('filter_changed', {
        filter_name: filterName,
        filter_value: filterValue,
        all_filters: allFilters
      });
    }

    /**
     * Track promo code applied
     */
    promoCodeApplied(cartId, code, data = {}) {
      return this.track('promo_code_applied', {
        code: code,
        discount_amount: data.discountAmount,
        discount_type: data.discountType,
        currency: data.currency
      }, {
        entities: { cart_id: String(cartId) }
      });
    }

    /**
     * Track user identification (login/signup)
     */
    identify(data = {}) {
      // Store for future events if email provided
      if (data.email) {
        storageSet('identified_email', data.email);
      }

      return this.track('user_identified', {
        method: data.method || 'login', // login, signup, checkout
        has_email: !!data.email,
        has_phone: !!data.phone
      });
    }

    /**
     * Track waitlist signup
     */
    waitlistSignup(eventEntityId, data = {}) {
      return this.track('waitlist_signup', {
        ticket_type_id: data.ticketTypeId
      }, {
        entities: { event_entity_id: String(eventEntityId) }
      });
    }

    /**
     * Track share action
     */
    shareClicked(platform, eventEntityId = null) {
      return this.track('share_clicked', {
        platform: platform // facebook, twitter, whatsapp, copy_link, etc.
      }, {
        entities: eventEntityId ? { event_entity_id: String(eventEntityId) } : {}
      });
    }

    // =========================================================================
    // EVENT SENDING
    // =========================================================================

    /**
     * Flush event queue
     */
    async flush() {
      if (this.disabled || this.isFlushing || this.eventQueue.length === 0) {
        return;
      }

      this.isFlushing = true;
      const events = [...this.eventQueue];
      this.eventQueue = [];

      try {
        await this._sendEvents(events);
        this.retryCount = 0;
      } catch (error) {
        this._log('Flush failed:', error);
        // Put events back in queue for retry
        this.eventQueue = [...events, ...this.eventQueue].slice(0, MAX_QUEUE_SIZE);
        this.retryCount++;
      }

      this.isFlushing = false;
    }

    /**
     * Send events to API
     */
    async _sendEvents(events) {
      if (events.length === 0) return;

      const payload = { events: events };

      try {
        const response = await fetch(this.apiEndpoint, {
          method: 'POST',
          headers: {
            'Content-Type': 'application/json',
            'Accept': 'application/json'
          },
          body: JSON.stringify(payload),
          keepalive: true // Allow request to complete even if page unloads
        });

        if (!response.ok) {
          throw new Error(`HTTP ${response.status}`);
        }

        const result = await response.json();
        this._log('Events sent:', result);

      } catch (error) {
        this._log('Send failed:', error);
        throw error;
      }
    }

    /**
     * Start auto-flush interval
     */
    _startAutoFlush() {
      setInterval(() => {
        if (this.eventQueue.length > 0 && this.retryCount < MAX_RETRY_ATTEMPTS) {
          this.flush();
        }
      }, this.flushInterval);
    }

    // =========================================================================
    // UTILITY METHODS
    // =========================================================================

    /**
     * Detect page type from URL
     */
    _detectPageType() {
      const path = window.location.pathname.toLowerCase();

      if (path === '/' || path === '/home') return 'home';
      if (path.match(/^\/(events?|eveniment|evenimente)\/?$/)) return 'listing';
      if (path.match(/^\/(events?|eveniment|evenimente)\/[\w-]+/)) return 'event';
      if (path.match(/^\/(checkout|cos|cart)/)) return 'checkout';
      if (path.match(/^\/(artist|artisti)/)) return 'artist';
      if (path.match(/^\/(venue|locatie|locatii)/)) return 'venue';
      if (path.match(/^\/(shop|magazin|produs|product)/)) return 'shop';
      if (path.match(/^\/(account|cont|profil|profile)/)) return 'account';
      if (path.match(/^\/(search|cauta)/)) return 'search';

      return 'other';
    }

    /**
     * Get device type
     */
    _getDeviceType() {
      const ua = navigator.userAgent.toLowerCase();
      if (/tablet|ipad|playbook|silk/i.test(ua)) return 'tablet';
      if (/mobile|iphone|ipod|android|blackberry|mini|windows\sce|palm/i.test(ua)) return 'mobile';
      return 'desktop';
    }

    /**
     * Get navigation type
     */
    _getNavigationType() {
      try {
        const nav = performance.getEntriesByType('navigation')[0];
        return nav?.type || 'unknown';
      } catch (e) {
        return 'unknown';
      }
    }

    /**
     * Get event consent scope
     */
    _getEventScope(eventName) {
      const analyticsEvents = [
        'page_view', 'page_engagement', 'event_view', 'artist_view', 'venue_view',
        'add_to_cart', 'remove_from_cart', 'checkout_started', 'checkout_step_completed',
        'search_performed', 'filter_changed', 'ticket_type_selected'
      ];
      const marketingEvents = ['pixel_event_enqueued'];

      if (analyticsEvents.includes(eventName)) return 'analytics';
      if (marketingEvents.includes(eventName)) return 'marketing';
      return 'necessary';
    }

    /**
     * Check if Do Not Track is enabled
     */
    _isDoNotTrackEnabled() {
      return navigator.doNotTrack === '1' ||
             window.doNotTrack === '1' ||
             navigator.msDoNotTrack === '1';
    }

    /**
     * Validate UUID format
     */
    _isValidUUID(str) {
      return /^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i.test(str);
    }

    /**
     * Get cookie value
     */
    _getCookie(name) {
      const match = document.cookie.match(new RegExp('(^| )' + name + '=([^;]+)'));
      return match ? match[2] : null;
    }

    /**
     * Clean URL parameter without reload
     */
    _cleanUrlParam(param) {
      try {
        const url = new URL(window.location.href);
        url.searchParams.delete(param);
        window.history.replaceState({}, '', url.toString());
      } catch (e) {}
    }

    /**
     * Wait for DOM ready
     */
    _onDOMReady(callback) {
      if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', callback);
      } else {
        callback();
      }
    }

    /**
     * Debug logging
     */
    _log(...args) {
      if (this.debug) {
        console.log('[TX]', ...args);
      }
    }
  }

  // Export to window
  window.TxTracker = TxTracker;

  // Auto-initialize if config is present
  if (window.txConfig) {
    window.tx = new TxTracker(window.txConfig);
  }

})(window, document);
