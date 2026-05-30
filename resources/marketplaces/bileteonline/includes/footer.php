<?php
/**
 * bilete.online — site footer (v3 design)
 *
 * Closes <main> (unless $skipMainTag was set in header.php), renders the
 * footer markup, loads page-bottom scripts, then closes </body></html>.
 *
 * Dynamic data:
 *   - Categories (marquee + column) pulled live from the events API.
 *   - Cities (column + newsletter select) pulled live from the API.
 *   - Newsletter form posts to /newsletter/subscribe via BileteOnlineAPI.
 *   - "Popular searches" derived from real categories + intent hubs.
 *
 * Variables a page can set BEFORE include:
 *   $skipMainTag      — true if the page handles its own <main>
 *   $footerCategories — override default categories list (each ['label','href'])
 *   $footerCities     — override default cities list (each ['label','href'])
 *   $footerExtraJs    — array of extra JS files to load (relative paths)
 *   $extraBodyEnd     — raw HTML to inject just before </body>
 *   $hideCookieBanner — true to suppress the cookie consent module
 */

if (!defined('BILETEONLINE_ROOT')) {
    require_once __DIR__ . '/config.php';
}
if (! function_exists('api_cached')) {
    require_once __DIR__ . '/api.php';
}
require_once __DIR__ . '/nav-helpers.php';

$skipMainTag = $skipMainTag ?? false;
$footerExtraJs = $footerExtraJs ?? [];
$extraBodyEnd = $extraBodyEnd ?? '';
$hideCookieBanner = $hideCookieBanner ?? false;

// --- Categories (live, with sensible fallback) ---------------------------
if (! isset($footerCategories)) {
    $resp = api_cached('footer_event_top_categories', fn () => api_get('/events/categories', ['all' => 1, 'parents_only' => 1]), 600);
    $rows = $resp['data']['categories'] ?? [];
    $footerCategories = [];
    foreach ((is_array($rows) ? $rows : []) as $c) {
        $footerCategories[] = [
            'label' => $c['name'] ?? $c['slug'] ?? '',
            'href'  => '/' . (function_exists('bo_short_category_slug') ? bo_short_category_slug($c) : ($c['slug'] ?? '')),
        ];
        if (count($footerCategories) >= 8) break;
    }
    if (empty($footerCategories)) {
        $footerCategories = [
            ['label' => 'Escape rooms',          'href' => '/escape-rooms'],
            ['label' => 'Muzee & expoziții',     'href' => '/muzee-expozitii'],
            ['label' => 'Parcuri de distracții', 'href' => '/parcuri-de-distractii'],
            ['label' => 'Parcuri de aventură',   'href' => '/parcuri-de-aventura'],
            ['label' => 'Acvarii & grădini zoo', 'href' => '/acvarii-zoo-animale'],
            ['label' => 'Ateliere & experiențe', 'href' => '/ateliere-experiente-creative'],
        ];
    }
}

// --- Cities (live, with fallback) ----------------------------------------
if (! isset($footerCities)) {
    $footerCities = array_map(
        fn ($c) => ['label' => $c['label'], 'href' => $c['href']],
        navGetCities(8)
    );
    if (empty($footerCities)) {
        $footerCities = [
            ['label' => 'București',   'href' => '/bucuresti'],
            ['label' => 'Cluj-Napoca', 'href' => '/cluj-napoca'],
            ['label' => 'Brașov',      'href' => '/brasov'],
            ['label' => 'Timișoara',   'href' => '/timisoara'],
            ['label' => 'Iași',        'href' => '/iasi'],
            ['label' => 'Constanța',   'href' => '/constanta'],
        ];
    }
}

// --- Help / support links (real routes) ----------------------------------
$footerSupport = [
    ['label' => 'Întrebări frecvente',   'href' => '/faqs'],
    ['label' => 'Cum funcționează',      'href' => '/cum-functioneaza'],
    ['label' => 'Recuperează comanda',   'href' => '/recuperare-comanda'],
    ['label' => 'Biletele mele',         'href' => '/cont/bilete'],
    ['label' => 'Verifică voucher',      'href' => '/voucher'],
    ['label' => 'Contact suport',        'href' => '/contact'],
];

