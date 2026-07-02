<?php
require_once dirname(__DIR__) . '/includes/config.php';
$pageTitle = 'Vânzări';
$bodyClass = 'min-h-screen flex bg-slate-100';
$currentPage = 'leisure_sales';
$cssBundle = 'organizer';
$headExtra = '<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>';
require_once dirname(__DIR__) . '/includes/head.php';
require_once dirname(__DIR__) . '/includes/organizer-sidebar.php';
?>
<div class="flex flex-col flex-1 min-h-screen lg:ml-0">
    <?php require_once dirname(__DIR__) . '/includes/organizer-topbar.php'; ?>
    <main class="flex-1 p-4 lg:p-8">
        <div class="mb-6">
            <h1 class="text-2xl font-bold text-secondary lg:text-3xl">Vânzări</h1>
            <p class="mt-1 text-sm text-muted">Cifre detaliate pe perioade — pe categorii, societăți emitente, ore vârf.</p>
        </div>

        <!-- Date range -->
        <div class="bg-white border rounded-2xl border-border p-4 mb-6 flex flex-wrap items-end gap-2">
            <div class="flex-1 min-w-[200px]">
                <p class="text-xs uppercase tracking-wider text-muted font-semibold mb-2">Filtru perioadă</p>
                <div class="flex flex-wrap gap-2">
                    <button data-range="7" class="lv-range-btn px-3 py-1.5 text-xs font-medium rounded-lg border border-border bg-white hover:bg-slate-50">7 zile</button>
                    <button data-range="14" class="lv-range-btn px-3 py-1.5 text-xs font-medium rounded-lg border border-border bg-white hover:bg-slate-50">14 zile</button>
                    <button data-range="30" class="lv-range-btn px-3 py-1.5 text-xs font-medium rounded-lg border border-border bg-white hover:bg-slate-50">1 lună</button>
                    <button data-range="90" class="lv-range-btn px-3 py-1.5 text-xs font-medium rounded-lg border border-border bg-white hover:bg-slate-50">3 luni</button>
                    <button data-range="180" class="lv-range-btn px-3 py-1.5 text-xs font-medium rounded-lg border border-border bg-white hover:bg-slate-50">6 luni</button>
                </div>
            </div>
            <div class="flex items-end gap-2">
                <label class="block">
                    <span class="text-xs font-semibold text-muted">Grupare</span>
                    <select id="lv-groupby" class="block mt-1 px-3 py-1.5 text-sm border border-border rounded-lg">
                        <option value="day">Pe zi</option>
                        <option value="week">Pe săptămână</option>
                        <option value="month">Pe lună</option>
                    </select>
                </label>
            </div>
            <span id="lv-range-label" class="text-xs text-muted">Ultimele 7 zile</span>
        </div>

        <!-- Stats -->
        <div class="grid grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
            <div class="p-4 bg-white border rounded-2xl border-border">
                <p class="text-xs uppercase tracking-wider text-muted font-semibold mb-1">Total vândut</p>
                <p class="text-2xl font-bold text-secondary"><span id="lv-stat-total">0</span> <span class="text-sm text-muted">RON</span></p>
            </div>
            <div class="p-4 bg-white border rounded-2xl border-border">
                <p class="text-xs uppercase tracking-wider text-muted font-semibold mb-1">Bilete vândute</p>
                <p class="text-2xl font-bold text-secondary"><span id="lv-stat-tickets">0</span></p>
            </div>
            <div class="p-4 bg-white border rounded-2xl border-border">
                <p class="text-xs uppercase tracking-wider text-muted font-semibold mb-1">Comenzi</p>
                <p class="text-2xl font-bold text-secondary"><span id="lv-stat-orders">0</span></p>
            </div>
            <div class="p-4 bg-white border rounded-2xl border-border">
                <p class="text-xs uppercase tracking-wider text-muted font-semibold mb-1">Coș mediu</p>
                <p class="text-2xl font-bold text-secondary"><span id="lv-stat-avg">0</span> <span class="text-sm text-muted">RON</span></p>
            </div>
        </div>

        <!-- Chart + breakdown grid -->
        <div class="grid lg:grid-cols-3 gap-6 mb-6">
            <div class="lg:col-span-2 bg-white border rounded-2xl border-border p-5">
                <h2 class="font-bold text-secondary mb-3">Vânzări în timp</h2>
                <div class="h-64"><canvas id="lv-chart"></canvas></div>
            </div>
            <div class="bg-white border rounded-2xl border-border p-5 space-y-5">
                <div>
                    <h2 class="font-bold text-secondary mb-3">Pe categorii</h2>
                    <div id="lv-categories" class="space-y-2 text-sm">
                        <p class="text-muted text-center py-4">Selectează o perioadă pentru raport.</p>
                    </div>
                </div>
                <div class="pt-4 border-t border-border">
                    <h2 class="font-bold text-secondary mb-3">Pe tip bilet</h2>
                    <div id="lv-ticket-types" class="space-y-2 text-sm max-h-96 overflow-y-auto pr-1">
                        <p class="text-muted text-center py-4">Selectează o perioadă pentru raport.</p>
                    </div>
                </div>
            </div>
        </div>

        <div id="lv-error" class="hidden mt-6 p-4 bg-rose-50 border border-rose-200 rounded-xl text-sm text-rose-900"></div>
    </main>
