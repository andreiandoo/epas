/**
 * Cookie Consent Module
 *
 * GDPR-compliant cookie consent management for tenant websites.
 * Displays a customizable cookie banner and manages consent preferences.
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

interface ConsentConfig {
    position?: 'bottom' | 'top' | 'center';
    theme?: 'light' | 'dark' | 'auto';
    showSettingsLink?: boolean;
    privacyPolicyUrl?: string;
    cookiePolicyUrl?: string;
    consentVersion?: string;
    expiryDays?: number;
}

interface StoredConsent {
    preferences: ConsentPreferences;
    consentedAt: string;
    version: string;
    visitorId: string;
}

const STORAGE_KEY = 'tixello_cookie_consent';
const VISITOR_ID_KEY = 'tixello_visitor_id';
const CONSENT_VERSION = '1.0';

export class CookieConsentModule {
    name = 'cookie-consent';
    private static instance: CookieConsentModule;
    private apiClient: ApiClient | null = null;
    private eventBus: EventBus | null = null;
    private config: ConsentConfig = {
        position: 'bottom',
        theme: 'light',
        showSettingsLink: true,
        consentVersion: CONSENT_VERSION,
        expiryDays: 365,
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
    private isInitialized: boolean = false;

    private constructor() {}

    static getInstance(): CookieConsentModule {
        if (!CookieConsentModule.instance) {
            CookieConsentModule.instance = new CookieConsentModule();
        }
        return CookieConsentModule.instance;
    }

    async init(apiClient: ApiClient, eventBus: EventBus, tixelloConfig?: TixelloConfig): Promise<void> {
        if (this.isInitialized) return;

        this.apiClient = apiClient;
        this.eventBus = eventBus;
        this.visitorId = this.getOrCreateVisitorId();

        // Check for existing consent
        const storedConsent = this.getStoredConsent();

        if (storedConsent && storedConsent.version === this.config.consentVersion) {
            // Valid consent exists
            this.consent = storedConsent.preferences;
            this.emitConsentUpdate();
        } else {
            // No consent or outdated version - show banner
            this.showBanner();
        }

        // Also sync with server if we have consent
        if (storedConsent) {
            this.syncWithServer();
        }

        this.isInitialized = true;
        console.log('Cookie Consent module initialized');
    }

    /**
     * Get current consent status
     */
    getConsent(): ConsentPreferences {
        return { ...this.consent };
    }

    /**
     * Check if a specific consent category is granted
     */
    hasConsent(category: keyof ConsentPreferences): boolean {
        return this.consent[category] === true;
    }

    /**
     * Show cookie settings modal
     */
    showSettings(): void {
        this.showSettingsModal();
    }

    /**
     * Withdraw all consent
     */
    async withdrawConsent(): Promise<void> {
        this.consent = {
            necessary: true,
            analytics: false,
            marketing: false,
            preferences: false,
        };

        this.saveConsent('reject_all');
        this.emitConsentUpdate();

        // Sync withdrawal with server
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

    // Private methods

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

        // Sync with server
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
                // Server has consent - use it if different
                const serverConsent = response.data.consent;
                this.consent = {
                    necessary: true,
                    analytics: serverConsent.analytics,
                    marketing: serverConsent.marketing,
                    preferences: serverConsent.preferences,
                };
                // Update local storage
                this.saveConsent('update');
            }
        } catch (error) {
            console.warn('Failed to sync consent from server:', error);
        }
    }

    private emitConsentUpdate(): void {
        if (this.eventBus) {
            this.eventBus.emit('consent:update', this.consent);
        }

        // Also emit a custom event for external listeners
        window.dispatchEvent(new CustomEvent('tixello:consent', {
            detail: this.consent
        }));
    }

    private showBanner(): void {
        if (this.bannerElement) {
            this.bannerElement.style.display = 'block';
            return;
        }

        this.bannerElement = document.createElement('div');
        this.bannerElement.id = 'tixello-cookie-banner';
        this.bannerElement.innerHTML = this.getBannerHTML();
        this.bannerElement.style.cssText = this.getBannerStyles();

        document.body.appendChild(this.bannerElement);
        this.bindBannerEvents();
    }

    private hideBanner(): void {
        if (this.bannerElement) {
            this.bannerElement.style.display = 'none';
        }
    }

    private getBannerHTML(): string {
        return `
            <div class="tixello-consent-content">
                <div class="tixello-consent-text">
                    <p class="tixello-consent-title">We use cookies</p>
                    <p class="tixello-consent-description">
                        We use cookies to improve your experience on our site. By clicking "Accept All", you consent to the use of all cookies.
                        You can also customize your preferences.
                        ${this.config.privacyPolicyUrl ? `<a href="${this.config.privacyPolicyUrl}" target="_blank" rel="noopener">Privacy Policy</a>` : ''}
                    </p>
                </div>
                <div class="tixello-consent-actions">
                    <button class="tixello-consent-btn tixello-consent-btn-secondary" id="tixello-consent-customize">
                        Customize
                    </button>
                    <button class="tixello-consent-btn tixello-consent-btn-outline" id="tixello-consent-reject">
                        Reject All
                    </button>
                    <button class="tixello-consent-btn tixello-consent-btn-primary" id="tixello-consent-accept">
                        Accept All
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

        if (acceptBtn) {
            acceptBtn.addEventListener('click', () => this.acceptAll());
        }

        if (rejectBtn) {
            rejectBtn.addEventListener('click', () => this.rejectAll());
        }

        if (customizeBtn) {
            customizeBtn.addEventListener('click', () => this.showSettingsModal());
        }
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
    }

    private showSettingsModal(): void {
        if (this.settingsModalElement) {
            this.settingsModalElement.style.display = 'flex';
            return;
        }

        this.settingsModalElement = document.createElement('div');
        this.settingsModalElement.id = 'tixello-cookie-settings';
        this.settingsModalElement.innerHTML = this.getSettingsHTML();
        this.settingsModalElement.style.cssText = this.getSettingsStyles();

        document.body.appendChild(this.settingsModalElement);
        this.bindSettingsEvents();
    }

    private hideSettingsModal(): void {
        if (this.settingsModalElement) {
            this.settingsModalElement.style.display = 'none';
        }
    }

    private getSettingsHTML(): string {
        return `
            <div class="tixello-settings-backdrop" id="tixello-settings-backdrop"></div>
            <div class="tixello-settings-modal">
                <div class="tixello-settings-header">
                    <h2>Cookie Preferences</h2>
                    <button class="tixello-settings-close" id="tixello-settings-close">&times;</button>
                </div>
                <div class="tixello-settings-body">
                    <div class="tixello-settings-category">
                        <div class="tixello-settings-category-header">
                            <div>
                                <h3>Necessary Cookies</h3>
                                <p>Required for the website to function. Cannot be disabled.</p>
                            </div>
                            <label class="tixello-toggle tixello-toggle-disabled">
                                <input type="checkbox" checked disabled>
                                <span class="tixello-toggle-slider"></span>
                            </label>
                        </div>
                    </div>

                    <div class="tixello-settings-category">
                        <div class="tixello-settings-category-header">
                            <div>
                                <h3>Analytics Cookies</h3>
                                <p>Help us understand how visitors interact with our website.</p>
                            </div>
                            <label class="tixello-toggle">
                                <input type="checkbox" id="tixello-toggle-analytics" ${this.consent.analytics ? 'checked' : ''}>
                                <span class="tixello-toggle-slider"></span>
                            </label>
                        </div>
                    </div>

                    <div class="tixello-settings-category">
                        <div class="tixello-settings-category-header">
                            <div>
                                <h3>Marketing Cookies</h3>
                                <p>Used to deliver relevant advertisements and track ad performance.</p>
                            </div>
                            <label class="tixello-toggle">
                                <input type="checkbox" id="tixello-toggle-marketing" ${this.consent.marketing ? 'checked' : ''}>
                                <span class="tixello-toggle-slider"></span>
                            </label>
                        </div>
                    </div>

                    <div class="tixello-settings-category">
                        <div class="tixello-settings-category-header">
                            <div>
                                <h3>Preference Cookies</h3>
                                <p>Remember your settings and preferences for a better experience.</p>
                            </div>
                            <label class="tixello-toggle">
                                <input type="checkbox" id="tixello-toggle-preferences" ${this.consent.preferences ? 'checked' : ''}>
                                <span class="tixello-toggle-slider"></span>
                            </label>
                        </div>
                    </div>
                </div>
                <div class="tixello-settings-footer">
                    <button class="tixello-consent-btn tixello-consent-btn-outline" id="tixello-settings-reject-all">
                        Reject All
                    </button>
                    <button class="tixello-consent-btn tixello-consent-btn-primary" id="tixello-settings-save">
                        Save Preferences
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

        if (closeBtn) {
            closeBtn.addEventListener('click', () => this.hideSettingsModal());
        }

        if (backdrop) {
            backdrop.addEventListener('click', () => this.hideSettingsModal());
        }

        if (saveBtn) {
            saveBtn.addEventListener('click', () => this.saveSettings());
        }

        if (rejectAllBtn) {
            rejectAllBtn.addEventListener('click', () => {
                this.rejectAll();
                this.hideSettingsModal();
            });
        }
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
    }

    /**
     * Inject CSS styles for the consent banner and settings modal
     */
    static injectStyles(): void {
        if (document.getElementById('tixello-consent-styles')) return;

        const style = document.createElement('style');
        style.id = 'tixello-consent-styles';
        style.textContent = `
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

            .tixello-consent-description a {
                color: var(--tixello-primary, #3b82f6);
                text-decoration: underline;
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
                padding: 0;
                line-height: 1;
            }

            .tixello-settings-close:hover {
                color: #111827;
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
