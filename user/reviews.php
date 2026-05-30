<?php
/**
 * bilete.online — /cont/recenzii (Recenziile mele, v2 full template)
 *
 * Implements template-client-reviews-bilete-online-v2.html end-to-end:
 *   - Hero w/ REVIEW SCORE card (avg rating, count, pending review hint)
 *   - 4 stats (PUBLICATE / DE EVALUAT / DRAFTURI / MODERARE)
 *   - "De evaluat" section: per pending event a card with star picker +
 *     textarea + suitable-for select + age select + photos upload +
 *     Save draft + Publish CTA + sidebar guide card
 *   - "Istoric" section: search + status + rating filters + 2-col grid of
 *     published/drafts/moderation review cards with edit/view/delete
 *   - 3 bottom utility cards (PERSONALIZARE / COMUNITATE / BONUS)
 *
 * Backend already exposes:
 *   GET    /customer/reviews
 *   POST   /customer/reviews
 *   GET    /customer/reviews/events-to-review
 *   GET    /customer/reviews/{review}
 *   PUT    /customer/reviews/{review}
 *   DELETE /customer/reviews/{review}
 */

require_once __DIR__ . '/../includes/config.php';

$pageTitleRaw    = 'Recenziile mele — ' . SITE_NAME;
$pageDescription = 'Recenziile mele pe bilete.online: scrie pentru activitățile la care ai fost, editează drafturi și ajută alți clienți să aleagă.';
$canonicalUrl    = SITE_URL . '/cont/recenzii';
$noindex         = true;
$currentPage     = 'cont';
$cssBundle       = 'auth';

include __DIR__ . '/../includes/head.php';
include __DIR__ . '/../includes/header.php';
?>

