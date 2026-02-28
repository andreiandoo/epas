/**
 * Cookie Consent Manager
 */
const CookieConsent = {
    storageKey: 'ambilet_cookie_consent',

    init() {
        const consent = this.getConsent();
        if (!consent) {
            // Show banner after a short delay
            setTimeout(() => this.showBanner(), 1000);
        }

        // Close modal on overlay click
        document.getElementById('cookieModal')?.addEventListener('click', (e) => {
            if (e.target.id === 'cookieModal') {
                this.closeSettings();
            }
        });
    },

    getConsent() {
        try {
            return JSON.parse(localStorage.getItem(this.storageKey));
        } catch {
            return null;
        }
    },

    setConsent(consent) {
        localStorage.setItem(this.storageKey, JSON.stringify({
            ...consent,
            timestamp: Date.now()
        }));
        this.hideBanner();
        this.closeSettings();
        this.applyConsent(consent);
    },

    applyConsent(consent) {
        // Dispatch event for other scripts to listen
        window.dispatchEvent(new CustomEvent('cookieConsentUpdated', { detail: consent }));

        // Google Consent Mode v2 — update all consent signals
        if (typeof gtag !== 'undefined') {
            gtag('consent', 'update', {
                'ad_storage': consent.marketing ? 'granted' : 'denied',
                'ad_user_data': consent.marketing ? 'granted' : 'denied',
                'ad_personalization': consent.marketing ? 'granted' : 'denied',
                'analytics_storage': consent.analytics ? 'granted' : 'denied',
                'functionality_storage': consent.functional ? 'granted' : 'denied',
                'personalization_storage': consent.functional ? 'granted' : 'denied'
            });
        }

        // Meta Pixel consent
        if (typeof fbq !== 'undefined') {
            fbq('consent', consent.marketing ? 'grant' : 'revoke');
        }

        // TikTok Pixel consent
        if (typeof ttq !== 'undefined') {
            if (consent.marketing) {
                ttq.enableCookie();
                ttq.grantConsent();
            } else {
                ttq.disableCookie();
                ttq.revokeConsent();
            }
        }
    },

    showBanner() {
        const banner = document.getElementById('cookieBanner');
        if (banner) {
            banner.classList.remove('translate-y-full');
            banner.classList.add('translate-y-0');
        }
    },

    hideBanner() {
        const banner = document.getElementById('cookieBanner');
        if (banner) {
            banner.classList.remove('translate-y-0');
            banner.classList.add('translate-y-full');
        }
    },

    openSettings() {
        const modal = document.getElementById('cookieModal');
        const content = document.getElementById('cookieModalContent');
        if (modal) {
            modal.classList.remove('hidden');
            modal.classList.add('flex');
            setTimeout(() => {
                modal.classList.remove('opacity-0');
                modal.classList.add('opacity-100');
                content?.classList.remove('scale-95');
                content?.classList.add('scale-100');
            }, 10);
        }
        // Load current preferences
        const consent = this.getConsent() || {};
        document.getElementById('cookieFunctional').checked = consent.functional !== false;
        document.getElementById('cookieAnalytics').checked = consent.analytics !== false;
        document.getElementById('cookieMarketing').checked = consent.marketing === true;
    },

    closeSettings() {
        const modal = document.getElementById('cookieModal');
        const content = document.getElementById('cookieModalContent');
        if (modal) {
            modal.classList.remove('opacity-100');
            modal.classList.add('opacity-0');
            content?.classList.remove('scale-100');
            content?.classList.add('scale-95');
            setTimeout(() => {
                modal.classList.add('hidden');
                modal.classList.remove('flex');
            }, 300);
        }
    },

    acceptAll() {
        this.setConsent({
            essential: true,
            functional: true,
            analytics: true,
            marketing: true
        });
    },

    rejectAll() {
        this.setConsent({
            essential: true,
            functional: false,
            analytics: false,
            marketing: false
        });
    },

    savePreferences() {
        this.setConsent({
            essential: true,
            functional: document.getElementById('cookieFunctional')?.checked ?? false,
            analytics: document.getElementById('cookieAnalytics')?.checked ?? false,
            marketing: document.getElementById('cookieMarketing')?.checked ?? false
        });
    }
};

// Initialize on DOM ready
document.addEventListener('DOMContentLoaded', () => CookieConsent.init());
