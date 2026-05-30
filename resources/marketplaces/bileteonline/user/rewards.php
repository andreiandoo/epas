<?php
/**
 * bilete.online — /cont/punctele-mele (Puncte bonus + Afiliere, v2 full template)
 *
 * Implements template-client-points-bilete-online-v2.html end-to-end:
 *   - Hero w/ balance ticket (shimmer) + estimated lei + progress to next tier
 *   - 4 stats (DISPONIBILE / CÂȘTIGATE / FOLOSITE / EXPIRĂ CURÂND)
 *   - "Cum funcționează" 3-step + regulă card
 *   - Nivel client (tier) progress + perks list
 *   - AFILIERE section (forest bg): referral stats + link card + copy + share
 *   - Tranzacții puncte table with type filter
 *   - Expiring soon sidebar + regulament card
 */

require_once __DIR__ . '/../includes/config.php';

$pageTitleRaw    = 'Punctele mele — ' . SITE_NAME;
$pageDescription = 'Punctele tale bonus pe bilete.online: sold disponibil, expirare, istoric, nivel client și link de afiliere.';
$canonicalUrl    = SITE_URL . '/cont/punctele-mele';
$noindex         = true;
$currentPage     = 'cont';
$cssBundle       = 'auth';

include __DIR__ . '/../includes/head.php';
include __DIR__ . '/../includes/header.php';
?>