// --- Popular searches: real categories + intent hubs ---------------------
$footerPopular = [];
foreach (array_slice($footerCategories, 0, 6) as $c) {
    $footerPopular[] = ['label' => $c['label'], 'href' => $c['href']];
}
foreach ([
    ['label' => 'Activități în weekend',  'href' => '/activitati-weekend'],
    ['label' => 'Activități cu copiii',   'href' => '/activitati-copii'],
    ['label' => 'Indoor când plouă',      'href' => '/activitati-zile-ploioase'],
    ['label' => 'Sub 50 lei',             'href' => '/activitati-sub-50-lei'],
    ['label' => 'Experiențe cadou',       'href' => '/card-cadou'],
    ['label' => 'Activități pentru cupluri','href' => '/activitati-cupluri'],
] as $hub) {
    $footerPopular[] = $hub;
}

$currentYear  = date('Y');
$supportEmail = defined('SUPPORT_EMAIL') ? SUPPORT_EMAIL : '';

// Alpine seed (newsletter + popular toggle only — link lists are server-rendered
// for SEO). json_encode + htmlspecialchars(ENT_QUOTES) below is the correct
// escaping for the x-data attribute; JSON_HEX_* would break the object keys.
$footerAlpineSeed = json_encode([
    'popular' => $footerPopular,
], JSON_UNESCAPED_UNICODE);
?>
<?php if (!$skipMainTag): ?>
</main>
<?php endif; ?>

