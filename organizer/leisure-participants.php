<?php
require_once dirname(__DIR__) . '/includes/config.php';
$pageTitle = 'Participanți';
$bodyClass = 'min-h-screen flex bg-slate-100';
$currentPage = 'leisure_participants';
$cssBundle = 'organizer';
require_once dirname(__DIR__) . '/includes/head.php';
require_once dirname(__DIR__) . '/includes/organizer-sidebar.php';
?>
<div class="flex flex-col flex-1 min-h-screen lg:ml-0">
    <?php require_once dirname(__DIR__) . '/includes/organizer-topbar.php'; ?>
    <main class="flex-1 p-4 lg:p-8">
        <div class="mb-6">
            <h1 class="text-2xl font-bold text-secondary lg:text-3xl">Participanți</h1>
            <p class="mt-1 text-sm text-muted">Vizitatorii care au cumpărat bilete pentru locația ta.</p>
        </div>

        <!-- Date range filter -->
        <div class="bg-white border rounded-2xl border-border p-4 mb-6">
            <div class="flex flex-wrap items-end gap-2">
                <div class="flex-1 min-w-[200px]">
                    <p class="text-xs uppercase tracking-wider text-muted font-semibold mb-2">Filtru perioadă</p>
                    <div class="flex flex-wrap gap-2">
                        <button data-range="7" class="lv-range-btn px-3 py-1.5 text-xs font-medium rounded-lg border border-border bg-white hover:bg-slate-50">Ultimele 7 zile</button>
                        <button data-range="14" class="lv-range-btn px-3 py-1.5 text-xs font-medium rounded-lg border border-border bg-white hover:bg-slate-50">14 zile</button>
                        <button data-range="30" class="lv-range-btn px-3 py-1.5 text-xs font-medium rounded-lg border border-border bg-white hover:bg-slate-50">1 lună</button>
                        <button data-range="90" class="lv-range-btn px-3 py-1.5 text-xs font-medium rounded-lg border border-border bg-white hover:bg-slate-50">3 luni</button>
                        <button data-range="180" class="lv-range-btn px-3 py-1.5 text-xs font-medium rounded-lg border border-border bg-white hover:bg-slate-50">6 luni</button>
                        <button data-range="custom" class="lv-range-btn px-3 py-1.5 text-xs font-medium rounded-lg border border-border bg-white hover:bg-slate-50">Custom</button>
                    </div>
                </div>
                <div id="lv-custom-range" class="hidden flex items-end gap-2">
                    <label class="block">
                        <span class="text-xs font-semibold text-muted">De la</span>
                        <input id="lv-from" type="date" class="block mt-1 px-3 py-1.5 text-sm border border-border rounded-lg">
                    </label>
                    <label class="block">
                        <span class="text-xs font-semibold text-muted">Până la</span>
                        <input id="lv-to" type="date" class="block mt-1 px-3 py-1.5 text-sm border border-border rounded-lg">
                    </label>
                    <button id="lv-apply-custom" class="px-3 py-1.5 bg-primary text-white text-xs font-medium rounded-lg hover:bg-primary-dark">Aplică</button>
                </div>
                <div class="text-xs text-muted">
                    <span id="lv-range-label">Ultimele 7 zile</span>
                </div>
            </div>
        </div>

        <!-- Stats -->
        <div class="grid grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
            <div class="p-4 bg-white border rounded-2xl border-border">
                <p class="text-xs uppercase tracking-wider text-muted font-semibold mb-1">Total participanți</p>
                <p class="text-2xl font-bold text-secondary"><span id="lv-stat-total">0</span></p>
            </div>
            <div class="p-4 bg-white border rounded-2xl border-border">
                <p class="text-xs uppercase tracking-wider text-muted font-semibold mb-1">Check-in efectuat</p>
                <p class="text-2xl font-bold text-emerald-600"><span id="lv-stat-checked">0</span></p>
            </div>
            <div class="p-4 bg-white border rounded-2xl border-border">
                <p class="text-xs uppercase tracking-wider text-muted font-semibold mb-1">Rata prezență</p>
                <p class="text-2xl font-bold text-secondary"><span id="lv-stat-rate">0</span>%</p>
            </div>
            <div class="p-4 bg-white border rounded-2xl border-border">
                <p class="text-xs uppercase tracking-wider text-muted font-semibold mb-1">No-show</p>
                <p class="text-2xl font-bold text-amber-600"><span id="lv-stat-noshow">0</span></p>
            </div>
        </div>

        <!-- Search + table -->
        <div class="bg-white border rounded-2xl border-border">
            <div class="px-5 py-4 border-b border-border flex flex-wrap items-center gap-3">
                <input id="lv-search" type="text" placeholder="🔍 Caută după nume, email, cod bilet..." class="flex-1 min-w-[200px] px-3 py-2 text-sm border border-border rounded-lg">
                <button id="lv-export" class="px-3 py-2 text-xs font-medium bg-white border border-border rounded-lg hover:bg-slate-50">📥 Export CSV</button>
            </div>
            <div id="lv-loading" class="p-8 text-center"><div class="inline-block w-6 h-6 border-2 rounded-full border-primary border-t-transparent animate-spin"></div></div>
            <div id="lv-table-wrap" class="hidden overflow-x-auto">
                <table class="w-full text-sm">
                    <thead class="text-xs uppercase bg-slate-50 text-muted">
                        <tr>
                            <th class="px-5 py-3 text-left">Cod bilet</th>
                            <th class="px-5 py-3 text-left">Nume / Email</th>
                            <th class="px-5 py-3 text-left">Tip bilet</th>
                            <th class="px-5 py-3 text-left">Data vizită</th>
                            <th class="px-5 py-3 text-left">Status</th>
                            <th class="px-5 py-3 text-left">Check-in</th>
                        </tr>
                    </thead>
                    <tbody id="lv-rows" class="divide-y divide-border"></tbody>
                </table>
            </div>
            <div id="lv-empty" class="hidden p-8 text-center text-muted">Niciun participant în perioada selectată.</div>
        </div>

    </main>
