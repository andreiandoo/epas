<?php
/**
 * Cookie Consent Banner & Modal
 *
 * Features:
 * - GDPR-compliant cookie consent
 * - Customizable cookie categories
 * - Persistent preferences via localStorage
 * - Smooth animations
 *
 * Usage: Include this file at the end of your page, before </body>
 * <?php include 'includes/cookie-consent.php'; ?>
 */
?>

<!-- Cookie Banner -->
<div id="cookieBanner" class="fixed bottom-0 left-0 right-0 bg-white border-t border-gray-200 shadow-[0_-4px_30px_rgba(0,0,0,0.1)] p-5 z-[9999] transform translate-y-full transition-transform duration-400 ease-out">
    <div class="flex flex-col items-center gap-6 mx-auto max-w-7xl lg:flex-row">
        <div class="flex items-center justify-center flex-shrink-0 w-12 h-12 text-white mobile:hidden bg-gradient-to-br from-primary to-primary-light rounded-xl">
            <svg class="w-6 h-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <circle cx="12" cy="12" r="10"/>
                <circle cx="12" cy="12" r="4"/>
                <line x1="4.93" y1="4.93" x2="9.17" y2="9.17"/>
                <line x1="14.83" y1="14.83" x2="19.07" y2="19.07"/>
                <line x1="14.83" y1="9.17" x2="19.07" y2="4.93"/>
                <line x1="4.93" y1="19.07" x2="9.17" y2="14.83"/>
            </svg>
        </div>
        <div class="flex-1 text-center mobile:hidden lg:text-left">
            <h3 class="mb-1 text-base font-bold text-secondary">Folosim cookie-uri</h3>
            <p class="text-sm leading-relaxed text-muted">
                Utilizăm cookie-uri pentru a-ți oferi cea mai bună experiență pe site.
                Află mai multe în <a href="/cookies" class="font-medium text-primary">Politica de cookies</a>.
            </p>
        </div>
        <div class="items-center hidden mobile:flex gap-x-4">
            <div class="flex items-center justify-center flex-shrink-0 w-12 h-12 text-white bg-gradient-to-br from-primary to-primary-light rounded-xl">
                <svg class="w-6 h-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <circle cx="12" cy="12" r="10"/>
                    <circle cx="12" cy="12" r="4"/>
                    <line x1="4.93" y1="4.93" x2="9.17" y2="9.17"/>
                    <line x1="14.83" y1="14.83" x2="19.07" y2="19.07"/>
                    <line x1="14.83" y1="9.17" x2="19.07" y2="4.93"/>
                    <line x1="4.93" y1="19.07" x2="9.17" y2="14.83"/>
                </svg>
            </div>
            <div class="flex-1 text-left lg:text-left">
                <h3 class="mb-1 text-base font-bold text-secondary">Folosim cookie-uri</h3>
                <p class="text-sm leading-relaxed text-muted">
                    Utilizăm cookie-uri pentru a-ți oferi cea mai bună experiență pe site.
                    Află mai multe în <a href="/cookies" class="font-medium text-primary">Politica de cookies</a>.
                </p>
            </div>
        </div>
        <div class="flex flex-wrap items-center justify-center flex-shrink-0 gap-3">
            <button onclick="CookieConsent.openSettings()" class="px-6 py-3 text-sm font-semibold transition-all bg-transparent border border-gray-200 rounded-xl text-muted hover:border-gray-300 hover:text-secondary mobile:px-4 mobile:text-xs" name="customize-cookies">
                Personalizează
            </button>
            <button onclick="CookieConsent.rejectAll()" class="px-6 py-3 text-sm font-semibold text-gray-600 transition-all bg-gray-100 border-none rounded-xl hover:bg-gray-200 mobile:px-4 mobile:text-xs" name="reject-cookies">
                Refuză
            </button>
            <button onclick="CookieConsent.acceptAll()" class="px-6 py-3 rounded-xl text-sm font-semibold bg-gradient-to-br from-primary to-primary-light border-none text-white hover:-translate-y-0.5 hover:shadow-lg hover:shadow-primary/30 transition-all" name="accept-cookies">
                Acceptă toate
            </button>
        </div>
    </div>
