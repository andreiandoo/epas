<?php
/**
 * bilete.online — /cont (client dashboard, v2 design)
 *
 * Welcome card + 4 stat tiles + upcoming events rail + quick actions +
 * recent activity. All data flows through BileteOnlineAPI.customer.* and
 * is rendered Alpine-side; PHP is just the scaffold + SEO.
 */

require_once __DIR__ . '/../includes/config.php';

$pageTitleRaw    = 'Contul meu — ' . SITE_NAME;
$pageDescription = 'Dashboardul tău pe bilete.online: bilete viitoare, comenzi, puncte bonus, recomandări și acțiuni rapide.';
$canonicalUrl    = SITE_URL . '/cont';
$noindex         = true;
$currentPage     = 'cont';
$cssBundle       = 'auth';

include __DIR__ . '/../includes/head.php';
include __DIR__ . '/../includes/header.php';
?>

<div class="max-w-[1500px] mx-auto px-4 sm:px-6 py-6 lg:py-8">
    <div class="grid lg:grid-cols-[280px_minmax(0,1fr)] gap-6 lg:gap-8 items-start">

        <?php $currentClientPage = 'dashboard'; include __DIR__ . '/../includes/client-sidebar-v2.php'; ?>

        <main class="min-w-0" x-data="clientDashboardPage()" x-init="init()">

            <!-- HERO / WELCOME -->
            <section class="rounded-[2rem] border-2 border-ink bg-ink text-paper p-6 sm:p-8 shadow-deep">
                <div class="grid xl:grid-cols-[1fr_360px] gap-8 items-center">
                    <div>
                        <p class="stamp inline-flex px-3 py-1 text-xs font-mono tracking-[.18em] text-ochre">CONT CLIENT</p>
                        <h1 class="mt-5 font-display text-5xl sm:text-6xl lg:text-7xl font-bold leading-[.85]">
                            Bine ai revenit, <span x-text="firstName || 'prieten'">prieten</span>!
                        </h1>
                        <p class="mt-5 max-w-3xl text-paper/65 text-lg leading-relaxed">
                            Vezi biletele tale viitoare, ultimele comenzi și punctele bonus disponibile. De aici poți acționa rapid pe orice.
                        </p>
                        <div class="mt-7 flex flex-wrap gap-3">
                            <a href="/cont/bilete" class="rounded-full bg-vermilion text-paper px-6 py-4 font-bold hover:bg-vermilion-d transition">Vezi biletele</a>
                            <a href="/categorii" class="rounded-full border-2 border-paper/50 px-6 py-4 font-bold hover:bg-paper hover:text-ink transition">Descoperă activități</a>
                        </div>
                    </div>

                    <!-- Points ticket -->
                    <div class="ticket relative bg-paper text-ink rounded-[2rem] border-2 border-paper/30 p-6 rotate-[-2deg]" style="--perf:100%">
                        <p class="font-mono text-xs tracking-[.18em] text-ink-soft">PUNCTE BONUS</p>
                        <p class="mt-2 font-display text-6xl font-bold leading-none" x-text="(stats.points_balance ?? 0).toLocaleString('ro-RO')">0</p>
                        <p class="mt-2 text-ink-soft text-sm">1 punct = 0,10 lei la următoarea comandă</p>
                        <a href="/cont/puncte" class="mt-4 inline-flex rounded-full bg-ink text-paper px-4 py-2 text-sm font-bold hover:bg-vermilion transition">Folosește punctele</a>
                    </div>
                </div>
            </section>

            <!-- STATS -->
            <section class="mt-6 grid sm:grid-cols-2 xl:grid-cols-4 gap-4">
                <article class="rounded-[2rem] border-2 border-ink bg-paper p-5 shadow-ticket">
                    <p class="font-mono text-xs tracking-[.18em] text-ink-soft">BILETE VIITOARE</p>
                    <p class="mt-3 font-display text-6xl font-bold" x-text="stats.upcoming_tickets_count ?? 0">0</p>
                    <p class="mt-1 text-ink-soft">gata de scanare</p>
                </article>
                <article class="rounded-[2rem] border-2 border-ink bg-mint p-5 shadow-ticket">
                    <p class="font-mono text-xs tracking-[.18em] text-forest">COMENZI</p>
                    <p class="mt-3 font-display text-6xl font-bold" x-text="stats.orders_count ?? 0">0</p>
                    <p class="mt-1 text-ink-soft">total în istoric</p>
                </article>
                <article class="rounded-[2rem] border-2 border-ink bg-paper p-5 shadow-ticket">
                    <p class="font-mono text-xs tracking-[.18em] text-ink-soft">CHELTUIT</p>
                    <p class="mt-3 font-display text-5xl font-bold" x-text="formatLei(stats.total_spent)">—</p>
                    <p class="mt-1 text-ink-soft">pe experiențe</p>
                </article>
                <article class="rounded-[2rem] border-2 border-ink bg-rose p-5 shadow-ticket">
                    <p class="font-mono text-xs tracking-[.18em] text-vermilion">ECONOMISIT</p>
                    <p class="mt-3 font-display text-5xl font-bold" x-text="formatLei(stats.total_saved)">—</p>
                    <p class="mt-1 text-ink-soft">prin coduri și puncte</p>
                </article>
            </section>

            <!-- UPCOMING EVENTS -->
            <section class="mt-8">
                <div class="flex items-end justify-between gap-3 mb-4">
                    <div>
                        <p class="font-mono text-xs tracking-[.18em] text-ink-soft">URMĂTOARELE EVENIMENTE</p>
                        <h2 class="mt-1 font-display text-4xl sm:text-5xl font-bold leading-none">Ce urmează</h2>
                    </div>
                    <a href="/cont/bilete" class="text-vermilion font-bold underline-wobble hidden sm:inline-flex">Vezi toate biletele →</a>
                </div>

                <div x-show="loading" class="grid sm:grid-cols-2 xl:grid-cols-3 gap-4">
                    <div class="h-44 rounded-[2rem] border-2 border-ink/10 bg-paper-2/60 animate-pulse"></div>
                    <div class="h-44 rounded-[2rem] border-2 border-ink/10 bg-paper-2/60 animate-pulse"></div>
                    <div class="h-44 rounded-[2rem] border-2 border-ink/10 bg-paper-2/60 animate-pulse"></div>
                </div>

                <div x-show="!loading && upcoming.length > 0" class="grid sm:grid-cols-2 xl:grid-cols-3 gap-4">
                    <template x-for="ev in upcoming" :key="ev.id || ev.slug">
                        <article class="rounded-[2rem] border-2 border-ink bg-paper p-5 shadow-ticket hover:-translate-y-1 transition">
                            <p class="font-mono text-xs tracking-[.18em] text-vermilion" x-text="formatDate(ev.date || ev.start_date)"></p>
                            <h3 class="mt-2 font-display text-2xl font-bold leading-none" x-text="ev.title || ev.name"></h3>
                            <p class="mt-1 text-ink-soft text-sm" x-text="(ev.venue && (ev.venue.name || ev.venue)) || ev.location || ''"></p>
                            <div class="mt-4 flex flex-wrap items-center gap-2">
                                <span class="rounded-full bg-mint text-forest px-3 py-1 text-xs font-bold" x-text="(ev.tickets_count || 1) + ' bilete'"></span>
                                <a :href="ev.slug ? ('/cont/bilete') : '/cont/bilete'" class="rounded-full bg-ink text-paper px-4 py-2 text-xs font-bold ml-auto hover:bg-vermilion transition">Deschide QR</a>
                            </div>
                        </article>
                    </template>
                </div>

                <div x-show="!loading && upcoming.length === 0" class="rounded-[2rem] border-2 border-dashed border-ink/20 bg-paper-2/60 p-10 text-center">
                    <p class="text-5xl">🎟️</p>
                    <p class="mt-4 font-display text-3xl font-bold">Nu ai bilete viitoare</p>
                    <p class="mt-2 text-ink-soft">Descoperă activități și rezervă online cu bilete cu QR.</p>
                    <a href="/categorii" class="mt-5 inline-flex rounded-full bg-vermilion text-paper px-6 py-3 font-bold">Descoperă activități</a>
                </div>
            </section>

            <!-- QUICK ACTIONS -->
            <section class="mt-8">
                <p class="font-mono text-xs tracking-[.18em] text-ink-soft">ACȚIUNI RAPIDE</p>
                <h2 class="mt-1 font-display text-3xl font-bold leading-none">Mai des folosite</h2>
                <div class="mt-4 grid sm:grid-cols-2 xl:grid-cols-4 gap-4">
                    <a href="/cont/bilete" class="group rounded-3xl border-2 border-ink bg-paper p-5 hover:-translate-y-1 transition shadow-ticket">
                        <p class="text-3xl">🎟️</p>
                        <h3 class="mt-3 font-display text-2xl font-bold">Biletele mele</h3>
                        <p class="mt-1 text-ink-soft text-sm">QR, PDF, calendar, beneficiari</p>
                    </a>
                    <a href="/cont/comenzi" class="group rounded-3xl border-2 border-ink bg-paper p-5 hover:-translate-y-1 transition shadow-ticket">
                        <p class="text-3xl">🧾</p>
                        <h3 class="mt-3 font-display text-2xl font-bold">Comenzile mele</h3>
                        <p class="mt-1 text-ink-soft text-sm">istoric, retururi, facturi</p>
                    </a>
                    <a href="/cont/recomandari" class="group rounded-3xl border-2 border-ink bg-mint p-5 hover:-translate-y-1 transition shadow-ticket">
                        <p class="text-3xl">✨</p>
                        <h3 class="mt-3 font-display text-2xl font-bold">Recomandări</h3>
                        <p class="mt-1 text-ink-soft text-sm">activități după gusturile tale</p>
                    </a>
                    <a href="/recuperare-comanda" class="group rounded-3xl border-2 border-ink bg-rose p-5 hover:-translate-y-1 transition shadow-ticket">
                        <p class="text-3xl">🔎</p>
                        <h3 class="mt-3 font-display text-2xl font-bold">Recuperare comandă</h3>
                        <p class="mt-1 text-ink-soft text-sm">găsește bilete după cod + email</p>
                    </a>
                </div>
            </section>

            <!-- NOT LOGGED IN GUARD -->
            <div x-show="!isAuth" x-cloak class="mt-8 rounded-[2rem] border-2 border-vermilion bg-rose p-8 text-center">
                <p class="font-display text-3xl font-bold text-vermilion">Trebuie să fii autentificat</p>
                <p class="mt-2 text-ink-soft">Intră în cont pentru a vedea dashboardul, biletele și punctele tale.</p>
                <a href="/autentificare?redirect=/cont" class="mt-5 inline-flex rounded-full bg-vermilion text-paper px-6 py-3 font-bold">Intră în cont</a>
            </div>
        </main>
    </div>
