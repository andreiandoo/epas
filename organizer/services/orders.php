<?php
/**
 * Service Orders History
 * Shows all service orders for the organizer
 */
require_once dirname(__DIR__, 2) . '/includes/config.php';
$pageTitle = 'Comenzile Mele - Servicii';
$bodyClass = 'min-h-screen flex bg-slate-100';
$currentPage = 'services';
require_once dirname(__DIR__, 2) . '/includes/head.php';
require_once dirname(__DIR__, 2) . '/includes/organizer-sidebar.php';
?>

    <!-- Main Content -->
    <div class="flex-1 flex flex-col min-h-screen lg:ml-0">
        <?php require_once dirname(__DIR__, 2) . '/includes/organizer-topbar.php'; ?>

        <main class="flex-1 p-4 lg:p-8">
            <!-- Page Header -->
            <div class="flex items-center justify-between mb-6">
                <div>
                    <div class="flex items-center gap-3 mb-2">
                        <a href="/organizator/services" class="text-muted hover:text-secondary">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/></svg>
                        </a>
                        <h1 class="text-2xl font-bold text-secondary">Comenzile Mele</h1>
                    </div>
                    <p class="text-sm text-muted">Istoricul comenzilor de servicii extra</p>
                </div>
                <a href="/organizator/services" class="btn btn-primary">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                    Comanda Noua
                </a>
            </div>

            <!-- Stats Summary -->
            <div class="grid grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
                <div class="bg-white rounded-2xl border border-border p-5">
                    <p class="text-sm text-muted mb-1">Total Comenzi</p>
                    <p class="text-2xl font-bold text-secondary" id="stat-total">0</p>
                </div>
                <div class="bg-white rounded-2xl border border-border p-5">
                    <p class="text-sm text-muted mb-1">Active</p>
                    <p class="text-2xl font-bold text-success" id="stat-active">0</p>
                </div>
                <div class="bg-white rounded-2xl border border-border p-5">
                    <p class="text-sm text-muted mb-1">In Asteptare</p>
                    <p class="text-2xl font-bold text-warning" id="stat-pending">0</p>
                </div>
                <div class="bg-white rounded-2xl border border-border p-5">
                    <p class="text-sm text-muted mb-1">Total Investit</p>
                    <p class="text-2xl font-bold text-primary" id="stat-spent">0 RON</p>
                </div>
            </div>

            <!-- Filters -->
            <div class="bg-white rounded-2xl border border-border p-4 mb-6">
                <div class="flex flex-wrap gap-4">
                    <div class="flex-1 min-w-[200px]">
                        <label class="label text-xs">Tip Serviciu</label>
                        <select id="filter-type" class="input w-full">
                            <option value="">Toate</option>
                            <option value="featuring">Promovare Eveniment</option>
                            <option value="email">Email Marketing</option>
                            <option value="tracking">Ad Tracking</option>
                            <option value="campaign">Campanii Ads</option>
                        </select>
                    </div>
                    <div class="flex-1 min-w-[200px]">
                        <label class="label text-xs">Status</label>
                        <select id="filter-status" class="input w-full">
                            <option value="">Toate</option>
                            <option value="pending_payment">Asteapta Plata</option>
                            <option value="processing">In Procesare</option>
                            <option value="active">Activ</option>
                            <option value="completed">Finalizat</option>
                            <option value="cancelled">Anulat</option>
                        </select>
                    </div>
                    <div class="flex-1 min-w-[200px]">
                        <label class="label text-xs">Perioada</label>
                        <select id="filter-period" class="input w-full">
                            <option value="">Toate</option>
                            <option value="7">Ultimele 7 zile</option>
                            <option value="30">Ultimele 30 zile</option>
                            <option value="90">Ultimele 90 zile</option>
                            <option value="365">Ultimul an</option>
                        </select>
                    </div>
                    <div class="flex items-end">
                        <button onclick="resetFilters()" class="btn btn-secondary">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg>
                            Reset
                        </button>
                    </div>
                </div>
            </div>

            <!-- Orders Table -->
            <div class="bg-white rounded-2xl border border-border overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="w-full">
                        <thead class="bg-surface">
                            <tr>
                                <th class="px-6 py-4 text-left text-sm font-semibold text-secondary">Comanda</th>
                                <th class="px-6 py-4 text-left text-sm font-semibold text-secondary">Serviciu</th>
                                <th class="px-6 py-4 text-left text-sm font-semibold text-secondary">Eveniment</th>
                                <th class="px-6 py-4 text-left text-sm font-semibold text-secondary">Total</th>
                                <th class="px-6 py-4 text-left text-sm font-semibold text-secondary">Status</th>
                                <th class="px-6 py-4 text-left text-sm font-semibold text-secondary">Data</th>
                                <th class="px-6 py-4 text-right text-sm font-semibold text-secondary">Actiuni</th>
                            </tr>
                        </thead>
                        <tbody id="orders-list" class="divide-y divide-border">
                            <tr>
                                <td colspan="7" class="px-6 py-12 text-center">
                                    <div class="animate-pulse flex flex-col items-center">
                                        <div class="w-12 h-12 bg-surface rounded-full mb-4"></div>
                                        <div class="h-4 bg-surface rounded w-32"></div>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <!-- Pagination -->
                <div id="pagination" class="px-6 py-4 border-t border-border flex items-center justify-between">
                    <p class="text-sm text-muted" id="pagination-info">Se incarca...</p>
                    <div class="flex gap-2" id="pagination-buttons"></div>
                </div>
            </div>
        </main>
    </div>

    <!-- Order Detail Modal -->
    <div id="order-modal" class="fixed inset-0 bg-black/50 z-50 hidden items-center justify-center p-4">
        <div class="bg-white rounded-2xl max-w-2xl w-full max-h-[90vh] overflow-y-auto">
            <div class="sticky top-0 bg-white p-6 border-b border-border flex items-center justify-between">
                <div>
                    <h3 class="text-xl font-bold text-secondary" id="modal-order-number">Comanda #---</h3>
                    <p class="text-sm text-muted" id="modal-order-date"></p>
                </div>
                <button onclick="closeOrderModal()" class="p-2 hover:bg-surface rounded-lg">
                    <svg class="w-5 h-5 text-muted" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                </button>
            </div>

            <div class="p-6" id="modal-content">
                <!-- Content loaded dynamically -->
            </div>

            <div class="sticky bottom-0 bg-white p-6 border-t border-border flex gap-3">
                <button onclick="closeOrderModal()" class="btn btn-secondary flex-1">Inchide</button>
                <a href="#" id="modal-invoice-btn" class="btn btn-primary flex-1 hidden">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                    Descarca Factura
                </a>
            </div>
        </div>
    </div>

