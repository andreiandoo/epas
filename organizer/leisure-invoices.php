<?php
require_once dirname(__DIR__) . '/includes/config.php';
$pageTitle = 'Facturi persoane juridice';
$bodyClass = 'min-h-screen flex bg-slate-100';
$currentPage = 'leisure_invoices';
$cssBundle = 'organizer';
require_once dirname(__DIR__) . '/includes/head.php';
require_once dirname(__DIR__) . '/includes/organizer-sidebar.php';
?>
<div class="flex flex-col flex-1 min-h-screen lg:ml-0">
    <?php require_once dirname(__DIR__) . '/includes/organizer-topbar.php'; ?>
    <main class="flex-1 p-4 lg:p-8">
        <div class="mb-6 flex flex-wrap items-center justify-between gap-3">
            <div>
                <h1 class="text-2xl font-bold text-secondary lg:text-3xl">📄 Facturi persoane juridice</h1>
                <p class="mt-1 text-sm text-muted">Comenzile POS în care operatorul a bifat „Generează factură fiscală" și a introdus date de firmă (CUI, denumire, adresă).</p>
            </div>
            <a href="/organizator/leisure-sales" class="inline-flex items-center gap-2 px-4 py-2 text-sm font-semibold border rounded-lg text-secondary bg-white border-border hover:bg-slate-50">← Înapoi la Vânzări</a>
        </div>

        <!-- Filter perioada -->
        <div class="bg-white border rounded-2xl border-border p-4 mb-6 flex flex-wrap items-end gap-3">
            <div class="flex flex-wrap gap-2">
                <button data-range="7" class="li-range-btn px-3 py-1.5 text-xs font-medium rounded-lg border border-border bg-white hover:bg-slate-50">7 zile</button>
                <button data-range="14" class="li-range-btn px-3 py-1.5 text-xs font-medium rounded-lg border border-border bg-white hover:bg-slate-50">14 zile</button>
                <button data-range="30" class="li-range-btn px-3 py-1.5 text-xs font-medium rounded-lg border border-border bg-white hover:bg-slate-50">1 lună</button>
                <button data-range="90" class="li-range-btn px-3 py-1.5 text-xs font-medium rounded-lg border border-border bg-white hover:bg-slate-50">3 luni</button>
                <button data-range="180" class="li-range-btn px-3 py-1.5 text-xs font-medium rounded-lg border border-border bg-white hover:bg-slate-50">6 luni</button>
            </div>
            <div class="flex flex-wrap items-end gap-2 ml-4">
                <label class="block">
                    <span class="text-xs font-semibold text-muted">De la data</span>
                    <input id="li-from" type="date" class="block mt-1 px-3 py-1.5 text-sm border border-border rounded-lg">
                </label>
                <label class="block">
                    <span class="text-xs font-semibold text-muted">Până la data</span>
                    <input id="li-to" type="date" class="block mt-1 px-3 py-1.5 text-sm border border-border rounded-lg">
                </label>
                <button id="li-apply" type="button" class="px-3 py-1.5 text-xs font-semibold rounded-lg border border-primary bg-primary text-white hover:opacity-90">Aplică</button>
            </div>
            <div class="flex-1 ml-auto min-w-[220px] max-w-xs">
                <input id="li-search" type="text" placeholder="Caută nr comandă, factură, firmă, CUI…" class="w-full px-3 py-1.5 text-sm border border-border rounded-lg">
            </div>
            <span id="li-range-label" class="text-xs text-muted w-full">Ultimele 30 zile</span>
        </div>

        <div id="li-error" class="hidden mb-4 p-4 bg-rose-50 border border-rose-200 rounded-xl text-sm text-rose-900"></div>
        <div id="li-loading" class="hidden p-8 text-center"><div class="inline-block w-6 h-6 border-2 rounded-full border-primary border-t-transparent animate-spin"></div></div>

        <div id="li-summary" class="hidden mb-6 p-4 bg-white border rounded-2xl border-border">
            <p class="text-sm text-muted">
                <strong id="li-summary-count" class="text-2xl text-secondary tabular-nums">0</strong>
                <span class="text-lg">facturi</span>
                · Total valoare: <strong id="li-summary-total" class="tabular-nums text-emerald-700">0.00 RON</strong>
            </p>
        </div>

        <div class="bg-white border rounded-2xl border-border overflow-hidden">
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead class="bg-slate-50 border-b border-border">
                        <tr class="text-left text-xs font-semibold uppercase text-muted tracking-wider">
                            <th class="px-4 py-3">Nr. comandă</th>
                            <th class="px-4 py-3">Data</th>
                            <th class="px-4 py-3">Nr. factură</th>
                            <th class="px-4 py-3">Firmă</th>
                            <th class="px-4 py-3">CUI</th>
                            <th class="px-4 py-3">Client contact</th>
                            <th class="px-4 py-3 text-right">Total</th>
                            <th class="px-4 py-3">Operator</th>
                        </tr>
                    </thead>
                    <tbody id="li-tbody" class="divide-y divide-border"></tbody>
                </table>
            </div>
            <div id="li-empty" class="hidden p-8 text-center text-muted">Nicio factură în perioada selectată.</div>
        </div>
    </main>
