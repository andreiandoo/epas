/**
 * Cookie Consent Module
 *
 * GDPR-compliant cookie consent management for tenant websites.
 *
 * Features:
 * - Multi-language support (English, Romanian)
 * - Google Consent Mode v2 integration
 * - Tenant-configurable settings
 * - Full GDPR compliance with audit trail
 * - Accessibility (WCAG 2.1 AA compliant)
 * - Dark mode support
 * - Third-party script manager
 * - Footer settings link
 */

import { ApiClient } from '../core/ApiClient';
import { EventBus } from '../core/EventBus';
import { TixelloConfig } from '../core/ConfigManager';

export interface ConsentPreferences {
    necessary: boolean;  // Always true, cannot be disabled
    analytics: boolean;
    marketing: boolean;
    preferences: boolean;
}

export interface ConsentConfig {
    position?: 'bottom' | 'top' | 'center';
    theme?: 'light' | 'dark' | 'auto';
    language?: 'en' | 'ro' | 'auto';
    showSettingsLink?: boolean;
    privacyPolicyUrl?: string;
    cookiePolicyUrl?: string;
    consentVersion?: string;
    expiryDays?: number;
    // Tenant customization
    bannerTitle?: string;
    bannerDescription?: string;
    primaryColor?: string;
    // Google Consent Mode
    enableGoogleConsentMode?: boolean;
    // Footer link
    showFooterLink?: boolean;
    footerLinkText?: string;
}

export interface ThirdPartyScript {
    id: string;
    src?: string;
    inline?: string;
    category: 'analytics' | 'marketing' | 'preferences';
    async?: boolean;
    defer?: boolean;
    onLoad?: () => void;
}

interface StoredConsent {
    preferences: ConsentPreferences;
    consentedAt: string;
    version: string;
    visitorId: string;
}

interface Translations {
    title: string;
    description: string;
    acceptAll: string;
    rejectAll: string;
    customize: string;
    savePreferences: string;
    cookiePreferences: string;
    necessaryCookies: string;
    necessaryDescription: string;
    analyticsCookies: string;
    analyticsDescription: string;
    marketingCookies: string;
    marketingDescription: string;
    preferenceCookies: string;
    preferenceDescription: string;
    privacyPolicy: string;
    cookiePolicy: string;
    cookieSettings: string;
    close: string;
    // Renewal
    renewalTitle: string;
    renewalDescription: string;
    renewalUrgentDescription: string;
    renewNow: string;
    dismiss: string;
}

// Translation dictionaries
const translations: Record<string, Translations> = {
    en: {
        title: 'We use cookies',
        description: 'We use cookies to improve your experience on our site. By clicking "Accept All", you consent to the use of all cookies. You can also customize your preferences.',
        acceptAll: 'Accept All',
        rejectAll: 'Reject All',
        customize: 'Customize',
        savePreferences: 'Save Preferences',
        cookiePreferences: 'Cookie Preferences',
        necessaryCookies: 'Necessary Cookies',
        necessaryDescription: 'Required for the website to function. Cannot be disabled.',
        analyticsCookies: 'Analytics Cookies',
        analyticsDescription: 'Help us understand how visitors interact with our website.',
        marketingCookies: 'Marketing Cookies',
        marketingDescription: 'Used to deliver relevant advertisements and track ad performance.',
        preferenceCookies: 'Preference Cookies',
        preferenceDescription: 'Remember your settings and preferences for a better experience.',
        privacyPolicy: 'Privacy Policy',
        cookiePolicy: 'Cookie Policy',
        cookieSettings: 'Cookie Settings',
        close: 'Close',
        renewalTitle: 'Your cookie preferences are expiring',
        renewalDescription: 'Your cookie consent will expire soon. Please renew your preferences to continue enjoying a personalized experience.',
        renewalUrgentDescription: 'Your cookie consent expires in a few days. Renew now to keep your preferences.',
        renewNow: 'Renew Now',
        dismiss: 'Dismiss',
    },
    ro: {
        title: 'Folosim cookie-uri',
        description: 'Folosim cookie-uri pentru a imbunatatii experienta dumneavoastra pe site-ul nostru. Apasand "Accepta toate", consimtiti la utilizarea tuturor cookie-urilor. Puteti personaliza preferintele.',
        acceptAll: 'Accepta toate',
        rejectAll: 'Refuza toate',
        customize: 'Personalizeaza',
        savePreferences: 'Salveaza preferintele',
        cookiePreferences: 'Preferinte cookie-uri',
        necessaryCookies: 'Cookie-uri necesare',
        necessaryDescription: 'Necesare pentru functionarea site-ului. Nu pot fi dezactivate.',
        analyticsCookies: 'Cookie-uri de analiza',
        analyticsDescription: 'Ne ajuta sa intelegem cum interactioneaza vizitatorii cu site-ul nostru.',
        marketingCookies: 'Cookie-uri de marketing',
        marketingDescription: 'Utilizate pentru a livra reclame relevante si a urmari performanta.',
        preferenceCookies: 'Cookie-uri de preferinte',
        preferenceDescription: 'Retin setarile si preferintele pentru o experienta mai buna.',
        privacyPolicy: 'Politica de confidentialitate',
        cookiePolicy: 'Politica de cookie-uri',
        cookieSettings: 'Setari cookie-uri',
        close: 'Inchide',
        renewalTitle: 'Preferintele tale de cookie-uri expira',
        renewalDescription: 'Consimtamantul tau pentru cookie-uri va expira in curand. Te rugam sa reinnoiesti preferintele pentru a continua sa te bucuri de o experienta personalizata.',
        renewalUrgentDescription: 'Consimtamantul tau pentru cookie-uri expira in cateva zile. Reinnoieste acum pentru a pastra preferintele.',
        renewNow: 'Reinnoieste acum',
        dismiss: 'Inchide',
    },
};

const STORAGE_KEY = 'tixello_cookie_consent';
const VISITOR_ID_KEY = 'tixello_visitor_id';
const CONSENT_VERSION = '1.0';

// Declare gtag for TypeScript
declare global {
    interface Window {
        gtag?: (...args: any[]) => void;
        dataLayer?: any[];
    }
}

