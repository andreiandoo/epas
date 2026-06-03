<?php
/**
 * bilete.online — Organizator › Vânzări (v3).
 * Route: /organizator/vanzari
 *
 * Lists all of the organizer's orders (event tickets + activity bookings),
 * filterable by activity, status, date range and free-text search, with live
 * stats and CSV export. Ported from the ambilet organizer sales page, restyled
 * to the bilete.online v3 design and wired to BileteOnlineAPI.organizer.
 */
require_once dirname(__DIR__) . '/includes/config.php';
$pageTitle   = 'Vânzări';
$currentPage = 'sales';
require_once dirname(__DIR__) . '/includes/head.php';
require_once dirname(__DIR__) . '/includes/organizer-sidebar.php';
?>
<div class="flex min-w-0 flex-1 flex-col">
    <?php require_once dirname(__DIR__) . '/includes/organizer-topbar.php'; ?>

    <main class="flex-1 p-4 lg:p-8">
        <!-- Header -->
        <div class="mb-6 flex flex-col justify-between gap-4 lg:flex-row lg:items-center">
            <div>
                <h1 class="font-display text-3xl font-bold leading-none">Vânzări</h1>
                <p class="mt-1.5 text-sm text-ink-soft">Toate comenzile și rezervările tale într-un singur loc.</p>
            </div>
            <button onclick="exportSales()" class="inline-flex items-center justify-center gap-2 rounded-full border-2 border-ink px-5 py-2.5 text-sm font-bold transition hover:bg-ink hover:text-paper">
                <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/></svg>
                Export CSV
            </button>
        </div>

        <!-- Filters -->
        <div class="mb-6 rounded-2xl border-2 border-ink bg-paper p-4">
            <div class="grid grid-cols-1 gap-4 md:grid-cols-2 lg:grid-cols-5">
                <label class="block">
                    <span class="mb-1.5 block text-xs font-bold text-ink-soft">Activitate</span>
                    <select id="filter-event" class="w-full rounded-xl border-2 border-ink/15 bg-paper-2 px-3 py-2.5 text-sm font-medium outline-none transition focus:border-ink" onchange="resetAndLoad()">
                        <option value="">Toate activitățile</option>
                    </select>
                </label>
                <label class="block">
                    <span class="mb-1.5 block text-xs font-bold text-ink-soft">Status</span>
                    <select id="filter-status" class="w-full rounded-xl border-2 border-ink/15 bg-paper-2 px-3 py-2.5 text-sm font-medium outline-none transition focus:border-ink" onchange="resetAndLoad()">
                        <option value="">Toate statusurile</option>
                        <option value="completed" selected>Finalizate</option>
                        <option value="pending">În așteptare</option>
                        <option value="failed">Eșuate</option>
                        <option value="expired">Expirate</option>
                        <option value="cancelled">Anulate</option>
                        <option value="refunded">Rambursate</option>
                    </select>
                </label>
                <label class="block">
                    <span class="mb-1.5 block text-xs font-bold text-ink-soft">De la data</span>
                    <input type="date" id="filter-from" class="w-full rounded-xl border-2 border-ink/15 bg-paper-2 px-3 py-2.5 text-sm font-medium outline-none transition focus:border-ink" onchange="resetAndLoad()">
                </label>
                <label class="block">
                    <span class="mb-1.5 block text-xs font-bold text-ink-soft">Până la data</span>
                    <input type="date" id="filter-to" class="w-full rounded-xl border-2 border-ink/15 bg-paper-2 px-3 py-2.5 text-sm font-medium outline-none transition focus:border-ink" onchange="resetAndLoad()">
                </label>
                <label class="block">
                    <span class="mb-1.5 block text-xs font-bold text-ink-soft">Caută</span>
                    <input type="text" id="filter-search" placeholder="Nume, email, comandă…" class="w-full rounded-xl border-2 border-ink/15 bg-paper-2 px-3 py-2.5 text-sm font-medium outline-none transition focus:border-ink" oninput="debounceSearch()">
                </label>
            </div>
        </div>

        <!-- Stats -->
        <div class="mb-6 grid grid-cols-2 gap-4 lg:grid-cols-3">
            <div class="rounded-2xl border-2 border-ink bg-paper p-4">
                <p class="mb-1 font-mono text-[11px] uppercase tracking-[.14em] text-ink-soft">Comenzi finalizate</p>
                <p class="font-display text-3xl font-bold text-forest" id="stat-completed">—</p>
            </div>
            <div class="rounded-2xl border-2 border-ink bg-paper p-4">
                <p class="mb-1 font-mono text-[11px] uppercase tracking-[.14em] text-ink-soft">Bilete / rezervări</p>
                <p class="font-display text-3xl font-bold" id="stat-total-tickets">—</p>
            </div>
            <div class="rounded-2xl border-2 border-ink bg-paper p-4">
                <p class="mb-1 font-mono text-[11px] uppercase tracking-[.14em] text-ink-soft">Venituri nete</p>
                <p class="font-display text-3xl font-bold text-vermilion" id="stat-total-value">—</p>
            </div>
        </div>

        <!-- Orders table -->
        <div class="overflow-hidden rounded-2xl border-2 border-ink bg-paper">
            <div class="overflow-x-auto">
                <table class="w-full">
                    <thead class="bg-paper-2 text-left">
                        <tr class="font-mono text-[11px] uppercase tracking-[.12em] text-ink-soft">
                            <th class="cursor-pointer select-none px-4 py-3 hover:text-ink" onclick="toggleSort('order_number')">Comandă <span class="sort-arrow" data-arrow-for="order_number"></span></th>
                            <th class="cursor-pointer select-none px-4 py-3 hover:text-ink" onclick="toggleSort('customer_name')">Participant <span class="sort-arrow" data-arrow-for="customer_name"></span></th>
                            <th class="px-4 py-3">Tip bilet</th>
                            <th class="px-4 py-3 text-center">Bilete</th>
                            <th class="cursor-pointer select-none px-4 py-3 text-right hover:text-ink" onclick="toggleSort('total')">Valoare <span class="sort-arrow" data-arrow-for="total"></span></th>
                            <th class="cursor-pointer select-none px-4 py-3 text-center hover:text-ink" onclick="toggleSort('status')">Status <span class="sort-arrow" data-arrow-for="status"></span></th>
                            <th class="px-4 py-3">Sursă</th>
                            <th class="cursor-pointer select-none px-4 py-3 hover:text-ink" onclick="toggleSort('created_at')">Data <span class="sort-arrow" data-arrow-for="created_at"></span></th>
                        </tr>
                    </thead>
                    <tbody id="orders-list" class="divide-y divide-ink/10 text-sm">
                        <tr><td colspan="8" class="px-4 py-16 text-center text-ink-soft">Se încarcă…</td></tr>
                    </tbody>
                </table>
            </div>
            <div id="pagination" class="hidden items-center justify-between border-t-2 border-dashed border-ink/15 px-4 py-3">
                <p class="text-sm text-ink-soft"><span id="page-info">Pagina 1 din 1</span></p>
                <div class="flex gap-2">
                    <button onclick="goToPage(currentPage - 1)" id="prev-btn" class="rounded-full border-2 border-ink px-4 py-1.5 text-sm font-bold transition hover:bg-ink hover:text-paper disabled:opacity-40 disabled:hover:bg-transparent disabled:hover:text-ink" disabled>Anterior</button>
                    <button onclick="goToPage(currentPage + 1)" id="next-btn" class="rounded-full border-2 border-ink px-4 py-1.5 text-sm font-bold transition hover:bg-ink hover:text-paper disabled:opacity-40 disabled:hover:bg-transparent disabled:hover:text-ink" disabled>Următoarea</button>
                </div>
            </div>
        </div>
    </main>

    <?php require_once dirname(__DIR__) . '/includes/organizer-footer.php'; ?>
