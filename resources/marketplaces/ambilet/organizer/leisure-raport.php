<?php
require_once dirname(__DIR__) . '/includes/config.php';
$pageTitle = 'Raport';
$bodyClass = 'min-h-screen flex bg-slate-100';
$currentPage = 'leisure_raport';
$cssBundle = 'organizer';
require_once dirname(__DIR__) . '/includes/head.php';
require_once dirname(__DIR__) . '/includes/organizer-sidebar.php';
?>
<div class="flex flex-col flex-1 min-h-screen lg:ml-0">
    <?php require_once dirname(__DIR__) . '/includes/organizer-topbar.php'; ?>
    <main class="flex-1 p-4 lg:p-8">
        <div class="mb-6">
            <h1 class="text-2xl font-bold text-secondary lg:text-3xl">📑 Raport</h1>
            <p class="mt-1 text-sm text-muted">Vânzări, bilete și venit per perioadă, tip bilet, operator POS și sursă (fizic vs online).</p>
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
        <div class="grid grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
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

        <div class="bg-white border rounded-2xl border-border">
            <div class="px-5 py-3 border-b border-border flex items-center justify-between">
                <h2 class="font-bold text-secondary">Per tip bilet</h2>
                <span class="text-xs text-muted">Detaliu produse</span>
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
                        </tr>
                    </thead>
                    <tbody id="r-tt-rows" class="divide-y divide-border">
                        <tr><td class="px-5 py-6 text-center text-muted" colspan="5">—</td></tr>
                    </tbody>
                </table>
            </div>
        </div>
    </main>
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
            renderSources(data.by_source || [], t.revenue || 0);
            renderCashiers(data.by_cashier || []);
            renderTicketTypes(data.by_ticket_type || []);
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

    function renderCashiers(rows) {
        const wrap = $('r-cashier-rows');
        if (!rows.length) { wrap.innerHTML = '<p class="text-sm text-muted text-center py-6">—</p>'; return; }
        wrap.innerHTML = rows.map(r => `
            <div class="flex items-center justify-between py-2 border-b border-border last:border-b-0">
                <div class="text-sm">${r.cashier_label || '—'}</div>
                <div class="text-right text-xs text-muted">
                    <span class="font-bold text-secondary">${fmtMoney(r.revenue)} RON</span>
                    <div>${r.orders} comenzi · ${r.tickets} bilete</div>
                </div>
            </div>
        `).join('');
    }

    function renderTicketTypes(rows) {
        const tbody = $('r-tt-rows');
        if (!rows.length) { tbody.innerHTML = '<tr><td class="px-5 py-6 text-center text-muted" colspan="5">Nicio vânzare în perioadă.</td></tr>'; return; }
        rows.sort((a, b) => b.revenue - a.revenue);
        tbody.innerHTML = rows.map(r => {
            const issuer = r.issuing_company === 'secondary' ? 'SC2' : 'SC1';
            return `<tr class="hover:bg-slate-50">
                <td class="px-5 py-3 font-medium text-secondary">${(typeof r.name === 'string' ? r.name : (r.name?.ro || '—'))}</td>
                <td class="px-5 py-3 text-xs">${CAT_LABEL[r.service_category] || r.service_category}</td>
                <td class="px-5 py-3 text-xs">${issuer}</td>
                <td class="px-5 py-3 text-right">${r.tickets}</td>
                <td class="px-5 py-3 text-right font-semibold">${fmtMoney(r.revenue)} RON</td>
            </tr>`;
        }).join('');
    }

    function exportCsv() {
        if (!lastReport) { alert('Încarcă mai întâi raportul.'); return; }
        const rows = [];
        rows.push(['Raport perioadă', lastReport.from, '→', lastReport.to]);
        rows.push([]);
        rows.push(['TOTALURI']);
        rows.push(['Comenzi', lastReport.totals?.orders || 0]);
        rows.push(['Bilete', lastReport.totals?.tickets || 0]);
        rows.push(['Venit', lastReport.totals?.revenue || 0, 'RON']);
        rows.push(['Coș mediu', lastReport.totals?.avg_order || 0, 'RON']);
        rows.push([]);
        rows.push(['SURSĂ', 'Comenzi', 'Bilete', 'Venit']);
        (lastReport.by_source || []).forEach(s => rows.push([s.source, s.orders, s.tickets, s.revenue]));
        rows.push([]);
        rows.push(['OPERATOR', 'Comenzi', 'Bilete', 'Venit']);
        (lastReport.by_cashier || []).forEach(c => rows.push([c.cashier_label, c.orders, c.tickets, c.revenue]));
        rows.push([]);
        rows.push(['TIP BILET', 'Categorie', 'Emitent', 'Bilete', 'Venit']);
        (lastReport.by_ticket_type || []).forEach(t => rows.push([typeof t.name === 'string' ? t.name : (t.name?.ro || ''), t.service_category, t.issuing_company, t.tickets, t.revenue]));
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
        setRange('30');
    });
})();
</script>
<?php
require_once dirname(__DIR__) . '/includes/scripts.php';
?>
