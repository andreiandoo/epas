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
            <div class="flex flex-col lg:flex-row lg:items-center justify-between gap-4 mb-6">
                <div>
                    <h1 class="text-2xl font-bold text-secondary">Vanzari</h1>
                    <p class="text-sm text-muted">Toate comenzile tale intr-un singur loc</p>
                </div>
                <button onclick="exportSales()" class="btn btn-secondary w-auto">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/></svg>
                    Export
                </button>
            </div>

            <!-- Filters -->
            <div class="bg-white border border-border rounded-2xl p-4 mb-6">
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-5 gap-4">
                    <div>
                        <label class="label">Eveniment</label>
                        <select id="filter-event" class="input w-full" onchange="loadOrders()">
                            <option value="">Toate evenimentele</option>
                        </select>
                    </div>
                    <div>
                        <label class="label">Status</label>
                        <select id="filter-status" class="input w-full" onchange="loadOrders()">
                            <option value="">Toate statusurile</option>
                            <option value="completed">Finalizata</option>
                            <option value="pending">In asteptare</option>
                            <option value="cancelled">Anulata</option>
                            <option value="refunded">Rambursata</option>
                        </select>
                    </div>
                    <div>
                        <label class="label">De la data</label>
                        <input type="date" id="filter-from" class="input w-full" onchange="loadOrders()">
                    </div>
                    <div>
                        <label class="label">Pana la data</label>
                        <input type="date" id="filter-to" class="input w-full" onchange="loadOrders()">
                    </div>
                    <div>
                        <label class="label">Cauta</label>
                        <input type="text" id="filter-search" class="input w-full" placeholder="Nume, email, comanda..." oninput="debounceSearch()">
                    </div>
                </div>
            </div>

            <!-- Stats -->
            <div class="grid grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
                <div class="bg-white border border-border rounded-2xl p-4">
                    <p class="text-sm text-muted mb-1">Total comenzi</p>
                    <p class="text-2xl font-bold text-secondary" id="stat-total-orders">-</p>
                </div>
                <div class="bg-white border border-border rounded-2xl p-4">
                    <p class="text-sm text-muted mb-1">Valoare totala</p>
                    <p class="text-2xl font-bold text-primary" id="stat-total-value">-</p>
                </div>
                <div class="bg-white border border-border rounded-2xl p-4">
                    <p class="text-sm text-muted mb-1">Bilete vandute</p>
                    <p class="text-2xl font-bold text-secondary" id="stat-total-tickets">-</p>
                </div>
                <div class="bg-white border border-border rounded-2xl p-4">
                    <p class="text-sm text-muted mb-1">Finalizate</p>
                    <p class="text-2xl font-bold text-success" id="stat-completed">-</p>
                </div>
            </div>

            <!-- Orders Table -->
            <div class="bg-white border border-border rounded-2xl overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="w-full">
                        <thead class="bg-surface">
                            <tr>
                                <th class="px-4 py-3 text-sm font-semibold text-left text-secondary">Comanda</th>
                                <th class="px-4 py-3 text-sm font-semibold text-left text-secondary">Participant</th>
                                <th class="px-4 py-3 text-sm font-semibold text-left text-secondary">Eveniment</th>
                                <th class="px-4 py-3 text-sm font-semibold text-center text-secondary">Bilete</th>
                                <th class="px-4 py-3 text-sm font-semibold text-right text-secondary">Valoare</th>
                                <th class="px-4 py-3 text-sm font-semibold text-center text-secondary">Status</th>
                                <th class="px-4 py-3 text-sm font-semibold text-left text-secondary">Sursa</th>
                                <th class="px-4 py-3 text-sm font-semibold text-left text-secondary">Data</th>
                            </tr>
                        </thead>
                        <tbody id="orders-list" class="divide-y divide-border">
                            <tr><td colspan="8" class="px-4 py-12 text-center text-muted">Se incarca...</td></tr>
                        </tbody>
                    </table>
                </div>
                <!-- Pagination -->
                <div id="pagination" class="px-4 py-3 border-t border-border flex items-center justify-between hidden">
                    <p class="text-sm text-muted"><span id="page-info">Pagina 1 din 1</span></p>
                    <div class="flex gap-2">
                        <button onclick="goToPage(currentPage - 1)" id="prev-btn" class="btn btn-secondary btn-sm" disabled>Anterior</button>
                        <button onclick="goToPage(currentPage + 1)" id="next-btn" class="btn btn-secondary btn-sm" disabled>Urmator</button>
                    </div>
                </div>
            </div>
        </main>
    </div>