</div>

<?php
$scriptsExtra = <<<'JS'
<script>
let ordersData = [];
let eventsData = [];
let currentPage = 1;
let totalPages = 1;
let searchTimeout = null;
const perPage = 25;
let sortBy = 'created_at';
let sortDir = 'desc';

const urlParams = new URLSearchParams(window.location.search);
const highlightEventId = urlParams.get('event');

function orgNotify(msg, type) {
    try {
        if (typeof BileteOnlineNotifications !== 'undefined' && BileteOnlineNotifications[type || 'info']) {
            BileteOnlineNotifications[type || 'info'](msg);
            return;
        }
    } catch (e) {}
    if (type === 'error') alert(msg);
}

function escHtml(str) {
    const div = document.createElement('div');
    div.textContent = str == null ? '' : str;
    return div.innerHTML;
}
function maskEmail(email) {
    if (!email) return '-';
    const parts = email.split('@');
    if (parts.length !== 2) return email;
    const name = parts[0], domain = parts[1];
    const masked = name.length > 2 ? name[0] + '*'.repeat(name.length - 2) + name[name.length - 1] : name;
    return masked + '@' + domain;
}
function money(v) {
    try { return BileteOnlineUtils.formatCurrency(v || 0); } catch (e) { return (Math.round((v||0)*100)/100) + ' lei'; }
}
function isEventLive(ev) {
    if (ev.is_cancelled || ev.is_postponed || ev.is_past || ev.is_ended) return false;
    if (ev.status !== 'published' && ev.status !== 'active') return false;
    const endDate = ev.ends_at || ev.starts_at;
    return !endDate || new Date(endDate) >= new Date();
}

