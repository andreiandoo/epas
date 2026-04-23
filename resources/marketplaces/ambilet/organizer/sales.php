<?php
require_once dirname(__DIR__) . '/includes/config.php';
$pageTitle = 'Vanzari';
$bodyClass = 'min-h-screen flex bg-slate-100';
$currentPage = 'sales';
$cssBundle = 'organizer';
require_once dirname(__DIR__) . '/includes/head.php';
require_once dirname(__DIR__) . '/includes/organizer-sidebar.php';
?>

    <!-- Main Content -->
    <div class="flex flex-col flex-1 min-h-screen lg:ml-0">
        <?php require_once dirname(__DIR__) . '/includes/organizer-topbar.php'; ?>
        <!-- Page Content -->
        <main class="flex-1 p-4 lg:p-8">
            <!-- Page Header -->
            <div class="flex flex-col justify-between gap-4 mb-6 lg:flex-row lg:items-center">
                <div>
                    <h1 class="text-2xl font-bold text-secondary">Vânzări</h1>
                    <p class="text-sm text-muted">Toate comenzile tale într-un singur loc</p>
                </div>
                <button onclick="exportSales()" class="w-auto btn btn-secondary">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/></svg>
                    Export
                </button>
            </div>

            <!-- Filters -->
            <div class="p-4 mb-6 bg-white border border-border rounded-2xl">
                <div class="grid grid-cols-1 gap-4 md:grid-cols-2 lg:grid-cols-5">
                    <div>
                        <label class="label">Eveniment</label>
                        <select id="filter-event" class="w-full input" onchange="loadOrders()">
                            <option value="">Toate evenimentele</option>
                        </select>
                    </div>
                    <div>
                        <label class="label">Status</label>
                        <select id="filter-status" class="w-full input" onchange="loadOrders()">
                            <option value="">Toate statusurile</option>
                            <option value="completed">Finalizate</option>
                            <option value="pending">În așteptare</option>
                            <option value="cancelled">Anulate</option>
                            <option value="refunded">Rambursate</option>
                        </select>
                    </div>
                    <div>
                        <label class="label">De la data</label>
                        <input type="date" id="filter-from" class="w-full input" onchange="loadOrders()">
                    </div>
                    <div>
                        <label class="label">Până la data</label>
                        <input type="date" id="filter-to" class="w-full input" onchange="loadOrders()">
                    </div>
                    <div>
                        <label class="label">Caută</label>
                        <input type="text" id="filter-search" class="w-full input" placeholder="Nume, email, comanda..." oninput="debounceSearch()">
                    </div>
                </div>
            </div>

            <!-- Stats -->
            <div class="grid grid-cols-2 gap-4 mb-6 lg:grid-cols-3">
                <div class="p-4 bg-white border border-border rounded-2xl">
                    <p class="mb-1 text-sm text-muted">Comenzi finalizate</p>
                    <p class="text-2xl font-bold text-success" id="stat-completed">-</p>
                </div>
                <div class="p-4 bg-white border border-border rounded-2xl">
                    <p class="mb-1 text-sm text-muted">Bilete vândute</p>
                    <p class="text-2xl font-bold text-secondary" id="stat-total-tickets">-</p>
                </div>
                <div class="p-4 bg-white border border-border rounded-2xl">
                    <p class="mb-1 text-sm text-muted">Venituri nete</p>
                    <p class="text-2xl font-bold text-primary" id="stat-total-value">-</p>
                </div>
            </div>

            <!-- Orders Table -->
            <div class="overflow-hidden bg-white border border-border rounded-2xl">
                <div class="overflow-x-auto">
                    <table class="w-full">
                        <thead class="bg-surface">
                            <tr>
                                <th class="px-4 py-3 text-sm font-semibold text-left text-secondary">Comanda</th>
                                <th class="px-4 py-3 text-sm font-semibold text-left text-secondary">Participant</th>
                                <th class="px-4 py-3 text-sm font-semibold text-left text-secondary">Tip bilet</th>
                                <th class="px-4 py-3 text-sm font-semibold text-center text-secondary">Bilete</th>
                                <th class="px-4 py-3 text-sm font-semibold text-right text-secondary">Valoare</th>
                                <th class="px-4 py-3 text-sm font-semibold text-center text-secondary">Status</th>
                                <th class="px-4 py-3 text-sm font-semibold text-left text-secondary">Sursa</th>
                                <th class="px-4 py-3 text-sm font-semibold text-left text-secondary">Data</th>
                            </tr>
                        </thead>
                        <tbody id="orders-list" class="divide-y divide-border">
                            <tr id="select-event-prompt"><td colspan="8" class="px-4 py-16 text-center">
                                <svg class="w-12 h-12 mx-auto mb-3 text-muted/50" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                                <p class="mb-1 text-base font-medium text-secondary">Selectează un eveniment</p>
                                <p class="text-sm text-muted">Alege un eveniment din filtrul de mai sus pentru a vedea comenzile</p>
                            </td></tr>
                        </tbody>
                    </table>
                </div>
                <!-- Pagination -->
                <div id="pagination" class="flex items-center justify-between hidden px-4 py-3 border-t border-border">
                    <p class="text-sm text-muted"><span id="page-info">Pagina 1 din 1</span></p>
                    <div class="flex gap-2">
                        <button onclick="goToPage(currentPage - 1)" id="prev-btn" class="btn btn-secondary btn-sm" disabled>Anterior</button>
                        <button onclick="goToPage(currentPage + 1)" id="next-btn" class="btn btn-secondary btn-sm" disabled>Următoarea</button>
                    </div>
                </div>
            </div>
        </main>
    </div>

