<?php
require_once dirname(__DIR__) . '/includes/config.php';
$pageTitle = 'Deconturi';
$bodyClass = 'min-h-screen flex bg-slate-100';
$currentPage = 'leisure_deconturi';
$cssBundle = 'organizer';
require_once dirname(__DIR__) . '/includes/head.php';
require_once dirname(__DIR__) . '/includes/organizer-sidebar.php';
?>

<div class="flex flex-col flex-1 min-h-screen lg:ml-0">
    <?php require_once dirname(__DIR__) . '/includes/organizer-topbar.php'; ?>

    <main class="flex-1 p-4 lg:p-8">
        <div class="mb-6 flex flex-wrap items-start justify-between gap-4">
            <div>
                <h1 class="text-2xl font-bold text-secondary lg:text-3xl">💰 Deconturi</h1>
                <p class="mt-1 text-sm text-muted">Toate deconturile emise pentru acest eveniment, cu detalii per bilet și link către PDF-ul generat.</p>
            </div>
            <button id="dc-refresh" class="px-3 py-1.5 text-xs font-medium bg-white border border-border rounded-lg hover:bg-slate-50">🔄 Reîmprospătează</button>
        </div>

        <div id="dc-error" class="hidden mb-4 p-4 bg-rose-50 border border-rose-200 rounded-xl text-sm text-rose-900"></div>
        <div id="dc-loading" class="hidden p-8 text-center"><div class="inline-block w-6 h-6 border-2 rounded-full border-primary border-t-transparent animate-spin"></div></div>

        <!-- Sumar total (toate deconturile) -->
        <div class="grid grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
            <div class="p-4 bg-white border rounded-2xl border-border">
                <p class="text-xs uppercase tracking-wider text-muted font-semibold mb-1">Deconturi</p>
                <p class="text-2xl font-bold text-secondary"><span id="dc-total-count">0</span></p>
            </div>
            <div class="p-4 bg-white border rounded-2xl border-border">
                <p class="text-xs uppercase tracking-wider text-muted font-semibold mb-1">Total încasat (net)</p>
                <p class="text-2xl font-bold text-secondary"><span id="dc-total-net">0.00</span> <span class="text-sm text-muted">RON</span></p>
            </div>
            <div class="p-4 bg-white border rounded-2xl border-border">
                <p class="text-xs uppercase tracking-wider text-muted font-semibold mb-1">Comision cumulat</p>
                <p class="text-2xl font-bold text-blue-800"><span id="dc-total-commission">0.00</span> <span class="text-sm text-blue-700">RON</span></p>
            </div>
            <div class="p-4 bg-white border rounded-2xl border-border">
                <p class="text-xs uppercase tracking-wider text-muted font-semibold mb-1">Venit brut cumulat</p>
                <p class="text-2xl font-bold text-secondary"><span id="dc-total-gross">0.00</span> <span class="text-sm text-muted">RON</span></p>
            </div>
        </div>

        <!-- Decontare (compensare AmBilet <-> locație) pe perioade de 2 săptămâni -->
        <section class="mb-6 overflow-hidden bg-white border rounded-2xl border-border">
            <div class="flex flex-wrap items-center justify-between gap-3 p-4 border-b border-border bg-slate-50">
                <div>
                    <h2 class="font-bold text-secondary">⚖️ Decontare (compensare)</h2>
                    <p class="text-xs text-muted">Online-ul e încasat de AmBilet; POS-ul (cash/card) e încasat în locație. Se compensează comisioanele.</p>
                </div>
                <label class="flex items-center gap-2 text-sm">
                    <span class="text-muted">Perioadă:</span>
                    <select id="dc-settle-period" class="px-3 py-1.5 text-sm bg-white border rounded-lg border-border"></select>
                </label>
            </div>
            <div id="dc-settle-loading" class="hidden p-8 text-center"><div class="inline-block w-5 h-5 border-2 rounded-full border-primary border-t-transparent animate-spin"></div></div>
            <div id="dc-settle-body" class="p-4"></div>
        </section>

        <!-- Tabel deconturi -->
        <div class="bg-white border rounded-2xl border-border overflow-hidden">
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead class="text-xs uppercase bg-slate-50 text-muted">
                        <tr>
                            <th class="px-4 py-3 text-left w-8"></th>
                            <th class="px-4 py-3 text-left">Serie decont</th>
                            <th class="px-4 py-3 text-left">Perioadă</th>
                            <th class="px-4 py-3 text-left">Status</th>
                            <th class="px-4 py-3 text-right">Venit brut</th>
                            <th class="px-4 py-3 text-right text-blue-700">Comision</th>
                            <th class="px-4 py-3 text-right">Reduceri</th>
                            <th class="px-4 py-3 text-right font-bold">Net încasat</th>
                            <th class="px-4 py-3 text-center">PDF</th>
                        </tr>
                    </thead>
                    <tbody id="dc-rows" class="divide-y divide-border">
                        <tr><td class="px-4 py-6 text-center text-muted" colspan="9">Se încarcă...</td></tr>
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
    const STATUS_LABEL = {
        pending: 'În așteptare',
        approved: 'Aprobat',
        processing: 'Procesare',
        completed: 'Completat',
        rejected: 'Respins',
        cancelled: 'Anulat',
    };
    const STATUS_COLOR = {
        pending: 'bg-amber-100 text-amber-800',
        approved: 'bg-sky-100 text-sky-800',
        processing: 'bg-indigo-100 text-indigo-800',
        completed: 'bg-emerald-100 text-emerald-800',
        rejected: 'bg-rose-100 text-rose-800',
        cancelled: 'bg-slate-100 text-slate-700',
    };

    let currentEventId = null;
    let openBreakdownIds = new Set(); // ce breakdown-uri sunt expandate

    async function loadPayouts() {
        if (!currentEventId) return;
        $('dc-error').classList.add('hidden');
        $('dc-loading').classList.remove('hidden');
        try {
            const res = await AmbiletAPI.get(`/organizer/events/${currentEventId}/leisure/payouts`);
            const data = res.data || {};
            const payouts = Array.isArray(data.payouts) ? data.payouts : [];
            render(payouts);
        } catch (e) {
            console.error('[deconturi] load', e);
            $('dc-error').textContent = 'Eroare la încărcarea deconturilor: ' + (e?.message || '');
            $('dc-error').classList.remove('hidden');
        } finally {
            $('dc-loading').classList.add('hidden');
        }
    }

    function statusPill(status) {
        const cls = STATUS_COLOR[status] || 'bg-slate-100 text-slate-700';
        const label = STATUS_LABEL[status] || status;
        return `<span class="inline-block px-2 py-0.5 text-[11px] font-medium rounded-full ${cls}">${label}</span>`;
    }

    function escapeHtml(s) { return String(s || '').replace(/[<>&]/g, c => ({'<':'&lt;','>':'&gt;','&':'&amp;'}[c])); }

    function period(p) {
        if (!p.period_start && !p.period_end) return '—';
        const s = p.period_start ? new Date(p.period_start).toLocaleDateString('ro-RO', { day:'2-digit', month:'short', year:'numeric' }) : '—';
        const e = p.period_end ? new Date(p.period_end).toLocaleDateString('ro-RO', { day:'2-digit', month:'short', year:'numeric' }) : '—';
        return s + ' → ' + e;
    }

    function render(payouts) {
        // Sumar
        let cnt = payouts.length;
        let sumNet = 0, sumComm = 0, sumGross = 0;
        payouts.forEach(p => {
            sumNet += Number(p.amount || 0);
            sumComm += Number(p.commission_amount || 0);
            sumGross += Number(p.gross_amount || 0);
        });
        $('dc-total-count').textContent = cnt;
        $('dc-total-net').textContent = fmtMoney(sumNet);
        $('dc-total-commission').textContent = fmtMoney(sumComm);
        $('dc-total-gross').textContent = fmtMoney(sumGross);

        const tbody = $('dc-rows');
        if (!payouts.length) {
            tbody.innerHTML = '<tr><td class="px-4 py-8 text-center text-muted" colspan="9">Nu există deconturi emise pentru acest eveniment.</td></tr>';
            return;
        }

        tbody.innerHTML = payouts.map(p => {
            const isOpen = openBreakdownIds.has(p.id);
            const pdfCell = p.pdf_url
                ? `<a href="${p.pdf_url}" target="_blank" rel="noopener" class="inline-flex items-center gap-1 px-2 py-1 text-xs font-medium bg-emerald-50 text-emerald-800 border border-emerald-200 rounded-lg hover:bg-emerald-100"><svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>PDF</a>`
                : '<span class="text-[11px] text-muted">—</span>';
            const chevron = `<svg class="w-4 h-4 text-muted transition-transform ${isOpen ? 'rotate-90' : ''}" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>`;

            const serieBadge = p.decont_series
                ? `<span class="font-bold text-secondary">${escapeHtml(p.decont_series)}</span>`
                : `<span class="text-muted">${escapeHtml(p.reference || '#'+p.id)}</span>`;

            const row = `<tr class="hover:bg-slate-50 cursor-pointer" data-dc-toggle="${p.id}">
                <td class="px-4 py-3">${chevron}</td>
                <td class="px-4 py-3">${serieBadge}<div class="text-[10px] text-muted">${escapeHtml(p.reference || '')}</div></td>
                <td class="px-4 py-3 text-xs">${period(p)}</td>
                <td class="px-4 py-3">${statusPill(p.status)}</td>
                <td class="px-4 py-3 text-right">${fmtMoney(p.gross_amount)} RON</td>
                <td class="px-4 py-3 text-right text-blue-700 font-semibold">${fmtMoney(p.commission_amount)} RON</td>
                <td class="px-4 py-3 text-right text-muted">${fmtMoney(p.discount_amount)} RON</td>
                <td class="px-4 py-3 text-right font-bold text-emerald-800">${fmtMoney(p.amount)} RON</td>
                <td class="px-4 py-3 text-center">${pdfCell}</td>
            </tr>`;

            let breakdownRow = '';
            if (isOpen) {
                const rows = p.breakdown || [];
                if (!rows.length) {
                    breakdownRow = `<tr class="bg-slate-50/60"><td colspan="9" class="px-6 py-3 text-xs text-muted">Nu există detaliu ticket_breakdown pentru acest decont.</td></tr>`;
                } else {
                    const subRows = rows.map(b => `<tr class="border-t border-slate-200">
                        <td class="px-2 py-1.5 text-xs">${escapeHtml(b.name)}</td>
                        <td class="px-2 py-1.5 text-xs text-right">${b.qty}</td>
                        <td class="px-2 py-1.5 text-xs text-right">${fmtMoney(b.unit_price)}</td>
                        <td class="px-2 py-1.5 text-xs text-right">${fmtMoney(b.gross)}</td>
                        <td class="px-2 py-1.5 text-xs text-right text-blue-700">${fmtMoney(b.commission)}</td>
                        <td class="px-2 py-1.5 text-xs text-right text-muted">${fmtMoney(b.discount)}</td>
                        <td class="px-2 py-1.5 text-xs text-right font-semibold text-emerald-800">${fmtMoney(b.net)}</td>
                    </tr>`).join('');
                    breakdownRow = `<tr class="bg-slate-50/60">
                        <td colspan="9" class="px-6 py-3">
                            <p class="text-[10px] uppercase tracking-wider text-muted font-bold mb-2">Detaliu bilete emise pe acest decont</p>
                            <table class="w-full text-xs bg-white border border-slate-200 rounded-lg overflow-hidden">
                                <thead class="text-[10px] uppercase text-muted bg-slate-100">
                                    <tr>
                                        <th class="px-2 py-1.5 text-left">Tip bilet</th>
                                        <th class="px-2 py-1.5 text-right">Cant.</th>
                                        <th class="px-2 py-1.5 text-right">Preț unit</th>
                                        <th class="px-2 py-1.5 text-right">Brut</th>
                                        <th class="px-2 py-1.5 text-right text-blue-700">Comision</th>
                                        <th class="px-2 py-1.5 text-right">Reducere</th>
                                        <th class="px-2 py-1.5 text-right">Net</th>
                                    </tr>
                                </thead>
                                <tbody>${subRows}</tbody>
                            </table>
                        </td>
                    </tr>`;
                }
            }
            return row + breakdownRow;
        }).join('');

        // Wire up toggle
        tbody.querySelectorAll('[data-dc-toggle]').forEach(tr => {
            tr.addEventListener('click', (ev) => {
                // Nu toggle daca s-a apasat pe link PDF
                if (ev.target.closest('a')) return;
                const id = parseInt(tr.dataset.dcToggle, 10);
                if (openBreakdownIds.has(id)) openBreakdownIds.delete(id);
                else openBreakdownIds.add(id);
                // Re-render pastreaza starea
                render(window.__dcLast || []);
            });
        });
        window.__dcLast = payouts;
    }

    window.addEventListener('load', async () => {
        let retries = 0;
        while (typeof AmbiletAPI === 'undefined' && retries < 10) { await new Promise(r => setTimeout(r, 100)); retries++; }
        if (typeof AmbiletAPI === 'undefined') {
            $('dc-error').textContent = 'API indisponibil — reîncarcă pagina.';
            $('dc-error').classList.remove('hidden');
            return;
        }
        try {
            const res = await AmbiletAPI.get('/organizer/events');
            const events = res.data || [];
            const leisure = events.filter(e => (e.display_template || 'standard') === 'leisure_venue');
            if (leisure.length > 0) currentEventId = leisure[0].id;
        } catch (e) { console.error(e); }

        if (!currentEventId) {
            $('dc-error').textContent = 'Nu există un eveniment de tip Locație de agrement asociat.';
            $('dc-error').classList.remove('hidden');
            return;
        }

        $('dc-refresh').addEventListener('click', loadPayouts);
        loadPayouts();

        // Settlement: build the 2-week period selector (from 2026-07-15) and load.
        buildSettlePeriods();
        const sel = $('dc-settle-period');
        if (sel) {
            sel.addEventListener('change', () => {
                const opt = sel.selectedOptions[0];
                if (opt) loadSettlement(opt.dataset.from, opt.dataset.to);
            });
            const first = sel.selectedOptions[0];
            if (first) loadSettlement(first.dataset.from, first.dataset.to);
        }
    });

    // ---- Settlement (compensare) ----
    const PROJECT_START = '2026-07-15';

    function ymd(d) {
        return d.getFullYear() + '-' + String(d.getMonth() + 1).padStart(2, '0') + '-' + String(d.getDate()).padStart(2, '0');
    }
    function shortDate(s) {
        return new Date(s + 'T00:00:00').toLocaleDateString('ro-RO', { day: '2-digit', month: 'short' });
    }

    function buildSettlePeriods() {
        const sel = $('dc-settle-period');
        if (!sel) return;
        const today = new Date(); today.setHours(0, 0, 0, 0);
        const start = new Date(PROJECT_START + 'T00:00:00');
        const periods = [];
        let cur = new Date(start);
        while (cur <= today && periods.length < 200) {
            const from = new Date(cur);
            const to = new Date(cur); to.setDate(to.getDate() + 13);
            periods.push({ from: ymd(from), to: ymd(to) });
            cur.setDate(cur.getDate() + 14);
        }
        if (!periods.length) periods.push({ from: PROJECT_START, to: ymd(new Date(start.getFullYear(), start.getMonth(), start.getDate() + 13)) });
        // Newest first
        periods.reverse();
        sel.innerHTML = periods.map((p, i) =>
            `<option value="${i}" data-from="${p.from}" data-to="${p.to}">${shortDate(p.from)} – ${shortDate(p.to)} ${new Date(p.to + 'T00:00:00').getFullYear()}</option>`
        ).join('');
    }

    async function loadSettlement(from, to) {
        if (!currentEventId || !from || !to) return;
        $('dc-settle-loading').classList.remove('hidden');
        $('dc-settle-body').classList.add('hidden');
        try {
            const res = await AmbiletAPI.get(`/organizer/events/${currentEventId}/leisure/settlement`, { from, to });
            renderSettlement(res.data || {});
        } catch (e) {
            console.error('[settlement]', e);
            $('dc-settle-body').innerHTML = `<p class="text-sm text-rose-700">Eroare la calculul decontării: ${escapeHtml(e?.message || '')}</p>`;
        } finally {
            $('dc-settle-loading').classList.add('hidden');
            $('dc-settle-body').classList.remove('hidden');
        }
    }

    function renderSettlement(d) {
        const on = d.online || {}, pos = d.pos || {}, bal = d.balance || {};
        const cur = d.currency || 'RON';
        const M = v => fmtMoney(Number(v || 0)) + ' ' + cur;

        let balanceBox;
        if (bal.direction === 'ambilet_to_venue') {
            balanceBox = `<div class="p-4 border-2 rounded-xl bg-emerald-50 border-emerald-300">
                <p class="text-[11px] uppercase tracking-wider font-bold text-emerald-700">De achitat, prin compensare</p>
                <p class="mt-1 text-lg font-extrabold text-emerald-800">AmBilet → Locație</p>
                <p class="text-3xl font-extrabold text-emerald-900">${M(bal.amount)}</p>
            </div>`;
        } else if (bal.direction === 'venue_to_ambilet') {
            balanceBox = `<div class="p-4 border-2 rounded-xl bg-amber-50 border-amber-300">
                <p class="text-[11px] uppercase tracking-wider font-bold text-amber-700">De achitat, prin compensare</p>
                <p class="mt-1 text-lg font-extrabold text-amber-800">Locație → AmBilet</p>
                <p class="text-3xl font-extrabold text-amber-900">${M(bal.amount)}</p>
            </div>`;
        } else {
            balanceBox = `<div class="p-4 border-2 rounded-xl bg-slate-50 border-slate-300">
                <p class="text-[11px] uppercase tracking-wider font-bold text-slate-600">Sold</p>
                <p class="mt-1 text-lg font-extrabold text-slate-700">Zero — nimic de decontat</p>
                <p class="text-3xl font-extrabold text-slate-800">${M(0)}</p>
            </div>`;
        }

        $('dc-settle-body').innerHTML = `
            <div class="grid gap-4 lg:grid-cols-3">
                <div class="p-4 border rounded-xl border-border">
                    <p class="text-[11px] uppercase tracking-wider font-bold text-blue-700 mb-2">🌐 Online (încasat de AmBilet)</p>
                    <div class="space-y-1 text-sm">
                        <div class="flex justify-between"><span class="text-muted">Brut</span><strong>${M(on.gross)}</strong></div>
                        <div class="flex justify-between"><span class="text-muted">Comision AmBilet</span><strong class="text-blue-700">${M(on.commission)}</strong></div>
                        <div class="flex justify-between pt-1 border-t border-border"><span class="text-muted">Net (AmBilet datorează)</span><strong class="text-emerald-800">${M(on.net)}</strong></div>
                    </div>
                </div>
                <div class="p-4 border rounded-xl border-border">
                    <p class="text-[11px] uppercase tracking-wider font-bold text-emerald-700 mb-2">🏪 POS (încasat în locație)</p>
                    <div class="space-y-1 text-sm">
                        <div class="flex justify-between"><span class="text-muted">Brut</span><strong>${M(pos.gross)}</strong></div>
                        <div class="flex justify-between"><span class="text-muted">💵 Cash</span><strong>${M(pos.cash)}</strong></div>
                        <div class="flex justify-between"><span class="text-muted">💳 Card</span><strong>${M(pos.card)}</strong></div>
                        <div class="flex justify-between pt-1 border-t border-border"><span class="text-muted">Comision (locația datorează)</span><strong class="text-amber-700">${M(pos.commission)}</strong></div>
                    </div>
                </div>
                ${balanceBox}
            </div>
            <p class="mt-3 text-[11px] text-muted">
                Compensare: AmBilet datorează locației netul online (<strong>${M(bal.ambilet_owes_venue)}</strong>), iar locația datorează AmBilet comisionul POS (<strong>${M(bal.venue_owes_ambilet)}</strong>).
                Soldul net = ${M(bal.ambilet_owes_venue)} − ${M(bal.venue_owes_ambilet)} = <strong>${M(bal.net)}</strong>.
            </p>`;
    }
})();
</script>
<?php require_once dirname(__DIR__) . '/includes/scripts.php'; ?>