async function loadEvents() {
    try {
        const r = await BileteOnlineAPI.organizer.getEvents({ per_page: 100 });
        const rows = (r && (r.data && (r.data.events || r.data.items)) ) || (r && r.data) || [];
        eventsData = Array.isArray(rows) ? rows : [];
        eventsData.sort((a, b) => {
            const aLive = isEventLive(a), bLive = isEventLive(b);
            if (aLive && !bLive) return -1;
            if (!aLive && bLive) return 1;
            const aDate = new Date(a.starts_at || 0), bDate = new Date(b.starts_at || 0);
            return aLive ? aDate - bDate : bDate - aDate;
        });
        const select = document.getElementById('filter-event');
        select.innerHTML = '<option value="">Toate activitățile</option>';
        eventsData.forEach(ev => {
            const opt = document.createElement('option');
            opt.value = ev.id;
            const dot = isEventLive(ev) ? '🟢 ' : '⚫ ';
            let date = ''; try { date = ev.starts_at ? BileteOnlineUtils.formatDate(ev.starts_at) : ''; } catch (e) {}
            const meta = [date, ev.venue_name || (ev.venue && ev.venue.name) || ''].filter(Boolean).join(' · ');
            opt.textContent = dot + (ev.name || ev.title || ('#' + ev.id)) + (meta ? ' — ' + meta : '');
            select.appendChild(opt);
        });
        if (highlightEventId) select.value = highlightEventId;
    } catch (e) { /* keep "Toate activitățile" */ }
}

function debounceSearch() { clearTimeout(searchTimeout); searchTimeout = setTimeout(loadOrders, 300); }
function resetAndLoad() { currentPage = 1; loadOrders(); }

function toggleSort(column) {
    if (sortBy === column) sortDir = sortDir === 'asc' ? 'desc' : 'asc';
    else { sortBy = column; sortDir = 'desc'; }
    currentPage = 1; loadOrders();
}
function renderSortArrows() {
    document.querySelectorAll('[data-arrow-for]').forEach(el => {
        const col = el.dataset.arrowFor;
        el.textContent = (col === sortBy) ? (sortDir === 'asc' ? '▲' : '▼') : '↕';
        el.classList.toggle('text-vermilion', col === sortBy);
    });
}

