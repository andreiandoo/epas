<?php
require_once dirname(__DIR__) . '/includes/config.php';
$pageTitle = 'Raport';
$bodyClass = 'min-h-screen flex bg-slate-100';
$currentPage = 'leisure_raport';
$cssBundle = 'organizer';
$headExtra = '<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>';
require_once dirname(__DIR__) . '/includes/head.php';
require_once dirname(__DIR__) . '/includes/organizer-sidebar.php';
?>
<div class="flex flex-col flex-1 min-h-screen lg:ml-0">
    <?php require_once dirname(__DIR__) . '/includes/organizer-topbar.php'; ?>
    <main class="flex-1 p-4 lg:p-8">
        <div class="mb-6 flex flex-wrap items-start justify-between gap-3">
            <div>
                <h1 class="text-2xl font-bold text-secondary lg:text-3xl">📑 Raport</h1>
                <p class="mt-1 text-sm text-muted">Vânzări, bilete și venit per perioadă, tip bilet, operator POS și sursă (fizic vs online).</p>
            </div>
            <a href="/organizator/staff-raport" class="inline-flex items-center gap-2 px-4 py-2 text-sm font-semibold border border-secondary text-secondary rounded-lg hover:bg-secondary hover:text-white transition-colors">
                👥 Raport activitate angajați →
            </a>
        </div>

        <!-- Filtre perioadă -->
        <div class="bg-white border rounded-2xl border-border p-4 mb-6 flex flex-wrap items-end gap-3">
            <div>
                <p class="text-xs uppercase tracking-wider text-muted font-semibold mb-2">Perioadă rapidă</p>
                <div class="flex flex-wrap gap-2">
                    <button data-range="7" class="lv-range-btn px-3 py-1.5 text-xs font-medium rounded-lg border border-border bg-white hover:bg-slate-50">7 zile</button>
                    <button data-range="14" class="lv-range-btn px-3 py-1.5 text-xs font-medium rounded-lg border border-border bg-white hover:bg-slate-50">14 zile</button>
                    <button data-range="30" class="lv-range-btn px-3 py-1.5 text-xs font-medium rounded-lg border border-border bg-white hover:bg-slate-50">1 lună</button>
                    <button data-range="90" class="lv-range-btn px-3 py-1.5 text-xs font-medium rounded-lg border border-border bg-white hover:bg-slate-50">3 luni</button>
                    <button data-range="180" class="lv-range-btn px-3 py-1.5 text-xs font-medium rounded-lg border border-border bg-white hover:bg-slate-50">6 luni</button>
                    <button data-range="365" class="lv-range-btn px-3 py-1.5 text-xs font-medium rounded-lg border border-border bg-white hover:bg-slate-50">1 an</button>
                </div>
            </div>
            <label class="block">
                <span class="text-xs font-semibold text-muted uppercase tracking-wider">De la</span>
                <input id="r-from" type="date" class="block mt-1 px-3 py-1.5 text-sm border border-border rounded-lg">
            </label>
            <label class="block">
                <span class="text-xs font-semibold text-muted uppercase tracking-wider">Până la</span>
                <input id="r-to" type="date" class="block mt-1 px-3 py-1.5 text-sm border border-border rounded-lg">
            </label>
            <button id="r-apply" class="px-4 py-1.5 bg-primary text-white text-sm font-semibold rounded-lg hover:bg-primary-dark">Aplică</button>
            <div class="ml-auto">
                <button id="r-export" class="px-3 py-1.5 text-xs font-medium bg-white border border-border rounded-lg hover:bg-slate-50">📥 Export CSV</button>
            </div>
        </div>

        <div id="r-error" class="hidden mb-4 p-4 bg-rose-50 border border-rose-200 rounded-xl text-sm text-rose-900"></div>
        <div id="r-loading" class="hidden p-8 text-center"><div class="inline-block w-6 h-6 border-2 rounded-full border-primary border-t-transparent animate-spin"></div></div>

        <!-- Totaluri -->
        <div class="grid grid-cols-2 lg:grid-cols-4 gap-4 mb-4">
            <div class="p-4 bg-white border rounded-2xl border-border">
                <p class="text-xs uppercase tracking-wider text-muted font-semibold mb-1">Venit total</p>
                <p class="text-2xl font-bold text-secondary"><span id="r-total-revenue">0.00</span> <span class="text-sm text-muted">RON</span></p>
            </div>
            <div class="p-4 bg-white border rounded-2xl border-border">
                <p class="text-xs uppercase tracking-wider text-muted font-semibold mb-1">Bilete</p>
                <p class="text-2xl font-bold text-secondary"><span id="r-total-tickets">0</span></p>
            </div>
            <div class="p-4 bg-white border rounded-2xl border-border">
                <p class="text-xs uppercase tracking-wider text-muted font-semibold mb-1">Comenzi</p>
                <p class="text-2xl font-bold text-secondary"><span id="r-total-orders">0</span></p>
            </div>
            <div class="p-4 bg-white border rounded-2xl border-border">
                <p class="text-xs uppercase tracking-wider text-muted font-semibold mb-1">Coș mediu</p>
                <p class="text-2xl font-bold text-secondary"><span id="r-avg">0.00</span> <span class="text-sm text-muted">RON</span></p>
            </div>
        </div>

        <!-- Comision AmBilet (colored blue accent) -->
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-4 mb-6">
            <div class="p-4 bg-blue-50 border border-blue-200 rounded-2xl lg:col-span-2">
                <div class="flex items-start justify-between gap-3">
                    <div>
                        <p class="text-xs uppercase tracking-wider text-blue-800 font-semibold mb-1 flex items-center gap-1.5">
                            💰 Comision AmBilet
                        </p>
                        <p class="text-2xl font-bold text-blue-900"><span id="r-total-commission">0.00</span> <span class="text-sm text-blue-700">RON</span></p>
                    </div>
                    <div class="w-11 h-11 bg-blue-100 rounded-xl flex items-center justify-center flex-shrink-0">
                        <svg class="w-6 h-6 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                    </div>
                </div>
            </div>
            <div class="p-4 bg-emerald-50 border border-emerald-200 rounded-2xl">
                <p class="text-xs uppercase tracking-wider text-emerald-800 font-semibold mb-1">Venit net (după comision)</p>
                <p class="text-2xl font-bold text-emerald-900"><span id="r-net-revenue">0.00</span> <span class="text-sm text-emerald-700">RON</span></p>
            </div>
        </div>

        <!-- Panou: Pe metoda plata -->
        <div class="bg-white border rounded-2xl border-border mb-6">
            <div class="px-5 py-3 border-b border-border flex items-center justify-between">
                <h2 class="font-bold text-secondary">💰 Pe metodă plată</h2>
                <span class="text-xs text-muted">Cash / Card / Online</span>
            </div>
            <div class="p-5" id="r-payment-rows">
                <p class="text-sm text-muted text-center py-6">Selectează o perioadă.</p>
            </div>
        </div>

        <!-- Grid: surse + cashiers + tipuri bilete -->
        <div class="grid lg:grid-cols-2 gap-6 mb-6">
            <div class="bg-white border rounded-2xl border-border">
                <div class="px-5 py-3 border-b border-border flex items-center justify-between">
                    <h2 class="font-bold text-secondary">Vânzări fizice vs online</h2>
                    <span class="text-xs text-muted">Sursă</span>
                </div>
                <div class="p-5" id="r-source-rows">
                    <p class="text-sm text-muted text-center py-6">Selectează o perioadă.</p>
                </div>
            </div>
            <div class="bg-white border rounded-2xl border-border">
                <div class="px-5 py-3 border-b border-border flex items-center justify-between">
                    <h2 class="font-bold text-secondary">Per operator POS</h2>
                    <span class="text-xs text-muted">Cashier</span>
                </div>
                <div class="p-5" id="r-cashier-rows">
                    <p class="text-sm text-muted text-center py-6">—</p>
                </div>
            </div>
        </div>

        <div class="bg-white border rounded-2xl border-border mb-6">
            <div class="px-5 py-3 border-b border-border flex items-center justify-between">
                <h2 class="font-bold text-secondary">Per tip bilet</h2>
                <span class="text-xs text-muted">Tranzacții (pachete + bilete individuale)</span>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead class="text-xs uppercase bg-slate-50 text-muted">
                        <tr>
                            <th class="px-5 py-3 text-left">Bilet</th>
                            <th class="px-5 py-3 text-left">Categorie</th>
                            <th class="px-5 py-3 text-left">Emitent</th>
                            <th class="px-5 py-3 text-right">Bilete</th>
                            <th class="px-5 py-3 text-right">Venit</th>
                            <th class="px-5 py-3 text-right text-blue-700">Comision</th>
                        </tr>
                    </thead>
                    <tbody id="r-tt-rows" class="divide-y divide-border">
                        <tr><td class="px-5 py-6 text-center text-muted" colspan="6">—</td></tr>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Bilete FIZICE emise (componente pachet + individuale). Fara pachetele parinte. -->
        <div class="bg-white border rounded-2xl border-border">
            <div class="px-5 py-3 border-b border-border flex items-center justify-between">
                <h2 class="font-bold text-secondary">🎟️ Pe tip bilet emis</h2>
                <span class="text-xs text-muted">Bilete fizice (componente pachet + individuale)</span>
            </div>
            <div class="px-5 py-3 bg-slate-50 border-b border-border text-xs text-muted">
                Total bilete emise: <strong id="r-physical-total" class="text-secondary">0</strong> · Include componentele pachetelor emise (Adult, Copil, etc.) fără biletele-parinte pachet.
            </div>
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead class="text-xs uppercase bg-slate-50 text-muted">
                        <tr>
                            <th class="px-5 py-3 text-left">Bilet</th>
                            <th class="px-5 py-3 text-left">Categorie</th>
                            <th class="px-5 py-3 text-right">Bucăți emise</th>
                        </tr>
                    </thead>
                    <tbody id="r-comp-rows" class="divide-y divide-border">
                        <tr><td class="px-5 py-6 text-center text-muted" colspan="3">—</td></tr>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Sectiune Scanari — chart stacked pe zi + modal detalii -->
        <div class="bg-white border rounded-2xl border-border mt-6">
            <div class="px-5 py-3 border-b border-border flex items-center justify-between flex-wrap gap-2">
                <h2 class="font-bold text-secondary">📡 Scanări</h2>
                <div class="flex items-center gap-3 text-xs">
                    <span class="inline-flex items-center gap-1"><span class="w-3 h-3 rounded-sm bg-slate-300"></span> Așteptate</span>
                    <span class="inline-flex items-center gap-1"><span class="w-3 h-3 rounded-sm bg-emerald-500"></span> Valide</span>
                    <span class="inline-flex items-center gap-1"><span class="w-3 h-3 rounded-sm bg-sky-500"></span> Angajați</span>
                    <span class="inline-flex items-center gap-1"><span class="w-3 h-3 rounded-sm bg-rose-500"></span> Invalide</span>
                </div>
            </div>
            <div class="px-5 py-3 bg-slate-50 border-b border-border text-xs text-muted">
                Bara <strong class="text-slate-700">gri</strong> = câte scanări sunt așteptate în ziua respectivă (bilete de acces cu visit_date = ziua).
                Culorile stacked = scanările efective: <strong class="text-emerald-700">valide</strong>, <strong class="text-sky-700">angajați</strong>, <strong class="text-rose-700">invalide</strong>. Click pe o zi pentru detalii.
            </div>
            <div class="p-5">
                <div class="h-72"><canvas id="r-scans-chart"></canvas></div>
            </div>
        </div>
    </main>

    <!-- Modal Detalii scanari pe zi -->
    <div id="r-scans-modal" class="hidden fixed inset-0 bg-black/50 z-50 items-start justify-center p-4 md:p-10 overflow-y-auto">
        <div class="bg-white rounded-2xl border border-border max-w-4xl w-full my-6 shadow-xl">
            <div class="flex items-center justify-between px-6 py-4 border-b border-border">
                <div>
                    <h3 class="font-bold text-lg text-secondary">📡 Detalii scanări</h3>
                    <p id="r-scans-modal-date" class="text-xs text-muted mt-0.5">—</p>
                </div>
                <button type="button" id="r-scans-modal-close" class="text-muted hover:text-secondary text-2xl leading-none">×</button>
            </div>
            <div class="px-6 py-4">
                <div id="r-scans-modal-totals" class="mb-3 text-sm text-muted">—</div>
                <div class="overflow-x-auto max-h-[60vh]">
                    <table class="w-full text-sm">
                        <thead class="text-xs uppercase bg-slate-50 text-muted sticky top-0">
                            <tr>
                                <th class="px-3 py-2 text-left">Ora</th>
                                <th class="px-3 py-2 text-left">Nume</th>
                                <th class="px-3 py-2 text-left">Status</th>
                                <th class="px-3 py-2 text-left">Cod</th>
                                <th class="px-3 py-2 text-left">Email</th>
                                <th class="px-3 py-2 text-left">Detaliu</th>
                            </tr>
                        </thead>
                        <tbody id="r-scans-modal-rows" class="divide-y divide-border">
                            <tr><td class="px-3 py-6 text-center text-muted" colspan="6">Se încarcă...</td></tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>