export class CookieConsentModule {
    name = 'cookie-consent';
    private static instance: CookieConsentModule;
    private apiClient: ApiClient | null = null;
    private eventBus: EventBus | null = null;
    private config: ConsentConfig = {
        position: 'bottom',
        theme: 'auto',
        language: 'auto',
        showSettingsLink: true,
        consentVersion: CONSENT_VERSION,
        expiryDays: 365,
        enableGoogleConsentMode: true,
        showFooterLink: true,
    };
    private consent: ConsentPreferences = {
        necessary: true,
        analytics: false,
        marketing: false,
        preferences: false,
    };
    private visitorId: string = '';
    private bannerElement: HTMLElement | null = null;
    private settingsModalElement: HTMLElement | null = null;
    private footerLinkElement: HTMLElement | null = null;
    private renewalBannerElement: HTMLElement | null = null;
    private isInitialized: boolean = false;
    private currentLanguage: string = 'en';
    private t: Translations = translations.en;
    private isDarkMode: boolean = false;
    private pendingScripts: ThirdPartyScript[] = [];
    private loadedScripts: Set<string> = new Set();
    private previousActiveElement: Element | null = null;
    private renewalDismissed: boolean = false;

    private constructor() {}

    static getInstance(): CookieConsentModule {
        if (!CookieConsentModule.instance) {
            CookieConsentModule.instance = new CookieConsentModule();
        }
        return CookieConsentModule.instance;
    }

    /**
     * Configure the consent module with tenant settings
     */
    configure(config: Partial<ConsentConfig>): void {
        this.config = { ...this.config, ...config };
        this.detectLanguage();
        this.detectTheme();

        // If already initialized, update the UI
        if (this.bannerElement) {
            this.bannerElement.innerHTML = this.getBannerHTML();
            this.applyThemeStyles();
            this.bindBannerEvents();
        }
    }

    async init(apiClient: ApiClient, eventBus: EventBus, tixelloConfig?: TixelloConfig): Promise<void> {
        if (this.isInitialized) return;

        this.apiClient = apiClient;
        this.eventBus = eventBus;
        this.visitorId = this.getOrCreateVisitorId();

        // Apply config from TixelloConfig if available
        if (tixelloConfig?.site?.language) {
            this.config.language = tixelloConfig.site.language as 'en' | 'ro' | 'auto';
        }
        if (tixelloConfig?.theme?.primaryColor) {
            this.config.primaryColor = tixelloConfig.theme.primaryColor;
        }

        // Detect language and theme
        this.detectLanguage();
        this.detectTheme();

        // Listen for system theme changes
        this.listenForThemeChanges();

        // Initialize Google Consent Mode with defaults (denied)
        this.initGoogleConsentMode();

        // Check for existing consent
        const storedConsent = this.getStoredConsent();

        if (storedConsent && storedConsent.version === this.config.consentVersion) {
            // Valid consent exists
            this.consent = storedConsent.preferences;
            this.emitConsentUpdate();
            this.updateGoogleConsentMode();
            this.loadPendingScripts();
        } else {
            // No consent or outdated version - show banner
            this.showBanner();
        }

        // Also sync with server if we have consent
        if (storedConsent) {
            await this.syncWithServer();
            // Check for renewal notifications
            this.checkRenewalStatus();
        }

        // Add footer link if configured
        if (this.config.showFooterLink) {
            this.addFooterLink();
        }

        this.isInitialized = true;
        console.log(`Cookie Consent module initialized (${this.currentLanguage}, ${this.isDarkMode ? 'dark' : 'light'} mode)`);
    }

    // ==================== Theme Management ====================

    /**
     * Detect and set the current theme
     */
    private detectTheme(): void {
        const theme = this.config.theme || 'auto';

        if (theme === 'auto') {
            this.isDarkMode = window.matchMedia?.('(prefers-color-scheme: dark)').matches || false;
        } else {
            this.isDarkMode = theme === 'dark';
        }
    }

    /**
     * Listen for system theme changes
     */
    private listenForThemeChanges(): void {
        if (this.config.theme !== 'auto') return;

        window.matchMedia?.('(prefers-color-scheme: dark)').addEventListener('change', (e) => {
            this.isDarkMode = e.matches;
            this.applyThemeStyles();
        });
    }

    /**
     * Apply theme styles to banner and modal
     */
    private applyThemeStyles(): void {
        if (this.bannerElement) {
            this.bannerElement.classList.toggle('tixello-dark', this.isDarkMode);
        }
        if (this.settingsModalElement) {
            this.settingsModalElement.classList.toggle('tixello-dark', this.isDarkMode);
        }
    }

    /**
     * Set theme manually
     */
    setTheme(theme: 'light' | 'dark' | 'auto'): void {
        this.config.theme = theme;
        this.detectTheme();
        this.applyThemeStyles();
    }

    // ==================== Language Management ====================

    /**
     * Detect and set the current language
     */
    private detectLanguage(): void {
        let lang = this.config.language || 'auto';

        if (lang === 'auto') {
            const browserLang = navigator.language?.substring(0, 2) || 'en';
            const htmlLang = document.documentElement.lang?.substring(0, 2);
            lang = htmlLang || browserLang;
        }

        this.currentLanguage = translations[lang] ? lang : 'en';
        this.t = translations[this.currentLanguage];
    }

    /**
     * Change language dynamically
     */
    setLanguage(lang: 'en' | 'ro'): void {
        this.config.language = lang;
        this.detectLanguage();

        if (this.bannerElement) {
            this.bannerElement.innerHTML = this.getBannerHTML();
            this.bindBannerEvents();
        }
        if (this.settingsModalElement) {
            this.settingsModalElement.innerHTML = this.getSettingsHTML();
            this.bindSettingsEvents();
        }
        if (this.footerLinkElement) {
            this.footerLinkElement.textContent = this.config.footerLinkText || this.t.cookieSettings;
        }
    }

    // ==================== Third-Party Script Manager ====================

