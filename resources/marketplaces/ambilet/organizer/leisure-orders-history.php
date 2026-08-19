<?php
require_once dirname(__DIR__) . '/includes/config.php';
$pageTitle = 'Istoric ștergeri comenzi';
$bodyClass = 'min-h-screen flex bg-slate-100';
$currentPage = 'leisure_orders_history';
$cssBundle = 'organizer';
require_once dirname(__DIR__) . '/includes/head.php';
require_once dirname(__DIR__) . '/includes/organizer-sidebar.php';
?>
<div class="flex flex-col flex-1 min-h-screen lg:ml-0">
    <?php require_once dirname(__DIR__) . '/includes/organizer-topbar.php'; ?>
    <main class="flex-1 p-4 lg:p-8">
        <div class="mb-6 flex flex-wrap items-center justify-between gap-3">
            <div>
                <h1 class="text-2xl font-bold text-secondary lg:text-3xl">📜 Istoric ștergeri comenzi</h1>
                <p class="mt-1 text-sm text-muted">Toate ștergerile de comenzi + motiv + user + detalii complete.</p>
            </div>
            <a href="/organizator/leisure-orders" class="inline-flex items-center gap-2 px-4 py-2 text-sm font-semibold border rounded-lg text-secondary bg-white border-border hover:bg-slate-50">
                ← Înapoi la Comenzi
            </a>
        </div>

        <!-- Filter -->
        <div class="bg-white border rounded-2xl border-border p-4 mb-6 flex flex-wrap items-end gap-3">
            <div class="flex flex-wrap items-end gap-2">
                <label class="block">
                    <span class="text-xs font-semibold text-muted">De la data</span>
                    <input id="lh-from" type="date" class="block mt-1 px-3 py-1.5 text-sm border border-border rounded-lg">
                </label>
                <label class="block">
                    <span class="text-xs font-semibold text-muted">Până la data</span>
                    <input id="lh-to" type="date" class="block mt-1 px-3 py-1.5 text-sm border border-border rounded-lg">
                </label>
                <button id="lh-apply-range" type="button" class="px-3 py-1.5 text-xs font-semibold rounded-lg border border-primary bg-primary text-white hover:opacity-90">Aplică</button>
            </div>
            <input id="lh-search" type="text" placeholder="🔍 Caută după nr. comandă, nume, motiv..." class="flex-1 min-w-[220px] px-3 py-2 text-sm border border-border rounded-lg">
        </div>

        <div id="lh-error" class="hidden mb-4 p-4 bg-rose-50 border border-rose-200 rounded-xl text-sm text-rose-900"></div>

        <div class="bg-white border rounded-2xl border-border overflow-hidden">
            <div id="lh-loading" class="p-8 text-center"><div class="inline-block w-6 h-6 border-2 rounded-full border-primary border-t-transparent animate-spin"></div></div>
            <div id="lh-table-wrap" class="hidden overflow-x-auto">
                <table class="w-full text-sm">
                    <thead class="text-xs uppercase bg-slate-50 text-muted">
                        <tr>
                            <th class="px-3 py-3 text-left">Ștearsă la</th>
                            <th class="px-3 py-3 text-left">Nr. comandă</th>
                            <th class="px-3 py-3 text-left">Plasată la</th>
                            <th class="px-3 py-3 text-left">Client</th>
                            <th class="px-3 py-3 text-left">Sursă / Plată</th>
                            <th class="px-3 py-3 text-left">Operator POS</th>
                            <th class="px-3 py-3 text-right">Bilete</th>
                            <th class="px-3 py-3 text-right">Valoare</th>
                            <th class="px-3 py-3 text-left">Ștearsă de</th>
                            <th class="px-3 py-3 text-left">Motiv</th>
                            <th class="px-3 py-3 text-center">Snapshot</th>
                        </tr>
                    </thead>
                    <tbody id="lh-rows" class="divide-y divide-border"></tbody>
                </table>
            </div>
            <div id="lh-empty" class="hidden p-8 text-center text-muted">Nicio ștergere în perioada selectată.</div>
            <div id="lh-pagination" class="hidden px-5 py-3 border-t border-border flex items-center justify-between text-xs text-muted">
                <span id="lh-count">—</span>
                <div class="flex gap-2">
                    <button id="lh-prev" type="button" class="px-3 py-1 text-xs border rounded hover:bg-slate-50 disabled:opacity-50">← Anterior</button>
                    <button id="lh-next" type="button" class="px-3 py-1 text-xs border rounded hover:bg-slate-50 disabled:opacity-50">Următor →</button>
                </div>
            </div>
        </div>
    </main>