<?php
$scriptsExtra = <<<'JS'
<script>
AmbiletAuth.requireOrganizerAuth();

let orders = [];
let currentPage = 1;
let totalPages = 1;
const perPage = 10;

const serviceTypes = {
    featuring: { label: 'Promovare', color: 'primary', icon: '⭐' },
    email: { label: 'Email Marketing', color: 'accent', icon: '📧' },
    tracking: { label: 'Ad Tracking', color: 'blue-600', icon: '📊' },
    campaign: { label: 'Campanie Ads', color: 'purple-600', icon: '📢' }
};

const statusConfig = {
    draft: { label: 'Ciorna', color: 'muted', bg: 'bg-gray-100' },
    pending_payment: { label: 'Asteapta Plata', color: 'warning', bg: 'bg-amber-100' },
    processing: { label: 'In Procesare', color: 'blue-600', bg: 'bg-blue-100' },
    active: { label: 'Activ', color: 'success', bg: 'bg-green-100' },
    completed: { label: 'Finalizat', color: 'muted', bg: 'bg-gray-100' },
    cancelled: { label: 'Anulat', color: 'error', bg: 'bg-red-100' },
    refunded: { label: 'Rambursat', color: 'error', bg: 'bg-red-100' }
};

document.addEventListener('DOMContentLoaded', function() {
    loadOrders();
    setupFilters();
});