</div>

<!-- Modal detalii -->
<div id="li-modal" class="fixed inset-0 z-50 hidden items-center justify-center p-4 bg-black/60 backdrop-blur-sm">
    <div class="bg-white rounded-2xl shadow-2xl max-w-4xl w-full max-h-[92vh] overflow-hidden flex flex-col">
        <div class="flex items-center justify-between px-6 py-4 border-b border-border">
            <h3 id="li-modal-title" class="text-lg font-bold text-secondary">Detalii comandă cu factură</h3>
            <button id="li-modal-close" class="w-8 h-8 flex items-center justify-center rounded-lg hover:bg-slate-100 text-slate-500">✕</button>
        </div>
        <div id="li-modal-body" class="p-6 overflow-y-auto flex-1"></div>
    </div>
</div>

<script>
(function(){
    const $ = (id) => document.getElementById(id);
    let currentEventId = null;
    let currentFrom = null;
    let currentTo = null;
    let currentRows = [];

    const fmt = d => d.getFullYear() + '-' + String(d.getMonth()+1).padStart(2,'0') + '-' + String(d.getDate()).padStart(2,'0');
    const fmtMoney = v => Number(v || 0).toLocaleString('ro-RO', {minimumFractionDigits: 2, maximumFractionDigits: 2});
    const fmtDate = s => { if (!s) return '—'; const d = new Date(s); return isNaN(d) ? '—' : d.toLocaleDateString('ro-RO', {day:'2-digit', month:'short', year:'numeric'}); };
    const fmtDateTime = s => { if (!s) return '—'; const d = new Date(s); return isNaN(d) ? '—' : d.toLocaleString('ro-RO', {day:'2-digit', month:'short', year:'numeric', hour:'2-digit', minute:'2-digit'}); };
    const esc = s => { const d = document.createElement('div'); d.textContent = (s == null ? '' : String(s)); return d.innerHTML; };

    function setRange(days) {
        document.querySelectorAll('.li-range-btn').forEach(b => {
            b.classList.remove('bg-primary','text-white','border-primary');
            b.classList.add('bg-white');
        });
        const btn = document.querySelector(`.li-range-btn[data-range="${days}"]`);
        if (btn) { btn.classList.remove('bg-white'); btn.classList.add('bg-primary','text-white','border-primary'); }
        const to = new Date();
        const from = new Date(Date.now() - parseInt(days,10)*86400000);
        currentFrom = fmt(from);
        currentTo = fmt(to);
        $('li-from').value = currentFrom;
        $('li-to').value = currentTo;
        const labels = {'7':'Ultimele 7 zile','14':'Ultimele 14 zile','30':'Ultima lună','90':'Ultimele 3 luni','180':'Ultimele 6 luni'};
        $('li-range-label').textContent = labels[days] || (days + ' zile');
        loadInvoices();
    }

    function applyCustom() {
        const f = $('li-from').value, t = $('li-to').value;
        if (!f || !t) return alert('Selectează ambele date');
        currentFrom = f; currentTo = t;
        $('li-range-label').textContent = 'Perioadă custom';
        document.querySelectorAll('.li-range-btn').forEach(b => { b.classList.remove('bg-primary','text-white','border-primary'); b.classList.add('bg-white'); });
        loadInvoices();
    }

    async function loadInvoices() {
        if (!currentEventId) return;
        $('li-error').classList.add('hidden');
        $('li-loading').classList.remove('hidden');
        try {
            const params = { from: currentFrom, to: currentTo, per_page: 200 };
            const search = ($('li-search').value || '').trim();
            if (search) params.search = search;
            const res = await AmbiletAPI.get(`/organizer/events/${currentEventId}/leisure/invoices`, params);
            const data = res.data || {};
            currentRows = data.orders || [];
            renderRows();
        } catch (e) {
            console.error('[leisure-invoices] load', e);
            $('li-error').textContent = 'Eroare la încărcare: ' + (e?.message || '');
            $('li-error').classList.remove('hidden');
        } finally {
            $('li-loading').classList.add('hidden');
        }
    }

    function renderRows() {
        const tbody = $('li-tbody');
        const empty = $('li-empty');
        const summary = $('li-summary');
        if (!currentRows.length) {
            tbody.innerHTML = '';
            empty.classList.remove('hidden');
            summary.classList.add('hidden');
            return;
        }
        empty.classList.add('hidden');
        summary.classList.remove('hidden');
        const total = currentRows.reduce((s,r) => s + Number(r.total || 0), 0);
        $('li-summary-count').textContent = currentRows.length;
        $('li-summary-total').textContent = fmtMoney(total) + ' RON';

        tbody.innerHTML = currentRows.map((r, idx) => {
            const orderNum = r.order_number || ('#' + r.id);
            const invNum = r.invoice_number || (r.invoice_requested ? '<span class="text-amber-700 text-xs italic">cerută</span>' : '—');
            const company = r.company_name || '—';
            const cui = r.company_cui || '—';
            const contact = r.customer_name || r.customer_email || '—';
            const operator = r.operator_name || '—';
            return `<tr class="hover:bg-blue-50 cursor-pointer" data-idx="${idx}">
                <td class="px-4 py-3 font-mono text-xs font-semibold text-secondary">${esc(orderNum)}</td>
                <td class="px-4 py-3 text-xs text-muted whitespace-nowrap">${fmtDateTime(r.paid_at)}</td>
                <td class="px-4 py-3 font-mono text-xs font-semibold text-blue-700">${invNum}</td>
                <td class="px-4 py-3 text-sm font-medium text-secondary">${esc(company)}</td>
                <td class="px-4 py-3 font-mono text-xs">${esc(cui)}</td>
                <td class="px-4 py-3 text-xs">${esc(contact)}</td>
                <td class="px-4 py-3 text-right font-semibold text-secondary tabular-nums">${fmtMoney(r.total)} ${esc(r.currency || 'RON')}</td>
                <td class="px-4 py-3 text-xs text-muted">${esc(operator)}</td>
            </tr>`;
        }).join('');

        tbody.querySelectorAll('tr[data-idx]').forEach(tr => {
            tr.addEventListener('click', () => openModal(parseInt(tr.dataset.idx, 10)));
        });
    }

    async function openModal(idx) {
        const listRow = currentRows[idx];
        if (!listRow) return;
        $('li-modal-title').textContent = 'Comandă ' + (listRow.order_number || ('#' + listRow.id));
        $('li-modal-body').innerHTML = '<div class="text-center py-8 text-muted">Se încarcă…</div>';
        $('li-modal').classList.remove('hidden');
        $('li-modal').classList.add('flex');

        try {
            // Fetch order complet cu bilete
            const res = await AmbiletAPI.get(`/organizer/events/${currentEventId}/leisure/orders/${listRow.id}`);
            const d = res.data || {};
            const meta = d.meta || {};
            const cb = meta.company_billing || {};

            let html = '';

            // Datele firmei
            html += `<div class="mb-6 p-4 bg-blue-50 border border-blue-200 rounded-xl">
                <h4 class="font-bold text-blue-900 mb-3 flex items-center gap-2">🏢 Date firmă (client B2B)</h4>
                <div class="grid grid-cols-2 gap-3 text-sm">
                    ${row('Denumire', cb.name)}
                    ${row('CUI', cb.cui)}
                    ${row('Nr. Reg. Comerț', cb.reg_no)}
                    ${row('IBAN', cb.iban)}
                    ${row('Adresă', cb.address, 'col-span-2')}
                    ${row('Persoană de contact', cb.contact_person, 'col-span-2')}
                </div>
            </div>`;

            // Datele comenzii
            html += `<div class="mb-6 p-4 bg-white border border-border rounded-xl">
                <h4 class="font-bold text-secondary mb-3 flex items-center gap-2">📋 Detalii comandă</h4>
                <div class="grid grid-cols-2 gap-3 text-sm">
                    ${row('Nr. comandă', d.order_number || ('#' + d.id))}
                    ${row('Nr. factură', meta.invoice_number || (meta.invoice_requested ? 'Cerută (nefinalizată)' : '—'))}
                    ${row('Status', d.status)}
                    ${row('Sursă', d.source === 'pos' ? 'POS (fizic)' : 'Online')}
                    ${row('Metodă plată', meta.payment_method || '—')}
                    ${row('Data plății', fmtDateTime(d.paid_at))}
                    ${row('Total', fmtMoney(d.total) + ' ' + (d.currency || 'RON'), '', 'text-right font-bold text-emerald-700')}
                    ${meta.commission_total ? row('Comision', fmtMoney(meta.commission_total) + ' ' + (d.currency || 'RON')) : ''}
                </div>
            </div>`;

            // Datele clientului (contact)
            html += `<div class="mb-6 p-4 bg-white border border-border rounded-xl">
                <h4 class="font-bold text-secondary mb-3 flex items-center gap-2">👤 Contact client</h4>
                <div class="grid grid-cols-2 gap-3 text-sm">
                    ${row('Nume', d.customer_name)}
                    ${row('Email', d.customer_email)}
                    ${row('Telefon', d.customer_phone)}
                </div>
            </div>`;

            // Bilete
            if (Array.isArray(d.tickets) && d.tickets.length) {
                html += `<div class="p-4 bg-white border border-border rounded-xl">
                    <h4 class="font-bold text-secondary mb-3 flex items-center gap-2">🎟️ Bilete emise (${d.tickets.length})</h4>
                    <div class="overflow-x-auto">
                        <table class="w-full text-xs">
                            <thead class="text-muted uppercase text-[10px]">
                                <tr class="text-left"><th class="py-1">Cod</th><th>Tip</th><th>Categorie</th><th class="text-right">Preț</th><th>Status</th></tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100">
                                ${d.tickets.map(t => `<tr>
                                    <td class="py-2 font-mono">${esc(t.code || '—')}</td>
                                    <td>${esc(t.ticket_type)}${t.is_umbrella ? ' <span class="text-[9px] text-amber-700">(umbrella)</span>' : ''}${t.from_package ? ' <span class="text-[9px] text-blue-700">(din pachet)</span>' : ''}</td>
                                    <td class="text-muted">${esc(t.service_category)}</td>
                                    <td class="text-right tabular-nums">${fmtMoney(t.price)} RON</td>
                                    <td>${statusBadge(t.status)}</td>
                                </tr>`).join('')}
                            </tbody>
                        </table>
                    </div>
                </div>`;
            }

            $('li-modal-body').innerHTML = html;
        } catch (e) {
            console.error('[leisure-invoices] modal', e);
            $('li-modal-body').innerHTML = '<div class="p-4 bg-rose-50 border border-rose-200 rounded-xl text-sm text-rose-900">Eroare la încărcare: ' + esc(e?.message || '') + '</div>';
        }
    }

    function row(label, value, extraCls = '', valueCls = 'font-medium text-secondary') {
        const v = (value == null || value === '') ? '—' : esc(value);
        return `<div class="${extraCls}">
            <div class="text-[11px] uppercase text-muted tracking-wider">${esc(label)}</div>
            <div class="${valueCls}">${v}</div>
        </div>`;
    }

    function statusBadge(status) {
        const m = {
            valid: '<span class="px-2 py-0.5 rounded bg-emerald-100 text-emerald-800 text-[10px]">valid</span>',
            used: '<span class="px-2 py-0.5 rounded bg-blue-100 text-blue-800 text-[10px]">scanat</span>',
            cancelled: '<span class="px-2 py-0.5 rounded bg-rose-100 text-rose-800 text-[10px]">anulat</span>',
            refunded: '<span class="px-2 py-0.5 rounded bg-gray-100 text-gray-800 text-[10px]">rambursat</span>',
        };
        return m[status] || esc(status);
    }

    function closeModal() {
        $('li-modal').classList.add('hidden');
        $('li-modal').classList.remove('flex');
    }

    window.addEventListener('load', async () => {
        let retries = 0;
        while (typeof AmbiletAPI === 'undefined' && retries < 10) { await new Promise(r => setTimeout(r, 100)); retries++; }
        if (typeof AmbiletAPI === 'undefined') {
            $('li-error').textContent = 'Resurse JS indisponibile — reîncarcă pagina.';
            $('li-error').classList.remove('hidden');
            return;
        }
        try {
            const res = await AmbiletAPI.get('/organizer/events');
            const events = res.data || [];
            const leisure = events.filter(e => (e.display_template || 'standard') === 'leisure_venue');
            if (leisure.length) currentEventId = leisure[0].id;
        } catch (e) { console.error(e); }
        if (!currentEventId) {
            $('li-error').textContent = 'Nu există un eveniment de tip Locație de agrement.';
            $('li-error').classList.remove('hidden');
            return;
        }
        document.querySelectorAll('.li-range-btn').forEach(b => b.addEventListener('click', () => setRange(b.dataset.range)));
        $('li-apply').addEventListener('click', applyCustom);
        let searchDebounce = null;
        $('li-search').addEventListener('input', () => { clearTimeout(searchDebounce); searchDebounce = setTimeout(loadInvoices, 400); });
        $('li-modal-close').addEventListener('click', closeModal);
        $('li-modal').addEventListener('click', (e) => { if (e.target.id === 'li-modal') closeModal(); });
        document.addEventListener('keydown', (e) => { if (e.key === 'Escape') closeModal(); });
        setRange('30');
    });
})();
</script>
<?php require_once dirname(__DIR__) . '/includes/scripts.php'; ?>
