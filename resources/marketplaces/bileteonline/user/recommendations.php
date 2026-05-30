<?php
/**
 * bilete.online — /cont/recomandari (Recomandări personalizate, v2 full template)
 *
 * Implements template-client-recommendations-bilete-online-v2.html end-to-end:
 *   - Hero w/ PROFILE SIGNALS card (oraș / interes / familie / puncte)
 *   - 4 stat cards (MATCH BUN / CU PUNCTE / FAMILIE / EXPIRĂ)
 *   - Filter row: search + reason + city + budget + reset
 *   - Quick-filter pills (profile/family/points/weather)
 *   - 2-col activity cards: image overlay + match badge + points-eligible badge +
 *     city/category/price tags + description + "De ce ți-o recomandăm?" box +
 *     "Vezi bilete" + "Nu mă interesează" CTAs
 *   - Right sidebar: CONTROL PERSONALIZARE (4 toggles) + PUNCTE BONUS + DISCOVERY cards
 */

require_once __DIR__ . '/../includes/config.php';

$pageTitleRaw    = 'Recomandări pentru tine — ' . SITE_NAME;
$pageDescription = 'Recomandări personalizate bilete.online: activități pe baza orașelor preferate, comenzilor, recenziilor, punctelor și profilului de familie.';
$canonicalUrl    = SITE_URL . '/cont/recomandari';
$noindex         = true;
$currentPage     = 'cont';
$cssBundle       = 'auth';

include __DIR__ . '/../includes/head.php';
include __DIR__ . '/../includes/header.php';
?>