    /**
     * Register a third-party script to be loaded based on consent
     */
    registerScript(script: ThirdPartyScript): void {
        if (this.loadedScripts.has(script.id)) {
            return; // Already loaded
        }

        if (this.hasConsent(script.category)) {
            this.loadScript(script);
        } else {
            this.pendingScripts.push(script);
        }
    }

    /**
     * Load a script
     */
    private loadScript(script: ThirdPartyScript): void {
        if (this.loadedScripts.has(script.id)) return;

        if (script.src) {
            const el = document.createElement('script');
            el.id = `tixello-script-${script.id}`;
            el.src = script.src;
            if (script.async) el.async = true;
            if (script.defer) el.defer = true;
            el.onload = () => {
                this.loadedScripts.add(script.id);
                script.onLoad?.();
            };
            document.head.appendChild(el);
        } else if (script.inline) {
            const el = document.createElement('script');
            el.id = `tixello-script-${script.id}`;
            el.textContent = script.inline;
            document.head.appendChild(el);
            this.loadedScripts.add(script.id);
            script.onLoad?.();
        }

        console.log(`Cookie Consent: Loaded script "${script.id}" (${script.category})`);
    }

    /**
     * Load pending scripts based on consent
     */
    private loadPendingScripts(): void {
        const toLoad = this.pendingScripts.filter(script => this.hasConsent(script.category));
        toLoad.forEach(script => this.loadScript(script));
        this.pendingScripts = this.pendingScripts.filter(script => !this.hasConsent(script.category));
    }

    // ==================== Footer Link ====================

    /**
     * Add cookie settings link to footer
     */
    private addFooterLink(): void {
        // Check if footer link already exists
        if (document.getElementById('tixello-cookie-settings-link')) return;

        this.footerLinkElement = document.createElement('button');
        this.footerLinkElement.id = 'tixello-cookie-settings-link';
        this.footerLinkElement.className = 'tixello-footer-link';
        this.footerLinkElement.textContent = this.config.footerLinkText || this.t.cookieSettings;
        this.footerLinkElement.setAttribute('aria-label', this.t.cookieSettings);
        this.footerLinkElement.addEventListener('click', () => this.showSettings());

        // Try to find a footer element
        const footer = document.querySelector('footer') || document.querySelector('[role="contentinfo"]');
        if (footer) {
            footer.appendChild(this.footerLinkElement);
        } else {
            // Create a fixed bottom-left link
            this.footerLinkElement.classList.add('tixello-footer-link-fixed');
            document.body.appendChild(this.footerLinkElement);
        }
    }

    /**
     * Create a standalone footer link element
     */
    static createFooterLink(text?: string): HTMLElement {
        const instance = CookieConsentModule.getInstance();
        const link = document.createElement('button');
        link.className = 'tixello-footer-link';
        link.textContent = text || instance.t.cookieSettings;
        link.setAttribute('aria-label', instance.t.cookieSettings);
        link.addEventListener('click', () => instance.showSettings());
        return link;
    }

    // ==================== Renewal Notifications ====================

    /**
     * Check renewal status from server
     */
    private async checkRenewalStatus(): Promise<void> {
        if (!this.apiClient || this.renewalDismissed) return;

        try {
            const response = await this.apiClient.get(`/consent/renewal-status?visitor_id=${encodeURIComponent(this.visitorId)}`);
            if (response.success && response.data?.needs_renewal) {
                this.showRenewalBanner(response.data.is_urgent, response.data.days_until_expiry);
            }
        } catch (error) {
            console.warn('Failed to check consent renewal status:', error);
        }
    }

    /**
     * Show renewal banner
     */
    private showRenewalBanner(isUrgent: boolean, daysUntilExpiry: number): void {
        if (this.renewalBannerElement || this.renewalDismissed) return;

        this.renewalBannerElement = document.createElement('div');
        this.renewalBannerElement.id = 'tixello-renewal-banner';
        this.renewalBannerElement.setAttribute('role', 'alert');
        this.renewalBannerElement.setAttribute('aria-live', 'polite');
        this.renewalBannerElement.innerHTML = this.getRenewalBannerHTML(isUrgent, daysUntilExpiry);
        this.renewalBannerElement.style.cssText = this.getRenewalBannerStyles(isUrgent);

        if (this.isDarkMode) {
            this.renewalBannerElement.classList.add('tixello-dark');
        }

        document.body.appendChild(this.renewalBannerElement);
        this.bindRenewalBannerEvents();
    }

    /**
     * Get renewal banner HTML
     */
    private getRenewalBannerHTML(isUrgent: boolean, daysUntilExpiry: number): string {
        const description = isUrgent ? this.t.renewalUrgentDescription : this.t.renewalDescription;

        return `
            <div class="tixello-renewal-content ${isUrgent ? 'tixello-renewal-urgent' : ''}">
                <div class="tixello-renewal-text">
                    <p class="tixello-renewal-title">${this.t.renewalTitle}</p>
                    <p class="tixello-renewal-description">
                        ${description}
                        <span class="tixello-renewal-days">(${daysUntilExpiry} days remaining)</span>
                    </p>
                </div>
                <div class="tixello-renewal-actions">
                    <button class="tixello-consent-btn tixello-consent-btn-outline" id="tixello-renewal-dismiss">
                        ${this.t.dismiss}
                    </button>
                    <button class="tixello-consent-btn tixello-consent-btn-primary" id="tixello-renewal-renew">
                        ${this.t.renewNow}
                    </button>
                </div>
            </div>
        `;
    }

    /**
     * Get renewal banner styles
     */
    private getRenewalBannerStyles(isUrgent: boolean): string {
        return `
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            z-index: 99998;
            padding: 0.75rem 1rem;
            background: ${isUrgent ? '#fef3c7' : '#dbeafe'};
            border-bottom: 2px solid ${isUrgent ? '#f59e0b' : '#3b82f6'};
            font-family: var(--tixello-font, system-ui, sans-serif);
        `;
    }

    /**
     * Bind renewal banner events
     */
    private bindRenewalBannerEvents(): void {
        const dismissBtn = document.getElementById('tixello-renewal-dismiss');
        const renewBtn = document.getElementById('tixello-renewal-renew');

        dismissBtn?.addEventListener('click', () => this.dismissRenewalBanner());
        renewBtn?.addEventListener('click', () => this.renewConsent());
    }

