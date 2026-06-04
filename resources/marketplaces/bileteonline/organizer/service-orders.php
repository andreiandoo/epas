<?php
/**
 * bilete.online — Organizator › Comenzile Mele (Servicii) (v3).
 * Route: /organizator/servicii/comenzi
 *
 * History of extra-service orders: stats cards, search + status/type filters,
 * paginated table. Ported 1:1 from ambilet to v3 + shell. Activity-centric copy.
 * All JS logic / IDs / field names preserved; wired to organizer.services.* proxy
 * actions via BileteOnlineAPI. NOTE: the `/organizer/services/orders/stats`
 * endpoint the source loadStats() hits is not distinctly mapped in api.js (it is
 * swallowed by the generic orders.show regex); call kept as-is per source.
 */
require_once dirname(__DIR__) . '/includes/config.php';
$pageTitle   = 'Comenzile Mele - Servicii';
$currentPage = 'services';
require_once dirname(__DIR__) . '/includes/head.php';
require_once dirname(__DIR__) . '/includes/organizer-sidebar.php';
?>
<div class="flex min-w-0 flex-1 flex-col">
    <?php require_once dirname(__DIR__) . '/includes/organizer-topbar.php'; ?>

    <main class="flex-1 p-4 lg:p-8">
        <!-- Page Header -->
        <div class="mb-6 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <nav class="mb-2 flex items-center gap-2 text-sm text-ink-soft">
                    <a href="/organizator/servicii" class="hover:text-vermilion">Servicii Extra</a>
                    <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/></svg>
                    <span class="text-ink">Comenzile Mele</span>
                </nav>
                <h1 class="font-display text-3xl font-bold leading-none">Comenzile Mele</h1>
                <p class="mt-1.5 text-sm text-ink-soft">Istoric comenzi servicii extra.</p>
            </div>
            <a href="/organizator/servicii" class="inline-flex items-center gap-2 self-start rounded-full border-2 border-ink px-4 py-2.5 text-sm font-bold transition hover:bg-ink hover:text-paper sm:self-auto">
                <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M11 17l-5-5m0 0l5-5m-5 5h12"/></svg>
                Înapoi la Servicii
            </a>
        </div>

        <!-- Stats Cards -->
        <div class="mb-8 grid grid-cols-2 gap-4 lg:grid-cols-4">
            <div class="rounded-2xl border-2 border-ink bg-paper p-6">
                <div class="mb-3 flex items-center gap-3">
                    <span class="grid h-10 w-10 place-items-center rounded-xl bg-vermilion/10 text-vermilion"><svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/></svg></span>
                    <span class="text-sm text-ink-soft">Total Comenzi</span>
                </div>
                <p class="font-display text-2xl font-bold" id="total-orders">0</p>
            </div>
            <div class="rounded-2xl border-2 border-ink bg-paper p-6">
                <div class="mb-3 flex items-center gap-3">
                    <span class="grid h-10 w-10 place-items-center rounded-xl bg-forest/10 text-forest"><svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6"/></svg></span>
                    <span class="text-sm text-ink-soft">Servicii Active</span>
                </div>
                <p class="font-display text-2xl font-bold" id="active-count">0</p>
            </div>
            <div class="rounded-2xl border-2 border-ink bg-paper p-6">
                <div class="mb-3 flex items-center gap-3">
                    <span class="grid h-10 w-10 place-items-center rounded-xl bg-ochre/10 text-ochre"><svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg></span>
                    <span class="text-sm text-ink-soft">În Așteptare</span>
                </div>
                <p class="font-display text-2xl font-bold" id="pending-count">0</p>
            </div>
            <div class="rounded-2xl border-2 border-ink bg-paper p-6">
                <div class="mb-3 flex items-center gap-3">
                    <span class="grid h-10 w-10 place-items-center rounded-xl bg-sky/10 text-sky"><svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg></span>
                    <span class="text-sm text-ink-soft">Investit Total</span>
                </div>
                <p class="font-display text-2xl font-bold" id="total-spent">0 RON</p>
            </div>
        </div>

        <!-- Filters -->
        <div class="mb-6 rounded-2xl border-2 border-ink bg-paper p-4">
            <div class="flex flex-wrap items-center gap-4">
                <div class="min-w-[200px] flex-1">
                    <input type="text" id="search-orders" placeholder="Caută după număr comandă sau activitate..." class="w-full rounded-xl border-2 border-ink/15 bg-paper-2 px-4 py-2.5 text-sm outline-none transition focus:border-ink">
                </div>
                <select id="filter-status" class="w-40 rounded-xl border-2 border-ink/15 bg-paper-2 px-4 py-2.5 text-sm outline-none transition focus:border-ink">
                    <option value="">Toate statusurile</option>
                    <option value="pending_payment">Așteaptă Plata</option>
                    <option value="processing">În Procesare</option>
                    <option value="active">Activ</option>
                    <option value="completed">Finalizat</option>
                    <option value="cancelled">Anulat</option>
                </select>
                <select id="filter-type" class="w-40 rounded-xl border-2 border-ink/15 bg-paper-2 px-4 py-2.5 text-sm outline-none transition focus:border-ink">
                    <option value="">Toate tipurile</option>
                    <option value="featuring">Promovare</option>
                    <option value="email">Email Marketing</option>
                    <option value="tracking">Ad Tracking</option>
                    <option value="campaign">Creare Campanie</option>
                </select>
            </div>
        </div>

        <!-- Orders List -->
        <div class="overflow-hidden rounded-2xl border-2 border-ink bg-paper">
            <div class="overflow-x-auto">
                <table class="w-full">
                    <thead class="bg-paper-2">
                        <tr>
                            <th class="px-6 py-4 text-left text-sm font-bold text-ink">Comandă</th>
                            <th class="px-6 py-4 text-left text-sm font-bold text-ink">Tip Serviciu</th>
                            <th class="px-6 py-4 text-left text-sm font-bold text-ink">Activitate</th>
                            <th class="px-6 py-4 text-left text-sm font-bold text-ink">Perioadă</th>
                            <th class="px-6 py-4 text-right text-sm font-bold text-ink">Total</th>
                            <th class="px-6 py-4 text-center text-sm font-bold text-ink">Status</th>
                            <th class="px-6 py-4 text-right text-sm font-bold text-ink">Data</th>
                        </tr>
                    </thead>
                    <tbody id="orders-list" class="divide-y divide-ink/10"></tbody>
                </table>
            </div>
            <!-- Pagination -->
            <div id="pagination" class="flex items-center justify-between border-t-2 border-ink/10 px-6 py-4">
                <p class="text-sm text-ink-soft" id="pagination-info">Se încarcă...</p>
                <div class="flex items-center gap-2" id="pagination-buttons"></div>
            </div>
        </div>
    </main>

    <?php require_once dirname(__DIR__) . '/includes/organizer-footer.php'; ?>