async function loadOrders() {
    const type = document.getElementById('filter-type').value;
    const status = document.getElementById('filter-status').value;
    const period = document.getElementById('filter-period').value;

    const params = { page: currentPage, per_page: perPage };
    if (type) params.type = type;
    if (status) params.status = status;
    if (period) params.days = period;

    try {
        const response = await AmbiletAPI.get('/organizer/services/orders', params);
        if (response.success) {
            orders = response.data.orders || [];
            totalPages = response.data.pagination?.total_pages || 1;
            updateStats(response.data.stats || {});
            renderOrders();
            renderPagination();
        }
    } catch (e) {
        console.error('Error loading orders:', e);
        renderEmptyState();
    }
}

function updateStats(stats) {
    document.getElementById('stat-total').textContent = stats.total || 0;
    document.getElementById('stat-active').textContent = stats.active || 0;
    document.getElementById('stat-pending').textContent = stats.pending || 0;
    document.getElementById('stat-spent').textContent = AmbiletUtils.formatCurrency(stats.total_spent || 0);
}

function renderOrders() {
    const container = document.getElementById('orders-list');

    if (!orders.length) {
        renderEmptyState();
        return;
    }

    container.innerHTML = orders.map(order => {
        const type = serviceTypes[order.service_type] || serviceTypes.featuring;
        const status = statusConfig[order.status] || statusConfig.draft;

        return `
            <tr class="hover:bg-surface/50 cursor-pointer" onclick="viewOrder('${order.id}')">
                <td class="px-6 py-4">
                    <p class="font-medium text-secondary">#${order.order_number || order.id}</p>
                </td>
                <td class="px-6 py-4">
                    <div class="flex items-center gap-2">
                        <span class="text-lg">${type.icon}</span>
                        <span class="text-sm font-medium text-${type.color}">${type.label}</span>
                    </div>
                </td>
                <td class="px-6 py-4">
                    <p class="font-medium text-secondary truncate max-w-[200px]">${order.event?.title || 'N/A'}</p>
                </td>
                <td class="px-6 py-4">
                    <p class="font-bold text-secondary">${AmbiletUtils.formatCurrency(order.total)}</p>
                </td>
                <td class="px-6 py-4">
                    <span class="px-3 py-1 ${status.bg} text-${status.color} text-sm font-medium rounded-full">
                        ${status.label}
                    </span>
                </td>
                <td class="px-6 py-4 text-sm text-muted">
                    ${AmbiletUtils.formatDate(order.created_at)}
                </td>
                <td class="px-6 py-4 text-right">
                    <div class="flex items-center justify-end gap-2">
                        ${order.status === 'pending_payment' ? `
                            <button onclick="event.stopPropagation(); payOrder('${order.id}')" class="p-2 text-primary hover:bg-primary/10 rounded-lg" title="Plateste">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z"/></svg>
                            </button>
                        ` : ''}
                        ${order.payment_status === 'paid' && order.invoice_url ? `
                            <a href="${order.invoice_url}" target="_blank" onclick="event.stopPropagation()" class="p-2 text-muted hover:text-secondary hover:bg-surface rounded-lg" title="Descarca Factura">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                            </a>
                        ` : ''}
                        <button onclick="event.stopPropagation(); viewOrder('${order.id}')" class="p-2 text-muted hover:text-secondary hover:bg-surface rounded-lg" title="Detalii">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
                        </button>
                    </div>
                </td>
            </tr>
        `;
    }).join('');
}