async function loadOrders() {
    const params = { page: currentPage, per_page: perPage, sort_by: sortBy, sort_dir: sortDir };
    const eventId = document.getElementById('filter-event').value;
    const status  = document.getElementById('filter-status').value;
    const from    = document.getElementById('filter-from').value;
    const to      = document.getElementById('filter-to').value;
    const search  = document.getElementById('filter-search').value.trim();
    if (eventId) params.event_id = eventId;   // optional — no event = all orders
    if (status)  params.status = status;
    if (from)    params.from_date = from;
    if (to)      params.to_date = to;
    if (search)  params.search = search;

    document.getElementById('orders-list').innerHTML = '<tr><td colspan="8" class="px-4 py-12 text-center text-ink-soft">Se încarcă…</td></tr>';
    try {
        const r = await BileteOnlineAPI.organizer.getOrders(params);
        ordersData = (r && r.data) || [];
        if (!Array.isArray(ordersData)) ordersData = ordersData.orders || ordersData.items || [];
        const meta = (r && r.meta) || {};
        currentPage = meta.current_page || 1;
        totalPages  = meta.last_page || 1;
        renderOrders();
        updateStats(meta);
        updatePagination();
    } catch (e) {
        document.getElementById('orders-list').innerHTML = '<tr><td colspan="8" class="px-4 py-12 text-center text-vermilion">Eroare la încărcarea comenzilor.</td></tr>';
    }
}

function renderOrders() {
    renderSortArrows();
    const tbody = document.getElementById('orders-list');
    if (!ordersData.length) {
        tbody.innerHTML = '<tr><td colspan="8" class="px-4 py-12 text-center text-ink-soft">Nu există comenzi pentru filtrele selectate.</td></tr>';
        return;
    }
    tbody.innerHTML = ordersData.map(order => {
        const orderDate = order.created_at ? new Date(order.created_at).toLocaleString('ro-RO', { day: '2-digit', month: '2-digit', year: 'numeric', hour: '2-digit', minute: '2-digit' }) : '-';
        const tickets = (order.ticket_types && order.ticket_types.length)
            ? order.ticket_types.map(tt => '<span class="inline-block rounded bg-vermilion/10 px-2 py-0.5 text-xs font-bold text-vermilion">' + escHtml(tt) + '</span>').join(' ')
            : '<span class="text-xs text-ink-soft">—</span>';
        const discount = order.discount_info
            ? '<span class="mt-0.5 block text-[11px] font-medium leading-tight text-forest">' + (order.discount_info.code ? 'Cod ' + escHtml(order.discount_info.code) + ': ' : 'Redus ') + '-' + money(order.discount_info.discount_amount) + '</span>'
            : '';
        return '<tr class="hover:bg-paper-2/60">'
            + '<td class="px-4 py-3"><div class="flex flex-col"><span class="font-bold">' + escHtml(order.order_number) + '</span><span class="text-xs text-ink-soft">#' + order.id + '</span></div></td>'
            + '<td class="px-4 py-3"><div class="flex max-w-[200px] flex-col"><span class="truncate font-medium">' + escHtml(order.customer || '-') + '</span><span class="truncate text-xs text-ink-soft">' + escHtml(maskEmail(order.customer_email)) + '</span>'
                + (order.customer_phone ? '<span class="text-xs text-ink-soft">' + escHtml(order.customer_phone) + '</span>' : '') + '</div></td>'
            + '<td class="px-4 py-3"><div class="flex max-w-[200px] flex-wrap gap-1">' + tickets + '</div></td>'
            + '<td class="px-4 py-3 text-center font-bold">' + (order.tickets_count || 0) + '</td>'
            + '<td class="px-4 py-3 text-right"><span class="font-bold">' + money((order.net_total != null ? order.net_total : order.total) || 0) + '</span>' + discount + '</td>'
            + '<td class="px-4 py-3 text-center">' + getStatusBadge(order.status) + '</td>'
            + '<td class="px-4 py-3 text-xs text-ink-soft">' + escHtml(getSourceLabel(order.source)) + '</td>'
            + '<td class="px-4 py-3 text-sm text-ink-soft whitespace-nowrap">' + orderDate + '</td>'
            + '</tr>';
    }).join('');
}