</div>

<!-- Cookie Settings Modal -->
<div id="cookieModal" class="fixed inset-0 bg-slate-900/60 backdrop-blur-sm z-[9998] hidden items-center justify-center p-6 opacity-0 transition-opacity duration-300">
    <div class="bg-white rounded-3xl max-w-xl w-full max-h-[90vh] overflow-y-auto shadow-2xl transform scale-95 transition-transform duration-300" id="cookieModalContent">
        <!-- Header -->
        <div class="px-8 pt-8 text-center">
            <div class="flex items-center justify-center w-16 h-16 mx-auto mb-5 bg-gradient-to-br from-primary to-primary-light rounded-2xl">
                <svg class="w-8 h-8 text-white" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <circle cx="12" cy="12" r="10"/>
                    <circle cx="12" cy="12" r="4"/>
                    <line x1="4.93" y1="4.93" x2="9.17" y2="9.17"/>
                    <line x1="14.83" y1="14.83" x2="19.07" y2="19.07"/>
                </svg>
            </div>
            <h2 class="mb-2 text-2xl font-extrabold text-secondary">Setări Cookie-uri</h2>
            <p class="text-[15px] text-muted leading-relaxed">Alege ce tipuri de cookie-uri accepți. Poți modifica aceste preferințe oricând.</p>
        </div>

        <!-- Body -->
        <div class="p-6 space-y-3">
            <!-- Essential -->
            <div class="p-4 transition-colors border border-gray-200 bg-gray-50 rounded-xl hover:border-gray-300">
                <div class="flex items-center justify-between">
                    <div class="flex items-center gap-3">
                        <div class="flex items-center justify-center w-10 h-10 text-white rounded-xl bg-gradient-to-br from-emerald-500 to-emerald-600">
                            <svg class="w-5 h-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/>
                            </svg>
                        </div>
                        <div>
                            <div class="text-[15px] font-bold text-secondary">Cookie-uri esențiale</div>
                            <div class="text-[11px] text-emerald-500 font-semibold uppercase tracking-wide">Întotdeauna active</div>
                        </div>
                    </div>
                    <label class="cookie-toggle">
                        <input type="checkbox" checked disabled>
                        <span class="cookie-toggle-slider"></span>
                    </label>
                </div>
                <p class="text-[13px] text-muted mt-3 pl-[52px] leading-relaxed">Necesare pentru funcționarea de bază a site-ului. Fără ele, site-ul nu poate funcționa corect.</p>
            </div>

            <!-- Functional -->
            <div class="p-4 transition-colors border border-gray-200 bg-gray-50 rounded-xl hover:border-gray-300">
                <div class="flex items-center justify-between">
                    <div class="flex items-center gap-3">
                        <div class="flex items-center justify-center w-10 h-10 text-white rounded-xl bg-gradient-to-br from-blue-500 to-blue-600">
                            <svg class="w-5 h-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <circle cx="12" cy="12" r="3"/>
                                <path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1 0 2.83 2 2 0 0 1-2.83 0l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-2 2 2 2 0 0 1-2-2v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83 0 2 2 0 0 1 0-2.83l.06-.06a1.65 1.65 0 0 0 .33-1.82 1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1-2-2 2 2 0 0 1 2-2h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 0-2.83 2 2 0 0 1 2.83 0l.06.06a1.65 1.65 0 0 0 1.82.33H9a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 2-2 2 2 0 0 1 2 2v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 0 2 2 0 0 1 0 2.83l-.06.06a1.65 1.65 0 0 0-.33 1.82V9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 2 2 2 2 0 0 1-2 2h-.09a1.65 1.65 0 0 0-1.51 1z"/>
                            </svg>
                        </div>
                        <div>
                            <div class="text-[15px] font-bold text-secondary">Cookie-uri funcționale</div>
                        </div>
                    </div>
                    <label class="cookie-toggle">
                        <input type="checkbox" id="cookieFunctional" checked>
                        <span class="cookie-toggle-slider"></span>
                    </label>
                </div>
                <p class="text-[13px] text-muted mt-3 pl-[52px] leading-relaxed">Permit funcționalități îmbunătățite și personalizare, cum ar fi preferințele de limbă.</p>
            </div>

            <!-- Analytics -->
            <div class="p-4 transition-colors border border-gray-200 bg-gray-50 rounded-xl hover:border-gray-300">
                <div class="flex items-center justify-between">
                    <div class="flex items-center gap-3">
                        <div class="flex items-center justify-center w-10 h-10 text-white rounded-xl bg-gradient-to-br from-amber-500 to-amber-600">
                            <svg class="w-5 h-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <line x1="18" y1="20" x2="18" y2="10"/>
                                <line x1="12" y1="20" x2="12" y2="4"/>
                                <line x1="6" y1="20" x2="6" y2="14"/>
                            </svg>
                        </div>
                        <div>
                            <div class="text-[15px] font-bold text-secondary">Cookie-uri analitice</div>
                        </div>
                    </div>
                    <label class="cookie-toggle">
                        <input type="checkbox" id="cookieAnalytics" checked>
                        <span class="cookie-toggle-slider"></span>
                    </label>
                </div>
                <p class="text-[13px] text-muted mt-3 pl-[52px] leading-relaxed">Ne ajută să înțelegem cum folosești site-ul pentru a-l îmbunătăți continuu.</p>
            </div>

            <!-- Marketing -->
            <div class="p-4 transition-colors border border-gray-200 bg-gray-50 rounded-xl hover:border-gray-300">
                <div class="flex items-center justify-between">
                    <div class="flex items-center gap-3">
                        <div class="flex items-center justify-center w-10 h-10 text-white rounded-xl bg-gradient-to-br from-primary to-primary-light">
                            <svg class="w-5 h-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/>
                            </svg>
                        </div>
                        <div>
                            <div class="text-[15px] font-bold text-secondary">Cookie-uri de marketing</div>
                        </div>
                    </div>
                    <label class="cookie-toggle">
                        <input type="checkbox" id="cookieMarketing">
                        <span class="cookie-toggle-slider"></span>
                    </label>
                </div>
                <p class="text-[13px] text-muted mt-3 pl-[52px] leading-relaxed">Folosite pentru a-ți afișa reclame relevante pe alte site-uri.</p>
            </div>
        </div>

        <!-- Footer -->
        <div class="px-8 pb-8 space-y-3">
            <button onclick="CookieConsent.acceptAll()" class="w-full py-4 rounded-xl text-[15px] font-semibold bg-gradient-to-br from-primary to-primary-light text-white flex items-center justify-center gap-2 hover:-translate-y-0.5 hover:shadow-lg hover:shadow-primary/35 transition-all" name="accept-all-cookies">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="w-[18px] h-[18px]">
                    <polyline points="20 6 9 17 4 12"/>
                </svg>
                Acceptă toate
            </button>
            <button onclick="CookieConsent.savePreferences()" class="w-full py-4 rounded-xl text-[15px] font-semibold bg-gray-100 text-gray-600 hover:bg-gray-200 transition-all" name="save-cookie-preferences">
                Salvează preferințele
            </button>
            <button onclick="CookieConsent.rejectAll()" class="w-full py-4 rounded-xl text-[15px] font-semibold bg-transparent border border-gray-200 text-muted hover:border-gray-300 hover:text-secondary transition-all" name="reject-all-cookies">
                Refuză toate opționalele
            </button>
            <div class="mt-2 text-center">
                <a href="/cookies" class="text-[13px] text-muted hover:text-primary transition-colors">Citește politica completă de cookies →</a>
            </div>
        </div>
    </div>
</div>

<script defer src="<?= asset('assets/js/cookie-consent.js') ?>"></script>
