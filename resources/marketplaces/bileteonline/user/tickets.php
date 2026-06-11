<?php
/**
 * bilete.online — /cont/bilete (Biletele mele, v2 design)
 *
 * Lists all the customer's tickets with status/city/sort filters, QR
 * modal preview, PDF download links + calendar add. Data comes from
 * BileteOnlineAPI.customer.getAllTickets(); the JS exists already
 * (assets/js/pages/user-tickets.js) but renders a different shape,
 * so this page bootstraps its own Alpine state and hits the API
 * directly to match the v2 visual structure.
 */

require_once __DIR__ . '/../includes/config.php';

$pageTitleRaw    = 'Biletele mele — ' . SITE_NAME;
$pageDescription = 'Biletele tale pe bilete.online: QR, PDF, beneficiari, calendar, status și acțiuni de retur sau protecție bilet.';
$canonicalUrl    = SITE_URL . '/cont/bilete';
$noindex         = true;
$currentPage     = 'cont';
$cssBundle       = 'auth';

include __DIR__ . '/../includes/head.php';
include __DIR__ . '/../includes/header.php';
?>

<div class="max-w-[1500px] mx-auto px-4 sm:px-6 py-6 lg:py-8">
    <div class="grid lg:grid-cols-[280px_minmax(0,1fr)] gap-6 lg:gap-8 items-start">

        <?php $currentClientPage = 'tickets'; include __DIR__ . '/../includes/client-sidebar-v2.php'; ?>

        <main class="min-w-0" x-data="clientTicketsPage()" x-init="init()">

            <!-- HERO -->
            <section class="rounded-[2rem] border-2 border-ink bg-ink text-paper p-6 sm:p-8 shadow-deep">
                <div class="grid xl:grid-cols-[1fr_360px] gap-8 items-center">
                    <div>
                        <p class="stamp inline-flex px-3 py-1 text-xs font-mono tracking-[.18em] text-ochre">BILETE CLIENT</p>
                        <h1 class="mt-5 font-display text-5xl sm:text-6xl lg:text-7xl font-bold leading-[.85]">Biletele mele</h1>
                        <p class="mt-5 max-w-3xl text-paper/65 text-lg leading-relaxed">
                            Toate biletele tale într-un singur loc: QR, PDF, beneficiari, calendar, status, acces și opțiuni de retur.
                        </p>
                        <div class="mt-7 flex flex-wrap gap-3">
                            <a href="#bilete" class="rounded-full bg-vermilion text-paper px-6 py-4 font-bold hover:bg-vermilion-d transition">Vezi biletele</a>
                            <a href="/recuperare-comanda" class="rounded-full border-2 border-paper/50 px-6 py-4 font-bold hover:bg-paper hover:text-ink transition">Recuperează comandă</a>
                        </div>
                    </div>
                    <div class="ticket relative bg-paper text-ink rounded-[2rem] border-2 border-paper/30 p-6 rotate-[-2deg]" style="--perf:100%">
                        <p class="font-mono text-xs tracking-[.18em] text-ink-soft">NEXT QR</p>
                        <h2 class="mt-2 font-display text-4xl font-bold leading-none" x-text="(nextTicket && nextTicket.title) || 'În curând'">—</h2>
                        <p class="mt-1 text-ink-soft" x-text="(nextTicket && nextTicket.subline) || 'Nu ai bilete viitoare'"></p>
                        <div class="mt-5 flex items-end justify-between gap-4">
                            <div>
                                <p class="font-display text-5xl font-bold" x-text="upcomingCount + 'x'">0x</p>
                                <p class="text-sm text-ink-soft">bilete viitoare</p>
                            </div>
                            <button @click="nextTicket && showQR(nextTicket)" :disabled="!nextTicket" class="relative w-32 h-32 rounded-2xl bg-ink text-paper grid place-items-center overflow-hidden disabled:opacity-40">
                                <span class="font-mono text-xs text-center">QR<br>OPEN</span>
                                <span class="scanline absolute left-3 right-3 top-0 h-0.5 bg-vermilion shadow-[0_0_12px_rgba(232,69,39,.85)]"></span>
                            </button>
                        </div>
                    </div>
                </div>
            </section>

            <!-- STATS -->
            <section class="mt-6 grid sm:grid-cols-2 xl:grid-cols-4 gap-4">
                <article class="rounded-[2rem] border-2 border-ink bg-paper p-5 shadow-ticket">
                    <p class="font-mono text-xs tracking-[.18em] text-ink-soft">BILETE VIITOARE</p>
                    <p class="mt-3 font-display text-6xl font-bold" x-text="counts.upcoming">0</p>
                    <p class="mt-1 text-ink-soft" x-text="'în ' + counts.upcomingActivities + ' activități'">în 0 activități</p>
                </article>
                <article class="rounded-[2rem] border-2 border-ink bg-mint p-5 shadow-ticket">
                    <p class="font-mono text-xs tracking-[.18em] text-forest">VALIDE</p>
                    <p class="mt-3 font-display text-6xl font-bold" x-text="counts.valid">0</p>
                    <p class="mt-1 text-ink-soft">gata de scanare</p>
                </article>
                <article class="rounded-[2rem] border-2 border-ink bg-paper p-5 shadow-ticket">
                    <p class="font-mono text-xs tracking-[.18em] text-ink-soft">SCANATE</p>
                    <p class="mt-3 font-display text-6xl font-bold" x-text="counts.checked_in">0</p>
                    <p class="mt-1 text-ink-soft">istoric complet</p>
                </article>
                <article class="rounded-[2rem] border-2 border-ink bg-rose p-5 shadow-ticket">
                    <p class="font-mono text-xs tracking-[.18em] text-vermilion">ACȚIUNI</p>
                    <p class="mt-3 font-display text-6xl font-bold" x-text="counts.action">0</p>
                    <p class="mt-1 text-ink-soft">nume de completat</p>
                </article>
            </section>

            <!-- FILTERS -->
            <section class="mt-6 rounded-[2rem] border-2 border-ink bg-paper p-5 sm:p-6 shadow-ticket">
                <div class="grid xl:grid-cols-[1.3fr_.7fr_.7fr_.7fr_auto] gap-3 items-end">
                    <label>
                        <span class="block mb-1.5 text-sm font-bold">Caută bilet</span>
                        <input class="field" x-model="search" placeholder="Activitate, oraș, beneficiar, cod bilet...">
                    </label>
                    <label>
                        <span class="block mb-1.5 text-sm font-bold">Status</span>
                        <select class="field" x-model="status">
                            <option value="all">Toate</option>
                            <option value="upcoming">Viitoare</option>
                            <option value="valid">Valide</option>
                            <option value="used">Scanate</option>
                            <option value="expired">Expirate</option>
                            <option value="action">Necesită acțiune</option>
                        </select>
                    </label>
                    <label>
                        <span class="block mb-1.5 text-sm font-bold">Oraș</span>
                        <select class="field" x-model="city">
                            <option value="all">Toate orașele</option>
                            <template x-for="c in availableCities" :key="c">
                                <option :value="c" x-text="c"></option>
                            </template>
                        </select>
                    </label>
                    <label>
                        <span class="block mb-1.5 text-sm font-bold">Sortare</span>
                        <select class="field" x-model="sort">
                            <option value="soon">Cele mai apropiate</option>
                            <option value="newest">Cele mai noi</option>
                            <option value="activity">Activitate A-Z</option>
                        </select>
                    </label>
                    <button @click="resetFilters()" class="rounded-full border-2 border-ink px-5 py-3.5 font-bold hover:bg-ink hover:text-paper transition">Reset</button>
                </div>

                <!-- Quick filter pills -->
                <div class="mt-5 flex flex-wrap gap-2">
                    <button @click="status='upcoming'" :class="status==='upcoming'?'bg-ink text-paper':'bg-paper-2'" class="rounded-full px-4 py-2 font-bold border border-ink/10">Viitoare</button>
                    <button @click="status='valid'" :class="status==='valid'?'bg-forest text-paper':'bg-paper-2'" class="rounded-full px-4 py-2 font-bold border border-ink/10">Valide</button>
                    <button @click="status='action'" :class="status==='action'?'bg-rose text-vermilion':'bg-paper-2'" class="rounded-full px-4 py-2 font-bold border border-ink/10">Necesită acțiune</button>
                    <button @click="status='used'" :class="status==='used'?'bg-paper-3 text-ink':'bg-paper-2'" class="rounded-full px-4 py-2 font-bold border border-ink/10">Scanate</button>
                </div>
            </section>

            <!-- TICKETS GRID -->
            <section id="bilete" class="mt-6">
                <div class="flex flex-col sm:flex-row sm:items-end sm:justify-between gap-3 mb-4">
                    <div>
                        <p class="font-mono text-xs tracking-[.18em] text-ink-soft">REZULTATE</p>
                        <h2 class="font-display text-4xl sm:text-5xl font-bold leading-none" x-text="filteredTickets().length + ' bilete'"></h2>
                    </div>
                    <div class="flex flex-wrap gap-2">
                        <button @click="downloadAllPdf()" :disabled="batchBusy || filteredTickets().length === 0" class="rounded-full bg-paper border-2 border-ink px-5 py-3 font-bold hover:bg-ink hover:text-paper transition disabled:opacity-40">
                            <span x-show="!batchBusy">Descarcă toate PDF</span>
                            <span x-show="batchBusy" x-cloak>Se descarcă…</span>
                        </button>
                        <button @click="addAllToCalendar()" :disabled="filteredTickets().length === 0" class="rounded-full bg-vermilion text-paper px-5 py-3 font-bold hover:bg-vermilion-d transition disabled:opacity-40">Adaugă toate în calendar</button>
                    </div>
                </div>

                <div x-show="loading" class="grid xl:grid-cols-2 gap-5">
                    <div class="h-56 rounded-[2rem] bg-paper-2/60 animate-pulse"></div>
                    <div class="h-56 rounded-[2rem] bg-paper-2/60 animate-pulse"></div>
                </div>

                <div x-show="!loading && filteredTickets().length > 0" class="grid xl:grid-cols-2 gap-5">
                    <template x-for="ticket in filteredTickets()" :key="ticket.code || ticket.id">
                        <article class="ticket rounded-[2rem] border-2 border-ink bg-paper shadow-ticket overflow-hidden" style="--perf:74%">
                            <div class="perf hidden sm:block"></div>
                            <div class="grid sm:grid-cols-[1fr_180px]">
                                <div class="p-5 sm:p-6 min-w-0">
                                    <div class="flex flex-wrap gap-2">
                                        <span class="rounded-full px-3 py-1 text-xs font-bold" :class="badgeClass(ticket.status)" x-text="statusLabel(ticket.status)"></span>
                                        <span x-show="ticket.event_city || (ticket.event && ticket.event.city)" class="rounded-full bg-paper-2 border border-ink/10 px-3 py-1 text-xs font-bold" x-text="ticket.event_city || (ticket.event && ticket.event.city) || ''"></span>
                                        <span x-show="hasProtection(ticket)" class="rounded-full bg-mint text-forest px-3 py-1 text-xs font-bold">protecție bilet</span>
                                    </div>
                                    <h3 class="mt-4 font-display text-3xl font-bold leading-none" x-text="ticket.event_title || (ticket.event && (ticket.event.title || ticket.event.name)) || 'Bilet'"></h3>
                                    <p class="mt-2 text-ink-soft" x-text="ticket.venue_name || (ticket.event && ticket.event.venue_name) || ''"></p>

                                    <div class="mt-5 grid sm:grid-cols-2 gap-3 text-sm">
                                        <div class="rounded-2xl bg-paper-2 border border-ink/10 p-4">
                                            <p class="font-mono text-[10px] tracking-[.16em] text-ink-soft">DATA</p>
                                            <p class="font-bold" x-text="formatDate(ticket.event_date || (ticket.event && (ticket.event.date || ticket.event.start_date)))"></p>
                                        </div>
                                        <div class="rounded-2xl bg-paper-2 border border-ink/10 p-4">
                                            <p class="font-mono text-[10px] tracking-[.16em] text-ink-soft">BENEFICIAR</p>
                                            <p class="font-bold" x-text="ticket.attendee_name || 'necompletat'"></p>
                                        </div>
                                        <div class="rounded-2xl bg-paper-2 border border-ink/10 p-4">
                                            <p class="font-mono text-[10px] tracking-[.16em] text-ink-soft">TIP BILET</p>
                                            <p class="font-bold" x-text="(ticket.ticket_type && (ticket.ticket_type.name || ticket.ticket_type)) || ticket.type || 'Standard'"></p>
                                        </div>
                                        <div class="rounded-2xl bg-paper-2 border border-ink/10 p-4">
                                            <p class="font-mono text-[10px] tracking-[.16em] text-ink-soft">COD</p>
                                            <p class="font-bold font-mono text-xs" x-text="ticket.code || ticket.barcode || ''"></p>
                                        </div>
                                    </div>

                                    <div x-show="!ticket.attendee_name && ticket.status !== 'checked_in' && ticket.status !== 'cancelled'" class="mt-4 rounded-2xl bg-rose border border-vermilion/20 p-4">
                                        <p class="font-bold text-vermilion">Adaugă beneficiar</p>
                                        <p class="mt-1 text-sm text-ink-soft">Completează numele înainte de eveniment pentru a evita probleme la intrare.</p>
                                    </div>

                                    <div class="mt-5 flex flex-wrap gap-2">
                                        <a :href="ticketDetailUrl(ticket)" class="rounded-full bg-ink text-paper px-4 py-2 text-sm font-bold hover:bg-vermilion transition">Deschide</a>
                                        <a :href="pdfUrl(ticket)" class="rounded-full bg-vermilion text-paper px-4 py-2 text-sm font-bold hover:bg-vermilion-d transition">PDF</a>
                                        <a :href="calendarUrl(ticket)" class="rounded-full border border-ink/20 px-4 py-2 text-sm font-bold hover:bg-ink hover:text-paper transition">Calendar</a>
                                        <button @click="showQR(ticket)" class="rounded-full border border-ink/20 px-4 py-2 text-sm font-bold hover:bg-ink hover:text-paper transition">QR mare</button>
                                        <a x-show="canEditBeneficiary(ticket)" :href="ticketEditUrl(ticket)" class="rounded-full border border-ink/20 px-4 py-2 text-sm font-bold hover:bg-ink hover:text-paper transition">Editează nume</a>
                                        <a x-show="canRefund(ticket)" :href="'/cont/cerere-rambursare?ticket=' + (ticket.id || '')" class="rounded-full border border-vermilion/30 bg-rose text-vermilion px-4 py-2 text-sm font-bold hover:bg-vermilion hover:text-paper transition">Retur</a>
                                    </div>
                                </div>

                                <div class="relative bg-ink text-paper p-5 sm:p-6 grid place-items-center">
                                    <div class="text-center">
                                        <div class="relative w-32 h-32 rounded-2xl bg-paper text-ink grid place-items-center overflow-hidden mx-auto">
                                            <span class="font-mono text-xs text-center" x-text="qrPreview(ticket)"></span>
                                            <span x-show="ticket.status !== 'checked_in' && ticket.status !== 'cancelled'" class="scanline absolute left-3 right-3 top-0 h-0.5 bg-vermilion shadow-[0_0_12px_rgba(232,69,39,.85)]"></span>
                                        </div>
                                        <p class="mt-4 font-mono text-[10px] tracking-[.18em] text-paper/45">COMANDĂ</p>
                                        <a x-show="ticket.order_number" :href="'/cont/comenzi'" class="font-bold text-ochre underline-wobble" x-text="'#' + (ticket.order_number || '')"></a>
                                    </div>
                                </div>
                            </div>
                        </article>
                    </template>
                </div>

                <div x-show="!loading && filteredTickets().length === 0" class="rounded-[2rem] border-2 border-dashed border-ink/20 bg-paper-2/60 p-10 text-center">
                    <p class="text-5xl">🎟️</p>
                    <p class="mt-4 font-display text-3xl font-bold" x-text="tickets.length === 0 ? 'Nu ai bilete încă' : 'Nicio potrivire'"></p>
                    <p class="mt-2 text-ink-soft" x-text="tickets.length === 0 ? 'Descoperă activități și rezervă online.' : 'Schimbă filtrele sau resetează căutarea.'"></p>
                    <a x-show="tickets.length === 0" href="/categorii" class="mt-5 inline-flex rounded-full bg-vermilion text-paper px-6 py-3 font-bold">Descoperă activități</a>
                    <button x-show="tickets.length > 0" @click="resetFilters()" class="mt-5 rounded-full bg-vermilion text-paper px-6 py-3 font-bold">Resetează</button>
                </div>
            </section>

            <!-- INFO CARDS -->
            <section class="mt-6 grid md:grid-cols-3 gap-5">
                <article class="rounded-[2rem] border-2 border-ink bg-paper p-6 shadow-ticket">
                    <p class="font-mono text-xs tracking-[.18em] text-ink-soft">ACCES</p>
                    <h2 class="mt-2 font-display text-4xl font-bold leading-none">Cum folosești QR-ul?</h2>
                    <p class="mt-3 text-ink-soft">Arată QR-ul de pe telefon, cu luminozitate ridicată. Fiecare bilet are cod unic. Dacă nu se citește din prima, mai încearcă — operatorul are scaner manual ca rezervă.</p>
                </article>
                <article class="rounded-[2rem] border-2 border-ink bg-mint p-6 shadow-ticket">
                    <p class="font-mono text-xs tracking-[.18em] text-forest">BENEFICIARI</p>
                    <h2 class="mt-2 font-display text-4xl font-bold leading-none">Nume diferite?</h2>
                    <p class="mt-3 text-ink-soft">Pentru grupuri, poți avea beneficiari diferiți pe fiecare bilet, dacă activitatea o cere. Editează numele înainte de eveniment ca să eviți probleme la intrare.</p>
                </article>
                <article class="rounded-[2rem] border-2 border-ink bg-rose p-6 shadow-ticket">
                    <p class="font-mono text-xs tracking-[.18em] text-vermilion">RETUR</p>
                    <h2 class="mt-2 font-display text-4xl font-bold leading-none">Nu mai poți ajunge?</h2>
                    <p class="mt-3 text-ink-soft">Verifică dacă biletul este eligibil pentru retur. Dacă ai protecție bilet activă (cumpărată la checkout), poți recupera banii fără justificare.</p>
                </article>
            </section>

            <!-- QR MODAL -->
            <div x-show="selectedTicket" x-cloak class="fixed inset-0 z-[100] bg-ink/70 backdrop-blur-sm p-4 grid place-items-center" @click.self="selectedTicket=null">
                <div class="max-w-lg w-full rounded-[2rem] border-2 border-ink bg-paper p-6 shadow-deep">
                    <div class="flex items-start justify-between gap-4">
                        <div>
                            <p class="font-mono text-xs tracking-[.18em] text-ink-soft">QR BILET</p>
                            <h2 class="mt-2 font-display text-4xl font-bold leading-none" x-text="(selectedTicket && (selectedTicket.event_title || (selectedTicket.event && (selectedTicket.event.title || selectedTicket.event.name)))) || 'Bilet'"></h2>
                            <p class="mt-1 text-ink-soft" x-text="(selectedTicket && selectedTicket.attendee_name) || 'Beneficiar necompletat'"></p>
                        </div>
                        <button @click="selectedTicket=null" class="grid place-items-center w-10 h-10 rounded-full bg-ink text-paper font-bold">×</button>
                    </div>
                    <div class="mt-6 rounded-[2rem] bg-ink text-paper p-8 grid place-items-center">
                        <div class="relative">
                            <div id="qr-modal-canvas" class="w-64 h-64 bg-paper rounded-3xl grid place-items-center overflow-hidden"></div>
                            <span class="scanline absolute left-5 right-5 top-0 h-1 bg-vermilion shadow-[0_0_18px_rgba(232,69,39,.9)]"></span>
                        </div>
                    </div>
                    <p class="mt-4 text-center font-mono text-xs text-ink-soft" x-text="selectedTicket && selectedTicket.code"></p>
                    <div class="mt-5 flex flex-wrap gap-2 justify-center">
                        <a :href="selectedTicket && pdfUrl(selectedTicket)" class="rounded-full bg-vermilion text-paper px-5 py-3 font-bold hover:bg-vermilion-d transition">Descarcă PDF</a>
                        <a :href="selectedTicket && calendarUrl(selectedTicket)" class="rounded-full border-2 border-ink px-5 py-3 font-bold hover:bg-ink hover:text-paper transition">Adaugă în calendar</a>
                    </div>
                </div>
            </div>

            <!-- AUTH GUARD -->
            <div x-show="isAuth === false" x-cloak class="mt-8 rounded-[2rem] border-2 border-vermilion bg-rose p-8 text-center">
                <p class="font-display text-3xl font-bold text-vermilion">Trebuie să fii autentificat</p>
                <a href="/autentificare?redirect=/cont/bilete" class="mt-5 inline-flex rounded-full bg-vermilion text-paper px-6 py-3 font-bold">Intră în cont</a>
            </div>
        </main>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/qrcode@1.5.3/build/qrcode.min.js"></script>
