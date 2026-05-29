<?php
/**
 * bilete.online — /cont/puncte (Punctele mele, v2 design)
 *
 * Loyalty dashboard: current balance + lifetime + tier progress + recent
 * points-history feed. Data: BileteOnlineAPI.customer.getRewardsOverview()
 * + getPointsHistory(). No XP/badges UI for v1 — focused on the headline
 * mechanic: 1 punct la fiecare 10 lei cheltuiți.
 */

require_once __DIR__ . '/../includes/config.php';

$pageTitleRaw    = 'Punctele mele — ' . SITE_NAME;
$pageDescription = 'Solul tău de puncte bonus pe bilete.online, istoric tranzacții și cum poți folosi punctele la următoarea comandă.';
$canonicalUrl    = SITE_URL . '/cont/puncte';
$noindex         = true;
$currentPage     = 'cont';
$cssBundle       = 'auth';

include __DIR__ . '/../includes/head.php';
include __DIR__ . '/../includes/header.php';
?>

<div class="max-w-[1500px] mx-auto px-4 sm:px-6 py-6 lg:py-8">
    <div class="grid lg:grid-cols-[280px_minmax(0,1fr)] gap-6 lg:gap-8 items-start">

        <?php $currentClientPage = 'points'; include __DIR__ . '/../includes/client-sidebar-v2.php'; ?>

        <main class="min-w-0" x-data="clientRewardsPage()" x-init="init()">

            <!-- HERO -->
            <section class="rounded-[2rem] border-2 border-ink bg-ink text-paper p-6 sm:p-8 shadow-deep">
                <div class="grid xl:grid-cols-[1fr_360px] gap-8 items-center">
                    <div>
                        <p class="stamp inline-flex px-3 py-1 text-xs font-mono tracking-[.18em] text-ochre">PUNCTE BONUS</p>
                        <h1 class="mt-5 font-display text-5xl sm:text-6xl lg:text-7xl font-bold leading-[.85]">Punctele mele</h1>
                        <p class="mt-5 max-w-3xl text-paper/65 text-lg leading-relaxed">
                            La fiecare 10 lei cheltuiți primești 1 punct. Punctele se pot folosi la următoarea comandă (1 punct = 0,10 lei reducere).
                        </p>
                        <div class="mt-7 flex flex-wrap gap-3">
                            <a href="/categorii" class="rounded-full bg-vermilion text-paper px-6 py-4 font-bold hover:bg-vermilion-d transition">Folosește puncte</a>
                            <a href="/cont/bilete" class="rounded-full border-2 border-paper/50 px-6 py-4 font-bold hover:bg-paper hover:text-ink transition">Vezi biletele</a>
                        </div>
                    </div>

                    <!-- Balance card -->
                    <div class="ticket relative bg-paper text-ink rounded-[2rem] border-2 border-paper/30 p-6 rotate-[-2deg]" style="--perf:100%">
                        <p class="font-mono text-xs tracking-[.18em] text-ink-soft">SOLD ACTUAL</p>
                        <p class="mt-2 font-display text-7xl font-bold leading-none" x-text="(balance || 0).toLocaleString('ro-RO')">0</p>
                        <p class="mt-1 font-mono text-xs text-ink-soft">PUNCTE</p>
                        <div class="mt-4 grid grid-cols-2 gap-2 text-center text-xs">
                            <div class="rounded-xl bg-paper-2 border border-ink/10 p-2">
                                <p class="font-mono text-ink-soft tracking-wider">VALOARE</p>
                                <p class="font-bold mt-1" x-text="((balance || 0) * 0.1).toFixed(2) + ' lei'">0 lei</p>
                            </div>
                            <div class="rounded-xl bg-paper-2 border border-ink/10 p-2">
                                <p class="font-mono text-ink-soft tracking-wider">TOTAL CÂȘTIGAT</p>
                                <p class="font-bold mt-1" x-text="(lifetime || 0).toLocaleString('ro-RO')">0</p>
                            </div>
                        </div>
                    </div>
                </div>
            </section>

            <!-- TIER PROGRESS -->
            <section class="mt-6 rounded-[2rem] border-2 border-ink bg-paper p-6 shadow-ticket">
                <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 mb-5">
                    <div>
                        <p class="font-mono text-xs tracking-[.18em] text-ink-soft">NIVELUL TĂU</p>
                        <h2 class="mt-1 font-display text-3xl font-bold" x-text="currentTier.name">Începător</h2>
                    </div>
                    <span class="inline-flex rounded-full bg-mint text-forest px-3 py-1 text-xs font-bold" x-text="nextTier ? ((nextTier.threshold - lifetime).toLocaleString('ro-RO') + ' până la ' + nextTier.name) : 'Nivel maxim atins'"></span>
                </div>
                <div class="h-4 rounded-full bg-paper-2 border border-ink/10 overflow-hidden">
                    <div class="h-full bg-gradient-to-r from-vermilion to-ochre transition-all" :style="'width:' + tierProgressPct + '%'"></div>
                </div>
                <div class="mt-4 grid grid-cols-4 gap-2 text-center text-xs">
                    <template x-for="t in tiers" :key="t.name">
                        <div :class="lifetime >= t.threshold ? 'bg-ink text-paper' : 'bg-paper-2'" class="rounded-xl p-2">
                            <p class="font-bold" x-text="t.name"></p>
                            <p class="opacity-70 mt-0.5" x-text="t.threshold.toLocaleString('ro-RO') + ' pct'"></p>
                        </div>
                    </template>
                </div>
            </section>

            <!-- HOW IT WORKS -->
            <section class="mt-6 grid sm:grid-cols-3 gap-4">
                <article class="rounded-[2rem] border-2 border-ink bg-paper p-5 shadow-ticket">
                    <p class="text-3xl">🛒</p>
                    <h3 class="mt-3 font-display text-2xl font-bold">Cumperi un bilet</h3>
                    <p class="mt-1 text-ink-soft text-sm">Primești 1 punct pentru fiecare 10 lei cheltuiți.</p>
                </article>
                <article class="rounded-[2rem] border-2 border-ink bg-mint p-5 shadow-ticket">
                    <p class="text-3xl">⭐</p>
                    <h3 class="mt-3 font-display text-2xl font-bold">Strânge puncte</h3>
                    <p class="mt-1 text-ink-soft text-sm">Punctele apar instant în sold după confirmarea comenzii.</p>
                </article>
                <article class="rounded-[2rem] border-2 border-ink bg-rose p-5 shadow-ticket">
                    <p class="text-3xl">🎁</p>
                    <h3 class="mt-3 font-display text-2xl font-bold">Folosește punctele</h3>
                    <p class="mt-1 text-ink-soft text-sm">La checkout, alege câte puncte vrei să folosești.</p>
                </article>
            </section>

            <!-- HISTORY -->
            <section class="mt-6 rounded-[2rem] border-2 border-ink bg-paper p-6 shadow-ticket">
                <div class="flex items-end justify-between gap-3 mb-5">
                    <div>
                        <p class="font-mono text-xs tracking-[.18em] text-ink-soft">ISTORIC</p>
                        <h2 class="mt-1 font-display text-3xl font-bold">Tranzacții recente</h2>
                    </div>
                    <span class="text-sm text-ink-soft" x-text="history.length + ' intrări'"></span>
                </div>

                <div x-show="loading" class="space-y-2">
                    <div class="h-16 rounded-2xl bg-paper-2/60 animate-pulse"></div>
                    <div class="h-16 rounded-2xl bg-paper-2/60 animate-pulse"></div>
                </div>

                <div x-show="!loading && history.length > 0" class="divide-y divide-dashed divide-ink/15">
                    <template x-for="entry in history" :key="entry.id || (entry.created_at + entry.amount)">
                        <div class="py-4 flex flex-wrap items-center gap-4">
                            <div class="grid place-items-center w-12 h-12 rounded-full" :class="entry.amount > 0 ? 'bg-mint text-forest' : 'bg-rose text-vermilion'">
                                <span class="font-bold text-lg" x-text="entry.amount > 0 ? '+' : '−'"></span>
                            </div>
                            <div class="min-w-0 flex-1">
                                <p class="font-bold" x-text="entry.description || (entry.amount > 0 ? 'Puncte câștigate' : 'Puncte folosite')"></p>
                                <p class="text-sm text-ink-soft" x-text="formatDate(entry.created_at)"></p>
                            </div>
                            <span class="font-display text-2xl font-bold" :class="entry.amount > 0 ? 'text-forest' : 'text-vermilion'" x-text="(entry.amount > 0 ? '+' : '') + (entry.amount || 0).toLocaleString('ro-RO')"></span>
                        </div>
                    </template>
                </div>

                <div x-show="!loading && history.length === 0" class="rounded-2xl border-2 border-dashed border-ink/20 p-8 text-center">
                    <p class="text-4xl">📭</p>
                    <p class="mt-3 font-display text-2xl font-bold">Nu ai tranzacții încă</p>
                    <p class="mt-1 text-ink-soft text-sm">Cumpără primul bilet ca să începi să câștigi puncte.</p>
                </div>
            </section>

            <!-- AUTH GUARD -->
            <div x-show="!isAuth" x-cloak class="mt-8 rounded-[2rem] border-2 border-vermilion bg-rose p-8 text-center">
                <p class="font-display text-3xl font-bold text-vermilion">Trebuie să fii autentificat</p>
                <a href="/autentificare?redirect=/cont/puncte" class="mt-5 inline-flex rounded-full bg-vermilion text-paper px-6 py-3 font-bold">Intră în cont</a>
            </div>
        </main>
    </div>
