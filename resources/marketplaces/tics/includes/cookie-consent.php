<?php
/**
 * TICS.ro - Cookie Consent Banner & Modal
 *
 * Features:
 * - GDPR-compliant cookie consent
 * - Customizable categories (essential, analytics, functional, marketing)
 * - Persistent preferences via localStorage
 * - Google Consent Mode v2, Meta Pixel, TikTok Pixel support
 *
 * Usage: Include at the end of body, before </body>
 * <?php include __DIR__ . '/cookie-consent.php'; ?>
 */
?>

<!-- ========== BANNER: Bottom bar ========== -->
<div id="cookieBanner" class="cookie-banner fixed bottom-0 left-0 right-0 z-50 hidden">
    <div class="bg-white border-t border-gray-200 shadow-2xl shadow-black/10">
        <div class="max-w-7xl mx-auto px-4 lg:px-8 py-4">
            <div class="flex flex-col sm:flex-row items-start sm:items-center gap-4">
                <div class="flex items-start gap-3 flex-1">
                    <div class="w-10 h-10 bg-gray-100 rounded-xl flex items-center justify-center flex-shrink-0 mt-0.5">
                        <svg class="w-5 h-5 text-gray-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/></svg>
                    </div>
                    <div>
                        <p class="text-sm text-gray-900 font-medium mb-0.5">Folosim cookie-uri 🍪</p>
                        <p class="text-sm text-gray-600 leading-relaxed">Utilizăm cookie-uri pentru a îmbunătăți experiența ta pe TICS.ro, pentru analiză și personalizare. Poți alege ce cookie-uri accepți. <a href="/confidentialitate" class="text-indigo-600 font-medium hover:underline">Politica de confidențialitate</a></p>
                    </div>
                </div>
                <div class="flex items-center gap-2 flex-shrink-0 w-full sm:w-auto">
                    <button onclick="openCookieModal()" class="flex-1 sm:flex-none px-4 py-2.5 border border-gray-200 text-gray-700 text-sm font-medium rounded-full hover:bg-gray-50 transition-colors">Personalizează</button>
                    <button onclick="rejectAll()" class="flex-1 sm:flex-none px-4 py-2.5 border border-gray-200 text-gray-700 text-sm font-medium rounded-full hover:bg-gray-50 transition-colors">Refuză</button>
                    <button onclick="acceptAll()" class="flex-1 sm:flex-none px-5 py-2.5 bg-gray-900 text-white text-sm font-medium rounded-full hover:bg-gray-800 transition-colors">Acceptă toate</button>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- ========== MODAL: Cookie preferences ========== -->