<?php
$scriptsExtra = <<<'JS'
<script>
document.addEventListener('DOMContentLoaded', function() { AmbiletAuth.requireOrganizerAuth(); });

let ordersData = [];
let eventsData = [];
let currentPage = 1;
let totalPages = 1;
let searchTimeout = null;
const perPage = 25;

// Highlight event from URL param
const urlParams = new URLSearchParams(window.location.search);
const highlightEventId = urlParams.get('event');

document.addEventListener('DOMContentLoaded', function() {
    loadEvents().then(() => {
        // Only auto-load orders if an event is pre-selected via URL
        if (highlightEventId) {
            loadOrders();
        }
    });
});

function escHtml(str) {
    const div = document.createElement('div');
    div.textContent = str || '';
    return div.innerHTML;
}

function maskEmail(email) {
    if (!email) return '-';
    const parts = email.split('@');
    if (parts.length !== 2) return email;
    const name = parts[0];
    const domain = parts[1];
    const masked = name.length > 2 ? name[0] + '*'.repeat(name.length - 2) + name[name.length - 1] : name;
    return masked + '@' + domain;
}

function isEventLive(ev) {
    if (ev.is_cancelled || ev.is_postponed || ev.is_past || ev.is_ended) return false;
    if (ev.status !== 'published' && ev.status !== 'active') return false;
    const endDate = ev.ends_at || ev.starts_at;
    return !endDate || new Date(endDate) >= new Date();
}

async function loadEvents() {
    try {
        const response = await AmbiletAPI.get('/organizer/events', { per_page: 100 });
        if (response.success) {
            eventsData = response.data.events || response.data || [];
            // Sort: live first, then by date ascending (closest first)
            eventsData.sort((a, b) => {
                const aLive = isEventLive(a), bLive = isEventLive(b);
                if (aLive && !bLive) return -1;
                if (!aLive && bLive) return 1;
                const aDate = new Date(a.starts_at || 0), bDate = new Date(b.starts_at || 0);
                return aLive ? aDate - bDate : bDate - aDate; // live: closest first, ended: most recent first
            });
            const select = document.getElementById('filter-event');
            select.innerHTML = '<option value="">Selecteaza un eveniment</option>';
            eventsData.forEach(ev => {
                const opt = document.createElement('option');
                opt.value = ev.id;
                const live = isEventLive(ev);
                const dot = live ? '🟢 ' : '⚫ ';
                const date = ev.starts_at ? AmbiletUtils.formatDate(ev.starts_at) : '';
                const venue = ev.venue_name || '';
                const meta = [date, venue].filter(Boolean).join(' · ');
                opt.textContent = dot + (ev.name || ev.title) + (meta ? ' — ' + meta : '');
                select.appendChild(opt);
            });
            // Set from URL param
            if (highlightEventId) {
                select.value = highlightEventId;
            }
        }
    } catch (error) {
        console.error('Failed to load events:', error);
    }
}

function debounceSearch() {
    clearTimeout(searchTimeout);
    searchTimeout = setTimeout(() => loadOrders(), 300);
}

