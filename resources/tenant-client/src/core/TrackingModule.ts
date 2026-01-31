/**
 * Tixello Real-Time Tracking Module
 *
 * Collects visitor behavior, engagement metrics, and conversion events
 * Sends data to platform for real-time analytics and dual-tracking
 *
 * GDPR Compliant: Respects user cookie consent preferences
 */

import { TixelloConfig } from './ConfigManager';
import { EventBus } from './EventBus';
import { CookieConsentModule, ConsentPreferences } from '../modules/CookieConsentModule';

interface TrackingConfig {
    enabled: boolean;
    endpoint: string;
    tenantId: string;
    sessionTimeout: number;
    heartbeatInterval: number;
    batchSize: number;
    flushInterval: number;
}

interface UserData {
    visitorId: string;
    sessionId: string;
    email?: string;
    phone?: string;
    firstName?: string;
    lastName?: string;
}

interface EventData {
    eventType: string;
    eventCategory: string;
    timestamp: number;
    pageUrl: string;
    pageTitle: string;
    referrer: string;
    utmSource?: string;
    utmMedium?: string;
    utmCampaign?: string;
    utmTerm?: string;
    utmContent?: string;
    gclid?: string;
    fbclid?: string;
    ttclid?: string;
    liFatId?: string;
    value?: number;
    currency?: string;
    orderId?: string;
    eventData?: Record<string, any>;
    timeOnPage?: number;
    scrollDepth?: number;
}

interface DeviceInfo {
    deviceType: string;
    deviceBrand?: string;
    deviceModel?: string;
    browser: string;
    browserVersion: string;
    os: string;
    osVersion: string;
    screenWidth: number;
    screenHeight: number;
    viewportWidth: number;
    viewportHeight: number;
    isMobile: boolean;
    isTablet: boolean;
    isDesktop: boolean;
}

export class TrackingModule {
    private static instance: TrackingModule;
    private config: TrackingConfig;
    private userData: UserData;
    private deviceInfo: DeviceInfo;
    private eventQueue: EventData[] = [];
    private pageEntryTime: number = Date.now();
    private maxScrollDepth: number = 0;
    private heartbeatTimer?: number;
    private flushTimer?: number;
    private isInitialized: boolean = false;
    private consentModule: CookieConsentModule | null = null;
    private consentPreferences: ConsentPreferences = {
        necessary: true,
        analytics: false,
        marketing: false,
        preferences: false,
    };

    private constructor() {
        this.config = {
            enabled: true,
            endpoint: '',
            tenantId: '',
            sessionTimeout: 30 * 60 * 1000, // 30 minutes
            heartbeatInterval: 30 * 1000, // 30 seconds
            batchSize: 10,
            flushInterval: 5000, // 5 seconds
        };

        this.userData = {
            visitorId: this.getOrCreateVisitorId(),
            sessionId: this.getOrCreateSessionId(),
        };

        this.deviceInfo = this.collectDeviceInfo();
    }

    static getInstance(): TrackingModule {
        if (!TrackingModule.instance) {
            TrackingModule.instance = new TrackingModule();
        }
        return TrackingModule.instance;
    }

    /**
     * Initialize tracking with tenant config
     */
    init(tixelloConfig: TixelloConfig): void {
        if (this.isInitialized) return;

        // Extract base URL from apiEndpoint (remove /api/tenant-client path if present)
        const apiEndpoint = tixelloConfig.apiEndpoint || '';
        try {
            const url = new URL(apiEndpoint);
            this.config.endpoint = url.origin; // Just use the origin (e.g., https://core.tixello.com)
        } catch {
            this.config.endpoint = apiEndpoint.replace(/\/api\/tenant-client\/?$/, '');
        }
        // Convert tenantId to string - PHP validation requires string type
        this.config.tenantId = String(tixelloConfig.tenantId || '');
        this.config.enabled = tixelloConfig.tracking?.enabled !== false;

        if (!this.config.enabled) {
            console.log('Tixello Tracking: Disabled');
            return;
        }

        // Initialize cookie consent integration
        this.initConsentIntegration();

        // Extract URL parameters
        this.extractUrlParameters();

        // Track initial page view (only if analytics consent given)
        if (this.hasAnalyticsConsent()) {
            this.trackPageView();
        }

        // Setup event listeners
        this.setupEventListeners();

        // Start heartbeat
        this.startHeartbeat();

        // Start event flushing
        this.startFlushTimer();

        this.isInitialized = true;
        console.log('Tixello Tracking: Initialized (consent-aware)');
    }