<script>
function clientTicketsPage() {
    return {
        loading: true,
        isAuth: null,
        tickets: [],
        search: '',
        status: 'upcoming',
        city: 'all',
        sort: 'soon',
        selectedTicket: null,
        batchBusy: false,
        counts: { upcoming: 0, upcomingActivities: 0, valid: 0, checked_in: 0, action: 0 },

        init() {
            try { if (window.BileteOnlineAuth && BileteOnlineAuth.getToken && BileteOnlineAuth.getToken()) this.isAuth = true; } catch (e) {}
            if (this.isAuth === false) { this.loading = false; return; }
            this.load();
        },

        async load() {
            try {
                const r = await BileteOnlineAPI.customer.getAllTickets('all', { per_page: 200 });
                const list = (r && r.data && (r.data.tickets || r.data.items)) || (r && r.data) || [];
                this.tickets = Array.isArray(list) ? list : [];
                this.computeCounts();
            } catch (e) {}
            this.loading = false;
        },

        computeCounts() {
            this.counts = { upcoming: 0, upcomingActivities: 0, valid: 0, checked_in: 0, action: 0 };
            const upcomingEventIds = new Set();
            this.tickets.forEach(t => {
                const isUpcoming = this.isUpcoming(t);
                if (isUpcoming && t.status !== 'cancelled') {
                    this.counts.upcoming++;
                    const eventKey = t.event_id || (t.event && t.event.id) || t.event_slug || (t.event && t.event.slug) || t.event_title;
                    if (eventKey) upcomingEventIds.add(eventKey);
                }
                if (t.status === 'valid' || t.status === 'paid' || t.status === 'confirmed' || t.status === 'pending') this.counts.valid++;
                if (t.status === 'checked_in') this.counts.checked_in++;
                if (isUpcoming && ! t.attendee_name) this.counts.action++;
            });
            this.counts.upcomingActivities = upcomingEventIds.size;
        },

        get availableCities() {
            const set = new Set();
            this.tickets.forEach(t => {
                const c = t.event_city || (t.event && t.event.city);
                if (c) set.add(c);
            });
            return Array.from(set).sort((a, b) => a.localeCompare(b, 'ro'));
        },

        isUpcoming(t) {
            const d = t.event_date || (t.event && (t.event.date || t.event.start_date));
            if (! d) return false;
            try { return new Date(d) >= new Date(new Date().toDateString()); } catch (e) { return false; }
        },

        get nextTicket() {
            const upcoming = this.tickets
                .filter(t => this.isUpcoming(t) && t.status !== 'cancelled')
                .sort((a, b) => {
                    const ad = new Date(a.event_date || (a.event && (a.event.date || a.event.start_date)) || 0);
                    const bd = new Date(b.event_date || (b.event && (b.event.date || b.event.start_date)) || 0);
                    return ad - bd;
                });
            if (upcoming.length === 0) return null;
            const t = upcoming[0];
            return {
                ...t,
                title: t.event_title || (t.event && (t.event.title || t.event.name)) || 'Bilet',
                subline: this.formatDate(t.event_date || (t.event && (t.event.date || t.event.start_date))) + (t.event_city || (t.event && t.event.city) ? ' · ' + (t.event_city || t.event.city) : ''),
            };
        },

        get upcomingCount() { return this.counts.upcoming; },

        filteredTickets() {
            const q = (this.search || '').toLowerCase().trim();
            let list = this.tickets.filter(t => {
                if (this.status === 'upcoming') return this.isUpcoming(t) && t.status !== 'cancelled';
                if (this.status === 'valid')    return ['valid','paid','confirmed','pending'].includes(t.status);
                if (this.status === 'used')     return t.status === 'checked_in';
                if (this.status === 'expired')  return ! this.isUpcoming(t) && t.status !== 'checked_in';
                if (this.status === 'action')   return this.isUpcoming(t) && ! t.attendee_name;
                return true;
            });
            if (this.city !== 'all') {
                list = list.filter(t => (t.event_city || (t.event && t.event.city) || '') === this.city);
            }
            if (q) list = list.filter(t => JSON.stringify(t).toLowerCase().includes(q));
            if (this.sort === 'activity') list.sort((a, b) => (a.event_title || '').localeCompare(b.event_title || ''));
            else if (this.sort === 'newest') list.sort((a, b) => new Date(b.created_at || 0) - new Date(a.created_at || 0));
            else /* soon */ list.sort((a, b) => {
                const ad = new Date(a.event_date || (a.event && (a.event.date || a.event.start_date)) || 0);
                const bd = new Date(b.event_date || (b.event && (b.event.date || b.event.start_date)) || 0);
                return ad - bd;
            });
            return list;
        },

        resetFilters() { this.search = ''; this.status = 'upcoming'; this.city = 'all'; this.sort = 'soon'; },

        canEditBeneficiary(t) {
            return this.isUpcoming(t) && t.status !== 'cancelled' && t.status !== 'checked_in';
        },
        hasProtection(t) {
            return !!(t.has_protection || t.protection || t.protected || (t.options && t.options.protection));
        },
        ticketDetailUrl(t) {
            const orderId = t.order_number || t.order_id || (t.order && (t.order.number || t.order.id));
            if (orderId) return '/cont/comenzile-mele#' + orderId;
            return '/cont/biletele-mele#t-' + (t.id || t.code || '');
        },
        ticketEditUrl(t) {
            return '/cont/biletele-mele/editeaza?ticket=' + encodeURIComponent(t.id || t.code || '');
        },

        async downloadAllPdf() {
            const list = this.filteredTickets();
            if (! list.length) return;
            this.batchBusy = true;
            try {
                for (let i = 0; i < list.length; i++) {
                    const a = document.createElement('a');
                    a.href = this.pdfUrl(list[i]);
                    a.target = '_blank';
                    a.rel = 'noopener';
                    document.body.appendChild(a);
                    a.click();
                    a.remove();
                    await new Promise(r => setTimeout(r, 350));
                }
            } catch (e) {}
            this.batchBusy = false;
        },

        addAllToCalendar() {
            const list = this.filteredTickets();
            if (! list.length) return;
            list.forEach((t, i) => {
                setTimeout(() => {
                    const a = document.createElement('a');
                    a.href = this.calendarUrl(t);
                    a.target = '_blank';
                    a.rel = 'noopener';
                    document.body.appendChild(a);
                    a.click();
                    a.remove();
                }, i * 250);
            });
        },

        showQR(ticket) {
            this.selectedTicket = ticket;
            this.$nextTick(() => {
                const wrap = document.getElementById('qr-modal-canvas');
                if (! wrap) return;
                wrap.innerHTML = '';
                const code = ticket.barcode || ticket.code || '';
                if (typeof QRCode !== 'undefined' && code) {
                    const canvas = document.createElement('canvas');
                    QRCode.toCanvas(canvas, code, { width: 240, margin: 1 }, () => wrap.appendChild(canvas));
                } else {
                    wrap.textContent = code || 'QR indisponibil';
                }
            });
        },

        qrPreview(t) {
            if (t.status === 'checked_in') return 'SCANAT';
            if (t.status === 'cancelled') return 'ANULAT';
            return (t.code || t.barcode || '').slice(-6).toUpperCase();
        },

        statusLabel(s) {
            const map = { valid: 'valid', paid: 'plătit', confirmed: 'confirmat', pending: 'în așteptare', checked_in: 'scanat', cancelled: 'anulat', refunded: 'rambursat' };
            return map[s] || s || '—';
        },
        badgeClass(s) {
            const upcoming = ['valid','paid','confirmed','pending'];
            if (s === 'checked_in') return 'bg-paper-2 text-ink-soft';
            if (s === 'cancelled' || s === 'refunded') return 'bg-rose text-vermilion';
            if (upcoming.includes(s)) return 'bg-mint text-forest';
            return 'bg-paper-2';
        },
        canRefund(t) {
            return t.status !== 'checked_in' && t.status !== 'cancelled' && t.status !== 'refunded' && this.isUpcoming(t);
        },

        pdfUrl(t)      { return '/api/proxy.php?action=ticket.download-pdf&id=' + encodeURIComponent(t.id || t.code || ''); },
        calendarUrl(t) { return '/api/proxy.php?action=ticket.ics&id=' + encodeURIComponent(t.id || t.code || ''); },

        formatDate(d) {
            if (! d) return '—';
            try {
                const dt = new Date(d);
                if (isNaN(dt)) return d;
                const months = ['ian','feb','mar','apr','mai','iun','iul','aug','sep','oct','noi','dec'];
                return dt.getDate() + ' ' + months[dt.getMonth()] + ' ' + dt.getFullYear() + ' · ' + String(dt.getHours()).padStart(2,'0') + ':' + String(dt.getMinutes()).padStart(2,'0');
            } catch (e) { return d; }
        },
    };
}
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>