<div class="max-w-[1500px] mx-auto px-4 sm:px-6 py-6 lg:py-8">
    <div class="grid lg:grid-cols-[280px_minmax(0,1fr)] gap-6 lg:gap-8 items-start">

        <?php $currentClientPage = 'points'; include __DIR__ . '/../includes/client-sidebar-v2.php'; ?>

        <main class="min-w-0" x-data="clientPointsPage()" x-init="init()">

            <!-- HERO -->
            <section class="relative overflow-hidden rounded-[2rem] border-2 border-ink bg-ink text-paper p-6 sm:p-8 shadow-deep">
                <div class="absolute inset-0 opacity-10" style="background-image:radial-gradient(#fff 1px,transparent 1.4px);background-size:15px 15px"></div>
                <div class="relative grid xl:grid-cols-[1fr_420px] gap-8 items-center">
                    <div>
                        <p class="stamp inline-flex px-3 py-1 text-xs font-mono tracking-[.18em] text-ochre">LOYALTY WALLET</p>
                        <h1 class="mt-5 font-display text-5xl sm:text-6xl lg:text-7xl font-bold leading-[.85]">Punctele mele</h1>
                        <p class="mt-5 max-w-3xl text-paper/65 text-lg leading-relaxed">Vezi soldul punctelor bonus, valoarea estimată, cum le poți folosi, ce puncte urmează să expire și câte ai câștigat din comenzi sau afiliere.</p>
                        <div class="mt-7 flex flex-wrap gap-3">
                            <a href="/cont/recomandari" class="rounded-full bg-vermilion text-paper px-6 py-4 font-bold hover:bg-vermilion-d transition">Folosește puncte</a>
                            <a href="#afiliere" class="rounded-full border-2 border-paper/50 px-6 py-4 font-bold hover:bg-paper hover:text-ink transition">Invită prieteni</a>
                        </div>
                    </div>

                    <div class="rounded-[2rem] border-2 border-paper/30 bg-paper text-ink p-6 rotate-[-2deg]">
                        <p class="font-mono text-xs tracking-[.18em] text-ink-soft">SOLD DISPONIBIL</p>
                        <p class="mt-3 font-display text-8xl font-bold leading-none" x-text="formatNumber(points.balance)">0</p>
                        <p class="mt-2 text-ink-soft">puncte bonus · aproximativ <strong class="text-ink" x-text="formatLei(pointsToLei(points.balance))">0 lei</strong></p>
                        <div class="mt-5 h-3 rounded-full bg-paper-2 overflow-hidden border border-ink/10">
                            <div class="h-full bg-vermilion rounded-full transition-all" :style="'width:' + (tier.progress) + '%'"></div>
                        </div>
                        <p class="mt-2 text-sm text-ink-soft">
                            <template x-if="tier.next"><span>Încă <strong x-text="formatNumber(tier.toNext)"></strong> puncte până la nivelul <strong x-text="tier.next.name"></strong>.</span></template>
                            <template x-if="!tier.next"><span>Ești la cel mai înalt nivel — felicitări!</span></template>
                        </p>
                    </div>
                </div>
            </section>

            <!-- 4 STATS -->
            <section class="mt-6 grid sm:grid-cols-2 xl:grid-cols-4 gap-4">
                <article class="rounded-[2rem] border-2 border-ink bg-paper p-5 shadow-ticket">
                    <p class="font-mono text-xs tracking-[.18em] text-ink-soft">PUNCTE DISPONIBILE</p>
                    <p class="mt-3 font-display text-6xl font-bold" x-text="formatNumber(points.balance)">0</p>
                    <p class="mt-1 text-ink-soft">≈ <span x-text="formatLei(pointsToLei(points.balance))">0 lei</span></p>
                </article>
                <article class="rounded-[2rem] border-2 border-ink bg-mint p-5 shadow-ticket">
                    <p class="font-mono text-xs tracking-[.18em] text-forest">CÂȘTIGATE TOTAL</p>
                    <p class="mt-3 font-display text-6xl font-bold" x-text="formatNumber(points.lifetime_earned)">0</p>
                    <p class="mt-1 text-ink-soft">din comenzi și afiliere</p>
                </article>
                <article class="rounded-[2rem] border-2 border-ink bg-paper p-5 shadow-ticket">
                    <p class="font-mono text-xs tracking-[.18em] text-ink-soft">FOLOSITE</p>
                    <p class="mt-3 font-display text-6xl font-bold" x-text="formatNumber(points.spent)">0</p>
                    <p class="mt-1 text-ink-soft">≈ <span x-text="formatLei(pointsToLei(points.spent))">0 lei</span> reduceri</p>
                </article>
                <article class="rounded-[2rem] border-2 border-ink bg-rose p-5 shadow-ticket">
                    <p class="font-mono text-xs tracking-[.18em] text-vermilion">EXPIRĂ CURÂND</p>
                    <p class="mt-3 font-display text-6xl font-bold" x-text="formatNumber(points.expiring_soon)">0</p>
                    <p class="mt-1 text-ink-soft" x-text="points.expiring_days ? ('în ' + points.expiring_days + ' zile') : 'fără expirare imediată'">—</p>
                </article>
            </section>

            <!-- CUM FUNCȚIONEAZĂ + NIVEL CLIENT -->
            <section class="mt-6 grid xl:grid-cols-[1fr_.9fr] gap-6">
                <div class="rounded-[2rem] border-2 border-ink bg-paper p-6 shadow-ticket">
                    <div class="flex flex-col sm:flex-row sm:items-end sm:justify-between gap-4">
                        <div>
                            <p class="font-mono text-xs tracking-[.18em] text-ink-soft">CUM FUNCȚIONEAZĂ</p>
                            <h2 class="mt-2 font-display text-4xl sm:text-5xl font-bold leading-none">Folosești punctele direct în checkout.</h2>
                        </div>
                        <a href="/categorii" class="rounded-full bg-vermilion text-paper px-5 py-3 font-bold hover:bg-vermilion-d transition">Caută activități</a>
                    </div>

                    <div class="mt-6 grid md:grid-cols-3 gap-4">
                        <article class="rounded-3xl bg-paper-2 border border-ink/10 p-5">
                            <p class="font-display text-5xl font-bold text-vermilion">1</p>
                            <h3 class="mt-2 font-display text-3xl font-bold">Cumperi</h3>
                            <p class="mt-2 text-ink-soft">La comenzile eligibile primești puncte bonus după confirmare.</p>
                        </article>
                        <article class="rounded-3xl bg-paper-2 border border-ink/10 p-5">
                            <p class="font-display text-5xl font-bold text-vermilion">2</p>
                            <h3 class="mt-2 font-display text-3xl font-bold">Strângi</h3>
                            <p class="mt-2 text-ink-soft">Punctele se adună în cont și apar în istoricul tranzacțiilor.</p>
                        </article>
                        <article class="rounded-3xl bg-paper-2 border border-ink/10 p-5">
                            <p class="font-display text-5xl font-bold text-vermilion">3</p>
                            <h3 class="mt-2 font-display text-3xl font-bold">Reduci</h3>
                            <p class="mt-2 text-ink-soft">La checkout alegi câte puncte vrei să aplici în comanda eligibilă.</p>
                        </article>
                    </div>

                    <div class="mt-6 rounded-3xl bg-mint border border-forest/20 p-5">
                        <p class="font-bold text-forest">Conversie actuală</p>
                        <p class="mt-1 text-ink-soft"><strong x-text="config.pointsPerLei">20</strong> puncte = 1 leu reducere. Reducere maximă pe comandă: <strong x-text="formatLei(config.maxRedeemPerOrder)">10 lei</strong>.</p>
                    </div>
                </div>

                <div class="rounded-[2rem] border-2 border-ink bg-paper p-6 shadow-ticket">
                    <p class="font-mono text-xs tracking-[.18em] text-ink-soft">NIVEL CLIENT</p>
                    <h2 class="mt-2 font-display text-4xl sm:text-5xl font-bold leading-none" x-text="tier.current.name">Beginning</h2>
                    <p class="mt-3 text-ink-soft" x-text="tier.current.description">Faci primii pași.</p>

                    <div class="mt-6">
                        <div class="flex justify-between text-sm font-bold">
                            <span x-text="tier.current.name">Beginning</span>
                            <span x-text="tier.next ? tier.next.name : '—'">Active</span>
                        </div>
                        <div class="mt-2 h-4 rounded-full bg-paper-2 border border-ink/10 overflow-hidden">
                            <div class="h-full bg-forest rounded-full" :style="'width:' + tier.progress + '%'"></div>
                        </div>
                        <p class="mt-2 text-sm text-ink-soft">
                            <template x-if="tier.next"><span>Mai ai nevoie de <strong x-text="formatNumber(tier.toNext)"></strong> puncte pentru următorul nivel.</span></template>
                            <template x-if="!tier.next"><span>Cel mai înalt nivel atins.</span></template>
                        </p>
                    </div>

                    <div class="mt-6 space-y-3">
                        <template x-for="perk in tier.current.perks" :key="perk.label">
                            <div class="rounded-2xl bg-paper-2 border border-ink/10 p-4 flex items-center justify-between gap-3">
                                <span class="font-bold" x-text="perk.label"></span>
                                <span class="font-bold" :class="perk.active ? 'text-forest' : 'text-ochre'" x-text="perk.active ? 'activ' : 'soon'"></span>
                            </div>
                        </template>
                    </div>
                </div>
            </section>

            <!-- AFILIERE -->
            <section id="afiliere" class="mt-6 rounded-[2rem] border-2 border-ink bg-forest text-paper p-6 sm:p-8 shadow-deep">
                <div class="grid xl:grid-cols-[1fr_420px] gap-8 items-center">
                    <div>
                        <p class="stamp inline-flex px-3 py-1 text-xs font-mono tracking-[.18em] text-ochre">AFILIERE CLIENT</p>
                        <h2 class="mt-5 font-display text-5xl sm:text-6xl font-bold leading-[.9]">Invită prieteni. Câștigă puncte când cumpără.</h2>
                        <p class="mt-5 max-w-3xl text-paper/65 text-lg leading-relaxed">Distribuie linkul tău de afiliat. Când un prieten creează cont și cumpără prima activitate eligibilă, primești puncte bonus. Prietenul poate primi și el un beneficiu, dacă există campanie activă.</p>

                        <div class="mt-6 grid sm:grid-cols-3 gap-3">
                            <div class="rounded-2xl bg-paper/10 border border-paper/10 p-4">
                                <p class="font-display text-5xl font-bold" x-text="referral.clicks ?? 0">0</p>
                                <p class="text-paper/55 text-sm">clickuri</p>
                            </div>
                            <div class="rounded-2xl bg-paper/10 border border-paper/10 p-4">
                                <p class="font-display text-5xl font-bold" x-text="referral.accounts ?? 0">0</p>
                                <p class="text-paper/55 text-sm">conturi create</p>
                            </div>
                            <div class="rounded-2xl bg-paper/10 border border-paper/10 p-4">
                                <p class="font-display text-5xl font-bold" x-text="referral.qualified ?? 0">0</p>
                                <p class="text-paper/55 text-sm">comenzi eligibile</p>
                            </div>
                        </div>
                    </div>

                    <div class="rounded-[2rem] border-2 border-paper/20 bg-paper text-ink p-6 rotate-[-2deg]">
                        <p class="font-mono text-xs tracking-[.18em] text-ink-soft">LINKUL TĂU</p>
                        <h3 class="mt-2 font-display text-4xl font-bold leading-none break-all" x-text="referral.displayLink">bilete.online/r/—</h3>
                        <div class="mt-5 rounded-2xl bg-paper-2 border border-ink/10 p-4 font-mono text-sm break-all" x-text="referral.fullUrl"></div>
                        <div class="mt-5 flex flex-wrap gap-2">
                            <button @click="copyReferral()" class="rounded-full bg-vermilion text-paper px-5 py-3 font-bold hover:bg-vermilion-d transition" x-text="referral.copied ? 'Copiat ✓' : 'Copiază link'">Copiază link</button>
                            <a :href="whatsappShareUrl()" target="_blank" rel="noopener" class="rounded-full border-2 border-ink px-5 py-3 font-bold hover:bg-ink hover:text-paper transition">Share WhatsApp</a>
                            <button @click="regenerateCode()" :disabled="referral.regenerating" class="rounded-full border-2 border-ink/20 px-4 py-3 text-sm font-bold disabled:opacity-50">
                                <span x-show="!referral.regenerating">Cod nou</span>
                                <span x-show="referral.regenerating" x-cloak>Se generează…</span>
                            </button>
                        </div>
                        <p class="mt-4 text-sm text-ink-soft">Cod afiliat: <strong class="font-mono text-ink" x-text="referral.code || '—'"></strong></p>
                    </div>
                </div>
            </section>

            <!-- ISTORIC TRANZACȚII + SIDEBAR -->
            <section class="mt-6 grid xl:grid-cols-[1fr_360px] gap-6 items-start">

                <div class="rounded-[2rem] border-2 border-ink bg-paper p-5 sm:p-6 shadow-ticket">
                    <div class="flex flex-col sm:flex-row sm:items-end sm:justify-between gap-4">
                        <div>
                            <p class="font-mono text-xs tracking-[.18em] text-ink-soft">ISTORIC</p>
                            <h2 class="mt-2 font-display text-4xl sm:text-5xl font-bold leading-none">Tranzacții puncte</h2>
                        </div>
                        <select class="field !w-auto" x-model="transactionType">
                            <option value="all">Toate</option>
                            <option value="earned">Câștigate</option>
                            <option value="spent">Folosite</option>
                            <option value="expired">Expirate</option>
                            <option value="affiliate">Afiliere</option>
                        </select>
                    </div>

                    <div x-show="loadingTransactions" class="mt-6 space-y-2">
                        <div class="h-12 rounded-2xl bg-paper-2/60 animate-pulse"></div>
                        <div class="h-12 rounded-2xl bg-paper-2/60 animate-pulse"></div>
                        <div class="h-12 rounded-2xl bg-paper-2/60 animate-pulse"></div>
                    </div>

                    <div x-show="!loadingTransactions && filteredTransactions().length === 0" class="mt-6 text-center py-8 text-ink-soft">
                        Nu există tranzacții pentru filtrul ales.
                    </div>

                    <div x-show="!loadingTransactions && filteredTransactions().length > 0" class="mt-6 overflow-hidden rounded-3xl border border-ink/10">
                        <table class="w-full text-left">
                            <thead class="bg-paper-2 text-xs font-mono tracking-[.16em] text-ink-soft">
                                <tr>
                                    <th class="px-4 py-3">Data</th>
                                    <th class="px-4 py-3">Descriere</th>
                                    <th class="px-4 py-3 hidden md:table-cell">Tip</th>
                                    <th class="px-4 py-3 text-right">Puncte</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-ink/10">
                                <template x-for="tx in filteredTransactions()" :key="tx.id">
                                    <tr class="hover:bg-paper-2/60">
                                        <td class="px-4 py-4 text-sm text-ink-soft whitespace-nowrap" x-text="tx.date"></td>
                                        <td class="px-4 py-4">
                                            <p class="font-bold" x-text="tx.title"></p>
                                            <p class="text-xs text-ink-soft" x-text="tx.meta"></p>
                                        </td>
                                        <td class="px-4 py-4 hidden md:table-cell">
                                            <span class="rounded-full px-3 py-1 text-xs font-bold" :class="tx.badgeClass" x-text="tx.typeLabel"></span>
                                        </td>
                                        <td class="px-4 py-4 text-right font-display text-2xl font-bold" :class="tx.amount > 0 ? 'text-forest' : 'text-vermilion'" x-text="(tx.amount > 0 ? '+' : '') + formatNumber(tx.amount)"></td>
                                    </tr>
                                </template>
                            </tbody>
                        </table>
                    </div>
                </div>

                <aside class="space-y-6">
                    <div class="rounded-[2rem] border-2 border-ink p-6 shadow-ticket" :class="points.expiring_soon > 0 ? 'bg-rose border-vermilion/30' : 'bg-paper'">
                        <p class="font-mono text-xs tracking-[.18em]" :class="points.expiring_soon > 0 ? 'text-vermilion' : 'text-ink-soft'">EXPIRARE</p>
                        <h2 class="mt-2 font-display text-4xl font-bold leading-none">
                            <template x-if="points.expiring_soon > 0"><span><span x-text="formatNumber(points.expiring_soon)"></span> puncte expiră curând</span></template>
                            <template x-if="!points.expiring_soon"><span>Niciun punct în expirare</span></template>
                        </h2>
                        <p class="mt-3 text-ink-soft" x-show="points.expiring_soon > 0">Folosește-le în următoarele <strong x-text="points.expiring_days || 30"></strong> zile la o comandă eligibilă.</p>
                        <p class="mt-3 text-ink-soft" x-show="!points.expiring_soon">Continuă să cumperi pentru a câștiga puncte noi.</p>
                        <a href="/cont/recomandari" class="mt-5 inline-flex rounded-full bg-vermilion text-paper px-5 py-3 font-bold hover:bg-vermilion-d transition">Vezi recomandări</a>
                    </div>

                    <div class="rounded-[2rem] border-2 border-ink bg-paper p-6 shadow-ticket">
                        <p class="font-mono text-xs tracking-[.18em] text-ink-soft">REGULI</p>
                        <h2 class="mt-2 font-display text-4xl font-bold leading-none">Pe scurt</h2>
                        <div class="mt-4 space-y-3 text-sm text-ink-soft">
                            <p><strong class="text-ink">Câștigare:</strong> la comenzi eligibile, după confirmare.</p>
                            <p><strong class="text-ink">Folosire:</strong> direct în checkout, în limita regulilor.</p>
                            <p><strong class="text-ink">Afiliere:</strong> puncte după prima comandă eligibilă a prietenului.</p>
                            <p><strong class="text-ink">Expirare:</strong> punctele pot avea termen de valabilitate.</p>
                        </div>
                        <a href="/termeni-program-puncte" class="mt-5 inline-flex font-bold text-vermilion underline-wobble">Vezi regulament</a>
                    </div>
                </aside>
            </section>

            <!-- AUTH GUARD -->
            <div x-show="!isAuth" x-cloak class="mt-8 rounded-[2rem] border-2 border-vermilion bg-rose p-8 text-center">
                <p class="font-display text-3xl font-bold text-vermilion">Trebuie să fii autentificat</p>
                <a href="/autentificare?redirect=/cont/punctele-mele" class="mt-5 inline-flex rounded-full bg-vermilion text-paper px-6 py-3 font-bold">Intră în cont</a>
            </div>
        </main>
    </div>