    /**
     * Initialize integration with cookie consent module
     */
    private initConsentIntegration(): void {
        try {
            this.consentModule = CookieConsentModule.getInstance();
            this.consentPreferences = this.consentModule.getConsent();

            // Listen for consent changes
            window.addEventListener('tixello:consent', ((event: CustomEvent<ConsentPreferences>) => {
                this.consentPreferences = event.detail;
                console.log('Tixello Tracking: Consent updated', this.consentPreferences);

                // If analytics consent was just granted, send page view
                if (this.consentPreferences.analytics && !this.isInitialized) {
                    this.trackPageView();
                }
            }) as EventListener);
        } catch (error) {
            // Consent module not available, default to opted-out
            console.warn('Tixello Tracking: Cookie consent module not available, tracking disabled');
            this.consentPreferences = {
                necessary: true,
                analytics: false,
                marketing: false,
                preferences: false,
            };
        }
    }

    /**
     * Check if analytics consent is granted
     */
    hasAnalyticsConsent(): boolean {
        return this.consentPreferences.analytics === true;
    }

    /**
     * Check if marketing consent is granted
     */
    hasMarketingConsent(): boolean {
        return this.consentPreferences.marketing === true;
    }

    /**
     * Check if specific consent category is granted
     */
    hasConsent(category: keyof ConsentPreferences): boolean {
        return this.consentPreferences[category] === true;
    }

    /**
     * Track a page view
     */
    trackPageView(customData?: Record<string, any>): void {
        this.pageEntryTime = Date.now();
        this.maxScrollDepth = 0;

        this.queueEvent({
            eventType: 'page_view',
            eventCategory: 'navigation',
            timestamp: Date.now(),
            pageUrl: window.location.href,
            pageTitle: document.title,
            referrer: document.referrer,
            eventData: customData,
            ...this.getAttributionParams(),
        });
    }

    /**
     * Track add to cart
     */
    trackAddToCart(data: {
        eventId?: string;
        ticketTypeId?: string;
        ticketTypeName?: string;
        quantity?: number;
        price?: number;
        currency?: string;
    }): void {
        this.queueEvent({
            eventType: 'add_to_cart',
            eventCategory: 'ecommerce',
            timestamp: Date.now(),
            pageUrl: window.location.href,
            pageTitle: document.title,
            referrer: document.referrer,
            value: data.price,
            currency: data.currency || 'USD',
            eventData: data,
            ...this.getAttributionParams(),
        });

        this.flushEvents(); // Immediate flush for important events
    }

    /**
     * Track begin checkout
     */
    trackBeginCheckout(data: {
        cartTotal?: number;
        currency?: string;
        itemCount?: number;
        items?: Array<{id: string; name: string; quantity: number; price: number}>;
    }): void {
        this.queueEvent({
            eventType: 'begin_checkout',
            eventCategory: 'ecommerce',
            timestamp: Date.now(),
            pageUrl: window.location.href,
            pageTitle: document.title,
            referrer: document.referrer,
            value: data.cartTotal,
            currency: data.currency || 'USD',
            eventData: data,
            ...this.getAttributionParams(),
        });

        this.flushEvents();
    }