</div>
<script>
(function(){
    const $ = (id) => document.getElementById(id);
    let chart = null;
    let currentEventId = null;
    let currentFrom = null;
    let currentTo = null;
    let currentGroupBy = 'day';
    let currentDays = '7';

    function fmtMoney(v) {
        return Number(v || 0).toLocaleString('ro-RO', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    }

    function categoryLabel(c) {
        const m = { 'access': 'Acces', 'parking': 'Parcare', 'rental': 'Închirieri', 'activity': 'Activități', 'extra': 'Extra' };
        return m[c] || c;
    }

    function categoryColor(c) {
        const m = { 'access': '#3B82F6', 'parking': '#8B5CF6', 'rental': '#F59E0B', 'activity': '#10B981', 'extra': '#64748B' };
        return m[c] || '#94A3B8';
    }

    function renderChart(rows, groupBy) {
        if (chart) { chart.destroy(); chart = null; }
        const labels = rows.map(r => {
            if (groupBy === 'month') return r.date;
            if (groupBy === 'week')  return r.date;
            try { return new Date(r.date + 'T00:00:00').toLocaleDateString('ro-RO', { day: '2-digit', month: 'short' }); }
            catch { return r.date; }
        });
        const data = rows.map(r => Number(r.revenue || 0));
        const ctx = $('lv-chart').getContext('2d');
        chart = new Chart(ctx, {
            type: 'line',
            data: { labels, datasets: [{ label: 'Vânzări (RON)', data, borderColor: '#22C55E', backgroundColor: 'rgba(34,197,94,0.12)', fill: true, tension: 0.3, pointRadius: 3 }] },
            options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { display: false } }, scales: { y: { beginAtZero: true } } }
        });
    }

    // Breakdown per tip bilet (nume + count + venit + procent din total)
    function renderTicketTypes(rows) {
        const wrap = $('lv-ticket-types');
        if (!rows || !rows.length) {
            wrap.innerHTML = '<p class="text-muted text-center py-4">Nicio vânzare în această perioadă.</p>';
            return;
        }
        const totalTickets = rows.reduce((s, r) => s + Number(r.tickets || 0), 0);
        wrap.innerHTML = rows.map(r => {
            const pct = totalTickets > 0 ? Math.round((r.tickets / totalTickets) * 100) : 0;
            const color = categoryColor(r.service_category || 'access');
            const rev = Number(r.revenue || 0);
            const safeName = String(r.name || 'Bilet').replace(/[<>&]/g, s => ({'<':'&lt;','>':'&gt;','&':'&amp;'}[s]));
            return `
            <div>
                <div class="flex justify-between text-xs mb-1 gap-2">
                    <span class="font-medium truncate" style="color:${color}" title="${safeName}">${safeName}</span>
                    <span class="text-muted whitespace-nowrap">${r.tickets} × · ${fmtMoney(rev)} RON</span>
                </div>
                <div class="h-2 bg-slate-100 rounded-full overflow-hidden">
                    <div class="h-full rounded-full" style="width:${pct}%;background:${color}"></div>
                </div>
            </div>`;
        }).join('');
    }

    function renderCategories(byCat) {
        const wrap = $('lv-categories');
        const entries = Object.entries(byCat || {});
        if (!entries.length) {
            wrap.innerHTML = '<p class="text-muted text-center py-4">Nicio vânzare în această perioadă.</p>';
            return;
        }
        const total = entries.reduce((s, [, n]) => s + Number(n), 0);
        wrap.innerHTML = entries
            .sort((a, b) => b[1] - a[1])
            .map(([cat, n]) => {
                const pct = total > 0 ? Math.round((n / total) * 100) : 0;
                const color = categoryColor(cat);
                return `
                <div>
                    <div class="flex justify-between text-xs mb-1">
                        <span class="font-medium" style="color:${color}">${categoryLabel(cat)}</span>
                        <span class="text-muted">${n} bilete · ${pct}%</span>
                    </div>
                    <div class="h-2 bg-slate-100 rounded-full overflow-hidden">
                        <div class="h-full rounded-full" style="width:${pct}%;background:${color}"></div>
                    </div>
                </div>`;
            })
            .join('');
    }

    function setRange(days) {
        currentDays = days;
        document.querySelectorAll('.lv-range-btn').forEach(b => b.classList.remove('bg-primary', 'text-white', 'border-primary'));
        const btn = document.querySelector(`.lv-range-btn[data-range="${days}"]`);
        if (btn) btn.classList.add('bg-primary', 'text-white', 'border-primary');
        const labels = { '7': '7 zile', '14': '14 zile', '30': '1 lună', '90': '3 luni', '180': '6 luni' };
        $('lv-range-label').textContent = 'Ultimele ' + (labels[days] || days);
        const to = new Date();
        const from = new Date(Date.now() - parseInt(days, 10) * 86400000);
        currentFrom = from.toISOString().slice(0, 10);
        currentTo = to.toISOString().slice(0, 10);
        loadTimeline();
    }

    async function loadTimeline() {
        $('lv-error').classList.add('hidden');
        if (!currentEventId) {
            $('lv-error').textContent = 'Nu există un eveniment de tip Locație de agrement asociat.';
            $('lv-error').classList.remove('hidden');
            return;
        }
        try {
            const res = await AmbiletAPI.get(`/organizer/events/${currentEventId}/leisure/sales-timeline`, {
                from: currentFrom,
                to: currentTo,
                group_by: currentGroupBy,
            });
            const data = res.data || {};
            const totals = data.totals || {};
            $('lv-stat-total').textContent = fmtMoney(totals.revenue);
            $('lv-stat-tickets').textContent = totals.tickets || 0;
            $('lv-stat-orders').textContent = totals.orders || 0;
            $('lv-stat-avg').textContent = fmtMoney(totals.avg_order);
            renderChart(data.rows || [], data.group_by || 'day');
            renderCategories(data.by_category || {});
            renderTicketTypes(data.by_ticket_type || []);
        } catch (e) {
            console.error('[leisure-sales] load failed', e);
            $('lv-error').textContent = 'Eroare la încărcarea datelor: ' + (e?.message || 'necunoscut');
            $('lv-error').classList.remove('hidden');
        }
    }

    window.addEventListener('load', async () => {
        let retries = 0;
        while (typeof AmbiletAPI === 'undefined' && retries < 10) { await new Promise(r => setTimeout(r, 100)); retries++; }
        if (typeof AmbiletAPI === 'undefined' || typeof Chart === 'undefined') {
            $('lv-error').textContent = 'Resurse JS indisponibile — reîncarcă pagina.';
            $('lv-error').classList.remove('hidden');
            return;
        }
        try {
            const res = await AmbiletAPI.get('/organizer/events');
            const events = res.data || [];
            const leisure = events.filter(e => (e.display_template || 'standard') === 'leisure_venue');
            if (leisure.length > 0) currentEventId = leisure[0].id;
        } catch (e) { console.error(e); }

        document.querySelectorAll('.lv-range-btn').forEach(b => b.addEventListener('click', () => setRange(b.dataset.range)));
        $('lv-groupby').addEventListener('change', (e) => { currentGroupBy = e.target.value; loadTimeline(); });
        setRange('7');
    });
})();
</script>
<?php
require_once dirname(__DIR__) . '/includes/scripts.php';
?>
