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

        <!-- Sumar cumulat de la 16.07.2026 pana azi -->
        <div class="grid grid-cols-2 lg:grid-cols-5 gap-3 mb-6">
            <div class="p-4 bg-white border-2 rounded-2xl border-slate-300">
                <p class="text-[10px] uppercase tracking-wider text-slate-600 font-bold">📅 Zile activitate</p>
                <p class="text-2xl font-extrabold text-slate-900"><span id="dc-cum-days">—</span></p>
                <p class="text-[10px] text-muted mt-0.5" id="dc-cum-period">de la 16 iul 2026</p>
            </div>
            <div class="p-4 bg-white border-2 rounded-2xl border-sky-200">
                <p class="text-[10px] uppercase tracking-wider text-sky-700 font-bold">🌐 Vânzări online</p>
                <p class="text-2xl font-extrabold text-sky-900"><span id="dc-cum-online-gross">—</span> <span class="text-xs text-sky-700">RON</span></p>
            </div>
            <div class="p-4 bg-white border-2 rounded-2xl border-amber-200">
                <p class="text-[10px] uppercase tracking-wider text-amber-700 font-bold">🏪 Vânzări POS</p>
                <p class="text-2xl font-extrabold text-amber-900"><span id="dc-cum-pos-gross">—</span> <span class="text-xs text-amber-700">RON</span></p>
            </div>
            <div class="p-4 bg-white border-2 rounded-2xl border-indigo-200">
                <p class="text-[10px] uppercase tracking-wider text-indigo-700 font-bold">🧾 Comisioane online</p>
                <p class="text-2xl font-extrabold text-indigo-900"><span id="dc-cum-online-comm">—</span> <span class="text-xs text-indigo-700">RON</span></p>
            </div>
            <div class="p-4 bg-white border-2 rounded-2xl border-purple-200">
                <p class="text-[10px] uppercase tracking-wider text-purple-700 font-bold">🧾 Comisioane POS</p>
                <p class="text-2xl font-extrabold text-purple-900"><span id="dc-cum-pos-comm">—</span> <span class="text-xs text-purple-700">RON</span></p>
            </div>
        </div>

        <!-- Decontare (compensare AmBilet <-> locație) pe jumătăți de lună: 1-15 și 16-ultima_zi -->
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
    const SOCIETY_COLOR = {
        primary: 'bg-blue-100 text-blue-800',
        secondary: 'bg-purple-100 text-purple-800',
    };

    // Invoice status pill (used inside the invoices accordion, per payout).
    const INV_STATUS_LABEL = {
        paid: 'Achitată',
        outstanding: 'Neachitată',
        pending: 'În așteptare',
        cancelled: 'Anulată',
        refunded: 'Rambursată',
    };
    const INV_STATUS_COLOR = {
        paid: 'bg-emerald-100 text-emerald-800',
        outstanding: 'bg-amber-100 text-amber-800',
        pending: 'bg-amber-100 text-amber-800',
        cancelled: 'bg-slate-100 text-slate-700',
        refunded: 'bg-rose-100 text-rose-800',
    };
    const INV_STAGE_LABEL = {
        fiscala: 'Fiscală',
        proforma: 'Proformă',
    };

    let currentEventId = null;
    let openPeriodKeys = new Set();   // perioadele expandate (parent accordion)
    let openBreakdownIds = new Set(); // decont-uri expandate (grandchild — bilete)
    let openInvoiceIds = new Set();   // facturi expandate (grandchild — articole)

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

    function societyBadge(p) {
        const key = p.issuing_company;
        const name = p.society_name || '-';
        if (!key || name === '-') {
            return '<span class="text-muted text-xs">-</span>';
        }
        const cls = SOCIETY_COLOR[key] || 'bg-slate-100 text-slate-700';
        return `<span class="inline-block px-2 py-0.5 text-[11px] font-medium rounded-full ${cls}">${escapeHtml(name)}</span>`;
    }

    function invStatusPill(s) {
        const cls = INV_STATUS_COLOR[s] || 'bg-slate-100 text-slate-700';
        const label = INV_STATUS_LABEL[s] || s;
        return `<span class="inline-block px-2 py-0.5 text-[10px] font-medium rounded-full ${cls}">${escapeHtml(label)}</span>`;
    }

    function escapeHtml(s) { return String(s || '').replace(/[<>&]/g, c => ({'<':'&lt;','>':'&gt;','&':'&amp;'}[c])); }

    function period(p) {
        if (!p.period_start && !p.period_end) return '—';
        const s = p.period_start ? new Date(p.period_start).toLocaleDateString('ro-RO', { day:'2-digit', month:'short', year:'numeric' }) : '—';
        const e = p.period_end ? new Date(p.period_end).toLocaleDateString('ro-RO', { day:'2-digit', month:'short', year:'numeric' }) : '—';
        return s + ' → ' + e;
    }

    // Cardurile de sus - cifre cumulate de la 16.07.2026 pana azi.
    async function loadCumulative() {
        if (!currentEventId) return;
        try {
            const res = await AmbiletAPI.get(`/organizer/events/${currentEventId}/leisure/settlement/cumulative`);
            const d = res.data || {};
            const on = d.online || {}; const po = d.pos || {};
            $('dc-cum-days').textContent = d.days_count || 0;
            const from = d.from ? new Date(d.from + 'T00:00:00').toLocaleDateString('ro-RO', { day:'2-digit', month:'short', year:'numeric' }) : '16 iul 2026';
            $('dc-cum-period').textContent = 'de la ' + from;
            $('dc-cum-online-gross').textContent = fmtMoney(on.gross);
            $('dc-cum-pos-gross').textContent = fmtMoney(po.gross);
            $('dc-cum-online-comm').textContent = fmtMoney(on.commission);
            $('dc-cum-pos-comm').textContent = fmtMoney(po.commission);
        } catch (e) {
            console.warn('[cumulative]', e);
        }
    }

    function render(payouts) {
        const tbody = $('dc-rows');
        if (!payouts.length) {
            tbody.innerHTML = '<tr><td class="px-4 py-8 text-center text-muted" colspan="9">Nu există deconturi emise pentru acest eveniment.</td></tr>';
            return;
        }

        // Group payouts by settlement period (period_start + period_end pair)
        // so each period becomes a parent accordion that expands to its
        // children (deconturi + facturi). Newest period on top.
        const byPeriod = {};
        payouts.forEach(p => {
            const key = (p.period_start || '') + '_' + (p.period_end || '');
            if (!byPeriod[key]) {
                byPeriod[key] = { key, period_start: p.period_start, period_end: p.period_end, payouts: [] };
            }
            byPeriod[key].payouts.push(p);
        });
        const groups = Object.values(byPeriod).sort((a, b) => (b.period_start || '').localeCompare(a.period_start || ''));

        function periodLabel(g) {
            if (!g.period_start && !g.period_end) return 'Fără perioadă';
            const s = g.period_start ? new Date(g.period_start).toLocaleDateString('ro-RO', { day:'2-digit', month:'short' }) : '—';
            const e = g.period_end ? new Date(g.period_end).toLocaleDateString('ro-RO', { day:'2-digit', month:'short', year:'numeric' }) : '—';
            return s + ' – ' + e;
        }

        // Render a single decont child row (chevron + inline summary), plus
        // its bilet-breakdown sub-row when expanded.
        function renderDecontChild(p) {
            const isOpen = openBreakdownIds.has(p.id);
            const chevron = `<svg class="w-4 h-4 text-emerald-700 transition-transform ${isOpen ? 'rotate-90' : ''}" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>`;
            const pdfCell = p.pdf_url
                ? `<a href="${p.pdf_url}" target="_blank" rel="noopener" onclick="event.stopPropagation();" class="inline-flex items-center gap-1 px-2 py-1 text-xs font-medium bg-emerald-100 text-emerald-800 border border-emerald-200 rounded-lg hover:bg-emerald-200">PDF</a>`
                : '<span class="text-[11px] text-muted">—</span>';
            const numberCell = `<div class="flex items-center gap-2">
                    <span class="text-[10px] text-emerald-700 font-bold uppercase">decont</span>
                    <span class="font-bold text-emerald-900">${escapeHtml(p.decont_series || p.reference || ('#' + p.id))}</span>
                </div>
                <div class="text-[10px] text-muted mt-0.5">${escapeHtml(p.reference || '')}</div>`;

            let row = `<tr class="bg-emerald-50/60 hover:bg-emerald-100/60 cursor-pointer border-l-4 border-emerald-400" data-dc-toggle="${p.id}">
                <td class="px-4 py-2.5 pl-10">${chevron}</td>
                <td class="px-4 py-2.5">${numberCell}</td>
                <td class="px-4 py-2.5 text-xs">${period(p)}</td>
                <td class="px-4 py-2.5">${societyBadge(p)}</td>
                <td class="px-4 py-2.5 text-right text-xs text-muted">Vanzari: <strong class="text-emerald-900">${fmtMoney(p.gross_amount)} RON</strong></td>
                <td class="px-4 py-2.5 text-right text-xs text-muted">Comision: <strong class="text-blue-700">${fmtMoney(p.commission_amount)} RON</strong></td>
                <td class="px-4 py-2.5"></td>
                <td class="px-4 py-2.5"></td>
                <td class="px-4 py-2.5 text-center">${pdfCell}</td>
            </tr>`;

            if (isOpen) {
                const rows = p.breakdown || [];
                if (!rows.length) {
                    row += `<tr class="bg-emerald-50/30"><td colspan="9" class="px-10 py-3 text-xs text-muted">Nu există detaliu ticket_breakdown pentru acest decont.</td></tr>`;
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
                    row += `<tr class="bg-emerald-50/30"><td colspan="9" class="px-10 py-3">
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
                    </td></tr>`;
                }
            }
            return row;
        }

        // Render a single factura child row (sibling of decont) + its
        // articole sub-row when expanded. Uses the parent payout's society
        // (issuer of the invoice) for the society badge.
        function renderInvoiceChild(inv, parentPayout) {
            const invOpen = openInvoiceIds.has(inv.id);
            const chevron = `<svg class="w-4 h-4 text-amber-700 transition-transform ${invOpen ? 'rotate-90' : ''}" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>`;
            const invPdf = inv.accounting_pdf_url
                ? `<a href="${inv.accounting_pdf_url}" target="_blank" rel="noopener" onclick="event.stopPropagation();" class="inline-flex items-center gap-1 px-2 py-1 text-xs font-medium bg-amber-100 text-amber-900 border border-amber-200 rounded-lg hover:bg-amber-200">PDF</a>`
                : '<span class="text-[11px] text-muted">—</span>';
            const dateLabel = inv.issue_date
                ? new Date(inv.issue_date + 'T00:00:00').toLocaleDateString('ro-RO', { day:'2-digit', month:'short', year:'numeric' })
                : '-';
            // Number cell: Oblio number BIG, Tixello internal small below,
            // prefixed by "factura" label (per operator request).
            const numberCell = inv.accounting_number
                ? `<div class="flex items-center gap-2">
                        <span class="text-[10px] text-amber-700 font-bold uppercase">factura</span>
                        <span class="font-bold text-amber-900">${escapeHtml(inv.accounting_number)}</span>
                    </div>
                    <div class="text-[10px] text-muted mt-0.5">${escapeHtml(inv.number || '')}</div>`
                : `<div class="flex items-center gap-2">
                        <span class="text-[10px] text-amber-700 font-bold uppercase">factura</span>
                        <span class="font-semibold text-amber-900">${escapeHtml(inv.number || ('#' + inv.id))}</span>
                    </div>`;

            let row = `<tr class="bg-amber-50/60 hover:bg-amber-100/60 cursor-pointer border-l-4 border-amber-400" data-inv-toggle="${inv.id}">
                <td class="px-4 py-2.5 pl-10">${chevron}</td>
                <td class="px-4 py-2.5">${numberCell}</td>
                <td class="px-4 py-2.5 text-xs">Data: <strong>${dateLabel}</strong></td>
                <td class="px-4 py-2.5">${societyBadge(parentPayout)}</td>
                <td class="px-4 py-2.5 text-right text-xs text-muted">Subtotal: ${fmtMoney(inv.subtotal)}</td>
                <td class="px-4 py-2.5 text-right text-xs text-muted">TVA: ${fmtMoney(inv.vat_amount)}</td>
                <td class="px-4 py-2.5 text-right text-muted">—</td>
                <td class="px-4 py-2.5 text-right font-bold text-amber-900">${fmtMoney(inv.amount)} ${inv.currency || 'RON'}</td>
                <td class="px-4 py-2.5 text-center">${invPdf}</td>
            </tr>`;

            if (invOpen) {
                const items = inv.items || [];
                if (!items.length) {
                    row += `<tr class="bg-amber-50/30"><td colspan="9" class="px-10 py-3 text-xs text-muted italic">Factura nu are articole înregistrate.</td></tr>`;
                } else {
                    const itemBody = items.map(it => `<tr class="border-t border-slate-100">
                        <td class="px-2 py-1.5 text-[11px] align-top">
                            <div class="font-semibold">${escapeHtml(it.name || '')}</div>
                            ${it.description && it.description !== it.name ? `<div class="text-muted mt-0.5 text-[10px]">${escapeHtml(it.description)}</div>` : ''}
                        </td>
                        <td class="px-2 py-1.5 text-[11px] text-right">${Number(it.quantity || 0).toLocaleString('ro-RO')}</td>
                        <td class="px-2 py-1.5 text-[11px] text-right">${fmtMoney(it.unit_price)}</td>
                        <td class="px-2 py-1.5 text-[11px] text-right font-semibold">${fmtMoney(it.amount)}</td>
                    </tr>`).join('');
                    row += `<tr class="bg-amber-50/30"><td colspan="9" class="px-10 py-3">
                        <p class="text-[10px] uppercase tracking-wider text-muted font-bold mb-2">Articole factură</p>
                        <table class="w-full text-[11px] bg-white border border-slate-200 rounded-lg overflow-hidden">
                            <thead class="text-[10px] uppercase text-muted bg-slate-100">
                                <tr>
                                    <th class="px-2 py-1.5 text-left">Denumire / descriere</th>
                                    <th class="px-2 py-1.5 text-right">Cant.</th>
                                    <th class="px-2 py-1.5 text-right">Preț unit</th>
                                    <th class="px-2 py-1.5 text-right">Total</th>
                                </tr>
                            </thead>
                            <tbody>${itemBody}</tbody>
                        </table>
                    </td></tr>`;
                }
            }
            return row;
        }

        // Render one PERIOD accordion group.
        tbody.innerHTML = groups.map(g => {
            const isOpen = openPeriodKeys.has(g.key);
            const chevron = `<svg class="w-4 h-4 text-slate-600 transition-transform ${isOpen ? 'rotate-90' : ''}" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>`;

            // Aggregate summary for the parent row.
            let sumNet = 0, sumGross = 0, sumComm = 0, cntInv = 0;
            g.payouts.forEach(p => {
                sumNet += Number(p.amount || 0);
                sumGross += Number(p.gross_amount || 0);
                sumComm += Number(p.commission_amount || 0);
                cntInv += (p.invoices || []).length;
            });
            const cntDec = g.payouts.length;
            const summary = `${cntDec} decont${cntDec === 1 ? '' : 'uri'} · ${cntInv} factur${cntInv === 1 ? 'ă' : 'i'}`;

            let out = `<tr class="bg-slate-100 hover:bg-slate-200 cursor-pointer font-semibold" data-period-toggle="${g.key}">
                <td class="px-4 py-3">${chevron}</td>
                <td class="px-4 py-3">
                    <span class="text-[10px] text-slate-600 font-bold uppercase">Perioada</span>
                    <div class="text-secondary font-bold">${periodLabel(g)}</div>
                </td>
                <td class="px-4 py-3 text-xs text-muted">${summary}</td>
                <td class="px-4 py-3"></td>
                <td class="px-4 py-3 text-right text-xs text-muted">${fmtMoney(sumGross)} RON</td>
                <td class="px-4 py-3 text-right text-xs text-blue-700">${fmtMoney(sumComm)} RON</td>
                <td class="px-4 py-3"></td>
                <td class="px-4 py-3 text-right font-bold text-emerald-800">${fmtMoney(sumNet)} RON</td>
                <td class="px-4 py-3"></td>
            </tr>`;

            if (isOpen) {
                // Children: interleave decont child + its invoice children.
                // Each payout emits: [decont row] [invoice rows...] so the
                // decont-to-facturi grouping stays visible even inside a
                // shared period.
                g.payouts.forEach(p => {
                    out += renderDecontChild(p);
                    (p.invoices || []).forEach(inv => {
                        out += renderInvoiceChild(inv, p);
                    });
                });
            }
            return out;
        }).join('');

        // Wire up toggle for period parent rows
        tbody.querySelectorAll('[data-period-toggle]').forEach(tr => {
            tr.addEventListener('click', (ev) => {
                if (ev.target.closest('a')) return;
                const key = tr.dataset.periodToggle;
                if (openPeriodKeys.has(key)) openPeriodKeys.delete(key);
                else openPeriodKeys.add(key);
                render(window.__dcLast || []);
            });
        });

        // Wire up toggle for decont child rows
        tbody.querySelectorAll('[data-dc-toggle]').forEach(tr => {
            tr.addEventListener('click', (ev) => {
                if (ev.target.closest('a')) return;
                const id = parseInt(tr.dataset.dcToggle, 10);
                if (openBreakdownIds.has(id)) openBreakdownIds.delete(id);
                else openBreakdownIds.add(id);
                render(window.__dcLast || []);
            });
        });

        // Wire up toggle for invoice child rows (siblings of decont)
        tbody.querySelectorAll('[data-inv-toggle]').forEach(tr => {
            tr.addEventListener('click', (ev) => {
                if (ev.target.closest('a')) return;
                const id = parseInt(tr.dataset.invToggle, 10);
                if (openInvoiceIds.has(id)) openInvoiceIds.delete(id);
                else openInvoiceIds.add(id);
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

        $('dc-refresh').addEventListener('click', () => { loadPayouts(); loadCumulative(); });
        loadPayouts();
        loadCumulative();

        // Settlement: build half-month period selector (1-15 & 16-end, din 2026-07-15) and load.
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

    // Genereaza perioade jumatati-de-luna: [1-15] si [16-ultima_zi].
    // Regula: sarim perioada care a inceput INAINTE de PROJECT_START (ex: proiectul
    // incepe 15 iulie -> jumatatea 1-15 iulie a inceput pe 1 iulie -> skip; prima
    // afisata devine 16-31 iulie).
    function buildSettlePeriods() {
        const sel = $('dc-settle-period');
        if (!sel) return;
        const today = new Date(); today.setHours(0, 0, 0, 0);
        const start = new Date(PROJECT_START + 'T00:00:00');
        const periods = [];

        // Determina jumatatea de start (1 = 1-15, 2 = 16-end):
        //   startDay=1  -> half 1 (incepe fix pe 1)
        //   startDay=16 -> half 2 (incepe fix pe 16)
        //   startDay 2..15 -> half 1 a inceput pe 1, e partiala -> sarim la half 2
        //   startDay >16 -> half 2 a inceput pe 16, e partiala -> sarim la half 1 luna urmatoare
        let year = start.getFullYear();
        let month = start.getMonth(); // 0-indexed
        let half = 1;
        const sd = start.getDate();
        if (sd === 1) { half = 1; }
        else if (sd === 16) { half = 2; }
        else if (sd < 16) { half = 2; }
        else { half = 1; month++; if (month > 11) { month = 0; year++; } }

        let safety = 0;
        while (safety++ < 500) {
            const fromDay = (half === 1) ? 1 : 16;
            const from = new Date(year, month, fromDay);
            // half 1: pana pe 15; half 2: ultima zi din luna curenta (day 0 din luna urmatoare)
            const to = (half === 1)
                ? new Date(year, month, 15)
                : new Date(year, month + 1, 0);
            if (from > today) break;
            periods.push({ from: ymd(from), to: ymd(to) });
            // avanseaza
            if (half === 1) {
                half = 2;
            } else {
                half = 1;
                month++;
                if (month > 11) { month = 0; year++; }
            }
        }

        if (!periods.length) {
            // Fallback defensiv: jumatatea curenta a lunii
            const now = new Date();
            const d = now.getDate() <= 15
                ? { from: new Date(now.getFullYear(), now.getMonth(), 1), to: new Date(now.getFullYear(), now.getMonth(), 15) }
                : { from: new Date(now.getFullYear(), now.getMonth(), 16), to: new Date(now.getFullYear(), now.getMonth() + 1, 0) };
            periods.push({ from: ymd(d.from), to: ymd(d.to) });
        }
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

        // Breakdown pe societate (SC1/SC2)
        const byIssuer = d.by_issuer || {};
        const societyCard = (iss) => {
            if (!iss || !iss.name) return '';
            const anyValue = (iss.online_gross || iss.pos_gross || iss.online_commission || iss.pos_commission);
            if (!anyValue) return '';
            const label = iss.issuer === 'primary' ? 'SC1 · Principală' : 'SC2 · Secundară';
            const colorClass = iss.issuer === 'primary' ? 'border-blue-300 bg-blue-50/40' : 'border-purple-300 bg-purple-50/40';
            const compClass = iss.compensation > 0 ? 'text-emerald-800' : (iss.compensation < 0 ? 'text-amber-800' : 'text-slate-700');
            return `
                <div class="p-4 border-2 rounded-xl ${colorClass}">
                    <div class="mb-3">
                        <p class="text-[10px] uppercase tracking-wider font-bold text-slate-600">${label}</p>
                        <p class="text-sm font-bold text-secondary">${escapeHtml(iss.name)}</p>
                    </div>
                    <div class="space-y-2 text-sm">
                        <div class="p-2 rounded bg-sky-50 border border-sky-200">
                            <p class="text-[10px] uppercase font-bold text-sky-700 mb-1">🌐 Online</p>
                            <div class="flex justify-between text-xs"><span class="text-muted">Vânzări</span><strong class="tabular-nums">${M(iss.online_gross)}</strong></div>
                            <div class="flex justify-between text-xs"><span class="text-muted">Comisioane</span><strong class="text-blue-700 tabular-nums">${M(iss.online_commission)}</strong></div>
                        </div>
                        <div class="p-2 rounded bg-amber-50 border border-amber-200">
                            <p class="text-[10px] uppercase font-bold text-amber-700 mb-1">🏪 POS</p>
                            <div class="flex justify-between text-xs"><span class="text-muted">Vânzări</span><strong class="tabular-nums">${M(iss.pos_gross)}</strong></div>
                            <div class="flex justify-between text-xs"><span class="text-muted">Comisioane</span><strong class="text-amber-700 tabular-nums">${M(iss.pos_commission)}</strong></div>
                        </div>
                        <div class="pt-2 border-t-2 border-slate-300">
                            <p class="text-[10px] uppercase font-bold text-slate-700">Compensare (vânzări online − comisioane totale)</p>
                            <p class="text-xl font-extrabold ${compClass} tabular-nums">${M(iss.compensation)}</p>
                        </div>
                    </div>
                </div>
            `;
        };
        const issuerCards = societyCard(byIssuer.primary) + societyCard(byIssuer.secondary);
        const issuerSection = issuerCards ? `
            <div class="mt-6 pt-4 border-t border-border">
                <h3 class="text-sm font-bold text-secondary mb-3">🏢 Breakdown pe societate</h3>
                <div class="grid gap-4 lg:grid-cols-2">${issuerCards}</div>
            </div>` : '';

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
            </p>
            ${issuerSection}`;
    }
})();
</script>
<?php require_once dirname(__DIR__) . '/includes/scripts.php'; ?>