<div class="max-w-[1500px] mx-auto px-4 sm:px-6 py-6 lg:py-8">
    <div class="grid lg:grid-cols-[280px_minmax(0,1fr)] gap-6 lg:gap-8 items-start">

        <?php $currentClientPage = 'recommendations'; include __DIR__ . '/../includes/client-sidebar-v2.php'; ?>

        <main class="min-w-0" x-data="clientRecommendationsPage()" x-init="init()">

            <!-- HERO with PROFILE SIGNALS -->
            <section class="relative overflow-hidden rounded-[2rem] border-2 border-ink bg-ink text-paper p-6 sm:p-8 shadow-deep">
                <div class="absolute inset-0 opacity-10" style="background-image:radial-gradient(#fff 1px,transparent 1.4px);background-size:15px 15px"></div>
                <div class="relative grid xl:grid-cols-[1fr_420px] gap-8 items-center">
                    <div>
                        <p class="stamp inline-flex px-3 py-1 text-xs font-mono tracking-[.18em] text-ochre">PERSONAL DISCOVERY</p>
                        <h1 class="mt-5 font-display text-5xl sm:text-6xl lg:text-7xl font-bold leading-[.85]">Recomandări pentru tine</h1>
                        <p class="mt-5 max-w-3xl text-paper/65 text-lg leading-relaxed">Activități recomandate pe baza orașelor preferate, comenzilor, recenziilor, punctelor disponibile, profilului de familie și intereselor tale.</p>
                        <div class="mt-7 flex flex-wrap gap-3">
                            <a href="#recomandari" class="rounded-full bg-vermilion text-paper px-6 py-4 font-bold hover:bg-vermilion-d transition">Vezi recomandări</a>
                            <a href="/cont/setari#profil-preferinte" class="rounded-full border-2 border-paper/50 px-6 py-4 font-bold hover:bg-paper hover:text-ink transition">Rafinează profilul</a>
                        </div>
                    </div>

                    <div class="rounded-[2rem] border-2 border-paper/30 bg-paper text-ink p-6 rotate-[-2deg]">
                        <p class="font-mono text-xs tracking-[.18em] text-ink-soft">PROFILE SIGNALS</p>
                        <h2 class="mt-2 font-display text-4xl font-bold leading-none">De ce vezi aceste recomandări?</h2>
                        <div class="mt-5 space-y-3">
                            <div class="rounded-2xl bg-paper-2 border border-ink/10 p-4 flex items-center justify-between gap-2">
                                <span class="font-bold">Oraș preferat</span>
                                <span x-text="signals.city || 'neales'"></span>
                            </div>
                            <div class="rounded-2xl bg-paper-2 border border-ink/10 p-4 flex items-center justify-between gap-2">
                                <span class="font-bold">Interes</span>
                                <span class="truncate" x-text="signals.interest || 'descoperire'"></span>
                            </div>
                            <div class="rounded-2xl bg-paper-2 border border-ink/10 p-4 flex items-center justify-between gap-2">
                                <span class="font-bold">Familie</span>
                                <span x-text="signals.family || 'doar tu'"></span>
                            </div>
                            <div class="rounded-2xl bg-paper-2 border border-ink/10 p-4 flex items-center justify-between gap-2">
                                <span class="font-bold">Puncte</span>
                                <span x-text="formatNumber(signals.points || 0)"></span>
                            </div>
                        </div>
                    </div>
                </div>
            </section>

            <!-- 4 STAT CARDS -->
            <section class="mt-6 grid sm:grid-cols-2 xl:grid-cols-4 gap-4">
                <article class="rounded-[2rem] border-2 border-ink bg-paper p-5 shadow-ticket">
                    <p class="font-mono text-xs tracking-[.18em] text-ink-soft">MATCH BUN</p>
                    <p class="mt-3 font-display text-6xl font-bold" x-text="stats.goodMatch">0</p>
                    <p class="mt-1 text-ink-soft">activități potrivite</p>
                </article>
                <article class="rounded-[2rem] border-2 border-ink bg-mint p-5 shadow-ticket">
                    <p class="font-mono text-xs tracking-[.18em] text-forest">CU PUNCTE</p>
                    <p class="mt-3 font-display text-6xl font-bold" x-text="stats.withPoints">0</p>
                    <p class="mt-1 text-ink-soft">poți aplica reducere</p>
                </article>
                <article class="rounded-[2rem] border-2 border-ink bg-paper p-5 shadow-ticket">
                    <p class="font-mono text-xs tracking-[.18em] text-ink-soft">FAMILIE</p>
                    <p class="mt-3 font-display text-6xl font-bold" x-text="stats.family">0</p>
                    <p class="mt-1 text-ink-soft">potrivite pentru copii</p>
                </article>
                <article class="rounded-[2rem] border-2 border-ink bg-rose p-5 shadow-ticket">
                    <p class="font-mono text-xs tracking-[.18em] text-vermilion">EXPIRĂ</p>
                    <p class="mt-3 font-display text-6xl font-bold" x-text="formatNumber(stats.expiringPoints)">0</p>
                    <p class="mt-1 text-ink-soft" x-text="stats.expiringPoints > 0 ? ('puncte în ' + (stats.expiringDays || 30) + ' zile') : 'fără puncte expirate'">—</p>
                </article>
            </section>

            <!-- FILTERS -->
            <section class="mt-6 rounded-[2rem] border-2 border-ink bg-paper p-5 sm:p-6 shadow-ticket">
                <div class="grid xl:grid-cols-[1.2fr_.7fr_.7fr_.7fr_auto] gap-3 items-end">
                    <label>
                        <span class="block mb-1.5 text-sm font-bold">Caută</span>
                        <input class="field" x-model="search" placeholder="Escape room, copii, muzeu, Brașov…">
                    </label>
                    <label>
                        <span class="block mb-1.5 text-sm font-bold">Motiv</span>
                        <select class="field" x-model="reason">
                            <option value="all">Toate</option>
                            <option value="profile">Profil</option>
                            <option value="family">Familie</option>
                            <option value="points">Puncte</option>
                            <option value="history">Istoric</option>
                            <option value="weather">Vreme/sezon</option>
                        </select>
                    </label>
                    <label>
                        <span class="block mb-1.5 text-sm font-bold">Oraș</span>
                        <select class="field" x-model="cityFilter">
                            <option value="all">Toate</option>
                            <template x-for="c in availableCities" :key="c">
                                <option :value="c" x-text="c"></option>
                            </template>
                        </select>
                    </label>
                    <label>
                        <span class="block mb-1.5 text-sm font-bold">Buget</span>
                        <select class="field" x-model="budget">
                            <option value="all">Orice</option>
                            <option value="low">sub 50 lei</option>
                            <option value="mid">50–120 lei</option>
                            <option value="high">120+ lei</option>
                        </select>
                    </label>
                    <button @click="resetFilters()" class="rounded-full border-2 border-ink px-5 py-3.5 font-bold hover:bg-ink hover:text-paper transition">Reset</button>
                </div>

                <div class="mt-5 flex flex-wrap gap-2">
                    <button @click="reason='profile'" :class="reason==='profile'?'bg-ink text-paper':'bg-paper-2'" class="rounded-full px-4 py-2 font-bold border border-ink/10">Pentru profilul tău</button>
                    <button @click="reason='family'" :class="reason==='family'?'bg-forest text-paper':'bg-paper-2'" class="rounded-full px-4 py-2 font-bold border border-ink/10">Cu copiii</button>
                    <button @click="reason='points'" :class="reason==='points'?'bg-vermilion text-paper':'bg-paper-2'" class="rounded-full px-4 py-2 font-bold border border-ink/10">Folosește puncte</button>
                    <button @click="reason='weather'" :class="reason==='weather'?'bg-ochre text-ink':'bg-paper-2'" class="rounded-full px-4 py-2 font-bold border border-ink/10">Weekend / vreme</button>
                </div>
            </section>

            <!-- RESULTS + RIGHT SIDEBAR -->
            <section id="recomandari" class="mt-6 grid xl:grid-cols-[1fr_360px] gap-6 items-start">

                <div>
                    <div class="flex flex-col sm:flex-row sm:items-end sm:justify-between gap-3 mb-4">
                        <div>
                            <p class="font-mono text-xs tracking-[.18em] text-ink-soft">REZULTATE</p>
                            <h2 class="font-display text-4xl sm:text-5xl font-bold leading-none" x-text="filteredItems().length + ' recomandări'">0 recomandări</h2>
                        </div>
                        <a href="/cont/setari#profil-preferinte" class="rounded-full bg-ink text-paper px-5 py-3 font-bold hover:bg-vermilion transition">Rafinează profilul</a>
                    </div>

                    <div x-show="loading" class="grid md:grid-cols-2 gap-5">
                        <div class="h-96 rounded-[2rem] bg-paper-2/60 animate-pulse"></div>
                        <div class="h-96 rounded-[2rem] bg-paper-2/60 animate-pulse"></div>
                    </div>

                    <div x-show="!loading && filteredItems().length > 0" class="grid md:grid-cols-2 gap-5">
                        <template x-for="item in filteredItems()" :key="item.url || item.id">
                            <article class="rounded-[2rem] border-2 border-ink bg-paper overflow-hidden shadow-ticket hover:-translate-y-1 transition">
                                <div class="relative h-56 bg-ink overflow-hidden">
                                    <img x-show="item.image" :src="item.image" :alt="item.title" class="w-full h-full object-cover opacity-85" loading="lazy" onerror="this.style.display='none'">
                                    <div class="absolute inset-0 grid place-items-center" x-show="!item.image"><span class="text-6xl opacity-30">🎫</span></div>
                                    <div class="absolute inset-0 bg-gradient-to-t from-ink/90 via-transparent to-transparent"></div>
                                    <span x-show="item.match > 0" class="absolute left-4 top-4 rounded-full bg-paper text-ink px-3 py-1 text-xs font-bold" x-text="item.match + '% match'"></span>
                                    <span x-show="item.canUsePoints" class="absolute right-4 top-4 rounded-full bg-mint text-forest px-3 py-1 text-xs font-bold">poți folosi puncte</span>
                                    <h3 class="absolute left-4 right-4 bottom-4 font-display text-4xl font-bold text-paper leading-none" x-text="item.title"></h3>
                                </div>
                                <div class="p-5">
                                    <div class="flex flex-wrap gap-2">
                                        <span x-show="item.city" class="rounded-full bg-paper-2 border border-ink/10 px-3 py-1 text-xs font-bold" x-text="item.city"></span>
                                        <span x-show="item.category" class="rounded-full bg-paper-2 border border-ink/10 px-3 py-1 text-xs font-bold" x-text="item.category"></span>
                                        <span x-show="item.price" class="rounded-full bg-paper-2 border border-ink/10 px-3 py-1 text-xs font-bold" x-text="item.price"></span>
                                    </div>
                                    <p class="mt-4 text-ink-soft leading-relaxed" x-text="item.description"></p>
                                    <div class="mt-4 rounded-2xl bg-mint border border-forest/20 p-4">
                                        <p class="font-bold text-forest">De ce ți-o recomandăm?</p>
                                        <p class="mt-1 text-sm text-ink-soft" x-text="item.why"></p>
                                    </div>
                                    <div class="mt-5 flex flex-wrap gap-2">
                                        <a :href="item.url" class="rounded-full bg-vermilion text-paper px-5 py-3 font-bold hover:bg-vermilion-d transition">Vezi bilete</a>
                                        <button @click="hideItem(item)" class="rounded-full border border-ink/20 px-5 py-3 font-bold hover:bg-ink hover:text-paper transition">Nu mă interesează</button>
                                    </div>
                                </div>
                            </article>
                        </template>
                    </div>

                    <div x-show="!loading && filteredItems().length === 0" class="mt-6 rounded-[2rem] border-2 border-ink bg-paper p-8 text-center">
                        <p class="font-display text-4xl font-bold">Nu am găsit recomandări.</p>
                        <p class="mt-2 text-ink-soft">Schimbă filtrele sau completează profilul pentru sugestii mai bune.</p>
                        <div class="mt-5 flex flex-wrap gap-2 justify-center">
                            <button @click="resetFilters()" class="rounded-full border-2 border-ink px-5 py-3 font-bold hover:bg-ink hover:text-paper transition">Resetează filtrele</button>
                            <a href="/cont/setari#profil-preferinte" class="rounded-full bg-vermilion text-paper px-5 py-3 font-bold hover:bg-vermilion-d transition">Adaugă preferințe</a>
                        </div>
                    </div>
                </div>

                <aside class="space-y-6 xl:sticky xl:top-28">

                    <!-- CONTROL PERSONALIZARE -->
                    <div class="rounded-[2rem] border-2 border-ink bg-paper p-6 shadow-ticket">
                        <p class="font-mono text-xs tracking-[.18em] text-ink-soft">CONTROL PERSONALIZARE</p>
                        <h2 class="mt-2 font-display text-4xl font-bold leading-none">Ce influențează recomandările</h2>
                        <div class="mt-5 space-y-3">
                            <label class="flex items-start gap-3 cursor-pointer">
                                <input type="checkbox" x-model="signalsToggles.history" @change="recompute()" class="mt-1 w-5 h-5 accent-vermilion">
                                <span><strong>Istoric comenzi</strong><br><span class="text-sm text-ink-soft">activități cumpărate anterior</span></span>
                            </label>
                            <label class="flex items-start gap-3 cursor-pointer">
                                <input type="checkbox" x-model="signalsToggles.reviews" @change="recompute()" class="mt-1 w-5 h-5 accent-vermilion">
                                <span><strong>Recenzii</strong><br><span class="text-sm text-ink-soft">ratinguri și feedback</span></span>
                            </label>
                            <label class="flex items-start gap-3 cursor-pointer">
                                <input type="checkbox" x-model="signalsToggles.family" @change="recompute()" class="mt-1 w-5 h-5 accent-vermilion">
                                <span><strong>Profil familie</strong><br><span class="text-sm text-ink-soft">vârste copii / tipuri activități</span></span>
                            </label>
                            <label class="flex items-start gap-3 cursor-pointer">
                                <input type="checkbox" x-model="signalsToggles.cities" @change="recompute()" class="mt-1 w-5 h-5 accent-vermilion">
                                <span><strong>Orașe favorite</strong><br><span class="text-sm text-ink-soft" x-text="(prefCities.join(', ')) || 'încă nimic ales'">încă nimic ales</span></span>
                            </label>
                        </div>
                        <a href="/cont/setari#profil-preferinte" class="mt-5 inline-flex rounded-full bg-ink text-paper px-5 py-3 font-bold hover:bg-vermilion transition">Editează semnalele</a>
                    </div>

                    <!-- PUNCTE BONUS -->
                    <div class="rounded-[2rem] border-2 border-forest bg-mint p-6">
                        <p class="font-mono text-xs tracking-[.18em] text-forest">PUNCTE BONUS</p>
                        <h2 class="mt-2 font-display text-4xl font-bold leading-none">Ai <span x-text="formatNumber(signals.points)">0</span> puncte</h2>
                        <p class="mt-3 text-ink-soft">Poți reduce următoarea comandă cu aproximativ <strong x-text="formatLei(pointsToLei(signals.points))">0 lei</strong>, în funcție de regulile checkout-ului.</p>
                        <a href="/cont/punctele-mele" class="mt-5 inline-flex rounded-full bg-forest text-paper px-5 py-3 font-bold hover:bg-ink transition">Vezi puncte</a>
                    </div>

                    <!-- DISCOVERY -->
                    <div class="rounded-[2rem] border-2 border-ink bg-vermilion text-paper p-6 shadow-ticket">
                        <p class="font-mono text-xs tracking-[.18em] text-paper/60">DISCOVERY</p>
                        <h2 class="mt-2 font-display text-4xl font-bold leading-none">Vrei altceva?</h2>
                        <p class="mt-3 text-paper/70">Explorează toate categoriile sau caută manual activități în orașul tău.</p>
                        <a href="/categorii" class="mt-5 inline-flex rounded-full bg-paper text-ink px-5 py-3 font-bold hover:bg-ink hover:text-paper transition">Vezi categorii</a>
                    </div>
                </aside>
            </section>

            <!-- AUTH GUARD -->
            <div x-show="!isAuth" x-cloak class="mt-8 rounded-[2rem] border-2 border-vermilion bg-rose p-8 text-center">
                <p class="font-display text-3xl font-bold text-vermilion">Trebuie să fii autentificat</p>
                <a href="/autentificare?redirect=/cont/recomandari" class="mt-5 inline-flex rounded-full bg-vermilion text-paper px-6 py-3 font-bold">Intră în cont</a>
            </div>
        </main>
    </div>