</div>

<?php
$scriptsExtra = <<<'JS'
<script>
function orgNotify(msg, type) {
    try { if (typeof BileteOnlineNotifications !== 'undefined' && BileteOnlineNotifications[type || 'info']) { BileteOnlineNotifications[type || 'info'](msg); return; } } catch (e) {}
    if (type === 'error' || type === 'warning') alert(msg);
}
function escHtml(s) { const d = document.createElement('div'); d.textContent = s == null ? '' : s; return d.innerHTML; }

let allOrders = [];
let filteredOrders = [];
let currentPage = 1;
const perPage = 20;

document.addEventListener('DOMContentLoaded', function() {
    if (typeof BileteOnlineAuth !== 'undefined' && BileteOnlineAuth.requireOrganizerAuth && !BileteOnlineAuth.requireOrganizerAuth()) return;
    loadOrders();
    loadStats();

    document.getElementById('search-orders').addEventListener('input', BileteOnlineUtils.debounce(filterOrders, 300));
    document.getElementById('filter-status').addEventListener('change', filterOrders);
    document.getElementById('filter-type').addEventListener('change', filterOrders);
});

async function loadStats() {
    try {
        const response = await BileteOnlineAPI.get('/organizer/services/orders/stats');
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
        const response = await BileteOnlineAPI.get('/organizer/services/orders');
        if (response.success) {
            allOrders = response.data.data || [];
            document.getElementById('total-orders').textContent = response.data.total || allOrders.length;

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
        if (searchQuery) {
            const searchMatch =
                (order.order_number || '').toLowerCase().includes(searchQuery) ||
                (order.event_name || '').toLowerCase().includes(searchQuery);
            if (!searchMatch) return false;
        }

        if (statusFilter && order.status !== statusFilter) return false;

        if (typeFilter && order.type !== typeFilter) return false;

        return true;
    });

    currentPage = 1;
    renderOrders(filteredOrders);
    renderPagination();
}

const ORDER_TYPE_BADGE = {
    featuring: 'bg-vermilion/10 text-vermilion',
    email: 'bg-forest/10 text-forest',
    tracking: 'bg-sky/10 text-sky',
    campaign: 'bg-ochre/10 text-ochre'
};

const ORDER_STATUS_BADGE = {
    draft: 'bg-ink/10 text-ink-soft',
    pending_payment: 'bg-ochre/10 text-ochre',
    processing: 'bg-sky/10 text-sky',
    active: 'bg-forest/10 text-forest',
    completed: 'bg-vermilion/10 text-vermilion',
    cancelled: 'bg-vermilion/10 text-vermilion',
    refunded: 'bg-vermilion/10 text-vermilion'
};