<div class="max-w-[1500px] mx-auto px-4 sm:px-6 py-6 lg:py-8">
    <div class="grid lg:grid-cols-[280px_minmax(0,1fr)] gap-6 lg:gap-8 items-start">

        <?php $currentClientPage = 'reviews'; include __DIR__ . '/../includes/client-sidebar-v2.php'; ?>

        <main class="min-w-0" x-data="clientReviewsPage()" x-init="init()">

            <!-- HERO -->
            <section class="rounded-[2rem] border-2 border-ink bg-ink text-paper p-6 sm:p-8 shadow-deep">
                <div class="grid xl:grid-cols-[1fr_380px] gap-8 items-center">
                    <div>
                        <p class="stamp inline-flex px-3 py-1 text-xs font-mono tracking-[.18em] text-ochre">CLIENT REVIEWS</p>
                        <h1 class="mt-5 font-display text-5xl sm:text-6xl lg:text-7xl font-bold leading-[.85]">Recenziile mele</h1>
                        <p class="mt-5 max-w-3xl text-paper/65 text-lg leading-relaxed">Scrie recenzii pentru activitățile la care ai fost, vezi ce ai publicat, editează drafturi și ajută sistemul să îți recomande experiențe mai potrivite.</p>
                        <div class="mt-7 flex flex-wrap gap-3">
                            <a href="#de-evaluat" class="rounded-full bg-vermilion text-paper px-6 py-4 font-bold hover:bg-vermilion-d transition">Scrie recenzie</a>
                            <a href="/cont/recomandari" class="rounded-full border-2 border-paper/50 px-6 py-4 font-bold hover:bg-paper hover:text-ink transition">Vezi recomandări</a>
                        </div>
                    </div>
                    <div class="rounded-[2rem] border-2 border-paper/20 bg-paper text-ink p-6 rotate-[-2deg]">
                        <p class="font-mono text-xs tracking-[.18em] text-ink-soft">REVIEW SCORE</p>
                        <p class="mt-3 font-display text-7xl font-bold leading-none" x-text="avgRating.toFixed(1)">0.0</p>
                        <p class="mt-2 text-ink-soft">rating mediu oferit · <span x-text="stats.published"></span> recenzii publicate</p>
                        <div class="mt-5 flex text-3xl text-ochre" x-text="starsFor(Math.round(avgRating))">★★★★★</div>
                        <p class="mt-3 text-sm text-ink-soft" x-text="stats.toReview > 0 ? (stats.toReview + ' recenzii așteaptă să fie scrise.') : 'Felicitări! Toate sunt scrise.'"></p>
                    </div>
                </div>
            </section>

            <!-- 4 STATS -->
            <section class="mt-6 grid sm:grid-cols-2 xl:grid-cols-4 gap-4">
                <article class="rounded-[2rem] border-2 border-ink bg-paper p-5 shadow-ticket">
                    <p class="font-mono text-xs tracking-[.18em] text-ink-soft">PUBLICATE</p>
                    <p class="mt-3 font-display text-6xl font-bold" x-text="stats.published">0</p>
                    <p class="mt-1 text-ink-soft">recenzii vizibile</p>
                </article>
                <article class="rounded-[2rem] border-2 border-ink bg-mint p-5 shadow-ticket">
                    <p class="font-mono text-xs tracking-[.18em] text-forest">DE EVALUAT</p>
                    <p class="mt-3 font-display text-6xl font-bold" x-text="stats.toReview">0</p>
                    <p class="mt-1 text-ink-soft" x-text="stats.toReview > 0 ? 'experiențe recente' : 'toate scrise'">—</p>
                </article>
                <article class="rounded-[2rem] border-2 border-ink bg-paper p-5 shadow-ticket">
                    <p class="font-mono text-xs tracking-[.18em] text-ink-soft">DRAFTURI</p>
                    <p class="mt-3 font-display text-6xl font-bold" x-text="stats.draft">0</p>
                    <p class="mt-1 text-ink-soft">nefinalizate</p>
                </article>
                <article class="rounded-[2rem] border-2 border-ink bg-rose p-5 shadow-ticket">
                    <p class="font-mono text-xs tracking-[.18em] text-vermilion">MODERARE</p>
                    <p class="mt-3 font-display text-6xl font-bold" x-text="stats.moderation">0</p>
                    <p class="mt-1 text-ink-soft">în verificare</p>
                </article>
            </section>

            <!-- DE EVALUAT -->
            <section id="de-evaluat" class="mt-6 rounded-[2rem] border-2 border-ink bg-paper p-5 sm:p-6 shadow-ticket">
                <div class="flex flex-col lg:flex-row lg:items-end lg:justify-between gap-4">
                    <div>
                        <p class="font-mono text-xs tracking-[.18em] text-ink-soft">DE EVALUAT</p>
                        <h2 class="mt-2 font-display text-4xl sm:text-5xl font-bold leading-none">Activități care așteaptă recenzia ta</h2>
                    </div>
                    <div x-show="toReviewEvents.length > 1" class="flex gap-2">
                        <button @click="selectEventIdx = Math.max(0, selectEventIdx - 1)" :disabled="selectEventIdx === 0" class="rounded-full border-2 border-ink px-4 py-2 font-bold disabled:opacity-40">←</button>
                        <span class="px-3 py-2 font-bold" x-text="(selectEventIdx + 1) + ' din ' + toReviewEvents.length"></span>
                        <button @click="selectEventIdx = Math.min(toReviewEvents.length - 1, selectEventIdx + 1)" :disabled="selectEventIdx >= toReviewEvents.length - 1" class="rounded-full border-2 border-ink px-4 py-2 font-bold disabled:opacity-40">→</button>
                    </div>
                </div>

                <div x-show="toReviewEvents.length === 0 && !loadingToReview" class="mt-6 rounded-3xl border-2 border-dashed border-ink/20 bg-paper-2/50 p-8 text-center">
                    <p class="text-5xl">✨</p>
                    <p class="mt-3 font-display text-3xl font-bold">Nicio activitate de evaluat acum</p>
                    <p class="mt-2 text-ink-soft">După ce participi la o activitate, va apărea aici pentru recenzie.</p>
                </div>

                <div x-show="loadingToReview" class="mt-6 h-64 rounded-3xl bg-paper-2/60 animate-pulse"></div>

                <div x-show="!loadingToReview && toReviewEvents.length > 0" class="mt-6 grid lg:grid-cols-[1fr_420px] gap-6">
                    <article class="rounded-[2rem] border-2 border-ink bg-paper-2/70 overflow-hidden">
                        <div class="grid md:grid-cols-[260px_1fr]">
                            <img :src="currentEvent?.image || fallbackImage" :alt="currentEvent?.title || ''" class="w-full h-64 md:h-full object-cover" onerror="this.style.opacity='.4'">
                            <div class="p-5 sm:p-6">
                                <div class="flex flex-wrap gap-2">
                                    <span class="rounded-full bg-mint text-forest px-3 py-1 text-xs font-bold">participare confirmată</span>
                                    <span class="rounded-full bg-paper border border-ink/10 px-3 py-1 text-xs font-bold" x-text="currentEvent?.date"></span>
                                </div>
                                <h3 class="mt-4 font-display text-4xl font-bold leading-none" x-text="currentEvent?.title"></h3>
                                <p class="mt-2 text-ink-soft" x-text="(currentEvent?.location || '') + (currentEvent?.tickets_count ? ' · ' + currentEvent.tickets_count + ' bilete scanate' : '')"></p>

                                <div class="mt-5">
                                    <p class="font-bold">Rating rapid</p>
                                    <div class="mt-2 flex gap-1 text-4xl">
                                        <template x-for="star in 5" :key="star">
                                            <button @click="newReview.rating = star" type="button" class="transition" :class="newReview.rating >= star ? 'text-ochre' : 'text-ink/20'">★</button>
                                        </template>
                                    </div>
                                </div>

                                <label class="block mt-5">
                                    <span class="block mb-1.5 text-sm font-bold">Ce ți-a plăcut sau ce ar trebui să știe alții?</span>
                                    <textarea class="field min-h-32" x-model="newReview.text" maxlength="2000" placeholder="Scrie sincer, util și concret. De exemplu: cât a durat, pentru ce vârstă e potrivit, cum a fost accesul, dacă ai merge din nou."></textarea>
                                    <span class="block mt-1 text-xs text-ink-soft" x-text="(newReview.text.length || 0) + ' / 2000'">0 / 2000</span>
                                </label>

                                <div class="mt-4 grid sm:grid-cols-2 gap-3">
                                    <label>
                                        <span class="block mb-1.5 text-sm font-bold">Potrivit pentru</span>
                                        <select class="field" x-model="newReview.suitable">
                                            <option>Copii</option>
                                            <option>Familie</option>
                                            <option>Cuplu</option>
                                            <option>Grupuri</option>
                                            <option>Team building</option>
                                        </select>
                                    </label>
                                    <label>
                                        <span class="block mb-1.5 text-sm font-bold">Vârsta recomandată</span>
                                        <select class="field" x-model="newReview.age">
                                            <option>3–6 ani</option>
                                            <option>6–10 ani</option>
                                            <option>10–14 ani</option>
                                            <option>Adulți</option>
                                            <option>Toate vârstele</option>
                                        </select>
                                    </label>
                                </div>

                                <div x-show="newReview.photos.length > 0" class="mt-4 flex flex-wrap gap-2">
                                    <template x-for="(photo, idx) in newReview.photos" :key="idx">
                                        <div class="relative w-20 h-20 rounded-xl overflow-hidden border border-ink/10">
                                            <img :src="photo" class="w-full h-full object-cover">
                                            <button @click="newReview.photos.splice(idx, 1)" type="button" class="absolute top-1 right-1 w-5 h-5 rounded-full bg-ink text-paper text-xs">×</button>
                                        </div>
                                    </template>
                                </div>

                                <div class="mt-4 flex flex-wrap gap-2">
                                    <label class="rounded-full border border-ink/20 px-4 py-2 text-sm font-bold hover:bg-ink hover:text-paper transition cursor-pointer">
                                        Atașează poze
                                        <input type="file" accept="image/*" multiple class="hidden" @change="handlePhotoUpload($event)">
                                    </label>
                                    <button @click="saveReview('draft')" :disabled="savingReview" class="rounded-full border border-ink/20 px-4 py-2 text-sm font-bold hover:bg-ink hover:text-paper transition disabled:opacity-60">
                                        <span x-show="!savingReview">Salvează draft</span>
                                        <span x-show="savingReview" x-cloak>Se salvează…</span>
                                    </button>
                                    <button @click="saveReview('publish')" :disabled="savingReview || !canSubmit" class="rounded-full bg-vermilion text-paper px-5 py-2 text-sm font-bold hover:bg-vermilion-d transition disabled:opacity-60">
                                        <span x-show="!savingReview">Publică recenzia</span>
                                        <span x-show="savingReview" x-cloak>Se publică…</span>
                                    </button>
                                </div>
                                <div x-show="formMessage" x-cloak class="mt-3 rounded-2xl border-2 px-4 py-3 text-sm font-bold" :class="formMessageType === 'error' ? 'border-vermilion bg-vermilion/10 text-vermilion' : 'border-forest bg-mint text-forest'" x-text="formMessage"></div>
                            </div>
                        </div>
                    </article>

                    <aside class="rounded-[2rem] border-2 border-forest bg-mint p-6">
                        <p class="font-mono text-xs tracking-[.18em] text-forest">GHID RECENZIE BUNĂ</p>
                        <h3 class="mt-2 font-display text-4xl font-bold leading-none">Scrie pentru omul care decide.</h3>
                        <div class="mt-5 space-y-3 text-ink-soft">
                            <p><strong class="text-ink">Concret:</strong> spune durata reală, accesul, aglomerația, vârsta potrivită.</p>
                            <p><strong class="text-ink">Util:</strong> menționează dacă ai merge din nou și cu cine.</p>
                            <p><strong class="text-ink">Corect:</strong> evită date personale sau informații care nu țin de activitate.</p>
                        </div>
                    </aside>
                </div>
            </section>

            <!-- ISTORIC -->
            <section class="mt-6 rounded-[2rem] border-2 border-ink bg-paper p-5 sm:p-6 shadow-ticket">
                <div class="flex flex-col xl:flex-row xl:items-end xl:justify-between gap-4">
                    <div>
                        <p class="font-mono text-xs tracking-[.18em] text-ink-soft">ISTORIC</p>
                        <h2 class="mt-2 font-display text-4xl sm:text-5xl font-bold leading-none">Recenzii publicate și drafturi</h2>
                    </div>
                    <div class="grid sm:grid-cols-3 gap-2">
                        <input class="field" x-model="search" placeholder="Caută recenzie...">
                        <select class="field" x-model="statusFilter">
                            <option value="all">Toate</option>
                            <option value="published">Publicate</option>
                            <option value="draft">Drafturi</option>
                            <option value="moderation">În moderare</option>
                        </select>
                        <select class="field" x-model="ratingFilter">
                            <option value="all">Orice rating</option>
                            <option value="5">5 stele</option>
                            <option value="4">4 stele</option>
                            <option value="3">3 stele</option>
                        </select>
                    </div>
                </div>

                <div x-show="loadingReviews" class="mt-6 grid xl:grid-cols-2 gap-5">
                    <div class="h-44 rounded-[2rem] bg-paper-2/60 animate-pulse"></div>
                    <div class="h-44 rounded-[2rem] bg-paper-2/60 animate-pulse"></div>
                </div>

                <div x-show="!loadingReviews && filteredReviews().length > 0" class="mt-6 grid xl:grid-cols-2 gap-5">
                    <template x-for="review in filteredReviews()" :key="review.id">
                        <article class="rounded-[2rem] border-2 border-ink bg-paper overflow-hidden">
                            <div class="grid sm:grid-cols-[180px_1fr]">
                                <img :src="review.image || fallbackImage" :alt="review.title" class="w-full h-48 sm:h-full object-cover" onerror="this.style.opacity='.4'">
                                <div class="p-5">
                                    <div class="flex flex-wrap gap-2">
                                        <span class="rounded-full px-3 py-1 text-xs font-bold" :class="review.statusClass" x-text="review.statusLabel"></span>
                                        <span class="rounded-full bg-paper-2 border border-ink/10 px-3 py-1 text-xs font-bold" x-text="review.date"></span>
                                    </div>
                                    <h3 class="mt-3 font-display text-3xl font-bold leading-none" x-text="review.title"></h3>
                                    <p class="mt-1 text-ink-soft" x-text="review.location"></p>
                                    <div class="mt-3 text-2xl text-ochre" x-text="starsFor(review.rating)"></div>
                                    <p class="mt-3 text-ink-soft leading-relaxed line-clamp-3" x-text="review.text"></p>
                                    <div class="mt-4 flex flex-wrap gap-2">
                                        <button @click="editReview(review)" class="rounded-full border border-ink/20 px-4 py-2 text-sm font-bold hover:bg-ink hover:text-paper transition">Editează</button>
                                        <a :href="review.activityUrl" class="rounded-full border border-ink/20 px-4 py-2 text-sm font-bold hover:bg-ink hover:text-paper transition">Vezi activitatea</a>
                                        <button @click="deleteReview(review)" class="rounded-full border border-vermilion/30 bg-rose text-vermilion px-4 py-2 text-sm font-bold hover:bg-vermilion hover:text-paper transition">Șterge</button>
                                    </div>
                                </div>
                            </div>
                        </article>
                    </template>
                </div>

                <div x-show="!loadingReviews && filteredReviews().length === 0" class="mt-6 rounded-[2rem] border-2 border-ink bg-paper-2 p-8 text-center">
                    <p class="font-display text-4xl font-bold" x-text="reviews.length === 0 ? 'Încă nu ai recenzii' : 'Nu am găsit recenzii pentru filtre'"></p>
                    <p class="mt-2 text-ink-soft">Schimbă filtrele sau caută după alt termen.</p>
                </div>
            </section>

            <!-- 3 BOTTOM CARDS -->
            <section class="mt-6 grid md:grid-cols-3 gap-5">
                <article class="rounded-[2rem] border-2 border-ink bg-paper p-6 shadow-ticket">
                    <p class="font-mono text-xs tracking-[.18em] text-ink-soft">PERSONALIZARE</p>
                    <h2 class="mt-2 font-display text-4xl font-bold leading-none">Recenziile antrenează recomandările.</h2>
                    <p class="mt-3 text-ink-soft">Ratingurile și preferințele din review-uri pot îmbunătăți recomandările viitoare.</p>
                </article>
                <article class="rounded-[2rem] border-2 border-ink bg-mint p-6 shadow-ticket">
                    <p class="font-mono text-xs tracking-[.18em] text-forest">COMUNITATE</p>
                    <h2 class="mt-2 font-display text-4xl font-bold leading-none">Ajută alți clienți.</h2>
                    <p class="mt-3 text-ink-soft">O recenzie bună reduce incertitudinea și crește încrederea în activități.</p>
                </article>
                <article class="rounded-[2rem] border-2 border-ink bg-vermilion text-paper p-6 shadow-ticket">
                    <p class="font-mono text-xs tracking-[.18em] text-paper/60">BONUS</p>
                    <h2 class="mt-2 font-display text-4xl font-bold leading-none">Review-uri cu puncte?</h2>
                    <p class="mt-3 text-paper/70">Recenziile eligibile pot aduce puncte bonus când campaniile sunt active.</p>
                </article>
            </section>

            <!-- AUTH GUARD -->
            <div x-show="!isAuth" x-cloak class="mt-8 rounded-[2rem] border-2 border-vermilion bg-rose p-8 text-center">
                <p class="font-display text-3xl font-bold text-vermilion">Trebuie să fii autentificat</p>
                <a href="/autentificare?redirect=/cont/recenzii" class="mt-5 inline-flex rounded-full bg-vermilion text-paper px-6 py-3 font-bold">Intră în cont</a>
            </div>
        </main>
    </div>