    /**
     * Track purchase completion
     */
    trackPurchase(data: {
        orderId: string;
        orderTotal: number;
        currency?: string;
        ticketCount?: number;
        eventId?: string;
        items?: Array<{id: string; name: string; quantity: number; price: number}>;
        email?: string;
        phone?: string;
        firstName?: string;
        lastName?: string;
    }): void {
        // Update user data if provided
        if (data.email) this.userData.email = data.email;
        if (data.phone) this.userData.phone = data.phone;
        if (data.firstName) this.userData.firstName = data.firstName;
        if (data.lastName) this.userData.lastName = data.lastName;

        this.queueEvent({
            eventType: 'purchase',
            eventCategory: 'ecommerce',
            timestamp: Date.now(),
            pageUrl: window.location.href,
            pageTitle: document.title,
            referrer: document.referrer,
            orderId: data.orderId,
            value: data.orderTotal,
            currency: data.currency || 'USD',
            eventData: {
                ticketCount: data.ticketCount,
                eventId: data.eventId,
                items: data.items,
            },
            ...this.getAttributionParams(),
        });

        this.flushEvents();
    }

    /**
     * Track user sign up
     */
    trackSignUp(data: {
        method?: string;
        email?: string;
        phone?: string;
        firstName?: string;
        lastName?: string;
    }): void {
        if (data.email) this.userData.email = data.email;
        if (data.phone) this.userData.phone = data.phone;
        if (data.firstName) this.userData.firstName = data.firstName;
        if (data.lastName) this.userData.lastName = data.lastName;

        this.queueEvent({
            eventType: 'sign_up',
            eventCategory: 'user',
            timestamp: Date.now(),
            pageUrl: window.location.href,
            pageTitle: document.title,
            referrer: document.referrer,
            eventData: { method: data.method },
            ...this.getAttributionParams(),
        });

        this.flushEvents();
    }

    /**
     * Track user login
     */
    trackLogin(data: {
        method?: string;
        userId?: string;
        email?: string;
    }): void {
        if (data.email) this.userData.email = data.email;

        this.queueEvent({
            eventType: 'login',
            eventCategory: 'user',
            timestamp: Date.now(),
            pageUrl: window.location.href,
            pageTitle: document.title,
            referrer: document.referrer,
            eventData: { method: data.method },
            ...this.getAttributionParams(),
        });
    }

    /**
     * Track event view (viewing event details page)
     */
    trackViewEvent(data: {
        eventId: string;
        eventName: string;
        eventDate?: string;
        venue?: string;
        lowestPrice?: number;
        currency?: string;
    }): void {
        this.queueEvent({
            eventType: 'view_item',
            eventCategory: 'engagement',
            timestamp: Date.now(),
            pageUrl: window.location.href,
            pageTitle: document.title,
            referrer: document.referrer,
            value: data.lowestPrice,
            currency: data.currency || 'USD',
            eventData: data,
            ...this.getAttributionParams(),
        });
    }

    /**
     * Track search
     */
    trackSearch(data: {
        searchTerm: string;
        resultsCount?: number;
        category?: string;
    }): void {
        this.queueEvent({
            eventType: 'search',
            eventCategory: 'engagement',
            timestamp: Date.now(),
            pageUrl: window.location.href,
            pageTitle: document.title,
            referrer: document.referrer,
            eventData: data,
            ...this.getAttributionParams(),
        });
    }

    /**
     * Track viewing event lineup
     */
    trackViewLineup(data: {
        eventId: string;
        eventName?: string;
    }): void {
        this.queueEvent({
            eventType: 'view_lineup',
            eventCategory: 'engagement',
            timestamp: Date.now(),
            pageUrl: window.location.href,
            pageTitle: document.title,
            referrer: document.referrer,
            eventData: {
                event_id: data.eventId,
                event_name: data.eventName,
            },
            ...this.getAttributionParams(),
        });
    }

    /**
     * Track viewing event pricing/tickets
     */
    trackViewPricing(data: {
        eventId: string;
        eventName?: string;
    }): void {
        this.queueEvent({
            eventType: 'view_pricing',
            eventCategory: 'engagement',
            timestamp: Date.now(),
            pageUrl: window.location.href,
            pageTitle: document.title,
            referrer: document.referrer,
            eventData: {
                event_id: data.eventId,
                event_name: data.eventName,
            },
            ...this.getAttributionParams(),
        });
    }

