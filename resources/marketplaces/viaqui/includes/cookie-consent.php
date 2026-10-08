<?php
/**
 * viaqui.com — Cookie banner + preferences modal (v2 design).
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
 *
 * Texts go through v2_t() / v2_te() (includes/v2/i18n.php, loaded here because the pages that still use this banner
 * do not load the v2 helpers). The texts of the Alpine component are printed into the script as JSON by $ccJs().
 */
require_once __DIR__ . '/v2/i18n.php';

/** A translated text as a JavaScript string literal, safe inside the inline script. */
$ccJs = static fn (string $text): string => (string) json_encode($text, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
?>

<!-- Cookie banner + preferences modal (Alpine.js) -->
<div x-data="bileteOnlineCookieConsent()" x-init="init()" x-cloak class="font-sans">
    <!-- Cookie banner -->
    <section x-show="showBanner" x-transition.opacity.duration.250ms class="fixed inset-x-0 bottom-0 z-[9998] p-3 sm:p-5" aria-label="<?= v2_te('Cookie settings') ?>">
        <div class="bo-grain relative mx-auto max-w-7xl overflow-hidden rounded-[1.75rem] border-2 border-ink bg-paper shadow-deep">
            <div class="relative grid gap-5 p-5 sm:p-6 lg:grid-cols-[1fr_auto] lg:p-7">
                <div>
                    <div class="flex flex-wrap items-center gap-2">
                        <span class="inline-flex rounded-full border-2 border-vermilion px-3 py-1 text-[11px] font-mono font-bold tracking-[.18em] text-vermilion" style="text-transform:uppercase"><?= v2_te('Cookies') ?></span>
                        <span class="inline-flex rounded-full bg-mint px-3 py-1 text-xs font-bold text-forest"><?= v2_te('you choose') ?></span>
                    </div>
                    <h2 class="mt-4 font-display text-3xl font-bold leading-none text-ink sm:text-4xl">
                        <?= v2_te('We use cookies to make the site work well and to recommend more relevant activities.') ?>
                    </h2>
                    <p class="mt-3 max-w-4xl text-base leading-relaxed text-ink-soft sm:text-lg">
                        <?= v2_te('Essential cookies are needed for the cart, checkout, sign-in and security. With your consent, we can also use cookies for analytics, personalisation and marketing. You can accept all, reject the optional ones or choose by category.') ?>
                    </p>
                    <div class="mt-4 flex flex-wrap gap-3 text-sm">
                        <a href="/cookies" class="font-bold text-vermilion underline underline-offset-4 hover:text-vermilion-d"><?= v2_te('Cookie policy') ?></a>
                        <a href="/privacy" class="font-bold text-vermilion underline underline-offset-4 hover:text-vermilion-d"><?= v2_te('Privacy policy') ?></a>
                        <button type="button" @click="openPreferences()" class="font-bold text-ink underline underline-offset-4 hover:text-vermilion"><?= v2_te('Manage preferences') ?></button>
                    </div>
                </div>
                <div class="flex flex-col gap-2 sm:flex-row lg:w-[230px] lg:flex-col">
                    <button type="button" @click="acceptAll()" class="rounded-full bg-vermilion px-5 py-3.5 text-center font-bold text-paper transition hover:bg-vermilion-d"><?= v2_te('Accept all') ?></button>
                    <button type="button" @click="rejectOptional()" class="rounded-full border-2 border-ink px-5 py-3.5 text-center font-bold text-ink transition hover:bg-ink hover:text-paper"><?= v2_te('Reject optional') ?></button>
                    <button type="button" @click="openPreferences()" class="rounded-full bg-paper-2 px-5 py-3.5 text-center font-bold text-ink transition hover:bg-ink hover:text-paper"><?= v2_te('Customise') ?></button>
                </div>
            </div>
        </div>
    </section>

    <!-- Floating reopen button -->
    <button x-show="!showBanner && !showModal && hasSavedConsent" x-transition.opacity.duration.200ms type="button" @click="openPreferences()" class="fixed bottom-4 left-4 z-[9997] inline-flex items-center gap-2 rounded-full border-2 border-ink bg-paper px-4 py-3 text-sm font-bold text-ink shadow-ticket transition hover:bg-ink hover:text-paper" aria-label="<?= v2_te('Open cookie settings') ?>">
        <svg class="w-4 h-4" viewBox="0 0 256 256" fill="currentColor" aria-hidden="true"><path d="M164.49,163.51a12,12,0,1,1-17,0A12,12,0,0,1,164.49,163.51Zm-81-8a12,12,0,1,0,17,0A12,12,0,0,0,83.51,155.51Zm9-39a12,12,0,1,0-17,0A12,12,0,0,0,92.49,116.49Zm48-1a12,12,0,1,0,0,17A12,12,0,0,0,140.49,115.51ZM232,128A104,104,0,1,1,128,24a8,8,0,0,1,8,8,40,40,0,0,0,40,40,8,8,0,0,1,8,8,40,40,0,0,0,40,40A8,8,0,0,1,232,128Zm-16.31,7.39A56.13,56.13,0,0,1,168.5,87.5a56.13,56.13,0,0,1-47.89-47.19,88,88,0,1,0,95.08,95.08Z"/></svg>
        <span class="hidden sm:inline"><?= v2_te('Cookies') ?></span>
    </button>

    <!-- Preferences modal -->
    <div x-show="showModal" x-transition.opacity.duration.200ms class="fixed inset-0 z-[9999] grid place-items-center bg-ink/75 p-3 backdrop-blur-sm sm:p-5" role="dialog" aria-modal="true" aria-labelledby="cookie-preferences-title" @keydown.escape.window="closePreferences()">
        <div x-show="showModal" x-transition.scale.origin.center.duration.200ms @click.outside="closePreferences()" class="bo-grain relative max-h-[92vh] w-full max-w-5xl overflow-hidden rounded-[2rem] border-2 border-ink bg-paper shadow-deep">
            <div class="relative flex items-start justify-between gap-5 border-b-2 border-dashed border-ink/15 p-5 sm:p-7">
                <div>
                    <p class="font-mono text-xs tracking-[.18em] text-ink-soft" style="text-transform:uppercase"><?= v2_te('Cookie preferences') ?></p>
                    <h2 id="cookie-preferences-title" class="mt-2 font-display text-4xl font-bold leading-none text-ink sm:text-5xl"><?= v2_te('Cookie settings') ?></h2>
                    <p class="mt-3 max-w-3xl text-ink-soft"><?= v2_te('Choose which categories you allow. Essential cookies stay on so that the platform works.') ?></p>
                </div>
                <button type="button" @click="closePreferences()" class="grid h-11 w-11 shrink-0 place-items-center rounded-full bg-ink text-2xl font-bold text-paper transition hover:bg-vermilion" aria-label="<?= v2_te('Close cookie settings') ?>">×</button>
            </div>
            <div class="relative grid max-h-[calc(92vh-170px)] overflow-auto lg:grid-cols-[280px_1fr]">
                <aside class="border-b-2 border-dashed border-ink/15 bg-paper-2/65 p-5 sm:p-6 lg:border-b-0 lg:border-r-2">
                    <p class="font-mono text-xs tracking-[.18em] text-ink-soft" style="text-transform:uppercase"><?= v2_te('Summary') ?></p>
                    <div class="mt-4 space-y-3">
                        <template x-for="category in categories" :key="category.key">
                            <button type="button" @click="activeCategory = category.key" :class="activeCategory === category.key ? 'bg-ink text-paper' : 'bg-paper text-ink hover:bg-ink/5'" class="flex w-full items-center justify-between gap-3 rounded-2xl border border-ink/10 px-4 py-3 text-left text-sm font-bold transition">
                                <span x-text="category.label"></span>
                                <span class="rounded-full px-2 py-0.5 text-[11px]" :class="consent[category.key] ? 'bg-mint text-forest' : 'bg-rose text-vermilion'" x-text="category.required ? txt.required : (consent[category.key] ? txt.on : txt.off)"></span>
                            </button>
                        </template>
                    </div>
                    <div class="mt-5 rounded-2xl border border-forest/20 bg-mint p-4">
                        <p class="font-bold text-forest"><?= v2_te('Our suggestion') ?></p>
                        <p class="mt-1 text-sm text-ink-soft"><?= v2_te('Analytics and personalisation help us understand which pages work and show you more relevant activities.') ?></p>
                    </div>
                </aside>
                <section class="p-5 sm:p-7">
                    <template x-for="category in categories" :key="category.key">
                        <article x-show="activeCategory === category.key" x-transition.opacity.duration.150ms>
                            <div class="flex flex-col gap-5 sm:flex-row sm:items-start sm:justify-between">
                                <div>
                                    <p class="font-mono text-xs tracking-[.18em] text-vermilion" style="text-transform:uppercase" x-text="category.kicker"></p>
                                    <h3 class="mt-2 font-display text-4xl font-bold leading-none text-ink" x-text="category.label"></h3>
                                    <p class="mt-3 max-w-2xl text-lg leading-relaxed text-ink-soft" x-text="category.description"></p>
                                </div>
                                <label class="flex shrink-0 items-center gap-3 rounded-full bg-paper-2 px-4 py-3 font-bold">
                                    <span x-text="category.required ? txt.alwaysOn : (consent[category.key] ? txt.switchOn : txt.switchOff)"></span>
                                    <input type="checkbox" class="hidden" x-model="consent[category.key]" :disabled="category.required">
                                    <span class="bo-toggle block"></span>
                                </label>
                            </div>
                            <div class="mt-6 grid gap-4 md:grid-cols-2">
                                <div class="rounded-3xl border border-ink/10 bg-paper-2 p-5">
                                    <p class="font-mono text-xs tracking-[.16em] text-ink-soft" style="text-transform:uppercase"><?= v2_te('Examples') ?></p>
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
                                    <p class="font-mono text-xs tracking-[.16em] text-ink-soft" style="text-transform:uppercase"><?= v2_te('Possible services') ?></p>
                                    <div class="mt-3 flex flex-wrap gap-2">
                                        <template x-for="service in category.services" :key="service">
                                            <span class="rounded-full bg-paper px-3 py-1 text-xs font-bold text-ink" x-text="service"></span>
                                        </template>
                                    </div>
                                </div>
                            </div>
                            <div class="mt-6 rounded-3xl border-2 border-ink bg-ink p-5 text-paper">
                                <p class="font-mono text-xs tracking-[.18em] text-paper/45" style="text-transform:uppercase"><?= v2_te('Technical detail') ?></p>
                                <p class="mt-2 text-paper/70" x-text="category.technical"></p>
                            </div>
                        </article>
                    </template>
                </section>
            </div>
            <div class="relative flex flex-col gap-3 border-t-2 border-dashed border-ink/15 p-5 sm:flex-row sm:items-center sm:justify-between sm:p-7">
                <p class="text-sm text-ink-soft"><?= v2_t('Consent version: {version}', ['version' => '<strong class="text-ink" x-text="consentVersion"></strong>']) ?></p>
                <div class="flex flex-col gap-2 sm:flex-row">
                    <button type="button" @click="rejectOptional()" class="rounded-full border-2 border-ink px-5 py-3 font-bold text-ink transition hover:bg-ink hover:text-paper"><?= v2_te('Reject optional') ?></button>
                    <button type="button" @click="savePreferences()" class="rounded-full bg-forest px-5 py-3 font-bold text-paper transition hover:bg-ink"><?= v2_te('Save preferences') ?></button>
                    <button type="button" @click="acceptAll()" class="rounded-full bg-vermilion px-5 py-3 font-bold text-paper transition hover:bg-vermilion-d"><?= v2_te('Accept all') ?></button>
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
        txt: {
            required: <?= $ccJs(v2_t('required')) ?>, on: <?= $ccJs(v2_t('on')) ?>, off: <?= $ccJs(v2_t('off')) ?>,
            alwaysOn: <?= $ccJs(v2_t('Always on')) ?>, switchOn: <?= $ccJs(v2_t('On')) ?>, switchOff: <?= $ccJs(v2_t('Off')) ?>
        },
        categories: [
            { key:'essential', label:<?= $ccJs(v2_t('Essential')) ?>, kicker:<?= $ccJs(v2_t('Necessary')) ?>, required:true,
                description:<?= $ccJs(v2_t('These cookies are needed for the platform to work and cannot be turned off from this panel.')) ?>,
                examples:[<?= $ccJs(v2_t('keeping products in the cart')) ?>, <?= $ccJs(v2_t('checkout and order processing')) ?>, <?= $ccJs(v2_t('sign-in, session and security')) ?>, <?= $ccJs(v2_t('remembering your consent settings')) ?>, <?= $ccJs(v2_t('strictly aggregate audience measurement (first-party)')) ?>],
                services:[<?= $ccJs(v2_t('session')) ?>, <?= $ccJs(v2_t('cart')) ?>, <?= $ccJs(v2_t('checkout')) ?>, <?= $ccJs(v2_t('security')) ?>, <?= $ccJs(v2_t('server analytics (aggregate)')) ?>],
                technical:<?= $ccJs(v2_t('They are not used for behavioural marketing. Here we include only first-party audience measurement that is strictly aggregate: no sharing with third parties, no cross-site tracking, with anonymised IP and limited retention, in line with the consent exemption for audience measurement (CNIL guidance).')) ?>
            },
            { key:'analytics', label:<?= $ccJs(v2_t('Analytics')) ?>, kicker:<?= $ccJs(v2_t('Measurement')) ?>, required:false,
                description:<?= $ccJs(v2_t('They help us understand how the site is used: pages visited, performance, errors and aggregate conversions, through third-party tools.')) ?>,
                examples:[<?= $ccJs(v2_t('measuring page traffic with third-party tools')) ?>, <?= $ccJs(v2_t('understanding the steps of the checkout')) ?>, <?= $ccJs(v2_t('detecting errors and slow pages')) ?>, <?= $ccJs(v2_t('reports on popular activities')) ?>],
                services:['Google Analytics', <?= $ccJs(v2_t('conversion events')) ?>],
                technical:<?= $ccJs(v2_t('Third-party measurement scripts are turned on only after consent. Server-side first-party measurement, strictly aggregate, stays in the Essential category under the audience measurement exemption.')) ?>
            },
            { key:'personalization', label:<?= $ccJs(v2_t('Personalisation')) ?>, kicker:<?= $ccJs(v2_t('Recommendations')) ?>, required:false,
                description:<?= $ccJs(v2_t('Allows your preferences and browsing behaviour to be used for more relevant activity recommendations.')) ?>,
                examples:[<?= $ccJs(v2_t('recommendations based on the city and categories you visited')) ?>, <?= $ccJs(v2_t('activities similar to the ones you bought')) ?>, <?= $ccJs(v2_t('remembering your preferred filters')) ?>, <?= $ccJs(v2_t('an experience adapted for families and children')) ?>],
                services:[<?= $ccJs(v2_t('recommendation engine')) ?>, <?= $ccJs(v2_t('saved filters')) ?>, <?= $ccJs(v2_t('profile signals')) ?>],
                technical:<?= $ccJs(v2_t('On-site personalisation is kept separate from marketing. This turns on local recommendations and preferences, not external ads.')) ?>
            },
            { key:'marketing', label:<?= $ccJs(v2_t('Marketing')) ?>, kicker:<?= $ccJs(v2_t('Pixels and campaigns')) ?>, required:false,
                description:<?= $ccJs(v2_t('Allows pixels and identifiers to be used for campaigns, remarketing, ad measurement and audiences.')) ?>,
                examples:[<?= $ccJs(v2_t('remarketing for activities you viewed')) ?>, <?= $ccJs(v2_t('measuring Meta, Google and TikTok campaigns')) ?>, <?= $ccJs(v2_t('custom audiences')) ?>, <?= $ccJs(v2_t('optimising conversions from ads')) ?>],
                services:['Meta Pixel', 'Google Ads', 'TikTok Pixel', <?= $ccJs(v2_t('affiliate tracking')) ?>],
                technical:<?= $ccJs(v2_t('Marketing scripts must be loaded only after explicit acceptance. Consent is respected for each tenant and white-label site.')) ?>
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
