<?php
/**
 * bilete.online — /cont (client dashboard, v2 full template)
 *
 * Implements template-client-dashboard-bilete-online-v2.html end-to-end:
 *   - Hero w/ next-ticket card (date, time, beneficiaries, "Deschide bilet")
 *   - 4 stat cards (BILETE VIITOARE / PUNCTE BONUS / COMENZI / PROFIL %)
 *   - URMEAZĂ list (ticket cards w/ image + status + Deschide + Calendar)
 *   - Sidebar: PERSONALIZARE checklist (3 profile tasks) + AFILIERE (referral link)
 *   - PENTRU TINE recommendations grid + ISTORIC orders table
 *   - 3 utility cards (SUPORT count / RECENZII pending / CARD CADOU balance)
 */

require_once __DIR__ . '/../includes/config.php';

$pageTitleRaw    = 'Dashboard client — ' . SITE_NAME;
$pageDescription = 'Dashboard client bilete.online: bilete, comenzi, puncte bonus, carduri cadou, recomandări personalizate, recenzii, suport și setări cont.';
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

            <!-- ===== HERO ===== -->
            <section class="relative overflow-hidden rounded-[2rem] border-2 border-ink bg-ink text-paper p-6 sm:p-8 lg:p-10 shadow-deep">
                <div class="absolute inset-0 opacity-10" style="background-image:radial-gradient(#fff 1px,transparent 1.4px);background-size:15px 15px"></div>
                <div class="relative grid xl:grid-cols-[1fr_380px] gap-8 items-center">
                    <div>
                        <p class="stamp inline-flex px-3 py-1 text-xs font-mono tracking-[.18em] text-ochre">DASHBOARD CLIENT</p>
                        <h1 class="mt-5 font-display text-5xl sm:text-6xl lg:text-7xl font-bold leading-[.85]">
                            Salut, <span x-text="firstName || 'prieten'">prieten</span>.
                            <template x-if="stats.upcoming_tickets_count > 0">
                                <span>Ai <span x-text="stats.upcoming_tickets_count"></span> bilete viitoare.</span>
                            </template>
                            <template x-if="!stats.upcoming_tickets_count">
                                <span>Bine ai revenit.</span>
                            </template>
                        </h1>
                        <p class="mt-5 max-w-2xl text-paper/65 text-lg leading-relaxed">Aici vezi ce urmează, ce ai cumpărat, câte puncte ai, ce recomandări ți se potrivesc și ce mai ai de rezolvat înainte de următoarea activitate.</p>
                        <div class="mt-7 flex flex-wrap gap-3">
                            <a href="/cont/biletele-mele" class="rounded-full bg-vermilion text-paper px-6 py-4 font-bold hover:bg-vermilion-d transition">Vezi biletele</a>
                            <a href="/cont/recomandari" class="rounded-full border-2 border-paper/50 px-6 py-4 font-bold hover:bg-paper hover:text-ink transition">Recomandări</a>
                        </div>
                    </div>

                    <!-- NEXT TICKET card -->
                    <template x-if="nextTicket">
                        <div class="ticket relative rounded-[2rem] border-2 border-paper/30 bg-paper text-ink p-6 overflow-hidden rotate-[-2deg]" style="--perf:100%">
                            <p class="font-mono text-xs tracking-[.18em] text-ink-soft">NEXT TICKET</p>
                            <h2 class="mt-3 font-display text-4xl font-bold leading-none" x-text="nextTicket.title"></h2>
                            <p class="mt-2 text-ink-soft" x-text="nextTicket.location"></p>
                            <div class="mt-5 grid grid-cols-[1fr_auto] gap-4 items-end">
                                <div>
                                    <p class="font-mono text-xs tracking-[.18em] text-ink-soft uppercase" x-text="nextTicket.weekday"></p>
                                    <p class="font-display text-5xl font-bold" x-text="nextTicket.time"></p>
                                    <p class="text-sm text-ink-soft" x-text="nextTicket.dateLong + (nextTicket.beneficiaries > 1 ? ' · ' + nextTicket.beneficiaries + ' beneficiari' : '')"></p>
                                </div>
                                <div class="w-24 h-24 rounded-2xl bg-ink text-paper grid place-items-center rotate-3"><span class="font-mono text-xs text-center">QR<br>READY</span></div>
                            </div>
                            <a :href="nextTicket.url" class="mt-5 inline-flex rounded-full bg-ink text-paper px-5 py-3 font-bold hover:bg-vermilion transition">Deschide bilet</a>
                        </div>
                    </template>
                    <template x-if="!nextTicket && !loading">
                        <div class="ticket relative rounded-[2rem] border-2 border-paper/30 bg-paper text-ink p-6 overflow-hidden rotate-[-2deg]">
                            <p class="font-mono text-xs tracking-[.18em] text-ink-soft">PUNCTE BONUS</p>
                            <p class="mt-3 font-display text-6xl font-bold" x-text="formatNumber(stats.points_balance ?? 0)">0</p>
                            <p class="mt-2 text-sm text-ink-soft">≈ <span x-text="formatLei(pointsToLei(stats.points_balance ?? 0))">0 lei</span> reducere</p>
                            <a href="/cont/punctele-mele" class="mt-4 inline-flex rounded-full bg-ink text-paper px-4 py-2 text-sm font-bold hover:bg-vermilion transition">Folosește punctele</a>
                        </div>
                    </template>
                </div>
            </section>

            <!-- ===== 4 STATS ===== -->
            <section class="mt-6 grid sm:grid-cols-2 xl:grid-cols-4 gap-4">
                <article class="rounded-[2rem] border-2 border-ink bg-paper p-5 shadow-ticket">
                    <p class="font-mono text-xs tracking-[.18em] text-ink-soft">BILETE VIITOARE</p>
                    <p class="mt-3 font-display text-6xl font-bold" x-text="stats.upcoming_tickets_count ?? 0">0</p>
                    <p class="mt-1 text-ink-soft" x-text="(stats.upcoming_activities_count ?? 0) + ' activități confirmate'">0 activități confirmate</p>
                </article>
                <article class="rounded-[2rem] border-2 border-ink bg-mint p-5 shadow-ticket">
                    <p class="font-mono text-xs tracking-[.18em] text-forest">PUNCTE BONUS</p>
                    <p class="mt-3 font-display text-6xl font-bold" x-text="formatNumber(stats.points_balance ?? 0)">0</p>
                    <p class="mt-1 text-ink-soft">≈ <span x-text="formatLei(pointsToLei(stats.points_balance ?? 0))">0 lei</span> reducere</p>
                </article>
                <article class="rounded-[2rem] border-2 border-ink bg-paper p-5 shadow-ticket">
                    <p class="font-mono text-xs tracking-[.18em] text-ink-soft">COMENZI</p>
                    <p class="mt-3 font-display text-6xl font-bold" x-text="stats.orders_count ?? 0">0</p>
                    <p class="mt-1 text-ink-soft" x-text="stats.last_order_relative ? ('ultima ' + stats.last_order_relative) : 'fără comenzi încă'">—</p>
                </article>
                <article class="rounded-[2rem] border-2 border-ink bg-rose p-5 shadow-ticket">
                    <p class="font-mono text-xs tracking-[.18em] text-vermilion">PROFIL</p>
                    <p class="mt-3 font-display text-6xl font-bold"><span x-text="profileCompletion ?? 0">0</span>%</p>
                    <p class="mt-1 text-ink-soft">completează preferințele</p>
                </article>
            </section>

            <!-- ===== URMEAZĂ + PERSONALIZARE + AFILIERE ===== -->
            <section class="mt-6 grid xl:grid-cols-[1.15fr_.85fr] gap-6">

                <!-- Upcoming tickets list -->
                <div class="rounded-[2rem] border-2 border-ink bg-paper p-5 sm:p-6 shadow-ticket">
                    <div class="flex flex-col sm:flex-row sm:items-end sm:justify-between gap-4">
                        <div>
                            <p class="font-mono text-xs tracking-[.18em] text-ink-soft">URMEAZĂ</p>
                            <h2 class="mt-2 font-display text-4xl sm:text-5xl font-bold leading-none">Bilete viitoare</h2>
                        </div>
                        <a href="/cont/biletele-mele" class="rounded-full bg-ink text-paper px-5 py-3 font-bold hover:bg-vermilion transition">Toate biletele</a>
                    </div>
                    <div x-show="loading" class="mt-6 space-y-3">
                        <div class="h-24 rounded-3xl bg-paper-2/60 animate-pulse"></div>
                        <div class="h-24 rounded-3xl bg-paper-2/60 animate-pulse"></div>
                    </div>
                    <div x-show="!loading && upcoming.length === 0" class="mt-6 rounded-3xl border-2 border-dashed border-ink/20 bg-paper-2/60 p-8 text-center">
                        <p class="text-4xl">🎟️</p>
                        <p class="mt-3 font-display text-2xl font-bold">Nu ai bilete viitoare</p>
                        <p class="mt-1 text-ink-soft text-sm">Descoperă activități și rezervă online.</p>
                        <a href="/categorii" class="mt-4 inline-flex rounded-full bg-vermilion text-paper px-5 py-3 font-bold">Descoperă activități</a>
                    </div>
                    <div x-show="!loading && upcoming.length > 0" class="mt-6 space-y-3">
                        <template x-for="ticket in upcoming" :key="ticket.id">
                            <article class="rounded-3xl border-2 border-ink/10 bg-paper-2/70 p-4 hover:border-ink transition">
                                <div class="flex flex-col md:flex-row md:items-center gap-4">
                                    <img :src="ticket.image || fallbackImage" :alt="ticket.title" class="w-full md:w-32 h-28 rounded-2xl object-cover border border-ink/10" onerror="this.src='data:image/svg+xml;utf8,&lt;svg xmlns=%22http://www.w3.org/2000/svg%22 viewBox=%220 0 100 100%22&gt;&lt;rect fill=%22%231B1714%22 width=%22100%22 height=%22100%22/&gt;&lt;text x=%2250%22 y=%2255%22 text-anchor=%22middle%22 fill=%22%23E84527%22 font-size=%2236%22&gt;🎟️&lt;/text&gt;&lt;/svg&gt;'">
                                    <div class="min-w-0 flex-1">
                                        <div class="flex flex-wrap gap-2">
                                            <span class="rounded-full bg-mint text-forest px-3 py-1 text-xs font-bold" x-text="ticket.status"></span>
                                            <span class="rounded-full bg-paper border border-ink/10 px-3 py-1 text-xs font-bold" x-text="ticket.city"></span>
                                        </div>
                                        <h3 class="mt-2 font-display text-3xl font-bold leading-none" x-text="ticket.title"></h3>
                                        <p class="mt-1 text-ink-soft" x-text="ticket.meta"></p>
                                    </div>
                                    <div class="flex md:flex-col gap-2 md:items-end shrink-0">
                                        <a :href="ticket.url" class="rounded-full bg-vermilion text-paper px-4 py-2 text-sm font-bold hover:bg-vermilion-d transition">Deschide</a>
                                        <a :href="ticket.calendarUrl" class="rounded-full border border-ink/20 px-4 py-2 text-sm font-bold hover:bg-ink hover:text-paper transition">Calendar</a>
                                    </div>
                                </div>
                            </article>
                        </template>
                    </div>
                </div>

                <aside class="space-y-6">

                    <!-- PERSONALIZARE checklist -->
                    <div class="rounded-[2rem] border-2 border-ink bg-paper p-5 sm:p-6 shadow-ticket">
                        <p class="font-mono text-xs tracking-[.18em] text-ink-soft">PERSONALIZARE</p>
                        <h2 class="mt-2 font-display text-4xl font-bold leading-none">Recomandări mai bune în 3 pași</h2>
                        <div class="mt-5 space-y-3">
                            <template x-for="task in profileTasks" :key="task.label">
                                <a :href="task.url" class="flex items-center gap-3 rounded-2xl bg-paper-2 border border-ink/10 p-4 hover:border-ink transition">
                                    <span class="grid place-items-center w-10 h-10 rounded-full shrink-0" :class="task.done ? 'bg-mint text-forest' : 'bg-rose text-vermilion'" x-text="task.done ? '✓' : '!'"></span>
                                    <span class="flex-1 min-w-0">
                                        <span class="block font-bold" x-text="task.label"></span>
                                        <span class="block text-sm text-ink-soft" x-text="task.helper"></span>
                                    </span>
                                </a>
                            </template>
                        </div>
                        <a href="/cont/setari#profil-preferinte" class="mt-5 inline-flex rounded-full bg-ink text-paper px-5 py-3 font-bold hover:bg-vermilion transition">Completează profilul</a>
                    </div>

                    <!-- AFILIERE referral -->
                    <div class="rounded-[2rem] border-2 border-forest bg-mint p-5 sm:p-6">
                        <p class="font-mono text-xs tracking-[.18em] text-forest">AFILIERE</p>
                        <h2 class="mt-2 font-display text-4xl font-bold leading-none">Invită prieteni. Primești puncte.</h2>
                        <p class="mt-3 text-ink-soft">Distribuie linkul tău și primești puncte bonus când prietenii cumpără prima activitate eligibilă.</p>
                        <div class="mt-4 rounded-2xl bg-paper border border-forest/20 p-3 font-mono text-sm break-all" x-text="referralUrl">bilete.online/r/—</div>
                        <div class="mt-4 flex gap-2">
                            <button @click="copyReferralLink()" class="rounded-full bg-forest text-paper px-5 py-3 font-bold hover:bg-ink transition" x-text="referralCopied ? 'Copiat ✓' : 'Copiază'">Copiază</button>
                            <a href="/cont/punctele-mele#afiliere" class="rounded-full border border-forest/30 px-5 py-3 font-bold text-forest hover:bg-forest hover:text-paper transition">Detalii</a>
                        </div>
                    </div>
                </aside>
            </section>

            <!-- ===== PENTRU TINE + ISTORIC ===== -->
            <section class="mt-6 grid xl:grid-cols-[.95fr_1.05fr] gap-6">

                <!-- Recommendations -->
                <div class="rounded-[2rem] border-2 border-ink bg-paper p-5 sm:p-6 shadow-ticket">
                    <div class="flex items-end justify-between gap-4">
                        <div>
                            <p class="font-mono text-xs tracking-[.18em] text-ink-soft">PENTRU TINE</p>
                            <h2 class="mt-2 font-display text-4xl sm:text-5xl font-bold leading-none">Recomandări</h2>
                        </div>
                        <a href="/cont/recomandari" class="font-bold text-vermilion underline-wobble">Vezi tot</a>
                    </div>
                    <div x-show="loadingRecommendations" class="mt-6 grid sm:grid-cols-2 gap-4">
                        <div class="h-48 rounded-3xl bg-paper-2/60 animate-pulse"></div>
                        <div class="h-48 rounded-3xl bg-paper-2/60 animate-pulse"></div>
                    </div>
                    <div x-show="!loadingRecommendations && recommendations.length === 0" class="mt-6 rounded-3xl border-2 border-dashed border-ink/20 p-6 text-center text-ink-soft">
                        Adaugă preferințe în
                        <a href="/cont/setari#profil-preferinte" class="font-bold text-vermilion underline-wobble">Setări</a>
                        ca să primești recomandări.
                    </div>
                    <div x-show="!loadingRecommendations && recommendations.length > 0" class="mt-6 grid sm:grid-cols-2 gap-4">
                        <template x-for="item in recommendations" :key="item.url || item.slug">
                            <article class="rounded-3xl border-2 border-ink/10 bg-paper-2 overflow-hidden hover:border-ink transition">
                                <img :src="item.image || fallbackImage" :alt="item.title" class="h-36 w-full object-cover">
                                <div class="p-4">
                                    <span class="rounded-full bg-paper border border-ink/10 px-3 py-1 text-xs font-bold" x-text="item.reason || 'recomandare'"></span>
                                    <h3 class="mt-3 font-display text-2xl font-bold leading-none" x-text="item.title"></h3>
                                    <p class="mt-2 text-sm text-ink-soft" x-text="item.meta"></p>
                                    <a :href="item.url" class="mt-4 inline-flex rounded-full bg-vermilion text-paper px-4 py-2 text-sm font-bold hover:bg-vermilion-d transition">Vezi activitatea</a>
                                </div>
                            </article>
                        </template>
                    </div>
                </div>

                <!-- Recent orders -->
                <div class="rounded-[2rem] border-2 border-ink bg-paper p-5 sm:p-6 shadow-ticket">
                    <div class="flex items-end justify-between gap-4">
                        <div>
                            <p class="font-mono text-xs tracking-[.18em] text-ink-soft">ISTORIC</p>
                            <h2 class="mt-2 font-display text-4xl sm:text-5xl font-bold leading-none">Comenzi recente</h2>
                        </div>
                        <a href="/cont/comenzile-mele" class="font-bold text-vermilion underline-wobble">Toate comenzile</a>
                    </div>
                    <div x-show="loadingOrders" class="mt-6 space-y-2">
                        <div class="h-12 rounded-2xl bg-paper-2/60 animate-pulse"></div>
                        <div class="h-12 rounded-2xl bg-paper-2/60 animate-pulse"></div>
                    </div>
                    <div x-show="!loadingOrders && recentOrders.length === 0" class="mt-6 text-center text-ink-soft py-6">Nu ai comenzi încă.</div>
                    <div x-show="!loadingOrders && recentOrders.length > 0" class="mt-6 overflow-hidden rounded-3xl border border-ink/10">
                        <table class="w-full text-left">
                            <thead class="bg-paper-2 text-xs font-mono tracking-[.16em] text-ink-soft">
                                <tr>
                                    <th class="px-4 py-3">Comandă</th>
                                    <th class="px-4 py-3 hidden sm:table-cell">Data</th>
                                    <th class="px-4 py-3">Total</th>
                                    <th class="px-4 py-3">Status</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-ink/10">
                                <template x-for="order in recentOrders" :key="order.id">
                                    <tr class="hover:bg-paper-2/60">
                                        <td class="px-4 py-4">
                                            <a :href="order.url" class="font-bold text-vermilion underline-wobble" x-text="order.id"></a>
                                            <p class="sm:hidden text-xs text-ink-soft" x-text="order.date"></p>
                                        </td>
                                        <td class="px-4 py-4 hidden sm:table-cell text-ink-soft" x-text="order.date"></td>
                                        <td class="px-4 py-4 font-bold" x-text="order.total"></td>
                                        <td class="px-4 py-4">
                                            <span class="rounded-full px-3 py-1 text-xs font-bold" :class="order.statusClass" x-text="order.status"></span>
                                        </td>
                                    </tr>
                                </template>
                            </tbody>
                        </table>
                    </div>
                </div>
            </section>

            <!-- ===== 3 UTILITY CARDS ===== -->
            <section class="mt-6 grid md:grid-cols-3 gap-6">
                <article class="rounded-[2rem] border-2 border-ink bg-paper p-6 shadow-ticket">
                    <p class="font-mono text-xs tracking-[.18em] text-ink-soft">SUPORT</p>
                    <h2 class="mt-2 font-display text-4xl font-bold leading-none">
                        <template x-if="utility.supportActive > 0"><span><span x-text="utility.supportActive"></span> tichete active</span></template>
                        <template x-if="!utility.supportActive"><span>Niciun tichet activ</span></template>
                    </h2>
                    <p class="mt-3 text-ink-soft" x-text="utility.supportActive > 0 ? 'Vezi statusul răspunsurilor de la echipa noastră.' : 'Deschide un tichet dacă ai nelămuriri.'"></p>
                    <a href="/cont/tichete-support" class="mt-5 inline-flex rounded-full bg-ink text-paper px-5 py-3 font-bold hover:bg-vermilion transition">Vezi tichete</a>
                </article>
                <article class="rounded-[2rem] border-2 border-ink bg-paper p-6 shadow-ticket">
                    <p class="font-mono text-xs tracking-[.18em] text-ink-soft">RECENZII</p>
                    <h2 class="mt-2 font-display text-4xl font-bold leading-none">
                        <template x-if="utility.reviewsPending > 0"><span><span x-text="utility.reviewsPending"></span> de scris</span></template>
                        <template x-if="!utility.reviewsPending"><span>Toate scrise</span></template>
                    </h2>
                    <p class="mt-3 text-ink-soft" x-text="utility.reviewsPending > 0 ? 'Scrie despre activitățile la care ai participat.' : 'Mulțumim că împărtășești experiențele tale.'"></p>
                    <a href="/cont/recenzii" class="mt-5 inline-flex rounded-full bg-ink text-paper px-5 py-3 font-bold hover:bg-vermilion transition">Scrie recenzie</a>
                </article>
                <article class="rounded-[2rem] border-2 border-ink p-6 shadow-ticket" :class="utility.giftBalance > 0 ? 'bg-vermilion text-paper' : 'bg-paper'">
                    <p class="font-mono text-xs tracking-[.18em]" :class="utility.giftBalance > 0 ? 'text-paper/60' : 'text-ink-soft'">CARD CADOU</p>
                    <h2 class="mt-2 font-display text-4xl font-bold leading-none">
                        <template x-if="utility.giftBalance > 0"><span>Ai <span x-text="formatLei(utility.giftBalance)"></span> disponibili</span></template>
                        <template x-if="!utility.giftBalance"><span>Verifică un card cadou</span></template>
                    </h2>
                    <p class="mt-3" :class="utility.giftBalance > 0 ? 'text-paper/70' : 'text-ink-soft'" x-text="utility.giftBalance > 0 ? 'Card activ — folosește-l la următoarea comandă.' : 'Introdu codul cardului pentru a vedea soldul.'"></p>
                    <a :href="utility.giftBalance > 0 ? '/cont/carduri-cadou' : '/voucher'" class="mt-5 inline-flex rounded-full px-5 py-3 font-bold transition" :class="utility.giftBalance > 0 ? 'bg-paper text-ink hover:bg-ink hover:text-paper' : 'bg-ink text-paper hover:bg-vermilion'" x-text="utility.giftBalance > 0 ? 'Vezi carduri' : 'Verifică sold'"></a>
                </article>
            </section>

            <!-- AUTH GUARD -->
            <div x-show="isAuth === false" x-cloak class="mt-8 rounded-[2rem] border-2 border-vermilion bg-rose p-8 text-center">
                <p class="font-display text-3xl font-bold text-vermilion">Trebuie să fii autentificat</p>
                <p class="mt-2 text-ink-soft">Intră în cont pentru a vedea dashboardul.</p>
                <a href="/autentificare?redirect=/cont" class="mt-5 inline-flex rounded-full bg-vermilion text-paper px-6 py-3 font-bold">Intră în cont</a>
            </div>
        </main>
    </div>
