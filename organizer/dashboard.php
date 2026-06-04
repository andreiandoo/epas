<?php
/**
 * bilete.online — Organizator › Panou (v3).
 * Route: /organizator/panou
 *
 * Dashboard landing for the organizer account: KPI cards, sales chart
 * (Chart.js, 7/30/90/custom), activities table, next activity + recent
 * activity. Activity-centric adaptation of the ambilet dashboard, wired to
 * BileteOnlineAPI.organizer (getDashboard / dashboard.sales-timeline) and the
 * v3 shell hydration.
 */
require_once dirname(__DIR__) . '/includes/config.php';
$pageTitle   = 'Panou';
$currentPage = 'dashboard';
$extraHead   = '<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>';
require_once dirname(__DIR__) . '/includes/head.php';
require_once dirname(__DIR__) . '/includes/organizer-sidebar.php';
?>
<div class="flex min-w-0 flex-1 flex-col">
    <?php require_once dirname(__DIR__) . '/includes/organizer-topbar.php'; ?>

    <main class="flex-1">
        <!-- Welcome banner -->
        <div class="relative overflow-hidden bg-gradient-to-br from-vermilion to-vermilion-d p-6 text-paper lg:p-8">
            <div class="absolute right-0 top-0 h-64 w-64 -translate-y-1/2 translate-x-1/2 rounded-full bg-paper/5"></div>
            <div class="absolute right-20 bottom-0 h-32 w-32 translate-y-1/2 rounded-full bg-paper/5"></div>
            <div class="relative">
                <h1 class="mb-2 font-display text-2xl font-bold md:text-3xl">Bun venit înapoi! 👋</h1>
                <p id="welcome-stat" class="text-paper/85">Se încarcă datele…</p>
            </div>
        </div>

        <div class="p-4 lg:p-8">
            <!-- KPI cards -->
            <div class="mb-8 grid grid-cols-2 gap-4 lg:grid-cols-4">
                <div class="rounded-2xl border-2 border-ink bg-paper p-5">
                    <div class="mb-3 flex items-center justify-between">
                        <span class="grid h-10 w-10 place-items-center rounded-xl bg-forest/10 text-forest"><svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg></span>
                        <span id="stat-revenue-change" class="rounded-full bg-forest/10 px-2 py-1 text-xs font-bold text-forest">+0%</span>
                    </div>
                    <p id="stat-revenue" class="font-display text-2xl font-bold">0 lei</p>
                    <p class="mt-1 text-sm text-ink-soft">Venituri luna aceasta</p>
                </div>
                <div class="rounded-2xl border-2 border-ink bg-paper p-5">
                    <div class="mb-3 flex items-center justify-between">
                        <span class="grid h-10 w-10 place-items-center rounded-xl bg-vermilion/10 text-vermilion"><svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M15 5v2m0 4v2m0 4v2M5 5a2 2 0 00-2 2v3a2 2 0 110 4v3a2 2 0 002 2h14a2 2 0 002-2v-3a2 2 0 110-4V7a2 2 0 00-2-2H5z"/></svg></span>
                        <span id="stat-tickets-change" class="rounded-full bg-forest/10 px-2 py-1 text-xs font-bold text-forest">+0%</span>
                    </div>
                    <p id="stat-tickets" class="font-display text-2xl font-bold">0</p>
                    <p class="mt-1 text-sm text-ink-soft">Bilete vândute</p>
                </div>
                <div class="rounded-2xl border-2 border-ink bg-paper p-5">
                    <div class="mb-3 flex items-center justify-between">
                        <span class="grid h-10 w-10 place-items-center rounded-xl bg-ochre/10 text-ochre"><svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg></span>
                    </div>
                    <p id="stat-events" class="font-display text-2xl font-bold">0</p>
                    <p class="mt-1 text-sm text-ink-soft">Activități active</p>
                </div>
                <div class="rounded-2xl border-2 border-ink bg-paper p-5">
                    <div class="mb-3 flex items-center justify-between">
                        <span class="grid h-10 w-10 place-items-center rounded-xl bg-sky/10 text-sky"><svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6"/></svg></span>
                        <span id="stat-conv-change" class="rounded-full bg-forest/10 px-2 py-1 text-xs font-bold text-forest">+0%</span>
                    </div>
                    <p id="stat-conversion" class="font-display text-2xl font-bold">0%</p>
                    <p class="mt-1 text-sm text-ink-soft">Rată conversie</p>
                </div>
            </div>

            <div class="grid gap-8 lg:grid-cols-3">
                <!-- Left -->
                <div class="space-y-8 lg:col-span-2">
                    <!-- Sales chart -->
                    <div class="rounded-2xl border-2 border-ink bg-paper p-6">
                        <div class="mb-6 flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
                            <div>
                                <h2 class="font-display text-lg font-bold">Vânzări bilete</h2>
                                <p id="chartPeriodLabel" class="text-sm text-ink-soft">Ultimele 7 zile</p>
                            </div>
                            <div class="flex flex-wrap items-center gap-2">
                                <button class="chart-period rounded-lg bg-vermilion/10 px-3 py-1.5 text-xs font-bold text-vermilion" data-days="7">7 zile</button>
                                <button class="chart-period rounded-lg px-3 py-1.5 text-xs font-bold text-ink-soft transition hover:bg-paper-2" data-days="30">30 zile</button>
                                <button class="chart-period rounded-lg px-3 py-1.5 text-xs font-bold text-ink-soft transition hover:bg-paper-2" data-days="90">90 zile</button>
                                <button class="chart-period rounded-lg px-3 py-1.5 text-xs font-bold text-ink-soft transition hover:bg-paper-2" data-days="custom">Personalizat</button>
                            </div>
                        </div>
                        <div id="chartCustomRange" class="mb-4 flex flex-wrap items-center gap-2 rounded-xl border-2 border-ink/15 bg-paper-2 p-3" style="display:none;">
                            <label class="flex items-center gap-2 text-xs text-ink-soft">De la<input type="date" id="chartCustomFrom" class="rounded-lg border-2 border-ink/15 bg-paper px-2 py-1 text-xs"></label>
                            <label class="flex items-center gap-2 text-xs text-ink-soft">Până la<input type="date" id="chartCustomTo" class="rounded-lg border-2 border-ink/15 bg-paper px-2 py-1 text-xs"></label>
                            <button id="chartCustomApply" class="rounded-lg bg-vermilion px-3 py-1 text-xs font-bold text-paper transition hover:bg-vermilion-d">Aplică</button>
                        </div>
                        <div class="h-64"><canvas id="salesChart"></canvas></div>
                    </div>

                    <!-- Activities table -->
                    <div class="overflow-hidden rounded-2xl border-2 border-ink bg-paper">
                        <div class="flex items-center justify-between border-b-2 border-ink/10 p-6">
                            <div>
                                <h2 class="font-display text-lg font-bold">Activitățile tale</h2>
                                <p id="events-count-text" class="text-sm text-ink-soft">0 activități active</p>
                            </div>
                            <a href="/organizator/events" class="text-sm font-bold text-vermilion underline-wobble">Vezi toate →</a>
                        </div>
                        <div class="overflow-x-auto">
                            <table class="w-full" id="events-table">
                                <thead class="bg-paper-2 text-left">
                                    <tr class="font-mono text-[11px] uppercase tracking-[.12em] text-ink-soft">
                                        <th class="px-6 py-3">Activitate</th>
                                        <th class="px-6 py-3">Dată</th>
                                        <th class="px-6 py-3">Vânzări</th>
                                        <th class="px-6 py-3">Status</th>
                                        <th class="px-6 py-3 text-right">Acțiuni</th>
                                    </tr>
                                </thead>
                                <tbody id="events-tbody" class="divide-y divide-ink/10 text-sm"></tbody>
                            </table>
                        </div>
                        <div id="no-events" class="hidden py-12 text-center">
                            <svg class="mx-auto mb-3 h-12 w-12 text-ink/15" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                            <p class="mb-3 text-ink-soft">Nu ai activități încă.</p>
                            <a href="/organizator/events?new=1" class="inline-block rounded-full bg-vermilion px-4 py-2 text-sm font-bold text-paper transition hover:bg-vermilion-d">Creează prima activitate</a>
                        </div>
                    </div>
                </div>

                <!-- Right -->
                <div class="space-y-8">
                    <div class="overflow-hidden rounded-2xl border-2 border-ink bg-paper">
                        <div class="border-b-2 border-ink/10 p-5"><h2 class="font-display font-bold">Următoarea activitate</h2></div>
                        <div id="upcoming-event-content" class="p-5"><p class="py-4 text-center text-sm text-ink-soft">—</p></div>
                    </div>

                    <div class="rounded-2xl border-2 border-ink bg-paper p-5">
                        <h2 class="mb-4 font-display font-bold">Acțiuni rapide</h2>
                        <div class="grid grid-cols-2 gap-3">
                            <a href="/organizator/events?new=1" class="flex flex-col items-center gap-2 rounded-xl bg-vermilion px-3 py-4 text-center text-sm font-bold text-paper transition hover:bg-vermilion-d"><svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/></svg>Activitate nouă</a>
                            <a href="/organizator/participanti" class="flex flex-col items-center gap-2 rounded-xl border-2 border-ink px-3 py-4 text-center text-sm font-bold transition hover:bg-ink hover:text-paper"><svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>Check-in</a>
                            <a href="/organizator/vanzari" class="flex flex-col items-center gap-2 rounded-xl border-2 border-ink px-3 py-4 text-center text-sm font-bold transition hover:bg-ink hover:text-paper"><svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/></svg>Vânzări</a>
                            <a href="/organizator/sold" class="flex flex-col items-center gap-2 rounded-xl border-2 border-ink px-3 py-4 text-center text-sm font-bold transition hover:bg-ink hover:text-paper"><svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z"/></svg>Sold</a>
                        </div>
                    </div>

                    <div class="rounded-2xl border-2 border-ink bg-paper p-5">
                        <h2 class="mb-4 font-display font-bold">Activitate recentă</h2>
                        <div id="recent-activity" class="space-y-4"></div>
                    </div>
                </div>
            </div>
        </div>
    </main>

    <?php require_once dirname(__DIR__) . '/includes/organizer-footer.php'; ?>