function getStatusBadge(status) {
    const m = {
        completed:           ['bg-forest/15 text-forest', 'Finalizată'],
        pending:             ['bg-ochre/15 text-ochre', 'În așteptare'],
        cancelled:           ['bg-vermilion/10 text-vermilion', 'Anulată'],
        refunded:            ['bg-sky/15 text-sky', 'Rambursată'],
        partially_refunded:  ['bg-ochre/15 text-ochre', 'Parțial rambursată'],
        failed:              ['bg-vermilion/10 text-vermilion', 'Eșuată'],
        expired:             ['bg-ink/10 text-ink-soft', 'Expirată'],
    };
    const b = m[status] || ['bg-ink/10 text-ink-soft', status || '—'];
    return '<span class="inline-block rounded-full px-2.5 py-1 text-xs font-bold ' + b[0] + '">' + escHtml(b[1]) + '</span>';
}
function getSourceLabel(source) {
    const m = { marketplace: 'bilete.online', widget: 'Widget', pos: 'POS', pos_app: 'Aplicație', api: 'API', manual: 'Manual', legacy_import: 'Import' };
    return m[source] || source || 'bilete.online';
}

function updateStats(meta) {
    const set = (id, v) => { const el = document.getElementById(id); if (el) el.textContent = v; };
    set('stat-completed', (meta.completed_orders || 0).toLocaleString('ro-RO'));
    set('stat-total-tickets', (meta.total_tickets || 0).toLocaleString('ro-RO'));
    set('stat-total-value', money(meta.total_revenue || 0));
}

function updatePagination() {
    const pagination = document.getElementById('pagination');
    if (totalPages <= 1) { pagination.classList.add('hidden'); return; }
    pagination.classList.remove('hidden'); pagination.classList.add('flex');
    document.getElementById('page-info').textContent = 'Pagina ' + currentPage + ' din ' + totalPages;
    document.getElementById('prev-btn').disabled = currentPage <= 1;
    document.getElementById('next-btn').disabled = currentPage >= totalPages;
}
function goToPage(page) { if (page < 1 || page > totalPages) return; currentPage = page; loadOrders(); }

async function exportSales() {
    try {
        const token = (typeof BileteOnlineAuth !== 'undefined' && BileteOnlineAuth.getToken) ? BileteOnlineAuth.getToken() : null;
        if (!token) { orgNotify('Sesiune expirată. Autentifică-te din nou.', 'error'); return; }
        orgNotify('Se generează exportul…', 'info');
        const p = new URLSearchParams();
        p.set('action', 'organizer.orders.export');
        const eventId = document.getElementById('filter-event').value;
        const status = document.getElementById('filter-status').value;
        const from = document.getElementById('filter-from').value;
        const to = document.getElementById('filter-to').value;
        if (eventId) p.set('event_id', eventId);
        if (status) p.set('status', status);
        if (from) p.set('from_date', from);
        if (to) p.set('to_date', to);
        const base = (window.BILETEONLINE && window.BILETEONLINE.apiUrl) || '/api/proxy.php';
        const resp = await fetch(base + '?' + p.toString(), { headers: { 'Authorization': 'Bearer ' + token, 'Accept': 'text/csv' } });
        if (!resp.ok) throw new Error('Eroare la export');
        const blob = await resp.blob();
        const url = window.URL.createObjectURL(blob);
        const a = document.createElement('a');
        a.href = url; a.download = 'bilete-online-vanzari-' + new Date().toISOString().slice(0, 10) + '.csv';
        document.body.appendChild(a); a.click(); window.URL.revokeObjectURL(url); a.remove();
        orgNotify('Exportul a fost descărcat.', 'success');
    } catch (e) { orgNotify(e.message || 'Eroare la export', 'error'); }
}

document.addEventListener('DOMContentLoaded', function () {
    renderSortArrows();
    loadEvents().finally(loadOrders);  // load all orders by default (no event required)
});
</script>
JS;
require_once dirname(__DIR__) . '/includes/scripts.php';
?>