</div>
<script>
(function(){
    const $ = (id) => document.getElementById(id);
    let currentRange = '7';
    let currentEventId = null;
    let currentFrom = null;
    let currentTo = null;
    let allRows = [];
    let searchTimer = null;

    function fmtDate(iso) {
        if (!iso) return '—';
        return (window.AmbiletFmt?.datetime(iso)) || iso;
    }

    function fmtDay(iso) {
        if (!iso) return '—';
        return (window.AmbiletFmt?.date(iso)) || iso;
    }

    function statusBadge(s) {
        const map = {
            'valid':    ['bg-emerald-100','text-emerald-800','Valid'],
            'used':     ['bg-slate-100','text-slate-700','Folosit'],
            'cancelled':['bg-rose-100','text-rose-700','Anulat'],
            'refunded': ['bg-amber-100','text-amber-800','Restituit'],
        };
        const m = map[s] || ['bg-slate-100','text-slate-600', s || '—'];
        return `<span class="inline-block px-2 py-0.5 text-xs rounded-full ${m[0]} ${m[1]}">${m[2]}</span>`;
    }

    function categoryBadge(c) {
        const map = {
            'access':   ['bg-blue-100','text-blue-800','Acces'],
            'parking':  ['bg-violet-100','text-violet-800','Parcare'],
            'rental':   ['bg-amber-100','text-amber-800','Închiriere'],
            'activity': ['bg-emerald-100','text-emerald-800','Activitate'],
            'extra':    ['bg-slate-100','text-slate-700','Extra'],
        };
        const m = map[c] || ['bg-slate-100','text-slate-600', c || ''];
        return `<span class="inline-block px-1.5 py-0.5 text-[10px] font-semibold rounded ${m[0]} ${m[1]}">${m[2]}</span>`;
    }

    function renderRows(rows) {
        const tbody = $('lv-rows');
        if (!rows || rows.length === 0) {
            tbody.innerHTML = '';
            $('lv-table-wrap').classList.add('hidden');
            $('lv-empty').classList.remove('hidden');
            return;
        }
        tbody.innerHTML = rows.map(r => `
            <tr class="hover:bg-slate-50">
                <td class="px-5 py-3 font-mono text-xs">${r.code || r.barcode || '—'}</td>
                <td class="px-5 py-3">
                    <div class="font-medium text-secondary">${r.customer_name || '—'}</div>
                    <div class="text-xs text-muted">${r.customer_email || ''}</div>
                </td>
                <td class="px-5 py-3">
                    <div class="text-sm">${r.ticket_type || '—'}</div>
                    <div class="mt-0.5">${categoryBadge(r.service_category)}</div>
                </td>
                <td class="px-5 py-3 text-sm">${fmtDay(r.visit_date)}</td>
                <td class="px-5 py-3">${statusBadge(r.status)}</td>
                <td class="px-5 py-3 text-xs">${r.checked_in_at ? fmtDate(r.checked_in_at) : '<span class="text-muted">— neefectuat</span>'}</td>
            </tr>
        `).join('');
        $('lv-table-wrap').classList.remove('hidden');
        $('lv-empty').classList.add('hidden');
    }

    function applyClientSearch() {
        const q = ($('lv-search').value || '').trim().toLowerCase();
        if (!q) return renderRows(allRows);
        const filtered = allRows.filter(r =>
            (r.code || '').toLowerCase().includes(q) ||
            (r.barcode || '').toLowerCase().includes(q) ||
            (r.customer_name || '').toLowerCase().includes(q) ||
            (r.customer_email || '').toLowerCase().includes(q) ||
            (r.ticket_type || '').toLowerCase().includes(q)
        );
        renderRows(filtered);
    }

    function setRange(days) {
        currentRange = days;
        document.querySelectorAll('.lv-range-btn').forEach(b => b.classList.remove('bg-primary', 'text-white', 'border-primary'));
        const btn = document.querySelector(`.lv-range-btn[data-range="${days}"]`);
        if (btn) btn.classList.add('bg-primary', 'text-white', 'border-primary');

        if (days === 'custom') {
            $('lv-custom-range').classList.remove('hidden');
            $('lv-custom-range').classList.add('flex');
            $('lv-range-label').textContent = 'Perioadă custom';
        } else {
            $('lv-custom-range').classList.add('hidden');
            $('lv-custom-range').classList.remove('flex');
            const labels = { '7': 'Ultimele 7 zile', '14': 'Ultimele 14 zile', '30': 'Ultima lună', '90': 'Ultimele 3 luni', '180': 'Ultimele 6 luni' };
            $('lv-range-label').textContent = labels[days] || `${days} zile`;
            const to = new Date();
            const from = new Date(Date.now() - parseInt(days, 10) * 86400000);
            currentFrom = from.toISOString().slice(0, 10);
            currentTo = to.toISOString().slice(0, 10);
            loadParticipants();
        }
    }

    async function loadParticipants() {
        $('lv-loading').classList.remove('hidden');
        $('lv-table-wrap').classList.add('hidden');
        $('lv-empty').classList.add('hidden');

        if (!currentEventId) {
            $('lv-loading').classList.add('hidden');
            $('lv-empty').textContent = 'Nu există un eveniment de tip Lacul / Locație de agrement asociat.';
            $('lv-empty').classList.remove('hidden');
            return;
        }
        try {
            const params = {};
            if (currentFrom) params.from = currentFrom;
            if (currentTo) params.to = currentTo;
            params.per_page = 200;
            const res = await AmbiletAPI.get(`/organizer/events/${currentEventId}/leisure/participants`, params);
            const data = res.data || {};
            const stats = data.stats || {};
            $('lv-stat-total').textContent = stats.total || 0;
            $('lv-stat-checked').textContent = stats.checked_in || 0;
            $('lv-stat-rate').textContent = stats.rate || 0;
            $('lv-stat-noshow').textContent = stats.no_show || 0;
            allRows = data.rows || [];
            applyClientSearch();
        } catch (e) {
            console.error('[leisure-participants] load failed', e);
            allRows = [];
            $('lv-empty').textContent = 'Eroare la încărcarea datelor. Verifică consola.';
            $('lv-empty').classList.remove('hidden');
        } finally {
            $('lv-loading').classList.add('hidden');
        }
    }

    function exportCsv() {
        const rows = allRows;
        if (!rows.length) { alert('Niciun rând de exportat.'); return; }
        const header = ['Cod', 'Nume', 'Email', 'Tip bilet', 'Categorie', 'Societate', 'Data vizita', 'Status', 'Check-in'];
        const csv = [header.join(',')].concat(rows.map(r => [
            r.code || r.barcode || '',
            (r.customer_name || '').replace(/"/g, '""'),
            r.customer_email || '',
            (r.ticket_type || '').replace(/"/g, '""'),
            r.service_category || '',
            r.issuing_company || '',
            r.visit_date || '',
            r.status || '',
            r.checked_in_at || '',
        ].map(v => `"${v}"`).join(','))).join('\n');
        const blob = new Blob(["﻿" + csv], { type: 'text/csv;charset=utf-8;' });
        const url = URL.createObjectURL(blob);
        const a = document.createElement('a');
        a.href = url;
        a.download = `participanti_${currentFrom || ''}_${currentTo || ''}.csv`;
        a.click();
        URL.revokeObjectURL(url);
    }

    window.addEventListener('load', async () => {
        let retries = 0;
        while (typeof AmbiletAPI === 'undefined' && retries < 10) { await new Promise(r => setTimeout(r, 100)); retries++; }
        if (typeof AmbiletAPI === 'undefined') {
            $('lv-loading').classList.add('hidden');
            $('lv-empty').textContent = 'API indisponibil — reîncarcă pagina.';
            $('lv-empty').classList.remove('hidden');
            return;
        }
        try {
            const res = await AmbiletAPI.get('/organizer/events');
            const events = res.data || [];
            const leisure = events.filter(e => (e.display_template || 'standard') === 'leisure_venue');
            if (leisure.length > 0) currentEventId = leisure[0].id;
        } catch (e) { console.error(e); }

        document.querySelectorAll('.lv-range-btn').forEach(btn => {
            btn.addEventListener('click', () => setRange(btn.dataset.range));
        });
        $('lv-apply-custom').addEventListener('click', () => {
            const f = $('lv-from').value;
            const t = $('lv-to').value;
            if (!f || !t) return;
            currentFrom = f;
            currentTo = t;
            $('lv-range-label').textContent = `${f} → ${t}`;
            loadParticipants();
        });
        $('lv-search').addEventListener('input', () => {
            clearTimeout(searchTimer);
            searchTimer = setTimeout(applyClientSearch, 150);
        });
        $('lv-export').addEventListener('click', exportCsv);
        setRange('7');
    });
})();
</script>
<?php
require_once dirname(__DIR__) . '/includes/scripts.php';
?>
