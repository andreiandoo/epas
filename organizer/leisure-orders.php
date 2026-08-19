<?php
require_once dirname(__DIR__) . '/includes/config.php';
$pageTitle = 'Comenzi';
$bodyClass = 'min-h-screen flex bg-slate-100';
$currentPage = 'leisure_orders';
$cssBundle = 'organizer';
require_once dirname(__DIR__) . '/includes/head.php';
require_once dirname(__DIR__) . '/includes/organizer-sidebar.php';
?>
<div class="flex flex-col flex-1 min-h-screen lg:ml-0">
    <?php require_once dirname(__DIR__) . '/includes/organizer-topbar.php'; ?>
    <main class="flex-1 p-4 lg:p-8">
        <div class="mb-6 flex flex-wrap items-center justify-between gap-3">
            <div>
                <h1 class="text-2xl font-bold text-secondary lg:text-3xl">Comenzi</h1>
                <p class="mt-1 text-sm text-muted">Toate comenzile evenimentului. Click pe rând pentru a vedea biletele. Ștergerile se înregistrează în Istoric.</p>
            </div>
            <a href="/organizator/leisure-orders-history" class="inline-flex items-center gap-2 px-4 py-2 text-sm font-semibold border rounded-lg text-secondary bg-white border-border hover:bg-slate-50">
                📜 Istoric ștergeri
            </a>
        </div>

        <!-- Filter bar -->
        <div class="bg-white border rounded-2xl border-border p-4 mb-6 flex flex-wrap items-end gap-3">
            <div class="flex flex-wrap items-end gap-2">
                <label class="block">
                    <span class="text-xs font-semibold text-muted">De la data</span>
                    <input id="lo-from" type="date" class="block mt-1 px-3 py-1.5 text-sm border border-border rounded-lg">
                </label>
                <label class="block">
                    <span class="text-xs font-semibold text-muted">Până la data</span>
                    <input id="lo-to" type="date" class="block mt-1 px-3 py-1.5 text-sm border border-border rounded-lg">
                </label>
                <button id="lo-apply-range" type="button" class="px-3 py-1.5 text-xs font-semibold rounded-lg border border-primary bg-primary text-white hover:opacity-90">Aplică</button>
            </div>
            <input id="lo-search" type="text" placeholder="🔍 Caută după nr. comandă, nume, email..." class="flex-1 min-w-[220px] px-3 py-2 text-sm border border-border rounded-lg">
            <select id="lo-source" class="px-3 py-2 text-sm bg-white border border-border rounded-lg">
                <option value="">Toate sursele</option>
                <option value="pos">POS</option>
                <option value="online">Online</option>
            </select>
            <select id="lo-status" class="px-3 py-2 text-sm bg-white border border-border rounded-lg">
                <option value="">Toate statusurile</option>
                <option value="paid">Plătit</option>
                <option value="completed">Completat</option>
                <option value="pending">În așteptare</option>
                <option value="refunded">Restituit</option>
                <option value="cancelled">Anulat</option>
            </select>
        </div>

        <div id="lo-error" class="hidden mb-4 p-4 bg-rose-50 border border-rose-200 rounded-xl text-sm text-rose-900"></div>

        <!-- Orders table (accordion rows) -->
        <div class="bg-white border rounded-2xl border-border overflow-hidden">
            <div id="lo-loading" class="p-8 text-center"><div class="inline-block w-6 h-6 border-2 rounded-full border-primary border-t-transparent animate-spin"></div></div>
            <div id="lo-table-wrap" class="hidden overflow-x-auto">
                <table class="w-full text-sm">
                    <thead class="text-xs uppercase bg-slate-50 text-muted">
                        <tr>
                            <th class="px-3 py-3 text-left w-8"></th>
                            <th class="px-3 py-3 text-left">Nr. comandă</th>
                            <th class="px-3 py-3 text-left">Data plată</th>
                            <th class="px-3 py-3 text-left">Client</th>
                            <th class="px-3 py-3 text-left">Sursă</th>
                            <th class="px-3 py-3 text-left">Plată</th>
                            <th class="px-3 py-3 text-left">Operator POS</th>
                            <th class="px-3 py-3 text-right">Bilete</th>
                            <th class="px-3 py-3 text-right">Total</th>
                            <th class="px-3 py-3 text-right">Status</th>
                            <th class="px-3 py-3 text-center w-16">Acțiuni</th>
                        </tr>
                    </thead>
                    <tbody id="lo-rows" class="divide-y divide-border"></tbody>
                </table>
            </div>
            <div id="lo-empty" class="hidden p-8 text-center text-muted">Nicio comandă în perioada selectată.</div>
            <div id="lo-pagination" class="hidden px-5 py-3 border-t border-border flex items-center justify-between text-xs text-muted">
                <span id="lo-count">—</span>
                <div class="flex gap-2">
                    <button id="lo-prev" type="button" class="px-3 py-1 text-xs border rounded hover:bg-slate-50 disabled:opacity-50">← Anterior</button>
                    <button id="lo-next" type="button" class="px-3 py-1 text-xs border rounded hover:bg-slate-50 disabled:opacity-50">Următor →</button>
                </div>
            </div>
        </div>

        <!-- Delete modal -->
        <div id="lo-del-modal" class="hidden fixed inset-0 z-50 bg-black/50 flex items-center justify-center p-4">
            <div class="bg-white rounded-2xl shadow-xl max-w-lg w-full">
                <div class="px-6 py-4 border-b border-border">
                    <h3 class="font-bold text-lg text-rose-700">🗑️ Șterge comanda <span id="lo-del-order-number"></span></h3>
                </div>
                <div class="p-6 space-y-4">
                    <div class="p-3 bg-amber-50 border border-amber-200 rounded-lg text-xs text-amber-900">
                        ⚠️ Ștergerea este <strong>ireversibilă</strong>. Comanda + toate biletele + check-in-urile vor fi eliminate din DB.
                        Dacă comanda e într-o sesiune de casă închisă, snapshot-ul se recomputează automat.
                        Totul se salvează în <strong>Istoric ștergeri</strong>.
                    </div>
                    <div id="lo-del-summary" class="text-xs text-muted space-y-1"></div>
                    <label class="block">
                        <span class="text-sm font-semibold text-secondary">Motiv ștergere (obligatoriu)</span>
                        <textarea id="lo-del-note" rows="3" required minlength="3" maxlength="1000" class="mt-1 block w-full px-3 py-2 text-sm border border-border rounded-lg" placeholder="Ex: comandă duplicată accidentală, eroare operator..."></textarea>
                        <span class="text-[10px] text-muted">Min 3 caractere, max 1000. Va apărea în Istoric.</span>
                    </label>
                </div>
                <div class="px-6 py-4 border-t border-border flex justify-end gap-2">
                    <button id="lo-del-cancel" type="button" class="px-4 py-2 text-sm border rounded-lg text-secondary bg-white border-border hover:bg-slate-50">Anulează</button>
                    <button id="lo-del-confirm" type="button" class="px-4 py-2 text-sm font-bold text-white bg-rose-600 rounded-lg hover:bg-rose-700 disabled:opacity-50">🗑️ Șterge definitiv</button>
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
    let deletingOrder = null;
    const CAT_LABEL = { access: 'Acces', parking: 'Parcare', activity: 'Activitate', rental: 'Închiriere', extra: 'Extra', package: 'Pachet' };

    function fmtMoney(v) {
        return Number(v || 0).toLocaleString('ro-RO', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    }
    function fmtDateTime(iso) {
        if (!iso) return '—';
        try { const d = new Date(iso); return d.toLocaleDateString('ro-RO', { day:'2-digit', month:'2-digit', year:'2-digit' }) + ' ' + d.toLocaleTimeString('ro-RO', { hour:'2-digit', minute:'2-digit' }); } catch { return iso; }
    }
    function esc(v) { return String(v == null ? '' : v).replace(/[<>&"']/g, c => ({'<':'&lt;','>':'&gt;','&':'&amp;','"':'&quot;',"'":'&#39;'}[c])); }

    function sourceBadge(s) {
        if (s === 'pos') return '<span class="px-2 py-0.5 text-[10px] font-semibold rounded-full bg-amber-100 text-amber-800">🏪 POS</span>';
        return '<span class="px-2 py-0.5 text-[10px] font-semibold rounded-full bg-sky-100 text-sky-800">🌐 Online</span>';
    }
    function paymentBadge(pm) {
        if (!pm) return '<span class="text-muted">—</span>';
        const map = { cash: '💵 Cash', card: '💳 Card', invoice: '📧 Link email', online: '🌐 Online' };
        return '<span class="text-xs">' + (map[pm] || pm) + '</span>';
    }
    function statusBadge(s) {
        const map = {
            paid:      ['bg-emerald-100','text-emerald-800','Plătit'],
            completed: ['bg-emerald-100','text-emerald-800','Completat'],
            pending:   ['bg-amber-100','text-amber-800','În așteptare'],
            refunded:  ['bg-slate-100','text-slate-700','Restituit'],
            cancelled: ['bg-rose-100','text-rose-700','Anulat'],
        };
        const m = map[s] || ['bg-slate-100','text-slate-600', s || '—'];
        return `<span class="inline-block px-2 py-0.5 text-[10px] rounded ${m[0]} ${m[1]}">${m[2]}</span>`;
    }

    function orderRowHtml(o) {
        return `
            <tr class="lo-row hover:bg-slate-50 cursor-pointer" data-order-id="${o.id}" data-order-number="${esc(o.order_number)}" data-order-total="${o.total}" data-order-tickets="${o.tickets_count}" data-order-source="${o.source||''}" data-order-customer="${esc(o.customer_name||'')}">
                <td class="px-3 py-3 text-center"><span class="lo-caret text-slate-400 text-xs">▶</span></td>
                <td class="px-3 py-3 font-mono text-xs">${esc(o.order_number)}</td>
                <td class="px-3 py-3 text-xs">${fmtDateTime(o.paid_at)}</td>
                <td class="px-3 py-3">
                    <div class="text-sm">${esc(o.customer_name) || '—'}</div>
                    <div class="text-xs text-muted">${esc(o.customer_email) || ''}</div>
                </td>
                <td class="px-3 py-3">${sourceBadge(o.source)}</td>
                <td class="px-3 py-3">${paymentBadge(o.payment_method)}</td>
                <td class="px-3 py-3 text-xs">${esc(o.operator_name) || '—'}</td>
                <td class="px-3 py-3 text-right tabular-nums">${o.tickets_count}</td>
                <td class="px-3 py-3 text-right font-semibold tabular-nums">${fmtMoney(o.total)} <span class="text-[10px] text-muted">${o.currency||'RON'}</span></td>
                <td class="px-3 py-3 text-right">${statusBadge(o.status)}</td>
                <td class="px-3 py-3 text-center">
                    <button class="lo-del-btn px-2 py-1 text-[11px] font-semibold text-rose-700 bg-rose-50 border border-rose-200 rounded hover:bg-rose-100" data-order-id="${o.id}" data-order-number="${esc(o.order_number)}" data-order-total="${o.total}" data-order-tickets="${o.tickets_count}" data-order-source="${o.source||''}" data-order-customer="${esc(o.customer_name||'')}">🗑️ Șterge</button>
                </td>
            </tr>
            <tr class="lo-detail-row hidden bg-slate-50" data-detail-for="${o.id}">
                <td colspan="11" class="px-6 py-4">
                    <div id="lo-detail-${o.id}" class="text-xs">
                        <div class="inline-block w-4 h-4 border-2 rounded-full border-primary border-t-transparent animate-spin"></div>
                        <span class="ml-2 text-muted">Se încarcă biletele...</span>
                    </div>
                </td>
            </tr>
        `;
    }

    async function loadOrders(page = 1) {
        $('lo-error').classList.add('hidden');
        $('lo-loading').classList.remove('hidden');
        $('lo-table-wrap').classList.add('hidden');
        $('lo-empty').classList.add('hidden');
        $('lo-pagination').classList.add('hidden');
        currentPage = page;
        try {
            const params = {
                page,
                per_page: 30,
                from: $('lo-from').value || '',
                to: $('lo-to').value || '',
                search: ($('lo-search').value || '').trim(),
                source: $('lo-source').value || '',
                status: $('lo-status').value || '',
            };
            const res = await AmbiletAPI.get(`/organizer/events/${currentEventId}/leisure/orders`, params);
            const data = res.data || {};
            const orders = data.orders || [];
            const pg = data.pagination || {};
            lastPage = pg.last_page || 1;
            $('lo-loading').classList.add('hidden');
            if (orders.length === 0) {
                $('lo-empty').classList.remove('hidden');
                return;
            }
            $('lo-rows').innerHTML = orders.map(orderRowHtml).join('');
            $('lo-table-wrap').classList.remove('hidden');
            $('lo-pagination').classList.remove('hidden');
            $('lo-count').textContent = `${pg.total || 0} comenzi · pagina ${pg.current_page}/${pg.last_page}`;
            $('lo-prev').disabled = pg.current_page <= 1;
            $('lo-next').disabled = pg.current_page >= pg.last_page;
            wireRowEvents();
        } catch (e) {
            console.error('[orders]', e);
            $('lo-loading').classList.add('hidden');
            $('lo-error').textContent = 'Eroare la încărcare: ' + (e?.message || 'necunoscut');
            $('lo-error').classList.remove('hidden');
        }
    }

    function wireRowEvents() {
        document.querySelectorAll('.lo-row').forEach(row => {
            row.addEventListener('click', (e) => {
                if (e.target.closest('.lo-del-btn')) return; // click pe delete btn nu expandeaza
                toggleDetail(row);
            });
        });
        document.querySelectorAll('.lo-del-btn').forEach(btn => {
            btn.addEventListener('click', (e) => {
                e.stopPropagation();
                openDeleteModal(btn);
            });
        });
    }

    async function toggleDetail(row) {
        const orderId = row.dataset.orderId;
        const detailRow = document.querySelector(`.lo-detail-row[data-detail-for="${orderId}"]`);
        const caret = row.querySelector('.lo-caret');
        if (!detailRow) return;
        const isOpen = !detailRow.classList.contains('hidden');
        if (isOpen) {
            detailRow.classList.add('hidden');
            if (caret) caret.textContent = '▶';
            return;
        }
        detailRow.classList.remove('hidden');
        if (caret) caret.textContent = '▼';
        // Load tickets daca nu-s incarcate
        const detailEl = document.getElementById(`lo-detail-${orderId}`);
        if (!detailEl.dataset.loaded) {
            try {
                const res = await AmbiletAPI.get(`/organizer/events/${currentEventId}/leisure/orders/${orderId}`);
                const o = res.data || {};
                const tickets = o.tickets || [];
                if (tickets.length === 0) {
                    detailEl.innerHTML = '<p class="text-muted">Nicio linie de bilet.</p>';
                } else {
                    detailEl.innerHTML = `
                        <div class="mb-3 text-[11px] text-muted">Bilete emise (${tickets.length}):</div>
                        <table class="w-full text-xs">
                            <thead class="text-[10px] uppercase text-slate-500">
                                <tr>
                                    <th class="px-2 py-1.5 text-left">Cod</th>
                                    <th class="px-2 py-1.5 text-left">Tip bilet</th>
                                    <th class="px-2 py-1.5 text-left">Categorie</th>
                                    <th class="px-2 py-1.5 text-right">Preț</th>
                                    <th class="px-2 py-1.5 text-left">Status</th>
                                    <th class="px-2 py-1.5 text-left">Check-in</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-200">
                                ${tickets.map(t => `
                                    <tr>
                                        <td class="px-2 py-1.5 font-mono">${esc(t.code) || '—'}</td>
                                        <td class="px-2 py-1.5">${esc(t.ticket_type)}${t.from_package ? ' <span class="text-[9px] text-slate-500">(din pachet)</span>' : ''}${t.is_umbrella ? ' <span class="text-[9px] text-indigo-600">(pachet)</span>' : ''}</td>
                                        <td class="px-2 py-1.5">${CAT_LABEL[t.service_category] || t.service_category}</td>
                                        <td class="px-2 py-1.5 text-right tabular-nums">${fmtMoney(t.price)}</td>
                                        <td class="px-2 py-1.5">${statusBadge(t.status)}</td>
                                        <td class="px-2 py-1.5">${t.checked_in_at ? '<span class="text-emerald-700">✓ ' + fmtDateTime(t.checked_in_at) + '</span>' : '<span class="text-slate-400">— neefectuat</span>'}</td>
                                    </tr>
                                `).join('')}
                            </tbody>
                        </table>
                        ${o.customer_phone ? '<p class="mt-3 text-[11px] text-muted">Telefon client: <strong>' + esc(o.customer_phone) + '</strong></p>' : ''}
                    `;
                }
                detailEl.dataset.loaded = '1';
            } catch (e) {
                detailEl.innerHTML = '<p class="text-rose-700">Eroare la încărcarea biletelor: ' + esc(e?.message) + '</p>';
            }
        }
    }

    function openDeleteModal(btn) {
        deletingOrder = {
            id: btn.dataset.orderId,
            number: btn.dataset.orderNumber,
            total: btn.dataset.orderTotal,
            tickets: btn.dataset.orderTickets,
            source: btn.dataset.orderSource,
            customer: btn.dataset.orderCustomer,
        };
        $('lo-del-order-number').textContent = deletingOrder.number;
        $('lo-del-summary').innerHTML = `
            <div>Client: <strong>${esc(deletingOrder.customer) || '—'}</strong></div>
            <div>Total: <strong>${fmtMoney(deletingOrder.total)} RON</strong></div>
            <div>Bilete: <strong>${deletingOrder.tickets}</strong></div>
            <div>Sursă: ${sourceBadge(deletingOrder.source)}</div>
        `;
        $('lo-del-note').value = '';
        $('lo-del-modal').classList.remove('hidden');
        $('lo-del-note').focus();
    }
    function closeDeleteModal() {
        $('lo-del-modal').classList.add('hidden');
        deletingOrder = null;
    }
    async function confirmDelete() {
        const note = ($('lo-del-note').value || '').trim();
        if (note.length < 3) { alert('Motivul e obligatoriu (min. 3 caractere).'); $('lo-del-note').focus(); return; }
        const btn = $('lo-del-confirm');
        btn.disabled = true; btn.textContent = '⏳ Se șterge...';
        try {
            const proxyBase = (window.AMBILET && window.AMBILET.apiUrl) || '/api/proxy.php';
            const url = proxyBase + '?action=organizer.event.leisure.orders.destroy&event=' + encodeURIComponent(currentEventId) + '&order_id=' + encodeURIComponent(deletingOrder.id);
            const token = localStorage.getItem('ambilet_organizer_token') || localStorage.getItem('organizer_token') || '';
            const resp = await fetch(url, {
                method: 'DELETE',
                headers: Object.assign({ 'Content-Type': 'application/json' }, token ? { 'Authorization': 'Bearer ' + token } : {}),
                body: JSON.stringify({ note }),
            });
            const data = await resp.json();
            if (!resp.ok) throw new Error(data?.message || ('HTTP ' + resp.status));
            const regen = data?.data?.cashier_snapshot_regenerated;
            alert('✅ Comanda ' + deletingOrder.number + ' a fost ștearsă.' + (regen ? '\nSnapshot-ul sesiunii de casă a fost recomputat automat.' : ''));
            closeDeleteModal();
            loadOrders(currentPage);
        } catch (e) {
            alert('Eroare: ' + (e?.message || 'necunoscut'));
        } finally {
            btn.disabled = false; btn.textContent = '🗑️ Șterge definitiv';
        }
    }

    window.addEventListener('load', async () => {
        let retries = 0;
        while (typeof AmbiletAPI === 'undefined' && retries < 10) { await new Promise(r => setTimeout(r, 100)); retries++; }
        if (typeof AmbiletAPI === 'undefined') { $('lo-loading').innerHTML = 'API indisponibil.'; return; }
        try {
            const res = await AmbiletAPI.get('/organizer/events');
            const events = res.data || [];
            const leisure = events.filter(e => (e.display_template || 'standard') === 'leisure_venue');
            if (leisure.length) currentEventId = leisure[0].id;
        } catch (e) { console.error(e); }
        if (!currentEventId) {
            $('lo-loading').innerHTML = 'Nu există un eveniment de tip Locație de agrement asociat.';
            return;
        }
        // Default range: ultimele 30 zile
        const to = new Date();
        const from = new Date(); from.setDate(from.getDate() - 30);
        $('lo-from').value = from.toISOString().slice(0,10);
        $('lo-to').value = to.toISOString().slice(0,10);

        $('lo-apply-range').addEventListener('click', () => loadOrders(1));
        $('lo-search').addEventListener('input', () => {
            clearTimeout(searchTimer);
            searchTimer = setTimeout(() => loadOrders(1), 400);
        });
        $('lo-source').addEventListener('change', () => loadOrders(1));
        $('lo-status').addEventListener('change', () => loadOrders(1));
        $('lo-prev').addEventListener('click', () => currentPage > 1 && loadOrders(currentPage - 1));
        $('lo-next').addEventListener('click', () => currentPage < lastPage && loadOrders(currentPage + 1));
        $('lo-del-cancel').addEventListener('click', closeDeleteModal);
        $('lo-del-confirm').addEventListener('click', confirmDelete);
        $('lo-del-modal').addEventListener('click', (e) => { if (e.target === $('lo-del-modal')) closeDeleteModal(); });

        loadOrders(1);
    });
})();
</script>
<?php require_once dirname(__DIR__) . '/includes/scripts.php'; ?>