    /**
     * Track viewing event FAQ
     */
    trackViewFaq(data: {
        eventId: string;
        eventName?: string;
    }): void {
        this.queueEvent({
            eventType: 'view_faq',
            eventCategory: 'engagement',
            timestamp: Date.now(),
            pageUrl: window.location.href,
            pageTitle: document.title,
            referrer: document.referrer,
            eventData: {
                event_id: data.eventId,
                event_name: data.eventName,
            },
            ...this.getAttributionParams(),
        });
    }

    /**
     * Track selecting tickets (before add to cart)
     */
    trackSelectTickets(data: {
        eventId: string;
        eventName?: string;
        ticketTypeId: string;
        ticketTypeName: string;
        quantity: number;
        price: number;
        currency?: string;
    }): void {
        this.queueEvent({
            eventType: 'select_tickets',
            eventCategory: 'ecommerce',
            timestamp: Date.now(),
            pageUrl: window.location.href,
            pageTitle: document.title,
            referrer: document.referrer,
            value: data.price * data.quantity,
            currency: data.currency || 'RON',
            eventData: {
                event_id: data.eventId,
                event_name: data.eventName,
                ticket_type_id: data.ticketTypeId,
                ticket_type_name: data.ticketTypeName,
                quantity: data.quantity,
                unit_price: data.price,
            },
            ...this.getAttributionParams(),
        });
    }

    /**
     * Track viewing event gallery
     */
    trackViewGallery(data: {
        eventId: string;
        eventName?: string;
    }): void {
        this.queueEvent({
            eventType: 'view_gallery',
            eventCategory: 'engagement',
            timestamp: Date.now(),
            pageUrl: window.location.href,
            pageTitle: document.title,
            referrer: document.referrer,
            eventData: {
                event_id: data.eventId,
                event_name: data.eventName,
            },
            ...this.getAttributionParams(),
        });
    }

    /**
     * Track social share intent
     */
    trackShareEvent(data: {
        eventId: string;
        eventName?: string;
        platform: string; // facebook, twitter, whatsapp, email, copy_link
    }): void {
        this.queueEvent({
            eventType: 'share',
            eventCategory: 'engagement',
            timestamp: Date.now(),
            pageUrl: window.location.href,
            pageTitle: document.title,
            referrer: document.referrer,
            eventData: {
                event_id: data.eventId,
                event_name: data.eventName,
                share_platform: data.platform,
            },
            ...this.getAttributionParams(),
        });
    }

    /**
     * Track interest/wishlist/reminder signup
     */
    trackEventInterest(data: {
        eventId: string;
        eventName?: string;
        interestType: 'reminder' | 'wishlist' | 'notify';
    }): void {
        this.queueEvent({
            eventType: 'event_interest',
            eventCategory: 'engagement',
            timestamp: Date.now(),
            pageUrl: window.location.href,
            pageTitle: document.title,
            referrer: document.referrer,
            eventData: {
                event_id: data.eventId,
                event_name: data.eventName,
                interest_type: data.interestType,
            },
            ...this.getAttributionParams(),
        });
    }

    /**
     * Track custom event
     */
    trackCustomEvent(eventName: string, data?: Record<string, any>): void {
        this.queueEvent({
            eventType: eventName,
            eventCategory: 'custom',
            timestamp: Date.now(),
            pageUrl: window.location.href,
            pageTitle: document.title,
            referrer: document.referrer,
            eventData: data,
            ...this.getAttributionParams(),
        });
    }

    /**
     * Set user identity (call after login/signup)
     */
    setUserIdentity(data: {
        email?: string;
        phone?: string;
        firstName?: string;
        lastName?: string;
        userId?: string;
    }): void {
        if (data.email) this.userData.email = data.email;
        if (data.phone) this.userData.phone = data.phone;
        if (data.firstName) this.userData.firstName = data.firstName;
        if (data.lastName) this.userData.lastName = data.lastName;

        // Send identify event
        this.queueEvent({
            eventType: 'identify',
            eventCategory: 'user',
            timestamp: Date.now(),
            pageUrl: window.location.href,
            pageTitle: document.title,
            referrer: document.referrer,
            eventData: { userId: data.userId },
            ...this.getAttributionParams(),
        });

        this.flushEvents();
    }