<script>
(function(){
    const $ = (id) => document.getElementById(id);
    const fmtMoney = (v) => Number(v || 0).toLocaleString('ro-RO', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    const CAT_LABEL = { access: 'Acces', parking: 'Parcare', rental: 'Închiriere', activity: 'Activitate', extra: 'Extra' };

    let currentEventId = null;
    let lastReport = null;

    function setRange(days) {
        const to = new Date();
        const from = new Date(Date.now() - parseInt(days, 10) * 86400000);
        $('r-from').value = from.toISOString().slice(0,10);
        $('r-to').value = to.toISOString().slice(0,10);
        document.querySelectorAll('.lv-range-btn').forEach(b => b.classList.remove('bg-primary', 'text-white', 'border-primary'));
        const btn = document.querySelector(`.lv-range-btn[data-range="${days}"]`);
        if (btn) btn.classList.add('bg-primary', 'text-white', 'border-primary');
        loadReport();
    }

    async function loadReport() {
        if (!currentEventId) return;
        $('r-error').classList.add('hidden');
        $('r-loading').classList.remove('hidden');
        try {
            const res = await AmbiletAPI.get(`/organizer/events/${currentEventId}/leisure/raport`, {
                from: $('r-from').value, to: $('r-to').value
            });
            const data = res.data || {};
            lastReport = data;
            const t = data.totals || {};
            $('r-total-revenue').textContent = fmtMoney(t.revenue);
            $('r-total-tickets').textContent = t.tickets || 0;
            $('r-total-orders').textContent = t.orders || 0;
            $('r-avg').textContent = fmtMoney(t.avg_order);
            // Comision AmBilet
            $('r-total-commission').textContent = fmtMoney(t.commission);
            $('r-net-revenue').textContent = fmtMoney(t.net_revenue);
            renderSources(data.by_source || [], t.revenue || 0);
            renderPaymentMethods(data.by_payment_method || [], t.revenue || 0);
            renderCashiers(data.by_cashier || []);
            renderTicketTypes(data.by_ticket_type || []);
            renderComponents(data.by_component_type || [], data.total_physical_tickets || 0);
            loadScansChart();
        } catch (e) {
            console.error('[raport] load', e);
            $('r-error').textContent = 'Eroare: ' + (e?.message || '');
            $('r-error').classList.remove('hidden');
        } finally {
            $('r-loading').classList.add('hidden');
        }
    }

    function renderSources(rows, totalRev) {
        const wrap = $('r-source-rows');
        if (!rows.length) { wrap.innerHTML = '<p class="text-sm text-muted text-center py-6">Nicio vânzare în perioadă.</p>'; return; }
        wrap.innerHTML = rows.map(r => {
            const pct = totalRev > 0 ? Math.round((r.revenue / totalRev) * 100) : 0;
            const isPos = r.source === 'pos';
            const color = isPos ? 'emerald' : 'blue';
            const label = isPos ? '🏪 Fizic (POS)' : '🌐 Online';
            return `<div class="mb-3">
                <div class="flex justify-between text-sm mb-1">
                    <span class="font-semibold text-${color}-800">${label}</span>
                    <span class="text-muted">${r.orders} comenzi · ${r.tickets} bilete · <strong class="text-${color}-700">${fmtMoney(r.revenue)} RON</strong></span>
                </div>
                <div class="h-2 bg-slate-100 rounded-full overflow-hidden"><div class="h-full bg-${color}-500" style="width:${pct}%"></div></div>
                <div class="text-[10px] text-muted mt-0.5 text-right">${pct}% din total</div>
            </div>`;
        }).join('');
    }

    // Breakdown per metoda plata: Cash / Card / Online
    const PM_META = {
        cash:   { label: '💵 Cash',   color: 'amber',    hex: '#F59E0B' },
        card:   { label: '💳 Card',   color: 'indigo',   hex: '#6366F1' },
        online: { label: '🌐 Online', color: 'emerald',  hex: '#10B981' },
    };
    function renderPaymentMethods(rows, totalRev) {
        const wrap = $('r-payment-rows');
        if (!rows.length) { wrap.innerHTML = '<p class="text-sm text-muted text-center py-6">Nicio vânzare în perioadă.</p>'; return; }
        wrap.innerHTML = rows.map(r => {
            const meta = PM_META[r.method] || { label: r.method, hex: '#94A3B8' };
            const rev = Number(r.revenue || 0);
            const pct = totalRev > 0 ? Math.round((rev / totalRev) * 100) : 0;
            return `<div class="mb-3">
                <div class="flex justify-between text-sm mb-1">
                    <span class="font-semibold" style="color:${meta.hex}">${meta.label}</span>
                    <span class="text-muted">${r.orders} comenzi · ${r.tickets} bilete · <strong style="color:${meta.hex}">${fmtMoney(rev)} RON</strong></span>
                </div>
                <div class="h-2 bg-slate-100 rounded-full overflow-hidden"><div class="h-full" style="width:${pct}%;background:${meta.hex}"></div></div>
                <div class="text-[10px] text-muted mt-0.5 text-right">${pct}% din total</div>
            </div>`;
        }).join('');
    }

    function renderCashiers(rows) {
        const wrap = $('r-cashier-rows');
        if (!rows.length) { wrap.innerHTML = '<p class="text-sm text-muted text-center py-6">—</p>'; return; }
        wrap.innerHTML = rows.map(r => `
            <div class="flex items-center justify-between py-2 border-b border-border last:border-b-0 gap-3">
                <div class="text-sm flex-1 min-w-0 truncate">${r.cashier_label || '—'}</div>
                <div class="text-right text-xs text-muted whitespace-nowrap">
                    <span class="font-bold text-secondary">${fmtMoney(r.revenue)} RON</span>
                    <div>${r.orders} comenzi · ${r.tickets} bilete</div>
                </div>
            </div>
        `).join('');
    }

    // Bilete FIZICE emise (componente pachet + individuale), fara parintele pachet
    function renderComponents(rows, totalPhysical) {
        const totalEl = $('r-physical-total');
        if (totalEl) totalEl.textContent = totalPhysical || 0;
        const tbody = $('r-comp-rows');
        if (!rows.length) { tbody.innerHTML = '<tr><td class="px-5 py-6 text-center text-muted" colspan="3">Niciun bilet emis în perioadă.</td></tr>'; return; }
        rows.sort((a, b) => (b.tickets || 0) - (a.tickets || 0));
        tbody.innerHTML = rows.map(r => {
            const catLabel = CAT_LABEL[r.service_category] || r.service_category || '—';
            return `<tr class="hover:bg-slate-50">
                <td class="px-5 py-3 font-medium text-secondary">${(typeof r.name === 'string' ? r.name : (r.name?.ro || '—'))}</td>
                <td class="px-5 py-3 text-xs">${catLabel}</td>
                <td class="px-5 py-3 text-right font-bold text-emerald-800">${r.tickets}</td>
            </tr>`;
        }).join('');
    }

    function renderTicketTypes(rows) {
        const tbody = $('r-tt-rows');
        if (!rows.length) { tbody.innerHTML = '<tr><td class="px-5 py-6 text-center text-muted" colspan="6">Nicio vânzare în perioadă.</td></tr>'; return; }
        rows.sort((a, b) => b.revenue - a.revenue);
        tbody.innerHTML = rows.map(r => {
            const issuer = r.issuing_company === 'secondary' ? 'SC2' : 'SC1';
            return `<tr class="hover:bg-slate-50">
                <td class="px-5 py-3 font-medium text-secondary">${(typeof r.name === 'string' ? r.name : (r.name?.ro || '—'))}</td>
                <td class="px-5 py-3 text-xs">${CAT_LABEL[r.service_category] || r.service_category}</td>
                <td class="px-5 py-3 text-xs">${issuer}</td>
                <td class="px-5 py-3 text-right">${r.tickets}</td>
                <td class="px-5 py-3 text-right font-semibold">${fmtMoney(r.revenue)} RON</td>
                <td class="px-5 py-3 text-right text-blue-700 font-semibold">${fmtMoney(r.commission || 0)} RON</td>
            </tr>`;
        }).join('');
    }

    // ============ Sectiune Scanari (chart stacked + modal detalii) ============
    let scansChart = null;
    let scansRows = [];

    async function loadScansChart() {
        if (!currentEventId) return;
        try {
            const res = await AmbiletAPI.get(`/organizer/events/${currentEventId}/leisure/scans`, {
                from: $('r-from').value, to: $('r-to').value,
            });
            const data = res.data || {};
            scansRows = data.rows || [];
            renderScansChart(scansRows);
        } catch (e) {
            console.error('[scans] load', e);
        }
    }

    function renderScansChart(rows) {
        const ctx = $('r-scans-chart');
        if (!ctx) return;
        const labels = rows.map(r => {
            try { return new Date(r.date + 'T00:00:00').toLocaleDateString('ro-RO', { day:'2-digit', month:'short' }); }
            catch { return r.date; }
        });
        const cfg = {
            type: 'bar',
            data: {
                labels: labels,
                datasets: [
                    { label: 'Așteptate', data: rows.map(r => r.expected || 0), backgroundColor: '#CBD5E1', stack: 'expected' },
                    { label: 'Valide', data: rows.map(r => r.valid || 0), backgroundColor: '#10B981', stack: 'actual' },
                    { label: 'Angajați', data: rows.map(r => r.staff || 0), backgroundColor: '#0EA5E9', stack: 'actual' },
                    { label: 'Invalide', data: rows.map(r => r.invalid || 0), backgroundColor: '#F43F5E', stack: 'actual' },
                ],
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: { legend: { display: false }, tooltip: { mode: 'index', intersect: false } },
                scales: {
                    x: { stacked: true, ticks: { autoSkip: true, maxRotation: 45 } },
                    y: { stacked: true, beginAtZero: true, ticks: { precision: 0 } },
                },
                onClick: (evt, elements) => {
                    if (!elements.length) return;
                    const idx = elements[0].index;
                    const row = rows[idx];
                    if (row) openScansModal(row.date);
                },
            },
        };
        if (scansChart) { scansChart.destroy(); scansChart = null; }
        scansChart = new Chart(ctx.getContext('2d'), cfg);
    }

    async function openScansModal(date) {
        const modal = $('r-scans-modal');
        modal.classList.remove('hidden'); modal.classList.add('flex');
        $('r-scans-modal-date').textContent = new Date(date + 'T00:00:00').toLocaleDateString('ro-RO', { day:'2-digit', month:'long', year:'numeric' });
        $('r-scans-modal-rows').innerHTML = '<tr><td class="px-3 py-6 text-center text-muted" colspan="6">Se încarcă...</td></tr>';
        try {
            const res = await AmbiletAPI.get(`/organizer/events/${currentEventId}/leisure/scans-detail`, { date });
            const data = res.data || {};
            const items = data.items || [];
            const totals = data.totals || {};
            $('r-scans-modal-totals').innerHTML = `<strong class="text-emerald-700">${totals.valid || 0} valide</strong> · <strong class="text-sky-700">${totals.staff || 0} angajați</strong> · <strong class="text-rose-700">${totals.invalid || 0} invalide</strong> · Total: <strong>${items.length}</strong>`;
            if (!items.length) {
                $('r-scans-modal-rows').innerHTML = '<tr><td class="px-3 py-6 text-center text-muted" colspan="6">Nicio scanare înregistrată în această zi.</td></tr>';
                return;
            }
            $('r-scans-modal-rows').innerHTML = items.map(it => {
                const escapeHtml = (s) => String(s || '').replace(/[<>&]/g, c => ({'<':'&lt;','>':'&gt;','&':'&amp;'}[c]));
                let statusPill = '';
                if (it.type === 'ticket_valid') statusPill = '<span class="inline-block px-2 py-0.5 text-[10px] font-semibold rounded-full bg-emerald-100 text-emerald-800">✓ VALID</span>';
                else if (it.type === 'staff') statusPill = '<span class="inline-block px-2 py-0.5 text-[10px] font-semibold rounded-full bg-sky-100 text-sky-800">👷 ANGAJAT</span>';
                else if (it.type === 'invalid') {
                    const label = it.status === 'duplicate' ? '⚠ DUPLICAT' : '✕ INVALID';
                    statusPill = `<span class="inline-block px-2 py-0.5 text-[10px] font-semibold rounded-full bg-rose-100 text-rose-800">${label}</span>`;
                }
                return `<tr class="hover:bg-slate-50">
                    <td class="px-3 py-2 text-xs font-mono">${escapeHtml(it.time)}</td>
                    <td class="px-3 py-2">${escapeHtml(it.name)}</td>
                    <td class="px-3 py-2">${statusPill}</td>
                    <td class="px-3 py-2 text-xs font-mono">${escapeHtml(it.code || '')}</td>
                    <td class="px-3 py-2 text-xs">${escapeHtml(it.email || '')}</td>
                    <td class="px-3 py-2 text-xs text-muted">${escapeHtml(it.ticket_type || '')}</td>
                </tr>`;
            }).join('');
        } catch (e) {
            $('r-scans-modal-rows').innerHTML = '<tr><td class="px-3 py-6 text-center text-rose-700" colspan="6">Eroare: ' + (e?.message || '') + '</td></tr>';
        }
    }

    function closeScansModal() {
        const modal = $('r-scans-modal');
        modal.classList.add('hidden'); modal.classList.remove('flex');
    }

    function exportCsv() {
        if (!lastReport) { alert('Încarcă mai întâi raportul.'); return; }
        const rows = [];
        rows.push(['Raport perioadă', lastReport.from, '→', lastReport.to]);
        rows.push([]);
        rows.push(['TOTALURI']);
        rows.push(['Comenzi', lastReport.totals?.orders || 0]);
        rows.push(['Bilete', lastReport.totals?.tickets || 0]);
        rows.push(['Venit brut', lastReport.totals?.revenue || 0, 'RON']);
        rows.push(['Comision AmBilet', lastReport.totals?.commission || 0, 'RON']);
        rows.push(['Venit net (dupa comision)', lastReport.totals?.net_revenue || 0, 'RON']);
        rows.push(['Rata comision efectiva %', lastReport.totals?.commission_pct_effective || 0]);
        rows.push(['Coș mediu', lastReport.totals?.avg_order || 0, 'RON']);
        rows.push([]);
        rows.push(['COMISION CONFIG']);
        rows.push(['Formula', (lastReport.commission_config?.formula || '—')]);
        rows.push(['Rata %', lastReport.commission_config?.rate || 0]);
        rows.push(['Floor per bilet (lei)', lastReport.commission_config?.floor || 0]);
        rows.push(['Mod', lastReport.commission_config?.mode || 'included']);
        rows.push([]);
        rows.push(['SURSĂ', 'Comenzi', 'Bilete', 'Venit', 'Comision']);
        (lastReport.by_source || []).forEach(s => rows.push([s.source, s.orders, s.tickets, s.revenue, s.commission || 0]));
        rows.push([]);
        rows.push(['METODA PLATA', 'Comenzi', 'Bilete', 'Venit', 'Comision']);
        (lastReport.by_payment_method || []).forEach(p => rows.push([p.method, p.orders, p.tickets, p.revenue, p.commission || 0]));
        rows.push([]);
        rows.push(['OPERATOR', 'Comenzi', 'Bilete', 'Venit', 'Comision']);
        (lastReport.by_cashier || []).forEach(c => rows.push([c.cashier_label, c.orders, c.tickets, c.revenue, c.commission || 0]));
        rows.push([]);
        rows.push(['TIP BILET', 'Categorie', 'Emitent', 'Bilete', 'Venit', 'Comision']);
        (lastReport.by_ticket_type || []).forEach(t => rows.push([typeof t.name === 'string' ? t.name : (t.name?.ro || ''), t.service_category, t.issuing_company, t.tickets, t.revenue, t.commission || 0]));
        rows.push([]);
        rows.push(['BILETE FIZICE EMISE (componente + individuale)', 'Categorie', 'Bucăți']);
        (lastReport.by_component_type || []).forEach(t => rows.push([typeof t.name === 'string' ? t.name : (t.name?.ro || ''), t.service_category, t.tickets]));
        rows.push([]);
        rows.push(['Total bilete fizice emise', lastReport.total_physical_tickets || 0]);
        const csv = rows.map(r => r.map(c => `"${String(c ?? '').replace(/"/g, '""')}"`).join(',')).join('\n');
        const blob = new Blob(["﻿" + csv], { type: 'text/csv;charset=utf-8;' });
        const url = URL.createObjectURL(blob);
        const a = document.createElement('a');
        a.href = url;
        a.download = `raport_${lastReport.from}_${lastReport.to}.csv`;
        a.click();
        URL.revokeObjectURL(url);
    }

    window.addEventListener('load', async () => {
        let retries = 0;
        while (typeof AmbiletAPI === 'undefined' && retries < 10) { await new Promise(r => setTimeout(r, 100)); retries++; }
        if (typeof AmbiletAPI === 'undefined') { $('r-error').textContent = 'API indisponibil.'; $('r-error').classList.remove('hidden'); return; }
        try {
            const res = await AmbiletAPI.get('/organizer/events');
            const events = res.data || [];
            const leisure = events.filter(e => (e.display_template || 'standard') === 'leisure_venue');
            if (leisure.length > 0) currentEventId = leisure[0].id;
        } catch (e) { console.error(e); }
        if (!currentEventId) {
            $('r-error').textContent = 'Nu există un eveniment de tip Locație de agrement.';
            $('r-error').classList.remove('hidden');
            return;
        }
        document.querySelectorAll('.lv-range-btn').forEach(b => b.addEventListener('click', () => setRange(b.dataset.range)));
        $('r-apply').addEventListener('click', loadReport);
        $('r-export').addEventListener('click', exportCsv);
        $('r-scans-modal-close')?.addEventListener('click', closeScansModal);
        setRange('30');
    });
})();
</script>
<?php
require_once dirname(__DIR__) . '/includes/scripts.php';
?>