<footer x-data="bileteFooter(<?= htmlspecialchars($footerAlpineSeed, ENT_QUOTES) ?>)"
        class="relative overflow-hidden bg-ink text-paper"
        role="contentinfo" itemscope itemtype="https://schema.org/Organization">
    <meta itemprop="name" content="<?= htmlspecialchars(SITE_NAME, ENT_QUOTES) ?>">
    <meta itemprop="url" content="<?= htmlspecialchars(SITE_URL, ENT_QUOTES) ?>">

    <!-- Category marquee -->
    <section class="bo-marquee relative border-y-2 border-paper/10 bg-vermilion text-paper overflow-hidden" aria-label="Categorii populare">
        <div class="bo-marquee-track flex w-max items-center gap-5 py-3 text-sm font-mono font-semibold tracking-[.16em] uppercase">
            <?php for ($rep = 0; $rep < 2; $rep++): ?>
                <div class="flex items-center gap-5" <?= $rep === 1 ? 'aria-hidden="true"' : '' ?>>
                    <?php foreach ($footerCategories as $cat): ?>
                        <a href="<?= htmlspecialchars($cat['href'], ENT_QUOTES) ?>" class="hover:text-ink"><?= htmlspecialchars($cat['label']) ?></a><span aria-hidden="true">✦</span>
                    <?php endforeach; ?>
                </div>
            <?php endfor; ?>
        </div>
    </section>

    <section class="bo-grain relative">
        <div class="absolute inset-0" aria-hidden="true" style="background-image:radial-gradient(circle at 80% 0%,rgba(232,69,39,.22),transparent 30%),radial-gradient(circle at 10% 50%,rgba(218,154,51,.16),transparent 32%),radial-gradient(circle at 70% 90%,rgba(30,74,61,.34),transparent 34%)"></div>

        <div class="relative mx-auto max-w-[1500px] px-4 py-12 sm:px-6 sm:py-16 lg:py-20">
            <div class="grid gap-5 lg:grid-cols-[1.25fr_.75fr]">
                <!-- Discovery hero -->
                <section class="relative overflow-hidden rounded-[2rem] border-2 border-paper/15 bg-paper text-ink shadow-deep">
                    <div class="absolute inset-0 opacity-[.08]" style="background-image:radial-gradient(#1B1714 1.2px,transparent 1.3px);background-size:16px 16px"></div>
                    <div class="relative grid gap-8 p-6 sm:p-8 lg:grid-cols-[1fr_260px] lg:p-10">
                        <div>
                            <p class="inline-flex rounded-full border-2 border-vermilion px-3 py-1 text-xs font-mono font-bold tracking-[.18em] text-vermilion">DISCOVERY ENGINE</p>
                            <h2 class="mt-5 font-display text-5xl font-bold leading-[.85] sm:text-6xl lg:text-7xl">
                                Nu vinde doar bilete. Vinde motive bune să ieși din casă.
                            </h2>
                            <p class="mt-5 max-w-3xl text-lg leading-relaxed text-ink-soft">
                                <?= htmlspecialchars(SITE_NAME) ?> adună activități, locații și experiențe locale într-un sistem gândit pentru descoperire, rezervare rapidă și recomandări personalizate.
                            </p>
                            <div class="mt-7 flex flex-wrap gap-3">
                                <a href="/categorii" class="rounded-full bg-vermilion px-6 py-4 font-bold text-paper transition hover:bg-vermilion-d">Explorează categorii</a>
                                <a href="/orase" class="rounded-full border-2 border-ink px-6 py-4 font-bold text-ink transition hover:bg-ink hover:text-paper">Alege orașul</a>
                                <a href="/card-cadou" class="rounded-full bg-forest px-6 py-4 font-bold text-paper transition hover:bg-ink">Card cadou</a>
                            </div>
                        </div>

                        <aside class="ticket-cut grid place-items-center rounded-[1.5rem] border-2 border-dashed border-ink/25 bg-ink p-5 text-paper shadow-ticket" style="--punch:#F4EFE3">
                            <div class="text-center">
                                <p class="font-mono text-xs tracking-[.18em] text-paper/40">DIGITAL TICKET</p>
                                <p class="mt-3 font-display text-6xl font-bold leading-none">QR</p>
                                <p class="mt-2 text-paper/60">bilete digitale, scanare rapidă, acces simplu</p>
                                <div class="mx-auto mt-5 grid h-28 w-28 place-items-center rounded-2xl bg-paper text-ink">
                                    <span class="font-mono text-xs text-center">SCAN<br>ME</span>
                                </div>
                            </div>
                        </aside>
                    </div>
                </section>

                <!-- Newsletter -->
                <section class="rounded-[2rem] border-2 border-paper/15 bg-paper/10 p-6 shadow-deep sm:p-8">
                    <p class="font-mono text-xs tracking-[.18em] text-paper/40">NEWSLETTER LOCAL</p>
                    <h2 class="mt-3 font-display text-4xl font-bold leading-none">Primește idei de weekend înainte să întrebi „ce facem?”.</h2>
                    <p class="mt-4 text-paper/60">Ghiduri, activități noi, idei pentru copii, experiențe cadou și puncte bonus care expiră.</p>

                    <form class="mt-6 space-y-3" @submit.prevent="subscribe()">
                        <label class="sr-only" for="footer-email">Email</label>
                        <input id="footer-email" required type="email" x-model="email" placeholder="emailul tău" class="w-full rounded-full border-2 border-paper/15 bg-paper px-5 py-4 font-bold text-ink outline-none transition focus:border-vermilion">
                        <div class="grid gap-2 sm:grid-cols-[1fr_auto]">
                            <select x-model="city" class="rounded-full border-2 border-paper/15 bg-paper px-5 py-4 font-bold text-ink outline-none">
                                <option value="">Orașul tău (opțional)</option>
                                <?php foreach ($footerCities as $city): ?>
                                    <option value="<?= htmlspecialchars($city['label'], ENT_QUOTES) ?>"><?= htmlspecialchars($city['label']) ?></option>
                                <?php endforeach; ?>
                            </select>
                            <button type="submit" :disabled="sending" class="rounded-full bg-vermilion px-6 py-4 font-bold text-paper transition hover:bg-vermilion-d disabled:opacity-60">
                                <span x-text="sending ? 'Se trimite…' : 'Abonează-mă'"></span>
                            </button>
                        </div>
                        <p x-show="message" x-cloak x-transition class="rounded-2xl px-4 py-3 text-sm font-bold" :class="ok ? 'bg-mint text-forest' : 'bg-rose text-vermilion-d'" x-text="message"></p>
                        <p class="text-xs leading-relaxed text-paper/40">Prin abonare accepți să primești comunicări editoriale și comerciale. Te poți dezabona oricând.</p>
                    </form>
                </section>
            </div>

            <div class="mt-10 grid gap-5 lg:grid-cols-[1fr_1fr_1fr_1.15fr]">
                <!-- Categories -->
                <nav class="rounded-[1.75rem] border border-paper/10 bg-paper/5 p-5" aria-label="Categorii">
                    <p class="font-mono text-xs tracking-[.18em] text-ochre">CATEGORII</p>
                    <div class="mt-4 space-y-2">
                        <?php foreach (array_slice($footerCategories, 0, 6) as $cat): ?>
                            <a href="<?= htmlspecialchars($cat['href'], ENT_QUOTES) ?>" class="group flex items-center justify-between rounded-2xl px-3 py-2.5 font-bold text-paper/70 transition hover:bg-paper hover:text-ink">
                                <span><?= htmlspecialchars($cat['label']) ?></span>
                                <span class="transition group-hover:translate-x-1" aria-hidden="true">→</span>
                            </a>
                        <?php endforeach; ?>
                    </div>
                </nav>

                <!-- Cities -->
                <nav class="rounded-[1.75rem] border border-paper/10 bg-paper/5 p-5" aria-label="Orașe">
                    <p class="font-mono text-xs tracking-[.18em] text-ochre">ORAȘE</p>
                    <div class="mt-4 space-y-2">
                        <?php foreach (array_slice($footerCities, 0, 6) as $city): ?>
                            <a href="<?= htmlspecialchars($city['href'], ENT_QUOTES) ?>" class="group flex items-center justify-between rounded-2xl px-3 py-2.5 font-bold text-paper/70 transition hover:bg-paper hover:text-ink">
                                <span><?= htmlspecialchars($city['label']) ?></span>
                                <span class="transition group-hover:translate-x-1" aria-hidden="true">→</span>
                            </a>
                        <?php endforeach; ?>
                    </div>
                </nav>

                <!-- Help -->
                <nav class="rounded-[1.75rem] border border-paper/10 bg-paper/5 p-5" aria-label="Ajutor">
                    <p class="font-mono text-xs tracking-[.18em] text-ochre">AJUTOR</p>
                    <div class="mt-4 space-y-2">
                        <?php foreach ($footerSupport as $link): ?>
                            <a href="<?= htmlspecialchars($link['href'], ENT_QUOTES) ?>" class="group flex items-center justify-between rounded-2xl px-3 py-2.5 font-bold text-paper/70 transition hover:bg-paper hover:text-ink">
                                <span><?= htmlspecialchars($link['label']) ?></span>
                                <span class="transition group-hover:translate-x-1" aria-hidden="true">→</span>
                            </a>
                        <?php endforeach; ?>
                    </div>
                </nav>

                <!-- For venues -->
                <section class="rounded-[1.75rem] border border-paper/10 bg-paper/5 p-5">
                    <p class="font-mono text-xs tracking-[.18em] text-ochre">PENTRU LOCAȚII</p>
                    <h3 class="mt-4 font-display text-4xl font-bold leading-none">Ai activități care pot fi rezervate online?</h3>
                    <p class="mt-4 text-paper/60">Listează locația, creează activități, vinde bilete, scanează QR și ajungi mai ușor la oamenii care caută experiențe ca ale tale.</p>
                    <div class="mt-5 flex flex-wrap gap-2">
                        <a href="/pentru-locatii" class="rounded-full bg-paper px-5 py-3 font-bold text-ink transition hover:bg-vermilion hover:text-paper">Pentru locații</a>
                        <a href="/contact?motiv=locatie" class="rounded-full border border-paper/25 px-5 py-3 font-bold text-paper transition hover:bg-paper hover:text-ink">Cere demo</a>
                    </div>
                </section>
            </div>

            <div class="mt-10 grid gap-5 lg:grid-cols-[.9fr_1.1fr]">
                <section class="rounded-[1.75rem] border border-paper/10 bg-paper/5 p-5">
                    <p class="font-mono text-xs tracking-[.18em] text-ochre">DE CE <?= htmlspecialchars(strtoupper(SITE_NAME)) ?></p>
                    <div class="mt-5 grid gap-3 sm:grid-cols-2">
                        <div class="rounded-2xl bg-paper/5 p-4"><p class="font-display text-4xl font-bold">QR</p><p class="mt-1 text-sm text-paper/50">bilete digitale și acces rapid</p></div>
                        <div class="rounded-2xl bg-paper/5 p-4"><p class="font-display text-4xl font-bold">Local</p><p class="mt-1 text-sm text-paper/50">pagini pentru orașe, categorii și locații</p></div>
                        <div class="rounded-2xl bg-paper/5 p-4"><p class="font-display text-4xl font-bold">Bonus</p><p class="mt-1 text-sm text-paper/50">puncte la achiziții eligibile</p></div>
                        <div class="rounded-2xl bg-paper/5 p-4"><p class="font-display text-4xl font-bold">Gift</p><p class="mt-1 text-sm text-paper/50">carduri cadou pentru experiențe</p></div>
                    </div>
                </section>

                <!-- Popular searches -->
                <section class="rounded-[1.75rem] border border-paper/10 bg-paper/5 p-5">
                    <div class="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
                        <div>
                            <p class="font-mono text-xs tracking-[.18em] text-ochre">CĂUTĂRI POPULARE</p>
                            <h3 class="mt-3 font-display text-4xl font-bold leading-none">Scurtături către activități, orașe și idei căutate des.</h3>
                        </div>
                        <button type="button" @click="popularExpanded = !popularExpanded" class="rounded-full border border-paper/25 px-5 py-3 font-bold text-paper transition hover:bg-paper hover:text-ink">
                            <span x-text="popularExpanded ? 'Restrânge' : 'Vezi mai multe'"></span>
                        </button>
                    </div>
                    <div class="mt-5 flex flex-wrap gap-2">
                        <template x-for="tag in visiblePopular()" :key="tag.href">
                            <a :href="tag.href" class="rounded-full bg-paper/5 px-4 py-2 text-sm font-bold text-paper/70 transition hover:bg-paper hover:text-ink" x-text="tag.label"></a>
                        </template>
                    </div>
                </section>
            </div>

            <!-- Bottom -->
            <div class="mt-12 border-t border-paper/10 pt-7">
                <div class="grid gap-7 lg:grid-cols-[1fr_auto] lg:items-end">
                    <div>
                        <a href="/" class="inline-flex items-center gap-2.5 group" aria-label="<?= htmlspecialchars(SITE_NAME, ENT_QUOTES) ?> — acasă">
                            <span class="grid h-10 w-10 place-items-center rounded-md bg-vermilion text-paper rotate-[-4deg] transition group-hover:rotate-[4deg]">
                                <svg viewBox="0 0 24 24" class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M3 9a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2 2 2 0 0 0 0 4 2 2 0 0 1-2 2H5a2 2 0 0 1-2-2 2 2 0 0 0 0-4Z"/><path d="M9 7v10" stroke-dasharray="2 2"/></svg>
                            </span>
                            <span class="font-display text-3xl font-bold text-paper">bilete<span class="text-vermilion">.</span>online</span>
                        </a>
                        <p class="mt-4 max-w-3xl text-sm leading-relaxed text-paper/50">
                            Platformă pentru descoperirea și vânzarea online de bilete pentru activități, experiențe, locații, muzee, escape rooms, tururi, ateliere și evenimente locale. Operat tehnologic de <a href="https://tixello.ro" rel="noopener" class="font-bold text-ochre underline-wobble" itemprop="parentOrganization">Tixello</a>.
                        </p>
                        <?php if ($supportEmail): ?>
                            <p class="mt-3 text-sm text-paper/40">Contact: <a href="mailto:<?= htmlspecialchars($supportEmail, ENT_QUOTES) ?>" class="font-bold text-ochre underline-wobble" itemprop="email"><?= htmlspecialchars($supportEmail) ?></a></p>
                        <?php endif; ?>
                    </div>

                    <div class="flex flex-wrap gap-2 lg:justify-end">
                        <a href="/termeni" class="rounded-full border border-paper/15 px-4 py-2 text-sm font-bold text-paper/60 transition hover:bg-paper hover:text-ink">Termeni</a>
                        <a href="/confidentialitate" class="rounded-full border border-paper/15 px-4 py-2 text-sm font-bold text-paper/60 transition hover:bg-paper hover:text-ink">Confidențialitate</a>
                        <a href="/cookies" class="rounded-full border border-paper/15 px-4 py-2 text-sm font-bold text-paper/60 transition hover:bg-paper hover:text-ink">Cookies</a>
                        <button type="button" @click="openCookiePreferences()" class="rounded-full border border-paper/15 px-4 py-2 text-sm font-bold text-paper/60 transition hover:bg-paper hover:text-ink">Setări cookies</button>
                        <a href="/contact" class="rounded-full border border-paper/15 px-4 py-2 text-sm font-bold text-paper/60 transition hover:bg-paper hover:text-ink">Contact</a>
                    </div>
                </div>

                <div class="mt-7 flex flex-col gap-4 border-t border-paper/10 pt-5 text-xs text-paper/30 sm:flex-row sm:items-center sm:justify-between">
                    <p>© <?= $currentYear ?> <?= htmlspecialchars(SITE_NAME) ?>. Toate drepturile rezervate.</p>
                    <div class="flex flex-wrap items-center gap-4">
                        <span>Card · Apple Pay · Google Pay · Revolut</span>
                        <a href="#top" class="font-bold text-paper/50 hover:text-ochre">Înapoi sus ↑</a>
                    </div>
                </div>
            </div>
        </div>
    </section>
