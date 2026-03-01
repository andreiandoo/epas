<?php
require_once dirname(__DIR__) . '/includes/config.php';
$pageTitle = 'Comenzile Mele - Servicii';
$bodyClass = 'min-h-screen flex bg-slate-100';
$currentPage = 'services';
$cssBundle = 'organizer';
require_once dirname(__DIR__) . '/includes/head.php';
require_once dirname(__DIR__) . '/includes/organizer-sidebar.php';
?>

    <!-- Main Content -->
    <div class="flex-1 flex flex-col min-h-screen lg:ml-0">
        <?php require_once dirname(__DIR__) . '/includes/organizer-topbar.php'; ?>
                <!-- Page Content -->
        <main class="flex-1 p-4 lg:p-8">
            <!-- Page Header -->
            <div class="flex items-center justify-between mb-6">
                <div>
                    <nav class="flex items-center gap-2 text-sm text-muted mb-2">
                        <a href="/organizator/servicii" class="hover:text-primary">Servicii Extra</a>
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
                        <span class="text-secondary">Comenzile Mele</span>
                    </nav>
                    <h1 class="text-2xl font-bold text-secondary">Comenzile Mele</h1>
                    <p class="text-sm text-muted">Istoric comenzi servicii extra</p>
                </div>
                <a href="/organizator/servicii" class="btn btn-secondary">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 17l-5-5m0 0l5-5m-5 5h12"/></svg>
                    Inapoi la Servicii
                </a>
            </div>

            <!-- Stats Cards -->
            <div class="grid grid-cols-2 lg:grid-cols-4 gap-4 mb-8">
                <div class="bg-white rounded-2xl border border-border p-6">
                    <div class="flex items-center gap-3 mb-3">
                        <div class="w-10 h-10 bg-primary/10 rounded-xl flex items-center justify-center">
                            <svg class="w-5 h-5 text-primary" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/></svg>
                        </div>
                        <span class="text-sm text-muted">Total Comenzi</span>
                    </div>
                    <p class="text-2xl font-bold text-secondary" id="total-orders">0</p>
                </div>
                <div class="bg-white rounded-2xl border border-border p-6">
                    <div class="flex items-center gap-3 mb-3">
                        <div class="w-10 h-10 bg-success/10 rounded-xl flex items-center justify-center">
                            <svg class="w-5 h-5 text-success" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6"/></svg>
                        </div>
                        <span class="text-sm text-muted">Servicii Active</span>
                    </div>
                    <p class="text-2xl font-bold text-secondary" id="active-count">0</p>
                </div>
                <div class="bg-white rounded-2xl border border-border p-6">
                    <div class="flex items-center gap-3 mb-3">
                        <div class="w-10 h-10 bg-warning/10 rounded-xl flex items-center justify-center">
                            <svg class="w-5 h-5 text-warning" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                        </div>
                        <span class="text-sm text-muted">In Asteptare</span>
                    </div>
                    <p class="text-2xl font-bold text-secondary" id="pending-count">0</p>
                </div>
                <div class="bg-white rounded-2xl border border-border p-6">
                    <div class="flex items-center gap-3 mb-3">
                        <div class="w-10 h-10 bg-blue-100 rounded-xl flex items-center justify-center">
                            <svg class="w-5 h-5 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                        </div>
                        <span class="text-sm text-muted">Investit Total</span>
                    </div>
                    <p class="text-2xl font-bold text-secondary" id="total-spent">0 RON</p>
                </div>
            </div>

            <!-- Filters -->
            <div class="bg-white rounded-2xl border border-border p-4 mb-6">
                <div class="flex flex-wrap items-center gap-4">
                    <div class="flex-1 min-w-[200px]">
                        <input type="text" id="search-orders" placeholder="Cauta dupa numar comanda sau eveniment..." class="input w-full">
                    </div>
                    <select id="filter-status" class="input w-40">
                        <option value="">Toate statusurile</option>
                        <option value="pending_payment">Asteapta Plata</option>
                        <option value="processing">In Procesare</option>
                        <option value="active">Activ</option>
                        <option value="completed">Finalizat</option>
                        <option value="cancelled">Anulat</option>
                    </select>
                    <select id="filter-type" class="input w-40">
                        <option value="">Toate tipurile</option>
                        <option value="featuring">Promovare</option>
                        <option value="email">Email Marketing</option>
                        <option value="tracking">Ad Tracking</option>
                        <option value="campaign">Creare Campanie</option>
                    </select>
                </div>
            </div>

            <!-- Orders List -->
            <div class="bg-white rounded-2xl border border-border overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="w-full">
                        <thead class="bg-surface">
                            <tr>
                                <th class="px-6 py-4 text-left text-sm font-semibold text-secondary">Comanda</th>
                                <th class="px-6 py-4 text-left text-sm font-semibold text-secondary">Tip Serviciu</th>
                                <th class="px-6 py-4 text-left text-sm font-semibold text-secondary">Eveniment</th>
                                <th class="px-6 py-4 text-left text-sm font-semibold text-secondary">Perioada</th>
                                <th class="px-6 py-4 text-right text-sm font-semibold text-secondary">Total</th>
                                <th class="px-6 py-4 text-center text-sm font-semibold text-secondary">Status</th>
                                <th class="px-6 py-4 text-right text-sm font-semibold text-secondary">Data</th>
                            </tr>
                        </thead>
                        <tbody id="orders-list" class="divide-y divide-border"></tbody>
                    </table>
                </div>
                <!-- Pagination -->
                <div id="pagination" class="px-6 py-4 border-t border-border flex items-center justify-between">
                    <p class="text-sm text-muted" id="pagination-info">Se incarca...</p>
                    <div class="flex items-center gap-2" id="pagination-buttons"></div>
                </div>
            </div>
        </main>
    </div>