    /**
     * Dismiss renewal banner
     */
    private dismissRenewalBanner(): void {
        if (this.renewalBannerElement) {
            this.renewalBannerElement.remove();
            this.renewalBannerElement = null;
        }
        this.renewalDismissed = true;
        // Store dismissal in session storage
        sessionStorage.setItem('tixello_renewal_dismissed', 'true');
    }

    /**
     * Renew consent (extend expiration)
     */
    async renewConsent(): Promise<void> {
        if (!this.apiClient) {
            // Just save locally with updated timestamp
            this.saveConsent('update');
            this.dismissRenewalBanner();
            return;
        }

        try {
            await this.apiClient.post('/consent/renew', {
                visitor_id: this.visitorId,
            });

            // Update local storage with new expiry
            this.saveConsent('update');
            this.dismissRenewalBanner();

            console.log('Cookie consent renewed successfully');
        } catch (error) {
            console.warn('Failed to renew consent:', error);
        }
    }

    // ==================== Google Consent Mode ====================

    private initGoogleConsentMode(): void {
        if (!this.config.enableGoogleConsentMode) return;

        window.dataLayer = window.dataLayer || [];

        if (!window.gtag) {
            window.gtag = function() {
                window.dataLayer!.push(arguments);
            };
        }

        window.gtag('consent', 'default', {
            'analytics_storage': 'denied',
            'ad_storage': 'denied',
            'ad_user_data': 'denied',
            'ad_personalization': 'denied',
            'functionality_storage': 'denied',
            'personalization_storage': 'denied',
            'security_storage': 'granted',
        });

        console.log('Google Consent Mode v2: Initialized with defaults');
    }

    private updateGoogleConsentMode(): void {
        if (!this.config.enableGoogleConsentMode || !window.gtag) return;

        window.gtag('consent', 'update', {
            'analytics_storage': this.consent.analytics ? 'granted' : 'denied',
            'ad_storage': this.consent.marketing ? 'granted' : 'denied',
            'ad_user_data': this.consent.marketing ? 'granted' : 'denied',
            'ad_personalization': this.consent.marketing ? 'granted' : 'denied',
            'functionality_storage': this.consent.preferences ? 'granted' : 'denied',
            'personalization_storage': this.consent.preferences ? 'granted' : 'denied',
        });

        console.log('Google Consent Mode v2: Updated', {
            analytics: this.consent.analytics,
            marketing: this.consent.marketing,
            preferences: this.consent.preferences,
        });
    }

    // ==================== Public API ====================

    getConsent(): ConsentPreferences {
        return { ...this.consent };
    }

    hasConsent(category: keyof ConsentPreferences): boolean {
        return this.consent[category] === true;
    }

    showSettings(): void {
        this.showSettingsModal();
    }

    async withdrawConsent(): Promise<void> {
        this.consent = {
            necessary: true,
            analytics: false,
            marketing: false,
            preferences: false,
        };

        this.saveConsent('reject_all');
        this.emitConsentUpdate();
        this.updateGoogleConsentMode();

        if (this.apiClient) {
            try {
                await this.apiClient.post('/consent/withdraw', {
                    visitor_id: this.visitorId,
                });
            } catch (error) {
                console.warn('Failed to sync consent withdrawal with server:', error);
            }
        }

        this.showBanner();
    }

    // ==================== Private Methods ====================

    private getOrCreateVisitorId(): string {
        let visitorId = localStorage.getItem(VISITOR_ID_KEY);
        if (!visitorId) {
            visitorId = this.generateUUID();
            localStorage.setItem(VISITOR_ID_KEY, visitorId);
        }
        return visitorId;
    }

    private generateUUID(): string {
        return 'xxxxxxxx-xxxx-4xxx-yxxx-xxxxxxxxxxxx'.replace(/[xy]/g, (c) => {
            const r = Math.random() * 16 | 0;
            const v = c === 'x' ? r : (r & 0x3 | 0x8);
            return v.toString(16);
        });
    }

    private getStoredConsent(): StoredConsent | null {
        try {
            const stored = localStorage.getItem(STORAGE_KEY);
            if (stored) {
                return JSON.parse(stored);
            }
        } catch {
            // Invalid stored consent
        }
        return null;
    }

    private saveConsent(action: 'accept_all' | 'reject_all' | 'customize' | 'update'): void {
        const storedConsent: StoredConsent = {
            preferences: this.consent,
            consentedAt: new Date().toISOString(),
            version: this.config.consentVersion || CONSENT_VERSION,
            visitorId: this.visitorId,
        };

        localStorage.setItem(STORAGE_KEY, JSON.stringify(storedConsent));
        this.syncConsentToServer(action);
    }

    private async syncConsentToServer(action: string): Promise<void> {
        if (!this.apiClient) return;

        try {
            await this.apiClient.post('/consent', {
                visitor_id: this.visitorId,
                necessary: this.consent.necessary,
                analytics: this.consent.analytics,
                marketing: this.consent.marketing,
                preferences: this.consent.preferences,
                action: action,
                consent_version: this.config.consentVersion || CONSENT_VERSION,
                page_url: window.location.href,
                referrer_url: document.referrer || null,
            });
        } catch (error) {
            console.warn('Failed to sync consent with server:', error);
        }
    }

    private async syncWithServer(): Promise<void> {
        if (!this.apiClient) return;

        try {
            const response = await this.apiClient.get(`/consent?visitor_id=${encodeURIComponent(this.visitorId)}`);
            if (response.success && response.data?.has_consent && response.data?.consent) {
                const serverConsent = response.data.consent;
                this.consent = {
                    necessary: true,
                    analytics: serverConsent.analytics,
                    marketing: serverConsent.marketing,
                    preferences: serverConsent.preferences,
                };
                this.saveConsent('update');
                this.updateGoogleConsentMode();
                this.loadPendingScripts();
            }
        } catch (error) {
            console.warn('Failed to sync consent from server:', error);
        }
    }