</div>

<script>
function clientReviewsPage() {
    return {
        isAuth: true,
        loadingToReview: true,
        loadingReviews: true,
        savingReview: false,
        formMessage: '',
        formMessageType: 'success',

        toReviewEvents: [],
        selectEventIdx: 0,
        reviews: [],

        search: '',
        statusFilter: 'all',
        ratingFilter: 'all',

        newReview: { rating: 0, text: '', suitable: 'Familie', age: 'Toate vârstele', photos: [], editingId: null },

        fallbackImage: 'data:image/svg+xml;utf8,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100"><rect fill="%231B1714" width="100" height="100"/><text x="50" y="58" text-anchor="middle" fill="%23E84527" font-size="40">⭐</text></svg>',

        get currentEvent() { return this.toReviewEvents[this.selectEventIdx] || null; },

        get canSubmit() {
            return this.currentEvent && this.newReview.rating > 0 && (this.newReview.text || '').trim().length >= 10;
        },

        get stats() {
            const published = this.reviews.filter(r => r.statusKey === 'published').length;
            const draft     = this.reviews.filter(r => r.statusKey === 'draft').length;
            const moderation= this.reviews.filter(r => r.statusKey === 'moderation').length;
            return { published, draft, moderation, toReview: this.toReviewEvents.length };
        },

        get avgRating() {
            const published = this.reviews.filter(r => r.statusKey === 'published');
            if (published.length === 0) return 0;
            return published.reduce((s, r) => s + (r.rating || 0), 0) / published.length;
        },

        init() {
            try { this.isAuth = (window.BileteOnlineAuth && BileteOnlineAuth.isLoggedIn && BileteOnlineAuth.isLoggedIn()); } catch (e) { this.isAuth = false; }
            if (! this.isAuth) { this.loadingToReview = false; this.loadingReviews = false; return; }
            this.loadToReview();
            this.loadReviews();
        },

        async loadToReview() {
            try {
                const r = await BileteOnlineAPI.get('/customer/reviews/events-to-review');
                const list = (r && r.data && (r.data.events || r.data.items)) || (r && r.data) || [];
                this.toReviewEvents = (Array.isArray(list) ? list : []).map(e => this.normalizeEvent(e));
            } catch (e) {}
            this.loadingToReview = false;
        },

        async loadReviews() {
            try {
                const r = await BileteOnlineAPI.get('/customer/reviews', { per_page: 50 });
                const list = (r && r.data && (r.data.reviews || r.data.items)) || (r && r.data) || [];
                this.reviews = (Array.isArray(list) ? list : []).map(r => this.normalizeReview(r));
            } catch (e) {}
            this.loadingReviews = false;
        },

        normalizeEvent(ev) {
            const date = ev.event_date || ev.date || ev.last_attended_at;
            return {
                id:    ev.id || ev.event_id || ev.slug,
                title: ev.title || ev.name,
                location: (ev.venue && (ev.venue.name || ev.venue)) || ev.location || '',
                image: ev.cover_image_url || ev.image || null,
                tickets_count: ev.tickets_count || ev.attended_tickets || 0,
                date:  date ? new Date(date).toLocaleDateString('ro-RO', { day: 'numeric', month: 'long', year: 'numeric' }) : '',
                slug:  ev.slug || '',
                activityUrl: ev.slug ? ('/activitate/' + ev.slug) : '#',
            };
        },

        normalizeReview(r) {
            const sRaw = (r.status || 'published').toLowerCase();
            let key = 'published', label = 'publicată', cls = 'bg-mint text-forest';
            if (sRaw === 'draft' || sRaw === 'pending_publish') { key = 'draft'; label = 'draft'; cls = 'bg-ochre text-ink'; }
            else if (sRaw === 'pending' || sRaw === 'pending_moderation' || sRaw === 'moderation' || sRaw === 'flagged') { key = 'moderation'; label = 'în moderare'; cls = 'bg-rose text-vermilion'; }

            const event = r.event || {};
            return {
                id:      r.id,
                title:   (event.title || event.name) || r.event_title || 'Activitate',
                location:(event.venue && (event.venue.name || event.venue)) || r.location || event.city || '',
                image:   event.cover_image_url || event.image || r.image || null,
                date:    r.created_at ? new Date(r.created_at).toLocaleDateString('ro-RO', { day: 'numeric', month: 'long', year: 'numeric' }) : '',
                rating:  parseInt(r.rating || 0) || 0,
                text:    r.text || r.body || r.comment || '',
                suitable:r.suitable_for || r.meta?.suitable || 'Familie',
                age:     r.age_group || r.meta?.age || 'Toate vârstele',
                statusKey:  key,
                statusLabel:label,
                statusClass:cls,
                activityUrl: event.slug ? ('/activitate/' + event.slug) : '#',
            };
        },

        starsFor(n) {
            n = parseInt(n) || 0;
            return '★'.repeat(Math.max(0, Math.min(5, n))) + '☆'.repeat(Math.max(0, 5 - n));
        },

        handlePhotoUpload(ev) {
            const files = Array.from(ev.target.files || []).slice(0, 5 - this.newReview.photos.length);
            files.forEach(file => {
                if (! file.type.startsWith('image/')) return;
                if (file.size > 5 * 1024 * 1024) {
                    this.flashForm('Imaginile trebuie să fie sub 5 MB.', 'error');
                    return;
                }
                const reader = new FileReader();
                reader.onload = e => { this.newReview.photos.push(e.target.result); };
                reader.readAsDataURL(file);
            });
        },

        async saveReview(mode) {
            if (mode === 'publish' && ! this.canSubmit) {
                this.flashForm('Adaugă cel puțin 10 caractere și un rating înainte de publicare.', 'error');
                return;
            }
            if (! this.currentEvent && ! this.newReview.editingId) return;

            this.savingReview = true;
            try {
                const payload = {
                    event_id: this.currentEvent ? this.currentEvent.id : undefined,
                    rating:   this.newReview.rating,
                    text:     this.newReview.text,
                    body:     this.newReview.text,
                    suitable_for: this.newReview.suitable,
                    age_group:    this.newReview.age,
                    photos:       this.newReview.photos,
                    status:       mode === 'publish' ? 'published' : 'draft',
                };

                let r;
                if (this.newReview.editingId) {
                    r = await BileteOnlineAPI.put('/customer/reviews/' + this.newReview.editingId, payload);
                } else {
                    r = await BileteOnlineAPI.post('/customer/reviews', payload);
                }

                if (r && r.success) {
                    this.flashForm(mode === 'publish' ? 'Recenzia a fost publicată. Mulțumim!' : 'Draft salvat.', 'success');
                    this.newReview = { rating: 0, text: '', suitable: 'Familie', age: 'Toate vârstele', photos: [], editingId: null };
                    await this.loadReviews();
                    await this.loadToReview();
                    if (this.selectEventIdx >= this.toReviewEvents.length) this.selectEventIdx = Math.max(0, this.toReviewEvents.length - 1);
                } else {
                    this.flashForm((r && r.message) || 'Nu am putut salva recenzia.', 'error');
                }
            } catch (e) {
                this.flashForm((e && e.message) || 'Eroare la salvare.', 'error');
            }
            this.savingReview = false;
        },

        editReview(review) {
            this.newReview = {
                rating: review.rating,
                text:   review.text,
                suitable: review.suitable || 'Familie',
                age:    review.age || 'Toate vârstele',
                photos: [],
                editingId: review.id,
            };
            try { document.getElementById('de-evaluat').scrollIntoView({ behavior: 'smooth', block: 'start' }); } catch (e) {}
        },

        async deleteReview(review) {
            if (! confirm('Sigur ștergi recenzia pentru "' + review.title + '"?')) return;
            try {
                const r = await BileteOnlineAPI.delete('/customer/reviews/' + review.id, {});
                if (r && r.success) {
                    this.reviews = this.reviews.filter(x => x.id !== review.id);
                }
            } catch (e) {
                alert('Eroare la ștergere.');
            }
        },

        flashForm(msg, type) {
            this.formMessage = msg;
            this.formMessageType = type || 'success';
            setTimeout(() => { this.formMessage = ''; }, 4500);
        },

        filteredReviews() {
            const q = (this.search || '').toLowerCase().trim();
            return this.reviews.filter(r => {
                if (this.statusFilter !== 'all' && r.statusKey !== this.statusFilter) return false;
                if (this.ratingFilter !== 'all' && String(r.rating) !== this.ratingFilter) return false;
                if (! q) return true;
                return (r.title + ' ' + r.text + ' ' + r.location).toLowerCase().includes(q);
            });
        },
    };
}
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>