async function loadOrders() {
    const params = { page: currentPage, per_page: perPage };

    const eventId = document.getElementById('filter-event').value;
    const status = document.getElementById('filter-status').value;
    const fromDate = document.getElementById('filter-from').value;
    const toDate = document.getElementById('filter-to').value;
    const search = document.getElementById('filter-search').value.trim();

    if (!eventId) {
        // No event selected — show prompt
        document.getElementById('orders-list').innerHTML = `<tr><td colspan="8" class="px-4 py-16 text-center">
            <svg class="w-12 h-12 mx-auto mb-3 text-muted/50" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
            <p class="mb-1 text-base font-medium text-secondary">Selectează un eveniment</p>
            <p class="text-sm text-muted">Alege un eveniment din filtrul de mai sus pentru a vedea comenzile</p>
        </td></tr>`;
        document.getElementById('stat-total-orders').textContent = '-';
        document.getElementById('stat-total-value').textContent = '-';
        document.getElementById('stat-total-tickets').textContent = '-';
        document.getElementById('stat-completed').textContent = '-';
        document.getElementById('pagination').classList.add('hidden');
        return;
    }

    params.event_id = eventId;
    if (status) params.status = status;
    if (fromDate) params.from_date = fromDate;
    if (toDate) params.to_date = toDate;
    if (search) params.search = search;

    try {
        const response = await AmbiletAPI.get('/organizer/orders', params);
        if (response.success) {
            ordersData = response.data || [];
            const meta = response.meta || {};
            currentPage = meta.current_page || 1;
            totalPages = meta.last_page || 1;
            const total = meta.total || ordersData.length;

            renderOrders();
            updateStats(meta);
            updatePagination();
        }
    } catch (error) {
        console.error('Failed to load orders:', error);
        document.getElementById('orders-list').innerHTML = `<tr><td colspan="8" class="px-4 py-12 text-center text-error">Eroare la încărcarea comenzilor</td></tr>`;
    }
}

function renderOrders() {
    const tbody = document.getElementById('orders-list');

    if (!ordersData.length) {
        tbody.innerHTML = `<tr><td colspan="8" class="px-4 py-12 text-center text-muted">Nu există comenzi pentru filtrele selectate</td></tr>`;
        return;
    }

    tbody.innerHTML = ordersData.map(order => {
        const statusBadge = getStatusBadge(order.status);
        const sourceLabel = getSourceLabel(order.source);
        const orderDate = order.created_at ? new Date(order.created_at).toLocaleString('ro-RO', {
            day: '2-digit', month: '2-digit', year: 'numeric', hour: '2-digit', minute: '2-digit'
        }) : '-';

        return `
            <tr class="hover:bg-surface/50">
                <td class="px-4 py-3">
                    <div class="flex flex-col">
                        <span class="text-sm font-semibold text-secondary">${escHtml(order.order_number)}</span>
                        <span class="text-xs text-muted">#${order.id}</span>
                    </div>
                </td>
                <td class="px-4 py-3">
                    <div class="flex flex-col max-w-[200px]">
                        <span class="text-sm font-medium truncate text-secondary">${escHtml(order.customer || '-')}</span>
                        <span class="text-xs truncate text-muted">${escHtml(maskEmail(order.customer_email))}</span>
                        ${order.customer_phone ? `<span class="text-xs text-muted">${escHtml(order.customer_phone)}</span>` : ''}
                        ${order.customer_city ? `<span class="text-xs text-muted">${escHtml(order.customer_city)}</span>` : ''}
                    </div>
                </td>
                <td class="px-4 py-3">
                    <div class="flex flex-col gap-0.5 max-w-[200px]">
                        ${(order.ticket_types && order.ticket_types.length > 0)
                            ? order.ticket_types.map(tt => `<span class="px-2 py-0.5 text-xs font-medium rounded bg-primary/10 text-primary inline-block">${escHtml(tt)}</span>`).join('')
                            : '<span class="text-xs text-muted">-</span>'}
                    </div>
                </td>
                <td class="px-4 py-3 text-center">
                    <span class="font-semibold text-secondary">${order.tickets_count || 0}</span>
                </td>
                <td class="px-4 py-3 text-right">
                    <span class="font-semibold text-secondary">${AmbiletUtils.formatCurrency((order.net_total ?? order.total) || 0)}</span>
                </td>
                <td class="px-4 py-3 text-center">${statusBadge}</td>
                <td class="px-4 py-3">
                    <span class="text-xs text-muted">${sourceLabel}</span>
                </td>
                <td class="px-4 py-3">
                    <span class="text-sm text-muted whitespace-nowrap">${orderDate}</span>
                </td>
            </tr>
        `;
    }).join('');
}

function getStatusBadge(status) {
    const badges = {
        'completed': '<span class="px-2 py-1 text-xs font-medium rounded-full bg-success/10 text-success">Finalizata</span>',
        'pending': '<span class="px-2 py-1 text-xs font-medium rounded-full bg-warning/10 text-warning">In asteptare</span>',
        'cancelled': '<span class="px-2 py-1 text-xs font-medium rounded-full bg-error/10 text-error">Anulata</span>',
        'refunded': '<span class="px-2 py-1 text-xs font-medium text-purple-600 bg-purple-100 rounded-full">Rambursata</span>',
        'partially_refunded': '<span class="px-2 py-1 text-xs font-medium text-orange-600 bg-orange-100 rounded-full">Partial rambursata</span>',
    };
    return badges[status] || `<span class="px-2 py-1 text-xs font-medium text-gray-600 bg-gray-100 rounded-full">${escHtml(status)}</span>`;
}