    private emitConsentUpdate(): void {
        if (this.eventBus) {
            this.eventBus.emit('consent:update', this.consent);
        }

        window.dispatchEvent(new CustomEvent('tixello:consent', {
            detail: this.consent
        }));
    }

    // ==================== Banner UI ====================

    private showBanner(): void {
        if (this.bannerElement) {
            this.bannerElement.style.display = 'block';
            this.bannerElement.setAttribute('aria-hidden', 'false');
            this.focusFirstButton();
            return;
        }

        this.bannerElement = document.createElement('div');
        this.bannerElement.id = 'tixello-cookie-banner';
        this.bannerElement.setAttribute('role', 'dialog');
        this.bannerElement.setAttribute('aria-modal', 'false');
        this.bannerElement.setAttribute('aria-label', this.t.cookiePreferences);
        this.bannerElement.setAttribute('aria-describedby', 'tixello-consent-description');
        this.bannerElement.innerHTML = this.getBannerHTML();
        this.bannerElement.style.cssText = this.getBannerStyles();

        if (this.isDarkMode) {
            this.bannerElement.classList.add('tixello-dark');
        }

        document.body.appendChild(this.bannerElement);
        this.bindBannerEvents();
        this.focusFirstButton();
    }

    private focusFirstButton(): void {
        setTimeout(() => {
            const firstBtn = this.bannerElement?.querySelector('button');
            firstBtn?.focus();
        }, 100);
    }

    private hideBanner(): void {
        if (this.bannerElement) {
            this.bannerElement.style.display = 'none';
            this.bannerElement.setAttribute('aria-hidden', 'true');
        }
    }

    private getBannerHTML(): string {
        const title = this.config.bannerTitle || this.t.title;
        const description = this.config.bannerDescription || this.t.description;

        let policyLinks = '';
        if (this.config.privacyPolicyUrl) {
            policyLinks += `<a href="${this.config.privacyPolicyUrl}" target="_blank" rel="noopener">${this.t.privacyPolicy}</a>`;
        }
        if (this.config.cookiePolicyUrl) {
            if (policyLinks) policyLinks += ' | ';
            policyLinks += `<a href="${this.config.cookiePolicyUrl}" target="_blank" rel="noopener">${this.t.cookiePolicy}</a>`;
        }

        return `
            <div class="tixello-consent-content">
                <div class="tixello-consent-text">
                    <p class="tixello-consent-title" id="tixello-consent-title">${title}</p>
                    <p class="tixello-consent-description" id="tixello-consent-description">
                        ${description}
                        ${policyLinks ? `<br><span class="tixello-consent-links">${policyLinks}</span>` : ''}
                    </p>
                </div>
                <div class="tixello-consent-actions" role="group" aria-label="Cookie consent options">
                    <button class="tixello-consent-btn tixello-consent-btn-secondary" id="tixello-consent-customize" aria-describedby="tixello-consent-description">
                        ${this.t.customize}
                    </button>
                    <button class="tixello-consent-btn tixello-consent-btn-outline" id="tixello-consent-reject" aria-describedby="tixello-consent-description">
                        ${this.t.rejectAll}
                    </button>
                    <button class="tixello-consent-btn tixello-consent-btn-primary" id="tixello-consent-accept" aria-describedby="tixello-consent-description">
                        ${this.t.acceptAll}
                    </button>
                </div>
            </div>
        `;
    }

    private getBannerStyles(): string {
        const position = this.config.position || 'bottom';
        const positionStyles = position === 'bottom'
            ? 'bottom: 0; left: 0; right: 0;'
            : position === 'top'
            ? 'top: 0; left: 0; right: 0;'
            : 'top: 50%; left: 50%; transform: translate(-50%, -50%);';

        return `
            position: fixed;
            ${positionStyles}
            z-index: 99999;
            padding: 1rem;
            font-family: var(--tixello-font, system-ui, sans-serif);
        `;
    }

    private bindBannerEvents(): void {
        const acceptBtn = document.getElementById('tixello-consent-accept');
        const rejectBtn = document.getElementById('tixello-consent-reject');
        const customizeBtn = document.getElementById('tixello-consent-customize');

        acceptBtn?.addEventListener('click', () => this.acceptAll());
        rejectBtn?.addEventListener('click', () => this.rejectAll());
        customizeBtn?.addEventListener('click', () => this.showSettingsModal());

        // Keyboard navigation
        this.bannerElement?.addEventListener('keydown', (e) => {
            if (e.key === 'Escape') {
                this.rejectAll();
            }
        });
    }

    private acceptAll(): void {
        this.consent = {
            necessary: true,
            analytics: true,
            marketing: true,
            preferences: true,
        };

        this.saveConsent('accept_all');
        this.hideBanner();
        this.emitConsentUpdate();
        this.updateGoogleConsentMode();
        this.loadPendingScripts();
    }

    private rejectAll(): void {
        this.consent = {
            necessary: true,
            analytics: false,
            marketing: false,
            preferences: false,
        };

        this.saveConsent('reject_all');
        this.hideBanner();
        this.emitConsentUpdate();
        this.updateGoogleConsentMode();
    }

    // ==================== Settings Modal ====================

    private showSettingsModal(): void {
        // Store current focus for restoration
        this.previousActiveElement = document.activeElement;

        if (this.settingsModalElement) {
            this.settingsModalElement.style.display = 'flex';
            this.settingsModalElement.setAttribute('aria-hidden', 'false');
            this.updateSettingsCheckboxes();
            this.trapFocus();
            return;
        }

        this.settingsModalElement = document.createElement('div');
        this.settingsModalElement.id = 'tixello-cookie-settings';
        this.settingsModalElement.setAttribute('role', 'dialog');
        this.settingsModalElement.setAttribute('aria-modal', 'true');
        this.settingsModalElement.setAttribute('aria-labelledby', 'tixello-settings-title');
        this.settingsModalElement.innerHTML = this.getSettingsHTML();
        this.settingsModalElement.style.cssText = this.getSettingsStyles();

        if (this.isDarkMode) {
            this.settingsModalElement.classList.add('tixello-dark');
        }

        document.body.appendChild(this.settingsModalElement);
        this.bindSettingsEvents();
        this.trapFocus();
    }