</div>

<?php
$scriptsExtra = <<<'JS'
<script>
function orgNotify(msg, type) {
    try { if (typeof BileteOnlineNotifications !== 'undefined' && BileteOnlineNotifications[type || 'info']) { BileteOnlineNotifications[type || 'info'](msg); return; } } catch (e) {}
}
const MONTHS = ['Ian', 'Feb', 'Mar', 'Apr', 'Mai', 'Iun', 'Iul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
function escHtml(s) { const d = document.createElement('div'); d.textContent = s == null ? '' : String(s); return d.innerHTML; }
function imgUrl(p) { try { return getStorageUrl(p); } catch (e) { return p || '/assets/images/default-event.png'; } }

const OrgDashboard = {
    data: null, salesChart: null,

    async init() {
        if (typeof BileteOnlineAuth !== 'undefined' && BileteOnlineAuth.requireOrganizerAuth && !BileteOnlineAuth.requireOrganizerAuth()) return;
        await this.loadDashboard();
        this.initChart();
        this.setupChartPeriod();
    },

    async loadDashboard() {
        try {
            const r = await BileteOnlineAPI.organizer.getDashboard();
            if (r && r.success && r.data) { this.data = r.data; this.render(); return; }
        } catch (e) {}
        this.data = { revenue_month: 0, tickets_sold: 0, active_events: 0, events_list: [], recent_activity: [] };
        this.render();
    },

    render() {
        const d = this.data;
        const revenueMonth = d.revenue_month ?? (d.sales && d.sales.gross_revenue) ?? 0;
        const ticketsSold = d.tickets_sold ?? (d.sales && d.sales.tickets_sold) ?? 0;
        const activeEvents = d.active_events ?? (d.events && d.events.upcoming) ?? 0;
        const conversionRate = d.conversion_rate ?? 0;

        document.getElementById('stat-revenue').textContent = this.money(revenueMonth);
        document.getElementById('stat-revenue-change').textContent = `+${d.revenue_change ?? 0}%`;
        document.getElementById('stat-tickets').textContent = ticketsSold;
        document.getElementById('stat-tickets-change').textContent = `+${d.tickets_change ?? 0}%`;
        document.getElementById('stat-events').textContent = activeEvents;
        document.getElementById('stat-conversion').textContent = `${conversionRate}%`;
        document.getElementById('stat-conv-change').textContent = `+${d.conversion_change ?? 0}%`;

        const weekly = d.weekly_sales ?? 0;
        let msg;
        if (weekly === 0) msg = 'Nu ai vânzări în ultima săptămână. Hai să schimbăm asta! 💪';
        else if (weekly <= 10) msg = `Ai vândut ${weekly} bilete în ultima săptămână. Un început bun!`;
        else if (weekly <= 50) msg = `Ai vândut ${weekly} bilete în ultima săptămână. Continuă tot așa! 🎉`;
        else msg = `Ai vândut ${weekly} bilete în ultima săptămână. Excelent! 🚀`;
        document.getElementById('welcome-stat').textContent = msg;

        const events = d.events_list ?? d.events ?? [];
        this.renderEvents(Array.isArray(events) ? events : []);
        if (Array.isArray(events) && events.length) this.renderUpcoming(events[0]);
        this.renderActivity(d.recent_activity);
    },

    renderEvents(events) {
        const tbody = document.getElementById('events-tbody');
        const no = document.getElementById('no-events');
        const table = document.getElementById('events-table');
        if (!events.length) { tbody.innerHTML = ''; no.classList.remove('hidden'); table.classList.add('hidden'); return; }
        no.classList.add('hidden'); table.classList.remove('hidden');
        document.getElementById('events-count-text').textContent = `${events.length} activități active`;
        tbody.innerHTML = events.map(e => {
            const sold = e.tickets_sold || 0, total = e.tickets_total || 100;
            const pct = total > 0 ? Math.round((sold / total) * 100) : 0;
            const bar = pct > 50 ? 'bg-forest' : pct > 25 ? 'bg-ochre' : 'bg-vermilion';
            const date = new Date(e.start_date || e.starts_at);
            const name = e.name || e.title || 'Activitate';
            const venue = e.venue || e.venue_name || '';
            const city = e.venue_city ? `, ${e.venue_city}` : '';
            return `
                <tr class="hover:bg-paper-2/60">
                    <td class="px-6 py-4"><div class="flex items-center gap-3"><img src="${imgUrl(e.image)}" class="h-12 w-12 rounded-xl object-cover" alt="" onerror="this.src='/assets/images/default-event.png'" loading="lazy"><div><p class="font-bold">${escHtml(name)}</p><p class="text-xs text-ink-soft">${escHtml(venue)}${escHtml(city)}</p></div></div></td>
                    <td class="px-6 py-4"><p class="text-sm font-medium">${isNaN(date) ? '—' : date.getDate() + ' ' + MONTHS[date.getMonth()]}</p><p class="text-xs text-ink-soft">${isNaN(date) ? '' : date.getFullYear()}</p></td>
                    <td class="px-6 py-4"><div class="flex items-center gap-2"><div class="h-2 w-20 overflow-hidden rounded-full bg-paper-2"><div class="h-full ${bar} rounded-full" style="width:${pct}%"></div></div><span class="text-sm font-medium">${sold}/${total}</span></div></td>
                    <td class="px-6 py-4"><span class="rounded-full ${e.status === 'published' ? 'bg-forest/15 text-forest' : 'bg-ink/10 text-ink-soft'} px-2.5 py-1 text-xs font-bold">${e.status === 'published' ? 'Activă' : 'Draft'}</span></td>
                    <td class="px-6 py-4 text-right"><a href="/organizator/events?id=${e.id}" class="inline-grid h-8 w-8 place-items-center rounded-lg border-2 border-ink/15 text-ink-soft transition hover:border-ink hover:text-ink"><svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg></a></td>
                </tr>`;
        }).join('');
    },

    renderUpcoming(e) {
        const c = document.getElementById('upcoming-event-content');
        const date = new Date(e.start_date || e.starts_at);
        const sold = e.tickets_sold || 0, total = e.tickets_total || 100;
        const available = total - sold;
        const name = e.name || e.title || 'Activitate';
        const venue = e.venue || e.venue_name || '';
        const city = e.venue_city ? `, ${e.venue_city}` : '';
        const diffDays = Math.ceil((date - new Date()) / (1000 * 60 * 60 * 24));
        let label = '';
        if (diffDays === 0) label = 'Astăzi'; else if (diffDays === 1) label = 'Mâine';
        else if (diffDays > 0) label = `În ${diffDays} zile`; else label = 'Încheiată';
        c.innerHTML = `
            <div class="relative mb-4"><img src="${imgUrl(e.image)}" class="h-32 w-full rounded-xl object-cover" alt="" onerror="this.src='/assets/images/default-event.png'" loading="lazy">${diffDays >= 0 ? `<div class="absolute left-3 top-3 rounded-lg bg-vermilion px-3 py-1 text-xs font-bold text-paper">${label}</div>` : ''}</div>
            <h3 class="mb-1 font-bold">${escHtml(name)}</h3>
            <p class="mb-4 text-sm text-ink-soft">${isNaN(date) ? '' : date.getDate() + ' ' + MONTHS[date.getMonth()] + ' ' + date.getFullYear()} · ${escHtml(venue)}${escHtml(city)}</p>
            <div class="mb-4 grid grid-cols-2 gap-3">
                <div class="rounded-xl bg-paper-2 p-3 text-center"><p class="font-display text-xl font-bold">${sold}</p><p class="text-xs text-ink-soft">Bilete vândute</p></div>
                <div class="rounded-xl bg-paper-2 p-3 text-center"><p class="font-display text-xl font-bold">${available}</p><p class="text-xs text-ink-soft">Disponibile</p></div>
            </div>
            <div class="flex gap-2">
                <a href="/organizator/participanti?event=${e.id}" class="flex-1 rounded-full bg-vermilion py-2.5 text-center text-sm font-bold text-paper transition hover:bg-vermilion-d">Check-in</a>
                <a href="/organizator/events?id=${e.id}" class="flex-1 rounded-full border-2 border-ink py-2.5 text-center text-sm font-bold transition hover:bg-ink hover:text-paper">Detalii</a>
            </div>`;
    },

    renderActivity(activities) {
        const c = document.getElementById('recent-activity');
        if (!activities || !activities.length) { c.innerHTML = '<p class="py-4 text-center text-sm text-ink-soft">Nicio activitate recentă.</p>'; return; }
        c.innerHTML = activities.slice(0, 5).map(a => `
            <div class="flex items-start gap-3">
                <span class="grid h-8 w-8 flex-shrink-0 place-items-center rounded-full bg-vermilion/10 text-vermilion"><svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg></span>
                <div><p class="text-sm">${escHtml(a.message)}</p>${a.details ? `<p class="text-xs text-ink-soft">${escHtml(a.details)}</p>` : ''}${a.time ? `<p class="mt-1 text-xs text-ink-soft">${escHtml(a.time)}</p>` : ''}</div>
            </div>`).join('');
    },

    initChart() {
        const el = document.getElementById('salesChart');
        if (!el || typeof Chart === 'undefined') return;
        this.salesChart = new Chart(el.getContext('2d'), {
            type: 'line',
            data: { labels: [], datasets: [
                { label: 'Comenzi', data: [], borderColor: '#E84527', backgroundColor: 'rgba(232,69,39,0.1)', borderWidth: 3, fill: true, tension: 0.4, pointBackgroundColor: '#E84527', pointBorderColor: '#fff', pointBorderWidth: 2, pointRadius: 4, pointHoverRadius: 6 },
                { label: 'Venituri (lei)', data: [], borderColor: '#1E4A3D', backgroundColor: 'transparent', borderWidth: 2, borderDash: [5, 5], tension: 0.4, pointRadius: 0, yAxisID: 'y1' }
            ] },
            options: {
                responsive: true, maintainAspectRatio: false, interaction: { intersect: false, mode: 'index' },
                plugins: {
                    legend: { display: true, position: 'top', align: 'end', labels: { boxWidth: 12, padding: 16, font: { size: 12 } } },
                    tooltip: { backgroundColor: '#1B1714', padding: 12, cornerRadius: 8, displayColors: true }
                },
                scales: {
                    x: { grid: { display: false }, ticks: { font: { size: 12 }, color: '#5A4F46' } },
                    y: { beginAtZero: true, position: 'left', grid: { color: '#E8DFCF' }, ticks: { font: { size: 12 }, color: '#5A4F46' } },
                    y1: { beginAtZero: true, position: 'right', grid: { display: false }, ticks: { font: { size: 12 }, color: '#1E4A3D', callback: v => v + ' lei' } }
                }
            }
        });
        this.loadChartData(7);
    },

    setupChartPeriod() {
        const self = this;
        const customEl = document.getElementById('chartCustomRange');
        const activate = (btn) => {
            document.querySelectorAll('.chart-period').forEach(b => { b.classList.remove('bg-vermilion/10', 'text-vermilion'); b.classList.add('text-ink-soft'); });
            if (btn) { btn.classList.add('bg-vermilion/10', 'text-vermilion'); btn.classList.remove('text-ink-soft'); }
        };
        document.querySelectorAll('.chart-period').forEach(btn => {
            btn.addEventListener('click', async () => {
                const mode = btn.dataset.days;
                activate(btn);
                if (mode === 'custom') {
                    if (customEl) {
                        customEl.style.display = '';
                        const fromEl = document.getElementById('chartCustomFrom'), toEl = document.getElementById('chartCustomTo');
                        const today = new Date();
                        if (toEl && !toEl.value) toEl.value = today.toISOString().split('T')[0];
                        if (fromEl && !fromEl.value) fromEl.value = new Date(Date.now() - 7 * 864e5).toISOString().split('T')[0];
                    }
                    return;
                }
                if (customEl) customEl.style.display = 'none';
                await self.loadChartData(parseInt(mode) || 7);
            });
        });
        const applyBtn = document.getElementById('chartCustomApply');
        if (applyBtn) applyBtn.addEventListener('click', async () => {
            const from = document.getElementById('chartCustomFrom').value, to = document.getElementById('chartCustomTo').value;
            if (!from || !to) { orgNotify('Alege ambele date pentru a aplica perioada.', 'warning'); return; }
            if (from > to) { orgNotify('Data de început trebuie să fie înainte de data de sfârșit.', 'warning'); return; }
            await self.loadChartData({ from, to });
        });
    },

    updateChartLabel(range) {
        const el = document.getElementById('chartPeriodLabel');
        if (!el) return;
        if (range && range.from && range.to) {
            const fmt = (iso) => new Date(iso).toLocaleDateString('ro-RO', { day: '2-digit', month: '2-digit', year: 'numeric' });
            el.textContent = `${fmt(range.from)} – ${fmt(range.to)}`;
        } else { el.textContent = `Ultimele ${typeof range === 'number' ? range : 7} zile`; }
    },

    async loadChartData(range = 7) {
        try {
            let fromDate, toDate;
            if (typeof range === 'object' && range.from && range.to) { fromDate = range.from; toDate = range.to; this.updateChartLabel(range); }
            else { const days = typeof range === 'number' ? range : 7; toDate = new Date().toISOString().split('T')[0]; fromDate = new Date(Date.now() - days * 864e5).toISOString().split('T')[0]; this.updateChartLabel(days); }
            const r = await BileteOnlineAPI.get(`/organizer/dashboard/sales-timeline?from_date=${fromDate}&to_date=${toDate}&group_by=day`);
            if (r && r.success && r.data && r.data.timeline) {
                const t = r.data.timeline;
                const labels = t.map(x => { const d = new Date(x.period); return `${d.getDate()}/${d.getMonth() + 1}`; });
                const tickets = t.map(x => x.orders || 0);
                const revenue = t.map(x => parseFloat(x.revenue) || 0);
                if (this.salesChart) { this.salesChart.data.labels = labels; this.salesChart.data.datasets[0].data = tickets; this.salesChart.data.datasets[1].data = revenue; this.salesChart.update(); }
            }
        } catch (e) {}
    },

    money(a) { try { return BileteOnlineUtils.formatCurrency(a); } catch (e) { return new Intl.NumberFormat('ro-RO').format(a || 0) + ' lei'; } },
};

document.addEventListener('DOMContentLoaded', () => OrgDashboard.init());
</script>
JS;
require_once dirname(__DIR__) . '/includes/scripts.php';
?>
