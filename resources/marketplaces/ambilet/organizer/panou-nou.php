<?php
require_once dirname(__DIR__) . '/includes/config.php';
$pageTitle = 'Panou Organizator';
$bodyClass = 'min-h-screen flex bg-slate-100';
$currentPage = 'dashboard';
$headExtra = '<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>';
$cssBundle = 'organizer';
require_once dirname(__DIR__) . '/includes/head.php';
require_once dirname(__DIR__) . '/includes/organizer-sidebar.php';
?>

<div class="flex flex-col flex-1 min-h-screen lg:ml-0">
    <?php require_once dirname(__DIR__) . '/includes/organizer-topbar.php'; ?>

    <main class="flex-1 p-4 lg:p-8">
        <div class="w-full mx-auto space-y-6 lg:space-y-8" style="max-width:1500px;">

            <!-- ===== HERO ===== -->
            <section class="relative overflow-hidden shadow-lg rounded-3xl"
                     style="background:linear-gradient(135deg,#0f172a 0%,#1e293b 55%,#334155 100%);">
                <div class="absolute inset-0 opacity-40" style="background:radial-gradient(1200px 300px at 85% -20%, rgba(196,30,58,.55), transparent 60%);"></div>
                <div class="relative flex flex-col gap-6 p-6 lg:flex-row lg:items-center lg:justify-between lg:p-9">
                    <div>
                        <span class="inline-flex items-center gap-2 px-3 py-1 mb-3 text-[11px] font-bold tracking-wide text-white uppercase rounded-full bg-white/10 backdrop-blur">
                            <span class="relative flex w-2 h-2">
                                <span class="absolute inline-flex w-full h-full rounded-full opacity-75 animate-ping bg-emerald-400"></span>
                                <span class="relative inline-flex w-2 h-2 rounded-full bg-emerald-500"></span>
                            </span>
                            Panou organizator
                        </span>
                        <h1 class="text-2xl font-extrabold text-white lg:text-3xl" id="pn-greeting">Bine ai revenit 👋</h1>
                        <p class="mt-1 text-sm text-slate-300 lg:text-base">Iată cum performează evenimentele tale în derulare.</p>
                    </div>
                    <div class="flex flex-wrap gap-3">
                        <a href="/organizator/events?action=create" class="inline-flex items-center gap-2 px-5 py-3 text-sm font-bold text-white transition-all shadow-lg rounded-xl bg-primary hover:opacity-90">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                            Eveniment nou
                        </a>
                        <a href="/organizator/events" class="inline-flex items-center gap-2 px-5 py-3 text-sm font-bold text-white transition-all rounded-xl bg-white/10 backdrop-blur hover:bg-white/20">
                            Toate evenimentele
                        </a>
                    </div>
                </div>
            </section>

            <!-- ===== KPI TILES (compact, horizontal) ===== -->
            <section class="grid grid-cols-2 gap-3 lg:grid-cols-4">
                <div class="flex items-center gap-3 p-3.5 bg-white border shadow-sm rounded-xl border-border">
                    <div class="flex items-center justify-center w-10 h-10 rounded-lg shrink-0 bg-emerald-500/10">
                        <svg class="w-5 h-5 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8V7m0 10v1m0-9a9 9 0 110 0z"/></svg>
                    </div>
                    <div class="min-w-0">
                        <p class="text-lg font-extrabold leading-none text-secondary lg:text-xl tabular-nums" id="kpi-revenue">—</p>
                        <p class="mt-1 text-xs truncate text-muted">Vânzări totale</p>
                    </div>
                </div>
                <div class="flex items-center gap-3 p-3.5 bg-white border shadow-sm rounded-xl border-border">
                    <div class="flex items-center justify-center w-10 h-10 rounded-lg shrink-0 bg-indigo-500/10">
                        <svg class="w-5 h-5 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 5v2m0 4v2m0 4v2M5 5a2 2 0 00-2 2v3a2 2 0 110 4v3a2 2 0 002 2h14a2 2 0 002-2v-3a2 2 0 110-4V7a2 2 0 00-2-2H5z"/></svg>
                    </div>
                    <div class="min-w-0">
                        <p class="text-lg font-extrabold leading-none text-secondary lg:text-xl tabular-nums" id="kpi-tickets">—</p>
                        <p class="mt-1 text-xs truncate text-muted">Bilete vândute</p>
                    </div>
                </div>
                <div class="flex items-center gap-3 p-3.5 bg-white border shadow-sm rounded-xl border-border">
                    <div class="flex items-center justify-center w-10 h-10 rounded-lg shrink-0 bg-cyan-500/10">
                        <svg class="w-5 h-5 text-cyan-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
                    </div>
                    <div class="min-w-0">
                        <p class="text-lg font-extrabold leading-none text-secondary lg:text-xl tabular-nums" id="kpi-views">—</p>
                        <p class="mt-1 text-xs truncate text-muted">Vizualizări</p>
                    </div>
                </div>
                <div class="flex items-center gap-3 p-3.5 bg-white border shadow-sm rounded-xl border-border">
                    <div class="flex items-center justify-center w-10 h-10 rounded-lg shrink-0 bg-primary/10">
                        <svg class="w-5 h-5 text-primary" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                    </div>
                    <div class="min-w-0">
                        <p class="text-lg font-extrabold leading-none text-secondary lg:text-xl tabular-nums" id="kpi-events">—</p>
                        <p class="mt-1 text-xs truncate text-muted">Evenimente în derulare</p>
                    </div>
                </div>
            </section>

            <!-- ===== PERFORMANCE CHART ===== -->
            <section class="p-5 bg-white border shadow-sm rounded-2xl border-border lg:p-6">
                <div class="flex flex-col gap-4 mb-5 lg:flex-row lg:items-center lg:justify-between">
                    <div>
                        <h2 class="text-lg font-bold text-secondary">Performanță vânzări</h2>
                        <p class="text-sm text-muted">Toate evenimentele în derulare, cumulat</p>
                    </div>
                    <div class="flex flex-wrap items-center gap-2" id="pn-period">
                        <button data-days="7"  class="pn-period-btn px-3 py-1.5 text-xs font-semibold rounded-lg text-muted hover:bg-surface transition-colors">7 zile</button>
                        <button data-days="30" class="pn-period-btn px-3 py-1.5 text-xs font-semibold rounded-lg bg-primary/10 text-primary transition-colors">30 zile</button>
                        <button data-days="90" class="pn-period-btn px-3 py-1.5 text-xs font-semibold rounded-lg text-muted hover:bg-surface transition-colors">90 zile</button>
                    </div>
                </div>
                <!-- metric toggles -->
                <div class="flex flex-wrap gap-2 mb-4" id="pn-metrics">
                    <button data-metric="revenue" class="pn-metric-btn active inline-flex items-center gap-2 px-3 py-1.5 text-xs font-semibold rounded-full border transition-colors">
                        <span class="w-2.5 h-2.5 rounded-full" style="background:#10b981"></span> Venituri
                    </button>
                    <button data-metric="tickets" class="pn-metric-btn active inline-flex items-center gap-2 px-3 py-1.5 text-xs font-semibold rounded-full border transition-colors">
                        <span class="w-2.5 h-2.5 rounded-full" style="background:#3b82f6"></span> Bilete
                    </button>
                    <button data-metric="views" class="pn-metric-btn active inline-flex items-center gap-2 px-3 py-1.5 text-xs font-semibold rounded-full border transition-colors">
                        <span class="w-2.5 h-2.5 rounded-full" style="background:#06b6d4"></span> Vizualizări
                    </button>
                </div>
                <div class="relative" style="height:320px;">
                    <canvas id="pnChart"></canvas>
                    <div id="pnChartEmpty" class="absolute inset-0 flex-col items-center justify-center hidden text-sm text-muted">
                        <svg class="w-10 h-10 mb-2 text-muted/40" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/></svg>
                        Nu există date pentru perioada selectată
                    </div>
                </div>
            </section>

            <!-- ===== EVENTS IN PROGRESS ===== -->
            <section>
                <div class="flex items-center justify-between mb-4">
                    <h2 class="text-lg font-bold text-secondary">Evenimente în derulare</h2>
                    <a href="/organizator/events" class="text-sm font-semibold text-primary hover:underline">Vezi toate &rarr;</a>
                </div>
                <div id="pn-events-grid" class="grid grid-cols-1 gap-5 md:grid-cols-2 lg:grid-cols-4">
                    <!-- skeletons -->
                    <?php for ($i = 0; $i < 3; $i++): ?>
                    <div class="overflow-hidden bg-white border rounded-2xl border-border">
                        <div class="h-40 skeleton"></div>
                        <div class="p-4 space-y-3">
                            <div class="w-2/3 h-4 skeleton rounded"></div>
                            <div class="w-1/2 h-3 skeleton rounded"></div>
                            <div class="h-16 skeleton rounded-xl"></div>
                        </div>
                    </div>
                    <?php endfor; ?>
                </div>
                <div id="pn-events-empty" class="hidden py-16 text-center bg-white border rounded-2xl border-border">
                    <svg class="w-12 h-12 mx-auto mb-3 text-muted/30" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                    <p class="mb-3 text-muted">Nu ai evenimente în derulare momentan.</p>
                    <a href="/organizator/events?action=create" class="inline-flex px-4 py-2 text-sm font-semibold text-white rounded-xl bg-primary">Creează un eveniment</a>
                </div>
            </section>

            <!-- ===== MOBILE APPS PROMO ===== -->
            <section class="relative overflow-hidden shadow-lg rounded-3xl"
                     style="background:linear-gradient(135deg,#111827 0%,#1f2937 60%,#0b1220 100%);">
                <div class="absolute inset-0 opacity-50" style="background:radial-gradient(900px 260px at 15% 120%, rgba(6,182,212,.35), transparent 60%);"></div>
                <div class="relative grid gap-8 p-6 lg:grid-cols-2 lg:p-9">
                    <div class="flex flex-col justify-center">
                        <span class="inline-flex w-max items-center gap-2 px-3 py-1 mb-3 text-[11px] font-bold tracking-wide text-white uppercase rounded-full bg-white/10 backdrop-blur">Aplicații mobile</span>
                        <h2 class="text-2xl font-extrabold text-white lg:text-3xl">Vinde și scanează bilete din palmă</h2>
                        <p class="max-w-md mt-2 text-sm text-slate-300">Instalează aplicația AmBilet pentru Android sau folosește aplicația de scanare direct din browser. Vânzare la intrare, print și check-in rapid — oriunde te afli.</p>
                        <div class="flex flex-wrap gap-3 mt-6">
                            <a href="https://ambilet.ro/android" target="_blank" rel="noopener" class="inline-flex items-center gap-3 px-5 py-3 text-sm font-bold text-white transition-all shadow-lg rounded-xl bg-primary hover:opacity-90">
                                <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 24 24"><path d="M17.523 15.342a.998.998 0 01-.998.998.998.998 0 01-.998-.998.998.998 0 01.998-.998.998.998 0 01.998.998m-9.05 0a.998.998 0 01-.997.998.998.998 0 01-.998-.998.998.998 0 01.998-.998.998.998 0 01.997.998m9.405-4.28l1.996-3.459a.416.416 0 00-.152-.567.416.416 0 00-.568.152l-2.02 3.5A12.06 12.06 0 0012 9.5c-1.62 0-3.148.35-4.536.94l-2.02-3.5a.416.416 0 00-.72.415l1.996 3.459C3.29 12.06 1.5 14.72 1.5 17.75h21c0-3.03-1.79-5.69-4.622-6.688"/></svg>
                                <span><span class="block text-[10px] font-normal opacity-80">Descarcă pentru</span>Android</span>
                            </a>
                            <a href="/organizator/scan/panou" class="inline-flex items-center gap-3 px-5 py-3 text-sm font-bold text-white transition-all rounded-xl bg-white/10 backdrop-blur hover:bg-white/20">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v1m6 11h2m-6 0h-2v4m0-11v3m0 0h.01M12 12h4.01M16 20h4M4 12h.01M4 4h4v4H4V4zm12 0h4v4h-4V4zM4 16h4v4H4v-4z"/></svg>
                                <span><span class="block text-[10px] font-normal opacity-80">Deschide în browser</span>Aplicație Scan</span>
                            </a>
                        </div>
                        <p class="mt-4 text-xs text-slate-400">Aplicația nu este pe Google Play — se instalează dedicat organizatorilor.</p>
                    </div>
                    <div class="flex items-center justify-center">
                        <div class="p-5 text-center bg-white shadow-2xl rounded-2xl">
                            <img src="https://api.qrserver.com/v1/create-qr-code/?data=https%3A%2F%2Fambilet.ro%2Fandroid&size=180x180&margin=6" width="180" height="180" alt="QR — descarcă aplicația AmBilet" class="w-40 h-40 mx-auto rounded-lg lg:w-44 lg:h-44" loading="lazy">
                            <p class="mt-3 text-sm font-semibold text-secondary">Scanează pentru a descărca</p>
                            <p class="text-xs text-muted">ambilet.ro/android</p>
                        </div>
                    </div>
                </div>
            </section>

        </div>
    </main>
