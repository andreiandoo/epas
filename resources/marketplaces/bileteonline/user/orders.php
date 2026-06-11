<?php
/**
 * bilete.online — /cont/comenzile-mele (Comenzile mele, v2 full template)
 *
 * Implements template-client-orders-bilete-online-v2.html end-to-end:
 *   - Hero with 4 stat boxes (TOTAL / CHELTUIT / PUNCTE / RETURURI)
 *   - Filter row: search + status + period + sort + reset
 *   - Status pill filters below filters
 *   - Results header with Export CSV + Descarcă istoric
 *   - Order cards: image + status/payment/protection badges + title + city +
 *     date + items + beneficiaries + total + points earned
 *   - 4-box fee breakdown per card (Bilete, Comision platformă, Procesare, Discount)
 *   - 6 CTAs per card (Detalii / Vezi bilete / Confirmare PDF / Factură /
 *     Cere retur / Mai multe)
 *   - Expandable "Mai multe" panel: per-ticket list + financial summary sidebar
 *   - Empty state with reset
 */

require_once __DIR__ . '/../includes/config.php';

$pageTitleRaw    = 'Comenzile mele — ' . SITE_NAME;
$pageDescription = 'Comenzile mele pe bilete.online: bilete, plăți, comisioane, puncte câștigate, documente, retururi.';
$canonicalUrl    = SITE_URL . '/cont/comenzile-mele';
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
                <div class="flex flex-col xl:flex-row xl:items-end xl:justify-between gap-8">
                    <div>
                        <p class="stamp inline-flex px-3 py-1 text-xs font-mono tracking-[.18em] text-ochre">COMENZI CLIENT</p>
                        <h1 class="mt-5 font-display text-5xl sm:text-6xl lg:text-7xl font-bold leading-[.85]">Comenzile mele</h1>
                        <p class="mt-5 max-w-3xl text-paper/65 text-lg leading-relaxed">Vezi istoricul complet al comenzilor, statusul plăților, biletele emise, comisioanele, punctele câștigate, documentele și opțiunile de retur.</p>
                    </div>

                    <div class="grid grid-cols-2 sm:grid-cols-4 xl:grid-cols-2 gap-3 min-w-[320px]">
                        <div class="rounded-2xl bg-paper/10 border border-paper/10 p-4">
                            <p class="font-mono text-[10px] tracking-[.18em] text-paper/45">TOTAL COMENZI</p>
                            <p class="font-display text-4xl font-bold" x-text="stats.total_orders ?? 0">0</p>
                        </div>
                        <div class="rounded-2xl bg-paper/10 border border-paper/10 p-4">
                            <p class="font-mono text-[10px] tracking-[.18em] text-paper/45">CHELTUIT</p>
                            <p class="font-display text-4xl font-bold" x-text="formatLei(stats.total_spent ?? 0) + ' lei'">0 lei</p>
                        </div>
                        <div class="rounded-2xl bg-paper/10 border border-paper/10 p-4">
                            <p class="font-mono text-[10px] tracking-[.18em] text-paper/45">PUNCTE</p>
                            <p class="font-display text-4xl font-bold" x-text="stats.total_points ?? 0">0</p>
                        </div>
                        <div class="rounded-2xl bg-paper/10 border border-paper/10 p-4">
                            <p class="font-mono text-[10px] tracking-[.18em] text-paper/45">RETURURI</p>
                            <p class="font-display text-4xl font-bold" x-text="stats.total_refunds ?? 0">0</p>
                        </div>
                    </div>
                </div>
            </section>

            <!-- FILTERS -->
            <section class="mt-6 rounded-[2rem] border-2 border-ink bg-paper p-5 sm:p-6 shadow-ticket">
                <div class="grid xl:grid-cols-[1.3fr_.7fr_.7fr_.7fr_auto] gap-3 items-end">
                    <label>
                        <span class="block mb-1.5 text-sm font-bold">Caută comandă</span>
                        <input class="field" x-model="search" placeholder="Număr comandă, activitate, oraș, metodă plată…">
                    </label>
                    <label>
                        <span class="block mb-1.5 text-sm font-bold">Status</span>
                        <select class="field" x-model="status">
                            <option value="all">Toate</option>
                            <option value="confirmed">Confirmate</option>
                            <option value="pending">În așteptare</option>
                            <option value="refunded">Retur / rambursare</option>
                            <option value="failed">Eșuate</option>
                        </select>
                    </label>
                    <label>
                        <span class="block mb-1.5 text-sm font-bold">Perioadă</span>
                        <select class="field" x-model="period">
                            <option value="all">Toate</option>
                            <option value="30">Ultimele 30 zile</option>
                            <option value="90">Ultimele 90 zile</option>
                            <option value="year">Anul curent</option>
                        </select>
                    </label>
                    <label>
                        <span class="block mb-1.5 text-sm font-bold">Sortare</span>
                        <select class="field" x-model="sort">
                            <option value="newest">Cele mai noi</option>
                            <option value="oldest">Cele mai vechi</option>
                            <option value="value_desc">Valoare desc.</option>
                            <option value="value_asc">Valoare asc.</option>
                        </select>
                    </label>
                    <button @click="resetFilters()" class="rounded-full border-2 border-ink px-5 py-3.5 font-bold hover:bg-ink hover:text-paper transition">Reset</button>
                </div>

                <div class="mt-5 flex flex-wrap gap-2">
                    <button @click="status='all'" :class="status==='all'?'bg-ink text-paper':'bg-paper-2'" class="rounded-full px-4 py-2 font-bold border border-ink/10">Toate</button>
                    <button @click="status='confirmed'" :class="status==='confirmed'?'bg-forest text-paper':'bg-paper-2'" class="rounded-full px-4 py-2 font-bold border border-ink/10">Confirmate</button>
                    <button @click="status='pending'" :class="status==='pending'?'bg-ochre text-ink':'bg-paper-2'" class="rounded-full px-4 py-2 font-bold border border-ink/10">În așteptare</button>
                    <button @click="status='refunded'" :class="status==='refunded'?'bg-rose text-vermilion':'bg-paper-2'" class="rounded-full px-4 py-2 font-bold border border-ink/10">Retururi</button>
                    <button @click="status='failed'" :class="status==='failed'?'bg-vermilion text-paper':'bg-paper-2'" class="rounded-full px-4 py-2 font-bold border border-ink/10">Eșuate</button>
                </div>
            </section>

            <!-- RESULTS -->
            <section class="mt-6">
                <div class="flex flex-col sm:flex-row sm:items-end sm:justify-between gap-3 mb-4">
                    <div>
                        <p class="font-mono text-xs tracking-[.18em] text-ink-soft">REZULTATE</p>
                        <h2 class="font-display text-4xl sm:text-5xl font-bold leading-none" x-text="filteredOrders().length + ' comenzi'">0 comenzi</h2>
                    </div>
                    <div class="flex gap-2">
                        <button @click="exportCsv()" class="rounded-full bg-paper border-2 border-ink px-5 py-3 font-bold hover:bg-ink hover:text-paper transition">Export CSV</button>
                        <button @click="downloadHistory()" :disabled="historyBusy" class="rounded-full bg-vermilion text-paper px-5 py-3 font-bold hover:bg-vermilion-d transition disabled:opacity-60">
                            <span x-show="!historyBusy">Descarcă istoric</span>
                            <span x-show="historyBusy" x-cloak>Se descarcă…</span>
                        </button>
                    </div>
                </div>

                <div x-show="loading" class="space-y-4">
                    <div class="h-44 rounded-[2rem] bg-paper-2/60 animate-pulse"></div>
                    <div class="h-44 rounded-[2rem] bg-paper-2/60 animate-pulse"></div>
                </div>

                <div x-show="!loading" class="space-y-4">
                    <template x-for="order in filteredOrders()" :key="order._key">
                        <article class="rounded-[2rem] border-2 border-ink bg-paper shadow-ticket overflow-hidden" :id="'order-' + order._idAnchor">
                            <div class="p-5 sm:p-6">
                                <div class="flex flex-col xl:flex-row xl:items-start xl:justify-between gap-5">
                                    <div class="flex gap-4 min-w-0">
                                        <img :src="order.image" :alt="order.title" class="w-24 h-24 rounded-2xl object-cover border border-ink/10 shrink-0" onerror="this.src='data:image/svg+xml;utf8,&lt;svg xmlns=%22http://www.w3.org/2000/svg%22 viewBox=%220 0 100 100%22&gt;&lt;rect fill=%22%231B1714%22 width=%22100%22 height=%22100%22/&gt;&lt;text x=%2250%22 y=%2258%22 text-anchor=%22middle%22 fill=%22%23E84527%22 font-size=%2240%22&gt;🧾&lt;/text&gt;&lt;/svg&gt;'">
                                        <div class="min-w-0">
                                            <div class="flex flex-wrap gap-2">
                                                <span class="rounded-full px-3 py-1 text-xs font-bold" :class="order.statusClass" x-text="order.statusLabel"></span>
                                                <span x-show="order.payment" class="rounded-full bg-paper-2 border border-ink/10 px-3 py-1 text-xs font-bold" x-text="order.payment"></span>
                                                <span x-show="order.protection" class="rounded-full bg-mint text-forest px-3 py-1 text-xs font-bold">protecție bilet</span>
                                            </div>
                                            <h3 class="mt-3 font-display text-3xl sm:text-4xl font-bold leading-none">
                                                <a :href="order.url" class="hover:text-vermilion transition" x-text="order.id"></a>
                                            </h3>
                                            <p class="mt-1 text-ink-soft" x-text="order.title + (order.city ? (' · ' + order.city) : '')"></p>
                                            <p class="mt-1 text-sm text-ink-soft">
                                                <span x-text="order.date"></span>
                                                <span x-show="order.items"> · <span x-text="order.items"></span> bilete</span>
                                                <span x-show="order.beneficiaries"> · <span x-text="order.beneficiaries"></span> beneficiari</span>
                                            </p>
                                        </div>
                                    </div>

                                    <div class="xl:text-right">
                                        <p class="font-mono text-[10px] tracking-[.18em] text-ink-soft">TOTAL COMANDĂ</p>
                                        <p class="font-display text-5xl font-bold" x-text="order.total"></p>
                                        <p class="mt-1 text-sm text-ink-soft" x-show="order.points"><span x-text="'+' + order.points + ' puncte bonus'"></span></p>
                                    </div>
                                </div>

                                <div class="mt-5 grid md:grid-cols-4 gap-3">
                                    <div class="rounded-2xl bg-paper-2 border border-ink/10 p-4">
                                        <p class="font-mono text-[10px] tracking-[.16em] text-ink-soft">BILETE</p>
                                        <p class="mt-1 font-bold" x-text="order.ticketsValue"></p>
                                    </div>
                                    <div class="rounded-2xl bg-paper-2 border border-ink/10 p-4">
                                        <p class="font-mono text-[10px] tracking-[.16em] text-ink-soft">COMISION PLATFORMĂ</p>
                                        <p class="mt-1 font-bold" x-text="order.platformFee"></p>
                                    </div>
                                    <div class="rounded-2xl bg-paper-2 border border-ink/10 p-4">
                                        <p class="font-mono text-[10px] tracking-[.16em] text-ink-soft">PROCESARE PLATĂ</p>
                                        <p class="mt-1 font-bold" x-text="order.processingFee"></p>
                                    </div>
                                    <div class="rounded-2xl bg-paper-2 border border-ink/10 p-4">
                                        <p class="font-mono text-[10px] tracking-[.16em] text-ink-soft">DISCOUNT / PUNCTE</p>
                                        <p class="mt-1 font-bold" x-text="order.discount"></p>
                                    </div>
                                </div>

                                <div class="mt-5 flex flex-wrap gap-2">
                                    <a :href="order.url" class="rounded-full bg-ink text-paper px-4 py-2 text-sm font-bold hover:bg-vermilion transition">Detalii comandă</a>
                                    <a :href="order.ticketsUrl" class="rounded-full bg-vermilion text-paper px-4 py-2 text-sm font-bold hover:bg-vermilion-d transition">Vezi bilete</a>
                                    <a :href="order.confirmationUrl" target="_blank" rel="noopener" class="rounded-full border border-ink/20 px-4 py-2 text-sm font-bold hover:bg-ink hover:text-paper transition">Confirmare PDF</a>
                                    <a :href="order.invoiceUrl" target="_blank" rel="noopener" class="rounded-full border border-ink/20 px-4 py-2 text-sm font-bold hover:bg-ink hover:text-paper transition">Factură</a>
                                    <a x-show="order.refundable" :href="order.refundUrl" class="rounded-full border border-vermilion/30 bg-rose text-vermilion px-4 py-2 text-sm font-bold hover:bg-vermilion hover:text-paper transition">Cere retur</a>
                                    <button @click="toggle(order._key)" class="rounded-full border border-ink/20 px-4 py-2 text-sm font-bold hover:bg-ink hover:text-paper transition">
                                        <span x-text="openOrder === order._key ? 'Ascunde detalii' : 'Mai multe'"></span>
                                    </button>
                                </div>
                            </div>

                            <!-- Expanded panel -->
                            <div x-show="openOrder === order._key" x-cloak x-collapse class="border-t-2 border-dashed border-ink/15 bg-paper-2/60 p-5 sm:p-6">
                                <div class="grid lg:grid-cols-[1fr_360px] gap-6">
                                    <div>
                                        <p class="font-mono text-xs tracking-[.18em] text-ink-soft">BILETE DIN COMANDĂ</p>
                                        <div x-show="(order.tickets || []).length === 0" class="mt-3 text-sm text-ink-soft italic">Detaliile biletelor se încarcă din rezervare.</div>
                                        <div x-show="(order.tickets || []).length > 0" class="mt-3 space-y-2">
                                            <template x-for="ticket in (order.tickets || [])" :key="ticket.code || ticket.id">
                                                <div class="rounded-2xl bg-paper border border-ink/10 p-4 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
                                                    <div class="min-w-0">
                                                        <p class="font-bold" x-text="ticket.name || ticket.attendee_name || 'Beneficiar necompletat'"></p>
                                                        <p class="text-sm text-ink-soft" x-text="(ticket.type || 'Standard') + ' · ' + (ticket.code || '')"></p>
                                                    </div>
                                                    <span class="rounded-full px-3 py-1 text-xs font-bold shrink-0" :class="ticket.used ? 'bg-paper-2 text-ink-soft' : 'bg-mint text-forest'" x-text="ticket.used ? 'scanat' : 'valid'"></span>
                                                </div>
                                            </template>
                                        </div>
                                    </div>

                                    <aside class="rounded-3xl bg-paper border border-ink/10 p-5">
                                        <p class="font-mono text-xs tracking-[.18em] text-ink-soft">SUMAR FINANCIAR</p>
                                        <div class="mt-4 space-y-3 text-sm">
                                            <div class="flex justify-between gap-4"><span>Bilete</span><strong x-text="order.ticketsValue"></strong></div>
                                            <div class="flex justify-between gap-4"><span>Comision platformă</span><strong x-text="order.platformFee"></strong></div>
                                            <div class="flex justify-between gap-4"><span>Procesare plată</span><strong x-text="order.processingFee"></strong></div>
                                            <div class="flex justify-between gap-4"><span>Discount</span><strong x-text="order.discount"></strong></div>
                                            <div class="pt-3 border-t border-ink/10 flex justify-between gap-4 text-lg"><span>Total</span><strong x-text="order.total"></strong></div>
                                        </div>
                                        <p class="mt-4 text-xs text-ink-soft">Comisioanele sunt afișate separat pentru transparență.</p>
                                        <div class="mt-4 flex flex-wrap gap-2">
                                            <button @click="resendConfirmation(order)" class="rounded-full bg-ink text-paper px-4 py-2 text-xs font-bold hover:bg-vermilion transition">Retrimite email</button>
                                        </div>
                                    </aside>
                                </div>
                            </div>
                        </article>
                    </template>
                </div>

                <div x-show="!loading && filteredOrders().length === 0" class="mt-6 rounded-[2rem] border-2 border-dashed border-ink/20 bg-paper-2/60 p-10 text-center">
                    <p class="text-5xl">🧾</p>
                    <p class="mt-4 font-display text-3xl font-bold" x-text="orders.length === 0 ? 'Nu ai comenzi încă' : 'Nicio comandă pentru filtrele alese'"></p>
                    <p class="mt-2 text-ink-soft">Descoperă activități și rezervă online.</p>
                    <button x-show="orders.length > 0" @click="resetFilters()" class="mt-5 rounded-full bg-vermilion text-paper px-6 py-4 font-bold hover:bg-vermilion-d transition">Resetează filtrele</button>
                    <a x-show="orders.length === 0" href="/categorii" class="mt-5 inline-flex rounded-full bg-vermilion text-paper px-6 py-4 font-bold hover:bg-vermilion-d transition">Descoperă activități</a>
                </div>
            </section>

            <!-- AUTH GUARD -->
            <div x-show="isAuth === false" x-cloak class="mt-8 rounded-[2rem] border-2 border-vermilion bg-rose p-8 text-center">
                <p class="font-display text-3xl font-bold text-vermilion">Trebuie să fii autentificat</p>
                <a href="/autentificare?redirect=/cont/comenzile-mele" class="mt-5 inline-flex rounded-full bg-vermilion text-paper px-6 py-3 font-bold">Intră în cont</a>
            </div>
        </main>
    </div>