</div>
<script>
(function(){
    const $ = (id) => document.getElementById(id);
    let currentEventId = null;
    let currentPage = 1;
    let lastPage = 1;
    let searchTimer = null;

    function fmtMoney(v) { return Number(v || 0).toLocaleString('ro-RO', { minimumFractionDigits: 2, maximumFractionDigits: 2 }); }
    function fmtDateTime(iso) {
        if (!iso) return '—';
        try { const d = new Date(iso); return d.toLocaleDateString('ro-RO', { day:'2-digit', month:'2-digit', year:'numeric' }) + ' ' + d.toLocaleTimeString('ro-RO', { hour:'2-digit', minute:'2-digit', second:'2-digit' }); } catch { return iso; }
    }
    function esc(v) { return String(v == null ? '' : v).replace(/[<>&"']/g, c => ({'<':'&lt;','>':'&gt;','&':'&amp;','"':'&quot;',"'":'&#39;'}[c])); }
    function sourceBadge(s) {
        if (s === 'pos') return '<span class="px-2 py-0.5 text-[10px] font-semibold rounded-full bg-amber-100 text-amber-800">🏪 POS</span>';
        return '<span class="px-2 py-0.5 text-[10px] font-semibold rounded-full bg-sky-100 text-sky-800">🌐 Online</span>';
    }
    function paymentLabel(pm) {
        const map = { cash: '💵 Cash', card: '💳 Card', invoice: '📧 Link email', online: '🌐 Online' };
        return pm ? '<div class="text-[10px] text-muted mt-0.5">' + (map[pm] || pm) + '</div>' : '';
    }
    function actorBadge(type, name, email) {
        const t = type === 'team_member' ? '👤 Angajat' : (type === 'admin' ? '👨‍💼 Admin' : '👥 Organizator');
        return `<div class="text-xs"><strong>${esc(name) || '—'}</strong>${email ? '<div class="text-[10px] text-muted">'+esc(email)+'</div>' : ''}<div class="text-[10px] text-muted">${t}</div></div>`;
    }

    function rowHtml(l) {
        const regen = l.cashier_snapshot_regenerated
            ? '<span class="px-2 py-0.5 text-[10px] font-semibold text-emerald-700 bg-emerald-50 border border-emerald-200 rounded" title="Snapshot-ul sesiunii de casă a fost recomputat">✓ Recomp.</span>'
            : '<span class="text-[10px] text-muted">—</span>';
        return `
            <tr class="hover:bg-slate-50">
                <td class="px-3 py-3 text-xs">${fmtDateTime(l.deleted_at)}</td>
                <td class="px-3 py-3 font-mono text-xs">${esc(l.order_number)}</td>
                <td class="px-3 py-3 text-xs">${fmtDateTime(l.order_paid_at)}</td>
                <td class="px-3 py-3">
                    <div class="text-sm">${esc(l.customer_name) || '—'}</div>
                    <div class="text-xs text-muted">${esc(l.customer_email) || ''}</div>
                    ${l.customer_phone ? '<div class="text-[10px] text-muted">📞 '+esc(l.customer_phone)+'</div>' : ''}
                </td>
                <td class="px-3 py-3">
                    ${sourceBadge(l.order_source)}
                    ${paymentLabel(l.payment_method)}
                </td>
                <td class="px-3 py-3 text-xs">${esc(l.cashier_operator_name) || '—'}${l.cashier_session_id ? '<div class="text-[10px] text-muted">Sesiune #'+l.cashier_session_id+'</div>' : ''}</td>
                <td class="px-3 py-3 text-right tabular-nums">${l.tickets_count || 0}</td>
                <td class="px-3 py-3 text-right font-semibold tabular-nums">${fmtMoney(l.order_total)} <span class="text-[10px] text-muted">${l.order_currency||'RON'}</span></td>
                <td class="px-3 py-3">${actorBadge(l.deleted_by_type, l.deleted_by_name, l.deleted_by_email)}</td>
                <td class="px-3 py-3 text-xs max-w-xs">
                    <div class="p-2 bg-amber-50 border border-amber-200 rounded text-amber-900 whitespace-pre-wrap break-words">${esc(l.note)}</div>
                </td>
                <td class="px-3 py-3 text-center">${regen}</td>
            </tr>
        `;
    }

    async function loadHistory(page = 1) {
        $('lh-error').classList.add('hidden');
        $('lh-loading').classList.remove('hidden');
        $('lh-table-wrap').classList.add('hidden');
        $('lh-empty').classList.add('hidden');
        $('lh-pagination').classList.add('hidden');
        currentPage = page;
        try {
            const params = {
                page,
                per_page: 30,
                from: $('lh-from').value || '',
                to: $('lh-to').value || '',
                search: ($('lh-search').value || '').trim(),
            };
            const res = await AmbiletAPI.get(`/organizer/events/${currentEventId}/leisure/orders/deletion-history`, params);
            const data = res.data || {};
            const items = data.items || [];
            const pg = data.pagination || {};
            lastPage = pg.last_page || 1;
            $('lh-loading').classList.add('hidden');
            if (items.length === 0) {
                $('lh-empty').classList.remove('hidden');
                return;
            }
            $('lh-rows').innerHTML = items.map(rowHtml).join('');
            $('lh-table-wrap').classList.remove('hidden');
            $('lh-pagination').classList.remove('hidden');
            $('lh-count').textContent = `${pg.total || 0} ștergeri · pagina ${pg.current_page}/${pg.last_page}`;
            $('lh-prev').disabled = pg.current_page <= 1;
            $('lh-next').disabled = pg.current_page >= pg.last_page;
        } catch (e) {
            console.error('[history]', e);
            $('lh-loading').classList.add('hidden');
            $('lh-error').textContent = 'Eroare la încărcare: ' + (e?.message || 'necunoscut');
            $('lh-error').classList.remove('hidden');
        }
    }

    window.addEventListener('load', async () => {
        let retries = 0;
        while (typeof AmbiletAPI === 'undefined' && retries < 10) { await new Promise(r => setTimeout(r, 100)); retries++; }
        if (typeof AmbiletAPI === 'undefined') { $('lh-loading').innerHTML = 'API indisponibil.'; return; }
        try {
            const res = await AmbiletAPI.get('/organizer/events');
            const events = res.data || [];
            const leisure = events.filter(e => (e.display_template || 'standard') === 'leisure_venue');
            if (leisure.length) currentEventId = leisure[0].id;
        } catch (e) { console.error(e); }
        if (!currentEventId) { $('lh-loading').innerHTML = 'Nu există eveniment leisure.'; return; }
        const to = new Date();
        const from = new Date(); from.setDate(from.getDate() - 60);
        $('lh-from').value = from.toISOString().slice(0,10);
        $('lh-to').value = to.toISOString().slice(0,10);
        $('lh-apply-range').addEventListener('click', () => loadHistory(1));
        $('lh-search').addEventListener('input', () => {
            clearTimeout(searchTimer);
            searchTimer = setTimeout(() => loadHistory(1), 400);
        });
        $('lh-prev').addEventListener('click', () => currentPage > 1 && loadHistory(currentPage - 1));
        $('lh-next').addEventListener('click', () => currentPage < lastPage && loadHistory(currentPage + 1));
        loadHistory(1);
    });
})();
</script>
<?php require_once dirname(__DIR__) . '/includes/scripts.php'; ?>
