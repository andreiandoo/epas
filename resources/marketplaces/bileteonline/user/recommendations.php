<?php
/**
 * bilete.online — /cont/recomandari (Recomandări personalizate, v2 design)
 *
 * Activity suggestions based on the client's taste profile (cities + categories
 * mined from their order history). Hits /customer/profile-data for the taste
 * signal and /activities for the candidate pool. No dedicated recommendation
 * endpoint yet — v1 ranks client-side by tag/city overlap.
 */

require_once __DIR__ . '/../includes/config.php';

$pageTitleRaw    = 'Recomandări personalizate — ' . SITE_NAME;
$pageDescription = 'Activități recomandate pentru tine pe bilete.online, alese pe baza orașelor și categoriilor pe care le-ai vizitat sau ai cumpărat.';
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

            <!-- HERO -->
            <section class="rounded-[2rem] border-2 border-ink bg-ink text-paper p-6 sm:p-8 shadow-deep">
                <div class="grid xl:grid-cols-[1fr_360px] gap-8 items-center">
                    <div>
                        <p class="stamp inline-flex px-3 py-1 text-xs font-mono tracking-[.18em] text-ochre">PENTRU TINE</p>
                        <h1 class="mt-5 font-display text-5xl sm:text-6xl lg:text-7xl font-bold leading-[.85]">Recomandări</h1>
                        <p class="mt-5 max-w-3xl text-paper/65 text-lg leading-relaxed">
                            Activități alese pe baza orașelor și categoriilor pe care le-ai vizitat sau le-ai cumpărat. Cu cât folosești mai mult contul, cu atât devin mai relevante.
                        </p>
                        <div class="mt-6 flex flex-wrap gap-3">
                            <a href="/cont/setari#profil-preferinte" class="rounded-full bg-vermilion text-paper px-6 py-3 font-bold hover:bg-vermilion-d transition">Ajustează preferințele</a>
                            <a href="/categorii" class="rounded-full border-2 border-paper/50 px-6 py-3 font-bold hover:bg-paper hover:text-ink transition">Vezi categorii</a>
                        </div>
                    </div>
                    <div class="ticket relative bg-paper text-ink rounded-[2rem] border-2 border-paper/30 p-6 rotate-[-2deg]" style="--perf:100%">
                        <p class="font-mono text-xs tracking-[.18em] text-ink-soft">PROFIL DE GUST</p>
                        <p class="mt-2 font-display text-3xl font-bold leading-tight" x-text="topCity || 'Niciun oraș setat'">Niciun oraș setat</p>
                        <p class="mt-1 text-sm text-ink-soft">cel mai vizitat oraș</p>
                        <div class="mt-4 flex flex-wrap gap-1.5">
                            <template x-for="tag in topCategories.slice(0, 5)" :key="tag">
                                <span class="rounded-full bg-paper-2 border border-ink/10 px-2.5 py-1 text-xs font-bold" x-text="tag"></span>
                            </template>
                            <span x-show="topCategories.length === 0" class="text-xs text-ink-soft">Folosește contul ca să-ți construim profilul.</span>
                        </div>
                    </div>
                </div>
            </section>

            <!-- TASTE FILTERS -->
            <section class="mt-6 rounded-[2rem] border-2 border-ink bg-paper p-5 shadow-ticket">
                <p class="font-mono text-xs tracking-[.18em] text-ink-soft">FILTRE</p>
                <div class="mt-3 flex flex-wrap gap-2">
                    <button @click="activeFilter='all'" :class="activeFilter==='all' ? 'bg-ink text-paper' : 'bg-paper-2'" class="rounded-full px-4 py-2 font-bold border border-ink/10 text-sm">Toate</button>
                    <template x-for="t in topCategories.slice(0, 6)" :key="'cat-'+t">
                        <button @click="activeFilter=t" :class="activeFilter===t ? 'bg-ink text-paper' : 'bg-paper-2'" class="rounded-full px-4 py-2 font-bold border border-ink/10 text-sm" x-text="t"></button>
                    </template>
                    <button x-show="topCity" @click="cityFilter = cityFilter ? '' : topCity" :class="cityFilter ? 'bg-vermilion text-paper' : 'bg-paper-2'" class="rounded-full px-4 py-2 font-bold border border-ink/10 text-sm">
                        <span x-text="cityFilter ? '✓ Doar ' + topCity : 'Doar ' + topCity"></span>
                    </button>
                </div>
            </section>

            <!-- ACTIVITY GRID -->
            <section class="mt-6">
                <div class="flex items-end justify-between gap-3 mb-4">
                    <div>
                        <p class="font-mono text-xs tracking-[.18em] text-ink-soft">SUGESTII</p>
                        <h2 class="font-display text-4xl sm:text-5xl font-bold leading-none" x-text="filteredItems().length + ' activități'"></h2>
                    </div>
                </div>

                <div x-show="loading" class="grid sm:grid-cols-2 xl:grid-cols-3 gap-4">
                    <div class="h-64 rounded-[2rem] bg-paper-2/60 animate-pulse"></div>
                    <div class="h-64 rounded-[2rem] bg-paper-2/60 animate-pulse"></div>
                    <div class="h-64 rounded-[2rem] bg-paper-2/60 animate-pulse"></div>
                </div>

                <div x-show="!loading && filteredItems().length > 0" class="grid sm:grid-cols-2 xl:grid-cols-3 gap-4">
                    <template x-for="item in filteredItems()" :key="item.slug || item.id">
                        <article class="rounded-[2rem] border-2 border-ink bg-paper overflow-hidden shadow-ticket hover:-translate-y-1 transition">
                            <a :href="'/activitate/' + item.slug" class="block">
                                <div class="relative h-44 bg-ink overflow-hidden">
                                    <img x-show="item.image" :src="item.image" :alt="item.title" class="w-full h-full object-cover opacity-85" loading="lazy" onerror="this.style.display='none'">
                                    <div class="absolute inset-0 grid place-items-center" x-show="!item.image"><span class="text-6xl opacity-30">🎫</span></div>
                                    <div class="absolute inset-0 bg-gradient-to-t from-ink/85 via-ink/10 to-transparent"></div>
                                    <span x-show="item.category" class="absolute left-4 top-4 rounded-full bg-paper text-ink px-3 py-1 text-xs font-bold" x-text="item.category"></span>
                                    <span x-show="item.matchScore > 0" class="absolute right-4 top-4 rounded-full bg-mint text-forest px-3 py-1 text-xs font-bold">match <span x-text="item.matchScore"></span></span>
                                    <h3 class="absolute left-4 bottom-4 right-4 font-display text-2xl font-bold text-paper leading-none" x-text="item.title"></h3>
                                </div>
                            </a>
                            <div class="p-4 flex items-center justify-between gap-3">
                                <span class="text-sm font-bold" x-text="item.city || '—'"></span>
                                <a :href="'/activitate/' + item.slug" class="rounded-full bg-vermilion text-paper px-4 py-2 text-xs font-bold hover:bg-vermilion-d transition">Vezi bilete</a>
                            </div>
                        </article>
                    </template>
                </div>

                <div x-show="!loading && filteredItems().length === 0" class="rounded-[2rem] border-2 border-dashed border-ink/20 bg-paper-2/60 p-10 text-center">
                    <p class="text-5xl">✨</p>
                    <p class="mt-4 font-display text-3xl font-bold">Pregătim recomandările tale</p>
                    <p class="mt-2 text-ink-soft">Cumpără sau vizualizează câteva activități și revino — sistemul învață din pașii tăi.</p>
                    <a href="/categorii" class="mt-5 inline-flex rounded-full bg-vermilion text-paper px-6 py-3 font-bold">Descoperă activități</a>
                </div>
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
        items: [],
        topCity: '',
        topCategories: [],
        // Explicit prefs from /cont/setari (settings.interests.*) — these
        // get higher weight than history-derived signals.
        prefCities: [],
        prefCategories: [],
        activeFilter: 'all',
        cityFilter: '',

        init() {
            try { this.isAuth = (window.BileteOnlineAuth && BileteOnlineAuth.isLoggedIn && BileteOnlineAuth.isLoggedIn()); } catch (e) { this.isAuth = false; }
            if (! this.isAuth) { this.loading = false; return; }
            this.load();
        },

        async load() {
            // 1. Explicit prefs from settings (set via /cont/setari → Preferințe client).
            //    These are the user's stated intent — they outrank historical signals.
            try {
                const me = await BileteOnlineAPI.customer.getProfile();
                // /customer/me response varies: customer may be at data.*, data.customer.*, or both
                const u = (me && me.data) || {};
                const settings = (u.customer && u.customer.settings) || u.settings || u.preferences || {};
                const interests = settings.interests || {};
                this.prefCities = (Array.isArray(interests.preferred_cities) ? interests.preferred_cities : []).filter(Boolean);
                this.prefCategories = (Array.isArray(interests.event_categories) ? interests.event_categories : []).filter(Boolean);
            } catch (e) {}

            // 2. Pull taste profile from history (best effort — endpoint may be partial)
            let profileData = null;
            try {
                const p = await BileteOnlineAPI.customer.getProfileData();
                profileData = (p && p.data) || null;
            } catch (e) {}

            try {
                const s = await BileteOnlineAPI.customer.getSmartSuggestions();
                if (s && s.data) profileData = Object.assign({}, profileData || {}, s.data);
            } catch (e) {}

            // 3. Merge — explicit prefs come first, history fills in the gaps
            let historyTopCity = '';
            let historyCats = [];
            if (profileData) {
                historyTopCity = profileData.top_city || profileData.preferred_city || profileData.most_visited_city || '';
                const cats = profileData.top_categories || profileData.preferred_categories || profileData.preferred_genres || [];
                historyCats = (Array.isArray(cats) ? cats : []).map(c => typeof c === 'string' ? c : (c.name || c.slug || '')).filter(Boolean);
            }
            this.topCity = (this.prefCities[0] || historyTopCity || '');
            // Show explicit categories first, then top history categories not already in the list
            const merged = [...this.prefCategories];
            historyCats.forEach(c => { if (! merged.some(m => m.toLowerCase() === c.toLowerCase())) merged.push(c); });
            this.topCategories = merged.slice(0, 8);

            // 4. Pull a pool of recent activities, rank client-side
            try {
                const r = await BileteOnlineAPI.get('/activities?sort=recent&per_page=30');
                const list = (r && r.data && (r.data.items || r.data.activities)) || (r && r.data) || [];
                const pool = (Array.isArray(list) ? list : []).map(a => ({
                    title:        a.title || a.name || '',
                    slug:         a.slug || '',
                    image:        a.cover_image_url || a.image || null,
                    city:         a.city ? (a.city.name || a.city) : '',
                    category:     a.category ? (a.category.name || a.category) : '',
                    categorySlug: a.category ? (a.category.slug || (typeof a.category === 'string' ? a.category.toLowerCase() : '')) : '',
                    tags:         a.tags || [],
                    matchScore:   0,
                }));

                const lcPrefCities = this.prefCities.map(c => (c || '').toLowerCase());
                const lcPrefCats   = this.prefCategories.map(c => (c || '').toLowerCase());
                const lcHistCity   = (historyTopCity || '').toLowerCase();
                const lcHistCats   = historyCats.map(c => (c || '').toLowerCase());

                pool.forEach(p => {
                    let score = 0;
                    const pc = (p.city || '').toLowerCase();
                    const pcat = (p.category || '').toLowerCase();
                    const pcatSlug = (p.categorySlug || '').toLowerCase();

                    // Explicit prefs (higher weight)
                    if (lcPrefCities.length > 0 && lcPrefCities.some(c => pc.includes(c) || c.includes(pc))) score += 5;
                    if (lcPrefCats.length > 0 && (lcPrefCats.includes(pcatSlug) || lcPrefCats.includes(pcat))) score += 4;

                    // History signals (lower weight, additive)
                    if (lcHistCity && pc.includes(lcHistCity)) score += 2;
                    if (lcHistCats.includes(pcat)) score += 1;
                    (p.tags || []).forEach(t => {
                        const tl = ((typeof t === 'string') ? t : (t && (t.name || t.slug)) || '').toLowerCase();
                        if (lcPrefCats.includes(tl)) score += 2;
                        else if (lcHistCats.includes(tl)) score += 1;
                    });

                    p.matchScore = score;
                });
                pool.sort((a, b) => b.matchScore - a.matchScore);

                this.items = pool;
            } catch (e) {}
            this.loading = false;
        },

        filteredItems() {
            const lcFilter = (this.activeFilter || 'all').toLowerCase();
            const lcCity = (this.cityFilter || '').toLowerCase();
            return this.items.filter(p => {
                if (lcFilter !== 'all' && (p.category || '').toLowerCase() !== lcFilter) return false;
                if (lcCity && ! (p.city || '').toLowerCase().includes(lcCity)) return false;
                return true;
            });
        },
    };
}
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>