    private trapFocus(): void {
        setTimeout(() => {
            const modal = this.settingsModalElement?.querySelector('.tixello-settings-modal');
            const focusableElements = modal?.querySelectorAll(
                'button, [href], input, select, textarea, [tabindex]:not([tabindex="-1"])'
            );

            if (focusableElements && focusableElements.length > 0) {
                (focusableElements[0] as HTMLElement).focus();
            }
        }, 100);
    }

    private updateSettingsCheckboxes(): void {
        const analyticsToggle = document.getElementById('tixello-toggle-analytics') as HTMLInputElement;
        const marketingToggle = document.getElementById('tixello-toggle-marketing') as HTMLInputElement;
        const preferencesToggle = document.getElementById('tixello-toggle-preferences') as HTMLInputElement;

        if (analyticsToggle) analyticsToggle.checked = this.consent.analytics;
        if (marketingToggle) marketingToggle.checked = this.consent.marketing;
        if (preferencesToggle) preferencesToggle.checked = this.consent.preferences;
    }

    private hideSettingsModal(): void {
        if (this.settingsModalElement) {
            this.settingsModalElement.style.display = 'none';
            this.settingsModalElement.setAttribute('aria-hidden', 'true');

            // Restore focus
            if (this.previousActiveElement instanceof HTMLElement) {
                this.previousActiveElement.focus();
            }
        }
    }

    private getSettingsHTML(): string {
        return `
            <div class="tixello-settings-backdrop" id="tixello-settings-backdrop"></div>
            <div class="tixello-settings-modal" role="document">
                <div class="tixello-settings-header">
                    <h2 id="tixello-settings-title">${this.t.cookiePreferences}</h2>
                    <button class="tixello-settings-close" id="tixello-settings-close" aria-label="${this.t.close}">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="tixello-settings-body">
                    <div class="tixello-settings-category">
                        <div class="tixello-settings-category-header">
                            <div>
                                <h3 id="necessary-label">${this.t.necessaryCookies}</h3>
                                <p id="necessary-desc">${this.t.necessaryDescription}</p>
                            </div>
                            <label class="tixello-toggle tixello-toggle-disabled">
                                <input type="checkbox" checked disabled aria-labelledby="necessary-label" aria-describedby="necessary-desc">
                                <span class="tixello-toggle-slider" aria-hidden="true"></span>
                            </label>
                        </div>
                    </div>

                    <div class="tixello-settings-category">
                        <div class="tixello-settings-category-header">
                            <div>
                                <h3 id="analytics-label">${this.t.analyticsCookies}</h3>
                                <p id="analytics-desc">${this.t.analyticsDescription}</p>
                            </div>
                            <label class="tixello-toggle">
                                <input type="checkbox" id="tixello-toggle-analytics" ${this.consent.analytics ? 'checked' : ''} aria-labelledby="analytics-label" aria-describedby="analytics-desc">
                                <span class="tixello-toggle-slider" aria-hidden="true"></span>
                            </label>
                        </div>
                    </div>

                    <div class="tixello-settings-category">
                        <div class="tixello-settings-category-header">
                            <div>
                                <h3 id="marketing-label">${this.t.marketingCookies}</h3>
                                <p id="marketing-desc">${this.t.marketingDescription}</p>
                            </div>
                            <label class="tixello-toggle">
                                <input type="checkbox" id="tixello-toggle-marketing" ${this.consent.marketing ? 'checked' : ''} aria-labelledby="marketing-label" aria-describedby="marketing-desc">
                                <span class="tixello-toggle-slider" aria-hidden="true"></span>
                            </label>
                        </div>
                    </div>

                    <div class="tixello-settings-category">
                        <div class="tixello-settings-category-header">
                            <div>
                                <h3 id="preferences-label">${this.t.preferenceCookies}</h3>
                                <p id="preferences-desc">${this.t.preferenceDescription}</p>
                            </div>
                            <label class="tixello-toggle">
                                <input type="checkbox" id="tixello-toggle-preferences" ${this.consent.preferences ? 'checked' : ''} aria-labelledby="preferences-label" aria-describedby="preferences-desc">
                                <span class="tixello-toggle-slider" aria-hidden="true"></span>
                            </label>
                        </div>
                    </div>
                </div>
                <div class="tixello-settings-footer">
                    <button class="tixello-consent-btn tixello-consent-btn-outline" id="tixello-settings-reject-all">
                        ${this.t.rejectAll}
                    </button>
                    <button class="tixello-consent-btn tixello-consent-btn-primary" id="tixello-settings-save">
                        ${this.t.savePreferences}
                    </button>
                </div>
            </div>
        `;
    }

    private getSettingsStyles(): string {
        return `
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            z-index: 100000;
            display: flex;
            align-items: center;
            justify-content: center;
            font-family: var(--tixello-font, system-ui, sans-serif);
        `;
    }

    private bindSettingsEvents(): void {
        const closeBtn = document.getElementById('tixello-settings-close');
        const backdrop = document.getElementById('tixello-settings-backdrop');
        const saveBtn = document.getElementById('tixello-settings-save');
        const rejectAllBtn = document.getElementById('tixello-settings-reject-all');

        closeBtn?.addEventListener('click', () => this.hideSettingsModal());
        backdrop?.addEventListener('click', () => this.hideSettingsModal());
        saveBtn?.addEventListener('click', () => this.saveSettings());
        rejectAllBtn?.addEventListener('click', () => {
            this.rejectAll();
            this.hideSettingsModal();
        });

        // Keyboard navigation
        this.settingsModalElement?.addEventListener('keydown', (e) => {
            if (e.key === 'Escape') {
                this.hideSettingsModal();
            }

            // Tab trap
            if (e.key === 'Tab') {
                const modal = this.settingsModalElement?.querySelector('.tixello-settings-modal');
                const focusableElements = modal?.querySelectorAll(
                    'button, [href], input:not([disabled]), select, textarea, [tabindex]:not([tabindex="-1"])'
                );

                if (focusableElements && focusableElements.length > 0) {
                    const firstElement = focusableElements[0] as HTMLElement;
                    const lastElement = focusableElements[focusableElements.length - 1] as HTMLElement;

                    if (e.shiftKey && document.activeElement === firstElement) {
                        e.preventDefault();
                        lastElement.focus();
                    } else if (!e.shiftKey && document.activeElement === lastElement) {
                        e.preventDefault();
                        firstElement.focus();
                    }
                }
            }
        });
    }

