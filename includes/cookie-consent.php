<?php
/**
 * bilete.online — Cookie banner + preferences modal (v2 design).
 *
 * Drop-in component included automatically from footer.php unless the
 * page sets $hideCookieBanner = true before the footer include.
 *
 * Storage: localStorage key `bo_cookie_consent_v1` carries
 *   { version, source, consent: {essential, analytics, personalization,
 *     marketing}, savedAt }
 *
 * Versioning: bump `consentVersion` when consent text or category list
 * changes meaningfully — the banner will re-appear automatically for
 * users with an older stored version.
 *
 * Hook points: the `bo-cookie-consent-updated` window event fires on
 * every save with the full payload in event.detail. Trackers / analytics
 * loaders listen for that and decide whether to fire.
 */
?>

<!-- Cookie banner + preferences modal (Alpine.js) -->
<div x-data="bileteOnlineCookieConsent()" x-init="init()" x-cloak class="font-sans">
    <!-- Cookie banner -->
    <section x-show="showBanner" x-transition.opacity.duration.250ms class="fixed inset-x-0 bottom-0 z-[9998] p-3 sm:p-5" aria-label="Setări cookies">
        <div class="bo-grain relative mx-auto max-w-7xl overflow-hidden rounded-[1.75rem] border-2 border-ink bg-paper shadow-deep">
            <div class="relative grid gap-5 p-5 sm:p-6 lg:grid-cols-[1fr_auto] lg:p-7">
                <div>
                    <div class="flex flex-wrap items-center gap-2">
                        <span class="inline-flex rounded-full border-2 border-vermilion px-3 py-1 text-[11px] font-mono font-bold tracking-[.18em] text-vermilion">COOKIES</span>
                        <span class="inline-flex rounded-full bg-mint px-3 py-1 text-xs font-bold text-forest">preferințe controlabile</span>
                    </div>
                    <h2 class="mt-4 font-display text-3xl font-bold leading-none text-ink sm:text-4xl">
                        Folosim cookies ca site-ul să funcționeze bine și să-ți recomandăm activități mai relevante.
                    </h2>
                    <p class="mt-3 max-w-4xl text-base leading-relaxed text-ink-soft sm:text-lg">
                        Cookies esențiale sunt necesare pentru coș, checkout, login și securitate. Cu acordul tău, putem folosi și cookies pentru analytics, personalizare și marketing. Poți accepta tot, refuza opționalele sau personaliza pe categorii.
                    </p>
                    <div class="mt-4 flex flex-wrap gap-3 text-sm">
                        <a href="/cookies" class="font-bold text-vermilion underline underline-offset-4 hover:text-vermilion-d">Politica de cookies</a>
                        <a href="/confidentialitate" class="font-bold text-vermilion underline underline-offset-4 hover:text-vermilion-d">Politica de confidențialitate</a>
                        <button type="button" @click="openPreferences()" class="font-bold text-ink underline underline-offset-4 hover:text-vermilion">Gestionează preferințele</button>
                    </div>
                </div>
                <div class="flex flex-col gap-2 sm:flex-row lg:w-[230px] lg:flex-col">
                    <button type="button" @click="acceptAll()" class="rounded-full bg-vermilion px-5 py-3.5 text-center font-bold text-paper transition hover:bg-vermilion-d">Acceptă toate</button>
                    <button type="button" @click="rejectOptional()" class="rounded-full border-2 border-ink px-5 py-3.5 text-center font-bold text-ink transition hover:bg-ink hover:text-paper">Refuză opționale</button>
                    <button type="button" @click="openPreferences()" class="rounded-full bg-paper-2 px-5 py-3.5 text-center font-bold text-ink transition hover:bg-ink hover:text-paper">Personalizează</button>
                </div>
            </div>
        </div>
    </section>

    <!-- Floating reopen button -->
    <button x-show="!showBanner && !showModal && hasSavedConsent" x-transition.opacity.duration.200ms type="button" @click="openPreferences()" class="fixed bottom-4 left-4 z-[9997] inline-flex items-center gap-2 rounded-full border-2 border-ink bg-paper px-4 py-3 text-sm font-bold text-ink shadow-ticket transition hover:bg-ink hover:text-paper" aria-label="Deschide setările de cookies">
        <span aria-hidden="true">🍪</span>
        <span class="hidden sm:inline">Cookies</span>
    </button>

    <!-- Preferences modal -->
    <div x-show="showModal" x-transition.opacity.duration.200ms class="fixed inset-0 z-[9999] grid place-items-center bg-ink/75 p-3 backdrop-blur-sm sm:p-5" role="dialog" aria-modal="true" aria-labelledby="cookie-preferences-title" @keydown.escape.window="closePreferences()">
        <div x-show="showModal" x-transition.scale.origin.center.duration.200ms @click.outside="closePreferences()" class="bo-grain relative max-h-[92vh] w-full max-w-5xl overflow-hidden rounded-[2rem] border-2 border-ink bg-paper shadow-deep">
            <div class="relative flex items-start justify-between gap-5 border-b-2 border-dashed border-ink/15 p-5 sm:p-7">
                <div>
                    <p class="font-mono text-xs tracking-[.18em] text-ink-soft">COOKIE PREFERENCES</p>
                    <h2 id="cookie-preferences-title" class="mt-2 font-display text-4xl font-bold leading-none text-ink sm:text-5xl">Setări cookies</h2>
                    <p class="mt-3 max-w-3xl text-ink-soft">Alege ce categorii permiți. Cookies esențiale rămân active pentru funcționarea platformei.</p>
                </div>
                <button type="button" @click="closePreferences()" class="grid h-11 w-11 shrink-0 place-items-center rounded-full bg-ink text-2xl font-bold text-paper transition hover:bg-vermilion" aria-label="Închide modalul de cookies">×</button>
            </div>
            <div class="relative grid max-h-[calc(92vh-170px)] overflow-auto lg:grid-cols-[280px_1fr]">
                <aside class="border-b-2 border-dashed border-ink/15 bg-paper-2/65 p-5 sm:p-6 lg:border-b-0 lg:border-r-2">
                    <p class="font-mono text-xs tracking-[.18em] text-ink-soft">SUMAR</p>
                    <div class="mt-4 space-y-3">
                        <template x-for="category in categories" :key="category.key">
                            <button type="button" @click="activeCategory = category.key" :class="activeCategory === category.key ? 'bg-ink text-paper' : 'bg-paper text-ink hover:bg-ink/5'" class="flex w-full items-center justify-between gap-3 rounded-2xl border border-ink/10 px-4 py-3 text-left text-sm font-bold transition">
                                <span x-text="category.label"></span>
                                <span class="rounded-full px-2 py-0.5 text-[11px]" :class="consent[category.key] ? 'bg-mint text-forest' : 'bg-rose text-vermilion'" x-text="category.required ? 'necesar' : (consent[category.key] ? 'activ' : 'inactiv')"></span>
                            </button>
                        </template>
                    </div>
                    <div class="mt-5 rounded-2xl border border-forest/20 bg-mint p-4">
                        <p class="font-bold text-forest">Recomandare</p>
                        <p class="mt-1 text-sm text-ink-soft">Analytics și personalizarea ne ajută să înțelegem ce pagini funcționează și să-ți afișăm activități mai relevante.</p>
                    </div>
                </aside>
                <section class="p-5 sm:p-7">
                    <template x-for="category in categories" :key="category.key">
                        <article x-show="activeCategory === category.key" x-transition.opacity.duration.150ms>
                            <div class="flex flex-col gap-5 sm:flex-row sm:items-start sm:justify-between">
                                <div>
                                    <p class="font-mono text-xs tracking-[.18em] text-vermilion" x-text="category.kicker"></p>
                                    <h3 class="mt-2 font-display text-4xl font-bold leading-none text-ink" x-text="category.label"></h3>
                                    <p class="mt-3 max-w-2xl text-lg leading-relaxed text-ink-soft" x-text="category.description"></p>
                                </div>
                                <label class="flex shrink-0 items-center gap-3 rounded-full bg-paper-2 px-4 py-3 font-bold">
                                    <span x-text="category.required ? 'Mereu activ' : (consent[category.key] ? 'Activ' : 'Inactiv')"></span>
                                    <input type="checkbox" class="hidden" x-model="consent[category.key]" :disabled="category.required">
                                    <span class="bo-toggle block"></span>
                                </label>
                            </div>
                            <div class="mt-6 grid gap-4 md:grid-cols-2">
                                <div class="rounded-3xl border border-ink/10 bg-paper-2 p-5">
                                    <p class="font-mono text-xs tracking-[.16em] text-ink-soft">EXEMPLE</p>
                                    <ul class="mt-3 space-y-2 text-ink-soft">
                                        <template x-for="item in category.examples" :key="item">
                                            <li class="flex gap-2">
                                                <span class="mt-2 h-1.5 w-1.5 shrink-0 rounded-full bg-vermilion"></span>
                                                <span x-text="item"></span>
                                            </li>
                                        </template>
                                    </ul>
                                </div>
                                <div class="rounded-3xl border border-ink/10 bg-paper-2 p-5">
                                    <p class="font-mono text-xs tracking-[.16em] text-ink-soft">SERVICII POSIBILE</p>
                                    <div class="mt-3 flex flex-wrap gap-2">
                                        <template x-for="service in category.services" :key="service">
                                            <span class="rounded-full bg-paper px-3 py-1 text-xs font-bold text-ink" x-text="service"></span>
                                        </template>
                                    </div>
                                </div>
                            </div>
                            <div class="mt-6 rounded-3xl border-2 border-ink bg-ink p-5 text-paper">
                                <p class="font-mono text-xs tracking-[.18em] text-paper/45">DETALIU TEHNIC</p>
                                <p class="mt-2 text-paper/70" x-text="category.technical"></p>
                            </div>
                        </article>
                    </template>
                </section>
            </div>
            <div class="relative flex flex-col gap-3 border-t-2 border-dashed border-ink/15 p-5 sm:flex-row sm:items-center sm:justify-between sm:p-7">
                <p class="text-sm text-ink-soft">Versiune consimțământ: <strong class="text-ink" x-text="consentVersion"></strong></p>
                <div class="flex flex-col gap-2 sm:flex-row">
                    <button type="button" @click="rejectOptional()" class="rounded-full border-2 border-ink px-5 py-3 font-bold text-ink transition hover:bg-ink hover:text-paper">Refuză opționale</button>
                    <button type="button" @click="savePreferences()" class="rounded-full bg-forest px-5 py-3 font-bold text-paper transition hover:bg-ink">Salvează preferințele</button>
                    <button type="button" @click="acceptAll()" class="rounded-full bg-vermilion px-5 py-3 font-bold text-paper transition hover:bg-vermilion-d">Acceptă toate</button>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
    .bo-grain::after{
        content:"";position:absolute;inset:0;pointer-events:none;opacity:.055;
        background-image:url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='160' height='160'%3E%3Cfilter id='n'%3E%3CfeTurbulence type='fractalNoise' baseFrequency='0.85' numOctaves='2' stitchTiles='stitch'/%3E%3C/filter%3E%3Crect width='100%25' height='100%25' filter='url(%23n)'/%3E%3C/svg%3E");
        mix-blend-mode:multiply;
    }
    .bo-toggle{position:relative;width:3.35rem;height:1.8rem;border-radius:9999px;background:#E2D7BF;border:2px solid rgba(27,23,20,.18);transition:.2s;flex:0 0 auto}
    .bo-toggle::after{content:"";position:absolute;top:2px;left:2px;width:1.25rem;height:1.25rem;border-radius:9999px;background:#1B1714;transition:.2s}
    input:checked + .bo-toggle{background:#1E4A3D}
    input:checked + .bo-toggle::after{transform:translateX(1.55rem);background:#F4EFE3}
    input:disabled + .bo-toggle{opacity:.65;cursor:not-allowed}
</style>

<script>
function bileteOnlineCookieConsent() {
    return {
        storageKey: 'bo_cookie_consent_v1',
        consentVersion: '2026-05-26',
        showBanner: false,
        showModal: false,
        hasSavedConsent: false,
        activeCategory: 'essential',
        consent: { essential: true, analytics: false, personalization: false, marketing: false },
        categories: [
            { key:'essential', label:'Esențiale', kicker:'NECESARE', required:true,
                description:'Aceste cookies sunt necesare pentru funcționarea platformei și nu pot fi dezactivate din acest panou.',
                examples:['păstrarea produselor în coș','checkout și procesarea comenzii','login, sesiune și securitate','memorarea setărilor de consimțământ'],
                services:['session','cart','checkout','security'],
                technical:'Aceste cookies nu sunt folosite pentru marketing comportamental. Sunt necesare pentru furnizarea serviciului cerut de utilizator.'
            },
            { key:'analytics', label:'Analytics', kicker:'MĂSURARE', required:false,
                description:'Ne ajută să înțelegem cum este folosit site-ul: pagini vizitate, performanță, erori și conversii agregate.',
                examples:['măsurarea traficului pe pagini','înțelegerea pașilor din checkout','detectarea erorilor și a paginilor lente','rapoarte agregate despre activități populare'],
                services:['Google Analytics','server analytics','conversion events'],
                technical:'Activează scripturile de măsurare doar după consimțământ. Evită încărcarea pixelilor analytics înainte de accept.'
            },
            { key:'personalization', label:'Personalizare', kicker:'RECOMANDĂRI', required:false,
                description:'Permite folosirea preferințelor și comportamentului de navigare pentru recomandări mai relevante de activități.',
                examples:['recomandări după oraș și categorii vizitate','activități similare cu cele cumpărate','memorarea filtrelor preferate','experiență adaptată pentru familie/copii'],
                services:['recommendation engine','saved filters','profile signals'],
                technical:'Separă personalizarea on-site de marketing. Aici se activează recomandări și preferințe locale, nu reclame externe.'
            },
            { key:'marketing', label:'Marketing', kicker:'PIXELI & CAMPANII', required:false,
                description:'Permite folosirea pixelilor și identificatorilor pentru campanii, remarketing, măsurarea reclamelor și audiențe.',
                examples:['remarketing pentru activități vizualizate','măsurarea campaniilor Meta, Google, TikTok','audiențe personalizate','optimizarea conversiilor din reclame'],
                services:['Meta Pixel','Google Ads','TikTok Pixel','affiliate tracking'],
                technical:'Scripturile de marketing trebuie încărcate numai după accept explicit. Respectă consimțământul pentru fiecare tenant / white-label.'
            }
        ],
        init() {
            const saved = this.readSavedConsent();
            if (saved && saved.version === this.consentVersion && saved.consent) {
                this.consent = { ...this.consent, ...saved.consent, essential: true };
                this.hasSavedConsent = true;
                this.showBanner = false;
                this.applyCookieConsent();
                return;
            }
            this.showBanner = true;
        },
        readSavedConsent() {
            try { return JSON.parse(localStorage.getItem(this.storageKey)); }
            catch (e) { return null; }
        },
        persistConsent(source) {
            const payload = {
                version: this.consentVersion,
                source: source || 'preferences',
                consent: { ...this.consent, essential: true },
                savedAt: new Date().toISOString()
            };
            localStorage.setItem(this.storageKey, JSON.stringify(payload));
            this.hasSavedConsent = true;
            this.showBanner = false;
            this.showModal = false;
            this.applyCookieConsent();
            window.dispatchEvent(new CustomEvent('bo-cookie-consent-updated', { detail: payload }));
        },
        acceptAll() {
            this.consent = { essential: true, analytics: true, personalization: true, marketing: true };
            this.persistConsent('accept_all');
        },
        rejectOptional() {
            this.consent = { essential: true, analytics: false, personalization: false, marketing: false };
            this.persistConsent('reject_optional');
        },
        savePreferences() {
            this.consent.essential = true;
            this.persistConsent('save_preferences');
        },
        openPreferences() {
            this.showModal = true;
            this.showBanner = false;
        },
        closePreferences() {
            this.showModal = false;
            if (!this.hasSavedConsent) this.showBanner = true;
        },
        applyCookieConsent() {
            // Real loaders are gated client-side by tracking.js / pixel
            // bootstrapping code listening for `bo-cookie-consent-updated`.
            // Nothing to do here beyond firing the event from persistConsent().
        }
    }
}
</script>