    // Private methods

    private getOrCreateVisitorId(): string {
        const storageKey = 'tixello_visitor_id';
        let visitorId = localStorage.getItem(storageKey);

        if (!visitorId) {
            visitorId = this.generateUUID();
            localStorage.setItem(storageKey, visitorId);
        }

        return visitorId;
    }

    private getOrCreateSessionId(): string {
        const storageKey = 'tixello_session';
        const stored = sessionStorage.getItem(storageKey);

        if (stored) {
            const session = JSON.parse(stored);
            const elapsed = Date.now() - session.lastActivity;

            if (elapsed < this.config.sessionTimeout) {
                session.lastActivity = Date.now();
                sessionStorage.setItem(storageKey, JSON.stringify(session));
                return session.id;
            }
        }

        // Create new session
        const sessionId = this.generateUUID();
        sessionStorage.setItem(storageKey, JSON.stringify({
            id: sessionId,
            startTime: Date.now(),
            lastActivity: Date.now(),
        }));

        return sessionId;
    }

    private generateUUID(): string {
        return 'xxxxxxxx-xxxx-4xxx-yxxx-xxxxxxxxxxxx'.replace(/[xy]/g, (c) => {
            const r = Math.random() * 16 | 0;
            const v = c === 'x' ? r : (r & 0x3 | 0x8);
            return v.toString(16);
        });
    }

    private collectDeviceInfo(): DeviceInfo {
        const ua = navigator.userAgent;
        const isMobile = /Mobile|Android|iPhone|iPad|iPod/i.test(ua);
        const isTablet = /Tablet|iPad/i.test(ua);

        // Simple browser detection
        let browser = 'Unknown';
        let browserVersion = '';
        if (ua.includes('Chrome')) {
            browser = 'Chrome';
            browserVersion = ua.match(/Chrome\/(\d+)/)?.[1] || '';
        } else if (ua.includes('Firefox')) {
            browser = 'Firefox';
            browserVersion = ua.match(/Firefox\/(\d+)/)?.[1] || '';
        } else if (ua.includes('Safari')) {
            browser = 'Safari';
            browserVersion = ua.match(/Version\/(\d+)/)?.[1] || '';
        } else if (ua.includes('Edge')) {
            browser = 'Edge';
            browserVersion = ua.match(/Edge\/(\d+)/)?.[1] || '';
        }

        // OS detection
        let os = 'Unknown';
        let osVersion = '';
        if (ua.includes('Windows')) {
            os = 'Windows';
            osVersion = ua.match(/Windows NT (\d+\.\d+)/)?.[1] || '';
        } else if (ua.includes('Mac OS')) {
            os = 'macOS';
            osVersion = ua.match(/Mac OS X (\d+[_\.]\d+)/)?.[1]?.replace('_', '.') || '';
        } else if (ua.includes('Linux')) {
            os = 'Linux';
        } else if (ua.includes('Android')) {
            os = 'Android';
            osVersion = ua.match(/Android (\d+\.?\d*)/)?.[1] || '';
        } else if (ua.includes('iOS') || ua.includes('iPhone') || ua.includes('iPad')) {
            os = 'iOS';
            osVersion = ua.match(/OS (\d+[_\.]\d+)/)?.[1]?.replace('_', '.') || '';
        }

        return {
            deviceType: isTablet ? 'tablet' : (isMobile ? 'mobile' : 'desktop'),
            browser,
            browserVersion,
            os,
            osVersion,
            screenWidth: window.screen.width,
            screenHeight: window.screen.height,
            viewportWidth: window.innerWidth,
            viewportHeight: window.innerHeight,
            isMobile,
            isTablet,
            isDesktop: !isMobile && !isTablet,
        };
    }