function getSourceLabel(source) {
    const labels = {
        'marketplace': 'Website',
        'widget': 'Widget',
        'pos': 'POS',
        'api': 'API',
        'manual': 'Manual',
    };
    return labels[source] || source || 'Website';
}

function updateStats(meta) {
    document.getElementById('stat-total-orders').textContent = (meta.total || 0).toLocaleString('ro-RO');
    document.getElementById('stat-total-value').textContent = AmbiletUtils.formatCurrency(meta.total_revenue || 0);
    document.getElementById('stat-total-tickets').textContent = (meta.total_tickets || 0).toLocaleString('ro-RO');
    document.getElementById('stat-completed').textContent = (meta.completed_orders || 0).toLocaleString('ro-RO');

    // Order breakdown
    const bd = meta.order_breakdown;
    if (bd) {
        const parts = [];
        if (bd.failed > 0 || bd.cancelled > 0 || bd.expired > 0) parts.push(`${(bd.failed||0)+(bd.cancelled||0)+(bd.expired||0)} eșuate`);
        if (bd.pending > 0) parts.push(`${bd.pending} în așteptare`);
        if (bd.refunded > 0) parts.push(`${bd.refunded} rambursate`);
        document.getElementById('stat-orders-breakdown').textContent = parts.join(' · ');
    }
}

function updatePagination() {
    const pagination = document.getElementById('pagination');
    const pageInfo = document.getElementById('page-info');
    const prevBtn = document.getElementById('prev-btn');
    const nextBtn = document.getElementById('next-btn');

    if (totalPages <= 1) {
        pagination.classList.add('hidden');
        return;
    }

    pagination.classList.remove('hidden');
    pageInfo.textContent = `Pagina ${currentPage} din ${totalPages}`;
    prevBtn.disabled = currentPage <= 1;
    nextBtn.disabled = currentPage >= totalPages;
}

function goToPage(page) {
    if (page < 1 || page > totalPages) return;
    currentPage = page;
    loadOrders();
}

async function exportSales() {
    const eventId = document.getElementById('filter-event').value;
    if (!eventId) {
        AmbiletNotifications.error('Selectează un eveniment');
        return;
    }

    try {
        AmbiletNotifications.info('Se generează exportul...');

        const authToken = (typeof AmbiletAuth !== 'undefined' ? AmbiletAuth.getToken() : null);
        if (!authToken) {
            AmbiletNotifications.error('Sesiune expirată. Te rugăm să te autentifici din nou.');
            return;
        }

        const params = new URLSearchParams();
        params.set('action', 'organizer.orders.export');
        params.set('event_id', eventId);

        const status = document.getElementById('filter-status').value;
        const fromDate = document.getElementById('filter-from').value;
        const toDate = document.getElementById('filter-to').value;
        if (status) params.set('status', status);
        if (fromDate) params.set('from_date', fromDate);
        if (toDate) params.set('to_date', toDate);

        const response = await fetch(`/api/proxy.php?${params.toString()}`, {
            method: 'GET',
            headers: {
                'Authorization': `Bearer ${authToken}`,
                'Accept': 'text/csv'
            }
        });

        if (!response.ok) {
            const errorData = await response.json().catch(() => ({}));
            throw new Error(errorData.message || 'Eroare la export');
        }

        // Build filename: [event name]-Vanzari-[date].csv
        const selectedEvent = eventsData.find(e => String(e.id) === String(eventId));
        const eventTitle = selectedEvent ? (selectedEvent.name || selectedEvent.title || 'Eveniment') : 'Eveniment';
        const safeTitle = eventTitle.replace(/[^a-zA-Z0-9àáâãäåăîșțâéèêëìíïòóôõöùúûüñç -]/gi, '').replace(/\s+/g, '-');
        const exportDate = new Date().toISOString().slice(0, 10);
        const filename = `${safeTitle}-Vanzari-${exportDate}.csv`;

        const blob = await response.blob();
        const url = window.URL.createObjectURL(blob);
        const a = document.createElement('a');
        a.href = url;
        a.download = filename;
        document.body.appendChild(a);
        a.click();
        window.URL.revokeObjectURL(url);
        document.body.removeChild(a);

        AmbiletNotifications.success('Exportul a fost descărcat');
    } catch (error) {
        console.error('Export error:', error);
        AmbiletNotifications.error(error.message || 'Eroare la export');
    }
}
</script>
JS;
require_once dirname(__DIR__) . '/includes/scripts.php';
?>
