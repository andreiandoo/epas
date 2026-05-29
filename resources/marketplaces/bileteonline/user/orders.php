<?php
/**
 * bilete.online — /cont/comenzi (Comenzile mele, v2 design)
 *
 * Order history with status filter, search, collapsible per-order detail
 * (items, payment, status timeline) and quick refund / re-send buttons.
 */

require_once __DIR__ . '/../includes/config.php';

$pageTitleRaw    = 'Comenzile mele — ' . SITE_NAME;
$pageDescription = 'Istoricul comenzilor tale pe bilete.online: status, plată, bilete emise și opțiuni de retur.';
$canonicalUrl    = SITE_URL . '/cont/comenzi';
$noindex         = true;
$currentPage     = 'cont';
$cssBundle       = 'auth';

include __DIR__ . '/../includes/head.php';
include __DIR__ . '/../includes/header.php';
?>

<div class="max-w-[1500px] mx-auto px-4 sm:px-6 py-6 lg:py-8">
    <div class="grid lg:grid-cols-[280px_minmax(0,1fr)] gap-6 lg:gap-8 items-start">

        <?php $currentClientPage = 'orders'; include __DIR__ . '/../includes/client-sidebar-v2.php'; ?>

        <main class="min-w-0" x-data="clientOrdersPage()" x-init="init()">

            <!-- HERO -->
            <section class="rounded-[2rem] border-2 border-ink bg-ink text-paper p-6 sm:p-8 shadow-deep">
                <div class="grid xl:grid-cols-[1fr_360px] gap-8 items-center">
                    <div>
                        <p class="stamp inline-flex px-3 py-1 text-xs font-mono tracking-[.18em] text-ochre">CONT CLIENT · COMENZI</p>
                        <h1 class="mt-5 font-display text-5xl sm:text-6xl lg:text-7xl font-bold leading-[.85]">Comenzile mele</h1>
                        <p class="mt-5 max-w-3xl text-paper/65 text-lg leading-relaxed">
                            Toate comenzile tale într-un singur loc: status, plată, bilete emise, retururi și facturi.
                        </p>
                    </div>
                    <div class="ticket relative bg-paper text-ink rounded-[2rem] border-2 border-paper/30 p-6 rotate-[-2deg]" style="--perf:100%">
                        <p class="font-mono text-xs tracking-[.18em] text-ink-soft">TOTAL CHELTUIT</p>
                        <p class="mt-2 font-display text-5xl font-bold leading-none" x-text="formatLei(stats.total_spent)">—</p>
                        <p class="mt-3 text-sm text-ink-soft">pe <span class="font-bold" x-text="stats.total_orders || orders.length"></span> comenzi</p>
                        <p x-show="stats.total_saved" class="mt-1 text-sm text-forest">economisit <span x-text="formatLei(stats.total_saved)"></span> prin coduri</p>
                    </div>
                </div>
            </section>

            <!-- FILTERS -->
            <section class="mt-6 rounded-[2rem] border-2 border-ink bg-paper p-5 sm:p-6 shadow-ticket">
                <div class="grid xl:grid-cols-[1.5fr_.7fr_auto] gap-3 items-end">
                    <label>
                        <span class="block mb-1.5 text-sm font-bold">Caută comandă</span>
                        <input class="field" x-model="search" placeholder="Număr, activitate, eveniment...">
                    </label>
                    <label>
                        <span class="block mb-1.5 text-sm font-bold">Status</span>
                        <select class="field" x-model="status">
                            <option value="all">Toate</option>
                            <option value="paid">Plătită</option>
                            <option value="confirmed">Confirmată</option>
                            <option value="completed">Finalizată</option>
                            <option value="pending">În așteptare</option>
                            <option value="cancelled">Anulată</option>
                            <option value="refunded">Rambursată</option>
                        </select>
                    </label>
                    <button @click="resetFilters()" class="rounded-full border-2 border-ink px-5 py-3.5 font-bold hover:bg-ink hover:text-paper transition">Reset</button>
                </div>
            </section>

            <!-- ORDERS LIST -->
            <section class="mt-6">
                <div class="flex items-end justify-between gap-3 mb-4">
                    <div>
                        <p class="font-mono text-xs tracking-[.18em] text-ink-soft">REZULTATE</p>
                        <h2 class="font-display text-4xl sm:text-5xl font-bold leading-none" x-text="filteredOrders().length + ' comenzi'"></h2>
                    </div>
                    <a href="/recuperare-comanda" class="text-vermilion font-bold underline-wobble hidden sm:inline-flex">Nu găsești o comandă? →</a>
                </div>

                <div x-show="loading" class="space-y-4">
                    <div class="h-32 rounded-[2rem] bg-paper-2/60 animate-pulse"></div>
                    <div class="h-32 rounded-[2rem] bg-paper-2/60 animate-pulse"></div>
                </div>

                <div x-show="!loading && filteredOrders().length > 0" class="space-y-4">
                    <template x-for="order in filteredOrders()" :key="order.id || order.order_number">
                        <article class="rounded-[2rem] border-2 border-ink bg-paper shadow-ticket overflow-hidden">
                            <button @click="toggle(order)" class="w-full p-5 sm:p-6 grid sm:grid-cols-[1fr_auto] gap-4 items-start text-left">
                                <div class="min-w-0">
                                    <div class="flex flex-wrap items-center gap-2">
                                        <span class="rounded-full px-3 py-1 text-xs font-bold" :class="badgeClass(order.status)" x-text="statusLabel(order.status)"></span>
                                        <span class="font-mono text-xs text-ink-soft" x-text="formatDate(order.created_at || order.paid_at)"></span>
                                    </div>
                                    <h3 class="mt-3 font-display text-3xl font-bold leading-none" x-text="orderTitle(order)"></h3>
                                    <p class="mt-1 text-ink-soft font-mono text-sm" x-text="'#' + (order.order_number || order.id)"></p>
                                </div>
                                <div class="text-right shrink-0">
                                    <p class="font-display text-3xl font-bold leading-none" x-text="formatLei(order.total) + ' ' + (order.currency || 'RON')"></p>
                                    <p class="text-sm text-ink-soft mt-1" x-text="(order.tickets_count || (order.tickets && order.tickets.length) || 0) + ' bilete'"></p>
                                    <span class="mt-2 inline-flex items-center gap-1 text-vermilion font-bold text-xs" x-text="expandedOrder === (order.id || order.order_number) ? 'Ascunde detalii ▴' : 'Vezi detalii ▾'"></span>
                                </div>
                            </button>

                            <div x-show="expandedOrder === (order.id || order.order_number)" x-collapse class="border-t-2 border-dashed border-ink/15 px-5 sm:px-6 py-5 bg-paper-2/40">
                                <div class="grid md:grid-cols-2 gap-5">
                                    <div>
                                        <p class="font-mono text-xs tracking-[.18em] text-ink-soft">BILETE</p>
                                        <div class="mt-2 space-y-2">
                                            <template x-for="item in (order.items || [])" :key="item.id || (item.name + Math.random())">
                                                <div class="flex justify-between gap-3 text-sm">
                                                    <span class="font-bold" x-text="(item.quantity || 1) + 'x ' + (item.name || 'Bilet')"></span>
                                                    <span class="font-mono" x-text="formatLei(item.total || item.unit_price || 0)"></span>
                                                </div>
                                            </template>
                                            <div x-show="!order.items || order.items.length === 0" class="text-ink-soft text-sm">Detalii bilete indisponibile.</div>
                                        </div>
                                    </div>
                                    <div>
                                        <p class="font-mono text-xs tracking-[.18em] text-ink-soft">PLATĂ</p>
                                        <dl class="mt-2 space-y-1 text-sm">
                                            <div class="flex justify-between gap-3"><dt class="text-ink-soft">Subtotal</dt><dd class="font-mono" x-text="formatLei(order.subtotal || 0)"></dd></div>
                                            <div x-show="order.discount_amount > 0" class="flex justify-between gap-3"><dt class="text-ink-soft">Reducere</dt><dd class="font-mono text-forest" x-text="'-' + formatLei(order.discount_amount)"></dd></div>
                                            <div class="flex justify-between gap-3 font-bold pt-1 border-t border-ink/10"><dt>Total</dt><dd class="font-mono" x-text="formatLei(order.total) + ' ' + (order.currency || 'RON')"></dd></div>
                                            <div x-show="order.meta && order.meta.payment_method" class="flex justify-between gap-3 text-ink-soft text-xs"><dt>Metodă</dt><dd x-text="(order.meta && order.meta.payment_method) || ''"></dd></div>
                                        </dl>
                                    </div>
                                </div>
                                <div class="mt-5 flex flex-wrap gap-2">
                                    <a href="/cont/bilete" class="rounded-full bg-ink text-paper px-4 py-2 text-sm font-bold hover:bg-vermilion transition">Vezi biletele</a>
                                    <a :href="'/recuperare-comanda?order=' + (order.order_number || '')" class="rounded-full border-2 border-ink/15 px-4 py-2 text-sm font-bold hover:border-ink transition">Retrimite email confirmare</a>
                                    <a x-show="canRefund(order)" :href="'/cont/cerere-rambursare?order=' + (order.id || '')" class="rounded-full border border-vermilion/30 bg-rose text-vermilion px-4 py-2 text-sm font-bold hover:bg-vermilion hover:text-paper transition">Cere retur</a>
                                </div>
                            </div>
                        </article>
                    </template>
                </div>

                <div x-show="!loading && filteredOrders().length === 0" class="rounded-[2rem] border-2 border-dashed border-ink/20 bg-paper-2/60 p-10 text-center">
                    <p class="text-5xl">🧾</p>
                    <p class="mt-4 font-display text-3xl font-bold" x-text="orders.length === 0 ? 'Nu ai comenzi încă' : 'Nicio comandă pentru filtrele alese'"></p>
                    <p class="mt-2 text-ink-soft">Descoperă activități și rezervă online.</p>
                    <a href="/categorii" class="mt-5 inline-flex rounded-full bg-vermilion text-paper px-6 py-3 font-bold">Descoperă activități</a>
                </div>
            </section>

            <!-- AUTH GUARD -->
            <div x-show="!isAuth" x-cloak class="mt-8 rounded-[2rem] border-2 border-vermilion bg-rose p-8 text-center">
                <p class="font-display text-3xl font-bold text-vermilion">Trebuie să fii autentificat</p>
                <a href="/autentificare?redirect=/cont/comenzi" class="mt-5 inline-flex rounded-full bg-vermilion text-paper px-6 py-3 font-bold">Intră în cont</a>
            </div>
        </main>
    </div>