<div id="cookieModal" class="fixed inset-0 z-[60] hidden">
    <div class="cookie-modal-backdrop absolute inset-0 bg-black/50 backdrop-blur-sm" onclick="closeCookieModal()"></div>
    <div class="relative flex items-center justify-center min-h-screen p-4">
        <div class="cookie-modal relative bg-white rounded-2xl shadow-2xl w-full max-w-xl max-h-[90vh] overflow-hidden flex flex-col">
            <!-- Header -->
            <div class="flex items-center justify-between p-6 border-b border-gray-100 flex-shrink-0">
                <div class="flex items-center gap-3">
                    <div class="w-10 h-10 bg-gray-100 rounded-xl flex items-center justify-center">
                        <svg class="w-5 h-5 text-gray-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.066 2.573c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.573 1.066c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.066-2.573c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                    </div>
                    <div>
                        <h2 class="font-semibold text-gray-900 text-lg">Preferințe cookie-uri</h2>
                        <p class="text-xs text-gray-500">Controlează cum folosim datele tale</p>
                    </div>
                </div>
                <button onclick="closeCookieModal()" class="w-10 h-10 flex items-center justify-center rounded-full hover:bg-gray-100 transition-colors"><svg class="w-5 h-5 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg></button>
            </div>

            <!-- Body -->
            <div class="flex-1 overflow-y-auto p-6 space-y-4">
                <p class="text-sm text-gray-600 leading-relaxed">Alege ce tipuri de cookie-uri dorești să permiți. Cookie-urile esențiale sunt necesare pentru funcționarea platformei și nu pot fi dezactivate.</p>

                <!-- Essential (always on) -->
                <div class="bg-gray-50 rounded-xl border border-gray-200 overflow-hidden">
                    <button onclick="toggleAccordion('essential')" class="w-full flex items-center justify-between p-4 text-left">
                        <div class="flex items-center gap-3">
                            <div class="w-8 h-8 bg-green-100 rounded-lg flex items-center justify-center flex-shrink-0"><svg class="w-4 h-4 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/></svg></div>
                            <div><p class="font-medium text-gray-900 text-sm">Esențiale</p><p class="text-xs text-gray-500">Necesare pentru funcționare</p></div>
                        </div>
                        <div class="flex items-center gap-3">
                            <span class="px-2.5 py-1 bg-green-100 text-green-700 text-xs font-medium rounded-full">Mereu active</span>
                            <svg id="icon-essential" class="accordion-icon w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                        </div>
                    </button>
                    <div id="acc-essential" class="accordion-content">
                        <div class="px-4 pb-4 pt-0"><p class="text-xs text-gray-500 leading-relaxed">Aceste cookie-uri sunt necesare pentru funcționalitățile de bază ale site-ului: autentificare, coș de cumpărături, procesare plăți, securitate. Fără ele, platforma nu poate funcționa corect.</p>
                            <div class="mt-3 flex flex-wrap gap-1.5"><span class="px-2 py-0.5 bg-white border border-gray-200 text-xs text-gray-600 rounded">Sesiune</span><span class="px-2 py-0.5 bg-white border border-gray-200 text-xs text-gray-600 rounded">CSRF Token</span><span class="px-2 py-0.5 bg-white border border-gray-200 text-xs text-gray-600 rounded">Coș cumpărături</span><span class="px-2 py-0.5 bg-white border border-gray-200 text-xs text-gray-600 rounded">Autentificare</span></div>
                        </div>
                    </div>
                </div>

                <!-- Analytics -->
                <div class="bg-white rounded-xl border border-gray-200 overflow-hidden">
                    <button onclick="toggleAccordion('analytics')" class="w-full flex items-center justify-between p-4 text-left">
                        <div class="flex items-center gap-3">
                            <div class="w-8 h-8 bg-blue-100 rounded-lg flex items-center justify-center flex-shrink-0"><svg class="w-4 h-4 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/></svg></div>
                            <div><p class="font-medium text-gray-900 text-sm">Analitice</p><p class="text-xs text-gray-500">Statistici de utilizare anonime</p></div>
                        </div>
                        <div class="flex items-center gap-3">
                            <label class="relative inline-flex cursor-pointer" onclick="event.stopPropagation()"><input type="checkbox" id="cookieAnalytics" class="toggle-input sr-only peer" checked><div class="toggle-track w-9 h-5 bg-gray-300 rounded-full peer-checked:bg-gray-900"><div class="toggle-thumb absolute top-0.5 left-0.5 w-4 h-4 bg-white rounded-full shadow-sm"></div></div></label>
                            <svg id="icon-analytics" class="accordion-icon w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                        </div>
                    </button>
                    <div id="acc-analytics" class="accordion-content">
                        <div class="px-4 pb-4 pt-0"><p class="text-xs text-gray-500 leading-relaxed">Ne ajută să înțelegem cum utilizezi platforma: pagini vizitate, durata sesiunii, rate de bounce. Datele sunt anonimizate și nu sunt partajate cu terți.</p>
                            <div class="mt-3 flex flex-wrap gap-1.5"><span class="px-2 py-0.5 bg-white border border-gray-200 text-xs text-gray-600 rounded">Google Analytics</span><span class="px-2 py-0.5 bg-white border border-gray-200 text-xs text-gray-600 rounded">Hotjar</span></div>
                        </div>
                    </div>
                </div>

                <!-- Functional -->
                <div class="bg-white rounded-xl border border-gray-200 overflow-hidden">
                    <button onclick="toggleAccordion('functional')" class="w-full flex items-center justify-between p-4 text-left">
                        <div class="flex items-center gap-3">
                            <div class="w-8 h-8 bg-purple-100 rounded-lg flex items-center justify-center flex-shrink-0"><svg class="w-4 h-4 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/></svg></div>
                            <div><p class="font-medium text-gray-900 text-sm">Funcționale</p><p class="text-xs text-gray-500">Funcționalități îmbunătățite</p></div>
                        </div>
                        <div class="flex items-center gap-3">
                            <label class="relative inline-flex cursor-pointer" onclick="event.stopPropagation()"><input type="checkbox" id="cookieFunctional" class="toggle-input sr-only peer" checked><div class="toggle-track w-9 h-5 bg-gray-300 rounded-full peer-checked:bg-gray-900"><div class="toggle-thumb absolute top-0.5 left-0.5 w-4 h-4 bg-white rounded-full shadow-sm"></div></div></label>
                            <svg id="icon-functional" class="accordion-icon w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                        </div>
                    </button>
                    <div id="acc-functional" class="accordion-content">
                        <div class="px-4 pb-4 pt-0"><p class="text-xs text-gray-500 leading-relaxed">Permit funcționalități precum chat-ul live, embed-urile video, widget-urile sociale și memorarea preferințelor tale (limbă, oraș preferat).</p>
                            <div class="mt-3 flex flex-wrap gap-1.5"><span class="px-2 py-0.5 bg-white border border-gray-200 text-xs text-gray-600 rounded">Intercom</span><span class="px-2 py-0.5 bg-white border border-gray-200 text-xs text-gray-600 rounded">YouTube</span><span class="px-2 py-0.5 bg-white border border-gray-200 text-xs text-gray-600 rounded">Spotify</span></div>
                        </div>
                    </div>
                </div>

                <!-- Marketing -->
                <div class="bg-white rounded-xl border border-gray-200 overflow-hidden">
                    <button onclick="toggleAccordion('marketing')" class="w-full flex items-center justify-between p-4 text-left">
                        <div class="flex items-center gap-3">
                            <div class="w-8 h-8 bg-orange-100 rounded-lg flex items-center justify-center flex-shrink-0"><svg class="w-4 h-4 text-orange-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5.882V19.24a1.76 1.76 0 01-3.417.592l-2.147-6.15M18 13a3 3 0 100-6M5.436 13.683A4.001 4.001 0 017 6h1.832c4.1 0 7.625-1.234 9.168-3v14c-1.543-1.766-5.067-3-9.168-3H7a3.988 3.988 0 01-1.564-.317z"/></svg></div>
                            <div><p class="font-medium text-gray-900 text-sm">Marketing</p><p class="text-xs text-gray-500">Publicitate personalizată</p></div>
                        </div>
                        <div class="flex items-center gap-3">
                            <label class="relative inline-flex cursor-pointer" onclick="event.stopPropagation()"><input type="checkbox" id="cookieMarketing" class="toggle-input sr-only peer"><div class="toggle-track w-9 h-5 bg-gray-300 rounded-full peer-checked:bg-gray-900"><div class="toggle-thumb absolute top-0.5 left-0.5 w-4 h-4 bg-white rounded-full shadow-sm"></div></div></label>
                            <svg id="icon-marketing" class="accordion-icon w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                        </div>
                    </button>
                    <div id="acc-marketing" class="accordion-content">
                        <div class="px-4 pb-4 pt-0"><p class="text-xs text-gray-500 leading-relaxed">Permit afișarea de reclame relevante bazate pe interesele tale, atât pe TICS.ro cât și pe alte platforme. Datele pot fi partajate cu partenerii publicitari.</p>
                            <div class="mt-3 flex flex-wrap gap-1.5"><span class="px-2 py-0.5 bg-white border border-gray-200 text-xs text-gray-600 rounded">Meta Pixel</span><span class="px-2 py-0.5 bg-white border border-gray-200 text-xs text-gray-600 rounded">Google Ads</span><span class="px-2 py-0.5 bg-white border border-gray-200 text-xs text-gray-600 rounded">TikTok Pixel</span></div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Footer -->
            <div class="p-6 border-t border-gray-100 bg-gray-50/50 flex-shrink-0">
                <div class="flex flex-col sm:flex-row items-center justify-between gap-3">
                    <p class="text-xs text-gray-400">Poți modifica preferințele oricând din <a href="/confidentialitate" class="text-indigo-600 hover:underline">Setări</a>.</p>
                    <div class="flex items-center gap-2 w-full sm:w-auto">
                        <button onclick="rejectAll()" class="flex-1 sm:flex-none px-4 py-2.5 border border-gray-200 text-gray-700 text-sm font-medium rounded-full hover:bg-gray-50 transition-colors">Refuză opționale</button>
                        <button onclick="savePreferences()" class="flex-1 sm:flex-none px-5 py-2.5 bg-gray-900 text-white text-sm font-medium rounded-full hover:bg-gray-800 transition-colors">Salvează preferințele</button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