function renderEmptyState() {
    const container = document.getElementById('orders-list');
    container.innerHTML = `
        <tr>
            <td colspan="7" class="px-6 py-12 text-center">
                <div class="flex flex-col items-center">
                    <div class="w-16 h-16 bg-surface rounded-full flex items-center justify-center mb-4">
                        <svg class="w-8 h-8 text-muted" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/></svg>
                    </div>
                    <p class="text-muted mb-4">Nu ai comenzi de servicii</p>
                    <a href="/organizator/services" class="btn btn-primary">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                        Cumpara Servicii
                    </a>
                </div>
            </td>
        </tr>
    `;
}

function renderPagination() {
    const info = document.getElementById('pagination-info');
    const buttons = document.getElementById('pagination-buttons');

    const start = (currentPage - 1) * perPage + 1;
    const end = Math.min(currentPage * perPage, orders.length + (currentPage - 1) * perPage);
    info.textContent = `Afisez ${start}-${end} din ${totalPages * perPage} comenzi`;

    let html = '';
    if (currentPage > 1) {
        html += `<button onclick="goToPage(${currentPage - 1})" class="px-3 py-1 border border-border rounded-lg hover:bg-surface">Prev</button>`;
    }
    for (let i = Math.max(1, currentPage - 2); i <= Math.min(totalPages, currentPage + 2); i++) {
        html += `<button onclick="goToPage(${i})" class="px-3 py-1 border ${i === currentPage ? 'border-primary bg-primary text-white' : 'border-border hover:bg-surface'} rounded-lg">${i}</button>`;
    }
    if (currentPage < totalPages) {
        html += `<button onclick="goToPage(${currentPage + 1})" class="px-3 py-1 border border-border rounded-lg hover:bg-surface">Next</button>`;
    }
    buttons.innerHTML = html;
}

function goToPage(page) {
    currentPage = page;
    loadOrders();
}

function setupFilters() {
    ['filter-type', 'filter-status', 'filter-period'].forEach(id => {
        document.getElementById(id).addEventListener('change', () => {
            currentPage = 1;
            loadOrders();
        });
    });
}

function resetFilters() {
    document.getElementById('filter-type').value = '';
    document.getElementById('filter-status').value = '';
    document.getElementById('filter-period').value = '';
    currentPage = 1;
    loadOrders();
}

async function viewOrder(orderId) {
    try {
        const response = await AmbiletAPI.get(`/organizer/services/orders/${orderId}`);
        if (response.success) {
            showOrderModal(response.data.order);
        }
    } catch (e) {
        AmbiletNotifications.error('Eroare la incarcarea detaliilor comenzii');
    }
}