<?php
$scriptsExtra = <<<'JS'
<script>
AmbiletAuth.requireOrganizerAuth();
let allOrders = [];
let filteredOrders = [];
let currentPage = 1;
const perPage = 20;

document.addEventListener('DOMContentLoaded', function() {
    loadOrders();
    loadStats();

    // Event listeners for filters
    document.getElementById('search-orders').addEventListener('input', AmbiletUtils.debounce(filterOrders, 300));
    document.getElementById('filter-status').addEventListener('change', filterOrders);
    document.getElementById('filter-type').addEventListener('change', filterOrders);
});

async function loadStats() {
    try {
        const response = await AmbiletAPI.get('/organizer/services/orders/stats');
        if (response.success) {
            document.getElementById('active-count').textContent = response.data.active_count || 0;
            document.getElementById('total-spent').textContent = formatCurrency(response.data.total_spent || 0) + ' RON';
        }
    } catch (error) {
        console.error('Error loading stats:', error);
    }
}

async function loadOrders() {
    try {
        const response = await AmbiletAPI.get('/organizer/services/orders');
        if (response.success) {
            allOrders = response.data.data || [];
            document.getElementById('total-orders').textContent = response.data.total || allOrders.length;

            // Count pending orders
            const pendingCount = allOrders.filter(o => o.status === 'pending_payment' || o.status === 'processing').length;
            document.getElementById('pending-count').textContent = pendingCount;

            filterOrders();
        } else {
            renderOrders([]);
        }
    } catch (error) {
        console.error('Error loading orders:', error);
        renderOrders([]);
    }
}

function filterOrders() {
    const searchQuery = document.getElementById('search-orders').value.toLowerCase();
    const statusFilter = document.getElementById('filter-status').value;
    const typeFilter = document.getElementById('filter-type').value;

    filteredOrders = allOrders.filter(order => {
        // Search filter
        if (searchQuery) {
            const searchMatch =
                (order.order_number || '').toLowerCase().includes(searchQuery) ||
                (order.event_name || '').toLowerCase().includes(searchQuery);
            if (!searchMatch) return false;
        }

        // Status filter
        if (statusFilter && order.status !== statusFilter) return false;

        // Type filter
        if (typeFilter && order.type !== typeFilter) return false;

        return true;
    });

    currentPage = 1;
    renderOrders(filteredOrders);
    renderPagination();
}