</div>

<script>
function clientDashboardPage() {
    return {
        loading: true,
        isAuth: true,
        firstName: '',
        stats: {},
        upcoming: [],

        init() {
            try { this.isAuth = (window.BileteOnlineAuth && BileteOnlineAuth.isLoggedIn && BileteOnlineAuth.isLoggedIn()); } catch (e) { this.isAuth = false; }
            if (! this.isAuth) { this.loading = false; return; }

            // Get cached user for instant greeting
            try {
                const u = window.BileteOnlineAuth && BileteOnlineAuth.getUser ? BileteOnlineAuth.getUser() : null;
                if (u) this.firstName = u.first_name || (u.name ? u.name.split(' ')[0] : '');
            } catch (e) {}

            this.loadStats();
            this.loadUpcoming();
        },

        async loadStats() {
            try {
                const r = await BileteOnlineAPI.customer.getDashboardStats();
                if (r && r.data) {
                    const s = r.data.stats || r.data;
                    // Normalize fields across possible API shapes
                    this.stats = {
                        upcoming_tickets_count: s.upcoming_tickets_count ?? s.tickets_count ?? s.upcoming_count ?? 0,
                        orders_count:           s.orders_count ?? s.total_orders ?? 0,
                        total_spent:            s.total_spent ?? s.spent_total ?? 0,
                        total_saved:            s.total_saved ?? s.saved_total ?? 0,
                        points_balance:         s.points_balance ?? (s.points && s.points.balance) ?? 0,
                    };
                }
            } catch (e) { /* keep zeros */ }
        },

        async loadUpcoming() {
            try {
                const r = await BileteOnlineAPI.customer.getUpcomingEvents(6);
                const events = (r && r.data && (r.data.events || r.data.items)) || (r && r.data) || [];
                this.upcoming = Array.isArray(events) ? events.slice(0, 6) : [];
            } catch (e) {}
            this.loading = false;
        },

        formatLei(n) {
            if (n == null) return '—';
            return new Intl.NumberFormat('ro-RO', { maximumFractionDigits: 0 }).format(n) + ' lei';
        },
        formatDate(d) {
            if (! d) return '';
            try {
                const dt = new Date(d);
                if (isNaN(dt)) return d;
                const months = ['ian','feb','mar','apr','mai','iun','iul','aug','sep','oct','noi','dec'];
                return dt.getDate() + ' ' + months[dt.getMonth()] + ' ' + dt.getFullYear();
            } catch (e) { return d; }
        },
    };
}
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>
