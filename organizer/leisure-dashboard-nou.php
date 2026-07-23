<?php
require_once dirname(__DIR__) . '/includes/config.php';
$pageTitle = 'Dashboard live';
$bodyClass = 'min-h-screen flex bg-slate-100';
$currentPage = 'leisure_dashboard';
$headExtra = '<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>';
$cssBundle = 'organizer';
require_once dirname(__DIR__) . '/includes/head.php';
require_once dirname(__DIR__) . '/includes/organizer-sidebar.php';
?>

<div class="flex flex-col flex-1 min-h-screen lg:ml-0">
    <?php require_once dirname(__DIR__) . '/includes/organizer-topbar.php'; ?>

    <main class="flex-1 p-3 sm:p-4 lg:p-8">
        <div class="w-full mx-auto space-y-5 lg:space-y-7" style="max-width:1500px;">

            <div id="ld-error" class="hidden p-4 text-sm border rounded-xl bg-rose-50 border-rose-200 text-rose-900"></div>

            <!-- ===== CASĂ (cash drawer) ===== -->
            <section id="ld-casa" class="overflow-hidden border shadow-sm rounded-2xl border-border">
                <!-- filled by JS -->
            </section>

            <!-- ===== LIVE KPI ===== -->
            <section class="grid grid-cols-2 gap-3 lg:grid-cols-5">
                <div class="flex items-center gap-3 p-3.5 bg-white border shadow-sm rounded-xl border-border">
                    <div class="flex items-center justify-center w-10 h-10 rounded-lg shrink-0 bg-emerald-500/10">🎟️</div>
                    <div class="min-w-0"><p class="text-lg font-extrabold leading-none text-secondary tabular-nums" id="ld-sold">—</p><p class="mt-1 text-xs truncate text-muted">Bilete azi</p></div>
                </div>
                <div class="flex items-center gap-3 p-3.5 bg-white border shadow-sm rounded-xl border-border">
                    <div class="flex items-center justify-center w-10 h-10 rounded-lg shrink-0 bg-cyan-500/10">✅</div>
                    <div class="min-w-0"><p class="text-lg font-extrabold leading-none text-secondary tabular-nums" id="ld-scanned">—</p><p class="mt-1 text-xs truncate text-muted">Check-in azi</p></div>
                </div>
                <div class="flex items-center gap-3 p-3.5 bg-white border shadow-sm rounded-xl border-border">
                    <div class="flex items-center justify-center w-10 h-10 rounded-lg shrink-0 bg-amber-500/10">💰</div>
                    <div class="min-w-0"><p class="text-lg font-extrabold leading-none text-secondary tabular-nums" id="ld-revenue">—</p><p class="mt-1 text-xs truncate text-muted">Venituri azi</p></div>
                </div>
                <div class="flex items-center gap-3 p-3.5 bg-white border shadow-sm rounded-xl border-border">
                    <div class="flex items-center justify-center w-10 h-10 rounded-lg shrink-0 bg-indigo-500/10">🧾</div>
                    <div class="min-w-0"><p class="text-lg font-extrabold leading-none text-secondary tabular-nums" id="ld-orders">—</p><p class="mt-1 text-xs truncate text-muted">Comenzi azi</p></div>
                </div>
                <div class="flex items-center gap-3 p-3.5 bg-white border shadow-sm rounded-xl border-border">
                    <div class="flex items-center justify-center w-10 h-10 rounded-lg shrink-0 bg-teal-500/10">👥</div>
                    <div class="min-w-0"><p class="text-lg font-extrabold leading-none text-secondary tabular-nums" id="ld-occupancy">—</p><p class="mt-1 text-xs truncate text-muted">Prezenți acum</p></div>
                </div>
            </section>

            <!-- ===== SALES CHART + PARTICIPANTS ===== -->
            <section class="grid gap-5 lg:grid-cols-3">
                <div class="p-5 bg-white border shadow-sm rounded-2xl border-border lg:col-span-2">
                    <div class="flex items-center justify-between mb-4">
                        <div><h2 class="font-bold text-secondary">Vânzări — ultimele 30 zile</h2><p class="text-xs text-muted">Venituri (RON) și bilete pe zi</p></div>
                    </div>
                    <div style="height:280px;position:relative;"><canvas id="ld-sales-chart"></canvas>
                        <div id="ld-sales-empty" class="absolute inset-0 items-center justify-center hidden text-sm text-muted">Nicio vânzare în perioadă</div>
                    </div>
                </div>
                <div class="p-5 bg-white border shadow-sm rounded-2xl border-border">
                    <h2 class="mb-3 font-bold text-secondary">Participanți (persoane)</h2>
                    <div class="p-4 mb-3 text-center bg-teal-50 rounded-xl">
                        <p class="text-3xl font-extrabold text-teal-800 tabular-nums" id="ld-part-total">—</p>
                        <p class="text-xs text-teal-700">bilete de acces — pachetele se numără pe componente; fără parcare/activități</p>
                    </div>
                    <div class="grid grid-cols-2 gap-3">
                        <div class="p-3 text-center rounded-xl bg-surface"><p class="text-xl font-bold text-secondary tabular-nums" id="ld-part-checked">—</p><p class="text-[11px] text-muted">Check-in</p></div>
                        <div class="p-3 text-center rounded-xl bg-surface"><p class="text-xl font-bold text-secondary tabular-nums" id="ld-part-rate">—</p><p class="text-[11px] text-muted">Rată prezență</p></div>
                    </div>
                    <a href="/organizator/leisure-participants" class="block w-full py-2 mt-3 text-sm font-semibold text-center transition-colors border rounded-xl border-border text-secondary hover:border-teal-400">Vezi participanții →</a>
                </div>
            </section>

            <!-- ===== ACTIVITY STREAM ===== -->
            <section class="p-5 bg-white border shadow-sm rounded-2xl border-border">
                <h2 class="mb-3 font-bold text-secondary">Activitate recentă</h2>
                <div id="ld-stream" class="space-y-1"><p class="py-6 text-sm text-center text-muted">Se încarcă…</p></div>
            </section>

        </div>
    </main>