</div>

<script>
function clientDashboardPage() {
    return {
        loading: true,
        loadingRecommendations: true,
        loadingOrders: true,
        isAuth: null,
        firstName: '',
        fallbackImage: 'data:image/svg+xml;utf8,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100"><rect fill="%231B1714" width="100" height="100"/><text x="50" y="58" text-anchor="middle" fill="%23E84527" font-size="40">🎟️</text></svg>',

        stats: {
            upcoming_tickets_count: 0,
            upcoming_activities_count: 0,
            points_balance: 0,
            orders_count: 0,
            last_order_relative: '',
            total_spent: 0,
        },
        profileCompletion: 0,
        nextTicket: null,
        upcoming: [],
        recommendations: [],
        recentOrders: [],

        profileTasks: [],
        referralUrl: 'bilete.online/r/—',
        referralCode: '',
        referralCopied: false,

        utility: { supportActive: 0, reviewsPending: 0, giftBalance: 0 },

        // Loaded from /customer/rewards/config (so dashboard never duplicates
        // the conversion rate). Fallback 100 = backend default point_value 0.01.
        rewardsConfig: { pointsPerLei: 100, loaded: false },

        init() {
            try { if (window.BileteOnlineAuth && BileteOnlineAuth.getToken && BileteOnlineAuth.getToken()) this.isAuth = true; } catch (e) {}
            if (this.isAuth === false) { this.loading = false; this.loadingRecommendations = false; this.loadingOrders = false; return; }

            // Hydrate from cached session for instant first paint — both the
            // hero greeting and the 3-step profile checklist render before the
            // API round-trip finishes.
            try {
                const u = window.BileteOnlineAuth && BileteOnlineAuth.getUser ? BileteOnlineAuth.getUser() : null;
                if (u) {
                    this.firstName = u.first_name || (u.name ? u.name.split(' ')[0] : '');
                    // Pre-render the personalisation checklist from cached
                    // profile_completion so it never shows blank.
                    if (u.profile_completion) this.applyProfileCompletion(u.profile_completion);
                    else this.applyProfileCompletion(null);
                    if (u.points != null) this.stats.points_balance = u.points;
                }
            } catch (e) {}
            // Always render the 3 default tasks even when nothing cached
            if (this.profileTasks.length === 0) this.applyProfileCompletion(null);

            // ONE request gets everything dashboard needs. Falls back to the
            // 8 individual endpoints if the bundle endpoint isn't deployed
            // yet (graceful rollout).
            this.loadBundle();
        },

        async loadBundle() {
            try {
                const r = await BileteOnlineAPI.get('/customer/dashboard-bundle');
                if (! r || ! r.success || ! r.data) throw new Error('bundle missing');
                this.isAuth = true;
                const d = r.data;

                // --- customer (profile + profile_completion + referral_code legacy)
                const u = d.customer || {};
                if (u.first_name) this.firstName = u.first_name;
                if (u.profile_completion) this.applyProfileCompletion(u.profile_completion);

                // --- rewards config (points_per_lei)
                if (d.rewards_config && d.rewards_config.points_per_lei) {
                    this.rewardsConfig.pointsPerLei = d.rewards_config.points_per_lei;
                }
                this.rewardsConfig.loaded = true;

                // --- rewards summary (balance for the hero ticket + 4 stats card)
                const rs = d.rewards_summary || {};
                this.stats.points_balance = rs.balance ?? u.points ?? 0;

                // --- referrals
                const ref = d.referrals || {};
                const code = ref.referral_code || ref.code || (ref.referralCode && ref.referralCode.code) || '';
                const fullLink = ref.referral_link || ref.share_url || '';
                if (code) {
                    this.referralCode = code;
                    this.referralUrl = fullLink
                        ? fullLink.replace(/^https?:\/\//, '')
                        : (location.hostname.replace(/^www\./, '') + '/r/' + code);
                }

                // --- stats (counts)
                const s = d.stats || {};
                this.stats.upcoming_tickets_count    = s.upcoming_tickets_count ?? s.tickets_count ?? 0;
                this.stats.upcoming_activities_count = s.upcoming_activities_count ?? s.upcoming_events_count ?? this.stats.upcoming_tickets_count;
                this.stats.orders_count              = s.orders_count ?? s.total_orders ?? 0;
                this.stats.total_spent               = s.total_spent ?? 0;
                if (s.last_order_at) this.stats.last_order_relative = this.relTime(s.last_order_at);

                // --- upcoming events
                const upcomingList = Array.isArray(d.upcoming) ? d.upcoming : (d.upcoming && d.upcoming.events ? d.upcoming.events : []);
                this.upcoming = upcomingList.slice(0, 4).map(ev => this.normalizeUpcoming(ev));
                if (this.upcoming.length > 0) {
                    const top = this.upcoming[0];
                    this.nextTicket = {
                        title:    top.title,
                        location: top.location || '',
                        weekday:  top.weekday || '',
                        time:     top.time || '',
                        dateLong: top.dateLong || '',
                        beneficiaries: top.tickets_count || 1,
                        url:      top.url,
                    };
                }
                this.loading = false;

                // --- recommendations (server-ranked; no client recompute)
                this.recommendations = (d.recommendations || []).slice(0, 4).map(it => ({
                    title:  it.title || '',
                    reason: (it.reasons && it.reasons[0]) || it.reason_primary || 'recomandare',
                    meta:   it.price_label || '',
                    image:  it.image,
                    url:    it.url || '#',
                }));
                this.loadingRecommendations = false;

                // --- recent orders (already formatted server-side)
                this.recentOrders = (d.recent_orders || []).map(o => ({
                    id:          o.id,
                    date:        o.date,
                    total:       o.total,
                    status:      o.status,
                    statusClass: o.statusClass,
                    url:         o.url,
                }));
                this.loadingOrders = false;

                // --- utility (support / reviews / gift cards)
                const ut = d.utility || {};
                this.utility = {
                    supportActive:  ut.supportActive  || 0,
                    reviewsPending: ut.reviewsPending || 0,
                    giftBalance:    ut.giftBalance    || 0,
                };
            } catch (e) {
                if (e && e.status === 401) { this.isAuth = false; return; }
                // Bundle endpoint missing/failed — fall back to the old fan-out so
                // the page still works during deploy rollouts.
                console.warn('dashboard-bundle unavailable, falling back to per-endpoint fetches', e);
                this.loadRewardsConfig();
                this.loadMe();
                this.loadReferral();
                this.loadStats();
                this.loadUpcoming();
                this.loadRecommendations();
                this.loadRecentOrders();
                this.loadUtilityCounts();
            }
        },

        applyProfileCompletion(pc) {
            const fields = (pc && pc.fields) || {};
            this.profileCompletion = (pc && pc.percentage) || 0;
            this.profileTasks = [
                { label: 'Adaugă orașul preferat',     helper: 'recomandări locale mai bune',                done: !!fields.city,                          url: '/cont/setari#profil-preferinte' },
                { label: 'Alege tipuri de activități', helper: 'copii, muzee, natură, escape rooms',          done: !!fields.interests,                     url: '/cont/setari#profil-preferinte' },
                { label: 'Adaugă vârstele copiilor',   helper: 'filtrare activități potrivite',               done: !!fields.family || !!fields.beneficiaries, url: '/cont/setari#familie' },
            ];
        },

        async loadReferral() {
            // /customer/me returns CustomerPoints.referral_code which is
            // distinct from marketplace_referral_codes — the latter is the
            // canonical share link. /customer/referrals auto-creates the row
            // for us and returns the full URL, stats and code.
            try {
                const r = await BileteOnlineAPI.get('/customer/referrals');
                const d = (r && r.data) || {};
                const code = d.referral_code || d.code || (d.referralCode || {}).code || '';
                const fullLink = d.referral_link || d.share_url || '';
                if (code) {
                    this.referralCode = code;
                    if (fullLink) {
                        this.referralUrl = fullLink.replace(/^https?:\/\//, '');
                    } else {
                        const host = location.hostname.replace(/^www\./, '');
                        this.referralUrl = host + '/r/' + code;
                    }
                }
            } catch (e) {}
        },

        async loadRewardsConfig() {
            try {
                const r = await BileteOnlineAPI.get('/customer/rewards/config');
                const d = (r && r.data) || {};
                if (d.points_per_lei) this.rewardsConfig.pointsPerLei = d.points_per_lei;
                this.rewardsConfig.loaded = true;
            } catch (e) {}
        },

        async loadMe() {
            try {
                const r = await BileteOnlineAPI.customer.getProfile();
                if (r && r.success) this.isAuth = true;
                const root = (r && r.data) || {};
                const u = root.customer || root;
                if (u.first_name) this.firstName = u.first_name;
                if (u.profile_completion) this.applyProfileCompletion(u.profile_completion);
                if (u.points != null) this.stats.points_balance = u.points;
                // u.referral_code is the CustomerPoints code (legacy); we still
                // override it with the marketplace_referral_codes value from
                // loadReferral() if that endpoint returns one.
                if (u.referral_code && ! this.referralCode) {
                    this.referralCode = u.referral_code;
                    const host = location.hostname.replace(/^www\./, '');
                    this.referralUrl = host + '/r/' + u.referral_code;
                }
            } catch (e) {
                if (e && e.status === 401) this.isAuth = false;
            }
        },

        async loadStats() {
            try {
                const r = await BileteOnlineAPI.customer.getDashboardStats();
                const d = (r && r.data) || {};
                const s = d.stats || d;
                this.stats.upcoming_tickets_count    = s.upcoming_tickets_count ?? s.tickets_count ?? s.upcoming_count ?? 0;
                this.stats.upcoming_activities_count = s.upcoming_activities_count ?? s.upcoming_events_count ?? this.stats.upcoming_tickets_count;
                this.stats.orders_count              = s.orders_count ?? s.total_orders ?? 0;
                this.stats.total_spent               = s.total_spent ?? 0;
                if (s.points_balance != null) this.stats.points_balance = s.points_balance;
                if (s.last_order_at) {
                    this.stats.last_order_relative = this.relTime(s.last_order_at);
                }
            } catch (e) {}
        },

        async loadUpcoming() {
            try {
                const r = await BileteOnlineAPI.customer.getUpcomingEvents(6);
                const events = (r && r.data && (r.data.events || r.data.items)) || (r && r.data) || [];
                this.upcoming = (Array.isArray(events) ? events : []).slice(0, 4).map(ev => this.normalizeUpcoming(ev));

                // Pick the first upcoming as the hero card
                if (this.upcoming.length > 0) {
                    const top = this.upcoming[0];
                    this.nextTicket = {
                        title:    top.title,
                        location: top.location || '',
                        weekday:  top.weekday || '',
                        time:     top.time || '',
                        dateLong: top.dateLong || '',
                        beneficiaries: top.tickets_count || 1,
                        url:      top.url,
                    };
                }
            } catch (e) {}
            this.loading = false;
        },

        normalizeUpcoming(ev) {
            const dt = ev.date || ev.start_date || ev.start_time || ev.event_date;
            const obj = new Date(dt);
            const valid = !isNaN(obj.getTime());
            const months = ['ianuarie','februarie','martie','aprilie','mai','iunie','iulie','august','septembrie','octombrie','noiembrie','decembrie'];
            const wdays  = ['DUMINICĂ','LUNI','MARȚI','MIERCURI','JOI','VINERI','SÂMBĂTĂ'];
            const weekday = valid ? wdays[obj.getDay()] : '';
            const dateLong = valid ? (obj.getDate() + ' ' + months[obj.getMonth()] + ' ' + obj.getFullYear()) : '';
            const time = valid ? (String(obj.getHours()).padStart(2,'0') + ':' + String(obj.getMinutes()).padStart(2,'0')) : '';
            const city = (ev.venue && (ev.venue.city || (ev.venue.address && ev.venue.address.city))) || ev.city || '';
            const location = (ev.venue && (ev.venue.name || ev.venue)) || ev.location || '';
            const orderId = ev.order_id || ev.order_number || (ev.order && (ev.order.id || ev.order.number));
            const baseUrl = orderId ? ('/cont/comenzi/' + orderId) : '/cont/biletele-mele';
            return {
                id:           ev.id || ev.event_id || ev.slug,
                title:        ev.title || ev.name,
                location:     location,
                city:         city || '—',
                status:       'confirmat',
                meta:         (dateLong + (time ? ' · ' + time : '') + (ev.tickets_count > 1 ? ' · ' + ev.tickets_count + ' beneficiari' : '')),
                image:        ev.cover_image_url || ev.image || ev.thumbnail,
                weekday:      weekday,
                time:         time,
                dateLong:     dateLong,
                tickets_count:ev.tickets_count || 1,
                url:          baseUrl,
                calendarUrl:  baseUrl + '?action=calendar',
            };
        },

        async loadRecommendations() {
            try {
                const r = await BileteOnlineAPI.get('/activities?sort=recent&per_page=12');
                const list = (r && r.data && (r.data.items || r.data.activities)) || (r && r.data) || [];
                this.recommendations = (Array.isArray(list) ? list : []).slice(0, 4).map(a => ({
                    title:  a.title || a.name || '',
                    reason: a.category ? ((a.category.name || a.category) + ' în ' + (a.city ? (a.city.name || a.city) : 'oraș')) : 'recomandare',
                    meta:   (a.price_from ? ('de la ' + a.price_from + ' lei') : 'activitate populară'),
                    image:  a.cover_image_url || a.image,
                    url:    a.slug ? ('/activitate/' + a.slug) : '#',
                }));
            } catch (e) {}
            this.loadingRecommendations = false;
        },

        async loadRecentOrders() {
            try {
                const r = await BileteOnlineAPI.customer.getOrders({ per_page: 4 });
                const items = (r && r.data && (r.data.orders || r.data.items)) || (r && r.data) || [];
                this.recentOrders = (Array.isArray(items) ? items : []).slice(0, 4).map(o => {
                    const status = (o.status || 'confirmată').toLowerCase();
                    const statusClass = (status === 'completed' || status === 'paid' || status === 'confirmat' || status === 'confirmată') ? 'bg-mint text-forest'
                                      : (status === 'refunded' || status === 'cancelled' || status === 'retur') ? 'bg-rose text-vermilion'
                                      : 'bg-paper-2 text-ink-soft';
                    const orderId = o.order_number || ('#' + o.id);
                    return {
                        id:          orderId.startsWith('#') ? orderId : ('#' + orderId),
                        date:        o.created_at ? new Date(o.created_at).toLocaleDateString('ro-RO', { day: 'numeric', month: 'short', year: 'numeric' }) : '',
                        total:       (o.total_amount != null ? o.total_amount : (o.total || 0)) + ' lei',
                        status:      status,
                        statusClass: statusClass,
                        url:         '/cont/comenzile-mele#' + (o.order_number || o.id),
                    };
                });
            } catch (e) {}
            this.loadingOrders = false;
        },

        async loadUtilityCounts() {
            // Support count
            try {
                const r = await BileteOnlineAPI.get('/customer/support-tickets', { status: 'open', per_page: 1 });
                this.utility.supportActive = (r && r.data && (r.data.total ?? r.data.meta?.total ?? (r.data.tickets || []).length)) || 0;
            } catch (e) {}
            // Reviews pending
            try {
                const r = await BileteOnlineAPI.get('/customer/reviews/events-to-review');
                const list = (r && r.data && (r.data.events || r.data.items)) || (r && r.data) || [];
                this.utility.reviewsPending = Array.isArray(list) ? list.length : 0;
            } catch (e) {}
            // Gift cards
            try {
                const r = await BileteOnlineAPI.get('/customer/gift-cards');
                const cards = (r && r.data && (r.data.gift_cards || r.data.items)) || (r && r.data) || [];
                this.utility.giftBalance = (Array.isArray(cards) ? cards : []).reduce((sum, c) => sum + (c.balance || c.remaining_balance || 0), 0);
            } catch (e) {}
        },

        copyReferralLink() {
            const url = location.protocol + '//' + this.referralUrl;
            try {
                navigator.clipboard.writeText(url);
                this.referralCopied = true;
                setTimeout(() => { this.referralCopied = false; }, 2000);
            } catch (e) {
                window.prompt('Copiază linkul tău:', url);
            }
        },

        // ===== utils =====
        pointsToLei(points)  { return Math.floor((points || 0) / (this.rewardsConfig.pointsPerLei || 100)); },
        formatNumber(n)      { return new Intl.NumberFormat('ro-RO').format(n || 0); },
        formatLei(n)         { return (n == null ? '0' : new Intl.NumberFormat('ro-RO', { maximumFractionDigits: 0 }).format(n)) + ' lei'; },
        relTime(iso) {
            try {
                const d = new Date(iso);
                const diff = (Date.now() - d.getTime()) / 1000;
                if (diff < 60)      return 'acum';
                if (diff < 3600)    return 'acum ' + Math.floor(diff/60) + ' min';
                if (diff < 86400)   return 'acum ' + Math.floor(diff/3600) + ' h';
                if (diff < 86400*7) return 'acum ' + Math.floor(diff/86400) + ' zile';
                return d.toLocaleDateString('ro-RO');
            } catch (e) { return ''; }
        },
    };
}
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>