</div>

<script>
function clientRewardsPage() {
    return {
        loading: true,
        isAuth: true,
        balance: 0,
        lifetime: 0,
        history: [],
        tiers: [
            { name: 'Începător', threshold: 0 },
            { name: 'Activ',     threshold: 500 },
            { name: 'Fidel',     threshold: 2000 },
            { name: 'VIP',       threshold: 5000 },
        ],

        init() {
            try { this.isAuth = (window.BileteOnlineAuth && BileteOnlineAuth.isLoggedIn && BileteOnlineAuth.isLoggedIn()); } catch (e) { this.isAuth = false; }
            if (! this.isAuth) { this.loading = false; return; }
            this.load();
        },

        async load() {
            try {
                const r = await BileteOnlineAPI.customer.getRewardsOverview();
                const d = r && r.data ? r.data : {};
                this.balance  = d.balance ?? d.points_balance ?? (d.points && d.points.balance) ?? 0;
                this.lifetime = d.lifetime ?? d.total_earned ?? d.lifetime_points ?? this.balance;
            } catch (e) {}

            try {
                const h = await BileteOnlineAPI.customer.getPointsHistory({ per_page: 30 });
                const list = (h && h.data && (h.data.history || h.data.items || h.data.transactions)) || (h && h.data) || [];
                this.history = Array.isArray(list) ? list : [];
            } catch (e) {}

            this.loading = false;
        },

        get currentTier() {
            const reached = this.tiers.filter(t => this.lifetime >= t.threshold);
            return reached[reached.length - 1] || this.tiers[0];
        },
        get nextTier() {
            const next = this.tiers.find(t => this.lifetime < t.threshold);
            return next || null;
        },
        get tierProgressPct() {
            if (! this.nextTier) return 100;
            const prev = this.currentTier.threshold;
            const next = this.nextTier.threshold;
            const span = next - prev;
            if (span <= 0) return 100;
            return Math.min(100, Math.max(0, ((this.lifetime - prev) / span) * 100));
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