const TicsCookieConsent = {
    storageKey: 'tics_cookie_consent',

    init() {
        const consent = this.getConsent();
        if (!consent) {
            setTimeout(() => this.showBanner(), 800);
        }
    },

    getConsent() {
        try { return JSON.parse(localStorage.getItem(this.storageKey)); } catch { return null; }
    },

    setConsent(consent) {
        localStorage.setItem(this.storageKey, JSON.stringify({ ...consent, timestamp: Date.now() }));
        this.hideBanner();
        this.closeModal();
        this.applyConsent(consent);
    },

    applyConsent(consent) {
        window.dispatchEvent(new CustomEvent('cookieConsentUpdated', { detail: consent }));
        if (typeof gtag !== 'undefined') {
            gtag('consent', 'update', {
                'ad_storage':             consent.marketing  ? 'granted' : 'denied',
                'ad_user_data':           consent.marketing  ? 'granted' : 'denied',
                'ad_personalization':     consent.marketing  ? 'granted' : 'denied',
                'analytics_storage':      consent.analytics  ? 'granted' : 'denied',
                'functionality_storage':  consent.functional ? 'granted' : 'denied',
                'personalization_storage':consent.functional ? 'granted' : 'denied'
            });
        }
        if (typeof fbq !== 'undefined') fbq('consent', consent.marketing ? 'grant' : 'revoke');
        if (typeof ttq !== 'undefined') {
            if (consent.marketing) { ttq.enableCookie(); ttq.grantConsent(); }
            else { ttq.disableCookie(); ttq.revokeConsent(); }
        }
    },

    showBanner() {
        const b = document.getElementById('cookieBanner');
        if (b) b.classList.remove('hidden');
    },

    hideBanner() {
        const b = document.getElementById('cookieBanner');
        if (b) b.classList.add('hidden');
    },

    openModal() {
        const m = document.getElementById('cookieModal');
        if (m) m.classList.remove('hidden');
        const consent = this.getConsent() || {};
        const a = document.getElementById('cookieAnalytics');
        const f = document.getElementById('cookieFunctional');
        const mk = document.getElementById('cookieMarketing');
        if (a) a.checked = consent.analytics !== false;
        if (f) f.checked = consent.functional !== false;
        if (mk) mk.checked = consent.marketing === true;
    },

    closeModal() {
        const m = document.getElementById('cookieModal');
        if (m) m.classList.add('hidden');
    },

    acceptAll() {
        this.setConsent({ essential: true, functional: true, analytics: true, marketing: true });
    },

    rejectAll() {
        this.setConsent({ essential: true, functional: false, analytics: false, marketing: false });
    },

    savePreferences() {
        const a = document.getElementById('cookieAnalytics');
        const f = document.getElementById('cookieFunctional');
        const mk = document.getElementById('cookieMarketing');
        this.setConsent({
            essential: true,
            functional: f ? f.checked : true,
            analytics: a ? a.checked : true,
            marketing: mk ? mk.checked : false
        });
    },

    toggleAccordion(id) {
        const el = document.getElementById('acc-' + id);
        const icon = document.getElementById('icon-' + id);
        if (el) el.classList.toggle('open');
        if (icon) icon.classList.toggle('open');
    }
};

// Global shims matching original HTML inline onclick handlers
function openCookieModal()  { TicsCookieConsent.openModal(); }
function closeCookieModal() { TicsCookieConsent.closeModal(); }
function acceptAll()        { TicsCookieConsent.acceptAll(); }
function rejectAll()        { TicsCookieConsent.rejectAll(); }
function savePreferences()  { TicsCookieConsent.savePreferences(); }
function toggleAccordion(id){ TicsCookieConsent.toggleAccordion(id); }

document.addEventListener('keydown', e => { if (e.key === 'Escape') TicsCookieConsent.closeModal(); });
document.addEventListener('DOMContentLoaded', () => TicsCookieConsent.init());
</script>