    private saveSettings(): void {
        const analyticsToggle = document.getElementById('tixello-toggle-analytics') as HTMLInputElement;
        const marketingToggle = document.getElementById('tixello-toggle-marketing') as HTMLInputElement;
        const preferencesToggle = document.getElementById('tixello-toggle-preferences') as HTMLInputElement;

        this.consent = {
            necessary: true,
            analytics: analyticsToggle?.checked || false,
            marketing: marketingToggle?.checked || false,
            preferences: preferencesToggle?.checked || false,
        };

        this.saveConsent('customize');
        this.hideSettingsModal();
        this.hideBanner();
        this.emitConsentUpdate();
        this.updateGoogleConsentMode();
        this.loadPendingScripts();
    }

    // ==================== Styles ====================

    static injectStyles(): void {
        if (document.getElementById('tixello-consent-styles')) return;

        const style = document.createElement('style');
        style.id = 'tixello-consent-styles';
        style.textContent = `
            /* Light theme (default) */
            #tixello-cookie-banner {
                background: white;
                box-shadow: 0 -4px 20px rgba(0, 0, 0, 0.1);
            }

            .tixello-consent-content {
                max-width: 1200px;
                margin: 0 auto;
                display: flex;
                flex-wrap: wrap;
                align-items: center;
                justify-content: space-between;
                gap: 1rem;
                background: white;
                padding: 1.5rem;
                border-radius: 0.5rem;
            }

            .tixello-consent-text {
                flex: 1;
                min-width: 280px;
            }

            .tixello-consent-title {
                font-size: 1.125rem;
                font-weight: 600;
                margin: 0 0 0.5rem 0;
                color: #111827;
            }

            .tixello-consent-description {
                font-size: 0.875rem;
                color: #6b7280;
                margin: 0;
                line-height: 1.5;
            }

            .tixello-consent-description a,
            .tixello-consent-links a {
                color: var(--tixello-primary, #3b82f6);
                text-decoration: underline;
            }

            .tixello-consent-links {
                display: inline-block;
                margin-top: 0.5rem;
                font-size: 0.8125rem;
            }

            .tixello-consent-actions {
                display: flex;
                flex-wrap: wrap;
                gap: 0.5rem;
            }

            .tixello-consent-btn {
                padding: 0.625rem 1.25rem;
                border-radius: 0.375rem;
                font-size: 0.875rem;
                font-weight: 500;
                cursor: pointer;
                transition: all 0.2s;
                border: none;
            }

            .tixello-consent-btn:focus {
                outline: 2px solid var(--tixello-primary, #3b82f6);
                outline-offset: 2px;
            }

            .tixello-consent-btn-primary {
                background: var(--tixello-primary, #3b82f6);
                color: white;
            }

            .tixello-consent-btn-primary:hover {
                background: var(--tixello-primary-dark, #2563eb);
            }

            .tixello-consent-btn-secondary {
                background: #f3f4f6;
                color: #374151;
            }

            .tixello-consent-btn-secondary:hover {
                background: #e5e7eb;
            }

            .tixello-consent-btn-outline {
                background: transparent;
                color: #374151;
                border: 1px solid #d1d5db;
            }

            .tixello-consent-btn-outline:hover {
                background: #f3f4f6;
            }

            /* Settings Modal */
            .tixello-settings-backdrop {
                position: absolute;
                top: 0;
                left: 0;
                right: 0;
                bottom: 0;
                background: rgba(0, 0, 0, 0.5);
            }

            .tixello-settings-modal {
                position: relative;
                background: white;
                border-radius: 0.75rem;
                max-width: 500px;
                width: 90%;
                max-height: 90vh;
                overflow-y: auto;
                box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.25);
            }

            .tixello-settings-header {
                display: flex;
                justify-content: space-between;
                align-items: center;
                padding: 1.25rem 1.5rem;
                border-bottom: 1px solid #e5e7eb;
            }

            .tixello-settings-header h2 {
                margin: 0;
                font-size: 1.25rem;
                font-weight: 600;
                color: #111827;
            }

            .tixello-settings-close {
                background: none;
                border: none;
                font-size: 1.5rem;
                color: #6b7280;
                cursor: pointer;
                padding: 0.25rem;
                line-height: 1;
                border-radius: 0.25rem;
            }

            .tixello-settings-close:hover {
                color: #111827;
            }

            .tixello-settings-close:focus {
                outline: 2px solid var(--tixello-primary, #3b82f6);
                outline-offset: 2px;
            }

            .tixello-settings-body {
                padding: 1rem 1.5rem;
            }

            .tixello-settings-category {
                padding: 1rem 0;
                border-bottom: 1px solid #e5e7eb;
            }

            .tixello-settings-category:last-child {
                border-bottom: none;
            }

            .tixello-settings-category-header {
                display: flex;
                justify-content: space-between;
                align-items: flex-start;
                gap: 1rem;
            }

            .tixello-settings-category h3 {
                margin: 0 0 0.25rem 0;
                font-size: 0.9375rem;
                font-weight: 600;
                color: #111827;
            }

            .tixello-settings-category p {
                margin: 0;
                font-size: 0.8125rem;
                color: #6b7280;
                line-height: 1.4;
            }

            .tixello-settings-footer {
                display: flex;
                justify-content: flex-end;
                gap: 0.75rem;
                padding: 1.25rem 1.5rem;
                border-top: 1px solid #e5e7eb;
            }

            /* Toggle Switch */
            .tixello-toggle {
                position: relative;
                display: inline-block;
                width: 48px;
                height: 26px;
                flex-shrink: 0;
            }

            .tixello-toggle input {
                opacity: 0;
                width: 0;
                height: 0;
            }

            .tixello-toggle input:focus + .tixello-toggle-slider {
                box-shadow: 0 0 0 2px var(--tixello-primary, #3b82f6);
            }

            .tixello-toggle-slider {
                position: absolute;
                cursor: pointer;
                top: 0;
                left: 0;
                right: 0;
                bottom: 0;
                background-color: #d1d5db;
                transition: 0.3s;
                border-radius: 26px;
            }

            .tixello-toggle-slider:before {
                position: absolute;
                content: "";
                height: 20px;
                width: 20px;
                left: 3px;
                bottom: 3px;
                background-color: white;
                transition: 0.3s;
                border-radius: 50%;
            }

            .tixello-toggle input:checked + .tixello-toggle-slider {
                background-color: var(--tixello-primary, #3b82f6);
            }

            .tixello-toggle input:checked + .tixello-toggle-slider:before {
                transform: translateX(22px);
            }

            .tixello-toggle-disabled .tixello-toggle-slider {
                cursor: not-allowed;
                opacity: 0.6;
            }

            .tixello-toggle-disabled input:checked + .tixello-toggle-slider {
                background-color: #9ca3af;
            }

            /* Footer Link */
            .tixello-footer-link {
                background: none;
                border: none;
                color: #6b7280;
                font-size: 0.75rem;
                cursor: pointer;
                padding: 0.5rem;
                text-decoration: underline;
            }

            .tixello-footer-link:hover {
                color: var(--tixello-primary, #3b82f6);
            }

            .tixello-footer-link:focus {
                outline: 2px solid var(--tixello-primary, #3b82f6);
                outline-offset: 2px;
            }

            .tixello-footer-link-fixed {
                position: fixed;
                bottom: 1rem;
                left: 1rem;
                z-index: 99998;
                background: white;
                padding: 0.5rem 1rem;
                border-radius: 0.25rem;
                box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
            }

            /* Dark Mode */
            #tixello-cookie-banner.tixello-dark {
                background: #1f2937;
                box-shadow: 0 -4px 20px rgba(0, 0, 0, 0.3);
            }

            .tixello-dark .tixello-consent-content {
                background: #1f2937;
            }

            .tixello-dark .tixello-consent-title {
                color: #f9fafb;
            }

            .tixello-dark .tixello-consent-description {
                color: #9ca3af;
            }

            .tixello-dark .tixello-consent-btn-secondary {
                background: #374151;
                color: #f3f4f6;
            }

            .tixello-dark .tixello-consent-btn-secondary:hover {
                background: #4b5563;
            }

            .tixello-dark .tixello-consent-btn-outline {
                color: #f3f4f6;
                border-color: #4b5563;
            }

            .tixello-dark .tixello-consent-btn-outline:hover {
                background: #374151;
            }

            .tixello-dark .tixello-settings-modal {
                background: #1f2937;
            }

            .tixello-dark .tixello-settings-header {
                border-bottom-color: #374151;
            }

            .tixello-dark .tixello-settings-header h2 {
                color: #f9fafb;
            }

            .tixello-dark .tixello-settings-close {
                color: #9ca3af;
            }

            .tixello-dark .tixello-settings-close:hover {
                color: #f9fafb;
            }

            .tixello-dark .tixello-settings-category {
                border-bottom-color: #374151;
            }

            .tixello-dark .tixello-settings-category h3 {
                color: #f9fafb;
            }

            .tixello-dark .tixello-settings-category p {
                color: #9ca3af;
            }

            .tixello-dark .tixello-settings-footer {
                border-top-color: #374151;
            }

            .tixello-dark .tixello-toggle-slider {
                background-color: #4b5563;
            }

            .tixello-dark .tixello-footer-link {
                color: #9ca3af;
            }

            .tixello-dark .tixello-footer-link-fixed {
                background: #1f2937;
            }

            /* Renewal Banner */
            .tixello-renewal-content {
                max-width: 1200px;
                margin: 0 auto;
                display: flex;
                flex-wrap: wrap;
                align-items: center;
                justify-content: space-between;
                gap: 1rem;
            }

            .tixello-renewal-text {
                flex: 1;
                min-width: 280px;
            }

            .tixello-renewal-title {
                font-size: 0.9375rem;
                font-weight: 600;
                margin: 0 0 0.25rem 0;
                color: #1f2937;
            }

            .tixello-renewal-description {
                font-size: 0.8125rem;
                color: #4b5563;
                margin: 0;
            }

            .tixello-renewal-days {
                font-weight: 500;
            }

            .tixello-renewal-actions {
                display: flex;
                gap: 0.5rem;
            }

            .tixello-renewal-urgent .tixello-renewal-title {
                color: #92400e;
            }

            .tixello-renewal-urgent .tixello-renewal-description {
                color: #78350f;
            }

            .tixello-dark #tixello-renewal-banner {
                background: #1e3a5f;
                border-bottom-color: #3b82f6;
            }

            .tixello-dark .tixello-renewal-title {
                color: #f3f4f6;
            }

            .tixello-dark .tixello-renewal-description {
                color: #d1d5db;
            }

            /* Responsive */
            @media (max-width: 640px) {
                .tixello-consent-content {
                    flex-direction: column;
                    align-items: stretch;
                }

                .tixello-consent-actions {
                    flex-direction: column;
                }

                .tixello-consent-btn {
                    width: 100%;
                    text-align: center;
                }

                .tixello-renewal-content {
                    flex-direction: column;
                    align-items: stretch;
                    text-align: center;
                }

                .tixello-renewal-actions {
                    flex-direction: column;
                    width: 100%;
                }
            }

            /* Reduced motion */
            @media (prefers-reduced-motion: reduce) {
                .tixello-toggle-slider,
                .tixello-toggle-slider:before,
                .tixello-consent-btn {
                    transition: none;
                }
            }
        `;
        document.head.appendChild(style);
    }
}

// Auto-inject styles
if (typeof document !== 'undefined') {
    CookieConsentModule.injectStyles();
}

// Export singleton
export const CookieConsent = CookieConsentModule.getInstance();