</div>

<script>
function clientOrdersPage() {
    return {
        loading: true,
        isAuth: null,
        orders: [],
        stats: { total_orders: 0, total_spent: 0, total_points: 0, total_refunds: 0 },
        search: '',
        status: 'all',
        period: 'all',
        sort: 'newest',
        openOrder: null,
        historyBusy: false,

        init() {
            try { if (window.BileteOnlineAuth && BileteOnlineAuth.getToken && BileteOnlineAuth.getToken()) this.isAuth = true; } catch (e) {}
            if (this.isAuth === false) { this.loading = false; return; }
            this.load();

            // Open a specific order if URL hash points to one (#BO-2026-XXXX)
            const h = (location.hash || '').replace('#', '').trim();
            if (h) this.openOrder = h;
        },

        async load() {
            try {
                const r = await BileteOnlineAPI.customer.getOrders({ per_page: 100 });
                const list = (r && r.data && (r.data.orders || r.data.items)) || (r && r.data) || [];
                this.orders = (Array.isArray(list) ? list : []).map(o => this.normalizeOrder(o));

                // Compute or carry-over stats
                const apiStats = (r && r.stats) || (r && r.data && r.data.stats) || null;
                if (apiStats) {
                    this.stats = {
                        total_orders:  apiStats.total_orders ?? this.orders.length,
                        total_spent:   apiStats.total_spent ?? this.orders.reduce((s, o) => s + (o._totalNumber || 0), 0),
                        total_points:  apiStats.total_points ?? this.orders.reduce((s, o) => s + (o.points || 0), 0),
                        total_refunds: apiStats.total_refunds ?? this.orders.filter(o => o.status === 'refunded').length,
                    };
                } else {
                    this.stats = {
                        total_orders:  this.orders.length,
                        total_spent:   this.orders.reduce((s, o) => s + (o._totalNumber || 0), 0),
                        total_points:  this.orders.reduce((s, o) => s + (o.points || 0), 0),
                        total_refunds: this.orders.filter(o => o.status === 'refunded').length,
                    };
                }
            } catch (e) {}
            this.loading = false;
        },

        normalizeOrder(o) {
            const status = (o.status || 'confirmed').toLowerCase();
            const map = {
                paid:      { label: 'plătită',       cls: 'bg-mint text-forest',     refundable: true,  status: 'confirmed' },
                confirmed: { label: 'confirmată',    cls: 'bg-mint text-forest',     refundable: true,  status: 'confirmed' },
                completed: { label: 'finalizată',   cls: 'bg-paper-2 text-ink-soft', refundable: false, status: 'confirmed' },
                pending:   { label: 'în așteptare', cls: 'bg-ochre text-ink',        refundable: false, status: 'pending' },
                refunded:  { label: 'retur',         cls: 'bg-rose text-vermilion',  refundable: false, status: 'refunded' },
                cancelled: { label: 'anulată',       cls: 'bg-rose text-vermilion',  refundable: false, status: 'refunded' },
                failed:    { label: 'plată eșuată',  cls: 'bg-vermilion text-paper', refundable: false, status: 'failed' },
                free:      { label: 'gratuită',      cls: 'bg-mint text-forest',     refundable: false, status: 'confirmed' },
            };
            const meta = map[status] || map.confirmed;

            const total = parseFloat(o.total_amount ?? o.total ?? 0) || 0;
            const ticketsValue = parseFloat(o.subtotal ?? o.tickets_value ?? (total - (o.platform_fee || 0) - (o.processing_fee || 0))) || 0;
            const platformFee  = parseFloat(o.platform_fee ?? o.commission ?? 0) || 0;
            const processingFee= parseFloat(o.processing_fee ?? o.payment_fee ?? 0) || 0;
            const discount     = parseFloat(o.discount ?? o.discount_amount ?? 0) || 0;
            const items = (o.items || o.order_items || []);
            const ticketsList = (o.tickets || o.tickets_list || []).map(t => ({
                name: t.attendee_name || t.name,
                type: (t.ticket_type && (t.ticket_type.name || t.ticket_type)) || t.type || 'Standard',
                code: t.code || t.barcode || ('T-' + (t.id || '')),
                used: t.status === 'checked_in' || t.status === 'used',
            }));
            const event = o.event || (items[0] && items[0].event) || null;
            const title = (event && (event.title || event.name)) || (items[0] && (items[0].name || items[0].title)) || 'Comandă';
            const city  = (event && (event.city || (event.venue && (event.venue.city || (event.venue.address && event.venue.address.city))))) || o.city || '';
            const image = (event && (event.cover_image_url || event.image)) || o.cover_image_url || o.image || null;
            const orderNum = o.order_number || o.number || ('BO-' + (o.id || ''));
            const idDisplay = '#' + (orderNum.startsWith('#') ? orderNum.slice(1) : orderNum);
            const baseUrl = '/cont/comenzile-mele#' + (orderNum.startsWith('#') ? orderNum.slice(1) : orderNum);

            const dateLocal = (() => {
                try {
                    const dt = new Date(o.created_at);
                    if (isNaN(dt)) return '';
                    return dt.toLocaleDateString('ro-RO', { day: 'numeric', month: 'long', year: 'numeric' });
                } catch (e) { return ''; }
            })();

            const fmt = (n) => new Intl.NumberFormat('ro-RO', { maximumFractionDigits: 2 }).format(n || 0) + ' lei';

            const points = parseInt(o.points_earned || o.earned_points || 0) || 0;

            return {
                _key: orderNum,
                _idAnchor: (orderNum.startsWith('#') ? orderNum.slice(1) : orderNum),
                _totalNumber: total,
                status: meta.status,
                statusLabel: meta.label,
                statusClass: meta.cls,
                id: idDisplay,
                title: title,
                city: city,
                date: dateLocal,
                payment: o.payment_method || o.payment_provider || 'Card',
                protection: !!(o.has_protection || o.protection),
                items: items.length || ticketsList.length || (o.quantity || 0),
                beneficiaries: ticketsList.length || items.length || 0,
                total: fmt(total),
                points: points,
                ticketsValue:  fmt(ticketsValue),
                platformFee:   fmt(platformFee),
                processingFee: fmt(processingFee),
                discount:      (discount > 0 ? '−' + fmt(discount) : '−0 lei'),
                refundable:    meta.refundable && new Date(o.event && (o.event.date || o.event.start_date) || o.created_at) > new Date(),
                image:         image,
                url:           baseUrl,
                ticketsUrl:    '/cont/biletele-mele?order=' + encodeURIComponent(orderNum),
                confirmationUrl: '/api/proxy.php?action=order.confirmation-pdf&id=' + encodeURIComponent(o.id || orderNum),
                invoiceUrl:    '/api/proxy.php?action=order.invoice-pdf&id=' + encodeURIComponent(o.id || orderNum),
                refundUrl:     '/cerere-retur?order=' + encodeURIComponent(orderNum),
                tickets:       ticketsList,
                raw_id:        o.id || orderNum,
            };
        },

        toggle(key) { this.openOrder = this.openOrder === key ? null : key; },

        resetFilters() { this.search = ''; this.status = 'all'; this.period = 'all'; this.sort = 'newest'; },

        filteredOrders() {
            const q = (this.search || '').toLowerCase().trim();
            let list = this.orders.filter(order => {
                if (this.status !== 'all' && order.status !== this.status) return false;
                if (this.period !== 'all') {
                    const days = this.period === 'year' ? null : parseInt(this.period);
                    const dt = order.date ? new Date(order.date) : null;
                    if (this.period === 'year') {
                        if (! dt || dt.getFullYear() !== new Date().getFullYear()) return false;
                    } else if (days) {
                        if (! dt) return false;
                        const cutoff = Date.now() - days * 86400000;
                        if (dt.getTime() < cutoff) return false;
                    }
                }
                if (! q) return true;
                return JSON.stringify(order).toLowerCase().includes(q);
            });

            if (this.sort === 'value_desc') list.sort((a, b) => b._totalNumber - a._totalNumber);
            else if (this.sort === 'value_asc') list.sort((a, b) => a._totalNumber - b._totalNumber);
            else if (this.sort === 'oldest') list.sort((a, b) => new Date(a.date) - new Date(b.date));
            else list.sort((a, b) => new Date(b.date) - new Date(a.date));

            return list;
        },

        exportCsv() {
            const list = this.filteredOrders();
            if (! list.length) return;
            const rows = [
                ['Comandă', 'Data', 'Activitate', 'Oraș', 'Plată', 'Status', 'Total', 'Bilete', 'Comision', 'Procesare', 'Discount', 'Puncte']
            ];
            list.forEach(o => {
                rows.push([
                    o.id, o.date, o.title, o.city, o.payment, o.statusLabel,
                    o.total, o.ticketsValue, o.platformFee, o.processingFee, o.discount, o.points,
                ]);
            });
            const csv = rows.map(r => r.map(cell => '"' + String(cell ?? '').replace(/"/g, '""') + '"').join(',')).join('\n');
            const blob = new Blob(['﻿' + csv], { type: 'text/csv;charset=utf-8' });
            const a = document.createElement('a');
            a.href = URL.createObjectURL(blob);
            a.download = 'comenzi-bilete-online-' + new Date().toISOString().slice(0, 10) + '.csv';
            document.body.appendChild(a);
            a.click();
            a.remove();
        },

        async downloadHistory() {
            // Trigger GDPR-style export via existing endpoint
            this.historyBusy = true;
            try {
                const r = await BileteOnlineAPI.post('/customer/gdpr/export', {});
                if (r && r.success) {
                    alert('Cerere de export pornită. Vei primi un email cu link de descărcare.');
                } else {
                    this.exportCsv(); // fallback to CSV
                }
            } catch (e) {
                this.exportCsv();
            }
            this.historyBusy = false;
        },

        async resendConfirmation(order) {
            try {
                const r = await BileteOnlineAPI.post('/customer/orders/' + encodeURIComponent(order.raw_id) + '/resend-confirmation', {});
                alert((r && r.success) ? 'Email-ul de confirmare a fost retrimis.' : 'Nu am putut retrimite confirmarea.');
            } catch (e) {
                alert('Eroare la retrimitere.');
            }
        },

        formatLei(n) {
            if (n == null) return '0';
            return new Intl.NumberFormat('ro-RO', { maximumFractionDigits: 0 }).format(Number(n) || 0);
        },
    };
}
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>