</div>

<script>
function clientPointsPage() {
    return {
        loading: true,
        isAuth: true,
        loadingTransactions: true,
        transactionType: 'all',

        points: { balance: 0, lifetime_earned: 0, spent: 0, expiring_soon: 0, expiring_days: 30 },
        config: { pointsPerLei: 20, maxRedeemPerOrder: 50 },

        tier: {
            current: { name: 'Beginning', description: 'Faci primii pași.', perks: [
                { label: 'Bonus la comenzi eligibile', active: true },
                { label: 'Recomandări personalizate', active: true },
                { label: 'Campanii exclusive', active: false },
            ] },
            next: null,
            progress: 0,
            toNext: 0,
        },

        referral: {
            code: '',
            displayLink: 'bilete.online/r/—',
            fullUrl: '',
            clicks: 0,
            accounts: 0,
            qualified: 0,
            copied: false,
            regenerating: false,
        },

        transactions: [],

        tierLadder: [
            { name: 'Beginning', threshold: 0,    description: 'Faci primii pași.', perks: [
                { label: 'Bonus la comenzi eligibile', active: true },
                { label: 'Recomandări personalizate',  active: true },
                { label: 'Campanii exclusive',         active: false },
            ] },
            { name: 'Explorer',  threshold: 500,  description: 'Ești client constant, mulțumim!', perks: [
                { label: 'Bonus la comenzi eligibile', active: true },
                { label: 'Recomandări personalizate',  active: true },
                { label: 'Acces preview activități',   active: true },
            ] },
            { name: 'Explorer Plus', threshold: 1200, description: 'Activitate constantă — ești aproape de VIP.', perks: [
                { label: 'Bonus extra la comenzi mari', active: true },
                { label: 'Recomandări premium',         active: true },
                { label: 'Campanii exclusive',          active: true },
            ] },
            { name: 'VIP', threshold: 3000, description: 'Cel mai înalt nivel.', perks: [
                { label: 'Bonus maxim',                  active: true },
                { label: 'Activități în avanpremieră',   active: true },
                { label: 'Suport prioritar',             active: true },
            ] },
        ],

        init() {
            try { this.isAuth = (window.BileteOnlineAuth && BileteOnlineAuth.isLoggedIn && BileteOnlineAuth.isLoggedIn()); } catch (e) { this.isAuth = false; }
            if (! this.isAuth) { this.loading = false; this.loadingTransactions = false; return; }

            this.loadRewards();
            this.loadReferrals();
            this.loadHistory();
        },

        async loadRewards() {
            try {
                const r = await BileteOnlineAPI.get('/customer/rewards');
                const d = (r && r.data) || {};
                const p = d.points || d;
                this.points.balance         = p.balance ?? p.current_balance ?? 0;
                this.points.lifetime_earned = p.lifetime_earned ?? p.total_earned ?? 0;
                this.points.spent           = p.spent ?? p.total_spent ?? 0;
                this.points.expiring_soon   = p.expiring_soon ?? 0;
                this.points.expiring_days   = p.expiring_in_days ?? 30;

                if (d.config) {
                    this.config.pointsPerLei      = d.config.points_per_lei ?? d.config.conversion_rate ?? 20;
                    this.config.maxRedeemPerOrder = d.config.max_redeem_per_order ?? 50;
                }

                this.computeTier();
            } catch (e) {}
            this.loading = false;
        },

        async loadReferrals() {
            try {
                const r = await BileteOnlineAPI.get('/customer/referrals');
                const d = (r && r.data) || {};
                this.referral.code      = d.referral_code ?? d.code ?? '';
                this.referral.clicks    = d.clicks    ?? (d.stats && d.stats.clicks)    ?? 0;
                this.referral.accounts  = d.accounts  ?? (d.stats && d.stats.accounts)  ?? d.signups ?? 0;
                this.referral.qualified = d.qualified ?? (d.stats && d.stats.qualified) ?? d.conversions ?? 0;
            } catch (e) {}

            // Fallback to user profile's referral_code
            if (! this.referral.code) {
                try {
                    const r = await BileteOnlineAPI.customer.getProfile();
                    const u = ((r && r.data) || {});
                    const c = (u.customer && u.customer.referral_code) || u.referral_code;
                    if (c) this.referral.code = c;
                } catch (e) {}
            }

            const host = location.hostname.replace(/^www\./, '');
            const base = (location.protocol + '//' + host).replace(/:\d+$/, '');
            const slug = this.referral.code || '—';
            this.referral.fullUrl     = base + '/r/' + slug;
            this.referral.displayLink = host + '/r/' + slug;
        },

        async loadHistory() {
            try {
                const r = await BileteOnlineAPI.get('/customer/rewards/history', { per_page: 50 });
                const list = (r && r.data && (r.data.history || r.data.items || r.data.transactions)) || (r && r.data) || [];
                this.transactions = (Array.isArray(list) ? list : []).map((tx, i) => this.normalizeTransaction(tx, i));
            } catch (e) {}
            this.loadingTransactions = false;
        },

        normalizeTransaction(tx, idx) {
            const amount = parseInt(tx.amount ?? tx.points ?? 0) || 0;
            const rawType = (tx.type || tx.transaction_type || '').toLowerCase();
            let type = 'earned';
            if (rawType.includes('spent') || rawType.includes('redeem') || amount < 0) type = 'spent';
            if (rawType.includes('expir')) type = 'expired';
            if (rawType.includes('affiliate') || rawType.includes('referral')) type = 'affiliate';

            const labels = { earned: 'câștigate', spent: 'folosite', expired: 'expirate', affiliate: 'afiliere' };
            const classes = {
                earned:    'bg-mint text-forest',
                spent:     'bg-rose text-vermilion',
                expired:   'bg-paper-2 text-ink-soft',
                affiliate: 'bg-forest text-paper',
            };

            const date = tx.created_at ? new Date(tx.created_at) : null;
            return {
                id: tx.id || ('tx-' + idx),
                date: date ? date.toLocaleDateString('ro-RO', { day: 'numeric', month: 'short', year: 'numeric' }) : '',
                title: tx.description || tx.title || (type === 'earned' ? 'Puncte câștigate' : type === 'spent' ? 'Puncte folosite' : type === 'expired' ? 'Puncte expirate' : 'Afiliere'),
                meta: tx.meta || tx.source || tx.order_number || '',
                type: type,
                typeLabel: labels[type] || type,
                badgeClass: classes[type] || 'bg-paper-2',
                amount: type === 'spent' || type === 'expired' ? -Math.abs(amount) : Math.abs(amount),
            };
        },

        filteredTransactions() {
            if (this.transactionType === 'all') return this.transactions;
            return this.transactions.filter(tx => tx.type === this.transactionType);
        },

        computeTier() {
            const balance = this.points.lifetime_earned || this.points.balance;
            let currentIdx = 0;
            for (let i = 0; i < this.tierLadder.length; i++) {
                if (balance >= this.tierLadder[i].threshold) currentIdx = i;
            }
            this.tier.current = this.tierLadder[currentIdx];
            this.tier.next = this.tierLadder[currentIdx + 1] || null;
            if (this.tier.next) {
                const span = this.tier.next.threshold - this.tier.current.threshold;
                const done = balance - this.tier.current.threshold;
                this.tier.progress = Math.min(100, Math.round((done / Math.max(1, span)) * 100));
                this.tier.toNext = Math.max(0, this.tier.next.threshold - balance);
            } else {
                this.tier.progress = 100;
                this.tier.toNext = 0;
            }
        },

        copyReferral() {
            try {
                navigator.clipboard.writeText(this.referral.fullUrl);
                this.referral.copied = true;
                setTimeout(() => { this.referral.copied = false; }, 1800);
            } catch (e) {
                window.prompt('Copiază link:', this.referral.fullUrl);
            }
        },

        whatsappShareUrl() {
            const txt = 'Hei! Am descoperit bilete.online — bilete pentru escape rooms, muzee, ateliere și multe altele. Folosește linkul meu: ' + this.referral.fullUrl;
            return 'https://wa.me/?text=' + encodeURIComponent(txt);
        },

        async regenerateCode() {
            if (! confirm('Sigur regenerezi codul? Linkul curent va fi invalidat.')) return;
            this.referral.regenerating = true;
            try {
                const r = await BileteOnlineAPI.post('/customer/referrals/regenerate', {});
                if (r && r.success) {
                    const d = r.data || {};
                    this.referral.code = d.referral_code || d.code || this.referral.code;
                    const host = location.hostname.replace(/^www\./, '');
                    const base = (location.protocol + '//' + host).replace(/:\d+$/, '');
                    this.referral.fullUrl     = base + '/r/' + (this.referral.code || '—');
                    this.referral.displayLink = host + '/r/' + (this.referral.code || '—');
                }
            } catch (e) {}
            this.referral.regenerating = false;
        },

        // ===== utils =====
        pointsToLei(p) { return Math.floor((p || 0) / (this.config.pointsPerLei || 20)); },
        formatNumber(n) { return new Intl.NumberFormat('ro-RO').format(n || 0); },
        formatLei(n)    { return (new Intl.NumberFormat('ro-RO', { maximumFractionDigits: 0 }).format(n || 0)) + ' lei'; },
    };
}
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>