function renderOrders(orders) {
    const container = document.getElementById('orders-list');

    // Get current page slice
    const start = (currentPage - 1) * perPage;
    const end = start + perPage;
    const pageOrders = orders.slice(start, end);

    if (!pageOrders.length) {
        container.innerHTML = '<tr><td colspan="7" class="px-6 py-12 text-center text-muted">Nu exista comenzi</td></tr>';
        return;
    }

    const typeLabels = {
        featuring: 'Promovare',
        email: 'Email Marketing',
        tracking: 'Ad Tracking',
        campaign: 'Creare Campanie'
    };

    const typeColors = {
        featuring: 'primary',
        email: 'success',
        tracking: 'blue-600',
        campaign: 'warning'
    };

    const statusLabels = {
        draft: 'Draft',
        pending_payment: 'Asteapta Plata',
        processing: 'In Procesare',
        active: 'Activ',
        completed: 'Finalizat',
        cancelled: 'Anulat',
        refunded: 'Rambursat'
    };

    const statusColors = {
        draft: 'muted',
        pending_payment: 'warning',
        processing: 'blue-600',
        active: 'success',
        completed: 'primary',
        cancelled: 'error',
        refunded: 'error'
    };

    container.innerHTML = pageOrders.map(order => {
        const typeLabel = typeLabels[order.type] || order.type_label || order.type;
        const typeColor = typeColors[order.type] || 'primary';
        const statusLabel = statusLabels[order.status] || order.status_label || order.status;
        const statusColor = statusColors[order.status] || 'muted';
        const period = order.service_start_date && order.service_end_date
            ? `${formatDate(order.service_start_date)} - ${formatDate(order.service_end_date)}`
            : '-';

        return `
            <tr class="hover:bg-surface/50">
                <td class="px-6 py-4">
                    <div class="flex items-center gap-3">
                        <div class="w-10 h-10 bg-surface rounded-xl flex items-center justify-center">
                            <svg class="w-5 h-5 text-secondary" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/></svg>
                        </div>
                        <div>
                            <p class="font-medium text-secondary">${order.order_number}</p>
                            <p class="text-xs text-muted">${order.payment_status === 'paid' ? 'Platit' : 'Neplătit'}</p>
                        </div>
                    </div>
                </td>
                <td class="px-6 py-4">
                    <span class="px-2.5 py-1 bg-${typeColor}/10 text-${typeColor} text-xs font-medium rounded-lg">${typeLabel}</span>
                </td>
                <td class="px-6 py-4">
                    <p class="text-sm text-secondary">${order.event_name || '-'}</p>
                </td>
                <td class="px-6 py-4">
                    <p class="text-sm text-muted">${period}</p>
                </td>
                <td class="px-6 py-4 text-right">
                    <p class="font-semibold text-secondary">${formatCurrency(order.total)} ${order.currency}</p>
                </td>
                <td class="px-6 py-4 text-center">
                    <span class="px-2.5 py-1 bg-${statusColor}/10 text-${statusColor} text-xs font-medium rounded-lg">${statusLabel}</span>
                </td>
                <td class="px-6 py-4 text-right">
                    <p class="text-sm text-muted">${formatDateTime(order.created_at)}</p>
                </td>
            </tr>
        `;
    }).join('');
}

function renderPagination() {
    const totalItems = filteredOrders.length;
    const totalPages = Math.ceil(totalItems / perPage);
    const start = (currentPage - 1) * perPage + 1;
    const end = Math.min(currentPage * perPage, totalItems);

    document.getElementById('pagination-info').textContent = totalItems > 0
        ? `Afisare ${start}-${end} din ${totalItems} comenzi`
        : 'Nu exista comenzi';

    const buttonsContainer = document.getElementById('pagination-buttons');

    if (totalPages <= 1) {
        buttonsContainer.innerHTML = '';
        return;
    }

    let buttons = '';

    // Previous button
    buttons += `<button onclick="goToPage(${currentPage - 1})" class="px-3 py-1 rounded-lg border border-border text-sm ${currentPage === 1 ? 'opacity-50 cursor-not-allowed' : 'hover:bg-surface'}" ${currentPage === 1 ? 'disabled' : ''}>
        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
    </button>`;

    // Page numbers
    for (let i = 1; i <= totalPages; i++) {
        if (i === 1 || i === totalPages || (i >= currentPage - 1 && i <= currentPage + 1)) {
            buttons += `<button onclick="goToPage(${i})" class="px-3 py-1 rounded-lg text-sm ${i === currentPage ? 'bg-primary text-white' : 'border border-border hover:bg-surface'}">${i}</button>`;
        } else if (i === currentPage - 2 || i === currentPage + 2) {
            buttons += '<span class="px-2">...</span>';
        }
    }

    // Next button
    buttons += `<button onclick="goToPage(${currentPage + 1})" class="px-3 py-1 rounded-lg border border-border text-sm ${currentPage === totalPages ? 'opacity-50 cursor-not-allowed' : 'hover:bg-surface'}" ${currentPage === totalPages ? 'disabled' : ''}>
        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
    </button>`;

    buttonsContainer.innerHTML = buttons;
}

function goToPage(page) {
    const totalPages = Math.ceil(filteredOrders.length / perPage);
    if (page < 1 || page > totalPages) return;
    currentPage = page;
    renderOrders(filteredOrders);
    renderPagination();
}

function formatDate(dateStr) {
    if (!dateStr) return '-';
    const date = new Date(dateStr);
    return date.toLocaleDateString('ro-RO', { day: '2-digit', month: '2-digit', year: 'numeric' });
}

function formatDateTime(dateStr) {
    if (!dateStr) return '-';
    const date = new Date(dateStr);
    return date.toLocaleDateString('ro-RO', { day: '2-digit', month: '2-digit', year: 'numeric', hour: '2-digit', minute: '2-digit' });
}

function formatCurrency(amount) {
    return new Intl.NumberFormat('ro-RO', { minimumFractionDigits: 2, maximumFractionDigits: 2 }).format(amount);
}
</script>
JS;
require_once dirname(__DIR__) . '/includes/scripts.php';
?>