function renderOrders(orders) {
    const container = document.getElementById('orders-list');

    const start = (currentPage - 1) * perPage;
    const end = start + perPage;
    const pageOrders = orders.slice(start, end);

    if (!pageOrders.length) {
        container.innerHTML = '<tr><td colspan="7" class="px-6 py-12 text-center text-ink-soft">Nu există comenzi</td></tr>';
        return;
    }

    const typeLabels = {
        featuring: 'Promovare',
        email: 'Email Marketing',
        tracking: 'Ad Tracking',
        campaign: 'Creare Campanie'
    };

    const statusLabels = {
        draft: 'Draft',
        pending_payment: 'Așteaptă Plata',
        processing: 'În Procesare',
        active: 'Activ',
        completed: 'Finalizat',
        cancelled: 'Anulat',
        refunded: 'Rambursat'
    };

    container.innerHTML = pageOrders.map(order => {
        const typeLabel = typeLabels[order.type] || order.type_label || order.type;
        const typeBadge = ORDER_TYPE_BADGE[order.type] || 'bg-vermilion/10 text-vermilion';
        const statusLabel = statusLabels[order.status] || order.status_label || order.status;
        const statusBadge = ORDER_STATUS_BADGE[order.status] || 'bg-ink/10 text-ink-soft';
        const period = order.service_start_date && order.service_end_date
            ? `${formatDate(order.service_start_date)} - ${formatDate(order.service_end_date)}`
            : '-';

        return `
            <tr class="hover:bg-paper-2/50">
                <td class="px-6 py-4">
                    <div class="flex items-center gap-3">
                        <span class="grid h-10 w-10 place-items-center rounded-xl bg-paper-2 text-ink">
                            <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/></svg>
                        </span>
                        <div>
                            <p class="font-medium text-ink">${escHtml(order.order_number)}</p>
                            <p class="text-xs text-ink-soft">${order.payment_status === 'paid' ? 'Plătit' : 'Neplătit'}</p>
                        </div>
                    </div>
                </td>
                <td class="px-6 py-4">
                    <span class="rounded-lg ${typeBadge} px-2.5 py-1 text-xs font-bold">${escHtml(typeLabel)}</span>
                </td>
                <td class="px-6 py-4">
                    <p class="text-sm text-ink">${escHtml(order.event_name) || '-'}</p>
                </td>
                <td class="px-6 py-4">
                    <p class="text-sm text-ink-soft">${period}</p>
                </td>
                <td class="px-6 py-4 text-right">
                    <p class="font-bold text-ink">${formatCurrency(order.total)} ${escHtml(order.currency)}</p>
                </td>
                <td class="px-6 py-4 text-center">
                    <span class="rounded-lg ${statusBadge} px-2.5 py-1 text-xs font-bold">${escHtml(statusLabel)}</span>
                </td>
                <td class="px-6 py-4 text-right">
                    <p class="text-sm text-ink-soft">${formatDateTime(order.created_at)}</p>
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
        ? `Afișare ${start}-${end} din ${totalItems} comenzi`
        : 'Nu există comenzi';

    const buttonsContainer = document.getElementById('pagination-buttons');

    if (totalPages <= 1) {
        buttonsContainer.innerHTML = '';
        return;
    }

    let buttons = '';

    buttons += `<button onclick="goToPage(${currentPage - 1})" class="rounded-lg border-2 border-ink/15 px-3 py-1 text-sm ${currentPage === 1 ? 'opacity-50 cursor-not-allowed' : 'hover:bg-paper-2'}" ${currentPage === 1 ? 'disabled' : ''}>
        <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7"/></svg>
    </button>`;

    for (let i = 1; i <= totalPages; i++) {
        if (i === 1 || i === totalPages || (i >= currentPage - 1 && i <= currentPage + 1)) {
            buttons += `<button onclick="goToPage(${i})" class="rounded-lg px-3 py-1 text-sm font-bold ${i === currentPage ? 'bg-vermilion text-paper' : 'border-2 border-ink/15 hover:bg-paper-2'}">${i}</button>`;
        } else if (i === currentPage - 2 || i === currentPage + 2) {
            buttons += '<span class="px-2">...</span>';
        }
    }

    buttons += `<button onclick="goToPage(${currentPage + 1})" class="rounded-lg border-2 border-ink/15 px-3 py-1 text-sm ${currentPage === totalPages ? 'opacity-50 cursor-not-allowed' : 'hover:bg-paper-2'}" ${currentPage === totalPages ? 'disabled' : ''}>
        <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/></svg>
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