</div>

<?php
$scriptsExtra = <<<'JS'
<script>
const LeisureDash = {
    eventId: null,
    salesChart: null,

    async init() {
        AmbiletAuth.requireOrganizerAuth();
        let retries = 0;
        while (typeof AmbiletAPI === 'undefined' && retries < 10) { await new Promise(r => setTimeout(r, 100)); retries++; }
        try {
            const res = await AmbiletAPI.get('/organizer/events');
            const events = res.data || [];
            const leisure = events.filter(e => (e.display_template || 'standard') === 'leisure_venue');
            if (leisure.length) { this.eventId = leisure[0].id; }
        } catch (e) { console.error(e); }
        if (!this.eventId) {
            const el = document.getElementById('ld-error');
            el.textContent = 'Nu există un eveniment de tip Locație de agrement asociat.';
            el.classList.remove('hidden');
            return;
        }
        this.refreshAll();
        // Live refresh of the fast-moving panels every 20s.
        setInterval(() => { this.loadLive(); this.loadCasa(); }, 20000);
    },

    refreshAll() { this.loadLive(); this.loadCasa(); this.loadSales(); this.loadParticipants(); },

    // ---- Live stats + stream ----
    async loadLive() {
        try {
            const res = await AmbiletAPI.get(`/organizer/events/${this.eventId}/leisure/dashboard/live`);
            const d = res.data || {}; const s = d.stats || {};
            document.getElementById('ld-sold').textContent = this.num(s.sold_today);
            document.getElementById('ld-scanned').textContent = this.num(s.scanned_today);
            document.getElementById('ld-revenue').textContent = this.money(s.revenue_today);
            document.getElementById('ld-orders').textContent = this.num(s.orders_today);
            document.getElementById('ld-occupancy').textContent = this.num(s.occupancy);
            this.renderStream(d.stream || []);
        } catch (e) { console.warn('live', e); }
    },

    renderStream(stream) {
        const el = document.getElementById('ld-stream');
        if (!stream.length) { el.innerHTML = '<p class="py-6 text-sm text-center text-muted">Nicio activitate recentă.</p>'; return; }
        const ICON = { sale: '🛒', scan: '✅', staff_scan: '👷' };
        el.innerHTML = stream.slice(0, 20).map(a => `
            <div class="flex items-center gap-3 py-2 border-b border-slate-100 last:border-0">
                <span class="text-lg">${ICON[a.type] || '•'}</span>
                <div class="flex-1 min-w-0">
                    <p class="text-sm font-semibold truncate text-secondary">${this.esc(a.label)}</p>
                    <p class="text-xs truncate text-muted">${this.esc(a.detail || '')}</p>
                </div>
                <span class="text-[11px] text-muted whitespace-nowrap">${this.fmtTime(a.at)}</span>
            </div>`).join('');
    },

    // ---- Casă (cash drawer) ----
    async loadCasa() {
        try {
            const res = await AmbiletAPI.get(`/organizer/events/${this.eventId}/leisure/cashier/current`);
            this.renderCasa((res.data || {}).session || null);
        } catch (e) { console.warn('casa', e); }
    },

    renderCasa(session) {
        const el = document.getElementById('ld-casa');
        if (!session) {
            el.className = 'overflow-hidden border-2 shadow-sm rounded-2xl border-slate-300 bg-slate-50';
            el.innerHTML = `<div class="flex flex-col gap-2 p-5 sm:flex-row sm:items-center sm:justify-between">
                <div><p class="text-[11px] uppercase tracking-wider font-bold text-slate-500">Casă</p><p class="text-lg font-extrabold text-slate-700">🔒 Închisă</p><p class="text-xs text-muted">Nicio sesiune de casă deschisă acum.</p></div>
                <a href="/organizator/leisure-pos" class="inline-flex px-4 py-2 text-sm font-bold text-white rounded-xl bg-emerald-600 hover:bg-emerald-700">Deschide casa</a>
            </div>`;
            return;
        }
        const live = session.live || { cash: 0, card: 0, total: 0, orders: 0 };
        el.className = 'overflow-hidden border-2 shadow-sm rounded-2xl border-emerald-300 bg-emerald-50';
        el.innerHTML = `
            <div class="flex flex-wrap items-center justify-between gap-3 px-5 py-3 border-b border-emerald-200 bg-emerald-100/60">
                <div><p class="text-[11px] uppercase tracking-wider font-bold text-emerald-700">Casă · ${this.esc(session.opened_label || 'InfoPoint')}</p>
                    <p class="text-lg font-extrabold text-emerald-900">🟢 Deschisă de ${this.since(session.opened_at)}</p>
                    <p class="text-xs text-emerald-700/80">Deschisă la ${this.fmtDateTime(session.opened_at)} · doar vânzările POS din locație (online-ul nu intră în casă)</p>
                </div>
                <a href="/organizator/leisure-pos" class="inline-flex px-4 py-2 text-sm font-bold rounded-xl bg-white text-emerald-800 border border-emerald-300 hover:bg-emerald-50">Gestionează casa</a>
            </div>
            <div class="grid grid-cols-2 gap-3 p-5 sm:grid-cols-4">
                <div class="p-3 bg-white border-2 rounded-xl border-amber-300">
                    <p class="text-[10px] uppercase tracking-wider font-bold text-amber-700">💵 Cash de predat</p>
                    <p class="text-2xl font-extrabold text-amber-800 tabular-nums">${this.money(live.cash)} <span class="text-xs">RON</span></p>
                </div>
                <div class="p-3 bg-white border-2 rounded-xl border-sky-200">
                    <p class="text-[10px] uppercase tracking-wider font-bold text-sky-700">💳 Card încasat</p>
                    <p class="text-2xl font-extrabold text-sky-800 tabular-nums">${this.money(live.card)} <span class="text-xs">RON</span></p>
                </div>
                <div class="p-3 bg-white rounded-xl">
                    <p class="text-[10px] uppercase tracking-wider font-bold text-muted">Total în casă</p>
                    <p class="text-2xl font-extrabold text-emerald-800 tabular-nums">${this.money(live.total)} <span class="text-xs">RON</span></p>
                </div>
                <div class="p-3 bg-white rounded-xl">
                    <p class="text-[10px] uppercase tracking-wider font-bold text-muted">Comenzi în sesiune</p>
                    <p class="text-2xl font-extrabold text-secondary tabular-nums">${this.num(live.orders)}</p>
                </div>
            </div>`;
    },

    // ---- Participants ----
    async loadParticipants() {
        try {
            const res = await AmbiletAPI.get(`/organizer/events/${this.eventId}/leisure/participants`);
            const st = (res.data || {}).stats || {};
            document.getElementById('ld-part-total').textContent = this.num(st.total);
            document.getElementById('ld-part-checked').textContent = this.num(st.checked_in);
            document.getElementById('ld-part-rate').textContent = (Number(st.rate) || 0) + '%';
        } catch (e) { console.warn('participants', e); }
    },

    // ---- Sales chart ----
    async loadSales() {
        try {
            const to = new Date(); const from = new Date(); from.setDate(from.getDate() - 29);
            const fmt = d => d.getFullYear() + '-' + String(d.getMonth() + 1).padStart(2, '0') + '-' + String(d.getDate()).padStart(2, '0');
            const res = await AmbiletAPI.get(`/organizer/events/${this.eventId}/leisure/sales-timeline`, { from: fmt(from), to: fmt(to), group_by: 'day' });
            this.renderSales((res.data || {}).rows || []);
        } catch (e) { console.warn('sales', e); }
    },

    renderSales(rows) {
        const canvas = document.getElementById('ld-sales-chart');
        const emptyEl = document.getElementById('ld-sales-empty');
        if (!canvas || typeof Chart === 'undefined') return;
        const labels = rows.map(r => { const d = new Date((r.date || '') + 'T00:00:00'); return isNaN(d) ? (r.date || '') : (d.getDate() + '.' + (d.getMonth() + 1)); });
        const revenue = rows.map(r => Number(r.revenue || 0));
        const tickets = rows.map(r => Number(r.tickets || 0));
        const has = revenue.some(v => v > 0) || tickets.some(v => v > 0);
        if (this.salesChart) { this.salesChart.destroy(); this.salesChart = null; }
        if (!has) { canvas.style.display = 'none'; emptyEl.classList.remove('hidden'); emptyEl.classList.add('flex'); return; }
        canvas.style.display = ''; emptyEl.classList.add('hidden'); emptyEl.classList.remove('flex');
        this.salesChart = new Chart(canvas, {
            type: 'bar',
            data: { labels, datasets: [
                { type: 'line', label: 'Venituri (RON)', data: revenue, yAxisID: 'y', borderColor: '#059669', backgroundColor: 'rgba(5,150,105,.12)', borderWidth: 2.5, fill: true, tension: .35, pointRadius: 0, order: 0 },
                { type: 'bar', label: 'Bilete', data: tickets, yAxisID: 'y1', backgroundColor: 'rgba(20,184,166,.55)', borderRadius: 4, order: 1 },
            ]},
            options: { responsive: true, maintainAspectRatio: false, interaction: { mode: 'index', intersect: false },
                plugins: { legend: { display: true, labels: { boxWidth: 10, font: { size: 11 } } } },
                scales: {
                    x: { grid: { display: false }, ticks: { color: '#94a3b8', maxRotation: 45, font: { size: 10 } } },
                    y: { position: 'left', beginAtZero: true, grid: { color: 'rgba(148,163,184,.15)' }, ticks: { color: '#059669', font: { size: 10 }, callback: v => new Intl.NumberFormat('ro-RO', { notation: 'compact' }).format(v) } },
                    y1: { position: 'right', beginAtZero: true, grid: { display: false }, ticks: { color: '#0d9488', font: { size: 10 }, precision: 0 } },
                } }
        });
    },

    // ---- helpers ----
    money(v) { return new Intl.NumberFormat('ro-RO', { maximumFractionDigits: 0 }).format(Math.round(Number(v) || 0)); },
    num(v) { return new Intl.NumberFormat('ro-RO').format(Number(v) || 0); },
    fmtTime(s) { if (!s) return '—'; const d = new Date(s); return isNaN(d) ? '—' : d.toLocaleTimeString('ro-RO', { hour: '2-digit', minute: '2-digit' }); },
    fmtDateTime(s) { if (!s) return '—'; const d = new Date(s); return isNaN(d) ? '—' : d.toLocaleString('ro-RO', { day: '2-digit', month: 'short', hour: '2-digit', minute: '2-digit' }); },
    since(s) { if (!s) return '—'; const mins = Math.max(0, Math.floor((Date.now() - new Date(s).getTime()) / 60000)); const h = Math.floor(mins / 60), m = mins % 60; return h > 0 ? (h + 'h ' + m + 'm') : (m + 'm'); },
    esc(s) { const d = document.createElement('div'); d.textContent = (s == null ? '' : String(s)); return d.innerHTML; },
};

function toggleSidebar() {
    const sidebar = document.getElementById('sidebar');
    const overlay = document.getElementById('sidebarOverlay');
    if (sidebar) sidebar.classList.toggle('-translate-x-full');
    if (overlay) overlay.classList.toggle('active');
}

document.addEventListener('DOMContentLoaded', () => LeisureDash.init());
</script>
JS;

require_once dirname(__DIR__) . '/includes/scripts.php';
?>