</div>

<script>
function clientRecommendationsPage() {
    return {
        loading: true,
        isAuth: true,

        search: '',
        reason: 'all',
        cityFilter: 'all',
        budget: 'all',
        hidden: [],

        items: [],
        prefCities: [],
        prefCategories: [],

        signals: { city: '', interest: '', family: '', points: 0 },
        signalsToggles: { history: true, reviews: true, family: true, cities: true },

        stats: { goodMatch: 0, withPoints: 0, family: 0, expiringPoints: 0, expiringDays: 30 },

        // Loaded from /customer/rewards/config
        rewardsConfig: { pointsPerLei: 100, loaded: false },

        init() {
            try { this.isAuth = (window.BileteOnlineAuth && BileteOnlineAuth.isLoggedIn && BileteOnlineAuth.isLoggedIn()); } catch (e) { this.isAuth = false; }
            if (! this.isAuth) { this.loading = false; return; }

            // Restore "hidden" set from localStorage so dismissed items don't reappear
            try {
                const saved = JSON.parse(localStorage.getItem('bo_rec_hidden') || '[]');
                if (Array.isArray(saved)) this.hidden = saved;
            } catch (e) {}

            this.load();
        },

        async load() {
            // 1. Rewards config (for pointsToLei conversion)
            try {
                const r = await BileteOnlineAPI.get('/customer/rewards/config');
                const d = (r && r.data) || {};
                if (d.points_per_lei) this.rewardsConfig.pointsPerLei = d.points_per_lei;
                this.rewardsConfig.loaded = true;
            } catch (e) {}

            // 2. Server-side recommendations engine — match_score + reasons
            //    are computed in PHP (RecommendationsController) based on
            //    settings.interests + history + family + points; we no longer
            //    score client-side.
            try {
                const r = await BileteOnlineAPI.get('/customer/recommendations', { limit: 24 });
                const d = (r && r.data) || {};
                this.items = (d.items || []).map(it => ({
                    id:           it.id,
                    title:        it.title || '',
                    url:          it.url || (it.slug ? '/activitate/' + it.slug : '#'),
                    image:        it.image || null,
                    city:         it.city || '',
                    category:     it.category || '',
                    categorySlug: it.category_slug || '',
                    price:        it.price_label || '',
                    priceValue:   it.price_from_lei || 0,
                    description:  it.short_description || '',
                    match:        it.match_score || 0,
                    why:          (it.reasons || []).join(' · '),
                    reason:       it.reason_primary || 'profile',
                    canUsePoints: !!it.can_use_points,
                    isFamily:     !!it.is_family,
                    budget:       it.budget_bucket || 'mid',
                }));

                // Stats from engine
                this.stats.goodMatch     = d.stats?.good_match_count  || 0;
                this.stats.withPoints    = d.stats?.with_points_count || 0;
                this.stats.family        = d.stats?.family_count      || 0;

                // Profile signals card (right of hero)
                this.signals.city     = d.signals?.city     || '';
                this.signals.interest = d.signals?.interest || 'descoperire';
                this.signals.family   = d.signals?.family   || 'doar tu';
                this.signals.points   = d.signals?.points   || 0;

                // Sync local "interests" arrays so the sidebar checkbox card
                // shows the actual preferred-cities list under "Orașe favorite".
                this.prefCities     = d.engine?.pref_cities     || [];
                this.prefCategories = d.engine?.pref_categories || [];
            } catch (e) {}

            // 3. Expiring points warning (still from rewards summary endpoint)
            try {
                const r = await BileteOnlineAPI.get('/customer/rewards');
                const d = (r && r.data) || {};
                const p = d.points || d;
                this.stats.expiringPoints = p.expiring_soon ?? 0;
                this.stats.expiringDays   = p.expiring_in_days ?? 30;
            } catch (e) {}

            this.loading = false;
        },

        normalizeActivity(a) {
            const cityName = a.city ? (typeof a.city === 'string' ? a.city : a.city.name) : '';
            const catName  = a.category ? (typeof a.category === 'string' ? a.category : a.category.name) : '';
            const catSlug  = a.category ? (typeof a.category === 'string' ? a.category.toLowerCase() : a.category.slug) : '';
            const priceFrom = a.price_from || (a.tickets && a.tickets.length > 0 ? Math.min.apply(null, a.tickets.map(t => t.price || 1e9)) : null);
            const tags = (a.tags || []).map(t => typeof t === 'string' ? t : (t.name || t.slug || ''));

            return {
                id:    a.id || a.slug,
                title: a.title || a.name || '',
                slug:  a.slug || '',
                url:   a.slug ? ('/activitate/' + a.slug) : '#',
                image: a.cover_image_url || a.image || null,
                city:  cityName,
                category: catName,
                categorySlug: catSlug,
                tags:  tags,
                price: priceFrom ? ('de la ' + Math.round(priceFrom) + ' lei') : '',
                priceValue: priceFrom || 0,
                description: a.short_description || a.description || (catName ? ('Activitate ' + catName.toLowerCase() + (cityName ? (' în ' + cityName) : '') + '.') : 'Activitate populară.'),
                // computed below
                match: 0,
                why: '',
                reason: 'profile',
                canUsePoints: false,
                isFamily: false,
                budget: 'mid',
            };
        },

        // Client-side fallback re-ranking — only used if the server endpoint
        // ever fails AND we somehow have unranked items. Real ranking lives in
        // RecommendationsController. Kept here for resilience only.
        recompute() {
            const lcPrefCities = this.signalsToggles.cities ? this.prefCities.map(c => (c || '').toLowerCase()) : [];
            const lcPrefCats   = this.prefCategories.map(c => (c || '').toLowerCase());
            const pts = this.signals.points;

            this.items.forEach(it => {
                let score = 30; // base
                let reasons = [];
                let reason = 'profile';

                const cityLc = (it.city || '').toLowerCase();
                const catLc  = (it.category || '').toLowerCase();
                const slugLc = (it.categorySlug || '').toLowerCase();

                if (lcPrefCities.some(c => c && (cityLc.includes(c) || c.includes(cityLc)))) {
                    score += 25; reasons.push('Orașul tău preferat');
                }
                if (lcPrefCats.length > 0 && (lcPrefCats.includes(slugLc) || lcPrefCats.includes(catLc))) {
                    score += 30; reasons.push('Categorie pe care ai marcat-o ca preferată');
                }

                // Family / kids signal
                const isFamily = /\b(copii|copil|family|familie|kids|junior)\b/i.test(it.title + ' ' + it.description + ' ' + it.tags.join(' '));
                if (isFamily) { it.isFamily = true; }
                if (isFamily && this.signalsToggles.family && /copii/.test(this.signals.family || '')) {
                    score += 15; reasons.push('Activitate potrivită pentru copii'); reason = 'family';
                }

                // Budget bucket
                if (it.priceValue && it.priceValue < 50)         it.budget = 'low';
                else if (it.priceValue && it.priceValue <= 120)  it.budget = 'mid';
                else if (it.priceValue && it.priceValue > 120)   it.budget = 'high';

                // Points eligibility — heuristic: only count if user actually has points
                it.canUsePoints = pts >= 100;
                if (it.canUsePoints) {
                    score += 5;
                    if (reasons.length === 0) { reasons.push('Poți aplica reducere din punctele bonus'); reason = 'points'; }
                }

                // Weather / weekend — pull from tags
                if (/weekend|outdoor|exterior|natur(ă|a)/i.test(it.tags.join(' ') + ' ' + it.description)) {
                    score += 5; if (reasons.length === 0) reason = 'weather';
                }

                // History signal not directly available client-side; use category match as proxy
                if (! reasons.length && lcPrefCats.includes(slugLc)) { reason = 'history'; }

                it.match = Math.min(99, Math.round(score));
                it.reason = reason;
                it.why = reasons.length > 0
                    ? reasons.join(' · ')
                    : 'Activitate populară pe care credem că o vei aprecia.';
            });

            this.items.sort((a, b) => b.match - a.match);

            // Stat tiles
            this.stats.goodMatch  = this.items.filter(i => i.match >= 70).length;
            this.stats.withPoints = this.items.filter(i => i.canUsePoints).length;
            this.stats.family     = this.items.filter(i => i.isFamily).length;
        },

        get availableCities() {
            const set = new Set();
            this.items.forEach(i => { if (i.city) set.add(i.city); });
            return Array.from(set).sort((a, b) => a.localeCompare(b, 'ro'));
        },

        filteredItems() {
            const q = (this.search || '').toLowerCase().trim();
            return this.items.filter(item => {
                if (this.hidden.includes(item.url)) return false;
                if (this.reason !== 'all' && item.reason !== this.reason) return false;
                if (this.cityFilter !== 'all' && item.city !== this.cityFilter) return false;
                if (this.budget !== 'all' && item.budget !== this.budget) return false;
                if (! q) return true;
                return JSON.stringify(item).toLowerCase().includes(q);
            });
        },

        hideItem(item) {
            this.hidden.push(item.url);
            try { localStorage.setItem('bo_rec_hidden', JSON.stringify(this.hidden.slice(-200))); } catch (e) {}
        },

        resetFilters() {
            this.search = ''; this.reason = 'all'; this.cityFilter = 'all'; this.budget = 'all';
        },

        pointsToLei(p) { return Math.floor((p || 0) / (this.rewardsConfig.pointsPerLei || 100)); },
        formatNumber(n) { return new Intl.NumberFormat('ro-RO').format(n || 0); },
        formatLei(n)    { return (new Intl.NumberFormat('ro-RO', { maximumFractionDigits: 0 }).format(n || 0)) + ' lei'; },
    };
}
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>