    private extractUrlParameters(): void {
        const params = new URLSearchParams(window.location.search);

        // Store click IDs for attribution
        const gclid = params.get('gclid');
        const fbclid = params.get('fbclid');
        const ttclid = params.get('ttclid');
        const liFatId = params.get('li_fat_id');

        if (gclid) localStorage.setItem('tixello_gclid', gclid);
        if (fbclid) localStorage.setItem('tixello_fbclid', fbclid);
        if (ttclid) localStorage.setItem('tixello_ttclid', ttclid);
        if (liFatId) localStorage.setItem('tixello_li_fat_id', liFatId);

        // Store UTM parameters
        const utmParams = ['utm_source', 'utm_medium', 'utm_campaign', 'utm_term', 'utm_content'];
        utmParams.forEach(param => {
            const value = params.get(param);
            if (value) localStorage.setItem(`tixello_${param}`, value);
        });
    }

    private getAttributionParams(): Partial<EventData> {
        return {
            utmSource: localStorage.getItem('tixello_utm_source') || undefined,
            utmMedium: localStorage.getItem('tixello_utm_medium') || undefined,
            utmCampaign: localStorage.getItem('tixello_utm_campaign') || undefined,
            utmTerm: localStorage.getItem('tixello_utm_term') || undefined,
            utmContent: localStorage.getItem('tixello_utm_content') || undefined,
            gclid: localStorage.getItem('tixello_gclid') || undefined,
            fbclid: localStorage.getItem('tixello_fbclid') || undefined,
            ttclid: localStorage.getItem('tixello_ttclid') || undefined,
            liFatId: localStorage.getItem('tixello_li_fat_id') || undefined,
        };
    }

    private setupEventListeners(): void {
        // Track scroll depth
        let scrollTimeout: number;
        window.addEventListener('scroll', () => {
            clearTimeout(scrollTimeout);
            scrollTimeout = window.setTimeout(() => {
                const scrollTop = window.scrollY;
                const docHeight = document.documentElement.scrollHeight - window.innerHeight;
                const scrollPercent = Math.round((scrollTop / docHeight) * 100);
                this.maxScrollDepth = Math.max(this.maxScrollDepth, scrollPercent);
            }, 100);
        });

        // Track page unload (send engagement data and end session)
        window.addEventListener('beforeunload', () => {
            this.trackEngagement();
            this.trackSessionEnd();
            this.flushEvents(true);
        });

        // Track visibility changes
        document.addEventListener('visibilitychange', () => {
            if (document.hidden) {
                this.trackEngagement();
                this.flushEvents();
            }
        });

        // Track clicks on outbound links
        document.addEventListener('click', (e) => {
            const target = e.target as HTMLElement;
            const link = target.closest('a');

            if (link && link.hostname !== window.location.hostname) {
                this.queueEvent({
                    eventType: 'outbound_click',
                    eventCategory: 'engagement',
                    timestamp: Date.now(),
                    pageUrl: window.location.href,
                    pageTitle: document.title,
                    referrer: document.referrer,
                    eventData: {
                        targetUrl: link.href,
                        linkText: link.textContent?.substring(0, 100),
                    },
                    ...this.getAttributionParams(),
                });
            }
        });

        // Track history changes (SPA navigation)
        const originalPushState = history.pushState;
        history.pushState = (...args) => {
            originalPushState.apply(history, args);
            this.handleNavigation();
        };

        window.addEventListener('popstate', () => {
            this.handleNavigation();
        });
    }

    private handleNavigation(): void {
        // Track engagement for previous page
        this.trackEngagement();

        // Track new page view
        setTimeout(() => {
            this.trackPageView();
        }, 100);
    }

    private trackEngagement(): void {
        const timeOnPage = Math.round((Date.now() - this.pageEntryTime) / 1000);

        if (timeOnPage > 0) {
            this.queueEvent({
                eventType: 'engagement',
                eventCategory: 'engagement',
                timestamp: Date.now(),
                pageUrl: window.location.href,
                pageTitle: document.title,
                referrer: document.referrer,
                timeOnPage,
                scrollDepth: this.maxScrollDepth,
                ...this.getAttributionParams(),
            });
        }
    }