</div>

<?php
$scriptsExtra = <<<'JS'
<script>
const OrgPanouNou = {
    events: [],
    chart: null,
    chartData: null,
    chartDays: 30,
    metrics: { revenue: true, tickets: true, views: true },

    async init() {
        AmbiletAuth.requireOrganizerAuth();
        const org = AmbiletAuth.getOrganizerData();
        const name = (org && (org.public_name || org.name)) || '';
        const gEl = document.getElementById('pn-greeting');
        if (gEl) gEl.textContent = name ? ('Bună, ' + name + ' 👋') : 'Bine ai revenit 👋';

        this.bindControls();
        await Promise.all([this.loadEvents(), this.loadChart(this.chartDays)]);
    },

    bindControls() {
        document.querySelectorAll('.pn-period-btn').forEach(btn => {
            btn.addEventListener('click', () => {
                document.querySelectorAll('.pn-period-btn').forEach(b => {
                    b.classList.remove('bg-primary/10', 'text-primary');
                    b.classList.add('text-muted', 'hover:bg-surface');
                });
                btn.classList.add('bg-primary/10', 'text-primary');
                btn.classList.remove('text-muted', 'hover:bg-surface');
                this.chartDays = parseInt(btn.dataset.days, 10) || 30;
                this.loadChart(this.chartDays);
            });
        });
        document.querySelectorAll('.pn-metric-btn').forEach(btn => {
            btn.addEventListener('click', () => {
                const m = btn.dataset.metric;
                this.metrics[m] = !this.metrics[m];
                btn.classList.toggle('active');
                this.applyMetricVisibility();
            });
        });
    },

    // ---------- EVENTS ----------
    async loadEvents() {
        try {
            // upcoming=1 uses the Event "upcoming" scope (handles multi-day/range
            // events) + status=published → exactly the "în derulare" set.
            const res = await AmbiletAPI.get('/organizer/events?status=published&upcoming=1&per_page=100&sort=event_date&order=asc');
            const all = res.data || res || [];
            // "În derulare" = active/published, not ended, not cancelled, not draft.
            // Filtered client-side too (belt-and-suspenders) so ended/draft never leak in.
            // Sorted by date, nearest event first.
            this.events = all
                .filter(e => e.status === 'published' && !e.is_past && !e.is_cancelled && !e.is_postponed)
                .sort((a, b) => (new Date(a.starts_at || 0).getTime()) - (new Date(b.starts_at || 0).getTime()));
        } catch (e) {
            console.warn('loadEvents failed', e);
            this.events = [];
        }
        this.renderKpis();
        this.renderEvents();
    },

    renderKpis() {
        const sum = (fn) => this.events.reduce((a, e) => a + (Number(fn(e)) || 0), 0);
        const paidOf = (e) => (e.tickets_paid != null ? e.tickets_paid : (e.tickets_sold || 0));
        document.getElementById('kpi-revenue').textContent = this.money(sum(e => e.revenue));
        document.getElementById('kpi-tickets').textContent = this.num(sum(paidOf));
        document.getElementById('kpi-views').textContent = this.num(sum(e => e.views));
        document.getElementById('kpi-events').textContent = this.num(this.events.length);
    },

    renderEvents() {
        const grid = document.getElementById('pn-events-grid');
        const empty = document.getElementById('pn-events-empty');
        if (!this.events.length) {
            grid.innerHTML = '';
            grid.classList.add('hidden');
            empty.classList.remove('hidden');
            return;
        }
        empty.classList.add('hidden');
        grid.classList.remove('hidden');
        const months = ['Ian','Feb','Mar','Apr','Mai','Iun','Iul','Aug','Sep','Oct','Nov','Dec'];

        grid.innerHTML = this.events.map(e => {
            const d = new Date(e.starts_at || e.event_date);
            const dateOk = !isNaN(d.getTime());
            const dayBadge = dateOk ? (d.getDate() + ' ' + months[d.getMonth()]) : '';
            const venue = [e.venue_name, e.venue_city].filter(Boolean).join(' • ');
            const poster = e.image ? (typeof getStorageUrl === 'function' ? getStorageUrl(e.image) : e.image) : '/assets/images/default-event.png';
            const soldOut = e.is_sold_out;

            return `
            <div class="flex flex-col overflow-hidden transition-all bg-white border shadow-sm group rounded-2xl border-border hover:shadow-lg hover:-translate-y-0.5">
                <div class="relative h-40 overflow-hidden">
                    <img src="${poster}" alt="" class="object-cover w-full h-full transition-transform duration-300 group-hover:scale-105" onerror="this.src='/assets/images/default-event.png'" loading="lazy">
                    <div class="absolute inset-x-0 bottom-0 h-16" style="background:linear-gradient(to top,rgba(0,0,0,.55),transparent)"></div>
                    ${dayBadge ? `<span class="absolute px-2.5 py-1 text-xs font-bold text-white rounded-lg top-3 left-3" style="background:rgba(196,30,58,.92)">${dayBadge}</span>` : ''}
                    <span class="absolute px-2 py-1 text-[10px] font-bold rounded-full top-3 right-3 ${soldOut ? 'bg-gray-800/90 text-white' : 'bg-emerald-500/90 text-white'}">${soldOut ? 'SOLD OUT' : 'Activ'}</span>
                </div>
                <div class="flex flex-col flex-1 p-4">
                    <h3 class="font-bold leading-snug truncate text-secondary" title="${this.esc(e.name)}">${this.esc(e.name)}</h3>
                    <p class="mb-3 text-xs truncate text-muted">${this.esc(venue) || '&nbsp;'}</p>
                    <div class="grid grid-cols-4 gap-1.5 mb-4">
                        <div class="p-2 text-center rounded-xl bg-surface">
                            <p class="text-sm font-bold text-secondary tabular-nums">${this.money(e.revenue || 0)}</p>
                            <p class="text-[10px] text-muted">Vânzări</p>
                        </div>
                        <div class="p-2 text-center rounded-xl bg-surface">
                            <p class="text-sm font-bold text-secondary tabular-nums">${this.num(e.tickets_paid != null ? e.tickets_paid : (e.tickets_sold || 0))}</p>
                            <p class="text-[10px] text-muted">Bilete</p>
                        </div>
                        <div class="p-2 text-center rounded-xl bg-surface">
                            <p class="text-sm font-bold text-secondary tabular-nums">${this.num(e.invitations || 0)}</p>
                            <p class="text-[10px] text-muted">Invitații</p>
                        </div>
                        <div class="p-2 text-center rounded-xl bg-surface">
                            <p class="text-sm font-bold text-secondary tabular-nums">${this.num(e.views || 0)}</p>
                            <p class="text-[10px] text-muted">Vizualizări</p>
                        </div>
                    </div>
                    <div class="grid grid-cols-2 gap-2 mt-auto sm:grid-cols-3">
                        ${this.actions(e)}
                    </div>
                </div>
            </div>`;
        }).join('');
    },

    actions(e) {
        const btn = (href, label, cls, path, blank) =>
            `<a href="${href}"${blank ? ' target="_blank" rel="noopener"' : ''} class="inline-flex items-center justify-center gap-1.5 px-2 py-1.5 text-[11px] font-semibold border rounded-lg transition-colors ${cls}">
                <svg class="w-3.5 h-3.5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="${path}"/></svg>
                <span class="truncate">${label}</span>
            </a>`;
        const out = [];
        if (e.is_editable !== false)
            out.push(btn(`/organizator/event/${e.id}?action=edit`, 'Editează', 'bg-blue-50 text-blue-700 border-blue-200 hover:bg-blue-100', 'M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z'));
        out.push(btn(`/organizator/invitatii?event=${e.id}`, 'Invitații', 'bg-rose-50 text-rose-700 border-rose-200 hover:bg-rose-100', 'M3 8l7.89 4.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z'));
        out.push(btn(`/organizator/sold?event=${e.id}`, 'Vânzări', 'bg-emerald-50 text-emerald-700 border-emerald-200 hover:bg-emerald-100', 'M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8V7m0 10v1m0-9a9 9 0 110 0z'));
        out.push(btn(`/organizator/participanti?event=${e.id}`, 'Participanți', 'bg-cyan-50 text-cyan-700 border-cyan-200 hover:bg-cyan-100', 'M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z'));
        out.push(btn(`/organizator/analytics/${e.id}`, 'Analiză', 'bg-violet-50 text-violet-700 border-violet-200 hover:bg-violet-100', 'M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z'));
        const slug = e.slug || '';
        out.push(btn(`/bilete/${slug}`, 'Pagină event', 'bg-slate-50 text-slate-700 border-slate-200 hover:bg-slate-100', 'M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14', true));
        // Promovează — always last, full-width primary CTA.
        const promo = `<a href="/organizator/servicii?event=${e.id}" class="col-span-full inline-flex items-center justify-center gap-1.5 px-2 py-2 text-[11px] font-bold text-white rounded-lg bg-primary hover:opacity-90 transition-opacity">
                <svg class="w-3.5 h-3.5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5.882V19.24a1.76 1.76 0 01-3.417.592l-2.147-6.15M18 13a3 3 0 100-6M5.436 13.683A4.001 4.001 0 017 6h1.832c4.1 0 7.625-1.234 9.168-3v14c-1.543-1.766-5.067-3-9.168-3H7a3.988 3.988 0 01-1.564-.317z"/></svg>
                Promovează
            </a>`;
        return out.join('') + promo;
    },

    // ---------- CHART ----------
    async loadChart(days) {
        let data = null;
        try {
            const res = await AmbiletAPI.get('/organizer/dashboard/analytics-timeline?days=' + days);
            data = res.data || res;
        } catch (e) {
            console.warn('analytics-timeline unavailable', e);
        }
        const hasSignal = data && data.labels && data.labels.length &&
            ((data.revenue || []).some(v => v > 0) || (data.tickets || []).some(v => v > 0) || (data.views || []).some(v => v > 0));
        // Fallback to the always-available sales-timeline (revenue only) when the
        // richer endpoint isn't deployed yet or returned nothing usable.
        if (!hasSignal) {
            const fb = await this.loadChartFallback(days);
            if (fb) data = fb;
        }
        this.chartData = data;
        this.renderChart();
    },

    async loadChartFallback(days) {
        try {
            const fmt = (d) => d.getFullYear() + '-' + String(d.getMonth() + 1).padStart(2, '0') + '-' + String(d.getDate()).padStart(2, '0');
            const to = new Date();
            const from = new Date(); from.setDate(from.getDate() - (days - 1));
            const res = await AmbiletAPI.get('/organizer/dashboard/sales-timeline?from_date=' + fmt(from) + '&to_date=' + fmt(to) + '&group_by=day');
            const rows = (res.data && res.data.timeline) || res.timeline || [];
            if (!rows.length) return null;
            const byDay = {};
            rows.forEach(r => { byDay[String(r.period).slice(0, 10)] = Number(r.revenue) || 0; });
            const months = ['Ian','Feb','Mar','Apr','Mai','Iun','Iul','Aug','Sep','Oct','Nov','Dec'];
            const labels = [], revenue = [], tickets = [], views = [];
            const cur = new Date(from);
            for (let i = 0; i < days; i++) {
                const key = fmt(cur);
                labels.push(cur.getDate() + ' ' + months[cur.getMonth()]);
                revenue.push(byDay[key] || 0); tickets.push(0); views.push(0);
                cur.setDate(cur.getDate() + 1);
            }
            return { labels, revenue, tickets, views };
        } catch (e) {
            return null;
        }
    },

    renderChart() {
        const canvas = document.getElementById('pnChart');
        const emptyEl = document.getElementById('pnChartEmpty');
        if (!canvas || typeof Chart === 'undefined') return;
        const d = this.chartData || {};
        // Coerce to real numbers — a JSON API can hand back numeric strings,
        // which Chart.js will not plot as bars.
        const rev = (d.revenue || []).map(Number);
        const tik = (d.tickets || []).map(Number);
        const viw = (d.views || []).map(Number);
        const hasData = d.labels && d.labels.length &&
            (rev.some(v => v > 0) || tik.some(v => v > 0) || viw.some(v => v > 0));

        if (this.chart) { this.chart.destroy(); this.chart = null; }

        if (!hasData) {
            canvas.style.display = 'none';
            emptyEl.classList.remove('hidden'); emptyEl.classList.add('flex');
            return;
        }
        canvas.style.display = '';
        emptyEl.classList.add('hidden'); emptyEl.classList.remove('flex');

        this.chart = new Chart(canvas, {
            type: 'bar',
            data: {
                labels: d.labels,
                datasets: [
                    { type: 'line', label: 'Venituri (RON)', data: rev, yAxisID: 'y',
                      borderColor: '#10b981', backgroundColor: 'rgba(16,185,129,.12)',
                      borderWidth: 2.5, fill: true, tension: .35, pointRadius: 0, pointHoverRadius: 4, order: 0,
                      hidden: !this.metrics.revenue },
                    { type: 'bar', label: 'Bilete', data: tik, yAxisID: 'y1',
                      backgroundColor: 'rgba(59,130,246,.75)', borderRadius: 4, order: 1,
                      hidden: !this.metrics.tickets },
                    { type: 'bar', label: 'Vizualizări', data: viw, yAxisID: 'y1',
                      backgroundColor: 'rgba(6,182,212,.65)', borderRadius: 4, order: 2,
                      hidden: !this.metrics.views },
                ]
            },
            options: {
                responsive: true, maintainAspectRatio: false,
                interaction: { mode: 'index', intersect: false },
                plugins: {
                    legend: { display: false },
                    tooltip: {
                        callbacks: {
                            label: (c) => {
                                let v = c.parsed.y || 0;
                                if (c.dataset.label.indexOf('Venituri') === 0) return '  Venituri: ' + OrgPanouNou.money(v);
                                return '  ' + c.dataset.label + ': ' + OrgPanouNou.num(v);
                            }
                        }
                    }
                },
                scales: {
                    x: { grid: { display: false }, ticks: { color: '#94a3b8', maxRotation: 45, font: { size: 10 } } },
                    y:  { position: 'left',  beginAtZero: true, grid: { color: 'rgba(148,163,184,.15)' },
                          ticks: { color: '#10b981', font: { size: 10 },
                                   callback: (v) => new Intl.NumberFormat('ro-RO', { notation: 'compact', maximumFractionDigits: 1 }).format(v) } },
                    y1: { position: 'right', beginAtZero: true, grid: { display: false },
                          ticks: { color: '#3b82f6', font: { size: 10 }, precision: 0 } },
                }
            }
        });
    },

    applyMetricVisibility() {
        if (!this.chart) return;
        this.chart.data.datasets[0].hidden = !this.metrics.revenue;
        this.chart.data.datasets[1].hidden = !this.metrics.tickets;
        this.chart.data.datasets[2].hidden = !this.metrics.views;
        this.chart.update();
    },

    // ---------- helpers ----------
    money(v) { return new Intl.NumberFormat('ro-RO', { maximumFractionDigits: 0 }).format(Math.round(Number(v) || 0)) + ' lei'; },
    num(v) { return new Intl.NumberFormat('ro-RO').format(Number(v) || 0); },
    esc(s) { const d = document.createElement('div'); d.textContent = (s == null ? '' : String(s)); return d.innerHTML; },
};

function toggleSidebar() {
    const sidebar = document.getElementById('sidebar');
    const overlay = document.getElementById('sidebarOverlay');
    if (sidebar) sidebar.classList.toggle('-translate-x-full');
    if (overlay) overlay.classList.toggle('active');
}

document.addEventListener('DOMContentLoaded', () => OrgPanouNou.init());
</script>
<style>
    .pn-metric-btn { border-color: rgba(148,163,184,.35); color: #64748b; background: #fff; opacity: .55; }
    .pn-metric-btn.active { opacity: 1; border-color: rgba(148,163,184,.6); color: #334155; background: #f8fafc; }
</style>
JS;

require_once dirname(__DIR__) . '/includes/scripts.php';
?>