</footer>

<script>
function bileteFooter(seed) {
    return {
        newsletterSent: false,
        popularExpanded: false,
        email: '',
        city: '',
        sending: false,
        ok: false,
        message: '',
        popular: (seed && seed.popular) || [],
        visiblePopular() {
            return this.popularExpanded ? this.popular : this.popular.slice(0, 6);
        },
        async subscribe() {
            if (! this.email || this.sending) return;
            this.sending = true;
            this.message = '';
            try {
                if (typeof BileteOnlineAPI === 'undefined' || ! BileteOnlineAPI.post) {
                    throw new Error('API indisponibil');
                }
                const payload = { email: this.email, source: 'footer' };
                if (this.city) payload.city = this.city;
                const r = await BileteOnlineAPI.post('/newsletter/subscribe', payload);
                this.ok = !! (r && (r.success === undefined || r.success));
                this.message = this.ok
                    ? 'Gata! Verifică emailul pentru confirmare.'
                    : ((r && r.message) || 'Nu am putut finaliza abonarea. Încearcă din nou.');
                if (this.ok) this.email = '';
            } catch (e) {
                this.ok = false;
                this.message = (e && e.message) ? e.message : 'A apărut o eroare. Încearcă din nou.';
            } finally {
                this.sending = false;
            }
        },
        openCookiePreferences() {
            try {
                if (window.CookieConsent && typeof CookieConsent.openPreferences === 'function') {
                    CookieConsent.openPreferences();
                    return;
                }
                if (window.CookieConsent && typeof CookieConsent.show === 'function') {
                    CookieConsent.show();
                    return;
                }
            } catch (e) {}
            window.dispatchEvent(new CustomEvent('bo-open-cookie-preferences'));
        },
    };
}
</script>

<!-- ===================== TAIL JS (cart + notifications + tracking) =====================
     config / utils / api / auth load from head.php BEFORE Alpine so Alpine's
     init() hooks can read BileteOnlineAuth synchronously. The scripts below are
     only consumed by user interactions, so they load after Alpine bootstraps. -->
<script defer src="<?= asset('assets/js/cart.js') ?>"></script>
<script defer src="<?= asset('assets/js/components/notifications.js') ?>"></script>
<script defer src="<?= asset('assets/js/tracking.js') ?>"></script>

<!-- ===================== PAGE-BOTTOM SCRIPTS ===================== -->
<script defer src="<?= asset('assets/js/components/scroll-reveal.js') ?>"></script>
<?php foreach ($footerExtraJs as $jsPath): ?>
<script defer src="<?= asset(ltrim($jsPath, '/')) ?>"></script>
<?php endforeach; ?>

<?php if (! $hideCookieBanner): ?>
    <?php include __DIR__ . '/cookie-consent.php'; ?>
<?php endif; ?>

<?php echo $extraBodyEnd; ?>
</body>
</html>