</div>

<script>
function clientOrdersPage() {
    return {
        loading: true,
        isAuth: true,
        orders: [],
        stats: {},
        search: '',
        status: 'all',
        expandedOrder: null,

        init() {
            try { this.isAuth = (window.BileteOnlineAuth && BileteOnlineAuth.isLoggedIn && BileteOnlineAuth.isLoggedIn()); } catch (e) { this.isAuth = false; }
            if (! this.isAuth) { this.loading = false; return; }
            this.load();
        },

        async load() {
            try {
                const r = await BileteOnlineAPI.customer.getOrders({ per_page: 50 });
                const list = (r && r.data && (r.data.orders || r.data.items)) || (r && r.data) || [];
                this.orders = Array.isArray(list) ? list : [];
                this.stats = (r && r.stats) || (r && r.data && r.data.stats) || {};
            } catch (e) {}
            this.loading = false;
        },

        toggle(order) {
            const key = order.id || order.order_number;
            this.expandedOrder = this.expandedOrder === key ? null : key;
        },

        filteredOrders() {
            const q = (this.search || '').toLowerCase().trim();
            return this.orders.filter(o => {
                if (this.status !== 'all' && o.status !== this.status) return false;
                if (! q) return true;
                return JSON.stringify(o).toLowerCase().includes(q);
            });
        },

        resetFilters() { this.search = ''; this.status = 'all'; },

        orderTitle(o) {
            if (o.event && (o.event.title || o.event.name)) return o.event.title || o.event.name;
            if (Array.isArray(o.items) && o.items[0]) return o.items[0].name || 'Comandă';
            return 'Comandă';
        },

        statusLabel(s) {
            const map = { paid: 'plătită', confirmed: 'confirmată', completed: 'finalizată', pending: 'în așteptare', cancelled: 'anulată', refunded: 'rambursată', free: 'gratuită' };
            return map[s] || s || '—';
        },
        badgeClass(s) {
            if (s === 'cancelled' || s === 'refunded') return 'bg-rose text-vermilion';
            if (s === 'pending') return 'bg-paper-2 text-ink-soft';
            return 'bg-mint text-forest';
        },
        canRefund(o) { return ['paid','confirmed','completed'].includes(o.status); },

        formatLei(n) {
            if (n == null) return '—';
            return new Intl.NumberFormat('ro-RO', { maximumFractionDigits: 2 }).format(Number(n) || 0);
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