function showOrderModal(order) {
    const type = serviceTypes[order.service_type] || serviceTypes.featuring;
    const status = statusConfig[order.status] || statusConfig.draft;

    document.getElementById('modal-order-number').textContent = `Comanda #${order.order_number || order.id}`;
    document.getElementById('modal-order-date').textContent = AmbiletUtils.formatDateTime(order.created_at);

    let configHtml = '';
    switch (order.service_type) {
        case 'featuring':
            const locationLabels = { home: 'Pagina Principala', category: 'Categorie', genre: 'Gen', city: 'Oras' };
            configHtml = `
                <p><strong>Locatii:</strong> ${(order.config?.locations || []).map(l => locationLabels[l]).join(', ')}</p>
                <p><strong>Perioada:</strong> ${AmbiletUtils.formatDate(order.config?.start_date)} - ${AmbiletUtils.formatDate(order.config?.end_date)}</p>
            `;
            break;
        case 'email':
            const audienceLabels = { all: 'Baza Completa', filtered: 'Audienta Filtrata', own: 'Clientii Tai' };
            configHtml = `
                <p><strong>Audienta:</strong> ${audienceLabels[order.config?.audience] || 'N/A'}</p>
                <p><strong>Destinatari:</strong> ${AmbiletUtils.formatNumber(order.config?.recipient_count || 0)}</p>
                ${order.config?.sent_at ? `
                    <p><strong>Trimis la:</strong> ${AmbiletUtils.formatDateTime(order.config.sent_at)}</p>
                ` : ''}
                ${order.email_stats ? `
                    <div class="mt-4 p-4 bg-surface rounded-xl">
                        <p class="font-semibold text-secondary mb-2">Statistici Campanie (Brevo)</p>
                        <div class="grid grid-cols-3 gap-4 text-center">
                            <div>
                                <p class="text-2xl font-bold text-success">${order.email_stats.open_rate || 0}%</p>
                                <p class="text-xs text-muted">Open Rate</p>
                            </div>
                            <div>
                                <p class="text-2xl font-bold text-primary">${order.email_stats.click_rate || 0}%</p>
                                <p class="text-xs text-muted">Click Rate</p>
                            </div>
                            <div>
                                <p class="text-2xl font-bold text-secondary">${AmbiletUtils.formatNumber(order.email_stats.delivered || 0)}</p>
                                <p class="text-xs text-muted">Livrate</p>
                            </div>
                        </div>
                        <div class="grid grid-cols-3 gap-4 text-center mt-3">
                            <div>
                                <p class="text-lg font-bold text-muted">${AmbiletUtils.formatNumber(order.email_stats.bounced || 0)}</p>
                                <p class="text-xs text-muted">Bounce</p>
                            </div>
                            <div>
                                <p class="text-lg font-bold text-muted">${AmbiletUtils.formatNumber(order.email_stats.unsubscribed || 0)}</p>
                                <p class="text-xs text-muted">Dezabonari</p>
                            </div>
                            <div>
                                <p class="text-lg font-bold text-muted">${AmbiletUtils.formatNumber(order.email_stats.complaints || 0)}</p>
                                <p class="text-xs text-muted">Reclamatii</p>
                            </div>
                        </div>
                    </div>
                ` : ''}
            `;
            break;
        case 'tracking':
            const platformLabels = { facebook: 'Facebook Pixel', google: 'Google Ads', tiktok: 'TikTok Pixel' };
            configHtml = `
                <p><strong>Platforme:</strong> ${(order.config?.platforms || []).map(p => platformLabels[p]).join(', ')}</p>
                <p><strong>Durata:</strong> ${order.config?.duration_months || 1} luni</p>
            `;
            break;
        case 'campaign':
            const typeLabels = { basic: 'Basic', standard: 'Standard', premium: 'Premium' };
            configHtml = `
                <p><strong>Pachet:</strong> Campanie ${typeLabels[order.config?.campaign_type] || 'Standard'}</p>
                <p><strong>Buget Ads:</strong> ${AmbiletUtils.formatCurrency(order.config?.ad_budget || 0)}</p>
                ${order.config?.notes ? `<p><strong>Note:</strong> ${order.config.notes}</p>` : ''}
            `;
            break;
    }

    document.getElementById('modal-content').innerHTML = `
        <div class="space-y-6">
            <!-- Status -->
            <div class="flex items-center justify-between p-4 ${status.bg} rounded-xl">
                <div class="flex items-center gap-3">
                    <span class="text-2xl">${type.icon}</span>
                    <div>
                        <p class="font-semibold text-secondary">${type.label}</p>
                        <p class="text-sm text-muted">Status: <span class="font-medium text-${status.color}">${status.label}</span></p>
                    </div>
                </div>
                <p class="text-2xl font-bold text-secondary">${AmbiletUtils.formatCurrency(order.total)}</p>
            </div>

            <!-- Event -->
            ${order.event ? `
                <div class="p-4 bg-surface rounded-xl">
                    <p class="text-xs text-muted uppercase tracking-wider mb-2">Eveniment</p>
                    <div class="flex gap-4">
                        <img src="${order.event.image || '/assets/images/default-event.png'}" alt="" class="w-16 h-16 rounded-lg object-cover">
                        <div>
                            <p class="font-semibold text-secondary">${order.event.title}</p>
                            <p class="text-sm text-muted">${AmbiletUtils.formatDate(order.event.date)}</p>
                            <p class="text-sm text-muted">${order.event.venue || ''}</p>
                        </div>
                    </div>
                </div>
            ` : ''}

            <!-- Configuration -->
            <div class="p-4 bg-surface rounded-xl">
                <p class="text-xs text-muted uppercase tracking-wider mb-2">Configuratie</p>
                <div class="text-sm text-secondary space-y-1">
                    ${configHtml}
                </div>
            </div>

            <!-- Payment -->
            <div class="p-4 bg-surface rounded-xl">
                <p class="text-xs text-muted uppercase tracking-wider mb-2">Plata</p>
                <div class="text-sm text-secondary space-y-1">
                    <p><strong>Metoda:</strong> ${order.payment_method === 'card' ? 'Card Bancar' : 'Transfer Bancar'}</p>
                    <p><strong>Status:</strong> ${order.payment_status === 'paid' ? '<span class="text-success">Platit</span>' : '<span class="text-warning">In asteptare</span>'}</p>
                    ${order.paid_at ? `<p><strong>Platit la:</strong> ${AmbiletUtils.formatDateTime(order.paid_at)}</p>` : ''}
                    ${order.payment_reference ? `<p><strong>Referinta:</strong> ${order.payment_reference}</p>` : ''}
                </div>
            </div>

            <!-- Timeline -->
            ${order.timeline && order.timeline.length ? `
                <div class="p-4 bg-surface rounded-xl">
                    <p class="text-xs text-muted uppercase tracking-wider mb-3">Istoric</p>
                    <div class="space-y-3">
                        ${order.timeline.map(item => `
                            <div class="flex gap-3">
                                <div class="w-2 h-2 mt-2 rounded-full bg-${item.type === 'success' ? 'success' : item.type === 'error' ? 'error' : 'muted'}"></div>
                                <div>
                                    <p class="text-sm text-secondary">${item.message}</p>
                                    <p class="text-xs text-muted">${AmbiletUtils.formatDateTime(item.created_at)}</p>
                                </div>
                            </div>
                        `).join('')}
                    </div>
                </div>
            ` : ''}
        </div>
    `;

    // Show/hide invoice button
    const invoiceBtn = document.getElementById('modal-invoice-btn');
    if (order.payment_status === 'paid' && order.invoice_url) {
        invoiceBtn.href = order.invoice_url;
        invoiceBtn.classList.remove('hidden');
    } else {
        invoiceBtn.classList.add('hidden');
    }

    // Show modal
    document.getElementById('order-modal').classList.remove('hidden');
    document.getElementById('order-modal').classList.add('flex');
}

function closeOrderModal() {
    document.getElementById('order-modal').classList.add('hidden');
    document.getElementById('order-modal').classList.remove('flex');
}

async function payOrder(orderId) {
    try {
        const response = await AmbiletAPI.post(`/organizer/services/orders/${orderId}/pay`, {
            return_url: window.location.origin + '/organizator/services/success?order=' + orderId,
            cancel_url: window.location.href
        });

        if (response.success && response.data.payment_url) {
            if (response.data.method === 'POST' && response.data.form_data) {
                const form = document.createElement('form');
                form.method = 'POST';
                form.action = response.data.payment_url;
                for (const [key, value] of Object.entries(response.data.form_data)) {
                    const input = document.createElement('input');
                    input.type = 'hidden';
                    input.name = key;
                    input.value = value;
                    form.appendChild(input);
                }
                document.body.appendChild(form);
                form.submit();
            } else {
                window.location.href = response.data.payment_url;
            }
        }
    } catch (e) {
        AmbiletNotifications.error('Eroare la initierea platii');
    }
}
</script>
JS;
require_once dirname(__DIR__, 2) . '/includes/scripts.php';
?>