    /**
     * Track session end (called on page unload)
     */
    private trackSessionEnd(): void {
        // Get session info
        const storageKey = 'tixello_session';
        const stored = sessionStorage.getItem(storageKey);

        if (!stored) return;

        const session = JSON.parse(stored);
        const sessionDuration = Math.round((Date.now() - session.startTime) / 1000);

        this.queueEvent({
            eventType: 'session_end',
            eventCategory: 'session',
            timestamp: Date.now(),
            pageUrl: window.location.href,
            pageTitle: document.title,
            referrer: document.referrer,
            eventData: {
                sessionDuration,
                sessionStartTime: session.startTime,
            },
            ...this.getAttributionParams(),
        });
    }

    private startHeartbeat(): void {
        this.heartbeatTimer = window.setInterval(() => {
            // Update session activity
            const storageKey = 'tixello_session';
            const stored = sessionStorage.getItem(storageKey);
            if (stored) {
                const session = JSON.parse(stored);
                session.lastActivity = Date.now();
                sessionStorage.setItem(storageKey, JSON.stringify(session));
            }
        }, this.config.heartbeatInterval);
    }

    private startFlushTimer(): void {
        this.flushTimer = window.setInterval(() => {
            if (this.eventQueue.length > 0) {
                this.flushEvents();
            }
        }, this.config.flushInterval);
    }

    private queueEvent(event: EventData): void {
        // Check consent before queuing events
        // Essential events (necessary category) are always allowed
        // Analytics events require analytics consent
        // Marketing-related events require marketing consent

        const essentialEvents = ['error', 'session_start'];
        const marketingEvents = ['outbound_click'];

        // Allow essential events without consent check
        if (!essentialEvents.includes(event.eventType)) {
            // Marketing events require marketing consent
            if (marketingEvents.includes(event.eventType) && !this.hasMarketingConsent()) {
                return; // Skip - no marketing consent
            }

            // All other events require analytics consent
            if (!marketingEvents.includes(event.eventType) && !this.hasAnalyticsConsent()) {
                return; // Skip - no analytics consent
            }
        }

        this.eventQueue.push(event);

        // Auto-flush if queue is full
        if (this.eventQueue.length >= this.config.batchSize) {
            this.flushEvents();
        }
    }

    private flushEvents(sync: boolean = false): void {
        if (this.eventQueue.length === 0 || !this.config.endpoint) return;

        const events = [...this.eventQueue];
        this.eventQueue = [];

        const payload = {
            tenantId: this.config.tenantId,
            userData: this.userData,
            deviceInfo: this.deviceInfo,
            events,
            timestamp: Date.now(),
            // Include consent status for GDPR compliance
            consent: {
                analytics: this.consentPreferences.analytics,
                marketing: this.consentPreferences.marketing,
                preferences: this.consentPreferences.preferences,
            },
        };

        if (sync && navigator.sendBeacon) {
            // Use sendBeacon for page unload - use Blob for proper JSON content-type
            const blob = new Blob([JSON.stringify(payload)], { type: 'application/json' });
            navigator.sendBeacon(
                `${this.config.endpoint}/api/tracking/events`,
                blob
            );
        } else {
            // Use fetch for normal sends with proper CORS mode
            fetch(`${this.config.endpoint}/api/tracking/events`, {
                method: 'POST',
                mode: 'cors',
                credentials: 'omit',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                },
                body: JSON.stringify(payload),
            }).catch((error) => {
                // Re-queue events on failure
                this.eventQueue = [...events, ...this.eventQueue];
                console.warn('Tixello Tracking: Failed to send events', error);
            });
        }
    }

    /**
     * Cleanup on destroy
     */
    destroy(): void {
        if (this.heartbeatTimer) clearInterval(this.heartbeatTimer);
        if (this.flushTimer) clearInterval(this.flushTimer);
        this.flushEvents(true);
    }
}

// Export singleton
export const Tracking = TrackingModule.getInstance();