<?php
$scriptsExtra = <<<'JS'
<script>
AmbiletAuth.requireOrganizerAuth();

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
    loadEvents();
    loadOrders();
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

async function loadEvents() {
    try {
        const response = await AmbiletAPI.get('/organizer/events', { per_page: 100 });
        if (response.success) {
            eventsData = response.data.events || response.data || [];
            const select = document.getElementById('filter-event');
            select.innerHTML = '<option value="">Toate evenimentele</option>';
            eventsData.forEach(ev => {
                const opt = document.createElement('option');
                opt.value = ev.id;
                opt.textContent = ev.name || ev.title;
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

    if (eventId) params.event_id = eventId;
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
            updateStats(ordersData, total);
            updatePagination();
        }
    } catch (error) {
        console.error('Failed to load orders:', error);
        document.getElementById('orders-list').innerHTML = `<tr><td colspan="8" class="px-4 py-12 text-center text-error">Eroare la incarcarea comenzilor</td></tr>`;
    }
}

function renderOrders() {
    const tbody = document.getElementById('orders-list');

    if (!ordersData.length) {
        tbody.innerHTML = `<tr><td colspan="8" class="px-4 py-12 text-center text-muted">Nu exista comenzi pentru filtrele selectate</td></tr>`;
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
                        <span class="font-semibold text-secondary text-sm">${escHtml(order.order_number)}</span>
                        <span class="text-xs text-muted">#${order.id}</span>
                    </div>
                </td>
                <td class="px-4 py-3">
                    <div class="flex flex-col max-w-[200px]">
                        <span class="font-medium text-secondary text-sm truncate">${escHtml(order.customer || '-')}</span>
                        <span class="text-xs text-muted truncate">${escHtml(maskEmail(order.customer_email))}</span>
                        ${order.customer_phone ? `<span class="text-xs text-muted">${escHtml(order.customer_phone)}</span>` : ''}
                        ${order.customer_city ? `<span class="text-xs text-muted">${escHtml(order.customer_city)}</span>` : ''}
                    </div>
                </td>
                <td class="px-4 py-3">
                    <span class="text-sm text-secondary truncate block max-w-[180px]" title="${escHtml(order.event)}">${escHtml(order.event || '-')}</span>
                </td>
                <td class="px-4 py-3 text-center">
                    <span class="font-semibold text-secondary">${order.tickets_count || 0}</span>
                </td>
                <td class="px-4 py-3 text-right">
                    <span class="font-semibold text-secondary">${AmbiletUtils.formatCurrency(order.total || 0)}</span>
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
        'refunded': '<span class="px-2 py-1 text-xs font-medium rounded-full bg-purple-100 text-purple-600">Rambursata</span>',
        'partially_refunded': '<span class="px-2 py-1 text-xs font-medium rounded-full bg-orange-100 text-orange-600">Partial rambursata</span>',
    };
    return badges[status] || `<span class="px-2 py-1 text-xs font-medium rounded-full bg-gray-100 text-gray-600">${escHtml(status)}</span>`;
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

function updateStats(orders, total) {
    document.getElementById('stat-total-orders').textContent = total.toLocaleString('ro-RO');

    // Calculate from current page data (approximate for now)
    let totalValue = 0;
    let totalTickets = 0;
    let completed = 0;

    orders.forEach(o => {
        totalValue += parseFloat(o.total) || 0;
        totalTickets += parseInt(o.tickets_count) || 0;
        if (o.status === 'completed') completed++;
    });

    document.getElementById('stat-total-value').textContent = AmbiletUtils.formatCurrency(totalValue);
    document.getElementById('stat-total-tickets').textContent = totalTickets.toLocaleString('ro-RO');
    document.getElementById('stat-completed').textContent = completed.toLocaleString('ro-RO');
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

function exportSales() {
    // Build export URL with current filters
    const params = new URLSearchParams();

    const eventId = document.getElementById('filter-event').value;
    const status = document.getElementById('filter-status').value;
    const fromDate = document.getElementById('filter-from').value;
    const toDate = document.getElementById('filter-to').value;

    if (eventId) params.set('event_id', eventId);
    if (status) params.set('status', status);
    if (fromDate) params.set('from_date', fromDate);
    if (toDate) params.set('to_date', toDate);

    AmbiletNotifications.info('Export in lucru... Functionalitatea va fi disponibila curand.');
}
</script>
JS;
require_once dirname(__DIR__) . '/includes/scripts.php';
?>
